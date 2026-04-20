## Checklist implementacyjna (MVP) — Wallet Master

Cel: zrealizować zakres z `.docs/prd.md` (terminologia: **Konto** / **Transakcja** / **Import** / **Transfer**).

---

### 0) Uruchomienie projektu / baseline
- [ ] Uruchom aplikację lokalnie (Sail albo `composer run dev`) i potwierdź, że Inertia + Vue działa.
- [ ] Ustal konwencję nazw: “Transakcja” (UI) / `Transaction` (model/tabela) / “Konto” / `Account`.
- [ ] Ustal formaty prezentacji:
  - [ ] Data prezentowana jako **DD-MM-YYYY**
  - [ ] Kwoty formatowane pod PL (przecinek dziesiętny w UI)

---

### 1) Model danych + migracje
- [ ] Dodać encję waluty (MVP: rekord `PLN`), żeby w przyszłości dodać kolejne waluty.
- [ ] Dodać encję konta:
  - [ ] `name`
  - [ ] `currency_id`
  - [ ] `opening_balance` (saldo początkowe)
  - [ ] `current_balance` (saldo bieżące — aktualizowane)
  - [ ] soft delete (żeby po “usunięciu konta” transakcje zostawały, ale były read-only)
- [ ] Dodać encję transakcji:
  - [ ] `account_id`, `user_id` (lub inny jednoznaczny mechanizm izolacji)
  - [ ] `date` (data bez czasu)
  - [ ] `amount` jako **decimal** (ujemne dla wydatków, dodatnie dla przychodów)
  - [ ] `type` (income/expense/transfer) — utrzymywane mimo znaku kwoty
  - [ ] `description`
  - [ ] `subject` (nadawca/odbiorca)
  - [ ] `currency_id` (na MVP zawsze PLN, ale pole istnieje)
  - [ ] `transfer_id` (nullable; łączy 2 transakcje transferu)
  - [ ] `import_id` (nullable; powiązanie z importem)
- [ ] Dodać encje importu + mapowania per bank:
  - [ ] `imports`: `user_id`, `account_id`, `bank_key`/`bank_name`, status, liczniki (`rows_total`, `rows_imported`, `rows_skipped_duplicate`, `rows_failed_validation`)
  - [ ] `import_profiles` (per user + bank): zapis mapowania kolumn + wersjonowanie (opcjonalnie)
- [ ] Indeksy:
  - [ ] `transactions(account_id, date)`
  - [ ] `transactions(user_id, date)`
  - [ ] `transactions(transfer_id)`
  - [ ] Indeks/unikalność deduplikacji (jeśli możliwe): `account_id + date + amount + normalized_description` (wymaga pola lub generowanej kolumny)

---

### 2) Autoryzacja i izolacja danych
- [ ] Zaimplementować autoryzację per zasób (konto/transakcja/import) tak, aby użytkownik widział wyłącznie swoje dane.
- [ ] Dodać testy izolacji danych (min. 2 użytkowników, próby odczytu/edycji cudzych zasobów).
- [ ] Upewnić się, że reset hasła nie ujawnia czy email istnieje.

---

### 3) Konta — API/CRUD + UI
- [ ] Lista kont (z walutą i bieżącym saldem).
- [ ] Dodanie konta:
  - [ ] Walidacje: nazwa wymagana; saldo początkowe liczba; waluta wybieralna, ale na MVP dostępna tylko PLN.
- [ ] Edycja konta:
  - [ ] Zmiana nazwy, salda początkowego.
  - [ ] Zdefiniować wpływ zmiany salda początkowego na `current_balance` (rekomendacja: przeliczyć różnicą). 
- [ ] Usuwanie konta:
  - [ ] Soft delete konta.
  - [ ] Zablokować edycję/usuwanie transakcji na usuniętym koncie (UI + backend).
  - [ ] Zablokować import i transfer dla usuniętego konta (UI + backend).
- [ ] Korekta salda:
  - [ ] Akcja “Ustaw saldo” (manual adjustment).
  - [ ] Audit trail minimalny (kto/kiedy/stara→nowa wartość).

---

### 4) Transakcje — API/CRUD + UI
- [ ] Lista transakcji:
  - [ ] Filtry: konto, zakres dat
  - [ ] Sort: data/kwota
  - [ ] Paginacja (backend)
  - [ ] Podsumowanie zakresu: suma wpływów i wydatków (oddzielnie)
  - [ ] Empty state + CTA
- [ ] Dodanie transakcji:
  - [ ] Pola: data (DD-MM-YYYY), kwota (decimal), opis, subject (opcjonalny)
  - [ ] Ustalenie typu na podstawie znaku kwoty (ujemna=wydatek, dodatnia=przychód)
  - [ ] Walidacje: kwota != 0; konto nieusunięte
  - [ ] Aktualizacja `current_balance` konta deltą kwoty
- [ ] Edycja transakcji:
  - [ ] Zmiana pól i przeliczenie delty salda (stara kwota → nowa kwota)
  - [ ] Blokada edycji dla transakcji na usuniętym koncie
- [ ] Usuwanie transakcji:
  - [ ] Aktualizacja salda deltą (odwrócenie wpływu)
  - [ ] Blokada usuwania dla transakcji na usuniętym koncie

---

### 5) Transfer — jedna akcja → 2 transakcje
- [ ] UI “Transfer”:
  - [ ] Konto źródłowe != konto docelowe
  - [ ] Użytkownik wpisuje kwotę dodatnią, system zapisuje `-X` i `+X` (walidacja wejścia)
  - [ ] Data wspólna
  - [ ] Opis (wspólny) + subject (opcjonalny)
- [ ] Backend:
  - [ ] Utworzyć 2 transakcje w jednej transakcji DB
  - [ ] Ustawić `transfer_id` (wspólny identyfikator)
  - [ ] Zaktualizować saldo obu kont
- [ ] Blokady:
  - [ ] Transfer do/z usuniętego konta niedozwolony

---

### 6) Import — flow: upload → mapowanie → preview → commit

#### 6.1 Kontrakty i UI flow
- [ ] Ekran wyboru konta + banku.
- [ ] Upload CSV/XLSX.
- [ ] Ekran mapowania kolumn:
  - [ ] Wymagane: data, kwota, opis, subject
  - [ ] Zapisywanie mapowania per user + bank (profil)
- [ ] Ekran preview:
  - [ ] Lista/widok próbki danych + podsumowanie: `rows_total`, `rows_skipped_duplicate`, `rows_failed_validation`, `rows_will_import`
  - [ ] Informacja, że duplikaty zawsze będą pominięte
- [ ] Commit importu:
  - [ ] Zapis tylko poprawnych, nie-duplikatowych wierszy
  - [ ] Podsumowanie końcowe

#### 6.2 Parsowanie i walidacja
- [ ] CSV:
  - [ ] Obsłużyć separator `;` i `,` (minimum) **[Assumption]**
  - [ ] Obsłużyć kwoty z `,` i `.` (wejście)
- [ ] XLSX:
  - [ ] Importować pierwszy arkusz
- [ ] Data:
  - [ ] Parsowanie do formatu daty (prezentacja DD-MM-YYYY; storage jako date)
- [ ] Kwota:
  - [ ] Ujemne kwoty → wydatek, dodatnie → przychód (ustawić `type`)
  - [ ] Kwota 0 → błąd walidacji
- [ ] `subject`:
  - [ ] Ekstrakcja per bank (adaptery) z `description` lub innych pól, jeśli bank tego wymaga
  - [ ] Fallback: pozostawić puste, jeśli nie da się wyciągnąć **[Assumption]**

#### 6.3 Deduplikacja (zawsze pomijamy)
- [ ] Zaimplementować normalizację opisu:
  - [ ] `trim`
  - [ ] `case-fold` (np. lowercase)
  - [ ] standaryzacja whitespace (wielokrotne spacje → jedna)
- [ ] Duplikat = identyczne: `date + amount + normalized_description` na tym samym `account_id`
- [ ] Preview i commit używają tej samej logiki dedupe (żadnych rozjazdów).

#### 6.4 Salda po imporcie
- [ ] Dla każdego zaimportowanego wiersza zastosować aktualizację `current_balance` deltą `amount`.
- [ ] Zabezpieczyć przed podwójnym zapisem (idempotencja importu przez `import_id` + dedupe).

---

### 7) Adaptery banków + profile mapowań
- [ ] Zdefiniować interfejs “BankAdapter”:
  - [ ] identyfikator banku (`bank_key`)
  - [ ] ekstrakcja `subject`
  - [ ] ewentualne pre-processing `description` (tylko jeśli wymagane)
- [ ] Mechanizm rejestracji adapterów + fallback “Generic”.
- [ ] Model “ImportProfile” per user + bank:
  - [ ] przechowywanie mapowania kolumn
  - [ ] możliwość nadpisania / aktualizacji

---

### 8) Telemetria / eventy (min.)
- [ ] Auth: `user_registered`, `user_logged_in`, `user_login_failed`
- [ ] Konta: `account_created`, `account_updated`, `account_deleted`, `account_deleted_with_transactions`, `account_balance_adjusted`
- [ ] Transakcje: `transaction_create_opened`, `transaction_created`, `transaction_updated`, `transaction_deleted`
- [ ] Lista: `transactions_filtered`, `transactions_sorted`, `transactions_page_changed`
- [ ] Transfer: `transfer_created`, `transfer_failed_validation`
- [ ] Import: `import_started`, `import_preview_generated`, `import_completed`, `import_failed`, `import_bank_selected`, `import_mapping_saved`, `import_mapping_reused`, `import_type_inferred`

---

### 9) UX jakościowe (do odhaczenia)
- [ ] Wszystkie formularze mają:
  - [ ] walidację inline
  - [ ] czytelne komunikaty błędów po polsku
  - [ ] stany loading/disabled podczas requestów
- [ ] Empty states (konta/transakcje/import) mają jasne CTA.
- [ ] A11y:
  - [ ] pełna obsługa klawiaturą
  - [ ] focus states widoczne
  - [ ] poprawne `label`/aria
  - [ ] kontrast WCAG AA

---

### 10) Testy (Pest) + QA checklist

#### 10.1 Testy automatyczne (minimum)
- [ ] Autoryzacja i izolacja danych (konto/transakcja/import).
- [ ] CRUD kont:
  - [ ] tworzenie/edycja/usunięcie (soft delete)
  - [ ] transakcje na usuniętym koncie są read-only
- [ ] CRUD transakcji:
  - [ ] saldo aktualizuje się o deltę kwoty przy create/update/delete
- [ ] Transfer:
  - [ ] tworzy 2 transakcje z `transfer_id`
  - [ ] aktualizuje saldo obu kont
- [ ] Import:
  - [ ] walidacja wymaganych pól
  - [ ] deduplikacja po normalizacji opisu
  - [ ] liczniki `rows_*` poprawne

#### 10.2 Manual QA (minimum)
- [ ] “Happy path” rejestracja → konto → transakcja → filtr → import → transfer.
- [ ] Import: plik z samymi duplikatami → komunikat i `rows_imported=0`.
- [ ] Usunięte konto:
  - [ ] transakcje widoczne
  - [ ] brak możliwości edycji/usuwania
  - [ ] brak możliwości importu/transferu

---

### 11) Quality gates przed merge
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `php artisan test --compact` (uruchomić testy dotknięte zmianami)
- [ ] `npm run lint` + `npm run format` (jeśli dotyczy)
- [ ] Brak logowania wrażliwych danych importu w produkcji (przegląd logów/handlerów).

