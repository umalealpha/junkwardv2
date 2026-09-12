---
lastReviewed: 2026-06-03
---
## Overview

The AD Group Processing module manages employer groups and their associated group insurance policies. CSRs and administrators use this section to maintain employer records, monitor group policy enrollment, and batch-create group policies from file uploads.

## Who uses this

* **Underwriting team** — manages employer group enrollment and policy status
* **CSR/Operations** — updates employer contact information and processes batch uploads
* **Finance/Premium management** — reviews group policies and premium tracking
* **Administrators** — batch-creates group policies via template upload

## Step-by-step

### View and Manage Employer Groups

1. Navigate to **Employer Groups** page.
2. Optionally search by group name, contact person, or employer ID in the **Search** field.
3. Filter by **Status** (All Status, Active, Inactive, Pending).
4. Results display 25 records per page. Use **Prev**/**Next** buttons or the **Go to** page number box to navigate.
5. Review group details: Group ID, Name, Industry, Contact Name, Phone, Email, Employee Count, Status, and Creation Date.

### View Group Policies

1. Navigate to **AD Group Policies** page.
2. Optionally search by policy number, customer name, or employee ID.
3. Filter by **Employer Group ID** to show only policies from a specific employer group.
4. Results display 25 records per page. Pagination controls work as above.
5. Review policy details: Policy Number (clickable to view full policy), Employee ID, Customer, Group Name, Product, Premium (in Pula), Status, and Creation Date.
6. Status indicators: Inactive (gray), Active (green), Cancelled (red), or Expired (yellow).

### Batch Create Group Policies

1. Navigate to **AD Group Batch Create** page.
2. Click **Download Template** to obtain the batch creation template file.
3. Open the downloaded file (.xlsx, .xls, or .csv) and fill in:
   * Employee details (name, ID, contact info)
   * Group information (group name, contact, industry, employee count)
   * Policy data (product, premium, effective date)
4. Save the completed file.
5. Upload by one of:
   * Dragging the file into the dashed box, or
   * Clicking the box to browse and select the file.
6. Once a file is selected, a green checkmark appears with the filename and file size.
7. Click **Upload & Process** to submit.
8. A success message will confirm upload, and processing begins in the background.
9. Check the **Batch Report** page for detailed processing results and any errors.

## Field reference

| Field | Where | Meaning | Required |
|-------|-------|---------|----------|
| Group ID | Employer Groups table | Unique identifier for the employer group | No |
| Name | Employer Groups table | Employer or group legal name | No |
| Industry | Employer Groups table | Industry classification (e.g., Manufacturing, Retail) | No |
| Contact Name | Employer Groups table | Primary contact person for the group | No |
| Contact Phone | Employer Groups table | Phone number of primary contact | No |
| Contact Email | Employer Groups table | Email address of primary contact | No |
| Employees | Employer Groups table | Total number of employees in the group | No |
| Status | Employer Groups, filter | Group status: Active, Inactive, or Pending | No |
| Policy Number | AD Group Policies table | Unique identifier for each group policy | No |
| Employee ID | AD Group Policies table; Batch template | Unique ID assigned to the employee within the group | No |
| Customer | AD Group Policies table | Name of the customer/policyholder | No |
| Group Name | AD Group Policies table | The employer group the policy belongs to | No |
| Product | AD Group Policies table; Batch template | Insurance product type (e.g., Life, Medical) | No |
| Premium | AD Group Policies table; Batch template | Monthly or annual premium amount in Pula | No |
| Status | AD Group Policies table | Policy status: Active, Inactive, Cancelled, or Expired | No |
| Date | Both tables | Creation or effective date of the record | No |
| Employer Group ID | AD Group Policies filter | Filter policies by a specific employer group ID | No |

## Tips & gotchas

* **Template file formats**: The batch upload accepts only .xlsx, .xls, or .csv files. Other formats will be rejected.
* **Async processing**: Once a batch file is uploaded, the system validates and creates policies in the background. Results and errors appear on the Batch Report page, not immediately on screen.
* **File validation**: If the upload fails, an error message displays on the page explaining the issue. Common causes are missing required columns or invalid data format.
* **Status lifecycle**: Employer groups can have status "Pending" while being onboarded, and transition to "Active" or "Inactive" once finalized.
* **Policy status mapping**: Group policies have four status states (numeric codes 0–3): Inactive (0), Active (1), Cancelled (2), Expired (3). Expired policies do not renew; Cancelled policies are terminated by the group.
* **Search and filter behavior**: Search and status filters reset pagination to page 1 when applied. Use exact IDs for fastest filtering.
* **Pagination jump**: The **Go to** page box allows direct navigation; typing an invalid page number (outside the valid range) has no effect.
* **Contact field truncation**: Long contact emails and group names are truncated in the table view; hover over them to see the full text.
* **No inline editing**: This module displays records only; group and policy details are not editable from these tables. Updates may require direct data admin access or API calls.

## Related modules

* [Policies](/help/policies) — manage individual and group policies
* [Batch Processing](/help/batch-processing) — general batch job monitoring and error handling
* [Customer KYC](/help/customer-kyc) — customer verification and onboarding
* [Underwriting](/help/underwriting) — policy approval and risk assessment
* [Excel Imports](/help/excel-imports) — file upload and template management
