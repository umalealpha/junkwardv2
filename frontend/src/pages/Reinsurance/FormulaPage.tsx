import DualScrollTable from '../../components/common/DualScrollTable'
import { useRef, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import {
  useReinsuranceFormulas,
  useCreateReinsuranceFormula,
  useUpdateReinsuranceFormula,
  useDeleteReinsuranceFormula,
  useReinsuranceFormLookups,
} from '../../hooks/useReinsurance'
import { fetchReinsuranceFormula } from '../../api/reinsurance'
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
  formula_name:        '',
  formula_code:        '',
  product_id:          '',
  reinsurance_type_id: '',
  type_id:             '',
  s_FormulaType:       '',
  status:              1,
  group_name:          '',
  operator:            '',
  vehicle_type:        '',
  si_allocation:       '',
  percentage:          '',
  datefrom:            '',
  dateto:              '',
}

type FormulaFormState = typeof EMPTY_FORM

export default function FormulaPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [jumpPage, setJumpPage] = useState('')
  const [showForm, setShowForm] = useState(false)
  const [editId, setEditId] = useState<number | null>(null)
  const [form, setForm] = useState<FormulaFormState>({ ...EMPTY_FORM })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [apiError, setApiError] = useState<string | null>(null)
  const dateFromRef = useRef<HTMLInputElement>(null)
  const dateToRef = useRef<HTMLInputElement>(null)

  const filters = {
    search:   searchParams.get('search') || undefined,
    per_page: 25,
    page:     Number(searchParams.get('page') || '1'),
  }

  const { data, isLoading, isFetching } = useReinsuranceFormulas(filters)
  const { data: lookups } = useReinsuranceFormLookups()
  const createMutation = useCreateReinsuranceFormula()
  const updateMutation = useUpdateReinsuranceFormula()
  const deleteMutation = useDeleteReinsuranceFormula()

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
    setErrors({})
    setApiError(null)
    setShowForm(true)
  }

  async function openEdit(item: any) {
    if (!item) return
    setEditId(item.id)
    setErrors({})
    setApiError(null)
    setShowForm(true)

    try {
      // Fetch full formula details including joined detail fields
      const fullFormula = await fetchReinsuranceFormula(item.id)
      console.log('Fetched formula:', fullFormula) // Debug log
      setForm({
        formula_name:        fullFormula.formulaName ?? '',
        formula_code:        fullFormula.formulaCode ?? '',
        product_id:          fullFormula.productId ? String(fullFormula.productId) : '',
        reinsurance_type_id: fullFormula.reinsuranceTypeId ? String(fullFormula.reinsuranceTypeId) : '',
        type_id:             fullFormula.typeId ? String(fullFormula.typeId) : '',
        s_FormulaType:       fullFormula.sFormulaType ?? '',
        status:              fullFormula.status ?? 1,
        group_name:          fullFormula.groupId ? String(fullFormula.groupId) : '',
        operator:            String(fullFormula.operator ?? ''),
        vehicle_type:        fullFormula.vehicleType ?? '',
        si_allocation:       fullFormula.siAllocation ?? '',
        percentage:          fullFormula.percentage ?? '',
        datefrom:            fullFormula.dateFrom ?? '',
        dateto:              fullFormula.dateTo ?? '',
      })
    } catch (error) {
      console.error('Error loading formula:', error)
      setApiError('Failed to load formula details')
      setShowForm(false)
    }
  }

  function closeForm() {
    setShowForm(false)
    setEditId(null)
    setApiError(null)
  }

  function setField(key: keyof FormulaFormState, value: string | number) {
    setForm(f => ({ ...f, [key]: value }))
  }

  function validate() {
    const errs: Record<string, string> = {}
    if (!form.formula_name.trim()) errs.formula_name = 'Formula name is required.'
    if (!form.formula_code.trim()) errs.formula_code = 'Formula code is required.'
    // Saved empty, the cession calc stops splitting motor classes per vehicle and
    // silently cedes only the first vehicle on a multi-vehicle policy.
    if (!form.type_id) errs.type_id = 'Formula category is required — Motor cedes per vehicle, Non-Motor aggregates per class.'
    // A native <input type="date"> clears an out-of-range value (e.g. 31 June) and
    // flags validity.badInput, so an invalid calendar date reaches us as empty. Tell
    // the user exactly what's wrong instead of the browser's generic message.
    if (dateFromRef.current?.validity.badInput) {
      errs.datefrom = 'From Date is not a valid date — check the day exists for that month.'
    }
    if (dateToRef.current?.validity.badInput) {
      errs.dateto = 'To Date is not a valid date — check the day exists for that month (e.g. June has 30 days, not 31).'
    } else if (form.datefrom && form.dateto && form.dateto < form.datefrom) {
      errs.dateto = 'To Date must be on or after From Date.'
    }
    setErrors(errs)
    return Object.keys(errs).length === 0
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!validate()) return
    setApiError(null)
    const payload: Record<string, unknown> = {
      formula_name:        form.formula_name,
      formula_code:        form.formula_code,
      product_id:          form.product_id ? Number(form.product_id) : null,
      reinsurance_type_id: form.reinsurance_type_id ? Number(form.reinsurance_type_id) : null,
      type_id:             form.type_id ? Number(form.type_id) : null,
      s_FormulaType:       form.s_FormulaType || null,
      status:              form.status,
      group_name:          form.group_name ? Number(form.group_name) : null,
      operator:            form.operator ? Number(form.operator) : null,
      vehicle_type:        form.vehicle_type || null,
      si_allocation:       form.si_allocation || null,
      percentage:          form.percentage || null,
      datefrom:            form.datefrom || null,
      dateto:              form.dateto || null,
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
    if (!window.confirm(`Delete formula "${name}"? This cannot be undone.`)) return
    try {
      await deleteMutation.mutateAsync(id)
    } catch {
      alert('Failed to delete. The record may be in use.')
    }
  }

  const items = data?.data ?? []
  const products = lookups?.products ?? []
  const types = lookups?.types ?? []
  const groups = lookups?.groups ?? []
  const formulaTypes = lookups?.formulaTypes ?? []
  const formulaKeys = lookups?.formulaKeys ?? []

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-800">Reinsurance Formulas</h1>
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
            placeholder="Formula code, name..."
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
                <th className="px-4 py-3 text-left">Code</th>
                <th className="px-4 py-3 text-left">Name</th>
                <th className="px-4 py-3 text-left">Product ID</th>
                <th className="px-4 py-3 text-left">RI Type</th>
                <th className="px-4 py-3 text-left">Status</th>
                <th className="px-4 py-3 text-left">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {isLoading ? (
                <tr><td colSpan={7} className="px-4 py-12 text-center"><LoadingSpinner size="md" /></td></tr>
              ) : items.length === 0 ? (
                <tr><td colSpan={7} className="p-0"><EmptyState compact title="No reinsurance formulas found" description="No reinsurance formulas have been configured yet." /></td></tr>
              ) : (
                items.map(item => (
                  <tr key={item.id} className="hover:bg-gray-50 transition">
                    <td className="px-4 py-2 font-medium text-gray-700">{item.id}</td>
                    <td className="px-4 py-2 font-medium text-blue-600">{item.formulaCode || '\u2014'}</td>
                    <td className="px-4 py-2">{item.formulaName || '\u2014'}</td>
                    <td className="px-4 py-2">{item.productId ?? '\u2014'}</td>
                    <td className="px-4 py-2">{item.reinsuranceType || '\u2014'}</td>
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
                          onClick={() => handleDelete(item.id, item.formulaName)}
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
              <h2 className="text-lg font-semibold">{editId ? 'Edit' : 'Add New'} Formula</h2>
              <button onClick={closeForm} className="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
            </div>
            <form onSubmit={handleSubmit} noValidate className="p-5 space-y-3">
              {apiError && (
                <div className="p-3 bg-red-50 border border-red-200 rounded text-sm text-red-700">{apiError}</div>
              )}
              <Field label="Formula Name" required error={errors.formula_name}>
                <input
                  type="text"
                  value={form.formula_name}
                  onChange={e => setField('formula_name', e.target.value)}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="e.g. Motor Quota Share Formula"
                />
              </Field>
              <Field label="Formula Code" required error={errors.formula_code}>
                <input
                  type="text"
                  value={form.formula_code}
                  onChange={e => setField('formula_code', e.target.value)}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="e.g. FRM-MOTOR-QS"
                />
              </Field>
              <Field label="Product" error={errors.product_id}>
                <select
                  value={form.product_id}
                  onChange={e => setField('product_id', e.target.value)}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 bg-white"
                >
                  <option value="">-- Select Product --</option>
                  {products.map(p => (
                    <option key={p.id} value={p.id}>{p.name}</option>
                  ))}
                </select>
              </Field>
              <Field label="Reinsurance Type" error={errors.reinsurance_type_id}>
                <select
                  value={form.reinsurance_type_id}
                  onChange={e => setField('reinsurance_type_id', e.target.value)}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 bg-white"
                >
                  <option value="">-- Select RI Type --</option>
                  {types.map(t => (
                    <option key={t.id} value={t.id}>{t.type_name}</option>
                  ))}
                </select>
              </Field>
              <Field label="Formula Type" error={errors.s_FormulaType}>
                {formulaTypes.length > 0 ? (
                  <select
                    value={form.s_FormulaType}
                    onChange={e => setField('s_FormulaType', e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 bg-white"
                  >
                    <option value="">-- Select Formula Type --</option>
                    {formulaTypes.map(ft => (
                      <option key={ft.id} value={ft.name}>{ft.name}</option>
                    ))}
                  </select>
                ) : (
                  <input
                    type="text"
                    value={form.s_FormulaType}
                    onChange={e => setField('s_FormulaType', e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="e.g. Proportional"
                  />
                )}
              </Field>
              <Field label="Formula Category" error={errors.type_id}>
                <select
                  value={form.type_id}
                  onChange={e => setField('type_id', e.target.value)}
                  className="w-full px-3 py-2 border border-line rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 bg-surface"
                >
                  <option value="">-- Select Category --</option>
                  {formulaKeys.map(k => (
                    <option key={k.id} value={k.id}>{k.name}</option>
                  ))}
                </select>
                <p className="mt-1 text-xs text-ink-muted">
                  Motor cedes one row per vehicle. Non-Motor aggregates the class into a single cession.
                </p>
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

              <hr className="my-4" />
              <h3 className="text-sm font-semibold text-gray-700 mb-3">Formula Details</h3>

              <Field label="Reinsurance Group" error={errors.group_name}>
                <select
                  value={form.group_name}
                  onChange={e => setField('group_name', e.target.value)}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 bg-white"
                >
                  <option value="">-- Select Group --</option>
                  {groups.map(g => (
                    <option key={g.id} value={g.id}>{g.group_name}</option>
                  ))}
                </select>
              </Field>

              {form.type_id === '35' && (
                <Field label="Motor Type" error={errors.vehicle_type}>
                  <input
                    type="text"
                    value={form.vehicle_type}
                    onChange={e => setField('vehicle_type', e.target.value)}
                    placeholder="e.g. Motor Vehicle"
                    className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                  />
                </Field>
              )}

              <Field label="Operator" error={errors.operator}>
                <select
                  value={form.operator}
                  onChange={e => setField('operator', e.target.value)}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 bg-white"
                >
                  {/* Values are the integer codes the RI calc maps in
                      PolicyCoverage::MotorComTradersReinsurance and siblings.
                      Sending the symbol itself fails — the column is int(11). */}
                  <option value="">-- Select Operator --</option>
                  <option value="1">=</option>
                  <option value="2">&lt;</option>
                  <option value="3">&lt;=</option>
                  <option value="4">&gt;</option>
                  <option value="5">&gt;=</option>
                  <option value="6">&lt;&gt;</option>
                  <option value="7">between</option>
                  <option value="8">not between</option>
                  <option value="9">* (multiply)</option>
                </select>
              </Field>

              <Field label="SI Allocation" error={errors.si_allocation}>
                <input
                  type="text"
                  value={form.si_allocation}
                  onChange={e => setField('si_allocation', e.target.value)}
                  placeholder="e.g. 300000"
                  className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                />
              </Field>

              <Field label="Percentage" error={errors.percentage}>
                <input
                  type="text"
                  value={form.percentage}
                  onChange={e => setField('percentage', e.target.value)}
                  placeholder="e.g. 10"
                  className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                />
              </Field>

              <div className="grid grid-cols-2 gap-3">
                <Field label="From Date" error={errors.datefrom}>
                  <input
                    ref={dateFromRef}
                    type="date"
                    value={form.datefrom}
                    onChange={e => setField('datefrom', e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                  />
                </Field>
                <Field label="To Date" error={errors.dateto}>
                  <input
                    ref={dateToRef}
                    type="date"
                    value={form.dateto}
                    onChange={e => setField('dateto', e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                  />
                </Field>
              </div>

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
