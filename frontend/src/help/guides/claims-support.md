---
lastReviewed: 2026-06-03
---
## Overview

The Claims Support module is where insurance operators manage customer claims from start to finish. Create new claims, track them through status workflow stages (New → Pending Assessment → Approved or Rejected → Closed), manage reserve and payment allocations across policy coverages, attach supporting documents, and handle amendments to claim details.

## Who uses this

- **Claims Handlers/Processors** — register, review, and process claims; transition status and capture assessment notes
- **Finance/Accounts** — allocate reserves and payments to coverages, handle void-payment reversals, create invoices
- **Loss Adjusters/Assessors** — review claim details, input assessment reports and valuations
- **Customer Service Reps** — look up claim history, answer customer inquiries about claim status

## Step-by-step

### View and filter all claims

1. Navigate to **Claims** (main list page).
2. The table shows: Claim #, Customer, Policy #, Product, Type, Status, and Created date.
3. Use the filters:
   - **Search field** — find by claim #, customer name, or policy number
   - **All Statuses** dropdown — filter by New, Pending, Pending Assessment, Approved, Rejected, Closed, Reopen
   - **All Claim Types** dropdown — Motor, Glass, Key Loss, Fire, Burglary, Accident, Legal, Life, Workers Compensation, Liability, Property Damage, Accidental Damage, Business All Risks, Personal All Risks, Mobile Electronic Devices, Goods In Transit, Office Contents, Business Interruption, Fidelity Guarantee, and others
   - **From / To date pickers** — filter by claim registration or incident date range
4. Results show up to 25 claims per page; use **Pagination** controls at the bottom.
5. Click any row to open the claim detail page.

### Register a new claim

1. From the Claims list, click **+ Register Claim** (top right).
2. On the **Create Claim** form:
   - **Select a Policy** (required) — type the policy number to auto-fetch the policy and customer.
   - **Claim Type** (required) — dropdowns dynamically show types available for the selected policy and product.
   - **Registered Claim Date** — defaults to today; adjust if needed.
   - **Incident Information** — date, time, location, description of loss.
   - **Reported By / Date** — who reported the claim and when.
   - **Claim Allocated To / On** — assign to a claims handler; defaults to today.
   - **Product-specific fields** — form sections vary by claim type:
     - **Motor/Vehicle/Accident**: vehicle details, driver info, third-party details, injury info, witness details, damage specifics.
     - **Glass/Windscreen**: damage location, replacement quotes (2 suppliers), photo descriptions.
     - **Life/Accidental Death**: date and cause of death.
     - **Legal**: legal firm, lawyer, matter description, jurisdiction.
     - **Hospital Cash**: patient info, medical scheme, admission/discharge dates, doctor/hospital details.
     - **Goods In Transit**: cargo description, carrier details, consignment origin/destination, insurance details.
     - **Fire/Burglary/Theft**: premises details, security info, date/time of incident, damage value.
     - **Public Liability**: insured contact, accident details, witnesses.
     - **All Risks / Property**: loss/damage discovery, valuation, insurance details.
     - **Other types** — Workers Compensation, Defective Workmanship, Professional Indemnity, Plant All Risks, Travel Insurance, Medical Malpractice, etc. (see full field set in code).
   - **File Attachments** — upload supporting documents (PDF, JPG, PNG, DOC, DOCX) for specific fields (e.g., police affidavit, quotes, photos).
3. Click **Save** to register the claim.

### View and update claim details

1. From the claim list, click any claim to open its detail page.
2. The page displays a **Status Badge** (New/Pending/Pending Assessment/Approved/Rejected/Closed/Reopen) and shows who the claim is allocated to.
3. Click **Edit** to modify claim fields inline (if the claim status permits).
4. The form contains **10 tabs**:
   - **Policy Details** — linked policy number, product, premium, status; customer/company name, email, phone.
   - **Claim Details** — all entered fields from registration: incident info, parties, classification/allocation fields, product-specific data.
   - **Assessor** — assessor name, assessment report, quotations, valuation, notes (for evaluating loss severity).
   - **Attachments** — upload and view all supporting documents (police reports, quotes, invoices, photos, etc.).
   - **Reserves / Payments** — create reserve (estimate) or payment entries; allocate amounts across policy coverages; view running balance.
   - **Suppliers** — track quotes from repair centers; mark as selected; link to invoices.
   - **Invoice** — view supplier invoices and reserve/payment invoices with amounts and dates.
   - **Reinsurance** — recovery/reinsurance details and calculations.
   - **Activity Log** — timeline of all changes, status transitions, and user actions.
   - **Complaint Log** — DFS complaints associated with the claim.

### Transition claim status

1. On the claim detail page, click **Change Status** (top section).
2. A **Status Modal** appears showing the current status and allowed next statuses:
   - From **New** → Pending Assessment, Approved, or Rejected
   - From **Pending Assessment** → Approved or Rejected
   - From **Approved** → Closed
   - From **Rejected** → Closed or Reopen
   - From **Closed** → Reopen
   - From **Reopen** → Pending Assessment or Approved
3. Select the new status from the dropdown.
4. (Optional) If transitioning to **Closed**, you can add a note explaining closure reason.
5. Click **Update Status** to save.

### Create reserves and payments

1. On the claim detail page, go to the **Reserves / Payments** tab.
2. View the **Summary Cards**: Total Reserve, Total Payment, Balance (remaining claim liability).
3. Click **Add Reserve / Payment**:
   - **Transaction Type** (required) — select from System-defined types (Reserve, Payment, Reinsurance Payment, Salvage Reserve, etc.; excludes "Initial").
   - **Transaction Sub Type** (optional) — sub-category; appears as a dropdown after picking the type.
   - **Date** — defaults to today.
   - **Payee** (optional) — repair center, legal firm, hospital, etc.
   - **Address** (optional) — claim's risk address (if multi-location policy).
   - **Invoice Number / Date / Due Date** (optional) — invoice tracking info.
   - **Credit Note** (checkbox) — mark if this is a credit reversal.
   - **Include VAT** (checkbox) — apply VAT to allocation amounts.
   - **Memo / Description** (optional) — internal notes.
4. After picking a Transaction Type, a **Coverage Allocation Table** appears showing all policy coverages (grouped by risk address for DOM/COM products).
5. In the **Reserve Allocation** column, enter the amount to allocate to each coverage.
6. You must enter at least one allocation amount.
7. Click **Submit** to create the reserve/payment row.

### Void a reserve or payment

1. On the **Reserves / Payments** tab, locate the row you want to void.
2. Click **Void Payment** (only appears on rows that have a payment recorded).
3. A **Reserve Information Modal** shows the transaction details for confirmation.
4. Click **Void This Payment** to proceed.
5. A **Void Reason Modal** appears.
6. Enter your reason for voiding (e.g., "Duplicate entry", "Incorrect payee").
7. Click **Confirm** to void and reverse the payment.
8. The original row becomes marked as voided; a reversal entry is created.

### Manage attachments

1. Go to the **Attachments** tab.
2. Click **Add Attachment**:
   - Select a **Document Type** (if applicable) or upload as generic attachment.
   - Upload files (PDF, JPG, PNG, DOC, DOCX).
3. View existing attachments grouped by type.
4. Click the file link to download or view the document.
5. Click **Delete** to remove (if permitted).

## Field reference

| Field | Meaning | Required |
|-------|---------|----------|
| **Policy Number** | Existing policy the claim is for | Yes |
| **Claim Type** | Category of loss (Motor, Glass, Fire, etc.) | Yes |
| **Registered Claim Date** | Date claim was registered in system | No (defaults to today) |
| **Incident Date / Time** | When the loss event occurred | Varies by type |
| **Incident Location** | Where the loss happened | Varies by type |
| **Incident Description** | Summary of what happened | Varies by type |
| **Reported By** | Insured, Agent, Broker, etc. | No |
| **Reported Date** | When claim was first notified | No (defaults to today) |
| **Claim Allocated To** | Claims handler assigned | No |
| **Claim Allocated On** | Assignment date | No (defaults to today) |
| **Vehicle Plate** | Vehicle registration (Motor claims) | No |
| **Driver Name / DOB / License** | Driver details (Motor accidents) | No |
| **Third Party Details** | Other vehicle/party in accident | No |
| **Date of Death / Cause** | Life claims | No |
| **Legal Firm / Lawyer** | Legal claims | No |
| **Patient Name / DOB** | Hospital Cash claims | No |
| **Hospital / Admission Date** | Hospital details (Hospital Cash) | No |
| **Premises Details** | Address, security (Burglary, Fire) | No |
| **Type of Loss** | Property / Liability (DOM/COM) | No |
| **Service Representative** | Internal user assigned (DOM/COM) | No |
| **Primary Attorney Involved** | Yes/No (DOM/COM) | No |
| **Primary Attorney Assigned** | Attorney name if involved | No |
| **Co-Attorney Involved** | Yes/No | No |
| **DFS Complaint** | Is this a DFS complaint? | No |
| **Catastrophe Loss** | Yes/No | No |
| **Transaction Type** | Reserve/Payment/Reinsurance/Salvage | Yes (Reserves tab) |
| **Transaction Sub Type** | Category of transaction | No |
| **Payee** | Recipient of payment | No |
| **Invoice Number** | Supplier or transaction invoice ID | No |
| **Reserve Allocation Amount** | Amount allocated to each coverage | Yes (at least one) |
| **Void Reason** | Explanation for voiding payment | Yes (if voiding) |

## Tips & gotchas

### Status workflow rules
- Status transitions follow a strict workflow — you cannot skip stages. For example, a New claim cannot jump directly to Closed; it must go through an intermediate assessment stage first.
- Only certain transitions are allowed from each status. The UI only shows buttons for valid next states.
- Attempting an invalid transition will show an error.

### Claim type and form variation
- The form dynamically changes based on the selected claim type. Registering a Motor Accident claim will show vehicle, driver, and third-party fields; a Glass claim will show damage location and quote sections.
- Some fields are conditional — e.g., on Motor Accident claims, "Recovery Name / Address" fields only appear if "PA Involved" is marked Yes.
- If you switch the claim type during creation, some fields will be cleared. Save early and frequently.

### Reserve and payment allocation
- You must pick a **Transaction Type** before the coverage allocation table appears. The system knows which types are "payments" vs. "reserves" (e.g., "Payment" is a payment; "Reserve" is an estimate).
- All coverages for the selected claim are shown in the allocation table. You cannot allocate to a coverage that is not on the policy.
- Allocation amounts must be numeric and greater than zero. The submit button stays disabled until at least one valid allocation is entered.
- **Credit Note** checkbox automatically negates the allocation amounts (for reversals without voiding).
- Voiding a payment creates a new reversal entry (marked with `isPaymentVoided=2`) and marks the original as voided (marked with `isPaymentVoided=1`).

### Attachments
- File types allowed: PDF, JPG, PNG, DOC, DOCX. Other formats are rejected by the file input.
- Some claim types require specific attachments (e.g., Glass claims typically need front/back/left/right damage photos; Life claims need death certificate).
- Attachments are stored and linked to the claim; deleted attachments cannot be recovered.

### Multi-location (DOM/COM) products
- For policies covering multiple risk addresses, the Coverage Allocation form groups coverages by address.
- You must select a risk address when creating a reserve for a multi-location policy.
- Some fields like "Location" are specific to DOM/COM claims and appear in the Classification & Allocation section.

### Edit and history
- When you edit an existing claim, a draft copy of the form opens in a modal.
- Not all fields are editable after creation — some are locked based on status (e.g., most Motor claim fields lock once status moves past Pending Assessment).
- Edit history is logged; you can view a timeline of all changes in the Activity Log tab.

### Field gating by product
- Motor products (MIS, Motor Trade) show Motor-specific fields (vehicle, driver, fault party, weather).
- DOM/COM and Specialist products show DOM/COM-specific fields (Service Rep, Classification, Attorney involvement, etc.).
- Some all-risk types appear on multiple products with slightly different form layouts (e.g., Glass appears on Motor and DOM/COM).

### Invoicing
- The **Invoice** tab aggregates both supplier invoices (from the Suppliers tab quotes) and reserve/payment transaction invoices.
- Invoice amounts are calculated from coverage-level allocations; an invoice number is tracked if provided at create time.

### Assessment and valuation
- The **Assessor** tab is separate from the Reserves tab. Assessment is used to estimate liability; Reserves/Payments record the actual amounts set aside or paid.
- Assessment can be entered independently of reserves — some claims may be assessed but not paid.

### DFS complaints
- If "DFS Complaint" is marked Yes during creation, the claim is flagged for special handling.
- DFS complaints are tracked in the Complaint Log tab and may trigger escalation workflows.

## Related modules

- [Policies](/help/policies) — view policy details linked from claims
- [Customers](/help/customers) — customer records and contact info referenced in claims
- [Claims](/help/claims) — claims that draw on these suppliers, repair centres and activation codes
- [Payments](/help/payments) — payment processing and reconciliation
- [Accounting](/help/accounting) — reserve and payment booking to general ledger
- [Reconciliation](/help/reconciliation) — match claim payments to bank transactions
- [Communications](/help/communications) — send notifications to customers about claim status
- [Audit Trail](/help/audit-trail) — view all changes and actions on claims
- [Reinsurance](/help/reinsurance) — recovery and reinsurance recovery tracking
