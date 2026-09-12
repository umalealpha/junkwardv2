---
lastReviewed: 2026-06-03
---
## Overview

The Products & Plans module lets you manage insurance products and their pricing plans. From the product list, toggle which products appear on the public start.alphadirect.co.bw website. Drill into any product to view and edit its coverage plans, sum assured amounts, premiums, and rating factors.

## Who uses this

Product managers, underwriters, and pricing specialists who need to control product visibility and configure plan details (coverage levels, premium amounts, and rating factors).

## Step-by-step

### View all products

1. Navigate to **Products** in the main menu.
2. The product list displays as a table with columns: ID, Name, Type, Flags, Status, and "Show on start site".
3. Products marked **Active** have a green badge; **Inactive** products appear gray.
4. The **Type** column shows the product category (e.g., motor, life); **Flags** show applicable attributes (vehicle, member, or both).

### Control product visibility on the public website

1. In the product list, locate the toggle switch under **Show on start site** for the product you want to manage.
2. Click the toggle to switch ON (orange) or OFF (gray).
3. When you toggle, the product's visibility updates immediately. The backend invalidates its cache so the public website reflects the change on the next request.
4. A loading state (reduced opacity) appears while the toggle is being saved; do not click again until it completes.

### View product details

1. From the product list, click the product **Name** to open its detail page.
2. You'll see a header showing the product name and its current status (Active/Inactive).
3. Below the header is an info card displaying: Product ID, Slug, KYC Compliance Rule, and Product Type ID.

### Add a new plan to a product

1. On the product detail page, scroll to the **Plans** section.
2. Click the **+ Add Plan** button in the top-right corner of the Plans card.
3. A modal dialog opens with four fields:
   - **Plan Name** (required text)
   - **Sum Assured** (numeric; in Pula currency)
   - **Premium** (numeric; in Pula currency)
   - Status (defaults to 1 = Active)
4. Fill in the fields and click **Save**. The modal closes and the plans table refreshes.
5. If the plan name is blank, the Save button is disabled and cannot be clicked.

### Edit an existing plan

1. On the product detail page, find the plan in the **Plans** table.
2. Click **Edit** (blue link) at the end of the plan row.
3. The same modal opens with the plan's existing values pre-filled.
4. Make your changes and click **Save**.
5. The modal closes and the table refreshes with the updated values.

### View rating factors

1. If the product has rating factors configured, a **Rating Factors** section appears below the Plans section.
2. Each factor is listed by name with a set of values shown as gray tags (e.g., age brackets, vehicle make).
3. This section is read-only in the UI; editing factors requires backend changes.

## Field reference

| Field | Where | Type | Required | Notes |
|-------|-------|------|----------|-------|
| Product ID | Product list & detail page | Read-only (integer) | N/A | Assigned by system |
| Name | Product list & detail page | Read-only (text) | N/A | Set on product creation |
| Type | Product list (hidden on mobile) | Read-only (text) | N/A | E.g., motor, life, health |
| Flags | Product list (hidden on <1024px) | Read-only (text) | N/A | Comma-separated: "vehicle", "member" |
| Status | Product list & detail page | Read-only badge | N/A | 1 = Active (green), 0 = Inactive (gray) |
| Show on start site | Product list | Toggle switch | No | Controls whether product appears on start.alphadirect.co.bw; updates on change |
| Slug | Detail page info card | Read-only (text) | N/A | URL-friendly product identifier |
| KYC Compliance Rule | Detail page info card | Read-only (text) | N/A | Compliance rule applied to this product (default: "None") |
| Product Type ID | Detail page info card | Read-only (integer) | N/A | Numeric identifier linking to product type |
| Plan Name | Add/Edit Plan modal | Text input | Yes | Name of the coverage plan (e.g., "Basic", "Premium") |
| Sum Assured | Add/Edit Plan modal | Number input | No | Coverage amount in Pula; formatted as currency in table |
| Premium | Add/Edit Plan modal | Number input | No | Price in Pula; formatted as currency in table |
| Plan Status | Add/Edit Plan modal | Status | No | 1 = Active (green dot), 0 = Inactive (gray dot) |

## Tips & gotchas

- **Visibility toggle caches the public site**: Toggling "Show on start site" invalidates server caches (TAG_PRODUCTS and TAG_LOOKUPS), so the public catalogue refreshes on the next user request. Allow a few seconds for the change to propagate.
- **Plan save requires a name**: The Save button in the Add/Edit Plan modal is disabled (grayed out) if the Plan Name field is empty. You must fill it before saving.
- **Numeric fields accept zero**: Sum Assured and Premium both accept 0 as a valid value; they are converted to numbers server-side, so empty inputs default to 0.
- **Optimistic UI updates on toggle**: The product visibility toggle shows the new state immediately, then syncs with the server. If the save fails, the UI rolls back to the previous state and an error alert appears.
- **Toggle is disabled while saving**: While a product's visibility toggle is being saved, it is disabled (reduced opacity, no-click cursor). Wait for the state change to finish before interacting with other toggles.
- **Plan status is hidden in list view**: The status of a plan appears only as a small colored dot (green or gray) in the table; to change it, you must edit the plan. The status field is not labeled in the modal, only shown as a default.
- **Rating factors are read-only in UI**: If a product has rating factors, they are displayed as a reference list but cannot be edited via this page. Modifications require backend or a separate admin interface.
- **Product list shows limited columns on mobile**: The Type and Flags columns are hidden on screens smaller than 768px and 1024px respectively; product visibility toggle is always visible.
- **No bulk actions**: Each product and plan must be edited individually; there is no batch update, import, or delete function in this module.

## Related modules

- [Policies](/help/policies) — View and manage customer insurance policies.
- [Coverage Management](/help/coverage-management) — Manage coverage details and attributes.
- [Underwriting](/help/underwriting) — Review and approve new business applications.
- [Quotes](/help/quotes) — Generate and manage quotations linked to product plans.
- [Compliance](/help/compliance) — Monitor KYC rules and regulatory requirements applied to products.
