import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import DualScrollTable from '../../components/common/DualScrollTable'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import EmptyState from '../../components/common/EmptyState'
import {
  FacPage, FacPageHead, FacTile, FacTiles, FacPanel, FacNote, FacRow,
  FacDialog, FacField, FacTabs,
} from '../../components/fac/FacUI'
import { FacSlipTermsDialog } from '../../components/fac/FacSlipTermsDialog'
import {
  fetchFacSummary, fetchFacVariance, storeFacGl, closeFacPeriod,
  fetchFacSlips, sendFacSlip, downloadFacSlip, fetchFacCoverageScan,
  facMoney, facAmount, type FacSlip,
} from '../../api/fac'

/**
 * FAC Settlements — what is owed to whom, right now.
 *
 * This is the SUMMARY tab of the master workbook, live. It is grouped by
 * (basis, currency, counterparty) rather than counterparty alone, because the
 * workbook keeps Auto FAC and Normal FAC apart and Grand Re appears in both —
 * collapsing them would make the figures impossible to tie back.
 *
 * Three tabs: what we owe, the slips going out, and the policies Graphite says
 * need facultative cover that this register cannot account for.
 */

type Tab = 'payable' | 'slips' | 'coverage'

export default function FacSettlementsPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const tab = (searchParams.get('tab') as Tab) || 'payable'

  const setTab = (t: Tab) => {
    const next = new URLSearchParams(searchParams)
    next.set('tab', t)
    setSearchParams(next)
  }

  return (
    <FacPage>
      <FacPageHead
        eyebrow={<Link to="/reinsurance/fac" className="fac-link" style={{ fontSize: 12 }}>← FAC Register</Link>}
        title="FAC Settlements"
        blurb="What is owed to each reinsurer and broker, so a payment run can be worked off it. The payments themselves are raised in omni."
      />

      <FacTabs<Tab>
        value={tab}
        onChange={setTab}
        tabs={[['payable', 'What we owe'], ['slips', 'Slips'], ['coverage', 'Uncovered risks']]}
      />

      {tab === 'payable' && <PayableTab />}
      {tab === 'slips' && <SlipsTab />}
      {tab === 'coverage' && <CoverageTab />}
    </FacPage>
  )
}

// ─── What we owe ──────────────────────────────────────────────────────────────

function PayableTab() {
  const qc = useQueryClient()
  const [periodEnd, setPeriodEnd] = useState(() => {
    const d = new Date()
    return new Date(d.getFullYear(), d.getMonth(), 0).toISOString().slice(0, 10)
  })
  const [glForm, setGlForm] = useState({ open: false, premium: '', commission: '', source: '', asAt: '' })

  const summary = useQuery({ queryKey: ['fac-summary'], queryFn: () => fetchFacSummary() })
  const variance = useQuery({
    queryKey: ['fac-variance', periodEnd],
    queryFn: () => fetchFacVariance({ period_end: periodEnd }),
    enabled: !!periodEnd,
  })

  const onError = (err: any) => alert(err?.response?.data?.message || 'That action could not be completed.')

  const glMut = useMutation({
    mutationFn: () => storeFacGl({
      period_end: periodEnd,
      gl_premium: Number(glForm.premium),
      gl_commission: glForm.commission ? Number(glForm.commission) : undefined,
      gl_source: glForm.source.trim(),
      gl_as_at: glForm.asAt,
    }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['fac-variance'] })
      setGlForm({ open: false, premium: '', commission: '', source: '', asAt: '' })
    },
    onError,
  })

  const closeMut = useMutation({
    mutationFn: () => closeFacPeriod({ period_end: periodEnd }),
    onSuccess: (r: any) => {
      qc.invalidateQueries({ queryKey: ['fac-summary'] })
      alert(`Period frozen. ${r.rowsWritten} counterparty position(s) recorded at ${facMoney(r.payable)}.`)
    },
    onError,
  })

  if (summary.isLoading) return <div className="p-12 text-center"><LoadingSpinner size="lg" /></div>

  const rows = summary.data?.rows ?? []
  const totals = summary.data?.totals
  const v = variance.data

  return (
    <div className="space-y-4">

      {totals && (
        <FacTiles cols={4}>
          <FacTile label="Total payable" value={facMoney(totals.payable)} tone="navy" />
          <FacTile label="Prior period" value={facMoney(totals.priorPayable)} tone="quiet" />
          <FacTile label="Movement" value={facMoney(totals.change)} tone={totals.change < 0 ? 'ok' : undefined} />
          <FacTile label="Lines" value={String(totals.lineCount)} tone="quiet" />
        </FacTiles>
      )}

      {summary.data && !summary.data.priorPeriodEnd && (
        <FacNote tone="warn">
          No period has been frozen yet, so the prior and movement columns read zero. Close a month
          below and next month's roll-forward will work — that is what produces the workbook's
          "Prior FAC Payable" and "Change" columns.
        </FacNote>
      )}

      <FacPanel bodyless>
        <DualScrollTable>
          <table className="fac-table">
            <thead>
              <tr>
                <th>Basis</th>
                <th>Counterparty</th>
                <th>Cur</th>
                <th className="fac-num">Premium excl VAT</th>
                <th className="fac-num">Commission excl VAT</th>
                <th className="fac-num">Payable</th>
                <th className="fac-num">Prior</th>
                <th className="fac-num">Change</th>
                <th className="fac-num">Open now</th>
                <th className="fac-num">Lines</th>
              </tr>
            </thead>
            <tbody>
              {rows.length === 0 ? (
                <tr><td colSpan={10} style={{ padding: 0 }}>
                  <EmptyState compact title="Nothing owed" description="The register holds no placements yet." />
                </td></tr>
              ) : rows.map((r, i) => (
                <tr key={i}>
                  <td className="fac-hint">{r.placementTypeLabel}</td>
                  <td>
                    <span className="fac-strong">{r.counterpartyName || '(unnamed)'}</span>
                    {r.rateMissingCount > 0 && (
                      <span className="fac-flag fac-flag--amber" style={{ marginLeft: 8 }}>
                        {r.rateMissingCount} without a rate
                      </span>
                    )}
                  </td>
                  <td className="fac-hint">{r.currency}</td>
                  <td className="fac-num">{facAmount(r.premiumExclVat)}</td>
                  <td className="fac-num">{facAmount(r.commissionExclVat)}</td>
                  <td className="fac-num fac-strong">{facAmount(r.payable)}</td>
                  <td className="fac-num" style={{ color: 'var(--fac-ink-dim)' }}>{facAmount(r.priorPayable)}</td>
                  <td className="fac-num"
                      style={{ color: r.change < 0 ? 'var(--fac-ok)' : r.change > 0 ? 'var(--fac-ink)' : 'var(--fac-ink-dim)' }}>
                    {facAmount(r.change)}
                  </td>
                  <td className="fac-num">{facAmount(r.openPayable)}</td>
                  <td className="fac-num" style={{ color: 'var(--fac-ink-dim)' }}>{r.lineCount}</td>
                </tr>
              ))}
            </tbody>
            {totals && rows.length > 0 && (
              <tfoot>
                <tr>
                  <td colSpan={3}>Total</td>
                  <td className="fac-num">{facAmount(totals.premiumExclVat)}</td>
                  <td className="fac-num">{facAmount(totals.commissionExclVat)}</td>
                  <td className="fac-num">{facAmount(totals.payable)}</td>
                  <td className="fac-num">{facAmount(totals.priorPayable)}</td>
                  <td className="fac-num">{facAmount(totals.change)}</td>
                  <td colSpan={2}></td>
                </tr>
              </tfoot>
            )}
          </table>
        </DualScrollTable>
      </FacPanel>

      {/* ── Month end ──────────────────────────────────────────────── */}
      <FacPanel title="Month end">
        <div className="flex flex-wrap items-end gap-3">
          <label className="w-44 fac-field">
            <span className="fac-label">Period end</span>
            <input type="date" className="fac-input" value={periodEnd} onChange={e => setPeriodEnd(e.target.value)} />
          </label>
          <button className="fac-btn" onClick={() => setGlForm(g => ({ ...g, open: true }))}>
            Capture ledger figures
          </button>
          <button
            className="fac-btn fac-btn--primary"
            disabled={closeMut.isPending}
            onClick={() => {
              if (confirm(`Freeze the position at ${periodEnd}? Re-running replaces that period's snapshot only.`)) {
                closeMut.mutate()
              }
            }}
          >
            {closeMut.isPending ? 'Freezing…' : 'Freeze this period'}
          </button>
        </div>

        {v && (
          v.available ? (
            <div className="grid md:grid-cols-2 gap-3 mt-4">
              <div className="fac-panel" style={{ borderRadius: 'var(--fac-radius)' }}>
                <div className="fac-panel-body">
                  <span className="fac-eyebrow">Premium</span>
                  <div className="fac-dl mt-1">
                    <FacRow k="Register" v={facAmount(v.registerPremium)} />
                    <FacRow k="Ledger" v={facAmount(v.glPremium ?? null)} />
                    <FacRow k="Variance" v={facAmount(v.premiumVariance ?? null)} strong />
                  </div>
                </div>
              </div>
              <div className="fac-panel" style={{ borderRadius: 'var(--fac-radius)' }}>
                <div className="fac-panel-body">
                  <span className="fac-eyebrow">Commission</span>
                  <div className="fac-dl mt-1">
                    <FacRow k="Register" v={facAmount(v.registerCommission)} />
                    <FacRow k="Ledger" v={facAmount(v.glCommission ?? null)} />
                    <FacRow k="Variance" v={facAmount(v.commissionVariance ?? null)} strong />
                  </div>
                </div>
              </div>
              <p className="fac-hint md:col-span-2">
                Ledger figures from {v.glSource} as at {v.glAsAt}. The variance is the journal
                Finance passes — <strong>in omni</strong>. Graphite does not post to the ledger.
              </p>

              {/*
                THE ENTRIES TO PASS, which Reinsurance kept by hand in the master
                spreadsheet because only the two variance figures above were
                produced here. Still stated rather than posted — the classification
                and the gross-up are the arithmetic, not an instruction to a ledger
                Graphite does not have.
              */}
              {v.journalToPass?.lines?.available === true && (
                <div className="md:col-span-2 mt-2">
                  <span className="fac-eyebrow">Entries to pass</span>
                  <div className="fac-table-wrap mt-1">
                    <table className="fac-table">
                      <thead>
                        <tr>
                          <th>Classification</th>
                          <th>Account</th>
                          <th className="text-right">Amount</th>
                          <th>Dr/Cr</th>
                        </tr>
                      </thead>
                      <tbody>
                        {v.journalToPass.lines.lines.map(l => (
                          <tr key={`${l.classification}-${l.account}`}>
                            <td>{l.classification}</td>
                            <td>{l.account}</td>
                            <td className="text-right">{facAmount(l.amount)}</td>
                            <td>{l.drCr}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>

                  {/*
                    THE TWO ENDS OF EACH MOVEMENT. Reinsurance check the entry by
                    comparing last month's balance to this month's, so both are
                    shown — an entry nobody can see the ends of is one taken on
                    trust.
                  */}
                  <div className="fac-grid-2 mt-2">
                    <div className="fac-dl">
                      <span className="fac-eyebrow">FAC payable, incl VAT</span>
                      <FacRow k="Prior month" v={facAmount(v.journalToPass.lines.balances.payable.prior)} />
                      <FacRow k="This month" v={facAmount(v.journalToPass.lines.balances.payable.current)} />
                      <FacRow k="Movement" v={facAmount(v.journalToPass.lines.balances.payable.movement)} strong />
                    </div>
                    <div className="fac-dl">
                      <span className="fac-eyebrow">Commission receivable, incl VAT</span>
                      <FacRow k="Prior month" v={facAmount(v.journalToPass.lines.balances.receivable.prior)} />
                      <FacRow k="This month" v={facAmount(v.journalToPass.lines.balances.receivable.current)} />
                      <FacRow k="Movement" v={facAmount(v.journalToPass.lines.balances.receivable.movement)} strong />
                    </div>
                  </div>

                  {/*
                    Their CONTROL CHECK. Shown either way — a balanced journal
                    that never says so is indistinguishable from one nobody
                    checked, and this is the figure that makes it postable.
                  */}
                  {v.journalToPass.lines.balanced ? (
                    <FacNote>
                      Balances — debits equal credits. VAT at{' '}
                      {(v.journalToPass.lines.vatRate * 100).toFixed(2)}%.
                    </FacNote>
                  ) : (
                    <FacNote tone="warn">
                      Does not balance: debits less credits ={' '}
                      {facAmount(v.journalToPass.lines.control)}. Do not post this — raise it
                      with IT.
                    </FacNote>
                  )}
                </div>
              )}

              {v.journalToPass?.lines?.available === false && (
                <div className="md:col-span-2">
                  <FacNote tone="warn">{v.journalToPass.lines.message}</FacNote>
                </div>
              )}
            </div>
          ) : (
            <div className="mt-4"><FacNote tone="warn">{v.message}</FacNote></div>
          )
        )}
      </FacPanel>

      {glForm.open && (
        <FacDialog
          title="Ledger figures for this period"
          onClose={() => setGlForm(g => ({ ...g, open: false }))}
          footer={
            <>
              <button className="fac-btn fac-btn--primary flex-1" disabled={glMut.isPending}
                onClick={() => {
                  if (!glForm.premium || !glForm.source.trim() || !glForm.asAt) {
                    alert('The premium, the source and the as-at date are all required.'); return
                  }
                  glMut.mutate()
                }}>
                {glMut.isPending ? 'Saving…' : 'Save'}
              </button>
              <button className="fac-btn" onClick={() => setGlForm(g => ({ ...g, open: false }))}>Cancel</button>
            </>
          }
        >
          <FacNote>
            Graphite holds no general ledger, so these come from omni. They are stored with their
            source and date and are never derived — a variance computed against a guess is worse
            than no variance.
          </FacNote>
          <div className="mt-3 space-y-3">
            <FacField label="FAC premium per the ledger *">
              <input type="number" step="0.01" className="fac-input" value={glForm.premium}
                onChange={e => setGlForm(g => ({ ...g, premium: e.target.value }))} />
            </FacField>
            <FacField label="FAC commission per the ledger">
              <input type="number" step="0.01" className="fac-input" value={glForm.commission}
                onChange={e => setGlForm(g => ({ ...g, commission: e.target.value }))} />
            </FacField>
            <FacField label="Source *">
              <input type="text" className="fac-input" value={glForm.source} placeholder="omni trial balance, ADIC"
                onChange={e => setGlForm(g => ({ ...g, source: e.target.value }))} />
            </FacField>
            <FacField label="Figures as at *">
              <input type="date" className="fac-input" value={glForm.asAt}
                onChange={e => setGlForm(g => ({ ...g, asAt: e.target.value }))} />
            </FacField>
          </div>
        </FacDialog>
      )}
    </div>
  )
}

// ─── Slips ────────────────────────────────────────────────────────────────────

function SlipsTab() {
  const qc = useQueryClient()
  const q = useQuery({ queryKey: ['fac-slips'], queryFn: () => fetchFacSlips({ per_page: 100 }) })

  const sendMut = useMutation({
    mutationFn: (slipId: number) => sendFacSlip(slipId),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['fac-slips'] }); alert('Slip sent.') },
    onError: (err: any) => alert(err?.response?.data?.message || 'The slip could not be sent.'),
  })

  /*
   * The term-sheet wording — deductible, description of risk, territorial scope.
   *
   * The form now lives in FacSlipTermsDialog, because the placement screen opens
   * it too: the field was only ever reachable from this page, which needs
   * `reinsurance-fac-settle`, and the underwriter who knows the deductible has
   * no reason to be here.
   */
  const [terms, setTerms] = useState<FacSlip | null>(null)
  const openTerms = (slip: FacSlip) => setTerms(slip)

  if (q.isLoading) return <div className="p-12 text-center"><LoadingSpinner size="lg" /></div>

  const rows = q.data?.data ?? []

  return (
    <div className="space-y-3">
      <FacNote>
        One slip covers every placement line carrying its number — a monthly risk placed on one
        slip produces several lines but only one document. Slips are generated automatically;
        sending one is a deliberate action, because it is a contractual communication to a
        reinsurer.
      </FacNote>

      <FacPanel bodyless>
        <DualScrollTable>
          <table className="fac-table">
            <thead>
              <tr>
                <th>Slip</th>
                <th>Policy / insured</th>
                <th>Counterparty</th>
                <th className="fac-num">Lines</th>
                <th>Status</th>
                <th>Sent</th>
                <th className="fac-num">Actions</th>
              </tr>
            </thead>
            <tbody>
              {rows.length === 0 ? (
                <tr><td colSpan={7} style={{ padding: 0 }}>
                  <EmptyState compact title="No slips yet"
                    description="Slips are generated from placements that carry a slip number." />
                </td></tr>
              ) : rows.map(s => (
                <tr key={s.id} data-void={s.status === 'superseded' ? 'true' : 'false'}>
                  <td>
                    <span className="fac-ref">{s.slipNo}</span>
                    <span className="fac-hint"> v{s.version}</span>
                  </td>
                  <td>
                    <span className="fac-strong">{s.policyNumber || '—'}</span>
                    <span className="fac-truncate fac-hint">{s.insuredName || '—'}</span>
                  </td>
                  <td>{s.counterpartyName || '—'}</td>
                  <td className="fac-num">{s.lineCount}</td>
                  <td>
                    <span className={`fac-badge ${
                      s.status === 'sent' ? 'fac-badge--paid'
                      : s.status === 'superseded' ? 'fac-badge--closed'
                      : 'fac-badge--placed'
                    }`}>{s.status}</span>
                  </td>
                  <td style={{ fontSize: 12 }}>
                    {s.sentAt ? <>{s.sentAt}<span className="fac-hint">{s.sentTo}</span></> : '—'}
                    {s.sendError && <span className="fac-hint" style={{ color: 'var(--fac-danger)' }}>{s.sendError}</span>}
                  </td>
                  <td className="fac-num">
                    <span className="inline-flex gap-2">
                      {s.hasDocument && (
                        <button
                          className="fac-link"
                          style={{ fontSize: 12, border: 'none', background: 'none', cursor: 'pointer' }}
                          onClick={async () => {
                            const r = await downloadFacSlip(s.id)
                            window.open(r.url, '_blank', 'noopener')
                          }}
                        >
                          Open
                        </button>
                      )}
                      {s.termsEditable && (
                        <button
                          className="fac-link"
                          style={{ fontSize: 12, border: 'none', background: 'none', cursor: 'pointer' }}
                          onClick={() => openTerms(s)}
                        >
                          Wording
                        </button>
                      )}
                      {s.status !== 'superseded' && s.hasDocument && (
                        <button
                          className="fac-link"
                          style={{ fontSize: 12, border: 'none', background: 'none', cursor: 'pointer', color: 'var(--fac-ok)' }}
                          disabled={sendMut.isPending}
                          onClick={() => {
                            if (confirm(`Email slip ${s.slipNo} to ${s.counterpartyName}?`)) sendMut.mutate(s.id)
                          }}
                        >
                          {s.sentAt ? 'Resend' : 'Send'}
                        </button>
                      )}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </DualScrollTable>
      </FacPanel>

      {terms && (
        <FacSlipTermsDialog slip={terms} onClose={() => setTerms(null)} />
      )}
    </div>
  )
}

// ─── Uncovered risks ──────────────────────────────────────────────────────────

function CoverageTab() {
  const q = useQuery({
    queryKey: ['fac-coverage-scan'],
    queryFn: () => fetchFacCoverageScan({ exceptions_only: true }),
  })

  if (q.isLoading) return <div className="p-12 text-center"><LoadingSpinner size="lg" /></div>

  const rows = q.data?.data ?? []
  const s = q.data?.summary

  return (
    <div className="space-y-3">
      <FacNote>
        Graphite already computes, per policy, the share of the risk the treaty programme could not
        absorb — that share has to be placed facultatively. This compares it against what the
        register carries. A policy here with nothing placed is an exposure nobody can currently
        see, because the requirement lives in Graphite and the placement lived in a spreadsheet.
      </FacNote>

      {s && (
        <FacTiles cols={5}>
          <FacTile label="Need FAC cover" value={String(s.policiesRequiringFac)} tone="quiet" />
          <FacTile label="Properly covered" value={String(s.covered)} tone="ok" />
          <FacTile label="Nothing placed" value={String(s.nonePlaced)} tone={s.nonePlaced ? 'danger' : 'quiet'} />
          <FacTile label="Under-placed" value={String(s.underPlaced)} tone={s.underPlaced ? 'warn' : 'quiet'} />
          <FacTile label="Ceded premium unplaced" value={facMoney(s.exposureUnplacedBwp)} tone="navy" />
        </FacTiles>
      )}

      <FacPanel bodyless>
        <DualScrollTable>
          <table className="fac-table">
            <thead>
              <tr>
                <th>Policy</th>
                <th>Verdict</th>
                <th className="fac-num">Required (BWP)</th>
                <th className="fac-num">Placed (BWP)</th>
                <th className="fac-num">Gap (BWP)</th>
                <th className="fac-num">Lines</th>
              </tr>
            </thead>
            <tbody>
              {rows.length === 0 ? (
                <tr><td colSpan={6} style={{ padding: 0 }}>
                  <EmptyState compact title="No exceptions"
                    description="Every policy needing facultative cover is accounted for in the register." />
                </td></tr>
              ) : rows.map((r, i) => (
                <tr key={i}>
                  <td className="fac-strong">{r.policyNumber}</td>
                  <td>
                    <span className={`fac-badge ${
                      r.verdict === 'fac_required_none_placed' ? 'fac-badge--void'
                      : r.verdict === 'fac_under_placed' ? 'fac-badge--waiting'
                      : 'fac-badge--draft'
                    }`}>{r.verdictLabel}</span>
                  </td>
                  <td className="fac-num">{facAmount(r.requiredPremium)}</td>
                  <td className="fac-num">{facAmount(r.placedPremium)}</td>
                  <td className="fac-num fac-strong">{facAmount(r.gap)}</td>
                  <td className="fac-num" style={{ color: 'var(--fac-ink-dim)' }}>{r.placedCount}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </DualScrollTable>
      </FacPanel>
    </div>
  )
}
