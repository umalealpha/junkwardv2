#!/usr/bin/env node
/**
 * ledger_rebuild.js
 * ─────────────────
 * Fast ledger rebuild for DOM/COM policies (product_id IN 7,8,16,17,18,19).
 * Processes all active policies with missing or stale ledger entries.
 *
 * Usage:
 *   node ledger_rebuild.js                  → DOM/COM only
 *   node ledger_rebuild.js --all            → all products
 *   node ledger_rebuild.js --policy 99628   → single policy
 *   node ledger_rebuild.js --dry-run        → no DB writes
 */

import { query, queryBatch, closePool } from './db.js'

const DOMCOM_PRODUCTS = [7, 8, 16,17,18,20,22]
const BATCH_SIZE = 500

const args = process.argv.slice(2)
const DRY_RUN   = args.includes('--dry-run')
const ALL_PRODS = args.includes('--all')
const policyArg = args.indexOf('--policy')
const SINGLE_POLICY = policyArg !== -1 ? parseInt(args[policyArg + 1]) : null

const log = (msg) => console.log(`[${new Date().toISOString()}] ${msg}`)
const fmt = (n) => Number(n || 0).toFixed(2)

// ── VAT rate ──────────────────────────────────────────────────
const VAT_RATE = 0.14

async function main() {
  log('=== Ledger Rebuild Started ===')
  log(`Mode: ${DRY_RUN ? 'DRY RUN (no writes)' : 'LIVE'}`)
  log(`Scope: ${SINGLE_POLICY ? `policy #${SINGLE_POLICY}` : ALL_PRODS ? 'ALL products' : 'DOM/COM only'}`)

  // ── Step 1: Fetch policies needing ledger entries ─────────────
  let policiesSQL = `
    SELECT
      pa.id          AS action_id,
      pa.policy_id,
      pa.status      AS action_status,
      pa.effective_from,
      pa.expiry_date,
      pa.premium,
      pa.vat         AS action_vat,
      p.product_id,
      p.policyNumber AS policy_number,
      p.premium_freq,
      p.customer_id,
      p.premium      AS policy_premium,
      p.vat          AS policy_vat,
      p.status       AS policy_status
    FROM policy_actions pa
    JOIN policies p ON p.id = pa.policy_id
    WHERE pa.deleted_at IS NULL
      AND pa.status = 'ISSUED'
      AND p.status = 1
  `
  const params = []

  if (SINGLE_POLICY) {
    policiesSQL += ' AND pa.policy_id = ?'
    params.push(SINGLE_POLICY)
  } else if (!ALL_PRODS) {
    policiesSQL += ` AND p.product_id IN (${DOMCOM_PRODUCTS.join(',')})`
  }

  policiesSQL += ' ORDER BY pa.policy_id ASC'

  log('Fetching policies...')
  const policies = await query(policiesSQL, params)
  log(`Found ${policies.length} issued policy actions to check`)

  let created = 0, skipped = 0, errors = 0

  // ── Step 2: Process in batches ────────────────────────────────
  for (let i = 0; i < policies.length; i += BATCH_SIZE) {
    const batch = policies.slice(i, i + BATCH_SIZE)
    const policyIds = batch.map(p => p.policy_id)

    // Fetch existing ledger entries for this batch
    const [existingLedger] = await (await import('./db.js')).pool.execute(
      `SELECT policy_id, action_id, trans_type FROM policy_ledger
       WHERE policy_id IN (${policyIds.map(() => '?').join(',')})
         AND deleted_at IS NULL`,
      policyIds
    )

    const ledgerMap = new Set(existingLedger.map(l => `${l.policy_id}_${l.action_id}_${l.trans_type}`))

    const toInsert = []

    for (const pol of batch) {
      const premiumKey  = `${pol.policy_id}_${pol.action_id}_Invoice`
      const subKey      = `${pol.policy_id}_${pol.action_id}_SubLedger`

      const premium = parseFloat(pol.premium || pol.policy_premium || 0)
      const vat     = parseFloat(pol.action_vat || pol.policy_vat || 0)
      const total   = premium + vat

      if (total <= 0) {
        skipped++
        continue
      }

      // Create Invoice ledger entry if missing
      if (!ledgerMap.has(premiumKey)) {
        toInsert.push([
          pol.policy_id,
          pol.action_id,
          pol.customer_id,
          'Invoice',
          fmt(total),        // invoice_amount = premium + vat
          fmt(premium),      // premium (net)
          fmt(total),        // balance = full amount outstanding (unpaid)
          pol.effective_from,// invoice_date
          new Date().toISOString().slice(0, 19).replace('T', ' '),
          new Date().toISOString().slice(0, 19).replace('T', ' '),
        ])
        created++
      } else {
        skipped++
      }
    }

    if (toInsert.length > 0 && !DRY_RUN) {
      try {
        const conn = await (await import('./db.js')).pool.getConnection()
        await conn.beginTransaction()
        await conn.query(
          `INSERT INTO policy_ledger
             (policy_id, action_id, customer_id, trans_type, invoice_amount, premium,
              balance, invoice_date, created_at, updated_at)
           VALUES ?`,
          [toInsert]
        )
        await conn.commit()
        conn.release()
      } catch (e) {
        errors++
        log(`ERROR batch ${i}-${i + BATCH_SIZE}: ${e.message}`)
      }
    } else if (toInsert.length > 0 && DRY_RUN) {
      log(`[DRY RUN] Would insert ${toInsert.length} ledger entries`)
    }

    if ((i + BATCH_SIZE) % 2000 === 0 || i + BATCH_SIZE >= policies.length) {
      log(`Progress: ${Math.min(i + BATCH_SIZE, policies.length)}/${policies.length} | Created: ${created} | Skipped: ${skipped} | Errors: ${errors}`)
    }
  }

  log('=== Ledger Rebuild Complete ===')
  log(`Total: ${policies.length} | Created: ${created} | Skipped: ${skipped} | Errors: ${errors}`)

  await closePool()
}

main().catch(e => {
  console.error('FATAL:', e)
  process.exit(1)
})
