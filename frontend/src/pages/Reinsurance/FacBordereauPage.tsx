/**
 * The cession bordereau — the statement sent to a broker or reinsurer.
 *
 * This is not the register export. The export is a flat extract of every column
 * for a spreadsheet; a bordereau is addressed to one counterparty, grouped and
 * subtotalled so they can agree it line by line against their own book. Month-end
 * control C7 reconciles the cession to this document.
 *
 * THE FORMAT IS NOT FINAL. No sample bordereau has been supplied by the broker, so
 * the columns are the ones the register holds and that a cession bordereau
 * conventionally carries. The figures are the part that cannot be renegotiated; the
 * column order is expected to change once Reinsurance has put a real one in front
 * of the broker. That is said on the page rather than left for somebody to discover.
 */
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import apiClient from '../../api/client'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import EmptyState from '../../components/common/EmptyState'
import { useToast } from '../../components/common/Toast'
import {
  FacPage, FacPageHead, FacPanel, FacNote, FacTile, FacTiles,
} from '../../components/fac/FacUI'
import {
  fetchFacBordereau, downloadFacBordereauCsv, facMoney, facPct,
  type FacBordereauFilters, type FacBordereauLine,
} from '../../api/fac'

/** Month start and end, so the common case needs no typing. */
function thisMonth(): { from: string; to: string } {
  const d = new Date()
  const p = (n: number) => String(n).padStart(2, '0')
  const last = new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate()

  return {
    from: `${d.getFullYear()}-${p(d.getMonth() + 1)}-01`,
    to: `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(last)}`,
  }
}

const COLS = [
  'FAC Reference', 'Type', 'Slip No.', 'Policy Number', 'Insured', 'RI Group',
  'Slip Signed', 'Risk Carried By', 'Risk %', 'Sum Insured Ceded',
  'Gross Ceded Premium', 'Excl VAT', 'Comm %', 'Commission', 'Net Ceded Premium',
]

function Row({ l }: { l: FacBordereauLine }) {
  const cur = l.currency ?? undefined

  return (
    <tr data-void={l.isReversal ? 'true' : 'false'}>
      <td className="fac-ref">{l.facReference}</td>
      <td>{l.placementType}</td>
      <td>{l.slipNo ?? '—'}</td>
      <td>{l.policyNumber ?? '—'}</td>
      <td><span className="fac-truncate" style={{ maxWidth: 180 }}>{l.insuredName ?? '—'}</span></td>
      <td>{l.riGroupLabel ?? '—'}</td>
      <td>{l.slipSignedDate ?? '—'}</td>
      <td>{l.riskCarrier ?? '—'}</td>
      <td className="fac-num">{facPct(l.riskPct)}</td>
      <td className="fac-num">{facMoney(l.cessionSumInsured, cur)}</td>
      <td className="fac-num">{facMoney(l.grossCededPremium, cur)}</td>
      <td className="fac-num">{facMoney(l.premiumExclVat, cur)}</td>
      <td className="fac-num">{facPct(l.commissionPct)}</td>
      <td className="fac-num">{facMoney(l.commission, cur)}</td>
      <td className="fac-num">{facMoney(l.netCededPremium, cur)}</td>
    </tr>
  )
}

export default function FacBordereauPage() {
  const m = thisMonth()
  const { toast } = useToast()
  const [downloading, setDownloading] = useState(false)
  const [filters, setFilters] = useState<FacBordereauFilters>({
    period_from: m.from,
    period_to: m.to,
  })

  const bord = useQuery({
    queryKey: ['fac-bordereau', filters],
    queryFn: () => fetchFacBordereau(filters),
  })

  const counterparties = useQuery({
    queryKey: ['fac-counterparties'],
    queryFn: () => apiClient.get('/reinsurance/reinsurers', { params: { per_page: 200 } }).then(r => r.data),
    staleTime: 5 * 60 * 1000,
  })

  const cpList: Array<{ id: number; company_name: string }> = counterparties.data?.data ?? []
  const b = bord.data
  const set = (k: keyof FacBordereauFilters, v: string) =>
    setFilters(f => ({ ...f, [k]: v === '' ? undefined : v }))

  return (
    <FacPage>
      <FacPageHead
        eyebrow={<Link to="/reinsurance/fac" className="fac-link" style={{ fontSize: 12 }}>← FAC Register</Link>}
        title="Cession Bordereau"
        blurb="The statement of cessions for a period, grouped by reinsurer. Signed placements only — a draft is a placement no reinsurer has agreed to, so it is never on a bordereau."
        actions={
          <button
            className="fac-btn fac-btn--primary"
            disabled={downloading}
            onClick={async () => {
              setDownloading(true)
              try {
                await downloadFacBordereauCsv(filters)
              } catch (e: any) {
                // Said out loud. A download that silently does nothing is the
                // failure people report as "the button is broken".
                toast.error(e?.message || 'The bordereau could not be downloaded.')
              } finally {
                setDownloading(false)
              }
            }}
          >
            {downloading ? 'Preparing…' : 'Download CSV'}
          </button>
        }
      />

      <FacPanel title="Period and scope">
        <div className="fac-grid-4">
          <label className="fac-field">
            <span className="fac-label">Signed from</span>
            <input type="date" className="fac-input" value={filters.period_from ?? ''}
                   onChange={e => set('period_from', e.target.value)} />
            <span className="fac-hint">Tested on the slip signing date — when the cession came on risk.</span>
          </label>
          <label className="fac-field">
            <span className="fac-label">Signed to</span>
            <input type="date" className="fac-input" value={filters.period_to ?? ''}
                   onChange={e => set('period_to', e.target.value)} />
          </label>
          <label className="fac-field">
            <span className="fac-label">Reinsurer</span>
            <select className="fac-select" value={filters.counterparty_id ?? ''}
                    onChange={e => setFilters(f => ({
                      ...f,
                      counterparty_id: e.target.value === '' ? undefined : Number(e.target.value),
                    }))}>
              <option value="">Every reinsurer</option>
              {cpList.map(c => <option key={c.id} value={c.id}>{c.company_name}</option>)}
            </select>
            <span className="fac-hint">Pick one to send that reinsurer their own statement.</span>
          </label>
          <label className="fac-field">
            <span className="fac-label">Basis</span>
            <select className="fac-select" value={filters.placement_type ?? ''}
                    onChange={e => set('placement_type', e.target.value)}>
              <option value="">FAC and Auto FAC</option>
              <option value="fac">FAC only</option>
              <option value="auto_fac">Auto FAC only</option>
            </select>
          </label>
        </div>
      </FacPanel>

      {bord.isLoading && <LoadingSpinner />}
      {bord.isError && <FacNote tone="danger">The bordereau could not be built.</FacNote>}

      {b && (
        <>
          <FacTiles cols={4}>
            <FacTile label="Cessions" value={String(b.grandTotal.lineCount)} tone="navy" />
            <FacTile label="Sum insured ceded" value={facMoney(b.grandTotal.cessionSumInsured, '')} />
            <FacTile label="Gross ceded premium" value={facMoney(b.grandTotal.grossCededPremium, '')} />
            <FacTile label="Net ceded premium" value={facMoney(b.grandTotal.netCededPremium, '')} />
          </FacTiles>

          <FacNote>{b.header.basis}</FacNote>

          {b.groups.length === 0 ? (
            <FacPanel title="Nothing in this period" bodyless>
              {/*
                SAY HOW MUCH THERE IS TO FIND, AND WHERE. This panel used to give
                generic advice — widen the period, check the slips are filed —
                which is correct and unfalsifiable, and Reinsurance read it as the
                module failing to record placements. The counts come from the
                register, so the reader can tell an empty period from an empty
                register without leaving the page.
              */}
              <EmptyState compact title="No cessions"
                          description={b.emptyReason?.summary
                            ?? 'No placement was signed inside these dates. Widen the period, or check whether the signed slips have been filed.'} />

              {b.emptyReason && b.emptyReason.signedRange.from && (
                <FacNote>
                  The register holds slips signed between{' '}
                  <strong>{b.emptyReason.signedRange.from}</strong> and{' '}
                  <strong>{b.emptyReason.signedRange.to}</strong>. The period is tested
                  on the date the reinsurer signed, not the date of capture — try those
                  dates, or clear both fields to see the whole book.
                </FacNote>
              )}

              {b.emptyReason && b.emptyReason.withoutSignedDate > 0 && (
                <FacNote>
                  <strong>{b.emptyReason.withoutSignedDate}</strong>{' '}
                  {b.emptyReason.withoutSignedDate === 1 ? 'placement has' : 'placements have'}{' '}
                  no signed date. Widening the period will not surface{' '}
                  {b.emptyReason.withoutSignedDate === 1 ? 'it' : 'them'} — file the signed
                  slip and {b.emptyReason.withoutSignedDate === 1 ? 'it' : 'they'} will appear.
                </FacNote>
              )}
            </FacPanel>
          ) : b.groups.map(g => (
            <FacPanel
              key={`${g.counterpartyId}-${g.currency}`}
              title={`${g.counterpartyName} — ${g.currency}`}
              action={<span className="fac-hint">
                {g.subtotals.lineCount} {g.subtotals.lineCount === 1 ? 'cession' : 'cessions'}
              </span>}
            >
              <table className="fac-table">
                <thead>
                  <tr>{COLS.map(c => (
                    <th key={c} className={c.match(/%|Premium|Insured Ceded|Commission/) ? 'fac-num' : undefined}>{c}</th>
                  ))}</tr>
                </thead>
                <tbody>
                  {g.lines.map(l => <Row key={l.facReference} l={l} />)}
                  <tr className="fac-strong">
                    <td colSpan={9}>Subtotal — {g.counterpartyName}</td>
                    <td className="fac-num">{facMoney(g.subtotals.cessionSumInsured, g.currency)}</td>
                    <td className="fac-num">{facMoney(g.subtotals.grossCededPremium, g.currency)}</td>
                    <td className="fac-num">{facMoney(g.subtotals.premiumExclVat, g.currency)}</td>
                    <td />
                    <td className="fac-num">{facMoney(g.subtotals.commission, g.currency)}</td>
                    <td className="fac-num">{facMoney(g.subtotals.netCededPremium, g.currency)}</td>
                  </tr>
                </tbody>
              </table>
            </FacPanel>
          ))}

          {/*
            Reversals apart, and said plainly. A bordereau that nets a reversal into
            a subtotal hides the movement the recipient is trying to agree.
          */}
          {b.reversals.length > 0 && (
            <FacPanel title="Reversals">
              <FacNote tone="warn">
                Listed separately and NOT netted into the subtotals above. Agree each
                one against the original cession it reverses.
              </FacNote>
              <table className="fac-table">
                <thead><tr>{COLS.map(c => <th key={c}>{c}</th>)}</tr></thead>
                <tbody>{b.reversals.map(l => <Row key={l.facReference} l={l} />)}</tbody>
              </table>
            </FacPanel>
          )}

          <FacNote tone="warn">
            The layout is not confirmed. No sample bordereau has been supplied by the
            broker, so these are the columns a cession bordereau conventionally
            carries. Send one, have it marked up, and the columns will be changed to
            match — the figures will not move.
          </FacNote>
        </>
      )}
    </FacPage>
  )
}
