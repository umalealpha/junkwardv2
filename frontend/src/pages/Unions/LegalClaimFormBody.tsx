import { useState, type ReactNode } from 'react'
import { useParams, useNavigate, useLocation, Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import {
  fetchUnion, fetchMembers, createLegalClaim,
  MATTER_RELATES_TO, MATTER_TYPES, DOCUMENTATION_CATALOG,
  type UnionMember, type DocChecklistState,
} from '../../api/unions'

// ─── Presentational helpers (module-level so they never remount → no focus loss) ──

function SectionCard({ title, children }: { title: string; children: ReactNode }) {
  return (
    <div className="bg-surface rounded-lg shadow-sm border p-5 space-y-4">
      <h2 className="text-base font-semibold text-ink border-b pb-2">{title}</h2>
      {children}
    </div>
  )
}

function ReadOnly({ label, value }: { label: string; value: string | null | undefined }) {
  return (
    <div>
      <div className="text-xs text-ink-muted mb-1">{label}</div>
      <div className="px-3 py-2 bg-surface-2 border rounded text-sm text-ink min-h-[38px]">{value || '—'}</div>
    </div>
  )
}

function Text({ label, value, onChange, type = 'text', required }: {
  label: string; value: string; onChange: (v: string) => void; type?: string; required?: boolean
}) {
  return (
    <div>
      <label className="block text-xs text-ink-muted mb-1">{label}{required && <span className="text-red-500"> *</span>}</label>
      <input type={type} value={value} onChange={e => onChange(e.target.value)}
        className="w-full px-3 py-2 border rounded text-sm" />
    </div>
  )
}

function Area({ label, value, onChange, required }: {
  label: string; value: string; onChange: (v: string) => void; required?: boolean
}) {
  return (
    <div className="md:col-span-2">
      <label className="block text-xs text-ink-muted mb-1">{label}{required && <span className="text-red-500"> *</span>}</label>
      <textarea value={value} onChange={e => onChange(e.target.value)} rows={4}
        className="w-full px-3 py-2 border rounded text-sm" />
    </div>
  )
}

function RadioRow({ label, options, value, onChange, required }: {
  label: string; options: readonly string[]; value: string; onChange: (v: string) => void; required?: boolean
}) {
  return (
    <div className="md:col-span-2">
      <div className="text-xs text-ink-muted mb-1.5">{label}{required && <span className="text-red-500"> *</span>}</div>
      <div className="flex flex-wrap gap-4">
        {options.map(opt => (
          <label key={opt} className="inline-flex items-center gap-2 text-sm text-ink">
            <input type="radio" checked={value === opt} onChange={() => onChange(opt)} />
            {opt}
          </label>
        ))}
      </div>
    </div>
  )
}

// ─── Shared form body ───────────────────────────────────────────────────────────
// Renders the union legal claim form. Each dedicated per-union page (BONU /
// BOWASEWU / generic fallback) wraps this and passes its own `formName` — the
// stored form identity itself is derived server-side from the union's code, so
// this component only drives the on-screen title.

export default function LegalClaimFormBody({ formName }: { formName?: string }) {
  const { id, memberId } = useParams<{ id: string; memberId: string }>()
  const navigate = useNavigate()
  const location = useLocation()

  const unionQ = useQuery({ queryKey: ['union', id], queryFn: () => fetchUnion(id!), enabled: !!id })

  // Member: prefer the object passed from the member row; fall back to a bounded
  // roster fetch (deep-link / refresh) and find by id.
  const stateMember = (location.state as { member?: UnionMember } | null)?.member
  const membersQ = useQuery({
    queryKey: ['union-members-forclaim', id],
    queryFn: () => fetchMembers(id!, { per_page: 500 }),
    enabled: !!id && !stateMember,
  })
  const member = stateMember ?? membersQ.data?.data?.find(m => m.id === Number(memberId))

  const [form, setForm] = useState({
    region: '',
    claim_type: 'Legal',
    matter_relates_to: '',
    child_financially_dependent: '' as '' | 'yes' | 'no',
    dependent_omang_passport: '',
    dependent_dob: '',
    matter_type: '',
    matter_arose_date: '',
    proposed_course_of_action: '',
  })
  const set = <K extends keyof typeof form>(k: K, v: (typeof form)[K]) => setForm(f => ({ ...f, [k]: v }))

  const [checklist, setChecklist] = useState<DocChecklistState>({})
  const [files, setFiles] = useState<Record<string, File | null>>({})
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const setChk = (key: string, field: 'enclosed' | 'forwarded', val: boolean) =>
    setChecklist(c => ({ ...c, [key]: { enclosed: c[key]?.enclosed ?? false, forwarded: c[key]?.forwarded ?? false, [field]: val } }))

  const isChild = form.matter_relates_to === 'Child'

  async function handleSubmit() {
    setError(null)
    if (!form.matter_relates_to) { setError('Please select who the matter relates to.'); return }
    if (!form.matter_type) { setError('Please select the type of matter.'); return }
    if (!id || !memberId) { setError('Missing union / member reference.'); return }

    setSubmitting(true)
    try {
      const fd = new FormData()
      const appendIf = (k: string, v: string) => { if (v !== '' && v != null) fd.append(k, v) }
      appendIf('region', form.region)
      appendIf('claim_type', form.claim_type)
      fd.append('matter_relates_to', form.matter_relates_to)
      fd.append('matter_type', form.matter_type)
      appendIf('matter_arose_date', form.matter_arose_date)
      appendIf('proposed_course_of_action', form.proposed_course_of_action)
      if (isChild && form.child_financially_dependent) {
        fd.append('child_financially_dependent', form.child_financially_dependent === 'yes' ? '1' : '0')
        appendIf('dependent_omang_passport', form.dependent_omang_passport)
        appendIf('dependent_dob', form.dependent_dob)
      }
      fd.append('documentation_checklist', JSON.stringify(checklist))

      // Enclosed files → documents[<key>], with a parallel key→label map.
      const labels: Record<string, string> = {}
      DOCUMENTATION_CATALOG.forEach(g => g.items.forEach(it => {
        const f = files[it.key]
        if (f) { fd.append(`documents[${it.key}]`, f); labels[it.key] = it.label }
      }))
      fd.append('document_labels', JSON.stringify(labels))

      await createLegalClaim(id, memberId, fd)
      navigate(`/unions/${id}`)
    } catch (e: any) {
      setError(e?.response?.data?.error || e?.response?.data?.message || e?.message || 'Failed to file legal claim.')
    } finally {
      setSubmitting(false)
    }
  }

  if (unionQ.isLoading || (!stateMember && membersQ.isLoading)) {
    return <div className="p-12 flex justify-center"><LoadingSpinner size="lg" /></div>
  }
  if (!member) {
    return (
      <div className="p-6">
        <p className="text-sm text-ink-muted">Member not found. Please open this form from the member row.</p>
        <Link to={`/unions/${id}`} className="text-blue-600 hover:underline text-sm">← Back to union</Link>
      </div>
    )
  }

  const union = unionQ.data
  const title = formName ?? (union?.union_name ? `${union.union_name} Legal Claim Form` : 'Legal Claim Form')

  return (
    <div className="p-6 max-w-4xl mx-auto space-y-5">
      <div>
        <Link to={`/unions/${id}`} className="text-xs text-blue-600 hover:underline">← Back to {union?.union_name ?? 'union'}</Link>
        <h1 className="text-2xl font-bold text-ink mt-1">{title}</h1>
        <p className="text-sm text-ink-muted">Member: {member.member_name}</p>
      </div>

      {error && <div className="rounded-md border border-red-300 bg-red-50 px-4 py-2 text-sm text-red-700">{error}</div>}

      <SectionCard title="Claim Details">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <ReadOnly label="Policy Number" value={union?.policy_number} />
          <ReadOnly label="Insured Name" value={member.member_name} />
          <Text label="Region" value={form.region} onChange={v => set('region', v)} />
          <ReadOnly label="Omang / Passport Number" value={member.id_number} />
          <ReadOnly label="Cellphone / Telephone" value={member.contact_number} />
          <ReadOnly label="Email Address" value={member.email} />
          <Text label="Claim Type" value={form.claim_type} onChange={v => set('claim_type', v)} />
        </div>
      </SectionCard>

      <SectionCard title="Details of the Matter">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <RadioRow label="Who does the matter relate to?" options={MATTER_RELATES_TO}
            value={form.matter_relates_to} onChange={v => set('matter_relates_to', v)} required />

          {isChild && (
            <>
              <RadioRow label="If a child, is the child financially dependent on the Main Member and a full-time scholar?"
                options={['Yes', 'No']}
                value={form.child_financially_dependent === 'yes' ? 'Yes' : form.child_financially_dependent === 'no' ? 'No' : ''}
                onChange={v => set('child_financially_dependent', v === 'Yes' ? 'yes' : 'no')} />
              <Text label="Omang / passport # of dependent" value={form.dependent_omang_passport} onChange={v => set('dependent_omang_passport', v)} />
              <Text label="Dependent Date of birth" type="date" value={form.dependent_dob} onChange={v => set('dependent_dob', v)} />
            </>
          )}

          <RadioRow label="Type of Matter" options={MATTER_TYPES}
            value={form.matter_type} onChange={v => set('matter_type', v)} required />
          <Text label="Date upon which the matter arose" type="date" value={form.matter_arose_date} onChange={v => set('matter_arose_date', v)} />
          <Area label="Proposed course of action" value={form.proposed_course_of_action} onChange={v => set('proposed_course_of_action', v)} />
        </div>
      </SectionCard>

      <SectionCard title="Documentation (enclosed herewith, or to be forwarded)">
        <p className="text-xs text-ink-muted -mt-2">Tick <b>Enclosed</b> and attach the file, or tick <b>To be forwarded</b> to send it later.</p>
        <div className="space-y-5">
          {DOCUMENTATION_CATALOG.map(group => (
            <div key={group.group}>
              <div className="text-sm font-semibold text-ink mb-2">{group.group}</div>
              <div className="space-y-2">
                {group.items.map(item => {
                  const st = checklist[item.key] ?? { enclosed: false, forwarded: false }
                  return (
                    <div key={item.key} className="flex flex-col md:flex-row md:items-center gap-2 md:gap-4 border-b border-line pb-2">
                      <div className="flex-1 text-sm text-ink">{item.label}</div>
                      <label className="inline-flex items-center gap-1.5 text-xs text-ink-muted">
                        <input type="checkbox" checked={st.enclosed} onChange={e => setChk(item.key, 'enclosed', e.target.checked)} /> Enclosed
                      </label>
                      <label className="inline-flex items-center gap-1.5 text-xs text-ink-muted">
                        <input type="checkbox" checked={st.forwarded} onChange={e => setChk(item.key, 'forwarded', e.target.checked)} /> To be forwarded
                      </label>
                      <input type="file" disabled={!st.enclosed}
                        onChange={e => setFiles(f => ({ ...f, [item.key]: e.target.files?.[0] ?? null }))}
                        className="text-xs w-56 disabled:opacity-40" />
                    </div>
                  )
                })}
              </div>
            </div>
          ))}
        </div>
      </SectionCard>

      <div className="flex gap-2">
        <button onClick={handleSubmit} disabled={submitting}
          className="px-5 py-2 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700 disabled:opacity-50">
          {submitting ? 'Filing…' : 'File Legal Claim'}
        </button>
        <Link to={`/unions/${id}`} className="px-4 py-2 border rounded text-sm hover:bg-surface-2">Cancel</Link>
      </div>
    </div>
  )
}
