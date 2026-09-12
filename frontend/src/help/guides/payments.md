---
lastReviewed: 2026-06-03
---
## Overview

The Payments module manages all payment-related operations for policies, including recording manual offline payments, viewing third-party processor transactions (DPO, RealPay, Orange Money), managing payment schedules, and tracking discounts and surcharges. Use this module to record, monitor, and control policy payments across multiple channels.

## Who uses this

- **Customer Service Reps** — Record offline payments, look up payment status, manage payment schedules
- **Finance Teams** — Reconcile payments, track third-party payment processors (DPO, RealPay, Orange Money)
- **Operations** — Cancel recurring payment schedules when requested, view payment history
- **Underwriting** — View discount and surcharge history for policies

## Step-by-step

### Record an offline payment

1. Navigate to **Payments > Offline Payment**.
2. Enter the **Policy Number** and click **Lookup** (or press Enter).
3. The policy details appear (policy number, customer name, product, premium).
4. Fill in the payment form:
   - **Amount (BWP)** — Payment amount (required, decimal to 2 places)
   - **Payment Date** — Date the payment was made (required)
   - **Receipt Number** — Reference number from the payment receipt (required)
   - **Notes** — Optional details about the payment (e.g., cash, cheque, bank transfer method)
5. Click **Record Payment**.
6. A confirmation message appears. The payment is now logged in the system.

### View and search DPO payments

1. Navigate to **Payments > DPO Payments**.
2. Use the **Search** field to find payments by policy number or customer name (searches as you type, 400ms delay).
3. Filter by **Status**: All Statuses, Success, Failed, or Pending.
4. Results show in a table with policy #, customer, amount, status badge, payment method, reference, and date.
5. Use **Prev/Next** buttons or page numbers to navigate results (25 per page).

### View RealPay transactions

1. Navigate to **Payments > RealPay Transactions**.
2. Search by **Policy Number** (searches as you type, 400ms delay).
3. The table displays policy #, event type, status badge, response details, and transaction date.
4. Status values: Success (green), Failed (red), Pending (yellow), Processed (blue).
5. Use pagination to browse transactions (25 per page).

### View Orange Money transactions

1. Navigate to **Payments > Orange Money Transactions**.
2. Search by **Policy Number** (searches as you type, 400ms delay).
3. The table shows policy #, customer, amount, status badge, reference, and date.
4. Use pagination to navigate (25 per page).

### View discounts and surcharges for a policy

1. Navigate to **Payments > Discount / Surcharge**.
2. Enter the **Policy Number** and click **Search** (or press Enter).
3. Policy details appear: policy number, customer name, product.
4. A table below lists all discounts and surcharges:
   - **Type** — Discount (green) or Surcharge (orange) badge
   - **Description** — Reason for the discount/surcharge
   - **Amount / Percentage** — Either a fixed amount in BWP or a percentage
   - **Applied Date** — When it was applied
5. If no entries exist, a "No discounts or surcharges" message appears.

### View and cancel payment schedules

1. Navigate to **Payments > Cancel Schedule Transactions**.
2. Search by **Policy Number** to filter schedules (searches as you type, 400ms delay).
3. The table lists active/paused/cancelled payment schedules with policy #, amount, frequency, next payment date, status, and an Actions column.
4. Click **Cancel** to cancel a schedule:
   - A confirmation dialog appears showing policy #, amount, and frequency
   - Click **OK** to proceed or **Cancel** to abort
   - On success, status changes to "Cancelled" and a confirmation message appears
   - If the schedule is already Cancelled or Completed, the Cancel button is disabled

### View RealPay contracts

1. Navigate to **Payments > RealPay Contracts**.
2. Use the search box to find contracts by policy number or customer name.
3. The table displays contract ID, policy number (linked), customer name, status badge, and creation date.
4. Status: Active (green) or Inactive/other (gray).
5. Click a policy number to view the full policy details. Use **Previous/Next** to navigate pages (25 per page).

## Field reference

| Field | Purpose | Required | Notes |
|-------|---------|----------|-------|
| **Policy Number** | Unique identifier for the policy | Yes | Must exist in system; lookups fail if not found |
| **Amount (BWP)** | Payment amount in Botswana Pula | Yes (offline only) | Decimal to 0.01; minimum 0 |
| **Payment Date** | Date the payment was received or processed | Yes (offline only) | Verify carefully — no future-date block in the form |
| **Receipt Number** | External reference (cash receipt, cheque #, etc.) | Yes (offline only) | Free text, user-provided |
| **Notes** | Optional payment details (method, description, memo) | No | Free text; helps with reconciliation |
| **Status** | Payment or schedule state | Display only | Success, Failed, Pending, Processed, Active, Paused, Cancelled, Completed |
| **Frequency** | Recurrence interval for scheduled payments | Display only | E.g., monthly, weekly, annual |
| **Reference** | Transaction reference from the payment processor | Display only | From DPO/RealPay/Orange Money API |
| **Next Payment Date** | Upcoming payment date for active schedules | Display only | Read-only; updated by backend |

## Tips & gotchas

- **Offline payment requires a lookup first** — the "Record Payment" form only appears after a successful policy lookup. If the policy number isn't found, you can't proceed.
- **Payment date is editable with no future-date block** in the form — double-check the date before submitting.
- **Receipt number is required** for offline payments (audit purposes) and cannot be empty.
- **Amounts accept decimals** (step 0.01) — enter to two decimal places to avoid rounding errors.
- **Search is debounced 400ms** across DPO, RealPay, Orange Money, and Cancel Schedule pages — results update after a short pause, not on every keystroke.
- **Pagination resets to page 1** when you change a status filter or search term.
- **Cancelling a schedule shows a confirmation dialog** with amount and frequency so you can verify before confirming.
- **You cannot cancel a schedule that is already Cancelled or Completed** — the Cancel button is disabled and shows "—".
- **Discounts and surcharges are read-only here** — to create or edit them, contact Finance or Underwriting.
- **Status badges are color-coded**: green = Success/Active, red = Failed/Cancelled, yellow = Pending/Paused, blue = Processed.
- **Tables scroll horizontally on small screens**; policy numbers and references use a monospace font for easier scanning.

## Related modules

- [Policies](/help/policies) — Create and manage policies linked to these payments
- [Customers](/help/customers) — View customer information associated with payment records
- [Claims](/help/claims) — Claims may affect payment schedules
- [Bulk Refunds](/help/bulk-refunds) — Bulk refund processing for DPO customers
- [Excel Imports](/help/excel-imports) — DPO refund import for bulk payment updates
