import { useState } from 'react'
import apiClient from '../../api/client'
import type { ClaimData } from './types'

export default function DetailsTab({ claim, onUpdate }: { claim: ClaimData; onUpdate: () => void }) {
  const [editing, setEditing] = useState(false)
  const [values, setValues] = useState<Record<string, string>>(claim.dynamic_fields || {})
  const [saving, setSaving] = useState(false)

  const fieldLabels: Record<string, string> = {
    driver_name: 'Driver Name',
    driver_license: 'Driver License #',
    vehicle_reg: 'Vehicle Registration',
    accident_type: 'Accident Type',
    police_ref: 'Police Reference #',
    damage_description: 'Vehicle Damage Description',
    estimated_repair: 'Estimated Repair Cost',
    stolen_items: 'Description of Stolen Items',
    security_measures: 'Security Measures in Place',
    estimated_value: 'Estimated Value',
    premises_address: 'Premises Address',
    cause: 'Cause of Fire',
    fire_brigade_called: 'Fire Brigade Called',
    fire_brigade_ref: 'Fire Brigade Reference',
    estimated_damage: 'Estimated Damage',
    entry_point: 'Point of Entry',
    items_stolen: 'Items Stolen',
    details: 'Additional Details',
  }

  const handleSave = () => {
    setSaving(true)
    apiClient.put(`/claims-v2/${claim.id}/details`, { dynamic_fields: values })
      .then(() => { setEditing(false); onUpdate() })
      .catch(() => { setEditing(false) })
      .finally(() => setSaving(false))
  }

  const fields = Object.entries(values)

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h3 className="text-sm font-semibold text-gray-700">Product-Specific Details</h3>
          <p className="text-xs text-gray-500">Claim type: {claim.claim_type}</p>
        </div>
        {!editing ? (
          <button
            onClick={() => setEditing(true)}
            className="px-3 py-1.5 text-sm font-medium text-claims-primary hover:bg-claims-light/50 rounded-lg transition"
          >
            Edit
          </button>
        ) : (
          <div className="flex gap-2">
            <button
              onClick={() => { setValues(claim.dynamic_fields || {}); setEditing(false) }}
              className="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-800 transition"
            >
              Cancel
            </button>
            <button
              onClick={handleSave}
              disabled={saving}
              className="px-3 py-1.5 text-sm font-medium bg-claims-primary text-white rounded-lg hover:bg-claims-dark transition disabled:opacity-50"
            >
              {saving ? 'Saving...' : 'Save'}
            </button>
          </div>
        )}
      </div>

      {fields.length > 0 ? (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {fields.map(([key, value]) => (
            <div key={key} className={`bg-gray-50 rounded-lg p-4 ${value.length > 80 ? 'md:col-span-2' : ''}`}>
              <label className="block text-xs font-semibold text-gray-500 uppercase mb-1">
                {fieldLabels[key] || key.replace(/_/g, ' ')}
              </label>
              {editing ? (
                value.length > 80 ? (
                  <textarea
                    rows={3}
                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-claims-primary/30"
                    value={values[key] || ''}
                    onChange={e => setValues(v => ({ ...v, [key]: e.target.value }))}
                  />
                ) : (
                  <input
                    type="text"
                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-claims-primary/30"
                    value={values[key] || ''}
                    onChange={e => setValues(v => ({ ...v, [key]: e.target.value }))}
                  />
                )
              ) : (
                <p className="text-sm text-gray-700">{value || '--'}</p>
              )}
            </div>
          ))}
        </div>
      ) : (
        <p className="text-sm text-gray-400 py-8 text-center">No product-specific details recorded.</p>
      )}
    </div>
  )
}
