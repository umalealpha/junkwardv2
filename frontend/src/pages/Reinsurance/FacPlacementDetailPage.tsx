import { useState } from 'react'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import apiClient from '../../api/client'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import { useToast } from '../../components/common/Toast'
import {
  FacPage, FacPageHead, FacPanel, FacFlags, FacStatusBadge, FacNote,
  FacRow, FacDialog, FacField,
} from '../../components/fac/FacUI'
import FacPlacementDialog, { facDraftFrom, addDays, todayLocal } from '../../components/fac/FacPlacementDialog'
import { FacParticipantsPanel } from '../../components/fac/FacParticipantsPanel'
import { FacScheduleEditor } from '../../components/fac/FacScheduleEditor'
import { FacSlipTermsDialog } from '../../components/fac/FacSlipTermsDialog'
import {
  fetchFacPlacement, markFacClientPaid, settleFacPlacement, cancelFacPlacement,
  uploadFacAttachment, downloadFacAttachment, syncFacPolicy, generateFacSlip,
  updateFacPlacement, uploadFacSignedSlip, fetchFacSlips, facMoney, facPct,
  type FacScheduleInput,
} from '../../api/fac'

/**
 * One placement, in full.
 *
 * The trail at the bottom matters as much as the figures: it shows who was told,
 * when, and whether the email actually left. Without that there is no way to
 * prove afterwards that the RI team was told about a cancellation before a
 * payment went out — which is the whole reason the register exists rather than a
 * spreadsheet.
 */
export default function FacPlacementDetailPage() {
  const { id } = useParams<{ id: string }>()
  const placementId = Number(id)
  const qc = useQueryClient()

  const [settleForm, setSettleForm] = useState({ open: false, reference: '', amount: '', date: '' })
  const [cancelForm, setCancelForm] = useState({ open: false, reason: '' })
  const [upload, setUpload] = useState({ open: false, docType: 'client_pop', paymentDate: '', amount: '', note: '' })
  const [file, setFile] = useState<File | null>(null)
  const [editing, setEditing] = useState(false)
  const [signedForm, setSignedForm] = useState({ open: false, date: '', note: '', signatory: '' })
  const [signedFile, setSignedFile] = useState<File | null>(null)
  const [searchParams] = useSearchParams()

  const { toast } = useToast()

  const q = useQuery({
    queryKey: ['fac-placement', placementId],
    queryFn: () => fetchFacPlacement(placementId),
    enabled: !Number.isNaN(placementId),
  })

  const refresh = () => {
    qc.invalidateQueries({ queryKey: ['fac-placement', placementId] })
    qc.invalidateQueries({ queryKey: ['fac-placements'] })
  }

  /*
   * The slip this placement prints on, fetched so its term-sheet wording can be
   * edited from HERE.
   *
   * Reinsurance reported a slip printing the deductible as "as per the original
   * policy" when the agreed term was "10% of each and every loss minimum
   * P5,000.00". The field was never missing — the only form for it was on the
   * settlements page, behind `reinsurance-fac-settle`, and the underwriter who
   * knows the deductible does not go there. The endpoint itself has always been
   * gated on `reinsurance-fac-slip`.
   *
   * `q.data?.` rather than `p.` because the hook has to run before this
   * component's early returns, and `p` is only narrowed after them.
   */
  const slipNo = q.data?.facSlipNo ?? null
  const slipQ = useQuery({
    queryKey: ['fac-slips', 'for-placement', slipNo],
    queryFn: () => fetchFacSlips({ search: slipNo as string, per_page: 25 }),
    enabled: !!slipNo,
    staleTime: 60 * 1000,
  })
  const [termsOpen, setTermsOpen] = useState(false)

  // The app's own toast, not alert(). Every other module reports this way, and a
  // native alert blocks the page and cannot say anything in the affirmative — so
  // a successful action had no way of confirming itself at all.
  const onError = (err: any) =>
    toast.error(err?.response?.data?.message || 'That action could not be completed.')

  const paidMut = useMutation({
    mutationFn: () => markFacClientPaid(placementId),
    onSuccess: () => { toast.success('Client premium recorded as received.'); refresh() },
    onError,
  })

  const syncMut = useMutation({
    mutationFn: () => syncFacPolicy(placementId),
    onSuccess: () => { toast.success('Policy details refreshed from Graphite.'); refresh() },
    onError,
  })

  // No onError: the dialog reads the 422 itself and marks the offending fields,
  // which is the whole reason it is shared with the capture screen.
  const editMut = useMutation({
    mutationFn: (payload: Record<string, unknown>) => updateFacPlacement(placementId, payload),
    /**
     * Confirm what actually happened, not merely that the request returned.
     *
     * The server writes no trail entry for an edit that changed nothing, so
     * "Edited successfully" on a no-op would be a small lie — and the capturer
     * would then go looking for a trail entry that was never written and report
     * THAT as the next defect. The count comes from the amendment the server
     * just recorded, so the number in the message is the number on the trail.
     */
    onSuccess: (updated) => {
      const newest = updated.events[0]
      const isAmendment = newest?.event === 'updated'
        && updated.events.length > (q.data?.events.length ?? 0)
      const changed = isAmendment ? (newest.changes?.length ?? 0) : 0

      if (changed > 0) {
        toast.success(`Edited successfully — ${changed} field${changed === 1 ? '' : 's'} updated.`)
      } else {
        toast.info('Nothing was changed, so nothing was recorded on the trail.')
      }

      refresh()
      setEditing(false)
    },
  })

  // Only needed once the correction dialog is open — the register's own list is a
  // separate query, so this keeps the detail page from fetching 200 reinsurers on
  // every visit just in case somebody edits.
  const counterparties = useQuery({
    queryKey: ['fac-counterparties'],
    queryFn: () => apiClient.get('/reinsurance/reinsurers', { params: { per_page: 200 } }).then(r => r.data),
    staleTime: 5 * 60 * 1000,
    enabled: editing,
  })

  const settleMut = useMutation({
    mutationFn: () => settleFacPlacement(placementId, {
      settlement_reference: settleForm.reference.trim(),
      settled_amount: Number(settleForm.amount),
      settled_at: settleForm.date || undefined,
    }),
    onSuccess: () => {
      toast.success('Settlement recorded.')
      refresh()
      setSettleForm({ open: false, reference: '', amount: '', date: '' })
    },
    onError,
  })

  const cancelMut = useMutation({
    mutationFn: () => cancelFacPlacement(placementId, cancelForm.reason.trim()),
    onSuccess: () => {
      toast.success('Placement cancelled and the payable reversed.')
      refresh()
      setCancelForm({ open: false, reason: '' })
    },
    onError,
  })

  const uploadMut = useMutation({
    mutationFn: () => {
      const fd = new FormData()
      fd.append('file', file as File)
      fd.append('doc_type', upload.docType)
      if (upload.paymentDate) fd.append('payment_date', upload.paymentDate)
      if (upload.amount) fd.append('amount', upload.amount)
      if (upload.note) fd.append('note', upload.note)
      return uploadFacAttachment(placementId, fd)
    },
    onSuccess: () => {
      toast.success('Document attached.')
      refresh()
      setUpload({ open: false, docType: 'client_pop', paymentDate: '', amount: '', note: '' })
      setFile(null)
    },
    onError,
  })

  const signedMut = useMutation({
    mutationFn: () => {
      const fd = new FormData()
      fd.append('file', signedFile as File)
      fd.append('slip_signed_date', signedForm.date)
      if (signedForm.note) fd.append('note', signedForm.note)
      if (signedForm.signatory) fd.append('signatory_name', signedForm.signatory)
      return uploadFacSignedSlip(placementId, fd)
    },
    // The promotion out of draft is the part worth saying out loud: it is the
    // moment the placement starts counting as money owed.
    onSuccess: (updated) => {
      toast.success(updated.status === 'placed' && q.data?.status === 'draft'
        ? 'Signed slip filed. This placement is now payable.'
        : 'Signed slip filed.')
      refresh()
      setSignedForm({ open: false, date: '', note: '', signatory: '' })
      setSignedFile(null)
    },
    onError,
  })

  /**
   * The schedule saves on its own, not through the placement edit.
   *
   * It is its own document — fifteen lines an underwriter transcribes from a
   * schedule — and folding it into the general edit would mean a rejected field
   * somewhere else discarded the whole transcription. Its own mutation also means
   * its own toast, which can say what the slip now needs.
   */
  const scheduleMut = useMutation({
    mutationFn: (schedule: FacScheduleInput[]) =>
      updateFacPlacement(placementId, { schedule }),
    onSuccess: (updated) => {
      const total = updated.schedule?.totalLimitsOfIndemnity ?? 0
      const lines = updated.schedule?.lineCount ?? 0
      toast.success(
        lines === 0
          ? 'Schedule cleared.'
          : `Schedule saved — ${lines} ${lines === 1 ? 'line' : 'lines'}, total limits of indemnity ${facMoney(total, 'BWP')}.`
          + (updated.slipGeneratedAt ? ' Generate the slip again for it to carry this.' : ''),
      )
      refresh()
    },
    onError,
  })

  const slipMut = useMutation({
    mutationFn: (slipNo: string) => generateFacSlip({ slip_no: slipNo, placement_type: p?.placementType }),
    onSuccess: () => {
      toast.success('Slip generated. Send it from the Settlements screen.')
      refresh()
    },
    onError,
  })

  if (q.isLoading) return <div className="p-12 text-center"><LoadingSpinner size="lg" /></div>
  if (q.isError || !q.data) {
    return <FacPage><FacNote tone="danger">This placement could not be loaded.</FacNote></FacPage>
  }

  const p = q.data

  /*
   * EXACT match, not whatever the search returned. fetchFacSlips matches slip_no
   * with LIKE, so a placement on slip "2026-11" also gets back "2026-113" and
   * would offer to edit the wrong document's wording.
   */
  const slip = slipQ.data?.data.find(s => s.slipNo === p.facSlipNo) ?? null

  const canSettle = p.status === 'ready_to_settle' || p.status === 'client_paid'
  const isClosed = p.status === 'settled' || p.status === 'cancelled'

  /**
   * The one thing to do next, worked out from the record rather than from how the
   * capturer arrived.
   *
   * Deriving it means the guidance is right on every visit, not only in the moment
   * after capture — a draft that has been sitting for a fortnight still says what
   * it is waiting for. The `?next=` the capture flow arrives with only decides
   * whether to open the dialog straight away.
   */
  const hasSignedSlip = p.attachments.some(a => a.docType === 'fac_slip')
  const nextStep: null | 'generate' | 'await-signature' | 'file-signed' =
    isClosed ? null
    : p.status === 'draft' && !p.slipGeneratedAt ? 'generate'
    : p.status === 'draft' ? 'await-signature'
    : !p.slipSignedDate || !hasSignedSlip ? 'file-signed'
    : null

  const arrivedFor = searchParams.get('next')

  return (
    <FacPage>
      <FacPageHead
        eyebrow={<Link to="/reinsurance/fac" className="fac-link" style={{ fontSize: 12 }}>← FAC Register</Link>}
        title={p.facReference}
        blurb={[
          p.placementTypeLabel,
          p.facSlipNo ? `slip ${p.facSlipNo}` : 'no slip number',
          p.financialYear || null,
          p.isReversal ? 'reversal row' : null,
        ].filter(Boolean).join(' · ')}
        actions={
          <>
            <FacStatusBadge status={p.status} />
            {/*
              Hidden once the line is settled or cancelled, matching the server:
              a settled placement has been paid, so the figure has left the
              building and a correction to it is a reversal, not an edit.

              Labelled "Edit details", not "Correct details". The domain word for
              this is a correction, and the trail still records it as an amendment
              — but Reinsurance asked for "edit capability" and then walked past
              this button three times looking for the word "Edit". A control nobody
              recognises is not a control.
            */}
            {!isClosed && (
              <button className="fac-btn fac-btn--sm" onClick={() => setEditing(true)}>
                Edit details
              </button>
            )}
            <button className="fac-btn fac-btn--sm" onClick={() => syncMut.mutate()} disabled={syncMut.isPending}>
              {syncMut.isPending ? 'Refreshing…' : 'Refresh from Graphite'}
            </button>
            {p.facSlipNo && (
              <button className="fac-btn fac-btn--sm" onClick={() => slipMut.mutate(p.facSlipNo as string)}
                      disabled={slipMut.isPending}>
                {slipMut.isPending ? 'Generating…' : 'Generate slip'}
              </button>
            )}
            {/*
              The deductible and the rest of the term-sheet wording, next to the
              button that generates the document they print on. Shown only once a
              slip exists and while it is still editable — `termsEditable` goes
              false when the slip has been sent, accepted or superseded, which is
              corrected by generating a new version, not by retyping the old one.
            */}
            {slip?.termsEditable && (
              <button className="fac-btn fac-btn--sm" onClick={() => setTermsOpen(true)}>
                Slip wording
              </button>
            )}
            {!isClosed && !p.clientPaidAt && (
              <button className="fac-btn fac-btn--sm fac-btn--act" onClick={() => paidMut.mutate()} disabled={paidMut.isPending}>
                {paidMut.isPending ? 'Checking…' : 'Client premium received'}
              </button>
            )}
            {canSettle && (
              <button className="fac-btn fac-btn--sm fac-btn--go"
                      onClick={() => setSettleForm(s => ({ ...s, open: true, amount: String(p.netCededPremium ?? '') }))}>
                Record settlement
              </button>
            )}
            {!isClosed && (
              <button className="fac-btn fac-btn--sm fac-btn--danger" onClick={() => setCancelForm({ open: true, reason: '' })}>
                Cancel placement
              </button>
            )}
          </>
        }
      />

      {p.flags.length > 0 && <FacFlags flags={p.flags} />}

      {/* ── The step that comes next ───────────────────────────────── */}
      {nextStep && (
        <FacPanel title="Next step">
          {nextStep === 'generate' && (
            <>
              <FacNote tone="warn">
                <strong>Not placed yet — the reinsurer has not signed.</strong> This is a draft,
                so it is <strong>not</strong> counted in the payable. Generate the slip and send
                it for signature.
              </FacNote>
              <div className="flex gap-2 mt-3">
                <button className="fac-btn fac-btn--primary fac-btn--sm"
                        disabled={!p.facSlipNo || slipMut.isPending}
                        onClick={() => slipMut.mutate(p.facSlipNo as string)}>
                  {slipMut.isPending ? 'Generating…' : 'Generate slip'}
                </button>
                {!p.facSlipNo && (
                  <span className="fac-hint" style={{ alignSelf: 'center' }}>
                    A slip number is needed first — add one with "Edit details".
                  </span>
                )}
              </div>
            </>
          )}

          {nextStep === 'await-signature' && (
            <>
              <FacNote>
                <strong>Slip generated{p.slipSentAt ? ' and sent' : ''}.</strong> Waiting on the
                reinsurer's signature. When the signed slip comes back, file it here — that is
                what sets the premium warranty date and moves this into the payable.
              </FacNote>
              <div className="mt-3">
                <button className="fac-btn fac-btn--go fac-btn--sm"
                        onClick={() => setSignedForm({ open: true, date: '', note: '', signatory: '' })}>
                  File the signed slip
                </button>
              </div>
            </>
          )}

          {nextStep === 'file-signed' && (
            <>
              <FacNote tone={p.slipSignedDate ? 'warn' : 'danger'}>
                {p.slipSignedDate
                  ? <><strong>The signed slip is not on file.</strong> The signing date is
                      recorded as {p.slipSignedDate}, but the document itself is missing — there
                      is nothing behind this payable if it is queried.</>
                  : <><strong>This placement is payable with no signed slip on file.</strong> Attach
                      it and record the date the reinsurer signed, or the premium warranty has no
                      date the register can enforce.</>}
              </FacNote>
              <div className="mt-3">
                <button className="fac-btn fac-btn--go fac-btn--sm"
                        onClick={() => setSignedForm({ open: true, date: p.slipSignedDate ?? '', note: '', signatory: '' })}>
                  File the signed slip
                </button>
              </div>
            </>
          )}
        </FacPanel>
      )}

      <div className="grid md:grid-cols-2 gap-4">

        <FacPanel title="Policy">
          <div className="fac-dl">
            <FacRow k="Policy number" v={p.policyNumber} />
            <FacRow k="Insured" v={p.insuredName} />
            <FacRow k="Cover" v={p.riGroupLabel} />
            <FacRow k="Policy type" v={p.policyType} />
            <FacRow k="Period" v={p.periodFrom || p.periodTo ? `${p.periodFrom || '?'} to ${p.periodTo || '?'}` : null} />
            <FacRow k="Status in Graphite" v={p.policyStatus} />
          </div>
          {!p.policyId && (
            <div className="mt-3">
              <FacNote tone="danger">
                This policy number does not exist in Graphite. It is registered so the payable is
                complete, but nothing can be read automatically — the client receipt has to be
                proved by upload.
              </FacNote>
            </div>
          )}
        </FacPanel>

        <FacPanel title="Counterparties">
          <div className="fac-dl">
            <FacRow k="We pay" v={p.counterpartyName} strong />
            <FacRow k="Risk carried by" v={p.riskCarrier} />
            <FacRow k="Underwriter" v={p.underwriterName} />
            <FacRow k="Sum insured ceded" v={facMoney(p.cessionSumInsured, p.currency)} />
            <FacRow k="Total risk %" v={facPct(p.riskPct)} />
            {p.sourcePremium !== null && (
              <FacRow k="Full policy premium" v={facMoney(p.sourcePremium, p.currency)} />
            )}
          </div>
        </FacPanel>

        <FacPanel title="Money">
          <div className="fac-dl">
            <FacRow k="Currency" v={p.currency} />
            <FacRow k="Gross ceded premium" v={facMoney(p.grossCededPremium, p.currency)} strong />
            <FacRow k="Commission" v={`${facPct(p.commissionPct)} — ${facMoney(p.commissionAmount, p.currency)}`} />
            <FacRow k="Net of commission" v={facMoney(p.netCededPremium, p.currency)} />
            <FacRow k="VAT" v={p.vatApplicable ? 'Botswana VAT applies' : 'VAT-exclusive'} />
            {p.vatApplicable && <FacRow k="VAT inside the gross" v={facMoney(p.vatAmount, p.currency)} />}
            <FacRow k="Gross excluding VAT" v={facMoney(p.grossExclVat, p.currency)} />
            {p.currency !== 'BWP' && (
              <>
                <FacRow k="Exchange rate" v={p.fxRate ? `${p.fxRate} (${p.fxRateDate || 'no date'})` : null} />
                <FacRow k="Rate source" v={p.fxRateSource} />
              </>
            )}
          </div>
          <div className="mt-3 pt-3" style={{ borderTop: '2px solid var(--fac-navy)' }}>
            <FacRow k="Payable in Pula" strong v={p.payableBwp === null ? null : facMoney(p.payableBwp)} />
            {p.payableBwp === null && (
              <div className="mt-2">
                <FacNote tone="warn">
                  No exchange rate is recorded, so this placement has no Pula value. It is not
                  being counted as Pula. Add the rate, its date and its source.
                </FacNote>
              </div>
            )}
          </div>
        </FacPanel>

        {/*
          Treaty conditions this placement trips. Above the participants, because
          whether the risk is inside the treaty at all comes before who is on it.
          Each finding names the document it comes from — a finding an underwriter
          cannot trace back to a slip is one they will overrule.
        */}
        {(p.mandates?.length ?? 0) > 0 && (
          <FacPanel
            title="Treaty conditions"
            action={p.policyMonths != null
              ? <span className="fac-hint">Policy period {p.policyMonths} months</span>
              : undefined}
          >
            {p.mandates!.map(m => (
              <FacNote key={m.code} tone={m.severity === 'info' ? undefined : m.severity}>
                <strong>{m.title}</strong>
                {/* Spans, not divs — FacNote renders a <p>. */}
                <span style={{ display: 'block', marginTop: 4 }}>{m.detail}</span>
                <span className="fac-hint" style={{ display: 'block', marginTop: 6 }}>{m.authority}</span>
              </FacNote>
            ))}
          </FacPanel>
        )}

        {/*
          Who is on the risk. Sits above the money legs deliberately — whether the
          panel has signed decides whether the cession is cover at all, and that
          question comes before what is owed on it.
        */}
        <FacParticipantsPanel participants={p.participants} />

        {/*
          What is insured, itemised. Below the participants because who carries the
          risk comes before the detail of it, and above the money legs because the
          schedule is what the premium was rated on.
        */}
        <FacScheduleEditor
          schedule={p.schedule}
          saving={scheduleMut.isPending}
          onSave={lines => scheduleMut.mutate(lines)}
        />

        <FacPanel title="The two money legs">
          <span className="fac-eyebrow">Leg A — client pays us</span>
          <div className="fac-dl mt-1">
            <FacRow k="Slip signed on" v={p.slipSignedDate} />
            <FacRow k="Payment window" v={p.ppwTerms || (p.ppwDays ? `${p.ppwDays} days` : null)} />
            <FacRow k="Premium payment warranty" v={p.ppwDueDate || 'not set'} />
            <FacRow k="Received" v={p.clientPaidAt ? `${p.clientPaidAt} (${p.clientPaidSource})` : 'not yet'} />
            {p.receipts && (
              <FacRow k="Graphite receipts to date"
                      v={`${facMoney(p.receipts.received)} (paid ${facMoney(p.receipts.paid)}, reversed ${facMoney(p.receipts.reversed)})`} />
            )}
          </div>

          <span className="fac-eyebrow" style={{ display: 'block', marginTop: 16 }}>Leg B — we pay the counterparty</span>
          <div className="fac-dl mt-1">
            <FacRow k="Settlement due" v={p.settlementDueDate} />
            <FacRow k="Settled" v={p.settledAt ? `${p.settledAt} — ${p.settlementReference}` : 'not yet'} />
          </div>
          <p className="fac-hint mt-2">
            The payment itself is raised and paid in omni. This register holds the status.
          </p>
        </FacPanel>

        {p.coverage && (
          <FacPanel title="Facultative cover check">
            <div className="fac-dl">
              <FacRow k="Verdict" v={p.coverage.verdictLabel} strong />
              <FacRow k="Graphite says required" v={facMoney(p.coverage.requiredPremium)} />
              <FacRow k="Registered here" v={facMoney(p.coverage.placedPremium)} />
              <FacRow k="Gap" v={facMoney(p.coverage.gap)} />
              <FacRow k="Placements on this policy" v={String(p.coverage.placedCount)} />
            </div>
            <p className="fac-hint mt-2">
              "Required" is Graphite's own facultative share on the policy's latest reinsurance
              computation — not an assumption.
            </p>
          </FacPanel>
        )}

        {/*
          WHAT THE SLIP WILL SAY, shown before it is printed.

          The only way Reinsurance found the deductible wrong was by reading the
          generated PDF and comparing it against the signed original. These are
          the terms the reinsurer signs, so they are worth seeing next to the
          placement they describe — and a term nobody has typed says so, rather
          than appearing as though somebody chose it.
        */}
        {slip && (
          <FacPanel
            title={`Slip wording (${slip.slipNo})`}
            action={slip.termsEditable
              ? <button className="fac-btn fac-btn--sm" onClick={() => setTermsOpen(true)}>Edit wording</button>
              : undefined}
          >
            <FacRow k="Deductible"
                    v={slip.deductibleText || 'as per the original policy.  (standing default)'} />
            <FacRow k="Description of risk"
                    v={slip.descriptionOfRisk || 'from the cover granted  (standing default)'} />
            <FacRow k="Territorial scope"
                    v={slip.territorialScope
                      || 'BOTSWANA and other Territories as Per Policy Document  (standing default)'} />
            <FacRow k="Risk ceded"
                    v={slip.riskCededText || 'derived from the panel  (standing default)'} />
            <FacRow k="Basis of cover"
                    v={slip.basisOfCover
                      ? slip.basisOfCoverSource === 'underwriter'
                        ? `${slip.basisOfCover}  (stated by the underwriter${
                            slip.basisPolicyValue ? `; the policy says ${slip.basisPolicyValue}` : ''})`
                        : `${slip.basisOfCover}  (from the policy)`
                      : 'not stated — the policy carries two bases, or none'} />
            <FacRow k="Notes" v={slip.slipNotes || 'none — the slip prints no Notes section'} />
            {slip.basisOfCoverSource === 'underwriter' && slip.basisPolicyValue
              && slip.basisOfCover !== slip.basisPolicyValue && (
              <FacNote tone="warn">
                This slip states a different basis of cover from the policy it reinsures.
                Agreed with the reinsurer on that basis, per the recorded override.
              </FacNote>
            )}
            {!slip.termsEditable && (
              <p className="fac-hint mt-2">
                This slip has been sent, accepted or superseded, so its wording is fixed.
                Correct it by generating a new version.
              </p>
            )}
          </FacPanel>
        )}

        <FacPanel
          title={`Documents (${p.attachments.length})`}
          action={!isClosed
            ? <button className="fac-btn fac-btn--sm" onClick={() => setUpload(u => ({ ...u, open: true }))}>Upload</button>
            : undefined}
        >
          {p.attachments.length === 0 ? (
            <p className="fac-hint">Nothing attached yet.</p>
          ) : (
            <div className="fac-dl">
              {p.attachments.map(a => (
                <div key={a.id} className="fac-dt-row">
                  <span style={{ minWidth: 0 }}>
                    <span className="fac-truncate fac-strong">{a.originalName}</span>
                    <span className="fac-hint">
                      {a.docType.replace(/_/g, ' ')}
                      {a.paymentDate ? ` · payment dated ${a.paymentDate}` : ''}
                      {a.amount !== null ? ` · ${facMoney(a.amount)}` : ''}
                      {a.uploadedBy ? ` · ${a.uploadedBy}` : ''}
                    </span>
                  </span>
                  <button
                    className="fac-link"
                    style={{ fontSize: 12, flex: 'none', border: 'none', background: 'none', cursor: 'pointer' }}
                    onClick={async () => {
                      const r = await downloadFacAttachment(placementId, a.id)
                      window.open(r.url, '_blank', 'noopener')
                    }}
                  >
                    Open
                  </button>
                </div>
              ))}
            </div>
          )}
        </FacPanel>
      </div>

      {/* ── The trail ──────────────────────────────────────────────── */}
      <FacPanel title={`Trail (${p.events.length})`}>
        {p.events.length === 0 ? (
          <p className="fac-hint">Nothing recorded yet.</p>
        ) : (
          <div className="fac-dl">
            {p.events.map(e => (
              <div key={e.id} className="fac-dt-row" style={{ alignItems: 'flex-start' }}>
                <span style={{ minWidth: 0 }}>
                  <span style={{ display: 'block' }}>{e.summary}</span>
                  <span className="fac-hint">
                    {e.at}{e.actor ? ` · ${e.actor}` : ''}
                    {e.notifiedTo ? ` · told: ${e.notifiedTo}` : ''}
                  </span>
                  {e.error && (
                    <span className="fac-hint" style={{ color: 'var(--fac-danger)' }}>
                      The email did not send: {e.error}. The event itself is still recorded.
                    </span>
                  )}
                  {/*
                    What the amendment actually did. Shown in full rather than
                    behind a "view changes" click: an audit trail that has to be
                    expanded field by field is one nobody checks, and the point of
                    recording the before value is that somebody reads it.
                  */}
                  {e.changes.length > 0 && (
                    <span className="fac-changes">
                      {e.changes.map(c => (
                        <span key={c.field} className="fac-change">
                          <span className="fac-change-field">{c.label}</span>
                          <span className="fac-change-from">{c.from ?? 'blank'}</span>
                          <span className="fac-change-arrow" aria-hidden="true">→</span>
                          <span className="fac-change-to">{c.to ?? 'blank'}</span>
                        </span>
                      ))}
                    </span>
                  )}
                </span>
                {e.notifiedTo && (
                  <span className={`fac-badge ${e.sent ? 'fac-badge--paid' : 'fac-badge--waiting'}`}
                        style={{ flex: 'none' }}>
                    {e.sent ? 'emailed' : 'not emailed'}
                  </span>
                )}
              </div>
            ))}
          </div>
        )}
      </FacPanel>

      {/* ── File the signed slip ───────────────────────────────────── */}
      {(signedForm.open || arrivedFor === 'signed-slip') && !isClosed && (
        <FacDialog
          title="File the signed slip"
          onClose={() => { setSignedForm({ open: false, date: '', note: '', signatory: '' }); setSignedFile(null) }}
          footer={
            <>
              <button
                className="fac-btn fac-btn--go flex-1"
                disabled={signedMut.isPending || !signedFile || !signedForm.date}
                onClick={() => signedMut.mutate()}
              >
                {signedMut.isPending ? 'Filing…' : 'File signed slip'}
              </button>
              <button className="fac-btn"
                      onClick={() => { setSignedForm({ open: false, date: '', note: '', signatory: '' }); setSignedFile(null) }}>
                Cancel
              </button>
            </>
          }
        >
          <FacNote tone={p.status === 'draft' ? 'warn' : undefined}>
            {p.status === 'draft'
              ? <>Filing this moves the placement out of draft and <strong>into the payable</strong>. The
                  premium warranty date is worked out from the signing date and the{' '}
                  {p.ppwDays ? `${p.ppwDays}-day` : 'agreed'} window.</>
              : <>The document and the signing date are filed together — the warranty date is
                  recalculated from the date you give here.</>}
          </FacNote>

          <div className="mt-3">
            <FacField label="Date the reinsurer signed *"
                      hint="As it appears on the slip. It cannot be in the future.">
              <input type="date" className="fac-input" value={signedForm.date}
                     max={todayLocal()}
                     onChange={e => setSignedForm(s => ({ ...s, date: e.target.value }))} />
            </FacField>
          </div>

          <div className="mt-3">
            <FacField label="The signed slip *" hint="PDF, image or Word document, up to 20MB.">
              <input type="file" className="fac-input" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                     onChange={e => setSignedFile(e.target.files?.[0] ?? null)} />
            </FacField>
          </div>

          <div className="mt-3">
            <FacField label="Who signed for the reinsurer"
                      hint="Optional — the person named on the acceptance panel. Left blank, the panel records the accepting company.">
              <input type="text" className="fac-input" value={signedForm.signatory}
                     onChange={e => setSignedForm(s => ({ ...s, signatory: e.target.value }))} />
            </FacField>
          </div>

          <div className="mt-3">
            <FacField label="Note" hint="Optional — anything worth recording about the signing.">
              <input type="text" className="fac-input" value={signedForm.note}
                     onChange={e => setSignedForm(s => ({ ...s, note: e.target.value }))} />
            </FacField>
          </div>

          {p.ppwDays && signedForm.date && (
            <p className="fac-hint mt-3">
              The client's premium will be due <strong>{addDays(signedForm.date, p.ppwDays)}</strong>{' '}
              — {p.ppwDays} days from signing.
            </p>
          )}
          {!p.ppwDays && (
            <div className="mt-3">
              <FacNote tone="warn">
                No payment window is recorded on this placement, so no premium due date can be
                worked out and no warranty alarm can fire on it. Add the window with "Correct
                details".
              </FacNote>
            </div>
          )}
        </FacDialog>
      )}

      {/* ── Correct ────────────────────────────────────────────────── */}
      {editing && (
        <FacPlacementDialog
          mode="edit"
          initial={facDraftFrom(p)}
          counterparties={counterparties.data?.data ?? []}
          onSave={payload => editMut.mutateAsync(payload)}
          onClose={() => setEditing(false)}
        />
      )}

      {/* ── Settle ─────────────────────────────────────────────────── */}
      {settleForm.open && (
        <FacDialog
          title="Record settlement"
          onClose={() => setSettleForm(s => ({ ...s, open: false }))}
          footer={
            <>
              <button className="fac-btn fac-btn--go flex-1" disabled={settleMut.isPending}
                onClick={() => {
                  if (!settleForm.reference.trim() || !settleForm.amount) {
                    toast.error('A settlement reference and an amount are both required.'); return
                  }
                  settleMut.mutate()
                }}>
                {settleMut.isPending ? 'Saving…' : 'Record settlement'}
              </button>
              <button className="fac-btn" onClick={() => setSettleForm(s => ({ ...s, open: false }))}>Cancel</button>
            </>
          }
        >
          <FacNote>
            The money itself moves in omni. Record here what was paid and its reference, so the
            register and the payment run agree.
          </FacNote>
          <div className="mt-3 space-y-3">
            <FacField label="Payment reference *">
              <input type="text" className="fac-input" value={settleForm.reference}
                onChange={e => setSettleForm(s => ({ ...s, reference: e.target.value }))} />
            </FacField>
            <FacField label={`Amount paid (${p.currency}) *`}>
              <input type="number" step="0.01" className="fac-input" value={settleForm.amount}
                onChange={e => setSettleForm(s => ({ ...s, amount: e.target.value }))} />
            </FacField>
            <FacField label="Date paid">
              <input type="date" className="fac-input" value={settleForm.date}
                onChange={e => setSettleForm(s => ({ ...s, date: e.target.value }))} />
            </FacField>
          </div>
        </FacDialog>
      )}

      {/* ── Cancel ─────────────────────────────────────────────────── */}
      {cancelForm.open && (
        <FacDialog
          title="Cancel this placement"
          onClose={() => setCancelForm({ open: false, reason: '' })}
          footer={
            <>
              <button className="fac-btn fac-btn--danger flex-1" disabled={cancelMut.isPending}
                onClick={() => { if (!cancelForm.reason.trim()) { toast.error('A reason is required to cancel a placement.'); return } cancelMut.mutate() }}>
                {cancelMut.isPending ? 'Cancelling…' : 'Cancel and reverse'}
              </button>
              <button className="fac-btn" onClick={() => setCancelForm({ open: false, reason: '' })}>Keep it</button>
            </>
          }
        >
          <FacNote tone="danger">
            This does not delete anything. The placement is marked cancelled, a reversing row is
            raised so the payable rolls back, and the RI team is told not to settle it.
          </FacNote>
          <div className="mt-3">
            <FacField label="Reason *">
              <textarea rows={3} className="fac-input" value={cancelForm.reason}
                onChange={e => setCancelForm(c => ({ ...c, reason: e.target.value }))} />
            </FacField>
          </div>
        </FacDialog>
      )}

      {/* ── Upload ─────────────────────────────────────────────────── */}
      {upload.open && (
        <FacDialog
          title="Upload a document"
          onClose={() => { setUpload(u => ({ ...u, open: false })); setFile(null) }}
          footer={
            <>
              <button className="fac-btn fac-btn--primary flex-1" disabled={uploadMut.isPending}
                onClick={() => { if (!file) { toast.error('Choose a file to attach.'); return } uploadMut.mutate() }}>
                {uploadMut.isPending ? 'Uploading…' : 'Upload'}
              </button>
              <button className="fac-btn" onClick={() => { setUpload(u => ({ ...u, open: false })); setFile(null) }}>Cancel</button>
            </>
          }
        >
          <div className="space-y-3">
            <FacField label="Type"
              hint={upload.docType === 'client_pop'
                ? 'Uploading this marks the client premium as received and tells Debtors and the RI team. Use it only where Graphite does not show the money.'
                : undefined}>
              <select className="fac-select" value={upload.docType}
                onChange={e => setUpload(u => ({ ...u, docType: e.target.value }))}>
                <option value="client_pop">Proof the client paid us</option>
                <option value="ri_payment_advice">Proof we paid the reinsurer</option>
                <option value="other">Other</option>
              </select>
            </FacField>
            <FacField label="File *">
              <input type="file" className="fac-input" onChange={e => setFile(e.target.files?.[0] ?? null)} />
            </FacField>
            <FacField label="Date of the payment being proved" hint="Not today's date — the date the money actually moved.">
              <input type="date" className="fac-input" value={upload.paymentDate}
                onChange={e => setUpload(u => ({ ...u, paymentDate: e.target.value }))} />
            </FacField>
            <FacField label="Amount">
              <input type="number" step="0.01" className="fac-input" value={upload.amount}
                onChange={e => setUpload(u => ({ ...u, amount: e.target.value }))} />
            </FacField>
            <FacField label="Note">
              <input type="text" className="fac-input" value={upload.note}
                onChange={e => setUpload(u => ({ ...u, note: e.target.value }))} />
            </FacField>
          </div>
        </FacDialog>
      )}

      {termsOpen && slip && (
        <FacSlipTermsDialog
          slip={slip}
          onClose={() => setTermsOpen(false)}
          onSaved={m => { toast.success(m); refresh() }}
          onError={m => toast.error(m)}
        />
      )}
    </FacPage>
  )
}
