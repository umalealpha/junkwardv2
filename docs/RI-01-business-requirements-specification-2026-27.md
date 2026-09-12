# Reinsurance Business Requirements Specification

## 2026/27 Treaty Year — General Quota Share and Motor Quota Share

**Document:** RI-01 — Business Requirements Specification
**Prepared for:** Chief Financial Officer, Alpha Direct Insurance Company (Pty) Ltd
**Date:** 3 August 2026
**Status:** For business validation and confirmation

---

## 1. Purpose

This document states **what the two 2026/27 reinsurance treaties require of Alpha Direct**. It is derived entirely from the executed slips and contractual wordings placed through J.B. Boda. It makes no reference to any system.

Its purpose is confirmation. Please read each requirement and confirm it is correctly captured, or mark it for correction. Section 13 lists items that could not be resolved from the documents and need a decision.

Every requirement carries an identifier (for example `BR-CES-01`). These identifiers are the reference keys used by the companion document, **RI-02 — Gap Analysis and Implementation Plan**.

**Reading key.** *Applies to* — **G** = General Quota Share, **M** = Motor Quota Share, **Both** = both treaties.

---

## 2. Treaty identification

| | General Quota Share | Motor Quota Share |
|---|---|---|
| Reinsured | Alpha Direct Insurance Company (Pty) Ltd, Gaborone | Alpha Direct Insurance Company, Gaborone, and/or Associated and/or Subsidiary Companies |
| Broker / Intermediary | J.B. Boda Insurance & Reinsurance Brokers Pvt Ltd | J.B. Boda Insurance & Reinsurance Brokers Pvt Ltd |
| Slip leader | FM Re Botswana | Continental Re Botswana |
| Period | 1 July 2026 – 30 June 2027 | 1 July 2026 – 30 June 2027 |
| Basis | Underwriting year | Underwriting year |
| Retention / Cession | 30% / 70% | 30% / 70% |
| Commission | Flat 37.50% plus profit commission 30% | Sliding scale 40% – 22.5%, provisional 25% |
| Currency | Botswana Pula | Botswana Pula |
| Law and jurisdiction | Botswana | Botswana |
| Estimated premium income 2026/27 (100%) | BWP 24,872,310 | BWP 70,000,000 (100% FGU) |
| Brokerage | 2.50% | 2.50% |

---

## 3. Treaty governance and scope

| ID | Requirement | Applies to | Source |
|---|---|---|---|
| BR-GOV-01 | Two separate treaties operate in parallel and must be administered, accounted and reported entirely separately. | Both | Slips |
| BR-GOV-02 | Cover applies to all policies issued or renewed during the twelve months commencing 1 July 2026, expiring 30 June in any year but not before 30 June 2027, subject to three months' notice of cancellation. | Both | Slips, Period |
| BR-GOV-03 | Provisional notice of cancellation three months prior to the anniversary date is deemed to have been given by both Reinsured and Reinsurers. | Both | Slips, Period |
| BR-GOV-04 | All business is written and accounted on an **underwriting year basis**. Premium and claims attach to the underwriting year of the original policy, not the calendar period of the transaction. | Both | Slips, Period |
| BR-GOV-05 | General class: all business accepted and renewed, whether direct or by way of facultative reinsurance, treated in the books as Personal Lines and Commercial Business as set out in Schedule A. | G | General slip, Class |
| BR-GOV-06 | Motor class: all business accepted in respect of New Business, Renewals and Endorsements, whether direct or by way of facultative reinsurance, treated in the books as Motor Own Damage, Motor Third Party and Passenger Liability. | M | Motor slip, Class |
| BR-GOV-07 | Motor cover extends to Associated and Subsidiary Companies but **excludes companies acquired during the period**. | M | Motor slip, Reinsured |
| BR-GOV-08 | Cessions are made in original currency. Where risks are written in a currency other than Botswana Pula, underwriting limits convert at the official rate of exchange on the day of issuing the policy. | Both | Wording Art 12 / 13 |
| BR-GOV-09 | Loss settlements in other currencies convert at the rate of exchange used in making the remittance. | Both | Wording Art 13 |
| BR-GOV-10 | Motor settlement currency shall be the same currency in which the premium was paid. | M | Motor wording Art 12 |
| BR-GOV-11 | Botswana law and jurisdiction. Seat of arbitration Gaborone, Botswana. | Both | Slips |
| BR-GOV-12 | No portfolio entry and no portfolio withdrawal. Premium nil and losses nil, underwriting year basis. | G | General slip, Portfolio |
| BR-GOV-13 | On termination the Reinsured has the option to withdraw the existing portfolio on the specified basis or on alternative terms mutually agreed. | M | Motor wording Art 17.1 |
| BR-GOV-14 | Extended expiry: where a loss occurrence is in progress at expiry, Reinsurers indemnify the whole claim resulting from that occurrence, and no part may be claimed against any renewal. | G | General wording Art 18 |
| BR-GOV-15 | Special cancellation rights arise on prohibition of performance, insolvency, material change in ownership or control, armed hostilities, withdrawal of authority to transact, or failure to comply with terms. Motor adds loss of 25% or more of shareholders' funds; General refers to loss of the whole or any part of paid-up capital. | Both | Wording Art 17.2 |
| BR-GOV-16 | On special cancellation, premium due to Reinsurers is calculated on Gross Earned Premium Income up to the date of termination. Liability continues for unsettled losses arising from policies issued during the currency of the agreement. | Both | Wording Art 17.3 |
| BR-GOV-17 | Terms are construed in accordance with recognised reinsurance practice rather than strict literal or legal interpretation. | Both | Wording, Interpretation |
| BR-GOV-18 | Errors and omissions on either side do not relieve the other party from liability, provided the error is rectified immediately on discovery. | Both | Wording, Errors and Omissions |

---

## 4. Territorial scope

| ID | Requirement | Applies to | Source |
|---|---|---|---|
| BR-TER-01 | General base territory: Botswana, Zimbabwe, Zambia and incidental interests abroad, **excluding USA and Canada**. | G | General slip |
| BR-TER-02 | Worldwide in respect of All Risks, Laptops, Personal Belongings Cover and Personal Legal Liability. | G | General slip |
| BR-TER-03 | Worldwide in respect of Liability Business of original insureds domiciled in Sub-Saharan Africa and their subsidiaries and associate companies, excluding original insureds' practices or businesses domiciled in the USA and Canada. | G | General slip |
| BR-TER-04 | For policies issued in Botswana, group extensions apply: **Choppies Group** adds South Africa and Zimbabwe; **Motovac Group** adds South Africa and Namibia; **Kamoso Distribution Group** adds South Africa and Namibia. | G | General slip |
| BR-TER-05 | Policies for the Choppies, Kamoso and Motovac Groups are issued in the original countries by partner insurance companies and facultatively accepted by Alpha Direct in Botswana. | Both | Slips |
| BR-TER-06 | Motor territory: Sub-Saharan Africa and Indian Ocean Islands, being the 47 countries listed in the Motor slip schedule, in respect of policies issued in Botswana or issued in original countries by partner insurers and facultatively accepted by Alpha Direct in Botswana. | M | Motor slip |
| BR-TER-07 | Special acceptance outside territorial scope: **Botho University, Lesotho, peak value BWP 5,000,000**, with no facultative inwards restriction to apply and all other terms and conditions applying. | G | General slip, Information |
| BR-TER-08 | Special acceptance: **Diagnofirm Medical Laboratories (Pty) Ltd**. | G | General slip, Information |
| BR-TER-09 | All special acceptances are to be agreed by the Leading Reinsurer only. Motor special acceptances are as expiring, to be reviewed with the Leading Reinsurer on renewal. | Both | Slips |

---

## 5. Cession and retention

| ID | Requirement | Applies to | Source |
|---|---|---|---|
| BR-CES-01 | Retention **30.00%**, cession **70.00%** of every risk falling within the class and territorial scope. | Both | Slips, Limits |
| BR-CES-02 | Rate basis is Original Gross Rate. | G | General slip, Rate |
| BR-CES-03 | Premium ceded is the Reinsurers' proportion of the original premiums which the Reinsured receives, **less only** returns, cancellations, and premiums paid for prior insurances or reinsurances which inure to the benefit of the agreement. | Both | Slips, Premium |
| BR-CES-04 | Claim recovery is at the ceded proportion. Reinsurers participate to the extent of their share in **all salvages and recoveries**. | Both | Wording Art 8.2 |
| BR-CES-05 | Reinsurers indemnify their share of all claim expenses incurred in investigating, adjusting, settling, compromising or contesting a claim, **other than** salaries of employees and management expenses of the Reinsured. | Both | Wording Art 8.2 |
| BR-CES-06 | Ex gratia claim payments made voluntarily are not recoverable without the Reinsurers' prior written consent. | Both | Wording Art 8.2 |
| BR-CES-07 | Reinsurers' obligation to pay claims is contingent on payment of due premiums. Until premium is received Reinsurers have no obligation to pay claims. Off-set applied under the agreement constitutes compliance. | Both | Wording Art 6.2 |
| BR-CES-08 | **Co-insurance 50%.** | G | General slip, Limits |
| BR-CES-09 | The Reinsured is the sole judge as to what constitutes One Risk (Motor). For General, one risk means all property or interest at any one location designated by the Reinsured in its records as subject to one risk retention. | Both | Wording Art 5.3 |
| BR-CES-10 | Follow the fortunes: all cessions are subject to the same terms and conditions as bind the Reinsured under the original acceptances. | Both | Wording Art 5.7 / 5.8 |

---

## 6. Capacity and limits

### 6.1 General Quota Share — Schedule A

Limits are VAT-exclusive, on a sum insured basis, in Botswana Pula. The Quota Share account at 100% shall not be committed for more than:

| ID | Class | Limit (BWP) |
|---|---|---|
| BR-CAP-01 | Damage to Real Property — Material Damage and Business Interruption combined, any one situation | 10,000,000 |
| BR-CAP-02 | Engineering, Contractors All Risks, Erection All Risks, Advanced Loss of Profits, Machinery Breakdown and Machinery Loss of Profits — combined limit | 10,000,000 |
| BR-CAP-03 | Electronic Equipment, any one situation | 6,000,000 |
| BR-CAP-04 | Goods in Transit | 3,000,000 |
| BR-CAP-05 | Miscellaneous and Financial Loss, including Money, Glass, Theft and Malicious Damage Extension, any one risk | 1,000,000 |
| BR-CAP-06 | Fidelity Guarantee — maximum capacity per risk in respect of one or more employed acting in collusion | 1,000,000 |
| BR-CAP-07 | Accidental Damage | 7,500,000 |

### 6.2 Motor Quota Share — Schedule A

| ID | Class | Limit (BWP) | Basis of cover |
|---|---|---|---|
| BR-CAP-08 | Own Damage | 5,000,000 | Any one motor vehicle |
| BR-CAP-09 | Own Damage — trailer | 1,500,000 | Any one trailer |
| BR-CAP-10 | Passenger Liability | 2,500,000 | Any one event for authorised passengers |
| BR-CAP-11 | Third Party Damage | 10,000,000 | Any one event |

### 6.3 Event limits and annual aggregates

| ID | Requirement | Applies to | Source |
|---|---|---|---|
| BR-CAP-12 | **Event limit BWP 70,000,000.** Reinsurers' liability covering material damage and/or business interruption policies is limited to BWP 70,000,000 any one loss occurrence. | G | General slip / Art 5.9 |
| BR-CAP-13 | **Event limit BWP 70,000,000.** | M | Motor slip |
| BR-CAP-14 | **Event limit in respect of Riot and Strike: BWP 50,000,000.** | M | Motor slip |
| BR-CAP-15 | **Annual aggregate limit in respect of Riot and Strike: BWP 50,000,000.** | M | Motor slip |
| BR-CAP-16 | **Annual aggregate limit BWP 20,000,000** for SRCC coverage with respect to risks situated in **Zimbabwe**. | G | General slip |
| BR-CAP-17 | **Annual aggregate limit BWP 6,000,000** for War and Civil War coverage with respect to **Goods in Transit, all territories**. | G | General slip |
| BR-CAP-18 | Where an event involves two or more underwriting years, Reinsurers' liability is reduced in the same proportion as the losses of that event involving this agreement contribute to the sum of all losses of that event across all underwriting years. | G | General wording Art 5.9 |
| BR-CAP-19 | Marine terrorism aggregate for US situated stationary risks: per event limited to twice the per-risk treaty limit, where "event" means all acts of terrorism executed within a consecutive period of 72 hours; annual aggregate limited to four times the per-risk treaty limit. | G | General exclusion schedule, cl. 24 |

### 6.4 Hours clause — definition of one loss occurrence

A loss occurrence means all individual losses arising out of and directly occasioned by one and the same event. The duration of any event is limited as follows.

| ID | Peril | General | Motor |
|---|---|---|---|
| BR-CAP-20 | Hurricane, typhoon, tornado, cyclone, windstorm, hailstorm (Motor adds rainstorm emanating from one atmospheric disturbance) | 72 consecutive hours | 72 consecutive hours |
| BR-CAP-21 | Earthquake, seaquake, volcanic eruption, tidal wave | 72 consecutive hours | 72 consecutive hours |
| BR-CAP-22 | Strike, riot, civil commotion and malicious damage **within the limits of one city, town or village** | 72 consecutive hours | 72 consecutive hours |
| BR-CAP-23 | All other perils reinsured | 168 consecutive hours | 168 consecutive hours |
| BR-CAP-24 | Explosion, conflagration, firestorms, bush fires and any other fires or series of fires, **within an 80 kilometre radius of any one fixed point selected by the Reinsured** | 75 consecutive hours | Not applicable |

| ID | Requirement | Applies to |
|---|---|---|
| BR-CAP-25 | Where a single insured peril gives rise to another insured peril, all individual losses occasioned by all such perils are treated as one event. | Both |
| BR-CAP-26 | Where an event involves a peril in the 72-hour categories as well as a peril in the 168-hour category, the Reinsured may decide under which section to submit the claim. | Both |
| BR-CAP-27 | The Reinsured chooses the date and time when any period of consecutive hours commences. Where an event exceeds the period, the Reinsured may divide it into two or more claims, provided no two periods overlap and no period commences earlier than the date and time of the first recorded individual loss in that event. | Both |

### 6.5 Contingent business interruption sub-limits

| ID | Requirement | Applies to | Source |
|---|---|---|---|
| BR-CAP-28 | CBI coverage is granted only as a consequence of insured physical damage, and for **named** suppliers and customers only. | G | General wording Art 27 |
| BR-CAP-29 | Named suppliers and customers sub-limit must not exceed 75% of the business interruption limit of liability, subject to a maximum of **BWP 7,500,000**. | G | General wording Art 27 |
| BR-CAP-30 | Unnamed suppliers and customers sub-limit must not exceed 10% of the business interruption limit of liability, subject to a maximum of **BWP 1,000,000**. | G | General wording Art 27 |
| BR-CAP-31 | Public utilities and denial or prevention of access sub-limit must not exceed three months, or the equivalent amount of the annual business interruption sums insured, subject to a maximum of **BWP 1,000,000**. | G | General wording Art 27 |
| BR-CAP-32 | Denial of access extension must be within 50 km of the vicinity of the insured location; where the logical access point is beyond 50 km, a maximum of 100 km applies. | G | General wording Art 27 |
| BR-CAP-33 | CBI limits and accumulation between several risks must be taken into account in deciding capacity allocation. Reinsurers may request specific CBI exposures per treaty to establish accumulation potential. | G | General wording Art 27 |

---

## 7. Commission

### 7.1 General Quota Share — flat and profit commission

| ID | Requirement | Applies to | Source |
|---|---|---|---|
| BR-COM-01 | **Ceding commission 37.50%** of ceded premium. | G | General slip |
| BR-COM-02 | **Profit commission 30.00%** of the profit of each underwriting year. | G | General slip |
| BR-COM-03 | Management expenses **7.5%** of the premiums for the underwriting year. | G | General slip |
| BR-COM-04 | Losses carried forward **5 years**. | G | General slip |
| BR-COM-05 | Profit calculation — **Income**: gross premiums for the underwriting year. | G | General slip |
| BR-COM-06 | Profit calculation — **Outgo**: (1) commission of 37.50% on the underwriting year's premium; (2) losses paid and outstanding and loss expenses paid during the underwriting year; (3) management expenses at 7.5% of premiums for the underwriting year; (4) any deficit carried forward from the previous year. | G | General slip |
| BR-COM-07 | Where the result is a profit, profit commission is payable. Where the result is a deficit, no profit commission is paid for that underwriting year and the deficit is set against profit arising in subsequent underwriting years until the deficit or accumulation of deficits is extinguished, after which payment resumes. | G | General slip |
| BR-COM-08 | The first profit or loss statement for each underwriting year is ascertained **24 months from inception of that underwriting year** and submitted together with the fourth quarter statement of account. | G | General slip |
| BR-COM-09 | The second calculation is ascertained 12 months from the first calculation and submitted with the fourth quarter statement of account. | G | General slip |
| BR-COM-10 | Thereafter profit or loss statements are ascertained every 24 months, unless there is a material change in result, meaning a **20% variation in profit or loss**. | G | General slip |

### 7.2 Motor Quota Share — sliding scale commission

| ID | Requirement | Applies to | Source |
|---|---|---|---|
| BR-COM-11 | Commission is calculated on a **sliding scale, underwriting year basis**. The Reinsured calculates an adjusted commission at the end of each underwriting year. | M | Motor slip |
| BR-COM-12 | A **provisional commission of 25.00%** is adopted for the accounts of the first three quarters of the underwriting year. | M | Motor slip |
| BR-COM-13 | The **fourth quarter and subsequent quarterly accounts adjust** the commission payable for the underwriting year at the appropriate rate from the scale. | M | Motor slip |
| BR-COM-14 | **Loss Ratio CAP — 75%.** | M | Motor slip |
| BR-COM-15 | Where the agreement is terminated on a run-off basis, the final sliding scale commission or profit commission is calculated only after all claims have been finalised and settled. | M | Motor wording Art 7 |

**Sliding scale commission table (BR-COM-11).** Commission falls 0.7 percentage points for each 1 percentage point rise in loss ratio.

| LR % | Comm % | Margin | LR % | Comm % | Margin | LR % | Comm % | Margin |
|---|---|---|---|---|---|---|---|---|
| 50 | 40.0 | 10.0 | 59 | 33.7 | 7.3 | 68 | 27.4 | 4.6 |
| 51 | 39.3 | 9.7 | 60 | 33.0 | 7.0 | 69 | 26.7 | 4.3 |
| 52 | 38.6 | 9.4 | 61 | 32.3 | 6.7 | 70 | 26.0 | 4.0 |
| 53 | 37.9 | 9.1 | 62 | 31.6 | 6.4 | 71 | 25.3 | 3.7 |
| 54 | 37.2 | 8.8 | 63 | 30.9 | 6.1 | 72 | 24.6 | 3.4 |
| 55 | 36.5 | 8.5 | 64 | 30.2 | 5.8 | 73 | 23.9 | 3.1 |
| 56 | 35.8 | 8.2 | 65 | 29.5 | 5.5 | 74 | 23.2 | 2.8 |
| 57 | 35.1 | 7.9 | 66 | 28.8 | 5.2 | 75 | 22.5 | 2.5 |
| 58 | 34.4 | 7.6 | 67 | 28.1 | 4.9 | | | |

### 7.3 Brokerage and deductions

| ID | Requirement | Applies to | Source |
|---|---|---|---|
| BR-COM-16 | **Brokerage 2.50%.** | Both | Slips |
| BR-COM-17 | **No other deductions from premium.** | Both | Slips |

---

## 8. Claims

| ID | Requirement | Applies to | Source |
|---|---|---|---|
| BR-CLM-01 | Notice of claims threshold **BWP 500,000 for 100% of the treaty**. | G | General slip |
| BR-CLM-02 | Notice of claims threshold **BWP 500,000 for the ceded portion** of the loss, or equivalent in other currencies. | M | Motor slip |
| BR-CLM-03 | Written notice must be given without delay and **not later than 30 days** of the event or claim, or of becoming aware of circumstances which could give rise to a claim exceeding or potentially exceeding the notification limit. Reinsurers must thereafter be kept fully informed of developments. | Both | Wording Art 8.1 |
| BR-CLM-04 | Claim notification must include: name of policy holder, claimant and party causing the loss; date of loss; place of loss; estimated amount of the original loss and amount of claim made, cause and circumstances of loss; comment on the obligation to indemnify under the original policy; settlement or defence measures carried out or planned; estimated total reinsured expenditure with transparent calculation of the allocation to reserves; and allocation to the reinsurance treaty or programme under which the claim is made. | G | General wording Art 8.1 |
| BR-CLM-05 | **Cash loss limit BWP 500,000 for 100% of the treaty.** Where a claim on Reinsurers exceeds this amount the Reinsured is entitled to payment on demand, provided the Loss Notification / Cash Loss Request Form (Annexure A) is completed in full. | G | General slip / Art 8.3 |
| BR-CLM-06 | **Cash loss limit BWP 250,000 for the ceded portion.** Where a claim on Reinsurers exceeds this amount the Reinsured is entitled to payment **within five working days**. | M | Motor slip / Art 8.3 |
| BR-CLM-07 | Cash call payments made by Reinsurers must be refunded by the Reinsured in the same quarter in which the cash call was paid, and included in the quarterly statement of account **as a separate line item**. | G | General wording Art 8.3 |
| BR-CLM-08 | The Reinsured settles claims at its discretion and without interference, but is not precluded from consulting Reinsurers on any claim which may result in a treaty claim. | Both | Wording Art 8.2 |
| BR-CLM-09 | An outstanding claims register must be supplied to Reinsurers at the end of each quarter, showing for each outstanding claim exceeding the notification threshold: name, date of loss and estimated amount — given **separately per year of occurrence and per underwriting year** (General) and **per underwriting year** (Motor). | Both | Wording Art 8.4 |
| BR-CLM-10 | The outstanding claims register must be forwarded **not later than six weeks from the end of the quarter**. | Both | Wording Art 8.4 |
| BR-CLM-11 | Following cancellation, outstanding claims information continues to be forwarded until all liability under the agreement has been discharged. | Both | Wording Art 8.4 |
| BR-CLM-12 | All claims are subject to the agreement of each Reinsurer in respect of its own participation. Lloyd's underwriters per the Lloyd's 2006 Claims Scheme; IUA company underwriters per IUA Claims Agreement practices; non-bureaux underwriters per their own practices. | G | Contract administration |

---

## 9. Accounting and settlement

| ID | Requirement | Applies to | Source |
|---|---|---|---|
| BR-ACC-01 | Statements of account are rendered **quarterly by the Reinsured to the Reinsurers within 45 days after the close of each quarter**. | Both | Slips |
| BR-ACC-02 | Following receipt, Reinsurers confirm the accounts or raise objections **within fourteen days**. | Both | Slips |
| BR-ACC-03 | Statements of account must be **broken down according to the different shares and classes of insurance**. | Both | Wording Art 10.2 |
| BR-ACC-04 | Accounting item: written premiums payable to Reinsurers **less returns, cancellations and premiums paid for insurance and reinsurance which inure to the benefit** of the agreement. | Both | Wording Art 10.2.1 |
| BR-ACC-05 | Accounting item: **commissions and expenses**. | Both | Wording Art 10.2.2 |
| BR-ACC-06 | Accounting item: **claims paid less salvages and recoveries**. | Both | Wording Art 10.2.3 |
| BR-ACC-07 | Accounting item: **outstanding losses**, broken down into years of occurrence (General) and underwriting years (Motor). | Both | Wording Art 10.2.4 |
| BR-ACC-08 | Accounting item: **cash loss recoveries**. | Both | Wording Art 10.2.5 |
| BR-ACC-09 | Settlement of balances — from the cedant: **on a cheque attached basis**, at the same time the accounts are rendered. From the Reinsurer: at the same time as the accounts are confirmed. | Both | Slips / Art 10.4 |
| BR-ACC-10 | **Delay in payment interest at 110% of the market prime lending rate** on balances due, from due date to date of payment, on overdue balances. | Both | Slips / Art 10.5 |
| BR-ACC-11 | Any confirmed balances due by either party may be **set off** against confirmed balances of the other party. This right survives termination and extends to any other insurance or reinsurance business relationship between the parties. | Both | Wording Art 11 |
| BR-ACC-12 | **Reserve deposit — General, foreign reinsurers:** premium reserve **40.00%**; interest **2.00% below the average call rate for the year**; loss reserve **nil**. Domestic reinsurers nil. | G | General slip |
| BR-ACC-13 | Alternative to a reserve deposit: a **letter of credit issued by a bank in the form prescribed by the Registrar of Short Term Insurance**, or an irrevocable guarantee. Where furnished, no reserve deposit applies. | Both | Slips / Art 9 |
| BR-ACC-14 | Reserve deposits are retained only in respect of **Reinsurers not domiciled in Botswana**. Where required by law, the Reinsured has a prior charge and lien on monies retained as security. | Both | Wording Art 9 |
| BR-ACC-15 | Interest on reserves retained is payable **per annum less income tax if applicable**, accruing from the dates on which the respective amounts are credited to the Reserve Fund. | Both | Wording Art 9 |
| BR-ACC-16 | On termination the entire reserve deposit is released to Reinsurers together with interest due. Where the premium portfolio is run off, or the reserve deposit provision is deleted, the reserve deposit is released **quarterly in the quarterly accounts**. | Both | Wording Art 9 |
| BR-ACC-17 | **VAT** applies to the financial transactions arising from the agreements, at the rate current at the time of the transaction. | Both | Slips |
| BR-ACC-18 | All treaty figures, including those reflected in attached statistics, **exclude VAT** unless otherwise stated. General Schedule A limits are VAT-exclusive. | Both | Slips |
| BR-ACC-19 | **Repatriation clause to apply.** | M | Motor slip, Information |
| BR-ACC-20 | Payments by a Reinsurer to the Intermediary for the account of the Reinsured **discharge the Reinsurer's liability**. Payments by the Reinsured through the Intermediary constitute payment to the Reinsurer **only when actually received** by the Reinsurer. | Both | Wording, Intermediaries |

---

## 10. Underwriting controls and validations

| ID | Requirement | Applies to | Source |
|---|---|---|---|
| BR-UWC-01 | **Inwards facultative reinsurance is restricted to 25% of the Reinsured's treaty limit**, unless otherwise agreed by the Leading Reinsurer. | Both | Slips / Motor excl. 11 |
| BR-UWC-02 | **Exception:** for Choppies Group, Kamoso Group and Motovac Group risks, Alpha Direct may cede inwards facultative reinsurance **up to 100% of treaty capacity**. | Both | Slips / Motor excl. 11 |
| BR-UWC-03 | Acceptances on a PML basis are subject to a **minimum MPL of 50% on referral**. | G | General slip |
| BR-UWC-04 | The Reinsured may effect prior facultative reinsurance to reduce its gross commitment on any risk where necessary, but **shall not effect facultative protection solely to protect business retained net for its own account**. | Both | Wording Art 5.4 / 5.5 |
| BR-UWC-05 | The Reinsured reserves the right to effect excess of loss protection for its net retention at its own discretion; such protection does not affect the operation of the treaty. | Both | Wording Art 5.5 / 5.6 |
| BR-UWC-06 | The Reinsured undertakes **not to introduce any change in its established acceptance and underwriting practice** capable of increasing or extending the liability or exposure of, or the cessions to, Reinsurers, without prior consultation and written approval of Reinsurers. | Both | Wording Art 5.8 / 5.9 |
| BR-UWC-07 | Original policy periods must not exceed **twelve months plus odd time not exceeding eighteen months in all**. | Both | Exclusion schedules |
| BR-UWC-08 | **Accidental Damage** and resulting business interruption is excluded for the 100% Quota Share limit on the Accidental Damage section **which exceeds BWP 7,500,000**. | G | Property/Fire exclusions, cl. 15 |
| BR-UWC-09 | Business interruption **indemnity periods exceeding twelve months must be notified to Reinsurers at renewal**. | G | Property/Fire exclusions, cl. 18 |
| BR-UWC-10 | Engineering referral risks require referral, including: construction of new power stations or plants; tunnels and shafts; power plants; gas turbines; wet risks; semi-conductor factories; petrochemical facilities; policies originally issued for periods in excess of 36 months; machinery breakdown loss of profits with indemnity periods exceeding 12 months; waste incinerating plants; black liquor boilers; erection all risks policies where maintenance cover exceeds 12 months; run-off covers; **contractors plant and machinery in excess of BWP 10,000,000**; **single projects outside Botswana where project value exceeds BWP 10,000,000**; **projects in Botswana or policies with a value in excess of BWP 10,000,000**; policies issued to mining enterprises; and bridges with a single span in excess of 50 m or exceeding 100 m in total length. | G | Engineering referral risks |
| BR-UWC-11 | Advanced Loss of Profits or Delay in Start-up covers with annual gross profit sums insured **exceeding BWP 10,000,000 for the cedant's share** are excluded. | G | CAR/EAR exclusions, cl. 2 |
| BR-UWC-12 | Fidelity Guarantee liability is **non-cumulative** from year to year or period to period, and shall in no case exceed the limit of indemnity stated in the policy schedule. | G | Fidelity exclusions, cl. 12 |
| BR-UWC-13 | The **SRCC extension** covers physical damage only and does not cover loss or damage occurring in the Republic of South Africa or Namibia. For the Choppies, Kamoso and Motovac portfolios the extension is amended to exclude South Africa in respect of South African domiciled risks or registered vehicles, and Namibia in respect of Namibian domiciled risks or registered vehicles. | Both | Exclusion schedules |
| BR-UWC-14 | **Strike, riot, civil commotion and malicious damage is not covered** for the following occupancies: government businesses (police offices, civil registry offices, fiscal authorities); government linked assets (embassies, diplomatic premises, political party offices or headquarters, military and parliamentary installations); key infrastructure (toll stations, train stations, metro stations); retail (supermarkets, shopping malls, car dealers, electronic shops, jewellery shops); highstreet bank branches and ATMs; and hotels with casinos and entertainment venues. | G | Exclusion schedule |
| BR-UWC-15 | The **General exclusion schedule** must be enforced. It comprises general exclusions (obligatory reinsurance; war, civil war, political risk and terrorism; nuclear energy risks; nuclear causes and radioactive contamination; pools and pooling arrangements; first loss, excess of loss, layered and stop loss business; travel insurance; tour operators; pollution and contamination; infectious epidemics; detention, confiscation and forfeiture; fines and punitive damages; IT and cyber exposure; oil and gas drilling and production rigs; offshore technology risks; grid interruption in South Africa with a loadshedding carve-back; asbestos; known losses; business run-off; coal-fired power generation and thermal coal mining; oil and gas exploration and production), together with class-specific exclusion schedules for Property and Fire, CAR/EAR and Engineering, Fidelity Guarantee, and Marine. | G | Exclusion schedule |
| BR-UWC-16 | The **Motor exclusion schedule** must be enforced. It comprises general exclusions (obligatory reinsurance and retrocession; war, civil war, political risk and terrorism; nuclear energy risks; nuclear causes and radioactive; pools; first loss, excess of loss, stop loss and layered business; computer loss general exception with special extension; policies exceeding twelve months plus odd time; retroactive cover for known losses; loss portfolio transfer; the inwards facultative 25% restriction; and all other exclusions per the underlying policy) together with motor specific exclusions. | M | Motor exclusion schedule |
| BR-UWC-17 | **Motor specific exclusions:** races, rallies and speed trials; vehicles on rails and not terra firma; road use of contractors plant equipment and special type vehicles not licensed for public road use; commercial vehicles not compliant with dangerous goods legislation or United Nations regulations; police force and military vehicles; liability assumed under any Compulsory Motor Vehicle Act enactment; public emergency service vehicles; goods of any kind transported in the insured vehicle; airport vehicles used on airside; and vehicles whose principal use is the transportation of highly explosive substances, bulk oil or liquefied gas, chemical substances and gases, hazardous waste, or self drive hire. | M | Motor specific exclusions |
| BR-UWC-18 | **Sanction Limitation and Exclusion Clause LMA3100** applies. | Both | Wording |
| BR-UWC-19 | **Communicable Disease Exclusion LMA5394** and **Cyber Loss Exclusion LMA5411** apply. | Both | Wording |
| BR-UWC-20 | **Institute Cyber Attack Exclusion Clause LMA5402**, RUB (Russia–Ukraine) Exclusion Clause and Five Powers Clause apply to marine business. | G | Marine clauses |

---

## 11. Security and reinsurer participations

| ID | Requirement | Applies to | Source |
|---|---|---|---|
| BR-SEC-01 | **Several liability (LSW 1001).** Subscribing Reinsurers' obligations are several and not joint and are limited solely to the extent of their individual subscriptions. No Reinsurer is responsible for the subscription of a co-subscribing Reinsurer that fails to satisfy its obligations. | Both | Security details |
| BR-SEC-02 | **Basis of written lines: percentage of order.** | Both | Security details |
| BR-SEC-03 | Where written lines exceed 100% of the order, lines written "to stand" are allocated in full and all other lines are signed down in equal proportions so that aggregate signed lines equal 100% of the order. | Both | Signing provisions |
| BR-SEC-04 | Where the placement of the order is **not completed by the commencement date**, all lines written by that date are signed in full. | Both | Signing provisions |
| BR-SEC-05 | The Reinsured may elect disproportionate signing of Reinsurers' lines provided any such variation is made prior to the commencement date. Lines written "to stand" may not be varied without documented agreement. Signed lines may be varied before or after commencement only by documented agreement of the Reinsured and all Reinsurers whose lines are varied. | Both | Signing provisions |
| BR-SEC-06 | Participations are expressed on **two different bases** across the slips — as a percentage of cession and as a percentage of 100%. Both must be recorded explicitly and unambiguously, and each participation must state which basis applies. On the Motor slip GIC Re South Africa is recorded as "17% of cession or 11.90% of 100%". | Both | Written lines |
| BR-SEC-07 | Participations recorded to date — **General:** FM Re Property & Casualty Botswana, written line 30% of 100%, final signed line 29% of 100%, stamped 13 July 2026; PBC Re, 10% of cession, dated 16 July 2026. **Motor:** Continental Re, written line 25% of 100%, final signed line not stated, stamped and dated 13 July 2026; GIC Re South Africa, written line 22.50%, signed line 17% of cession or 11.90% of 100%. | Both | Written lines |
| BR-SEC-08 | Every ceded amount — premium, commission, claim recovery, cash loss, reserve deposit and balance — must be allocated to each Reinsurer in proportion to its participation, and the allocation must reconcile to the total. | Both | Wording Art 10.2 |
| BR-SEC-09 | Contract changes and endorsements are agreed by each Reinsurer in respect of its own participation, handled electronically or via broker visit, email, facsimile or letter. | G | Contract administration |
| BR-SEC-10 | Modes of execution accepted: original ink signature; facsimile or scanned copies of ink-signed documents; electronic signature technology; unique authorisation via a secure electronic trading platform; and timed and dated authorisation via an electronic message or system. | Both | Mode of execution |

---

## 12. Reporting, records and data protection

| ID | Requirement | Applies to | Source |
|---|---|---|---|
| BR-RPT-01 | Reporting must support IFRS 17 treatment of these agreements as **reinsurance contracts held**, with ceded premium and amounts recoverable from reinsurers separately identifiable. | Both | Accounting standard |
| BR-RPT-02 | All reporting must be capable of presentation **per treaty, per underwriting year, per class of business and per reinsurer**. | Both | Wording Art 10.2 |
| BR-RPT-03 | An information pack and statistics are provided to Reinsurers at renewal and are seen and noted by Reinsurers. | Both | Slips, Information |
| BR-RPT-04 | Reinsurers or their appointed representatives may at any time during normal office hours **inspect and take copies** of the Reinsured's records and documents relating to the business covered. This right persists as long as either party has a claim against the other. | Both | Wording Art 15 |
| BR-RPT-05 | **Data protection.** Where personal data is collected, used, disclosed or stored for reinsurance purposes, it must be handled in compliance with applicable data protection legislation, including the Protection of Personal Information Act 4 of 2013. The disclosing party must obtain and document consent for processing and for cross-border transfer, and demonstrate proof of consent on request. Each party must notify the other immediately on becoming aware of a personal data breach. | Both | Wording, Data Protection Clause |
| BR-RPT-06 | Records of risk and claim data are maintained electronically. Alpha Direct maintains records at Bar 2, Floor 2, Botswana Innovation Hub, Plot 69184, Block 8, Gaborone. J.B. Boda and FM Re may also hold data, information and documents electronically. | Both | Slips |

### Company underwriting standards — not treaty-derived

These are Alpha Direct's own standards. They are listed separately so they can be confirmed or removed independently of the treaty requirements.

| ID | Requirement |
|---|---|
| BR-STD-01 | Facultative reinsurance placement is mandatory where sum insured exceeds BWP 50,000,000, and placement must be confirmed before the risk is treated as covered. |
| BR-STD-02 | Reinsurance processing operates on policy numbers, claim numbers, sums insured and amounts only. No insured names, Omang or identity numbers, bank details or residential addresses. |
| BR-STD-03 | No reinsurance figure is published unless it reconciles, reinsurer shares total 100%, the treaty matches class of business and treaty year, and the calculation is in a single currency. |

---

## 13. Open items requiring CFO decision

These could not be resolved from the documents provided. Each affects the numbers.

| # | Item | Why it matters | Proposed resolution |
|---|---|---|---|
| 1 | **Placement does not reach 100% on either treaty.** General shows FM Re signed 29% and PBC Re 10% of cession. Motor shows Continental Re written 25% with no final signed line stated, and GIC Re 17% of cession. | Ceded amounts cannot be allocated to reinsurers, and no statement of account can be rendered, until participations aggregate to 100% of the order. | Obtain the complete signing schedule for both treaties from J.B. Boda. |
| 2 | **Two bases of expressing participations.** "% of cession" and "% of 100%" are both used, and GIC Re is stated on both bases. It is not stated which basis Continental Re's "25% of 100%" and FM Re's "29% of 100%" use. | Reading a "% of cession" figure as a "% of 100%" figure, or the reverse, misstates every reinsurer allocation by a factor of roughly 1.43. | Confirm the basis for each participation in writing. |
| 3 | **Motor slip is not executed by Alpha Direct.** The Reinsured signing page is blank. On the General slip the signature is present (Paul Beka, Operations Manager) but the execution date line is blank. | Treaty terms should not be loaded as authoritative from an unexecuted document. | Complete execution of both signing pages. |
| 4 | **All reinsurer stamps post-date the 1 July 2026 commencement.** FM Re 13 July, PBC Re 16 July, Continental Re 13 July. | The signing provisions address placement not completed by the commencement date. The practical effect on the signed lines should be confirmed. | Confirm with the broker how the signing provisions have been applied. |
| 5 | **Meaning of "Loss Ratio CAP – 75%" on the Motor treaty.** It sits under the commission heading, which suggests it caps the loss ratio used in the sliding scale. It could alternatively be read as capping Reinsurers' claims liability at a 75% loss ratio. | The two readings differ materially. Under the second reading, recoveries would stop once ceded claims reach 75% of ceded premium. | Obtain written confirmation from the Leading Reinsurer. |
| 6 | **Motor sliding scale below 50% loss ratio.** The table begins at a 50% loss ratio with 40% commission. Behaviour below 50% is not stated. | Determines the maximum commission receivable in a good year. | Confirm that 40% is the maximum. |
| 7 | **Motor reserve deposit for foreign reinsurers.** The Motor slip states only "Domestic Reinsurers – Nil", yet Article 9 provides for a reserve deposit against reinsurers not domiciled in Botswana, and GIC Re South Africa is a foreign reinsurer. No percentage or interest rate is specified. | Without a specified rate no reserve can be retained, or the General terms would have to be applied by analogy. | Confirm whether a reserve deposit applies to GIC Re and on what terms. |
| 8 | **No profit commission on the Motor treaty.** The Motor slip provides sliding scale commission only, while Article 7 of the Motor wording refers to "commission and profit commission". | Determines whether a profit commission accrual is required for Motor. | Confirm that no profit commission is payable on Motor. |
| 9 | **General estimated premium income.** 2025/26 was BWP 8,500,000. 2026/27 is BWP 24,872,310, an increase of approximately 193%. | Profit commission and management expenses both key off premium for the underwriting year. | Confirm whether this reflects real growth or a change of basis. |
| 10 | **No excess of loss or catastrophe programme has been supplied.** Reinsurers' liability caps at BWP 70,000,000 per occurrence. | A loss occurrence of BWP 150,000,000 would cede BWP 70,000,000 and leave BWP 80,000,000 net to Alpha Direct. Both wordings expressly permit the Reinsured to protect its net retention. | Confirm whether a catastrophe programme exists for 2026/27. |
| 11 | **Notification and cash loss bases differ between the treaties.** General thresholds are expressed "for 100% of the treaty"; Motor thresholds are expressed "for the ceded portion of the loss". | The same nominal figure triggers at different loss sizes on each treaty. | Confirm the difference is intended. |
| 12 | **Botho University special acceptance is in Lesotho**, which is outside the General territorial scope, and is granted with no facultative inwards restriction. | Requires an explicit override in the territorial validation rather than an exception handled informally. | Confirm the acceptance remains in force for 2026/27. |

---

## 14. Confirmation

| | |
|---|---|
| Requirements confirmed as complete and correct | ☐ |
| Requirements confirmed subject to the corrections marked | ☐ |
| Open items in section 13 resolved | ☐ |

**Name:** ............................................ **Designation:** ............................................

**Signature:** ............................................ **Date:** ............................................

---

*Source documents: J.B. Boda General Quota Share Treaty 2026/27 amended signed slip and contractual wording, executed 16 July 2026; J.B. Boda Alpha Direct Motor Quota Share Reinsurance Slip 2026/27, Continental Re lead signed slip and contractual wording. Companion document: RI-02 — Gap Analysis and Implementation Plan.*
