---
lastReviewed: 2026-06-03
---
## Overview

The Underwriting module is a dedicated work queue where underwriters review and decide on pending policies before they are finalized. You can see all policies awaiting approval, view their full risk details (sum insured, premium, coverage breakdowns, reinsurance allocations), and approve, reject, or refer them with notes.

## Who uses this

- **Underwriters** – the primary users who review and decide on policies.
- **Operations team members** – who may triage or monitor the queue.
- **Risk managers** – who may review referred policies.

## Step-by-step

### Viewing the underwriting queue

1. Navigate to **Underwriting** from the main sidebar.
2. You will see a table of all pending policies, showing:
   - Policy number (linked to the full policy detail page)
   - Customer name
   - Product name
   - Transaction type (displayed as a badge)
   - Premium amount
   - Days pending (color-coded: green 0–1 day, yellow 2–3 days, red >3 days)
   - Who submitted it
3. A summary at the top shows **total pending count** and how many are **over SLA (3 days)**.

### Searching and filtering

1. Use the search box at the top-right to filter by policy number or customer name.
2. The table updates as you type; press Enter or wait for auto-search to trigger.
3. Results are paginated at 25 per page.
4. Use **Previous** and **Next** buttons to navigate pages.

### Making an underwriting decision

1. Click the green **Decide** button on any policy row.
2. A modal opens showing:
   - Policy number, customer name, product, and premium
   - **Risk preview** (automatically loaded):
     - Total sum insured, total premium, and reinsurance allocation count
     - Any validation rule violations flagged (e.g., SI breach, missing fields)
     - Per-coverage-group breakdown (sum insured and premium by group)
     - Reinsurance treaty allocation table (percentage and premium per treaty)
   - A warning if no reinsurance allocation is recorded yet
3. Select one of three decisions:
   - **Approve** (green) – policy proceeds to binding
   - **Reject** (red) – policy is declined
   - **Refer** (yellow) – escalate for further review
4. (Optional) Add underwriting notes in the text area below.
5. Click **Confirm [decision]** to submit.
6. The modal closes, the queue refreshes, and the policy is removed from the pending list.

## Field reference

| Field | Meaning | Required |
|-------|---------|----------|
| Policy | Unique policy identifier, links to policy detail page | – |
| Customer | Name of the customer/policyholder | – |
| Product | Insurance product type (e.g., Motor, Home, Travel) | – |
| Type | Transaction type (e.g., New business, Endorsement, Renewal) | – |
| Premium | Annual/policy premium in Pula | – |
| Days Pending | Number of calendar days waiting for underwriting decision | – |
| Submitted By | User or role that initiated the submission | – |
| Decision | Choice of Approve, Reject, or Refer | Yes |
| Notes | Free-text underwriting comments (e.g., conditions, reasons) | No |

## Tips & gotchas

- **Risk preview is essential before deciding.** The preview shows whether validation rules (e.g., sum-insured limits, underwriting restrictions) have been breached. If violations appear, clarify with the submitter before approving. Do not approve policies that fail critical rules.
- **Reinsurance allocation matters.** If the "No reinsurance allocation recorded" warning appears, the policy may be bound 100% net-retained (not transferred to reinsurers). Click "Recalculate reinsurance" on the policy detail page *before* approving, or the risk exposure will not be shared as intended.
- **SLA is 3 days.** The summary and the Days Pending column highlight policies that exceed the 3-day approval window in red. Aim to clear these first.
- **Search is live but paginated.** If you search and get no results, the policy may be on another page once the search is cleared.
- **Notes are optional but recommended** for rejections and referrals so the submitter understands why and what to do next.
- **Once decided, the action is final** for this submission cycle. If you need to reverse a decision, you will need to contact an administrator or use an audit trail correction.
- **Premium amounts are formatted** in Pula (P). The preview shows detailed breakdowns by coverage group; the queue table shows the total.

## Related modules

- [Policies](/help/policies) — create, renew, and manage the full policy lifecycle; contains reinsurance recalculation workflows.
- [Policy Wordings](/help/policy-wordings) — legal templates and document generation that may be blocked if underwriting is pending.
- [Reports](/help/reports) — operational reporting that can filter on UW status and decision metrics.
- [Quotes](/help/quotes) — quote-to-policy conversion may require underwriting approval before binding.
- [Reinsurance](/help/reinsurance) — configure treaties and coverage groups so reinsurance allocation works correctly during UW.
