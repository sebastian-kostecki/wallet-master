# Plan refaktoryzacji — Wariant A (ewolucja)

Dokument operacyjny dla agentów AI i deweloperów. Opisuje **docelową strukturę** backendu Wallet Master bez przejścia na moduły pionowe (Wariant B) ani warstwy DDD (Wariant C).

**Powiązane:** `.docs/improvement-plan.md` (funkcje MVP), `AGENTS.md`, reguły Cursor w `.cursor/rules/wallet-*.mdc`.

**Stack:** Laravel 13, PHP 8.5, Inertia v2, Pest 4. Po każdej zmianie PHP: `vendor/bin/pint --dirty`. Każda zmiana funkcjonalna: test Pest.

---

## 1. Cele

1. **Spójna nazwa domeny** we wszystkich warstwach (np. zawsze `Transactions`, nie `Transaction` w jednym folderze i `Transactions` w drugim).
2. **Jasne granice odpowiedzialności** — Action ≠ prezentacja Inertia, `Support` ≠ integracje zewnętrzne.
3. **Symetryczny `app/Http/`** — kontrolery, requesty i resources pogrupowane po domenie.
4. **Jeden wzorzec danych pod formularze** — katalog `Data/` zamiast mieszanki `ViewModels` + metod na Action.
5. **Moduł Import** czytelny na mapie projektu — HTTP pod `Imports`, logika w `app/Imports/`.
6. **Refaktoring moduł po module** — bez jednego wielkiego PR zmieniającego całe `app/`.

---

## 2. Co zostaje bez zmiany filozofii

| Element | Uzasadnienie |
|---------|--------------|
| `app/Actions/{Domain}/` | Use case’y z metodą `handle()` — konwencja projektu |
| `app/Models/` globalnie | Mało modeli; relacje cross-domain; migracja do modułów nieopłacalna |
| `app/Imports/` | Duży workflow; osobny feature module |
| `routes/{domain}.php` | Już podzielone (accounts, transactions, transfers) |
| `tests/Feature/{Domain}/` | Lustrzane do domen |
| Front: `resources/js/pages/` + `components/{domain}/` | Zgodne z Inertia |

**Nie wprowadzać:** równoległego `app/Services/`, pełnego `app/Modules/` (Wariant B), warstw `Domain/Application/Infrastructure` (Wariant C).

---

## 3. Docelowa struktura `app/`

```
app/
├── Actions/{Domain}/              # Use case — logika biznesowa, bez formatowania pod Vue
├── Data/{Domain}/                 # FormOptions, shared DTOs (not index read results — use Action getters)
├── Enums/
├── Events/ / Listeners/ / Jobs/
├── Exceptions/
├── Http/
│   ├── Controllers/{Domain}/      # Cienkie: Request → Action → Inertia/redirect
│   ├── Middleware/
│   ├── Requests/{Domain}/
│   └── Resources/{Domain}/
├── Imports/                       # Workflow importu, adaptery banków (bez zmiany root)
├── Integrations/                # Klienty zewnętrzne (Typesense, przyszłe API)
│   └── Typesense/
│   └── DescriptionMemory/         # przeniesione z Support/DescriptionMemory
├── Models/
├── Policies/
├── Providers/
├── Console/Commands/{Domain}/
└── Support/{Domain}/              # Tylko logika domenowa współdzielona (bez HTTP, bez SDK)
```

### 3.1. Nazwy domen (kanoniczne)

| Domena | Namespace segment | Przykładowe modele |
|--------|-------------------|-------------------|
| Accounts | `Accounts` | Account, AccountBalanceAdjustment |
| Transactions | `Transactions` | Transaction |
| Transfers | `Transfers` | (logika na Transaction) |
| Imports | `Imports` | Import |
| Auth | `Auth` | User |
| Settings | `Settings` | User (profil, hasło) |

Zasada: **liczba mnoga** w folderach (`Transactions`, `Accounts`), zgodnie z `Actions/` i `tests/Feature/`.

### 3.2. Odpowiedzialności warstw

| Warstwa | Odpowiedzialność | Nie robi |
|---------|------------------|----------|
| **Controller** | Autoryzacja zasobu, wywołanie Action, mapowanie `Http\Resources\*`, `Inertia::render`, redirect, toast (`message_key`) | SQL, reguły deduplikacji, pełny index workflow |
| **Form Request** | `rules()`, `authorize()`, filtry/sort/stronicowanie (`getFilters()`, `getData()`, `getSorts()`, `Indexable`) | Zapis do DB |
| **Action** | Orchestracja: zapytania, transakcje DB, wywołanie innych Actions/Support; read index: `handle(): void` + gettery | `TransactionResource`, `number_format`, `asset()` |
| **Data** | Opcje formularza (`*FormOptions`), współdzielone immutable DTO | Wyników index (gettery w Action), zapytań Eloquent |
| **Support/{Domain}** | Algorytmy czystej logiki (dedupe, daty względne) | Integracji HTTP/SDK |
| **Integrations/** | Klienty API, repozytoria infrastruktury | Reguł biznesowych portfela |
| **Resource** | Kształt tablicy pod Inertia/API | Autoryzacji |
| **Model** | Relacje, casts, scope’y | Całego workflow importu |
| **Imports/** | Parse, adaptery, workflow commit | CRUD kont |
| **Job** | Dispatch, timeout, tagi | Parsowania CSV w kontrolerze |
| **Policy** | `view/update/delete` per model | Walidacji pól |

---

## 4. Mapowanie migracji (stan obecny → docelowy)

### Faza 0 — Porządek nazw i dokumentacja

| Obecnie | Docelowo | Uwagi |
|---------|----------|-------|
| `Http/Controllers/Transaction/` | `Http/Controllers/Transactions/` | + przenieść `TransferController` → `Transfers/` |
| `Http/Controllers/Transaction/TransactionImportController` | `Http/Controllers/Imports/ImportController` | Opcjonalna zmiana nazwy klasy |
| `Http/Resources/Transaction/` | `Http/Resources/Transactions/` | |
| `Http/Controllers/AccountController.php` (root) | `Http/Controllers/Accounts/AccountController.php` | |
| `Http/Controllers/AccountBalanceController.php` | `Http/Controllers/Accounts/AccountBalanceController.php` | |
| `Http/Requests/StoreAccountRequest.php` (root) | `Http/Requests/Accounts/StoreAccountRequest.php` | |
| `Http/Requests/UpdateAccountRequest.php` | `Http/Requests/Accounts/UpdateAccountRequest.php` | |
| `Http/Requests/AdjustAccountBalanceRequest.php` | `Http/Requests/Accounts/AdjustAccountBalanceRequest.php` | |
| `ViewModels/Accounts/AccountFormOptions` | `Data/Accounts/AccountFormOptions` | Namespace: `App\Data\Accounts` |

**Kroki:** przeniesienie plików, aktualizacja namespace, importów, routes, testów. Jeden PR: „Phase 0: HTTP domain folders”.

### Faza 1 — `Data/` i formularze

| Obecnie | Docelowo |
|---------|----------|
| `ViewModels/` (usunąć po migracji) | `Data/{Domain}/` |
| `ListUserAccounts::forTransactionForm()` | `Data/Accounts/AccountListForForm` lub Action `ListAccountsForForm` + Resource w kontrolerze |

**Decyzja projektowa:** dane statyczne pod formularz (enumy, ikony) → `Data/`. Listy z DB → Action z getterami lub `Collection`, kontroler mapuje przez Resource. Filtry index → `*IndexRequest` (`getFilters()`, `Indexable`), nie `Data/`.

**Kroki:**
1. Przenieś `AccountFormOptions` → `Data/Accounts/`.
2. Ujednolić `create`/`edit` kont i transakcji — jeden typ na ekran.
3. Usuń katalog `app/ViewModels/`.

### Faza 2 — Rozdzielenie query i prezentacji (Transactions)

**Problem:** `ListTransactions` używał `TransactionResource` i formatował summary w Action.

**Docelowo (wzorzec getterów):**
- `ListTransactions::handle(*IndexRequest $request): void` — orkiestracja w prywatnych metodach (`handleFilters`, `handleAccounts`, `handleTransactions`).
- Publiczne gettery eksponują wyniki: `getFilters()`, `getTransactionPaginator()`, `getAccounts()`, `getSummary()`.
- Kontroler: jedno `handle()`, potem jawne składanie props z getterów przez Resources.

| Plik | Zmiana |
|------|--------|
| `Actions/Transactions/ListTransactions.php` | Usuń Resource i `number_format` z Action; gettery zamiast DTO |
| `Actions/Accounts/ListUserAccounts.php` | Usuń `AccountResource::collection` z Action; gettery lub `Collection` |

**Testy:** zaktualizuj `TransactionIndexTest` — asserty na props bez zmiany zachowania UI.

### Faza 3 — `Integrations/` (Typesense)

| Obecnie | Docelowo |
|---------|----------|
| `Support/Typesense/TypesenseClient` | `Integrations/Typesense/TypesenseClient` |
| `Support/DescriptionMemory/*` | `Integrations/DescriptionMemory/*` |

**Kroki:**
1. Przenieś pliki, namespace `App\Integrations\...`.
2. Zaktualizuj `AppServiceProvider` bindingi.
3. Zaktualizuj importy w `Imports/Workflow/EnrichImportRowDescription.php`, listenerach, testach.

`Support/Transactions/` **zostaje** — to logika domenowa.

### Faza 4 — Imports (granica modułu)

| Obecnie | Docelowo |
|---------|----------|
| `TransactionImportController` w `Controllers/Transaction/` | `Controllers/Imports/ImportController` |
| Routes w `web.php` (imports) | Opcjonalnie `routes/imports.php` + `require` w `web.php` |

Logika w `app/Imports/` **bez zmiany**.

### Faza 5 — Pozostałe domeny (kolejność)

1. **Accounts** — po Fazie 0–2 już w większości gotowe.
2. **Transfers** — kontroler do `Controllers/Transfers/`.
3. **Auth / Settings** — tylko jeśli dotykasz tych plików; niski priorytet.

---

## 5. Zasady dla nowego kodu (checklist agenta)

Przed dodaniem pliku sprawdź:

1. **Domena** — czy nazwa folderu jest na liście kanonicznej (§3.1)?
2. **Typ pliku** — Controller/Request/Resource tylko w `Http/`; use case w `Actions/`; DTO w `Data/`.
3. **Action** — jedna publiczna metoda `handle()`, klasa `final` gdy możliwe; bez Resource.
4. **Prezentacja** — Resource wyłącznie w `Http/Resources/{Domain}/` lub cienki mapper w kontrolerze.
5. **Integracja zewnętrzna** — tylko `Integrations/`, binding w `AppServiceProvider`.
6. **Import** — nowy adapter w `Imports/BankAdapters/`, krok workflow w `Imports/Workflow/`.
7. **Test** — feature w `tests/Feature/{Domain}/`, unit czystej logiki w `tests/Unit/{Domain}/` lub `tests/Unit/Data/`.
8. **Routes** — named routes bez zmiany URL-i przy refaktorze namespace.

---

## 6. Front (bez dużych przenosin)

Struktura `resources/js/` zostaje. Przy refaktorze backendu:

- `pages/{domain}/` ↔ `Inertia::render('{domain}/...')`
- Klucze i18n: `{domain}.toast.*` w `locales/*.json`
- Komponenty domenowe: `components/{domain}/` — nie wrzucać logiki kont do `components/ui/`

---

## 7. Kolejność PR-ów (rekomendowana)

| # | PR | Zakres | Ryzyko |
|---|-----|--------|--------|
| 1 | Phase 0: HTTP folders | Przeniesienia namespace, routes | Niskie — mechaniczne |
| 2 | Phase 1: Data + ViewModels | AccountFormOptions, usunięcie ViewModels | Niskie |
| 3 | Phase 2: ListTransactions presenter | Rozdzielenie query/presentacja | Średnie — testy index |
| 4 | Phase 3: Integrations | Typesense, DescriptionMemory | Średnie — DI, import enrichment |
| 5 | Phase 4: Imports controller | Kontroler + routes | Niskie |
| 6 | Phase 5: Transfers + porządki | Pozostałe | Niskie |

**Zasada:** jeden PR = jedna faza lub jedna domena. Nie mieszaj z feature’ami z `improvement-plan.md` w tym samym PR, chyba że naturalnie dotykają tych samych plików.

---

## 8. Akceptacja per faza

### Faza 0
- [x] Wszystkie kontrolery kont w `Http/Controllers/Accounts/`
- [x] `Transactions` i `Imports` — spójne nazwy folderów HTTP
- [x] `php artisan test --compact` — zielone
- [x] `php artisan route:list` — te same URI i nazwy route

### Faza 1
- [x] Brak katalogu `app/ViewModels/`
- [x] `AccountFormOptions` w `app/Data/Accounts/`
- [x] Test `AccountFormOptionsTest` zaktualizowany

### Faza 2
- [x] Żaden Action nie importuje `Http/Resources/*`
- [x] Lista transakcji — identyczne props w Inertia (feature test)

### Faza 3
- [x] Brak `Support/Typesense` i `Support/DescriptionMemory`
- [x] Import enrichment działa (testy `tests/Feature/Imports/`)

### Faza 4
- [x] `ImportController` w `Http/Controllers/Imports/`
- [x] Testy import upload/commit — zielone

---

## 9. Ryzyka i antywzorce

| Antywzorzec | Dlaczego unikać |
|-------------|----------------|
| `Services/` obok `Actions/` | Duplikacja warstwy aplikacji |
| Resource w Action | Trudne testy, zależność Application → Http |
| `Data/*Result` dla index read | Gettery w Action są kanoniczne; kontroler składa props explicite |
| `Support/` na wszystko | Rozmyta granica; użyj `Integrations/` lub `Data/` |
| Big-bang rename całego `app/` | Konflikty git, trudny review |
| Zmiana URL-i przy przenosinach kontrolerów | Łamie zakładki i testy E2E |

---

## 10. Status realizacji

| Faza | Status | Data | Uwagi |
|------|--------|------|-------|
| 0 | Zakończona | 2026-05-28 | HTTP: `Accounts/`, `Transactions/`, `Transfers/` |
| 1 | Zakończona | 2026-05-28 | `Data/Accounts/AccountFormOptions`; usunięto `ViewModels/` |
| 2 | Zakończona | 2026-05-28 | `ListTransactions` (getters); Actions bez Resource |
| 3 | Zakończona | 2026-05-28 | `Integrations/Typesense`, `Integrations/DescriptionMemory` |
| 4 | Zakończona | 2026-05-28 | `ImportController`; `routes/imports.php` |
| 5 | Zakończona | 2026-05-28 | `StoreAccount`, prezentacja w `AccountResource` |

*Aktualizuj tabelę po zakończeniu każdego PR.*

---

## 11. Szybka ściąga: gdzie wrzucić nowy plik

| Potrzebuję… | Lokalizacja |
|-------------|-------------|
| Endpoint Inertia | `Http/Controllers/{Domain}/` |
| Walidacja requestu | `Http/Requests/{Domain}/` |
| Kształt props JSON | `Http/Resources/{Domain}/` |
| Operacja biznesowa | `Actions/{Domain}/` |
| Read index (gettery) | `Actions/{Domain}/` + `Http/Requests/{Domain}/` (*IndexRequest) |
| Opcje formularza / współdzielone DTO | `Data/{Domain}/` |
| Algorytm domenowy (dedupe, daty) | `Support/{Domain}/` |
| API zewnętrzne (Typesense) | `Integrations/{Name}/` |
| Encja DB | `Models/` |
| Import CSV / workflow | `Imports/` |
| Kolejka | `Jobs/` + wywołanie z Action/Workflow |
| Test HTTP | `tests/Feature/{Domain}/` |
| Test czystej logiki | `tests/Unit/...` |
