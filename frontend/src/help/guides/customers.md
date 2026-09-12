---
lastReviewed: 2026-06-03
---
## Overview

The Customers module is a comprehensive customer management hub within the Graphite V2 admin portal. From here, CSRs and operators can view and manage all customer records, search for specific customers, monitor compliance status (KYC/AML), block customers when necessary, and access 360-degree customer views with policy, payment, and claims history.

## Who uses this

- **Customer Service Representatives (CSRs)** — daily customer lookups, account inquiries, and support
- **Compliance Officers** — KYC and AML screening, customer blocking/unblocking, compliance status review
- **Collections Team** — identifying blocked customers, managing customer records
- **Admin/Operations** — bulk customer management, block-list maintenance

## Step-by-step

### 1. View Customer List

1. Navigate to **Customers** module (main navigation).
2. The **Customer List** page displays all customers in a paginated table (25 per page).
3. **Table columns** show: Name, ID Number, Phone, Email, KYC Status, AML Status, Active Policies, Premium, Blocked status, and Actions.
4. Use the **Search** input field to filter by name, email, phone, or ID number — press **Enter** to apply.
5. Use the **Status** dropdown to filter by: All Customers, KYC Compliant, KYC Pending, KYC Non-Compliant, Blocked, or AML Flagged.
6. Navigate pages using **Prev**/**Next** buttons, numbered page links, or the **Go to** jump box.

### 2. View Customer 360 (Detail View)

1. From the Customer List, click **View** next to any customer or click the customer's name to open the **Customer 360 Page**.
2. **Header section** displays: customer avatar, name, ID number, phone, email, with an **Edit** button to update contact details.
3. **Status badges** show KYC Status, AML Status, and Risk Score with color-coded indicators.
4. **Stat cards** summarize: Active Policies, Total Premium, Total Paid, and Open Claims.
5. Three **tabs** provide:
   - **Policies**: linked policy numbers, product names, statuses, premiums, and start dates.
   - **Payments**: recent payment records with amounts, status, dates, and methods.
   - **Claims**: claim numbers, types, statuses, and creation dates.
6. Click policy numbers to navigate to the Policies module for further action.

### 3. Edit Customer Information

1. From the **Customer 360 Page**, click **Edit** in the header.
2. Editable fields appear: **Full Name**, **ID Number**, **Phone**, **Email**.
3. Update any field as needed.
4. Click **Save** to persist changes (triggers an API call and refreshes the page).
5. Click **Cancel** to discard changes and exit edit mode.

### 4. Search for Customers (Dedicated Search)

1. Navigate to **Customer Search** page.
2. Enter search term in the large input: name, phone, email, OMANG (Botswana ID), or passport number.
3. Click **Search** or press **Enter**.
4. Results table displays: ID, Name, Phone, Email, Date (created).
5. Click a customer row to view more details (links to Customer 360 if integrated).
6. Paginate results using **Prev**/**Next** buttons, numbered pages, or **Go to** jump box.

### 5. Block a Customer

#### From the Customer List:
1. In the **Actions** column, click **Block** on any customer row.
2. A prompt appears asking for a **block reason** — enter text describing why.
3. Click **OK** to confirm; the button changes to **Unblock** immediately.
4. The customer's **Blocked** column shows a red lock icon.

#### From the Block List page:
1. Navigate to **Customer Block List**.
2. Click **Block Customer** button (top right).
3. A modal form appears with two required fields:
   - **Customer ID**: numeric customer ID
   - **Block Reason**: textarea for detailed reason
4. Click **Block Customer** to submit.
5. The customer is added to the block list and cannot transact.

### 6. Unblock a Customer

#### From the Customer List:
1. Locate the blocked customer row (indicated by a red lock icon).
2. Click **Unblock** in the **Actions** column.
3. The block status is immediately removed.

#### From the Block List:
1. Navigate to **Customer Block List**.
2. Find the blocked customer in the table.
3. Click **Unblock** in the **Actions** column (rightmost).
4. A **Confirm Unblock** modal appears.
5. Click **Confirm Unblock** to finalize; customer is removed from block list.

### 7. Manage Block Reasons

#### Inline edit (Block List):
1. On the **Customer Block List** page, locate the customer row.
2. Click **Edit** in the **Actions** column.
3. The **Block Reason** cell becomes an editable input.
4. Type the new reason and press **Enter** or click **Save**.
5. Press **Escape** or click **Cancel** to discard.

#### Detail modal edit (Block List):
1. On the **Customer Block List** page, click **View** in the **Actions** column.
2. A detail modal opens showing full customer information.
3. Under **Block Reason**, click **Edit** next to the current reason.
4. Editable input appears; update and click **Save**.
5. Click **Cancel** to discard changes.

### 8. Add and Remove Customer Aliases (Block List)

1. Navigate to **Customer Block List** and click **View** on a blocked customer.
2. In the detail modal, scroll to **Aliases** section.
3. **To add**: type an alias name in the input field and click **Add** or press **Enter**.
4. **To remove**: click **Remove** next to any alias.
5. New aliases help identify the customer under alternative names during AML screening.

### 9. Run AML Checks

#### Single customer (Block List):
1. On the **Customer Block List**, click **AML** in the **Actions** column for a row.
2. System runs an AML check and returns result: "AML CLEAR" or "AML FLAGGED (N matches)".
3. A toast notification displays the outcome.

#### From detail modal:
1. Click **View** on a blocked customer to open the detail modal.
2. Click **Run AML Check** button in the modal footer.
3. Result updates the **AML Status** badge and displays a toast.

#### Bulk AML Scan:
1. On the **Customer Block List** page, click **Bulk AML Scan** (top right).
2. System scans all customers currently on the page.
3. Returns summary: "X clear, Y flagged, Z failed."
4. Reflects current page only (25 customers max per scan).

### 10. Change Customer on a Policy (Advanced)

1. Navigate to **Change Customer in Policy** page.
2. Fill two required fields:
   - **Select Policy**: policy number or ID
   - **Select Customer**: new customer name, phone, or ID
3. Click **Change Customer** to submit.
4. ⚠️ **Note**: This feature is currently in placeholder mode; the API endpoint is not yet built. Success message indicates the transfer would happen once backend is ready.

## Field reference

| Field | Meaning | Required | Notes |
|-------|---------|----------|-------|
| **Name** (First + Last) | Customer's full name | Yes | Displayed in list and 360 view; editable in Customer 360 |
| **ID Number** (OMANG/Passport) | National/travel identification | No | Used for compliance screening and customer identification |
| **Phone** (Cellphone) | Contact phone number | No | For customer outreach; searchable in lists |
| **Email** | Customer email address | No | For communication; searchable in lists |
| **KYC Status** | Know-Your-Customer compliance | No | Values: Compliant, Pending, Non-Compliant, Not set |
| **AML Status** | Anti-Money Laundering status | No | Values: Cleared, Checked, Not Checked, Flagged |
| **Active Policies** | Count of active policies | Read-only | Displayed in list and stat cards |
| **Total Premium** | Sum of active policy premiums | Read-only | Formatted as P (Pula) in list and stat cards |
| **Block Reason** | Reason for blocking customer | Conditional | Required when blocking; editable in block list detail view |
| **Blocked** | Block status indicator | Read-only | Shows red lock icon if blocked; updated via block/unblock actions |
| **Risk Score** | Compliance risk level | Read-only | Values: Low, Medium, High; shown in Customer 360 header |

## Tips & gotchas

### Compliance & KYC/AML

- **KYC "Not set"** appears when a customer has never been screened. This is not the same as "Pending" — the system shows it in gray, italic text to distinguish.
- **AML "Not screened"** means the customer has no AML check history. First screening required before status updates.
- Blocking a customer via the Block List is a **hard block** (customer cannot transact); blocking from the Customer List requires an inline reason prompt.

### Block List Management

- The **Block List page** and **Customer List** are separate views:
  - **Customer List** shows all customers (including blocked status).
  - **Block List** shows *only* blocked customers with detailed management tools.
- **Aliases** are stored against the blocked customer record and used for AML matching. Useful when a customer appears under multiple names.
- When you **unblock** a customer, they are removed from the block list entirely but their customer record remains in the main list.

### AML Checks

- **Individual AML check** (row action on Block List) returns immediate result: match count if flagged, or "clear."
- **Bulk AML Scan** runs checks on all 25 customers on the current page only. Pagination is not automatic — you must scan each page separately.
- AML results are cached; refreshing the block list shows the latest status.

### Customer 360 Performance

- The 360 page uses **React Query** with a 2-minute cache. Navigating away and back within 2 minutes returns cached data.
- Policy, payment, and claims **tabs lazy-load** their data but do not require additional API calls (all included in initial fetch).
- **Empty tabs** display helpful placeholder text (e.g., "No claims found for this customer").

### Editing & Validation

- **Customer name edit**: System splits on the first space (first name / remaining = last name). E.g., "John Paul Smith" → firstName=John, lastName="Paul Smith".
- **Email and phone** accept any text in the edit form — no format validation enforced in the UI (validation handled server-side).
- **ID Number** field is optional and accepts any text.
- Edits in the 360 detail header do **not** auto-save; you must click the **Save** button, and clicking **Cancel** reverts all unsaved changes.

### Searching & Filtering

- **Search** across Customer List supports: name, email, phone, ID number. Partial matches work (e.g., "john" finds "John Smith").
- **Status filter** on Customer List resets pagination to page 1 when applied.
- **Customer Search page** is a dedicated, larger search UI separate from the list; results are paginated independently.

### Rare Scenarios

- **Customer with no contact info**: Shows "—" (en-dash) for missing phone/email. Can still be viewed and managed.
- **Policy without a premium**: In the 360 tab, displays "P 0" formatted as currency.
- **Claim with no date**: Displays "—" in the date column.
- **Block reason on Block List**: If empty, inline edit cell shows "—"; edit and enter a reason to populate it.

## Related modules

- [Policies](/help/policies) — view and manage customer policies linked from the 360 view
- [Customer KYC](/help/customer-kyc) — detailed KYC onboarding and compliance workflows
- [Claims](/help/claims) — customer claim records visible in the 360 Claims tab
- [Payments](/help/payments) — payment history accessible via the 360 Payments tab
- [Leads](/help/leads) — prospective customers before they become active accounts
- [Access Control](/help/access-control) — manage who can view/block customers
- [Audit Trail](/help/audit-trail) — track changes to customer records and block status
