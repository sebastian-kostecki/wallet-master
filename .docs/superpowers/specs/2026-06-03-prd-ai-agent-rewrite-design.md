# PRD — przepisanie pod agenty AI (podejście C)

**Data:** 2026-06-03  
**Status:** Approved (brainstorming)  
**Deliverable:** `.docs/prd.md` (zastąpienie obecnej wersji)  
**Poza zakresem tej pracy:** zmiana kodu aplikacji, `.docs/checklist.md` (poza ewentualnym dopiskiem w nagłówku), `.cursor/rules/*`

## Problem

Obecny `.docs/prd.md` (~650 linii) nie jest optymalny jako jedyne źródło prawdy dla agentów kodujących AI:

- Odniesienia do `.docs/mvp.md` i Appendix z cytatami / tabelą konfliktów historycznych.
- Artefakty procesu: `[Assumption]`, bloki „Options + Recommendation + Rationale”, `Implementation note`.
- Rozproszona wiedza domenowa (model danych w §12, wymagania w §7, tech w §11 + Appendix).
- Duplikacja treści względem `mvp.md` i częściowo `tech-stack.md`.

Użytkownik chce **jeden dokument produktowy** do rozwoju kolejnych feature’ów, z **jednym** zewnętrznym odniesieniem: `.docs/tech-stack.md` (stack i komendy dev). Język PRD: **polski**; kod i komentarze: **angielski**.

## Goals

1. Przepisać `.docs/prd.md` od zera według struktury 12 sekcji (poniżej).
2. Zachować stabilne identyfikatory `FR-*` (zgodność z `.docs/checklist.md` i historią repo).
3. Usunąć wszystkie odniesienia do innych plików produktowych poza `.docs/tech-stack.md`.
4. Ujednolicić format wymagań pod parsowanie przez agentów (tabele metadanych, numerowane AC, jawne reguły).
5. Dodać sekcję „For implementers” i szablon nowego `FR-XX`.
6. Dodać indeks ID wymagań na końcu dokumentu.

## Non-Goals

- Tłumaczenie PRD na angielski.
- Wchłanianie treści `.docs/tech-stack.md` (komendy Sail, wersje pakietów, `compose.yaml`) — tylko krótki most w §9.
- Zmiana `.docs/checklist.md` poza ewentualnym jednym zdaniem w nagłówku (już wskazuje na `prd.md`).
- Usunięcie `.docs/mvp.md` w tym samym PR (osobna decyzja; rekomendacja: banner deprecated).
- Aktualizacja `.cursor/rules/wallet-dev-workflow.mdc` (tabela docs nadal poprawna: PRD + checklist + tech-stack).

## Decisions

| Temat | Decyzja |
|-------|---------|
| Podejście | C — pełna restrukturyzacja, nie patch |
| Język PRD | Polski |
| Język kodu | Angielski (bez zmian) |
| Zewnętrzne linki | Wyłącznie `.docs/tech-stack.md` |
| ID wymagań | Zachować: `FR-A1`, `FR-A2`, `FR-K1`, `FR-K2`, `FR-T1`–`T3`, `FR-S1`, `FR-I1`–`I6` |
| Assumptions | Usunąć tag; treść → wymaganie lub §12 Open Questions |
| Historia decyzji (opcje A/B) | Usunąć; zostaje jedna **Decyzja** / **Rules** |
| Appendix §18 | Usunąć (treść już w PRD) |
| Checklist PM/CEO/Tech/Legal | Usunąć z PRD |
| Długość docelowej | ~400–500 linii (gęstszy, bez utraty wymagań MVP) |

---

## Docelowa struktura `.docs/prd.md`

```markdown
# Wallet Master — wymagania produktowe (PRD)

> Jedyny kanoniczny dokument wymagań produktowych.
> Stack technologiczny i komendy deweloperskie: `.docs/tech-stack.md`

## 0. Dla implementujących (AI i zespół)
## 1. Słownik pojęć
## 2. Streszczenie produktu
## 3. Zakres i metryki sukcesu
   ### 3.1 MVP — Must
   ### 3.2 Poza zakresem (Out of scope)
   ### 3.3 Metryki i zdarzenia telemetryczne
## 4. Użytkownicy i przepływy
   ### 4.1 Persona
   ### 4.2 Kluczowe journey (A–D)
## 5. Model domeny
## 6. Katalog wymagań
   ### 6.1 Autentykacja
   ### 6.2 Konta
   ### 6.3 Transakcje
   ### 6.4 Salda
   ### 6.5 Import
   ### 6.6 Szablon: dodawanie FR-XX
## 7. UX, IA i copy
## 8. Wymagania niefunkcjonalne
## 9. Ograniczenia techniczne (skrót + tech-stack)
## 10. Granice release (MVP vs później)
## 11. Ryzyka i mitygacje
## 12. Zależności i otwarte pytania
## Indeks identyfikatorów FR
```

---

## Sekcja 0 — treść obowiązkowa (skrót)

Agent / developer czyta to przed implementacją:

- **Źródło prawdy:** ten plik dla *co* budujemy; `.docs/tech-stack.md` dla *jak* uruchamiamy i jakiego stacku używamy.
- **MVP:** implementuj `Priority: Must` chyba że zadanie explicite obejmuje `Should`.
- **Izolacja:** wszystkie zasoby scoped do `user_id`; brak wycieków między użytkownikami (metryka §3.3).
- **Konto usunięte (soft delete):** transakcje widoczne, **read-only**; import i transfer zablokowane.
- **Import:** wybór konta → upload → auto-mapowanie adaptera banku → auto-commit **bez preview**; status `draft` techniczny między uploadem a `queued`.
- **Dedupe importu:** `date + amount + normalized_description` per konto; ręczny duplikat dozwolony (`dedupe_hash` z sufiksem UUID).
- **Saldo:** `current_balance` utrzymywane przy zmianach + komenda `accounts:recalculate-balance` jako safety net.
- **Filtry listy / summary:** domyślnie `booked_at`; kolumna daty okresu `COALESCE(booked_at, date)`; summary bez wewnętrznych transferów (`transfer_id IS NULL`).
- **Out of scope §3.2:** nie implementuj bez osobnego PRD / rozszerzenia dokumentu.

---

## Sekcja 6 — szablon pojedynczego wymagania

Każde `FR-*` w nowym PRD:

```markdown
### FR-XX — Krótki tytuł

| Pole | Wartość |
|------|---------|
| **Priorytet** | Must \| Should \| Could |
| **Domena** | Auth \| Accounts \| Transactions \| Balances \| Import |

**Zachowanie**
Opis 2–5 zdań, język produktowy.

**Kryteria akceptacji**
1. Given … When … Then …
2. …

**Reguły i przypadki brzegowe**
- …

**Zdarzenia** (opcjonalnie)
- `event_name`, …
```

**Mapowanie ze starego PRD → nowe sekcje:**

| Stare ID | Nowa podsekcja | Uwagi migracji treści |
|----------|----------------|------------------------|
| FR-A1, FR-A2 | §6.1 | Usunąć `[Assumption]` przy haśle/rate limit — wpisać jako regułę lub Open Questions |
| FR-K1, FR-K2 | §6.2 | Opcje soft-delete → jedna decyzja w Rules |
| FR-T1–T3 | §6.3 | Zachować booked_at, summary, transfer 2× transakcja |
| FR-S1 | §6.4 | Opcja salda stored → Decyzja w Rules; adjustment + audyt |
| FR-I1–I6 | §6.5 | Usunąć `Implementation note`; tokeny transferów bez `config/imports.php` |
| (nowe) | §6.6 | Szablon + przykład pusty FR-XX |

---

## Sekcja 5 — model domeny (obowiązkowe elementy)

Skonsolidować z obecnego §12 + glossary:

**Encje:** User, Currency, Account, Transaction, Import, AccountBalanceAdjustment.

**Account:** `name`, `currency_id`, `opening_balance`, `current_balance`, `type` (`Ror`, `Savings`), `bank` (`BnpParibas`, `MBank`, `Cash`), `deleted_at`.

**Transaction:** `date`, `booked_at` (default `date`), `amount` (signed decimal), `type` (`income`, `expense`, `transfer`, `adjustment`), `description`, `subject`, `normalized_description`, `dedupe_hash`, `transfer_id`, `transfer_match_status`, `transfer_candidate_for_id`, `import_id`, `raw_statement_description`.

**Import:** statusy `draft`, `queued`, `processing`, `committed`, `failed`; liczniki wierszy; `details` JSON.

**Relacje:** User 1—N Accounts, Transactions, Imports; Account 1—N Transactions; Transfer = 2 Transactions, ten sam `transfer_id`.

---

## Sekcja 3.3 — metryki (bez zmian merytorycznych)

- Import success rate ≥ 90% (`rows_imported` / poprawnie zmapowane).
- Time-to-add manual < 30s (mediana, eventy frontend).
- Activation import ≥ 70% nowych userów w 7 dni.
- Data isolation: 0 wycieków (testy feature).

Zdarzenia telemetryczne: zebrać z obecnych FR w jednej tabeli lub podsekcji (łatwiejsze grep dla agenta).

---

## Sekcja 9 — most do tech-stack (max ~10 linii)

Przykład:

- Backend: Laravel 13, Inertia v2, sesja auth.
- Frontend: Vue 3 + TypeScript, Vite, Tailwind 3.
- Infra dev/prod: MySQL, Redis, Typesense, Reverb, Horizon (szczegóły → `.docs/tech-stack.md`).
- Testy/jakość: Pest, Pint, Larastan (komendy → tech-stack).

**Nie powielać:** tabel portów Sail, `compose.yaml`, listy pakietów composer/npm.

---

## Sekcja 12 — Open Questions (po migracji)

Przenieść spekulatywne punkty ze starych `[Assumption]`, jeśli nie są twardym wymaganiem MVP:

- Feature flags dla importu/transferu przy rollout.
- Kanał feedbacku (email).
- Ekran pomocy importu (treść help).
- Ustawienia profilu post-MVP.

Jeśli produkt już wdrożył daną rzecz (np. rate limit 6/min) — zostaje w §8 NFR jako fakt, nie w Open Questions.

---

## Indeks FR (koniec dokumentu)

Tabela:

| ID | Tytuł | Priorytet | Sekcja |
|----|-------|-----------|--------|
| FR-A1 | Rejestracja i logowanie | Must | §6.1 |
| … | … | … | … |

---

## Pliki powiązane po wdrożeniu

| Plik | Akcja |
|------|--------|
| `.docs/prd.md` | Zastąpić nową treścią |
| `.docs/mvp.md` | Rekomendacja: banner `> Deprecated — see .docs/prd.md` (osobny commit/PR) |
| `.docs/checklist.md` | Bez zmian (już linkuje PRD) |
| `.docs/tech-stack.md` | Bez zmian |
| `.cursor/rules/wallet-dev-workflow.mdc` | Bez zmian |

---

## Weryfikacja ukończenia (implementacja przepisania)

- [ ] Brak wystąpień `mvp.md`, `Assumption`, `Options + Recommendation`, `## 18. Appendix`.
- [ ] Dokładnie jedno odniesienie do `tech-stack.md` w nagłówku + §9 (dopuszczalne powtórzenie w §9).
- [ ] Wszystkie ID `FR-A1` … `FR-I6` obecne w §6 i Indeksie.
- [ ] Sekcje 0, 5, 6.6, Indeks obecne.
- [ ] Długość w przybliżeniu 400–500 linii (orientacyjnie).
- [ ] Przegląd ręczny: żadna reguła z obecnego PRD nie zginęła (checklist §1–10 pokrywa FR).

---

## Kolejny krok po akceptacji spec

1. Użytkownik review tego pliku spec.
2. Skill **writing-plans** → plan implementacji przepisania `prd.md`.
3. Implementacja na branchu `improvement/prd-ai-rewrite` (lub podobnym).
4. Opcjonalnie: deprecated `mvp.md` w tym samym lub follow-up PR.
