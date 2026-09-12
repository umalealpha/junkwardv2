import { useState, useEffect, useRef, useMemo, type ReactNode, type RefObject } from 'react'
import { NavLink, useLocation } from 'react-router-dom'
import { IS_TEST_ENV } from '../EnvironmentBanner'
import { ALPHA_BRIDGE_BOARD_URL } from '../../utils/alphaBridge'
import { useIntegrations } from '../../hooks/useIntegrations'

interface MenuItem {
  label: string
  path?: string
  /** External URL — renders a plain <a> (new tab) instead of a NavLink.
      Used for cross-app destinations (Alpha Bridge). Mutually exclusive
      with path/children. */
  href?: string
  /** With href: open in the SAME window (omit target=_blank). For our own
      static pages (e.g. /alpha-brain/) that carry their own back-to-Graphite nav. */
  sameTab?: boolean
  icon: ReactNode
  permission?: string
  /** Single role name, or array of role names — item visible if user has ANY of them */
  role?: string | string[]
  /** Runtime integration/feature flag slug — item hidden unless that flag is ON. */
  flag?: string
  section?: string // section header above this item
  /** Children: `path` renders a NavLink; `href` renders a plain <a> in a NEW
      tab (cross-app destinations, e.g. Omni's PO board). One of the two. */
  children?: { label: string; path?: string; href?: string; permission?: string; role?: string | string[]; flag?: string }[]
}

export const MENU_ITEMS: MenuItem[] = [
  // ─── MAIN ───────────────────────────────────────
  {
    label: 'Dashboard',
    icon: <GridIcon />,
    children: [
      { label: 'Sales Dashboard', path: '/' },
      { label: 'Finance Dashboard', path: '/finance' },
    ],
  },

  // ─── POLICY MANAGEMENT ──────────────────────────
  {
    label: 'Policies',
    icon: <DocIcon />,
    section: 'Policy Management',
    permission: 'policy-list',
    children: [
      { label: 'All Policies', path: '/policies' },
      { label: 'Policy Wordings', path: '/wordings' },
      { label: 'Draft Policies', path: '/policies?status=0&draft=1' },
      { label: 'Create DOM/COM Policy', path: '/policies/create' },
      { label: 'MIS Renew Policy', path: '/policies/renew' },
      { label: 'DOM/COM Batch Renew', path: '/policies/dom-com-batch-renew', role: ['admin', 'Super Admin'] },
      { label: 'Renewal Dashboard', path: '/renewals/dashboard' },
      { label: 'Cancel Requests', path: '/policies/cancel-requests', permission: 'cancelpolicyrequest_list' },
      { label: 'Soft Delete Item', path: '/policies/soft-delete', role: ['Super Admin'] },
    ],
  },
  {
    label: 'Claims',
    icon: <ClipboardIcon />,
    permission: 'claim-list',
    children: [
      { label: 'All Claims', path: '/claims' },
      // FNOL Intake — First Notification of Loss. Hidden unless the
      // `claims_fnol` flag is ON and the user holds a claims-handling role
      // (matches CLAIMS_FNOL_ROLES + the flag gate re-checked inside each page).
      { label: 'FNOL Intake', path: '/claims/fnol', role: ['Claims Team', 'Claims Manager', 'Admin', 'admin', 'Super Admin'], flag: 'claims_fnol' },
      // Claims dashboard — CORE claims nav (not a dark flag). Role-gated to any
      // claims role plus finance / underwriting / exec read. The page itself
      // re-checks access and degrades SLA panels for non-managers.
      { label: 'Dashboard', path: '/claims/dashboard', role: ['Claims Team', 'Claims Manager', 'Admin', 'admin', 'Super Admin', 'Finance', 'Finance Claims Viewer', 'Underwriting', 'CXO', 'CEO', 'CFO'] },
      { label: 'Create Claim', path: '/claims/create', permission: 'claim-create' },
      // Claims SLA dashboard — hidden unless the `claims_sla` flag is ON and the
      // user is a Claims Manager / admin (matches the backend manager guard).
      { label: 'SLA Dashboard', path: '/claims/sla', role: ['Claims Manager', 'Super Admin', 'Admin'], flag: 'claims_sla' },
      // Notifications dashboard — hidden unless the `claims_notifications` flag
      // is ON and the user is a Claims Manager / admin (matches the backend
      // controller role guard). Preview-only while it ships dark.
      { label: 'Notifications', path: '/claims/notifications', role: ['Claims Manager', 'Super Admin', 'Admin'], flag: 'claims_notifications' },
      // Premium Confirmation Tracker — measures Finance against the 24-hour
      // release. Hidden unless the `premium_confirmation` flag is ON. Finance
      // needs it as much as Claims do, so both roles see it.
      { label: 'Premium Confirmations', path: '/claims/premium-confirmations', role: ['Claims Manager', 'Finance', 'Manager', 'Super Admin', 'Admin'], flag: 'premium_confirmation' },
      // Incentive report — hidden unless the `claims_incentive_report` flag is
      // ON and the user is a Claims Manager / admin (matches the backend route
      // role guard).
      { label: 'Incentive Report', path: '/claims/incentive', role: ['Claims Manager', 'Admin', 'Super Admin'], flag: 'claims_incentive_report' },
      // Analytics — charts over existing claims data; any claims-handling role.
      { label: 'Analytics', path: '/claims/analytics', role: ['Claims Manager', 'Claims Team', 'Admin', 'Super Admin'] },
      // Master Data — reference lists for the Claims module (Claims-Tracker
      // migration). Admin / Super Admin only; the page re-checks access.
      { label: 'Master Data', path: '/claims/master-data', role: ['Admin', 'admin', 'Super Admin'] },
      // Backdate Control — admin governance of claim stage-date backdating
      // (Claims-Tracker port). Preview-capable: shown to Admin / Super Admin
      // ALWAYS (no flag gate) so they can pre-configure; enforcement itself is
      // flag-gated server-side. The page re-checks access + shows a preview
      // banner while the flag is off.
      { label: 'Backdate Control', path: '/claims/backdate-control', role: ['Admin', 'admin', 'Super Admin', 'developer'] },
      // Claim Forms — the Claims Document Generator (7 standard correspondence
      // forms, static/client-side, print to PDF). Dark until the `claims_docs`
      // flag is ON; visible to claims-handling roles + admins. No backend.
      { label: 'Claim Forms', path: '/claims/forms', role: ['Claims Team', 'Claims Manager', 'Admin', 'admin', 'Super Admin'], flag: 'claims_docs' },
      // Report Schedules — Claims Admin scheduled KPI email reports. Hidden
      // unless the `claims_scheduled_reports` flag is ON and the user is an
      // admin (matches the backend route role guard + MANAGE_ROLES in the
      // page — admin-only, a Claims Manager cannot change the exec
      // distribution). Manageable while dark; sending is flag-gated
      // server-side.
      { label: 'Report Schedules', path: '/claims/report-schedules', role: ['Admin', 'admin', 'Super Admin'], flag: 'claims_scheduled_reports' },
      // Bulk Import — Claims Admin bulk claim import (analyze / preview / commit).
      // Hidden unless the `claims_bulk_import` flag is ON and the user is a
      // Claims Manager / admin (matches the backend route role guard +
      // MANAGE_ROLES in the page). Commit is flag-gated server-side.
      { label: 'Bulk Import', path: '/claims/bulk-import', role: ['Claims Manager', 'Admin', 'admin', 'Super Admin'], flag: 'claims_bulk_import' },
    ],
  },
  {
    label: 'Quotes',
    path: '/quotes',
    icon: <TagIcon />,
    permission: 'quote-list',
  },
  {
    label: 'Leads',
    path: '/leads',
    icon: <UsersIcon />,
    permission: 'lead-list',
  },

  // ─── BATCH OPERATIONS ───────────────────────────
  {
    label: 'Batch Processing',
    icon: <LayersIcon />,
    section: 'Batch Operations',
    permission: 'bonupolicy_list',
    children: [
      { label: 'Batch Policy Create', path: '/batch/create', permission: 'bonupolicy_create' },
      { label: 'Batch Policy Cancel', path: '/batch/cancel', permission: 'bonupolicy_cancel' },
      { label: 'Batch Report', path: '/batch/report' },
    ],
  },
  {
    label: 'AD Group Processing',
    icon: <BuildingIcon />,
    permission: 'ad-group-batch-policy_processing',
    children: [
      { label: 'Group Policy', path: '/policies/group', permission: 'bonupolicy_list' },
      { label: 'Employer Groups', path: '/ad-group/employer-groups' },
      // V8 sidebar parity: dedicated "Create Employer Group" entry.
      { label: 'Add Employer Group', path: '/ad-group/employer-groups/new', permission: 'employer-group-create' },
      { label: 'AD Group Policy', path: '/ad-group/policies' },
      { label: 'Batch Create', path: '/ad-group/batch-create' },
    ],
  },

  // ─── CUSTOMERS & KYC ────────────────────────────
  {
    label: 'Customers',
    icon: <UsersIcon />,
    section: 'Customers',
    permission: 'customer-list',
    children: [
      { label: 'All Customers', path: '/customers' },
      { label: 'Customer Search', path: '/customers/search' },
      { label: 'Block List', path: '/customers/block-list', permission: 'block_list' },
      { label: 'Change Customer', path: '/customers/change-policy' },
      { label: 'Company Master', path: '/master/companies' },
    ],
  },

  // ─── UNION GROUP SCHEME ──────
  // Gated by `view_unions` ALONE — matching the backend, where every
  // /api/v1/unions route checks `permission:view_unions` and no role. The
  // permission ships granted to Super Admin only (see
  // 2026_07_25_000012_seed_union_permissions), so the default is unchanged;
  // but granting view_unions to another role/user in Roles & Permissions now
  // actually reveals the menu. Previously an extra `role: 'Super Admin'` was
  // AND-ed on here, so granted users saw nothing.
  {
    label: 'Unions',
    icon: <BuildingIcon />,
    section: 'Unions',
    permission: 'view_unions',
    children: [
      { label: 'Union Management', path: '/unions' },
      { label: 'Members', path: '/unions/members' },
    ],
  },
  {
    label: 'Customer KYC',
    icon: <IdCardIcon />,
    permission: 'customer-kyc-list',
    children: [
      { label: 'KYC List', path: '/kyc' },
      { label: 'Employer Group KYC', path: '/kyc/employer-group' },
      { label: 'Re-KYC', path: '/kyc/re-kyc' },
      { label: 'AD Group KYC', path: '/kyc/ad-group' },
      { label: 'Duplicate Customers', path: '/kyc/duplicates' },
      { label: 'Deduplication', path: '/kyc/deduplication' },
      { label: 'KYC Compliance Rules', path: '/kyc/compliance-rules' },
      { label: 'KYC Fields', path: '/kyc/fields' },
      { label: 'KYC Access Report', path: '/kyc/access-report', permission: 'kyc-access-report' },
    ],
  },

  // ─── COMMUNICATIONS ──────────────────────────────
  {
    label: 'Communications',
    icon: <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>,
    section: 'Communications',
    children: [
      { label: 'Notification Templates', path: '/communications/templates' },
      { label: 'SMS / Email Templates', path: '/communications/sms-email-templates' },
      { label: 'WhatsApp Templates',   path: '/communications/whatsapp-templates' },
      { label: 'Delivery Logs', path: '/communications/logs' },
    ],
  },

  // ─── AGENTS & STAFF ─────────────────────────────
  // Users + Roles & Permissions moved out to a dedicated "Access Control"
  // section below — the old layout had Users duplicated across both this
  // group and SYSTEM, and the RBAC pages were scattered across three
  // top-level sections (Agents, System, Policy Setup). One canonical
  // home now.
  {
    label: 'Agents & Staff',
    icon: <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>,
    section: 'Agents',
    role: ['admin', 'Super Admin'],
    children: [
      { label: 'Agents', path: '/agents' },
      { label: 'Agencies', path: '/agencies' },
      { label: 'Agent Logins', path: '/agent-logins' },
      { label: 'Partner Companies', path: '/partner-companies', permission: 'partner-company-list' },
    ],
  },

  // ─── INSPECTIONS ────────────────────────────────
  {
    label: 'Vehicle Preinspection',
    path: '/preinspection/vehicle',
    icon: <CarIcon />,
    section: 'Inspections',
    permission: 'customer-preinspection-list',
  },
  {
    label: 'Device Preinspection',
    path: '/preinspection/device',
    icon: <SmartphoneIcon />,
    permission: 'device-preinspection-list',
  },

  // ─── ACCOUNTING ─────────────────────────────────
  {
    label: 'Accounting',
    icon: <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>,
    section: 'Accounting',
    children: [
      { label: 'Chart of Accounts', path: '/accounting/accounts' },
      { label: 'Accounting Rules', path: '/accounting/rules' },
      { label: 'Sub Ledger', path: '/accounting/sub-ledger' },
      { label: 'Settlement Reconciliation', path: '/finance/settlement-reconciliation' },
      // Customer Refund Engine — intake→review→approve; money leg via Omni.
      // Visible to anyone holding a refund role (all roles carry refund-report);
      // the backend re-scopes every request by refund_area_mis / refund_area_dc.
      { label: 'Customer Refunds', path: '/finance/refund-engine', permission: 'refund-report' },
      // Finance review-and-post queue: Credit Note entries prepared by paid
      // return-premium refunds — nothing auto-posts (CFO 2026-07-26).
      { label: 'Refund Accounting', path: '/finance/refund-engine/accounting', permission: 'refund-accounting-post' },
    ],
  },

  // ─── CLAIMS SUPPORT ────────────────────────────
  {
    label: 'Claims Support',
    icon: <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>,
    section: 'Claims Support',
    children: [
      { label: 'Suppliers', path: '/suppliers' },
      { label: 'Repair Centers', path: '/repair-centers' },
      { label: 'Activation Codes', path: '/activation-codes' },
      // Purchase Orders — doorway to Omni's PO board (CFO spec 12 Aug 2026:
      // Omni OWNS POs, Graphite gives access; never a second copy). Ships
      // DARK: the item exists only when VITE_OMNI_URL is configured at build
      // time, so merging this changes nothing until the Phase-0 access gate
      // passes and the env var is deliberately added to the build.
      ...(import.meta.env.VITE_OMNI_URL
        ? [{ label: 'Purchase Orders', href: `${import.meta.env.VITE_OMNI_URL}/purchase-orders` }]
        : []),
    ],
  },

  // ─── PAYMENTS ───────────────────────────────────
  {
    label: 'Payments',
    icon: <WalletIcon />,
    section: 'Payments',
    children: [
      { label: 'Cancel Schedule', path: '/payments/cancel-schedule' },
      { label: 'Offline Payment', path: '/payments/offline' },
      { label: 'Discount / Surcharge', path: '/payments/discount-surcharge' },
      { label: 'DPO Payments', path: '/payments/dpo', permission: 'policy-pay_with_dpo' },
      { label: 'RealPay Transactions', path: '/payments/realpay' },
      { label: 'Orange Money', path: '/payments/orange-money' },
      { label: 'RealPay Contracts', path: '/payments/realpay-contracts' },
      { label: 'Payment Vendors', path: '/payments/vendors' },
    ],
  },

  // ─── RECONCILIATION & ANOMALIES ───────────────
  {
    label: 'Reconciliation',
    icon: <ClipboardIcon />,
    section: 'Reconciliation',
    permission: 'reconciliation-list',
    children: [
      { label: 'Dashboard',                 path: '/reconciliation' },
      { label: 'Exceptions',                path: '/finance/exceptions', permission: 'view_exceptions' },
      { label: 'Reconciliation Anomalies',  path: '/reconciliation/anomalies' },
      { label: 'Operational Anomalies',     path: '/anomalies' },
      { label: 'Consent Compliance',        path: '/consents' },
    ],
  },

  // ─── MASTER DATA ────────────────────────────────
  {
    label: 'Products & Plans',
    path: '/products',
    icon: <DocIcon />,
    section: 'Master Data',
    permission: 'products-list',
  },
  {
    label: 'Coverage Management',
    icon: <ShieldIcon />,
    section: 'Master Data',
    children: [
      { label: 'Coverages', path: '/master/coverages' },
      { label: 'Sub Coverages', path: '/master/sub-coverages' },
      { label: 'Extensions, Excess & Misc', path: '/master/extensions' },
      { label: 'Specified Coverage Items', path: '/master/specified-coverage-items' },
    ],
  },

  // ─── EXCEL IMPORTS ──────────────────────────────
  {
    label: 'Excel Imports',
    icon: <SpreadsheetIcon />,
    section: 'Imports',
    permission: 'excel-import',
    children: [
      { label: 'Policy Activation', path: '/imports/policy-activation' },
      { label: 'Policy Cancellation', path: '/imports/policy-cancellation' },
      { label: 'RealPay Settlement', path: '/imports/realpay-settlement' },
      { label: 'DPO Refund', path: '/imports/dpo-refund', permission: 'DpoRefundByExcel' },
      { label: 'Activate & Cancel Report', path: '/imports/activate-cancel-report' },
      { label: 'NGenius Import', path: '/imports/ngenius' },
    ],
  },

  // ─── COMPLIANCE ─────────────────────────────────
  {
    label: 'Compliance',
    icon: <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>,
    section: 'Compliance',
    children: [
      { label: 'Complaints Register', path: '/compliance/complaints' },
      { label: 'High Risk Countries', path: '/compliance/high-risk-countries' },
    ],
  },

  // ─── ALPHA BRAIN ────────────────────────────────
  // Opens the standalone Neural Console (/alpha-brain/) in the SAME window. It's
  // a same-origin static page that reads the Sanctum session + calls the RBAC
  // proxy (/api/v1/brain/*); it carries its own "Back to Graphite" nav.
  // Visible to ALL employees (CFO 26 Jul): the page shows the counts-only
  // summary to everyone; the customer-level queue inside is gated server-side by
  // `brain-queue` (Finance/Compliance/UW/Claims + named users) and the page
  // degrades gracefully to summary-only for anyone without it. So no nav gate.
  {
    label: 'Alpha Brain',
    href: '/alpha-brain/',
    sameTab: true,
    icon: <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" /></svg>,
    section: 'Alpha Brain',
  },

  // ─── REWARDS ───────────────────────────────────
  {
    label: 'Customer Rewards',
    icon: <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" /></svg>,
    section: 'Rewards',
    children: [
      { label: 'Reward Tiers & Benefits', path: '/rewards/tiers' },
    ],
  },

  // ─── REINSURANCE ────────────────────────────────
  {
    label: 'Reinsurance',
    icon: <DiamondIcon />,
    section: 'Reinsurance',
    children: [
      { label: 'Reinsurance Type', path: '/reinsurance/types', permission: 'reinsurance-type-list' },
      { label: 'Coverage Grouping', path: '/reinsurance/coverage-groups', permission: 'reinsurance-coverage-group-list' },
      { label: 'Formula', path: '/reinsurance/formulas', permission: 'reinsurance-formula-list' },
      { label: 'Treaty', path: '/reinsurance/treaties', permission: 'reinsurance-treaty-list' },
      { label: 'Reinsurers', path: '/reinsurance/reinsurers' },
      { label: 'FAC Register', path: '/reinsurance/fac', permission: 'reinsurance-fac-list' },
      { label: 'FAC Bordereau', path: '/reinsurance/fac/bordereau', permission: 'reinsurance-fac-list' },
      { label: 'FAC Settlements', path: '/reinsurance/fac/settlements', permission: 'reinsurance-fac-settle' },
    ],
  },

  // ─── COMMISSION ──────────────────────────────────
  {
    label: 'Commission',
    icon: <WalletIcon />,
    section: 'Commission',
    children: [
      { label: 'Dashboard', path: '/commission' },
      { label: 'Commission Rules', path: '/commission/rules' },
      { label: 'Targets & Bonuses', path: '/commission/targets' },
      { label: 'Commission Ledger', path: '/commission/ledger' },
      { label: 'Fraud Alerts', path: '/commission/fraud-alerts' },
    ],
  },

  // ─── ACCESS CONTROL ──────────────────────────────
  // One canonical home for everything RBAC. Previously the team had to
  // touch four screens scattered across Agents / System / Policy Setup
  // to give a new hire the right permissions; now all five live here
  // with a single shield icon. Page paths unchanged for back-compat.
  {
    label: 'Access Control',
    icon: <ShieldIcon />,
    section: 'Access Control',
    children: [
      { label: 'Users',                  path: '/users',                       permission: 'user-list' },
      { label: 'Roles & Permissions',    path: '/roles' },
      { label: 'UW Rules',               path: '/policy-validation/rules',     permission: 'validation-rule-list' },
      { label: 'UW Bands',               path: '/policy-validation/groups',   permission: 'validation-rule-group-list' },
      { label: 'Role ↔ UW Band Mapping', path: '/policy-validation/role-groups' },
    ],
  },
  // Reports previously sat under SYSTEM (inheriting from Users' section
  // header). Users moved into Access Control — make Reports' section
  // explicit so the SYSTEM header still renders.
  {
    label: 'Reports',
    path: '/reports',
    icon: <ClipboardIcon />,
    section: 'System',
    permission: 'reports',
  },
  {
    label: 'Underwriting',
    path: '/underwriting',
    icon: <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>,
    section: 'Underwriting',
  },
  {
    label: 'Smart Upload',
    path: '/underwriting/smart-upload',
    icon: <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 16a4 4 0 01-.88-7.9A5 5 0 1115.9 6.1 4.5 4.5 0 0117 15M12 12v6m0-6l-2 2m2-2l2 2" /></svg>,
    section: 'Underwriting',
  },
  {
    label: 'Inflation Rates',
    path: '/admin/inflation-rates',
    icon: <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>,
    section: 'Underwriting',
    // No sidebar role gate, same as the other master-data screens (Assessors,
    // Lawyers): the API is gated (Admin|admin|Super Admin|Underwriter), and an
    // exact role-name match here silently hides the menu from anyone whose role
    // is spelled differently — e.g. a 'developer' account.
  },
  {
    label: 'Audit Trail',
    path: '/audit-trail',
    icon: <HistoryIcon />,
  },
  {
    label: 'Regions & Departments',
    path: '/admin/regions-departments',
    icon: <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>,
    section: 'Admin',
  },
  {
    label: 'Assessors',
    path: '/admin/assessors',
    icon: <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>,
    section: 'Admin',
  },
  {
    label: 'Lawyers',
    path: '/admin/lawyers',
    icon: <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 21h18M12 3l8 4-8 4-8-4 8-4zM6 10v6m12-6v6M4 21v-2h16v2" /></svg>,
    section: 'Admin',
  },
  {
    label: 'States & Cities',
    path: '/admin/geography',
    icon: <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>,
    section: 'Admin',
    role: ['Admin', 'admin', 'Super Admin'],
  },
  {
    label: 'Integrations',
    path: '/admin/integrations',
    icon: <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>,
    section: 'Admin',
    role: ['admin', 'Super Admin', 'developer'],
  },
  {
    label: 'Alpha Transit',
    path: '/admin/alpha-transit',
    icon: <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1" /></svg>,
    section: 'Admin',
    role: ['admin', 'Super Admin', 'developer'],
  },
  {
    label: 'Cron Portal',
    path: '/system/cron-portal',
    icon: <span className="w-5 h-5 flex items-center justify-center text-base leading-none">⚙️</span>,
  },
  {
    label: 'Cron Daily Activity',
    path: '/system/cron-daily-activity',
    icon: <span className="w-5 h-5 flex items-center justify-center text-base leading-none">📅</span>,
  },
  {
    label: 'Application Logs',
    path: '/system/cron-logs',
    icon: <span className="w-5 h-5 flex items-center justify-center text-base leading-none">📜</span>,
    role: ['admin', 'Super Admin', 'developer', 'dev_log_viewer'],
  },
  {
    label: 'Report Config',
    path: '/system/report-config',
    icon: <span className="w-5 h-5 flex items-center justify-center text-base leading-none">📊</span>,
  },
  {
    label: 'Document Jobs',
    path: '/system/document-jobs',
    icon: <span className="w-5 h-5 flex items-center justify-center text-base leading-none">📄</span>,
  },
  {
    label: 'Storage Status',
    path: '/system/storage-status',
    icon: <span className="w-5 h-5 flex items-center justify-center text-base leading-none">💾</span>,
  },
  {
    label: 'Policy Wordings',
    path: '/system/wordings',
    icon: <span className="w-5 h-5 flex items-center justify-center text-base leading-none">📚</span>,
  },
  // Bounded from → to endorsement refresh — Super Admin / Admin only.
  // Backend re-checks the role on every call.
  {
    label: 'Refresh Endorsement Range',
    path: '/system/refresh-endorse-range',
    icon: <span className="w-5 h-5 flex items-center justify-center text-base leading-none">🔁</span>,
    role: ['admin', 'Super Admin'],
  },
  // GRA-0155 — self-service download of the daily Infobip SMS-log exports.
  // Permission-gated (per-user delegable via Roles & Permissions), matching the
  // backend `permission:sms-logs-download` route middleware.
  {
    label: 'SMS Log Exports',
    path: '/system/sms-exports',
    icon: <span className="w-5 h-5 flex items-center justify-center text-base leading-none">✉️</span>,
    permission: 'sms-logs-download',
  },

  // ─── SUPPORT ────────────────────────────────────
  // Help is intentionally ungated (no permission/role) so every user can
  // reach the module guides.
  {
    label: 'Help & Guides',
    path: '/help',
    section: 'Support',
    icon: <span className="w-5 h-5 flex items-center justify-center text-base leading-none">❓</span>,
  },
  // Help Desk lives in Alpha Bridge post-cutover (2026-07) — opens in a NEW
  // tab; users authenticate to Bridge with the same Azure SSO tenant. The
  // old native Archive + SLA Dashboard entries were removed 2026-07-14: they
  // showed pre-migration snapshots that diverge from Bridge (the live source
  // of truth). Create via the Report an Issue widget; track in Bridge.
  {
    label: 'Help Desk',
    href: ALPHA_BRIDGE_BOARD_URL,
    icon: <span className="w-5 h-5 flex items-center justify-center text-base leading-none">🎧</span>,
  },
]

interface SidebarProps {
  collapsed: boolean
  onToggle: () => void
  permissions: string[]
  roles: string[]
  /** Drawer open state — only meaningful below Tailwind `md`. */
  mobileOpen?: boolean
  /** Close the mobile drawer (backdrop click / Escape / nav-item click). */
  onMobileClose?: () => void
  /** Element to return focus to when the drawer closes (the Header hamburger). */
  returnFocusRef?: RefObject<HTMLElement>
}

export default function Sidebar({
  collapsed,
  onToggle,
  permissions,
  roles,
  mobileOpen = false,
  onMobileClose,
  returnFocusRef,
}: SidebarProps) {
  // Search/filter — the menu has 20+ sections and >50 items now, scrolling
  // through it to find "Role ↔ UW Band Mapping" or "Compliance Alerts" is
  // a real friction point. Match both top-level labels and children labels;
  // when a child matches we keep its parent in the result.
  const [search, setSearch] = useState('')
  const q = search.trim().toLowerCase()

  // Runtime feature flags (auth-only, cached). Items carrying a `flag` are
  // hidden until that integration is enabled — lets flagged features (e.g. the
  // Claims SLA dashboard) ship dark without touching the static menu.
  const { data: integrations } = useIntegrations()
  const enabledFlags = useMemo(
    () => new Set((integrations?.data ?? []).filter((i) => i.enabled).map((i) => i.integration)),
    [integrations],
  )

  // Scroll-shadow fades: a soft navy gradient at the top/bottom edges of the
  // nav that signals "more above / below" and clears at the ends. Driven by
  // scroll + resize + a MutationObserver, so it also updates the moment a menu
  // group expands or collapses (which changes the scroll height). No scrollbar.
  const navRef = useRef<HTMLElement>(null)
  // The drawer panel itself — used for the mobile focus trap + initial focus.
  const asideRef = useRef<HTMLElement>(null)
  const [fadeTop, setFadeTop] = useState(false)
  const [fadeBottom, setFadeBottom] = useState(false)
  useEffect(() => {
    const el = navRef.current
    if (!el) return
    const update = () => {
      const { scrollTop, scrollHeight, clientHeight } = el
      setFadeTop(scrollTop > 4)
      setFadeBottom(scrollTop + clientHeight < scrollHeight - 4)
    }
    update()
    el.addEventListener('scroll', update, { passive: true })
    window.addEventListener('resize', update)
    const ro = new ResizeObserver(update); ro.observe(el)
    const mo = new MutationObserver(update); mo.observe(el, { childList: true, subtree: true })
    return () => {
      el.removeEventListener('scroll', update)
      window.removeEventListener('resize', update)
      ro.disconnect()
      mo.disconnect()
    }
  }, [collapsed])

  // ── Mobile drawer behaviour (below md only) ─────────────────────────────
  // Escape closes the drawer and returns focus to the hamburger; focus is also
  // trapped inside the open panel; opening moves focus into the panel. All of
  // this is inert at md+ because the drawer is never "open" there (the Header
  // hamburger that sets mobileOpen is display:none at md+).
  useEffect(() => {
    if (!mobileOpen) return
    const panel = asideRef.current
    if (!panel) return

    const focusableSelector =
      'a[href], button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'

    // Move focus into the panel (first focusable, else the panel itself).
    const first = panel.querySelector<HTMLElement>(focusableSelector)
    ;(first ?? panel).focus()

    function onKey(e: KeyboardEvent) {
      if (e.key === 'Escape') {
        e.stopPropagation()
        onMobileClose?.()
        return
      }
      if (e.key !== 'Tab') return
      // Focus trap — keep Tab cycling within the panel.
      const focusables = Array.from(
        panel!.querySelectorAll<HTMLElement>(focusableSelector)
      ).filter((el) => el.offsetParent !== null || el === document.activeElement)
      if (focusables.length === 0) {
        e.preventDefault()
        panel!.focus()
        return
      }
      const firstEl = focusables[0]
      const lastEl = focusables[focusables.length - 1]
      const active = document.activeElement as HTMLElement | null
      if (e.shiftKey) {
        if (active === firstEl || active === panel) {
          e.preventDefault()
          lastEl.focus()
        }
      } else if (active === lastEl) {
        e.preventDefault()
        firstEl.focus()
      }
    }
    document.addEventListener('keydown', onKey, true)
    return () => document.removeEventListener('keydown', onKey, true)
  }, [mobileOpen, onMobileClose])

  // Return focus to the hamburger after the drawer closes (was open → now
  // closed). Guard on a ref so we don't steal focus on the very first render.
  const wasOpenRef = useRef(false)
  useEffect(() => {
    if (wasOpenRef.current && !mobileOpen) {
      returnFocusRef?.current?.focus()
    }
    wasOpenRef.current = mobileOpen
  }, [mobileOpen, returnFocusRef])

  const filteredItems: MenuItem[] = q.length === 0
    ? MENU_ITEMS
    : MENU_ITEMS
        .map((item) => {
          const parentMatch = item.label.toLowerCase().includes(q)
          const matchedChildren = item.children?.filter((c) =>
            c.label.toLowerCase().includes(q)
          )
          if (parentMatch) {
            // Parent matched — show with ALL its children so user can browse
            return item
          }
          if (matchedChildren && matchedChildren.length > 0) {
            // Some children matched — show only those
            return { ...item, children: matchedChildren }
          }
          return null
        })
        .filter((x): x is MenuItem => x !== null)

  let lastSection: string | undefined

  return (
    <>
      {/* Mobile backdrop — sits just under the drawer panel (both z-drawer, panel
          wins by DOM order). Below md only; click closes. Hidden/inert at md+. */}
      <div
        className={`fixed inset-0 z-drawer bg-black/50 motion-safe:transition-opacity motion-safe:duration-300 md:hidden ${
          mobileOpen ? 'opacity-100' : 'pointer-events-none opacity-0'
        }`}
        aria-hidden="true"
        onClick={onMobileClose}
      />
      <aside
        ref={asideRef}
        id="app-sidebar"
        tabIndex={-1}
        role="navigation"
        aria-label="Main navigation"
        aria-hidden={undefined}
        className={`fixed ${IS_TEST_ENV ? 'top-10 h-[calc(100vh-2.5rem)]' : 'top-0 h-screen'} left-0 z-drawer bg-brand-navy text-white flex flex-col outline-none transition-transform motion-reduce:transition-none duration-300 md:transition-all ${
          mobileOpen ? 'translate-x-0' : '-translate-x-full'
        } md:translate-x-0 ${
          collapsed ? 'w-[var(--sidebar-w-collapsed)]' : 'w-[var(--sidebar-w)]'
        }`}
      >
      {/* Logo / brand — full-width lockup like the Reporting portal */}
      <div className={`shrink-0 border-b border-white/10 ${collapsed ? 'px-2 pt-3 pb-2.5 flex justify-center' : 'px-4 pt-3 pb-2.5'}`}>
        {collapsed ? (
          <div className="w-8 h-8 rounded-lg bg-brand-orange flex items-center justify-center font-extrabold text-white text-sm select-none">AD</div>
        ) : (
          <>
            <img src="/logo.svg" alt="Alpha Direct Insurance Co." className="w-full object-contain" style={{ mixBlendMode: 'screen' }} />
            <p className="text-center text-[10px] font-semibold text-white/40 uppercase tracking-[0.2em] mt-1">Operations Portal</p>
          </>
        )}
      </div>

      {/* Search — hidden when collapsed so the icon-only sidebar stays compact */}
      {!collapsed && (
        <div className="shrink-0 px-3 py-2 border-b border-white/10">
          <div className="relative">
            <svg
              className="w-4 h-4 absolute left-2 top-1/2 -translate-y-1/2 text-white/60 pointer-events-none"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z" />
            </svg>
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search menu…"
              className="w-full pl-8 pr-7 py-2 text-xs rounded-lg bg-white/10 border border-white/20 text-white placeholder-white/60 focus:outline-none focus:ring-1 focus:ring-brand-orange focus:border-brand-orange"
            />
            {search && (
              <button
                type="button"
                onClick={() => setSearch('')}
                className="absolute right-2 top-1/2 -translate-y-1/2 text-white/40 hover:text-white text-xs"
                aria-label="Clear search"
              >
                ×
              </button>
            )}
          </div>
        </div>
      )}

      {/* Menu */}
      <div className="relative flex-1 min-h-0">
        {/* top scroll-shadow */}
        <div className={`pointer-events-none absolute inset-x-0 top-0 h-4 z-10 bg-gradient-to-b from-brand-navy to-transparent transition-opacity duration-200 ${fadeTop ? 'opacity-100' : 'opacity-0'}`} />
        <nav ref={navRef} className="h-full py-4 overflow-y-auto overflow-x-hidden nav-scroll">
        <ul className="space-y-0.5 px-2">
          {filteredItems.length === 0 ? (
            <li className="px-2 py-3 text-xs text-white/40 italic">No matches.</li>
          ) : (
            filteredItems.map((item) => {
              const showSection = item.section && item.section !== lastSection
              if (item.section) lastSection = item.section

              return (
                <SidebarItem
                  key={item.label}
                  item={item}
                  collapsed={collapsed}
                  permissions={permissions}
                  roles={roles}
                  enabledFlags={enabledFlags}
                  sectionLabel={showSection ? item.section : undefined}
                  forceExpand={q.length > 0 && (item.children?.length ?? 0) > 0}
                  onNavClick={onMobileClose}
                />
              )
            })
          )}
        </ul>
        </nav>
        {/* bottom scroll-shadow */}
        <div className={`pointer-events-none absolute inset-x-0 bottom-0 h-5 z-10 bg-gradient-to-t from-brand-navy to-transparent transition-opacity duration-200 ${fadeBottom ? 'opacity-100' : 'opacity-0'}`} />
      </div>

      {/* Collapse toggle — bottom bar (matches the Reporting portal) */}
      <div className={`shrink-0 border-t border-white/10 px-3 py-2 flex ${collapsed ? 'justify-center' : 'justify-end'}`}>
        <button
          onClick={onToggle}
          className="flex h-7 w-7 items-center justify-center rounded-md text-white/50 hover:bg-white/10 hover:text-white transition"
          title={collapsed ? 'Expand' : 'Collapse'}
          aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
          aria-expanded={!collapsed}
        >
          <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            {collapsed ? (
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 5l7 7-7 7M5 5l7 7-7 7" />
            ) : (
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
            )}
          </svg>
        </button>
      </div>
      </aside>
    </>
  )
}

function SidebarItem({
  item,
  collapsed,
  permissions,
  roles,
  enabledFlags,
  sectionLabel,
  forceExpand,
  onNavClick,
}: {
  item: MenuItem
  collapsed: boolean
  permissions: string[]
  roles: string[]
  enabledFlags: Set<string>
  sectionLabel?: string
  /** When true (search active + this item has matches), keep group expanded
      so user sees the filtered children without an extra click. */
  forceExpand?: boolean
  /** Called when a leaf link is clicked — closes the mobile drawer (covers the
      same-route click case that the route-change effect can't see). */
  onNavClick?: () => void
}) {
  const location = useLocation()
  const [open, setOpen] = useState(() => {
    if (!item.children) return false
    return item.children.some((c) => !!c.path && location.pathname.startsWith(c.path.split('?')[0]))
  })

  // When the user types in the search box, parents with matching children
  // should expand automatically — otherwise the menu just shows collapsed
  // headers and the filter result is invisible.
  const isOpen = forceExpand ? true : open

  // Permission check
  if (item.permission && !permissions.includes(item.permission)) return null
  if (item.role) {
    const required = Array.isArray(item.role) ? item.role : [item.role]
    if (!required.some((r) => roles.includes(r))) return null
  }
  if (item.flag && !enabledFlags.has(item.flag)) return null

  const sectionHeader = sectionLabel && !collapsed ? (
    <li className="pt-4 pb-1 px-3 first:pt-0">
      <span className="text-[10px] font-bold text-white/40 uppercase tracking-widest">{sectionLabel}</span>
    </li>
  ) : null

  // Has children → accordion
  if (item.children) {
    const visibleChildren = item.children.filter(
      (c) =>
        (!c.permission || permissions.includes(c.permission)) &&
        (!c.role || (Array.isArray(c.role) ? c.role : [c.role]).some((r) => roles.includes(r))) &&
        (!c.flag || enabledFlags.has(c.flag))
    )
    if (visibleChildren.length === 0) return null

    return (
      <>
        {sectionHeader}
        <li>
          <button
            onClick={() => setOpen(!open)}
            className={`nav-fx flex items-center w-full px-3 py-2 text-xs font-medium rounded-lg text-white/75 hover:bg-white/10 hover:text-white transition ${
              collapsed ? 'justify-center' : 'gap-2.5'
            }`}
            title={collapsed ? item.label : undefined}
          >
            <span className="flex-shrink-0 text-white/80 [&_svg]:w-[17px] [&_svg]:h-[17px]">{item.icon}</span>
            {!collapsed && (
              <>
                <span className="flex-1 text-left truncate">{item.label}</span>
                <svg
                  className={`w-4 h-4 text-white/50 transition-transform ${isOpen ? 'rotate-90' : ''}`}
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                </svg>
              </>
            )}
          </button>
          {isOpen && !collapsed && (
            <ul className="ml-5 mt-0.5 space-y-0.5 border-l border-white/10 pl-3">
              {visibleChildren.map((child) => (
                <li key={child.path ?? child.href}>
                  {child.href ? (
                    <a
                      href={child.href}
                      target="_blank"
                      rel="noopener noreferrer"
                      onClick={onNavClick}
                      className="nav-fx flex items-center min-h-[44px] md:min-h-0 px-3 py-1.5 text-xs font-medium rounded-md transition text-white/70 hover:bg-white/10 hover:text-white"
                    >
                      {child.label}
                    </a>
                  ) : (
                    <NavLink
                      to={child.path ?? '/'}
                      onClick={onNavClick}
                      className={({ isActive }) =>
                        `nav-fx flex items-center min-h-[44px] md:min-h-0 px-3 py-1.5 text-xs font-medium rounded-md transition ${
                          isActive ? 'bg-brand-orange text-white font-medium shadow-sm' : 'text-white/70 hover:bg-white/10 hover:text-white'
                        }`
                      }
                    >
                      {child.label}
                    </NavLink>
                  )}
                </li>
              ))}
            </ul>
          )}
        </li>
      </>
    )
  }

  // External link — plain anchor in a NEW tab (no router, no active state).
  if (item.href) {
    return (
      <>
        {sectionHeader}
        <li>
          <a
            href={item.href}
            target={item.sameTab ? undefined : '_blank'}
            rel={item.sameTab ? undefined : 'noopener noreferrer'}
            onClick={onNavClick}
            className={`nav-fx flex items-center min-h-[44px] md:min-h-0 px-3 py-2 text-xs font-medium rounded-lg transition ${
              collapsed ? 'justify-center' : 'gap-2.5'
            } text-white/75 hover:bg-white/10 hover:text-white`}
            title={collapsed ? item.label : undefined}
          >
            <span className="flex-shrink-0 [&_svg]:w-[17px] [&_svg]:h-[17px]">{item.icon}</span>
            {!collapsed && <span className="truncate">{item.label}</span>}
          </a>
        </li>
      </>
    )
  }

  // Simple link
  return (
    <>
      {sectionHeader}
      <li>
        <NavLink
          to={item.path!}
          end={item.path === '/'}
          onClick={onNavClick}
          className={({ isActive }) =>
            `nav-fx flex items-center min-h-[44px] md:min-h-0 px-3 py-2 text-xs font-medium rounded-lg transition ${
              collapsed ? 'justify-center' : 'gap-2.5'
            } ${isActive ? 'bg-brand-orange text-white shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white'}`
          }
          title={collapsed ? item.label : undefined}
        >
          <span className="flex-shrink-0 [&_svg]:w-[17px] [&_svg]:h-[17px]">{item.icon}</span>
          {!collapsed && <span className="truncate">{item.label}</span>}
        </NavLink>
      </li>
    </>
  )
}

// --- SVG Icons ---

function GridIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="currentColor" className="w-5 h-5">
      <rect x="2" y="2" width="9" height="9" rx="2" />
      <rect opacity="0.3" x="13" y="2" width="9" height="9" rx="2" />
      <rect opacity="0.3" x="13" y="13" width="9" height="9" rx="2" />
      <rect opacity="0.3" x="2" y="13" width="9" height="9" rx="2" />
    </svg>
  )
}

function DocIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="w-5 h-5">
      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
      <polyline points="14,2 14,8 20,8" />
      <line x1="16" y1="13" x2="8" y2="13" />
      <line x1="16" y1="17" x2="8" y2="17" />
    </svg>
  )
}

function TagIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="w-5 h-5">
      <path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z" />
      <line x1="7" y1="7" x2="7.01" y2="7" />
    </svg>
  )
}



function LayersIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="w-5 h-5">
      <polygon points="12 2 2 7 12 12 22 7 12 2" />
      <polyline points="2 17 12 22 22 17" />
      <polyline points="2 12 12 17 22 12" />
    </svg>
  )
}

function BuildingIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="w-5 h-5">
      <rect x="4" y="2" width="16" height="20" rx="2" ry="2" />
      <line x1="9" y1="22" x2="9" y2="2" />
      <line x1="14" y1="2" x2="14" y2="22" />
      <line x1="4" y1="7" x2="20" y2="7" />
      <line x1="4" y1="12" x2="20" y2="12" />
      <line x1="4" y1="17" x2="20" y2="17" />
    </svg>
  )
}

function ClipboardIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="w-5 h-5">
      <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
      <rect x="8" y="2" width="8" height="4" rx="1" ry="1" />
    </svg>
  )
}

function UsersIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="w-5 h-5">
      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
      <circle cx="9" cy="7" r="4" />
      <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
      <path d="M16 3.13a4 4 0 0 1 0 7.75" />
    </svg>
  )
}

function IdCardIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="w-5 h-5">
      <rect x="2" y="4" width="20" height="16" rx="2" />
      <circle cx="8" cy="11" r="2" />
      <path d="M4 18c0-2 2-3 4-3s4 1 4 3" />
      <line x1="15" y1="9" x2="20" y2="9" />
      <line x1="15" y1="13" x2="20" y2="13" />
    </svg>
  )
}

function CarIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="w-5 h-5">
      <path d="M5 17h14a2 2 0 002-2v-3a2 2 0 00-1.17-1.82L18 9l-1.5-4.5A2 2 0 0014.6 3H9.4a2 2 0 00-1.9 1.5L6 9l-1.83 1.18A2 2 0 003 12v3a2 2 0 002 2z" />
      <circle cx="7.5" cy="17" r="2" />
      <circle cx="16.5" cy="17" r="2" />
    </svg>
  )
}

function SmartphoneIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="w-5 h-5">
      <rect x="5" y="2" width="14" height="20" rx="2" ry="2" />
      <line x1="12" y1="18" x2="12.01" y2="18" />
    </svg>
  )
}

function WalletIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="w-5 h-5">
      <rect x="2" y="5" width="20" height="14" rx="2" />
      <path d="M16 14a2 2 0 1 0 0-4 2 2 0 0 0 0 4z" />
      <path d="M2 10h20" />
    </svg>
  )
}

function DiamondIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="currentColor" className="w-5 h-5">
      <path d="M11.293 2.707a1 1 0 0 1 1.414 0l2.586 2.586a1 1 0 0 1 0 1.414l-2.586 2.586a1 1 0 0 1-1.414 0L8.707 6.707a1 1 0 0 1 0-1.414l2.586-2.586Z" />
      <path d="M11.293 14.707a1 1 0 0 1 1.414 0l2.586 2.586a1 1 0 0 1 0 1.414l-2.586 2.586a1 1 0 0 1-1.414 0l-2.586-2.586a1 1 0 0 1 0-1.414l2.586-2.586Z" />
      <path opacity="0.3" d="M5.293 8.707a1 1 0 0 1 1.414 0l2.586 2.586a1 1 0 0 1 0 1.414l-2.586 2.586a1 1 0 0 1-1.414 0L2.707 12.707a1 1 0 0 1 0-1.414l2.586-2.586Z" />
      <path opacity="0.3" d="M17.293 8.707a1 1 0 0 1 1.414 0l2.586 2.586a1 1 0 0 1 0 1.414l-2.586 2.586a1 1 0 0 1-1.414 0l-2.586-2.586a1 1 0 0 1 0-1.414l2.586-2.586Z" />
    </svg>
  )
}

function SpreadsheetIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="w-5 h-5">
      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
      <polyline points="14 2 14 8 20 8" />
      <line x1="8" y1="13" x2="16" y2="13" />
      <line x1="8" y1="17" x2="16" y2="17" />
      <line x1="12" y1="9" x2="12" y2="21" />
    </svg>
  )
}

function HistoryIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="w-5 h-5">
      <circle cx="12" cy="12" r="10" />
      <polyline points="12 6 12 12 16 14" />
    </svg>
  )
}

function ShieldIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="w-5 h-5">
      <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
    </svg>
  )
}

