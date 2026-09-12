import { useState, useEffect, useCallback } from 'react'
import { useSearchParams } from 'react-router-dom'
import Select from 'react-select'
import apiClient from '../../api/client'
import { getStoredPermissions, getStoredRoles } from '../../api/auth'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import { reportErrorToTeam } from '../../utils/reportError'
import LoginActivityModal from './LoginActivityModal'

interface User {
  id: number
  firstName: string
  lastName: string
  name?: string
  email: string
  role?: string
  agency_id?: number | null
  pin?: string | number | null
  active: number
  last_login_at: string | null
  created_at: string
}

interface Agency {
  id: number
  name: string
}

interface Role {
  id: number
  name: string
  rule_group?: string | null
  is_grade?: boolean
}

interface Meta {
  total: number
  per_page: number
  current_page: number
  last_page: number
}

const STATUS_BADGE: Record<number, { label: string; classes: string }> = {
  0: { label: 'Inactive', classes: 'bg-red-100 text-red-700' },
  1: { label: 'Active', classes: 'bg-green-100 text-green-700' },
  2: { label: 'Suspended', classes: 'bg-yellow-100 text-yellow-700' },
}

/**
 * Minimum password length. Mirrors the backend's `min:8` on
 * UserController::update(), which in turn mirrors the app-wide policy in
 * Actions\Fortify\PasswordValidationRules. The app sets no complexity
 * requirements (no uppercase/numeric/symbol flags), so neither does this —
 * the client check exists to save a round-trip, not to be the gate.
 */
const PASSWORD_MIN_LENGTH = 8

/** Roles allowed to rotate staff passwords — mirrors PASSWORD_MANAGER_ROLES on the backend. */
const PASSWORD_MANAGER_ROLES = ['Admin', 'admin', 'Super Admin']

/**
 * Validation keys this modal actually renders an input (and an inline error)
 * for. Anything the backend rejects outside this set has nowhere to show, so
 * handleSaveUser promotes it into the banner rather than leaving the operator
 * hunting for a red field that was never drawn.
 */
const FORM_FIELDS = [
  'firstName', 'lastName', 'email', 'password', 'passwordConfirmation',
  'role_id', 'agency_id', 'pin', 'active',
]

/**
 * May the current user set another staff member's password? The backend
 * enforces this too (403); this only decides whether to render the fields, so
 * operators who can't use them don't see a control that will just fail.
 */
function canManageStaffPasswords(): boolean {
  const mine = getStoredRoles()
  return PASSWORD_MANAGER_ROLES.some((r) => mine.includes(r))
    || getStoredPermissions().includes('user-edit')
}

export default function UserListPage() {
  const [searchParams, setSearchParams] = useSearchParams()

  const [users, setUsers] = useState<User[]>([])
  const [meta, setMeta] = useState<Meta | null>(null)
  const [loading, setLoading] = useState(true)
  const [fetching, setFetching] = useState(false)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [agencies, setAgencies] = useState<Agency[]>([])
  const [roles, setRoles] = useState<Role[]>([])

  const [showModal, setShowModal] = useState(false)
  const [editingUser, setEditingUser] = useState<User | null>(null)
  const [activityUser, setActivityUser] = useState<User | null>(null)
  const [formData, setFormData] = useState({
    firstName: '',
    lastName: '',
    email: '',
    password: '',
    passwordConfirmation: '',
    role_id: '',
    agency_id: '',
    pin: '',
    active: '1',
  })
  const [formError, setFormError] = useState('')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})
  const [formLoading, setFormLoading] = useState(false)
  // Edit mode: the editing user's currently-assigned roles. Loaded
  // on modal open. Add/remove operations call the dedicated additive
  // endpoints so other roles (Super Admin, Manager, Admin) survive
  // when a dev swaps themselves into a Grade role for testing.
  const [editingUserRoles, setEditingUserRoles] = useState<Role[]>([])
  const [addRoleId, setAddRoleId] = useState('')
  const [roleOpInFlight, setRoleOpInFlight] = useState(false)
  const canSetPassword = canManageStaffPasswords()

  const search = searchParams.get('search') || ''
  const roleFilter = searchParams.get('role') || ''
  const agencyFilter = searchParams.get('agency') || ''
  const statusFilter = searchParams.get('status') || ''
  const page = Number(searchParams.get('page') || '1')

  // Fetch agencies and roles on mount
  useEffect(() => {
    Promise.all([
      apiClient.get('/lookups/agencies').catch(() => ({ data: { data: [] } })),
      apiClient.get('/roles').catch(() => ({ data: { data: [] } })),
    ]).then(([agRes, rolRes]) => {
      setAgencies(agRes.data.data || [])
      setRoles(rolRes.data.data || [])
    })
  }, [])

  const fetchUsers = useCallback(async () => {
    setFetching(true)
    try {
      const params: Record<string, any> = { per_page: 25, page }
      if (search) params.search = search
      if (roleFilter) params.role_id = roleFilter
      if (agencyFilter) params.agency_id = agencyFilter
      if (statusFilter) params.status = statusFilter
      const res = await apiClient.get<{ data: User[]; meta: Meta }>('/users', { params })
      setUsers(res.data.data.map(u => ({ ...u, name: `${u.firstName} ${u.lastName}`.trim() })))
      setMeta(res.data.meta)
      setLoadError(null)
    } catch (err: any) {
      // UAT 2026-05-26 (Prathap BUG-019 / BUG-020): silent catch{} hides
      // backend failures behind an empty list — operators can't tell if
      // "0 users" means filtered-empty or API-down. Surface the error and
      // report to developers@ so it doesn't go uninvestigated.
      const message = err?.response?.data?.message ?? err?.message ?? 'Failed to load users.'
      setUsers([])
      setMeta(null)
      setLoadError(message)
      reportErrorToTeam({
        error: message,
        stack: err?.stack,
        context: 'UserListPage:fetchUsers',
      })
    } finally {
      setLoading(false)
      setFetching(false)
    }
  }, [search, roleFilter, agencyFilter, statusFilter, page])

  useEffect(() => {
    fetchUsers()
  }, [fetchUsers])

  function updateFilter(key: string, value: string) {
    const next = new URLSearchParams(searchParams)
    if (value) next.set(key, value)
    else next.delete(key)
    next.delete('page')
    setSearchParams(next)
  }

  function goToPage(p: number) {
    const next = new URLSearchParams(searchParams)
    next.set('page', String(p))
    setSearchParams(next)
  }

  const currentPage = meta?.current_page ?? 1
  const lastPage = meta?.last_page ?? 1

  function getPageNumbers(): (number | '...')[] {
    if (lastPage <= 7) return Array.from({ length: lastPage }, (_, i) => i + 1)
    const pages: (number | '...')[] = []
    const addPage = (p: number) => { if (!pages.includes(p)) pages.push(p) }
    addPage(1)
    if (currentPage > 3) pages.push('...')
    for (let i = Math.max(2, currentPage - 1); i <= Math.min(lastPage - 1, currentPage + 1); i++) addPage(i)
    if (currentPage < lastPage - 2) pages.push('...')
    addPage(lastPage)
    return pages
  }

  function formatDate(dateStr: string | null): string {
    if (!dateStr) return '—'
    const d = new Date(dateStr)
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) +
      ' ' + d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
  }

  async function handleSaveUser() {
    setFormError('')
    setFieldErrors({})

    // The "Confirm New Password" input only exists inside edit mode's Change
    // Password box. Create mode renders a single Password field, so
    // passwordConfirmation is always blank there — comparing the two rejected
    // every new staff member with the "fix the errors below" banner and no
    // field to fix, since the error attached to a control that isn't rendered.
    const hasConfirmField = !!editingUser && canSetPassword

    // Password is optional on edit — blank means "leave the existing one
    // alone". Only validate once the operator has actually typed something.
    // Checked before setFormLoading so an early return can't strand the
    // Save button on "Saving...".
    if (formData.password || (hasConfirmField && formData.passwordConfirmation)) {
      if (formData.password.length < PASSWORD_MIN_LENGTH) {
        setFieldErrors({ password: `Password must be at least ${PASSWORD_MIN_LENGTH} characters` })
        setFormError('Please fix the errors below')
        return
      }
      if (hasConfirmField && formData.password !== formData.passwordConfirmation) {
        setFieldErrors({ passwordConfirmation: 'Passwords do not match' })
        setFormError('Please fix the errors below')
        return
      }
    }

    setFormLoading(true)
    try {
      const payload = {
        firstName: formData.firstName.trim(),
        lastName: formData.lastName.trim(),
        email: formData.email.trim(),
        // Send the password only when one was typed. Omitting the key is what
        // tells the backend to keep the current hash — an empty string would
        // trip the min:8 rule and fail an otherwise-valid save.
        // password_confirmation goes only with the edit-mode form, which is
        // the one the backend validates `confirmed` on (update()); store()
        // has no such rule and create mode has no confirmation input, so
        // sending a blank one there would be a lie waiting to fail.
        ...(formData.password && {
          password: formData.password,
          ...(hasConfirmField && { password_confirmation: formData.passwordConfirmation }),
        }),
        ...(formData.role_id && { role_id: parseInt(formData.role_id) }),
        ...(formData.agency_id && { agency_id: parseInt(formData.agency_id) }),
        // Only send pin when the operator typed one — an empty string would
        // fail the backend's size:4 rule, and omitting it leaves any
        // existing PIN untouched on save.
        ...(formData.pin && { pin: formData.pin }),
        active: parseInt(formData.active),
      }

      if (editingUser) {
        await apiClient.put(`/users/${editingUser.id}`, payload)
      } else {
        if (!formData.password) {
          setFormError('Password is required for new users')
          setFormLoading(false)
          return
        }
        await apiClient.post('/users', payload)
      }

      setShowModal(false)
      setEditingUser(null)
      setFormData({ firstName: '', lastName: '', email: '', password: '', passwordConfirmation: '', role_id: '', agency_id: '', pin: '', active: '1' })
      fetchUsers()
    } catch (err: any) {
      const errors = err?.response?.data?.errors || {}
      if (Object.keys(errors).length > 0) {
        // Convert field errors to readable format
        const fieldErrorMap: Record<string, string> = {}
        for (const [field, messages] of Object.entries(errors)) {
          fieldErrorMap[field] = Array.isArray(messages) ? messages[0] : String(messages)
        }
        setFieldErrors(fieldErrorMap)

        // The backend validates more columns than this modal renders (dob,
        // omang, cellphone, work_position, accountType…). An error on one of
        // those has no input to attach to, so "fix the errors below" would
        // point at nothing. Spell those ones out in the banner instead.
        const orphaned = Object.entries(fieldErrorMap)
          .filter(([field]) => !FORM_FIELDS.includes(field))
          .map(([, message]) => message)
        setFormError(
          orphaned.length > 0
            ? orphaned.join(' ')
            : 'Please fix the errors below'
        )
      } else {
        setFieldErrors({})
        setFormError(err?.response?.data?.message || 'Failed to save user')
      }
    } finally {
      setFormLoading(false)
    }
  }

  async function handleToggleStatus(userId: number, currentStatus: number) {
    try {
      const newStatus = currentStatus === 1 ? 0 : 1
      await apiClient.post(`/users/${userId}/toggle-status`, { status: newStatus })
      fetchUsers()
    } catch (err: any) {
      alert(err?.response?.data?.message || 'Failed to toggle status')
    }
  }

  function openCreateModal() {
    setEditingUser(null)
    setFormData({ firstName: '', lastName: '', email: '', password: '', passwordConfirmation: '', role_id: '', agency_id: '', pin: '', active: '1' })
    setEditingUserRoles([])
    setAddRoleId('')
    setFormError('')
    setFieldErrors({})
    setShowModal(true)
  }

  async function openEditModal(user: User) {
    setEditingUser(user)
    setFormData({
      firstName: user.firstName,
      lastName: user.lastName,
      email: user.email,
      // Never pre-filled — we only ever hold the hash server-side. Blank on
      // open means "keep the current password" unless the operator types one.
      password: '',
      passwordConfirmation: '',
      role_id: '',
      agency_id: user.agency_id?.toString() || '',
      // API may return the PIN as a number; coerce so it round-trips as a string.
      pin: user.pin != null ? String(user.pin) : '',
      active: user.active.toString(),
    })
    setEditingUserRoles([])
    setAddRoleId('')
    setFormError('')
    setFieldErrors({})
    setShowModal(true)
    await fetchEditingUserRoles(user.id)
  }

  async function fetchEditingUserRoles(userId: number) {
    try {
      const r = await apiClient.get(`/users/${userId}/roles`)
      setEditingUserRoles(r.data?.data ?? [])
    } catch {
      setEditingUserRoles([])
    }
  }

  async function handleAttachRole() {
    if (!editingUser || !addRoleId) return
    setRoleOpInFlight(true)
    try {
      await apiClient.post(`/users/${editingUser.id}/roles/${addRoleId}`)
      setAddRoleId('')
      await fetchEditingUserRoles(editingUser.id)
    } catch (err: any) {
      alert(err?.response?.data?.message || 'Failed to attach role')
    } finally {
      setRoleOpInFlight(false)
    }
  }

  async function handleDetachRole(roleId: number) {
    if (!editingUser) return
    if (!window.confirm('Remove this role from the user? Other roles remain unchanged.')) return
    setRoleOpInFlight(true)
    try {
      await apiClient.delete(`/users/${editingUser.id}/roles/${roleId}`)
      await fetchEditingUserRoles(editingUser.id)
    } catch (err: any) {
      alert(err?.response?.data?.message || 'Failed to remove role')
    } finally {
      setRoleOpInFlight(false)
    }
  }

  return (
    <div className="p-6 space-y-4">
      {/* Header */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <h1 className="text-2xl font-bold text-gray-800">Staff</h1>
          {meta && (
            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
              {meta.total.toLocaleString()}
            </span>
          )}
        </div>
        <button
          onClick={openCreateModal}
          className="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
          + Create Staff
        </button>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap gap-3">
        <input
          type="text"
          placeholder="Search by name or email..."
          defaultValue={search}
          onKeyDown={(e) => {
            if (e.key === 'Enter') updateFilter('search', (e.target as HTMLInputElement).value)
          }}
          className="border rounded-md px-3 py-1.5 text-sm w-72 focus:ring-2 focus:ring-blue-500 focus:outline-none"
        />
        <select
          value={roleFilter}
          onChange={(e) => updateFilter('role', e.target.value)}
          className="border rounded-md px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
          <option value="">All Roles</option>
          {roles.map(r => <option key={r.id} value={r.id}>{r.name}</option>)}
        </select>
        <select
          value={agencyFilter}
          onChange={(e) => updateFilter('agency', e.target.value)}
          className="border rounded-md px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
          <option value="">All Agencies</option>
          {agencies.map(a => <option key={a.id} value={a.id}>{a.name}</option>)}
        </select>
        <select
          value={statusFilter}
          onChange={(e) => updateFilter('status', e.target.value)}
          className="border rounded-md px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
          <option value="">All Statuses</option>
          <option value="1">Active</option>
          <option value="0">Inactive</option>
          <option value="2">Suspended</option>
        </select>
      </div>

      {/* Table */}
      {loading ? (
        <div className="bg-white rounded-lg border border-gray-200 shadow-sm p-12">
          <LoadingSpinner size="lg" className="mb-3" />
          <p className="text-center text-sm text-gray-400">Loading users...</p>
        </div>
      ) : loadError ? (
        <div className="bg-red-50 border border-red-200 rounded-lg p-6 space-y-3">
          <p className="text-sm font-medium text-red-700">Failed to load users.</p>
          <p className="text-xs text-red-600">{loadError}</p>
          <button onClick={fetchUsers} className="px-3 py-1.5 bg-red-600 text-white rounded text-sm hover:bg-red-700">Try again</button>
        </div>
      ) : (
        <>
          {fetching && (
            <div className="bg-white rounded-lg border border-blue-200 shadow-sm px-4 py-3 flex items-center gap-2">
              <LoadingSpinner size="sm" />
              <span className="text-sm text-blue-600">Updating results...</span>
            </div>
          )}

          <div className={`bg-white rounded-lg border border-gray-200 shadow-sm overflow-x-auto transition-opacity ${fetching ? 'opacity-50 pointer-events-none' : ''}`}>
            <table className="w-full text-sm">
              <thead className="bg-gray-50 text-gray-500 uppercase text-xs">
                <tr>
                  <th className="px-4 py-2 text-left">Agent ID</th>
                  <th className="px-4 py-2 text-left">Name</th>
                  <th className="px-4 py-2 text-left">Email</th>
                  <th className="px-4 py-2 text-left">Role</th>
                  <th className="px-4 py-2 text-left">Status</th>
                  <th className="px-4 py-2 text-left">Last Login</th>
                  <th className="px-4 py-2 text-left">Created</th>
                  <th className="px-4 py-2 text-left">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {users.map((u) => {
                  const badge = STATUS_BADGE[u.active] ?? { label: 'Unknown', classes: 'bg-gray-100 text-gray-600' }
                  return (
                    <tr key={u.id} className="hover:bg-gray-50">
                      <td className="px-4 py-2 font-mono text-gray-700">{u.id}</td>
                      <td className="px-4 py-2 font-medium text-gray-800">{u.name}</td>
                      <td className="px-4 py-2 text-gray-600 truncate max-w-[220px]" title={u.email}>{u.email}</td>
                      <td className="px-4 py-2">
                        <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                          {u.role || '—'}
                        </span>
                      </td>
                      <td className="px-4 py-2">
                        <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${badge.classes}`}>
                          {badge.label}
                        </span>
                      </td>
                      <td className="px-4 py-2 text-gray-500">{formatDate(u.last_login_at)}</td>
                      <td className="px-4 py-2 text-gray-500">{formatDate(u.created_at)}</td>
                      <td className="px-4 py-2 text-sm flex gap-2">
                        <button
                          onClick={() => openEditModal(u)}
                          className="text-blue-600 hover:text-blue-800 font-medium">
                          Edit
                        </button>
                        <button
                          onClick={() => handleToggleStatus(u.id, u.active)}
                          className="text-amber-600 hover:text-amber-800 font-medium">
                          {u.active === 1 ? 'Deactivate' : 'Activate'}
                        </button>
                        <button
                          onClick={() => setActivityUser(u)}
                          className="text-gray-600 hover:text-gray-800 font-medium">
                          Activity
                        </button>
                      </td>
                    </tr>
                  )
                })}
                {users.length === 0 && (
                  <tr>
                    <td colSpan={8} className="px-4 py-12 text-center">
                      <p className="text-gray-500">No users found. Try adjusting your filters.</p>
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>

          {/* Pagination */}
          {meta && meta.last_page > 1 && (
            <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-gray-500">
              <span>
                Page {currentPage} of {lastPage.toLocaleString()} ({meta.total.toLocaleString()} total)
              </span>
              <div className="flex items-center gap-1">
                <button
                  disabled={currentPage === 1}
                  onClick={() => goToPage(currentPage - 1)}
                  className="px-2.5 py-1 border rounded disabled:opacity-40 hover:bg-gray-50 text-xs"
                >
                  Prev
                </button>
                {getPageNumbers().map((p, i) =>
                  p === '...' ? (
                    <span key={`e${i}`} className="px-1.5 text-gray-400">...</span>
                  ) : (
                    <button
                      key={p}
                      onClick={() => goToPage(p)}
                      className={`px-2.5 py-1 border rounded text-xs ${
                        p === currentPage
                          ? 'bg-blue-600 text-white border-blue-600'
                          : 'hover:bg-gray-50'
                      }`}
                    >
                      {p}
                    </button>
                  )
                )}
                <button
                  disabled={currentPage === lastPage}
                  onClick={() => goToPage(currentPage + 1)}
                  className="px-2.5 py-1 border rounded disabled:opacity-40 hover:bg-gray-50 text-xs"
                >
                  Next
                </button>
              </div>
            </div>
          )}
        </>
      )}

      {/* Create/Edit Modal */}
      {showModal && (
        <div
          className="fixed inset-0 bg-black/50 flex items-start justify-center z-50 p-4 pt-10 overflow-y-auto"
          onClick={(e) => { if (e.target === e.currentTarget) setShowModal(false) }}
        >
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md p-5 max-h-[85vh] overflow-y-auto">
            <h2 className="text-lg font-bold text-gray-800 mb-3">
              {editingUser ? 'Edit Staff' : 'Create Staff'}
            </h2>

            {formError && (
              <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded text-sm text-red-700">
                {formError}
              </div>
            )}

            <div className="space-y-2.5">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <input
                    type="text"
                    name="staff_first_name"
                    autoComplete="off"
                    placeholder="First Name"
                    value={formData.firstName}
                    onChange={(e) => setFormData({ ...formData, firstName: e.target.value })}
                    className={`w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:outline-none ${
                      fieldErrors.firstName ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 focus:ring-blue-500'
                    }`}
                  />
                  {fieldErrors.firstName && (
                    <p className="text-xs text-red-600 mt-1">{fieldErrors.firstName}</p>
                  )}
                </div>
                <div>
                  <input
                    type="text"
                    name="staff_last_name"
                    autoComplete="off"
                    placeholder="Last Name"
                    value={formData.lastName}
                    onChange={(e) => setFormData({ ...formData, lastName: e.target.value })}
                    className={`w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:outline-none ${
                      fieldErrors.lastName ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 focus:ring-blue-500'
                    }`}
                  />
                  {fieldErrors.lastName && (
                    <p className="text-xs text-red-600 mt-1">{fieldErrors.lastName}</p>
                  )}
                </div>
              </div>

              <div>
                <input
                  type="email"
                  name="staff_email_address"
                  autoComplete="off"
                  placeholder="Email"
                  value={formData.email}
                  onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                  className={`w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:outline-none ${
                    fieldErrors.email ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 focus:ring-blue-500'
                  }`}
                />
                {fieldErrors.email && (
                  <p className="text-xs text-red-600 mt-1">{fieldErrors.email}</p>
                )}
              </div>

              {!editingUser && (
                <div>
                  <input
                    type="password"
                    name="staff_new_password"
                    autoComplete="new-password"
                    placeholder="Password"
                    value={formData.password}
                    onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                    className={`w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:outline-none ${
                      fieldErrors.password ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 focus:ring-blue-500'
                    }`}
                  />
                  {fieldErrors.password && (
                    <p className="text-xs text-red-600 mt-1">{fieldErrors.password}</p>
                  )}
                </div>
              )}

              {/* EDIT mode → optional password rotation. Blank leaves the
                  existing password untouched; the fields are only rendered for
                  operators the backend would actually let through, so nobody
                  types a new password into a control that 403s on save. */}
              {editingUser && canSetPassword && (
                <div className="border rounded p-3 space-y-2.5">
                  <div className="text-xs font-semibold text-gray-600 uppercase tracking-wide">
                    Change Password
                  </div>
                  <div>
                    <input
                      type="password"
                      name="staff_reset_password"
                      autoComplete="new-password"
                      placeholder="New Password"
                      value={formData.password}
                      onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                      className={`w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:outline-none ${
                        fieldErrors.password ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 focus:ring-blue-500'
                      }`}
                    />
                    {fieldErrors.password && (
                      <p className="text-xs text-red-600 mt-1">{fieldErrors.password}</p>
                    )}
                  </div>
                  <div>
                    <input
                      type="password"
                      name="staff_reset_password_confirm"
                      autoComplete="new-password"
                      placeholder="Confirm New Password"
                      value={formData.passwordConfirmation}
                      onChange={(e) => setFormData({ ...formData, passwordConfirmation: e.target.value })}
                      className={`w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:outline-none ${
                        fieldErrors.passwordConfirmation ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 focus:ring-blue-500'
                      }`}
                    />
                    {fieldErrors.passwordConfirmation && (
                      <p className="text-xs text-red-600 mt-1">{fieldErrors.passwordConfirmation}</p>
                    )}
                  </div>
                  <div className="text-[11px] text-gray-500 leading-snug">
                    Leave both blank to keep the current password. Minimum{' '}
                    {PASSWORD_MIN_LENGTH} characters. The staff member is not
                    notified — tell them their new password yourself.
                  </div>
                </div>
              )}

              {/* CREATE mode → single role dropdown for the first assignment.
                  EDIT mode → live chips + add/remove against the new
                  additive endpoints. Other roles survive grade-role swaps. */}
              {!editingUser ? (
                <div>
                  <select
                    value={formData.role_id}
                    onChange={(e) => setFormData({ ...formData, role_id: e.target.value })}
                    className={`w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:outline-none ${
                      fieldErrors.role_id ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 focus:ring-blue-500'
                    }`}>
                    <option value="">Select Role (Optional)</option>
                    {roles.map(r => <option key={r.id} value={r.id}>{r.name}</option>)}
                  </select>
                  {fieldErrors.role_id && (
                    <p className="text-xs text-red-600 mt-1">{fieldErrors.role_id}</p>
                  )}
                </div>
              ) : (
                <div className="border rounded p-3 space-y-3">
                  <div className="text-xs font-semibold text-gray-600 uppercase tracking-wide">Roles</div>
                  {editingUserRoles.length === 0 ? (
                    <div className="text-sm text-gray-500 italic">No roles assigned.</div>
                  ) : (
                    <div className="flex flex-wrap gap-2">
                      {editingUserRoles.map(r => (
                        <span
                          key={r.id}
                          className={`inline-flex items-center gap-2 px-2.5 py-1 rounded-full text-xs font-medium ${
                            r.is_grade
                              ? 'bg-orange-50 text-orange-800 border border-orange-200'
                              : 'bg-blue-50 text-blue-800 border border-blue-200'
                          }`}
                          title={r.rule_group ? `rule_group=${r.rule_group}` : undefined}
                        >
                          {r.name}
                          <button
                            type="button"
                            onClick={() => handleDetachRole(r.id)}
                            disabled={roleOpInFlight}
                            className="text-gray-500 hover:text-red-600 disabled:opacity-40"
                            aria-label={`Remove ${r.name}`}
                          >
                            ×
                          </button>
                        </span>
                      ))}
                    </div>
                  )}
                  <div className="flex gap-2 items-center">
                    {/* react-select with menuPortalTarget renders the dropdown
                        list at document.body, escaping the modal's clipping
                        context so long role names (e.g. "Grade ELECTRONIC-
                        EQANDBI_DOM_DRIVER") don't push the popup out of the
                        modal box. Searchable too — handy with 20+ roles. */}
                    <div className="flex-1 min-w-0">
                      <Select
                        value={
                          addRoleId
                            ? (() => {
                                const r = roles.find(x => String(x.id) === addRoleId)
                                return r ? { value: String(r.id), label: r.name } : null
                              })()
                            : null
                        }
                        onChange={opt => setAddRoleId(opt?.value ?? '')}
                        options={roles
                          .filter(r => !editingUserRoles.some(er => er.id === r.id))
                          .map(r => ({ value: String(r.id), label: r.name }))}
                        isClearable
                        isSearchable
                        placeholder="+ Add role…"
                        menuPortalTarget={typeof document !== 'undefined' ? document.body : undefined}
                        styles={{
                          control: (base, state) => ({
                            ...base,
                            minHeight: '32px',
                            fontSize: '0.875rem',
                            borderColor: state.isFocused ? '#3b82f6' : '#d1d5db',
                            boxShadow: state.isFocused ? '0 0 0 2px rgba(59,130,246,0.3)' : 'none',
                            '&:hover': { borderColor: state.isFocused ? '#3b82f6' : '#9ca3af' },
                          }),
                          menu:       base => ({ ...base, fontSize: '0.875rem', zIndex: 9999 }),
                          menuPortal: base => ({ ...base, zIndex: 9999 }),
                          option: (base, state) => ({
                            ...base,
                            backgroundColor: state.isSelected ? '#3b82f6' : state.isFocused ? '#eff6ff' : '#fff',
                            color: state.isSelected ? '#fff' : '#111',
                            fontSize: '0.875rem',
                            cursor: 'pointer',
                          }),
                        }}
                      />
                    </div>
                    <button
                      type="button"
                      onClick={handleAttachRole}
                      disabled={!addRoleId || roleOpInFlight}
                      className="px-3 py-1.5 bg-blue-600 text-white rounded text-xs font-medium hover:bg-blue-700 disabled:opacity-50 whitespace-nowrap">
                      {roleOpInFlight ? 'Saving…' : 'Add'}
                    </button>
                  </div>
                  <div className="text-[11px] text-gray-500 leading-snug">
                    Role changes are saved immediately. Orange chips are <strong>grade roles</strong>
                    (drive validation-rules behaviour). Blue chips are functional roles.
                  </div>
                </div>
              )}

              <div>
                <label className="block text-xs font-medium text-ink-muted mb-1">Agency</label>
                {/* Searchable: the agency list is long, and a plain <select>
                    gave no way to type-ahead. Same react-select setup as the
                    role picker above so the menu escapes the modal clipping. */}
                <Select
                  value={(() => {
                    const a = agencies.find(x => String(x.id) === formData.agency_id)
                    return a ? { value: String(a.id), label: a.name } : null
                  })()}
                  onChange={opt => setFormData({ ...formData, agency_id: opt?.value ?? '' })}
                  options={agencies.map(a => ({ value: String(a.id), label: a.name }))}
                  isClearable
                  isSearchable
                  placeholder="Select Agency (Optional)"
                  noOptionsMessage={() => 'No agency matches'}
                  menuPortalTarget={typeof document !== 'undefined' ? document.body : undefined}
                  styles={{
                    // Themed via CSS tokens (same as CreateWizard/FormField) so
                    // the control flips correctly in dark mode.
                    control: (base, state) => ({
                      ...base,
                      minHeight: '38px',
                      fontSize: '0.875rem',
                      borderColor: fieldErrors.agency_id ? 'rgb(var(--danger-fg))' : state.isFocused ? 'rgb(var(--primary))' : 'rgb(var(--border))',
                      backgroundColor: 'rgb(var(--surface))',
                      boxShadow: state.isFocused ? '0 0 0 2px rgb(var(--primary) / 0.25)' : 'none',
                      '&:hover': { borderColor: state.isFocused ? 'rgb(var(--primary))' : 'rgb(var(--text-faint))' },
                    }),
                    singleValue: base => ({ ...base, color: 'rgb(var(--text))' }),
                    input:       base => ({ ...base, color: 'rgb(var(--text))' }),
                    menu:        base => ({ ...base, fontSize: '0.875rem', zIndex: 9999, backgroundColor: 'rgb(var(--surface))' }),
                    menuPortal:  base => ({ ...base, zIndex: 9999 }),
                    option: (base, state) => ({
                      ...base,
                      backgroundColor: state.isSelected ? 'rgb(var(--primary))' : state.isFocused ? 'rgb(var(--surface-2))' : 'rgb(var(--surface))',
                      color: state.isSelected ? 'rgb(var(--primary-contrast))' : 'rgb(var(--text))',
                      fontSize: '0.875rem',
                      cursor: 'pointer',
                    }),
                  }}
                />
                {fieldErrors.agency_id && (
                  <p className="text-xs text-red-600 mt-1">{fieldErrors.agency_id}</p>
                )}
              </div>

              {/* Agent PIN — the 4-digit code sales agents use to issue
                  policies. Left blank on save it stays unchanged; type a new
                  one to set/rotate it. Backend validates exactly 4 digits. */}
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">Agent PIN</label>
                <input
                  type="text"
                  name="staff_agent_pin"
                  autoComplete="off"
                  inputMode="numeric"
                  maxLength={4}
                  placeholder="4-digit PIN"
                  value={formData.pin}
                  onChange={(e) => setFormData({ ...formData, pin: e.target.value.replace(/\D/g, '') })}
                  className={`w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:outline-none ${
                    fieldErrors.pin ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 focus:ring-blue-500'
                  }`}
                />
                {fieldErrors.pin && (
                  <p className="text-xs text-red-600 mt-1">{fieldErrors.pin}</p>
                )}
              </div>

              <div>
                <select
                  value={formData.active}
                  onChange={(e) => setFormData({ ...formData, active: e.target.value })}
                  className={`w-full px-3 py-2 border rounded text-sm focus:ring-2 focus:outline-none ${
                    fieldErrors.active ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 focus:ring-blue-500'
                  }`}>
                  <option value="1">Active</option>
                  <option value="0">Inactive</option>
                  <option value="2">Suspended</option>
                </select>
                {fieldErrors.active && (
                  <p className="text-xs text-red-600 mt-1">{fieldErrors.active}</p>
                )}
              </div>
            </div>

            <div className="flex gap-3 mt-4">
              <button
                onClick={() => setShowModal(false)}
                disabled={formLoading}
                className="flex-1 px-4 py-2 border rounded text-sm font-medium hover:bg-gray-50 disabled:opacity-50">
                Cancel
              </button>
              <button
                onClick={handleSaveUser}
                disabled={formLoading}
                className="flex-1 px-4 py-2 bg-blue-600 text-white rounded text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
                {formLoading ? 'Saving...' : 'Save'}
              </button>
            </div>
          </div>
        </div>
      )}

      <LoginActivityModal
        open={!!activityUser}
        onClose={() => setActivityUser(null)}
        userId={activityUser?.id ?? 0}
        userName={activityUser ? `${activityUser.firstName} ${activityUser.lastName}`.trim() : ''}
      />
    </div>
  )
}
