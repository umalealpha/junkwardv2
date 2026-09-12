import { useEffect, useState } from 'react'
import Modal from '../common/Modal'
import { useToast } from '../common/Toast'
import {
  getClaimFormOptions,
  sendClaimForm,
  type ClaimFormOptions,
} from '../../api/claimFormDispatch'

interface Props {
  claimId: number
  claimNumber?: string | null
}

/**
 * "Send claim form" — the handler emails the claimant the right form: a
 * pre-filled PDF plus a link they can open with no password to complete it
 * online and upload their documents.
 *
 * A HUMAN picks the form on purpose. `claim_type = Accident` is 41% of the
 * claims book and nothing in the data reliably says whether those are motor
 * accidents, so an automatic choice would send the wrong form at scale. When
 * the suggestion is uncertain the dialog says so, rather than letting the
 * handler click straight through.
 *
 * Renders nothing when the feature is off — the options call 404s and the
 * button never appears.
 */
export default function SendClaimFormButton({ claimId, claimNumber }: Props) {
  const { toast } = useToast()

  const [available, setAvailable] = useState(false)
  const [open, setOpen] = useState(false)
  const [loading, setLoading] = useState(false)
  const [sending, setSending] = useState(false)
  const [options, setOptions] = useState<ClaimFormOptions | null>(null)

  const [formId, setFormId] = useState<number | ''>('')
  const [email, setEmail] = useState('')
  const [withPdf, setWithPdf] = useState(true)
  const [withLink, setWithLink] = useState(true)

  // Probe once so the button only exists where the feature is armed.
  useEffect(() => {
    let cancelled = false
    getClaimFormOptions(claimId)
      .then((o) => {
        if (cancelled) return
        setAvailable(true)
        setOptions(o)
      })
      .catch(() => {
        /* 404 = not switched on. Nothing to show and nothing to report. */
      })
    return () => {
      cancelled = true
    }
  }, [claimId])

  // Seed the dialog from what the server suggests, every time it opens, so a
  // handler who cancelled and reopened does not inherit a stale choice.
  useEffect(() => {
    if (!open || !options) return
    setFormId(options.suggestedFormId ?? '')
    setEmail(options.defaultEmail ?? '')
    setWithPdf(true)
    setWithLink(true)
  }, [open, options])

  if (!available) return null

  async function refresh() {
    setLoading(true)
    try {
      setOptions(await getClaimFormOptions(claimId))
    } catch {
      toast.error('Could not load the claim forms.')
    } finally {
      setLoading(false)
    }
  }

  async function handleSend() {
    if (!formId) {
      toast.error('Choose which form to send.')
      return
    }
    if (!email.trim()) {
      toast.error('Enter the email address to send it to.')
      return
    }
    if (!withPdf && !withLink) {
      toast.error('Send the form, the link, or both.')
      return
    }

    setSending(true)
    try {
      const res = await sendClaimForm(claimId, {
        form_id: Number(formId),
        to_email: email.trim(),
        include_pdf: withPdf,
        include_link: withLink,
      })
      toast.success(`${res.formTitle} sent.`)
      setOpen(false)
      void refresh()
    } catch (err: any) {
      toast.error(err?.response?.data?.message || 'Could not send the form.')
    } finally {
      setSending(false)
    }
  }

  const uncertain = options ? !options.suggestionIsCertain : false
  const resent = (options?.alreadySent ?? 0) > 0

  return (
    <>
      {/* The wrapper lives here, not on the page, so that while the feature is
          dark this component contributes nothing at all — no divider, no padding. */}
      <div className="px-5 pb-4 pt-3 flex flex-wrap gap-2 border-t border-line">
        <button
          onClick={() => {
            setOpen(true)
            void refresh()
          }}
          className="px-3 py-1.5 text-xs font-medium rounded-md border border-line text-ink-muted hover:bg-surface-2 transition"
        >
          Send claim form
        </button>
      </div>

      <Modal
        open={open}
        onClose={() => setOpen(false)}
        title="Send the claim form"
        size="md"
        footer={
          <div className="flex justify-end gap-2">
            <button
              onClick={() => setOpen(false)}
              className="px-3 py-1.5 text-xs font-medium rounded-md border border-line text-ink-muted hover:bg-surface-2 transition"
            >
              Cancel
            </button>
            <button
              onClick={handleSend}
              disabled={sending || loading}
              className="px-3 py-1.5 text-xs font-semibold rounded-md bg-primary text-primary-contrast hover:opacity-90 transition disabled:opacity-50"
            >
              {sending ? 'Sending…' : 'Send it'}
            </button>
          </div>
        }
      >
        <div className="space-y-4">
          <p className="text-sm text-ink-muted">
            Email the claimant the form for claim{' '}
            <span className="font-medium text-ink">{claimNumber || claimId}</span>. It goes out
            already filled in with what we hold, so they only complete what we cannot know.
          </p>

          {resent && (
            <p className="text-xs rounded-md px-3 py-2 bg-surface-2 text-ink-muted">
              A form has already been sent on this claim {options?.alreadySent} time
              {options?.alreadySent === 1 ? '' : 's'}.
            </p>
          )}

          <div>
            <label htmlFor="scf-form" className="block text-[11px] font-medium text-ink-muted mb-1">
              Which form
            </label>
            <select
              id="scf-form"
              value={formId}
              onChange={(e) => setFormId(e.target.value ? Number(e.target.value) : '')}
              className="w-full min-h-[44px] md:min-h-0 px-2.5 py-2 text-sm rounded-md bg-surface text-ink border border-line focus:outline-none focus:ring-1 focus:ring-primary"
            >
              <option value="">Choose a form</option>
              {(options?.forms ?? []).map((f) => (
                <option key={f.id} value={f.id}>
                  {f.form_title} — {f.claim_type}
                </option>
              ))}
            </select>

            {uncertain && (
              <p className="mt-1.5 text-[11px] rounded-md px-2.5 py-2 bg-amber-50 text-amber-900 border border-amber-200">
                This claim is recorded as{' '}
                <span className="font-medium">{options?.claimType}</span>, which does not tell us
                which form is right. Please check before sending.
              </p>
            )}
          </div>

          <div>
            <label htmlFor="scf-email" className="block text-[11px] font-medium text-ink-muted mb-1">
              Send to
            </label>
            <input
              id="scf-email"
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="name@example.com"
              className="w-full min-h-[44px] md:min-h-0 px-2.5 py-2 text-sm rounded-md bg-surface text-ink border border-line focus:outline-none focus:ring-1 focus:ring-primary"
            />
            {!options?.defaultEmail && (
              <p className="mt-1 text-[11px] text-ink-muted">
                We hold no email address for this customer — please type theirs in.
              </p>
            )}
          </div>

          <div className="space-y-2">
            <label className="flex items-center gap-2 text-sm text-ink min-h-[44px] md:min-h-0">
              <input type="checkbox" checked={withPdf} onChange={(e) => setWithPdf(e.target.checked)} />
              Attach the filled-in form as a PDF
            </label>
            <label className="flex items-center gap-2 text-sm text-ink min-h-[44px] md:min-h-0">
              <input type="checkbox" checked={withLink} onChange={(e) => setWithLink(e.target.checked)} />
              Include a link to fill it in online and upload documents
            </label>
          </div>
        </div>
      </Modal>
    </>
  )
}
