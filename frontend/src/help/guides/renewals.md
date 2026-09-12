---
lastReviewed: 2026-06-03
---
## Overview
The Renewals module tracks and manages insurance policy renewals across two product families: MIS (Motor Insurance Scheme, auto-debit) and DomCom/Specialist (report-only). It shows a real-time pipeline of policies due for renewal within the next 60 days and provides tools for rating new premiums, collecting customer consent, and either auto-renewing or deactivating policies.

## Who uses this
- **Renewal Managers / CSRs**: Use the Renewal Dashboard to monitor the renewal pipeline, categorize policies by urgency, and send payment links to customers.
- **Finance/Admin**: Use the Renewals list page to review rerated and renewed policies for reporting and reconciliation.
- **Agents**: May receive payment/consent links via WhatsApp or phone for distribution to customers.

## Step-by-step

### Renewal Dashboard Workflow (MIS auto-debit policies)

1. **Navigate to the Renewal Dashboard**
   - Access the Renewals module from the main menu.
   - You see a summary of all renewal statuses at a glance (Due 7 Days, Due 15 Days, etc.).

2. **Filter and review your renewal pool**
   - Click a summary card (e.g., "Due 7 Days") to focus on high-urgency renewals, or use the filter buttons below ("All Due (60 days)", "Due 7 Days", "Overdue", "Pending Consent", "Renewed").
   - Use the **Section tabs** to separate MIS from DomCom/Specialist policies. Only MIS allows auto-debit actions.
   - Search by policy number or customer name in the search box.

3. **Review rated policies**
   - The table shows all policies in the current filter with their current premium, new premium (if rated), and rate change percentage.
   - Rows with customers who "Needs Consent" are highlighted in purple; overdue rows are in red.
   - Status column shows: "Pending" (not yet rated) → "Rated" (ready for consent) → "Needs Consent" → "Renewed".

4. **Send payment/consent link (for MIS policies with Needs Consent status)**
   - Locate the policy in the table.
   - Click the **Send Link** button in the Actions column.
   - A prompt asks you to confirm the policy number. Click OK.
   - A payment/consent URL is generated and copied to your clipboard. Use this to send to the customer via WhatsApp or phone.
   - The customer completes payment/consent through the link; the policy transitions to "Renewed".

5. **Auto-renew eligible policies**
   - Only MIS policies with status "Rated" (not yet renewed) and where the new premium is ≤ the current premium are eligible.
   - Click the **Renew** button in the Actions column.
   - Confirm in the prompt. The policy is immediately renewed.
   - Status changes to "Renewed".

6. **Deactivate a policy**
   - If a renewal payment fails or you need to stop a policy, click the **Deactivate** button in the Actions column.
   - A prompt asks for a reason (e.g., "Renewal payment failed", "Customer opted out").
   - The policy is deactivated and removed from the active pipeline.

7. **Export for reporting**
   - Click **Export to Excel** in the top-right corner.
   - An .xlsx file downloads with the current filter, search, and section applied.
   - File name is `renewals-<filter>-<date>.xlsx` (e.g., `renewals-due_7-2026-06-03.xlsx`).

8. **Paginate through results**
   - Use **Previous** and **Next** buttons at the bottom to navigate pages (25 policies per page).

### Renewals List Page Workflow

1. **Navigate to the Renewals page** (alternative view, simpler list)
   - Access from Policies or a separate Renewals link.

2. **Search and filter**
   - Use the **Search** box to find by policy number, customer name, or phone.
   - Use the **Renewed** dropdown to show "All", "Renewed", or "Not Renewed" records.

3. **Review renewal records**
   - The table shows policyId, customer contact, old/new premium, expiry date, rerated status, renewed status, and creation date.
   - "Rerated" column shows whether the policy was re-evaluated for the new premium.
   - "Renewed" column shows final status (Yes/No).

4. **Link to policy detail**
   - Click a policy number to navigate to the policy detail page for that record.

5. **Paginate**
   - Use numbered page buttons or the "Go to" jump box to navigate; shows 25 records per page.

## Field reference

| Field | Meaning | Required | Notes |
|-------|---------|----------|-------|
| **Policy #** | Unique policy identifier number | Yes | Links to the full policy detail page |
| **Policy Number** | Human-readable policy code (e.g., "POL-2024-00123") | Yes | Displayed in the table and used in searches |
| **Customer Name** | Customer/insured's full name | Yes | Required for contact tracing; may be truncated in the list |
| **Customer Phone** | Contact phone number | No | Displayed for customer contact, required for WhatsApp links |
| **Customer Email** | Email address | No | For records; not used for renewal links (WhatsApp/phone only) |
| **Agent Name** | Assigned sales or account manager | No | Shown in Dashboard for attribution |
| **Product Name** | Insurance product (e.g., "Vehicle Insurance", "Building & Contents") | Yes | Defines the renewal rules |
| **Premium Frequency** | Billing interval (Annual, Semi-Annual, Quarterly, Other) | Yes | Shown as a badge (Annual blue, Semi-Annual violet, Quarterly green) |
| **Current Premium** | Active premium amount | Yes | Formatted as "P X,XXX.XX" |
| **New Premium** | Rated premium for renewal | No | Null if policy not yet rated; shows rate change % |
| **Expiry Date** | Policy anniversary / renewal date | Yes | Format: YYYY-MM-DD; days to expiry is calculated |
| **Days To Expiry** | Calculated days until renewal | Yes | Negative means overdue; used for urgency badges |
| **Rerated** | Whether premium was recalculated | Yes | Boolean; Yes/No badge in Renewals list |
| **Is Renewed** | Whether policy has been renewed | Yes | Boolean; final status badge |
| **Section** | Product family category | Yes | "MIS" (Motor, auto-debit) or "DomCom" (report-only) |
| **Rate Change %** | Premium increase/decrease | No | Shown in green (decrease) or red (increase); helps identify cost impacts |
| **Needs Consent** | Customer approval required (before auto-debit) | Yes | Boolean; triggers "Send Link" button |

## Tips & gotchas

- **MIS vs DomCom distinction**: Only MIS policies show action buttons (Send Link, Renew, Deactivate). DomCom/Specialist policies show "report-only" and are for monitoring only—auto-debit rules do not apply.

- **Auto-renew eligibility**: A policy qualifies for "Renew" only if ALL of these are true:
  - It is MIS (auto-debit product)
  - Status is "Rated" (premium calculated)
  - Status is NOT already "Renewed"
  - New premium ≤ current premium (no price increase)
  - Both newPremium and oldPremium are non-null
  - If any condition fails, the Renew button is hidden.

- **Urgency badges**: Policies are color-coded by days to expiry:
  - Red: ≤ 7 days or overdue (negative days shown as "OVERDUE Nd")
  - Orange: 8–15 days
  - Yellow: 16–30 days
  - Gray: 31+ days

- **Payment links are not Sandbox-safe**: The "Send Link" button generates a real payment/consent URL. Test carefully in a staging environment or with test customer records if available.

- **Deactivate requires a reason**: The prompt is mandatory; you cannot skip it. A reason such as "Renewal payment failed" is logged for audit purposes.

- **Export includes current context**: The Excel export applies your active filter, section, and search. If you filter to "Due 7 Days" + "MIS" + "John", the export will only contain those 25 policies (or fewer).

- **Search is not real-time**: In the Renewals list page, search is applied on blur (click away) or pressing Enter, not as you type. In the Dashboard, search updates immediately as you type.

- **Pagination resets on filter change**: Switching filters, sections, or search queries resets you to page 1 automatically.

- **Rates of change not shown for DomCom**: DomCom policies in the Dashboard show "—" for new premium and rate change because they are not auto-rated or auto-renewed.

- **Customer contact in list is summary only**: The Dashboard shows abbreviated customer info (name, phone, email snippet). For full customer details, click the policy number to view the full policy record.

- **Rerated vs Renewed are independent**: A policy can be "Rated" but not yet "Renewed". Only after the customer sends payment/consent or you auto-renew does it transition to "Renewed". "Rerated" in the Renewals list indicates whether the premium was recalculated, which is separate from renewal status.

## Related modules

- [Policies](/help/policies) — View full policy records, coverage, and history; link to individual renewals.
- [Payments](/help/payments) — Track payment receipts from renewal payment links and auto-debit transactions.
- [Quotes](/help/quotes) — Create new quotes; premium rerate logic may overlap with renewal rating.
- [Communications](/help/communications) — Log and track customer notifications and payment link shares.
- [Accounting](/help/accounting) — Post renewal premium receipts to ledger; reconcile with renewals pipeline.
