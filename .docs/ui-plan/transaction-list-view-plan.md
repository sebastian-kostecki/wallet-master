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
- [ ] Zdefiniować layout: nagłówek „Transakcje” + sekcja filtrów + podsumowanie + lista/tabela + paginacja.
- [ ] Dodać główne CTA: „Dodaj transakcję” (nawiguje do osobnej strony dodawania).
- [ ] Dodać akcję wiersza: „Edytuj” (nawiguje do strony edycji).
- [ ] Ustalić zachowanie „Wstecz” z create/edit (powrót na listę z zachowanymi filtrami dzięki query string).

### 2) Filtry (konto + zakres dat)
- [ ] Filtr „Konto”: select z `accounts[]` + opcja „Wszystkie konta”.
- [ ] Filtr „Zakres dat”: pola „Od” i „Do” (format `DD-MM-YYYY`).
- [ ] Walidacja inline filtrów:
  - [ ] Jeśli `od > do` → komunikat błędu przy polach zakresu.
  - [ ] Jeśli format daty błędny → komunikat i brak odświeżenia listy.
- [ ] Aktualizacja listy przez Inertia GET (query string) po zmianie filtrów:
  - [ ] Przy zmianie filtrów reset paginacji do 1.
  - [ ] Zapewnić widoczny stan „ładowanie” podczas przeładowania.
- [ ] Przycisk „Wyczyść filtry” (czyści `account_id`, `from`, `to`, wraca do domyślnego sortu).

### 3) Sortowanie
- [ ] Sort po dacie (domyślnie: malejąco) i po kwocie (rosnąco/malejąco).
- [ ] Widoczny stan aktywnego sortu (etykieta + ikona kierunku).
- [ ] Sortowanie jako query string (`sort`, `direction`) bez gubienia filtrów.

### 4) Lista/tabela transakcji
- [ ] Zdecydować o formie:
  - [ ] Desktop: tabela z kolumnami (Data, Konto, Opis, Subject, Kwota, Akcje).
  - [ ] Mobile: lista kart/wierszy (Data + Opis, poniżej Konto/Subject, po prawej Kwota).
- [ ] Formatowanie danych:
  - [ ] Data prezentowana jako `DD-MM-YYYY`.
  - [ ] Kwota prezentowana w formacie PL (przecinek), z wyróżnieniem przychód/wydatek kolorem i znakiem.
  - [ ] Subject opcjonalny: jeśli pusty, nie zajmuje miejsca (lub subtelny placeholder typu „—”).
- [ ] Akcje wiersza:
  - [ ] „Edytuj” zawsze dostępne, o ile transakcja jest edytowalna.
  - [ ] Jeśli transakcja jest powiązana z usuniętym kontem → wiersz oznaczyć jako read-only (copy + badge) i ukryć/wyłączyć akcje edycji/usuwania (w zależności od reguł backendu).

### 5) Paginacja
- [ ] Dodać kontrolkę paginacji opartą o paginację backendu (`transactions`).
- [ ] Zmiana strony zachowuje aktualne filtry i sort (query string).
- [ ] Na mobile: uproszczona paginacja (Poprzednia/Następna + „Strona X z Y”).

### 6) Podsumowanie wpływów i wydatków
- [ ] Wyświetlić `summary.total_income` oraz `summary.total_expense` jako dwa „kafle”/metryki.
- [ ] Podsumowanie zawsze zgodne z filtrami (konto + zakres dat).
- [ ] Stany:
  - [ ] Przy ładowaniu filtrów → skeleton dla kafli.
  - [ ] Gdy brak wyników → nadal pokazać 0,00 dla obu wartości.

### 7) Stany UX (must-have)
- [ ] Loading:
  - [ ] Skeleton dla listy/tabeli + podsumowania.
  - [ ] Zablokować wielokrotne szybkie przełączenia (np. disable na czas ładowania lub debounce).
- [ ] Empty:
  - [ ] „Brak transakcji dla wybranych filtrów” + CTA „Dodaj transakcję” i pomocnicze „Wyczyść filtry”.
  - [ ] Jeśli w ogóle brak transakcji (pierwsze użycie) → mocniejszy empty state z CTA „Dodaj transakcję” + drugie CTA „Zaimportuj plik” (link do Import).
- [ ] Error:
  - [ ] 422 (walidacja filtrów) → inline przy polach.
  - [ ] 403/404 → czytelny komunikat + akcja powrotu do listy bez filtrów.
  - [ ] Błąd sieci/500 → banner/toast + „Spróbuj ponownie”.

### 8) Copy (PL) — gotowe teksty
- [ ] Nagłówek: „Transakcje”
- [ ] CTA primary: „Dodaj transakcję”
- [ ] Filtry:
  - [ ] Etykiety: „Konto”, „Od”, „Do”, „Sortuj”, „Kierunek”
  - [ ] Placeholder select: „Wszystkie konta”
  - [ ] Przycisk: „Wyczyść filtry”
- [ ] Podsumowanie:
  - [ ] „Wpływy” / „Wydatki”
- [ ] Empty (po filtrach): „Brak transakcji dla wybranych filtrów.”
  - [ ] Akcje: „Dodaj transakcję”, „Wyczyść filtry”
- [ ] Empty (pierwsze użycie): „Nie masz jeszcze żadnych transakcji.”
  - [ ] Akcje: „Dodaj transakcję”, „Zaimportuj plik”
- [ ] Błędy filtrów:
  - [ ] Zakres dat: „Data „Od” nie może być późniejsza niż „Do”.”
  - [ ] Format daty: „Podaj datę w formacie DD-MM-YYYY.”

### 9) A11y checklist
- [ ] Focus order: nagłówek → filtry → CTA → podsumowanie → lista → paginacja.
- [ ] Wszystkie pola filtrów mają widoczne `label` (nie tylko placeholder).
- [ ] Błędy powiązane z polami: `aria-describedby` + `aria-invalid`.
- [ ] Sortowanie i paginacja dostępne z klawiatury (Tab/Enter/Space) i mają czytelne nazwy (np. `aria-label="Sortuj po dacie"`).
- [ ] Akcje wiersza (Edytuj) dostępne klawiaturą i mają kontekst (np. `aria-label="Edytuj transakcję: {opis}"`).
- [ ] Kontrast WCAG AA dla stanów przychód/wydatek i focus ring.
- [ ] Komunikaty ładowania: element z `aria-busy="true"` dla obszaru listy podczas przeładowania.

### 10) Telemetria (zgodnie z PRD)
- [ ] `transactions_filtered` przy zmianie filtrów (z parametrami: account_id, from, to).
- [ ] `transactions_sorted` przy zmianie sortu (sort, direction).
- [ ] `transactions_page_changed` przy zmianie strony (page).
- [ ] (Opcjonalnie, dla PRD „time-to-add”) `transaction_create_opened` po kliknięciu „Dodaj transakcję”.

