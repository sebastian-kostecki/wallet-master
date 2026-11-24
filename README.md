<div align="center">

# Wallet Master

**Personal household budgeting — accounts, bank statement imports, categories and savings envelopes.**

[![Status: pre-release](https://img.shields.io/badge/Status-pre--release-orange?style=for-the-badge)](#project-status)
[![Tests](https://img.shields.io/github/actions/workflow/status/sebastian-kostecki/wallet-master/tests.yml?branch=master&style=for-the-badge&label=Tests&logo=githubactions&logoColor=white)](https://github.com/sebastian-kostecki/wallet-master/actions/workflows/tests.yml)
[![Lint](https://img.shields.io/github/actions/workflow/status/sebastian-kostecki/wallet-master/lint.yml?branch=master&style=for-the-badge&label=Lint&logo=githubactions&logoColor=white)](https://github.com/sebastian-kostecki/wallet-master/actions/workflows/lint.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue?style=for-the-badge)](LICENSE)

[![PHP 8.5](https://img.shields.io/badge/PHP_8.5-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![Laravel 13](https://img.shields.io/badge/Laravel_13-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com/)
[![Inertia 2](https://img.shields.io/badge/Inertia_2-9553E9?style=for-the-badge&logo=inertia&logoColor=white)](https://inertiajs.com/)
[![Vue 3](https://img.shields.io/badge/Vue_3-4FC08D?style=for-the-badge&logo=vuedotjs&logoColor=white)](https://vuejs.org/)
[![TypeScript](https://img.shields.io/badge/TypeScript-3178C6?style=for-the-badge&logo=typescript&logoColor=white)](https://www.typescriptlang.org/)
[![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Redis](https://img.shields.io/badge/Redis-DC382D?style=for-the-badge&logo=redis&logoColor=white)](https://redis.io/)
[![Docker](https://img.shields.io/badge/Docker-2496ED?style=for-the-badge&logo=docker&logoColor=white)](https://www.docker.com/)

[Product requirements](.docs/prd.md) · [Tech stack](.docs/tech-stack.md) · [Report a bug](https://github.com/sebastian-kostecki/wallet-master/issues/new)

**English** · [Polski](README.pl.md)

</div>

## About

Wallet Master is a web application for managing a personal or household budget. It is built for people who export bank statements as CSV or XLSX from Polish banks (mBank, BNP Paribas) and want a fast path from import to a clear picture of spending, plans and savings goals. The interface is desktop-first; mobile web works but is not the primary design target.

> [!NOTE]
> Wallet Master is the predecessor of [Domowy Skarbnik](https://github.com/sebastian-kostecki/household-treasurer), a yearly household budget that runs in production.

## Why Wallet Master

- **Fast import** — upload CSV/XLSX from supported banks; automatic column mapping, deduplication and background processing with live status updates.
- **Full control** — manual income and expense entries, internal transfers between accounts and balance corrections when your statement and the app disagree.
- **Plan vs actual** — P&L categories with monthly and yearly budget views. Estimates are informational, not hard spending limits.
- **Savings envelopes (Pockets)** — named goals linked to savings-account transfers; track how much you set aside, withdraw and have left.

## Features

### Accounts

Create and manage accounts with a name, type, bank and balance. **Checking (ROR)** and **Savings** types are supported. Banks: **mBank**, **BNP Paribas** and **Cash** (manual entry only — file import is not available for Cash accounts). Deleting an account hides it from your list but keeps its transactions in history as read-only.

### Transactions

Record income, expenses and balance adjustments. Each P&L transaction requires a category. The list supports filtering by account and period, sorting and pagination. Period totals show inflows and outflows and **exclude internal transfers**, so your spending picture stays accurate.

### Import

From the transactions screen, pick an account and upload a CSV or XLSX bank export. The app picks the bank adapter from the account, maps columns automatically and commits the import without a manual preview step. When it finishes you see how many rows were imported, how many duplicates were skipped and how many rows failed validation. An optional transfer matcher suggests pairs of opposite amounts across your accounts.

### Transfers

A single transfer creates two linked transactions: an outflow on the source account and an inflow on the destination. Transfers between checking accounts have no category or pocket. When a **Savings** account is involved, both legs must have the same **Pocket**, so savings progress stays tied to the right goal.

### Categories

Build your own catalogue of income and expense categories with icons and colours. A starter set is created on first use, and you can reorder categories. On import, the app remembers your category choice per normalised bank description and applies it next time; otherwise it falls back to your first category of the matching type.

### Budget

- **Monthly view** — plan vs actual for each P&L category in the selected month, with inline monthly plan overrides. A **Pockets** section shows plan, saved, released and balance per active savings envelope.
- **Yearly view** — annual estimate vs actual per category for the calendar year.

### Pockets (savings envelopes)

Pockets are separate from P&L categories. Use them for goals such as a holiday fund or an emergency buffer. Each pocket can have a target amount and a planning mode: a fixed monthly contribution or a target date (the app recommends a monthly amount from your progress and deadline). Currency is set at creation (PLN in the UI). Archive a pocket to hide it from default lists and the monthly budget.

### Privacy and data

All accounts, transactions, imports, categories and pockets belong to the signed-in user only; there is no sharing between users. Authentication uses a standard e-mail and password session.

## Typical workflows

1. **First use** — register, create an account, add a transaction and confirm the list and balance update.
2. **Import a statement** — Transactions → Import → choose the account → upload your CSV/XLSX → wait for processing → review the counters and the updated list.
3. **Save for a goal** — create a Pocket (e.g. Holiday), transfer from checking to savings with that pocket and check the monthly budget for saved, released and remaining amounts.

## Tech stack

| Layer | Technology |
| --- | --- |
| Backend | PHP 8.5, Laravel 13, Inertia Laravel 2, Ziggy, Horizon, Reverb |
| Frontend | Vue 3, TypeScript, Inertia Vue 2, Vite, Tailwind CSS 3, Radix Vue, Headless UI |
| Data | MySQL 8.4, Redis (queues), Typesense |
| Quality | Pest 4 / PHPUnit 12, Larastan, Laravel Pint, ESLint 9, Prettier 3, vue-tsc |

## Getting started

### Prerequisites

- Docker ([Laravel Sail](https://laravel.com/docs/sail)): PHP 8.5, MySQL, Redis, Typesense and Mailpit run in containers

### Installation

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

Without the full Docker stack, `composer run dev` starts `serve`, `queue:listen`, `pail` and `vite` together.

## Development

| Command | What it does |
| --- | --- |
| `sail artisan test --compact` | Pest suite |
| `vendor/bin/pint --dirty --format agent` | Code style |
| `vendor/bin/phpstan analyse` | Larastan static analysis |
| `npm run lint` / `npm run format` | ESLint and Prettier |
| `npm run build:ssr` | Client and SSR bundles |

CI runs the [`tests`](.github/workflows/tests.yml) and [`linter`](.github/workflows/lint.yml) workflows on pushes and pull requests to `develop` and `master`.

## Not included (yet)

- Multi-currency UI or automatic currency conversion (PLN only in the interface)
- Direct bank API integrations
- Native mobile apps
- Data export or file attachments on transactions
- Import from PDF, MT940 or scanned documents
- AI-powered category suggestions
- Shared or multi-user household accounts

## Documentation

- [Product requirements](.docs/prd.md) and [MVP scope](.docs/mvp.md)
- [Tech stack, Sail services and commands](.docs/tech-stack.md)
- [`AGENTS.md`](AGENTS.md) — rules for coding agents

## Project status

Pre-release: there is no tagged version and no public deployment. Active development of the household budget continues in [Domowy Skarbnik](https://github.com/sebastian-kostecki/household-treasurer).

## License

Released under the [MIT License](LICENSE).
