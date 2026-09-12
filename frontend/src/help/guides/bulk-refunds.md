---
lastReviewed: 2026-06-03
---
## Overview

Issue DPO (debit order) refunds in bulk against already-settled payments. Upload or paste a list of payment transaction IDs with optional amounts, choose a reason, and either create the batch to review later or immediately queue it for processing.

## Who uses this

Finance teams and payment operations staff who handle refunds for wrong customer debits, duplicate charges, policy cancellations, goodwill complaints, or other reasons.

## Step-by-step

### Creating a bulk refund batch

1. Click the **New batch** tab.
2. Enter a **Batch name** (optional but recommended; example: "Feb wrong-customer sweep").
3. Choose your input method:
   - **Paste list**: Paste payment transaction IDs, one per line. Optionally add a comma and amount for partial refunds (example: `12345,250.00`). Lines starting with `#` are ignored as comments.
   - **Upload CSV**: Select a CSV file with header row `payment_transaction_id,amount`. The amount column is optional; omit it or leave cells blank for full refunds.
4. Select a **Reason code** (applies to all rows in the batch):
   - Wrong customer debited
   - Duplicate charge
   - Goodwill / complaint resolution
   - Policy cancelled, refund due
   - Other
5. Enter **Notes / ticket ref** (optional; max 500 chars). Example: "JIRA-123, call 2026-04-18".
6. Choose action:
   - Check **Run immediately after creating** to queue the batch and start processing now.
   - Leave unchecked to create the batch in draft state for review before running.
7. Click **Create & run** or **Create batch** (button label changes based on your choice).
8. The page redirects to the batch detail page.

### Running a batch (manual trigger)

If you created a batch without running it:

1. Navigate to the **Batches** tab to find your batch.
2. Click **Open →** to view the batch detail page.
3. If the batch status is draft, queued, or failed, a **Run batch now** button appears (or **Retry failed rows** if status is failed).
4. Click the button to queue the batch. The page polls every 3 seconds to show live progress.

### Viewing batch status and results

1. From the **Batches** tab, click **Open →** on any batch.
2. The detail page shows:
   - **Batch header**: ID, name, reason code, created timestamp, and action buttons.
   - **Summary cards**: Status, total rows, succeeded count, failed count, pending count.
   - **Amount progress**: Total refunded vs. total amount due, with a progress bar.
   - **Row table**: One row per payment, showing:
     - Row ID and policy number
     - Customer ID
     - Amount requested
     - Refund status (pending, submitted, succeeded, failed, cancelled)
     - DPO result code and explanation
     - DPO refund reference (confirmation number from payment processor)
     - Completed timestamp (when the refund finished processing)
3. Click **Download CSV** to export all rows as a spreadsheet for your records.

## Field reference

| Field | Meaning | Required |
|-------|---------|----------|
| Batch name | Human-readable label for tracking | No |
| Payment transaction ID | The ID of the payment to refund (one per row) | Yes |
| Amount | Partial refund in Pula. Leave blank or omit for full refund of the original payment. | No |
| Reason code | Why the refund is being issued; applies to all rows in the batch | Yes |
| Notes / ticket ref | Free-form text for audit trail (JIRA ticket, call date, etc.) | No |
| Run immediately | Checkbox: queue the batch now (checked) or create in draft for later review (unchecked) | No |

## Tips & gotchas

- **Paste format**: The parser accepts payment IDs separated by commas, tabs, or semicolons. For example, these all work:
  ```
  12345
  12346,250.00
  12347;100
  ```
  Invalid or blank lines are skipped silently (including comment lines starting with `#`).

- **CSV header**: Must be exactly `payment_transaction_id,amount` (case-sensitive). The amount column is optional, but the header must include both columns.

- **Partial vs. full refund**: If you specify an amount, only that amount is refunded. If you leave the amount blank or omit it, the system refunds the full payment transaction.

- **Batch status workflow**:
  - **draft**: Created but not queued. Use "Create batch" (without "Run immediately") to land here.
  - **queued**: Batch has been queued for processing but hasn't started yet.
  - **processing**: Refunds are being sent to the payment processor. The page animates with a pulse effect and polls every 3 seconds.
  - **completed**: All refunds have finished (may include successes, failures, and cancellations).
  - **failed**: At least one row failed, and processing stopped. Use "Retry failed rows" to resubmit pending and failed rows.
  - **cancelled**: User or system cancelled the batch.

- **Row-level statuses**: Each payment refund can be:
  - **pending**: Waiting to be submitted to DPO.
  - **submitted**: Sent to the payment processor, awaiting response (animates with pulse).
  - **succeeded**: Refund completed successfully; DPO assigned a refund reference.
  - **failed**: Payment processor rejected the refund (check DPO result explanation).
  - **cancelled**: User or system cancelled this row.

- **Retry behavior**: If a batch fails partway through, you can click "Retry failed rows" on the detail page. This requeues only rows with pending or failed status, not succeeded rows.

- **Live polling**: While a batch is in processing status, the detail page automatically refreshes every 3 seconds so you can watch counters update in real time without manual refresh.

- **Reason code applies to all rows**: All refunds in a batch share the same reason code. You cannot set different reasons per row in a single batch. Create separate batches if you need different codes.

- **DPO result codes**: After the payment processor responds, each row shows a result code (e.g., `SUCCESS`, `ACCOUNT_NOT_FOUND`) and a human-readable explanation. Check this if a refund fails.

- **CSV download**: The "Download CSV" button exports the full batch results, including DPO references and completion timestamps, for reconciliation and audit.

## Related modules

- [Payments](/help/payments)
- [Accounting](/help/accounting)
- [Reconciliation](/help/reconciliation)
- [Audit Trail](/help/audit-trail)
