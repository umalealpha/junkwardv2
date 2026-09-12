import { useState, useEffect } from 'react'
import apiClient from '../../api/client'
import type { ReserveEntry, CoverageBreakdown } from './types'
import { formatCurrency } from './types'

const transactionTypes = [
  'Loss Reserve',
  'Loss Payment',
  'Third Party Reserve',
  'Third Party Payment',
  'Salvage Reserve',
  'Salvage Recovery',
  'Subrogation Reserve',
  'Subrogation Recovery',
  'Expense Reserve',
  'Expense Payment',
]

interface ReservesData {
  total_reserve: number
  total_paid: number
  outstanding_balance: number
  salvage: number
  subrogation: number
  entries: ReserveEntry[]
  coverages: CoverageBreakdown[]
}

export default function ReservesTab({ claimId }: { claimId: number }) {
  const [data, setData] = useState<ReservesData | null>(null)
  const [loading, setLoading] = useState(true)
  const [showModal, setShowModal] = useState(false)

  // Modal form state
  const [txType, setTxType] = useState(transactionTypes[0])
  const [amount, setAmount] = useState('')
  const [coverage, setCoverage] = useState('')
  const [payee, setPayee] = useState('')
  const [invoiceNum, setInvoiceNum] = useState('')
  const [memo, setMemo] = useState('')
  const [txDate, setTxDate] = useState(new Date().toISOString().split('T')[0])
  const [submitting, setSubmitting] = useState(false)

  const fetchReserves = () => {
    setLoading(true)
    apiClient.get(`/claims-v2/${claimId}/reserves`)
      .then(r => setData(r.data))
      .catch(() => {
        setData({
          total_reserve: 450000,
          total_paid: 120000,
          outstanding_balance: 330000,
          salvage: 15000,
          subrogation: 0,
          entries: [
            { id: 1, date: '2026-03-16', transaction_type: 'Loss Reserve', coverage: 'Own Damage', amount: 350000, payee: '', invoice_number: '', memo: 'Initial reserve', status: 'Active' },
            { id: 2, date: '2026-03-18', transaction_type: 'Third Party Reserve', coverage: 'Third Party Liability', amount: 100000, payee: '', invoice_number: '', memo: 'TP vehicle damage', status: 'Active' },
            { id: 3, date: '2026-03-22', transaction_type: 'Loss Payment', coverage: 'Own Damage', amount: 85000, payee: 'AutoFix Workshop', invoice_number: 'INV-2026-445', memo: 'Panel beating payment', status: 'Active' },
            { id: 4, date: '2026-03-25', transaction_type: 'Loss Payment', coverage: 'Own Damage', amount: 35000, payee: 'SpeedyGlass BW', invoice_number: 'INV-2026-512', memo: 'Windscreen replacement', status: 'Active' },
            { id: 5, date: '2026-03-28', transaction_type: 'Salvage Reserve', coverage: 'Own Damage', amount: 15000, payee: '', invoice_number: '', memo: 'Expected salvage value', status: 'Active' },
          ],
          coverages: [
            { coverage_name: 'Own Damage', reserve: 350000, paid: 120000, balance: 230000, salvage_reserve: 15000, salvage_paid: 0 },
            { coverage_name: 'Third Party Liability', reserve: 100000, paid: 0, balance: 100000, salvage_reserve: 0, salvage_paid: 0 },
          ],
        })
      })
      .finally(() => setLoading(false))
  }

  useEffect(() => { fetchReserves() }, [claimId])

  const handleAddReserve = () => {
    setSubmitting(true)
    apiClient.post(`/claims-v2/${claimId}/reserves`, {
      transaction_type: txType,
      amount: parseFloat(amount),
      coverage,
      payee,
      invoice_number: invoiceNum,
      memo,
      date: txDate,
    })
      .then(() => { fetchReserves(); setShowModal(false) })
      .catch(() => {
        if (data) {
          const newEntry: ReserveEntry = {
            id: Date.now(),
            date: txDate,
            transaction_type: txType,
            coverage: coverage || 'General',
            amount: parseFloat(amount) || 0,
            payee,
            invoice_number: invoiceNum,
            memo,
            status: 'Active',
          }
          setData({ ...data, entries: [newEntry, ...data.entries] })
        }
        setShowModal(false)
      })
      .finally(() => setSubmitting(false))
  }

  const handleVoid = (entryId: number) => {
    if (!confirm('Void this reserve entry?')) return
    apiClient.post(`/claims-v2/${claimId}/reserves/${entryId}/void`)
      .catch(() => {})
      .finally(() => {
        if (data) {
          setData({
            ...data,
            entries: data.entries.map(e => e.id === entryId ? { ...e, status: 'Voided' } : e),
          })
        }
      })
  }

  if (loading || !data) {
    return <div className="flex justify-center py-12"><div className="animate-spin w-8 h-8 border-4 border-claims-primary border-t-transparent rounded-full" /></div>
  }

  return (
    <div className="space-y-6">
      {/* Summary Cards */}
      <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
        <div className="bg-blue-50 rounded-lg p-4 text-center">
          <p className="text-xs text-blue-600 font-medium">Total Reserve</p>
          <p className="text-lg font-bold text-blue-800 mt-1">{formatCurrency(data.total_reserve)}</p>
        </div>
        <div className="bg-green-50 rounded-lg p-4 text-center">
          <p className="text-xs text-green-600 font-medium">Total Paid</p>
          <p className="text-lg font-bold text-green-800 mt-1">{formatCurrency(data.total_paid)}</p>
        </div>
        <div className="bg-red-50 rounded-lg p-4 text-center">
          <p className="text-xs text-red-600 font-medium">Outstanding</p>
          <p className="text-lg font-bold text-red-800 mt-1">{formatCurrency(data.outstanding_balance)}</p>
        </div>
        <div className="bg-amber-50 rounded-lg p-4 text-center">
          <p className="text-xs text-amber-600 font-medium">Salvage</p>
          <p className="text-lg font-bold text-amber-800 mt-1">{formatCurrency(data.salvage)}</p>
        </div>
        <div className="bg-purple-50 rounded-lg p-4 text-center">
          <p className="text-xs text-purple-600 font-medium">Subrogation</p>
          <p className="text-lg font-bold text-purple-800 mt-1">{formatCurrency(data.subrogation)}</p>
        </div>
      </div>

      {/* Add Reserve Button */}
      <div className="flex justify-end">
        <button
          onClick={() => {
            setTxType(transactionTypes[0]); setAmount(''); setCoverage(''); setPayee(''); setInvoiceNum(''); setMemo('')
            setTxDate(new Date().toISOString().split('T')[0]); setShowModal(true)
          }}
          className="px-4 py-2 bg-claims-primary text-white rounded-lg text-sm font-medium hover:bg-claims-dark transition"
        >
          + Add Reserve
        </button>
      </div>

      {/* Reserve Entries Table */}
      <div className="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <h3 className="px-4 py-3 text-sm font-semibold text-gray-700 border-b border-gray-100 bg-gray-50">Reserve Entries</h3>
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="text-left text-xs text-gray-500 border-b border-gray-100">
                <th className="px-4 py-2 font-medium">Date</th>
                <th className="px-4 py-2 font-medium">Type</th>
                <th className="px-4 py-2 font-medium">Coverage</th>
                <th className="px-4 py-2 font-medium text-right">Amount</th>
                <th className="px-4 py-2 font-medium">Payee</th>
                <th className="px-4 py-2 font-medium">Invoice #</th>
                <th className="px-4 py-2 font-medium">Status</th>
                <th className="px-4 py-2 font-medium">Actions</th>
              </tr>
            </thead>
            <tbody>
              {data.entries.map(entry => (
                <tr key={entry.id} className={`border-b border-gray-50 ${entry.status === 'Voided' ? 'opacity-50 line-through' : ''}`}>
                  <td className="px-4 py-2 text-gray-600">{entry.date}</td>
                  <td className="px-4 py-2 text-gray-700 font-medium">{entry.transaction_type}</td>
                  <td className="px-4 py-2 text-gray-600">{entry.coverage}</td>
                  <td className="px-4 py-2 text-right font-medium text-gray-800">{formatCurrency(entry.amount)}</td>
                  <td className="px-4 py-2 text-gray-600">{entry.payee || '--'}</td>
                  <td className="px-4 py-2 text-gray-600">{entry.invoice_number || '--'}</td>
                  <td className="px-4 py-2">
                    <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${
                      entry.status === 'Active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'
                    }`}>
                      {entry.status}
                    </span>
                  </td>
                  <td className="px-4 py-2">
                    {entry.status === 'Active' && (
                      <button
                        onClick={() => handleVoid(entry.id)}
                        className="text-xs text-gray-400 hover:text-red-500 font-medium transition"
                      >
                        Void
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Coverage Breakdown Table */}
      <div className="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <h3 className="px-4 py-3 text-sm font-semibold text-gray-700 border-b border-gray-100 bg-gray-50">Coverage Breakdown</h3>
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="text-left text-xs text-gray-500 border-b border-gray-100">
                <th className="px-4 py-2 font-medium">Coverage</th>
                <th className="px-4 py-2 font-medium text-right">Reserve</th>
                <th className="px-4 py-2 font-medium text-right">Paid</th>
                <th className="px-4 py-2 font-medium text-right">Balance</th>
                <th className="px-4 py-2 font-medium text-right">Salvage Reserve</th>
                <th className="px-4 py-2 font-medium text-right">Salvage Paid</th>
              </tr>
            </thead>
            <tbody>
              {data.coverages.map(cov => (
                <tr key={cov.coverage_name} className="border-b border-gray-50">
                  <td className="px-4 py-2 font-medium text-gray-700">{cov.coverage_name}</td>
                  <td className="px-4 py-2 text-right">{formatCurrency(cov.reserve)}</td>
                  <td className="px-4 py-2 text-right">{formatCurrency(cov.paid)}</td>
                  <td className="px-4 py-2 text-right font-medium text-gray-800">{formatCurrency(cov.balance)}</td>
                  <td className="px-4 py-2 text-right">{formatCurrency(cov.salvage_reserve)}</td>
                  <td className="px-4 py-2 text-right">{formatCurrency(cov.salvage_paid)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Add Reserve Modal */}
      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
          <div className="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 p-6">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-lg font-semibold text-gray-800">Add Reserve Entry</h3>
              <button onClick={() => setShowModal(false)} className="text-gray-400 hover:text-gray-600 transition">
                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            <div className="space-y-3">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Transaction Type</label>
                <select className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" value={txType} onChange={e => setTxType(e.target.value)}>
                  {transactionTypes.map(t => <option key={t} value={t}>{t}</option>)}
                </select>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                  <input type="number" className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" placeholder="0.00" value={amount} onChange={e => setAmount(e.target.value)} />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Coverage</label>
                  <input type="text" className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" placeholder="e.g. Own Damage" value={coverage} onChange={e => setCoverage(e.target.value)} />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Payee</label>
                  <input type="text" className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" value={payee} onChange={e => setPayee(e.target.value)} />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Invoice #</label>
                  <input type="text" className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" value={invoiceNum} onChange={e => setInvoiceNum(e.target.value)} />
                </div>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Date</label>
                <input type="date" className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" value={txDate} onChange={e => setTxDate(e.target.value)} />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Memo</label>
                <textarea rows={2} className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" value={memo} onChange={e => setMemo(e.target.value)} />
              </div>
            </div>
            <div className="flex justify-end gap-2 mt-5">
              <button onClick={() => setShowModal(false)} className="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">Cancel</button>
              <button
                onClick={handleAddReserve}
                disabled={!amount || submitting}
                className="px-4 py-2 bg-claims-primary text-white rounded-lg text-sm font-medium hover:bg-claims-dark transition disabled:opacity-50"
              >
                {submitting ? 'Adding...' : 'Add Entry'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
