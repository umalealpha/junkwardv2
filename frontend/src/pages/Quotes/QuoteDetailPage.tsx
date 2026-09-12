import DualScrollTable from '../../components/common/DualScrollTable'
import { useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import { useQuoteDetail, useUpdateQuotePremium } from '../../hooks/useQuotes'
import { exportQuotePdf } from '../../api/quotes'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import { fmtPula, fmtDate } from '../../utils/format'

const STATUS_BADGE: Record<string, string> = {
  Active:   'bg-green-100 text-green-700',
  Used:     'bg-blue-100 text-blue-700',
  Draft:    'bg-gray-100 text-gray-600',
  Expired:  'bg-yellow-100 text-yellow-700',
  Rejected: 'bg-red-100 text-red-700',
}

function InfoRow({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="flex justify-between py-1.5 border-b border-line last:border-0">
      <span className="text-ink-muted text-sm">{label}</span>
      <span className="text-sm font-medium text-ink text-right">{value || '—'}</span>
    </div>
  )
}

function fmtCurrency(v: number | null | undefined) {
  if (v == null) return '—'
  return fmtPula(v)
}

export default function QuoteDetailPage() {
  const { id } = useParams<{ id: string }>()
  const quoteId = Number(id)
  const { data: quote, isLoading, error } = useQuoteDetail(quoteId)
  const updatePremiumMutation = useUpdateQuotePremium()

  const [showPremiumModal, setShowPremiumModal] = useState(false)
  const [premiumForm, setPremiumForm] = useState({ type: 'Discount' as 'Discount' | 'Surcharge', value_type: 1 as 1 | 2, value: '', reason: '' })
  const [exporting, setExporting] = useState(false)

  if (isLoading) return <div className="p-6 flex justify-center"><LoadingSpinner size="lg" /></div>
  if (error || !quote) return (
    <div className="p-6">
      <div className="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
        <p className="text-red-700 font-medium">Failed to load quote. It may not exist or the server is unavailable.</p>
        <Link to="/quotes" className="text-blue-600 hover:underline text-sm mt-2 inline-block">Back to Quotes</Link>
      </div>
    </div>
  )

  const isActive = quote.status === 1

  function handlePremiumSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!premiumForm.value || !premiumForm.reason) return
    updatePremiumMutation.mutate(
      { id: quoteId, payload: { ...premiumForm, value: Number(premiumForm.value) } },
      { onSuccess: () => { setShowPremiumModal(false); setPremiumForm({ type: 'Discount', value_type: 1, value: '', reason: '' }) } }
    )
  }

  // V2 only handles DomCom (7, 8) + Engineering/Specialist (16-19).
  // MIS / retail quotes keep their policy-wording + issuance flow on
  // legacy graphite — the Quote Detail page here is read-only for them
  // and the banner below tells the operator where to complete issuance.
  const V2_PRODUCT_IDS = [7, 8, 16,17,18,20,22,24]
  const isMisQuote = quote.product?.id !== null && quote.product?.id !== undefined
    && !V2_PRODUCT_IDS.includes(Number(quote.product.id))

  return (
    <div className="p-6 space-y-6">
      {/* MIS banner — quotes for non-V2 products */}
      {isMisQuote && (
        <div className="bg-amber-50 border-l-4 border-amber-400 p-4 rounded">
          <div className="flex items-start gap-3">
            <span className="text-amber-600 text-xl leading-none">⚠</span>
            <div className="flex-1">
              <h3 className="font-semibold text-amber-900 text-sm">MIS quote — issue on legacy</h3>
              <p className="text-sm text-amber-800 mt-1">
                This quote is for <strong>{quote.product?.name || 'a non-DomCom product'}</strong>.
                MIS / retail policies have their own wording and rating engine and must be
                issued on legacy graphite. This page is read-only for such quotes.
              </p>
              <a
                href="https://graphite.alphadirect.co.bw"
                target="_blank"
                rel="noopener noreferrer"
                className="inline-block mt-2 text-sm font-medium text-amber-900 underline hover:text-amber-950"
              >
                Open legacy graphite →
              </a>
            </div>
          </div>
        </div>
      )}

      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <Link to="/quotes" className="text-sm text-blue-600 hover:underline mb-1 inline-block">&larr; Back to Quotes</Link>
          <h1 className="text-2xl font-bold text-ink flex items-center gap-3">
            Quote: {quote.quoteCode}
            {quote.statusLabel ? (
              <span className={`px-3 py-1 rounded-full text-xs font-semibold ${STATUS_BADGE[quote.statusLabel] ?? 'bg-gray-100 text-gray-600'}`}>
                {quote.statusLabel}
              </span>
            ) : (
              <span className="px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-500 italic">Unknown</span>
            )}
          </h1>
        </div>
        <div className="flex gap-2 flex-wrap">
          {/* Export PDF — always available */}
          <button
            onClick={async () => {
              setExporting(true)
              try {
                const result = await exportQuotePdf(quoteId)
                window.open(result.url, '_blank')
              } catch (err: any) {
                const msg = err?.response?.data?.error || err?.response?.data?.message || 'Failed to generate quote PDF.'
                alert(msg.includes('wkhtmltopdf') || msg.includes('execution time')
                  ? 'PDF generation requires wkhtmltopdf which is only available on the production server. This will work when deployed.'
                  : msg)
              } finally {
                setExporting(false)
              }
            }}
            disabled={exporting}
            className="px-4 py-2 bg-gray-700 text-white rounded-md text-sm font-medium hover:bg-gray-800 disabled:opacity-50 flex items-center gap-1.5"
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
            {exporting ? 'Generating...' : 'Export PDF'}
          </button>

          {/* Actions for active quotes only */}
          {isActive && (
            <>
              <Link to={`/quotes/${quoteId}/edit`} className="px-4 py-2 bg-amber-600 text-white rounded-md text-sm font-medium hover:bg-amber-700 flex items-center gap-1.5">
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                Update Quote
              </Link>
              <button onClick={() => setShowPremiumModal(true)} className="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700">
                Adjust Premium
              </button>
            </>
          )}

          {/* Used indicator when policy exists */}
          {quote.status === 2 && (
            <span className="px-4 py-2 bg-blue-50 text-blue-700 rounded-md text-sm font-medium border border-blue-200">
              Policy Generated
            </span>
          )}

          {/* Link to policy if exists */}
          {quote.policy && (
            <Link to={`/policies/${quote.policy.id}`} className="px-4 py-2 bg-green-600 text-white rounded-md text-sm font-medium hover:bg-green-700">
              View Policy {quote.policy.policyNumber}
            </Link>
          )}
        </div>
      </div>

      {/* Cards grid */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Customer Details */}
        <div className="bg-surface rounded-lg shadow-sm border border-line p-5">
          <h3 className="text-base font-semibold text-ink mb-3 border-b border-line pb-2">Customer Details</h3>
          <InfoRow label="Full Name" value={quote.customer.fullName} />
          <InfoRow label="Email" value={quote.customer.email} />
          <InfoRow label="Cellphone" value={quote.customer.cellphone} />
          <InfoRow label="Gender" value={quote.customer.gender} />
          <InfoRow label="Date of Birth" value={fmtDate(quote.customer.dob)} />
          <InfoRow label="Marital Status" value={quote.customer.maritalStatus} />
          <InfoRow label="Omang" value={quote.customer.omang} />
          <InfoRow label="Passport" value={quote.customer.passport} />
          <InfoRow label="Address" value={quote.customer.address} />
        </div>

        {/* Product & Quote Info */}
        <div className="bg-surface rounded-lg shadow-sm border border-line p-5">
          <h3 className="text-base font-semibold text-ink mb-3 border-b border-line pb-2">Quote Information</h3>
          <InfoRow label="Quote Code" value={quote.quoteCode} />
          <InfoRow label="Status" value={quote.statusLabel
            ? <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_BADGE[quote.statusLabel] ?? 'bg-gray-100 text-gray-600'}`}>{quote.statusLabel}</span>
            : <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500 italic">Unknown</span>} />
          <InfoRow label="Type" value={quote.quoteType} />
          <InfoRow label="Product" value={quote.product.name} />
          <InfoRow label="Plan" value={quote.product.plan} />
          <InfoRow label="Agent" value={quote.agentName} />
          <InfoRow label="Store" value={quote.storeName} />
          <InfoRow label="Expiry Date" value={fmtDate(quote.expiryDate)} />
          <InfoRow label="Created" value={fmtDate(quote.createdAt)} />
          {quote.policy && <InfoRow label="Policy Number" value={<Link to={`/policies/${quote.policy.id}`} className="text-blue-600 hover:underline">{quote.policy.policyNumber}</Link>} />}
        </div>

        {/* Vehicle Details (if motor) */}
        {quote.vehicle && (
          <div className="bg-surface rounded-lg shadow-sm border border-line p-5">
            <h3 className="text-base font-semibold text-ink mb-3 border-b border-line pb-2">Vehicle Details</h3>
            <InfoRow label="Make" value={quote.vehicle.make} />
            <InfoRow label="Model" value={quote.vehicle.model} />
            <InfoRow label="Year" value={quote.vehicle.year} />
            <InfoRow label="Variant" value={quote.vehicle.variant} />
            <InfoRow label="Estimated Value" value={fmtCurrency(quote.vehicle.estimatedValue)} />
            <InfoRow label="Imported" value={quote.vehicle.isImported ? 'Yes' : 'No'} />
            <InfoRow label="Prior Accidents" value={String(quote.vehicle.priorAccidents ?? 0)} />
          </div>
        )}

        {/* Premium Details */}
        <div className="bg-surface rounded-lg shadow-sm border border-line p-5">
          <h3 className="text-base font-semibold text-ink mb-3 border-b border-line pb-2">Premium Details</h3>
          <InfoRow label="Monthly Premium" value={fmtCurrency(quote.premium.monthly)} />
          <InfoRow label="3-Instalment Premium" value={fmtCurrency(quote.premium.threeInstalment)} />
          <InfoRow label="Annual Premium" value={<span className="text-lg font-bold text-green-700">{fmtCurrency(quote.premium.annually)}</span>} />
          <InfoRow label="Premium Rate" value={quote.premium.rate ? `${Number(quote.premium.rate).toFixed(4)}%` : null} />
          <InfoRow label="Discount/Surcharge" value={quote.premium.discountSurcharge ? fmtCurrency(quote.premium.discountSurcharge) : 'None'} />
        </div>
      </div>

      {/* Premium History */}
      {quote.premiumHistory && quote.premiumHistory.length > 0 && (
        <div className="bg-surface rounded-lg shadow-sm border border-line p-5">
          <h3 className="text-base font-semibold text-ink mb-3 border-b border-line pb-2">Premium Update History</h3>
          <DualScrollTable>
            <table className="w-full text-sm">
              <thead className="bg-surface-2 text-ink-muted uppercase text-xs tracking-wider">
                <tr>
                  <th className="px-4 py-2 text-left">Old Premium</th>
                  <th className="px-4 py-2 text-left">New Premium</th>
                  <th className="px-4 py-2 text-left">Adjustment</th>
                  <th className="px-4 py-2 text-left">Reason</th>
                  <th className="px-4 py-2 text-left">Updated By</th>
                  <th className="px-4 py-2 text-left">Date</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {quote.premiumHistory.map(h => (
                  <tr key={h.id} className="hover:bg-surface-2">
                    <td className="px-4 py-2">{fmtCurrency(h.oldValue)}</td>
                    <td className="px-4 py-2 font-medium">{fmtCurrency(h.newValue)}</td>
                    <td className="px-4 py-2">{fmtCurrency(h.discountSurcharge)}</td>
                    <td className="px-4 py-2 truncate max-w-[200px]" title={h.reason ?? ''}>{h.reason || '—'}</td>
                    <td className="px-4 py-2">{h.addedBy || '—'}</td>
                    <td className="px-4 py-2 text-ink-faint">{fmtDate(h.createdAt)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </DualScrollTable>
        </div>
      )}

      {/* Premium Adjustment Modal */}
      {showPremiumModal && (
        <div className="fixed inset-0 bg-black/40 z-50 flex items-start justify-center p-4 pt-10 overflow-y-auto" onClick={() => setShowPremiumModal(false)}>
          <div className="bg-surface rounded-lg shadow-xl p-5 w-full max-w-md max-h-[85vh] overflow-y-auto" onClick={e => e.stopPropagation()}>
            <h3 className="text-lg font-semibold text-ink mb-3">Adjust Premium</h3>
            <form onSubmit={handlePremiumSubmit} className="space-y-4">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-sm font-medium text-ink-muted mb-1">Type</label>
                  <select value={premiumForm.type} onChange={e => setPremiumForm(f => ({ ...f, type: e.target.value as 'Discount' | 'Surcharge' }))}
                    className="w-full px-3 py-2 border border-line rounded-md text-sm">
                    <option value="Discount">Discount</option>
                    <option value="Surcharge">Surcharge</option>
                  </select>
                </div>
                <div>
                  <label className="block text-sm font-medium text-ink-muted mb-1">Value Type</label>
                  <select value={premiumForm.value_type} onChange={e => setPremiumForm(f => ({ ...f, value_type: Number(e.target.value) as 1 | 2 }))}
                    className="w-full px-3 py-2 border border-line rounded-md text-sm">
                    <option value={1}>Flat Amount</option>
                    <option value={2}>Percentage (%)</option>
                  </select>
                </div>
              </div>
              <div>
                <label className="block text-sm font-medium text-ink-muted mb-1">Value</label>
                <input type="number" step="0.01" min="0" value={premiumForm.value}
                  onChange={e => setPremiumForm(f => ({ ...f, value: e.target.value }))}
                  className="w-full px-3 py-2 border border-line rounded-md text-sm" placeholder={premiumForm.value_type === 2 ? 'e.g. 10 for 10%' : 'e.g. 500'} />
              </div>
              <div>
                <label className="block text-sm font-medium text-ink-muted mb-1">Reason</label>
                <textarea value={premiumForm.reason} onChange={e => setPremiumForm(f => ({ ...f, reason: e.target.value }))}
                  className="w-full px-3 py-2 border border-line rounded-md text-sm" rows={2} placeholder="Reason for adjustment..." />
              </div>
              {updatePremiumMutation.isError && (
                <p className="text-sm text-red-600">Failed to update premium. Please try again.</p>
              )}
              <div className="flex justify-end gap-2 pt-2">
                <button type="button" onClick={() => setShowPremiumModal(false)} className="px-4 py-2 border border-line rounded-md text-sm text-ink-muted hover:bg-surface-2">Cancel</button>
                <button type="submit" disabled={updatePremiumMutation.isPending || !premiumForm.value || !premiumForm.reason}
                  className="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
                  {updatePremiumMutation.isPending ? 'Updating...' : 'Apply'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}
