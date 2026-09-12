import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import apiClient from '../../api/client'
import EmptyState from '../../components/common/EmptyState'

interface Agent {
  id: number; name: string; firstName: string; lastName: string
  email: string; agencyId: number | null
  agencyName: string | null
  activePolicies: number; totalCommission: string; createdAt: string
  commission?: boolean; isGraphiteLogin?: boolean; active?: number
  cellphone?: string; dob?: string; omang?: string; pin?: string; address?: string; passport?: string; gender?: string
  departmentId?: number; role?: string[]; isReportLogin?: boolean; bypass500k?: boolean
}

interface Agency { id: number; name: string }
interface Department { id: number; name: string }
interface Role { id: number; name: string }

export default function AgentListPage() {
  const [agents, setAgents] = useState<Agent[]>([])
  const [agencies, setAgencies] = useState<Agency[]>([])
  const [departments, setDepartments] = useState<Department[]>([])
  const [roles, setRoles] = useState<Role[]>([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [hasMore, setHasMore] = useState(false)
  const [showModal, setShowModal] = useState(false)
  const [editingAgent, setEditingAgent] = useState<Agent | null>(null)
  const [formLoading, setFormLoading] = useState(false)
  const [formError, setFormError] = useState('')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formData, setFormData] = useState({
    firstName: '', lastName: '', email: '', password: '', confirmPassword: '', agencyId: '', departmentId: '',
    cellphone: '', dob: '', omang: '', pin: '', address: '', passport: '', gender: '',
    commission: false, isGraphiteLogin: false, isReportLogin: false, bypass500k: false, role: [] as string[]
  })

  useEffect(() => {
    fetchAgencies()
    fetchDepartments()
    fetchRoles()
  }, [])

  useEffect(() => {
    fetchAgents()
  }, [search, page])

  const fetchAgencies = async () => {
    try {
      const response = await apiClient.get('/lookups/agencies')
      setAgencies(response.data.agencies || [])
    } catch (error) {
      console.error('Failed to fetch agencies:', error)
    }
  }

  const fetchDepartments = async () => {
    try {
      const response = await apiClient.get('/lookups/departments')
      setDepartments(response.data.departments || [])
    } catch (error) {
      console.error('Failed to fetch departments:', error)
    }
  }

  const fetchRoles = async () => {
    try {
      const response = await apiClient.get('/lookups/roles')
      setRoles(response.data.roles || [])
    } catch (error) {
      console.error('Failed to fetch roles:', error)
    }
  }

  const fetchAgents = async () => {
    try {
      setLoading(true)
      const params: any = { page, per_page: 25 }
      if (search) params.search = search
      const response = await apiClient.get('/agents', { params })
      setAgents(response.data.data ?? [])
      setHasMore(response.data.meta?.has_more ?? false)
    } catch (error) {
      console.error('Failed to fetch agents:', error)
      setAgents([])
    } finally {
      setLoading(false)
    }
  }

  function openCreateModal() {
    setEditingAgent(null)
    setFormData({
      firstName: '', lastName: '', email: '', password: '', confirmPassword: '', agencyId: '', departmentId: '',
      cellphone: '', dob: '', omang: '', pin: '', address: '', passport: '', gender: '',
      commission: false, isGraphiteLogin: false, isReportLogin: false, bypass500k: false, role: []
    })
    setFormError('')
    setShowModal(true)
  }

  function getFieldError(fieldName: string): string {
    const errors = fieldErrors[fieldName]
    return errors ? errors[0] : ''
  }

  async function openEditModal(agent: Agent) {
    setEditingAgent(agent)
    setFormError('')

    // Fetch full agent details including roles and department
    try {
      const response = await apiClient.get(`/agents/${agent.id}`)
      const fullAgent = response.data.data

      setFormData({
        firstName: fullAgent.firstName || '',
        lastName: fullAgent.lastName || '',
        email: fullAgent.email || '',
        password: '',
        confirmPassword: '',
        agencyId: fullAgent.agencyId?.toString() || '',
        departmentId: fullAgent.departmentId?.toString() || '',
        cellphone: fullAgent.cellphone || '',
        dob: fullAgent.dob || '',
        omang: fullAgent.omang || '',
        pin: fullAgent.pin || '',
        address: fullAgent.address || '',
        passport: fullAgent.passport || '',
        gender: fullAgent.gender || '',
        commission: fullAgent.commission || false,
        isGraphiteLogin: fullAgent.isGraphiteLogin || false,
        isReportLogin: fullAgent.isReportLogin || false,
        bypass500k: fullAgent.bypass500k || false,
        role: Array.isArray(fullAgent.role) ? fullAgent.role : (fullAgent.roles || [])
      })
    } catch (error) {
      console.error('Failed to load agent details:', error)
      // Fallback to agent data from list
      setFormData({
        firstName: agent.firstName || '',
        lastName: agent.lastName || '',
        email: agent.email || '',
        password: '',
        confirmPassword: '',
        agencyId: agent.agencyId?.toString() || '',
        departmentId: agent.departmentId?.toString() || '',
        cellphone: agent.cellphone || '',
        dob: agent.dob || '',
        omang: agent.omang || '',
        pin: agent.pin || '',
        address: agent.address || '',
        passport: agent.passport || '',
        gender: agent.gender || '',
        commission: agent.commission || false,
        isGraphiteLogin: agent.isGraphiteLogin || false,
        isReportLogin: agent.isReportLogin || false,
        bypass500k: agent.bypass500k || false,
        role: agent.role || []
      })
    }

    setShowModal(true)
  }

  async function handleSaveAgent() {
    setFormError('')
    setFieldErrors({})

    // Frontend validation. Collect EVERY failing field and flag it inline
    // (same shape as Laravel's 422 `errors` map) rather than bailing on the
    // first one with a banner-only message that pointed at no field.
    const clientErrors: Record<string, string[]> = {}
    if (!formData.firstName.trim()) clientErrors.firstName = ['First Name is required']
    if (!formData.lastName.trim()) clientErrors.lastName = ['Last Name is required']
    if (!formData.email.trim()) clientErrors.email = ['Email is required']
    if (!formData.agencyId) clientErrors.agency_id = ['Agency is required']
    if (!formData.gender) clientErrors.gender = ['Gender is required']

    if (!editingAgent) {
      if (!formData.password) clientErrors.password = ['Password is required for new agents']
      if (!formData.confirmPassword) clientErrors.confirmPassword = ['Confirm Password is required']
      else if (formData.password && formData.password !== formData.confirmPassword) clientErrors.confirmPassword = ['Passwords do not match']
    }

    if (Object.keys(clientErrors).length > 0) {
      setFieldErrors(clientErrors)
      setFormError('Please fix the errors below')
      return
    }

    setFormLoading(true)
    try {
      const payload: any = {
        firstName: formData.firstName.trim(),
        lastName: formData.lastName.trim(),
        email: formData.email.trim(),
        agency_id: parseInt(formData.agencyId),
        cellphone: formData.cellphone.trim() || null,
        dob: formData.dob || null,
        omang: formData.omang || null,
        address: formData.address || null,
        passport: formData.passport || null,
        gender: formData.gender || null,
        commission: formData.commission,
        is_graphite_login: formData.isGraphiteLogin,
        is_report_login: formData.isReportLogin,
        bypass_500k: formData.bypass500k
      }

      if (formData.departmentId) payload.department_id = parseInt(formData.departmentId)
      if (formData.role && formData.role.length > 0) payload.role = formData.role
      if (formData.password) payload.password = formData.password
      if (formData.confirmPassword) payload.confirmPassword = formData.confirmPassword
      if (formData.pin) payload.pin = formData.pin

      if (editingAgent) {
        await apiClient.put(`/agents/${editingAgent.id}`, payload)
      } else {
        await apiClient.post('/agents', payload)
      }
      setShowModal(false)
      fetchAgents()
    } catch (err: any) {
      // Handle Laravel validation errors (422 status code)
      if (err?.response?.status === 422 && err?.response?.data?.errors) {
        setFieldErrors(err.response.data.errors)
        setFormError('Please fix the errors below')
      } else if (err?.response?.data?.message) {
        setFormError(err.response.data.message)
      } else if (err?.response?.data?.error) {
        setFormError(err.response.data.error)
      } else {
        setFormError('Failed to save agent')
      }
    } finally {
      setFormLoading(false)
    }
  }

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-800">Agents</h1>
        <div className="flex gap-2">
          <input value={search} onChange={e => { setSearch(e.target.value); setPage(1) }}
            placeholder="Search agents..." className="px-3 py-1.5 border rounded-md text-sm w-64" />
          <button onClick={openCreateModal} className="px-4 py-1.5 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">+ Add Agent</button>
        </div>
      </div>

      <div className="bg-white shadow rounded-lg overflow-hidden">
        <table className="min-w-full divide-y divide-gray-200 text-sm">
          <thead className="bg-gray-50">
            <tr>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Agent</th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Agency</th>
              <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Active Policies</th>
              <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Commission</th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
              <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-200">
            {loading && <tr><td colSpan={7} className="px-4 py-8 text-center text-gray-400">Loading...</td></tr>}
            {!loading && agents.length === 0 && <tr><td colSpan={7} className="p-0"><EmptyState compact title="No agents found" description="Try adjusting your filters or search terms." /></td></tr>}
            {agents.map(a => (
              <tr key={a.id} className="hover:bg-gray-50">
                <td className="px-4 py-3">
                  <Link to={`/agents/${a.id}`} className="text-blue-600 hover:underline font-medium">{a.name}</Link>
                </td>
                <td className="px-4 py-3 text-xs text-gray-500">
                  <div>{a.email}</div>
                  {a.cellphone && <div>{a.cellphone}</div>}
                </td>
                <td className="px-4 py-3">
                  {a.agencyName ? <span className="px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-700 font-medium">{a.agencyName}</span> : '-'}
                </td>
                <td className="px-4 py-3 text-right font-medium">{a.activePolicies}</td>
                <td className="px-4 py-3 text-right font-medium">{a.commission}</td>
                <td className="px-4 py-3">
                  <span className={`px-2 py-1 text-xs rounded-full font-medium ${a.active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'}`}>
                    {a.active ? 'Active' : 'Inactive'}
                  </span>
                </td>
                <td className="px-4 py-3 text-center">
                  <button onClick={() => openEditModal(a)} className="text-xs text-blue-600 hover:underline">Edit</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="flex justify-between items-center">
        <button disabled={page <= 1} onClick={() => setPage(p => p - 1)} className="px-3 py-1.5 text-sm border rounded-md disabled:opacity-30">Previous</button>
        <span className="text-sm text-gray-500">Page {page}</span>
        <button disabled={!hasMore} onClick={() => setPage(p => p + 1)} className="px-3 py-1.5 text-sm border rounded-md disabled:opacity-30">Next</button>
      </div>

      {showModal && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 overflow-y-auto py-4 p-4 pt-10" onClick={(e) => { if (e.target === e.currentTarget) setShowModal(false) }}>
          <div className="bg-white rounded-lg shadow-xl w-full max-w-3xl p-5 space-y-3 max-h-[85vh] overflow-y-auto">
            <h2 className="text-lg font-bold">{editingAgent ? 'Edit Agent' : 'Add Agent'}</h2>
            {formError && <div className="p-3 text-sm text-red-700 bg-red-50 rounded-md">{formError}</div>}
            <div className="grid grid-cols-2 gap-4 max-h-96 overflow-y-auto pr-2">
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">First Name *</label>
                <input type="text" value={formData.firstName} onChange={e => setFormData({ ...formData, firstName: e.target.value })}
                  className={`w-full px-3 py-2 border rounded-md text-sm ${getFieldError('firstName') ? 'border-red-500' : ''}`} />
                {getFieldError('firstName') && <p className="text-xs text-red-600 mt-1">{getFieldError('firstName')}</p>}
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">Last Name *</label>
                <input type="text" value={formData.lastName} onChange={e => setFormData({ ...formData, lastName: e.target.value })}
                  className={`w-full px-3 py-2 border rounded-md text-sm ${getFieldError('lastName') ? 'border-red-500' : ''}`} />
                {getFieldError('lastName') && <p className="text-xs text-red-600 mt-1">{getFieldError('lastName')}</p>}
              </div>
              <div className="col-span-2">
                <label className="block text-xs font-medium text-gray-600 mb-1">Email *</label>
                <input type="email" value={formData.email} onChange={e => setFormData({ ...formData, email: e.target.value })}
                  className={`w-full px-3 py-2 border rounded-md text-sm ${getFieldError('email') ? 'border-red-500' : ''}`} />
                {getFieldError('email') && <p className="text-xs text-red-600 mt-1">{getFieldError('email')}</p>}
              </div>
              {!editingAgent && (
                <div>
                  <label className="block text-xs font-medium text-gray-600 mb-1">Password *</label>
                  <input type="password" value={formData.password} onChange={e => setFormData({ ...formData, password: e.target.value })}
                    className={`w-full px-3 py-2 border rounded-md text-sm ${getFieldError('password') ? 'border-red-500' : ''}`} />
                  {getFieldError('password') && <p className="text-xs text-red-600 mt-1">{getFieldError('password')}</p>}
                </div>
              )}
              {!editingAgent && (
                <div>
                  <label className="block text-xs font-medium text-gray-600 mb-1">Confirm Password *</label>
                  <input type="password" value={formData.confirmPassword} onChange={e => setFormData({ ...formData, confirmPassword: e.target.value })}
                    className={`w-full px-3 py-2 border rounded-md text-sm ${getFieldError('confirmPassword') ? 'border-red-500' : ''}`} />
                  {getFieldError('confirmPassword') && <p className="text-xs text-red-600 mt-1">{getFieldError('confirmPassword')}</p>}
                </div>
              )}
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">Agency *</label>
                <select value={formData.agencyId} onChange={e => setFormData({ ...formData, agencyId: e.target.value })}
                  className={`w-full px-3 py-2 border rounded-md text-sm ${getFieldError('agency_id') ? 'border-red-500' : ''}`}>
                  <option value="">Select Agency</option>
                  {agencies.map(ag => <option key={ag.id} value={ag.id}>{ag.name}</option>)}
                </select>
                {getFieldError('agency_id') && <p className="text-xs text-red-600 mt-1">{getFieldError('agency_id')}</p>}
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">Department</label>
                <select value={formData.departmentId} onChange={e => setFormData({ ...formData, departmentId: e.target.value })}
                  className={`w-full px-3 py-2 border rounded-md text-sm ${getFieldError('department_id') ? 'border-red-500' : ''}`}>
                  <option value="">Select Department</option>
                  {departments.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
                </select>
                {getFieldError('department_id') && <p className="text-xs text-red-600 mt-1">{getFieldError('department_id')}</p>}
              </div>
              <div className="col-span-2">
                <label className="block text-xs font-medium text-gray-600 mb-1">Role(s)</label>
                <div className="grid grid-cols-2 gap-2 border p-2 rounded-md">
                  {roles.map(r => (
                    <label key={r.id} className="flex items-center gap-2 text-sm">
                      <input type="checkbox" checked={formData.role.includes(r.name)}
                        onChange={e => {
                          if (e.target.checked) {
                            setFormData({ ...formData, role: [...formData.role, r.name] })
                          } else {
                            setFormData({ ...formData, role: formData.role.filter(x => x !== r.name) })
                          }
                        }} className="rounded border-gray-300 w-3 h-3" />
                      <span>{r.name}</span>
                    </label>
                  ))}
                </div>
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">Mobile No.</label>
                <input type="text" value={formData.cellphone} onChange={e => setFormData({ ...formData, cellphone: e.target.value })}
                  className={`w-full px-3 py-2 border rounded-md text-sm ${getFieldError('cellphone') ? 'border-red-500' : ''}`} placeholder="Enter mobile no." />
                {getFieldError('cellphone') && <p className="text-xs text-red-600 mt-1">{getFieldError('cellphone')}</p>}
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">Date of Birth</label>
                <input type="date" value={formData.dob} onChange={e => setFormData({ ...formData, dob: e.target.value })}
                  className={`w-full px-3 py-2 border rounded-md text-sm ${getFieldError('dob') ? 'border-red-500' : ''}`} />
                {getFieldError('dob') && <p className="text-xs text-red-600 mt-1">{getFieldError('dob')}</p>}
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">OMANG</label>
                <input type="text" value={formData.omang} onChange={e => setFormData({ ...formData, omang: e.target.value })}
                  className={`w-full px-3 py-2 border rounded-md text-sm ${getFieldError('omang') ? 'border-red-500' : ''}`} placeholder="Enter Omang" maxLength={9} />
                {getFieldError('omang') && <p className="text-xs text-red-600 mt-1">{getFieldError('omang')}</p>}
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">Address</label>
                <input type="text" value={formData.address} onChange={e => setFormData({ ...formData, address: e.target.value })}
                  className={`w-full px-3 py-2 border rounded-md text-sm ${getFieldError('address') ? 'border-red-500' : ''}`} placeholder="Enter address" />
                {getFieldError('address') && <p className="text-xs text-red-600 mt-1">{getFieldError('address')}</p>}
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">Passport</label>
                <input type="text" value={formData.passport} onChange={e => setFormData({ ...formData, passport: e.target.value })}
                  className={`w-full px-3 py-2 border rounded-md text-sm ${getFieldError('passport') ? 'border-red-500' : ''}`} placeholder="Enter passport number" />
                {getFieldError('passport') && <p className="text-xs text-red-600 mt-1">{getFieldError('passport')}</p>}
              </div>
              <div>
                <label className="block text-xs font-medium text-ink-muted mb-1">Gender *</label>
                <select value={formData.gender} onChange={e => setFormData({ ...formData, gender: e.target.value })}
                  className={`w-full px-3 py-2 border rounded-md text-sm ${getFieldError('gender') ? 'border-red-500' : ''}`}>
                  <option value="">Select Gender</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                </select>
                {getFieldError('gender') && <p className="text-xs text-red-600 mt-1">{getFieldError('gender')}</p>}
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">PIN</label>
                <input type="text" value={formData.pin} onChange={e => setFormData({ ...formData, pin: e.target.value })}
                  placeholder="4-digit PIN" className={`w-full px-3 py-2 border rounded-md text-sm ${getFieldError('pin') ? 'border-red-500' : ''}`} maxLength={4} />
                {getFieldError('pin') && <p className="text-xs text-red-600 mt-1">{getFieldError('pin')}</p>}
              </div>
              <div className="flex items-center gap-2">
                <input type="checkbox" checked={formData.commission} onChange={e => setFormData({ ...formData, commission: e.target.checked })}
                  className="rounded border-gray-300 w-4 h-4" />
                <label className="text-sm font-medium text-gray-600">Commission Enabled</label>
              </div>
              <div className="flex items-center gap-2">
                <input type="checkbox" checked={formData.isGraphiteLogin} onChange={e => setFormData({ ...formData, isGraphiteLogin: e.target.checked })}
                  className="rounded border-gray-300 w-4 h-4" />
                <label className="text-sm font-medium text-gray-600">Graphite Login</label>
              </div>
              <div className="flex items-center gap-2">
                <input type="checkbox" checked={formData.isReportLogin} onChange={e => setFormData({ ...formData, isReportLogin: e.target.checked })}
                  className="rounded border-gray-300 w-4 h-4" />
                <label className="text-sm font-medium text-gray-600">Report Login</label>
              </div>
              <div className="flex items-center gap-2">
                <input type="checkbox" checked={formData.bypass500k} onChange={e => setFormData({ ...formData, bypass500k: e.target.checked })}
                  className="rounded border-gray-300 w-4 h-4" />
                <label className="text-sm font-medium text-gray-600">Bypass 500k</label>
              </div>
            </div>
            <div className="flex justify-end gap-2 pt-4">
              <button onClick={() => setShowModal(false)} className="px-4 py-2 text-sm border rounded-md">Cancel</button>
              <button onClick={handleSaveAgent} disabled={formLoading}
                className="px-4 py-2 text-sm bg-blue-600 text-white rounded-md disabled:opacity-50">{formLoading ? 'Saving...' : 'Save'}</button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
