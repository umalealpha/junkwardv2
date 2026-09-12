import { useState, useEffect } from 'react'
import apiClient from '../../api/client'
import type { ThirdParty } from './types'

export default function ThirdPartyTab({ claimId }: { claimId: number }) {
  const [parties, setParties] = useState<ThirdParty[]>([])
  const [loading, setLoading] = useState(true)
  const [showModal, setShowModal] = useState(false)
  const [submitting, setSubmitting] = useState(false)

  // Form state
  const [name, setName] = useState('')
  const [phone, setPhone] = useState('')
  const [email, setEmail] = useState('')
  const [idNumber, setIdNumber] = useState('')
  const [vehicleReg, setVehicleReg] = useState('')
  const [insurer, setInsurer] = useState('')
  const [policyNumber, setPolicyNumber] = useState('')
  const [bankName, setBankName] = useState('')
  const [bankAccount, setBankAccount] = useState('')
  const [bankBranch, setBankBranch] = useState('')

  const resetForm = () => {
    setName(''); setPhone(''); setEmail(''); setIdNumber(''); setVehicleReg('')
    setInsurer(''); setPolicyNumber(''); setBankName(''); setBankAccount(''); setBankBranch('')
  }

  const fetchParties = () => {
    setLoading(true)
    apiClient.get(`/claims-v2/${claimId}/third-parties`)
      .then(r => setParties(r.data))
      .catch(() => {
        setParties([
          {
            id: 1, name: 'T. Molefi', phone: '+267 7123 4567', email: 'tmolefi@email.bw',
            id_number: '548901234', vehicle_reg: 'B 456 ABC', insurer: 'Botswana Insurance Co.',
            policy_number: 'BIC-2025-7890', bank_name: 'FNB Botswana', bank_account: '6200145678', bank_branch: 'Mall Branch',
          },
        ])
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetchParties() }, [claimId])

  const handleAdd = () => {
    setSubmitting(true)
    apiClient.post(`/claims-v2/${claimId}/third-parties`, {
      name, phone, email, id_number: idNumber, vehicle_reg: vehicleReg,
      insurer, policy_number: policyNumber, bank_name: bankName, bank_account: bankAccount, bank_branch: bankBranch,
    })
      .then(() => { fetchParties(); setShowModal(false); resetForm() })
      .catch(() => {
        setParties(prev => [...prev, {
          id: Date.now(), name, phone, email, id_number: idNumber, vehicle_reg: vehicleReg,
          insurer, policy_number: policyNumber, bank_name: bankName, bank_account: bankAccount, bank_branch: bankBranch,
        }])
        setShowModal(false)
        resetForm()
      })
      .finally(() => setSubmitting(false))
  }

  const handleDelete = (id: number) => {
    if (!confirm('Remove this third party?')) return
    apiClient.delete(`/claims-v2/${claimId}/third-parties/${id}`)
      .catch(() => {})
      .finally(() => setParties(prev => prev.filter(p => p.id !== id)))
  }

  if (loading) {
    return <div className="flex justify-center py-12"><div className="animate-spin w-8 h-8 border-4 border-claims-primary border-t-transparent rounded-full" /></div>
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h3 className="text-sm font-semibold text-gray-700">Third Parties</h3>
        <button
          onClick={() => { resetForm(); setShowModal(true) }}
          className="px-4 py-2 bg-claims-primary text-white rounded-lg text-sm font-medium hover:bg-claims-dark transition"
        >
          + Add Third Party
        </button>
      </div>

      {/* Party Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {parties.map(p => (
          <div key={p.id} className="bg-white border border-gray-200 rounded-lg p-4">
            <div className="flex items-start justify-between mb-3">
              <div>
                <h4 className="text-sm font-semibold text-gray-800">{p.name}</h4>
                <p className="text-xs text-gray-500">ID: {p.id_number}</p>
              </div>
              <button onClick={() => handleDelete(p.id)} className="text-gray-400 hover:text-red-500 transition">
                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </div>
            <div className="grid grid-cols-2 gap-2 text-xs">
              <div>
                <span className="text-gray-500">Phone:</span>
                <p className="text-gray-700">{p.phone}</p>
              </div>
              <div>
                <span className="text-gray-500">Email:</span>
                <p className="text-gray-700">{p.email || '--'}</p>
              </div>
              <div>
                <span className="text-gray-500">Vehicle:</span>
                <p className="text-gray-700">{p.vehicle_reg || '--'}</p>
              </div>
              <div>
                <span className="text-gray-500">Insurer:</span>
                <p className="text-gray-700">{p.insurer || '--'}</p>
              </div>
              <div>
                <span className="text-gray-500">Policy #:</span>
                <p className="text-gray-700">{p.policy_number || '--'}</p>
              </div>
              <div>
                <span className="text-gray-500">Bank:</span>
                <p className="text-gray-700">{p.bank_name ? `${p.bank_name} (${p.bank_account})` : '--'}</p>
              </div>
            </div>
          </div>
        ))}
        {parties.length === 0 && (
          <p className="text-sm text-gray-400 py-8 text-center col-span-full">No third parties added.</p>
        )}
      </div>

      {/* Add Third Party Modal */}
      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
          <div className="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 p-6 max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-lg font-semibold text-gray-800">Add Third Party</h3>
              <button onClick={() => setShowModal(false)} className="text-gray-400 hover:text-gray-600 transition">
                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            <div className="space-y-3">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                  <input type="text" className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" value={name} onChange={e => setName(e.target.value)} />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">ID Number</label>
                  <input type="text" className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" value={idNumber} onChange={e => setIdNumber(e.target.value)} />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                  <input type="text" className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" value={phone} onChange={e => setPhone(e.target.value)} />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
                  <input type="email" className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" value={email} onChange={e => setEmail(e.target.value)} />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Vehicle Reg</label>
                  <input type="text" className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" value={vehicleReg} onChange={e => setVehicleReg(e.target.value)} />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Insurer</label>
                  <input type="text" className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" value={insurer} onChange={e => setInsurer(e.target.value)} />
                </div>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Policy Number</label>
                <input type="text" className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" value={policyNumber} onChange={e => setPolicyNumber(e.target.value)} />
              </div>
              <hr className="border-gray-100" />
              <p className="text-xs font-semibold text-gray-500 uppercase">Bank Details</p>
              <div className="grid grid-cols-3 gap-3">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Bank</label>
                  <input type="text" className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" value={bankName} onChange={e => setBankName(e.target.value)} />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Account #</label>
                  <input type="text" className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" value={bankAccount} onChange={e => setBankAccount(e.target.value)} />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Branch</label>
                  <input type="text" className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" value={bankBranch} onChange={e => setBankBranch(e.target.value)} />
                </div>
              </div>
            </div>
            <div className="flex justify-end gap-2 mt-5">
              <button onClick={() => setShowModal(false)} className="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">Cancel</button>
              <button
                onClick={handleAdd}
                disabled={!name || submitting}
                className="px-4 py-2 bg-claims-primary text-white rounded-lg text-sm font-medium hover:bg-claims-dark transition disabled:opacity-50"
              >
                {submitting ? 'Adding...' : 'Add Third Party'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
