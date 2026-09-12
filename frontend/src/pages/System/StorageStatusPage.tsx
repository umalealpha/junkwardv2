import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import apiClient from '../../api/client'
import LoadingSpinner from '../../components/common/LoadingSpinner'

// ─── Types ────────────────────────────────────────────────────────────────────

interface PathRow {
  path: string
  full: string
  category: string
  critical: boolean
  exists: boolean
  writable: boolean
  files: number
  subdirs: number
  size: number
  last_modified: string | null
  samples: { name: string; size: number; mtime: string }[]
  note: string | null
}

interface StorageStatusResponse {
  host: string
  app_env: string
  base_path: string
  paths: PathRow[]
  checked_at: string
}

interface ResyncResponse {
  success: boolean
  exit_code: number
  output: string
  prefix: string
  disk: string
  ran_at: string
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function humanSize(bytes: number): string {
  if (!bytes) return '0 B'
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / 1024 / 1024).toFixed(2)} MB`
}

function statusIcon(r: PathRow): { icon: string; cls: string; tip: string } {
  if (!r.exists) return { icon: '✕', cls: 'bg-red-100 text-red-700', tip: 'Directory missing' }
  if (r.critical && r.files === 0 && r.subdirs === 0) {
    return { icon: '!', cls: 'bg-amber-100 text-amber-800', tip: 'Empty — critical' }
  }
  if (!r.writable) return { icon: '⊘', cls: 'bg-amber-100 text-amber-800', tip: 'Read-only' }
  return { icon: '✓', cls: 'bg-green-100 text-green-700', tip: 'OK' }
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function StorageStatusPage() {
  const qc = useQueryClient()
  const [openRow, setOpenRow] = useState<string | null>(null)
  const [resyncOutput, setResyncOutput] = useState<ResyncResponse | null>(null)

  const status = useQuery<StorageStatusResponse>({
    queryKey: ['system-storage-status'],
    queryFn: () => apiClient.get('/system/storage-status').then(r => r.data),
    refetchOnWindowFocus: false,
  })

  const resync = useMutation({
    mutationFn: () => apiClient.post('/system/storage/resync-static-pdfs').then(r => r.data as ResyncResponse),
    onSuccess: (data) => {
      setResyncOutput(data)
      qc.invalidateQueries({ queryKey: ['system-storage-status'] })
    },
    onError: (err: any) => alert(err?.response?.data?.error || 'Re-sync failed'),
  })

  const rows = status.data?.paths ?? []
  const categories = Array.from(new Set(rows.map(r => r.category)))
  const criticalProblems = rows.filter(r => r.critical && (!r.exists || (r.files === 0 && r.subdirs === 0))).length

  return (
    <div className="p-6 space-y-4">
      {/* Header */}
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Storage Status</h1>
          <p className="text-sm text-gray-500 mt-0.5">
            Live state of the storage paths used by V2 Quote Sheet, Policy Doc, and the other PDF generators.
            Each backend container builds these directories at boot, then pulls static templates from S3.
          </p>
        </div>
        <div className="flex gap-2">
          <button
            onClick={() => qc.invalidateQueries({ queryKey: ['system-storage-status'] })}
            disabled={status.isFetching}
            className="px-3 py-1.5 text-sm border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-50">
            {status.isFetching ? 'Refreshing…' : 'Refresh'}
          </button>
          <button
            onClick={() => {
              if (window.confirm('Re-pull static PDF templates from S3 to this container? Idempotent — only changed files are written.')) {
                resync.mutate()
              }
            }}
            disabled={resync.isPending}
            className="px-3 py-1.5 text-sm bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50">
            {resync.isPending ? 'Re-syncing…' : 'Re-sync from S3'}
          </button>
        </div>
      </div>

      {/* Top-line status */}
      {status.data && (
        <div className="flex flex-wrap items-center gap-4 text-xs text-gray-600 bg-gray-50 px-3 py-2 rounded border border-gray-200">
          <span><strong>Host:</strong> <code className="font-mono">{status.data.host}</code></span>
          <span><strong>Env:</strong> {status.data.app_env}</span>
          <span><strong>Base:</strong> <code className="font-mono">{status.data.base_path}</code></span>
          <span><strong>Checked:</strong> {new Date(status.data.checked_at).toLocaleTimeString()}</span>
          {criticalProblems > 0 ? (
            <span className="font-semibold text-red-700">
              ⚠ {criticalProblems} critical path(s) need attention
            </span>
          ) : (
            <span className="font-semibold text-green-700">✓ All critical paths populated</span>
          )}
        </div>
      )}

      {/* Re-sync result, if any */}
      {resyncOutput && (
        <div className={`rounded border px-3 py-2 ${resyncOutput.success ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'}`}>
          <div className="text-xs font-medium mb-1">
            Re-sync from S3 (<code className="font-mono">{resyncOutput.disk}://static-pdfs/{resyncOutput.prefix}</code>) —{' '}
            {resyncOutput.success ? 'completed' : `exit ${resyncOutput.exit_code}`}
          </div>
          <pre className="text-[11px] bg-white border border-gray-200 rounded p-2 max-h-48 overflow-auto whitespace-pre-wrap">
{resyncOutput.output || '(no output)'}
          </pre>
          <button
            onClick={() => setResyncOutput(null)}
            className="mt-1 text-xs text-gray-500 hover:text-gray-700 underline"
          >
            Dismiss
          </button>
        </div>
      )}

      {status.isLoading && (
        <div className="flex justify-center py-8">
          <LoadingSpinner />
        </div>
      )}

      {/* Path tables grouped by category */}
      {categories.map(cat => {
        const catRows = rows.filter(r => r.category === cat)
        return (
          <section key={cat} className="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
            <header className="px-4 py-2 bg-gray-50 border-b border-gray-200">
              <h2 className="text-sm font-semibold text-gray-700">{cat}</h2>
            </header>
            <table className="w-full text-sm">
              <thead className="bg-gray-50 text-gray-500 uppercase text-xs">
                <tr>
                  <th className="px-3 py-2 text-left w-10"></th>
                  <th className="px-3 py-2 text-left">Path</th>
                  <th className="px-3 py-2 text-right">Files</th>
                  <th className="px-3 py-2 text-right">Subdirs</th>
                  <th className="px-3 py-2 text-right">Size</th>
                  <th className="px-3 py-2 text-left">Last modified</th>
                  <th className="px-3 py-2 text-left">Note</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {catRows.map(r => {
                  const s = statusIcon(r)
                  const expanded = openRow === r.path
                  return (
                    <>
                      <tr
                        key={r.path}
                        className="hover:bg-gray-50 cursor-pointer"
                        onClick={() => setOpenRow(expanded ? null : r.path)}
                      >
                        <td className="px-3 py-2">
                          <span className={`inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold ${s.cls}`} title={s.tip}>
                            {s.icon}
                          </span>
                        </td>
                        <td className="px-3 py-2 font-mono text-xs text-gray-800">
                          {r.path}
                          {r.critical && <span className="ml-2 text-[10px] text-orange-700 font-semibold uppercase">critical</span>}
                        </td>
                        <td className="px-3 py-2 text-right text-gray-700">{r.files}</td>
                        <td className="px-3 py-2 text-right text-gray-700">{r.subdirs}</td>
                        <td className="px-3 py-2 text-right text-gray-700">{humanSize(r.size)}</td>
                        <td className="px-3 py-2 text-gray-600 text-xs whitespace-nowrap">{r.last_modified ?? '—'}</td>
                        <td className="px-3 py-2 text-xs text-gray-600">
                          {r.note ? (
                            <span className={r.critical ? 'text-red-700' : 'text-amber-700'}>{r.note}</span>
                          ) : (
                            <span className="text-gray-400 italic">—</span>
                          )}
                        </td>
                      </tr>
                      {expanded && r.samples.length > 0 && (
                        <tr key={r.path + '-detail'} className="bg-gray-50">
                          <td colSpan={7} className="px-3 py-3">
                            <div className="text-[11px] text-gray-500 mb-1">
                              First {r.samples.length} files in <code className="font-mono">{r.full}</code>:
                            </div>
                            <ul className="space-y-0.5 text-xs font-mono text-gray-700">
                              {r.samples.map(f => (
                                <li key={f.name}>
                                  {f.name} <span className="text-gray-400">·</span>{' '}
                                  <span className="text-gray-500">{humanSize(f.size)}</span> <span className="text-gray-400">·</span>{' '}
                                  <span className="text-gray-500">{f.mtime}</span>
                                </li>
                              ))}
                            </ul>
                          </td>
                        </tr>
                      )}
                    </>
                  )
                })}
              </tbody>
            </table>
          </section>
        )
      })}
    </div>
  )
}
