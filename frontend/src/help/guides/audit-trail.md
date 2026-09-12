---
lastReviewed: 2026-06-03
---
## Overview

The Audit Trail shows a complete log of all changes made in the system—who did what, when, and where. Use this to track policy updates, claim modifications, user logins, exports, and deletions. Results display with timestamps, user names, action types (created, updated, deleted, login, export), affected record types, and IP addresses.

## Who uses this

- **Compliance & Audit teams** — to verify system usage and track regulatory requirements.
- **Operations & Support** — to investigate "who changed this and when?" questions.
- **System administrators** — to monitor user activity and identify suspicious patterns.

## Step-by-step

### View the audit trail

1. Navigate to **Audit Trail** from the main menu.
2. The page loads and displays up to 50 entries per page in a table.
3. Each row shows: **Date**, **User**, **Action** (color-coded badge), **Subject** (record type), **Record ID**, **Details**, and **IP Address**.

### Filter by search text

1. In the **Search** field, enter keywords (searches the details/description column).
2. Press **Enter** to apply the filter.
3. Results update automatically.
4. To clear, delete the text and press **Enter** again.

### Filter by date range

1. Click the **Date From** field and select a start date.
2. Click the **Date To** field and select an end date.
3. Results update instantly and show only entries within that range (inclusive).

### Filter by user

1. Click the **User** dropdown.
2. Select a user name from the list, or leave as **All Users** for no filter.
3. Results update instantly.

### Filter by action type

1. Click the **Action Type** dropdown.
2. Select an action (e.g., created, updated, deleted, login, export), or leave as **All Actions**.
3. Results update instantly.

### Navigate through pages

1. At the bottom of the table, use **Prev** and **Next** buttons to move between pages.
2. Click a specific page number to jump directly to it.
3. To jump to a specific page, type the page number in the **Go to** input box and press **Enter**.
   - The page number must be between 1 and the last page.
   - Valid entries go to the page; invalid ones are ignored.

## Field reference

| Field | Meaning | Required |
|-------|---------|----------|
| **Date** | Timestamp (date and time, in GB format) of when the action occurred. | Always shown |
| **User** | Name of the user who performed the action. Shown as "—" if no user is associated. | Always shown |
| **Action** | Type of change: created, updated, deleted, login, or export. Color-coded badge for quick scanning. | Always shown |
| **Subject** | Record type affected (e.g., Policy, Claim, Customer). Extracted from the system class name. | Always shown |
| **Record ID** | Numeric ID of the specific record that was changed. Shown as "—" if none. | Always shown |
| **Details** | Description of what changed (e.g., field names, values). Truncated on screen; hover to see full text. | May be empty (shown as "—") |
| **IP Address** | IP address of the user's machine/connection when the action was performed. Shown as "—" if not recorded. | May be empty |

### Filter fields

| Field | Meaning | Behavior |
|-------|---------|----------|
| **Search** | Search by keyword in the details/description | Press Enter to apply; empty clears the filter |
| **Date From** | Start date (inclusive) | Dropdown calendar; updates instantly |
| **Date To** | End date (inclusive) | Dropdown calendar; updates instantly |
| **User** | Filter by who performed the action | Dropdown list of active users; "All Users" shows no filter |
| **Action Type** | Filter by action type | Dropdown of available actions from the system; "All Actions" shows no filter |

## Tips & gotchas

- **Filters reset pagination**: When you change any filter (search, date, user, action), the page jumps back to page 1. This is intentional to prevent showing inconsistent results.
  
- **Combining filters uses AND logic**: If you select a user AND a date range AND an action type, results must match *all three* conditions. Leave a filter empty (or at "All") to exclude it.

- **Search is case-insensitive** and searches the details column only, not user names or action types. Use the dropdowns for those.

- **Action type badges are color-coded** for quick scanning:
  - Green = created
  - Blue = updated
  - Red = deleted
  - Purple = login
  - Yellow = export
  - Gray = unknown action type

- **Date format is DD MMM YYYY HH:MM:SS** (e.g., "03 Jun 2026 14:32:45") in GB locale.

- **"—" symbols appear** when a field has no value (e.g., no IP address recorded, no user name).

- **Details column is truncated** in the table. Hover over the cell to see the full text in a tooltip.

- **IP addresses are right-aligned monospace** and may be empty for backend-triggered actions or older entries.

- **Page jump validation**: The "Go to" page input only accepts numbers. If you type a page number outside the valid range (e.g., page 999 when only 5 pages exist), the input is ignored and you stay on the current page.

- **Loading and error states**:
  - Initial load shows a spinner with "Loading audit trail..."
  - If the API fails, a red error box appears with the error message and a **Try again** button. The system automatically reports the error to developers, so don't worry about manually escalating it.
  - While results are updating (e.g., after changing a filter), a blue "Updating results..." banner appears and the table becomes slightly dimmed.

- **Empty results**: If no entries match your filters, you'll see a message "No audit entries found" with a suggestion to adjust filters. This is normal—filters may be too restrictive.

- **Large result sets**: The audit trail can contain thousands of entries. Use filters to narrow results; the most recent entries will be on earlier pages if sorted by date.

## Related modules

- [Access Control](/help/access-control)
- [Compliance](/help/compliance)
- [Communications](/help/communications)
- [Policies](/help/policies)
- [Claims](/help/claims)
- [Payments](/help/payments)
