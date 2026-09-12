# Status mail to the manager — reinsurance, 27 August 2026

Covering mail for RI-14 (the progress record), sent as an attachment. A status share:
what is done, what is pending, and the dates. No resourcing ask — one developer, as
instructed.

**Note for Snehal.**

- Fill the manager's name.
- The plan is 53 working days of work against 54 available, with **one day of
  contingency inside each task** rather than a block of buffer at the end. The
  statement issues 12 November, one day before it is due. Section "The dates" says
  plainly that there is no reserve behind that — do not soften it.
- The two blockers are stated as facts affecting the date, not as asks. If the manager
  wants them chased, that is their call to make from the information.

**Subject:** Reinsurance — progress to 27 August, pending items and dates

---

[Manager name],

Attached is the reinsurance progress record to 27 August. Status below.

**Completed**

- 68 changes delivered on the 2026/27 treaty year across 24 working days.
- Both treaties configured and in force — General Quota Share and Motor Quota Share, 1 July 2026 to 30 June 2027 — with layers attached and the regulatory mapping seeded, commercial and domestic.
- The cession calculation reproduces both signed slips and has been agreed with Reinsurance across three rounds of review.
- FAC register built and handed to Reinsurance for testing on 20 August. All seven items they reported are closed, along with the eight slip content errors raised on 24 August.

**Pending — in my hands**

- Three faults in the FAC slip: filing a signed slip does not record the reinsurer's commitment; three slip terms have no field behind them; and the slip prints every figure as Pula whatever currency the placement is in.
- The cession still routes by reinsurance group rather than by the regulatory mapping. The new calculator is built and tested but nothing calls it yet.
- Contractors All Risks, Plant All Risks and Machinery Breakdown sit in no reinsurance group, so they will not route.
- Passenger Liability has no coverage and no group.

**Pending — not started**

- The quarterly statement of account. 20 to 25 days on its own, and the only piece with a contractual deadline.
- Treaty limits and underwriting controls, the commission engine, and the IFRS 17 outputs. These sequence against 14 August 2027 and are not on the November path.

**The dates**

The first statement is due 45 days after the quarter closing 30 September. 14 November is a Saturday, so it must be issued **Friday 13 November 2026** — 54 working days from 1 September, before public holidays.

| Window | Work | Days |
|---|---|---|
| 1 – 14 Sep | Close the three slip faults. Point the cession at the regulatory mapping. Create the missing Engineering groups and Passenger Liability. Reconcile COMG2026213751 against Reinsurance's working. | 10 |
| 15 – 22 Sep | Reinsurance testing of the slip, the schedule and the bordereau, and the fixes arising. | 6 |
| 23 Sep – 28 Oct | Build the statement of account — the accounting item types, reserve deposit ledger, brokerage, VAT, cash-loss register, and the 45-day and 14-day clocks. | 26 |
| 29 Oct – 5 Nov | Dry-run the September quarter statement and reconcile it against the cessions. | 6 |
| 6 – 12 Nov | Remediate and issue. Due 13 November, so it lands one day early. | 5 |

53 working days of work against 54 available. Each task carries one day of contingency inside it rather than a block of spare days at the end, so a window that runs to its estimate absorbs its own slip and the next one still starts on time. A window that overruns by more than a day moves every date after it, and there is no reserve at the end to recover from.

**Two things outside my control that sit on this date**

1. **The full signing schedule.** Neither treaty placement reaches 100% today, and the system refuses a reinsurer split that does not total 100%. No statement of account can be issued until it arrives. With J.B. Boda and Reinsurance.
2. **The surplus slip.** The 40,000,000 surplus is configured on written confirmation with no signed slip behind it, and Reinsurance's own work paper questions the figure — four lines on the 3,000,000 net retention is 12,000,000, not 40,000,000. Every Property and Engineering figure moves if it is wrong.

Regards,

Snehal Zunjarrao
Developer, Graphite v2
