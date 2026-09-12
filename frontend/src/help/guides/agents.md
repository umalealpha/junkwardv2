---
lastReviewed: 2026-06-03
---
## Overview

The Agents & Staff module manages insurance agents, their agencies, roles, permissions, and login activity. Here you can onboard agents, assign them to agencies and departments, control system access (Graphite & reporting), set commission eligibility, and view agent performance metrics.

## Who uses this

- **HR / Admin staff**: Onboard agents, manage agency records, assign agents to departments
- **Compliance & Audit**: Review agent logins and activity tracking
- **Finance**: Verify commission settings and eligibility flags (e.g., bypass 500k limit)
- **Operations managers**: Check agent portfolio performance (active policies, commission earned)

## Step-by-step

### Add a new agent
1. Click **+ Add Agent** on the Agents tab
2. Fill in the form:
   - **First Name** and **Last Name** (required)
   - **Email** (required)
   - **Password** and **Confirm Password** (required for new agents only; grayed out when editing)
   - **Agency** (required dropdown; must select one)
   - **Department** (optional dropdown)
   - **Role(s)** (optional multi-select checkboxes)
   - Personal details: Mobile No., Date of Birth, OMANG, Address, Passport, Gender (all optional)
   - **PIN** (4-digit code; optional but validated server-side if provided)
3. Check feature toggles as needed:
   - **Commission Enabled**: Agent can earn commissions
   - **Graphite Login**: Agent can log into the main admin portal (this system)
   - **Report Login**: Agent can access reporting tools
   - **Bypass 500k**: Agent can process policies above 500k limit
4. Click **Save**
   - If the server returns validation errors (422 status), they will display in red with field hints
   - On success, modal closes and the agents list refreshes

### Edit an agent
1. Click **Edit** on any row in the agents table
2. Modal opens with agent's current details (password fields hidden; you can add a new password if resetting)
3. Update any field and click **Save**
4. The agent detail page loads their full profile (email, phone, dates, roles, department)

### View agent details
1. Click the **agent name link** (blue text) on the agents list
2. Displays a summary card with:
   - Email, phone, DOB, OMANG, account type, join date
3. Shows performance stats:
   - Active Policies, Total Policies, Cancelled, Retention Rate (%), Commission Earned, Commission Paid

### Manage agencies
1. Navigate to the **Agencies** tab
2. View all agencies: ID, name, status (green dot = active), agent count, total premium
3. Click **+ Add Agency** to create:
   - Enter agency **Name** (required)
   - Check **Active** checkbox (status defaults to 1 = active)
4. Click **Edit** on any agency to update name or status
5. Click **Save**

### Create and assign roles
1. Navigate to the **Roles & Permissions** tab
2. Click **+ Add Role**
3. Enter a **Role Name** (required)
4. Under **Permissions** tab (visible on edit):
   - Permissions are grouped by module (e.g., AGENTS, POLICIES, CLAIMS)
   - Check the module header to select/deselect all in that group
   - Or check individual permissions within each module
   - Count shown: "Permissions (N selected)"
5. On edit mode only — switch to **Roles Under Roles** tab:
   - Select which other roles can be assigned to users by this role (role hierarchy)
6. Click **Save**

### View role assignment counts
- The Roles table shows each role's name, permission count, and how many users have that role
- Edit a role to see which permissions it includes or which roles it supervises

### Track agent logins
1. Navigate to the **Agent Logins** tab
2. Search by agent name or email (optional search box)
3. Table shows:
   - Agent ID and Name
   - Store ID (if assigned)
   - Login Date & Time (formatted as local date + time, or "—" if never logged in)
   - Last Activity (formatted similarly)
4. Pagination shows total count and current page range
5. Results are 25 per page; use Previous/Next to navigate

## Field reference

| Field | Meaning | Required | Notes |
|-------|---------|----------|-------|
| First Name | Agent's given name | Yes (add only) | Trimmed on save |
| Last Name | Agent's family name | Yes (add only) | Trimmed on save |
| Email | Email address for login | Yes | Must be valid, checked server-side |
| Password | Initial password (add) or new password (edit) | Yes (add only) | Min 8 chars (server validates) |
| Confirm Password | Must match Password | Yes (add only) | Only validated on create |
| Agency | Which agency employs the agent | Yes | Dropdown loaded from `/lookups/agencies` |
| Department | Operational department assignment | No | Optional dropdown |
| Role(s) | Permissions group(s) | No | Multi-select checkboxes; can assign 0+ roles |
| Mobile No. | Phone number | No | Free text |
| Date of Birth | Birth date | No | HTML date picker (YYYY-MM-DD) |
| OMANG | National ID (Botswana) | No | Max 9 characters |
| Address | Physical address | No | Free text |
| Passport | Passport number | No | Free text |
| Gender | Male or Female | No | Dropdown with two options |
| PIN | 4-digit security code | No | Max 4 characters; server validates format if provided |
| Commission Enabled | Toggle commission eligibility | No (default false) | Controls whether agent earns commission |
| Graphite Login | Toggle Graphite portal access | No (default false) | Agent can log in to this admin system |
| Report Login | Toggle reporting portal access | No (default false) | Agent can log in to reports/analytics |
| Bypass 500k | Toggle 500k policy limit bypass | No (default false) | Agent can write policies above 500,000 Pula |

## Tips & gotchas

- **Password on edit:** When editing an agent, password fields are hidden. Leaving them blank keeps the existing password. Only fill in password fields if you want to reset it.
- **Email validation:** Email must be unique and match standard format; server returns 422 validation error if it fails.
- **PIN validation:** PIN must be exactly 4 digits if provided; server will reject non-numeric or wrong-length values.
- **Agency is mandatory:** An agent cannot be saved without an agency assignment. The dropdown is required and will prevent save if empty.
- **Commission earned vs. paid:** Commission Earned = total commission calculated for agent; Commission Paid = amount actually disbursed. These may differ if payments are pending.
- **Retention Rate:** Displayed as a percentage (%) based on (Total Policies - Cancelled) / Total Policies.
- **Login tracking:** Agent Logins tab records login timestamp and last activity timestamp. Agents with no login history show "—" for both fields.
- **Search is real-time:** In Agents list, typing in the search box triggers a new query immediately; pagination resets to page 1.
- **Role permissions:** Permissions are stored as strings (e.g., "agents-read", "policies-write") and are checked by module. When editing a role, "Roles Under Roles" only appears if the role already exists (i.e., not on create).
- **Agency status:** Status is a toggle (1 = active, 0 = inactive) but there is no enforcement preventing creation of inactive agencies. A green dot in the table indicates active status.
- **Module headers and bulk select:** In Roles, clicking the module header checkbox selects or deselects all permissions in that module at once. Partially checked modules show as checked if any permissions are selected (toggle state reverses based on "all selected" logic).
- **Pagination pages persist search:** When searching agents, page 1 is forced on first search keystroke; subsequent pagination respects your search query.

## Related modules

- [Customers](/help/customers) — Customer master data and 360 views
- [Policies](/help/policies) — Policy management; agents author and manage policies
- [Commission](/help/commission) — Commission calculations and payouts (uses agent commission settings)
- [Access Control](/help/access-control) — User permissions and role assignments across the system
- [Audit Trail](/help/audit-trail) — System-wide login and action logs (includes agent access)
- [Regions & Departments](/help/regions-departments) — Department and regional structure (agents assigned here)
