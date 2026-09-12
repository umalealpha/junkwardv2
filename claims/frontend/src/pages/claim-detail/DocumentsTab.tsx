import { useState, useEffect } from 'react'
import apiClient from '../../api/client'
import type { Document } from './types'

const fileTypeConfig: Record<string, { bg: string; text: string; label: string }> = {
  pdf: { bg: 'bg-red-50', text: 'text-red-600', label: 'PDF' },
  jpg: { bg: 'bg-blue-50', text: 'text-blue-600', label: 'Image' },
  jpeg: { bg: 'bg-blue-50', text: 'text-blue-600', label: 'Image' },
  png: { bg: 'bg-blue-50', text: 'text-blue-600', label: 'Image' },
  gif: { bg: 'bg-blue-50', text: 'text-blue-600', label: 'Image' },
  doc: { bg: 'bg-purple-50', text: 'text-purple-600', label: 'Doc' },
  docx: { bg: 'bg-purple-50', text: 'text-purple-600', label: 'Doc' },
  xls: { bg: 'bg-green-50', text: 'text-green-600', label: 'Excel' },
  xlsx: { bg: 'bg-green-50', text: 'text-green-600', label: 'Excel' },
}

function getFileInfo(filename: string) {
  const ext = filename.split('.').pop()?.toLowerCase() || ''
  return fileTypeConfig[ext] || { bg: 'bg-gray-50', text: 'text-gray-600', label: ext.toUpperCase() || 'File' }
}

function formatSize(bytes: number): string {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

export default function DocumentsTab({ claimId }: { claimId: number }) {
  const [docs, setDocs] = useState<Document[]>([])
  const [loading, setLoading] = useState(true)
  const [uploading, setUploading] = useState(false)
  const [dragOver, setDragOver] = useState(false)

  const fetchDocs = () => {
    setLoading(true)
    apiClient.get(`/claims-v2/${claimId}/documents`)
      .then(r => setDocs(r.data))
      .catch(() => {
        setDocs([
          { id: 1, filename: 'Police_Report_CLM2026.pdf', type: 'pdf', size: 245760, uploaded_by: 'K. Mokaleng', created_at: '2026-03-16', url: '#' },
          { id: 2, filename: 'Damage_Photo_Front.jpg', type: 'jpg', size: 1843200, uploaded_by: 'K. Mokaleng', created_at: '2026-03-16', url: '#' },
          { id: 3, filename: 'Damage_Photo_Side.jpg', type: 'jpg', size: 2150400, uploaded_by: 'K. Mokaleng', created_at: '2026-03-16', url: '#' },
          { id: 4, filename: 'Repair_Quotation.xlsx', type: 'xlsx', size: 52430, uploaded_by: 'M. Assessor', created_at: '2026-03-18', url: '#' },
          { id: 5, filename: 'Claim_Form_Signed.pdf', type: 'pdf', size: 189440, uploaded_by: 'K. Mokaleng', created_at: '2026-03-15', url: '#' },
        ])
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetchDocs() }, [claimId])

  const handleUpload = (files: FileList) => {
    setUploading(true)
    const formData = new FormData()
    Array.from(files).forEach((f, i) => formData.append(`files[${i}]`, f))
    apiClient.post(`/claims-v2/${claimId}/documents`, formData, { headers: { 'Content-Type': 'multipart/form-data' } })
      .then(() => fetchDocs())
      .catch(() => {
        // Mock: add to list
        const newDocs = Array.from(files).map((f, i) => ({
          id: Date.now() + i,
          filename: f.name,
          type: f.name.split('.').pop() || '',
          size: f.size,
          uploaded_by: 'You',
          created_at: new Date().toISOString().split('T')[0],
          url: '#',
        }))
        setDocs(prev => [...newDocs, ...prev])
      })
      .finally(() => setUploading(false))
  }

  const handleDelete = (docId: number) => {
    if (!confirm('Delete this document?')) return
    apiClient.delete(`/claims-v2/${claimId}/documents/${docId}`)
      .catch(() => {})
      .finally(() => setDocs(prev => prev.filter(d => d.id !== docId)))
  }

  if (loading) {
    return <div className="flex justify-center py-12"><div className="animate-spin w-8 h-8 border-4 border-claims-primary border-t-transparent rounded-full" /></div>
  }

  return (
    <div className="space-y-4">
      {/* Upload Area */}
      <div
        className={`border-2 border-dashed rounded-xl p-6 text-center transition ${
          dragOver ? 'border-claims-primary bg-claims-light/20' : 'border-gray-300'
        }`}
        onDragOver={e => { e.preventDefault(); setDragOver(true) }}
        onDragLeave={() => setDragOver(false)}
        onDrop={e => { e.preventDefault(); setDragOver(false); if (e.dataTransfer.files.length) handleUpload(e.dataTransfer.files) }}
      >
        <svg className="w-10 h-10 text-gray-400 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1}>
          <path strokeLinecap="round" strokeLinejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
        </svg>
        <p className="text-sm text-gray-600 mb-1">Drag & drop files here</p>
        <label className="inline-block px-4 py-1.5 bg-claims-primary text-white rounded-lg text-sm font-medium cursor-pointer hover:bg-claims-dark transition">
          {uploading ? 'Uploading...' : 'Browse Files'}
          <input type="file" multiple className="hidden" onChange={e => e.target.files && handleUpload(e.target.files)} disabled={uploading} />
        </label>
      </div>

      {/* Document Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
        {docs.map(doc => {
          const info = getFileInfo(doc.filename)
          return (
            <div key={doc.id} className="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-sm transition">
              <div className="flex items-start gap-3">
                <div className={`w-10 h-10 ${info.bg} rounded-lg flex items-center justify-center flex-shrink-0`}>
                  <span className={`text-xs font-bold ${info.text}`}>{info.label}</span>
                </div>
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium text-gray-800 truncate">{doc.filename}</p>
                  <div className="flex items-center gap-2 mt-0.5">
                    <span className={`text-[10px] font-medium px-1.5 py-0.5 rounded ${info.bg} ${info.text}`}>{info.label}</span>
                    <span className="text-xs text-gray-400">{formatSize(doc.size)}</span>
                  </div>
                  <p className="text-xs text-gray-400 mt-1">by {doc.uploaded_by} on {doc.created_at}</p>
                </div>
              </div>
              <div className="flex items-center justify-end gap-2 mt-3 pt-2 border-t border-gray-100">
                <a
                  href={doc.url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="text-xs text-claims-primary hover:text-claims-dark font-medium transition"
                >
                  Download
                </a>
                <button
                  onClick={() => handleDelete(doc.id)}
                  className="text-xs text-gray-400 hover:text-red-500 font-medium transition"
                >
                  Delete
                </button>
              </div>
            </div>
          )
        })}
        {docs.length === 0 && (
          <p className="text-sm text-gray-400 py-8 text-center col-span-full">No documents uploaded yet.</p>
        )}
      </div>
    </div>
  )
}
