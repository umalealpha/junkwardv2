---
lastReviewed: 2026-06-03
---
## Overview

The Document Jobs module tracks policy document, certificate and correspondence generation requests in real time. It displays the current queue status, individual job details, and lets admins retry failed jobs or cancel stuck ones. The page auto-refreshes every 30 seconds so you can monitor bulk document generation without manual polling.

## Who uses this

- **System administrators** managing document generation workflows
- **Operations teams** responding to stuck or failed PDF generation requests
- **Underwriting and compliance staff** verifying that required documents are being generated

The Document Jobs module itself has no explicit permission gates in the UI (it lives in the System section), but the backend API enforces access control at the endpoint level.

## Step-by-step

### View job queue and statistics
1. Open **Document Jobs** from the sidebar (System section, 📄 icon).
2. The **Statistics cards** appear at the top showing:
   - **Total**: all jobs ever queued
   - **Queued**: jobs waiting to process (includes both regular and large-policy queues)
   - **Processing**: jobs currently generating PDFs
   - **Completed**: successfully generated documents
   - **Failed**: jobs that hit an error
   - **Cancelled**: jobs manually cancelled by a user
   - **⚠ Stuck >30min** (only appears if any exist): jobs in non-terminal status for over 30 minutes
3. The page automatically refreshes the list and stats every 30 seconds.

### Filter by status
1. Use the **Status** dropdown in the filter bar to narrow the table to one or more statuses:
   - All statuses
   - Queued (includes both `queued` and `queued_long`)
   - Processing
   - Completed
   - Failed
   - Cancelled
2. Alternatively, click any of the statistic cards to instantly filter the table to that status.

### Search for a specific policy
1. In the **Policy #** field, type or paste a policy number (e.g., `COMG2024130199`).
2. Click **Search** or press Enter.
3. The table updates to show only jobs for that policy.
4. Click **Clear** to remove the search and show all jobs matching the current status filter.

### Adjust table row limit
1. Use the **Show** dropdown to display 50, 100, 250, or 500 rows.
2. The list reloads with the new limit.

### Download a completed document
1. Find a job with status **Completed**.
2. Click the **download icon** (↓) to download the PDF to your computer.
3. The filename uses the original `file_name` from the job record, or defaults to `policy_<id>_quote_sheet.pdf`.

### View a completed document in browser
1. Find a job with status **Completed**.
2. Click the **eye icon** to open the PDF in a new browser tab.

### Investigate a failed job
1. Find a job with status **Failed**.
2. If an error log exists, click **View error log** to open an inline modal showing:
   - API method and endpoint (e.g., `POST /policies/...`)
   - HTTP status code (if available)
   - Error message (the high-level failure reason)
   - Stack trace or detailed error data (if logged)
   - Request payload (if available)
3. Click the **×** to close the modal, or click outside it.
4. If no error log exists, the job will show **Failed** without a clickable link.

### Retry a failed or cancelled job
1. Find a job with status **Failed** or **Cancelled**.
2. Click **Retry** to re-queue the same document generation with the same policy and action ID.
3. Confirm the prompt.
4. A success message appears, and the new job is added to the queue.

### Cancel an in-progress job
1. Find a job with status **Queued**, **Queued (large)**, or **Processing**.
2. Click **Cancel** to stop the generation.
3. Confirm the prompt (e.g., "Cancel job #123?").
4. The job status changes to **Cancelled**.

### Manually refresh the list
1. Click the **Refresh** button in the header to immediately reload jobs and stats (instead of waiting for the 30-second auto-refresh).
2. The button shows "Loading..." while fetching.

## Field reference

| Field | Meaning | Required |
|-------|---------|----------|
| Job ID | Unique job number (e.g., #42) | N/A |
| Policy | Policy number linked to this document (clickable to open the policy detail page) | N/A |
| Status | One of: Completed, Processing, Queued, Queued (large), Failed, Cancelled | N/A |
| File | Original filename of the generated PDF (e.g., `quote_2024_001.pdf`) | No |
| Message | User-facing status message or error summary (truncated in table) | No |
| Requested | Human-readable "time ago" (e.g., "5 minutes ago") | N/A |
| Actions | Context-sensitive buttons (Download, View, Retry, Cancel, View error log) | N/A |

## Tips & gotchas

- **Stuck jobs are flagged automatically**: If a job stays in Queued, Queued (large), or Processing status for more than 30 minutes, it gets the ⚠ badge. The sixth stats card changes from "Cancelled" to "Stuck" when any exist. Click the **⚠ Stuck >30min** card to filter and investigate.

- **Large-policy queue is slower**: Jobs marked **Queued (large)** are for policies with many sub-policies or complex structures. These are processed by a minutely cron job every 1–5 minutes, not by the main queue, so expect **5–15 minutes** of wait time. They are not stuck; this is normal.

- **PDF download works only for Completed jobs**: Download and View buttons appear only when status is **Completed** and a `file_name` exists. Other statuses show dynamic status text (e.g., spinning icon + "In progress").

- **Error logs appear inline, not in a new tab**: Clicking "View error log" opens a modal within the current page, not a separate admin panel. This avoids session and authentication issues between origins. The modal shows the full error record from `api_error_log` table, including stack traces and request payloads.

- **Retry is safe to use multiple times**: Retrying a failed or cancelled job re-queues the PDF generation with the same inputs. It does not duplicate the request or cause side effects; the backend treats each job ID as unique.

- **Policy link is live**: Clicking the policy number in the Job table navigates to the full policy detail page, letting you check policy state or other linked actions without leaving the admin panel.

- **Stats are fetched with the same API call**: The stats cards (Total, Queued, Processing, etc.) come from the same `/document-jobs` endpoint as the table rows, so they stay in sync.

- **Clear button only appears if you have an active search**: Once you search by policy number, a **Clear** button appears next to the Search button. Click it to reset the search and see all jobs matching the current status filter.

## Related modules

- [Cron & Automation](/help/cron-portal) — configure and monitor scheduled jobs that trigger document generation
- [Policies](/help/policies) — the policy detail page linked from each job's policy number
- [Reports](/help/reports) — generate ad-hoc reports; document jobs statistics could feed into SLA dashboards
