# Wallet Master

<p align="center">
  <a href="#license"><img src="https://img.shields.io/badge/License-MIT-blue?style=for-the-badge" alt="License: MIT"></a>
  <a href="#documentation"><img src="https://img.shields.io/badge/Status-pre--release-orange?style=for-the-badge" alt="Status: pre-release"></a>
  <a href="https://www.docker.com/" target="_blank"><img src="https://img.shields.io/badge/Docker-2496ED?style=for-the-badge&logo=docker&logoColor=white" alt="Docker"></a>
  <a href="https://www.php.net/" target="_blank"><img src="https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP"></a>
  <a href="https://laravel.com/" target="_blank"><img src="https://img.shields.io/badge/Laravel-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel"></a>
  <a href="https://vuejs.org/" target="_blank"><img src="https://img.shields.io/badge/Vue.js-4FC08D?style=for-the-badge&logo=vue.js&logoColor=white" alt="Vue.js"></a>
</p>

**Personal household budgeting — accounts, imports, categories, and savings envelopes.**

Wallet Master is a web application for managing a personal or household budget. It is built for users who export bank statements as CSV or XLSX from Polish banks (mBank, BNP Paribas) and want a fast path from import to a clear picture of spending, plans, and savings goals. The interface is desktop-first; mobile web should work but is not the primary design target.

## Why Wallet Master

- **Fast import** — Upload CSV/XLSX from supported banks; automatic column mapping, deduplication, and background processing with live status updates.
- **Full control** — Manual income and expense entries, internal transfers between accounts, and balance corrections when your statement and the app disagree.
- **Plan vs actual** — P&L categories with monthly and yearly budget views. Estimates are informational — not hard spending limits.
- **Savings envelopes (Pockets)** — Named goals linked to savings-account transfers; track how much you set aside, withdraw, and have left.

## Features

### Accounts

Create and manage accounts with a name, type, bank, and balance. **Checking (ROR)** and **Savings** types are supported. Banks: **mBank**, **BNP Paribas**, and **Cash** (manual entry only — file import is not available for Cash accounts). Deleting an account hides it from your list but keeps its transactions in history as read-only.

<!-- screenshot: accounts-list -->
*(Screenshot coming soon)* — Accounts list

### Transactions

Record income, expenses, and balance adjustments. Each P&L transaction requires a category. The transaction list supports filtering by account and period date, sorting, and pagination. Summary totals for a period show inflows and outflows and **exclude internal transfers** so your spending picture stays accurate.

<!-- screenshot: transactions-filters -->
*(Screenshot coming soon)* — Transaction list with filters and summary

### Import

From the transactions screen, pick an account and upload a CSV or XLSX bank export. The app detects the bank adapter from the account, maps columns automatically, and commits the import without a manual preview step. When finished, you see how many rows were imported, how many duplicates were skipped, and how many rows failed validation. An optional transfer matcher can suggest pairs of opposite amounts across your accounts after import.

<!-- screenshot: import-result -->
*(Screenshot coming soon)* — Import result summary

### Transfers

A single transfer action creates two linked transactions: an outflow on the source account and an inflow on the destination. Transfers between checking accounts have no category or pocket. When a **Savings** account is involved, you must assign the same **Pocket** on both legs so savings progress stays tied to the right goal.

### Categories

Build your own P&L catalog of income and expense categories with icons and colors. A starter set is created on first use. You can reorder categories. On import, the app remembers your category choices per normalized bank description and applies them on the next import; otherwise it falls back to your first category of the matching type.

### Budget

- **Monthly view** — Plan vs actual for each P&L category in the selected month. Edit monthly plan overrides inline. A **Pockets** section shows plan, saved, released, and balance per active savings envelope.
- **Yearly view** — Annual estimate vs actual per category for the calendar year. P&L plan editing lives on the budget screens, not on the categories screen.

<!-- screenshot: budget-monthly -->
*(Screenshot coming soon)* — Monthly budget (categories + pockets)

### Pockets (savings envelopes)

Pockets are separate from P&L categories. Use them for goals such as a vacation fund or emergency buffer. Each pocket can have an optional target amount and a planning mode: fixed monthly contribution or target date (the app recommends a monthly amount from your progress and deadline). Currency is set at creation and cannot be changed later (MVP: PLN only in the UI). Archive a pocket to hide it from default lists and the monthly budget section.

<!-- screenshot: pockets-list -->
*(Screenshot coming soon)* — Pockets list

### Privacy and data

All accounts, transactions, imports, categories, and pockets belong to the logged-in user only. There is no sharing between users. Authentication uses a standard email-and-password session.

## Typical workflows

**First use** — Register, create an account, add a transaction, and confirm the list and balance update.

**Import a statement** — Open Transactions → Import → choose the account → upload your CSV/XLSX → wait for automatic processing → review the result counters and updated list.

**Save for a goal** — Create a Pocket (e.g. Vacation), transfer from checking to savings with that pocket assigned, and check the monthly budget to see saved, released, and remaining balance.

## What's not included (yet)

- Multi-currency UI or automatic currency conversion (PLN only in the interface today)
- Direct bank API integrations
- Native mobile apps
- Data export or file attachments on transactions
- Import from PDF, MT940, or scanned documents
- AI-powered category suggestions
- Shared or multi-user household accounts

## Documentation

- [Product requirements (detailed)](.docs/prd.md)
- **Developers:** setup, stack, and commands → [`.docs/tech-stack.md`](.docs/tech-stack.md)

## License

MIT
