# Transactions index — raw statement tooltip

**Date:** 2026-06-02  
**Status:** Approved (brainstorming)  
**Scope:** `resources/js/pages/transactions/Index.vue` + `TransactionResource` + tests

## Problem

On the transactions list, users cannot quickly verify whether `subject` and `description` match the original bank statement text. That metadata exists as `raw_statement_description` and is already shown on the edit page, but not on the index.

## Goals

- Show bank statement context on the list without changing table layout (no new columns or badges).
- Enable fast post-import review: hover/tap the description block to see raw text and full edited fields when truncated.
- Only surface this when `raw_statement_description` is non-empty.

## Non-goals

- Filter “needs review” or diff highlighting.
- Link to import details from the row.
- Lazy-load raw text via separate API.
- Expose `import_id` on index props (not required for display rule).

## Decisions (from brainstorming)

| Topic | Choice |
|-------|--------|
| When to enable | Non-empty `raw_statement_description` only |
| Trigger | Whole description block: icon + subject + description |
| Desktop interaction | `cursor-pointer` + tooltip on hover |
| Mobile interaction | Tap opens same tooltip |
| Pointer / tooltip | Always when raw exists (not only when truncated) |
| Tooltip content | Raw statement + full subject/description when truncated |

## Architecture

### Data flow

```
TransactionIndexRequest
  → ListTransactions (unchanged query)
  → TransactionResource (add raw_statement_description)
  → Index.vue (conditional wrapper tooltip)
```

Follows Variant A: Resource shapes Inertia props; no Action/Resource coupling changes.

### Backend

**`App\Http\Resources\Transactions\TransactionResource`**

Add to `toArray()`:

```php
'raw_statement_description' => $this->raw_statement_description,
```

Nullable string; omit or null for manual transactions without import raw text.

**`ListTransactions`**

No query changes — column already selected via `transactions.*` / default model attributes.

### Frontend

**Type `Transaction`** in `Index.vue`:

```ts
raw_statement_description: string | null;
```

**Desktop table (`md+`) — description cell**

1. **No raw** (`!raw_statement_description?.trim()`): keep current behavior — separate `Tooltip` on subject/description only when `truncateText(...).isTruncated`.

2. **Has raw**: wrap the entire description block (icon column + text column) in one `TooltipProvider` / `Tooltip`:
   - `TooltipTrigger`: outer `div` with `cursor-pointer` and `class="... min-w-0"` covering icon + texts.
   - Remove inner subject/description tooltips (avoid nested/conflicting triggers).
   - `TooltipContent` sections (top to bottom):
     - **Statement:** label `t('transactions.edit.statement.title')`, body = full `raw_statement_description` (`whitespace-pre-wrap break-words`, `max-w-md`).
     - **Subject (if truncated):** label e.g. `t('transactions.index.table.description')` or dedicated key `transactions.index.tooltip.subject`, body = full `tx.subject`.
     - **Description (if truncated):** label for description field, body = full `tx.description`.
   - Use same `truncateText` thresholds as today (subject 80, description 120 on desktop).

**Mobile cards (`md:hidden`)**

Same wrapper rules on the card’s title/subject block (adapt truncate limits: description 90, subject 70).

**Tooltip component**

- `delay-duration="0"` (consistent with existing index tooltips).
- Rely on shadcn/radix tooltip tap behavior for mobile (option A).

### i18n

- Reuse `transactions.edit.statement.title` for raw section header.
- Reuse `transactions.edit.statement.description` as optional muted subtitle under header, or skip to save space.
- Add `transactions.index.a11y.showStatementDescription` for `aria-label` on trigger (PL/EN).

### Accessibility

- `aria-label` on block trigger when raw exists.
- Tooltip content readable: `break-words`, sufficient `max-w`.

## Testing

**Feature — `tests/Feature/Transactions/TransactionIndexTest.php`**

- Transaction with `raw_statement_description` → Inertia `transactions.data[0].raw_statement_description` equals stored value.
- Manual transaction without raw → `null` or absent (match existing Resource null conventions).

No browser/E2E test required for MVP; manual check desktop hover + mobile tap.

## Verification checklist

- [x] Imported row with raw: pointer on description block, tooltip shows raw + truncated fields.
- [x] Manual row: no pointer, truncation tooltips unchanged.
- [x] Mobile tap opens tooltip.
- [x] `vendor/bin/pint --dirty` on touched PHP.
- [x] `php artisan test --compact --filter=raw_statement_description`.

## Payload note

`raw_statement_description` is sent for every row on the current page that has it. Acceptable for typical `per_page` (15–50). No pagination change.
