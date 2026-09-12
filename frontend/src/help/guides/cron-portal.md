---
lastReviewed: 2026-06-03
---
## Overview

The Cron & Automation module monitors and manages the Alpha Direct kernel scheduler — a system that runs background jobs on repeating schedules (hourly, daily, weekly, monthly). Use this module to enable or disable scheduled tasks, adjust their run times, configure email alerts, and diagnose job failures in real-time.

## Who uses this

- **System Administrators**: manage job schedules, enable/disable jobs, update email recipients.
- **DevOps / On-call engineers**: diagnose stuck or failed cron runs, monitor daily activity, inspect live logs.
- **Finance ops**: monitor end-of-month and daily batch processing jobs to ensure data flows correctly.

## Step-by-step

### Flow 1: View all jobs and current status

1. Open **Cron Portal** from the System menu.
2. The **Status Summary Bar** at the top right shows at a glance:
   - Total jobs in the system
   - How many are enabled / disabled
   - Which jobs are running right now (with names if 3 or fewer)
3. The main table below lists all jobs. Columns are: Name, Type, Time, Server, Status, Last Run, Emails, and a toggle switch.
4. Use the filters to narrow the view:
   - **Search**: type a job name (e.g., "daily", "invoice") to find a specific job.
   - **All Servers / [server name]**: show jobs running on all servers or a specific one.
   - **All / Enabled / Disabled**: filter by job state.

### Flow 2: Edit a job's schedule

1. In the **Cron Portal** table, click any job row to open the **Detail Panel** (a slide-over on the right).
2. Under the **Schedule** section, click **Edit**.
3. Choose the **Run Type**:
   - Hourly
   - Daily
   - Weekly (Sundays)
   - Last Day of Month
4. Enter the **Run Time** in `HH:MM` format (24-hour, e.g., `02:30` for 2:30 AM).
5. Click **Save** to apply the changes. The page reloads the job data automatically.

### Flow 3: Manage email recipients for job completion notifications

1. In the **Detail Panel** (opened by clicking a job), scroll to **Email Recipients**.
2. To add: type an email address in the input field and click **Add**, or press Enter.
3. To remove: click the X icon next to any email address.
4. Email alerts are sent to all configured recipients when the job completes (success or failure).

### Flow 4: Review run history and diagnose failures

1. In the **Detail Panel**, scroll to **Run History** (showing the last 20 runs).
2. Each row shows:
   - **Start**: when the job began.
   - **Duration**: how long it took.
   - **Processed**: count of records processed (if applicable).
   - **Last step**: the current or final step in the job (e.g., "completed", "failed", or a descriptive step name). Yellow = in progress, green = completed, red = failed.
   - **Last policy**: the ID of the last record being processed (helps identify where a stuck job paused).
3. If an **error_message** is present, it appears below the step badge. Hover or click to see the full message.

### Flow 5: Monitor daily activity

1. Open **Daily Cron Activity** from the System menu.
2. By default, today's activity is shown. Use the **date picker** to view any past day.
3. Summary cards show **Total Runs**, **Completed**, and **Running / Incomplete** for that day.
4. The table lists every cron run that day with columns: #, Cron Name, Started, Ended, Duration, Status.
5. Use the **search** field to filter by cron name (e.g., "invoice", "reconcile").
6. Click **Download CSV** to export the day's activity for reports or spreadsheet analysis. (Button is disabled if no runs exist for that date.)

### Flow 6: View live logs

1. Open **Cron Logs** from the System menu.
2. Logs are a live tail from a shared backend log file (EFS). The page header shows the file size and last-modified timestamp.
3. **Filters**:
   - **Cron**: select a specific job name, or "All crons" to see all scheduled-command logs.
   - **Level**: filter by Error, Warning, Info, or all.
   - **Search**: find a substring in the message (e.g., "SQLSTATE", "policy 153455").
   - **Lines**: load the last 100, 500, 1000, 2000, or 5000 log lines.
4. **Auto-refresh (30s)**: check this box to poll for new logs every 30 seconds.
5. Click **Refresh** to reload logs immediately.
6. Each log line shows: timestamp, level (color-coded: red = error, amber = warning, blue = info, gray = debug), channel, and message.
7. Long messages are collapsed; click a line to expand and see the full text.

## Field reference

| Field | Meaning | Required | Notes |
|-------|---------|----------|-------|
| **Cron Name** | Unique identifier for the job (e.g., "daily_invoice_batch") | Yes | Read-only; set in the backend. |
| **Run Type** | Schedule frequency | Yes | Hourly, Daily, Weekly (Sundays), or Last Day of Month. |
| **Run Time** | Time of day the job is triggered | No | Format: `HH:MM` in 24-hour time. Applies to Daily, Weekly, and Month End jobs; Hourly jobs ignore this. |
| **Status** | Whether the job is active | Yes | Toggle on/off in the table. Disabled jobs do not run. |
| **Email** | Recipient for completion alerts | No | Multiple emails allowed. Truncated in table view; see Detail Panel for full list. |
| **Server** | Which machine executes the job | Yes (set by admin) | Read-only; visible in both table and Detail Panel. |
| **Enabled** | Binary flag (1 = on, 0 = off) | Yes | Controlled by the toggle switch in the table. |
| **Last Run Start / End / OK** | Timestamps and outcome of the most recent execution | No | Displayed as a green/red indicator with time. "Never" if job has never run. |
| **Current Step** | Progress marker written by the job as it executes | No | Useful for debugging stuck jobs. Shown in Run History. |
| **Last Policy ID** | The ID of the last record processed by the job | No | Shown in Run History; helps identify where a run paused. |

## Tips & gotchas

- **Email format**: the system does not validate email syntax client-side, but the backend will reject invalid addresses on submit. If adding an email fails silently, check the browser console.

- **Run time validation**: the `HH:MM` field is freeform text. Invalid times (e.g., "25:00", "12:60") are accepted but will cause undefined behavior at runtime. Stick to valid 24-hour format.

- **Hourly jobs**: if Run Type is "Hourly", the Run Time field is ignored. Hourly jobs run at the top of every hour regardless of the time value.

- **Last Day of Month**: if the current month has fewer than 31 days, the job runs on the last day present (e.g., Feb 28). This is a backend behavior; the UI does not warn.

- **Status bar auto-updates every 60 seconds**: if you enable a job, the "Enabled" count may not update immediately. Refresh the page or wait for the next auto-sync.

- **Run History in Detail Panel shows only the last 20 runs**: older runs are not visible in the UI but are logged in the backend database.

- **Heartbeat columns** (Current Step, Last Policy ID, Error Message): the running cron writes these periodically so you can detect a stuck job. A stuck job will show an old timestamp in "Last step" even if "Duration" shows a pulsing "Running..." indicator. Check the job logs if a run appears frozen.

- **Cron Logs are truncated at 4 MB**: if you load many lines from a large log file, only the latest 4 MB is scanned. The UI will show "scan truncated" in gray text if this occurs. Use the **Search** and **Level** filters to reduce noise.

- **Daily Activity summary emails**: a summary email is sent every night at 23:55 to `kkatolkar@alphadirect.co.bw`. The date shown is always selectable back to any past day.

- **Download CSV button is context-sensitive**: it is disabled if no runs exist for the selected date. If you see "No cron runs recorded for [date]", the CSV will be empty.

- **Log line expansion**: messages longer than 220 characters or containing newlines are truncated. Click the row to expand and see the full message (the collapse arrow shows a ▼ or ▲).

- **Disabling a running job**: if you toggle a job off while it is running, the current execution continues to completion. The job will not start again after it finishes.

- **Pagination in Cron Portal**: the default page size is 10 jobs. Change it with the "Rows: [10/25/50/100]" buttons at the bottom. The page automatically resets to page 1 when you change a filter.

## Related modules

- [Batch Processing](/help/batch-processing)
- [Accounting](/help/accounting)
- [Reconciliation](/help/reconciliation)
- [Audit Trail](/help/audit-trail)
