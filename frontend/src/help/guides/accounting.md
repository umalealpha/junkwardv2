---
lastReviewed: 2026-06-03
---
## Overview

The Accounting module is a core back-office system that manages the general ledger, account definitions, and transaction reconciliation. Finance and accounting teams use this module to configure which accounts receive debits and credits for each policy action, view the detailed transaction journal, and reconcile daily payment settlement files from DPO and RealPay payment processors against internal payment records.

## Who uses this

- **Finance/Accounting team**: Manage the chart of accounts, accounting rules, and sub-ledger transactions
- **Finance ops / Settlement teams**: Upload and reconcile daily settlement files, review unmatched transactions, and resolve findings
- **Finance leadership**: Review reconciliation status and sign off on closed runs before cash posting

## Step-by-step

### Chart of Accounts Setup

1. Navigate to **Accounting → Chart of Accounts**.
2. Click **+ Add Account** or click **Edit** on an existing account row.
3. Fill in the modal:
   - **Account Name**: Human-readable name (required)
   - **Account Number**: GL account code (required)
   - **Branch Name**: Optional; branch or cost centre identifier
   - **Branch Code**: Optional; code for the branch
4. Click **Save**. The account is now available for use in accounting rules and settlement records.
5. Use the **Search** field to filter accounts by name or number.

### Accounting Rules Configuration

1. Navigate to **Accounting → Accounting Rules**.
2. Click **+ Add Rule** or click **Edit** on an existing rule row.
3. Fill in the modal:
   - **Product ID**: Numeric ID of the insurance product (required)
   - **Action**: Name of the trigger action (e.g. "premium_payment", "claim_settlement", "refund_initiated") (required)
   - **Action Type**: Numeric code for the action (required)
   - **Account Name**: GL account to post to (required)
   - **Entry Type**: Select **Debit** or **Credit** (required)
4. Click **Save**. On the next occurrence of this action for this product, the system will automatically post the configured amount to the selected account.

### Sub-Ledger Inquiry

1. Navigate to **Accounting → Sub-Ledger**.
2. Use the optional filters at the top:
   - **Policy ID**: Enter a policy number to filter by that policy only
   - **Date from / Date to**: Enter date ranges (both optional)
3. The table displays all general ledger entries:
   - **Account**: Account name from the chart
   - **Policy**: Policy ID associated with the entry
   - **Debit**: Amount posted to the debit side (red)
   - **Credit**: Amount posted to the credit side (green)
   - **Date**: Transaction date
4. Click **Previous** / **Next** to page through results (50 per page).

### Settlement Reconciliation Upload

1. Navigate to **Finance → Settlement Reconciliation**.
2. Click **+ Upload Settlement File**.
3. In the modal:
   - **Provider**: Select **DPO Pay** (RealPay is not yet enabled)
   - **CSV file**: Choose a settlement CSV file from your payment processor
4. Click **Upload & match**. The system will:
   - Hash the file to detect duplicates (safe to re-upload the same file)
   - Parse the CSV in the background (typically ~75 seconds for an 11k-row file)
   - Run the matching engine against local payment records
   - Redirect to the run detail page once matching completes
5. Monitor the status in the **Settlement Reconciliation** list. Status moves from "uploading" → "parsing" → "matched" → "reviewed" → "closed".

### Settlement Reconciliation Review & Findings Management

1. Navigate to **Finance → Settlement Reconciliation** and click **View** on a run.
2. Review the stat tiles at the top:
   - **Status**: Current state (matched, reviewed, closed, etc.)
   - **Transactions**: Total rows from the settlement file
   - **Matched**: Successful automatic matches against local records
   - **Findings**: Number of unmatched transactions (flagged for review)
   - **Drift**: Total amount discrepancy between DPO and local records
3. Use the **Findings** tab (default) to see only transactions with open findings:
   - **Status** pills filter by: open, accepted, disputed, all
   - Use the filter bar to narrow results by: search terms, type (Transaction/Refund/Manual), batch, date range, or amount
4. For each open finding:
   - Click **Accept** to acknowledge the finding and optionally add a note (e.g. "Timing difference—expected to clear next settlement")
   - Click **Dispute** to record a formal disagreement and explain why (required note)
5. Once all findings are accepted or disputed, click **Close run**. A closed run moves to audit trail.
6. If the matcher logic has been improved or local data corrected, click **Rematch** to re-evaluate all transactions (preserved accepted findings remain unchanged).

### Settlement Reconciliation Tabs & Filters

- **Findings tab**: Shows only transactions with unmatched statuses (amount/date/status mismatch, orphans, duplicates, manual review).
- **All transactions tab**: Shows every settlement row, filterable by match status.
- **Batches tab**: Groups transactions by DPO batch ID, showing batch-level totals and drift.

Run filters (on the main list page):
- **Provider**: DPO, RealPay, or all
- **Status**: Open (uploading, parsing, parsed, matched, reviewed) or Closed

### Detecting Stale Data Issues

If the **Findings** tile shows >100 orphan_dpo findings and many cluster on the last 1–3 days of the settlement period, this likely means the local payment_transactions table is older than the settlement file (e.g. test-RDS clone cutoff). Use the **Date from** filter to focus on dates the local database covers, then hit **Rematch** after the local data is refreshed.

## Field reference

| Field | Location | Meaning | Required |
|-------|----------|---------|----------|
| Account Name | Chart of Accounts | Human-readable GL account label | Yes |
| Account Number | Chart of Accounts | GL account code or ledger number | Yes |
| Branch Name | Chart of Accounts | Branch or cost-centre name | No |
| Branch Code | Chart of Accounts | Short branch identifier | No |
| Product ID | Accounting Rules | Insurance product ID (numeric) | Yes |
| Action | Accounting Rules | Type of transaction trigger (e.g. "premium_payment") | Yes |
| Action Type | Accounting Rules | Numeric code for the action | Yes |
| Account Name | Accounting Rules | Target GL account for posting | Yes |
| Entry Type | Accounting Rules | Debit or Credit | Yes |
| Policy ID | Sub-Ledger filter | Policy number to narrow results | No |
| Date from / Date to | Sub-Ledger / Settlement filters | Date range (ISO format) | No |
| Provider | Settlement upload | DPO Pay or RealPay | Yes |
| CSV file | Settlement upload | Settlement export from payment processor | Yes |
| Match Status | Settlement filters | matched, amount_mismatch, date_mismatch, status_mismatch, orphan_dpo, duplicate, manual_review | No |
| Finding Status | Settlement filters | open, accepted, disputed, resolved | No |
| Provider Type | Settlement filters | Transaction, Refund, Manual, Other | No |
| Amount (min/max) | Settlement filters | Numeric boundary for filtering by transaction size | No |
| Sort | Settlement filters | id (newest first), date, amount, drift (in ascending or descending order) | No |

## Tips & gotchas

- **Accounting rules apply automatically**: Once a rule is saved, the next matching transaction for that product and action will auto-post to the configured account. Test in a non-production environment first.
- **Debit vs. Credit matters**: Ensure your action → account → entry type mapping matches your GL chart. A misconfigured rule will post to the wrong side of the ledger until corrected.
- **Settlement file duplicates are safe**: The system detects identical files by SHA-256 hash. Re-uploading the same CSV will return the existing run instead of creating a duplicate.
- **Matching runs take ~75 seconds**: For a typical 11k-row DPO settlement file, the upload dialog will poll for 5 minutes max. If processing is still pending after 5 minutes, check the runs list shortly—the backend continues processing in background.
- **Findings must be resolved before closing**: A run cannot be closed if any findings remain open. You must Accept or Dispute every finding, then Close.
- **Rematch preserves accepted findings**: If you click Rematch on an existing run, only findings that are still open get re-evaluated. Findings you've already accepted stay accepted.
- **Drift flags on amounts > 0.005**: On the detail page, the Drift column highlights in red only if the absolute discrepancy exceeds 0.005 Pula (rounding tolerance). Smaller drifts are shown as "—".
- **Orphan_dpo warnings indicate data freshness**: A high count of "orphan in DPO" findings (transactions in the settlement file with no match in local records) often means the local payment_transactions table is stale, especially if orphans cluster near the end of the file period. Verify the RDS snapshot date and refresh local data if needed.
- **Search works across references**: In the settlement Findings tab, the search field matches transaction reference ID, MIS codes, or policy number—useful for tracing a specific payment across the settlement.
- **Accounts must have a name**: In the Chart of Accounts, Account Name is required; Account Number is also required. Branch fields are optional.
- **Actions require notes**: When you Accept or Dispute a finding, the system prompts for an optional note on accept (for timing explanations) or a required note on dispute (to document your disagreement).

## Related modules

- [Payments](/help/payments)
- [Reconciliation](/help/reconciliation)
- [Bulk Refunds](/help/bulk-refunds)
- **Finance Dashboard** — financial KPI overview (open from the main menu)
- [Audit Trail](/help/audit-trail)
