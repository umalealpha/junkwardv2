#!/usr/bin/env node
/**
 * reconciliation.js
 * ──────────────────
 * Fast payment reconciliation for DOM/COM policies.
 * Checks ledger vs actual payments and flags anomalies.
 *
 * Usage:
 *   node reconciliation.js              → DOM/COM reconciliation
 *   node reconciliation.js --fix        → also auto-fix balance mismatches
 *   node reconciliation.js --dry-run    → report only, no writes
 */

import { query, closePool, pool } from './db.js'

const args     = process.argv.slice(2)
const DRY_RUN  = args.includes('--dry-run')
const AUTO_FIX = args.includes('--fix')
const DOMCOM   = [7, 8, 16,17,18,20,22]

const log  = (msg) => console.log(`[${new Date().toISOString()}] ${msg}`)
const warn = (msg) => console.warn(`[WARN] ${msg}`)
const fmt  = (n)   => `P${Number(n || 0).toLocaleString('en', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`

async function main() {
  log('=== DOM/COM Reconciliation Started ===')
  log(`Mode: ${DRY_RUN ? 'DRY RUN' : AUTO_FIX ? 'LIVE + AUTO-FIX' : 'LIVE (report only)'}`)

  const results = {
    checked:           0,
    ok:                0,
    missing_ledger:    0,
    balance_mismatch:  0,
    overpaid:          0,
    underpaid:         0,
    fixed:             0,
  }

  // ── 1. Get all active DOM/COM issued policies with their ledger & payment totals ──
  log('Fetching policy ledger vs payment data...')
  const rows = await query(`
    SELECT
      p.id           AS policy_id,
      p.policyNumber AS policy_number,
      p.product_id,
      p.customer_id,
      p.premium_freq,
      p.premium      AS policy_premium,
      p.vat          AS policy_vat,
      pa.id          AS action_id,
      pa.status      AS action_status,
      pa.premium     AS action_premium,
      COALESCE(SUM(pl.invoice_amount), 0)  AS invoice_total,
      COALESCE(SUM(CASE WHEN pl.trans_type = 'Receipt' THEN pl.credit ELSE 0 END), 0) AS payment_total,
      COUNT(pl.id)   AS ledger_count,
      SUM(CASE WHEN pl.trans_type = 'Invoice' THEN 1 ELSE 0 END) AS invoice_count
    FROM policies p
    JOIN policy_actions pa ON pa.policy_id = p.id AND pa.deleted_at IS NULL AND pa.status = 'ISSUED'
    LEFT JOIN policy_ledger pl ON pl.policy_id = p.id AND pl.action_id = pa.id AND pl.deleted_at IS NULL
    WHERE p.product_id IN (${DOMCOM.join(',')})
      AND p.status = 1
    GROUP BY p.id, pa.id
    ORDER BY p.id
  `)

  log(`Checking ${rows.length} active DOM/COM policy actions...`)

  const anomalies = []

  for (const row of rows) {
    results.checked++

    // Use ?? (nullish coalescing) so that a genuine 0 premium is respected,
    // not fallen through to policy_premium like || would do.
    const premium  = parseFloat(row.action_premium ?? row.policy_premium ?? 0)
    const vat      = parseFloat(row.policy_vat ?? 0)
    const invoiced = premium + vat
    const paid     = parseFloat(row.payment_total ?? 0)
    const invoiceCount = parseInt(row.invoice_count ?? 0)

    // Check 1: Missing Invoice ledger entry — only flag when premium > 0
    // (policies with premium=0 but non-zero VAT are a data issue, not missing invoice)
    if (invoiceCount === 0 && premium > 0) {
      results.missing_ledger++
      anomalies.push({
        type:          'missing_ledger',
        policy_id:     row.policy_id,
        policy_number: row.policy_number,
        action_id:     row.action_id,
        detail:        `No Invoice ledger entry — premium is ${fmt(invoiced)}`,
        invoiced,
        paid,
      })
      continue
    }

    // Check 2: Overpaid (payment > invoiced by >5%)
    if (paid > 0 && invoiced > 0 && paid > invoiced * 1.05) {
      results.overpaid++
      anomalies.push({
        type:          'overpaid',
        policy_id:     row.policy_id,
        policy_number: row.policy_number,
        action_id:     row.action_id,
        detail:        `Paid ${fmt(paid)} > invoiced ${fmt(invoiced)} (overpaid ${fmt(paid - invoiced)})`,
        invoiced,
        paid,
      })
    }

    // Check 3: Underpaid (payment < invoiced by >5%, policy active)
    if (paid > 0 && invoiced > 0 && paid < invoiced * 0.95) {
      results.underpaid++
      anomalies.push({
        type:          'underpaid',
        policy_id:     row.policy_id,
        policy_number: row.policy_number,
        action_id:     row.action_id,
        detail:        `Paid ${fmt(paid)} < invoiced ${fmt(invoiced)} (shortfall ${fmt(invoiced - paid)})`,
        invoiced,
        paid,
      })
    }

    results.ok++
  }

  // ── 2. Print anomaly report ───────────────────────────────────
  log('\n=== ANOMALY REPORT ===')
  log(`Checked:          ${results.checked}`)
  log(`OK:               ${results.ok}`)
  log(`Missing Ledger:   ${results.missing_ledger}`)
  log(`Overpaid:         ${results.overpaid}`)
  log(`Underpaid:        ${results.underpaid}`)

  if (anomalies.length > 0) {
    log(`\nTop anomalies (max 50):`)
    anomalies.slice(0, 50).forEach(a => {
      log(`  [${a.type.toUpperCase()}] ${a.policy_number} (policy #${a.policy_id}, action #${a.action_id}): ${a.detail}`)
    })
  }

  // ── 3. Auto-fix balance mismatches if --fix passed ───────────
  if (AUTO_FIX && !DRY_RUN) {
    log('\n=== AUTO-FIX: Recalculating ledger balances ===')

    const fixSQL = `
      UPDATE policy_ledger pl
      JOIN (
        SELECT policy_id, action_id,
          SUM(CASE WHEN trans_type = 'Invoice' THEN invoice_amount ELSE 0 END) -
          SUM(CASE WHEN trans_type = 'Receipt' THEN credit ELSE 0 END) AS correct_balance
        FROM policy_ledger
        WHERE deleted_at IS NULL
        GROUP BY policy_id, action_id
      ) calc ON calc.policy_id = pl.policy_id AND calc.action_id = pl.action_id
      SET pl.balance = calc.correct_balance,
          pl.updated_at = NOW()
      WHERE pl.trans_type = 'Invoice'
        AND pl.deleted_at IS NULL
        AND ABS(COALESCE(pl.balance, 0) - calc.correct_balance) > 0.01
    `
    const [result] = await pool.execute(fixSQL)
    results.fixed = result.affectedRows
    log(`Fixed ${results.fixed} balance mismatches`)
  }

  log('\n=== Reconciliation Complete ===')
  log(`Total anomalies: ${anomalies.length}`)

  await closePool()
}

main().catch(e => {
  console.error('FATAL:', e)
  process.exit(1)
})
