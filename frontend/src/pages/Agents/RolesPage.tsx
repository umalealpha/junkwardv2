import { useState, useEffect } from 'react'
import apiClient from '../../api/client'

interface Role { id: number; name: string; guardName: string; permissionCount: number; userCount: number; createdAt: string }
interface PermGroup { module: string; permissions: string[] }

export default function RolesPage() {
  const [roles, setRoles] = useState<Role[]>([])
  const [permGroups, setPermGroups] = useState<PermGroup[]>([])
  const [loading, setLoading] = useState(true)
  const [modalOpen, setModalOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [formName, setFormName] = useState('')
  const [selectedPerms, setSelectedPerms] = useState<string[]>([])
  const [selectedUnderRoles, setSelectedUnderRoles] = useState<number[]>([])
  const [saving, setSaving] = useState(false)
  const [activeTab, setActiveTab] = useState<'permissions' | 'under-roles'>('permissions')
  const [openingId, setOpeningId] = useState<number | null>(null)
  // How many permissions the role held when the modal opened. PUT /roles/{id}
  // is a FULL REPLACE — RolePermissionController::syncPermissions deletes every
  // role_has_permissions row for the role before re-inserting whatever the form
  // submits — so saving an empty tick-list strips the role. Kept to catch that.
  const [baselinePermCount, setBaselinePermCount] = useState(0)

  const load = () => {
    setLoading(true)
    Promise.all([apiClient.get('/roles'), apiClient.get('/permissions')])
      .then(([rr, pr]) => {
        setRoles(rr.data.data ?? [])
        setPermGroups(pr.data.grouped ?? [])
      })
      .catch(() => {})
      .finally(() => setLoading(false))
  }
  useEffect(() => { load() }, [])

  function openCreate() {
    setEditingId(null); setFormName(''); setSelectedPerms([]); setSelectedUnderRoles([]); setBaselinePermCount(0); setActiveTab('permissions'); setModalOpen(true)
  }

  /**
   * Load the role's current grants BEFORE showing the form.
   *
   * The previous version ran both GETs in one Promise.all with a catch that
   * set selectedPerms to [] and opened the modal anyway. Because the save is a
   * full replace, a failure on EITHER request — including the unrelated
   * under-roles one — presented a form with every box unticked that looked
   * perfectly normal, and one Save wiped the role's entire permission set.
   *
   * So the two calls are now separated by how bad it is to lose them:
   *   - role detail (permissions) is FATAL — abort and leave the modal shut.
   *   - under-roles is cosmetic — degrade to empty and carry on.
   */
  async function openEdit(role: Role) {
    if (openingId !== null) return
    setOpeningId(role.id)
    try {
      const permRes = await apiClient.get(`/roles/${role.id}`)
      const perms: string[] = permRes.data?.data?.permissions ?? []
      // A 200 with a body we can't read looks identical to "role has no
      // permissions". The list row already told us how many there should be, so
      // treat a mismatch as a failed load rather than opening an empty form.
      if (perms.length === 0 && role.permissionCount > 0) {
        throw new Error(`"${role.name}" reports ${role.permissionCount} permission(s) but none came back. Reload and try again.`)
      }
      const underRes = await apiClient.get(`/roles/${role.id}/under-roles`).catch(() => null)

      // Only commit form state once the permissions are safely in hand, so a
      // failed load can't leave the page holding a half-populated role.
      setEditingId(role.id)
      setFormName(role.name)
      setActiveTab('permissions')
      setSelectedPerms(perms)
      setBaselinePermCount(perms.length)
      // Coerce to numbers: rows written by the legacy page hold string ids.
      setSelectedUnderRoles((underRes?.data?.data?.under_role_ids ?? []).map(Number).filter(Number.isFinite))
      setModalOpen(true)
    } catch (e: any) {
      alert(e?.response?.data?.message || e?.message
        || `Could not load "${role.name}". Nothing has been changed — please try again.`)
    } finally {
      setOpeningId(null)
    }
  }

  function togglePerm(perm: string) {
    setSelectedPerms(prev => prev.includes(perm) ? prev.filter(p => p !== perm) : [...prev, perm])
  }

  function toggleModule(perms: string[]) {
    const allSelected = perms.every(p => selectedPerms.includes(p))
    if (allSelected) setSelectedPerms(prev => prev.filter(p => !perms.includes(p)))
    else setSelectedPerms(prev => [...new Set([...prev, ...perms])])
  }

  async function handleSave() {
    // Last line of defence on the full-replace save: clearing every box is a
    // legitimate thing to want, but it is far more often an accident. Make it
    // deliberate rather than silent.
    if (editingId && selectedPerms.length === 0 && baselinePermCount > 0) {
      if (!confirm(`This removes all ${baselinePermCount} permissions from "${formName}" — users with this role lose that access. Continue?`)) return
    }
    setSaving(true)
    try {
      if (editingId) {
        await apiClient.put(`/roles/${editingId}`, { name: formName, permissions: selectedPerms })
        await apiClient.post('/roles/under-roles', { role_id: editingId, under_role_ids: selectedUnderRoles })
      } else {
        await apiClient.post('/roles', { name: formName, permissions: selectedPerms })
      }
      setModalOpen(false); load()
    } catch (e: any) {
      // Surface the failing FIELD, not just Laravel's generic "The given data
      // was invalid." - that message alone gave no way to tell which of the two
      // calls (permissions PUT vs under-roles POST) rejected what.
      const errs = e.response?.data?.errors
      const detail = errs
        ? Object.entries(errs).map(([f, m]: any) => `${f}: ${[].concat(m).join(', ')}`).join('\n')
        : ''
      alert([e.response?.data?.message || 'Failed', detail].filter(Boolean).join('\n'))
    }
    finally { setSaving(false) }
  }

  if (loading) return <div className="p-6"><div className="animate-pulse space-y-3">{[1,2,3].map(i => <div key={i} className="h-10 bg-gray-200 rounded" />)}</div></div>

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-800">Roles & Permissions</h1>
        <button onClick={openCreate} className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">+ Add Role</button>
      </div>

      <div className="bg-white shadow rounded-lg overflow-hidden">
        <table className="min-w-full divide-y divide-gray-200 text-sm">
          <thead className="bg-gray-50">
            <tr>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
              <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Permissions</th>
              <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Users</th>
              <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-200">
            {roles.map(r => (
              <tr key={r.id} className="hover:bg-gray-50">
                <td className="px-4 py-3 font-medium text-gray-800">{r.name}</td>
                <td className="px-4 py-3 text-right"><span className="px-2 py-0.5 text-xs bg-blue-100 text-blue-700 rounded-full">{r.permissionCount}</span></td>
                <td className="px-4 py-3 text-right"><span className="px-2 py-0.5 text-xs bg-green-100 text-green-700 rounded-full">{r.userCount}</span></td>
                <td className="px-4 py-3 text-right">
                  <button onClick={() => openEdit(r)} disabled={openingId !== null}
                    className="text-sm text-blue-600 hover:underline disabled:opacity-50 disabled:no-underline">
                    {openingId === r.id ? 'Loading…' : 'Edit'}
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {modalOpen && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 overflow-y-auto py-4 p-4 pt-10" onClick={(e) => { if (e.target === e.currentTarget) setModalOpen(false) }}>
          <div className="bg-white rounded-lg shadow-xl w-full max-w-3xl p-5 space-y-3 max-h-[85vh] overflow-y-auto">
            <h2 className="text-lg font-bold">{editingId ? 'Edit Role' : 'Add Role'}</h2>
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Role Name</label>
              <input value={formName} onChange={e => setFormName(e.target.value)}
                className="w-full px-3 py-2 border rounded-md text-sm" />
            </div>

            {editingId && (
              <div className="flex gap-2 border-b">
                <button onClick={() => setActiveTab('permissions')}
                  className={`px-4 py-2 text-sm font-medium ${activeTab === 'permissions' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600'}`}>
                  Permissions ({selectedPerms.length})
                </button>
                <button onClick={() => setActiveTab('under-roles')}
                  className={`px-4 py-2 text-sm font-medium ${activeTab === 'under-roles' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-600'}`}>
                  Roles Under Roles ({selectedUnderRoles.length})
                </button>
              </div>
            )}

            {activeTab === 'permissions' && (
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-2">
                  Permissions ({selectedPerms.length} selected)
                </label>
                <div className="border rounded-md max-h-96 overflow-y-auto divide-y">
                  {permGroups.length === 0 && (
                    /* The master list failed to load. The role's own grants are
                       still held in selectedPerms and Save resubmits them intact,
                       but say so — a blank list otherwise reads as "this role has
                       nothing", which is exactly the misreading that precedes an
                       accidental wipe. */
                    <div className="p-3 text-xs text-amber-700 bg-amber-50">
                      Could not load the permission list. Reload the page before editing —
                      saving now keeps this role's {selectedPerms.length} existing permission(s) but you cannot change them here.
                    </div>
                  )}
                  {permGroups.map(g => {
                    const allSelected = g.permissions.every(p => selectedPerms.includes(p))
                    return (
                      <div key={g.module} className="p-3">
                        <label className="flex items-center gap-2 cursor-pointer mb-2">
                          <input type="checkbox" checked={allSelected} onChange={() => toggleModule(g.permissions)}
                            className="rounded border-gray-300 text-blue-600" />
                          <span className="font-medium text-sm text-gray-700 uppercase">{g.module}</span>
                          <span className="text-[10px] text-gray-400">({g.permissions.length})</span>
                        </label>
                        <div className="flex flex-wrap gap-2 ml-6">
                          {g.permissions.map(p => (
                            <label key={p} className="flex items-center gap-1 text-xs cursor-pointer">
                              <input type="checkbox" checked={selectedPerms.includes(p)} onChange={() => togglePerm(p)}
                                className="rounded border-gray-300 text-blue-600 w-3.5 h-3.5" />
                              <span className={selectedPerms.includes(p) ? 'text-blue-700 font-medium' : 'text-gray-500'}>{p.replace(`${g.module}-`, '')}</span>
                            </label>
                          ))}
                        </div>
                      </div>
                    )
                  })}
                </div>
              </div>
            )}

            {activeTab === 'under-roles' && editingId && (
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-2">
                  Select roles that this role can assign to users
                </label>
                <div className="border rounded-md max-h-96 overflow-y-auto p-3 space-y-2">
                  {roles.filter(r => r.id !== editingId).map(r => (
                    <label key={r.id} className="flex items-center gap-2 text-sm cursor-pointer">
                      <input type="checkbox" checked={selectedUnderRoles.includes(r.id)} onChange={e => {
                        setSelectedUnderRoles(prev => e.target.checked ? [...prev, r.id] : prev.filter(id => id !== r.id))
                      }} className="rounded border-gray-300 text-blue-600 w-4 h-4" />
                      <span className={selectedUnderRoles.includes(r.id) ? 'text-blue-700 font-medium' : 'text-gray-600'}>{r.name}</span>
                      <span className="text-[10px] text-gray-400">({r.userCount} users)</span>
                    </label>
                  ))}
                </div>
              </div>
            )}

            <div className="flex justify-end gap-2 pt-2">
              <button onClick={() => setModalOpen(false)} className="px-4 py-2 text-sm border rounded-md">Cancel</button>
              <button onClick={handleSave} disabled={saving || !formName}
                className="px-4 py-2 text-sm bg-blue-600 text-white rounded-md disabled:opacity-50">{saving ? 'Saving...' : 'Save'}</button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
