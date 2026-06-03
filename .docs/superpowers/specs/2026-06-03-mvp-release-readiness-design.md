# MVP release readiness — design spec

**Status:** Approved (brainstorming 2026-06-03)  
**Goal:** First production release with Must FR verified, telemetry (PRD §3.3/§3.4), A11y/UX (PRD §7), and quality gates.  
**Sources:** `.docs/prd.md`, `.docs/checklist.md`, `.cursor/rules/wallet-dev-workflow.mdc`  
**Approach:** Stabilize → telemetry → A11y → light NFR → manual QA → bugfix → checklist sync (single-agent sequence on `develop` / `improvement/*` branches).

---

## 1. Scope

### In scope (before release)

| Area | Checklist | PRD |
|------|-----------|-----|
| Environment & conventions | §0 | §7 formats (DD-MM-YYYY, PL amounts) |
| Quality gates | §11 | §8 NFR (tests, static analysis, lint, log review) |
| Telemetry | §8, §13 | §3.3 metrics, §3.4 events, §8 observability |
| A11y & UX quality | §9 | §7 A11y, form UX |
| Manual QA | §10.2 | §4.2 journeys A–D |
| Light NFR | §12.1 (`api` rate limit), §12.3 (strict models dev) | §8 security |
| Feature test: import all-duplicates | §10.1 (if missing) | Journey B alt |

### Out of scope (defer post-release unless QA finds blocker)

- Import stress test 5000+ rows / `Lock wait timeout` (§6.4)
- `ImportController::index` pagination (§17)
- Full `$fillable` migration on all models beyond minimum for strict mode
- Post-MVP features (PRD §3.2)

### Optional during A11y pass (low cost)

- Transfer form: optional `subject` field (§5 partial; FR-T3 allows optional subject)

---

## 2. Phase order

```text
Phase 0: Pre-flight (§0, §11)
    ↓
Phase 1: Telemetry infrastructure + events (§8, §13)
    ↓
Phase 2: A11y & UX pass (§9)
    ↓
Phase 3: Light NFR + gap tests (§12, §10.1)
    ↓
Phase 4: Manual QA + bugfix + checklist sign-off (§10.2, §11)
```

**Rationale:** Wire telemetry before touching Vue pages extensively, so A11y work can add `track()` in one pass per screen.

---

## 3. Phase 0 — Pre-flight

### 3.1 Baseline (§0)

- [ ] Start stack: `./vendor/bin/sail up -d` (or `composer run dev` per `.docs/tech-stack.md`)
- [ ] Smoke: login → accounts index → transactions index (Inertia renders, no console errors)
- [ ] Confirm UI conventions already in use: “Transakcja” / “Konto”, dates DD-MM-YYYY, PL number formatting

### 3.2 Quality gates (§11)

Run on clean `develop`:

```bash
vendor/bin/pint --dirty --format agent
./vendor/bin/sail artisan test --compact
./vendor/bin/phpstan analyse
npm run lint   # if frontend touched in prior work
```

- [ ] Review logging: no raw import file contents or full row payloads in production log paths (handlers, `CommitImport`, exceptions)

**Exit criteria:** All green; no new PHPStan issues; environment documented for QA tester.

---

## 4. Phase 1 — Telemetry

Implements checklist §13 (canonical); §8 checkboxes align when events are wired.

### 4.1 Infrastructure

| Component | Location / behaviour |
|-----------|-------------------|
| `Event::record(string $name, array $payload, ?int $userId = null)` | `App\Telemetry\Event` — no HTTP, no Resources |
| Log channel `telemetry` | `config/logging.php` — `daily`, JSON line per event |
| API route | `POST /telemetry/event` — authenticated; throttle **60/min per user** (`RateLimiter::for('api', ...)`) |
| Frontend helper | `resources/js/lib/telemetry.ts` — `track(name, payload)` → Inertia/fetch POST |

**Payload conventions:**

- Always include `event` name (or rely on log context), ISO8601 `recorded_at`, `user_id` when known
- No PII beyond what PRD already stores (email only if already in auth events — prefer user id)
- Import events: counts and ids, not row content

### 4.2 Backend events (Actions / Auth controllers)

| Domain | Events |
|--------|--------|
| Auth | `user_registered`, `user_logged_in`, `user_login_failed`, `password_reset_requested`, `password_reset_completed` |
| Accounts | `account_created`, `account_updated`, `account_deleted`, `account_deleted_with_transactions`, `account_balance_adjusted` |
| Transactions | `transaction_created`, `transaction_updated`, `transaction_deleted` |
| Transfer UI | `transfer_created`, `transfer_failed_validation` |
| Transfer matcher | `transfer_auto_linked`, `transfer_manually_linked`, `transfer_unlinked`, `transfer_match_skipped_ambiguous` (migrate existing ad-hoc `Log::` to `Event::record` where applicable) |
| Import | `import_started`, `import_completed`, `import_failed`, `import_type_inferred`, `import_rows_skipped_duplicate`, `import_bank_resolved_from_account`, `import_headers_unrecognized`, `import_enrichment_typesense_hit`, `import_enrichment_typesense_miss` |

Call sites: write Actions after successful mutations; Auth on register/login failure; Import workflow at start/end/failure; matcher after link/unlink/skip.

### 4.3 Frontend events

| Event | Trigger |
|-------|---------|
| `transaction_create_opened` | Open create transaction form/modal |
| `transactions_filtered` | Apply filter (debounce or on submit — one event per user intent) |
| `transactions_sorted` | Change sort |
| `transactions_page_changed` | Pagination change |

Enables PRD §3.3 **time-to-add** (median `transaction_create_opened` → `transaction_created`) and list engagement metrics.

### 4.4 Tests

- Feature: `Event::record` writes to `telemetry` channel (fake log / `Log::fake`)
- Feature: `/telemetry/event` requires auth, respects throttle, validates event name allowlist or max payload size
- Existing domain tests still pass; optional assert critical events on happy path

**Exit criteria:** PRD §3.4 table covered; checklist §8 and §13 backend/frontend items checked.

---

## 5. Phase 2 — A11y & UX (§9)

Single pass per screen group (Polish copy, inline validation, loading/disabled, labels, keyboard, focus, WCAG AA contrast).

| Order | Screens | Focus |
|-------|---------|--------|
| 1 | Auth: login, register, reset | Forms, error announcements, focus trap none needed |
| 2 | Accounts: index, create, edit | Empty state + CTA, delete/balance dialogs |
| 3 | Transactions: index, create, edit | Filters, table, dialogs (delete, unlink transfer), `TransferCandidatesBanner` |
| 4 | Import modal | Steps, progress, failed rows, dismiss banner |
| 5 | Transfer form | Fields, validation, optional `subject` if added |
| 6 | Global layout | Nav keyboard order, skip link if missing, visible `:focus-visible` |

**Exit criteria:** Every §9 bullet satisfied on reviewed screens; no critical a11y blockers for Journey A–D.

---

## 6. Phase 3 — Light NFR & tests

| Item | Action |
|------|--------|
| §12.3 | `Model::shouldBeStrict()` in `AppServiceProvider::boot()` when not production; ensure `Transaction` and touched models use `$fillable` |
| §12.1 | `RateLimiter::for('api', ...)` — completed in Phase 1 if telemetry route exists |
| §10.1 | Pest: import file with only duplicates → `rows_imported = 0` and user-visible outcome |

**Exit criteria:** Tests added pass; strict mode does not break test suite.

---

## 7. Phase 4 — Manual QA & release sign-off

### 7.1 Scenarios (§10.2 ↔ PRD §4.2)

Execute in order; record PASS/FAIL + step for each.

| ID | Journey | Steps |
|----|---------|-------|
| QA-A | First use | Register → create account → add transaction → verify list + balance |
| QA-B | Import | Transactions → Import → pick account → upload BNP or mBank sample → committed → counters on UI → list updated |
| QA-B2 | Import alt | File with only duplicates → message, `rows_imported = 0` |
| QA-C | Transfer | Transfer between two accounts → two legs, balances correct |
| QA-D | Deleted account | Soft delete → transactions visible, no edit/delete/import/transfer |

### 7.2 Cross-checks during QA

- [ ] `storage/logs/telemetry-*.log` (or configured path) contains events for QA-A through QA-C
- [ ] Tab through QA-A and QA-B without focus traps; modals escapable
- [ ] Cash account import → 422 toast, not 500

### 7.3 Bugfix loop

- Fix on `improvement/fix-<issue>`
- Re-run affected Pest tests + §11 gates
- Re-test failed QA steps only

### 7.4 Checklist sync

On `improvement/*` merge to `develop`, update `.docs/checklist.md` checkboxes for §0, §8, §9, §10.1 (added tests), §10.2, §11, §13.

**Release decision:** All QA scenarios PASS; §11 green on release commit.

---

## 8. Implementation branches (Superpowers)

| Phase | Suggested branch | Plan file (after this spec) |
|-------|------------------|----------------------------|
| 1 | `improvement/telemetry` | `.docs/superpowers/plans/2026-06-03-telemetry.md` |
| 2 | `improvement/a11y-mvp` | `.docs/superpowers/plans/2026-06-03-a11y-mvp.md` |
| 3 | `improvement/release-nfr-tests` | `.docs/superpowers/plans/2026-06-03-release-nfr-tests.md` |
| 4 | QA fixes | `improvement/fix-*` as needed |

Phase 0 has no separate plan — execute locally and document PASS in checklist or QA notes.

---

## 9. Verification commands (workflow)

After each PHP-changing phase:

```bash
vendor/bin/pint --dirty --format agent
./vendor/bin/sail artisan test --compact [--filter=Telemetry|Accounts|...]
./vendor/bin/phpstan analyse   # before merge when types/architecture touched
```

Frontend-heavy phase (A11y):

```bash
npm run lint
npm run format   # if project uses format check on CI
```

---

## 10. Risks

| Risk | Mitigation |
|------|------------|
| Telemetry noise / PII in logs | Allowlist event names; strip row content from import payloads |
| A11y scope creep | Stick to §9 checklist screens only |
| QA blocked by env | Complete §0 before Phase 4; document sample CSV paths |
| Double work on Vue files | Strict phase order: telemetry helper first, A11y second |

---

## 11. Success criteria (release ready)

1. PRD Must journeys A–D pass manual QA (§10.2).
2. PRD §3.4 events recordable in production via `telemetry` channel.
3. PRD §7 A11y baseline met (§9 complete).
4. Automated suite + PHPStan + log review (§11) green on release tag/commit.
5. `.docs/checklist.md` synchronized with reality.

---

## 12. Next step

Invoke **writing-plans** skill to produce implementation plans for Phases 1–3 (Phase 0 and 4 are procedural). Recommended first plan: `2026-06-03-telemetry.md`.
