import { useState, useEffect, useCallback } from 'react'
import { createPortal } from 'react-dom'
import { getStoredRoles } from '../../api/auth'
import EmptyState from '../../components/common/EmptyState'
import {
  fetchReportSchedules, createReportSchedule, updateReportSchedule,
  deleteReportSchedule, previewReport, runReportScheduleNow,
  type ReportSchedule, type ReportTypeOption, type SchedulePayload,
} from '../../api/claimsReportSchedules'

const MANAGE_ROLES = ['Admin', 'admin', 'Super Admin']
const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

const EMPTY: SchedulePayload = {
  name: '', report_type: 'executive_kpi', frequency: 'weekly',
  day_of_week: 1, day_of_month: 1, hour: 7, recipients: [], enabled: false,
}

export default function ClaimReportSchedulesPage() {
  const roles = getStoredRoles()
  const canManage = roles.some(r => MANAGE_ROLES.includes(r))

  const [rows, setRows] = useState<ReportSchedule[]>([])
  const [flagOn, setFlagOn] = useState(false)
  const [reportTypes, setReportTypes] = useState<ReportTypeOption[]>([])
  const [loading, setLoading] = useState(true)

  const [modalOpen, setModalOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [form, setForm] = useState<SchedulePayload>(EMPTY)
  const [recipientsText, setRecipientsText] = useState('')
  const [saving, setSaving] = useState(false)
  const [busyId, setBusyId] = useState<number | null>(null)

  const [previewHtml, setPreviewHtml] = useState<string | null>(null)
  const [previewSubject, setPreviewSubject] = useState('')
  const [previewLoading, setPreviewLoading] = useState(false)

  const load = useCallback(() => {
    if (!canManage) return
    setLoading(true)
    fetchReportSchedules()
      .then(r => { setRows(r.data); setFlagOn(r.flagOn); setReportTypes(r.reportTypes) })
      .catch(() => setRows([]))
      .finally(() => setLoading(false))
  }, [canManage])
  useEffect(() => { load() }, [load])

  if (!canManage) {
    return (
      <div className="p-6">
        <EmptyState title="Access restricted" description="Claims report schedules are available to Admin and Super Admin only." />
      </div>
    )
  }

  function openCreate() {
    setEditingId(null); setForm(EMPTY); setRecipientsText(''); setModalOpen(true)
  }
  function openEdit(s: ReportSchedule) {
    setEditingId(s.id)
    setForm({
      name: s.name, report_type: s.reportType, frequency: s.frequency,
      day_of_week: s.dayOfWeek ?? 1, day_of_month: s.dayOfMonth ?? 1,
      hour: s.hour, recipients: s.recipients, enabled: s.enabled,
    })
    setRecipientsText(s.recipients.join(', '))
    setModalOpen(true)
  }

  function parseRecipients(text: string): string[] {
    return text.split(/[,;\n]/).map(e => e.trim()).filter(Boolean)
  }

  async function handleSave() {
    setSaving(true)
    try {
      const payload: SchedulePayload = {
        ...form,
        recipients: parseRecipients(recipientsText),
        day_of_week: form.frequency === 'weekly' ? form.day_of_week : null,
        day_of_month: form.frequency === 'monthly' ? form.day_of_month : null,
      }
      if (editingId) await updateReportSchedule(editingId, payload)
      else await createReportSchedule(payload)
      setModalOpen(false); load()
    } catch (e: any) {
      alert(e.response?.data?.message || 'Failed to save schedule.')
    } finally { setSaving(false) }
  }

  async function handleDelete(s: ReportSchedule) {
    if (!confirm(`Delete schedule "${s.name}"?`)) return
    try { await deleteReportSchedule(s.id); load() }
    catch (e: any) { alert(e.response?.data?.message || 'Failed to delete.') }
  }

  async function handleRunNow(s: ReportSchedule) {
    setBusyId(s.id)
    try {
      const res = await runReportScheduleNow(s.id)
      alert(res.message)
      load()
    } catch (e: any) { alert(e.response?.data?.message || 'Run failed.') }
    finally { setBusyId(null) }
  }

  async function handlePreview(params: { schedule_id?: number; report_type?: string; frequency?: string }) {
    setPreviewLoading(true)
    try {
      const res = await previewReport(params)
      setPreviewHtml(res.html)
      setPreviewSubject(res.subject)
    } catch (e: any) { alert(e.response?.data?.message || 'Preview failed.') }
    finally { setPreviewLoading(false) }
  }

  function scheduleTiming(s: ReportSchedule): string {
    const hh = String(s.hour).padStart(2, '0') + ':00'
    if (s.frequency === 'daily') return `Daily at ${hh}`
    if (s.frequency === 'weekly') return `Weekly on ${DAYS[s.dayOfWeek ?? 0]} at ${hh}`
    return `Monthly on day ${s.dayOfMonth ?? 1} at ${hh}`
  }

  return (
    <div className="p-6 space-y-5">
      <div className="flex items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Claims Report Schedules</h1>
          <p className="text-sm text-gray-500">Scheduled executive KPI email reports. Manage schedules any time; emails are only sent when the feature flag is on, the schedule is enabled, and it has recipients.</p>
        </div>
        <button onClick={openCreate} className="px-4 py-2 bg-brand-navy text-white text-sm rounded-md hover:opacity-90 whitespace-nowrap">+ New Schedule</button>
      </div>

      {!flagOn && (
        <div className="rounded-md bg-amber-50 border border-amber-200 px-4 py-2.5 text-sm text-amber-800">
          <strong>Sending is OFF.</strong> The <code>claims_scheduled_reports</code> flag is disabled, so due schedules render and log but send no email. Preview and "Run now" work; enable the flag in Admin → Integrations to start sending.
        </div>
      )}

      <div className="flex flex-wrap items-center gap-2">
        <button onClick={() => handlePreview({ report_type: 'executive_kpi', frequency: 'weekly' })}
          className="px-3 py-1.5 text-sm border rounded-md hover:bg-gray-50">Preview Executive KPI (weekly)</button>
        <button onClick={() => handlePreview({ report_type: 'pending_digest', frequency: 'weekly' })}
          className="px-3 py-1.5 text-sm border rounded-md hover:bg-gray-50">Preview Pending Digest</button>
        {previewLoading && <span className="text-sm text-gray-400">Rendering…</span>}
      </div>

      <div className="bg-white shadow rounded-lg overflow-hidden">
        <table className="min-w-full divide-y divide-gray-200 text-sm">
          <thead className="bg-gray-50"><tr>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Report</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">When</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Recipients</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Enabled</th>
            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last run</th>
            <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
          </tr></thead>
          <tbody className="divide-y divide-gray-200">
            {loading && <tr><td colSpan={7} className="px-4 py-8 text-center text-gray-400">Loading…</td></tr>}
            {!loading && rows.length === 0 && <tr><td colSpan={7} className="p-0"><EmptyState compact title="No schedules yet" description="Create a schedule to start emailing KPI reports." /></td></tr>}
            {rows.map(s => (
              <tr key={s.id} className="hover:bg-gray-50">
                <td className="px-4 py-3 font-medium">{s.name}</td>
                <td className="px-4 py-3 text-gray-600">{s.reportLabel}</td>
                <td className="px-4 py-3 text-gray-600">{scheduleTiming(s)}</td>
                <td className="px-4 py-3 text-gray-600">{s.recipients.length}</td>
                <td className="px-4 py-3">
                  <span className={`px-2 py-0.5 text-xs rounded-full ${s.enabled ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                    {s.enabled ? 'Enabled' : 'Disabled'}
                  </span>
                </td>
                <td className="px-4 py-3 text-xs text-gray-500">
                  {s.lastRunAt ? <div>{new Date(s.lastRunAt).toLocaleString()}</div> : '—'}
                  {s.lastStatus && <div className="text-gray-400">{s.lastStatus}</div>}
                </td>
                <td className="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                  <button onClick={() => handlePreview({ schedule_id: s.id })} className="text-sm text-brand-navy hover:underline">Preview</button>
                  <button disabled={busyId === s.id} onClick={() => handleRunNow(s)} className="text-sm text-brand-navy hover:underline disabled:opacity-40">Run now</button>
                  <button onClick={() => openEdit(s)} className="text-sm text-blue-600 hover:underline">Edit</button>
                  <button onClick={() => handleDelete(s)} className="text-sm text-red-600 hover:underline">Delete</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Create/edit modal */}
      {modalOpen && createPortal(
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto"
          onClick={e => { if (e.target === e.currentTarget) setModalOpen(false) }}>
          <div className="bg-white rounded-lg shadow-xl w-full max-w-lg p-5 space-y-3 max-h-[85vh] overflow-y-auto">
            <h2 className="text-lg font-bold">{editingId ? 'Edit Schedule' : 'New Schedule'}</h2>
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Name *</label>
              <input value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} className="w-full px-3 py-2 border rounded-md text-sm" />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">Report type</label>
                <select value={form.report_type} onChange={e => setForm({ ...form, report_type: e.target.value })} className="w-full px-3 py-2 border rounded-md text-sm">
                  {reportTypes.map(t => <option key={t.code} value={t.code}>{t.label}</option>)}
                </select>
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">Frequency</label>
                <select value={form.frequency} onChange={e => setForm({ ...form, frequency: e.target.value as SchedulePayload['frequency'] })} className="w-full px-3 py-2 border rounded-md text-sm">
                  <option value="daily">Daily</option>
                  <option value="weekly">Weekly</option>
                  <option value="monthly">Monthly</option>
                </select>
              </div>
              {form.frequency === 'weekly' && (
                <div>
                  <label className="block text-xs font-medium text-gray-500 mb-1">Day of week</label>
                  <select value={form.day_of_week ?? 1} onChange={e => setForm({ ...form, day_of_week: Number(e.target.value) })} className="w-full px-3 py-2 border rounded-md text-sm">
                    {DAYS.map((d, i) => <option key={d} value={i}>{d}</option>)}
                  </select>
                </div>
              )}
              {form.frequency === 'monthly' && (
                <div>
                  <label className="block text-xs font-medium text-gray-500 mb-1">Day of month</label>
                  <input type="number" min={1} max={31} value={form.day_of_month ?? 1} onChange={e => setForm({ ...form, day_of_month: Number(e.target.value) })} className="w-full px-3 py-2 border rounded-md text-sm" />
                </div>
              )}
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">Hour (0–23)</label>
                <input type="number" min={0} max={23} value={form.hour} onChange={e => setForm({ ...form, hour: Number(e.target.value) })} className="w-full px-3 py-2 border rounded-md text-sm" />
              </div>
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Recipients (comma or newline separated)</label>
              <textarea value={recipientsText} onChange={e => setRecipientsText(e.target.value)} rows={3} placeholder="name@alphadirect.co.bw, other@alphadirect.co.bw" className="w-full px-3 py-2 border rounded-md text-sm" />
            </div>
            <label className="flex items-center gap-2 text-sm text-gray-700">
              <input type="checkbox" checked={form.enabled} onChange={e => setForm({ ...form, enabled: e.target.checked })} className="rounded" /> Enabled
            </label>
            <div className="flex justify-end gap-2 pt-1">
              <button onClick={() => setModalOpen(false)} className="px-4 py-2 text-sm border rounded-md">Cancel</button>
              <button onClick={handleSave} disabled={saving || !form.name.trim()} className="px-4 py-2 text-sm bg-brand-navy text-white rounded-md disabled:opacity-50">{saving ? 'Saving…' : 'Save'}</button>
            </div>
          </div>
        </div>, document.body)}

      {/* Preview modal */}
      {previewHtml !== null && createPortal(
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-8 overflow-y-auto"
          onClick={e => { if (e.target === e.currentTarget) setPreviewHtml(null) }}>
          <div className="bg-white rounded-lg shadow-xl w-full max-w-3xl p-4 space-y-3 max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between">
              <div>
                <h2 className="text-lg font-bold">Report preview</h2>
                <p className="text-xs text-gray-500">{previewSubject} · No email was sent.</p>
              </div>
              <button onClick={() => setPreviewHtml(null)} className="px-3 py-1.5 text-sm border rounded-md">Close</button>
            </div>
            <iframe title="report-preview" srcDoc={previewHtml} className="w-full h-[70vh] border rounded-md bg-white" />
          </div>
        </div>, document.body)}
    </div>
  )
}
