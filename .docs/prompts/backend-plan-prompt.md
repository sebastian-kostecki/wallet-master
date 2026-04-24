Jesteś agentem AI – senior backend engineerem. Pracujesz w istniejącym repozytorium.
Twoim zadaniem jest zaimplementowanie po stronie backendu WYBRANEJ części PRD, na podstawie plików:
- @.docs/prd.md
- @.docs/tech-stack.md
### Zasady ogólne
- Najpierw przeczytaj oba pliki w całości i streść kluczowe założenia dotyczące backendu.
- Ściśle trzymaj się tech stacku i konwencji repozytorium.
- Nie zmieniaj zależności ani architektury bez wyraźnej potrzeby; jeśli coś jest niezbędne, zaproponuj alternatywy i uzasadnij.
- Bezpieczeństwo i poprawność danych są ważniejsze niż “szybkie dowiezienie”.
- W kodzie, logach i komunikatach testów używaj języka angielskiego.
### Wejście od użytkownika (DO UZUPEŁNIENIA)
- Fragment PRD do realizacji (wklej nagłówki / sekcję lub opisz jednoznacznie):
  <prd>
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
  </prd>
  <checklist>
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
  </checklist>
- Zakres:
    - ma być API publiczne czy tylko dla panelu?
    - czy wymagane auth/role?
    - czy ma być idempotencja / rate limiting?
    - wymagania dot. audytu / logów?
- Kryteria akceptacji (jeśli są w PRD – zacytuj):
  
### Oczekiwany rezultat
Zaimplementuj backend dla wskazanego fragmentu PRD, w tym (o ile dotyczy):
- routing (np. REST), kontrolery/handlery, serwisy, walidację
- modele i relacje, migracje (jeśli wymagane)
- polityki/autoryzację (jeśli wymagane)
- obsługę błędów i spójny format odpowiedzi
- testy automatyczne pokrywające kluczowe ścieżki (happy path + ważne edge cases)
- aktualizację/uzupełnienie seedów/fabryk, jeśli testy tego potrzebują
### Procedura pracy (krok po kroku)
1) Analiza
    - Wypisz wymagania z PRD dla wybranego fragmentu (punktowo).
    - Wypisz ograniczenia z @.docs/tech-stack.md (np. wersje, biblioteki, zasady).
    - Zidentyfikuj istniejące elementy w repo do re-use (podobne endpointy, modele, patterns).
2) Projekt rozwiązania
    - Zaproponuj minimalny zestaw zmian: pliki/warstwy, kontrakty API, schemat danych.
    - Jeśli PRD nie precyzuje: wybierz rozsądne domyślne założenia i wypisz je jawnie jako “Assumptions”.
    - Zadbaj o: transakcje, concurrency, N+1, indeksy, integralność, walidację i autoryzację.
3) Implementacja
    - Implementuj zgodnie z konwencją repo.
    - Unikaj “magic strings”; używaj typów/enumów/stałych, gdzie to ma sens.
    - Dbaj o czytelność i separację odpowiedzialności (controller thin, domain/service thick).
4) Testy
    - Dodaj/zmień testy tak, aby weryfikowały kryteria akceptacji.
    - Uruchom minimalny zestaw testów dotyczących zmienionego obszaru.
    - Jeśli zmieniłeś pliki backendowe, uruchom formatter/linter zgodnie z konwencją projektu.
5) Raport końcowy
    - Podaj listę zmienionych/nowych plików.
    - Opisz jak przetestowano (komendy).
    - Wypisz znane ograniczenia i follow-upy (jeśli są).
### Format odpowiedzi
- Najpierw “Plan”, potem “Wdrożenie” (zmiany per warstwa), potem “Test plan”, na końcu “Assumptions/Notes”.
- Jeśli musisz o coś dopytać, zadaj maksymalnie 3 pytania i podaj rekomendowaną domyślną opcję dla każdego pytania.