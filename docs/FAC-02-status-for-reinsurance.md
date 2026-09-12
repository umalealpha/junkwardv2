# Status mail to Tlamelo — everything done and everything outstanding

Consolidates the three lists in play: his eight slip content errors (24 Aug), the five
outstanding R/I set-up items, and the three cession bifurcation conflicts.

**Subject:** Reinsurance module — what is done, what is left

---

Tlamelo,

Consolidated status across everything raised. Three things need you; the rest is either done or ours to finish.

**1. Slip content errors — all eight done**

| # | Item | Status |
|---|---|---|
| 1 | Logo | Done |
| 2 | Basis of Cover | Done — read from the policy, so the slip cannot contradict it |
| 3 | "17% of 17%" | Done — now "17% of 100% Cession" |
| 4 | Heading must say Automatic Facultative | Done |
| 5 | Quarterly payments and the quarterly amount | Done |
| 6 | Prepared By, and PPW removed | Done |
| 8 | Fire and Business Interruption schedule | Done, including Total Limits of Indemnity |

Your list had no point 7.

**2. R/I set-up items**

| Item | Status |
|---|---|
| Sum insured allocation on the reinsurance tab | Done |
| Auto FAC — who is participating | Done |
| FAC slip — who is participating and their proportions | Done |
| Print the slip | Done |
| Print the bordereaux | Done — cession bordereau, grouped by reinsurer |
| Auto FAC and FAC placement mandates | Two of eight conditions done |

The two done are the policy period over eighteen months, and the Choppies, Kamoso and Motovac carve-out to the inwards facultative restriction.

**3. Cession bifurcation — two of three conflicts closed**

| Conflict | Status |
|---|---|
| Class limits removed for Motor, Miscellaneous, Guarantee, Transportation | Closed — the Schedule A limits are back |
| At what level the Property sum insured is tested | Closed — tested at the risk address |
| The 40,000,000 surplus | **Open** |

**4. Still to build — ours, nothing needed from you**

Four faults we found ourselves while testing. They were not on your list.

- The slip prints "P" on a foreign-currency placement
- Deductible, Description of Risk and Territorial Scope cannot yet be typed in
- A slip can group two currencies without refusing
- Filing the signed slip does not record that the reinsurer has committed

The last one matters for your testing: until it is fixed, every placement shows "Nobody on this panel has signed", including ones you have signed slips for. It is correct against the data and it will look like a fault.

**5. Three things we need from you**

1. **The surplus.** You confirmed the 40,000,000 in writing, but neither signed slip establishes a surplus treaty and your own work paper questions the amount — four lines on the net retention of 3,000,000 is 12,000,000, not 40,000,000 on the gross first line. Please send the signed slip or endorsement, and confirm which figure applies.
2. **Where to capture MPL, co-insurance, and the engineering project and plant values.** The remaining six treaty conditions cannot be tested without them. Whether they belong on the FAC placement or upstream at underwriting is an underwriting decision.
3. **Testing.** Generate one slip on the server and confirm the layout including the logo — our test machine renders PDFs with a different engine, so it proves the wording but not the appearance. Enter a Fire and Allied Perils schedule and confirm the printed block. Send one bordereau to the broker and mark up the columns; no sample was ever supplied, so the layout is our best reading and the figures are the part that will not change.

**6. Two points for the team at roll-out**

- Enter actual cession rates, not rounded ones. The system now holds eight decimals.
- Enter the full reinsurance period, with the payment frequency captured separately. Our test slip read 01/07 to 30/09 where yours reads 01/01 to 31/12 — a payment quarter had been entered as the period.

Regards,

Snehal Zunjarrao
Developer, Graphite v2
