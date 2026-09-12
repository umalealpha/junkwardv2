---
lastReviewed: 2026-06-03
---
## Overview

The Regions & Departments module lets you manage your company's organisational structure. You can create and edit both regions (geographic areas your business operates in) and departments (teams or business units). This is foundational data that other modules reference when assigning work, policies, or staff to operational units.

## Who uses this

- **System Administrators** — set up and maintain the organizational structure
- **Admin staff** — add new regions or departments as the business expands
- **Team leads** — typically view this data to understand how their teams are structured

## Step-by-step

### Adding a new Region

1. Navigate to **Regions & Departments** from the sidebar (under System > Admin)
2. Ensure the **Regions** tab is selected (top of the page; it is the default view)
3. Click **+ Add Region** in the top-right corner
4. A modal window opens with a **Name** field
5. Enter the region name (e.g. "Southern Region", "Gaborone Metro", "Central")
6. Click **Save**
   - If the name field is empty, the Save button is disabled
   - While saving, the button shows "Saving..." and is temporarily disabled
7. The new region appears in the table below; the modal closes automatically on success

### Adding a new Department

1. Navigate to **Regions & Departments**
2. Click the **Departments** tab at the top
3. Click **+ Add Department** in the top-right corner
4. Enter the department name (e.g. "Claims", "Finance", "Underwriting", "Customer Service")
5. Click **Save**
6. The new department appears in the table below

### Editing a Region or Department

1. Go to the Regions or Departments tab (whichever you want to edit)
2. Find the item you want to edit in the table
3. Click the **Edit** link in the Actions column (far right)
4. The modal opens with the current name pre-filled
5. Update the name as needed
6. Click **Save**
   - The Save button is disabled if the name field is empty
7. The table refreshes with the updated name

### Viewing all Regions or Departments

1. Use the **Regions** or **Departments** tab buttons to switch between the two lists
2. The table shows:
   - **ID** — the system identifier (auto-assigned)
   - **Name** — the region or department name you entered
   - **Actions** — currently just the Edit link
3. If the list is empty, you will see a "No regions." or "No departments." message
4. If data is loading, a "Loading..." message appears in the table

## Field reference

| Field | What it means | Required? |
|-------|---------------|-----------|
| **Name** | The display name of the region or department (e.g. "North", "Finance", "Claims Ops") | Yes |

## Tips & gotchas

- **Name is required** — if you leave the Name field blank, the Save button becomes disabled. You must type something before saving.
- **IDs are auto-assigned** — you cannot set the ID manually; the system assigns a unique number when the region or department is created.
- **No delete option** — the UI only supports Add and Edit; there is no delete button. To remove a region or department, contact a system administrator or database team. This is intentional to prevent accidental deletion of operational data.
- **Name uniqueness** — the code does not enforce unique names, so you could theoretically create two regions with the same name. Best practice: use distinct names to avoid confusion.
- **Save clears the modal** — when save succeeds, the modal closes automatically and the table reloads. If save fails (e.g. network error), an alert popup shows the error message from the API.
- **Modal state** — clicking Cancel closes the modal without saving. If you edit one item, then open Edit for a different item, the modal switches to the new item automatically.
- **Tab switching reloads data** — switching between Regions and Departments tabs triggers a fresh API call to load the appropriate list. Any in-flight edits are lost if you switch tabs without saving first.

## Related modules

- [Access Control](/help/access-control) — user roles and permissions; often tied to regions or departments for delegation
- [Agents & Staff](/help/agents) — agent and agency management; may be organized by region or department
- [Products & Plans](/help/products) — product configuration; can be scoped to specific regions
