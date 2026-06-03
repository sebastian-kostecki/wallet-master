# MVP — Wallet Master (wave 1, skrót)

> **Canonical source:** Szczegóły wymagań, AC i NFR — `.docs/prd.md`.  
> Ten plik opisuje **minimalny zestaw funkcjonalności (wave 1)** i kryteria sukcesu w skrócie. W razie rozbieżności obowiązuje PRD.

### Główny problem
Aplikacja pomaga użytkownikowi w zarządzaniu budżetem domowym, co pozwala mu planować przychody i wydatki, ograniczać zbędne wydatki oraz zwiększać oszczędności.

### Najmniejszy zestaw funkcjonalności
- Rejestracja i logowanie użytkownika; izolacja danych per użytkownik (każdy użytkownik widzi wyłącznie swoje konta i operacje)
- Zarządzanie kontami bankowym (przeglądanie, dodawanie, edycja, usuwanie): każde konto ma nazwę, walutę domyślną (na MVP: PLN) i saldo początkowe
- Zarządzanie operacjami (przeglądanie, dodawanie, edycja, usuwanie): każda operacja zawiera typ (przychód / wydatek / transfer między własnymi kontami), kwotę, walutę (na MVP: PLN), datę, opis, przypisane konto i opcjonalnie kontrahenta (`subject`)
- Przeglądanie operacji z filtrowaniem po koncie i przedziale dat, sortowaniem po dacie/kwocie, paginacją oraz podsumowaniem (suma wpływów i wydatków w wybranym okresie)
- Import operacji z wyciągu bankowego (CSV/XLSX): wybór konta → upload → automatyczne mapowanie kolumn (adapter banku) → auto-commit bez podglądu → typ transakcji ze znaku kwoty → automatyczne wykrywanie i pomijanie duplikatów (identyczne daty, kwoty i opisy na tym samym koncie)

> Funkcje **Should** z PRD (Typesense, matcher transferów przy imporcie, reset hasła) — patrz PRD §7 i Release Plan.

### Co nie wchodzi w zakres MVP
- Kategoryzacja operacji i AI do przypisywania/sugerowania kategorii
- Wielowalutowość i przeliczenia kursów walut
- Współdzielenie kont i operacji między użytkownikami (budżet rodzinny)
- Import z formatów innych niż CSV/XLSX (PDF, MT940, OCR ze skanów bankowych)
- Załączniki do operacji, eksport danych, raporty i wykresy
- Mechanizm szacowania wydatków i przychodów; budżetowanie
- Integracje z zewnętrznymi systemami i bankami
- Aplikacje mobilne (na początek wyłącznie web)
- Zaawansowane działania na operacjach (duplikowanie, masowe edycje, szablony)

### Kryteria sukcesu
- Minimum 90% wierszy z poprawnie zmapowanego pliku CSV/XLSX zostaje zaimportowanych bez potrzeby ręcznej korekty
- Mediana czasu dodania ręcznej operacji (od kliknięcia „Dodaj operację" do zatwierdzenia zapisu) poniżej 30 sekund
- Minimum 70% nowo zarejestrowanych użytkowników wykonuje przynajmniej jeden import wyciągu w ciągu pierwszych 7 dni od rejestracji
- Zero incydentów wycieku danych między użytkownikami (weryfikacja w testach automatycznych i manual testing)

### Persona / grupa docelowa
Osoba prowadząca budżet osobisty, posiadająca 1–3 konta bankowe w PLN, korzystająca regularnie z bankowości elektronicznej i potrafiąca wyeksportować historię operacji do formatu CSV/XLSX.
