---
lastReviewed: 2026-08-10
---
## Overview

The Claims module is Alpha Direct's rebuild of the legacy **Claims Tracker** inside Graphite — same screens, same workflow, now backed by Graphite's data and design system. You record a reported loss the moment it comes in (even before documents or a valid policy are confirmed), chase the outstanding items, walk the claim through a 7-stage assessment-to-repair timeline with SLA tracking, and finally **Convert** it into a fully registered Graphite claim with an official claim number.

The module keeps the tracker's familiar top chrome: an action bar (**Refresh**, **Master Data**, **Print**, **+ New Claim**), a horizontal tab strip (**Dashboard · All Claims · Incentive Report · Analytics · How It Works · Policy Library**, plus an **Admin ▾** menu for admins), and a red/amber **overdue banner** that appears whenever claims have stages past their SLA due date.

## Who uses this

- **Claims handlers** — record new claims (FNOL), fill in stage dates, chase outstanding documents, convert to registered claims
- **Claims managers / Head of Operations** — monitor the dashboard, SLA breaches and handler load; take approve/repudiate decisions
- **Assessors & panel beaters** — their names/values are captured on the stage timeline (managed as Master Data)
- **Finance** — reserve and paid amounts, FAC/reinsurer tracking
- **Admins / Superadmins** — Master Data, notifications, API access, backdate control, and decision reversals

## The claims screens

Use the tab strip at the top of every claims page to move between:

- **📊 Dashboard** — the live command centre. Ten KPI cards (Total Claims, In Progress, Overdue Stages, Delayed, Completed, Avg Days Late, Total Reserve, Total Paid, Major Claims, FAC Claims), a **🔗 Graphite Sync Status** strip (Synced / Pending / Sync Failed), an **⚠ SLA Breach Alerts** panel, a Claim Type summary, the Claims Pipeline (Motor / Glass / Non-Motor tabs), an Overdue & Delayed list, and a Handler Performance leaderboard. Most cards are clickable and drill into the filtered All Claims list.
- **📋 All Claims** — the searchable, filterable claim list. This is the default `/claims` tab.
- **🏆 Incentive Report** — the handler incentive scoreboard split into panel-beater and glass categories (a 1:1 rebuild of the tracker's Handler Incentive Report).
- **📈 Analytics** — seven charts: Claims by Status, Pipeline Stage Distribution, Channel Split, Claims by Type, Monthly Volume, On Time vs Delayed, and Top Handlers by claim load.
- **📖 How It Works** — this guide.
- **📚 Policy Library** — policy wording documents (opens the Policy Wordings screen).

## Step-by-step

### Flow 1: Record a new claim (FNOL intake form)

Click **+ New Claim** in the top-right of the claims chrome. When FNOL intake is enabled this opens the unified **tracker-style New Claim form** — a First Notification of Loss (FNOL). It captures everything the old tracker's "New Claim" modal did: Basic Information, a claim-type cascade, a Comment Status, and the 7 numbered stage accordions.

1. **Channel + Broker** — choose **Broker** (default) or **Direct**. When Broker is selected, a **Broker Name** dropdown appears; pick a broker or choose *Others (Specify Below)* to type one not yet on the list. (The broker list comes from Master Data → Approved Brokers.)
2. **Client Name** *(required)* — the claimant's full name. If the name matches Alpha Direct's **FAC Clients** list, a navy **📋 FAC (FACULTATIVE) CLAIM** tag appears and a **Reinsurer** dropdown becomes available.
3. **Contact Phone** — used for SMS status updates to the claimant.
4. **Policy Number** *(required)* — type the policy number and click **Look up** to resolve it to a Graphite policy (confirms the product and links the claim). The FNOL can still be recorded if the policy isn't found — but a valid policy is required later to convert.
5. **Claim Number** — read-only; it is **auto-assigned by Graphite** on conversion, so it shows "Pending — auto-assigned by Graphite".
6. **Claims Handler** — pick a handler, or **Other** to type a name.
7. **Reported Date** *(required)* — defaults to today and **cannot be in the future**.
8. **Claim Type** *(required)* — one of **Motor Claim · Non-Motor Claim · Glass · Lock & Key**. This drives which extra fields appear:
   - **Motor / Non-Motor** reveal a **Customer Type** dropdown (COM / DOM / MIS / Others).
   - **Non-Motor** additionally reveals a **Non-Motor Claim Type** sub-type (All Risk, Legal, Hospital Cash Back, Fire, WCA, GIT, and more) and a **Non-Motor Assessor**.
   - **Glass / Lock & Key** reveal a **Glass Supplier** dropdown.
9. **Plate Number**, **Reserve Amount**, **Claim Paid Amount** — reserve above **P300,000** auto-shows a **🚨 MAJOR CLAIM** banner flagging it for senior review.
10. **Claim Description** *(required)* — the incident narrative; Graphite uses it on the official claim record.
11. **Comments → Comment Status** — an optional workflow status (Awaiting, Outstanding Premiums, Repudiated, Claim Withdrawn, Claim Closed, Management Review, System Issue, Recovery & Legal, File with Accounts, Claim Below Excess). Choosing **Awaiting** reveals an "Awaiting — what specifically?" sub-reason (Claim Documents, Invoices, Assessment Report, etc.).
12. **Stage accordions** — the 7 numbered stages (see Flow 2), all optional at intake.
13. Click **Save Claim**. The FNOL is recorded with its own **FNOL number** and you land on its detail page. Only **Client Name, Reported Date and Description** are hard-required; everything else can be filled in later.

### Flow 2: The 7 stages (assessment-to-repair timeline)

The New Claim form and the claim's **SLA / Stage Timeline** tab both use the same 7 numbered stages the tracker used. Each stage is a collapsible accordion of dates, dropdowns and a comment:

1. **Assessor Allotment & File Upload** — claim docs received (this date starts the SLA clock), assessor allotment date, assessor name, file uploaded to GT, GT number, comment.
2. **Physical Assessment** — distance (`<50Km` / `>50Km`), panel beater name (or *Other*), physical assessment date, comment.
3. **Quote Request & Finalisation** — quote request date, under warranty (Yes/No), quote finalisation date, comment.
4. **Assessment Report** — assessment report date, comment.
5. **PO Generation & Issue** — PO generation date, PO issue type(s) — a checkbox set of *Repair Order, Parts Supplier PO, Contract Pricing PO, CIL, Others* (ticking **Contract Pricing PO** or **CIL** reveals a value field; **Others** reveals a free-text), and PO issue date.
6. **Parts Delivery & Confirmation** — parts ETA, parts delivery date, confirmation date, mismatch reported (No/Yes), replacement date.
7. **Job Completion** — job end date, job end status (Complete / Incomplete).

**Glass and Lock & Key claims** use only stages **3, 5 and 7** — the other accordions are hidden automatically once you pick that claim type.

Any stage data typed on the New Claim form is parked on the FNOL and copied onto the claim's stage timeline when you convert. After conversion, handlers keep editing these dates on the claim's **Stage Timeline** tab.

### Flow 3: Convert an FNOL into a registered claim

An FNOL is intake only — it becomes a real, numbered claim when you **Convert** it.

1. Open the FNOL from the FNOL list (or straight after saving it).
2. While its status is **Open** you can **Edit** it, **Close** it, or **Convert to claim**.
3. Click **Convert to claim** and confirm. Graphite creates the full claim, applies the parked stage timeline, and returns the **official claim number** — you're taken to the new claim's detail page.
4. The FNOL's status flips to **Converted** with a green "View claim" link back to it.

If the FNOL has **no valid policy or no loss date**, convert is refused with a message — link a resolvable policy (via the Look up step) and set the reported/loss date first, then convert.

### Flow 4: Track, update & watch SLAs

1. Use **📋 All Claims** to search and filter. Open any claim to see its tabs, including the **SLA / Stage Timeline** tab.
2. The SLA panel shows the claim's SLA class, start date, total working days, overall due date, and a per-stage table with **Due date**, **Completed date**, a **Status** chip and a working-day **variance** (e.g. `+3d late`, `2d early`).
3. Handlers with edit rights update stage dates inline and **Save changes**; SLA calculations re-run on every save. Saving any field creates the timeline if one doesn't exist yet.
4. Backdating stage dates is blocked by default — admins can issue a **time-limited backdate grant**, or a handler can request one from the claim via **Request Backdate**.
5. The red/amber **overdue banner** at the top of the claims chrome counts claims with overdue stages; it turns from amber to red once more than three claims are overdue. Dismiss it and it re-appears when the count changes.

### Flow 5: Decisions (approve / repudiate)

Every claim eventually needs a decision, recorded with a permanent audit trail:

- **Approve** or **Repudiate** — repudiation **requires a note** explaining why. The decision date is server-stamped, so it can't be backdated.
- **Reverse a decision** — Admin/Superadmin only. The original decision is preserved in history; the reversal is itself a logged event, after which the claim can be re-decided.

## Field reference

| Field | What it means | Required |
|-------|---------------|----------|
| **Channel** | Broker or Direct; Broker reveals the Broker Name dropdown | No (defaults to Broker) |
| **Broker Name** | Approved broker, or *Others (Specify Below)* to type one | When Channel = Broker |
| **Client Name** | The claimant; a match on the FAC Clients list auto-flags the claim as Facultative | Yes |
| **Contact Phone** | Claimant number for SMS status updates | No |
| **Reinsurer** | Facultative reinsurer; only shown on FAC-flagged claims | FAC only |
| **Policy Number** | The policy this claim belongs to; **Look up** resolves it to a Graphite policy | Yes (to convert) |
| **Claim Number** | Official number, **auto-assigned by Graphite** on conversion | Read-only |
| **Claims Handler** | Owning handler; *Other* allows a typed name | No |
| **Reported Date** | When the loss was reported; cannot be in the future | Yes |
| **Claim Type** | Motor / Non-Motor / Glass / Lock & Key — drives the field cascade | Yes |
| **Customer Type** | COM / DOM / MIS / Others | Motor & Non-Motor |
| **Non-Motor Claim Type** | Sub-type (All Risk, Legal, Hospital Cash Back, Fire, GIT, …) | Non-Motor |
| **Non-Motor Assessor** | Assessor for a non-motor loss; *Other* allows a typed name | Non-Motor |
| **Glass Supplier** | Approved glass supplier; *Other* allows a typed name | Glass / Lock & Key |
| **Plate Number** | Vehicle registration | No |
| **Reserve Amount (P)** | Amount set aside; above **P300,000** flags a Major Claim | No |
| **Claim Paid Amount (P)** | Amount settled | No |
| **Claim Description** | Incident narrative used on the official record | Yes |
| **Comment Status** | Workflow status; *Awaiting* reveals a sub-reason | No |
| **Estimate / Outstanding Documents** | On the FNOL detail: estimate value and a checklist of docs still owed | No |

## Statuses & colour meaning

**FNOL status** (intake record):

| Status | Meaning | Colour |
|--------|---------|--------|
| **Open** | Recorded, still being worked / documents outstanding | Accent (blue) |
| **Converting…** | Conversion in progress | Amber |
| **Converted** | Turned into a registered claim (green "View claim" link shows) | Green |
| **Closed** | Ended without a claim (e.g. duplicate, withdrawn) | Grey |

**SLA stage status** (per stage on the timeline):

| Status | Meaning | Colour |
|--------|---------|--------|
| **On track** | Not yet due | Green |
| **Met** | Completed on or before its due date | Green |
| **Due soon** | Approaching its due date | Amber |
| **Missed** | Completed after its due date | Red |
| **Breached** | Past due and not completed | Red |

**Dashboard row/badge cues** carried over from the tracker: **🚨 MAJOR** (reserve > P300,000), **📋 FAC** (facultative client), decision **✓ APPROVED / ✗ REPUDIATED**, and Graphite sync **🔗 Synced / ⏳ Pending / 🚨 Sync Failed**.

## Tips & gotchas

- **Only three fields are truly required** to record a claim — Client Name, Reported Date, Description. Record the loss first, chase the rest later; that's the whole point of FNOL intake.
- **A valid, resolved policy and a loss date are required to convert** — not to record. If Convert refuses, run the policy **Look up** and set the reported date, then retry.
- **Reported Date can't be in the future**, and stage dates can't be backdated without an admin backdate grant — this protects SLA integrity.
- **Claim type changes the form.** Glass / Lock & Key deliberately hide all stages except 3, 5 and 7; Non-Motor adds the sub-type + assessor; Motor/Non-Motor add Customer Type.
- **FAC and Major flags are automatic** — FAC on a client-name match against Master Data, Major when reserve exceeds P300,000. You don't set them manually.
- **The claim number is Graphite's to assign** — it stays "Pending" until conversion, so don't expect to type it.
- **Stage timeline saves are incremental** — saving any field creates the timeline; every save re-runs the SLA maths and updates the dashboard's overdue count.
- **The overdue banner escalates** from amber to red past three overdue claims, and re-shows itself after dismissal when the count changes.
- **Master Data drives the dropdowns** — brokers, handlers, assessors, panel beaters, glass suppliers, reinsurers and FAC clients all come from there; use *Other* / *Others (Specify Below)* only for one-offs not yet on a list.

## Related modules

- [Policies](/help/policies) — where the policy this claim attaches to is defined; the policy Look up links here
- [Claims Support](/help/claims-support) — supplier networks, repair centres and activation codes used on claims
- [Customers](/help/customers) — the claimant / insured contact record
- [Reinsurance](/help/reinsurance) — facultative reinsurers surfaced on FAC-flagged claims
- [Underwriting](/help/underwriting) — the upstream policy approval workflow
