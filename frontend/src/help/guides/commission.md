---
lastReviewed: 2026-06-03
---
## Overview

The Commission module lets you manage agent earnings on policies. You define rules that calculate commissions (by percentage or fixed amount), run calculations against recent policies, approve pending payments, track fraud alerts, and set bonus targets for agents.

## Who uses this

Commission analysts, underwriting supervisors, and finance staff who need to:
- Set up and adjust commission rules for products and agents
- Execute monthly commission calculations 
- Review and approve commission ledger entries
- Investigate fraud alerts triggered by unusual agent activity
- Configure bonus targets and incentive campaigns

## Step-by-step

### View the Dashboard

1. Navigate to **Commission > Dashboard**.
2. Review the **Stat Cards** at the top:
   - Pending: commissions awaiting approval
   - Approved: approved but not yet paid
   - Paid This Month: amounts paid in the current month
   - Clawed Back: amounts reversed due to policy cancellation
   - Fraud Alerts: open fraud flags for review
3. View the **Top 5 Agents** table showing commission earners and policy counts.

### Run a Commission Calculation

1. On the Dashboard, scroll to **Run Commission Calculation**.
2. Set parameters:
   - **Days**: how many days back to scan for policies (1–365; default 30)
   - **Limit**: max policies to process (1–500; default 50)
   - **Dry Run**: checkbox. When checked, shows what *would* be calculated without saving. When unchecked, applies the calculation and creates ledger entries.
3. Click **Test Rules** (dry run) or **Run & Apply** (live).
4. Review the results table:
   - **Policies Scanned**: total count
   - **Rules Matched**: count with a matching rule
   - **No Match**: policies without a qualifying rule
   - **Total Commission**: sum of earned amount
   - Each row shows policy, product, premium, matched rule, commission amount, and any validation failures.
5. If satisfied, run again with **Dry Run** unchecked to apply.

### Create or Edit a Commission Rule

1. Go to **Commission > Rules**.
2. Click **+ Add Rule** (or **Edit** on an existing rule).
3. Fill in the modal:
   - **Rule Name** (required): e.g. "Motor Comprehensive — Standard Agent Commission"
   - **Product**: select a product or leave blank for all
   - **Type**: Percentage (%) or Fixed Amount (P)
   - **Value** (required): the commission amount
   - **Priority**: higher priority rules are checked first (default 1)
   - **Status**: Active or Inactive
4. Expand **Qualification Conditions** to add gates:
   - **Checkboxes**: KYC Compliant, Preinspection Done, Renewals Only, New Business Only
   - **Min Active Months**: customer must have been active X months
   - **Min Successful Payments**: policy must have X successful payments
   - **Max Failed Payments**: allow up to X payment failures (0 = none allowed)
   - **Cooling Period (days)**: hold commission X days before paying
   - **Clawback Period (days)**: reverse commission if policy cancels within X days
   - **Min Premium (P)**: only apply if premium meets threshold
   - **Campaign Dates**: Valid From / Valid To (for time-limited rules)
5. Click **Create Rule** or **Update Rule**.

### Manage Rules

1. On **Commission > Rules**, view the table of all rules (filtered by product/status if needed).
2. **Edit**: click the **Edit** button to modify.
3. **Copy**: click **Copy** to duplicate a rule as a template.
4. **Deactivate**: click **Del** to mark as Inactive (reversible; no data is lost).

### Review and Approve Commission Ledger

1. Go to **Commission > Ledger**.
2. Review the summary strip: Total Earned, Total Clawback, Total Pending, Total Paid.
3. Filter by:
   - Agent (search box; 400ms debounce)
   - Status: Pending, Approved, Paid, Held, Cancelled
   - Entry Type: Earned, Clawback, Bonus, Adjustment
   - Date range (From / To)
4. Approve individual entries:
   - Find a Pending entry and click **Approve** to move it to Approved.
5. Bulk approve:
   - Check the boxes for entries you want to approve.
   - The **Approve Selected (N)** button appears in the header.
   - Click to approve all selected at once.
6. View entry details in the table: policy, agent, amount, premium, commission type, status, qualifying date, cooling end date, KYC/payments qualifications.

### Investigate Fraud Alerts

1. Go to **Commission > Fraud Alerts**.
2. Review the table of alerts filtered by:
   - Status: Open, Reviewing, Resolved, Dismissed
   - Severity: Low, Medium, High, Critical
   - Alert Type: Rapid Policy Creation, Self Referral, Unusual Cancellation, Duplicate Customer, Premium Manipulation, Ghost Policy, Churning
   - Agent (search box; 400ms debounce)
3. Click **Review** on any alert to open the review modal.
4. In the modal, see:
   - Agent name, policy number, alert type, severity, and date
   - Detailed description and optional JSON details
   - A **Status** dropdown to change from Open → Reviewing → Resolved → Dismissed
   - A **Resolution Note** field for notes
5. Click **Save Review** to record the status change and notes.

### Manage Commission Targets

1. Go to **Commission > Targets**.
2. Click **+ Add Target** to create a bonus incentive.
3. In the modal, fill in:
   - **Name**: e.g. "Q1 Motor Sales Bonus"
   - **Target Type**: Policy Count or Premium Amount
   - **Target Value**: the threshold (e.g. 50 policies or P100,000 premium)
   - **Period Type**: Weekly, Monthly, Quarterly, Yearly, or Custom
   - **Period Days**: if Custom, how many days the period spans
   - **Bonus Type**: Fixed Amount (P) or Percentage (%)
   - **Bonus Value**: the payout amount
   - **Product** (optional): limit to a product
   - **Agent** (optional): target a specific agent (search by name)
   - **Agency** (optional): target an agency
   - **Effective From / To**: date range for the campaign
   - **Status**: Active or Inactive
4. Click **Create Target** or **Update Target**.
5. View all targets in the table; click **Edit** or **Delete** to manage.

## Field reference

| Field | Meaning | Required | Notes |
|-------|---------|----------|-------|
| Rule Name | Unique identifier for the rule | Yes | Searchable in dropdown menus |
| Product | Product that triggers the rule | No | Blank = all products |
| Commission Type | Percentage (%) or fixed (P) | Yes | Determines how Value is interpreted |
| Commission Value | The commission amount or rate | Yes | Decimal; e.g. 10 or 500.50 |
| Priority | Rule evaluation order | No | Higher = checked first; default 1 |
| Status | Active or Inactive | No | Inactive rules are never matched |
| KYC Required | Policy must pass KYC screening | No | Boolean; unchecked = not required |
| Preinspection Required | Property/vehicle must be inspected | No | Boolean; unchecked = not required |
| Min Active Months | Months customer must have been active | No | Blank = no minimum |
| Min Successful Payments | Payments the policy must have made | No | Blank = no minimum |
| Max Failed Payments | Payment failures allowed | No | 0 = zero failures allowed; blank = no limit |
| Cooling Period (days) | Days to hold commission before paying | No | Blank = no hold |
| Clawback Period (days) | Days window to reverse if policy cancelled | No | Blank = no clawback window |
| Min Premium | Minimum policy premium (P) | No | Blank = no minimum |
| Valid From | Campaign start date | No | Blank = always valid |
| Valid To | Campaign end date | No | Blank = no end date |
| Target Name | Label for the incentive | Yes | e.g. "Q1 Motor Bonus" |
| Target Type | Policy Count or Premium Amount | Yes | Determines what agents measure toward |
| Target Value | Threshold to reach the bonus | Yes | Policies or currency amount |
| Period Type | Weekly, Monthly, Quarterly, Yearly, Custom | Yes | Reset cycle for tracking |
| Period Days | Custom period length | Conditional | Required only if Period Type = Custom |
| Bonus Type | Fixed Amount (P) or Percentage (%) | Yes | Type of payout |
| Bonus Value | Bonus amount or rate | Yes | Decimal |
| Agent | Specific agent | No | Blank = all agents. Uses typeahead search |
| Agency | Specific agency | No | Blank = all agencies |
| Effective From | Start date for target | No | Past dates OK; used to filter active targets |
| Effective To | End date for target | No | Blank = ongoing |
| Days (Run Calculation) | Lookback window in days | No | Default 30 |
| Limit (Run Calculation) | Max policies to scan | No | Default 50 |
| Dry Run | Simulate without saving | No | Checked = no ledger entries created |

## Tips & gotchas

- **Rule priority is real.** Rules are checked in descending priority order. If two rules match, only the highest-priority one is applied. If you want multiple rules to apply to the same policy, you must handle it manually via the **Adjustment** entry type in the ledger.

- **All conditions are AND, not OR.** If a rule lists "KYC Required" and "Min 2 Active Months," the policy must pass both. Leaving a condition blank means "don't check it."

- **Cooling and clawback windows are separate.** A cooling period delays payout; a clawback period reverses commission if the policy is cancelled. Both can apply to the same rule.

- **Dry run doesn't create ledger entries.** The results table shows "Would Create" status for a dry run. You must run again with **Dry Run** unchecked to save them.

- **Commission is calculated once per policy per rule.** If you run the calculation twice on the same policies without clearing, you'll see the same policies in the results again. Check the **Status** column: "Created" means it was just added, "Would Create" means it already exists.

- **Fraud alerts are auto-generated.** You cannot create them manually; the system detects patterns like rapid policy creation or self-referral. You can only change their status (Open → Reviewing → Resolved → Dismissed) and add notes.

- **Bulk ledger approval only selects pending entries.** The checkboxes only appear on Pending entries. Approved, Paid, Held, or Cancelled entries cannot be bulk-selected.

- **Agent search is debounced (400ms).** After typing in agent/fraud-alert filters, wait briefly for results to load.

- **Inactive rules are ignored in calculations.** Toggling a rule to Inactive retroactively does not reverse already-earned commissions; it only prevents new matches. Use a Clawback entry type or manual adjustment if reversal is needed.

- **Target bonus calculation happens separately.** Commission targets are *not* enforced during the rule-based calculation run. Bonuses must be manually recorded as Bonus entry type in the ledger or through a separate bonus-payout process.

- **Campaign date logic is inclusive.** Valid From and Valid To on rules are inclusive boundaries (e.g. "2024-01-01" to "2024-03-31" includes both dates). Leaving both blank means the rule is always valid.

- **Ledger entry statuses flow one direction.** Pending → Approved → Paid is the normal flow. Other transitions (Held, Cancelled) are manual interventions and should be used sparingly.

## Related modules

- [Policies](/help/policies) — view and manage issued policies
- [Agents](/help/agents) — agent profiles and settings
- [Products](/help/products) — product catalog and underwriting rules
- [Renewals](/help/renewals) — renewal processing (uses commission rules)
- [Payments](/help/payments) — track agent payments and reconciliation
- [Reconciliation](/help/reconciliation) — match ledger entries to bank deposits
- [Accounting](/help/accounting) — general ledger integration
- [Audit Trail](/help/audit-trail) — audit all commission changes
