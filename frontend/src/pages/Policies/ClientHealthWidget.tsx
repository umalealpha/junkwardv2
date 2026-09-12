import type { ReactNode } from 'react'
import { usePolicyClientHealth } from '../../hooks/usePolicies'
import { fmtPula } from '../../utils/format'
import type { BannerSeverity, CollectionStatusKey, PolicyClientHealth } from '../../api/policies'

// ─── Tone / colour tables ──────────────────────────────────────────────────

const COLLECTION_TONE: Record<CollectionStatusKey, { bg: string; text: string; ring: string }> = {
  active:     { bg: 'bg-green-50',  text: 'text-green-700',  ring: 'ring-green-200' },
  in_arrears: { bg: 'bg-amber-50',  text: 'text-amber-700',  ring: 'ring-amber-200' },
  failed:     { bg: 'bg-red-50',    text: 'text-red-700',    ring: 'ring-red-200' },
  cancelled:  { bg: 'bg-gray-50',   text: 'text-gray-600',   ring: 'ring-gray-200' },
  none:       { bg: 'bg-gray-50',   text: 'text-gray-500',   ring: 'ring-gray-200' },
}

const BANNER_TONE: Record<BannerSeverity, { bg: string; border: string; text: string }> = {
  critical: { bg: 'bg-red-50',    border: 'border-red-200',    text: 'text-red-800' },
  warning:  { bg: 'bg-amber-50',  border: 'border-amber-200',  text: 'text-amber-800' },
  info:     { bg: 'bg-blue-50',   border: 'border-blue-200',   text: 'text-blue-800' },
  success:  { bg: 'bg-green-50',  border: 'border-green-200',  text: 'text-green-800' },
}

function arrearsTone(days: number): { text: string; chip: string } {
  if (days <= 0)  return { text: 'text-green-700', chip: 'bg-green-100 text-green-700' }
  if (days <= 30) return { text: 'text-amber-700', chip: 'bg-amber-100 text-amber-700' }
  return { text: 'text-red-700', chip: 'bg-red-100 text-red-700' }
}

function balanceTone(amount: number): string {
  if (amount <= 0) return 'text-green-700'
  return 'text-red-700'
}

// ─── Icons (inline SVG — no extra dependency) ──────────────────────────────

function MoneyIcon() {
  return (
    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={1.8}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
  )
}
function ClockIcon() {
  return (
    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={1.8}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
  )
}
function ShieldIcon() {
  return (
    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={1.8}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
    </svg>
  )
}
function ClipboardIcon() {
  return (
    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={1.8}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
    </svg>
  )
}

function BannerIcon({ severity }: { severity: BannerSeverity }) {
  if (severity === 'success') {
    return (
      <svg className="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
    )
  }
  // warning / critical / info all use the same exclamation triangle
  return (
    <svg className="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-2.99l-6.93-12a2 2 0 00-3.48 0L3.34 16a2 2 0 001.73 3z" />
    </svg>
  )
}

// ─── Building blocks ───────────────────────────────────────────────────────

function MetricCard({
  icon, label, accentTone, primary, secondary,
}: {
  icon: ReactNode
  label: string
  accentTone: string   // tailwind text colour for the icon halo
  primary: ReactNode
  secondary?: ReactNode
}) {
  return (
    <div className="bg-white rounded-lg border border-gray-200 shadow-sm p-4 flex items-start gap-3 min-w-0">
      <div className={`p-2 rounded-md bg-gray-50 ${accentTone} flex-shrink-0`}>
        {icon}
      </div>
      <div className="min-w-0 flex-1">
        <div className="text-[10px] font-semibold tracking-wider text-gray-500 uppercase">{label}</div>
        <div className="text-xl font-semibold leading-tight mt-0.5 truncate">{primary}</div>
        {secondary && <div className="text-[11px] text-gray-500 mt-1 truncate">{secondary}</div>}
      </div>
    </div>
  )
}

function SkeletonBar({ w = 'w-24' }: { w?: string }) {
  return <div className={`h-6 ${w} bg-gray-100 rounded animate-pulse`} />
}

// ─── Main widget ───────────────────────────────────────────────────────────

export default function ClientHealthWidget({ policyId }: { policyId: number }) {
  const { data, isLoading, isError } = usePolicyClientHealth(policyId)

  if (isLoading) return <LoadingState />
  if (isError || !data) return <ErrorState />

  return <Loaded data={data} />
}

function Loaded({ data }: { data: PolicyClientHealth }) {
  const balance = data.balanceOwing
  const arrears = data.daysInArrears
  const claims  = data.activeClaims
  const status  = data.collectionStatus
  const banner  = data.banner
  const method  = data.paymentMethod

  const balToneCls = balanceTone(balance.headline)
  const arrToneCls = arrearsTone(arrears)
  const colTone    = COLLECTION_TONE[status.key]
  const bannerTone = BANNER_TONE[banner.severity]

  // Balance secondary text — when RealPay is the source, show both
  // numbers side-by-side so reconciliation gaps surface.
  const balanceSecondary =
    balance.source === 'realpay' && balance.realpayUnpaid !== null ? (
      <span>
        RealPay <span className="font-medium text-gray-700">{fmtPula(balance.realpayUnpaid)}</span>
        <span className="mx-1.5 text-gray-300">|</span>
        Ledger <span className="font-medium text-gray-700">{fmtPula(balance.ledgerNet)}</span>
      </span>
    ) : (
      <span>via Ledger ({method.method})</span>
    )

  return (
    <div className="bg-gray-50 border-b border-gray-200 px-6 py-4 space-y-3">
      {/* 4-card responsive grid: 4 cols on lg+, 2 on md, 1 on mobile */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
        <MetricCard
          icon={<MoneyIcon />}
          label="Balance Owing"
          accentTone={balToneCls}
          primary={<span className={balToneCls}>{fmtPula(balance.headline)}</span>}
          secondary={balanceSecondary}
        />
        <MetricCard
          icon={<ClockIcon />}
          label="Days in Arrears"
          accentTone={arrToneCls.text}
          primary={<span className={arrToneCls.text}>{arrears}</span>}
          secondary={
            arrears === 0 ? <span className="text-green-700">On schedule</span> :
            arrears <= 30 ? <span className="text-amber-700">Attention required</span> :
            <span className="text-red-700">Critical — over 30 days</span>
          }
        />
        <MetricCard
          icon={<ShieldIcon />}
          label="Collection Status"
          accentTone={colTone.text}
          primary={
            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium ${colTone.bg} ${colTone.text} ring-1 ${colTone.ring}`}>
              {status.label}
            </span>
          }
          secondary={
            <span>
              {method.method}
              <span className="mx-1 text-gray-300">•</span>
              <span className="text-gray-400">{method.source}</span>
            </span>
          }
        />
        <MetricCard
          icon={<ClipboardIcon />}
          label="Active Claims"
          accentTone={claims.count > 0 ? 'text-amber-700' : 'text-gray-600'}
          primary={
            <span className={claims.count > 0 ? 'text-amber-700' : 'text-gray-800'}>
              {claims.count}
            </span>
          }
          secondary={
            <span>
              Reserve <span className="font-medium text-gray-700">{fmtPula(claims.totalReserve)}</span>
              <span className="mx-1.5 text-gray-300">|</span>
              Paid <span className="font-medium text-gray-700">{fmtPula(claims.totalPayment)}</span>
            </span>
          }
        />
      </div>

      {/* Status banner — single most-urgent message */}
      <div className={`flex items-center gap-2.5 px-4 py-2.5 rounded-md border ${bannerTone.bg} ${bannerTone.border} ${bannerTone.text}`}>
        <BannerIcon severity={banner.severity} />
        <span className="text-sm font-medium">{banner.message}</span>
      </div>
    </div>
  )
}

function LoadingState() {
  return (
    <div className="bg-gray-50 border-b border-gray-200 px-6 py-4 space-y-3">
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
        {[0, 1, 2, 3].map(i => (
          <div key={i} className="bg-white rounded-lg border border-gray-200 shadow-sm p-4 flex items-start gap-3">
            <div className="w-10 h-10 bg-gray-100 rounded-md animate-pulse" />
            <div className="flex-1 space-y-2">
              <SkeletonBar w="w-20" />
              <SkeletonBar w="w-28" />
            </div>
          </div>
        ))}
      </div>
      <div className="h-9 bg-white border border-gray-200 rounded-md animate-pulse" />
    </div>
  )
}

function ErrorState() {
  return (
    <div className="bg-gray-50 border-b border-gray-200 px-6 py-4">
      <div className="flex items-center gap-2.5 px-4 py-3 rounded-md border bg-amber-50 border-amber-200 text-amber-800">
        <svg className="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2}>
          <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-2.99l-6.93-12a2 2 0 00-3.48 0L3.34 16a2 2 0 001.73 3z" />
        </svg>
        <span className="text-sm font-medium">
          Could not load the client health summary. The rest of the policy page is still usable.
        </span>
      </div>
    </div>
  )
}
