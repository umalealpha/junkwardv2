import { useState, useEffect } from 'react'
import apiClient from '../../api/client'
import type { Assessment } from './types'
import { formatCurrency } from './types'

export default function AssessmentTab({ claimId }: { claimId: number }) {
  const [assessment, setAssessment] = useState<Assessment | null>(null)
  const [loading, setLoading] = useState(true)
  const [editing, setEditing] = useState(false)
  const [saving, setSaving] = useState(false)

  const [assessorName, setAssessorName] = useState('')
  const [assignedDate, setAssignedDate] = useState('')
  const [valuationAmount, setValuationAmount] = useState('')
  const [notes, setNotes] = useState('')
  const [reportFile, setReportFile] = useState<File | null>(null)
  const [quotationsFile, setQuotationsFile] = useState<File | null>(null)

  const fetchAssessment = () => {
    setLoading(true)
    apiClient.get(`/claims-v2/${claimId}/assessment`)
      .then(r => {
        setAssessment(r.data)
        populateForm(r.data)
      })
      .catch(() => {
        const mock: Assessment = {
          assessor_name: 'M. Kgositsile',
          assigned_date: '2026-03-18',
          valuation_amount: 320000,
          notes: 'Vehicle inspected at AutoFix Workshop. Front and side panel damage confirmed. Mechanical components appear undamaged. Recommend repair over write-off.',
          report_url: '#',
          quotations_url: '#',
        }
        setAssessment(mock)
        populateForm(mock)
      })
      .finally(() => setLoading(false))
  }

  const populateForm = (a: Assessment) => {
    setAssessorName(a.assessor_name || '')
    setAssignedDate(a.assigned_date || '')
    setValuationAmount(String(a.valuation_amount || ''))
    setNotes(a.notes || '')
  }

  useEffect(() => { fetchAssessment() }, [claimId])

  const handleSave = () => {
    setSaving(true)
    const formData = new FormData()
    formData.append('assessor_name', assessorName)
    formData.append('assigned_date', assignedDate)
    formData.append('valuation_amount', valuationAmount)
    formData.append('notes', notes)
    if (reportFile) formData.append('report', reportFile)
    if (quotationsFile) formData.append('quotations', quotationsFile)

    apiClient.post(`/claims-v2/${claimId}/assessment`, formData, { headers: { 'Content-Type': 'multipart/form-data' } })
      .then(() => { fetchAssessment(); setEditing(false) })
      .catch(() => {
        setAssessment({
          assessor_name: assessorName,
          assigned_date: assignedDate,
          valuation_amount: parseFloat(valuationAmount) || 0,
          notes,
          report_url: reportFile ? '#' : assessment?.report_url,
          quotations_url: quotationsFile ? '#' : assessment?.quotations_url,
        })
        setEditing(false)
      })
      .finally(() => setSaving(false))
  }

  if (loading) {
    return <div className="flex justify-center py-12"><div className="animate-spin w-8 h-8 border-4 border-claims-primary border-t-transparent rounded-full" /></div>
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h3 className="text-sm font-semibold text-gray-700">Assessment Details</h3>
        {!editing ? (
          <button
            onClick={() => setEditing(true)}
            className="px-3 py-1.5 text-sm font-medium text-claims-primary hover:bg-claims-light/50 rounded-lg transition"
          >
            Edit
          </button>
        ) : (
          <div className="flex gap-2">
            <button onClick={() => { if (assessment) populateForm(assessment); setEditing(false) }} className="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-800 transition">Cancel</button>
            <button onClick={handleSave} disabled={saving} className="px-3 py-1.5 text-sm font-medium bg-claims-primary text-white rounded-lg hover:bg-claims-dark transition disabled:opacity-50">
              {saving ? 'Saving...' : 'Save'}
            </button>
          </div>
        )}
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div className="bg-gray-50 rounded-lg p-4">
          <label className="block text-xs font-semibold text-gray-500 uppercase mb-1">Assessor Name</label>
          {editing ? (
            <input type="text" className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" value={assessorName} onChange={e => setAssessorName(e.target.value)} />
          ) : (
            <p className="text-sm font-medium text-gray-800">{assessment?.assessor_name || '--'}</p>
          )}
        </div>
        <div className="bg-gray-50 rounded-lg p-4">
          <label className="block text-xs font-semibold text-gray-500 uppercase mb-1">Assigned Date</label>
          {editing ? (
            <input type="date" className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" value={assignedDate} onChange={e => setAssignedDate(e.target.value)} />
          ) : (
            <p className="text-sm font-medium text-gray-800">{assessment?.assigned_date || '--'}</p>
          )}
        </div>
        <div className="bg-gray-50 rounded-lg p-4">
          <label className="block text-xs font-semibold text-gray-500 uppercase mb-1">Valuation Amount</label>
          {editing ? (
            <input type="number" className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" value={valuationAmount} onChange={e => setValuationAmount(e.target.value)} />
          ) : (
            <p className="text-sm font-medium text-gray-800">{assessment?.valuation_amount ? formatCurrency(assessment.valuation_amount) : '--'}</p>
          )}
        </div>
      </div>

      <div className="bg-gray-50 rounded-lg p-4">
        <label className="block text-xs font-semibold text-gray-500 uppercase mb-1">Notes</label>
        {editing ? (
          <textarea rows={4} className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" value={notes} onChange={e => setNotes(e.target.value)} />
        ) : (
          <p className="text-sm text-gray-700">{assessment?.notes || 'No notes.'}</p>
        )}
      </div>

      {/* File Uploads / Downloads */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div className="bg-white border border-gray-200 rounded-lg p-4">
          <h4 className="text-xs font-semibold text-gray-500 uppercase mb-2">Assessment Report</h4>
          {editing ? (
            <label className="flex items-center gap-2 px-4 py-2 border border-dashed border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50 transition">
              <svg className="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
              </svg>
              <span className="text-sm text-gray-600">{reportFile ? reportFile.name : 'Upload report...'}</span>
              <input type="file" className="hidden" onChange={e => e.target.files && setReportFile(e.target.files[0])} />
            </label>
          ) : assessment?.report_url ? (
            <a href={assessment.report_url} target="_blank" rel="noopener noreferrer" className="text-sm text-claims-primary hover:text-claims-dark font-medium">
              Download Report
            </a>
          ) : (
            <p className="text-sm text-gray-400">Not uploaded</p>
          )}
        </div>
        <div className="bg-white border border-gray-200 rounded-lg p-4">
          <h4 className="text-xs font-semibold text-gray-500 uppercase mb-2">Quotations / Parts</h4>
          {editing ? (
            <label className="flex items-center gap-2 px-4 py-2 border border-dashed border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50 transition">
              <svg className="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
              </svg>
              <span className="text-sm text-gray-600">{quotationsFile ? quotationsFile.name : 'Upload quotations...'}</span>
              <input type="file" className="hidden" onChange={e => e.target.files && setQuotationsFile(e.target.files[0])} />
            </label>
          ) : assessment?.quotations_url ? (
            <a href={assessment.quotations_url} target="_blank" rel="noopener noreferrer" className="text-sm text-claims-primary hover:text-claims-dark font-medium">
              Download Quotations
            </a>
          ) : (
            <p className="text-sm text-gray-400">Not uploaded</p>
          )}
        </div>
      </div>
    </div>
  )
}
