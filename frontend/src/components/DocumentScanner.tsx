import { useState, useRef, useCallback } from 'react'
import { createWorker } from 'tesseract.js'
import {
  parseDocumentText, uploadDocumentFile,
  type DocumentType, type OcrResult, type FraudResult, type DbFlag, type OcrParseResponse,
} from '../api/ocr'

interface DocumentScannerProps {
  onFieldsExtracted: (fields: OcrResult, docType: DocumentType) => void
  onClose: () => void
  productContext?: string   // e.g. "Motor Comprehensive", "Domestic", "Commercial"
  entityContext?: string    // "Individual" or "Organisation"
}

type Stage = 'upload' | 'processing' | 'review' | 'error'

const ACCEPT = 'image/*,.pdf,.doc,.docx,.xlsx,.xls,.csv'
const IMAGE_EXTS = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff']

// @ts-ignore
const RISK_COLORS = {
  low:    { bg: 'bg-green-50', border: 'border-green-200', text: 'text-green-700', badge: 'bg-green-100 text-green-800' },
  medium: { bg: 'bg-yellow-50', border: 'border-yellow-200', text: 'text-yellow-700', badge: 'bg-yellow-100 text-yellow-800' },
  high:   { bg: 'bg-red-50', border: 'border-red-200', text: 'text-red-700', badge: 'bg-red-100 text-red-800' },
}

function getFileIcon(name: string) {
  const ext = name.split('.').pop()?.toLowerCase() || ''
  if (ext === 'pdf') return { color: 'text-red-500', label: 'PDF' }
  if (['doc', 'docx'].includes(ext)) return { color: 'text-blue-600', label: 'DOC' }
  if (['xlsx', 'xls', 'csv'].includes(ext)) return { color: 'text-green-600', label: 'XLS' }
  if (IMAGE_EXTS.includes(ext)) return { color: 'text-purple-500', label: 'IMG' }
  return { color: 'text-gray-500', label: 'FILE' }
}

export default function DocumentScanner({ onFieldsExtracted, onClose, productContext, entityContext }: DocumentScannerProps) {
  const [stage, setStage] = useState<Stage>('upload')
  const [files, setFiles] = useState<File[]>([])
  const [progress, setProgress] = useState('')
  const [extractedFields, setExtractedFields] = useState<OcrResult | null>(null)
  const [_fraudResult, setFraudResult] = useState<FraudResult | null>(null)
  const [dbFlags, setDbFlags] = useState<DbFlag[]>([])
  const [detectedType, setDetectedType] = useState<string>('')
  const [errorMsg, setErrorMsg] = useState('')
  const [processedCount, setProcessedCount] = useState(0)
  const [skippedCount, setSkippedCount] = useState(0)
  const [fileFraudResults, setFileFraudResults] = useState<Array<{ fileName: string; fraud: FraudResult; previewUrl: string }>>([])
  const fileRef = useRef<HTMLInputElement>(null)

  const handleFilesSelected = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
    const selected = Array.from(e.target.files || [])
    if (selected.length > 0) setFiles(prev => [...prev, ...selected])
  }, [])

  const removeFile = (idx: number) => setFiles(prev => prev.filter((_, i) => i !== idx))

  const processFiles = useCallback(async () => {
    if (files.length === 0) return
    setStage('processing')
    setErrorMsg('')

    try {
      // Process each file and merge results — skip failures, collect what we can
      let allFields: OcrResult = {}
      let lastFraud: FraudResult | null = null
      let allDbFlags: DbFlag[] = []
      let lastType = ''
      let processed = 0
      let skipped = 0
      const perFileFraud: Array<{ fileName: string; fraud: FraudResult; previewUrl: string }> = []

      for (let i = 0; i < files.length; i++) {
        const file = files[i]
        const ext = file.name.split('.').pop()?.toLowerCase() || ''
        const isImage = IMAGE_EXTS.includes(ext)

        setProgress(`Processing ${i + 1}/${files.length}: ${file.name}${skipped ? ` (${skipped} skipped)` : ''}...`)

        try {
          let response: OcrParseResponse & { detected_type?: string }

          if (isImage) {
            setProgress(`Reading text from ${file.name}...`)
            const worker = await createWorker('eng', 1, {
              logger: (m: any) => {
                if (m.status === 'recognizing text') {
                  setProgress(`OCR ${file.name}: ${Math.round(m.progress * 100)}%`)
                }
              },
            })
            const { data } = await worker.recognize(URL.createObjectURL(file))
            await worker.terminate()

            const text = data.text.trim()
            if (text.length < 10) { skipped++; continue }

            setProgress(`AI analyzing ${file.name}...`)
            response = await parseDocumentText(text, 'any')
          } else {
            setProgress(`Uploading & analyzing ${file.name}...`)
            response = await uploadDocumentFile(file, 'any', { product: productContext, entityType: entityContext })
          }

          // Backend may return skipped=true for unreadable files
          if ((response as any).skipped) { skipped++; continue }

          // Normalize nested {value, confidence} format
          for (const [key, val] of Object.entries(response.data || {})) {
            if (val && typeof val === 'object' && 'value' in (val as any)) {
              allFields[key] = (val as any).value
              allFields[`${key}_confidence`] = (val as any).confidence ?? null
            } else if (!allFields[key]) {
              allFields[key] = val as any
            }
          }

          if (response.fraud) {
            lastFraud = response.fraud
            perFileFraud.push({ fileName: file.name, fraud: response.fraud, previewUrl: URL.createObjectURL(file) })
          }
          if (response.db_flags) allDbFlags = [...allDbFlags, ...response.db_flags]
          if (response.detected_type) lastType = response.detected_type
          processed++
        } catch (fileErr: any) {
          // Skip this file, continue with the rest
          console.warn(`Skipped ${file.name}:`, fileErr?.message || fileErr)
          skipped++
          continue
        }
      }

      if (Object.keys(allFields).filter(k => !k.endsWith('_confidence')).length === 0) {
        setErrorMsg(`Could not extract fields from any of the ${files.length} file(s). ${skipped} skipped due to unreadable content. Try clearer scans or different formats.`)
        setStage('error')
        return
      }

      setExtractedFields(allFields)
      setFraudResult(lastFraud)
      setDbFlags(allDbFlags)
      setDetectedType(lastType)
      setProcessedCount(processed)
      setSkippedCount(skipped)
      setFileFraudResults(perFileFraud)
      setStage('review')
    } catch (err: any) {
      setErrorMsg(err?.response?.data?.error || err?.message || 'Failed to process files')
      setStage('error')
    }
  }, [files])

  const handleConfirm = () => {
    if (extractedFields) onFieldsExtracted(extractedFields, (detectedType || 'any') as DocumentType)
  }

  const reset = () => {
    setStage('upload')
    setFiles([])
    setProgress('')
    setExtractedFields(null)
    setFraudResult(null)
    setDbFlags([])
    setErrorMsg('')
    setProcessedCount(0)
    setSkippedCount(0)
    setFileFraudResults([])
    if (fileRef.current) fileRef.current.value = ''
  }

  const isHighRisk = fileFraudResults.some(fr => fr.fraud.risk_level === 'high')

  return (
    <div
      className="fixed inset-0 z-50 flex items-start justify-center bg-black/50 p-4 pt-10 overflow-y-auto"
      onClick={(e) => { if (e.target === e.currentTarget) onClose() }}
    >
      <div className="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4 max-h-[85vh] overflow-hidden flex flex-col">

        {/* Header */}
        <div className="bg-gradient-to-r from-indigo-600 to-blue-600 px-5 py-3 flex items-center justify-between flex-shrink-0">
          <div>
            <h2 className="text-lg font-bold text-white">Upload Documents</h2>
            <p className="text-indigo-200 text-xs">PDF, Word, Excel, or images — AI extracts the fields</p>
          </div>
          <button onClick={onClose} className="text-white/70 hover:text-white p-1">
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div className="flex-1 overflow-y-auto p-5">

          {/* ── Upload ── */}
          {stage === 'upload' && (
            <div className="space-y-4">
              <div
                className="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-indigo-400 transition-colors cursor-pointer"
                onClick={() => fileRef.current?.click()}
                onDragOver={e => { e.preventDefault(); e.currentTarget.classList.add('border-indigo-500', 'bg-indigo-50') }}
                onDragLeave={e => { e.currentTarget.classList.remove('border-indigo-500', 'bg-indigo-50') }}
                onDrop={e => {
                  e.preventDefault()
                  e.currentTarget.classList.remove('border-indigo-500', 'bg-indigo-50')
                  const dropped = Array.from(e.dataTransfer.files)
                  if (dropped.length > 0) setFiles(prev => [...prev, ...dropped])
                }}
              >
                <svg className="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
                <p className="text-sm font-medium text-gray-600">Drop files here or click to browse</p>
                <p className="text-xs text-gray-400 mt-1">PDF, Word (.docx), Excel (.xlsx), CSV, or images — up to 20MB each</p>
              </div>
              <input ref={fileRef} type="file" accept={ACCEPT} multiple onChange={handleFilesSelected} className="hidden" />

              {/* File list */}
              {files.length > 0 && (
                <div className="space-y-2">
                  <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide">{files.length} file{files.length > 1 ? 's' : ''} selected</p>
                  {files.map((f, i) => {
                    const icon = getFileIcon(f.name)
                    return (
                      <div key={i} className="flex items-center gap-3 bg-gray-50 rounded-lg px-3 py-2 border border-gray-200">
                        <span className={`text-xs font-bold ${icon.color} bg-white px-2 py-1 rounded border`}>{icon.label}</span>
                        <span className="text-sm text-gray-700 truncate flex-1">{f.name}</span>
                        <span className="text-xs text-gray-400">{(f.size / 1024).toFixed(0)} KB</span>
                        <button onClick={() => removeFile(i)} className="text-gray-400 hover:text-red-500 p-1">
                          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                          </svg>
                        </button>
                      </div>
                    )
                  })}
                </div>
              )}

              <button onClick={processFiles} disabled={files.length === 0}
                className="w-full py-3 bg-gradient-to-r from-indigo-600 to-blue-600 text-white font-semibold rounded-xl hover:from-indigo-700 hover:to-blue-700 disabled:from-gray-300 disabled:to-gray-300 transition-all shadow-md disabled:shadow-none">
                Extract Fields from {files.length || 0} File{files.length !== 1 ? 's' : ''}
              </button>
            </div>
          )}

          {/* ── Processing ── */}
          {stage === 'processing' && (
            <div className="text-center py-12">
              <svg className="w-12 h-12 text-indigo-500 animate-spin mx-auto mb-4" fill="none" viewBox="0 0 24 24">
                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              <p className="text-base font-semibold text-gray-800">{progress || 'Processing...'}</p>
              <p className="text-xs text-gray-400 mt-2">Extracting text + AI field mapping + fraud check</p>
            </div>
          )}

          {/* ── Review ── */}
          {stage === 'review' && extractedFields && (
            <div className="space-y-4">
              {/* Per-file fraud results */}
              {fileFraudResults.length > 0 && (
                <div className="space-y-2">
                  <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide">Document Verification</p>
                  {fileFraudResults.map((fr, fi) => {
                    const level = fr.fraud.risk_level
                    const isClean = level === 'low' && fr.fraud.flags.length === 0
                    return (
                      <div key={fi} className={`rounded-xl border overflow-hidden ${
                        level === 'high' ? 'border-red-300 bg-red-50' :
                        level === 'medium' ? 'border-yellow-300 bg-yellow-50' :
                        'border-green-200 bg-green-50'
                      }`}>
                        {/* File header row */}
                        <div className="flex items-center gap-3 px-4 py-2.5">
                          {/* Status icon */}
                          {isClean ? (
                            <svg className="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                          ) : (
                            <svg className={`w-5 h-5 flex-shrink-0 ${level === 'high' ? 'text-red-500' : 'text-yellow-500'}`} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" /></svg>
                          )}
                          {/* File name — red if flagged */}
                          <a
                            href={fr.previewUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            className={`text-sm font-semibold flex-1 truncate hover:underline ${
                              level === 'high' ? 'text-red-700' :
                              level === 'medium' ? 'text-yellow-800' :
                              'text-green-700'
                            }`}
                            title={`Click to open ${fr.fileName}`}
                          >
                            {fr.fileName}
                            <svg className="w-3 h-3 inline ml-1 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                          </a>
                          {/* Score badge */}
                          <span className={`text-xs font-bold px-2 py-0.5 rounded-full flex-shrink-0 ${
                            level === 'high' ? 'bg-red-200 text-red-800' :
                            level === 'medium' ? 'bg-yellow-200 text-yellow-800' :
                            'bg-green-200 text-green-800'
                          }`}>
                            {isClean ? 'CLEAN' : `${level.toUpperCase()} ${fr.fraud.risk_score}/100`}
                          </span>
                        </div>
                        {/* Flags (only if not clean) */}
                        {!isClean && (
                          <div className="px-4 pb-3 pt-0 space-y-1 border-t border-dashed border-gray-200 mt-0">
                            <p className={`text-xs mt-2 ${level === 'high' ? 'text-red-600' : 'text-yellow-700'}`}>{fr.fraud.summary}</p>
                            {fr.fraud.flags.map((f, i) => (
                              <div key={i} className="flex items-start gap-2 text-xs pl-1">
                                <span className={`mt-1 w-1.5 h-1.5 rounded-full flex-shrink-0 ${f.severity === 'high' ? 'bg-red-500' : f.severity === 'medium' ? 'bg-yellow-500' : 'bg-blue-400'}`} />
                                <span className="text-gray-700">{f.description}</span>
                              </div>
                            ))}
                          </div>
                        )}
                      </div>
                    )
                  })}
                </div>
              )}

              {/* DB flags */}
              {dbFlags.map((f, i) => (
                <div key={i} className={`rounded-lg border p-3 text-xs ${f.severity === 'high' ? 'bg-red-50 border-red-200 text-red-700' : f.severity === 'medium' ? 'bg-yellow-50 border-yellow-200 text-yellow-700' : 'bg-blue-50 border-blue-200 text-blue-700'}`}>
                  {f.description}
                </div>
              ))}

              {/* Summary */}
              <div className="flex items-center gap-3 flex-wrap text-xs">
                {detectedType && detectedType !== 'any' && (
                  <span className="text-gray-500">Type: <strong className="text-gray-700">{detectedType}</strong></span>
                )}
                <span className="text-green-600 bg-green-50 px-2 py-0.5 rounded-full border border-green-200 font-medium">{processedCount} processed</span>
                {skippedCount > 0 && (
                  <span className="text-orange-600 bg-orange-50 px-2 py-0.5 rounded-full border border-orange-200 font-medium">{skippedCount} skipped (unreadable)</span>
                )}
              </div>

              {/* Fields */}
              <div className="bg-gray-50 rounded-xl border border-gray-200 divide-y divide-gray-200 overflow-hidden">
                {Object.entries(extractedFields)
                  .filter(([k]) => !k.endsWith('_confidence'))
                  .map(([key, value]) => {
                    const conf = extractedFields[`${key}_confidence`]
                    const pct = typeof conf === 'number' ? Math.round(conf * 100) : null
                    return (
                      <div key={key} className="flex items-center px-4 py-2.5">
                        <span className="text-xs font-medium text-gray-500 w-40 flex-shrink-0">{key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</span>
                        <span className="flex-1 text-sm font-medium text-gray-900">{value !== null && value !== undefined ? String(value) : <span className="text-gray-300 italic">—</span>}</span>
                        {pct !== null && <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${pct >= 80 ? 'bg-green-100 text-green-700' : pct >= 50 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'}`}>{pct}%</span>}
                      </div>
                    )
                  })}
              </div>

              <div className="flex gap-3 pt-2">
                <button onClick={reset} className="flex-1 py-2.5 border border-gray-300 text-gray-600 rounded-xl hover:bg-gray-50 text-sm font-medium">Upload More</button>
                <button onClick={handleConfirm} disabled={isHighRisk}
                  className={`flex-1 py-2.5 rounded-xl text-sm font-semibold shadow-md ${isHighRisk ? 'bg-red-100 text-red-600 border border-red-300 cursor-not-allowed' : 'bg-gradient-to-r from-green-600 to-emerald-600 text-white hover:from-green-700 hover:to-emerald-700'}`}>
                  {isHighRisk ? 'Blocked — High Risk' : 'Apply to Form'}
                </button>
              </div>
              {isHighRisk && <p className="text-xs text-red-500 text-center">This document was flagged as high fraud risk. Verify manually.</p>}
            </div>
          )}

          {/* ── Error ── */}
          {stage === 'error' && (
            <div className="text-center py-8">
              <div className="w-14 h-14 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
                <svg className="w-7 h-7 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <p className="text-sm font-medium text-red-700">{errorMsg}</p>
              <button onClick={reset} className="mt-4 px-5 py-2 bg-gray-800 text-white rounded-xl text-sm font-medium hover:bg-gray-900">Try Again</button>
            </div>
          )}
        </div>
      </div>
    </div>
  )
}
