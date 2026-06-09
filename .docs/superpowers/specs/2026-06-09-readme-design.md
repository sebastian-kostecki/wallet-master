# README — design spec

**Date:** 2026-06-09  
**Status:** Approved (brainstorming)  
**Output file:** `README.md` (repository root)

## Goal

Create a **GitHub discovery README** for end users who find the repository. The document explains what Wallet Master is, what it does today, and how typical users benefit — without developer setup instructions.

## Audience & constraints

| Decision | Choice |
|----------|--------|
| Primary audience | End users (not developers) |
| Language | English |
| Product scope | Current full product: MVP + categories, budget views, savings envelopes (pockets) |
| Purpose | GitHub discovery — product pitch + feature overview |
| Visuals | Placeholder sections for screenshots (to be added later) |
| Structure | Hybrid: pitch → value props → features → user journeys → limitations → footer |

### Out of scope for README

- Sail / Docker setup, migrations, env configuration
- Architecture (Variant A, Actions, domains)
- Test commands, Pint, PHPStan, CI
- Full FR catalog or acceptance criteria

Developer and contributor details remain in `.docs/tech-stack.md`, `.cursor/rules/`, and `.docs/checklist.md`. The README may link to these in one line at the footer only.

## Source documents

| Document | Use in README |
|----------|----------------|
| `.docs/prd.md` §2 (summary), §4 (journeys), §7 (UX/IA), §3.2 (out of scope), §10 (release boundaries) | Product copy, feature names, limitations |
| `.docs/tech-stack.md` | License reference only (MIT via `composer.json`) |
| `.cursor/rules/wallet-dev-workflow.mdc` | Confirm docs paths for footer links |

## README structure

### 1. Header and pitch

- **Title:** Wallet Master
- **Tagline:** Personal household budgeting — accounts, imports, categories, and savings envelopes
- **Short description (2–3 sentences):**
  - Web app for personal/household budgeting
  - Optimized for Polish bank exports (mBank, BNP Paribas) and PLN
  - Desktop-first; mobile web should work but is not the primary target
- Optional single-line status note: pre-release / active development (no version numbers)

### 2. Value propositions (4 bullets)

1. **Fast import** — Upload CSV/XLSX from supported banks; automatic column mapping, deduplication, and async processing with live status
2. **Full control** — Manual income/expense entries, internal transfers, balance corrections
3. **Plan vs actual** — P&L categories with monthly and yearly budget views (estimates, not hard limits)
4. **Savings envelopes** — Pockets linked to savings-account transfers; track progress toward optional targets

### 3. Feature overview

Grouped subsections with 2–4 sentences each. Use user-facing language; avoid Laravel/Inertia/MySQL terms.

#### Accounts

- Create and manage bank accounts: ROR (checking) and Savings
- Banks: mBank, BNP Paribas, Cash (manual-only; no file import for Cash)
- Opening and current balance; soft-delete keeps transaction history read-only

#### Transactions

- Income, expense, and balance adjustments with required P&L category
- List with account and period-date filters, sort, pagination
- Period summary (inflows/outflows) excludes internal transfers

#### Import

- Select account → upload CSV/XLSX → automatic bank adapter mapping → commit without preview step
- Result counters: imported, duplicates skipped, validation errors
- Optional transfer matcher suggests cross-account transfer pairs after import

#### Transfers

- One action creates two linked transactions (outflow + inflow)
- ROR↔ROR transfers have no category or pocket
- Transfers involving a Savings account require a pocket on both legs

#### Categories

- Personal P&L catalog (income/expense) with icons and colors
- Starter set on first use; reorder supported
- Import remembers category per normalized bank description (fallback when no memory)

#### Budget

- **Monthly view:** plan vs actual per category; editable monthly overrides; pockets section (plan / saved / released / balance)
- **Yearly view:** annual estimate vs actual per category; P&L plan editing lives on budget screens, not on the categories screen

#### Pockets (savings envelopes)

- Separate from P&L categories; optional target amount and planning mode (monthly contribution or target date)
- Currency fixed at creation (MVP: PLN only in UI)
- Archive to hide from default lists and budget section

#### Privacy & data

- All data scoped to the logged-in user
- No sharing between users
- Session-based authentication

### 4. User journeys (3 short scenarios)

**A — First use:** Register → create an account → add a transaction → see list and balance

**B — Import:** Transactions → Import → pick account → upload statement → automatic import → counters and updated list/balance

**C — Savings goal:** Create pocket (e.g. Vacation) → monthly transfer checking → Savings with pocket assigned → monthly budget shows saved/released/balance

### 5. Screenshot placeholders

Five placeholder blocks. Use visible caption text `*(Screenshot coming soon)*` plus HTML comment for maintainers:

```markdown
<!-- screenshot: accounts-list -->
*(Screenshot coming soon)* — Accounts list
```

| Placeholder ID | Caption |
|----------------|---------|
| `accounts-list` | Accounts list |
| `transactions-filters` | Transaction list with filters and summary |
| `import-result` | Import result summary |
| `budget-monthly` | Monthly budget (categories + pockets) |
| `pockets-list` | Pockets list |

No image files in this iteration.

### 6. What's not included (limitations)

Short honest list derived from PRD §3.2:

- PLN only in UI (no multi-currency display or conversion)
- No direct bank API integrations
- No native mobile apps
- No data export or attachments
- Import formats: CSV/XLSX only (not PDF, MT940, OCR)
- No AI category suggestions
- No shared household / multi-user accounts

### 7. Footer

- **Detailed requirements:** link to `.docs/prd.md` (relative path on GitHub)
- **Developers:** one line — setup and commands in `.docs/tech-stack.md`
- **License:** MIT

## Terminology

| PRD / UI (PL) | README (EN) |
|---------------|-------------|
| Kieszenie | Pockets (savings envelopes) — use **Pockets** as primary term; parenthetical “savings envelopes” on first mention |
| Konto ROR | Checking account (ROR) |
| Konto oszczędnościowe | Savings account |
| Szacunek | Estimate / plan (not “budget limit”) |
| Budżet | Budget view |

## Tone & format

- English, clear and concise (GitHub README style)
- No emoji in headings (optional in bullets only if consistent with repo — default: none)
- Target length: ~120–150 lines
- Standard Markdown; GitHub-flavored tables allowed in feature overview if it improves scanability

## Acceptance criteria

1. `README.md` exists at repository root.
2. Document is entirely in English and readable without opening `.docs/`.
3. No developer setup steps in the body (footer link only).
4. All eight feature groups and three user journeys are present.
5. Five screenshot placeholders are present with HTML comments.
6. Limitations section matches PRD out-of-scope themes (paraphrased, not copy-paste of FR IDs).
7. Footer links to `.docs/prd.md` and `.docs/tech-stack.md`.

## Implementation note

After this spec is approved, create an implementation plan via the writing-plans skill. Implementation is a single file write (`README.md`); no code or test changes required.
