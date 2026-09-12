/**
 * SCAFFOLD — not yet wired into the live app. Move to
 * frontend/src/pages/Underwriting/ and register the route + sidebar child when
 * deploying. Untested in-app (needs a running Graphite frontend).
 *
 * Wiring required on deploy:
 *   App.tsx:        <Route path="underwriting/smart-upload" element={<SmartUnderwritingUploadPage/>} />
 *   Sidebar.tsx:    Underwriting -> children [ {Queue, /underwriting}, {Smart Upload, /underwriting/smart-upload} ]
 *   permission:     underwriting_smart_upload
 *
 * Patterns copied from Imports/PolicyActivationImportPage (dropzone) and
 * BatchProcessing/BatchCreatePage (FormData upload) + api/client.ts.
 */
import { useState, useRef } from 'react'
import { useMutation } from '@tanstack/react-query'
import apiClient from '../../api/client'

interface RiskExtraction {
  id: number
  segment_name: string
  extracted_json: string // parse to the review-screen shape (CreateWizard payloads)
  confidence: number
  provider: string
  human_verified: boolean
}

export default function SmartUnderwritingUploadPage() {
  const [file, setFile] = useState<File | null>(null)
  const [dragOver, setDragOver] = useState(false)
  const [jobId, setJobId] = useState<number | null>(null)
  const [risks, setRisks] = useState<RiskExtraction[]>([])
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null)
  const inputRef = useRef<HTMLInputElement>(null)

  const uploadMutation = useMutation({
    mutationFn: async (f: File) => {
      const fd = new FormData()
      fd.append('file', f)
      return apiClient.post('/underwriting/smart-upload', fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
    },
    onSuccess: (res) => {
      setJobId(res.data.job_id)
      setMsg({ ok: true, text: res.data.message })
      poll(res.data.job_id)
    },
    onError: (err: any) =>
      setMsg({ ok: false, text: err?.response?.data?.message || 'Upload failed' }),
  })

  async function poll(id: number) {
    // simple poll; swap for React Query refetchInterval in real wiring
    const tick = async () => {
      const r = await apiClient.get(`/underwriting/smart-upload/${id}`)
      if (r.data.status === 'completed') {
        setRisks(r.data.risks || [])
        setMsg({ ok: true, text: `Extracted ${r.data.risks?.length ?? 0} risk(s) — review below.` })
      } else if (r.data.status === 'failed') {
        setMsg({ ok: false, text: r.data.message || 'Extraction failed' })
      } else {
        setTimeout(tick, 2500)
      }
    }
    tick()
  }

  function onDrop(e: React.DragEvent) {
    e.preventDefault(); setDragOver(false)
    const f = e.dataTransfer.files?.[0]
    if (f) setFile(f)
  }

  return (
    <div className="p-6 space-y-4">
      <h1 className="text-2xl font-bold text-gray-800">Smart Underwriting Upload</h1>
      <p className="text-sm text-gray-500">
        Drop a broker policy schedule (.xlsx / .xls / .pdf). AI extracts the cover
        sections, sums insured, rates and vehicle schedules; you review and issue.
      </p>

      <div className="bg-white rounded-lg shadow p-6">
        <div
          className={`border-2 border-dashed rounded-lg p-10 text-center transition ${
            dragOver ? 'border-blue-500 bg-blue-50' : 'border-gray-300'
          }`}
          onDragOver={(e) => { e.preventDefault(); setDragOver(true) }}
          onDragLeave={() => setDragOver(false)}
          onDrop={onDrop}
          onClick={() => inputRef.current?.click()}
          role="button"
        >
          <input
            ref={inputRef}
            type="file"
            accept=".xlsx,.xls,.pdf"
            className="hidden"
            onChange={(e) => e.target.files?.[0] && setFile(e.target.files[0])}
          />
          {file ? (
            <p className="text-gray-700">{file.name}</p>
          ) : (
            <p className="text-gray-400">Drag a schedule here, or click to browse</p>
          )}
        </div>

        <button
          onClick={() => file && uploadMutation.mutate(file)}
          disabled={!file || uploadMutation.isPending}
          className="mt-4 px-4 py-2 bg-blue-600 text-white rounded-md disabled:opacity-50"
        >
          {uploadMutation.isPending ? 'Uploading…' : 'Upload & Extract'}
        </button>
      </div>

      {msg && (
        <div className={`p-3 rounded-md text-sm ${msg.ok ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'}`}>
          {msg.text}
        </div>
      )}

      {risks.length > 0 && (
        <div className="bg-white rounded-lg shadow divide-y">
          {risks.map((r) => (
            <div key={r.id} className="p-4 flex items-center justify-between">
              <div>
                <div className="font-medium text-gray-800">{r.segment_name}</div>
                <div className="text-xs text-gray-400">
                  via {r.provider} · confidence {Math.round((r.confidence || 0) * 100)}%
                </div>
              </div>
              {/* Review opens the CreateWizard pre-filled from extracted_json */}
              <button className="px-3 py-1.5 text-sm bg-orange-500 text-white rounded-md">
                Review & Issue
              </button>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
