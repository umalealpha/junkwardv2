import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { getStoredUser } from '../../api/auth'
import { useCreateHelpDeskTicket } from '../../hooks/useHelpDesk'
import { PRIORITY_OPTIONS, TYPE_OPTIONS, ASSIGNEE_SUGGESTIONS, type HelpDeskPriority, type HelpDeskType } from '../../api/helpdesk'

const MIN_DESCRIPTION = 100

interface ScreenshotSlot { key: 'screenshot1' | 'screenshot2' | 'screenshot3'; label: string; required: boolean }
const SCREENSHOT_SLOTS: ScreenshotSlot[] = [
  { key: 'screenshot1', label: 'Screenshot one', required: true },
  { key: 'screenshot2', label: 'Screenshot two', required: true },
  { key: 'screenshot3', label: 'Screenshot three (additional)', required: false },
]

export default function HelpDeskSubmitPage() {
  const navigate = useNavigate()
  const user = getStoredUser()
  const createTicket = useCreateHelpDeskTicket()

  const [title, setTitle] = useState('')
  const [priority, setPriority] = useState<HelpDeskPriority>('medium')
  const [type, setType] = useState<HelpDeskType>('bug')
  const [description, setDescription] = useState('')
  const [shots, setShots] = useState<Record<string, File | null>>({ screenshot1: null, screenshot2: null, screenshot3: null })
  const [attachment, setAttachment] = useState<File | null>(null)
  const [assigneeEmail, setAssigneeEmail] = useState<string>('')
  const [serverError, setServerError] = useState('')
  const [touched, setTouched] = useState(false)

  const trimmedLen = description.trim().length
  const tooShort = trimmedLen < MIN_DESCRIPTION
  const missingShots = !shots.screenshot1 || !shots.screenshot2
  const missingTitle = title.trim().length === 0

  function setShot(key: string, file: File | null) {
    setShots(s => ({ ...s, [key]: file }))
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setTouched(true)
    setServerError('')
    if (tooShort || missingShots || missingTitle) return

    try {
      const res = await createTicket.mutateAsync({
        title: title.trim(),
        priority,
        type,
        description: description.trim(),
        assignee_email: assigneeEmail.trim() || null,
        screenshot1: shots.screenshot1,
        screenshot2: shots.screenshot2,
        screenshot3: shots.screenshot3,
        attachment,
      })
      navigate('/help-desk', {
        state: { successMessage: `${res.message} Thank you — the team will pick it up.` },
      })
    } catch (err: any) {
      const msg = err?.response?.data?.message
        || err?.response?.data?.errors?.description?.[0]
        || 'Failed to submit your issue. Please try again.'
      setServerError(msg)
    }
  }

  const inp = 'w-full px-3 py-2 border rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500'

  return (
    <div className="p-6 max-w-3xl mx-auto space-y-6">
      <div className="flex items-center gap-3">
        <button
          onClick={() => navigate(-1)}
          className="text-gray-500 hover:text-gray-700 p-1 rounded"
          title="Back"
        >
          <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Report an Issue</h1>
          <p className="text-sm text-gray-500">Tell us what went wrong in Graphite V2 — be as specific as you can.</p>
        </div>
      </div>

      {/* Reporter — captured automatically from your SSO session */}
      <div className="flex items-center gap-2 bg-blue-50 border border-blue-200 text-blue-800 text-sm px-4 py-3 rounded-md">
        <svg className="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
        </svg>
        Raising as <span className="font-semibold">{user?.name || user?.email || 'current user'}</span>
        {user?.email && <span className="text-blue-600">({user.email})</span>}
      </div>

      {serverError && (
        <div className="bg-red-50 border border-red-300 text-red-700 text-sm px-4 py-3 rounded-md">
          {serverError}
        </div>
      )}

      <form onSubmit={handleSubmit} noValidate className="space-y-6">
        {/* Title + classification */}
        <section className="bg-white rounded-lg shadow-sm border p-5 space-y-4">
          <div>
            <label className="block text-sm font-semibold text-gray-700 mb-1">
              Title <span className="text-red-500">*</span>
            </label>
            <input
              type="text"
              value={title}
              onChange={e => setTitle(e.target.value)}
              maxLength={150}
              className={`${inp} ${touched && missingTitle ? 'border-red-400' : ''}`}
              placeholder="A short summary, e.g. “RealPay contract page shows blank premium”"
            />
            {touched && missingTitle && <p className="text-red-500 text-xs mt-1">Please give the issue a short title.</p>}
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Priority</label>
              <select value={priority} onChange={e => setPriority(e.target.value as HelpDeskPriority)} className={inp}>
                {PRIORITY_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Type</label>
              <select value={type} onChange={e => setType(e.target.value as HelpDeskType)} className={inp}>
                {TYPE_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
              </select>
            </div>
          </div>
        </section>

        {/* Description */}
        <section className="bg-white rounded-lg shadow-sm border p-5 space-y-3">
          <label className="block text-sm font-semibold text-gray-700">
            Issue Description <span className="text-red-500">*</span>
          </label>
          <textarea
            value={description}
            onChange={e => setDescription(e.target.value)}
            onBlur={() => setTouched(true)}
            rows={6}
            className={`${inp} resize-y ${touched && tooShort ? 'border-red-400' : ''}`}
            placeholder="Describe the issue: what you were doing, what you expected, and what actually happened. Include the policy / page / reference involved if relevant."
          />
          <div className="flex items-center justify-between text-xs">
            <span className={tooShort ? 'text-red-500' : 'text-green-600'}>
              {tooShort
                ? `At least ${MIN_DESCRIPTION} characters required — ${MIN_DESCRIPTION - trimmedLen} more to go.`
                : 'Looks good.'}
            </span>
            <span className="text-gray-400">{trimmedLen} characters</span>
          </div>
        </section>

        {/* Screenshots */}
        <section className="bg-white rounded-lg shadow-sm border p-5 space-y-4">
          <div>
            <h2 className="text-sm font-semibold text-gray-700">Screenshots <span className="text-red-500">*</span></h2>
            <p className="text-xs text-gray-500">At least <strong>two screenshots are required</strong>; a third is optional. Max 5MB each.</p>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            {SCREENSHOT_SLOTS.map(slot => (
              <ImageDrop
                key={slot.key}
                label={slot.label}
                required={slot.required}
                file={shots[slot.key]}
                onChange={(f) => setShot(slot.key, f)}
              />
            ))}
          </div>
          {touched && missingShots && (
            <p className="text-red-500 text-xs">Please attach the first two screenshots before submitting.</p>
          )}
        </section>

        {/* Attachment + assignee */}
        <section className="bg-white rounded-lg shadow-sm border p-5 space-y-4">
          <div>
            <label className="block text-sm font-semibold text-gray-700 mb-1">Add a file</label>
            <p className="text-xs text-gray-500 mb-2">Optional — any supporting document (log, PDF, export). Max 10MB.</p>
            <input
              type="file"
              onChange={e => setAttachment(e.target.files?.[0] ?? null)}
              className="block w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200"
            />
            {attachment && <p className="text-xs text-gray-500 mt-1">Selected: {attachment.name}</p>}
          </div>

          <div>
            <label className="block text-sm font-semibold text-gray-700 mb-1">Assign to (optional)</label>
            <p className="text-xs text-gray-500 mb-2">Type an email address or leave blank to assign later.</p>
            <input
              type="email"
              list="assignee-suggestions"
              value={assigneeEmail}
              onChange={e => setAssigneeEmail(e.target.value)}
              placeholder="e.g. developers@theriskco.com"
              className={inp}
            />
            <datalist id="assignee-suggestions">
              {ASSIGNEE_SUGGESTIONS.map(e => <option key={e} value={e} />)}
            </datalist>
          </div>
        </section>

        <div className="flex items-center justify-end gap-3">
          <button
            type="button"
            onClick={() => navigate('/help-desk')}
            className="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={createTicket.isPending || tooShort || missingShots || missingTitle}
            className="inline-flex items-center gap-1.5 px-5 py-2 bg-[#0B1272] text-white text-sm font-medium rounded-md hover:bg-[#0d1699] transition disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {createTicket.isPending ? 'Submitting…' : 'Submit Issue'}
          </button>
        </div>
      </form>
    </div>
  )
}

function ImageDrop({ label, required, file, onChange }: { label: string; required?: boolean; file: File | null; onChange: (f: File | null) => void }) {
  const preview = file ? URL.createObjectURL(file) : null
  return (
    <div className="space-y-1.5">
      <span className="block text-xs font-medium text-gray-600">
        {label} {required && <span className="text-red-500">*</span>}
      </span>
      <label className="relative flex flex-col items-center justify-center h-32 border-2 border-dashed border-gray-300 rounded-md cursor-pointer hover:border-blue-400 hover:bg-gray-50 transition overflow-hidden">
        {preview ? (
          <img src={preview} alt={label} className="absolute inset-0 w-full h-full object-cover" />
        ) : (
          <div className="flex flex-col items-center text-gray-400">
            <svg className="w-6 h-6 mb-1" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M4 6h16v12H4V6z" />
            </svg>
            <span className="text-xs">Click to upload</span>
          </div>
        )}
        <input
          type="file"
          accept="image/*"
          onChange={e => onChange(e.target.files?.[0] ?? null)}
          className="hidden"
        />
      </label>
      {file && (
        <button
          type="button"
          onClick={() => onChange(null)}
          className="text-xs text-red-500 hover:text-red-700"
        >
          Remove
        </button>
      )}
    </div>
  )
}
