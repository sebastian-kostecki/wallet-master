## Widok/Flow: Import transakcji (CSV/XLSX) — 7.5 Import

### 1. Założenia i zakres strony

#### Założenia (MVP)
- Flow z PRD: **Transakcje → Import → wybór konta → upload → auto-commit (bez preview) → wynik → lista transakcji (preset filtra “Zaimportowane przed chwilą”)**.
- Import dotyczy **jednego wybranego konta**. Bank importu wynika z konta (`Account.bank`) i jest tylko wyświetlany (bez osobnego wyboru). **[Assumption]**
- Import jest “zero-config” na froncie: **brak mapowania kolumn w UI**. Backend sam rozpoznaje format i nagłówki pliku dla danego banku. **[Assumption]**
- Po uploadzie import **uruchamia się automatycznie** (auto-commit), bez etapu podglądu wierszy.
- Deduplikacja jest zawsze włączona: duplikaty są **pomijane** (liczone do `rows_skipped_duplicate`).
- Definicja duplikatu: **`account_id` + `raw_description` + `date` + `amount`**. **[Assumption]**
- Typ transakcji jest inferowany ze znaku kwoty: ujemna → wydatek, dodatnia → przychód; kwota trafia do DB z tym znakiem (w tym kwoty w nawiasach traktowane jako ujemne). **[Assumption]**
- CSV: separator wykrywany automatycznie; XLSX: importujemy **pierwszy arkusz**.
- Import jest asynchroniczny (job): po commicie UI pokazuje progres i finalny wynik; realtime (Reverb) + fallback polling. **[Assumption]**
- Import jest niezależny od reszty UI: użytkownik może zamknąć modal i kontynuować pracę na liście transakcji.
- Import zapisuje i utrzymuje nieedytowalne `raw_description` z wyciągu, widoczne w edycji transakcji w sekcji “Dane z wyciągu”. **[Assumption]**
- Pole **„Nadawca/Odbiorca”** jest sugestią generowaną z `raw_description` (Typesense) i uczy się na podstawie edycji użytkownika; użytkownik może je poprawić w edycji transakcji. **[Assumption]**

#### In-scope (MVP)
- Import jako **zawsze modal** z: wyborem konta, uploadem pliku, uruchomieniem importu i ekranem wyniku.
- Obsługa błędów: niepoprawny format pliku, brak nagłówków, walidacja dat/kwot, częściowy import (część wierszy pominięta / błędna).
- Telemetria: `import_started`, `import_completed`, `import_failed`, `import_type_inferred`, `import_rows_skipped_duplicate` (agregat) oraz (Should) typense hit/miss. 

#### Out-of-scope (MVP)
- Preview danych (tabelka z wierszami przed importem).
- Ręczna edycja błędnych wierszy “w imporcie” i ponowne odpalenie tylko części (naprawa w pliku).
- Wsparcie formatów innych niż CSV/XLSX (PDF/MT940/OCR).
- Wybór arkusza w XLSX (zawsze 1. arkusz).
- Historia importów / lista poprzednich importów.

#### Miejsce w nawigacji
- Sekcja: **Transakcje**.
- Wejście: CTA **„Importuj”** na liście transakcji (obok „Dodaj transakcję” / „Transfer”).
- Wyjście po zakończeniu: **automatyczne odświeżenie** listy transakcji + preset filtra-chip **„Zaimportowane przed chwilą”** (po `import_id`) i sortowanie “najnowsze”. Po wyczyszczeniu presetu przywrócić poprzednie sortowanie. **[Assumption]**
- Preset jest widoczny jako chip “Zaimportowane przed chwilą”, ale sam `import_id` nie jest wyświetlany użytkownikowi. **[Assumption]**

---

### 2. Information Architecture

#### Sekcje (priorytetowo)
1) Kontekst (konto + bank) i instrukcja.
2) Upload pliku.
3) Ostrzeżenie (brak cofania + definicja duplikatu).
4) Uruchomienie importu (auto-commit) + stan “w toku”.
5) Wynik importu (liczniki + krótkie wskazówki + przejście do listy z presetem filtra).

#### Nawigacja i główne CTA
- Primary CTA: **„Rozpocznij import”** (aktywne dopiero, gdy konto i plik są poprawne; klik uruchamia auto-commit).
- Secondary: **„Anuluj”** (zamyka modal / wraca na listę).
- Pomocnicze:
  - **„Zmień plik”** (resetuje upload),
  - (Po zakończeniu) **„Zamknij”** / **„Przejdź do transakcji”**.

---

### 3. Wireframe w tekście (desktop + mobile)

#### Forma: modal “wizard” (rekomendacja)
- Uzasadnienie: import jest akcją kontekstową z listy transakcji; modal skraca “time-to-import” i utrzymuje kontekst filtrów.

#### Desktop — modal
- **Modal header**
  - Tytuł: „Import transakcji”
  - Subheader: „Konto: {nazwa} • Bank: {bank}”
  - Link „Wstecz” (jeśli to wielo-krokowy modal) lub ikona X
- **Krok 1: Wybór konta (jeśli wejście bez preselectu)**
  - Select „Konto”
  - Info pod selectem: „Bank importu wynika z konta.”
- **Krok 2: Upload**
  - Dropzone + przycisk „Wybierz plik”
  - Akceptowane: CSV, XLSX; limit rozmiaru egzekwowany na froncie (blokada) + komunikat inline. **[Assumption]**
  - Po wgraniu: chip z nazwą pliku + akcja „Zmień plik”
- **Krok 3: Ostrzeżenie przed importem**
  - InlineNotice:
    - “Importu nie można cofnąć.”
    - “Duplikaty zostaną pominięte (duplikat = surowy opis + data + kwota + konto).”
    - “Po imporcie pokażemy tylko nowo dodane transakcje.”
- **Krok 4: Import w toku**
  - Status: „Import w toku…” + spinner
  - Tekst pomocniczy: „Może to potrwać do kilku minut. Możesz zamknąć to okno — import dokończy się w tle.”
  - (Opcjonalnie) pasek postępu nie jest wymagany; w MVP wystarczy spinner + status.
- **Krok 5: Wynik**
  - 3 metryki/kafle:
    - `rows_imported`
    - `rows_skipped_duplicate`
    - `rows_failed_validation`
  - Podsumowanie zdaniem: „Zaimportowano X, pominięto duplikaty Y, błędne wiersze Z.”
  - Akcje: „Przejdź do transakcji” (primary) + „Zamknij” (secondary)
  - Po kliknięciu “Przejdź do transakcji”: przejście na listę transakcji z presetem filtra “Zaimportowane przed chwilą” (po `import_id`, bez pokazywania `import_id`) + sort “najnowsze”. **[Assumption]**

#### Mobile — modal pełnoekranowy, jednokolumnowy
- Header: tytuł + przycisk „Wstecz” / „Zamknij”.
- Kroki w pionie:
  - Konto (select)
  - Upload
  - Ostrzeżenie
  - CTA na dole (sticky): „Rozpocznij import”
- Na mobile ukryć subheader w jednej linii (np. tylko konto; bank jako mniejszy tekst pod spodem).

Co się zwija/ukrywa na mobile:
- Subheader (kontekst) → skrócony (np. tylko konto w jednej linii).

---

### 4. Komponenty UI (re-używalne)

#### Struktura i layout
- `ModalWizard` / `Dialog` (Headless UI lub Radix Vue): kroki, nagłówek, stopka akcji.
- `PageHeader` (w modalu): tytuł + kontekst (konto/bank).
- `StepIndicator` (opcjonalnie): 1) Konto 2) Plik 3) Ostrzeżenie 4) Import 5) Wynik.

#### Upload i ostrzeżenie
- `FileDropzone`:
  - stany: idle / dragging / file-selected / error
  - walidacja typu i rozmiaru z czytelnym komunikatem
- `SelectField` dla konta:
  - stany: normal / error / disabled (np. konto usunięte)
- `InlineNotice`:
  - info o deduplikacji (“Duplikaty zostaną pominięte”)
  - ostrzeżenie o braku cofania
- `InlineError` + `FormErrorSummary`

#### Progress i wynik
- `LoadingPanel`:
  - spinner + copy “Import w toku…”
  - `aria-busy`
- `ResultMetrics`:
  - 3 kafle/liczniki + podsumowanie zdaniem
- `Toast`/`FlashMessage` (sukces/porażka)

#### Ikony (Lucide) — propozycja
- `Upload`: sekcja upload
- `ShieldAlert` lub `AlertTriangle`: ostrzeżenie
- `Loader2`: import w toku
- `CheckCircle2`: import zakończony
- `AlertTriangle`: błędy / ostrzeżenia
- `Copy` (opcjonalnie): “Skopiuj podsumowanie” (nice-to-have; MVP optional)

---

### 5. Stany i edge-case UX (must-have)

#### Loading
- Podczas commit: “Import w toku…”:
  - zablokować ponowny submit
  - pokazać spinner i status
  - fallback polling, jeśli brak realtime. **[Assumption]**

#### Empty
- Brak kont: komunikat “Dodaj konto, aby wykonać import.” + CTA „Dodaj konto”.
- Konto usunięte (soft-deleted) wybrane przez deep-link: blokada importu + komunikat.

#### Error (czytelne, krótkie)
- **Błędny format pliku / MIME**:
  - “Nieobsługiwany format pliku. Wgraj CSV lub XLSX.”
- **Brak nagłówków**:
  - “Plik musi zawierać nagłówki kolumn (pierwszy wiersz).”
- **XLSX**:
  - Jeśli plik uszkodzony: “Nie udało się odczytać pliku XLSX. Sprawdź format i spróbuj ponownie.”
- **CSV**:
  - Jeśli separator nie wykryty / plik nieczytelny: “Nie udało się odczytać pliku CSV. Sprawdź separator i kodowanie.”
- **403/404**:
  - “Nie masz dostępu do tego importu.” / “Import nie istnieje.” + “Wróć do transakcji”.
- **500/błąd sieci**:
  - “Nie udało się uruchomić importu. Spróbuj ponownie.”

#### Walidacja inline (krytyczne)
- Konto:
  - wymagane
  - jeśli konto usunięte → disable i błąd: “To konto jest usunięte — import jest zablokowany.”
- Plik:
  - wymagany
  - typ: CSV/XLSX
  - rozmiar: blokada na froncie + komunikat inline. **[Assumption]**

#### Edge-case’y z PRD (konkretne zachowania)
- **Różne formaty dat/kwot**:
  - import może skończyć się częściowo; użytkownik widzi `rows_failed_validation`.
  - dodatkowy hint po wyniku, gdy `rows_failed_validation > 0`: “Sprawdź format dat (DD-MM-YYYY lub ISO) i kwot (np. 123,45).”
- **Kwoty w nawiasach**:
  - copy w pomocy (opcjonalnie): “Kwoty w nawiasach (np. (12,34)) są traktowane jako wydatki.” **[Assumption]**
- **Wszystko to duplikaty**:
  - wynik jako sukces, ale z wyraźnym komunikatem: “Nie znaleźliśmy nowych transakcji. Wszystkie wiersze były duplikatami.”
- **Rozpoznanie formatu po banku**:
  - backend rozpoznaje format automatycznie na podstawie banku konta i nagłówków pliku. Jeśli nie da się rozpoznać formatu: import failuje z czytelnym komunikatem. **[Assumption]**
- **Brak Typesense (FR-I5)**:
  - nie komunikować jako błąd; import ma się udać. (Ewentualnie brak “auto-sugestii” jest niewidoczny w UI.)
- **Bank: Gotówka (`Cash`)**:
  - import jest **zablokowany** z czytelnym komunikatem: „Import z pliku nie jest dostępny dla kont gotówkowych.”

---

### 6. Copy (PL) — gotowe teksty

#### Nagłówki i opisy
- Tytuł modalu: „Import transakcji”
- Subheader (kontekst): „Konto: {konto} • Bank: {bank}”
- Opis kroku (upload): „Wgraj plik CSV lub XLSX z nagłówkami kolumn.”
- Opis kroku (ostrzeżenie): „Import uruchomi się automatycznie i nie można go cofnąć.”
- Notka o deduplikacji: „Duplikaty zostaną pominięte.”

#### Etykiety pól
- „Konto”
- „Plik”

#### CTA
- Primary (gotowe do startu): „Rozpocznij import”
- Primary (w toku, disabled): „Importuję…”
- Secondary: „Anuluj”
- Po wyniku:
  - Primary: „Przejdź do transakcji”
  - Secondary: „Zamknij”
- Akcje pomocnicze:
  - „Zmień plik”

#### Walidacje i błędy (krótkie)
- Wymagane pole: „To pole jest wymagane.”
- Plik — format: „Nieobsługiwany format pliku. Wgraj CSV lub XLSX.”
- Plik — nagłówki: „Plik musi zawierać nagłówki kolumn (pierwszy wiersz).”
- Konto usunięte: „To konto jest usunięte — import jest zablokowany.”
- Błąd uruchomienia importu: „Nie udało się uruchomić importu. Spróbuj ponownie.”
- Import nieudany (system): „Import nie powiódł się. Spróbuj ponownie lub zmień plik.”
 - Wszystkie wiersze błędne: „Import nie powiódł się — wszystkie wiersze były błędne.”

#### Wynik (wymagana formuła)
- Podsumowanie: „Zaimportowano {rows_imported}, pominięto duplikaty {rows_skipped_duplicate}, błędne wiersze {rows_failed_validation}.”
- Gdy `rows_imported = 0` i `rows_skipped_duplicate > 0`:
  - „Nie znaleźliśmy nowych transakcji. Wszystkie wiersze były duplikatami.”

---

### 7. A11y checklist

- Fokus:
  - kolejność: tytuł → konto → upload → ostrzeżenie → CTA.
- Formularz:
  - każde pole ma widoczny `label` (nie tylko placeholder).
  - błędy inline: `aria-invalid="true"` + `aria-describedby` do elementu błędu.
- Dialog:
  - poprawne `role="dialog"` + `aria-modal="true"` + `aria-labelledby`.
  - trap focus w modalu, zamknięcie Esc (gdy bezpieczne).
- Upload:
  - dropzone obsługiwalny klawiaturą (Enter/Space otwiera file picker).
  - komunikaty o błędach uploadu dostępne dla screen reader (np. `role="alert"`).
- Import w toku:
  - obszar statusu ma `aria-busy="true"`.
  - przycisk primary w stanie loading ma tekst “Importuję…” (nie tylko spinner).

---

### 8. Sugestia implementacji w stacku (bez pisania kodu)

#### Podział na pliki (Inertia + Vue)
- Modal importu jako komponent na stronie listy transakcji:
  - `resources/js/pages/transactions/Index.vue` (dodaje CTA „Importuj” i otwiera modal) **[Assumption]**
  - `resources/js/components/import/ImportDialog.vue`
  - Pod-komponenty (opcjonalnie):
    - `resources/js/components/import/ImportFileStep.vue`
    - `resources/js/components/import/ImportWarningStep.vue`
    - `resources/js/components/import/ImportProgressStep.vue`
    - `resources/js/components/import/ImportResultStep.vue`
 - Edycja transakcji (modal):
   - `resources/js/pages/transactions/Edit.vue` (sekcja/accordion “Dane z wyciągu” z `raw_description`) **[Assumption]**

#### Kontrakty/propsy z backendu (minimalnie, UI)
- `accounts[]`: `{ id, name, bank, deleted_at? }` (do wyboru konta, wyświetlenia banku i blokady usuniętych).
- Import lifecycle:
  - po upload: `import_id`.
  - po commit: status `queued|processing|committed|failed` + liczniki `rows_*` (przez realtime/polling na `imports.show`). **[Assumption]**

#### Akcje Inertia (przepływ)
- `POST` upload:
  - payload: `account_id`, `file`
  - response: `import_id` (import uruchomiony automatycznie po uploadzie lub natychmiast po akceptacji). **[Assumption]**
- Polling fallback:
  - `GET` `imports.show` co ~2s przez 60s; potem komunikat “Import nadal trwa” + przycisk “Odśwież”. **[Assumption]**
- Po zakończeniu:
  - flash/banner w kontekście listy transakcji: podsumowanie importu (zamykalny).
  - automatyczny refresh listy transakcji po `import_completed` z presetem filtra-chip “Zaimportowane przed chwilą” (po `import_id`) + sort “najnowsze”.
  - po wyczyszczeniu presetu: przywrócić poprzednie sortowanie użytkownika. **[Assumption]**

#### Wskazówki Tailwind (kluczowe decyzje, bez pełnych klas)
- Modal: desktop max-width ~`2xl/3xl`, sekcje w cardach z wyraźnymi nagłówkami.
- Mapowanie: desktop grid 2 kolumny, mobile stack; obowiązkowe pola oznaczone „*”.
- Wynik: 3 metryki jako kafle; wyróżnić “imported” jako pozytywne, “failed” jako ostrzegawcze.

---

### Decyzje (odpowiedzi na pytania otwarte)
1) Import jest **zawsze modalem** z wyborem konta i możliwością wrzucenia pliku z wyciągiem.
2) Po zakończeniu importu lista transakcji **odświeża się automatycznie**; opcjonalnie można zastosować filtr “nowo dodane”.
3) Lista błędów w UI: **nie teraz** (MVP). Szczegóły błędów trafiają do logów; UI pokazuje liczniki `rows_*`.
4) Mapowanie kolumn: **brak w UI**. Backend rozpoznaje format automatycznie.
5) Po imporcie: preset filtra-chip “Zaimportowane przed chwilą” (po `import_id`), bez pokazywania `import_id` na froncie; po wyczyszczeniu presetu wraca poprzednie sortowanie.
6) Import gotówkowy: **nie dopuszczamy**. Dla `Bank::Cash` import jest zablokowany w UI z komunikatem.

