# FAC register — defects and enhancements ready for testing

Mail to the Reinsurance team inviting testing of the seven reported items.

**Note for Snehal.** Change the addressee if the defect list came from someone other than Tlamelo. Section 3 is the important part — do not drop it. Reporting seven items as done without stating what we could not test ourselves is how a UAT sign-off gets given on something nobody actually exercised.

**Subject:** FAC register — all 7 reported items addressed, ready for your testing

---

Tlamelo,

All seven items you reported are built. Automated tests cover each one — 35 tests, 178 assertions, all passing. One thing still needs your eyes rather than ours, and it is in section 3.

**1. What was done**

| # | Item you reported | Status | What changed |
|---|---|---|---|
| 1 | No split at point of entry | Done | Adding a placement now starts with a choice between a new placement and one already signed. The steps after it differ — a new placement generates a slip, a signed one asks for the signing date and the slip upload |
| 2 | Slip generation does not work | Done | A new placement generates its slip on save. Where no slip number is typed the register allocates one, so the case you hit — the third placement with no number and no slip — cannot recur |
| 3 | No upload of the signed slip | Done | Signed slips attach to any placement, not only legacy ones. Filing one promotes a draft to payable and sets the premium warranty date |
| 4 | No edit function | Done | Saved placements can be amended. The trail records the user, the time, each field changed and its before and after value, including the derived figures the change moves |
| 5 | Validation errors not completely identified | Done | Every rejected field is now listed by name with the reason. Where a field fails two rules both are shown |
| 6 | Slip numbering descending, format may conflict | Done | The register lists lowest number first. Allocated numbers continue your existing series and match its width — 2026-005 gives 2026-006, and 2026-0005 gives 2026-0006 |
| 7 | No placement stage visibility | Done | Every placement shows its stage — awaiting signature, awaiting premium, ready to settle, complete or cancelled — and the register can be filtered by it |

**2. How to test each one**

| # | Test | Expected |
|---|---|---|
| 1 | Add a placement. Take each of the two options in turn | You are asked to choose first, and the questions afterwards differ between the two |
| 2 | Choose a new placement. Save it without typing a slip number | A number is allocated, a slip is produced, and you can open and send it |
| 3 | Open any placement. Attach the signed slip with the date the reinsurer signed | The line becomes payable and a premium due date appears |
| 4 | Amend a saved placement. Change the risk % and the policy number | The trail names each field with its old and new value, who changed it and when |
| 5 | Save a placement with several fields left blank | Every failing field is named with its reason, not only the premium |
| 6 | Look at the register order, then allocate a new number | Lowest number first, and the new number continues your series in the same width |
| 7 | Check the stage column, then filter by stage | Stage shown on every line and the filter returns only that stage |

Before testing the send step in item 2, check the reinsurer has an email address on the Reinsurers screen. Sending needs one.

**3. One thing we cannot confirm ourselves**

| # | Point | Why we cannot confirm it | What we need |
|---|---|---|---|
| 1 | The slip format | Item 2 asked for the agreed format. We have built the slip to print the terms the signed slips carry, but nobody has approved the layout | Generate one slip, read it, and confirm the layout and wording. Mark up anything wrong and we will change it |

We also tested the screens by reading the code rather than by using them, because we have no running copy of the live system here. Items 1 and 5 are screen behaviour, so your testing is the first real test of both.

**4. One point to note on item 4**

A settled or cancelled placement cannot be edited, and a placement whose client premium is confirmed received cannot be put back to draft. Both are deliberate. Reversing a confirmed liability moves the payable, the month-end snapshot and the journal figure, so it belongs to the settlement desk rather than to an edit. Tell us if you need a different rule.

Regards,

Snehal Zunjarrao
Developer, Graphite v2
