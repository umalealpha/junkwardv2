import { lazy, Suspense } from 'react'
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ReactQueryDevtools } from '@tanstack/react-query-devtools'
import AdminLayout from './components/Layout/AdminLayout'
import LoadingSpinner from './components/common/LoadingSpinner'
import EnvironmentBanner from './components/EnvironmentBanner'
import ErrorBoundary from './components/common/ErrorBoundary'
import ComingSoonPage from './components/common/ComingSoonPage'
import { ToastProvider } from './components/common/Toast'
import { ConfirmProvider } from './components/common/ConfirmDialog'
import { reportErrorToTeam } from './utils/reportError'
import { QueryCache, MutationCache } from '@tanstack/react-query'

// ─── Auth ─────────────────────────────────────────────
const LoginPage        = lazy(() => import('./pages/Auth/LoginPage'))
const SsoPage          = lazy(() => import('./pages/Auth/SsoPage'))

// ─── Main ─────────────────────────────────────────────
const DashboardPage         = lazy(() => import('./pages/Dashboard/DashboardPage'))
const FinanceDashboardPage  = lazy(() => import('./pages/Dashboard/FinanceDashboardPage'))

// ─── Help ─────────────────────────────────────────────
const HelpHomePage          = lazy(() => import('./pages/Help/HelpHomePage'))
const HelpModulePage        = lazy(() => import('./pages/Help/HelpModulePage'))

// ─── Help Desk (retired 2026-07 — lives in Alpha Bridge) ────────────
// Creation goes through the Bridge widget (BridgeReportRedirect keeps the
// /help-desk/new deep link working); every other native page (list, detail,
// SLA dashboard, submit form) is unrouted and forwards to the Bridge board —
// they showed pre-migration snapshots that diverge from Bridge. Components
// stay on disk in case a rollback is ever needed.
const BridgeReportRedirect  = lazy(() => import('./pages/HelpDesk/BridgeReportRedirect'))
const BridgeBoardRedirect   = lazy(() => import('./pages/HelpDesk/BridgeBoardRedirect'))

// ─── Policies ─────────────────────────────────────────
const PolicyListPage              = lazy(() => import('./pages/Policies/PolicyListPage'))
const PolicyDetailPage            = lazy(() => import('./pages/Policies/PolicyDetailPage'))
const PolicyCreatePage            = lazy(() => import('./pages/Policies/PolicyCreatePage'))
const SpecialistCoveragePage      = lazy(() => import('./pages/Policies/SpecialistCoveragePage'))
const GroupPolicyPage             = lazy(() => import('./pages/Policies/GroupPolicyPage'))
const RenewPolicyPage      = lazy(() => import('./pages/Policies/RenewPolicyPage'))
const RenewFlowPage        = lazy(() => import('./pages/Policies/RenewFlowPage'))
const CancelRequestsPage   = lazy(() => import('./pages/Policies/CancelRequestsPage'))
const SoftDeleteItemPage   = lazy(() => import('./pages/Policies/SoftDeleteItemPage'))
const WordingsLibraryPage  = lazy(() => import('./pages/Wordings/WordingsLibraryPage'))
const ChangelogPage        = lazy(() => import('./pages/WhatsNew/ChangelogPage'))

// ─── Quotes & Leads ──────────────────────────────────
const QuoteListPage        = lazy(() => import('./pages/Quotes/QuoteListPage'))
const QuoteDetailPage      = lazy(() => import('./pages/Quotes/QuoteDetailPage'))
const QuoteEditPage        = lazy(() => import('./pages/Quotes/QuoteEditPage'))

// ─── Batch Processing ────────────────────────────────
const BatchCreatePage      = lazy(() => import('./pages/BatchProcessing/BatchCreatePage'))
const BatchCancelPage      = lazy(() => import('./pages/BatchProcessing/BatchCancelPage'))
const BatchReportPage      = lazy(() => import('./pages/BatchProcessing/BatchReportPage'))

// ─── AD Group Processing ─────────────────────────────
const EmployerGroupsPage   = lazy(() => import('./pages/ADGroup/EmployerGroupsPage'))
const EmployerGroupCreatePage = lazy(() => import('./pages/ADGroup/EmployerGroupCreatePage'))
const EmployerGroupDetailPage = lazy(() => import('./pages/ADGroup/EmployerGroupDetailPage'))
const EmployerGroupEditPage   = lazy(() => import('./pages/ADGroup/EmployerGroupEditPage'))
const ADGroupPolicyPage    = lazy(() => import('./pages/ADGroup/ADGroupPolicyPage'))
const ADGroupBatchCreatePage = lazy(() => import('./pages/ADGroup/ADGroupBatchCreatePage'))

// ─── Claims ───────────────────────────────────────────
const ClaimListPage        = lazy(() => import('./pages/Claims/ClaimListPage'))
// /claims/create is flag-gated: ClaimCreateGate renders the tracker-style
// FnolCreatePage when `claims_fnol` is ON, else the legacy ClaimCreatePage.
const ClaimCreateGate      = lazy(() => import('./pages/Claims/ClaimCreateGate'))
const ClaimDetailPage      = lazy(() => import('./pages/Claims/ClaimDetailPage'))
const ClaimsSlaDashboardPage = lazy(() => import('./pages/Claims/ClaimsSlaDashboardPage'))
const BackdateControlPage  = lazy(() => import('./pages/Claims/BackdateControlPage'))
const ClaimsNotificationsPage = lazy(() => import('./pages/Claims/ClaimsNotificationsPage'))
const PremiumConfirmationTrackerPage = lazy(() => import('./pages/Claims/PremiumConfirmationTrackerPage'))
const ClaimsDashboardPage  = lazy(() => import('./pages/Claims/ClaimsDashboardPage'))
const ClaimsIncentiveReportPage = lazy(() => import('./pages/Claims/ClaimsIncentiveReportPage'))
const ClaimsAnalyticsPage = lazy(() => import('./pages/Claims/ClaimsAnalyticsPage'))
const ClaimsMasterDataPage = lazy(() => import('./pages/Claims/ClaimsMasterDataPage'))
const ClaimFormsPage = lazy(() => import('./pages/Claims/ClaimFormsPage'))
const ClaimReportSchedulesPage = lazy(() => import('./pages/Claims/ClaimReportSchedulesPage'))
const ClaimBulkImportPage = lazy(() => import('./pages/Claims/ClaimBulkImportPage'))
// FNOL (First Notification of Loss) intake — flag-gated (`claims_fnol`) +
// role-gated inside each page. Additive claim-intake surface.
const FnolListPage = lazy(() => import('./pages/Claims/FnolListPage'))
const FnolCreatePage = lazy(() => import('./pages/Claims/FnolCreatePage'))
const FnolDetailPage = lazy(() => import('./pages/Claims/FnolDetailPage'))
// Claims Tracker replica — placeholder for tracker screens not yet ported
// (How It Works, Policy Library, Audit Log, API Access). Keeps the tab strip complete.
const ClaimsApiAccessPage = lazy(() => import('./pages/Claims/ClaimsApiAccessPage'))

// ─── Customers ────────────────────────────────────────
const CustomerListPage         = lazy(() => import('./pages/Customers/CustomerListPage'))
const Customer360Page          = lazy(() => import('./pages/Customers/Customer360Page'))
const CustomerSearchPage       = lazy(() => import('./pages/Customers/CustomerSearchPage'))
const CustomerBlockListPage    = lazy(() => import('./pages/Customers/CustomerBlockListPage'))
const ChangeCustomerPolicyPage = lazy(() => import('./pages/Customers/ChangeCustomerPolicyPage'))

// ─── KYC ──────────────────────────────────────────────
const CustomerKycPage      = lazy(() => import('./pages/KYC/CustomerKycPage'))
const KycDetailPage        = lazy(() => import('./pages/KYC/KycDetailPage'))
const DomComKycDetailPage  = lazy(() => import('./pages/KYC/DomComKycDetailPage'))
const EmployerGroupKycPage = lazy(() => import('./pages/KYC/EmployerGroupKycPage'))
const ReKycPage            = lazy(() => import('./pages/KYC/ReKycPage'))
const ADGroupKycPage       = lazy(() => import('./pages/KYC/ADGroupKycPage'))
const ADGroupKycCampaignsPage = lazy(() => import('./pages/KYC/ADGroupKycCampaignsPage'))
const ADGroupKycCampaignPage  = lazy(() => import('./pages/KYC/ADGroupKycCampaignPage'))
const ADGroupKycLinkPage      = lazy(() => import('./pages/KYC/ADGroupKycLinkPage'))
const DuplicateCustomersPage = lazy(() => import('./pages/KYC/DuplicateCustomersPage'))
const DeduplicationPage    = lazy(() => import('./pages/KYC/DeduplicationPage'))
const KycCompliancePage    = lazy(() => import('./pages/KYC/KycCompliancePage'))
const KycFieldsPage        = lazy(() => import('./pages/KYC/KycFieldsPage'))
const KycAccessReportPage  = lazy(() => import('./pages/KYC/KycAccessReportPage'))
const SanctionedCustomersPage = lazy(() => import('./pages/KYC/SanctionedCustomersPage'))

// ─── Product Config & Underwriting ───────────────────
const ProductDetailPage2   = lazy(() => import('./pages/Products/ProductDetailPage'))
const UnderwritingQueuePage = lazy(() => import('./pages/Underwriting/UnderwritingQueuePage'))
const SmartUnderwritingUploadPage = lazy(() => import('./pages/Underwriting/SmartUnderwritingUploadPage'))
const SmsTemplatesPage     = lazy(() => import('./pages/Communications/SmsTemplatesPage'))
const WhatsAppTemplatesPage = lazy(() => import('./pages/Communications/WhatsAppTemplatesPage'))
const SettlementReconciliationPage      = lazy(() => import('./pages/Finance/SettlementReconciliationPage'))
const SettlementReconciliationDetailPage = lazy(() => import('./pages/Finance/SettlementReconciliationDetailPage'))
const RefundEnginePage          = lazy(() => import('./pages/Finance/Refunds/RefundEnginePage'))
const RefundRequestCreatePage   = lazy(() => import('./pages/Finance/Refunds/RefundRequestCreatePage'))
const RefundRequestDetailPage   = lazy(() => import('./pages/Finance/Refunds/RefundRequestDetailPage'))
const RefundAccountingQueuePage = lazy(() => import('./pages/Finance/Refunds/RefundAccountingQueuePage'))
const ExceptionsPage       = lazy(() => import('./pages/Finance/ExceptionsPage'))
const ExceptionDetailPage  = lazy(() => import('./pages/Finance/ExceptionDetailPage'))
const RegionsDepartmentsPage = lazy(() => import('./pages/Admin/RegionsDepartmentsPage'))
const AssessorListPage     = lazy(() => import('./pages/Admin/AssessorListPage'))
const LawyerListPage       = lazy(() => import('./pages/Admin/LawyerListPage'))
const StatesCitiesPage     = lazy(() => import('./pages/Admin/StatesCitiesPage'))
const InflationRatesPage   = lazy(() => import('./pages/Admin/InflationRatesPage'))

// ─── Payments Extended ───────────────────────────────
const RealPayContractsPage = lazy(() => import('./pages/Payments/RealPayContractsPage'))
const PaymentVendorsPage   = lazy(() => import('./pages/Admin/PaymentVendorsPage'))

// ─── Compliance ──────────────────────────────────────
const ComplaintsPage       = lazy(() => import('./pages/Compliance/ComplaintsPage'))
const HighRiskCountriesPage = lazy(() => import('./pages/Compliance/HighRiskCountriesPage'))

// ─── Rewards ─────────────────────────────────────────
const RewardTiersPage      = lazy(() => import('./pages/Rewards/RewardTiersPage'))

// ─── Accounting ──────────────────────────────────────
const ChartOfAccountsPage  = lazy(() => import('./pages/Accounting/ChartOfAccountsPage'))
const AccountingRulesPage  = lazy(() => import('./pages/Accounting/AccountingRulesPage'))
const SubLedgerPage        = lazy(() => import('./pages/Accounting/SubLedgerPage'))

// ─── Suppliers & Claims Support ─────────────────────
const SupplierListPage     = lazy(() => import('./pages/Suppliers/SupplierListPage'))
const RepairCentersPage    = lazy(() => import('./pages/Suppliers/RepairCentersPage'))
const ActivationCodesPage  = lazy(() => import('./pages/Suppliers/ActivationCodesPage'))

// ─── Agents & Roles ──────────────────────────────────
const AgentListPage    = lazy(() => import('./pages/Agents/AgentListPage'))
const AgentDetailPage  = lazy(() => import('./pages/Agents/AgentDetailPage'))
const AgencyListPage   = lazy(() => import('./pages/Agents/AgencyListPage'))
const AgentLoginsPage  = lazy(() => import('./pages/Agents/AgentLoginsPage'))
const RolesPage        = lazy(() => import('./pages/Agents/RolesPage'))

// ─── Renewals ────────────────────────────────────────
const RenewalDashboardV2Page = lazy(() => import('./pages/Renewals/RenewalDashboardPage'))
const DomComBatchRenewPage   = lazy(() => import('./pages/Policies/DomComBatchRenewPage'))

// ─── Communications ──────────────────────────────────
const CommunicationTemplatesPage = lazy(() => import('./pages/Communications/CommunicationTemplatesPage'))
const CommunicationLogsPage      = lazy(() => import('./pages/Communications/CommunicationLogsPage'))

// ─── Preinspection ────────────────────────────────────
const VehiclePreinspectionPage = lazy(() => import('./pages/Preinspection/VehiclePreinspectionPage'))
const VehicleInspectionDetailPage = lazy(() => import('./pages/Preinspection/VehicleInspectionDetailPage'))
const DevicePreinspectionPage  = lazy(() => import('./pages/Preinspection/DevicePreinspectionPage'))
const DeviceInspectionDetailPage = lazy(() => import('./pages/Preinspection/DeviceInspectionDetailPage'))

// ─── Payments ─────────────────────────────────────────
const CancelSchedulePage    = lazy(() => import('./pages/Payments/CancelSchedulePage'))
const OfflinePaymentPage    = lazy(() => import('./pages/Payments/OfflinePaymentPage'))
const DiscountSurchargePage = lazy(() => import('./pages/Payments/DiscountSurchargePage'))
const DpoPaymentsPage       = lazy(() => import('./pages/Payments/DpoPaymentsPage'))
const RealPayPage            = lazy(() => import('./pages/Payments/RealPayPage'))

// ─── Excel Imports ────────────────────────────────────
const PolicyActivationImportPage  = lazy(() => import('./pages/Imports/PolicyActivationImportPage'))
const PolicyCancellationImportPage = lazy(() => import('./pages/Imports/PolicyCancellationImportPage'))
const RealpaySettlementImportPage = lazy(() => import('./pages/Imports/RealpaySettlementImportPage'))
const DpoRefundImportPage          = lazy(() => import('./pages/Imports/DpoRefundImportPage'))
const ActivateCancelReportPage     = lazy(() => import('./pages/Imports/ActivateCancelReportPage'))

// ─── Reconciliation ───────────────────────────────────
const ReconciliationDashboardPage = lazy(() => import('./pages/Reconciliation/ReconciliationDashboardPage'))
const ReconciliationAnomaliesPage = lazy(() => import('./pages/Reconciliation/ReconciliationAnomaliesPage'))

// ─── Anomaly findings (WhatsApp anomaly engine) ──────
const AnomalyFindingsPage = lazy(() => import('./pages/Anomalies/AnomalyFindingsPage'))

// ─── Consent compliance (DPA 2024 + ECTA 2014 + NBFIRA TCF) ──────────
const ConsentsPage = lazy(() => import('./pages/Admin/ConsentsPage'))

// ─── Refunds (DPO) ────────────────────────────────────
const BulkRefundPage       = lazy(() => import('./pages/Finance/BulkRefundPage'))
const BulkRefundDetailPage = lazy(() => import('./pages/Finance/BulkRefundDetailPage'))

// ─── Reinsurance ──────────────────────────────────────
const ReinsuranceTypePage   = lazy(() => import('./pages/Reinsurance/ReinsuranceTypePage'))
const CoverageGroupingPage  = lazy(() => import('./pages/Reinsurance/CoverageGroupingPage'))
const FormulaPage           = lazy(() => import('./pages/Reinsurance/FormulaPage'))
const TreatyPage            = lazy(() => import('./pages/Reinsurance/TreatyPage'))

// ─── AI ───────────────────────────────────────────────
const AiPage           = lazy(() => import('./pages/AI/AiPage'))

// ─── Audit Trail ────────────────────────────────────────
const AuditTrailPage   = lazy(() => import('./pages/AuditTrail/AuditTrailPage'))

// ─── User Profile ───────────────────────────────────────
const UserProfilePage  = lazy(() => import('./pages/UserProfile/UserProfilePage'))

// ─── Other ────────────────────────────────────────────
const ProductListPage  = lazy(() => import('./pages/Products/ProductListPage'))
const ReportsPage      = lazy(() => import('./pages/Reports/ReportsPage'))
const UserListPage     = lazy(() => import('./pages/Users/UserListPage'))

// ─── System ───────────────────────────────────────────
const ReportConfigPage          = lazy(() => import('./pages/System/ReportConfigPage'))
const CronPortalPage            = lazy(() => import('./pages/System/CronPortalPage'))
const CronDailyActivityPage     = lazy(() => import('./pages/System/CronDailyActivityPage'))
const CronLogsPage              = lazy(() => import('./pages/System/CronLogsPage'))
const DocumentJobsPage          = lazy(() => import('./pages/System/DocumentJobsPage'))
const StorageStatusPage         = lazy(() => import('./pages/System/StorageStatusPage'))
const WordingsPage              = lazy(() => import('./pages/System/WordingsPage'))
const SmsLogExportsPage         = lazy(() => import('./pages/System/SmsLogExportsPage'))
const RefreshEndorseRangePage   = lazy(() => import('./pages/System/RefreshEndorseRangePage'))
const IntegrationsPage          = lazy(() => import('./pages/Admin/IntegrationsPage'))
const SwiftlyTestConsolePage    = lazy(() => import('./pages/Admin/SwiftlyTestConsolePage'))
const MapfreSubmissionsPage     = lazy(() => import('./pages/Admin/MapfreSubmissionsPage'))
const AlphaTransitPage          = lazy(() => import('./pages/Admin/AlphaTransitPage'))
const PartnerCompaniesPage      = lazy(() => import('./pages/Admin/PartnerCompaniesPage'))

// ─── New pages ────────────────────────────────────────
const OrangeMoneyPage       = lazy(() => import('./pages/Payments/OrangeMoneyPage'))
const NGeniusImportPage     = lazy(() => import('./pages/Imports/NGeniusImportPage'))
const LeadListPage          = lazy(() => import('./pages/Leads/LeadListPage'))
const BulkImportPage        = lazy(() => import('./pages/Policies/BulkImportPage'))
const ValidationRulesPage   = lazy(() => import('./pages/PolicyValidation/ValidationRulesPage'))
const ValidationGroupsPage  = lazy(() => import('./pages/PolicyValidation/ValidationGroupsPage'))
const RoleRuleGroupsPage    = lazy(() => import('./pages/PolicyValidation/RoleRuleGroupsPage'))
const CoverageMasterPage    = lazy(() => import('./pages/Master/CoverageMasterPage'))
const SpecifiedCoverageItemsPage = lazy(() => import('./pages/Master/SpecifiedCoverageItemsPage'))
const SubCoveragesPage      = lazy(() => import('./pages/Master/SubCoveragesPage'))
const ExtensionsPage        = lazy(() => import('./pages/Master/ExtensionsPage'))
const CompaniesAdminPage    = lazy(() => import('./pages/Master/CompaniesAdminPage'))
const UnionsListPage        = lazy(() => import('./pages/Unions/UnionsListPage'))
const UnionMembersPage      = lazy(() => import('./pages/Unions/UnionMembersPage'))
const UnionDashboardPage    = lazy(() => import('./pages/Unions/UnionDashboardPage'))
const UnionPaymentsPage     = lazy(() => import('./pages/Unions/UnionPaymentsPage'))
const LegalClaimFormPage    = lazy(() => import('./pages/Unions/LegalClaimFormPage'))
const BonuLegalClaimFormPage     = lazy(() => import('./pages/Unions/BonuLegalClaimFormPage'))
const BowasewuLegalClaimFormPage = lazy(() => import('./pages/Unions/BowasewuLegalClaimFormPage'))
const ReinsurersPage        = lazy(() => import('./pages/Reinsurance/ReinsurersPage'))
const FacRegisterPage       = lazy(() => import('./pages/Reinsurance/FacRegisterPage'))
const FacPlacementDetailPage = lazy(() => import('./pages/Reinsurance/FacPlacementDetailPage'))
const FacSettlementsPage    = lazy(() => import('./pages/Reinsurance/FacSettlementsPage'))
const FacBordereauPage      = lazy(() => import('./pages/Reinsurance/FacBordereauPage'))

// ─── Commission ──────────────────────────────────────
const CommissionDashboardPage = lazy(() => import('./pages/Commission/CommissionDashboardPage'))
const CommissionRulesPage     = lazy(() => import('./pages/Commission/CommissionRulesPage'))
const CommissionTargetsPage   = lazy(() => import('./pages/Commission/CommissionTargetsPage'))
const CommissionLedgerPage    = lazy(() => import('./pages/Commission/CommissionLedgerPage'))
const CommissionFraudPage     = lazy(() => import('./pages/Commission/CommissionFraudPage'))

// Global error handlers for React Query — every failed query / mutation
// fires the team-notification reporter once retries are exhausted.
// Added 2026-05-26 per Pramod's UAT-response directive: silent data-load
// failures must surface to developers@theriskco.com so the team can
// triage broken pages without needing every operator to report them.
const queryClient = new QueryClient({
  queryCache: new QueryCache({
    onError: (error, query) => {
      const key = query.queryKey && query.queryKey.length > 0
        ? String(query.queryKey[0])
        : 'unknown'
      reportErrorToTeam({
        error: error instanceof Error ? error.message : String(error),
        stack: error instanceof Error ? error.stack : undefined,
        context: `query:${key}`,
      })
    },
  }),
  mutationCache: new MutationCache({
    onError: (error, _vars, _ctx, mutation) => {
      const mkey = mutation.options.mutationKey && mutation.options.mutationKey.length > 0
        ? String(mutation.options.mutationKey[0])
        : 'unknown'
      reportErrorToTeam({
        error: error instanceof Error ? error.message : String(error),
        stack: error instanceof Error ? error.stack : undefined,
        context: `mutation:${mkey}`,
      })
    },
  }),
  defaultOptions: {
    queries: {
      retry: 1,
      refetchOnWindowFocus: false,
    },
  },
})

const PageLoader = (
  <div className="flex items-center justify-center h-64">
    <LoadingSpinner size="lg" />
  </div>
)

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <EnvironmentBanner />
      <ToastProvider>
      <ConfirmProvider>
      <BrowserRouter>
        <Suspense fallback={PageLoader}>
          <ErrorBoundary>
          <Routes>
            {/* Public */}
            <Route path="/login" element={<LoginPage />} />
            <Route path="/sso" element={<SsoPage />} />

            {/* Protected — wrapped in AdminLayout (sidebar + header) */}
            <Route element={<AdminLayout />}>
              <Route index element={<DashboardPage />} />
              <Route path="finance" element={<FinanceDashboardPage />} />

              {/* Help & Guides */}
              <Route path="help" element={<HelpHomePage />} />
              <Route path="help/:moduleKey" element={<HelpModulePage />} />
              <Route path="whats-new" element={<ChangelogPage />} />

              {/* Help Desk — retired; lives in Alpha Bridge. /new opens the
                  Bridge report widget; every other old URL forwards to the
                  Bridge board. */}
              <Route path="help-desk/new" element={<BridgeReportRedirect />} />
              <Route path="help-desk/*" element={<BridgeBoardRedirect />} />
              <Route path="help-desk" element={<BridgeBoardRedirect />} />

              {/* Policies */}
              <Route path="wordings" element={<WordingsLibraryPage />} />
              <Route path="policies" element={<PolicyListPage />} />
              <Route path="policies/create" element={<PolicyCreatePage />} />
              <Route path="policies/:id/edit" element={<PolicyCreatePage />} />
              <Route path="policies/:id/renew" element={<RenewFlowPage />} />
              <Route path="policies/:id/specialist-coverage/:type" element={<SpecialistCoveragePage />} />
              <Route path="policies/:id" element={<PolicyDetailPage />} />
              <Route path="policies/group" element={<GroupPolicyPage />} />
              <Route path="policies/renew" element={<RenewPolicyPage />} />
              <Route path="policies/dom-com-batch-renew" element={<DomComBatchRenewPage />} />
              <Route path="policies/cancel-requests" element={<CancelRequestsPage />} />
              <Route path="policies/soft-delete" element={<SoftDeleteItemPage />} />

              {/* Quotes & Leads */}
              <Route path="quotes" element={<QuoteListPage />} />
              <Route path="quotes/:id" element={<QuoteDetailPage />} />
              <Route path="quotes/:id/edit" element={<QuoteEditPage />} />

              {/* Batch Processing */}
              <Route path="batch/create" element={<BatchCreatePage />} />
              <Route path="batch/cancel" element={<BatchCancelPage />} />
              <Route path="batch/report" element={<BatchReportPage />} />

              {/* AD Group Processing */}
              <Route path="ad-group/employer-groups" element={<EmployerGroupsPage />} />
              <Route path="ad-group/employer-groups/new" element={<EmployerGroupCreatePage />} />
              <Route path="ad-group/employer-groups/:id" element={<EmployerGroupDetailPage />} />
              <Route path="ad-group/employer-groups/:id/edit" element={<EmployerGroupEditPage />} />
              <Route path="ad-group/policies" element={<ADGroupPolicyPage />} />
              <Route path="ad-group/batch-create" element={<ADGroupBatchCreatePage />} />

              {/* Claims */}
              <Route path="claims" element={<ClaimListPage />} />
              {/* Claims dashboard — core claims nav, role-gated inside the page
                  (renders a no-access state otherwise). Static segment must
                  precede the :id route. */}
              <Route path="claims/dashboard" element={<ClaimsDashboardPage />} />
              <Route path="claims/create" element={<ClaimCreateGate />} />
              {/* Claims SLA dashboard — flag + role gated inside the page (renders
                  a no-access / not-enabled state otherwise). Static segment must
                  precede the :id route. */}
              <Route path="claims/sla" element={<ClaimsSlaDashboardPage />} />
              {/* Backdate Control (admin) — preview-capable regardless of the
                  `claims_backdate_governance` flag; role-gated inside the page.
                  Static segment must precede the :id route. */}
              <Route path="claims/backdate-control" element={<BackdateControlPage />} />
              {/* Claims Notifications dashboard — flag + role gated inside the
                  page (renders a no-access / not-enabled state otherwise).
                  Static segment must precede the :id route. */}
              <Route path="claims/notifications" element={<ClaimsNotificationsPage />} />
              {/* Premium Confirmation Tracker — measures Finance against the 24-hour
                  release. Renders a not-enabled state while the flag is off.
                  Static segment must precede the :id route. */}
              <Route path="claims/premium-confirmations" element={<PremiumConfirmationTrackerPage />} />
              {/* Incentive report + analytics — role-gated inside each page.
                  Static segments must precede the :id route. */}
              <Route path="claims/incentive" element={<ClaimsIncentiveReportPage />} />
              <Route path="claims/analytics" element={<ClaimsAnalyticsPage />} />
              {/* Claims Master Data — Admin / Super Admin gated inside the page
                  (renders an access-restricted state otherwise). Static segment
                  must precede the :id route. */}
              <Route path="claims/master-data" element={<ClaimsMasterDataPage />} />
              <Route path="claims/forms" element={<ClaimFormsPage />} />
              {/* Claims Admin (Phase 2) — role-gated inside each page and
                  flag-gated in the sidebar (claims_scheduled_reports /
                  claims_bulk_import). Report schedules are admin-only (Admin /
                  Super Admin); bulk import also allows Claims Manager. Static
                  segments must precede the :id route. */}
              <Route path="claims/report-schedules" element={<ClaimReportSchedulesPage />} />
              <Route path="claims/bulk-import" element={<ClaimBulkImportPage />} />
              {/* FNOL Intake — flag (`claims_fnol`) + role gated inside each page
                  (renders a no-access / not-enabled state otherwise). Static
                  segments (fnol, fnol/new) must precede the :id routes. */}
              <Route path="claims/fnol" element={<FnolListPage />} />
              <Route path="claims/fnol/new" element={<FnolCreatePage />} />
              <Route path="claims/fnol/:id" element={<FnolDetailPage />} />
              {/* Claims Tracker replica — tabs that map to existing Graphite
                  pages redirect there (Pramod 2026-08-07). API Access is a real
                  screen (Sanctum token registry via /claims-v2/api-access).
                  Static segments must precede the :id route. */}
              <Route path="claims/how-it-works" element={<Navigate to="/help/claims" replace />} />
              <Route path="claims/policy-library" element={<Navigate to="/system/wordings" replace />} />
              <Route path="claims/audit-log" element={<Navigate to="/audit-trail" replace />} />
              <Route path="claims/api-access" element={<ClaimsApiAccessPage />} />
              <Route path="claims/:id" element={<ClaimDetailPage />} />

              {/* Customers */}
              <Route path="customers" element={<CustomerListPage />} />
              <Route path="customers/:id" element={<Customer360Page />} />
              <Route path="customers/search" element={<CustomerSearchPage />} />
              <Route path="customers/block-list" element={<CustomerBlockListPage />} />
              <Route path="customers/change-policy" element={<ChangeCustomerPolicyPage />} />

              {/* KYC.
                  /kyc/:kycId is V8-parity: the segment carries
                  customer_kyc.id, not customer.id (see KycDetailPage).
                  DomCom is still keyed on customer.id — that flow merges
                  customer_kyc + customer_kyc_dom_com via the customer. */}
              <Route path="kyc" element={<CustomerKycPage />} />
              <Route path="kyc/:kycId" element={<KycDetailPage />} />
              <Route path="kyc/dom-com/:kycId" element={<DomComKycDetailPage />} />
              <Route path="kyc/employer-group" element={<EmployerGroupKycPage />} />
              <Route path="kyc/re-kyc" element={<ReKycPage />} />
              {/* AD Group KYC campaign management (V8 admin port). The old
                  employer-group listing survives at /kyc/ad-group/employer-groups
                  as a secondary reference screen. */}
              <Route path="kyc/ad-group" element={<ADGroupKycCampaignsPage />} />
              <Route path="kyc/ad-group/employer-groups" element={<ADGroupKycPage />} />
              <Route path="kyc/ad-group/campaigns/:id" element={<ADGroupKycCampaignPage />} />
              <Route path="kyc/ad-group/links/:id" element={<ADGroupKycLinkPage />} />
              <Route path="kyc/duplicates" element={<DuplicateCustomersPage />} />
              <Route path="kyc/deduplication" element={<DeduplicationPage />} />
              <Route path="kyc/compliance-rules" element={<KycCompliancePage />} />
              <Route path="kyc/fields" element={<KycFieldsPage />} />
              <Route path="kyc/access-report" element={<KycAccessReportPage />} />
              <Route path="kyc/sanctioned" element={<SanctionedCustomersPage />} />

              {/* Product Config & Underwriting */}
              <Route path="products/:id/config" element={<ProductDetailPage2 />} />
              <Route path="underwriting" element={<UnderwritingQueuePage />} />
              <Route path="underwriting/smart-upload" element={<SmartUnderwritingUploadPage />} />
              <Route path="communications/sms-email-templates" element={<SmsTemplatesPage />} />
              <Route path="communications/whatsapp-templates" element={<WhatsAppTemplatesPage />} />
              <Route path="finance/settlement-reconciliation" element={<SettlementReconciliationPage />} />
              <Route path="finance/settlement-reconciliation/:id" element={<SettlementReconciliationDetailPage />} />
              <Route path="finance/refund-engine" element={<RefundEnginePage />} />
              <Route path="finance/refund-engine/new" element={<RefundRequestCreatePage />} />
              <Route path="finance/refund-engine/accounting" element={<RefundAccountingQueuePage />} />
              <Route path="finance/refund-engine/:id/edit" element={<RefundRequestCreatePage />} />
              <Route path="finance/refund-engine/:id" element={<RefundRequestDetailPage />} />
              <Route path="finance/exceptions" element={<ExceptionsPage />} />
              <Route path="finance/exceptions/:id" element={<ExceptionDetailPage />} />
              <Route path="admin/regions-departments" element={<RegionsDepartmentsPage />} />
              <Route path="admin/assessors" element={<AssessorListPage />} />
              <Route path="admin/lawyers" element={<LawyerListPage />} />
              <Route path="admin/geography" element={<StatesCitiesPage />} />
              <Route path="admin/inflation-rates" element={<InflationRatesPage />} />

              {/* Payments Extended */}
              <Route path="payments/realpay-contracts" element={<RealPayContractsPage />} />
              <Route path="payments/vendors" element={<PaymentVendorsPage />} />

              {/* Compliance */}
              <Route path="compliance/complaints" element={<ComplaintsPage />} />
              <Route path="compliance/high-risk-countries" element={<HighRiskCountriesPage />} />

              {/* Rewards */}
              <Route path="rewards/tiers" element={<RewardTiersPage />} />

              {/* Accounting */}
              <Route path="accounting/accounts" element={<ChartOfAccountsPage />} />
              <Route path="accounting/rules" element={<AccountingRulesPage />} />
              <Route path="accounting/sub-ledger" element={<SubLedgerPage />} />

              {/* Suppliers & Claims Support */}
              <Route path="suppliers" element={<SupplierListPage />} />
              <Route path="repair-centers" element={<RepairCentersPage />} />
              <Route path="activation-codes" element={<ActivationCodesPage />} />

              {/* Agents & Roles */}
              <Route path="agents" element={<AgentListPage />} />
              <Route path="agents/:id" element={<AgentDetailPage />} />
              <Route path="agencies" element={<AgencyListPage />} />
              <Route path="agent-logins" element={<AgentLoginsPage />} />
              <Route path="roles" element={<RolesPage />} />

              {/* Renewals */}
              <Route path="renewals/dashboard" element={<RenewalDashboardV2Page />} />

              {/* Communications */}
              <Route path="communications/templates" element={<CommunicationTemplatesPage />} />
              <Route path="communications/logs" element={<CommunicationLogsPage />} />

              {/* Preinspection */}
              <Route path="preinspection/vehicle" element={<VehiclePreinspectionPage />} />
              <Route path="preinspection/vehicle/:id" element={<VehicleInspectionDetailPage />} />
              <Route path="preinspection/device" element={<DevicePreinspectionPage />} />
              <Route path="preinspection/device/:id" element={<DeviceInspectionDetailPage />} />

              {/* Payments */}
              <Route path="payments/cancel-schedule" element={<CancelSchedulePage />} />
              <Route path="payments/offline" element={<OfflinePaymentPage />} />
              <Route path="payments/discount-surcharge" element={<DiscountSurchargePage />} />
              <Route path="payments/dpo" element={<DpoPaymentsPage />} />
              <Route path="payments/realpay" element={<RealPayPage />} />

              {/* Excel Imports */}
              <Route path="imports/policy-activation" element={<PolicyActivationImportPage />} />
              <Route path="imports/policy-cancellation" element={<PolicyCancellationImportPage />} />
              <Route path="imports/realpay-settlement" element={<RealpaySettlementImportPage />} />
              <Route path="imports/dpo-refund" element={<DpoRefundImportPage />} />
              <Route path="imports/activate-cancel-report" element={<ActivateCancelReportPage />} />

              {/* Reinsurance */}
              {/* Reconciliation */}
              <Route path="reconciliation" element={<ReconciliationDashboardPage />} />
              <Route path="reconciliation/anomalies" element={<ReconciliationAnomaliesPage />} />

              {/* Anomaly findings — WhatsApp anomaly engine output */}
              <Route path="anomalies" element={<AnomalyFindingsPage />} />
              <Route path="consents"  element={<ConsentsPage />} />

              {/* Finance — DPO refunds */}
              <Route path="finance/bulk-refunds"      element={<BulkRefundPage />} />
              <Route path="finance/bulk-refunds/:id"  element={<BulkRefundDetailPage />} />

              <Route path="reinsurance/types" element={<ReinsuranceTypePage />} />
              <Route path="reinsurance/coverage-groups" element={<CoverageGroupingPage />} />
              <Route path="reinsurance/formulas" element={<FormulaPage />} />
              <Route path="reinsurance/treaties" element={<TreatyPage />} />
              {/* FAC register — settlements route declared before :id so it is not swallowed */}
              <Route path="reinsurance/fac" element={<FacRegisterPage />} />
              <Route path="reinsurance/fac/settlements" element={<FacSettlementsPage />} />
              <Route path="reinsurance/fac/bordereau" element={<FacBordereauPage />} />
              <Route path="reinsurance/fac/:id" element={<FacPlacementDetailPage />} />

              {/* AI */}
              <Route path="ai" element={<AiPage />} />

              {/* Audit Trail */}
              <Route path="audit-trail" element={<AuditTrailPage />} />

              {/* User Profile */}
              <Route path="profile" element={<UserProfilePage />} />

              {/* Other */}
              <Route path="products" element={<ProductListPage />} />
              <Route path="reports" element={<ReportsPage />} />
              <Route path="users" element={<UserListPage />} />

              {/* Leads */}
              <Route path="leads" element={<LeadListPage />} />

              {/* Payments — additional */}
              <Route path="payments/orange-money" element={<OrangeMoneyPage />} />

              {/* Imports — additional */}
              <Route path="imports/ngenius" element={<NGeniusImportPage />} />

              {/* Policies — bulk import */}
              <Route path="policies/bulk-import" element={<BulkImportPage />} />

              {/* Policy Validation */}
              <Route path="policy-validation/rules" element={<ValidationRulesPage />} />
              <Route path="policy-validation/groups" element={<ValidationGroupsPage />} />
              <Route path="policy-validation/role-groups" element={<RoleRuleGroupsPage />} />

              {/* Coverage Master */}
              <Route path="master/coverages" element={<CoverageMasterPage />} />
              <Route path="master/sub-coverages" element={<SubCoveragesPage />} />
              <Route path="master/extensions" element={<ExtensionsPage />} />
              <Route path="master/specified-coverage-items" element={<SpecifiedCoverageItemsPage />} />
              <Route path="master/companies" element={<CompaniesAdminPage />} />

              {/* Union Group Scheme */}
              <Route path="unions" element={<UnionsListPage />} />
              <Route path="unions/members" element={<UnionMembersPage />} />
              <Route path="unions/:id" element={<UnionDashboardPage />} />
              <Route path="unions/:id/payments" element={<UnionPaymentsPage />} />
              <Route path="unions/:id/members/:memberId/legal-claim/bonu" element={<BonuLegalClaimFormPage />} />
              <Route path="unions/:id/members/:memberId/legal-claim/bowasewu" element={<BowasewuLegalClaimFormPage />} />
              <Route path="unions/:id/members/:memberId/legal-claim" element={<LegalClaimFormPage />} />

              {/* Reinsurers */}
              <Route path="reinsurance/reinsurers" element={<ReinsurersPage />} />

              {/* Commission */}
              <Route path="commission" element={<CommissionDashboardPage />} />
              <Route path="commission/rules" element={<CommissionRulesPage />} />
              <Route path="commission/targets" element={<CommissionTargetsPage />} />
              <Route path="commission/ledger" element={<CommissionLedgerPage />} />
              <Route path="commission/fraud-alerts" element={<CommissionFraudPage />} />

              {/* System */}
              <Route path="system/report-config" element={<ReportConfigPage />} />
              <Route path="system/cron-portal" element={<CronPortalPage />} />
              <Route path="system/cron-daily-activity" element={<CronDailyActivityPage />} />
              <Route path="system/cron-logs" element={<CronLogsPage />} />
              <Route path="system/document-jobs" element={<DocumentJobsPage />} />
              <Route path="system/storage-status" element={<StorageStatusPage />} />
              <Route path="system/wordings" element={<WordingsPage />} />
              <Route path="system/sms-exports" element={<SmsLogExportsPage />} />
              <Route path="system/refresh-endorse-range" element={<RefreshEndorseRangePage />} />
              <Route path="admin/integrations" element={<IntegrationsPage />} />
              <Route path="admin/integrations/swiftly/console" element={<SwiftlyTestConsolePage />} />
              <Route path="admin/integrations/mapfre/binds" element={<MapfreSubmissionsPage />} />
              <Route path="admin/alpha-transit" element={<AlphaTransitPage />} />
              <Route path="partner-companies" element={<PartnerCompaniesPage />} />

              {/* Companies redirect */}
              <Route path="companies" element={<Navigate to="/customers" replace />} />

              {/* ─── Coming Soon stubs ──────────────────────────────────
                  Routes that previously fell through to the catch-all
                  (silently redirecting to Dashboard) — replaced with
                  informative placeholders so UAT testers see what is
                  in flight rather than reporting "blank page" as a defect.
                  Source: Arjun H3 + Prathap BUG-021 (26 May 2026 UAT). */}
              {/* /customer-kyc — historically a "Coming Soon" stub even though
                  the real Customer KYC list (CustomerKycPage) already shipped
                  at /kyc. Redirect to /kyc so the URL CFO Prathap had on file
                  (BUG-021, CFO 11 PM list #11) lands on the working dashboard
                  instead of the placeholder. Single canonical URL = /kyc. */}
              <Route path="customer-kyc" element={<Navigate to="/kyc" replace />} />
              <Route path="access-control" element={
                <ComingSoonPage
                  moduleName="Access Control"
                  trackingId="V2-FEAT-ACCESS-CONTROL"
                  description="User role and permission management is being migrated from the legacy admin panel. Existing role management remains available through the admin dashboard for now."
                />
              } />
              <Route path="payments" element={
                <ComingSoonPage
                  moduleName="Payments Hub"
                  trackingId="V2-FEAT-PAYMENTS-HUB"
                  description="A consolidated payments overview is in progress. Specific payment workflows (RealPay contracts, offline payments, DPO transactions, Orange Money) are available from within each policy."
                />
              } />
              <Route path="communications" element={
                <ComingSoonPage
                  moduleName="Communications Hub"
                  trackingId="V2-FEAT-COMMUNICATIONS-HUB"
                  description="A consolidated communications overview is being built. Existing communication tools (WhatsApp templates, SMS / Email templates, logs) remain available under Communications in the sidebar."
                />
              } />
              <Route path="underwriting-queue" element={
                <ComingSoonPage
                  moduleName="Underwriting Queue"
                  trackingId="V2-FEAT-UW-QUEUE"
                  description="The dedicated underwriting work queue is being built. Policies pending underwriting can be filtered from the Policies list using the UW Status filter for the time being."
                />
              } />
              <Route path="cancel-requests" element={
                <ComingSoonPage
                  moduleName="Cancellation Requests"
                  trackingId="V2-FEAT-CANCEL-REQUESTS"
                  description="The cancellation-request review surface is being built. Cancellations can be raised today from the individual policy detail page."
                />
              } />
            </Route>

            {/* Catch-all — informative 404 rather than silent redirect, so
                operators know they landed on something unmapped and AI
                testers don't report blank pages as defects. */}
            <Route path="*" element={
              <ComingSoonPage
                moduleName="Page Not Found"
                trackingId="V2-ROUTE-NOT-FOUND"
                description="The URL you opened does not match any page in Graphite V2. If you reached this from a saved link or another system, the link may need updating. If you reached this from inside Graphite V2, please report it so we can fix the navigation."
              />
            } />
          </Routes>
          </ErrorBoundary>
        </Suspense>
      </BrowserRouter>
      <ReactQueryDevtools initialIsOpen={false} />
      </ConfirmProvider>
      </ToastProvider>
    </QueryClientProvider>
  )
}
