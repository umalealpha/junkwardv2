import { useState, useEffect } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import {
  useHelpDeskTicket,
  useHelpDeskAudit,
  useHelpDeskComments,
  useAddHelpDeskComment,
  useHelpDeskSla,
  useAssignHelpDeskTicket,
  useUpdateHelpDeskStatus,
  useReopenHelpDeskTicket,
  useCloneHelpDeskTicket,
  useCloseHelpDeskTicket,
  useUpdateHelpDeskTicket,
  useAddHelpDeskAttachment,
  useDeleteHelpDeskAttachment,
} from '../../hooks/useHelpDesk'
import {
  downloadHelpDeskAttachment, fetchHelpDeskAttachmentObjectURL, ASSIGNEE_SUGGESTIONS,
  PRIORITY_OPTIONS, TYPE_OPTIONS, HELPDESK_ARCHIVED,
  type HelpDeskStatus, type HelpDeskAttachment, type HelpDeskPriority, type HelpDeskType,
  type HelpDeskSlaLeg, type SlaRagStatus,
} from '../../api/helpdesk'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import ImageLightbox, { type LightboxItem } from '../../components/common/ImageLightbox'
import { fmtDateTime } from '../../utils/format'

const STATUS_BADGE: Record<HelpDeskStatus, string> = {
  new:                 'bg-indigo-100 text-indigo-700',
  open:                'bg-blue-100 text-blue-700',
  in_progress:         'bg-yellow-100 text-yellow-700',
  pending_customer:    'bg-purple-100 text-purple-700',
  pending_third_party: 'bg-pink-100 text-pink-700',
  resolved:            'bg-green-100 text-green-700',
  closed:              'bg-gray-100 text-gray-600',
  reopened:            'bg-orange-100 text-orange-700',
}
const STATUS_LABEL: Record<HelpDeskStatus, string> = {
  new: 'New', open: 'Open', in_progress: 'In Progress',
  pending_customer: 'Pending Customer', pending_third_party: 'Pending Third Party',
  resolved: 'Resolved', closed: 'Closed', reopened: 'Reopened',
}
// Manually-settable statuses, in workflow order. Status only ever changes when
// a user clicks one of these — never on view, assign or edit. Reopen + Close
// have their own dedicated flows. The two pending_* states pause the SLA clock.
const STATUS_OPTIONS: { value: HelpDeskStatus; label: string }[] = [
  { value: 'new', label: 'New' },
  { value: 'open', label: 'Open' },
  { value: 'in_progress', label: 'In Progress' },
  { value: 'pending_customer', label: 'Pending Customer' },
  { value: 'pending_third_party', label: 'Pending Third Party' },
  { value: 'resolved', label: 'Resolved' },
  { value: 'closed', label: 'Closed' },
]
// Colour-coded priority / type pills so the header is scannable at a glance.
const PRIORITY_BADGE: Record<string, string> = {
  low:    'bg-slate-100 text-slate-600',
  medium: 'bg-amber-100 text-amber-700',
  high:   'bg-orange-100 text-orange-700',
  urgent: 'bg-red-100 text-red-700',
  critical: 'bg-red-100 text-red-700',
}
const TYPE_BADGE: Record<string, string> = {
  bug:      'bg-rose-100 text-rose-700',
  task:     'bg-indigo-100 text-indigo-700',
  question: 'bg-sky-100 text-sky-700',
  feature:  'bg-violet-100 text-violet-700',
  incident: 'bg-red-100 text-red-700',
  change:   'bg-teal-100 text-teal-700',
}
const STATUS_DOT: Record<HelpDeskStatus, string> = {
  new: 'bg-indigo-500', open: 'bg-blue-500', in_progress: 'bg-yellow-500',
  pending_customer: 'bg-purple-500', pending_third_party: 'bg-pink-500',
  resolved: 'bg-green-500', closed: 'bg-gray-400', reopened: 'bg-orange-500',
}

/** Image attachments get an in-app View (gallery); other files just download. */
function isHelpDeskImage(att: HelpDeskAttachment): boolean {
  return !!att.mime?.startsWith('image/') || att.slot === 'screenshot' || /\.(png|jpe?g|gif|webp|bmp|svg)$/i.test(att.originalName)
}

export default function HelpDeskDetailPage() {
  const { id } = useParams<{ id: string }>()
  const ticketId = Number(id)
  const navigate = useNavigate()

  const { data: ticket, isLoading, isError } = useHelpDeskTicket(ticketId)
  const { data: audit } = useHelpDeskAudit(ticketId)
  const { data: comments } = useHelpDeskComments(ticketId)
  const { data: sla } = useHelpDeskSla(ticketId)
  const commentMut = useAddHelpDeskComment(ticketId)
  const assignMut = useAssignHelpDeskTicket(ticketId)
  const statusMut = useUpdateHelpDeskStatus(ticketId)
  const closeMut = useCloseHelpDeskTicket(ticketId)
  const updateMut = useUpdateHelpDeskTicket(ticketId)
  const addAttachMut = useAddHelpDeskAttachment(ticketId)
  const deleteAttachMut = useDeleteHelpDeskAttachment(ticketId)
  const reopenMut = useReopenHelpDeskTicket(ticketId)
  const cloneMut = useCloneHelpDeskTicket(ticketId)

  const [assigneeEmail, setAssigneeEmail] = useState<string>('')
  const [feedback, setFeedback] = useState<string | null>(null)
  const [reopenReason, setReopenReason] = useState('')
  const [commentText, setCommentText] = useState('')
  const [downloading, setDownloading] = useState<number | null>(null)
  const [removingIdx, setRemovingIdx] = useState<number | null>(null)
  // Index (within the image-only list) the gallery lightbox opens at; null = closed.
  const [lightboxStart, setLightboxStart] = useState<number | null>(null)

  // Close flow — moving to "closed" requires a summary + proof screenshot.
  const [showClose, setShowClose] = useState(false)
  const [closeSummary, setCloseSummary] = useState('')
  const [closeShot, setCloseShot] = useState<File | null>(null)
  const [closeError, setCloseError] = useState('')

  // Edit flow — title / description / priority / type.
  const [showEdit, setShowEdit] = useState(false)
  const [editTitle, setEditTitle] = useState('')
  const [editDesc, setEditDesc] = useState('')
  const [editPriority, setEditPriority] = useState<HelpDeskPriority>('medium')
  const [editType, setEditType] = useState<HelpDeskType>('bug')
  const [editError, setEditError] = useState('')

  useEffect(() => {
    if (ticket) setAssigneeEmail(ticket.assigneeName ?? '')
  }, [ticket?.assigneeName])

  if (isLoading) {
    return <div className="p-12 flex justify-center"><LoadingSpinner size="lg" /></div>
  }
  if (isError || !ticket) {
    return (
      <div className="p-8 text-center">
        <p className="text-red-600 font-medium">Could not load this ticket.</p>
        <button onClick={() => navigate('/help-desk')} className="mt-4 text-sm text-blue-600 underline">Back to Help Desk</button>
      </div>
    )
  }

  async function handleAssign() {
    setFeedback(null)
    try {
      await assignMut.mutateAsync(assigneeEmail.trim() || null)
      setFeedback('Assignment updated.')
    } catch {
      setFeedback('Failed to update assignment.')
    }
  }

  async function handleStatus(status: HelpDeskStatus) {
    // Closing is special — it needs a summary + proof screenshot, so open
    // the modal instead of flipping status directly.
    if (status === 'closed') {
      setCloseSummary('')
      setCloseShot(null)
      setCloseError('')
      setShowClose(true)
      return
    }
    setFeedback(null)
    try {
      await statusMut.mutateAsync(status)
      setFeedback(`Status set to ${status.replace('_', ' ')}.`)
    } catch {
      setFeedback('Failed to update status.')
    }
  }

  async function handleConfirmClose() {
    setCloseError('')
    if (closeSummary.trim().length < 50 || !closeShot) return
    try {
      await closeMut.mutateAsync({ summary: closeSummary.trim(), screenshot: closeShot })
      setShowClose(false)
      setFeedback('Ticket closed.')
    } catch (err: any) {
      setCloseError(
        err?.response?.data?.message
        || err?.response?.data?.errors?.closing_screenshot?.[0]
        || 'Failed to close the ticket. Please try again.',
      )
    }
  }

  async function handleDownload(att: HelpDeskAttachment) {
    setDownloading(att.index)
    try {
      await downloadHelpDeskAttachment(ticketId, att)
    } finally {
      setDownloading(null)
    }
  }

  function openEdit() {
    if (!ticket) return
    setEditTitle(ticket.title ?? '')
    setEditDesc(ticket.description ?? '')
    setEditPriority(ticket.priority)
    setEditType(ticket.type)
    setEditError('')
    setShowEdit(true)
  }

  async function handleSaveEdit() {
    setEditError('')
    if (editTitle.trim().length === 0) { setEditError('Title is required.'); return }
    if (editDesc.trim().length < 100) { setEditError('Description must be at least 100 characters.'); return }
    try {
      await updateMut.mutateAsync({
        title: editTitle.trim(),
        description: editDesc.trim(),
        priority: editPriority,
        type: editType,
      })
      setShowEdit(false)
      setFeedback('Ticket updated.')
    } catch (err: any) {
      setEditError(err?.response?.data?.message || 'Failed to update the ticket. Please try again.')
    }
  }

  async function handleAddAttachment(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0]
    e.target.value = '' // allow re-selecting the same file
    if (!file) return
    setFeedback(null)
    try {
      const slot = file.type.startsWith('image/') ? 'screenshot' : 'attachment'
      await addAttachMut.mutateAsync({ file, slot })
      setFeedback('Attachment added.')
    } catch {
      setFeedback('Failed to add attachment.')
    }
  }

  async function handleDeleteAttachment(index: number, name: string) {
    if (!window.confirm(`Remove attachment "${name}"? This cannot be undone.`)) return
    setRemovingIdx(index)
    setFeedback(null)
    try {
      await deleteAttachMut.mutateAsync(index)
      setFeedback('Attachment removed.')
    } catch {
      setFeedback('Failed to remove attachment.')
    } finally {
      setRemovingIdx(null)
    }
  }

  async function handleAddComment() {
    const text = commentText.trim()
    if (text.length === 0) return
    setFeedback(null)
    try {
      await commentMut.mutateAsync(text)
      setCommentText('')
    } catch {
      setFeedback('Failed to add comment.')
    }
  }

  async function handleReopen() {
    if (reopenReason.trim().length < 10) return
    setFeedback(null)
    try {
      await reopenMut.mutateAsync(reopenReason.trim())
      setReopenReason('')
      setFeedback('Ticket reopened. The team will take another look.')
    } catch (err: any) {
      setFeedback(err?.response?.data?.message || 'Failed to reopen the ticket.')
    }
  }

  async function handleClone() {
    if (!window.confirm(`Raise a NEW ticket linked to ${ticket?.ticketRef}? Use this for a related or recurring issue — the original stays as-is.`)) return
    setFeedback(null)
    try {
      const created = await cloneMut.mutateAsync()
      navigate(`/help-desk/${created.id}`)
    } catch (err: any) {
      setFeedback(err?.response?.data?.message || 'Failed to raise a related ticket.')
    }
  }

  // Server is the single source of truth on who may reopen (reporter within
  // the window, or an agent/admin) — no duplicated role logic on the client.
  const canReopen = ticket.canReopen
  // Defensive: tolerate a response that predates these fields (e.g. a staged
  // frontend-before-backend rollout) so the page never white-screens on `.length`.
  const relatedChildren = ticket.relatedChildren ?? []

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center gap-3">
        <button onClick={() => navigate('/help-desk')} className="text-ink-muted hover:text-ink p-1 rounded" title="Back">
          <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <h1 className="text-2xl font-bold text-ink">{ticket.ticketRef}</h1>
        <span className={`px-2.5 py-0.5 rounded-full text-xs font-medium ${STATUS_BADGE[ticket.status]}`}>
          {STATUS_LABEL[ticket.status]}
        </span>
        <span className={`px-2.5 py-0.5 rounded-full text-xs font-medium capitalize ${PRIORITY_BADGE[String(ticket.priority).toLowerCase()] ?? 'bg-gray-100 text-gray-600'}`}>{ticket.priority} priority</span>
        <span className={`px-2.5 py-0.5 rounded-full text-xs font-medium capitalize ${TYPE_BADGE[String(ticket.type).toLowerCase()] ?? 'bg-gray-100 text-gray-600'}`}>{ticket.type}</span>
        {!HELPDESK_ARCHIVED && (
          <button
            onClick={openEdit}
            className="ml-auto inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-[#0B1272] border border-[#0B1272]/30 rounded-md hover:bg-[#0B1272]/5 transition"
            title="Edit ticket"
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            Edit
          </button>
        )}
      </div>

      {ticket.title && <h2 className="text-lg font-semibold text-ink-muted -mt-2">{ticket.title}</h2>}

      {HELPDESK_ARCHIVED && (
        <div className="bg-brand-navy/5 border border-brand-navy/20 text-brand-navy text-sm px-4 py-2.5 rounded-md">
          Read-only archive — this ticket pre-dates the move to Alpha Bridge and was migrated there.
          No further changes can be made here.
        </div>
      )}

      {feedback && (
        <div className="bg-blue-50 border border-blue-200 text-blue-800 text-sm px-4 py-2.5 rounded-md">{feedback}</div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Main */}
        <div className="lg:col-span-2 space-y-6">
          <section className="bg-surface rounded-lg shadow-sm border border-line p-5 space-y-2">
            <h2 className="text-sm font-semibold text-ink-muted uppercase tracking-wide">Description</h2>
            <p className="text-sm text-ink whitespace-pre-wrap">{ticket.description}</p>
          </section>

          <section className="bg-surface rounded-lg shadow-sm border border-line p-5 space-y-3">
            <div className="flex items-center justify-between">
              <h2 className="text-sm font-semibold text-ink-muted uppercase tracking-wide">Attachments</h2>
              {!HELPDESK_ARCHIVED && (
                <label className="inline-flex items-center gap-1.5 text-xs font-medium text-[#0B1272] cursor-pointer hover:underline">
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
                  </svg>
                  {addAttachMut.isPending ? 'Uploading…' : 'Add file / screenshot'}
                  <input type="file" className="hidden" disabled={addAttachMut.isPending} onChange={handleAddAttachment} />
                </label>
              )}
            </div>
            {ticket.attachments.length === 0 ? (
              <p className="text-sm text-ink-faint">No files attached.</p>
            ) : (
              <ul className="divide-y divide-line">
                {ticket.attachments.map(att => (
                  <li key={att.index} className="flex items-center justify-between py-2">
                    <div className="flex items-center gap-2 min-w-0">
                      <svg className="w-4 h-4 text-ink-faint flex-shrink-0" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                      </svg>
                      <span className="text-sm text-ink-muted truncate" title={att.originalName}>{att.originalName}</span>
                      <span className="text-xs text-ink-faint capitalize">({att.slot.replace('_', ' ')})</span>
                    </div>
                    <div className="flex items-center gap-3 flex-shrink-0">
                      {isHelpDeskImage(att) && (
                        <button
                          onClick={() => setLightboxStart(ticket.attachments.filter(isHelpDeskImage).findIndex(a => a.index === att.index))}
                          className="text-xs text-brand-navy hover:text-brand-orange font-medium"
                        >
                          View
                        </button>
                      )}
                      <button
                        onClick={() => handleDownload(att)}
                        disabled={downloading === att.index}
                        className="text-xs text-blue-600 hover:text-blue-800 font-medium disabled:opacity-50"
                      >
                        {downloading === att.index ? 'Downloading…' : 'Download'}
                      </button>
                      {!HELPDESK_ARCHIVED && (
                        <button
                          onClick={() => handleDeleteAttachment(att.index, att.originalName)}
                          disabled={removingIdx === att.index}
                          className="text-xs text-red-500 hover:text-red-700 font-medium disabled:opacity-50"
                        >
                          {removingIdx === att.index ? 'Removing…' : 'Delete'}
                        </button>
                      )}
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </section>

          {lightboxStart !== null && lightboxStart >= 0 && (
            <ImageLightbox
              items={ticket.attachments.filter(isHelpDeskImage).map((a): LightboxItem => ({
                name: a.originalName,
                load: () => fetchHelpDeskAttachmentObjectURL(ticketId, a),
              }))}
              startIndex={lightboxStart}
              onClose={() => setLightboxStart(null)}
            />
          )}

          {/* Discussion — user-entered collaboration notes. Distinct from the
              Activity/audit trail below, which is system-generated. */}
          <section className="bg-surface rounded-lg shadow-sm border border-line p-5 space-y-4">
            <h2 className="text-sm font-semibold text-ink-muted uppercase tracking-wide">Discussion</h2>

            {!comments || comments.length === 0 ? (
              <p className="text-sm text-ink-faint">No comments yet. Start the discussion below.</p>
            ) : (
              <ul className="space-y-4">
                {comments.map(c => (
                  <li key={c.id} className="flex gap-3">
                    <div className="flex-none w-8 h-8 rounded-full bg-[#0B1272]/10 text-[#0B1272] flex items-center justify-center text-xs font-semibold uppercase">
                      {(c.userName ?? '?').trim().charAt(0)}
                    </div>
                    <div className="min-w-0 flex-1">
                      <div className="flex items-baseline gap-2 flex-wrap">
                        <span className="text-sm font-medium text-ink">{c.userName || 'Unknown user'}</span>
                        <span className="text-xs text-ink-faint">{fmtDateTime(c.createdAt)}</span>
                      </div>
                      <p className="text-sm text-ink-muted whitespace-pre-wrap break-words mt-0.5">{c.comment}</p>
                    </div>
                  </li>
                ))}
              </ul>
            )}

            {/* Add a comment — author + timestamp are captured server-side.
                Hidden post-cutover: the archive is read-only. */}
            {!HELPDESK_ARCHIVED && (
              <div className="pt-1 border-t border-line space-y-2">
                <textarea
                  value={commentText}
                  onChange={e => setCommentText(e.target.value)}
                  rows={3}
                  maxLength={5000}
                  placeholder="Add an investigation note, update, or question…"
                  className="w-full px-3 py-2 border border-line rounded-md text-sm focus:ring-1 focus:ring-[#0B1272] resize-y"
                />
                <div className="flex justify-end">
                  <button
                    onClick={handleAddComment}
                    disabled={commentMut.isPending || commentText.trim().length === 0}
                    className="px-4 py-2 bg-[#0B1272] text-white text-sm font-medium rounded-md hover:bg-[#0d1699] transition disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    {commentMut.isPending ? 'Adding…' : 'Add Comment'}
                  </button>
                </div>
              </div>
            )}
          </section>

          {/* Activity / audit trail */}
          <section className="bg-surface rounded-lg shadow-sm border border-line p-5 space-y-3">
            <h2 className="text-sm font-semibold text-ink-muted uppercase tracking-wide">Activity</h2>
            {!audit || audit.length === 0 ? (
              <p className="text-sm text-ink-faint">No activity recorded yet.</p>
            ) : (
              <ol className="relative border-l border-line ml-1.5 space-y-4">
                {audit.map(entry => (
                  <li key={entry.id} className="ml-4">
                    <span className="absolute -left-[5px] mt-1.5 w-2.5 h-2.5 rounded-full bg-[#0B1272]" />
                    <p className="text-sm text-ink">{entry.description}</p>
                    <p className="text-xs text-ink-faint">
                      {entry.actor || 'System'}{entry.createdAt ? ` · ${fmtDateTime(entry.createdAt)}` : ''}
                    </p>
                  </li>
                ))}
              </ol>
            )}
          </section>
        </div>

        {/* Sidebar — triage */}
        <div className="space-y-6">
          <section className="bg-surface rounded-lg shadow-sm border border-line p-5 space-y-3 text-sm">
            <h2 className="text-sm font-semibold text-ink-muted uppercase tracking-wide">Details</h2>
            <div>
              <dt className="text-[11px] font-semibold uppercase tracking-wide text-ink-faint">Raised by</dt>
              <dd className="mt-1 flex items-center gap-2">
                <span className="w-7 h-7 rounded-full bg-brand-navy/10 text-brand-navy flex items-center justify-center text-xs font-semibold shrink-0">
                  {(ticket.reporterName?.charAt(0) || '?').toUpperCase()}
                </span>
                <span className="text-sm text-ink break-words min-w-0">{ticket.reporterName || '—'}</span>
              </dd>
            </div>
            <Field label="Email" value={ticket.reporterEmail} />
            <Field label="Opened" value={fmtDateTime(ticket.openedAt ?? ticket.createdAt)} />
            <Field label="Closed" value={ticket.closedAt ? fmtDateTime(ticket.closedAt) : '—'} />
            <Field label="Last updated" value={fmtDateTime(ticket.updatedAt)} />
          </section>

          {/* SLA panel — only renders when the ticket has an SLA (feature on). */}
          {sla && (
            <section className="bg-surface rounded-lg shadow-sm border border-line p-5 space-y-3 text-sm">
              <div className="flex items-center justify-between">
                <h2 className="text-sm font-semibold text-ink-muted uppercase tracking-wide">SLA</h2>
                {sla.paused && (
                  <span className="px-2 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-600">Paused</span>
                )}
              </div>
              <SlaLegRow label="Response" leg={sla.response} />
              <SlaLegRow label="Resolution" leg={sla.resolution} />
            </section>
          )}

          {ticket.status === 'closed' && ticket.closingSummary && (
            <section className="bg-green-50 rounded-lg shadow-sm border border-green-200 p-5 space-y-1">
              <h2 className="text-sm font-semibold text-green-800 uppercase tracking-wide">Resolution</h2>
              <p className="text-sm text-green-900">{ticket.closingSummary}</p>
            </section>
          )}

          {/* Related tickets — the one this was cloned from, and any raised off it. */}
          {(ticket.relatedTicketRef || relatedChildren.length > 0) && (
            <section className="bg-surface rounded-lg shadow-sm border border-line p-5 space-y-2 text-sm">
              <h2 className="text-sm font-semibold text-ink-muted uppercase tracking-wide">Related tickets</h2>
              {ticket.relatedTicketRef && (
                <p className="text-ink-muted">
                  Raised from{' '}
                  <button
                    onClick={() => ticket.relatedTicketId && navigate(`/help-desk/${ticket.relatedTicketId}`)}
                    className="text-[#0B1272] font-medium hover:underline"
                  >
                    {ticket.relatedTicketRef}
                  </button>
                </p>
              )}
              {relatedChildren.length > 0 && (
                <div className="space-y-1">
                  <p className="text-ink-muted text-xs">Related tickets raised from this one:</p>
                  <ul className="space-y-1">
                    {relatedChildren.map(c => (
                      <li key={c.id}>
                        <button onClick={() => navigate(`/help-desk/${c.id}`)} className="text-[#0B1272] font-medium hover:underline">
                          {c.ticketRef}
                        </button>
                        <span className="text-xs text-ink-faint ml-1.5">({STATUS_LABEL[c.status]})</span>
                      </li>
                    ))}
                  </ul>
                </div>
              )}
            </section>
          )}

          {/* Reopen — reporter (within window) or agent/admin; a reason is required. */}
          {!HELPDESK_ARCHIVED && canReopen && (
            <section className="bg-orange-50 rounded-lg shadow-sm border border-orange-200 p-5 space-y-2">
              <h2 className="text-sm font-semibold text-orange-800 uppercase tracking-wide">Not resolved?</h2>
              <p className="text-xs text-orange-700">If the issue isn't fully resolved, reopen this ticket and the team will take another look.</p>
              <textarea
                value={reopenReason}
                onChange={e => setReopenReason(e.target.value)}
                rows={2}
                maxLength={500}
                placeholder="Why are you reopening this? (at least 10 characters)"
                className="w-full px-3 py-2 border border-orange-200 rounded-md text-sm focus:ring-1 focus:ring-orange-500 resize-y"
              />
              <button
                onClick={handleReopen}
                disabled={reopenMut.isPending || reopenReason.trim().length < 10}
                className="w-full px-4 py-2 bg-orange-600 text-white text-sm font-medium rounded-md hover:bg-orange-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {reopenMut.isPending ? 'Reopening…' : 'Reopen Ticket'}
              </button>
            </section>
          )}

          {/* Raise a related ticket — a native CREATE path, frozen post-cutover
              (new tickets are raised in Alpha Bridge via the widget). */}
          {!HELPDESK_ARCHIVED && (
            <section className="bg-surface rounded-lg shadow-sm border border-line p-5 space-y-2">
              <h2 className="text-sm font-semibold text-ink-muted uppercase tracking-wide">Related issue?</h2>
              <p className="text-xs text-ink-muted">Raise a new ticket linked to this one — copies the details and keeps a reference back to {ticket.ticketRef}.</p>
              <button
                onClick={handleClone}
                disabled={cloneMut.isPending}
                className="w-full px-4 py-2 border border-[#0B1272] text-[#0B1272] text-sm font-medium rounded-md hover:bg-[#0B1272]/5 transition disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {cloneMut.isPending ? 'Creating…' : 'Raise a related ticket'}
              </button>
            </section>
          )}

          {!HELPDESK_ARCHIVED && (
            <section className="bg-surface rounded-lg shadow-sm border border-line p-5 space-y-3">
              <h2 className="text-sm font-semibold text-ink-muted uppercase tracking-wide">Assign</h2>
              <p className="text-xs text-ink-muted">Route this issue to whoever can solve it.</p>
              <input
                type="email"
                list="assignee-suggestions"
                value={assigneeEmail}
                onChange={e => setAssigneeEmail(e.target.value)}
                placeholder="e.g. developers@theriskco.com"
                className="w-full px-3 py-2 border border-line rounded-md text-sm focus:ring-1 focus:ring-blue-500"
              />
              <datalist id="assignee-suggestions">
                {ASSIGNEE_SUGGESTIONS.map(e => <option key={e} value={e} />)}
              </datalist>
              <button
                onClick={handleAssign}
                disabled={assignMut.isPending || assigneeEmail.trim() === (ticket.assigneeName ?? '')}
                className="w-full px-4 py-2 bg-[#0B1272] text-white text-sm font-medium rounded-md hover:bg-[#0d1699] transition disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {assignMut.isPending ? 'Saving…' : 'Update Assignment'}
              </button>
            </section>
          )}

          {!HELPDESK_ARCHIVED && (
            <section className="bg-surface rounded-lg shadow-sm border border-line p-5 space-y-3">
              <h2 className="text-sm font-semibold text-ink-muted uppercase tracking-wide">Status</h2>
              <div className="grid grid-cols-2 gap-2">
                {STATUS_OPTIONS.map(s => (
                  <button
                    key={s.value}
                    onClick={() => handleStatus(s.value)}
                    disabled={statusMut.isPending || ticket.status === s.value}
                    className={`flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border transition disabled:cursor-not-allowed ${
                      ticket.status === s.value
                        ? 'bg-[#0B1272] text-white border-[#0B1272]'
                        : 'text-ink-muted border-line hover:bg-surface-2'
                    }`}
                  >
                    <span className={`w-1.5 h-1.5 rounded-full ${ticket.status === s.value ? 'bg-white' : STATUS_DOT[s.value]}`} />
                    {s.label}
                  </button>
                ))}
              </div>
            </section>
          )}
        </div>
      </div>

      {/* Close modal — summary (≤50 chars) + mandatory proof screenshot */}
      {showEdit && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10" onClick={() => !updateMut.isPending && setShowEdit(false)}>
          <div className="bg-surface rounded-lg shadow-xl w-full max-w-lg p-5 space-y-3 max-h-[85vh] overflow-y-auto" onClick={e => e.stopPropagation()}>
            <h2 className="text-lg font-bold text-ink">Edit ticket {ticket.ticketRef}</h2>

            {editError && (
              <div className="bg-red-50 border border-red-300 text-red-700 text-sm px-3 py-2 rounded-md">{editError}</div>
            )}

            <div>
              <label className="block text-sm font-medium text-ink-muted mb-1">Title <span className="text-red-500">*</span></label>
              <input
                type="text"
                value={editTitle}
                maxLength={150}
                onChange={e => setEditTitle(e.target.value)}
                className="w-full px-3 py-2 border border-line rounded-md text-sm focus:ring-1 focus:ring-blue-500"
              />
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="block text-sm font-medium text-ink-muted mb-1">Priority</label>
                <select value={editPriority} onChange={e => setEditPriority(e.target.value as HelpDeskPriority)} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                  {PRIORITY_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium text-ink-muted mb-1">Type</label>
                <select value={editType} onChange={e => setEditType(e.target.value as HelpDeskType)} className="w-full px-3 py-2 border border-line rounded-md text-sm">
                  {TYPE_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
                </select>
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-ink-muted mb-1">Description <span className="text-red-500">*</span></label>
              <textarea
                value={editDesc}
                onChange={e => setEditDesc(e.target.value)}
                rows={6}
                className="w-full px-3 py-2 border border-line rounded-md text-sm focus:ring-1 focus:ring-blue-500 resize-y"
              />
              <div className="text-right text-xs mt-1">
                {editDesc.trim().length < 100
                  ? <span className="text-ink-faint">{100 - editDesc.trim().length} more characters needed (minimum 100)</span>
                  : <span className="text-green-600">{editDesc.trim().length} characters</span>}
              </div>
            </div>

            <div className="flex items-center justify-end gap-3 pt-2">
              <button
                type="button"
                onClick={() => setShowEdit(false)}
                disabled={updateMut.isPending}
                className="px-4 py-2 text-sm font-medium text-ink-muted hover:text-ink disabled:opacity-50"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={handleSaveEdit}
                disabled={updateMut.isPending || editTitle.trim().length === 0 || editDesc.trim().length < 100}
                className="px-5 py-2 bg-[#0B1272] text-white text-sm font-medium rounded-md hover:bg-[#0d1699] transition disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {updateMut.isPending ? 'Saving…' : 'Save Changes'}
              </button>
            </div>
          </div>
        </div>
      )}

      {showClose && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto" onClick={() => !closeMut.isPending && setShowClose(false)}>
          <div className="bg-surface rounded-lg shadow-xl w-full max-w-md p-5 space-y-3 max-h-[85vh] overflow-y-auto" onClick={e => e.stopPropagation()}>
            <h2 className="text-lg font-bold text-ink">Close ticket {ticket.ticketRef}</h2>
            <p className="text-sm text-ink-muted">Record a quick closing summary and attach a screenshot showing the issue is resolved.</p>

            {closeError && (
              <div className="bg-red-50 border border-red-300 text-red-700 text-sm px-3 py-2 rounded-md">{closeError}</div>
            )}

            <div>
              <label className="block text-sm font-medium text-ink-muted mb-1">Closing summary <span className="text-red-500">*</span></label>
              <textarea
                value={closeSummary}
                onChange={e => setCloseSummary(e.target.value)}
                rows={4}
                className="w-full px-3 py-2 border border-line rounded-md text-sm focus:ring-1 focus:ring-blue-500 resize-y"
                placeholder="Describe how the issue was resolved (at least 50 characters)…"
              />
              <div className="text-right text-xs mt-1">
                {closeSummary.trim().length < 50
                  ? <span className="text-ink-faint">{50 - closeSummary.trim().length} more character{50 - closeSummary.trim().length === 1 ? '' : 's'} needed (minimum 50)</span>
                  : <span className="text-green-600">{closeSummary.trim().length} characters</span>}
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-ink-muted mb-1">Proof screenshot <span className="text-red-500">*</span></label>
              <input
                type="file"
                accept="image/*"
                onChange={e => setCloseShot(e.target.files?.[0] ?? null)}
                className="block w-full text-sm text-ink-muted file:mr-3 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-surface-2 file:text-ink-muted hover:file:bg-surface-2"
              />
              {closeShot && <p className="text-xs text-ink-muted mt-1">Selected: {closeShot.name}</p>}
            </div>

            <div className="flex items-center justify-end gap-3 pt-2">
              <button
                type="button"
                onClick={() => setShowClose(false)}
                disabled={closeMut.isPending}
                className="px-4 py-2 text-sm font-medium text-ink-muted hover:text-ink disabled:opacity-50"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={handleConfirmClose}
                disabled={closeMut.isPending || closeSummary.trim().length < 50 || !closeShot}
                className="px-5 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {closeMut.isPending ? 'Closing…' : 'Confirm Close'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

function Field({ label, value }: { label: string; value: string | null | undefined }) {
  return (
    <div>
      <dt className="text-[11px] font-semibold uppercase tracking-wide text-ink-faint">{label}</dt>
      <dd className="mt-0.5 text-sm text-ink break-words">{value || '—'}</dd>
    </div>
  )
}

// ── SLA panel bits ────────────────────────────────────────────────────
const SLA_RAG: Record<SlaRagStatus, { label: string; chip: string; bar: string }> = {
  green:    { label: 'On track', chip: 'bg-green-100 text-green-700',  bar: 'bg-green-500' },
  amber:    { label: 'Due soon', chip: 'bg-amber-100 text-amber-700',  bar: 'bg-amber-500' },
  red:      { label: 'At risk',  chip: 'bg-red-100 text-red-700',      bar: 'bg-red-500' },
  met:      { label: 'Met',      chip: 'bg-green-100 text-green-700',  bar: 'bg-green-500' },
  met_late: { label: 'Met late', chip: 'bg-orange-100 text-orange-700',bar: 'bg-orange-500' },
}

function fmtMins(min: number | null): string {
  if (min === null) return '—'
  if (min <= 0) return '0m'
  const h = Math.floor(min / 60), m = min % 60
  return h > 0 ? (m > 0 ? `${h}h ${m}m` : `${h}h`) : `${m}m`
}

function SlaLegRow({ label, leg }: { label: string; leg: HelpDeskSlaLeg }) {
  const rag = SLA_RAG[leg.status]
  const chipLabel = leg.status === 'red' && leg.breached ? 'Breached' : rag.label
  return (
    <div className="space-y-1">
      <div className="flex items-center justify-between">
        <span className="text-ink-muted font-medium">{label}</span>
        <span className={`px-2 py-0.5 rounded-full text-[11px] font-medium ${rag.chip}`}>{chipLabel}</span>
      </div>
      <div className="flex items-center justify-between text-xs text-ink-muted">
        {leg.metAt
          ? <span>Met {fmtDateTime(leg.metAt)}</span>
          : leg.breached
            ? <span className="text-red-600">Overdue · due {fmtDateTime(leg.dueAt)}</span>
            : <span>{fmtMins(leg.remainingMinutes)} left · due {fmtDateTime(leg.dueAt)}</span>}
        <span className="tabular-nums">{leg.consumedPct}%</span>
      </div>
      <div className="h-1.5 bg-surface-2 rounded-full overflow-hidden">
        <div className={`h-full ${rag.bar}`} style={{ width: `${Math.min(100, leg.consumedPct)}%` }} />
      </div>
    </div>
  )
}
