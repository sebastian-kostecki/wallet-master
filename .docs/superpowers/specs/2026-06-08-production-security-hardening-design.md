# Production security hardening — design spec

> Approved: 2026-06-08. Scope: application code and Laravel config only (VPS/containers infra out of scope).

## Context

Wallet Master pre-release (wave 2). First production deployment on VPS with Docker containers. Closed registration (`REGISTRATION_ENABLED=false`), no 2FA in v1.

## Goals

1. Close gaps vs PRD §8 (OWASP baseline, rate limits, policies, logs, mass assignment).
2. Add scheduled backups via `spatie/laravel-backup` (local disk; S3-ready config).
3. Expand security test coverage for isolation and throttling.

## Out of scope

- VPS/nginx/TLS/firewall/container orchestration.
- 2FA, field-level DB encryption, WAF, S3 upload (config hook only).
- GDPR data export/deletion product features.

## Code changes

### Rate limiting (PRD §8)

- `throttle:6,1` on `POST /login`, `POST /forgot-password`, `POST /reset-password`.
- Keep existing `LoginRequest` lockout (5 failures per email|ip) as defence in depth.
- Feature tests for login and password-reset throttling.

### Mass assignment

- Remove `$guarded = []` from `Transaction` (keep `$fillable`).
- Replace `Currency::$guarded = []` with explicit `$fillable`.

### Production logs

- Remove `description_raw` from `CommitImport` debug log (telemetry already records reason codes).
- Document prod env in `.env.example`: `APP_DEBUG=false`, `LOG_LEVEL=info`, `SESSION_SECURE_COOKIE`, `SESSION_ENCRYPT`.

### Security headers (app middleware)

- `X-Frame-Options: SAMEORIGIN`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy` (minimal)
- CSP compatible with Inertia + Bunny fonts (same-origin scripts, Bunny for fonts/styles)

### Session / proxy

- `TrustProxies` for reverse proxy in container setup.
- Session hardening via env (documented, not enforced in code).

### Horizon

- `viewHorizon` gate reads `HORIZON_ALLOWED_EMAILS` (comma-separated) from config.

### Registration

- Existing middleware + tests; no code change required.

### Isolation tests (gaps)

- Categories: edit/update/delete cross-user.
- Pockets: show/delete cross-user.
- Budget: monthly/yearly cross-user (no data leak).
- Transfer unlink cross-user.
- Import commit rate limit.

## Backup (`spatie/laravel-backup`)

- Package: `spatie/laravel-backup`.
- Contents: MySQL dump + `storage/app/private` (failed import files).
- Destination disk: `backups` → `storage/app/backups/` (local).
- Retention: 7 daily backups (default Spatie strategy).
- Scheduler: daily `backup:run`, daily `backup:clean`, daily `backup:monitor`.
- Email notification on backup failure (uses `MAIL_*`).
- Env `BACKUP_DISK=backups`; future S3 = add disk + change env.

## Ops note (informational, not implemented in repo)

Reverse proxy must terminate TLS and set `X-Forwarded-*`. Mount persistent volume for `storage/app/backups` if backups should survive container recreation.

## Verification

- `./vendor/bin/sail artisan test --compact` (security-related filters).
- `vendor/bin/pint --dirty --format agent`.
- Update `.docs/checklist.md` §12.
