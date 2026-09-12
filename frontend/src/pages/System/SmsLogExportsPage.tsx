import { useState } from 'react'
import { useQuery, useMutation } from '@tanstack/react-query'
import {
  listSmsExports,
  getSmsExportDownloadUrl,
  generateSmsExport,
  type SmsExportListResponse,
  type SmsExportGenerateResponse,
} from '../../api/smsLogExports'
import LoadingSpinner from '../../components/common/LoadingSpinner'

// GRA-0155 — self-service download of the daily Infobip SMS-log exports.
// Lists the CSVs the nightly cron writes to S3, offers a presigned download
// per file, and lets an authorized user request an on-demand date-range CSV.
// Gated backend-side by the `sms-logs-download` permission (route middleware);
// the sidebar entry is permission-gated to match. A user lacking the permission
// gets a 403 from the list endpoint, which this page renders as a notice.

function humanSize(bytes: number | null): string {
  if (!bytes) return '—'
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / 1024 / 1024).toFixed(2)} MB`
}

function openInNewTab(url: string) {
  // The presigned URL points straight at S3 with content-disposition; opening
  // it triggers the browser download without leaking the token in our history.
  window.open(url, '_blank', 'noopener,noreferrer')
}

export default function SmsLogExportsPage() {
  const today = new Date().toISOString().slice(0, 10)
  const [startDate, setStartDate] = useState<string>(today)
  const [endDate, setEndDate] = useState<string>(today)
  const [notice, setNotice] = useState<string | null>(null)

  const list = useQuery<SmsExportListResponse>({
    queryKey: ['sms-log-exports'],
    queryFn: listSmsExports,
    refetchOnWindowFocus: false,
  })

  const forbidden = (list.error as any)?.response?.status === 403

  const download = useMutation({
    mutationFn: (path: string) => getSmsExportDownloadUrl(path),
    onSuccess: (data) => {
      if (data?.url) openInNewTab(data.url)
    },
    onError: (err: any) => alert(err?.response?.data?.message || err?.response?.data?.error || 'Could not get download link'),
  })

  const generate = useMutation<SmsExportGenerateResponse, any, void>({
    mutationFn: () => generateSmsExport(startDate, endDate),
    onSuccess: (data) => {
      if (data?.url) {
        setNotice(`Export ready: ${data.filename} (${data.rows ?? 0} rows).`)
        openInNewTab(data.url)
        list.refetch()
      } else {
        setNotice(data?.message || 'No SMS log records found for the selected date range.')
      }
    },
    onError: (err: any) => {
      setNotice(null)
      alert(err?.response?.data?.error || err?.response?.data?.message || 'Export failed')
    },
  })

  const maxDays = list.data?.max_range_days ?? 92
  const files = list.data?.items ?? []

  return (
    <div className="p-6 space-y-4">
      {/* Header */}
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">SMS Log Exports</h1>
          <p className="text-sm text-gray-500 mt-0.5">
            Daily CSV exports of the Infobip SMS delivery logs, archived to company storage so the
            data is retained without paying Infobip archived-log retrieval fees. Download an existing
            daily file, or generate an export for any date range.
          </p>
        </div>
        <button
          onClick={() => list.refetch()}
          disabled={list.isFetching}
          className="px-3 py-1.5 text-sm border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-50">
          {list.isFetching ? 'Refreshing…' : 'Refresh'}
        </button>
      </div>

      {forbidden && (
        <div className="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
          You are not authorised to access SMS log exports.
        </div>
      )}

      {/* On-demand date-range export */}
      {!forbidden && (
        <section className="bg-white rounded-lg border border-gray-200 shadow-sm p-4 space-y-3">
          <h2 className="text-sm font-semibold text-gray-700">Export a date range</h2>
          <div className="flex flex-wrap items-end gap-3">
            <label className="text-xs text-gray-600">
              <span className="block mb-1">Start date</span>
              <input
                type="date"
                value={startDate}
                max={today}
                onChange={(e) => setStartDate(e.target.value)}
                className="border border-gray-300 rounded px-2 py-1.5 text-sm"
              />
            </label>
            <label className="text-xs text-gray-600">
              <span className="block mb-1">End date</span>
              <input
                type="date"
                value={endDate}
                max={today}
                onChange={(e) => setEndDate(e.target.value)}
                className="border border-gray-300 rounded px-2 py-1.5 text-sm"
              />
            </label>
            <button
              onClick={() => { setNotice(null); generate.mutate() }}
              disabled={generate.isPending || !startDate || !endDate}
              className="px-3 py-1.5 text-sm bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50">
              {generate.isPending ? 'Generating…' : 'Generate & download'}
            </button>
            <span className="text-xs text-gray-400">Max {maxDays} days per export.</span>
          </div>
          {notice && (
            <div className="text-xs text-gray-700 bg-gray-50 border border-gray-200 rounded px-3 py-2">
              {notice}
            </div>
          )}
        </section>
      )}

      {/* Existing daily files */}
      {!forbidden && (
        <section className="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
          <header className="px-4 py-2 bg-gray-50 border-b border-gray-200">
            <h2 className="text-sm font-semibold text-gray-700">Daily exports</h2>
          </header>

          {list.isLoading ? (
            <div className="flex justify-center py-8"><LoadingSpinner /></div>
          ) : files.length === 0 ? (
            <div className="px-4 py-6 text-sm text-gray-500 italic">
              No SMS log exports yet. The nightly job creates one file per day.
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-gray-50 text-gray-500 uppercase text-xs">
                  <tr>
                    <th className="px-3 py-2 text-left">File</th>
                    <th className="px-3 py-2 text-right">Size</th>
                    <th className="px-3 py-2 text-left">Last modified</th>
                    <th className="px-3 py-2 text-right">Action</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                  {files.map((f) => (
                    <tr key={f.path} className="hover:bg-gray-50">
                      <td className="px-3 py-2 font-mono text-xs text-gray-800 break-all">{f.filename}</td>
                      <td className="px-3 py-2 text-right text-gray-700 whitespace-nowrap">{humanSize(f.size_bytes)}</td>
                      <td className="px-3 py-2 text-gray-600 text-xs whitespace-nowrap">
                        {f.last_modified ? new Date(f.last_modified).toLocaleString() : '—'}
                      </td>
                      <td className="px-3 py-2 text-right">
                        <button
                          onClick={() => download.mutate(f.path)}
                          disabled={download.isPending}
                          className="px-3 py-1 text-xs bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50">
                          Download
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>
      )}
    </div>
  )
}
