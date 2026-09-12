import { useEffect, useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import {
  useDomComKycDetail,
  useVerifyDomComKycDocument,
  useUpdateDomComKycStatus,
} from '../../hooks/useKyc'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import { canApproveKyc, kycActionErrorMessage, type DomComKycDocument } from '../../api/kyc'

/**
 * DOM/COM-tier KYC review page (products 7, 8, 16, 17, 18, 19, 20, 22).
 *
 * Mirrors KycDetailPage.tsx (MIS) but renders two grouped document
 * sets:
 *   • Identity Documents — shared with MIS, stored on customer_kyc.
 *   • Corporate / Entity Documents — DOM/COM-only, stored on
 *     customer_kyc_dom_com (KYC Form, Data Protection Form, Certificate
 *     of Incorporation, Resolution, Directors/Shareholders IDs, etc.).
 *
 * Also lists the V8 BizSure sparse multi-director / multi-shareholder
 * uploads from policy_kyc_documents (rendered below as "Additional
 * Uploads").
 */

const DOC_STATUS: Record<number, { label: string; cls: string }> = {
  0: { label: 'Pending', cls: 'bg-yellow-100 text-yellow-700' },
  1: { label: 'Approved', cls: 'bg-green-100 text-green-700' },
  2: { label: 'Rejected', cls: 'bg-red-100 text-red-700' },
}

const POLICY_STATUS: Record<number, string> = { 0: 'Inactive', 1: 'Active', 2: 'Cancelled' }

export default function DomComKycDetailPage() {
  // Route param is customer_kyc.id (the KYC row PK), NOT customer.id —
  // the DomCom sibling is resolved server-side via customer_kyc_id.
  const { kycId } = useParams<{ kycId: string }>()
  const kid = Number(kycId)

  const { data, isLoading, error, refetch } = useDomComKycDetail(kid)
  const updateOverall = useUpdateDomComKycStatus()
  // Approve / reject controls are only rendered for holders of
  // `customer-kyc-approve` (KYC Approver role); the backend enforces the
  // same permission on the decision routes.
  const canApprove = canApproveKyc()

  const [overallRemark, setOverallRemark] = useState('')
  const [overallError, setOverallError] = useState<string | null>(null)
  const [activeTab, setActiveTab] = useState<'documents' | 'extras' | 'activity'>('documents')

  // Hydrate the Overall Decision remark from the stored value whenever
  // the server-side remark changes (initial load + after submit-then-
  // refetch). The local clear in handleOverallAction prevents a stale
  // edit from flashing back; the next refetch then re-fills with the
  // newly-saved value.
  useEffect(() => {
    setOverallRemark(data?.kyc?.remark ?? '')
  }, [data?.kyc?.remark])

  if (isLoading) {
    return (
      <div className="flex justify-center py-20">
        <LoadingSpinner size="lg" />
      </div>
    )
  }
  if (error || !data) {
    return (
      <div className="p-8 text-center text-red-500">
        Failed to load DOM/COM KYC data. {(error as Error)?.message}
      </div>
    )
  }

  const { customer, kyc, kycDomCom, documents, extraDocs, policies, activityLog, policyTier } = data

  // Split documents by underlying table for grouped display. The
  // backend already filtered the doc list to the right set for this
  // customer's policy tier (Domestic / Commercial / Mixed), so we just
  // render whatever comes through.
  const identityDocs  = documents.filter((d) => d.table === 'kyc')
  const corporateDocs = documents.filter((d) => d.table === 'kyc_dom_com')

  // Section labels mirror V8's blade wording (Domestic vs Commercial).
  const identitySectionLabel = policyTier === 'DOMESTIC'
    ? 'Domestic Policy — Individual KYC Documents'
    : policyTier === 'MIXED'
    ? 'Individual KYC Documents (Domestic policy)'
    : 'Individual KYC Documents'
  const corporateSectionLabel = policyTier === 'COMMERCIAL'
    ? 'Commercial Policy — Corporate KYC Documents'
    : policyTier === 'MIXED'
    ? 'Corporate KYC Documents (Commercial policy)'
    : 'Corporate / Entity Documents'

  // Overall approve gate: every uploaded doc must be approved.
  // Kept around (with `void` references below) so the inline status
  // hints under the Approve/Reject buttons can be un-commented without
  // re-deriving these — see the commented JSX next to those buttons.
  const allApproved = documents.filter((d) => d.uploaded).every((d) => d.status === 1)
  const hasRejected = documents.some((d) => d.status === 2)
  void allApproved; void hasRejected

  async function handleOverallAction(status: 'approved' | 'rejected') {
    setOverallError(null)
    try {
      await updateOverall.mutateAsync({ kycId: kid, status, remark: overallRemark })
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

  return (
    <div className="p-6 space-y-6 max-w-6xl mx-auto">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <Link to="/kyc" className="text-sm text-gray-500 hover:text-blue-600">
            &larr; Back to KYC List
          </Link>
          <h1 className="text-2xl font-bold text-gray-800 mt-1">
            DOM/COM KYC Review: {customer.name}
          </h1>
          <p className="text-xs text-gray-500 mt-0.5 flex items-center gap-2">
            <span>Corporate KYC flow</span>
            <span className={`px-1.5 py-0.5 rounded text-[10px] font-medium ${
              policyTier === 'DOMESTIC' ? 'bg-amber-100 text-amber-700'
                : policyTier === 'COMMERCIAL' ? 'bg-purple-100 text-purple-700'
                : policyTier === 'MIXED' ? 'bg-blue-100 text-blue-700'
                : 'bg-gray-100 text-gray-600'
            }`}>{policyTier}</span>
          </p>
        </div>
        <div className="flex items-center gap-2">
          {kyc.status && (
            <span
              className={`px-3 py-1 rounded-full text-sm font-medium capitalize ${
                kyc.status === 'Approve' || kyc.status === 'approved'
                  ? 'bg-green-100 text-green-700'
                  : kyc.status === 'Unapprove' || kyc.status === 'rejected'
                  ? 'bg-red-100 text-red-700'
                  : 'bg-yellow-100 text-yellow-700'
              }`}
            >
              {kyc.status}
            </span>
          )}
          {customer.isBlocked && (
            <span className="px-3 py-1 rounded-full text-sm font-medium bg-red-600 text-white">
              Blocked
            </span>
          )}
        </div>
      </div>

      {/* Customer Info + KYC Summary + Policies */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div className="bg-white rounded-lg border p-4">
          <h3 className="text-sm font-semibold text-gray-500 uppercase mb-3">Customer</h3>
          <div className="space-y-1.5 text-sm">
            <div>
              <span className="text-gray-500 w-20 inline-block">Name:</span>{' '}
              <Link to={`/customers/${customer.id}`} className="text-blue-600 hover:underline">
                {customer.name}
              </Link>
            </div>
            <div><span className="text-gray-500 w-20 inline-block">Phone:</span> {customer.cellphone || '-'}</div>
            <div><span className="text-gray-500 w-20 inline-block">Email:</span> {customer.email || '-'}</div>
            <div><span className="text-gray-500 w-20 inline-block">DOB:</span> {customer.dob || '-'}</div>
            <div><span className="text-gray-500 w-20 inline-block">Address:</span> {customer.address || '-'}</div>
          </div>
        </div>

        <div className="bg-white rounded-lg border p-4">
          <h3 className="text-sm font-semibold text-gray-500 uppercase mb-3">DOM/COM Status</h3>
          <div className="space-y-1.5 text-sm">
            <div><span className="text-gray-500">Compliance:</span>{' '}
              {kycDomCom?.compliance === 1 ? <span className="text-green-700">Compliant</span>
                : kycDomCom?.compliance === 2 ? <span className="text-red-700">Non-compliant</span>
                : <span className="text-yellow-700">Pending</span>}
            </div>
            <div><span className="text-gray-500">Directors ID Exp:</span> {kycDomCom?.directorsIdExpiry || '-'}</div>
            <div><span className="text-gray-500">Directors Passport Exp:</span> {kycDomCom?.directorsPassportExpiry || '-'}</div>
            <div><span className="text-gray-500">Shareholders ID Exp:</span> {kycDomCom?.shareholdersIdExpiry || '-'}</div>
            <div><span className="text-gray-500">Approved Date:</span> {kycDomCom?.approvedDate || '-'}</div>
          </div>
        </div>

        <div className="bg-white rounded-lg border p-4">
          <h3 className="text-sm font-semibold text-gray-500 uppercase mb-3">
            DOM/COM Policies ({policies.length})
          </h3>
          <div className="space-y-1 text-sm max-h-40 overflow-y-auto">
            {policies.length === 0 ? (
              <span className="text-gray-400">No DOM/COM policies</span>
            ) : (
              policies.map((p) => (
                <div key={p.id} className="flex items-center justify-between gap-2">
                  <Link to={`/policies/${p.id}`} className="text-blue-600 hover:underline text-xs">
                    {p.policyNumber}
                  </Link>
                  <span className="text-xs text-gray-500 truncate">{p.productName || ''}</span>
                  <span
                    className={`text-xs px-1.5 py-0.5 rounded flex-shrink-0 ${
                      p.status === 1
                        ? 'bg-green-100 text-green-600'
                        : p.status === 2
                        ? 'bg-red-100 text-red-600'
                        : 'bg-gray-100 text-gray-500'
                    }`}
                  >
                    {POLICY_STATUS[p.status] || p.status}
                  </span>
                </div>
              ))
            )}
          </div>
        </div>
      </div>

      {/* Overall Approve / Reject — for KYC Approvers always visible AND
          always enabled. Per ops request 2026-06-03: no conditions on
          these buttons — reviewers must be able to flip a verdict at any
          moment, regardless of doc-level state or whether a verdict has
          been recorded before. Everyone else (2026-09-10: approvals
          restricted to three named people) sees a read-only note. */}
      <div className="bg-white rounded-lg border p-4">
        <h3 className="text-sm font-semibold text-gray-500 uppercase mb-2">Overall Decision</h3>
        {canApprove ? (
          <>
            <input
              type="text"
              placeholder="Reason / remark (optional)"
              value={overallRemark}
              onChange={(e) => setOverallRemark(e.target.value)}
              className="w-full px-3 py-1.5 border rounded text-sm mb-3"
            />
            <div className="flex items-center gap-3">
              <button
                type="button"
                onClick={() => handleOverallAction('approved')}
                className="px-4 py-1.5 bg-green-600 text-white rounded text-sm font-medium hover:bg-green-700"
              >
                {kyc.status === 'Approve' || kyc.status === 'approved' ? 'Re-approve KYC' : 'Approve KYC'}
              </button>
              <button
                type="button"
                onClick={() => handleOverallAction('rejected')}
                className="px-4 py-1.5 bg-red-600 text-white rounded text-sm font-medium hover:bg-red-700"
              >
                Reject KYC
              </button>
              {/* Per ops request 2026-06-03: no conditions on the Approve/
                  Reject buttons, so the doc-state status hints are commented
                  out too (they read like a precondition). Un-comment to
                  restore the inline indicators. */}
              {/* {hasRejected && <span className="text-xs text-red-600 font-medium">Some documents rejected</span>} */}
              {/* {allApproved && <span className="text-xs text-green-600 font-medium">All uploaded documents approved</span>} */}
            </div>
            {overallError && <p className="mt-2 text-sm text-red-600">{overallError}</p>}
          </>
        ) : (
          <p className="text-sm text-ink-muted">
            Approving or rejecting KYC is restricted to the KYC Approver role. You can view the documents and activity log.
          </p>
        )}
      </div>

      {/* Tabs */}
      <div className="bg-white rounded-lg border">
        <div className="flex border-b">
          <button
            onClick={() => setActiveTab('documents')}
            className={`px-4 py-2 text-sm font-medium border-b-2 ${
              activeTab === 'documents' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
          >
            Documents ({documents.filter((d) => d.uploaded).length}/{documents.length})
          </button>
          <button
            onClick={() => setActiveTab('extras')}
            className={`px-4 py-2 text-sm font-medium border-b-2 ${
              activeTab === 'extras' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
          >
            Additional Uploads ({extraDocs.length})
          </button>
          <button
            onClick={() => setActiveTab('activity')}
            className={`px-4 py-2 text-sm font-medium border-b-2 ${
              activeTab === 'activity' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
          >
            Activity Log ({activityLog.length})
          </button>
        </div>

        <div className="p-4">
          {activeTab === 'documents' && (
            <div className="space-y-6">
              <DocumentSection title={identitySectionLabel}  docs={identityDocs}  kycId={kid} onUpdated={refetch} />
              <DocumentSection title={corporateSectionLabel} docs={corporateDocs} kycId={kid} onUpdated={refetch} />
            </div>
          )}

          {activeTab === 'extras' && (
            <div>
              {extraDocs.length === 0 ? (
                <p className="text-sm text-gray-400 py-4 text-center">
                  No additional uploads. (BizSure director / shareholder docs would appear here when present.)
                </p>
              ) : (
                <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                  {extraDocs.map((d) => (
                    <div key={d.id} className="bg-white border rounded-lg p-3">
                      <div className="text-xs font-semibold text-gray-700 mb-1">
                        {d.docType}
                        {d.docIndex != null && <span className="ml-1 text-gray-400">#{d.docIndex + 1}</span>}
                      </div>
                      <div className="text-xs text-gray-500 mb-2">Policy {d.policyId ?? '-'}</div>
                      {d.url ? (
                        <a
                          href={d.url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="block w-full h-32 bg-gray-100 rounded border flex items-center justify-center hover:bg-gray-200 transition"
                        >
                          {d.url.match(/\.(jpg|jpeg|png|gif|webp)$/i) ? (
                            <img src={d.url} alt={d.docType} className="max-h-full max-w-full object-contain rounded" />
                          ) : (
                            <div className="text-center text-xs text-blue-600">
                              <div className="text-2xl mb-1">&#128196;</div>
                              View
                            </div>
                          )}
                        </a>
                      ) : (
                        <div className="w-full h-32 bg-gray-50 rounded border flex items-center justify-center text-sm text-gray-400">
                          No file
                        </div>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </div>
          )}

          {activeTab === 'activity' && (
            <div className="space-y-2">
              {activityLog.length === 0 ? (
                <p className="text-sm text-gray-400 py-4 text-center">No activity yet.</p>
              ) : (
                activityLog.map((log) => <DomComActivityRow key={log.id} log={log} />)
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  )
}

// Timeline row for the Activity Log tab. Identical layout to the MIS
// page's ActivityLogRow so both reviews look the same; adds an
// expandable "before snapshot" when kyc_activity_log captured
// old_data_json for the row.
function DomComActivityRow({ log }: { log: import('../../api/kyc').KycActivityLog }) {
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

/**
 * Renders one document section (Identity or Corporate). Same per-doc
 * approve / reject / reset UX as the MIS page.
 */
function DocumentSection({
  title, docs, kycId, onUpdated,
}: { title: string; docs: DomComKycDocument[]; kycId: number; onUpdated: () => void }) {
  if (docs.length === 0) return null
  return (
    <div>
      <h3 className="text-sm font-semibold text-gray-600 uppercase mb-3">{title}</h3>
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
        {docs.map((d) => (
          <DocCard key={d.key} doc={d} kycId={kycId} onUpdated={onUpdated} />
        ))}
      </div>
    </div>
  )
}

function DocCard({
  doc, kycId, onUpdated,
}: { doc: DomComKycDocument; kycId: number; onUpdated: () => void }) {
  const verify = useVerifyDomComKycDocument()
  // Per-document verdicts are gated by the same approver permission as the
  // overall decision (backend: permission:customer-kyc-approve).
  const canApprove = canApproveKyc()
  const [remark, setRemark] = useState(doc.remark || '')
  // Local expiry draft — pre-filled from the backend's normalised
  // YYYY-MM-DD value so the date input renders correctly even when the
  // DB stored a Carbon datetime string.
  const [expiry, setExpiry] = useState(doc.expiryDate || '')
  const [showActions, setShowActions] = useState(false)
  const [verifyError, setVerifyError] = useState<string | null>(null)
  const st = DOC_STATUS[doc.status] || DOC_STATUS[0]
  const hasExpiryField = !!doc.expiryField

  async function handleVerify(status: number) {
    setVerifyError(null)
    try {
      await verify.mutateAsync({
        kycId, document: doc.key, status, remark,
        // Only send the expiry param for docs that actually carry one;
        // otherwise leave it undefined so the server skips the column.
        expiryDate: hasExpiryField ? (expiry || '') : undefined,
      })
    } catch (e) {
      setVerifyError(kycActionErrorMessage(e))
      return
    }
    setShowActions(false)
    onUpdated()
  }

  return (
    <div className={`bg-white rounded-lg border p-4 ${!doc.uploaded ? 'opacity-60' : ''}`}>
      <div className="flex items-center justify-between mb-2">
        <h4 className="font-medium text-gray-800 text-sm">{doc.label}</h4>
        <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${st.cls}`}>{st.label}</span>
      </div>

      {doc.uploaded && doc.url ? (
        <a
          href={doc.url}
          target="_blank"
          rel="noopener noreferrer"
          className="block w-full h-40 bg-gray-100 rounded border mb-3 flex items-center justify-center hover:bg-gray-200 transition"
        >
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
      {hasExpiryField && doc.expiryDate && !showActions && (
        <p className="text-xs text-gray-500 mb-2">Expiry: {doc.expiryDate}</p>
      )}

      {doc.uploaded && canApprove && (
        showActions ? (
          <div className="space-y-2">
            <input
              type="text"
              placeholder="Remark..."
              value={remark}
              onChange={(e) => setRemark(e.target.value)}
              className="w-full px-2 py-1.5 border rounded text-sm"
            />
            {hasExpiryField && (
              <div>
                <label className="block text-[11px] font-medium text-gray-500 mb-0.5">Date of Expiry</label>
                <input
                  type="date"
                  value={expiry}
                  onChange={(e) => setExpiry(e.target.value)}
                  className="w-full px-2 py-1.5 border rounded text-sm"
                />
              </div>
            )}
            <div className="flex gap-2">
              <button
                type="button"
                onClick={() => handleVerify(1)}
                disabled={verify.isPending}
                className="flex-1 py-1.5 bg-green-600 text-white rounded text-xs font-medium hover:bg-green-700 disabled:opacity-50"
              >
                Approve
              </button>
              <button
                type="button"
                onClick={() => handleVerify(2)}
                disabled={verify.isPending}
                className="flex-1 py-1.5 bg-red-600 text-white rounded text-xs font-medium hover:bg-red-700 disabled:opacity-50"
              >
                Reject
              </button>
              <button
                type="button"
                onClick={() => handleVerify(0)}
                disabled={verify.isPending}
                className="py-1.5 px-3 bg-gray-200 text-gray-600 rounded text-xs hover:bg-gray-300 disabled:opacity-50"
              >
                Reset
              </button>
              <button
                type="button"
                onClick={() => setShowActions(false)}
                className="py-1.5 px-3 bg-gray-100 text-gray-500 rounded text-xs hover:bg-gray-200"
              >
                Cancel
              </button>
            </div>
            {verifyError && <p className="text-xs text-red-600">{verifyError}</p>}
          </div>
        ) : (
          <button
            type="button"
            onClick={() => setShowActions(true)}
            className="w-full py-1.5 border border-gray-300 rounded text-sm text-gray-600 hover:bg-gray-50 font-medium"
          >
            Verify Document
          </button>
        )
      )}
    </div>
  )
}
