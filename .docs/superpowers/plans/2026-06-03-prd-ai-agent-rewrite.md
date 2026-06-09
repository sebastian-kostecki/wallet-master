# PRD AI-Agent Rewrite Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace `.docs/prd.md` with a restructured, self-contained Polish PRD optimized for AI coding agents—one external doc link (`.docs/tech-stack.md`), preserved `FR-*` IDs, no process artifacts.

**Architecture:** Editorial full rewrite per spec `.docs/superpowers/specs/2026-06-03-prd-ai-agent-rewrite-design.md`. Migrate all product rules from the current PRD (~650 lines) into 12 sections (~400–500 lines). No application code changes.

**Tech Stack:** Markdown only. Source material: current `.docs/prd.md` on `develop`. Verification: `rg` grep + manual FR checklist against `.docs/checklist.md` §1–10.

**Supersedes:** `.docs/superpowers/plans/2026-06-03-prd-documentation-fixes.md` (partial editorial fixes—abandon in favor of this rewrite).

---

## Files

| Action | Path |
|--------|------|
| Replace | `.docs/prd.md` |
| Optional | `.docs/mvp.md` (deprecated banner only) |
| Read-only | `.docs/checklist.md`, `.docs/tech-stack.md`, `.docs/superpowers/specs/2026-06-03-prd-ai-agent-rewrite-design.md` |
| No change | `.cursor/rules/wallet-dev-workflow.mdc`, `.docs/checklist.md` body |

---

## Task 1: Branch and baseline

**Files:**
- Modify: (none)
- Read: `.docs/prd.md` (current on branch)

- [ ] **Step 1: Create feature branch**

```bash
cd /home/sebastian/my-projects/wallet-master
git checkout develop
git pull --ff-only origin develop 2>/dev/null || true
git checkout -b improvement/prd-ai-rewrite
```

- [ ] **Step 2: Snapshot old PRD line count and FR IDs**

```bash
wc -l .docs/prd.md
rg -o 'FR-[A-Z]+[0-9]+' .docs/prd.md | sort -u
```

Expected IDs (must all exist in new PRD):

```
FR-A1 FR-A2 FR-K1 FR-K2 FR-T1 FR-T2 FR-T3 FR-S1 FR-I1 FR-I2 FR-I3 FR-I4 FR-I5 FR-I6
```

- [ ] **Step 3: Record forbidden patterns to eliminate**

```bash
rg -n 'mvp\.md|\[Assumption\]|Options \+ Recommendation|## 18\. Appendix|Implementation note' .docs/prd.md
```

Expected after plan complete: **no matches** in `.docs/prd.md`.

---

## Task 2: Write PRD header, §0–§2

**Files:**
- Modify: `.docs/prd.md` (start fresh file or overwrite after backup)

- [ ] **Step 1: Replace file opening through §2**

Write from line 1 (overwrite entire file is OK in Task 6; here build incrementally or draft in one pass):

```markdown
# Wallet Master — wymagania produktowe (PRD)

> Jedyny kanoniczny dokument wymagań produktowych.
> Stack technologiczny i komendy deweloperskie: `.docs/tech-stack.md`

## 0. Dla implementujących (AI i zespół)

- **Źródło prawdy:** ten plik = *co* budujemy; `.docs/tech-stack.md` = *jak* uruchamiamy projekt i jaki stack.
- **MVP:** implementuj wymagania z **Priorytet: Must**, chyba że zadanie wyraźnie obejmuje **Should**.
- **Izolacja danych:** konta, transakcje, importy — wyłącznie w scope `user_id` zalogowanego użytkownika.
- **Konto usunięte (soft delete):** transakcje pozostają w historii, **read-only**; import i transfer na takie konto — zablokowane.
- **Import:** konto → upload CSV/XLSX → auto-mapowanie adaptera banku → auto-commit **bez podglądu**; `draft` to stan techniczny między uploadem a kolejką.
- **Dedupe (import):** klucz `date + amount + normalized_description` na koncie; pominięcie duplikatu przy imporcie; ręczne dodanie identycznej transakcji — dozwolone.
- **Saldo:** `current_balance` aktualizowane przy zmianach transakcji; komenda `accounts:recalculate-balance` jako safety net.
- **Lista transakcji:** filtry, sort i podsumowanie po dacie okresu (`booked_at`, UI: `COALESCE(booked_at, date)`); sumy wpływów/wydatków **bez** wewnętrznych transferów (`transfer_id` pusty).
- **Poza zakresem:** §3.2 — nie implementuj bez rozszerzenia tego dokumentu.

## 1. Słownik pojęć

(migrate all glossary bullets from old PRD lines 4–17; remove `[Assumption]` tags; keep definitions for: Konto, Typ konta, Bank, Transakcja, date, booked_at, Transfer, adjustment, Import, Mapowanie, Subject, Duplikat importu; add short note for „nowy użytkownik” = 7 dni od rejestracji for activation metric)
```

**§2 Streszczenie produktu** — 1 short paragraph from old §1 Overview (problem + time-to-value), no `mvp.md` reference.

- [ ] **Step 2: Verify §0–§2**

```bash
rg 'mvp\.md' .docs/prd.md && echo FAIL || echo OK
head -40 .docs/prd.md
```

---

## Task 3: Write §3 Scope and metrics

**Files:**
- Modify: `.docs/prd.md`

- [ ] **Step 1: Add §3.1 MVP Must list**

Bullet list of 7 key use cases from old §5 (rejestracja, konta, transakcje, lista, import, transfer, reset hasła as Should in §6).

- [ ] **Step 2: Add §3.2 Out of scope**

Copy bullets from old §3 Non-Goals **without** „Zgodnie z `.docs/mvp.md`”.

- [ ] **Step 3: Add §3.3 Metryki + tabela zdarzeń**

From old §2 Goals:
- 4 metrics with measurement rules
- Consolidated **Telemetry** table: columns `Zdarzenie | Kontekst | FR` — aggregate all `Telemetry/Events` from old FR-A1 through FR-I6 (grep old file before delete)

Example table row:

| Zdarzenie | Kontekst | FR |
|-----------|----------|-----|
| `import_completed` | Koniec importu | FR-I1 |

- [ ] **Step 4: Verify §3**

```bash
rg 'Zgodnie z|mvp' .docs/prd.md && echo FAIL || echo OK
rg '^## 3\.' .docs/prd.md
```

---

## Task 4: Write §4 Journeys and §5 Domain model

**Files:**
- Modify: `.docs/prd.md`

- [ ] **Step 1: §4.1 Persona** — from old §4 (remove `[Assumption]` on desktop/mobile; state as product rule: desktop priority, mobile web works)

- [ ] **Step 2: §4.2 Journeys A–D** — condensed from old §6 (happy path + critical alternatives only, no redundancy with FR AC)

- [ ] **Step 3: §5 Model domeny**

Include:
- Entity relationship list (User → Accounts, Transactions, Imports)
- Field tables for Account, Transaction, Import, AccountBalanceAdjustment
- Enum values: `Account.type`, `Account.bank`, `Transaction.type`, `Import.status`, `transfer_match_status`
- Dedupe hash rule + manual UUID suffix
- Import `details` JSON purpose

Source: old §12 Data Model + glossary.

- [ ] **Step 4: Verify §5 enums present**

```bash
rg 'BnpParibas|booked_at|transfer_match_status|draft.*queued' .docs/prd.md
```

---

## Task 5: Write §6.1–§6.2 (Auth + Accounts)

**Files:**
- Modify: `.docs/prd.md`

Use uniform FR template from spec for each requirement.

- [ ] **Step 1: FR-A1, FR-A2 in §6.1**

Migrate AC from old FR-A1/A2. Rules:
- Password: Laravel default rules (not „assumption”)
- Reset: no email enumeration; rate limit 6/min per IP (fact → §8 NFR if duplicated)

- [ ] **Step 2: FR-K1, FR-K2 in §6.2**

Migrate field list (name, currency PLN UI, opening_balance, type, bank, icons).
FR-K1: include `opening_balance` delta rule for `current_balance`.
FR-K2: **Decyzja** single line — soft-delete; transactions read-only; no Options block.

- [ ] **Step 3: Verify §6.1–6.2**

```bash
rg '### FR-A1|### FR-A2|### FR-K1|### FR-K2' .docs/prd.md
rg 'Options \+ Recommendation' .docs/prd.md && echo FAIL || echo OK
```

---

## Task 6: Write §6.3–§6.4 (Transactions + Balances)

**Files:**
- Modify: `.docs/prd.md`

- [ ] **Step 1: FR-T1, FR-T2, FR-T3 in §6.3**

Critical rules to preserve verbatim in **Reguły**:
- `booked_at` default = `date`; filters/summary on booked_at; list column COALESCE
- Summary excludes transfers; adjustments count by sign
- Manual duplicate allowed; `dedupe_hash` UUID for manual
- Transfer: 2 transactions, same date, opposite amounts, shared `transfer_id`
- Sort default: period date desc, tie `date desc, id desc`

- [ ] **Step 2: FR-S1 in §6.4**

**Decyzja:** stored `current_balance` + DB transactions on write + `accounts:recalculate-balance`.
Adjustment transaction + `account_balance_adjustments` audit.
Badge „Korekta” on list.

- [ ] **Step 3: Verify §6.3–6.4**

```bash
rg '### FR-T[123]|### FR-S1' .docs/prd.md
rg 'COALESCE\(booked_at|transfer_id IS NULL' .docs/prd.md
```

---

## Task 7: Write §6.5 Import (FR-I1–I6)

**Files:**
- Modify: `.docs/prd.md`

- [ ] **Step 1: FR-I1 through FR-I4**

Preserve:
- Auto-commit, no preview, adapter-only mapping
- Amount/date/encoding formats (bullet list from old FR-I1 edge cases)
- Cash account → 422 on import
- Dedupe FR-I3 normalization rules
- FR-I4: bank from account, no separate bank picker in import UI

Remove: `BankImportAdapter::defaultMapping`, `Implementation note` — use „adapter banku” wording.

- [ ] **Step 2: FR-I5 Typesense memory**

Behavior + AC from old FR-I5; product terms „pamięć opisów”; degradation if search service unavailable; per user+bank isolation.

- [ ] **Step 3: FR-I6 Transfer matcher**

AC for auto/manual/rejected/unlink; token list „konfigurowalna lista tokenów w aplikacji” (no `config/imports.php` path).
**Decyzja:** matcher synchronous after import commit on MVP (from old recommendation).

- [ ] **Step 4: Verify §6.5**

```bash
rg '### FR-I[1-6]' .docs/prd.md
rg 'config/imports|BankImportAdapter|Implementation note' .docs/prd.md && echo FAIL || echo OK
```

---

## Task 8: Write §6.6 template, §7–§9

**Files:**
- Modify: `.docs/prd.md`

- [ ] **Step 1: §6.6 Szablon FR-XX**

Copy empty template from spec (Pole/Priorytet/Domena/Zachowanie/AC/Reguły/Zdarzenia) + one-line instruction: assign next ID (e.g. FR-T4) and add row to Indeks.

- [ ] **Step 2: §7 UX, IA, copy**

From old §8–§9:
- Screens: Auth, Konta, Transakcje (+ modal Import, baner kandydatów transferu)
- Navigation: Konta, Transakcje (no top-level Import)
- UI language PL; date DD-MM-YYYY; amount formats; empty states; Reverb loader; a11y baseline; import summary copy pattern

- [ ] **Step 3: §8 NFR**

From old §10: performance (chunk 500, indexes), security (rate limits table), observability, encoding/parsers, file retention 30d failed imports, no raw import data in prod logs.

- [ ] **Step 4: §9 Tech constraints**

Max 10 lines + pointer to `.docs/tech-stack.md`. One sentence on import adapter architecture (enum Bank, resolver per account.bank). No Sail port table.

```bash
rg 'tech-stack\.md' .docs/prd.md -c
# Expected: 2 (header blockquote + §9)
```

---

## Task 9: Write §10–§12 and FR Index

**Files:**
- Modify: `.docs/prd.md`

- [ ] **Step 1: §10 Release boundaries**

MVP bullet list from old §14 (Must features only, compact).
Post-MVP one short list (categories, reports, multi-currency, etc.).

- [ ] **Step 2: §11 Risks**

Migrate 9 risks from old §15 (short table: Ryzyko | Mitygacja | FR).

- [ ] **Step 3: §12 Dependencies + Open Questions**

Dependencies: sample CSV/XLSX files, bank icons, mail env.
Open Questions (only unresolved): feature flags rollout, feedback channel, help screen content, profile settings post-MVP — **not** things already implemented.

- [ ] **Step 4: Indeks identyfikatorów FR**

Complete table all 14 IDs:

| ID | Tytuł | Priorytet | Sekcja |
|----|-------|-----------|--------|
| FR-A1 | Rejestracja i logowanie | Must | §6.1 |
| FR-A2 | Reset hasła | Should | §6.1 |
| FR-K1 | CRUD kont | Must | §6.2 |
| FR-K2 | Usunięcie konta | Must | §6.2 |
| FR-T1 | CRUD transakcji | Must | §6.3 |
| FR-T2 | Lista, filtry, podsumowanie | Must | §6.3 |
| FR-T3 | Transfer między kontami | Must | §6.3 |
| FR-S1 | Saldo i korekta | Must | §6.4 |
| FR-I1 | Import auto-commit | Must | §6.5 |
| FR-I2 | Typ ze znaku kwoty | Must | §6.5 |
| FR-I3 | Deduplikacja | Must | §6.5 |
| FR-I4 | Adaptery banków | Must | §6.5 |
| FR-I5 | Pamięć opisów | Should | §6.5 |
| FR-I6 | Matcher transferów | Should | §6.5 |

- [ ] **Step 5: Final line count**

```bash
wc -l .docs/prd.md
# Target: roughly 400-550 lines
```

---

## Task 10: Full verification pass

**Files:**
- Verify: `.docs/prd.md`

- [ ] **Step 1: Forbidden pattern scan**

```bash
cd /home/sebastian/my-projects/wallet-master
for pat in 'mvp\.md' '\[Assumption\]' 'Options \+ Recommendation' '## 18\. Appendix' 'Implementation note' 'Checklist review'; do
  echo "=== $pat ==="
  rg "$pat" .docs/prd.md || echo "(none)"
done
```

Expected: `(none)` for every pattern.

- [ ] **Step 2: FR ID completeness**

```bash
rg -o 'FR-[A-Z]+[0-9]+' .docs/prd.md | sort -u > /tmp/prd-fr-new.txt
comm -23 <(echo FR-A1 FR-A2 FR-K1 FR-K2 FR-T1 FR-T2 FR-T3 FR-S1 FR-I1 FR-I2 FR-I3 FR-I4 FR-I5 FR-I6 | tr ' ' '\n' | sort) /tmp/prd-fr-new.txt
# comm output must be empty (all IDs present)
```

- [ ] **Step 3: Checklist spot-check**

Manually confirm `.docs/checklist.md` sections 1–10 still align with FR rules (booked_at, dedupe, import no mapping UI, transfer banner FR-I6). No checklist edits required unless a bullet explicitly cited removed PRD section numbers (grep `§18` / `Appendix` in checklist).

```bash
rg 'Appendix|mvp\.md' .docs/checklist.md
```

- [ ] **Step 4: tech-stack reference count**

```bash
rg -c 'tech-stack\.md' .docs/prd.md
# Expect 2
```

---

## Task 11 (optional): Deprecate mvp.md

**Files:**
- Modify: `.docs/mvp.md`

- [ ] **Step 1: Add banner at top only**

```markdown
> **Deprecated:** Kanoniczne wymagania produktowe — `.docs/prd.md`. Ten plik nie jest utrzymywany.

```

Leave rest of file or trim to 3-line pointer—user preference minimal diff.

- [ ] **Step 2: Verify**

```bash
head -5 .docs/mvp.md
```

---

## Task 12: Commit

**Files:**
- Git: `.docs/prd.md`, optionally `.docs/mvp.md`

- [ ] **Step 1: Stage and commit** (only when user asked for commit)

```bash
git add .docs/prd.md
# optional: git add .docs/mvp.md
git status
git commit -m "$(cat <<'EOF'
docs: rewrite PRD for AI agents (single source of truth)

Restructure prd.md into 12 sections, preserve FR-* IDs, remove mvp
references and process artifacts; keep tech-stack.md as only external link.
EOF
)"
```

---

## Plan self-review (spec coverage)

| Spec requirement | Task |
|------------------|------|
| 12-section structure | Tasks 2–9 |
| §0 for implementers | Task 2 |
| Preserve FR-* IDs | Tasks 5–7, 9 |
| Only tech-stack link | Tasks 2, 8, 10 |
| Remove assumptions/options/appendix | Tasks 5–7, 10 |
| §6.6 template | Task 8 |
| FR Index | Task 9 |
| Open Questions | Task 9 |
| Optional mvp deprecated | Task 11 |
| Verification checklist | Task 10 |

No placeholders in plan steps.

---

## Execution handoff

Plan complete and saved to `.docs/superpowers/plans/2026-06-03-prd-ai-agent-rewrite.md`.

**Two execution options:**

1. **Subagent-Driven (recommended)** — fresh subagent per task, review between tasks  
2. **Inline Execution** — implement all tasks in this session (executing-plans)

Which approach do you want?
