---
lastReviewed: 2026-06-03
---
## Overview

Coverage Management is where you define and organize all the insurance coverages, sub-coverages, optional items, and add-ons that are available in your product catalogue. This module drives what customers see when they buy policies — every coverage code, name, rate, and field on a policy form is built here. You maintain the master data once, and it flows downstream to quotes, policies, and underwriting.

## Who uses this

- Product managers and product analysts who design coverage structures
- Pricing specialists who assign rates to each coverage
- Insurance underwriters who define coverage rules and validity periods
- System administrators who manage coverage catalogues and effective dates

## Step-by-step

### Browse all coverages

1. Open the **Coverage Master** tab
2. The main table shows all parent-level coverages with their code, name, group, rate, and whether they require a risk address
3. Use the **Search** field to find coverages by code, name, or group (press Enter or blur to apply)
4. Navigate pages using **Prev**, **Next**, or the page number buttons; use **Go to** to jump directly to a page number
5. Each page shows up to 25 coverages; the footer displays total count and current range

### Add a sub-coverage

1. Open the **Sub Coverages** tab
2. Click **+ Add Sub Coverage** (top right)
3. In the modal form:
   - Select a **Parent Coverage** from the dropdown (required)
   - Enter **Coverage Name** (required) — internal identifier, e.g. "Fire Sub"
   - Enter **Screen Name** (required) — the label shown on the policy form
   - Enter **Coverage Group** (optional) — organizes rows, e.g. "Section A"
   - Enter **Sub Coverage Main Name** (optional) — a heading or grouping label
   - Enter **Rate %** (required) — decimal rate applied to this sub-coverage, up to 4 decimal places
   - Enter **Display Seq** (optional) — controls the order on the policy form
   - Tick **Visible to user** to show this on customer-facing forms
   - Set **Effective From** and **Expiration** (optional) — dates when this sub-coverage is valid
4. Click **Create** to save
5. Sub-coverages drive the "Description of Cover" rows in the policy coverage form

### Edit or delete a sub-coverage

1. In the **Sub Coverages** table, find the row
2. Click **Edit** to modify any field (same modal form as Create)
3. Click **Delete** to remove; you'll be asked to confirm the deletion by name

### Add a specified coverage item

1. Open the **Specified Coverage Items** tab
2. Click **+ Add Item** (top right)
3. In the modal form:
   - Select a **Coverage** from the dropdown (required) — the parent coverage this item belongs to
   - Enter **Specified Name** (required) — the item customers choose, e.g. "Canopy", "Windscreen"
   - Enter **Rate %** (required) — the rate applied when this item is selected
   - Set **Effective From** and **Effective To** (optional) — when this item is available
4. Click **Create** to save
5. Specified items are the specific options customers pick from on a policy (e.g., choosing a canopy as a specified peril under Fire)

### Edit or delete a specified item

1. In the **Specified Coverage Items** table, find the row
2. Click **Edit** to modify fields
3. Click **Delete** to remove; you'll be asked to confirm

### Add an extension, excess, or misc item

1. Open the **Extensions, Excesses & Misc** tab
2. Click **+ Add Entry** (top right)
3. In the modal form:
   - Select **Parent Coverage** (required)
   - Select **Type** (required) — one of: Extention, Perils, Excess, Misc
   - Enter **Coverage Name** (required) — internal name
   - Enter **Screen Name** (required) — label on the form
   - Select **Input Type** (optional) — how the customer enters data:
     - NOEDIT: fixed, non-editable
     - NUMBER: a numeric input field
     - RADIO: radio button group
     - DROPDOWN: dropdown selector
   - Enter **Extensions Group** (optional) — groups related items, defaults to "Main"
   - Optionally select a **Sub Coverage** to nest this extension under a specific sub-coverage
   - Enter **Rate %** (required)
   - Enter **Display Seq** (optional) — display order
   - Tick **Visible to user** to show on customer forms
   - Set **Effective From** and **Expiration** (optional)
4. Click **Create** to save

### Edit or delete an extension, excess, or misc item

1. In the **Extensions, Excesses & Misc** table, find the row
2. Click **Edit** to modify any field
3. Click **Delete** to remove; confirm by name

### Filter and search

All four tabs support filtering:

- **Search**: Text field that matches coverage code, name, or group (enter or blur to apply)
- **Parent Coverage** dropdown (Sub Coverages, Specified Items, Extensions tabs): Filter by parent coverage
- **Type** dropdown (Extensions tab only): Filter by Extention, Perils, Excess, or Misc

## Field reference

| Field | Meaning | Required | Notes |
|-------|---------|----------|-------|
| Coverage Code | System identifier for the coverage | Yes (Master only) | Shown in blue, used in API calls |
| Coverage Name | Internal name for the coverage | Yes | Not always shown to customers |
| Screen Name | Label displayed on policy forms | Yes (Sub, Ext) | Visible to policy operators and sometimes customers |
| Coverage Group | Section or section label | No | Used to group related rows on the form |
| Sub Coverage Main Name | Heading for a group of sub-coverages | No | Groups multiple sub-coverage rows under one heading |
| Rate % | Premium rate as a decimal | Yes | Up to 4 decimal places; applied to sum insured or flat premium |
| Display Seq | Numeric order (ascending) | No | Lower numbers appear first on forms |
| Visible to user | Checkbox | No | When unchecked, this coverage/extension is hidden from customer forms |
| Effective From | Date | No | When this coverage becomes active |
| Effective To | Date | No | When this coverage expires |
| Parent Coverage | Reference to a master coverage | Yes (Sub, Ext) | Links sub-coverages and extensions to their parent |
| Input Type | NOEDIT, NUMBER, RADIO, DROPDOWN | No (Ext only) | Controls how customers input data for extensions |
| Extensions Group | Organizes extensions on the form | No | Defaults to "Main" |
| Specified Name | Specific item within a coverage | Yes (Specified items) | e.g., "Canopy" under Fire coverage |
| Type (Extensions) | Extention, Perils, Excess, Misc | Yes (Extensions only) | Categorizes the extension for form layout |
| Has Risk Address | Boolean flag (Master display only) | No | Indicates whether this coverage requires a risk address field |

## Tips & gotchas

**Rates and calculation:**
- Rates are stored as decimals (e.g., 0.0250 = 2.5%). The system accepts up to 4 decimal places.
- Sub-coverages and extensions each have their own rate; they may be combined when premium is calculated.
- If a coverage has both a master rate and a specific rate on a sub-coverage, the system prefers the specific rate.

**Display sequence:**
- On the policy form, rows are ordered by **Display Seq** (lowest number first).
- If Display Seq is empty or null, the system likely uses database insertion order or alphabetical sorting — test before deploying.

**Effective dating:**
- An item with an **Effective From** date in the future is valid starting that date.
- An item with an **Effective To** date in the past is expired and hidden from new policies; existing policies may still reference it.
- Leaving both dates empty means the item is always valid (no time restriction).

**Parent–child hierarchy:**
- Every sub-coverage must have a parent coverage. Deleting a parent may orphan or cascade-delete children — check with your admin.
- Extensions can optionally reference a sub-coverage; if left blank, they're linked directly to the parent coverage.

**User visibility:**
- The **Visible to user** checkbox controls whether this item appears on customer-facing forms.
- If unchecked, the coverage still exists in the system and can be used internally or in underwriting, but operators won't see it when creating policies.

**Deletion safety:**
- Deleting a coverage that is already used in active policies may break those policies. The system will alert you if this is not allowed.
- Always confirm deletion by checking the item name in the popup.

**Search behavior:**
- Search is non-indexed and runs on the server; large datasets may be slow. Paginate or filter by parent coverage first.
- The **Search** field matches partial text in code, name, and group fields across all tabs.

**Pagination:**
- All tables default to 25 rows per page. The **Go to** field jumps to a specific page (1–max).
- The footer shows "Showing X–Y of Z" to indicate your current range.

**Modal forms:**
- Validation errors (missing required fields) trigger an alert before submission.
- After a successful save, the modal closes and the list refreshes automatically.
- If an API error occurs, you'll see an alert with the server error message.

## Related modules

- [Products](/help/products) — Define product lines that use these coverages
- [Policies](/help/policies) — Create and manage policies that include these coverages
- [Quotes](/help/quotes) — Generate quotes using these master coverages
- [Underwriting](/help/underwriting) — Review and approve coverage selections on policies
- [Compliance](/help/compliance) — Ensure coverages meet regulatory requirements
- [Reports](/help/reports) — Analyze coverage uptake and premium by coverage type
