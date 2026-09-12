import DualScrollTable from '../../components/common/DualScrollTable'
import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import {
  useReinsuranceTreaties,
  useCreateReinsuranceTreaty,
  useUpdateReinsuranceTreaty,
  useDeleteReinsuranceTreaty,
  useRolloverReinsuranceTreaty,
  useReinsuranceFormulas,
} from '../../hooks/useReinsurance'
import type { TreatyRolloverResult } from '../../api/reinsurance'
import { fmtDate } from '../../utils/format'
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
  treaty_name: '',
  treaty_number: '',
  effective_from: '',
  effective_to: '',
  provisional_commission: '',
  proportional_share: '',
  cash_loss_advise: '',
  event_limit: '',
  exclusions: '',
  formula_attached: [] as number[],
  status: 1,
}

type TreatyFormState = typeof EMPTY_FORM

export default function TreatyPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [jumpPage, setJumpPage] = useState('')
  const [showForm, setShowForm] = useState(false)
  const [editId, setEditId] = useState<number | null>(null)
  const [form, setForm] = useState<TreatyFormState>({ ...EMPTY_FORM })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [apiError, setApiError] = useState<string | null>(null)

  const filters = {
    search:   searchParams.get('search') || undefined,
    per_page: 25,
    page:     Number(searchParams.get('page') || '1'),
  }

  const { data, isLoading, isFetching } = useReinsuranceTreaties(filters)
  const { data: formulasData } = useReinsuranceFormulas({ per_page: 100 })
  const createMutation = useCreateReinsuranceTreaty()
  const updateMutation = useUpdateReinsuranceTreaty()
  const deleteMutation = useDeleteReinsuranceTreaty()
  const rolloverMutation = useRolloverReinsuranceTreaty()

  // ─── Rollover modal state ───────────────────────────────────────────────
  // Kept separate from the add/edit slide-over so the user can preview a
  // dry-run without losing any Add form they had in progress.
  const [rolloverSource, setRolloverSource] = useState<any | null>(null)
  const [rolloverForm, setRolloverForm] = useState({
    name: '',
    number: '',
    effective_from: '',
    effective_to: '',
    force: false,
  })
  const [rolloverResult, setRolloverResult] = useState<TreatyRolloverResult | null>(null)
  const [rolloverError, setRolloverError] = useState<string | null>(null)

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

  function formatDate(d: string | null) {
    return fmtDate(d)
  }

  function openAdd() {
    setEditId(null)
    setForm({ ...EMPTY_FORM })
    setErrors({})
    setApiError(null)
    setShowForm(true)
  }

  function openEdit(item: any) {
    if (!item) return
    setEditId(item.id)
    setForm({
      treaty_name:             item.treatyName ?? '',
      treaty_number:           item.treatyNumber ?? '',
      effective_from:          item.effectiveFrom ? item.effectiveFrom.substring(0, 10) : '',
      effective_to:            item.effectiveTo ? item.effectiveTo.substring(0, 10) : '',
      provisional_commission:  item.provisionalCommission != null ? String(item.provisionalCommission) : '',
      proportional_share:      item.proportionalShare != null ? String(item.proportionalShare) : '',
      cash_loss_advise:        item.cashLossAdvise != null ? String(item.cashLossAdvise) : '',
      event_limit:             item.eventLimit != null ? String(item.eventLimit) : '',
      exclusions:              item.exclusions ?? '',
      formula_attached:        item.formulaAttached ?? [],
      status:                  item.status ?? 1,
    })
    setErrors({})
    setApiError(null)
    setShowForm(true)
  }

  function closeForm() {
    setShowForm(false)
    setEditId(null)
    setApiError(null)
  }

  function validate() {
    const errs: Record<string, string> = {}
    if (!form.treaty_name.trim()) errs.treaty_name = 'Treaty name is required.'
    if (!form.treaty_number.trim()) errs.treaty_number = 'Treaty number is required.'
    if (!form.effective_from) errs.effective_from = 'Effective from date is required.'
    if (!form.effective_to) errs.effective_to = 'Effective to date is required.'
    if (form.effective_from && form.effective_to && form.effective_to < form.effective_from) {
      errs.effective_to = 'Effective to must be on or after effective from.'
    }
    if (form.provisional_commission === '') errs.provisional_commission = 'Required.'
    if (form.proportional_share === '') errs.proportional_share = 'Required.'
    if (form.cash_loss_advise === '') errs.cash_loss_advise = 'Required.'
    if (form.event_limit === '') errs.event_limit = 'Required.'
    setErrors(errs)
    return Object.keys(errs).length === 0
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!validate()) return
    setApiError(null)
    const payload = {
      treaty_name:             form.treaty_name,
      treaty_number:           form.treaty_number,
      effective_from:          form.effective_from,
      effective_to:            form.effective_to,
      provisional_commission:  Number(form.provisional_commission),
      proportional_share:      Number(form.proportional_share),
      cash_loss_advise:        Number(form.cash_loss_advise),
      event_limit:             Number(form.event_limit),
      exclusions:              form.exclusions,
      formula_attached:        form.formula_attached,
      status:                  form.status,
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
      if (e?.response?.data?.errors) {
        const serverErrors: Record<string, string> = {}
        for (const [k, v] of Object.entries(e.response.data.errors)) {
          serverErrors[k] = Array.isArray(v) ? v[0] : String(v)
        }
        setErrors(serverErrors)
      } else {
        setApiError(e?.response?.data?.message ?? 'An error occurred. Please try again.')
      }
    }
  }

  async function handleDelete(id: number, name: string) {
    if (!window.confirm(`Delete treaty "${name}"? This cannot be undone.`)) return
    try {
      await deleteMutation.mutateAsync(id)
    } catch {
      alert('Failed to delete. The record may be in use.')
    }
  }

  // ─── Rollover helpers ───────────────────────────────────────────────────
  // Bump YYYY[_-]YYYY in the source treaty name so the user sees the
  // auto-derived new name as a pre-filled default (editable). Mirrors
  // TreatyRolloverService::bumpName() on the backend so users don't have to
  // guess what the server will do.
  function bumpName(src: string): string {
    const m = src.match(/^(.+?)(\d{4})([_\-])(\d{4})(.*)$/)
    if (!m) return ''
    return `${m[1]}${Number(m[2]) + 1}${m[3]}${Number(m[4]) + 1}${m[5]}`
  }

  function defaultNewFrom(srcTo: string | null | undefined): string {
    if (!srcTo) return ''
    try {
      const d = new Date(srcTo.substring(0, 10))
      d.setDate(d.getDate() + 1)
      return d.toISOString().substring(0, 10)
    } catch { return '' }
  }

  function defaultNewTo(from: string): string {
    if (!from) return ''
    try {
      const d = new Date(from)
      d.setFullYear(d.getFullYear() + 1)
      d.setDate(d.getDate() - 1)
      return d.toISOString().substring(0, 10)
    } catch { return '' }
  }

  function openRollover(item: any) {
    const newFrom = defaultNewFrom(item.effectiveTo)
    setRolloverSource(item)
    setRolloverForm({
      name:           bumpName(item.treatyName ?? ''),
      number:         '',   // defaults to name on the backend
      effective_from: newFrom,
      effective_to:   defaultNewTo(newFrom),
      force:          false,
    })
    setRolloverResult(null)
    setRolloverError(null)
  }

  function closeRollover() {
    setRolloverSource(null)
    setRolloverResult(null)
    setRolloverError(null)
  }

  async function submitRollover(dry: boolean) {
    if (!rolloverSource) return
    setRolloverError(null)
    try {
      const res = await rolloverMutation.mutateAsync({
        id: rolloverSource.id,
        payload: {
          name:           rolloverForm.name || undefined,
          number:         rolloverForm.number || undefined,
          effective_from: rolloverForm.effective_from || undefined,
          effective_to:   rolloverForm.effective_to || undefined,
          dry_run:        dry,
          force:          rolloverForm.force,
        },
      })
      setRolloverResult(res.data)
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string } } }
      setRolloverError(e?.response?.data?.message ?? 'Rollover failed.')
    }
  }

  const items = data?.data ?? []

  function setField(key: keyof TreatyFormState, value: string | number | number[]) {
    setForm(f => ({ ...f, [key]: value }))
  }

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-800">Reinsurance Treaties</h1>
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
            placeholder="Treaty name, number..."
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
                <th className="px-4 py-3 text-left">Treaty Name</th>
                <th className="px-4 py-3 text-left">Treaty Number</th>
                <th className="px-4 py-3 text-left">Effective From</th>
                <th className="px-4 py-3 text-left">Effective To</th>
                <th className="px-4 py-3 text-left">Status</th>
                <th className="px-4 py-3 text-left">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {isLoading ? (
                <tr><td colSpan={7} className="px-4 py-12 text-center"><LoadingSpinner size="md" /></td></tr>
              ) : items.length === 0 ? (
                <tr><td colSpan={7} className="p-0"><EmptyState compact title="No treaties found" description="Try adjusting your filters." /></td></tr>
              ) : (
                items.map(item => {
                  // An active treaty without provisional_commission / proportional_share
                  // is a red flag for audit (NBFIRA / Munich Re would ask what the
                  // terms are). These fields don't drive the calc engine today —
                  // they're audit metadata. But an Active treaty with all zeros
                  // is still unauditable. Flag it.
                  const missingAudit = item.status === 1 && (
                    item.provisionalCommission == null || Number(item.provisionalCommission) === 0 ||
                    item.proportionalShare == null     || Number(item.proportionalShare) === 0
                  )
                  return (
                  <tr key={item.id} className={`hover:bg-gray-50 transition ${missingAudit ? 'bg-amber-50/40' : ''}`}>
                    <td className="px-4 py-2 font-medium text-gray-700">{item.id}</td>
                    <td className="px-4 py-2 font-medium text-blue-600">
                      {item.treatyName || '\u2014'}
                      {missingAudit && (
                        <span className="ml-2 px-1.5 py-0.5 rounded-full text-[10px] font-medium bg-amber-100 text-amber-800 border border-amber-200"
                              title="Active treaty is missing one or more audit fields (provisional commission / proportional share). Click Edit to fill them in. These fields are stored for regulatory audit — they do not drive the allocation engine, which reads reinsurance_formula_details instead.">
                          ⚠ needs audit fields
                        </span>
                      )}
                    </td>
                    <td className="px-4 py-2">{item.treatyNumber || '\u2014'}</td>
                    <td className="px-4 py-2 text-gray-600">{formatDate(item.effectiveFrom)}</td>
                    <td className="px-4 py-2 text-gray-600">{formatDate(item.effectiveTo)}</td>
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
                          onClick={() => openRollover(item)}
                          className="text-emerald-600 hover:text-emerald-800 p-1 rounded hover:bg-emerald-50"
                          title="Rollover — clone this treaty to next period"
                        >
                          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                        </button>
                        <button
                          onClick={() => handleDelete(item.id, item.treatyName ?? String(item.id))}
                          className="text-red-400 hover:text-red-600 p-1 rounded hover:bg-red-50"
                          title="Delete"
                        >
                          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </button>
                      </div>
                    </td>
                  </tr>
                )})
              )}
            </tbody>
          </table>
        </DualScrollTable>

        {/* Footer note — explains that these fields are audit metadata */}
        <div className="border-t bg-blue-50 px-4 py-2.5 text-xs text-blue-800 flex items-start gap-2">
          <span>ℹ</span>
          <span>
            <strong>Audit fields vs calculation engine:</strong>{' '}
            <em>provisional_commission</em>, <em>proportional_share</em>, <em>cash_loss_advise</em>, <em>event_limit</em>, and <em>exclusions</em> are stored for
            regulatory audit trail. They do <strong>not</strong> drive the allocation engine — actual split percentages come from
            <code className="mx-1 px-1 bg-blue-100 rounded">reinsurance_formula_details</code> keyed by group + SI threshold.
            Active treaties with missing audit fields are flagged above.
          </span>
        </div>

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
              <h2 className="text-lg font-semibold">{editId ? 'Edit' : 'Add New'} Treaty</h2>
              <button onClick={closeForm} className="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
            </div>
            <form onSubmit={handleSubmit} className="p-5 space-y-3">
              {apiError && (
                <div className="p-3 bg-red-50 border border-red-200 rounded text-sm text-red-700">{apiError}</div>
              )}
              <Field label="Treaty Name" required error={errors.treaty_name}>
                <input
                  type="text"
                  value={form.treaty_name}
                  onChange={e => setField('treaty_name', e.target.value)}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="e.g. Motor Quota Share 2026"
                />
              </Field>
              <div className="grid grid-cols-2 gap-4">
                <Field label="Treaty Number" required error={errors.treaty_number}>
                  <input
                    type="text"
                    value={form.treaty_number}
                    onChange={e => setField('treaty_number', e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="e.g. TRY-2026-001"
                  />
                </Field>
                <Field label="Formula Attached" error={errors.formula_attached}>
                  <div className="space-y-2">
                    {/* Display selected formulas as removable chips */}
                    {form.formula_attached && form.formula_attached.length > 0 && (
                      <div className="flex flex-wrap gap-2 p-2 bg-gray-50 border border-gray-300 rounded-md min-h-[2.5rem]">
                        {form.formula_attached.map(id => {
                          const numId = Number(id)
                          const formula = (formulasData?.data ?? []).find((f: any) => Number(f.id) === numId)
                          return (
                            <span
                              key={id}
                              className="inline-flex items-center gap-1 px-3 py-1 bg-blue-100 text-blue-800 text-sm rounded-full border border-blue-300"
                            >
                              {formula?.formulaName || formula?.formulaCode || `Formula ${id}`}
                              <button
                                type="button"
                                onClick={() => {
                                  const updated = form.formula_attached.filter(fid => fid !== id)
                                  setField('formula_attached', updated)
                                }}
                                className="ml-1 hover:text-blue-600 font-bold"
                              >
                                ×
                              </button>
                            </span>
                          )
                        })}
                      </div>
                    )}
                    {/* Dropdown to add new formulas */}
                    <select
                      value=""
                      onChange={e => {
                        if (e.target.value) {
                          const id = parseInt(e.target.value, 10)
                          if (!form.formula_attached.includes(id)) {
                            setField('formula_attached', [...form.formula_attached, id])
                          }
                          e.target.value = ''
                        }
                      }}
                      className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 bg-white"
                    >
                      <option value="">+ Add Formula</option>
                      {(formulasData?.data ?? []).map((formula: any) => (
                        <option
                          key={formula.id}
                          value={formula.id}
                          disabled={form.formula_attached.includes(formula.id)}
                        >
                          {formula.formulaName || formula.formulaCode || `Formula ${formula.id}`}
                        </option>
                      ))}
                    </select>
                  </div>
                </Field>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <Field label="Effective From" required error={errors.effective_from}>
                  <input
                    type="date"
                    value={form.effective_from}
                    onChange={e => setField('effective_from', e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                  />
                </Field>
                <Field label="Effective To" required error={errors.effective_to}>
                  <input
                    type="date"
                    value={form.effective_to}
                    onChange={e => setField('effective_to', e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                  />
                </Field>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <Field label="Provisional Commission (%)" required error={errors.provisional_commission}>
                  <input
                    type="number"
                    step="0.01"
                    min="0"
                    max="100"
                    value={form.provisional_commission}
                    onChange={e => setField('provisional_commission', e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="e.g. 25"
                  />
                </Field>
                <Field label="Proportional Share (%)" required error={errors.proportional_share}>
                  <input
                    type="number"
                    step="0.01"
                    min="0"
                    max="100"
                    value={form.proportional_share}
                    onChange={e => setField('proportional_share', e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="e.g. 50"
                  />
                </Field>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <Field label="Cash Loss Advise" required error={errors.cash_loss_advise}>
                  <input
                    type="number"
                    step="0.01"
                    min="0"
                    value={form.cash_loss_advise}
                    onChange={e => setField('cash_loss_advise', e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="e.g. 50000"
                  />
                </Field>
                <Field label="Event Limit" required error={errors.event_limit}>
                  <input
                    type="number"
                    step="0.01"
                    min="0"
                    value={form.event_limit}
                    onChange={e => setField('event_limit', e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="e.g. 500000"
                  />
                </Field>
              </div>
              <Field label="Exclusions" error={errors.exclusions}>
                <textarea
                  value={form.exclusions}
                  onChange={e => setField('exclusions', e.target.value)}
                  rows={4}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="List any exclusions applicable to this treaty..."
                />
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

      {/* ─── Rollover modal ───────────────────────────────────────────── */}
      {rolloverSource && (
        <div className="fixed inset-0 z-50 flex items-start justify-center p-4 pt-10">
          <div className="fixed inset-0 bg-black/40" onClick={closeRollover} />
          <div className="relative w-full max-w-2xl bg-white rounded-lg shadow-xl max-h-[85vh] overflow-hidden flex flex-col">
            <div className="px-5 py-3 border-b flex items-center justify-between flex-shrink-0">
              <div>
                <h2 className="text-lg font-semibold">Rollover Treaty</h2>
                <p className="text-xs text-gray-500 mt-0.5">
                  Clones <strong>#{rolloverSource.id} — {rolloverSource.treatyName}</strong> and
                  all its treaty_details into a new period. Source treaty is untouched.
                </p>
              </div>
              <button onClick={closeRollover} className="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
            </div>

            <div className="flex-1 overflow-y-auto px-6 py-4 space-y-4">
              {/* Source snapshot */}
              <div className="rounded border border-gray-200 bg-gray-50 px-3 py-2 text-xs">
                <div className="grid grid-cols-3 gap-3">
                  <div>
                    <div className="text-gray-500">Source name</div>
                    <div className="font-medium text-gray-800">{rolloverSource.treatyName || '—'}</div>
                  </div>
                  <div>
                    <div className="text-gray-500">Effective from</div>
                    <div className="font-medium text-gray-800">{formatDate(rolloverSource.effectiveFrom)}</div>
                  </div>
                  <div>
                    <div className="text-gray-500">Effective to</div>
                    <div className="font-medium text-gray-800">{formatDate(rolloverSource.effectiveTo)}</div>
                  </div>
                </div>
              </div>

              {/* New-treaty fields */}
              <div className="grid grid-cols-2 gap-4">
                <Field label="New Treaty Name">
                  <input
                    type="text"
                    value={rolloverForm.name}
                    onChange={e => setRolloverForm(f => ({ ...f, name: e.target.value }))}
                    placeholder="auto — bump year in source name"
                    className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                  />
                </Field>
                <Field label="New Treaty Number">
                  <input
                    type="text"
                    value={rolloverForm.number}
                    onChange={e => setRolloverForm(f => ({ ...f, number: e.target.value }))}
                    placeholder="default: same as name"
                    className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                  />
                </Field>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <Field label="New Effective From">
                  <input
                    type="date"
                    value={rolloverForm.effective_from}
                    onChange={e => {
                      const v = e.target.value
                      setRolloverForm(f => ({
                        ...f,
                        effective_from: v,
                        // auto-suggest new to = +1y -1d whenever from changes,
                        // but only if the user hasn't overridden it themselves
                        effective_to: f.effective_to && f.effective_to !== defaultNewTo(f.effective_from)
                          ? f.effective_to
                          : defaultNewTo(v),
                      }))
                    }}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                  />
                </Field>
                <Field label="New Effective To">
                  <input
                    type="date"
                    value={rolloverForm.effective_to}
                    onChange={e => setRolloverForm(f => ({ ...f, effective_to: e.target.value }))}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                  />
                </Field>
              </div>

              <label className="inline-flex items-center gap-2 cursor-pointer text-sm">
                <input
                  type="checkbox"
                  checked={rolloverForm.force}
                  onChange={e => setRolloverForm(f => ({ ...f, force: e.target.checked }))}
                  className="w-4 h-4 text-red-600 border-gray-300 rounded"
                />
                <span className="text-gray-700">Force — override duplicate-name / overlap checks</span>
              </label>

              {rolloverError && (
                <div className="p-3 bg-red-50 border border-red-200 rounded text-sm text-red-700">
                  {rolloverError}
                </div>
              )}

              {/* Dry-run / result log */}
              {rolloverResult && (
                <div className={`rounded border px-3 py-2 text-xs ${rolloverResult.dry_run ? 'bg-amber-50 border-amber-200' : 'bg-green-50 border-green-200'}`}>
                  <div className={`font-semibold mb-1 ${rolloverResult.dry_run ? 'text-amber-800' : 'text-green-800'}`}>
                    {rolloverResult.dry_run
                      ? 'Dry-run preview (nothing committed)'
                      : `Committed — new treaty id #${rolloverResult.new_treaty_id}`}
                  </div>
                  <pre className="whitespace-pre-wrap font-mono text-[11px] text-gray-700 leading-5">
{rolloverResult.log.join('\n')}
                  </pre>
                </div>
              )}

              <div className="text-[11px] text-gray-500 leading-5 border-t pt-3">
                <strong>What this does:</strong> clones the source treaty header row + every matching
                <code className="mx-1 px-1 bg-gray-100 rounded">reinsurance_treaty_details</code> row into a new
                treaty with status=Active. The source treaty is left alone — retire it manually
                afterwards (set status=Inactive) once reinsurers confirm the new period is live.
                Run Dry-run first to see what will be cloned.
              </div>
            </div>

            <div className="border-t px-6 py-3 flex items-center justify-between flex-shrink-0 bg-gray-50">
              <button
                type="button"
                onClick={closeRollover}
                className="px-4 py-2 border border-gray-300 text-sm rounded-md hover:bg-gray-100 transition"
              >
                Close
              </button>
              <div className="flex gap-2">
                <button
                  type="button"
                  disabled={rolloverMutation.isPending}
                  onClick={() => submitRollover(true)}
                  className="px-4 py-2 border border-amber-300 bg-amber-50 text-amber-800 text-sm font-medium rounded-md hover:bg-amber-100 disabled:opacity-50 transition"
                >
                  {rolloverMutation.isPending && rolloverMutation.variables?.payload.dry_run ? 'Running...' : 'Dry-run'}
                </button>
                <button
                  type="button"
                  disabled={rolloverMutation.isPending}
                  onClick={() => {
                    if (!window.confirm('Commit the rollover? This will create a new treaty and copy all treaty_details rows. Run Dry-run first if you are unsure.')) return
                    submitRollover(false)
                  }}
                  className="px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-md hover:bg-emerald-700 disabled:opacity-50 transition"
                >
                  {rolloverMutation.isPending && !rolloverMutation.variables?.payload.dry_run ? 'Rolling over...' : 'Rollover Now'}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
