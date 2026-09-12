import DualScrollTable from '../../components/common/DualScrollTable'
import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import {
  useReinsuranceCoverageGroups,
  useCreateReinsuranceCoverageGroup,
  useUpdateReinsuranceCoverageGroup,
  useDeleteReinsuranceCoverageGroup,
  useReinsuranceFormLookups,
} from '../../hooks/useReinsurance'
import { fetchReinsuranceCoverageGroup, fetchProductCoverages, type GroupCoverageRow } from '../../api/reinsurance'
import apiClient from '../../api/client'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import EmptyState from '../../components/common/EmptyState'

function Field({ label, required, error, children }: { label: string; required?: boolean; error?: string; children: React.ReactNode }) {
  return (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1">
        {label}{required && <span className="text-red-500 ml-1">*</span>}
      </label>
      {children}
      {error && <p className="text-xs text-red-600 mt-1">{error}</p>}
    </div>
  )
}

const EMPTY_FORM = {
  group_code: '',
  group_name: '',
  product_id: '',
  status: 1,
}

type GroupFormState = typeof EMPTY_FORM

export default function CoverageGroupingPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [jumpPage, setJumpPage] = useState('')
  const [showForm, setShowForm] = useState(false)
  const [editId, setEditId] = useState<number | null>(null)
  const [form, setForm] = useState<GroupFormState>({ ...EMPTY_FORM })
  const [coverages, setCoverages] = useState<GroupCoverageRow[]>([])
  const [coveragesLoading, setCoveragesLoading] = useState(false)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [apiError, setApiError] = useState<string | null>(null)

  const filters = {
    search:   searchParams.get('search') || undefined,
    per_page: 25,
    page:     Number(searchParams.get('page') || '1'),
  }

  const { data, isLoading, isFetching } = useReinsuranceCoverageGroups(filters)
  const { data: lookups } = useReinsuranceFormLookups()
  const createMutation = useCreateReinsuranceCoverageGroup()
  const updateMutation = useUpdateReinsuranceCoverageGroup()
  const deleteMutation = useDeleteReinsuranceCoverageGroup()

  const saving = createMutation.isPending || updateMutation.isPending

  function updateFilter(key: string, value: string) {
    const next = new URLSearchParams(searchParams)
    if (value) next.set(key, value)
    else next.delete(key)
    next.delete('page')
    setSearchParams(next)
  }

  function goToPage(page: number) {
    const next = new URLSearchParams(searchParams)
    next.set('page', String(page))
    setSearchParams(next)
  }

  const meta = data?.meta
  const currentPage = meta?.current_page ?? 1
  const lastPage = meta?.last_page ?? 1

  function getPageNumbers() {
    const pages: (number | '...')[] = []
    if (lastPage <= 7) {
      for (let i = 1; i <= lastPage; i++) pages.push(i)
    } else {
      pages.push(1)
      if (currentPage > 3) pages.push('...')
      for (let i = Math.max(2, currentPage - 1); i <= Math.min(lastPage - 1, currentPage + 1); i++) pages.push(i)
      if (currentPage < lastPage - 2) pages.push('...')
      pages.push(lastPage)
    }
    return pages
  }

  function openAdd() {
    setEditId(null)
    setForm({ ...EMPTY_FORM })
    setCoverages([])
    setErrors({})
    setApiError(null)
    setShowForm(true)
  }

  async function openEdit(item: any) {
    if (!item) return
    setEditId(item.id)
    setForm({
      group_code: item.groupCode ?? '',
      group_name: item.groupName ?? '',
      product_id: item.productId != null ? String(item.productId) : '',
      status:     item.status ?? 1,
    })
    setCoverages([])
    setErrors({})
    setApiError(null)
    setShowForm(true)
    try {
      setCoveragesLoading(true)

      // Load saved coverage data
      const full = await fetchReinsuranceCoverageGroup(item.id)
      console.log('📋 Edit form - Saved coverages:', full.coverages)

      // Get IDs of already-selected coverages for marking
      const selectedCoverageIds = new Set((full.coverages ?? []).map(c => c.coverage_id))
      console.log('✅ Already selected coverage IDs:', Array.from(selectedCoverageIds))

      // Load ALL available coverages for this product (same as add form)
      if (item.productId) {
        console.log('📥 Loading all coverages for product:', item.productId)
        const res = await fetchProductCoverages(item.productId)

        const flattened: any[] = []

        for (const coverage of (res.data ?? [])) {
          // Add parent coverage
          const isSelected = selectedCoverageIds.has(coverage.coverage_id)
          const savedData = (full.coverages ?? []).find(c => c.coverage_id === coverage.coverage_id)

          flattened.push({
            id:            savedData?.id,
            coverage_id:   Number(coverage.coverage_id),
            coverage_name: coverage.name,
            si_premium:    savedData?.si_premium != null ? Number(savedData.si_premium) : null,
            ri_limit:      savedData?.ri_limit != null ? Number(savedData.ri_limit) : null,
            limit_value:   savedData?.limit_value ?? null,
            is_parent:     true,
            is_selected:   isSelected,
          })
          console.log(`Parent: ${coverage.name} - Selected: ${isSelected}`)

          // Try to load sub-coverages for ANY coverage code
          if (coverage.code) {
            try {
              console.log(`📥 Loading sub-coverages for: ${coverage.code}`)
              const { data: subData } = await apiClient.get(`/reinsurance/sub-coverages/${coverage.code}`)

              const subCoverages = subData.data ?? []
              if (subCoverages.length > 0) {
                // Sub-coverages found - add them all
                subCoverages.forEach((sub: any) => {
                  const subSelected = selectedCoverageIds.has(sub.id)
                  const subSavedData = (full.coverages ?? []).find(c => c.coverage_id === sub.id)

                  flattened.push({
                    id:            subSavedData?.id,
                    coverage_id:   Number(sub.id),
                    coverage_name: sub.name,
                    si_premium:    subSavedData?.si_premium != null ? Number(subSavedData.si_premium) : null,
                    ri_limit:      subSavedData?.ri_limit != null ? Number(subSavedData.ri_limit) : null,
                    limit_value:   subSavedData?.limit_value ?? null,
                    is_parent:     false,
                    is_selected:   subSelected,
                  })
                })
              } else if (coverage.is_hardcoded) {
                // No sub-coverages and this is a hardcoded type
                console.log(`📭 Using hardcoded variants for: ${coverage.code}`)
                const { data: hardData } = await apiClient.get(`/reinsurance/hardcoded-coverages/${coverage.code}`)
                // Saved rows store motor-trader / commercial-motor variants under
                // FIXED numeric coverage_ids (mtext_→15, mtint_→16, comm_→22) plus
                // the variant NAME — see buildGroupCoverageRows on the API. The
                // variant checkboxes here use string ids (mtext_comprehensive, …),
                // so a saved row must be matched by (fixed id + variant name), NOT
                // by the string id. Matching on the string id never hit, so no
                // variant was pre-selected and its saved SI/limit came back empty
                // (and was then dropped by the si_premium != null save filter).
                const motorMap: Record<string, number> = { mtext_: 15, mtint_: 16, comm_: 22 }
                ;(hardData.data ?? []).forEach((variant: any) => {
                  const prefix = Object.keys(motorMap).find(p => String(variant.id).startsWith(p))
                  const variantFixedId = prefix ? motorMap[prefix] : null
                  const variantSavedData = (full.coverages ?? []).find(c =>
                    Number(c.coverage_id) === variantFixedId && c.coverage_name === variant.name)
                  const variantSelected = !!variantSavedData

                  flattened.push({
                    id:            variantSavedData?.id,
                    coverage_id:   variant.id,
                    coverage_name: variant.name,
                    si_premium:    variantSavedData?.si_premium != null ? Number(variantSavedData.si_premium) : null,
                    ri_limit:      variantSavedData?.ri_limit != null ? Number(variantSavedData.ri_limit) : null,
                    limit_value:   variantSavedData?.limit_value ?? null,
                    is_parent:     false,
                    is_selected:   variantSelected,
                  })
                })
              }
            } catch (err) {
              console.error(`❌ Error loading variants for ${coverage.code}:`, err)
            }
          }
        }

        console.log('✅ Final edit form coverages:', flattened)
        setCoverages(flattened)
      }
    } catch (err) {
      console.error('❌ Error in openEdit:', err)
      setApiError('Failed to load coverage details.')
    } finally {
      setCoveragesLoading(false)
    }
  }

  async function handleProductChange(productId: string) {
    console.log('🔄 handleProductChange called with productId:', productId)
    setField('product_id', productId)
    if (!productId) { setCoverages([]); console.log('❌ No productId'); return }
    try {
      console.log('📡 Fetching product coverages...')
      setCoveragesLoading(true)
      const res = await fetchProductCoverages(Number(productId))
      console.log('✅ Product coverages response:', res)

      // Use hybrid approach: try to load sub-coverages for ALL coverage codes,
      // fall back to hardcoded variants if none exist
      const flattened: any[] = []

      for (const coverage of (res.data ?? [])) {
        // Add parent coverage
        flattened.push({
          coverage_id:   Number(coverage.coverage_id),
          coverage_name: coverage.name,
          si_premium:    null,
          ri_limit:      null,
          limit_value:   null,
          is_parent:     true,
        })
        console.log(`Processing coverage: ${coverage.name}, code: ${coverage.code}, is_hardcoded: ${coverage.is_hardcoded}`)

        // Try to load sub-coverages for ANY coverage code (just like old project)
        if (coverage.code) {
          try {
            console.log(`📥 Loading sub-coverages for: ${coverage.code}`)
            const { data: subData } = await apiClient.get(`/reinsurance/sub-coverages/${coverage.code}`)
            console.log(`Sub-coverages data for ${coverage.code}:`, subData)

            const subCoverages = subData.data ?? []
            if (subCoverages.length > 0) {
              // Sub-coverages found - add them all
              subCoverages.forEach((sub: any) => {
                flattened.push({
                  coverage_id:   Number(sub.id),
                  coverage_name: sub.name,
                  si_premium:    null,
                  ri_limit:      null,
                  limit_value:   null,
                  is_parent:     false,
                })
              })
            } else if (coverage.is_hardcoded) {
              // No sub-coverages and this is a hardcoded type - use hardcoded variants
              console.log(`📭 No sub-coverages, trying hardcoded variants for: ${coverage.code}`)
              const { data: hardData } = await apiClient.get(`/reinsurance/hardcoded-coverages/${coverage.code}`)
              console.log(`Hardcoded variants for ${coverage.code}:`, hardData)
              ;(hardData.data ?? []).forEach((variant: any) => {
                flattened.push({
                  coverage_id:   variant.id,
                  coverage_name: variant.name,
                  si_premium:    null,
                  ri_limit:      null,
                  limit_value:   null,
                  is_parent:     false,
                })
              })
            }
          } catch (err) {
            console.error(`❌ Error loading variants for ${coverage.code}:`, err)
          }
        }
      }

      console.log('Final flattened coverages:', flattened)
      setCoverages(flattened)
    } catch (err) {
      console.error('Error in handleProductChange:', err)
      setApiError('Failed to load product coverages.')
      setCoverages([])
    } finally {
      setCoveragesLoading(false)
    }
  }

  function setCoverageField(index: number, key: keyof GroupCoverageRow, value: number | string | null) {
    setCoverages(rows => rows.map((r, i) => i === index ? { ...r, [key]: value } : r))
  }

  function closeForm() {
    setShowForm(false)
    setEditId(null)
    setApiError(null)
  }

  function setField(key: keyof GroupFormState, value: string | number) {
    setForm(f => ({ ...f, [key]: value }))
  }

  function validate() {
    const errs: Record<string, string> = {}
    if (!form.group_code.trim()) errs.group_code = 'Group code is required.'
    if (!form.group_name.trim()) errs.group_name = 'Group name is required.'
    setErrors(errs)
    return Object.keys(errs).length === 0
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!validate()) return
    // Don't submit before the Coverages list has finished loading — an empty
    // payload would otherwise wipe the group's existing coverage rows.
    if (coveragesLoading) {
      setApiError('Please wait for the coverages to finish loading before saving.')
      return
    }
    setApiError(null)
    // Send only the coverages the user configured (an SI/Premium selection),
    // mirroring the old project (FIELD1 != ""). Keep coverage_id as-is: numeric
    // ids and the hardcoded motor variant string ids (mtext_*, mtint_*, comm_*)
    // are both resolved server-side — the motor variants map to fixed coverage
    // ids 15/16/22, everything else to its s_CoverageCode.
    const savableCoverages = coverages.filter(c => c.si_premium != null)
    const payload = {
      group_code: form.group_code,
      group_name: form.group_name,
      product_id: form.product_id ? Number(form.product_id) : null,
      status:     form.status,
      coverages:  savableCoverages.map(c => ({
        coverage_id:   c.coverage_id,
        coverage_name: c.coverage_name,
        si_premium:    c.si_premium,
        ri_limit:      c.ri_limit,
        limit_value:   c.ri_limit === 3 ? c.limit_value : null,
      })),
    }
    try {
      if (editId) {
        await updateMutation.mutateAsync({ id: editId, payload })
      } else {
        await createMutation.mutateAsync(payload)
      }
      closeForm()
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
      const serverErrs = e?.response?.data?.errors
      if (serverErrs) {
        const serverErrors: Record<string, string> = {}
        for (const [k, v] of Object.entries(serverErrs)) {
          serverErrors[k] = Array.isArray(v) ? v[0] : String(v)
        }
        setErrors(serverErrors)
        // Surface errors that aren't tied to a visible form field (e.g.
        // coverages.*.coverage_id) so the save never fails silently.
        const visibleFields = ['group_code', 'group_name']
        if (!Object.keys(serverErrors).some(k => visibleFields.includes(k))) {
          setApiError(e?.response?.data?.message ?? Object.values(serverErrors)[0] ?? 'Validation failed.')
        }
      } else {
        setApiError(e?.response?.data?.message ?? 'An error occurred. Please try again.')
      }
    }
  }

  async function handleDelete(id: number, name: string) {
    if (!window.confirm(`Delete coverage group "${name}"? Related coverage rows will also be removed. This cannot be undone.`)) return
    try {
      await deleteMutation.mutateAsync(id)
    } catch {
      alert('Failed to delete. The record may be in use.')
    }
  }

  const items = data?.data ?? []
  const products = lookups?.products ?? []

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-800">Reinsurance Coverage Grouping</h1>
        <button
          onClick={openAdd}
          className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition"
        >
          + Add New
        </button>
      </div>

      <div className="flex flex-wrap gap-3 items-end">
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Search</label>
          <input
            type="text"
            placeholder="Group code, name..."
            defaultValue={filters.search}
            onKeyDown={e => { if (e.key === 'Enter') updateFilter('search', (e.target as HTMLInputElement).value) }}
            onBlur={e => updateFilter('search', e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm w-64 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
      </div>

      <div className="bg-white rounded-lg shadow-sm border overflow-hidden relative">
        {isFetching && !isLoading && (
          <div className="absolute top-0 left-0 right-0 h-0.5 bg-blue-500 animate-pulse z-10" />
        )}
        <DualScrollTable>
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-gray-600 uppercase text-xs tracking-wider">
              <tr>
                <th className="px-4 py-3 text-left">ID</th>
                <th className="px-4 py-3 text-left">Group Code</th>
                <th className="px-4 py-3 text-left">Group Name</th>
                <th className="px-4 py-3 text-left">Product ID</th>
                <th className="px-4 py-3 text-left">Status</th>
                <th className="px-4 py-3 text-left">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {isLoading ? (
                <tr><td colSpan={6} className="px-4 py-12 text-center"><LoadingSpinner size="md" /></td></tr>
              ) : items.length === 0 ? (
                <tr><td colSpan={6} className="p-0"><EmptyState compact title="No coverage groups found" description="No coverage groups have been configured yet." /></td></tr>
              ) : (
                items.map(item => (
                  <tr key={item.id} className="hover:bg-gray-50 transition">
                    <td className="px-4 py-2 font-medium text-gray-700">{item.id}</td>
                    <td className="px-4 py-2 font-medium text-blue-600">{item.groupCode || '\u2014'}</td>
                    <td className="px-4 py-2">{item.groupName || '\u2014'}</td>
                    <td className="px-4 py-2">{item.productId ?? '\u2014'}</td>
                    <td className="px-4 py-2">
                      {item.status === 1
                        ? <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Active</span>
                        : <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Inactive</span>
                      }
                    </td>
                    <td className="px-4 py-2">
                      <div className="flex items-center gap-2">
                        <button
                          onClick={() => openEdit(item)}
                          className="text-blue-500 hover:text-blue-700 p-1 rounded hover:bg-blue-50"
                          title="Edit"
                        >
                          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                        </button>
                        <button
                          onClick={() => handleDelete(item.id, item.groupName ?? String(item.id))}
                          className="text-red-400 hover:text-red-600 p-1 rounded hover:bg-red-50"
                          title="Delete"
                        >
                          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </DualScrollTable>

        {meta && meta.last_page > 1 && (
          <div className="flex items-center justify-between px-4 py-3 border-t bg-gray-50 text-sm">
            <span className="text-gray-500">Showing {meta.from}&ndash;{meta.to} of {meta.total}</span>
            <div className="flex items-center gap-1">
              <button disabled={currentPage === 1} onClick={() => goToPage(currentPage - 1)} className="px-2.5 py-1 rounded border text-gray-600 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed">Prev</button>
              {getPageNumbers().map((p, i) =>
                p === '...' ? (
                  <span key={`e${i}`} className="px-1.5 text-gray-400">...</span>
                ) : (
                  <button key={p} onClick={() => goToPage(p as number)} className={`px-2.5 py-1 rounded border text-sm ${p === currentPage ? 'bg-blue-600 text-white border-blue-600' : 'text-gray-600 hover:bg-gray-100'}`}>{p}</button>
                )
              )}
              <button disabled={currentPage === lastPage} onClick={() => goToPage(currentPage + 1)} className="px-2.5 py-1 rounded border text-gray-600 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
              <div className="flex items-center gap-1 ml-3 border-l pl-3">
                <span className="text-gray-500 text-xs">Go to</span>
                <input type="number" min={1} max={lastPage} value={jumpPage} onChange={e => setJumpPage(e.target.value)} onKeyDown={e => { if (e.key === 'Enter') { const p = Number(jumpPage); if (p >= 1 && p <= lastPage) { goToPage(p); setJumpPage('') } } }} className="w-14 px-2 py-1 border rounded text-sm text-center" placeholder="#" />
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Slide-over */}
      {showForm && (
        <div className="fixed inset-0 z-50 flex justify-end">
          <div className="fixed inset-0 bg-black/30" onClick={closeForm} />
          <div className="relative w-full max-w-lg bg-white shadow-xl overflow-y-auto">
            <div className="px-5 py-3 border-b flex items-center justify-between">
              <h2 className="text-lg font-semibold">{editId ? 'Edit' : 'Add New'} Coverage Group</h2>
              <button onClick={closeForm} className="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
            </div>
            <form onSubmit={handleSubmit} className="p-5 space-y-3">
              {apiError && (
                <div className="p-3 bg-red-50 border border-red-200 rounded text-sm text-red-700">{apiError}</div>
              )}
              <Field label="Group Code" required error={errors.group_code}>
                <input
                  type="text"
                  value={form.group_code}
                  onChange={e => setField('group_code', e.target.value)}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="e.g. GRP-MOTOR"
                />
              </Field>
              <Field label="Group Name" required error={errors.group_name}>
                <input
                  type="text"
                  value={form.group_name}
                  onChange={e => setField('group_name', e.target.value)}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="e.g. Motor Coverages"
                />
              </Field>
              <Field label="Product" error={errors.product_id}>
                {editId ? (
                  <div className="px-3 py-2 border border-gray-200 bg-gray-50 rounded-md text-sm text-gray-700">
                    {products.find(p => String(p.id) === form.product_id)?.name ?? '—'}
                  </div>
                ) : (
                  <select
                    value={form.product_id}
                    onChange={e => handleProductChange(e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 bg-white"
                  >
                    <option value="">-- Select Product --</option>
                    {products.map(p => (
                      <option key={p.id} value={p.id}>{p.name}</option>
                    ))}
                  </select>
                )}
              </Field>
              <Field label="Status">
                <label className="inline-flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={form.status === 1}
                    onChange={e => setField('status', e.target.checked ? 1 : 0)}
                    className="w-4 h-4 text-blue-600 border-gray-300 rounded"
                  />
                  <span className="text-sm text-gray-600">{form.status === 1 ? 'Active' : 'Inactive'}</span>
                </label>
              </Field>

              {(coveragesLoading || coverages.length > 0) && (
                <div>
                  <h3 className="text-sm font-semibold text-gray-700 mb-2">Coverages</h3>
                  {coveragesLoading ? (
                    <div className="py-4 flex justify-center"><LoadingSpinner size="sm" /></div>
                  ) : (
                    <div className="border rounded-md overflow-hidden">
                      <table className="w-full text-xs">
                        <thead className="bg-gray-50 text-gray-600 uppercase tracking-wider">
                          <tr>
                            <th className="px-2 py-2 text-left">Coverage Name</th>
                            <th className="px-2 py-2 text-left">SI/Premium</th>
                            <th className="px-2 py-2 text-left">RI Limit</th>
                            <th className="px-2 py-2 text-left">Limit</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                          {coverages.map((c, idx) => (
                            <tr key={`${c.coverage_id}-${idx}`} className={c.is_parent ? 'bg-gray-100 font-semibold' : 'bg-white'}>
                              <td colSpan={c.is_parent ? 4 : 1} className={`px-2 py-1.5 ${c.is_parent ? 'text-gray-900' : 'text-gray-700 pl-6'}`}>
                                <div className="flex items-center gap-2">
                                  {c.is_selected && (
                                    <span className="text-green-600 font-bold" title="Already selected">✓</span>
                                  )}
                                  {c.coverage_name}
                                </div>
                              </td>
                              {!c.is_parent && (
                                <>
                                  <td className="px-2 py-1.5">
                                    <select
                                      value={c.si_premium ?? ''}
                                      onChange={e => setCoverageField(idx, 'si_premium', e.target.value === '' ? null : Number(e.target.value))}
                                      className="w-full px-1.5 py-1 border border-gray-300 rounded text-xs bg-white"
                                    >
                                      <option value="">Select</option>
                                      <option value="1">SumInsured</option>
                                      <option value="2">Premium</option>
                                    </select>
                                  </td>
                                  <td className="px-2 py-1.5">
                                    <select
                                      value={c.ri_limit ?? ''}
                                      onChange={e => setCoverageField(idx, 'ri_limit', e.target.value === '' ? null : Number(e.target.value))}
                                      className="w-full px-1.5 py-1 border border-gray-300 rounded text-xs bg-white"
                                    >
                                      <option value="">Select</option>
                                      <option value="1">SumInsured</option>
                                      <option value="2">Skip</option>
                                      <option value="3">Other</option>
                                    </select>
                                  </td>
                                  <td className="px-2 py-1.5">
                                    {c.ri_limit === 3 && (
                                      <input
                                        type="text"
                                        value={c.limit_value ?? ''}
                                        onChange={e => setCoverageField(idx, 'limit_value', e.target.value)}
                                        className="w-full px-1.5 py-1 border border-gray-300 rounded text-xs"
                                        placeholder="Limit"
                                      />
                                    )}
                                  </td>
                                </>
                              )}
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  )}
                </div>
              )}

              <div className="flex gap-3 pt-2">
                <button
                  type="submit"
                  disabled={saving}
                  className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 disabled:opacity-50 transition"
                >
                  {saving ? 'Saving...' : editId ? 'Update' : 'Create'}
                </button>
                <button
                  type="button"
                  onClick={closeForm}
                  className="px-4 py-2 border border-gray-300 text-sm rounded-md hover:bg-gray-50 transition"
                >
                  Cancel
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}
