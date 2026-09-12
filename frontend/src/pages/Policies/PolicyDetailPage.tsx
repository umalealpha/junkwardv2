import DualScrollTable from '../../components/common/DualScrollTable'
import Modal from '../../components/common/Modal'
import NumericInput from '../../components/common/NumericInput'
import ClientHealthWidget from './ClientHealthWidget'
import { fmtPula } from '../../utils/format'
import { buildDocFilename, docTypeFromTitle } from '../../utils/filename'
import { motorCoverTypeLabel } from '../../utils/motorCoverType'
import { useState, useEffect, useMemo, useRef, Fragment, type ReactNode } from 'react'
import { useParams, Link, useNavigate, useSearchParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import apiClient from '../../api/client'
import { canCancelProduct, canReinstate, canReversePayment } from '../../api/auth'
import { decidePolicyClaimsWaiver, sendClaimsWaiverOtp, verifyClaimsWaiverOtp, fetchPolicyCoverages, deleteSpecialistCoverage, triggerCollectNow, fetchCollectNowHistory, fetchCollectNowOutstanding, fetchAssignAgentData, updateAssignAgent, fetchPolicyWordingDocs, sendMatiVerificationLink, fetchMatiData, updateMatiData, cancelPolicyDpoContract, exportPolicyActivityLog, updateActionFrequency, PREMIUM_FREQ_OPTIONS } from '../../api/policies'
import type { PolicyCoApplicant } from '../../api/policies'
import type { CollectNowEvent, CollectNowOutstandingResponse, OutstandingPremium } from '../../api/policies'
import {
  fetchRerateData, recalculateRerate, applyDiscountSurcharge as apiApplyDiscountSurcharge,
  applyCustomRate as apiApplyCustomRate, acceptRerate, fetchRerateHistory,
  type RerateData, type RerateRates, type RerateHistoryRow,
} from '../../api/reratePremium'
import {
  usePolicy,
  usePolicyVehicles,
  usePolicyMembers,
  usePolicyCoApplicants,
  usePolicyDevices,
  usePolicyClaims,
  usePolicyTransactions,
  usePolicyRiskAddresses,
  usePolicyKycDocuments,
  usePolicyBankingDocuments,
  usePolicyClaimsWaiver,
  usePolicyLogs,
  usePolicyLedger,
  usePolicyScheduleTransactions,
  usePolicyMati,
  usePolicyAttachments,
  usePolicyAttachmentList,
  usePolicyFileTypes,
  usePolicyTerms,
  usePolicyActions,
  usePolicyReinsurance,
  usePolicySpecialistCoverages,
} from '../../hooks/usePolicies'
import {
  useRealpayBanks,
  useRealpayBranches,
  usePolicyRealpayContracts,
  usePolicyRealpayInstallments,
  usePolicyTransactionLogs,
} from '../../hooks/useRealpay'
import { usePolicyCreateData, useCitiesByState } from '../../hooks/useLookups'
import { useMyProfile } from '../../hooks/useMyProfile'
import { updateCustomer } from '../../api/customer360'
import { cancelPolicyImmediate, fetchCancelFeedbackOptions, fetchPolicyCancelReason, resendCancellationNotice } from '../../api/cancelRequests'
import { moveToRenew, fetchMisRenewFlow, highRiskRenew } from '../../api/renewals'
import {
  addRealpayInstallment,
  updateRealpayInstallment,
  updateRealpayAllInstallments,
  createPolicyRealpayContract,
  cancelPolicyRealpayContract,
  syncPolicyRealpayFromPortal,
  createOfflinePayment,
  fetchTransactionProofUrl,
  reversePolicyTransaction,
  refundPolicyMoney,
  fetchPolicyPaymentConversions,
} from '../../api/realpay'
import type { PaymentConversionRow } from '../../api/realpay'
import { getAtcPolicyShipment } from '../../api/alphaTransit'
import type { AtcPolicyDetail } from '../../api/alphaTransit'
import StatusBadge from '../../components/common/StatusBadge'
import ProgressBar from '../../components/common/ProgressBar'
import type {
  Policy,
  PolicyTransaction,
  PolicyMember,
  PolicyBeneficiary,
  PolicyDevice,
  PolicyVehicle,
  PolicyClaim,
  PolicyRiskAddress,
  SmsEmailLog,
  ActivityLog,
  LedgerAccountEntry,
  LedgerReceivableEntry,
  LedgerInvoiceEntry,
  LedgerCreditNoteEntry,
  LedgerSubEntry,
  ScheduleTransaction,
  PolicyAttachment,
  PolicyAttachmentFile,
  PolicyTerm,
} from '../../api/policies'

type TabKey = 'details' | 'productDetails' | 'customer' | 'actions' | 'vehicles' | 'members' | 'coapplicants' | 'devices'
  | 'coverages' | 'claims' | 'claimsWaiver' | 'transactions' | 'banking' | 'riskAddresses'
  | 'documents' | 'attachments' | 'kycDocuments' | 'matiVerification' | 'logs' | 'ledger' | 'scheduleTransactions' | 'terms'
  | 'notes' | 'reinsurance' | 'collectNow' | 'linkedPolicy'
  | 'addRealpayContract' | 'realpayContracts' | 'realpayTransactions'
  | 'offlinePayments' | 'transactionLogs' | 'paymentConversions' | 'assignAgent' | 'reratePremium'

const ALL_TABS: { key: TabKey; label: string }[] = [
  { key: 'details',              label: 'Policy Details' },
  // Alpha Transit Cover (product 25) only — everything ATC stores for the
  // policy (atc_shipments risk record + courier + ATC-side claims).
  { key: 'productDetails',       label: 'Product Details' },
  { key: 'customer',             label: 'Customer' },
  { key: 'actions',              label: 'Policy Actions' },
  { key: 'vehicles',             label: 'Vehicles' },
  { key: 'members',              label: 'Members / Beneficiaries' },
  // Hospital Cashback Insurance (product 9) only — distinct from the generic
  // Members tab above; premium recalculates on every add/edit/remove here.
  { key: 'coapplicants',         label: 'Co-Applicants' },
  { key: 'devices',              label: 'Devices' },
  { key: 'coverages',            label: 'Coverages' },
  { key: 'claims',               label: 'Claims' },
  // Replaced by the new 'Transaction Logs' tab — kept for rollback only.
  // { key: 'transactions',         label: 'Transactions' },
  { key: 'ledger',               label: 'Ledger' },
  // MIS policies only — see getVisibleTabs. Single-document upload
  // (No Claims Declaration), distinct from the 'claims' tab above which lists
  // actual Claim records. The `claimsWaiver` key is the pre-rename internal
  // identifier and stays put — it is not user-visible.
  { key: 'claimsWaiver',         label: 'No Claims Declaration' },
  { key: 'banking',              label: 'Banking Details' },
  { key: 'riskAddresses',        label: 'Risk Addresses' },
  { key: 'reinsurance',          label: 'Reinsurance' },
  { key: 'linkedPolicy',         label: 'Linked Policy' },
  { key: 'documents',            label: 'Documents' },
  // V8 parity: dedicated Attachments tab that mirrors the legacy admin
  // attachment.blade.php (multi-file rows + delete + document-type select).
  { key: 'attachments',          label: 'Attachments' },
  { key: 'kycDocuments',         label: 'KYC Documents' },
  // Dedicated Mati Verification tab (MIS-only) — V8 policyDetails_View parity.
  { key: 'matiVerification',     label: 'Mati Verification' },
  { key: 'logs',                 label: 'Logs' },
  { key: 'scheduleTransactions', label: 'Schedule Transactions' },
  { key: 'collectNow',           label: '⚡ Collect Now' },
  { key: 'terms',                label: 'Terms' },
  // Rerate Premium — MIS Motor Comprehensive (product 3) only; gated below.
  { key: 'reratePremium',        label: 'Rerate Premium' },
  { key: 'notes',                label: 'Notes' },
  // RealPay tabs — visible only when banking.billing === 'RealPay'.
  { key: 'addRealpayContract',   label: 'Add Realpay Contract' },
  { key: 'realpayContracts',     label: 'Realpay Contract Lists' },
  { key: 'realpayTransactions',  label: 'Realpay Transactions' },
  // Available on every policy regardless of billing method.
  { key: 'offlinePayments',      label: 'Add Offline Payments' },
  { key: 'transactionLogs',      label: 'Transaction Logs' },
  // GRA-0182: V8 "Payment Update Contract" tab parity — payment-method
  // conversion log (e.g. DPO → RealPay): agent, old/new method, date.
  { key: 'paymentConversions',   label: 'Payment Conversions' },
  // Old policy edit page parity: update the policy's agent and store.
  // Visible only with the assign-agent-policy-edit permission, like the
  // old @can('assign-agent-policy-edit') tab.
  { key: 'assignAgent',          label: 'Assign Agent' },
]

/**
 * MIS = Motor Insurance Scheme / personal retail lines. In graphiteBWV8 these
 * are products 1-6, 9, 12, 13. COMG/DOMG = 7, 8, 16-23.
 */
const MIS_PRODUCT_IDS = [1, 2, 3, 4, 5, 6, 9, 12, 13]

/** Alpha Transit Cover (courier goods-in-transit) — GoodsInTransitController::PRODUCT_ID. */
const ALPHA_TRANSIT_PRODUCT_ID = 25

/**
 * MIS products that expose the dedicated "Mati Verification" tab. MetaMap
 * (Mati) KYC is only run on these retail/personal lines, so the tab is hidden
 * everywhere else. (graphiteBWV8 surfaced the tab on the same MIS products.)
 */
const MATI_PRODUCT_IDS = [1, 2, 3, 4, 5, 6, 9, 10]

/**
 * DOMG/COMG (Domestic + Commercial + their Engineering/Specialist variants).
 * For these products the Realpay tabs are visible unconditionally — no
 * banking.billing check, unlike MIS which gates by RealPay billing.
 *   7  = Commercial Insurance
 *   8  = Domestic Insurance
 *   16 = Commercial Engineering
 *   17 = Commercial Specialist
 *   18 = Domestic Engineering
 *   19 = Domestic Specialist
 *   20, 22 = additional specialist lines
 */
const DOMG_COMG_PRODUCT_IDS = [7, 8, 16, 17, 18, 19, 20, 22, 23, 24]

/**
 * Products with coverages, risk addresses, reinsurance, and the full Actions workflow.
 * 7 = Commercial Insurance (DomCom)
 * 8 = Domestic Insurance  (DomCom)
 * 16 = Commercial Engineering
 * 17 = Commercial Specialist
 * 18 = Domestic Engineering
 * 19 = Domestic Specialist
 */
const COVERAGE_PRODUCT_IDS = [7, 8, 16,17,18,20,22,23,24]

/**
 * Transaction types that may be assigned via the Edit Transaction modal.
 * Mirrors the set the backend updateTransaction endpoint validates against —
 * i.e. every type is assignable, on a QUOTE *and* on an already-ISSUED action,
 * so an operator can correct a mis-keyed transaction type without unissuing.
 * NEWBUSINESS is selectable so a wrongly-picked base transaction (e.g. UW
 * chose REISSUE for a New Business) can be corrected — it needs no reason.
 * No type is a dead end: an action that is already NEWBUSINESS or RENEW can
 * still be re-opened here, for every product, so a mis-keyed effective period
 * stays correctable. The backend only withholds the premium re-rate, never
 * the edit itself.
 */
const EDITABLE_TXN_TYPES = ['NEWBUSINESS', 'RENEW', 'ENDORSE', 'CANCEL', 'REINSTATE', 'ANNIVERSARY-RENEW', 'EXTENSION-COVER', 'ENDORSE-RENEW', 'REISSUE', 'EXPIRE']

/** Engineering and Specialist products have product-specific coverage tables */
const SPECIALIST_PRODUCT_IDS = [16,17,18,20,22,23,24]

/**
 * MIS retail products that expose the inline "Cancel Policy" button on the
 * policy view page (parity with the old Graphite policy view). Submitting the
 * cancellation feedback cancels the policy immediately and cancels its
 * schedule/contract. Kept in sync with the backend
 * CancelRequestController::IMMEDIATE_CANCEL_PRODUCT_IDS allow-list.
 */
const CANCELLABLE_PRODUCT_IDS = [1, 2, 3, 4, 5, 9]

/** Filter visible tabs based on product type — avoids showing irrelevant tabs and unnecessary API calls */
function getVisibleTabs(policy: Policy): { key: TabKey; label: string }[] {
  const productType = policy.product?.type
  const productId = policy.product?.id ?? (policy as any).product_id ?? 0
  const hasVehicle = policy.hasVehicle || policy.product?.hasVehicle
  const hasMember = policy.hasMember || policy.product?.hasMember
  const isRealPay = policy.banking?.billing === 'RealPay'
  // graphiteBWV8 parity: the cellphone / Mobile & Electronic Device line is
  // product_id 5. product.type isn't always the literal 'Cellphone' for it, so
  // key off the product id (type-string kept as a fallback).
  const isCellphone = productType === 'Cellphone' || productId === 5
  const isActive = policy.status === 1
  const hasCoverages = COVERAGE_PRODUCT_IDS.includes(productId)
  const isMIS = MIS_PRODUCT_IDS.includes(productId)


  return ALL_TABS.filter((tab) => {
    switch (tab.key) {
      case 'productDetails':
        // Alpha Transit Cover only — the shipment risk record lives in
        // atc_shipments, not on the legacy policy tables.
        return productId === ALPHA_TRANSIT_PRODUCT_ID
      case 'actions':
        return !isMIS // MIS policies don't need the submit/approve/issue workflow
      case 'vehicles':
        return !!hasVehicle || hasCoverages // DomCom has motor vehicles under coverages
      case 'members':
        return !!hasMember
      case 'coapplicants':
        return productId === 9
      case 'devices':
        return isCellphone
      case 'banking':
        // Always visible — the Customer Banking tab shows on every policy
        // regardless of billing method, and is not permission-gated.
        return true
      case 'coverages':
        // Tab hidden — coverage viewing/editing has moved under Policy Actions
        // (the embedded read-only display + "Edit Coverages" button that opens
        // the wizard scoped to the selected action). Keeping the switch case
        // for future re-enable; just force false for now.
        return false && hasCoverages
      case 'riskAddresses':
        return hasCoverages
      case 'reinsurance':
        return !isMIS // graphiteBWV8: reinsurance only for commercial/domestic lines
      case 'linkedPolicy':
        // Hidden on COM/DOM (product 7, 8) — not used for those lines.
        return productId !== 7 && productId !== 8
      case 'notes':
        // Hidden on COM/DOM (product 7, 8) — notes captured per-transaction instead.
        return productId !== 7 && productId !== 8
      case 'claims':
        return isActive
      case 'ledger':
        return true // Show for all
      case 'claimsWaiver':
        return isMIS // MIS retail lines only
      case 'scheduleTransactions':
        // Show if currently DPO OR there's historical DPO schedule data, so the
        // history stays visible after a payment-method switch (read-only then).
        return policy.billingType === 'DPO' || policy.banking?.billing === 'DPO' || !!(policy as any).hasDpoSchedule
      case 'collectNow':
        return isActive // only show for active (status=1) policies
      case 'terms':
        return productId === 3
      case 'matiVerification':
        // MIS retail lines only — MetaMap (Mati) KYC isn't run elsewhere.
        return MATI_PRODUCT_IDS.includes(productId)
      case 'reratePremium':
        // V8 parity: rerate is only available for Motor Comprehensive (id 3),
        // and additionally gated by the 'Premium-Rerate Premium' permission.
        if (productId !== 3) return false
        try {
          const perms = JSON.parse(localStorage.getItem('user_permissions') || '[]') as string[]
          return perms.length === 0 || perms.includes('Premium-Rerate Premium')
        } catch { return true }
      case 'realpayContracts':
      case 'realpayTransactions':
        // Always visible — the Realpay contract list and installments
        // (Realpay Transactions) tabs show on every policy regardless of
        // billing method, and are not permission-gated.
        return true
      case 'addRealpayContract':
        // DOMG/COMG (products 7,8,16-22): always show — these lines bill
        // through RealPay regardless of the customer_banking.billing flag.
        // MIS / other products: gate by banking.billing === 'RealPay'.
        return DOMG_COMG_PRODUCT_IDS.includes(productId) || !!isRealPay
      case 'offlinePayments':
      case 'transactionLogs':
        // Offline payments and the transaction log are available for any
        // policy with active premiums to capture.
        return true
      case 'paymentConversions':
        // GRA-0182: always visible; the tab shows an empty state when the
        // policy has never had its payment method converted. Mirrors the V8
        // "Payment Update Contract" tab (which only appeared once a
        // conversion existed) but keeps discovery simple in V2.
        return true
      case 'assignAgent': {
        // Permission parity with the old edit page's
        // @can('assign-agent-policy-edit') tab gate.
        try {
          const perms = JSON.parse(localStorage.getItem('user_permissions') || '[]') as string[]
          return perms.length === 0 || perms.includes('assign-agent-policy-edit') || perms.includes('assign-agent-policy-list')
        } catch { return true }
      }
      default:
        return true
    }
  })
}

/**
 * Cancel-policy feedback modal — a React port of the old Graphite policy view's
 * cancel feedback form. Collects a reason (and the relevant follow-up: hardship
 * circumstances, the insurer they're moving to, or a free-text reason), then
 * cancels the policy immediately via POST /policies/{id}/cancel.
 */
function CancelPolicyModal({
  policyId,
  policyNumber,
  onClose,
  onCancelled,
}: {
  policyId: number
  policyNumber: string
  onClose: () => void
  onCancelled: () => void
}) {
  // DB-driven cancellation reasons (same customer_feedback_options source the
  // start frontend uses). 'other' is an appended free-text option.
  const { data: options = [], isLoading: optionsLoading, isError: optionsError } = useQuery({
    queryKey: ['cancelFeedbackOptions'],
    queryFn: fetchCancelFeedbackOptions,
    staleTime: 5 * 60 * 1000,
  })

  const [reason, setReason] = useState('')        // picked option name, or 'other'
  const [circumstances, setCircumstances] = useState('') // input_type 1 sub-option
  const [otherCompany, setOtherCompany] = useState('')   // input_type 2 sub-option
  const [otherReason, setOtherReason] = useState('')     // free text for 'Other'
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [confirming, setConfirming] = useState(false)
  // Contracts the cancellation could NOT stop. The policy is cancelled, so this
  // is not an error — but the customer can still be debited, so the agent is
  // told rather than shown a silent success.
  const [realpayStuck, setRealpayStuck] = useState<string[] | null>(null)

  const selected = options.find(o => o.name === reason)
  const selType = selected ? Number(selected.input_type) : 0
  const isOther = reason === 'other'
  // Only render/require a sub-field when the option actually carries sub-options.
  const selHasSubs = (selected?.suboptions?.length ?? 0) > 0

  const pickReason = (val: string) => {
    setReason(val)
    setCircumstances(''); setOtherCompany(''); setOtherReason('')
    setError(null)
  }

  const validate = (): string | null => {
    if (!reason) return 'Please select a reason for cancellation.'
    if (isOther && otherReason.trim().length < 10) return 'Please describe the reason (at least 10 characters).'
    if (!isOther && selType === 1 && selHasSubs && !circumstances) return 'Please select the circumstances.'
    if (!isOther && selType === 2 && selHasSubs && !otherCompany) return 'Please select an option.'
    return null
  }

  const handleReviewClick = () => {
    const v = validate()
    if (v) { setError(v); return }
    setError(null)
    setConfirming(true)
  }

  const handleConfirmedSubmit = async () => {
    setSubmitting(true)
    setError(null)
    try {
      const result = await cancelPolicyImmediate(policyId, {
        reason,
        circumstances: !isOther && selType === 1 && selHasSubs ? circumstances : null,
        other_company: !isOther && selType === 2 && selHasSubs ? otherCompany : (isOther ? otherReason.trim() : null),
      })

      const stuck = result?.payment_cancel?.realpay?.failed ?? []
      if (stuck.length > 0) {
        // The policy IS cancelled; only the debit order is outstanding. Hold the
        // modal open so the agent sees it, instead of closing on a success that
        // still leaves the customer being debited.
        setRealpayStuck(stuck)
        setSubmitting(false)
        return
      }

      onCancelled()
    } catch (e: any) {
      setError(e?.response?.data?.error || e?.message || 'Failed to cancel policy.')
      setSubmitting(false)
      setConfirming(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10" onClick={(e) => { if (e.target === e.currentTarget) onClose() }}>
      <div className="bg-surface rounded-lg shadow-xl w-full max-w-lg max-h-[85vh] overflow-y-auto">
        <div className="flex items-center justify-between px-5 py-3 border-b border-line sticky top-0 bg-surface z-10">
          <h2 className="text-base font-semibold text-ink">Cancel Policy {policyNumber}</h2>
          <button onClick={onClose} disabled={submitting} className="text-ink-faint hover:text-ink-muted text-xl leading-none">&times;</button>
        </div>

        {realpayStuck ? (
          /* ── Step 3: cancelled, but the debit order is still live ── */
          <div className="p-5 space-y-4">
            <div className="flex items-start gap-3 p-4 bg-status-warning-bg border border-status-warning-fg rounded-lg">
              <svg className="w-5 h-5 text-status-warning-fg mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
              </svg>
              <div>
                <p className="text-sm font-semibold text-status-warning-fg">
                  Policy cancelled — but the RealPay debit order is still active.
                </p>
                <p className="text-sm text-status-warning-fg mt-1">
                  RealPay would not confirm cancellation of contract{realpayStuck.length > 1 ? 's' : ''}{' '}
                  <span className="font-medium">{realpayStuck.join(', ')}</span>, so the customer can still be debited.
                </p>
                <p className="text-sm text-status-warning-fg mt-1">
                  It has been queued for automatic retry (hourly). If it is still active tomorrow, cancel it from the
                  RealPay tab on this policy and raise it with IT.
                </p>
              </div>
            </div>

            <div className="flex justify-end pt-1">
              <button
                onClick={onCancelled}
                className="px-4 py-1.5 text-sm font-medium text-white bg-primary rounded-md hover:opacity-90"
              >
                Understood
              </button>
            </div>
          </div>
        ) : confirming ? (
          /* ── Step 2: confirmation ── */
          <div className="p-5 space-y-4">
            <div className="flex items-start gap-3 p-4 bg-status-danger-bg border border-status-danger-fg rounded-lg">
              <svg className="w-5 h-5 text-status-danger-fg mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
              </svg>
              <div>
                <p className="text-sm font-semibold text-status-danger-fg">This action cannot be undone.</p>
                <p className="text-sm text-status-danger-fg mt-1">
                  Cancelling <span className="font-medium">{policyNumber}</span> will set the policy status to
                  Cancelled and stop all scheduled debit orders and contracts.
                </p>
              </div>
            </div>

            <div className="bg-surface-2 border border-line rounded-md px-4 py-3 text-sm text-ink-muted">
              <span className="font-medium text-ink-muted uppercase text-xs tracking-wide block mb-1">Cancellation reason</span>
              {isOther ? otherReason.trim() : reason}
            </div>

            {error && <div className="text-sm text-status-danger-fg bg-status-danger-bg border border-status-danger-fg rounded-md px-3 py-2">{error}</div>}

            <div className="flex justify-end gap-2 pt-1">
              <button
                onClick={() => setConfirming(false)}
                disabled={submitting}
                className="px-3 py-1.5 text-sm font-medium text-ink-muted border border-line rounded-md hover:bg-surface-2 disabled:opacity-50"
              >
                Go Back
              </button>
              <button
                onClick={handleConfirmedSubmit}
                disabled={submitting}
                className="px-4 py-1.5 text-sm font-medium text-white bg-status-danger-fg rounded-md hover:bg-status-danger-fg disabled:opacity-50"
              >
                {submitting ? 'Cancelling…' : 'Yes, Cancel Policy'}
              </button>
            </div>
          </div>
        ) : (
          /* ── Step 1: reason selection ── */
          <>
            <div className="p-5 space-y-4">
              <p className="text-sm text-ink-muted">Please tell us why the customer is cancelling.</p>

              {optionsLoading && <p className="text-sm text-ink-muted">Loading reasons…</p>}
              {optionsError && <p className="text-sm text-status-danger-fg">Couldn't load cancellation reasons. Please try again.</p>}

              <div className="space-y-2">
                {options.map(o => {
                  const picked = reason === o.name
                  const type = Number(o.input_type)
                  const hasSubs = (o.suboptions?.length ?? 0) > 0
                  return (
                    <div key={o.id} className={`rounded-md border p-2.5 ${picked ? 'border-primary bg-status-info-bg' : 'border-line'}`}>
                      <label className="flex items-start gap-2 text-sm text-ink-muted cursor-pointer">
                        <input type="radio" name="cancel-reason" className="mt-0.5" checked={picked} onChange={() => pickReason(o.name)} />
                        <span>{o.name}</span>
                      </label>

                      {picked && type === 1 && hasSubs && (
                        <div className="mt-2 ml-6 space-y-1.5">
                          {o.description && <p className="text-xs font-medium text-ink-muted">{o.description}</p>}
                          {o.suboptions.map(s => (
                            <label key={s.name} className="flex items-center gap-2 text-sm text-ink-muted cursor-pointer">
                              <input type="radio" name="cancel-circumstances" checked={circumstances === s.name} onChange={() => setCircumstances(s.name)} />
                              <span className="capitalize">{s.name}</span>
                            </label>
                          ))}
                        </div>
                      )}

                      {picked && type === 2 && hasSubs && (
                        <div className="mt-2 ml-6">
                          {o.description && <p className="text-xs font-medium text-ink-muted mb-1">{o.description}</p>}
                          <select
                            value={otherCompany}
                            onChange={e => setOtherCompany(e.target.value)}
                            className="w-full border border-line rounded-md px-3 py-2 text-sm"
                          >
                            <option value="">Select…</option>
                            {o.suboptions.map(s => <option key={s.name} value={s.name}>{s.name}</option>)}
                          </select>
                        </div>
                      )}
                    </div>
                  )
                })}

                {/* Appended free-text "Other" option */}
                <div className={`rounded-md border p-2.5 ${isOther ? 'border-primary bg-status-info-bg' : 'border-line'}`}>
                  <label className="flex items-start gap-2 text-sm text-ink-muted cursor-pointer">
                    <input type="radio" name="cancel-reason" className="mt-0.5" checked={isOther} onChange={() => pickReason('other')} />
                    <span>Other</span>
                  </label>
                  {isOther && (
                    <div className="mt-2 ml-6">
                      <textarea
                        value={otherReason}
                        onChange={e => setOtherReason(e.target.value)}
                        rows={3}
                        className="w-full border border-line rounded-md px-3 py-2 text-sm"
                        placeholder="Reason for cancellation (at least 10 characters)"
                      />
                    </div>
                  )}
                </div>
              </div>

              {error && <div className="text-sm text-status-danger-fg bg-status-danger-bg border border-status-danger-fg rounded-md px-3 py-2">{error}</div>}
            </div>
            <div className="flex justify-end gap-2 px-5 py-3 border-t border-line">
              <button
                onClick={onClose}
                className="px-3 py-1.5 text-sm font-medium text-ink-muted border border-line rounded-md hover:bg-surface-2"
              >
                Close
              </button>
              <button
                onClick={handleReviewClick}
                className="px-3 py-1.5 text-sm font-medium text-white bg-status-danger-fg rounded-md hover:bg-status-danger-fg"
              >
                Continue
              </button>
            </div>
          </>
        )}
      </div>
    </div>
  )
}

function ReinstatePolicyModal({
  policyId,
  policyNumber,
  productId,
  onClose,
  onReinstated,
}: {
  policyId: number
  policyNumber: string
  productId: number
  onClose: () => void
  onReinstated: () => void
}) {
  const [reinstateType, setReinstateType] = useState<'reinstate_fresh' | 'Reinstate_arrears'>('reinstate_fresh')
  const [note, setNote] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const isMisPolicy = MIS_PRODUCT_IDS.includes(productId)

  const handleSubmit = async () => {
    setSubmitting(true)
    setError(null)
    try {
      const token = localStorage.getItem('sanctum_token') || ''
      const apiBase = `${(import.meta as any).env.VITE_API_URL}/api/v1`
      const endpoint = isMisPolicy
        ? `${apiBase}/policies/${policyId}/reinstate-legacy`
        : `${apiBase}/policies/${policyId}/reinstate-create`
      const r = await fetch(endpoint, {
        method: 'POST',
        headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ reinstate_type: reinstateType, note: note.trim() || undefined }),
      })
      const d = await r.json()
      if (!r.ok) {
        setError(d.error || d.message || 'Reinstatement failed.')
        setSubmitting(false)
        return
      }
      onReinstated()
    } catch (e: any) {
      setError(e?.message || 'Network error. Please try again.')
      setSubmitting(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10" onClick={(e) => { if (e.target === e.currentTarget) onClose() }}>
      <div className="bg-surface rounded-lg shadow-xl w-full max-w-md">
        <div className="flex items-center justify-between px-5 py-3 border-b border-line sticky top-0 bg-surface z-10">
          <h2 className="text-base font-semibold text-ink">Reinstate Policy {policyNumber}</h2>
          <button onClick={onClose} disabled={submitting} className="text-ink-faint hover:text-ink-muted text-xl leading-none">&times;</button>
        </div>
        <div className="p-5 space-y-4">
          <p className="text-sm text-ink-muted">Select how the policy should be reinstated.</p>

          <div className="space-y-2">
            {([
              { value: 'reinstate_fresh',   label: 'Fresh',   desc: 'Reinstate at full premium — customer restarts collection from today.' },
              { value: 'Reinstate_arrears', label: 'Arrears', desc: 'Reinstate with arrears — customer pays missed premiums before collecting resumes.' },
            ] as const).map(opt => (
              <label
                key={opt.value}
                className={`flex items-start gap-3 rounded-md border p-3 cursor-pointer transition ${
                  reinstateType === opt.value ? 'border-status-success-fg bg-status-success-bg' : 'border-line hover:border-line'
                }`}
              >
                <input
                  type="radio"
                  name="reinstate-type"
                  className="mt-0.5"
                  checked={reinstateType === opt.value}
                  onChange={() => setReinstateType(opt.value)}
                />
                <span>
                  <span className="block text-sm font-medium text-ink">{opt.label}</span>
                  <span className="block text-xs text-ink-muted mt-0.5">{opt.desc}</span>
                </span>
              </label>
            ))}
          </div>

          <div>
            <label className="block text-xs font-medium text-ink-muted mb-1">Note (optional)</label>
            <textarea
              rows={2}
              value={note}
              onChange={e => setNote(e.target.value)}
              placeholder="Reason for reinstatement…"
              className="w-full text-sm border border-line rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-status-success-fg resize-none"
            />
          </div>

          {error && (
            <div className="text-sm text-status-danger-fg bg-status-danger-bg border border-status-danger-fg rounded-md px-3 py-2">{error}</div>
          )}
        </div>
        <div className="flex justify-end gap-2 px-5 py-3 border-t border-line">
          <button
            onClick={onClose}
            disabled={submitting}
            className="px-3 py-1.5 text-sm font-medium text-ink-muted border border-line rounded-md hover:bg-surface-2 disabled:opacity-50"
          >
            Cancel
          </button>
          <button
            onClick={handleSubmit}
            disabled={submitting}
            className="px-3 py-1.5 text-sm font-medium text-white bg-status-success-fg rounded-md hover:bg-status-success-fg disabled:opacity-50"
          >
            {submitting ? 'Reinstating…' : 'Reinstate Policy'}
          </button>
        </div>
      </div>
    </div>
  )
}

export default function PolicyDetailPage() {
  const { id } = useParams<{ id: string }>()
  const policyId = Number(id)
  const { data: policy, isLoading, error } = usePolicy(policyId)
  const { data: actionsData } = usePolicyActions(policyId, true)
  // When the policy is cancelled, surface the stored cancellation reason
  // (newest cancel_policy_requests row). Only fetched for cancelled policies.
  const { data: cancelReason } = useQuery({
    queryKey: ['policyCancelReason', policyId],
    queryFn: () => fetchPolicyCancelReason(policyId),
    enabled: Number.isFinite(policyId) && policy?.status === 2,
    staleTime: 60 * 1000,
  })
  // Initial tab can be driven by ?tab=<key> so deep-links from elsewhere
  // (e.g. Edit Policy coverage section's "+ Add New Vehicle") land on the
  // right tab. ?add=1 then asks that tab to auto-open its add modal.
  const [searchParams] = useSearchParams()
  const initialTab = (searchParams.get('tab') as TabKey) || 'details'
  const autoOpenAdd = searchParams.get('add') === '1'
  const [activeTab, setActiveTab] = useState<TabKey>(initialTab)
  const qc = useQueryClient()
  const [showCancelModal, setShowCancelModal] = useState(false)
  const [showReinstateModal, setShowReinstateModal] = useState(false)
  // Resend cancellation notice (SMS / email) for an already-cancelled policy —
  // parity with the old Edit Policy page's "Send policy cancelled email/sms".
  const [resending, setResending] = useState<null | 'email' | 'sms'>(null)
  const handleResendCancellation = async (type: 'email' | 'sms') => {
    setResending(type)
    try {
      const res = await resendCancellationNotice(Number(policyId), type)
      window.alert(res?.message || (type === 'email' ? 'Cancellation email sent.' : 'Cancellation SMS sent.'))
    } catch (e: any) {
      window.alert(e?.response?.data?.message || e?.response?.data?.error || e?.message || `Failed to send cancellation ${type}.`)
    } finally {
      setResending(null)
    }
  }
  // Reinstate right — admin/manager OR the policy_reinstate permission
  // (fail-closed; the old permissive "empty perms = allow" fallback is gone).
  // The product-scoped cancel right is computed below, once productId is known.
  const canReinstatePolicy = canReinstate()
  // GRA-0194 — permission to cancel a LIVE DPO mandate (DPO→RealPay double-debit
  // fix). Mirrors the backend cancel-dpo-contract route gate
  // (permission:policy-suspend_payment_dpo). Same admin-fallback (empty perms = allow).
  const canCancelDpoContract = useMemo(() => {
    try {
      const perms = JSON.parse(localStorage.getItem('user_permissions') || '[]') as string[]
      return perms.length === 0 || perms.includes('policy-suspend_payment_dpo')
    } catch { return true }
  }, [])

  // "Move to Renew Table" header action — mirrors the legacy Graphite policy
  // view button; POSTs to the MIS renew API which creates the policy_renewals
  // row (and, for MIS, runs the rerate). On success the renewal flow becomes
  // available via the "Renew" button.
  const [movingToRenew, setMovingToRenew] = useState(false)
  const handleMoveToRenew = async () => {
    if (!window.confirm('Move this policy to the renew table?')) return
    setMovingToRenew(true)
    try {
      const res = await moveToRenew(Number(policyId))
      qc.invalidateQueries({ queryKey: ['policy', policyId] })
      window.alert(res?.message || 'Policy has been moved to renew table successfully.')
    } catch (e: any) {
      window.alert(e?.response?.data?.message || e?.message || 'Failed to move policy to renew table.')
    } finally {
      setMovingToRenew(false)
    }
  }

  // High-risk customer yearly renewal (all products EXCEPT Motor Comp 3).
  // Creates a new term, then routes to the RealPay contract screen so staff
  // create the new payment contract. Backend re-checks the high-risk/product/
  // due-date gate. Button visibility is computed after the policy loads (below).
  const [highRiskRenewing, setHighRiskRenewing] = useState(false)
  const handleHighRiskRenew = async () => {
    if (!window.confirm('Renew this policy for another year? This creates a new term; you will then create the new payment contract.')) return
    setHighRiskRenewing(true)
    try {
      const res = await highRiskRenew(Number(policyId))
      await qc.invalidateQueries({ queryKey: ['policy', policyId] })
      window.alert(res?.message || 'Renewal term created. Now create the new payment contract.')
      setActiveTab('realpayContracts')
    } catch (e: any) {
      window.alert(e?.response?.data?.error || e?.response?.data?.message || e?.message || 'Failed to renew policy.')
    } finally {
      setHighRiskRenewing(false)
    }
  }

  // Renewal lifecycle state for the header buttons. MUST be declared before the
  // loading/error early returns below — hooks can't run conditionally (React
  // error #310). `enabled` keeps it dormant until a Motor Comp policy has loaded.
  const { data: renewFlow } = useQuery({
    queryKey: ['misRenewFlow', Number(policyId)],
    queryFn: () => fetchMisRenewFlow(Number(policyId)),
    enabled: !!policy && ((policy.product?.id ?? (policy as any).product_id ?? 0) === 3) && policy.status !== 2,
    retry: false,
    staleTime: 60_000,
  })

  if (isLoading) {
    return (
      <div className="p-6">
        <div className="bg-surface rounded-lg border border-line shadow-sm p-8 mt-4">
          <ProgressBar isLoading label="Loading policy details" className="max-w-sm mx-auto" />
        </div>
      </div>
    )
  }

  if (error || !policy) {
    return (
      <div className="p-6">
        <div className="bg-status-danger-bg border border-status-danger-fg rounded-lg p-6 text-center text-status-danger-fg">
          Failed to load policy. It may not exist or the server is unavailable.
        </div>
        <Link to="/policies" className="inline-block mt-4 text-sm text-brand-navy hover:underline">
          &larr; Back to Policies
        </Link>
      </div>
    )
  }

  const productId = policy.product?.id ?? (policy as any).product_id ?? 0

  // Product-scoped cancel right: Motor Comprehensive (product 3) vs the instant
  // book use separate permissions; admin/manager bypass; fail-closed otherwise.
  const canCancelPolicy = canCancelProduct(productId)

  // Renew header actions ("Move to Renew" + "Renew") — Motor Comprehensive
  // (product_id 3) only, since the renew flow (vehicle details + premium rerate
  // via the policy_renewals path) is Motor-Comp shaped. Hidden once the policy
  // is already cancelled (status 2).
  //
  // Visibility is gated on the actual renewal lifecycle so the buttons stop
  // showing once a policy has been renewed:
  //   • "Move to Renew" — only when the policy is due (its coverage end date is
  //     today or past) AND it hasn't been moved to renew yet (canMoveToRenew).
  //   • "Renew"         — only when a renewal is pending (moved to renew but not
  //     yet renewed → canMoveToRenew === false).
  // After a renewal completes, endDate jumps ~1yr ahead (covered going forward)
  // and canMoveToRenew flips back to true, so both buttons hide until the next
  // cycle is actually due.
  const isMotorComp = productId === 3 && policy.status !== 2
  // Policy is still covered going forward when its end date is in the future.
  const coveredForward = !!policy.endDate && new Date(policy.endDate) > new Date()
  const showMoveToRenew = isMotorComp && renewFlow?.canMoveToRenew === true && !coveredForward
  const showRenewButton = isMotorComp && renewFlow?.canMoveToRenew === false
  const showRenewActions = showMoveToRenew || showRenewButton

  // High-risk yearly renewal — shown only for HIGH-RISK customers, on every
  // active product EXCEPT Motor Comp (3, which keeps its own MIS renew flow),
  // once the policy is due (within 30 days of / after its term end date).
  const isHighRiskCustomer = !!(policy.profile?.isPep || policy.profile?.isPepRelated || policy.profile?.isHighRiskCountry)
  const highRiskRenewDue = (() => {
    if (!policy.endDate) return false
    const opensAt = new Date(policy.endDate)
    opensAt.setDate(opensAt.getDate() - 30)
    return new Date() >= opensAt
  })()
  const showHighRiskRenew = isHighRiskCustomer && productId !== 3 && policy.status === 1 && highRiskRenewDue

  // Pre-compute action state badge values (IIFEs in JSX confuse the TSX parser)
  const actionStatus = actionsData?.current?.status ?? null
  const showActionBadge = !!actionStatus && COVERAGE_PRODUCT_IDS.includes(policy.product?.id ?? 0)
  const actionBadgeCls = actionStatus === 'ISSUED'      ? 'bg-status-success-bg text-status-success-fg ring-status-success-fg' :
                         actionStatus === 'IN_APPROVAL' ? 'bg-status-warning-bg text-status-warning-fg ring-status-warning-fg' :
                         actionStatus === 'APPROVED'    ? 'bg-status-success-bg text-status-success-fg ring-status-success-fg' :
                         actionStatus === 'QUOTE'       ? 'bg-status-info-bg text-primary ring-primary' :
                         actionStatus === 'REJECTED'    ? 'bg-status-danger-bg text-status-danger-fg ring-status-danger-fg' :
                                                         'bg-surface-2 text-ink-muted ring-line'

  return (
    <div className="flex flex-col h-full">
      {/* Client Health Widget — sits above the sticky chrome so it's the
          first thing visible when a policy opens. CFO dashboard-style
          summary; non-sticky on purpose so the rest of the chrome can
          stick once the user starts scrolling. */}
      <ClientHealthWidget policyId={policyId} />

      {/* Sticky Header + Tabs */}
      <div className="sticky top-0 z-30 bg-surface border-b border-line shadow-sm px-6 pt-3 pb-0">
        {/* Back link + dates on same row */}
        <div className="flex items-center justify-between mb-2">
          <Link to="/policies" className="text-xs text-brand-navy hover:underline">&larr; Back To Policies</Link>
          <div className="flex items-center gap-4 text-xs text-ink-muted">
            {policy.policyActivatedDate && <span title={`Activated: ${fmtDate(policy.policyActivatedDate)}`}>Activated: {fmtDate(policy.policyActivatedDate)}</span>}
            {policy.cancelledDate && <span className="text-status-danger-fg" title={`Cancelled: ${fmtDate(policy.cancelledDate)}`}>Cancelled: {fmtDate(policy.cancelledDate)}</span>}
            <span title={`Created: ${fmtDate(policy.createdAt)}`}>Created: {fmtDate(policy.createdAt)}</span>
          </div>
        </div>

        {/* Policy title + meta */}
        <div className="flex items-center gap-3 pb-2">
          <h1 className="text-xl font-bold font-heading text-ink">Policy {policy.policyNumber}</h1>
          <StatusBadge status={policy.statusLabel} />
          {showActionBadge && actionStatus && (
            <span className={`px-2.5 py-0.5 rounded-full text-xs font-semibold ring-1 ${actionBadgeCls}`}>
              {actionStatus.replace('_', ' ')}
            </span>
          )}
          {/* UAT 2026-06-03 (Satyajeet): the V2 edit wizard is hardwired to the
              DomCom shape (Risk Addresses + policy_coverages). MIS retail products
              (ACD / HCB / Legal / Mobile&Electronic / Third Party Car) don't use
              that schema and don't have a working V2 edit flow yet — opening it
              dumps the user into an empty Risk Addresses section. Gate the
              top-level Edit button to products that actually use the wizard.
              CSRs continue to edit MIS policies in V1 admin until the dedicated
              V2 MIS edit flow ships. */}
          {COVERAGE_PRODUCT_IDS.includes(productId) && (
            <Link
              to={`/policies/${policyId}/edit`}
              className="ml-auto px-3 py-1.5 text-xs font-medium text-primary border border-primary rounded-md hover:bg-status-info-bg transition whitespace-nowrap"
              title="Edit this policy in the wizard"
            >
              ✎ Edit Policy
            </Link>
          )}
          {/* Renew actions — parity with the old Graphite policy view's "Move to
              Renew Table" + "Renew" buttons, placed just before Cancel. "Move to
              Renew" POSTs to the MIS renew API; "Renew" opens the renewal flow. */}
          {showMoveToRenew && (
            <button
              type="button"
              onClick={handleMoveToRenew}
              disabled={movingToRenew}
              className="ml-auto px-3 py-1.5 text-xs font-medium text-status-success-fg border border-status-success-fg rounded-md hover:bg-status-success-bg transition whitespace-nowrap disabled:opacity-50"
              title="Move this policy to the renew table"
            >
              {movingToRenew ? 'Moving…' : '↪ Move to Renew'}
            </button>
          )}
          {showRenewButton && (
            <Link
              to={`/policies/${policyId}/renew`}
              className="ml-auto px-3 py-1.5 text-xs font-medium text-status-success-fg border border-status-success-fg rounded-md hover:bg-status-success-bg transition whitespace-nowrap"
              title="Open the renewal flow"
            >
              ⟳ Renew
            </Link>
          )}
          {/* High-risk customer yearly renewal — all products except Motor Comp
              (3). Creates a new term, then routes to the RealPay contract
              screen to create the new payment contract. */}
          {showHighRiskRenew && (
            <button
              type="button"
              onClick={handleHighRiskRenew}
              disabled={highRiskRenewing}
              className="ml-auto px-3 py-1.5 text-xs font-medium text-status-success-fg border border-status-success-fg rounded-md hover:bg-status-success-bg transition whitespace-nowrap disabled:opacity-50"
              title="Renew this high-risk customer's policy for another year"
            >
              {highRiskRenewing ? 'Renewing…' : '⟳ Renew'}
            </button>
          )}
          {/* Inline cancel for MIS retail products — parity with the old
              Graphite policy view's Cancel button + feedback modal. Hidden once
              the policy is already cancelled (status 2). */}
          {CANCELLABLE_PRODUCT_IDS.includes(productId) && canCancelPolicy && policy.status !== 2 && (
            <button
              type="button"
              onClick={() => setShowCancelModal(true)}
              className={`${(COVERAGE_PRODUCT_IDS.includes(productId) || showRenewActions) ? '' : 'ml-auto'} px-3 py-1.5 text-xs font-medium text-status-danger-fg border border-status-danger-fg rounded-md hover:bg-status-danger-bg transition whitespace-nowrap`}
              title="Cancel this policy"
            >
              ✕ Cancel Policy
            </button>
          )}
          {/* Reinstate Policy — shown once the policy is cancelled/expired
              (status != 1), so it's available right here on the Policy Details
              header (not only buried in the Actions tab). Hidden for DOM/COM /
              specialist / engineering products (COVERAGE_PRODUCT_IDS), which
              reinstate through their REINSTATE transaction workflow instead.
              ml-auto because for a cancelled MIS policy neither the Edit
              (coverage-only) nor the Cancel (status!=2) button is present. */}
          {policy.status !== 1 && !COVERAGE_PRODUCT_IDS.includes(productId) && canReinstatePolicy && (
            <button
              type="button"
              onClick={() => setShowReinstateModal(true)}
              className="ml-auto px-3 py-1.5 text-xs font-medium text-status-success-fg border border-status-success-fg rounded-md hover:bg-status-success-bg transition whitespace-nowrap"
              title="Reinstate this cancelled policy"
            >
              ⟲ Reinstate Policy
            </button>
          )}
          {/* Resend cancellation notice — only for already-cancelled policies
              (status 2). Parity with the old Edit Policy page's "Send policy
              cancelled email" / "Send policy cancelled sms" buttons. */}
          {policy.status === 2 && (
            <>
              <button
                type="button"
                onClick={() => handleResendCancellation('email')}
                disabled={resending !== null}
                className={`${(COVERAGE_PRODUCT_IDS.includes(productId) || !canReinstatePolicy) ? 'ml-auto' : ''} px-3 py-1.5 text-xs font-medium text-primary border border-primary rounded-md hover:bg-status-info-bg transition whitespace-nowrap disabled:opacity-50`}
                title="Send the policy-cancelled email to the customer"
              >
                {resending === 'email' ? 'Sending…' : '✉ Send Cancelled Email'}
              </button>
              <button
                type="button"
                onClick={() => handleResendCancellation('sms')}
                disabled={resending !== null}
                className="px-3 py-1.5 text-xs font-medium text-primary border border-primary rounded-md hover:bg-status-info-bg transition whitespace-nowrap disabled:opacity-50"
                title="Send the policy-cancelled SMS to the customer"
              >
                {resending === 'sms' ? 'Sending…' : '✉ Send Cancelled SMS'}
              </button>
            </>
          )}
        </div>
        {showReinstateModal && (
          <ReinstatePolicyModal
            policyId={policyId}
            policyNumber={policy.policyNumber}
            productId={productId}
            onClose={() => setShowReinstateModal(false)}
            onReinstated={() => {
              setShowReinstateModal(false)
              qc.invalidateQueries({ queryKey: ['policy', policyId] })
              qc.invalidateQueries({ queryKey: ['policy', policyId, 'actions'] })
              qc.invalidateQueries({ queryKey: ['policyCancelReason', policyId] })
            }}
          />
        )}
        {showCancelModal && (
          <CancelPolicyModal
            policyId={policyId}
            policyNumber={policy.policyNumber}
            onClose={() => setShowCancelModal(false)}
            onCancelled={() => {
              setShowCancelModal(false)
              qc.invalidateQueries({ queryKey: ['policy', policyId] })
              qc.invalidateQueries({ queryKey: ['policy', policyId, 'actions'] })
              qc.invalidateQueries({ queryKey: ['policyCancelReason', policyId] })
            }}
          />
        )}
        {/* Customer name directly under the policy number so the data team can
            confirm accuracy without opening the Customer tab (Babusi #3). */}
        {policy.customer?.fullName && (
          <p className="-mt-1 pb-2 text-sm font-semibold text-ink-muted">{policy.customer.fullName}</p>
        )}
        <div className="flex flex-wrap gap-3 pb-3 text-sm text-ink-muted">
          {/* GFS / Portal policy number for source-system claims history (Babusi #1) */}
          {(policy as any).gfsPolicyNo && (
            <span title="GFS / Portal policy number" className="font-medium text-ink-muted">GFS: {(policy as any).gfsPolicyNo}</span>
          )}
          {policy.product && <span title={policy.product.name}>{policy.product.name}</span>}
          {policy.plan && <span title={`Plan: ${policy.plan.name}`}>Plan: {policy.plan.name}</span>}
          {policy.agent && <span title={`Agent: ${policy.agent.name}`}>Agent: {policy.agent.name}</span>}
          {policy.agencyName && <span title={`Agency: ${policy.agencyName}`}>Agency: {policy.agencyName}</span>}
          {policy.complianceLabel && (
            <span className={policy.kyc?.compliance === 1 ? 'text-status-success-fg font-medium' : 'text-status-warning-fg font-medium'} title={policy.complianceLabel}>
              {policy.complianceLabel}
            </span>
          )}
          {/* Banking Details compliance — compliant when either banking doc is approved */}
          <BankingComplianceBadge policyId={policyId} />
          {/* High Risk Customer — flagged when the customer declared they are a
              Prominent/Influential Person (PEP) or related to one during policy
              creation, OR their country is on the Compliance high-risk watch-list.
              Display-only badge (profile.isPep / isPepRelated / isHighRiskCountry). */}
          {(policy.profile?.isPep || policy.profile?.isPepRelated || policy.profile?.isHighRiskCountry) && (
            <span
              className="inline-flex items-center rounded-full bg-status-danger-bg px-2 py-0.5 text-xs font-semibold text-status-danger-fg"
              title={[
                policy.profile?.isPep ? 'Declared as a Prominent/Influential Person (PEP)' : null,
                policy.profile?.isPepRelated ? 'Related to a Prominent/Influential Person (PEP)' : null,
                policy.profile?.isHighRiskCountry
                  ? `High-risk country${policy.profile?.highRiskCountryName ? `: ${policy.profile.highRiskCountryName}` : ''}`
                  : null,
              ].filter(Boolean).join(' · ')}
            >
              ⚠ High Risk Customer
            </span>
          )}
        </div>

        {/* Tabs — filtered by product type */}
        <nav className="flex gap-0 overflow-x-auto -mb-px">
          {getVisibleTabs(policy).map((t) => (
            <button
              key={t.key}
              onClick={() => setActiveTab(t.key)}
              className={`px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 transition ${
                activeTab === t.key
                  ? 'border-brand-navy text-brand-navy'
                  : 'border-transparent text-ink-muted hover:text-ink-muted hover:border-line'
              }`}
            >
              {t.label}
            </button>
          ))}
        </nav>
      </div>

      {/* Tab Content (scrollable) */}
      <div className="flex-1 overflow-y-auto p-6">
        {policy.status === 2 && (cancelReason?.reason || cancelReason?.cancelledBy || policy.cancelledBy) && (
          <div className="mb-4 rounded-md border border-status-danger-fg bg-status-danger-bg px-4 py-3 text-sm">
            {cancelReason?.reason && (
              <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                <span className="font-semibold text-status-danger-fg">Cancellation reason:</span>
                <span className="text-status-danger-fg">{cancelReason.reason}</span>
                {cancelReason.circumstances && <span className="text-status-danger-fg">· {cancelReason.circumstances}</span>}
                {cancelReason.otherCompany && <span className="text-status-danger-fg">· {cancelReason.otherCompany}</span>}
              </div>
            )}
            {(cancelReason?.cancelledBy || policy.cancelledBy) && (
              <div className="flex flex-wrap items-center gap-x-2 gap-y-1 mt-0.5">
                <span className="font-semibold text-status-danger-fg">Cancelled by:</span>
                <span className="text-status-danger-fg">{cancelReason?.cancelledBy || policy.cancelledBy}</span>
                {policy.cancelledDate && <span className="text-status-danger-fg">· {fmtDate(policy.cancelledDate)}</span>}
              </div>
            )}
            {cancelReason?.createdAt && (
              <div className="mt-0.5 text-xs text-status-danger-fg">Recorded {fmtDate(cancelReason.createdAt)}</div>
            )}
          </div>
        )}
        {activeTab === 'details' && <PolicyDetailsTab policy={policy} policyId={policyId} />}
        {activeTab === 'productDetails' && <ProductDetailsTab policyId={policyId} />}
        {activeTab === 'customer' && <CustomerTab policy={policy} policyId={policyId} />}
        {activeTab === 'actions' && <ActionsTab policyId={policyId} policyNumber={policy.policyNumber ?? String(policyId)} policyStatus={policy.status} productId={policy.product?.id ?? (policy as any).product_id ?? 0} premiumFreqLabel={policy.premiumFreqLabel} policyEndDate={policy.endDate} />}
        {activeTab === 'vehicles' && <VehiclesTab policyId={policyId} productId={policy.product?.id ?? (policy as any).product_id ?? 0} autoOpenAdd={autoOpenAdd} />}
        {activeTab === 'members' && <MembersTab policyId={policyId} />}
        {activeTab === 'coapplicants' && <CoApplicantsTab policyId={policyId} />}
        {activeTab === 'devices' && <DevicesTab policyId={policyId} />}
        {activeTab === 'coverages' && <CoveragesTab policyId={policyId} productId={policy.product?.id ?? (policy as any).product_id ?? 0} />}
        {activeTab === 'claims' && <ClaimsTab policyId={policyId} policyNumber={policy.policyNumber} />}
        {/* Replaced by the new 'Transaction Logs' tab — kept for rollback only.
        {activeTab === 'transactions' && <TransactionsTab policyId={policyId} />} */}
        {activeTab === 'ledger' && <LedgerTab policyId={policyId} productId={productId} policyNumber={policy.policyNumber} />}
        {activeTab === 'claimsWaiver' && <ClaimsWaiverTab policyId={policyId} />}
        {activeTab === 'banking' && <BankingTab policy={policy} policyId={policyId} />}
        {activeTab === 'riskAddresses' && <RiskAddressesTab policyId={policyId} policy={policy} />}
        {activeTab === 'reinsurance' && <ReinsuranceTab policyId={policyId} />}
        {activeTab === 'linkedPolicy' && <LinkedPolicyTab policyId={policyId} policy={policy} />}
        {activeTab === 'documents' && (() => {
          // Show Cancel Note only when the current action is a CANCEL that has
          // already been ISSUED. Any other state (quote/new business/endorse
          // /draft) has nothing to cancel yet, so hide the button.
          const curAct: any = (actionsData as any)?.current
              ?? (Array.isArray((actionsData as any)?.data) ? (actionsData as any).data.slice(-1)[0] : null)
          const curTx = String(curAct?.transactionType ?? '').toUpperCase()
          const curSt = String(curAct?.status ?? '').toUpperCase()
          const showCancelNote = curTx === 'CANCEL' && curSt === 'ISSUED'
          return <DocumentsTab policyId={policyId} productId={policy.product?.id ?? (policy as any).product_id ?? 0} showCancelNote={showCancelNote} policyNumber={policy.policyNumber} />
        })()}
        {activeTab === 'attachments' && <AttachmentsTab policyId={policyId} />}
        {activeTab === 'kycDocuments' && <KycDocumentsTab policyId={policyId} kyc={policy.kyc} policy={policy} />}
        {activeTab === 'matiVerification' && <MatiVerificationTab policyId={policyId} />}
        {activeTab === 'logs' && <LogsTab policyId={policyId} />}
        {activeTab === 'scheduleTransactions' && (
          <ScheduleTransactionsTab
            policyId={policyId}
            editable={policy.billingType === 'DPO' || policy.banking?.billing === 'DPO'}
            currentMethod={policy.billingType ?? policy.banking?.billing ?? null}
            canCancelDpoContract={canCancelDpoContract}
          />
        )}
        {activeTab === 'collectNow' && <CollectNowTab policyId={policyId} policyNumber={policy.policyNumber} />}
        {activeTab === 'terms' && <TermsTab policyId={policyId} />}
        {activeTab === 'reratePremium' && <ReratePremiumTab policyId={policyId} policy={policy} />}
        {activeTab === 'notes' && <NotesTab policy={policy} />}
        {activeTab === 'addRealpayContract' && <AddRealpayContractTab policy={policy} policyId={policyId} />}
        {activeTab === 'realpayContracts' && <RealpayContractsTab policyId={policyId} />}
        {activeTab === 'realpayTransactions' && <RealpayTransactionsTab policyId={policyId} />}
        {activeTab === 'offlinePayments' && <OfflinePaymentsTab policy={policy} policyId={policyId} />}
        {activeTab === 'transactionLogs' && <TransactionLogsTab policyId={policyId} />}
        {activeTab === 'paymentConversions' && <PaymentConversionsTab policyId={policyId} />}
        {activeTab === 'assignAgent' && <AssignAgentTab policyId={policyId} />}
      </div>
    </div>
  )
  // closes flex-1 div ^ and flex-col div ^
}

// ─── Tab: Policy Details (from main response, no extra fetch) ───

function PolicyDetailsTab({ policy, policyId }: { policy: Policy; policyId: number }) {
  // KYC URLs fetched on demand — only when user clicks "View Document"
  const { data: kycDocs, refetch: refetchKyc, isFetching: kycFetching } = usePolicyKycDocuments(policyId, false)
  const { data: attachments } = usePolicyAttachments(policyId, true)
  const [previewUrl, setPreviewUrl] = useState<string | null>(null)
  const [pendingField, setPendingField] = useState<string | null>(null)

  // Build URL lookup once fetched
  const docUrlMap: Record<string, string | null> = {}
  if (kycDocs?.documents) {
    for (const doc of kycDocs.documents) docUrlMap[doc.field] = doc.url
  }

  function handleViewKycDoc(field: string) {
    if (docUrlMap[field]) {
      setPreviewUrl(docUrlMap[field])
    } else if (kycDocs) {
      // Already fetched, no URL for this doc
    } else {
      setPendingField(field)
      refetchKyc().then((result) => {
        const url = result.data?.documents.find(d => d.field === field)?.url
        if (url) setPreviewUrl(url)
        setPendingField(null)
      })
    }
  }

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <Card title="Policy Information">
        {policy.product?.type && <InfoRow label="productType" value={policy.product.type} />}
        <InfoRow label="leadSource" value={policy.leadSource} />
        <InfoRow label="activationCode" value={policy.activationCode} />
        <InfoRow label="isReinstate" value={policy.isReinstate ? 'Yes' : 'No'} />
        {policy.isBundled && <InfoRow label="isBundled" value="Yes" />}
      </Card>

      <Card title="Premium & Billing">
        <InfoRow label="premium" value={fmtCurrency(policy.premium)} />
        {policy.firstPremium != null && <InfoRow label="firstPremium" value={fmtCurrency(policy.firstPremium)} />}
        <InfoRow label="sumAssured" value={fmtCurrency(policy.sumAssured)} />
        <InfoRow label="premiumFreqLabel" value={policy.premiumFreqLabel ?? 'Monthly'} />
        {policy.vat != null && <InfoRow label="vat" value={fmtCurrency(policy.vat)} />}
        <InfoRow label="billingStartDate" value={fmtDate(policy.billingStartDate)} />
        <InfoRow label="billingType" value={policy.billingType} />
        {policy.isBundled && (
          <>
            <InfoRow label="bundledDiscountRate" value={policy.bundledDiscountRate ? `${policy.bundledDiscountRate}%` : null} />
            <InfoRow label="bundledDiscountAmount" value={fmtCurrency(policy.bundledDiscountAmount)} />
          </>
        )}
      </Card>

      <Card title="Dates">
        <InfoRow label="startDate" value={fmtDate(policy.startDate)} />
        <InfoRow label="endDate" value={fmtDate(policy.endDate)} />
        <InfoRow label="policyActivatedDate" value={fmtDate(policy.policyActivatedDate)} />
        {policy.cancelledDate && <InfoRow label="cancelledDate" value={fmtDate(policy.cancelledDate)} />}
        {policy.cancelledBy && <InfoRow label="cancelledBy" value={policy.cancelledBy} />}
        <InfoRow label="createdAt" value={fmtDate(policy.createdAt)} />
        <InfoRow label="updatedAt" value={fmtDate(policy.updatedAt)} />
      </Card>

      <Card title="Product & Plan">
        <InfoRow label="product" value={policy.product?.name} />
        {policy.plan && (
          <>
            <InfoRow label="planName" value={policy.plan.name} />
            {policy.plan.sumAssured != null && <InfoRow label="planSumAssured" value={fmtCurrency(policy.plan.sumAssured)} />}
            {policy.plan.premium != null && <InfoRow label="planPremium" value={fmtCurrency(policy.plan.premium)} />}
          </>
        )}
      </Card>

      <Card title="Agent & Agency">
        {policy.agent ? (
          <>
            <InfoRow label="agentName" value={policy.agent.name} />
            <InfoRow label="agentEmail" value={policy.agent.email} />
          </>
        ) : (
          <p className="text-sm text-ink-faint">No agent assigned</p>
        )}
        <InfoRow label="agencyName" value={policy.agencyName} />
        <InfoRow label="storeName" value={policy.storeName} />
      </Card>

      {policy.kyc && (
        <Card title="KYC Summary">
          <InfoRow label="complianceLabel" value={
            policy.kyc.compliance === 1
              ? <StatusBadge status="approved" label="KYC Compliant" />
              : <StatusBadge status="pending" label={policy.kyc.complianceLabel ?? 'Not Compliant'} />
          } />
          {(kycDocs?.documents ?? []).filter((d) => d.hasFile).map((doc) => {
            const isLoadingThis = kycFetching && pendingField === doc.field
            return (
              <DocLink
                key={doc.field}
                label={doc.label}
                hasFile={true}
                url={docUrlMap[doc.field]}
                urlsLoaded={!!kycDocs}
                loading={isLoadingThis}
                onPreview={() => handleViewKycDoc(doc.field)}
              />
            )
          })}
          {kycDocs && (kycDocs.documents ?? []).every((d) => !d.hasFile) && (
            <p className="text-xs text-ink-faint italic">No documents uploaded</p>
          )}
        </Card>
      )}

      {/* Policy Documents / Attachments as tiles */}
      {attachments && attachments.length > 0 && (
        <div className="col-span-full">
          <h3 className="text-sm font-semibold text-ink-muted uppercase tracking-wider mb-3">Policy Documents</h3>
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
            {attachments.map((att: PolicyAttachment) => (
              <button
                key={att.id}
                onClick={() => att.url && setPreviewUrl(att.url)}
                className="bg-surface rounded-lg border border-line shadow-sm p-4 hover:border-brand-navy/40 hover:shadow transition text-left group"
                title={att.description || att.name || 'Document'}
              >
                <div className="flex items-center gap-3">
                  <span className="flex-shrink-0 w-8 h-8 rounded bg-brand-navy/10 flex items-center justify-center text-brand-navy text-xs font-bold">
                    {(att.type || 'DOC').substring(0, 3).toUpperCase()}
                  </span>
                  <div className="min-w-0">
                    <p className="text-sm font-medium text-ink-muted truncate group-hover:text-brand-navy">{att.name || 'Document'}</p>
                    {att.createdAt && <p className="text-xs text-ink-faint">{fmtDate(att.createdAt)}</p>}
                  </div>
                </div>
              </button>
            ))}
          </div>
        </div>
      )}

      <DocumentPreviewModal url={previewUrl} onClose={() => setPreviewUrl(null)} />
    </div>
  )
}

// ─── Tab: Customer (from main response) ─────────────────────────

const MARITAL_STATUS_OPTIONS = ['Single', 'Married', 'Divorced', 'Widowed']
const SOURCE_OF_INCOME_OPTIONS = [
  { value: 'employment', label: 'Salary / Employment' },
  { value: 'pensioner_retired', label: 'Pensioner/Retired' },
  { value: 'bussiness', label: 'Business income / Self-Employment' },
  { value: 'dividends', label: 'Dividends' },
  { value: 'rental', label: 'Rental' },
  { value: 'inheritance', label: 'Inheritance' },
  { value: 'gifts', label: 'Gifts' },
  { value: 'investments', label: 'Investments' },
  { value: 'other', label: 'Other (specify)' },
]
const OCCUPATION_LEVEL_OPTIONS = ['Senior', 'Middle', 'Junior', 'Unemployed']
const PEP_TYPE_OPTIONS = [
  'President',
  'Vice-President',
  'Cabinet Minister',
  'Speaker of the National Assembly',
  'Deputy Speaker of the National Assembly',
  'Member of the National Assembly',
  'Councillor',
  'Senior government official (a public officer in senior management appointed under the Public Service Act or any senior officer appointed under any enactment)',
  'Judicial officer',
  'Kgosi',
  'Senior executive of a private entity where the private entity has a turnover of P1 million and above',
  'Senior executive of a public body (senior officer of an organisation, establishment or body created by or under any enactment and includes any company in which Government has equity shares or any organisation or body where public moneys are used)',
  'Senior executive of a political party',
  'Senior executive of an international organisation operating in Botswana',
]
// "Close associate" / "Other" need a free-text specification.
const PEP_RELATIONSHIP_OPTIONS = [
  { value: 'Spouse', label: 'Spouse', specify: false },
  { value: 'Child', label: 'Child', specify: false },
  { value: 'Sibling', label: 'Sibling', specify: false },
  { value: 'Close associate', label: 'Close associate (specify)', specify: true },
  { value: 'Other', label: 'Other (specify)', specify: true },
]
const PEP_RELATIONSHIP_NEEDS_SPECIFY = (v: string) =>
  PEP_RELATIONSHIP_OPTIONS.some(o => o.value === v && o.specify)

function EditRow({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="flex items-center justify-between text-sm gap-4">
      <span className="text-ink-muted flex-shrink-0">{formatLabel(label)}</span>
      <span className="text-right">{children}</span>
    </div>
  )
}

const editInputClass = 'px-2 py-1 text-sm border border-line rounded-md text-right focus:ring-1 focus:ring-primary focus:outline-none'

function CustomerTab({ policy, policyId }: { policy: Policy; policyId: number }) {
  const c = policy.customer
  const p = policy.profile
  const { data: mati, isLoading: matiLoading } = usePolicyMati(policyId, true)
  const { data: myProfile } = useMyProfile()
  const qc = useQueryClient()

  // KYC staff (customer-kyc-edit) and other authorised users (customer-edit)
  // can correct customer/profile details from here — previously this whole
  // tab was read-only with no way to fix a wrong name/omang/DOB on an
  // already-issued policy (the Edit Policy wizard only allows drafts).
  const canEdit = !!myProfile?.permissions?.some(perm => perm === 'customer-edit' || perm === 'customer-kyc-edit')

  // Which block is currently being edited — only that card flips to inputs, so
  // clicking one block's Edit no longer opens every block at once.
  const [editingSection, setEditingSection] = useState<'customer' | 'personal' | 'employment' | 'pep' | null>(null)
  const [saving, setSaving] = useState(false)
  const [saveError, setSaveError] = useState<string | null>(null)
  const [form, setForm] = useState({
    firstName: '', middleName: '', lastName: '', email: '', cellphone: '',
    omang: '', passport: '', dob: '', gender: '', maritalstatus: '', sourceOfIncome: '',
    nationality: '', occupation: '', occupationLevel: '', employerName: '', country: '',
    // Physical address (free text) + State/District and City FK ids (kept as
    // strings for the <select> values; coerced to int/null on save).
    address: '', state: '', city: '',
    isPep: false, pepType: '',
    isPepRelated: false, pepRelationship: '', pepRelationshipSpecify: '',
  })

  // Location lookups for the State/District + City selects. States come from
  // the shared policy-create lookup; cities are fetched for the chosen state.
  const { data: createData } = usePolicyCreateData()
  const states = createData?.states ?? []
  const { data: cities = [] } = useCitiesByState(form.state ? Number(form.state) : null)

  function startEdit(section: 'customer' | 'personal' | 'employment' | 'pep') {
    setForm({
      firstName: c?.firstName ?? '', middleName: c?.middleName ?? '', lastName: c?.lastName ?? '',
      email: c?.email ?? '', cellphone: c?.cellphone ?? '',
      omang: p?.omang ?? '', passport: p?.passport ?? '', dob: (p?.dob ?? '').slice(0, 10),
      gender: p?.gender ?? '', maritalstatus: MARITAL_STATUS_OPTIONS.includes(p?.maritalStatus ?? '') ? p!.maritalStatus! : '',
      sourceOfIncome: p?.sourceOfIncomeKey ?? (p as any)?.sourceOfIncomeRaw ?? '',
      nationality: p?.nationality ?? '', occupation: p?.occupation ?? '',
      occupationLevel: OCCUPATION_LEVEL_OPTIONS.includes(p?.occupationLevel ?? '') ? p!.occupationLevel! : '',
      employerName: p?.employerName ?? '', country: p?.country ?? '',
      address: p?.address ?? '',
      state: p?.stateId != null ? String(p.stateId) : '',
      city: p?.cityId != null ? String(p.cityId) : '',
      isPep: !!p?.isPep, pepType: p?.pepType ?? '',
      isPepRelated: !!p?.isPepRelated, pepRelationship: p?.pepRelationship ?? '',
      pepRelationshipSpecify: p?.pepRelationshipSpecify ?? '',
    })
    setSaveError(null)
    setEditingSection(section)
  }

  async function handleSave() {
    if (!c?.id) return
    setSaving(true)
    setSaveError(null)
    try {
      // state/city are FK ids validated as nullable|integer server-side — send
      // null (not '') when unset so the whole update doesn't 422.
      const payload = {
        ...form,
        state: form.state ? Number(form.state) : null,
        city: form.city ? Number(form.city) : null,
      }
      await updateCustomer(c.id, payload)
      await qc.invalidateQueries({ queryKey: ['policy', policyId] })
      setEditingSection(null)
    } catch (e: any) {
      setSaveError(e?.response?.data?.message || 'Failed to save customer details.')
    } finally {
      setSaving(false)
    }
  }

  // Actions for one editable block. Only the active block shows Save/Cancel;
  // while a block is being edited the other blocks hide their Edit button so
  // you can't accidentally open a second block (and lose the unsaved edit).
  const sectionActions = (section: 'customer' | 'personal' | 'employment' | 'pep') => {
    if (editingSection === section) {
      return (
        <div className="flex items-center gap-2">
          <button onClick={handleSave} disabled={saving}
            className="px-3 py-1 text-xs bg-primary text-white rounded hover:bg-primary disabled:opacity-50">
            {saving ? 'Saving…' : 'Save'}
          </button>
          <button onClick={() => { setEditingSection(null); setSaveError(null) }} disabled={saving}
            className="px-3 py-1 text-xs bg-surface-2 text-ink-muted rounded hover:bg-surface-2">
            Cancel
          </button>
        </div>
      )
    }
    if (canEdit && editingSection === null) {
      return <button onClick={() => startEdit(section)} className="text-xs text-primary hover:underline">Edit</button>
    }
    return undefined
  }

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <Card title="Customer Details" actions={sectionActions('customer')}>
        {saveError && editingSection === 'customer' && <p className="text-xs text-status-danger-fg mb-1">{saveError}</p>}
        {c ? (
          editingSection === 'customer' ? (
            <>
              <EditRow label="firstName"><input className={editInputClass} value={form.firstName} onChange={e => setForm(f => ({ ...f, firstName: e.target.value }))} /></EditRow>
              <EditRow label="middleName"><input className={editInputClass} value={form.middleName} onChange={e => setForm(f => ({ ...f, middleName: e.target.value }))} /></EditRow>
              <EditRow label="lastName"><input className={editInputClass} value={form.lastName} onChange={e => setForm(f => ({ ...f, lastName: e.target.value }))} /></EditRow>
              <EditRow label="email"><input type="email" className={editInputClass} value={form.email} onChange={e => setForm(f => ({ ...f, email: e.target.value }))} /></EditRow>
              <EditRow label="cellphone"><input className={editInputClass} value={form.cellphone} onChange={e => setForm(f => ({ ...f, cellphone: e.target.value }))} /></EditRow>
            </>
          ) : (
            <>
              <InfoRow label="fullName" value={c.fullName} />
              <InfoRow label="email" value={c.email} />
              <InfoRow label="cellphone" value={c.cellphone} />
            </>
          )
        ) : (
          <p className="text-sm text-ink-faint">No customer data</p>
        )}
      </Card>

      {p && (
        <>
          <Card title="Personal Information" actions={sectionActions('personal')}>
            {editingSection === 'personal' ? (
              <>
                {saveError && <p className="text-xs text-status-danger-fg mb-1">{saveError}</p>}
                <EditRow label="omang"><input className={editInputClass} value={form.omang} onChange={e => setForm(f => ({ ...f, omang: e.target.value }))} /></EditRow>
                <EditRow label="passport"><input className={editInputClass} value={form.passport} onChange={e => setForm(f => ({ ...f, passport: e.target.value }))} /></EditRow>
                <EditRow label="dob"><input type="date" className={editInputClass} value={form.dob} onChange={e => setForm(f => ({ ...f, dob: e.target.value }))} /></EditRow>
                <EditRow label="gender">
                  <select className={editInputClass} value={form.gender} onChange={e => setForm(f => ({ ...f, gender: e.target.value }))}>
                    <option value="">- Select -</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                  </select>
                </EditRow>
                <EditRow label="maritalStatus">
                  <select className={editInputClass} value={form.maritalstatus} onChange={e => setForm(f => ({ ...f, maritalstatus: e.target.value }))}>
                    <option value="">- Select -</option>
                    {MARITAL_STATUS_OPTIONS.map(o => <option key={o} value={o}>{o}</option>)}
                  </select>
                </EditRow>
                <EditRow label="sourceOfIncome">
                  <select className={editInputClass} value={form.sourceOfIncome} onChange={e => setForm(f => ({ ...f, sourceOfIncome: e.target.value }))}>
                    <option value="">- Select -</option>
                    {SOURCE_OF_INCOME_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
                  </select>
                </EditRow>
                <EditRow label="nationality"><input className={editInputClass} value={form.nationality} onChange={e => setForm(f => ({ ...f, nationality: e.target.value }))} /></EditRow>
                <EditRow label="country"><input className={editInputClass} value={form.country} onChange={e => setForm(f => ({ ...f, country: e.target.value }))} /></EditRow>
                <EditRow label="Physical Address"><input className={editInputClass} value={form.address} onChange={e => setForm(f => ({ ...f, address: e.target.value }))} /></EditRow>
                <EditRow label="State/District">
                  {/* Changing the state clears the city so a stale city from the
                      previous state can't be saved against the new one. */}
                  <select className={editInputClass} value={form.state} onChange={e => setForm(f => ({ ...f, state: e.target.value, city: '' }))}>
                    <option value="">- Select -</option>
                    {states.map(s => <option key={s.id} value={String(s.id)}>{s.name}</option>)}
                  </select>
                </EditRow>
                <EditRow label="City">
                  <select className={editInputClass} value={form.city} disabled={!form.state} onChange={e => setForm(f => ({ ...f, city: e.target.value }))}>
                    <option value="">{form.state ? '- Select -' : 'Select a state first'}</option>
                    {cities.map(ci => <option key={ci.id} value={String(ci.id)}>{ci.name}</option>)}
                  </select>
                </EditRow>
              </>
            ) : (
              <>
                <InfoRow label="omang" value={p.omang} />
                <InfoRow label="passport" value={p.passport} />
                <InfoRow label="dob" value={fmtDate(p.dob)} />
                <InfoRow label="gender" value={p.gender} />
                <InfoRow label="maritalStatus" value={p.maritalStatus} />
                <InfoRow label="nationality" value={p.nationality || (p.omang ? 'Botswana' : undefined)} />
                <InfoRow label="Physical Address" value={p.address} />
                <InfoRow label="country" value={p.country} />
                <InfoRow label="State/District" value={p.state} />
                <InfoRow label="City" value={p.city} />
              </>
            )}
          </Card>

          <Card title="Identification">
            <InfoRow label="drivingLicense" value={p.drivingLicense} />
            <InfoRow label="licenseValidTill" value={fmtDate(p.licenseValidTill)} />
            <InfoRow label="taxIdNumber" value={p.taxIdNumber} />
          </Card>

          <Card title="Employment" actions={sectionActions('employment')}>
            {editingSection === 'employment' ? (
              <>
                {saveError && <p className="text-xs text-status-danger-fg mb-1">{saveError}</p>}
                <EditRow label="occupation"><input className={editInputClass} value={form.occupation} onChange={e => setForm(f => ({ ...f, occupation: e.target.value }))} /></EditRow>
                <EditRow label="occupationLevel">
                  <select className={editInputClass} value={form.occupationLevel} onChange={e => setForm(f => ({ ...f, occupationLevel: e.target.value }))}>
                    <option value="">- Select -</option>
                    {OCCUPATION_LEVEL_OPTIONS.map(o => <option key={o} value={o}>{o}</option>)}
                  </select>
                </EditRow>
                <EditRow label="employerName"><input className={editInputClass} value={form.employerName} onChange={e => setForm(f => ({ ...f, employerName: e.target.value }))} /></EditRow>
              </>
            ) : (
              <>
                <InfoRow label="occupation" value={p.occupation} />
                <InfoRow label="occupationLevel" value={p.occupationLevel} />
                <InfoRow label="incomeBracket" value={p.incomeBracket} />
                <InfoRow label="sourceOfIncome" value={p.sourceOfIncome} />
                {p.employerName && (
                  <>
                    <InfoRow label="employerName" value={p.employerName} />
                    <InfoRow label="employeeNo" value={p.employeeNo} />
                    <InfoRow label="employerPhone" value={p.employerPhone} />
                    <InfoRow label="salaryPayDate" value={fmtDate(p.salaryPayDate)} />
                  </>
                )}
              </>
            )}
          </Card>

          <Card title="PEP Declaration" actions={sectionActions('pep')}>
            {editingSection === 'pep' ? (
              <>
                {saveError && <p className="text-xs text-status-danger-fg mb-1">{saveError}</p>}
                <EditRow label="isPep">
                  <input type="checkbox" checked={form.isPep}
                    onChange={e => setForm(f => ({ ...f, isPep: e.target.checked, pepType: e.target.checked ? f.pepType : '' }))} />
                </EditRow>
                {form.isPep && (
                  <EditRow label="pepType">
                    <select className={editInputClass} value={form.pepType} onChange={e => setForm(f => ({ ...f, pepType: e.target.value }))}>
                      <option value="">- Select -</option>
                      {PEP_TYPE_OPTIONS.map(o => <option key={o} value={o}>{o}</option>)}
                    </select>
                  </EditRow>
                )}
                <EditRow label="isPepRelated">
                  <input type="checkbox" checked={form.isPepRelated}
                    onChange={e => setForm(f => ({ ...f, isPepRelated: e.target.checked, pepRelationship: e.target.checked ? f.pepRelationship : '', pepRelationshipSpecify: e.target.checked ? f.pepRelationshipSpecify : '' }))} />
                </EditRow>
                {form.isPepRelated && (
                  <>
                    <EditRow label="pepRelationship">
                      <select className={editInputClass} value={form.pepRelationship}
                        onChange={e => setForm(f => ({ ...f, pepRelationship: e.target.value, pepRelationshipSpecify: PEP_RELATIONSHIP_NEEDS_SPECIFY(e.target.value) ? f.pepRelationshipSpecify : '' }))}>
                        <option value="">- Select -</option>
                        {PEP_RELATIONSHIP_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
                      </select>
                    </EditRow>
                    {PEP_RELATIONSHIP_NEEDS_SPECIFY(form.pepRelationship) && (
                      <EditRow label="pepRelationshipSpecify"><input className={editInputClass} value={form.pepRelationshipSpecify} onChange={e => setForm(f => ({ ...f, pepRelationshipSpecify: e.target.value }))} /></EditRow>
                    )}
                  </>
                )}
              </>
            ) : (
              <>
                <InfoRow label="isPep" value={p.isPep ? 'Yes' : 'No'} />
                {p.isPep && <InfoRow label="pepType" value={p.pepType} />}
                <InfoRow label="isPepRelated" value={p.isPepRelated ? 'Yes' : 'No'} />
                {p.isPepRelated && (
                  <InfoRow label="pepRelationship" value={[p.pepRelationship, p.pepRelationshipSpecify].filter(Boolean).join(' — ') || undefined} />
                )}
              </>
            )}
          </Card>

          <Card title="KYC Check">
            <InfoRow label="currentlyInsured" value={(p as any).currentlyInsured ?? '—'} />
            <InfoRow label="hearAboutAlpha"   value={(p as any).hearAboutAlpha ?? '—'} />
            <InfoRow label="declineProposal"  value={(p as any).declineProposal ? 'Yes' : 'No'} />
            <InfoRow label="refusedPolicy"    value={(p as any).refusedPolicy   ? 'Yes' : 'No'} />
            <InfoRow label="cancelPolicy"     value={(p as any).cancelPolicy    ? 'Yes' : 'No'} />
          </Card>
        </>
      )}

      {/* Mati Verification */}
      {mati && (
        <Card title="Mati Verification">
          <InfoRow label="matiId" value={mati.identityId || mati.verificationId || '—'} />
          {mati.status && <InfoRow label="verificationStatus" value={mati.status} />}
          {mati.identityStatus && <InfoRow label="identityStatus" value={mati.identityStatus} />}
          {mati.eventName && <InfoRow label="eventName" value={mati.eventName} />}
          {mati.documentType && <InfoRow label="documentType" value={mati.documentType} />}
          {mati.fullName && <InfoRow label="verifiedName" value={mati.fullName} />}
          {mati.dateOfBirth && <InfoRow label="verifiedDob" value={fmtDate(mati.dateOfBirth)} />}
          {mati.documentNumber && <InfoRow label="documentNumber" value={mati.documentNumber} />}
          {mati.country && <InfoRow label="country" value={mati.country} />}
          {mati.createdAt && <InfoRow label="verifiedAt" value={fmtDate(mati.createdAt)} />}

        </Card>
      )}

      {/* Mati loading or not found state */}
      {!mati && !matiLoading && (
        <Card title="Mati Verification">
          <p className="text-sm text-ink-faint">No Mati verification data available for this customer.</p>
        </Card>
      )}
    </div>
  )
}

// ─── Tab: Vehicles (lazy loaded) ────────────────────────────────

const BLANK_VEHICLE = { vehiclePlate: '', make: '', model: '', year: '', chassisNo: '', engineNo: '', colour: '', seats: '', cylinders: '', estimated_value: '', is_imported: false, risk_id: '', claim_count: '', vehicle_type: '' }

// Inspection photo slots shown in the Add/Edit Vehicle modal (graphiteBWV8 parity).
const VEHICLE_PHOTO_SLOTS: { key: keyof NonNullable<PolicyVehicle['images']>; label: string }[] = [
  { key: 'front', label: 'Front' },
  { key: 'back', label: 'Back' },
  { key: 'right', label: 'Right' },
  { key: 'left', label: 'Left' },
  { key: 'registration', label: 'Registration' },
  { key: 'valuation', label: 'Invoice / Valuation' },
]

function VehiclesTab({ policyId, productId = 0, autoOpenAdd = false }: { policyId: number; productId?: number; autoOpenAdd?: boolean }) {
  // Vehicle rows are replicated per policy action — the tab shows the rows
  // belonging to whichever action the operator picks (defaults to the
  // current action, same default as the Policy Actions tab).
  const { data: actionsData } = usePolicyActions(policyId, true)
  const actionHistory: any[] = (actionsData as any)?.history ?? []
  const defaultActionId: number | undefined = (actionsData as any)?.current?.id ?? actionHistory[actionHistory.length - 1]?.id ?? undefined
  const [vehicleActionId, setVehicleActionId] = useState<number | undefined>(undefined)
  const activeActionId = vehicleActionId ?? defaultActionId
  const { data: vehicleData, isLoading, refetch } = usePolicyVehicles(policyId, true, activeActionId)
  const [previewUrl, setPreviewUrl] = useState<string | null>(null)
  const [modal, setModal] = useState<{ open: boolean; editing: any | null }>({ open: false, editing: null })
  const [form, setForm] = useState<any>(BLANK_VEHICLE)
  const [photos, setPhotos] = useState<Record<string, PhotoSlot>>({})

  // Auto-open the Add Vehicle modal when the tab is reached via
  // /policies/{id}?tab=vehicles&add=1 (deep-link from Edit Policy coverage
  // section). Runs once on mount.
  useEffect(() => {
    if (autoOpenAdd) {
      setForm(BLANK_VEHICLE)
      setModal({ open: true, editing: null })
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])
  const [saving, setSaving] = useState(false)
  const [deleting, setDeleting] = useState<number | null>(null)
  const [expandedDocs, setExpandedDocs] = useState<number | null>(null)

  // Vehicle make/model dropdowns — mirrors QuoteEditPage logic
  const isImportedStr = form.is_imported ? '1' : '0'
  const { data: vehicleMakes = [], isLoading: makesLoading } = useQuery({
    queryKey: ['vehicle-makes', isImportedStr],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: string[] }>('/vehicle/makes', { params: { is_imported: isImportedStr } })
      return data.data ?? []
    },
    staleTime: 10 * 60 * 1000,
  })
  const { data: vehicleModels = [], isLoading: modelsLoading } = useQuery({
    queryKey: ['vehicle-models', form.make, isImportedStr],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: string[] }>('/vehicle/models', { params: { make: form.make, is_imported: isImportedStr } })
      return data.data ?? []
    },
    enabled: !!form.make,
    staleTime: 10 * 60 * 1000,
  })

  // Risk addresses for this policy — pairs each vehicle to an address so
  // the motor coverage dropdown can filter to only vehicles registered at
  // the same address. Mirrors legacy graphiteBWV8 where vehicle.risk_id
  // drives the same filter.
  const { data: policyRiskAddresses = [] } = useQuery({
    queryKey: ['policy-risk-addresses', policyId],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: any[] }>(`/policies/${policyId}/risk-addresses`)
      return data.data ?? []
    },
    staleTime: 5 * 60 * 1000,
  })

  // Motor types for this product — populates the "Motor Type" dropdown on
  // the vehicle add/edit modal. Mirrors legacy AddVehicle's $allMotorType
  // (MotorType::where('product_id', ...)).
  const { data: motorTypes = [] } = useQuery<{ id: number | string; name: string }[]>({
    queryKey: ['motor-types', productId],
    queryFn: async () => {
      if (!productId) return []
      const { data } = await apiClient.get<{ data: { id: number; name: string }[] }>(`/lookups/products/${productId}/motor-types`)
      return data.data ?? []
    },
    enabled: !!productId,
    staleTime: 30 * 60 * 1000,
  })

  const token = localStorage.getItem('sanctum_token')
  const headers: Record<string, string> = { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json', Accept: 'application/json' }
  const apiBase = `${(import.meta as any).env.VITE_API_URL}/api/v1`

  // Support both old array format and new {data, pendingApprovals} format
  const vehicles: any[] = Array.isArray(vehicleData) ? vehicleData : (vehicleData as any)?.data ?? []
  const pendingApprovals: any[] = (vehicleData as any)?.pendingApprovals ?? []

  function openAdd() { setForm(BLANK_VEHICLE); setPhotos({}); setModal({ open: true, editing: null }) }
  function openEdit(v: any) {
    setForm({
      vehiclePlate: v.vehiclePlate || '',
      make: v.make || '',
      model: v.model || '',
      year: v.year || '',
      chassisNo: v.chassisNo || '',
      engineNo: v.engineNo || '',
      seats: v.seats?.toString() || '',
      cylinders: v.cylinders?.toString() || '',
      estimated_value: v.estimatedValue?.toString() || '',
      is_imported: !!v.isImported,
      risk_id: v.riskId ? String(v.riskId) : (v.risk_id ? String(v.risk_id) : ''),
      claim_count: v.claimCount != null ? String(v.claimCount) : '',
      vehicle_type: v.vehicleType ? String(v.vehicleType) : '',
    })
    const init: Record<string, PhotoSlot> = {}
    VEHICLE_PHOTO_SLOTS.forEach(s => { init[s.key] = { existing: v.images?.[s.key] ?? null } })
    setPhotos(init)
    setModal({ open: true, editing: v })
  }

  function pickPhoto(key: string, file: File | null) {
    setPhotos(p => ({ ...p, [key]: { ...p[key], file: file || undefined, preview: file ? URL.createObjectURL(file) : undefined, remove: false } }))
  }
  function clearPhoto(key: string) {
    setPhotos(p => ({ ...p, [key]: { ...p[key], file: undefined, preview: undefined, remove: !!p[key]?.existing } }))
  }

  async function save() {
    setSaving(true)
    try {
      const { colour, ...rest } = form as any  // strip colour — column doesn't exist
      const body = new FormData()
      // Method spoofing: PHP only parses multipart on POST, so edits POST with _method=PUT.
      if (modal.editing) body.append('_method', 'PUT')
      body.append('vehiclePlate', rest.vehiclePlate ?? '')
      body.append('make', rest.make ?? '')
      body.append('model', rest.model ?? '')
      ;['year', 'chassisNo', 'engineNo', 'seats', 'cylinders', 'estimated_value', 'financial_interest'].forEach(k => {
        if (rest[k] !== undefined && rest[k] !== null && rest[k] !== '') body.append(k, String(rest[k]))
      })
      body.append('is_imported', rest.is_imported ? '1' : '0')
      if (rest.risk_id) body.append('risk_id', String(Number(rest.risk_id)))
      if (rest.claim_count !== '' && rest.claim_count != null) body.append('claim_count', String(Number(rest.claim_count)))
      if (rest.vehicle_type) body.append('vehicle_type', String(rest.vehicle_type))
      VEHICLE_PHOTO_SLOTS.forEach(s => {
        const slot = photos[s.key]
        if (slot?.file) body.append(`img_${s.key}`, slot.file)
        else if (slot?.remove) body.append(`remove_${s.key}`, '1')
      })
      const url = modal.editing ? `${apiBase}/policies/${policyId}/vehicles/${modal.editing.id}` : `${apiBase}/policies/${policyId}/vehicles`
      // Multipart upload: send via fetch WITHOUT a JSON Content-Type so the
      // browser sets multipart/form-data + boundary. Using apiClient here
      // would force application/json (its default) and the body would not
      // parse server-side → 422 "The given data was invalid".
      const r = await fetch(url, { method: 'POST', headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' }, body })
      const d = await r.json().catch(() => ({}))
      if (!r.ok) {
        // Laravel 422 returns { message, errors: { field: [msgs] } } — surface specifics.
        const fieldErrors = d?.errors ? Object.values(d.errors as Record<string, string[]>).flat().join('\n') : ''
        alert(fieldErrors || d?.error || d?.message || 'Failed')
        setSaving(false); return
      }
      setModal({ open: false, editing: null }); refetch()
    } catch (e: any) {
      alert(e?.message || 'Failed')
    }
    setSaving(false)
  }

  async function remove(id: number) {
    if (!confirm('Delete this vehicle?')) return
    setDeleting(id)
    try {
      const r = await fetch(`${apiBase}/policies/${policyId}/vehicles/${id}`, { method: 'DELETE', headers })
      const d = await r.json()
      if (!r.ok) alert(d.error || 'Failed')
      else refetch()
    } catch (e: any) { alert(e.message) }
    setDeleting(null)
  }

  if (isLoading) return <ProgressBar isLoading label="Loading vehicles" className="max-w-xs mx-auto py-8" />

  return (
    <div className="space-y-4">
      {/* Pending Vehicle Approvals */}
      {pendingApprovals.length > 0 && (
        <Card title="Pending Vehicle Approvals">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-status-warning-bg text-status-warning-fg uppercase text-xs">
                <tr>
                  <th className="px-3 py-2 text-left">Vehicle Plate</th>
                  <th className="px-3 py-2 text-left">Make / Model</th>
                  <th className="px-3 py-2 text-left">Comment</th>
                  <th className="px-3 py-2 text-left">Requested By</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-status-warning-fg">
                {pendingApprovals.map((v: any) => (
                  <tr key={v.id} className="hover:bg-status-warning-bg">
                    <td className="px-3 py-2 font-medium">{v.vehiclePlate || '—'}</td>
                    <td className="px-3 py-2">{[v.make, v.model].filter(Boolean).join(' ') || '—'}</td>
                    <td className="px-3 py-2">{v.comment || '—'}</td>
                    <td className="px-3 py-2">{v.requestedBy || '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>
      )}

      <div className="flex justify-between items-center gap-3 flex-wrap">
        {/* Action selector — vehicles are stored per policy action, so the
            list follows whichever action is picked here (mirrors the
            "Select Policy Action" dropdown on the Policy Actions tab). */}
        {actionHistory.length > 0 ? (
          <div className="flex items-center gap-2">
            <label className="text-xs font-medium text-ink-muted whitespace-nowrap">Policy Action</label>
            <select
              value={activeActionId ?? ''}
              onChange={e => setVehicleActionId(e.target.value ? Number(e.target.value) : undefined)}
              className="min-w-[300px] px-3 py-2 border border-line rounded text-sm focus:outline-none focus:ring-2 focus:ring-primary"
              title="Vehicles are stored per policy action — pick an action to view its vehicles"
            >
              {[...actionHistory].sort((a: any, b: any) => {
                // Sort by effective_from DESC (most recent first); tiebreak by id DESC
                const da = a.effectiveFrom ? new Date(a.effectiveFrom).getTime() : 0
                const db = b.effectiveFrom ? new Date(b.effectiveFrom).getTime() : 0
                if (db !== da) return db - da
                return (b.id || 0) - (a.id || 0)
              }).map((a: any) => {
                const from = a.effectiveFrom ? new Date(a.effectiveFrom).toLocaleDateString('en-GB') : '?'
                const to   = a.effectiveTo   ? new Date(a.effectiveTo).toLocaleDateString('en-GB')   : '?'
                return (
                  <option key={a.id} value={a.id}>
                    {a.transactionType} — {a.status} ({from} – {to})
                  </option>
                )
              })}
            </select>
          </div>
        ) : <div />}
        <button onClick={openAdd} className="px-4 py-2 bg-primary text-white rounded text-sm hover:bg-primary">+ Add Vehicle</button>
      </div>

      {vehicles.length === 0 && (
        <EmptyState message={actionHistory.length > 0 ? 'No vehicles on the selected policy action.' : 'No vehicles attached to this policy.'} />
      )}

      {/* Flat vehicle table matching old portal */}
      {vehicles.length > 0 && (
        <Card title={`Vehicles (${vehicles.length})`}>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-surface-2 text-ink-muted uppercase text-xs">
                <tr>
                  <th className="px-3 py-2 text-left">SR.NO.</th>
                  <th className="px-3 py-2 text-left">Vehicle Plate</th>
                  <th className="px-3 py-2 text-left">Car Imported</th>
                  <th className="px-3 py-2 text-left">Make</th>
                  <th className="px-3 py-2 text-left">Model</th>
                  <th className="px-3 py-2 text-left">Engine No.</th>
                  <th className="px-3 py-2 text-left">Chassis No.</th>
                  <th className="px-3 py-2 text-left">Year</th>
                  <th className="px-3 py-2 text-left">Seats</th>
                  <th className="px-3 py-2 text-left">Estimated Value</th>
                  <th className="px-3 py-2 text-left">No. of Accidents</th>
                  <th className="px-3 py-2 text-left">Risk Address</th>
                  <th className="px-3 py-2 text-left">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {vehicles.map((v: any, i: number) => (
                  <Fragment key={v.id}>
                    <tr className="hover:bg-surface-2">
                      <td className="px-3 py-2 text-ink-muted">{i + 1}</td>
                      <td className="px-3 py-2 font-medium">{v.vehiclePlate || '—'}</td>
                      <td className="px-3 py-2">{v.isImported ? 'Yes' : 'No'}</td>
                      <td className="px-3 py-2">{v.make || '—'}</td>
                      <td className="px-3 py-2">{v.model || '—'}</td>
                      <td className="px-3 py-2">{v.engineNo || '—'}</td>
                      <td className="px-3 py-2">{v.chassisNo || '—'}</td>
                      <td className="px-3 py-2">{v.year || '—'}</td>
                      <td className="px-3 py-2">{v.seats || '—'}</td>
                      <td className="px-3 py-2">{v.estimatedValue ? fmtCurrency(v.estimatedValue) : '—'}</td>
                      <td className="px-3 py-2">{v.claimCount ?? '0'}</td>
                      <td className="px-3 py-2">{v.riskAddressName || '—'}</td>
                      <td className="px-3 py-2">
                        <div className="flex gap-1">
                          <button onClick={() => openEdit(v)} className="px-2 py-0.5 text-xs bg-surface-2 hover:bg-surface-2 rounded border border-line">Edit</button>
                          <button onClick={() => remove(v.id)} disabled={deleting === v.id} className="px-2 py-0.5 text-xs bg-status-danger-bg hover:bg-status-danger-bg text-status-danger-fg rounded border border-status-danger-fg disabled:opacity-50">
                            {deleting === v.id ? '…' : 'Del'}
                          </button>
                          {v.documents?.length > 0 && (
                            <button onClick={() => setExpandedDocs(expandedDocs === v.id ? null : v.id)} className="px-2 py-0.5 text-xs bg-status-info-bg hover:bg-status-info-bg text-primary rounded border border-primary">
                              Docs
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                    {/* Expandable docs row */}
                    {expandedDocs === v.id && v.documents?.length > 0 && (
                      <tr key={`docs-${v.id}`}>
                        <td colSpan={13} className="px-3 py-2 bg-surface-2">
                          <div className="text-xs font-semibold text-ink-muted mb-2">Preinspection Documents — {v.inspectionStatus}</div>
                          <div className="flex flex-wrap gap-2">
                            {v.documents.map((doc: any) => (
                              <button key={doc.label} onClick={() => setPreviewUrl(doc.url)}
                                className="flex items-center gap-1 px-2 py-1 text-xs border border-line rounded hover:bg-surface bg-surface shadow-sm">
                                <span className="text-ink-muted">{doc.label}</span>
                                <span className={`ml-1 px-1 rounded text-[10px] font-medium ${String(doc.status) === '1' ? 'bg-status-success-bg text-status-success-fg' : String(doc.status) === '2' ? 'bg-status-danger-bg text-status-danger-fg' : 'bg-status-warning-bg text-status-warning-fg'}`}>
                                  {String(doc.status) === '1' ? 'Approved' : String(doc.status) === '2' ? 'Rejected' : 'Pending'}
                                </span>
                              </button>
                            ))}
                          </div>
                        </td>
                      </tr>
                    )}
                  </Fragment>
                ))}
              </tbody>
            </table>
          </div>
        </Card>
      )}

      <DocumentPreviewModal url={previewUrl} onClose={() => setPreviewUrl(null)} />

      {modal.open && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-start justify-center p-4 pt-10" onClick={(e) => { if (e.target === e.currentTarget) setModal({ open: false, editing: null }) }}>
          <div className="bg-surface rounded-xl shadow-xl w-full max-w-xl max-h-[85vh] flex flex-col">
            <div className="flex items-center justify-between px-5 py-3 border-b border-line shrink-0">
              <h3 className="font-semibold text-ink">{modal.editing ? 'Edit Vehicle' : 'Add Vehicle'}</h3>
              <button onClick={() => setModal({ open: false, editing: null })} className="text-ink-faint hover:text-ink-muted text-xl leading-none">✕</button>
            </div>
            <div className="px-5 py-4 grid grid-cols-1 sm:grid-cols-2 gap-4 overflow-y-auto flex-1 min-h-0">
              {/* Is Imported first — controls which make/model list loads */}
              <div className="flex items-center gap-2 sm:col-span-2">
                <input type="checkbox" id="is_imported" checked={!!form.is_imported}
                  onChange={e => setForm((p: any) => ({ ...p, is_imported: e.target.checked, make: '', model: '' }))}
                  className="w-4 h-4" />
                <label htmlFor="is_imported" className="text-sm text-ink-muted">Is Imported Vehicle</label>
              </div>

              {/* Vehicle Plate */}
              <div>
                <label className="block text-xs font-medium text-ink-muted mb-1">Vehicle Plate *</label>
                <input value={form.vehiclePlate || ''} onChange={e => setForm((p: any) => ({ ...p, vehiclePlate: e.target.value }))}
                  className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none" />
              </div>

              {/* Make — combobox (dropdown + free text) */}
              <div>
                <label className="block text-xs font-medium text-ink-muted mb-1">Make *</label>
                <input list="make-options" value={form.make || ''}
                  onChange={e => setForm((p: any) => ({ ...p, make: e.target.value, model: '' }))}
                  placeholder={makesLoading ? 'Loading…' : 'Type or select make'}
                  className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none bg-surface" />
                <datalist id="make-options">
                  {(vehicleMakes as string[]).map((m: string) => (
                    <option key={m} value={m} />
                  ))}
                </datalist>
              </div>

              {/* Model — combobox (dropdown + free text) */}
              <div>
                <label className="block text-xs font-medium text-ink-muted mb-1">Model *</label>
                <input list="model-options" value={form.model || ''}
                  onChange={e => setForm((p: any) => ({ ...p, model: e.target.value }))}
                  placeholder={modelsLoading ? 'Loading…' : (!form.make ? 'Select Make first' : 'Type or select model')}
                  className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none bg-surface"
                  disabled={!form.make && !form.model} />
                <datalist id="model-options">
                  {(vehicleModels as string[]).map((m: string) => (
                    <option key={m} value={m} />
                  ))}
                </datalist>
              </div>

              {/* Remaining text fields */}
              {[
                { key: 'year', label: 'Year' },
                { key: 'chassisNo', label: 'Chassis No' },
                { key: 'engineNo', label: 'Engine No' },
                { key: 'seats', label: 'Seats' },
                { key: 'cylinders', label: 'Cylinders' },
                { key: 'estimated_value', label: 'Estimated Value (P)' },
              ].map(({ key, label }) => (
                <div key={key}>
                  <label className="block text-xs font-medium text-ink-muted mb-1">{label}</label>
                  <input value={form[key] || ''} onChange={e => setForm((p: any) => ({ ...p, [key]: e.target.value }))}
                    className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none" />
                </div>
              ))}

              {/* No of Accidents — legacy `claim_count`. Drives underwriting / rate
                  factors on COMG/DOMG motor; integer 0–99. */}
              <div>
                <label className="block text-xs font-medium text-ink-muted mb-1">No of Accidents *</label>
                <input type="number" min={0} max={99} value={form.claim_count || ''}
                  onChange={e => setForm((p: any) => ({ ...p, claim_count: e.target.value }))}
                  className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none" />
              </div>

              {/* Motor Type — legacy AddVehicle "Please choose the motor type"
                  dropdown, sourced from motor_type filtered by policy.product_id. */}
              <div>
                <label className="block text-xs font-medium text-ink-muted mb-1">Motor Type *</label>
                <select value={form.vehicle_type || ''}
                  onChange={e => setForm((p: any) => ({ ...p, vehicle_type: e.target.value }))}
                  className="w-full px-3 py-2 border border-line rounded text-sm bg-surface focus:ring-2 focus:ring-primary focus:outline-none">
                  <option value="">— Select —</option>
                  {motorTypes.map((mt) => (
                    <option key={mt.id} value={String(mt.id)}>{mt.name}</option>
                  ))}
                </select>
              </div>

              {/* Risk Address — pairs the vehicle to a specific address. Motor
                  coverage dropdowns scope to this to stop vehicles from one
                  address leaking into another address's coverage. */}
              <div className="sm:col-span-2">
                <label className="block text-xs font-medium text-ink-muted mb-1">Risk Address</label>
                <select value={form.risk_id || ''}
                  onChange={e => setForm((p: any) => ({ ...p, risk_id: e.target.value }))}
                  className="w-full px-3 py-2 border border-line rounded text-sm bg-surface focus:ring-2 focus:ring-primary focus:outline-none">
                  <option value="">— Unassigned —</option>
                  {(policyRiskAddresses as any[]).map((ra: any) => (
                    <option key={ra.id} value={ra.id}>
                      {(ra.address_name || ra.addressName || 'Address')} — {(ra.physical_address || ra.physicalAddress || '').slice(0, 60)}
                    </option>
                  ))}
                </select>
                <p className="text-[10px] text-ink-faint mt-1">
                  Motor coverage vehicle picker filters to this address + unassigned vehicles.
                </p>
              </div>

              {/* Inspection photos — uploaded straight to S3 (no local storage) */}
              <div className="sm:col-span-2 pt-2 border-t border-line">
                <p className="text-xs font-semibold text-ink-muted mb-2">Inspection Photos &amp; Documents</p>
                <div className="grid grid-cols-3 sm:grid-cols-6 gap-3">
                  {VEHICLE_PHOTO_SLOTS.map(s => {
                    const slot = photos[s.key] || {}
                    const showImg = slot.preview || (!slot.remove ? slot.existing : null)
                    return (
                      <div key={s.key} className="text-center">
                        <label className="block cursor-pointer">
                          <div className="w-full h-20 rounded border border-dashed border-line bg-surface-2 flex items-center justify-center overflow-hidden hover:border-primary">
                            {showImg
                              ? <img src={showImg} alt={s.label} className="w-full h-full object-cover" />
                              : <span className="text-[11px] text-ink-faint">+ Upload</span>}
                          </div>
                          <input type="file" accept=".png,.jpg,.jpeg,.pdf" className="hidden"
                            onChange={e => pickPhoto(s.key, e.target.files?.[0] || null)} />
                        </label>
                        <div className="flex items-center justify-center gap-1 mt-1">
                          <span className="text-[11px] text-ink-muted">{s.label}</span>
                          {showImg && (
                            <button type="button" onClick={() => clearPhoto(s.key)}
                              className="text-[11px] text-status-danger-fg hover:text-status-danger-fg">✕</button>
                          )}
                        </div>
                      </div>
                    )
                  })}
                </div>
                <p className="text-[11px] text-ink-faint mt-2">JPG/PNG/PDF, max 20MB each. Click a tile to choose or replace; ✕ removes. Files upload to S3.</p>
              </div>
            </div>
            <div className="flex justify-end gap-2 px-5 py-4 border-t border-line bg-surface-2 shrink-0">
              <button onClick={() => setModal({ open: false, editing: null })} className="px-4 py-2 text-sm border border-line rounded hover:bg-surface-2">Cancel</button>
              <button onClick={save} disabled={saving} className="px-4 py-2 text-sm bg-primary text-white rounded hover:bg-primary disabled:opacity-50">
                {saving ? 'Saving…' : modal.editing ? 'Update Vehicle' : 'Add Vehicle'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

// ─── Tab: Members & Beneficiaries (lazy loaded) ─────────────────

const BLANK_PERSON = { first_name: '', middle_name: '', last_name: '', relation: '', gender: 'Male', dob: '', omang: '', passport: '', payment: '' }
const RELATIONS = ['Spouse', 'Child', 'Parent', 'Sibling', 'Partner', 'Other']

function MembersTab({ policyId }: { policyId: number }) {
  const { data, isLoading, refetch } = usePolicyMembers(policyId, true)
  const [modal, setModal] = useState<{ open: boolean; editingType: 'member' | 'beneficiary' | null; addType: 'member' | 'beneficiary' | null; record: any }>({ open: false, editingType: null, addType: null, record: null })
  const [form, setForm] = useState<any>(BLANK_PERSON)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [saving, setSaving] = useState(false)
  const [deleting, setDeleting] = useState<string | null>(null)

  const token = localStorage.getItem('sanctum_token')
  const headers: Record<string, string> = { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json', Accept: 'application/json' }
  const apiBase = `${(import.meta as any).env.VITE_API_URL}/api/v1`

  const isBeneficiary = modal.editingType === 'beneficiary' || modal.addType === 'beneficiary'
  const endpoint = isBeneficiary ? 'beneficiaries' : 'members'
  const label = isBeneficiary ? 'Beneficiary' : 'Member'

  function openAdd(type: 'member' | 'beneficiary') { setForm(BLANK_PERSON); setErrors({}); setModal({ open: true, editingType: null, addType: type, record: null }) }
  function openEdit(type: 'member' | 'beneficiary', record: any) {
    setForm({ first_name: record.firstName || '', middle_name: record.middleName || '', last_name: record.lastName || '', relation: record.relation || '', gender: record.gender || 'Male', dob: record.dob || '', omang: record.omang || '', passport: record.passport || '', payment: record.payment?.toString() || '' })
    setErrors({})
    setModal({ open: true, editingType: type, addType: null, record })
  }

  function setField(key: string, value: string) {
    setForm((p: any) => ({ ...p, [key]: value }))
    setErrors(p => { if (!p[key]) return p; const n = { ...p }; delete n[key]; return n })
  }

  function validate(): boolean {
    const e: Record<string, string> = {}
    if (!form.first_name?.trim()) e.first_name = 'First Name is required.'
    if (!form.last_name?.trim()) e.last_name = 'Last Name is required.'
    if (!form.relation) e.relation = 'Relation is required.'
    if (form.omang && !/^\d{4}[12]\d{4}$/.test(form.omang)) e.omang = 'Omang must be 9 digits and the 5th digit must be 1 or 2.'
    if (form.payment !== '' && form.payment != null) {
      const n = Number(form.payment)
      if (Number.isNaN(n)) e.payment = 'Payment Share (%) must be a number.'
      else if (n < 1 || n > 100) e.payment = 'Payment Share (%) must be between 1 and 100.'
    }
    setErrors(e)
    return Object.keys(e).length === 0
  }

  async function save() {
    if (!validate()) return
    setSaving(true)
    try {
      const isEdit = !!modal.record
      const url = isEdit ? `${apiBase}/policies/${policyId}/${endpoint}/${modal.record.id}` : `${apiBase}/policies/${policyId}/${endpoint}`
      const method = isEdit ? 'PUT' : 'POST'
      const r = await fetch(url, { method, headers, body: JSON.stringify(form) })
      const d = await r.json()
      if (!r.ok) {
        // Laravel 422 → { errors: { field: [msg, …] } }; map onto the fields
        if (r.status === 422 && d.errors) {
          const e: Record<string, string> = {}
          for (const [k, msgs] of Object.entries(d.errors)) e[k] = Array.isArray(msgs) ? msgs[0] : String(msgs)
          setErrors(e)
        } else alert(d.error || d.message || 'Failed')
        setSaving(false); return
      }
      setModal({ open: false, editingType: null, addType: null, record: null }); refetch()
    } catch (e: any) { alert(e.message) }
    setSaving(false)
  }

  async function remove(type: 'member' | 'beneficiary', id: number) {
    if (!confirm(`Delete this ${type}?`)) return
    const key = `${type}_${id}`
    setDeleting(key)
    try {
      const ep = type === 'beneficiary' ? 'beneficiaries' : 'members'
      const r = await fetch(`${apiBase}/policies/${policyId}/${ep}/${id}`, { method: 'DELETE', headers })
      const d = await r.json()
      if (!r.ok) alert(d.error || 'Failed')
      else refetch()
    } catch (e: any) { alert(e.message) }
    setDeleting(null)
  }

  if (isLoading) return <ProgressBar isLoading label="Loading members" className="max-w-xs mx-auto py-8" />

  const members = data?.members ?? []
  const beneficiaries = data?.beneficiaries ?? []

  return (
    <div className="space-y-6">
      {/* Members section */}
      <div>
        <div className="flex items-center justify-between mb-3">
          <h3 className="text-sm font-semibold text-ink-muted uppercase tracking-wider">Sub-Applicants / Members</h3>
          <button onClick={() => openAdd('member')} className="px-3 py-1.5 bg-primary text-white rounded text-xs hover:bg-primary">+ Add Member</button>
        </div>
        {members.length === 0 ? <p className="text-sm text-ink-faint italic">No members.</p> : (
          <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            {members.map((m: PolicyMember, i: number) => (
              <PersonTileEditable key={m.id} index={i + 1} type="Member" person={m} onEdit={() => openEdit('member', m)} onDelete={() => remove('member', m.id)} deleting={deleting === `member_${m.id}`} />
            ))}
          </div>
        )}
      </div>

      {/* Beneficiaries section */}
      <div>
        <div className="flex items-center justify-between mb-3">
          <h3 className="text-sm font-semibold text-ink-muted uppercase tracking-wider">Beneficiaries</h3>
          <button onClick={() => openAdd('beneficiary')} className="px-3 py-1.5 bg-status-success-fg text-white rounded text-xs hover:bg-status-success-fg">+ Add Beneficiary</button>
        </div>
        {beneficiaries.length === 0 ? <p className="text-sm text-ink-faint italic">No beneficiaries.</p> : (
          <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            {beneficiaries.map((b: PolicyBeneficiary, i: number) => (
              <PersonTileEditable key={b.id} index={i + 1} type="Beneficiary" person={b} onEdit={() => openEdit('beneficiary', b)} onDelete={() => remove('beneficiary', b.id)} deleting={deleting === `beneficiary_${b.id}`} />
            ))}
          </div>
        )}
      </div>

      {/* Add/Edit Modal */}
      {modal.open && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-start justify-center p-4 pt-10" onClick={(e) => { if (e.target === e.currentTarget) setModal({ open: false, editingType: null, addType: null, record: null }) }}>
          <div className="bg-surface rounded-xl shadow-xl w-full max-w-lg max-h-[85vh] overflow-y-auto">
            <div className="flex items-center justify-between px-5 py-3 border-b border-line sticky top-0 bg-surface z-10">
              <h3 className="font-semibold text-ink">{modal.record ? `Edit ${label}` : `Add ${label}`}</h3>
              <button onClick={() => setModal({ open: false, editingType: null, addType: null, record: null })} className="text-ink-faint hover:text-ink-muted text-xl leading-none">✕</button>
            </div>
            <div className="px-5 py-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
              {[
                { key: 'first_name', label: 'First Name *' },
                { key: 'middle_name', label: 'Middle Name' },
                { key: 'last_name', label: 'Last Name *' },
                { key: 'dob', label: 'Date of Birth', type: 'date' },
                { key: 'omang', label: 'Omang' },
                { key: 'passport', label: 'Passport' },
                { key: 'payment', label: 'Payment Share (%)', type: 'number', min: 1, max: 100 },
              ].map(({ key, label: lbl, type, min, max }: any) => (
                <div key={key}>
                  <label className="block text-xs font-medium text-ink-muted mb-1">{lbl}</label>
                  <input type={type || 'text'} min={min} max={max} value={form[key] || ''} onChange={e => setField(key, e.target.value)}
                    className={`w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:outline-none ${errors[key] ? 'border-status-danger-fg focus:ring-status-danger-fg' : 'focus:ring-primary'}`} />
                  {errors[key] && <p className="text-xs text-status-danger-fg mt-1">{errors[key]}</p>}
                </div>
              ))}
              <div>
                <label className="block text-xs font-medium text-ink-muted mb-1">Relation *</label>
                <select value={form.relation} onChange={e => setField('relation', e.target.value)}
                  className={`w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:outline-none ${errors.relation ? 'border-status-danger-fg focus:ring-status-danger-fg' : 'focus:ring-primary'}`}>
                  <option value="">Select…</option>
                  {RELATIONS.map(r => <option key={r} value={r}>{r}</option>)}
                </select>
                {errors.relation && <p className="text-xs text-status-danger-fg mt-1">{errors.relation}</p>}
              </div>
              <div>
                <label className="block text-xs font-medium text-ink-muted mb-1">Gender *</label>
                <select value={form.gender} onChange={e => setField('gender', e.target.value)}
                  className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none">
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                </select>
              </div>
            </div>
            <div className="flex justify-end gap-2 px-5 py-4 border-t border-line bg-surface-2">
              <button onClick={() => setModal({ open: false, editingType: null, addType: null, record: null })} className="px-4 py-2 text-sm border border-line rounded hover:bg-surface-2">Cancel</button>
              <button onClick={save} disabled={saving} className="px-4 py-2 text-sm bg-primary text-white rounded hover:bg-primary disabled:opacity-50">
                {saving ? 'Saving…' : modal.record ? `Update ${label}` : `Add ${label}`}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

function PersonTileEditable({ index, type, person, onEdit, onDelete, deleting }: { index: number; type: string; person: PolicyMember | PolicyBeneficiary; onEdit: () => void; onDelete: () => void; deleting: boolean }) {
  const fullName = [person.firstName, person.middleName, person.lastName].filter(Boolean).join(' ')
  const idType = person.omang ? 'Omang' : person.passport ? 'Passport' : null
  const idNumber = person.omang || person.passport || null

  return (
    <div className="bg-surface rounded-lg border border-line shadow-sm overflow-hidden">
      <div className="bg-surface-2 px-4 py-2.5 border-b border-line flex items-center justify-between">
        <span className="text-sm font-semibold text-ink-muted">{type} {index}</span>
        <div className="flex items-center gap-1.5">
          {person.payment != null && Number(person.payment) > 0 && (
            <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-brand-navy/10 text-brand-navy">{person.payment}%</span>
          )}
          <button onClick={onEdit} className="text-xs px-2 py-0.5 bg-surface-2 hover:bg-surface-2 rounded border border-line">Edit</button>
          <button onClick={onDelete} disabled={deleting} className="text-xs px-2 py-0.5 bg-status-danger-bg hover:bg-status-danger-bg text-status-danger-fg rounded border border-status-danger-fg disabled:opacity-50">{deleting ? '…' : 'Del'}</button>
        </div>
      </div>
      <div className="px-4 py-3 space-y-2">
        <InfoRow label="name" value={capitalizeValue(fullName)} />
        <InfoRow label="relationship" value={person.relation} />
        <InfoRow label="dateOfBirth" value={fmtDate(person.dob)} />
        <InfoRow label="gender" value={person.gender} />
        {idType && <InfoRow label="idType" value={idType} />}
        {idNumber && <InfoRow label="idNumber" value={idNumber} />}
        {person.cellphone && <InfoRow label="cellphone" value={person.cellphone} />}
        {person.email && <InfoRow label="email" value={person.email} />}
      </div>
    </div>
  )
}

// ─── Tab: Hospital Cashback Co-Applicants (product 9 only) ─────────

const BLANK_COAPPLICANT = { relation: 'spouse', first_name: '', middle_name: '', last_name: '', gender: 'Male', dob: '', omang: '', passport: '' }

function CoApplicantsTab({ policyId }: { policyId: number }) {
  const { data, isLoading, refetch } = usePolicyCoApplicants(policyId, true)
  const [modal, setModal] = useState<{ open: boolean; record: any }>({ open: false, record: null })
  const [form, setForm] = useState<any>(BLANK_COAPPLICANT)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [saving, setSaving] = useState(false)
  const [deleting, setDeleting] = useState<number | null>(null)

  const token = localStorage.getItem('sanctum_token')
  const headers: Record<string, string> = { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json', Accept: 'application/json' }
  const apiBase = `${(import.meta as any).env.VITE_API_URL}/api/v1`

  const canManage = (() => {
    try {
      const perms = JSON.parse(localStorage.getItem('user_permissions') || '[]') as string[]
      return perms.length === 0 || perms.includes('hcb-coapplicants-manage')
    } catch { return true }
  })()

  function openAdd() { setForm(BLANK_COAPPLICANT); setErrors({}); setModal({ open: true, record: null }) }
  function openEdit(record: any) {
    setForm({
      relation: record.relation === 'spouse' ? 'spouse' : 'child',
      first_name: record.first_name || '', middle_name: record.middle_name || '', last_name: record.last_name || '',
      gender: Number(record.gender) === 1 ? 'Male' : 'Female',
      dob: (record.dob || '').slice(0, 10), omang: record.omang || '', passport: record.passport || '',
    })
    setErrors({})
    setModal({ open: true, record })
  }

  function setField(key: string, value: string) {
    setForm((p: any) => ({ ...p, [key]: value }))
    setErrors(p => { if (!p[key]) return p; const n = { ...p }; delete n[key]; return n })
  }

  function validate(): boolean {
    const e: Record<string, string> = {}
    if (!form.first_name?.trim()) e.first_name = 'First Name is required.'
    if (!form.last_name?.trim()) e.last_name = 'Last Name is required.'
    if (form.omang && !/^\d{9}$/.test(form.omang)) e.omang = 'Omang must be 9 digits.'
    setErrors(e)
    return Object.keys(e).length === 0
  }

  async function save() {
    if (!validate()) return
    setSaving(true)
    try {
      const isEdit = !!modal.record
      const url = isEdit ? `${apiBase}/policies/${policyId}/coapplicants/${modal.record.id}` : `${apiBase}/policies/${policyId}/coapplicants`
      const method = isEdit ? 'PUT' : 'POST'
      const r = await fetch(url, { method, headers, body: JSON.stringify(form) })
      const d = await r.json()
      if (!r.ok) {
        if (r.status === 422 && d.errors) {
          const e: Record<string, string> = {}
          for (const [k, msgs] of Object.entries(d.errors)) e[k] = Array.isArray(msgs) ? msgs[0] : String(msgs)
          setErrors(e)
        } else alert(d.error || d.message || 'Failed')
        setSaving(false); return
      }
      setModal({ open: false, record: null }); refetch()
    } catch (e: any) { alert(e.message) }
    setSaving(false)
  }

  async function remove(id: number) {
    if (!confirm('Remove this co-applicant? Premium will recalculate immediately.')) return
    setDeleting(id)
    try {
      const r = await fetch(`${apiBase}/policies/${policyId}/coapplicants/${id}`, { method: 'DELETE', headers })
      const d = await r.json()
      if (!r.ok) alert(d.error || d.message || 'Failed')
      else refetch()
    } catch (e: any) { alert(e.message) }
    setDeleting(null)
  }

  if (isLoading) return <ProgressBar isLoading label="Loading co-applicants" className="max-w-xs mx-auto py-8" />

  const coapplicants: PolicyCoApplicant[] = data?.data ?? []
  const spouseCount = coapplicants.filter(c => c.relation === 'spouse').length
  const childCount = coapplicants.filter(c => c.relation === 'child').length

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between flex-wrap gap-2">
        <div>
          <h3 className="text-sm font-semibold text-ink-muted uppercase tracking-wider">Hospital Cashback Co-Applicants</h3>
          <p className="text-xs text-ink-muted mt-1">
            Current premium: <span className="font-semibold">{fmtPula(data?.premium ?? 0)}</span> · {spouseCount}/1 spouse · {childCount}/6 children
          </p>
        </div>
        {canManage && (
          <button onClick={openAdd} className="px-3 py-1.5 bg-primary text-white rounded text-xs hover:bg-primary">+ Add Co-Applicant</button>
        )}
      </div>

      {coapplicants.length === 0 ? <p className="text-sm text-ink-faint italic">No co-applicants.</p> : (
        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
          {coapplicants.map((c, i) => (
            <div key={c.id} className="bg-surface rounded-lg border border-line shadow-sm overflow-hidden">
              <div className="bg-surface-2 px-4 py-2.5 border-b border-line flex items-center justify-between">
                <span className="text-sm font-semibold text-ink-muted">{c.relation === 'spouse' ? 'Spouse' : 'Child'} {i + 1}</span>
                {canManage && (
                  <div className="flex items-center gap-1.5">
                    <button onClick={() => openEdit(c)} className="text-xs px-2 py-0.5 bg-surface-2 hover:bg-surface-2 rounded border border-line">Edit</button>
                    <button onClick={() => remove(c.id)} disabled={deleting === c.id} className="text-xs px-2 py-0.5 bg-status-danger-bg hover:bg-status-danger-bg text-status-danger-fg rounded border border-status-danger-fg disabled:opacity-50">{deleting === c.id ? '…' : 'Del'}</button>
                  </div>
                )}
              </div>
              <div className="px-4 py-3 space-y-2">
                <InfoRow label="name" value={capitalizeValue([c.first_name, c.middle_name, c.last_name].filter(Boolean).join(' '))} />
                <InfoRow label="dateOfBirth" value={fmtDate(c.dob)} />
                <InfoRow label="gender" value={Number(c.gender) === 1 ? 'Male' : 'Female'} />
                {c.omang && <InfoRow label="idType" value="Omang" />}
                {(c.omang || c.passport) && <InfoRow label="idNumber" value={c.omang || c.passport || ''} />}
              </div>
            </div>
          ))}
        </div>
      )}

      {modal.open && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-start justify-center p-4 pt-10" onClick={(e) => { if (e.target === e.currentTarget) setModal({ open: false, record: null }) }}>
          <div className="bg-surface rounded-xl shadow-xl w-full max-w-lg max-h-[85vh] overflow-y-auto">
            <div className="flex items-center justify-between px-5 py-3 border-b border-line sticky top-0 bg-surface z-10">
              <h3 className="font-semibold text-ink">{modal.record ? 'Edit Co-Applicant' : 'Add Co-Applicant'}</h3>
              <button onClick={() => setModal({ open: false, record: null })} className="text-ink-faint hover:text-ink-muted text-xl leading-none">✕</button>
            </div>
            <div className="px-5 py-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-medium text-ink-muted mb-1">Relation *</label>
                <select value={form.relation} onChange={e => setField('relation', e.target.value)}
                  className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none">
                  <option value="spouse" disabled={!modal.record && spouseCount >= 1}>Spouse / Immediate Dependent (max 1)</option>
                  <option value="child" disabled={!modal.record && childCount >= 6}>Child (max 6)</option>
                </select>
              </div>
              <div>
                <label className="block text-xs font-medium text-ink-muted mb-1">Gender *</label>
                <select value={form.gender} onChange={e => setField('gender', e.target.value)}
                  className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none">
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                </select>
              </div>
              {[
                { key: 'first_name', label: 'First Name *' },
                { key: 'middle_name', label: 'Middle Name' },
                { key: 'last_name', label: 'Last Name *' },
                { key: 'dob', label: 'Date of Birth', type: 'date' },
                { key: 'omang', label: 'Omang' },
                { key: 'passport', label: 'Passport' },
              ].map(({ key, label: lbl, type }: any) => (
                <div key={key}>
                  <label className="block text-xs font-medium text-ink-muted mb-1">{lbl}</label>
                  <input type={type || 'text'} value={form[key] || ''} onChange={e => setField(key, e.target.value)}
                    className={`w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:outline-none ${errors[key] ? 'border-status-danger-fg focus:ring-status-danger-fg' : 'focus:ring-primary'}`} />
                  {errors[key] && <p className="text-xs text-status-danger-fg mt-1">{errors[key]}</p>}
                </div>
              ))}
            </div>
            <div className="flex justify-end gap-2 px-5 py-4 border-t border-line bg-surface-2">
              <button onClick={() => setModal({ open: false, record: null })} className="px-4 py-2 text-sm border border-line rounded hover:bg-surface-2">Cancel</button>
              <button onClick={save} disabled={saving} className="px-4 py-2 text-sm bg-primary text-white rounded hover:bg-primary disabled:opacity-50">
                {saving ? 'Saving…' : modal.record ? 'Update Co-Applicant' : 'Add Co-Applicant'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

// ─── Tab: Devices (lazy loaded) ─────────────────────────────────

const BLANK_DEVICE = { device_type: 'Cellphone', imei: '', cell_phone_make: '', cell_phone_model: '', phone_value: '' }

// Pre-inspection photo gallery for a cellphone device (graphiteBWV8 parity:
// front/back/left/right/top/bottom). Renders nothing when no photos exist.
function DeviceImages({ images }: { images?: PolicyDevice['images'] }) {
  if (!images) return null
  const slots: { key: keyof NonNullable<PolicyDevice['images']>; label: string }[] = [
    { key: 'front', label: 'Front' },
    { key: 'back', label: 'Back' },
    { key: 'left', label: 'Left' },
    { key: 'right', label: 'Right' },
    { key: 'top', label: 'Top' },
    { key: 'bottom', label: 'Bottom' },
    { key: 'invoice', label: 'Invoice' },
  ]
  const present = slots.filter(s => images[s.key])
  if (present.length === 0) return null
  return (
    <div className="mt-4 pt-4 border-t border-line">
      <p className="text-xs font-semibold text-ink-muted mb-2">Pre-inspection Photos</p>
      <div className="grid grid-cols-3 sm:grid-cols-6 gap-3">
        {present.map(s => (
          <a key={s.key} href={images[s.key] as string} target="_blank" rel="noopener noreferrer" className="block group">
            <img src={images[s.key] as string} alt={s.label}
              className="w-full h-24 object-cover rounded border border-line group-hover:ring-2 group-hover:ring-primary" />
            <span className="block text-center text-[11px] text-ink-muted mt-1">{s.label}</span>
          </a>
        ))}
      </div>
    </div>
  )
}

// Photo slots shown in the Add/Edit Device modal (graphiteBWV8 parity).
const DEVICE_PHOTO_SLOTS: { key: keyof NonNullable<PolicyDevice['images']>; label: string }[] = [
  { key: 'front', label: 'Front' },
  { key: 'back', label: 'Back' },
  { key: 'left', label: 'Left' },
  { key: 'right', label: 'Right' },
  { key: 'top', label: 'Top' },
  { key: 'bottom', label: 'Bottom' },
  { key: 'invoice', label: 'Invoice' },
]

type PhotoSlot = { file?: File; preview?: string; existing?: string | null; remove?: boolean }

function DevicesTab({ policyId }: { policyId: number }) {
  const { data: devices, isLoading, refetch } = usePolicyDevices(policyId, true)
  const [modal, setModal] = useState<{ open: boolean; editing: any | null }>({ open: false, editing: null })
  const [form, setForm] = useState<any>(BLANK_DEVICE)
  const [photos, setPhotos] = useState<Record<string, PhotoSlot>>({})
  const [saving, setSaving] = useState(false)
  const [deleting, setDeleting] = useState<number | null>(null)

  const token = localStorage.getItem('sanctum_token')
  // NB: multipart uploads must NOT set Content-Type (the browser adds the boundary).
  const headers: Record<string, string> = { Authorization: `Bearer ${token}`, Accept: 'application/json' }
  const apiBase = `${(import.meta as any).env.VITE_API_URL}/api/v1`

  function openAdd() { setForm(BLANK_DEVICE); setPhotos({}); setModal({ open: true, editing: null }) }
  function openEdit(d: any) {
    setForm({ device_type: d.deviceType || 'Cellphone', imei: d.imei || '', cell_phone_make: d.make || '', cell_phone_model: d.model || '', phone_value: d.value?.toString() || '' })
    const init: Record<string, PhotoSlot> = {}
    DEVICE_PHOTO_SLOTS.forEach(s => { init[s.key] = { existing: d.images?.[s.key] ?? null } })
    setPhotos(init)
    setModal({ open: true, editing: d })
  }

  function pickPhoto(key: string, file: File | null) {
    setPhotos(p => ({ ...p, [key]: { ...p[key], file: file || undefined, preview: file ? URL.createObjectURL(file) : undefined, remove: false } }))
  }
  function clearPhoto(key: string) {
    // Clears a newly-picked file, or marks an existing stored photo for removal.
    setPhotos(p => ({ ...p, [key]: { ...p[key], file: undefined, preview: undefined, remove: !!p[key]?.existing } }))
  }

  async function save() {
    setSaving(true)
    try {
      const url = modal.editing ? `${apiBase}/policies/${policyId}/devices/${modal.editing.id}` : `${apiBase}/policies/${policyId}/devices`
      const body = new FormData()
      // Method spoofing: PHP only parses multipart on POST, so edits POST with _method=PUT.
      if (modal.editing) body.append('_method', 'PUT')
      Object.entries(form).forEach(([k, v]) => body.append(k, (v as any) ?? ''))
      DEVICE_PHOTO_SLOTS.forEach(s => {
        const slot = photos[s.key]
        if (slot?.file) body.append(`cell_phone_${s.key}`, slot.file)
        else if (slot?.remove) body.append(`remove_${s.key}`, '1')
      })
      const r = await fetch(url, { method: 'POST', headers, body })
      const d = await r.json()
      if (!r.ok) { alert(d.error || d.message || 'Failed'); setSaving(false); return }
      setModal({ open: false, editing: null }); refetch()
    } catch (e: any) { alert(e.message) }
    setSaving(false)
  }

  async function remove(id: number) {
    if (!confirm('Delete this device?')) return
    setDeleting(id)
    try {
      const r = await fetch(`${apiBase}/policies/${policyId}/devices/${id}`, { method: 'DELETE', headers })
      const d = await r.json()
      if (!r.ok) alert(d.error || 'Failed')
      else refetch()
    } catch (e: any) { alert(e.message) }
    setDeleting(null)
  }

  if (isLoading) return <ProgressBar isLoading label="Loading devices" className="max-w-xs mx-auto py-8" />

  return (
    <div className="space-y-4">
      <div className="flex justify-end">
        <button onClick={openAdd} className="px-4 py-2 bg-primary text-white rounded text-sm hover:bg-primary">+ Add Device</button>
      </div>

      {(!devices || devices.length === 0) && <EmptyState message="No devices attached to this policy." />}

      {devices?.map((d: PolicyDevice, i: number) => (
        <Card key={d.id} title={`Device ${i + 1} — ${d.make} ${d.model}`}>
          <div className="flex justify-end gap-2 mb-2">
            <button onClick={() => openEdit(d)} className="px-3 py-1 text-xs bg-surface-2 hover:bg-surface-2 rounded border border-line">Edit</button>
            <button onClick={() => remove(d.id)} disabled={deleting === d.id} className="px-3 py-1 text-xs bg-status-danger-bg hover:bg-status-danger-bg text-status-danger-fg rounded border border-status-danger-fg disabled:opacity-50">
              {deleting === d.id ? 'Deleting…' : 'Delete'}
            </button>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-2">
            <InfoRow label="deviceType" value={d.deviceType} />
            <InfoRow label={d.deviceType === 'Cellphone' ? 'imei' : 'serialNumber'} value={d.imei} />
            <InfoRow label="make" value={d.make} />
            <InfoRow label="model" value={d.model} />
            <InfoRow label="value" value={fmtCurrency(d.value)} />
          </div>
          <DeviceImages images={d.images} />
        </Card>
      ))}

      {modal.open && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-start justify-center p-4 pt-10" onClick={(e) => { if (e.target === e.currentTarget) setModal({ open: false, editing: null }) }}>
          <div className="bg-surface rounded-xl shadow-xl w-full max-w-2xl max-h-[85vh] overflow-y-auto">
            <div className="flex items-center justify-between px-5 py-3 border-b border-line sticky top-0 bg-surface">
              <h3 className="font-semibold text-ink">{modal.editing ? 'Edit Device' : 'Add Device'}</h3>
              <button onClick={() => setModal({ open: false, editing: null })} className="text-ink-faint hover:text-ink-muted text-xl leading-none">✕</button>
            </div>
            <div className="px-5 py-4 space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-medium text-ink-muted mb-1">Device Type *</label>
                  <select value={form.device_type} onChange={e => setForm((p: any) => ({ ...p, device_type: e.target.value }))}
                    className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none">
                    {['Cellphone', 'Tablet', 'Laptop', 'Other'].map(t => <option key={t} value={t}>{t}</option>)}
                  </select>
                </div>
                {[
                  { key: 'imei', label: 'IMEI / Serial No *' },
                  { key: 'cell_phone_make', label: 'Make *' },
                  { key: 'cell_phone_model', label: 'Model *' },
                  { key: 'phone_value', label: 'Value (P)' },
                ].map(({ key, label }) => (
                  <div key={key}>
                    <label className="block text-xs font-medium text-ink-muted mb-1">{label}</label>
                    <input value={form[key] || ''} onChange={e => setForm((p: any) => ({ ...p, [key]: e.target.value }))}
                      className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none" />
                  </div>
                ))}
              </div>

              <div className="pt-2 border-t border-line">
                <p className="text-xs font-semibold text-ink-muted mb-2">Pre-inspection Photos</p>
                <div className="grid grid-cols-3 sm:grid-cols-4 gap-3">
                  {DEVICE_PHOTO_SLOTS.map(s => {
                    const slot = photos[s.key] || {}
                    const showImg = slot.preview || (!slot.remove ? slot.existing : null)
                    return (
                      <div key={s.key} className="text-center">
                        <label className="block cursor-pointer">
                          <div className="w-full h-20 rounded border border-dashed border-line bg-surface-2 flex items-center justify-center overflow-hidden hover:border-primary">
                            {showImg
                              ? <img src={showImg} alt={s.label} className="w-full h-full object-cover" />
                              : <span className="text-[11px] text-ink-faint">+ Upload</span>}
                          </div>
                          <input type="file" accept=".png,.jpg,.jpeg,.pdf" className="hidden"
                            onChange={e => pickPhoto(s.key, e.target.files?.[0] || null)} />
                        </label>
                        <div className="flex items-center justify-center gap-1 mt-1">
                          <span className="text-[11px] text-ink-muted">{s.label}</span>
                          {showImg && (
                            <button type="button" onClick={() => clearPhoto(s.key)}
                              className="text-[11px] text-status-danger-fg hover:text-status-danger-fg">✕</button>
                          )}
                        </div>
                      </div>
                    )
                  })}
                </div>
                <p className="text-[11px] text-ink-faint mt-2">JPG/PNG/PDF, max 20MB each. Click a tile to choose or replace; ✕ removes.</p>
              </div>
            </div>
            <div className="flex justify-end gap-2 px-5 py-4 border-t border-line bg-surface-2">
              <button onClick={() => setModal({ open: false, editing: null })} className="px-4 py-2 text-sm border border-line rounded hover:bg-surface-2">Cancel</button>
              <button onClick={save} disabled={saving} className="px-4 py-2 text-sm bg-primary text-white rounded hover:bg-primary disabled:opacity-50">
                {saving ? 'Saving…' : modal.editing ? 'Update Device' : 'Add Device'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

// ─── Tab: Coverages (lazy loaded) ───────────────────────────────

function CoveragesTab({ policyId, productId = 0, overrideActionId, readOnly = false }: { policyId: number; productId?: number; overrideActionId?: number; readOnly?: boolean }) {
  const actionsQuery = usePolicyActions(policyId, true)
  const [selectedActionId, setSelectedActionId] = useState<number | null>(null)
  const [covData, setCovData] = useState<any>(null)
  const [covLoading, setCovLoading] = useState(true)
  const [expanded, setExpanded] = useState<Record<string, boolean>>({})
  const [editedDetails, setEditedDetails] = useState<Record<string, any>>({})
  const [saving, setSaving] = useState(false)

  // ── Motor edit modal state ──────────────────────────────────────
  const [motorModal, setMotorModal] = useState<{
    open: boolean; coverageId: number | null; motor: any | null
  }>({ open: false, coverageId: null, motor: null })
  const [motorNote, setMotorNote] = useState('')
  const [motorItems, setMotorItems] = useState<any[]>([])
  const [motorSaving, setMotorSaving] = useState(false)
  // Master list (from `specified_coverage_items`) keyed by coverage.
  // Legacy admin listing in graphiteBWV8 Livewire/Coverage/SpecifiedCoverage/Edit
  // is fed by the same table — we reuse it here.
  const [masterItems, setMasterItems] = useState<Array<{ id: number; name: string; rate: number | null }>>([])
  // Master-dropdown only (no free-text path). Matches legacy ManageCoverages.
  const [addingItemOpen, setAddingItemOpen] = useState(false)
  const [newItemMasterId, setNewItemMasterId] = useState('')
  const [newItemSum, setNewItemSum] = useState('')
  const [newItemRate, setNewItemRate] = useState('')
  const [addingItem, setAddingItem] = useState(false)
  const resetAddItem = () => { setAddingItemOpen(false); setNewItemMasterId(''); setNewItemSum(''); setNewItemRate('') }

  async function openMotorEdit(coverageId: number, motor: any) {
    setMotorModal({ open: true, coverageId, motor })
    setMotorNote(motor.note || '')
    setMotorItems(motor.specifiedItems || [])
    resetAddItem()
    // Fetch the master catalogue for this coverage so the Add-Item row is a
    // dropdown instead of a free-text field.
    try {
      const res = await fetch(`${apiBase}/lookups/coverages/${coverageId}/specified-items`, { headers: apiHeaders })
      const json = await res.json()
      setMasterItems(json.data ?? [])
    } catch { setMasterItems([]) }
  }

  async function saveMotorNote() {
    if (!motorModal.motor || !motorModal.coverageId) return
    setMotorSaving(true)
    try {
      await fetch(`${apiBase}/policies/${policyId}/coverages/${motorModal.coverageId}/motor/${motorModal.motor.id}/note`, {
        method: 'PUT', headers: apiHeaders,
        body: JSON.stringify({ note: motorNote }),
      })
      const fresh = await fetchPolicyCoverages(policyId, selectedActionId!)
      setCovData(fresh)
      // Sync updated vehicle from fresh data
      const updatedCov = (fresh.data || []).find((c: any) => c.id === motorModal.coverageId)
      const updatedMotor = (updatedCov?.vehicles || []).find((v: any) => v.id === motorModal.motor.id)
      if (updatedMotor) { setMotorModal(p => ({ ...p, motor: updatedMotor })); setMotorItems(updatedMotor.specifiedItems || []) }
    } catch (e: any) { alert(e.message || 'Failed to save note') }
    setMotorSaving(false)
  }

  async function addMotorItem() {
    if (!motorModal.motor || !motorModal.coverageId || !newItemSum || !newItemMasterId) return
    setAddingItem(true)
    try {
      const calcVal = newItemRate ? ((parseFloat(newItemSum) * parseFloat(newItemRate)) / 100).toFixed(2) : '0'
      const payload: any = {
        specified_coverage_id: parseInt(newItemMasterId, 10),
        sum_insured: parseFloat(newItemSum),
        rate: parseFloat(newItemRate || '0'),
        calculated_value: parseFloat(calcVal),
      }
      const res = await fetch(`${apiBase}/policies/${policyId}/coverages/${motorModal.coverageId}/motor/${motorModal.motor.id}/specified-items`, {
        method: 'POST', headers: apiHeaders,
        body: JSON.stringify(payload),
      })
      const json = await res.json()
      if (!res.ok) { alert(json.error || json.message || 'Failed'); setAddingItem(false); return }
      setMotorItems(p => [...p, json.data])
      resetAddItem()
      const fresh = await fetchPolicyCoverages(policyId, selectedActionId!)
      setCovData(fresh)
    } catch (e: any) { alert(e.message || 'Failed') }
    setAddingItem(false)
  }

  async function removeMotorItem(itemId: number) {
    if (!motorModal.motor || !motorModal.coverageId) return
    if (!confirm('Remove this specified item?')) return
    try {
      await fetch(`${apiBase}/policies/${policyId}/coverages/${motorModal.coverageId}/motor/${motorModal.motor.id}/specified-items/${itemId}`, {
        method: 'DELETE', headers: apiHeaders,
      })
      setMotorItems(p => p.filter(i => i.id !== itemId))
      const fresh = await fetchPolicyCoverages(policyId, selectedActionId!)
      setCovData(fresh)
    } catch (e: any) { alert(e.message || 'Failed') }
  }

  const actions = actionsQuery.data?.history || []
  const currentAction = actionsQuery.data?.current

  // Set default action on load (use override if provided)
  useEffect(() => {
    if (overrideActionId) setSelectedActionId(overrideActionId)
    else if (currentAction && !selectedActionId) setSelectedActionId(currentAction.id)
  }, [currentAction, overrideActionId])

  // Fetch coverages when action changes
  const effectiveActionId = overrideActionId || selectedActionId
  useEffect(() => {
    if (!effectiveActionId) return
    setCovLoading(true)
    fetchPolicyCoverages(policyId, effectiveActionId)
      .then(d => setCovData(d))
      .catch(() => setCovData(null))
      .finally(() => setCovLoading(false))
  }, [policyId, selectedActionId])

  const isDomCom = covData?.type === 'domcom'
  const coverages: any[] = covData?.data || []
  const endorsementReason: string | null = covData?.endorsementReason ?? null

  // Editable only while the policy action is still a QUOTE. Matches legacy
  // graphiteBWV8 EditWizard behaviour — operators must click "Unissue" to
  // flip an ISSUED / APPROVED action back to QUOTE before they can edit
  // coverages. Prevents silent mid-term mutation of an in-force policy.
  const selectedAction = actions.find((a: any) => a.id === selectedActionId)
  const actionStatus = selectedAction?.status || currentAction?.status || ''
  // Force read-only when the caller asks (e.g. the embedded view on the
  // Policy Actions tab — edits go through the dedicated wizard there via
  // the "✎ Edit Coverages" button, not inline controls).
  const isEditable = !readOnly && actionStatus === 'QUOTE'
  const isIssued   = ['ISSUED', 'APPROVED'].includes(actionStatus)
  const [unissuing, setUnissuing] = useState(false)

  async function handleUnissue() {
    if (!window.confirm(`Unissue this ${actionStatus} action? Status will revert to QUOTE so coverages can be edited. You'll need to re-approve and re-issue afterwards.`)) return
    setUnissuing(true)
    try {
      const r = await fetch(`${apiBase}/policies/${policyId}/unissue`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${localStorage.getItem('sanctum_token')}`, Accept: 'application/json', 'Content-Type': 'application/json' },
      })
      const d = await r.json()
      if (!r.ok) { alert(d.error || d.message || 'Unissue failed'); setUnissuing(false); return }
      alert(d.message || 'Action reverted to QUOTE.')
      actionsQuery.refetch()
      // Reload coverage data with the fresh status
      setCovLoading(true)
      const fresh = await fetchPolicyCoverages(policyId, selectedActionId!)
      setCovData(fresh)
    } catch (e: any) { alert(e?.message || 'Unissue failed') }
    setUnissuing(false)
  }

  const token = localStorage.getItem('sanctum_token')
  const apiBase = `${(import.meta as any).env.VITE_API_URL || ''}/api/v1`
  const apiHeaders: Record<string, string> = { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json', Accept: 'application/json' }

  // Get or set edited value for a detail row
  const getDetailVal = (detailId: number, field: string, original: any) => {
    const key = `${detailId}_${field}`
    return editedDetails[key] !== undefined ? editedDetails[key] : (original ?? '')
  }
  const setDetailVal = (detailId: number, field: string, value: string) => {
    setEditedDetails(prev => ({ ...prev, [`${detailId}_${field}`]: value }))
  }

  // Save edited detail values
  const saveDetailEdits = async (coverageId: number, details: any[]) => {
    setSaving(true)
    try {
      for (const d of details) {
        const cv = getDetailVal(d.id, 'coverageValue', d.coverageValue)
        const rate = getDetailVal(d.id, 'rate', d.rate)
        const calc = cv && rate ? ((parseFloat(cv) * parseFloat(rate)) / 100).toFixed(2) : d.calculatedValue
        await fetch(`${apiBase}/policies/${policyId}/coverages/${coverageId}/details/${d.id}`, {
          method: 'PUT', headers: apiHeaders,
          body: JSON.stringify({ coverage_value: cv, rate, calculated_value: calc }),
        })
      }
      // Refresh coverages
      const fresh = await fetchPolicyCoverages(policyId, selectedActionId!)
      setCovData(fresh)
      setEditedDetails({})
    } catch (e: any) { alert(e.message || 'Failed to save') }
    setSaving(false)
  }

  // Total Coverage Premium — backend-computed canonical total so the UI,
  // the Rate banner, and the V2 Quote / Policy Doc render the same figure.
  // Backend's /coverages endpoint sums all 5 premium-bearing tables
  // (policy_coverage_detail / motor / policy_extention_detail /
  // policy_specified_items / policy_coverages_data) and exposes the result
  // as `totalPremium`. Falls back to a local sum for older deployments.
  // Comma-safe numeric parse. Legacy coverage values are stored formatted
  // ("9,153,941.00"); a bare parseFloat stops at the first comma and truncates
  // ("1,250" → 1) — the same bug fmtCurrency was patched for below. Strip the
  // thousands separators before parsing so those rows aren't silently dropped.
  const toNum = (v: any) => {
    if (v == null) return 0
    const n = typeof v === 'string' ? parseFloat(v.replace(/,/g, '')) : Number(v)
    return isNaN(n) ? 0 : n
  }
  // Motor sub-coverage premiums live on the motor row (camelCase mirror of the
  // backend premium_* columns) SEPARATELY from calculated_value (own damage).
  // The canonical v2 quote-sheet / recomputeActionTotals motor bucket is
  // calculated_value + every one of these, so the total must add them too —
  // omitting them understated every motor coverage carrying sub-coverages.
  const MOTOR_PREMIUM_FIELDS = [
    'premiumWreckageRemoval', 'premiumWindowGlass', 'premiumLocksKeys',
    'premiumPartsAccessories', 'premiumAudioAccessories', 'premiumRiotStrike',
    'premiumCarHireTheft', 'premiumCreditShortfall', 'premiumInsuredDriver',
    'premiumInsuredFamily', 'premiumMedicalExpenses', 'premiumPassengerLiability',
    'premiumThirdPartyLiability', 'premiumSpecifiedAccessories',
    'premiumUnorthorisedPassangerLiability', 'premiumParkingFacilities',
    'premiumComWindscreen', 'premiumContigentLiability',
  ]
  const motorVehiclePremium = (v: any) =>
    toNum(v.calculatedValue) + MOTOR_PREMIUM_FIELDS.reduce((a, f) => a + toNum(v[f]), 0)

  const localCoverageSum = isDomCom ? coverages.reduce((sum: number, c: any) => {
    // Coverage-level premium is snake_case `calculated_value` (specialist rows);
    // null for COM/DOM, whose premium lives in the child rows below.
    const cp = toNum(c.calculated_value ?? c.calculatedValue ?? c.proRatePremium)
    const dp = (c.details || []).reduce((s: number, d: any) => s + toNum(d.calculatedValue ?? d.proRatePremium), 0)
    const ep = (c.extensions || []).reduce((s: number, e: any) => s + toNum(e.calculatedValue ?? e.proRatePremium), 0)
    const sp = (c.specifiedItems || []).reduce((s: number, x: any) => s + toNum(x.calculatedValue), 0)
    // Fidelity Guarantee premium lives only in policy_coverages_data (fidelityData).
    const fp = (c.fidelityData || []).reduce((s: number, f: any) => s + toNum(f.premium), 0)
    const mt = (c.motorTraders || []).reduce((s: number, m: any) => s + toNum(m.totalCalculatedValue), 0)
    const vp = (c.vehicles || []).reduce((s: number, v: any) => {
      const vehicleSpecified = (v.specifiedItems || []).reduce((ss: number, x: any) => ss + toNum(x.calculatedValue), 0)
      return s + motorVehiclePremium(v) + vehicleSpecified
    }, 0)
    return sum + cp + dp + ep + sp + fp + mt + vp
  }, 0) : 0
  const backendTotal = parseFloat((covData as any)?.totalPremium ?? 0) || 0
  const totalPremium = isDomCom ? (backendTotal > 0 ? backendTotal : localCoverageSum) : 0

  // Per-coverage totals — sums Sum Insured and Premium across every
  // premium-bearing block under a single coverage. Mirrors the canonical
  // v2 quote-sheet / recomputeActionTotals recipe so each card reconciles
  // with the quote sheet: coverage-level premium (specialist) + details +
  // extensions + specified items + Fidelity (policy_coverages_data) + motor
  // traders + motor vehicles (own damage + every premium_* + their items).
  const coverageTotals = (c: any) => {
    const sum = (arr: any[], fn: (x: any) => number) => (arr || []).reduce((s, x) => s + fn(x), 0)
    const premium =
      toNum(c.calculated_value ?? c.calculatedValue) +
      sum(c.details, (d) => toNum(d.calculatedValue ?? d.proRatePremium)) +
      sum(c.extensions, (e) => toNum(e.calculatedValue ?? e.proRatePremium)) +
      sum(c.specifiedItems, (x) => toNum(x.calculatedValue)) +
      sum(c.fidelityData, (f) => toNum(f.premium)) +
      sum(c.motorTraders, (m) => toNum(m.totalCalculatedValue)) +
      sum(c.vehicles, (v) => motorVehiclePremium(v) + sum(v.specifiedItems, (x: any) => toNum(x.calculatedValue)))
    const sumInsured =
      sum(c.details, (d) => toNum(d.coverageValue)) +
      sum(c.extensions, (e) => toNum(e.sumInsured)) +
      sum(c.specifiedItems, (x) => toNum(x.sumInsured)) +
      sum(c.fidelityData, (f) => toNum(f.amountToBeGuaranteed)) +
      sum(c.motorTraders, (m) => toNum(m.totalCoverageValue)) +
      sum(c.vehicles, (v) => toNum(v.coverageValue) + sum(v.specifiedItems, (x: any) => toNum(x.sumInsured)))
    return { premium, sumInsured }
  }

  // Group coverages by risk address
  const grouped: Record<string, any[]> = {}
  coverages.forEach((c: any) => {
    const key = c.riskAddressId || 'none'
    if (!grouped[key]) grouped[key] = []
    grouped[key].push(c)
  })

  const toggle = (key: string) => setExpanded(p => ({ ...p, [key]: !p[key] }))

  if (actionsQuery.isLoading) return <ProgressBar isLoading label="Loading..." className="max-w-xs mx-auto py-8" />

  return (
    <div className="space-y-4">
      {/* Action Selector — hidden when embedded in Actions tab */}
      {actions.length > 0 && !overrideActionId && (
        <div className="flex items-center gap-3 bg-surface-2 p-3 rounded-lg border border-line">
          <label className="text-sm font-medium text-ink-muted whitespace-nowrap">Policy Action:</label>
          <select value={selectedActionId || ''} onChange={e => setSelectedActionId(Number(e.target.value))}
            className="flex-1 px-3 py-2 border border-line rounded text-sm">
            {actions.map((a: any) => (
              <option key={a.id} value={a.id}>{a.transactionType} — {a.status} ({a.effectiveFrom} to {a.effectiveTo})</option>
            ))}
          </select>
        </div>
      )}

      {/* Read-only banner when action is ISSUED/APPROVED — mirrors legacy
          graphiteBWV8 behaviour: coverages cannot be edited on a live
          action. Operator must Unissue first (flips status → QUOTE),
          make changes, then re-approve + re-issue. */}
      {isIssued && (
        <div className="bg-status-danger-bg border-2 border-status-danger-fg rounded-lg p-4">
          <div className="flex items-start gap-3">
            <span className="text-status-danger-fg text-xl">🔒</span>
            <div className="flex-1">
              <h3 className="font-semibold text-status-danger-fg">This {actionStatus} action is locked for edits</h3>
              <p className="text-sm text-status-danger-fg mt-1">
                Issued policy actions are frozen. To change coverage, vehicles, rate or premium,
                you must first <strong>Unissue</strong> to revert the action to QUOTE, make your
                edits, then re-approve and re-issue. Alternatively, create an Endorsement action
                from Policy Actions.
              </p>
            </div>
            <button
              type="button"
              onClick={handleUnissue}
              disabled={unissuing}
              className="shrink-0 px-3 py-1.5 bg-status-danger-fg text-white text-sm font-medium rounded hover:bg-status-danger-fg disabled:opacity-50"
            >
              {unissuing ? 'Unissuing…' : 'Unissue to Edit'}
            </button>
          </div>
        </div>
      )}

      {/* Add Coverage — links to edit wizard */}
      {COVERAGE_PRODUCT_IDS.includes(productId) && (
        <div className="flex justify-end">
          <a href={`/policies/${policyId}/edit`}
            className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-status-success-fg rounded-lg hover:bg-status-success-fg transition">
            + Add Coverage
          </a>
        </div>
      )}

      {/* Endorsement Reason Banner */}
      {endorsementReason && (
        <div className="flex items-start gap-2 bg-status-warning-bg border border-status-warning-fg rounded-lg px-4 py-3 text-sm text-status-warning-fg">
          <span className="font-semibold whitespace-nowrap">Endorsement Reason:</span>
          <span>{endorsementReason}</span>
        </div>
      )}

      {/* Natively-disabled fieldset when action is ISSUED/APPROVED — every
          input/button inside becomes non-interactive. Belt-and-braces on top
          of the isEditable checks already sprinkled on individual controls. */}
      <fieldset disabled={!isEditable} className="contents">
      {covLoading ? <ProgressBar isLoading label="Loading coverages..." className="max-w-xs mx-auto py-8" /> :
       coverages.length === 0 ? <EmptyState message="No coverages for this action." /> :
       isDomCom ? (
        Object.entries(grouped).map(([raId, covs]) => {
          const raName = covs[0]?.riskAddressName || `Risk Address #${raId}`
          const raKey = `ra_${raId}`
          return (
            <div key={raKey} className="border border-line rounded-lg overflow-hidden">
              {/* Risk Address Header */}
              <button onClick={() => toggle(raKey)}
                className="w-full flex items-center justify-between px-4 py-3 bg-status-info-bg text-primary font-semibold text-sm hover:bg-status-info-bg transition">
                <span>{raName}</span>
                <span className="text-xs">{expanded[raKey] === false ? '▶' : '▼'} {covs.length} coverage(s)</span>
              </button>

              {expanded[raKey] !== false && (
                <div className="divide-y">
                  {covs.map((c: any) => {
                    const covKey = `cov_${c.id}`
                    return (
                      <div key={c.id} className="border-t border-line">
                        <div className="flex items-center justify-between px-4 py-2 bg-surface-2 hover:bg-surface-2 transition text-sm">
                          <button onClick={() => toggle(covKey)} className="flex-1 text-left">
                            <span className="font-medium text-ink-muted">{c.coverageCode} <span className="text-xs text-ink-faint ml-1">{c.rowType}</span></span>
                          </button>
                          <div className="flex items-center gap-2">
                            {(() => {
                              const code = String(c.coverageCode || '').toUpperCase()
                              if (code === 'ERECTIONALLRISKS' || code === 'EAR') {
                                return (
                                  <a href={`/policies/${policyId}/specialist-coverage/ear?policy_coverage_id=${c.id}`}
                                    className="text-xs px-2 py-1 rounded bg-primary text-white hover:bg-primary"
                                    title="Open Erection All Risks – Policy Schedule">
                                    EAR Schedule
                                  </a>
                                )
                              }
                              if (code === 'PLANTALLRISKS' || code === 'PAR') {
                                return (
                                  <a href={`/policies/${policyId}/specialist-coverage/par?policy_coverage_id=${c.id}`}
                                    className="text-xs px-2 py-1 rounded bg-primary text-white hover:bg-primary"
                                    title="Open Plant All Risks – Policy Schedule">
                                    PAR Schedule
                                  </a>
                                )
                              }
                              if (code === 'CONTRACTORSALLRISKS' || code === 'CAR') {
                                return (
                                  <a href={`/policies/${policyId}/specialist-coverage/car?policy_coverage_id=${c.id}`}
                                    className="text-xs px-2 py-1 rounded bg-primary text-white hover:bg-primary"
                                    title="Open Contractors All Risks – Policy Schedule">
                                    CAR Schedule
                                  </a>
                                )
                              }
                              if (code === 'MEDICAMALPRACTICEINSURANCE' || code === 'MM') {
                                return (
                                  <a href={`/policies/${policyId}/specialist-coverage/medical-malpractice?policy_coverage_id=${c.id}`}
                                    className="text-xs px-2 py-1 rounded bg-primary text-white hover:bg-primary"
                                    title="Open Medical Malpractice – Policy Schedule">
                                    MM Schedule
                                  </a>
                                )
                              }
                              if (code === 'TRAVELINSURANCE' || code === 'TRAVEL') {
                                return (
                                  <a href={`/policies/${policyId}/specialist-coverage/travel-insurance?policy_coverage_id=${c.id}`}
                                    className="text-xs px-2 py-1 rounded bg-primary text-white hover:bg-primary"
                                    title="Open Travel Insurance – Policy Schedule">
                                    Travel Schedule
                                  </a>
                                )
                              }
                              if (code === 'PROFESSIONALINDEMNITY' || code === 'PI') {
                                return (
                                  <a href={`/policies/${policyId}/specialist-coverage/professional-indemnity?policy_coverage_id=${c.id}`}
                                    className="text-xs px-2 py-1 rounded bg-primary text-white hover:bg-primary"
                                    title="Open Professional Indemnity – Policy Schedule">
                                    PI Schedule
                                  </a>
                                )
                              }
                              if (code === 'MACHINERYBREAKDOWN' || code === 'MB') {
                                return (
                                  <a href={`/policies/${policyId}/specialist-coverage/machinery-breakdown?policy_coverage_id=${c.id}`}
                                    className="text-xs px-2 py-1 rounded bg-primary text-white hover:bg-primary"
                                    title="Open Machinery Breakdown – Policy Schedule">
                                    MB Schedule
                                  </a>
                                )
                              }
                              if (code === 'MEDICALEVACUATION') {
                                return (
                                  <a href={`/policies/${policyId}/specialist-coverage/medical-evacuation?policy_coverage_id=${c.id}`}
                                    className="text-xs px-2 py-1 rounded bg-primary text-white hover:bg-primary"
                                    title="Open Medical Evacuation – Policy Schedule">
                                    Medical Evac Schedule
                                  </a>
                                )
                              }
                              if (code === 'COMMERCIALCRIME') {
                                return (
                                  <a href={`/policies/${policyId}/specialist-coverage/commercial-crime?policy_coverage_id=${c.id}`}
                                    className="text-xs px-2 py-1 rounded bg-primary text-white hover:bg-primary"
                                    title="Open Commercial Crime – Policy Schedule">
                                    Open Commercial Crime Schedule
                                  </a>
                                )
                              }
                              if (code === 'BONDSANDGUARANTEES') {
                                return (
                                  <a href={`/policies/${policyId}/specialist-coverage/bonds?policy_coverage_id=${c.id}`}
                                    className="text-xs px-2 py-1 rounded bg-primary text-white hover:bg-primary"
                                    title="Open Bonds and Guarantees – Policy Schedule">
                                    Open Bonds Schedule
                                  </a>
                                )
                              }
                              if (code === 'MARINECARGOONCEOFF' || code === 'MARINEONCEOFFCOVER') {
                                return (
                                  <a href={`/policies/${policyId}/specialist-coverage/marine-cargo-once-off?policy_coverage_id=${c.id}`}
                                    className="text-xs px-2 py-1 rounded bg-primary text-white hover:bg-primary"
                                    title="Open Marine Cargo Once-Off – Policy Schedule">
                                    Marine Once-Off Schedule
                                  </a>
                                )
                              }
                              if (code === 'MARINECARGOOPEN' || code === 'MARINEOPENCOVER') {
                                return (
                                  <a href={`/policies/${policyId}/specialist-coverage/marine-cargo-open?policy_coverage_id=${c.id}`}
                                    className="text-xs px-2 py-1 rounded bg-primary text-white hover:bg-primary"
                                    title="Open Marine Cargo Open Cover – Policy Schedule">
                                    Marine Open Schedule
                                  </a>
                                )
                              }
                              if (code === 'MARINEDO' || code === 'MARINEDIRECTORSOFFICERS') {
                                return (
                                  <a href={`/policies/${policyId}/specialist-coverage/marine-directors-officers?policy_coverage_id=${c.id}`}
                                    className="text-xs px-2 py-1 rounded bg-primary text-white hover:bg-primary"
                                    title="Open Marine Directors & Officers – Policy Schedule">
                                    Marine D&O Schedule
                                  </a>
                                )
                              }
                              // Directors & Officers Liability reuses the marine-directors-officers
                              // schedule page — same convention as the create wizard
                              // (StepCoverages: isDoCoverage -> marine-directors-officers).
                              if (code === 'DIRECTORSOFFICERSLIABILITY' || code === 'DOL') {
                                return (
                                  <a href={`/policies/${policyId}/specialist-coverage/marine-directors-officers?policy_coverage_id=${c.id}`}
                                    className="text-xs px-2 py-1 rounded bg-primary text-white hover:bg-primary"
                                    title="Open Directors & Officers Liability – Policy Schedule">
                                    Open D&O Schedule
                                  </a>
                                )
                              }
                              return null
                            })()}
                            {isEditable && (
                              <button onClick={async () => {
                                if (!confirm(`Delete coverage "${c.coverageCode}"?`)) return
                                setSaving(true)
                                await fetch(`${apiBase}/policies/${policyId}/coverages/${c.id}`, { method: 'DELETE', headers: apiHeaders })
                                const fresh = await fetchPolicyCoverages(policyId, selectedActionId!); setCovData(fresh); setSaving(false)
                              }} className="text-status-danger-fg hover:text-status-danger-fg text-xs px-2 py-1 border border-status-danger-fg rounded hover:bg-status-danger-bg">Delete</button>
                            )}
                            <span className="text-xs text-ink-faint cursor-pointer" onClick={() => toggle(covKey)}>{expanded[covKey] === false ? '▶' : '▼'}</span>
                          </div>
                        </div>

                        {expanded[covKey] !== false && (
                          <div className="px-4 py-3 space-y-3">
                            {/* Coverage meta row */}
                            <div className="flex flex-wrap gap-4 text-xs text-ink-muted">
                              {c.coverageName && <span><span className="font-medium text-ink-muted">Name:</span> {c.coverageName}</span>}
                              {c.groupName && <span><span className="font-medium text-ink-muted">Group:</span> {c.groupName}</span>}
                              {c.masterRate && <span><span className="font-medium text-ink-muted">Master Rate:</span> {c.masterRate}%</span>}
                            </div>

                            {/* Coverage Details — editable for QUOTE, read-only for ISSUED */}
                            {c.details?.length > 0 ? (
                              <div>
                                <table className="w-full text-sm">
                                  <thead>
                                    <tr className="text-left text-xs text-ink-muted border-b border-line">
                                      <th className="pb-2 pl-2">Description</th>
                                      <th className="pb-2">Sum Insured</th>
                                      <th className="pb-2">Rate %</th>
                                      <th className="pb-2">Premium</th>
                                      {isEditable && <th className="pb-2"></th>}
                                    </tr>
                                  </thead>
                                  <tbody>
                                    {c.details.map((d: any) => (
                                      <tr key={d.id} className="border-b border-line">
                                        <td className="py-2 pl-2 text-ink-muted text-xs">{d.description || d.coverageValueString || '—'}</td>
                                        {isEditable ? (
                                          <>
                                            <td className="py-1.5 pr-2">
                                              <NumericInput value={getDetailVal(d.id, 'coverageValue', d.coverageValue)}
                                                onChange={v => setDetailVal(d.id, 'coverageValue', v)}
                                                className="w-full px-2 py-1.5 text-sm border border-line rounded focus:ring-2 focus:ring-primary" />
                                            </td>
                                            <td className="py-1.5 pr-2">
                                              <NumericInput value={getDetailVal(d.id, 'rate', d.rate)}
                                                onChange={v => setDetailVal(d.id, 'rate', v)}
                                                className="w-full px-2 py-1.5 text-sm border border-line rounded focus:ring-2 focus:ring-primary"
                                                hideHint />
                                            </td>
                                            <td className="py-1.5 pr-2">
                                              <span className="text-sm font-medium">
                                                {(() => {
                                                  const cv = parseFloat(getDetailVal(d.id, 'coverageValue', d.coverageValue))
                                                  const r = parseFloat(getDetailVal(d.id, 'rate', d.rate))
                                                  return (!isNaN(cv) && !isNaN(r)) ? fmtCurrency(cv * r / 100) : fmtCurrency(d.calculatedValue || d.proRatePremium)
                                                })()}
                                              </span>
                                            </td>
                                            <td className="py-1.5 pr-1">
                                              <button onClick={async () => {
                                                if (!confirm('Delete this subcoverage?')) return
                                                await fetch(`${apiBase}/policies/${policyId}/coverages/${c.id}/details/${d.id}`, { method: 'DELETE', headers: apiHeaders })
                                                const fresh = await fetchPolicyCoverages(policyId, selectedActionId!); setCovData(fresh)
                                              }} className="text-status-danger-fg hover:text-status-danger-fg text-xs">✕</button>
                                            </td>
                                          </>
                                        ) : (
                                          <>
                                            <td className="py-2">{fmtCurrency(d.coverageValue)}</td>
                                            <td className="py-2">{d.rate ? `${d.rate}%` : '—'}</td>
                                            <td className="py-2 font-medium">{fmtCurrency(d.calculatedValue || d.proRatePremium)}</td>
                                          </>
                                        )}
                                      </tr>
                                    ))}
                                  </tbody>
                                </table>
                                {isEditable && (
                                  <div className="flex justify-end mt-2">
                                    <button onClick={() => saveDetailEdits(c.id, c.details)} disabled={saving}
                                      className="px-4 py-1.5 text-xs font-medium text-white bg-status-success-fg rounded hover:bg-status-success-fg disabled:opacity-50">
                                      {saving ? 'Saving...' : 'Save Changes'}
                                    </button>
                                  </div>
                                )}
                              </div>
                            ) : c.motorTraders?.length > 0 ? (
                              <div className="space-y-3">
                                {c.motorTraders.map((mt: any, mi: number) => (
                                  <div key={mi} className="border border-line rounded p-3 bg-surface-2 space-y-2">
                                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
                                      <div><span className="text-ink-muted">Type of Cover: </span><span className="font-medium">{mt.typeOfCover || '—'}</span></div>
                                      <div><span className="text-ink-muted">Sum Insured: </span><span className="font-medium text-primary">{fmtCurrency(mt.totalCoverageValue)}</span></div>
                                      <div><span className="text-ink-muted">Premium: </span><span className="font-medium text-status-success-fg">{fmtCurrency(mt.totalCalculatedValue)}</span></div>
                                    </div>
                                    {mt.subCoverages?.length > 0 ? (
                                      <div>
                                        <div className="text-xs font-semibold text-ink-muted uppercase mb-1">Sub-Coverages</div>
                                        <table className="w-full text-xs">
                                          <thead className="bg-surface">
                                            <tr className="text-left text-ink-faint border-b border-line">
                                              <th className="py-1 pr-4">Coverage</th>
                                              <th className="py-1 pr-4">Sum Insured</th>
                                              <th className="py-1">Premium</th>
                                            </tr>
                                          </thead>
                                          <tbody>
                                            {mt.subCoverages.map((sc: any, sci: number) => (
                                              <tr key={sci} className="border-b border-line">
                                                <td className="py-1 pr-4">{sc.label}</td>
                                                <td className="py-1 pr-4">{sc.coverageValue ? fmtCurrency(sc.coverageValue) : '—'}</td>
                                                <td className="py-1 font-medium">{sc.calculatedValue ? fmtCurrency(sc.calculatedValue) : '—'}</td>
                                              </tr>
                                            ))}
                                          </tbody>
                                        </table>
                                      </div>
                                    ) : (
                                      <p className="text-xs text-status-warning-fg bg-status-warning-bg border border-status-warning-fg rounded px-3 py-2">
                                        Sub-coverage values appear here once the policy is rated.
                                      </p>
                                    )}
                                    {(mt.excess?.ownDamageMinPercent || mt.excess?.ownDamageMinAmount || mt.excess?.windscreenMinPercent || mt.excess?.windscreenMinAmount) && (
                                      <div>
                                        <div className="text-xs font-semibold text-ink-muted uppercase mb-1">Excess</div>
                                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
                                          {mt.excess?.ownDamageMinPercent && <div><span className="text-ink-muted">Own Damage Min %: </span><span className="font-medium">{mt.excess.ownDamageMinPercent}</span></div>}
                                          {mt.excess?.ownDamageMinAmount && <div><span className="text-ink-muted">Own Damage Min Amt: </span><span className="font-medium">{fmtCurrency(mt.excess.ownDamageMinAmount)}</span></div>}
                                          {mt.excess?.windscreenMinPercent && <div><span className="text-ink-muted">Windscreen Min %: </span><span className="font-medium">{mt.excess.windscreenMinPercent}</span></div>}
                                          {mt.excess?.windscreenMinAmount && <div><span className="text-ink-muted">Windscreen Min Amt: </span><span className="font-medium">{fmtCurrency(mt.excess.windscreenMinAmount)}</span></div>}
                                        </div>
                                      </div>
                                    )}
                                  </div>
                                ))}
                              </div>
                            ) : c.vehicles?.length > 0 ? (
                              <div className="space-y-3">
                                {c.vehicles.map((v: any, vi: number) => {
                                  const subCoverages = [
                                    { label: 'Wreckage Removal', flag: v.wreckageRemoval, premium: v.premiumWreckageRemoval },
                                    { label: 'Window Glass', flag: v.windowGlass, premium: v.premiumWindowGlass },
                                    { label: 'Locks & Keys', flag: v.locksKeys, premium: v.premiumLocksKeys },
                                    { label: 'Parts & Accessories', flag: v.partsAccessories, premium: v.premiumPartsAccessories },
                                    { label: 'Audio Accessories', flag: v.audioAccessories, premium: v.premiumAudioAccessories },
                                    { label: 'Riot & Strike', flag: v.riotStrike, premium: v.premiumRiotStrike },
                                    { label: 'Car Hire (Theft)', flag: v.carHireTheft, premium: v.premiumCarHireTheft },
                                    { label: 'Credit Shortfall', flag: v.creditShortfall, premium: v.premiumCreditShortfall },
                                    { label: 'Insured Driver', flag: v.insuredDriver, premium: v.premiumInsuredDriver },
                                    { label: 'Insured Family', flag: v.insuredFamily, premium: v.premiumInsuredFamily },
                                    { label: 'Medical Expenses', flag: v.medicalExpenses, premium: v.premiumMedicalExpenses },
                                    { label: 'Passenger Liability', flag: v.passengerLiability, premium: v.premiumPassengerLiability },
                                    { label: 'Third Party Liability', flag: v.thirdPartyLiability, premium: v.premiumThirdPartyLiability },
                                    { label: 'Specified Accessories', flag: v.specifiedAccessories, premium: v.premiumSpecifiedAccessories },
                                  ].filter(sc => sc.flag && sc.flag !== '0' && sc.flag !== 'false' && sc.flag !== false)
                                  return (
                                    <div key={vi} className="border border-line rounded p-3 bg-surface-2 space-y-2">
                                      {/* Vehicle header + Edit button */}
                                      <div className="flex items-start justify-between gap-2">
                                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs flex-1">
                                          <div><span className="text-ink-muted">Reg No: </span><span className="font-medium">{v.registrationNo || '—'}</span></div>
                                          <div><span className="text-ink-muted">Make/Model: </span><span className="font-medium">{[v.make, v.model].filter(Boolean).join(' / ') || '—'}</span></div>
                                          <div><span className="text-ink-muted">Type of Cover: </span><span className="font-medium">{motorCoverTypeLabel(v.typeOfCover) || '—'}</span></div>
                                          <div><span className="text-ink-muted">Est. Value: </span><span className="font-medium">{v.estimatedValue ? fmtCurrency(v.estimatedValue) : '—'}</span></div>
                                          <div><span className="text-ink-muted">Engine No: </span><span className="font-medium">{v.engineNumber || '—'}</span></div>
                                          <div><span className="text-ink-muted">Chassis No: </span><span className="font-medium">{v.chassisNumber || '—'}</span></div>
                                          <div><span className="text-ink-muted">Sum Insured: </span><span className="font-medium text-primary">{fmtCurrency(v.coverageValue)}</span></div>
                                          <div><span className="text-ink-muted">Premium: </span><span className="font-medium text-status-success-fg">{fmtCurrency(v.calculatedValue)}</span></div>
                                        </div>
                                        {isEditable && v.id && (
                                          <button
                                            onClick={() => openMotorEdit(c.id, v)}
                                            className="shrink-0 px-2 py-1 text-xs font-medium text-white bg-primary rounded hover:bg-primary"
                                          >✏ Edit</button>
                                        )}
                                      </div>

                                      {/* Sub-coverages */}
                                      {subCoverages.length > 0 && (
                                        <div>
                                          <div className="text-xs font-semibold text-ink-muted uppercase mb-1">Sub-Coverages</div>
                                          <table className="w-full text-xs">
                                            <thead className="bg-surface">
                                              <tr className="text-left text-ink-faint border-b border-line">
                                                <th className="py-1 pr-4">Coverage</th>
                                                <th className="py-1">Premium</th>
                                              </tr>
                                            </thead>
                                            <tbody>
                                              {subCoverages.map(sc => (
                                                <tr key={sc.label} className="border-b border-line">
                                                  <td className="py-1 pr-4">{sc.label}</td>
                                                  <td className="py-1 font-medium">{sc.premium ? fmtCurrency(sc.premium) : '—'}</td>
                                                </tr>
                                              ))}
                                            </tbody>
                                          </table>
                                        </div>
                                      )}

                                      {/* Per-motor specified items */}
                                      {v.specifiedItems?.length > 0 && (
                                        <div>
                                          <div className="text-xs font-semibold text-ink-muted uppercase mb-1">Specified Items</div>
                                          <table className="w-full text-xs">
                                            <thead className="bg-surface">
                                              <tr className="text-left text-ink-faint border-b border-line">
                                                <th className="py-1 pr-4">Item</th>
                                                <th className="py-1 pr-4">Sum Insured</th>
                                                <th className="py-1 pr-4">Rate %</th>
                                                <th className="py-1">Premium</th>
                                              </tr>
                                            </thead>
                                            <tbody>
                                              {v.specifiedItems.map((s: any, si: number) => (
                                                <tr key={si} className="border-b border-line">
                                                  <td className="py-1 pr-4">{s.name}</td>
                                                  <td className="py-1 pr-4">{s.sumInsured ? `P ${Number(s.sumInsured).toLocaleString()}` : '—'}</td>
                                                  <td className="py-1 pr-4">{s.rate ?? '—'}</td>
                                                  <td className="py-1 font-medium">{s.calculatedValue ? `P ${Number(s.calculatedValue).toLocaleString()}` : '—'}</td>
                                                </tr>
                                              ))}
                                            </tbody>
                                          </table>
                                        </div>
                                      )}

                                      {/* Per-motor note */}
                                      {v.note && (
                                        <div className="bg-status-warning-bg border border-status-warning-fg rounded p-2">
                                          <span className="text-xs font-semibold text-status-warning-fg mr-1">Note:</span>
                                          <span className="text-xs text-ink-muted whitespace-pre-wrap">{v.note}</span>
                                        </div>
                                      )}
                                    </div>
                                  )
                                })}
                              </div>
                            ) : c.fidelityData?.length > 0 ? (
                              // Fidelity Guarantee stores its amount + premium in
                              // policy_coverages_data, not the details/motor tables —
                              // render those rows here so the value shows instead of
                              // the "No premium detail recorded yet" fallback.
                              <div>
                                <table className="w-full text-sm">
                                  <thead>
                                    <tr className="text-left text-xs text-ink-muted border-b border-line">
                                      <th className="pb-2 pl-2">Name / Position</th>
                                      <th className="pb-2">Designation</th>
                                      <th className="pb-2">Length of Service</th>
                                      <th className="pb-2">Amount Guaranteed</th>
                                      <th className="pb-2">Premium</th>
                                    </tr>
                                  </thead>
                                  <tbody>
                                    {c.fidelityData.map((f: any) => (
                                      <tr key={f.id} className="border-b border-line">
                                        <td className="py-2 pl-2 text-ink-muted text-xs">
                                          {[f.coverType ? String(f.coverType).replace(/_/g, ' ') : null, f.nameAndPosition].filter(Boolean).join(' : ') || '—'}
                                        </td>
                                        <td className="py-2">{f.designation || '—'}</td>
                                        <td className="py-2">{f.lengthOfService || '—'}</td>
                                        <td className="py-2">{fmtCurrency(f.amountToBeGuaranteed)}</td>
                                        <td className="py-2 font-medium">{fmtCurrency(f.premium)}</td>
                                      </tr>
                                    ))}
                                  </tbody>
                                </table>
                              </div>
                            ) : (
                              <p className="text-xs text-status-warning-fg bg-status-warning-bg border border-status-warning-fg rounded px-3 py-2">
                                No premium detail recorded yet — sum insured and rate will appear here once the policy is rated.
                              </p>
                            )}

                            {/* Extensions */}
                            {c.extensions?.length > 0 && (
                              <div>
                                <h4 className="text-xs font-semibold text-ink-muted uppercase mb-1">Extensions & Perils</h4>
                                <table className="w-full text-sm">
                                  <thead>
                                    <tr className="text-left text-xs text-ink-muted border-b border-line">
                                      <th className="pb-2 pl-2">Extension</th>
                                      <th className="pb-2">Sum Insured</th>
                                      <th className="pb-2">Excess %</th>
                                      <th className="pb-2">Min Excess</th>
                                      <th className="pb-2">Premium</th>
                                      {isEditable && <th className="pb-2"></th>}
                                    </tr>
                                  </thead>
                                  <tbody>
                                    {c.extensions.map((e: any, ei: number) => (
                                      <tr key={ei} className="border-b border-line">
                                        <td className="py-2 pl-2 text-ink-muted text-xs">{e.name || e.code}</td>
                                        {isEditable ? (
                                          <>
                                            <td className="py-1.5 pr-2">
                                              <NumericInput value={e.sumInsured || ''} onChange={() => {}} className="w-full px-2 py-1 text-sm border border-line rounded" />
                                            </td>
                                            <td className="py-1.5 pr-2">
                                              <input type="number" defaultValue={e.excessMin || ''} className="w-full px-2 py-1 text-sm border border-line rounded" placeholder="%" onChange={evt => evt.target.value = evt.target.value.replace(/[^0-9.-]/g, '')} />
                                            </td>
                                            <td className="py-1.5 pr-2">
                                              <input type="number" defaultValue={e.excessMax || ''} className="w-full px-2 py-1 text-sm border border-line rounded" onChange={evt => evt.target.value = evt.target.value.replace(/[^0-9.-]/g, '')} />
                                            </td>
                                            <td className="py-1.5 pr-2">
                                              <NumericInput value={e.calculatedValue || e.proRatePremium || ''} onChange={() => {}} className="w-full px-2 py-1 text-sm border border-line rounded" />
                                            </td>
                                            <td className="py-1.5 pr-1">
                                              <button onClick={async () => {
                                                if (!confirm('Delete this extension?')) return
                                                await fetch(`${apiBase}/policies/${policyId}/coverages/${c.id}/extensions/${e.id}`, { method: 'DELETE', headers: apiHeaders })
                                                const fresh = await fetchPolicyCoverages(policyId, selectedActionId!); setCovData(fresh)
                                              }} className="text-status-danger-fg hover:text-status-danger-fg text-xs">✕</button>
                                            </td>
                                          </>
                                        ) : (
                                          <>
                                            <td className="py-2">{e.sumInsured || '—'}</td>
                                            <td className="py-2">{e.excessMin ? `${e.excessMin}%` : '—'}</td>
                                            <td className="py-2">{e.excessMax ? fmtCurrency(e.excessMax) : '—'}</td>
                                            <td className="py-2 font-medium">{fmtCurrency(e.calculatedValue || e.proRatePremium)}</td>
                                          </>
                                        )}
                                      </tr>
                                    ))}
                                  </tbody>
                                </table>
                              </div>
                            )}

                            {/* Specified Items */}
                            {c.specifiedItems?.length > 0 && (
                              <div>
                                <h4 className="text-xs font-semibold text-ink-muted uppercase mb-1">Specified Items</h4>
                                <table className="w-full text-sm">
                                  <thead>
                                    <tr className="text-left text-xs text-ink-muted border-b border-line">
                                      <th className="pb-2 pl-2">Item</th>
                                      <th className="pb-2">Sum Insured</th>
                                      <th className="pb-2">Rate %</th>
                                      <th className="pb-2">Premium</th>
                                    </tr>
                                  </thead>
                                  <tbody>
                                    {c.specifiedItems.map((s: any, si: number) => (
                                      <tr key={si} className="border-b border-line">
                                        <td className="py-2 pl-2 text-ink-muted text-xs">{s.name}</td>
                                        <td className="py-2 text-xs">{s.sumInsured ? `P ${Number(s.sumInsured).toLocaleString()}` : '—'}</td>
                                        <td className="py-2 text-xs">{s.rate ?? '—'}</td>
                                        <td className="py-2 text-xs font-medium">{s.calculatedValue ? `P ${Number(s.calculatedValue).toLocaleString()}` : '—'}</td>
                                      </tr>
                                    ))}
                                  </tbody>
                                </table>
                              </div>
                            )}

                            {/* Per-coverage total — Sum Insured + Premium summed
                                across all blocks under this coverage. */}
                            {(() => {
                              const t = coverageTotals(c)
                              if (t.premium <= 0 && t.sumInsured <= 0) return null
                              return (
                                <div className="flex flex-wrap justify-end items-center gap-x-6 gap-y-1 border-t border-line pt-2 mt-1 text-sm">
                                  <span className="text-ink-muted">Total Sum Insured: <span className="font-semibold text-primary">{fmtCurrency(t.sumInsured)}</span></span>
                                  <span className="text-ink-muted">Total Premium: <span className="font-semibold text-status-success-fg">{fmtCurrency(t.premium)}</span></span>
                                </div>
                              )
                            })()}

                            {/* Note */}
                            {c.note && (
                              <div className="bg-status-warning-bg border border-status-warning-fg rounded p-3">
                                <h4 className="text-xs font-semibold text-status-warning-fg mb-1">Note</h4>
                                <pre className="text-xs text-ink-muted whitespace-pre-wrap font-sans">{c.note}</pre>
                              </div>
                            )}
                          </div>
                        )}
                      </div>
                    )
                  })}
                </div>
              )}
            </div>
          )
        })
      ) : SPECIALIST_PRODUCT_IDS.includes(productId) ? null : (
        <Card title="Policy Coverages">
          <DataTable
            columns={['coverage', 'type', 'value', 'discount']}
            rows={coverages.map((c: any) => [c.main || '—', c.type || '—', c.value || '—', c.discount || '—'])}
          />
        </Card>
      )}

      {/* Total premium summary for DomCom */}
      {isDomCom && coverages.length > 0 && totalPremium > 0 && (
        <div className="flex justify-end items-center gap-3 bg-status-info-bg border border-primary rounded-lg px-4 py-3">
          <span className="text-sm font-medium text-ink-muted">Total Coverage Premium:</span>
          <span className="text-base font-bold text-primary">
            {fmtPula(totalPremium)}
          </span>
        </div>
      )}

      {/* Specialist / Engineering / Marine product-specific coverage tables */}
      {SPECIALIST_PRODUCT_IDS.includes(productId) && (
        <SpecialistCoveragesSection policyId={policyId} productId={productId} />
      )}
      </fieldset>

      {/* ── Motor vehicle edit modal (note + specified items) ── */}
      {motorModal.open && motorModal.motor && (
        <div className="fixed inset-0 bg-black/50 flex items-start justify-center z-50 p-4 pt-10" onClick={(e) => { if (e.target === e.currentTarget) setMotorModal({ open: false, coverageId: null, motor: null }) }}>
          <div className="bg-surface rounded-xl shadow-2xl w-full max-w-lg max-h-[85vh] overflow-y-auto">
            <div className="flex items-center justify-between px-5 py-3 border-b border-line sticky top-0 bg-surface z-10">
              <h3 className="text-base font-semibold text-ink">
                Edit Vehicle — {motorModal.motor.registrationNo || motorModal.motor.vehicleName || 'Motor'}
              </h3>
              <button onClick={() => setMotorModal({ open: false, coverageId: null, motor: null })}
                className="text-ink-faint hover:text-ink-muted text-xl leading-none">✕</button>
            </div>

            <div className="px-5 py-4 space-y-5">
              {/* Note */}
              <div>
                <label className="block text-sm font-medium text-ink-muted mb-1">Note</label>
                <textarea
                  value={motorNote}
                  onChange={e => setMotorNote(e.target.value)}
                  rows={3}
                  placeholder="Enter note for this vehicle…"
                  className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none"
                />
                <button
                  onClick={saveMotorNote}
                  disabled={motorSaving}
                  className="mt-1 px-3 py-1.5 text-xs font-medium text-white bg-status-success-fg rounded hover:bg-status-success-fg disabled:opacity-50"
                >
                  {motorSaving ? 'Saving…' : 'Save Note'}
                </button>
              </div>

              {/* Per-motor specified items */}
              <div>
                <h4 className="text-sm font-medium text-ink-muted mb-2">Specified Items</h4>
                {motorItems.length > 0 ? (
                  <table className="w-full text-xs mb-3">
                    <thead>
                      <tr className="text-left text-ink-muted border-b border-line">
                        <th className="pb-1.5 pr-3">Item</th>
                        <th className="pb-1.5 pr-3">Sum Insured</th>
                        <th className="pb-1.5 pr-3">Rate %</th>
                        <th className="pb-1.5 pr-3">Premium</th>
                        <th className="pb-1.5"></th>
                      </tr>
                    </thead>
                    <tbody>
                      {motorItems.map((s: any) => (
                        <tr key={s.id} className="border-b border-line">
                          <td className="py-1.5 pr-3">{s.name}</td>
                          <td className="py-1.5 pr-3">{s.sumInsured ? `P ${Number(s.sumInsured).toLocaleString()}` : '—'}</td>
                          <td className="py-1.5 pr-3">{s.rate ?? '—'}</td>
                          <td className="py-1.5 pr-3">{s.calculatedValue ? `P ${Number(s.calculatedValue).toLocaleString()}` : '—'}</td>
                          <td className="py-1.5">
                            <button onClick={() => removeMotorItem(s.id)} className="text-status-danger-fg hover:text-status-danger-fg">✕</button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                ) : (
                  <p className="text-xs text-ink-faint mb-3">No specified items yet.</p>
                )}

                {/* Add new item — master dropdown only, no free-text path
                    (matches legacy ManageCoverages). Rate auto-fills from
                    the master on selection. */}
                <div className="bg-surface-2 border border-line rounded p-3 space-y-2">
                  <div className="flex items-center justify-between">
                    <p className="text-xs font-medium text-ink-muted">Add Item</p>
                    {masterItems.length > 0 && (
                      <button type="button"
                        onClick={() => { if (addingItemOpen) resetAddItem(); else setAddingItemOpen(true) }}
                        className="px-2 py-0.5 text-[11px] font-medium bg-status-warning-bg text-status-warning-fg rounded hover:bg-status-warning-bg">
                        {addingItemOpen ? 'Cancel' : '+ Add Item'}
                      </button>
                    )}
                  </div>
                  {masterItems.length === 0 && (
                    <p className="text-[11px] text-status-warning-fg italic">No specified-item master entries configured for this coverage.</p>
                  )}
                  {addingItemOpen && masterItems.length > 0 && (
                    <>
                      <select
                        value={newItemMasterId}
                        onChange={e => {
                          const id = e.target.value
                          setNewItemMasterId(id)
                          const m = masterItems.find(mi => String(mi.id) === id)
                          if (m && m.rate != null) setNewItemRate(String(m.rate))
                        }}
                        className="w-full px-2 py-1.5 border border-line rounded text-xs focus:ring-1 focus:ring-primary focus:outline-none bg-surface"
                      >
                        <option value="">-- Select item --</option>
                        {masterItems.map(m => (
                          <option key={m.id} value={m.id}>{m.name}{m.rate != null ? ` (default rate ${m.rate}%)` : ''}</option>
                        ))}
                      </select>
                      <div className="flex gap-2">
                        <div className="flex-1">
                          <NumericInput
                            value={newItemSum}
                            onChange={v => setNewItemSum(v)}
                            placeholder="Sum insured"
                            className="w-full px-2 py-1.5 border border-line rounded text-xs focus:ring-1 focus:ring-primary focus:outline-none"
                          />
                        </div>
                        <div className="w-24">
                          <input type="number" value={newItemRate} readOnly
                            title="Rate is managed on the specified-item master"
                            className="w-full px-2 py-1.5 border border-line rounded text-xs bg-surface-2 text-ink-muted"
                            placeholder="Rate %" />
                        </div>
                        <button
                          onClick={addMotorItem}
                          disabled={addingItem || !newItemSum || !newItemMasterId}
                          className="px-3 py-1.5 text-xs font-medium text-white bg-primary rounded hover:bg-primary disabled:opacity-50"
                        >
                          {addingItem ? '…' : '+ Add'}
                        </button>
                      </div>
                    </>
                  )}
                </div>
              </div>
            </div>

            <div className="flex justify-end gap-2 px-5 py-4 border-t border-line bg-surface-2">
              <button
                onClick={() => setMotorModal({ open: false, coverageId: null, motor: null })}
                className="px-4 py-2 text-sm border border-line rounded hover:bg-surface-2"
              >Close</button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

// ─── Specialist Coverages Section (Engineering / Specialist / Marine) ──────

const SPECIALIST_HIDDEN_COLS = new Set(['id', 'policy_id', 'created_at', 'updated_at', 'deleted_at'])

/** Specialist types that are one-per-address: the accordion header button
 *  opens the existing schedule record directly (via recordId) instead of
 *  always starting a blank "+ Add", when one has already been saved. */
const SMART_SCHEDULE_LABELS: Record<string, string> = {
  'bonds': 'Open Bonds Schedule',
  'medical-evacuation': 'Open Medical Evacuation Schedule',
  'commercial-crime': 'Open Commercial Crime Schedule',
}

/** Available coverage types per product, in order */
const SPECIALIST_TYPE_MAP: Record<number, Array<{ slug: string; label: string; hasForm: boolean }>> = {
  16: [
    { slug: 'ear',    label: 'EAR Coverage',         hasForm: true  },
    { slug: 'car',    label: 'CAR Coverage',         hasForm: true  },
    { slug: 'par',    label: 'PAR Coverage',         hasForm: true  },
    { slug: 'machinery-breakdown', label: 'Machinery Breakdown', hasForm: true },
  ],
  18: [
    { slug: 'ear',    label: 'EAR Coverage',         hasForm: true  },
    { slug: 'car',    label: 'CAR Coverage',         hasForm: true  },
    { slug: 'par',    label: 'PAR Coverage',         hasForm: true  },
    { slug: 'machinery-breakdown', label: 'Machinery Breakdown', hasForm: true },
  ],
  17: [
    { slug: 'medical-malpractice',    label: 'Medical Malpractice',     hasForm: true  },
    { slug: 'professional-indemnity', label: 'Professional Indemnity',  hasForm: true  },
    { slug: 'marine-cargo-once-off',  label: 'Marine Cargo Once Off',   hasForm: true  },
    { slug: 'marine-cargo-open',      label: 'Marine Cargo Open',       hasForm: true  },
    { slug: 'marine-directors-officers', label: 'Directors & Officers', hasForm: true  },
  ],
  19: [
    { slug: 'medical-malpractice',    label: 'Medical Malpractice',     hasForm: true  },
    { slug: 'professional-indemnity', label: 'Professional Indemnity',  hasForm: true  },
    { slug: 'marine-cargo-once-off',  label: 'Marine Cargo Once Off',   hasForm: true  },
    { slug: 'marine-cargo-open',      label: 'Marine Cargo Open',       hasForm: true  },
    { slug: 'marine-directors-officers', label: 'Directors & Officers', hasForm: true  },
  ],
  20: [
    { slug: 'medical-malpractice',    label: 'Medical Malpractice',     hasForm: true  },
    { slug: 'professional-indemnity', label: 'Professional Indemnity',  hasForm: true  },
    { slug: 'marine-directors-officers', label: 'Directors & Officers', hasForm: true  },
    { slug: 'environmental-liability', label: 'Environmental Liability', hasForm: true },
  ],
  24: [
    { slug: 'medical-evacuation', label: 'Medical Evacuation', hasForm: true },
    { slug: 'commercial-crime',   label: 'Commercial Crime',   hasForm: true },
  ],
  23: [
    { slug: 'bonds', label: 'Bonds and Guarantees', hasForm: true },
  ],
}

function SpecialistCoveragesSection({ policyId, productId }: { policyId: number; productId: number }) {
  const { data, isLoading, isError, refetch } = usePolicySpecialistCoverages(policyId, true)
  const [expanded, setExpanded] = useState<Record<string, boolean>>({})
  const [deleting, setDeleting] = useState<number | null>(null)
  const toggle = (key: string) => setExpanded(p => ({ ...p, [key]: !p[key] }))

  const typeList = SPECIALIST_TYPE_MAP[productId] ?? []

  // Build a map: label → rows from API response (which only returns non-empty groups)
  const rowsByLabel = new Map<string, Record<string, any>[]>()
  data?.forEach(g => rowsByLabel.set(g.label, g.rows))

  const handleDelete = async (slug: string, recordId: number) => {
    if (!window.confirm('Delete this coverage record? This cannot be undone.')) return
    setDeleting(recordId)
    try {
      await deleteSpecialistCoverage(policyId, slug, recordId)
      await refetch()
    } catch { /* ignore */ } finally {
      setDeleting(null)
    }
  }

  if (isLoading) return <ProgressBar isLoading label="Loading specialist coverages..." className="max-w-xs mx-auto py-4" />
  if (isError) return <EmptyState message="Could not load specialist coverages." />

  if (typeList.length === 0) return null

  return (
    <div className="space-y-3">
      <h3 className="text-sm font-semibold text-ink-muted uppercase tracking-wider border-b border-line pb-2">
        Product-Specific Coverages
      </h3>
      {typeList.map(typeInfo => {
        const key = `sg_${typeInfo.slug}`
        const rows = rowsByLabel.get(typeInfo.label) ?? []
        const visibleCols = rows.length > 0
          ? Object.keys(rows[0]).filter(c => !SPECIALIST_HIDDEN_COLS.has(c))
          : []
        const isOpen = expanded[key] !== false

        return (
          <div key={key} className="border border-line rounded-lg overflow-hidden">
            {/* Group header */}
            <div className="flex items-center justify-between px-4 py-3 bg-status-info-bg border-b border-primary">
              <button
                type="button"
                onClick={() => toggle(key)}
                className="flex items-center gap-2 text-primary font-semibold text-sm hover:text-primary flex-1 text-left"
              >
                <span>{isOpen ? '▼' : '▶'}</span>
                <span>{typeInfo.label}</span>
                <span className="text-xs font-normal text-primary ml-1">({rows.length} record{rows.length !== 1 ? 's' : ''})</span>
              </button>
              {typeInfo.hasForm && SMART_SCHEDULE_LABELS[typeInfo.slug] && (
                // These types are one-per-address: a saved schedule record
                // already exists more often than not, so this button opens
                // it directly (view/edit) instead of always starting a blank
                // create — same screen either way, just pre-loaded with the
                // existing record when there is one.
                // Rendered as an <a>, not a <button>: this section is always
                // embedded read-only under Policy Actions (see ActionsTab's
                // `<CoveragesTab ... readOnly />`), which wraps everything in
                // a disabled <fieldset>. A native <button> there never fires
                // (disabled fieldsets suppress click on descendant form
                // controls); anchors are unaffected, matching the EAR/PAR/CAR
                // links above.
                <a
                  href={
                    rows.length > 0
                      ? `/policies/${policyId}/specialist-coverage/${typeInfo.slug}?recordId=${rows[0].id}`
                      : `/policies/${policyId}/specialist-coverage/${typeInfo.slug}`
                  }
                  className="ml-4 text-xs bg-primary text-white px-3 py-1.5 rounded-md hover:bg-primary transition font-medium whitespace-nowrap"
                >
                  {SMART_SCHEDULE_LABELS[typeInfo.slug]}
                </a>
              )}
              {typeInfo.hasForm && !SMART_SCHEDULE_LABELS[typeInfo.slug] && (
                <a
                  href={`/policies/${policyId}/specialist-coverage/${typeInfo.slug}`}
                  className="ml-4 text-xs bg-primary text-white px-3 py-1.5 rounded-md hover:bg-primary transition font-medium whitespace-nowrap"
                >
                  + Add
                </a>
              )}
            </div>

            {/* Rows table */}
            {isOpen && rows.length > 0 && (
              <DualScrollTable>
                <table className="w-full text-xs border-t border-line">
                  <thead>
                    <tr className="bg-surface-2">
                      {visibleCols.map(col => (
                        <th key={col} className="px-3 py-2 text-left font-medium text-ink-muted capitalize border-b border-line whitespace-nowrap">
                          {col.replace(/_/g, ' ')}
                        </th>
                      ))}
                      {typeInfo.hasForm && (
                        <th className="px-3 py-2 text-left font-medium text-ink-muted border-b border-line whitespace-nowrap">Actions</th>
                      )}
                    </tr>
                  </thead>
                  <tbody className="divide-y">
                    {rows.map((row, ri) => (
                      <tr key={ri} className="hover:bg-surface-2">
                        {visibleCols.map(col => {
                          const val = (row as any)[col]
                          return (
                            <td key={col} className="px-3 py-2 text-ink-muted whitespace-nowrap max-w-xs truncate">
                              {val !== null && val !== undefined && val !== '' ? String(val) : '—'}
                            </td>
                          )
                        })}
                        {typeInfo.hasForm && (
                          <td className="px-3 py-2 whitespace-nowrap">
                            <div className="flex items-center gap-2">
                              <a
                                href={`/policies/${policyId}/specialist-coverage/${typeInfo.slug}?recordId=${row.id}`}
                                className="text-xs text-primary hover:text-primary font-medium"
                              >
                                Edit
                              </a>
                              <button
                                type="button"
                                onClick={() => handleDelete(typeInfo.slug, row.id)}
                                disabled={deleting === row.id}
                                className="text-xs text-status-danger-fg hover:text-status-danger-fg font-medium disabled:opacity-50"
                              >
                                {deleting === row.id ? '…' : 'Delete'}
                              </button>
                            </div>
                          </td>
                        )}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </DualScrollTable>
            )}

            {isOpen && rows.length === 0 && (
              <div className="px-4 py-3 text-sm text-ink-faint italic">
                No records yet.{typeInfo.hasForm ? ' Use "+ Add" to create one.' : ''}
              </div>
            )}
          </div>
        )
      })}
    </div>
  )
}

// ─── Tab: Policy Actions (DomCom state history) ─────────────────

/**
 * Period end to show against a policy action in the "Select Policy Action"
 * card. The ACTION's own effective_to is printed VERBATIM whenever it exists,
 * so this card, the Edit Current Transaction modal and the Vehicles-tab action
 * selector all show the identical range straight out of `policy_actions`.
 *
 * It is deliberately NOT filtered against the action's start any more. A
 * same-day window (ENDORSE 31/07/2026 – 31/07/2026) is real data, and skipping
 * it dropped through to the POLICY expiry — which then printed 11/07/2027 in
 * the dropdown while the edit modal, reading the same action, showed
 * 31/07/2026. Applies to DOM/COM and specialist alike (one shared component).
 *
 * The POLICY expiry stays a FALLBACK only, for an action carrying no usable
 * effective_to at all. It must never LEAD: expiry_date is re-stamped by
 * issuePolicy on EVERY transaction, so ~2,600 DomCom policies hold a wrong
 * expiry and that one policy-level date would print on every row.
 */
function actionPeriodEnd(policyEndDate: string | null | undefined, action: any): string {
  for (const candidate of [action?.effectiveTo, policyEndDate]) {
    if (!candidate) continue
    if (Number.isNaN(new Date(candidate).getTime())) continue
    return new Date(candidate).toLocaleDateString('en-GB')
  }
  return '?'
}

function ActionsTab({ policyId, policyNumber, policyStatus, productId = 0, premiumFreqLabel, policyEndDate }:{ policyId: number; policyNumber?: string; policyStatus?: number; productId?: number; premiumFreqLabel?: string; policyEndDate?: string | null }) {
  const { data, isLoading, refetch } = usePolicyActions(policyId, true)
  const qc = useQueryClient()
  const [acting, setActing] = useState(false)
  // Live status of the queued Refresh Endorsement job (null = not running).
  const [refreshStatus, setRefreshStatus] = useState<{ status: string; message?: string; done?: number; total?: number } | null>(null)
  const [isRated, setIsRated] = useState(false)
  const [showReinstateModal, setShowReinstateModal] = useState(false)

  // Permission check — reads from localStorage (set on login)
  const perms = useMemo(() => {
    try { return JSON.parse(localStorage.getItem('user_permissions') || '[]') as string[] } catch { return [] }
  }, [])
  const hasPerm = (p: string) => perms.length === 0 || perms.includes(p) // if no perms stored, allow all (admin fallback)
  // Super Admin gate (role names stored on login) — used to restrict the Renew Policy button
  const isSuperAdmin = useMemo(() => {
    try {
      const roles = JSON.parse(localStorage.getItem('user_roles') || '[]') as string[]
      return roles.some(r => { const s = String(r).toLowerCase(); return s.includes('super') && s.includes('admin') })
    } catch { return false }
  }, [])
  // Admin / Super Admin / Manager gate — used to restrict the Delete
  // REINSTATE/REISSUE quote button to those roles only (not all staff).
  const isAdminOrManager = useMemo(() => {
    try {
      const roles = JSON.parse(localStorage.getItem('user_roles') || '[]') as string[]
      return roles.some(r => { const s = String(r).toLowerCase(); return s.includes('admin') || s.includes('manager') })
    } catch { return false }
  }, [])
  // Admin / Super Admin gate (NOT Manager) — used to restrict the
  // Policy Doc (Date Range) button to those roles only.
  const isAdminOrSuperAdmin = useMemo(() => {
    try {
      const roles = JSON.parse(localStorage.getItem('user_roles') || '[]') as string[]
      return roles.some(r => String(r).toLowerCase().includes('admin'))
    } catch { return false }
  }, [])
  // Change Summary is a read-only diagnostic available to everyone — no role
  // gate. The backend gate is likewise open (see EndorseChangeSummary::load).
  const [pdfNotif, setPdfNotif] = useState<{ status: 'generating' | 'completed' | 'failed'; message: string; url?: string } | null>(null)

  // ── Policy Doc (Date Range) — standalone, additive. Generates one Policy
  // Document per action whose term start falls in [from, to], via the SAME
  // async store-per-action pipeline as the single "Policy Doc" button, so each
  // doc lands in the Documents tab. Runs in the background; the button stays
  // disabled until every queued job finishes. Nothing is downloaded/zipped.
  const [showDocRangeModal, setShowDocRangeModal] = useState(false)
  // ── Set Frequency (Super Admin only) — corrects one action's frequency stamp,
  // optionally writing policies.premium_freq too. See updateActionFrequency.
  const [showFreqModal, setShowFreqModal] = useState(false)
  const [freqValue, setFreqValue] = useState<number>(0)
  const [freqApplyToPolicy, setFreqApplyToPolicy] = useState(false)
  const [freqSaving, setFreqSaving] = useState(false)
  const [freqError, setFreqError] = useState<string | null>(null)
  const [docRangeFrom, setDocRangeFrom] = useState('')
  const [docRangeTo, setDocRangeTo] = useState('')
  const [docRangeGenerating, setDocRangeGenerating] = useState(false)

  // ── V2 Quote Sheet — cross-user-aware live status (server-side, polled).
  //
  // Drives the banner above the action buttons AND the V2 Quote Sheet /
  // Last V2 Quote button states. The shape mirrors backend
  // PolicyCreateController::v2QuoteLatestForPolicy() response.
  //
  // Why server-side, not local-only state: a second underwriter opening the
  // same policy must see "Sonali is generating this" instead of independently
  // kicking off a duplicate job. Polling /policies/{id}/v2-quote/latest every
  // 5s while the page is visible (paused via Page Visibility API) gives all
  // viewers consistent state with ~5s cross-user lag.
  type V2QuoteCreator = {
    id: number
    name: string
    email: string | null
    is_current_user: boolean
  }
  type V2QuoteJob = {
    id: number
    status: 'queued' | 'queued_long' | 'processing' | 'completed' | 'failed' | 'cancelled' | string
    progress: number
    message: string | null
    policy_id: number
    policy_number: string | null   // canonical from API (e.g., COMG2026214943/01)
    action_id: number | null
    document_title: string | null
    file_name: string | null
    created_at: string
    updated_at: string
    heartbeat_at: string | null
    elapsed_seconds: number        // frozen at (updated_at - created_at) on terminal states
    generation_duration_seconds: number | null  // only set on terminal states
    is_in_flight: boolean
    is_stale: boolean
    created_by: V2QuoteCreator | null
    download_url: string | null
  }
  const [v2QuoteJob, setV2QuoteJob] = useState<V2QuoteJob | null>(null)
  // Previous completed job for the same (policy, action, document) — shown
  // as a "Download last version" link while a new generation is in-flight so
  // the user isn't stranded without an artifact during the regenerate window.
  const [v2QuotePrevious, setV2QuotePrevious] = useState<V2QuoteJob | null>(null)
  // Newest COMPLETED, still-downloadable job for the action currently on
  // screen (backend: action_latest_completed). This is what the Download
  // Quote Sheet button reads.
  //
  // Why it exists: `v2QuoteJob` is the newest job for the POLICY, and the
  // banner that owns it self-dismisses 120s after completion; while
  // `selected.lastV2QuoteUrl` is only computed when the page payload loads
  // and is never refetched after a generation. So the download link used to
  // blink out a couple of minutes after generating (and never appeared at
  // all when the job was stamped against a different action, e.g. the
  // motor-data fallback in generateV2QuoteSheet) until a full page reload.
  // This one is action-scoped, quote-sheet-only, refreshed every 5s, and
  // never auto-dismissed.
  const [v2QuoteActionFile, setV2QuoteActionFile] = useState<V2QuoteJob | null>(null)
  // Canonical policy_number from the API — avoids guessing at data-path keys
  // on the page state. Survives across action switches because it's a
  // property of the policy, not the action.
  const [v2QuotePolicyNumber, setV2QuotePolicyNumber] = useState<string | null>(null)
  // Regenerate-warning hint from /v2-quote/latest. When non-null AND user
  // clicks Generate Quote Sheet, we show a confirm modal instead of firing
  // a new job immediately. Soft-gate, never disables the button.
  type RegenerateWarning = {
    should_warn: boolean
    last_quote_id: number
    last_quote_at: string
    last_quote_by_name: string
    last_quote_by_id: number | null
    minutes_ago: number
    policy_changed_since: boolean
  }
  const [v2RegenerateWarning, setV2RegenerateWarning] = useState<RegenerateWarning | null>(null)
  const [showRegenerateConfirm, setShowRegenerateConfirm] = useState(false)
  // Split-button "Download Quote Sheet" dropdown open state.
  const [downloadMenuOpen, setDownloadMenuOpen] = useState(false)
  // Toast queue — tiny inline replacement for a library. Each toast has
  // an id, message, variant ('success' | 'info' | 'error'), and auto-dismisses
  // after 3.5s. Click ✕ to dismiss immediately.
  type Toast = { id: number; message: string; variant: 'success' | 'info' | 'error' }
  const [toasts, setToasts] = useState<Toast[]>([])
  const toastIdRef = useRef(0)
  const buildQuoteFilename = (): string => buildDocFilename(v2QuotePolicyNumber, 'Quote', { policyId })
  const pushToast = (message: string, variant: 'success' | 'info' | 'error' = 'info') => {
    const id = ++toastIdRef.current
    setToasts(t => [...t, { id, message, variant }])
    setTimeout(() => setToasts(t => t.filter(x => x.id !== id)), 3500)
  }
  // Bridges the regenerate-confirm modal (rendered outside the V2 Quote
  // Sheet button's IIFE) to the in-IIFE fireGenerate closure. Set on every
  // render of the button so the modal always invokes the freshest binding.
  const fireGenerateRef = useRef<(() => Promise<void>) | null>(null)
  // Local UI flags — kept separate from server-state so download progress
  // doesn't bleed across users polling the same job. Per-action (in-flight
  // request) flags are scoped to which download surface is firing, so a
  // click on the persistent split-button doesn't disable the banner's
  // previous-version downloader and vice versa.
  const [v2QuotePreviousDownloading, setV2QuotePreviousDownloading] = useState(false)
  const [lastQuoteDownloading, setLastQuoteDownloading] = useState(false)

  // ── Selected action (defaults to most-recent = data.current)
  const [selectedActionId, setSelectedActionId] = useState<number | null>(null)

  // ── Clone Policy modal
  const [showCloneModal, setShowCloneModal] = useState(false)
  const [cloning, setCloning] = useState(false)
  const [cloneError, setCloneError] = useState('')
  const [cloneResult, setCloneResult] = useState<any>(null)
  const [cloneOpts, setCloneOpts] = useState({ term_start_date: new Date().toISOString().split('T')[0], expiry_date: '', skip_motor: false })

  // ── New Transaction modal
  const [showNewTxn, setShowNewTxn] = useState(false)
  const [allowedTxnTypes, setAllowedTxnTypes] = useState<{ id: string; name: string }[]>([])
  const [txnSubTypes, setTxnSubTypes] = useState<{ id: string; name: string }[]>([])
  const [txnForm, setTxnForm] = useState({
    transaction_type: '', transaction_reason: '',
    effective_from: '', effective_to: '',
    transaction_date: new Date().toISOString().split('T')[0],
    note: '',
  })

  // ── Change Summary modal (read-only Endorse/Cancel diagnostic)
  type CsRow = { bucket: string; line_id: number; coverage_id: number; name: string; detail: string; change_type: string; what_changed: string; calc_value: number; delta: number; pro_rata: number }
  const [showChangeSummary, setShowChangeSummary] = useState(false)
  const [csLoading, setCsLoading] = useState(false)
  const [csRows, setCsRows] = useState<CsRow[]>([])
  const [csMath, setCsMath] = useState<Record<string, any>>({})
  const [csError, setCsError] = useState('')

  // ── Edit Transaction modal
  const [showEditTxn, setShowEditTxn] = useState(false)
  const [editTxnForm, setEditTxnForm] = useState({
    transaction_type: '', transaction_reason: '',
    effective_from: '', effective_to: '',
    transaction_date: '', note: '',
  })
  const [editTxnSubTypes, setEditTxnSubTypes] = useState<{ id: string; name: string }[]>([])

  // ── Other modals (existing)
  const [showReinstate, setShowReinstate] = useState(false)
  const [reinstateInfo, setReinstateInfo] = useState<any>(null)
  const [reinstateLoading, setReinstateLoading] = useState(false)
  const [reinstateForm, setReinstateForm] = useState({ paymentMethod: 'Cash', paymentFreq: '1', paymentDate: '', billingDay: '' })
  // Inline banner for the Rate button — `alert()` was hitting browser popup
  // blockers for some users, leaving the button looking dead.
  const [rateResult, setRateResult] = useState<null | {
    kind: 'ok' | 'err'
    annualPremium?: number
    proRatePremium?: number
    message?: string
  }>(null)
  const [showEndorse, setShowEndorse] = useState(false)
  // Endorse matches legacy graphiteBWV8 AddTransaction — `transaction_reason`
  // is a sub-type code from tb_prtransubtypes (ADDCOVG / DELCOVG / RATECHG / ...),
  // NOT a free-text field. The `note` is a separate plain-text column.
  const [endorseForm, setEndorseForm] = useState({ effective_from: '', effective_to: '', transaction_reason: '', note: '' })
  const [endorseSubTypes, setEndorseSubTypes] = useState<Array<{ id: string; name: string }>>([])
  // ── Renew modal
  const [showRenew, setShowRenew] = useState(false)
  const [renewForm, setRenewForm] = useState({ effective_from: '', effective_to: '', transaction_date: '', note: '' })

  // NOTE: do NOT early-return on isLoading here. The hooks below — useState(gates)
  // and the useEffects — must run on EVERY render, or React throws #310
  // ("rendered fewer hooks than expected") when isLoading flips. The loading
  // guard is placed after the last hook instead. The derived values below use
  // optional chaining, so they are safe while `data` is still undefined.
  const STATUS_CLS: Record<string, string> = {
    ISSUED: 'bg-status-success-bg text-status-success-fg', QUOTE: 'bg-status-info-bg text-primary',
    IN_APPROVAL: 'bg-status-warning-bg text-status-warning-fg', APPROVED: 'bg-status-success-bg text-status-success-fg',
    REJECTED: 'bg-status-danger-bg text-status-danger-fg', LAPSED: 'bg-surface-2 text-ink-muted',
    NTU: 'bg-surface-2 text-ink-muted',
  }

  const allActions: any[] = data?.history ?? (data?.current ? [data.current] : [])
  // Most recent is at end of history array — default to that
  const defaultActionId = data?.current?.id ?? allActions[allActions.length - 1]?.id ?? null
  const activeId = selectedActionId ?? defaultActionId
  const selected: any = allActions.find((a) => a.id === activeId) ?? data?.current ?? null

  const status = selected?.status || ''
  const txType = selected?.transactionType || ''
  // Frequency is a per-TRANSACTION choice (policy_actions.current_frequency_id),
  // so the label here follows the action selected above, not the policy column.
  // Backend already resolves the carry-forward for unstamped rows and falls back
  // to policies.premium_freq; `premiumFreqLabel` remains the last-resort default.
  const actionFreqLabel: string | undefined = selected?.frequencyLabel
    ?? data?.policyFrequencyLabel
    ?? premiumFreqLabel
  // Only flag a divergence when THIS action carries its own stamp; an inherited
  // value is by definition the same as the policy-level one.
  const actionFreqIsOwn = Number(selected?.currentFrequencyId ?? 0) > 0
  const policyFreqLabel: string | undefined = data?.policyFrequencyLabel ?? premiumFreqLabel

  // Open the Set Frequency modal pre-filled with what this action currently has.
  const openFreqModal = () => {
    setFreqValue(Number(selected?.frequencyId ?? data?.policyFrequencyId ?? 0) || 0)
    setFreqApplyToPolicy(false)
    setFreqError(null)
    setShowFreqModal(true)
  }

  const saveActionFrequency = async () => {
    if (!activeId || !freqValue) return
    setFreqSaving(true)
    setFreqError(null)
    try {
      await updateActionFrequency(policyId, activeId, freqValue, freqApplyToPolicy)
      setShowFreqModal(false)
      // The policy header reads premiumFreqLabel from the policy query, so
      // invalidate that too when the policy column was written.
      await refetch()
      if (freqApplyToPolicy) qc.invalidateQueries({ queryKey: ['policy', policyId] })
    } catch (e: any) {
      setFreqError(e?.response?.data?.error ?? e?.response?.data?.message ?? 'Could not update the frequency.')
    } finally {
      setFreqSaving(false)
    }
  }

  const token = localStorage.getItem('sanctum_token')
  const headers = { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json', Accept: 'application/json' }
  const apiBase = `${(import.meta as any).env.VITE_API_URL}/api/v1`

  // ── Validation gates preview ──────────────────────────────────────────
  // Pulls the 6-action permission map from the engine for THIS policy +
  // action so we can grey out buttons + show the operator's denial message
  // BEFORE the user clicks and gets a 403. Backend endpoint is read-only
  // and fail-safe — if it errors, gates stay permissive and the existing
  // server-side middleware still has the final say.
  type GateMap = { canRate: boolean; canPrintQuote: boolean; canPrintApp: boolean; canBindApp: boolean; canSubmitUnbound: boolean; canIssue: boolean }
  type BlockingRule = { ruleCode: string; message: string; blocks: string[] }
  const [gates, setGates] = useState<{ allowed: GateMap; blockingRules: BlockingRule[] } | null>(null)
  useEffect(() => {
    if (!policyId || !activeId) return
    let cancelled = false
    fetch(`${apiBase}/policy-validation/preview/${policyId}?action_id=${activeId}`, { headers })
      .then((r) => (r.ok ? r.json() : null))
      .then((d) => {
        if (cancelled || !d?.data?.allowed) return
        // Backend returns snake_case blocking_rules; normalise to camelCase
        // so the front-end keys match the rest of the codebase.
        const raw = (d.data.blocking_rules ?? []) as Array<{ ruleCode?: string; rule_code?: string; message: string; blocks: string[] }>
        const normalised: BlockingRule[] = raw.map((r) => ({
          ruleCode: r.ruleCode ?? r.rule_code ?? '',
          message:  r.message ?? '',
          blocks:   r.blocks ?? [],
        }))
        setGates({ allowed: d.data.allowed, blockingRules: normalised })
      })
      .catch(() => { /* fail-safe: leave gates null = permissive */ })
    return () => { cancelled = true }
  }, [policyId, activeId])

  // Reset per-action UI overlays whenever the operator switches the Select
  // Policy Action dropdown to a different action_id. Without this, the
  // Rating-complete banner, the "PDF ready" toast, and the in-session
  // isRated flag all leak across switches — operator sees previous
  // action's premium/pro-rata under the freshly-selected action.
  useEffect(() => {
    setRateResult(null)
    setPdfNotif(null)
    setIsRated(false)
  }, [activeId])

  // V2 Quote Sheet — cross-user-aware polling.
  //
  // Hits the per-policy /v2-quote/latest endpoint every 5s while the tab is
  // visible. Pauses entirely when the tab is hidden (Page Visibility API)
  // so a backgrounded tab doesn't burn API quota / DB load. Clears state
  // when policy changes so the previous policy's banner doesn't leak.
  //
  // Cost analysis: 1 SELECT per 5s while visible, single composite-indexed
  // lookup on (policy_id, created_at DESC). At 20 concurrent UW users with
  // visible tabs that's ~4 req/s, negligible against master.
  useEffect(() => {
    if (!policyId) { setV2QuoteJob(null); setV2QuotePrevious(null); setV2QuoteActionFile(null); setV2QuotePolicyNumber(null); setV2RegenerateWarning(null); return }
    let cancelled = false
    const pollOnce = async () => {
      if (document.hidden) return
      try {
        const t = localStorage.getItem('sanctum_token') || ''
        // Scope the lookup to the action on screen so the response carries
        // action_latest_completed for THIS transaction's quote sheet. Omitted
        // while the payload is still loading (activeId null) — the next tick
        // picks it up.
        const scope = activeId ? `?action_id=${activeId}` : ''
        const r = await fetch(`${apiBase}/policies/${policyId}/v2-quote/latest${scope}`, {
          headers: { Authorization: `Bearer ${t}`, Accept: 'application/json' },
        })
        if (!r.ok) return // 404 / 401 / 500 — swallow; next tick retries
        const d = await r.json()
        if (!cancelled) {
          setV2QuoteJob(d.job ?? null)
          setV2QuotePrevious(d.previous_completed ?? null)
          setV2QuoteActionFile(d.action_latest_completed ?? null)
          setV2QuotePolicyNumber(d.policy_number ?? null)
          setV2RegenerateWarning(d.regenerate_warning ?? null)
        }
      } catch { /* network blip — next tick retries */ }
    }
    // Reset local download flags when policy changes so per-action flags
    // don't bleed from a previous policy's in-flight requests.
    setV2QuotePreviousDownloading(false)
    setLastQuoteDownloading(false)
    pollOnce()
    const interval = setInterval(pollOnce, 5000)
    // Also poll immediately on tab-focus so the user sees fresh state when
    // they return to the tab after working elsewhere.
    const onVisible = () => { if (!document.hidden) pollOnce() }
    document.addEventListener('visibilitychange', onVisible)
    return () => {
      cancelled = true
      clearInterval(interval)
      document.removeEventListener('visibilitychange', onVisible)
    }
    // activeId is a dependency: the action-scoped download lookup must follow
    // the action dropdown, otherwise switching transactions leaves the
    // Download button pointing at the previous action's PDF.
  }, [policyId, apiBase, activeId])

  // Auto-dismiss the COMPLETED banner 120s after the job's updated_at.
  // Uses the server timestamp (not a local timer) so all viewers fade out
  // at the same moment — keeps cross-user consistency. In-flight + failed
  // + stale banners stay pinned until manual dismiss.
  useEffect(() => {
    if (!v2QuoteJob || v2QuoteJob.status !== 'completed') return
    const completedAt = new Date(v2QuoteJob.updated_at).getTime()
    const elapsed = Date.now() - completedAt
    const remaining = (120 * 1000) - elapsed
    if (remaining <= 0) {
      setV2QuoteJob(null)
      return
    }
    const t = setTimeout(() => setV2QuoteJob(null), remaining)
    return () => clearTimeout(t)
  }, [v2QuoteJob?.id, v2QuoteJob?.status, v2QuoteJob?.updated_at])

  // All hooks are declared above — safe to bail out for the loading state now.
  if (isLoading) return <ProgressBar isLoading label="Loading actions" className="max-w-xs mx-auto py-8" />

  // Returns the operator's error message for a blocked action, or null when
  // the action is allowed (or the preview hasn't loaded yet — fail-safe).
  function blockerFor(actionKey: keyof GateMap): string | null {
    if (!gates) return null
    if (gates.allowed?.[actionKey] !== false) return null
    const r = gates.blockingRules.find((br) => br.blocks?.includes(actionKey))
    return r?.message || 'You are not authorised to perform this action. Please see your manager for assistance.'
  }

  async function doAction(endpoint: string, body?: any, method = 'POST'): Promise<any> {
    setActing(true)
    try {
      // Always include action_id so backend workflow endpoints target the
      // action the user is *viewing*, not the latest-by-id row. Legacy
      // graphiteBWV8 scopes Submit/Approve/Issue to `dataShowForActionId`.
      const payload = { action_id: selected?.id, ...(body ?? {}) }
      const resp = await fetch(`${apiBase}/policies/${policyId}/${endpoint}`, {
        method, headers, body: JSON.stringify(payload),
      })
      const d = await resp.json()
      if (!resp.ok) { alert(d.message || d.error || 'Action failed'); setActing(false); return null }
      refetch()
      setActing(false)
      return d
    } catch (e: any) { alert(e.message); setActing(false); return null }
  }

  /**
   * Poll the queued "Refresh Endorsement" job until it finishes.
   *
   * refreshEndorse() dispatches to the queue and returns 202 immediately, so
   * the browser used to just show "Refresh started…" and never told the user
   * whether it actually completed. This polls GET refresh-endorse/status
   * (written by RefreshEndorseJob) and surfaces progress + a final result.
   */
  async function pollRefreshStatus() {
    setRefreshStatus({ status: 'queued', message: 'Refresh queued…' })
    const started = Date.now()
    const deadline = started + 15 * 60 * 1000 // give big policies up to 15 min
    // The job's first act in handle() is to write status:'running'. The backend
    // spawns its own detached worker per click, so that normally lands within
    // seconds. If that spawn failed, the status poll below is itself the retry:
    // the endpoint re-kicks a stalled worker (~45s apart, 3 attempts) and parks
    // the run as 'failed' by ~135s. So allow ~2.5 min before giving up — long
    // enough to see that verdict, where the old 25s window declared failure
    // while the first retry was still pending.
    const noWorkerAfter = started + 150 * 1000
    while (Date.now() < deadline) {
      await new Promise((r) => setTimeout(r, 3000))
      let d: any
      try {
        const resp = await fetch(`${apiBase}/policies/${policyId}/refresh-endorse/status`, { headers })
        d = await resp.json()
      } catch {
        continue // transient network blip — keep polling
      }
      if (!d?.status) continue
      setRefreshStatus({ status: d.status, message: d.message, done: d.done, total: d.total })
      if (d.status === 'completed') {
        alert(d.message || 'Refresh complete.')
        setRefreshStatus(null)
        refetch()
        return
      }
      if (d.status === 'failed') {
        alert(d.message || 'Refresh failed.')
        setRefreshStatus(null)
        return
      }
      // Still queued / idle well past the grace window → the per-click worker
      // AND every poll-driven retry failed to start it. That is an environment
      // fault, not something the operator can fix by retrying differently.
      if ((d.status === 'queued' || d.status === 'idle') && Date.now() > noWorkerAfter) {
        alert('The refresh has not started after 2 minutes. Nothing was changed on the policy — please retry once, and if it stalls again send this policy number to IT (background refresh worker is not starting).')
        setRefreshStatus(null)
        return
      }
    }
    // Fell through the deadline without a terminal state.
    alert('Refresh is taking longer than expected. It may still be running — reload the page in a few minutes to check.')
    setRefreshStatus(null)
  }

  /** Load allowed transaction types for the New Transaction modal */
  async function openNewTxnModal() {
    try {
      const r = await fetch(`${apiBase}/lookups/transaction-types?current_type=${encodeURIComponent(txType || 'NEWBUSINESS')}&policy_id=${policyId}&action_id=${selected?.id ?? ''}`, { headers })
      const d = await r.json()
      setAllowedTxnTypes(d.data ?? [])
    } catch { setAllowedTxnTypes([]) }
    setTxnForm({
      transaction_type: '', transaction_reason: '',
      effective_from: selected?.effectiveFrom ?? '',
      effective_to: selected?.effectiveTo ?? '',
      transaction_date: new Date().toISOString().split('T')[0],
      note: '',
    })
    setTxnSubTypes([])
    setShowNewTxn(true)
  }

  /** When transaction type is picked in New Txn modal — load sub-types */
  async function onNewTxnTypeChange(type: string) {
    setTxnForm(p => ({ ...p, transaction_type: type, transaction_reason: '' }))
    if (!type) { setTxnSubTypes([]); return }
    try {
      const r = await fetch(`${apiBase}/lookups/transaction-subtypes/${encodeURIComponent(type)}`, { headers })
      const d = await r.json()
      setTxnSubTypes(d.data ?? [])
    } catch { setTxnSubTypes([]) }
  }

  async function handleNewTxn() {
    if (!txnForm.transaction_type || !txnForm.effective_from || !txnForm.effective_to || !txnForm.transaction_date) {
      alert('Fill all required fields (Type, Effective From, Effective To, Transaction Date)'); return
    }
    // ISO yyyy-mm-dd compares lexicographically — same as date order.
    // Same-day transactions are allowed (from == to), only from > to is invalid.
    if (txnForm.effective_from > txnForm.effective_to) {
      alert('Effective From date cannot be after Effective To date.'); return
    }
    setActing(true)
    const beforeIds = new Set(allActions.map((a: any) => a.id))
    try {
      const payload = { action_id: selected?.id, ...txnForm }
      const resp = await fetch(`${apiBase}/policies/${policyId}/new-transaction`, {
        method: 'POST', headers, body: JSON.stringify(payload),
      })
      const d = await resp.json().catch(() => ({}))
      if (!resp.ok) { alert(d?.message || d?.error || 'Action failed'); setActing(false); return }
      setShowNewTxn(false)
      setTxnForm({ transaction_type: '', transaction_reason: '', effective_from: '', effective_to: '', transaction_date: '', note: '' })
      setTxnSubTypes([])
      await qc.invalidateQueries({ queryKey: ['policy', policyId, 'actions'] })
      await refetch()
    } catch (e: any) {
      // Network / gateway timeout — large policies can take >60s for the
      // backend to finish replication, by which point the gateway closes
      // the connection even though the new action row IS persisted.
      // Refetch the actions list and check if a new row appeared; if so,
      // treat as success.
      try {
        await qc.invalidateQueries({ queryKey: ['policy', policyId, 'actions'] })
        const refreshed: any = await refetch()
        const after = refreshed?.data?.history ?? []
        const created = after.find((a: any) => !beforeIds.has(a.id))
        if (created) {
          setShowNewTxn(false)
          setTxnForm({ transaction_type: '', transaction_reason: '', effective_from: '', effective_to: '', transaction_date: '', note: '' })
          setTxnSubTypes([])
          setSelectedActionId(created.id)
        } else {
          alert(e?.message || 'Network error — no new transaction was created. Please retry.')
        }
      } catch {
        alert(e?.message || 'Network error')
      }
    }
    setActing(false)
  }

  /** Open Edit Transaction modal (pre-populated with selected action) */
  async function openEditTxnModal() {
    // <input type="date"> only accepts a bare YYYY-MM-DD. Some actions come
    // back with a datetime ("2026-09-03 00:00:00" / ISO) — feeding that in
    // leaves the field BLANK, which then submits as null and the date edit
    // looks like it did nothing. Trim to the date part first.
    const dateOnly = (v: any) => (v ? String(v).slice(0, 10) : '')
    setEditTxnForm({
      transaction_type:   selected?.transactionType ?? '',
      transaction_reason: selected?.transactionReason ?? '',
      effective_from:     dateOnly(selected?.effectiveFrom),
      effective_to:       dateOnly(selected?.effectiveTo),
      transaction_date:   dateOnly(selected?.transactionDate),
      note:               selected?.note ?? '',
    })
    // Load sub-types for current transaction type
    await loadEditTxnSubTypes(selected?.transactionType ?? '')
    setShowEditTxn(true)
  }

  /** Load transaction sub-types for a given transaction type (Edit modal).
   *  Extracted so the editable Transaction Type dropdown can refresh the
   *  reason list when the type is changed on a QUOTE action. */
  async function loadEditTxnSubTypes(type: string) {
    if (!type) { setEditTxnSubTypes([]); return }
    try {
      const r = await fetch(`${apiBase}/lookups/transaction-subtypes/${encodeURIComponent(type)}`, { headers })
      const d = await r.json()
      setEditTxnSubTypes(d.data ?? [])
    } catch { setEditTxnSubTypes([]) }
  }

  async function handleEditTxn() {
    if (!selected?.id) return
    // Type changes are allowed on every type and on ISSUED actions too, so the
    // only thing left to acknowledge is the money consequence: an issued action
    // is NOT re-rated, so the premium keeps the value it was issued at. The
    // change is no longer one-way — a NEWBUSINESS / RENEW action can be edited
    // again afterwards.
    const newType = editTxnForm.transaction_type
    const typeChanged = !!newType && newType !== selected.transactionType
    if (typeChanged) {
      const lines = [`Change transaction type ${selected.transactionType} → ${newType}?`]
      if (selected.status && selected.status !== 'QUOTE') {
        lines.push(`This action is ${selected.status} — the premium and any issued invoice will NOT be recalculated.`)
      }
      if (!confirm(lines.join('\n\n'))) return
    }
    setActing(true)
    try {
      const resp = await fetch(`${apiBase}/policies/${policyId}/actions/${selected.id}`, {
        method: 'PUT', headers,
        body: JSON.stringify(editTxnForm),
      })
      const d = await resp.json()
      if (!resp.ok) { alert(d.message || d.error || 'Edit failed'); setActing(false); return }
      // The PUT returns the saved policy_actions row, read back on the same
      // request (connection is sticky, so that read is authoritative). The
      // refetch below is a NEW request and lands on the read replica, which can
      // be a few ms behind the master — patch the cache with the saved row first
      // so the action never flashes its pre-edit dates back and read as
      // "the edit didn't save". Keys mirror the camelCase actions payload.
      const saved = d?.data
      if (saved?.id) {
        qc.setQueryData(['policy', policyId, 'actions'], (prev: any) => {
          if (!prev) return prev
          const patch = (a: any) => (a && a.id === saved.id ? {
            ...a,
            transactionType:   saved.transaction_type   ?? a.transactionType,
            transactionReason: saved.transaction_reason ?? a.transactionReason,
            effectiveFrom:     saved.effective_from     ?? a.effectiveFrom,
            effectiveTo:       saved.effective_to       ?? a.effectiveTo,
            transactionDate:   saved.transaction_date   ?? a.transactionDate,
            note:              saved.note              ?? a.note,
          } : a)
          return {
            ...prev,
            history: Array.isArray(prev.history) ? prev.history.map(patch) : prev.history,
            current: patch(prev.current),
          }
        })
      }
      setShowEditTxn(false)
      refetch()
    } catch (e: any) { alert(e.message) }
    setActing(false)
  }



  async function generatePolicyDoc() {
    setActing(true)
    // Always re-read token at click time (component-scope `headers` may
    // hold a stale token from a previous render — same fix as V2 Quote).
    const freshHeaders = (): Record<string, string> => {
      const t = localStorage.getItem('sanctum_token') || ''
      return { Authorization: `Bearer ${t}`, 'Content-Type': 'application/json', Accept: 'application/json' }
    }
    try {
      // Always send action_id so the backend renders the action the operator
      // is *viewing* — without this, generateV2QuoteSheet (DomCom path) falls
      // back to PolicyAction::orderBy('id','desc')->first() and the Policy
      // Doc reflects the latest action instead of the one in the dropdown.
      const resp = await fetch(`${apiBase}/policies/${policyId}/generate-policy-document`, {
        method: 'POST',
        headers: freshHeaders(),
        body: JSON.stringify({ action_id: activeId }),
      })
      const contentType = resp.headers.get('content-type') || ''
      if (contentType.includes('pdf') || contentType.includes('octet-stream')) {
        const blob = await resp.blob()
        window.open(URL.createObjectURL(blob), '_blank')
      } else {
        const d = await resp.json()
        if (!resp.ok) { alert(d.error || d.message || 'Failed'); setActing(false); return }
        if (d.job_id) {
          // DomCom (7/8): backend now hands off to the V2 Quote async pipeline
          // with document_title='POLICY DOCUMENT'. Poll for completion the
          // same way the V2 Quote button does — avoids the 60s gateway
          // timeout on policies with hundreds of coverages.
          const bigBadge = d.big ? ` — big policy (${d.coverage_count} cov / ${d.vehicle_count} veh)` : ''
          setPdfNotif({ status: 'generating', message: `Generating Policy Document${bigBadge}. ${d.message || ''}` })
          const poll = setInterval(async () => {
            try {
              const sr = await fetch(`${apiBase}/policies/${policyId}/quote-pdf-status/${d.job_id}`, { headers: freshHeaders() })
              const sd = await sr.json()
              if (sd.status === 'completed') {
                clearInterval(poll)
                const downloadUrl = `${apiBase}/policies/${policyId}/download-quote-pdf/${d.job_id}`
                setPdfNotif({ status: 'completed', message: 'Policy Document ready!', url: downloadUrl })
                try {
                  const dr = await fetch(downloadUrl, { headers: freshHeaders() })
                  if (dr.ok) window.open(URL.createObjectURL(await dr.blob()), '_blank')
                } catch {}
              } else if (sd.status === 'failed') {
                clearInterval(poll)
                setPdfNotif({ status: 'failed', message: 'Policy Document generation failed: ' + (sd.message || 'Unknown error') })
              } else if (sd.status === 'cancelled') {
                clearInterval(poll)
                setPdfNotif({ status: 'failed', message: 'Job was cancelled.' })
              } else {
                const pct = Math.max(0, Math.min(100, sd.progress ?? 0))
                setPdfNotif({ status: 'generating', message: `${pct}% — ${sd.message || sd.status}` })
              }
            } catch { clearInterval(poll); setPdfNotif({ status: 'failed', message: 'Lost connection to server' }) }
          }, 3000)
        } else if (d.url) {
          window.open(d.url, '_blank')
        } else if (d.path) {
          const pdfResp = await fetch(`${apiBase}/policies/${policyId}/download-document?path=${encodeURIComponent(d.path)}`, { headers: freshHeaders() })
          if (pdfResp.ok) {
            const blob = await pdfResp.blob()
            window.open(URL.createObjectURL(blob), '_blank')
          } else {
            const cdnBase = 'https://d20dgglp0tqnyi.cloudfront.net'
            window.open(`${cdnBase}/${d.path}`, '_blank')
          }
        } else { alert(d.message || 'Policy document generated.') }
      }
      refetch()
    } catch (e: any) { alert(e.message) }
    setActing(false)
  }

  // Policy Doc (Date Range): queue one Policy Document per action whose term
  // start falls in [from, to] (reuses the same backend pipeline as Policy Doc),
  // then poll all jobs. Button stays disabled until every job finishes; the
  // generated docs appear under the Documents tab (no download/zip).
  // STAGING ONLY: the latest cover-sheet OTP for this policy, so a tester can
  // read the code here instead of needing the handset the SMS went to (on a
  // real policy that phone is the customer's). The endpoint returns an empty
  // payload on production, so this stays hidden there with no extra guard.
  const [coverOtp, setCoverOtp] = useState<{ code: string; sentAt?: string | null; usedAt?: string | null; sentTo?: string | null } | null>(null)
  const [coverOtpLoading, setCoverOtpLoading] = useState(false)

  async function loadCoverOtp() {
    if (!policyId) return
    setCoverOtpLoading(true)
    try {
      const r = await fetch(`${apiBase}/policies/${policyId}/cover-sheet/otp`, {
        headers: { Authorization: `Bearer ${localStorage.getItem('sanctum_token') || ''}`, Accept: 'application/json' },
      })
      if (!r.ok) { setCoverOtp(null); return }
      const d = await r.json()
      setCoverOtp(d?.otp ?? null)
    } catch {
      setCoverOtp(null)
    } finally {
      setCoverOtpLoading(false)
    }
  }

  // Runs on mount and on every refresh, which is when a tester wants to read
  // it. Keyed on policyId only — loadCoverOtp is redefined each render, so
  // depending on it would re-fetch in a loop.
  useEffect(() => { loadCoverOtp() }, [policyId])

  // Cover Sheet — the one-page branded summary with the client-access QR.
  // ADDITIONAL to the Policy Document, never a replacement: the full pack is
  // untouched. Stored as an ordinary document (document_title 'POLICY COVER
  // SHEET'), so it downloads through the same download-quote-pdf route.
  async function generateCoverSheet() {
    setActing(true)
    // Re-read the token at click time — component-scope `headers` can hold a
    // stale token from an earlier render (same fix as V2 Quote / Policy Doc).
    const freshHeaders = (): Record<string, string> => {
      const t = localStorage.getItem('sanctum_token') || ''
      return { Authorization: `Bearer ${t}`, 'Content-Type': 'application/json', Accept: 'application/json' }
    }
    try {
      // Always send action_id: the sheet summarises the transaction the
      // operator is viewing, and its QR opens that action's document.
      const resp = await fetch(`${apiBase}/policies/${policyId}/cover-sheet`, {
        method: 'POST',
        headers: freshHeaders(),
        body: JSON.stringify({ action_id: activeId }),
      })
      const d = await resp.json()
      if (!resp.ok) { alert(d.message || d.error || 'Cover sheet failed'); setActing(false); return }

      // The sheet is valid even when this action has no Policy Document yet —
      // the QR starts working once it is generated. Staff must know before
      // they post the page.
      if (d.warning) alert(d.warning)

      const downloadUrl = `${apiBase}/policies/${policyId}/download-quote-pdf/${d.jobId}`
      setPdfNotif({ status: 'completed', message: 'Cover sheet ready!', url: downloadUrl })
      // Pick up any OTP already outstanding for this policy (staging only).
      loadCoverOtp()
      try {
        const dr = await fetch(downloadUrl, { headers: freshHeaders() })
        if (dr.ok) window.open(URL.createObjectURL(await dr.blob()), '_blank')
      } catch {}
    } catch (e: any) {
      alert(e?.message || 'Cover sheet failed')
    } finally {
      setActing(false)
    }
  }

  async function generatePolicyDocRange() {
    if (!docRangeFrom || !docRangeTo) { alert('Select both From and To dates.'); return }
    if (docRangeFrom > docRangeTo) { alert('From date must be on or before To date.'); return }
    setDocRangeGenerating(true)
    const freshHeaders = (): Record<string, string> => {
      const t = localStorage.getItem('sanctum_token') || ''
      return { Authorization: `Bearer ${t}`, 'Content-Type': 'application/json', Accept: 'application/json' }
    }
    try {
      const resp = await fetch(`${apiBase}/policies/${policyId}/generate-policy-documents-range`, {
        method: 'POST',
        headers: freshHeaders(),
        body: JSON.stringify({ from: docRangeFrom, to: docRangeTo }),
      })
      const d = await resp.json()
      if (!resp.ok) { alert(d.error || d.message || 'Failed'); setDocRangeGenerating(false); return }
      if (!d.count) {
        setPdfNotif({ status: 'failed', message: 'No policy actions found in the selected date range.' })
        setDocRangeGenerating(false)
        return
      }
      const jobIds: number[] = (d.jobs || []).map((j: any) => j.job_id).filter((x: any) => x != null)
      if (jobIds.length === 0) {
        setPdfNotif({ status: 'failed', message: 'Could not queue document generation for the selected range.' })
        setDocRangeGenerating(false)
        return
      }
      setShowDocRangeModal(false)
      setPdfNotif({ status: 'generating', message: `Generating ${jobIds.length} policy document(s) in the background…` })
      // Poll every queued job until all complete/fail. Button stays disabled meanwhile.
      const pending = new Set<number>(jobIds)
      const poll = setInterval(async () => {
        for (const jid of Array.from(pending)) {
          try {
            const sr = await fetch(`${apiBase}/policies/${policyId}/quote-pdf-status/${jid}`, { headers: freshHeaders() })
            const sd = await sr.json()
            if (sd.status === 'completed' || sd.status === 'failed' || sd.status === 'cancelled') {
              pending.delete(jid)
            }
          } catch { /* transient — retry next tick */ }
        }
        if (pending.size === 0) {
          clearInterval(poll)
          setDocRangeGenerating(false)
          setPdfNotif({ status: 'completed', message: `${jobIds.length} policy document(s) generated — see the Documents tab.` })
          refetch()
        } else {
          setPdfNotif({ status: 'generating', message: `Generating documents… ${jobIds.length - pending.size}/${jobIds.length} done.` })
        }
      }, 3000)
    } catch (e: any) {
      alert(e.message)
      setDocRangeGenerating(false)
    }
  }

  const policyIsActive = policyStatus === 1

  // "Edit Transaction" shows for EVERY transaction type (NEWBUSINESS and RENEW
  // included) and every product — Engineering / Specialist / Marine etc. too.
  // A mis-keyed effective period has to be correctable in place on any
  // transaction; unissuing the whole thing to fix two dates is not an option
  // on an already-invoiced action. Money safety is enforced on the backend
  // instead of by hiding the button: full-period types (NEWBUSINESS / RENEW /
  // ANNIVERSARY-RENEW / …) never re-rate on a date edit, and an ISSUED action
  // is never re-priced at all (actionRepriceable).
  const canEditTxn = !!selected
  // "Lapse" shows for ANNIVERSARY-RENEW/QUOTE (mirrors old Graphite getLapsedProperty)
  // showLapseBtn logic moved inline to button visibility rules
  // "Unissue" shows whenever any action in history has ISSUED status (not just selected)
  void allActions.some((a: any) => a.status === 'ISSUED' && a.transactionType !== 'RENEW') // hasIssuedAction — available if needed

  return (
    <div className="space-y-4">
      {/* Fallback for legacy policies (active, no action record) */}
      {!selected && policyIsActive && (
        <Card title="Policy Actions">
          <p className="text-sm text-ink-muted mb-3">This policy is currently <strong>active/issued</strong>. No action record found in the new workflow.</p>
          <div className="flex flex-wrap gap-2 border-t border-line pt-3">
            <button onClick={() => { if (confirm('Unissue this policy?')) doAction('unissue') }} disabled={acting}
              className="px-4 py-2 bg-status-warning-fg text-white rounded text-sm hover:bg-status-warning-fg disabled:opacity-50">Unissue Policy</button>
          </div>
        </Card>
      )}

      {/* ── Select Policy Action ── */}
      {allActions.length > 0 && (
        <Card title="Policy Actions">
          {/* The period shown in this card is the ACTION's own
              effective_from → effective_to, straight from policy_actions,
              printed verbatim — the SAME values the Edit Current Transaction
              modal loads into its date fields, so the dropdown label and that
              modal can never disagree (incl. a same-day endorse window).
              See actionPeriodEnd(): the POLICY expiry
              (policy.expiry_date → PolicyResource endDate) is only a
              FALLBACK for an action with no effective_to at all.
              It briefly LED instead, to mask a NEWBUSINESS effective_to that
              reads a year early on DOM/COM instalment policies. That
              backfired: expiry_date is itself re-stamped by issuePolicy on
              EVERY transaction — an endorse or cancel writes
              effective_from + 1yr - 1d into it — so ~2,600 DomCom policies
              carry a wrong expiry, and that single policy-level date printed
              on every row showed the wrong year for every action and
              disagreed with the edit modal. The instalment-NEWBUSINESS
              complaint must be fixed at its source, not by leading with a
              policy-level date here. */}
          {/* Action selector dropdown — mirrors old Graphite's "Select Policy Action At" */}
          <div className="mb-4">
            <label className="block text-xs font-medium text-ink-muted mb-1">Select Policy Action</label>
            <select
              value={activeId ?? ''}
              onChange={e => setSelectedActionId(Number(e.target.value))}
              className="w-full md:w-auto min-w-[340px] px-3 py-2 border border-line rounded text-sm focus:outline-none focus:ring-2 focus:ring-primary"
            >
              {[...allActions].sort((a: any, b: any) => {
                // Sort by effective_from DESC (most recent first); tiebreak by id DESC
                const da = a.effectiveFrom ? new Date(a.effectiveFrom).getTime() : 0
                const db = b.effectiveFrom ? new Date(b.effectiveFrom).getTime() : 0
                if (db !== da) return db - da
                return (b.id || 0) - (a.id || 0)
              }).map((a: any) => {
                const from = a.effectiveFrom ? new Date(a.effectiveFrom).toLocaleDateString('en-GB') : '?'
                const to   = actionPeriodEnd(policyEndDate, a)
                return (
                  <option key={a.id} value={a.id}>
                    {a.transactionType} — {a.status} ({from} – {to})
                  </option>
                )
              })}
            </select>
          </div>

          {/* Frequency — DOM/COM (7/8) only, shown after the action selector.
              Action-wise: reads the SELECTED action's own frequency stamp
              (policy_actions.current_frequency_id). The policy-level figure
              (policies.premium_freq) is printed beside it whenever the two
              differ, so an operator looking at a Quarterly NEWBUSINESS on an
              Annual policy sees both. Super Admin can correct either from the
              Set Frequency modal. */}
          {(productId === 7 || productId === 8) && actionFreqLabel && (
            <div className="mb-4 flex flex-wrap items-baseline gap-x-4 gap-y-1">
              <span>
                <span className="text-xs font-medium text-ink-muted">Transaction Frequency: </span>
                <span className="text-sm font-semibold text-ink">{actionFreqLabel}</span>
                {!actionFreqIsOwn && (
                  <span className="ml-1 text-xs text-ink-faint">(inherited)</span>
                )}
              </span>
              {policyFreqLabel && policyFreqLabel !== actionFreqLabel && (
                <span>
                  <span className="text-xs font-medium text-ink-muted">Policy Frequency: </span>
                  <span className="text-sm font-semibold text-ink">{policyFreqLabel}</span>
                </span>
              )}
              {isSuperAdmin && activeId && (
                <button onClick={openFreqModal}
                  className="text-xs px-2 py-1 border border-line rounded hover:bg-surface-2 text-ink-muted">
                  Set Frequency
                </button>
              )}
            </div>
          )}

          {/* Selected action details */}
          {selected && (
            <div className="flex flex-wrap items-center gap-3 mb-4 px-3 py-2 bg-surface-2 rounded-lg">
              <span className={`px-3 py-1 rounded-full text-xs font-bold ${STATUS_CLS[status] || 'bg-surface-2'}`}>{status}</span>
              <span className="text-sm font-semibold">{txType}</span>
              <span className="text-sm text-ink-muted">{selected.policyQuoteNo}</span>
              {selected.premium && <span className="text-sm font-bold text-primary">P {Number(selected.premium).toLocaleString()}</span>}
              <span className="text-xs text-ink-faint">
                {selected.effectiveFrom ? new Date(selected.effectiveFrom).toLocaleDateString('en-GB') : '?'}
                {' — '}
                {actionPeriodEnd(policyEndDate, selected)}
              </span>
              {selected.transactionReason && <span className="text-xs text-ink-muted">Reason: {selected.transactionReason}</span>}
              {selected.note && <span className="text-xs text-ink-faint italic">"{selected.note}"</span>}
            </div>
          )}

          {/* ── Validation gates banner ──
              Surfaces the operator's screen-error message from the legacy GFS
              rule engine BEFORE the user clicks. Same source as the per-button
              disable/tooltip, but always-visible so UW can see which rule is
              firing without hovering each greyed-out action. */}
          {gates && gates.blockingRules.length > 0 && (
            <div className="border-t border-line pt-3 mb-2">
              <div className="rounded-md border border-status-warning-fg bg-status-warning-bg px-3 py-2 text-sm text-status-warning-fg">
                <div className="font-semibold mb-1">⚠️ Some actions are restricted for your role on this policy</div>
                <ul className="list-disc pl-5 space-y-1">
                  {gates.blockingRules.map((r) => (
                    <li key={r.ruleCode}>
                      <span className="font-mono text-xs bg-status-warning-bg px-1 rounded">{r.ruleCode}</span>{' '}
                      {r.message}
                      {r.blocks?.length > 0 && (
                        <span className="text-xs text-status-warning-fg ml-1">
                          (blocks: {r.blocks.join(', ')})
                        </span>
                      )}
                    </li>
                  ))}
                </ul>
              </div>
            </div>
          )}

          {/* ── Action Buttons — matches graphiteBWV8 Submit.blade.php rules ── */}
          {/* Permission-gated + status + transaction type rules */}
          <div className="flex flex-wrap gap-2 border-t border-line pt-3">
            {/* EDIT COVERAGES — legacy graphiteBWV8 EditWizard only accepts
                QUOTE rows. To modify an ISSUED policy the operator must
                first create an endorsement (Endorse Policy button) — that
                spawns a new QUOTE action which IS editable. Same rule for
                IN_APPROVAL / APPROVED: un-issue first to drop back to QUOTE.
                Showing the button on non-QUOTE rows only misled users into
                opening the wizard and seeing read-only data.

                UAT 2026-06-03 (Satyajeet): also gate by COVERAGE_PRODUCT_IDS
                — MIS retail products don't have a working V2 edit wizard.
                In practice MIS policies skip QUOTE (created straight to
                ISSUED via the public payment flow), so this gate is
                defensive but keeps the file consistent with the other
                MIS gates in the same component (lines 3025/3060). */}
            {status === 'QUOTE' && COVERAGE_PRODUCT_IDS.includes(productId) && (
              <Link to={`/policies/${policyId}/edit?action_id=${selected?.id ?? ''}`}
                className="px-4 py-2 bg-primary text-white rounded text-sm font-medium hover:bg-primary">
                ✎ Edit Coverages
              </Link>
            )}
            {/* RATE — always visible for QUOTE (except CANCEL txn type) */}
            {((status === 'QUOTE' && txType !== 'CANCEL') || (txType === 'CANCEL' && status !== 'ISSUED')) && (
              <button onClick={async () => {
                setActing(true)
                setRateResult(null)
                try {
                  // Policy-level rating: backend switches on empty coverage_id,
                  // so just send action_id. The prior `{coverage_id: null,
                  // coverage_value: 0}` was misleading payload noise.
                  const res = await fetch(`${apiBase}/policies/${policyId}/calculate-premium`, {
                    method: 'POST', headers,
                    body: JSON.stringify({ action_id: selected?.id }),
                  })
                  const d = await res.json()
                  // Dev-only console trace — helps triage the "nothing happens" report.
                  console.log('[Rate] response', res.status, d)
                  if (!res.ok) {
                    setRateResult({ kind: 'err', message: d.error || d.message || `Rating failed (HTTP ${res.status})` })
                    setActing(false)
                    return
                  }
                  setIsRated(true)
                  refetch()
                  setRateResult({
                    kind: 'ok',
                    annualPremium: d.annual_premium ?? d.premium ?? 0,
                    proRatePremium: d.pro_rate_premium ?? d.annual_premium ?? 0,
                    message: (d.annual_premium ?? d.premium ?? 0) === 0
                      ? 'Computed premium is 0 — add at least one coverage before rating.'
                      : undefined,
                  })
                } catch (e: any) {
                  console.error('[Rate] exception', e)
                  setRateResult({ kind: 'err', message: e.message || 'Network error' })
                }
                setActing(false)
              }} disabled={acting}
                className="px-4 py-2 bg-status-warning-fg text-black font-semibold rounded text-sm hover:bg-status-warning-fg disabled:opacity-50">
                {acting ? 'Rating…' : 'Rate'}
              </button>
            )}

            {/* SUBMIT TO APPROVAL — QUOTE + rated + permission: policy_submit_to_approval */}
            {status === 'QUOTE' && (isRated || Number(selected?.premium) > 0 || selected?.hasSpecialistPremium) && hasPerm('policy_submit_to_approval') && (() => {
              const blocker = blockerFor('canSubmitUnbound')
              return (
                <button
                  onClick={() => doAction('submit-approval')}
                  disabled={acting || !!blocker}
                  title={blocker ?? undefined}
                  className="px-4 py-2 bg-ink text-white rounded text-sm hover:bg-ink disabled:opacity-50">
                  Submit to Approval
                </button>
              )
            })()}

            {/* APPROVE — IN_APPROVAL + permission: policy_approved.
                Bonds & Guarantees (product 23) additionally require the EXCO
                `bonds-approve` right; the backend returns 403 without it, so
                hide the button rather than offer a click that must fail. */}
            {status === 'IN_APPROVAL' && hasPerm('policy_approved')
              && (productId !== 23 || hasPerm('bonds-approve')) && (
              <button onClick={() => doAction('approve')} disabled={acting}
                className="px-4 py-2 bg-status-success-fg text-white rounded text-sm hover:bg-status-success-fg disabled:opacity-50">Approve</button>
            )}
            {/* Bonds without the EXCO right — say why, so the operator doesn't
                hunt for a missing button. */}
            {status === 'IN_APPROVAL' && productId === 23 && !hasPerm('bonds-approve') && (
              <p className="px-3 py-2 text-xs text-status-warning-fg">
                Bonds and Guarantees are approved by EXCO only.
              </p>
            )}

            {/* REJECT — IN_APPROVAL + permission: policy_rejected */}
            {status === 'IN_APPROVAL' && hasPerm('policy_rejected') && (
              <button onClick={() => doAction('reject')} disabled={acting}
                className="px-4 py-2 bg-status-danger-fg text-white rounded text-sm hover:bg-status-danger-fg disabled:opacity-50">Reject</button>
            )}

            {/* SUBMIT TO ISSUE — APPROVED + permission: policy_submit_to_issue */}
            {status === 'APPROVED' && hasPerm('policy_submit_to_issue') && (() => {
              const blocker = blockerFor('canIssue')
              return (
                <button
                  onClick={() => doAction('issue')}
                  disabled={acting || !!blocker}
                  title={blocker ?? undefined}
                  className="px-4 py-2 bg-primary text-white rounded text-sm hover:bg-primary disabled:opacity-50">
                  Submit to Issue
                </button>
              )
            })()}

            {/* REJECTED: Resubmit (back to QUOTE flow) */}
            {status === 'REJECTED' && hasPerm('policy_submit_to_approval') && (
              <button onClick={() => doAction('submit-approval')} disabled={acting}
                className="px-4 py-2 bg-ink text-white rounded text-sm hover:bg-ink disabled:opacity-50">Resubmit</button>
            )}

            {/* APPROVE FROM REJECTED — undo a mistaken Reject without
                routing through QUOTE → re-rate → re-submit. Same permission
                gate as the IN_APPROVAL Approve button. Confirm prompt so a
                stray click doesn't flip a genuinely-rejected action. */}
            {status === 'REJECTED' && hasPerm('policy_approved') && (
              <button
                onClick={() => { if (confirm('Approve this action that was previously rejected? This will move it directly to APPROVED.')) doAction('approve') }}
                disabled={acting}
                className="px-4 py-2 bg-status-success-fg text-white rounded text-sm hover:bg-status-success-fg disabled:opacity-50">Approve</button>
            )}

            {/* UNISSUE — not LAPSED, not NTU, not QUOTE, not RENEW txn type + permission: policy_unissue */}
            {status !== 'LAPSED' && status !== 'NTU' && status !== 'QUOTE' && txType !== 'RENEW' && hasPerm('policy_unissue') && (
              <button onClick={() => { if (confirm(`Unissue this transaction (${status})? Status will revert to QUOTE.`)) doAction('unissue') }} disabled={acting}
                className="px-4 py-2 bg-status-warning-fg text-white rounded text-sm hover:bg-status-warning-fg disabled:opacity-50">Unissue</button>
            )}

            {/* NTU — Not Taken Up. Mirrors graphiteBWV8 EditWizard::submitToNTU().
                Offered on any NEWBUSINESS quote (no ledger/payment gate — the
                financial-activity check was removed per ops request). */}
            {status === 'QUOTE' && txType === 'NEWBUSINESS' && hasPerm('policy_refersh_endorse') && (
              <button
                onClick={() => { if (confirm('Mark this New Business quote as Not Taken Up? This cannot be undone.')) doAction('ntu') }}
                disabled={acting}
                className="px-4 py-2 bg-status-warning-fg text-white rounded text-sm hover:bg-status-warning-fg disabled:opacity-50">
                NTU
              </button>
            )}

            {/* LAPSE — ANNIVERSARY-RENEW + QUOTE + permission: policy_refersh_endorse */}
            {txType === 'ANNIVERSARY-RENEW' && status === 'QUOTE' && hasPerm('policy_refersh_endorse') && (
              <button onClick={() => { if (confirm('Lapse this policy?')) doAction('lapse') }} disabled={acting}
                className="px-4 py-2 bg-status-warning-fg text-white rounded text-sm hover:bg-status-warning-fg disabled:opacity-50">Lapse</button>
            )}

            {/* DELETE ENDORSEMENT — ENDORSE + QUOTE + permission: policy_delete_endorse */}
            {txType === 'ENDORSE' && status === 'QUOTE' && hasPerm('policy_delete_endorse') && (
              <button onClick={async () => {
                if (!confirm('Delete this endorsement? This cannot be undone.')) return
                const r = await doAction('delete-action')
                if (r) {
                  // Drop selection AND nuke the cached actions list so the
                  // deleted row disappears immediately — without
                  // invalidateQueries, react-query keeps the stale cache
                  // for staleTime (5 min) and the dropdown still shows the
                  // soft-deleted action until the user hard-refreshes.
                  setSelectedActionId(null)
                  setRateResult(null)
                  setIsRated(false)
                  await qc.invalidateQueries({ queryKey: ['policy', policyId, 'actions'] })
                  await qc.invalidateQueries({ queryKey: ['policy', policyId] })
                  await refetch()
                }
              }} disabled={acting}
                className="px-4 py-2 bg-status-danger-fg text-white rounded text-sm hover:bg-status-danger-fg disabled:opacity-50">Delete Endorsement</button>
            )}

            {/* CHANGE SUMMARY — read-only ENDORSE/CANCEL diagnostic, available to
                EVERYONE (no role gate — the backend EndorseChangeSummary::load is
                likewise open and writes nothing). Shows every added/changed/deleted
                coverage line + the pro-rata reconciliation math. Reuses the same
                backend logic as the Livewire edit-wizard popup, so the two never
                drift. Do NOT add a role/permission gate here. */}
            {(txType === 'ENDORSE' || txType === 'CANCEL') && (
              <button
                onClick={async () => {
                  setShowChangeSummary(true)
                  setCsLoading(true); setCsError(''); setCsRows([]); setCsMath({})
                  try {
                    const r = await fetch(`${apiBase}/policies/${policyId}/actions/${activeId}/change-summary`, { headers })
                    const d = await r.json()
                    if (!r.ok && !d?.error) { setCsError(`Failed (${r.status}).`); return }
                    setCsRows(Array.isArray(d?.rows) ? d.rows : [])
                    setCsMath(d?.math ?? {})
                    setCsError(d?.error ?? '')
                  } catch (e: any) {
                    setCsError(e?.message ?? 'Request failed.')
                  } finally {
                    setCsLoading(false)
                  }
                }}
                className="px-4 py-2 rounded text-sm text-white disabled:opacity-50"
                style={{ backgroundColor: '#0D1B2A' }}
                title="Read-only: what this endorsement/cancel added, changed or deleted + the pro-rata math">
                📋 Change Summary
              </button>
            )}

            {/* REFRESH ENDORSEMENT — push this action's coverage/motor/SI data
                forward into any later RENEW / ANNIVERSARY-RENEW / ENDORSE /
                REINSTATE actions. Mirrors legacy Submit::refreshEndorse(). Only
                valid on ISSUED actions of types that drive the downstream chain. */}
            {status === 'ISSUED' && ['NEWBUSINESS', 'ENDORSE', 'RENEW', 'ANNIVERSARY-RENEW'].includes(txType) && (
              <button
                onClick={async () => {
                  // Name the SOURCE explicitly. The refresh pushes data forward
                  // by effective date from THIS action; picking the newest action
                  // (nothing dated after it) silently updates 0, so make the
                  // source unambiguous before the destructive rebuild runs.
                  const srcFrom = selected?.effectiveFrom ? new Date(selected.effectiveFrom).toLocaleDateString('en-GB') : '?'
                  if (!confirm(`Refresh downstream FROM ${selected?.transactionType} (effective ${srcFrom})?\n\nEvery later action (by effective date) will be rebuilt from this one and RENEW invoices regenerated. To update a later batch, refresh FROM an earlier action — not the newest.`)) return
                  const res = await doAction('refresh-endorse')
                  // 202 + queued → poll the status file until the job finishes
                  // and tell the user; otherwise just surface the message.
                  if (res?.queued) pollRefreshStatus()
                  else if (res?.message) alert(res.message)
                }}
                disabled={acting || !!refreshStatus}
                className="px-4 py-2 bg-primary text-white rounded text-sm hover:bg-primary disabled:opacity-50"
                title="Push coverage/motor/SI changes into later actions + regenerate RENEW invoices">
                {refreshStatus
                  ? `↻ Refreshing…${refreshStatus.total ? ` (${refreshStatus.done ?? 0}/${refreshStatus.total})` : ''}`
                  : '↻ Refresh Endorsement'}
              </button>
            )}

            {/* DELETE RENEW — Super Admin only. Discards a wrongly batch-created
                RENEW (e.g. an annual policy the monthly cron renewed) together
                with its invoice. Backend re-checks the role and refuses when a
                payment/credit exists on the invoice (use Reverse Invoice first). */}
            {txType === 'RENEW' && isSuperAdmin && (
              <button onClick={async () => {
                if (!confirm('Delete this RENEW transaction and its invoice? This cannot be undone.')) return
                const r = await doAction('delete-renew')
                if (r) {
                  setSelectedActionId(null)
                  setRateResult(null)
                  setIsRated(false)
                  await qc.invalidateQueries({ queryKey: ['policy', policyId, 'actions'] })
                  await qc.invalidateQueries({ queryKey: ['policy', policyId] })
                  await refetch()
                }
              }} disabled={acting}
                className="px-4 py-2 bg-status-danger-fg text-white rounded text-sm hover:bg-status-danger-fg disabled:opacity-50">Delete Renew</button>
            )}

            {/* RATE INVOICE — Super Admin only, RENEW + ISSUED. Re-rates this
                action and pushes the rated premium into its invoice amount
                only; nothing else on the policy changes. Backend re-checks the
                role. An already-settled invoice is re-priced as well: the
                receipt stays attached and the still-outstanding amount is
                re-derived. Only a live refund / credit note refuses. */}
            {txType === 'RENEW' && status === 'ISSUED' && isSuperAdmin && (
              <button onClick={async () => {
                if (!confirm(
                  'Re-rate this RENEW and update its invoice amount to the rated premium?\n\n'
                  + 'Nothing else on the policy changes. If a payment is already on this invoice it stays '
                  + 'attached and the amount still outstanding is re-derived.',
                )) return
                const r = await doAction('rate-renew-invoice')
                if (r) {
                  const how = r.recipe === 'source-verbatim'
                    ? `copied verbatim from the source period (action ${r.source_action_id})`
                    : r.recipe === 'endorse-recompute'
                      ? `recomputed to a full period from endorse action ${r.source_action_id}`
                      : r.recipe === 'tree-recompute'
                        ? "recomputed from this action's own coverages (no earlier ISSUED period at this frequency)"
                        : 'recomputed by the specialist rater'
                  alert(
                    `Invoice updated to rated premium: ${r.new_premium} (was ${r.old_premium}) across ${r.invoice_rows} invoice row(s).`
                    + `\nFigure ${how}.`
                    + (r.settled_rows ? `\n\n${r.settled_rows} of those already carried a payment — the receipt is unchanged and the amount due was re-derived. Check the ledger balance.` : ''),
                  )
                  await qc.invalidateQueries({ queryKey: ['policy', policyId, 'actions'] })
                  await qc.invalidateQueries({ queryKey: ['policy', policyId] })
                  await refetch()
                }
              }} disabled={acting}
                className="px-4 py-2 bg-status-warning-fg text-black font-semibold rounded text-sm hover:bg-status-warning-fg disabled:opacity-50">
                {acting ? 'Rating…' : 'Rate Invoice'}
              </button>
            )}

            {/* RATE ALL RENEW INVOICES — the same rate-renew-invoice endpoint in
                scope=all mode, so a run of wrong monthly invoices is corrected
                from this one screen instead of one click per month. The first
                call is a preview: the backend rates every ISSUED RENEW for real
                inside a rolled-back transaction, so the figures are the
                canonical rater's and nothing is written until the user confirms
                the listed changes. Months the guard refuses (live refund /
                credit note) are listed as skipped with the backend's own
                reason rather than silently rated or silently dropped. */}
            {txType === 'RENEW' && status === 'ISSUED' && isSuperAdmin && (
              <button onClick={async () => {
                const fmtSkip = (r: any) => `${r.period}: ${r.reason}`
                const fmtAmt = (n: any) => Number(n ?? 0).toFixed(2)

                const p = await doAction('rate-renew-invoice', { scope: 'all', preview: true })
                if (!p) return
                const rows: any[] = p.results ?? []
                const diff = rows.filter((r) => r.status === 'ok' && Math.abs(Number(r.delta)) >= 0.01)
                const skips = rows.filter((r) => r.status !== 'ok')
                const skipBlock = skips.length ? `\n\nSkipped (not changed):\n${skips.map(fmtSkip).join('\n')}` : ''

                if (!diff.length) {
                  alert(`All ${rows.length} RENEW invoice(s) already match the rated premium.${skipBlock}`)
                  return
                }

                const body = diff.map((r) =>
                  `${r.period}  ${r.invoice_no ?? '(no invoice)'}\n`
                  + `    ${fmtAmt(r.invoice_amount)} -> ${fmtAmt(r.new_premium)}`
                  + `  (${Number(r.delta) > 0 ? '+' : ''}${fmtAmt(r.delta)})`,
                ).join('\n')

                if (!confirm(
                  `${diff.length} of ${rows.length} RENEW invoice(s) differ from the rated premium:\n\n`
                  + `${body}${skipBlock}\n\nUpdate these invoice amounts now?`,
                )) return

                const r = await doAction('rate-renew-invoice', { scope: 'all' })
                if (r) {
                  const done = (r.results ?? []).filter((x: any) => x.status === 'ok')
                  alert(`${r.message}\n\n${done.map((x: any) => `${x.period}: ${fmtAmt(x.new_premium)}`).join('\n')}`)
                  await qc.invalidateQueries({ queryKey: ['policy', policyId, 'actions'] })
                  await qc.invalidateQueries({ queryKey: ['policy', policyId] })
                  await refetch()
                }
              }} disabled={acting}
                className="px-4 py-2 border border-status-warning-fg text-status-warning-fg font-semibold rounded text-sm hover:bg-status-warning-bg disabled:opacity-50">
                {acting ? 'Rating…' : 'Rate All Renew Invoices'}
              </button>
            )}

            {/* DELETE CANCEL/ANNIVERSARY-RENEW QUOTE — permission: policy_delete_cancel */}
            {(txType === 'CANCEL' || txType === 'ANNIVERSARY-RENEW') && status === 'QUOTE' && hasPerm('policy_delete_cancel') && (
              <button onClick={async () => {
                if (!confirm(`Delete this ${txType} quote?`)) return
                const r = await doAction('delete-action')
                if (r) {
                  setSelectedActionId(null)
                  setRateResult(null)
                  setIsRated(false)
                  await qc.invalidateQueries({ queryKey: ['policy', policyId, 'actions'] })
                  await qc.invalidateQueries({ queryKey: ['policy', policyId] })
                  await refetch()
                }
              }} disabled={acting}
                className="px-4 py-2 bg-status-danger-fg text-white rounded text-sm hover:bg-status-danger-fg disabled:opacity-50">Delete {txType} Quote</button>
            )}
            {/* DELETE REINSTATE/REISSUE QUOTE — REINSTATE/REISSUE + QUOTE +
                role: Admin / Super Admin / Manager only. Backend deleteAction
                already soft-deletes any non-NEWBUSINESS QUOTE action; this
                surfaces the option so a reinstate/reissue quote can be
                discarded while still on quote. */}
            {(txType === 'REINSTATE' || txType === 'REISSUE') && status === 'QUOTE' && isAdminOrManager && (
              <button onClick={async () => {
                if (!confirm(`Delete this ${txType} quote? This cannot be undone.`)) return
                const r = await doAction('delete-action')
                if (r) {
                  setSelectedActionId(null)
                  setRateResult(null)
                  setIsRated(false)
                  await qc.invalidateQueries({ queryKey: ['policy', policyId, 'actions'] })
                  await qc.invalidateQueries({ queryKey: ['policy', policyId] })
                  await refetch()
                }
              }} disabled={acting}
                className="px-4 py-2 bg-status-danger-fg text-white rounded text-sm hover:bg-status-danger-fg disabled:opacity-50">Delete {txType} Quote</button>
            )}
            {status === 'LAPSED' && canReinstate() && (
              <button onClick={async () => {
                setReinstateLoading(true)
                try {
                  const r = await fetch(`${apiBase}/getPolicyInformationReinstate`, { method: 'POST', headers, body: JSON.stringify({ policy_id: policyId }) })
                  const d = await r.json(); setReinstateInfo(d?.information ?? d); setShowReinstate(true)
                } catch (e: any) { alert(e.message) }
                setReinstateLoading(false)
              }} disabled={acting || reinstateLoading}
                className="px-4 py-2 bg-status-success-fg text-white rounded text-sm hover:bg-status-success-fg disabled:opacity-50">
                {reinstateLoading ? 'Loading…' : '↩ Reinstate'}
              </button>
            )}
            {/* Edit Transaction — every transaction type, every product */}
            {canEditTxn && (
              <button onClick={openEditTxnModal} disabled={acting}
                className="px-4 py-2 border border-primary text-primary rounded text-sm hover:bg-status-info-bg disabled:opacity-50">✏ Edit Transaction</button>
            )}
            {/* Endorse Policy (ISSUED only) — hidden for motor/specialist/engineering products */}
            {status === 'ISSUED' && !COVERAGE_PRODUCT_IDS.includes(productId) && (
              <button onClick={async () => {
                // Default dates like legacy AddTransaction: effective_from=today,
                // effective_to=current action's effective_to.
                const today = new Date().toISOString().split('T')[0]
                setEndorseForm({
                  effective_from: today,
                  effective_to: selected?.effectiveTo ?? '',
                  transaction_reason: '',
                  note: '',
                })
                try {
                  const r = await fetch(`${apiBase}/lookups/transaction-subtypes/ENDORSE`, { headers })
                  const d = await r.json()
                  setEndorseSubTypes(d.data ?? [])
                } catch { setEndorseSubTypes([]) }
                setShowEndorse(true)
              }} disabled={acting}
                className="px-4 py-2 bg-primary text-white rounded text-sm hover:bg-primary disabled:opacity-50">✏ Endorse Policy</button>
            )}
            {/* Renew Policy (ISSUED only) — restricted to Super Admin */}
            {status === 'ISSUED' && isSuperAdmin && (
              <button onClick={() => {
                const from = selected?.effectiveTo
                  ? (() => { const d = new Date(selected.effectiveTo); d.setDate(d.getDate() + 1); return d.toISOString().split('T')[0] })()
                  : ''
                const to = from
                  ? (() => { const d = new Date(from); d.setFullYear(d.getFullYear() + 1); d.setDate(d.getDate() - 1); return d.toISOString().split('T')[0] })()
                  : ''
                setRenewForm({ effective_from: from, effective_to: to, transaction_date: new Date().toISOString().split('T')[0], note: '' })
                setShowRenew(true)
              }} disabled={acting}
                className="px-4 py-2 bg-status-success-fg text-white rounded text-sm hover:bg-status-success-fg disabled:opacity-50">↻ Renew Policy</button>
            )}
            {/* Send Renewal Link (ISSUED only) — customer-facing tokenised URL via Email + WhatsApp/SMS — hidden for motor/specialist/engineering products */}
            {status === 'ISSUED' && !COVERAGE_PRODUCT_IDS.includes(productId) && (
              <button onClick={async () => {
                if (!confirm('Send the renewal link to the customer via Email + WhatsApp/SMS?')) return
                setActing(true)
                try {
                  const r = await fetch(`${apiBase}/policies/${policyId}/renewal-link`, { method: 'POST', headers })
                  const d = await r.json()
                  alert(r.ok ? `${d.message}\n${d.sent_via?.length ? 'Sent via: ' + d.sent_via.join(', ') : ''}\n${d.link}` : (d.error || 'Failed'))
                } catch (e: any) { alert(e.message) }
                setActing(false)
              }} disabled={acting}
                className="px-4 py-2 border border-status-success-fg text-status-success-fg rounded text-sm hover:bg-status-success-bg disabled:opacity-50">📧 Send Renewal Link</button>
            )}
            {/* Reinstate Policy — Cancelled/Expired policies only (status != 1) — hidden for motor/specialist/engineering products.
                MIS / legacy retail policies were cancelled outside the V2 transaction
                workflow (status=2, no ISSUED CANCEL), so they use the status-based
                /reinstate-legacy endpoint which reactivates them directly. Non-MIS
                (DOM/COM/specialist cancelled in V2) keep /reinstate-create, which
                opens a REINSTATE transaction to edit → rate → submit → issue. */}
            {(policyStatus !== 1) && !COVERAGE_PRODUCT_IDS.includes(productId) && canReinstate() && (
              <>
                <button
                  onClick={() => setShowReinstateModal(true)}
                  disabled={acting}
                  className="px-4 py-2 bg-status-success-fg text-white rounded text-sm hover:bg-status-success-fg disabled:opacity-50"
                >
                  ⟲ Reinstate Policy
                </button>
                {showReinstateModal && (
                  <ReinstatePolicyModal
                    policyId={policyId}
                    policyNumber={policyNumber ?? String(policyId)}
                    productId={productId}
                    onClose={() => setShowReinstateModal(false)}
                    onReinstated={() => {
                      setShowReinstateModal(false)
                      refetch()
                      qc.invalidateQueries({ queryKey: ['policy', policyId] })
                      qc.invalidateQueries({ queryKey: ['policyCancelReason', policyId] })
                    }}
                  />
                )}
              </>
            )}
            {/* New Transaction — enabled whenever the action in view (the one
                selected in the dropdown) is ISSUED, or LAPSED so REISSUE/
                REINSTATE stay possible. Per Monika 2026-06-03: every issued
                state — NEWBUSINESS / RENEW / ANNIVERSARY-RENEW / REINSTATE /
                REISSUE / CANCEL / ENDORSE — may start a follow-up transaction,
                even when a later action is still sitting in QUOTE (e.g. a future
                DOM/COM renewal quote). We deliberately gate on the SELECTED
                action, not data.current, so viewing an issued renewal while a
                newer QUOTE exists still lets you endorse. The backend
                transaction-types lookup + store guard reject conflicting
                in-flight endorse-class types (409), so the modal self-protects
                against double transactions. */}
            {(() => {
              const blocked = !['ISSUED', 'LAPSED'].includes(selected?.status)
              const tip = blocked
                ? `Select an ISSUED action to start a new transaction — the current selection (${selected?.transactionType ?? '—'} — ${selected?.status ?? '—'}) is not issued yet.`
                : ''
              return (
                <button
                  onClick={openNewTxnModal}
                  disabled={acting || blocked}
                  title={tip}
                  className="px-4 py-2 bg-primary text-white rounded text-sm hover:bg-primary disabled:opacity-50 disabled:cursor-not-allowed">
                  + New Transaction
                </button>
              )
            })()}
            {/* V2 Quote Sheet — cross-user-aware. Button disables while any
                user (including this one) has an in-flight job for the same
                action. The banner above (V2 Quote Awareness) shows who, what,
                and how long. No auto-open after generation — user clicks
                Download in the banner when ready. */}
            {(() => {
              const inFlight = !!(v2QuoteJob && v2QuoteJob.is_in_flight && !v2QuoteJob.is_stale && v2QuoteJob.action_id === activeId)
              const otherUserGenerating = inFlight && v2QuoteJob?.created_by && !v2QuoteJob.created_by.is_current_user
              // Actual generation call — extracted so the regenerate-warning
              // confirm modal can fire the same flow after user clicks "Generate
              // Anyway". Stashed on a ref so the modal's handler can reach it
              // without prop-drilling.
              const fireGenerate = async () => {
                setActing(true)
                const freshHeaders = (): Record<string, string> => {
                  const t = localStorage.getItem('sanctum_token') || ''
                  return { Authorization: `Bearer ${t}`, 'Content-Type': 'application/json', Accept: 'application/json' }
                }
                try {
                  const r = await fetch(`${apiBase}/policies/${policyId}/generate-v2-quote-sheet`, { method: 'POST', headers: freshHeaders(), body: JSON.stringify({ action_id: activeId }) })
                  if (r.status === 401) {
                    setPdfNotif({ status: 'failed', message: 'Session token invalid for this endpoint — try refreshing the page.' })
                    pushToast('Session expired — refresh the page', 'error')
                    setActing(false); return
                  }
                  const ct = r.headers.get('content-type') || ''
                  if (ct.includes('pdf')) {
                    const blob = await r.blob()
                    const url = URL.createObjectURL(blob)
                    const a = document.createElement('a')
                    // force-download via `download` attribute (no target=_blank)
                    // — saves the PDF to disk instead of opening it in a new
                    // browser-viewer tab. User can open after if they want.
                    a.href = url
                    a.download = buildQuoteFilename()
                    a.click()
                    setTimeout(() => URL.revokeObjectURL(url), 5000)
                    pushToast('Quote Sheet ready — downloading', 'success')
                  } else {
                    const d = await r.json()
                    if (d.deduped) {
                      pushToast('Joined existing generation by ' + (d.created_by_name ?? 'another user'), 'info')
                    } else if (!d.job_id && d.url) {
                      const a = document.createElement('a')
                      a.href = d.url
                      a.download = buildQuoteFilename()
                      a.click()
                      pushToast('Quote Sheet ready — downloading', 'success')
                    } else if (!d.job_id) {
                      setPdfNotif({ status: 'failed', message: d.error || d.message || 'Failed' })
                      pushToast(d.error || d.message || 'Generation failed', 'error')
                    } else {
                      pushToast('Generation queued — banner above will update', 'info')
                    }
                  }
                } catch (e: any) {
                  setPdfNotif({ status: 'failed', message: e.message })
                  pushToast('Generation failed: ' + (e?.message || 'network error'), 'error')
                }
                setActing(false)
              }
              // Stash the latest fireGenerate closure on a ref so the regenerate
              // confirm modal (rendered separately, outside this IIFE) can
              // invoke it without re-rendering the button.
              fireGenerateRef.current = fireGenerate
              return (
              <button onClick={() => {
                // Soft warning — if backend's /v2-quote/latest payload says
                // should_warn=true (recent quote + no policy changes since),
                // pop the confirm modal instead of firing immediately. Slack
                // pattern; user stays in control, no hard disable.
                if (v2RegenerateWarning?.should_warn) {
                  setShowRegenerateConfirm(true)
                  return
                }
                fireGenerate()
              }}
                disabled={
                  acting ||
                  inFlight ||
                  !(isRated || (selected?.premium != null && Number(selected?.premium) !== 0) || selected?.status === 'ISSUED' || selected?.hasSpecialistPremium) ||
                  !!blockerFor('canPrintQuote')
                }
                title={
                  blockerFor('canPrintQuote')
                  ?? (inFlight
                      ? (otherUserGenerating
                          ? `${v2QuoteJob?.created_by?.name} is generating this quote sheet (started ${Math.round((v2QuoteJob?.elapsed_seconds ?? 0))}s ago)`
                          : `You're already generating this quote sheet (started ${Math.round((v2QuoteJob?.elapsed_seconds ?? 0))}s ago)`)
                      : (!(isRated || (selected?.premium != null && Number(selected?.premium) !== 0) || selected?.status === 'ISSUED' || selected?.hasSpecialistPremium)
                          ? 'Click Rate first to compute premium before generating the quote sheet'
                          : undefined))
                }
                className="px-4 py-2 text-white rounded text-sm disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center gap-2"
                style={{ backgroundColor: 'rgb(var(--accent-fg))' }}>
                {inFlight && (
                  <span className="w-3.5 h-3.5 border-2 border-line border-t-transparent rounded-full animate-spin" />
                )}
                {inFlight ? 'Generating…' : '+ Generate Quote Sheet'}
              </button>
              )
            })()}
            {/* Download Quote Sheet — split-button dropdown with Latest /
                Previous version options. Shown whenever a downloadable
                quote exists for this action (selected.lastV2QuoteUrl from
                the action payload OR v2QuoteJob.download_url from live
                polling). When previous_completed is also available (an
                older completed quote for same policy+action+document_title),
                expose it as the "Previous" menu entry — lets ops grab a
                prior version if the latest looks wrong.
                Persistent button is hidden while the awareness banner is
                showing a completed/downloadable state — that banner's
                Download has been removed (status-only banner), so this is
                the single canonical download surface either way. */}
            {(() => {
              // Canonical source: the action-scoped, quote-sheet-only row from
              // the 5s poll. It outlives the banner (which self-dismisses at
              // 120s) and is refreshed server-side, so the button no longer
              // goes dead a couple of minutes after a successful generation.
              const actionFile = (v2QuoteActionFile && v2QuoteActionFile.download_url && v2QuoteActionFile.action_id === activeId)
                ? v2QuoteActionFile
                : null
              // Fallbacks, in order: the live banner job (must be this action
              // AND a quote sheet — a completed POLICY DOCUMENT must never
              // light up "Download Quote Sheet"), then the page-load payload.
              const liveCompleted = !!(v2QuoteJob && v2QuoteJob.status === 'completed' && v2QuoteJob.download_url && v2QuoteJob.action_id === activeId && !v2QuoteJob.document_title)
              const hasLatestUrl = !!actionFile || liveCompleted || !!selected?.lastV2QuoteUrl
              // Always render the Download button — when nothing is available
              // (no live completed job for this action AND no lastV2QuoteUrl),
              // show it disabled with a clear tooltip. Avoids the previous
              // "button disappears entirely" UX where users on an ISSUED policy
              // with no quote (e.g., pre-V2 renewals) saw only a disabled
              // Generate button and had no signal about what to do.
              if (!hasLatestUrl) {
                return (
                  <button
                    disabled
                    title="No quote sheet has been generated for this action yet. Click Generate Quote Sheet to create one."
                    className="px-4 py-2 bg-surface-2 text-ink-muted rounded text-sm inline-flex items-center gap-2 cursor-not-allowed">
                    ↓ Download Quote Sheet
                  </button>
                )
              }
              const latestUrl = actionFile
                ? actionFile.download_url!
                : (liveCompleted ? v2QuoteJob!.download_url! : (selected!.lastV2QuoteUrl as string))
              const latestCreator = actionFile
                ? (actionFile.created_by?.name ?? null)
                : (liveCompleted ? (v2QuoteJob?.created_by?.name ?? null) : null)
              const latestDuration = actionFile
                ? actionFile.generation_duration_seconds
                : (liveCompleted ? v2QuoteJob?.generation_duration_seconds : null)
              const previousAvailable = !!(v2QuotePrevious && v2QuotePrevious.download_url)
              const fmtSec = (s: number) => s < 60 ? `${s}s` : `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`
              const downloadBlob = async (url: string, label: string) => {
                if (lastQuoteDownloading) return
                setLastQuoteDownloading(true)
                pushToast(`Downloading ${label}…`, 'info')
                try {
                  const t = localStorage.getItem('sanctum_token') || ''
                  const r = await fetch(url, { headers: { Authorization: `Bearer ${t}`, Accept: 'application/pdf' } })
                  if (!r.ok) {
                    pushToast(`Download failed (${r.status}). Regenerate via Generate Quote Sheet.`, 'error')
                    return
                  }
                  const blob = await r.blob()
                  const burl = URL.createObjectURL(blob)
                  const a = document.createElement('a')
                  // force-download (save to disk) instead of opening in a
                  // new tab via the browser's PDF viewer — UX feedback from
                  // staging users wanted explicit local save first, view later.
                  a.href = burl
                  a.download = buildQuoteFilename()
                  a.click()
                  setTimeout(() => URL.revokeObjectURL(burl), 5000)
                } catch (e: any) {
                  pushToast('Download failed: ' + (e?.message || 'network error'), 'error')
                } finally {
                  setLastQuoteDownloading(false)
                  setDownloadMenuOpen(false)
                }
              }
              // Always render as a split-button, even when no Previous exists.
              // The dropdown arrow is part of the button's visual identity so
              // users always see that version history is a first-class concept
              // here — sets the expectation for when a Previous version starts
              // appearing. When no Previous exists yet, the dropdown shows the
              // Latest entry + a disabled "No previous version yet" stub so the
              // dropdown still serves its discovery role.
              return (
                <div className="relative inline-block">
                  <div className="inline-flex rounded overflow-hidden">
                    <button
                      disabled={lastQuoteDownloading}
                      onClick={() => downloadBlob(latestUrl, 'Latest')}
                      className="px-4 py-2 text-white text-sm hover:opacity-90 inline-flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                      style={{ backgroundColor: 'rgb(var(--success-fg))' }}
                      title={lastQuoteDownloading ? 'Fetching PDF…' : 'Download the latest version'}>
                      {lastQuoteDownloading && (
                        <span className="w-3.5 h-3.5 border-2 border-line border-t-transparent rounded-full animate-spin" />
                      )}
                      {lastQuoteDownloading ? 'Downloading…' : '↓ Download Quote Sheet'}
                    </button>
                    <button
                      disabled={lastQuoteDownloading}
                      onClick={() => setDownloadMenuOpen(o => !o)}
                      className="px-2 py-2 text-white text-sm hover:opacity-90 border-l border-status-success-fg disabled:opacity-50 disabled:cursor-not-allowed"
                      style={{ backgroundColor: 'rgb(var(--success-fg))' }}
                      title="Choose version">
                      ▾
                    </button>
                  </div>
                  {downloadMenuOpen && (
                    // z-[100] to beat the sidebar (z-50) and header (z-50)
                    // — same value Header's own dropdown uses. z-50 wasn't
                    // enough: equal z-index + sidebar rendered later in the
                    // DOM = sidebar won the stacking war wherever the
                    // dropdown overlapped (visible on staging as the
                    // dropdown's left edge being clipped by the sidebar).
                    <div className="absolute right-0 mt-1 w-72 bg-surface border border-line rounded-md shadow-lg z-[100]">
                      <button
                        onClick={() => downloadBlob(latestUrl, 'Latest')}
                        className="w-full text-left px-3 py-2 hover:bg-surface-2 text-sm">
                        <div className="font-medium text-ink">↓ Latest</div>
                        <div className="text-xs text-ink-muted mt-0.5">
                          {latestCreator ? `By ${latestCreator}` : 'For this action'}
                          {latestDuration !== null && latestDuration !== undefined && ` · ${fmtSec(latestDuration)}`}
                        </div>
                      </button>
                      {previousAvailable ? (
                        <button
                          onClick={() => downloadBlob(v2QuotePrevious!.download_url!, 'Previous')}
                          className="w-full text-left px-3 py-2 hover:bg-surface-2 text-sm border-t border-line">
                          <div className="font-medium text-ink">↓ Previous</div>
                          <div className="text-xs text-ink-muted mt-0.5">
                            {v2QuotePrevious?.created_by?.name ? `By ${v2QuotePrevious.created_by.name}` : 'Earlier version'}
                            {v2QuotePrevious?.generation_duration_seconds !== null && v2QuotePrevious?.generation_duration_seconds !== undefined && ` · ${fmtSec(v2QuotePrevious.generation_duration_seconds)}`}
                          </div>
                        </button>
                      ) : (
                        <div className="px-3 py-2 text-sm border-t border-line bg-surface-2 cursor-not-allowed">
                          <div className="font-medium text-ink-faint">↓ Previous</div>
                          <div className="text-xs text-ink-faint mt-0.5">No previous version yet</div>
                        </div>
                      )}
                    </div>
                  )}
                </div>
              )
            })()}
            {/* Rate Sheet */}
            <button onClick={async () => {
              setActing(true)
              try {
                const r = await fetch(`${apiBase}/policies/${policyId}/generate-rate-sheet`, { method: 'POST', headers, body: JSON.stringify({ action_id: activeId }) })
                if (r.headers.get('content-type')?.includes('pdf')) {
                  window.open(URL.createObjectURL(await r.blob()), '_blank')
                } else {
                  const d = await r.json(); alert(d.error || d.message || 'Failed')
                }
              } catch (e: any) { alert(e.message) }
              setActing(false)
            }} disabled={acting}
              className="px-4 py-2 bg-primary text-white rounded text-sm hover:bg-primary disabled:opacity-50">
              Rate Sheet
            </button>
            {/* Policy Document */}
            {(() => {
              const blocker = blockerFor('canPrintApp')
              return (
                <button
                  onClick={generatePolicyDoc}
                  disabled={acting || !!blocker}
                  title={blocker ?? undefined}
                  className="px-4 py-2 bg-status-accent-fg text-white rounded text-sm hover:bg-status-accent-fg disabled:opacity-50">
                  Policy Doc
                </button>
              )
            })()}
            {/* Cover Sheet — one page with the client-access QR, posted in
                place of the full pack. Only offered on an ISSUED transaction:
                there is no cover to summarise on a quote. Does NOT depend on
                the Policy Document existing — the QR resolves when the client
                scans, so it starts working as soon as that document exists.

                Hidden without `policy-cover-sheet-share`, so the button is
                never offered and then refused. The admin-role clause mirrors
                AuthGate::canPerform(), which lets ADMIN_ROLES through without
                consulting the permission — without it an admin whose stored
                permission list lacks the row would see no button for an action
                the backend would have allowed. Assign it role-wise or to an
                individual user in /roles; the permission row is seeded by
                2026_09_08_143000.

                Tests `perms.includes(...)` directly rather than hasPerm(),
                whose documented fallback treats an EMPTY stored permission
                list as "allow all". That fallback would show this button to
                every user whose localStorage has no permissions — which is
                the opposite of a gate. Sharing a client's policy document is
                not the place for an allow-by-default. */}
            {selected?.status === 'ISSUED' && (perms.includes('policy-cover-sheet-share') || isAdminOrSuperAdmin) && (
              <button
                onClick={generateCoverSheet}
                disabled={acting}
                title="Generate the one-page cover sheet with the QR code the client scans to download this transaction's policy document"
                className="px-4 py-2 bg-teal-600 text-white rounded text-sm hover:bg-teal-700 disabled:opacity-50">
                {acting ? 'Working…' : '🔒 Cover Sheet'}
              </button>
            )}
            {/* Staging-only OTP readout. Rendered only when the backend
                returned a code, which it never does on production. Lets a
                tester verify the scan flow without the customer's handset. */}
            {coverOtp?.code && (
              <div className="flex items-center gap-2 px-3 py-2 rounded border border-dashed border-amber-500 bg-amber-50 text-amber-900">
                <span className="text-[10px] font-bold uppercase tracking-wider">Staging OTP</span>
                <span className="font-mono text-lg font-bold tracking-widest text-ink">{coverOtp.code}</span>
                {coverOtp.usedAt
                  ? <span className="text-[10px] font-semibold uppercase text-status-danger-fg">used</span>
                  : <span className="text-[10px] font-semibold uppercase text-status-success-fg">live</span>}
                {coverOtp.sentTo && <span className="text-[10px] opacity-70">to {coverOtp.sentTo}</span>}
                <button
                  onClick={loadCoverOtp}
                  disabled={coverOtpLoading}
                  title="Re-read the latest OTP for this policy"
                  className="text-xs underline disabled:opacity-50">
                  {coverOtpLoading ? '…' : 'refresh'}
                </button>
              </div>
            )}
            {/* Policy Doc (Date Range) — additive: generates one stored doc per
                action whose term start falls in [from, to]; disabled while
                generating in the background. Admin / Super Admin only. */}
            {isAdminOrSuperAdmin && (() => {
              const blocker = blockerFor('canPrintApp')
              return (
                <button
                  onClick={() => { setDocRangeFrom(''); setDocRangeTo(''); setShowDocRangeModal(true) }}
                  disabled={acting || docRangeGenerating || !!blocker}
                  title={blocker ?? 'Generate Policy Documents for every action in a date range (stored under Documents)'}
                  className="px-4 py-2 bg-purple-500 text-white rounded text-sm hover:bg-purple-600 disabled:opacity-50">
                  {docRangeGenerating ? 'Generating…' : '📅 Policy Doc (Date Range)'}
                </button>
              )
            })()}
            {/* Export Full Policy as Excel — re-importable for backup/migration */}
            <button onClick={async () => {
              setActing(true)
              try {
                const r = await fetch(`${apiBase}/policies/${policyId}/export-full`, {
                  headers: { Authorization: `Bearer ${localStorage.getItem('sanctum_token')}` },
                })
                if (!r.ok) { alert('Export failed'); setActing(false); return }
                const blob = await r.blob()
                const url = URL.createObjectURL(blob)
                const a = document.createElement('a')
                a.href = url
                const cd = r.headers.get('Content-Disposition') ?? ''
                const m = cd.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/)
                a.download = m ? m[1].replace(/['"]/g, '') : `policy_${policyId}_export.xlsx`
                document.body.appendChild(a); a.click(); document.body.removeChild(a)
                URL.revokeObjectURL(url)
              } catch (e: any) { alert(e.message) }
              setActing(false)
            }} disabled={acting}
              className="px-4 py-2 bg-status-success-fg text-white rounded text-sm hover:bg-status-success-fg disabled:opacity-50"
              title="Download full policy as Excel — re-importable to recreate everything">
              ⬇ Export Excel
            </button>
            {/* Clone Policy — hidden for now. Operator workflow focus is
                Edit Coverages (QUOTE) + endorsement lifecycle. Re-enable
                when clone path has been reviewed end-to-end.
            <button onClick={() => setShowCloneModal(true)} disabled={acting}
              className="px-4 py-2 bg-status-warning-fg text-white rounded text-sm hover:bg-status-warning-fg disabled:opacity-50"
              title="Clone this policy with all coverages, vehicles, items, notes">
              ⊕ Clone Policy
            </button>
            */}
          </div>

          {/* Rate result banner — surfaces the rating outcome inline so the
              user doesn't depend on a popup (popup blockers were hiding it). */}
          {rateResult && (
            <div className={`mt-3 p-3 rounded text-sm border ${
              rateResult.kind === 'ok'
                ? 'bg-status-success-bg border-status-success-fg text-status-success-fg'
                : 'bg-status-danger-bg border-status-danger-fg text-status-danger-fg'
            }`}>
              <div className="flex items-start justify-between gap-3">
                <div>
                  {rateResult.kind === 'ok' ? (
                    <>
                      <div className="font-semibold">Rating complete</div>
                      <div className="mt-1">
                        Annual premium: <b>P {Number(rateResult.annualPremium ?? 0).toLocaleString()}</b>
                        {rateResult.proRatePremium != null && rateResult.proRatePremium !== rateResult.annualPremium && (
                          Number(rateResult.proRatePremium) < 0 ? (
                            // Negative pro-rata = money back to the customer (cancel /
                            // coverage-decrease). Label it clearly as a Refund.
                            <span className="ml-3">Refund: <b>P -{Math.abs(Number(rateResult.proRatePremium)).toLocaleString()}</b></span>
                          ) : (
                            <span className="ml-3">Pro-rated: <b>P {Number(rateResult.proRatePremium).toLocaleString()}</b></span>
                          )
                        )}
                      </div>
                      {rateResult.message && <div className="text-xs mt-1 text-status-warning-fg">{rateResult.message}</div>}
                    </>
                  ) : (
                    <div>
                      <div className="font-semibold">Rating failed</div>
                      <div className="mt-1 text-xs">{rateResult.message}</div>
                    </div>
                  )}
                </div>
                <button onClick={() => setRateResult(null)} className="text-xs opacity-60 hover:opacity-100">✕</button>
              </div>
            </div>
          )}
        </Card>
      )}

      {/* ── Clone Policy Modal ── */}
      {showCloneModal && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-2 sm:p-4 pt-10 overflow-y-auto" onClick={(e) => { if (e.target === e.currentTarget) { setShowCloneModal(false); setCloneResult(null) } }}>
          <div className="bg-surface rounded-xl shadow-xl w-full max-w-2xl my-4 max-h-[85vh] flex flex-col overflow-hidden">
            <div className="flex items-center justify-between px-5 py-3 border-b border-line shrink-0">
              <div>
                <h3 className="text-lg font-semibold text-ink">Clone Policy</h3>
                <p className="text-xs text-ink-faint mt-0.5">Deep-copy all coverages, vehicles, items, extensions, notes to a new policy</p>
              </div>
              <button onClick={() => { setShowCloneModal(false); setCloneResult(null) }} className="text-ink-faint hover:text-ink-muted text-xl">&times;</button>
            </div>

            {!cloneResult ? (
              <>
                <div className="px-5 py-4 space-y-4 overflow-y-auto grow min-h-0">
                  {/* Source summary */}
                  <div className="bg-surface-2 rounded-lg p-3 text-sm">
                    <div className="font-medium text-ink-muted">Source: Policy #{policyId}</div>
                    <div className="text-xs text-ink-muted mt-1">
                      All coverages, subcoverages, extensions, specified items, notes, and vehicles will be deep-copied.
                    </div>
                  </div>

                  {/* Options */}
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-xs font-medium text-ink-muted mb-1">New Policy Start Date</label>
                      <input type="date" value={cloneOpts.term_start_date}
                        onChange={e => setCloneOpts((p: any) => ({ ...p, term_start_date: e.target.value }))}
                        className="w-full px-3 py-2 border border-line rounded text-sm" />
                    </div>
                    <div>
                      <label className="block text-xs font-medium text-ink-muted mb-1">Expiry Date</label>
                      <input type="date" value={cloneOpts.expiry_date}
                        onChange={e => setCloneOpts((p: any) => ({ ...p, expiry_date: e.target.value }))}
                        className="w-full px-3 py-2 border border-line rounded text-sm" />
                    </div>
                  </div>

                  <label className="flex items-center gap-2 text-sm text-ink-muted">
                    <input type="checkbox" checked={cloneOpts.skip_motor}
                      onChange={e => setCloneOpts((p: any) => ({ ...p, skip_motor: e.target.checked }))}
                      className="rounded" />
                    Skip motor vehicles (clone coverages only)
                  </label>

                  <div className="text-xs text-ink-faint bg-status-info-bg rounded p-2">
                    The new policy will be created in <b>Quote</b> status. You can edit coverages/values and then proceed to <b>Rate → Submit → Approve → Issue</b>.
                    <br />To modify via Excel first: export this policy, edit the spreadsheet, then import into the new cloned policy.
                  </div>
                </div>

                {cloneError && <div className="px-5 py-2 bg-status-danger-bg text-status-danger-fg text-sm border-t border-line">{cloneError}</div>}

                <div className="flex justify-end gap-2 px-5 py-3 border-t border-line bg-surface-2 rounded-b-xl shrink-0">
                  <button onClick={() => setShowCloneModal(false)} className="px-4 py-2 text-sm border border-line rounded-md hover:bg-surface-2">Cancel</button>
                  <button disabled={cloning} onClick={async () => {
                    setCloning(true); setCloneError('')
                    try {
                      const r = await apiClient.post(`/policies/${policyId}/clone`, cloneOpts)
                      setCloneResult(r.data)
                    } catch (e: any) {
                      setCloneError(e?.response?.data?.error || e?.response?.data?.message || 'Clone failed')
                    }
                    setCloning(false)
                  }} className="px-5 py-2 text-sm bg-status-warning-fg text-white rounded-md hover:bg-status-warning-fg disabled:opacity-50 font-medium">
                    {cloning ? 'Cloning...' : 'Clone Now'}
                  </button>
                </div>
              </>
            ) : (
              <>
                {/* Clone Result */}
                <div className="px-5 py-4 space-y-4 overflow-y-auto grow min-h-0">
                  <div className="flex items-center gap-2 text-status-success-fg bg-status-success-bg rounded-lg p-3">
                    <span className="text-2xl">✓</span>
                    <div>
                      <div className="font-semibold">{cloneResult.message}</div>
                      <div className="text-sm mt-0.5">New policy: <b>{cloneResult.data?.policy_number}</b> (ID: {cloneResult.data?.policy_id})</div>
                    </div>
                  </div>

                  {/* Clone report */}
                  <div className="border border-line rounded-lg overflow-hidden">
                    <div className="bg-surface-2 px-4 py-2 border-b border-line text-xs font-semibold text-ink-muted uppercase">Clone Report</div>
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-px bg-surface-2">
                      {Object.entries(cloneResult.report ?? {}).filter(([k, v]) => k !== 'errors' && k !== 'source_policy' && (v as number) > 0).map(([key, val]) => (
                        <div key={key} className="bg-surface p-3 text-center">
                          <div className="text-lg font-bold text-ink">{String(val)}</div>
                          <div className="text-[10px] text-ink-muted uppercase">{key.replace(/_/g, ' ')}</div>
                        </div>
                      ))}
                    </div>
                  </div>

                  {cloneResult.report?.errors?.length > 0 && (
                    <div className="bg-status-danger-bg border border-status-danger-fg rounded p-3">
                      <div className="text-xs font-semibold text-status-danger-fg mb-1">Errors during clone:</div>
                      {cloneResult.report.errors.map((e: string, i: number) => <div key={i} className="text-xs text-status-danger-fg">{e}</div>)}
                    </div>
                  )}
                </div>

                <div className="flex justify-end gap-2 px-5 py-3 border-t border-line bg-surface-2 rounded-b-xl shrink-0">
                  <button onClick={() => { setShowCloneModal(false); setCloneResult(null) }} className="px-4 py-2 text-sm border border-line rounded-md hover:bg-surface-2">Close</button>
                  <button onClick={() => window.open(`/policies/${cloneResult.data?.policy_id}/edit`, '_blank')}
                    className="px-5 py-2 text-sm bg-primary text-white rounded-md hover:bg-primary font-medium">
                    Open & Edit New Policy
                  </button>
                </div>
              </>
            )}
          </div>
        </div>
      )}

      {/* ── New Transaction Modal ── */}
      {showNewTxn && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto" onClick={(e) => { if (e.target === e.currentTarget) setShowNewTxn(false) }}>
          <div className="bg-surface rounded-2xl shadow-2xl w-full max-w-lg mx-4 p-5 space-y-3 max-h-[85vh] overflow-y-auto">
            <div className="flex items-center justify-between">
              <h2 className="text-lg font-bold text-ink">New Transaction</h2>
              <button onClick={() => setShowNewTxn(false)} className="text-ink-faint hover:text-ink-muted text-xl">✕</button>
            </div>
            <div className="space-y-3">
              <div>
                <label className="block text-xs font-medium text-ink-muted mb-1">Select Transaction Type *</label>
                <select value={txnForm.transaction_type} onChange={e => onNewTxnTypeChange(e.target.value)}
                  className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary">
                  <option value="">— Select —</option>
                  {allowedTxnTypes.map(t => <option key={t.id} value={t.id}>{t.name}</option>)}
                </select>
              </div>
              <div>
                <label className="block text-xs font-medium text-ink-muted mb-1">Transaction Reason *</label>
                <select value={txnForm.transaction_reason} onChange={e => setTxnForm(p => ({ ...p, transaction_reason: e.target.value }))}
                  className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary">
                  <option value="">— Select —</option>
                  {txnSubTypes.map(t => <option key={t.id} value={t.id}>{t.name}</option>)}
                </select>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-ink-muted mb-1">Effective From *</label>
                  <input type="date" value={txnForm.effective_from}
                    max={txnForm.effective_to || undefined}
                    onChange={e => setTxnForm(p => ({ ...p, effective_from: e.target.value }))}
                    className="w-full px-3 py-2 border border-line rounded text-sm" />
                </div>
                <div>
                  <label className="block text-xs font-medium text-ink-muted mb-1">
                    Effective To *
                    {txnForm.transaction_type === 'ENDORSE' && (
                      <span className="ml-1 text-[10px] text-ink-faint font-normal">(locked for ENDORSE)</span>
                    )}
                  </label>
                  <input type="date" value={txnForm.effective_to}
                    min={txnForm.effective_from || undefined}
                    readOnly={txnForm.transaction_type === 'ENDORSE'}
                    onChange={e => setTxnForm(p => ({ ...p, effective_to: e.target.value }))}
                    className={`w-full px-3 py-2 border border-line rounded text-sm ${txnForm.transaction_type === 'ENDORSE' ? 'bg-surface-2 text-ink-muted cursor-not-allowed' : ''}`} />
                </div>
              </div>
              <div>
                <label className="block text-xs font-medium text-ink-muted mb-1">Transaction Date *</label>
                <input type="date" value={txnForm.transaction_date}
                  onChange={e => setTxnForm(p => ({ ...p, transaction_date: e.target.value }))}
                  className="w-full px-3 py-2 border border-line rounded text-sm" />
              </div>
              <div>
                <label className="block text-xs font-medium text-ink-muted mb-1">Transaction Notes</label>
                <textarea value={txnForm.note} onChange={e => setTxnForm(p => ({ ...p, note: e.target.value }))}
                  className="w-full px-3 py-2 border border-line rounded text-sm" rows={3} placeholder="Transaction Notes" />
              </div>
            </div>
            <div className="flex gap-2 pt-2 border-t border-line">
              <button onClick={handleNewTxn} disabled={acting}
                className="flex-1 py-2 bg-primary text-white rounded text-sm font-medium hover:bg-primary disabled:opacity-50">
                {acting ? 'Creating…' : 'Submit'}
              </button>
              <button onClick={() => setShowNewTxn(false)} className="px-4 py-2 border border-line rounded text-sm hover:bg-surface-2">Close</button>
            </div>
          </div>
        </div>
      )}

      {/* ── Change Summary Modal (read-only Endorse/Cancel diagnostic) ── */}
      {showChangeSummary && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto" onClick={(e) => { if (e.target === e.currentTarget) setShowChangeSummary(false) }}>
          <div className="bg-surface rounded-2xl shadow-2xl w-full max-w-5xl mx-4 max-h-[88vh] overflow-hidden flex flex-col">
            <div className="flex items-center justify-between px-5 py-3" style={{ backgroundColor: '#0D1B2A' }}>
              <h2 className="text-lg font-bold text-white">
                Endorse / Cancel Change Summary
                <span className="ml-2 text-xs font-normal" style={{ color: '#F4A623' }}>(read-only)</span>
              </h2>
              <button onClick={() => setShowChangeSummary(false)} className="text-white hover:opacity-80 text-xl">✕</button>
            </div>

            <div className="p-5 space-y-4 overflow-y-auto">
              {csLoading && <div className="text-sm text-ink-muted py-6 text-center">Loading change summary…</div>}

              {!csLoading && csError && (
                <div className="px-4 py-3 rounded text-sm bg-status-danger-bg text-status-danger-fg">{csError}</div>
              )}

              {!csLoading && !csError && (
                <>
                  {/* Reconciliation math */}
                  {csMath && Object.keys(csMath).length > 0 && (
                    <div className="rounded-lg border border-line p-4 text-sm space-y-2">
                      <div className="flex flex-wrap items-center gap-2">
                        <span className="font-bold text-ink">{csMath.type}</span>
                        <span className="text-ink-muted">·</span>
                        <span className="text-ink-muted">Factor</span>
                        <code className="px-1.5 py-0.5 rounded bg-surface-2 text-ink">{csMath.factor_formula}</code>
                        <span className="text-ink">= {csMath.numerator} ÷ {csMath.denominator} = <b>{Number(csMath.factor ?? 0).toFixed(6)}</b></span>
                      </div>
                      <div className="grid grid-cols-2 md:grid-cols-4 gap-x-4 gap-y-1 text-xs text-ink-muted">
                        {csMath.new_from && <div>New period: <span className="text-ink">{csMath.new_from} → {csMath.new_to}</span></div>}
                        {csMath.prev_from && <div>Prev period: <span className="text-ink">{csMath.prev_from} → {csMath.prev_to}</span></div>}
                        {csMath.prev_label && <div className="col-span-2">Baseline: <span className="text-ink">{csMath.prev_label}</span></div>}
                        {csMath.cancel_from && <div>Cancel from: <span className="text-ink">{csMath.cancel_from}</span></div>}
                        {csMath.period_from && <div>Period: <span className="text-ink">{csMath.period_from} → {csMath.period_to}</span></div>}
                        {csMath.freq != null && <div>Freq: <span className="text-ink">{csMath.freq}</span></div>}
                        {csMath.basis_label && <div>{csMath.basis_label}: <span className="text-ink">{Number(csMath.basis_value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span></div>}
                      </div>
                      {/* Explicit pro-rata formula spelled out for UW:
                          (numerator ÷ denominator) × basis = recomputed */}
                      <div className="rounded-md bg-surface-2 px-4 py-3" style={{ borderLeft: '4px solid #F4A623' }}>
                        <div className="text-xs uppercase tracking-wide text-ink-muted mb-1">How the pro-rata is calculated</div>
                        <div className="text-base text-ink">
                          Pro-rata ={' '}
                          <b>{csMath.numerator}</b> ÷ <b>{csMath.denominator}</b>
                          {' '}× <b>({Number(csMath.basis_value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })})</b>
                          {' '}= <b>{Number(csMath.recomputed ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</b>
                        </div>
                        <div className="text-xs text-ink-muted mt-1">
                          days elapsed ÷ days in period × {csMath.basis_label ? csMath.basis_label.toLowerCase() : 'basis'}
                          {' '}&nbsp;·&nbsp; factor {Number(csMath.factor ?? 0).toFixed(6)}
                        </div>
                      </div>
                      <div className="flex flex-wrap items-center gap-3 pt-2 border-t border-line">
                        <span className="text-xs text-ink-muted">Recomputed <b className="text-ink">{Number(csMath.recomputed ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</b></span>
                        <span className="text-xs text-ink-muted">Stored <b className="text-ink">{Number(csMath.stored ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</b></span>
                        <span className={`px-2 py-0.5 rounded text-xs font-semibold ${csMath.reconciles ? 'bg-status-success-bg text-status-success-fg' : 'bg-status-danger-bg text-status-danger-fg'}`}>
                          {csMath.reconciles ? '✓ Reconciles' : '✗ Does not reconcile'}
                        </span>
                      </div>
                    </div>
                  )}

                  {/* Change rows */}
                  {csRows.length === 0 ? (
                    <div className="text-sm text-ink-muted py-4 text-center">No changed lines found for this action.</div>
                  ) : (
                    <div className="overflow-x-auto rounded-lg border border-line">
                      <table className="w-full text-sm">
                        <thead>
                          <tr className="text-left text-xs uppercase tracking-wide" style={{ backgroundColor: '#0D1B2A', color: '#fff' }}>
                            <th className="px-3 py-2">Section</th>
                            <th className="px-3 py-2">Detail</th>
                            <th className="px-3 py-2">Change</th>
                            <th className="px-3 py-2">What changed</th>
                            <th className="px-3 py-2 text-right">Value</th>
                            <th className="px-3 py-2 text-right" title="Annual value change (new − old)">Δ</th>
                            <th className="px-3 py-2 text-right" title="Δ × factor (pro-rata for this line)">Pro-rata</th>
                          </tr>
                        </thead>
                        <tbody>
                          {csRows.map((row, i) => (
                            <tr key={`${row.bucket}-${row.line_id}-${i}`} className="border-t border-line">
                              <td className="px-3 py-2 text-ink">{row.name}</td>
                              <td className="px-3 py-2 text-ink-muted">{row.detail || '—'}</td>
                              <td className="px-3 py-2">
                                <span className={`px-2 py-0.5 rounded text-xs font-semibold ${
                                  row.change_type === 'ADDED' ? 'bg-status-success-bg text-status-success-fg'
                                    : row.change_type === 'DELETED' ? 'bg-status-danger-bg text-status-danger-fg'
                                      : 'bg-status-info-bg text-primary'}`}>
                                  {row.change_type}
                                </span>
                              </td>
                              <td className="px-3 py-2 text-ink-muted">{row.what_changed}</td>
                              <td className="px-3 py-2 text-right text-ink">{Number(row.calc_value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                              <td className={`px-3 py-2 text-right ${Number(row.delta) < 0 ? 'text-status-danger-fg' : 'text-ink'}`}>{Number(row.delta ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                              <td className={`px-3 py-2 text-right font-medium ${Number(row.pro_rata) < 0 ? 'text-status-danger-fg' : 'text-ink'}`}>{Number(row.pro_rata ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  )}
                </>
              )}
            </div>

            <div className="flex justify-end gap-2 px-5 py-3 border-t border-line">
              <button onClick={() => setShowChangeSummary(false)} className="px-4 py-2 border border-line rounded text-sm hover:bg-surface-2">Close</button>
            </div>
          </div>
        </div>
      )}

      {/* ── Edit Transaction Modal ── */}
      {showEditTxn && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto" onClick={(e) => { if (e.target === e.currentTarget) setShowEditTxn(false) }}>
          <div className="bg-surface rounded-2xl shadow-2xl w-full max-w-lg mx-4 p-5 space-y-3 max-h-[85vh] overflow-y-auto">
            <div className="flex items-center justify-between">
              <h2 className="text-lg font-bold text-ink">Edit Current Transaction</h2>
              <button onClick={() => setShowEditTxn(false)} className="text-ink-faint hover:text-ink-muted text-xl">✕</button>
            </div>
            <div className="space-y-3">
              <div>
                <label className="block text-xs font-medium text-ink-muted mb-1">Transaction Type</label>
                {/* Editable for EVERY transaction type, on QUOTE and on an
                    already-ISSUED action — a mis-keyed type can be corrected
                    in place instead of unissuing the whole transaction.
                    Changing the type clears the now-mismatched reason and
                    reloads the sub-type list for the new type.
                    Money: the backend only re-rates a QUOTE action
                    (actionRepriceable). On an ISSUED action the type change is
                    a re-label — premium and invoices stay exactly as issued. */}
                <select value={editTxnForm.transaction_type}
                  onChange={e => {
                    const t = e.target.value
                    setEditTxnForm(p => ({ ...p, transaction_type: t, transaction_reason: '' }))
                    loadEditTxnSubTypes(t)
                  }}
                  className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary">
                  {EDITABLE_TXN_TYPES.map(t => <option key={t} value={t}>{t}</option>)}
                  {/* Preserve current type even if outside the editable set */}
                  {editTxnForm.transaction_type && !EDITABLE_TXN_TYPES.includes(editTxnForm.transaction_type) && (
                    <option value={editTxnForm.transaction_type}>{editTxnForm.transaction_type}</option>
                  )}
                </select>
                {selected?.status && selected.status !== 'QUOTE' && (
                  <p className="mt-1 text-xs text-ink-faint">
                    {selected.status} action — changing the type re-labels this transaction only.
                    The premium and any issued invoice are not recalculated.
                  </p>
                )}
                {/* Full-period types: a date correction never re-rates, so the
                    verbatim renewal / UW-overridden specialist premium is kept.
                    Use Rate afterwards if the premium really must change. */}
                {['NEWBUSINESS', 'RENEW', 'ANNIVERSARY-RENEW', 'ENDORSE-RENEW', 'REINSTATE', 'REISSUE', 'EXPIRE'].includes(editTxnForm.transaction_type) && (
                  <p className="mt-1 text-xs text-ink-faint">
                    Dates on a {editTxnForm.transaction_type} are corrected as-is — the premium is not recalculated. Use <strong>Rate</strong> if it needs to change.
                  </p>
                )}
              </div>
              <div>
                <label className="block text-xs font-medium text-ink-muted mb-1">Transaction Reason</label>
                <select value={editTxnForm.transaction_reason} onChange={e => setEditTxnForm(p => ({ ...p, transaction_reason: e.target.value }))}
                  className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary">
                  <option value="">— Select —</option>
                  {editTxnSubTypes.map(t => <option key={t.id} value={t.id}>{t.name}</option>)}
                  {/* Preserve existing reason even if not in subtypes list */}
                  {editTxnForm.transaction_reason && !editTxnSubTypes.find(t => t.id === editTxnForm.transaction_reason) && (
                    <option value={editTxnForm.transaction_reason}>{editTxnForm.transaction_reason}</option>
                  )}
                </select>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-ink-muted mb-1">Effective From *</label>
                  <input type="date" value={editTxnForm.effective_from}
                    onChange={e => setEditTxnForm(p => ({ ...p, effective_from: e.target.value }))}
                    className="w-full px-3 py-2 border border-line rounded text-sm" />
                </div>
                <div>
                  <label className="block text-xs font-medium text-ink-muted mb-1">Effective To *</label>
                  <input type="date" value={editTxnForm.effective_to}
                    onChange={e => setEditTxnForm(p => ({ ...p, effective_to: e.target.value }))}
                    className="w-full px-3 py-2 border border-line rounded text-sm" />
                </div>
              </div>
              <div>
                <label className="block text-xs font-medium text-ink-muted mb-1">Transaction Date</label>
                <input type="date" value={editTxnForm.transaction_date}
                  onChange={e => setEditTxnForm(p => ({ ...p, transaction_date: e.target.value }))}
                  className="w-full px-3 py-2 border border-line rounded text-sm" />
              </div>
              <div>
                <label className="block text-xs font-medium text-ink-muted mb-1">Transaction Notes</label>
                <textarea value={editTxnForm.note} onChange={e => setEditTxnForm(p => ({ ...p, note: e.target.value }))}
                  className="w-full px-3 py-2 border border-line rounded text-sm" rows={3} />
              </div>
            </div>
            <div className="flex gap-2 pt-2 border-t border-line">
              <button onClick={handleEditTxn} disabled={acting}
                className="flex-1 py-2 bg-primary text-white rounded text-sm font-medium hover:bg-primary disabled:opacity-50">
                {acting ? 'Saving…' : 'Save Changes'}
              </button>
              <button onClick={() => setShowEditTxn(false)} className="px-4 py-2 border border-line rounded text-sm hover:bg-surface-2">Cancel</button>
            </div>
          </div>
        </div>
      )}

      {/* ── V2 Quote Sheet — cross-user awareness banner ──
          Renders only when there's a recent v2_pdf_jobs row for this policy.
          Shows requester name, policy number, elapsed time, current status.
          Disappears when the user dismisses (only for terminal states) or
          when a fresh idle poll returns null. */}
      {v2QuoteJob && (v2QuoteJob.is_in_flight || v2QuoteJob.status === 'completed' || v2QuoteJob.status === 'failed' || v2QuoteJob.is_stale) && (() => {
        const isMine = v2QuoteJob.created_by?.is_current_user === true
        const creatorLabel = v2QuoteJob.created_by
          ? (isMine ? 'you' : v2QuoteJob.created_by.name)
          : 'system'
        // Policy number comes from the API (canonical) — falls back to local
        // state if not yet populated (first poll race).
        const policyNo = v2QuoteJob.policy_number ?? v2QuotePolicyNumber ?? `policy #${policyId}`
        // Helpers: human-friendly seconds → "6s" / "1:23"
        const fmtSec = (s: number) => s < 60 ? `${s}s` : `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`
        const fmtClock = (iso: string) => {
          try { return new Date(iso).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }) }
          catch { return iso }
        }
        // This banner tracks the newest job for the POLICY, and Policy Doc
        // shares the same pipeline (document_title = 'POLICY DOCUMENT'). Name
        // the document the job actually is — labelling a policy schedule "V2
        // Quote Sheet ready" is what sent people to the Download Quote Sheet
        // button expecting a quote that had never been generated.
        const docLabel = v2QuoteJob.document_title
          ? (v2QuoteJob.document_title === 'POLICY DOCUMENT' ? 'Policy Document' : v2QuoteJob.document_title)
          : 'V2 Quote Sheet'
        const elapsed = v2QuoteJob.elapsed_seconds ?? 0
        const elapsedStr = fmtSec(elapsed)
        const duration = v2QuoteJob.generation_duration_seconds
        // Colour treatment per state — green=ready, blue=in-flight, amber=stale, red=failed
        let bgClass = 'bg-status-info-bg border-primary text-primary'
        let icon = <span className="w-4 h-4 border-2 border-primary border-t-transparent rounded-full animate-spin" />
        let headline = ''
        if (v2QuoteJob.status === 'completed') {
          bgClass = 'bg-status-success-bg border-status-success-fg text-status-success-fg'
          icon = <span className="text-status-success-fg text-lg">✓</span>
          headline = `${docLabel} ready for ${policyNo}`
        } else if (v2QuoteJob.status === 'failed' || v2QuoteJob.status === 'cancelled') {
          bgClass = 'bg-status-danger-bg border-status-danger-fg text-status-danger-fg'
          icon = <span className="text-status-danger-fg text-lg">✕</span>
          headline = `${docLabel} generation ${v2QuoteJob.status} for ${policyNo}`
        } else if (v2QuoteJob.is_stale) {
          bgClass = 'bg-status-warning-bg border-status-warning-fg text-status-warning-fg'
          icon = <span className="text-status-warning-fg text-lg">⚠</span>
          headline = `${docLabel} appears stuck — last heartbeat ${elapsedStr} ago`
        } else if (isMine) {
          headline = `Generating ${docLabel} for ${policyNo}`
        } else {
          headline = `${v2QuoteJob.created_by?.name ?? 'Another user'} is generating ${docLabel} for ${policyNo}`
          icon = <span className="text-primary text-lg">ℹ</span>
        }
        // Subline: completed → "Generated by X · Took 6s · Ready at 14:47:36"
        //          in-flight → "Started by X · 0:23 elapsed"
        //          stale    → "Started by X · click below to retry"
        const subline = v2QuoteJob.status === 'completed'
          ? `Generated by ${creatorLabel}` +
            (duration !== null ? ` · Took ${fmtSec(duration)}` : '') +
            ` · Ready at ${fmtClock(v2QuoteJob.updated_at)}` +
            (v2QuoteJob.message ? ` · ${v2QuoteJob.message}` : '')
          : v2QuoteJob.is_stale
            ? `Started by ${creatorLabel} · click below to retry`
            : `Started by ${creatorLabel} · ${elapsedStr} elapsed` + (v2QuoteJob.message ? ` · ${v2QuoteJob.message}` : '')
        // Previous-version slot: only when current is in-flight and we have
        // a prior completed job. Lets the user grab the last good PDF while
        // the new one is regenerating.
        const showPrevious = v2QuoteJob.is_in_flight && v2QuotePrevious && v2QuotePrevious.download_url
        const prevCreator = v2QuotePrevious?.created_by
          ? (v2QuotePrevious.created_by.is_current_user ? 'you' : v2QuotePrevious.created_by.name)
          : 'system'
        const prevDuration = v2QuotePrevious?.generation_duration_seconds ?? null
        return (
          <div className={`px-4 py-3 rounded-lg text-sm border border-line ${bgClass} mb-2`}>
            <div className="flex items-start justify-between">
              <div className="flex items-start gap-2 min-w-0">
                <div className="pt-0.5">{icon}</div>
                <div className="min-w-0">
                  <div className="font-medium">{headline}</div>
                  <div className="text-xs opacity-80 mt-0.5">{subline}</div>
                </div>
              </div>
              <div className="flex items-center gap-2 ml-3 shrink-0">
                {/* Banner is STATUS-ONLY — no inline Download. The persistent
                    "Download Quote Sheet" split-button below the action row is
                    the single download surface. This kills the redundancy
                    where two buttons were doing the same thing. */}
                {(v2QuoteJob.status === 'completed' || v2QuoteJob.status === 'failed' || v2QuoteJob.status === 'cancelled' || v2QuoteJob.is_stale) && (
                  <button onClick={() => setV2QuoteJob(null)} className="text-ink-faint hover:text-ink-muted" title="Dismiss">✕</button>
                )}
              </div>
            </div>
            {showPrevious && v2QuotePrevious && (
              <div className="mt-2 pt-2 border-t border-primary flex items-center justify-between text-xs opacity-90">
                <div className="min-w-0">
                  <span className="font-medium">Last version available</span>
                  <span className="opacity-80">
                    {' · Generated by '}{prevCreator}
                    {prevDuration !== null && <> · Took {fmtSec(prevDuration)}</>}
                    {' · '}{fmtClock(v2QuotePrevious.updated_at)}
                  </span>
                </div>
                <button
                  disabled={v2QuotePreviousDownloading}
                  onClick={async () => {
                    if (v2QuotePreviousDownloading) return
                    setV2QuotePreviousDownloading(true)
                    try {
                      const t = localStorage.getItem('sanctum_token') || ''
                      const r = await fetch(v2QuotePrevious.download_url!, {
                        headers: { Authorization: `Bearer ${t}`, Accept: 'application/pdf' },
                      })
                      if (!r.ok) {
                        alert(`Download failed (${r.status}). Wait for current generation to complete.`)
                        return
                      }
                      const blob = await r.blob()
                      const url = URL.createObjectURL(blob)
                      const a = document.createElement('a')
                      // force-download (save to disk) instead of opening in
                      // a new browser-viewer tab — explicit save first, view
                      // after on user's terms.
                      a.href = url
                      a.download = buildQuoteFilename()
                      a.click()
                      setTimeout(() => URL.revokeObjectURL(url), 5000)
                    } catch (e: any) {
                      alert('Download failed: ' + (e?.message || 'network error'))
                    } finally {
                      setV2QuotePreviousDownloading(false)
                    }
                  }}
                  className="ml-3 px-3 py-1 bg-primary text-white rounded text-xs font-medium hover:bg-primary inline-flex items-center gap-1.5 disabled:opacity-60 disabled:cursor-not-allowed shrink-0">
                  {v2QuotePreviousDownloading && <span className="w-3 h-3 border-2 border-line border-t-transparent rounded-full animate-spin" />}
                  {v2QuotePreviousDownloading ? 'Downloading…' : '↓ Download last version'}
                </button>
              </div>
            )}
          </div>
        )
      })()}

      {/* ── PDF Generation Notification ── */}
      {pdfNotif && (
        <div className={`flex items-center justify-between px-4 py-3 rounded-lg text-sm ${
          pdfNotif.status === 'generating' ? 'bg-status-info-bg border border-primary text-primary' :
          pdfNotif.status === 'completed' ? 'bg-status-success-bg border border-status-success-fg text-status-success-fg' :
          'bg-status-danger-bg border border-status-danger-fg text-status-danger-fg'
        }`}>
          <div className="flex items-center gap-2">
            {pdfNotif.status === 'generating' && <span className="w-4 h-4 border-2 border-primary border-t-transparent rounded-full animate-spin" />}
            {pdfNotif.status === 'completed' && <span className="text-status-success-fg text-lg">✓</span>}
            {pdfNotif.status === 'failed' && <span className="text-status-danger-fg text-lg">✕</span>}
            <span>{pdfNotif.message}</span>
          </div>
          <div className="flex items-center gap-2">
            {pdfNotif.status === 'completed' && pdfNotif.url && (
              <button onClick={async () => {
                // IMPORTANT: do NOT send Accept: application/json — the server
                // returns either a PDF binary or JSON 404, and an Accept: json
                // header made it return JSON even when the PDF existed, so
                // the button silently no-oped. Use application/pdk OR omit it
                // so any successful response is read as a blob.
                try {
                  const r = await fetch(pdfNotif.url!, {
                    headers: {
                      Authorization: `Bearer ${localStorage.getItem('sanctum_token')}`,
                      Accept: 'application/pdf',
                    }
                  })
                  if (r.ok) {
                    const blob = await r.blob()
                    const url = URL.createObjectURL(blob)
                    // Use a download link so it actually downloads instead of
                    // being blocked by popup blockers on some browsers.
                    const a = document.createElement('a')
                    a.href = url
                    a.target = '_blank'
                    a.rel = 'noopener noreferrer'
                    a.click()
                    setTimeout(() => URL.revokeObjectURL(url), 5000)
                  } else {
                    const msg = await r.text().catch(() => '')
                    alert(`Download failed (${r.status}). ${msg.slice(0, 200)}`)
                  }
                } catch (e: any) {
                  alert('Download failed: ' + (e?.message || 'network error'))
                }
              }} className="px-3 py-1 bg-status-success-fg text-white rounded text-xs font-medium hover:bg-status-success-fg">
                Download PDF
              </button>
            )}
            <button onClick={() => setPdfNotif(null)} className="text-ink-faint hover:text-ink-muted">✕</button>
          </div>
        </div>
      )}

      {/* ── Full Coverage View for Selected Action (read-only) ──
          Edits are driven entirely by the "✎ Edit Coverages" button
          above, which opens the wizard scoped to the selected action.
          The embedded view just renders the current state of the action
          so operators can see what's attached before editing. */}
      <CoveragesTab policyId={policyId} productId={productId} overrideActionId={activeId ?? undefined} readOnly />

      {/* ── Action History Table ── */}
      <Card title="Action History">
        <DataTable
          columns={['Type', 'Status', 'Quote No', 'Premium', 'Effective From', 'Effective To', 'Reason', 'Note', 'Date']}
          rows={([...allActions].sort((a: any, b: any) => {
            const da = a.effectiveFrom ? new Date(a.effectiveFrom).getTime() : 0
            const db = b.effectiveFrom ? new Date(b.effectiveFrom).getTime() : 0
            if (db !== da) return db - da
            return (b.id || 0) - (a.id || 0)
          }).map((a: any) => [
            a.transactionType,
            a.status,
            a.policyQuoteNo || '—',
            a.premium != null && a.premium !== '' ? fmtPula(a.premium) : '—',
            a.effectiveFrom ? new Date(a.effectiveFrom).toLocaleDateString('en-GB') : '—',
            a.effectiveTo ? new Date(a.effectiveTo).toLocaleDateString('en-GB') : '—',
            a.transactionReason || '—',
            a.note || '—',
            fmtDate(a.createdAt),
          ]) || [])}
        />
      </Card>

      {/* ── Reinstate Modal ── */}
      {showReinstate && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto" onClick={(e) => { if (e.target === e.currentTarget) setShowReinstate(false) }}>
          <div className="bg-surface rounded-2xl shadow-2xl w-full max-w-lg mx-4 p-5 space-y-3 max-h-[85vh] overflow-y-auto">
            <div className="flex items-center justify-between">
              <h2 className="text-lg font-bold text-ink">Reinstate Policy</h2>
              <button onClick={() => setShowReinstate(false)} className="text-ink-faint hover:text-ink-muted text-xl">✕</button>
            </div>
            {reinstateInfo && (
              <div className="bg-surface-2 rounded-lg p-3 text-sm space-y-1">
                <div className="flex justify-between"><span className="text-ink-muted">Policy No</span><span className="font-medium">{reinstateInfo.policyInfo?.policyNumber || '—'}</span></div>
                <div className="flex justify-between"><span className="text-ink-muted">Customer</span><span className="font-medium">{reinstateInfo.customer?.firstName} {reinstateInfo.customer?.lastName}</span></div>
                {reinstateInfo.premium?.monthly && <div className="flex justify-between"><span className="text-ink-muted">Monthly Premium</span><span className="font-bold text-primary">P {Number(String(reinstateInfo.premium.monthly).replace(/,/g, '')).toLocaleString()}</span></div>}
                {reinstateInfo.balance && <div className="flex justify-between border-t border-line pt-2 mt-2"><span className="text-ink-muted font-medium">Arrears Balance</span><span className="font-bold text-status-danger-fg">P {Number(String(reinstateInfo.balance).replace(/,/g, '')).toLocaleString()}</span></div>}
              </div>
            )}
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="block text-xs text-ink-muted mb-1">Payment Method *</label>
                <select value={reinstateForm.paymentMethod} onChange={e => setReinstateForm(p => ({ ...p, paymentMethod: e.target.value }))} className="w-full px-3 py-2 border border-line rounded text-sm">
                  <option value="Cash">Cash</option><option value="RealPay">RealPay</option><option value="DPO">DPO</option>
                </select>
              </div>
              <div>
                <label className="block text-xs text-ink-muted mb-1">Frequency *</label>
                <select value={reinstateForm.paymentFreq} onChange={e => setReinstateForm(p => ({ ...p, paymentFreq: e.target.value }))} className="w-full px-3 py-2 border border-line rounded text-sm">
                  <option value="1">Monthly</option><option value="2">3-Instalment</option><option value="3">Annual</option>
                </select>
              </div>
              <div>
                <label className="block text-xs text-ink-muted mb-1">Payment Date *</label>
                <input type="date" value={reinstateForm.paymentDate} onChange={e => setReinstateForm(p => ({ ...p, paymentDate: e.target.value }))} className="w-full px-3 py-2 border border-line rounded text-sm" />
              </div>
              <div>
                <label className="block text-xs text-ink-muted mb-1">Next Billing Day *</label>
                <input type="date" value={reinstateForm.billingDay} onChange={e => setReinstateForm(p => ({ ...p, billingDay: e.target.value }))} className="w-full px-3 py-2 border border-line rounded text-sm" />
              </div>
            </div>
            <div className="flex gap-2 pt-2 border-t border-line">
              <button onClick={async () => {
                if (!reinstateForm.paymentDate || !reinstateForm.billingDay) { alert('Fill all required fields'); return }
                setActing(true)
                try {
                  const r = await fetch(`${apiBase}/PolicyReinstate`, { method: 'POST', headers, body: JSON.stringify({ policy_id: policyId, policyID: policyId, paymentMethod: reinstateForm.paymentMethod, paymentFreq: Number(reinstateForm.paymentFreq), paymentDate: reinstateForm.paymentDate, billingDay: reinstateForm.billingDay }) })
                  const d = await r.json()
                  if (!r.ok) { alert(d.message || 'Reinstate failed'); setActing(false); return }
                  alert('Policy reinstated successfully'); setShowReinstate(false); refetch()
                } catch (e: any) { alert(e.message) }
                setActing(false)
              }} disabled={acting} className="flex-1 py-2 bg-status-success-fg text-white rounded text-sm font-medium hover:bg-status-success-fg disabled:opacity-50">
                {acting ? 'Processing…' : 'Confirm Reinstatement'}
              </button>
              <button onClick={() => setShowReinstate(false)} className="px-4 py-2 border border-line rounded text-sm hover:bg-surface-2">Cancel</button>
            </div>
          </div>
        </div>
      )}

      {/* ── Renew Policy Modal ── */}
      {showRenew && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto" onClick={(e) => { if (e.target === e.currentTarget) setShowRenew(false) }}>
          <div className="bg-surface rounded-2xl shadow-2xl w-full max-w-lg mx-4 p-5 space-y-3 max-h-[85vh] overflow-y-auto">
            <div className="flex items-center justify-between">
              <h2 className="text-lg font-bold text-ink">Renew Policy</h2>
              <button onClick={() => setShowRenew(false)} className="text-ink-faint hover:text-ink-muted text-xl">✕</button>
            </div>
            <p className="text-sm text-ink-muted">Creates a new annual renewal (ANNIVERSARY-RENEW) transaction. Dates are pre-filled from the current policy period.</p>
            <div className="space-y-3">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-ink-muted mb-1">Renewal From *</label>
                  <input type="date" value={renewForm.effective_from}
                    onChange={e => setRenewForm(p => ({ ...p, effective_from: e.target.value }))}
                    className="w-full px-3 py-2 border border-line rounded text-sm" />
                </div>
                <div>
                  <label className="block text-xs font-medium text-ink-muted mb-1">Renewal To *</label>
                  <input type="date" value={renewForm.effective_to}
                    onChange={e => setRenewForm(p => ({ ...p, effective_to: e.target.value }))}
                    className="w-full px-3 py-2 border border-line rounded text-sm" />
                </div>
              </div>
              <div>
                <label className="block text-xs font-medium text-ink-muted mb-1">Transaction Date *</label>
                <input type="date" value={renewForm.transaction_date}
                  onChange={e => setRenewForm(p => ({ ...p, transaction_date: e.target.value }))}
                  className="w-full px-3 py-2 border border-line rounded text-sm" />
              </div>
              <div>
                <label className="block text-xs font-medium text-ink-muted mb-1">Notes</label>
                <textarea value={renewForm.note} onChange={e => setRenewForm(p => ({ ...p, note: e.target.value }))}
                  className="w-full px-3 py-2 border border-line rounded text-sm" rows={2} placeholder="Optional renewal notes" />
              </div>
            </div>
            <div className="flex gap-2 pt-2 border-t border-line">
              <button onClick={async () => {
                if (!renewForm.effective_from || !renewForm.effective_to || !renewForm.transaction_date) {
                  alert('Please fill all required fields'); return
                }
                await doAction('new-transaction', {
                  transaction_type: 'ANNIVERSARY-RENEW',
                  transaction_reason: 'ANNIVERSARY-RENEW',
                  effective_from: renewForm.effective_from,
                  effective_to: renewForm.effective_to,
                  transaction_date: renewForm.transaction_date,
                  note: renewForm.note,
                })
                setShowRenew(false)
              }} disabled={acting}
                className="flex-1 py-2 bg-status-success-fg text-white rounded text-sm font-medium hover:bg-status-success-fg disabled:opacity-50">
                {acting ? 'Creating…' : 'Create Renewal'}
              </button>
              <button onClick={() => setShowRenew(false)} className="px-4 py-2 border border-line rounded text-sm hover:bg-surface-2">Cancel</button>
            </div>
          </div>
        </div>
      )}

      {/* ── Endorsement Modal ── */}
      {showEndorse && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto" onClick={(e) => { if (e.target === e.currentTarget) setShowEndorse(false) }}>
          <div className="bg-surface rounded-2xl shadow-2xl w-full max-w-lg mx-4 p-5 space-y-3 max-h-[85vh] overflow-y-auto">
            <div className="flex items-center justify-between">
              <h2 className="text-lg font-bold text-ink">Endorse Policy</h2>
              <button onClick={() => setShowEndorse(false)} className="text-ink-faint hover:text-ink-muted text-xl">✕</button>
            </div>
            <p className="text-sm text-ink-muted">An endorsement records a formal amendment to this policy. The reason code drives downstream reporting and must match the <code className="text-[11px] bg-surface-2 px-1 rounded">tb_prtransubtypes</code> catalogue.</p>
            <div className="space-y-3">
              <div>
                <label className="block text-xs text-ink-muted mb-1">Reason *</label>
                <select value={endorseForm.transaction_reason}
                  onChange={e => setEndorseForm(p => ({ ...p, transaction_reason: e.target.value }))}
                  className="w-full px-3 py-2 border border-line rounded text-sm bg-surface">
                  <option value="">Select endorsement reason…</option>
                  {endorseSubTypes.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
                </select>
                {endorseSubTypes.length === 0 && (
                  <p className="text-[11px] text-status-warning-fg mt-1">No endorsement sub-types configured in <code>tb_prtransubtypes</code> for TranTypeCode=ENDORSE.</p>
                )}
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs text-ink-muted mb-1">Effective From *</label>
                  <input type="date" value={endorseForm.effective_from} onChange={e => setEndorseForm(p => ({ ...p, effective_from: e.target.value }))} className="w-full px-3 py-2 border border-line rounded text-sm" />
                </div>
                <div>
                  <label className="block text-xs text-ink-muted mb-1">Effective To *</label>
                  <input type="date" value={endorseForm.effective_to} onChange={e => setEndorseForm(p => ({ ...p, effective_to: e.target.value }))} className="w-full px-3 py-2 border border-line rounded text-sm" />
                </div>
              </div>
              <div>
                <label className="block text-xs text-ink-muted mb-1">Note</label>
                <textarea value={endorseForm.note} onChange={e => setEndorseForm(p => ({ ...p, note: e.target.value }))} className="w-full px-3 py-2 border border-line rounded text-sm" rows={3} placeholder="Describe what changed (free text)" />
              </div>
            </div>
            <div className="flex gap-2 pt-2 border-t border-line">
              <button onClick={async () => {
                if (!endorseForm.transaction_reason || !endorseForm.effective_from || !endorseForm.effective_to) { alert('Please fill all required fields'); return }
                await doAction('new-transaction', {
                  transaction_type: 'ENDORSE',
                  transaction_reason: endorseForm.transaction_reason,
                  effective_from: endorseForm.effective_from,
                  effective_to: endorseForm.effective_to,
                  note: endorseForm.note || null,
                })
                setShowEndorse(false)
                setEndorseForm({ effective_from: '', effective_to: '', transaction_reason: '', note: '' })
              }} disabled={acting} className="flex-1 py-2 bg-primary text-white rounded text-sm font-medium hover:bg-primary disabled:opacity-50">
                {acting ? 'Saving…' : 'Submit Endorsement'}
              </button>
              <button onClick={() => setShowEndorse(false)} className="px-4 py-2 border border-line rounded text-sm hover:bg-surface-2">Cancel</button>
            </div>
          </div>
        </div>
      )}

      {/* ── Policy Doc (Date Range) — modal (additive) ── */}
      {/* ── Set Frequency (Super Admin) ──
          Writes policy_actions.current_frequency_id for the SELECTED action, and
          policies.premium_freq only when the operator ticks the box. No other
          action row is touched. */}
      {showFreqModal && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto"
             onClick={(e) => { if (e.target === e.currentTarget && !freqSaving) setShowFreqModal(false) }}>
          <div className="bg-surface rounded-lg shadow-xl p-5 w-full max-w-md mx-4" onClick={(e) => e.stopPropagation()}>
            <h3 className="text-lg font-semibold text-ink mb-1">Set Premium Frequency</h3>
            <p className="text-xs text-ink-muted mb-4">
              Applies to <b>{txType || 'this action'}</b>{selected?.policyQuoteNo ? ' — ' + selected.policyQuoteNo : ''}
              {selected?.effectiveFrom
                ? ' (' + new Date(selected.effectiveFrom).toLocaleDateString('en-GB') + ' – ' + actionPeriodEnd(policyEndDate, selected) + ')'
                : ''}.
              Only this transaction is changed — other actions keep their own frequency.
            </p>

            <label className="block text-xs font-medium text-ink-muted mb-1">Frequency</label>
            <select value={freqValue || ''} onChange={(e) => setFreqValue(Number(e.target.value))}
              disabled={freqSaving}
              className="w-full px-3 py-2 border border-line rounded-md text-sm mb-4 focus:outline-none focus:ring-2 focus:ring-primary disabled:opacity-50">
              <option value="">Select…</option>
              {PREMIUM_FREQ_OPTIONS.map(o => <option key={o.id} value={o.id}>{o.label}</option>)}
            </select>

            <label className="flex items-start gap-2 mb-1 cursor-pointer">
              <input type="checkbox" checked={freqApplyToPolicy} disabled={freqSaving}
                onChange={(e) => setFreqApplyToPolicy(e.target.checked)} className="mt-0.5" />
              <span className="text-sm text-ink">Also set this as the policy frequency</span>
            </label>
            <p className="text-xs text-ink-faint mb-4 ml-6">
              Writes the policies table. This drives invoicing and the renew cycles — leave it
              unticked to correct only this transaction's label.
            </p>

            {freqError && (
              <div className="mb-3 rounded border border-status-danger-fg bg-status-danger-bg px-3 py-2 text-xs text-status-danger-fg">
                {freqError}
              </div>
            )}

            <div className="flex justify-end gap-2">
              <button onClick={() => setShowFreqModal(false)} disabled={freqSaving}
                className="px-4 py-2 border border-line rounded text-sm hover:bg-surface-2 disabled:opacity-50">Cancel</button>
              <button onClick={saveActionFrequency} disabled={freqSaving || !freqValue}
                className="px-4 py-2 bg-primary text-white rounded text-sm hover:opacity-90 disabled:opacity-50">
                {freqSaving ? 'Saving…' : 'Save'}
              </button>
            </div>
          </div>
        </div>
      )}

      {showDocRangeModal && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto"
             onClick={(e) => { if (e.target === e.currentTarget && !docRangeGenerating) setShowDocRangeModal(false) }}>
          <div className="bg-white rounded-lg shadow-xl p-5 w-full max-w-md mx-4" onClick={(e) => e.stopPropagation()}>
            <h3 className="text-lg font-semibold text-gray-800 mb-1">Generate Policy Documents — Date Range</h3>
            <p className="text-xs text-gray-500 mb-4">
              One Policy Document is generated for every policy action whose term start falls within the selected dates.
              They run in the background and appear under the <b>Documents</b> tab once ready.
            </p>
            <div className="flex gap-3 mb-4">
              <div className="flex-1">
                <label className="block text-xs font-medium text-gray-600 mb-1">From</label>
                <input type="date" value={docRangeFrom} max={docRangeTo || undefined}
                  onChange={(e) => setDocRangeFrom(e.target.value)}
                  disabled={docRangeGenerating}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 disabled:bg-gray-100" />
              </div>
              <div className="flex-1">
                <label className="block text-xs font-medium text-gray-600 mb-1">To</label>
                <input type="date" value={docRangeTo} min={docRangeFrom || undefined}
                  onChange={(e) => setDocRangeTo(e.target.value)}
                  disabled={docRangeGenerating}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 disabled:bg-gray-100" />
              </div>
            </div>
            <div className="flex justify-end gap-2">
              <button onClick={() => setShowDocRangeModal(false)} disabled={docRangeGenerating}
                className="px-4 py-2 border rounded text-sm hover:bg-gray-50 disabled:opacity-50">Cancel</button>
              <button onClick={generatePolicyDocRange}
                disabled={docRangeGenerating || !docRangeFrom || !docRangeTo}
                className="px-4 py-2 bg-purple-600 text-white rounded text-sm hover:bg-purple-700 disabled:opacity-50">
                {docRangeGenerating ? 'Generating…' : 'Generate'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ── Regenerate Quote Sheet — confirm modal ──
          Soft warning: a quote was generated < 30 min ago AND no policy
          changes since. User can still proceed via "Generate Anyway". */}
      {showRegenerateConfirm && v2RegenerateWarning && (
        <div className="fixed inset-0 bg-black/50 flex items-start justify-center z-50 p-4 pt-10 overflow-y-auto" onClick={() => setShowRegenerateConfirm(false)}>
          <div className="bg-surface rounded-lg shadow-xl p-5 max-w-md mx-4 max-h-[85vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
            <div className="flex items-start gap-3">
              <div className="text-2xl">⚠️</div>
              <div className="flex-1">
                <h3 className="text-base font-semibold text-ink">Quote Sheet recently generated</h3>
                <p className="text-sm text-ink-muted mt-2">
                  A quote sheet was generated by <strong>{v2RegenerateWarning.last_quote_by_name}</strong>{' '}
                  <strong>{v2RegenerateWarning.minutes_ago} {v2RegenerateWarning.minutes_ago === 1 ? 'minute' : 'minutes'} ago</strong>,
                  and no policy changes have been detected since.
                </p>
                <p className="text-sm text-ink-muted mt-2">Generate a new one anyway?</p>
              </div>
            </div>
            <div className="flex items-center justify-end gap-2 mt-5">
              <button
                onClick={() => setShowRegenerateConfirm(false)}
                className="px-4 py-2 text-sm border border-line rounded hover:bg-surface-2">
                Cancel
              </button>
              <button
                onClick={() => {
                  setShowRegenerateConfirm(false)
                  fireGenerateRef.current?.()
                }}
                className="px-4 py-2 text-sm text-white rounded hover:opacity-90"
                style={{ backgroundColor: 'rgb(var(--accent-fg))' }}>
                Generate Anyway
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ── Toast container — bottom-right, stack vertically.
          Lightweight inline alternative to a toast library; each toast
          auto-dismisses after 3.5s (set in pushToast). */}
      {toasts.length > 0 && (
        <div className="fixed bottom-4 right-4 z-50 flex flex-col gap-2 max-w-sm">
          {toasts.map(t => (
            <div
              key={t.id}
              className={`px-4 py-3 rounded-lg shadow-lg border text-sm flex items-start gap-2 ${
                t.variant === 'success' ? 'bg-status-success-bg border-status-success-fg text-status-success-fg'
                : t.variant === 'error'   ? 'bg-status-danger-bg border-status-danger-fg text-status-danger-fg'
                                          : 'bg-status-info-bg border-primary text-primary'
              }`}>
              <span>{t.variant === 'success' ? '✓' : t.variant === 'error' ? '✕' : 'ℹ'}</span>
              <span className="flex-1">{t.message}</span>
              <button onClick={() => setToasts(prev => prev.filter(x => x.id !== t.id))} className="opacity-60 hover:opacity-100">✕</button>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}

// ─── Tab: Ledger (full view) ────────────────────────────────────

function InvoicePdfButton({ policyId, ledgerId }: { policyId: number; ledgerId: number }) {
  const [loading, setLoading] = useState(false)
  const apiBase = `${(import.meta as any).env.VITE_API_URL}/api/v1`
  const open = async () => {
    setLoading(true)
    try {
      const r = await fetch(`${apiBase}/policies/${policyId}/invoice/${ledgerId}`, {
        headers: { Authorization: `Bearer ${localStorage.getItem('sanctum_token')}`, Accept: 'application/pdf' },
      })
      if (!r.ok) {
        // Surface the real backend error (the endpoint returns JSON {error} on
        // 404/500) instead of a generic message, so a bad invoice is diagnosable.
        let msg = `Failed to generate invoice PDF (HTTP ${r.status}).`
        try { const j = await r.json(); if (j?.error) msg = j.error } catch { /* non-JSON body */ }
        alert(msg)
        return
      }
      const blob = await r.blob()
      window.open(URL.createObjectURL(blob), '_blank')
    } catch (e: any) {
      alert(e?.message ?? 'Failed to generate invoice PDF')
    } finally {
      // Always clear the spinner — the previous early `return` on !r.ok skipped
      // this and left the button stuck on the loading indicator.
      setLoading(false)
    }
  }
  return (
    <button onClick={open} disabled={loading}
      className="px-2 py-0.5 text-xs bg-primary text-white rounded hover:bg-primary disabled:opacity-50">
      {loading ? '…' : 'View PDF'}
    </button>
  )
}

/**
 * Opens the credit note document.
 *
 * Deliberately NOT a plain <a href={fileUrl}>: credit_note.credit_note_file is
 * only set when the render at posting time succeeded (best-effort by design —
 * a dompdf hiccup must not block the accounting), so notes exist in PROD with
 * no stored file and those rows had no way to reach the document at all. The
 * endpoint serves the stored PDF when there is one and re-renders it from the
 * note's own figures when there is not.
 */
function CreditNotePdfButton({ policyId, creditNoteId, label = 'View PDF', className, title }:
  { policyId: number; creditNoteId: number; label?: string; className?: string; title?: string }) {
  const [loading, setLoading] = useState(false)
  const apiBase = `${(import.meta as any).env.VITE_API_URL}/api/v1`
  const open = async () => {
    setLoading(true)
    try {
      const r = await fetch(`${apiBase}/policies/${policyId}/credit-note/${creditNoteId}/pdf`, {
        headers: { Authorization: `Bearer ${localStorage.getItem('sanctum_token')}`, Accept: 'application/pdf' },
      })
      if (!r.ok) {
        // Surface the real backend message (the endpoint returns JSON {error}
        // on 422/500) rather than a generic failure.
        let msg = `Could not open the credit note (HTTP ${r.status}).`
        try { const j = await r.json(); if (j?.error) msg = j.error } catch { /* non-JSON body */ }
        alert(msg)
        return
      }
      window.open(URL.createObjectURL(await r.blob()), '_blank')
    } catch (e: any) {
      alert(e?.message ?? 'Could not open the credit note')
    } finally {
      setLoading(false)
    }
  }
  return (
    <button onClick={open} disabled={loading} title={title ?? 'Open the credit note PDF'}
      className={className ?? 'px-2 py-0.5 text-xs bg-primary text-white rounded hover:bg-primary disabled:opacity-50'}>
      {loading ? '…' : label}
    </button>
  )
}

// ── Credit Note ────────────────────────────────────────────────
//
// The CR button used to deep-link to the legacy screen at
// {be}/admin/policy/creditNoteView/{ledger_id}. That link could never work:
// the whole /admin/* tree on the V2 backend is mounted behind BlockV1AdminPanel,
// which abort(404)s unless V1_ADMIN_PANEL_ENABLED is truthy — and it is
// deliberately never set in production (Prathap UAT 2026-05-26 §2.2, DPA-001).
// Even with the panel on, the route group's role:Super Admin|Manager|Admin|
// developer excluded Finance, and the tab has no legacy session cookie anyway.
//
// So the flow is now native: GET/POST /api/v1/policies/{id}/invoice/{ledgerId}/
// credit-note (Api\V1\CreditNoteController). Everything below mirrors the legacy
// screen's three steps — read the invoice, Calculate, Generate — but all the
// arithmetic and the bounds check run server-side.

type CreditNotePreview = {
  policy: { id: number; policyNumber: string; premium: number; premiumFreq: number | null; activatedDate: string | null; cancelledDate: string | null }
  invoice: { id: number; invoiceNo: string | null; invoiceDate: string | null; invoiceAmount: number }
  oneDayPremium: number
  activeDays: number | null
  vatRate: number
  existing: { id: number; creditNoteNo: string; status: number; noOfDays: number | null; postedDate: string | null; effectiveDate: string | null; endDate: string | null; earned: string | null; unearned: string | null; fileUrl: string | null } | null
}

type CreditNoteCalc = {
  no_of_days: number; one_day_premium: number; earned: number; unearned: number
  before_vat: number; vat: number; start_date: string; end_date: string
}

const FREQ_LABEL: Record<number, string> = {
  1: 'Monthly Instalments', 2: 'Three Instalments in a year', 3: 'Annual Instalments',
  4: 'Semi-Annual Instalments', 5: 'Quarterly Instalments',
}

/** P-prefixed money, matching the rest of the ledger tab. */
const pula = (n: number | string | null | undefined) =>
  n === null || n === undefined || n === '' ? 'N/A'
    : `P ${Number(String(n).replace(/,/g, '')).toLocaleString('en-BW', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`

function CreditNoteModal({ policyId, ledgerId, onClose, onPosted }:
  { policyId: number; ledgerId: number; onClose: () => void; onPosted: () => void }) {
  const apiBase = `${(import.meta as any).env.VITE_API_URL}/api/v1`
  const authHeaders = { Authorization: `Bearer ${localStorage.getItem('sanctum_token')}`, 'Content-Type': 'application/json', Accept: 'application/json' }
  const cnUrl = `${apiBase}/policies/${policyId}/invoice/${ledgerId}/credit-note`

  const [preview, setPreview] = useState<CreditNotePreview | null>(null)
  const [loadErr, setLoadErr] = useState<string | null>(null)
  const [completeTerm, setCompleteTerm] = useState(false)
  const [months, setMonths] = useState('1')
  const [endDate, setEndDate] = useState('')
  // The date the note is RECORDED under (the Date column on the Statement of
  // Account). It used to be hardcoded to now(), so a note raised for a month
  // that had already closed still posted, and printed, under today's date. It
  // now FOLLOWS the transaction end date the operator picks — the date they are
  // crediting to is the date the note belongs on — and only falls back to today
  // for a complete-term note, where no date is picked at all. Editing the field
  // directly pins it (dateTouched) so the follow stops overwriting a deliberate
  // choice.
  // Local date, not toISOString() — Botswana runs UTC+2, so a UTC slice defaults
  // the field to YESTERDAY between midnight and 02:00.
  const today = (() => {
    const d = new Date()
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
  })()
  const [creditNoteDate, setCreditNoteDate] = useState(today)
  const [dateTouched, setDateTouched] = useState(false)
  const [calc, setCalc] = useState<CreditNoteCalc | null>(null)
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState<string | null>(null)
  // Re-dating an already-posted note (see the `existing` branch below).
  const [redate, setRedate] = useState('')
  const [redateMsg, setRedateMsg] = useState<string | null>(null)

  // Read the backend's {error} body so a 422 shows the real reason (already
  // credit-noted, amount over the invoice, no premium) rather than a code.
  const readErr = async (r: Response, fallback: string) => {
    try { const j = await r.json(); return j?.error ?? fallback } catch { return fallback }
  }

  useEffect(() => {
    let cancelled = false
    fetch(cnUrl, { headers: authHeaders })
      .then(async r => {
        if (!r.ok) throw new Error(await readErr(r, `Could not load the credit note (HTTP ${r.status}).`))
        return r.json()
      })
      .then(d => {
        if (cancelled) return
        setPreview(d)
        if (d?.existing?.status === 1) setRedate(d.existing.postedDate ?? '')
      })
      .catch(e => { if (!cancelled) setLoadErr(e.message) })
    return () => { cancelled = true }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [cnUrl])

  const body = () => JSON.stringify({
    complete_term: completeTerm, months: Number(months) || 1, end_date: endDate || null,
    credit_note_date: creditNoteDate || null,
  })

  /** Correct the posting date of a note that is already on the ledger. */
  const saveRedate = async () => {
    if (!redate) { setErr('Pick a credit note date.'); return }
    setBusy(true); setErr(null); setRedateMsg(null)
    try {
      const r = await fetch(`${cnUrl}/date`, {
        method: 'PATCH', headers: authHeaders, body: JSON.stringify({ credit_note_date: redate }),
      })
      if (!r.ok) { setErr(await readErr(r, 'Could not update the credit note date.')); return }
      const d = await r.json()
      setRedateMsg(`Credit note date updated to ${d.credit_note_date}.`)
      // Keep the panel showing the new date, and refresh every ledger view —
      // the statement sorts and prints Credit Note rows by this date.
      setPreview(pv => pv && pv.existing
        ? { ...pv, existing: { ...pv.existing, postedDate: d.credit_note_date } }
        : pv)
      onPosted()
    } catch (e: any) {
      setErr(e?.message ?? 'Could not update the credit note date')
    } finally { setBusy(false) }
  }

  const runCalc = async () => {
    setBusy(true); setErr(null)
    try {
      const r = await fetch(`${cnUrl}/calculate`, { method: 'POST', headers: authHeaders, body: body() })
      if (!r.ok) { setErr(await readErr(r, 'Calculation failed.')); setCalc(null); return }
      setCalc(await r.json())
    } catch (e: any) {
      setErr(e?.message ?? 'Calculation failed'); setCalc(null)
    } finally { setBusy(false) }
  }

  const generate = async () => {
    setBusy(true); setErr(null)
    try {
      const r = await fetch(cnUrl, { method: 'POST', headers: authHeaders, body: body() })
      if (!r.ok) { setErr(await readErr(r, 'Could not generate the credit note.')); return }
      await r.json()
      onPosted()   // refresh the ledger so the new 'Credit Note' row appears
      // Close on success — the refreshed ledger's new 'Credit Note' row is the
      // confirmation, and leaving the dialog sitting on a "generated" panel made
      // operators think something was still pending. The generated document stays
      // one click away: re-opening CR on the same invoice hits the `existing`
      // branch above, which renders "View generated document".
      onClose()
    } catch (e: any) {
      setErr(e?.message ?? 'Could not generate the credit note')
    } finally { setBusy(false) }
  }

  const existing = preview?.existing
  const row = (label: string, value: React.ReactNode) => (
    <tr className="border-b border-line last:border-0">
      <th className="w-1/2 text-left px-3 py-2 text-sm font-medium text-ink-muted">{label}</th>
      <td className="px-3 py-2 text-sm text-ink">{value}</td>
    </tr>
  )

  return (
    <Modal open onClose={onClose} size="2xl"
      title={`Credit Note${preview?.invoice.invoiceNo ? ` — Invoice ${preview.invoice.invoiceNo}` : ''}`}>
        {loadErr ? (
          <div className="text-sm text-status-danger-fg">{loadErr}</div>
        ) : !preview ? (
          <div className="text-sm text-ink-muted">Loading…</div>
        ) : (
          <div className="space-y-4">
            <table className="w-full border border-line rounded">
              <tbody>
                {row('Policy Activated Date', preview.policy.activatedDate ?? 'N/A')}
                {row('Policy Cancelled Date', preview.policy.cancelledDate ?? 'N/A')}
                {row('Premium', pula(preview.policy.premium))}
                {row('Premium Frequency', preview.policy.premiumFreq ? (FREQ_LABEL[preview.policy.premiumFreq] ?? 'N/A') : 'N/A')}
                {row('Invoice Date', preview.invoice.invoiceDate ?? 'N/A')}
                {row('Invoice Amount', pula(preview.invoice.invoiceAmount))}
              </tbody>
            </table>

            {existing && existing.status === 1 ? (
              /* Already credit-noted — read-only, matching the legacy screen,
                 which hid the GENERATE button once credit_note.status == 1. */
              <>
                <div className="px-4 py-3 rounded-md bg-status-info-bg border border-primary text-sm text-primary">
                  Credit note <span className="font-semibold">{existing.creditNoteNo}</span> has already been raised
                  against this invoice.
                </div>
                <table className="w-full border border-line rounded">
                  <tbody>
                    {row('Credit Note Date', existing.postedDate ?? 'N/A')}
                    {row('No. of Active Days', existing.noOfDays ?? 'N/A')}
                    {row('Transaction Effective Date', existing.effectiveDate ?? 'N/A')}
                    {row('Transaction End Date', existing.endDate ?? 'N/A')}
                    {row('Earned Premium', pula(existing.earned))}
                    {row('Unearned Premium', pula(existing.unearned))}
                  </tbody>
                </table>

                {/* Re-date. Every note raised before the posting date became
                    selectable was stamped with now(), so the wrong dates already
                    in the ledger can only be corrected here. Nothing about the
                    money moves — the debit, the earned/unearned split and the
                    generated document (which prints the credited period, never
                    the posting date) are all untouched; only the date the ledger
                    and the Statement of Account record it under changes. */}
                <div className="border border-line rounded p-3 space-y-2">
                  <div className="text-sm font-medium text-ink-muted">Correct the credit note date</div>
                  <p className="text-xs text-ink-faint">
                    Updates the date this note is recorded under in the ledger, the sub-ledger and the
                    Statement of Account. The credited period and all amounts stay as they are.
                  </p>
                  <div className="flex items-end gap-2">
                    <input type="date" value={redate} onChange={e => { setRedate(e.target.value); setRedateMsg(null) }}
                      className="px-3 py-2 border border-line rounded bg-surface text-ink text-sm" />
                    <button onClick={saveRedate} disabled={busy || !redate || redate === existing.postedDate}
                      className="px-4 py-2 text-sm rounded bg-primary text-white hover:opacity-90 disabled:opacity-50">
                      {busy ? '…' : 'Update date'}
                    </button>
                  </div>
                  {redateMsg && <div className="text-sm text-status-success-fg">{redateMsg}</div>}
                  {err && <div className="text-sm text-status-danger-fg">{err}</div>}
                </div>

                {existing.fileUrl && (
                  <a href={existing.fileUrl} target="_blank" rel="noopener noreferrer"
                    className="inline-block px-4 py-2 text-sm rounded bg-primary text-white hover:opacity-90">
                    View generated document
                  </a>
                )}
              </>
            ) : (
              <>
                {/* Period — the legacy "Generate for complete Term?" radio. */}
                <div className="space-y-3">
                  <div className="flex items-center gap-6 text-sm text-ink">
                    <span className="font-medium text-ink-muted">Generate for complete term?</span>
                    <label className="flex items-center gap-1.5">
                      <input type="radio" checked={completeTerm} onChange={() => {
                        setCompleteTerm(true); setCalc(null)
                        if (!dateTouched) setCreditNoteDate(today)
                      }} /> Yes
                    </label>
                    <label className="flex items-center gap-1.5">
                      <input type="radio" checked={!completeTerm} onChange={() => {
                        setCompleteTerm(false); setCalc(null)
                        if (!dateTouched) setCreditNoteDate(endDate || today)
                      }} /> No
                    </label>
                  </div>

                  {completeTerm ? (
                    <label className="block text-sm">
                      <span className="text-ink-muted">
                        For how many {preview.policy.premiumFreq === 3 ? 'years' : 'months'} do you want to generate the credit note?
                      </span>
                      <input type="number" min={1} value={months}
                        onChange={e => { setMonths(e.target.value); setCalc(null) }}
                        className="mt-1 w-full px-3 py-2 border border-line rounded bg-surface text-ink" />
                    </label>
                  ) : (
                    <label className="block text-sm">
                      <span className="text-ink-muted">Transaction end date</span>
                      {/* No min/max — the credited period is Finance's call, so
                          any end date can be picked, the invoice date included. */}
                      <input type="date" value={endDate}
                        onChange={e => {
                          setEndDate(e.target.value); setCalc(null)
                          // The note is recorded on the date being credited to,
                          // unless the operator has pinned a different one.
                          if (!dateTouched) setCreditNoteDate(e.target.value || today)
                        }}
                        className="mt-1 w-full px-3 py-2 border border-line rounded bg-surface text-ink" />
                      <span className="block mt-1 text-xs text-ink-faint">
                        Credited from the invoice date ({preview.invoice.invoiceDate ?? 'N/A'}) to this date.
                      </span>
                    </label>
                  )}
                </div>

                {/* Posting date — what the ledger and the Statement of Account
                    record the note under. It TRACKS the transaction end date
                    above (the date being credited to is the date the note
                    belongs on), so the normal flow needs no second decision;
                    editing it here pins it. Complete-term notes fall back to
                    today, since that mode picks no date. */}
                <label className="block text-sm">
                  <span className="text-ink-muted">Credit note date</span>
                  <input type="date" value={creditNoteDate}
                    onChange={e => { setCreditNoteDate(e.target.value); setDateTouched(true) }}
                    className="mt-1 w-full px-3 py-2 border border-line rounded bg-surface text-ink" />
                  <span className="block mt-1 text-xs text-ink-faint">
                    {dateTouched
                      ? 'The date this note is recorded under in the ledger and on the Statement of Account.'
                      : 'Follows the date above, so the note is recorded under that date and not today. Change it only if it must be booked in a different period.'}
                  </span>
                </label>

                <table className="w-full border border-line rounded">
                  <tbody>
                    {row('No. of Active Days', calc ? calc.no_of_days : (preview.activeDays ?? 'N/A'))}
                    {row('One Day Premium', pula(calc ? calc.one_day_premium : preview.oneDayPremium))}
                    {row('Earned Premium', calc ? pula(calc.earned) : '—')}
                    {row('Unearned Premium', calc ? pula(calc.unearned) : '—')}
                  </tbody>
                </table>

                {err && <div className="text-sm text-status-danger-fg">{err}</div>}

                <div className="flex items-center justify-end gap-2 pt-1">
                  <button onClick={runCalc} disabled={busy}
                    className="px-4 py-2 text-sm rounded border border-line text-ink hover:bg-surface-2 disabled:opacity-50">
                    {busy ? '…' : 'Calculate'}
                  </button>
                  {/* Generate only waits for Calculate to have run, so the user
                      posts the figures they were shown. The period itself is not
                      policed: an end date equal to the invoice date (0 earned
                      days) or one long enough to leave a negative unearned split
                      both generate. The ledger amount is the invoice amount
                      either way — only the earned/unearned split moves. */}
                  <button onClick={generate} disabled={busy || !calc}
                    className="px-4 py-2 text-sm rounded bg-primary text-white hover:opacity-90 disabled:opacity-50">
                    Generate Credit Note
                  </button>
                </div>
              </>
            )}
          </div>
        )}
    </Modal>
  )
}

/** CR — opens the credit-note screen for this invoice row. */
function CreditNoteButton({ policyId, ledgerId, invoiceNo, invoiceAmount, onPosted }:
  { policyId: number; ledgerId: number; invoiceNo?: string | null; invoiceAmount?: string | number | null; onPosted: () => void }) {
  const [open, setOpen] = useState(false)
  // A credit note reverses the full invoice, so a row that is ALREADY a credit
  // (negative amount — the reversal DomCom raises when an endorsement reduces
  // premium) has nothing to credit: the money has gone back to the customer
  // and the API rejects it. Those rows read as "(Credit P 504.66)" in the
  // listing, so Finance can still see them; they just get no CR button.
  // Unparseable/absent amounts keep the button — the API is the real guard.
  const amount = invoiceAmount == null ? NaN
    : typeof invoiceAmount === 'string' ? parseFloat(invoiceAmount.replace(/,/g, '')) : invoiceAmount
  if (!isNaN(amount) && amount <= 0) return null
  // Rendered only when the row HAS an invoice number (a credit note is raised
  // against an invoice; the endpoint rejects numberless ledger rows) AND the
  // user holds policy_credit_note. The API already enforces the permission
  // (403), but showing a button that only 403s is poor UX. Empty perms list =
  // admin fallback (matches hasPerm() elsewhere in this file).
  const canCreditNote = (() => {
    try {
      const perms = JSON.parse(localStorage.getItem('user_permissions') || '[]') as string[]
      return perms.length === 0 || perms.includes('policy_credit_note')
    } catch { return false }
  })()
  if (!invoiceNo || !canCreditNote) return null
  return (
    <>
      <button onClick={() => setOpen(true)}
        title="Raise a credit note for this invoice"
        className="px-2 py-0.5 text-xs bg-surface-2 text-ink border border-line rounded hover:bg-surface-3">
        CR
      </button>
      {open && (
        <CreditNoteModal policyId={policyId} ledgerId={ledgerId}
          onClose={() => setOpen(false)} onPosted={onPosted} />
      )}
    </>
  )
}

/**
 * Edit the date a posted credit note is RECORDED under.
 *
 * That date lives on the note's policy_ledger row, not on credit_note, and it
 * is what the Statement of Account prints and sorts Credit Note rows by — so a
 * note stamped with the day it was RAISED prints in the wrong month. Opens the
 * same modal the CR flow uses: against an invoice that already carries a note
 * it renders the note read-only plus its "Correct the credit note date"
 * control, which moves the ledger row and its sub-ledger legs together.
 *
 * This is the only way in. The Invoicing tab shows the CR NUMBER in place of
 * the CR button once a note exists, so without this the re-date control would
 * be unreachable for exactly the notes that need correcting.
 */
function CreditNoteDateButton({ policyId, ledgerId, onSaved }:
  { policyId: number; ledgerId: number; onSaved: () => void }) {
  const [open, setOpen] = useState(false)
  // Same gate as CreditNoteButton — the PATCH is behind policy_credit_note, and
  // a button that can only 403 is worse than no button. Empty perms list =
  // admin fallback (matches hasPerm() elsewhere in this file).
  const canEdit = (() => {
    try {
      const perms = JSON.parse(localStorage.getItem('user_permissions') || '[]') as string[]
      return perms.length === 0 || perms.includes('policy_credit_note')
    } catch { return false }
  })()
  if (!canEdit) return null
  return (
    <>
      <button onClick={() => setOpen(true)}
        title="Change the date this note is recorded under in the ledger and on the Statement of Account"
        className="px-2 py-0.5 text-xs bg-surface-2 text-ink border border-line rounded hover:bg-surface-3 whitespace-nowrap">
        Edit Date
      </button>
      {open && (
        <CreditNoteModal policyId={policyId} ledgerId={ledgerId}
          onClose={() => setOpen(false)} onPosted={onSaved} />
      )}
    </>
  )
}

function LedgerTab({ policyId, productId = 0, policyNumber }: { policyId: number; productId?: number; policyNumber: string }) {
  return <FullLedger policyId={policyId} productId={productId} policyNumber={policyNumber} />
}

// ─── Tab: Claims (lazy loaded) ──────────────────────────────────

function ClaimsTab({ policyId, policyNumber }: { policyId: number; policyNumber: string }) {
  const [page, setPage] = useState(1)
  const { data, isLoading } = usePolicyClaims(policyId, true, page)
  const navigate = useNavigate()

  const goRegisterClaim = () => navigate('/claims/create', { state: { policyNumber } })
  const registerBtn = (
    <button onClick={goRegisterClaim}
      className="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium bg-primary text-white rounded hover:bg-primary">
      + Register Claim
    </button>
  )

  if (isLoading) return <ProgressBar isLoading label="Loading claims" className="max-w-xs mx-auto py-8" />

  const claims: PolicyClaim[] = data?.data ?? []
  const meta = data?.meta

  if (claims.length === 0) return (
    <div className="flex flex-col items-center justify-center py-10 gap-3">
      <EmptyState message="No claims on this policy." />
      {registerBtn}
    </div>
  )

  return (
    <div className="space-y-3">
      <div className="flex justify-end">{registerBtn}</div>
    <Card title="Claims">
      <DualScrollTable>
        <table className="w-full text-sm">
          <thead className="bg-surface-2 text-ink-muted uppercase text-xs">
            <tr>
              <th className="px-4 py-2 text-left">Claim Number</th>
              <th className="px-4 py-2 text-left">Claim Type</th>
              <th className="px-4 py-2 text-left">Status</th>
              <th className="px-4 py-2 text-left">Created At</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-line">
            {claims.map((c: PolicyClaim) => (
              <tr key={c.id} className="hover:bg-surface-2">
                <td className="px-4 py-2 font-mono">
                  <Link to={`/claims/${c.id}`} className="text-primary hover:underline">{c.claimNumber}</Link>
                </td>
                <td className="px-4 py-2">{c.claimType}</td>
                <td className="px-4 py-2"><StatusBadge status={c.status} /></td>
                <td className="px-4 py-2 text-ink-muted">{fmtDate(c.createdAt)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </DualScrollTable>
      {meta && meta.last_page > 1 && (
        <Pagination current={meta.current_page} last={meta.last_page} onChange={setPage} />
      )}
    </Card>
    </div>
  )
}

// ─── Tab: Transactions (lazy loaded) ────────────────────────────

type LedgerTabKey = 'account' | 'receivable' | 'invoicing' | 'creditNote' | 'subLedger'

const LEDGER_TABS: { key: LedgerTabKey; label: string }[] = [
  { key: 'account', label: 'Account View' },
  { key: 'receivable', label: 'Receivable View' },
  { key: 'invoicing', label: 'Invoicing' },
  { key: 'creditNote', label: 'Credit Notes' },
  { key: 'subLedger', label: 'Sub Ledger' },
]

const LEDGER_PAGE_SIZE = 10

function useLedgerPagination<T>(items: T[], search: string) {
  const [page, setPage] = useState(1)
  const filtered = search
    ? items.filter(row => Object.values(row as Record<string, unknown>).some(v => String(v ?? '').toLowerCase().includes(search.toLowerCase())))
    : items
  const totalPages = Math.max(1, Math.ceil(filtered.length / LEDGER_PAGE_SIZE))
  const safeP = Math.min(page, totalPages)
  const paged = filtered.slice((safeP - 1) * LEDGER_PAGE_SIZE, safeP * LEDGER_PAGE_SIZE)
  return { paged, page: safeP, totalPages, setPage, total: filtered.length }
}

// ─── Commented out: replaced by TransactionLogsTab (V8 parity port) ───
// Kept here for rollback only. To re-enable, also un-comment the entry in
// ALL_TABS and the activeTab === 'transactions' render line above.
// @ts-expect-error retained for rollback
// eslint-disable-next-line @typescript-eslint/no-unused-vars
function TransactionsTab({ policyId }: { policyId: number }) {
  const [txPage, setTxPage] = useState(1)
  const { data, isLoading } = usePolicyTransactions(policyId, true, txPage)

  const transactions: PolicyTransaction[] = data?.data ?? []
  const txMeta = data?.meta

  return (
    <div className="space-y-6">
      {/* Payment Transactions */}
      <Card title="Payment Transactions">
        {isLoading ? (
          <ProgressBar isLoading label="Loading transactions" className="max-w-xs mx-auto py-4" />
        ) : transactions.length === 0 ? (
          <p className="text-sm text-ink-faint text-center py-4">No transactions on this policy.</p>
        ) : (
          <>
            <DualScrollTable>
              <table className="w-full text-sm">
                <thead className="bg-surface-2 text-ink-muted uppercase text-xs">
                  <tr>
                    <th className="px-3 py-2 text-left">Id</th>
                    <th className="px-3 py-2 text-left">Reference Number</th>
                    <th className="px-3 py-2 text-left">Payment Method</th>
                    <th className="px-3 py-2 text-right">Amount</th>
                    <th className="px-3 py-2 text-left">Payment Date</th>
                    <th className="px-3 py-2 text-left">Settlement Date</th>
                    <th className="px-3 py-2 text-right">Installments</th>
                    <th className="px-3 py-2 text-left">Note</th>
                    <th className="px-3 py-2 text-left">Status</th>
                    <th className="px-3 py-2 text-left">Received By</th>
                    <th className="px-3 py-2 text-left">Added By</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-line">
                  {transactions.map((t: PolicyTransaction) => (
                    <tr key={t.id} className="hover:bg-surface-2">
                      <td className="px-3 py-2 text-ink-faint">{t.id}</td>
                      <td className="px-3 py-2 font-mono text-xs max-w-[180px] truncate" title={t.referenceNumber || ''}>{t.referenceNumber || '—'}</td>
                      <td className="px-3 py-2">{t.paymentMethod || '—'}</td>
                      <td className="px-3 py-2 text-right">{t.amount ? fmtCurrency(t.amount) : '—'}</td>
                      <td className="px-3 py-2 text-ink-muted whitespace-nowrap">{t.paymentDate || '—'}</td>
                      <td className="px-3 py-2 text-ink-muted whitespace-nowrap">{t.settlementDate || '—'}</td>
                      <td className="px-3 py-2 text-right">{t.installmentsPaid ?? '—'}</td>
                      <td className="px-3 py-2 text-xs max-w-[150px] truncate" title={t.note || ''}>{t.note || '—'}</td>
                      <td className="px-3 py-2">
                        {t.isReversed ? (
                          <span className="text-status-danger-fg font-medium text-xs">Reversed</span>
                        ) : t.isRefunded ? (
                          <span className="text-status-danger-fg font-medium text-xs">Refunded</span>
                        ) : t.status ? (
                          <StatusBadge status={t.status} />
                        ) : '—'}
                      </td>
                      <td className="px-3 py-2 whitespace-nowrap">{t.cashRecipient || '—'}</td>
                      <td className="px-3 py-2 whitespace-nowrap">{t.addedBy || '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </DualScrollTable>
            {txMeta && txMeta.last_page > 1 && (
              <Pagination current={txMeta.current_page} last={txMeta.last_page} onChange={setTxPage} />
            )}
          </>
        )}
      </Card>
    </div>
  )
}

function FullLedger({ policyId, productId = 0, policyNumber }: { policyId: number; productId?: number; policyNumber: string }) {
  // DomCom (7,8) and special products (16,17,18,19,20,22) don't use the
  // Sub Ledger view — hide that tab for them. Other products keep it.
  const ledgerTabs = DOMG_COMG_PRODUCT_IDS.includes(productId)
    ? LEDGER_TABS.filter(t => t.key !== 'subLedger')
    : LEDGER_TABS
  const { data: actionsData } = usePolicyActions(policyId, true)
  const [ledgerActionId, setLedgerActionId] = useState<number | undefined>(undefined)
  const { data: ledgerData, isLoading: ledgerLoading } = usePolicyLedger(policyId, true, ledgerActionId)
  const policyActions = (actionsData as any)?.data ?? actionsData ?? []
  // Posting a credit note writes a credit_notes row plus a new 'Credit Note'
  // policy_ledger row and its policy_sub_ledger lines
  // (Services\CreditNotes\InvoiceCreditNoteService), so every ledger view for
  // this policy is stale afterwards — Account, Receivable, Invoicing and Sub
  // Ledger all come from the one ['policy', id, 'ledger', actionId] query, and
  // the prefix key below matches it for every action filter.
  //
  // The reversal also moves the money, so the Balance Owing / Days in Arrears /
  // Collection Status header tiles are stale too. Those read a SEPARATE query
  // (usePolicyClientHealth), which the ledger prefix does not cover — without
  // invalidating it the operator raised a credit note and the header kept
  // showing the pre-reversal balance until a full page reload.
  const ledgerQc = useQueryClient()
  const refreshLedger = () => {
    ledgerQc.invalidateQueries({ queryKey: ['policy', policyId, 'ledger'] })
    ledgerQc.invalidateQueries({ queryKey: ['policy', policyId, 'client-health'] })
  }

  const [ledgerTab, setLedgerTab] = useState<LedgerTabKey>('account')
  const [ledgerSearch, setLedgerSearch] = useState('')

  const accountView: LedgerAccountEntry[] = ledgerData?.accountView ?? []
  const receivableView: LedgerReceivableEntry[] = ledgerData?.receivableView ?? []
  const invoicing: LedgerInvoiceEntry[] = ledgerData?.invoicing ?? []
  const creditNotes: LedgerCreditNoteEntry[] = ledgerData?.creditNotes ?? []
  const subLedger: LedgerSubEntry[] = ledgerData?.subLedger ?? []
  const subLedgerCapped = ledgerData?.subLedgerCapped ?? false
  const subLedgerTotal = ledgerData?.subLedgerTotal ?? subLedger.length
  const totalDues = ledgerData?.totalDues ?? '0.00'

  const acctPg = useLedgerPagination(accountView, ledgerSearch)
  const recvPg = useLedgerPagination(receivableView, ledgerSearch)
  const invPg = useLedgerPagination(invoicing, ledgerSearch)
  const cnPg = useLedgerPagination(creditNotes, ledgerSearch)
  // What has been credited back on this policy in total. Amounts arrive as
  // formatted strings ("1,234.56"), so strip the grouping before adding.
  const cnTotal = creditNotes.reduce(
    (t, c) => t + (parseFloat(String(c.amount ?? '0').replace(/,/g, '')) || 0), 0)
  const subPg = useLedgerPagination(subLedger, ledgerSearch)

  const [pdfLoading, setPdfLoading] = useState(false)

  const activePg = ledgerTab === 'account' ? acctPg
    : ledgerTab === 'receivable' ? recvPg
    : ledgerTab === 'invoicing' ? invPg
    : ledgerTab === 'creditNote' ? cnPg
    : subPg

  const downloadStatement = async () => {
    if (pdfLoading) return
    setPdfLoading(true)
    try {
      const r = await apiClient.get(`/policies/${policyId}/account-statement-pdf`, {
        responseType: 'blob',
        params: ledgerActionId ? { action_id: ledgerActionId } : {},
      })
      // Backend may return JSON-encoded errors with a 200 wrapper or a 5xx
      // that axios catches below. Detect non-PDF blobs and surface the message.
      const ct = (r.data?.type || '').toLowerCase()
      if (ct.includes('json') || ct.includes('text')) {
        const txt = await r.data.text()
        try { alert(JSON.parse(txt)?.error || 'Failed to generate account statement') }
        catch { alert(txt || 'Failed to generate account statement') }
        return
      }
      const blob = new Blob([r.data], { type: 'application/pdf' })
      const url = URL.createObjectURL(blob)
      // Use a real anchor click so the browser treats this as a user-initiated
      // download — window.open after an async await is silently blocked by
      // most pop-up blockers (the user-gesture context is lost).
      const a = document.createElement('a')
      a.href = url
      a.download = buildDocFilename(policyNumber, 'AccountStatement', { policyId })
      a.rel = 'noopener'
      document.body.appendChild(a)
      a.click()
      a.remove()
      setTimeout(() => URL.revokeObjectURL(url), 60_000)
    } catch (e: any) {
      // axios returns the raw Blob in e.response.data when responseType=blob,
      // so the usual `.error` lookup fails — read the blob as text first.
      let msg = 'Failed to generate account statement'
      const data = e?.response?.data
      if (data && typeof data.text === 'function') {
        try {
          const txt = await data.text()
          try { msg = JSON.parse(txt)?.error || msg } catch { msg = txt || msg }
        } catch { /* keep default */ }
      } else if (data?.error) {
        msg = data.error
      }
      alert(msg)
    } finally {
      setPdfLoading(false)
    }
  }

  return (
    <div className="space-y-6">
      <Card title={
        <div className="flex items-center justify-between w-full">
          <span>Policy Ledger</span>
          <div className="flex items-center gap-3">
            <button type="button" onClick={downloadStatement} disabled={pdfLoading}
              className="px-3 py-1 text-xs font-medium bg-primary text-white rounded hover:bg-primary disabled:opacity-60 disabled:cursor-wait inline-flex items-center gap-2">
              {pdfLoading && <span className="w-3 h-3 border-2 border-line border-t-transparent rounded-full animate-spin" />}
              {pdfLoading ? 'Generating PDF…' : 'Export Account Statement (PDF)'}
            </button>
            <span className="text-base font-semibold text-ink-muted">Total Dues: <span className="text-primary">P{totalDues}</span></span>
          </div>
        </div>
      }>
        {ledgerLoading ? (
          <ProgressBar isLoading label="Loading ledger" className="max-w-xs mx-auto py-4" />
        ) : (
          <div>
            {/* Ledger sub-tabs */}
            <div className="flex items-center gap-0 border-b border-line mb-3">
              {ledgerTabs.map(tab => (
                <button
                  key={tab.key}
                  onClick={() => { setLedgerTab(tab.key); setLedgerSearch('') }}
                  className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${
                    ledgerTab === tab.key
                      ? 'border-primary text-primary'
                      : 'border-transparent text-ink-muted hover:text-ink-muted hover:border-line'
                  }`}
                >
                  {tab.label}
                </button>
              ))}
            </div>

            {/* Search + Action filter */}
            <div className="px-1 pb-3 flex gap-2 flex-wrap items-center">
              <input
                type="text"
                placeholder={`Search ${ledgerTabs.find(t => t.key === ledgerTab)?.label ?? ''}...`}
                value={ledgerSearch}
                onChange={e => { setLedgerSearch(e.target.value); activePg.setPage(1) }}
                className="w-full max-w-xs px-3 py-1.5 border border-line rounded text-sm focus:outline-none focus:ring-1 focus:ring-primary"
              />
              {policyActions.length > 0 && (
                <select
                  value={ledgerActionId ?? ''}
                  onChange={e => setLedgerActionId(e.target.value ? Number(e.target.value) : undefined)}
                  className="px-3 py-1.5 border border-line rounded text-sm focus:outline-none focus:ring-1 focus:ring-primary"
                  title="Filter ledger by policy action (matches graphiteBWV8)"
                >
                  <option value="">All actions</option>
                  {[...policyActions]
                    .sort((a: any, b: any) => (b.effective_from || '').localeCompare(a.effective_from || ''))
                    .map((a: any) => (
                      <option key={a.id} value={a.id}>
                        #{a.id} {a.action_name || a.policy_action || a.name || ''} {a.effective_from ? `(${a.effective_from})` : ''}
                      </option>
                    ))}
                </select>
              )}
            </div>

            {/* Account View */}
            {ledgerTab === 'account' && (
              acctPg.paged.length === 0 ? (
                <p className="text-sm text-ink-faint text-center py-4">No account view entries.</p>
              ) : (
                <>
                  <DualScrollTable>
                    <table className="w-full text-sm">
                      <thead className="bg-surface-2 text-ink-muted uppercase text-xs">
                        <tr>
                          <th className="px-3 py-2 text-left">ID</th>
                          <th className="px-3 py-2 text-left">Accounting Dt.</th>
                          <th className="px-3 py-2 text-left">Trans Type</th>
                          <th className="px-3 py-2 text-left">Trans Ref</th>
                          <th className="px-3 py-2 text-left">Customer Name</th>
                          <th className="px-3 py-2 text-left">Orig Trans</th>
                          <th className="px-3 py-2 text-right">Unallocated</th>
                          <th className="px-3 py-2 text-right">Debit</th>
                          <th className="px-3 py-2 text-right">Credit</th>
                          <th className="px-3 py-2 text-right">Balance</th>
                          <th className="px-3 py-2 text-left">System Dt.</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-line">
                        {acctPg.paged.map((l: LedgerAccountEntry) => (
                          <tr key={l.id} className="hover:bg-surface-2">
                            <td className="px-3 py-2 text-ink-faint">{l.id}</td>
                            <td className="px-3 py-2 whitespace-nowrap">{l.accountingDate || '—'}</td>
                            <td className="px-3 py-2">{l.transType || '—'}</td>
                            {/* Credit Note rows carry only the CR number, so there
                                was nothing on screen tying a note back to the
                                invoice it reverses — the Statement of Account
                                prints "<invoice no>_<CR no>", and searching the
                                ledger for that reference matched nothing. The
                                credited invoice is shown under the CR number, and
                                the composite is the cell's tooltip. */}
                            <td className="px-3 py-2 font-mono text-xs max-w-[200px]"
                              title={l.creditNoteRef || l.transRef || ''}>
                              <span className="block truncate">{l.transRef || '—'}</span>
                              {l.creditedInvoiceNo && (
                                <span className="block truncate text-ink-faint" title={l.creditedInvoiceNo}>
                                  ↳ {l.creditedInvoiceNo}
                                </span>
                              )}
                            </td>
                            <td className="px-3 py-2 whitespace-nowrap">{l.customerName || '—'}</td>
                            <td className="px-3 py-2 font-mono text-xs max-w-[160px] truncate" title={l.origTrans || ''}>{l.origTrans || '—'}</td>
                            <td className="px-3 py-2 text-right text-primary">{l.unallocated ? fmtCurrency(l.unallocated) : '—'}</td>
                            <td className="px-3 py-2 text-right">{l.debit ? fmtCurrency(l.debit) : '—'}</td>
                            <td className="px-3 py-2 text-right text-status-success-fg">{l.credit ? fmtCurrency(l.credit) : '—'}</td>
                            <td className="px-3 py-2 text-right font-medium">{l.balance ? fmtCurrency(l.balance) : '—'}</td>
                            <td className="px-3 py-2 whitespace-nowrap">{l.systemDate || '—'}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </DualScrollTable>
                  {acctPg.totalPages > 1 && (
                    <LedgerPagination current={acctPg.page} last={acctPg.totalPages} total={acctPg.total} onChange={acctPg.setPage} />
                  )}
                </>
              )
            )}

            {/* Receivable View */}
            {ledgerTab === 'receivable' && (
              recvPg.paged.length === 0 ? (
                <p className="text-sm text-ink-faint text-center py-4">No receivable view entries.</p>
              ) : (
                <>
                  <DualScrollTable>
                    <table className="w-full text-sm">
                      <thead className="bg-surface-2 text-ink-muted uppercase text-xs">
                        <tr>
                          <th className="px-3 py-2 text-left">ID</th>
                          <th className="px-3 py-2 text-left">Accounting Dt.</th>
                          <th className="px-3 py-2 text-left">Trans Type</th>
                          <th className="px-3 py-2 text-left">Trans Sub Type</th>
                          <th className="px-3 py-2 text-left">Trans Ref</th>
                          <th className="px-3 py-2 text-left">Eff Date</th>
                          <th className="px-3 py-2 text-right">Debit</th>
                          <th className="px-3 py-2 text-right">Credit</th>
                          <th className="px-3 py-2 text-right">Balance</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-line">
                        {recvPg.paged.map((l: LedgerReceivableEntry) => (
                          <tr key={l.id} className="hover:bg-surface-2">
                            <td className="px-3 py-2 text-ink-faint">{l.id}</td>
                            <td className="px-3 py-2 whitespace-nowrap">{l.accountingDate || '—'}</td>
                            <td className="px-3 py-2">{l.transType || '—'}</td>
                            <td className="px-3 py-2">{l.transSubType || '—'}</td>
                            <td className="px-3 py-2 font-mono text-xs max-w-[160px] truncate" title={l.transRef || ''}>{l.transRef || '—'}</td>
                            <td className="px-3 py-2 whitespace-nowrap">{l.effDate || '—'}</td>
                            <td className="px-3 py-2 text-right">{l.debit ? fmtCurrency(l.debit) : '—'}</td>
                            <td className="px-3 py-2 text-right text-status-success-fg">{l.credit ? fmtCurrency(l.credit) : '—'}</td>
                            <td className="px-3 py-2 text-right font-medium">{l.balance ? fmtCurrency(l.balance) : '—'}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </DualScrollTable>
                  {recvPg.totalPages > 1 && (
                    <LedgerPagination current={recvPg.page} last={recvPg.totalPages} total={recvPg.total} onChange={recvPg.setPage} />
                  )}
                </>
              )
            )}

            {/* Invoicing */}
            {ledgerTab === 'invoicing' && (
              invPg.paged.length === 0 ? (
                <p className="text-sm text-ink-faint text-center py-4">No invoicing entries.</p>
              ) : (
                <>
                  <DualScrollTable>
                    <table className="w-full text-sm">
                      <thead className="bg-surface-2 text-ink-muted uppercase text-xs">
                        <tr>
                          <th className="px-3 py-2 text-left">ID</th>
                          <th className="px-3 py-2 text-left">Invoice Dt.</th>
                          <th className="px-3 py-2 text-left">Invoice No.</th>
                          <th className="px-3 py-2 text-right">Premium</th>
                          <th className="px-3 py-2 text-right">Other Charges</th>
                          <th className="px-3 py-2 text-right">Due Amount</th>
                          <th className="px-3 py-2 text-right">Balance</th>
                          <th className="px-3 py-2 text-right">Pmts/Adjust</th>
                          <th className="px-3 py-2 text-right">Invoice Amt.</th>
                          <th className="px-3 py-2 text-left">Due Date</th>
                          <th className="px-3 py-2 text-left">Status</th>
                          <th className="px-3 py-2 text-left">Invoice</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-line">
                        {invPg.paged.map((l: LedgerInvoiceEntry) => (
                          <tr key={l.id} className="hover:bg-surface-2">
                            <td className="px-3 py-2 text-ink-faint">{l.id}</td>
                            <td className="px-3 py-2 whitespace-nowrap">{l.invoiceDate || '—'}</td>
                            <td className="px-3 py-2 font-mono text-xs">{l.invoiceNo || '—'}</td>
                            <td className="px-3 py-2 text-right">{l.premium ? fmtCurrency(l.premium) : '—'}</td>
                            <td className="px-3 py-2 text-right">{l.otherCharges ? fmtCurrency(l.otherCharges) : '—'}</td>
                            <td className="px-3 py-2 text-right">{l.dueAmount ? fmtCurrency(l.dueAmount) : '—'}</td>
                            <td className="px-3 py-2 text-right font-medium">{l.balance ? fmtCurrency(l.balance) : '—'}</td>
                            <td className="px-3 py-2 text-right">{l.pmtsAdjust ? fmtCurrency(l.pmtsAdjust) : '—'}</td>
                            <td className="px-3 py-2 text-right">{l.invoiceAmount ? fmtCurrency(l.invoiceAmount) : '—'}</td>
                            <td className="px-3 py-2 whitespace-nowrap">{l.dueDate || '—'}</td>
                            <td className="px-3 py-2">
                              {l.status ? <StatusBadge status={l.status} /> : '—'}
                            </td>
                            <td className="px-3 py-2">
                              <div className="flex items-center gap-1">
                                <InvoicePdfButton policyId={policyId} ledgerId={l.id} />
                                {/* An invoice can carry only ONE credit note — the
                                    API 422s a second one — so once a note exists the
                                    CR button is dead weight. Show the CR number in
                                    its place; the period, amounts and PDF live on
                                    the Credit Notes tab. */}
                                {l.creditNote?.no ? (
                                  <CreditNotePdfButton policyId={policyId} creditNoteId={l.creditNote.id}
                                    label={l.creditNote.no}
                                    title={`Credit noted ${l.creditNote.date ?? ''} — click to open the note`}
                                    className="px-2 py-0.5 text-xs font-mono rounded border border-status-success-fg text-status-success-fg bg-status-success-bg whitespace-nowrap hover:opacity-80 disabled:opacity-50" />
                                ) : (
                                  <CreditNoteButton policyId={policyId} ledgerId={l.id} invoiceNo={l.invoiceNo} invoiceAmount={l.invoiceAmount} onPosted={refreshLedger} />
                                )}
                              </div>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </DualScrollTable>
                  {invPg.totalPages > 1 && (
                    <LedgerPagination current={invPg.page} last={invPg.totalPages} total={invPg.total} onChange={invPg.setPage} />
                  )}
                </>
              )
            )}

            {/* Credit Notes — every note raised on this policy in one place.
                The Invoicing tab only ever showed a note against the invoice it
                credits, so "how many notes are on this policy, for what, and
                where are the PDFs" had no answer short of reading every row. */}
            {ledgerTab === 'creditNote' && (
              creditNotes.length === 0 ? (
                <p className="text-sm text-ink-faint text-center py-4">No credit notes raised on this policy.</p>
              ) : (
                <>
                  <div className="flex flex-wrap items-center gap-x-6 gap-y-1 text-sm mb-2">
                    <span className="text-ink">
                      <span className="font-semibold">{creditNotes.length}</span>
                      {creditNotes.length === 1 ? ' credit note' : ' credit notes'} raised on this policy
                    </span>
                    <span className="text-ink-muted">
                      Total credited: <span className="font-semibold text-ink">{fmtCurrency(cnTotal)}</span>
                    </span>
                  </div>
                  {cnPg.paged.length === 0 ? (
                    <p className="text-sm text-ink-faint text-center py-4">No credit notes match this search.</p>
                  ) : (
                  <DualScrollTable>
                    <table className="w-full text-sm">
                      <thead className="bg-surface-2 text-ink-muted uppercase text-xs">
                        <tr>
                          <th className="px-3 py-2 text-left">Credit Note No.</th>
                          <th className="px-3 py-2 text-left">Against Invoice</th>
                          {/* The date the ledger and the Statement of Account
                              record this note under — editable via Edit Date. */}
                          <th className="px-3 py-2 text-left">Credit Note Date</th>
                          <th className="px-3 py-2 text-left">Credited Period</th>
                          <th className="px-3 py-2 text-right">Days</th>
                          <th className="px-3 py-2 text-right">Earned</th>
                          <th className="px-3 py-2 text-right">Unearned</th>
                          <th className="px-3 py-2 text-right">Amount Credited</th>
                          <th className="px-3 py-2 text-left">Created On</th>
                          <th className="px-3 py-2 text-left">Credit Note</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-line">
                        {cnPg.paged.map((c: LedgerCreditNoteEntry) => (
                          <tr key={c.id} className="hover:bg-surface-2">
                            <td className="px-3 py-2 whitespace-nowrap">
                              {/* The number IS the link — clicking a credit note
                                  should open the credit note. */}
                              <CreditNotePdfButton policyId={policyId} creditNoteId={c.id}
                                label={c.no || `#${c.id}`}
                                className="font-mono text-xs font-semibold text-primary underline hover:opacity-80 disabled:opacity-50" />
                            </td>
                            <td className="px-3 py-2 font-mono text-xs whitespace-nowrap">{c.invoiceNo || '—'}</td>
                            <td className="px-3 py-2 whitespace-nowrap">{c.date || '—'}</td>
                            <td className="px-3 py-2 whitespace-nowrap text-ink-muted text-xs">
                              {c.periodFrom || c.periodTo ? `${c.periodFrom ?? '—'} → ${c.periodTo ?? '—'}` : '—'}
                            </td>
                            <td className="px-3 py-2 text-right">{c.noOfDays ?? '—'}</td>
                            <td className="px-3 py-2 text-right">{c.earned ? fmtCurrency(c.earned) : '—'}</td>
                            <td className="px-3 py-2 text-right">{c.unearned ? fmtCurrency(c.unearned) : '—'}</td>
                            <td className="px-3 py-2 text-right font-medium">{c.amount ? fmtCurrency(c.amount) : '—'}</td>
                            <td className="px-3 py-2 whitespace-nowrap text-ink-muted">{c.createdAt || '—'}</td>
                            <td className="px-3 py-2">
                              <div className="flex items-center gap-1">
                                {/* Always rendered: the endpoint falls back to
                                    re-rendering the note, so a missing stored
                                    file is no longer a dead end. */}
                                <CreditNotePdfButton policyId={policyId} creditNoteId={c.id} />
                                {/* Edit Date needs the INVOICE ledger row the
                                    note was raised against; a note whose invoice
                                    can no longer be resolved gets no button
                                    rather than one that 422s. */}
                                {c.invoiceId ? (
                                  <CreditNoteDateButton policyId={policyId} ledgerId={c.invoiceId}
                                    onSaved={refreshLedger} />
                                ) : null}
                              </div>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </DualScrollTable>
                  )}
                  {cnPg.totalPages > 1 && (
                    <LedgerPagination current={cnPg.page} last={cnPg.totalPages} total={cnPg.total} onChange={cnPg.setPage} />
                  )}
                </>
              )
            )}

            {/* Sub Ledger */}
            {ledgerTab === 'subLedger' && (
              subPg.paged.length === 0 ? (
                <p className="text-sm text-ink-faint text-center py-4">No sub ledger entries.</p>
              ) : (
                <>
                  {subLedgerCapped && (
                    <p className="text-xs text-status-warning-fg bg-status-warning-bg border border-status-warning-fg rounded px-3 py-2 mb-2">
                      Showing the latest {subLedger.length.toLocaleString()} of {subLedgerTotal.toLocaleString()} sub ledger entries.
                    </p>
                  )}
                  <DualScrollTable>
                    <table className="w-full text-sm">
                      <thead className="bg-surface-2 text-ink-muted uppercase text-xs">
                        <tr>
                          <th className="px-3 py-2 text-left">ID</th>
                          <th className="px-3 py-2 text-left">System Date</th>
                          <th className="px-3 py-2 text-left">Trans Type</th>
                          <th className="px-3 py-2 text-left">Trans Ref</th>
                          <th className="px-3 py-2 text-left">Account Name</th>
                          <th className="px-3 py-2 text-right">Debit</th>
                          <th className="px-3 py-2 text-right">Credit</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-line">
                        {subPg.paged.map((l: LedgerSubEntry) => (
                          <tr key={l.id} className="hover:bg-surface-2">
                            <td className="px-3 py-2 text-ink-faint">{l.id}</td>
                            <td className="px-3 py-2 whitespace-nowrap">{l.systemDate || '—'}</td>
                            <td className="px-3 py-2">{l.transType || '—'}</td>
                            <td className="px-3 py-2 font-mono text-xs max-w-[160px] truncate" title={l.transRef || ''}>{l.transRef || '—'}</td>
                            <td className="px-3 py-2">{l.accountName || '—'}</td>
                            <td className="px-3 py-2 text-right">{l.debit ? fmtCurrency(l.debit) : '—'}</td>
                            <td className="px-3 py-2 text-right text-status-success-fg">{l.credit ? fmtCurrency(l.credit) : '—'}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </DualScrollTable>
                  {subPg.totalPages > 1 && (
                    <LedgerPagination current={subPg.page} last={subPg.totalPages} total={subPg.total} onChange={subPg.setPage} />
                  )}
                </>
              )
            )}
          </div>
        )}
      </Card>
    </div>
  )
}

function LedgerPagination({ current, last, total, onChange }: { current: number; last: number; total: number; onChange: (p: number) => void }) {
  return (
    <div className="flex items-center justify-between px-4 py-3 border-t border-line text-sm text-ink-muted">
      <span>{total} entries — Page {current} of {last}</span>
      <div className="flex gap-1">
        <button onClick={() => onChange(current - 1)} disabled={current <= 1} className="px-2 py-1 rounded border border-line text-xs disabled:opacity-40">Prev</button>
        <button onClick={() => onChange(current + 1)} disabled={current >= last} className="px-2 py-1 rounded border border-line text-xs disabled:opacity-40">Next</button>
      </div>
    </div>
  )
}

// ─── Tab: Banking (from main response) ──────────────────────────

function BankingTab({ policy, policyId }: { policy: Policy; policyId: number }) {
  const b = policy.banking
  const [editing, setEditing] = useState(false)
  const [form, setForm] = useState({ bankName: b?.bankName || '', branchCode: b?.branchCode || '', accountNumber: b?.accountNumber || '', bankingMethod: b?.billing || '', myzaka: b?.myzaka || '', orangeMoney: b?.orangeMoney || '', billing: b?.billing || '' })
  const [saving, setSaving] = useState(false)

  const qc = useQueryClient()
  const token = localStorage.getItem('sanctum_token')
  const headers: Record<string, string> = { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json', Accept: 'application/json' }
  const apiBase = `${(import.meta as any).env.VITE_API_URL}/api/v1`

  async function save() {
    setSaving(true)
    try {
      const r = await fetch(`${apiBase}/policies/${policyId}/banking`, { method: 'PUT', headers, body: JSON.stringify(form) })
      const d = await r.json()
      if (!r.ok) { alert(d.error || d.message || 'Failed'); setSaving(false); return }
      await qc.invalidateQueries({ queryKey: ['policy', policyId] })
      alert('Banking details updated.'); setEditing(false)
    } catch (e: any) { alert(e.message) }
    setSaving(false)
  }

  return (
    <div className="space-y-4">
      {/* Banking System / Method — select the customer's billing system and save */}
      <BankingSystemCard policy={policy} policyId={policyId} />

      {/* Banking documents — Bank Statement + Debit Authorization Form, with
          upload/replace/delete and per-document approve/reject + remark. */}
      <BankingDocumentsSection policyId={policyId} />

      <div className="flex justify-end">
        <button onClick={() => setEditing(!editing)} className="px-4 py-2 bg-ink text-white rounded text-sm hover:bg-ink">
          {editing ? 'Cancel Edit' : 'Edit Banking Details'}
        </button>
      </div>

      {editing ? (
        <Card title="Edit Banking Details">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {[
              { key: 'bankName', label: 'Bank Name' },
              { key: 'branchCode', label: 'Branch Code' },
              { key: 'accountNumber', label: 'Account Number' },
              { key: 'billing', label: 'Billing Method' },
              { key: 'myzaka', label: 'MyZaka Number' },
              { key: 'orangeMoney', label: 'Orange Money Number' },
            ].map(({ key, label }) => (
              <div key={key}>
                <label className="block text-xs font-medium text-ink-muted mb-1">{label}</label>
                <input value={(form as any)[key] || ''} onChange={e => setForm(p => ({ ...p, [key]: e.target.value }))}
                  className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none" />
              </div>
            ))}
          </div>
          <div className="flex justify-end gap-2 mt-4 pt-4 border-t border-line">
            <button onClick={() => setEditing(false)} className="px-4 py-2 text-sm border border-line rounded hover:bg-surface-2">Cancel</button>
            <button onClick={save} disabled={saving} className="px-4 py-2 text-sm bg-primary text-white rounded hover:bg-primary disabled:opacity-50">
              {saving ? 'Saving…' : 'Save Banking Details'}
            </button>
          </div>
        </Card>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <Card title="Bank Account">
            <InfoRow label="bankName" value={b?.bankName} />
            <InfoRow label="accountHolder" value={b?.accountHolder} />
            <InfoRow label="accountNumber" value={b?.accountNumber} />
            <InfoRow label="accountType" value={b?.accountType} />
            <InfoRow label="branchCode" value={b?.branchCode} />
            <InfoRow label="billing" value={b?.billing} />
            {b?.billingCell && <InfoRow label="billingCell" value={b.billingCell} />}
            {b?.orangeMoney && <InfoRow label="orangeMoney" value={b.orangeMoney} />}
            {b?.myzaka && <InfoRow label="myzaka" value={b.myzaka} />}
          </Card>

          {(b?.cardType || b?.cardHolderName) && (
            <Card title="Card Details">
              <InfoRow label="cardType" value={b?.cardType} />
              <InfoRow label="cardHolderName" value={b?.cardHolderName} />
              {b?.subscriptionId && <InfoRow label="subscriptionId" value={b.subscriptionId} />}
            </Card>
          )}
        </div>
      )}

      {!b && !editing && <EmptyState message="No banking details available. Click 'Edit Banking Details' to add." />}
    </div>
  )
}

// ─── Banking System / Method selector ───────────────────────────
// Port of the graphiteBWN8 policy-edit "Customer Banking" tab: pick the
// customer's banking system (billing method) and save it. Writes the
// `billing` field on customer_banking — the same field the tab-gating and
// legacy admin (PolicyController::action) read — plus the matching mobile
// wallet number when Orange Money is selected.
function BankingSystemCard({ policy, policyId }: { policy: Policy; policyId: number }) {
  const b = policy.banking
  const { data: lookups } = usePolicyCreateData()
  const methods: { id: string; name: string }[] = (lookups as any)?.billing_methods ?? [
    { id: 'DPO', name: 'DPO (Debit Order)' },
    { id: 'RealPay', name: 'RealPay (Direct Debit)' },
    { id: 'Orange', name: 'Orange Money' },
    { id: 'NGenius', name: 'NGenius (Card)' },
    { id: 'Cash', name: 'Cash' },
  ]

  const [billing, setBilling] = useState<string>(b?.billing || '')
  const [orangeMoney, setOrangeMoney] = useState<string>(b?.orangeMoney || '')
  const [myzaka, setMyzaka] = useState<string>(b?.myzaka || '')
  const [saving, setSaving] = useState(false)

  const qc = useQueryClient()
  const token = localStorage.getItem('sanctum_token')
  const headers: Record<string, string> = { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json', Accept: 'application/json' }
  const apiBase = `${(import.meta as any).env.VITE_API_URL}/api/v1`

  const isMobileWallet = billing === 'Orange'
  const dirty = billing !== (b?.billing || '') || orangeMoney !== (b?.orangeMoney || '') || myzaka !== (b?.myzaka || '')

  async function save() {
    if (!billing) { alert('Please select a banking system.'); return }
    setSaving(true)
    try {
      const payload: Record<string, string> = { billing, bankingMethod: billing }
      if (isMobileWallet) { payload.orangeMoney = orangeMoney; payload.myzaka = myzaka }
      const r = await fetch(`${apiBase}/policies/${policyId}/banking`, { method: 'PUT', headers, body: JSON.stringify(payload) })
      const d = await r.json()
      if (!r.ok) { alert(d.error || d.message || 'Failed'); setSaving(false); return }
      await qc.invalidateQueries({ queryKey: ['policy', policyId] })
      alert('Banking system updated.')
    } catch (e: any) { alert(e.message) }
    setSaving(false)
  }

  return (
    <Card title="Banking System">
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label className="block text-xs font-medium text-ink-muted mb-1">Banking System / Method</label>
          <select value={billing} onChange={e => setBilling(e.target.value)}
            className="w-full px-3 py-2 border border-line rounded text-sm bg-surface focus:ring-2 focus:ring-primary focus:outline-none">
            <option value="">— Select —</option>
            {methods.map(m => <option key={m.id} value={m.id}>{m.name}</option>)}
          </select>
        </div>
        {isMobileWallet && (
          <>
            <div>
              <label className="block text-xs font-medium text-ink-muted mb-1">Orange Money Number</label>
              <input value={orangeMoney} onChange={e => setOrangeMoney(e.target.value)} placeholder="7XXXXXXX"
                className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none" />
            </div>
            <div>
              <label className="block text-xs font-medium text-ink-muted mb-1">MyZaka Number</label>
              <input value={myzaka} onChange={e => setMyzaka(e.target.value)} placeholder="7XXXXXXX"
                className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none" />
            </div>
          </>
        )}
      </div>
      <div className="flex justify-end mt-4 pt-4 border-t border-line">
        <button onClick={save} disabled={saving || !dirty}
          className="px-4 py-2 text-sm bg-primary text-white rounded hover:bg-primary disabled:opacity-50">
          {saving ? 'Saving…' : 'Save Banking System'}
        </button>
      </div>
    </Card>
  )
}

// Header badge mirroring the KYC compliance label: compliant when at least
// one banking doc is approved. Shares the bankingDocuments query cache with
// BankingDocumentsSection, so approving/rejecting in the tab updates this too.
function BankingComplianceBadge({ policyId }: { policyId: number }) {
  const { data } = usePolicyBankingDocuments(policyId, true)
  if (!data) return null
  const compliant = data.documents.some((d) => d.status === 1)
  const label = compliant ? 'Banking Details Compliant' : 'Banking Details Non-Compliant'
  return (
    <span className={compliant ? 'text-status-success-fg font-medium' : 'text-status-warning-fg font-medium'} title={label}>
      {label}
    </span>
  )
}

// ─── Banking Documents (Bank Statement + Debit Authorization Form) ──
// Lives on the Banking Details tab. Upload tiles (reusing KycDocCard) plus
// a per-document approve/reject + remark panel. Stored on customer_banking
// via /policies/{id}/banking-documents (GET/POST/DELETE/verify).
function BankingDocumentsSection({ policyId }: { policyId: number }) {
  const { data, isLoading, refetch } = usePolicyBankingDocuments(policyId, true)
  const [previewUrl, setPreviewUrl] = useState<string | null>(null)
  const [queuedFiles, setQueuedFiles] = useState<Record<string, File>>({})
  const [submitting, setSubmitting] = useState(false)
  const [deletingField, setDeletingField] = useState<string | null>(null)
  const [submitError, setSubmitError] = useState<string | null>(null)

  // Banking-document maker/checker split: uploaders hold
  // policy-banking-documents-upload; approvers hold -verify (which still
  // implies upload). Empty permission list = allow (admin fallback); BE
  // re-checks the same permissions on every write.
  const perms = useMemo(() => {
    try { return JSON.parse(localStorage.getItem('user_permissions') || '[]') as string[] } catch { return [] }
  }, [])
  const canVerify = perms.length === 0 || perms.includes('policy-banking-documents-verify')
  const canUpload = canVerify || perms.includes('policy-banking-documents-upload')
  const noPermissionTooltip = 'You do not have permission to manage banking documents.'

  const docs = data?.documents ?? []
  // Banking Details is compliant when at least one of the two documents
  // (Bank Statement / Debit Authorization Form) is approved (status === 1).
  const bankingCompliant = docs.some((d) => d.status === 1)

  function queueFile(field: string, file: File | null) {
    setQueuedFiles(prev => {
      const next = { ...prev }
      if (!file) delete next[field]
      else next[field] = file
      return next
    })
  }

  async function submitUploads() {
    if (Object.keys(queuedFiles).length === 0) {
      setSubmitError('Pick at least one file to upload (click the pencil on a card).')
      return
    }
    setSubmitError(null)
    setSubmitting(true)
    try {
      const fd = new FormData()
      Object.entries(queuedFiles).forEach(([field, file]) => fd.append(field, file))
      await apiClient.post(`/policies/${policyId}/banking-documents`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      setQueuedFiles({})
      refetch()
    } catch (e: any) {
      setSubmitError(e?.response?.data?.message || e?.response?.data?.error || 'Upload failed')
    } finally {
      setSubmitting(false)
    }
  }

  async function handleDelete(field: string, label: string) {
    if (!confirm(`Delete the uploaded ${label}?`)) return
    setDeletingField(field)
    try {
      await apiClient.delete(`/policies/${policyId}/banking-documents/${encodeURIComponent(field)}`)
      refetch()
    } catch (e: any) {
      alert(e?.response?.data?.message || e?.response?.data?.error || 'Delete failed')
    } finally {
      setDeletingField(null)
    }
  }

  return (
    <div className="bg-surface rounded-lg border border-line p-4 space-y-4">
      <div className="flex items-center justify-between gap-2">
        <h3 className="text-base font-semibold text-ink">Banking Documents</h3>
        {!!data && (
          <span className={`px-2.5 py-1 rounded-full text-xs font-medium ${
            bankingCompliant ? 'bg-status-success-bg text-status-success-fg' : 'bg-status-warning-bg text-status-warning-fg'
          }`}>
            {bankingCompliant ? 'Banking Details Compliant' : 'Banking Details Non-Compliant'}
          </span>
        )}
      </div>

      {isLoading && !data ? (
        <div className="p-4 text-sm text-ink-muted">Loading documents…</div>
      ) : (
        <>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-2xl">
            {docs.map((doc) => (
              <BankingDocCard
                key={doc.field}
                doc={doc}
                policyId={policyId}
                canUpload={canUpload}
                canVerify={canVerify}
                disabledTooltip={noPermissionTooltip}
                queued={queuedFiles[doc.field]}
                deleting={deletingField === doc.field}
                onQueue={(f) => queueFile(doc.field, f)}
                onPreview={() => doc.url && setPreviewUrl(doc.url)}
                onDelete={() => handleDelete(doc.field, doc.label)}
                onVerified={refetch}
              />
            ))}
          </div>

          <div className="flex flex-col items-start pt-1">
            {submitError && <p className="text-xs text-status-danger-fg mb-2">{submitError}</p>}
            {!canUpload && <p className="text-xs text-status-warning-fg mb-2">{noPermissionTooltip}</p>}
            <button
              type="button"
              onClick={submitUploads}
              disabled={!canUpload || submitting || Object.keys(queuedFiles).length === 0}
              title={!canUpload ? noPermissionTooltip : undefined}
              className="px-6 py-2 text-sm font-medium rounded-md bg-primary text-white hover:bg-primary disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {submitting
                ? 'Uploading…'
                : `Submit${Object.keys(queuedFiles).length > 0 ? ` (${Object.keys(queuedFiles).length})` : ''}`}
            </button>
          </div>
        </>
      )}

      <DocumentPreviewModal url={previewUrl} onClose={() => setPreviewUrl(null)} />
    </div>
  )
}

// Single banking-document card: the KYC upload tile + a status badge and an
// approve/reject + remark panel (mirrors the KYC review DocumentCard).
function BankingDocCard({
  doc, policyId, canUpload, canVerify, disabledTooltip, queued, deleting,
  onQueue, onPreview, onDelete, onVerified,
}: {
  doc: import('../../api/policies').BankingDocument
  policyId: number
  canUpload: boolean
  /** Approve/reject rights (policy-banking-documents-verify) — upload alone doesn't grant these. */
  canVerify: boolean
  disabledTooltip: string
  queued?: File
  deleting: boolean
  onQueue: (file: File | null) => void
  onPreview: () => void
  onDelete: () => void
  onVerified: () => void
}) {
  const [remark, setRemark] = useState(doc.remark || '')
  const [showActions, setShowActions] = useState(false)
  const [busy, setBusy] = useState(false)

  const st = doc.status === 1
    ? { label: 'Approved', cls: 'bg-status-success-bg text-status-success-fg' }
    : doc.status === 2
      ? { label: 'Rejected', cls: 'bg-status-danger-bg text-status-danger-fg' }
      : { label: 'Pending', cls: 'bg-status-warning-bg text-status-warning-fg' }

  async function verify(status: number) {
    setBusy(true)
    try {
      await apiClient.post(`/policies/${policyId}/banking-documents/verify`, { document: doc.field, status, remark })
      setShowActions(false)
      onVerified()
    } catch (e: any) {
      alert(e?.response?.data?.message || e?.response?.data?.error || 'Verification failed')
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="space-y-2">
      <KycDocCard
        label={doc.label}
        url={doc.url}
        hasFile={doc.hasFile}
        queued={queued}
        deleting={deleting}
        canUpload={canUpload}
        disabledTooltip={disabledTooltip}
        onQueue={onQueue}
        onPreview={onPreview}
        onDelete={onDelete}
      />

      {doc.hasFile && (
        <div className="flex justify-center">
          <span className={`px-2 py-0.5 rounded-full text-[10px] font-medium ${st.cls}`}>{st.label}</span>
        </div>
      )}

      {doc.remark && !showActions && (
        <p className="text-[10px] text-ink-muted text-center px-1">Remark: {doc.remark}</p>
      )}

      {doc.hasFile && canVerify && (
        showActions ? (
          <div className="space-y-2">
            <input type="text" placeholder="Remark…" value={remark} onChange={e => setRemark(e.target.value)}
              className="w-full px-2 py-1.5 border border-line rounded text-sm" />
            <div className="flex gap-2">
              <button onClick={() => verify(1)} disabled={busy}
                className="flex-1 py-1.5 bg-status-success-fg text-white rounded text-xs font-medium hover:bg-status-success-fg disabled:opacity-50">Approve</button>
              <button onClick={() => verify(2)} disabled={busy}
                className="flex-1 py-1.5 bg-status-danger-fg text-white rounded text-xs font-medium hover:bg-status-danger-fg disabled:opacity-50">Reject</button>
              <button onClick={() => verify(0)} disabled={busy}
                className="py-1.5 px-3 bg-surface-2 text-ink-muted rounded text-xs hover:bg-surface-2 disabled:opacity-50">Reset</button>
              <button onClick={() => setShowActions(false)}
                className="py-1.5 px-3 bg-surface-2 text-ink-muted rounded text-xs hover:bg-surface-2">Cancel</button>
            </div>
          </div>
        ) : (
          <button onClick={() => setShowActions(true)}
            className="w-full py-1.5 border border-line rounded text-sm text-ink-muted hover:bg-surface-2 font-medium">
            Verify Document
          </button>
        )
      )}
    </div>
  )
}

// ─── No Claims Declaration: sign via OTP ───
//
// EXCO brief (2026-09-08): replace the print → sign → scan → upload loop.
// Step 1 SMSes an OTP to the customer's REGISTERED cellphone (server-side
// lookup; the agent cannot retarget it). The SMS says that sharing the code
// with the agent is consent to sign. Step 2: the agent keys the code in; on
// success the backend renders a PDF stating it was signed via OTP, with the
// number and timestamp, and files it as the current declaration.

function NcdOtpSignModal({ policyId, meta, onClose, onSigned }: {
  policyId: number
  meta: { hasCellphone: boolean; cellphoneMasked: string | null; validitySeconds: number; cooldownSeconds: number }
  onClose: () => void
  onSigned: () => void
}) {
  const [step, setStep] = useState<'send' | 'verify' | 'done'>('send')
  const [sending, setSending] = useState(false)
  const [verifying, setVerifying] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [code, setCode] = useState('')
  const [sentTo, setSentTo] = useState<string | null>(meta.cellphoneMasked)
  const [expiresAt, setExpiresAt] = useState<number | null>(null)
  const [cooldownLeft, setCooldownLeft] = useState(0)
  const [attemptsLeft, setAttemptsLeft] = useState<number | null>(null)
  const [now, setNow] = useState(Date.now())

  // One-second tick for the expiry countdown and the resend cooldown.
  useEffect(() => {
    const t = setInterval(() => setNow(Date.now()), 1000)
    return () => clearInterval(t)
  }, [])
  useEffect(() => {
    if (cooldownLeft <= 0) return
    const t = setTimeout(() => setCooldownLeft(c => Math.max(0, c - 1)), 1000)
    return () => clearTimeout(t)
  }, [cooldownLeft])

  const secondsLeft = expiresAt ? Math.max(0, Math.floor((expiresAt - now) / 1000)) : null
  const expired = secondsLeft === 0

  async function send() {
    setError(null)
    setSending(true)
    try {
      const res = await sendClaimsWaiverOtp(policyId)
      setSentTo(res.data.cellphoneMasked)
      setExpiresAt(new Date(res.data.expiresAt).getTime())
      setCooldownLeft(meta.cooldownSeconds)
      setAttemptsLeft(null)
      setCode('')
      setStep('verify')
    } catch (e: any) {
      const wait = e?.response?.data?.wait_s
      if (wait) setCooldownLeft(Number(wait))
      setError(e?.response?.data?.message || e?.response?.data?.error || 'Could not send the OTP.')
    } finally {
      setSending(false)
    }
  }

  async function verify() {
    if (!/^\d{6}$/.test(code)) {
      setError('Enter the 6-digit code the customer received.')
      return
    }
    setError(null)
    setVerifying(true)
    try {
      await verifyClaimsWaiverOtp(policyId, code)
      setStep('done')
      onSigned()
    } catch (e: any) {
      const left = e?.response?.data?.attempts_left
      setAttemptsLeft(typeof left === 'number' ? left : null)
      setError(e?.response?.data?.message || e?.response?.data?.error || 'Verification failed.')
      if (['expired', 'locked', 'no_active_otp'].includes(e?.response?.data?.error)) {
        setExpiresAt(null)
      }
    } finally {
      setVerifying(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
      <div className="bg-surface rounded-lg shadow-xl w-full max-w-md" onClick={e => e.stopPropagation()}>
        <div className="flex items-center justify-between px-5 py-3 border-b border-line">
          <h3 className="text-base font-semibold text-ink">Sign No Claims Declaration via OTP</h3>
          <button type="button" onClick={onClose} className="text-ink-muted hover:text-ink text-xl leading-none" aria-label="Close">×</button>
        </div>

        <div className="px-5 py-4 space-y-4 text-sm">
          {step === 'send' && (
            <>
              <p className="text-ink">
                A one-time code will be sent by SMS to the customer's registered cellphone
                {sentTo ? <> <span className="font-semibold">{sentTo}</span></> : ''}.
              </p>
              <p className="text-xs text-ink-muted bg-surface-2 rounded-md px-3 py-2">
                The SMS tells the customer that sharing the code with you is their consent to sign the declaration.
                Confirm the customer has the phone with them before you send.
              </p>
              {!meta.hasCellphone && (
                <p className="text-xs text-status-danger-fg">
                  The customer has no valid registered cellphone number. Update the customer record first.
                </p>
              )}
            </>
          )}

          {step === 'verify' && (
            <>
              <p className="text-ink">
                Code sent to <span className="font-semibold">{sentTo}</span>.
                {secondsLeft !== null && !expired && (
                  <span className="text-ink-muted"> Expires in {Math.floor(secondsLeft / 60)}:{String(secondsLeft % 60).padStart(2, '0')}.</span>
                )}
                {expired && <span className="text-status-danger-fg"> The code has expired — send a new one.</span>}
              </p>
              <label className="block">
                <span className="block text-xs font-medium text-ink-muted mb-1">6-digit code from the customer</span>
                <input
                  type="text"
                  inputMode="numeric"
                  autoComplete="one-time-code"
                  maxLength={6}
                  value={code}
                  onChange={e => setCode(e.target.value.replace(/\D/g, ''))}
                  onKeyDown={e => { if (e.key === 'Enter' && !verifying) verify() }}
                  disabled={expired}
                  className="w-full px-3 py-2 border border-line rounded-md text-lg tracking-[0.4em] text-center font-mono bg-surface text-ink focus:ring-2 focus:ring-primary focus:outline-none disabled:opacity-50"
                  placeholder="••••••"
                  autoFocus
                />
              </label>
              {attemptsLeft !== null && attemptsLeft > 0 && (
                <p className="text-xs text-status-warning-fg">{attemptsLeft} attempt{attemptsLeft === 1 ? '' : 's'} left before this code is locked.</p>
              )}
            </>
          )}

          {step === 'done' && (
            <div className="rounded-md bg-status-success-bg text-status-success-fg px-3 py-3">
              <p className="font-semibold">Declaration signed and generated.</p>
              <p className="text-xs mt-1">The PDF records that it was signed via OTP to {sentTo}, with the timestamp. It is now pending approval, like an uploaded form.</p>
            </div>
          )}

          {error && <p className="text-xs text-status-danger-fg">{error}</p>}
        </div>

        <div className="flex justify-end gap-2 px-5 py-3 border-t border-line">
          {step !== 'done' ? (
            <>
              <button type="button" onClick={onClose} className="px-4 py-2 text-sm border border-line rounded-md text-ink hover:bg-surface-2">Cancel</button>
              {step === 'send' && (
                <button
                  type="button"
                  onClick={send}
                  disabled={sending || !meta.hasCellphone || cooldownLeft > 0}
                  className="px-4 py-2 text-sm font-medium rounded-md bg-primary text-primary-contrast hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {sending ? 'Sending…' : cooldownLeft > 0 ? `Send OTP (${cooldownLeft}s)` : 'Send OTP'}
                </button>
              )}
              {step === 'verify' && (
                <>
                  <button
                    type="button"
                    onClick={send}
                    disabled={sending || cooldownLeft > 0}
                    className="px-4 py-2 text-sm border border-line rounded-md text-ink hover:bg-surface-2 disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    {sending ? 'Sending…' : cooldownLeft > 0 ? `Resend (${cooldownLeft}s)` : 'Resend'}
                  </button>
                  <button
                    type="button"
                    onClick={verify}
                    disabled={verifying || expired || code.length !== 6}
                    className="px-4 py-2 text-sm font-medium rounded-md bg-primary text-primary-contrast hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    {verifying ? 'Verifying…' : 'Verify & Sign'}
                  </button>
                </>
              )}
            </>
          ) : (
            <button type="button" onClick={onClose} className="px-4 py-2 text-sm font-medium rounded-md bg-primary text-primary-contrast hover:opacity-90">Done</button>
          )}
        </div>
      </div>
    </div>
  )
}

// ─── Tab: No Claims Declaration (MIS policies only, lazy loaded) ───

function ClaimsWaiverTab({ policyId }: { policyId: number }) {
  const { data, isLoading, refetch } = usePolicyClaimsWaiver(policyId, true)
  const [previewUrl, setPreviewUrl] = useState<string | null>(null)
  const [queued, setQueued] = useState<File | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [deleting, setDeleting] = useState(false)
  const [deciding, setDeciding] = useState(false)
  const [submitError, setSubmitError] = useState<string | null>(null)
  const [otpOpen, setOtpOpen] = useState(false)

  // Same permission-gate convention as Banking Documents: empty permission
  // list = allow (admin fallback); BE re-checks hasPermissionTo on every write.
  const perms = useMemo(() => {
    try { return JSON.parse(localStorage.getItem('user_permissions') || '[]') as string[] } catch { return [] }
  }, [])
  // Admin-equivalent roles pass by role, mirroring the backend's
  // AuthGate::canPerform gate. Needed because `policy-claims-waiver-upload`
  // does not exist in every environment's `permissions` table and /roles has
  // no endpoint to create it, so a permission-only check hid the button from
  // Super Admin with no way to grant it.
  const isAdminOrManager = useMemo(() => {
    try {
      const roles = JSON.parse(localStorage.getItem('user_roles') || '[]') as string[]
      return roles.some(r => { const s = String(r).toLowerCase(); return s.includes('admin') || s.includes('manager') })
    } catch { return false }
  }, [])
  // The backend now returns the authoritative answer (`mayUpload`); the
  // localStorage heuristic is only the fallback for an API that predates it.
  const localCanManage = perms.length === 0 || isAdminOrManager || perms.includes('policy-claims-waiver-upload')
  const canManage = data?.mayUpload ?? localCanManage
  const noPermissionTooltip = 'You do not have permission to manage the No Claims Declaration.'

  // Approval (2026-08-31). Upload and approval are deliberately separate:
  // Underwriting uploads, and only a holder of the approver role signs off —
  // admin roles do NOT bypass that, so this flag comes from the backend only.
  const status = data?.hasFile ? (data.status || 'PENDING') : null
  const mayApprove = !!data?.mayApprove
  const isApproved = status === 'APPROVED'
  // An approved declaration is a signed-off record: replacing or deleting it
  // withdraws the approval, so that stays with the approvers.
  const canReplace = canManage && (!isApproved || mayApprove)
  const lockedTooltip = 'This declaration is approved. Only an approver can replace or delete it.'
  // Any number of people may hold the approver role (the two-person cap was
  // removed 2026-09-07), so the "awaiting" line lists the first few and counts
  // the rest instead of running off the width of the tab.
  const approverNames = useMemo(() => {
    const names = (data?.approvers ?? []).map(a => a.name).filter(Boolean) as string[]
    if (names.length === 0) return ''
    if (names.length <= 3) return names.join(' or ')
    return `${names.slice(0, 3).join(', ')} or ${names.length - 3} other approver${names.length - 3 === 1 ? '' : 's'}`
  }, [data?.approvers])
  const history = data?.history ?? []
  const HISTORY_LABEL: Record<string, string> = {
    UPLOADED: 'Uploaded',
    APPROVED: 'Approved',
    REJECTED: 'Rejected',
  }
  const HISTORY_STYLE: Record<string, string> = {
    UPLOADED: 'text-ink-muted',
    APPROVED: 'text-status-success-fg',
    REJECTED: 'text-status-danger-fg',
  }

  const STATUS_STYLE: Record<string, string> = {
    PENDING:  'bg-status-warning-bg text-status-warning-fg',
    APPROVED: 'bg-status-success-bg text-status-success-fg',
    REJECTED: 'bg-status-danger-bg text-status-danger-fg',
  }
  const STATUS_LABEL: Record<string, string> = {
    PENDING:  'Pending approval',
    APPROVED: 'Approved',
    REJECTED: 'Rejected',
  }

  async function submitUpload() {
    if (!queued) {
      setSubmitError('Pick a file to upload first.')
      return
    }
    setSubmitError(null)
    setSubmitting(true)
    try {
      const fd = new FormData()
      fd.append('file', queued)
      await apiClient.post(`/policies/${policyId}/claims-waiver`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      setQueued(null)
      refetch()
    } catch (e: any) {
      setSubmitError(e?.response?.data?.message || e?.response?.data?.error || 'Upload failed')
    } finally {
      setSubmitting(false)
    }
  }

  async function handleDelete() {
    if (!confirm('Delete the uploaded No Claims Declaration?')) return
    setDeleting(true)
    try {
      await apiClient.delete(`/policies/${policyId}/claims-waiver`)
      refetch()
    } catch (e: any) {
      alert(e?.response?.data?.message || e?.response?.data?.error || 'Delete failed')
    } finally {
      setDeleting(false)
    }
  }

  // Approve, or reject with a reason. One approver's decision is final; the
  // second role holder exists so approval does not stall on an absence.
  async function decide(reject: boolean) {
    let reason = ''
    if (reject) {
      const entered = prompt('Reason for rejecting this No Claims Declaration (optional):', '')
      if (entered === null) return
      reason = entered.trim()
    } else if (!confirm('Approve this No Claims Declaration?')) {
      return
    }
    setSubmitError(null)
    setDeciding(true)
    try {
      await decidePolicyClaimsWaiver(policyId, { reject, reason })
      refetch()
    } catch (e: any) {
      setSubmitError(e?.response?.data?.message || e?.response?.data?.error || 'Could not record the decision')
    } finally {
      setDeciding(false)
    }
  }

  return (
    <div className="bg-surface rounded-lg border border-line p-4 space-y-4">
      <div className="flex items-center justify-between gap-2">
        <div className="flex items-center gap-2">
          <h3 className="text-base font-semibold text-ink">No Claims Declaration</h3>
          {status && (
            <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_STYLE[status] || STATUS_STYLE.PENDING}`}>
              {STATUS_LABEL[status] || status}
            </span>
          )}
        </div>
        {data?.hasFile && data.url && (
          <a href={data.url} target="_blank" rel="noreferrer" download
            className="text-sm text-primary hover:underline">
            Download
          </a>
        )}
      </div>

      {/* Set-up gap (approver role missing or unassigned). Only the backend
          knows, and only users who could act on it are told. */}
      {data?.warning && (
        <p className="text-xs text-status-warning-fg bg-status-warning-bg rounded-md px-3 py-2">{data.warning}</p>
      )}

      {isLoading && !data ? (
        <div className="p-4 text-sm text-ink-muted">Loading document…</div>
      ) : (
        <>
          <div className="max-w-[220px]">
            <KycDocCard
              label={data?.hasFile ? (data.name || 'No Claims Declaration') : 'No Claims Declaration'}
              url={data?.url ?? null}
              hasFile={!!data?.hasFile}
              queued={queued ?? undefined}
              deleting={deleting}
              canUpload={canReplace}
              disabledTooltip={canManage && !canReplace ? lockedTooltip : noPermissionTooltip}
              onQueue={(f) => setQueued(f)}
              onPreview={() => data?.url && setPreviewUrl(data.url)}
              onDelete={handleDelete}
            />
          </div>

          {/* Audit trail: who uploaded, who decided, and why if rejected. */}
          {data?.hasFile && (
            <div className="text-xs text-ink-muted space-y-1">
              {data.signature?.method === 'OTP' && (
                <p className="text-status-success-fg font-medium">
                  Signed by the customer via OTP to {data.signature.cellphoneMasked}
                  {data.signature.signedAt ? ` on ${fmtDate(data.signature.signedAt)}` : ''}
                </p>
              )}
              {data.uploadedByName && <p>{data.signature ? 'Generated' : 'Uploaded'} by {data.uploadedByName}{data.uploadedAt ? ` on ${fmtDate(data.uploadedAt)}` : ''}</p>}
              {isApproved && (
                <p className="text-status-success-fg">
                  Approved{data.approvedByName ? ` by ${data.approvedByName}` : ''}{data.approvedAt ? ` on ${fmtDate(data.approvedAt)}` : ''}
                </p>
              )}
              {status === 'REJECTED' && (
                <p className="text-status-danger-fg">
                  Rejected{data.approvedByName ? ` by ${data.approvedByName}` : ''}{data.approvedAt ? ` on ${fmtDate(data.approvedAt)}` : ''}
                  {data.rejectionReason ? ` — ${data.rejectionReason}` : ''}
                </p>
              )}
              {status === 'PENDING' && (
                <p>{approverNames ? `Awaiting approval by ${approverNames}.` : 'Awaiting approval. No approver is assigned yet.'}</p>
              )}
            </div>
          )}

          <div className="flex flex-col items-start pt-1">
            {submitError && <p className="text-xs text-status-danger-fg mb-2">{submitError}</p>}
            {!canManage && <p className="text-xs text-status-warning-fg mb-2">{noPermissionTooltip}</p>}
            {canManage && !canReplace && <p className="text-xs text-status-warning-fg mb-2">{lockedTooltip}</p>}
            <div className="flex flex-wrap items-center gap-2">
              <button
                type="button"
                onClick={submitUpload}
                disabled={!canReplace || submitting || !queued}
                title={!canManage ? noPermissionTooltip : (!canReplace ? lockedTooltip : undefined)}
                className="px-6 py-2 text-sm font-medium rounded-md bg-primary text-white hover:bg-primary disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {submitting ? 'Uploading…' : (data?.hasFile ? 'Replace' : 'Upload')}
              </button>

              {/* OTP e-signature path. Same gate as upload/replace; hidden when
                  the backend says the feature is not migrated on this env. */}
              {data?.otpSign?.available && (
                <button
                  type="button"
                  onClick={() => setOtpOpen(true)}
                  disabled={!canReplace || submitting}
                  title={!canManage ? noPermissionTooltip : (!canReplace ? lockedTooltip : 'Send an OTP to the customer and generate a digitally signed declaration')}
                  className="px-6 py-2 text-sm font-medium rounded-md border border-primary text-primary hover:bg-surface-2 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Generate via OTP
                </button>
              )}

              {/* Approver-only. Hidden entirely for everyone else — the
                  backend returns 403 regardless of what is rendered. */}
              {data?.hasFile && mayApprove && !isApproved && (
                <button
                  type="button"
                  onClick={() => decide(false)}
                  disabled={deciding}
                  className="px-6 py-2 text-sm font-medium rounded-md bg-status-success-fg text-white hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {deciding ? 'Saving…' : 'Approve'}
                </button>
              )}
              {data?.hasFile && mayApprove && status !== 'REJECTED' && (
                <button
                  type="button"
                  onClick={() => decide(true)}
                  disabled={deciding}
                  className="px-6 py-2 text-sm font-medium rounded-md border border-status-danger-fg text-status-danger-fg hover:bg-status-danger-bg disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {deciding ? 'Saving…' : 'Reject'}
                </button>
              )}
            </div>
          </div>

          {/* Approve / reject log. Every upload and decision, newest first,
              kept even after the document is replaced or deleted — so who
              signed a declaration off, and when, stays answerable. */}
          {history.length > 0 && (
            <div className="pt-2 border-t border-line">
              <h4 className="text-sm font-semibold text-ink mb-2">Approval history</h4>
              <div className="overflow-x-auto">
                <table className="min-w-full text-xs">
                  <thead>
                    <tr className="text-left text-ink-muted">
                      <th className="py-1 pr-4 font-medium">Action</th>
                      <th className="py-1 pr-4 font-medium">By</th>
                      <th className="py-1 pr-4 font-medium">Date</th>
                      <th className="py-1 pr-4 font-medium">Document</th>
                      <th className="py-1 font-medium">Reason</th>
                    </tr>
                  </thead>
                  <tbody>
                    {history.map(h => (
                      <tr key={h.id} className="border-t border-line align-top">
                        <td className={`py-1.5 pr-4 font-medium ${HISTORY_STYLE[h.action] || ''}`}>
                          {HISTORY_LABEL[h.action] || h.action}
                        </td>
                        <td className="py-1.5 pr-4 text-ink">{h.byName || '—'}</td>
                        <td className="py-1.5 pr-4 text-ink-muted whitespace-nowrap">{fmtDate(h.at)}</td>
                        <td className="py-1.5 pr-4 text-ink-muted break-all">{h.fileName || '—'}</td>
                        <td className="py-1.5 text-ink-muted">{h.reason || '—'}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </>
      )}

      <DocumentPreviewModal url={previewUrl} onClose={() => setPreviewUrl(null)} />

      {otpOpen && data?.otpSign && (
        <NcdOtpSignModal
          policyId={policyId}
          meta={data.otpSign}
          onClose={() => setOtpOpen(false)}
          onSigned={() => { setQueued(null); refetch() }}
        />
      )}
    </div>
  )
}

// ─── Tab: Risk Addresses (lazy loaded) ──────────────────────────

const BLANK_RA = {
  address_name: '', 
  physical_address: '', 
  risk_state: '', 
  risk_city: '', 
  extension: '',
  occupation: '',
  year_built: '',
  area: '',
  structure_type: '',
  const_type: '',
  town_class: '',
  risk_class: '',
  iso_rcv: '',
  usage: '',
  occupancy_type: '',
  central_fire: false,
  central_burglar: false,
  gated_community: false,
  automatic: false,
  distance_to_water: '',
  distance_to_fire: '',
  distance_to_hydrant: '',
  lat: '',
  lng: ''
}

function RiskAddressesTab({ policyId, policy: _policy }: { policyId: number; policy?: any }) {
  // Risk addresses are versioned per policy_action — every endorsement /
  // renewal gets its own risk_address rows (PolicyAction::replicateRecords
  // re-points coverages at the new action's copy). So the tab is scoped to a
  // selected action: the list, Add, Edit and Delete all target that action's
  // rows only. Mirrors the Coverages tab selector.
  const actionsQuery = usePolicyActions(policyId, true)
  const actions = actionsQuery.data?.history || []
  const currentAction = actionsQuery.data?.current
  const [selectedActionId, setSelectedActionId] = useState<number | null>(null)
  const effectiveActionId = selectedActionId ?? currentAction?.id ?? null

  useEffect(() => {
    if (currentAction && !selectedActionId) setSelectedActionId(currentAction.id)
  }, [currentAction])

  // Only a QUOTE action may be changed — same lock as the Coverages tab. An
  // ISSUED / APPROVED transaction has already been printed and rated, so its
  // risk addresses are frozen: Unissue it or raise a new endorsement. Blank
  // status (legacy rows) stays editable, matching the backend guard.
  const selectedAction = actions.find((a: any) => a.id === effectiveActionId) || currentAction
  const actionStatus = selectedAction?.status || ''
  const isEditable = !actionStatus || actionStatus === 'QUOTE'

  const { data: addresses, isLoading, refetch } = usePolicyRiskAddresses(policyId, !actionsQuery.isLoading, null, effectiveActionId)
  const [modal, setModal] = useState<{ open: boolean; editing: any | null }>({ open: false, editing: null })
  const [form, setForm] = useState<any>(BLANK_RA)
  const [saving, setSaving] = useState(false)
  const [deleting, setDeleting] = useState<number | null>(null)
  const [states, setStates] = useState<any[]>([])
  const [cities, setCities] = useState<any[]>([])
  const [constructionTypes, setConstructionTypes] = useState<any[]>([])
  const [_loadingDropdowns, setLoadingDropdowns] = useState(false)
  const [cityCache, setCityCache] = useState<Record<string, any[]>>({})
  const [currentTermId, setCurrentTermId] = useState<number | null>(null)
  const [currentActionId, setCurrentActionId] = useState<number | null>(null)

  const token = localStorage.getItem('sanctum_token')
  const headers: Record<string, string> = { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json', Accept: 'application/json' }
  const apiBase = `${(import.meta as any).env.VITE_API_URL}/api/v1`

  // Preload lookup dropdowns as soon as the tab loads
  useEffect(() => {
    if (states.length === 0) {
      loadDropdownData()
    }
  }, [])

  // Term/action metadata always follows the SELECTED action, so a new address
  // is written onto the action the operator is looking at (not the latest
  // QUOTE, which is what /edit-data defaults to).
  useEffect(() => {
    if (effectiveActionId) loadPolicyEditData(effectiveActionId)
  }, [effectiveActionId])

  // Fetch cities when state changes
  useEffect(() => {
    if (form.risk_state && modal.open) {
      loadCitiesForState(form.risk_state)
    } else {
      setCities([])
    }
  }, [form.risk_state, modal.open])

  async function loadDropdownData() {
    setLoadingDropdowns(true)
    try {
      const response = await fetch(`${apiBase}/lookups/policy-create`, { headers })
      const body = await response.json()
      const lookupData = body.data || {}
      setStates(lookupData.states || [])
      setConstructionTypes(lookupData.construction_types || [])
    } catch (e) {
      console.error('Failed to load dropdowns:', e)
    }
    setLoadingDropdowns(false)
  }

  async function loadPolicyEditData(actionId?: number | null) {
    try {
      const qs = actionId ? `?action_id=${actionId}` : ''
      const response = await fetch(`${apiBase}/policies/${policyId}/edit-data${qs}`, { headers })
      const body = await response.json()
      const editData = body.data || {}
      setCurrentTermId(editData.term_id ?? null)
      setCurrentActionId(editData.action_id ?? null)
    } catch (e) {
      console.error('Failed to load policy term/action data:', e)
    }
  }

  async function loadCitiesForState(stateId: number | string) {
    if (!stateId) {
      setCities([])
      return []
    }

    const cacheKey = String(stateId)
    if (cityCache[cacheKey]) {
      setCities(cityCache[cacheKey])
      return cityCache[cacheKey]
    }

    try {
      const response = await fetch(`${apiBase}/lookups/states/${stateId}/cities`, { headers })
      const citiesData = await response.json()
      const list = citiesData.data || []
      setCityCache((prev) => ({ ...prev, [cacheKey]: list }))
      setCities(list)
      return list
    } catch (e) {
      console.error('Failed to load cities:', e)
      setCities([])
      return []
    }
  }

  function getStateId(value: string | number | null | undefined) {
    if (value === null || value === undefined || value === '') return ''
    const valueStr = String(value).trim()
    if (states.some((s) => String(s.id) === valueStr)) return valueStr
    const match = states.find((s) => String(s.name).trim().toLowerCase() === valueStr.toLowerCase())
    return match ? String(match.id) : valueStr
  }

  function getCityId(value: string | number | null | undefined, cityList: any[] = cities) {
    if (value === null || value === undefined || value === '') return ''
    const valueStr = String(value).trim()
    if (cityList.some((c) => String(c.id) === valueStr)) return valueStr
    const match = cityList.find((c) => String(c.name).trim().toLowerCase() === valueStr.toLowerCase())
    return match ? String(match.id) : valueStr
  }

  useEffect(() => {
    if (modal.open && states.length > 0 && form.risk_state) {
      const normalizedState = getStateId(form.risk_state)
      if (normalizedState && normalizedState !== String(form.risk_state)) {
        setForm((p: any) => ({ ...p, risk_state: normalizedState, risk_city: '' }))
      }
    }
  }, [states, modal.open])

  useEffect(() => {
    if (modal.open && cities.length > 0 && form.risk_city) {
      const normalizedCity = getCityId(form.risk_city)
      if (normalizedCity && normalizedCity !== String(form.risk_city)) {
        setForm((p: any) => ({ ...p, risk_city: normalizedCity }))
      }
    }
  }, [cities, modal.open])

  function openAdd() { setForm(BLANK_RA); setModal({ open: true, editing: null }) }
  async function openEdit(r: any) {
    if (states.length === 0) {
      await loadDropdownData()
    }

    const stateValue = r.risk_state ?? r.state_id ?? r.state ?? r.stateId ?? ''
    const selectedStateId = getStateId(stateValue)

    let selectedCityId = ''
    if (selectedStateId) {
      if (cityCache[String(selectedStateId)]) {
        setCities(cityCache[String(selectedStateId)])
        selectedCityId = getCityId(r.risk_city ?? r.city_id ?? r.city ?? r.cityId ?? '', cityCache[String(selectedStateId)])
      } else {
        const cityList = await loadCitiesForState(selectedStateId)
        selectedCityId = getCityId(r.risk_city ?? r.city_id ?? r.city ?? r.cityId ?? '', cityList)
      }
    }

    setForm({ 
      address_name: r.addressName ?? r.address_name ?? r.address ?? '', 
      physical_address: r.physical_address ?? r.address ?? '', 
      risk_state: selectedStateId,
      risk_city: selectedCityId,
      extension: r.extension ?? r.zipCode ?? '',
      occupation: r.occupation ?? '',
      year_built: r.year_built ?? r.yearBuilt ?? '',
      area: r.area ?? '',
      structure_type: r.structure_type ?? r.structureType ?? '',
      const_type: r.const_type ?? r.constructionType ?? r.constType ?? '',
      town_class: r.town_class ?? r.townClass ?? '',
      risk_class: r.risk_class ?? r.riskClass ?? '',
      iso_rcv: r.iso_rcv ?? r.isoRcv ?? '',
      usage: r.usage ?? '',
      occupancy_type: r.occupancy_type ?? r.occupancyType ?? '',
      central_fire: r.central_fire ?? r.centralFireAlarm ?? false,
      central_burglar: r.central_burglar ?? r.centralBurglarAlarm ?? false,
      gated_community: r.gated_community ?? r.gatedCommunity ?? false,
      automatic: !!(r.automatic ?? r.automaticSprinklers ?? false),
      distance_to_water: r.distance_to_water ?? r.distanceToWater ?? '',
      distance_to_fire: r.distance_to_fire ?? r.distanceToFire ?? '',
      distance_to_hydrant: r.distance_to_hydrant ?? r.distanceToHydrant ?? '',
      lat: r.lat ?? r.latitude ?? '',
      lng: r.lng ?? r.longitude ?? ''
    })
    setModal({ open: true, editing: r })
  }

  async function save() {
    // Belt-and-braces: the action could have been issued in another tab while
    // this modal sat open. Backend answers 409 either way.
    if (!isEditable) {
      alert(`Risk addresses on a ${actionStatus} action cannot be changed. Unissue the action or create an Endorsement first.`)
      return
    }
    setSaving(true)
    try {
      let payload = { ...form }
      const targetActionId = effectiveActionId ?? currentActionId
      if (!modal.editing) {
        if (!currentTermId || !targetActionId) {
          await loadPolicyEditData(effectiveActionId)
        }
        if (!currentTermId || !targetActionId) {
          alert('Unable to determine policy term/action. Please refresh the page and try again.')
          setSaving(false)
          return
        }
        payload = {
          ...payload,
          term_id: currentTermId,
          action_id: targetActionId,
          address_name: payload.address_name || payload.physical_address || ''
        }
      } else {
        // Guard: the backend rejects the update when the row doesn't belong to
        // the action being viewed, so a stale list can never edit another
        // action's copy of the address.
        payload = { ...payload, action_id: targetActionId }
      }

      const url = modal.editing ? `${apiBase}/policies/${policyId}/risk-addresses/${modal.editing.id}` : `${apiBase}/policies/${policyId}/risk-addresses`
      const method = modal.editing ? 'PUT' : 'POST'
      const r = await fetch(url, { method, headers, body: JSON.stringify(payload) })
      const d = await r.json()
      if (!r.ok) { alert(d.error || d.message || 'Failed'); setSaving(false); return }
      // The edit is applied to this address's copy on every OPEN transaction,
      // but issued ones keep the detail they were printed on. Say so — that is
      // the answer to "I changed the Construction Type and the V2 Quote still
      // shows the old one" on an issued transaction.
      if (d.locked_copies?.length) alert(d.message)
      setModal({ open: false, editing: null }); refetch()
    } catch (e: any) { alert(e.message) }
    setSaving(false)
  }

  async function remove(id: number) {
    if (!isEditable) {
      alert(`Risk addresses on a ${actionStatus} action cannot be changed. Unissue the action or create an Endorsement first.`)
      return
    }
    if (!confirm('Delete this risk address?')) return
    setDeleting(id)
    try {
      const qs = effectiveActionId ? `?action_id=${effectiveActionId}` : ''
      const r = await fetch(`${apiBase}/policies/${policyId}/risk-addresses/${id}${qs}`, { method: 'DELETE', headers })
      const d = await r.json()
      if (!r.ok) alert(d.error || 'Failed')
      else refetch()
    } catch (e: any) { alert(e.message) }
    setDeleting(null)
  }

  if (actionsQuery.isLoading || isLoading) return <ProgressBar isLoading label="Loading addresses" className="max-w-xs mx-auto py-8" />

  return (
    <div className="space-y-4">
      {/* Action selector — risk addresses are per-action rows */}
      {actions.length > 0 && (
        <div className="flex items-center gap-3 bg-surface-2 p-3 rounded-lg border border-line">
          <label className="text-sm font-medium text-ink-muted whitespace-nowrap">Policy Action:</label>
          <select value={effectiveActionId || ''} onChange={e => setSelectedActionId(Number(e.target.value))}
            className="flex-1 px-3 py-2 border border-line rounded text-sm">
            {actions.map((a: any) => (
              <option key={a.id} value={a.id}>{a.transactionType} — {a.status} ({a.effectiveFrom} to {a.effectiveTo})</option>
            ))}
          </select>
        </div>
      )}

      {/* Locked unless the selected action is a QUOTE */}
      {!isEditable && (
        <div className="flex items-start gap-3 bg-status-danger-bg border border-status-danger-fg rounded-lg px-4 py-3">
          <span className="text-status-danger-fg">🔒</span>
          <p className="text-sm text-status-danger-fg">
            Risk addresses on this <strong>{actionStatus}</strong> action are read-only. Only a <strong>QUOTE</strong> action
            can be changed — Unissue this action, or create an Endorsement, then edit there.
          </p>
        </div>
      )}

      {/* Each action holds its own copy of the address, so an edit here does
          not rewrite later transactions. */}
      {isEditable && effectiveActionId && currentAction && effectiveActionId !== currentAction.id && (
        <div className="text-xs text-ink-muted bg-surface-2 border border-line rounded px-3 py-2">
          Viewing a past action. Adds, edits and deletes apply to this action only — later actions keep their own risk address rows.
        </div>
      )}

      {isEditable && (
        <div className="flex justify-end">
          <button onClick={openAdd} className="px-4 py-2 bg-primary text-white rounded text-sm hover:bg-primary">+ Add Risk Address</button>
        </div>
      )}

      {(!addresses || addresses.length === 0) && (
        <EmptyState message={effectiveActionId ? 'No risk addresses on this policy action.' : 'No risk addresses.'} />
      )}

      {addresses?.map((r: PolicyRiskAddress, i: number) => (
        <Card key={r.id} title={`Risk Address ${i + 1}${r.addressName ? ` — ${r.addressName}` : ''}`}>
          {isEditable && (
            <div className="flex justify-end gap-2 mb-2">
              <button onClick={() => openEdit(r)} className="px-3 py-1 text-xs bg-surface-2 hover:bg-surface-2 rounded border border-line">Edit</button>
              <button onClick={() => remove(r.id)} disabled={deleting === r.id} className="px-3 py-1 text-xs bg-status-danger-bg hover:bg-status-danger-bg text-status-danger-fg rounded border border-status-danger-fg disabled:opacity-50">
                {deleting === r.id ? 'Deleting…' : 'Delete'}
              </button>
            </div>
          )}
          <InfoRow label="Address" value={r.address} />
          {r.addressName && r.addressName !== r.address && <InfoRow label="Name / Label" value={r.addressName} />}
          {r.city && <InfoRow label="City" value={r.city} />}
          {r.state && <InfoRow label="State" value={r.state} />}
          {r.zipCode && <InfoRow label="Zip Code" value={r.zipCode} />}
        </Card>
      ))}

      {modal.open && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-start justify-center p-4 pt-10" onClick={(e) => { if (e.target === e.currentTarget) setModal({ open: false, editing: null }) }}>
          <div className="bg-surface rounded-xl shadow-xl w-full max-w-2xl max-h-[85vh] overflow-y-auto">
            <div className="sticky top-0 flex items-center justify-between px-5 py-3 border-b border-line bg-surface">
              <h3 className="font-semibold text-ink">{modal.editing ? 'Edit Risk Address' : 'Add Risk Address'}</h3>
              <button onClick={() => setModal({ open: false, editing: null })} className="text-ink-faint hover:text-ink-muted text-xl leading-none">✕</button>
            </div>
            <div className="px-5 py-4 space-y-4">
              {/* Row 1: Address Name & Physical Address */}
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-medium text-ink-muted mb-1">Address Name / Label</label>
                  <input value={form.address_name || ''} onChange={e => setForm((p: any) => ({ ...p, address_name: e.target.value }))}
                    className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none" placeholder="e.g. Main Office" />
                </div>
                <div>
                  <label className="block text-xs font-medium text-ink-muted mb-1">Physical Address *</label>
                  <input value={form.physical_address || ''} onChange={e => setForm((p: any) => ({ ...p, physical_address: e.target.value }))}
                    className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none" placeholder="Plot number, street, area" />
                </div>
              </div>

              {/* Row 2: Province, City, Construction Type */}
              <div className="grid grid-cols-3 gap-4">
                <div>
                  <label className="block text-xs font-medium text-ink-muted mb-1">Province / State *</label>
                  <select value={form.risk_state || ''} onChange={e => setForm((p: any) => ({ ...p, risk_state: e.target.value, risk_city: '' }))}
                    className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none">
                    <option value="">Select State</option>
                    {states.map((s: any) => (
                      <option key={s.id} value={s.id}>{s.name}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-medium text-ink-muted mb-1">City *</label>
                  <select value={form.risk_city || ''} onChange={e => setForm((p: any) => ({ ...p, risk_city: e.target.value }))}
                    disabled={!form.risk_state}
                    className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none disabled:bg-surface-2 disabled:cursor-not-allowed">
                    <option value="">Select City</option>
                    {cities.map((c: any) => (
                      <option key={c.id} value={c.id}>{c.name}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-medium text-ink-muted mb-1">Construction Type *</label>
                  <select value={form.const_type || ''} onChange={e => setForm((p: any) => ({ ...p, const_type: e.target.value }))}
                    className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none">
                    <option value="">Select Type</option>
                    {constructionTypes.map((ct: any) => (
                      <option key={ct.id || ct.value} value={ct.id || ct.value}>{ct.name || ct.value}</option>
                    ))}
                  </select>
                </div>
              </div>

              {/* Row 3: Extension, Occupation, Year Built, Area */}
              <div className="grid grid-cols-4 gap-4">
                <div>
                  <label className="block text-xs font-medium text-ink-muted mb-1">Extension</label>
                  <input value={form.extension || ''} onChange={e => setForm((p: any) => ({ ...p, extension: e.target.value }))}
                    className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none" placeholder="e.g. Ext 12" />
                </div>
                <div>
                  <label className="block text-xs font-medium text-ink-muted mb-1">Occupation/Use</label>
                  <input value={form.occupation || ''} onChange={e => setForm((p: any) => ({ ...p, occupation: e.target.value }))}
                    className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none" placeholder="e.g. Office" />
                </div>
                <div>
                  <label className="block text-xs font-medium text-ink-muted mb-1">Year Built</label>
                  <input value={form.year_built || ''} onChange={e => setForm((p: any) => ({ ...p, year_built: e.target.value }))}
                    className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none" placeholder="e.g. 2005" />
                </div>
                <div>
                  <label className="block text-xs font-medium text-ink-muted mb-1">Area (sqm)</label>
                  <input value={form.area || ''} onChange={e => setForm((p: any) => ({ ...p, area: e.target.value }))}
                    className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none" placeholder="e.g. 500" />
                </div>
              </div>

              {/* Row 4: Structure Type, Town Class, Risk Class, ISO RCV */}
              <div className="grid grid-cols-4 gap-4">
                <div>
                  <label className="block text-xs font-medium text-ink-muted mb-1">Structure Type</label>
                  <input value={form.structure_type || ''} onChange={e => setForm((p: any) => ({ ...p, structure_type: e.target.value }))}
                    className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none" placeholder="Single/Multi Story" />
                </div>
                <div>
                  <label className="block text-xs font-medium text-ink-muted mb-1">Town Class</label>
                  <select value={form.town_class || ''} onChange={e => setForm((p: any) => ({ ...p, town_class: e.target.value }))}
                    className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none">
                    <option value="">Select</option>
                    <option value="High">High</option>
                    <option value="Medium">Medium</option>
                    <option value="Low">Low</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-medium text-ink-muted mb-1">Risk Class</label>
                  <select value={form.risk_class || ''} onChange={e => setForm((p: any) => ({ ...p, risk_class: e.target.value }))}
                    className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none">
                    <option value="">Select</option>
                    <option value="High">High</option>
                    <option value="Medium">Medium</option>
                    <option value="Low">Low</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-medium text-ink-muted mb-1">ISO RCV</label>
                  <input value={form.iso_rcv || ''} onChange={e => setForm((p: any) => ({ ...p, iso_rcv: e.target.value }))}
                    className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none" placeholder="ISO RCV" />
                </div>
              </div>

              {/* Row 5: Usage, Occupancy Type */}
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-medium text-ink-muted mb-1">Usage</label>
                  <select value={form.usage || ''} onChange={e => setForm((p: any) => ({ ...p, usage: e.target.value }))}
                    className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none">
                    <option value="">Select</option>
                    <option value="Administrative Office">Administrative Office</option>
                    <option value="Distribution Center">Distribution Center</option>
                    <option value="Manufacturing (Light)">Manufacturing (Light)</option>
                    <option value="Manufacturing (Heavy)">Manufacturing (Heavy)</option>
                    <option value="Retail (FMGG)">Retail (FMGG)</option>
                    <option value="Retail (High Value)">Retail (High Value)</option>
                    <option value="Stock Yard">Stock Yard</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-medium text-ink-muted mb-1">Occupancy Type</label>
                  <input value={form.occupancy_type || ''} onChange={e => setForm((p: any) => ({ ...p, occupancy_type: e.target.value }))}
                    className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none" placeholder="Select..." />
                </div>
              </div>

              {/* Row 6: Distances */}
              <div className="grid grid-cols-3 gap-4">
                <div>
                  <label className="block text-xs font-medium text-ink-muted mb-1">Distance to Water (Km)</label>
                  <input value={form.distance_to_water || ''} onChange={e => setForm((p: any) => ({ ...p, distance_to_water: e.target.value }))}
                    className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none" placeholder="e.g. 5" />
                </div>
                <div>
                  <label className="block text-xs font-medium text-ink-muted mb-1">Distance to Fire Station</label>
                  <input value={form.distance_to_fire || ''} onChange={e => setForm((p: any) => ({ ...p, distance_to_fire: e.target.value }))}
                    className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none" placeholder="e.g. 2" />
                </div>
                <div>
                  <label className="block text-xs font-medium text-ink-muted mb-1">Distance to Hydrant</label>
                  <input value={form.distance_to_hydrant || ''} onChange={e => setForm((p: any) => ({ ...p, distance_to_hydrant: e.target.value }))}
                    className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none" placeholder="e.g. 1" />
                </div>
              </div>

              {/* Row 7: Safety Features */}
              <div className="space-y-2">
                <label className="block text-xs font-medium text-ink-muted mb-2">Safety Features</label>
                <div className="grid grid-cols-2 gap-4">
                  <label className="flex items-center gap-2">
                    <input type="checkbox" checked={form.central_fire || false} onChange={e => setForm((p: any) => ({ ...p, central_fire: e.target.checked }))}
                      className="rounded" />
                    <span className="text-sm">Central Fire Alarm</span>
                  </label>
                  <label className="flex items-center gap-2">
                    <input type="checkbox" checked={form.central_burglar || false} onChange={e => setForm((p: any) => ({ ...p, central_burglar: e.target.checked }))}
                      className="rounded" />
                    <span className="text-sm">Central Burglar Alarm</span>
                  </label>
                  <label className="flex items-center gap-2">
                    <input type="checkbox" checked={form.gated_community || false} onChange={e => setForm((p: any) => ({ ...p, gated_community: e.target.checked }))}
                      className="rounded" />
                    <span className="text-sm">Gated Community</span>
                  </label>
                  <label className="flex items-center gap-2">
                    <input type="checkbox" checked={!!form.automatic} onChange={e => setForm((p: any) => ({ ...p, automatic: e.target.checked }))}
                      className="rounded" />
                    <span className="text-sm">Automatic Sprinklers</span>
                  </label>
                </div>
              </div>

              {/* Row 8: Coordinates */}
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-medium text-ink-muted mb-1">Latitude</label>
                  <input value={form.lat || ''} onChange={e => setForm((p: any) => ({ ...p, lat: e.target.value }))}
                    className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none" placeholder="e.g. -17.8232" readOnly />
                </div>
                <div>
                  <label className="block text-xs font-medium text-ink-muted mb-1">Longitude</label>
                  <input value={form.lng || ''} onChange={e => setForm((p: any) => ({ ...p, lng: e.target.value }))}
                    className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none" placeholder="e.g. 25.8653" readOnly />
                </div>
              </div>
            </div>

            <div className="sticky bottom-0 flex justify-end gap-2 px-5 py-4 border-t border-line bg-surface-2">
              <button onClick={() => setModal({ open: false, editing: null })} className="px-4 py-2 text-sm border border-line rounded hover:bg-surface-2">Cancel</button>
              <button onClick={save} disabled={saving} className="px-4 py-2 text-sm bg-primary text-white rounded hover:bg-primary disabled:opacity-50">
                {saving ? 'Saving…' : modal.editing ? 'Update Address' : 'Add Address'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

// ─── Tab: Reinsurance (lazy loaded) ─────────────────────────────

function ReinsuranceTab({ policyId }: { policyId: number }) {
  const { data: records, isLoading, refetch } = usePolicyReinsurance(policyId, true)
  const qc = useQueryClient()
  const [recalculating, setRecalculating] = useState(false)
  // Inline result banner — replaces the old alert()/confirm() chain. Keeps
  // the user on-page and shows the computed numbers right next to the
  // table so the old "click → alert → scroll to verify" loop is gone.
  type RecalcResult = {
    kind: 'ok' | 'queued' | 'error'
    message: string
    actionId?: number
    rowCount?: number
    coverageCount?: number
  }
  const [result, setResult] = useState<RecalcResult | null>(null)

  const recalculate = async () => {
    setRecalculating(true)
    setResult(null)
    try {
      const r = await apiClient.post(`/policies/${policyId}/reinsurance/recalculate`, {})
      const d = r.data || {}

      // Force-refresh the cached reinsurance rows so the table reflects
      // reality even inside staleTime. Plain refetch() can return cached
      // data; invalidateQueries guarantees a network call.
      await qc.invalidateQueries({ queryKey: ['policy', policyId, 'reinsurance'] })
      await refetch()

      if (d.queued) {
        setResult({
          kind: 'queued',
          message: (d.message || `Queued for background recompute (${d.coverage_count ?? '?'} coverages).`)
            + ' Computing… this banner will confirm once the calculation finishes.',
          actionId: d.action_id,
          coverageCount: d.coverage_count,
        })

        // Ask the NotificationBell to poll every 10 s for the next 5 min
        // so the toast surfaces ~immediately after the background job
        // finishes. Base polling stays at 60 s the rest of the day.
        window.dispatchEvent(new CustomEvent('notifications:accelerate-poll', { detail: { ms: 300000 } }))

        // Completion is driven by the server's "running" flag, NOT by "rows
        // exist". On a RE-compute the OLD rows stay visible until the compute's
        // transaction commits, so a `length > 0` check flashes success on stale
        // data and stops early — the fresh numbers then only appear on a much
        // later refetch. Poll status until `running` flips false (set before the
        // recalc response returned, cleared when the transaction commits), THEN
        // refetch so the table and the success banner update at the same moment.
        let tries = 0
        const maxTries = 60 // ~3 min at 3 s
        const poll = async (): Promise<void> => {
          if (tries++ >= maxTries) {
            setResult({
              kind: 'queued',
              message: 'Still computing — taking longer than usual. A notification will appear in the bell when it finishes; you can safely leave this tab.',
              actionId: d.action_id,
            })
            return
          }
          await new Promise((r) => setTimeout(r, 3000))
          let status: { running?: boolean; rowCount?: number } = {}
          try {
            const s = await apiClient.get(`/policies/${policyId}/reinsurance/status`, { params: { action_id: d.action_id } })
            status = s.data || {}
          } catch {
            return poll() // transient network blip — keep polling
          }
          if (status.running) return poll()
          // Compute finished (transaction committed) — pull the fresh rows so
          // the table reflects reality at the same instant we show success.
          await qc.invalidateQueries({ queryKey: ['policy', policyId, 'reinsurance'] })
          const res = await refetch()
          const rows = res.data?.length ?? status.rowCount ?? 0
          setResult({
            kind: 'ok',
            message: `Background compute completed — ${rows} rows written.`,
            actionId: d.action_id,
            rowCount: rows,
          })
        }
        poll() // fire and forget — UI updates when running flips false
      } else {
        setResult({
          kind: 'ok',
          message: d.message || 'Reinsurance mapping recalculated.',
          actionId: d.action_id,
          rowCount: d.row_count ?? 0,
        })
      }
    } catch (e: any) {
      setResult({
        kind: 'error',
        message: e?.response?.data?.error || e?.message || 'Recalculation failed.',
      })
    } finally { setRecalculating(false) }
  }

  // Shared banner rendered above both the empty and populated states.
  const resultBanner = result && (
    <div className={[
      'rounded-md px-4 py-3 text-sm border',
      result.kind === 'ok'     ? 'bg-status-success-bg border-status-success-fg text-status-success-fg' :
      result.kind === 'queued' ? 'bg-status-warning-bg border-status-warning-fg text-status-warning-fg' :
                                 'bg-status-danger-bg   border-status-danger-fg   text-status-danger-fg',
    ].join(' ')}>
      <div className="font-medium">
        {result.kind === 'ok' && '✓ Reinsurance computed'}
        {result.kind === 'queued' && '⏳ Recompute running in the background'}
        {result.kind === 'error' && '✗ Recompute failed'}
      </div>
      <div className="text-xs mt-1">{result.message}</div>
      {(result.actionId || result.rowCount != null || result.coverageCount != null) && (
        <div className="text-[11px] mt-1 opacity-75 flex gap-4">
          {result.actionId    != null && <span>action #{result.actionId}</span>}
          {result.rowCount    != null && <span>{result.rowCount} rows written</span>}
          {result.coverageCount != null && <span>{result.coverageCount} coverages processed</span>}
        </div>
      )}
    </div>
  )

  if (isLoading) return <ProgressBar isLoading label="Loading reinsurance" className="max-w-xs mx-auto py-8" />
  if (!records || records.length === 0) {
    return (
      <div className="space-y-3">
        {resultBanner}
        <EmptyState message="No reinsurance records for this policy." />
        <div className="text-center">
          <button onClick={recalculate} disabled={recalculating}
            className="px-4 py-2 bg-primary text-white rounded text-sm hover:bg-primary disabled:opacity-50">
            {recalculating ? 'Computing…' : 'Compute Reinsurance Mapping'}
          </button>
        </div>
        {/* Diagnostic when we just recomputed but still got 0 rows — points
            at the most common root cause so UW doesn't have to guess. */}
        {result?.kind === 'ok' && result.rowCount === 0 && (
          <div className="rounded-md px-4 py-3 text-xs bg-status-info-bg border border-primary text-primary">
            <div className="font-medium mb-1">Why zero rows?</div>
            The calculator ran without errors but didn't write any allocations. Most common causes:
            <ul className="list-disc ml-5 mt-1 space-y-0.5">
              <li>No active <b>reinsurance_treaty</b> covering this policy's effective dates (check Reinsurance → Treaties).</li>
              <li>No <b>reinsurance_formula</b> rows for this product, or formulas have no <b>_details</b> thresholds defined.</li>
              <li>Coverage group not mapped to the product — check Reinsurance → Coverage Grouping.</li>
              <li>Policy's SI falls outside every formula's between/&lt;/&gt; range — engine has no row to pick.</li>
            </ul>
            Run <code className="bg-white/70 px-1 rounded">php artisan treaty:check</code> on the server for a full diagnostic.
          </div>
        )}
      </div>
    )
  }

  const RI_COLS = [
    { key: 'netRetention',   siKey: 'netRetentionSI',  label: 'Net Retention',   color: 'text-primary' },
    { key: 'quotaShare',     siKey: 'quotaShareSI',    label: 'Quota Share',     color: 'text-status-success-fg' },
    { key: 'surplus',        siKey: 'surplusSI',       label: 'Surplus',         color: 'text-status-accent-fg' },
    { key: 'facultative',    siKey: 'facultativeSI',   label: 'Auto Fac',        color: 'text-status-warning-fg' },
    { key: 'facPlacement',   siKey: 'facPlacementSI',  label: 'Fac Placement',   color: 'text-status-danger-fg' },
  ]

  // Aggregate totals across all risks/groups so the finance / UW reader
  // can see "Net Retention: P X of P Y total premium" at a glance.
  const num = (v: any) => {
    const n = Number(String(v ?? '').replace(/[^0-9.-]/g, ''))
    return Number.isFinite(n) ? n : 0
  }
  const totals = records.reduce((acc: any, r: any) => {
    acc.totalSI      += num(r.totalSumInsured)
    acc.totalPremium += num(r.totalPremium)
    for (const c of RI_COLS) {
      acc[c.key]     += num(r[c.key])
      acc[c.siKey]   += num(r[c.siKey])
    }
    return acc
  }, { totalSI: 0, totalPremium: 0,
       netRetention: 0, netRetentionSI: 0,
       quotaShare: 0,   quotaShareSI: 0,
       surplus: 0,      surplusSI: 0,
       facultative: 0,  facultativeSI: 0,
       facPlacement: 0, facPlacementSI: 0 })

  /*
   * CEDED IS QUOTA SHARE PLUS SURPLUS. Auto FAC and Fac Placement are NOT ceded.
   *
   * They used to be counted in, which reported 94.6% ceded on COMG2026213751 where
   * the treaty actually takes 10.9%. Reinsurance has stated the position twice — on
   * points 1, 2 and 9 of the RI-09 review and again on 25 August 2026: a facultative
   * placement is per policy, negotiated on its own terms, and neither treaty
   * establishes a facultative facility. So Auto FAC and FAC are exposure Alpha Direct
   * has kept and placed separately, not cession the treaty carries.
   *
   * The distinction is the whole point of the number: what the TREATY is on risk for.
   * Including a facultative placement in it overstates the reinsurance asset and
   * understates what is retained.
   */
  const cededPremium = totals.quotaShare + totals.surplus
  const cededSI      = totals.quotaShareSI + totals.surplusSI

  // Retained is everything the treaty did not take: the net retention leg plus the
  // facultative layers, which are placed policy by policy rather than ceded.
  const retainedPremium = totals.netRetention + totals.facultative + totals.facPlacement
  const retainedSI      = totals.netRetentionSI + totals.facultativeSI + totals.facPlacementSI

  /*
   * Premium above capacity is on NEITHER side, so it gets its own figure.
   *
   * Retained and ceded used to account for the whole premium, because the facultative
   * placement layer had no upper limit and swallowed whatever sat above its attach
   * point. Now that Band 3 caps it, a large risk leaves premium that no layer took —
   * 2,480,182 of 21,885,877 on COMG2026213751, almost all of it Goods in Transit. That
   * is the premium on exposure Alpha Direct is carrying with nothing placed behind it,
   * and it is the single most important number on this tab: showing only two tiles that
   * quietly stop adding up would hide it.
   *
   * Percentages stay out of the whole premium so all three read against the same base
   * and sum to 100%.
   */
  const uncoveredPremium = Math.max(0, totals.totalPremium - (retainedPremium + cededPremium))
  const splitBase    = retainedPremium + cededPremium + uncoveredPremium
  const retentionPct = splitBase > 0 ? (retainedPremium / splitBase) * 100 : 0
  const cededPct     = splitBase > 0 ? (cededPremium / splitBase) * 100 : 0
  const uncoveredPct = splitBase > 0 ? (uncoveredPremium / splitBase) * 100 : 0

  // Sum insured the treaty did not absorb — total less every layer it placed.
  // Deliberately called "outside treaty" rather than a gap: until a facultative
  // placement or a decision to retain net is recorded against it, we cannot say
  // whether it is a problem. Subtracts every layer, not just quota share, so a
  // 2024/25 policy carrying surplus and fac lines is not overstated.
  const outsideOf = (r: any) =>
    num(r.totalSumInsured) - RI_COLS.reduce((s, c) => s + num(r[c.siKey]), 0)
  const outsideTotal = totals.totalSI - RI_COLS.reduce((s, c) => s + totals[c.siKey], 0)

  const fmt = (n: number) => n === 0 ? '-' : fmtPula(n)

  return (
    <div className="space-y-3">
      {resultBanner}
      {/* Summary bar — net retention vs ceded at a glance */}
      <div className="bg-gradient-to-r from-status-info-bg to-status-success-bg border border-primary rounded-md px-4 py-3 grid grid-cols-5 gap-3 text-sm">
        <div>
          <div className="text-[10px] text-ink-muted uppercase tracking-wide">Total Premium</div>
          <div className="font-semibold text-ink">{fmt(totals.totalPremium)}</div>
          <div className="text-[10px] text-ink-faint">Total SI {fmt(totals.totalSI)}</div>
        </div>
        {/*
          RETAINED, not just the net retention leg. It carries the facultative layers
          too, because those are placed policy by policy rather than ceded to the
          treaty — so the label has to say what the figure contains, or a reader
          compares it against the old "Net Retention" and thinks it has moved.
        */}
        <div>
          <div className="text-[10px] text-primary uppercase tracking-wide">
            Retained (Net + Auto Fac + Fac)
          </div>
          <div className="font-semibold text-primary">{fmt(retainedPremium)}</div>
          <div className="text-[10px] text-primary">{retentionPct.toFixed(1)}% · SI {fmt(retainedSI)}</div>
        </div>
        <div>
          <div className="text-[10px] text-status-accent-fg uppercase tracking-wide">
            Ceded to Treaty (QS + Surplus)
          </div>
          <div className="font-semibold text-status-accent-fg">{fmt(cededPremium)}</div>
          <div className="text-[10px] text-status-accent-fg">{cededPct.toFixed(1)}% · SI {fmt(cededSI)}</div>
        </div>
        {/*
          OUTSIDE TREATY. Premium and sum insured that no layer took, once the
          facultative placement is capped at Band 3. Warning-coloured when there is
          any, muted at nil — this is exposure with nothing behind it, not a rounding
          residue, and the work paper is explicit that an unplaced facultative share
          "is an uninsured net exposure, not nil".
        */}
        <div>
          <div className={`text-[10px] uppercase tracking-wide ${
            uncoveredPremium > 0 ? 'text-status-warning-fg' : 'text-ink-muted'}`}>
            Outside Treaty (unplaced)
          </div>
          <div className={`font-semibold ${
            uncoveredPremium > 0 ? 'text-status-warning-fg' : 'text-ink-faint'}`}>
            {fmt(uncoveredPremium)}
          </div>
          <div className={`text-[10px] ${
            uncoveredPremium > 0 ? 'text-status-warning-fg' : 'text-ink-faint'}`}>
            {uncoveredPct.toFixed(1)}% · SI {fmt(outsideTotal)}
          </div>
        </div>
        <div className="flex items-center justify-end">
          <button onClick={recalculate} disabled={recalculating}
            className="px-3 py-1.5 border border-primary text-primary rounded text-xs hover:bg-status-info-bg disabled:opacity-50">
            {recalculating ? 'Recomputing…' : '↻ Recalculate'}
          </button>
        </div>
      </div>
      <DualScrollTable>
        <table className="w-full text-xs whitespace-nowrap">
          <thead className="bg-surface-2 text-ink-muted uppercase sticky top-0">
            <tr>
              <th className="px-3 py-2 text-left font-medium" rowSpan={2}>Risk / Group</th>
              <th className="px-3 py-2 text-left font-medium" rowSpan={2}>Treaty</th>
              <th className="px-3 py-2 text-right font-medium" rowSpan={2}>Total SI</th>
              <th className="px-3 py-2 text-right font-medium" rowSpan={2}>Total Premium</th>
              {RI_COLS.map((col) => (
                <th key={col.key} className="px-2 py-1 text-center font-medium border-l border-line" colSpan={2}>{col.label}</th>
              ))}
              <th className="px-3 py-2 text-right font-medium border-l-2 border-line" rowSpan={2}
                  title="Sum insured this treaty did not absorb: total SI less every layer above. It is not yet placed facultatively or recorded as retained net.">
                Outside Treaty<span className="block font-normal text-[10px] normal-case">SI</span>
              </th>
            </tr>
            <tr>
              {RI_COLS.map((col) => (
                <>
                  <th key={`${col.key}-p`} className="px-2 py-1 text-right font-normal text-[10px] border-l border-line">Premium</th>
                  <th key={`${col.key}-si`} className="px-2 py-1 text-right font-normal text-[10px]">SI</th>
                </>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-line">
            {records.map((r: any, i: number) => (
              <tr key={i} className="hover:bg-surface-2">
                <td className="px-3 py-2">
                  <span className="font-medium text-ink">{r.riskAddress || '-'}</span>
                  {r.groupCode && <span className="ml-1.5 text-[10px] px-1.5 py-0.5 bg-status-info-bg text-primary rounded-full font-medium">{r.groupCode}</span>}
                </td>
                <td className="px-3 py-2 text-ink-muted">
                  {r.treatyName ? <span className="font-medium">{r.treatyName}</span> : '-'}
                  {r.treatyNumber && <span className="ml-1 text-[10px] text-ink-faint">#{r.treatyNumber}</span>}
                </td>
                <td className="px-3 py-2 text-right font-semibold text-ink">P {r.totalSumInsured}</td>
                <td className="px-3 py-2 text-right font-semibold text-ink">{fmtCurrency(r.totalPremium)}</td>
                {RI_COLS.map((col) => (
                  <>
                    <td key={`${col.key}-p-${i}`} className={`px-2 py-2 text-right border-l border-line ${col.color}`}>
                      {r[col.key] && r[col.key] !== '0.00' ? fmtCurrency(r[col.key]) : '-'}
                    </td>
                    <td key={`${col.key}-si-${i}`} className="px-2 py-2 text-right text-ink-muted">
                      {r[col.siKey] && r[col.siKey] !== '0.00' ? fmtCurrency(r[col.siKey]) : '-'}
                    </td>
                  </>
                ))}
                <td className={`px-3 py-2 text-right border-l-2 border-line font-semibold ${
                  outsideOf(r) > 0 ? 'text-status-warning-fg' : 'text-ink-faint'}`}>
                  {fmt(outsideOf(r))}
                </td>
              </tr>
            ))}
          </tbody>
          {/* Totals footer — aggregate across all rows */}
          <tfoot className="bg-surface-2 border-t-2 border-line">
            <tr className="text-[11px]">
              <td className="px-3 py-2 font-bold text-ink-muted" colSpan={2}>TOTAL</td>
              <td className="px-3 py-2 text-right font-bold text-ink">{fmt(totals.totalSI)}</td>
              <td className="px-3 py-2 text-right font-bold text-ink">{fmt(totals.totalPremium)}</td>
              {RI_COLS.map((col) => (
                <>
                  <td key={`${col.key}-pt`} className={`px-2 py-2 text-right border-l border-line font-bold ${col.color}`}>
                    {fmt(totals[col.key])}
                  </td>
                  <td key={`${col.key}-sit`} className="px-2 py-2 text-right text-ink-muted font-semibold">
                    {fmt(totals[col.siKey])}
                  </td>
                </>
              ))}
              <td className={`px-3 py-2 text-right border-l-2 border-line font-bold ${
                outsideTotal > 0 ? 'text-status-warning-fg' : 'text-ink-faint'}`}>
                {fmt(outsideTotal)}
              </td>
            </tr>
          </tfoot>
        </table>
      </DualScrollTable>
    </div>
  )
}

// ─── Tab: KYC Documents (tier-aware: DOMG → 7 docs, COMG → 13 docs, MIS → legacy) ─

function KycDocumentsTab({ policyId, kyc, policy }: { policyId: number; kyc?: Policy['kyc']; policy: Policy }) {
  // Eager fetch — the backend now returns the per-tier doc list, including
  // hasFile + URL per row. Rendering off this response keeps DOMG/COMG/MIS
  // in lockstep with the V8 blade lists.
  const { data: kycData, isLoading: kycLoading, refetch: refetchKyc } = usePolicyKycDocuments(policyId, true)
  const [previewUrl, setPreviewUrl] = useState<string | null>(null)

  // policy-kyc-documents-upload gate. KYC Agents (and any other role not
  // granted this permission) can VIEW the docs but can't add, replace or
  // delete them. The same check fires on the BE; the FE flag here just
  // surfaces the disabled state with a tooltip instead of letting the
  // operator click and hit a 403.
  // Mirror every other permission gate in this file: read the stored list
  // once and treat an empty list as "allow" (admin fallback), so a missing
  // or not-yet-loaded cache never hard-blocks an authorised operator. The
  // BE re-checks hasPermissionTo('policy-kyc-documents-upload') on POST.
  const perms = useMemo(() => {
    try { return JSON.parse(localStorage.getItem('user_permissions') || '[]') as string[] } catch { return [] }
  }, [])
  const canUpload = perms.length === 0 || perms.includes('policy-kyc-documents-upload')
  const noPermissionTooltip = 'You do not have permission to upload KYC documents.'

  // Queued files per doc field (FE-only until "Submit" is clicked, just like
  // V8's customerkyc-section.blade which batches everything into one POST).
  const [queuedFiles, setQueuedFiles] = useState<Record<string, File>>({})
  const [submitting, setSubmitting] = useState(false)
  const [deletingField, setDeletingField] = useState<string | null>(null)
  const [submitError, setSubmitError] = useState<string | null>(null)

  // Only block while the GET is in-flight. After the request completes,
  // an empty docs[] still gets rendered as a tier-aware grid of empty
  // upload cards so reviewers can upload for the first time on a
  // brand-new policy. Previously this guard fired whenever both
  // customer_kyc and customer_kyc_dom_com were empty and short-circuited
  // the upload UI entirely — exactly the COMG-no-row case.
  if (kycLoading && !kycData) {
    return <div className="p-6 text-sm text-ink-muted">Loading KYC documents…</div>
  }

  const docs = kycData?.documents ?? []
  const complianceLabel = kycData?.complianceLabel ?? kyc?.complianceLabel ?? 'Not Compliant'
  const compliance = kycData?.compliance ?? kyc?.compliance
  // policy is reserved for future tier-driven UI tweaks (e.g. badges)
  void policy

  function queueFile(field: string, file: File | null) {
    setQueuedFiles(prev => {
      const next = { ...prev }
      if (!file) delete next[field]
      else next[field] = file
      return next
    })
  }

  async function submitUploads() {
    if (Object.keys(queuedFiles).length === 0) {
      setSubmitError('Pick at least one file to upload (click the pencil on a card).')
      return
    }
    setSubmitError(null)
    setSubmitting(true)
    try {
      const fd = new FormData()
      Object.entries(queuedFiles).forEach(([field, file]) => fd.append(field, file))
      await apiClient.post(`/policies/${policyId}/kyc-documents`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      setQueuedFiles({})
      refetchKyc()
    } catch (e: any) {
      setSubmitError(e?.response?.data?.message || e?.response?.data?.error || 'Upload failed')
    } finally {
      setSubmitting(false)
    }
  }

  async function handleDelete(field: string, label: string) {
    if (!confirm(`Delete the uploaded ${label}?`)) return
    setDeletingField(field)
    try {
      await apiClient.delete(`/policies/${policyId}/kyc-documents/${encodeURIComponent(field)}`)
      refetchKyc()
    } catch (e: any) {
      alert(e?.response?.data?.message || e?.response?.data?.error || 'Delete failed')
    } finally {
      setDeletingField(null)
    }
  }

  return (
    <div className="space-y-6">
      <Card title="Compliance Status">
        <InfoRow label="complianceLabel" value={
          compliance === 1
            ? <StatusBadge status="approved" label="KYC Compliant" />
            : <StatusBadge status="pending" label={complianceLabel} />
        } />
      </Card>

      {/* Customer KYC — V8 customerkyc-section.blade mirror: grid of cards
          with PDF/image preview, pencil-icon to replace, trash-icon to
          delete, and a central Submit at the bottom. */}
      <div className="bg-surface rounded-lg border border-line p-4 space-y-4">
        <div className="flex items-center justify-between">
          <h3 className="text-base font-semibold text-ink">Customer KYC</h3>
        </div>

        {kycLoading && !kycData ? (
          <div className="p-4 text-sm text-ink-muted">Loading documents…</div>
        ) : docs.length === 0 ? (
          <p className="text-sm text-ink-faint py-2">No KYC documents required for this policy.</p>
        ) : (
          <>
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4">
              {docs.map((doc) => {
                const queued = queuedFiles[doc.field]
                return (
                  <KycDocCard
                    key={doc.field}
                    label={doc.label}
                    url={doc.url}
                    hasFile={doc.hasFile}
                    queued={queued}
                    deleting={deletingField === doc.field}
                    canUpload={canUpload}
                    disabledTooltip={noPermissionTooltip}
                    onQueue={(f) => queueFile(doc.field, f)}
                    onPreview={() => doc.url && setPreviewUrl(doc.url)}
                    onDelete={() => handleDelete(doc.field, doc.label)}
                  />
                )
              })}
            </div>

            <div className="flex flex-col items-center pt-2">
              {submitError && <p className="text-xs text-status-danger-fg mb-2">{submitError}</p>}
              {!canUpload && (
                <p className="text-xs text-status-warning-fg mb-2">{noPermissionTooltip}</p>
              )}
              <button
                type="button"
                onClick={submitUploads}
                disabled={!canUpload || submitting || Object.keys(queuedFiles).length === 0}
                title={!canUpload ? noPermissionTooltip : undefined}
                className="px-6 py-2 text-sm font-medium rounded-md bg-primary text-white hover:bg-primary disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {submitting
                  ? 'Uploading…'
                  : `Submit${Object.keys(queuedFiles).length > 0 ? ` (${Object.keys(queuedFiles).length})` : ''}`}
              </button>
            </div>
          </>
        )}
      </div>

      {/* Mati Verification has moved to its own dedicated tab (MIS-only). */}

      <DocumentPreviewModal url={previewUrl} onClose={() => setPreviewUrl(null)} />
    </div>
  )
}

// ─── Tab: Mati Verification (MIS-only) ──────────────────────────────────────
// React port of the graphiteBWV8 policyDetails_View.blade.php "Mati Verification
// Details" tab: status badge, identity/verification IDs, the parsed document
// details table, location details, uploaded images, device checks, plus the
// four legacy actions (Verification Mail, Verification SMS, Fetch Data, and
// Update Mati Identity & Verification ID).

/** Colour classes for the MetaMap (Mati) identity status pill. */
function matiStatusPillClasses(status?: string | null): string {
  switch ((status ?? '').toLowerCase()) {
    case 'verified':      return 'bg-status-success-bg text-status-success-fg'
    case 'rejected':      return 'bg-status-danger-bg text-status-danger-fg'
    case 'reviewneeded':  return 'bg-status-warning-bg text-status-warning-fg'
    default:              return 'bg-status-info-bg text-primary'
  }
}

/** Two-column key/value table used by the Mati tab sections. */
function MatiKvTable({ rows }: { rows: Array<[string, ReactNode]> }) {
  const visible = rows.filter(([, v]) => v !== null && v !== undefined && v !== '')
  if (visible.length === 0) return null
  return (
    <table className="w-full text-sm">
      <tbody>
        {visible.map(([label, value], i) => (
          <tr key={label} className={i % 2 === 0 ? 'bg-surface-2' : ''}>
            <th className="text-left font-medium text-ink-muted px-3 py-2 w-1/3 align-top">{label}</th>
            <td className="text-ink px-3 py-2">{value}</td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}

/** Single uploaded-image tile (Passport / Omang / Omang Back / Selfie). */
function MatiImageTile({ label, url, onPreview }: { label: string; url?: string | null; onPreview: (u: string) => void }) {
  return (
    <div className="flex flex-col items-start gap-2">
      <span className="text-xs font-semibold text-ink-muted">{label}</span>
      {url ? (
        <button
          type="button"
          onClick={() => onPreview(url)}
          className="w-28 h-28 rounded border border-line overflow-hidden bg-surface-2 hover:ring-2 hover:ring-primary"
          title={`Preview ${label}`}
        >
          <img src={url} alt={label} className="w-full h-full object-cover" />
        </button>
      ) : (
        <div className="w-28 h-28 rounded border border-dashed border-line bg-surface-2 flex items-center justify-center text-xs text-ink-faint">
          No image
        </div>
      )}
    </div>
  )
}

function MatiVerificationTab({ policyId }: { policyId: number }) {
  const { data: mati, isLoading, refetch } = usePolicyMati(policyId, true)
  const [previewUrl, setPreviewUrl] = useState<string | null>(null)
  const [modal, setModal] = useState<null | 'email' | 'sms' | 'fetch' | 'update'>(null)
  const [busy, setBusy] = useState(false)
  const [toast, setToast] = useState<{ kind: 'success' | 'error'; text: string } | null>(null)

  // Update-IDs form state — prefilled from the current record.
  const [identityId, setIdentityId] = useState('')
  const [verificationId, setVerificationId] = useState('')
  useEffect(() => {
    if (modal === 'update') {
      setIdentityId(mati?.identityId ?? mati?.matiId ?? '')
      setVerificationId(mati?.verificationId ?? '')
    }
  }, [modal, mati])

  function flash(kind: 'success' | 'error', text: string) {
    setToast({ kind, text })
    window.setTimeout(() => setToast(null), 5000)
  }

  async function runAction(fn: () => Promise<{ message: string }>, refetchAfter: boolean) {
    setBusy(true)
    try {
      const res = await fn()
      flash('success', res?.message || 'Done.')
      setModal(null)
      if (refetchAfter) await refetch()
    } catch (e: any) {
      flash('error', e?.response?.data?.message || e?.response?.data?.error || 'Action failed. Please try again.')
    } finally {
      setBusy(false)
    }
  }

  if (isLoading && !mati) {
    return <div className="p-6 text-sm text-ink-muted">Loading Mati verification…</div>
  }

  if (!mati) {
    return (
      <div className="space-y-4">
        <Card title="Mati Verification">
          <p className="text-sm text-ink-faint">No Mati verification data available for this customer.</p>
        </Card>
        <div className="flex flex-wrap gap-2">
          <button onClick={() => setModal('email')} className="px-4 py-2 text-sm font-medium rounded-md bg-primary text-white hover:bg-primary">Verification Mail</button>
          <button onClick={() => setModal('sms')} className="px-4 py-2 text-sm font-medium rounded-md bg-primary text-white hover:bg-primary">Verification SMS</button>
          <button onClick={() => setModal('fetch')} className="px-4 py-2 text-sm font-medium rounded-md bg-primary text-white hover:bg-primary">Fetch Data</button>
          <button onClick={() => setModal('update')} className="px-4 py-2 text-sm font-medium rounded-md bg-primary text-white hover:bg-primary">Update Mati Identity and Verification ID</button>
        </div>
        {toast && (
          <div className={`text-sm px-3 py-2 rounded ${toast.kind === 'success' ? 'bg-status-success-bg text-status-success-fg' : 'bg-status-danger-bg text-status-danger-fg'}`}>{toast.text}</div>
        )}
        <MatiActionModals
          modal={modal} busy={busy} onClose={() => setModal(null)}
          identityId={identityId} verificationId={verificationId}
          setIdentityId={setIdentityId} setVerificationId={setVerificationId}
          onConfirmEmail={() => runAction(() => sendMatiVerificationLink(policyId, 'email'), false)}
          onConfirmSms={() => runAction(() => sendMatiVerificationLink(policyId, 'sms'), false)}
          onConfirmFetch={() => runAction(() => fetchMatiData(policyId), true)}
          onConfirmUpdate={() => runAction(() => updateMatiData(policyId, identityId.trim(), verificationId.trim()), true)}
        />
      </div>
    )
  }

  const doc = mati.documents?.[0]
  const loc = mati.location
  const dev = mati.device
  const img = mati.images ?? {}
  const rejected = (mati.status ?? '').toLowerCase() === 'rejected'

  return (
    <div className="space-y-6">
      {toast && (
        <div className={`text-sm px-3 py-2 rounded ${toast.kind === 'success' ? 'bg-status-success-bg text-status-success-fg' : 'bg-status-danger-bg text-status-danger-fg'}`}>{toast.text}</div>
      )}

      <Card
        title={
          <span className="flex items-center gap-2">
            Mati Verification Details
            {mati.status && (
              <span className={`text-xs font-semibold px-2 py-0.5 rounded-full ${matiStatusPillClasses(mati.status)}`}>
                {mati.status}
              </span>
            )}
          </span>
        }
        actions={
          <>
            <button onClick={() => setModal('email')} className="px-3 py-1.5 text-xs font-medium rounded-md bg-primary text-white hover:bg-primary">Verification Mail</button>
            <button onClick={() => setModal('sms')} className="px-3 py-1.5 text-xs font-medium rounded-md bg-primary text-white hover:bg-primary">Verification SMS</button>
            <button onClick={() => setModal('fetch')} className="px-3 py-1.5 text-xs font-medium rounded-md bg-primary text-white hover:bg-primary">Fetch Data</button>
            <button onClick={() => setModal('update')} className="px-3 py-1.5 text-xs font-medium rounded-md bg-primary text-white hover:bg-primary">Update Mati Identity and Verification ID</button>
          </>
        }
      >
        <div className="space-y-1 text-sm">
          {mati.matiId && <p className="font-semibold text-ink-muted">Mati Id : <span className="font-normal">{mati.matiId}</span></p>}
          {mati.identityId && <p className="font-semibold text-ink-muted">Identity ID : <span className="font-normal">{mati.identityId}</span></p>}
          {mati.verificationId && <p className="font-semibold text-ink-muted">Verification ID : <span className="font-normal">{mati.verificationId}</span></p>}
        </div>

        {doc ? (
          <div className="mt-3 border border-line rounded overflow-hidden">
            <MatiKvTable
              rows={[
                ['Full Name', doc.fullName],
                ['Date Of Birth', doc.dateOfBirth],
                ['First Name', rejected ? null : doc.firstName],
                ['Surname', rejected ? null : doc.surname],
                ['Gender', rejected ? null : doc.sex],
                ['Document Type', rejected ? null : doc.type],
                ['Document Number', doc.documentNumber],
                ['Expiration Date', doc.expirationDate],
                ['Country of issuance', rejected ? null : doc.issueCountry],
                ['Nationality', rejected ? null : doc.nationality],
              ]}
            />
          </div>
        ) : (
          <p className="mt-3 text-sm text-ink-faint">No document details available. Use <b>Fetch Data</b> to pull the latest verification result.</p>
        )}
      </Card>

      <Card title="Location Details">
        {loc && (loc.country || loc.region || loc.city || loc.zip) ? (
          <div className="border border-line rounded overflow-hidden">
            <MatiKvTable rows={[['Country', loc.country], ['Region', loc.region], ['City', loc.city], ['Zip', loc.zip]]} />
          </div>
        ) : (
          <p className="text-sm text-ink-faint">No location details available.</p>
        )}
      </Card>

      <Card title="Images Uploaded">
        <div className="flex flex-wrap gap-6">
          <MatiImageTile label="Passport" url={img.passportUrl} onPreview={setPreviewUrl} />
          <MatiImageTile label="Omang" url={img.omangUrl} onPreview={setPreviewUrl} />
          <MatiImageTile label="Omang Back" url={img.omangBackUrl} onPreview={setPreviewUrl} />
          <MatiImageTile label="Selfie" url={img.selfieUrl} onPreview={setPreviewUrl} />
        </div>
      </Card>

      <Card title="Device Checks">
        {dev && (dev.deviceType || dev.os || dev.browser || dev.ip) ? (
          <div className="border border-line rounded overflow-hidden">
            <MatiKvTable rows={[['Device Type', dev.deviceType], ['OS', dev.os], ['Browser', dev.browser], ['IP Address', dev.ip]]} />
          </div>
        ) : (
          <p className="text-sm text-ink-faint">No device fingerprint available.</p>
        )}
      </Card>

      <DocumentPreviewModal url={previewUrl} onClose={() => setPreviewUrl(null)} />
      <MatiActionModals
        modal={modal} busy={busy} onClose={() => setModal(null)}
        identityId={identityId} verificationId={verificationId}
        setIdentityId={setIdentityId} setVerificationId={setVerificationId}
        onConfirmEmail={() => runAction(() => sendMatiVerificationLink(policyId, 'email'), false)}
        onConfirmSms={() => runAction(() => sendMatiVerificationLink(policyId, 'sms'), false)}
        onConfirmFetch={() => runAction(() => fetchMatiData(policyId), true)}
        onConfirmUpdate={() => runAction(() => updateMatiData(policyId, identityId.trim(), verificationId.trim()), true)}
      />
    </div>
  )
}

/** Modals for the four Mati Verification actions. */
function MatiActionModals({
  modal, busy, onClose,
  identityId, verificationId, setIdentityId, setVerificationId,
  onConfirmEmail, onConfirmSms, onConfirmFetch, onConfirmUpdate,
}: {
  modal: null | 'email' | 'sms' | 'fetch' | 'update'
  busy: boolean
  onClose: () => void
  identityId: string
  verificationId: string
  setIdentityId: (v: string) => void
  setVerificationId: (v: string) => void
  onConfirmEmail: () => void
  onConfirmSms: () => void
  onConfirmFetch: () => void
  onConfirmUpdate: () => void
}) {
  if (!modal) return null

  const title = modal === 'email' ? 'MATI verification (Email)'
    : modal === 'sms' ? 'MATI verification (SMS)'
    : modal === 'fetch' ? 'Fetch Mati Data'
    : 'Update Mati Identity and Verification ID'

  const updateValid = identityId.trim() !== '' && verificationId.trim() !== ''

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/50 p-4 pt-10" onClick={onClose}>
      <div className="bg-surface rounded-lg shadow-xl w-full max-w-md" onClick={(e) => e.stopPropagation()}>
        <div className="flex justify-between items-center px-4 py-3 border-b border-line sticky top-0 bg-surface z-10">
          <h3 className="text-sm font-semibold text-ink-muted">{title}</h3>
          <button onClick={onClose} className="text-ink-faint hover:text-ink-muted text-xl leading-none">&times;</button>
        </div>
        <div className="px-4 py-4 text-sm text-ink-muted space-y-3">
          {modal === 'email' && <p>The MATI verification link will be shared to the customer's registered email.</p>}
          {modal === 'sms' && <p>The MATI verification link will be shared to the customer's registered cellphone number.</p>}
          {modal === 'fetch' && <p>This pulls the latest verification data and documents from MetaMap (Mati) for the current Identity / Verification ID and updates the customer's KYC. Continue?</p>}
          {modal === 'update' && (
            <>
              <label className="block">
                <span className="text-ink-muted">Mati Identity ID</span>
                <input value={identityId} onChange={(e) => setIdentityId(e.target.value)} className="mt-1 w-full border border-line rounded px-2 py-1.5" />
              </label>
              <label className="block">
                <span className="text-ink-muted">Mati Verification ID</span>
                <input value={verificationId} onChange={(e) => setVerificationId(e.target.value)} className="mt-1 w-full border border-line rounded px-2 py-1.5" />
              </label>
            </>
          )}
        </div>
        <div className="flex justify-end gap-2 px-4 py-3 border-t border-line">
          <button onClick={onClose} disabled={busy} className="px-4 py-1.5 text-sm rounded-md border border-line text-ink-muted hover:bg-surface-2">Close</button>
          <button
            onClick={modal === 'email' ? onConfirmEmail : modal === 'sms' ? onConfirmSms : modal === 'fetch' ? onConfirmFetch : onConfirmUpdate}
            disabled={busy || (modal === 'update' && !updateValid)}
            className="px-4 py-1.5 text-sm font-medium rounded-md bg-primary text-white hover:bg-primary disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {busy ? 'Working…' : modal === 'update' ? 'Update' : modal === 'fetch' ? 'Fetch' : 'Send'}
          </button>
        </div>
      </div>
    </div>
  )
}

/** Per-document upload card — V8 customerkyc-section.blade mirror.
 *  Shows a PDF/image preview tile (or a placeholder for unfilled slots),
 *  with a pencil icon (top-right) to pick/replace the file and a red
 *  trash icon (top-right) to delete the existing upload. A queued (not-
 *  yet-submitted) file gets a small "Selected" badge so reviewers know
 *  what will go up when they hit the Submit button. */
function KycDocCard({
  label, url, hasFile, queued, deleting,
  canUpload, disabledTooltip,
  onQueue, onPreview, onDelete,
}: {
  label: string
  url: string | null
  hasFile: boolean
  queued?: File
  deleting: boolean
  /** Render pencil + trash visually disabled and suppress click handlers
   *  when false. The user can still click the preview tile to view an
   *  already-uploaded doc — read access isn't gated. */
  canUpload: boolean
  /** Tooltip text shown on the disabled controls (also used as aria-label). */
  disabledTooltip: string
  onQueue: (file: File | null) => void
  onPreview: () => void
  onDelete: () => void
}) {
  const isImage = url ? /\.(jpg|jpeg|png|gif|webp)$/i.test(url) : false
  const editTitle = canUpload ? (hasFile ? 'Replace file' : 'Upload file') : disabledTooltip
  const deleteTitle = canUpload ? 'Delete uploaded document' : disabledTooltip

  return (
    <div className="relative bg-surface border border-line rounded-lg p-2 hover:border-primary transition">
      {/* Action icons (top-right) */}
      <div className="absolute top-1.5 right-1.5 flex items-center gap-1 z-10">
        <label
          title={editTitle}
          aria-label={editTitle}
          aria-disabled={!canUpload}
          className={`w-6 h-6 flex items-center justify-center rounded-full bg-surface border border-line text-ink-muted shadow-sm ${
            canUpload ? 'hover:bg-surface-2 cursor-pointer' : 'opacity-50 cursor-not-allowed'
          }`}
        >
          <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
          </svg>
          <input
            type="file"
            className="hidden"
            accept="image/*,application/pdf"
            disabled={!canUpload}
            onChange={(e) => canUpload && onQueue(e.target.files?.[0] ?? null)}
          />
        </label>
        {hasFile && (
          <button
            type="button"
            title={deleteTitle}
            aria-label={deleteTitle}
            onClick={canUpload ? onDelete : undefined}
            disabled={!canUpload || deleting}
            className="w-6 h-6 flex items-center justify-center rounded-full bg-surface border border-status-danger-fg text-status-danger-fg hover:bg-status-danger-bg disabled:opacity-50 disabled:cursor-not-allowed shadow-sm"
          >
            {deleting ? '…' : (
              <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3" />
              </svg>
            )}
          </button>
        )}
      </div>

      {/* Preview tile */}
      <button
        type="button"
        onClick={() => { if (hasFile && url) onPreview() }}
        className={`block w-full h-32 rounded-md border border-dashed flex items-center justify-center mb-2 ${hasFile ? 'border-line bg-surface-2 hover:bg-surface-2' : 'border-line bg-surface-2'}`}
        aria-label={label}
      >
        {hasFile && url ? (
          isImage ? (
            <img src={url} alt={label} className="max-h-full max-w-full object-contain" />
          ) : (
            <div className="flex flex-col items-center text-status-danger-fg">
              <svg className="w-10 h-10" fill="currentColor" viewBox="0 0 20 20">
                <path fillRule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clipRule="evenodd" />
              </svg>
              <span className="text-[10px] font-bold text-status-danger-fg mt-0.5">PDF</span>
            </div>
          )
        ) : (
          <svg className="w-10 h-10 text-ink-faint" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={1.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
          </svg>
        )}
      </button>

      {/* Document label + queued indicator */}
      <p className="text-xs text-ink-muted text-center truncate" title={label}>{label}</p>
      {queued && (
        <p className="text-[10px] text-primary text-center mt-0.5 truncate" title={queued.name}>
          → {queued.name}
        </p>
      )}
    </div>
  )
}

// ─── Tab: Logs (SMS/Email + Activity) ────────────────────────────

// ─── Tab: All Documents (attachments + policy docs + KYC) ───

// MIS retail products whose policy wording comes from the documents table
// per product + plan (old edit page Documents tab parity) instead of the
// single static per-product PDF.
const WORDING_DOC_PRODUCT_IDS = [1, 2, 3, 4, 5, 9]

function DocumentsTab({ policyId, productId, showCancelNote = false, policyNumber }: { policyId: number; productId: number; showCancelNote?: boolean; policyNumber: string }) {
  const isMIS = MIS_PRODUCT_IDS.includes(productId)

  // Per-plan wording documents (documents table) — each plan has its own
  // wording, so the static product-level PDF is only a fallback when the
  // policy's product/plan has no rows configured.
  const { data: wordingDocs } = useQuery({
    queryKey: ['policy', policyId, 'wordingDocs'],
    queryFn: () => fetchPolicyWordingDocs(policyId),
    enabled: WORDING_DOC_PRODUCT_IDS.includes(productId),
    staleTime: 5 * 60 * 1000,
  })
  const hasPlanWordings = (wordingDocs?.length ?? 0) > 0

  const openStaticPdf = async (kind: 'policy-wording-pdf' | 'complaint-procedure-pdf') => {
    try {
      const r = await apiClient.get(`/policies/${policyId}/${kind}`, { responseType: 'blob' })
      const blob = new Blob([r.data], { type: 'application/pdf' })
      const url = URL.createObjectURL(blob)
      window.open(url, '_blank')
      setTimeout(() => URL.revokeObjectURL(url), 60_000)
    } catch (e: any) { alert(e?.response?.data?.error || 'Failed to open document') }
  }
  const { data: allDocs, isLoading, refetch: refetchDocs } = usePolicyAttachments(policyId, true)
  const [previewUrl, setPreviewUrl] = useState<string | null>(null)
  const [filter, setFilter] = useState<string>('all')
  const [generating, setGenerating] = useState<string | null>(null)

  // Stored generated PDFs (Policy Document / V2 Quote Sheet) — every
  // generation stores a v2_pdf_jobs row stamped with its action_id, so
  // the tab lists the documents belonging to the selected action for
  // direct download (no re-generation). DOM/COM + specialist products
  // use this pipeline.
  const showGeneratedDocs = [7, 8, 16, 17, 18, 19, 20, 22].includes(productId)
  const { data: docActionsData } = usePolicyActions(policyId, showGeneratedDocs)
  const docActionHistory: any[] = (docActionsData as any)?.history ?? []
  const defaultDocActionId: number | undefined = (docActionsData as any)?.current?.id ?? docActionHistory[docActionHistory.length - 1]?.id ?? undefined
  const [docActionId, setDocActionId] = useState<number | undefined>(undefined)
  const activeDocActionId = docActionId ?? defaultDocActionId
  const { data: generatedDocs = [], isLoading: genDocsLoading } = useQuery({
    queryKey: ['policy', policyId, 'generated-docs', activeDocActionId ?? 'all'],
    queryFn: async () => {
      const { data } = await apiClient.get<{ data: any[] }>(`/policies/${policyId}/policy-documents`, {
        params: activeDocActionId ? { action_id: activeDocActionId } : {},
      })
      return data.data ?? []
    },
    enabled: showGeneratedDocs && !!policyId,
    staleTime: 60 * 1000,
  })
  const [downloadingJob, setDownloadingJob] = useState<number | null>(null)
  async function downloadGeneratedDoc(jobId: number, _fileName: string, title?: string) {
    setDownloadingJob(jobId)
    try {
      const r = await apiClient.get(`/policies/${policyId}/download-quote-pdf/${jobId}`, { responseType: 'blob' })
      const blob = new Blob([r.data], { type: 'application/pdf' })
      const url = URL.createObjectURL(blob)
      // Canonical filename: ignore the server-side storage name and
      // rebuild it client-side from the doc title so every download
      // lands as {policy}_{DocType}_{date}_{time}.pdf.
      const docType = docTypeFromTitle(title, 'PolicyDocument')
      const a = document.createElement('a')
      a.href = url
      a.download = buildDocFilename(policyNumber, docType, { policyId })
      a.rel = 'noopener'
      document.body.appendChild(a)
      a.click()
      a.remove()
      setTimeout(() => URL.revokeObjectURL(url), 60_000)
    } catch (e: any) { alert(e?.response?.data?.error || 'Failed to download document') }
    setDownloadingJob(null)
  }
  const [uploadFile, setUploadFile] = useState<File | null>(null)
  const [uploadName, setUploadName] = useState('')
  const [uploading, setUploading] = useState(false)
  const [deletingDoc, setDeletingDoc] = useState<string | null>(null)
  const [sendingDocs, setSendingDocs] = useState(false)

  const apiBase = `${(import.meta as any).env.VITE_API_URL}/api/v1`

  async function sendDocuments() {
    if (!confirm('Resend policy documents to the customer\'s registered email address?')) return
    setSendingDocs(true)
    try {
      const freshToken = localStorage.getItem('sanctum_token')
      const r = await fetch(`${apiBase}/policies/${policyId}/send-documents`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${freshToken}`, 'Content-Type': 'application/json', Accept: 'application/json' },
      })
      const d = await r.json().catch(() => ({}))
      alert(d.message || (r.ok ? 'Documents sent.' : 'Failed to send documents.'))
    } catch (e: any) {
      alert(e.message || 'Failed to send documents.')
    }
    setSendingDocs(false)
  }

  async function uploadAttachment() {
    if (!uploadFile) { alert('Select a file first'); return }
    setUploading(true)
    try {
      const fd = new FormData()
      fd.append('file', uploadFile)
      fd.append('name', uploadName || uploadFile.name)
      fd.append('type', 'Documents')
      const r = await fetch(`${apiBase}/policies/${policyId}/attachments`, { method: 'POST', headers: { Authorization: `Bearer ${localStorage.getItem('sanctum_token')}`, Accept: 'application/json' }, body: fd })
      const d = await r.json()
      if (!r.ok) { alert(d.error || d.message || 'Upload failed') } else { setUploadFile(null); setUploadName(''); refetchDocs() }
    } catch (e: any) { alert(e.message) }
    setUploading(false)
  }

  async function deleteDoc(docId: string) {
    // Only user-uploaded attachments (id starts with 'att_') can be deleted via API
    if (!docId.startsWith('att_')) { alert('Only uploaded attachments can be deleted.'); return }
    const numId = docId.replace('att_', '').split('_')[0]
    if (!confirm('Delete this attachment?')) return
    setDeletingDoc(docId)
    try {
      const r = await fetch(`${apiBase}/policies/${policyId}/attachments/${numId}`, { method: 'DELETE', headers: { Authorization: `Bearer ${localStorage.getItem('sanctum_token')}`, 'Content-Type': 'application/json', Accept: 'application/json' } })
      const d = await r.json()
      if (!r.ok) alert(d.error || 'Failed')
      else refetchDocs()
    } catch (e: any) { alert(e.message) }
    setDeletingDoc(null)
  }

  async function generate(type: 'policy-document' | 'cover-note' | 'cancel-note') {
    setGenerating(type)
    try {
      const url = `${apiBase}/policies/${policyId}/generate-${type}`
      const method = 'POST'
      // Read token fresh at call time — stale closure would send expired token
      const freshToken = localStorage.getItem('sanctum_token')
      const freshHeaders = { Authorization: `Bearer ${freshToken}`, 'Content-Type': 'application/json', Accept: 'application/json' }
      const r = await fetch(url, { method, headers: freshHeaders })
      if (!r.ok) { const err = await r.json().catch(() => ({})); alert(err.message || `Failed to generate document`); setGenerating(null); return }
      const contentType = r.headers.get('content-type') || ''
      if (contentType.includes('pdf') || contentType.includes('octet-stream')) {
        const blob = await r.blob()
        window.open(URL.createObjectURL(blob), '_blank')
      } else {
        const d = await r.json()
        if (d.url) window.open(d.url, '_blank')
        else if (d.path) {
          const cdnBase = 'https://d20dgglp0tqnyi.cloudfront.net'
          window.open(d.path.startsWith('http') ? d.path : `${cdnBase}/${d.path}`, '_blank')
        }
        else alert(d.message || 'Document generated — check list below')
      }
      refetchDocs()
    } catch (e: any) { alert(e.message) }
    setGenerating(null)
  }

  const docs = allDocs || []
  const categories = [...new Set(docs.map((d: PolicyAttachment) => d.category || 'other'))]
  const filtered = filter === 'all' ? docs : docs.filter((d: PolicyAttachment) => d.category === filter)

  const catLabels: Record<string, string> = { attachment: 'Attachments', policy_document: 'Policy Documents', kyc: 'KYC / Corporate KYC', other: 'Other' }
  const catColors: Record<string, string> = { attachment: 'bg-status-info-bg text-primary', policy_document: 'bg-status-accent-bg text-status-accent-fg', kyc: 'bg-status-success-bg text-status-success-fg', other: 'bg-surface-2 text-ink-muted' }

  // Generate buttons — Policy Document generation is hidden on DOM/COM +
  // specialist products: those generate it from the Policy Actions tab and
  // this tab offers the STORED per-action document for download instead.
  const genButtons: { type: 'policy-document' | 'cover-note' | 'cancel-note'; label: string; color: string; desc: string }[] = [
    ...(showGeneratedDocs ? [] : [{ type: 'policy-document' as const, label: '📄 Policy Document', color: 'bg-status-accent-fg hover:bg-status-accent-fg', desc: 'Full policy schedule PDF' }]),
    // Cover Note hidden on COM/DOM (product 7, 8) — those products
    // use the policy document directly, no temporary cover note step.
    ...(productId === 7 || productId === 8 ? [] : [{ type: 'cover-note' as const, label: '📋 Cover Note', color: 'bg-primary hover:bg-primary', desc: 'Temporary cover note PDF' }]),
    ...(showCancelNote ? [{ type: 'cancel-note' as const, label: '🚫 Cancel Note', color: 'bg-status-danger-fg hover:bg-status-danger-fg', desc: 'Cancellation notice PDF' }] : []),
  ]

  return (
    <div className="space-y-4">
      {(genButtons.length > 0 || isMIS) && (
      <Card title="Generate Documents">
        <div className="flex flex-wrap gap-3">
          {genButtons.map(({ type, label, color, desc }) => (
            <button key={type} onClick={() => generate(type)} disabled={generating !== null}
              className={`flex flex-col items-start px-4 py-3 rounded-lg text-white text-sm ${color} disabled:opacity-50 min-w-[160px]`}>
              <span className="font-semibold">{generating === type ? 'Generating…' : label}</span>
              <span className="text-[11px] opacity-80 mt-0.5">{desc}</span>
            </button>
          ))}
          {isMIS && (
            <>
              {!hasPlanWordings && (
                <button onClick={() => openStaticPdf('policy-wording-pdf')}
                  className="flex flex-col items-start px-4 py-3 rounded-lg text-white text-sm bg-status-success-fg hover:bg-status-success-fg min-w-[160px]">
                  <span className="font-semibold">📜 Policy Wording</span>
                  <span className="text-[11px] opacity-80 mt-0.5">Standard wording for this product</span>
                </button>
              )}
              <button onClick={() => openStaticPdf('complaint-procedure-pdf')}
                className="flex flex-col items-start px-4 py-3 rounded-lg text-white text-sm bg-status-warning-fg hover:bg-status-warning-fg min-w-[160px]">
                <span className="font-semibold">📣 Complaint Procedure</span>
                <span className="text-[11px] opacity-80 mt-0.5">Customer complaints procedure</span>
              </button>
              <button onClick={sendDocuments} disabled={sendingDocs}
                className="flex flex-col items-start px-4 py-3 rounded-lg text-white text-sm bg-status-success-fg hover:bg-status-success-fg disabled:opacity-50 min-w-[160px]">
                <span className="font-semibold">{sendingDocs ? 'Sending…' : '✉️ Resend Documents'}</span>
                <span className="text-[11px] opacity-80 mt-0.5">Email policy documents to customer</span>
              </button>
            </>
          )}
        </div>
      </Card>
      )}

      {/* Stored generated documents — one list per policy action. Generated
          PDFs are stored as v2_pdf_jobs rows stamped with action_id, so
          whichever action is picked here shows ITS stored documents for
          direct download without re-generating. */}
      {showGeneratedDocs && (
        <Card title="Stored Policy Documents (per action)">
          {docActionHistory.length > 0 && (
            <div className="flex items-center gap-2 flex-wrap mb-3">
              <label className="text-xs font-medium text-ink-muted whitespace-nowrap">Policy Action</label>
              <select
                value={activeDocActionId ?? ''}
                onChange={e => setDocActionId(e.target.value ? Number(e.target.value) : undefined)}
                className="min-w-[300px] px-3 py-2 border border-line rounded text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                title="Generated documents are stored per policy action — pick an action to see its documents"
              >
                {[...docActionHistory].sort((a: any, b: any) => {
                  // Sort by effective_from DESC (most recent first); tiebreak by id DESC
                  const da = a.effectiveFrom ? new Date(a.effectiveFrom).getTime() : 0
                  const db = b.effectiveFrom ? new Date(b.effectiveFrom).getTime() : 0
                  if (db !== da) return db - da
                  return (b.id || 0) - (a.id || 0)
                }).map((a: any) => {
                  const from = a.effectiveFrom ? new Date(a.effectiveFrom).toLocaleDateString('en-GB') : '?'
                  const to   = a.effectiveTo   ? new Date(a.effectiveTo).toLocaleDateString('en-GB')   : '?'
                  return (
                    <option key={a.id} value={a.id}>
                      {a.transactionType} — {a.status} ({from} – {to})
                    </option>
                  )
                })}
              </select>
            </div>
          )}
          {genDocsLoading ? (
            <ProgressBar isLoading label="Loading stored documents" className="max-w-xs mx-auto py-2" />
          ) : generatedDocs.length === 0 ? (
            <p className="text-sm text-ink-faint">No stored Policy Document for the selected action. Generate it from the Policy Actions tab ("Policy Doc") — it will appear here.</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-surface-2 text-ink-muted uppercase text-xs">
                  <tr>
                    <th className="px-3 py-2 text-left">Document</th>
                    <th className="px-3 py-2 text-left">File</th>
                    <th className="px-3 py-2 text-left">Generated</th>
                    <th className="px-3 py-2 text-right">Download</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-line">
                  {generatedDocs.map((d: any) => (
                    <tr key={d.jobId} className="hover:bg-surface-2">
                      <td className="px-3 py-2 font-medium">{d.title}</td>
                      <td className="px-3 py-2 text-ink-muted truncate max-w-[280px]" title={d.fileName}>{d.fileName}</td>
                      <td className="px-3 py-2 text-ink-muted">{d.createdAt || '—'}</td>
                      <td className="px-3 py-2 text-right">
                        <button onClick={() => downloadGeneratedDoc(d.jobId, d.fileName, d.title)} disabled={downloadingJob === d.jobId}
                          className="px-3 py-1.5 bg-status-accent-fg text-white rounded text-xs hover:bg-status-accent-fg disabled:opacity-50">
                          {downloadingJob === d.jobId ? 'Downloading…' : '⬇ Download'}
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Card>
      )}

      {/* Per-plan policy wording — old edit page Documents tab parity: the
          documents-table wordings for this policy's product and plan, the
          same set the document emails attach. */}
      {hasPlanWordings && (
        <Card title="Policy Wording">
          <p className="text-sm text-ink-muted mb-2">Wording documents for this policy's product and plan. These are the documents sent to the customer's email.</p>
          <div className="space-y-1">
            {wordingDocs!.map(d => (
              <div key={d.id} className="text-sm">
                <span className="text-ink-faint mr-1">⇒</span>
                <a href={d.url} target="_blank" rel="noreferrer" className="text-primary hover:underline">{d.name}</a>
              </div>
            ))}
          </div>
        </Card>
      )}

      {isLoading && <ProgressBar isLoading label="Loading documents" className="max-w-xs mx-auto py-4" />}

      {/* Category filter pills */}
      {docs.length > 0 && (
        <div className="flex items-center gap-2 flex-wrap">
          <button onClick={() => setFilter('all')} className={`px-3 py-1.5 rounded-full text-xs font-medium border transition-colors ${filter === 'all' ? 'bg-ink text-white border-ink' : 'bg-surface text-ink-muted border-line hover:bg-surface-2'}`}>All ({docs.length})</button>
          {categories.map(cat => {
            const count = docs.filter((d: PolicyAttachment) => d.category === cat).length
            return <button key={cat} onClick={() => setFilter(cat)} className={`px-3 py-1.5 rounded-full text-xs font-medium border transition-colors ${filter === cat ? 'bg-ink text-white border-ink' : 'bg-surface text-ink-muted border-line hover:bg-surface-2'}`}>{catLabels[cat] || cat} ({count})</button>
          })}
        </div>
      )}

      {/* Upload Attachment */}
      <Card title="Upload Attachment">
        <div className="flex flex-wrap items-end gap-3">
          <div className="flex-1 min-w-[200px]">
            <label className="block text-xs font-medium text-ink-muted mb-1">Document Name</label>
            <input value={uploadName} onChange={e => setUploadName(e.target.value)} placeholder="Optional — defaults to file name"
              className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none" />
          </div>
          <div className="flex-1 min-w-[200px]">
            <label className="block text-xs font-medium text-ink-muted mb-1">File *</label>
            <input type="file" onChange={e => setUploadFile(e.target.files?.[0] || null)}
              className="w-full text-sm text-ink-muted file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-medium file:bg-status-info-bg file:text-primary hover:file:bg-status-info-bg" />
          </div>
          <button onClick={uploadAttachment} disabled={uploading || !uploadFile} className="px-4 py-2 bg-status-success-fg text-white rounded text-sm hover:bg-status-success-fg disabled:opacity-50 whitespace-nowrap">
            {uploading ? 'Uploading…' : '⬆ Upload'}
          </button>
        </div>
      </Card>

      {!isLoading && docs.length === 0 && <p className="text-ink-faint py-4 text-center text-sm">No uploaded documents. Use the buttons above to generate or upload documents.</p>}

      {/* Docs grid with delete button on user-uploaded attachments */}
      {docs.length > 0 && (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 mt-2">
          {filtered.map((doc: PolicyAttachment) => (
            <div key={doc.id} className="bg-surface rounded-xl border border-line p-4 hover:border-primary hover:shadow-md transition-all group relative">
              <button onClick={() => doc.url && (doc.url.endsWith('.pdf') ? window.open(doc.url, '_blank') : setPreviewUrl(doc.url))} className="w-full text-left">
                <div className="flex items-start gap-3">
                  <div className="w-10 h-10 rounded-lg bg-surface-2 flex items-center justify-center flex-shrink-0 group-hover:bg-status-info-bg">
                    {doc.url?.endsWith('.pdf')
                      ? <svg className="w-5 h-5 text-status-danger-fg" fill="currentColor" viewBox="0 0 20 20"><path fillRule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clipRule="evenodd" /></svg>
                      : <svg className="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>}
                  </div>
                  <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-ink truncate group-hover:text-primary">{doc.name || 'Document'}</p>
                    <div className="flex items-center gap-2 mt-1">
                      <span className={`text-[10px] font-semibold px-2 py-0.5 rounded-full ${catColors[doc.category || 'other']}`}>{doc.type || doc.category || 'Document'}</span>
                      {doc.createdAt && <span className="text-[10px] text-ink-faint">{new Date(doc.createdAt).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })}</span>}
                    </div>
                  </div>
                </div>
              </button>
              {String(doc.id).startsWith('att_') && (
                <button onClick={() => deleteDoc(String(doc.id))} disabled={deletingDoc === String(doc.id)}
                  className="absolute top-2 right-2 w-6 h-6 flex items-center justify-center rounded-full bg-status-danger-bg hover:bg-status-danger-bg text-status-danger-fg text-xs disabled:opacity-50 opacity-0 group-hover:opacity-100 transition-opacity">
                  {deletingDoc === String(doc.id) ? '…' : '✕'}
                </button>
              )}
            </div>
          ))}
        </div>
      )}

      <DocumentPreviewModal url={previewUrl} onClose={() => setPreviewUrl(null)} />
    </div>
  )
}

// ─── Tab: Attachments (V8-style multi-file rows) ───────────────────────────
//
// Mirrors graphiteBWV8 admin/policy/attachment.blade.php (line 1) +
// PolicyController::attachmentData (5983):
//   - "Add" toggle reveals an inline upload form that supports MULTIPLE
//     attachment rows in a single submit (each row = name + document type +
//     N files), exactly like V8's `name[]` / `type[]` / `attachment_file[i][]`.
//   - Table renders one row per policy_attachments record; each row may
//     show several thumbnail/preview tiles for the files it carries.
//   - Search + page-size + "Showing X results" footer match V8's
//     DataTable controls.

type AttachmentDraftRow = {
  name: string
  type: string
  files: File[]
}

function blankDraftRow(defaultType: string): AttachmentDraftRow {
  return { name: '', type: defaultType, files: [] }
}

function AttachmentsTab({ policyId }: { policyId: number }) {
  const { data: rows = [], isLoading, refetch } = usePolicyAttachmentList(policyId, true)
  const { data: fileTypes = [] } = usePolicyFileTypes(true)
  const defaultType = fileTypes[0]?.id ?? 'Documents'

  const [showUploadForm, setShowUploadForm] = useState(false)
  const [draft, setDraft] = useState<AttachmentDraftRow[]>([blankDraftRow(defaultType)])
  const [uploading, setUploading] = useState(false)
  const [uploadError, setUploadError] = useState<string | null>(null)

  const [previewUrl, setPreviewUrl] = useState<string | null>(null)
  const [deletingId, setDeletingId] = useState<number | null>(null)

  const [search, setSearch] = useState('')
  const [pageSize, setPageSize] = useState(10)
  const [page, setPage] = useState(1)

  // Re-seed the default document type once the lookup resolves (the
  // initial render mounts with whatever defaultType was at that moment).
  useEffect(() => {
    if (fileTypes.length > 0 && draft.length === 1 && !draft[0].type) {
      setDraft([blankDraftRow(fileTypes[0].id)])
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [fileTypes.length])

  function updateRow(i: number, patch: Partial<AttachmentDraftRow>) {
    setDraft(prev => prev.map((r, idx) => idx === i ? { ...r, ...patch } : r))
  }
  function addRow() {
    setDraft(prev => [...prev, blankDraftRow(defaultType)])
  }
  function removeRow(i: number) {
    setDraft(prev => prev.length === 1 ? prev : prev.filter((_, idx) => idx !== i))
  }
  function appendFiles(i: number, list: FileList | null) {
    if (!list || list.length === 0) return
    updateRow(i, { files: [...draft[i].files, ...Array.from(list)] })
  }
  function removeFile(rowIdx: number, fileIdx: number) {
    updateRow(rowIdx, { files: draft[rowIdx].files.filter((_, idx) => idx !== fileIdx) })
  }

  async function submitUpload() {
    setUploadError(null)
    // V8 requires at least one file per row (the blade marks attachment_file required).
    const empty = draft.findIndex(r => r.files.length === 0)
    if (empty !== -1) {
      setUploadError(`Row ${empty + 1}: please choose at least one file.`)
      return
    }
    const missingName = draft.findIndex(r => !r.name.trim())
    if (missingName !== -1) {
      setUploadError(`Row ${missingName + 1}: name is required.`)
      return
    }
    setUploading(true)
    try {
      const fd = new FormData()
      draft.forEach((row, i) => {
        fd.append(`name[${i}]`, row.name)
        fd.append(`type[${i}]`, row.type)
        row.files.forEach(f => fd.append(`attachment_file[${i}][]`, f))
      })
      await apiClient.post(`/policies/${policyId}/attachments`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      setDraft([blankDraftRow(defaultType)])
      setShowUploadForm(false)
      refetch()
    } catch (e: any) {
      setUploadError(e?.response?.data?.message || e?.response?.data?.error || 'Upload failed')
    } finally {
      setUploading(false)
    }
  }

  async function deleteRow(id: number) {
    if (!confirm('Are you sure you want to delete the Attachment ?')) return
    setDeletingId(id)
    try {
      await apiClient.delete(`/policies/${policyId}/attachments/${id}`)
      refetch()
    } catch (e: any) {
      alert(e?.response?.data?.message || e?.response?.data?.error || 'Delete failed')
    } finally {
      setDeletingId(null)
    }
  }

  // Search / paginate
  const filtered = !search.trim()
    ? rows
    : rows.filter(r => {
        const t = search.toLowerCase()
        return [r.name, r.type, ...(r.files.map(f => f.name))]
          .some(v => v && String(v).toLowerCase().includes(t))
      })
  const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize))
  const pageStart = (page - 1) * pageSize
  const pageRows = filtered.slice(pageStart, pageStart + pageSize)
  // Snap page back if filter shrinks the result set below current page.
  useEffect(() => { if (page > totalPages) setPage(1) }, [totalPages, page])

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h3 className="text-lg font-semibold text-ink">Attachments</h3>
        <button
          onClick={() => setShowUploadForm(s => !s)}
          className="px-4 py-1.5 text-sm font-medium rounded-md bg-status-accent-fg text-white hover:bg-status-accent-fg"
        >
          {showUploadForm ? 'Cancel' : 'Add'}
        </button>
      </div>

      {/* Upload form (mirrors the V8 collapse block — multi-row, multi-file). */}
      {showUploadForm && (
        <div className="bg-surface border border-line rounded-lg p-4 space-y-4">
          {draft.map((row, i) => (
            <div key={i} className="border border-line rounded-md p-3 space-y-3 bg-surface-2/50">
              <div className="flex items-start justify-between">
                <span className="text-xs font-medium text-ink-muted">Attachment #{i + 1}</span>
                {draft.length > 1 && (
                  <button type="button" onClick={() => removeRow(i)}
                    className="text-xs text-status-danger-fg hover:underline">Remove row</button>
                )}
              </div>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-ink-muted mb-1">Name <span className="text-status-danger-fg">*</span></label>
                  <input value={row.name} onChange={e => updateRow(i, { name: e.target.value })}
                    placeholder="Enter Name" required
                    className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-status-accent-fg focus:outline-none" />
                </div>
                <div>
                  <label className="block text-xs font-medium text-ink-muted mb-1">Document Type <span className="text-status-danger-fg">*</span></label>
                  <select value={row.type} onChange={e => updateRow(i, { type: e.target.value })}
                    className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-status-accent-fg focus:outline-none">
                    {fileTypes.map(t => <option key={t.id} value={t.id}>{t.name}</option>)}
                  </select>
                </div>
              </div>
              <div>
                <label className="block text-xs font-medium text-ink-muted mb-1">Attachment</label>
                <div className="flex flex-wrap items-center gap-2">
                  {row.files.map((f, fi) => (
                    <span key={fi} className="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-status-accent-bg text-status-accent-fg text-xs">
                      {f.name}
                      <button type="button" onClick={() => removeFile(i, fi)}
                        className="ml-1 text-status-accent-fg hover:text-status-accent-fg" title="Remove">×</button>
                    </span>
                  ))}
                  <label className="px-3 py-1.5 text-xs font-medium rounded border border-dashed border-line text-ink-muted hover:bg-surface-2 cursor-pointer">
                    + Add file
                    <input type="file" className="hidden" multiple
                      onChange={e => { appendFiles(i, e.target.files); e.target.value = '' }} />
                  </label>
                </div>
              </div>
            </div>
          ))}

          <div className="flex items-center justify-between pt-2">
            <button type="button" onClick={addRow}
              className="px-3 py-1.5 text-xs font-medium rounded border border-line text-ink-muted hover:bg-surface-2">
              + Add New Attachment
            </button>
            <div className="flex items-center gap-2">
              {uploadError && <span className="text-xs text-status-danger-fg">{uploadError}</span>}
              <button type="button" onClick={submitUpload} disabled={uploading}
                className="px-4 py-1.5 text-sm font-medium rounded-md bg-status-accent-fg text-white hover:bg-status-accent-fg disabled:opacity-50">
                {uploading ? 'Uploading…' : 'Submit'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Search + page-size controls (V8 DataTable pattern). */}
      <div className="flex items-center justify-between flex-wrap gap-2">
        <input
          type="text" placeholder="Search" value={search}
          onChange={e => { setSearch(e.target.value); setPage(1) }}
          className="px-3 py-1.5 border border-line rounded text-sm focus:ring-1 focus:ring-status-accent-fg focus:outline-none w-64"
        />
        <div className="flex items-center gap-2 text-sm text-ink-muted">
          <span>Show</span>
          <select value={pageSize} onChange={e => { setPageSize(Number(e.target.value)); setPage(1) }}
            className="px-2 py-1 border border-line rounded text-sm">
            <option value={10}>10</option>
            <option value={25}>25</option>
            <option value={50}>50</option>
            <option value={100}>100</option>
          </select>
        </div>
      </div>

      {/* Attachment table — one row per policy_attachments record. */}
      <div className="bg-surface border border-line rounded-lg overflow-hidden">
        <table className="w-full text-sm">
          <thead className="bg-surface-2 text-ink-muted text-xs uppercase tracking-wider">
            <tr>
              <th className="px-4 py-3 text-left font-medium">Name</th>
              <th className="px-4 py-3 text-left font-medium">Document Type</th>
              <th className="px-4 py-3 text-left font-medium">Attachment</th>
              <th className="px-4 py-3 text-left font-medium">Action</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-line">
            {isLoading ? (
              <tr><td colSpan={4} className="px-4 py-8 text-center text-ink-faint">Loading attachments…</td></tr>
            ) : pageRows.length === 0 ? (
              <tr><td colSpan={4} className="px-4 py-8 text-center text-ink-faint">
                {rows.length === 0 ? 'No attachments uploaded yet.' : 'No matching attachments.'}
              </td></tr>
            ) : (
              pageRows.map(row => (
                <tr key={row.id} className="hover:bg-surface-2">
                  <td className="px-4 py-3 align-top font-medium text-ink max-w-xs break-words">{row.name}</td>
                  <td className="px-4 py-3 align-top text-ink-muted whitespace-nowrap">{row.type}</td>
                  <td className="px-4 py-3 align-top">
                    <div className="flex flex-wrap gap-2">
                      {row.files.length === 0
                        ? <span className="text-xs text-ink-faint italic">No files</span>
                        : row.files.map((f, idx) => (
                            <AttachmentThumb key={idx} file={f} onPreview={url => setPreviewUrl(url)} />
                          ))}
                    </div>
                  </td>
                  <td className="px-4 py-3 align-top">
                    <button onClick={() => deleteRow(row.id)} disabled={deletingId === row.id}
                      className="px-3 py-1 text-xs font-medium rounded border border-status-success-fg text-status-success-fg hover:bg-status-success-bg disabled:opacity-50">
                      {deletingId === row.id ? 'Deleting…' : 'DELETE'}
                    </button>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>

        {/* Pager footer */}
        <div className="flex items-center justify-between px-4 py-2 text-xs text-ink-muted border-t border-line bg-surface-2">
          <span>
            {filtered.length === 0
              ? 'Showing 0 results'
              : `Showing ${pageStart + 1}–${Math.min(pageStart + pageSize, filtered.length)} of ${filtered.length} results`}
          </span>
          {totalPages > 1 && (
            <div className="flex items-center gap-1">
              <button disabled={page === 1} onClick={() => setPage(p => Math.max(1, p - 1))}
                className="px-2 py-1 rounded border border-line hover:bg-surface disabled:opacity-40">Prev</button>
              <span className="px-2">Page {page} / {totalPages}</span>
              <button disabled={page === totalPages} onClick={() => setPage(p => Math.min(totalPages, p + 1))}
                className="px-2 py-1 rounded border border-line hover:bg-surface disabled:opacity-40">Next</button>
            </div>
          )}
        </div>
      </div>

      <DocumentPreviewModal url={previewUrl} onClose={() => setPreviewUrl(null)} />
    </div>
  )
}

/** File preview tile — matches V8's clickable kt-avatar with kind-based icon. */
function AttachmentThumb({ file, onPreview }: {
  file: PolicyAttachmentFile
  onPreview: (url: string) => void
}) {
  // Icons mirror the V8 image/pdf/word/excel/doc choice. Inline SVGs so we
  // don't need to ship binary assets just for this view.
  const iconClass = "w-10 h-10"
  const inner = (() => {
    switch (file.kind) {
      case 'image':
        return <img src={file.url} alt={file.name} className="w-16 h-16 object-cover rounded" />
      case 'pdf':
        return (
          <div className="w-16 h-16 rounded bg-status-danger-bg border border-status-danger-fg flex items-center justify-center">
            <svg className={`${iconClass} text-status-danger-fg`} fill="currentColor" viewBox="0 0 20 20">
              <path fillRule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clipRule="evenodd" />
            </svg>
          </div>
        )
      case 'word':
        return (
          <div className="w-16 h-16 rounded bg-status-info-bg border border-primary flex items-center justify-center text-xs font-bold text-primary">DOC</div>
        )
      case 'excel':
        return (
          <div className="w-16 h-16 rounded bg-status-success-bg border border-status-success-fg flex items-center justify-center text-xs font-bold text-status-success-fg">XLS</div>
        )
      default:
        return (
          <div className="w-16 h-16 rounded bg-surface-2 border border-line flex items-center justify-center text-[10px] font-medium text-ink-muted uppercase">
            {file.extension || 'file'}
          </div>
        )
    }
  })()

  function handleClick() {
    // Images get the modal preview; everything else opens in a new tab
    // (matches the V8 anchor `target="_blank" download=""` behaviour).
    if (file.kind === 'image') onPreview(file.url)
    else window.open(file.url, '_blank')
  }

  return (
    <button type="button" onClick={handleClick} title={file.name}
      className="flex flex-col items-center gap-1 group">
      {inner}
      <span className="text-[10px] text-ink-muted truncate max-w-[64px] group-hover:text-status-accent-fg">{file.name}</span>
    </button>
  )
}

function LogsTab({ policyId }: { policyId: number }) {
  const { data, isLoading } = usePolicyLogs(policyId, true)
  const [smsSearch, setSmsSearch] = useState('')
  const [activitySearch, setActivitySearch] = useState('')
  const [emailPreview, setEmailPreview] = useState<string | null>(null)
  const [smsPage, setSmsPage] = useState(1)
  const [actPage, setActPage] = useState(1)
  const [exportingActivity, setExportingActivity] = useState(false)
  const PAGE_SIZE = 10

  async function handleExportActivityLog() {
    if (exportingActivity) return
    setExportingActivity(true)
    try {
      await exportPolicyActivityLog(policyId)
    } catch (e: any) {
      alert(e.message || 'Export failed. Please try again.')
    } finally {
      setExportingActivity(false)
    }
  }

  if (isLoading) return <ProgressBar isLoading label="Loading logs" className="max-w-xs mx-auto py-8" />

  const allSmsLogs = data?.smsEmailLogs ?? []
  const allActivityLogs = data?.activityLogs ?? []

  if (allSmsLogs.length === 0 && allActivityLogs.length === 0) {
    return <EmptyState message="No logs found for this policy." />
  }

  // Filter SMS/Email logs by search
  const filteredSms = smsSearch
    ? allSmsLogs.filter((log: SmsEmailLog) => {
        const t = smsSearch.toLowerCase()
        return [log.type, log.recipient, log.message, log.messageId, log.hook, log.status, log.createdAt]
          .some(v => v && String(v).toLowerCase().includes(t))
      })
    : allSmsLogs

  // Filter Activity logs by search
  const filteredAct = activitySearch
    ? allActivityLogs.filter((log: ActivityLog) => {
        const t = activitySearch.toLowerCase()
        return [log.activityBy, log.ipAddress, log.doneFrom, log.activityTag, log.url,
          JSON.stringify(log.oldValues), JSON.stringify(log.newValues), log.activityDone]
          .some(v => v && String(v).toLowerCase().includes(t))
      })
    : allActivityLogs

  // Paginate
  const smsTotalPages = Math.max(1, Math.ceil(filteredSms.length / PAGE_SIZE))
  const smsLogs = filteredSms.slice((smsPage - 1) * PAGE_SIZE, smsPage * PAGE_SIZE)
  const actTotalPages = Math.max(1, Math.ceil(filteredAct.length / PAGE_SIZE))
  const activityLogs = filteredAct.slice((actPage - 1) * PAGE_SIZE, actPage * PAGE_SIZE)

  function handleSmsSearch(val: string) { setSmsSearch(val); setSmsPage(1) }
  function handleActSearch(val: string) { setActivitySearch(val); setActPage(1) }

  return (
    <div className="space-y-6">
      {/* SMS / Email Logs */}
      {allSmsLogs.length > 0 && (
        <Card title={`SMS / Email Logs (${filteredSms.length})`}>
          <div className="mb-3">
            <input
              type="text"
              placeholder="Search logs..."
              value={smsSearch}
              onChange={e => handleSmsSearch(e.target.value)}
              className="w-full max-w-sm px-3 py-1.5 border border-line rounded text-sm focus:outline-none focus:ring-1 focus:ring-brand-navy focus:border-brand-navy"
            />
          </div>
          <DualScrollTable>
            <table className="w-full text-sm">
              <thead className="bg-surface-2 text-ink-muted uppercase text-xs">
                <tr>
                  <th className="px-3 py-2 text-left">Type</th>
                  <th className="px-3 py-2 text-left">Message Id</th>
                  <th className="px-3 py-2 text-left">Message</th>
                  <th className="px-3 py-2 text-left">Hook</th>
                  <th className="px-3 py-2 text-left">Send To</th>
                  <th className="px-3 py-2 text-left">Sent Date</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {smsLogs.map((log: SmsEmailLog) => (
                  <tr key={log.id} className="hover:bg-surface-2">
                    <td className="px-3 py-2">
                      <span className={`inline-flex px-2 py-0.5 rounded text-xs font-medium ${
                        log.type?.toLowerCase() === 'email' ? 'bg-status-accent-bg text-status-accent-fg' : 'bg-status-info-bg text-primary'
                      }`}>
                        {log.type || '—'}
                      </span>
                    </td>
                    <td className="px-3 py-2 text-xs max-w-[180px] truncate" title={log.messageId || ''}>{log.messageId || '—'}</td>
                    <td className="px-3 py-2 text-xs max-w-[300px]">
                      {log.type?.toLowerCase() === 'email' && log.htmlContent ? (
                        <button
                          onClick={() => setEmailPreview(log.htmlContent!)}
                          className="text-primary hover:underline text-xs"
                        >
                          View Email
                        </button>
                      ) : (
                        <div className="line-clamp-3" title={log.message || ''}>{log.message || '—'}</div>
                      )}
                    </td>
                    <td className="px-3 py-2 text-xs">{log.hook || '—'}</td>
                    <td className="px-3 py-2 font-mono text-xs">{log.recipient || '—'}</td>
                    <td className="px-3 py-2 text-ink-muted whitespace-nowrap">{log.createdAt || '—'}</td>
                  </tr>
                ))}
                {smsLogs.length === 0 && (
                  <tr><td colSpan={6} className="px-3 py-4 text-center text-ink-faint">No matching logs.</td></tr>
                )}
              </tbody>
            </table>
          </DualScrollTable>
          {/* SMS Pagination */}
          {smsTotalPages > 1 && (
            <div className="flex items-center justify-between pt-3 border-t border-line mt-2 text-sm">
              <span className="text-ink-muted">
                Showing {(smsPage - 1) * PAGE_SIZE + 1}–{Math.min(smsPage * PAGE_SIZE, filteredSms.length)} of {filteredSms.length}
              </span>
              <div className="flex gap-1">
                <button onClick={() => setSmsPage(p => Math.max(1, p - 1))} disabled={smsPage === 1}
                  className="px-2.5 py-1 rounded border border-line text-xs disabled:opacity-40 hover:bg-surface-2">Prev</button>
                {Array.from({ length: smsTotalPages }, (_, i) => i + 1)
                  .filter(p => p === 1 || p === smsTotalPages || Math.abs(p - smsPage) <= 2)
                  .map((p, idx, arr) => (
                    <span key={p}>
                      {idx > 0 && arr[idx - 1] !== p - 1 && <span className="px-1 text-ink-faint">...</span>}
                      <button onClick={() => setSmsPage(p)}
                        className={`px-2.5 py-1 rounded border text-xs ${p === smsPage ? 'bg-brand-navy text-white border-brand-navy' : 'hover:bg-surface-2'}`}>{p}</button>
                    </span>
                  ))}
                <button onClick={() => setSmsPage(p => Math.min(smsTotalPages, p + 1))} disabled={smsPage === smsTotalPages}
                  className="px-2.5 py-1 rounded border border-line text-xs disabled:opacity-40 hover:bg-surface-2">Next</button>
              </div>
            </div>
          )}
        </Card>
      )}

      {/* Email HTML Preview Modal */}
      {emailPreview && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/50 p-4 pt-10 overflow-y-auto" onClick={() => setEmailPreview(null)}>
          <div className="bg-surface rounded-lg shadow-xl w-[90vw] max-w-3xl max-h-[85vh] flex flex-col" onClick={e => e.stopPropagation()}>
            <div className="flex items-center justify-between px-4 py-3 border-b border-line sticky top-0 bg-surface z-10">
              <h3 className="font-semibold text-ink">Email Preview</h3>
              <button onClick={() => setEmailPreview(null)} className="text-ink-faint hover:text-ink-muted text-xl leading-none">&times;</button>
            </div>
            <div className="flex-1 overflow-hidden p-1">
              <iframe
                srcDoc={emailPreview}
                title="Email Preview"
                className="w-full h-full border-0"
                style={{ minHeight: '60vh' }}
                sandbox="allow-same-origin"
              />
            </div>
          </div>
        </div>
      )}

      {/* Activity Log */}
      {allActivityLogs.length > 0 && (
        <Card
          title={`Activity Log (${filteredAct.length})`}
          actions={
            <button
              onClick={handleExportActivityLog}
              disabled={exportingActivity}
              title="Export the full activity log history for this policy to Excel"
              className="px-3 py-1.5 bg-status-success-fg text-white rounded text-xs font-medium hover:bg-status-success-fg disabled:opacity-50"
            >
              {exportingActivity ? 'Exporting…' : '⬇ Export to Excel'}
            </button>
          }
        >
          <div className="mb-3">
            <input
              type="text"
              placeholder="Search activity..."
              value={activitySearch}
              onChange={e => handleActSearch(e.target.value)}
              className="w-full max-w-sm px-3 py-1.5 border border-line rounded text-sm focus:outline-none focus:ring-1 focus:ring-brand-navy focus:border-brand-navy"
            />
          </div>
          <DualScrollTable>
            <table className="w-full text-sm table-fixed">
              <thead className="bg-surface-2 text-ink-muted uppercase text-xs">
                <tr>
                  <th className="px-3 py-2 text-left w-[110px]">Info</th>
                  <th className="px-3 py-2 text-left w-[140px]">Action By</th>
                  <th className="px-3 py-2 text-left">Old Values</th>
                  <th className="px-3 py-2 text-left">New Data</th>
                  <th className="px-3 py-2 text-left w-[130px]">Activity Done</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {activityLogs.map((log: ActivityLog) => (
                  <tr key={log.id} className="hover:bg-surface-2 group relative">
                    <td className="px-3 py-2 text-xs">
                      <div className="relative">
                        <div className="font-medium text-ink-muted">{log.doneFrom || 'System'}</div>
                        <div className="text-ink-faint">{log.activityTag || '—'}</div>
                        {/* Full details on hover */}
                        <div className="hidden group-hover:block absolute left-0 top-full z-20 bg-surface border border-line rounded-lg shadow-lg p-3 w-72 text-xs space-y-1">
                          <div><span className="text-ink-faint font-medium">Id:</span> {log.id}</div>
                          <div><span className="text-ink-faint font-medium">Activity By:</span> {log.activityBy || '—'}</div>
                          <div><span className="text-ink-faint font-medium">IP Address:</span> <span className="font-mono">{log.ipAddress || '—'}</span></div>
                          <div><span className="text-ink-faint font-medium">Done From:</span> {log.doneFrom || '—'}</div>
                          <div><span className="text-ink-faint font-medium">Activity Tag:</span> {log.activityTag || '—'}</div>
                          <div className="break-all"><span className="text-ink-faint font-medium">Url:</span> {log.url || '—'}</div>
                          <div><span className="text-ink-faint font-medium">Activity Done:</span> {log.activityDone || '—'}</div>
                        </div>
                      </div>
                    </td>
                    <td className="px-3 py-2 text-xs align-top">
                      <div className="font-medium text-ink-muted">{log.activityBy || 'System'}</div>
                      {log.ipAddress && <div className="text-[10px] text-ink-faint font-mono truncate" title={log.ipAddress}>{log.ipAddress}</div>}
                    </td>
                    <td className="px-3 py-2 text-xs align-top">
                      {log.oldValues ? (
                        <pre className="whitespace-pre-wrap break-all text-[11px] bg-status-danger-bg text-status-danger-fg rounded p-2 max-h-40 overflow-y-auto">
                          {typeof log.oldValues === 'string' ? log.oldValues : JSON.stringify(log.oldValues, null, 2)}
                        </pre>
                      ) : <span className="text-ink-faint">—</span>}
                    </td>
                    <td className="px-3 py-2 text-xs align-top">
                      {log.newValues ? (
                        <pre className="whitespace-pre-wrap break-all text-[11px] bg-status-success-bg text-status-success-fg rounded p-2 max-h-40 overflow-y-auto">
                          {typeof log.newValues === 'string' ? log.newValues : JSON.stringify(log.newValues, null, 2)}
                        </pre>
                      ) : <span className="text-ink-faint">—</span>}
                    </td>
                    <td className="px-3 py-2 text-ink-muted whitespace-nowrap text-xs">{log.activityDone || '—'}</td>
                  </tr>
                ))}
                {activityLogs.length === 0 && (
                  <tr><td colSpan={4} className="px-3 py-4 text-center text-ink-faint">No matching activity.</td></tr>
                )}
              </tbody>
            </table>
          </DualScrollTable>
          {/* Activity Pagination */}
          {actTotalPages > 1 && (
            <div className="flex items-center justify-between pt-3 border-t border-line mt-2 text-sm">
              <span className="text-ink-muted">
                Showing {(actPage - 1) * PAGE_SIZE + 1}–{Math.min(actPage * PAGE_SIZE, filteredAct.length)} of {filteredAct.length}
              </span>
              <div className="flex gap-1">
                <button onClick={() => setActPage(p => Math.max(1, p - 1))} disabled={actPage === 1}
                  className="px-2.5 py-1 rounded border border-line text-xs disabled:opacity-40 hover:bg-surface-2">Prev</button>
                {Array.from({ length: actTotalPages }, (_, i) => i + 1)
                  .filter(p => p === 1 || p === actTotalPages || Math.abs(p - actPage) <= 2)
                  .map((p, idx, arr) => (
                    <span key={p}>
                      {idx > 0 && arr[idx - 1] !== p - 1 && <span className="px-1 text-ink-faint">...</span>}
                      <button onClick={() => setActPage(p)}
                        className={`px-2.5 py-1 rounded border text-xs ${p === actPage ? 'bg-brand-navy text-white border-brand-navy' : 'hover:bg-surface-2'}`}>{p}</button>
                    </span>
                  ))}
                <button onClick={() => setActPage(p => Math.min(actTotalPages, p + 1))} disabled={actPage === actTotalPages}
                  className="px-2.5 py-1 rounded border border-line text-xs disabled:opacity-40 hover:bg-surface-2">Next</button>
              </div>
            </div>
          )}
        </Card>
      )}
    </div>
  )
}

// ─── Tab: Linked Policy (other policies for same customer) ──────

function LinkedPolicyTab({ policyId, policy }: { policyId: number; policy: Policy }) {
  const [rows, setRows] = useState<any[]>([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let active = true
    setLoading(true)
    apiClient.get(`/policies/${policyId}/linked-policies`)
      .then(r => { if (active) setRows(r.data?.data ?? []) })
      .catch(() => { if (active) setRows([]) })
      .finally(() => { if (active) setLoading(false) })
    return () => { active = false }
  }, [policyId])

  if (loading) return <ProgressBar isLoading label="Loading linked policies" className="max-w-xs mx-auto py-8" />
  if (!rows.length) return <EmptyState message={`No other policies found for ${(policy as any).customer?.firstName || 'this customer'}.`} />

  const statusLabel = (s: any) => {
    const n = Number(s)
    return n === 1 ? 'Active' : n === 2 ? 'Cancelled' : n === 3 ? 'Expired' : n === 0 ? 'Pending' : (s || '—')
  }

  return (
    <Card title={`Linked Policies (${rows.length})`}>
      <DualScrollTable>
        <table className="w-full text-sm">
          <thead className="bg-surface-2 text-ink-muted uppercase text-xs">
            <tr>
              <th className="px-3 py-2 text-left">ID</th>
              <th className="px-3 py-2 text-left">Policy Number</th>
              <th className="px-3 py-2 text-left">Customer</th>
              <th className="px-3 py-2 text-left">Agent</th>
              <th className="px-3 py-2 text-left">Cellphone</th>
              <th className="px-3 py-2 text-left">Product</th>
              <th className="px-3 py-2 text-left">Payment Method</th>
              <th className="px-3 py-2 text-left">Payment Ref</th>
              <th className="px-3 py-2 text-left">Vehicle Plate</th>
              <th className="px-3 py-2 text-left">Status</th>
              <th className="px-3 py-2 text-left">Created</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-line">
            {rows.map(r => (
              <tr key={r.id} className="hover:bg-surface-2">
                <td className="px-3 py-2 text-ink-faint">{r.id}</td>
                <td className="px-3 py-2">
                  <a href={`/policies/${r.id}`} className="text-primary hover:underline font-medium">{r.policyNumber}</a>
                </td>
                <td className="px-3 py-2">{r.customerName || '—'}</td>
                <td className="px-3 py-2">{r.agentName || '—'}</td>
                <td className="px-3 py-2">{r.cellphone || '—'}</td>
                <td className="px-3 py-2">{r.product || '—'}</td>
                <td className="px-3 py-2">{r.paymentMethod || '—'}</td>
                <td className="px-3 py-2 font-mono text-xs">{r.paymentReference || '—'}</td>
                <td className="px-3 py-2 font-mono">{r.vehiclePlate || '—'}</td>
                <td className="px-3 py-2">{statusLabel(r.status)}</td>
                <td className="px-3 py-2 whitespace-nowrap">{r.createdAt ? new Date(r.createdAt).toLocaleDateString('en-GB') : '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </DualScrollTable>
    </Card>
  )
}

// ─── Tab: Schedule Transactions (DPO policies) ──────────────────

function ScheduleTransactionsTab({ policyId, editable, currentMethod, canCancelDpoContract }: { policyId: number; editable: boolean; currentMethod?: string | null; canCancelDpoContract?: boolean }) {
  const { data: schedules, isLoading, refetch } = usePolicyScheduleTransactions(policyId, true)
  const [busy, setBusy] = useState<string | null>(null)
  const [cancellingDpo, setCancellingDpo] = useState(false)
  const [showAdd, setShowAdd] = useState(false)
  const [showBillingDay, setShowBillingDay] = useState(false)
  const [billingDay, setBillingDay] = useState('')
  const [addForm, setAddForm] = useState({ billing_date: '', premium: '', installment: '' })
  const [editRow, setEditRow] = useState<{ id: number; field: 'billing_date' | 'premium'; value: string } | null>(null)

  const post = async (path: string, body?: any, method: 'post' | 'put' = 'post') => {
    setBusy(path)
    try {
      const r = method === 'post' ? await apiClient.post(path, body) : await apiClient.put(path, body)
      await refetch()
      return r.data
    } catch (e: any) {
      alert(e?.response?.data?.error || e?.response?.data?.message || 'Action failed')
    } finally { setBusy(null) }
  }

  const cancelRow = async (id: number) => {
    if (!window.confirm('Cancel this scheduled transaction?')) return
    await post(`/policies/${policyId}/schedule-transactions/${id}/cancel`)
  }
  const cancelAll = async () => {
    if (!window.confirm('Cancel ALL pending/failed scheduled transactions for this policy?')) return
    await post(`/policies/${policyId}/schedule-transactions/cancel-all`)
  }
  // GRA-0194 — stop the LIVE DPO mandate for a migrated-but-active policy so
  // DPO stops deducting alongside RealPay. Does NOT cancel the policy.
  const cancelDpoContract = async () => {
    if (!window.confirm('Cancel the active DPO mandate for this policy? DPO will stop deducting. This does not cancel the policy.')) return
    setCancellingDpo(true)
    try {
      const res = await cancelPolicyDpoContract(policyId)
      await refetch()
      window.alert(res?.message || 'DPO contract cancelled.')
    } catch (e: any) {
      window.alert(e?.response?.data?.message || e?.response?.data?.error || 'Failed to cancel DPO contract.')
    } finally { setCancellingDpo(false) }
  }
  const addSchedule = async () => {
    if (!addForm.billing_date || !addForm.premium) return alert('Billing date and premium required.')
    const res = await post(`/policies/${policyId}/schedule-transactions`, {
      billing_date: addForm.billing_date,
      premium: Number(addForm.premium),
      installment: addForm.installment ? Number(addForm.installment) : undefined,
    })
    if (res) { setShowAdd(false); setAddForm({ billing_date: '', premium: '', installment: '' }) }
  }
  const updateBillingDay = async () => {
    if (!billingDay) return alert('Please select a billing day.')
    if (!window.confirm(`Recalculate billing dates for ALL pending scheduled transactions from day ${billingDay}?`)) return
    const res = await post(`/policies/${policyId}/schedule-transactions/billing-date`, { billing_day: Number(billingDay) }, 'put')
    if (res) { setShowBillingDay(false); setBillingDay('') }
  }
  const saveEdit = async () => {
    if (!editRow) return
    const path = editRow.field === 'billing_date' ? 'billing-date' : 'premium'
    const body = editRow.field === 'billing_date' ? { billing_date: editRow.value } : { premium: Number(editRow.value) }
    const res = await post(`/policies/${policyId}/schedule-transactions/${editRow.id}/${path}`, body, 'put')
    if (res) setEditRow(null)
  }

  // A row is cancellable unless it has already settled or been cancelled.
  // Cancelling is allowed even on a historical (moved-to-RealPay) schedule —
  // it only sets status=4 so the DPO cron stops attempting the charge; the
  // backend deliberately does not require a live DPO mandate for this.
  const isCancellable = (s: ScheduleTransaction) => s.status !== 'Successful' && s.status !== 'Cancelled'
  const hasCancellable = (schedules ?? []).some(isCancellable)

  // Add / edit stay editable-only (they'd mutate the schedule). Cancel All is
  // offered whenever pending charges exist, regardless of editable state.
  const toolbar = (editable || hasCancellable) ? (
    <div className="flex flex-wrap items-center gap-2">
      {editable && (
        <>
          <button onClick={() => setShowAdd(true)} disabled={!!busy}
            className="px-3 py-1 text-xs font-medium bg-primary text-white rounded hover:bg-primary disabled:opacity-50">+ Add Schedule</button>
          <button onClick={() => setShowBillingDay(true)} disabled={!!busy}
            className="px-3 py-1 text-xs font-medium bg-status-warning-fg text-white rounded hover:bg-status-warning-fg disabled:opacity-50">Update Billing Day</button>
        </>
      )}
      {hasCancellable && (
        <button onClick={cancelAll} disabled={!!busy}
          className="px-3 py-1 text-xs font-medium bg-status-danger-fg text-white rounded hover:bg-status-danger-fg disabled:opacity-50">Cancel All</button>
      )}
    </div>
  ) : null

  return (
    <Card title={<div className="flex items-center justify-between w-full"><span>Scheduled Transactions</span>{toolbar}</div>}>
      {!editable && (
        <div className="mb-3 flex items-start gap-2 rounded-md border border-status-warning-fg bg-status-warning-bg px-3 py-2 text-xs text-status-warning-fg">
          <svg className="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" /></svg>
          <div className="flex-1">
            <span>This customer has moved to <strong>{currentMethod || 'another payment method'}</strong>. The DPO schedule below is <strong>historical and read-only</strong>.</span>
            {/* GRA-0194 — a migrated policy can still carry a LIVE DPO mandate that keeps deducting
                (double-debit). Permitted staff can stop it here without cancelling the policy. */}
            {canCancelDpoContract && (
              <div className="mt-2">
                <button onClick={cancelDpoContract} disabled={cancellingDpo}
                  className="px-3 py-1 text-xs font-medium bg-status-danger-fg text-white rounded hover:bg-status-danger-fg disabled:opacity-50">
                  {cancellingDpo ? 'Cancelling…' : 'Cancel DPO Contract'}
                </button>
                <span className="ml-2 text-[11px]">Stops DPO from deducting for this policy. Does not cancel the policy.</span>
              </div>
            )}
          </div>
        </div>
      )}
      {showAdd && (
        <div className="mb-3 p-3 bg-status-info-bg border border-primary rounded flex flex-wrap items-end gap-2">
          <div><label className="block text-[10px] text-ink-muted">Billing Date</label>
            <input type="date" value={addForm.billing_date}
              onChange={e => setAddForm({ ...addForm, billing_date: e.target.value })}
              className="px-2 py-1 border border-line rounded text-sm" /></div>
          <div><label className="block text-[10px] text-ink-muted">Premium</label>
            <NumericInput value={addForm.premium}
              onChange={v => setAddForm({ ...addForm, premium: v })}
              className="px-2 py-1 border border-line rounded text-sm w-28" /></div>
          <div><label className="block text-[10px] text-ink-muted">Installment</label>
            <NumericInput value={addForm.installment}
              onChange={v => setAddForm({ ...addForm, installment: v })}
              placeholder="1" className="px-2 py-1 border border-line rounded text-sm w-20" hideHint /></div>
          <button onClick={addSchedule} disabled={!!busy}
            className="px-3 py-1 text-sm bg-primary text-white rounded hover:bg-primary">Save</button>
          <button onClick={() => setShowAdd(false)}
            className="px-3 py-1 text-sm border border-line rounded">Cancel</button>
        </div>
      )}
      {showBillingDay && (
        <div className="mb-3 p-3 bg-status-warning-bg border border-status-warning-fg rounded flex flex-wrap items-end gap-2">
          <div><label className="block text-[10px] text-ink-muted">Billing Day</label>
            <select value={billingDay} onChange={e => setBillingDay(e.target.value)}
              className="px-2 py-1 border border-line rounded text-sm">
              <option value="">Select billing day</option>
              {Array.from({ length: 28 }, (_, i) => i + 1).map(d => (
                <option key={d} value={d}>{d}</option>
              ))}
            </select></div>
          <button onClick={updateBillingDay} disabled={!!busy}
            className="px-3 py-1 text-sm bg-status-warning-fg text-white rounded hover:bg-status-warning-fg disabled:opacity-50">Update All Pending</button>
          <button onClick={() => { setShowBillingDay(false); setBillingDay('') }}
            className="px-3 py-1 text-sm border border-line rounded">Cancel</button>
          <span className="text-[11px] text-ink-muted">Recalculates dates for all pending installments — first on the selected day (this month if not yet passed, otherwise next month), then one month apart.</span>
        </div>
      )}
      {isLoading ? <ProgressBar isLoading label="Loading schedule" className="max-w-xs mx-auto py-8" />
        : !schedules || schedules.length === 0 ? <EmptyState message="No scheduled transactions." />
        : (
      <DualScrollTable>
        <table className="w-full text-sm">
          <thead className="bg-surface-2 text-ink-muted uppercase text-xs">
            <tr>
              <th className="px-4 py-2 text-right">#</th>
              <th className="px-4 py-2 text-left">Billing Date</th>
              <th className="px-4 py-2 text-right">Premium</th>
              <th className="px-4 py-2 text-left">Status</th>
              <th className="px-4 py-2 text-left">Payment Method</th>
              <th className="px-4 py-2 text-right">Retry Count</th>
              <th className="px-4 py-2 text-left">Reason</th>
              <th className="px-4 py-2 text-left">Created</th>
              <th className="px-4 py-2 text-center">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-line">
            {schedules.map((s: ScheduleTransaction) => {
              const isEditingDate = editRow?.id === s.id && editRow.field === 'billing_date'
              const isEditingPremium = editRow?.id === s.id && editRow.field === 'premium'
              const canEdit = editable && s.status !== 'Successful' && s.status !== 'Cancelled'
              const canCancel = isCancellable(s)
              return (
              <tr key={s.id} className="hover:bg-surface-2">
                <td className="px-4 py-2 text-right text-ink-faint">{s.installment ?? '—'}</td>
                <td className="px-4 py-2">
                  {isEditingDate ? (
                    <span className="flex items-center gap-1">
                      <input type="date" value={editRow!.value}
                        onChange={e => setEditRow({ ...editRow!, value: e.target.value })}
                        className="px-1 py-0.5 border border-line rounded text-xs" />
                      <button onClick={saveEdit} className="text-status-success-fg text-xs">✓</button>
                      <button onClick={() => setEditRow(null)} className="text-status-danger-fg text-xs">✕</button>
                    </span>
                  ) : fmtDate(s.billingDate)}
                </td>
                <td className="px-4 py-2 text-right">
                  {isEditingPremium ? (
                    <span className="flex items-center gap-1 justify-end">
                      <NumericInput value={editRow!.value}
                        onChange={v => setEditRow({ ...editRow!, value: v })}
                        className="px-1 py-0.5 border border-line rounded text-xs w-24 text-right"
                        hideHint />
                      <button onClick={saveEdit} className="text-status-success-fg text-xs">✓</button>
                      <button onClick={() => setEditRow(null)} className="text-status-danger-fg text-xs">✕</button>
                    </span>
                  ) : (s.amount ? fmtCurrency(s.amount) : '—')}
                </td>
                <td className="px-4 py-2">
                  {s.status ? (
                    <span className={`inline-flex px-2 py-0.5 rounded text-xs font-medium ${
                      s.status === 'Successful' ? 'bg-status-success-bg text-status-success-fg' :
                      s.status === 'Failed' ? 'bg-status-danger-bg text-status-danger-fg' :
                      s.status === 'In Progress' ? 'bg-status-info-bg text-primary' :
                      s.status === 'Cancelled' ? 'bg-surface-2 text-ink-muted' :
                      'bg-status-warning-bg text-status-warning-fg'
                    }`}>
                      {s.status}
                    </span>
                  ) : '—'}
                </td>
                <td className="px-4 py-2">{s.paymentMethod || '—'}</td>
                <td className="px-4 py-2 text-right">{s.retryCount ?? '—'}</td>
                <td className="px-4 py-2 text-xs text-ink-muted max-w-[200px] truncate" title={s.reason || ''}>{s.reason || '—'}</td>
                <td className="px-4 py-2 text-ink-muted">{fmtDate(s.createdAt)}</td>
                <td className="px-4 py-2 text-center">
                  {(canEdit || canCancel) ? (
                    <div className="flex items-center gap-1 justify-center">
                      {canEdit && (
                        <>
                          <button title="Edit billing date"
                            onClick={() => setEditRow({ id: s.id, field: 'billing_date', value: s.billingDate?.slice(0, 10) || '' })}
                            className="text-primary text-xs hover:underline">Date</button>
                          <button title="Edit premium"
                            onClick={() => setEditRow({ id: s.id, field: 'premium', value: String(s.amount ?? '') })}
                            className="text-primary text-xs hover:underline">P</button>
                        </>
                      )}
                      {canCancel && (
                        <button title="Cancel this transaction" onClick={() => cancelRow(s.id)}
                          className="text-status-danger-fg text-xs hover:underline">Cancel</button>
                      )}
                    </div>
                  ) : '—'}
                </td>
              </tr>
            )})}
          </tbody>
        </table>
      </DualScrollTable>
        )}
    </Card>
  )
}

// ─── Tab: Collect Now (Pay Now) ─────────────────────────────────

function CollectNowTab({ policyId, policyNumber }: { policyId: number; policyNumber: string }) {
  const [collecting, setCollecting] = useState(false)
  const [result, setResult] = useState<{ success: boolean; message: string; method: string; reference: string | null } | null>(null)
  const [history, setHistory] = useState<CollectNowEvent[] | null>(null)
  const [loadingHistory, setLoadingHistory] = useState(false)
  // Proper in-page confirm dialog — replaces window.confirm() which
  // some operators dismissed thinking nothing had happened.
  const [showConfirm, setShowConfirm] = useState(false)

  // Outstanding premiums the operator can tick off for collection.
  const [outstanding, setOutstanding] = useState<CollectNowOutstandingResponse | null>(null)
  const [loadingOutstanding, setLoadingOutstanding] = useState(false)
  const [selectedIds, setSelectedIds] = useState<number[]>([])

  const loadHistory = async () => {
    setLoadingHistory(true)
    try {
      const resp = await fetchCollectNowHistory(policyId)
      setHistory(resp.data)
    } catch {
      setHistory([])
    } finally {
      setLoadingHistory(false)
    }
  }

  const loadOutstanding = async () => {
    setLoadingOutstanding(true)
    try {
      const resp = await fetchCollectNowOutstanding(policyId)
      setOutstanding(resp)
      // Never carry a stale tick over a reload — a premium that has since been
      // settled must not stay selected.
      setSelectedIds(prev => prev.filter(id => resp.data.some(r => r.id === id)))
    } catch {
      setOutstanding(null)
    } finally {
      setLoadingOutstanding(false)
    }
  }

  // Load history + outstanding premiums on mount
  useEffect(() => { loadHistory(); loadOutstanding() }, [policyId]) // eslint-disable-line react-hooks/exhaustive-deps

  const rows: OutstandingPremium[] = outstanding?.data ?? []
  const selectionSupported = outstanding?.policy.selectionSupported ?? false
  const selectedRows = rows.filter(r => selectedIds.includes(r.id))
  // Totals are derived from the ticked rows on every render, so the figure in
  // the footer and the figure in the confirm dialog can never disagree.
  const selectedCount = selectedRows.length
  const selectedTotal = Math.round(selectedRows.reduce((sum, r) => sum + Number(r.amount || 0), 0) * 100) / 100
  const allSelected = rows.length > 0 && selectedCount === rows.length

  const toggleRow = (id: number) =>
    setSelectedIds(prev => (prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]))
  const toggleAll = () => setSelectedIds(allSelected ? [] : rows.map(r => r.id))

  const canCollect = selectionSupported ? selectedCount > 0 : true

  const handleCollect = async () => {
    setShowConfirm(false)
    setCollecting(true)
    setResult(null)
    try {
      // Send the ids AND the total the operator just confirmed. The server
      // re-sums independently and refuses if the two disagree, so a stale tab
      // can never debit an amount nobody authorised.
      const res = selectionSupported
        ? await triggerCollectNow(policyId, {
            schedule_ids: selectedRows.map(r => r.id),
            expected_amount: selectedTotal,
            confirmed: true,
          })
        : await triggerCollectNow(policyId)
      setResult(res)
      if (res.success) {
        setSelectedIds([])
        await Promise.all([loadHistory(), loadOutstanding()])
      } else {
        // A refusal usually means the list moved under us — pull it fresh.
        await loadOutstanding()
      }
    } catch (err: any) {
      const msg = err?.response?.data?.message ?? 'Collection request failed. Please try again.'
      setResult({ success: false, message: msg, method: 'NONE', reference: null })
      await loadOutstanding()
    } finally {
      setCollecting(false)
    }
  }

  return (
    <div className="space-y-6">
      {/* Outstanding premiums — selection table */}
      <Card
        title={`Outstanding Premiums${outstanding ? ` (${rows.length})` : ''}`}
        actions={
          <button
            onClick={loadOutstanding}
            disabled={loadingOutstanding}
            className="px-3 py-1.5 text-xs font-medium border border-line rounded hover:bg-surface-2 disabled:opacity-50"
          >
            Refresh
          </button>
        }
      >
        {loadingOutstanding ? (
          <ProgressBar isLoading label="Loading outstanding premiums" className="max-w-xs mx-auto py-4" />
        ) : !outstanding ? (
          <EmptyState message="Could not load outstanding premiums." />
        ) : !selectionSupported ? (
          <div className="rounded-lg p-4 text-sm bg-status-info-bg text-primary">
            Selecting individual premiums is available on MIS policies only. Use the single-premium
            collection below for <span className="font-semibold">{policyNumber}</span>.
          </div>
        ) : rows.length === 0 ? (
          <EmptyState message="No outstanding premiums for this policy." />
        ) : (
          <>
            <p className="text-sm text-ink-muted mb-3">
              Tick the premiums to collect. They are taken as a{' '}
              <span className="font-semibold">single debit</span> for the combined amount, and each
              premium is marked off individually.
            </p>

            <DualScrollTable>
              <table className="min-w-full text-sm">
                <thead className="bg-surface-2 text-xs text-ink-muted uppercase">
                  <tr>
                    <th className="px-3 py-2 text-left w-10">
                      <input
                        type="checkbox"
                        checked={allSelected}
                        onChange={toggleAll}
                        aria-label="Select all outstanding premiums"
                        className="h-4 w-4 rounded border-line"
                      />
                    </th>
                    <th className="px-3 py-2 text-left">Installment</th>
                    <th className="px-3 py-2 text-left">Billing Date</th>
                    <th className="px-3 py-2 text-right">Amount</th>
                    <th className="px-3 py-2 text-left">Status</th>
                    <th className="px-3 py-2 text-left">Method</th>
                    <th className="px-3 py-2 text-right">Retries</th>
                    <th className="px-3 py-2 text-left">Reason</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-line">
                  {rows.map(row => {
                    const checked = selectedIds.includes(row.id)
                    return (
                      <tr
                        key={row.id}
                        className={`cursor-pointer ${checked ? 'bg-status-info-bg' : 'hover:bg-surface-2'}`}
                        onClick={() => toggleRow(row.id)}
                      >
                        <td className="px-3 py-2" onClick={e => e.stopPropagation()}>
                          <input
                            type="checkbox"
                            checked={checked}
                            onChange={() => toggleRow(row.id)}
                            aria-label={`Select premium ${row.installment ?? row.id}`}
                            className="h-4 w-4 rounded border-line"
                          />
                        </td>
                        <td className="px-3 py-2 font-medium">{row.installment ?? '—'}</td>
                        <td className="px-3 py-2 text-ink-muted">
                          {row.billingDate ? fmtDate(row.billingDate) : '—'}
                          {!row.isDue && (
                            <span className="ml-2 inline-flex px-2 py-0.5 rounded text-xs font-medium bg-status-warning-bg text-status-warning-fg">
                              not yet due
                            </span>
                          )}
                        </td>
                        <td className="px-3 py-2 text-right">{fmtCurrency(row.amount)}</td>
                        <td className="px-3 py-2">
                          <span className={`inline-flex px-2 py-0.5 rounded text-xs font-medium ${
                            row.statusCode === 3 ? 'bg-status-danger-bg text-status-danger-fg' :
                            row.statusCode === 1 ? 'bg-status-warning-bg text-status-warning-fg' :
                            'bg-surface-2 text-ink-muted'
                          }`}>
                            {row.status}
                          </span>
                        </td>
                        <td className="px-3 py-2 text-xs text-ink-muted">{row.paymentMethod || '—'}</td>
                        <td className="px-3 py-2 text-right text-xs text-ink-muted">{row.retryCount ?? 0}</td>
                        <td className="px-3 py-2 text-xs text-ink-muted max-w-[220px] truncate" title={row.reason || ''}>
                          {row.reason || '—'}
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
                <tfoot className="bg-surface-2 font-semibold">
                  <tr>
                    <td className="px-3 py-2" colSpan={3}>
                      {selectedCount} of {rows.length} premium{selectedCount === 1 ? '' : 's'} selected
                    </td>
                    <td className="px-3 py-2 text-right">{fmtCurrency(selectedTotal)}</td>
                    <td className="px-3 py-2 text-xs font-normal text-ink-muted" colSpan={4}>
                      total deduction
                    </td>
                  </tr>
                </tfoot>
              </table>
            </DualScrollTable>
          </>
        )}
      </Card>

      {/* Action card */}
      <Card title="Collect Premium Now">
        <div className="flex flex-col gap-4">
          <p className="text-sm text-ink-muted">
            {selectionSupported
              ? "This immediately debits the customer's saved payment method (DPO or RealPay) for the premiums selected above — a one-off collection outside the regular billing cycle."
              : "Click the button below to immediately debit this policy's premium via DPO or RealPay. This triggers a one-off payment collection outside the regular billing cycle."}
            {' '}Use this for customers who are behind on payments or as a management-requested collection.
          </p>

          <div className="flex items-center gap-4 flex-wrap">
            <button
              onClick={() => setShowConfirm(true)}
              disabled={collecting || !canCollect}
              className="inline-flex items-center gap-2 px-5 py-2.5 bg-primary hover:bg-primary disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold rounded-lg shadow transition"
            >
              {collecting ? (
                <>
                  <svg className="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                  </svg>
                  Collecting...
                </>
              ) : (
                <>⚡ Collect Now</>
              )}
            </button>

            {selectionSupported && (
              <span className="text-sm text-ink-muted">
                {selectedCount === 0
                  ? 'Select at least one outstanding premium above.'
                  : <>Will deduct <span className="font-semibold text-ink">{fmtCurrency(selectedTotal)}</span> across {selectedCount} premium{selectedCount === 1 ? '' : 's'}.</>}
              </span>
            )}
          </div>

          {/* In-page confirm modal — replaces native window.confirm */}
          {showConfirm && (
            <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto" onClick={() => setShowConfirm(false)}>
              <div className="bg-surface rounded-xl shadow-2xl w-full max-w-md mx-4 p-5 max-h-[85vh] overflow-y-auto" onClick={e => e.stopPropagation()}>
                <h2 className="text-lg font-bold mb-2">Confirm Collect Now</h2>
                <p className="text-sm text-ink-muted mb-4">
                  This will immediately debit the customer's account for policy{' '}
                  <span className="font-semibold text-ink">{policyNumber}</span>.
                </p>

                {selectionSupported && (
                  <>
                    {/* The two numbers the operator is authorising, stated plainly. */}
                    <div className="rounded-lg border border-line divide-y divide-line mb-4">
                      <div className="flex items-center justify-between px-4 py-3">
                        <span className="text-sm text-ink-muted">Premiums selected</span>
                        <span className="text-base font-bold text-ink">{selectedCount}</span>
                      </div>
                      <div className="flex items-center justify-between px-4 py-3">
                        <span className="text-sm text-ink-muted">Total deduction</span>
                        <span className="text-lg font-bold text-ink">{fmtCurrency(selectedTotal)}</span>
                      </div>
                    </div>

                    <ul className="text-xs text-ink-muted mb-4 space-y-1 max-h-40 overflow-y-auto">
                      {selectedRows.map(r => (
                        <li key={r.id} className="flex justify-between gap-3">
                          <span>
                            Installment {r.installment ?? r.id}
                            {r.billingDate ? ` · ${fmtDate(r.billingDate)}` : ''}
                          </span>
                          <span className="font-medium text-ink">{fmtCurrency(r.amount)}</span>
                        </li>
                      ))}
                    </ul>
                  </>
                )}

                <p className="text-xs text-ink-muted mb-4">
                  {selectionSupported
                    ? 'The selected premiums will be taken as a single debit against the customer’s saved payment method (DPO or RealPay). Proceed only if the customer has authorised this deduction.'
                    : 'A one-off collection will be triggered outside the regular billing cycle. The customer’s saved payment method (DPO or RealPay) will be charged. Proceed only if the customer has authorised this collection.'}
                </p>
                <div className="flex justify-end gap-2">
                  <button onClick={() => setShowConfirm(false)} className="px-4 py-2 text-sm border border-line rounded-md">Cancel</button>
                  <button onClick={handleCollect} className="px-4 py-2 text-sm bg-primary text-white rounded-md hover:bg-primary">
                    {selectionSupported
                      ? `Yes, deduct ${fmtCurrency(selectedTotal)}`
                      : 'Yes, collect now'}
                  </button>
                </div>
              </div>
            </div>
          )}

          {/* Result banner */}
          {result && (
            <div className={`rounded-lg p-4 text-sm ${result.success ? 'bg-status-success-bg border border-status-success-fg text-status-success-fg' : 'bg-status-danger-bg border border-status-danger-fg text-status-danger-fg'}`}>
              <div className="font-semibold mb-1">{result.success ? '✅ Collection Successful' : '❌ Collection Failed'}</div>
              <div>{result.message}</div>
              {result.success && result.method !== 'NONE' && (
                <div className="mt-1 text-xs text-ink-muted">
                  Gateway: <span className="font-medium">{result.method}</span>
                  {result.reference && <> · Reference: <span className="font-medium">{result.reference}</span></>}
                </div>
              )}
            </div>
          )}
        </div>
      </Card>

      {/* History card */}
      <Card title="Collection History">
        {loadingHistory ? (
          <ProgressBar isLoading label="Loading history" className="max-w-xs mx-auto py-4" />
        ) : !history || history.length === 0 ? (
          <EmptyState message="No collection events yet." />
        ) : (
          <DualScrollTable>
            <table className="w-full text-sm">
              <thead className="bg-surface-2 text-ink-muted uppercase text-xs">
                <tr>
                  <th className="px-4 py-2 text-left">Date</th>
                  <th className="px-4 py-2 text-left">Gateway</th>
                  <th className="px-4 py-2 text-right">Premiums</th>
                  <th className="px-4 py-2 text-right">Amount</th>
                  <th className="px-4 py-2 text-left">Status</th>
                  <th className="px-4 py-2 text-left">Reference</th>
                  <th className="px-4 py-2 text-left">Triggered By</th>
                  <th className="px-4 py-2 text-left">Note</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {history.map((ev: CollectNowEvent) => (
                  <tr key={ev.id} className="hover:bg-surface-2">
                    <td className="px-4 py-2 text-ink-muted">{fmtDate(ev.created_at)}</td>
                    <td className="px-4 py-2">
                      <span className={`inline-flex px-2 py-0.5 rounded text-xs font-medium ${
                        ev.payment_method === 'DPO' ? 'bg-status-accent-bg text-status-accent-fg' : 'bg-status-info-bg text-primary'
                      }`}>
                        {ev.payment_method}
                      </span>
                    </td>
                    <td className="px-4 py-2 text-right text-xs text-ink-muted">{ev.premium_count ?? '—'}</td>
                    <td className="px-4 py-2 text-right">{fmtCurrency(ev.amount)}</td>
                    <td className="px-4 py-2">
                      <span className={`inline-flex px-2 py-0.5 rounded text-xs font-medium ${
                        ev.status === 'success' ? 'bg-status-success-bg text-status-success-fg' :
                        ev.status === 'failed'  ? 'bg-status-danger-bg text-status-danger-fg' :
                        'bg-status-warning-bg text-status-warning-fg'
                      }`}>
                        {ev.status}
                      </span>
                    </td>
                    <td className="px-4 py-2 text-xs text-ink-muted max-w-[160px] truncate" title={ev.gateway_reference || ''}>
                      {ev.gateway_reference || '—'}
                    </td>
                    <td className="px-4 py-2 text-xs text-ink-muted">{ev.triggered_by_name?.trim() || 'System'}</td>
                    <td className="px-4 py-2 text-xs text-status-danger-fg max-w-[200px] truncate" title={ev.failure_reason || ''}>
                      {ev.failure_reason || '—'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </DualScrollTable>
        )}
      </Card>
    </div>
  )
}

// ─── Tab: Terms (Motor Comprehensive) ───────────────────────────

function TermsTab({ policyId }: { policyId: number }) {
  const { data: terms, isLoading } = usePolicyTerms(policyId, true)

  if (isLoading) return <ProgressBar isLoading label="Loading terms" className="max-w-xs mx-auto py-8" />
  if (!terms || terms.length === 0) return <EmptyState message="No terms found for this policy." />

  return (
    <div className="space-y-4">
      {terms.map((t: PolicyTerm, i: number) => (
        <Card key={t.id} title={`Term ${t.termNumber ?? (i + 1)}`}>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-2">
            <InfoRow label="startDate" value={fmtDate(t.startDate)} />
            <InfoRow label="endDate" value={fmtDate(t.endDate)} />
            <InfoRow label="premium" value={t.premium != null ? fmtCurrency(t.premium) : null} />
            <InfoRow label="status" value={
              t.status != null ? (
                // policy_term.status is a string ('Active'/'Deactive') on most
                // rows, but numeric 1/0 on some — accept both.
                <StatusBadge status={
                  (t.status === 1 || t.status === '1' || String(t.status).toLowerCase() === 'active')
                    ? 'active' : 'in-active'
                } />
              ) : null
            } />
            <InfoRow label="createdAt" value={fmtDate(t.createdAt)} />
          </div>
        </Card>
      ))}
    </div>
  )
}

// ─── Tab: Notes (from main response) ────────────────────────────

function NotesTab({ policy }: { policy: Policy }) {
  return (
    <Card title="Policy Notes">
      {policy.note ? (
        <p className="text-sm text-ink-muted whitespace-pre-wrap">{policy.note}</p>
      ) : (
        <p className="text-sm text-ink-faint">No notes for this policy.</p>
      )}
    </Card>
  )
}

// ─── Tab: Assign Agent (old policy edit page parity) ─────────────
//
// Port of the old Graphite policy edit page's "Assign Agent" tab
// (admin/policy/edit.blade.php → admin.policy.agentUpdate): two
// live-searchable selects — agent (all active users) and store —
// that update policies.agent_id / policies.storeID.

function SearchableSelect({ label, options, value, onChange }: {
  label: string
  options: { id: number; name: string }[]
  value: number | null
  onChange: (id: number | null) => void
}) {
  const [search, setSearch] = useState('')
  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase()
    if (!q) return options
    return options.filter(o => o.name.toLowerCase().includes(q) || String(o.id).includes(q))
  }, [options, search])

  // Keep the current selection visible even when the search filters it out,
  // so the select never silently jumps to another option.
  const selected = value != null ? options.find(o => o.id === value) : undefined
  const list = selected && !filtered.some(o => o.id === selected.id) ? [selected, ...filtered] : filtered

  return (
    <div>
      <label className="block text-sm font-medium text-ink-muted mb-1">{label}</label>
      <input
        type="text"
        value={search}
        onChange={e => setSearch(e.target.value)}
        placeholder={`Search ${label.toLowerCase()}…`}
        className="w-full border border-line rounded-md px-3 py-2 text-sm mb-1 focus:outline-none focus:ring-1 focus:ring-primary"
      />
      <select
        value={value ?? ''}
        onChange={e => onChange(e.target.value === '' ? null : Number(e.target.value))}
        className="w-full border border-line rounded-md px-3 py-2 text-sm bg-surface focus:outline-none focus:ring-1 focus:ring-primary"
      >
        <option value="">— Select —</option>
        {list.map(o => (
          <option key={o.id} value={o.id}>{o.name} - {o.id}</option>
        ))}
      </select>
    </div>
  )
}

function AssignAgentTab({ policyId }: { policyId: number }) {
  const qc = useQueryClient()
  const { data, isLoading, error } = useQuery({
    queryKey: ['policy', policyId, 'assignAgent'],
    queryFn: () => fetchAssignAgentData(policyId),
  })

  const [agentId, setAgentId] = useState<number | null>(null)
  const [storeId, setStoreId] = useState<number | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null)

  // Preselect the policy's current agent/store once the data arrives.
  useEffect(() => {
    if (data) {
      setAgentId(data.agentId)
      setStoreId(data.storeId)
    }
  }, [data])

  const handleUpdate = async () => {
    setSubmitting(true)
    setMessage(null)
    try {
      await updateAssignAgent(policyId, { agent_id: agentId, store_id: storeId })
      setMessage({ type: 'success', text: 'Updated Successfully' })
      // Refresh the policy header (agent name / store name) and this tab.
      qc.invalidateQueries({ queryKey: ['policy', policyId] })
    } catch (e: any) {
      setMessage({ type: 'error', text: e?.response?.data?.error || e?.message || 'Something went wrong' })
    } finally {
      setSubmitting(false)
    }
  }

  if (isLoading) return <ProgressBar isLoading label="Loading agents" className="max-w-xs mx-auto py-8" />
  if (error || !data) return <EmptyState message="Couldn't load agents and stores. Please try again." />

  return (
    <Card title="Assign Agent">
      <div className="max-w-xl space-y-5">
        <SearchableSelect label="Agent" options={data.agents} value={agentId} onChange={setAgentId} />
        <SearchableSelect label="Store" options={data.stores} value={storeId} onChange={setStoreId} />
        {message && (
          <p className={`text-sm ${message.type === 'success' ? 'text-status-success-fg' : 'text-status-danger-fg'}`}>{message.text}</p>
        )}
        <div className="flex gap-2">
          <button
            onClick={handleUpdate}
            disabled={submitting}
            className="px-4 py-2 bg-primary text-white text-sm font-medium rounded-md hover:bg-primary disabled:opacity-50"
          >
            {submitting ? 'Updating…' : 'Update'}
          </button>
        </div>
      </div>
    </Card>
  )
}

// ─── Tab: Add Realpay Contract ──────────────────────────────────
//
// Mirrors graphiteBWV8 AddRealpayContract Livewire form. Prefills
// customer info (id type/number, email, cellphone) and billing
// fields from the policy. Payment frequency is read-only and
// derived from policy.premiumFrequency to match V8 behaviour.
//
// "Same as billing date" and "Same as premium" toggles control
// whether First Collection Date / First Instalment Amount inputs
// are shown — same conditional rule the V8 form enforced.

function AddRealpayContractTab({ policy, policyId }: { policy: Policy; policyId: number }) {
  const qc = useQueryClient()
  const perms = useMemo(() => {
    try { return JSON.parse(localStorage.getItem('user_permissions') || '[]') as string[] } catch { return [] }
  }, [])
  const canCreate = perms.length === 0 || perms.includes('realpay-Add Client')

  // ── Customer prefill (kyc/customer block on the Policy resource) ──
  const customer: any = (policy as any).customer || {}
  const banking: any = policy.banking || {}
  const kyc: any = (policy as any).kyc || {}
  const billingFrequency = ((policy as any).premiumFrequency || (policy as any).billing || 'monthly').toString().toLowerCase()

  const [form, setForm] = useState({
    id_type: (kyc.idType || customer.idType || 'omang') as 'omang' | 'passport',
    id_number: kyc.idNumber || customer.idNumber || '',
    email: customer.email || '',
    cellphone: customer.cellphone || customer.mobileNumber || '',
    payment_frequency: ['monthly', 'quarterly', 'annual'].includes(billingFrequency)
      ? (billingFrequency as 'monthly' | 'quarterly' | 'annual')
      : 'monthly',
    premium: (policy.premium ?? 0) as number,
    billing_date: ((policy as any).billingStartDate || new Date().toISOString().slice(0, 10)).slice(0, 10),
    is_first_collection_same: false,
    first_collection_date: '',
    is_first_instalment_same: false,
    first_instalment_amount: '' as string | number,
    bank_id: (banking?.bank_id ?? 0) as number,
    branch_id: (banking?.branch_id ?? 0) as number | string,
    // The policy API masks the stored account number (e.g. "****6557").
    // A masked value would fail the digits-only backend validation, so only
    // prefill when the value is genuinely all digits — otherwise leave it
    // blank and force the operator to re-enter the real account number.
    account_number: /^[0-9]+$/.test(banking?.accountNumber || '') ? banking.accountNumber : '',
    // V8 parity: 1 = Cheque, 2 = Savings (stored as int in
    // customer_banking.accountType). Default to '1' (Cheque) when the
    // existing row is missing or carries the legacy junk strings.
    account_type: (String(banking?.accountType ?? '') === '2' ? '2' : '1') as '1' | '2',
  })

  const { data: banks } = useRealpayBanks(canCreate)
  const { data: branches } = useRealpayBranches(form.bank_id || null)

  const [submitting, setSubmitting] = useState(false)
  const [msg, setMsg] = useState<{ kind: 'success' | 'error'; text: string } | null>(null)

  // Compliance / context warning shown above the form — same wording V8 uses
  const showComplianceWarning = !customer.email || !(customer.cellphone || customer.mobileNumber) || !(kyc.idType || customer.idType)

  function update<K extends keyof typeof form>(key: K, value: typeof form[K]) {
    setForm((p) => ({ ...p, [key]: value }))
  }

  async function submit(e: React.FormEvent) {
    e.preventDefault()
    setMsg(null)
    if (!canCreate) { setMsg({ kind: 'error', text: 'You do not have permission to create RealPay contracts.' }); return }
    setSubmitting(true)
    try {
      const payload = {
        id_type: form.id_type,
        id_number: form.id_number.trim(),
        email: form.email.trim(),
        cellphone: form.cellphone.trim(),
        payment_frequency: form.payment_frequency,
        premium: Number(form.premium),
        billing_date: form.billing_date,
        is_first_collection_same: form.is_first_collection_same,
        first_collection_date: form.is_first_collection_same ? null : (form.first_collection_date || null),
        is_first_instalment_same: form.is_first_instalment_same,
        first_instalment_amount: form.is_first_instalment_same ? null : (form.first_instalment_amount ? Number(form.first_instalment_amount) : null),
        bank_id: Number(form.bank_id),
        branch_id: form.branch_id,
        account_number: form.account_number.trim(),
        account_type: form.account_type,
      }
      const result = await createPolicyRealpayContract(policyId, payload as any)
      // Diagnostic surfacing — V8 parity: the API is always called against
      // whichever RealPay credentials are configured in this environment's
      // .env (sandbox in dev, production in live). The response tells us
      // whether the upstream accepted it.
      const d: any = result.data || {}
      const ref = d.contractNumber ? ` (Contract: ${d.contractNumber})` : ''
      let badge = ''
      let kind: 'success' | 'error' = 'success'
      if (d.realpayOk === true) {
        badge = ' [✓ sent to RealPay]'
      } else {
        const why = d.realpayError || 'unknown'
        const status = d.realpayStatus ? ` status=${d.realpayStatus}` : ''
        badge = ` [✗ RealPay call FAILED: ${why}${status}]`
        kind = 'error'
      }
      setMsg({ kind, text: result.message + ref + badge })
      // Also dump the full response (including realpayDebug) to the console
      // so we can grep it without DevTools Network-tab gymnastics.
      // eslint-disable-next-line no-console
      console.log('[Add Realpay Contract] response:', result)
      qc.invalidateQueries({ queryKey: ['policy', policyId, 'realpay'] })
      qc.invalidateQueries({ queryKey: ['policy', policyId] })
    } catch (err: any) {
      const apiErr = err?.response?.data
      // Loud, structured dump — press F12 → Console and you'll see every
      // detail of the failed request. Helps debug Cloudflare 502s, CORS
      // failures, validation errors, RealPay upstream rejections.
      /* eslint-disable no-console */
      console.group('%c[Add Realpay Contract] FAILED', 'color:#dc2626;font-weight:bold')
      console.log('axios message :', err?.message)
      console.log('axios code    :', err?.code)
      console.log('HTTP status   :', err?.response?.status, err?.response?.statusText)
      console.log('Request URL   :', err?.config?.baseURL + (err?.config?.url || ''))
      console.log('Request method:', err?.config?.method?.toUpperCase())
      console.log('Request body  :', err?.config?.data ? JSON.parse(err.config.data) : null)
      console.log('Response data :', apiErr)
      console.log('realpayDebug  :', apiErr?.data?.realpayDebug)
      console.log('FULL error obj:', err)
      console.groupEnd()
      /* eslint-enable no-console */

      // Build a user-facing banner that includes the most actionable error.
      let text = apiErr?.message || err?.message || 'Failed to create contract.'
      if (apiErr?.errors && typeof apiErr.errors === 'object') {
        const fieldErrs = Object.values(apiErr.errors).flat().filter(Boolean)
        if (fieldErrs.length) text = fieldErrs.join(' ')
      }
      // "Network Error" with no response object → Cloudflare/proxy returned
      // a CORS-less error page before our backend could respond.
      if (!err?.response && /network error/i.test(String(err?.message))) {
        text = 'Network/Gateway error — check browser console (F12) for full details. The contract was NOT created.'
      }
      setMsg({ kind: 'error', text })
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <form onSubmit={submit} className="space-y-6">
      {showComplianceWarning && (
        <div className="rounded-md bg-status-warning-bg border border-status-warning-fg px-4 py-3 text-sm text-status-warning-fg">
          For creating a contract, <b>ID Type and Number</b>, <b>Email</b>, and <b>Cellphone</b> are mandatory.
          If they are missing, please add them from the <b>Customer</b> tab before proceeding.
        </div>
      )}

      <Card title="Customer Information">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <ReadOnlyField label="Policy Number" value={policy.policyNumber} />
          <ReadOnlyField label="Customer Name" value={(customer.fullName || `${customer.firstName ?? ''} ${customer.lastName ?? ''}`.trim()) || '—'} />
          <SelectField
            label="ID Type *"
            value={form.id_type}
            onChange={(v) => update('id_type', v as any)}
            options={[{ value: 'omang', label: 'Omang' }, { value: 'passport', label: 'Passport' }]}
          />
          <TextField label="ID Number *" value={form.id_number} onChange={(v) => update('id_number', v)} required />
          <TextField label="Email *" type="email" value={form.email} onChange={(v) => update('email', v)} required />
          <TextField label="Cellphone *" value={form.cellphone} onChange={(v) => update('cellphone', v)} required />
        </div>
      </Card>

      <Card title="Contract Details">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <SelectField
            label="Payment Frequency *"
            value={form.payment_frequency}
            onChange={(v) => update('payment_frequency', v as any)}
            options={[
              { value: 'monthly', label: 'Monthly' },
              { value: 'quarterly', label: 'Quarterly' },
              { value: 'annual', label: 'Annual' },
            ]}
          />
          <TextField label="Premium (P) *" type="number" value={String(form.premium)} onChange={(v) => update('premium', Number(v) as any)} required />
          <TextField label="Billing Date *" type="date" value={form.billing_date} onChange={(v) => update('billing_date', v)} required />
          <div />

          <BoolToggle
            label="Is your first collection date the same as the billing date?"
            value={form.is_first_collection_same}
            onChange={(v) => update('is_first_collection_same', v)}
          />
          <BoolToggle
            label="Is your first instalment premium the same as the premium?"
            value={form.is_first_instalment_same}
            onChange={(v) => update('is_first_instalment_same', v)}
          />

          {!form.is_first_collection_same && (
            <TextField label="First Collection Date *" type="date" value={form.first_collection_date} onChange={(v) => update('first_collection_date', v)} required />
          )}
          {!form.is_first_instalment_same && (
            <TextField label="First Instalment Amount (P) *" type="number" value={String(form.first_instalment_amount)} onChange={(v) => update('first_instalment_amount', v as any)} required />
          )}
        </div>
      </Card>

      <Card title="Banking Information">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <SelectField
            label="Bank *"
            value={String(form.bank_id || '')}
            onChange={(v) => { update('bank_id', Number(v) as any); update('branch_id', '' as any) }}
            options={[{ value: '', label: 'Select Bank' }, ...((banks || []).map((b: any) => ({ value: String(b.bank_number), label: b.bank_name })))]}
          />
          <SelectField
            label="Branch *"
            value={String(form.branch_id || '')}
            onChange={(v) => update('branch_id', v as any)}
            disabled={!form.bank_id}
            options={[
              { value: '', label: form.bank_id ? 'Select Branch' : 'Please select a bank first' },
              ...((branches || []).map((b: any) => ({ value: String(b.branch_id), label: b.name }))),
            ]}
          />
          <TextField label="Account Number *" value={form.account_number} onChange={(v) => update('account_number', v.replace(/\D/g, ''))} required />
          <SelectField
            label="Account Type *"
            value={form.account_type}
            onChange={(v) => update('account_type', v as any)}
            options={[
              { value: '1', label: 'Cheque' },
              { value: '2', label: 'Savings' },
            ]}
          />
        </div>
      </Card>

      {msg && (
        <div className={`rounded-md px-4 py-3 text-sm border ${msg.kind === 'success' ? 'bg-status-success-bg border-status-success-fg text-status-success-fg' : 'bg-status-danger-bg border-status-danger-fg text-status-danger-fg'}`}>
          {msg.text}
        </div>
      )}

      <div className="rounded-md border border-status-danger-fg bg-status-danger-bg px-4 py-3 text-sm text-status-danger-fg">
        While creating a new contract, the system will cancel the existing contract and then create a new one.
      </div>

      <div className="flex justify-end gap-2">
        <button type="button" onClick={() => window.location.reload()} className="px-4 py-2 text-sm border border-line rounded hover:bg-surface-2">Cancel</button>
        <button
          type="submit"
          disabled={submitting || !canCreate}
          className="px-4 py-2 text-sm bg-primary text-white rounded hover:bg-primary disabled:opacity-50"
        >
          {submitting ? 'Submitting…' : 'Submit'}
        </button>
      </div>
    </form>
  )
}

// ─── Tab: Realpay Contract Lists ───────────────────────────────

function RealpayContractsTab({ policyId }: { policyId: number }) {
  const qc = useQueryClient()
  const { data, isLoading, error } = usePolicyRealpayContracts(policyId, true)
  const [cancellingId, setCancellingId] = useState<number | null>(null)
  const [syncing, setSyncing] = useState(false)
  const [syncMsg, setSyncMsg] = useState<{ kind: 'success' | 'info' | 'error'; text: string } | null>(null)

  // Check the LIVE RealPay portal for an existing contract on this policy and
  // sync it (contract + installments) into our DB. Useful when a contract was
  // created on the portal but never synced back (so the lists below are empty).
  async function handleSyncFromPortal() {
    setSyncing(true)
    setSyncMsg(null)
    try {
      const res = await syncPolicyRealpayFromPortal(policyId)
      await qc.invalidateQueries({ queryKey: ['policy', policyId, 'realpay', 'contracts'] })
      await qc.invalidateQueries({ queryKey: ['policy', policyId, 'realpay', 'installments'] })
      setSyncMsg(
        res.exists
          ? { kind: 'success', text: `Active contract found on RealPay — synced ${res.synced} contract${res.synced === 1 ? '' : 's'} with ${res.installments.length} installment${res.installments.length === 1 ? '' : 's'}.` }
          : res.contracts.length > 0
            ? { kind: 'info', text: 'No active contract on the RealPay portal right now — showing what is stored locally.' }
            : { kind: 'info', text: 'No RealPay contract found for this policy.' },
      )
    } catch (e: any) {
      setSyncMsg({ kind: 'error', text: e?.response?.data?.message || 'Could not reach RealPay to check the contract. Please try again.' })
    } finally {
      setSyncing(false)
    }
  }

  async function handleCancel(contractId: number, contractNumber: string) {
    if (!window.confirm(`Cancel Realpay contract ${contractNumber}? This will cancel it on RealPay and stop future debit orders. This cannot be undone.`)) return
    setCancellingId(contractId)
    try {
      const res = await cancelPolicyRealpayContract(policyId, contractId)
      await qc.invalidateQueries({ queryKey: ['policy', policyId, 'realpay', 'contracts'] })
      await qc.invalidateQueries({ queryKey: ['policy', policyId, 'realpay', 'installments'] })
      alert(res.message || 'Realpay contract cancelled successfully.')
    } catch (e: any) {
      alert(e?.response?.data?.message || 'Failed to cancel Realpay contract.')
    } finally {
      setCancellingId(null)
    }
  }

  const rows = data || []
  const syncBtn = (
    <button
      onClick={handleSyncFromPortal}
      disabled={syncing}
      className="px-3 py-1.5 text-xs font-medium text-primary border border-primary rounded-md hover:bg-status-info-bg transition disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap"
      title="Check the RealPay portal for an existing contract on this policy and fetch its installment schedule"
    >
      {syncing ? 'Checking RealPay…' : '↻ Check RealPay portal'}
    </button>
  )

  return (
    <Card title={`Realpay Contracts (${rows.length})`} actions={syncBtn}>
      {syncMsg && (
        <div className={`rounded-md px-3 py-2 text-sm border ${
          syncMsg.kind === 'success' ? 'bg-status-success-bg border-status-success-fg text-status-success-fg' :
          syncMsg.kind === 'error'   ? 'bg-status-danger-bg border-status-danger-fg text-status-danger-fg' :
                                       'bg-status-warning-bg border-status-warning-fg text-status-warning-fg'
        }`}>{syncMsg.text}</div>
      )}
      {isLoading ? (
        <div className="p-4 text-sm text-ink-muted">Loading contracts…</div>
      ) : error ? (
        <div className="p-4 text-sm text-status-danger-fg">Failed to load contracts.</div>
      ) : rows.length === 0 ? (
        <EmptyState message="No Realpay contracts have been created for this policy. Use “Check RealPay portal” to look for one created directly on RealPay." />
      ) : (
        <div className="overflow-x-auto">
          <table className="min-w-full text-sm">
            <thead className="bg-surface-2 text-xs text-ink-muted uppercase">
              <tr>
                <th className="px-3 py-2 text-left">ID</th>
                <th className="px-3 py-2 text-left">Client Number</th>
                <th className="px-3 py-2 text-left">Contract Number</th>
                <th className="px-3 py-2 text-left">Status</th>
                <th className="px-3 py-2 text-left">Created</th>
                <th className="px-3 py-2 text-right">Action</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((c: any) => (
                <tr key={c.id} className="border-t border-line">
                  <td className="px-3 py-2">{c.id}</td>
                  <td className="px-3 py-2">{c.clientNumber}</td>
                  <td className="px-3 py-2 font-mono text-xs">{c.contractNumber}</td>
                  <td className="px-3 py-2">
                    <span className={`px-2 py-0.5 rounded text-xs font-semibold ${
                      c.statusLabel === 'Active'    ? 'bg-status-info-bg text-primary' :
                      c.statusLabel === 'Cancelled' ? 'bg-status-danger-bg text-status-danger-fg' :
                                                      'bg-surface-2 text-ink-muted'
                    }`}>{c.statusLabel}</span>
                  </td>
                  <td className="px-3 py-2 text-ink-muted">{fmtDate(c.createdAt)}</td>
                  <td className="px-3 py-2 text-right">
                    {c.statusLabel === 'Active' && c.status === 1 ? (
                      <button
                        onClick={() => handleCancel(c.id, c.contractNumber)}
                        disabled={cancellingId !== null}
                        className="px-3 py-1 rounded text-xs font-semibold bg-status-danger-fg text-white hover:bg-status-danger-fg disabled:opacity-50 disabled:cursor-not-allowed"
                      >
                        {cancellingId === c.id ? 'Cancelling…' : 'Cancel Contract'}
                      </button>
                    ) : (
                      <span className="text-ink-faint text-xs">—</span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Card>
  )
}

// ─── Tab: Realpay Transactions (Installments) ──────────────────

function RealpayTransactionsTab({ policyId }: { policyId: number }) {
  const { data, isLoading, error, refetch } = usePolicyRealpayInstallments(policyId, true)
  const { data: contracts } = usePolicyRealpayContracts(policyId, true)

  // V8 permission gates: empty stored list = allow (admin fallback), matching
  // every other permission gate in this file. BE is the source of truth.
  const perms = useMemo(() => {
    try { return JSON.parse(localStorage.getItem('user_permissions') || '[]') as string[] } catch { return [] }
  }, [])
  const canAdd = perms.length === 0 || perms.includes('policy-add_new_realpay_installment_data')
  const canUpdate = perms.length === 0 || perms.includes('policy-update_realpay_installment_data')

  const [modal, setModal] = useState<null | 'add' | 'update' | 'updateAll'>(null)
  const [busy, setBusy] = useState(false)
  const [toast, setToast] = useState<{ kind: 'success' | 'error'; text: string } | null>(null)

  // Prefill client/contract numbers from the latest contract (V8 prefilled the
  // modals from the same source); fall back to the newest installment row.
  const latest = (contracts && contracts[0]) || null
  const fallbackRow = (data && data[0]) || null
  const prefillClient = latest?.clientNumber ?? fallbackRow?.clientNumber ?? ''
  const prefillContract = latest?.contractNumber ?? fallbackRow?.contractNumber ?? ''

  function flash(kind: 'success' | 'error', text: string) {
    setToast({ kind, text })
    window.setTimeout(() => setToast(null), 6000)
  }

  async function run(fn: () => Promise<{ message: string }>) {
    setBusy(true)
    try {
      const res = await fn()
      flash('success', res?.message || 'Done.')
      setModal(null)
      await refetch()
    } catch (e: any) {
      flash('error', e?.response?.data?.message || e?.response?.data?.error || 'Action failed. Please try again.')
    } finally {
      setBusy(false)
    }
  }

  if (isLoading) return <div className="p-4 text-sm text-ink-muted">Loading installments…</div>
  if (error) return <div className="p-4 text-sm text-status-danger-fg">Failed to load installments.</div>
  const rows = data || []

  const statusClass = (s: string | null): string => {
    switch (s) {
      case 'S': return 'bg-status-success-bg text-status-success-fg'
      case 'F': return 'bg-status-danger-bg text-status-danger-fg'
      case 'W':
      case 'R':
      case 'A':
      case 'I': return 'bg-status-info-bg text-primary'
      case 'E': return 'bg-status-warning-bg text-status-warning-fg'
      default:  return 'bg-surface-2 text-ink-muted'
    }
  }

  const btn = 'px-3 py-1.5 text-xs font-medium rounded-md bg-primary text-white hover:bg-primary disabled:opacity-50'

  return (
    <div className="space-y-4">
      {toast && (
        <div className={`text-sm px-3 py-2 rounded ${toast.kind === 'success' ? 'bg-status-success-bg text-status-success-fg' : 'bg-status-danger-bg text-status-danger-fg'}`}>{toast.text}</div>
      )}

      <Card
        title={`Realpay Installments (${rows.length})`}
        actions={
          <>
            {canAdd && <button onClick={() => setModal('add')} className={btn}>Add New Installment</button>}
            {canUpdate && <button onClick={() => setModal('update')} className={btn}>Update Installment</button>}
            {canUpdate && <button onClick={() => setModal('updateAll')} className={btn}>Update All Installment</button>}
          </>
        }
      >
        {rows.length === 0 ? (
          <EmptyState message="No installment activity yet. Once RealPay processes the first debit, rows will appear here." />
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead className="bg-surface-2 text-xs text-ink-muted uppercase">
                <tr>
                  <th className="px-3 py-2 text-left">Seq</th>
                  <th className="px-3 py-2 text-left">Reference</th>
                  <th className="px-3 py-2 text-right">CTC</th>
                  <th className="px-3 py-2 text-left">Action Date</th>
                  <th className="px-3 py-2 text-left">Tracking</th>
                  <th className="px-3 py-2 text-right">Amount</th>
                  <th className="px-3 py-2 text-left">Bank Response</th>
                  <th className="px-3 py-2 text-left">Status</th>
                  <th className="px-3 py-2 text-right">Retries</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((r: any) => (
                  <tr key={r.id} className="border-t border-line">
                    <td className="px-3 py-2">{r.installmentSequence}</td>
                    <td className="px-3 py-2 font-mono text-xs">{r.installmentReferenceNumber}</td>
                    <td className="px-3 py-2 text-right">{r.ctcAmount}</td>
                    <td className="px-3 py-2">{fmtDate(r.installmentActionDate)}</td>
                    <td className="px-3 py-2">{r.trackingCode}</td>
                    <td className="px-3 py-2 text-right">{fmtCurrency(r.installmentAmount)}</td>
                    <td className="px-3 py-2 text-xs text-ink-muted max-w-xs truncate" title={r.bankResponse ?? undefined}>{r.bankResponse || '—'}</td>
                    <td className="px-3 py-2">
                      <span className={`px-2 py-0.5 rounded text-xs font-semibold ${statusClass(r.installmentStatus)}`}>{r.installmentStatusLabel}</span>
                    </td>
                    <td className="px-3 py-2 text-right">{r.retryCount}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>

      <RealpayInstallmentModal
        modal={modal}
        busy={busy}
        onClose={() => setModal(null)}
        prefillClient={prefillClient}
        prefillContract={prefillContract}
        onAdd={(p) => run(() => addRealpayInstallment(policyId, p))}
        onUpdate={(p) => run(() => updateRealpayInstallment(policyId, p))}
        onUpdateAll={(p) => run(() => updateRealpayAllInstallments(policyId, p))}
      />
    </div>
  )
}

/** Modal for the three RealPay installment actions (V8 parity). */
function RealpayInstallmentModal({
  modal, busy, onClose, prefillClient, prefillContract,
  onAdd, onUpdate, onUpdateAll,
}: {
  modal: null | 'add' | 'update' | 'updateAll'
  busy: boolean
  onClose: () => void
  prefillClient: string
  prefillContract: string
  onAdd: (p: { client_number: string; contract_number: string; installment_date: string; installment_premium: string; contract_sequence?: string }) => void
  onUpdate: (p: { client_number: string; contract_number: string; installment_number: string; installment_date?: string; installment_premium?: string }) => void
  onUpdateAll: (p: { client_number: string; contract_number: string; installment_date?: string; installment_premium?: string }) => void
}) {
  const [clientNumber, setClientNumber] = useState('')
  const [contractNumber, setContractNumber] = useState('')
  const [installmentNumber, setInstallmentNumber] = useState('')
  const [installmentDate, setInstallmentDate] = useState('')
  const [installmentPremium, setInstallmentPremium] = useState('')

  useEffect(() => {
    if (modal) {
      setClientNumber(prefillClient)
      setContractNumber(prefillContract)
      setInstallmentNumber('')
      setInstallmentDate('')
      setInstallmentPremium('')
    }
  }, [modal, prefillClient, prefillContract])

  if (!modal) return null

  const title = modal === 'add' ? 'Add Realpay Installment'
    : modal === 'update' ? 'Update Realpay Installment'
    : 'Update Realpay All Installments'

  // V8 validation: Add requires date + premium; Update requires installment
  // number + (date OR premium); Update All requires date OR premium.
  const hasDateOrPremium = installmentDate.trim() !== '' || installmentPremium.trim() !== ''
  const valid =
    clientNumber.trim() !== '' && contractNumber.trim() !== '' && (
      modal === 'add' ? (installmentDate.trim() !== '' && installmentPremium.trim() !== '')
      : modal === 'update' ? (installmentNumber.trim() !== '' && hasDateOrPremium)
      : hasDateOrPremium
    )

  function submit() {
    if (modal === 'add') {
      onAdd({ client_number: clientNumber.trim(), contract_number: contractNumber.trim(), installment_date: installmentDate, installment_premium: installmentPremium })
    } else if (modal === 'update') {
      onUpdate({
        client_number: clientNumber.trim(), contract_number: contractNumber.trim(),
        installment_number: installmentNumber.trim(),
        installment_date: installmentDate || undefined, installment_premium: installmentPremium || undefined,
      })
    } else {
      onUpdateAll({
        client_number: clientNumber.trim(), contract_number: contractNumber.trim(),
        installment_date: installmentDate || undefined, installment_premium: installmentPremium || undefined,
      })
    }
  }

  const field = 'mt-1 w-full border border-line rounded px-2 py-1.5 text-sm'

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/50 p-4 pt-10" onClick={onClose}>
      <div className="bg-surface rounded-lg shadow-xl w-full max-w-md" onClick={(e) => e.stopPropagation()}>
        <div className="flex justify-between items-center px-4 py-3 border-b border-line sticky top-0 bg-surface z-10">
          <h3 className="text-sm font-semibold text-ink-muted">{title}</h3>
          <button onClick={onClose} className="text-ink-faint hover:text-ink-muted text-xl leading-none">&times;</button>
        </div>
        <div className="px-4 py-4 space-y-3 text-sm">
          <label className="block">
            <span className="text-ink-muted">Client Number</span>
            <input value={clientNumber} onChange={(e) => setClientNumber(e.target.value)} className={field} placeholder="Please enter client number" />
          </label>
          <label className="block">
            <span className="text-ink-muted">Contract Number</span>
            <input value={contractNumber} onChange={(e) => setContractNumber(e.target.value)} className={field} placeholder="Please enter contract number" />
          </label>
          {modal === 'update' && (
            <label className="block">
              <span className="text-ink-muted">Installment Number</span>
              <input value={installmentNumber} onChange={(e) => setInstallmentNumber(e.target.value)} className={field} placeholder="Please enter installment number" />
            </label>
          )}
          <label className="block">
            <span className="text-ink-muted">Installment Date</span>
            <input type="date" value={installmentDate} onChange={(e) => setInstallmentDate(e.target.value)} className={field} />
          </label>
          <label className="block">
            <span className="text-ink-muted">Installment Premium</span>
            <input type="number" step="0.01" min="0" value={installmentPremium} onChange={(e) => setInstallmentPremium(e.target.value)} className={field} placeholder="Please enter installment premium" />
          </label>
          {modal !== 'add' && (
            <p className="text-xs text-status-danger-fg">Note: After processing, installments will be updated within the hour.</p>
          )}
        </div>
        <div className="flex justify-end gap-2 px-4 py-3 border-t border-line">
          <button onClick={onClose} disabled={busy} className="px-4 py-1.5 text-sm rounded-md border border-line text-ink-muted hover:bg-surface-2">Close</button>
          <button
            onClick={submit}
            disabled={busy || !valid}
            className="px-4 py-1.5 text-sm font-medium rounded-md bg-primary text-white hover:bg-primary disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {busy ? 'Working…' : 'Submit'}
          </button>
        </div>
      </div>
    </div>
  )
}

// ─── Tab: Rerate Premium (MIS Motor Comprehensive, product 3) ──────────────
// React port of graphiteBWV8's rerate-premium flow. Two-step (V8 parity):
// "Recalculate" previews a PENDING rate; "Accept" commits it. Optional
// discount/surcharge + custom-rate adjust the previewed annual premium first.
// Payment capture on accept is handled by the existing Add Realpay Contract /
// Add Offline Payments tabs (V2's dedicated, tested payment endpoints).

const MARITAL_OPTIONS = [
  { code: 1, label: 'Single' }, { code: 2, label: 'Married' }, { code: 3, label: 'Divorced' },
  { code: 4, label: 'Widowed' }, { code: 5, label: 'Living Together' }, { code: 6, label: 'Living Separately' },
]

function maritalToCode(raw: string | null): number {
  if (raw == null || raw === '') return 1
  const n = Number(raw)
  if (Number.isFinite(n) && n >= 1 && n <= 6) return n
  const hit = MARITAL_OPTIONS.find(o => o.label.toLowerCase() === String(raw).toLowerCase())
  return hit ? hit.code : 1
}
function genderToCode(raw: string | null): number {
  if (raw == null) return 1
  const s = String(raw).toLowerCase()
  if (s === '0' || s === 'female') return 0
  return 1
}
function ReratePremiumTab({ policyId, policy }: { policyId: number; policy: Policy }) {
  void policy
  const [data, setData] = useState<RerateData | null>(null)
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)

  // Editable form
  const [gender, setGender] = useState(1)
  const [dob, setDob] = useState('')
  const [marital, setMarital] = useState(1)
  const [isImported, setIsImported] = useState<'Yes' | 'No'>('No')
  const [make, setMake] = useState('')
  const [model, setModel] = useState('')
  const [year, setYear] = useState<number | ''>('')
  const [estimatedValue, setEstimatedValue] = useState<number | ''>('')
  const [priorAccidents, setPriorAccidents] = useState(0)

  // Recalc preview
  const [rates, setRates] = useState<RerateRates | null>(null)
  const [recalcLoading, setRecalcLoading] = useState(false)
  const [recalcError, setRecalcError] = useState<string | null>(null)

  // Adjustment (discount/surcharge OR custom rate)
  const [adjusted, setAdjusted] = useState<{ monthly: string; three: string; annual: string } | null>(null)
  const [adjustError, setAdjustError] = useState<string | null>(null)
  const [adjustBusy, setAdjustBusy] = useState(false)
  const [dsType, setDsType] = useState<'discount' | 'surcharge'>('discount')
  const [dsValueType, setDsValueType] = useState<1 | 2>(2)
  const [dsValue, setDsValue] = useState<number | ''>('')
  const [dsReason, setDsReason] = useState('')
  const [crValueType, setCrValueType] = useState<1 | 2>(2)
  const [crValue, setCrValue] = useState<number | ''>('')
  const [crReason, setCrReason] = useState('')

  // Accept
  const [frequency, setFrequency] = useState<1 | 2 | 3>(3)
  const [updateRenewTerm, setUpdateRenewTerm] = useState(false)
  const [updateRenewalRates, setUpdateRenewalRates] = useState(false)
  const [acceptBusy, setAcceptBusy] = useState(false)
  const [acceptMsg, setAcceptMsg] = useState<string | null>(null)
  const [acceptError, setAcceptError] = useState<string | null>(null)

  // Vehicle variant (V8 parity — TruTrade variant under make/model/year)
  const [variant, setVariant] = useState('')
  const [variantOptions, setVariantOptions] = useState<string[]>([])

  // Payment capture on accept (V8 parity): one of none/realpay/dpo/cash.
  const [payMethod, setPayMethod] = useState<'none' | 'realpay' | 'dpo' | 'cash'>('none')
  const [rerateWithoutPayment, setRerateWithoutPayment] = useState(false)

  // RealPay form
  const [rpBillingDate, setRpBillingDate] = useState('')
  const [rpFirstCollection, setRpFirstCollection] = useState('')
  const [rpFirstInstalment, setRpFirstInstalment] = useState<number | ''>('')
  const [rpPremium, setRpPremium] = useState<number | ''>('')
  const [rpBankId, setRpBankId] = useState<string>('')
  const [rpBranchId, setRpBranchId] = useState<string>('')
  const [rpAccountType, setRpAccountType] = useState<'1' | '2'>('1')
  const [rpAccountNumber, setRpAccountNumber] = useState('')
  const [rpBanks, setRpBanks] = useState<{ id: number; bank_number: string; bank_name: string }[]>([])
  const [rpBranches, setRpBranches] = useState<{ branch_id: string | number; name: string }[]>([])

  // DPO form (display only — V2 has no admin DPO debit-order schedule endpoint)
  const [dpoBillingDate, setDpoBillingDate] = useState('')
  const [dpoFirstCollection, setDpoFirstCollection] = useState('')
  const [dpoFirstInstalment, setDpoFirstInstalment] = useState<number | ''>('')

  // Cash form
  const [cashDate, setCashDate] = useState('')
  const [cashAmount, setCashAmount] = useState<number | ''>('')
  const [cashReceipt, setCashReceipt] = useState('')
  const [cashReceivedBy, setCashReceivedBy] = useState('')
  const [cashInstalments, setCashInstalments] = useState<number | ''>('')
  const [cashNote, setCashNote] = useState('')
  const [cashProof, setCashProof] = useState<File | null>(null)

  // History
  const [history, setHistory] = useState<RerateHistoryRow[] | null>(null)
  const [historyOpen, setHistoryOpen] = useState(false)

  const load = () => {
    setLoading(true)
    fetchRerateData(policyId)
      .then((d) => {
        setData(d)
        setGender(genderToCode(d.customer.gender))
        setDob((d.customer.dob ?? '').slice(0, 10))
        setMarital(maritalToCode(d.customer.maritalstatus))
        setIsImported(d.vehicle.isImported === 'Yes' ? 'Yes' : 'No')
        setMake(d.vehicle.make ?? '')
        setModel(d.vehicle.model ?? '')
        setYear(d.vehicle.year ? Number(d.vehicle.year) : '')
        setEstimatedValue(d.vehicle.estimatedValue ? Number(d.vehicle.estimatedValue) : '')
        setPriorAccidents(d.vehicle.priorAccidents ?? 0)
      })
      .catch((e) => setLoadError(e?.response?.data?.message || 'Failed to load rerate data.'))
      .finally(() => setLoading(false))
  }
  useEffect(load, [policyId])

  // Cascading make/model dropdowns — same lookups the Create Wizard / Quote
  // edit use (vehicle/makes + vehicle/models: imported → local DB, non-imported
  // → TruTrade), mirroring graphiteBWV8's is_imported→make, make/year→model flow.
  const [makeOptions, setMakeOptions] = useState<string[]>([])
  const [modelOptions, setModelOptions] = useState<string[]>([])
  const [modelsBusy, setModelsBusy] = useState(false)

  useEffect(() => {
    if (!data) return
    apiClient.get<{ data: string[] }>('/vehicle/makes', { params: { is_imported: isImported } })
      .then(r => setMakeOptions(r.data.data ?? []))
      .catch(() => setMakeOptions([]))
  }, [isImported, data])

  useEffect(() => {
    if (!data) return
    if (!make) { setModelOptions([]); return }
    setModelsBusy(true)
    apiClient.get<{ data: string[] }>('/vehicle/models', { params: { is_imported: isImported, make, year: year || undefined } })
      .then(r => setModelOptions(r.data.data ?? []))
      .catch(() => setModelOptions([]))
      .finally(() => setModelsBusy(false))
  }, [isImported, make, year, data])

  // Keep the currently-saved make/model selectable even if the lookup omits it.
  const selectableMakes = useMemo(
    () => (make && !makeOptions.includes(make) ? [make, ...makeOptions] : makeOptions), [make, makeOptions])
  const selectableModels = useMemo(
    () => (model && !modelOptions.includes(model) ? [model, ...modelOptions] : modelOptions), [model, modelOptions])

  // Variant lookup (TruTrade make+model+year → variants), V8 parity.
  useEffect(() => {
    if (!data || !make || !model || !year) { setVariantOptions([]); return }
    apiClient.get<{ data: any }>('/vehicle/variants', { params: { make, model, year } })
      .then(r => {
        const v = r.data.data
        const list = Array.isArray(v) ? v.map((x: any) => (typeof x === 'string' ? x : (x?.Variant ?? x?.name ?? ''))).filter(Boolean) : []
        setVariantOptions(list)
      })
      .catch(() => setVariantOptions([]))
  }, [make, model, year, data])

  // RealPay banks (loaded lazily when the RealPay payment option is chosen).
  useEffect(() => {
    if (payMethod !== 'realpay' || rpBanks.length) return
    apiClient.get<{ data: { id: number; bank_number: string; bank_name: string }[] }>('/realpay/banks')
      .then(r => setRpBanks(r.data.data ?? []))
      .catch(() => setRpBanks([]))
  }, [payMethod, rpBanks.length])

  // RealPay branches cascade off the selected bank (bank_number).
  useEffect(() => {
    if (!rpBankId) { setRpBranches([]); return }
    apiClient.get<{ data: { branch_id: string | number; name: string }[] }>(`/realpay/banks/${rpBankId}/branches`)
      .then(r => setRpBranches(r.data.data ?? []))
      .catch(() => setRpBranches([]))
  }, [rpBankId])

  // The current "base" annual that adjustments operate on.
  const previewAnnual = adjusted ? Number(adjusted.annual) : (rates ? Number(rates.annual) : 0)

  async function onRecalculate() {
    setRecalcError(null)
    if (!make || !model || !year || !dob || !estimatedValue) {
      setRecalcError('Make, model, year, date of birth and estimated value are required.')
      return
    }
    setRecalcLoading(true)
    try {
      const r = await recalculateRerate(policyId, {
        make, model, year, dob,
        estimatedValue: Number(estimatedValue),
        is_imported: isImported, marital, prior_accidents: priorAccidents, gender,
      })
      setRates(r)
      setAdjusted(null)
      setAcceptMsg(null)
    } catch (e: any) {
      setRecalcError(e?.response?.data?.message || 'Recalculation failed.')
    } finally {
      setRecalcLoading(false)
    }
  }

  async function onApplyDiscountSurcharge() {
    setAdjustError(null)
    if (!dsValue || !dsReason) { setAdjustError('Value and reason are required.'); return }
    setAdjustBusy(true)
    try {
      const res = await apiApplyDiscountSurcharge(policyId, {
        type: dsType, value_type: dsValueType, value: Number(dsValue),
        reason: dsReason, annual_premium_rerate: previewAnnual,
      })
      setAdjusted({ monthly: res.monthly_premium, three: res.threeintsll_premium, annual: res.annualPremium })
    } catch (e: any) {
      setAdjustError(e?.response?.data?.message || 'Failed to apply discount/surcharge.')
    } finally {
      setAdjustBusy(false)
    }
  }

  async function onApplyCustomRate() {
    setAdjustError(null)
    if (!crValue || !crReason) { setAdjustError('Value and reason are required.'); return }
    setAdjustBusy(true)
    try {
      const res = await apiApplyCustomRate(policyId, {
        value_type: crValueType, value: Number(crValue),
        reason: crReason, annual_premium_rerate: previewAnnual,
      })
      setAdjusted({ monthly: res.monthly_premium, three: res.threeintsll_premium, annual: res.annualPremium })
    } catch (e: any) {
      setAdjustError(e?.response?.data?.message || 'Failed to apply custom rate.')
    } finally {
      setAdjustBusy(false)
    }
  }

  async function onAccept() {
    if (!rates || !data) return
    setAcceptError(null); setAcceptMsg(null); setAcceptBusy(true)

    const annual = adjusted ? Number(adjusted.annual) : Number(rates.annual)
    const monthly = adjusted ? Number(adjusted.monthly) : Number(rates.monthly)
    const three = adjusted ? Number(adjusted.three) : Number(rates.threeInstalment)
    const committedForFreq = frequency === 1 ? monthly : frequency === 2 ? three : annual
    const wantsPayment = !rerateWithoutPayment && payMethod !== 'none'

    try {
      // 1) Commit (or record-only) the rerate.
      const res = await acceptRerate(policyId, {
        // Backend validates rateID as `required|string` (the legacy Blade form
        // always posted it as a string). The preview API returns rate_id as a
        // number over JSON, so coerce it or the accept call 422s with
        // "The rate id must be a string."
        rateID: String(rates.rateId),
        frequency,
        annual_premium_rerate: Number(rates.annual),
        dis_sur_annual_premium: adjusted ? Number(adjusted.annual) : null,
        first_premium: wantsPayment ? committedForFreq : null,
        billingDay: payMethod === 'realpay' && rpBillingDate ? rpBillingDate : null,
        rerate_update_renew_term: updateRenewTerm,
        rerate_update_renewal_rates: updateRenewalRates,
        rerate_without_payment: rerateWithoutPayment,
      })

      // 2) Capture payment via the existing V2 endpoints (V8 parity).
      let payMsg = ''
      if (wantsPayment && payMethod === 'realpay') {
        const idNumber = data.customer.omang || data.customer.passport || ''
        await createPolicyRealpayContract(policyId, {
          id_type: data.customer.omang ? 'omang' : 'passport',
          id_number: idNumber,
          email: data.customer.email || '',
          cellphone: data.customer.cellphone || '',
          payment_frequency: frequency === 1 ? 'monthly' : frequency === 2 ? 'quarterly' : 'annual',
          premium: Number(rpPremium || committedForFreq),
          billing_date: rpBillingDate,
          is_first_collection_same: !rpFirstCollection,
          first_collection_date: rpFirstCollection || null,
          is_first_instalment_same: !rpFirstInstalment,
          first_instalment_amount: rpFirstInstalment ? Number(rpFirstInstalment) : null,
          bank_id: Number(rpBankId),
          branch_id: rpBranchId,
          account_number: rpAccountNumber.trim(),
          account_type: rpAccountType,
        } as any)
        payMsg = ' RealPay contract created.'
      } else if (wantsPayment && payMethod === 'cash') {
        await createOfflinePayment(policyId, {
          amount: Number(cashAmount || committedForFreq),
          payment_date: cashDate,
          receipt_number: cashReceipt.trim(),
          payment_received_by: cashReceivedBy.trim() || undefined,
          number_of_instalments_paid: cashInstalments !== '' ? Number(cashInstalments) : undefined,
          notes: cashNote.trim() || undefined,
        } as any, cashProof)
        payMsg = ' Cash payment recorded.'
      } else if (wantsPayment && payMethod === 'dpo') {
        // V2 has no admin-side DPO debit-order scheduling endpoint; the premium
        // is committed, but the DPO schedule must be set via the existing flow.
        payMsg = ' Note: DPO debit-order scheduling is not available from this screen — set it up via Collect Now / the DPO flow.'
      }

      setAcceptMsg((res.message || 'New premium accepted.') + payMsg)
      setRates(null); setAdjusted(null); setPayMethod('none'); setCashProof(null)
      load()
      if (historyOpen) openHistory()
    } catch (e: any) {
      setAcceptError(e?.response?.data?.message || 'Failed to accept the new premium / capture payment.')
    } finally {
      setAcceptBusy(false)
    }
  }

  function openHistory() {
    setHistoryOpen(true)
    fetchRerateHistory(policyId).then(setHistory).catch(() => setHistory([]))
  }

  if (loading) return <div className="p-6 text-sm text-ink-muted">Loading rerate details…</div>
  if (loadError) return <div className="p-6 text-sm text-status-danger-fg">{loadError}</div>
  if (!data) return null

  const committedPreview = adjusted
    ? { monthly: adjusted.monthly, three: adjusted.three, annual: adjusted.annual }
    : rates
      ? { monthly: rates.monthly, three: rates.threeInstalment, annual: rates.annual }
      : null
  const committedForFreqDisplay = committedPreview
    ? (frequency === 1 ? committedPreview.monthly : frequency === 2 ? committedPreview.three : committedPreview.annual) ?? ''
    : ''

  return (
    <div className="space-y-6">
      {!data.canEdit && (
        <p className="text-xs text-status-warning-fg">
          This policy has reached the maximum number of premium edits allowed. Rerating may be rejected on submit.
        </p>
      )}

      {/* Customer Details */}
      <Card
        title="Customer Details"
        actions={
          <button onClick={openHistory} type="button"
            className="px-3 py-1.5 text-xs font-medium rounded-md bg-primary text-white hover:bg-primary">
            Premium Update History
          </button>
        }
      >
        <InfoRow label="name" value={data.customer.name} />
        <InfoRow label="omang" value={data.customer.omang} />
        <InfoRow label="passport" value={data.customer.passport} />
        <InfoRow label="email" value={data.customer.email} />
        <InfoRow label="cellphone" value={data.customer.cellphone} />
        <InfoRow label="storeName" value={data.customer.storeName} />
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2">
          <label className="text-sm">
            <span className="block text-ink-muted mb-1">Gender</span>
            <select value={gender} onChange={e => setGender(Number(e.target.value))}
              className="w-full border border-line rounded-md px-2 py-1.5 text-sm">
              <option value={1}>Male</option>
              <option value={0}>Female</option>
            </select>
          </label>
          <label className="text-sm">
            <span className="block text-ink-muted mb-1">Date of Birth</span>
            <input type="date" value={dob} onChange={e => setDob(e.target.value)}
              className="w-full border border-line rounded-md px-2 py-1.5 text-sm" />
          </label>
          <label className="text-sm">
            <span className="block text-ink-muted mb-1">Marital Status</span>
            <select value={marital} onChange={e => setMarital(Number(e.target.value))}
              className="w-full border border-line rounded-md px-2 py-1.5 text-sm">
              {MARITAL_OPTIONS.map(o => <option key={o.code} value={o.code}>{o.label}</option>)}
            </select>
          </label>
        </div>
      </Card>

      {/* Product Details */}
      <Card title="Product Details">
        <InfoRow label="product" value={data.product.name} />
        <InfoRow label="productPlan" value={data.product.plan} />
        <InfoRow label="sumInsured" value={fmtCurrency(data.product.sumInsured)} />
      </Card>

      {/* Vehicle Details */}
      <Card title="Vehicle Details">
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
          <label className="text-sm">
            <span className="block text-ink-muted mb-1">Japanese Import</span>
            <select value={isImported}
              onChange={e => { setIsImported(e.target.value as 'Yes' | 'No'); setMake(''); setModel(''); setModelOptions([]) }}
              className="w-full border border-line rounded-md px-2 py-1.5 text-sm">
              <option value="No">No</option>
              <option value="Yes">Yes</option>
            </select>
          </label>
          <label className="text-sm">
            <span className="block text-ink-muted mb-1">Make</span>
            <select value={make} onChange={e => { setMake(e.target.value); setModel('') }}
              className="w-full border border-line rounded-md px-2 py-1.5 text-sm">
              <option value="">— Select make —</option>
              {selectableMakes.map(m => <option key={m} value={m}>{m}</option>)}
            </select>
          </label>
          <label className="text-sm">
            <span className="block text-ink-muted mb-1">Manufacturing Year</span>
            <select value={year} onChange={e => { setYear(e.target.value ? Number(e.target.value) : ''); setModel('') }}
              className="w-full border border-line rounded-md px-2 py-1.5 text-sm">
              <option value="">—</option>
              {data.options.years.map(y => <option key={y} value={y}>{y}</option>)}
            </select>
          </label>
          <label className="text-sm">
            <span className="block text-ink-muted mb-1">Model</span>
            <select value={model} onChange={e => { setModel(e.target.value); setVariant('') }} disabled={!make || modelsBusy}
              className="w-full border border-line rounded-md px-2 py-1.5 text-sm disabled:bg-surface-2">
              <option value="">{modelsBusy ? 'Loading models…' : (make ? '— Select model —' : '— Select make first —')}</option>
              {selectableModels.map(m => <option key={m} value={m}>{m}</option>)}
            </select>
          </label>
          <label className="text-sm">
            <span className="block text-ink-muted mb-1">Variant</span>
            <select value={variant} onChange={e => setVariant(e.target.value)} disabled={!model || !variantOptions.length}
              className="w-full border border-line rounded-md px-2 py-1.5 text-sm disabled:bg-surface-2">
              <option value="">Select Variant</option>
              {variantOptions.map(v => <option key={v} value={v}>{v}</option>)}
            </select>
          </label>
          <label className="text-sm">
            <span className="block text-ink-muted mb-1">Estimated Value (20,000–500,000)</span>
            <input type="number" min={20000} max={500000} value={estimatedValue}
              onChange={e => setEstimatedValue(e.target.value ? Number(e.target.value) : '')}
              className="w-full border border-line rounded-md px-2 py-1.5 text-sm" />
          </label>
          <label className="text-sm">
            <span className="block text-ink-muted mb-1">Number of Prior Accidents (0–3)</span>
            <input type="number" min={0} max={3} value={priorAccidents}
              onChange={e => setPriorAccidents(Number(e.target.value))}
              className="w-full border border-line rounded-md px-2 py-1.5 text-sm" />
          </label>
        </div>
        {/* Read-only attributes (V8 parity — fixed for the motor-comp rerate path). */}
        <div className="pt-2 space-y-2 border-t border-line mt-2">
          <InfoRow label="condition" value="Excellent" />
          <InfoRow label="mileage" value="Low" />
          <InfoRow label="purpose" value="Personal" />
        </div>
      </Card>

      {/* Current Premium Calculation Details */}
      <Card title="Premium Calculation Details">
        <InfoRow label="ratingsCalculationLogId" value={data.premium.ratingsId} />
        <InfoRow label="monthly" value={fmtCurrency(data.premium.monthly)} />
        <InfoRow label="threeInstalments" value={fmtCurrency(data.premium.threeInstalment)} />
        <InfoRow label="annual" value={fmtCurrency(data.premium.annual)} />
        <InfoRow label="discountSurcharge" value={data.premium.discountSurcharge != null && Number(data.premium.discountSurcharge) !== 0 ? fmtCurrency(data.premium.discountSurcharge) : '—'} />
        <InfoRow label="ratingsPremiumRate" value={data.premium.premiumRate != null ? `${Number(data.premium.premiumRate).toFixed(2)}%` : '—'} />
        <InfoRow label="premiumRateAfterDiscountSurcharge" value="—" />
        <InfoRow label="reason" value={data.premium.reason ?? '—'} />
        <div className="pt-3 flex items-center gap-3">
          <button onClick={onRecalculate} disabled={recalcLoading || !data.canEdit}
            type="button"
            className="px-6 py-2 text-sm font-medium rounded-md bg-primary text-white hover:bg-primary disabled:opacity-50 disabled:cursor-not-allowed">
            {recalcLoading ? 'Recalculating…' : 'Recalculate Premium'}
          </button>
          {recalcError && <span className="text-xs text-status-danger-fg">{recalcError}</span>}
        </div>
      </Card>

      {/* New Premium Rate (after recalculation) */}
      {rates && (
        <Card title="New Premium Rate">
          {acceptMsg && <p className="text-xs text-status-success-fg mb-1">{acceptMsg}</p>}
          <InfoRow label="ratingsCalculationLogId" value={rates.rateId} />
          <InfoRow label="monthly" value={fmtCurrency(committedPreview?.monthly)} />
          <InfoRow label="threeInstalments" value={fmtCurrency(committedPreview?.three)} />
          <InfoRow label="annual" value={fmtCurrency(committedPreview?.annual)} />
          <InfoRow label="ratingsPremiumRate" value={rates.premiumRate != null ? `${rates.premiumRate.toFixed(2)}%` : '—'} />
          {adjusted && <p className="text-xs text-primary">Adjusted premium applied (discount/surcharge or custom rate).</p>}

          {/* Discount / Surcharge */}
          <div className="mt-4 border-t border-line pt-3">
            <p className="text-sm font-semibold text-ink-muted mb-2">Add Discount / Surcharge</p>
            <div className="grid grid-cols-1 sm:grid-cols-4 gap-2">
              <select value={dsType} onChange={e => setDsType(e.target.value as 'discount' | 'surcharge')} className="border border-line rounded-md px-2 py-1.5 text-sm">
                <option value="discount">Discount</option>
                <option value="surcharge">Surcharge</option>
              </select>
              <select value={dsValueType} onChange={e => setDsValueType(Number(e.target.value) as 1 | 2)} className="border border-line rounded-md px-2 py-1.5 text-sm">
                <option value={2}>Percent (%)</option>
                <option value={1}>Flat value</option>
              </select>
              <input type="number" placeholder="Value" value={dsValue} onChange={e => setDsValue(e.target.value ? Number(e.target.value) : '')} className="border border-line rounded-md px-2 py-1.5 text-sm" />
              <input type="text" placeholder="Reason" value={dsReason} onChange={e => setDsReason(e.target.value)} className="border border-line rounded-md px-2 py-1.5 text-sm" />
            </div>
            <button onClick={onApplyDiscountSurcharge} disabled={adjustBusy} type="button"
              className="mt-2 px-4 py-1.5 text-xs font-medium rounded-md bg-ink text-white hover:bg-ink disabled:opacity-50">
              Apply Discount/Surcharge
            </button>
          </div>

          {/* Custom Rate */}
          <div className="mt-4 border-t border-line pt-3">
            <p className="text-sm font-semibold text-ink-muted mb-2">Apply Custom Rate</p>
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
              <select value={crValueType} onChange={e => setCrValueType(Number(e.target.value) as 1 | 2)} className="border border-line rounded-md px-2 py-1.5 text-sm">
                <option value={2}>Percent of Sum Insured (%)</option>
                <option value={1}>Flat annual value</option>
              </select>
              <input type="number" placeholder="Value" value={crValue} onChange={e => setCrValue(e.target.value ? Number(e.target.value) : '')} className="border border-line rounded-md px-2 py-1.5 text-sm" />
              <input type="text" placeholder="Reason" value={crReason} onChange={e => setCrReason(e.target.value)} className="border border-line rounded-md px-2 py-1.5 text-sm" />
            </div>
            <button onClick={onApplyCustomRate} disabled={adjustBusy} type="button"
              className="mt-2 px-4 py-1.5 text-xs font-medium rounded-md bg-ink text-white hover:bg-ink disabled:opacity-50">
              Apply Custom Rate
            </button>
          </div>
          {adjustError && <p className="text-xs text-status-danger-fg mt-2">{adjustError}</p>}

          {/* Frequency + Accept */}
          <div className="mt-4 border-t border-line pt-3 space-y-3">
            <label className="text-sm block max-w-xs">
              <span className="block text-ink-muted mb-1">Please select frequency</span>
              <select value={frequency} onChange={e => setFrequency(Number(e.target.value) as 1 | 2 | 3)}
                className="w-full border border-line rounded-md px-2 py-1.5 text-sm">
                <option value={1}>Monthly Installments</option>
                <option value={2}>Three Installments in a year</option>
                <option value={3}>Annual Installments</option>
              </select>
            </label>

            {/* Payment method — V8 parity (RealPay / DPO / Cash), mutually exclusive */}
            {!rerateWithoutPayment && (
              <div className="space-y-2">
                {([['realpay', 'Add payment on RealPay'], ['dpo', 'Add payment on DPO'], ['cash', 'Pay with Cash']] as const).map(([key, lbl]) => (
                  <label key={key} className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={payMethod === key}
                      onChange={e => setPayMethod(e.target.checked ? key : 'none')} />
                    <span className="text-primary font-medium">{lbl}</span>
                  </label>
                ))}
              </div>
            )}

            {/* RealPay form */}
            {!rerateWithoutPayment && payMethod === 'realpay' && (
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 border border-line rounded-md p-3 bg-surface-2">
                <label className="text-xs">Instalment Start Date
                  <input type="date" value={rpBillingDate} onChange={e => setRpBillingDate(e.target.value)} className="w-full border border-line rounded px-2 py-1.5 text-sm mt-1" />
                </label>
                <label className="text-xs">First Collection Date
                  <input type="date" value={rpFirstCollection} onChange={e => setRpFirstCollection(e.target.value)} className="w-full border border-line rounded px-2 py-1.5 text-sm mt-1" />
                </label>
                <label className="text-xs">First Instalment Amount
                  <input type="number" value={rpFirstInstalment} onChange={e => setRpFirstInstalment(e.target.value ? Number(e.target.value) : '')} className="w-full border border-line rounded px-2 py-1.5 text-sm mt-1" />
                </label>
                <label className="text-xs">Premium
                  <input type="number" value={rpPremium} placeholder={String(committedForFreqDisplay)} onChange={e => setRpPremium(e.target.value ? Number(e.target.value) : '')} className="w-full border border-line rounded px-2 py-1.5 text-sm mt-1" />
                </label>
                <label className="text-xs">Bank
                  <select value={rpBankId} onChange={e => { setRpBankId(e.target.value); setRpBranchId('') }} className="w-full border border-line rounded px-2 py-1.5 text-sm mt-1">
                    <option value="">— Select bank —</option>
                    {rpBanks.map(b => <option key={b.id} value={b.bank_number}>{b.bank_name}</option>)}
                  </select>
                </label>
                <label className="text-xs">Bank Branch
                  <select value={rpBranchId} onChange={e => setRpBranchId(e.target.value)} disabled={!rpBankId} className="w-full border border-line rounded px-2 py-1.5 text-sm mt-1 disabled:bg-surface-2">
                    <option value="">— Select branch —</option>
                    {rpBranches.map(br => <option key={br.branch_id} value={br.branch_id}>{br.name}</option>)}
                  </select>
                </label>
                <label className="text-xs">Account Type
                  <select value={rpAccountType} onChange={e => setRpAccountType(e.target.value as '1' | '2')} className="w-full border border-line rounded px-2 py-1.5 text-sm mt-1">
                    <option value="1">Cheque</option>
                    <option value="2">Savings</option>
                  </select>
                </label>
                <label className="text-xs">Account Number
                  <input type="text" value={rpAccountNumber} onChange={e => setRpAccountNumber(e.target.value)} className="w-full border border-line rounded px-2 py-1.5 text-sm mt-1" />
                </label>
                <p className="sm:col-span-2 text-[11px] text-ink-muted">First Collection Date should be earlier than the Instalment Start Date.</p>
              </div>
            )}

            {/* DPO form (no admin schedule endpoint in V2 — premium commits, schedule set elsewhere) */}
            {!rerateWithoutPayment && payMethod === 'dpo' && (
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 border border-line rounded-md p-3 bg-surface-2">
                <label className="text-xs">Instalment Start Date
                  <input type="date" value={dpoBillingDate} onChange={e => setDpoBillingDate(e.target.value)} className="w-full border border-line rounded px-2 py-1.5 text-sm mt-1" />
                </label>
                <label className="text-xs">First Collection Date
                  <input type="date" value={dpoFirstCollection} onChange={e => setDpoFirstCollection(e.target.value)} className="w-full border border-line rounded px-2 py-1.5 text-sm mt-1" />
                </label>
                <label className="text-xs">First Instalment Amount
                  <input type="number" value={dpoFirstInstalment} onChange={e => setDpoFirstInstalment(e.target.value ? Number(e.target.value) : '')} className="w-full border border-line rounded px-2 py-1.5 text-sm mt-1" />
                </label>
                <label className="text-xs">Premium
                  <input type="number" value={committedForFreqDisplay} readOnly className="w-full border border-line rounded px-2 py-1.5 text-sm mt-1 bg-surface-2" />
                </label>
                <p className="sm:col-span-2 text-[11px] text-status-warning-fg">
                  Note: V2 has no admin-side DPO debit-order scheduling endpoint. Accepting commits the premium; set up the DPO schedule via Collect Now / the DPO flow.
                </p>
              </div>
            )}

            {/* Cash form */}
            {!rerateWithoutPayment && payMethod === 'cash' && (
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 border border-line rounded-md p-3 bg-surface-2">
                <label className="text-xs">Date of Payment
                  <input type="date" value={cashDate} onChange={e => setCashDate(e.target.value)} className="w-full border border-line rounded px-2 py-1.5 text-sm mt-1" />
                </label>
                <label className="text-xs">Amount
                  <input type="number" value={cashAmount} placeholder={String(committedForFreqDisplay)} onChange={e => setCashAmount(e.target.value ? Number(e.target.value) : '')} className="w-full border border-line rounded px-2 py-1.5 text-sm mt-1" />
                </label>
                <label className="text-xs">Receipt Number
                  <input type="text" value={cashReceipt} onChange={e => setCashReceipt(e.target.value)} className="w-full border border-line rounded px-2 py-1.5 text-sm mt-1" />
                </label>
                <label className="text-xs">Payment Received By
                  <input type="text" value={cashReceivedBy} onChange={e => setCashReceivedBy(e.target.value)} className="w-full border border-line rounded px-2 py-1.5 text-sm mt-1" />
                </label>
                <label className="text-xs">Number of Instalments Paid
                  <input type="number" value={cashInstalments} onChange={e => setCashInstalments(e.target.value ? Number(e.target.value) : '')} className="w-full border border-line rounded px-2 py-1.5 text-sm mt-1" />
                </label>
                <label className="text-xs">Upload Payment Proof
                  <input type="file" accept="image/*,application/pdf" onChange={e => setCashProof(e.target.files?.[0] ?? null)} className="w-full text-xs mt-1" />
                </label>
                <label className="text-xs sm:col-span-2">Note
                  <textarea value={cashNote} onChange={e => setCashNote(e.target.value)} className="w-full border border-line rounded px-2 py-1.5 text-sm mt-1" rows={2} />
                </label>
              </div>
            )}

            <label className="flex items-center gap-2 text-sm">
              <input type="checkbox" checked={rerateWithoutPayment}
                onChange={e => { setRerateWithoutPayment(e.target.checked); if (e.target.checked) setPayMethod('none') }} />
              Rerate without changing premium and payment contract?
            </label>
            <label className="flex items-center gap-2 text-sm">
              <input type="checkbox" checked={updateRenewTerm} onChange={e => setUpdateRenewTerm(e.target.checked)} />
              Apply rates to current term?
            </label>
            {data.isRenewal === 0 && (
              <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" checked={updateRenewalRates} onChange={e => setUpdateRenewalRates(e.target.checked)} />
                Apply rates to renewal?
              </label>
            )}
            {payMethod === 'none' && !rerateWithoutPayment && (
              <p className="text-xs text-status-danger-fg">Note * : Please select a payment method to accept the above premium (or tick "Rerate without changing premium").</p>
            )}
            <div className="flex items-center gap-3">
              <button onClick={onAccept} disabled={acceptBusy || (payMethod === 'none' && !rerateWithoutPayment)} type="button"
                className="px-6 py-2 text-sm font-medium rounded-md bg-status-success-fg text-white hover:bg-status-success-fg disabled:opacity-50 disabled:cursor-not-allowed">
                {acceptBusy ? 'Accepting…' : 'Accept New Premium'}
              </button>
              {acceptError && <span className="text-xs text-status-danger-fg">{acceptError}</span>}
            </div>
          </div>
        </Card>
      )}

      {/* Premium Update History */}
      {historyOpen && (
        <Card title="Premium Update History">
          {history == null ? (
            <p className="text-sm text-ink-muted">Loading…</p>
          ) : history.length === 0 ? (
            <p className="text-sm text-ink-faint">No rerate history for this policy.</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="min-w-full text-xs">
                <thead>
                  <tr className="text-left text-ink-muted border-b border-line">
                    <th className="py-2 pr-3">Rate ID</th>
                    <th className="py-2 pr-3">Monthly</th>
                    <th className="py-2 pr-3">3 Inst.</th>
                    <th className="py-2 pr-3">Annual</th>
                    <th className="py-2 pr-3">Sum Assured</th>
                    <th className="py-2 pr-3">Status</th>
                    <th className="py-2 pr-3">Rerated By</th>
                    <th className="py-2 pr-3">Date</th>
                  </tr>
                </thead>
                <tbody>
                  {history.map(h => (
                    <tr key={h.id} className="border-b border-line last:border-0">
                      <td className="py-2 pr-3 font-medium text-ink-muted">{h.ratingsId}</td>
                      <td className="py-2 pr-3">{fmtCurrency(h.monthly)}</td>
                      <td className="py-2 pr-3">{fmtCurrency(h.threeInstalment)}</td>
                      <td className="py-2 pr-3">{fmtCurrency(h.annual)}</td>
                      <td className="py-2 pr-3">{fmtCurrency(h.sumAssured)}</td>
                      <td className="py-2 pr-3">{h.status}</td>
                      <td className="py-2 pr-3">{h.reratedBy ?? '—'}</td>
                      <td className="py-2 pr-3">{h.createdAt ? String(h.createdAt).slice(0, 10) : '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Card>
      )}
    </div>
  )
}

// ─── Tab: Add Offline Payments ─────────────────────────────────

function OfflinePaymentsTab({ policy, policyId }: { policy: Policy; policyId: number }) {
  const qc = useQueryClient()
  const perms = useMemo(() => {
    try { return JSON.parse(localStorage.getItem('user_permissions') || '[]') as string[] } catch { return [] }
  }, [])
  const canEdit = perms.length === 0 || perms.includes('offline-payments-edit')

  const [form, setForm] = useState({
    amount: '' as string | number,
    payment_date: new Date().toISOString().slice(0, 10),
    receipt_number: '',
    payment_received_by: '',
    number_of_instalments_paid: '' as string | number,
    notes: '',
  })
  const [proofFile, setProofFile] = useState<File | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [msg, setMsg] = useState<{ kind: 'success' | 'error'; text: string } | null>(null)

  function update<K extends keyof typeof form>(key: K, value: typeof form[K]) {
    setForm((p) => ({ ...p, [key]: value }))
  }

  async function submit(e: React.FormEvent) {
    e.preventDefault()
    setMsg(null)
    if (!canEdit) { setMsg({ kind: 'error', text: 'You do not have permission to add offline payments.' }); return }
    setSubmitting(true)
    try {
      const payload = {
        amount: Number(form.amount),
        payment_date: form.payment_date,
        receipt_number: form.receipt_number.trim(),
        notes: form.notes.trim() || undefined,
        payment_received_by: form.payment_received_by.trim() || undefined,
        number_of_instalments_paid: form.number_of_instalments_paid !== '' ? Number(form.number_of_instalments_paid) : undefined,
      }
      const result = await createOfflinePayment(policyId, payload as any, proofFile)
      // The payment is now posted to the ledger as it is recorded, rather than
      // waiting for the overnight cron — but posting is best-effort by design
      // (a ledger error must never lose a recorded payment), so say which
      // happened instead of implying the statement is always up to date.
      const ledgerStatus = result?.data?.ledgerStatus as string | undefined
      const ledgerNote = ledgerStatus === 'posted' ? ' Ledger and statement updated.'
        : ledgerStatus === 'already' ? ' It was already on the ledger.'
        : ledgerStatus === 'skipped' ? ' It will appear on the ledger after tonight\'s run.'
        : ''
      setMsg({ kind: 'success', text: result.message + ledgerNote })
      setForm({ amount: '', payment_date: new Date().toISOString().slice(0, 10), receipt_number: '', payment_received_by: '', number_of_instalments_paid: '', notes: '' })
      setProofFile(null)
      qc.invalidateQueries({ queryKey: ['policy', policyId, 'transaction-logs'] })
      qc.invalidateQueries({ queryKey: ['policy', policyId, 'transactions'] })
      // The money is on the ledger now, so every view built from it is stale:
      // Account View, Receivable View, Invoicing and the Balance Owing / Days in
      // Arrears header tiles (a separate query the ledger prefix does not cover).
      qc.invalidateQueries({ queryKey: ['policy', policyId, 'ledger'] })
      qc.invalidateQueries({ queryKey: ['policy', policyId, 'client-health'] })
    } catch (err: any) {
      const apiErr = err?.response?.data
      setMsg({ kind: 'error', text: apiErr?.message || err?.message || 'Failed to record payment.' })
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <form onSubmit={submit} className="space-y-6">
      <Card title="Add Offline Payment">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <ReadOnlyField label="Policy Number" value={policy.policyNumber} />
          <TextField label="Payment Date *" type="date" value={form.payment_date} onChange={(v) => update('payment_date', v)} required />
          <TextField label="Payment Amount (P) *" type="number" value={String(form.amount)} onChange={(v) => update('amount', v as any)} required />
          <TextField label="Receipt Number *" value={form.receipt_number} onChange={(v) => update('receipt_number', v)} required />
          <TextField label="Payment Received By" value={form.payment_received_by} onChange={(v) => update('payment_received_by', v)} />
          <TextField label="Number of Instalments Paid" type="number" value={String(form.number_of_instalments_paid)} onChange={(v) => update('number_of_instalments_paid', v as any)} />
          <div className="md:col-span-2">
            <label className="block text-xs font-medium text-ink-muted mb-1">Payment Note</label>
            <textarea
              value={form.notes}
              onChange={(e) => update('notes', e.target.value)}
              rows={3}
              className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none"
            />
          </div>
          <div className="md:col-span-2">
            <label className="block text-xs font-medium text-ink-muted mb-1">Payment Proof (image or PDF, max 5MB)</label>
            <input
              type="file"
              accept="image/jpeg,image/png,application/pdf"
              onChange={(e) => setProofFile(e.target.files?.[0] ?? null)}
              className="block text-sm"
            />
            {proofFile && <p className="mt-1 text-xs text-ink-muted">Selected: {proofFile.name} ({Math.round(proofFile.size / 1024)} KB)</p>}
          </div>
        </div>
      </Card>

      {msg && (
        <div className={`rounded-md px-4 py-3 text-sm border ${msg.kind === 'success' ? 'bg-status-success-bg border-status-success-fg text-status-success-fg' : 'bg-status-danger-bg border-status-danger-fg text-status-danger-fg'}`}>
          {msg.text}
        </div>
      )}

      <div className="flex justify-end gap-2">
        <button
          type="submit"
          disabled={submitting || !canEdit}
          className="px-4 py-2 text-sm bg-primary text-white rounded hover:bg-primary disabled:opacity-50"
        >
          {submitting ? 'Saving…' : 'Add Payment'}
        </button>
      </div>
    </form>
  )
}

// ─── Tab: Payment Conversions (GRA-0182) ───────────────────────
//
// Port of the graphiteBWV8 policy edit page's "Payment Update Contract"
// tab. Lists every time this policy's payment method was switched
// (e.g. DPO → RealPay): the agent who did it, the old + new payment
// method, whether the old contract was cancelled, and when. Reads
// update_contract via GET /policies/{id}/payment-conversions.

function PaymentConversionsTab({ policyId }: { policyId: number }) {
  const { data: rows = [], isLoading, error } = useQuery<PaymentConversionRow[]>({
    queryKey: ['policy', policyId, 'payment-conversions'],
    queryFn: () => fetchPolicyPaymentConversions(policyId),
  })

  if (isLoading) return <div className="p-4 text-sm text-ink-muted">Loading payment conversions…</div>
  if (error) return <div className="p-4 text-sm text-status-danger-fg">Failed to load payment conversions.</div>
  if (rows.length === 0) return <EmptyState message="This policy has never had its payment method converted." />

  return (
    <Card title="Payment Method Conversions">
      <div className="overflow-x-auto">
        <table className="min-w-full text-sm">
          <thead className="bg-surface-2 text-xs text-ink-muted uppercase">
            <tr>
              <th className="px-3 py-2 text-left">ID</th>
              <th className="px-3 py-2 text-left">Policy Number</th>
              <th className="px-3 py-2 text-left">Agent Name</th>
              <th className="px-3 py-2 text-left">Old Payment Method</th>
              <th className="px-3 py-2 text-left">New Payment Method</th>
              <th className="px-3 py-2 text-left">Old Contract Cancelled</th>
              <th className="px-3 py-2 text-left">Converted On</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.id} className="border-t border-line">
                <td className="px-3 py-2">{r.id}</td>
                <td className="px-3 py-2">{r.policyNumber}</td>
                <td className="px-3 py-2">{r.agentName || '—'}</td>
                <td className="px-3 py-2">{r.oldPaymentMethod || '—'}</td>
                <td className="px-3 py-2">{r.newPaymentMethod || '—'}</td>
                <td className="px-3 py-2">{r.oldContractCancel || '—'}</td>
                <td className="px-3 py-2">{fmtDate(r.createdAt)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </Card>
  )
}

// ─── Tab: Product Details (Alpha Transit Cover) ────────────────

/**
 * Alpha Transit Cover (product 25) — shows everything ATC stores for the
 * policy: the atc_shipments risk record (route, goods, parties, financials,
 * issue/payment info), the courier partner it's scoped under, and any
 * ATC-side claims. Read-only: corrections happen on the ATC platform and
 * re-sync via the webhook.
 */
function ProductDetailsTab({ policyId }: { policyId: number }) {
  const { data, isLoading, error } = useQuery<AtcPolicyDetail>({
    queryKey: ['policy', policyId, 'atc-product-details'],
    queryFn: () => getAtcPolicyShipment(policyId),
  })

  if (isLoading) return <div className="p-4 text-sm text-ink-muted">Loading product details…</div>
  if (error) return <div className="p-4 text-sm text-status-danger-fg">Failed to load product details.</div>

  const shipment = data?.shipment
  if (!shipment) {
    return <EmptyState message={data?.error ? 'Alpha Transit data is unavailable in this environment.' : 'No shipment record found for this policy.'} />
  }

  const courier = data?.courier
  const claims = data?.claims ?? []
  const money = (v: string | number | null | undefined) => (v === null || v === undefined || v === '' ? '—' : fmtPula(v))

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <Card title="Shipment">
          <InfoRow label="Policy Number" value={shipment.policy_number} />
          <InfoRow label="Channel" value={shipment.channel === 'start' ? 'Start (Direct)' : 'ATC Platform'} />
          <InfoRow label="ATC Policy ID" value={shipment.atc_policy_id ?? '—'} />
          <InfoRow label="Courier" value={courier ? `${courier.name} (${courier.company_code})` : shipment.company_code} />
          <InfoRow label="Courier Waybill" value={shipment.courier_waybill || '—'} />
          <InfoRow label="Service Type" value={shipment.service_type || '—'} />
          <InfoRow label="Cover Start" value={fmtDate(shipment.cover_start)} />
          <InfoRow label="Cover End" value={fmtDate(shipment.cover_end)} />
          <InfoRow label="Issued At" value={fmtDate(shipment.issued_at)} />
          <InfoRow label="Issued By" value={shipment.issued_by_name || shipment.issued_by_email || '—'} />
        </Card>

        <Card title="Route & Goods">
          <InfoRow label="From" value={`${shipment.from_town || '—'} (Zone ${shipment.from_zone})`} />
          <InfoRow label="To" value={`${shipment.to_town || '—'} (Zone ${shipment.to_zone})`} />
          <InfoRow label="Goods Category" value={shipment.goods_category === 'ELE' ? 'Electronics (ELE)' : shipment.goods_category === 'STD' ? 'Standard Goods (STD)' : shipment.goods_category} />
          <InfoRow label="Goods Description" value={shipment.goods_description || '—'} />
          <InfoRow label="Weight (kg)" value={shipment.weight_kg ?? '—'} />
        </Card>

        <Card title="Financials">
          <InfoRow label="Declared Value" value={money(shipment.declared_value)} />
          <InfoRow label="Sum Insured" value={money(shipment.sum_insured)} />
          <InfoRow label="Premium" value={money(shipment.premium)} />
          <InfoRow label="Excess" value={money(shipment.excess)} />
          <InfoRow label="Rate" value={shipment.rate ?? '—'} />
          <InfoRow label="Courier Fee" value={money(shipment.courier_fee)} />
          <InfoRow label="Currency" value={shipment.currency} />
          <InfoRow label="Payment Status" value={<StatusBadge status={shipment.payment_status} />} />
        </Card>

        <Card title="Sender & Receiver">
          <InfoRow label="Sender Name" value={shipment.sender_name} />
          <InfoRow label="Sender Phone" value={shipment.sender_phone || '—'} />
          <InfoRow label="Sender Email" value={shipment.sender_email || '—'} />
          <InfoRow label="Receiver Name" value={shipment.receiver_name || '—'} />
          <InfoRow label="Receiver Phone" value={shipment.receiver_phone || '—'} />
          <InfoRow label="Receiver Email" value={shipment.receiver_email || '—'} />
        </Card>
      </div>

      <Card title={`ATC Claims (${claims.length})`}>
        {claims.length === 0 ? (
          <div className="text-sm text-ink-faint py-2">No ATC claims recorded against this policy.</div>
        ) : (
          <DataTable
            columns={['Claim Number', 'Incident Type', 'Status', 'Claim Amount', 'Settled Amount', 'Claimant', 'Filed By', 'Filed At']}
            rows={claims.map((c) => [
              c.claim_number,
              c.incident_type || '—',
              <StatusBadge key={`s-${c.id}`} status={c.status} />,
              money(c.claim_amount),
              money(c.settled_amount),
              c.claimant_name || '—',
              c.filed_by_email || '—',
              fmtDate(c.filed_at),
            ])}
          />
        )}
      </Card>
    </div>
  )
}

// ─── Tab: Transaction Logs ─────────────────────────────────────

function TransactionLogsTab({ policyId }: { policyId: number }) {
  const qc = useQueryClient()
  const { data, isLoading, error } = usePolicyTransactionLogs(policyId, true)

  // Reverse modal state
  const [reverseTarget, setReverseTarget] = useState<{
    id: number
    referenceNumber: string | null
    amount: string | number
    mode: 'before' | 'after'
  } | null>(null)

  // Refund modal state
  const [showRefundModal, setShowRefundModal] = useState(false)

  // Client-side search (mirrors V8's table search box)
  const [search, setSearch] = useState('')

  if (isLoading) return <div className="p-4 text-sm text-ink-muted">Loading transactions…</div>
  if (error) return <div className="p-4 text-sm text-status-danger-fg">Failed to load transactions.</div>
  const rows: any[] = data?.data ?? []
  const summary = data?.summary
  const q = search.trim().toLowerCase()
  const filteredRows = q
    ? rows.filter((r) =>
        String(r.referenceNumber ?? '').toLowerCase().includes(q) ||
        String(r.paymentMethod ?? '').toLowerCase().includes(q) ||
        String(r.status ?? '').toLowerCase().includes(q) ||
        String(r.paymentReceivedBy ?? '').toLowerCase().includes(q) ||
        String(r.paymentLoggedByName ?? '').toLowerCase().includes(q) ||
        String(r.note ?? '').toLowerCase().includes(q) ||
        String(r.source ?? '').toLowerCase().includes(q) ||
        String(r.amount ?? '').toLowerCase().includes(q),
      )
    : rows

  async function openProof(txnId: number) {
    try {
      const r = await fetchTransactionProofUrl(policyId, txnId)
      window.open(r.url, '_blank', 'noopener')
    } catch (e: any) {
      alert(e?.response?.data?.message || 'Could not load proof.')
    }
  }

  function onMutationSuccess() {
    qc.invalidateQueries({ queryKey: ['policy', policyId, 'transaction-logs'] })
    qc.invalidateQueries({ queryKey: ['policy', policyId, 'ledger'] })
    qc.invalidateQueries({ queryKey: ['policy', policyId, 'transactions'] })
  }

  return (
    <>
      <Card
        title={`Transaction Logs (${rows.length})`}
        actions={
          canReversePayment() ? (
            <button
              onClick={() => setShowRefundModal(true)}
              className="px-3 py-1.5 text-xs font-medium text-white bg-status-danger-fg rounded hover:bg-status-danger-fg"
            >
              Refund Money
            </button>
          ) : undefined
        }
      >
        {/* Summary aggregates — V8 RefundMoney.blade.php parity */}
        {summary && (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-4">
            <SummaryStat label="Success Transaction Count" value={summary.successCount} />
            <SummaryStat label="Total Successful Transactions In Amount" value={fmtCurrency(summary.successAmount)} />
            <SummaryStat label="Failed Transaction Count" value={summary.failedCount} />
            <SummaryStat label="Total Failed Transactions In Amount" value={fmtCurrency(summary.failedAmount)} />
            <SummaryStat label="Total Refunded Transactions Count" value={summary.refundCount} />
            <SummaryStat label="Total Refund Transactions In Amount" value={fmtCurrency(summary.refundAmount)} />
            <SummaryStat label="Total Reversal Transactions Count" value={summary.reverseCount} />
            <SummaryStat label="Total Reversal Transactions In Amount" value={fmtCurrency(summary.reverseAmount)} />
            <SummaryStat label="Total Balance" value={fmtCurrency(summary.totalBalance)} highlight />
          </div>
        )}

        {/* Search */}
        <div className="mb-3">
          <input
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search"
            className="w-full sm:w-64 px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none"
          />
          {q && (
            <span className="ml-3 text-xs text-ink-muted">
              {filteredRows.length} of {rows.length} match
            </span>
          )}
        </div>

        {rows.length === 0 ? (
          <EmptyState message="No transactions logged for this policy." />
        ) : filteredRows.length === 0 ? (
          <EmptyState message={`No transactions match "${search}".`} />
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead className="bg-surface-2 text-xs text-ink-muted uppercase">
                <tr>
                  <th className="px-3 py-2 text-left">ID</th>
                  <th className="px-3 py-2 text-left">Policy Number</th>
                  <th className="px-3 py-2 text-left">Reference</th>
                  <th className="px-3 py-2 text-left">Method</th>
                  <th className="px-3 py-2 text-right">Amount</th>
                  <th className="px-3 py-2 text-left">Payment Date</th>
                  <th className="px-3 py-2 text-left">Settlement</th>
                  <th className="px-3 py-2 text-right">Instalments</th>
                  <th className="px-3 py-2 text-left">Status</th>
                  <th className="px-3 py-2 text-left">Received By</th>
                  <th className="px-3 py-2 text-left">Added By</th>
                  <th className="px-3 py-2 text-left">Note</th>
                  <th className="px-3 py-2 text-left">Actions</th>
                </tr>
              </thead>
              <tbody>
                {filteredRows.map((r: any) => (
                  // Ledger-sourced refunds carry policy_ledger ids, which can
                  // collide with payment_transactions ids — key on both.
                  <tr key={`${r.source ?? 'payment'}-${r.id}`} className="border-t border-line">
                    <td className="px-3 py-2">{r.id}</td>
                    <td className="px-3 py-2">{r.policyNumber}</td>
                    <td className="px-3 py-2 font-mono text-xs">
                      {r.referenceNumber || <span className="text-ink-muted">—</span>}
                      {(r.source === 'ledger' || r.source === 'ledger-archive') && (
                        <span
                          className="ml-2 px-1.5 py-0.5 rounded bg-surface-2 text-ink-muted text-[10px] uppercase tracking-wide align-middle"
                          title={r.source === 'ledger-archive'
                            ? 'Posted directly to the policy ledger and since archived — no payment transaction behind it'
                            : 'Posted directly to the policy ledger — no payment transaction behind it'}
                        >
                          {r.source === 'ledger-archive' ? 'Archived Ledger' : 'Ledger'}
                        </span>
                      )}
                    </td>
                    <td className="px-3 py-2">{r.paymentMethod || '—'}</td>
                    <td className="px-3 py-2 text-right">{fmtCurrency(r.amount)}</td>
                    <td className="px-3 py-2">{fmtDate(r.paymentDate)}</td>
                    <td className="px-3 py-2">{fmtDate(r.paymentSettlementDate)}</td>
                    <td className="px-3 py-2 text-right">{r.numberOfInstalmentsPaid ?? '—'}</td>
                    <td className="px-3 py-2">
                      <span className={`px-2 py-0.5 rounded text-xs font-semibold ${
                        r.status === 'SUCCESS' || r.status === 'Success' ? 'bg-status-success-bg text-status-success-fg' :
                        r.status === 'CANCELLED' ? 'bg-surface-2 text-ink-muted'   :
                        r.status === 'FAILED'    ? 'bg-status-danger-bg text-status-danger-fg'     :
                                                   'bg-status-info-bg text-primary'
                      }`}>{r.status}</span>
                    </td>
                    <td className="px-3 py-2">{r.paymentReceivedBy || '—'}</td>
                    <td className="px-3 py-2">{r.paymentLoggedByName || '—'}</td>
                    <td className="px-3 py-2 text-xs text-ink-muted max-w-xs truncate" title={r.note ?? undefined}>{r.note || '—'}</td>
                    <td className="px-3 py-2 whitespace-nowrap">
                      {r.paymentProofPath && (
                        <button onClick={() => openProof(r.id)} className="text-primary hover:underline text-xs mr-2" title="View proof">📎 Proof</button>
                      )}
                      {r.isReversed && <span className="text-xs text-status-danger-fg">Reversed</span>}
                      {!r.isReversed && r.isRefunded && <span className="text-xs text-status-danger-fg">Refunded</span>}
                      {!r.isReversed && !r.isRefunded && (r.canReverseBeforeLedger || r.canReverseAfterLedger) && (
                        <button
                          onClick={() => setReverseTarget({
                            id: r.id,
                            referenceNumber: r.referenceNumber,
                            amount: r.amount,
                            mode: r.canReverseAfterLedger ? 'after' : 'before',
                          })}
                          className={`text-xs ${r.canReverseAfterLedger ? 'text-status-danger-fg' : 'text-primary'} hover:underline`}
                          title={r.canReverseAfterLedger ? 'Reverse after ledger' : 'Reverse before ledger'}
                        >
                          ↩ Reverse
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>

      {reverseTarget && (
        <ReverseTransactionModal
          policyId={policyId}
          target={reverseTarget}
          onClose={() => setReverseTarget(null)}
          onSuccess={() => { setReverseTarget(null); onMutationSuccess() }}
        />
      )}
      {showRefundModal && (
        <RefundMoneyModal
          policyId={policyId}
          onClose={() => setShowRefundModal(false)}
          onSuccess={() => { setShowRefundModal(false); onMutationSuccess() }}
        />
      )}
    </>
  )
}

// ─── Modal: Reverse Transaction ────────────────────────────────

function ReverseTransactionModal({ policyId, target, onClose, onSuccess }: {
  policyId: number
  target: { id: number; referenceNumber: string | null; amount: string | number; mode: 'before' | 'after' }
  onClose: () => void
  onSuccess: () => void
}) {
  const [reversalDate, setReversalDate] = useState(new Date().toISOString().slice(0, 10))
  const [comments, setComments] = useState('')
  const [referenceNumber, setReferenceNumber] = useState(target.referenceNumber || '')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function submit(e: React.FormEvent) {
    e.preventDefault()
    setError(null)
    if (!comments.trim()) { setError('Comment is required.'); return }
    setSubmitting(true)
    try {
      await reversePolicyTransaction(policyId, target.id, {
        reversal_date: reversalDate,
        comments: comments.trim(),
        reference_number: referenceNumber.trim() || undefined,
      })
      onSuccess()
    } catch (e: any) {
      setError(e?.response?.data?.message || e?.message || 'Reverse failed.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/50 p-4 pt-10" onClick={(e) => { if (e.target === e.currentTarget) onClose() }}>
      <div className="bg-surface rounded-lg shadow-xl w-full max-w-lg max-h-[85vh] flex flex-col overflow-hidden">
        <div className="px-5 py-3 border-b border-line flex items-center justify-between shrink-0">
          <h3 className="text-sm font-semibold text-ink-muted">
            Reverse Transaction {target.mode === 'after' ? '(After Ledger)' : '(Before Ledger)'}
          </h3>
          <button onClick={onClose} className="text-ink-faint hover:text-ink-muted text-xl leading-none">×</button>
        </div>
        <form onSubmit={submit} className="px-5 py-4 space-y-4 overflow-y-auto grow min-h-0">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
            <div>
              <label className="block text-xs font-medium text-ink-muted mb-1">Transaction ID</label>
              <div className="px-3 py-2 border border-line rounded bg-surface-2 text-ink-muted">{target.id}</div>
            </div>
            <div>
              <label className="block text-xs font-medium text-ink-muted mb-1">Amount</label>
              <div className="px-3 py-2 border border-line rounded bg-surface-2 text-ink-muted">{fmtCurrency(target.amount)}</div>
            </div>
          </div>

          <TextField label="Reference Number" value={referenceNumber} onChange={setReferenceNumber} />
          <TextField label="Reversal Date *" type="date" value={reversalDate} onChange={setReversalDate} required />

          <div>
            <label className="block text-xs font-medium text-ink-muted mb-1">Comments *</label>
            <textarea
              value={comments}
              onChange={(e) => setComments(e.target.value)}
              rows={3}
              maxLength={500}
              required
              className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none"
              placeholder="Reason for the reversal"
            />
          </div>

          {error && <div className="rounded-md px-3 py-2 text-xs bg-status-danger-bg border border-status-danger-fg text-status-danger-fg">{error}</div>}

          <div className="rounded-md border border-status-warning-fg bg-status-warning-bg px-3 py-2 text-xs text-status-warning-fg">
            {target.mode === 'after'
              ? 'This payment has been posted to the ledger. Reversing will insert a Reverse Payment ledger entry and mark the original ledger row as Reversed.'
              : 'This payment is before-ledger. Reversing will flag the transaction but make no ledger changes.'}
          </div>

          <div className="flex justify-end gap-2">
            <button type="button" onClick={onClose} className="px-3 py-1.5 text-sm border border-line rounded hover:bg-surface-2">Cancel</button>
            <button type="submit" disabled={submitting} className="px-3 py-1.5 text-sm bg-status-danger-fg text-white rounded hover:bg-status-danger-fg disabled:opacity-50">
              {submitting ? 'Reversing…' : 'Confirm Reverse'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

// ─── Modal: Refund Money (V8 manual cash refund) ──────────────

function RefundMoneyModal({ policyId, onClose, onSuccess }: {
  policyId: number
  onClose: () => void
  onSuccess: () => void
}) {
  const [form, setForm] = useState({
    reference_number: '',
    date_of_refund: new Date().toISOString().slice(0, 10),
    amount: '' as string | number,
    reason: '',
    refunded_by: '',
  })
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  function update<K extends keyof typeof form>(key: K, value: typeof form[K]) {
    setForm((p) => ({ ...p, [key]: value }))
  }

  async function submit(e: React.FormEvent) {
    e.preventDefault()
    setError(null)
    setSubmitting(true)
    try {
      await refundPolicyMoney(policyId, {
        reference_number: form.reference_number.trim(),
        date_of_refund: form.date_of_refund,
        amount: Number(form.amount),
        reason: form.reason.trim(),
        refunded_by: form.refunded_by.trim(),
      })
      onSuccess()
    } catch (e: any) {
      setError(e?.response?.data?.message || e?.message || 'Refund failed.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/50 p-4 pt-10" onClick={(e) => { if (e.target === e.currentTarget) onClose() }}>
      <div className="bg-surface rounded-lg shadow-xl w-full max-w-lg max-h-[85vh] flex flex-col overflow-hidden">
        <div className="px-5 py-3 border-b border-line flex items-center justify-between shrink-0">
          <h3 className="text-sm font-semibold text-ink-muted">Refund Money</h3>
          <button onClick={onClose} className="text-ink-faint hover:text-ink-muted text-xl leading-none">×</button>
        </div>
        <form onSubmit={submit} className="px-5 py-4 space-y-3 overflow-y-auto grow min-h-0">
          <TextField label="Reference Number *" value={form.reference_number} onChange={(v) => update('reference_number', v)} required />
          <TextField label="Date of Refund *" type="date" value={form.date_of_refund} onChange={(v) => update('date_of_refund', v)} required />
          <TextField label="Amount (P) *" type="number" value={String(form.amount)} onChange={(v) => update('amount', v as any)} required />
          <div>
            <label className="block text-xs font-medium text-ink-muted mb-1">Reason *</label>
            <textarea
              value={form.reason}
              onChange={(e) => update('reason', e.target.value)}
              rows={3}
              maxLength={300}
              required
              className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none"
            />
          </div>
          <TextField label="Refunded By *" value={form.refunded_by} onChange={(v) => update('refunded_by', v)} required />

          {error && <div className="rounded-md px-3 py-2 text-xs bg-status-danger-bg border border-status-danger-fg text-status-danger-fg">{error}</div>}

          <div className="rounded-md border border-status-danger-fg bg-status-danger-bg px-3 py-2 text-xs text-status-danger-fg">
            This records a manual cash refund. No card/bank transfer is initiated — just an accounting entry for money already paid back.
          </div>

          <div className="flex justify-end gap-2 pt-2">
            <button type="button" onClick={onClose} className="px-3 py-1.5 text-sm border border-line rounded hover:bg-surface-2">Cancel</button>
            <button type="submit" disabled={submitting} className="px-3 py-1.5 text-sm bg-status-danger-fg text-white rounded hover:bg-status-danger-fg disabled:opacity-50">
              {submitting ? 'Saving…' : 'Refund Money'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

// ─── Small form-field helpers used by the RealPay + Offline tabs ──

function TextField({ label, value, onChange, type = 'text', required }: {
  label: string
  value: string
  onChange: (v: string) => void
  type?: string
  required?: boolean
}) {
  return (
    <div>
      <label className="block text-xs font-medium text-ink-muted mb-1">{label}</label>
      <input
        type={type}
        value={value}
        required={required}
        onChange={(e) => onChange(e.target.value)}
        className="w-full px-3 py-2 border border-line rounded text-sm focus:ring-2 focus:ring-primary focus:outline-none"
      />
    </div>
  )
}

function SelectField({ label, value, onChange, options, disabled }: {
  label: string
  value: string
  onChange: (v: string) => void
  options: { value: string; label: string }[]
  disabled?: boolean
}) {
  return (
    <div>
      <label className="block text-xs font-medium text-ink-muted mb-1">{label}</label>
      <select
        value={value}
        disabled={disabled}
        onChange={(e) => onChange(e.target.value)}
        className="w-full px-3 py-2 border border-line rounded text-sm bg-surface focus:ring-2 focus:ring-primary focus:outline-none disabled:bg-surface-2"
      >
        {options.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
      </select>
    </div>
  )
}

function ReadOnlyField({ label, value }: { label: string; value: string | number | null | undefined }) {
  return (
    <div>
      <label className="block text-xs font-medium text-ink-muted mb-1">{label}</label>
      <div className="w-full px-3 py-2 border border-line rounded text-sm bg-surface-2 text-ink-muted">{value ?? '—'}</div>
    </div>
  )
}

function SummaryStat({ label, value, highlight }: { label: string; value: string | number; highlight?: boolean }) {
  return (
    <div className={`rounded-md border px-3 py-2 ${highlight ? 'bg-status-info-bg border-primary' : 'bg-surface border-line'}`}>
      <div className="text-[11px] text-ink-muted uppercase tracking-wide">{label}</div>
      <div className={`text-sm font-semibold ${highlight ? 'text-primary' : 'text-ink'}`}>{value}</div>
    </div>
  )
}

function BoolToggle({ label, value, onChange }: { label: string; value: boolean; onChange: (v: boolean) => void }) {
  return (
    <div>
      <label className="block text-xs font-medium text-ink-muted mb-1">{label}</label>
      <div className="flex gap-3 items-center pt-1">
        <label className="flex items-center gap-1 text-sm">
          <input type="radio" checked={value === true}  onChange={() => onChange(true)} /> Yes
        </label>
        <label className="flex items-center gap-1 text-sm">
          <input type="radio" checked={value === false} onChange={() => onChange(false)} /> No
        </label>
      </div>
    </div>
  )
}

// ─── Shared UI Components ───────────────────────────────────────

function Card({ title, children, actions }: { title: ReactNode; children: ReactNode; actions?: ReactNode }) {
  return (
    <div className="bg-surface rounded-lg border border-line shadow-sm">
      <div className="px-4 py-3 border-b border-line flex items-center justify-between gap-2">
        <h2 className="text-sm font-semibold text-ink-muted">{title}</h2>
        {actions && <div className="flex items-center gap-2">{actions}</div>}
      </div>
      <div className="px-4 py-3 space-y-2">{children}</div>
    </div>
  )
}

function InfoRow({ label, value }: { label: string; value: ReactNode }) {
  const displayValue = typeof value === 'string' ? capitalizeValue(value) : value
  const titleText = typeof displayValue === 'string' ? displayValue : undefined
  return (
    <div className="flex justify-between text-sm gap-4">
      <span className="text-ink-muted flex-shrink-0">{formatLabel(label)}</span>
      <span className="text-ink font-medium text-right truncate" title={titleText}>{displayValue ?? '—'}</span>
    </div>
  )
}

function DocLink({ label, hasFile, loading, onPreview }: { label: string; hasFile?: boolean; url?: string | null; urlsLoaded?: boolean; loading?: boolean; onPreview: () => void }) {
  return (
    <div className="flex justify-between text-sm gap-4">
      <span className="text-ink-muted flex-shrink-0">{typeof label === 'string' && !label.includes(' ') ? formatLabel(label) : label}</span>
      <span className="text-right">
        {hasFile ? (
          loading ? (
            <span className="text-ink-faint text-xs">Loading…</span>
          ) : (
            <button onClick={onPreview} className="text-primary hover:underline font-medium">
              View Document
            </button>
          )
        ) : (
          <span className="text-status-danger-fg font-medium">Missing</span>
        )}
      </span>
    </div>
  )
}

function DocumentPreviewModal({ url, onClose }: { url: string | null; onClose: () => void }) {
  if (!url) return null

  const isPdf = url.toLowerCase().endsWith('.pdf')

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/60 p-4 pt-10 overflow-y-auto" onClick={onClose}>
      <div className="bg-surface rounded-lg shadow-xl max-w-4xl w-full max-h-[85vh] overflow-auto p-4 mx-4" onClick={(e) => e.stopPropagation()}>
        <div className="flex justify-between items-center mb-3">
          <h3 className="text-sm font-semibold text-ink-muted">Document Preview</h3>
          <button onClick={onClose} className="text-ink-faint hover:text-ink-muted text-xl leading-none">&times;</button>
        </div>
        {isPdf ? (
          <iframe src={url} className="w-full h-[75vh] rounded border border-line" title="PDF Preview" />
        ) : (
          <img src={url} alt="Document" className="max-w-full rounded" onError={(e) => {
            (e.target as HTMLImageElement).style.display = 'none'
            const parent = (e.target as HTMLImageElement).parentElement
            if (parent && !parent.querySelector('.error-msg')) {
              const msg = document.createElement('p')
              msg.className = 'text-status-danger-fg text-sm error-msg'
              msg.textContent = 'Failed to load image. The file may not be accessible.'
              parent.appendChild(msg)
            }
          }} />
        )}
        <div className="mt-3 text-right">
          <a href={url} target="_blank" rel="noreferrer" className="text-sm text-primary hover:underline">
            Open in new tab
          </a>
        </div>
      </div>
    </div>
  )
}

function EmptyState({ message }: { message: string }) {
  return (
    <div className="bg-surface rounded-lg border border-line p-8 text-center text-ink-faint">
      {message}
    </div>
  )
}

function DataTable({ columns, rows }: { columns: string[]; rows: ReactNode[][] }) {
  return (
    <DualScrollTable>
      <table className="w-full text-sm">
        <thead className="bg-surface-2 text-ink-muted uppercase text-xs">
          <tr>
            {columns.map((col) => (
              <th key={col} className="px-4 py-2 text-left">{formatLabel(col)}</th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-line">
          {rows.map((row, i) => (
            <tr key={i} className="hover:bg-surface-2">
              {row.map((cell, j) => (
                <td key={j} className="px-4 py-2">{cell}</td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </DualScrollTable>
  )
}

function Pagination({ current, last, onChange }: { current: number; last: number; onChange: (p: number) => void }) {
  return (
    <div className="flex items-center justify-between px-4 py-3 border-t border-line text-sm text-ink-muted">
      <span>Page {current} of {last}</span>
      <div className="flex gap-2">
        <button
          disabled={current <= 1}
          onClick={() => onChange(current - 1)}
          className="px-3 py-1 rounded border border-line disabled:opacity-50 hover:bg-surface-2"
        >
          Previous
        </button>
        <button
          disabled={current >= last}
          onClick={() => onChange(current + 1)}
          className="px-3 py-1 rounded border border-line disabled:opacity-50 hover:bg-surface-2"
        >
          Next
        </button>
      </div>
    </div>
  )
}

// ─── Helpers ────────────────────────────────────────────────────

/** Converts camelCase/PascalCase to "Title Case" — e.g. "licenseValidTill" → "License Valid Till" */
function formatLabel(str: string): string {
  return str
    .replace(/([a-z])([A-Z])/g, '$1 $2')  // insert space before uppercase
    .replace(/([A-Z]+)([A-Z][a-z])/g, '$1 $2') // handle consecutive caps like "KYC" or "ID"
    .replace(/\b\w/g, (c) => c.toUpperCase())  // capitalize every word (Title Case)
}

/** Title Case all words — e.g. "motor comprehensive" → "Motor Comprehensive" */
function capitalizeValue(str: string): string {
  if (!str || str === '—') return str
  // Don't capitalize UUIDs, emails, URLs, or values that look like codes
  if (str.includes('@') || str.includes('://') || str.includes('-') && str.length > 20) return str
  return str.replace(/\b\w/g, (c) => c.toUpperCase())
}

function fmtDate(value: string | null | undefined): string {
  if (!value) return '—'
  try {
    const d = new Date(value)
    const date = d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
    // Include time if the value contains time information (not just a date like "2024-01-15")
    const hasTime = value.includes('T') || value.includes(' ') && value.includes(':')
    if (hasTime) {
      const time = d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
      return `${date} ${time}`
    }
    return date
  } catch {
    return value
  }
}

function fmtCurrency(value: number | string | null | undefined): string {
  if (value == null) return '—'
  // Backend ledger endpoints pre-format amounts with thousand-separators ("143,363.50").
  // parseFloat stops at the comma and returns 143, which is why Monika saw
  // "BWP 143.00" for a 143k credit. Strip the commas before parsing.
  const num = typeof value === 'string' ? parseFloat(value.replace(/,/g, '')) : value
  if (isNaN(num)) return '—'
  return fmtPula(num)
}
