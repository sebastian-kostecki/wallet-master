# Wallet Master — wymagania produktowe (PRD)

> Jedyny kanoniczny dokument wymagań produktowych.
> Stack technologiczny i komendy deweloperskie: `.docs/tech-stack.md`

## 0. Dla implementujących (AI i zespół)

- **Źródło prawdy:** ten plik = *co* budujemy; `.docs/tech-stack.md` = *jak* uruchamiamy projekt i jaki stack.
- **MVP:** implementuj wymagania z **Priorytet: Must**, chyba że zadanie wyraźnie obejmuje **Should**.
- **Izolacja danych:** konta, transakcje, importy — wyłącznie w scope `user_id` zalogowanego użytkownika.
- **Konto usunięte (soft delete):** transakcje pozostają w historii, **read-only**; import i transfer na takie konto — zablokowane.
- **Import:** konto → upload CSV/XLSX → auto-mapowanie adaptera banku → auto-commit **bez podglądu**; `draft` to stan techniczny między uploadem a kolejką.
- **Dedupe (import):** klucz `date + amount + normalized_description` na koncie; pominięcie duplikatu przy imporcie; ręczne dodanie identycznej transakcji — dozwolone (`dedupe_hash` z sufiksem UUID).
- **Saldo:** `current_balance` aktualizowane przy zmianach transakcji; komenda `accounts:recalculate-balance` jako safety net.
- **Lista transakcji:** filtry, sort i podsumowanie po dacie okresu (`booked_at`; UI kolumny: `COALESCE(booked_at, date)`); sumy wpływów/wydatków **bez** wewnętrznych transferów (`transfer_id` pusty).
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

---

## 2. Streszczenie produktu

Webowa aplikacja do budżetu domowego: konta, transakcje, import wyciągów CSV/XLSX. MVP optymalizuje **time-to-value** — szybkie ręczne dodawanie transakcji i wysoki sukces importu przy minimalnej korekcie.

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

### 3.2 Poza zakresem (Out of scope)

- Kategoryzacja transakcji i AI kategorii
- Wielowalutowość i przeliczenia (MVP: PLN w UI; pole waluty w danych)
- Współdzielenie danych między użytkownikami
- Import inny niż CSV/XLSX (PDF, MT940, OCR)
- Załączniki, eksport, raporty, wykresy
- Budżetowanie i szacowania
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

---

## 5. Model domeny

**Relacje:** User 1—N Accounts, Transactions, Imports · Account 1—N Transactions · Transfer = 2 Transactions, ten sam `transfer_id`.

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
Dodawanie, edycja, usuwanie na aktywnym koncie; wpływ na `current_balance`.

**Kryteria akceptacji**
1. Given aktywne konto When dodanie z `date`, opcjonalnie `booked_at` (default = `date`), kwota, opis, opcjonalnie `subject` Then wpis na liście, saldo zaktualizowane (ujemna zmniejsza, dodatnia zwiększa).
2. Given istniejąca transakcja When edycja pól Then zapis i korekta salda; zmiana samego `booked_at` **nie** zmienia salda bieżącego.

**Reguły**
- Konto usunięte: brak edycji/usuwania.
- Kwota = 0: niedozwolona (FormRequest, Akcja, Importer).
- Data w przyszłości: dozwolona.
- `booked_at` dowolny względem `date`; default `booked_at = date`.
- Ręczny duplikat (ta sama data, kwota, opis): **dozwolony** — `dedupe_hash` z sufiksem UUID.

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
Jedna akcja UI → 2 transakcje, wspólna data, przeciwne kwoty, wspólny `transfer_id`.

**Kryteria akceptacji**
1. Given dwa różne konta When transfer kwoty X w dacie D Then na źródle `-X`, na celu `+X`, salda obu kont zaktualizowane.

**Reguły**
- To samo konto jako źródło i cel — zabronione.
- Usunięte konto — blokada.
- Formularz: kwota dodatnia; system zapisuje znaki na transakcjach.

**Zdarzenia:** `transfer_created`, `transfer_failed_validation`

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
Po edycji `subject`/`description` na transakcji z importu system zapamiętuje mapowanie z `raw_statement_description` i stosuje przy kolejnym imporcie (best-effort, wyszukiwarka opisów w infrastrukturze).

**Kryteria akceptacji**
1. Given edycja zaimportowanej transakcji When zapis Then pamięć per user + bank.
2. Given kolejny import z tym samym (znormalizowanym) surowym opisem When commit Then uzupełnione `subject`/`description` jeśli jest dopasowanie.

**Reguły**
- Brak dopasowania: puste `subject`, `description` z surowego opisu.
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

### 6.6 Szablon: dodawanie FR-XX

```markdown
### FR-XX — Tytuł

| Pole | Wartość |
|------|---------|
| **Priorytet** | Must \| Should \| Could |
| **Domena** | Auth \| Accounts \| Transactions \| Balances \| Import |

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

**Ekrany:** Auth (login, rejestracja, reset) · Konta (lista, dodaj, edytuj) · Transakcje (lista, dodaj, edytuj, transfer, baner kandydatów FR-I6) · Import (modal z transakcji: konto, upload, status, podsumowanie).

**Nawigacja:** Konta · Transakcje (Import jako akcja/modal, baner kandydatów z licznikiem).

**Formaty:** data **DD-MM-YYYY**; kwota w UI — PL (przecinek), tolerancja kropki; import — patrz FR-I1.

**Stany:** empty (brak kont / transakcji / kandydatów); import — loader/skeleton + licznik realtime (Reverb), fallback polling 5 s.

**A11y:** klawiatura, focus, kontrast WCAG AA, etykiety/aria.

**Copy importu (wzór):** „Zaimportowano X, pominięto duplikaty Y, błędne wiersze Z, możliwe transfery do potwierdzenia: K”.

---

## 8. Wymagania niefunkcjonalne

**Wydajność**
- Lista: paginacja backend; indeksy złożone na `(account_id, booked_at)`, `(user_id, booked_at)`.
- Import: chunk domyślnie 500 wierszy, krótkie transakcje DB, bulk insert; `lockForUpdate` konta tylko przy finalnej aktualizacji salda.
- Realtime import: broadcast statusu + liczników per chunk; polling 5 s przy rozłączeniu WebSocket.

**Bezpieczeństwo**
- OWASP baseline (CSRF, XSS, auth hardening).
- Rate limit: logowanie/reset 6/min per IP; import upload/commit 10/min per user; API 60/min per user.
- Autoryzacja per zasób (Policy); pamięć opisów izolowana per user (+ bank).
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

### MVP (obecny zakres)

Auth · Konta + saldo + `adjustment` + rekalkulacja · Transakcje (`date`, `booked_at`, lista, summary) · Transfer UI · Import (auto-map, auto-commit, dedupe, chunked, realtime) · Matcher + baner (FR-I6) · Pamięć opisów best-effort (FR-I5) · Telemetria · Rate limiting importu.

### Po MVP (kierunek, bez zobowiązania)

Kategorie, raporty, eksport, multiwaluta, współdzielenie, integracje bankowe, aplikacje mobilne.

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
