/**
 * Minimal client-side CSV export helper. No dependency — builds an RFC-4180
 * quoted CSV from a header row + string cells and triggers a browser download.
 *
 * Every cell is quoted and internal quotes are doubled, so commas / newlines /
 * quotes in data (e.g. customer names) can't corrupt the columns. A leading
 * BOM is emitted so Excel opens UTF-8 (Pula "P", accented names) correctly.
 */
function escapeCell(value: unknown): string {
  const s = value === null || value === undefined ? '' : String(value)
  return `"${s.replace(/"/g, '""')}"`
}

export function downloadCsv(filename: string, headers: string[], rows: (string | number | null | undefined)[][]): void {
  const lines = [headers, ...rows].map((row) => row.map(escapeCell).join(','))
  const csv = '﻿' + lines.join('\r\n')
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename.endsWith('.csv') ? filename : `${filename}.csv`
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  // Revoke on the next tick so the click has definitely been dispatched.
  setTimeout(() => URL.revokeObjectURL(url), 0)
}
