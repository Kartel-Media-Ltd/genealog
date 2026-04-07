# GEDCOM Import/Eksport — Audyt bezpieczeństwa

## Klasyfikacja zagrożeń (STRIDE)

| Zagrożenie | Wektor | Mitygacja |
|-----------|--------|-----------|
| **Spoofing** | Fałszywa tożsamość właściciela drzewa | TreeAccessMiddleware + weryfikacja owner_id |
| **Tampering** | Manipulacja danych GEDCOM w trakcie importu | Transakcja DB, prepared statements |
| **Repudiation** | Zaprzeczenie kto wykonał import | Log importu (user_id, tree_id, timestamp) |
| **Information Disclosure** | Odczyt danych innego drzewa przez eksport | IDOR check przed eksportem |
| **DoS** | Upload ogromnych plików spowalniający serwer | Limit 50 MB + rate limiting + timeout |
| **Elevation of Privilege** | Import pliku .php zamiast .ged | Walidacja rozszerzenia + MIME + brak exec |

---

## 1. Walidacja uploadu pliku

### Rozszerzenie pliku

```php
$originalName = $_FILES['gedcom_file']['name'];
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if ($extension !== 'ged') {
    throw new ValidationException('Dozwolone są tylko pliki .ged');
}
```

Nigdy nie ufaj rozszerzeniu jako jedynemu zabezpieczeniu — to punkt wejściowy, nie gwarantuje treści.

### Walidacja MIME przez finfo

```php
$finfo = new \finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($_FILES['gedcom_file']['tmp_name']);
$allowed = ['text/plain', 'text/x-gedcom', 'application/octet-stream'];
if (!in_array($mime, $allowed, true)) {
    throw new ValidationException('Nieprawidłowy typ pliku.');
}
```

Uwaga: pliki GEDCOM są tekstem — wiele systemów raportuje `application/octet-stream`. Akceptuj oba.

### Limit rozmiaru pliku

```php
$maxSize = 50 * 1024 * 1024; // 50 MB
if ($_FILES['gedcom_file']['size'] > $maxSize) {
    throw new ValidationException('Plik przekracza dozwolony rozmiar 50 MB.');
}
```

Upewnij się, że `upload_max_filesize` i `post_max_size` w `php.ini` >= 50M.

### Kod błędu uploadu PHP

```php
if ($_FILES['gedcom_file']['error'] !== UPLOAD_ERR_OK) {
    throw new ValidationException('Błąd podczas przesyłania pliku (kod: ' . $_FILES['gedcom_file']['error'] . ')');
}
```

### Weryfikacja przez `is_uploaded_file()`

```php
if (!is_uploaded_file($_FILES['gedcom_file']['tmp_name'])) {
    throw new SecurityException('Potencjalny atak path injection na upload.');
}
```

---

## 2. Przechowywanie tymczasowe — poza public/

```php
// DOBRZE: sys_get_temp_dir() jest poza document root
$tmpPath = sys_get_temp_dir() . '/' . bin2hex(random_bytes(16)) . '.ged';
move_uploaded_file($_FILES['gedcom_file']['tmp_name'], $tmpPath);

// ŹLE: nigdy nie zapisuj do public/
// $tmpPath = __DIR__ . '/../../public/uploads/' . $filename; // NIEBEZPIECZNE
```

Plik tymczasowy musi być usunięty po przetworzeniu — nawet przy wyjątku:

```php
try {
    $result = $this->gedcomService->import($treeId, $tmpPath, $userId);
} finally {
    if (file_exists($tmpPath)) {
        unlink($tmpPath);
    }
}
```

---

## 3. Path Traversal

Nigdy nie używaj oryginalnej nazwy pliku jako ścieżki:

```php
// ŹLE:
$path = '/tmp/' . $_FILES['gedcom_file']['name']; // PODATNOŚĆ: ../../etc/passwd

// DOBRZE:
$path = sys_get_temp_dir() . '/' . bin2hex(random_bytes(16)) . '.ged';
```

Jeśli nazwa pliku jest gdziekolwiek wyświetlana w UI — escapuj przez `htmlspecialchars()`:

```php
$displayName = htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8');
```

---

## 4. IDOR — weryfikacja dostępu do drzewa

Każda operacja na drzewie musi weryfikować, że zalogowany użytkownik ma do niego dostęp.

```php
// W GedcomController — przed każdą operacją:
$tree = $this->treeRepo->findById($treeId);
if (!$tree || ($tree->owner_id !== $currentUserId && !$this->treeRepo->isMember($treeId, $currentUserId))) {
    http_response_code(403);
    $this->flash->error('Brak dostępu do tego drzewa.');
    $this->redirect('/dashboard');
    return;
}
```

Import powinien wymagać roli `owner` lub `editor` — `viewer` nie może modyfikować drzewa.

```php
$role = $this->treeRepo->getMemberRole($treeId, $currentUserId);
if (!in_array($role, ['owner', 'editor'], true)) {
    http_response_code(403);
    // ...
}
```

---

## 5. Transakcja bazy danych

Cały import musi być w jednej transakcji — częściowy import jest gorszy niż żaden:

```php
$this->pdo->beginTransaction();
try {
    // import wszystkich INDI
    // import wszystkich FAM
    $this->pdo->commit();
} catch (\Throwable $e) {
    $this->pdo->rollBack();
    throw new GedcomImportException('Import nie powiódł się: ' . $e->getMessage(), 0, $e);
}
```

Nie rób commit po każdej osobie — to powoduje niespójne dane przy błędzie w połowie pliku.

---

## 6. XSS — dane z pliku GEDCOM

Dane z pliku GEDCOM mogą zawierać złośliwe HTML/JS. Każdy wyciągnięty string musi być escapowany przed wstawieniem do HTML:

```php
// DOBRZE — w widoku PHP:
<?= htmlspecialchars($person->first_name, ENT_QUOTES, 'UTF-8') ?>

// DOBRZE — w GedcomService::buildINDI() — do DB przez prepared statements:
$stmt->bindValue(':first_name', $firstName, \PDO::PARAM_STR);
```

Do bazy danych dane wchodzą przez prepared statements — bez ryzyka SQL injection. XSS ryzyko pojawia się przy wyświetlaniu ich w widoku.

Sprawdź zwłaszcza pole `notes` — może zawierać dowolny tekst z oryginalnego pliku.

---

## 7. CSRF — ochrona formularza importu

Import to POST z plikiem — musi mieć CSRF token:

```html
<!-- W gedcom.php widoku -->
<form method="POST" action="/trees/<?= $treeId ?>/gedcom/import" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <input type="file" name="gedcom_file" accept=".ged">
    <button type="submit">Importuj</button>
</form>
```

W kontrolerze:

```php
$this->request->verifyCsrf(); // rzuca wyjątek jeśli token niepoprawny
```

Eksport (GET) nie wymaga CSRF — jest read-only. Upewnij się jednak, że endpoint eksportu wymaga uwierzytelnienia.

---

## 8. Rate limiting na endpoint importu

Import to operacja ciężka obliczeniowo (parsowanie, wiele INSERTów). Ogranicz częstotliwość:

```php
// Przykład logiki w kontrolerze lub middleware:
$key = 'gedcom_import_' . $currentUserId;
$attempts = $this->rateLimiter->getAttempts($key, windowSeconds: 3600);
if ($attempts >= 5) {
    $this->flash->error('Przekroczono limit importów. Spróbuj za godzinę.');
    $this->redirect('/trees/' . $treeId . '/gedcom');
    return;
}
$this->rateLimiter->increment($key, windowSeconds: 3600);
```

Limit sugestowany: **5 importów / godzinę / użytkownik**.

Tabela `rate_limits` (już opisana w CLAUDE.md): `(ip, endpoint, attempts, window_start)`.
Dla importu korzystaj z `user_id` zamiast `ip` — bardziej precyzyjne.

---

## 9. Bezpieczeństwo eksportu

- Eksport ujawnia **wszystkie dane osób w drzewie** — sprawdź dokładnie uprawnienia
- Plik `.ged` może zawierać dane wrażliwe (daty urodzenia żyjących osób) — eksportuj tylko z prawem `owner` lub `editor`
- Nagłówek `Content-Disposition: attachment` musi być ustawiony — zapobiega renderowaniu w przeglądarce
- Nie cache'uj eksportu (dane mogą się zmieniać): `Cache-Control: no-store, no-cache`
- Nie ujawniaj wewnętrznych ID bazy — używaj `gedcom_xref` jako identyfikatora w pliku .ged

---

## 10. Checklist weryfikacyjna przed wdrożeniem

- [ ] Plik > 50MB → błąd (nie wyczerpuje RAM serwera)
- [ ] Plik .php z rozszerzeniem .ged → odrzucony przez MIME check
- [ ] Próba importu do cudzego drzewa → 403
- [ ] Brak CSRF tokena → odrzucony
- [ ] Pole NOTE z `<script>alert(1)</script>` → wyświetlone bezpiecznie po htmlspecialchars
- [ ] SQL injection w polu first_name → zablokowany przez prepared statements
- [ ] Plik z `../../../etc/passwd` jako nazwą → bezpieczny (UUID jako nazwa tymczasowa)
- [ ] Import przerwany w połowie → rollback, baza bez częściowych danych
- [ ] Plik tymczasowy usunięty po błędzie (try/finally)
- [ ] 6 importów w 1h → rate limit error przy 6.
- [ ] Eksport przez viewer (nie editor) → 403
