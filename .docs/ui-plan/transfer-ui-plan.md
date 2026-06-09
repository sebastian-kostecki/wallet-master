## Widok: Transfer między kontami (Transakcje → Transfer)

### 1. Założenia i zakres strony

#### Założenia
- Transfer to **jedna akcja**, która tworzy **2 transakcje powiązane**:
  - konto źródłowe: kwota \( -X \)
  - konto docelowe: kwota \( +X \)
- Użytkownik w formularzu podaje **kwotę dodatnią** (system zapisuje znaki). **[Assumption]**
- Data obu transakcji transferu jest **identyczna** (jedno pole daty w UI).
- Format daty w UI: **DD-MM-YYYY**.
- Prezentacja kwoty: format PL (przecinek), input toleruje `,` i `.`.

#### In-scope (MVP)
- Formularz wykonania transferu (konto źródłowe, konto docelowe, kwota, data, opis opcjonalnie).
- Inline validation (w tym „źródło ≠ cel”, kwota > 0, poprawna data).
- Blokada transferu do/z **usuniętego konta** (konto soft-deleted) zgodnie z PRD.
- Stany: loading / error / sukces.
- Telemetria: `transfer_created`, `transfer_failed_validation`.

#### Out-of-scope (MVP)
- Przelewy zewnętrzne (banki), odbiorcy, tytuły przelewów, potwierdzenia bankowe.
- Opłaty/prowizje transferu, przewalutowania i kursy.
- Szablony transferów, transfery cykliczne, bulk/masowe operacje.
- Edycja istniejącego transferu jako „jednej encji” (UI dotyczy tworzenia; edycja pojedynczych transakcji transferu zależnie od reguł backendu).

#### Miejsce w nawigacji
- Sekcja: **Transakcje**.
- Wejście: główne CTA na liście transakcji (np. obok „Dodaj transakcję”): **„Transfer”** lub w menu „Dodaj”.
- Powrót po sukcesie: do **Listy transakcji** z zachowanymi filtrami (query string), analogicznie jak create/edit transakcji.

---

### 2. Information Architecture

#### Sekcje strony (priorytetowo)
1) Nagłówek widoku + kontekst (krótki opis).
2) Formularz transferu.
3) Podgląd skutków (opcjonalnie, w MVP jako mini-sekcja „Zaktualizuje salda obu kont” + ostrzeżenia walidacyjne; bez wyliczeń „po” jeśli brak danych).
4) Akcje: primary „Wykonaj transfer”, secondary „Anuluj”.

#### Nawigacja i główne CTA
- Primary CTA: **„Wykonaj transfer”**.
- Secondary: **„Anuluj”** (wraca do listy transakcji).
- Link pomocniczy (jeśli brak kont): **„Dodaj konto”** (prowadzi do Konta → Dodaj).

---

### 3. Wireframe w tekście (desktop + mobile)

#### Desktop
- **Topbar**
  - Breadcrumb: „Transakcje / Transfer”
  - Tytuł: „Transfer”
  - Krótki opis: „Przenieś środki między własnymi kontami. Utworzymy dwie powiązane transakcje.”
- **Card: Formularz**
  - Rząd 1 (2 kolumny):
    - Select „Konto źródłowe”
    - Select „Konto docelowe”
  - Rząd 2 (2 kolumny):
    - Input „Kwota”
    - Input „Data” (DD-MM-YYYY)
  - Rząd 3 (1 kolumna):
    - Input „Opis” (opcjonalnie)
  - **Inline errors** bezpośrednio pod polami + błąd formularza (jeśli dotyczy całości).
- **Footer akcji**
  - Button secondary: „Anuluj”
  - Button primary: „Wykonaj transfer”
  - Stan submit: spinner + disable

#### Mobile
- Układ jednokolumnowy:
  - Tytuł + opis
  - Select „Konto źródłowe”
  - Select „Konto docelowe”
  - Input „Kwota”
  - Input „Data”
  - Input „Opis” (opcjonalnie)
  - Sticky (opcjonalnie) pasek akcji na dole: „Anuluj” / „Wykonaj transfer”

Co się zwija/ukrywa na mobile:
- Breadcrumb może być zastąpiony pojedynczym tytułem + „Wstecz”.

---

### 4. Komponenty UI (re-używalne)

#### Formularz / pola
- `PageHeader`: tytuł + opis + (opcjonalnie) breadcrumb.
- `FormCard`: kontener formularza (card).
- `SelectField`:
  - warianty: normalny, disabled (konto usunięte / brak opcji), error
  - wsparcie wyszukiwania w select (nice-to-have; w MVP opcjonalne)
- `MoneyInput`:
  - toleruje `,` i `.`, formatuje prezentację PL po blur
  - warianty: normal/error/disabled
- `DateInput`:
  - maska/placeholder `DD-MM-YYYY`
  - walidacja formatu i sensowności daty
- `TextInput` dla opisu (opcjonalnie z limitem znaków wg backendu).
- `InlineError`: komunikat pod polem.
- `FormErrorSummary` (opcjonalnie): błąd ogólny (np. sieć/500).

#### Stany i feedback
- `LoadingSkeleton` (jeśli dane kont ładowane asynchronicznie).
- `InlineNotice` (info o tym, co zrobi transfer).
- `Toast`/`FlashMessage` (sukces / błąd zapisu).

#### Ikony (Lucide) — propozycja
- `ArrowRightLeft` / `ArrowLeftRight`: nagłówek transferu.
- `AlertTriangle`: ostrzeżenia walidacyjne / konto usunięte.
- `CheckCircle2`: sukces.

---

### 5. Stany i edge-case UX (must-have)

#### Loading
- Jeśli lista kont jest propsem od backendu: podczas wejścia zwykle brak ładowania.
- Jeśli przeładowania Inertia: zablokować submit, pokazać `aria-busy` na formularzu.
- Skeleton/placeholder w selectach, jeśli `accounts[]` jeszcze niegotowe.

#### Empty
- Brak kont: ekran/sekcja empty state:
  - komunikat: „Aby wykonać transfer, dodaj co najmniej dwa konta.”
  - CTA: „Dodaj konto”
- Tylko jedno konto: podobny komunikat + CTA „Dodaj konto”.

#### Error
- 422 (walidacja): inline przy polach + ewentualnie błąd formularza.
- 403 (autoryzacja) / 404 (zasób nie istnieje): komunikat + przycisk „Wróć do transakcji”.
- 500 / błąd sieci: banner/toast „Nie udało się wykonać transferu. Spróbuj ponownie.”

#### Walidacja inline (kluczowe reguły)
- Konto źródłowe:
  - wymagane
  - jeśli konto usunięte → pole disabled + komunikat „To konto jest usunięte — transfer z niego jest zablokowany.”
- Konto docelowe:
  - wymagane
  - jeśli konto usunięte → pole disabled + komunikat analogiczny
- Źródło = cel:
  - blokada submit
  - błąd przy obu polach lub przy docelowym: „Wybierz inne konto docelowe niż źródłowe.”
- Kwota:
  - wymagane
  - musi być \(> 0\)
  - jeśli użytkownik wpisze `-` lub ujemną wartość → błąd: „Podaj kwotę dodatnią.”
- Data:
  - wymagane
  - format DD-MM-YYYY i poprawna data kalendarzowa

#### Edge-case’y z PRD
- Usunięte konto źródłowe/docelowe: **blokada** (disable opcji lub oznaczenie + brak możliwości wyboru).
- Kwota ujemna w formularzu: **walidacja** + event `transfer_failed_validation`. **[Assumption]**

---

### 6. Copy (PL) — gotowe teksty

#### Nagłówki i opisy
- Tytuł: „Transfer”
- Opis: „Przenieś środki między własnymi kontami. Utworzymy dwie powiązane transakcje z tą samą datą.”

#### Etykiety pól
- „Konto źródłowe”
- „Konto docelowe”
- „Kwota”
- „Data”
- „Opis (opcjonalnie)”

#### Placeholdery / pomoc
- Select (gdy brak wyboru): „Wybierz konto”
- Kwota placeholder: „np. 120,50”
- Data placeholder: „DD-MM-YYYY”
- Hint pod formularzem (opcjonalnie): „Zapiszemy wydatek na koncie źródłowym i przychód na koncie docelowym.”

#### CTA
- Primary: „Wykonaj transfer”
- Secondary: „Anuluj”

#### Komunikaty walidacyjne
- Wymagane pole (select/input): „To pole jest wymagane.”
- Źródło = cel: „Wybierz inne konto docelowe niż źródłowe.”
- Kwota ≤ 0: „Podaj kwotę dodatnią.”
- Zły format daty: „Podaj datę w formacie DD-MM-YYYY.”
- Konto usunięte: „To konto jest usunięte — transfer jest zablokowany.”

#### Sukces / błąd
- Sukces (toast/flash): „Transfer zapisany.”
- Błąd ogólny: „Nie udało się wykonać transferu. Spróbuj ponownie.”

---

### 7. A11y checklist

- Kolejność fokusu: nagłówek → konto źródłowe → konto docelowe → kwota → data → opis → Anuluj → Wykonaj transfer.
- Każde pole ma widoczny `label` (placeholder nie zastępuje etykiety).
- Błędy:
  - `aria-invalid="true"` na polach z błędem
  - `aria-describedby` wskazuje element z treścią błędu
  - błąd „źródło = cel” musi być jednoznacznie ogłoszony (np. przypięty do pola docelowego + ogólny komunikat).
- Selecty i przyciski w pełni obsługiwalne klawiaturą (Tab/Shift+Tab/Enter/Space, strzałki w liście opcji).
- Widoczny focus ring i kontrast WCAG AA dla stanów disabled/error.
- Podczas submit: obszar formularza ma `aria-busy="true"`, a przycisk primary ma stan loading z tekstem (np. „Zapisywanie…”).

---

### 8. Sugestia implementacji w stacku (bez pisania kodu)

#### Podział na pliki (Inertia + Vue)
- Strona: `resources/js/pages/Transactions/TransferCreate.vue` (lub spójnie z istniejącą strukturą „Transakcje”).
- Komponenty:
  - `resources/js/components/forms/TransferForm.vue` (formularz + walidacja UI)
  - współdzielone: `MoneyInput`, `DateInput`, `SelectField`, `InlineError`, `PageHeader`

#### Kontrakty/propsy z backendu (minimalnie)
- `accounts[]`: `{ id, name, is_deleted? }` (albo `deleted_at` / flag) do budowy opcji i blokad.
- Opcjonalnie: `defaultDate` (np. dziś) lub `today` do ustawienia domyślnej daty.
- Flash messages dla sukcesu/błędu (Inertia shared props).

#### Akcje Inertia
- `GET` render strony transferu (pobiera konta użytkownika; wyklucza/oznacza usunięte).
- `POST` submit transferu:
  - payload: `source_account_id`, `destination_account_id`, `amount` (dodatnia), `date`, `description?`
  - po sukcesie: redirect na listę transakcji (zachować query string jeśli był przekazany) + flash „Transfer zapisany.”
  - po 422: błędy wracają jako errors (Inertia) do inline display

#### Wskazówki Tailwind (kluczowe decyzje)
- Desktop: formularz jako card max-width (np. `max-w-2xl`) z układem grid 2 kolumny dla par pól.
- Mobile: jedna kolumna, akcje na dole; primary przycisk pełnej szerokości.
- Spójne odstępy: sekcje `space-y-6`, pola `gap-4`, akcje wyrównane do prawej na desktop.

---

### Otwarte pytania (krytyczne)
1) Czy po sukcesie transferu użytkownik ma wracać na listę transakcji, czy zostać na formularzu z wyczyszczonymi polami (z opcją „Wykonaj kolejny”)?  
2) Czy w UI mamy pokazywać salda kont przed transferem (tylko podgląd), czy to out-of-scope dla MVP?  
3) Czy „Opis” ma trafiać do obu transakcji identycznie, czy np. z prefiksem „Transfer: …” (wymóg spójności historii)?  
4) Czy konta usunięte mają być całkiem ukryte w selectach, czy widoczne jako disabled (dla lepszego wyjaśnienia blokady)?  
5) Czy transfer ma wymagać potwierdzenia (modal „Czy na pewno?”) dla dużych kwot, czy MVP bez potwierdzeń?

