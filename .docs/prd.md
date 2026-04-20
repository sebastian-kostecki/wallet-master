# PRD v1 — Wallet Master (MVP)

## Glossary (spójne pojęcia)
- **Konto**: konto bankowe użytkownika w aplikacji (nazwa, waluta, saldo).
- **Transakcja**: pojedynczy zapis finansowy przypisany do konta (przychód/wydatek lub element transferu).
- **Transfer**: akcja tworząca **2 transakcje** (wydatek na koncie źródłowym + przychód na koncie docelowym) powiązane ze sobą.
- **Import**: proces wczytania transakcji z pliku CSV/XLSX z mapowaniem kolumn i podglądem.
- **Mapowanie (kolumn)**: przypisanie kolumn pliku do pól transakcji (data, kwota, opis, subject).
- **Subject**: pole tekstowe “nadawca/odbiorca” przechowywane osobno od opisu.
- **Duplikat (importu)**: transakcja o tej samej dacie, kwocie i **znormalizowanym** opisie na tym samym koncie.
- **Aktywny użytkownik**: użytkownik, który wykonał min. 1 akcję produktową (np. import lub dodanie transakcji) w ostatnich 7 dniach. **[Assumption]**

---

## 1. Overview
Budujemy webową aplikację do zarządzania budżetem domowym: użytkownik tworzy konta, dodaje/ogląda transakcje i importuje je z wyciągów bankowych (CSV/XLSX). MVP ma dać szybki “time-to-value”: sprawne dodawanie transakcji ręcznie oraz wysoki sukces importu z minimalną korektą.

Źródło wymagań produktu: `.docs/mvp.md` (MVP scope, sukces, persona) + `.docs/tech-stack.md` (stack i ograniczenia).

---

## 2. Goals / Success Metrics

### Cele (mierzalne)
- Użytkownik może samodzielnie: zarejestrować się, dodać konto, dodać transakcję, przejrzeć i przefiltrować historię.
- Użytkownik może zaimportować historię transakcji z CSV/XLSX z mapowaniem kolumn i podglądem.
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
5. Import CSV/XLSX z mapowaniem + podglądem + deduplikacją (Must)
6. Transfer między kontami (Must)
7. Reset hasła (Should)

---

## 6. User Journeys

### Journey A — Pierwsze uruchomienie i ręczne dodanie transakcji (happy path)
Rejestracja → utworzenie konta → “Dodaj transakcję” → zapis → transakcja widoczna na liście → saldo konta zaktualizowane.

**Alternatywy (krytyczne)**
- Walidacja błędów (np. brak daty/kwoty) → inline error → użytkownik poprawia → zapis.

### Journey B — Import wyciągu z banku (happy path)
Wejście w Import → wybór konta → upload CSV/XLSX → wybór banku i mapowanie kolumn → preview → zapis importu → duplikaty pominięte → lista transakcji uzupełniona → saldo zaktualizowane.

**Alternatywy (krytyczne)**
- Plik ma niepoprawne dane → błędy walidacji w preview → użytkownik poprawia mapowanie lub przerywa import.
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
    When doda konto z nazwą, walutą=PLN, saldem początkowym  
    Then konto pojawia się na liście, a saldo jest ustawione.
  - Given konto użytkownika  
    When edytuje nazwę/saldo początkowe  
    Then zmiany zapisują się, a saldo bieżące jest aktualizowane zgodnie z zasadami (FR-S1, FR-T1).
- **Edge cases**
  - Nazwa pusta/zbyt długa.
  - Waluta w MVP ograniczona do PLN w UI, ale istnieje jako encja (tabela) dla przyszłej rozbudowy. **[Assumption]**
- **Telemetry/Events**
  - `account_created`, `account_updated`, `account_deleted`

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
    When użytkownik doda transakcję z datą, kwotą, opisem, subject (opcjonalnie)  
    Then transakcja pojawia się na liście i wpływa na saldo (ujemna kwota zmniejsza saldo, dodatnia zwiększa).
  - Given istniejąca transakcja  
    When użytkownik edytuje jej pola  
    Then zmiany zapisują się i saldo jest zaktualizowane.
- **Edge cases**
  - Transakcja na usuniętym koncie: brak możliwości edycji/usuwania.
  - Kwota = 0: niedozwolona. **[Assumption]**
  - Data w przyszłości: dozwolona. **[Assumption]**
- **Telemetry/Events**
  - `transaction_created`, `transaction_updated`, `transaction_deleted`

#### FR-T2 Lista transakcji + filtry/sort/paginacja + podsumowanie
- **Opis**: lista z filtrowaniem po koncie i przedziale dat, sort po dacie/kwocie, paginacja, suma wpływów i wydatków w okresie.
- **Priorytet**: Must
- **Acceptance Criteria (Given/When/Then)**
  - Given lista transakcji  
    When użytkownik ustawi filtr dat i konto  
    Then widzi tylko pasujące transakcje oraz podsumowanie wpływów i wydatków dla tego zakresu.
- **Edge cases**
  - Brak wyników: empty state.
  - Zakres dat od>do: walidacja.
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
- **Opis**: saldo jest przechowywane i aktualizowane przy zmianach transakcji; dopuszczalna ręczna korekta.
- **Priorytet**: Must
- **Acceptance Criteria (Given/When/Then)**
  - Given saldo konta  
    When użytkownik doda/zmieni/usunie transakcję  
    Then saldo konta aktualizuje się o różnicę kwoty (delta).
  - Given użytkownik wprowadzi korektę salda  
    When zapisze korektę  
    Then saldo zostaje ustawione na nową wartość i zdarzenie jest audytowane.
- **Edge cases**
  - Korekta salda vs historia transakcji: korekta działa jako “ustaw saldo na wartość”, bez modyfikowania transakcji. **[Assumption]**
- **Telemetry/Events**
  - `account_balance_adjusted` (stare→nowe, reason opcjonalny) **[Assumption]**

**Options + Recommendation + Rationale**
- **Opcja 1**: saldo wyliczane z transakcji przy każdym odczycie.
- **Opcja 2**: saldo zapisywane i aktualizowane przy zmianach (z transakcjami DB).
- **Recommendation**: Opcja 2
- **Rationale**: wydajniejsze listy i podsumowania; mniej kosztownych zapytań.

---

### 7.5 Import (CSV/XLSX)

#### FR-I1 Import pliku z mapowaniem kolumn i preview
- **Opis**: użytkownik uploaduje CSV/XLSX, mapuje kolumny do pól: data, kwota, opis, subject; widzi preview przed zapisem.
- **Priorytet**: Must
- **Acceptance Criteria (Given/When/Then)**
  - Given użytkownik wybrał konto  
    When załaduje plik i zmapuje wymagane kolumny  
    Then widzi preview i może uruchomić import.
- **Edge cases**
  - Błędny format pliku: czytelny błąd.
  - Różne formaty dat/kwot: walidacja + komunikat.
  - XLSX: importujemy pierwszy arkusz. **[Assumption]**
- **Telemetry/Events**
  - `import_started`, `import_preview_generated`, `import_completed`, `import_failed`

#### FR-I2 Wyliczanie typu na podstawie znaku kwoty + przechowywanie kwot ujemnych
- **Opis**: typ transakcji jest determinowany znakiem kwoty (ujemna=wydatek, dodatnia=przychód), a kwota jest zapisywana w DB z tym znakiem (ujemna dla wydatków).
- **Priorytet**: Must
- **Acceptance Criteria (Given/When/Then)**
  - Given wiersz z kwotą ujemną  
    When preview/import  
    Then transakcja ma typ=wydatek i kwota jest zapisana jako ujemna.
  - Given wiersz z kwotą dodatnią  
    When preview/import  
    Then transakcja ma typ=przychód i kwota jest zapisana jako dodatnia.
- **Edge cases**
  - Kwoty w nawiasach (format księgowy): traktować jako ujemne. **[Assumption]**
- **Telemetry/Events**
  - `import_type_inferred`

#### FR-I3 Deduplikacja: zawsze pomijamy duplikaty
- **Opis**: system wykrywa duplikaty na tym samym koncie po dacie, kwocie i znormalizowanym opisie i zawsze je pomija.
- **Priorytet**: Must
- **Acceptance Criteria (Given/When/Then)**
  - Given w pliku jest wiersz odpowiadający istniejącej transakcji (duplikat)  
    When import  
    Then wiersz jest pominięty, a w podsumowaniu importu rośnie licznik `rows_skipped_duplicate`.
- **Edge cases**
  - Normalizacja opisu: tylko `trim` + `case-fold` + standaryzacja whitespace (wielokrotne spacje → jedna). (Twoja decyzja)
- **Telemetry/Events**
  - `import_rows_skipped_duplicate` (agregat)

#### FR-I4 Mapowanie per bank + “klasy banków” (adaptery)
- **Opis**: użytkownik może zapisać i ponownie użyć mapowania “per bank”; system ma przewidziane miejsce na logikę bankowo-specyficzną (np. ekstrakcja `subject` z opisu).
- **Priorytet**: Must
- **Acceptance Criteria (Given/When/Then)**
  - Given użytkownik importował wcześniej z banku X  
    When wybierze bank X  
    Then mapowanie podpowiada się automatycznie.
- **Edge cases**
  - Zmiana formatu pliku banku: możliwość aktualizacji mapowania.
- **Telemetry/Events**
  - `import_bank_selected`, `import_mapping_reused`, `import_mapping_saved`

**Options + Recommendation + Rationale**
- **Opcja 1**: tylko “szablony mapowania” w DB (konfigurowalne przez usera).
- **Opcja 2**: “bank adapters” w kodzie + możliwość zapisu mapowania (hybryda).
- **Recommendation**: Opcja 2
- **Rationale**: wspiera ekstrakcję `subject` i ułatwia utrzymanie wielu formatów, bez blokowania importu.

---

## 8. Information Architecture & Navigation

### Ekrany/strony
- **Auth**: Logowanie, Rejestracja, Reset hasła.
- **Konta**: Lista kont, Dodaj konto, Edytuj konto.
- **Transakcje**: Lista transakcji (filtry/sort/paginacja/podsumowanie), Dodaj transakcję, Edytuj transakcję, Dodaj transfer.
- **Import**: Wybór konta + banku, Upload pliku, Mapowanie, Preview, Podsumowanie importu.

### Mapa nawigacji
- Konta
- Transakcje
- Import
- (opcjonalnie) Ustawienia profilu **[Assumption]**

---

## 9. UX/UI Requirements
- **Język UI**: polski.
- **i18n**: architektura powinna uwzględniać możliwość i18n w przyszłości (np. formatowanie dat/kwot przez warstwę formatterów), bez pełnego wdrożenia w MVP. **[Assumption]**
- **Format daty**: **DD-MM-YYYY**.
- **Format kwoty**: zgodny z PL (przecinek dziesiętny w prezentacji), z tolerancją wejścia `,` i `.` w polu formularza. **[Assumption]**
- **Walidacja**: inline + jasne komunikaty; błędy importu wskazują, które pola są niepoprawne.
- **Empty states**: brak kont → CTA “Dodaj konto”; brak transakcji → CTA “Dodaj transakcję” i “Zaimportuj plik”.
- **Loading states**: import preview i zapis importu — loader/skeleton + licznik wierszy.
- **A11y baseline**: obsługa klawiaturą, widoczne focus states, kontrast WCAG AA, poprawne etykiety (label/aria).
- **Copy tone**: krótko, rzeczowo; podsumowanie importu: “Zaimportowano X, pominięto duplikaty Y, błędne wiersze Z”.

---

## 10. Non-Functional Requirements
- **Performance**
  - Lista transakcji: paginacja backendowa; filtry po koncie i dacie zoptymalizowane indeksami. **[Assumption]**
  - Import: akceptowalny czas dla MVP; przy dużych plikach przewidzieć progres lub asynchroniczność. **[Assumption]**
- **Security & privacy**
  - OWASP baseline: CSRF, XSS, hardening auth, rate limiting logowania i resetu hasła. **[Assumption]**
  - Izolacja danych: autoryzacja per zasób (konto/transakcja/import).
  - Nie logować surowych plików importu ani pełnych wierszy danych w logach produkcyjnych. **[Assumption]**
- **Observability**
  - Logi aplikacyjne + metryki produktowe (events z PRD).
  - Minimalny audit trail korekt salda i usunięć kont. **[Assumption]**
- **Backup/DR**
  - Backup DB zależny od środowiska wdrożenia (wymóg operacyjny, poza implementacją MVP). **[Assumption]**

---

## 11. Tech Constraints & Architecture Notes
- Backend: Laravel 13 + Inertia Laravel v2, auth sesyjny (`App\Http\Controllers\Auth\*`).
- Frontend: Vue 3 + TypeScript, Inertia Vue v2, Vite 6, Tailwind 3.
- DB: domyślnie SQLite, z Sail typowo MySQL.
- Queue: domyślnie `database` (opcjonalnie Redis).
- Testy/jakość: Pest 4 / PHPUnit 12, Pint, ESLint/Prettier, Larastan.

Integracje zewnętrzne: brak (import plików tylko lokalny upload).

Wymagania dot. kontraktów (na poziomie PRD):
- Import rekomendowany jako proces 2-etapowy: `preview` (walidacja + dedupe) → `commit` (zapis). **[Assumption]**

---

## 12. Data Model (produktowo)
Kluczowe encje i relacje:
- **User**
  - 1..N **Accounts**
  - 1..N **Imports** (historia importów) **[Assumption]**
- **Currency**
  - N..1 do Accounts i Transactions (MVP: PLN jako rekord).
- **Account**
  - należy do User
  - ma Currency, saldo początkowe, saldo bieżące
  - ma 0..N Transactions
  - może być “usunięte” (soft delete) → transakcje read-only
- **Transaction**
  - należy do User (bezpośrednio i/lub przez konto) **[Assumption]**
  - należy do Account
  - ma: date, amount (z ujemnymi dla wydatków), currency, description, subject
  - ma: type (income/expense/transfer) **[Assumption]** (utrzymywane dla filtrowania i podsumowań mimo znaku kwoty)
  - opcjonalnie: `transfer_id` (dla dwóch transakcji transferu)
  - metadata importu: `import_id` **[Assumption]**
- **Import**
  - należy do User i Account
  - ma status, liczniki wierszy (imported/skipped/failed), bank, mapowanie.
- **Bank / ImportProfile**
  - per-user zapis mapowań “per bank” (bank + mapping + wersja). **[Assumption]**

Własność danych, retencja, audyt:
- Własność: wszystko per User.
- Retencja: brak automatycznej retencji w MVP. **[Assumption]**
- Audyt: korekty salda i usunięcia konta powinny być audytowane (kto/kiedy/co). **[Assumption]**

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
- Konta: CRUD + saldo + korekta.
- Transakcje: CRUD + lista + filtry/sort/paginacja + podsumowanie.
- Transfer: jedna akcja → 2 transakcje powiązane.
- Import CSV/XLSX: upload + mapowanie + preview + commit + dedupe + mapowania per bank + ekstrakcja `subject` per bank.
- Telemetria podstawowa wg sekcji 2 i 7.

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
1. **Różnorodność formatów CSV/XLSX banków** → adaptery banków + mapowanie w UI + testy na realnych plikach.
2. **False positives w deduplikacji** (normalizacja jest minimalna) → jasne zasady normalizacji + możliwość rozbudowy per bank (np. czyszczenie referencji) post-MVP.
3. **Błędy w saldach** (race conditions, edycje) → transakcje DB, testy, ewentualne narzędzie do rekalkulacji salda. **[Assumption]**
4. **Wyciek danych między userami** → konsekwentna autoryzacja per zasób + testy izolacji.
5. **Decimal i zaokrąglenia** → jedna skala (2 miejsca) i konsekwentne parsowanie/formatowanie.
6. **Ekstrakcja `subject` z opisu** → reguły per bank + fallback (puste subject) + telemetria jakości ekstrakcji. **[Assumption]**

---

## 16. Dependencies
- Dostarczenie przykładowych plików CSV/XLSX dla banków (od Ciebie).
- Lista banków wspieranych “na start” (priorytet).
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

