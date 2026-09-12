---
lastReviewed: 2026-06-03
---
## Overview

The Batch Processing module lets you upload Excel or CSV files to create or cancel multiple policies at once, instead of handling them one by one. After you upload a file, the system validates and processes it in the background, and you can track progress and results on the Batch Report page.

## Who uses this

- **CSRs / Operations teams** — process large volumes of policy creates or cancellations from employer groups or bulk underwriting.
- **Underwriting staff** — batch-create policies after approval.
- **Back-office administrators** — handle policy lifecycle operations at scale.

## Step-by-step

### Create Policies in Batch

1. Navigate to **Batch Processing** > **Batch Policy Create** (route: `/batch/create`).
2. Click **Download Template** to get the Excel template (feature pending API configuration; you can also prepare a .xlsx, .xls, or .csv file with the required columns).
3. Open the template and fill in your policy data rows:
   - One policy per row.
   - Include all required columns as specified in the template.
4. Save the file.
5. Return to the **Batch Policy Create** page.
6. **Drag and drop** your completed file onto the dashed box, or **click to browse** and select it from your computer.
7. Confirm the file appears in the upload area (filename and size shown in green).
8. Click **Upload & Process** (button text changes to "Uploading..." while in flight).
9. Once successful, a green message confirms: "File uploaded successfully. Processing will begin shortly."
10. Check **Batch Processing** > **Batch Report** to monitor processing status and download results when complete.

### Cancel Policies in Batch

1. Navigate to **Batch Processing** > **Batch Policy Cancel** (route: `/batch/cancel`).
2. Click **Download Template** to get the cancellation template.
3. Open the template and fill in:
   - Policy numbers to cancel.
   - Cancellation reasons.
4. Save the file.
5. Return to the **Batch Policy Cancel** page.
6. **Drag and drop** your file, or **click to browse**.
7. Confirm the file appears (green checkmark, filename, and size displayed).
8. Click **Upload & Process Cancellations** (red button; text changes to "Uploading..." while in flight).
9. A green message confirms: "Cancellation file uploaded successfully. Processing will begin shortly."
10. Check **Batch Report** to track progress and results.

### Check Batch Processing Status and Results

1. Navigate to **Batch Processing** > **Batch Report** (route: `/batch/report`).
2. **Filter by Type** using the "Type (Remarks)" dropdown to show:
   - **All Types** — shows all batch jobs.
   - **Batch Create** — only policy creation jobs.
   - **Batch Cancel** — only policy cancellation jobs.
   - **Batch Update** — batch update jobs (if supported).
3. **Search** by file name or the username of who uploaded it; press **Enter** or click away to apply.
4. Review the table:
   - **ID** — unique batch job identifier.
   - **File Name** — your uploaded filename.
   - **Uploaded By** — username of the person who submitted it.
   - **Status** — **pending**, **processing**, **completed**, or **failed** (color-coded badge).
   - **Type** — batch operation type (shown as readable text, e.g., "batch create").
   - **Report File** — blue "Download" link (if available after completion) to download detailed results or error log.
   - **Date** — when the batch was submitted.
5. **Pagination**: If results span multiple pages, use **Prev/Next** buttons to navigate, or jump to a specific page using the "Go to" input field.

## Field reference

| Field | Purpose | Required | Notes |
|-------|---------|----------|-------|
| **File (Upload)** | The Excel or CSV file containing policy data | Yes | Accepts .xlsx, .xls, .csv only. Can drag-and-drop or click to browse. File size shown in KB once selected. |
| **Search** | Filter by file name or uploader username | No | Applies on Enter key or blur. Resets pagination. |
| **Type (Remarks)** | Filter reports by batch job type | No | Dropdown: "All Types", "Batch Create", "Batch Cancel", "Batch Update". Default is "All Types". |
| **Status** | Processing state of a batch job | N/A (read-only) | One of: pending (yellow), processing (blue), completed (green), failed (red). |
| **Report File** | Link to download batch results or error details | No | Only appears after processing completes. Click "Download" to retrieve the result file. |

## Tips & gotchas

- **File format**: Only .xlsx, .xls, and .csv files are accepted. If you upload another format, the browser will silently reject it (drag-drop) or the form will not select it (browse).

- **Download template first**: The template download button is currently non-functional pending API endpoint configuration. Reach out to your system administrator or check with operations for a sample template with the correct column names and structure. You can also use a previous successful batch file as a reference.

- **Background processing**: After you upload, the system processes the file asynchronously. Do not expect immediate results. Status stays "pending" briefly, then moves to "processing". Check back after a few minutes to see "completed" or "failed".

- **Error handling**: If validation fails (e.g., missing required fields, invalid policy numbers, or data type mismatches), the status becomes "failed". Download the report file to see which rows had errors and why. Correct the data and re-upload.

- **Uploading the same file twice**: Clearing the file input after a successful upload does not prevent you from re-uploading the same file. Each upload is treated as a separate batch job with a new ID.

- **Status badges**: Completed jobs show a green badge; failed jobs show red. Use the report file download link to inspect detailed results or error logs.

- **Pagination**: The report table paginates at 25 items per page. The "Go to page" field only accepts numeric values between 1 and the last page number.

- **Search & filter behavior**: Changing the search or remarks filter resets you to page 1 of results.

- **Cancellation workflow**: The batch cancel process requires policy numbers and cancellation reasons in the template. Policies not found in the system or already cancelled will be flagged in the report file.

## Related modules

- [Policies](/help/policies) — individual policy creation and management; batch operations complement this for large-scale volume.
- [Excel Imports](/help/excel-imports) — related bulk-upload flows for activation and other imports.
- [Reports](/help/reports) — access to system-wide reporting and audit trails for batch operations.
- [Audit Trail](/help/audit-trail) — detailed record of who submitted each batch and when.
