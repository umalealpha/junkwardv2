---
lastReviewed: 2026-06-03
---
## Overview

The Reinsurance module manages the structure and configuration of reinsurance arrangements within Alpha Direct's insurance admin portal. It lets you define reinsurance types, register reinsurer companies, set up treaties with effective periods and audit metadata, group product coverages, and configure allocation formulas that drive how claims and premiums are split with reinsurers.

## Who uses this

- **Reinsurance Managers** — maintain treaties, reinsurer records, and formula configurations.
- **Product/Actuarial Teams** — configure coverage groupings and allocation rules by product.
- **Compliance/Audit** — review and verify treaty audit fields, allocation engines, and treaty rollovers.
- **System Administrators** — set up reinsurance types and maintain master data integrity.

## Step-by-step

### Reinsurance Types

**Purpose:** Define the high-level categories of reinsurance (e.g., Quota Share, Excess of Loss).

1. Navigate to **Reinsurance Types**.
2. Click **+ Add New** in the top-right corner, or select an existing type to **Edit**.
3. Fill in the form slide-over:
   - **Type Code** (required): short abbreviation (e.g., `QS`, `XL`)
   - **Type Name** (required): full name (e.g., `Quota Share`)
   - **Description** (required): brief explanation of the type
   - **Status**: toggle to set Active or Inactive
4. Click **Create** (for new) or **Update** (for edits).
5. To remove a type, click the delete icon (red X) and confirm the deletion.
6. Search types by code or name using the **Search** field; pagination shows 25 per page.

### Reinsurers

**Purpose:** Maintain a master list of reinsurance companies and their contact details.

1. Navigate to **Reinsurers**.
2. Click **+ Add Reinsurer** to open the add/edit modal, or click **Edit** on an existing row.
3. Fill in required fields:
   - **Company Name** (required)
   - **Email** (required)
   - **Cellphone** (required)
4. Click **Create** (for new) or **Update** (for edits).
5. To delete a reinsurer, click **Delete**, confirm the deletion, and it will be removed.
6. Search by company name, email, or phone number using the **Search** field.

### Reinsurance Treaties

**Purpose:** Define treaty contracts, including effective dates, commission terms, and allocation formulas.

1. Navigate to **Reinsurance Treaties**.
2. Click **+ Add New** to open the form slide-over, or click the edit icon (pencil) to modify an existing treaty.
3. Fill in treaty header details:
   - **Treaty Name** (required): e.g., "Motor Quota Share 2026"
   - **Treaty Number** (required): e.g., "TRY-2026-001"
   - **Effective From** (required): start date
   - **Effective To** (required): end date (must be on or after the start date)
   - **Provisional Commission (%)** (required): percentage for audit
   - **Proportional Share (%)** (required): percentage for audit
   - **Cash Loss Advise** (required): numeric value
   - **Event Limit** (required): numeric value
   - **Exclusions** (required): text field describing applicable exclusions
   - **Formula Attached**: optional dropdown to select reinsurance formulas; selected formulas appear as removable chips
   - **Status**: toggle to set Active or Inactive
4. Click **Create** or **Update** to save.
5. **Audit flag:** if a treaty is Active but missing provisional commission or proportional share values, a warning badge "⚠ needs audit fields" appears in the table. Click Edit to fill these in.
6. To delete a treaty, click the delete icon and confirm.
7. To **rollover** a treaty (clone it to a new period):
   - Click the green refresh icon on the treaty row.
   - A modal opens showing the source treaty details.
   - Pre-filled new treaty name (with year bumped), effective dates, and an optional "Force" checkbox to override duplicate-name or overlap checks.
   - Click **Dry-run** first to preview what will be cloned.
   - Click **Rollover Now** to commit; all `reinsurance_treaty_details` rows from the source are cloned into the new treaty with status=Active.
   - The source treaty is left untouched; manually retire it (set status=Inactive) once confirmed.

**Key note:** Provisional Commission, Proportional Share, Cash Loss Advise, Event Limit, and Exclusions are stored for regulatory audit. They do **not** drive the allocation engine—that is controlled by reinsurance formulas and their detail rows.

### Reinsurance Coverage Grouping

**Purpose:** Group product coverages together and define SI/RI limit allocation behavior for each coverage.

1. Navigate to **Reinsurance Coverage Grouping**.
2. Click **+ Add New** or click the edit icon on an existing group.
3. Fill in the group header:
   - **Group Code** (required): e.g., `GRP-MOTOR`
   - **Group Name** (required): e.g., `Motor Coverages`
   - **Product** (optional): when adding a new group, select a product from the dropdown; product is locked when editing.
   - **Status**: toggle to set Active or Inactive
4. After selecting a product (on new groups), a **Coverages** table appears showing all coverages for that product.
5. For each coverage row, set:
   - **SI/Premium**: dropdown — select whether allocation is based on "SumInsured" or "Premium"
   - **RI Limit**: dropdown — select "SumInsured", "Skip", or "Other"
   - **Limit** (text field): only visible and editable if RI Limit = "Other"; enter a custom limit value
6. Click **Create** or **Update** to save.
7. To delete a group, click the delete icon; related coverage rows are also removed.

### Reinsurance Formulas

**Purpose:** Define allocation rules that determine how claims and premiums are split with reinsurers, keyed by coverage group, SI threshold, and operator logic.

1. Navigate to **Reinsurance Formulas**.
2. Click **+ Add New** or click the edit icon on an existing formula.
3. Fill in the formula header:
   - **Formula Name** (required): e.g., `Motor Quota Share Formula`
   - **Formula Code** (required): e.g., `FRM-MOTOR-QS`
   - **Product** (optional): select from dropdown
   - **Reinsurance Type** (optional): select from dropdown (e.g., Quota Share)
   - **Formula Type** (optional): select from dropdown or enter as text
   - **Status**: toggle to set Active or Inactive
4. Scroll to **Formula Details** section (below a dividing line).
5. Set allocation logic:
   - **Reinsurance Group** (optional): select the coverage group this formula applies to
   - **Motor Type** (conditional): only shown if a specific type is selected; enter vehicle type details
   - **Operator** (optional): dropdown with operators (`=`, `<<`, `<=`, `>=`, `<>`, `between`, `not between`)
   - **SI Allocation** (optional): numeric threshold value
   - **Percentage** (optional): split percentage
   - **From Date** and **To Date** (optional): effective period for this formula rule
6. Click **Create** or **Update** to save.
7. To delete a formula, click the delete icon and confirm.
8. Search formulas by code or name using the **Search** field.

## Field reference

| Field | Section | Meaning | Required | Data Type |
|-------|---------|---------|----------|-----------|
| Type Code | Reinsurance Types | Short code identifier (e.g., QS, XL) | Yes | Text (10 chars) |
| Type Name | Reinsurance Types | Full name of reinsurance type | Yes | Text (100 chars) |
| Description | Reinsurance Types | Explanation of the type | Yes | Text (1000 chars) |
| Status | Reinsurance Types, Treaties, Groups, Formulas | 1=Active, 0=Inactive | No | Boolean toggle |
| Company Name | Reinsurers | Name of reinsurance company | Yes | Text (200 chars) |
| Email | Reinsurers | Contact email address | Yes | Email |
| Cellphone | Reinsurers | Phone number | Yes | Text (20 chars) |
| Treaty Name | Treaties | Human-readable treaty name | Yes | Text (200 chars) |
| Treaty Number | Treaties | Unique treaty identifier | Yes | Text (100 chars) |
| Effective From | Treaties | Start date of treaty (YYYY-MM-DD) | Yes | Date |
| Effective To | Treaties | End date of treaty (YYYY-MM-DD); must be >= Effective From | Yes | Date |
| Provisional Commission (%) | Treaties | Commission percentage for audit | Yes | Decimal (0–100) |
| Proportional Share (%) | Treaties | Share percentage for audit | Yes | Decimal (0–100) |
| Cash Loss Advise | Treaties | Numeric loss trigger value | Yes | Decimal |
| Event Limit | Treaties | Maximum loss per event | Yes | Decimal |
| Exclusions | Treaties | List of excluded coverages/situations | Yes | Text (5000 chars) |
| Formula Attached | Treaties | List of formula IDs linked to treaty | No | Array of integers |
| Group Code | Coverage Groups | Short code for group (e.g., GRP-MOTOR) | Yes | Text (50 chars) |
| Group Name | Coverage Groups | Full name of coverage group | Yes | Text (200 chars) |
| Product | Coverage Groups | Product ID; locked after creation | No | Integer (FK to products) |
| SI/Premium | Coverage Rows (within group) | Allocation basis (1=SumInsured, 2=Premium) | No | Integer |
| RI Limit | Coverage Rows (within group) | Limit type (1=SumInsured, 2=Skip, 3=Other) | No | Integer |
| Limit Value | Coverage Rows (within group) | Custom limit; only applicable if RI Limit=3 | No | Text |
| Formula Name | Formulas | Human-readable formula name | Yes | Text (200 chars) |
| Formula Code | Formulas | Unique formula identifier | Yes | Text (50 chars) |
| Reinsurance Type | Formulas | Type ID of reinsurance (FK) | No | Integer |
| Formula Type | Formulas | Category (e.g., Proportional, Non-Proportional) | No | Text (100 chars) |
| Reinsurance Group | Formula Details | Group ID for coverage allocation | No | Integer (FK) |
| Operator | Formula Details | Comparison operator (=, <<, <=, >=, <>, between, not between) | No | Text |
| SI Allocation | Formula Details | Sum Insured threshold for rule | No | Decimal |
| Percentage | Formula Details | Reinsurance share percentage | No | Decimal |
| From Date | Formula Details | Rule effective start (YYYY-MM-DD) | No | Date |
| To Date | Formula Details | Rule effective end (YYYY-MM-DD) | No | Date |

## Tips & gotchas

- **Audit fields on active treaties:** The system flags any Active treaty missing provisional commission or proportional share (showing a yellow warning badge). These fields are stored for regulatory audit only and do not control the claims/premium split—that is driven by reinsurance formulas and their detail rows instead. If you see the badge, click Edit and provide values to satisfy audit requirements.

- **Treaty rollover behavior:** The rollover feature clones the entire source treaty header plus all `reinsurance_treaty_details` child rows into a new period with auto-bumped dates. The source treaty remains untouched; you must manually set it to Inactive after confirming reinsurers are live on the new treaty. Always run a Dry-run first to preview what will be copied.

- **Coverage group product lock:** When creating a new coverage group and selecting a product, all available coverages for that product are loaded and displayed in an editable table. Once saved, the product reference is locked and cannot be changed on edit—you must delete and recreate the group to switch products.

- **Formula operator options:** The `between` and `not between` operators are for range-based SI allocation rules. For example, SI > 300,000 AND SI <= 500,000 would use `between` with two boundaries in the SI Allocation field.

- **Date validation on treaties:** The Effective To date must be on or after Effective From. If you attempt to save with Effective To before Effective From, a validation error will appear.

- **Pagination:** All list pages show 25 records per page. Use the **Search** field to filter by name, code, or number, and use pagination controls (Prev/Next or direct page jump) to navigate large result sets.

- **Delete confirmations:** Deleting a reinsurance type, treaty, group, or formula requires a confirmation dialog. Groups also warn that related coverage rows will be removed. Once deleted, records cannot be recovered.

- **Formula details conditional display:** The "Motor Type" field in formula details only appears if a specific type ID (35) is selected in the main Reinsurance Type dropdown. This supports Motor-specific allocation rules.

- **Status and soft-delete:** Setting status to 0 (Inactive) does not hard-delete records; they remain in the system for audit and historical reference. Use the delete button only if you intend permanent removal.

## Related modules

- [Policies](/help/policies)
- [Quotes](/help/quotes)
- [Underwriting](/help/underwriting)
- [Claims](/help/claims)
- [Products](/help/products)
- [Coverage Management](/help/coverage-management)
- [Accounting](/help/accounting)
- [Audit Trail](/help/audit-trail)
