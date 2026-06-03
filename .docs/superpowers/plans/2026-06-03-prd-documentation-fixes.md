# PRD Documentation Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Align `.docs/prd.md` with its role as the canonical product source of truth—fix internal contradictions, clarify assumptions, and reduce drift in satellite docs—without changing product scope or code behavior.

**Architecture:** Editorial-only changes to markdown. PRD remains authoritative; `mvp.md` gets a short pointer/subset header; stale `backend-plan` files get deprecation notices. No migrations, no PHP/Vue changes.

**Tech Stack:** Markdown only. Verification = read-through + grep for leftover contradictions.

**Context:** Audit 2026-06-03 (brainstorming). User decision: **A** — PRD is source of truth; MVP will later list features outside current wave.

---

## Scope

### In scope
| ID | Issue | Primary file |
|----|--------|--------------|
| P1 | Appendix §Conflicts claims “no conflicts” | `prd.md` §18 |
| P2 | Navigation map lists Import as top-level vs modal IA | `prd.md` §8 |
| P3 | FR-I5 Options block describes bank adapters, not Typesense | `prd.md` FR-I5 |
| P4 | FR-K1 missing `opening_balance` → `current_balance` rule | `prd.md` FR-K1 |
| P5 | Metric “70% aktywnych” vs glossary “Aktywny użytkownik” | `prd.md` §2, Glossary |
| P6 | Release Plan help text “jak mapować” vs zero-config import | `prd.md` §14 |
| P7 | Clarify `draft` import status vs UX “no preview” | `prd.md` §12, FR-I1 (one sentence) |
| S1 | `mvp.md` superseded-by-PRD header + trim conflicting bullets | `mvp.md` |
| S2 | Deprecate stale import backend plan | `backend-plan/import-api-plan.md` |

### Out of scope
- New FRs, Should→Must changes, checklist checkboxes
- Code changes (`UpdateAccountDetails` already implements P4 rule)
- Full rewrite of `ui-plan/*` or `import-ui-plan.md` (only touch if grep finds “manual mapping” contradictions)
- New spec file under `superpowers/specs/` (doc hygiene only)

---

## Acceptance criteria (plan complete when)

- [x] Grep `prd.md` for “podgląd” / “preview” in MVP-conflict sense — only in Appendix as **historical** note, not as current requirement
- [x] §8 navigation consistent: Import entry = modal from Transactions (no orphan top-level Import nav unless explicitly marked “future”)
- [x] FR-I5 has no Options block about `BankImportAdapter` / user mapping templates
- [x] FR-K1 AC documents delta rule for `opening_balance` (matches `UpdateAccountDetails`)
- [x] §2 metric uses “nowo zarejestrowanych” (or equivalent), glossary unchanged for retention analytics
- [x] §14 help bullet describes bank export + account selection, not column mapping
- [x] `mvp.md` top states PRD is canonical; import bullets match PRD (no preview, no typ column)
- [x] `import-api-plan.md` has deprecation banner at top

---

### Task 1: Appendix §Conflicts (P1)

**Files:**
- Modify: `.docs/prd.md` — section `## 18. Appendix` → `### Conflicts`

- [ ] **Step 1:** Replace “Brak bezpośrednich sprzeczności…” with a short table:

| Topic | Legacy `.docs/mvp.md` | PRD (canonical) |
|-------|----------------------|-----------------|
| Import preview | Ekran podglądu przed zapisem | Auto-commit bez preview (FR-I1) |
| Typ transakcji w imporcie | Kolumna „typ” w mapowaniu | Typ ze znaku kwoty (FR-I2) |
| Scope | Minimalna lista | PRD = pełny katalog; MVP wave = subset |

- [ ] **Step 2:** Keep existing PLN vs Currency entity note as resolved tension (no change to resolution text).

- [ ] **Step 3:** Add one line: “`.docs/mvp.md` is maintained as a subset summary; see Task 4.”

---

### Task 2: Information Architecture & navigation (P2)

**Files:**
- Modify: `.docs/prd.md` — `## 8. Information Architecture & Navigation`

- [ ] **Step 1:** Under `### Mapa nawigacji`, change to:
  - Konta
  - Transakcje (filtry, podsumowanie, **Import** jako akcja/modal, baner kandydatów transferu)
  - (Post-MVP / optional) Ustawienia profilu

- [ ] **Step 2:** Remove standalone `- Import` nav item OR mark it `**(post-MVP, jeśli osobna strona historii importów)**` — pick **remove** for MVP consistency with existing UI plan.

- [ ] **Step 3:** In `### Ekrany/strony`, ensure Import line says “modal z widoku transakcji” (already there); cross-check no other §8 bullet implies separate Import app section.

---

### Task 3: FR-I5 Options cleanup (P3)

**Files:**
- Modify: `.docs/prd.md` — `#### FR-I5` block ending with `**Options + Recommendation**`

- [ ] **Step 1:** Delete misplaced Options block (Opcja 1: szablony mapowania / Opcja 2: bank adapters).

- [ ] **Step 2:** Replace with FR-I5-specific note (no Options table needed):

> **Implementation note:** Enrichment is best-effort via Typesense (`import_description_memory`). No user-editable mapping templates in MVP. Bank column mapping remains in FR-I4 adapters only.

- [ ] **Step 3:** Verify FR-I4 still owns the adapter Options block (unchanged).

---

### Task 4: FR-K1 opening balance rule (P4)

**Files:**
- Modify: `.docs/prd.md` — `#### FR-K1 CRUD kont`

- [ ] **Step 1:** Extend AC bullet “edytuje nazwę/saldo początkowe”:

```markdown
- Given konto z `opening_balance = O1` i `current_balance = C`
  When użytkownik zmienia `opening_balance` na `O2` (bez zmiany transakcji)
  Then `opening_balance = O2` i `current_balance = C + (O2 − O1)` (delta salda początkowego).
```

- [ ] **Step 2:** Add edge case under FR-K1:

> Zmiana `opening_balance` nie tworzy transakcji `adjustment`; korekta bieżącego salda bez zmiany historii → FR-S1 (akcja „Ustaw saldo”).

- [ ] **Step 3:** Optional cross-reference in FR-S1: “Edycja `opening_balance` — patrz FR-K1 (delta, nie adjustment).”

---

### Task 5: Metrics & glossary terminology (P5)

**Files:**
- Modify: `.docs/prd.md` — Glossary + `## 2. Goals / Success Metrics`

- [ ] **Step 1:** In §2, replace:

`min. **70% aktywnych** wykonuje ≥1 import…`

with:

`min. **70% nowo zarejestrowanych użytkowników** wykonuje ≥1 import w ciągu 7 dni od rejestracji`

- [ ] **Step 2:** In Glossary, rename or clarify:

`- **Aktywny użytkownik (retention):** …`  

and add:

`- **Nowy użytkownik (aktywacja importu):** użytkownik w oknie 7 dni od `user_registered` (metryka §2 Activation import).`

- [ ] **Step 3:** Update Appendix citation bullet for mvp.md success criteria to match new wording.

---

### Task 6: Release Plan help copy (P6)

**Files:**
- Modify: `.docs/prd.md` — `## 14. Release Plan` → “Plan komunikacji”

- [ ] **Step 1:** Replace:

`Ekran pomocy importu (krótko: jak wyeksportować plik i jak mapować).`

with:

`Ekran pomocy importu (krótko: jak wyeksportować plik CSV/XLSX z banku, wybrać właściwe konto w aplikacji; **bez** ręcznego mapowania kolumn — mapowanie z adaptera, FR-I4).`

---

### Task 7: Import `draft` status clarification (P7)

**Files:**
- Modify: `.docs/prd.md` — `## 12. Data Model` (Import entity) and/or `#### FR-I1`

- [ ] **Step 1:** After Import status list, add:

> **`draft`:** stan techniczny po uploadzie pliku, przed kolejkowaniem (`queued`). Użytkownik nie przechodzi etapu podglądu ani ręcznego mapowania; UX to upload → oczekiwanie → wynik.

- [ ] **Step 2:** In §11 Assumption “Import 1-etapowy”, add cross-ref: “patrz status `draft` w §12.”

---

### Task 8: MVP subset header (S1)

**Files:**
- Modify: `.docs/mvp.md`

- [ ] **Step 1:** Add header block (after title):

```markdown
> **Canonical source:** Szczegóły wymagań, AC i NFR — `.docs/prd.md`.  
> Ten plik opisuje **minimalny zestaw funkcjonalności (wave 1)** i kryteria sukcesu w skrócie. W razie rozbieżności obowiązuje PRD.
```

- [ ] **Step 2:** Fix import bullet (line ~9):
  - Remove: „ekranem podglądu przed zapisem”
  - Remove: „typ” z listy mapowanych kolumn
  - Add: auto-mapowanie adaptera banku, auto-commit, typ ze znaku kwoty

- [ ] **Step 3:** Fix success metric line ~25: „70% aktywnych” → „70% nowo zarejestrowanych użytkowników” (match PRD §2).

- [ ] **Step 4:** Optional one-liner under “Co nie wchodzi”: „Funkcje Should z PRD (Typesense, matcher transferów) — patrz PRD §7 / Release Plan.”

---

### Task 9: Deprecate stale backend plan (S2)

**Files:**
- Modify: `.docs/backend-plan/import-api-plan.md`

- [ ] **Step 1:** Insert at line 1:

```markdown
> **⚠️ Deprecated (2026-06-03):** Ten plan opisuje ręczne mapowanie kolumn i 2-etapowy UX z preview/mapowaniem w UI. **Obowiązuje `.docs/prd.md`** (FR-I1, FR-I4: adapter-only, auto-commit). Implementacja: `PrepareImportUpload`, `CommitImport`, adaptery w `app/Imports/BankAdapters/`.
```

- [ ] **Step 2:** Grep `.docs/` for `save_mapping`, `mapowanie w UI`, `preview` — list hits in plan completion notes; fix only if still stated as **current** requirement (not historical).

---

### Task 10: Self-review & sign-off

**Files:**
- Read: `.docs/prd.md` (full pass on edited sections)

- [ ] **Step 1:** Run contradiction grep:

```bash
rg -n "podgląd|preview|jak mapować|70% aktywnych|Opcja 1.*szablon" .docs/prd.md .docs/mvp.md
```

Expected: zero hits as *current* requirements (Appendix historical table OK).

- [ ] **Step 2:** Confirm FR-K1 delta text matches code:

`app/Actions/Accounts/UpdateAccountDetails.php` — `current_balance = bcadd(current, delta opening)`.

- [ ] **Step 3:** No commit unless user asks; doc-only change message suggestion:

`docs: align PRD as canonical source, fix internal contradictions`

---

## Task order (recommended)

```
Task 1 (Appendix) → Task 5 (metrics) → Task 4 (FR-K1) → Task 3 (FR-I5)
→ Task 2 (nav) → Task 6 (help) → Task 7 (draft) → Task 8 (mvp) → Task 9 (deprecate) → Task 10
```

Tasks 1–7 are independent within `prd.md` but sequential editing avoids merge-style conflicts in one file.

---

## Effort estimate

| Task | Time |
|------|------|
| 1–7 PRD edits | ~45 min |
| 8 mvp.md | ~15 min |
| 9–10 | ~15 min |
| **Total** | **~1–1.5 h** |

---

## Risks

| Risk | Mitigation |
|------|------------|
| Over-scoping doc change into new FRs | Stick to acceptance criteria; no new §7 requirements |
| Checklist drift | Out of scope; optional follow-up: grep checklist for `import_mapping_saved` telemetry |
| Translators confuse `draft` | P7 wording explicit: technical only |

---

## Follow-ups (separate plans, not this PR)

- [ ] Add PRD section **「MVP wave 1」** table (Must / Should / Later) when more post-MVP sections land
- [ ] Sync `import-ui-plan.md` if grep finds stale “krok mapowania” as user-editable
- [ ] Remove duplicate telemetry names in checklist §8 (`import_mapping_*`) in a small hygiene PR
