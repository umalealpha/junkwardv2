import { InputField, SelectField, FileUpload, Section, TestDataButton } from './FormField'
import { generateTestDevice } from './testData'
import type { DeviceForm, DeviceImages } from './types'

interface Props {
  device: DeviceForm
  setDevice: (v: DeviceForm) => void
  deviceImages: DeviceImages
  setDeviceImages: (v: DeviceImages) => void
  deviceBrands: any[]
  deviceModels: any[]
  deviceBrandsLoading?: boolean
  deviceModelsLoading?: boolean
  errors: Record<string, string>
}

export default function StepDevices({ device, setDevice, deviceImages, setDeviceImages, deviceBrands, deviceModels, deviceBrandsLoading, deviceModelsLoading }: Props) {
  const update = (key: keyof DeviceForm, value: any) => setDevice({ ...device, [key]: value })
  const updateImage = (key: keyof DeviceImages, file: File | null) => setDeviceImages({ ...deviceImages, [key]: file })

  return (
    <div className="space-y-6">
      <Section title="Device / Cellphone Details" action={<TestDataButton onClick={() => setDevice(generateTestDevice())} />}>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <SelectField label="Brand" value={device.brand}
            onChange={v => { setDevice({ ...device, brand: v, model_id: null, model_name: '' }) }}
            options={deviceBrands ?? []} loading={deviceBrandsLoading} />
          <SelectField label="Model" value={device.model_id}
            onChange={v => {
              const m = (deviceModels ?? []).find((dm: any) => dm.id === parseInt(v))
              setDevice({ ...device, model_id: v ? parseInt(v) : null, model_name: m?.name || '', phone_value: m?.price || device.phone_value })
            }}
            options={deviceModels ?? []} loading={deviceModelsLoading} />
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <InputField label="Model Name (if not in list)" value={device.model_name}
            onChange={v => update('model_name', v)} placeholder="Enter model name manually" />
          <InputField label="Phone Value (BWP)" type="number" value={device.phone_value}
            onChange={v => update('phone_value', v)} placeholder="0.00" />
        </div>
      </Section>

      <Section title="Device Images">
        <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
          <FileUpload label="Front" file={deviceImages.front} onChange={f => updateImage('front', f)} />
          <FileUpload label="Back" file={deviceImages.back} onChange={f => updateImage('back', f)} />
          <FileUpload label="Left Side" file={deviceImages.left} onChange={f => updateImage('left', f)} />
          <FileUpload label="Right Side" file={deviceImages.right} onChange={f => updateImage('right', f)} />
          <FileUpload label="Top" file={deviceImages.top} onChange={f => updateImage('top', f)} />
          <FileUpload label="Bottom" file={deviceImages.bottom} onChange={f => updateImage('bottom', f)} />
        </div>
      </Section>
    </div>
  )
}
