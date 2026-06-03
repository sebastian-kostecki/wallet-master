# Transfer Edit Guards + Unlink on Edit — Design Spec

**Date:** 2026-06-03  
**Status:** Approved (follow-up to transfer matcher spec)  
**Related:** `.docs/prd.md` FR-I6 (unlink), FR-T1; `.docs/checklist.md` §4 (edycja transferu), §6.6 (przycisk Edit); `.docs/superpowers/specs/2026-06-02-transfer-matcher-banner-design.md` (non-goals lifted)

## Problem

Połączone transakcje transferu (`transfer_id` ustawiony) można edytować w `transactions/Edit` jak zwykłe wpisy — w tym **kwotę**, która jest zamrożona w parze (−X / +X). Backend (`UpdateTransaction`) utrzymuje `type = transfer` przy zmianie kwoty, co łamie regułę produktową: kwotę zmienia się dopiero po rozłączeniu pary. Endpoint `POST /transfers/{transferId}/unlink` i testy istnieją, ale **brak akcji w UI** na stronie edycji.

## Goals

1. Zablokować zmianę **kwoty** i **konta** dla transakcji z `transfer_id !== null` (API + UI).
2. Nadal pozwolić edytować opis, subject, daty (`date`, `booked_at`) na pojedynczej nodze — bez synchronizacji drugiej nogi (YAGNI).
3. Dodać na `Edit.vue` sekcję z przyciskiem **„Rozłącz transfer”** + dialog potwierdzenia → istniejący route `transfers.unlink`.
4. Po unlink: toast `transfers.toast.unlinked`, odświeżenie strony edycji (ta sama transakcja, teraz `type` income/expense).

## Non-Goals

- Synchronizacja opisu/dat między obiema nogami transferu.
- Edycja „transferu jako całości” (wspólny formularz dla dwóch kont).
- Zmiana zachowania `DeleteTransaction` (już usuwa obie nogi).
- Telemetria poza istniejącym `transfer_unlinked` w `UnlinkTransfer`.

## Decisions

| Topic | Decision |
|-------|----------|
| Warstwa walidacji | `UpdateTransaction::handle()` — porównanie `amount` / `account_id` przed zapisem; `ValidationException` na `amount` / `account_id` |
| UI | `amount` + `account` disabled; baner informacyjny + `UnlinkTransferDialog` |
| Autoryzacja unlink | Bez zmian — `UnlinkTransfer` filtruje po `user_id` |
| Redirect po unlink | `TransferController::unlink` → `back()` (POST z Edit) |
| Korekta (`adjustment`) | Poza scope — `transfer_id` zwykle null |

## Acceptance

1. Given transakcja z `transfer_id` When PUT z inną kwotą Then 422, saldo bez zmian.
2. Given transakcja z `transfer_id` When PUT z innym `account_id` Then 422.
3. Given transakcja z `transfer_id` When PUT z nowym opisem Then 200, `type` nadal `transfer`.
4. Given Edit z `transfer_id` When „Rozłącz transfer” + confirm Then obie nogi bez `transfer_id`, typy ze znaku kwoty, toast sukcesu.
5. Given transakcja bez `transfer_id` When edycja kwoty Then bez regresji (istniejące testy).
