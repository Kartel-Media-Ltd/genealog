# Plan: Feature Druk/PDF — Genealog

## Cel

Umożliwienie użytkownikom drukowania drzewa genealogicznego (SVG) i listy osób, oraz eksportu drzewa jako plik PNG — bez dodatkowych zależności serwerowych (zero Puppeteer na MVP).

---

## Nowe trasy (Router)

| Metoda | Ścieżka | Kontroler::metoda | Opis |
|--------|---------|-------------------|------|
| GET | `/trees/{id}/print` | `TreeController::printView` | Strona druku SVG drzewa |
| GET | `/trees/{id}/persons/print` | `PersonController::printList` | Lista osób do druku |

Obie trasy przechodzą przez `TreeAccessMiddleware` — weryfikacja `tree_members` lub `owner_id` przed renderingiem.

---

## Nowe pliki

### `src/Views/pages/trees/print.php`

Widok druku drzewa SVG. Używa `PrintLayout.php` zamiast `AppLayout.php`.

Odpowiedzialności:
- Ładuje `public/js/d3.min.js` + `public/js/tree-visualizer.js`
- Inicjalizuje D3 tak samo jak `show.php` (ten sam `GET /api/trees/{id}/persons`)
- Ukrywa wszystkie elementy UI (nav, sidebar, toolbary) — zostaje tylko SVG
- Renderuje trzy przyciski w pasku narzędziowym nad SVG (tylko na ekranie, ukryte przy druku):
  - **Drukuj** — `window.print()`
  - **Pobierz PNG** — wywołuje `exportSvgAsPng()` z `print-helper.js`
  - **Zamknij** — `window.close()` lub powrót do `/trees/{id}`
- CSS `@page { size: A3 landscape; margin: 10mm; }` — optymalne dla rozbudowanych drzew
- SVG rozciągnięty do pełnej szerokości strony (`width: 100%; height: calc(100vh - 48px)`)

```php
<?php
// Kontroler przekazuje: $tree (obiekt Tree), $treeId (int)
// PrintLayout nie zawiera nav/header — tylko <html><head>...<body>$content</body>
$pageTitle = 'Druk drzewa — ' . htmlspecialchars($tree->name);
ob_start();
?>
<div class="print-toolbar no-print flex gap-2 p-2 bg-gray-100 border-b">
    <button onclick="window.print()" class="btn-print">Drukuj</button>
    <button onclick="exportSvgAsPng(document.querySelector('svg'), 'drzewo-<?= $treeId ?>.png')"
            class="btn-png">Pobierz PNG</button>
    <a href="/trees/<?= $treeId ?>" class="btn-close">Zamknij</a>
</div>
<div id="tree-container" class="print-full"></div>
<script>
  const TREE_ID = <?= $treeId ?>;
</script>
<script src="/js/d3.min.js"></script>
<script src="/js/tree-visualizer.js"></script>
<script src="/js/print-helper.js"></script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/PrintLayout.php';
```

---

### `src/Views/pages/trees/persons-print.php`

Widok druku listy osób. Używa `PrintLayout.php`.

Odpowiedzialności:
- Prosta tabela HTML z danymi osób (bez D3, bez JS poza Alpine CDN do ewentualnych dropdownów)
- Kolumny: Lp. | Imię | Nazwisko | Nazwisko panieńskie | Data ur. | Miejsce ur. | Data śm. | Miejsce śm.
- Sortowanie: `ORDER BY last_name, first_name` (po stronie serwera w `PersonRepository`)
- Stopka strony: nazwa drzewa + data wydruku + liczba osób
- Przycisk "Drukuj" i "Zamknij" nad tabelą (`.no-print`)
- Brak paginacji — pełna lista (CSS `page-break-inside: avoid` dla wierszy tabeli)

```php
<div class="no-print flex gap-2 p-2">
    <button onclick="window.print()">Drukuj listę</button>
    <a href="/trees/<?= $treeId ?>/persons">Zamknij</a>
</div>
<table class="w-full text-sm border-collapse">
  <thead>
    <tr>
      <th>Lp.</th><th>Imię</th><th>Nazwisko</th>
      <th>Nazwisko panieńskie</th>
      <th>Data ur.</th><th>Miejsce ur.</th>
      <th>Data śm.</th><th>Miejsce śm.</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($persons as $i => $p): ?>
    <tr>
      <td><?= $i + 1 ?></td>
      <td><?= htmlspecialchars($p->first_name) ?></td>
      <td><?= htmlspecialchars($p->last_name) ?></td>
      <td><?= htmlspecialchars($p->maiden_name ?? '—') ?></td>
      <td><?= $p->birth_date ? htmlspecialchars($p->birth_date) : '—' ?></td>
      <td><?= htmlspecialchars($p->birth_place ?? '—') ?></td>
      <td><?= $p->death_date ? htmlspecialchars($p->death_date) : '—' ?></td>
      <td><?= htmlspecialchars($p->death_place ?? '—') ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<footer class="mt-8 text-xs text-gray-500 no-screen">
  Drzewo: <?= htmlspecialchars($tree->name) ?> &bull;
  Liczba osób: <?= count($persons) ?> &bull;
  Wydrukowano: <?= date('d.m.Y H:i') ?>
</footer>
```

---

### `public/js/print-helper.js`

Client-side eksport SVG → Canvas → PNG.

```js
/**
 * Eksportuje element SVG jako plik PNG.
 * @param {SVGElement} svgElement - element SVG do eksportu
 * @param {string} filename - nazwa pliku (np. 'drzewo-5.png')
 */
function exportSvgAsPng(svgElement, filename) {
  if (!svgElement) {
    alert('Brak drzewa do eksportu. Odczekaj chwilę na załadowanie.');
    return;
  }

  const svgData = new XMLSerializer().serializeToString(svgElement);
  const svgBlob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
  const url = URL.createObjectURL(svgBlob);

  const img = new Image();
  img.onload = function () {
    const canvas = document.createElement('canvas');
    // Skalowanie 2x dla lepszej jakości (Retina/druk)
    const scale = 2;
    canvas.width  = img.naturalWidth  || svgElement.viewBox.baseVal.width  || 2480;
    canvas.height = img.naturalHeight || svgElement.viewBox.baseVal.height || 1754;
    canvas.width  *= scale;
    canvas.height *= scale;

    const ctx = canvas.getContext('2d');
    ctx.scale(scale, scale);
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(img, 0, 0);

    URL.revokeObjectURL(url);

    const link = document.createElement('a');
    link.download = filename || 'drzewo.png';
    link.href = canvas.toDataURL('image/png');
    link.click();
  };
  img.onerror = function () {
    URL.revokeObjectURL(url);
    alert('Błąd eksportu PNG. Spróbuj użyć opcji "Drukuj" i wybierz "Zapisz jako PDF".');
  };
  img.src = url;
}
```

---

### `src/Views/templates/PrintLayout.php`

Minimalny layout bez nav/header/footer — tylko niezbędne style i treść.

```php
<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="pl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'Druk — Genealog') ?></title>
  <!-- Tailwind CDN (print styles wymagają klas utility) -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="/css/globals.css">
  <style>
    /* Domyślna strona A3 landscape dla drzew */
    @page {
      size: A3 landscape;
      margin: 10mm;
    }
    /* Przełącznik dla listy osób: A4 portrait */
    @page :named-portrait {
      size: A4 portrait;
      margin: 15mm;
    }
    @media print {
      .no-print { display: none !important; }
      body { background: white !important; margin: 0; }
      .print-full { width: 100% !important; }
      table { border-collapse: collapse; }
      td, th { border: 1px solid #ddd; padding: 4px 8px; }
      tr { page-break-inside: avoid; }
      footer.no-screen { display: block !important; }
    }
    footer.no-screen { display: none; } /* ukryta na ekranie, widoczna przy druku */
  </style>
</head>
<body class="bg-white text-gray-900">
  <?= $content ?>
</body>
</html>
```

---

## Modyfikacje istniejących plików

### `src/Views/pages/trees/show.php`

Dodać przycisk "Drukuj drzewo" otwierający nową kartę. Umieścić w toolbarze/nagłówku widoku:

```php
<a href="/trees/<?= $treeId ?>/print"
   target="_blank"
   class="inline-flex items-center gap-1 px-3 py-1.5 text-sm border rounded hover:bg-gray-50">
  <svg ...><!-- ikona lucide: printer --></svg>
  Drukuj drzewo
</a>
```

### `src/Views/pages/trees/persons/index.php`

Dodać link "Lista do druku" obok paginacji lub w nagłówku sekcji:

```php
<a href="/trees/<?= $treeId ?>/persons/print"
   target="_blank"
   class="text-sm text-muted-foreground hover:underline flex items-center gap-1">
  <svg ...><!-- ikona lucide: printer --></svg>
  Lista do druku
</a>
```

---

## Architektura kontrolerów

### `TreeController::printView(int $id): void`

```php
public function printView(int $id): void
{
    // 1. Weryfikacja dostępu (middleware już sprawdził, ale można powtórzyć)
    $tree = $this->treeRepository->findByIdForUser($id, $this->session->getUserId());
    if (!$tree) {
        $this->response->redirect('/trees');
        return;
    }
    // 2. Renderuj — D3 pobierze dane przez /api/trees/{id}/persons (AJAX)
    $treeId   = $id;
    $pageTitle = 'Druk drzewa — ' . $tree->name;
    ob_start();
    include __DIR__ . '/../Views/pages/trees/print.php';
    $content = ob_get_clean();
    include __DIR__ . '/../Views/templates/PrintLayout.php';
}
```

### `PersonController::printList(int $id): void`

```php
public function printList(int $id): void
{
    $tree = $this->treeRepository->findByIdForUser($id, $this->session->getUserId());
    if (!$tree) {
        $this->response->redirect('/trees');
        return;
    }
    $persons  = $this->personRepository->findByTreeSortedByName($id);
    $treeId   = $id;
    $pageTitle = 'Lista osób — ' . $tree->name;
    ob_start();
    include __DIR__ . '/../Views/pages/trees/persons-print.php';
    $content = ob_get_clean();
    include __DIR__ . '/../Views/templates/PrintLayout.php';
}
```

---

## Repozytorium — nowa metoda

### `PersonRepository::findByTreeSortedByName(int $treeId): array`

```php
public function findByTreeSortedByName(int $treeId): array
{
    $stmt = $this->db->prepare(
        'SELECT id, first_name, last_name, maiden_name,
                birth_date, birth_place, death_date, death_place
         FROM persons
         WHERE tree_id = :tree_id
         ORDER BY last_name, first_name'
    );
    $stmt->execute(['tree_id' => $treeId]);
    return $stmt->fetchAll(\PDO::FETCH_OBJ);
}
```

---

## CSS print — globals.css (dopisek na końcu)

```css
/* ================================================
   Print styles — Genealog
   ================================================ */
@media print {
  .no-print { display: none !important; }

  body {
    background: white !important;
    color: black !important;
    margin: 0;
    padding: 0;
  }

  /* Pełna szerokość dla kontenera drzewa SVG */
  #tree-container,
  #tree-container svg {
    width: 100% !important;
    height: auto !important;
  }

  /* Tabela listy osób */
  .print-table { width: 100%; border-collapse: collapse; }
  .print-table th,
  .print-table td { border: 1px solid #ccc; padding: 3px 6px; font-size: 10pt; }
  .print-table thead { background: #f0f0f0; }

  /* Unikaj łamania wiersza tabeli */
  .print-table tr { page-break-inside: avoid; }

  /* Stopka widoczna przy druku */
  footer.no-screen { display: block !important; }
}
```

---

## Podsumowanie zależności

| Komponent | Zależność zewnętrzna | Uwaga |
|-----------|---------------------|-------|
| Druk SVG | brak (CSS `@media print`) | Działa w każdej nowoczesnej przeglądarce |
| Eksport PNG | brak (Canvas API + Blob) | Vanilla JS, zero npm |
| Lista do druku | brak | Pure HTML table |
| PrintLayout | Tailwind CDN | Już używany w projekcie |

**Brak nowych zależności Composer ani npm.** Cały feature działa client-side lub przez istniejące PHP/PDO.
