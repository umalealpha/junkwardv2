---
lastReviewed: 2026-09-10
---
## Overview
The Customer KYC module is where compliance teams review and approve customer identity and corporate documents for Know Your Customer (KYC) regulatory requirements. Each customer's submission gets a status (Pending, Approved, Rejected) and compliance rating, and reviewers verify documents individually before granting overall approval.

## Who uses this
- **KYC Approvers** (role *KYC Approver*, plus Super Admins): the only people who can verify documents and approve or reject a customer's KYC. Since 10 September 2026 this is a named list managed through Roles & Permissions; the approve, reject and Verify Document buttons are shown to them only.
- **Compliance Officers & KYC Reviewers**: View submissions, documents, compliance status and the activity log
- **Operations & Underwriting**: Check KYC status before policy activation
- **Admin**: Configure KYC compliance rules and document requirements per product (the Admin role does not by itself grant approval rights; Super Admin does)

## Step-by-step

### Main KYC List
1. Go to the **Customer KYC** list to view all customers with pending or completed KYC status.
2. **Filter** customers by:
   - **Search** — customer/company name, phone, email, or policy number
   - **Status** — Approved, Pending, or Rejected
   - **Compliance** — Compliant or Non-compliant
   - **Tier** — MIS (individual KYC), DOMG (domestic corporate), or COMG (commercial corporate)
3. View the **Documents** column to see which supporting files have been uploaded (checkmarks for Omang, Passport, Driving License, Proof of Residence, Proof of Income).
4. Click a customer name to open their detailed review page.

### Review MIS Tier Customer (Individual KYC)
1. Navigate to a customer's detail page (routes to `/kyc/:customerId`).
2. Review the customer profile, ID numbers (Omang, Passport), and linked policies.
3. Go to the **Documents** tab to see uploaded identity documents.
4. For each document (KYC Approvers only; other users see the document and its status without controls):
   - Click **Verify Document** to open the card controls.
   - Add a **Remark** (optional).
   - Click **Approve**, **Reject**, or **Reset** to set the status.
   - Click **Cancel** to discard changes.
5. After reviewing all documents, scroll to the **Overall Decision** section (KYC Approvers only; other users see the current verdict and a read-only note):
   - Add a **Remark** (optional) to explain the decision.
   - Click **Approve KYC** or **Reject KYC**.
   - If already approved, the button reads **Re-approve KYC**; use **Reject KYC** to flip the decision.
6. View the **Activity Log** tab to see historical approvals and rejections.

### Review DOM/COM Tier Customer (Corporate KYC)
1. Navigate to a customer's detail page. If they hold only domestic or commercial policies, the page routes to `/kyc/dom-com/:customerId`.
2. Review the customer profile and **DOM/COM Status** panel (Compliance, Directors/Shareholders ID expiry dates, and Approved Date).
3. Go to the **Documents** tab to see two grouped sections:
   - **Identity Documents** (shared individual KYC docs from the base MIS flow)
   - **Corporate/Entity Documents** (KYC Form, Data Protection Form, Certificate of Incorporation, Director/Shareholder IDs, etc.)
4. For each document (KYC Approvers only):
   - Click **Verify Document**.
   - Add a **Remark** (optional).
   - If the document has an expiry date field, enter the **Date of Expiry** (required for certain documents).
   - Click **Approve**, **Reject**, or **Reset**.
5. In the **Overall Decision** section (KYC Approvers only):
   - Add a **Reason/Remark** (optional).
   - Click **Approve KYC** or **Reject KYC** (always enabled; no preconditions).
   - If rejecting an already-approved KYC, the button shows **Reject KYC** to flip the verdict.
6. Check the **Additional Uploads** tab to view policy-specific director/shareholder documents (BizSure multi-director uploads).
7. View the **Activity Log** tab to track approvals and rejections.

### Configure KYC Compliance Rules
1. Go to **KYC Compliance Rules** admin page.
2. Toggle **Enable Mati** to activate/deactivate the Mati KYC verification service globally.
3. Click **+ Add Rule** to create a new compliance rule.
4. In the modal:
   - Enter a **Rule Name** (e.g., "Motor Comprehensive KYC").
   - (Optional) Enter a **Mati Flow ID** to link to a Mati verification workflow.
   - **Assign to Products** — check the MIS products (1–6, 9, 10) that require this rule.
   - **Document Requirements** — set each KYC field as Mandatory, Mandatory + Alternative, or Optional.
   - Click **Save**.
5. To edit a rule, click **Edit** on an existing rule card, change settings, and save.
6. To delete a rule, click **Delete**, confirm in the dialog.

### Re-KYC Management
1. Go to **Re-KYC Management** to see customers due for periodic re-verification.
2. **Search** by customer name, phone, or email.
3. View the compliance status and re-KYC campaign status for each customer.
4. Re-KYC customers follow the same review process as the main KYC list (click a customer to open their detail page).

## Field reference

| Field | Meaning | Required | Notes |
|-------|---------|----------|-------|
| **Search** | Customer/company name, phone, email, or policy number | No | Filters KYC list on Enter or blur |
| **Status** | KYC approval state | No | Approved, Pending, Rejected. List filters default to all. |
| **Compliance** | Regulatory compliance rating | No | Compliant (1), Non-compliant (2), or Pending (no value yet) |
| **Tier** | Policy product category | No | MIS = individual, DOMG = domestic corporate, COMG = commercial corporate |
| **Remark** | Text explanation for approval/rejection decision | No | Stored per document and per overall KYC submission |
| **Date of Expiry** | Expiration date for identity documents (DOM/COM only) | Conditional | Only for documents with an expiry field (e.g., Directors ID, Passport). Format: YYYY-MM-DD. |
| **Document Status** | Review result per uploaded file | No | Pending (0), Approved (1), Rejected (2). Set individually before overall approval. |
| **Overall KYC Status** | Final approval/rejection decision | No | Approved, Rejected, or Pending. Independent of individual document statuses (can approve overall even if one doc is pending). |

## Tips & gotchas

- **Tier routing is automatic**: MIS customers (MIS products only) use `/kyc/:customerId`. DOMG/COMG customers use `/kyc/dom-com/:customerId`. Customers with both MIS and DOM/COM policies see both review pages, with a banner linking between them.

- **DOM/COM has no preconditions**: Unlike MIS, the Approve/Reject buttons on the DOM/COM detail page are always enabled, even if documents are pending or rejected. Reviewers can flip a verdict at any moment regardless of document state (per ops request 2026-06-03).

- **Expiry dates are optional by document**: Only documents with an `expiryField` value in the backend show the **Date of Expiry** input. Common expiry documents: Directors ID, Directors Passport, Shareholders ID. The MIS flow does not use expiry tracking.

- **Compliance rules apply to MIS only**: The KYC Compliance Rules admin page is scoped to MIS products (1–6, 9, 10). DOM/COM compliance is managed separately. Rules define which documents are mandatory (with optional alternatives) per product.

- **Mati integration is global**: The **Enable Mati** toggle is a system-wide setting that gates whether downstream compliance emails surface Mati upload links. Changing it affects all KYC customers immediately.

- **Activity log is audit trail**: Every approval, rejection, and document-level change is logged with the reviewer name, timestamp, and decision reason. Remarks are preserved in the log.

- **Re-KYC campaigns are time-based**: The Re-KYC page lists only customers whose periodic re-verification date has arrived. It uses the same underlying KYC list API but filters by campaign triggers (not user-selectable).

- **Document uploads are not shown in the list**: The main KYC list shows checkmarks for document presence but does not display the actual files. Open the detail page to view and verify the uploaded documents.

- **Batch status updates are unavailable**: The list page does **not** offer bulk approve/reject. Each customer must be individually reviewed and approved on their detail page.

- **Deleted compliance rules unassign products**: When a compliance rule is deleted, all products linked to it lose their compliance check. The KYC cron will no longer enforce document requirements for those products.

## Related modules
- [Customers](/help/customers)
- [Policies](/help/policies)
- [Compliance](/help/compliance)
- [Audit Trail](/help/audit-trail)
