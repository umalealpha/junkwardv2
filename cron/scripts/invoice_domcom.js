#!/usr/bin/env node
/**
 * invoice_domcom.js
 * ──────────────────
 * Bulk invoice generation for DOM/COM policies (products 7,8).
 * Creates policy_ledger Invoice entries for ISSUED actions missing them.
 *
 * Usage:
 *   node invoice_domcom.js              → generate missing invoices
 *   node invoice_domcom.js --dry-run    → report only
 *   node invoice_domcom.js --product 7  → specific product
 *   node invoice_domcom.js --since 2025-07-01 → only policies from date
 */

import { query, closePool, pool } from './db.js'

const args       = process.argv.slice(2)
const DRY_RUN    = args.includes('--dry-run')
const prodArg    = args.indexOf('--product')
const sinceArg   = args.indexOf('--since')
// DomCom only. The Specialist family (16-24) is invoiced action-wise by its own
// renew crons (SpecialistMonthlyAutoRenew / SpecialistQuaterlyAutoRenew /
// RenewAnnualSpecialistPolicies), which raise the invoice at renewal time — no
// cron may auto-invoice those products on top. Still reachable explicitly via
// --product <id> for a one-off operator-driven catch-up.
const PRODUCT    = prodArg !== -1 ? [parseInt(args[prodArg + 1])] : [7, 8]
const SINCE_DATE = sinceArg !== -1 ? args[sinceArg + 1] : '2025-07-01'
const BATCH_SIZE = 200

const log = (msg) => console.log(`[${new Date().toISOString()}] ${msg}`)
const fmt = (n)   => Number(n || 0).toFixed(2)

async function main() {
  log('=== DOM/COM Invoice Generator Started ===')
  log(`Products: ${PRODUCT.join(', ')} | Since: ${SINCE_DATE} | Mode: ${DRY_RUN ? 'DRY RUN' : 'LIVE'}`)

  // ── Find ISSUED policy actions without Invoice ledger entries ──
  const missing = await query(`
    SELECT
      pa.id          AS action_id,
      pa.policy_id,
      pa.effective_from,
      pa.premium     AS action_premium,
      p.policyNumber AS policy_number,
      p.customer_id,
      p.product_id,
      p.premium_freq,
      p.premium      AS policy_premium,
      p.vat          AS policy_vat
    FROM policy_actions pa
    JOIN policies p ON p.id = pa.policy_id
    LEFT JOIN policy_ledger pl
      ON pl.policy_id = pa.policy_id
      AND pl.action_id = pa.id
      AND pl.trans_type = 'Invoice'
      AND pl.deleted_at IS NULL
    WHERE pa.status = 'ISSUED'
      AND pa.deleted_at IS NULL
      AND pa.effective_from >= ?
      AND p.product_id IN (${PRODUCT.map(() => '?').join(',')})
      AND p.status = 1
      AND pl.id IS NULL
      AND (pa.premium > 0 OR p.premium > 0)
    ORDER BY pa.policy_id
  `, [SINCE_DATE, ...PRODUCT])

  log(`Found ${missing.length} policy actions missing Invoice ledger entries`)

  if (missing.length === 0) {
    log('Nothing to generate. All invoices are up to date.')
    await closePool()
    return
  }

  // ── Get next invoice number ────────────────────────────────────
  const [lastInv] = await query(`
    SELECT invoice_no FROM policy_ledger
    WHERE invoice_no IS NOT NULL
    ORDER BY id DESC LIMIT 1
  `)
  let nextInvoiceNo = lastInv ? parseInt(lastInv.invoice_no || 0) + 1 : 100001

  let created = 0, errors = 0

  // ── Process in batches ────────────────────────────────────────
  for (let i = 0; i < missing.length; i += BATCH_SIZE) {
    const batch = missing.slice(i, i + BATCH_SIZE)
    const toInsert = []
    const now = new Date().toISOString().slice(0, 19).replace('T', ' ')

    for (const pol of batch) {
      // ?? preserves genuine zero premiums (|| would incorrectly skip them)
      const premium = parseFloat(pol.action_premium ?? pol.policy_premium ?? 0)
      const vat     = parseFloat(pol.policy_vat ?? 0)
      const total   = premium + vat

      if (total <= 0) continue

      toInsert.push([
        pol.policy_id,
        pol.action_id,
        pol.customer_id,
        'Invoice',
        fmt(total),        // invoice_amount
        fmt(premium),      // premium
        fmt(total),        // balance (unpaid = full amount)
        nextInvoiceNo,     // invoice_no
        pol.effective_from,// invoice_date
        now,               // created_at
        now,               // updated_at
      ])
      nextInvoiceNo++
    }

    if (toInsert.length === 0) continue

    if (DRY_RUN) {
      log(`[DRY RUN] Would INSERT ${toInsert.length} Invoice entries (batch ${Math.floor(i / BATCH_SIZE) + 1})`)
      toInsert.forEach(r => log(`  → Policy #${r[0]} | Action #${r[1]} | Amount P${r[4]} | Invoice #${r[7]}`))
      created += toInsert.length
      continue
    }

    try {
      const conn = await pool.getConnection()
      await conn.beginTransaction()
      await conn.query(
        `INSERT INTO policy_ledger
           (policy_id, action_id, customer_id, trans_type, invoice_amount, premium,
            balance, invoice_no, invoice_date, created_at, updated_at)
         VALUES ?`,
        [toInsert]
      )
      await conn.commit()
      conn.release()
      created += toInsert.length
      log(`Batch ${Math.floor(i / BATCH_SIZE) + 1}: Inserted ${toInsert.length} invoices`)
    } catch (e) {
      errors++
      log(`ERROR batch ${Math.floor(i / BATCH_SIZE) + 1}: ${e.message}`)
    }
  }

  log('=== Invoice Generation Complete ===')
  log(`Generated: ${created} | Errors: ${errors}`)

  await closePool()
}

main().catch(e => {
  console.error('FATAL:', e)
  process.exit(1)
})
