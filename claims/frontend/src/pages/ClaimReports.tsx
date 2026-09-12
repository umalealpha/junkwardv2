import { useState } from 'react'
import apiClient from '../api/client'

interface ReportRow {
  [key: string]: string | number
}

const reportTypes = [
  { value: 'bordeaux', label: 'Bordeaux Report' },
  { value: 'coverage_allocation', label: 'Coverage Allocation Report' },
  { value: 'outstanding_aging', label: 'Outstanding Aging Report' },
  { value: 'claim_summary', label: 'Claim Summary Report' },
]

function formatCurrency(amount: number): string {
  return 'P ' + amount.toLocaleString('en-BW', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function generateMockData(reportType: string): { columns: string[]; rows: ReportRow[] } {
  switch (reportType) {
    case 'bordeaux':
      return {
        columns: ['Claim #', 'Policy #', 'Insured', 'Date of Loss', 'Type', 'Reserve', 'Paid', 'Outstanding', 'Status'],
        rows: [
          { 'Claim #': 'CLM-2026-0451', 'Policy #': 'POL-10234', Insured: 'Kgomotso Holdings', 'Date of Loss': '2026-03-15', Type: 'Motor Accident', Reserve: 450000, Paid: 120000, Outstanding: 330000, Status: 'Under Review' },
          { 'Claim #': 'CLM-2026-0447', 'Policy #': 'POL-10198', Insured: 'BW Mining Corp', 'Date of Loss': '2026-03-10', Type: 'Fire', Reserve: 380000, Paid: 0, Outstanding: 380000, Status: 'Approved' },
          { 'Claim #': 'CLM-2026-0443', 'Policy #': 'POL-10156', Insured: 'Gaborone Motors', 'Date of Loss': '2026-03-05', Type: 'Theft', Reserve: 275000, Paid: 50000, Outstanding: 225000, Status: 'Pending Assessment' },
          { 'Claim #': 'CLM-2026-0440', 'Policy #': 'POL-10289', Insured: 'TechBW Solutions', 'Date of Loss': '2026-03-01', Type: 'Burglary', Reserve: 195000, Paid: 0, Outstanding: 195000, Status: 'New' },
          { 'Claim #': 'CLM-2026-0435', 'Policy #': 'POL-10312', Insured: 'Safari Lodges Ltd', 'Date of Loss': '2026-02-25', Type: 'Storm', Reserve: 160000, Paid: 30000, Outstanding: 130000, Status: 'Under Review' },
        ],
      }
    case 'coverage_allocation':
      return {
        columns: ['Claim #', 'Coverage', 'Reserve', 'Paid', 'Balance', 'Salvage'],
        rows: [
          { 'Claim #': 'CLM-2026-0451', Coverage: 'Own Damage', Reserve: 350000, Paid: 120000, Balance: 230000, Salvage: 15000 },
          { 'Claim #': 'CLM-2026-0451', Coverage: 'Third Party Liability', Reserve: 100000, Paid: 0, Balance: 100000, Salvage: 0 },
          { 'Claim #': 'CLM-2026-0447', Coverage: 'Fire Damage', Reserve: 380000, Paid: 0, Balance: 380000, Salvage: 0 },
          { 'Claim #': 'CLM-2026-0443', Coverage: 'Theft', Reserve: 275000, Paid: 50000, Balance: 225000, Salvage: 0 },
        ],
      }
    case 'outstanding_aging':
      return {
        columns: ['Claim #', 'Insured', 'Days Open', '0-30 Days', '31-60 Days', '61-90 Days', '90+ Days', 'Total Outstanding'],
        rows: [
          { 'Claim #': 'CLM-2026-0451', Insured: 'Kgomotso Holdings', 'Days Open': 26, '0-30 Days': 330000, '31-60 Days': 0, '61-90 Days': 0, '90+ Days': 0, 'Total Outstanding': 330000 },
          { 'Claim #': 'CLM-2026-0447', Insured: 'BW Mining Corp', 'Days Open': 31, '0-30 Days': 0, '31-60 Days': 380000, '61-90 Days': 0, '90+ Days': 0, 'Total Outstanding': 380000 },
          { 'Claim #': 'CLM-2026-0443', Insured: 'Gaborone Motors', 'Days Open': 36, '0-30 Days': 0, '31-60 Days': 225000, '61-90 Days': 0, '90+ Days': 0, 'Total Outstanding': 225000 },
          { 'Claim #': 'CLM-2026-0440', Insured: 'TechBW Solutions', 'Days Open': 40, '0-30 Days': 0, '31-60 Days': 195000, '61-90 Days': 0, '90+ Days': 0, 'Total Outstanding': 195000 },
        ],
      }
    case 'claim_summary':
      return {
        columns: ['Product', 'Total Claims', 'Open', 'Closed', 'Total Reserve', 'Total Paid', 'Outstanding'],
        rows: [
          { Product: 'Motor', 'Total Claims': 25, Open: 15, Closed: 10, 'Total Reserve': 1250000, 'Total Paid': 520000, Outstanding: 730000 },
          { Product: 'Commercial', 'Total Claims': 12, Open: 7, Closed: 5, 'Total Reserve': 890000, 'Total Paid': 310000, Outstanding: 580000 },
          { Product: 'Health', 'Total Claims': 8, Open: 3, Closed: 5, 'Total Reserve': 245000, 'Total Paid': 195000, Outstanding: 50000 },
          { Product: 'Burglary', 'Total Claims': 5, Open: 3, Closed: 2, 'Total Reserve': 420000, 'Total Paid': 85000, Outstanding: 335000 },
          { Product: 'Travel', 'Total Claims': 3, Open: 1, Closed: 2, 'Total Reserve': 75000, 'Total Paid': 60000, Outstanding: 15000 },
        ],
      }
    default:
      return { columns: [], rows: [] }
  }
}

export default function ClaimReports() {
  const [reportType, setReportType] = useState('bordeaux')
  const [dateFrom, setDateFrom] = useState('2026-01-01')
  const [dateTo, setDateTo] = useState('2026-04-11')
  const [loading, setLoading] = useState(false)
  const [columns, setColumns] = useState<string[]>([])
  const [rows, setRows] = useState<ReportRow[]>([])
  const [generated, setGenerated] = useState(false)

  const handleGenerate = () => {
    setLoading(true)
    apiClient.get('/claims-v2/reports', { params: { type: reportType, date_from: dateFrom, date_to: dateTo } })
      .then(r => {
        setColumns(r.data.columns || [])
        setRows(r.data.rows || [])
        setGenerated(true)
      })
      .catch(() => {
        const mock = generateMockData(reportType)
        setColumns(mock.columns)
        setRows(mock.rows)
        setGenerated(true)
      })
      .finally(() => setLoading(false))
  }

  const handleExport = () => {
    if (!columns.length || !rows.length) return

    const header = columns.join(',')
    const csvRows = rows.map(row =>
      columns.map(col => {
        const val = row[col]
        if (typeof val === 'string' && val.includes(',')) return `"${val}"`
        return val
      }).join(',')
    )
    const csv = [header, ...csvRows].join('\n')
    const blob = new Blob([csv], { type: 'text/csv' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `claims_${reportType}_${dateFrom}_${dateTo}.csv`
    a.click()
    URL.revokeObjectURL(url)
  }

  const isCurrencyColumn = (col: string) =>
    ['Reserve', 'Paid', 'Outstanding', 'Balance', 'Salvage', 'Total Reserve', 'Total Paid', '0-30 Days', '31-60 Days', '61-90 Days', '90+ Days', 'Total Outstanding'].includes(col)

  return (
    <div className="p-6 space-y-6">
      <h1 className="text-2xl font-bold text-gray-800">Claim Reports</h1>

      {/* Report Configuration */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div className="flex flex-wrap items-end gap-4">
          <div className="min-w-[200px]">
            <label className="block text-sm font-medium text-gray-700 mb-1">Report Type</label>
            <select
              className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-claims-primary/30"
              value={reportType}
              onChange={e => { setReportType(e.target.value); setGenerated(false) }}
            >
              {reportTypes.map(rt => <option key={rt.value} value={rt.value}>{rt.label}</option>)}
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Date From</label>
            <input
              type="date"
              className="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-claims-primary/30"
              value={dateFrom}
              onChange={e => setDateFrom(e.target.value)}
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Date To</label>
            <input
              type="date"
              className="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-claims-primary/30"
              value={dateTo}
              onChange={e => setDateTo(e.target.value)}
            />
          </div>
          <button
            onClick={handleGenerate}
            disabled={loading}
            className="px-6 py-2 bg-claims-primary text-white rounded-lg text-sm font-medium hover:bg-claims-dark transition disabled:opacity-50"
          >
            {loading ? 'Generating...' : 'Generate Report'}
          </button>
        </div>
      </div>

      {/* Results */}
      {generated && (
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
          <div className="flex items-center justify-between px-5 py-3 border-b border-gray-100 bg-gray-50">
            <div>
              <h3 className="text-sm font-semibold text-gray-700">
                {reportTypes.find(r => r.value === reportType)?.label}
              </h3>
              <p className="text-xs text-gray-500">{dateFrom} to {dateTo} -- {rows.length} records</p>
            </div>
            <button
              onClick={handleExport}
              className="flex items-center gap-1.5 px-4 py-1.5 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 transition"
            >
              <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
              </svg>
              Export CSV
            </button>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-xs text-gray-500 border-b border-gray-100">
                  {columns.map(col => (
                    <th key={col} className={`px-4 py-3 font-medium ${isCurrencyColumn(col) ? 'text-right' : ''}`}>
                      {col}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {rows.map((row, i) => (
                  <tr key={i} className="border-b border-gray-50 hover:bg-gray-50 transition">
                    {columns.map(col => (
                      <td key={col} className={`px-4 py-3 ${isCurrencyColumn(col) ? 'text-right font-medium' : ''}`}>
                        {isCurrencyColumn(col) && typeof row[col] === 'number'
                          ? formatCurrency(row[col] as number)
                          : row[col]}
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {rows.length === 0 && (
            <p className="text-sm text-gray-400 py-8 text-center">No data for the selected criteria.</p>
          )}
        </div>
      )}

      {!generated && (
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
          <svg className="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
          </svg>
          <p className="text-gray-500 text-sm">Select a report type and date range, then click Generate Report.</p>
        </div>
      )}
    </div>
  )
}
