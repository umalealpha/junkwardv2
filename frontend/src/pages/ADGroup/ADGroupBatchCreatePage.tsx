import { useState, useRef, useCallback } from 'react'
import apiClient from '../../api/client'

export default function ADGroupBatchCreatePage() {
  const [file, setFile] = useState<File | null>(null)
  const [dragActive, setDragActive] = useState(false)
  const [uploading, setUploading] = useState(false)
  const [result, setResult] = useState<{ success: boolean; message: string } | null>(null)
  const inputRef = useRef<HTMLInputElement>(null)

  const handleDrag = useCallback((e: React.DragEvent) => {
    e.preventDefault()
    e.stopPropagation()
    if (e.type === 'dragenter' || e.type === 'dragover') setDragActive(true)
    else if (e.type === 'dragleave') setDragActive(false)
  }, [])

  const handleDrop = useCallback((e: React.DragEvent) => {
    e.preventDefault()
    e.stopPropagation()
    setDragActive(false)
    const dropped = e.dataTransfer.files?.[0]
    if (dropped && (dropped.name.endsWith('.xlsx') || dropped.name.endsWith('.xls') || dropped.name.endsWith('.csv'))) {
      setFile(dropped)
      setResult(null)
    }
  }, [])

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const selected = e.target.files?.[0]
    if (selected) { setFile(selected); setResult(null) }
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!file) return
    setUploading(true)
    setResult(null)
    try {
      const formData = new FormData()
      formData.append('file', file)
      await apiClient.post('/ad-group-batch-create', formData, { headers: { 'Content-Type': 'multipart/form-data' } })
      setResult({ success: true, message: 'AD Group batch file uploaded successfully. Processing will begin shortly.' })
      setFile(null)
      if (inputRef.current) inputRef.current.value = ''
    } catch (err: any) {
      setResult({ success: false, message: err?.response?.data?.message || 'Upload failed. Please try again.' })
    } finally {
      setUploading(false)
    }
  }

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-800">AD Group Batch Create</h1>
      </div>

      <div className="bg-white rounded-lg shadow-sm border p-6 max-w-2xl">
        <div className="mb-6">
          <h2 className="text-lg font-semibold text-gray-700 mb-2">Instructions</h2>
          <ul className="text-sm text-gray-600 space-y-1 list-disc list-inside">
            <li>Download the AD Group batch template using the button below.</li>
            <li>Fill in employee details, group information, and policy data.</li>
            <li>Upload the completed file (.xlsx, .xls, or .csv).</li>
            <li>The system will validate and create group policies in the background.</li>
            <li>Check the Batch Report page for processing results.</li>
          </ul>
        </div>

        <div className="mb-6">
          <a
            href="#"
            onClick={e => { e.preventDefault(); alert('Template download will be available once the API endpoint is configured.') }}
            className="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-md border border-gray-300 hover:bg-gray-200 text-sm font-medium"
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
            Download Template
          </a>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div
            onDragEnter={handleDrag}
            onDragLeave={handleDrag}
            onDragOver={handleDrag}
            onDrop={handleDrop}
            onClick={() => inputRef.current?.click()}
            className={`border-2 border-dashed rounded-lg p-8 text-center cursor-pointer transition ${
              dragActive ? 'border-blue-500 bg-blue-50' : file ? 'border-green-400 bg-green-50' : 'border-gray-300 hover:border-gray-400'
            }`}
          >
            <input
              ref={inputRef}
              type="file"
              accept=".xlsx,.xls,.csv"
              onChange={handleFileChange}
              className="hidden"
            />
            {file ? (
              <div>
                <svg className="w-10 h-10 mx-auto text-green-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <p className="text-sm font-medium text-green-700">{file.name}</p>
                <p className="text-xs text-gray-500 mt-1">{(file.size / 1024).toFixed(1)} KB</p>
              </div>
            ) : (
              <div>
                <svg className="w-10 h-10 mx-auto text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" /></svg>
                <p className="text-sm text-gray-600 font-medium">Drag and drop your Excel file here</p>
                <p className="text-xs text-gray-400 mt-1">or click to browse (.xlsx, .xls, .csv)</p>
              </div>
            )}
          </div>

          {result && (
            <div className={`p-3 rounded-md text-sm ${result.success ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'}`}>
              {result.message}
            </div>
          )}

          <button
            type="submit"
            disabled={!file || uploading}
            className="w-full px-4 py-2.5 bg-blue-600 text-white rounded-md font-medium hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-sm"
          >
            {uploading ? 'Uploading...' : 'Upload & Process'}
          </button>
        </form>
      </div>
    </div>
  )
}
