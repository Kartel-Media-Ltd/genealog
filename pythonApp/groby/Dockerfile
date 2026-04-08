# syntax=docker/dockerfile:1
FROM python:3.12-slim

# Metadane
LABEL maintainer="Genealog.pl <kontakt@genealog.pl>"
LABEL description="Cemetery registry scraper microservice"

# Katalog roboczy
WORKDIR /app

# Zainstaluj zależności systemowe (lxml wymaga libxml2)
RUN apt-get update && apt-get install -y --no-install-recommends \
        libxml2 \
        libxslt1.1 \
    && rm -rf /var/lib/apt/lists/*

# Kopiuj i instaluj zależności Python (osobna warstwa — cache Docker)
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Kopiuj kod aplikacji
COPY . .

# Użytkownik nieroot (bezpieczeństwo)
RUN useradd --no-create-home --shell /bin/false appuser \
    && chown -R appuser:appuser /app
USER appuser

# Port serwisu
EXPOSE 8010

# Health check — Docker sprawdza co 30s
HEALTHCHECK --interval=30s --timeout=10s --start-period=10s --retries=3 \
    CMD python -c "import httpx; r = httpx.get('http://localhost:8010/health', timeout=5); r.raise_for_status()"

# Uruchomienie uvicorn
CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8010", "--workers", "1"]
