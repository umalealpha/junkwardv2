import { useState, useEffect } from 'react'
import { useParams, Link } from 'react-router-dom'
import apiClient from '../../api/client'
import { fmtPula } from '../../utils/format'

export default function ProductDetailPage() {
  const { id } = useParams<{ id: string }>()
  const [data, setData] = useState<any>(null)
  const [loading, setLoading] = useState(true)
  const [planModal, setPlanModal] = useState(false)
  const [editingPlanId, setEditingPlanId] = useState<number | null>(null)
  const [planForm, setPlanForm] = useState({ plan: '', sum_assured: '', premium: '', status: 1 })
  const [saving, setSaving] = useState(false)

  const load = () => {
    if (!id) return
    setLoading(true)
    apiClient.get(`/products/${id}/config`).then(r => setData(r.data.data)).catch(() => {}).finally(() => setLoading(false))
  }
  useEffect(() => { load() }, [id])

  async function handleSavePlan() {
    setSaving(true)
    const payload = { ...planForm, sum_assured: Number(planForm.sum_assured) || 0, premium: Number(planForm.premium) || 0 }
    try {
      if (editingPlanId) await apiClient.put(`/product-plans/${editingPlanId}`, payload)
      else await apiClient.post(`/products/${id}/plans`, payload)
      setPlanModal(false); load()
    } catch (e: any) { alert(e.response?.data?.message || 'Failed') }
    finally { setSaving(false) }
  }

  if (loading) return <div className="p-6"><div className="animate-pulse space-y-3">{[1,2,3].map(i => <div key={i} className="h-12 bg-gray-200 rounded" />)}</div></div>
  if (!data) return <div className="p-6 text-red-600">Product not found.</div>

  const p = data.product
  return (
    <div className="p-6 space-y-5">
      <div className="flex items-center gap-3">
        <Link to="/products" className="text-blue-600 hover:underline text-sm">&larr; Products</Link>
        <h1 className="text-2xl font-bold text-gray-800">{p.name}</h1>
        <span className={`px-2 py-0.5 text-xs rounded-full ${p.status ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>{p.status ? 'Active' : 'Inactive'}</span>
      </div>

      {/* Product Info */}
      <div className="bg-white shadow rounded-lg p-5 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
        <div><span className="text-gray-500 text-xs">Product ID</span><p className="font-medium">{p.id}</p></div>
        <div><span className="text-gray-500 text-xs">Slug</span><p className="font-medium">{p.slug || '-'}</p></div>
        <div><span className="text-gray-500 text-xs">KYC Compliance Rule</span><p className="font-medium">{p.kyc_compliance || 'None'}</p></div>
        <div><span className="text-gray-500 text-xs">Product Type</span><p className="font-medium">{p.product_type_id || '-'}</p></div>
      </div>

      {/* Plans */}
      <div className="bg-white shadow rounded-lg p-5">
        <div className="flex items-center justify-between mb-3">
          <h2 className="text-lg font-bold text-gray-800">Plans ({data.plans?.length || 0})</h2>
          <button onClick={() => { setEditingPlanId(null); setPlanForm({ plan: '', sum_assured: '', premium: '', status: 1 }); setPlanModal(true) }}
            className="px-3 py-1.5 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">+ Add Plan</button>
        </div>
        <table className="min-w-full divide-y divide-gray-200 text-sm">
          <thead className="bg-gray-50"><tr>
            <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Plan</th>
            <th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Sum Assured</th>
            <th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Premium</th>
            <th className="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
            <th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
          </tr></thead>
          <tbody className="divide-y divide-gray-200">
            {(data.plans ?? []).map((pl: any) => (
              <tr key={pl.id} className="hover:bg-gray-50">
                <td className="px-4 py-2 font-medium">{pl.plan}</td>
                <td className="px-4 py-2 text-right">{fmtPula(pl.sum_assured)}</td>
                <td className="px-4 py-2 text-right">{fmtPula(pl.premium)}</td>
                <td className="px-4 py-2 text-center"><span className={`inline-block w-3 h-3 rounded-full ${pl.status ? 'bg-green-500' : 'bg-gray-300'}`} /></td>
                <td className="px-4 py-2 text-right">
                  <button onClick={() => { setEditingPlanId(pl.id); setPlanForm({ plan: pl.plan, sum_assured: String(pl.sum_assured || ''), premium: String(pl.premium || ''), status: pl.status }); setPlanModal(true) }} className="text-xs text-blue-600 hover:underline">Edit</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Factors */}
      {data.factors?.length > 0 && (
        <div className="bg-white shadow rounded-lg p-5">
          <h2 className="text-lg font-bold text-gray-800 mb-3">Rating Factors ({data.factors.length})</h2>
          {data.factors.map((f: any) => (
            <div key={f.id} className="mb-3">
              <h3 className="text-sm font-semibold text-gray-700">{f.name || `Factor #${f.id}`}</h3>
              <div className="flex flex-wrap gap-1 mt-1">
                {(f.values ?? []).map((v: any) => (
                  <span key={v.id} className="px-2 py-0.5 text-xs bg-gray-100 rounded">{v.name ?? v.value ?? v.id}</span>
                ))}
              </div>
            </div>
          ))}
        </div>
      )}

      {planModal && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-4 pt-10 overflow-y-auto"
          onClick={(e) => { if (e.target === e.currentTarget) setPlanModal(false) }}>
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md p-5 space-y-2.5 max-h-[85vh] overflow-y-auto">
            <h2 className="text-lg font-bold">{editingPlanId ? 'Edit Plan' : 'Add Plan'}</h2>
            <div><label className="block text-xs font-medium text-gray-500 mb-1">Plan Name</label><input value={planForm.plan} onChange={e => setPlanForm({...planForm, plan: e.target.value})} className="w-full px-3 py-2 border rounded-md text-sm" /></div>
            <div className="grid grid-cols-2 gap-3">
              <div><label className="block text-xs font-medium text-gray-500 mb-1">Sum Assured</label><input type="number" value={planForm.sum_assured} onChange={e => setPlanForm({...planForm, sum_assured: e.target.value})} className="w-full px-3 py-2 border rounded-md text-sm" /></div>
              <div><label className="block text-xs font-medium text-gray-500 mb-1">Premium</label><input type="number" value={planForm.premium} onChange={e => setPlanForm({...planForm, premium: e.target.value})} className="w-full px-3 py-2 border rounded-md text-sm" /></div>
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <button onClick={() => setPlanModal(false)} className="px-4 py-2 text-sm border rounded-md">Cancel</button>
              <button onClick={handleSavePlan} disabled={saving || !planForm.plan} className="px-4 py-2 text-sm bg-blue-600 text-white rounded-md disabled:opacity-50">{saving ? 'Saving...' : 'Save'}</button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
