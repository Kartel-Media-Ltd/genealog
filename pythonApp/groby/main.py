"""
Groby — mikroserwis scrapingu rejestrów cmentarnych.

FastAPI REST API wywoływane przez PHP backend (Genealog).
Dwa tryby pracy:
  1. REST API  — PHP wywołuje POST /search/{registry} bezpośrednio
  2. Worker    — serwis polluje tabelę search_jobs w MySQL (opcjonalnie)

Konfiguracja przez zmienne środowiskowe (plik .env lub Docker ENV):
  GROBONET_ENABLED=true
  ECMENTARZE_ENABLED=true
  API_SECRET=tajny_klucz_api     (chronione przez nagłówek X-API-Secret)
  MYSQL_HOST=... (opcjonalnie — dla trybu worker)
  LOG_LEVEL=INFO

Uruchomienie lokalne:
  uvicorn main:app --reload --port 8010

Docker:
  docker compose up
"""

import logging
import os
from contextlib import asynccontextmanager
from typing import Optional

from fastapi import FastAPI, HTTPException, Header, Depends
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field

from scrapers.grobonet import GrobonetScraper
from scrapers.ecmentarze import eCmentarzeScraper
from scrapers.base import ScraperError, RobotsBlocked

# ---------------------------------------------------------------------------
# Konfiguracja
# ---------------------------------------------------------------------------

LOG_LEVEL = os.getenv("LOG_LEVEL", "INFO").upper()
logging.basicConfig(
    level=getattr(logging, LOG_LEVEL, logging.INFO),
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
)
logger = logging.getLogger(__name__)

GROBONET_ENABLED = os.getenv("GROBONET_ENABLED", "true").lower() == "true"
ECMENTARZE_ENABLED = os.getenv("ECMENTARZE_ENABLED", "false").lower() == "true"
API_SECRET = os.getenv("API_SECRET", "")

# ---------------------------------------------------------------------------
# Singletony scraperów (współdzielone między requestami — mają state robots.txt)
# ---------------------------------------------------------------------------

_grobonet: Optional[GrobonetScraper] = None
_ecmentarze: Optional[eCmentarzeScraper] = None


@asynccontextmanager
async def lifespan(app: FastAPI):
    global _grobonet, _ecmentarze
    if GROBONET_ENABLED:
        _grobonet = GrobonetScraper()
        logger.info("Grobonet scraper: WŁĄCZONY")
    else:
        logger.info("Grobonet scraper: wyłączony (GROBONET_ENABLED=false)")

    if ECMENTARZE_ENABLED:
        _ecmentarze = eCmentarzeScraper()
        logger.info("eCmentarze scraper: WŁĄCZONY")
    else:
        logger.info("eCmentarze scraper: wyłączony (ECMENTARZE_ENABLED=false)")

    yield
    logger.info("Zamykam serwis.")


# ---------------------------------------------------------------------------
# Aplikacja FastAPI
# ---------------------------------------------------------------------------

app = FastAPI(
    title="Groby — Cemetery Registry Scraper",
    description="Mikroserwis scrapingu polskich rejestrów cmentarnych dla Genealog.",
    version="1.0.0",
    lifespan=lifespan,
    # Dokumentacja Swagger dostępna pod /docs (wyłącz w prod przez docs_url=None)
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://localhost:8002", os.getenv("SITE_URL", "")],
    allow_methods=["GET", "POST"],
    allow_headers=["X-API-Secret", "Content-Type"],
)


# ---------------------------------------------------------------------------
# Schematy Pydantic
# ---------------------------------------------------------------------------

class SearchRequest(BaseModel):
    last_name: str = Field(..., min_length=2, max_length=100, description="Nazwisko (wymagane)")
    first_name: str = Field("", max_length=100, description="Imię (opcjonalne)")
    birth_year: Optional[int] = Field(None, ge=1000, le=2025, description="Rok urodzenia ±5 lat")
    region: Optional[str] = Field(None, max_length=100, description="Województwo/region (opcjonalne)")


class SearchResult(BaseModel):
    source: str
    first_name: str
    last_name: str
    birth_year: Optional[int]
    death_year: Optional[int]
    cemetery: str
    grave_location: str
    url: Optional[str]


class SearchResponse(BaseModel):
    registry: str
    count: int
    results: list[SearchResult]


# ---------------------------------------------------------------------------
# Autoryzacja
# ---------------------------------------------------------------------------

def verify_api_secret(x_api_secret: str = Header(default="")) -> None:
    """Prosta autoryzacja przez nagłówek X-API-Secret."""
    if API_SECRET and x_api_secret != API_SECRET:
        raise HTTPException(status_code=401, detail="Nieprawidłowy klucz API.")


# ---------------------------------------------------------------------------
# Endpoints
# ---------------------------------------------------------------------------

@app.get("/health")
async def health():
    """Health check — dla Docker HEALTHCHECK i load balancer."""
    return {
        "status": "ok",
        "registries": {
            "grobonet": GROBONET_ENABLED,
            "ecmentarze": ECMENTARZE_ENABLED,
        },
    }


@app.get("/registries")
async def list_registries():
    """Lista dostępnych (włączonych) rejestrów."""
    registries = []
    if GROBONET_ENABLED:
        registries.append({
            "id": "grobonet",
            "name": "Grobonet",
            "description": "Cmentarze komunalne i parafialne (600+ cmentarzy)",
            "url": "https://grobonet.com",
        })
    if ECMENTARZE_ENABLED:
        registries.append({
            "id": "ecmentarze",
            "name": "eCmentarze",
            "description": "Ogólnopolska baza pochowanych (2,36 mln rekordów)",
            "url": "https://www.ecmentarze.pl",
        })
    return {"registries": registries}


@app.post("/search/grobonet", response_model=SearchResponse)
async def search_grobonet(
    body: SearchRequest,
    _: None = Depends(verify_api_secret),
):
    """
    Wyszukiwanie w Grobonet.

    Rate limit: wbudowany w scraper (2s między requestami).
    """
    if not GROBONET_ENABLED or _grobonet is None:
        raise HTTPException(status_code=503, detail="Grobonet jest wyłączony (GROBONET_ENABLED=false).")

    try:
        raw = await _grobonet.search(
            last_name=body.last_name,
            first_name=body.first_name,
            birth_year=body.birth_year,
            region=body.region,
        )
    except RobotsBlocked as exc:
        raise HTTPException(status_code=451, detail=str(exc))
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc))
    except ScraperError as exc:
        logger.error("[/search/grobonet] ScraperError: %s", exc)
        raise HTTPException(status_code=502, detail="Błąd scrapowania Grobonet. Spróbuj ponownie.")

    results = [SearchResult(**r) for r in raw]
    return SearchResponse(registry="grobonet", count=len(results), results=results)


@app.post("/search/ecmentarze", response_model=SearchResponse)
async def search_ecmentarze(
    body: SearchRequest,
    _: None = Depends(verify_api_secret),
):
    """
    Wyszukiwanie w eCmentarze.

    ⚠️  Wymaga weryfikacji robots.txt przed uruchomieniem.
    """
    if not ECMENTARZE_ENABLED or _ecmentarze is None:
        raise HTTPException(status_code=503, detail="eCmentarze jest wyłączony (ECMENTARZE_ENABLED=false).")

    try:
        raw = await _ecmentarze.search(
            last_name=body.last_name,
            first_name=body.first_name,
            birth_year=body.birth_year,
            region=body.region,
        )
    except RobotsBlocked as exc:
        raise HTTPException(status_code=451, detail=str(exc))
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc))
    except ScraperError as exc:
        logger.error("[/search/ecmentarze] ScraperError: %s", exc)
        raise HTTPException(status_code=502, detail="Błąd scrapowania eCmentarze. Spróbuj ponownie.")

    results = [SearchResult(**r) for r in raw]
    return SearchResponse(registry="ecmentarze", count=len(results), results=results)


@app.post("/search/all", response_model=list[SearchResponse])
async def search_all(
    body: SearchRequest,
    _: None = Depends(verify_api_secret),
):
    """
    Wyszukiwanie we wszystkich włączonych rejestrach równolegle.
    Zwraca listę odpowiedzi (po jednej na rejestr).
    """
    import asyncio

    tasks = []
    names = []

    if GROBONET_ENABLED and _grobonet:
        tasks.append(_grobonet.search(
            last_name=body.last_name,
            first_name=body.first_name,
            birth_year=body.birth_year,
            region=body.region,
        ))
        names.append("grobonet")

    if ECMENTARZE_ENABLED and _ecmentarze:
        tasks.append(_ecmentarze.search(
            last_name=body.last_name,
            first_name=body.first_name,
            birth_year=body.birth_year,
            region=body.region,
        ))
        names.append("ecmentarze")

    if not tasks:
        raise HTTPException(status_code=503, detail="Żaden rejestr nie jest włączony.")

    # gather z return_exceptions — jeden błąd nie blokuje innych
    raw_results = await asyncio.gather(*tasks, return_exceptions=True)

    responses = []
    for name, result in zip(names, raw_results):
        if isinstance(result, Exception):
            logger.error("[/search/all] Błąd rejestru %s: %s", name, result)
            responses.append(SearchResponse(registry=name, count=0, results=[]))
        else:
            items = [SearchResult(**r) for r in result]
            responses.append(SearchResponse(registry=name, count=len(items), results=items))

    return responses
