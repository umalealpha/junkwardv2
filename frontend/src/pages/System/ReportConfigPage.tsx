import { useState } from 'react'
import { useCronConfig, useCronJobDetail, useUpdateCronConfig, useAddStakeholder, useDeleteStakeholder, useTriggerCronJob } from '../../hooks/useCronReports'
import { getReportDownloadUrl } from '../../api/cronReports'
import type { CronJobConfig } from '../../api/cronReports'

// Color mappings
const colorMap: Record<string, string> = {
  blue: 'bg-blue-100 text-blue-800 border-blue-200',
  orange: 'bg-orange-100 text-orange-800 border-orange-200',
  red: 'bg-red-100 text-red-800 border-red-200',
  yellow: 'bg-yellow-100 text-yellow-800 border-yellow-200',
  purple: 'bg-purple-100 text-purple-800 border-purple-200',
  indigo: 'bg-indigo-100 text-indigo-800 border-indigo-200',
  green: 'bg-green-100 text-green-800 border-green-200',
  teal: 'bg-teal-100 text-teal-800 border-teal-200',
  pink: 'bg-pink-100 text-pink-800 border-pink-200',
}

const statusBadge = (status: string) => {
  if (status === 'ok') return 'bg-green-100 text-green-700'
  if (status === 'error') return 'bg-red-100 text-red-700'
  if (status === 'running') return 'bg-blue-100 text-blue-700'
  return 'bg-gray-100 text-gray-500'
}

function formatSchedule(cron: string): string {
  const presets: Record<string, string> = {
    '0 5 * * *': 'Daily at 05:00 UTC',
    '30 5 * * *': 'Daily at 05:30 UTC',
    '0 6 * * *': 'Daily at 06:00 UTC',
    '0 7 * * *': 'Daily at 07:00 UTC',
    '0 6 1 * *': '1st of month at 06:00 UTC',
    '0 7 * * 1': 'Every Monday at 07:00 UTC',
    '0 8 * * 1': 'Every Monday at 08:00 UTC',
    '0 7 * * 2': 'Every Tuesday at 07:00 UTC',
    '0 6 * * 3': 'Every Wednesday at 06:00 UTC',
    '0 7 * * 4': 'Every Thursday at 07:00 UTC',
    '0 7 * * 5': 'Every Friday at 07:00 UTC',
  }
  return presets[cron] ?? cron
}

// ── Job Card ──────────────────────────────────────────────────────────────────
function JobCard({ job, onSelect, selected }: { job: CronJobConfig; onSelect: (key: string) => void; selected: boolean }) {
  return (
    <div
      onClick={() => onSelect(job.job_key)}
      className={`cursor-pointer rounded-lg border p-4 transition-all ${
        selected ? 'border-blue-500 bg-blue-50 shadow-md' : 'border-gray-200 bg-white hover:border-gray-300 hover:shadow-sm'
      } ${!job.enabled ? 'opacity-60' : ''}`}
    >
      <div className="flex items-start justify-between gap-2">
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 flex-wrap">
            <span className={`text-xs font-medium px-2 py-0.5 rounded-full border ${colorMap[job.color] ?? colorMap.blue}`}>
              {job.label}
            </span>
            {!job.enabled && <span className="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">Disabled</span>}
            {job.email_enabled && <span className="text-xs bg-teal-50 text-teal-700 px-2 py-0.5 rounded-full">📧 Email On</span>}
          </div>
          <p className="text-xs text-gray-500 mt-1.5 line-clamp-2">{job.description}</p>
        </div>
        <span className={`text-xs px-2 py-0.5 rounded-full whitespace-nowrap ${statusBadge(job.last_run_status)}`}>
          {job.last_run_status === 'never' ? 'Never run' : job.last_run_status}
        </span>
      </div>
      <div className="mt-3 flex flex-wrap gap-3 text-xs text-gray-400">
        <span>🕐 {formatSchedule(job.schedule)}</span>
        <span>👥 {job.stakeholder_count} recipient{job.stakeholder_count !== 1 ? 's' : ''}</span>
        {job.last_run_at && (
          <span>Last: {new Date(job.last_run_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })}</span>
        )}
        {job.last_run_elapsed && <span>({job.last_run_elapsed})</span>}
      </div>
    </div>
  )
}

// ── Job Detail Panel ──────────────────────────────────────────────────────────
function JobDetailPanel({ jobKey, onClose }: { jobKey: string; onClose: () => void }) {
  const { data: job, isLoading, refetch } = useCronJobDetail(jobKey)
  const updateMutation = useUpdateCronConfig()
  const addMutation = useAddStakeholder(jobKey)
  const deleteMutation = useDeleteStakeholder(jobKey)
  const triggerMutation = useTriggerCronJob()

  const [editMode, setEditMode] = useState(false)
  const [form, setForm] = useState({ schedule: '', notes: '', label: '' })
  const [newEmail, setNewEmail] = useState('')
  const [newName, setNewName] = useState('')
  const [triggerMsg, setTriggerMsg] = useState('')

  const handleEdit = () => {
    if (!job) return
    setForm({ schedule: job.schedule, notes: job.notes ?? '', label: job.label })
    setEditMode(true)
  }

  const handleSave = async () => {
    await updateMutation.mutateAsync({ key: jobKey, data: { schedule: form.schedule, notes: form.notes, label: form.label } })
    setEditMode(false)
    refetch()
  }

  const handleToggleEnabled = async () => {
    if (!job) return
    await updateMutation.mutateAsync({ key: jobKey, data: { enabled: !job.enabled } })
    refetch()
  }

  const handleToggleEmail = async () => {
    if (!job) return
    await updateMutation.mutateAsync({ key: jobKey, data: { email_enabled: !job.email_enabled } })
    refetch()
  }

  const handleAddStakeholder = async () => {
    if (!newEmail.trim()) return
    await addMutation.mutateAsync({ email: newEmail.trim(), name: newName.trim() || undefined })
    setNewEmail(''); setNewName('')
    refetch()
  }

  const handleDeleteStakeholder = async (id: number) => {
    await deleteMutation.mutateAsync(id)
    refetch()
  }

  const handleTrigger = async () => {
    const res = await triggerMutation.mutateAsync(jobKey)
    setTriggerMsg(res.message)
    setTimeout(() => setTriggerMsg(''), 5000)
    refetch()
  }

  if (isLoading) return (
    <div className="h-full flex items-center justify-center text-gray-400">Loading...</div>
  )
  if (!job) return null

  const latestRun = job.run_history?.[0]

  return (
    <div className="h-full flex flex-col overflow-hidden">
      {/* Header */}
      <div className="flex items-center justify-between p-4 border-b bg-white sticky top-0">
        <div>
          <h3 className="font-semibold text-gray-800">{job.label}</h3>
          <p className="text-xs text-gray-500">{job.description}</p>
        </div>
        <button onClick={onClose} className="text-gray-400 hover:text-gray-600 text-xl leading-none">×</button>
      </div>

      <div className="flex-1 overflow-y-auto p-4 space-y-5">

        {/* Quick actions */}
        <div className="flex flex-wrap gap-2">
          <button
            onClick={handleTrigger}
            disabled={triggerMutation.isPending}
            className="px-3 py-1.5 bg-blue-600 text-white text-xs font-medium rounded-md hover:bg-blue-700 disabled:opacity-50"
          >
            {triggerMutation.isPending ? '⏳ Triggering...' : '▶ Run Now'}
          </button>
          <button
            onClick={handleToggleEnabled}
            className={`px-3 py-1.5 text-xs font-medium rounded-md border ${job.enabled ? 'border-red-300 text-red-600 hover:bg-red-50' : 'border-green-300 text-green-700 hover:bg-green-50'}`}
          >
            {job.enabled ? '⏸ Disable' : '▶ Enable'}
          </button>
          <button
            onClick={handleToggleEmail}
            className={`px-3 py-1.5 text-xs font-medium rounded-md border ${job.email_enabled ? 'border-orange-300 text-orange-600 hover:bg-orange-50' : 'border-teal-300 text-teal-600 hover:bg-teal-50'}`}
          >
            {job.email_enabled ? '📧 Email Off' : '📧 Email On'}
          </button>
          {latestRun?.summary && Object.keys(latestRun.summary).length > 0 && (() => {
            // The Python cron engine writes the full absolute path
            // (e.g. /path/to/cron/storage/app/reports/anomaly_20260403_001335.xlsx)
            // into cron_runs.summary.output_file. The download endpoint
            // validates against /^[a-z_]+_\d{8}_\d{6}\.xlsx$/ — the bare
            // filename — so passing the full path was 404'ing.
            const fullPath = (latestRun.summary.output_file as string) ?? ''
            const filename = fullPath.split(/[\\/]/).pop() || ''
            if (!filename) return null
            return (
              <a
                href={getReportDownloadUrl(filename)}
                className="px-3 py-1.5 bg-green-600 text-white text-xs font-medium rounded-md hover:bg-green-700"
                download
              >
                ⬇ Download Report
              </a>
            )
          })()}
        </div>

        {triggerMsg && (
          <div className="text-xs bg-blue-50 border border-blue-200 text-blue-700 rounded px-3 py-2">{triggerMsg}</div>
        )}

        {/* Schedule config */}
        <div className="bg-gray-50 rounded-lg p-4 border">
          <div className="flex items-center justify-between mb-3">
            <h4 className="text-sm font-semibold text-gray-700">Schedule Configuration</h4>
            {!editMode ? (
              <button onClick={handleEdit} className="text-xs text-blue-600 hover:underline">Edit</button>
            ) : (
              <div className="flex gap-2">
                <button onClick={handleSave} disabled={updateMutation.isPending} className="text-xs bg-blue-600 text-white px-2 py-1 rounded hover:bg-blue-700">Save</button>
                <button onClick={() => setEditMode(false)} className="text-xs text-gray-500 hover:text-gray-700">Cancel</button>
              </div>
            )}
          </div>

          {editMode ? (
            <div className="space-y-3">
              <div>
                <label className="text-xs text-gray-500 block mb-1">Label</label>
                <input value={form.label} onChange={e => setForm(p => ({...p, label: e.target.value}))}
                  className="w-full text-sm border border-gray-300 rounded px-2 py-1.5" />
              </div>
              <div>
                <label className="text-xs text-gray-500 block mb-1">Cron Schedule (UTC)</label>
                <input value={form.schedule} onChange={e => setForm(p => ({...p, schedule: e.target.value}))}
                  className="w-full text-sm border border-gray-300 rounded px-2 py-1.5 font-mono"
                  placeholder="0 5 * * *" />
                <p className="text-xs text-gray-400 mt-1">Default: {job.default_schedule} — {formatSchedule(job.default_schedule)}</p>
              </div>
              <div>
                <label className="text-xs text-gray-500 block mb-1">Notes</label>
                <textarea value={form.notes} onChange={e => setForm(p => ({...p, notes: e.target.value}))}
                  rows={2} className="w-full text-sm border border-gray-300 rounded px-2 py-1.5" />
              </div>
            </div>
          ) : (
            <div className="space-y-2 text-sm">
              <div className="flex justify-between">
                <span className="text-gray-500">Current Schedule</span>
                <span className="font-mono text-gray-800 text-xs bg-white border rounded px-2 py-0.5">{job.schedule}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-gray-500">Human Readable</span>
                <span className="text-gray-700">{formatSchedule(job.schedule)}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-gray-500">Status</span>
                <span className={job.enabled ? 'text-green-600 font-medium' : 'text-red-500 font-medium'}>
                  {job.enabled ? '✓ Enabled' : '✗ Disabled'}
                </span>
              </div>
              <div className="flex justify-between">
                <span className="text-gray-500">Email Reports</span>
                <span className={job.email_enabled ? 'text-teal-600 font-medium' : 'text-gray-400'}>
                  {job.email_enabled ? '✓ On' : '✗ Off'}
                </span>
              </div>
              {job.notes && (
                <div className="mt-2 text-xs text-gray-500 italic">{job.notes}</div>
              )}
            </div>
          )}
        </div>

        {/* Latest run summary */}
        {latestRun && (
          <div className="bg-gray-50 rounded-lg p-4 border">
            <h4 className="text-sm font-semibold text-gray-700 mb-3">Latest Run</h4>
            <div className="grid grid-cols-2 gap-2 text-xs">
              <div className="text-gray-500">Status</div>
              <div><span className={`px-2 py-0.5 rounded-full ${statusBadge(latestRun.status)}`}>{latestRun.status}</span></div>
              <div className="text-gray-500">Ran at</div>
              <div>{new Date(latestRun.created_at).toLocaleString()}</div>
              {latestRun.elapsed && <><div className="text-gray-500">Duration</div><div>{latestRun.elapsed}</div></>}
              {Object.entries(latestRun.summary ?? {}).filter(([k]) => !k.includes('output_') && k !== 'report').map(([k, v]) => (
                <><div key={k} className="text-gray-500">{k.replace(/_/g,' ')}</div><div key={`${k}v`} className="font-medium">{String(v)}</div></>
              ))}
            </div>
          </div>
        )}

        {/* Stakeholders */}
        <div className="bg-gray-50 rounded-lg p-4 border">
          <h4 className="text-sm font-semibold text-gray-700 mb-3">Email Recipients ({job.stakeholders?.length ?? 0})</h4>
          <div className="space-y-2 mb-4">
            {(job.stakeholders ?? []).length === 0 && (
              <p className="text-xs text-gray-400 italic">No recipients — uses REPORT_RECIPIENTS env fallback.</p>
            )}
            {(job.stakeholders ?? []).map(s => (
              <div key={s.id} className="flex items-center justify-between bg-white rounded px-3 py-2 border text-sm">
                <div>
                  <span className={s.active ? 'text-gray-800' : 'text-gray-400 line-through'}>{s.email}</span>
                  {s.name && <span className="text-xs text-gray-400 ml-2">({s.name})</span>}
                </div>
                <button onClick={() => handleDeleteStakeholder(s.id)} className="text-red-400 hover:text-red-600 text-xs ml-2">Remove</button>
              </div>
            ))}
          </div>
          {/* Add new */}
          <div className="border-t pt-3 space-y-2">
            <p className="text-xs font-medium text-gray-600">Add recipient</p>
            <input value={newName} onChange={e => setNewName(e.target.value)} placeholder="Name (optional)"
              className="w-full text-sm border rounded px-2 py-1.5" />
            <div className="flex gap-2">
              <input value={newEmail} onChange={e => setNewEmail(e.target.value)} placeholder="Email address" type="email"
                className="flex-1 text-sm border rounded px-2 py-1.5"
                onKeyDown={e => e.key === 'Enter' && handleAddStakeholder()} />
              <button onClick={handleAddStakeholder} disabled={addMutation.isPending || !newEmail.trim()}
                className="px-3 py-1.5 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 disabled:opacity-50">
                Add
              </button>
            </div>
          </div>
        </div>

        {/* Run history */}
        {(job.run_history ?? []).length > 1 && (
          <div className="bg-gray-50 rounded-lg p-4 border">
            <h4 className="text-sm font-semibold text-gray-700 mb-3">Run History (Last 20)</h4>
            <div className="space-y-1">
              {job.run_history.map(r => (
                <div key={r.id} className="flex items-center justify-between text-xs py-1 border-b border-gray-100 last:border-0">
                  <span className={`px-1.5 py-0.5 rounded ${statusBadge(r.status)}`}>{r.status}</span>
                  <span className="text-gray-500">{new Date(r.created_at).toLocaleString()}</span>
                  <span className="text-gray-400">{r.elapsed ?? '-'}</span>
                </div>
              ))}
            </div>
          </div>
        )}
      </div>
    </div>
  )
}

// ── Main Page ─────────────────────────────────────────────────────────────────
export default function ReportConfigPage() {
  const { data: jobs = [], isLoading } = useCronConfig()
  const [selectedKey, setSelectedKey] = useState<string | null>(null)

  if (isLoading) return <div className="p-6 text-gray-500">Loading report configuration...</div>

  const enabled = jobs.filter(j => j.enabled)
  const disabled = jobs.filter(j => !j.enabled)
  const okRuns = jobs.filter(j => j.last_run_status === 'ok').length
  const errorRuns = jobs.filter(j => j.last_run_status === 'error').length

  return (
    <div className="h-screen flex flex-col">
      {/* Page header */}
      <div className="flex-shrink-0 px-6 py-4 border-b bg-white">
        <h1 className="text-xl font-bold text-gray-800">Finance Report Configuration</h1>
        <p className="text-sm text-gray-500 mt-0.5">Manage cron schedules, email recipients, and report settings</p>
        <div className="flex gap-4 mt-3 text-xs text-gray-600">
          <span className="bg-green-50 border border-green-200 px-3 py-1 rounded-full">✓ {enabled.length} enabled</span>
          {disabled.length > 0 && <span className="bg-gray-100 border px-3 py-1 rounded-full">{disabled.length} disabled</span>}
          <span className="bg-green-50 border border-green-200 px-3 py-1 rounded-full">✓ {okRuns} last OK</span>
          {errorRuns > 0 && <span className="bg-red-50 border border-red-200 px-3 py-1 rounded-full text-red-600">⚠ {errorRuns} errors</span>}
        </div>
      </div>

      {/* Body: job list + detail panel */}
      <div className="flex-1 flex overflow-hidden">
        {/* Job list */}
        <div className={`overflow-y-auto p-4 space-y-3 ${selectedKey ? 'w-1/2 border-r' : 'w-full'}`}>
          {jobs.map(job => (
            <JobCard key={job.job_key} job={job} onSelect={setSelectedKey} selected={selectedKey === job.job_key} />
          ))}
          {jobs.length === 0 && (
            <p className="text-center text-gray-400 py-8">No report jobs configured. Check backend connection.</p>
          )}
        </div>

        {/* Detail panel */}
        {selectedKey && (
          <div className="w-1/2 overflow-hidden flex flex-col bg-white">
            <JobDetailPanel jobKey={selectedKey} onClose={() => setSelectedKey(null)} />
          </div>
        )}
      </div>
    </div>
  )
}
