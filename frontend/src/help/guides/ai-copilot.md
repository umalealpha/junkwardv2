---
lastReviewed: 2026-06-03
---
## Overview

The AI Assistant (Alpha Direct Intelligence) is a real-time chat interface for querying insurance data without writing SQL. Ask natural-language questions about policies, claims, payments, customers, and reconciliation anomalies, and receive tables of data with export capability.

## Who uses this

- **Underwriting**: analyze policy volumes, premiums, and trends
- **Finance**: review collections, reconciliation anomalies, and payment data
- **Claims**: check open claims, recent activity, and patterns
- **Data Analytics & Operations**: ad-hoc data queries and reporting
- **Management/Leadership**: quick intelligence on business metrics

## Step-by-step

### Start a new chat

1. Click **New Chat** button (left sidebar, top)
2. Input box is auto-focused and ready
3. Conversation list clears; you begin fresh

### Ask a question

1. Type your question in the input box (e.g., "Show active policies this month")
2. Press **Enter** to send (Shift+Enter creates a new line within your question)
3. AI processes your question; animated thinking dots appear while analyzing
4. Response appears with data table if applicable; timestamp shown below each message

### View suggested prompts (empty chat only)

When a conversation is empty, eight starter prompts appear (e.g., "How many active policies do we have?", "Total collections this month by product"). Click any to auto-send.

### Reuse or extend a conversation

1. In the left sidebar, click any previous conversation title to load it
2. Scroll to the top to see earlier messages; scroll down to see your latest exchange
3. Type a follow-up question in the input box and press Enter
4. Your new message extends the same conversation thread

### Export table data

1. After AI returns results with a table, click **Export CSV** button (top-right of table)
2. Browser downloads a .csv file named after the data label (e.g., `active_policies.csv`)

### Delete a conversation

1. In the left sidebar, hover over any conversation title
2. An X icon appears on the right; click it
3. Conversation is removed permanently (no undo)

### Return to previous screen

Click **Back** button (top-right of the chat header) to exit the AI module

## Field reference

| Field | Meaning | Required | Notes |
|-------|---------|----------|-------|
| Input box (textarea) | Your question or follow-up | Yes | Placeholder text: "Ask about policies, payments, claims, customers..." |
| Enter / Send button | Submit your question | Yes | Disabled while AI is processing; shows spinning icon during load |
| Shift+Enter | Line break within question | No | Use to write multi-line questions before pressing Enter |
| Conversation title | Auto-generated summary of first question | Generated | Appears in sidebar; used to identify chats in history |
| Result table | Columns and rows of data | Conditional | Appears only if query returns structured data; includes row count footer |
| Period / Label | Context label for result | Optional | e.g., "Period: March 2026" or data label ("Active Policies") |
| Token counter | Running total of tokens used this session | Informational | Displayed top-right if > 0; read-only |
| Model badge | Name of AI model serving the query | Informational | Displayed top-right; read-only |
| Error message | API or query failure reason | Conditional | Red banner with close button; includes "Try again" for conversation-load failures |

## Tips & gotchas

- **Shift+Enter for multi-line input**: Only Enter alone sends; Shift+Enter is required if you need line breaks within your question.
- **CSV export filename**: Auto-generated from the data label and lowercased; special characters become underscores.
- **No results vs. error**: If a query returns no rows, you see "No results returned" (gray box) rather than an error. This is normal for empty datasets.
- **Conversation auto-save**: Conversations are persisted automatically after your first question; no explicit save button needed.
- **Token tracking**: The token count badge only appears after at least one query has been sent; it reflects cumulative usage for the session.
- **Active conversation highlight**: The currently open conversation is highlighted in blue in the sidebar; switching conversations loads that thread.
- **Delete on active conversation**: If you delete the conversation you're currently viewing, you're automatically returned to a new empty chat.
- **Focus behavior**: When you click "New Chat" or open the panel, the input box auto-focuses so you can start typing immediately.
- **Markdown formatting in responses**: AI responses support **bold text** (enclosed in **text**) and line breaks; tables render with sortable columns and row counts.
- **Error retry behavior**: If loading a saved conversation fails, an error banner appears with a "Try again" button; new-chat errors don't get retry (you can manually resend).
- **Data freshness**: All queries run against live database reads; results reflect current state as of query time.
- **Permission-based access**: If you cannot see certain data fields in results, you lack database read permission for those tables (contact your admin).

## Related modules

- [Policies](/help/policies)
- [Claims](/help/claims)
- [Payments](/help/payments)
- [Customers](/help/customers)
- [Reports](/help/reports)
- [Reconciliation](/help/reconciliation)
