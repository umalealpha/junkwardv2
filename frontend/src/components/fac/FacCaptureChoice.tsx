import { FacDialog, FacNote } from './FacUI'

/**
 * The question the register has to ask BEFORE the form opens.
 *
 * Capturing a placement we are about to make and recording one the reinsurer has
 * already signed are two different jobs, and they were sharing one screen. The
 * form could not help with either as a result: it asked for the slip signed date
 * on a placement nobody had signed yet, it captured everything as `placed` so an
 * unsigned line counted as money owed, and it left the capturer to work out on
 * their own whether the next step was to send a slip or to file one.
 *
 * Splitting it here, at the point of entry, is what lets everything downstream be
 * specific: which fields are asked for, whether the line joins the payable, and
 * which single action comes next.
 */

export type FacCaptureIntent = 'new' | 'signed'

export default function FacCaptureChoice({ onChoose, onClose }: {
  onChoose: (intent: FacCaptureIntent) => void
  onClose: () => void
}) {
  return (
    <FacDialog
      title="What are you capturing?"
      width="md"
      onClose={onClose}
      footer={<button className="fac-btn" onClick={onClose}>Cancel</button>}
    >
      <p className="fac-hint" style={{ marginTop: 0, marginBottom: 14 }}>
        These are two different processes. Pick the one you are doing and the rest of
        the capture follows it.
      </p>

      <div className="fac-choice-grid">
        <button type="button" className="fac-choice" onClick={() => onChoose('new')}>
          <span className="fac-choice-title">A new placement</span>
          <span className="fac-choice-sub">We are placing this risk now</span>
          <span className="fac-choice-body">
            The reinsurer has not signed yet. You give the risk and the terms, and the
            register produces the slip to send for signature.
          </span>
          <span className="fac-choice-next">
            <strong>Next:</strong> generate the slip and send it
          </span>
          <span className="fac-choice-flag">
            Held as a draft — it does not count as money owed until the signed slip is back.
          </span>
        </button>

        <button type="button" className="fac-choice" onClick={() => onChoose('signed')}>
          <span className="fac-choice-title">A placement already signed</span>
          <span className="fac-choice-sub">The reinsurer has signed the slip</span>
          <span className="fac-choice-body">
            You are recording something already agreed — off a signed slip in front of
            you, or a placement made before this register existed.
          </span>
          <span className="fac-choice-next">
            <strong>Next:</strong> attach the signed slip
          </span>
          <span className="fac-choice-flag">
            Counts as payable straight away, and the premium warranty runs from the
            signing date.
          </span>
        </button>
      </div>

      <div className="mt-3">
        <FacNote>
          Picked the wrong one? Nothing is lost — a placement can be corrected from its
          own screen, and the trail records the change.
        </FacNote>
      </div>
    </FacDialog>
  )
}
