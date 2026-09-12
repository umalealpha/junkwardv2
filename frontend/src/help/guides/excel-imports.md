---
lastReviewed: 2026-06-03
---
## Overview

The Excel Imports module allows operators to batch upload policy changes and refunds via spreadsheet files. Each import type processes a different operation — activating policies, cancelling policies, or processing DPO refunds — and tracks the upload history with processing status.

## Who uses this

- **Policy Operations team** — upload activation and cancellation files weekly or as needed
- **Finance/Refunds team** — manage DPO (Data Protection Opt-out) refund uploads; access gated by DpoRefundByExcel permission
- **Administrators** — view unified reports across all import types via the Activate & Cancel Report


## Step-by-step

### Policy Activation Import

1. Navigate to **Imports > Policy Activation**.
2. In the upload zone, either drag a file directly or click **Browse Files**.
3. Select a .xlsx, .xls, or .csv file where:
   - **Column A** contains policy numbers (one per row).
   - File formatting is clean and matches expected structure.
4. The file uploads automatically. You'll see "Uploading and processing..." with a spinner.
5. On success, a green banner confirms the file name and queued processing ID. On error, a red banner shows the issue.
6. Check the **Perform File** column in the table below to monitor progress (shows "Processing..." in yellow while pending, then the output file name in green when done).
7. Use **Search** to filter by file name or uploader, and navigate pages as needed.

### Policy Cancellation Import

1. Navigate to **Imports > Policy Cancellation**.
2. Upload a .xlsx, .xls, or .csv file with policy numbers in **Column A** (one per row).
3. The upload zone has a red accent (vs. blue for activations) to signal this is a destructive operation.
4. After upload, monitor the **Perform File** column — it shows "Processing..." initially, then the output file name when complete.
5. Search and paginate as with activations.

### DPO Refund Import

1. Navigate to **Imports > DPO Refund** (requires DpoRefundByExcel permission).
2. Upload a .xlsx, .xls, or .csv file via drag-drop or **Browse Files**.
3. The table shows:
   - **Uploaded File** — the file you provided
   - **Perform File** — the generated refund report (may be blank if still processing or not yet generated)
4. Search by file name or uploader. No real-time upload feedback is shown yet (integration pending).

### Activate & Cancel Report

1. Navigate to **Imports > Activate & Cancel Report**.
2. This is a **read-only summary** of all activations, cancellations, and DPO refunds processed.
3. Use the **Search** field to filter by file name or uploader name.
4. The **Status** column shows a colored badge: green (Activation), red (Cancellation), blue (DPO Refund).
5. View creation date and the user who added each import.
6. No uploads are performed here — this is for auditing and tracking only.


## Field reference

| Field | Meaning | Required | Notes |
|-------|---------|----------|-------|
| Uploaded File | The Excel/CSV file you submitted | Yes | 25 MB limit typical; see upload error if rejected |
| Perform File | Output file generated after processing | No | Blank while Processing, populated when done |
| Search | Filter by file name or "added by" user name | No | Press Enter or blur to apply |
| Status (report only) | Type of import (Activation / Cancellation / DPO Refund) | N/A | Color-coded badge for quick scanning |
| Added By | User who initiated the upload | Auto-filled | Logged from your session |
| Date | Upload timestamp | Auto-filled | Displayed in local date format |
| Column A (in file) | Policy numbers | Yes | One policy number per row, no headers |


## Tips & gotchas

- **File format matters**: Accepts only .xlsx, .xls, or .csv. Other formats fail silently or are rejected.
- **One policy per row**: The system expects Column A to contain policy numbers with one per row. No headers, no extra columns required initially.
- **Upload is fire-and-forget**: After a successful upload confirmation, processing happens in the background. Check the **Perform File** column later (not real-time) to see when output is ready.
- **"Processing..." status is normal**: If **Perform File** shows "Processing..." in yellow text, the import job is queued or running. Refresh the page in 30 seconds if needed.
- **DPO Refund upload integration pending**: The DPO page accepts file selection, but the actual processing endpoint is not yet wired. Expect a placeholder alert if you attempt upload.
- **Permissions gate access**: Only users with DpoRefundByExcel permission can see the DPO Refund tab. Policy Activation and Cancellation are available to all ops users.
- **Search resets pagination**: When you enter a search term, pagination resets to page 1. Jump to a specific page number using the "Go to" input at the bottom right if needed.
- **Drag-and-drop only accepts one file**: If you drop multiple files, only the first is processed.
- **Activate & Cancel Report is read-only**: You cannot delete or re-upload from the report page; use the individual import pages to upload new files.


## Related modules

- [Policies](/help/policies)
- [Batch Processing](/help/batch-processing)
- [Compliance](/help/compliance)
- [Accounting](/help/accounting)
- [Audit Trail](/help/audit-trail)
