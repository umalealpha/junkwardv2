import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { FacDialog, FacField, FacNote } from './FacUI'
import { updateFacSlipTerms, type FacSlip } from '../../api/fac'

/**
 * The term-sheet wording — deductible, description of risk, territorial scope,
 * risk ceded, basis of cover.
 *
 * These are legal terms on the document the reinsurer signs. Until there was a
 * form for them every slip printed the migration default, whatever the placement
 * had actually agreed.
 *
 * WHY THIS IS A SHARED COMPONENT AND NOT PART OF A PAGE. It began on the
 * settlements page, which is reached with `reinsurance-fac-settle`. Reinsurance
 * reported the deductible printing as "as per the original policy" on a slip
 * whose real deductible was "10% of each and every loss minimum P5,000.00": the
 * field existed, and the underwriter who knew the answer could not get to it.
 * The endpoint has always been gated on `reinsurance-fac-slip`, not settle, so
 * the permission was never the obstacle — the location was. It now opens from
 * the placement as well, which is where the placement's terms are agreed.
 *
 * The caller owns the feedback: the settlements page alerts, the placement page
 * toasts. Falls back to `alert` so neither has to pass one.
 */
export function FacSlipTermsDialog({ slip, onClose, onSaved, onError }: {
  slip: FacSlip
  onClose: () => void
  onSaved?: (message: string) => void
  onError?: (message: string) => void
}) {
  const qc = useQueryClient()

  const [draft, setDraft] = useState({
    description_of_risk: slip.descriptionOfRisk ?? '',
    territorial_scope:   slip.territorialScope ?? '',
    deductible_text:     slip.deductibleText ?? '',
    risk_ceded_text:     slip.riskCededText ?? '',
    basis_of_cover:      slip.basisOfCover ?? '',
    slip_notes:          slip.slipNotes ?? '',
  })

  /*
   * Whether what is typed differs from the policy's own answer. Drives the
   * warning below, and mirrors the server's rule exactly — case- and
   * whitespace-insensitive, and never an override where the policy has no
   * answer to contradict.
   */
  const typed = draft.basis_of_cover.trim()
  const overridesPolicy = !!slip.basisPolicyValue
    && typed !== ''
    && typed.toLowerCase() !== slip.basisPolicyValue.trim().toLowerCase()

  const mut = useMutation({
    mutationFn: () => updateFacSlipTerms(slip.id, {
      description_of_risk: draft.description_of_risk.trim() || null,
      territorial_scope:   draft.territorial_scope.trim() || null,
      deductible_text:     draft.deductible_text.trim() || null,
      risk_ceded_text:     draft.risk_ceded_text.trim() || null,
      basis_of_cover:      typed || null,
      slip_notes:          draft.slip_notes.trim() || null,
    }),
    onSuccess: (r) => {
      qc.invalidateQueries({ queryKey: ['fac-slips'] })
      // The placement screen prints these terms too, and it is now one of the
      // two places this dialog opens from.
      qc.invalidateQueries({ queryKey: ['fac-placement'] })
      onClose()
      ;(onSaved ?? window.alert)(r.message)
    },
    onError: (err: any) => {
      const msg = err?.response?.data?.message || 'The wording could not be saved.'
      ;(onError ?? window.alert)(msg)
    },
  })

  return (
    <FacDialog
      title={`Slip ${slip.slipNo} — term-sheet wording`}
      onClose={onClose}
      footer={
        <>
          <button className="fac-btn" onClick={onClose}>Cancel</button>
          <button className="fac-btn fac-btn--primary"
                  disabled={mut.isPending}
                  onClick={() => mut.mutate()}>
            {mut.isPending ? 'Saving…' : 'Save wording'}
          </button>
        </>
      }
    >
      <FacNote>
        These rows print on the slip the reinsurer signs. Left blank, each prints its
        standing default — "as per the original policy" for the deductible, and the
        general territorial wording. Anything typed here carries forward when the slip
        is regenerated.
      </FacNote>

      <div className="mt-3">
        <FacField label="Description of risk"
                  hint="As it should read on the slip, e.g. PROFESSIONAL INDEMNITY INSURANCE.">
          <input type="text" className="fac-input" value={draft.description_of_risk}
                 onChange={e => setDraft(d => ({ ...d, description_of_risk: e.target.value }))} />
        </FacField>
      </div>

      <div className="mt-3">
        <FacField label="Deductible"
                  hint='e.g. "10% of each and every loss minimum P5,000.00". Blank prints "as per the original policy."'>
          <input type="text" className="fac-input" value={draft.deductible_text}
                 onChange={e => setDraft(d => ({ ...d, deductible_text: e.target.value }))} />
        </FacField>
      </div>

      <div className="mt-3">
        <FacField label="Territorial scope"
                  hint="Blank prints BOTSWANA and other Territories as Per Policy Document.">
          <input type="text" className="fac-input" value={draft.territorial_scope}
                 onChange={e => setDraft(d => ({ ...d, territorial_scope: e.target.value }))} />
        </FacField>
      </div>

      <div className="mt-3">
        <FacField label="Risk ceded"
                  hint='Blank derives it from the panel, e.g. "100% (Nil Retention by Reinsured)".'>
          <input type="text" className="fac-input" value={draft.risk_ceded_text}
                 onChange={e => setDraft(d => ({ ...d, risk_ceded_text: e.target.value }))} />
        </FacField>
      </div>

      <div className="mt-3">
        <FacField label="Basis of cover"
                  hint={slip.basisPolicyValue
                    ? `The policy states "${slip.basisPolicyValue}". Change it only where the `
                      + 'reinsurance was agreed on a different basis — the change is recorded.'
                    : 'The policy does not state one — carrying two bases, or none — so state it here.'}>
          {/*
            Two fixed values, not free text. Reinsurance named them: "these can
            be on a claims made basis or a claims occurrence basis". A select
            cannot produce wording no policy uses.

            THE VALUES CARRY " Basis" ON THE END, and that is not cosmetic.
            Production stores the labels as "Claims Occurring" and "Claims Made",
            and basisOfCoverFor() appends "Basis" because that is how the signed
            slips word it — so the policy answers "Claims Occurring Basis".
            Offering the bare label here would make picking the policy's own
            answer differ from it by one word and record a spurious override.

            The current value is kept as an option when it is neither, so opening
            this form on an older slip cannot silently retype it.
          */}
          <select className="fac-input" value={draft.basis_of_cover}
                  onChange={e => setDraft(d => ({ ...d, basis_of_cover: e.target.value }))}>
            <option value="">Not stated</option>
            <option value="Claims Occurring Basis">Claims Occurring Basis</option>
            <option value="Claims Made Basis">Claims Made Basis</option>
            {!!draft.basis_of_cover
              && !['Claims Occurring Basis', 'Claims Made Basis'].includes(draft.basis_of_cover) && (
              <option value={draft.basis_of_cover}>{draft.basis_of_cover}</option>
            )}
          </select>
        </FacField>
        {overridesPolicy && (
          <FacNote tone="warn">
            <strong>This slip will state a different basis from the policy it reinsures.</strong>{' '}
            The policy says "{slip.basisPolicyValue}"; the slip will say "{typed}". That is
            allowed where the reinsurance was agreed on that basis, and it is recorded on the
            placement trail with both values.
          </FacNote>
        )}
      </div>

      {/*
        Free text, and the only field here that is. The five rows above each
        answer one named question; Reinsurance asked for somewhere to put the
        placement conditions that do not fit one of them.
      */}
      <div className="mt-3">
        <FacField label="Notes"
                  hint="Placement terms not covered by the rows above. Printed on the slip under
                        a Notes heading, and left off entirely when blank.">
          <textarea className="fac-input" rows={5} value={draft.slip_notes}
                    onChange={e => setDraft(d => ({ ...d, slip_notes: e.target.value }))} />
        </FacField>
      </div>
    </FacDialog>
  )
}

export default FacSlipTermsDialog
