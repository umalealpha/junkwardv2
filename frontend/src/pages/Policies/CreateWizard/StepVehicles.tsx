import { InputField, SelectField, CheckboxField, FileUpload, Section, TestDataButton } from './FormField'
import { generateTestVehicle } from './testData'
import type { VehicleForm, VehicleImages, MotorItemForm } from './types'

interface Props {
  vehicle: VehicleForm
  setVehicle: (v: VehicleForm) => void
  vehicleImages: VehicleImages
  setVehicleImages: (v: VehicleImages) => void
  motorItems: MotorItemForm[]
  setMotorItems: (v: MotorItemForm[]) => void
  showMotorItems: boolean
  vehicleMakes: any[]
  vehicleModels: any[]
  vehicleMakesLoading?: boolean
  vehicleModelsLoading?: boolean
  lookups: any
  errors: Record<string, string>
}

export default function StepVehicles({ vehicle, setVehicle, vehicleImages, setVehicleImages, motorItems, setMotorItems, showMotorItems, vehicleMakes, vehicleModels, vehicleMakesLoading, vehicleModelsLoading, lookups, errors }: Props) {
  const update = (key: keyof VehicleForm, value: any) => setVehicle({ ...vehicle, [key]: value })
  const updateImage = (key: keyof VehicleImages, file: File | null) => setVehicleImages({ ...vehicleImages, [key]: file })

  const years = Array.from({ length: 30 }, (_, i) => {
    const y = String(new Date().getFullYear() - i)
    return { id: y, name: y }
  })

  return (
    <div className="space-y-6">
      <Section title="Vehicle Details" action={<TestDataButton onClick={() => setVehicle(generateTestVehicle())} />}>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <InputField label="Vehicle Plate Number" value={vehicle.plate_number} required
            onChange={v => update('plate_number', v)} error={errors.v_plate} placeholder="e.g. B 123 AAA" />
          <InputField label="Chassis Number (VIN)" value={vehicle.chassis_number}
            onChange={v => update('chassis_number', v)} />
          <InputField label="Odometer (km)" type="number" value={vehicle.odometer}
            onChange={v => update('odometer', v)} />
        </div>

        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <SelectField label="Make" value={vehicle.make_id}
            onChange={v => { setVehicle({ ...vehicle, make_id: v ? parseInt(v) : null, model_id: null }) }}
            options={vehicleMakes ?? []} loading={vehicleMakesLoading} />
          <SelectField label="Model" value={vehicle.model_id}
            onChange={v => update('model_id', v ? parseInt(v) : null)}
            options={vehicleModels ?? []} loading={vehicleModelsLoading} />
          <SelectField label="Year" value={vehicle.year}
            onChange={v => update('year', v)} options={years} />
          <SelectField label="Purpose" value={vehicle.purpose}
            onChange={v => update('purpose', v)}
            options={lookups?.vehicle_purposes ?? []} />
        </div>

        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <SelectField label="Condition" value={vehicle.condition}
            onChange={v => update('condition', v)}
            options={lookups?.vehicle_conditions ?? []} />
          <InputField label="Engine Number" value={vehicle.engine_number}
            onChange={v => update('engine_number', v)} />
          <InputField label="No. of Seats" type="number" value={vehicle.seats}
            onChange={v => update('seats', v)} />
          <InputField label="Cylinders" type="number" value={vehicle.cylinders}
            onChange={v => update('cylinders', v)} />
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <InputField label="Cubic Capacity (cc)" type="number" value={vehicle.cubic_capacity}
            onChange={v => update('cubic_capacity', v)} />
          <InputField label="Estimated Value (BWP)" type="number" value={vehicle.estimated_value}
            onChange={v => update('estimated_value', v)} />
          <InputField label="Previous Claims" type="number" value={vehicle.claim_count}
            onChange={v => update('claim_count', v)} />
        </div>

        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 p-3 bg-surface-2 rounded-md">
          <CheckboxField label="Imported Vehicle" checked={vehicle.is_imported}
            onChange={v => update('is_imported', v)} />
          <CheckboxField label="Has Tracking Device" checked={vehicle.has_tracking}
            onChange={v => update('has_tracking', v)} />
          <CheckboxField label="Private Use" checked={vehicle.is_private}
            onChange={v => update('is_private', v)} />
          <CheckboxField label="Modified" checked={vehicle.is_modified}
            onChange={v => update('is_modified', v)} />
        </div>
      </Section>

      {/* Vehicle Images */}
      <Section title="Vehicle Images">
        <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
          <FileUpload label="Front View" file={vehicleImages.front} onChange={f => updateImage('front', f)} />
          <FileUpload label="Back View" file={vehicleImages.back} onChange={f => updateImage('back', f)} />
          <FileUpload label="Left Side" file={vehicleImages.left} onChange={f => updateImage('left', f)} />
          <FileUpload label="Right Side" file={vehicleImages.right} onChange={f => updateImage('right', f)} />
          <FileUpload label="Registration Book" file={vehicleImages.registration} onChange={f => updateImage('registration', f)} />
          <FileUpload label="Vehicle Invoice" file={vehicleImages.invoice} onChange={f => updateImage('invoice', f)} />
        </div>
      </Section>

      {/* Motor Items */}
      {showMotorItems && (
        <Section title="Motor Items" action={
          <button onClick={() => setMotorItems([...motorItems, { item_name: '', item_value: '' }])}
            className="px-3 py-1 text-xs bg-primary text-white rounded hover:bg-primary">+ Add Item</button>
        }>
          {motorItems.length === 0 ? (
            <p className="text-sm text-ink-muted text-center py-2">No motor items added.</p>
          ) : (
            <div className="space-y-2">
              {motorItems.map((item, i) => (
                <div key={i} className="flex gap-3 items-end">
                  <div className="flex-1">
                    <InputField label={i === 0 ? 'Item Name' : ''} value={item.item_name}
                      onChange={v => {
                        const updated = [...motorItems]
                        updated[i] = { ...updated[i], item_name: v }
                        setMotorItems(updated)
                      }} placeholder="e.g. Bull Bar, Canopy" />
                  </div>
                  <div className="flex-1">
                    <InputField label={i === 0 ? 'Item Value (BWP)' : ''} type="number" value={item.item_value}
                      onChange={v => {
                        const updated = [...motorItems]
                        updated[i] = { ...updated[i], item_value: v }
                        setMotorItems(updated)
                      }} placeholder="0.00" />
                  </div>
                  <button onClick={() => setMotorItems(motorItems.filter((_, idx) => idx !== i))}
                    className="px-2 py-2 text-status-danger-fg hover:text-status-danger-fg text-sm">✕</button>
                </div>
              ))}
            </div>
          )}
        </Section>
      )}
    </div>
  )
}
