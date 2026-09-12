# New Transaction button — enabled for all ISSUED states

**Date:** 2026-06-03 · **Branch:** fix/motor-coverage

## What changed

The **+ New Transaction** button (Policy → Policy Actions) is now enabled
whenever the **action currently in view** is `ISSUED` — or `LAPSED`, so
REISSUE/REINSTATE stay possible. This covers every issued transaction type:

- `NEWBUSINESS`
- `RENEW` / `ANNIVERSARY-RENEW`
- `REINSTATE` / `REISSUE`
- `CANCEL`
- `ENDORSE`

## Why

Previously the gate keyed off the policy's **latest** action (`data.current`)
and additionally blocked on **any** stranded `QUOTE` anywhere in history.

That broke a legitimate workflow: a DOM/COM policy can have a later action
sitting in `QUOTE` (e.g. a future-cycle renewal quote) while the renewal the
underwriter is actually looking at is `ISSUED`. The latest-action / stranded-
QUOTE rule disabled New Transaction on that issued renewal, so the UW could
not raise an endorsement.

Example: policy `COMG2026213639` — selected action `RENEW — ISSUED`
(02/11/2025–01/12/2025) with a newer action in `QUOTE` → button was greyed out.

## How it works now

The gate is based on the **selected** action's status, not the latest action:

```
blocked = !['ISSUED', 'LAPSED'].includes(selected.status)
```

Selecting any issued action in the dropdown enables the button. A selection
still sitting in the approval pipeline (`QUOTE` / `IN_APPROVAL` / `APPROVED` /
`REJECTED`) keeps it disabled.

### Concurrency is still safe

Enabling the button does not allow conflicting in-flight transactions. The
backend remains the gatekeeper:

- `LookupController::transactionTypes` filters out endorse-class options when an
  in-flight `QUOTE` of the same class already exists.
- `PolicyCreateController::store` returns **409** for a disallowed transition.

So the modal self-protects — it only offers types the API will accept.

## Files

- [PolicyDetailPage.tsx](../frontend/src/pages/Policies/PolicyDetailPage.tsx) —
  React gate (`+ New Transaction` button), now keyed off `selected?.status`.
- [EditWizard.php](../backend/app/Http/Livewire/Policy/EditWizard.php) —
  Livewire `getCanAddTransactionProperty()`, gated on the selected action
  (`actionId`, falling back to the latest non-deleted action) and filtered by
  `whereNull('deleted_at')`.
