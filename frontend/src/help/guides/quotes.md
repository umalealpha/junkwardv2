---
lastReviewed: 2026-06-03
---
## Overview

The Quotes module is where you browse, view, and manage insurance quotes for customers. Each quote shows premium pricing, customer details, and vehicle information (for motor products). Active quotes can be updated, premium adjusted, or converted into policies.

## Who uses this

- **Customer Service Representatives (CSRs)** — search and retrieve quotes for customers; send or resend quotes.
- **Underwriters** — review quotes, adjust premiums (discounts/surcharges), and approve issuance.
- **Agents** — view their quoted business and track conversions to policies.
- **Administrators** — manage quote lifecycle and permissions.

## Step-by-step

### List and search quotes

1. Go to **Quotes** in the sidebar.
2. You see a table of all quotes with Code, Customer, Phone, Agent, Policy No, Status, and Created date.
3. **Search** using Quote code, customer name, or phone number — press Enter or click away to apply.
4. **Filter by Status**:
   - All Status (default)
   - Active (new, can be edited)
   - Used (Policy Generated)
   - Expired
   - Rejected
   - Draft
5. **Paginate**: Use Prev / Next buttons or enter a page number in the "Go to" box.
   - Shows 25 quotes per page.

**Note:** Used, Expired, and Rejected quotes appear faded in the list and are read-only.

### View quote details

1. Click the **quote code** (blue link) in the list or navigate directly to `/quotes/{id}`.
2. See a full detail page with:
   - **Customer Details** card (name, email, phone, gender, DOB, marital status, identity documents, address)
   - **Quote Information** card (code, status, product, plan, agent, store, expiry date, created date)
   - **Vehicle Details** card (only for motor products — make, model, year, variant, estimated value, import status, prior accidents)
   - **Premium Details** card (monthly, 3-instalment, annual premium, rate, discount/surcharge applied)
   - **Premium Update History** table (if adjustments have been made) — shows old/new premium, adjustment amount, reason, updated by, date.

### Export quote as PDF

1. On the **Quote Detail** page, click the **Export PDF** button (top right).
2. A PDF is generated and opens in a new tab (requires wkhtmltopdf on production server).
3. If generation fails offline or takes too long, an error message explains the limitation.

### Update an active quote

1. On the **Quote Detail** page, click **Update Quote** button (only available for Active quotes).
2. On the **Update Quote** page, edit:
   - **Customer Details**: first name, middle name, last name, email, phone, gender, date of birth, omang, passport, marital status, address.
   - **Vehicle Details** (if present):
     - **Japanese Import** toggle (Yes/No) — resets all other vehicle fields if changed.
     - **Make**, **Model**, **Year**, **Variant** — use dropdowns or type freely (combobox).
     - **Estimated Value** (currency, BWP).
     - **Prior Accidents** (0–3).
   - **Calculate Premium** button — runs the rating engine and displays new premium breakdown (monthly, 3-instalment, annual, rate).
     - Required fields: Make, Year, DOB, Estimated Value, Gender, Marital Status.
     - Shows change from current premium.
   - **Discount / Surcharge** (optional):
     - **Type**: Discount or Surcharge.
     - **Value Type**: Flat Amount (BWP) or Percentage (%).
     - **Value**: numeric (e.g., 500 for BWP or 10 for 10%).
     - **Reason**: required text field.
3. Click **Update Quote** to save.
   - If a new premium was calculated via rating engine, the difference is applied automatically.
   - If a manual discount/surcharge is filled, it is applied on top.
4. You are redirected back to the detail page on success.

### Adjust premium on an active quote (without editing vehicle)

1. On the **Quote Detail** page, click **Adjust Premium** button (only for Active quotes).
2. A modal appears with:
   - **Type**: Discount or Surcharge.
   - **Value Type**: Flat Amount (BWP) or Percentage (%).
   - **Value**: numeric input.
   - **Reason**: required text field.
3. Click **Apply** to confirm.
4. The adjustment is recorded in the **Premium Update History** table.

### View linked policy

1. If a quote has been converted into a policy, the detail page shows:
   - **Policy Generated** indicator (blue badge).
   - **View Policy [Number]** button (green) — links to the full policy record.
2. On the Quotes list, click the **Policy No** link (blue) to jump to that policy.

### MIS / legacy product quotes (read-only)

1. Quotes for non-DomCom products (e.g., MIS, retail) show an **amber warning banner**:
   > MIS quote — issue on legacy
   > This quote is for [Product Name]. MIS / retail policies have their own wording and rating engine and must be issued on legacy graphite. This page is read-only for such quotes.
2. The **Update Quote** and **Adjust Premium** buttons are hidden.
3. Click the **Open legacy graphite** link in the banner to complete issuance in the legacy system.

## Field reference

| Field | Meaning | Required | Notes |
|-------|---------|----------|-------|
| **Quote Code** | Unique identifier for the quote | Yes | Auto-generated; read-only. |
| **Status** | Active, Draft, Used, Expired, or Rejected | Yes | Determines which actions are available. |
| **Customer Name** | Full name of the quoted customer | Yes | First + Middle + Last name. |
| **Email** | Customer email address | Yes | Used for sending quotes/documents. |
| **Cellphone** | Customer mobile number | Yes | Primary contact. |
| **Gender** | Male or Female | Yes | Required for premium calculation. |
| **Date of Birth** | Customer's birth date | Yes | Required for premium calculation and age-based rules. |
| **Marital Status** | Single, Married, Divorced, Widowed, Living Together, Living Separately | Yes | Required for premium calculation. |
| **Omang** | Botswana national ID number | No | Identity verification. |
| **Passport** | International passport number | No | For non-citizen customers. |
| **Address** | Full postal/residential address | No | For policy delivery and claims. |
| **Product** | Insurance product name (e.g., DomCom Motor) | Yes | Determines features and rating. |
| **Plan** | Product variant (e.g., Comprehensive, Third-Party) | Yes | Linked to product. |
| **Agent Name** | Name of the agent who quoted it | No | Tracking and commission. |
| **Store** | Branch or sales outlet | No | Organizational grouping. |
| **Expiry Date** | When the quote becomes invalid | Yes | Typically 30–90 days from creation. |
| **Make** | Vehicle manufacturer (e.g., Toyota, BMW) | Yes (motor) | Combobox; sourced from vehicle lookup. |
| **Model** | Vehicle model (e.g., Corolla, X5) | Yes (motor) | Dependent on Make. |
| **Year** | Manufacturing year | Yes (motor) | Dependent on Make + Model. |
| **Variant** | Specific trim or engine variant | No (motor) | Dependent on Make + Model + Year. |
| **Estimated Value (BWP)** | Sum insured / vehicle value | Yes (motor) | Used in premium calculation. |
| **Japanese Import** | Is the vehicle a Japanese import? | Yes (motor) | Affects available makes/models and rates. |
| **Prior Accidents** | Number of previous claim incidents | No (motor) | 0–3; affects rate. |
| **Monthly Premium** | Premium paid monthly | Calculated | Read-only; depends on annual / 12. |
| **3-Instalment Premium** | Premium for 3 payment installments | Calculated | Read-only; depends on payment plan. |
| **Annual Premium** | Full year premium before discount/surcharge | Calculated | Shown prominently in green. |
| **Premium Rate** | Percentage rate applied during calculation | Calculated | Read-only; from rating engine. |
| **Discount/Surcharge** | Applied adjustment to annual premium | No | Manually added; recorded with reason. |

## Tips & gotchas

### Searching and filtering
- The **Search** field looks in Quote code, Customer name, and Phone number. It is case-insensitive and partial-matches are supported.
- The **Status** dropdown uses numeric codes internally (0 = Draft, 1 = Active, 2 = Used, 3 = Expired, 4 = Rejected), but displays readable labels.
- Filtering by status resets pagination to page 1.

### Active vs. inactive quotes
- Only **Active** quotes (status 1) can be edited or have premiums adjusted.
- **Used** quotes (status 2) are linked to a policy and are read-only.
- **Expired** quotes (status 3) cannot be updated; you must create a new quote.
- **Rejected** quotes (status 4) are archived and cannot be reopened.
- **Draft** quotes (status 0) exist but are rarely used in V2.

### Vehicle lookups and cascades
- Changing **Japanese Import** clears all vehicle fields (Make, Model, Year, Variant, Premium).
- Changing **Make** clears Model, Year, Variant, and calculated Premium.
- Changing **Model** clears Year, Variant, and calculated Premium.
- Changing **Year** clears Variant and calculated Premium.
- The **Make**, **Model**, **Year**, and **Variant** fields are comboboxes: you can type freely or select from the dropdown list. Typed values are accepted even if not in the list (legacy vehicle support).

### Premium calculation
- Click **Calculate Premium** to run the rating engine with the current vehicle and customer details.
- **Required fields for calculation**: Make, Year, DOB, Estimated Value, Gender, Marital Status. If any is empty, an error message lists the missing fields.
- The engine returns monthly, 3-instalment, annual, and rate; it shows the change from the current premium.
- Clicking **Calculate** multiple times with different details will overwrite the previous calculation (not accumulated).
- When you save the quote, if a calculation was made, its annual premium is compared to the original; the difference is applied as a Surcharge (increase) or Discount (decrease) with reason "Re-rated via quote update."

### Discount and surcharge logic
- In the **Adjust Premium** modal or **Discount / Surcharge** section on the update page, you choose:
  - **Type**: Discount (reduces premium) or Surcharge (increases premium).
  - **Value Type**: Flat Amount in BWP or Percentage (%).
  - **Value**: The amount or percentage; if percentage, 10 means 10%.
  - **Reason**: Required; explains the adjustment (e.g., "Loyalty discount", "High-risk surcharge").
- Discounts and surcharges are recorded in the **Premium Update History** with the old premium, new premium, adjustment amount, reason, who made the change, and timestamp.
- Multiple adjustments can be applied over time; each is logged separately.

### MIS and legacy products
- V2 Graphite supports only DomCom Motor (7, 8) and Engineering/Specialist (16–20, 22) products.
- Quotes for other products (MIS, retail) are **read-only** on this page; no Update Quote or Adjust Premium buttons.
- A warning banner explains that such quotes must be issued on legacy Graphite (https://graphite.alphadirect.co.bw).

### PDF export
- **Export PDF** is always available, even for expired/rejected/used quotes.
- Export requires `wkhtmltopdf` binary, which is available on the production server only. Local/dev exports will fail with a message.
- The PDF includes all quote details and can be sent to the customer.

### Policy linking
- When a quote is used to generate a policy, its status changes to **Used** (status 2).
- The **Quote Detail** page then shows **Policy Generated** badge and a **View Policy** button.
- On the Quotes list, the **Policy No** column shows a link to the policy.

### Edge cases
- If a customer's DOB is empty, premium calculation fails (required for rating).
- If the vehicle's estimated value is 0 or missing, premium calculation fails.
- Changing vehicle details (make, year, etc.) clears the calculated premium, forcing a recalculation before saving.
- The **Reason** field for adjustments is mandatory; you cannot apply a discount/surcharge without it.
- Pagination shows a maximum of 7 page buttons at once (1, 2, 3, ..., lastPage).

## Related modules

- [Policies](/help/policies) — linked when a quote is converted into an active policy.
- [Leads](/help/leads) — source data for quotes; customers who enquired.
- [Customers](/help/customers) — full customer records and KYC status.
- [Agents](/help/agents) — agent lookup and commission tracking.
- [Products](/help/products) — product definitions and plan structures.
- [Reports](/help/reports) — quote conversion and premium analytics.
