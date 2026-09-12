---
lastReviewed: 2026-06-03
---
## Overview

The Access Control module manages who can log in and what they can do. This includes creating and managing staff accounts (user account activation/deactivation), assigning roles to those accounts, defining permissions within each role, and mapping validation rules to roles that control policy submission gates.

## Who uses this

- **System admins** — create new staff, manage role definitions and permissions (accessed via **Roles & Permissions** tab)
- **Operations leads** — manage staff account status, assign roles to individuals (accessed via **Staff** tab)
- **Underwriting leads** — set which validation rule groups apply to each role (accessed via **Role → Rule Group Assignment** tab)

## Step-by-step

### Managing Staff Accounts

1. Navigate to **Users** → **Staff**
2. Use the search box and filters (Role, Agency, Status) to find staff, or leave blank to see all
3. Click **+ Create Staff** to add a new staff member
   - Enter **First Name**, **Last Name**, and **Email**
   - Set a temporary **Password** (required for new accounts)
   - Leave **Role** blank (optional), or select one from the dropdown
   - Select an **Agency** if applicable (optional)
   - Set **Status** to Active, Inactive, or Suspended (defaults to Active)
   - Click **Save**
4. To edit an existing staff member:
   - Click the **Edit** button in the Actions column
   - Update First Name, Last Name, Email, or Status as needed
   - Change the **Password** field only if you want to reset it (leave blank to keep existing)
   - Click **Save**
5. To toggle a staff member's status between Active and Inactive, click the **Deactivate** or **Activate** button in the Actions column

### Managing User Roles

1. Navigate to **Agents** → **Roles & Permissions**
2. You see a table of all roles with permission counts and user counts
3. To **create a new role**:
   - Click **+ Add Role**
   - Enter the **Role Name**
   - Under the **Permissions** tab, browse modules and check the boxes for permissions you want to grant (you can also check the module header to select all permissions in that module at once)
   - Click **Save**
4. To **edit an existing role**:
   - Click the **Edit** button next to the role name
   - Update the **Role Name** if needed
   - Switch to the **Permissions** tab to add or remove individual permissions
   - If the role is used elsewhere, also check the **Roles Under Roles** tab to control which other roles this role can assign to staff
   - Click **Save**

### Assigning Roles to Staff

1. Navigate to **Users** → **Staff**
2. Click **Edit** on the staff member
3. In the modal, locate the **Roles** section (appears below the Agency field in edit mode)
4. To **add a role**:
   - Use the **+ Add role…** dropdown at the bottom of the Roles box
   - Search or scroll to find the role name (especially useful for long grade-role names)
   - Click the **Add** button
   - The role appears as a chip — blue for functional roles, orange for **grade roles** (which drive validation-rule behaviour during policy submission)
5. To **remove a role**:
   - Click the **×** on the role chip
   - Confirm in the popup
   - The role is removed immediately; other roles remain unchanged
6. Click **Save** when done

### Mapping Roles to Validation Rule Groups

1. Navigate to **PolicyValidation** → **Role → Rule Group Assignment**
2. You see a table of roles with their current validation rule group assignment
3. For each role, use the **Assign Group** dropdown (right column) to select or change the validation rule group
   - The dropdown shows all available groups with their code and description (e.g., "U-3 — Standard underwriting")
   - Clearing the selection removes the group binding (staff with that role submit policies with no per-role validation gate)
4. Selection is saved immediately; no extra Save button needed

## Field reference

| Field | Meaning | Required |
|-------|---------|----------|
| First Name | Staff member's given name | Yes (create only) |
| Last Name | Staff member's family name | Yes (create only) |
| Email | Staff member's login email address | Yes |
| Password | One-time login password for new accounts; optional on edit to reset existing | Yes (create only) |
| Role | Primary functional role on creation (blue chip); optional | No |
| Agency | Filters and scopes user operations; optional | No |
| Status | Active, Inactive, or Suspended; controls login ability | Yes (defaults to Active) |
| Role Name | Unique name for a role definition | Yes (roles) |
| Permissions | Granular actions in modules (e.g., "Users-create", "Claims-edit") | No (can be empty) |
| Roles Under Roles | Which other roles this role can assign to staff | No |
| Rule Group | Validation rule set bound to a role; gates policy submission transitions | No |

## Tips & gotchas

- **Role model:** Roles and permissions are stored separately. At the **Staff** level, you assign *instances* of roles to individuals. When you edit a role's permissions or sub-roles, changes apply to all staff who hold that role immediately.
- **Grade roles vs. functional roles:** Grade roles (shown in orange chips) are special roles used by the policy validation engine; their `rule_group` field is read during Submit-to-Approval transitions. Other blue-chip roles are functional only and have no validation-rules side effect. Don't mix them up when assigning.
- **Under-roles restriction:** If a role has no entries in its **Roles Under Roles** list, staff holding that role cannot assign any subordinate roles to others. This is a gating mechanism to enforce role hierarchy.
- **Password management:** New staff require a password at creation time. On edit, the password field is optional; leaving it blank keeps the existing password. There is no password reset UI in this module—staff must use a separate password-recovery flow (e.g., SSO or a login-page "Forgot Password" link).
- **Status transitions:** Toggling between Active/Inactive uses a dedicated endpoint. Suspended status exists but there is no Suspend button; use the Status dropdown in the edit modal if you need to move a staff member into Suspended state.
- **Validation rule gates:** The **Role → Rule Group Assignment** page is read-only for the role definitions themselves; it only controls which validation rule set each role's submissions are checked against. To change rules within a group, go to **PolicyValidation** → **Validation Groups**.
- **Multiple roles per staff:** A single staff member can hold multiple roles simultaneously. Adding one does not remove others. This is useful for temporary cross-functional assignments.
- **Additive role endpoints:** When editing a staff member's roles, the UI uses add/remove operations rather than a replace-all API. This means if multiple admins edit at the same time, the final set is a merge, not an overwrite.
- **Role-user bidirectional counts:** The Roles & Permissions table shows a user count for each role. If a role has 0 users, you can still keep it defined (e.g., for future use), but it will not appear in Staff assignment dropdowns until at least one user holds it.

## Related modules

- [Compliance](/help/compliance) — audit trail and consent consent compliance
- **Policy Validation** — validation rules and rule groups that roles can be bound to (managed within this module)
- [Audit Trail](/help/audit-trail) — logged changes to staff and role configurations
