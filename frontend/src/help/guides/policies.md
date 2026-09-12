---
lastReviewed: 2026-06-03
---
## Overview

The Policies module is the central hub for managing all insurance policies within Alpha Direct. CSRs and operators use this module to create new policies (for Domestic, Commercial, and Specialist products), view policy details, manage policy actions (quotes, endorsements, renewals), and handle administrative tasks like cancellation requests and group policies. The module supports the full lifecycle of a policy from creation through renewal and cancellation.

## Who uses this

- **CSR (Customer Service Representatives)**: Create policies, search/view customer policies, process cancellations, handle renewals.
- **Underwriters**: Review policies in Quote or In-Approval status, manage policy actions via the Policy Actions tab.
- **Finance/Operations**: Monitor renewals, track premiums, process cancellation requests, manage billing.
- **Managers**: Monitor policy performance via the Renewals and Cancellation Requests dashboards.

## Step-by-step

### Creating a new policy

1. From the Policies list, click the **Create Policy** button (top right, blue "+").
2. You will enter the **Policy Creation Wizard** which has the following sections (each can be expanded/collapsed):

#### Step 1: Policy & Customer Details
- **Product** (required): Choose Commercial, Domestic, Engineering, Specialist, or Marine insurance.
- **Plan** (required): Select a plan within the chosen product.
- **Entity Type** (required): Choose "Individual" (person) or "Organisation" (company).
- **Company** (if Organisation): Select the company that will hold the policy.
- **Agency** (required): Select the agency handling this policy. Once selected, the Agent dropdown populates based on that agency.
- **Agent** (optional): Select a specific agent within the chosen agency.
- **Premium Frequency** (required): Monthly, Annual, Quarterly, or Manual Input. Selecting a frequency auto-calculates the policy expiry date.
- **Start Date** (required): When the policy coverage begins. Backdated start dates are allowed for corrections and endorsements.
- **Expiry Date** (auto-calculated): Set automatically based on premium frequency, unless you manually override it with Manual Input frequency.
- **Binder Date** (optional): When billing starts (if different from start date).
- **GFS Policy Number** (optional): Legacy external system reference.
- **Note** (optional): Internal notes about the policy.

**Customer Information** (Individual only):
- **First Name, Last Name** (required): Letters only, max 16 chars each.
- **Cellphone** (required): 8 digits starting with 7 (Botswana format).
- **Email** (optional): Must be valid if provided.
- **Gender** (required): Male or Female.
- **Date of Birth** (required): Must be 18-100 years old.
- **Marital Status** (required): Single, Married, Divorced, Widowed.
- **Omang or Passport** (required for non-Commercial Insurance): Either ID required. Omang format: 9 digits, 5th digit must be 1 or 2. Passport: alphanumeric, max 16 chars.
- **State/Province** (required): Select from dropdown.
- **City** (required): Populates based on selected state.
- **Post Address** (required): Mailing address, max 80 chars.
- **Source of Income** (required): Unemployed, Employment, Pensioner/Retired, Self-Employment/Business, Inheritance, Gifts, Investments.
- **Currently Insured** (optional): Whether customer has existing insurance.
- **Hear About Alpha** (optional): How they learned about Alpha Direct.

3. Click **Save** or **Create Policy**. If creating, the policy is created in draft status and the next sections unlock.

#### Step 2: Risk Addresses (Domestic & Commercial only)
This section appears only after Step 1 is saved. You can:
- **Add Risk Address**: Enter property/location details (address name, physical address, state/city, construction type, safety features, etc.).
- **Safety Features**: Check boxes for Central Fire Alarm, Central Burglar Alarm, Gated Community, Automatic Sprinkler.
- **Underwriting Classification**: Town Class, Risk Class, ISO RCV, Occupancy Type, distances to water/fire station/hydrant.
- **Save Risk Address**: Adds the address to the policy. You must have at least one risk address before adding coverages.
- **Cancel Risk Address**: Soft-delete (can be reinstated later). A "Reinstate" button appears for cancelled addresses.

#### Step 3: Coverages (Domestic & Commercial only)
Add insurance coverages to each risk address:
- **Coverage Type** (required): Select from the master coverage list (e.g., Buildings, Contents, Liability).
- **Risk Address** (required): Choose which address this coverage applies to.
- **Coverage Value** (varies): Sum insured, auto-calculated if subcoverages exist, or disabled for certain coverage types.
- **Rate %** (optional): Premium rate per unit of coverage.
- **Sub-coverages**: Some coverages have sub-items (e.g., Buildings has Walls, Roof, etc.). Each sub-item can have its own sum insured and rate.
- **Extensions** (optional): Additional riders (e.g., Theft Excess, Burglar Warranty).
- **Excesses** (optional): Customer excess/deductible per coverage.
- **Save Coverage**: Adds coverage to the policy. Total premium updates in real-time.
- **Edit/Delete Coverage**: Modify or remove a saved coverage. Deleted coverages can be reinstated.
- **Singleton Families**: Certain coverage types (e.g., EAR, CAR, PAR) allow only one per risk address. Duplicates are blocked with a clear error message.
- **One Coverage Per Address** (Specialist products): Products 16–22 allow only one coverage per address, regardless of type.

#### Step 4: Excel Import (Optional)
Import bulk data from Excel templates:
- **Download Template**: Download the Excel template for Risk Addresses, Coverages, Specified Items, or Beneficiaries.
- **Upload File**: Select a completed template file.
- **Import**: The system validates and imports data. A preview shows what was imported.
- **Confirm Import**: Review and confirm before the data is saved to the policy.

#### Step 5: Review & Submit (Domestic & Commercial only)
Final step before submission:
- **Summary**: View total premium, all risk addresses, and coverages.
- **Policy Actions**: Submit for approval → Issue, or keep as Quote.
- **KYC Documents** (placeholder for future): Upload driving license, OMANG, proof of residence, etc.

### Viewing policy details

1. From the Policies list, click a **Policy Number** (or search and click).
2. The **Policy Detail Page** opens with tabs for:
   - **Policy Details**: Core policy info (product, customer, dates, status).
   - **Customer**: Full customer name, contact info, address.
   - **Policy Actions**: Quote → In Approval → Approved → Issued workflow (Domestic/Commercial only).
   - **Risk Addresses**: Property/location details.
   - **Coverages**: Coverages and sub-coverages (read-only; edit via "Edit Policy" button).
   - **Vehicles, Members, Devices**: Product-specific tabs.
   - **Claims**: Active claims against the policy.
   - **Ledger**: Financial entries and receivables.
   - **Transactions**: Payment history.
   - **Banking**: Payment method and Realpay contracts (if enabled).
   - **Documents**: Uploaded policy documents.
   - **Logs**: Audit trail of all changes.
   - **And others**: Attachments, KYC docs, schedule transactions, reinsurance, etc.

3. To **Edit Policy**: Click the "✎ Edit Policy" button (top right, only visible for Domestic/Commercial/Specialist products). Opens the same wizard as create, pre-populated with current data. You can switch between policy actions (quotes/endorsements) via a dropdown.

### Renewing a policy

1. From the **Renewals** page (via menu), view all policies due for renewal.
2. Policies show:
   - **Old Premium**: Previous term's premium.
   - **New Premium**: Rerated amount for the renewal term.
   - **Rerated**: Whether the premium has been recalculated.
   - **Renewed**: Whether the renewal has been completed (Yes/No).
3. Filter by **Renewed** status (All, Renewed, Not Renewed) or search by policy number/customer name.
4. Click a policy number to view full details and take renewal action (backend-driven; CSR approval/processing may vary by company workflow).

### Handling cancellation requests

1. From the **Cancellation Requests** page (via menu), view pending cancellations.
2. Each request shows:
   - **Status**: Pending, Approved, or Declined.
   - **Reason**: Why the customer requested cancellation.
   - **Product** and **Policy #**: Which policy is affected.
3. For **Pending** requests:
   - Click **Approve** to accept the cancellation (confirm in dialog first).
   - Click **Decline** to reject it (confirm in dialog first).
4. Once approved/declined, the status updates and action buttons disappear.

### Managing group policies

1. From the **Group Policies** page (via menu), view all group/bulk policies.
2. Filter by **Status** (All, Active, Inactive, Cancelled, Expired) or search.
3. Click a policy number to view its details.
4. Group policies follow the same structure as individual policies but apply to multiple members (e.g., corporate group life, employee benefits).

### Filtering and searching policies

On the **Policies list page**:
- **Product filter**: All Products, Instant (non-Commercial/Domestic), Commercial (COMG), Domestic (DOMG), or specific products.
  - Selecting a Domestic/Commercial product changes the Status filter to Domestic/Commercial-specific labels.
- **Status filter**: Depends on product:
  - **Default products**: Active, In-Active, Cancelled, Expired.
  - **Domestic/Commercial (DOMG/COMG)**: Issued, In Quote, Draft, Cancelled.
- **Search**: By policy number or customer name (debounced 500ms).
- **Pagination**: Page buttons, "Previous/Next", or jump to a specific page number.
- **Draft policies**: Filter by status "Draft" to see in-progress policies. A "Continue" link lets you resume editing (only for Domestic/Commercial/Specialist).

## Field reference

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| **Product** | Dropdown | Yes | Commercial, Domestic, Engineering, Specialist, Marine; read-only in edit mode |
| **Plan** | Dropdown | Yes | Filters based on product selected |
| **Entity Type** | Toggle | Yes | Individual or Organisation |
| **Company** | Dropdown | If Org | Required for Organisation type only |
| **Agency** | Searchable Dropdown | Yes | Fetches agency list on focus; auto-fills agents |
| **Agent** | Dropdown | No | Populates based on selected agency |
| **UW Status** | Dropdown | No | UW Open, Pending UW, Approved UW (info only) |
| **Premium Frequency** | Dropdown | Yes | Monthly, Annual, Quarterly, Manual Input; sets expiry date |
| **Start Date** | Date | Yes | Policy coverage start; backdated dates allowed |
| **Expiry Date** | Date | Auto | Calculated from start date + frequency; editable with Manual Input |
| **Binder Date** | Date | No | When billing actually starts (if different from start date) |
| **GFS Policy Number** | Text | No | External system reference |
| **Policy Note** | Text Area | No | Internal notes |
| **First Name** | Text | Yes (Ind) | Letters only, max 16 chars; required for Individual only |
| **Middle Name** | Text | No | Letters only |
| **Last Name** | Text | Yes (Ind) | Letters only, max 16 chars; required for Individual only |
| **Cellphone** | Text | Yes (Ind) | 8 digits starting with 7 (format: 7XXXXXXX) |
| **Email** | Text | No | Valid email if provided |
| **Gender** | Dropdown | Yes (Ind) | Male or Female |
| **Date of Birth** | Date | Yes (Ind) | Must be 18–100 years old |
| **Marital Status** | Dropdown | Yes (Ind) | Single, Married, Divorced, Widowed |
| **Omang** | Text | If no Passport | 9 digits; 5th digit must be 1 or 2; not required for Commercial Insurance |
| **Passport** | Text | If no Omang | Alphanumeric, max 16 chars; not required for Commercial Insurance |
| **State/Province** | Dropdown | Yes (Ind) | Populates cities based on selection |
| **City** | Dropdown | Yes (Ind) | Depends on selected state |
| **Post Address** | Text Area | Yes (Ind) | Mailing address, max 80 chars |
| **Source of Income** | Dropdown | Yes (Ind) | Unemployed, Employment, Pensioner/Retired, Self-Employment, Inheritance, Gifts, Investments |
| **Coverage Type** | Dropdown | Yes (Cov) | Buildings, Contents, Liability, etc. |
| **Risk Address** | Dropdown | Yes (Cov) | Which address this coverage applies to |
| **Coverage Value** | Text | Conditional | Sum insured; auto-calculated if subcoverages exist or hidden for some types |
| **Rate %** | Text | No | Premium rate per unit |

## Tips & gotchas

- **Draft policies auto-save every 30 seconds** (create mode only). If you lose connection or close the tab, a "Load Draft" banner appears on the Policies list, letting you resume. Edits to existing policies save on-demand via the **Save** button.
- **Omang/Passport are mutually exclusive** (either one required for Individual, non-Commercial Insurance). Commercial Insurance individuals don't need ID at all.
- **Policy expiry auto-calculates** based on Start Date + Premium Frequency (e.g., Annual = +365 days). Manual Input frequency lets you set a custom expiry.
- **Backdated start dates are allowed** for endorsements and corrections (no "future date only" block).
- **Risk addresses are required before coverages**. The Save Coverage button is locked until at least one risk address exists.
- **Singleton coverage families** (EAR, CAR, PAR, Machinery Breakdown, Medical Malpractice, Professional Indemnity, Directors & Officers, Marine Open/Once-Off, Travel) allow only one per risk address. Attempts to add a duplicate show an error: *"This risk address already has a {family} coverage. Only one is allowed per address."*
- **Specialist products (16, 17, 18, 20, 22) enforce one coverage per address**, regardless of type. Trying to add a second coverage on the same address blocks the save.
- **Cancelled risk addresses and coverages show a line-through, greyed-out card** with a green "Reinstate" button. Reinstatement reverses the soft-delete; pro-rata premium applies on the next endorsement.
- **MIS retail products (1–6, 9, 12, 13)** don't use the V2 coverage-based wizard. The "Edit Policy" button is hidden for these; CSRs use the legacy admin to edit them.
- **Commercial Insurance individuals don't need Omang or Passport**. Validation skips the ID requirement for the Commercial product type.
- **Cellphone must start with 7** and be exactly 8 digits (Botswana format). Validation happens on blur and form submit.
- **Validation errors appear on blur or form submit**, not on every keystroke. This reduces noise during typing.
- **Search is debounced 500ms**; pagination defaults to 25 items per page.
- **Edit button ("✎ Edit Policy")** is only visible for Domestic, Commercial, and Specialist products (IDs 7, 8, 16–22). MIS policies cannot be edited in V2 yet.
- **Agents are lazy-loaded by agency** and **cities by state** — the dependent dropdown stays empty until the parent is selected.

## Related modules

- [Customers](/help/customers) — Manage customer master records and KYC
- [Claims](/help/claims) — File, track, and adjudicate insurance claims
- [Agents & Staff](/help/agents) — Manage agency and agent relationships
- [Products & Plans](/help/products) — Define insurance products, plans, and coverage types
- [Underwriting](/help/underwriting) — UW queue, approval workflow, and rating
- [Payments](/help/payments) — Record payments, schedules, and ledger
- [Renewals](/help/renewals) — Renewal dashboard and rerating
