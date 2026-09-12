---
lastReviewed: 2026-06-03
---
## Overview

The Customer Rewards module lets you manage two core systems: the tier levels customers can achieve based on accumulated points, and the benefits (redeemable rewards) that customers can exchange for points. This page is your central control point for defining what loyalty tiers exist and what rewards they can unlock.

## Who uses this

- **Finance team**: Designs and monitors the reward structure and point economics
- **Customer Service** (CSRs): Reference the tier definitions and benefits when helping customers understand their loyalty status
- **Operations**: Ensures the reward catalog stays current and matches business goals

## Step-by-step

### Managing Reward Tiers

1. Click the **Reward Tiers** tab (appears as the active tab by default)
2. View all existing tiers in the table — each shows Name, Label, Level Points threshold, and Status (green = active)
3. To create a new tier:
   - Click the **+ Add Tier** button in the top right
   - Enter the **Name** (e.g. "Silver", "Gold")
   - Enter the **Label** (display name or short description, optional)
   - Enter the **Points Required** (the point threshold a customer must reach to unlock this tier)
   - Click **Save**
4. To edit a tier:
   - Click **Edit** on the row
   - Update any field (Name, Label, or Points Required)
   - Click **Save**

### Managing Benefits

1. Click the **Benefits** tab
2. View all available benefits in the table — each shows Tag, Type, Points required to redeem, Price (in Pula), and Status
3. To add a new benefit:
   - Click the **+ Add Benefit** button in the top right
   - Enter the **Name / Tag** (e.g. "Free Premium Cover", "Discount Voucher")
   - Enter the **Label / Type** (benefit category or description, optional)
   - Enter the **Points Required** (how many points the customer must spend to claim this benefit)
   - Click **Save**
4. To edit a benefit:
   - Benefits do not have an Edit button in the current interface; use the Admin API or contact engineering if modification is needed

## Field reference

| Field | Meaning | Required? | Notes |
|-------|---------|-----------|-------|
| **Name** (Tier) | Unique identifier for the tier (e.g. "Silver", "Platinum") | Yes | Used internally and in customer communications |
| **Label** (Tier) | Display label or short description of the tier | No | Shown in UI alongside the name for clarity |
| **Level Points** | The point threshold a customer must accumulate to reach this tier | Yes | Must be a whole number; 0 is valid |
| **Name / Tag** (Benefit) | Unique identifier for the benefit (e.g. "Free_Cover", "5_Percent_Discount") | Yes | Used to link benefits to customer redemptions |
| **Label / Type** (Benefit) | Category or description of what the benefit is (e.g. "Insurance", "Discount") | No | Helps CSRs understand the benefit type |
| **Points Required** (Benefit) | How many loyalty points a customer must redeem to claim this benefit | Yes | Must be a whole number; 0 is valid |
| **Price** (Benefit, display only) | The Pula value of the benefit for reporting — read-only in the UI | No | Fetched from the backend; used in analytics |
| **Status** | Whether the tier or benefit is active (green dot = active, gray dot = inactive) | Auto | Defaults to 1 (active) on creation; toggled via backend |

## Tips & gotchas

- **Name is required**: Both tiers and benefits must have a Name/Tag to save. The Save button remains disabled if this field is empty.
- **Points are numeric**: The "Points Required" field is a number input. Non-numeric input is silently converted to 0.
- **No in-app edit for benefits**: The Benefits tab does not show an Edit button. To modify an existing benefit, you must use the backend API endpoint or ask engineering. Tiers can be edited directly in the UI.
- **Status reflects backend state**: The green/gray status dot shows whether the tier or benefit is active. Status is not directly editable from this modal — that control is backend-only. New items default to active (status = 1).
- **Tier ordering**: Tiers appear in the order returned by the backend. There is no manual reordering in the UI.
- **Price display (Benefits only)**: The Price column shows the monetary value (formatted as "P X,XXX.XX" in Botswana Pula). Negative prices may render as "(Credit P X.XX)" depending on system configuration.
- **Concurrent saves**: If two admins save at the same time, the second save will overwrite the first. There is no conflict detection or optimistic locking.
- **Empty list handling**: If no tiers or benefits exist, the table displays empty rows. The page will still load successfully.
- **API errors**: Save failures (e.g. invalid request, server error) show a generic alert. Check the backend logs for detail.

## Related modules

- [Customers](/help/customers) — see customer tier membership and point balances
- [Payments](/help/payments) — track reward redemption transactions
- [Accounting](/help/accounting) — ledger entries for reward point liability
- [Reports](/help/reports) — analyze reward tier distribution and benefit popularity
- [Compliance](/help/compliance) — ensure reward program terms and taxation compliance
