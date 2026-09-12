---
lastReviewed: 2026-06-03
---
## Overview

Store and manage the standard legal PDFs that go into policy documents and quotes across all insurance products. This is a centralized repository where admin users upload, activate, deactivate and download policy wording templates by category—everything from General Liability to Motor, Travel, and Specialty coverages. The system automatically serves these to quote generators and policy document builders.

## Who uses this

- **System administrators** and **compliance teams** — upload and manage the master set of wordings.
- **Quote and policy generators** (other systems like the quote sheet builder and policy doc engine) — consume active wordings from here.
- **CSR/operators** (with read-only access) — can download wordings to answer customer questions or for audit trails.

Permissions are enforced by role: only users with `can_manage` are allowed to upload, deactivate/reactivate, and hard-delete. Only `can_hard_delete` permission holders can permanently remove records.

## Step-by-step

### View wordings by category

1. Navigate to **System admin > Policy Wordings** (route: `/system/wordings`).
2. The page loads with a **Categories sidebar** on the left, showing all defined wording types (e.g. General Liability, Motor, Travel).
3. Each category shows two counts:
   - Green badge: number of **active** wordings.
   - Gray badge (if present): total count including inactive ones.
4. Click any category name to view its files in the main table on the right.
5. The table automatically filters to show only files in that category.

### Upload a new wording PDF

1. Make sure you have `can_manage` permission.
2. Open the category where you want to upload (click its name in the sidebar).
3. Click the **+ Upload PDF** button (top-right of the wordings table).
4. An upload form appears with three optional fields:
   - **Product code** — e.g., `P49`, `P79`, `P99` (optional; useful for linking wordings to specific products).
   - **Notes** — e.g., `Revised v2 - 2026-05` (optional; helps document version or change date).
   - **Select PDF** — the file picker (required; accepts `.pdf` files, max 25 MB).
5. Choose a PDF file from your computer.
6. The file uploads automatically; you'll see **"Uploading…"** status.
7. On success, the form clears and the new row appears in the table as **Active**.
8. Click **Cancel** to close the upload form without uploading.

### Download a wording

1. Find the wording in the table.
2. Click the **Download** link (rightmost Actions column).
3. The PDF opens in a new browser tab.

### Deactivate a wording (mark inactive)

1. Locate the active wording in the table (status = **Active**).
2. Click **Deactivate** in the Actions column.
3. A confirmation dialog appears: _"Deactivate [filename]? Apps will stop using it for new PDFs."_
4. Click OK to confirm.
5. The wording status changes to **Inactive**; new quote/policy PDFs will not use this version.
6. The category count updates: the green (active) count drops, the gray (total) count may now appear if there are now inactive files.

### Reactivate a wording

1. Locate the inactive wording in the table (status = **Inactive**).
2. Click **Reactivate** in the Actions column.
3. The status changes back to **Active** immediately (no confirmation needed).
4. New PDFs will again use this wording.

### Hard-delete a wording (admin only)

1. Must have `can_hard_delete` permission.
2. Locate the wording in the table.
3. Click **Delete** in the Actions column.
4. A confirmation dialog appears: _"Hard-delete [filename]? File is preserved in S3 versioning but removed from this admin view."_
5. Click OK to confirm.
6. The row disappears from the table permanently.
7. S3 keeps a version history, but the record is removed from the admin UI.

## Field reference

| Field | What it does | Required? |
|-------|--------------|-----------|
| **Category** (sidebar selection) | Filters the table to show wordings for this product type (e.g., Motor, Travel). The system groups all uploads by category. | Yes, to upload |
| **Filename** | The display name of the PDF—usually auto-derived from the uploaded file name. Shows below-text notes if they exist. | (auto) |
| **Product code** (upload form) | Optional label, e.g., `P49`, linking the wording to a specific product plan. Appears in the table's "Product" column. | No |
| **Notes** (upload form) | Optional version or change summary, e.g., `Revised v2 - 2026-05`. Displayed as small gray text under the filename. | No |
| **PDF file** (upload form) | The `.pdf` file to upload. Max 25 MB per file. | Yes |
| **Size** (table column) | File size in B, KB, or MB; shown for reference. | (auto) |
| **Uploaded** (table column) | Date/time of upload and the email of the user who uploaded it. | (auto) |
| **Status** (table column) | **Active** or **Inactive**. Active wordings are used by quote/policy generators; inactive ones are archived but not deleted. | (auto) |

## Tips & gotchas

- **Deactivate, don't delete**: Always deactivate old versions instead of hard-deleting them. Deactivation hides them from future PDFs but keeps a record for audit. Only use hard-delete if absolutely necessary (e.g., uploaded by mistake).

- **25 MB file limit**: If your PDF is larger, compress it or split it before uploading. The system will reject anything over 25 MB with an alert.

- **Product code is optional but useful**: If you link wordings to specific products (e.g., `P79` for a Travel plan), that metadata helps trace which PDF was used in which quote/policy later.

- **Category determines where it lives**: You must select a category in the sidebar before uploading. The upload form reads the currently selected category automatically.

- **No pagination shown**: If a category has hundreds of files, the table loads them all; there's no visible "next page" control. Performance may degrade with very large lists.

- **Uploader email is logged**: When a wording is uploaded, the system records the email of the user who uploaded it (shown in the Uploaded column). This is useful for audit trails but is not anonymized in the UI.

- **Inactive counts**: If a category has both active and inactive wordings, you'll see two badges in the sidebar—green for active, gray for total. If all are active, only the green badge shows.

- **Confirmation dialogs**: Deactivate and hard-delete both require a confirmation dialog; if you cancel, the action is not performed. Reactivate is instant with no confirmation.

- **Download as admin reference**: Managers and CSRs can always download wordings to review or share with customers, regardless of `can_manage` permission.

- **S3 versioning**: Hard-deleted files are not truly lost; S3 versioning preserves them server-side. Only the admin UI entry is removed. If you hard-delete by accident, the backend team can recover from S3.

- **Auto-refresh**: After uploading, deactivating, or reactivating, the category counts and file list refresh automatically. No manual refresh button needed.

## Related modules

- [Products & Plans](/help/products) — Define which product codes (e.g., P49, P79) map to which wordings.
- [Quotes](/help/quotes) — Quote generator consumes active wordings to build PDF quote sheets.
- [Policies](/help/policies) — Policy document builder uses wordings to generate policy PDFs and certificates.
- [Document Jobs](/help/document-jobs) — Background jobs that generate policy documents (which pull wordings from here).
- [Cron & Automation](/help/cron-portal) — System-wide scheduled jobs and logs (may reference wording regeneration).
