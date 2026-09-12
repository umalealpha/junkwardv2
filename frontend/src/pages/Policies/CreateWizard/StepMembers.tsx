import { InputField, SelectField, Section, TestDataButton } from './FormField'
import { generateTestMember } from './testData'
import type { MemberForm } from './types'
import { INITIAL_MEMBER } from './types'

interface Props {
  members: MemberForm[]
  setMembers: (v: MemberForm[]) => void
  lookups: any
  errors: Record<string, string>
}

export default function StepMembers({ members, setMembers, lookups }: Props) {
  const addMember = () => setMembers([...members, { ...INITIAL_MEMBER }])
  const removeMember = (i: number) => setMembers(members.filter((_, idx) => idx !== i))
  const updateMember = (i: number, key: keyof MemberForm, value: string) => {
    const updated = [...members]
    updated[i] = { ...updated[i], [key]: value }
    setMembers(updated)
  }

  return (
    <div className="space-y-6">
      <Section title="Members / Sub-Applicants" action={
        <div className="flex gap-2">
          <TestDataButton onClick={() => setMembers([...members, generateTestMember()])} />
          <button onClick={addMember} className="px-3 py-1 text-xs bg-primary text-white rounded hover:bg-primary">+ Add Member</button>
        </div>
      }>
        {members.length === 0 ? (
          <p className="text-sm text-ink-muted text-center py-4">No members added yet. Click "Add Member" to add sub-applicants.</p>
        ) : (
          <div className="space-y-4">
            {members.map((m, i) => (
              <div key={i} className="p-4 border rounded-md bg-surface-2">
                <div className="flex justify-between items-center mb-3">
                  <h4 className="text-sm font-medium text-ink-muted">Member {i + 1}</h4>
                  <button onClick={() => removeMember(i)} className="text-xs text-status-danger-fg hover:text-status-danger-fg">Remove</button>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-5 gap-3">
                  <SelectField label="Relation" value={m.relation}
                    onChange={v => updateMember(i, 'relation', v)}
                    options={lookups?.member_relations ?? []} />
                  <InputField label="First Name" value={m.first_name}
                    onChange={v => updateMember(i, 'first_name', v)} />
                  <InputField label="Last Name" value={m.last_name}
                    onChange={v => updateMember(i, 'last_name', v)} />
                  <InputField label="Date of Birth" type="date" value={m.dob}
                    onChange={v => updateMember(i, 'dob', v)} />
                  <SelectField label="Gender" value={m.gender}
                    onChange={v => updateMember(i, 'gender', v)}
                    options={lookups?.genders ?? []} />
                </div>
              </div>
            ))}
          </div>
        )}
      </Section>
    </div>
  )
}
