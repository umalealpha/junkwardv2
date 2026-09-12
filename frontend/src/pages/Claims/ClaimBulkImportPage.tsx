import { useState, useRef } from 'react'
import { getStoredRoles } from '../../api/auth'
import EmptyState from '../../components/common/EmptyState'
import {
  analyzeImport, previewImport, commitImport,
  type ImportAnalysis, type ImportResult,
} from '../../api/claimBulkImport'

const MANAGE_ROLES = ['Admin', 'admin', 'Super Admin', 'Claims Manager']

type Step = 'upload' | 'map' | 'preview' | 'done'

const STATUS_STYLES: Record<string, string> = {
  valid: 'bg-green-100 text-green-700',
  created: 'bg-green-100 text-green-700',
  duplicate: 'bg-amber-100 text-amber-700',
  invalid: 'bg-red-100 text-red-700',
  failed: 'bg-red-100 text-red-700',
}

export default function ClaimBulkImportPage() {
  const roles = getStoredRoles()
  const canManage = roles.some(r => MANAGE_ROLES.includes(r))

  const [step, setStep] = useState<Step>('upload')
  const [file, setFile] = useState<File | null>(null)
  const [analysis, setAnalysis] = useState<ImportAnalysis | null>(null)
  const [flagOn, setFlagOn] = useState(false)
  const [mapping, setMapping] = useState<Record<string, string>>({})
  const [result, setResult] = useState<ImportResult | null>(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [dragOver, setDragOver] = useState(false)
  const inputRef = useRef<HTMLInputElement>(null)

  if (!canManage) {
    return (
      <div className="p-6">
        <EmptyState title="Access restricted" description="Bulk claim import is available to Admin, Super Admin and Claims Manager only." />
      </div>
    )
  }

  function reset() {
    setStep('upload'); setFile(null); setAnalysis(null); setMapping({}); setResult(null); setError(null)
  }

  async function handleFile(f: File) {
    setFile(f); setError(null); setBusy(true)
    try {
      const res = await analyzeImport(f)
      setAnalysis(res.data)
      setFlagOn(res.flagOn)
      // Auto-map when a header matches a target field name/label loosely.
      const auto: Record<string, string> = {}
      for (const tf of res.data.targetFields) {
        const hit = res.data.headers.find(h => {
          const hn = h.toLowerCase().replace(/[^a-z0-9]/g, '')
          const fn = tf.field.toLowerCase().replace(/[^a-z0-9]/g, '')
          const ln = tf.label.toLowerCase().replace(/[^a-z0-9]/g, '')
          return hn === fn || hn === ln || hn.includes(fn)
        })
        if (hit) auto[tf.field] = hit
      }
      setMapping(auto)
      setStep('map')
    } catch (e: any) {
      setError(e.response?.data?.message || 'Could not read the file.')
    } finally { setBusy(false) }
  }

  function onDrop(e: React.DragEvent) {
    e.preventDefault(); setDragOver(false)
    const f = e.dataTransfer.files?.[0]
    if (f) handleFile(f)
  }

  const requiredFields = analysis?.targetFields.filter(t => t.required) ?? []
  // policy_number OR policy_id satisfies the policy requirement.
  const policySatisfied = !!mapping['policy_number'] || !!mapping['policy_id']
  const otherRequiredSatisfied = requiredFields
    .filter(t => t.field !== 'policy_number' && t.field !== 'policy_id')
    .every(t => !!mapping[t.field])
  const mappingValid = policySatisfied && otherRequiredSatisfied

  async function runPreview() {
    if (!file) return
    setBusy(true); setError(null)
    try {
      const res = await previewImport(file, mapping)
      setResult(res.data)
      setFlagOn(res.flagOn)
      setStep('preview')
    } catch (e: any) {
      setError(e.response?.data?.message || 'Preview failed.')
    } finally { setBusy(false) }
  }

  async function runCommit() {
    if (!file || !result) return
    const willCreate = result.summary.valid
    if (!confirm(`Create ${willCreate} claim(s)? Invalid and duplicate rows are skipped. This cannot be undone.`)) return
    setBusy(true); setError(null)
    try {
      const res = await commitImport(file, mapping)
      setResult(res.data)
      setStep('done')
    } catch (e: any) {
      setError(e.response?.data?.message || 'Import failed.')
    } finally { setBusy(false) }
  }

  return (
    <div className="p-6 space-y-5">
      <div className="flex items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Bulk Claim Import</h1>
          <p className="text-sm text-gray-500">Import claims from .xlsx / .csv. Each row is created through the standard claim-create path (validation intact). Preview is always a dry-run; nothing is created until you confirm.</p>
        </div>
        {step !== 'upload' && <button onClick={reset} className="px-3 py-1.5 text-sm border rounded-md hover:bg-gray-50 whitespace-nowrap">Start over</button>}
      </div>

      {!flagOn && (
        <div className="rounded-md bg-amber-50 border border-amber-200 px-4 py-2.5 text-sm text-amber-800">
          <strong>Import commit is OFF.</strong> The <code>claims_bulk_import</code> flag is disabled — you can upload, map and preview (dry-run), but committing is blocked. Enable it in Admin → Integrations to import.
        </div>
      )}

      {error && <div className="rounded-md bg-red-50 border border-red-200 px-4 py-2.5 text-sm text-red-700">{error}</div>}

      {/* Step indicator */}
      <div className="flex items-center gap-2 text-xs text-gray-500">
        {(['upload', 'map', 'preview', 'done'] as Step[]).map((s, i) => (
          <span key={s} className={`px-2 py-1 rounded-full ${step === s ? 'bg-brand-navy text-white' : 'bg-gray-100'}`}>{i + 1}. {s}</span>
        ))}
      </div>

      {/* Step 1: upload */}
      {step === 'upload' && (
        <div
          onDragOver={e => { e.preventDefault(); setDragOver(true) }}
          onDragLeave={() => setDragOver(false)}
          onDrop={onDrop}
          className={`border-2 border-dashed rounded-lg p-10 text-center ${dragOver ? 'border-brand-orange bg-orange-50' : 'border-gray-300'}`}>
          <p className="text-gray-600">Drag &amp; drop a .xlsx or .csv file here</p>
          <p className="text-xs text-gray-400 mt-1 mb-4">First row must be column headers.</p>
          <input ref={inputRef} type="file" accept=".xlsx,.xls,.csv" className="hidden"
            onChange={e => { const f = e.target.files?.[0]; if (f) handleFile(f) }} />
          <button onClick={() => inputRef.current?.click()} disabled={busy}
            className="px-4 py-2 bg-brand-navy text-white text-sm rounded-md hover:opacity-90 disabled:opacity-50">
            {busy ? 'Reading…' : 'Browse files'}
          </button>
        </div>
      )}

      {/* Step 2: column map */}
      {step === 'map' && analysis && (
        <div className="space-y-4">
          <div className="bg-white shadow rounded-lg p-4">
            <div className="flex items-center justify-between mb-1">
              <h2 className="font-semibold text-gray-800">Map columns</h2>
              <span className="text-xs text-gray-400">{file?.name} · {analysis.rowCount} data row(s)</span>
            </div>
            {analysis.rowCount > analysis.maxRows && (
              <p className="text-xs text-amber-700 mb-2">File has more than {analysis.maxRows} rows — only the first {analysis.maxRows} will be processed.</p>
            )}
            <p className="text-xs text-gray-500 mb-3">Map each claim field to a column from your file. Provide either Policy Number or Policy ID.</p>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
              {analysis.targetFields.map(tf => (
                <div key={tf.field} className="flex items-center gap-3">
                  <label className="text-sm w-48 shrink-0">
                    {tf.label}{tf.required && <span className="text-red-500"> *</span>}
                  </label>
                  <select
                    value={mapping[tf.field] ?? ''}
                    onChange={e => setMapping(m => ({ ...m, [tf.field]: e.target.value }))}
                    className="flex-1 px-3 py-2 border rounded-md text-sm">
                    <option value="">— not mapped —</option>
                    {analysis.headers.map(h => <option key={h} value={h}>{h}</option>)}
                  </select>
                </div>
              ))}
            </div>
          </div>

          {analysis.sample.length > 0 && (
            <div className="bg-white shadow rounded-lg overflow-x-auto">
              <div className="px-4 py-2 bg-gray-50 text-xs font-semibold uppercase text-gray-500">File preview (first {analysis.sample.length} rows)</div>
              <table className="min-w-full text-xs">
                <thead className="bg-gray-50"><tr>{analysis.headers.map(h => <th key={h} className="px-3 py-2 text-left font-medium text-gray-500">{h}</th>)}</tr></thead>
                <tbody className="divide-y divide-gray-100">
                  {analysis.sample.map((r, i) => <tr key={i}>{analysis.headers.map((_, j) => <td key={j} className="px-3 py-1.5 text-gray-600">{r[j] ?? ''}</td>)}</tr>)}
                </tbody>
              </table>
            </div>
          )}

          <div className="flex justify-end gap-2">
            <button onClick={runPreview} disabled={!mappingValid || busy}
              className="px-4 py-2 bg-brand-navy text-white text-sm rounded-md hover:opacity-90 disabled:opacity-50">
              {busy ? 'Validating…' : 'Preview (dry-run)'}
            </button>
          </div>
          {!mappingValid && <p className="text-xs text-amber-700 text-right">Map all required fields (and a policy identifier) to continue.</p>}
        </div>
      )}

      {/* Step 3 & 4: preview / done */}
      {(step === 'preview' || step === 'done') && result && (
        <div className="space-y-4">
          <SummaryCards result={result} committed={step === 'done'} />

          <div className="bg-white shadow rounded-lg overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead className="bg-gray-50"><tr>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Row</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Policy</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date of loss</th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Claim / Notes</th>
              </tr></thead>
              <tbody className="divide-y divide-gray-200">
                {result.rows.length === 0 && <tr><td colSpan={6} className="px-4 py-8 text-center text-gray-400">No rows.</td></tr>}
                {result.rows.map(r => (
                  <tr key={r.row} className="hover:bg-gray-50 align-top">
                    <td className="px-4 py-3 text-gray-500">{r.row}</td>
                    <td className="px-4 py-3"><span className={`px-2 py-0.5 text-xs rounded-full ${STATUS_STYLES[r.status] || 'bg-gray-100 text-gray-600'}`}>{r.status}</span></td>
                    <td className="px-4 py-3">{r.preview.policyNumber ?? '—'}</td>
                    <td className="px-4 py-3">{r.preview.claimType ?? '—'}</td>
                    <td className="px-4 py-3">{r.preview.dateOfLoss ?? '—'}</td>
                    <td className="px-4 py-3 text-xs text-gray-600">
                      {r.claim_number && <div className="font-medium text-green-700">{r.claim_number}</div>}
                      {r.errors.length > 0 && <div className="text-red-600">{r.errors.join('; ')}</div>}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {step === 'preview' && (
            <div className="flex items-center justify-end gap-2">
              <button onClick={() => setStep('map')} className="px-4 py-2 text-sm border rounded-md">Back to mapping</button>
              <button onClick={runCommit} disabled={busy || !flagOn || result.summary.valid === 0}
                className="px-4 py-2 bg-brand-orange text-white text-sm rounded-md hover:opacity-90 disabled:opacity-50">
                {busy ? 'Importing…' : `Confirm import (${result.summary.valid})`}
              </button>
            </div>
          )}
          {step === 'preview' && !flagOn && <p className="text-xs text-amber-700 text-right">Enable the bulk-import flag to confirm.</p>}
          {step === 'done' && (
            <div className="rounded-md bg-green-50 border border-green-200 px-4 py-2.5 text-sm text-green-800">
              Import complete — {result.summary.created} created, {result.summary.duplicate} skipped (duplicate), {result.summary.failed} failed.
            </div>
          )}
        </div>
      )}
    </div>
  )
}

function SummaryCards({ result, committed }: { result: ImportResult; committed: boolean }) {
  const s = result.summary
  const cards: [string, number, string][] = [
    ['Total rows', s.total, 'text-gray-800'],
    [committed ? 'Created' : 'Will create', committed ? s.created : s.valid, 'text-green-700'],
    ['Duplicates (skipped)', s.duplicate, 'text-amber-700'],
    ['Invalid', s.invalid, 'text-red-700'],
    ['Failed', s.failed, 'text-red-700'],
  ]
  return (
    <div className="grid grid-cols-2 sm:grid-cols-5 gap-3">
      {cards.map(([label, value, color]) => (
        <div key={label} className="bg-white shadow rounded-lg p-4">
          <div className="text-xs uppercase tracking-wide text-gray-400">{label}</div>
          <div className={`text-2xl font-bold mt-1 ${color}`}>{value}</div>
        </div>
      ))}
    </div>
  )
}
