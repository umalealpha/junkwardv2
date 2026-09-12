/**
 * The schedule an underwriter types in — what is actually insured.
 *
 * Reinsurance's defect 8: a signed slip does not state one figure, it itemises.
 * Auto FAC slip 2026-002 carries nine lines of Fire & Allied Perils and six of
 * Business Interruption, and the underwriter in charge has to be able to enter them.
 *
 * THREE THINGS THIS SCREEN IS BUILT AROUND.
 *
 * The running total is always visible. The underwriter is copying from a schedule
 * that already has a total on it — 300,580,000 on slip 2026-002 — so the useful
 * question while typing is "does mine agree yet?". Hiding the total until save turns
 * a fifteen-line transcription into a hunt for the one wrong digit.
 *
 * A blank amount is NOT zero. "Indemnity period – 15 months" is a real line that
 * states a term, and sending 0 would assert nil cover and be added into the total.
 * The field stays empty and is sent as null.
 *
 * Nothing is saved until Save is pressed. The save REPLACES the whole schedule
 * server-side, so a half-finished edit must not reach it — the draft lives here
 * until the underwriter commits it.
 */
import { useEffect, useMemo, useState } from 'react'
import { FacNote, FacPanel } from './FacUI'
import {
  FAC_SCHEDULE_SECTIONS, facMoney,
  type FacSchedule, type FacScheduleInput, type FacScheduleSection,
} from '../../api/fac'

/** A row being edited. Amount is a string so the field can be genuinely empty. */
interface DraftRow {
  key: string
  section: FacScheduleSection
  label: string
  amount: string
}

let rowSeq = 0
const newKey = () => `r${++rowSeq}`

function toDraft(schedule?: FacSchedule): DraftRow[] {
  if (!schedule?.sections?.length) return []

  return schedule.sections.flatMap(s =>
    s.lines.map(l => ({
      key: newKey(),
      section: s.section,
      label: l.label,
      // Null stays empty, so a term-only line round-trips as a term-only line.
      amount: l.amount === null ? '' : String(l.amount),
    })),
  )
}

/** Parse for TOTALLING only — a half-typed number must not break the sum. */
function num(v: string): number {
  const n = Number(v.replace(/,/g, '').trim())
  return Number.isFinite(n) ? n : 0
}

export function FacScheduleEditor({ schedule, saving, onSave }: {
  schedule?: FacSchedule
  saving?: boolean
  onSave: (lines: FacScheduleInput[]) => void
}) {
  const [editing, setEditing] = useState(false)
  const [rows, setRows] = useState<DraftRow[]>(() => toDraft(schedule))

  // Re-seed from the server whenever it changes UNDERNEATH us, but never while the
  // underwriter is mid-edit — a refetch would otherwise wipe half-typed lines.
  useEffect(() => {
    if (!editing) setRows(toDraft(schedule))
  }, [schedule, editing])

  const totals = useMemo(() => {
    const bySection = new Map<string, number>()
    let grand = 0

    for (const r of rows) {
      // An empty amount contributes nothing at all — not zero.
      if (r.amount.trim() === '') continue
      const v = num(r.amount)
      bySection.set(r.section, (bySection.get(r.section) ?? 0) + v)
      grand += v
    }

    return { bySection, grand }
  }, [rows])

  const addRow = (section: FacScheduleSection) =>
    setRows(rs => [...rs, { key: newKey(), section, label: '', amount: '' }])

  const setRow = (key: string, patch: Partial<DraftRow>) =>
    setRows(rs => rs.map(r => (r.key === key ? { ...r, ...patch } : r)))

  const removeRow = (key: string) => setRows(rs => rs.filter(r => r.key !== key))

  /** Move within the section, since order is what the slip prints. */
  const move = (key: string, delta: -1 | 1) =>
    setRows(rs => {
      const i = rs.findIndex(r => r.key === key)
      const j = i + delta
      if (i < 0 || j < 0 || j >= rs.length || rs[j].section !== rs[i].section) return rs
      const next = [...rs]
      ;[next[i], next[j]] = [next[j], next[i]]
      return next
    })

  const blankLabels = rows.filter(r => r.label.trim() === '').length

  function save() {
    // Sent in on-screen order, which becomes the printed order. Blank-label rows
    // are dropped here rather than sent — the server refuses them outright, and a
    // rejected save would lose the whole schedule over one empty row.
    onSave(
      rows
        .filter(r => r.label.trim() !== '')
        .map(r => ({
          section: r.section,
          label: r.label.trim(),
          amount: r.amount.trim() === '' ? null : num(r.amount),
        })),
    )
    setEditing(false)
  }

  // ── Read-only ────────────────────────────────────────────────────────
  if (!editing) {
    return (
      <FacPanel
        title="Schedule — what is insured"
        action={
          <button className="fac-btn fac-btn--sm" onClick={() => setEditing(true)}>
            {schedule?.lineCount ? 'Edit schedule' : 'Add schedule'}
          </button>
        }
      >
        {!schedule?.lineCount ? (
          <FacNote>
            No schedule captured. The slip will state a single figure. Signed slips
            itemise the Fire &amp; Allied Perils and Business Interruption sums
            insured, so add them here for the slip to carry them.
          </FacNote>
        ) : (
          <>
            {schedule.sections.map(s => (
              <div key={s.section} style={{ marginBottom: 14 }}>
                <div className="fac-legend">{s.title}</div>
                <table className="fac-table">
                  <tbody>
                    {s.lines.map(l => (
                      <tr key={l.id}>
                        <td>{l.label}</td>
                        <td className="fac-num" style={{ width: '32%' }}>
                          {l.amount === null ? '' : facMoney(l.amount, '')}
                        </td>
                      </tr>
                    ))}
                    <tr className="fac-strong">
                      <td>Subtotal</td>
                      <td className="fac-num">{facMoney(s.subtotal, '')}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            ))}
            <table className="fac-table">
              <tbody>
                <tr className="fac-strong">
                  <td>TOTAL LIMITS OF INDEMNITY</td>
                  <td className="fac-num" style={{ width: '32%' }}>
                    {facMoney(schedule.totalLimitsOfIndemnity, '')}
                  </td>
                </tr>
              </tbody>
            </table>
          </>
        )}
      </FacPanel>
    )
  }

  // ── Editing ──────────────────────────────────────────────────────────
  return (
    <FacPanel
      title="Schedule — what is insured"
      action={
        <>
          <button className="fac-btn fac-btn--sm" disabled={saving}
                  onClick={() => { setEditing(false); setRows(toDraft(schedule)) }}>
            Cancel
          </button>{' '}
          <button className="fac-btn fac-btn--sm fac-btn--primary" disabled={saving} onClick={save}>
            {saving ? 'Saving…' : 'Save schedule'}
          </button>
        </>
      }
    >
      {FAC_SCHEDULE_SECTIONS.map(section => {
        const mine = rows.filter(r => r.section === section.key)

        return (
          <div key={section.key} style={{ marginBottom: 18 }}>
            <div className="fac-legend">{section.title}</div>

            {mine.length === 0 && (
              <p className="fac-hint" style={{ margin: '4px 0' }}>No lines yet.</p>
            )}

            <table className="fac-table">
              <tbody>
                {mine.map(r => (
                  <tr key={r.key}>
                    <td>
                      <input
                        type="text"
                        className="fac-input"
                        placeholder="e.g. Plant and machinery including generators"
                        value={r.label}
                        onChange={e => setRow(r.key, { label: e.target.value })}
                      />
                    </td>
                    <td style={{ width: '26%' }}>
                      <input
                        type="text"
                        inputMode="decimal"
                        className="fac-input fac-num"
                        /* Empty is a real state: a line can state a term and no
                           money. The placeholder says so rather than showing 0. */
                        placeholder="leave blank for none"
                        value={r.amount}
                        onChange={e => setRow(r.key, { amount: e.target.value })}
                      />
                    </td>
                    <td style={{ width: 96, whiteSpace: 'nowrap' }}>
                      <button className="fac-btn fac-btn--sm" title="Move up"
                              onClick={() => move(r.key, -1)}>↑</button>{' '}
                      <button className="fac-btn fac-btn--sm" title="Move down"
                              onClick={() => move(r.key, 1)}>↓</button>{' '}
                      <button className="fac-btn fac-btn--sm fac-btn--danger" title="Remove this line"
                              onClick={() => removeRow(r.key)}>×</button>
                    </td>
                  </tr>
                ))}
                {mine.length > 0 && (
                  <tr className="fac-strong">
                    <td>Subtotal</td>
                    <td className="fac-num">{facMoney(totals.bySection.get(section.key) ?? 0, '')}</td>
                    <td />
                  </tr>
                )}
              </tbody>
            </table>

            <button className="fac-btn fac-btn--sm" onClick={() => addRow(section.key)}>
              + Add line to {section.title.toLowerCase()}
            </button>
          </div>
        )
      })}

      {/* The figure the underwriter is checking their transcription against. */}
      <table className="fac-table">
        <tbody>
          <tr className="fac-strong">
            <td>TOTAL LIMITS OF INDEMNITY</td>
            <td className="fac-num" style={{ width: '26%' }}>{facMoney(totals.grand, '')}</td>
            <td style={{ width: 96 }} />
          </tr>
        </tbody>
      </table>

      {blankLabels > 0 && (
        <FacNote tone="warn">
          {blankLabels === 1 ? 'One line has' : `${blankLabels} lines have`} no
          description and will not be saved. An unlabelled figure means nothing to a
          reinsurer — name it, or remove the line.
        </FacNote>
      )}

      <FacNote>
        Saving replaces the whole schedule. Where a slip has already been generated
        it keeps the old schedule until you generate it again.
      </FacNote>
    </FacPanel>
  )
}
