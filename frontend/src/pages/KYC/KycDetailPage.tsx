import { useEffect, useState } from 'react'
import { useParams, Link, useNavigate } from 'react-router-dom'
import { useKycDetail, useVerifyKycDocument, useUpdateKycStatus, useRunOpenSanctionsCheck } from '../../hooks/useKyc'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import { canApproveKyc, kycActionErrorMessage, type KycDocument } from '../../api/kyc'
import { fmtDate } from '../../utils/format'

const DOC_STATUS: Record<number, { label: string; cls: string }> = {
  0: { label: 'Pending', cls: 'bg-yellow-100 text-yellow-700' },
  1: { label: 'Approved', cls: 'bg-green-100 text-green-700' },
  2: { label: 'Rejected', cls: 'bg-red-100 text-red-700' },
}

const POLICY_STATUS: Record<number, string> = { 0: 'Inactive', 1: 'Active', 2: 'Cancelled' }

export default function KycDetailPage() {
  // URL param is customer_kyc.id (V8 parity — see CustomerKycController::detail).
  // Accept the legacy `customerId` slug as a fallback so existing
  // bookmarks land cleanly during the rollover.
  const params = useParams<{ kycId?: string; customerId?: string }>()
  const navigate = useNavigate()
  const kycId = Number(params.kycId ?? params.customerId ?? 0)
  const { data, isLoading, error, refetch } = useKycDetail(kycId)
  const updateOverall = useUpdateKycStatus()
  const runSanctions = useRunOpenSanctionsCheck()
  // Approve / reject controls are only rendered for holders of
  // `customer-kyc-approve` (KYC Approver role). The backend enforces the
  // same permission on the decision routes; this just keeps the buttons
  // away from viewers who would otherwise get a 403.
  const canApprove = canApproveKyc()

  const [overallRemark, setOverallRemark] = useState('')
  const [overallError, setOverallError] = useState<string | null>(null)
  const [activeTab, setActiveTab] = useState<'documents' | 'activity'>('documents')
  const [sanctionsMsg, setSanctionsMsg] = useState<{ ok: boolean; text: string } | null>(null)

  // Hydrate the Overall Decision remark from the stored value whenever
  // the server-side remark changes (initial load + after submit-then-
  // refetch). Keeps the textbox prefilled so reviewers can see WHY a
  // prior verdict was recorded.
  useEffect(() => {
    setOverallRemark(data?.kyc?.remark ?? '')
  }, [data?.kyc?.remark])

  // Auto-redirect DOM/COM-only customers to the dedicated review page.
  // V8 parity: customers whose policies are all on products 7,8,16-19,
  // 20,22 use the customer_kyc_dom_com flow, not customer_kyc — the
  // MIS identity documents shown here don't apply to them. The DomCom
  // route is still keyed on customer.id, so resolve it from the loaded
  // KYC response rather than from the (kyc-id) URL param.
  const customerIdForRedirect = data?.customer?.id ?? null
  useEffect(() => {
    if (data?.tier === 'DOM_COM' && customerIdForRedirect) {
      navigate(`/kyc/dom-com/${customerIdForRedirect}`, { replace: true })
    }
  }, [data?.tier, customerIdForRedirect, navigate])

  if (isLoading) return <div className="flex justify-center py-20"><LoadingSpinner size="lg" /></div>
  if (error || !data) return <div className="p-8 text-center text-red-500">Failed to load KYC data. {(error as Error)?.message}</div>
  // Brief blank while the redirect above kicks in — avoid flashing the
  // MIS layout to a DOM/COM-only customer.
  if (data.tier === 'DOM_COM') return <div className="flex justify-center py-20"><LoadingSpinner size="lg" /></div>

  const { customer, kyc, documents, policies, activityLog, sanctions } = data

  async function handleOverallAction(status: 'approved' | 'rejected') {
    setOverallError(null)
    try {
      await updateOverall.mutateAsync({ kycId, status, remark: overallRemark })
    } catch (e) {
      // Surface the refusal (403 from the approver gate, validation, network)
      // instead of letting the click fail silently.
      setOverallError(kycActionErrorMessage(e))
      return
    }
    // Don't clear overallRemark here: the hydration useEffect only fires
    // when data.kyc.remark changes, so clearing on a same-value re-submit
    // (e.g. Re-approve without editing) leaves the input blank.
    refetch()
  }

  async function handleRunSanctions() {
    if (!window.confirm('Are you sure you want to run OpenSanctions check for this customer?')) return
    setSanctionsMsg(null)
    try {
      const r = await runSanctions.mutateAsync({ customerId: customer.id, kycId })
      setSanctionsMsg({
        ok: r.success,
        text: r.success
          ? `${r.message} Max score ${r.maxScore.toFixed(3)} across ${r.datasetsCount} dataset(s)${r.sanctioned ? ' — flagged as sanctioned.' : '.'}`
          : r.message,
      })
      refetch()
    } catch (e) {
      setSanctionsMsg({ ok: false, text: `Error running OpenSanctions check: ${(e as Error)?.message ?? 'unknown error'}` })
    }
  }

  return (
    <div className="p-6 space-y-6 max-w-6xl mx-auto">
      {/* DOM/COM cross-link — this customer also holds DOM/COM-tier
          policies which have their own KYC review flow. */}
      {data.tier === 'BOTH' && (
        <div className="bg-blue-50 border border-blue-200 rounded-lg px-4 py-2.5 text-sm flex items-center justify-between">
          <span className="text-blue-800">
            This customer also has DOM/COM-tier policies.
            Review their corporate KYC separately.
          </span>
          <Link
            to={`/kyc/dom-com/${customer.id}`}
            className="text-blue-700 font-medium hover:underline whitespace-nowrap"
          >
            Open DOM/COM review &rarr;
          </Link>
        </div>
      )}

      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <Link to="/kyc" className="text-sm text-gray-500 hover:text-blue-600">&larr; Back to KYC List</Link>
          <h1 className="text-2xl font-bold text-gray-800 mt-1">KYC Review: {customer.name}</h1>
        </div>
        <div className="flex items-center gap-2">
          {kyc.status && (
            <span className={`px-3 py-1 rounded-full text-sm font-medium ${
              kyc.status === 'approved' ? 'bg-green-100 text-green-700' :
              kyc.status === 'rejected' ? 'bg-red-100 text-red-700' :
              'bg-yellow-100 text-yellow-700'
            }`}>
              {kyc.status.charAt(0).toUpperCase() + kyc.status.slice(1)}
            </span>
          )}
          {customer.isBlocked && (
            <span className="px-3 py-1 rounded-full text-sm font-medium bg-red-600 text-white">Blocked</span>
          )}
        </div>
      </div>

      {/* Customer Info + KYC Summary */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div className="bg-white rounded-lg border p-4">
          <h3 className="text-sm font-semibold text-gray-500 uppercase mb-3">Customer</h3>
          <div className="space-y-1.5 text-sm">
            <div><span className="text-gray-500 w-20 inline-block">Name:</span> <Link to={`/customers/${customer.id}`} className="text-blue-600 hover:underline">{customer.name}</Link></div>
            <div><span className="text-gray-500 w-20 inline-block">Phone:</span> {customer.cellphone || '-'}</div>
            <div><span className="text-gray-500 w-20 inline-block">Email:</span> {customer.email || '-'}</div>
            <div><span className="text-gray-500 w-20 inline-block">DOB:</span> {customer.dob || '-'}</div>
            <div><span className="text-gray-500 w-20 inline-block">Address:</span> {customer.address || '-'}</div>
          </div>
        </div>

        <div className="bg-white rounded-lg border p-4">
          <h3 className="text-sm font-semibold text-gray-500 uppercase mb-3">ID Numbers</h3>
          <div className="space-y-1.5 text-sm">
            <div>
              <span className="text-gray-500">Omang:</span> {kyc.omangNumber || '-'}
              {kyc.omangExpiry && (
                <span className="text-gray-400 ml-2 text-xs">(expires {fmtDate(kyc.omangExpiry, undefined, '-')})</span>
              )}
            </div>
            <div>
              <span className="text-gray-500">Passport:</span> {kyc.passportNumber || '-'}
              {kyc.passportExpiry && (
                <span className="text-gray-400 ml-2 text-xs">(expires {fmtDate(kyc.passportExpiry, undefined, '-')})</span>
              )}
            </div>
            {kyc.licenseExpiry && (
              <div>
                <span className="text-gray-500">License Expiry:</span> {fmtDate(kyc.licenseExpiry, undefined, '-')}
              </div>
            )}
            <div><span className="text-gray-500">Last Reviewed:</span> {fmtDate(kyc.updatedAt, undefined, '-')}</div>
            <div><span className="text-gray-500">By:</span> {kyc.performedBy || '-'}</div>
          </div>
        </div>

        <div className="bg-white rounded-lg border p-4">
          <h3 className="text-sm font-semibold text-gray-500 uppercase mb-3">Policies</h3>
          <div className="space-y-1 text-sm max-h-32 overflow-y-auto">
            {policies.length === 0 ? <span className="text-gray-400">No policies</span> :
              policies.map((p: any) => (
                <div key={p.id} className="flex items-center justify-between gap-2">
                  <Link to={`/policies/${p.id}`} className="text-blue-600 hover:underline text-xs">{p.policyNumber}</Link>
                  <span className="text-xs text-gray-500 truncate">{p.productName || ''}</span>
                  <span className={`text-xs px-1.5 py-0.5 rounded flex-shrink-0 ${p.status === 1 ? 'bg-green-100 text-green-600' : p.status === 2 ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-500'}`}>
                    {POLICY_STATUS[p.status] || p.status}
                  </span>
                </div>
              ))
            }
          </div>
        </div>
      </div>

      {/* AML / Sanctions screening — port of the V8 viewData sanctions
          panel: flagged countries/regions, last scan, sanctions status,
          and a synchronous "Run OpenSanctions Check" trigger. */}
      <div className="bg-white rounded-lg border p-4 space-y-3">
        <div className="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
          <div className="flex-1">
            {sanctions.countries.length > 0 ? (
              <div className="rounded-md bg-amber-50 ring-1 ring-amber-200 p-3">
                <h6 className="text-sm font-semibold text-red-600 mb-2 flex items-center gap-2">
                  <span aria-hidden>&#127758;</span> Sanctioned Countries/Regions
                  <span className="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-700">
                    {sanctions.countries.length}
                  </span>
                </h6>
                <div className="flex flex-wrap gap-1.5">
                  {sanctions.countries.map((c, i) => (
                    <span key={i} className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-400 text-gray-900 ring-1 ring-amber-500">
                      <span aria-hidden>&#128681;</span> {c}
                    </span>
                  ))}
                </div>
              </div>
            ) : (
              <p className="text-sm text-gray-400">No sanctions hits on the latest scan.</p>
            )}
          </div>

          <div className="flex flex-col items-start md:items-end gap-2 text-sm">
            {sanctions.lastScan && (
              <div>
                <span className="text-gray-500">Last Scan: </span>
                <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-700">
                  &#128339; {new Date(sanctions.lastScan).toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}
                </span>
              </div>
            )}
            {sanctions.sanctioned && (
              <div>
                <span className="text-gray-500">Sanctions Status: </span>
                <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">
                  &#128737; Sanctioned
                </span>
              </div>
            )}
          </div>
        </div>

        {sanctionsMsg && (
          <div className={`rounded-md px-3 py-2 text-sm ring-1 ${sanctionsMsg.ok ? 'bg-emerald-50 text-emerald-800 ring-emerald-200' : 'bg-rose-50 text-rose-800 ring-rose-200'}`}>
            {sanctionsMsg.text}
          </div>
        )}

        <div className="flex justify-end">
          <button
            type="button"
            onClick={handleRunSanctions}
            disabled={runSanctions.isPending}
            className="inline-flex items-center gap-1.5 px-4 py-1.5 bg-red-600 text-white rounded text-sm font-medium hover:bg-red-700 disabled:opacity-50"
          >
            <span aria-hidden>&#128737;</span> {runSanctions.isPending ? 'Running…' : 'Run OpenSanctions Check'}
          </button>
        </div>
      </div>

      {/* Overall Approve/Reject — for KYC Approvers the buttons + remark
          are ALWAYS visible regardless of the current verdict, so a
          decision can be flipped at any moment (matches DomCom flow &
          ops request 2026-06-03). Everyone else sees the status strip
          only (2026-09-10: approvals restricted to three named people).
          The remark input is prefilled from kyc.remark via the useEffect
          above so the prior reason stays visible until edited. */}
      <div className="bg-white rounded-lg border p-4 space-y-3">
        {kyc.status === 'approved' && (
          <div className="rounded-md bg-green-50 border border-green-200 px-3 py-2 flex items-center gap-3 flex-wrap text-sm">
            <span className="text-green-700 font-medium">KYC Approved</span>
            {kyc.approvedDate && <span className="text-green-600">on {fmtDate(kyc.approvedDate)}</span>}
            {kyc.performedBy && <span className="text-green-600">by {kyc.performedBy}</span>}
          </div>
        )}
        {kyc.status === 'rejected' && (
          <div className="rounded-md bg-red-50 border border-red-200 px-3 py-2 flex items-center gap-3 flex-wrap text-sm">
            <span className="text-red-700 font-medium">KYC Rejected</span>
            {kyc.approvedDate && <span className="text-red-600">on {fmtDate(kyc.approvedDate)}</span>}
            {kyc.performedBy && <span className="text-red-600">by {kyc.performedBy}</span>}
          </div>
        )}

        {canApprove ? (
          <div className="flex items-center gap-3 flex-wrap">
            <span className="text-sm font-semibold text-ink-muted">Overall Decision:</span>
            <input type="text" placeholder="Remark (optional)" value={overallRemark} onChange={e => setOverallRemark(e.target.value)}
              className="px-3 py-1.5 border rounded text-sm flex-1 min-w-[200px]" />
            <button onClick={() => handleOverallAction('approved')} disabled={updateOverall.isPending}
              className="px-4 py-1.5 bg-green-600 text-white rounded text-sm font-medium hover:bg-green-700 disabled:opacity-50">
              {kyc.status === 'approved' ? 'Re-approve KYC' : 'Approve KYC'}
            </button>
            <button onClick={() => handleOverallAction('rejected')} disabled={updateOverall.isPending}
              className="px-4 py-1.5 bg-red-600 text-white rounded text-sm font-medium hover:bg-red-700 disabled:opacity-50">
              Reject KYC
            </button>
            {overallError && <p className="w-full text-sm text-red-600">{overallError}</p>}
          </div>
        ) : (
          <p className="text-sm text-ink-muted">
            Approving or rejecting KYC is restricted to the KYC Approver role. You can view the documents and activity log.
          </p>
        )}
      </div>

      {/* Tabs */}
      <div className="flex border-b">
        {(['documents', 'activity'] as const).map(tab => (
          <button key={tab} onClick={() => setActiveTab(tab)}
            className={`px-4 py-2 text-sm font-medium border-b-2 -mb-px transition ${
              activeTab === tab ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}>
            {tab === 'documents' ? `Documents (${documents.filter(d => d.uploaded).length})` :
             `Activity Log (${activityLog.length})`}
          </button>
        ))}
      </div>

      {/* Documents Tab */}
      {activeTab === 'documents' && (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {documents.map(doc => (
            <DocumentCard key={doc.key} doc={doc} kycId={kycId} onUpdated={refetch} />
          ))}
        </div>
      )}

      {/* Activity Log Tab — V8-parity audit columns:
          Old Values / New data render the JSON diff captured on the
          kyc_activity_log row when those columns are populated. Rows
          without diff data fall back to a dash. */}
      {activeTab === 'activity' && (
        <div className="bg-white rounded-lg border p-4">
          {activityLog.length === 0 ? (
            <p className="text-sm text-gray-400 py-4 text-center">No activity yet.</p>
          ) : (
            <div className="space-y-2">
              {activityLog.map((log) => (
                <ActivityLogRow key={log.id} log={log} />
              ))}
            </div>
          )}
        </div>
      )}

    </div>
  )
}

// Doc keys that carry an expiry date — kept in sync with the backend's
// $docFields map in CustomerKycController::detail. The local list is a
// fallback so the expiry input still renders if a stale cached response
// is missing the new expiryField key (the BE flag remains authoritative
// when present).
const DOC_KEYS_WITH_EXPIRY = new Set([
  'omang', 'omangBack', 'passport', 'driving_license',
])

// Timeline row for the Activity Log tab. Matches the DOM/COM KYC review
// pattern (status badge + description, then performer · timestamp), with
// an optional "View before snapshot" expandable when kyc_activity_log
// captured an old_data_json payload.
function ActivityLogRow({ log }: { log: import('../../api/kyc').KycActivityLog }) {
  const [showSnapshot, setShowSnapshot] = useState(false)
  const hasSnapshot = !!log.oldValues && Object.keys(log.oldValues).length > 0
  const status = log.reason || log.status
  return (
    <div className="border-b border-gray-100 pb-2 last:border-b-0">
      <div className="flex items-center gap-2 text-sm">
        <span
          className={`px-2 py-0.5 rounded-full text-xs font-medium ${
            status === 'Approved' ? 'bg-green-100 text-green-700'
              : status === 'Rejected' || status === 'Unapprove' ? 'bg-red-100 text-red-700'
              : 'bg-gray-100 text-gray-700'
          }`}
        >
          {status || '-'}
        </span>
        <span className="text-gray-700">{log.description || '-'}</span>
      </div>
      <div className="text-xs text-gray-500 mt-0.5">
        {log.performedBy || '-'} &middot; {log.performedAt ? new Date(log.performedAt).toLocaleString() : ''}
        {hasSnapshot && (
          <>
            {' '}&middot;{' '}
            <button
              type="button"
              onClick={() => setShowSnapshot((s) => !s)}
              className="text-blue-600 hover:underline"
            >
              {showSnapshot ? 'Hide before snapshot' : 'View before snapshot'}
            </button>
          </>
        )}
      </div>
      {showSnapshot && hasSnapshot && (
        <pre className="mt-1.5 p-2 bg-gray-50 border border-gray-100 rounded text-[11px] font-mono text-gray-700 max-h-64 overflow-auto whitespace-pre-wrap break-all">
          {JSON.stringify(log.oldValues, null, 2)}
        </pre>
      )}
    </div>
  )
}

function DocumentCard({ doc, kycId, onUpdated }: { doc: KycDocument; kycId: number; onUpdated: () => void }) {
  const verify = useVerifyKycDocument()
  // Per-document verdicts are gated by the same approver permission as the
  // overall decision (backend: permission:customer-kyc-approve).
  const canApprove = canApproveKyc()
  const [remark, setRemark] = useState(doc.remark || '')
  // Normalise to YYYY-MM-DD for the native date input. Backend may return
  // a datetime string (e.g. "2032-12-22 00:00:00") — slice the date part.
  const [expiry, setExpiry] = useState<string>(() => (doc.expiryDate ? String(doc.expiryDate).slice(0, 10) : ''))
  const [showActions, setShowActions] = useState(false)
  const [verifyError, setVerifyError] = useState<string | null>(null)
  const st = DOC_STATUS[doc.status] || DOC_STATUS[0]
  const hasExpiry = !!doc.expiryField || DOC_KEYS_WITH_EXPIRY.has(doc.key)

  async function handleVerify(status: number) {
    setVerifyError(null)
    try {
      await verify.mutateAsync({
        kycId, document: doc.key, status, remark,
        ...(hasExpiry ? { expiryDate: expiry || null } : {}),
      })
    } catch (e) {
      setVerifyError(kycActionErrorMessage(e))
      return
    }
    setShowActions(false)
    onUpdated()
  }

  return (
    <div className={`bg-white rounded-lg border p-4 ${!doc.uploaded ? 'opacity-50' : ''}`}>
      <div className="flex items-center justify-between mb-2">
        <h4 className="font-medium text-gray-800">{doc.label}</h4>
        <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${st.cls}`}>{st.label}</span>
      </div>

      {doc.uploaded && doc.url ? (
        <a href={doc.url} target="_blank" rel="noopener noreferrer"
          className="block w-full h-40 bg-gray-100 rounded border mb-3 flex items-center justify-center hover:bg-gray-200 transition">
          {doc.url.match(/\.(jpg|jpeg|png|gif|webp)$/i) ? (
            <img src={doc.url} alt={doc.label} className="max-h-full max-w-full object-contain rounded" />
          ) : (
            <div className="text-center">
              <div className="text-3xl mb-1">&#128196;</div>
              <span className="text-xs text-blue-600">Click to view</span>
            </div>
          )}
        </a>
      ) : (
        <div className="w-full h-40 bg-gray-50 rounded border mb-3 flex items-center justify-center">
          <span className="text-gray-400 text-sm">Not uploaded</span>
        </div>
      )}

      {doc.remark && !showActions && (
        <p className="text-xs text-gray-500 mb-2">Remark: {doc.remark}</p>
      )}
      {hasExpiry && doc.expiryDate && !showActions && (
        <p className="text-xs text-gray-500 mb-2">Expires: {fmtDate(doc.expiryDate)}</p>
      )}

      {doc.uploaded && canApprove && (
        showActions ? (
          <div className="space-y-2">
            <input type="text" placeholder="Remark..." value={remark} onChange={e => setRemark(e.target.value)}
              className="w-full px-2 py-1.5 border rounded text-sm" />
            {hasExpiry && (
              <label className="block">
                <span className="text-[11px] font-medium text-gray-500 mb-0.5 block">Date of Expiry</span>
                <input type="date" value={expiry} onChange={e => setExpiry(e.target.value)}
                  className="w-full px-2 py-1.5 border rounded text-sm" />
              </label>
            )}
            <div className="flex gap-2">
              <button onClick={() => handleVerify(1)} disabled={verify.isPending}
                className="flex-1 py-1.5 bg-green-600 text-white rounded text-xs font-medium hover:bg-green-700 disabled:opacity-50">Approve</button>
              <button onClick={() => handleVerify(2)} disabled={verify.isPending}
                className="flex-1 py-1.5 bg-red-600 text-white rounded text-xs font-medium hover:bg-red-700 disabled:opacity-50">Reject</button>
              <button onClick={() => handleVerify(0)} disabled={verify.isPending}
                className="py-1.5 px-3 bg-gray-200 text-gray-600 rounded text-xs hover:bg-gray-300 disabled:opacity-50">Reset</button>
              <button onClick={() => setShowActions(false)}
                className="py-1.5 px-3 bg-gray-100 text-gray-500 rounded text-xs hover:bg-gray-200">Cancel</button>
            </div>
            {verifyError && <p className="text-xs text-red-600">{verifyError}</p>}
          </div>
        ) : (
          <button onClick={() => setShowActions(true)}
            className="w-full py-1.5 border border-gray-300 rounded text-sm text-gray-600 hover:bg-gray-50 font-medium">
            Verify Document
          </button>
        )
      )}
    </div>
  )
}
