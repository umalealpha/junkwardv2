import { useState, useCallback, useRef } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { useBlockList } from '../../hooks/useBlockList'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import StatusBadge from '../../components/common/StatusBadge'
import Modal from '../../components/common/Modal'
import {
  fetchBlockListDetail,
  addToBlockList,
  unblockCustomer,
  updateBlockReason,
  addAlias,
  removeAlias,
  runAmlCheck,
  type BlockListItem,
  type BlockListDetail,
  type AmlCheckResult,
} from '../../api/blockList'
import { fmtDate } from '../../utils/format'
import EmptyState from '../../components/common/EmptyState'

/* Empty "Add to Block List" form — mirrors the graphiteBWV8 fields. */
const EMPTY_BLOCK_FORM = {
  first_name: '', middle_name: '', last_name: '',
  email: '', omang: '', passport: '', mobile: '', block_reason: '',
}

/* ─── Helpers ─── */

function fullName(item: { firstName: string; lastName: string }) {
  return [item.firstName, item.lastName].filter(Boolean).join(' ') || '\u2014'
}

function formatDate(d: string | null | undefined) {
  return fmtDate(d)
}

function amlVariant(status: string | null | undefined): string {
  if (status === 'flagged') return 'rejected'
  if (status === 'clear') return 'active'
  return 'default'
}

function amlLabel(status: string | null | undefined): string {
  if (status === 'flagged') return 'AML FLAGGED'
  if (status === 'clear') return 'AML CLEAR'
  return 'NOT CHECKED'
}

/* ─── Toast component ─── */

interface Toast { id: number; message: string; type: 'success' | 'error' | 'info' }

function ToastContainer({ toasts }: { toasts: Toast[] }) {
  return (
    <div className="fixed top-4 right-4 z-[100] space-y-2">
      {toasts.map(t => (
        <div
          key={t.id}
          className={`px-4 py-3 rounded-lg shadow-lg text-sm text-white transition-all ${
            t.type === 'success' ? 'bg-green-600' : t.type === 'error' ? 'bg-red-600' : 'bg-blue-600'
          }`}
        >
          {t.message}
        </div>
      ))}
    </div>
  )
}

/* ─── Main Page ─── */

export default function CustomerBlockListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [jumpPage, setJumpPage] = useState('')
  const queryClient = useQueryClient()

  // Toast state
  const toastId = useRef(0)
  const [toasts, setToasts] = useState<Toast[]>([])
  const addToast = useCallback((message: string, type: Toast['type'] = 'info') => {
    const id = ++toastId.current
    setToasts(prev => [...prev, { id, message, type }])
    setTimeout(() => setToasts(prev => prev.filter(t => t.id !== id)), 4000)
  }, [])

  // Modal state
  const [detailModalOpen, setDetailModalOpen] = useState(false)
  const [detailLoading, setDetailLoading] = useState(false)
  const [detail, setDetail] = useState<BlockListDetail | null>(null)
  const [editingReason, setEditingReason] = useState(false)
  const [editReasonValue, setEditReasonValue] = useState('')
  const [newAliasValue, setNewAliasValue] = useState('')
  const [savingReason, setSavingReason] = useState(false)
  const [addingAlias, setAddingAlias] = useState(false)

  const [blockModalOpen, setBlockModalOpen] = useState(false)
  const [blockForm, setBlockForm] = useState({ ...EMPTY_BLOCK_FORM })
  // Aliases captured on the create form — mirrors the detail modal's add/remove
  // list, held locally (the customer doesn't exist yet) and sent as an array.
  const [blockAliases, setBlockAliases] = useState<string[]>([])
  const [newBlockAlias, setNewBlockAlias] = useState('')
  const [blocking, setBlocking] = useState(false)
  const setBlockField = (k: keyof typeof EMPTY_BLOCK_FORM, v: string) =>
    setBlockForm(prev => ({ ...prev, [k]: v }))
  const blockFormValid =
    !!blockForm.first_name.trim() && !!blockForm.last_name.trim() && !!blockForm.block_reason.trim()

  function addBlockAlias() {
    const v = newBlockAlias.trim()
    if (!v) return
    setBlockAliases(prev => (prev.includes(v) ? prev : [...prev, v]))
    setNewBlockAlias('')
  }

  const [confirmUnblock, setConfirmUnblock] = useState<BlockListItem | null>(null)
  const [unblocking, setUnblocking] = useState(false)

  // Inline edit state (table row)
  const [inlineEditId, setInlineEditId] = useState<number | null>(null)
  const [inlineEditValue, setInlineEditValue] = useState('')
  const [inlineSaving, setInlineSaving] = useState(false)

  // Bulk AML
  const [bulkScanning, setBulkScanning] = useState(false)

  // Filters / query
  const filters = {
    search: searchParams.get('search') || undefined,
    per_page: 25,
    page: Number(searchParams.get('page') || '1'),
  }

  const { data, isLoading, isFetching } = useBlockList(filters)

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

  function invalidateList() {
    queryClient.invalidateQueries({ queryKey: ['block-list'] })
  }

  /* ─── Detail modal ─── */

  async function openDetail(item: BlockListItem) {
    setDetailModalOpen(true)
    setDetailLoading(true)
    setEditingReason(false)
    setNewAliasValue('')
    try {
      const d = await fetchBlockListDetail(item.id)
      setDetail(d)
      setEditReasonValue(d.blockReason || '')
    } catch {
      addToast('Failed to load customer details', 'error')
      setDetailModalOpen(false)
    } finally {
      setDetailLoading(false)
    }
  }

  async function saveReason() {
    if (!detail) return
    setSavingReason(true)
    try {
      await updateBlockReason(detail.id, editReasonValue)
      setDetail({ ...detail, blockReason: editReasonValue })
      setEditingReason(false)
      invalidateList()
      addToast('Block reason updated', 'success')
    } catch {
      addToast('Failed to update block reason', 'error')
    } finally {
      setSavingReason(false)
    }
  }

  async function handleAddAlias() {
    if (!detail || !newAliasValue.trim()) return
    setAddingAlias(true)
    try {
      const alias = await addAlias(detail.id, newAliasValue.trim())
      setDetail({ ...detail, aliases: [...detail.aliases, alias] })
      setNewAliasValue('')
      addToast('Alias added', 'success')
    } catch {
      addToast('Failed to add alias', 'error')
    } finally {
      setAddingAlias(false)
    }
  }

  async function handleRemoveAlias(aliasId: number) {
    if (!detail) return
    try {
      await removeAlias(aliasId)
      setDetail({ ...detail, aliases: detail.aliases.filter(a => a.id !== aliasId) })
      addToast('Alias removed', 'success')
    } catch {
      addToast('Failed to remove alias', 'error')
    }
  }

  async function handleDetailAmlCheck() {
    if (!detail) return
    try {
      const result = await runAmlCheck(detail.id)
      setDetail({ ...detail, amlStatus: result.status })
      invalidateList()
      addToast(
        result.status === 'flagged'
          ? `AML FLAGGED - ${result.matches} match(es) found`
          : 'AML check clear - no matches',
        result.status === 'flagged' ? 'error' : 'success'
      )
    } catch {
      addToast('AML check failed', 'error')
    }
  }

  /* ─── Row-level AML check ─── */

  async function handleRowAmlCheck(item: BlockListItem) {
    try {
      const result: AmlCheckResult = await runAmlCheck(item.id)
      invalidateList()
      addToast(
        result.status === 'flagged'
          ? `${fullName(item)}: AML FLAGGED (${result.matches} match${result.matches !== 1 ? 'es' : ''})`
          : `${fullName(item)}: AML CLEAR`,
        result.status === 'flagged' ? 'error' : 'success'
      )
    } catch {
      addToast(`AML check failed for ${fullName(item)}`, 'error')
    }
  }

  /* ─── Inline edit block reason ─── */

  function startInlineEdit(item: BlockListItem) {
    setInlineEditId(item.id)
    setInlineEditValue(item.blockReason || '')
  }

  async function saveInlineEdit(id: number) {
    setInlineSaving(true)
    try {
      await updateBlockReason(id, inlineEditValue)
      invalidateList()
      setInlineEditId(null)
      addToast('Block reason updated', 'success')
    } catch {
      addToast('Failed to update block reason', 'error')
    } finally {
      setInlineSaving(false)
    }
  }

  /* ─── Block customer ─── */

  async function handleBlock() {
    if (!blockFormValid) return
    setBlocking(true)
    try {
      await addToBlockList({
        first_name: blockForm.first_name.trim(),
        middle_name: blockForm.middle_name.trim() || undefined,
        last_name: blockForm.last_name.trim(),
        email: blockForm.email.trim() || undefined,
        omang: blockForm.omang.trim() || undefined,
        passport: blockForm.passport.trim() || undefined,
        mobile: blockForm.mobile.trim() || undefined,
        block_reason: blockForm.block_reason.trim(),
        aliases: blockAliases.length ? blockAliases : undefined,
      })
      invalidateList()
      setBlockModalOpen(false)
      setBlockForm({ ...EMPTY_BLOCK_FORM })
      addToast('Customer added to block list', 'success')
    } catch (e: any) {
      addToast(e?.response?.data?.message || 'Failed to add customer to block list', 'error')
    } finally {
      setBlocking(false)
    }
  }

  /* ─── Unblock customer ─── */

  async function handleUnblock() {
    if (!confirmUnblock) return
    setUnblocking(true)
    try {
      await unblockCustomer(confirmUnblock.id)
      invalidateList()
      setConfirmUnblock(null)
      addToast('Customer unblocked', 'success')
    } catch {
      addToast('Failed to unblock customer', 'error')
    } finally {
      setUnblocking(false)
    }
  }

  /* ─── Bulk AML scan ─── */

  async function handleBulkAml() {
    if (!data?.data.length) return
    setBulkScanning(true)
    let flagged = 0
    let clear = 0
    let failed = 0
    for (const item of data.data) {
      try {
        const result = await runAmlCheck(item.id)
        if (result.status === 'flagged') flagged++
        else clear++
      } catch {
        failed++
      }
    }
    invalidateList()
    setBulkScanning(false)
    addToast(
      `Bulk AML scan complete: ${clear} clear, ${flagged} flagged${failed ? `, ${failed} failed` : ''}`,
      flagged > 0 ? 'error' : 'success'
    )
  }

  /* ─── Render ─── */

  return (
    <div className="p-6 space-y-4">
      <ToastContainer toasts={toasts} />

      {/* Header */}
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-800">Customer Block List</h1>
        <div className="flex items-center gap-2">
          <button
            onClick={handleBulkAml}
            disabled={bulkScanning || !data?.data.length}
            className="px-4 py-2 text-sm font-medium rounded-md border border-orange-300 text-orange-700 bg-orange-50 hover:bg-orange-100 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {bulkScanning ? 'Scanning...' : 'Bulk AML Scan'}
          </button>
          <button
            onClick={() => { setBlockAliases([]); setNewBlockAlias(''); setBlockModalOpen(true) }}
            className="px-4 py-2 text-sm font-medium rounded-md bg-red-600 text-white hover:bg-red-700"
          >
            Block Customer
          </button>
        </div>
      </div>

      {/* Search */}
      <div className="flex flex-wrap gap-3 items-end">
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Search</label>
          <input
            type="text"
            placeholder="Name, phone, email..."
            defaultValue={filters.search}
            onKeyDown={e => { if (e.key === 'Enter') updateFilter('search', (e.target as HTMLInputElement).value) }}
            onBlur={e => updateFilter('search', e.target.value)}
            className="px-3 py-1.5 border border-gray-300 rounded-md text-sm w-64 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
      </div>

      {/* Table */}
      <div className="bg-white rounded-lg shadow-sm border overflow-hidden">
        {(isLoading || isFetching) && (
          <div className="absolute inset-0 bg-white/50 z-10 flex items-center justify-center">
            <LoadingSpinner size="md" />
          </div>
        )}
        <div className="overflow-x-auto relative">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-gray-600 uppercase text-xs tracking-wider">
              <tr>
                <th className="px-4 py-3 text-left">Name</th>
                <th className="px-4 py-3 text-left">ID Number</th>
                <th className="px-4 py-3 text-left">Phone</th>
                <th className="px-4 py-3 text-left">Email</th>
                <th className="px-4 py-3 text-left">Block Reason</th>
                <th className="px-4 py-3 text-left">Date Blocked</th>
                <th className="px-4 py-3 text-left">AML</th>
                <th className="px-4 py-3 text-left">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {isLoading ? (
                <tr><td colSpan={8} className="px-4 py-12 text-center"><LoadingSpinner size="md" /></td></tr>
              ) : data?.data.length === 0 ? (
                <tr><td colSpan={8} className="p-0"><EmptyState compact title="No blocked customers found" description="Try adjusting your filters." /></td></tr>
              ) : (
                data?.data.map(item => (
                  <tr key={item.id} className="hover:bg-gray-50 transition">
                    <td className="px-4 py-2 font-medium text-blue-600">{fullName(item)}{item.aliasNames && <span className="text-ink-muted font-normal"> ({item.aliasNames})</span>}</td>
                    <td className="px-4 py-2 text-gray-700">{item.idNumber || '\u2014'}</td>
                    <td className="px-4 py-2">{item.cellphone || '\u2014'}</td>
                    <td className="px-4 py-2 truncate max-w-[200px]" title={item.email ?? ''}>{item.email || '\u2014'}</td>
                    <td className="px-4 py-2 max-w-[200px]">
                      {inlineEditId === item.id ? (
                        <div className="flex items-center gap-1">
                          <input
                            type="text"
                            value={inlineEditValue}
                            onChange={e => setInlineEditValue(e.target.value)}
                            onKeyDown={e => { if (e.key === 'Enter') saveInlineEdit(item.id); if (e.key === 'Escape') setInlineEditId(null) }}
                            className="px-2 py-1 border border-gray-300 rounded text-sm w-full focus:ring-1 focus:ring-blue-500"
                            autoFocus
                            disabled={inlineSaving}
                          />
                          <button onClick={() => saveInlineEdit(item.id)} disabled={inlineSaving} className="text-green-600 hover:text-green-800 text-xs font-medium whitespace-nowrap">
                            {inlineSaving ? '...' : 'Save'}
                          </button>
                          <button onClick={() => setInlineEditId(null)} className="text-gray-400 hover:text-gray-600 text-xs">Cancel</button>
                        </div>
                      ) : (
                        <span className="truncate block" title={item.blockReason ?? ''}>{item.blockReason || '\u2014'}</span>
                      )}
                    </td>
                    <td className="px-4 py-2 text-gray-500">{formatDate(item.blockedAt)}</td>
                    <td className="px-4 py-2">
                      <StatusBadge status={amlVariant(item.amlStatus)} label={amlLabel(item.amlStatus)} />
                    </td>
                    <td className="px-4 py-2">
                      <div className="flex items-center gap-2">
                        <button onClick={() => openDetail(item)} className="text-blue-600 hover:text-blue-800 text-xs font-medium">View</button>
                        <button onClick={() => startInlineEdit(item)} className="text-gray-600 hover:text-gray-800 text-xs font-medium">Edit</button>
                        <button onClick={() => setConfirmUnblock(item)} className="text-green-600 hover:text-green-800 text-xs font-medium">Unblock</button>
                        <button onClick={() => handleRowAmlCheck(item)} className="text-orange-600 hover:text-orange-800 text-xs font-medium">AML</button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
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
                <input
                  type="number" min={1} max={lastPage} value={jumpPage}
                  onChange={e => setJumpPage(e.target.value)}
                  onKeyDown={e => { if (e.key === 'Enter') { const p = Number(jumpPage); if (p >= 1 && p <= lastPage) { goToPage(p); setJumpPage('') } } }}
                  className="w-14 px-2 py-1 border rounded text-sm text-center" placeholder="#"
                />
              </div>
            </div>
          </div>
        )}
      </div>

      {/* ─── Detail Modal ─── */}
      <Modal
        open={detailModalOpen}
        onClose={() => { setDetailModalOpen(false); setDetail(null) }}
        title={detail ? fullName(detail) : 'Customer Details'}
        footer={
          detail ? (
            <>
              <button onClick={handleDetailAmlCheck} className="px-3 py-1.5 text-sm rounded-md border border-orange-300 text-orange-700 hover:bg-orange-50">Run AML Check</button>
              <button onClick={() => { setDetailModalOpen(false); setDetail(null) }} className="px-3 py-1.5 text-sm rounded-md bg-gray-100 text-gray-700 hover:bg-gray-200">Close</button>
            </>
          ) : undefined
        }
      >
        {detailLoading ? (
          <div className="py-8"><LoadingSpinner size="md" /></div>
        ) : detail ? (
          <div className="space-y-5">
            {/* Customer info */}
            <div className="grid grid-cols-2 gap-3 text-sm">
              <div>
                <span className="text-gray-500 block text-xs">Name</span>
                <span className="font-medium text-gray-800">{fullName(detail)}</span>
              </div>
              <div>
                <span className="text-gray-500 block text-xs">ID Number</span>
                <span className="font-medium text-gray-800">{detail.idNumber || '\u2014'}</span>
              </div>
              <div>
                <span className="text-gray-500 block text-xs">Phone</span>
                <span className="font-medium text-gray-800">{detail.cellphone || '\u2014'}</span>
              </div>
              <div>
                <span className="text-gray-500 block text-xs">Email</span>
                <span className="font-medium text-gray-800">{detail.email || '\u2014'}</span>
              </div>
            </div>

            {/* Block reason */}
            <div>
              <span className="text-gray-500 text-xs block mb-1">Block Reason</span>
              {editingReason ? (
                <div className="flex items-center gap-2">
                  <input
                    type="text"
                    value={editReasonValue}
                    onChange={e => setEditReasonValue(e.target.value)}
                    className="flex-1 px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500"
                    autoFocus
                  />
                  <button onClick={saveReason} disabled={savingReason} className="px-3 py-1.5 text-sm rounded-md bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50">
                    {savingReason ? 'Saving...' : 'Save'}
                  </button>
                  <button onClick={() => { setEditingReason(false); setEditReasonValue(detail.blockReason || '') }} className="px-3 py-1.5 text-sm rounded-md bg-gray-100 text-gray-700 hover:bg-gray-200">Cancel</button>
                </div>
              ) : (
                <div className="flex items-center gap-2">
                  <span className="text-sm text-gray-800">{detail.blockReason || '\u2014'}</span>
                  <button onClick={() => setEditingReason(true)} className="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</button>
                </div>
              )}
            </div>

            {/* AML status */}
            <div>
              <span className="text-gray-500 text-xs block mb-1">AML Status</span>
              <StatusBadge status={amlVariant(detail.amlStatus)} label={amlLabel(detail.amlStatus)} />
            </div>

            {/* Aliases */}
            <div>
              <span className="text-ink-muted text-xs block mb-1">Alias names</span>
              {detail.aliases.length > 0 ? (
                <div className="space-y-1 mb-2">
                  {detail.aliases.map(alias => (
                    <div key={alias.id} className="flex items-center justify-between bg-gray-50 px-3 py-1.5 rounded text-sm">
                      <span>{alias.aliasName}</span>
                      <button onClick={() => handleRemoveAlias(alias.id)} className="text-red-500 hover:text-red-700 text-xs font-medium">Remove</button>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-sm text-gray-400 mb-2">No aliases.</p>
              )}
              <div className="flex items-center gap-2">
                <input
                  type="text"
                  value={newAliasValue}
                  onChange={e => setNewAliasValue(e.target.value)}
                  onKeyDown={e => { if (e.key === 'Enter') handleAddAlias() }}
                  placeholder="Add alias..."
                  className="flex-1 px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500"
                />
                <button onClick={handleAddAlias} disabled={addingAlias || !newAliasValue.trim()} className="px-3 py-1.5 text-sm rounded-md bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50">
                  {addingAlias ? 'Adding...' : 'Add'}
                </button>
              </div>
            </div>

            {/* Policies */}
            <div>
              <span className="text-gray-500 text-xs block mb-1">Policies ({detail.policies.length})</span>
              {detail.policies.length > 0 ? (
                <div className="border rounded-md divide-y divide-gray-100 max-h-48 overflow-y-auto">
                  {detail.policies.map(pol => (
                    <div key={pol.id} className="flex items-center justify-between px-3 py-2 text-sm">
                      <div>
                        <span className="font-medium text-gray-800">{pol.policyNumber}</span>
                        <span className="text-gray-400 ml-2">{pol.productName}</span>
                      </div>
                      <StatusBadge status={pol.status} />
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-sm text-gray-400">No policies.</p>
              )}
            </div>
          </div>
        ) : null}
      </Modal>

      {/* ─── Add to Block List Modal (graphiteBWV8 parity) ─── */}
      <Modal
        open={blockModalOpen}
        onClose={() => { setBlockModalOpen(false); setBlockForm({ ...EMPTY_BLOCK_FORM }) }}
        title="Add to Block List"
        size="2xl"
        footer={
          <>
            <button onClick={() => { setBlockModalOpen(false); setBlockForm({ ...EMPTY_BLOCK_FORM }) }} className="px-3 py-1.5 text-sm rounded-md bg-gray-100 text-gray-700 hover:bg-gray-200">Cancel</button>
            <button onClick={handleBlock} disabled={blocking || !blockFormValid} className="px-3 py-1.5 text-sm rounded-md bg-red-600 text-white hover:bg-red-700 disabled:opacity-50">
              {blocking ? 'Adding...' : 'Add to Block List'}
            </button>
          </>
        }
      >
        <div className="space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">First Name <span className="text-red-600">*</span></label>
              <input type="text" value={blockForm.first_name} onChange={e => setBlockField('first_name', e.target.value)}
                placeholder="Enter first name"
                className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500" />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
              <input type="text" value={blockForm.middle_name} onChange={e => setBlockField('middle_name', e.target.value)}
                placeholder="Enter middle name"
                className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500" />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Last Name <span className="text-red-600">*</span></label>
              <input type="text" value={blockForm.last_name} onChange={e => setBlockField('last_name', e.target.value)}
                placeholder="Enter last name"
                className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500" />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
              <input type="email" value={blockForm.email} onChange={e => setBlockField('email', e.target.value)}
                placeholder="Enter email"
                className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500" />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Omang Id</label>
              <input type="text" maxLength={9} value={blockForm.omang} onChange={e => setBlockField('omang', e.target.value)}
                placeholder="Enter Omang"
                className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500" />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Passport Number</label>
              <input type="text" maxLength={12} value={blockForm.passport} onChange={e => setBlockField('passport', e.target.value)}
                placeholder="Enter passport number"
                className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500" />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Mobile No.</label>
              <input type="text" maxLength={8} value={blockForm.mobile} onChange={e => setBlockField('mobile', e.target.value)}
                placeholder="Enter mobile no."
                className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500" />
            </div>
          </div>
          <div>
            <label className="block text-sm font-medium text-ink mb-1">Alias names</label>
            {blockAliases.length > 0 && (
              <div className="space-y-1 mb-2">
                {blockAliases.map((a, i) => (
                  <div key={i} className="flex items-center justify-between bg-surface-2 px-3 py-1.5 rounded text-sm">
                    <span>{a}</span>
                    <button type="button" onClick={() => setBlockAliases(prev => prev.filter((_, idx) => idx !== i))} className="text-red-500 hover:text-red-700 text-xs font-medium">Remove</button>
                  </div>
                ))}
              </div>
            )}
            <div className="flex items-center gap-2">
              <input
                type="text"
                value={newBlockAlias}
                onChange={e => setNewBlockAlias(e.target.value)}
                onKeyDown={e => { if (e.key === 'Enter') { e.preventDefault(); addBlockAlias() } }}
                placeholder="Add alias..."
                className="flex-1 px-3 py-2 border border-line rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
              />
              <button type="button" onClick={addBlockAlias} disabled={!newBlockAlias.trim()} className="px-3 py-2 text-sm rounded-md bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50">Add</button>
            </div>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Reasons of Cancellation <span className="text-red-600">*</span></label>
            <textarea value={blockForm.block_reason} onChange={e => setBlockField('block_reason', e.target.value)}
              placeholder="Reasons of Cancellation"
              rows={3} maxLength={255}
              className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 resize-none" />
          </div>
        </div>
      </Modal>

      {/* ─── Unblock Confirmation Modal ─── */}
      <Modal
        open={!!confirmUnblock}
        onClose={() => setConfirmUnblock(null)}
        title="Confirm Unblock"
        footer={
          <>
            <button onClick={() => setConfirmUnblock(null)} className="px-3 py-1.5 text-sm rounded-md bg-gray-100 text-gray-700 hover:bg-gray-200">Cancel</button>
            <button onClick={handleUnblock} disabled={unblocking} className="px-3 py-1.5 text-sm rounded-md bg-green-600 text-white hover:bg-green-700 disabled:opacity-50">
              {unblocking ? 'Unblocking...' : 'Confirm Unblock'}
            </button>
          </>
        }
      >
        <p className="text-sm text-gray-600">
          Are you sure you want to unblock <span className="font-semibold">{confirmUnblock ? fullName(confirmUnblock) : ''}</span>?
          This will remove them from the block list.
        </p>
      </Modal>
    </div>
  )
}
