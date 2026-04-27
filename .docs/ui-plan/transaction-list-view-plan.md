## Widok: Lista transakcji (Transakcje → Lista)

### Cel
- Dostarczyć szybki w użyciu widok historii transakcji z filtrami, sortowaniem, paginacją i podsumowaniem wpływów/wydatków dla wybranego zakresu.
- Wspierać metrykę PRD: szybkie ręczne dodanie transakcji oraz płynny przegląd historii.

### Zakres (MVP)
- In-scope: lista transakcji, filtry (konto + zakres dat), sort (data/kwota), paginacja backendowa, podsumowanie (wpływy/wydatki), empty/loading/error, podstawowe akcje przejścia do edycji.
- Out-of-scope: kategorie, wykresy/raporty, akcje masowe, eksport, multiwaluta z przeliczeniami, integracje bankowe.

### Kontrakty danych (z backendu)
- `accounts[]`: `{ id, name }` (do filtra „Konto”)
- `filters`: `{ account_id, from, to, sort, direction }` (stan filtrów z query string)
- `transactions`: paginowane (15/stronę) rekordy transakcji + `links/meta` paginacji
- `summary`: `{ total_income, total_expense }` (suma dla bieżących filtrów)

---

## Lista zadań (do wykonania)

### 1) Struktura strony i nawigacja
- [x] Zdefiniować layout: nagłówek „Transakcje” + sekcja filtrów + podsumowanie + lista/tabela + paginacja.
- [x] Dodać główne CTA: „Dodaj transakcję” (nawiguje do osobnej strony dodawania).
- [x] Dodać akcję wiersza: „Edytuj” (nawiguje do strony edycji).
- [x] Ustalić zachowanie „Wstecz” z create/edit (powrót na listę z zachowanymi filtrami dzięki query string).

### 2) Filtry (konto + zakres dat)
- [x] Filtr „Konto”: select z `accounts[]` + opcja „Wszystkie konta”.
- [x] Filtr „Zakres dat”: pola „Od” i „Do” (format `DD-MM-YYYY`).
- [x] Walidacja inline filtrów:
  - [x] Jeśli `od > do` → komunikat błędu przy polach zakresu.
  - [x] Jeśli format daty błędny → komunikat i brak odświeżenia listy.
- [x] Aktualizacja listy przez Inertia GET (query string) po zmianie filtrów:
  - [x] Przy zmianie filtrów reset paginacji do 1.
  - [x] Zapewnić widoczny stan „ładowanie” podczas przeładowania.
- [ ] Przycisk „Wyczyść filtry” (czyści `account_id`, `from`, `to`, wraca do domyślnego sortu).

### 3) Sortowanie
- [x] Sort po dacie (domyślnie: malejąco) i po kwocie (rosnąco/malejąco).
- [x] Widoczny stan aktywnego sortu (etykieta + ikona kierunku).
- [x] Sortowanie jako query string (`sort`, `direction`) bez gubienia filtrów.

### 4) Lista/tabela transakcji
- [x] Zdecydować o formie:
  - [x] Desktop: tabela z kolumnami (Data, Konto, Opis, Subject, Kwota, Akcje). (Desktop to tabela; układ pól jest lekko inny niż w opisie, ale funkcjonalnie spełnia cel.)
  - [x] Mobile: lista kart/wierszy (Data + Opis, poniżej Konto/Subject, po prawej Kwota).
- [x] Formatowanie danych:
  - [x] Kwota prezentowana w formacie PL (przecinek), z wyróżnieniem przychód/wydatek kolorem i znakiem.
  - [x] Subject opcjonalny: jeśli pusty, nie zajmuje miejsca (lub subtelny placeholder typu „—”).
- [x] Akcje wiersza:
  - [x] „Edytuj” zawsze dostępne, o ile transakcja jest edytowalna.
  - [x] Jeśli transakcja jest powiązana z usuniętym kontem → wiersz oznaczyć jako read-only (copy + badge) i ukryć/wyłączyć akcje edycji/usuwania (w zależności od reguł backendu).

### 5) Paginacja
- [x] Dodać kontrolkę paginacji opartą o paginację backendu (`transactions`).
- [x] Zmiana strony zachowuje aktualne filtry i sort (query string).
- [x] Na mobile: uproszczona paginacja (Poprzednia/Następna + „Strona X z Y”).

### 6) Podsumowanie wpływów i wydatków
- [x] Wyświetlić `summary.total_income` oraz `summary.total_expense` jako dwa „kafle”/metryki.
- [x] Podsumowanie zawsze zgodne z filtrami (konto + zakres dat).
- [x] Stany:
  - [x] Przy ładowaniu filtrów → skeleton dla kafli.
  - [x] Gdy brak wyników → nadal pokazać 0,00 dla obu wartości.

### 7) Stany UX (must-have)
- [x] Loading:
  - [x] Skeleton dla listy/tabeli + podsumowania.
  - [x] Zablokować wielokrotne szybkie przełączenia (np. disable na czas ładowania lub debounce).
- [x] Empty:
  - [x] „Brak transakcji dla wybranych filtrów” + CTA „Dodaj transakcję” i pomocnicze „Wyczyść filtry”.
  - [x] Jeśli w ogóle brak transakcji (pierwsze użycie) → mocniejszy empty state z CTA „Dodaj transakcję” + drugie CTA „Zaimportuj plik” (link do Import).
- [ ] Error:
  - [x] 422 (walidacja filtrów) → inline przy polach.
  - [ ] 403/404 → czytelny komunikat + akcja powrotu do listy bez filtrów.
  - [ ] Błąd sieci/500 → banner/toast + „Spróbuj ponownie”.

### 8) Copy (PL) — gotowe teksty
- [x] Nagłówek: „Transakcje”
- [x] CTA primary: „Dodaj transakcję”
- [ ] Filtry:
  - [x] Placeholder select: „Wszystkie konta”
- [x] Podsumowanie:
  - [x] „Wpływy” / „Wydatki”
- [x] Empty (po filtrach): „Brak transakcji dla wybranych filtrów.”
  - [x] Akcje: „Dodaj transakcję”, „Wyczyść filtry”
- [x] Empty (pierwsze użycie): „Nie masz jeszcze żadnych transakcji.”
  - [x] Akcje: „Dodaj transakcję”, „Zaimportuj plik”
- [x] Błędy filtrów:
  - [x] Zakres dat: „Data „Od” nie może być późniejsza niż „Do”.”
  - [x] Format daty: „Podaj datę w formacie DD-MM-YYYY.”

### 9) A11y checklist
- [x] Focus order: nagłówek → filtry → CTA → podsumowanie → lista → paginacja.
- [x] Wszystkie pola filtrów mają widoczne `label` (nie tylko placeholder).
- [x] Błędy powiązane z polami: `aria-describedby` + `aria-invalid`.
- [x] Sortowanie i paginacja dostępne z klawiatury (Tab/Enter/Space) i mają czytelne nazwy (np. `aria-label="Sortuj po dacie"`).
- [x] Akcje wiersza (Edytuj) dostępne klawiaturą i mają kontekst (np. `aria-label="Edytuj transakcję: {opis}"`).
- [ ] Kontrast WCAG AA dla stanów przychód/wydatek i focus ring.
- [x] Komunikaty ładowania: element z `aria-busy="true"` dla obszaru listy podczas przeładowania.

### 10) Telemetria (zgodnie z PRD)
- [ ] `transactions_filtered` przy zmianie filtrów (z parametrami: account_id, from, to).
- [ ] `transactions_sorted` przy zmianie sortu (sort, direction).
- [ ] `transactions_page_changed` przy zmianie strony (page).
- [ ] (Opcjonalnie, dla PRD „time-to-add”) `transaction_create_opened` po kliknięciu „Dodaj transakcję”.

