import { useState } from 'react'
import { InputField, SelectField, CheckboxField, Section, TestDataButton } from './FormField'
import { generateTestRiskAddress } from './testData'
import type { RiskAddressForm, SavedRiskAddress } from './types'

interface Props {
  riskForm: RiskAddressForm
  setRiskForm: (v: RiskAddressForm) => void
  savedAddresses: SavedRiskAddress[]
  onSave: () => void
  onDelete: (id: number) => void
  onReinstate?: (id: number) => void
  // Edit an already-saved address in place (action-wise — the card shown is
  // the row belonging to the action the wizard has open).
  onEdit?: (addr: SavedRiskAddress) => void
  onCancelEdit?: () => void
  editingId?: number | null
  errors: Record<string, string>
  setErrors: (fn: (p: Record<string, string>) => Record<string, string>) => void
  lookups: any
  riskCities: any[]
  riskCitiesLoading?: boolean
  saving?: boolean
}

export default function StepRiskAddresses({ riskForm, setRiskForm, savedAddresses, onSave, onDelete, onReinstate, onEdit, onCancelEdit, editingId, errors, setErrors, lookups, riskCities, riskCitiesLoading, saving }: Props) {
  const [confirmDelete, setConfirmDelete] = useState<number | null>(null)

  const update = (key: keyof RiskAddressForm, value: any) => {
    setRiskForm({ ...riskForm, [key]: value })
    setErrors(p => ({ ...p, [`ra_${key}`]: '' }))
  }

  return (
    <div className="space-y-6">
      {/* Saved addresses */}
      {savedAddresses.length > 0 && (
        <Section title={`Saved Risk Addresses (${savedAddresses.length})`}>
          <div className="space-y-3">
            {savedAddresses.map((addr) => {
              const isCancelled = !!((addr as any).deleted_at)
              const isEditing = editingId === addr.id
              return (
              <div key={addr.id} className={`flex items-center justify-between p-3 border rounded-md ${isCancelled ? 'bg-surface-2 border-line text-ink-faint line-through' : 'bg-status-success-bg border-status-success-fg'} ${isEditing ? 'ring-2 ring-primary' : ''}`}>
                <div>
                  <p className="text-sm font-medium text-ink">{addr.address_name}</p>
                  <p className="text-xs text-ink-muted">{addr.physical_address} | {addr.const_type} | Built: {addr.year_built || 'N/A'}</p>
                  <div className="flex gap-2 mt-1">
                    {addr.central_fire && <span className="text-[10px] bg-status-success-bg text-status-success-fg px-1.5 rounded">Fire Alarm</span>}
                    {addr.central_burglar && <span className="text-[10px] bg-status-success-bg text-status-success-fg px-1.5 rounded">Burglar Alarm</span>}
                    {addr.gated_community && <span className="text-[10px] bg-status-success-bg text-status-success-fg px-1.5 rounded">Gated</span>}
                    {addr.automatic && <span className="text-[10px] bg-status-success-bg text-status-success-fg px-1.5 rounded">Auto Sprinkler</span>}
                    {isCancelled && <span className="text-[10px] bg-status-danger-bg text-status-danger-fg px-1.5 rounded font-semibold">CANCELLED</span>}
                    {isEditing && <span className="text-[10px] bg-status-info-bg text-primary px-1.5 rounded font-semibold">EDITING</span>}
                  </div>
                </div>
                {isCancelled ? (
                  onReinstate && <button onClick={() => onReinstate(addr.id)}
                    className="px-2 py-1 text-xs text-status-success-fg border border-status-success-fg rounded hover:bg-status-success-bg"
                    title="Reinstate (un-cancel) — pro-rata applies in next endorsement">
                    Reinstate
                  </button>
                ) : confirmDelete === addr.id ? (
                  <div className="flex gap-1">
                    <button onClick={() => { onDelete(addr.id); setConfirmDelete(null) }}
                      className="px-2 py-1 text-xs bg-status-danger-fg text-white rounded">Confirm Cancel</button>
                    <button onClick={() => setConfirmDelete(null)}
                      className="px-2 py-1 text-xs border rounded">Back</button>
                  </div>
                ) : (
                  <div className="flex gap-1">
                    {onEdit && (
                      <button onClick={() => onEdit(addr)}
                        className="px-2 py-1 text-xs text-primary border border-primary rounded hover:bg-status-info-bg"
                        title="Edit this risk address on the transaction currently open (action-wise). No coverage or premium is touched.">
                        Edit
                      </button>
                    )}
                    <button onClick={() => setConfirmDelete(addr.id)}
                      className="px-2 py-1 text-xs text-status-danger-fg border border-status-danger-fg rounded hover:bg-status-danger-bg"
                      title="Cancel risk address (soft-delete; can be reinstated). Cancels all coverages under this address.">
                      Cancel
                    </button>
                  </div>
                )}
              </div>
              )
            })}
          </div>
        </Section>
      )}

      {/* Add new risk address */}
      {/* Anchored so Smart Upload's "Load into form" can scroll here — the
          form sits below the saved-address list and the fill was happening
          off-screen. scroll-mt keeps it clear of the sticky header. */}
      <div id="add-risk-address-form" className="scroll-mt-24">
      <Section
        title={editingId ? 'Edit Risk Address' : 'Add Risk Address'}
        action={editingId
          ? <button onClick={() => onCancelEdit?.()} type="button"
              className="px-2 py-1 text-xs text-ink-muted border border-line rounded hover:bg-surface-2">
              Cancel Edit
            </button>
          : <TestDataButton onClick={() => setRiskForm(generateTestRiskAddress())} />}
      >
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <InputField label="Address Name" value={riskForm.address_name} required
            onChange={v => update('address_name', v)} error={errors.ra_address_name} placeholder="e.g. Main Office - Gaborone" />
          <InputField label="Physical Address" value={riskForm.physical_address} required
            onChange={v => update('physical_address', v)} error={errors.ra_physical_address} placeholder="Plot number, street, area" />
        </div>

        {/* UAT 2026-05-29 (Muskan #4): legacy Graphite Risk Address captures lat/lng;
            V2 form previously didn't expose them so risk_address.lat/lng stayed NULL.
            BE validator already accepts them (PolicyCreateController::addRiskAddress
            +updateRiskAddress, both nullable|numeric) — pure FE surface. */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <InputField label="Latitude" value={riskForm.lat || ''}
            onChange={v => update('lat', v)}
            placeholder="e.g. -24.6282 (Gaborone)" />
          <InputField label="Longitude" value={riskForm.lng || ''}
            onChange={v => update('lng', v)}
            placeholder="e.g. 25.9231 (Gaborone)" />
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <SelectField label="Province" value={riskForm.risk_state} required
            onChange={v => {
              setRiskForm({ ...riskForm, risk_state: v ? parseInt(v) : null, risk_city: null })
              setErrors(p => ({ ...p, ra_risk_state: '' }))
            }}
            options={lookups?.states ?? []} error={errors.ra_risk_state} />
          <SelectField label="City" value={riskForm.risk_city} required
            onChange={v => update('risk_city', v ? parseInt(v) : null)}
            options={riskCities ?? []} loading={riskCitiesLoading} error={errors.ra_risk_city} />
          <SelectField label="Construction Type" value={riskForm.const_type} required
            onChange={v => update('const_type', v)}
            options={lookups?.construction_types ?? []} error={errors.ra_const_type} />
        </div>

        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <SelectField label="Extension" value={riskForm.extension || ''}
            onChange={v => update('extension', v)}
            options={lookups?.extensions ?? []} />
          <SelectField label="Occupation Type" value={riskForm.occupation || ''}
            onChange={v => update('occupation', v)}
            options={lookups?.occupation_types ?? []} />
          <InputField label="Year Built" value={riskForm.year_built || ''}
            onChange={v => update('year_built', v)} placeholder="e.g. 2005" />
          <InputField label="Area (sqm)" value={riskForm.area || ''}
            onChange={v => update('area', v)} placeholder="e.g. 500" />
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <SelectField label="Structure Type" value={riskForm.structure_type || ''}
            onChange={v => update('structure_type', v)}
            options={lookups?.structure_types ?? []} />
          <SelectField label="Usage" value={riskForm.usage || ''}
            onChange={v => update('usage', v)}
            options={lookups?.usage_types ?? []} />
        </div>

        {/* Underwriting classification (legacy risk_address cols).
            Drives premium calculation + reinsurance mapping. Previously
            absent from the V2 form so these stayed NULL on every
            address. Mirror of legacy create.blade property-risk section. */}
        <div className="p-3 bg-status-info-bg rounded-md border border-primary">
          <p className="text-xs font-medium text-ink-muted mb-2">Underwriting Classification</p>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
            <SelectField label="Town Class" value={riskForm.town_class || ''}
              onChange={v => update('town_class', v)}
              options={[
                { id: 'High', name: 'High' },
                { id: 'Medium', name: 'Medium' },
                { id: 'Low', name: 'Low' },
              ]} />
            <SelectField label="Risk Class" value={riskForm.risk_class || ''}
              onChange={v => update('risk_class', v)}
              options={[
                { id: 'High', name: 'High' },
                { id: 'Medium', name: 'Medium' },
                { id: 'Low', name: 'Low' },
              ]} />
            <InputField label="ISO RCV" value={riskForm.iso_rcv || ''}
              onChange={v => update('iso_rcv', v)} placeholder="ISO rating" />
            <SelectField label="Occupancy Type" value={riskForm.occupancy_type || ''}
              onChange={v => update('occupancy_type', v)}
              options={lookups?.occupancy_types ?? []} />
            <InputField label="Distance to Water (m)" value={riskForm.distance_to_water || ''}
              onChange={v => update('distance_to_water', v)} placeholder="metres" />
            <InputField label="Distance to Fire Station (m)" value={riskForm.distance_to_fire || ''}
              onChange={v => update('distance_to_fire', v)} placeholder="metres" />
            <InputField label="Distance to Hydrant (m)" value={riskForm.distance_to_hydrant || ''}
              onChange={v => update('distance_to_hydrant', v)} placeholder="metres" />
          </div>
        </div>

        <div className="p-3 bg-surface-2 rounded-md">
          <p className="text-xs font-medium text-ink-muted mb-2">Safety Features</p>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
            <CheckboxField label="Central Fire Alarm" checked={riskForm.central_fire}
              onChange={v => update('central_fire', v)} />
            <CheckboxField label="Central Burglar Alarm" checked={riskForm.central_burglar}
              onChange={v => update('central_burglar', v)} />
            <CheckboxField label="Gated Community" checked={riskForm.gated_community}
              onChange={v => update('gated_community', v)} />
            <CheckboxField label="Automatic Sprinkler" checked={riskForm.automatic}
              onChange={v => update('automatic', v)} />
          </div>
        </div>

        <div className="flex gap-2">
          <button onClick={onSave} disabled={saving}
            className="px-4 py-2 bg-primary text-white text-sm font-medium rounded-md hover:bg-primary disabled:opacity-50">
            {saving ? 'Saving...' : editingId ? 'Update Risk Address' : 'Save Risk Address'}
          </button>
          {editingId && (
            <button onClick={() => onCancelEdit?.()} type="button" disabled={saving}
              className="px-4 py-2 border border-line text-ink-muted text-sm font-medium rounded-md hover:bg-surface-2 disabled:opacity-50">
              Cancel
            </button>
          )}
        </div>
      </Section>
      </div>
    </div>
  )
}
