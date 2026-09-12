// ─────────────────────────────────────────────────────────────────────────
// Help center — module registry (the structure / single source of truth).
//
// Each entry describes ONE module a user can navigate to. The Help center nav
// is generated from this list, so adding a module here makes it appear on the
// /help landing page automatically. The actual guide CONTENT lives as markdown
// in ./guides/<key>.md and is wired in by ./registry.ts.
//
// `codePaths` lists the repo-relative source globs each guide documents. The
// CI staleness checker (frontend/scripts/check-help-guides.mjs) uses them to
// warn when a module's code changes but its guide hasn't been re-reviewed.
//
// Keep `route` aligned with the live route in App.tsx / the Sidebar menu so the
// "Open this module" link and the missing-guide check stay honest.
// ─────────────────────────────────────────────────────────────────────────

export type HelpCategory =
  | 'Getting started'
  | 'Policy management'
  | 'Batch operations'
  | 'Customers & KYC'
  | 'Claims & underwriting'
  | 'Finance & payments'
  | 'Communications'
  | 'Reinsurance & commission'
  | 'Reports & compliance'
  | 'System administration'

export interface HelpModule {
  /** Stable kebab-case key. Matches the guide filename: guides/<key>.md */
  key: string
  /** User-facing module name (matches the sidebar label where possible). */
  title: string
  /** One-line description shown on the Help landing page. */
  summary: string
  /** Primary live route for this module, for the "Open this module" link. */
  route: string
  category: HelpCategory
  /** Repo-relative source globs this guide documents (for the staleness check). */
  codePaths: string[]
  /** Extra top-level route segments this one module also covers, so the CI
   *  menu-coverage check knows e.g. Agents covers /agencies and /agent-logins. */
  coversRoutes?: string[]
}

// Order within a category follows the array order.
export const HELP_MODULES: HelpModule[] = [
  // ─── Policy management ───────────────────────────────────────────────
  {
    key: 'policies',
    title: 'Policies',
    summary: 'Create, view, edit, renew and cancel policies of every product type.',
    route: '/policies',
    category: 'Policy management',
    codePaths: ['frontend/src/pages/Policies/**'],
  },
  {
    key: 'quotes',
    title: 'Quotes',
    summary: 'Manage quotations and convert a quote into a policy.',
    route: '/quotes',
    category: 'Policy management',
    codePaths: ['frontend/src/pages/Quotes/**'],
  },
  {
    key: 'leads',
    title: 'Leads',
    summary: 'Track prospective customers and the sales pipeline.',
    route: '/leads',
    category: 'Policy management',
    codePaths: ['frontend/src/pages/Leads/**'],
  },
  {
    key: 'renewals',
    title: 'Renewals',
    summary: 'Renewal dashboard and the MIS renew-policy flow.',
    route: '/renewals/dashboard',
    category: 'Policy management',
    codePaths: ['frontend/src/pages/Renewals/**', 'frontend/src/pages/Policies/RenewPolicyPage.tsx'],
  },

  // ─── Batch operations ────────────────────────────────────────────────
  {
    key: 'batch-processing',
    title: 'Batch Processing',
    summary: 'Bulk policy create, cancel and batch reporting.',
    route: '/batch/report',
    category: 'Batch operations',
    codePaths: ['frontend/src/pages/BatchProcessing/**'],
  },
  {
    key: 'ad-group-processing',
    title: 'AD Group Processing',
    summary: 'Employer-group and AD-group policies with batch creation.',
    route: '/ad-group/employer-groups',
    category: 'Batch operations',
    codePaths: ['frontend/src/pages/ADGroup/**'],
  },
  {
    key: 'excel-imports',
    title: 'Excel Imports',
    summary: 'Bulk import policies, cancellations, refunds and gateway data from Excel.',
    route: '/imports/policy-activation',
    category: 'Batch operations',
    codePaths: ['frontend/src/pages/Imports/**'],
  },

  // ─── Customers & KYC ─────────────────────────────────────────────────
  {
    key: 'customers',
    title: 'Customers',
    summary: '360° customer profiles, search, block list and policy reassignment.',
    route: '/customers',
    category: 'Customers & KYC',
    codePaths: ['frontend/src/pages/Customers/**'],
  },
  {
    key: 'customer-kyc',
    title: 'Customer KYC',
    summary: 'KYC compliance, re-KYC, deduplication and compliance rules.',
    route: '/kyc',
    category: 'Customers & KYC',
    codePaths: ['frontend/src/pages/KYC/**'],
  },

  // ─── Claims & underwriting ───────────────────────────────────────────
  {
    key: 'claims',
    title: 'Claims',
    summary: 'Register, track and resolve claims across all policy types.',
    route: '/claims',
    category: 'Claims & underwriting',
    codePaths: ['frontend/src/pages/Claims/**'],
  },
  {
    key: 'underwriting',
    title: 'Underwriting',
    summary: 'The underwriting workflow and policy approval queue.',
    route: '/underwriting',
    category: 'Claims & underwriting',
    codePaths: ['frontend/src/pages/Underwriting/**'],
  },
  {
    key: 'preinspection',
    title: 'Pre-inspection',
    summary: 'Vehicle and device condition assessments before underwriting.',
    route: '/preinspection/vehicle',
    category: 'Claims & underwriting',
    codePaths: ['frontend/src/pages/Preinspection/**'],
  },

  // ─── Finance & payments ──────────────────────────────────────────────
  {
    key: 'payments',
    title: 'Payments',
    summary: 'Offline payments, DPO, RealPay, Orange Money, discounts/surcharges and vendors.',
    route: '/payments/offline',
    category: 'Finance & payments',
    codePaths: ['frontend/src/pages/Payments/**'],
  },
  {
    key: 'accounting',
    title: 'Accounting',
    summary: 'Chart of accounts, accounting rules, sub-ledger and settlement reconciliation.',
    route: '/accounting/accounts',
    category: 'Finance & payments',
    codePaths: ['frontend/src/pages/Accounting/**', 'frontend/src/pages/Finance/**'],
  },
  {
    key: 'reconciliation',
    title: 'Reconciliation',
    summary: 'Financial reconciliation and payment anomaly detection.',
    route: '/reconciliation',
    category: 'Finance & payments',
    codePaths: ['frontend/src/pages/Reconciliation/**'],
  },
  {
    key: 'bulk-refunds',
    title: 'Bulk Refunds',
    summary: 'Bulk refund processing for DPO customers.',
    route: '/finance/bulk-refunds',
    category: 'Finance & payments',
    codePaths: ['frontend/src/pages/Finance/**'],
  },

  // ─── Communications ──────────────────────────────────────────────────
  {
    key: 'communications',
    title: 'Communications',
    summary: 'SMS, email and WhatsApp templates and delivery logs.',
    route: '/communications/sms-email-templates',
    category: 'Communications',
    codePaths: ['frontend/src/pages/Communications/**'],
  },

  // ─── Reinsurance & commission ────────────────────────────────────────
  {
    key: 'reinsurance',
    title: 'Reinsurance',
    summary: 'Reinsurance types, coverage groupings, formulas, treaties and reinsurers.',
    route: '/reinsurance/treaties',
    category: 'Reinsurance & commission',
    codePaths: ['frontend/src/pages/Reinsurance/**'],
  },
  {
    key: 'commission',
    title: 'Commission',
    summary: 'Agent commission rules, targets, ledger and fraud alerts.',
    route: '/commission',
    category: 'Reinsurance & commission',
    codePaths: ['frontend/src/pages/Commission/**'],
  },
  {
    key: 'agents',
    title: 'Agents & Staff',
    summary: 'Agent profiles, agencies and agent login history.',
    route: '/agents',
    category: 'Reinsurance & commission',
    codePaths: ['frontend/src/pages/Agents/**'],
    coversRoutes: ['agencies', 'agent-logins'],
  },

  // ─── Reports & compliance ────────────────────────────────────────────
  {
    key: 'reports',
    title: 'Reports',
    summary: 'Ad-hoc and scheduled business reporting across modules.',
    route: '/reports',
    category: 'Reports & compliance',
    codePaths: ['frontend/src/pages/Reports/**'],
  },
  {
    key: 'compliance',
    title: 'Compliance',
    summary: 'Customer complaints register and consent compliance tracking.',
    route: '/compliance/complaints',
    category: 'Reports & compliance',
    codePaths: ['frontend/src/pages/Compliance/**', 'frontend/src/pages/Consents/**'],
    coversRoutes: ['consents'],
  },
  {
    key: 'operational-anomalies',
    title: 'Operational Anomalies',
    summary: 'Detects and reports operational anomalies from WhatsApp and other channels.',
    route: '/anomalies',
    category: 'Reports & compliance',
    codePaths: ['frontend/src/pages/Anomalies/**'],
  },
  {
    key: 'audit-trail',
    title: 'Audit Trail',
    summary: 'System action log for compliance and audit.',
    route: '/audit-trail',
    category: 'Reports & compliance',
    codePaths: ['frontend/src/pages/AuditTrail/**'],
  },

  // ─── System administration ───────────────────────────────────────────
  {
    key: 'access-control',
    title: 'Access Control',
    summary: 'Users, roles and underwriting validation rules (RBAC).',
    route: '/users',
    category: 'System administration',
    codePaths: ['frontend/src/pages/Users/**', 'frontend/src/pages/Roles/**', 'frontend/src/pages/PolicyValidation/**'],
    coversRoutes: ['roles', 'policy-validation'],
  },
  {
    key: 'claims-support',
    title: 'Claims Support',
    summary: 'Supplier networks, repair centres and activation codes for claims.',
    route: '/suppliers',
    category: 'Claims & underwriting',
    codePaths: ['frontend/src/pages/Suppliers/**'],
    coversRoutes: ['repair-centers', 'activation-codes'],
  },
  {
    key: 'customer-rewards',
    title: 'Customer Rewards',
    summary: 'Loyalty rewards programmes, tiers and benefits.',
    route: '/rewards/tiers',
    category: 'Customers & KYC',
    codePaths: ['frontend/src/pages/Rewards/**'],
  },
  {
    key: 'regions-departments',
    title: 'Regions & Departments',
    summary: 'Organisational structure, regions and department hierarchies.',
    route: '/admin/regions-departments',
    category: 'System administration',
    codePaths: ['frontend/src/pages/Admin/**'],
  },
  {
    key: 'products',
    title: 'Products & Plans',
    summary: 'Insurance products, plans and product-specific configuration.',
    route: '/products',
    category: 'System administration',
    codePaths: ['frontend/src/pages/Products/**'],
  },
  {
    key: 'coverage-management',
    title: 'Coverage Management',
    summary: 'Coverages, sub-coverages, extensions and specified items.',
    route: '/master/coverages',
    category: 'System administration',
    codePaths: ['frontend/src/pages/Master/**'],
  },
  {
    key: 'cron-portal',
    title: 'Cron & Automation',
    summary: 'Scheduled jobs, daily activity and automation logs.',
    route: '/system/cron-portal',
    category: 'System administration',
    codePaths: ['frontend/src/pages/System/**'],
  },
  {
    key: 'document-jobs',
    title: 'Document Jobs',
    summary: 'Policy document, certificate and correspondence generation jobs.',
    route: '/system/document-jobs',
    category: 'System administration',
    codePaths: ['frontend/src/pages/System/DocumentJobsPage.tsx'],
  },
  {
    key: 'policy-wordings',
    title: 'Policy Wordings',
    summary: 'Standard legal wordings and policy document templates.',
    route: '/system/wordings',
    category: 'System administration',
    codePaths: ['frontend/src/pages/System/WordingsPage.tsx'],
  },
  {
    key: 'ai-copilot',
    title: 'AI Copilot',
    summary: 'Ask natural-language questions about operational data.',
    route: '/ai',
    category: 'System administration',
    codePaths: ['frontend/src/pages/AI/**', 'frontend/src/components/AI/**'],
  },
]

export const HELP_CATEGORY_ORDER: HelpCategory[] = [
  'Getting started',
  'Policy management',
  'Batch operations',
  'Customers & KYC',
  'Claims & underwriting',
  'Finance & payments',
  'Communications',
  'Reinsurance & commission',
  'Reports & compliance',
  'System administration',
]
