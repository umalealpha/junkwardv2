---
lastReviewed: 2026-06-03
---
## Overview

The Reports module lets Finance operators manage automated financial reports that run on a schedule and email to stakeholders. You select a report job, view its last run status, adjust when it runs, control who receives it, and manually trigger it if needed.

## Who uses this

- **Finance team** — configure and monitor scheduled reports
- **Accounting** — recipients of automated report emails
- **System administrators** — oversee report job health and delivery

## Step-by-step

### View Report Jobs

1. Navigate to **System > Finance Report Configuration**.
2. On the left panel, you see a list of report jobs grouped by status (enabled, disabled).
3. Each job card shows:
   - Report label and description
   - Last run status (Never run, ok, error, or running)
   - Schedule in human-readable form (e.g. "Daily at 05:00 UTC")
   - Email status badge if notifications are active
   - Number of recipients
   - Timestamp of most recent run

### Select and View a Job's Details

1. Click any job card on the left to open its **detail panel on the right**.
2. The panel displays:
   - **Quick actions** — Run Now, Enable/Disable, Email On/Off, Download Report (if available)
   - **Schedule Configuration** — Current cron expression, human-readable schedule, enabled status, email status, and notes
   - **Latest Run** — Status, timestamp, duration, and job-specific summary metrics
   - **Email Recipients** — List of all stakeholders receiving this report (or fallback message if none set)
   - **Run History** — Last 20 run attempts with status and elapsed time

### Edit Schedule and Notes

1. In the detail panel, click **Edit** under "Schedule Configuration".
2. Update:
   - **Label** — Report name (shown on job card)
   - **Cron Schedule (UTC)** — Standard 5-field cron expression (e.g. `0 5 * * *` for daily at 05:00)
   - **Notes** — Internal notes visible to admins
3. Click **Save**. The panel closes and shows the updated schedule immediately.

### Enable or Disable a Report

1. In the detail panel, click **Disable** (if enabled) or **Enable** (if disabled).
2. The job card reflects the change instantly. Disabled jobs do not run on schedule.

### Control Email Distribution

1. In the detail panel, click **Email Off** (if active) or **Email On** (if inactive).
2. When email is ON, the report is sent to all listed recipients after each run.
3. When email is OFF, the report runs but is not emailed.

### Add an Email Recipient

1. In the detail panel, scroll to **Email Recipients**.
2. Enter the recipient's **Name** (optional) and **Email address**.
3. Press Enter or click **Add**.
4. The recipient appears in the list immediately and receives all future reports (if Email is On).

### Remove an Email Recipient

1. In the **Email Recipients** list, click **Remove** next to the recipient's email.
2. They are deleted instantly and no longer receive reports.

### Manually Run a Report

1. In the detail panel, click **Run Now**.
2. A message appears showing the job was queued.
3. The latest run summary and history update after the job completes (usually within seconds to minutes).
4. If the run generates a file, a **Download Report** button appears.

### Download a Report

1. After a successful run, in the detail panel, click **Download Report**.
2. The Excel file (.xlsx) is downloaded to your default folder.

## Field reference

| Field | Meaning | Required |
|-------|---------|----------|
| Label | Name of the report (appears on job card and detail header) | Yes |
| Description | One-line purpose shown on the job card | Inherited from config |
| Cron Schedule (UTC) | 5-field cron expression defining run frequency; if invalid, last valid schedule persists | Yes |
| Default Schedule | The original/fallback cron expression (read-only reference) | N/A |
| Notes | Internal notes for admins (not sent to recipients) | No |
| Email Recipients | List of email addresses and optional names to receive report files | No (fallback to env var) |
| Recipient Name | Optional display name for clarity | No |
| Recipient Email | Valid email address for delivery | Yes (if adding) |

## Tips & gotchas

- **Cron format is strict.** Use valid 5-field cron syntax (minute, hour, day-of-month, month, day-of-week). Typos are accepted but the job won't run correctly. Refer to the Default Schedule field to revert if unsure.
- **Recipients list is optional.** If no stakeholders are added, the system falls back to the `REPORT_RECIPIENTS` environment variable on the backend.
- **Disabled jobs still appear.** A disabled job is listed and editable but will not run on schedule. Manual "Run Now" still works.
- **Email toggle is independent of schedule.** You can have a report run daily but email disabled, so it only generates the file without sending.
- **Download button only appears after successful runs.** If the latest run status is "error" or "never run", no download link shows.
- **Download uses filename only.** The backend validates filenames matching the pattern `<name>_YYYYMMDD_HHMMSS.xlsx`. If the filename is malformed, download fails (404).
- **Run history shows last 20 runs only.** Older runs are not displayed in the detail panel but are logged on the backend.
- **Status changes are live.** Enable, disable, and email toggle changes take effect immediately without page refresh.
- **Active/Inactive recipients.** Inactive stakeholders (e.g. recently disabled accounts) appear crossed-out in the list but can still be removed.

## Related modules

- [Cron Portal](/help/cron-portal) — Backend job scheduling and logging interface
- [Accounting](/help/accounting) — Finance reconciliation and reporting
- [Finance](/help/compliance) — Broader compliance and audit trails
