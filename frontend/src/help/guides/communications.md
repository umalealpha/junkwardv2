---
lastReviewed: 2026-06-03
---
## Overview
The Communications module manages all notification templates and delivery logs across email, SMS, WhatsApp, and in-app channels. Admins use it to configure what messages go where and monitor whether notifications actually reach customers.

## Who uses this
- **System Admins**: Set up notification templates and troubleshoot delivery failures
- **Operations Teams**: Monitor the live log to verify notifications are being sent
- **Finance/Compliance**: Audit communication trails for regulatory records

## Step-by-step

### Managing system event templates
This page controls what gets sent when major events occur (policy activated, claim filed, payment received, etc.). The NotificationDispatcher system reads these templates to compose all outgoing alerts.

1. Go to **Communications → Notification Templates**
2. Click **+ Add Template** to create a new event handler
3. In the modal:
   - **Event Type** (required): Identifier for the system event that triggers this (e.g., `policy_activated`, `claim_approved`). Cannot be changed after creation
   - **Name** (required): Human-friendly label (e.g., "Policy Activated")
   - **Channels**: Tick which channels should receive this notification (In-App, Email, SMS, WhatsApp). Multiple can be active
   - For each channel enabled:
     - **Email Subject** / **Email Body**: HTML supported. Use `{{variable}}` placeholders
     - **SMS Body**: Plain text, 160–200 character limit recommended
     - **WhatsApp Body**: Plain text
   - **Variables** (comma-separated): List the template placeholders (e.g., `policy_number, customer_name, amount`). These must match `{{placeholders}}` in content
   - **Active** checkbox: Enable/disable without deleting
4. Click **Save**. Only `Name` and `Active` can be edited afterwards; `Event Type` is locked

**Deletion**: Click **Delete** next to the template. Confirm. Cannot undo; ensure no live event handlers reference it.

### SMS and Email templates
This simpler list manages reusable message templates for manual/marketing use, not tied to system events.

1. Go to **Communications → SMS/Email Templates**
2. Click the **SMS** or **Email** tab to switch
3. Click **+ Add Template**
4. In the modal:
   - **Name** (required): Template identifier
   - **Subject** (Email only): Email header
   - **Content/Body** (required): Message text. Email supports HTML
   - **Active** toggle: Enable/disable
5. Click **Save**

**Edit**: Click **Edit** next to any template in the table
**Delete**: Click **Delete**, then confirm

### WhatsApp templates
WhatsApp has stricter rules. Templates must be submitted to Meta for approval before they can be used. Only approved templates bypass the 24-hour customer-service window — essential for anomaly alerts and unsolicited notifications.

1. Go to **Communications → WhatsApp Templates**
2. Check the yellow banner — if it says "Service not configured," ensure the backend has `WHATSAPP_BUSINESS_ACCOUNT_ID` set in env. Find your WABA ID in Meta Business Manager → WhatsApp Accounts → Account ID
3. Click **+ Submit New Template** (disabled if service is not configured)
4. In the modal:
   - **Name** (required): Lowercase, underscores only. Meta rejects mixed case. Example: `graphite_anomaly_alert`
   - **Category**: UTILITY (transactional/alerts), MARKETING (promotional), or AUTHENTICATION (OTP). UTILITY templates are auto-reviewed within minutes
   - **Language**: ISO code (e.g., `en`, `fr`, `zu`). Each language is a separate submission
   - **Body text**: Plain text. Use `{{1}}`, `{{2}}`, `{{3}}` for placeholders. Example template:
     ```
     ⚠️ Graphite anomaly: {{1}}
     Severity: {{2}}
     Time: {{3}}
     ```
5. Click **Submit to Meta**. Template moves to `PENDING` status
6. Meta auto-reviews UTILITY templates within minutes. Check back for `APPROVED` or `REJECTED` status
7. If rejected, the reason appears below the status

**Test an approved template**:
1. Find the template with status `APPROVED`
2. Click **Test**
3. In the modal:
   - **Recipient**: Full phone number with country code, no `+` (e.g., `917276312582`)
   - **Body parameters**: Pipe-separated values in order, matching placeholders (e.g., `DPO ServiceRef mismatch | high | 14:32` for `{{1}}|{{2}}|{{3}}`)
4. Click **Send test**. Response shows message ID or error

**Delete**: Click **Delete** next to any template. Meta deletion is final and affects all language variants

### Notification delivery logs
See every notification the system has ever attempted to send — useful for troubleshooting delivery and auditing.

1. Go to **Communications → Notification Delivery Logs**
2. Filter by:
   - **Channel**: In-App, Email, SMS, WhatsApp, or all
   - **Status**: Sent, Dispatched, Failed, Skipped, or all
   - **Type**: Free-text search by event type (e.g., `policy_activated`)
3. Results show:
   - **ID**: Internal log entry ID
   - **Type**: Event type that triggered this
   - **Channel**: Which channel was used
   - **User**: Who the notification was sent to
   - **Status**: Sent (delivered), Dispatched (queued), Failed (error), Skipped (intentionally not sent)
   - **Reason**: If failed or skipped, why (hover over truncated text to see full reason)
   - **Message**: Preview of the notification content
   - **Time**: UTC timestamp
4. Click **Refresh** to reload the table
5. Navigate pages with **Previous** / **Next** (50 entries per page)

## Field reference

| Field | Meaning | Required |
|-------|---------|----------|
| Event Type | System event identifier (e.g., `policy_activated`) | Yes |
| Name | Human label for the template | Yes |
| Channels | Which outlets to notify (In-App, Email, SMS, WhatsApp) | No (defaults to In-App + Email) |
| Email Subject | Subject line for email notifications | No |
| Email Body | Email content; HTML allowed; use `{{variable}}` placeholders | No |
| SMS Body | SMS message text | No |
| WhatsApp Body | WhatsApp message; use `{{1}}`, `{{2}}` for params | No |
| Variables | Comma-separated list of data placeholders to inject | No |
| Active | Enable/disable template without deleting | No (default true) |
| Category (WhatsApp) | UTILITY, MARKETING, or AUTHENTICATION | Yes (WhatsApp only) |
| Language (WhatsApp) | ISO language code (e.g., `en`, `zu`) | Yes (WhatsApp only) |
| Status (in logs) | Sent, Dispatched, Failed, Skipped | — |

## Tips & gotchas

- **Event Type immutability**: Once you save a template with an `Event Type`, that field becomes read-only. Changing it requires deleting and recreating. Plan event type names carefully
- **WhatsApp placeholders are numbered**: Use `{{1}}`, `{{2}}`, not `{{variable_name}}`. When testing, pipe-separate values in the exact order they appear
- **Inactive templates still exist**: Unchecking "Active" disables the template but does not delete it. The event type remains registered, preventing creation of a new template with the same type. Delete if truly unwanted
- **Email HTML support**: Email body accepts `<style>`, `<table>`, `<img>` but verify rendering in common clients (Gmail, Outlook)
- **SMS length**: No hard limit enforced in the UI, but carriers split messages > 160 characters (standard) or 70 (Unicode) into separate SMSes. Keep important notifications under 160 characters
- **Failed notifications**: "Failed" status + reason usually indicates a bad phone number, disabled WhatsApp account, or missing email address. Check the reason tooltip
- **Skipped notifications**: Status "Skipped" means the system decided not to send (e.g., customer opted out, do-not-contact flag set, wrong channel config)
- **In-App notifications**: Default channel but less visible than SMS/Email. Typically used alongside SMS or Email
- **WhatsApp 24-hour window**: Only approved templates can be sent outside the 24-hour customer-service window (initiated by the customer). Non-approved templates only work if the customer messaged first in the last 24 hours
- **No backfill**: Changing a template after events have been logged does not re-send old notifications. Logs are immutable
- **Batch testing WhatsApp**: The test modal sends one message at a time. For bulk testing, create a test policy, adjust a field, and observe the logs

## Related modules

- [Policies](/help/policies) — Monitor policy events that trigger notifications
- [Claims](/help/claims) — Claims events send notifications to policyholders
- [Payments](/help/payments) — Payment confirmations are notification events
- [Customers](/help/customers) — Customer contact info is injected into templates
- [Compliance](/help/compliance) — Audit logs of all communications for DPA/ECTA/NBFIRA compliance
