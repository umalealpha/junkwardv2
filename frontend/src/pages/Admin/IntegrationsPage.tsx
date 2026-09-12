import { useCallback, useState } from 'react'
import { Link } from 'react-router-dom'
import { useIntegrations, useUpdateIntegration, useTestIntegrationConnection, useSubmitTestInvoice, useUpdateIntegrationSettings } from '../../hooks/useIntegrations'
import type { Integration } from '../../api/integrations'

// ─── Helpers ────────────────────────────────────────────────────────────────

function formatTime(iso: string | null): string {
  if (!iso) return '—'
  const d = new Date(iso)
  if (isNaN(d.getTime())) return iso
  return d.toLocaleString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  })
}

// Short human description per known integration. Falls back to a generic
// line for any integration the backend exposes that isn't listed here.
const DESCRIPTIONS: Record<string, string> = {
  swiftly:
    'Supply-chain / early-payment financing. When enabled, invoices are submitted to Swiftly and signed early-payment webhooks are processed.',
  claims_automation:
    'Claims automation. When enabled, a claimant is automatically emailed the correct claim form the moment a claim is registered. Defaults off — arm on UAT first.',
  premium_confirmation:
    'Premium confirmation on the claim. When enabled, every registered claim is auto-checked against the premium ledger: paid-up releases itself; arrears emails Finance and lands on the Premium Confirmations tracker. Never auto-declines. Defaults off — prove on UAT first.',
  mapfre:
    'MAPFRE / MAWDY travel connector. When enabled, the Start portal can price, quote and bind travel contracts. Those sales stay in MAPFRE’s book — they never become Graphite policies, so “View binds” is the only place they appear.',
  omni_po:
    'Purchase Orders tab on the claim, read live from Omni via a server-side proxy (the key never reaches the browser). Render-only — PO status and amounts are never stored in Graphite. Needs OMNI_PO_API_KEY configured to return data.',
  alpha_transit:
    'Courier goods-in-transit ingestion from the Alpha Transit platform (transit.alphadirect.co.bw). While off, the webhook answers 503 and ATC’s retry queue holds every event — replayable, nothing lost. Needs ALPHA_TRANSIT_WEBHOOK_TOKEN configured. Ingested data: Admin > Alpha Transit.',
}

// Internal feature flags (not third-party providers): no credentials, no
// program_id, no connection test / test-invoice — only the on/off toggle.
const INTERNAL_FLAGS = new Set<string>(['claims_automation', 'premium_confirmation'])

// What each integration actually supports. A card renders ONLY the controls
// listed here, so each integration is independent — no cloned Swiftly tabs.
interface Caps { programId?: boolean; connectionTest?: boolean; testInvoice?: boolean; console?: boolean; binds?: boolean }
const CAPS: Record<string, Caps> = {
  swiftly:           { programId: true, connectionTest: true, testInvoice: true, console: true },
  // `binds` opens the read-only console over the MAPFRE bind audit trail —
  // the only screen a portal travel sale ever appears on, since it is bound in
  // MAPFRE's book and never becomes a Graphite policy.
  mapfre:            { connectionTest: true, binds: true },
  claims_automation: {},
  premium_confirmation: {},
  // Toggle-only card, but NOT an "internal flag": it has a real credential,
  // so the `configured` chip matters (lights when OMNI_PO_API_KEY lands).
  omni_po: {},
  // Toggle-only inbound webhook: no program_id / connection test / test
  // invoice (ATC pushes to us). `configured` lights when the bearer lands.
  alpha_transit: {},
}
// Fallback: an unknown third-party provider gets the standard provider toolset;
// an internal feature flag gets none (toggle only).
const capsFor = (slug: string): Caps =>
  CAPS[slug] ?? (INTERNAL_FLAGS.has(slug) ? {} : { programId: true, connectionTest: true, testInvoice: true })

function PlugIcon() {
  return (
    <svg className="w-5 h-5 text-brand-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
    </svg>
  )
}

// ─── Toggle ───────────────────────────────────────────────────────────────────

interface ToggleProps {
  enabled: boolean
  disabled: boolean
  pending: boolean
  onToggle: () => void
  label: string
}

function Toggle({ enabled, disabled, pending, onToggle, label }: ToggleProps) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={enabled}
      aria-label={label}
      onClick={onToggle}
      disabled={disabled || pending}
      className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-brand-navy focus:ring-offset-1 ${
        disabled || pending ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'
      } ${enabled ? 'bg-green-500' : 'bg-gray-300'}`}
      title={disabled ? 'You do not have permission to change this' : enabled ? 'Disable integration' : 'Enable integration'}
    >
      <span
        className={`inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform ${
          enabled ? 'translate-x-6' : 'translate-x-1'
        }`}
      />
    </button>
  )
}

// ─── Integration card ──────────────────────────────────────────────────────────

interface CardProps {
  item: Integration
  canManage: boolean
  pending: boolean
  errorMsg: string | null
  onToggle: (item: Integration) => void
}

function IntegrationCard({ item, canManage, pending, errorMsg, onToggle }: CardProps) {
  const testMutation = useTestIntegrationConnection()
  const test = testMutation.data

  const invoiceMutation = useSubmitTestInvoice()
  const inv = invoiceMutation.data
  const [supplierId, setSupplierId] = useState('')
  const [amount, setAmount] = useState('50000')
  const [dueAt, setDueAt] = useState<string>(() => {
    const d = new Date()
    d.setDate(d.getDate() + 60)
    return d.toISOString().slice(0, 10)
  })
  const [autoEarly, setAutoEarly] = useState(true)

  const settingsMutation = useUpdateIntegrationSettings()
  const [programId, setProgramId] = useState(item.program_id ?? '')

  const sendTestInvoice = () => {
    invoiceMutation.mutate({
      slug: item.integration,
      data: {
        supplier_id: supplierId.trim(),
        amount: Number(amount) || undefined,
        due_at: dueAt || undefined,
        auto_request_early_payment: autoEarly,
      },
    })
  }

  const saveProgramId = () => {
    settingsMutation.mutate({ slug: item.integration, data: { program_id: programId.trim() || null } })
  }

  // Internal feature flags have no credentials / provider tooling — show the
  // on/off toggle only, hide the "configured", program_id, connection-test and
  // test-invoice controls that only make sense for a third-party provider.
  const isInternalFlag = INTERNAL_FLAGS.has(item.integration)
  const caps = capsFor(item.integration)

  return (
    <div className="bg-white shadow rounded-lg p-5 sm:p-6">
      <div className="flex items-start justify-between gap-4">
        <div className="flex items-start gap-3 min-w-0">
          <span className="mt-0.5 flex-shrink-0 inline-flex items-center justify-center w-9 h-9 rounded-lg bg-brand-navy/5">
            <PlugIcon />
          </span>
          <div className="min-w-0">
            <div className="flex items-center gap-2 flex-wrap">
              <h3 className="text-base font-semibold text-gray-800">{item.label}</h3>
              {item.enabled ? (
                <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">
                  <span className="w-1.5 h-1.5 bg-green-500 rounded-full inline-block" />
                  Enabled
                </span>
              ) : (
                <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-500">
                  Disabled
                </span>
              )}
              {!isInternalFlag && (item.configured ? (
                <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700">
                  Configured
                </span>
              ) : (
                <span
                  className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-50 text-amber-700"
                  title="Credentials are not set for this integration yet"
                >
                  Not configured
                </span>
              ))}
            </div>
            <p className="mt-1 text-sm text-gray-600 leading-relaxed">
              {DESCRIPTIONS[item.integration] ?? 'Third-party integration.'}
            </p>
            <p className="mt-2 text-xs text-gray-400">
              Last changed by{' '}
              <span className="text-gray-600">{item.updated_by ?? '—'}</span>{' '}
              on <span className="text-gray-600">{formatTime(item.updated_at)}</span>
            </p>
            {errorMsg && (
              <p className="mt-2 text-xs text-red-600" role="alert">
                {errorMsg}
              </p>
            )}
          </div>
        </div>

        <div className="flex-shrink-0 pt-0.5">
          <Toggle
            enabled={item.enabled}
            disabled={!canManage}
            pending={pending}
            label={`${item.enabled ? 'Disable' : 'Enable'} ${item.label}`}
            onToggle={() => onToggle(item)}
          />
        </div>
      </div>

      {/* Program ID — non-secret identifier, editable without a redeploy */}
      {canManage && caps.programId && (
        <div className="mt-4 pt-4 border-t border-gray-100">
          <label htmlFor={`pid-${item.integration}`} className="block text-xs font-medium text-gray-700 mb-1.5">Program ID</label>
          <div className="flex items-center gap-2 flex-wrap">
            <input
              id={`pid-${item.integration}`}
              type="text"
              value={programId}
              onChange={(e) => setProgramId(e.target.value)}
              placeholder="e.g. PTSJ09FLW"
              className="w-56 text-sm px-2.5 py-1.5 border border-gray-300 rounded font-mono focus:outline-none focus:ring-2 focus:ring-brand-navy/40 focus:border-brand-navy"
            />
            <button
              type="button"
              onClick={saveProgramId}
              disabled={settingsMutation.isPending || programId.trim() === (item.program_id ?? '')}
              className="text-xs px-3 py-1.5 rounded border border-brand-navy text-brand-navy hover:bg-brand-navy hover:text-white transition disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-transparent disabled:hover:text-brand-navy cursor-pointer"
            >
              {settingsMutation.isPending ? 'Saving…' : 'Save'}
            </button>
            {settingsMutation.isSuccess && !settingsMutation.isPending && (
              <span className="text-xs text-green-700">Saved</span>
            )}
            {settingsMutation.isError && (
              <span className="text-xs text-red-600" role="alert">Save failed</span>
            )}
          </div>
          <p className="mt-1.5 text-[11px] text-gray-400">
            Get this from Swiftly, or read it via Test connection. Stored here — no redeploy needed. Secret keys stay in server config.
          </p>
        </div>
      )}

      {/* Connection test — verifies credentials + connectivity to the provider */}
      {canManage && caps.connectionTest && (
        <div className="mt-4 pt-4 border-t border-gray-100">
          <div className="flex items-center gap-3 flex-wrap">
            <button
              type="button"
              onClick={() => testMutation.mutate(item.integration)}
              disabled={testMutation.isPending}
              className="text-xs px-3 py-1.5 rounded border border-brand-navy text-brand-navy hover:bg-brand-navy hover:text-white transition disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-transparent disabled:hover:text-brand-navy cursor-pointer"
            >
              {testMutation.isPending ? 'Testing…' : 'Test connection'}
            </button>

            {caps.binds && (
              <Link
                to="/admin/integrations/mapfre/binds"
                className="text-xs px-3 py-1.5 rounded bg-brand-navy text-white hover:bg-brand-navy/90 transition cursor-pointer"
              >
                View binds →
              </Link>
            )}

            {caps.console && (
              <Link
                to="/admin/integrations/swiftly/console"
                className="text-xs px-3 py-1.5 rounded bg-brand-navy text-white hover:bg-brand-navy/90 transition cursor-pointer"
              >
                Open test console →
              </Link>
            )}

            {testMutation.isError && (
              <span className="text-xs text-red-600" role="alert">Request failed — please try again.</span>
            )}

            {test && test.ok && (
              <span className="inline-flex items-center gap-1.5 text-xs text-green-700">
                <span className="w-1.5 h-1.5 bg-green-500 rounded-full inline-block" />
                Connected
                {test.program_id ? <> · program_id <span className="font-mono text-ink-muted">{test.program_id}</span></> : null}
                {typeof test.supplier_count === 'number' ? ` · ${test.supplier_count} suppliers` : ''}
                {/* MAPFRE reports the dealer/environment it authenticated against
                    rather than a supplier list — the PRE dealer only accepts
                    countryId=IT, so this is how you tell it apart from production. */}
                {test.country_id ? <> · countryId <span className="font-mono text-ink-muted">{test.country_id}</span></> : null}
              </span>
            )}

            {test && !test.ok && (
              <span className="text-xs text-red-600" role="alert">
                Failed: {test.error ?? 'connection error'}{test.http_status ? ` (HTTP ${test.http_status})` : ''}
              </span>
            )}
          </div>

          {/* Supplier list from the connection test — click Use to fill the form */}
          {test && test.ok && Array.isArray(test.suppliers) && test.suppliers.length > 0 && (
            <div className="mt-3 max-h-48 overflow-y-auto border border-gray-100 rounded">
              <table className="w-full text-xs">
                <tbody>
                  {test.suppliers.map((s, i) => {
                    const sup = s as Record<string, unknown>
                    const id = String(sup.supplier_id ?? sup.id ?? sup.supplierId ?? '')
                    const name = String(sup.name ?? sup.business_name ?? sup.businessName ?? '')
                    return (
                      <tr key={i} className="border-b border-gray-50 last:border-0">
                        <td className="px-2 py-1 font-mono text-gray-700 whitespace-nowrap">{id || '—'}</td>
                        <td className="px-2 py-1 text-gray-600">{name}</td>
                        <td className="px-2 py-1 text-right">
                          <button
                            type="button"
                            onClick={() => setSupplierId(id)}
                            disabled={!id}
                            className="text-brand-navy hover:underline disabled:opacity-40 disabled:no-underline cursor-pointer"
                          >
                            Use
                          </button>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {/* Send test invoice — exercises the outbound + full early-payment loop */}
      {canManage && caps.testInvoice && (
        <div className="mt-4 pt-4 border-t border-gray-100">
          <p className="text-xs font-medium text-gray-700 mb-2">Send a test invoice</p>
          <div className="flex flex-wrap items-end gap-3">
            <div>
              <label htmlFor={`sup-${item.integration}`} className="block text-[11px] text-gray-500 mb-1">Supplier ID</label>
              <input
                id={`sup-${item.integration}`}
                type="text"
                value={supplierId}
                onChange={(e) => setSupplierId(e.target.value)}
                placeholder="from Test connection"
                className="w-48 text-sm px-2.5 py-1.5 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-brand-navy/40 focus:border-brand-navy"
              />
            </div>
            <div>
              <label htmlFor={`amt-${item.integration}`} className="block text-[11px] text-gray-500 mb-1">Amount (BWP)</label>
              <input
                id={`amt-${item.integration}`}
                type="number"
                min={1}
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
                className="w-32 text-sm px-2.5 py-1.5 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-brand-navy/40 focus:border-brand-navy"
              />
            </div>
            <div>
              <label htmlFor={`due-${item.integration}`} className="block text-[11px] text-gray-500 mb-1">Maturity date</label>
              <input
                id={`due-${item.integration}`}
                type="date"
                value={dueAt}
                onChange={(e) => setDueAt(e.target.value)}
                className="w-40 text-sm px-2.5 py-1.5 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-brand-navy/40 focus:border-brand-navy"
              />
            </div>
            <button
              type="button"
              onClick={sendTestInvoice}
              disabled={invoiceMutation.isPending || !supplierId.trim()}
              className="text-xs px-3 py-1.5 rounded bg-brand-navy text-white hover:bg-brand-navy/90 transition disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
            >
              {invoiceMutation.isPending ? 'Sending…' : 'Send test invoice'}
            </button>
          </div>
          <label className="mt-2.5 flex items-center gap-2 text-xs text-gray-600 cursor-pointer select-none w-fit">
            <input
              type="checkbox"
              checked={autoEarly}
              onChange={(e) => setAutoEarly(e.target.checked)}
              className="h-3.5 w-3.5 rounded border-gray-300 text-brand-navy focus:ring-brand-navy/40"
            />
            Auto-request early payment <span className="text-gray-400">(triggers the webhook back to us)</span>
          </label>

          {invoiceMutation.isError && (
            <p className="mt-2 text-xs text-red-600" role="alert">Request failed — please try again.</p>
          )}
          {inv && inv.ok && (
            <p className="mt-2 text-xs text-green-700" role="status">
              Sent · invoice <span className="font-mono text-gray-700">{inv.invoice_id}</span> · early-payment auto-requested — watch for the webhook.
            </p>
          )}
          {inv && !inv.ok && (
            <p className="mt-2 text-xs text-red-600" role="alert">
              Failed: {inv.error ?? 'submission error'}{inv.http_status ? ` (HTTP ${inv.http_status})` : ''}
            </p>
          )}
        </div>
      )}
    </div>
  )
}

// ─── Page ──────────────────────────────────────────────────────────────────────

export default function IntegrationsPage() {
  const { data, isLoading, isError, refetch } = useIntegrations()
  const updateMutation = useUpdateIntegration()

  const canManage = data?.can_manage ?? false

  const handleToggle = useCallback(
    (item: Integration) => {
      const next = !item.enabled
      if (
        !window.confirm(
          `${next ? 'Enable' : 'Disable'} the ${item.label} integration?\n\n` +
            `This takes effect immediately and is recorded in the audit log.`,
        )
      ) {
        return
      }
      updateMutation.mutate({ slug: item.integration, data: { enabled: next } })
    },
    [updateMutation],
  )

  // Master–detail: one integration selected at a time. Defaults to the first.
  const [selectedSlug, setSelectedSlug] = useState<string | null>(null)
  const items = data?.data ?? []
  const selected = items.find((i) => i.integration === selectedSlug) ?? items[0] ?? null

  return (
    <div className="p-4 sm:p-6 max-w-5xl">
      {/* Header */}
      <div className="mb-1">
        <h1 className="text-2xl font-bold text-gray-800">Integrations</h1>
        <p className="mt-1 text-sm text-gray-500">
          Enable or disable third-party integrations. Changes apply immediately and every change is
          recorded in the audit trail.
        </p>
      </div>

      {!isLoading && !isError && !canManage && (
        <div className="mt-4 mb-2 rounded-md bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
          You can view integration status but are not authorised to change it. Contact an
          administrator if you need a change made.
        </div>
      )}

      {/* Loading skeletons */}
      {isLoading && (
        <div className="mt-4 space-y-4">
          {[...Array(2)].map((_, i) => (
            <div key={i} className="bg-white shadow rounded-lg p-6">
              <div className="flex items-start justify-between">
                <div className="flex items-start gap-3 w-full">
                  <div className="w-9 h-9 rounded-lg bg-gray-200 animate-pulse" />
                  <div className="flex-1 space-y-2">
                    <div className="h-4 w-40 bg-gray-200 animate-pulse rounded" />
                    <div className="h-3 w-3/4 bg-gray-100 animate-pulse rounded" />
                    <div className="h-3 w-1/3 bg-gray-100 animate-pulse rounded" />
                  </div>
                </div>
                <div className="h-6 w-11 bg-gray-200 animate-pulse rounded-full" />
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Error state */}
      {isError && (
        <div className="mt-4 bg-white shadow rounded-lg p-6 text-center">
          <p className="text-sm text-red-600">Could not load integration settings.</p>
          <button
            onClick={() => refetch()}
            className="mt-3 text-sm px-3 py-1.5 rounded border border-brand-navy text-brand-navy hover:bg-brand-navy hover:text-white transition cursor-pointer"
          >
            Retry
          </button>
        </div>
      )}

      {/* Master–detail: sub-nav (left) + selected integration panel (right) */}
      {!isLoading && !isError && items.length === 0 && (
        <div className="mt-4 bg-white shadow rounded-lg p-6 text-center text-sm text-gray-500">
          No integrations available.
        </div>
      )}

      {!isLoading && !isError && items.length > 0 && (
        <div className="mt-4 flex flex-col md:flex-row gap-4 md:gap-6 items-start">
          {/* Left sub-nav */}
          <nav
            aria-label="Integrations"
            className="w-full md:w-60 md:shrink-0 bg-white shadow rounded-lg p-2"
          >
            <p className="px-3 pt-2 pb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400">
              Integrations
            </p>
            {items.map((it) => {
              const active = selected?.integration === it.integration
              return (
                <button
                  key={it.integration}
                  type="button"
                  onClick={() => setSelectedSlug(it.integration)}
                  aria-current={active ? 'true' : undefined}
                  className={`flex items-center gap-2.5 w-full text-left rounded-md px-3 py-2.5 min-h-[44px] border-l-2 transition-colors cursor-pointer ${
                    active
                      ? 'bg-brand-navy/5 border-brand-orange font-semibold text-gray-800'
                      : 'border-transparent text-gray-600 hover:bg-brand-navy/5'
                  }`}
                >
                  <span className="flex-1 min-w-0 truncate text-sm">{it.label}</span>
                  <span
                    className={`w-2 h-2 rounded-full shrink-0 ${
                      it.enabled ? 'bg-green-500' : 'bg-gray-300'
                    }`}
                    aria-hidden="true"
                  />
                  <span className="sr-only">{it.enabled ? 'On' : 'Off'}</span>
                </button>
              )
            })}
          </nav>

          {/* Right panel — selected integration only */}
          <div className="flex-1 min-w-0 w-full">
            {selected && (
              <IntegrationCard
                key={selected.integration}
                item={selected}
                canManage={canManage}
                pending={
                  updateMutation.isPending &&
                  updateMutation.variables?.slug === selected.integration
                }
                errorMsg={
                  updateMutation.isError &&
                  updateMutation.variables?.slug === selected.integration
                    ? 'Failed to update. Please try again.'
                    : null
                }
                onToggle={handleToggle}
              />
            )}
          </div>
        </div>
      )}
    </div>
  )
}
