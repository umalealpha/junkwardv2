import { useState, useEffect, useRef, createContext, useContext } from 'react'
import { useNavigate, useSearchParams, useLocation } from 'react-router-dom'
import { useClaimCreateData, useCreateClaim } from '../../hooks/useClaims'
import { getStoredPermissions } from '../../api/auth'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import { useToast } from '../../components/common/Toast'
import apiClient from '../../api/client'
import { useClaimsSlaEnabled, canEditClaimSlaTimeline } from '../../hooks/useClaimsSla'
import { updateClaimSlaTimeline } from '../../api/claimsSla'
import { setClaimCommentStatus } from '../../api/claims'
import ClaimCreateStageAccordions, { type StageAccordionsHandle } from '../../components/claims/ClaimCreateStageAccordions'

// ── Form context — lets helper components defined OUTSIDE the main component
// read/write form state without being re-created on every render. Defining
// helpers inside the component caused focus loss after every keystroke because
// a new component identity is created each render ⇒ React unmounted+remounted
// the input's DOM node.
interface FormCtxValue {
  form: any
  update: (k: string, v: any) => void
  files: Record<string, File | null>
  setFile: (k: string, f: File | null) => void
}
const FormCtx = createContext<FormCtxValue | null>(null)
const useFormCtx = () => useContext(FormCtx)!

const Field = ({ k, l, type = 'text', placeholder, min, max }: any) => {
  const { form, update } = useFormCtx()
  return (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1">{l}</label>
      <input type={type} value={form[k] || ''} onChange={e => update(k, e.target.value)}
        placeholder={placeholder} min={min} max={max}
        className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
    </div>
  )
}
const Area = ({ k, l, rows = 3 }: any) => {
  const { form, update } = useFormCtx()
  return (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1">{l}</label>
      <textarea value={form[k] || ''} onChange={e => update(k, e.target.value)} rows={rows}
        className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
    </div>
  )
}
const Sel = ({ k, l, opts }: any) => {
  const { form, update } = useFormCtx()
  return (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1">{l}</label>
      <select value={form[k] || ''} onChange={e => update(k, e.target.value)}
        className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
        <option value="">-- Select --</option>
        {(opts || []).map((o: any) =>
          <option key={o.id ?? o.value ?? o} value={o.id ?? o.value ?? o}>{o.name ?? o.label ?? o}</option>
        )}
      </select>
    </div>
  )
}
const Chk = ({ k, l }: any) => {
  const { form, update } = useFormCtx()
  return (
    <label className="flex items-center gap-2 text-sm cursor-pointer">
      <input type="checkbox" checked={!!form[k]} onChange={e => update(k, e.target.checked)} className="rounded" /> {l}
    </label>
  )
}
const FileInput = ({ k, l }: any) => {
  const { files, setFile } = useFormCtx()
  return (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1">{l}</label>
      <input type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
        onChange={e => setFile(k, e.target.files?.[0] ?? null)}
        className="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-3 file:rounded-md file:border file:border-gray-300 file:text-sm file:font-medium file:bg-gray-50 file:text-gray-700 hover:file:bg-gray-100" />
      {files[k] && <p className="mt-1 text-xs text-green-600 truncate">{files[k]!.name}</p>}
    </div>
  )
}
const H = ({ children }: any) => <h3 className="text-lg font-semibold text-gray-800 border-b pb-2 mt-6">{children}</h3>

interface ClaimTypeOption { id: string; name: string; coverage_id?: number }
interface ActionOption { id: number; transactionType: string; status: string; effectiveFrom: string; effectiveTo: string }

type FormTemplate =
  | 'vehicle' | 'legal' | 'life' | 'cellphone'
  | 'tyre' | 'hospital_cash' | 'coverage_based' | ''

// Engineering coverage names carry a singular "Risk" ("Plant All Risk",
// "Contractors All Risk" — see PolicyCreateController synthetic coverage
// names), but the claim sub-form branches, the subClaimFieldsByType map, and
// the claims.claim_type ENUM all use the plural key. Canonicalise the
// normalised (upper, alphanumeric-only) claim_type so both the field render
// and the sub_claim_data routing match. Keep in lockstep with the backend
// CLAIM_TYPE_ENUM_ALIASES map in ClaimsController.
const CLAIM_TYPE_KEY_ALIASES: Record<string, string> = {
  PLANTALLRISK:       'PLANTALLRISKS',
  CONTRACTORSALLRISK: 'CONTRACTORSALLRISKS',
  // Directors & Officers — the DB coverage name is "Directors and Officers
  // Liability" (→ DIRECTORSANDOFFICERSLIABILITY); fold it (and shorthands) onto
  // the canonical key the D&O form branch + sub_claim_data routing use.
  DIRECTORSANDOFFICERSLIABILITY: 'DIRECTORSOFFICERSLIABILITY',
  DIRECTORSOFFICERS:             'DIRECTORSOFFICERSLIABILITY',
  DANDO:                         'DIRECTORSOFFICERSLIABILITY',
  DO:                            'DIRECTORSOFFICERSLIABILITY',
  // Machinery Breakdown Loss of Profit shorthands.
  MACHINERYBREAKDOWNLOP: 'MACHINERYBREAKDOWNLOSSOFPROFIT',
  MBLOSSOFPROFIT:        'MACHINERYBREAKDOWNLOSSOFPROFIT',
  MBLOP:                 'MACHINERYBREAKDOWNLOSSOFPROFIT',
  // Marine Cargo Once-Off — the DB coverage name is "Marine Once-Off Cover"
  // (→ MARINEONCEOFFCOVER). Without these the once-off marine form never
  // renders and no sub_claim_data is routed.
  MARINEONCEOFFCOVER:             'MARINECARGOONCEOFF',
  MARINECARGOONCEOFFSINGLEVOYAGE: 'MARINECARGOONCEOFF',
  MARINECARGO:                    'MARINECARGOONCEOFF',
  MARINECARGOSINGLEVOYAGE:        'MARINECARGOONCEOFF',
  // Marine Cargo Open Cover — the DB coverage name is "Marine Open Cover".
  MARINEOPENCOVER: 'MARINECARGOOPENCOVER',
  MARINECARGOOPEN: 'MARINECARGOOPENCOVER',
}

export default function ClaimCreatePage() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const location = useLocation()
  const { data: createData, isLoading } = useClaimCreateData()
  const createClaim = useCreateClaim()
  const { toast } = useToast()

  // Claims-Tracker stage accordions + comment status (additive second save
  // step). Only shown when the claims_sla feature is on AND the user may edit
  // the timeline — exactly the ClaimSlaTab gate. When off, the page behaves
  // exactly as before (nothing extra renders, submit does its single POST).
  const showStageAccordions = useClaimsSlaEnabled() && canEditClaimSlaTimeline()
  const stageRef = useRef<StageAccordionsHandle>(null)

  // Registering claims requires claim-create (enforced server-side too);
  // view-only roles reaching this URL directly get a clean denial.
  const canCreate = getStoredPermissions().includes('claim-create')

  const [form, setForm] = useState<any>({
    // ── Common ──
    policy_id: '', claim_type: '', claim_sub_type: '', claim_sub_type_id: '', category: '',
    type_of_loss: '', event_name: '', reason: '', registered_claim: new Date().toISOString().split('T')[0],
    incident_date: '', incident_time: '', incident_location: '', incident_description: '',
    reported_by: '', reported_date: new Date().toISOString().split('T')[0],
    claim_allocated_to: '' as '' | number, claim_allocated_on: new Date().toISOString().split('T')[0],

    // ── Common DOM/COM (DOMG/COMG product 7,8) classification & allocation ──
    // Mirrors the legacy shared block from V8 main.blade.php (lines ~110+).
    // Persisted to `new_claims` columns of the same names; backend already
    // accepts these keys in store()/update() validate rules.
    location_id: '',                  // FK risk_address.id (per-policy dropdown)
    service_representative_id: '',    // FK users.id
    co_attorney_involved: '',         // '0' | '1'
    dfs_complaint: '',                // '0' | '1'
    reserve_amount: '',
    paid_amount: '',
    date_first_visited: '',
    // Conditional sub-fields shown when *_involved === '1'. Mirror V8
    // main.blade.php `primary_attorney_assigned` / `co_attorney_assigned`
    // hidden divs that V8 toggles via jQuery on the radio click.
    primary_attorney_assigned_id: '', // hardcoded attorney list (V8 parity)
    p_a_assigned_date: '',
    co_attorney_assigned_id: '',
    c_a_assigned_date: '',

    // ── Motor / Vehicle / Accident ──
    vehicle_plate: '', vehicle_make: '', vehicle_model: '', vehicle_year: '',
    is_motor_claim: false, catastrophe_loss: false, attorney_involved: false,
    weather_condition: '', fault_party: '',
    // Glass-specific (damage_location is declared once below — shared
    // with another form section).
    extent: '', cause: '',
    front_image_description: '', back_image_description: '',
    right_image_description: '', left_image_description: '',
    // Accident-specific
    date_of_accident: '', place_of_accident: '', time_of_accident: '',
    driver_name: '', driver_dob: '', driver_num: '', driver_address: '',
    driver_purpose: '', driver_license: '',
    third_party: false, third_party_insured: false,
    first_name_insured: '', last_name_insured: '', cellphone_insured: '', email_insured: '', address_insured: '',
    other_first_name: '', other_last_name: '', other_cellphone: '', other_address: '',
    damage_details: '', injured_name: '', relationship_injured: '', hospital_name_injury: '', injured_details: '',
    pa_involved: false, recovery_name: '', recovery_address: '', recovery_phone: '', recovery_email: '',
    // Key Loss-specific (purpose is the V8 active field shown only for
    // non-MIS products via the form's productId gate; registered_claim
    // is declared higher up in the shared block — V8 reuses it here).
    purpose: '', key_reason: '',
    third_party_insured_elsewhere: '', driver_as_insured: '',
    is_imported_key: 'No',
    company_1: '', amount_quote_1: '', company_2: '', amount_quote_2: '',

    // ── Life ──
    date_of_death: '', cause_of_death: '', life_description: '',

    // ── Legal ──
    legal_firm: '', lawyer_name: '', legal_tel: '', legal_email: '', legaloption: '0',
    representing_member: '', lawyer_tarrif: '',
    member_name: '', membership_id: '', member_contact: '', member_email: '', lossreported_date: '',
    tariffs_1: false, tariffs_2: false,
    matter_relatesto: '', child_financial_dependent: '', idforchild: '', child_dob: '',
    realestate_enquiry_from: '', arose_date: '', matter_quantum: '', course_of_action: '',
    jurisdiction: '', criminalmatter_detail: '', criminalmatter_charge: '',
    declaration: false, nofalseinfo: false, signature: false,

    // ── Cellphone ──
    damage_extent: '', lossDate: '', device_make: '', device_model: '', device_imei: '',
    descriptionofLoss: '', contact_number: '', date_reported_to_alpha: '',
    // Insured contact block (matches legacy mobileAndElectronicDevices.blade)
    insured_name: '', email_address: '', address: '',
    // Public Liability + Liability (public_liability) — V8 column names per
    // graphiteBWV8 public_liability.blade.php. `insured_name` is shared
    // with the Mobile/Electronic Devices block above; both flows safely
    // share the same form key (one claim type filled per submission).
    insured_treding_name: '', insured_postal_address: '', insured_email: '',
    insured_telephone_no: '', insured_facsimile: '', insured_mobile_no: '',
    accident_date: '', accident_time: '', accident_incident: '', accident_injuries: '',
    accident_liability: '', accident_person: '', accident_contacted: '',
    attach_contractor: '', attach_employee: '', attach_employed: '', attach_blame: '',
    attach_circumstances: '', attach_property: '', attach_details: '', attach_owner: '', attach_damage: '',
    claim_name: '', claim_telephone: '', claim_mobile: '', claim_postal: '', claim_solicitor: '',
    witness1_name: '', witness1_telephone: '', witness1_mobile: '', witness1_postal: '', witness1_relationship: '',
    witness2_name: '', witness2_telephone: '', witness2_mobile: '', witness2_postal: '', witness2_relationship: '',
    witness2_damage: '',
    // Mobile/Electronic Devices + Office Contents — V8 column names per
    // graphiteBWV8 mobileAndElectronicDevices.blade.php. property_stolen_damaged
    // and is_sole_owner_of_property/sole_owner_of_property are already
    // declared in the All Risks block above and shared between both flows.
    telephone_no: '', date_time_loss_discovered: '', whom_discovered: '',
    // Fire (fire_claim) — V8 column names per fire.blade.php.
    // police_station_name + premises_properly_secured are already in the
    // Burglary block (shared form key, only one claim type active per
    // submission). contract_of_agreement is a file upload, handled via
    // the FormData files state on submit.
    address_of_theft_occurred: '', date_time_of_theft: '',
    anyone_during_burglary: '', details_during_burglary: '',
    days_premises_unoccupied: '',
    premises_guarded_by_watchman: '', name_of_guard: '', telephone_of_guard: '',
    guard_during_fire: '', name_of_security_agent: '',
    suspect_any_person: '', suspect_person_details: '',
    total_value_premises_buildings: '',
    other_insurance_against_fire: '', insurance_against_fire_details: '',
    estimated_amount_of_damaged: '', details_of_previous_loss: '',

    // ── Tyre ──
    tyre_count: '', tyre_size: '', damage_location: '',

    // ── Hospital Cash ──
    patient_name: '', patient_dob: '',
    // Patient identity: citizen flag routes the user to either Omang or Passport.
    // `patient_identity_number` remains the column the backend persists — we just
    // mirror whichever of patient_omang / patient_passport is populated into it
    // on submit.
    patient_is_citizen: '', patient_omang: '', patient_passport: '',
    patient_identity_number: '',
    relationship: '', relationship_other: '', occupation_date: '',
    gp_name: '', gp_postal_address: '', gp_cellular_no: '', gp_telephone_no: '', gp_fax_no: '',
    hospital_name: '', hospital_tel_fax: '', admitting_doctor: '', admitting_doctor_tel_fax: '',
    admission_date: '', admission_time: '', discharge_date: '', discharge_time: '',
    hospitalisation_type: '', accident_reported: '', symptoms_first_appeared: '',
    pregnancy_conception_date: '', pregnancy_delivery_date: '', injury_date: '',
    accident_circumstances: '', first_consultation_date: '',
    is_medical_scheme: '', medical_scheme_name: '', medical_aid_number: '',
    has_other_insurance: '', other_insurance_company_name: '', other_insurance_policy_numbers: '',

    // ── DOMG/COMG claim-type-specific (written to per-type legacy sub-tables
    //    via sub_claim_data on submit) ─────────────────────────────────
    // Business Interruption (business_interruption)
    nature_of_interruption: '', details_and_estimated_amount_of_loss: '',
    previously_suffered_loss: '', other_party_interest: '', other_insurance_covering: '',
    // Burglary / Theft (burglary)
    address_of_premises: '', description_of_incident: '', date_time_police_advised: '',
    anyone_on_premises: '', anyone_on_premises_brief: '', guarded_by_watchman: '',
    premises_properly_secured: '', total_value_contents_of_premises: '', stock_books_records_located: '',
    // Fidelity Guarantee (fidelity_guarantee)
    employees_been_involved: '', circumstances: '', defaulting_employees_name: '',
    // Property Loss / Damage (property_loss_damage) — V8 column names
    // per graphiteBWV8 property_loss_damage.blade.php. The earlier
    // `property_description`/`loss_damage_date`/etc. were stale and
    // didn't match V8 columns. `previously_suffered_loss` and
    // `other_insurance_covering` already declared in the Business
    // Interruption block above; both flows safely share form keys.
    loss_damage_discovered: '', loss_damage_occurred: '',
    premises_occupied: '', last_occupied: '', purpose_of_occupation: '',
    nature_interruption: '', loss_for_each_item: '',
    give_details: '', name_of_insurer: '', reference_no_station: '',
    interest_insured_property: '', give_name_insurer: '',
    value_all_property: '', when_last_valued: '',
    // Public Liability (public_liability)
    injured_party_name: '', injured_party_address: '', injury_description: '',
    date_time_incident: '', witness_name: '', witness_contact: '',
    // Workers Compensation / Stated Benefits (workers_compensation) —
    // V8 column names per graphiteBWV8 workers_compensation.blade.php.
    // EMPLOYER section is commented out in V8's blade; we skip those.
    // `injured_name` is already declared above (motor third-party block);
    // both flows safely share the same form key.
    injured_age: '', injured_address: '', injured_status: '',
    injured_occupation: '', injured_nationality: '', injured_service_period: '',
    your_direct_employ: '', address_of_contractor: '',
    date: '', time: '', place: '',
    how_accident_occur: '', first_report_accident: '', period_of_disablement: '',
    // Defective Workmanship (defective_workmanship) — V8 column names per
    // graphiteBWV8 defective_workmanship.blade.php. `address` is already
    // declared above (Mobile/Electronic Devices block); both flows safely
    // share the same form key (one claim type active per submission).
    location_of_accident: '', accident_date_time: '',
    owners_name: '', telephone_number: '', mobile_number: '',
    make: '', model: '', registration: '',
    vehicle_drivable: '', vehicle_handed_claimant: '',
    when_vehicle_handed: '', allegations_received: '',
    // Goods In Transit (goods_in_transit_claim) — mirrors graphiteBWV8
    // resources/views/admin/claims/newClaims/types/goods_in_transit.blade.php.
    // Field keys = DB column names so the backend's array_intersect_key
    // routing in ClaimsController::store/update lands them directly.
    // NOTE: `date_time_police_advised` is already declared in the Burglary
    // block above and shared between both claim types — do not redeclare.
    address_of_premises_loss: '', details_of_driver: '', property_last_seen: '',
    date_time_of_loss: '', brief_description_incident: '',
    police_station_name: '', witnesses_name: '', witnesses_mobile_number: '',
    total_value_of_loss: '', consignment_transported_to: '', consignment_from: '',
    vehicle_registration_number: '',
    is_carrier_contracted: '', carrier_has_own_GIT_ins: '',
    other_insurance_against_theft: '', insurance_against_theft_details: '',
    details_of_previous_loss_records: '',
    // All Risk / Electronic Equipment (all_risk_and_electronic_equipment)
    property_stolen_damaged: '', circumstances_loss_damage: '',
    thorough_search_made_for_article: '', loss_cause: '', loss_by_other_cause: '',
    stolenfromcar_unlockedpremises: '', sole_owner_of_property: '', is_sole_owner_of_property: '',
  })
  const [files, setFiles] = useState<{ [key: string]: File | null }>({
    document_1: null, document_2: null, document_3: null,
    death_certificate: null, police_affidavit: null, quote_1: null, quote_2: null,
    cell_phone_front: null, cell_phone_back: null, cell_phone_left: null,
    cell_phone_right: null, cell_phone_top: null, cell_phone_bottom: null,
    incidentFront: null, incidentBack: null, incidentRight: null, incidentLeft: null,
    // Goods In Transit: contract upload (only required when carrier is contracted)
    copy_of_contract: null,
    // Fire: security agent contract (always optional in V8 fire.blade.php)
    contract_of_agreement: null,
    // Contractors All Risks / PL: two S3-uploaded files
    works_claim_documentary_evidence: null,
    works_claim_bill_of_quantities: null,
  })
  const [errors, setErrors] = useState<Record<string, string>>({})

  // Product-specific state
  const [productType, setProductType] = useState<string>('')
  // Track the numeric product_id alongside `productType` so the Glass
  // sub-form can branch on MIS (product 2 = TP, product 3 = MotorComp)
  // vs DOM/COM / Specialist. MIS Glass keeps V2's original extensions
  // only (damage_location + replacement quotes); DOM/COM Glass adds the
  // V8 "Damage Photos & Descriptions" section on top.
  const [productId, setProductId] = useState<number | null>(null)
  // Motor Accident / Motor Traders nested payloads. graphiteBWV8
  // accident.blade.php has two repeaters (passengers injured + third
  // party details, the latter with nested "third party insured"
  // sub-fields). FormData submission packs these as PHP-style array
  // notation accident_passengers[0][name] / accident_third_parties[0][
  // first_name], etc. — matching the backend's update() validator
  // rules that the store() endpoint now also accepts.
  type PassengerRow = { name: string; address: string; injury: string }
  type ThirdPartyRow = {
    first_name: string; last_name: string; cellphone: string; address: string;
    make: string; model: string; registration_no: string; damage_details: string;
    injured_name: string; relationship: string; hospital_name: string; injured_details: string;
    third_party_insured: boolean;
    first_name_insured: string; last_name_insured: string;
    cellphone_insured: string; email_insured: string; address_insured: string;
  }
  const blankPassenger = (): PassengerRow => ({ name: '', address: '', injury: '' })
  const blankThirdParty = (): ThirdPartyRow => ({
    first_name: '', last_name: '', cellphone: '', address: '',
    make: '', model: '', registration_no: '', damage_details: '',
    injured_name: '', relationship: '', hospital_name: '', injured_details: '',
    third_party_insured: false,
    first_name_insured: '', last_name_insured: '',
    cellphone_insured: '', email_insured: '', address_insured: '',
  })
  const [motorPassengers, setMotorPassengers] = useState<PassengerRow[]>([])
  const [motorThirdParties, setMotorThirdParties] = useState<ThirdPartyRow[]>([])
  const [recoveryInvolved, setRecoveryInvolved] = useState(false)
  const [formTemplate, setFormTemplate] = useState<FormTemplate>('')
  const [dynamicClaimTypes, setDynamicClaimTypes] = useState<ClaimTypeOption[]>([])
  // Policy-level claim types cached from the initial /claim-types-by-policy
  // call. Used as a fallback when switching action/term returns an empty
  // list so the claim type dropdown doesn't disappear and leave the user
  // stuck (previous bug: change term → claim type hidden → no way back
  // short of blanking the policy number).
  const [policyLevelClaimTypes, setPolicyLevelClaimTypes] = useState<ClaimTypeOption[]>([])
  const [actionHadTypes, setActionHadTypes] = useState<boolean>(true)
  const [policyActions, setPolicyActions] = useState<ActionOption[]>([])
  const [selectedActionId, setSelectedActionId] = useState<string>('')
  const [loadingTypes, setLoadingTypes] = useState(false)
  const [policyInfo, setPolicyInfo] = useState<string>('')

  const update = (key: string, value: any) => {
    setForm((prev: any) => ({ ...prev, [key]: value }))
    setErrors((prev: any) => ({ ...prev, [key]: '' }))
  }
  const setFile = (key: string, f: File | null) => setFiles(p => ({ ...p, [key]: f }))

  const [debounceTimer, setDebounceTimer] = useState<ReturnType<typeof setTimeout> | null>(null)

  // Per-policy risk addresses for the DOM/COM "Select Location" dropdown.
  // Source: GET /api/v1/policies/{id}/risk-addresses (already exposed).
  // V8 reads `$riskAddress` as the loop source for the dropdown options
  // (resources/views/admin/claims/newClaims/main.blade.php:114). Stored as
  // `new_claims.location_id` once the claim is registered.
  //
  // Use `resolvedPolicyId` (set by fetchClaimTypes once the backend
  // resolves a policyNumber → numeric id) instead of `form.policy_id`,
  // because the user types either an id ("212962") or a policy number
  // ("COMG2026212962"). The risk-addresses endpoint requires a numeric id.
  const [resolvedPolicyId, setResolvedPolicyId] = useState<number | null>(null)
  const [policyRiskAddresses, setPolicyRiskAddresses] = useState<Array<{ id: number; name: string }>>([])
  useEffect(() => {
    if (!resolvedPolicyId) { setPolicyRiskAddresses([]); return }
    apiClient.get(`/policies/${resolvedPolicyId}/risk-addresses`)
      .then(r => {
        const rows: any[] = r.data?.data ?? []
        setPolicyRiskAddresses(rows.map((a: any) => ({
          id: a.id,
          // V2's PolicyController::riskAddresses returns camelCase keys
          // (addressName, physical_address, address). Try them in order
          // of human-friendliness. Fall back to a synthetic label so the
          // dropdown is never opaque (...) for an address that has data
          // but no display name set.
          name: a.addressName
             || a.address
             || a.physical_address
             || a.address_name
             || [a.city, a.state].filter(Boolean).join(', ')
             || `Address #${a.id}`,
        })))
      })
      .catch(() => setPolicyRiskAddresses([]))
  }, [resolvedPolicyId])

  // Per-policy vehicles for the motor claim "Select Motor (Registration No)"
  // dropdown. Source: GET /policies/{id}/vehicles?action_id= (already exposed;
  // the policy wizard uses the same endpoint). Scoping by action_id lists
  // exactly the vehicles covered under the SELECTED policy action/term; with
  // no term chosen the endpoint falls back to the latest action's vehicles.
  // Value is the registration plate so it round-trips through form.vehicle_plate
  // (the same column the free-text field wrote before).
  const [policyVehicles, setPolicyVehicles] = useState<Array<{ id: string; name: string }>>([])
  useEffect(() => {
    if (!resolvedPolicyId) { setPolicyVehicles([]); return }
    const params: Record<string, string> = {}
    if (selectedActionId) params.action_id = selectedActionId
    apiClient.get(`/policies/${resolvedPolicyId}/vehicles`, { params })
      .then(r => {
        const rows: any[] = r.data?.data ?? []
        setPolicyVehicles(
          rows
            .filter(v => v?.vehiclePlate)
            .map((v: any) => ({
              id: v.vehiclePlate,
              name: [v.vehiclePlate, [v.make, v.model].filter(Boolean).join(' ')]
                .filter(Boolean)
                .join(' — '),
            }))
        )
      })
      .catch(() => setPolicyVehicles([]))
  }, [resolvedPolicyId, selectedActionId])

  // Accept a ?policy_id= query param so the Policy Detail page's
  // "Register Claim" button can deep-link into the form with the
  // policy pre-filled and the claim-type lookup already running.
  useEffect(() => {
    const deepLinked = searchParams.get('policy_id')
    if (deepLinked && !form.policy_id) handlePolicyChange(deepLinked)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  function handlePolicyChange(policyId: string) {
    update('policy_id', policyId)
    setDynamicClaimTypes([]); setPolicyLevelClaimTypes([])
    setPolicyActions([]); setSelectedActionId(''); setActionHadTypes(true)
    setProductType(''); setFormTemplate(''); setPolicyInfo(''); setProductId(null)
    setResolvedPolicyId(null)
    update('claim_type', '')
    if (!policyId || policyId.length < 2) return
    if (debounceTimer) clearTimeout(debounceTimer)
    setDebounceTimer(setTimeout(() => fetchClaimTypes(policyId), 600))
  }

  async function fetchClaimTypes(policyId: string) {
    setLoadingTypes(true)
    try {
      const { data } = await apiClient.get('/claims/claim-types-by-policy', { params: { policy: policyId } })
      const d = data.data
      const types: ClaimTypeOption[] = d.claim_types ?? []
      setProductType(d.product_type)
      setProductId(typeof d.product_id === 'number' ? d.product_id : null)
      setFormTemplate((d.form_template ?? '') as FormTemplate)
      // Backend echoes back the resolved numeric policy id (handles both
      // "212962" and "COMG2026212962" inputs). Drives the risk-addresses
      // fetch for the DOM/COM "Select Location" dropdown.
      setResolvedPolicyId(typeof d.policy_id === 'number' ? d.policy_id : null)
      setDynamicClaimTypes(types)
      // Cache these as the policy-level fallback. Selecting a specific
      // action/term can narrow this list, but if that query returns
      // nothing we restore from here so the dropdown never disappears.
      setPolicyLevelClaimTypes(types)
      setActionHadTypes(true)
      setPolicyActions(d.actions ?? [])
      setPolicyInfo(`Product: ${d.product_type} (ID: ${d.product_id})`)
    } catch (e: any) {
      setPolicyInfo(e.response?.data?.message || 'Policy not found')
      setDynamicClaimTypes([]); setPolicyLevelClaimTypes([])
    } finally { setLoadingTypes(false) }
  }

  async function handleActionChange(actionId: string) {
    setSelectedActionId(actionId); update('claim_type', '')
    if (!actionId || !form.policy_id) return
    setLoadingTypes(true)
    try {
      const { data } = await apiClient.get(`/claims/policy/${form.policy_id}/claim-types-by-action/${actionId}`)
      const actionTypes: ClaimTypeOption[] = data.data ?? []
      if (actionTypes.length > 0) {
        // Normal path — the selected action has its own coverage-based
        // claim types.
        setDynamicClaimTypes(actionTypes)
        setActionHadTypes(true)
      } else {
        // Action exists but has no bound coverages (e.g. a fresh renewal
        // or an approval action that inherits). Fall back to the full
        // policy-level list so the user is never locked out. Surface a
        // hint via actionHadTypes so we can render a "no action-specific
        // types — showing policy-wide list" note on the dropdown.
        setDynamicClaimTypes(policyLevelClaimTypes)
        setActionHadTypes(false)
      }
    } catch {
      // Don't zero the list on error — keep whatever the user had so
      // they can still submit. The old behaviour of dropping to [] made
      // the dropdown disappear permanently.
      setDynamicClaimTypes(policyLevelClaimTypes)
      setActionHadTypes(false)
    }
    finally { setLoadingTypes(false) }
  }

  // React to action changes from the _action dropdown (set via Sel helper)
  useEffect(() => {
    if (form._action && form._action !== selectedActionId) handleActionChange(form._action)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [form._action])

  // Prefill policy when navigated from Policy Detail → Claims tab → Register Claim
  useEffect(() => {
    const pn = (location.state as { policyNumber?: string } | null)?.policyNumber
    if (pn && !form.policy_id) handlePolicyChange(pn)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    const errs: Record<string, string> = {}
    if (!form.policy_id) errs.policy_id = 'Policy ID is required'
    if (!form.claim_type) errs.claim_type = 'Claim type is required'

    // ── Goods In Transit field-level validation (V8 parity) ──────────
    // The backend re-validates these; this is purely UX. Toggles use
    // string '1' / '0' to match how radio inputs serialize, mirroring
    // V8's blade. Conditional fields (copy_of_contract,
    // insurance_against_theft_details) are required only when their
    // controlling radio is "Yes".
    const claimTypeKey = (form.claim_type || '').toUpperCase().replace(/[^A-Z0-9]/g, '')
    if (claimTypeKey === 'GOODSINTRANSIT') {
      const requiredGitFields: Array<[string, string]> = [
        ['address_of_premises_loss', 'Address of premises is required'],
        ['details_of_driver', 'Carrier/driver details are required'],
        ['property_last_seen', 'Property last seen is required'],
        ['date_time_of_loss', 'Date and time of loss are required'],
        ['brief_description_incident', 'Brief description of incident is required'],
        ['date_time_police_advised', 'Date/time police were advised is required'],
        ['police_station_name', 'Police station name is required'],
        ['witnesses_name', 'Witness names are required'],
        ['witnesses_mobile_number', 'Witness mobile number is required'],
        ['total_value_of_loss', 'Total value of loss is required'],
        ['consignment_transported_to', 'Consignment destination is required'],
        ['consignment_from', 'Consignment origin is required'],
        ['vehicle_registration_number', 'Vehicle registration is required'],
        ['is_carrier_contracted', 'Please select whether the carrier is contracted'],
        ['carrier_has_own_GIT_ins', 'Please select whether carrier has GIT insurance'],
        ['other_insurance_against_theft', 'Please select whether other theft insurance exists'],
        ['details_of_previous_loss_records', 'Previous loss records are required'],
      ]
      for (const [k, msg] of requiredGitFields) {
        if (!form[k] && form[k] !== 0) errs[k] = msg
      }
      if (form.is_carrier_contracted === '1' && !files.copy_of_contract) {
        errs.copy_of_contract = 'Carrier contract file is required when the carrier is contracted'
      }
      if (form.other_insurance_against_theft === '1' && !form.insurance_against_theft_details) {
        errs.insurance_against_theft_details = 'Please provide brief details of the other insurance'
      }
    }

    if (Object.keys(errs).length > 0) { setErrors(errs); return }

    try {
      // Mirror the citizen-gated Omang/Passport into the single column the DB keeps.
      const submission: Record<string, any> = { ...form }
      if (form.patient_is_citizen === 'yes') submission.patient_identity_number = form.patient_omang
      if (form.patient_is_citizen === 'no')  submission.patient_identity_number = form.patient_passport

      // The Policy Action / Term dropdown stores its selection on `form._action`
      // (a UI-only key prefixed with `_` so it doesn't get blindly forwarded).
      // Promote it to `policy_action_id` so the backend persists it on the
      // claim row — graphiteBWV8 stores claims.policy_action_id which the
      // Reserves tab later uses to scope the coverage list.
      if (form._action) submission.policy_action_id = form._action
      delete submission._action

      // Package DOMG/COMG claim-type-specific fields into sub_claim_data.
      // The backend writes this payload to the matching legacy sub-table
      // (business_interruption / burglary / fidelity_guarantee / …) keyed
      // by newclaim_id. Only the fields relevant to the selected claim
      // type are sent so the backend's array_intersect_key doesn't strip
      // half of them silently.
      const subClaimFieldsByType: Record<string, string[]> = {
        BUSINESSINTERRUPTION: [
          'previously_suffered_loss', 'other_party_interest', 'other_insurance_covering',
        ],
        BURGLARY: [
          'address_of_premises', 'description_of_incident', 'date_time_police_advised',
          'anyone_on_premises', 'anyone_on_premises_brief', 'guarded_by_watchman',
          'premises_properly_secured', 'total_value_contents_of_premises', 'stock_books_records_located',
        ],
        THEFT: [
          'address_of_premises', 'description_of_incident', 'date_time_police_advised',
          'anyone_on_premises', 'anyone_on_premises_brief', 'guarded_by_watchman',
          'premises_properly_secured', 'total_value_contents_of_premises', 'stock_books_records_located',
        ],
        BURGLARYTHEFT: [
          'address_of_premises', 'description_of_incident', 'date_time_police_advised',
          'anyone_on_premises', 'anyone_on_premises_brief', 'guarded_by_watchman',
          'premises_properly_secured', 'total_value_contents_of_premises', 'stock_books_records_located',
        ],
        // V8 main.blade.php groups MONEY onto burglary.blade.php + the
        // burglary table. Same field set as the THEFT/BURGLARY aliases.
        MONEY: [
          'address_of_premises', 'description_of_incident', 'date_time_police_advised',
          'anyone_on_premises', 'anyone_on_premises_brief', 'guarded_by_watchman',
          'premises_properly_secured', 'total_value_contents_of_premises', 'stock_books_records_located',
        ],
        FIDELITYGUARANTEE: [
          'employees_been_involved', 'circumstances', 'defaulting_employees_name',
        ],
        // graphiteBWV8 main.blade.php groups six "property" aliases onto
        // property_loss_damage.blade.php + the property_loss_damage table.
        // V8 column names from create_property_loss_damage migration.
        // Earlier `property_description`/`loss_damage_date`/etc. were stale
        // — they didn't match V8 columns or V2 form fields, so submitted
        // PLD data was silently dropped. Note: `previously_suffered_loss`
        // and `other_insurance_covering` are FREE-TEXT here (V8 stores
        // them as text on this table), not Yes/No like in BI.
        ...(['PROPERTYLOSSDAMAGE','PROPERTYDAMAGE','BUILDINGSCOMBINED',
            'ACCIDENTALDAMAGE','HOUSEHOLDERS','HOUSEOWNERS',
            'HOUSEOWNERBUILDINGS','HOUSEHOLDERSCONTENTS'].reduce((acc, key) => {
          acc[key] = [
            'loss_damage_discovered', 'loss_damage_occurred',
            'premises_occupied', 'last_occupied', 'purpose_of_occupation',
            'nature_interruption', 'loss_for_each_item',
            'previously_suffered_loss', 'give_details', 'name_of_insurer',
            'reference_no_station', 'interest_insured_property',
            'other_insurance_covering', 'give_name_insurer',
            'value_all_property', 'when_last_valued',
          ]
          return acc
        }, {} as Record<string, string[]>)),
        // graphiteBWV8 main.blade.php groups PUBLICLIABILITY and LIABILITY
        // onto the same public_liability.blade.php + public_liability table.
        // V8 column names from the create_public_liability migration.
        // The earlier `injured_party_*` / `witness_name` keys were stale —
        // they didn't match either V8 columns or V2's actual form fields,
        // so submitted PL data was silently dropped.
        PUBLICLIABILITY: [
          'insured_name', 'insured_treding_name', 'insured_postal_address',
          'insured_email', 'insured_telephone_no', 'insured_facsimile', 'insured_mobile_no',
          'accident_date', 'accident_time', 'accident_incident', 'accident_injuries',
          'accident_liability', 'accident_person', 'accident_contacted',
          'attach_contractor', 'attach_employee', 'attach_employed', 'attach_blame',
          'attach_circumstances', 'attach_property', 'attach_details', 'attach_owner', 'attach_damage',
          'claim_name', 'claim_telephone', 'claim_mobile', 'claim_postal', 'claim_solicitor',
          'witness1_name', 'witness1_telephone', 'witness1_mobile', 'witness1_postal', 'witness1_relationship',
          'witness2_name', 'witness2_telephone', 'witness2_mobile', 'witness2_postal', 'witness2_relationship',
          'witness2_damage',
        ],
        LIABILITY: [
          'insured_name', 'insured_treding_name', 'insured_postal_address',
          'insured_email', 'insured_telephone_no', 'insured_facsimile', 'insured_mobile_no',
          'accident_date', 'accident_time', 'accident_incident', 'accident_injuries',
          'accident_liability', 'accident_person', 'accident_contacted',
          'attach_contractor', 'attach_employee', 'attach_employed', 'attach_blame',
          'attach_circumstances', 'attach_property', 'attach_details', 'attach_owner', 'attach_damage',
          'claim_name', 'claim_telephone', 'claim_mobile', 'claim_postal', 'claim_solicitor',
          'witness1_name', 'witness1_telephone', 'witness1_mobile', 'witness1_postal', 'witness1_relationship',
          'witness2_name', 'witness2_telephone', 'witness2_mobile', 'witness2_postal', 'witness2_relationship',
          'witness2_damage',
        ],
        // graphiteBWV8 main.blade.php groups WORKERSCOMPENSATION and
        // STATEDBENEFITS onto workers_compensation.blade.php + the
        // workers_compensation table. V8 column names; the EMPLOYER block
        // is commented out in V8's blade so we don't ship those columns.
        WORKERSCOMPENSATION: [
          'injured_name', 'injured_age', 'injured_address', 'injured_status',
          'injured_occupation', 'injured_nationality', 'injured_service_period',
          'your_direct_employ', 'address_of_contractor',
          'date', 'time', 'place',
          'how_accident_occur', 'first_report_accident', 'period_of_disablement',
        ],
        STATEDBENEFITS: [
          'injured_name', 'injured_age', 'injured_address', 'injured_status',
          'injured_occupation', 'injured_nationality', 'injured_service_period',
          'your_direct_employ', 'address_of_contractor',
          'date', 'time', 'place',
          'how_accident_occur', 'first_report_accident', 'period_of_disablement',
        ],
        // graphiteBWV8 defective_workmanship.blade.php — V8 column names
        // for the defective_workmanship table.
        DEFECTIVEWORKMANSHIP: [
          'location_of_accident', 'accident_date_time', 'owners_name',
          'telephone_number', 'mobile_number', 'address',
          'make', 'model', 'registration', 'vehicle_drivable',
          'vehicle_handed_claimant', 'when_vehicle_handed', 'allegations_received',
        ],
        // Goods In Transit (goods_in_transit_claim) — V8 parity. The
        // copy_of_contract file is sent via FormData files state, NOT here.
        GOODSINTRANSIT: [
          'address_of_premises_loss', 'details_of_driver', 'property_last_seen',
          'date_time_of_loss', 'brief_description_incident', 'date_time_police_advised',
          'police_station_name', 'witnesses_name', 'witnesses_mobile_number',
          'total_value_of_loss', 'consignment_transported_to', 'consignment_from',
          'vehicle_registration_number', 'is_carrier_contracted',
          'carrier_has_own_GIT_ins', 'other_insurance_against_theft',
          'insurance_against_theft_details', 'details_of_previous_loss_records',
        ],
        // graphiteBWV8 main.blade.php groups four claim_type aliases onto
        // the same all_risk_and_electronic_equipment table. Register each
        // key so the FE ships the same field set regardless of which alias
        // the operator picked (V8 used the same blade for all four).
        BUSINESSALLRISKS: [
          'property_stolen_damaged',
          'thorough_search_made_for_article', 'loss_cause', 'loss_by_other_cause',
          'stolenfromcar_unlockedpremises', 'sole_owner_of_property', 'is_sole_owner_of_property',
        ],
        ELECTRONICEQUIPMENT: [
          'property_stolen_damaged',
          'thorough_search_made_for_article', 'loss_cause', 'loss_by_other_cause',
          'stolenfromcar_unlockedpremises', 'sole_owner_of_property', 'is_sole_owner_of_property',
        ],
        PERSONALALLRISKS: [
          'property_stolen_damaged',
          'thorough_search_made_for_article', 'loss_cause', 'loss_by_other_cause',
          'stolenfromcar_unlockedpremises', 'sole_owner_of_property', 'is_sole_owner_of_property',
        ],
        ALLRISK: [
          'property_stolen_damaged',
          'thorough_search_made_for_article', 'loss_cause', 'loss_by_other_cause',
          'stolenfromcar_unlockedpremises', 'sole_owner_of_property', 'is_sole_owner_of_property',
        ],
        // graphiteBWV8 fire.blade.php — V8 column names for fire_claim.
        // contract_of_agreement is the S3-uploaded security agent contract;
        // the FormData files state ships it separately on submit.
        FIRE: [
          'address_of_theft_occurred', 'property_last_seen', 'date_time_of_theft',
          'brief_description_incident', 'date_time_police_advised', 'police_station_name',
          'anyone_during_burglary', 'details_during_burglary',
          'days_premises_unoccupied',
          'premises_guarded_by_watchman', 'name_of_guard', 'telephone_of_guard', 'guard_during_fire',
          'name_of_security_agent',
          'premises_properly_secured',
          'suspect_any_person', 'suspect_person_details',
          'total_value_premises_buildings',
          'other_insurance_against_fire', 'insurance_against_fire_details',
          'estimated_amount_of_damaged', 'details_of_previous_loss',
        ],
        // graphiteBWV8 erection_all_risk.blade.php — V8 column names for
        // the erection_all_risk_claims table. Five sections; three Yes/No
        // fields gate conditional follow-up textareas.
        ERECTIONALLRISK: [
          'insured_occupation', 'period_from', 'period_to', 'supervisor_engineer_name',
          'date_of_occurrence', 'time_of_occurrence',
          'site_of_damage', 'nearest_railway_station',
          'damage_contract_works', 'damage_plant_equipment', 'damage_third_party_property',
          'cause_of_damage',
          'responsible_for_damage', 'responsible_for_damage_details',
          'possibility_of_recovery', 'recovery_details',
          'how_damage_occurred', 'probable_cause', 'progress_of_construction', 'how_items_repaired',
          'alterations_during_repairs',
          'witness_name', 'witness_address',
          'surrounding_properties_damaged',
          'third_party_liability', 'third_party_liability_details',
          'estimated_cost_contract_works', 'estimated_cost_plant_machinery',
          'estimated_cost_third_party_property', 'estimated_cost_owners_surrounding',
          'other_insurance_details', 'previous_losses_details',
        ],
        // graphiteBWV8 contractors_all_risks_public_liability.blade.php —
        // V8 column names. Two file uploads (works_claim_documentary_evidence
        // + works_claim_bill_of_quantities) are shipped via FormData files
        // state, NOT here. Three claim_type aliases all route to the same
        // table.
        ...(['CONTRACTORSALLRISKS','CONTRACTORSALLRISKSPUBLICLIABILITY','CARPL'].reduce((acc, key) => {
          acc[key] = [
            'responsible_person_name', 'responsible_person_phone',
            'responsible_person_cellphone', 'responsible_person_email',
            'responsible_person_fax',
            'parties_to_contract', 'contract_value', 'contract_number',
            'description_of_contract', 'site_physical_address', 'code',
            'contract_commencement_date', 'expected_contract_completion_date',
            'responsible_contract_works_claim', 'responsible_public_liability_claim',
            'loss_date', 'loss_time', 'loss_details', 'cause_of_loss',
            'party_responsible_name', 'party_responsible_contact',
            'estimated_cost_of_repair_replacement',
            'police_station', 'police_reference',
          ]
          return acc
        }, {} as Record<string, string[]>)),
        // graphiteBWV8 mobileAndElectronicDevices.blade.php — V8 column
        // names for the mobile_and_electronic_devices_claim table. V8 also
        // groups OFFICECONTENTS onto the same blade + table, so register
        // both keys with the same field set.
        MOBILEELECTRONICDEVICES: [
          'insured_name', 'email_address', 'address', 'telephone_no',
          'property_stolen_damaged', 'date_time_loss_discovered', 'whom_discovered',
          'is_sole_owner_of_property', 'sole_owner_of_property',
        ],
        OFFICECONTENTS: [
          'insured_name', 'email_address', 'address', 'telephone_no',
          'property_stolen_damaged', 'date_time_loss_discovered', 'whom_discovered',
          'is_sole_owner_of_property', 'sole_owner_of_property',
        ],
        // graphiteBWV8 admin/policy/key_loss.blade.php — the full legacy
        // key-loss form for the key_loss_claim sub-table. The three file
        // uploads (police_affidavit, quote_1, quote_2) ship via FormData
        // files state, not via sub_claim_data, so they're omitted here.
        // `registered_claim` is shared with the top-level new_claims column —
        // it's dual-written when submitted (column-intersect routes both).
        // Register both keys: MIS Key Loss ships claim_type 'Key Loss'
        // (→ KEYLOSS) while DOM/COM ships the V8 code 'Locks & Keys'
        // (→ LOCKSANDKEYS). Both dispatch to key_loss_claim on the backend,
        // so both need the same sub_claim_data field routing here.
        ...(['LOCKSANDKEYS', 'KEYLOSS'].reduce((acc, key) => {
          acc[key] = [
            'financial_interest', 'chassis_num', 'purpose', 'key_reason', 'estimate',
            'lossDate', 'descriptionofLoss', 'registered_claim',
            'name_of_insured', 'insured_address', 'insured_occupation',
            'insured_email', 'insured_contact_no',
            'vehicle_plate', 'is_imported', 'make', 'year', 'model',
            'company_1', 'amount_quote_1', 'company_2', 'amount_quote_2',
          ]
          return acc
        }, {} as Record<string, string[]>)),
        // graphiteBWV8 glass.blade.php — V8 column names for the glass_claim
        // (singular) sub-claim table. The 6 file uploads (incidentFront/Back/
        // Right/Left, quote_1, quote_2) ship via FormData files state on
        // submit, not via sub_claim_data, so they're omitted here.
        // damage_location, company_*, amount_quote_* are V2-only extensions
        // already on the existing Glass form — wiring them up ensures the
        // operator's input persists.
        GLASS: [
          'incident_date', 'extent', 'cause', 'damage_location',
          'company_1', 'amount_quote_1',
          'company_2', 'amount_quote_2',
          'front_image_description', 'back_image_description',
          'right_image_description', 'left_image_description',
        ],
        // graphiteBWV8 medical_malpractice.blade.php — V8 column names for
        // the medical_malpractice_claims table. Four sections; the 5
        // Section-4 file uploads (notification_letter, patient_records,
        // investigation_reports, correspondence, expert_legal_opinions)
        // ship via FormData files state on submit, not via sub_claim_data,
        // so they're omitted here. `insured_email` is shared with PI and
        // Public Liability — fine because only one claim type per
        // submission. V2 surfaces Section 1 fields in the create form
        // even though V8 blade left them orphan-column on the controller.
        MEDICALMALPRACTICE: [
          'insured_full_name', 'professional_title_role', 'license_registration_number',
          'facility_practice_name', 'address_of_practice',
          'insured_contact_number', 'insured_email',
          'claimant_full_name', 'claimant_date_of_birth',
          'claimant_contact_number', 'claimant_mailing_address',
          'date_of_alleged_incident', 'nature_of_services_provided',
          'date_of_notification', 'how_notified', 'description_of_allegation',
        ],
        // graphiteBWV8 plant_all_risks.blade.php — V8 column names for the
        // plant_all_risks_claims table. Four sections (Responsible Person on
        // Site / Site Details / Plant Details / Loss-Damage Details). Three
        // Yes/No booleans (uneconomical_to_repair / subject_to_finance /
        // on_hire_at_time). `responsible_person_*` keys are shared with
        // CARPL; `site_physical_address`, `party_responsible_*`,
        // `cause_of_loss`, `police_station`, `police_reference` are
        // shared with CARPL too — safe because only one claim type per
        // submission. `documentary_evidence` is omitted: V8 populates it
        // via a separate attachments UI which V2 already provides as a
        // generic claim attachments tab.
        PLANTALLRISKS: [
          'responsible_person_name', 'responsible_person_phone',
          'responsible_person_cellphone', 'responsible_person_email',
          'responsible_person_fax',
          'site_physical_address', 'site_code',
          'item_description', 'item_number_sum_insured',
          'date_of_loss', 'time_of_loss',
          'details_of_loss', 'cause_of_loss',
          'party_responsible_name', 'party_responsible_contact',
          'estimated_cost',
          'uneconomical_to_repair', 'subject_to_finance', 'on_hire_at_time',
          'police_station', 'police_reference',
        ],
        // Machinery Breakdown (form AD-CLM-MB-001) — machinery_breakdown_claims
        // columns, grouped by the PDF sections. Yes/No questions ship as
        // '1'/'0' strings; the free-text detail that follows each is its own
        // key. Some keys (date_of_loss, time_of_loss, cause_of_loss,
        // estimated_cost, item_description, subject_to_finance, insured) are
        // shared with other claim types — safe because only one type is filled
        // per submission and the backend filters to this table's columns.
        MACHINERYBREAKDOWN: [
          'insured', 'period_of_insurance', 'sum_insured',
          'contact_person', 'designation', 'phone', 'cellphone', 'email',
          'postal_physical_address', 'nature_of_business', 'years_in_operation',
          'item_description', 'make_model', 'serial_number', 'year_of_manufacture',
          'date_commissioned', 'technical_specs', 'current_replacement_value',
          'under_amc_contract', 'amc_contract_details',
          'date_of_loss', 'time_of_loss', 'date_loss_discovered', 'site_location',
          'equipment_status', 'cause_of_loss', 'damage_description',
          'estimated_cost', 'proposed_repairer', 'salvage_location',
          'sole_owner', 'sole_owner_details', 'co_owner_financier',
          'subject_to_finance', 'finance_details', 'financier_bank_reference',
          'third_party_responsible', 'third_party_details', 'third_party_name_contact',
          'recovery_claim_lodged', 'recovery_details',
          'other_insurance', 'other_insurance_details', 'other_insurer_policy',
          'loss_history',
          'procedural_improvements',
        ],
        // Machinery Breakdown — Loss of Profit (form AD MB LOP v1.0). Stored in
        // machinery_breakdown_lop_claims. Consequential-loss / BI computation
        // filed after the physical-damage MB claim. Money/count figures are
        // free-text best estimates. Five Yes/No booleans, each paired with a
        // details key. Some keys (insured, contact_person, email, loss_history,
        // declaration_*) are shared column names across sub-tables — safe as
        // only one type is filled per submission and the backend filters to
        // this table's columns.
        MACHINERYBREAKDOWNLOSSOFPROFIT: [
          'mb_claim_number', 'date_of_breakdown', 'mb_physical_claim_status',
          'insured', 'contact_person', 'designation', 'phone_cellphone', 'email',
          'postal_physical_address', 'site_premises_affected', 'nature_of_business', 'years_in_operation',
          'production_capacity', 'operating_hours_per_day', 'number_of_shifts', 'operating_days_per_week',
          'standard_turnover_prior_12m', 'standard_output_prior_12m',
          'is_seasonal', 'seasonal_details', 'peak_months_pattern',
          'comparable_period_turnover', 'comparable_period_output',
          'damaged_item_description', 'date_production_halted', 'time_excess_start_end',
          'date_production_partial_resumed', 'date_production_full_resumed',
          'total_full_shutdown_days', 'total_reduced_capacity_days', 'indemnity_period_max_end_date',
          'loss_continuing', 'loss_continuing_details', 'production_impact_description',
          'standard_turnover_indemnity', 'actual_turnover_indemnity', 'reduction_in_turnover',
          'gross_profit_rate', 'gross_profit_lost',
          'icw_outsourcing', 'icw_equipment_hire', 'icw_express_freight', 'icw_overtime_labour',
          'icw_temporary_premises', 'icw_other', 'icw_total',
          'savings_raw_materials', 'savings_power_utilities', 'savings_wages', 'savings_other', 'savings_total',
          'gross_loss_of_profit', 'less_time_excess', 'less_self_insured_retention', 'net_estimated_claim',
          'mitigation_steps', 'alt_production_available', 'alt_production_details',
          'replacement_equipment_sourced', 'replacement_supplier_terms',
          'other_bi_cover', 'other_bi_details', 'other_insurer_policy', 'loss_history',        ],
        // Directors & Officers Liability (form AD D&O v1.0). Stored in
        // directors_officers_liability_claims. Claims-made notification. The
        // "tick all that apply" groups are individual booleans (notif_*, side_*,
        // claimant_*, form_*, alleg_*); standalone Yes/No questions are booleans
        // with a paired *_details key. declaration_* / other_insurance /
        // other_insurance_details are shared column names — safe as only one type
        // is filled per submission and the backend filters to this table.
        DIRECTORSOFFICERSLIABILITY: [
          'notif_claim', 'notif_circumstance', 'notif_investigation', 'notif_subpoena',
          'period_of_insurance', 'retroactive_date', 'limit_aggregate', 'limit_each_claim', 'self_insured_retention',
          'side_a', 'side_b', 'side_c', 'epl_extension',
          'insured_company', 'company_registration_number', 'regulator_license_number',
          'industry_sector', 'registered_address', 'company_secretary_contact',
          'person1_name_id', 'person1_position', 'person1_appointment_date', 'person1_current_former',
          'person2_name_id', 'person2_position', 'person2_appointment_date', 'person2_current_former',
          'additional_insured_persons',
          'date_wrongful_act', 'date_claim_first_made', 'date_insured_first_aware',
          'claimant_shareholder', 'claimant_regulator', 'claimant_liquidator', 'claimant_employee',
          'claimant_customer', 'claimant_creditor', 'claimant_government', 'claimant_other',
          'claimant_names', 'claimant_legal_counsel',
          'form_letter_demand', 'form_summons', 'form_subpoena', 'form_regulator_inquiry',
          'form_criminal_charge', 'form_internal_investigation', 'form_other',
          'alleg_fiduciary_breach', 'alleg_misstatement', 'alleg_insolvent_trading', 'alleg_misappropriation',
          'alleg_regulatory_breach', 'alleg_employment_practices', 'alleg_negligence', 'alleg_criminal',
          'alleg_defamation', 'alleg_other',
          'allegation_description', 'total_quantum_claimed', 'stage_of_proceedings', 'court_forum', 'case_reference_number',
          'counsel_engaged', 'counsel_engaged_details', 'counsel_firm_attorney', 'counsel_contact_rate',
          'ad_prior_consent', 'ad_prior_consent_details', 'estimated_defense_costs', 'next_hearing_deadline',
          'settlement_offer_made', 'settlement_offer_details',
          'codefendants_insured_persons', 'codefendants_details', 'codefendant_names',
          'company_named', 'company_named_details',
          'outside_parties_named', 'outside_parties_details', 'outside_party_names',
          'other_insurance', 'other_insurance_details', 'other_policy_details',
          'previously_notified', 'previously_notified_details', 'prior_notification_reference',        ],
        // Marine Cargo Once-Off (form AD Marine Cargo v1.0). Stored in
        // marine_cargo_once_off_claims. Tick-all groups are individual booleans
        // (mode_*, loss_*); Yes/No questions are booleans with a paired *_details
        // key. Shared column names (insured, contact_person, date_of_loss,
        // other_insurance, declaration_*, etc.) are safe as only one type is
        // filled per submission and the backend filters to this table.
        MARINECARGOONCEOFF: [
          'certificate_number', 'insured', 'period_of_cover', 'insured_value', 'conditions_of_cover',
          'contact_person', 'designation', 'phone', 'cellphone', 'email', 'postal_physical_address', 'nature_of_business',
          'description_of_goods', 'number_type_packages', 'marks_numbers', 'gross_weight', 'net_weight',
          'commercial_invoice_number', 'invoice_value', 'cif_value', 'container_number', 'seal_numbers',
          'mode_sea', 'mode_air', 'mode_road', 'mode_rail', 'mode_multimodal',
          'multimodal_route_description', 'origin', 'destination', 'vessel_aircraft_truck_reg', 'voyage_flight_trip_no',
          'date_of_departure', 'date_of_arrival', 'bill_of_lading_number', 'carrier', 'freight_forwarder',
          'date_of_loss', 'date_loss_discovered', 'date_ad_notified', 'place_stage_of_loss',
          'loss_shortage', 'loss_pilferage', 'loss_non_delivery', 'loss_damage_handling', 'loss_wet_seawater',
          'loss_freshwater', 'loss_fire_explosion', 'loss_hijacking', 'loss_sea_perils', 'loss_other',
          'loss_description', 'estimated_value_of_loss',
          'notice_of_loss_issued', 'notice_of_loss_details', 'notice_date_reference',
          'carrier_acknowledged', 'carrier_acknowledged_details', 'carrier_reply_reference',
          'joint_survey_held', 'joint_survey_details', 'surveyor_agent',
          'survey_report_attached', 'survey_report_details',
          'police_report_attached', 'police_report_details', 'police_station_ob',
          'recovery_claim_lodged', 'recovery_claim_details', 'carrier_name_address',
          'goods_financed', 'goods_financed_details', 'bank_financier_reference',
          'other_insurance', 'other_insurance_details', 'other_insurer_policy',
          'loss_history', 'procedural_improvements',        ],
        // Marine Cargo Open Cover (form AD-CLM-MAR-001). Stored in
        // marine_cargo_open_cover_claims. Same shape as the once-off form except
        // the policy header captures open-cover / per-consignment declaration
        // fields. Tick-all groups are booleans (mode_*, loss_*); Yes/No + details
        // as elsewhere. Shared column names are safe (one type per submission).
        MARINECARGOOPENCOVER: [
          'open_cover_policy_number', 'insured', 'period_of_insurance', 'annual_aggregate_sum_insured',
          'certificate_declaration_number', 'date_of_declaration', 'insured_value_declared',
          'contact_person', 'designation', 'phone', 'cellphone', 'email', 'postal_physical_address', 'nature_of_business',
          'description_of_goods', 'number_type_packages', 'marks_numbers', 'gross_weight', 'net_weight',
          'commercial_invoice_number', 'invoice_value', 'cif_value', 'container_number', 'seal_numbers',
          'mode_sea', 'mode_air', 'mode_road', 'mode_rail', 'mode_multimodal',
          'multimodal_route_description', 'origin', 'destination', 'vessel_aircraft_truck_reg', 'voyage_flight_trip_no',
          'date_of_departure', 'date_of_arrival', 'bill_of_lading_number', 'carrier', 'freight_forwarder',
          'date_of_loss', 'date_loss_discovered', 'place_stage_of_loss',
          'loss_shortage', 'loss_pilferage', 'loss_non_delivery', 'loss_damage_handling', 'loss_wet_seawater',
          'loss_freshwater', 'loss_fire_explosion', 'loss_hijacking', 'loss_sea_perils', 'loss_other',
          'loss_description', 'estimated_value_of_loss',
          'notice_of_loss_issued', 'notice_of_loss_details', 'notice_date_reference',
          'carrier_acknowledged', 'carrier_acknowledged_details', 'carrier_reply_reference',
          'joint_survey_held', 'joint_survey_details', 'surveyor_agent',
          'survey_report_attached', 'survey_report_details',
          'police_report_attached', 'police_report_details', 'police_station_ob',
          'recovery_claim_lodged', 'recovery_claim_details', 'carrier_name_address',
          'goods_financed', 'goods_financed_details', 'bank_financier_reference',
          'other_insurance', 'other_insurance_details', 'other_insurer_policy',
          'loss_history', 'procedural_improvements',        ],
        // graphiteBWV8 professional_indemnity.blade.php — V8 column names
        // for the professional_indemnity_claims table. Five sections; the
        // two file uploads (contract_copy, investigation_findings) ship
        // via FormData files state on submit, not via sub_claim_data, so
        // they're omitted here. `insured_email` is shared with Public
        // Liability's "Insured — Email address"; safe because only one
        // claim type is filled per submission.
        PROFESSIONALINDEMNITY: [
          'type_of_business', 'contact_person', 'designation',
          'insured_email', 'insured_cell_tel',
          'claimant_type', 'claimant_name_surname',
          'claimant_email', 'claimant_cell_tel',
          'insured_retained_to_do', 'contract_in_place', 'contract_no_details',
          'work_performed_date', 'person_performed_work',
          'circumstances', 'first_aware_date', 'reason_for_reporting',
          'notification_purposes_only', 'verbal_written_demand', 'demand_received_date',
          'served_with_summons', 'summons_served_date',
          'attorney_appointed', 'attorney_details', 'amount_claimed',
          'own_investigation', 'views_on_liability', 'views_on_amount_claimed',
          'additional_details',
        ],
        // graphiteBWV8 travel_insurance.blade.php — V8 column names for the
        // travel_insurance_claim table. The 25 *_doc_* file columns are
        // shipped via FormData files state on submit (not via sub_claim_data),
        // so they're intentionally omitted from this list. `address` here
        // is the V8 free-text address for the OTHER insurer (only shown when
        // other_insurance_policy === "Yes").
        TRAVELINSURANCE: [
          'title', 'other_title', 'surname', 'forename', 'dob',
          'passport_no', 'nationality', 'telephone', 'post_code', 'mobile',
          'email', 'home_address',
          'policy_number', 'issued_by', 'issued_on', 'valid_from', 'valid_to',
          'beneficiary', 'bank_name', 'bank_address', 'account_number',
          'iban', 'swift_code', 'bic_code',
          'other_insurance_policy', 'name_insurance_company', 'address', 'phone_number',
          'type_of_refund', 'type_of_refund_other',
        ],
      }
      // Normalize the claim_type the same way the backend does so
      // "Business Interruption" and "BUSINESSINTERRUPTION" both map
      // cleanly.
      let normKey = (form.claim_type || '').toUpperCase().replace(/[^A-Z0-9]/g, '')
      // Singular coverage label → plural sub-form/ENUM key (see render block).
      normKey = CLAIM_TYPE_KEY_ALIASES[normKey] ?? normKey
      const subFields = subClaimFieldsByType[normKey]
      let subClaimData: Record<string, any> | null = null
      if (subFields && subFields.length) {
        subClaimData = {}
        for (const k of subFields) {
          const v = form[k]
          if (v !== '' && v !== null && v !== undefined) subClaimData[k] = v
        }
        if (Object.keys(subClaimData).length === 0) subClaimData = null
      }

      const fd = new FormData()
      Object.entries(submission).forEach(([key, val]) => {
        if (val === '' || val === null || val === undefined) return
        if (typeof val === 'boolean') { if (val) fd.append(key, '1'); return }
        // Don't double-send the individual sub-claim fields — they're
        // routed via sub_claim_data below. Keeping them out of the top-
        // level body also stops the backend validator from rejecting
        // unknown keys like `nature_of_interruption`.
        if (subFields && (subFields as string[]).includes(key)) return
        fd.append(key, String(val))
      })
      Object.entries(files).forEach(([key, f]) => { if (f) fd.append(key, f) })
      fd.append('form_template', formTemplate)
      if (subClaimData) {
        // Serialise the sub-claim object as JSON. The backend accepts
        // either a JSON string or a nested object; multipart/FormData
        // cannot carry nested objects so we stringify here.
        fd.append('sub_claim_data', JSON.stringify(subClaimData))
      }

      // ── Motor sub-table payloads (MOTORACCIDENT + traders) ──────────
      // Package the four V8 accident-blade sections (accident details,
      // driver, passengers repeater, third parties repeater) using PHP-
      // style array notation so the backend's array-rule validators
      // accept the nested shape. Skip for MIS (product 2/3) — those
      // products keep the lean V2 form without the V8 sections, so
      // there are no nested arrays to ship.
      // Normalise the claim_type the same way the render scope does so
      // DOM/COM motor claims (coverage_based form_template ships the V8
      // code 'MOTORACCIDENT' / 'MOTORTRADERSEXTERNAL' / 'MOTORTRADERS
      // INTERNAL' verbatim) and MIS motor claims (ship the friendly id
      // 'Accident' / 'Motor Traders External' / 'Motor Traders Internal')
      // both route through the motor sub-table payload packing below.
      const motorClaimKey = (form.claim_type || '').toUpperCase().replace(/[^A-Z0-9]/g, '')
      const isMotorAccidentLike = motorClaimKey === 'MOTOR'
        || motorClaimKey === 'ACCIDENT'
        || motorClaimKey === 'MOTORACCIDENT'
        || motorClaimKey === 'MOTORTRADERSEXTERNAL'
        || motorClaimKey === 'MOTORTRADERSINTERNAL'
      const isMisMotor = productId === 2 || productId === 3
      if (isMotorAccidentLike) {
        // accident_details — flat 1:1 onto claim_accidents
        const acc: Record<string, string> = {}
        if (form.date_of_accident) acc.date_of_accident = String(form.date_of_accident)
        if (form.time_of_accident) acc.time_of_accident = String(form.time_of_accident)
        if (form.place_of_accident) acc.place_of_accident = String(form.place_of_accident)
        if (form.damage_details) acc.detail_of_accident = String(form.damage_details)
        if (form.fault_party) acc.fault_party = String(form.fault_party)
        // V8 captures third_party as a top-level toggle; V2 derives it
        // from whether any third-party rows exist (a single source of
        // truth that survives in-form edits).
        acc.third_party = (!isMisMotor && motorThirdParties.length > 0) ? '1' : '0'
        for (const [k, v] of Object.entries(acc)) {
          fd.append(`accident_details[${k}]`, v)
        }

        if (!isMisMotor) {
          // accident_driver — flat 1:1 onto accident_driver
          const drv: Record<string, string> = {}
          if (form.driver_name) drv.name = String(form.driver_name)
          if (form.driver_num) drv.cellphone = String(form.driver_num)
          if (form.driver_dob) drv.dob = String(form.driver_dob)
          if (form.driver_address) drv.address = String(form.driver_address)
          if (form.driver_license) drv.license = String(form.driver_license)
          if (form.driver_purpose) drv.purpose = String(form.driver_purpose)
          for (const [k, v] of Object.entries(drv)) {
            fd.append(`accident_driver[${k}]`, v)
          }

          // accident_passengers — N rows
          motorPassengers.forEach((p, i) => {
            if (!p.name && !p.address && !p.injury) return
            if (p.name) fd.append(`accident_passengers[${i}][name]`, p.name)
            if (p.address) fd.append(`accident_passengers[${i}][address]`, p.address)
            if (p.injury) fd.append(`accident_passengers[${i}][injury]`, p.injury)
          })

          // accident_third_parties — N rows with nested insured fields
          motorThirdParties.forEach((tp, i) => {
            const tpRow: Record<string, string> = {}
            if (tp.first_name) tpRow.first_name = tp.first_name
            if (tp.last_name) tpRow.last_name = tp.last_name
            if (tp.cellphone) tpRow.cellphone = tp.cellphone
            if (tp.address) tpRow.address = tp.address
            if (tp.make) tpRow.make = tp.make
            if (tp.model) tpRow.model = tp.model
            if (tp.registration_no) tpRow.registration_no = tp.registration_no
            if (tp.damage_details) tpRow.damage_details = tp.damage_details
            if (tp.injured_name) tpRow.injured_name = tp.injured_name
            if (tp.relationship) tpRow.relationship = tp.relationship
            if (tp.hospital_name) tpRow.hospital_name = tp.hospital_name
            if (tp.injured_details) tpRow.injured_details = tp.injured_details
            if (tp.third_party_insured) {
              if (tp.first_name_insured) tpRow.first_name_insured = tp.first_name_insured
              if (tp.last_name_insured) tpRow.last_name_insured = tp.last_name_insured
              if (tp.cellphone_insured) tpRow.cellphone_insured = tp.cellphone_insured
              if (tp.email_insured) tpRow.email_insured = tp.email_insured
              if (tp.address_insured) tpRow.address_insured = tp.address_insured
            }
            if (Object.keys(tpRow).length === 0) return
            for (const [k, v] of Object.entries(tpRow)) {
              fd.append(`accident_third_parties[${i}][${k}]`, v)
            }
          })
        }
      }

      // ── Step 1: create the claim (existing flow) ──
      const res: any = await createClaim.mutateAsync(fd as any)
      const newClaimId: number | null = res?.data?.id ?? null

      // ── Step 2: persist the stage timeline + comment status (additive) ──
      // Only when the accordions are shown and the operator entered something.
      const stage = showStageAccordions ? stageRef.current?.collect() : undefined
      if (newClaimId && stage?.hasData) {
        try {
          if (Object.keys(stage.workflow).length > 0) {
            await updateClaimSlaTimeline(newClaimId, stage.workflow)
          }
          if (stage.commentStatus !== null) {
            await setClaimCommentStatus(newClaimId, {
              comment_status: stage.commentStatus,
              comment_sub_reason: stage.commentSubReason,
            })
          }
          toast.success('Claim registered with stage details.')
          navigate('/claims')
        } catch {
          // Partial failure: the claim WAS created — never silently drop the
          // stage input. Open the claim so the handler can re-enter it there.
          toast.error('Claim registered, but the stage/comment details could not be saved. Opening the claim so you can add them.')
          navigate(`/claims/${newClaimId}`)
        }
        return
      }

      navigate('/claims')
    } catch (err: any) {
      const d = err?.response?.data || {}
      const detail = d.error || (d.errors ? Object.values(d.errors).flat().join(' · ') : '') || ''
      setErrors({ _global: [d.message || 'Failed to register claim', detail].filter(Boolean).join(' — ') })
    }
  }

  // Sub-types are fetched on demand whenever the operator picks a
  // claim_type. Legacy NewClaimController has two data sources depending
  // on the claim type — Motor Accident → claim_subtype table, everything
  // else → Lookup::where('key', $claimType). /claims/sub-types-by-claim-type
  // encapsulates both and tries several key variants so we hit regardless
  // of how the seeder stored the key.
  // NOTE: must stay ABOVE the `if (isLoading) return` early-exit below.
  // React's hooks rule requires the same hook order on every render —
  // gating this useState/useEffect behind the early return triggered
  // "Rendered more hooks than during the previous render" once the
  // create-data resolved and isLoading flipped from true → false.
  const [subTypesForClaim, setSubTypesForClaim] = useState<{ id: any; name: string }[]>([])
  useEffect(() => {
    if (!form.claim_type) { setSubTypesForClaim([]); return }
    apiClient.get('/claims/sub-types-by-claim-type', { params: { claim_type: form.claim_type } })
      .then(r => setSubTypesForClaim(r.data?.data ?? []))
      .catch(() => setSubTypesForClaim([]))
  }, [form.claim_type])
  const filteredSubTypes = subTypesForClaim

  if (isLoading) return <div className="flex items-center justify-center h-64"><LoadingSpinner size="lg" /></div>

  if (!canCreate) {
    return (
      <div className="p-8 text-center">
        <p className="text-status-danger-fg font-medium">Access denied. Registering claims requires the claim-create permission.</p>
        <button onClick={() => navigate('/claims')} className="mt-4 text-sm text-primary underline">Back to Claims</button>
      </div>
    )
  }

  const isCoverageBased = formTemplate === 'coverage_based'
  const RELATIONSHIPS = ['Self', 'Spouse', 'Child', 'Parent', 'Sibling', 'Other']

  return (
    <FormCtx.Provider value={{ form, update, files, setFile }}>
    <div className="max-w-4xl mx-auto">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">Register New Claim</h1>
        <p className="text-sm text-gray-500 mt-1">Form fields adjust to the policy's product type.</p>
      </div>

      {errors._global && (
        <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-md text-red-700 text-sm">{errors._global}</div>
      )}

      <form onSubmit={handleSubmit} className="bg-white rounded-lg shadow-sm border p-6 space-y-4">
        {/* ── Step 1: Policy ── */}
        <h3 className="text-lg font-semibold text-gray-800 border-b pb-2">Step 1: Select Policy</h3>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Policy ID or Policy Number *</label>
            <input type="text" value={form.policy_id} onChange={e => handlePolicyChange(e.target.value)}
              placeholder="Enter policy ID or number"
              className={`w-full px-3 py-2 border rounded-md text-sm ${errors.policy_id ? 'border-red-300' : 'border-gray-300'}`} />
            {errors.policy_id && <p className="mt-1 text-xs text-red-500">{errors.policy_id}</p>}
          </div>
          <div className="flex items-end">
            {loadingTypes && <span className="text-sm text-blue-600 animate-pulse">Loading…</span>}
            {policyInfo && !loadingTypes && (
              <span className={`text-sm font-medium ${productType ? 'text-green-700' : 'text-red-500'}`}>
                {policyInfo}
                {productType && <span className="ml-2 px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full text-xs">{productType}</span>}
                {formTemplate && <span className="ml-2 px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded-full text-xs">{formTemplate}</span>}
              </span>
            )}
          </div>
        </div>

        {isCoverageBased && policyActions.length > 0 && (
          <>
            <H>Step 2: Select Policy Term</H>
            <Sel k="_action" l="Policy Action / Term *" opts={policyActions.map(a => ({ id: a.id, name: `${a.transactionType} — ${a.status} (${a.effectiveFrom?.slice(0,10)} – ${a.effectiveTo?.slice(0,10)})` }))} />
          </>
        )}

        {/* Render the Claim Type dropdown as soon as we have any types —
            action-level OR policy-level fallback. Previously gated on
            dynamicClaimTypes.length>0, which disappeared whenever the
            user switched to a policy term that had no coverages and
            couldn't be recovered without blanking the policy number. */}
        {(dynamicClaimTypes.length > 0 || policyLevelClaimTypes.length > 0) && (
          <>
            <H>{isCoverageBased ? 'Step 3: Claim Type' : 'Step 2: Claim Type'}</H>
            <Sel k="claim_type" l="Claim Type *"
                 opts={dynamicClaimTypes.length > 0 ? dynamicClaimTypes : policyLevelClaimTypes} />
            {!actionHadTypes && (
              <p className="text-xs text-amber-600 mt-1">
                ⚠ The selected policy term has no bound coverages — showing the policy-wide
                claim type list. Verify the coverage after submitting if the rating looks off.
              </p>
            )}
            {errors.claim_type && <p className="text-xs text-red-500">{errors.claim_type}</p>}
          </>
        )}

        {form.claim_type && <>

          {/* ═══════════ HOSPITAL CASH ═══════════ */}
          {formTemplate === 'hospital_cash' && <>
            <H>Patient</H>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <Field k="patient_name" l="Name of Patient *" />
              <Field k="patient_dob" l="Date of Birth" type="date" />
              <Sel k="patient_is_citizen" l="Are you a citizen of Botswana? *"
                opts={[{id:'yes',name:'Yes'},{id:'no',name:'No'}]} />
              {form.patient_is_citizen === 'yes' && (
                <Field k="patient_omang" l="Omang Number *" />
              )}
              {form.patient_is_citizen === 'no' && (
                <Field k="patient_passport" l="Passport Number *" />
              )}
              <Sel k="relationship" l="Relationship to Policyholder *" opts={RELATIONSHIPS} />
              {form.relationship === 'Other' && <Field k="relationship_other" l="Specify Relationship" />}
              <Field k="occupation_date" l="Occupation Date" type="date" />
            </div>

            <H>General Practitioner (family doctor)</H>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <Field k="gp_name" l="Name" />
              <Field k="gp_postal_address" l="Postal Address" />
              <Field k="gp_cellular_no" l="Cellular No" />
              <Field k="gp_telephone_no" l="Telephone No" />
              <Field k="gp_fax_no" l="Fax No" />
            </div>

            <H>Hospital</H>
            <p className="text-xs text-gray-500">Please attach copies of the hospital account and day to day hospital records</p>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <Field k="hospital_name" l="Name of Hospital *" />
              <Field k="hospital_tel_fax" l="Tel and Fax No" />
              <Field k="admitting_doctor" l="Admitting Doctor" />
              <Field k="admitting_doctor_tel_fax" l="Admitting Doctor Tel and Fax" />
              <Field k="admission_date" l="Admission Date *" type="date" />
              <Field k="admission_time" l="Admission Time" type="time" />
              <Field k="discharge_date" l="Discharge Date" type="date" />
              <Field k="discharge_time" l="Discharge Time" type="time" />
            </div>

            <H>Claim Information</H>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <Sel k="hospitalisation_type" l="Was hospitalisation due to: *"
                opts={[{id:'accident',name:'Accident'},{id:'sickness',name:'Sickness'},{id:'pregnancy',name:'Pregnancy'},{id:'injury',name:'Injury'}]} />
              {form.hospitalisation_type === 'accident' && <>
                <Field k="accident_reported" l="Case number (if reported)" />
                <Field k="injury_date" l="When did accident occur" type="date" />
              </>}
              {form.hospitalisation_type === 'sickness' && (
                <Field k="symptoms_first_appeared" l="When did symptoms first appear" type="date" />
              )}
              {form.hospitalisation_type === 'pregnancy' && <>
                <Field k="pregnancy_conception_date" l="Approximate date of conception" type="date" />
                <Field k="pregnancy_delivery_date" l="Date of delivery" type="date" />
              </>}
              <Field k="first_consultation_date" l="When did you first consult a doctor" type="date" />
            </div>
            <Area k="accident_circumstances" l="Describe the circumstances surrounding the accident" />

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <Sel k="is_medical_scheme" l="Is the patient on a medical scheme?" opts={[{id:'yes',name:'Yes'},{id:'no',name:'No'}]} />
              <Sel k="has_other_insurance" l="Other Hospital Insurance?" opts={[{id:'yes',name:'Yes'},{id:'no',name:'No'}]} />
            </div>
            {form.is_medical_scheme === 'yes' && (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <Field k="medical_scheme_name" l="Medical scheme name" />
                <Field k="medical_aid_number" l="Medical aid number" />
              </div>
            )}
            {form.has_other_insurance === 'yes' && (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <Field k="other_insurance_company_name" l="Insurance company name" />
                <Field k="other_insurance_policy_numbers" l="Policy numbers" />
              </div>
            )}
          </>}

          {/* ═══════════ LIFE / ADI ═══════════
              Matches the legacy graphiteBWV8 form — deliberately excludes
              the generic Incident Details + Supporting Documents blocks
              below (those fields are not relevant to a death claim). Only
              the death certificate + cause of death are captured here. */}
          {formTemplate === 'life' && <>
            <H>Life Claim Details</H>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <Field k="date_of_death" l="Date of Death *" type="date" />
              <Sel k="cause_of_death" l="Cause of Death *"
                opts={createData?.cause_of_death_options} />
              <Field k="registered_claim" l="Date Claim Registered" type="date" />
              <FileInput k="death_certificate" l="Upload Death Certificate" />
            </div>
            <Area k="life_description" l="Description *" rows={4} />
          </>}

          {/* ═══════════ LEGAL ═══════════ */}
          {formTemplate === 'legal' && <>
            <H>Lawyer's Details</H>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <Field k="legal_firm" l="Legal Firm *" />
              <Field k="lawyer_name" l="Lawyer's Name *" />
              <Field k="legal_tel" l="Telephone No *" />
              <Field k="legal_email" l="Email *" type="email" />
              <Sel k="legaloption" l="Use your own law firm?" opts={[{id:'0',name:'No (use Alpha Direct panel)'},{id:'1',name:'Yes (own lawyer)'}]} />
              {form.legaloption === '1' && <>
                <Sel k="representing_member" l="Prepared to represent our Member?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                <Sel k="lawyer_tarrif" l="Assist with Member compliance?" opts={[{id:'yes',name:'Yes'},{id:'no',name:'No'}]} />
              </>}
            </div>

            <H>Member Details</H>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <Field k="member_name" l="Name *" />
              <Field k="membership_id" l="Membership ID *" type="number" />
              <Field k="member_contact" l="Contact Number *" />
              <Field k="member_email" l="Email *" type="email" />
              <Field k="lossreported_date" l="Date Reported to Alpha Direct" type="date" />
            </div>
            <div className="flex flex-col gap-2">
              <Chk k="tariffs_1" l="Accept liability if lawyer non-compliant with tariffs" />
              <Chk k="tariffs_2" l="Understand liability without written confirmation" />
            </div>

            <H>Details of the Matter</H>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <Sel k="matter_relatesto" l="Who does the matter relate to?"
                opts={[{id:'1',name:'Main Member'},{id:'2',name:'Spouse'},{id:'3',name:'Child'},{id:'4',name:'Extended Family'}]} />
              {form.matter_relatesto === '3' && <>
                <Sel k="child_financial_dependent" l="Child financially dependent?" opts={[{id:'Yes',name:'Yes'},{id:'No',name:'No'}]} />
                <Field k="idforchild" l="Child ID No" type="number" />
                <Field k="child_dob" l="Child Date of Birth" type="date" />
              </>}
              <Sel k="realestate_enquiry_from" l="Type of Matter"
                opts={[{id:'1',name:'Civil'},{id:'2',name:'Criminal'},{id:'3',name:'Labour'}]} />
              <Field k="arose_date" l="Date matter arose *" type="date" />
              <Field k="matter_quantum" l="Quantum of the Matter *" />
              <Field k="jurisdiction" l="Jurisdiction *" placeholder="e.g. High Court of Botswana" />
            </div>
            <Area k="course_of_action" l="Proposed Course of Action * (min 200 chars)" rows={4} />
            {form.realestate_enquiry_from === '2' && <>
              <Area k="criminalmatter_detail" l="Previous Convictions Detail * (min 200 chars)" rows={3} />
              <Area k="criminalmatter_charge" l="Criminal Matter Charge * (min 200 chars)" rows={3} />
            </>}
            <div className="flex flex-col gap-2 bg-gray-50 p-3 rounded">
              <Chk k="declaration" l="I declare no cash payout has been / will be requested" />
              <Chk k="nofalseinfo" l="I understand the penalties for providing false information" />
              <Chk k="signature" l="I accept this as my electronic signature" />
            </div>
          </>}

          {/* ═══════════ VEHICLE — routes to Accident / Glass / Key Loss ═══════════
              Mirrors graphiteBWV8 admin/policy/{accident,glass,key_loss}.blade.
              claim_type id depends on the dispatcher:
                • MIS product 2/3 (form_template='vehicle') ships the V2
                  friendly ids 'Accident' / 'Glass' / 'Key Loss'.
                • DOM/COM and Specialist (form_template='coverage_based')
                  ship the coverage's claim_name verbatim — typically the
                  V8 code ('MOTORACCIDENT' / 'GLASS' / 'LOCKSANDKEYS' /
                  'MOTORTRADERSEXTERNAL' / 'MOTORTRADERSINTERNAL'). The
                  computed `motorClaimKey` normalises both shapes so the
                  same sub-form renders regardless of where the claim
                  type came from. */}
          {(() => {
            const motorClaimKey = (form.claim_type || '').toUpperCase().replace(/[^A-Z0-9]/g, '')
            const isAccidentClaim = motorClaimKey === 'MOTOR'
              || motorClaimKey === 'ACCIDENT'
              || motorClaimKey === 'MOTORACCIDENT'
              || motorClaimKey === 'MOTORTRADERSEXTERNAL'
              || motorClaimKey === 'MOTORTRADERSINTERNAL'
            const isGlassClaim   = motorClaimKey === 'GLASS'
            const isKeyLossClaim = motorClaimKey === 'KEYLOSS' || motorClaimKey === 'LOCKSANDKEYS'
            const isAnyMotor     = isAccidentClaim || isGlassClaim || isKeyLossClaim
            const showVehicleBlock = formTemplate === 'vehicle' || isAnyMotor
            if (!showVehicleBlock) return null
            return <>
            {/* MIS keeps the original 4-field vehicle block (plate / make /
                model / year). DOM/COM motor claims (coverage_based motor
                codes) instead render V8's main.blade top fields: "Is this
                motor claim" Yes/No + "Select Motor / Vehicle Registration",
                matching the V8 screenshot. */}
            {isKeyLossClaim ? null : formTemplate === 'vehicle' ? (
              <>
                <H>Vehicle</H>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <Field k="vehicle_plate" l="Registration Plate *" />
                  <Field k="vehicle_make" l="Make" />
                  <Field k="vehicle_model" l="Model" />
                  <Field k="vehicle_year" l="Year" />
                </div>
              </>
            ) : (
              <>
                <H>Motor</H>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <Sel k="is_motor_claim" l="Is this motor claim"
                       opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  {/* V8 main.blade.php hides Select Motor until the
                      operator answers Yes on "Is this motor claim".
                      Dropdown lists the vehicles covered under the selected
                      policy action/term; falls back to a free-text plate entry
                      when the policy has no captured vehicles for that term. */}
                  {String(form.is_motor_claim) === '1' && (
                    policyVehicles.length > 0
                      ? <Sel k="vehicle_plate" l="Select Motor (Registration No)" opts={policyVehicles} />
                      : <Field k="vehicle_plate" l="Select Motor (Registration No)" />
                  )}
                </div>
              </>
            )}

            {/* ── Accident sub-form ── */}
            {/* graphiteBWV8 accident.blade.php — V8 dispatches MOTORACCIDENT,
                MOTORTRADERSEXTERNAL and MOTORTRADERSINTERNAL to the same
                blade. V2 mirrors that by rendering the same sub-form for
                all three claim_type ids. New V8 sections (Driver Details,
                Passengers Injured repeater, Third Party Details repeater
                with nested "Third Party Insured" fields, Recovery Details)
                are gated by `productId !== 2 && productId !== 3` so MIS
                Motor Accident (product 3 = MotorComp) keeps the lean V2
                form. Backend dispatch writes the nested payload into
                claim_accidents / accident_driver / claim_accident_passengers
                / claim_accident_third_party on submit. */}
            {isAccidentClaim && <>
              <H>Accident Details</H>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <Field k="date_of_accident" l="Date of Accident *" type="date" />
                <Field k="time_of_accident" l="Time of Accident" type="time" />
                <Field k="place_of_accident" l="Place of Accident *" />
                <Sel k="weather_condition" l="Weather Condition" opts={createData?.weather_conditions} />
                <Sel k="fault_party" l="Party at Fault" opts={createData?.fault_parties} />
              </div>
              <Area k="damage_details" l="Damage Details *" />
              <div className="flex items-center gap-6 pt-1">
                <Chk k="catastrophe_loss" l="Catastrophe Loss" />
                <Chk k="attorney_involved" l="Attorney Involved" />
                <Chk k="pa_involved" l="Police Accident Reported" />
              </div>
              <H>Damage Photos</H>
              <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                <FileInput k="incidentFront" l="Front Side" />
                <FileInput k="incidentBack" l="Back Side" />
                <FileInput k="incidentRight" l="Right Side" />
                <FileInput k="incidentLeft" l="Left Side" />
              </div>

              {/* V8 sections — Driver / Passengers / Third Party /
                  Recovery — surfaced for DOM/COM and Specialist motor
                  claims. MIS Motor Accident (product 2/3) keeps the lean
                  V2 form unchanged. */}
              {productId !== 2 && productId !== 3 && <>
                <H>Driver Details</H>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <Field k="driver_name" l="Name" />
                  <Field k="driver_dob" l="Date of Birth" type="date" />
                  <Field k="driver_num" l="Mobile Number" />
                  <Field k="driver_address" l="Address" />
                  <Field k="driver_purpose" l="Purpose" />
                  <Field k="driver_license" l="License" />
                </div>

                <H>Passengers Injured</H>
                <div className="space-y-3">
                  {motorPassengers.length === 0 && (
                    <p className="text-xs text-gray-500">No passenger rows. Click below to add one.</p>
                  )}
                  {motorPassengers.map((p, i) => (
                    <div key={i} className="grid grid-cols-1 md:grid-cols-3 gap-3 p-3 border border-gray-200 rounded-md">
                      <div>
                        <label className="block text-xs font-medium text-gray-500 mb-1">Name</label>
                        <input value={p.name}
                          onChange={e => setMotorPassengers(motorPassengers.map((x, xi) => xi === i ? { ...x, name: e.target.value } : x))}
                          className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                      </div>
                      <div>
                        <label className="block text-xs font-medium text-gray-500 mb-1">Address</label>
                        <input value={p.address}
                          onChange={e => setMotorPassengers(motorPassengers.map((x, xi) => xi === i ? { ...x, address: e.target.value } : x))}
                          className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                      </div>
                      <div className="flex items-end gap-2">
                        <div className="flex-1">
                          <label className="block text-xs font-medium text-gray-500 mb-1">Injury</label>
                          <input value={p.injury}
                            onChange={e => setMotorPassengers(motorPassengers.map((x, xi) => xi === i ? { ...x, injury: e.target.value } : x))}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                        </div>
                        <button type="button"
                          onClick={() => setMotorPassengers(motorPassengers.filter((_, xi) => xi !== i))}
                          className="px-2 py-1 text-xs text-red-600 hover:text-red-800" title="Remove">✕</button>
                      </div>
                    </div>
                  ))}
                  <button type="button"
                    onClick={() => setMotorPassengers([...motorPassengers, blankPassenger()])}
                    className="text-xs text-blue-600 hover:underline">+ Add passenger</button>
                </div>

                <H>Third Party Details</H>
                <div className="space-y-3">
                  {motorThirdParties.length === 0 && (
                    <p className="text-xs text-gray-500">No third-party rows. Click below to add one.</p>
                  )}
                  {motorThirdParties.map((tp, i) => (
                    <div key={i} className="p-3 border border-gray-200 rounded-md space-y-3">
                      <div className="flex items-center gap-3">
                        <label className="flex items-center gap-2 text-sm">
                          <input type="checkbox" checked={tp.third_party_insured}
                            onChange={e => setMotorThirdParties(motorThirdParties.map((x, xi) => xi === i ? { ...x, third_party_insured: e.target.checked } : x))}
                            className="rounded" />
                          Is third party insured?
                        </label>
                        <button type="button"
                          onClick={() => setMotorThirdParties(motorThirdParties.filter((_, xi) => xi !== i))}
                          className="ml-auto px-2 py-1 text-xs text-red-600 hover:text-red-800" title="Remove">✕</button>
                      </div>

                      {tp.third_party_insured && (
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-3 pl-2 border-l-2 border-blue-100">
                          <div className="md:col-span-2 text-xs font-medium text-gray-700">Third Party Insured Details</div>
                          <div>
                            <label className="block text-xs font-medium text-gray-500 mb-1">First Name</label>
                            <input value={tp.first_name_insured}
                              onChange={e => setMotorThirdParties(motorThirdParties.map((x, xi) => xi === i ? { ...x, first_name_insured: e.target.value } : x))}
                              className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                          </div>
                          <div>
                            <label className="block text-xs font-medium text-gray-500 mb-1">Last Name</label>
                            <input value={tp.last_name_insured}
                              onChange={e => setMotorThirdParties(motorThirdParties.map((x, xi) => xi === i ? { ...x, last_name_insured: e.target.value } : x))}
                              className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                          </div>
                          <div>
                            <label className="block text-xs font-medium text-gray-500 mb-1">Mobile Number</label>
                            <input value={tp.cellphone_insured}
                              onChange={e => setMotorThirdParties(motorThirdParties.map((x, xi) => xi === i ? { ...x, cellphone_insured: e.target.value } : x))}
                              className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                          </div>
                          <div>
                            <label className="block text-xs font-medium text-gray-500 mb-1">Email</label>
                            <input type="email" value={tp.email_insured}
                              onChange={e => setMotorThirdParties(motorThirdParties.map((x, xi) => xi === i ? { ...x, email_insured: e.target.value } : x))}
                              className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                          </div>
                          <div className="md:col-span-2">
                            <label className="block text-xs font-medium text-gray-500 mb-1">Address</label>
                            <textarea value={tp.address_insured}
                              onChange={e => setMotorThirdParties(motorThirdParties.map((x, xi) => xi === i ? { ...x, address_insured: e.target.value } : x))}
                              rows={2} className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                          </div>
                        </div>
                      )}

                      <div className="text-xs font-medium text-gray-700">Other Party Details</div>
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                          <label className="block text-xs font-medium text-gray-500 mb-1">First Name</label>
                          <input value={tp.first_name}
                            onChange={e => setMotorThirdParties(motorThirdParties.map((x, xi) => xi === i ? { ...x, first_name: e.target.value } : x))}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                        </div>
                        <div>
                          <label className="block text-xs font-medium text-gray-500 mb-1">Last Name</label>
                          <input value={tp.last_name}
                            onChange={e => setMotorThirdParties(motorThirdParties.map((x, xi) => xi === i ? { ...x, last_name: e.target.value } : x))}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                        </div>
                        <div>
                          <label className="block text-xs font-medium text-gray-500 mb-1">Mobile Number</label>
                          <input value={tp.cellphone}
                            onChange={e => setMotorThirdParties(motorThirdParties.map((x, xi) => xi === i ? { ...x, cellphone: e.target.value } : x))}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                        </div>
                        <div>
                          <label className="block text-xs font-medium text-gray-500 mb-1">Address</label>
                          <input value={tp.address}
                            onChange={e => setMotorThirdParties(motorThirdParties.map((x, xi) => xi === i ? { ...x, address: e.target.value } : x))}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                        </div>
                      </div>

                      <div className="text-xs font-medium text-gray-700">Vehicle Details</div>
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                          <label className="block text-xs font-medium text-gray-500 mb-1">Registration Number</label>
                          <input value={tp.registration_no}
                            onChange={e => setMotorThirdParties(motorThirdParties.map((x, xi) => xi === i ? { ...x, registration_no: e.target.value } : x))}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                        </div>
                        <div>
                          <label className="block text-xs font-medium text-gray-500 mb-1">Damage Details</label>
                          <textarea value={tp.damage_details}
                            onChange={e => setMotorThirdParties(motorThirdParties.map((x, xi) => xi === i ? { ...x, damage_details: e.target.value } : x))}
                            rows={2} className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                        </div>
                        <div>
                          <label className="block text-xs font-medium text-gray-500 mb-1">Make</label>
                          <input value={tp.make}
                            onChange={e => setMotorThirdParties(motorThirdParties.map((x, xi) => xi === i ? { ...x, make: e.target.value } : x))}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                        </div>
                        <div>
                          <label className="block text-xs font-medium text-gray-500 mb-1">Model</label>
                          <input value={tp.model}
                            onChange={e => setMotorThirdParties(motorThirdParties.map((x, xi) => xi === i ? { ...x, model: e.target.value } : x))}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                        </div>
                      </div>

                      <div className="text-xs font-medium text-gray-700">Personal Injury</div>
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                          <label className="block text-xs font-medium text-gray-500 mb-1">Name of Injured</label>
                          <input value={tp.injured_name}
                            onChange={e => setMotorThirdParties(motorThirdParties.map((x, xi) => xi === i ? { ...x, injured_name: e.target.value } : x))}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                        </div>
                        <div>
                          <label className="block text-xs font-medium text-gray-500 mb-1">Relationship to Injured</label>
                          <input value={tp.relationship}
                            onChange={e => setMotorThirdParties(motorThirdParties.map((x, xi) => xi === i ? { ...x, relationship: e.target.value } : x))}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                        </div>
                        <div>
                          <label className="block text-xs font-medium text-gray-500 mb-1">Name of Hospital (if applicable)</label>
                          <input value={tp.hospital_name}
                            onChange={e => setMotorThirdParties(motorThirdParties.map((x, xi) => xi === i ? { ...x, hospital_name: e.target.value } : x))}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                        </div>
                        <div>
                          <label className="block text-xs font-medium text-gray-500 mb-1">Details of Injured</label>
                          <textarea value={tp.injured_details}
                            onChange={e => setMotorThirdParties(motorThirdParties.map((x, xi) => xi === i ? { ...x, injured_details: e.target.value } : x))}
                            rows={2} className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm" />
                        </div>
                      </div>
                    </div>
                  ))}
                  <button type="button"
                    onClick={() => setMotorThirdParties([...motorThirdParties, blankThirdParty()])}
                    className="text-xs text-blue-600 hover:underline">+ Add third party</button>
                </div>

                <H>Other Information</H>
                <label className="flex items-center gap-2 text-sm">
                  <input type="checkbox" checked={recoveryInvolved}
                    onChange={e => setRecoveryInvolved(e.target.checked)}
                    className="rounded" />
                  Recovery involved
                </label>
                {recoveryInvolved && (
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
                    <Field k="recovery_name" l="Name of third party" />
                    <Field k="recovery_address" l="Address of third party" />
                    <Field k="recovery_phone" l="Phone number of third party" />
                    <Field k="recovery_email" l="Email address of third party" type="email" />
                  </div>
                )}
              </>}
            </>}

            {/* ── Glass sub-form ── */}
            {/* graphiteBWV8 glass.blade.php — V8 captures incident_date,
                extent (Cracked/Shattered), cause, plus four "After"
                damage photos (incidentFront/Back/Right/Left) with text
                descriptions. V2 retains its own damage_location dropdown
                and two-quote comparison block. Backend dispatch persists
                everything to glass_claim via the subClaimFieldsByType
                routing. */}
            {isGlassClaim && <>
              <H>Glass / Windscreen Damage</H>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <Field k="incident_date" l="Date of Damage *" type="date" />
                <Sel k="extent" l="Damage Extent"
                  opts={[{id:'Cracked',name:'Cracked'},{id:'Shattered',name:'Shattered'}]} />
                {/* Damage Location is a V2-only MIS extension — V8 Glass
                    blade doesn't capture it. Show for MIS only. */}
                {formTemplate === 'vehicle' && (
                  <Sel k="damage_location" l="Damage Location"
                    opts={[{id:'windscreen',name:'Windscreen'},{id:'rear',name:'Rear Glass'},{id:'side',name:'Side Window'}]} />
                )}
              </div>
              <Area k="cause" l="Cause of Damage *" />
              {/* Replacement Quotes is a V2-only MIS extension — V8 Glass
                  blade doesn't capture quotes. Show for MIS only. */}
              {formTemplate === 'vehicle' && (
                <>
                  <H>Replacement Quotes</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="company_1" l="Quote 1 — Company" />
                    <Field k="amount_quote_1" l="Quote 1 — Amount" type="number" />
                    <Field k="company_2" l="Quote 2 — Company" />
                    <Field k="amount_quote_2" l="Quote 2 — Amount" type="number" />
                  </div>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FileInput k="quote_1" l="Quote 1 Document" />
                    <FileInput k="quote_2" l="Quote 2 Document" />
                  </div>
                </>
              )}
              {/* V8 "Damage Photos & Descriptions" applies to DOM/COM
                  and Specialist Glass claims only. MIS Glass (product
                  2 = TP, product 3 = MotorComp) has a different legacy
                  workflow that doesn't capture these 4 sided photos
                  with descriptions — render the section only when the
                  product is not one of the MIS pair. */}
              {productId !== 2 && productId !== 3 && <>
                <H>Damage Photos &amp; Descriptions</H>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <FileInput k="incidentFront" l="Front Side" />
                    <Area k="front_image_description" l="Front — Description" rows={2} />
                  </div>
                  <div className="space-y-2">
                    <FileInput k="incidentBack" l="Back Side" />
                    <Area k="back_image_description" l="Back — Description" rows={2} />
                  </div>
                  <div className="space-y-2">
                    <FileInput k="incidentRight" l="Right Side" />
                    <Area k="right_image_description" l="Right — Description" rows={2} />
                  </div>
                  <div className="space-y-2">
                    <FileInput k="incidentLeft" l="Left Side" />
                    <Area k="left_image_description" l="Left — Description" rows={2} />
                  </div>
                </div>
              </>}
            </>}

            {/* ── Key Loss sub-form ── */}
            {/* graphiteBWV8 key_loss.blade.php — V8 active fields are
                `purpose` (vehicle_purpose Lookup) and `registered_claim`
                (date), plus the shared `lossDate` / `descriptionofLoss`.
                V2 retains its own chassis_num / financial_interest /
                key_reason / estimate / police_affidavit extensions which
                the existing form already captures. V8 active extras
                (purpose / registered_claim) render only for non-MIS
                products — MIS Key Loss (product 3 = MotorComp) keeps the
                lean V2 form. Backend dispatch persists everything to
                key_loss_claim via subClaimFieldsByType routing. */}
            {isKeyLossClaim && <>
              {/* graphiteBWV8 admin/policy/key_loss.blade.php — full legacy
                  form, replicated field-for-field for products 2 & 3. */}
              <H>Loss Of Key Claim Information</H>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <Field k="financial_interest" l="Financial Interest" />
                <Field k="chassis_num" l="Chassis Number" />
                <Sel k="purpose" l="Purpose of use"
                  opts={[{id:'Private',name:'Private'},{id:'Commercial',name:'Commercial'},{id:'Public',name:'Public'}]} />
                <Sel k="key_reason" l="Is the key lost or damaged or stolen"
                  opts={[{id:'lost',name:'Lost'},{id:'damaged',name:'Damaged'},{id:'stolen',name:'Stolen'}]} />
                <Field k="estimate" l="Replacement Estimate" />
                <Field k="lossDate" l="Date of Loss/stolen/Damage *" type="date" />
                <Field k="registered_claim" l="Date of claim registered" type="date" />
              </div>
              <Area k="descriptionofLoss" l="Description *" />
              <FileInput k="police_affidavit" l="Police Affidavit" />

              <H>Insured Details</H>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <Field k="name_of_insured" l="Name of Insured" />
                <Field k="insured_address" l="Address" />
                <Field k="insured_occupation" l="Occupation" />
                <Field k="insured_email" l="Email" type="email" />
                <Field k="insured_contact_no" l="Contact No" />
              </div>

              <H>Vehicle Details</H>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <Field k="vehicle_plate" l="Registration Number" />
                <Sel k="is_imported" l="Is Imported?" opts={[{id:'Yes',name:'Yes'},{id:'No',name:'No'}]} />
                <Field k="make" l="Make" />
                <Field k="year" l="Manufacturing Year" />
                <Field k="model" l="Model" />
              </div>

              <H>Quotation</H>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <Field k="company_1" l="Quote 1 — Name of company" />
                <Field k="amount_quote_1" l="Quote 1 — Amount of quote" type="number" />
                <FileInput k="quote_1" l="Quote 1 — Document" />
                <Field k="company_2" l="Quote 2 — Name of company" />
                <Field k="amount_quote_2" l="Quote 2 — Amount of quote" type="number" />
                <FileInput k="quote_2" l="Quote 2 — Document" />
              </div>
            </>}

            {/* Classification footer for MIS motor only (formTemplate ===
                'vehicle'). DOM/COM motor claims get the full V8
                Classification & Allocation block rendered by the
                coverage_based IIFE below (it has its own ClassAlloc()
                helper in scope; rendering it here would duplicate). */}
            {formTemplate === 'vehicle' && !isKeyLossClaim && <>
              <H>Claim Classification</H>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <Sel k="claim_sub_type" l="Claim Sub-Type"  opts={filteredSubTypes} />
                <Sel k="type_of_loss"   l="Type of Loss"    opts={createData?.loss_types} />
                <Sel k="event_name"     l="Event Name"      opts={createData?.event_names} />
              </div>
            </>}
          </>
          })()}

          {/* ═══════════ CELLPHONE ═══════════
              Mirrors legacy graphiteBWV8 mobileAndElectronicDevices.blade:
                - Insured's Name / Email / Address
                - Property "Stolen or Damaged" (not the 5-way enum V2 used to have)
                - Date/time loss discovered + who discovered it
                - Device specs + 6 photos are V2 additions that stay (photos
                  are used by the assessor and have no legacy equivalent). */}
          {formTemplate === 'cellphone' && <>
            <H>Insured Details</H>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <Field k="insured_name"  l="Insured's Name *" />
              <Field k="email_address" l="E-mail Address" type="email" />
              <Field k="address"       l="Address" />
              <Field k="contact_number" l="Telephone / Mobile *" />
            </div>

            <H>Device Information</H>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <Field k="device_make"  l="Device Make *"  placeholder="Apple / Samsung" />
              <Field k="device_model" l="Device Model *" placeholder="iPhone 14" />
              <Field k="device_imei"  l="IMEI Number" />
              <Sel   k="damage_extent" l="Has property been stolen or damaged? *"
                opts={[{id:'damaged',name:'Damaged'},{id:'stolen',name:'Stolen'}]} />
              <Field k="lossDate" l="Date loss discovered *" type="date" />
              <Field k="date_reported_to_alpha" l="Date Reported to Alpha Direct *" type="date" />
            </div>
            <Area k="descriptionofLoss" l="Description of loss / damage *" />

            <H>Device Photos (all 6 required)</H>
            <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
              <FileInput k="cell_phone_front"  l="Front" />
              <FileInput k="cell_phone_back"   l="Back" />
              <FileInput k="cell_phone_left"   l="Left" />
              <FileInput k="cell_phone_right"  l="Right" />
              <FileInput k="cell_phone_top"    l="Top" />
              <FileInput k="cell_phone_bottom" l="Bottom" />
            </div>
          </>}

          {/* ═══════════ TYRE ═══════════ */}
          {formTemplate === 'tyre' && <>
            <H>Tyre & Rim Details</H>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <Field k="vehicle_plate" l="Vehicle Plate *" />
              <Field k="tyre_count" l="Number of Tyres Affected" type="number" min="1" max="4" />
              <Field k="tyre_size" l="Tyre Size" placeholder="e.g. 225/45 R18" />
              <Sel k="damage_location" l="Damage Location"
                opts={[{id:'front_left',name:'Front Left'},{id:'front_right',name:'Front Right'},{id:'rear_left',name:'Rear Left'},{id:'rear_right',name:'Rear Right'},{id:'multiple',name:'Multiple'}]} />
            </div>
          </>}

          {/* ═══════════ COVERAGE-BASED (COMG/DOMG/Specialist) ═══════════
              User flagged that weather / party-at-fault / type-of-loss /
              event-name from motor_claim_* lookups were leaking onto
              non-motor DOMG/COMG claims (e.g. Goods In Transit). Keep
              the Claim Sub-Type row visible always, and only show the
              motor-flavour rows when the operator explicitly ticks the
              Motor checkbox. Mirrors legacy which renders those fields
              conditionally on is_motor_claim. */}
          {isCoverageBased && <>
            {/* The outer "Claim Classification" block (Sub-Type / Motor /
                Catastrophe / Attorney) was removed — Sub-Type now lives
                inside Classification & Allocation, and Catastrophe Loss
                / Primary Attorney Involved render there as Yes/No
                dropdowns. Motor classification on a DOMG/COMG claim is
                derived from the policy product, not a manual checkbox. */}

            {/* ── DOMG/COMG claim-type-specific sub-forms ────────────────
                Each section renders only when the selected claim_type
                maps to the matching legacy sub-table. Fields align with
                graphiteBWV8 NewClaimController store methods:
                  BUSINESSINTERRUPTION → business_interruption
                  BURGLARY/THEFT       → burglary
                  FIDELITYGUARANTEE    → fidelity_guarantee
                  PROPERTYLOSSDAMAGE   → property_loss_damage
                  PUBLICLIABILITY      → public_liability
                  WORKERSCOMPENSATION  → workers_compensation
                  GOODSINTRANSIT       → goods_in_transit
                  BUSINESSALLRISKS     → all_risk_and_electronic_equipment
                Matching is done via .toUpperCase().replace(/[^A-Z0-9]/g,'')
                so "Business Interruption" and "BUSINESSINTERRUPTION" both
                hit the same branch. Backend applies the same
                normalization. */}
            {(() => {
              let t = (form.claim_type || '').toUpperCase().replace(/[^A-Z0-9]/g, '')
              // Engineering coverage names arrive with a singular "Risk"
              // ("Plant All Risk", "Contractors All Risk" — see
              // PolicyCreateController synthetic coverage names) but the
              // sub-form branches and the claims.claim_type ENUM use the
              // plural key (PLANTALLRISKS / CONTRACTORSALLRISKS). Canonicalise
              // so the fields actually render. Mirrors the backend
              // CLAIM_TYPE_ENUM_ALIASES map in ClaimsController.
              t = CLAIM_TYPE_KEY_ALIASES[t] ?? t

              // ─── Classification & Allocation block reused across all
              //     COMG/DOMG claim-type sub-forms. Lifted from the legacy
              //     shared footer (graphiteBWV8 main.blade.php:110+):
              //       Select Location, Claim Reported By
              //       Total Reserve / Paid amounts
              //       Type of Loss, Service Representative, Catastrophe Loss
              //       Primary/Co-Attorney, DFS Complaint
              //       Claims Allocated To/On, Date First Visited
              //
              // Renders only when the policy product is COMG/DOMG (product
              // 7 or 8) — backend tags coverage-based claim types with
              // form_template = 'coverage_based'. Other products keep the
              // narrower per-type form they already had.
              //
              // All fields persist to `new_claims` columns of the same
              // names; the V2 backend already accepts them in store() /
              // update() validate rules, so wiring is one-way (FE → BE).
              // graphiteBWV8 main.blade.php:177 only renders Claim Sub Type
              // for these claim type codes. Mirror the gate exactly so we
              // don't show the field where V8 hides it (Fire, Goods In
              // Transit, etc. don't get a sub-type picker).
              const SUB_TYPE_CLAIM_TYPES = new Set([
                'BUSINESSINTERRUPTION', 'BUSINESSALLRISKS', 'ELECTRONICEQUIPMENT',
                'PERSONALALLRISKS', 'THEFT', 'DEFECTIVEWORKMANSHIP',
                'FIDELITYGUARANTEE', 'WORKERSCOMPENSATION', 'STATEDBENEFITS', 'MONEY',
              ])
              const showClaimSubType = SUB_TYPE_CLAIM_TYPES.has(t)
              // All Risks family — V8 main.blade.php hides Reported by
              // Broker/Agent and surfaces Event Name on these claim types
              // instead. Mirror the swap inside ClassAlloc.
              const ALL_RISKS_TYPES = new Set([
                'BUSINESSALLRISKS', 'PERSONALALLRISKS', 'ELECTRONICEQUIPMENT', 'ALLRISK',
              ])
              const isAllRisksType = ALL_RISKS_TYPES.has(t)
              // Workers Compensation / Stated Benefits — V8 hides the
              // Reported by Broker/Agent dropdown for these too. Doesn't
              // surface Event Name either (unlike All Risks); the slot is
              // simply omitted from Classification & Allocation.
              const isWorkersCompType = t === 'WORKERSCOMPENSATION' || t === 'STATEDBENEFITS'

              const ClassAlloc = () => {
                if (!isCoverageBased) return null
                return (
                  <>
                    <H>Classification & Allocation</H>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      {/* graphiteBWV8 main.blade.php:179-187 — Lookup-driven
                          Claim Sub Type list filtered by claim_type code. The
                          /claims/sub-types-by-claim-type endpoint returns the
                          right rows; we save the lookup id to claim_sub_type_id. */}
                      {showClaimSubType && (
                        <Sel k="claim_sub_type_id" l="Claim Sub Type" opts={filteredSubTypes} />
                      )}
                      <Sel k="location_id" l="Select Location" opts={policyRiskAddresses} />
                      <Sel k="claim_reported_by" l="Claim Reported By" opts={createData?.reported_by} />
                      {/* DOM/COM Glass omits the financial reserve/paid
                          inputs (V8 glass.blade has no Reserve/Paid
                          section — those are filled by Finance later). */}
                      {/* Reserve is set at claim edit-time, not on the new-claim form (v5 #10). */}
                      {t !== 'GLASS' && (
                        <>
                          <Field k="paid_amount" l="Total Paid Amount (BWP)" type="number" placeholder="0.00" />
                        </>
                      )}
                      {/* DOM/COM Type of Loss is Property|Liability per V8
                          main.blade.php — different source from the motor
                          loss_types lookup. */}
                      <Sel k="type_of_loss" l="Type of Loss" opts={createData?.loss_types_dom_com} />
                      <Sel k="service_representative_id" l="Service Representative" opts={createData?.internal_users} />
                      <Sel k="catastrophe_loss" l="Catastrophe Loss" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                      <Sel k="attorney_involved" l="Primary Attorney Involved" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                    </div>
                    {/* V8 hides Primary Attorney Assigned + date until the
                        operator picks Yes on Primary Attorney Involved
                        (main.blade.php #primary_attorney_assigned). Mirror
                        with conditional render in React. Form uses string
                        '1' / '0' to align with the backend boolean rule. */}
                    {String(form.attorney_involved) === '1' && (
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <Sel k="primary_attorney_assigned_id" l="Primary Attorney Assigned" opts={createData?.attorneys_primary} />
                        <Field k="p_a_assigned_date" l="Primary Attorney Assigned Date" type="date" />
                      </div>
                    )}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <Sel k="co_attorney_involved" l="Co-Attorney Involved" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                    </div>
                    {String(form.co_attorney_involved) === '1' && (
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <Sel k="co_attorney_assigned_id" l="Co-Attorney Assigned" opts={createData?.attorneys_co} />
                        <Field k="c_a_assigned_date" l="Co-Attorney Assigned Date" type="date" />
                      </div>
                    )}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <Sel k="dfs_complaint" l="DFS Complaint" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                      <Sel k="claim_allocated_to" l="Claims Allocated To" opts={createData?.internal_users} />
                      <Field k="claim_allocated_on" l="Allocated Date" type="date" />
                      <Field k="date_first_visited" l="Date First Visited" type="date" />
                      {/* graphiteBWV8 main.blade.php Classification block —
                          Reported by Broker/Agent (union of users + agencies)
                          + Date of Loss + Description of Loss. Persists to
                          new_claims.{reportedByBrokerAgent,date_of_loss,description_of_loss}.
                          • All Risks family shows Event Name in this slot
                            instead of Reported by Broker/Agent.
                          • WORKERSCOMPENSATION / STATEDBENEFITS hide the
                            slot entirely (V8 doesn't render either field). */}
                      {isAllRisksType ? (
                        <Field k="event_name" l="Event Name" placeholder="Enter event name" />
                      ) : isWorkersCompType ? null : (
                        <Sel k="reportedByBrokerAgent" l="Reported by Broker/Agent" opts={createData?.agents_options} />
                      )}
                      {/* Glass omits Date of Loss — captured higher up
                          as Date of Damage (incident_date) in the
                          claim-specific block. */}
                      {t !== 'GLASS' && (
                        <Field k="date_of_loss" l="Date of Loss" type="date" />
                      )}
                    </div>
                    {/* Glass omits the free-text Description of Loss —
                        V8 captures Cause of Damage instead, which is
                        already in the Glass-specific block above. */}
                    {t !== 'GLASS' && (
                      <Area k="description_of_loss" l="Description of Loss" rows={3} />
                    )}
                  </>
                )
              }

              // ─── BUSINESS INTERRUPTION — graphiteBWV8 bussness_interruption.blade.php
              // Field keys match the business_interruption table columns 1:1
              // (V8 controller did request→column mapping; we go straight to
              // column names so the backend's array_intersect_key picks them up).
              // V8's blade has nature_of_interruption + details_and_estimated_amount_of_loss
              // commented out; we don't render them either.
              if (t === 'BUSINESSINTERRUPTION') return (
                <>
                  <H>Business Interruption Details</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Sel k="previously_suffered_loss" l="Have you previously suffered loss/damage?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                    <Field k="other_party_interest" l="Any other party with an interest in the insured property?" />
                    <Sel k="other_insurance_covering" l="Any other insurance covering this loss/damage?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  </div>
                  {ClassAlloc()}
                </>
              )

              // ─── BURGLARY / THEFT / MONEY — legacy burglary.blade.php
              // (main.blade.php: claimType == 'THEFT' || 'MONEY' → burglary)
              if (t === 'BURGLARY' || t === 'THEFT' || t === 'BURGLARYTHEFT' || t === 'MONEY') return (
                <>
                  <H>Burglary / Theft Details</H>
                  <Area k="address_of_premises" l="Address of premises where theft occurred *" rows={2} />
                  <Field k="date_time_police_advised" l="Date the police were advised of loss" type="date" />
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Sel k="anyone_on_premises" l="Was anyone at the premises during the burglary?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                    <Sel k="guarded_by_watchman" l="Is the premises guarded by a watchman?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  </div>
                  {form.anyone_on_premises === '1' && <Area k="anyone_on_premises_brief" l="Details in brief" rows={2} />}
                  <Sel k="premises_properly_secured" l="Were all means of access properly secured at the time of theft?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="total_value_contents_of_premises" l="Total value of contents of your premises at time of theft" rows={2} />
                  <Field k="stock_books_records_located" l="Where were stock books / records located at time of theft?" />
                  {ClassAlloc()}
                </>
              )

              // ─── FIDELITY GUARANTEE — legacy fidelity_guarantee.blade.php
              if (t === 'FIDELITYGUARANTEE') return (
                <>
                  <H>Fidelity Guarantee Details</H>
                  <Area k="defaulting_employees_name" l="Defaulting Employees (enter one per line as 'Name — Position')" rows={4}
                        placeholder="John Doe — Cashier&#10;Jane Smith — Accountant" />
                  <Sel k="employees_been_involved" l="Have the employees been involved in or suspected of any previous loss? *"
                       opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="circumstances" l="Full details of circumstances of the loss and how it was discovered" rows={4} />
                  {ClassAlloc()}
                </>
              )

              // ─── PROPERTY LOSS / DAMAGE — legacy property_loss_damage.blade.php
              // (main.blade.php: BUILDINGSCOMBINED || ACCIDENTALDAMAGE || HOUSEHOLDERS
              //   || HOUSEOWNERS || HOUSEOWNER-BUILDINGS || HOUSEHOLDERS-CONTENTS → property_loss_damage)
              if (t === 'PROPERTYLOSSDAMAGE' || t === 'PROPERTYDAMAGE' || t === 'BUILDINGSCOMBINED'
                  || t === 'ACCIDENTALDAMAGE' || t === 'HOUSEHOLDERS' || t === 'HOUSEOWNERS'
                  || t === 'HOUSEOWNERBUILDINGS' || t === 'HOUSEHOLDERSCONTENTS') return (
                <>
                  <H>Property Loss / Damage Details</H>
                  <Area k="loss_damage_discovered" l="When was loss/damage discovered?" rows={2} />
                  <Area k="loss_damage_occurred" l="Place where loss/damage occurred" rows={2} />
                  <Area k="premises_occupied" l="Were premises occupied? By whom?" rows={2} />
                  <Area k="last_occupied" l="If not occupied, when last occupied?" rows={2} />
                  <Area k="purpose_of_occupation" l="Purpose of occupation" rows={2} />
                  <Area k="nature_interruption" l="Nature of your interruption" rows={2} />
                  <Area k="loss_for_each_item" l="Details & estimated amount of loss for each item to be claimed" rows={3} />
                  <Area k="previously_suffered_loss" l="Have you previously suffered loss/damage?" rows={2} />
                  <Area k="give_details" l="If so, give details" rows={2} />
                  <Area k="name_of_insurer" l="If insured, provide name of insurer" rows={1} />
                  <Area k="reference_no_station" l="Police reference number and station and date reported" rows={2} />
                  <Area k="interest_insured_property" l="Any other party with an interest in the insured property?" rows={2} />
                  <Area k="other_insurance_covering" l="Any other insurance covering this loss/damage?" rows={2} />
                  <Area k="give_name_insurer" l="If so, give name of insurer" rows={1} />
                  <Area k="value_all_property" l="Estimated total value of all property insured" rows={1} />
                  <Area k="when_last_valued" l="When last valued?" rows={1} />
                  {ClassAlloc()}
                </>
              )

              // ─── PUBLIC LIABILITY — legacy public_liability.blade.php (full)
              // (main.blade.php: claimType == 'LIABILITY' → public_liability)
              if (t === 'PUBLICLIABILITY' || t === 'LIABILITY') return (
                <>
                  <H>Insured's Details</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="insured_name" l="Name" />
                    <Field k="insured_treding_name" l="Business or Trading name" />
                    <Field k="insured_postal_address" l="Postal Address" />
                    <Field k="insured_email" l="Email address" type="email" />
                    <Field k="insured_telephone_no" l="Telephone no" />
                    <Field k="insured_facsimile" l="Facsimile" />
                    <Field k="insured_mobile_no" l="Mobile no" />
                  </div>

                  <H>Details of the Accident / Incident</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="accident_date" l="Date" type="date" />
                    <Field k="accident_time" l="Time" type="time" />
                  </div>
                  <Area k="accident_incident" l="Location of accident / incident" rows={2} />
                  <Area k="accident_injuries" l="Details of damaged property and/or injuries suffered" rows={3} />
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Sel k="accident_liability" l="Have you admitted responsibility / liability?" opts={[{id:'YES',name:'Yes'},{id:'NO',name:'No'}]} />
                    <Sel k="accident_person" l="Does claim involve a product you manufacture or services provided?" opts={[{id:'YES',name:'Yes'},{id:'NO',name:'No'}]} />
                    <Sel k="accident_contacted" l="Were emergency services (ambulance/police/fire) contacted?" opts={[{id:'YES',name:'Yes'},{id:'NO',name:'No'}]} />
                  </div>

                  <H>Job at which the accident occurred</H>
                  <Field k="attach_contractor" l="Are you the head contractor? If not, who is?" />
                  <Field k="attach_employee" l="Was anyone other than yourself or employee involved?" />
                  <Area k="attach_employed" l="If so, give names, addresses and state by whom employed" rows={2} />
                  <Field k="attach_blame" l="Do you think you or any of your employee(s) was to blame?" />
                  <Area k="attach_circumstances" l="Has any other accident occurred under similar circumstances?" rows={2} />
                  <Field k="attach_property" l="Was there any damage to property?" />
                  <Area k="attach_details" l="If so, please give details" rows={2} />
                  <Area k="attach_owner" l="Name and address of property owner" rows={2} />
                  <Area k="attach_damage" l="Damage" rows={2} />

                  <H>Party Making Claim Against You</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="claim_name" l="Name" />
                    <Field k="claim_telephone" l="Telephone" />
                    <Field k="claim_mobile" l="Mobile no" />
                    <Field k="claim_postal" l="Postal address" />
                    <Field k="claim_solicitor" l="Solicitor's name" />
                  </div>

                  <H>Witness 1</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="witness1_name" l="Name" />
                    <Field k="witness1_telephone" l="Telephone no" />
                    <Field k="witness1_mobile" l="Mobile no" />
                    <Field k="witness1_postal" l="Postal address" />
                    <Field k="witness1_relationship" l="Relationship (employee/family/friend)" />
                  </div>

                  <H>Witness 2</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="witness2_name" l="Name" />
                    <Field k="witness2_telephone" l="Telephone no" />
                    <Field k="witness2_mobile" l="Mobile no" />
                    <Field k="witness2_postal" l="Postal address" />
                    <Field k="witness2_relationship" l="Relationship" />
                    <Field k="witness2_damage" l="Was there any damage to property?" />
                  </div>
                  {ClassAlloc()}
                </>
              )

              // ─── WORKERS COMPENSATION — legacy workers_compensation.blade.php
              // ─── WORKERS COMPENSATION / STATED BENEFITS ───
              // graphiteBWV8 workers_compensation.blade.php — Injured Person
              // + Accident sections. EMPLOYER section is commented out in V8
              // so we don't render it. address_of_contractor is revealed only
              // when your_direct_employ === '0' (V8 #your_direct_employ_div).
              if (t === 'WORKERSCOMPENSATION' || t === 'STATEDBENEFITS') return (
                <>
                  <H>The Injured Person</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="injured_name" l="Name *" />
                    <Field k="injured_age" l="Age" type="number" />
                    <Field k="injured_occupation" l="Normal Occupation" />
                    <Field k="injured_nationality" l="Nationality" />
                    <Sel k="injured_status" l="Status" opts={[{id:'Married',name:'Married'},{id:'Single',name:'Single'}]} />
                    <Field k="injured_service_period" l="Period of service" />
                    <Sel k="your_direct_employ" l="Is he/she in your direct employ?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  </div>
                  <Area k="injured_address" l="Address" rows={2} />
                  {form.your_direct_employ === '0' && (
                    <Area k="address_of_contractor" l="If not, give name and address of Contractor" rows={2} />
                  )}

                  <H>The Accident</H>
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Field k="date" l="Date *" type="date" />
                    <Field k="time" l="Time" type="time" />
                    <Field k="place" l="Place" />
                  </div>
                  <Field k="how_accident_occur" l="How did the accident occur?" />
                  <Field k="first_report_accident" l="When and to whom did he/she first report the accident?" />
                  <Field k="period_of_disablement" l="Probable period of disablement in your opinion" />
                  {ClassAlloc()}
                </>
              )

              // ─── FIRE — legacy fire.blade.php
              if (t === 'FIRE') return (
                <>
                  <H>Fire Details</H>
                  <Area k="address_of_theft_occurred" l="Address of premises where fire occurred *" rows={2} />
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="property_last_seen" l="When was the property last seen by you?" />
                    <Field k="date_time_of_theft" l="Date and time of fire *" type="datetime-local" />
                  </div>
                  <Area k="brief_description_incident" l="Brief description of incident *" rows={3} />
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="date_time_police_advised" l="Date and time police were advised of loss" type="datetime-local" />
                    <Field k="police_station_name" l="Name of police station" />
                  </div>
                  <Sel k="anyone_during_burglary" l="Was anyone at the premises during the loss?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  {form.anyone_during_burglary === '1' && <Area k="details_during_burglary" l="Details in brief who was in the premises" rows={2} />}
                  <Field k="days_premises_unoccupied" l="How many days have the premises been unoccupied in the past 12 months?" />
                  <Sel k="premises_guarded_by_watchman" l="Is the premises guarded by a watchman?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  {/* V8 #premises_guarded_by_watchman_div — name/telephone/
                      guard_during_fire are inside the watchman conditional. */}
                  {form.premises_guarded_by_watchman === '1' && <>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <Field k="name_of_guard" l="Name of guard" />
                      <Field k="telephone_of_guard" l="Telephone number of guard" />
                    </div>
                    <Area k="guard_during_fire" l="Where was the guard during the fire?" rows={2} />
                  </>}
                  {/* name_of_security_agent + contract_of_agreement are
                      OUTSIDE V8's watchman conditional (lines 109-128 of
                      fire.blade.php) — always rendered. */}
                  <Field k="name_of_security_agent" l="Name of security agent" />
                  <FileInput k="contract_of_agreement" l="Contract of agreement" />
                  <Sel k="premises_properly_secured" l="Were all means of access properly secured at time of fire?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Sel k="suspect_any_person" l="Do you suspect any person?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  {form.suspect_any_person === '1' && <Area k="suspect_person_details" l="Give details of suspect person" rows={2} />}
                  <Field k="total_value_premises_buildings" l="Total value of the buildings at time of loss (BWP)" type="number" />
                  <Sel k="other_insurance_against_fire" l="Any other insurances against fire on the same property?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  {form.other_insurance_against_fire === '1' && <Area k="insurance_against_fire_details" l="Details of other insurances against fire" rows={2} />}
                  <Field k="estimated_amount_of_damaged" l="Estimated amount of the damaged property (BWP)" type="number" />
                  <Area k="details_of_previous_loss" l="Previous losses in the premises or any other premises owned by you" rows={2} />
                  {ClassAlloc()}
                </>
              )

              // ─── MOBILE / ELECTRONIC DEVICES — legacy mobileAndElectronicDevices.blade.php
              if (t === 'MOBILEELECTRONICDEVICES' || t === 'OFFICECONTENTS') return (
                <>
                  <H>Insured Contact</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="insured_name" l="Insured's Name" />
                    <Field k="email_address" l="Email Address" type="email" />
                    <Field k="telephone_no" l="Telephone No" />
                    <Field k="address" l="Address" />
                  </div>
                  <H>Device / Property</H>
                  <Sel k="property_stolen_damaged" l="Has the property been stolen or damaged?" opts={[{id:'1',name:'Damaged'},{id:'0',name:'Stolen'}]} />
                  <Field k="date_time_loss_discovered" l="Date and time when loss/damage was discovered" type="datetime-local" />
                  <Field k="whom_discovered" l="By whom discovered?" />
                  <Sel k="is_sole_owner_of_property" l="Are you the sole owner of the property?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  {form.is_sole_owner_of_property === '0' && <Field k="sole_owner_of_property" l="Name of the owner" />}
                  {ClassAlloc()}
                </>
              )

              // ─── MEDICAL MALPRACTICE — legacy medical_malpractice.blade.php
              //     (same fields as contractors_all_risks_public_liability.blade.php)
              if (t === 'MEDICALMALPRACTICE') return (
                <>
                  <H>Section 1: Insured Party Information</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="insured_full_name" l="Full Name of Insured *" />
                    <Field k="professional_title_role" l="Professional Title / Role" />
                    <Field k="license_registration_number" l="License or Registration Number" />
                    <Field k="facility_practice_name" l="Facility / Practice Name" />
                    <Field k="insured_contact_number" l="Contact Number" />
                    <Field k="insured_email" l="Email Address" type="email" />
                  </div>
                  <Area k="address_of_practice" l="Address of Practice" rows={2} />

                  <H>Section 2: Claimant (Patient) Information</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="claimant_full_name" l="Full Name *" />
                    <Field k="claimant_date_of_birth" l="Date of Birth" type="date" />
                    <Field k="claimant_contact_number" l="Contact Number" />
                  </div>
                  <Area k="claimant_mailing_address" l="Mailing Address" rows={2} />

                  <H>Section 3: Details of Allegation</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="date_of_alleged_incident" l="Date of Alleged Incident *" type="date" />
                    <Field k="date_of_notification" l="Date of Notification of Allegation" type="date" />
                    <Field k="how_notified" l="How were you notified? (letter, legal notice)" />
                  </div>
                  <Area k="nature_of_services_provided" l="Nature of Services Provided" rows={3} />
                  <Area k="description_of_allegation" l="Detailed Description of Allegation *" rows={4} />

                  <H>Section 4: Supporting Information</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FileInput k="notification_letter" l="Notification letter / legal document" />
                    <FileInput k="patient_records" l="Patient records and treatment notes" />
                    <FileInput k="investigation_reports" l="Internal investigation reports" />
                    <FileInput k="correspondence" l="Correspondence with claimant" />
                    <FileInput k="expert_legal_opinions" l="Expert or legal opinions" />
                  </div>
                  {ClassAlloc()}
                </>
              )

              // ─── PROFESSIONAL INDEMNITY — legacy professional_indemnity.blade.php
              if (t === 'PROFESSIONALINDEMNITY') return (
                <>
                  <H>Insured's Details</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="type_of_business" l="Type of Business" />
                    <Field k="contact_person" l="Contact Person" />
                    <Field k="designation" l="Designation" />
                    <Field k="insured_email" l="Email Address" type="email" />
                    <Field k="insured_cell_tel" l="Cell / Tel Number" />
                  </div>

                  <H>Claimant / Potential Claimant Details</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Sel k="claimant_type" l="Claimant type" opts={[{id:'Business',name:'Business'},{id:'Individual',name:'Individual'}]} />
                    <Field k="claimant_name_surname" l="Name & Surname" />
                    <Field k="claimant_email" l="Email Address" type="email" />
                    <Field k="claimant_cell_tel" l="Cell / Tel Number" />
                  </div>

                  <H>Details of Contract and Claim</H>
                  <Area k="insured_retained_to_do" l="What was the insured retained/contracted to do?" rows={3} />
                  <Sel k="contract_in_place" l="Was there a contract in place?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  {form.contract_in_place === '1' && <FileInput k="contract_copy" l="Attach copy of contract" />}
                  {form.contract_in_place === '0' && <Area k="contract_no_details" l="Provide details" rows={2} />}
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="work_performed_date" l="When was the work performed?" type="date" />
                    <Field k="person_performed_work" l="Person who performed the work" />
                  </div>

                  <H>Circumstances</H>
                  <Area k="circumstances" l="Circumstances giving rise to the claim (allegations of negligence)" rows={4} />
                  <Field k="first_aware_date" l="When did the insured first become aware of the claim?" type="date" />
                  <Area k="reason_for_reporting" l="Reason for reporting the incident" rows={2} />
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Sel k="notification_purposes_only" l="Reported for notification purposes only?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                    <Sel k="verbal_written_demand" l="Received a verbal/written demand for compensation?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                    {form.verbal_written_demand === '1' && <Field k="demand_received_date" l="Date demand received" type="date" />}
                    <Sel k="served_with_summons" l="Has the insured been served with a Summons?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                    {form.served_with_summons === '1' && <Field k="summons_served_date" l="Date Summons served" type="date" />}
                    <Sel k="attorney_appointed" l="Has the insured appointed an Attorney / Loss Adjustor?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                    <Field k="amount_claimed" l="Amount claimed (BWP)" type="number" />
                  </div>
                  {form.attorney_appointed === '1' && <Area k="attorney_details" l="Attorney details" rows={2} />}

                  <H>Insured's Investigation</H>
                  <Sel k="own_investigation" l="Has the insured conducted their own investigation?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  {form.own_investigation === '1' && <FileInput k="investigation_findings" l="Attach findings" />}
                  <Area k="views_on_liability" l="Insured's views / comments on Liability" rows={3} />
                  <Area k="views_on_amount_claimed" l="Insured's views / comments on Amount Claimed" rows={3} />
                  <Area k="additional_details" l="Additional details to notify insurer" rows={3} />
                  {ClassAlloc()}
                </>
              )

              // ─── DEFECTIVE WORKMANSHIP — legacy defective_workmanship.blade.php
              // ─── DEFECTIVE WORKMANSHIP ───
              // graphiteBWV8 defective_workmanship.blade.php — three sections
              // (Accident/Incident details, Claimants Vehicle, Incident
              // Details). V8's form posts `Location_of_accident` (capital L)
              // but the DB column is lowercase; V2 uses the column name
              // directly so the backend's array_intersect_key picks it up.
              if (t === 'DEFECTIVEWORKMANSHIP') return (
                <>
                  <H>Details of the Accident / Incident</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="location_of_accident" l="Location of accident / incident *" />
                    <Field k="accident_date_time" l="Accident Date Time *" type="datetime-local" />
                    <Field k="owners_name" l="Owner's Name *" />
                    <Field k="telephone_number" l="Telephone Number *" />
                    <Field k="mobile_number" l="Mobile Number *" />
                  </div>
                  <Area k="address" l="Address *" rows={2} />

                  <H>Claimant's Vehicle</H>
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Field k="make" l="Make *" />
                    <Field k="model" l="Model *" />
                    <Field k="registration" l="Registration *" />
                  </div>
                  <Sel k="vehicle_drivable" l="Is the vehicle drivable? *" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />

                  <H>Incident Details</H>
                  <Sel k="vehicle_handed_claimant" l="Was the vehicle handed to the claimant?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Field k="when_vehicle_handed" l="When was the vehicle handed?" type="datetime-local" />
                  <Area k="allegations_received" l="Date and allegations received from claimant" rows={3} />
                  {ClassAlloc()}
                </>
              )

              // ─── ERECTION ALL RISK — legacy erection_all_risk.blade.php
              // ─── ERECTION ALL RISK ───
              // graphiteBWV8 erection_all_risk.blade.php — five labelled
              // sections (A: Insured, B: Accident, C: Damaged Section/
              // Works, D: Other Insurances, E: Previous Losses). Three
              // Yes/No fields conditionally reveal follow-up textareas
              // (responsible_for_damage, possibility_of_recovery,
              // third_party_liability).
              if (t === 'ERECTIONALLRISK') return (
                <>
                  <H>A. Details of Insured</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="insured_occupation" l="Occupation of the Insured" />
                    <Field k="supervisor_engineer_name" l="Name of Supervisor Engineer" />
                    <Field k="period_from" l="Period of Insurance: From" type="date" />
                    <Field k="period_to" l="Period of Insurance: To" type="date" />
                  </div>

                  <H>B. Particulars of Accident</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="date_of_occurrence" l="Date of Occurrence" type="date" />
                    <Field k="time_of_occurrence" l="Time of Occurrence" type="time" />
                  </div>
                  <Area k="site_of_damage" l="Site where damage occurred" rows={2} />
                  <Field k="nearest_railway_station" l="Nearest Railway Station" />

                  <H>Details of the Damage</H>
                  <Area k="damage_contract_works" l="a) Contract Works" rows={2} />
                  <Area k="damage_plant_equipment" l="b) Construction Plant & Equipment" rows={2} />
                  <Area k="damage_third_party_property" l="c) Property belonging to Third Parties" rows={2} />
                  <Area k="cause_of_damage" l="Cause of Damage" rows={2} />
                  <Sel k="responsible_for_damage" l="Is anyone responsible for the damage?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  {form.responsible_for_damage === '1' && (
                    <Area k="responsible_for_damage_details" l="Provide details" rows={2} />
                  )}
                  <Sel k="possibility_of_recovery" l="Is there any possibility of recovery?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  {form.possibility_of_recovery === '1' && (
                    <Area k="recovery_details" l="Recovery Details" rows={2} />
                  )}

                  <H>C. Details of the Damaged Section / Works</H>
                  <Area k="how_damage_occurred" l="How did the damage occur?" rows={2} />
                  <Area k="probable_cause" l="What was its probable cause?" rows={2} />
                  <Area k="progress_of_construction" l="Progress of construction at time of damage" rows={2} />
                  <Area k="how_items_repaired" l="How will the damaged items be repaired?" rows={2} />
                  <Sel k="alterations_during_repairs" l="Will alterations / improvements be made during repairs?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="witness_name" l="Name of Witness" />
                  </div>
                  <Area k="witness_address" l="Address of Witness" rows={2} />
                  <Sel k="surrounding_properties_damaged" l="Are existing buildings / surrounding properties damaged?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Sel k="third_party_liability" l="Is Third Party Liability involved?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  {form.third_party_liability === '1' && (
                    <Area k="third_party_liability_details" l="Third Party Liability Details" rows={2} />
                  )}

                  <H>Estimated Costs for Repair of Damage</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="estimated_cost_contract_works" l="a) Contract Works (BWP)" type="number" />
                    <Field k="estimated_cost_plant_machinery" l="b) Construction Plant & Machinery (BWP)" type="number" />
                    <Field k="estimated_cost_third_party_property" l="c) Third Party Property (BWP)" type="number" />
                    <Field k="estimated_cost_owners_surrounding" l="d) Owner's Surrounding Property (BWP)" type="number" />
                  </div>

                  <H>D. Details of Other Insurances</H>
                  <Area k="other_insurance_details" l="Provide details of other Insurance covering the present loss" rows={2} />

                  <H>E. Details of Previous Losses</H>
                  <Area k="previous_losses_details" l="Provide details of previous Claims, if any, on the project" rows={2} />
                  {ClassAlloc()}
                </>
              )

              // ─── PLANT ALL RISKS ───
              // graphiteBWV8 plant_all_risks.blade.php — four sections
              // (Responsible Person on Site / Site Details / Plant Details /
              // Loss-Damage Details with 3 Yes/No selects + Police fields).
              // V8 has no per-section file input on this blade — the legacy
              // `documentary_evidence` column was populated via a separate
              // attachments UI. Operators in V2 use the generic Attachments
              // tab for the same purpose, so no FileInput is rendered here.
              if (t === 'PLANTALLRISKS') return (
                <>
                  <H>Responsible Person on Site &amp; Contact Numbers</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="responsible_person_name" l="Name" />
                    <Field k="responsible_person_phone" l="Phone" />
                    <Field k="responsible_person_cellphone" l="Cellphone" />
                    <Field k="responsible_person_email" l="Email" type="email" />
                    <Field k="responsible_person_fax" l="Fax" />
                  </div>

                  <H>Site Details</H>
                  <Area k="site_physical_address" l="Site Physical Address" rows={2} />
                  <Field k="site_code" l="Code" />

                  <H>Plant Details</H>
                  <Area k="item_description" l="Item of Plant Stolen/Damaged (full description / model / serial number)" rows={3} />
                  <Field k="item_number_sum_insured" l="Item Number on Policy Schedule / Sum Insured" />

                  <H>Loss / Damage Details</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="date_of_loss" l="Date of Loss / Damage" type="date" />
                    <Field k="time_of_loss" l="Time" type="time" />
                  </div>
                  <Area k="details_of_loss" l="Details of Loss / Damage" rows={3} />
                  <Area k="cause_of_loss" l="Cause of Loss / Damage (e.g. Act of God / Operator Error)" rows={2} />
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="party_responsible_name" l="Party Responsible — Name" />
                    <Field k="party_responsible_contact" l="Party Responsible — Contact" />
                    <Field k="estimated_cost" l="Estimated Cost of Repair / Replacement (BWP)" type="number" />
                  </div>
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Sel k="uneconomical_to_repair"
                         l="Is the unit uneconomical to repair / write off?"
                         opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                    <Sel k="subject_to_finance"
                         l="Is the unit subject to Finance / Hire Purchase?"
                         opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                    <Sel k="on_hire_at_time"
                         l="Was the unit on hire at time of accident / theft?"
                         opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  </div>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="police_station" l="Police Station (Theft claims only)" />
                    <Field k="police_reference" l="Reference (Theft claims only)" />
                  </div>
                  {ClassAlloc()}
                </>
              )

              // ─── MACHINERY BREAKDOWN ───
              // Form AD-CLM-MB-001. Six sections mirroring the PDF. Yes/No
              // questions are Sel(1/0); the "Details" that follows each is an
              // always-visible Area.
              if (t === 'MACHINERYBREAKDOWN') return (
                <>
                  <H>Policy Details</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="insured" l="Insured" />
                    <Field k="period_of_insurance" l="Period of Insurance" />
                    <Field k="sum_insured" l="Sum Insured (BWP)" type="number" />
                  </div>

                  <H>Insured Contact &amp; Business</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="contact_person" l="Contact Person" />
                    <Field k="designation" l="Designation" />
                    <Field k="phone" l="Phone" />
                    <Field k="cellphone" l="Cellphone" />
                    <Field k="email" l="Email" type="email" />
                    <Field k="years_in_operation" l="Years in Operation" />
                  </div>
                  <Area k="postal_physical_address" l="Postal / Physical Address" rows={2} />
                  <Area k="nature_of_business" l="Nature of Business / Industry" rows={2} />

                  <H>Damaged Machinery</H>
                  <Area k="item_description" l="Description of Item per Policy Schedule" rows={2} />
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="make_model" l="Make / Model" />
                    <Field k="serial_number" l="Serial Number" />
                    <Field k="year_of_manufacture" l="Year of Manufacture" />
                    <Field k="date_commissioned" l="Date Originally Commissioned" type="date" />
                    <Field k="current_replacement_value" l="Current Replacement Value (BWP)" type="number" />
                  </div>
                  <Area k="technical_specs" l="Technical Specs (kW, RPM, voltage, capacity)" rows={2} />
                  <Sel k="under_amc_contract" l="Under Manufacturer / AMC Contract at date of loss?"
                       opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="amc_contract_details" l="AMC Contract — Details" rows={2} />

                  <H>Loss / Damage Details</H>
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Field k="date_of_loss" l="Date of Loss" type="date" />
                    <Field k="time_of_loss" l="Time of Loss" type="time" />
                    <Field k="date_loss_discovered" l="Date Loss Discovered" type="date" />
                  </div>
                  <Area k="site_location" l="Site / Location of Loss (full physical address or GPS)" rows={2} />
                  <Field k="equipment_status" l="Equipment status at time of loss (working / idle / cleaning / overhaul / relocation)" />
                  <Area k="cause_of_loss" l="Cause of Loss (mechanical, electrical, operator error, foreign body, short circuit, etc.)" rows={2} />
                  <Area k="damage_description" l="Description of Damage (which parts, extent of damage)" rows={3} />

                  <H>Repair, Replacement &amp; Salvage</H>
                  <Field k="estimated_cost" l="Estimated Cost of Repair / Replacement (BWP)" type="number" />
                  <Area k="proposed_repairer" l="Proposed Repairer / OEM Service Centre (Name, Address, Contact)" rows={2} />
                  <Area k="salvage_location" l="Salvage Location — where will the damaged item be physically held?" rows={2} />

                  <H>Financier, Recovery &amp; Other Insurance</H>
                  <Sel k="sole_owner" l="Sole owner of the damaged machinery?"
                       opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="sole_owner_details" l="Sole owner — Details" rows={2} />
                  <Area k="co_owner_financier" l="If no — co-owner / lessor / hire-purchase financier name" rows={2} />
                  <Sel k="subject_to_finance" l="Subject to bank loan, lease, hire-purchase or notarial bond?"
                       opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="finance_details" l="Finance — Details" rows={2} />
                  <Area k="financier_bank_reference" l="Financier / Bank name and account / agreement reference" rows={2} />
                  <Sel k="third_party_responsible" l="Third party (supplier / contractor / repairer) responsible for the loss?"
                       opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="third_party_details" l="Third party responsible — Details" rows={2} />
                  <Field k="third_party_name_contact" l="Third Party Name & Contact" />
                  <Sel k="recovery_claim_lodged" l="Recovery claim lodged with third party?"
                       opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="recovery_details" l="Recovery claim — Details" rows={2} />
                  <Sel k="other_insurance" l="Machinery insured under any other policy?"
                       opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="other_insurance_details" l="Other insurance — Details" rows={2} />
                  <Area k="other_insurer_policy" l="Other Insurer / Policy Number / Sum Insured" rows={2} />
                  <Area k="loss_history" l="Loss History — incidents involving this or similar machinery in the past 3 years (claimed or not)" rows={3} />

                  <Area k="procedural_improvements" l="Procedural improvements proposed or implemented to prevent recurrence" rows={3} />
                  {ClassAlloc()}
                </>
              )

              // ─── MACHINERY BREAKDOWN — LOSS OF PROFIT ───
              // Form AD MB LOP v1.0. Consequential financial loss filed after
              // the physical-damage MB claim. Five Yes/No selects each followed
              // by an always-visible details Area.
              if (t === 'MACHINERYBREAKDOWNLOSSOFPROFIT') return (
                <>
                  <H>Linked Machinery Breakdown Claim</H>
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Field k="mb_claim_number" l="Machinery Breakdown Claim Number" />
                    <Field k="date_of_breakdown" l="Date of Breakdown / Damage (start of indemnity period)" type="date" />
                    <Field k="mb_physical_claim_status" l="Status of MB Physical Damage Claim (notified / under assessment / settled)" />
                  </div>

                  <H>Insured Contact &amp; Business</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="insured" l="Insured (Registered Name)" />
                    <Field k="contact_person" l="Contact Person" />
                    <Field k="designation" l="Designation" />
                    <Field k="phone_cellphone" l="Phone / Cellphone" />
                    <Field k="email" l="Email" type="email" />
                    <Field k="years_in_operation" l="Years in Operation" />
                  </div>
                  <Area k="postal_physical_address" l="Postal / Physical Address" rows={2} />
                  <Area k="site_premises_affected" l="Site / Premises affected" rows={2} />
                  <Area k="nature_of_business" l="Nature of Business and main products / services" rows={2} />

                  <H>Operating Profile (Pre-Loss Baseline)</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="production_capacity" l="Production capacity (units / hour or units / day)" />
                    <Field k="operating_hours_per_day" l="Operating hours per day" />
                    <Field k="number_of_shifts" l="Number of shifts (1 / 2 / 3)" />
                    <Field k="operating_days_per_week" l="Number of operating days per week" />
                    <Field k="standard_turnover_prior_12m" l="Standard turnover for the prior 12 months (BWP)" type="number" />
                    <Field k="standard_output_prior_12m" l="Standard output for the prior 12 months (units)" />
                  </div>
                  <Sel k="is_seasonal" l="Is the business seasonal?"
                       opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="seasonal_details" l="Seasonal — Details" rows={2} />
                  <Area k="peak_months_pattern" l="Peak months / seasonal pattern (describe)" rows={2} />
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="comparable_period_turnover" l="Comparable period turnover — same period prior year (BWP)" type="number" />
                    <Field k="comparable_period_output" l="Comparable period output — same period prior year (units)" />
                  </div>

                  <H>Indemnity Period &amp; Downtime</H>
                  <Area k="damaged_item_description" l="Damaged item description and item number per Policy Schedule" rows={2} />
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="date_production_halted" l="Date production halted (if different from breakdown date)" type="date" />
                    <Field k="time_excess_start_end" l="Time excess (waiting period) start and end" />
                    <Field k="date_production_partial_resumed" l="Date production partially resumed" type="date" />
                    <Field k="date_production_full_resumed" l="Date production fully resumed (or expected)" type="date" />
                    <Field k="total_full_shutdown_days" l="Total full-shutdown days" />
                    <Field k="total_reduced_capacity_days" l="Total reduced-capacity days" />
                    <Field k="indemnity_period_max_end_date" l="Indemnity period max end-date (T-zero plus indemnity period)" type="date" />
                  </div>
                  <Sel k="loss_continuing" l="Is the loss continuing as at the date of this form?"
                       opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="loss_continuing_details" l="Loss continuing — Details" rows={2} />
                  <Area k="production_impact_description" l="Description of how the breakdown affected production / turnover (which lines, what %)" rows={3} />

                  <H>Loss of Profit — A. Reduction in Turnover / Output</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="standard_turnover_indemnity" l="Standard turnover during indemnity period to date (BWP)" type="number" />
                    <Field k="actual_turnover_indemnity" l="Actual turnover during indemnity period to date (BWP)" type="number" />
                    <Field k="reduction_in_turnover" l="Reduction in turnover (BWP)" type="number" />
                    <Field k="gross_profit_rate" l="Rate of Gross Profit applied (%) — to be confirmed by FA" />
                    <Field k="gross_profit_lost" l="Calculated Gross Profit lost (Reduction × Rate, BWP)" type="number" />
                  </div>

                  <H>Loss of Profit — B. Increased Cost of Working (ICW)</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="icw_outsourcing" l="Outsourcing / sub-contracting (BWP)" type="number" />
                    <Field k="icw_equipment_hire" l="Hire of replacement / temporary equipment (BWP)" type="number" />
                    <Field k="icw_express_freight" l="Express freight / expedited shipping (BWP)" type="number" />
                    <Field k="icw_overtime_labour" l="Overtime and additional labour (BWP)" type="number" />
                    <Field k="icw_temporary_premises" l="Temporary premises / utilities (BWP)" type="number" />
                    <Field k="icw_other" l="Other ICW (itemise) (BWP)" />
                    <Field k="icw_total" l="Total ICW (BWP)" type="number" />
                  </div>

                  <H>Loss of Profit — C. Savings (variable costs not incurred)</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="savings_raw_materials" l="Raw materials / consumables not used (BWP)" type="number" />
                    <Field k="savings_power_utilities" l="Power / utilities not consumed (BWP)" type="number" />
                    <Field k="savings_wages" l="Wages saved (staff stood down) (BWP)" type="number" />
                    <Field k="savings_other" l="Other savings (itemise) (BWP)" />
                    <Field k="savings_total" l="Total savings (BWP)" type="number" />
                  </div>

                  <H>Loss of Profit — D. Estimated Claim</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="gross_loss_of_profit" l="Estimated Gross Loss of Profit (A + B − C) (BWP)" type="number" />
                    <Field k="less_time_excess" l="Less Time Excess (waiting period deduction) (BWP)" type="number" />
                    <Field k="less_self_insured_retention" l="Less Self-Insured Retention (BWP)" type="number" />
                    <Field k="net_estimated_claim" l="Net estimated claim (BWP)" type="number" />
                  </div>

                  <H>Mitigation Actions</H>
                  <Area k="mitigation_steps" l="Mitigation steps taken (with dates)" rows={3} />
                  <Sel k="alt_production_available" l="Was alternative production capacity available within the group / region?"
                       opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="alt_production_details" l="Details (location, capacity used, cost)" rows={2} />
                  <Sel k="replacement_equipment_sourced" l="Was replacement / hire equipment sourced?"
                       opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="replacement_supplier_terms" l="Supplier and rental terms" rows={2} />

                  <H>Other Insurance &amp; Loss History</H>
                  <Sel k="other_bi_cover" l="Is there any other Business Interruption / LOP cover that may respond?"
                       opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="other_bi_details" l="Other BI / LOP cover — Details" rows={2} />
                  <Area k="other_insurer_policy" l="Other insurer / policy / period / sum insured" rows={2} />
                  <Area k="loss_history" l="Loss history — prior LOP / BI claims in past 24 months" rows={3} />
                  {ClassAlloc()}
                </>
              )

              // ─── DIRECTORS & OFFICERS LIABILITY ───
              // Form AD D&O v1.0. Claims-made notification. Tick-all groups use
              // Chk checkboxes; standalone Yes/No questions use Sel with a paired
              // details Area. Documents-attached checklist is intentionally
              // omitted — handled by the generic Attachments tab.
              if (t === 'DIRECTORSOFFICERSLIABILITY') return (
                <>
                  <H>Type of Notification (tick one or more)</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <Chk k="notif_claim" l="Claim — a written demand or proceeding served on an Insured Person" />
                    <Chk k="notif_circumstance" l="Circumstance — facts that may give rise to a Claim" />
                    <Chk k="notif_investigation" l="Investigation — regulatory or internal" />
                    <Chk k="notif_subpoena" l="Subpoena / Witness Summons issued to an Insured Person" />
                  </div>

                  <H>Policy Details</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="period_of_insurance" l="Period of Insurance" />
                    <Field k="retroactive_date" l="Retroactive Date" type="date" />
                    <Field k="limit_aggregate" l="Limit of Liability — Aggregate (BWP)" type="number" />
                    <Field k="limit_each_claim" l="Limit of Liability — Each Claim (BWP)" type="number" />
                    <Field k="self_insured_retention" l="Self-Insured Retention / Deductible (BWP)" type="number" />
                  </div>
                  <div className="mt-2">
                    <label className="block text-sm font-medium text-ink-muted mb-1">Policy Cover Sides (tick all applicable)</label>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                      <Chk k="side_a" l="Side A — direct cover for Insured Persons" />
                      <Chk k="side_b" l="Side B — reimbursement of the Company" />
                      <Chk k="side_c" l="Side C — entity cover (Company itself)" />
                      <Chk k="epl_extension" l="Employment Practices Liability extension" />
                    </div>
                  </div>

                  <H>Insured Entity</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="insured_company" l="Insured Company (registered name)" />
                    <Field k="company_registration_number" l="Company Registration Number" />
                    <Field k="regulator_license_number" l="NBFIRA / Regulator License Number (if applicable)" />
                    <Field k="industry_sector" l="Industry / Sector" />
                  </div>
                  <Area k="registered_address" l="Registered Address" rows={2} />
                  <Area k="company_secretary_contact" l="Company Secretary / Compliance Officer (Name & Contact)" rows={2} />

                  <H>Insured Person(s) Against Whom Claim Is Made</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="person1_name_id" l="Insured Person 1 — Name / Director ID" />
                    <Field k="person1_position" l="Person 1 — Position / Role" />
                    <Field k="person1_appointment_date" l="Person 1 — Date of Appointment" type="date" />
                    <Field k="person1_current_former" l="Person 1 — Current or Former Director / Officer" />
                    <Field k="person2_name_id" l="Insured Person 2 — Name / Director ID (if applicable)" />
                    <Field k="person2_position" l="Person 2 — Position / Role" />
                    <Field k="person2_appointment_date" l="Person 2 — Date of Appointment" type="date" />
                    <Field k="person2_current_former" l="Person 2 — Current or Former Director / Officer" />
                  </div>
                  <Area k="additional_insured_persons" l="Additional Insured Persons (attach annexure if more than 2)" rows={2} />

                  <H>Claim Trigger Dates (claims-made policy)</H>
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Field k="date_wrongful_act" l="Date Wrongful Act Allegedly Occurred" type="date" />
                    <Field k="date_claim_first_made" l="Date Claim First Made Against Insured Person" type="date" />
                    <Field k="date_insured_first_aware" l="Date Insured First Became Aware of Claim / Circumstance" type="date" />
                  </div>

                  <H>Claimant Details</H>
                  <label className="block text-sm font-medium text-ink-muted mb-1">Identity of Claimant (tick all that apply)</label>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <Chk k="claimant_shareholder" l="Shareholder / investor" />
                    <Chk k="claimant_regulator" l="Regulator (NBFIRA, BURS, Competition Authority, BSE, other)" />
                    <Chk k="claimant_liquidator" l="Liquidator / business rescue practitioner" />
                    <Chk k="claimant_employee" l="Employee or former employee" />
                    <Chk k="claimant_customer" l="Customer / counterparty" />
                    <Chk k="claimant_creditor" l="Creditor" />
                    <Chk k="claimant_government" l="Government / criminal prosecution" />
                    <Chk k="claimant_other" l="Other" />
                  </div>
                  <Field k="claimant_names" l="Claimant Name(s)" />
                  <Area k="claimant_legal_counsel" l="Claimant's Legal Counsel / Representative (Firm and Contact)" rows={2} />
                  <label className="block text-sm font-medium text-ink-muted mb-1 mt-3">Form of the Claim (tick all that apply)</label>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <Chk k="form_letter_demand" l="Letter of demand" />
                    <Chk k="form_summons" l="Summons / Statement of Claim filed" />
                    <Chk k="form_subpoena" l="Subpoena / witness summons" />
                    <Chk k="form_regulator_inquiry" l="Regulator inquiry / investigation notice" />
                    <Chk k="form_criminal_charge" l="Criminal charge" />
                    <Chk k="form_internal_investigation" l="Internal investigation initiated" />
                    <Chk k="form_other" l="Other" />
                  </div>

                  <H>Nature of Allegation</H>
                  <label className="block text-sm font-medium text-ink-muted mb-1">Nature of the Wrongful Act alleged (tick all that apply)</label>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <Chk k="alleg_fiduciary_breach" l="Breach of fiduciary duty / duty of care" />
                    <Chk k="alleg_misstatement" l="Misstatement / misrepresentation in financial statements or disclosures" />
                    <Chk k="alleg_insolvent_trading" l="Insolvent / reckless trading" />
                    <Chk k="alleg_misappropriation" l="Misappropriation of corporate opportunity / conflict of interest" />
                    <Chk k="alleg_regulatory_breach" l="Regulatory breach (NBFIRA, BURS, Competition, AML, data protection, listing rules)" />
                    <Chk k="alleg_employment_practices" l="Employment Practices — wrongful dismissal, discrimination, harassment" />
                    <Chk k="alleg_negligence" l="Negligence / failure of oversight" />
                    <Chk k="alleg_criminal" l="Criminal allegation (fraud, theft, corruption, bribery)" />
                    <Chk k="alleg_defamation" l="Defamation" />
                    <Chk k="alleg_other" l="Other" />
                  </div>
                  <Area k="allegation_description" l="Brief description of the allegation and the conduct alleged" rows={5} />
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="total_quantum_claimed" l="Total Quantum / Amount Claimed (BWP, if quantified)" type="number" />
                    <Field k="stage_of_proceedings" l="Stage of proceedings (pre-claim, demand, summons, hearing date, judgment)" />
                    <Field k="court_forum" l="Court / Forum (court name, regulator, tribunal)" />
                    <Field k="case_reference_number" l="Case / Reference Number" />
                  </div>

                  <H>Defense &amp; Counsel</H>
                  <Sel k="counsel_engaged" l="Has counsel been engaged?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="counsel_engaged_details" l="Counsel engaged — Details" rows={2} />
                  <Area k="counsel_firm_attorney" l="Counsel firm and lead attorney" rows={2} />
                  <Area k="counsel_contact_rate" l="Counsel firm contact and rate basis" rows={2} />
                  <Sel k="ad_prior_consent" l="Has Alpha Direct's prior consent been obtained?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="ad_prior_consent_details" l="Prior consent — Details" rows={2} />
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="estimated_defense_costs" l="Estimated defense costs incurred to date (BWP)" type="number" />
                    <Field k="next_hearing_deadline" l="Next pending hearing / response deadline (date)" type="date" />
                  </div>
                  <Sel k="settlement_offer_made" l="Has any settlement offer been made or received?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="settlement_offer_details" l="Settlement offer details (must not be accepted without AD consent)" rows={2} />

                  <H>Co-Defendants &amp; Other Insurance</H>
                  <Sel k="codefendants_insured_persons" l="Are other Insured Persons named as co-defendants?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="codefendants_details" l="Co-defendants — Details" rows={2} />
                  <Area k="codefendant_names" l="Names / Director IDs of co-defendants" rows={2} />
                  <Sel k="company_named" l="Is the Insured Company itself named?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="company_named_details" l="Company named — Details" rows={2} />
                  <Sel k="outside_parties_named" l="Are outside parties (non-Insureds) named?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="outside_parties_details" l="Outside parties — Details" rows={2} />
                  <Area k="outside_party_names" l="Names of outside parties" rows={2} />
                  <Sel k="other_insurance" l="Is there any other insurance that may respond (PI, EPLI, prior tower D&O)?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="other_insurance_details" l="Other insurance — Details" rows={2} />
                  <Area k="other_policy_details" l="Other policy / insurer / period / limit" rows={2} />
                  <Sel k="previously_notified" l="Has this Claim or Circumstance previously been notified to any insurer?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="previously_notified_details" l="Previously notified — Details" rows={2} />
                  <Area k="prior_notification_reference" l="Prior notification reference and insurer" rows={2} />
                  {ClassAlloc()}
                </>
              )

              // ─── MARINE CARGO ONCE-OFF (SINGLE VOYAGE) ───
              // Form AD Marine Cargo v1.0. Mode-of-transport and Type-of-Loss are
              // Chk tick-all groups; carrier/survey/recovery + financier use Sel
              // Yes/No with paired detail Areas. Documents-required checklist is
              // omitted — handled by the generic Attachments tab.
              if (t === 'MARINECARGOONCEOFF') return (
                <>
                  <H>Policy Details</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="certificate_number" l="Policy / Certificate Number" />
                    <Field k="insured" l="Insured" />
                    <Field k="period_of_cover" l="Period of Cover (this voyage)" />
                    <Field k="insured_value" l="Insured Value (CIF + 10%, BWP)" type="number" />
                    <Field k="conditions_of_cover" l="Conditions of Cover (e.g. Institute Cargo Clauses A / B / C)" />
                  </div>

                  <H>Insured Contact &amp; Business</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="contact_person" l="Contact Person" />
                    <Field k="designation" l="Designation" />
                    <Field k="phone" l="Phone" />
                    <Field k="cellphone" l="Cellphone" />
                    <Field k="email" l="Email" type="email" />
                  </div>
                  <Area k="postal_physical_address" l="Postal / Physical Address" rows={2} />
                  <Area k="nature_of_business" l="Nature of Business / Industry" rows={2} />

                  <H>Consignment / Cargo Details</H>
                  <Area k="description_of_goods" l="Description of Goods" rows={2} />
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="number_type_packages" l="Number and Type of Packages" />
                    <Field k="marks_numbers" l="Marks & Numbers" />
                    <Field k="gross_weight" l="Gross Weight (kg)" />
                    <Field k="net_weight" l="Net Weight (kg)" />
                    <Field k="commercial_invoice_number" l="Commercial Invoice Number" />
                    <Field k="invoice_value" l="Invoice Value (currency + amount)" />
                    <Field k="cif_value" l="CIF Value (BWP)" type="number" />
                    <Field k="container_number" l="Container Number (if containerised)" />
                    <Field k="seal_numbers" l="Seal Number(s)" />
                  </div>

                  <H>Voyage / Transit Details</H>
                  <label className="block text-sm font-medium text-ink-muted mb-1">Mode of transport (tick all legs that apply)</label>
                  <div className="grid grid-cols-2 md:grid-cols-3 gap-2">
                    <Chk k="mode_sea" l="Sea" />
                    <Chk k="mode_air" l="Air" />
                    <Chk k="mode_road" l="Road" />
                    <Chk k="mode_rail" l="Rail" />
                    <Chk k="mode_multimodal" l="Multimodal (combination)" />
                  </div>
                  <Area k="multimodal_route_description" l="Multimodal route description (if applicable)" rows={2} />
                  <Area k="origin" l="Origin (City, Country, Port / Airport)" rows={2} />
                  <Area k="destination" l="Destination (City, Country, Final Warehouse Address)" rows={2} />
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="vessel_aircraft_truck_reg" l="Vessel / Aircraft / Truck Registration" />
                    <Field k="voyage_flight_trip_no" l="Voyage No. / Flight No. / Trip No." />
                    <Field k="date_of_departure" l="Date of Sailing / Departure" type="date" />
                    <Field k="date_of_arrival" l="Date of Arrival at Destination" type="date" />
                    <Field k="bill_of_lading_number" l="Bill of Lading / Air Waybill / Waybill Number" />
                    <Field k="carrier" l="Carrier (Shipping Line / Airline / Trucking Co. / Rail Operator)" />
                  </div>
                  <Area k="freight_forwarder" l="Freight Forwarder / Clearing Agent (Name & Contact)" rows={2} />

                  <H>Loss / Damage Details</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="date_of_loss" l="Date of Loss / Damage" type="date" />
                    <Field k="date_loss_discovered" l="Date Loss Discovered" type="date" />
                    <Field k="date_ad_notified" l="Date Alpha Direct Notified" type="date" />
                  </div>
                  <Area k="place_stage_of_loss" l="Place / Stage at which Loss Occurred" rows={2} />
                  <label className="block text-sm font-medium text-ink-muted mb-1">Type of Loss (tick all that apply)</label>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <Chk k="loss_shortage" l="Shortage" />
                    <Chk k="loss_pilferage" l="Pilferage / Theft" />
                    <Chk k="loss_non_delivery" l="Non-Delivery" />
                    <Chk k="loss_damage_handling" l="Damage (handling / impact)" />
                    <Chk k="loss_wet_seawater" l="Wet damage / sea water" />
                    <Chk k="loss_freshwater" l="Fresh water damage" />
                    <Chk k="loss_fire_explosion" l="Fire / explosion" />
                    <Chk k="loss_hijacking" l="Hijacking / armed robbery" />
                    <Chk k="loss_sea_perils" l="Sea perils (heavy weather, stranding, collision, jettison)" />
                    <Chk k="loss_other" l="Other" />
                  </div>
                  <Area k="loss_description" l="Description of Loss / Damage (which items, extent of loss)" rows={4} />
                  <Field k="estimated_value_of_loss" l="Estimated Value of Loss (BWP)" type="number" />

                  <H>Carrier Notice, Survey &amp; Recovery</H>
                  <Sel k="notice_of_loss_issued" l="Was Notice of Loss issued to the carrier in writing?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="notice_of_loss_details" l="Notice of Loss — Details" rows={2} />
                  <Area k="notice_date_reference" l="Date Notice issued and reference" rows={2} />
                  <Sel k="carrier_acknowledged" l="Did the carrier acknowledge or reply?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="carrier_acknowledged_details" l="Carrier acknowledgement — Details" rows={2} />
                  <Area k="carrier_reply_reference" l="Carrier reply reference / date" rows={2} />
                  <Sel k="joint_survey_held" l="Was a joint survey held with the carrier?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="joint_survey_details" l="Joint survey — Details" rows={2} />
                  <Area k="surveyor_agent" l="Surveyor / Lloyd's Agent (Name & Contact)" rows={2} />
                  <Sel k="survey_report_attached" l="Survey report attached?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="survey_report_details" l="Survey report — Details" rows={2} />
                  <Sel k="police_report_attached" l="Police / port authority / customs report attached?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="police_report_details" l="Police / customs report — Details" rows={2} />
                  <Field k="police_station_ob" l="Police Station and OB Reference (theft / hijack only)" />
                  <Sel k="recovery_claim_lodged" l="Recovery claim lodged with carrier or freight forwarder?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="recovery_claim_details" l="Recovery claim — Details" rows={2} />
                  <Area k="carrier_name_address" l="Carrier Name and Address (for AD subrogation)" rows={2} />

                  <H>Financier, Other Insurance &amp; Loss History</H>
                  <Sel k="goods_financed" l="Are the goods financed under a letter of credit or bank loan?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="goods_financed_details" l="Goods financed — Details" rows={2} />
                  <Area k="bank_financier_reference" l="Bank / Financier name and reference" rows={2} />
                  <Sel k="other_insurance" l="Are the goods insured under any other policy?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="other_insurance_details" l="Other insurance — Details" rows={2} />
                  <Area k="other_insurer_policy" l="Other Insurer / Policy Number / Sum Insured" rows={2} />
                  <Area k="loss_history" l="Loss history — prior marine claims by the insured in the past 24 months" rows={3} />
                  <Area k="procedural_improvements" l="Procedural improvements proposed (packing, route, carrier selection)" rows={3} />
                  {ClassAlloc()}
                </>
              )

              // ─── MARINE CARGO OPEN COVER ───
              // Form AD-CLM-MAR-001. Same as the once-off marine form except the
              // policy header captures open-cover / per-consignment declaration
              // fields. Documents-required checklist omitted (Attachments tab).
              if (t === 'MARINECARGOOPENCOVER') return (
                <>
                  <H>Policy &amp; Open Cover Details</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="open_cover_policy_number" l="Open Cover Policy Number" />
                    <Field k="insured" l="Insured (Cover Holder)" />
                    <Field k="period_of_insurance" l="Period of Insurance" />
                    <Field k="annual_aggregate_sum_insured" l="Annual Aggregate Sum Insured (BWP)" type="number" />
                    <Field k="certificate_declaration_number" l="Certificate / Declaration Number for this Consignment" />
                    <Field k="date_of_declaration" l="Date of Declaration" type="date" />
                    <Field k="insured_value_declared" l="Insured Value declared for this Consignment (CIF + 10%)" type="number" />
                  </div>

                  <H>Insured Contact &amp; Business</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="contact_person" l="Contact Person" />
                    <Field k="designation" l="Designation" />
                    <Field k="phone" l="Phone" />
                    <Field k="cellphone" l="Cellphone" />
                    <Field k="email" l="Email" type="email" />
                  </div>
                  <Area k="postal_physical_address" l="Postal / Physical Address" rows={2} />
                  <Area k="nature_of_business" l="Nature of Business / Industry" rows={2} />

                  <H>Consignment / Cargo Details</H>
                  <Area k="description_of_goods" l="Description of Goods" rows={2} />
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="number_type_packages" l="Number and Type of Packages (e.g. 200 cartons, 1 x 40ft container)" />
                    <Field k="marks_numbers" l="Marks & Numbers" />
                    <Field k="gross_weight" l="Gross Weight (kg)" />
                    <Field k="net_weight" l="Net Weight (kg)" />
                    <Field k="commercial_invoice_number" l="Commercial Invoice Number" />
                    <Field k="invoice_value" l="Invoice Value (currency + amount)" />
                    <Field k="cif_value" l="CIF Value (BWP)" type="number" />
                    <Field k="container_number" l="Container Number (if containerised)" />
                    <Field k="seal_numbers" l="Seal Number(s)" />
                  </div>

                  <H>Voyage / Transit Details</H>
                  <label className="block text-sm font-medium text-ink-muted mb-1">Mode of transport (tick all legs that apply)</label>
                  <div className="grid grid-cols-2 md:grid-cols-3 gap-2">
                    <Chk k="mode_sea" l="Sea" />
                    <Chk k="mode_air" l="Air" />
                    <Chk k="mode_road" l="Road" />
                    <Chk k="mode_rail" l="Rail" />
                    <Chk k="mode_multimodal" l="Multimodal (combination)" />
                  </div>
                  <Area k="multimodal_route_description" l="Multimodal route description (if applicable)" rows={2} />
                  <Area k="origin" l="Origin (City, Country, Port / Airport)" rows={2} />
                  <Area k="destination" l="Destination (City, Country, Final Warehouse Address)" rows={2} />
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="vessel_aircraft_truck_reg" l="Vessel / Aircraft / Truck Registration" />
                    <Field k="voyage_flight_trip_no" l="Voyage No. / Flight No. / Trip No." />
                    <Field k="date_of_departure" l="Date of Sailing / Departure" type="date" />
                    <Field k="date_of_arrival" l="Date of Arrival at Destination" type="date" />
                    <Field k="bill_of_lading_number" l="Bill of Lading / Air Waybill / Waybill Number" />
                    <Field k="carrier" l="Carrier (Shipping Line / Airline / Trucking Co. / Rail Operator)" />
                  </div>
                  <Area k="freight_forwarder" l="Freight Forwarder / Clearing Agent (Name & Contact)" rows={2} />

                  <H>Loss / Damage Details</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="date_of_loss" l="Date of Loss / Damage" type="date" />
                    <Field k="date_loss_discovered" l="Date Loss Discovered" type="date" />
                  </div>
                  <Area k="place_stage_of_loss" l="Place / Stage at which Loss Occurred (port, in-transit, customs, warehouse)" rows={2} />
                  <label className="block text-sm font-medium text-ink-muted mb-1">Type of Loss (tick all that apply)</label>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <Chk k="loss_shortage" l="Shortage" />
                    <Chk k="loss_pilferage" l="Pilferage / Theft" />
                    <Chk k="loss_non_delivery" l="Non-Delivery" />
                    <Chk k="loss_damage_handling" l="Damage (handling / impact)" />
                    <Chk k="loss_wet_seawater" l="Wet damage / sea water" />
                    <Chk k="loss_freshwater" l="Fresh water damage" />
                    <Chk k="loss_fire_explosion" l="Fire / explosion" />
                    <Chk k="loss_hijacking" l="Hijacking / armed robbery" />
                    <Chk k="loss_sea_perils" l="Sea perils (heavy weather, stranding, collision, jettison)" />
                    <Chk k="loss_other" l="Other" />
                  </div>
                  <Area k="loss_description" l="Description of Loss / Damage (which items, extent of loss)" rows={4} />
                  <Field k="estimated_value_of_loss" l="Estimated Value of Loss (BWP)" type="number" />

                  <H>Carrier Notice, Survey &amp; Recovery</H>
                  <Sel k="notice_of_loss_issued" l="Was Notice of Loss issued to the carrier in writing?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="notice_of_loss_details" l="Notice of Loss — Details" rows={2} />
                  <Area k="notice_date_reference" l="Date Notice issued and reference" rows={2} />
                  <Sel k="carrier_acknowledged" l="Did the carrier acknowledge or reply?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="carrier_acknowledged_details" l="Carrier acknowledgement — Details" rows={2} />
                  <Area k="carrier_reply_reference" l="Carrier reply reference / date" rows={2} />
                  <Sel k="joint_survey_held" l="Was a joint survey held with the carrier?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="joint_survey_details" l="Joint survey — Details" rows={2} />
                  <Area k="surveyor_agent" l="Surveyor / Lloyd's Agent (Name & Contact)" rows={2} />
                  <Sel k="survey_report_attached" l="Survey report attached?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="survey_report_details" l="Survey report — Details" rows={2} />
                  <Sel k="police_report_attached" l="Police / port authority / customs report attached?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="police_report_details" l="Police / customs report — Details" rows={2} />
                  <Field k="police_station_ob" l="Police Station and OB Reference (theft / hijack only)" />
                  <Sel k="recovery_claim_lodged" l="Recovery claim lodged with carrier or freight forwarder?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="recovery_claim_details" l="Recovery claim — Details" rows={2} />
                  <Area k="carrier_name_address" l="Carrier Name and Address (for AD subrogation)" rows={2} />

                  <H>Financier, Other Insurance &amp; Loss History</H>
                  <Sel k="goods_financed" l="Are the goods financed under a letter of credit or bank loan?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="goods_financed_details" l="Goods financed — Details" rows={2} />
                  <Area k="bank_financier_reference" l="Bank / Financier name and reference" rows={2} />
                  <Sel k="other_insurance" l="Are the goods insured under any other policy?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  <Area k="other_insurance_details" l="Other insurance — Details" rows={2} />
                  <Area k="other_insurer_policy" l="Other Insurer / Policy Number / Sum Insured" rows={2} />
                  <Area k="loss_history" l="Loss History — claims under this Open Cover in the past 24 months" rows={3} />
                  <Area k="procedural_improvements" l="Procedural improvements proposed to prevent recurrence (packing, route, carrier selection)" rows={3} />
                  {ClassAlloc()}
                </>
              )

              // ─── CONTRACTORS ALL RISKS / PUBLIC LIABILITY ───
              // graphiteBWV8 contractors_all_risks_public_liability.blade.php.
              // Four sections (Responsible Person / Contract Details /
              // Insurance Responsibility / Loss-Damage Details with two
              // S3-uploaded file fields). V8 ContractorsAllRisksPublic
              // LiabilityStore() persists to contractors_all_risks_public
              // _liability table — V2 mirrors columns 1:1.
              if (t === 'CONTRACTORSALLRISKS' || t === 'CONTRACTORSALLRISKSPUBLICLIABILITY' || t === 'CARPL') return (
                <>
                  <H>Responsible Person on Site &amp; Contact Numbers</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="responsible_person_name" l="Name" />
                    <Field k="responsible_person_phone" l="Phone" />
                    <Field k="responsible_person_cellphone" l="Cellphone" />
                    <Field k="responsible_person_email" l="Email" type="email" />
                    <Field k="responsible_person_fax" l="Fax" />
                  </div>

                  <H>Contract Details</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="parties_to_contract" l="Parties to the Contract" />
                    <Field k="contract_value" l="Contract Value (incl. free issue materials) (BWP)" type="number" />
                    <Field k="contract_number" l="Contract Number" />
                    <Field k="code" l="Code" />
                    <Field k="contract_commencement_date" l="Contract Commencement Date" type="date" />
                    <Field k="expected_contract_completion_date" l="Expected Contract Completion Date" type="date" />
                  </div>
                  <Area k="description_of_contract" l="Description of Contract" rows={2} />
                  <Area k="site_physical_address" l="Site Physical Address" rows={2} />

                  <H>Insurance Responsibility</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Sel k="responsible_contract_works_claim" l="Responsible for arranging Project Insurance (Contract Works)?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                    <Sel k="responsible_public_liability_claim" l="Responsible for arranging Public Liability Insurance?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  </div>

                  <H>Loss / Damage Details</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="loss_date" l="Date of Loss / Damage" type="date" />
                    <Field k="loss_time" l="Time" type="time" />
                  </div>
                  <Area k="loss_details" l="Details of Loss / Damage" rows={3} />
                  <Area k="cause_of_loss" l="Cause of Loss / Damage" rows={2} />
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="party_responsible_name" l="Party Responsible — Name" />
                    <Field k="party_responsible_contact" l="Party Responsible — Contact" />
                  </div>
                  <Field k="estimated_cost_of_repair_replacement" l="Estimated Cost of Repair / Replacement (BWP)" type="number" />
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FileInput k="works_claim_documentary_evidence" l="Documentary evidence" />
                    <FileInput k="works_claim_bill_of_quantities" l="Bill of Quantities extracts" />
                  </div>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="police_station" l="Police Station (theft only)" />
                    <Field k="police_reference" l="Reference (theft only)" />
                  </div>
                  {ClassAlloc()}
                </>
              )

              // ─── TRAVEL INSURANCE — legacy travel_insurance.blade.php
              if (t === 'TRAVELINSURANCE') return (
                <>
                  {/* graphiteBWV8 travel_insurance.blade.php — V8 string
                      values are used as-is (e.g. "Medical Expenses" with
                      spaces) so the type_of_refund column matches V8
                      data shape and the conditional refund-doc sections
                      reveal cleanly. */}
                  <H>Claimant Details</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Sel k="title" l="Title" opts={[{id:'Mr',name:'Mr'},{id:'Mrs',name:'Mrs'},{id:'Miss',name:'Miss'},{id:'Ms',name:'Ms'},{id:'Other',name:'Other'}]} />
                    {form.title === 'Other' && <Field k="other_title" l="Other title" />}
                    <Field k="surname" l="Surname *" />
                    <Field k="forename" l="Forename(s) *" />
                    <Field k="dob" l="Date of Birth *" type="date" />
                    <Field k="passport_no" l="Passport No" />
                    <Field k="nationality" l="Nationality" />
                    <Field k="telephone" l="Telephone" />
                    <Field k="post_code" l="Post Code" />
                    <Field k="mobile" l="Mobile" />
                    <Field k="email" l="Email" type="email" />
                  </div>
                  <Area k="home_address" l="Home Address" rows={2} />

                  <H>Travel Insurance Policy and Journey Details</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="policy_number" l="Policy Number" />
                    <Field k="issued_by" l="Issued by (Insurance Company)" />
                    <Field k="issued_on" l="Issued on" />
                    <Field k="valid_from" l="Valid from" type="date" />
                    <Field k="valid_to" l="Valid to" type="date" />
                  </div>

                  <H>Bank Details (for Claim Reimbursement Purposes only)</H>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="beneficiary" l="Beneficiary (if different from insured)" />
                    <Field k="bank_name" l="Bank Name" />
                    <Field k="bank_address" l="Bank Address" />
                    <Field k="account_number" l="Account Number" />
                    <Field k="iban" l="IBAN" />
                    <Field k="swift_code" l="SWIFT Code" />
                    <Field k="bic_code" l="BIC Code" />
                  </div>

                  <H>Do you have any other Insurance Policy?</H>
                  <Sel k="other_insurance_policy" l="Other Insurance Policy"
                       opts={[{id:'No',name:'No'},{id:'Yes',name:'Yes'}]} />
                  {form.other_insurance_policy === 'Yes' && (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <Field k="name_insurance_company" l="Name of the Insurance Company" />
                      <Field k="phone_number" l="Phone Number" />
                      <Area k="address" l="Address" rows={2} />
                    </div>
                  )}

                  <H>Compulsory Documentation</H>
                  <FileInput k="compulsory_doc_proof_of_residence" l="Proof of residence in the Country where the Policy was issued *" />
                  <FileInput k="compulsory_doc_claim_form"          l="Claim form duly completed *" />
                  <FileInput k="compulsory_doc_insurance_policy"    l="Copy of Insurance Policy *" />
                  <FileInput k="compulsory_doc_detailed_letter"     l="Detailed letter explaining the loss *" />
                  <FileInput k="compulsory_doc_receipts"            l="ORIGINAL official Receipts of ALL incurred costs *" />
                  <FileInput k="compulsory_doc_passport_copy"       l="Copy of insured's passport showing the FIRST page and the exit/entry dates from country of residence *" />

                  <H>Type of Refund</H>
                  <Sel k="type_of_refund" l="Type of Refund"
                       opts={[
                         {id:'Medical Expenses',name:'Medical Expenses'},
                         {id:'Emergency Dental Care',name:'Emergency Dental Care'},
                         {id:'Delayed Luggage',name:'Delayed Luggage'},
                         {id:'Loss of Luggage',name:'Loss of Luggage'},
                         {id:'Flight Delay',name:'Flight Delay'},
                         {id:'Delayed Departure',name:'Delayed Departure'},
                         {id:'Loss of Personal Documents',name:'Loss of Personal Documents'},
                         {id:'Trip Cancellation',name:'Trip Cancellation'},
                         {id:'Curtailment',name:'Curtailment'},
                       ]} />

                  {(form.type_of_refund === 'Medical Expenses' || form.type_of_refund === 'Emergency Dental Care') && (
                    <>
                      <H>Claim for MEDICAL EXPENSES / EMERGENCY DENTAL CARE — Upload Documents</H>
                      <FileInput k="medical_dental_care_doc_1" l="Medical report with admission medical clinic" />
                      <FileInput k="medical_dental_care_doc_2" l="Clinical and/or Laboratory Results" />
                      <FileInput k="medical_dental_care_doc_3" l="Bank Account Information" />
                    </>
                  )}

                  {form.type_of_refund === 'Delayed Luggage' && (
                    <>
                      <H>Claim for DELAYED LUGGAGE — Upload Documents</H>
                      <FileInput k="claim_delayed_luggage_doc_1" l="Property Irregularity Report issued by the Carrier" />
                      <FileInput k="claim_delayed_luggage_doc_2" l="Incident Report from Client" />
                      <FileInput k="claim_delayed_luggage_doc_3" l="Original receipts for basic necessity items bought" />
                    </>
                  )}

                  {form.type_of_refund === 'Loss of Personal Documents' && (
                    <>
                      <H>Claim for LOSS OF PERSONAL DOCUMENTS — Upload Documents</H>
                      <FileInput k="claim_loss_personal_doc_doc_1" l="Statement of Loss (Police report)" />
                      <FileInput k="claim_loss_personal_doc_doc_2" l="Receipts of document replacement incurred costs" />
                    </>
                  )}

                  {form.type_of_refund === 'Loss of Luggage' && (
                    <>
                      <H>Claim for LOST LUGGAGE — Upload Documents</H>
                      <FileInput k="claim_lost_luggage_doc_1" l="Property Irregularity Report issued by the Carrier" />
                      <FileInput k="claim_lost_luggage_doc_2" l="Certificate of lost luggage issued by the Carrier" />
                      <FileInput k="claim_lost_luggage_doc_3" l="Copy of the Carrier settlement/reimbursement form" />
                      <FileInput k="claim_lost_luggage_doc_4" l="Incident Report from Client" />
                    </>
                  )}

                  {(form.type_of_refund === 'Trip Cancellation' || form.type_of_refund === 'Curtailment') && (
                    <>
                      <H>Claim for TRIP CANCELLATION or TRIP CURTAILMENT — Upload Documents</H>
                      <FileInput k="claim_trip_cancel_doc_1" l="List of the services hired for the trip (accommodation, flights, etc...)" />
                      <FileInput k="claim_trip_cancel_doc_2" l="Conditions and proof of cancellation of the said services" />
                      <FileInput k="claim_trip_cancel_doc_3" l="Certificate of non-refundable costs" />
                      <FileInput k="claim_trip_cancel_doc_4" l="The payment receipts of the hired services for the trip" />
                    </>
                  )}

                  {(form.type_of_refund === 'Flight Delay' || form.type_of_refund === 'Delayed Departure') && (
                    <>
                      <H>Claim for DELAYED FLIGHT — Upload Documents</H>
                      <FileInput k="claim_delayed_flight_doc_1" l="Certificate Issued by the Carrier" />
                      <FileInput k="claim_delayed_flight_doc_2" l="Copy of original travel ticket" />
                      <FileInput k="claim_delayed_flight_doc_3" l="Copy of replacement ticket indicating the paid amount" />
                    </>
                  )}

                  {ClassAlloc()}
                </>
              )

              if (t === 'GOODSINTRANSIT') return (
                <>
                  {/* Legacy-aligned Goods In Transit form — mirrors
                      graphiteBWV8/resources/views/admin/claims/newClaims/types/goods_in_transit.blade.php
                      field-for-field, with the same column names the legacy
                      NewClaimController::storeGoodsInTransit expects. */}
                  <H>Goods In Transit Details</H>
                  <Area k="address_of_premises_loss" l="Address of premises where loss occurred *" rows={2} />
                  <Area k="details_of_driver" l="Details of the carrier / driver" rows={2} />
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="property_last_seen" l="When was the property last seen by you?" />
                    <Field k="date_time_of_loss" l="Date and time of loss *" type="datetime-local" />
                  </div>
                  <Area k="brief_description_incident" l="Brief description of incident *" rows={3} />
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Field k="date_time_police_advised" l="Date and time the police were advised of loss" type="datetime-local" />
                    <Field k="police_station_name" l="Name of police station" />
                    <Field k="witnesses_name" l="Names of witnesses" />
                    <Field k="witnesses_mobile_number" l="Witnesses mobile number" />
                    <Field k="total_value_of_loss" l="What is the total value of the loss? (BWP)" type="number" />
                    <Field k="consignment_transported_to" l="Where was the consignment being transported to?" />
                    <Field k="consignment_from" l="Where was the consignment from?" />
                    <Field k="vehicle_registration_number" l="Registration number of the vehicle carrying the goods" />
                  </div>
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Sel k="is_carrier_contracted" l="Is the carrier contracted?"
                         opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                    <Sel k="carrier_has_own_GIT_ins" l="Does the carrier have their own GIT insurance?"
                         opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                    <Sel k="other_insurance_against_theft" l="Other insurance against theft on same property?"
                         opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                  </div>
                  {form.is_carrier_contracted === '1' && (
                    <FileInput k="copy_of_contract" l="Please provide a copy of the contract (PDF/Image)" />
                  )}
                  {form.other_insurance_against_theft === '1' && (
                    <Area k="insurance_against_theft_details" l="Give details in brief" rows={2} />
                  )}
                  <Area k="details_of_previous_loss_records"
                        l="Give details of records of previous loss in the premises or any other premises on similar goods"
                        rows={2} />

                  {ClassAlloc()}
                </>
              )

              // ─── ALL RISKS / ELECTRONIC EQUIPMENT / PERSONAL ALL RISKS
              // graphiteBWV8 main.blade.php (BUSINESSALLRISKS || ELECTRONICEQUIPMENT
              // || PERSONALALLRISKS) → all_risk.blade.php. The blade has two
              // conditional sub-blocks (V8 selectors #otherCauseInput and
              // #classStolen) — mirror them here so operators only see the
              // follow-up fields when their owning radio reveals them.
              // Note: ACCIDENTALDAMAGE routes to property_loss_damage, not here.
              if (t === 'BUSINESSALLRISKS' || t === 'PERSONALALLRISKS' || t === 'ALLRISK' || t === 'ELECTRONICEQUIPMENT') return (
                <>
                  <H>All Risks Details</H>
                  <Sel k="property_stolen_damaged" l="Has the property been stolen or damaged?" opts={[{id:'1',name:'Damaged'},{id:'0',name:'Stolen'}]} />
                  <Sel k="loss_cause" l="Have you ever before sustained previous loss?" opts={[{id:'1',name:'Loss by theft'},{id:'0',name:'Loss by other cause'}]} />
                  {/* V8 #otherCauseInput — text only when "other cause" is picked */}
                  {form.loss_cause === '0' && (
                    <Field k="loss_by_other_cause" l="Other cause (please provide details)" />
                  )}
                  {/* V8 #classStolen — only revealed when property is Stolen
                      (property_stolen_damaged === '0'). Damaged claims don't
                      ask about owner / search / locked premises. */}
                  {form.property_stolen_damaged === '0' && (
                    <>
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <Sel k="stolenfromcar_unlockedpremises" l="Was the property stolen from a car or unlocked premises?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                        <Sel k="thorough_search_made_for_article" l="Has a thorough search been made for the article(s)?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                        <Sel k="is_sole_owner_of_property" l="Are you the sole owner of the property?" opts={[{id:'1',name:'Yes'},{id:'0',name:'No'}]} />
                        {form.is_sole_owner_of_property === '0' && (
                          <Field k="sole_owner_of_property" l="Name of the owner" />
                        )}
                      </div>
                    </>
                  )}
                  {ClassAlloc()}
                </>
              )

              // ── Motor claim types under DOM/COM ────────────────────
              // V8 dispatches MOTORACCIDENT / MOTORTRADERSEXTERNAL /
              // MOTORTRADERSINTERNAL / GLASS / LOCKSANDKEYS through the
              // same accident/glass/key_loss blades. The motor IIFE
              // above renders those claim-type-specific sub-forms (it
              // fires for coverage_based motor codes too). Here we
              // just append the V8 main.blade Classification &
              // Allocation footer to match the V8 screenshot for
              // DOM/COM motor claims — without this, DOM/COM motor
              // forms wouldn't surface Reported by Broker/Agent,
              // Service Representative, Catastrophe Loss, attorney
              // toggles, allocation, etc.
              if (t === 'MOTORACCIDENT' || t === 'GLASS' || t === 'LOCKSANDKEYS'
                  || t === 'MOTORTRADERSEXTERNAL' || t === 'MOTORTRADERSINTERNAL') {
                return ClassAlloc()
              }

              return null
            })()}
          </>}

          {/* ═══════════ COMMON — Incident Details + Supporting Documents ═══════════
              HIDDEN per user request (2026-04-23): these generic blocks
              duplicated fields already captured in the type-specific blocks
              above and cluttered the form. Each claim-type block now owns
              its own classification + allocation + docs (see GIT block for
              the canonical layout). Kept the {false && ...} wrapper rather
              than deleting in case we need to re-enable for a new product
              that has no bespoke sub-form. */}
          {false && formTemplate === 'coverage_based' && <>
            <H>Incident Details</H>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <Field k="incident_date" l="Incident Date" type="date" />
              <Field k="incident_time" l="Incident Time" type="time" />
              <Sel k="reported_by" l="Reported By" opts={createData?.reported_by} />
              <Sel k="claim_allocated_to" l="Claim Allocated To" opts={createData?.internal_users} />
              <Field k="claim_allocated_on" l="Claim Allocated On" type="date" />
            </div>
            <Field k="incident_location" l="Incident Location" placeholder="Where did it occur?" />
            <Area k="incident_description" l="Description" rows={3} />
            <Area k="reason" l="Additional Notes" rows={2} />

            <H>Supporting Documents</H>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <FileInput k="document_1" l="Document 1" />
              <FileInput k="document_2" l="Document 2" />
              <FileInput k="document_3" l="Document 3" />
            </div>
          </>}

          {/* ── Claims-Tracker stage accordions + comment status (additive) ── */}
          {showStageAccordions && (
            <ClaimCreateStageAccordions ref={stageRef} claimType={form.claim_type} />
          )}

          {/* Submit */}
          <div className="flex items-center justify-end gap-3 border-t pt-4">
            <button type="button" onClick={() => navigate('/claims')}
              className="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">Cancel</button>
            <button type="submit" disabled={createClaim.isPending}
              className="px-6 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
              {createClaim.isPending ? 'Registering…' : 'Register Claim'}
            </button>
          </div>
        </>}
      </form>
    </div>
    </FormCtx.Provider>
  )
}
