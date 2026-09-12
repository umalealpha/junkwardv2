import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useEmployerGroup, useUpdateEmployerGroup } from '../../hooks/useEmployerGroups'
import { getStoredPermissions } from '../../api/auth'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import EmptyState from '../../components/common/EmptyState'
import EmployerGroupForm, { toPayload, type EmployerGroupFormValues } from './EmployerGroupForm'

function canEditEmployerGroup(): boolean {
  const perms = getStoredPermissions()
  return perms.length === 0 || perms.includes('employer-group-edit')
}

export default function EmployerGroupEditPage() {
  const { id } = useParams()
  const groupId = Number(id)
  const navigate = useNavigate()

  const { data: group, isLoading } = useEmployerGroup(groupId)
  const update = useUpdateEmployerGroup(groupId)
  const [serverError, setServerError] = useState('')

  if (!canEditEmployerGroup()) {
    return (
      <div className="p-8 text-center">
        <p className="text-status-danger-fg font-medium">Access denied. You do not have permission to edit employer groups.</p>
        <button onClick={() => navigate(-1)} className="mt-4 text-sm text-primary underline">Go back</button>
      </div>
    )
  }

  if (isLoading) return <div className="p-12 flex justify-center"><LoadingSpinner size="lg" /></div>
  if (!group) return <div className="p-6"><EmptyState title="Employer group not found" /></div>

  const initial: EmployerGroupFormValues = {
    name: group.name ?? '',
    industry: group.industry ?? '',
    other_industry: group.otherIndustry ?? '',
    address: group.address ?? '',
    town: group.town ?? '',
    postal_code: group.postalCode ?? '',
    contact_name: group.contactName ?? '',
    contact_phone: group.contactPhone ?? '',
    contact_email: group.contactEmail ?? '',
    no_of_employees: group.noOfEmployees,
    broker: group.broker ?? '',
    payment_method: group.paymentMethod ?? '',
    notes: group.notes ?? '',
    status: (group.status ?? 'active').toLowerCase(),
    bulk_emails_text: group.hrEmails.join('\n'),
  }

  async function handleSubmit(form: EmployerGroupFormValues) {
    setServerError('')
    try {
      await update.mutateAsync(toPayload(form))
      navigate(`/ad-group/employer-groups/${groupId}`, {
        state: { toast: 'Employer group updated.' },
      })
    } catch (err: any) {
      setServerError(err?.response?.data?.message || err?.response?.data?.error || 'Failed to update employer group.')
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
        <h1 className="text-2xl font-bold text-ink">Edit Employer Group</h1>
      </div>

      {/* Keyed on id+updatedAt so a background refetch (stale cache) remounts
          the form with fresh values instead of silently keeping old state. */}
      <EmployerGroupForm
        key={`${group.id}-${group.updatedAt ?? ''}`}
        initial={initial}
        groupCode={group.employerGroupId}
        requireContact={false}
        submitLabel="Save Changes"
        submitting={update.isPending}
        serverError={serverError}
        onSubmit={handleSubmit}
        onCancel={() => navigate(-1)}
      />
    </div>
  )
}
