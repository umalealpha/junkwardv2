import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { createEmployerGroup } from '../../api/employerGroups'
import { getStoredPermissions } from '../../api/auth'
import EmployerGroupForm, { EMPTY_FORM, toPayload, type EmployerGroupFormValues } from './EmployerGroupForm'

function canCreateEmployerGroup(): boolean {
  return getStoredPermissions().includes('employer-group-create')
}

export default function EmployerGroupCreatePage() {
  const navigate = useNavigate()
  const [submitting, setSubmitting] = useState(false)
  const [serverError, setServerError] = useState('')

  if (!canCreateEmployerGroup()) {
    return (
      <div className="p-8 text-center">
        <p className="text-status-danger-fg font-medium">Access denied. Manager or above required to create employer groups.</p>
        <button onClick={() => navigate(-1)} className="mt-4 text-sm text-primary underline">Go back</button>
      </div>
    )
  }

  async function handleSubmit(form: EmployerGroupFormValues) {
    setSubmitting(true)
    setServerError('')
    try {
      const res = await createEmployerGroup(toPayload(form))
      navigate('/ad-group/employer-groups', {
        state: { successMessage: `Employer group "${res.data.name}" created — ID: ${res.data.employerGroupId}` },
      })
    } catch (err: any) {
      setServerError(err?.response?.data?.message || err?.response?.data?.error || 'Failed to create employer group. Please try again.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="p-6 max-w-3xl mx-auto space-y-6">
      <div className="flex items-center gap-3">
        <button onClick={() => navigate(-1)} className="text-ink-faint hover:text-ink p-1 rounded" title="Back">
          <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <h1 className="text-2xl font-bold text-ink">New Employer Group</h1>
      </div>

      <EmployerGroupForm
        initial={EMPTY_FORM}
        submitLabel="Create Employer Group"
        submitting={submitting}
        serverError={serverError}
        onSubmit={handleSubmit}
        onCancel={() => navigate(-1)}
      />
    </div>
  )
}
