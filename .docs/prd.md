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
- **Lista transakcji:** filtry, sort i podsumowanie po dacie okresu (`booked_at`; UI kolumny: `COALESCE(booked_at, date)`); sumy wpływów/wydatków **bez** wewnętrznych transferów (`transfer_id` pusty).
- **Kategorie i szacunki (wave 2):** każda transakcja ma `category_id`; szacunki roczne (kanoniczne) + opcjonalne nadpisania miesięczne; widok miesięczny (plan vs fakty P&L + sekcja celów) i roczny (plan vs fakty bez transferów) — §3.1a, FR-C1–C7, FR-G*, FR-UX1.
- **Cele oszczędnościowe (wave 2 UX):** osobna encja od kategorii P&L; śledzenie przez transfery na kontach `Savings` (flow A) + opcjonalne powiązanie wydatku — §3.1a, FR-G1–G5.
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
- **Kategoria:** wpis w katalogu użytkownika (`income` \| `expense`) z kolejnością wyświetlania; przypisana do każdej transakcji.
- **Szacunek roczny:** planowana kwota na rok kalendarzowy per kategoria (przychód lub wydatek); można przekroczyć — to nie jest twardy limit.
- **Szacunek miesięczny:** opcjonalne nadpisanie planu na dany miesiąc; może różnić się od `szacunek_roczny ÷ 12`.
- **Cel (goal):** koperta oszczędnościowa użytkownika (np. „Wakacje”), **osobna od kategorii P&L**; planowane kwoty (szacunki roczne/miesięczne) i wykonanie śledzone przez transfery na kontach `Savings` (flow A: odkładanie → wypłata na ROR → wydatek na ROR z opcjonalnym powiązaniem celu).
- **Widok budżetu miesięczny:** plan vs wykonanie per kategoria P&L w miesiącu + sekcja **Cele** (plan / odłożono / wypłacono / saldo per cel).
- **Widok budżetu roczny:** szacunek roczny vs wykonanie per kategoria P&L w roku; bez agregacji transferów wewnętrznych; edycja planów P&L na tym ekranie (FR-UX1).

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

1. CRUD celów oszczędnościowych (FR-G1)
2. Szacunki roczne i miesięczne per cel (FR-G2)
3. Wymagany cel na transferze z udziałem konta `Savings` (FR-G3)
4. Widok celów w budżecie miesięcznym zamiast agregatu transferów (FR-G5)
5. Plany P&L tylko na ekranach budżetu — kategorie bez pól szacunków (FR-UX1)

**Should w rozszerzeniu wave 2 UX:** opcjonalny cel na wydatku/przychodzie (FR-G4).

### 3.2 Poza zakresem (Out of scope)

- AI do sugerowania kategorii (automatyczna klasyfikacja ML)
- Mapowanie kategorii z kolumny wyciągu bankowego (np. mBank `Kategoria`) — kategoria z pamięci lub fallback (FR-C7)
- Wielowalutowość i przeliczenia (MVP: PLN w UI; pole waluty w danych)
- Współdzielenie danych między użytkownikami
- Import inny niż CSV/XLSX (PDF, MT940, OCR)
- Załączniki, eksport danych
- Wykresy i zaawansowane raporty (poza tabelarycznymi widokami budżetu FR-C5/C6)
- Twarde limity budżetu z blokadą transakcji / alertami push
- Szacunki oszczędności per konto (zamiast celów + transferów z `goal_id`)
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
| `goal_created`, `goal_updated`, `goal_deleted` | Cele | FR-G1 |
| `goal_estimate_annual_saved`, `goal_estimate_monthly_saved` | Szacunki celów | FR-G2 |
| `goal_assigned_transfer`, `goal_assigned_transaction` | Przypisanie celu | FR-G3, FR-G4 |
| `category_memory_hit`, `category_memory_miss` | Pamięć kategorii (import) | FR-C7 |

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

**E — Budżet, kategorie i cele:** Rejestracja (kategorie startowe) → utwórz cel oszczędnościowy (np. Wakacje) → ustaw plany P&L na widoku budżetu rocznym → widok miesięczny (plan vs fakty P&L + sekcja celów: odłożono / wypłacono / saldo) → transfer ROR→Savings z przypisanym celem → korekta kategorii na transakcjach / import → widok roczny (podsumowanie roku P&L bez transferów). *Alt:* brak szacunku — tylko kolumna wykonania; przekroczenie planu — dozwolone, bez blokady.

**F — Flow A (cel oszczędnościowy):** Cel „Wakacje”, plan 200 PLN/mies. → co miesiąc transfer ROR→Savings z celem → przed wyjazdem transfer Savings→ROR z tym samym celem → wydatki na ROR (kategoria np. Rozrywka, opcjonalnie ten sam cel).

---

## 5. Model domeny

**Relacje:** User 1—N Accounts, Transactions, Imports, Categories, Goals · Account 1—N Transactions · Category 1—N Transactions · Goal 1—N Transactions (opcjonalnie) · Transfer = 2 Transactions, ten sam `transfer_id` · Category 1—N CategoryAnnualEstimate, CategoryMonthlyEstimate · Goal 1—N GoalAnnualEstimate, GoalMonthlyEstimate.

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
| `category_id` | FK → Category (wymagane po wave 2) |
| `goal_id` | FK → Goal (nullable); wymagane na obu nogach transferu, gdy uczestniczy konto `Savings`; opcjonalne na przychodzie/wydatku/korekcie |

### Category

| Pole | Opis |
|------|------|
| `user_id` | Właściciel |
| `name` | Nazwa wyświetlana |
| `type` | `income`, `expense` |
| `sort_order` | Kolejność listy; fallback importu = pierwsza kategoria danego typu |
| `is_system` | Kategoria systemowa (np. „Oszczędności”) — nieusuwalna w v1 |

### CategoryAnnualEstimate

| Pole | Opis |
|------|------|
| `category_id`, `year` | Rok kalendarzowy; unikalna para |
| `amount` | Szacunek roczny (nullable = brak planu) |

### CategoryMonthlyEstimate

| Pole | Opis |
|------|------|
| `category_id`, `year`, `month` | Miesiąc 1–12; unikalna trójka |
| `amount` | Nadpisanie szacunku miesięcznego (nullable = brak zapisanego nadpisania; UI może pokazać `roczny ÷ 12`) |

### Goal

| Pole | Opis |
|------|------|
| `user_id` | Właściciel |
| `name` | Nazwa wyświetlana (np. „Wakacje”) |
| `sort_order` | Kolejność listy |
| `is_archived` | Opcjonalne ukrycie (v1: można odłożyć; usunięcie zablokowane przy powiązanych transakcjach) |

Brak pola `type` — cel nie jest przychodem ani wydatkiem P&L.

### GoalAnnualEstimate

| Pole | Opis |
|------|------|
| `goal_id`, `year` | Rok kalendarzowy; unikalna para |
| `amount` | Szacunek roczny (nullable = brak planu) |

### GoalMonthlyEstimate

| Pole | Opis |
|------|------|
| `goal_id`, `year`, `month` | Miesiąc 1–12; unikalna trójka |
| `amount` | Nadpisanie szacunku miesięcznego (nullable = brak zapisanego nadpisania; UI może pokazać `roczny ÷ 12`) |

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
Dodawanie, edycja, usuwanie na aktywnym koncie; wpływ na `current_balance`. Od wave 2: wymagane pole `category_id` (FR-C2). Od rozszerzenia wave 2 UX: opcjonalne `goal_id` na przychodzie/wydatku (FR-G4).

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
Jedna akcja UI → 2 transakcje, wspólna data, przeciwne kwoty, wspólny `transfer_id`. Od wave 2: jedna wybrana kategoria przypisana do **obu** nóg (FR-C2). Od rozszerzenia wave 2 UX: gdy uczestniczy konto `Savings`, wymagany ten sam `goal_id` na obu nogach (FR-G3); transfer ROR↔ROR bez `goal_id`.

**Kryteria akceptacji**
1. Given dwa różne konta When transfer kwoty X w dacie D Then na źródle `-X`, na celu `+X`, salda obu kont zaktualizowane.
2. Given wave 2 When transfer Then obie nogi mają ten sam `category_id`.
3. Given transfer z kontem `Savings` When brak `goal_id` Then 422.
4. Given transfer ROR↔ROR When `goal_id` ustawione Then 422.
5. Given transfer z `Savings` When zapis Then obie nogi mają ten sam `goal_id`.

**Reguły**
- To samo konto jako źródło i cel — zabronione.
- Usunięte konto — blokada.
- Formularz: kwota dodatnia; system zapisuje znaki na transakcjach.
- Domyślna kategoria w formularzu transferu: „Oszczędności” (rekomendacja UX).
- Pole celu: widoczne/wymagane, gdy źródło lub cel ma `type = Savings`; ukryte/wyłączone dla ROR↔ROR.

**Zdarzenia:** `transfer_created`, `transfer_failed_validation`, `goal_assigned_transfer`

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
5. Given połączony transfer When „Rozłącz transfer” Then brak `transfer_id`, `type` ze znaku `amount`, `rejected`.

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
Użytkownik zarządza katalogiem kategorii P&L (etykiety transakcji). Przy pierwszym użyciu (rejestracja lub pierwsze wejście) system tworzy **zestaw startowy** (wydatki, przychody, kategoria systemowa **Oszczędności**). Użytkownik dodaje, zmienia nazwę, ustawia kolejność (`sort_order`). Usunięcie kategorii z przypisanymi transakcjami — zablokowane (v1). Zmiana `type` kategorii — zablokowana, gdy istnieją transakcje. **Ekran kategorii nie zawiera pól szacunków** — plany P&L edytuje się wyłącznie na widokach budżetu (FR-UX1).

**Kryteria akceptacji**
1. Given nowy użytkownik When pierwszy dostęp do kategorii/budżetu Then istnieje zestaw startowy.
2. Given kategoria z transakcjami When usunięcie Then błąd walidacji, kategoria pozostaje.
3. Given kategoria systemowa „Oszczędności” When usunięcie Then blokada.

**Reguły**
- Izolacja per `user_id`.
- Zestaw startowy (przykład): wydatki — Jedzenie, Transport, Mieszkanie, Zdrowie, Rozrywka, Oszczędności (system), Inne; przychody — Pensja, Inne przychody.
- `sort_order` decyduje o „pierwszej możliwej” kategorii przy imporcie (FR-C7).

**Zdarzenia:** `category_created`, `category_updated`

---

### FR-C2 — Kategoria wymagana na transakcji

| Pole | Wartość |
|------|---------|
| **Priorytet** | Must |
| **Domena** | Transactions |

**Zachowanie**
Ręczne dodanie/edycja, transfer i wiersz importu wymagają `category_id` należącego do użytkownika. Typ kategorii musi pasować do typu ekonomicznego transakcji (`income` / `expense` ze znaku kwoty / `TransactionType`). Transfer: **jedna** kategoria w formularzu → ten sam `category_id` na obu nogach. `adjustment`: kategoria wymagana.

**Kryteria akceptacji**
1. Given tworzenie transakcji When brak kategorii Then 422.
2. Given wydatek When kategoria przychodowa Then 422.
3. Given transfer When zapis Then obie nogi mają ten sam `category_id`.

**Reguły**
- Pre-release: migracja przypisuje kategorię wszystkim istniejącym transakcjom; po release brak trwałego stanu „bez kategorii”.
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
Użytkownik może nadpisać plan na konkretny miesiąc. Gdy brak nadpisania, widok miesięczny pokazuje **`szacunek_roczny ÷ 12`**, jeśli roczny istnieje. Suma 12 nadpisań może różnić się od rocznego — **miękka informacja** (bez blokady zapisu); istotna głównie na początku roku. **Edycja nadpisań:** widok budżetu miesięcznego (FR-UX1), nie ekran kategorii.

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
Dla wybranego miesiąca i roku kalendarzowego: tabela kategorii P&L (sekcje przychody / wydatki) z kolumnami: szacunek miesiąca (edytowalny), wykonanie, różnica. Okres i agregacja fakty: `COALESCE(booked_at, date)` w granicach miesiąca. Fakty kategorii P&L: **bez** wierszy z `transfer_id` (jak podsumowanie FR-T2). **Sekcja „Cele”:** jeden wiersz per cel użytkownika — plan (nadpisanie miesięczne lub `roczny ÷ 12`), **odłożono** (suma nóg transferu z `goal_id` na koncie `Savings`, `amount > 0`), **wypłacono** (suma nóg z `goal_id` na `Savings`, `amount < 0`), **saldo celu** (odłożono − wypłacono w miesiącu); opcjonalnie kolumna powiązanych wydatków (`goal_id` ustawione, `transfer_id` pusty) — informacyjnie. Zastępuje wave-2 sekcję agregatu „Transfery i oszczędności”.

**Kryteria akceptacji**
1. Given wydatek w kategorii Jedzenie w marcu When widok marzec Then wykonanie w Jedzeniu.
2. Given transfer wewnętrzny When tabela kategorii Then transfer nie wliczony do wykonania wydatków kategorii.
3. Given cel Wakacje, plan 200, transfer ROR→Savings 200 z `goal_id` When sekcja celów Then odłożono 200, saldo 200.
4. Given wypłata Savings→ROR 150 z tym samym celem When sekcja celów Then wypłacono 150, saldo 50 (przy braku innych ruchów w miesiącu).

**Reguły**
- `adjustment` w wykonaniu kategorii — wg znaku kwoty (zgodnie z FR-T2).
- Metryki celów **używają** nóg transferu (`transfer_id` ustawione) na kontach `Savings`.
- Miękka wskazówka: suma planów P&L vs roczne (bez celów) — jak FR-C4.

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
2. Given plan oszczędności w kategorii systemowej When widok roczny Then kategoria Oszczędności pokazuje **fakty** P&L (transfery wykluczone); planowanie oszczędności — przez cele (FR-G2), nie przez agregat w budżecie miesięcznym.

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
Lista transakcji wyświetla nazwę kategorii; opcjonalny filtr po `category_id`.

**Kryteria akceptacji**
1. Given lista When wiersz transakcji Then widoczna nazwa kategorii.
2. Given filtr kategorii When zastosowanie Then tylko pasujące wiersze.

---

### 6.9 Cele oszczędnościowe

### FR-G1 — CRUD celów

| Pole | Wartość |
|------|---------|
| **Priorytet** | Must |
| **Domena** | Goals |

**Zachowanie**
Użytkownik tworzy nazwane cele oszczędnościowe (koperty, np. „Wakacje”), zmienia nazwę, ustawia kolejność (`sort_order`). Usunięcie celu z powiązanymi transakcjami — zablokowane (v1). Cel jest **osobny od kategorii P&L** — nie ma pola `type` income/expense.

**Kryteria akceptacji**
1. Given zalogowany When dodanie celu z nazwą Then cel na liście `/goals`.
2. Given cel z transakcjami z `goal_id` When usunięcie Then błąd walidacji, cel pozostaje.
3. Given dwa cele When zmiana `sort_order` Then kolejność na liście i w budżecie miesięcznym zgodna z `sort_order`.

**Reguły**
- Izolacja per `user_id`.
- Empty state z CTA przy braku celów.

**Zdarzenia:** `goal_created`, `goal_updated`, `goal_deleted`

---

### FR-G2 — Szacunki celów (roczne / miesięczne)

| Pole | Wartość |
|------|---------|
| **Priorytet** | Must |
| **Domena** | Goals |

**Zachowanie**
Użytkownik ustawia opcjonalny **szacunek roczny** per cel i rok kalendarzowy oraz opcjonalne **nadpisania miesięczne** (ten sam model co FR-C3/C4 dla kategorii). Przekroczenie planu — dozwolone; UI pokazuje różnicę. Gdy brak nadpisania miesięcznego, plan = `szacunek_roczny ÷ 12`, jeśli roczny istnieje. Suma 12 nadpisań może różnić się od rocznego — miękka informacja (bez blokady zapisu).

**Kryteria akceptacji**
1. Given cel i rok 2026 When zapis szacunku rocznego 2400 Then plan roczny 2400; plan miesięczny domyślnie 200.
2. Given roczny 2400 i nadpisanie marca = 300 When widok marzec Then plan 300 dla celu.
3. Given wykonanie (odłożono) powyżej planu When widok miesięczny Then różnica informacyjna, bez błędu.

**Reguły**
- Kwoty ≥ 0; skala decimal jak w reszcie aplikacji.
- Edycja nadpisań miesięcznych preferowana inline na widoku budżetu miesięcznego (parity z P&L); roczny — na `/goals` lub budżecie rocznym (implementacja).

**Zdarzenia:** `goal_estimate_annual_saved`, `goal_estimate_monthly_saved`

---

### FR-G3 — Przypisanie celu do transferu

| Pole | Wartość |
|------|---------|
| **Priorytet** | Must |
| **Domena** | Transfers |

**Zachowanie**
Transfer, w którym **co najmniej jedna noga** dotyczy konta `type = Savings`, wymaga `goal_id` należącego do użytkownika na **obu** nogach (ta sama wartość). Transfer ROR↔ROR (bez udziału `Savings`) **nie** może mieć `goal_id`. `category_id` pozostaje wymagane niezależnie (domyślnie kategoria systemowa „Oszczędności”); śledzenie oszczędności odbywa się przez `goal_id`, nie przez kategorię.

**Kryteria akceptacji**
1. Given transfer ROR→Savings When brak `goal_id` Then 422.
2. Given transfer Savings→ROR When zapis Then obie nogi mają ten sam `goal_id`.
3. Given transfer ROR→ROR When `goal_id` ustawione Then 422.
4. Given `goal_id` innego użytkownika When zapis Then 422.

**Reguły**
- Formularz: pole celu widoczne/wymagane tylko gdy źródło lub cel ma `type = Savings`.
- Matcher importu (FR-I6): po połączeniu transferu z udziałem `Savings` użytkownik uzupełnia cel ręcznie (auto-przypisanie celu — poza zakresem v1).

**Zdarzenia:** `goal_assigned_transfer`

---

### FR-G4 — Opcjonalny cel na wydatku

| Pole | Wartość |
|------|---------|
| **Priorytet** | Should |
| **Domena** | Transactions |

**Zachowanie**
Przy tworzeniu/edycji przychodu, wydatku (i opcjonalnie korekty) użytkownik może opcjonalnie wybrać cel z listy swoich celów — np. wydatek na ROR po wypłacie z oszczędności powiązany z kopertą „Wakacje”. Kategoria P&L pozostaje wymagana osobno (np. cel „Wakacje”, kategoria „Rozrywka”).

**Kryteria akceptacji**
1. Given wydatek na ROR When zapis bez celu Then sukces (`goal_id` null).
2. Given wydatek When wybór celu użytkownika Then `goal_id` zapisane; widoczne w metryce powiązanych wydatków (FR-C5).
3. Given cel innego użytkownika When zapis Then 422.

**Reguły**
- `goal_id` opcjonalne; brak wymogu na transferach bez `Savings` (FR-G3).

**Zdarzenia:** `goal_assigned_transaction`

---

### FR-G5 — Widok celów w budżecie miesięcznym

| Pole | Wartość |
|------|---------|
| **Priorytet** | Must |
| **Domena** | Budgets |

**Zachowanie**
Widok budżetu miesięcznego (FR-C5) zawiera sekcję **Cele** z metrykami per cel (plan, odłożono, wypłacono, saldo). Zastępuje pojedynczą sekcję agregatu „Transfery i oszczędności” z wave 2. Link „Zarządzaj celami” → `/goals`.

**Kryteria akceptacji**
1. Given dwa cele z transferami w miesiącu When widok miesięczny Then osobne wiersze z poprawnymi sumami per cel.
2. Given brak celów When widok miesięczny Then sekcja celów pusta lub ukryta z CTA do utworzenia celu.

**Reguły**
- Definicje metryk — jak w §5 (Goal) i FR-C5.
- Kategoria systemowa „Oszczędności” nadal służy domyślnemu `category_id` transferu; **planowanie** oszczędności — przez cele, nie przez szacunek tej kategorii w sekcji budżetu.

**Zdarzenia:** `budget_view_monthly`

---

### 6.10 UX budżetu i kategorii

### FR-UX1 — Plany P&L tylko na budżecie

| Pole | Wartość |
|------|---------|
| **Priorytet** | Must |
| **Domena** | Budgets |

**Zachowanie**
Ekran **Kategorie** (`/categories`) to wyłącznie katalog etykiet P&L: CRUD, kolejność — **bez** selektora roku, pól szacunków rocznych ani miesięcznych. Edycja planów P&L: **szacunek roczny** na widoku budżetu rocznego; **nadpisania miesięczne** na widoku budżetu miesięcznego (te same reguły co FR-C3/C4). Link „Zarządzaj kategoriami” z budżetu → `/categories`. Kategorie **nie** przenoszą się do Ustawień.

**Kryteria akceptacji**
1. Given ekran kategorii When render Then brak pól kwot / selektora roku.
2. Given widok budżetu rocznego When edycja szacunku kategorii Then zapis i odświeżenie planu.
3. Given widok budżetu miesięcznego When nadpisanie planu kategorii Then zapis zgodnie z FR-C4.

**Reguły**
- API szacunków kategorii bez zmian semantyki; zmiana tylko miejsca edycji w UI.
- Sidebar: Konta · Transakcje · Budżet · Kategorie · Cele (§7).

**Zdarzenia:** `category_estimate_annual_saved`, `category_estimate_monthly_saved` (kontekst: budget screen)

---

### 6.11 Szablon: dodawanie FR-XX

```markdown
### FR-XX — Tytuł

| Pole | Wartość |
|------|---------|
| **Priorytet** | Must \| Should \| Could |
| **Domena** | Auth \| Accounts \| Transactions \| Balances \| Import \| Categories \| Budgets \| Goals |

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

**Ekrany:** Auth (login, rejestracja, reset) · Konta (lista, dodaj, edytuj) · Transakcje (lista, dodaj, edytuj, transfer z opcjonalnym/wymaganym celem FR-G3/G4, baner kandydatów FR-I6; **kategoria** wymagana w formularzach od wave 2) · Import (modal z transakcji: konto, upload, status, podsumowanie) · **Budżet** (widok miesięczny z sekcją celów + edycja planów P&L; widok roczny z edycją szacunków rocznych P&L) · **Kategorie** (CRUD, kolejność — **bez** szacunków, FR-UX1) · **Cele** (CRUD celów, szacunki roczne, kolejność).

**Nawigacja (sidebar):** Konta · Transakcje (Import jako akcja/modal, baner kandydatów z licznikiem) · **Budżet** (przełącznik miesięczny / roczny) · **Kategorie** (katalog P&L) · **Cele** (koperty oszczędnościowe). Ustawienia (`/settings/*`) — profil, hasło, wygląd; **kategorie nie przenoszą się do Ustawień**.

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

**Rozszerzenie wave 2 UX (cele + IA budżetu):** Cele (FR-G1, FR-G2) · Cel wymagany na transferze z `Savings` (FR-G3) · Sekcja celów w budżecie miesięcznym (FR-G5) · Plany P&L tylko na budżecie, kategorie bez szacunków (FR-UX1) · Opcjonalnie cel na wydatku (FR-G4).

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
| 11 | Rozjazd sumy miesięcznych vs rocznej | Miękka informacja, bez blokady | FR-C4, FR-G2 |
| 12 | Brak celu na transferze oszczędnościowym | Walidacja 422 (FR-G3); pole wymagane w UI | FR-G3 |

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
| FR-G1 | CRUD celów | Must | §6.9 |
| FR-G2 | Szacunki celów (roczne / miesięczne) | Must | §6.9 |
| FR-G3 | Przypisanie celu do transferu | Must | §6.9 |
| FR-G4 | Opcjonalny cel na wydatku | Should | §6.9 |
| FR-G5 | Widok celów w budżecie miesięcznym | Must | §6.9 |
| FR-UX1 | Plany P&L tylko na budżecie | Must | §6.10 |
