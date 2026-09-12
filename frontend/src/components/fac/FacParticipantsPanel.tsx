/**
 * Who is on a placement, and for what share.
 *
 * Reinsurance asked for two things that look like one: a list of reinsurers
 * participating on Auto Facultative, and the panel on a FAC slip with each
 * reinsurer's proportion. They are different questions off different tables, and
 * collapsing them into a single list would be the wrong answer to both:
 *
 *  · PLACED WITH — the placement lines sharing this slip number. Exists from
 *    capture. Says nothing about whether anybody has agreed.
 *  · SIGNED FOR — the slip's acceptance panel. A row here is only cover once it
 *    carries a signatory and a date. A slip that has been sent and not signed
 *    is not cover, and the screen has to say so rather than show a tidy list.
 *
 * So both are rendered, separately, each with its own total, and the gap between
 * them is stated. Nothing here validates: whether a panel must add to the placed
 * share is a Reinsurance rule we have not been given, so it is reported.
 */
import { FacNote, FacPanel } from './FacUI'
import { facMoney, facPct, type FacParticipants } from '../../api/fac'

export function FacParticipantsPanel({ participants }: { participants?: FacParticipants }) {
  if (!participants) {
    return (
      <FacPanel title="Participants">
        <FacNote>Nothing to show yet — this placement has no slip number.</FacNote>
      </FacPanel>
    )
  }

  const { basis, slipNo, slipVersion, slipStatus, lines, acceptances, totals } = participants
  const isAuto = basis === 'auto_fac'
  const label  = isAuto ? 'Auto FAC' : 'FAC'

  return (
    <FacPanel
      title={`${label} participants`}
      action={slipNo
        ? <span className="fac-hint">
            Slip {slipNo}{slipVersion ? ` v${slipVersion}` : ''}{slipStatus ? ` · ${slipStatus}` : ''}
          </span>
        : <span className="fac-hint">No slip number</span>}
    >
      {/* ── Who the risk was placed with ─────────────────────────────── */}
      <div className="fac-legend">Placed with</div>
      {lines.length === 0 ? (
        <FacNote>No placement lines.</FacNote>
      ) : (
        <table className="fac-table">
          <thead>
            <tr>
              <th>Reinsurer</th>
              <th>Risk carried by</th>
              <th className="fac-num">Share</th>
              <th className="fac-num">Sum insured ceded</th>
              <th className="fac-num">Gross ceded premium</th>
            </tr>
          </thead>
          <tbody>
            {lines.map(l => (
              <tr key={l.id}>
                <td>{l.counterpartyName ?? '—'}</td>
                <td>{l.riskCarrier ?? '—'}</td>
                <td className="fac-num">{facPct(l.riskPct)}</td>
                <td className="fac-num">{facMoney(l.cessionSumInsured, l.currency ?? undefined)}</td>
                <td className="fac-num">{facMoney(l.grossCededPremium, l.currency ?? undefined)}</td>
              </tr>
            ))}
            <tr className="fac-strong">
              <td colSpan={2}>
                {totals.lineCount} {totals.lineCount === 1 ? 'line' : 'lines'}
              </td>
              <td className="fac-num">{facPct(totals.lineSharePct)}</td>
              <td colSpan={2} />
            </tr>
          </tbody>
        </table>
      )}

      {/* ── Who has actually signed ──────────────────────────────────── */}
      <div className="fac-legend" style={{ marginTop: 18 }}>Signed for</div>
      {acceptances.length === 0 ? (
        <FacNote tone="warn">
          No acceptance panel yet. It is created when the slip is generated, so
          until then there is no record of who has agreed to carry this risk.
        </FacNote>
      ) : (
        <>
          <table className="fac-table">
            <thead>
              <tr>
                <th>Accepting company</th>
                <th className="fac-num">Share</th>
                <th className="fac-num">Limit accepted</th>
                <th>Signed by</th>
                <th>Signed on</th>
              </tr>
            </thead>
            <tbody>
              {acceptances.map(a => (
                <tr key={a.id}>
                  <td>
                    {a.acceptingCompany}
                    {!a.committed && (
                      <span className="fac-flag fac-flag--amber" style={{ marginLeft: 8 }}>
                        not signed
                      </span>
                    )}
                  </td>
                  <td className="fac-num">{facPct(a.sharePct)}</td>
                  <td className="fac-num">{facMoney(a.amount)}</td>
                  <td>{a.signatoryName ?? '—'}</td>
                  <td>{a.acceptedOn ?? '—'}</td>
                </tr>
              ))}
              <tr className="fac-strong">
                <td>
                  {totals.committedCount} of {totals.acceptanceCount} signed
                </td>
                <td className="fac-num">{facPct(totals.acceptedPct)}</td>
                <td colSpan={3} />
              </tr>
            </tbody>
          </table>

          {/*
            Two separate warnings, because they are two separate problems.
            A panel that does not add up is a capture question. A panel nobody has
            signed is an exposure question, and it is the more serious of the two.
          */}
          {!totals.reconciles && (
            <FacNote tone="warn">
              The panel adds to {facPct(totals.acceptedPct)} against a placed share of{' '}
              {facPct(totals.lineSharePct)}. Check the shares before relying on this slip.
            </FacNote>
          )}
          {totals.committedCount === 0 && (
            <FacNote tone="danger">
              Nobody on this panel has signed. Until the countersigned slip is filed
              this risk is retained net, whatever the shares above say.
            </FacNote>
          )}
        </>
      )}

      {isAuto && (
        <FacNote>
          This is who was placed on this Auto FAC risk. It is not a standing Auto FAC
          panel — neither treaty establishes a facultative facility, so each risk is
          placed on its own terms.
        </FacNote>
      )}
    </FacPanel>
  )
}
