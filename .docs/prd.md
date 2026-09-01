# Wallet Master — wymagania produktowe (PRD)

> Jedyny kanoniczny dokument wymagań produktowych.
> Stack technologiczny i komendy deweloperskie: `.docs/tech-stack.md`

## 0. Dla implementujących (AI i zespół)

- **Źródło prawdy:** ten plik = *co* budujemy; `.docs/tech-stack.md` = *jak* uruchamiamy projekt i jaki stack.
- **MVP:** implementuj wymagania z **Priorytet: Must**, chyba że zadanie wyraźnie obejmuje **Should**.
- **Izolacja danych:** konta, transakcje, importy, kategorie, cele, szacunki — wyłącznie w scope `user_id` zalogowanego użytkownika.
- **Konto usunięte (soft delete):** transakcje pozostają w historii, **read-only**; import i transfer na takie konto — zablokowane.
- **Import:** konto → upload CSV/XLSX → auto-mapowanie adaptera banku → auto-commit **bez podglądu**; `draft` to stan techniczny między uploadem a kolejką.
- **Dedupe (import):** klucz `date + amount + normalized_description` na koncie; pominięcie duplikatu przy imporcie; ręczne dodanie identycznej transakcji — dozwolone (`dedupe_hash` z sufiksem UUID).
- **Saldo:** `current_balance` aktualizowane przy zmianach transakcji; komenda `accounts:recalculate-balance` jako safety net.
- **Lista transakcji:** filtry, sort i podsumowanie po dacie okresu (`booked_at`; UI kolumny: `COALESCE(booked_at, date)`); sumy wpływów/wydatków **bez** wewnętrznych transferów (`transfer_id` pusty) **i bez kapitału lokat** (`deposit_movements.kind` ∈ `open`, `close_principal`); odsetki lokaty (`kind=interest`) wliczane jak zwykły przychód.
- **Kategorie i szacunki (wave 2):** każda transakcja P&L (`income` / `expense` / `adjustment`) ma `category_id`; transfer wewnętrzny ma `category_id = null`; szacunki roczne (kanoniczne) + opcjonalne nadpisania miesięczne; widok miesięczny (plan vs fakty P&L + sekcja celów) i roczny (plan vs fakty bez transferów) — §3.1a, FR-C1–C7, FR-P*, FR-UX1.
- **Kieszenie oszczędnościowe (wave 2 UX):** koperta oszczędnościowa z **walutą** (`currency_id`, MVP: tylko PLN w UI); opcjonalna kwota docelowa, tryb planowania; śledzenie przez opcjonalną kieszeń i ruch (`pocket_movement`: Odłożenie/Wypłata) na transferze wewnętrznym — §3.1a, FR-P1–P3, FR-P5.
- **Poza zakresem:** §3.2 — nie implementuj bez rozszerzenia tego dokumentu.

---

## 1. Słownik pojęć

- **Konto:** konto bankowe użytkownika w aplikacji (nazwa, waluta, saldo).
- **Typ konta:** klasyfikacja konta (enum), np. ROR, oszczędnościowe.
- **Bank:** instytucja/źródło wyciągu przypisane do konta. W MVP: **BNP Paribas**, **mBank**, **Gotówka** (`Cash`).
- **Transakcja:** pojedynczy zapis finansowy na koncie (przychód / wydatek / transfer / korekta).
- **`date`:** data operacji w banku.
- **`booked_at`:** data przypisania do okresu rozliczeniowego użytkownika; domyślnie = `date`. Filtry list, podsumowania i raporty operują po `booked_at` (kolumna UI: `COALESCE(booked_at, date)`).
- **Transfer:** jedna akcja tworząca **2 transakcje** (wydatek na koncie źródłowym, przychód na docelowym) z wspólnym `transfer_id`; manualnie lub przez matcher importu (FR-I6).
- **Korekta salda (`adjustment`):** transakcja przy ręcznym ustawieniu salda; kwota = `new_balance − current_balance`.
- **Import:** wczytanie CSV/XLSX z auto-mapowaniem adaptera banku, auto-commit bez preview; duplikaty pomijane; wynik z licznikami.
- **Mapowanie kolumn:** automatyczne przez adapter banku z nagłówków wyciągu — bez ręcznego mapowania w UI.
- **Subject:** nadawca/odbiorca, osobno od opisu.
- **Duplikat (importu):** ten sam `date + amount + normalized_description` na koncie — pomijany przy imporcie; ręczny duplikat dozwolony. Banki MVP nie eksportują unikalnych ID transakcji.
- **Aktywny użytkownik (retention):** ≥1 akcja produktowa (import lub transakcja) w ostatnich 7 dniach — metryka analityczna poza MVP.
- **Nowy użytkownik (aktywacja importu):** do 7 dni od `user_registered`; metryka „Activation import” (§3.3).
- **Kategoria:** wpis w katalogu użytkownika (`income` \| `expense`) z kolejnością wyświetlania, ikoną (Lucide) i kolorem (paleta hex); etykieta przepływów **zewnętrznych** P&L — wymagana na `income`, `expense`, `adjustment`; **brak** na transferze wewnętrznym (`category_id = null`).
- **Szacunek roczny:** planowana kwota na rok kalendarzowy per kategoria (przychód lub wydatek); można przekroczyć — to nie jest twardy limit.
- **Szacunek miesięczny:** opcjonalne, jawne nadpisanie planu na dany miesiąc; niezależne od rocznego (brak nadpisania = plan `0` w widoku miesięcznym). Prognoza roczna nadal może używać `szacunek_roczny ÷ 12` dla zamkniętych miesięcy bez nadpisania.
- **Kieszeń (pocket, EN: envelope):** koperta oszczędnościowa użytkownika (np. „Wakacje”), **osobna od kategorii P&L** i **bez roku kalendarzowego** (jak katalog kategorii, nie jak wiersz budżetu P&L). Każda kieszeń ma **walutę** (`currency_id`) — kwoty docelowe, saldo i planowanie są w tej walucie (MVP: tylko PLN w UI; pole w DB jak przy koncie). Opcjonalna **kwota docelowa** (`target_amount`); przy kwocie — wzajemnie wykluczające się planowanie: **składka miesięczna** (deklaracja użytkownika) **lub** **data docelowa** (termin; system liczy rekomendowaną składkę). Wykonanie: skumulowane saldo z transferów wewnętrznych z przypisaną kieszenią i ruchem (`pocket_movement`: **Odłożenie** / **Wypłata**) w walucie kieszeni — każda para transferu liczona raz per `transfer_id`. Kategoria P&L i kieszeń są **wzajemnie wykluczające** (XOR): P&L ma `category_id`, transfer ma opcjonalnie `pocket_id` + `pocket_movement`.
- **Widok budżetu miesięczny:** plan vs wykonanie per kategoria P&L w miesiącu + sekcja **Kieszenie** (plan / odłożono / wypłacono per kieszeń; bez kolumny salda miesięcznego).
- **Widok budżetu roczny:** szacunek roczny vs wykonanie per kategoria P&L w roku; bez agregacji transferów wewnętrznych; edycja planów P&L na tym ekranie (FR-UX1).
- **Lokata (deposit):** jednorazowa lokata terminowa (nie konto). Kapitał schodzi z rachunku bankowego jedną transakcją (`deposit_movements.kind=open`), wraca jako `close_principal`; odsetki to osobny przychód (`kind=interest`) z kategorią.

---

## 2. Streszczenie produktu

Webowa aplikacja do budżetu domowego: konta, transakcje, import wyciągów CSV/XLSX, kategorie operacji P&L, cele oszczędnościowe oraz szacunki plan vs wykonanie (miesięcznie i rocznie). MVP optymalizuje **time-to-value** — szybkie ręczne dodawanie transakcji i wysoki sukces importu przy minimalnej korekcie. Wave 2 (pre-release) dodaje kategoryzację i widoki budżetu ze **szacunkami** (nie limitami); rozszerzenie UX dodaje **cele** i przenosi edycję planów P&L na ekrany budżetu.

---

## 3. Zakres i metryki sukcesu

### 3.1 MVP — Must (zakres funkcjonalny)

1. Rejestracja i logowanie (FR-A1)
2. Zarządzanie kontami (FR-K1, FR-K2)
3. CRUD transakcji (FR-T1)
4. Lista, filtry, sort, paginacja, podsumowanie (FR-T2)
5. Import CSV/XLSX — auto-mapowanie, auto-commit, dedupe (FR-I1–I4)
6. Transfer między kontami (FR-T3)
7. Saldo i korekta (FR-S1)

**Should w MVP (wdrażaj gdy w scope zadania):** reset hasła (FR-A2), pamięć opisów (FR-I5), matcher transferów (FR-I6).

### 3.1a Wave 2 (pre-release) — kategorie i szacunki — Must

1. CRUD kategorii + zestaw startowy (FR-C1)
2. Kategoria wymagana na transakcji, transferze i imporcie (FR-C2, FR-C7)
3. Szacunki roczne i miesięczne per kategoria (FR-C3, FR-C4)
4. Widok budżetu miesięczny i roczny (FR-C5, FR-C6)

**Should w wave 2:** filtr/kolumna kategorii na liście transakcji (FR-C8).

**Rozszerzenie wave 2 UX (cele + IA budżetu) — Must:**

1. CRUD kieszeni (FR-P1)
2. Planowanie kieszeni: kwota docelowa + tryb planowania (FR-P2)
3. Opcjonalna kieszeń i ruch na transferze wewnętrznym (FR-P3)
4. Widok kieszeni w budżecie miesięcznym zamiast agregatu transferów (FR-P5)
5. Plany P&L tylko na ekranach budżetu — kategorie bez pól szacunków (FR-UX1)
6. Kategoria P&L tylko na przepływach zewnętrznych — transfer bez `category_id`, bez kategorii „Oszczędności” (spec: `.docs/superpowers/specs/2026-06-04-transfer-category-decoupling-design.md`)

**Should w rozszerzeniu wave 2 UX:** (brak — FR-P4 usunięte; kieszeń wyłącznie na transferze, XOR z kategorią P&L).

### 3.1b Lokaty (Should / plan)

1. Lokaty terminowe — trzecia zakładka Konta, kapitał poza P&L, odsetki w P&L, ręczne otwarcie/zamknięcie i przypisanie transakcji (FR-D1)
2. Import lokat BNP/mBank — auto-detekcja kapitału przy imporcie CSV, kandydaci przy niejednoznaczności (FR-D2)

### 3.2 Poza zakresem (Out of scope)

- AI do sugerowania kategorii (automatyczna klasyfikacja ML)
- Mapowanie kategorii z kolumny wyciągu bankowego (np. mBank `Kategoria`) — kategoria z pamięci lub fallback (FR-C7)
- Wielowalutowość w UI i przeliczenia walut (MVP: tylko PLN w selektorach; `currency_id` na koncie, transakcji i **celu** — bez konwersji i bez wielu walut w UI)
- Współdzielenie danych między użytkownikami
- Import inny niż CSV/XLSX (PDF, MT940, OCR)
- Załączniki, eksport danych
- Wykresy i zaawansowane raporty (poza tabelarycznymi widokami budżetu FR-C5/C6)
- Twarde limity budżetu z blokadą transakcji / alertami push
- Szacunki oszczędności per konto (zamiast celów + transferów z `pocket_id`)
- Wiele celów na jednym transferze; automatyczne dopasowanie wypłaty z oszczędności do kolejnego wydatku
- Powiązanie celu z konkretnym kontem oszczędnościowym (dowolne konto `Savings` użytkownika)
- Rok fiskalny niestandardowy (poza rokiem kalendarzowym)
- Integracje z bankami / API zewnętrzne
- Aplikacje mobilne natywne
- Masowe operacje, szablony, duplikowanie transakcji

### 3.3 Metryki sukcesu

| Metryka | Cel | Pomiar |
|---------|-----|--------|
| Import success rate | ≥90% wierszy z poprawnego mapowania zaimportowanych bez ręcznej korekty | `rows_imported` vs `rows_total` (−duplikaty −błędy walidacji) |
| Time-to-add (manual) | mediana <30 s | `transaction_create_opened` → `transaction_created` (frontend) |
| Activation import | ≥70% **nowo zarejestrowanych** z ≥1 importem w 7 dni | `user_registered` + `import_completed` w oknie 7 dni |
| Data isolation | 0 wycieków między użytkownikami | testy feature + QA |

**Cele produktowe (skrót):** użytkownik samodzielnie: rejestracja → konto → transakcja → lista z filtrami → import → transfer; dane ściśle per user.

### 3.4 Zdarzenia telemetryczne (skrót)

| Zdarzenie | Kontekst | FR |
|-----------|----------|-----|
| `user_registered`, `user_logged_in`, `user_login_failed` | Auth | FR-A1 |
| `password_reset_requested`, `password_reset_completed` | Reset hasła | FR-A2 |
| `account_created`, `account_updated`, `account_deleted`, `account_deleted_with_transactions` | Konta | FR-K1, FR-K2 |
| `transaction_created`, `transaction_updated`, `transaction_deleted` | Transakcje | FR-T1 |
| `transactions_filtered`, `transactions_sorted`, `transactions_page_changed` | Lista | FR-T2 |
| `transfer_created`, `transfer_failed_validation` | Transfer UI | FR-T3 |
| `account_balance_adjusted` | Korekta salda | FR-S1 |
| `import_started`, `import_completed`, `import_failed` | Import | FR-I1 |
| `import_type_inferred` | Typ ze znaku kwoty | FR-I2 |
| `import_rows_skipped_duplicate` | Dedupe | FR-I3 |
| `import_bank_resolved_from_account`, `import_headers_unrecognized` | Adapter | FR-I4 |
| `import_enrichment_typesense_hit`, `import_enrichment_typesense_miss` | Pamięć opisów | FR-I5 |
| `transfer_auto_linked`, `transfer_manually_linked`, `transfer_unlinked`, `transfer_match_skipped_ambiguous` | Matcher | FR-I6 |
| `category_created`, `category_updated` | Kategorie | FR-C1 |
| `category_estimate_annual_saved`, `category_estimate_monthly_saved` | Szacunki | FR-C3, FR-C4 |
| `budget_view_monthly`, `budget_view_yearly` | Widoki budżetu | FR-C5, FR-C6 |
| `pocket_created`, `pocket_updated`, `pocket_deleted` | Kieszenie | FR-P1 |
| `pocket_assigned_transfer` | Przypisanie kieszeni do transferu | FR-P3 |
| `category_memory_hit`, `category_memory_miss` | Pamięć kategorii (import) | FR-C7 |
| `deposit_created` | Ręczne otwarcie lokaty | FR-D1 |
| `deposit_closed` | Zamknięcie lokaty | FR-D1 |
| `deposit_updated` | Edycja metadanych lokaty | FR-D1 |
| `deposit_transaction_assigned` | Przypisanie transakcji do lokaty | FR-D1 |
| `deposit_import_open_auto` | Auto-otwarcie lokaty przy imporcie | FR-D2 |
| `deposit_import_close_auto` | Auto-zamknięcie lokaty przy imporcie (jedno dopasowanie) | FR-D2 |
| `deposit_import_open_candidate` | Kandydat otwarcia wstawiony przy imporcie | FR-D2 |
| `deposit_import_close_candidate` | Kandydat zamknięcia wstawiony przy imporcie (brak / wieloznaczne dopasowanie) | FR-D2 |
| `deposit_import_open_confirmed` | Użytkownik potwierdził kandydata otwarcia | FR-D2 |
| `deposit_import_close_confirmed` | Użytkownik potwierdził kandydata zamknięcia | FR-D2 |
| `deposit_import_candidate_rejected` | Użytkownik odrzucił kandydata lokaty | FR-D2 |

Kanał: log `telemetry` (daily, JSON line) — patrz §8.

---

## 4. Użytkownicy i przepływy

### 4.1 Persona

**Osoba prowadząca budżet osobisty:** 1–3 konta PLN, regularne korzystanie z bankowości, eksport CSV/XLSX. Priorytet: **desktop/laptop**; mobile web ma działać poprawnie (bez mobile-first w MVP).

### 4.2 Kluczowe journey

**A — Pierwsze użycie:** Rejestracja → konto → dodaj transakcję → lista + saldo. *Alt:* błędy walidacji → inline error → poprawka → zapis.

**B — Import:** Transakcje → Import (modal) → wybór konta → upload → auto-mapowanie → commit → wynik (X/Y/Z) → lista + saldo. *Alt:* częściowy błąd wierszy; same duplikaty (`rows_imported = 0` + komunikat).

**C — Transfer:** Transfer → konta źródło/cel → kwota, data, opis → 2 transakcje + salda.

**D — Usunięcie konta:** Usuń konto → znika z listy (soft delete) → transakcje w historii, **nieedytowalne**.

**E — Budżet, kategorie i kieszenie:** Rejestracja (kategorie startowe) → utwórz kieszeń (np. Wakacje) → ustaw plany P&L na widoku budżetu rocznym → widok miesięczny (plan vs fakty P&L + sekcja kieszeni: plan / odłożono / wypłacono) → transfer wewnętrzny z opcjonalną kieszenią i ruchem Odłożenie/Wypłata (FR-P3) → korekta kategorii na transakcjach P&L / import → widok roczny (podsumowanie roku P&L bez transferów). *Alt:* brak szacunku — tylko kolumna wykonania; przekroczenie planu — dozwolone, bez blokady.

**F — Flow A (kieszeń oszczędnościowa):** Kieszeń „Wakacje” z kwotą docelową i składką 200 PLN/mies. → co miesiąc transfer wewnętrzny z kieszenią i `pocket_movement = deposit` → przed wyjazdem transfer z tą samą kieszenią i `pocket_movement = withdrawal` → wydatki na ROR (kategoria np. Rozrywka; **bez** kieszeni — XOR z kategorią P&L).

---

## 5. Model domeny

**Relacje:** User 1—N Accounts, Transactions, Imports, Categories, Pockets, Deposits · Account 1—N Transactions · Category 1—N Transactions · Pocket 1—N Transactions (opcjonalnie) · Pocket N—1 Currency · Deposit 1—N DepositMovements · DepositMovement 1—1 Transaction · Transfer = 2 Transactions, ten sam `transfer_id` · Category 1—N CategoryAnnualEstimate, CategoryMonthlyEstimate.

### Account

| Pole | Opis |
|------|------|
| `name` | Nazwa |
| `currency_id` | Waluta (MVP: tylko PLN w UI) |
| `opening_balance`, `current_balance` | Decimal |
| `type` | `Ror`, `Savings` |
| `bank` | `BnpParibas`, `MBank`, `Cash` |
| `deleted_at` | Soft delete → transakcje read-only |

### Transaction

| Pole | Opis |
|------|------|
| `date`, `booked_at` | Data operacji / okresu (default `booked_at = date`) |
| `amount` | Decimal ze znakiem (ujemne = wydatek) |
| `type` | `income`, `expense`, `transfer`, `adjustment` |
| `description`, `subject` | Tekst |
| `normalized_description`, `dedupe_hash` | Dedupe importu; ręcznie: unikalny hash (UUID) |
| `transfer_id` | UUID łączący 2 nogi transferu |
| `transfer_match_status` | `none`, `auto`, `manual`, `rejected` |
| `transfer_candidate_for_id` | FK — kandydat pary (status `manual`) |
| `import_id`, `raw_statement_description` | Metadane importu |
| `category_id` | FK → Category; **wymagane** na `income` / `expense` / `adjustment`; **`null` na transferze** (`type = transfer` lub `transfer_id` ustawione) |
| `pocket_id` | FK → Pocket (nullable); wymagane na obu nogach transferu, gdy uczestniczy konto `Savings`; opcjonalne na przychodzie/wydatku/korekcie |
| `deposit_movement` | Relacja opcjonalna 1—1 → DepositMovement (przez `transaction_id`); kapitał lokaty (`open`, `close_principal`) ma `category_id = null`; odsetki (`interest`) — normalny przychód z kategorią |

### Deposit

| Pole | Opis |
|------|------|
| `user_id` | Właściciel |
| `currency_id` | Waluta (MVP: tylko PLN w UI) |
| `source_account_id` | FK → Account użyte przy otwarciu; domyślne konto zwrotu przy zamknięciu |
| `name` | Nazwa (nullable; fallback w Resource) |
| `principal` | Kwota zamrożona po otwarciu (decimal) |
| `opened_at`, `matures_at` | Data otwarcia; opcjonalna data zapadalności |
| `status` | `active`, `closed` |
| `closed_at` | Data zamknięcia (nullable) |

Jedna lokata = jedno założenie; zamknięcie zawsze 100% kapitału. Nie jest `Account` ani kieszenią.

### DepositMovement

| Pole | Opis |
|------|------|
| `deposit_id` | FK → Deposit |
| `transaction_id` | FK → Transaction (**unique**) |
| `kind` | `open`, `close_principal`, `interest` |

Unikalna para `(deposit_id, kind)` — co najwyżej jeden ruch każdego typu na lokatę.

### Category

| Pole | Opis |
|------|------|
| `user_id` | Właściciel |
| `name` | Nazwa wyświetlana |
| `icon` | Nazwa ikony Lucide (kebab-case), z whitelisty |
| `color` | Kolor hex z palety stałych swatchy |
| `type` | `income`, `expense` |
| `sort_order` | Kolejność listy; fallback importu = pierwsza kategoria danego typu |
| `is_system` | Kategoria systemowa (v1: brak w seedzie; pole zachowane na przyszłość) |

### CategoryAnnualEstimate

| Pole | Opis |
|------|------|
| `category_id`, `year` | Rok kalendarzowy; unikalna para |
| `amount` | Szacunek roczny (nullable = brak planu) |

### CategoryMonthlyEstimate

| Pole | Opis |
|------|------|
| `category_id`, `year`, `month` | Miesiąc 1–12; unikalna trójka |
| `amount` | Nadpisanie szacunku miesięcznego (nullable = brak zapisanego nadpisania; widok miesięczny pokazuje `0`) |

### Pocket

| Pole | Opis |
|------|------|
| `user_id` | Właściciel |
| `name` | Nazwa wyświetlana (np. „Wakacje”) |
| `icon` | Ikona Lucide (kebab-case), ta sama whitelist co kategorie |
| `color` | Kolor hex z palety stałych swatchy |
| `sort_order` | Kolejność listy |
| `currency_id` | Waluta celu (MVP: tylko PLN w UI; **niezmienialna** po utworzeniu — jak na koncie) |
| `target_amount` | Kwota docelowa w walucie celu (nullable = koperta bez limitu) |
| `planning_mode` | `monthly` \| `by_date` (nullable gdy brak `target_amount`) |
| `monthly_contribution` | Deklaracja użytkownika; zapisywana tylko przy `planning_mode = monthly` |
| `target_date` | Termin; zapisywany tylko przy `planning_mode = by_date` |
| `is_archived` | Ręczne ukrycie z domyślnych list i sekcji budżetu |

Brak pola `type` — cel nie jest przychodem ani wydatkiem P&L. Metryki skumulowane (saldo, postęp, rekomendowana składka, prognozowana data ukończenia) — obliczane przy odczycie, nie w DB (spec: `.docs/superpowers/specs/2026-06-04-goals-target-model-design.md`).

### Import

| Pole | Opis |
|------|------|
| Status | `draft` → `queued` → `processing` → `committed` \| `failed` |
| Liczniki | `rows_total`, `rows_imported`, `rows_skipped_duplicate`, `rows_failed_validation` |
| `details` (JSON) | `mapping_used`, `source_file`, `parser`, `bank`, `headers`, `diagnostics` |

`draft`: stan techniczny po uploadzie, przed `queued` — użytkownik **nie** widzi podglądu ani mapowania.

### AccountBalanceAdjustment

Audyt akcji „Ustaw saldo”: kto, kiedy, stare → nowe saldo.

**Własność i retencja:** wszystko per User; brak auto-retencji transakcji w MVP; pliki importu `failed` — 30 dni, potem purge; sukces — plik usunięty po commit.

---

## 6. Katalog wymagań

### 6.1 Autentykacja

### FR-A1 — Rejestracja i logowanie

| Pole | Wartość |
|------|---------|
| **Priorytet** | Must |
| **Domena** | Auth |

**Zachowanie**
Użytkownik zakłada konto (email + hasło) i loguje się; sesja Laravel.

**Kryteria akceptacji**
1. Given niezalogowany When rejestracja poprawna Then zalogowany i widzi aplikację.
2. Given niezalogowany When logowanie poprawne Then widzi aplikację.

**Reguły i przypadki brzegowe**
- Duplikat email — błąd walidacji.
- Hasło: reguły Laravel (domyślne progi siły).

**Zdarzenia:** `user_registered`, `user_logged_in`, `user_login_failed`

---

### FR-A2 — Reset hasła

| Pole | Wartość |
|------|---------|
| **Priorytet** | Should |
| **Domena** | Auth |

**Zachowanie**
Reset hasła e-mailem; brak ujawniania, czy email istnieje w systemie.

**Kryteria akceptacji**
1. Given użytkownik nie pamięta hasła When inicjuje reset Then otrzymuje mail (jeśli konto istnieje) i może ustawić nowe hasło bez enumeracji kont.

**Reguły**
- Rate limit żądań resetu: 6/min per IP (patrz §8).

**Zdarzenia:** `password_reset_requested`, `password_reset_completed`

---

### 6.2 Konta

### FR-K1 — CRUD kont

| Pole | Wartość |
|------|---------|
| **Priorytet** | Must |
| **Domena** | Accounts |

**Zachowanie**
Tworzenie, edycja, usuwanie (soft), lista kont z walutą i saldem bieżącym.

**Pola (MVP):** `name`, waluta PLN w UI, `opening_balance`, `type` (`Ror`, `Savings`), `bank` (`BnpParibas`, `MBank`, `Cash`). Ikona banku per wartość `bank`.

**Kryteria akceptacji**
1. Given zalogowany When dodaje konto z wymaganymi polami Then konto na liście, saldo ustawione.
2. Given konto When edycja nazwy / `opening_balance` Then zapis; `current_balance` zgodnie z regułą delty.
3. Given `opening_balance = O1`, `current_balance = C` When zmiana na `O2` bez zmiany transakcji Then `opening_balance = O2`, `current_balance = C + (O2 − O1)`.

**Reguły**
- `type` i `bank` z dozwolonej listy enum.
- Zmiana `opening_balance` **nie** tworzy `adjustment`; korekta bieżącego salda → FR-S1.
- Waluta: encja w DB; UI tylko PLN w MVP.

**Zdarzenia:** `account_created`, `account_updated`, `account_deleted`

---

### FR-K2 — Usunięcie konta

| Pole | Wartość |
|------|---------|
| **Priorytet** | Must |
| **Domena** | Accounts |

**Zachowanie**
Usunięcie konta nie usuwa transakcji; blokuje ich edycję i usuwanie.

**Kryteria akceptacji**
1. Given konto z transakcjami When usunięcie Then transakcje widoczne w historii, bez edycji/usuwania.

**Decyzja:** soft-delete konta; transakcje zachowują `account_id`; UI i API read-only dla transakcji na usuniętym koncie.

**Reguły**
- Import na usunięte konto — blokada.
- Transfer do/z usuniętego konta — blokada.

**Zdarzenia:** `account_deleted_with_transactions` (z liczbą transakcji)

---

### 6.3 Transakcje

### FR-T1 — CRUD transakcji (przychód/wydatek)

| Pole | Wartość |
|------|---------|
| **Priorytet** | Must |
| **Domena** | Transactions |

**Zachowanie**
Dodawanie, edycja, usuwanie na aktywnym koncie; wpływ na `current_balance`. Od wave 2: wymagane pole `category_id` (FR-C2). Transakcja P&L **nie** może mieć `pocket_id` (XOR z kategorią).

**Kryteria akceptacji**
1. Given aktywne konto When dodanie z `date`, opcjonalnie `booked_at` (default = `date`), kwota, opis, opcjonalnie `subject`, **kategoria** Then wpis na liście, saldo zaktualizowane (ujemna zmniejsza, dodatnia zwiększa).
2. Given istniejąca transakcja When edycja pól Then zapis i korekta salda; zmiana samego `booked_at` **nie** zmienia salda bieżącego.
3. Given wave 2 When zapis bez `category_id` Then 422.

**Reguły**
- Konto usunięte: brak edycji/usuwania.
- Kwota = 0: niedozwolona (FormRequest, Akcja, Importer).
- Data w przyszłości: dozwolona.
- `booked_at` dowolny względem `date`; default `booked_at = date`.
- Ręczny duplikat (ta sama data, kwota, opis): **dozwolony** — `dedupe_hash` z sufiksem UUID.
- `category_id`: wymagane; typ kategorii musi pasować do typu ekonomicznego transakcji (`income` / `expense` ze znaku kwoty).

**Zdarzenia:** `transaction_created`, `transaction_updated`, `transaction_deleted`

---

### FR-T2 — Lista, filtry, podsumowanie

| Pole | Wartość |
|------|---------|
| **Priorytet** | Must |
| **Domena** | Transactions |

**Zachowanie**
Lista z filtrem konta i dat (`booked_at`), sort, paginacja, suma wpływów i wydatków w okresie.

**Kryteria akceptacji**
1. Given lista When filtr `from`/`to` po `booked_at` i konto Then tylko pasujące wiersze i poprawne podsumowanie.
2. Given `date=01.04`, `booked_at=30.03` When filtr marzec Then widoczna w marcu, niewidoczna w kwietniu.

**Reguły**
- Empty state przy braku wyników; `from > to` — walidacja.
- **Summary:** wyklucz transakcje z `transfer_id` (wewnętrzne transfery); `adjustment` wliczaj wg znaku kwoty.
- Kolumna daty okresu: `COALESCE(booked_at, date)` + etykieta względna; `date` w Create/Edit i tooltip importu gdy różni się od okresu.
- Filtry, sort, summary: po `COALESCE(booked_at, date)`; domyślny sort: data okresu desc, tie-breaker `date desc, id desc`.

**Zdarzenia:** `transactions_filtered`, `transactions_sorted`, `transactions_page_changed`

---

### FR-T3 — Transfer między kontami

| Pole | Wartość |
|------|---------|
| **Priorytet** | Must |
| **Domena** | Transactions |

**Zachowanie**
Jedna akcja UI → 2 transakcje, wspólna data, przeciwne kwoty, wspólny `transfer_id`. **Bez** `category_id` na obu nogach (`null`). Opcjonalnie: ten sam `pocket_id` na obu nogach z wymaganym `pocket_movement` (`deposit` \| `withdrawal`); dozwolone na dowolnym transferze (ROR↔ROR, ROR↔Savings, Cash↔ROR). Gdy `pocket_id` ustawione — waluta kieszeni musi pasować do waluty transferu.

**Kryteria akceptacji**
1. Given dwa różne konta When transfer kwoty X w dacie D Then na źródle `-X`, na celu `+X`, salda obu kont zaktualizowane.
2. Given transfer When zapis Then obie nogi mają `category_id = null`.
3. Given transfer bez `pocket_id` When zapis Then 201; obie nogi mają `pocket_id = null` i `pocket_movement = null`.
4. Given transfer z `pocket_id` When brak `pocket_movement` Then 422.
5. Given transfer ROR↔ROR z `pocket_id` i `pocket_movement = deposit` When zapis Then 201; metryka kieszeni +X (raz per `transfer_id`).
6. Given transfer When `category_id` ustawione Then 422.
7. Given transfer z `pocket_id` When zapis Then obie nogi mają ten sam `pocket_id` i `pocket_movement`.
8. Given rozłączenie transferu When unlink Then typ przywrócony ze znaku kwoty, przypisana kategoria fallback, `pocket_id` i `pocket_movement` = null.

**Reguły**
- To samo konto jako źródło i cel — zabronione.
- Usunięte konto — blokada.
- Formularz: kwota dodatnia; system zapisuje znaki na transakcjach.
- Formularz transferu: opcjonalna kieszeń + wybór ruchu (Odłożenie/Wypłata); smart default w UI (Savings → deposit/withdrawal), API akceptuje oba ruchy.
- Formularz transferu: **bez** pickera kategorii.

**Zdarzenia:** `transfer_created`, `transfer_failed_validation`, `pocket_assigned_transfer`

---

### 6.4 Salda

### FR-S1 — Saldo i korekta

| Pole | Wartość |
|------|---------|
| **Priorytet** | Must |
| **Domena** | Balances |

**Zachowanie**
`current_balance` utrzymywane przy CRUD transakcji; ręczna korekta przez transakcję `adjustment` + audyt.

**Kryteria akceptacji**
1. Given saldo When CRUD transakcji Then `current_balance` += delta kwoty.
2. Given korekta na wartość X When zapis Then transakcja `adjustment` z kwotą `X − current_balance`, `booked_at = today`, `current_balance = X`, wpis w `account_balance_adjustments`.
3. Given dowolna historia When `accounts:recalculate-balance --dry-run` Then 0 różnic vs `opening_balance + SUM(amount)`.

**Decyzja:** saldo **zapisywane** (nie wyliczane przy każdym odczycie) + krótkie transakcje DB + komenda rekalkulacji jako safety net.

**Reguły**
- Korekta nie nadpisuje historii; badge „Korekta” na liście.
- Edycja `opening_balance` — delta na `current_balance` (FR-K1), bez `adjustment`.

**Zdarzenia:** `account_balance_adjusted` (stare→nowe; opcjonalny reason)

---

### 6.5 Import

### FR-I1 — Import auto-commit

| Pole | Wartość |
|------|---------|
| **Priorytet** | Must |
| **Domena** | Import |

**Zachowanie**
Wybór konta → upload CSV/XLSX → adapter z `Account.bank` mapuje kolumny → kolejka commit bez preview → wynik z licznikami.

**Kryteria akceptacji**
1. Given konto z obsługiwanym bankiem When plik z rozpoznanymi nagłówkami Then auto-start importu i wynik: `rows_imported`, `rows_skipped_duplicate`, `rows_failed_validation`.
2. Given nagłówki nierozpoznane When upload Then błąd `unrecognized_headers`, brak transakcji.

**Reguły**
- Formaty kwot: `1234,56`, `1 234,56`, `1.234,56`, `1234.56`, `(123,45)`; waluty `PLN`/`zł`/`EUR`/`USD` usuwane.
- Formaty dat: `d-m-Y`, `Y-m-d`, `d/m/Y`, `d.m.Y`, `Y.m.d`, `Y/m/d`; suffix czasu odcinany.
- Kodowanie: UTF-8 / Windows-1250 / ISO-8859-2 → UTF-8, BOM usunięty.
- XLSX: pierwszy arkusz; CSV: auto-separator; nagłówki wymagane; kwota 0 → `rows_failed_validation++`.
- Konto `Cash`: import z pliku — 422 z `message_key`, nie 500.
- Commit asynchroniczny: `queued` → `processing` → `committed`|`failed`; status realtime (§8).

**Zdarzenia:** `import_started`, `import_completed`, `import_failed`

---

### FR-I2 — Typ ze znaku kwoty

| Pole | Wartość |
|------|---------|
| **Priorytet** | Must |
| **Domena** | Import |

**Zachowanie**
Typ z znaku kwoty; kwota w DB ze znakiem (ujemna = wydatek).

**Kryteria akceptacji**
1. Given kwota ujemna When import Then `expense`, kwota ujemna w DB.
2. Given kwota dodatnia When import Then `income`, kwota dodatnia w DB.

**Reguły:** nawiasy księgowe `(123,45)` = ujemne.

**Zdarzenia:** `import_type_inferred`

---

### FR-I3 — Deduplikacja

| Pole | Wartość |
|------|---------|
| **Priorytet** | Must |
| **Domena** | Import |

**Zachowanie**
Import pomija duplikaty na koncie; ręczne dodanie identycznej transakcji dozwolone.

**Kryteria akceptacji**
1. Given dwa identyczne wiersze w pliku When import Then pierwszy zapisany, drugi `rows_skipped_duplicate++`.
2. Given ręczne dodanie z tym samym kluczem When zapis Then dwa rekordy w bazie.

**Reguły**
- Normalizacja opisu: trim, case-fold, whitespace → jedna spacja.
- Znana wada MVP: dwa zakupy ten sam dzień/sklep/kwota — drugi import pominięty; telemetria `import_rows_skipped_duplicate`.
- Brak `bank_reference_id` (banki MVP bez unikalnego ID w eksporcie).

**Zdarzenia:** `import_rows_skipped_duplicate`

---

### FR-I4 — Adaptery banków

| Pole | Wartość |
|------|---------|
| **Priorytet** | Must |
| **Domena** | Import |

**Zachowanie**
Mapowanie kolumn w adapterze banku; użytkownik wybiera tylko konto (`bank` z konta). Adapter: nagłówki, daty, kwoty, opis, opcjonalnie `subject`.

**Kryteria akceptacji**
1. Given konto mBank / BNP When standardowy wyciąg Then auto-mapowanie i import bez interakcji użytkownika.

**Reguły**
- Bank importu = `Account.bank` (brak osobnego wyboru banku w UI importu).
- Nowe formaty nagłówków — rozszerzenie adaptera / aliasy nagłówków.
- `Cash`: import zablokowany (422).

**Zdarzenia:** `import_bank_resolved_from_account`, `import_headers_unrecognized`

---

### FR-I5 — Pamięć opisów (Should)

| Pole | Wartość |
|------|---------|
| **Priorytet** | Should |
| **Domena** | Import |

**Zachowanie**
Po edycji `subject`/`description` na transakcji z importu system zapamiętuje mapowanie z `raw_statement_description` i stosuje przy kolejnym imporcie (best-effort, wyszukiwarka opisów w infrastrukturze). Od wave 2: ta sama pamięć może przechowywać `category_id` (patrz FR-C7).

**Kryteria akceptacji**
1. Given edycja zaimportowanej transakcji When zapis Then pamięć per user + bank.
2. Given kolejny import z tym samym (znormalizowanym) surowym opisem When commit Then uzupełnione `subject`/`description` jeśli jest dopasowanie.
3. Given wave 2 i edycja kategorii na zaimportowanej transakcji When kolejny import Then ta sama kategoria, jeśli pamięć trafi (FR-C7).

**Reguły**
- Brak dopasowania: puste `subject`, `description` z surowego opisu; kategoria wg FR-C7 (fallback).
- Izolacja per `user_id` (+ bank w filtrze).
- Niedostępność usługi wyszukiwania **nie** blokuje importu.
- Brak edytowalnych szablonów mapowania kolumn w UI (FR-I4).

**Zdarzenia:** `import_enrichment_typesense_hit`, `import_enrichment_typesense_miss`

---

### FR-I6 — Matcher transferów przy imporcie (Should)

| Pole | Wartość |
|------|---------|
| **Priorytet** | Should |
| **Domena** | Import |

**Zachowanie**
Po commicie importu matcher łączy przeciwne kwoty między kontami użytkownika; pewne przypadki auto, niejednoznaczne → baner kandydatów na liście transakcji.

**Kryteria akceptacji**
1. Given import −200 PLN (mBank) i +200 PLN (BNP), opisy z tokenem przelewu własnego, Δdata ≤3 dni When commit Then wspólny `transfer_id`, `type=transfer`, `transfer_match_status=auto`, salda bez zmiany względem stanu sprzed linku.
2. Given przeciwne kwoty bez tokenów transferu When commit Then brak auto-linku; `manual` + `transfer_candidate_for_id`; baner do potwierdzenia/odrzucenia.
3. Given >1 kandydat When matcher Then brak auto-linku; wszyscy `manual`.
4. Given „To nie transfer” When zapis Then `rejected`; brak ponownej propozycji po kolejnym imporcie.
5. Given połączony transfer When „Rozłącz transfer” Then brak `transfer_id`, `type` ze znaku `amount`, `rejected`, kategoria fallback na obu nogach (FR-C2).
6. Given połączona para When link (auto lub manual) Then `category_id = null` na obu nogach.

**Decyzja:** matcher **synchroniczny** po commicie importu (MVP).

**Reguły**
- `Cash` — matcher działa.
- Różne waluty — pomijaj.
- Transakcja z `transfer_id` z UI Transfer — poza matcherem.
- Tokeny (np. „przelew własny”, „transfer”) — konfigurowalna lista w aplikacji.

**Zdarzenia:** `transfer_auto_linked`, `transfer_manually_linked`, `transfer_unlinked`, `transfer_match_skipped_ambiguous`

---

### 6.6 Kategorie

### FR-C1 — CRUD kategorii + zestaw startowy

| Pole | Wartość |
|------|---------|
| **Priorytet** | Must |
| **Domena** | Categories |

**Zachowanie**
Użytkownik zarządza katalogiem kategorii P&L (etykiety transakcji zewnętrznych). Przy pierwszym użyciu (rejestracja lub pierwsze wejście) system tworzy **bogaty zestaw startowy** (wydatki i przychody), każda z ikoną i kolorem. Użytkownik dodaje i edytuje kategorie na osobnych ekranach (nazwa, typ, ikona, kolor — kolor wymagany przy zapisie), ustawia kolejność (`sort_order`) na liście. Usunięcie kategorii z przypisanymi transakcjami — zablokowane (v1). Zmiana `type` kategorii — zablokowana, gdy istnieją transakcje. **Ekran kategorii nie zawiera pól szacunków** — plany P&L edytuje się wyłącznie na widokach budżetu (FR-UX1).

**Kryteria akceptacji**
1. Given nowy użytkownik When pierwszy dostęp do kategorii/budżetu Then istnieje zestaw startowy.
2. Given kategoria z transakcjami When usunięcie Then błąd walidacji, kategoria pozostaje.

**Reguły**
- Izolacja per `user_id`.
- Zestaw startowy: ~20 kategorii wydatków i 5 przychodów z ikonami/kolorami (np. Artykuły spożywcze, Transport, Wynagrodzenie); **bez** kategorii oszczędności — oszczędzanie śledzi się przez **cele** (FR-P*).
- `sort_order` decyduje o „pierwszej możliwej” kategorii przy imporcie (FR-C7).

**Zdarzenia:** `category_created`, `category_updated`

---

### FR-C2 — Kategoria wymagana na transakcji

| Pole | Wartość |
|------|---------|
| **Priorytet** | Must |
| **Domena** | Transactions |

**Zachowanie**
Ręczne dodanie/edycja przychodu/wydatku/korekty oraz wiersz importu (przed połączeniem jako transfer) wymagają `category_id` należącego do użytkownika. Typ kategorii musi pasować do typu ekonomicznego transakcji (`income` / `expense` ze znaku kwoty / `TransactionType`). **Transfer:** obie nogi mają `category_id = null` — brak kategorii P&L. **Kapitał lokaty:** transakcje powiązane z `deposit_movements.kind` ∈ `open`, `close_principal` mają `category_id = null` (jak transfer); **odsetki** (`kind=interest`) pozostają przychodem P&L z wymaganą kategorią. **P&L:** `pocket_id` zabronione (XOR z kategorią). `adjustment`: kategoria wymagana. Matcher importu (FR-I6): po połączeniu pary jako transfer — `category_id = null` na obu nogach.

**Kryteria akceptacji**
1. Given tworzenie transakcji P&L When brak kategorii Then 422.
2. Given wydatek When kategoria przychodowa Then 422.
3. Given transfer When zapis Then obie nogi mają `category_id = null`.
4. Given transfer When `category_id` ustawione Then 422.
5. Given transakcja P&L When `pocket_id` ustawione Then 422.
6. Given matcher połączył parę When link Then `category_id = null` na obu nogach.

**Reguły**
- Pre-release: migracja przypisuje kategorię wszystkim istniejącym transakcjom P&L; transfery — `category_id = null`; po release brak trwałego stanu „bez kategorii” na P&L.
- Transakcje na usuniętym koncie: read-only (bez zmiany kategorii).

---

### 6.7 Budżet i szacunki

### FR-C3 — Szacunki roczne

| Pole | Wartość |
|------|---------|
| **Priorytet** | Must |
| **Domena** | Budgets |

**Zachowanie**
Użytkownik ustawia opcjonalny **szacunek roczny** per kategoria i rok kalendarzowy (przychód i wydatek). Przekroczenie szacunku przez wykonanie jest dozwolone — wyłącznie informacja w UI (różnica plan vs fakty). **Edycja planu:** widok budżetu rocznego (FR-UX1), nie ekran kategorii.

**Kryteria akceptacji**
1. Given kategoria i rok 2026 When zapis szacunku 12000 Then widok roczny pokazuje plan 12000.
2. Given wykonanie 13000 When widok roczny Then wykonanie 13000, różnica +1000, bez błędu.

**Reguły**
- Kwoty ≥ 0; skala decimal jak w reszcie aplikacji.
- Brak szacunku rocznego: widok pokazuje tylko wykonanie (plan „—”).

**Zdarzenia:** `category_estimate_annual_saved`

---

### FR-C4 — Szacunki miesięczne (nadpisania)

| Pole | Wartość |
|------|---------|
| **Priorytet** | Must |
| **Domena** | Budgets |

**Zachowanie**
Użytkownik może nadpisać plan na konkretny miesiąc. Gdy brak nadpisania, widok miesięczny pokazuje **`0`** (plan miesięczny jest jawny, niezależny od rocznego). Suma 12 nadpisań może różnić się od rocznego — **miękka informacja** (bez blokady zapisu); istotna głównie na początku roku. **Edycja nadpisań:** widok budżetu miesięcznego (FR-UX1), nie ekran kategorii. Z widoku rocznego można opcjonalnie ustawić jednolitą kwotę miesięczną dla bieżącego i przyszłych miesięcy roku (bez nadpisywania miesięcy z własnym planem).

**Kryteria akceptacji**
1. Given roczny 5000 i nadpisanie marca = 1500 When widok marzec Then plan 1500 dla kategorii.
2. Given suma nadpisań 4200 i roczny 5000 When styczeń Then opcjonalna wskazówka „4200 / 5000”, zapis dozwolony.

**Zdarzenia:** `category_estimate_monthly_saved`

---

### FR-C5 — Widok budżetu miesięczny

| Pole | Wartość |
|------|---------|
| **Priorytet** | Must |
| **Domena** | Budgets |

**Zachowanie**
Dla wybranego miesiąca i roku kalendarzowego: tabela kategorii P&L (sekcje przychody / wydatki) z kolumnami: szacunek miesiąca (edytowalny), wykonanie, różnica. Okres i agregacja fakty: `COALESCE(booked_at, date)` w granicach miesiąca. Fakty kategorii P&L: **bez** wierszy z `transfer_id` (jak podsumowanie FR-T2). **Sekcja „Kieszenie”:** jeden wiersz per kieszeń użytkownika (bez filtra archiwum) — **Plan** (tylko do odczytu): `monthly_contribution` przy `planning_mode = monthly`, w przeciwnym razie rekomendowana składka z `by_date`; brak planu — „—”. Kolumny miesięczne: **odłożono**, **wypłacono** (z `pocket_movement` = deposit/withdrawal, liczone raz per `transfer_id` w miesiącu); opcjonalnie wskazówka postępu odłożono vs plan. Zastępuje wave-2 sekcję agregatu „Transfery i oszczędności”. Sekcja ukryta **tylko** gdy użytkownik nie ma żadnej kieszeni.

**Kryteria akceptacji**
1. Given wydatek w kategorii Jedzenie w marcu When widok marzec Then wykonanie w Jedzeniu.
2. Given transfer wewnętrzny When tabela kategorii Then transfer nie wliczony do wykonania wydatków kategorii.
3. Given kieszeń Wakacje, plan 200, transfer z `pocket_id` i `pocket_movement = deposit` 200 When sekcja kieszeni Then odłożono 200.
4. Given wypłata z `pocket_movement = withdrawal` 150 z tą samą kieszenią When sekcja kieszeni Then wypłacono 150.
5. Given transfer ROR↔ROR z `pocket_movement = deposit` When sekcja kieszeni Then odłożono kwotę transferu (raz per para).

**Reguły**
- `adjustment` w wykonaniu kategorii — wg znaku kwoty (zgodnie z FR-T2).
- Metryki kieszeni z `pocket_movement` (deposit → odłożono, withdrawal → wypłacono); **bez** metryki powiązanych wydatków (`linked_expenses`).
- Kwoty w sekcji kieszeni wyświetlane z symbolem waluty kieszeni (np. `1 500,00 zł`).
- Kolumna **Plan** kieszeni — tylko do odczytu; źródło: pola kieszeni (FR-P2), nie szacunki per rok.
- Miękka wskazówka: suma planów P&L vs roczne (bez kieszeni) — jak FR-C4.

**Zdarzenia:** `budget_view_monthly`

---

### FR-C6 — Widok budżetu roczny

| Pole | Wartość |
|------|---------|
| **Priorytet** | Must |
| **Domena** | Budgets |

**Zachowanie**
Dla wybranego roku kalendarzowego: per kategoria szacunek roczny vs wykonanie (przychody i wydatki osobno lub z oznaczeniem typu). **Bez** sekcji transferów; **bez** rozbicia na miesięczne nadpisania. Agregacja fakty: `transfer_id IS NULL`; okres po `COALESCE(booked_at, date)` w roku.

**Kryteria akceptacji**
1. Given transakcje w 2026 When widok roczny 2026 Then sumy per kategoria bez transferów wewnętrznych.
2. Given plan oszczędności When widok roczny Then planowanie oszczędności wyłącznie przez **cele** (FR-P2 na `/pockets`), nie przez kategorie P&L.

**Zdarzenia:** `budget_view_yearly`

---

### FR-C7 — Pamięć kategorii przy imporcie

| Pole | Wartość |
|------|---------|
| **Priorytet** | Must |
| **Domena** | Import |

**Zachowanie**
**Nie** mapować kolumny kategorii z wyciągu banku. Przy commicie: `category_id` z pamięci opisu (ten sam klucz co FR-I5: `user_id` + bank + znormalizowany surowy opis). Przy trafieniu — zapisana kategoria. Przy braku — pierwsza kategoria pasującego typu (`income` / `expense`) wg `sort_order`. Niedostępność Typesense **nie** blokuje importu (fallback jak FR-I5).

**Kryteria akceptacji**
1. Given wcześniejsza ręczna kategoria dla opisu When ponowny import tego opisu Then ta sama kategoria.
2. Given brak pamięci When import wydatku Then pierwsza kategoria wydatkowa wg `sort_order`.
3. Given plik mBank z kolumną Kategoria When import Then kolumna ignorowana przy przypisaniu kategorii.

**Reguły**
- Po ręcznej edycji kategorii na transakcji z importu — zapamiętaj mapowanie (rozszerzenie pamięci opisów o `category_id`).
- Pamięć per `user_id` (+ bank w filtrze).

**Zdarzenia:** `category_memory_hit`, `category_memory_miss`

---

### FR-C8 — Kategoria na liście transakcji

| Pole | Wartość |
|------|---------|
| **Priorytet** | Should |
| **Domena** | Transactions |

**Zachowanie**
Lista transakcji wyświetla nazwę kategorii (transakcje P&L); transfer wewnętrzny — etykieta transferu i opcjonalnie cel, **bez** kategorii; opcjonalny filtr po `category_id` (transfery wykluczone).

**Kryteria akceptacji**
1. Given lista When wiersz transakcji P&L Then widoczna nazwa kategorii.
2. Given lista When wiersz transferu Then brak kategorii; opcjonalnie widoczny cel.
3. Given filtr kategorii When zastosowanie Then tylko pasujące wiersze P&L (bez transferów).

---

### 6.8 Lokaty (deposits)

### FR-D1 — Lokaty (term deposits)

| Pole | Wartość |
|------|---------|
| **Priorytet** | Should |
| **Domena** | Deposits |

**Zachowanie**
Użytkownik śledzi **lokaty terminowe** jako kapitał zaparkowany poza P&L: trzecia zakładka **Lokaty** na `/konta?kind=deposits` (obok Rachunki / Portfele). Otwarcie to **jedna** transakcja wydatek `−principal` na aktywnym koncie bankowym (`bank !== cash`); zwrot kapitału to przychód `+principal`; odsetki — osobny przychód z kategorią. Wszystkie nogi mają `transfer_id = null` (nie transfer). Kapitał (`open`, `close_principal`) wykluczony z podsumowania listy transakcji, budżetu P&L, cashflow dashboardu i puli matchera transferów; odsetki (`interest`) wliczane do P&L. v1: ręczne otwarcie, zamknięcie (± odsetki), edycja nazwy/`matures_at`, przypisanie istniejącej transakcji (open / close_principal / interest). Auto-klasyfikacja kapitału przy imporcie CSV — **FR-D2** (BNP/mBank); pozostałe banki: ręczne przypisanie. Konto `Cash` jako źródło/cel — 422. Dashboard: piąty KPI sumy aktywnych lokat PLN.

**Kryteria akceptacji**
1. Given otwarcie lokaty When zapis Then transakcja `−principal` z `category_id = null`, movement `open`, saldo konta źródłowego zmniejszone; podsumowanie listy i budżet **nie** wliczają tej kwoty.
2. Given zamknięcie z odsetkami When zapis Then `+principal` (`close_principal`, bez kategorii) i opcjonalnie `+interest` z kategorią przychodu; lokata `closed`.
3. Given odsetki lokaty When widok P&L / summary Then kwota odsetek **wliczona** jak zwykły przychód.
4. Given konto `Cash` When otwarcie lub zamknięcie lokaty Then 422.
5. Given przypisanie close When kwota transakcji ≠ `principal` aktywnej lokaty (ta sama waluta) Then 422.
6. Given przypisanie open When kwota ≥ 0 Then 422.

**Reguły**
- Jedna lokata = jedno założenie; `principal` zamrożony po otwarciu; zamknięcie zawsze 100%; brak delete w v1.
- Assign: transakcja bez `transfer_id`, bez istniejącego `deposit_movement`, nie `adjustment`, konto bankowe nieusunięte; co najwyżej jeden movement `interest` na lokatę.
- Import CSV (banki bez reguł FR-D2): wiersze kapitału lokaty pozostają P&L do ręcznego przypisania; BNP/mBank — patrz FR-D2.
- Izolacja per `user_id`; write routes `/lokaty`; index na `accounts.index?kind=deposits`.

**Zdarzenia:** `deposit_created`, `deposit_closed`, `deposit_updated`, `deposit_transaction_assigned`

---

### FR-D2 — Import lokat (BNP/mBank)

| Pole | Wartość |
|------|---------|
| **Priorytet** | Should |
| **Domena** | Deposits / Import |

**Zachowanie**
Przy commicie importu CSV/XLSX system rozpoznaje wiersze **kapitału lokaty** (otwarcie / zwrot kapitału) dla kont **BNP Paribas** i **mBank** według sygnałów bankowych. Pewne przypadki → auto-otwarcie lub auto-zamknięcie lokaty; niejednoznaczne → **kandydat** na liście transakcji (baner + filtr, wzór FR-I6). Wiersze kandydatów i auto-kapitału mają `category_id = null` od razu (kapitał poza P&L). **Odsetki** lokaty pozostają zwykłym przychodem P&L z kategorią — bez auto-linku do lokaty. Matcher transferów (FR-I6) pomija wiersze z `deposit_match_status ≠ none`.

**Kryteria akceptacji**
1. Given import BNP z `Typ transakcji = OTWARCIE LOKATY` i ujemną kwotą When commit Then auto-otwarcie lokaty, movement `open`, `deposit_match_status = auto`, `category_id = null`; podsumowanie listy i budżet **nie** wliczają kwoty.
2. Given import BNP z `Typ transakcji = SPŁATA DEPOZYTU` i dodatnią kwotą oraz dokładnie jedna aktywna lokata z tym samym `principal` i walutą When commit Then auto-zamknięcie, movement `close_principal`, lokata `closed`.
3. Given zwrot kapitału When 0 lub ≥2 aktywne lokaty z pasującym `principal` Then kandydat zamknięcia (`deposit_match_status = manual`, `deposit_candidate_kind = close_principal`); użytkownik wybiera lokatę przy potwierdzeniu.
4. Given import mBank z `Kategoria = Lokaty i konto oszcz.` When ujemna kwota Then auto-otwarcie; gdy dodatnia — logika zamknięcia jak BNP (1 dopasowanie → auto, inaczej kandydat). Kolumna `Kategoria` **nie** wpływa na `category_id` (FR-C7).
5. Given kandydat lokaty When potwierdzenie / odrzucenie z banera Then odpowiednio movement + lokata lub `deposit_match_status = rejected`; kapitał nadal poza P&L do ręcznej kategorii po odrzuceniu.
6. Given wiersz odsetek lokaty When import Then normalna ścieżka P&L z kategorią (bez auto-linku do lokaty).

**Reguły**
- Klasyfikacja **inline** w `CommitImport` przed przypisaniem kategorii; hook na adapterze banku (domyślnie brak reguł).
- Auto-zamknięcie: dopasowanie po `user_id`, `currency_id`, `status = active`, `principal = |amount|` (nie wymaga tego samego `source_account_id`).
- Kandydaci: baner na `/transakcje` + filtr `deposit_candidates=1`; badge „Kandydat lokaty”.
- Inne banki MVP: brak auto-detekcji — ręczne przypisanie (FR-D1).
- Spec: `.docs/superpowers/specs/2026-09-01-deposits-import-design.md`.

**Zdarzenia:** `deposit_import_open_auto`, `deposit_import_close_auto`, `deposit_import_open_candidate`, `deposit_import_close_candidate`, `deposit_import_open_confirmed`, `deposit_import_close_confirmed`, `deposit_import_candidate_rejected`

---

### 6.9 Kieszenie (pockets)

### FR-P1 — CRUD kieszeni

| Pole | Wartość |
|------|---------|
| **Priorytet** | Must |
| **Domena** | Pockets |

**Zachowanie**
Użytkownik zarządza kopertami oszczędnościowymi jak katalogiem kategorii: lista z ikoną i kolorem, drag reorder (`sort_order`), dodawanie i edycja w modalu na liście `/pockets`. Pola: nazwa, ikona, kolor, **waluta** (`currency_id`; MVP: tylko PLN w UI, domyślnie PLN); opcjonalna kwota docelowa; przy kwocie — tryb planowania (`monthly` \| `by_date`) z jednym edytowalnym parametrem (składka **lub** data docelowa). **Bez** filtrów aktywne/zarchiwizowane i **bez** archiwizacji w UI. Usunięcie kieszeni z historią: wyczyść `pocket_id` i `pocket_movement` na powiązanych transferach, potem usuń wiersz kieszeni. Kieszeń **osobna od kategorii P&L** — bez `type` income/expense. Kwoty na liście i w formularzach wyświetlane **z symbolem waluty kieszeni**.

**Kryteria akceptacji**
1. Given zalogowany When dodanie kieszeni z nazwą, ikoną, kolorem i walutą PLN Then kieszeń na liście `/pockets` z kwotami sformatowanymi z „zł”.
2. Given kieszeń z transferami z `pocket_id` When usunięcie Then 200; transfery mają `pocket_id = null` i `pocket_movement = null`; wiersz kieszeni usunięty.
3. Given dwie kieszenie When zmiana `sort_order` (reorder) Then kolejność na liście i w budżecie miesięcznym zgodna z `sort_order`.
4. Given kieszeń When lista `/pockets` Then brak zakładek filtra archiwum; wszystkie kieszenie widoczne.

**Reguły**
- Izolacja per `user_id`.
- Empty state z CTA przy braku kieszeni.
- **Bez** selektora roku i szacunków rocznych/miesięcznych per rok na `/pockets`.
- `currency_id` ustawiane przy tworzeniu; **nie** edytowalne na update (jak konto).
- Spec implementacji: `.docs/superpowers/specs/2026-06-05-goals-currency-design.md`.

**Zdarzenia:** `pocket_created`, `pocket_updated`, `pocket_deleted`

---

### FR-P2 — Kwota docelowa i planowanie celu

| Pole | Wartość |
|------|---------|
| **Priorytet** | Must |
| **Domena** | Pockets |

**Zachowanie**
Opcjonalna **kwota docelowa** (`target_amount`); `null` = koperta bez limitu (tylko skumulowane saldo, bez paska postępu). Przy ustawionej kwocie — wymagany `planning_mode`: **`monthly`** (użytkownik deklaruje `monthly_contribution`; system pokazuje read-only prognozowaną datę ukończenia na podstawie historii odkładania) **lub** **`by_date`** (użytkownik ustawia `target_date`; system liczy read-only rekomendowaną składkę miesięczną ze skumulowanego salda i pozostałego czasu). Tryby wzajemnie wykluczające — nie zapisuje się obu pól planowania jednocześnie. Stan **Ukończony** — obliczany, gdy `balance >= target_amount` (cel pozostaje aktywny; saldo może przekroczyć 100%). Kolumna **Plan** w budżecie miesięcznym (FR-C5) czyta te pola — bez edycji planu celu na ekranie budżetu.

**Kryteria akceptacji**
1. Given cel bez `target_amount` When zapis Then brak `planning_mode` i pól planowania.
2. Given `target_amount` i `planning_mode = monthly` When brak `monthly_contribution` Then 422.
3. Given `planning_mode = monthly` When jednocześnie `target_date` Then 422.
4. Given `planning_mode = by_date` i saldo rosnące When odczyt celu Then rekomendowana składka maleje (pozostała kwota / miesiące do terminu).
5. Given `planning_mode = monthly` i wolniejsze odkładanie When odczyt Then prognozowana data ukończenia przesuwa się; deklarowana składka bez zmian.
6. Given `balance >= target_amount` When lista celów Then badge Ukończony; transfery nadal dozwolone.

**Reguły**
- Kwoty ≥ 0; skala decimal jak w reszcie aplikacji.
- Saldo skumulowane: suma `pocket_movement = deposit` minus `pocket_movement = withdrawal` na transferach z `pocket_id` w walucie kieszeni (`transactions.currency_id = pocket.currency_id`), każda para liczona raz per `transfer_id`, plus `initial_balance`.
- Formuły projekcji — spec `2026-06-04-goals-target-model-design.md` (§ Metrics).

**Zdarzenia:** (brak osobnych zdarzeń zapisu planu — pola w `pocket_created` / `pocket_updated`)

---

### FR-P3 — Przypisanie kieszeni do transferu

| Pole | Wartość |
|------|---------|
| **Priorytet** | Must |
| **Domena** | Transfers |

**Zachowanie**
Transfer wewnętrzny może opcjonalnie mieć `pocket_id` należący do użytkownika na **obu** nogach (ta sama wartość) wraz z wymaganym `pocket_movement` (`deposit` \| `withdrawal`). Dozwolone na dowolnym transferze (ROR↔ROR, ROR↔Savings, Cash↔ROR). Waluta kieszeni musi być **zgodna** z walutą transferu. Obie nogi transferu mają `category_id = null`; śledzenie oszczędności odbywa się wyłącznie przez `pocket_id` + `pocket_movement`.

**Kryteria akceptacji**
1. Given transfer bez `pocket_id` When zapis Then 201; `pocket_movement = null`.
2. Given transfer z `pocket_id` When brak `pocket_movement` Then 422.
3. Given transfer ROR↔ROR z `pocket_id` i `pocket_movement = deposit` When zapis Then 201; saldo kieszeni +kwota (raz per para).
4. Given `pocket_id` innego użytkownika When zapis Then 422.
5. Given kieszeń w PLN i transfer w PLN When `pocket_id` + movement ustawione Then 201.
6. Given kieszeń w walucie X ≠ waluta transferu When zapis Then 422.
7. Given edycja powiązanego transferu When zmiana kieszeni/ruchu Then obie nogi zaktualizowane.

**Reguły**
- Formularz: opcjonalna kieszeń + ruch; smart default w UI przy Savings (nie wymuszane przez API).
- Matcher importu (FR-I6): po połączeniu transferu kieszenie pozostają null (ręczne przypisanie po imporcie).

**Zdarzenia:** `pocket_assigned_transfer`

---

### FR-P5 — Widok kieszeni w budżecie miesięcznym

| Pole | Wartość |
|------|---------|
| **Priorytet** | Must |
| **Domena** | Budgets |

**Zachowanie**
Widok budżetu miesięcznego (FR-C5) zawiera sekcję **Kieszenie**: jeden wiersz per kieszeń użytkownika (wszystkie kieszenie, bez filtra archiwum) z kolumnami **Plan** (tylko do odczytu, z FR-P2), **odłożono** i **wypłacono** (z `pocket_movement` = deposit/withdrawal, liczone raz per `transfer_id` w miesiącu). Zastępuje pojedynczą sekcję agregatu „Transfery i oszczędności” z wave 2. Link „Zarządzaj kieszeniami” → `/pockets`.

**Kryteria akceptacji**
1. Given dwie kieszenie z transferami w miesiącu When widok miesięczny Then osobne wiersze z poprawnymi sumami odłożono/wypłacono per kieszeń.
2. Given brak kieszeni When widok miesięczny Then sekcja kieszeni ukryta z CTA do utworzenia kieszeni.
3. Given transfer ROR↔ROR z `pocket_movement = deposit` When sekcja kieszeni Then odłożono kwotę transferu (raz per para).

**Reguły**
- Definicje metryk — jak w FR-C5: plan z pól kieszeni; odłożono/wypłacono z `pocket_movement`; **bez** metryki powiązanych wydatków (`linked_expenses`).
- Planowanie oszczędności — wyłącznie przez kieszenie (FR-P2); kategorie P&L nie obejmują transferów wewnętrznych.

**Zdarzenia:** `budget_view_monthly`

---

### 6.10 UX budżetu i kategorii

### FR-UX1 — Plany P&L tylko na budżecie

| Pole | Wartość |
|------|---------|
| **Priorytet** | Must |
| **Domena** | Budgets |

**Zachowanie**
Ekran **Kategorie** (`/categories`) to wyłącznie katalog etykiet P&L: CRUD, kolejność — **bez** selektora roku, pól szacunków rocznych ani miesięcznych. Edycja planów P&L: na widoku budżetu rocznego — **szacunek roczny** oraz opcjonalna **jednolita kwota miesięczna** (bulk od bieżącego miesiąca do grudnia, bez nadpisywania istniejących override’ów); na widoku budżetu miesięcznego — **nadpisania miesięczne** per miesiąc (FR-C4). Link „Zarządzaj kategoriami” z budżetu → `/categories`. Kategorie **nie** przenoszą się do Ustawień.

**Kryteria akceptacji**
1. Given ekran kategorii When render Then brak pól kwot / selektora roku.
2. Given widok budżetu rocznego When edycja szacunku kategorii Then zapis i odświeżenie planu.
3. Given widok budżetu miesięcznego When nadpisanie planu kategorii Then zapis zgodnie z FR-C4.

**Reguły**
- API szacunków kategorii bez zmian semantyki; zmiana tylko miejsca edycji w UI.
- Sidebar: Konta · Transakcje · Budżet · Kategorie (§7).

**Zdarzenia:** `category_estimate_annual_saved`, `category_estimate_monthly_saved`, `category_estimate_yearly_plan_saved` (kontekst: budget screen)

---

### 6.11 Szablon: dodawanie FR-XX

```markdown
### FR-XX — Tytuł

| Pole | Wartość |
|------|---------|
| **Priorytet** | Must \| Should \| Could |
| **Domena** | Auth \| Accounts \| Transactions \| Balances \| Import \| Categories \| Budgets \| Pockets |

**Zachowanie**
…

**Kryteria akceptacji**
1. Given … When … Then …

**Reguły i przypadki brzegowe**
- …

**Zdarzenia** (opcjonalnie)
- `event_name`
```

Po dodaniu: uzupełnij **Indeks identyfikatorów FR** i tabelę zdarzeń w §3.4 (jeśli dotyczy).

---

## 7. UX, IA i copy

**Język UI:** polski. Architektura pod przyszłe i18n (formatowanie dat/kwot), bez pełnego i18n w MVP.

**Ekrany:** Auth (login, rejestracja, reset) · Konta (zakładki **Rachunki** / **Portfele** / **Lokaty**; dodaj/edytuj konto w modalu; lokaty — lista + modals otwarcia/zamknięcia/edycji, FR-D1) · Transakcje (lista; dodaj/edytuj P&L i dodaj transfer w modalach **bez** pola kieszeni na P&L, **transfer bez kategorii** z opcjonalną kieszenią i ruchem FR-P3, baner kandydatów FR-I6; **kategoria** wymagana w formularzach P&L od wave 2) · Import (modal z transakcji: konto, upload, status, podsumowanie) · **Budżet** (widok miesięczny z sekcją kieszeni + edycja planów P&L; widok roczny z edycją szacunków rocznych P&L) · **Kategorie** (katalog: zakładka **Przychody i wydatki** — lista + dodaj/edytuj w modalu, kolejność, **bez** szacunków, FR-UX1; zakładka **Kieszenie** — katalog kopert: lista + dodaj/edytuj w modalu, reorder, ikona/kolor i planowanie — **bez** archiwizacji i filtrów).

**Nawigacja (sidebar):** Konta · Transakcje (Import jako akcja/modal, baner kandydatów z licznikiem) · **Budżet** (przełącznik miesięczny / roczny) · **Kategorie** (jeden link; aktywny na `categories.*` i `pockets.*`; zakładki **Przychody i wydatki** / **Kieszenie**). Ustawienia (`/settings/*`) — profil, hasło, wygląd; **kategorie nie przenoszą się do Ustawień**.

**Formaty:** data **DD-MM-YYYY**; kwota w UI — PL (przecinek), tolerancja kropki; import — patrz FR-I1.

**Stany:** empty (brak kont / transakcji / kandydatów); import — loader/skeleton + licznik realtime (Reverb), fallback polling 5 s.

**A11y:** klawiatura, focus, kontrast WCAG AA, etykiety/aria.

**Copy importu (wzór):** „Zaimportowano X, pominięto duplikaty Y, błędne wiersze Z, możliwe transfery do potwierdzenia: K”.

---

## 8. Wymagania niefunkcjonalne

**Wydajność**
- Lista: paginacja backend; indeksy złożone na `(account_id, booked_at)`, `(user_id, booked_at)`; po wave 2: `(category_id, booked_at)` lub `(user_id, category_id, booked_at)` pod agregacje budżetu.
- Import: chunk domyślnie 500 wierszy, krótkie transakcje DB, bulk insert; `lockForUpdate` konta tylko przy finalnej aktualizacji salda.
- Realtime import: broadcast statusu + liczników per chunk; polling 5 s przy rozłączeniu WebSocket.

**Bezpieczeństwo**
- OWASP baseline (CSRF, XSS, auth hardening).
- Rate limit: logowanie/reset 6/min per IP; import upload/commit 10/min per user; API 60/min per user.
- Autoryzacja per zasób (Policy); pamięć opisów i kategorii izolowana per user (+ bank).
- Produkcyjne logi: bez surowych plików importu i pełnych wierszy.
- Mass assignment: `$fillable`; `Model::shouldBeStrict()` poza produkcją.

**Obserwowalność**
- Telemetria → kanał `telemetry` (§3.4).
- Audyt: `adjustment` + `account_balance_adjustments`; soft delete kont.

**Importer**
- Encoding i parsery dat/kwot — zgodnie z FR-I1 (§6.5).

**Retencja plików**
- `committed`: plik usunięty po commit.
- `failed`: `storage/app/imports/{user}/{import}/source-failed.{ext}` — 30 dni; cron `imports:purge-old-files`.

---

## 9. Ograniczenia techniczne

- **Backend:** Laravel 13, Inertia v2, auth sesyjny.
- **Frontend:** Vue 3 + TypeScript, Vite, Tailwind 3.
- **Infra:** MySQL, Redis, Typesense, Reverb, Horizon — szczegóły, wersje, Sail, komendy testów → **`.docs/tech-stack.md`**.
- **Import per bank:** enum `Bank`; resolver adaptera po `Account.bank`; nowy bank = enum + adapter + ikona; `Cash` — import z pliku niedozwolony (422).

---

## 10. Granice release

### MVP (wave 1 — wdrożone)

Auth · Konta + saldo + `adjustment` + rekalkulacja · Transakcje (`date`, `booked_at`, lista, summary) · Transfer UI · Import (auto-map, auto-commit, dedupe, chunked, realtime) · Matcher + baner (FR-I6) · Pamięć opisów best-effort (FR-I5) · Telemetria · Rate limiting importu.

### Wave 2 (pre-release — kategorie i szacunki)

Kategorie (FR-C1, FR-C2) · Szacunki roczne i miesięczne P&L (FR-C3, FR-C4) · Widoki budżetu miesięczny i roczny (FR-C5, FR-C6) · Pamięć kategorii przy imporcie (FR-C7) · Opcjonalnie filtr kategorii na liście (FR-C8).

**Rozszerzenie wave 2 UX (kieszenie + IA budżetu):** Kieszenie (FR-P1, FR-P2 — model koperty z kwotą docelową, spec `2026-06-04-goals-target-model-design.md`; waluta kieszeni, spec `2026-06-05-goals-currency-design.md`) · Opcjonalna kieszeń i ruch na transferze (FR-P3; spec `2026-08-26-transaction-scope-pocket-catalog-design.md`) · Sekcja kieszeni w budżecie miesięcznym (FR-P5) · Plany P&L tylko na budżecie, kategorie bez szacunków (FR-UX1) · Transfer bez kategorii P&L, usunięcie kategorii „Oszczędności” (spec 2026-06-04).

### Po wave 2 (kierunek, bez zobowiązania)

AI kategorii, mapowanie kategorii z banku, wykresy, eksport, roczne podsumowanie celów, twarde limity z alertami, multiwaluta, współdzielenie, integracje bankowe, aplikacje mobilne.

---

## 11. Ryzyka i mitygacje

| # | Ryzyko | Mitygacja |
|---|--------|-----------|
| 1 | Różne formaty CSV/XLSX | Adaptery + testy na realnych plikach (FR-I4) |
| 2 | False positive dedupe | Akceptowana wada MVP; telemetria; ręczny wpis | FR-I3 |
| 3 | Dryf salda | Transakcje DB + `accounts:recalculate-balance` | FR-S1 |
| 4 | Wyciek danych | Policies + testy izolacji | §3.3 |
| 5 | Zaokrąglenia decimal | Skala 2, `bc*` | — |
| 6 | Jakość `subject` | Kolumna wyciągu + pamięć + fallback | FR-I5 |
| 7 | Fałszywe linki transferów | Tokeny + 1 kandydat auto; reszta manual | FR-I6 |
| 8 | Długie locki importu | Chunki, krótkie TX | FR-I1 |
| 9 | Błędy 500 zamiast produktowych | 422 + `message_key` (np. Cash) | FR-I4 |
| 10 | Złe kategorie po imporcie | Pamięć + edycja; fallback pierwsza wg typu | FR-C7 |
| 11 | Rozjazd sumy miesięcznych vs rocznej | Miękka informacja, bez blokady | FR-C4 |
| 12 | Brak ruchu przy kieszeni na transferze | Walidacja 422 (FR-P3); pole ruchu wymagane gdy kieszeń ustawiona | FR-P3 |

---

## 12. Zależności i otwarte pytania

**Zależności**
- Przykładowe pliki CSV/XLSX per bank (dostawca produktu).
- Ikony banków MVP: BNP Paribas, mBank, Gotówka.
- Mail: Mailpit (dev), SMTP (prod).

**Otwarte pytania (nieblokujące MVP)**
- Feature flags dla importu/transferu przy rollout?
- Kanał feedbacku użytkownika (np. email)?
- Treść ekranu pomocy importu (eksport z banku + wybór konta, bez mapowania kolumn)?
- Ustawienia profilu użytkownika (post-MVP)?

---

## Indeks identyfikatorów FR

| ID | Tytuł | Priorytet | Sekcja |
|----|-------|-----------|--------|
| FR-A1 | Rejestracja i logowanie | Must | §6.1 |
| FR-A2 | Reset hasła | Should | §6.1 |
| FR-K1 | CRUD kont | Must | §6.2 |
| FR-K2 | Usunięcie konta | Must | §6.2 |
| FR-T1 | CRUD transakcji | Must | §6.3 |
| FR-T2 | Lista, filtry, podsumowanie | Must | §6.3 |
| FR-T3 | Transfer między kontami | Must | §6.3 |
| FR-S1 | Saldo i korekta | Must | §6.4 |
| FR-I1 | Import auto-commit | Must | §6.5 |
| FR-I2 | Typ ze znaku kwoty | Must | §6.5 |
| FR-I3 | Deduplikacja | Must | §6.5 |
| FR-I4 | Adaptery banków | Must | §6.5 |
| FR-I5 | Pamięć opisów | Should | §6.5 |
| FR-I6 | Matcher transferów | Should | §6.5 |
| FR-C1 | CRUD kategorii + zestaw startowy | Must | §6.6 |
| FR-C2 | Kategoria wymagana na transakcji | Must | §6.6 |
| FR-C3 | Szacunki roczne | Must | §6.7 |
| FR-C4 | Szacunki miesięczne | Must | §6.7 |
| FR-C5 | Widok budżetu miesięczny | Must | §6.7 |
| FR-C6 | Widok budżetu roczny | Must | §6.7 |
| FR-C7 | Pamięć kategorii przy imporcie | Must | §6.7 |
| FR-C8 | Kategoria na liście transakcji | Should | §6.7 |
| FR-P1 | CRUD kieszeni | Must | §6.9 |
| FR-P2 | Kwota docelowa i planowanie kieszeni | Must | §6.9 |
| FR-P3 | Przypisanie kieszeni do transferu | Must | §6.9 |
| FR-P5 | Widok kieszeni w budżecie miesięcznym | Must | §6.9 |
| FR-D1 | Lokaty (term deposits) | Should | §6.8 |
| FR-D2 | Import lokat (BNP/mBank) | Should | §6.8 |
| FR-UX1 | Plany P&L tylko na budżecie | Must | §6.10 |
