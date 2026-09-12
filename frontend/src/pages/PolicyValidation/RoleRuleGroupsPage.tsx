import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import Select from 'react-select'
import apiClient from '../../api/client'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import EmptyState from '../../components/common/EmptyState'

type RoleRow = {
  id: number
  name: string
  guard_name: string
  /** MySQL stores roles.rule_group as varchar so this arrives as a
      string at runtime even though it represents an int PK. Coerce
      with Number() at comparison sites. */
  rule_group: number | string | null
  group_code: string | null
  group_desc: string | null
}

type GroupOpt = {
  n_PrValidationRuleGroupMasters_PK: number
  s_RuleCode: string
  s_RuleDesc: string | null
}

// Maps each application role to a Validation Rule Group. `roles.rule_group`
// is read at Submit-to-Approval to pick which rule set gates the transition
// (legacy PolicyController::submitToApproval line 22673).
export default function RoleRuleGroupsPage() {
  const qc = useQueryClient()

  const roles = useQuery<{ data: RoleRow[] }>({
    queryKey: ['roles-with-rule-group'],
    queryFn: () => apiClient.get('/policy-validation/role-groups').then(r => r.data),
  })

  const groups = useQuery({
    queryKey: ['validation-groups-all'],
    queryFn: () => apiClient.get('/policy-validation/groups', { params: { per_page: 500 } }).then(r => r.data),
    staleTime: 60_000,
  })

  const assignMut = useMutation({
    mutationFn: ({ roleId, ruleGroup }: { roleId: number; ruleGroup: number | null }) =>
      apiClient.put(`/policy-validation/roles/${roleId}/group`, { rule_group: ruleGroup }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['roles-with-rule-group'] }),
    onError: (err: any) => alert(err?.response?.data?.error || 'Assign failed'),
  })

  const rows: RoleRow[] = roles.data?.data ?? []
  const groupOpts: GroupOpt[] = groups.data?.data ?? []

  return (
    <div className="p-6 space-y-4">
      <div>
        <h1 className="text-2xl font-bold text-gray-800">Role → Rule Group Assignment</h1>
        <p className="text-sm text-gray-500 mt-0.5">
          Each role can be bound to one Validation Rule Group. On Submit-to-Approval the engine
          evaluates only the rules in that group — change here to tighten or relax per-role gating.
        </p>
      </div>

      <div className="bg-white rounded-lg shadow-sm border overflow-hidden relative">
        {roles.isFetching && !roles.isLoading && (
          <div className="absolute inset-0 bg-white/50 z-10 flex items-center justify-center"><LoadingSpinner size="md" /></div>
        )}
        <table className="w-full text-sm">
          <thead className="bg-gray-50 text-gray-600 uppercase text-xs">
            <tr>
              <th className="px-4 py-3 text-left">Role ID</th>
              <th className="px-4 py-3 text-left">Role Name</th>
              <th className="px-4 py-3 text-left">Current Group</th>
              <th className="px-4 py-3 text-left">Assign Group</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {roles.isLoading ? (
              <tr><td colSpan={4} className="px-4 py-12 text-center"><LoadingSpinner size="md" /></td></tr>
            ) : rows.length === 0 ? (
              <tr><td colSpan={4} className="p-0"><EmptyState compact title="No roles" description="No role rule groups have been configured yet." /></td></tr>
            ) : rows.map(r => (
              <tr key={r.id} className="hover:bg-gray-50">
                <td className="px-4 py-2 text-gray-500">{r.id}</td>
                <td className="px-4 py-2 font-medium text-gray-800">{r.name}</td>
                <td className="px-4 py-2 text-gray-600">
                  {r.group_code ? (
                    <span>
                      <span className="font-mono text-xs text-blue-700">{r.group_code}</span>
                      {r.group_desc && <span className="text-gray-500 ml-2">— {r.group_desc}</span>}
                    </span>
                  ) : '—'}
                </td>
                <td className="px-4 py-2">
                  {/* react-select with menuPortalTarget — keeps the long
                      grade names (e.g. "ELECTRONICEQANDBI_DOM_DRIVER — …")
                      from overflowing the table cell, and adds typeahead
                      so admins can jump to "U-3" by typing 3 chars. */}
                  <Select<{ value: string; label: string }>
                    value={
                      r.rule_group != null && r.rule_group !== ''
                        ? (() => {
                            // roles.rule_group is varchar in DB → arrives as a
                            // string at runtime even though the type hint says
                            // number. Compare as numbers so the preselected
                            // option matches regardless of which side coerced.
                            const target = Number(r.rule_group)
                            const g = groupOpts.find(x => Number(x.n_PrValidationRuleGroupMasters_PK) === target)
                            return g
                              ? {
                                  value: String(g.n_PrValidationRuleGroupMasters_PK),
                                  label: g.s_RuleCode + (g.s_RuleDesc ? ` — ${g.s_RuleDesc}` : ''),
                                }
                              : null
                          })()
                        : null
                    }
                    onChange={opt =>
                      assignMut.mutate({
                        roleId: r.id,
                        ruleGroup: opt?.value ? Number(opt.value) : null,
                      })
                    }
                    options={groupOpts.map(g => ({
                      value: String(g.n_PrValidationRuleGroupMasters_PK),
                      label: g.s_RuleCode + (g.s_RuleDesc ? ` — ${g.s_RuleDesc}` : ''),
                    }))}
                    isDisabled={assignMut.isPending}
                    isClearable
                    isSearchable
                    placeholder="— unassigned —"
                    menuPortalTarget={typeof document !== 'undefined' ? document.body : undefined}
                    styles={{
                      container: base => ({ ...base, width: '18rem' }),
                      control: (base, state) => ({
                        ...base,
                        minHeight: '32px',
                        fontSize: '0.75rem',
                        borderColor: state.isFocused ? '#3b82f6' : '#d1d5db',
                        boxShadow: state.isFocused ? '0 0 0 2px rgba(59,130,246,0.3)' : 'none',
                        '&:hover': { borderColor: state.isFocused ? '#3b82f6' : '#9ca3af' },
                      }),
                      menu:       base => ({ ...base, fontSize: '0.75rem', zIndex: 9999 }),
                      menuPortal: base => ({ ...base, zIndex: 9999 }),
                      option: (base, state) => ({
                        ...base,
                        backgroundColor: state.isSelected ? '#3b82f6' : state.isFocused ? '#eff6ff' : '#fff',
                        color: state.isSelected ? '#fff' : '#111',
                        fontSize: '0.75rem',
                        cursor: 'pointer',
                      }),
                    }}
                  />
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
