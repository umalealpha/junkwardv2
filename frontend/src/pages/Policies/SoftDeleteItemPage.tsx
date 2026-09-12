import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { getStoredRoles } from '../../api/auth'
import { softDeleteRecord } from '../../api/softDelete'

function isSuperAdmin(): boolean {
  return getStoredRoles().includes('Super Admin')
}

export default function SoftDeleteItemPage() {
  const navigate = useNavigate()

  const [tableName, setTableName] = useState('')
  const [recordId, setRecordId] = useState('')
  const [errors, setErrors] = useState<{ table_name?: string; record_id?: string }>({})
  const [submitting, setSubmitting] = useState(false)
  const [success, setSuccess] = useState('')
  const [serverError, setServerError] = useState('')

  // Authorization guard — Super Admin only.
  if (!isSuperAdmin()) {
    return (
      <div className="p-8 text-center text-red-600 font-medium">
        Super Admin access required.
      </div>
    )
  }

  function validate(): boolean {
    const e: typeof errors = {}
    if (!tableName.trim()) {
      e.table_name = 'Table name is required.'
    } else if (!/^[A-Za-z0-9_]+$/.test(tableName.trim())) {
      e.table_name = 'Table name may only contain letters, numbers and underscores.'
    }
    const idNum = Number(recordId)
    if (!recordId.trim()) {
      e.record_id = 'Record ID is required.'
    } else if (!Number.isInteger(idNum) || idNum < 1) {
      e.record_id = 'Record ID must be a positive whole number.'
    }
    setErrors(e)
    return Object.keys(e).length === 0
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setSuccess('')
    setServerError('')
    if (!validate()) return

    if (!window.confirm(
      `Soft delete record ID ${recordId} from "${tableName.trim()}"?\n\n` +
      'The record will be hidden from the application but kept in the database.'
    )) {
      return
    }

    setSubmitting(true)
    try {
      const res = await softDeleteRecord({
        table_name: tableName.trim(),
        record_id: Number(recordId),
      })
      setSuccess(res.message)
      setTableName('')
      setRecordId('')
      setErrors({})
    } catch (err: any) {
      const status = err?.response?.status
      const data = err?.response?.data
      const apiMsg =
        (data && typeof data === 'object' && (data.error || data.message)) ||
        (typeof data === 'string' && data) ||
        null
      let msg: string
      if (apiMsg) {
        msg = apiMsg
      } else if (status) {
        // Got an HTTP response but no recognised error body (e.g. HTML 404/500).
        msg = `Request failed (HTTP ${status}). The soft-delete API may not be deployed on the backend yet.`
      } else {
        // No response at all — network/CORS/timeout.
        msg = 'No response from the server (network, CORS or timeout). The soft-delete API may not be reachable.'
      }
      setServerError(msg)
    } finally {
      setSubmitting(false)
    }
  }

  const inp = 'w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-blue-500'
  const errInp = 'w-full px-3 py-2 border border-red-400 rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-red-500'

  return (
    <div className="p-6 max-w-2xl mx-auto space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-800">Soft Delete Item</h1>
        <p className="text-sm text-gray-500 mt-1">
          Soft-delete a single record by table name and ID. The row is kept in the
          database (its <code>deleted_at</code> is stamped) and hidden from the application.
        </p>
      </div>

      {success && (
        <div className="bg-green-50 border border-green-300 text-green-700 text-sm px-4 py-3 rounded-md">
          {success}
        </div>
      )}
      {serverError && (
        <div className="bg-red-50 border border-red-300 text-red-700 text-sm px-4 py-3 rounded-md">
          {serverError}
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-4 bg-white rounded-lg shadow-sm border p-5">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Table Name <span className="text-red-500">*</span>
          </label>
          <input
            type="text"
            value={tableName}
            onChange={e => { setTableName(e.target.value); setErrors(p => ({ ...p, table_name: undefined })) }}
            className={errors.table_name ? errInp : inp}
            placeholder="e.g. policies"
            autoComplete="off"
          />
          <p className="text-xs text-gray-500 mt-1">
            The table must contain a <code>deleted_at</code> column to support soft deletes.
          </p>
          {errors.table_name && <p className="text-red-500 text-xs mt-1">{errors.table_name}</p>}
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Record ID (Table ID) <span className="text-red-500">*</span>
          </label>
          <input
            type="number"
            min={1}
            value={recordId}
            onChange={e => { setRecordId(e.target.value); setErrors(p => ({ ...p, record_id: undefined })) }}
            className={errors.record_id ? errInp : inp}
            placeholder="e.g. 165097"
            autoComplete="off"
          />
          {errors.record_id && <p className="text-red-500 text-xs mt-1">{errors.record_id}</p>}
        </div>

        <div className="flex gap-3 pt-1">
          <button
            type="submit"
            disabled={submitting}
            className="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700 disabled:opacity-50"
          >
            {submitting ? 'Soft Deleting…' : 'Submit'}
          </button>
          <button
            type="button"
            onClick={() => navigate(-1)}
            className="px-4 py-2 border border-gray-300 text-gray-700 text-sm rounded-md hover:bg-gray-50"
          >
            Cancel
          </button>
        </div>
      </form>
    </div>
  )
}
