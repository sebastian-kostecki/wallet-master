<div align="center">

# Wallet Master

**Osobisty budżet domowy — konta, import wyciągów bankowych, kategorie i koperty oszczędnościowe.**

[![Status: przedpremierowy](https://img.shields.io/badge/Status-przed_wydaniem-orange?style=for-the-badge)](#status-projektu)
[![Testy](https://img.shields.io/github/actions/workflow/status/sebastian-kostecki/wallet-master/tests.yml?branch=master&style=for-the-badge&label=Testy&logo=githubactions&logoColor=white)](../../actions/workflows/tests.yml)
[![Lint](https://img.shields.io/github/actions/workflow/status/sebastian-kostecki/wallet-master/lint.yml?branch=master&style=for-the-badge&label=Lint&logo=githubactions&logoColor=white)](../../actions/workflows/lint.yml)
[![Licencja: MIT](https://img.shields.io/badge/Licencja-MIT-blue?style=for-the-badge)](LICENSE)

[![PHP 8.5](https://img.shields.io/badge/PHP_8.5-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![Laravel 13](https://img.shields.io/badge/Laravel_13-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com/)
[![Inertia 2](https://img.shields.io/badge/Inertia_2-9553E9?style=for-the-badge&logo=inertia&logoColor=white)](https://inertiajs.com/)
[![Vue 3](https://img.shields.io/badge/Vue_3-4FC08D?style=for-the-badge&logo=vuedotjs&logoColor=white)](https://vuejs.org/)
[![TypeScript](https://img.shields.io/badge/TypeScript-3178C6?style=for-the-badge&logo=typescript&logoColor=white)](https://www.typescriptlang.org/)
[![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Redis](https://img.shields.io/badge/Redis-DC382D?style=for-the-badge&logo=redis&logoColor=white)](https://redis.io/)
[![Docker](https://img.shields.io/badge/Docker-2496ED?style=for-the-badge&logo=docker&logoColor=white)](https://www.docker.com/)

[Wymagania produktu](.docs/prd.md) · [Stos technologiczny](.docs/tech-stack.md) · [Zgłoś błąd](../../issues/new)

[English](README.md) · **Polski**

</div>

## O projekcie

Wallet Master to aplikacja webowa do prowadzenia osobistego albo domowego budżetu. Powstała dla osób, które eksportują wyciągi z polskich banków (mBank, BNP Paribas) do CSV lub XLSX i chcą szybko przejść od importu do jasnego obrazu wydatków, planów i celów oszczędnościowych. Interfejs projektowany jest najpierw pod komputer; wersja mobilna działa, ale nie jest głównym celem.

> [!NOTE]
> Wallet Master jest poprzednikiem [Domowego Skarbnika](https://github.com/sebastian-kostecki/household-treasurer) — rocznego budżetu domowego, który działa na produkcji.

## Dlaczego Wallet Master

- **Szybki import** — wgraj CSV/XLSX z obsługiwanego banku; automatyczne mapowanie kolumn, usuwanie duplikatów i przetwarzanie w tle ze statusem na żywo.
- **Pełna kontrola** — ręczne dochody i wydatki, przelewy między własnymi kontami i korekty salda, gdy wyciąg i aplikacja się nie zgadzają.
- **Plan kontra wykonanie** — kategorie P&L z widokiem miesięcznym i rocznym. Szacunki są informacyjne, a nie twardymi limitami.
- **Koperty oszczędnościowe (Pockets)** — nazwane cele powiązane z przelewami na konto oszczędnościowe; widzisz, ile odłożono, wypłacono i ile zostało.

## Funkcje

### Konta

Twórz konta z nazwą, typem, bankiem i saldem. Obsługiwane typy to **rachunek bieżący (ROR)** i **oszczędnościowy**. Banki: **mBank**, **BNP Paribas** i **Gotówka** (tylko wpisy ręczne — import plików nie jest dostępny dla gotówki). Usunięte konto znika z listy, ale jego transakcje zostają w historii tylko do odczytu.

### Transakcje

Zapisuj dochody, wydatki i korekty salda. Każda transakcja P&L wymaga kategorii. Lista ma filtry po koncie i okresie, sortowanie i paginację. Sumy za okres pokazują wpływy i wydatki **bez przelewów wewnętrznych**, więc obraz wydatków pozostaje prawdziwy.

### Import

Na ekranie transakcji wybierz konto i wgraj eksport CSV albo XLSX z banku. Aplikacja dobiera adapter banku do konta, mapuje kolumny automatycznie i zapisuje import bez ręcznego podglądu. Na koniec widzisz, ile wierszy zaimportowano, ile duplikatów pominięto i ile wierszy nie przeszło walidacji. Opcjonalny matcher podpowiada pary przeciwnych kwot między Twoimi kontami.

### Przelewy

Jeden przelew tworzy dwie powiązane transakcje: wypływ z konta źródłowego i wpływ na docelowe. Przelewy między rachunkami bieżącymi nie mają kategorii ani koperty. Gdy w grze jest konto **oszczędnościowe**, obie nogi muszą mieć tę samą **kopertę**, żeby postęp oszczędzania trafił do właściwego celu.

### Kategorie

Budujesz własny katalog kategorii dochodów i wydatków z ikonami i kolorami. Przy pierwszym użyciu powstaje zestaw startowy, a kolejność możesz zmieniać. Przy imporcie aplikacja zapamiętuje wybór kategorii dla znormalizowanego opisu z banku i stosuje go następnym razem; w przeciwnym razie bierze Twoją pierwszą kategorię danego typu.

### Budżet

- **Widok miesięczny** — plan kontra wykonanie dla każdej kategorii P&L w wybranym miesiącu, z edycją planu miesiąca w tabeli. Sekcja **Koperty** pokazuje plan, odłożone, wypłacone i saldo każdej aktywnej koperty.
- **Widok roczny** — szacunek roczny kontra wykonanie dla każdej kategorii w roku kalendarzowym.

### Koperty oszczędnościowe

Koperty są osobne od kategorii P&L. Służą do celów takich jak fundusz wakacyjny czy poduszka finansowa. Każda może mieć kwotę docelową i tryb planowania: stałą wpłatę miesięczną albo datę docelową (aplikacja podpowiada miesięczną kwotę na podstawie postępu i terminu). Waluta ustalana jest przy tworzeniu (w UI PLN). Zarchiwizowana koperta znika z domyślnych list i budżetu miesięcznego.

### Prywatność i dane

Wszystkie konta, transakcje, importy, kategorie i koperty należą wyłącznie do zalogowanego użytkownika; nie ma współdzielenia między użytkownikami. Logowanie to standardowa sesja z e-mailem i hasłem.

## Typowe scenariusze

1. **Pierwsze użycie** — zarejestruj się, utwórz konto, dodaj transakcję i sprawdź, że lista i saldo się zmieniły.
2. **Import wyciągu** — Transakcje → Import → wybierz konto → wgraj CSV/XLSX → poczekaj na przetworzenie → sprawdź liczniki i listę.
3. **Oszczędzanie na cel** — utwórz kopertę (np. Wakacje), przelej z rachunku bieżącego na oszczędnościowy z tą kopertą i sprawdź w budżecie miesięcznym odłożone, wypłacone i pozostałe kwoty.

## Stos technologiczny

| Warstwa | Technologia |
| --- | --- |
| Backend | PHP 8.5, Laravel 13, Inertia Laravel 2, Ziggy, Horizon, Reverb |
| Frontend | Vue 3, TypeScript, Inertia Vue 2, Vite, Tailwind CSS 3, Radix Vue, Headless UI |
| Dane | MySQL 8.4, Redis (kolejki), Typesense |
| Jakość | Pest 4 / PHPUnit 12, Larastan, Laravel Pint, ESLint 9, Prettier 3, vue-tsc |

## Uruchomienie

### Wymagania

- Docker ([Laravel Sail](https://laravel.com/docs/sail)): PHP 8.5, MySQL, Redis, Typesense i Mailpit działają w kontenerach

### Instalacja

```bash
git clone https://github.com/sebastian-kostecki/wallet-master.git
cd wallet-master

cp .env.example .env
composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

Bez pełnego stosu Dockera `composer run dev` uruchamia razem `serve`, `queue:listen`, `pail` i `vite`.

## Praca nad kodem

| Polecenie | Co robi |
| --- | --- |
| `sail artisan test --compact` | Testy Pest |
| `vendor/bin/pint --dirty --format agent` | Styl kodu |
| `vendor/bin/phpstan analyse` | Analiza statyczna Larastan |
| `npm run lint` / `npm run format` | ESLint i Prettier |
| `npm run build:ssr` | Paczki klienta i SSR |

CI uruchamia workflowy [`tests`](.github/workflows/tests.yml) i [`linter`](.github/workflows/lint.yml) na pushach i pull requestach do `develop` i `master`.

## Czego (jeszcze) nie ma

- Wielu walut w UI ani automatycznego przeliczania (w interfejsie tylko PLN)
- Bezpośrednich integracji z API banków
- Natywnych aplikacji mobilnych
- Eksportu danych i załączników do transakcji
- Importu z PDF, MT940 ani skanów
- Podpowiedzi kategorii z AI
- Wspólnych kont gospodarstwa dla wielu osób

## Dokumentacja

- [Wymagania produktu](.docs/prd.md) i [zakres MVP](.docs/mvp.md)
- [Stos technologiczny, usługi Sail i polecenia](.docs/tech-stack.md)
- [`AGENTS.md`](AGENTS.md) — zasady dla agentów

## Status projektu

Przed wydaniem: nie ma otagowanej wersji ani publicznego wdrożenia. Prace nad budżetem domowym toczą się dalej w [Domowym Skarbniku](https://github.com/sebastian-kostecki/household-treasurer).

## Licencja

Projekt jest udostępniony na [licencji MIT](LICENSE).
