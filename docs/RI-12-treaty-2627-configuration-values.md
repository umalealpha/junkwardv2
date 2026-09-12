# Treaty 2026/27 — configuration values to enter

Everything needed to configure the reinsurance year manually. Values taken from
`RegulatoryCessionCalculator` as committed, so what you configure matches what has
been tested.

Sources: Alpha Direct Capacities Table 2026/27 (built solely from the two signed
slips), the regulatory mapping annexed 17 August, and Tlamelo's confirmations of
24 and 25 August 2026.

**Treaty year: 1 July 2026 – 30 June 2027.** Close the outgoing treaty at
30 June 2026 so there is no overlap and no double cede.

---

## 1. Global parameters

| Parameter | Value |
|---|---|
| Retention | 30% |
| Cession | 70% |
| One gross line (first line) | 10,000,000 |
| — retention leg of line 1 | 3,000,000 |
| — quota share leg of line 1 | 7,000,000 |
| Surplus (Property and Engineering only) | 40,000,000 |
| Auto FAC capacity | 50,000,000 |
| Auto FAC attaches at | 50,000,000 |
| FAC attaches at (treaty capacity exhausted) | 100,000,000 |
| Currency | Botswana Pula |
| Basis | Sum insured |
| Test level | Per risk address |

Attachment points are derived, not typed: Auto FAC attaches at first line + surplus
(10,000,000 + 40,000,000). FAC attaches at that plus Auto FAC capacity
(50,000,000 + 50,000,000).

---

## 2. Two kinds of class — this is the important distinction

**LAYERED — Property and Engineering only.** The cascade runs in full: first line,
then surplus, then Auto FAC, then FAC.

**CAPPED — Motor, Transportation, Miscellaneous, Guarantee.** No cascade. 30/70
applies *within the class limit* and everything above the limit is FAC. There is no
surplus on these classes. This is Tlamelo's amendment of 25 August, reversing the
earlier instruction to remove the limits.

---

## 3. Layered classes — Property and Engineering

Configure the same cascade on both.

| Layer | Formula type | Amount |
|---|---|---|
| Net retention | NETRETENTION | 3,000,000 |
| Quota share | QUOTASHARE | 7,000,000 |
| Surplus | SURPLUS | 40,000,000 |
| Auto FAC | AUTOFAC | 50,000,000 |
| Facultative | FACULTATIVE | balance above 100,000,000 |

Worked example, a risk address with a sum insured of 120,000,000:

| Layer | Amount |
|---|---|
| Net retention | 3,000,000 |
| Quota share | 7,000,000 |
| Surplus | 40,000,000 |
| Auto FAC | 50,000,000 |
| Facultative | 20,000,000 |
| **Total** | **120,000,000** |

---

## 4. Capped classes — no surplus, no cascade

| Regulatory mapping | Treaty | Class limit | Net retention 30% | Quota share 70% | Above the limit |
|---|---|---|---|---|---|
| Motor | Motor QS | 5,000,000 | 1,500,000 | 3,500,000 | FAC |
| Transportation | General QS | 3,000,000 | 900,000 | 2,100,000 | FAC |
| Miscellaneous | General QS | 1,000,000 | 300,000 | 700,000 | FAC |
| Guarantee | General QS | 1,000,000 | 300,000 | 700,000 | FAC |

The retention and quota share figures above apply where the sum insured reaches the
class limit. Below it, apply 30% and 70% to the sum insured itself.

Worked example, Motor with a sum insured of 34,000,000: limit 5,000,000, so
1,500,000 retained, 3,500,000 quota share, and 29,000,000 to FAC.

---

## 5. Group-level override

One group takes a different limit from its class.

| Group | Class limit | Reason |
|---|---|---|
| MOTOR_TRAILERS_COM | 1,500,000 | Schedule A — Own Damage, any one trailer |
| MOTOR_TRAILERS_DOM | 1,500,000 | Same limit, domestic |

Split at the limit: 450,000 retained, 1,050,000 quota share.

---

## 6. Classes that do not cede

| Regulatory mapping | Treatment |
|---|---|
| Accident | 100% retained |
| Liability | 100% retained |

Confirmed intentional for 2026/27 (point 14). Both raise monthly as exceptions.

Passenger Liability maps to Liability and is therefore 100% retained (point 5), even
though the Motor treaty's Schedule A does grant 2,500,000 of capacity for it.

---

## 7. Treaty attachment

| Treaty | Slip leader | Classes attached |
|---|---|---|
| General Quota Share 2026/27 | FM Re Botswana | Property, Engineering, Transportation, Miscellaneous, Guarantee |
| Motor Quota Share 2026/27 | Continental Re Botswana | Motor |

**Domestic attaches to the same two treaties.** Point 13 — the treaty covers the
whole Alpha Direct book, so domestic groups route by their regulatory mapping
exactly as commercial does. Attach every domestic group to the treaty its mapping
belongs to.

---

## 8. Treaty-level limits

Caps on reinsurers' liability. These are NOT per-risk capacity and must not be added
to the class limits.

| Limit | Amount |
|---|---|
| General — event limit | 70,000,000 |
| General — annual aggregate, SRCC, Zimbabwe risks | 20,000,000 |
| General — annual aggregate, War / Civil War, Goods in Transit | 6,000,000 |
| Motor — event limit | 70,000,000 |
| Motor — event limit, Riot & Strike | 50,000,000 |
| Motor — annual aggregate, Riot & Strike | 50,000,000 |

---

## 9. Regulatory mapping — coverage and group to class

Every group must carry a mapping or it will not route. Confirmed positions:

| Coverage / group | Mapping |
|---|---|
| Motor Traders External (MOTOR_TRADERS_COM_EXT) | Property |
| Motor Traders Internal (MOTOR_TRADERS_COM_INT) | Property |
| Contractors All Risks | Property |
| Plant All Risks, Machinery Breakdown, Assets All Risks | Engineering |
| Personal Accident | Liability |
| Personal All Risks | Property |
| Passenger Liability | Liability |

Do **not** create MOTOR_TRADERS_COM. Point 4 — it was a consolidation of External and
Internal, and only those two should exist.

---

## 10. Order of work

1. Close the outgoing treaty at 30 June 2026.
2. Insert the two 2026/27 treaties with the slip numbers from the signing schedule.
3. Attach formulas per sections 3 and 4, including the trailer override in section 5.
4. Populate the regulatory mapping per section 9, commercial and domestic.
5. Leave Accident and Liability unattached — retained is the correct outcome, not a gap.
6. Reconcile before going live: run COMG2026213751 and agree it against the
   restated workbook. That policy is the one every figure has been tested on.

---

## Two things to watch

**Nothing reads the regulatory mapping yet.** The live engine still routes by
reinsurance group. Configuring the mapping is correct and necessary, but the tab will
keep allocating by group until the engine is pointed at the mapping column — that is
a code change, still to be made.

**The surplus is unevidenced.** 40,000,000 is configured on Tlamelo's written
confirmation, but no signed surplus slip has been received and his own work paper
questions the figure — four lines on the 3,000,000 net retention would be 12,000,000,
not 40,000,000 on the gross first line. Configure 40,000,000 as instructed, and
expect to revisit it when the slip arrives.
