# PRD v1 — Wallet Master (MVP)

## Glossary (spójne pojęcia)
- **Konto**: konto bankowe użytkownika w aplikacji (nazwa, waluta, saldo).
- **Typ konta**: klasyfikacja konta (enum) opisująca przeznaczenie konta (np. ROR, oszczędnościowe).
- **Bank**: instytucja/źródło wyciągu przypisane do konta. W MVP wspieramy: **BNP Paribas**, **mBank** oraz specjalny “bank” **Gotówka** (dla kont gotówkowych).
- **Transakcja**: pojedynczy zapis finansowy przypisany do konta (przychód/wydatek/transfer/korekta).
- **Data operacji (`date`)**: faktyczna data wykonania operacji w banku.
- **Data księgowania (`booked_at`)**: data przypisania transakcji do okresu rozliczeniowego użytkownika. Domyślnie równa `date`. Użytkownik może ją zmienić, żeby zaliczyć transakcję do innego okresu (np. zwrot z karty z 03.05 ujęty w okresie kwietniowym). Wszystkie filtry list, podsumowania i raporty operują domyślnie po `booked_at`.
- **Transfer**: akcja tworząca **2 transakcje** (wydatek na koncie źródłowym + przychód na koncie docelowym) powiązane ze sobą wspólnym `transfer_id`. Może powstać manualnie (akcja użytkownika) albo automatycznie podczas importu (matcher transferów).
- **Korekta salda (`adjustment`)**: transakcja typu `adjustment` zapisywana przy ręcznej zmianie salda konta. Kwota równa się delcie (`new_balance - current_balance`); wpływa na saldo poprzez normalną sumę transakcji.
- **Import**: proces wczytania transakcji z pliku CSV/XLSX z automatycznym mapowaniem kolumn (adapter banku) i zapisem bez etapu preview; system pomija duplikaty i pokazuje wynik.
- **Mapowanie (kolumn)**: przypisanie kolumn pliku do pól transakcji (data, kwota, opis, opcjonalnie `subject`) wykonywane **automatycznie przez adapter banku** na podstawie nagłówków wyciągu — użytkownik nie mapuje kolumn ręcznie w UI.
- **Subject**: pole tekstowe “nadawca/odbiorca” przechowywane osobno od opisu.
- **Duplikat (importu)**: transakcja o identycznej dacie, kwocie i znormalizowanym opisie na tym samym koncie. Podczas importu zawsze pomijana. Ręczne dodanie identycznej transakcji jest dozwolone. **Uzasadnienie**: wspierane banki (BNP Paribas, mBank) nie eksportują w wyciągach unikalnych identyfikatorów transakcji, więc dedupe opieramy wyłącznie na heurystyce `date + amount + normalized_description`.
- **Aktywny użytkownik**: użytkownik, który wykonał min. 1 akcję produktową (np. import lub dodanie transakcji) w ostatnich 7 dniach. **[Assumption]**

---

## 1. Overview
Budujemy webową aplikację do zarządzania budżetem domowym: użytkownik tworzy konta, dodaje/ogląda transakcje i importuje je z wyciągów bankowych (CSV/XLSX). MVP ma dać szybki “time-to-value”: sprawne dodawanie transakcji ręcznie oraz wysoki sukces importu z minimalną korektą.

Źródło wymagań produktu: `.docs/mvp.md` (MVP scope, sukces, persona) + `.docs/tech-stack.md` (stack i ograniczenia).

---

## 2. Goals / Success Metrics

### Cele (mierzalne)
- Użytkownik może samodzielnie: zarejestrować się, dodać konto, dodać transakcję, przejrzeć i przefiltrować historię.
- Użytkownik może zaimportować historię transakcji z CSV/XLSX (wybór konta → upload); mapowanie kolumn odbywa się automatycznie (adapter banku konta), następnie auto-commit bez preview.
- Transfer pomiędzy własnymi kontami jest wspierany jako jedna akcja (2 transakcje) i poprawnie wpływa na salda.
- Dane są ściśle izolowane per użytkownik (brak wycieków).

### Metryki sukcesu i pomiar
- **Import success rate**: min. **90% wierszy** z poprawnie zmapowanego pliku zostaje zaimportowanych bez potrzeby ręcznej korekty.
  - Pomiar: w imporcie liczyć `rows_total`, `rows_imported`, `rows_skipped_duplicate`, `rows_failed_validation`.
- **Time-to-add (manual)**: mediana czasu ręcznego dodania transakcji < **30s** (od kliknięcia “Dodaj transakcję” do sukcesu zapisu).
  - Pomiar: event `transaction_create_opened` + `transaction_created` z różnicą czasu po stronie frontendu.
- **Activation import**: min. **70% aktywnych** wykonuje ≥1 import w ciągu 7 dni od rejestracji.
  - Pomiar: `user_registered` + `import_completed` w 7-dniowym oknie.
- **Data isolation**: **0** incydentów wycieku danych między użytkownikami.
  - Pomiar: testy automatyczne (feature), manual QA checklist.

---

## 3. Non-Goals (Out of Scope)
Zgodnie z `.docs/mvp.md`, MVP nie obejmuje:
- Kategoryzacji transakcji ani AI sugerowania kategorii.
- Wielowalutowości i przeliczeń kursów (MVP: PLN, ale waluta jest polem w danych).
- Współdzielenia danych między użytkownikami (budżet rodzinny).
- Importu z innych formatów niż CSV/XLSX (PDF/MT940/OCR).
- Załączników, eksportu danych, raportów/wykresów.
- Budżetowania/szacowania przychodów i wydatków.
- Integracji z bankami / zewnętrznymi systemami.
- Aplikacji mobilnych.
- Zaawansowanych akcji masowych/szablonów/duplikowania transakcji.

---

## 4. Target Users & Personas

### Persona 1: “Osoba prowadząca budżet osobisty”
- **Kim**: użytkownik z 1–3 kontami w PLN.
- **Potrzeby**: szybki wgląd w wpływy/wydatki w czasie; wygodny import z banku.
- **Bariery**: różne formaty plików bankowych; niechęć do ręcznego przepisywania.
- **Kontekst użycia**: web, regularnie (kilka razy w tygodniu/miesiącu), umiarkowana krytyczność (dane finansowe).

Desktop/laptop jest priorytetem, mobile web ma działać poprawnie, bez dedykowanego “mobile-first” zakresu w MVP. **[Assumption]**

---

## 5. Problem Statement & Key Use Cases

### Problem
Użytkownik chce kontrolować domowy budżet i ograniczać zbędne wydatki, ale ręczne wprowadzanie historii transakcji jest uciążliwe i podatne na błędy. Potrzebuje prostego narzędzia do wprowadzania/analizy historii transakcji oraz łatwego importu z banku.

### Key use cases (priorytet)
1. Rejestracja / logowanie (Must)
2. Zarządzanie kontami (Must)
3. Dodawanie i edycja transakcji (Must)
4. Przegląd transakcji + filtry/sort/paginacja + podsumowanie (Must)
5. Import CSV/XLSX z auto-mapowaniem bankowym + auto-commit + deduplikacją (Must)
6. Transfer między kontami (Must)
7. Reset hasła (Should)

---

## 6. User Journeys

### Journey A — Pierwsze uruchomienie i ręczne dodanie transakcji (happy path)
Rejestracja → utworzenie konta → “Dodaj transakcję” → zapis → transakcja widoczna na liście → saldo konta zaktualizowane.

**Alternatywy (krytyczne)**
- Walidacja błędów (np. brak daty/kwoty) → inline error → użytkownik poprawia → zapis.

### Journey B — Import wyciągu z banku (happy path)
Widok transakcji → “Import” → wybór konta → upload CSV/XLSX → system rozpoznaje nagłówki i mapuje kolumny (adapter banku konta) → automatyczny zapis importu → duplikaty pominięte → wynik (X nowych, Y duplikatów, Z błędnych) → lista transakcji uzupełniona → saldo zaktualizowane.

**Alternatywy (krytyczne)**
- Plik ma niepoprawne dane → import kończy się częściowo; użytkownik widzi liczniki i listę błędnych/skipowanych wierszy dla danego importu.
- Wszystkie wiersze są duplikatami → import kończy się sukcesem z `rows_imported = 0` i jasnym komunikatem.

### Journey C — Transfer między kontami
Użytkownik wybiera “Transfer” → wybiera konto źródłowe i docelowe → kwota + data + opis → zapis → powstają 2 transakcje powiązane, salda obu kont zaktualizowane.

### Journey D — Usunięcie konta z historią transakcji
Użytkownik usuwa konto → konto znika z listy kont (lub jest oznaczone jako usunięte) → transakcje pozostają w historii, ale są **nieedytowalne**.

---

## 7. Functional Requirements

### 7.1 Autentykacja i konto użytkownika

#### FR-A1 Rejestracja i logowanie
- **Opis**: użytkownik może założyć konto (email + hasło) i zalogować się; sesja Laravel.
- **Priorytet**: Must
- **Acceptance Criteria (Given/When/Then)**
  - Given użytkownik jest niezalogowany  
    When rejestruje się poprawnymi danymi  
    Then jest zalogowany i widzi aplikację.
  - Given użytkownik jest niezalogowany  
    When loguje się poprawnymi danymi  
    Then widzi aplikację.
- **Edge cases**
  - Duplikat email.
  - Zbyt słabe hasło (stosujemy reguły frameworka). **[Assumption]**
- **Telemetry/Events**
  - `user_registered`, `user_logged_in`, `user_login_failed`

#### FR-A2 Reset hasła
- **Opis**: użytkownik może zresetować hasło emailowo.
- **Priorytet**: Should
- **Acceptance Criteria (Given/When/Then)**
  - Given użytkownik nie pamięta hasła  
    When zainicjuje reset dla emaila  
    Then otrzyma email i może ustawić nowe hasło (bez ujawniania czy email istnieje).
- **Edge cases**
  - Rate limit żądań resetu. **[Assumption]**
- **Telemetry/Events**
  - `password_reset_requested`, `password_reset_completed`

---

### 7.2 Konta

#### FR-K1 CRUD kont
- **Opis**: tworzenie/edycja/usuwanie kont, lista kont.
- **Priorytet**: Must
- **Acceptance Criteria (Given/When/Then)**
  - Given zalogowany użytkownik  
    When doda konto z nazwą, walutą=PLN, saldem początkowym, typem konta i bankiem  
    Then konto pojawia się na liście, a saldo jest ustawione.
  - Given konto użytkownika  
    When edytuje nazwę/saldo początkowe  
    Then zmiany zapisują się, a saldo bieżące jest aktualizowane zgodnie z zasadami (FR-S1, FR-T1).
- **Edge cases**
  - Nazwa pusta/zbyt długa.
  - Waluta w MVP ograniczona do PLN w UI, ale istnieje jako encja (tabela) dla przyszłej rozbudowy. **[Assumption]**
  - Typ konta i bank muszą pochodzić z dozwolonej listy (enum).
- **Telemetry/Events**
  - `account_created`, `account_updated`, `account_deleted`

**Wymagane pola konta (MVP)**
- `name` (string)
- `currency` (MVP: PLN w UI)
- `opening_balance` (decimal)
- `type` (enum, MVP): `Ror`, `Savings` (możliwe dopisanie kolejnych w przyszłości)
- `bank` (enum, MVP): `BnpParibas`, `MBank`, `Cash`

**Ikony banków**
- Każdy bank w MVP ma mieć ikonę (asset) mapowaną po wartości `bank` (slug/enum). Ikony dostarczysz.

#### FR-K2 Usunięcie konta nie usuwa transakcji i blokuje edycję
- **Opis**: po usunięciu konta transakcje pozostają widoczne, ale bez możliwości edycji/usuwania.
- **Priorytet**: Must
- **Acceptance Criteria (Given/When/Then)**
  - Given konto ma transakcje  
    When użytkownik usunie konto  
    Then transakcje nadal są widoczne w historii, ale nie można ich edytować/usunąć.
- **Edge cases**
  - Import do usuniętego konta: blokada.
  - Transfer do/z usuniętego konta: blokada.
- **Telemetry/Events**
  - `account_deleted_with_transactions` (z liczbą transakcji)

**Options + Recommendation + Rationale**
- **Opcja 1**: soft-delete konta; transakcje nadal wskazują konto; UI blokuje edycję transakcji, gdy konto usunięte.
- **Opcja 2**: odłączenie `account_id = null` + “snapshot” nazwy konta w transakcji.
- **Recommendation**: Opcja 1
- **Rationale**: zachowuje spójność historii i filtrowania; mniej wyjątków w logice.

---

### 7.3 Transakcje

#### FR-T1 CRUD transakcji (przychód/wydatek)
- **Opis**: dodawanie, edycja, usuwanie transakcji na koncie.
- **Priorytet**: Must
- **Acceptance Criteria (Given/When/Then)**
  - Given konto aktywne  
    When użytkownik doda transakcję z datą operacji, datą księgowania (opcjonalnie, domyślnie = data operacji), kwotą, opisem, subject (opcjonalnie)  
    Then transakcja pojawia się na liście i wpływa na saldo (ujemna kwota zmniejsza saldo, dodatnia zwiększa).
  - Given istniejąca transakcja  
    When użytkownik edytuje jej pola (w tym samą `booked_at` bez zmiany `date`)  
    Then zmiany zapisują się i saldo jest zaktualizowane; przesunięcie `booked_at` zmienia tylko przypisanie do okresu rozliczeniowego, nie wpływa na saldo bieżące.
- **Edge cases**
  - Transakcja na usuniętym koncie: brak możliwości edycji/usuwania.
  - Kwota = 0: niedozwolona — egzekwowane w warstwie domeny (FormRequest + Akcja + Importer).
  - Data w przyszłości: dozwolona. **[Assumption]**
  - `booked_at` może być wcześniejszy lub późniejszy niż `date` (brak ograniczenia zakresu). Domyślnie `booked_at = date`.
  - Ręczne dodanie transakcji o identycznych polach co istniejąca (sklep + kwota + dzień) jest **dozwolone**. W bazie wpisy ręczne używają unikalnego `dedupe_hash` (sufiks UUID), żeby nie kolidować z indeksem dedupe importu.
- **Telemetry/Events**
  - `transaction_created`, `transaction_updated`, `transaction_deleted`

#### FR-T2 Lista transakcji + filtry/sort/paginacja + podsumowanie
- **Opis**: lista z filtrowaniem po koncie i przedziale dat (domyślnie po `booked_at`), sort po dacie/kwocie, paginacja, suma wpływów i wydatków w okresie.
- **Priorytet**: Must
- **Acceptance Criteria (Given/When/Then)**
  - Given lista transakcji  
    When użytkownik ustawi filtr dat (`from`/`to` po `booked_at`) i konto  
    Then widzi tylko pasujące transakcje oraz podsumowanie wpływów i wydatków dla tego zakresu.
  - Given transakcja z `date=01.04` i `booked_at=30.03`  
    When użytkownik filtruje listę po marcu  
    Then transakcja jest widoczna w tym oknie (i nie jest widoczna w kwietniu).
- **Edge cases**
  - Brak wyników: empty state.
  - Zakres dat od>do: walidacja.
  - **Wewnętrzne transfery są wykluczone z `summary.total_income` i `summary.total_expense`** (filtr `transfer_id IS NULL`), żeby nie zawyżać sumy wpływów/wydatków o przepływy między własnymi kontami.
  - Korekty salda (`type=adjustment`) są wliczane do wpływów/wydatków zgodnie ze znakiem kwoty (delta dodatnia → income, ujemna → expense).
  - Lista pokazuje **jedną kolumnę daty okresu** (`COALESCE(booked_at, date)`) z etykietą względną; faktyczna data operacji (`date`) jest widoczna w Create/Edit i w tooltipie surowego opisu importu, gdy różni się od daty okresu. Filtry, sort i podsumowanie operują po `COALESCE(booked_at, date)`; domyślny sort po tej dacie malejąco, tie-breaker `date desc, id desc`.
- **Telemetry/Events**
  - `transactions_filtered`, `transactions_sorted`, `transactions_page_changed`

#### FR-T3 Transfer między kontami (jedna akcja → 2 transakcje)
- **Opis**: UI pozwala wykonać transfer; tworzy 2 transakcje powiązane; nie wolno wybrać tego samego konta jako źródło i cel; daty identyczne.
- **Priorytet**: Must
- **Acceptance Criteria (Given/When/Then)**
  - Given dwa różne konta użytkownika  
    When wykona transfer kwoty X w dacie D  
    Then powstają 2 transakcje powiązane:  
    - na koncie źródłowym kwota = `-X`  
    - na koncie docelowym kwota = `+X`  
    i saldo obu kont aktualizuje się.
- **Edge cases**
  - Usunięte konto źródłowe/docelowe: blokada.
  - Kwota ujemna w formularzu transferu: walidacja (użytkownik podaje kwotę dodatnią, system zapisuje znaki). **[Assumption]**
- **Telemetry/Events**
  - `transfer_created`, `transfer_failed_validation`

---

### 7.4 Salda

#### FR-S1 Saldo kont: zapisywane i aktualizowane + ręczna korekta
- **Opis**: saldo jest przechowywane i aktualizowane przy zmianach transakcji; dopuszczalna ręczna korekta zapisywana jako transakcja typu `adjustment`.
- **Priorytet**: Must
- **Acceptance Criteria (Given/When/Then)**
  - Given saldo konta  
    When użytkownik doda/zmieni/usunie transakcję  
    Then saldo konta aktualizuje się o różnicę kwoty (delta).
  - Given użytkownik wprowadzi korektę salda na wartość `X`  
    When zapisze korektę  
    Then system: (1) tworzy transakcję typu `adjustment` z kwotą `X − current_balance`, datą i `booked_at = today`; (2) aktualizuje `current_balance = X`; (3) zapisuje wpis audytowy w `account_balance_adjustments` (stare/nowe saldo).
  - Given dowolna sekwencja operacji  
    When wykonana jest komenda `php artisan accounts:recalculate-balance --dry-run`  
    Then komenda raportuje 0 różnic między `current_balance` a `opening_balance + SUM(amount)`.
- **Edge cases**
  - Korekta salda nie nadpisuje historii; jest nowym wpisem księgowym (`adjustment`) i pozostawia pełny ślad audytowy.
  - Adjustment jest widoczny na liście transakcji (z badge „Korekta").
- **Telemetry/Events**
  - `account_balance_adjusted` (stare→nowe, reason opcjonalny) **[Assumption]**

**Options + Recommendation + Rationale**
- **Opcja 1**: saldo wyliczane z transakcji przy każdym odczycie.
- **Opcja 2**: saldo zapisywane i aktualizowane przy zmianach (z transakcjami DB) + komenda rekalkulacji jako safety net.
- **Recommendation**: Opcja 2
- **Rationale**: wydajniejsze listy i podsumowania; mniej kosztownych zapytań. Komenda rekalkulacji eliminuje ryzyko dryfu salda po incydencie.

---

### 7.5 Import (CSV/XLSX)

#### FR-I1 Import pliku z auto-mapowaniem bankowym (auto-commit) + wynik
- **Opis**: użytkownik wybiera konto i uploaduje CSV/XLSX. System na podstawie `Account.bank` wybiera adapter, rozpoznaje nagłówki i **automatycznie** przypisuje kolumny do pól: data, kwota, opis, opcjonalnie `subject`. Po uploadzie import jest od razu kolejkowany (commit) — bez etapu preview i **bez ręcznego mapowania w UI**. System pomija duplikaty i zwraca podsumowanie importu.
- **Priorytet**: Must
- **Acceptance Criteria (Given/When/Then)**
  - Given użytkownik wybrał konto z obsługiwanym bankiem  
    When załaduje plik z rozpoznawalnymi nagłówkami dla tego banku  
    Then import uruchamia się automatycznie i użytkownik widzi wynik: `rows_imported`, `rows_skipped_duplicate`, `rows_failed_validation`.
  - Given plik ma nagłówki nierozpoznawane przez adapter banku  
    When użytkownik próbuje uploadu  
    Then otrzymuje czytelny błąd (`unrecognized_headers`) bez tworzenia transakcji.
- **Edge cases**
  - Błędny format pliku: czytelny błąd.
  - Różne formaty dat/kwot: walidacja + komunikat.
  - **Akceptowane formaty kwot**: `1234,56`, `1 234,56`, `1.234,56`, `1234.56`, `(123,45)` (księgowe = ujemne); separator tysięcy: spacja (też NBSP) lub kropka; jednostki waluty (`PLN`, `zł`, `EUR`, `USD`) są usuwane.
  - **Akceptowane formaty dat**: `d-m-Y`, `Y-m-d`, `d/m/Y`, `d.m.Y`, `Y.m.d`, `Y/m/d`; suffix czasu jest odcinany.
  - **Kodowanie pliku**: detekcja `UTF-8`/`Windows-1250`/`ISO-8859-2` z konwersją do UTF-8; usunięcie BOM. Polskie znaki muszą być zachowane.
  - XLSX: importujemy pierwszy arkusz.
  - CSV: separator wykrywany automatycznie.
  - Nagłówki kolumn są wymagane (mapping po nazwach nagłówków).
  - Kwota = 0 jest odrzucana (`rows_failed_validation++`).
- **Telemetry/Events**
  - `import_started`, `import_completed`, `import_failed`

#### FR-I2 Wyliczanie typu na podstawie znaku kwoty + przechowywanie kwot ujemnych
- **Opis**: typ transakcji jest determinowany znakiem kwoty (ujemna=wydatek, dodatnia=przychód), a kwota jest zapisywana w DB z tym znakiem (ujemna dla wydatków).
- **Priorytet**: Must
- **Acceptance Criteria (Given/When/Then)**
  - Given wiersz z kwotą ujemną  
    When import  
    Then transakcja ma typ=wydatek i kwota jest zapisana jako ujemna.
  - Given wiersz z kwotą dodatnią  
    When import  
    Then transakcja ma typ=przychód i kwota jest zapisana jako dodatnia.
- **Edge cases**
  - Kwoty w nawiasach (format księgowy): traktować jako ujemne. **[Assumption]**
- **Telemetry/Events**
  - `import_type_inferred`

#### FR-I3 Deduplikacja: import pomija, ręczne dodanie dozwolone
- **Opis**: podczas importu system wykrywa duplikaty w obrębie tego samego konta i zawsze je pomija. Klucz dedupe: `date + amount + normalized_description`. Ręczne dodanie identycznej transakcji jest dozwolone.
- **Priorytet**: Must
- **Acceptance Criteria (Given/When/Then)**
  - Given w pliku importu są dwa wiersze z identycznymi `date + amount + normalized_description`
    When import
    Then drugi wiersz jest pominięty, `rows_skipped_duplicate++`.
  - Given użytkownik dodaje ręcznie transakcję, której `date + amount + normalized_description` zgadza się z istniejącą na tym koncie
    When zapisuje formularz
    Then transakcja jest zapisywana; w bazie istnieją dwa rekordy.
- **Edge cases**
  - Normalizacja opisu (MVP): `trim` + `case-fold` + standaryzacja whitespace (wielokrotne spacje → jedna).
  - Dwa zakupy w tym samym sklepie tego samego dnia za tę samą kwotę: pierwszy import zapisuje, drugi pomija (znana, akceptowana wada MVP — telemetria `import_rows_skipped_duplicate` daje sygnał skali w razie potrzeby decyzji o zmianie strategii).
  - Wspierane banki (BNP Paribas, mBank) nie eksportują w wyciągach unikalnych identyfikatorów transakcji — dlatego nie używamy `bank_reference_id`.
- **Telemetry/Events**
  - `import_rows_skipped_duplicate` (agregat)

#### FR-I4 Adaptery banków (auto-mapowanie kolumn)
- **Opis**: mapowanie kolumn jest częścią adaptera banku (`BankImportAdapter::defaultMapping`). Użytkownik nie edytuje mapowania w UI — wybiera tylko konto (bank wynika z `Account.bank`). Adapter rozpoznaje nagłówki wyciągu i mapuje je na pola transakcji; może zawierać logikę bankowo-specyficzną (parsowanie dat/kwot, format pliku, opcjonalna kolumna `subject`).
- **Priorytet**: Must
- **Acceptance Criteria (Given/When/Then)**
  - Given konto w banku X (mBank / BNP Paribas)  
    When użytkownik uploaduje standardowy wyciąg tego banku  
    Then adapter X automatycznie ustawia mapowanie kolumn i import przechodzi dalej bez interakcji użytkownika.
- **Edge cases**
  - Zmiana formatu pliku banku: nowe nagłówki wymagają aktualizacji adaptera (lub rozszerzenia `defaultMapping` o aliasy nagłówków).
  - Konto Cash: import z pliku zablokowany (422), nie 500.
- **Telemetry/Events**
  - `import_bank_resolved_from_account` (wartość banku z konta) **[Assumption]**
  - `import_headers_unrecognized` (gdy adapter nie rozpozna wymaganych kolumn) **[Assumption]**

**Doprecyzowanie (MVP)**
- Bank importu wynika z wybranego konta: `Account.bank` jest obowiązkowy, więc w imporcie nie wybiera się banku osobno (UI może go wyświetlić).
- Logika “per bank” obejmuje: rozpoznanie nagłówków, mapowanie kolumn, parsowanie dat/kwot, normalizację opisu, opcjonalną kolumnę `subject`.

#### FR-I5 “Pamięć” opisów z wyciągu: sugerowanie `subject` i `description` (Typesense)
- **Opis**: system uczy się, jak użytkownik rozdziela surowy opis z wyciągu na `subject` i `description`. Podczas importu system automatycznie stosuje `subject` i `description` na podstawie wcześniejszych korekt użytkownika zapisanych w Typesense (best-effort).
- **Priorytet**: Should
- **Acceptance Criteria (Given/When/Then)**
  - Given użytkownik edytował transakcję zaimportowaną z opisem z wyciągu `raw_statement_description`  
    When zapisze edycję `subject` i `description`  
    Then system zapisuje “pamięć” mapowania w Typesense dla tego użytkownika i banku.
  - Given użytkownik importuje kolejne transakcje z takim samym (lub znormalizowanym) `raw_statement_description`  
    When import tworzy transakcje  
    Then system uzupełnia `subject` i `description` na podstawie pamięci (jeśli jest dopasowanie).
- **Edge cases**
  - Brak dopasowania: `subject` pozostaje puste, a `description` bazuje na surowym opisie (fallback).
  - Pamięć jest izolowana per użytkownik (brak wycieków między userami).
  - Dopasowanie może być specyficzne per bank (formaty opisów różnią się między bankami).
  - Brak dostępności Typesense nie blokuje importu (degradacja do fallbacku).
- **Telemetry/Events**
  - `import_enrichment_typesense_hit`, `import_enrichment_typesense_miss` **[Assumption]**

**Options + Recommendation + Rationale**
- **Opcja 1**: tylko “szablony mapowania” w DB (konfigurowalne przez usera).
- **Opcja 2**: “bank adapters” w kodzie + możliwość zapisu mapowania (hybryda).
- **Recommendation**: Opcja 2
- **Rationale**: wspiera ekstrakcję `subject` i ułatwia utrzymanie wielu formatów, bez blokowania importu.

#### FR-I6 Identyfikacja transferów podczas importu (matcher)
- **Opis**: po zakończonym imporcie system próbuje rozpoznać, że nowo dodana transakcja jest jedną z dwóch nóg transferu między kontami tego samego użytkownika i automatycznie łączy ją ze sparowaną transakcją na innym koncie wspólnym `transfer_id`. Dla niejednoznacznych przypadków oznacza je jako kandydatów do ręcznego potwierdzenia.
- **Priorytet**: Should
- **Acceptance Criteria (Given/When/Then)**
  - Given użytkownik zaimportował z mBanku transakcję `-200 PLN` z opisem zawierającym token „przelew własny", a wcześniej (lub później) zaimportował z BNP transakcję `+200 PLN` z analogicznym opisem, obie z różnicą daty ≤ 3 dni  
    When importer kończy commit  
    Then obie transakcje otrzymują wspólny `transfer_id`, `type = transfer`, `transfer_match_status = auto`. Saldo obu kont nie zmienia się względem stanu sprzed dopasowania.
  - Given dwie transakcje o przeciwnych znakach i tej samej kwocie bezwzględnej, ale **bez** tokenów „transfer" w opisie  
    When importer kończy commit  
    Then transakcje **nie** są automatycznie łączone, ale obie otrzymują `transfer_match_status = manual` i wzajemny `transfer_candidate_for_id`. Pojawiają się w **banerze kandydatów transferu** na liście transakcji do potwierdzenia/odrzucenia.
  - Given dla nowej transakcji znaleziono >1 kandydatkę  
    When matcher pracuje  
    Then **nie** linkuje automatycznie — wszyscy kandydaci są oznaczeni jako `manual`.
  - Given użytkownik kliknie „To nie transfer"  
    When zapisze decyzję  
    Then obie transakcje otrzymują `transfer_match_status = rejected`; już nie są proponowane ponownie nawet po kolejnym imporcie.
  - Given istnieje transfer z `transfer_id`  
    When użytkownik wykona „Rozłącz transfer"  
    Then obie nogi tracą `transfer_id`, `type` jest przywracany na podstawie znaku `amount`, `transfer_match_status = rejected`.
- **Edge cases**
  - Konto Cash: matcher pracuje normalnie (Cash może być źródłem/celem transferu).
  - Różne waluty: matcher pomija (na MVP transfery wieloprawalutowe są nieobsługiwane).
  - Transakcja już mająca `transfer_id` (utworzona przez akcję „Transfer" w UI) nie podlega matcherowi.
  - Heurystyki tokenowe (`przelew własny`, `przelew wewn`, `transfer`, `własny`, `between accounts`) trzymane w `config/imports.php` jako konfigurowalna lista.
- **Telemetry/Events**
  - `transfer_auto_linked`, `transfer_manually_linked`, `transfer_unlinked`, `transfer_match_skipped_ambiguous`

**Options + Recommendation + Rationale**
- **Opcja 1**: matcher synchroniczny (po commicie importu, w tej samej kolejce).
- **Opcja 2**: matcher asynchroniczny (osobny job, dispatch po commicie).
- **Recommendation**: Opcja 1 (na MVP)
- **Rationale**: liczba kandydatów ograniczona przez user-scope; uproszczona obsługa błędów; nie potrzebujemy dodatkowej kolejki.

---

## 8. Information Architecture & Navigation

### Ekrany/strony
- **Auth**: Logowanie, Rejestracja, Reset hasła.
- **Konta**: Lista kont, Dodaj konto, Edytuj konto.
- **Transakcje**: Lista transakcji (filtry/sort/paginacja/podsumowanie, kolumna daty okresu `COALESCE(booked_at, date)`), Dodaj transakcję, Edytuj transakcję, Dodaj transfer; **baner kandydatów transferu** (FR-I6) na liście transakcji z akcjami „Potwierdź transfer" / „To nie transfer".
- **Import**: (modal z widoku transakcji) Wybór konta, Upload pliku, Oczekiwanie na przetwarzanie (status realtime), Podsumowanie importu.

### Mapa nawigacji
- Konta
- Transakcje (w tym baner kandydatów transferu z liczbą oczekujących par)
- Import
- (opcjonalnie) Ustawienia profilu **[Assumption]**

---

## 9. UX/UI Requirements
- **Język UI**: polski.
- **i18n**: architektura powinna uwzględniać możliwość i18n w przyszłości (np. formatowanie dat/kwot przez warstwę formatterów), bez pełnego wdrożenia w MVP. **[Assumption]**
- **Format daty**: **DD-MM-YYYY** (zarówno dla `date`, jak i `booked_at`).
- **Format kwoty (input)**: zgodny z PL (przecinek dziesiętny), z tolerancją kropki dziesiętnej.
- **Format kwoty (import z pliku)**: tolerujemy `1234,56`, `1 234,56` (NBSP), `1.234,56`, `1234.56`, `(123,45)` (księgowe = ujemne) oraz przyrostki `PLN`/`zł`/`EUR`/`USD`.
- **Walidacja**: inline + jasne komunikaty; błędy importu wskazują, które pola są niepoprawne; ręczne dodanie identycznej drugiej transakcji jest dozwolone (nie blokujemy false-positive duplikatami).
- **Empty states**: brak kont → CTA „Dodaj konto"; brak transakcji → CTA „Dodaj transakcję" i „Zaimportuj plik"; brak kandydatów transferów → komunikat „Brak nierozpoznanych transferów".
- **Loading states**: import (po upload/mapowaniu) — loader/skeleton + przyrastający licznik wierszy w czasie rzeczywistym (Reverb); na koniec podsumowanie importu.
- **A11y baseline**: obsługa klawiaturą, widoczne focus states, kontrast WCAG AA, poprawne etykiety (label/aria).
- **Copy tone**: krótko, rzeczowo; podsumowanie importu: „Zaimportowano X, pominięto duplikaty Y, błędne wiersze Z, możliwe transfery do potwierdzenia: K".

---

## 10. Non-Functional Requirements
- **Performance**
  - Lista transakcji: paginacja backendowa; filtry po koncie i `booked_at` zoptymalizowane indeksami złożonymi.
  - Import: chunked processing po N wierszy (default 500) w osobnych krótkich transakcjach DB; bulk insert; saldo aktualizowane w finalnej, oddzielnej transakcji. Brak długich locków na koncie/imporcie podczas pętli wierszy.
  - Realtime: broadcast statusu importu (`queued → processing → committed|failed`) **plus** broadcast przyrostu liczników po każdym chunk; fallback polling co 5s gdy WebSocket rozłączony.
- **Security & privacy**
  - OWASP baseline: CSRF, XSS, hardening auth.
  - Rate limiting:
    - logowanie i reset hasła: 6/min per IP (już wdrożone),
    - upload importu i commit importu: 10/min per użytkownik,
    - ogólny limiter API: 60/min per użytkownik.
  - Izolacja danych: autoryzacja per zasób (konto/transakcja/import). Pamięć Typesense (FR-I5) izolowana per `user_id + bank` w `filter_by`.
  - Nie logować surowych plików importu ani pełnych wierszy danych w logach produkcyjnych. **[Assumption]**
  - Mass assignment: modele używają `$fillable` (nie `$guarded = []`); `Model::shouldBeStrict()` w środowiskach nieprodukcyjnych.
  - Konto Cash przy próbie importu: czytelny komunikat 422 (nie 500).
- **Observability**
  - Logi aplikacyjne + metryki produktowe (eventy z PRD) zapisywane do dedykowanego kanału loga `telemetry` (daily, JSON line).
  - Minimalny audit trail: korekty salda (transakcja `adjustment` + wpis `account_balance_adjustments`); soft delete kont (`deleted_at`).
- **Encoding & parser ścieżki krytyczne (importer)**
  - Detekcja kodowania pliku: `UTF-8`, `Windows-1250`, `ISO-8859-2`; konwersja do UTF-8 przed parsowaniem; usunięcie BOM.
  - Parser kwot wspiera separator tysięcy (spacja, NBSP, kropka), separator dziesiętny (kropka lub przecinek), nawiasy księgowe, przyrostki walut.
  - Parser dat wspiera: `d-m-Y`, `Y-m-d`, `d/m/Y`, `d.m.Y`, `Y.m.d`, `Y/m/d` z opcjonalnym suffixem czasu.
- **Retencja plików importu**
  - Importy zakończone sukcesem: plik usuwany po commit.
  - Importy zakończone `Failed`: plik zachowywany w `storage/app/imports/{user}/{import}/source-failed.{ext}` przez 30 dni; cron `imports:purge-old-files` czyści starsze.
- **Backup/DR**
  - Backup DB zależny od środowiska wdrożenia (wymóg operacyjny, poza implementacją MVP). **[Assumption]**

---

## 11. Tech Constraints & Architecture Notes
- Backend: Laravel 13 + Inertia Laravel v2, auth sesyjny (`App\Http\Controllers\Auth\*`).
- Frontend: Vue 3 + TypeScript, Inertia Vue v2, Vite 6, Tailwind 3.
- DB: domyślnie SQLite, z Sail typowo MySQL.
- Queue: domyślnie `database`, na produkcji Horizon + Redis.
- Realtime: Reverb (kanały private per user/import).
- Wyszukiwanie/„pamięć" enrichment: Typesense (collection `import_description_memory`); brak Typesense degraduje feature do fallbacku, ale nie blokuje importu.
- Testy/jakość: Pest 4 / PHPUnit 12, Pint, ESLint/Prettier, Larastan.

Integracje zewnętrzne: Typesense (wewnątrz infrastruktury); import plików tylko lokalny upload.

Wymagania dot. kontraktów (na poziomie PRD):
- Import jako proces 1-etapowy: `commit` (walidacja + dedupe + zapis) bez etapu preview; użytkownik widzi wyłącznie wynik. **[Assumption]**
- Commit importu realizowany asynchronicznie (`queued` → `processing` → `committed|failed`) z aktualizacją statusu realtime (Reverb) i fallbackiem polling. **[Assumption]**

### Architektura importu “per bank” (adaptery)
Cel: umożliwić dodawanie kolejnych banków bez zmian w core importu.

- **Źródło prawdy dla banków**: enum `Bank` (MVP: `BnpParibas`, `MBank`, `Cash`).
- **Resolver parsera**: wybór implementacji na podstawie `Account.bank` (a nie wyboru banku w imporcie).
- **Kontrakt parsera (przykładowo)**:
  - `parse(...)` / `normalizeRow(...)` — mapuje surowy wiersz + mapping na ustandaryzowany rekord transakcji (`date`, `amount`, `description`, `subject`).
  - parser może zawierać reguły specyficzne dla banku (format daty/kwoty, czyszczenie opisu, ekstrakcja `subject`).
- **Dodawanie nowego banku**: dopisanie wartości w enum + nowa implementacja parsera + podpięcie w resolverze + dodanie ikony.
- **Gotówka (`Cash`)**: konto istnieje normalnie; import może być niewspierany albo ograniczony (decyzja implementacyjna), ale architektura powinna to obsłużyć czytelnym komunikatem.

---

## 12. Data Model (produktowo)
Kluczowe encje i relacje:
- **User**
  - 1..N **Accounts**
  - 1..N **Imports** (historia importów)
- **Currency**
  - N..1 do Accounts i Transactions (MVP: PLN jako rekord).
- **Account**
  - należy do User
  - ma Currency, saldo początkowe, saldo bieżące
  - ma: `type` (enum, MVP: `Ror`, `Savings`)
  - ma: `bank` (enum, MVP: `BnpParibas`, `MBank`, `Cash`)
  - ma 0..N Transactions
  - może być „usunięte" (soft delete) → transakcje read-only
- **Transaction**
  - należy do User i do Account
  - ma: `date` (data operacji), `booked_at` (data przypisania do okresu rozliczeniowego, default = `date`)
  - ma: `amount` (decimal 20,2, z ujemnymi dla wydatków), `currency`, `description`, `subject`
  - ma: `type` (`income` / `expense` / `transfer` / `adjustment`)
  - ma: `normalized_description`, `dedupe_hash` (klucz dedupe importu: deterministyczny hash `date + amount + normalized_description`; wpisy ręczne dostają unikalny hash z sufiksem UUID, żeby zachować unique index `(account_id, dedupe_hash)` bez blokowania ręcznych duplikatów)
  - opcjonalnie: `transfer_id` (UUID; dla dwóch transakcji transferu)
  - opcjonalnie: `transfer_match_status` (`none` / `auto` / `manual` / `rejected`) — dla matchera transferów (FR-I6)
  - opcjonalnie: `transfer_candidate_for_id` (FK na inną transakcję) — wskazuje proponowanego partnera transferu, gdy status `manual`
  - metadata importu: `import_id`, `raw_statement_description`
- **Import**
  - należy do User i Account
  - ma status (`draft` / `queued` / `processing` / `committed` / `failed`), liczniki wierszy (`rows_total`, `rows_imported`, `rows_skipped_duplicate`, `rows_failed_validation`), mapowanie kolumn
  - ma `details` (JSON) na metadane techniczne importu (`mapping_used`, `source_file`, `parser`, `bank`, `headers`, `diagnostics`).
- **AccountBalanceAdjustment** (audyt)
  - wpis przy każdej akcji „Ustaw saldo" (kto, kiedy, stare→nowe saldo)

Własność danych, retencja, audyt:
- Własność: wszystko per User.
- Retencja transakcji: brak automatycznej retencji w MVP. **[Assumption]**
- Retencja plików importu: 30 dni dla importów `Failed`; pliki sukcesowe usuwane od razu po commit.
- Audyt:
  - korekty salda → transakcja `adjustment` na liście + wpis `account_balance_adjustments`,
  - linkowanie/unlink transferów → eventy telemetryczne.

---

## 13. Permissions / Roles
Jedna rola: **User**.

| Akcja | User |
|------|------|
| CRUD własnych kont | Tak |
| CRUD własnych transakcji (na aktywnych kontach) | Tak |
| Edycja/usuwanie transakcji z usuniętego konta | Nie |
| Import do własnych aktywnych kont | Tak |
| Podgląd własnych importów | Tak |

Zasady autoryzacji (produktowe):
- Użytkownik ma dostęp wyłącznie do swoich kont, transakcji i importów.
- Transakcje powiązane z usuniętym kontem są read-only.

---

## 14. Release Plan

### MVP (do wdrożenia teraz)
- Auth (email + login + reset hasła).
- Konta: CRUD + saldo + korekta jako transakcja `adjustment` + komenda rekalkulacji salda.
- Transakcje: CRUD + `date` i `booked_at` + lista + filtry/sort/paginacja + podsumowanie (z wykluczeniem transferów).
- Transfer:
  - akcja użytkownika → 2 transakcje powiązane,
  - automatyczne dopasowanie transferów podczas importu (FR-I6) + baner kandydatów na liście transakcji do potwierdzenia niejednoznacznych par.
- Import CSV/XLSX: entrypoint z widoku transakcji + upload + auto-mapowanie (adapter banku) + auto-commit (bez preview, chunked) + dedupe po `date + amount + normalized_description` + ekstrakcja `subject` per bank + pamięć `subject/description` (Typesense, best-effort).
- Realtime status importu (Reverb): `queued → processing → committed|failed` + przyrost liczników; fallback polling.
- Telemetria podstawowa wg sekcji 2 i 7 (kanał loga `telemetry`).
- Rate limiting endpointów importu (10/min per user) + ogólny limiter API (60/min per user).

### Post-MVP (kierunek, bez zobowiązania)
- Kategorie, raporty, eksport, multiwaluta, współdzielenie, integracje.

Feature flags / rollout / migracje:
- Import i transfer mogą być pod flagą, jeśli ryzyko wdrożeniowe wysokie. **[Assumption]**
- Migracje: dodanie tabel walut, importów, powiązań transferu.

Plan komunikacji i supportu (minimum):
- Ekran pomocy importu (krótko: jak wyeksportować plik i jak mapować). **[Assumption]**
- Kanał feedbacku (np. email). **[Assumption]**

---

## 15. Risks & Mitigations
1. **Różnorodność formatów CSV/XLSX banków** → adaptery banków + testy na realnych plikach + wspólny parser kwot/dat/encoding (FR-I4).
2. **False positives w deduplikacji** → wspierane banki nie eksportują unikalnych identyfikatorów transakcji, więc dedupe opieramy na `date+amount+normalized_description`. Akceptowana wada MVP: dwa identyczne zakupy w tym samym dniu (np. dwie kawy w tej samej kawiarni) zostaną zaimportowane raz; manualnie można dodać brakującą drugą. Telemetria `import_rows_skipped_duplicate` daje sygnał skali w razie potrzeby zmiany strategii (np. occurrence-index, soft-duplicate confirm).
3. **Błędy w saldach** (race conditions, edycje) → transakcje DB krótkie + lockForUpdate; komenda `accounts:recalculate-balance` (z `--dry-run`) jako safety net; korekty zapisywane jako transakcje `adjustment`.
4. **Wyciek danych między userami** → konsekwentna autoryzacja per zasób + dedykowane testy izolacji (konta, transakcje, importy, pamięć Typesense).
5. **Decimal i zaokrąglenia** → jedna skala (2 miejsca) i konsekwentne parsowanie/formatowanie (`bcadd`/`bcsub`/`bcmul`).
6. **Ekstrakcja `subject` z opisu** → na MVP `subject` z dedykowanej kolumny mapowania (gdy istnieje) + pamięć Typesense (best-effort) + fallback (puste subject) + telemetria jakości ekstrakcji.
7. **Fałszywe linkowanie transferów** → matcher (FR-I6) automatycznie linkuje tylko gdy: dokładnie 1 kandydatka + opisy zawierają tokeny „transfer". Niejednoznaczne pary trafiają do UI jako `manual`. Status `rejected` zapamiętywany, żeby nie powtarzać propozycji.
8. **Długotrwałe locki przy imporcie dużych plików** → chunked processing, krótkie transakcje DB per chunk, lock konta tylko przy finalnej aktualizacji salda.
9. **Nieczytelne komunikaty błędów dla nieobsługiwanych operacji** (np. import na koncie Cash) → 422 z `message_key` zamiast 500.

---

## 16. Dependencies
- Dostarczenie przykładowych plików CSV/XLSX dla banków (od Ciebie).
- Lista banków wspieranych “na start” (priorytet).
- Dostarczenie ikon banków (assets) dla wartości `bank` w MVP: BNP Paribas, mBank, Gotówka.
- Środowisko wysyłki maili (dev: Mailpit; produkcja: SMTP). **[Assumption]**

---

## 17. Open Questions
Brak pytań blokujących na ten moment.

---

## 18. Appendix

### Cytaty/wycinki (źródła)
- `.docs/mvp.md`: “Rejestracja i logowanie użytkownika; izolacja danych per użytkownik…”
- `.docs/mvp.md`: “Zarządzanie kontami bankowym… walutę domyślną (na MVP: PLN) i saldo początkowe”
- `.docs/mvp.md`: “Zarządzanie operacjami… typ (przychód / wydatek / transfer)… opcjonalnie kontrahenta”
- `.docs/mvp.md`: “Import operacji… CSV/XLSX z mapowaniem kolumn… podglądem… wykrywaniem i pomijaniem duplikatów…”
- `.docs/mvp.md`: Kryteria sukcesu (90% importu, <30s manual, 70% import w 7 dni, 0 wycieków).
- `.docs/tech-stack.md`: Laravel 13 + Inertia v2 + Vue 3 + TS + Vite 6 + Tailwind 3; DB SQLite/MySQL; Pest/PHPUnit; auth sesyjny.

### Conflicts
- Brak bezpośrednich sprzeczności między `.docs/mvp.md` i `.docs/tech-stack.md` wykrytych na poziomie wymagań.
- Potencjalne napięcie: “MVP: PLN” vs “waluta jako wpis w tabeli pod przyszłe waluty” — rozwiązanie: encja waluty + UI ograniczone do PLN w MVP.

---

## Checklist review dla interesariuszy (PM/CEO/Tech/Legal)
- **PM/CEO**: czy MVP scope jest zgodny (bez kategorii/raportów/multiwaluty), czy success metrics są akceptowalne?
- **Tech**: czy model transakcji (kwoty ujemne) i zasady saldo/transfer są jednoznaczne; czy import per bank jest estymowalny.
- **QA**: czy AC Given/When/Then pokrywają krytyczne flow; plan testów izolacji danych i deduplikacji.
- **Legal/Privacy**: czy reset hasła i logowanie nie ujawniają danych; czy logi nie przechowują danych wrażliwych z importu.

