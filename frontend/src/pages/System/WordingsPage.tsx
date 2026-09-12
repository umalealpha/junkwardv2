import { useMemo, useRef, useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import apiClient from '../../api/client'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import { fmtDateTime } from '../../utils/format'

// ─── Types ────────────────────────────────────────────────────────────────────

interface CategoryRow {
  key: string
  label: string
  total: number
  active: number
}

interface CategoriesResponse {
  categories: CategoryRow[]
  can_manage: boolean
  can_hard_delete: boolean
}

interface WordingFile {
  id: number
  category: string
  product_code: string | null
  display_name: string
  s3_key: string
  size_bytes: number
  is_active: boolean
  notes: string | null
  uploaded_at: string
  deactivated_at: string | null
  uploader?: { id: number; email: string } | null
}

interface IndexResponse {
  items: WordingFile[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
  can_manage: boolean
  can_hard_delete: boolean
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function humanSize(bytes: number): string {
  if (!bytes) return '0 B'
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / 1024 / 1024).toFixed(2)} MB`
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function WordingsPage() {
  const qc = useQueryClient()
  const [activeCategory, setActiveCategory] = useState<string | null>(null)
  const [uploadOpen, setUploadOpen] = useState(false)
  const [uploadProductCode, setUploadProductCode] = useState('')
  const [uploadNotes, setUploadNotes] = useState('')
  const fileInputRef = useRef<HTMLInputElement>(null)

  const cats = useQuery<CategoriesResponse>({
    queryKey: ['wordings-categories'],
    queryFn: () => apiClient.get('/wordings/categories').then(r => r.data),
    refetchOnWindowFocus: false,
  })

  // Default to first category once data loads.
  const firstCategoryKey = cats.data?.categories?.[0]?.key
  const currentCategory = activeCategory ?? firstCategoryKey ?? null

  const list = useQuery<IndexResponse>({
    queryKey: ['wordings-list', currentCategory],
    queryFn: () =>
      apiClient
        .get('/wordings', { params: { category: currentCategory } })
        .then(r => r.data),
    enabled: !!currentCategory,
    refetchOnWindowFocus: false,
  })

  const canManage = cats.data?.can_manage ?? false
  const canHardDelete = cats.data?.can_hard_delete ?? false

  // ── Mutations ────────────────────────────────────────────────────────────

  const upload = useMutation({
    mutationFn: async (file: File) => {
      if (!currentCategory) throw new Error('No category selected')
      const form = new FormData()
      form.append('category', currentCategory)
      form.append('file', file)
      if (uploadProductCode.trim()) form.append('product_code', uploadProductCode.trim())
      if (uploadNotes.trim()) form.append('notes', uploadNotes.trim())
      const res = await apiClient.post('/wordings', form, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      return res.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['wordings-categories'] })
      qc.invalidateQueries({ queryKey: ['wordings-list', currentCategory] })
      setUploadOpen(false)
      setUploadProductCode('')
      setUploadNotes('')
      if (fileInputRef.current) fileInputRef.current.value = ''
    },
    onError: (err: any) => {
      alert(err?.response?.data?.error || 'Upload failed')
    },
  })

  const deactivate = useMutation({
    mutationFn: (id: number) =>
      apiClient.patch(`/wordings/${id}/deactivate`).then(r => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['wordings-categories'] })
      qc.invalidateQueries({ queryKey: ['wordings-list', currentCategory] })
    },
    onError: (err: any) => alert(err?.response?.data?.error || 'Deactivate failed'),
  })

  const reactivate = useMutation({
    mutationFn: (id: number) =>
      apiClient.patch(`/wordings/${id}/reactivate`).then(r => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['wordings-categories'] })
      qc.invalidateQueries({ queryKey: ['wordings-list', currentCategory] })
    },
    onError: (err: any) => alert(err?.response?.data?.error || 'Reactivate failed'),
  })

  const hardDelete = useMutation({
    mutationFn: (id: number) => apiClient.delete(`/wordings/${id}`).then(r => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['wordings-categories'] })
      qc.invalidateQueries({ queryKey: ['wordings-list', currentCategory] })
    },
    onError: (err: any) => alert(err?.response?.data?.error || 'Delete failed'),
  })

  // ── Actions ──────────────────────────────────────────────────────────────

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file) return
    if (file.size > 25 * 1024 * 1024) {
      alert('File is over 25 MB limit')
      return
    }
    upload.mutate(file)
  }

  const handleDownload = async (row: WordingFile) => {
    try {
      const res = await apiClient.get(`/wordings/${row.id}/download`)
      const url = res.data?.url
      if (!url) {
        alert('Could not generate download URL')
        return
      }
      window.open(url, '_blank', 'noopener,noreferrer')
    } catch (err: any) {
      alert(err?.response?.data?.error || 'Download failed')
    }
  }

  const currentLabel = useMemo(
    () => cats.data?.categories.find(c => c.key === currentCategory)?.label ?? currentCategory,
    [cats.data, currentCategory],
  )

  // ── Render ───────────────────────────────────────────────────────────────

  return (
    <div className="p-6 space-y-4">
      <div>
        <h1 className="text-2xl font-bold text-gray-800">Policy Wordings</h1>
        <p className="text-sm text-gray-500 mt-0.5">
          Canonical org-wide store for policy wording PDFs. Used by Graphite V2 quote sheet / policy doc generators
          and by sibling apps (Claims Tracker, future systems) as a single source of truth.
        </p>
      </div>

      {cats.isLoading && (
        <div className="flex justify-center py-8">
          <LoadingSpinner />
        </div>
      )}

      {cats.data && (
        <div className="flex gap-4">
          {/* Category sidebar */}
          <aside className="w-64 shrink-0 bg-white border border-gray-200 rounded-lg overflow-hidden">
            <header className="px-3 py-2 bg-gray-50 border-b border-gray-200">
              <h2 className="text-xs font-semibold text-gray-600 uppercase tracking-wide">Categories</h2>
            </header>
            <ul className="divide-y divide-gray-100">
              {cats.data.categories.map(c => {
                const isActive = c.key === currentCategory
                return (
                  <li key={c.key}>
                    <button
                      onClick={() => setActiveCategory(c.key)}
                      className={`w-full text-left px-3 py-2 text-sm flex items-center justify-between hover:bg-gray-50 ${
                        isActive ? 'bg-blue-50 border-l-4 border-blue-600' : ''
                      }`}
                    >
                      <span className={isActive ? 'font-semibold text-gray-800' : 'text-gray-700'}>
                        {c.label}
                      </span>
                      <span className="flex items-center gap-1.5 text-xs">
                        <span
                          className="inline-flex items-center justify-center w-6 h-5 rounded bg-green-100 text-green-700 font-semibold"
                          title="Active"
                        >
                          {c.active}
                        </span>
                        {c.total > c.active && (
                          <span
                            className="inline-flex items-center justify-center w-6 h-5 rounded bg-gray-200 text-gray-600"
                            title="Total (incl. inactive)"
                          >
                            {c.total}
                          </span>
                        )}
                      </span>
                    </button>
                  </li>
                )
              })}
            </ul>
          </aside>

          {/* Wordings table */}
          <section className="flex-1 bg-white border border-gray-200 rounded-lg overflow-hidden">
            <header className="px-4 py-2.5 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
              <div>
                <h2 className="text-sm font-semibold text-gray-700">{currentLabel}</h2>
                {list.data && (
                  <p className="text-xs text-gray-500 mt-0.5">
                    {list.data.meta.total} file{list.data.meta.total === 1 ? '' : 's'}
                  </p>
                )}
              </div>
              {canManage && currentCategory && (
                <button
                  onClick={() => setUploadOpen(o => !o)}
                  className="px-3 py-1.5 text-sm bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
                  disabled={upload.isPending}
                >
                  {uploadOpen ? 'Cancel' : '+ Upload PDF'}
                </button>
              )}
            </header>

            {/* Upload form */}
            {uploadOpen && canManage && (
              <div className="px-4 py-3 bg-blue-50/50 border-b border-gray-200 space-y-2">
                <div className="grid grid-cols-2 gap-2">
                  <label className="text-xs">
                    <span className="text-gray-600">Product code (optional)</span>
                    <input
                      type="text"
                      value={uploadProductCode}
                      onChange={e => setUploadProductCode(e.target.value)}
                      placeholder="e.g. P49, P79, P99"
                      className="mt-0.5 block w-full px-2 py-1 text-sm border border-gray-300 rounded"
                    />
                  </label>
                  <label className="text-xs">
                    <span className="text-gray-600">Notes (optional)</span>
                    <input
                      type="text"
                      value={uploadNotes}
                      onChange={e => setUploadNotes(e.target.value)}
                      placeholder="e.g. Revised v2 - 2026-05"
                      className="mt-0.5 block w-full px-2 py-1 text-sm border border-gray-300 rounded"
                    />
                  </label>
                </div>
                <label className="block text-xs text-gray-600">
                  Select PDF (max 25 MB):
                  <input
                    ref={fileInputRef}
                    type="file"
                    accept="application/pdf,.pdf"
                    onChange={handleFileChange}
                    disabled={upload.isPending}
                    className="mt-0.5 block w-full text-sm"
                  />
                </label>
                {upload.isPending && <div className="text-xs text-blue-700">Uploading…</div>}
              </div>
            )}

            {/* Table */}
            {list.isLoading ? (
              <div className="flex justify-center py-8">
                <LoadingSpinner />
              </div>
            ) : (
              <table className="w-full text-sm">
                <thead className="bg-gray-50 text-gray-500 uppercase text-xs">
                  <tr>
                    <th className="px-3 py-2 text-left">Filename</th>
                    <th className="px-3 py-2 text-left w-20">Product</th>
                    <th className="px-3 py-2 text-right w-24">Size</th>
                    <th className="px-3 py-2 text-left w-44">Uploaded</th>
                    <th className="px-3 py-2 text-left w-24">Status</th>
                    <th className="px-3 py-2 text-right w-56">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                  {(list.data?.items ?? []).length === 0 ? (
                    <tr>
                      <td colSpan={6} className="px-3 py-8 text-center text-sm text-gray-400 italic">
                        No wordings uploaded in this category yet
                        {canManage && ' — click "Upload PDF" to add one'}.
                      </td>
                    </tr>
                  ) : (
                    list.data!.items.map(row => (
                      <tr key={row.id} className="hover:bg-gray-50">
                        <td className="px-3 py-2">
                          <div className="font-medium text-gray-800">{row.display_name}</div>
                          {row.notes && <div className="text-xs text-gray-500 mt-0.5">{row.notes}</div>}
                        </td>
                        <td className="px-3 py-2 text-xs text-gray-600">{row.product_code || '—'}</td>
                        <td className="px-3 py-2 text-right text-gray-700">{humanSize(row.size_bytes)}</td>
                        <td className="px-3 py-2 text-xs text-gray-600">
                          <div>{fmtDateTime(row.uploaded_at)}</div>
                          {row.uploader?.email && (
                            <div className="text-gray-400">{row.uploader.email}</div>
                          )}
                        </td>
                        <td className="px-3 py-2">
                          {row.is_active ? (
                            <span className="inline-block px-2 py-0.5 rounded-full text-[11px] font-semibold bg-green-100 text-green-700">
                              Active
                            </span>
                          ) : (
                            <span className="inline-block px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-200 text-gray-600">
                              Inactive
                            </span>
                          )}
                        </td>
                        <td className="px-3 py-2 text-right space-x-1.5">
                          <button
                            onClick={() => handleDownload(row)}
                            className="text-xs text-blue-700 hover:underline"
                          >
                            Download
                          </button>
                          {canManage && row.is_active && (
                            <button
                              onClick={() => {
                                if (window.confirm(`Deactivate "${row.display_name}"? Apps will stop using it for new PDFs.`))
                                  deactivate.mutate(row.id)
                              }}
                              className="text-xs text-amber-700 hover:underline"
                              disabled={deactivate.isPending}
                            >
                              Deactivate
                            </button>
                          )}
                          {canManage && !row.is_active && (
                            <button
                              onClick={() => reactivate.mutate(row.id)}
                              className="text-xs text-green-700 hover:underline"
                              disabled={reactivate.isPending}
                            >
                              Reactivate
                            </button>
                          )}
                          {canHardDelete && (
                            <button
                              onClick={() => {
                                if (
                                  window.confirm(
                                    `Hard-delete "${row.display_name}"? File is preserved in S3 versioning but removed from this admin view.`,
                                  )
                                )
                                  hardDelete.mutate(row.id)
                              }}
                              className="text-xs text-red-700 hover:underline"
                              disabled={hardDelete.isPending}
                            >
                              Delete
                            </button>
                          )}
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            )}
          </section>
        </div>
      )}
    </div>
  )
}
