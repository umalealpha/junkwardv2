// ─── Shared Types for Policy Creation Wizard ────────────────

export interface PolicyFormData {
  // Policy
  product_id: number | null
  plan_id: number | null
  agency_id: number | null
  agent_id: number | null
  premium_freq: string
  term_start_date: string
  expiry_date: string
  binder_date: string
  gfs_policy_no: string
  note: string

  // Customer
  entity_type: 'Individual' | 'Organisation'
  company_id: number | null
  first_name: string
  middle_name: string
  last_name: string
  email: string
  cellphone: string
  gender: string
  dob: string
  marital_status: string
  omang: string
  passport: string
  state: number | null
  city: number | null
  post_address: string

  // Source of Income
  source_of_income: string
  employment: Record<string, string>

  // KYC
  currently_insured: string
  current_insurer_detail?: string
  hear_about_alpha: string
  decline_proposal: boolean
  refused_policy: boolean
  cancel_policy: boolean
  business_note: string
  uw_app_status?: string

  // COMG specific
  firm_member: boolean
  books: boolean
  date: string
}

export interface RiskAddressForm {
  address_name: string
  physical_address: string
  risk_state: number | null
  risk_city: number | null
  const_type: string
  // Stored as raw strings to support mid-typing intermediate states like
  // "25." or "-24." that get coerced to NaN if cast to number. BE validator
  // accepts nullable|numeric which handles numeric strings fine.
  lat?: string
  lng?: string
  extension?: string
  occupation?: string
  year_built?: string
  area?: string
  structure_type?: string
  // Legacy underwriting classification fields — present on risk_address
  // table + accepted by PolicyCreateController::addRiskAddress validator
  // (lines 858-869). Previously absent from the V2 form so the columns
  // stayed NULL on every address, which affects premium calc + reinsurance
  // mapping downstream.
  town_class?: string
  risk_class?: string
  iso_rcv?: string
  distance_to_water?: string
  distance_to_fire?: string
  distance_to_hydrant?: string
  usage?: string
  occupancy_type?: string
  central_fire: boolean
  central_burglar: boolean
  gated_community: boolean
  automatic: boolean
}

export interface SavedRiskAddress extends RiskAddressForm {
  id: number
  state_name?: string
  city_name?: string
}

export interface MemberForm {
  relation: string
  first_name: string
  last_name: string
  dob: string
  gender: string
}

export interface BeneficiaryForm {
  relation: string
  omang: string
  passport: string
  first_name: string
  last_name: string
  dob: string
  gender: string
  payment_percent: number
}

export interface VehicleForm {
  plate_number: string
  chassis_number: string
  odometer: string
  purpose: string
  condition: string
  year: string
  make_id: number | null
  model_id: number | null
  engine_number: string
  seats: string
  cylinders: string
  cubic_capacity: string
  is_imported: boolean
  has_tracking: boolean
  is_private: boolean
  is_modified: boolean
  estimated_value: string
  claim_count: string
}

export interface VehicleImages {
  front: File | null
  back: File | null
  left: File | null
  right: File | null
  registration: File | null
  invoice: File | null
}

export interface DeviceForm {
  brand: string
  model_id: number | null
  model_name: string
  phone_value: string
}

export interface DeviceImages {
  front: File | null
  back: File | null
  left: File | null
  right: File | null
  top: File | null
  bottom: File | null
}

export interface MotorItemForm {
  item_name: string
  item_value: string
}

export interface BillingForm {
  billing_method: string
  billing_start_date: string
  bank_id: number | null
  branch_id: number | null
  account_number: string
  account_type: string
  billing_cell: string
}

export interface SubCoverageEntry {
  id: number                         // Actual policy_coverage_detail.id for deletion (from backend detail_id)
  detail_id?: number                 // Alias for id when loading from API (backend returns both id and detail_id)
  s_ScreenName: string
  s_CoverageGroupName: string
  s_SubCoverageMainName: string
  s_CoverageCode?: string           // Subcoverage code from tb_cvgpccoverages
  s_ParentCoverageCode?: string     // Parent coverage code (e.g., PUBLICLIABILITY)
  s_LimitTypeCode?: string          // Limit type (NUMBER, NOEDIT, etc.) from tb_cvgpc_limits
  dropdown_options?: string[]       // Options for DROPDOWN type from API
  radio_options?: any[]             // Options for RADIO type from API (id, name)
  coverage_value: string
  rate: string
  calculated_value: string
  limit_id?: string                 // For RADIO type selections
  // isCustom = user-created row via the "+" duplicate button. Description is
  // editable only for these; master rows show the DB name read-only.
  isCustom?: boolean
  // sub_coverage_id = Master coverage ID from tb_cvgpccoverages. Used to clone
  // the master when saving duplicates. Duplicates inherit this from the source row.
  sub_coverage_id?: number
  // Per-row free-text note (saved into policy_coverage_detail.coverage_value_string)
  // Used for coverages with two-field patterns (Public Liability, Electronic Equipment, etc.)
  // and a discount/surcharge triplet for Workers Compensation. Premium is hand-entered by the
  // operator; we DO NOT auto-apply the discount math.
  coverage_value_string?: string
  discount_surcharge?: string         // 'Discount' | 'Surcharge' | '' (None)
  discount_surcharge_type?: string    // 'Flat' | 'Percentage' | ''
  discount_surcharge_value?: string
  // Per-row WC composite fields (legacy stores these on the same
  // policy_coverage_detail row alongside the discount columns).
  ratefactor_type?: string
  ratefactor_value?: string
  ratefactor_value_check?: string
  ratefactor_AnnualWages?: string
  ratefactor_deposit_min_pre?: string
  deleted_at?: string | null        // Soft-delete timestamp; null = active row
  // Transient (never persisted on this shape): set by handleSave to mark
  // whether the operator actually added/edited this row THIS session vs left
  // a carried-forward value untouched. Drives the endorse pro-rata gate so an
  // untouched line is saved but not charged. See StepCoverages.handleSave.
  _changed?: boolean
}

export interface ExtensionEntry {
  extentions_id: number
  s_ScreenName: string
  s_CoverageCode?: string       // Extension code (e.g., 'PREVENTIONOFACCESS')
  type: 'Extention' | 'Perils' | 'FirstAmountPayable'
  extention_type: string       // NUMBER, DROPDOWN, RADIO, NOEDIT
  extention_limit_type: string | null  // DROPDOWN, RADIO, NUMBER, NOEDIT, EXCESS from backend
  rate: string
  limits?: { id: number; name: string }[]
  // User-filled values
  extention_coverage_value: string
  extention_text_value: string
  extention_limit_id: number | null
  extention_excess_min_value: string
  extention_excess_max_value: string
  extention_discount_surcharge: string
  extention_discount_surcharge_type: string
  extention_discount_surcharge_value: string
  extention_calculated_value: string
  // Business All Risks: Sum Insured field for RADIO type extensions
  extention_sum_insured?: string
  // UI-only flag for the simplified non-motor extensions form (Yes/No
  // radios per legacy graphiteBWV8 manage-coverages.blade.php). When false
  // the row is rendered with disabled inputs and the discount + premium
  // fields are zeroed on save so the extension contributes nothing to
  // the policy total. Default true for backward compatibility — existing
  // saved extensions stay "taken" until the operator opts out.
  is_taken?: boolean
}

export interface SpecifiedItemEntry {
  id?: number
  // FK into specified_coverage_items master. Every row is a master pick;
  // there is no free-text path (legacy graphiteBWV8 ManageCoverages only
  // ever wrote master picks).
  specified_coverage_id: number | null
  motor_id?: number | null
  name: string
  sum_insured: string
  rate: string
  calculated_value: string
  deleted_at?: string | null
}

export interface ExcessEntry {
  excesses: string
  min_percent: string
  min_amt: string
  discount_surcharge?: string       // 'Discount' | 'Surcharge'
  discount_surcharge_type?: string  // 'Flat' | 'Percentage'
  discount_surcharge_value?: string
  premium?: string                   // Premium value for excess
}

export interface CoverageForm {
  coverage_id: number | null
  coverage_name: string
  risk_address_id: number | null
  coverage_value?: string  // Optional for Fidelity Guarantee (stored in policy_coverages_data)
  rate: string
  calculated_value?: string  // Optional for Fidelity Guarantee (stored in policy_coverages_data)
  discount_type: string
  discount_value: string
  notes: string
  subcoverages?: SubCoverageEntry[]
  extensions?: ExtensionEntry[]
  specified_items?: SpecifiedItemEntry[]
  excesses?: ExcessEntry[]
  // Workers Compensation specific fields
  ratefactor_type?: string
  ratefactor_value?: string
  ratefactor_value_check?: string
  ratefactor_AnnualWages?: string
  ratefactor_deposit_min_pre?: string
  // Public Liability retroactive date
  retroactive_date?: string
  // Fidelity Guarantee fields (stored in policy_coverages_data table)
  fidelity_data?: FidelityGuaranteeEntry[]
  // Theft General Questions (stored in theft_questions table)
  theft_questions?: TheftGeneralQuestions
  // Goods In Transit field (stored in policy_coverages table)
  property_business_being?: string
  // Office Content: Burglar Alarm Warranty text (stored in policy_coverages table)
  burglar_alarm_warranty?: string
  // Stated Benefits for Employees Liability / Workers Compensation (stored in policy_coverages table)
  stated_benefits?: string
  // Money coverage: Memoranda and Warranties (stored in policy_coverage_notes table)
  memoranda_warranty?: string
  // Money coverage: Cash Carrying Warranty (stored in policy_coverage_notes table)
  cash_warranty?: string
}

export interface FidelityGuaranteeEntry {
  id?: number
  cover_type: string
  cover_area: string
  name_and_position: string
  designation: string
  length_of_service: string
  amount_to_be_guaranteed: string
  premium: string
  deleted_at?: string | null
}

export interface TheftGeneralQuestions {
  physical_protection_implemented?: string
  premises_alarmed?: string
  subscribe_armed_security?: string
  security_company?: string
  maintenance_contract?: string
  alarmed_installed_date?: string
  opening_closing_signals?: string
}


export interface KycDocuments {
  driving_license: File | null
  omang_doc: File | null
  proof_of_residence: File | null
  proof_of_income: File | null
  passport_doc: File | null
}

export interface ProductDetails {
  id: number
  name: string
  has_vehicle: boolean
  has_member: boolean
  has_device: boolean
  has_risk_address: boolean
  kyc_customer: boolean
  is_motor_items: boolean
}

export interface PolicyResult {
  policy_id: number
  policy_number: string
  term_id: number
  action_id: number
  customer_id: number
  status: string
}

// ─── Step Configuration ─────────────────────────────────────

export interface WizardStep {
  key: string
  label: string
  show: boolean
}

/**
 * DOM/COM policy creation wizard steps.
 * MIS policies are NOT created from Graphite v2 — they come from Start/LiveQuote.
 * This wizard is exclusively for DOM/COM (products 7, 8, 16, 17, 18, 19).
 */
export function getWizardSteps(_product: ProductDetails | null): WizardStep[] {
  return [
    { key: 'policy', label: 'Policy & Customer Details', show: true },
    { key: 'risk', label: 'Risk Addresses', show: true },
    { key: 'coverages', label: 'Coverages', show: true },
    { key: 'review', label: 'Review & Submit', show: true },
  ]
}

// ─── Initial/Default Values ─────────────────────────────────

export const INITIAL_FORM: PolicyFormData = {
  product_id: null, plan_id: null, agency_id: null, agent_id: null,
  premium_freq: '3',
  term_start_date: new Date().toISOString().split('T')[0],
  expiry_date: (() => {
    const d = new Date(); d.setFullYear(d.getFullYear() + 1); d.setDate(d.getDate() - 1);
    return d.toISOString().split('T')[0];
  })(),
  binder_date: '', gfs_policy_no: '', note: '',
  entity_type: 'Individual', company_id: null,
  first_name: '', middle_name: '', last_name: '', email: '', cellphone: '',
  gender: '', dob: '', marital_status: '', omang: '', passport: '',
  state: null, city: null, post_address: '',
  source_of_income: '', employment: {},
  currently_insured: '', hear_about_alpha: '',
  decline_proposal: false, refused_policy: false, cancel_policy: false,
  business_note: '', firm_member: false, books: false, date: '',
}

export const INITIAL_RISK: RiskAddressForm = {
  address_name: '', physical_address: '', risk_state: null, risk_city: null,
  const_type: '', extension: '', occupation: '', year_built: '', area: '',
  structure_type: '',
  town_class: '', risk_class: '', iso_rcv: '',
  distance_to_water: '', distance_to_fire: '', distance_to_hydrant: '',
  usage: '', occupancy_type: '',
  central_fire: false, central_burglar: false,
  gated_community: false, automatic: false,
}

export const INITIAL_MEMBER: MemberForm = {
  relation: '', first_name: '', last_name: '', dob: '', gender: '',
}

export const INITIAL_BENEFICIARY: BeneficiaryForm = {
  relation: '', omang: '', passport: '', first_name: '', last_name: '',
  dob: '', gender: '', payment_percent: 0,
}

export const INITIAL_VEHICLE: VehicleForm = {
  plate_number: '', chassis_number: '', odometer: '', purpose: '', condition: '',
  year: '', make_id: null, model_id: null, engine_number: '', seats: '',
  cylinders: '', cubic_capacity: '', is_imported: false, has_tracking: false,
  is_private: true, is_modified: false, estimated_value: '', claim_count: '0',
}

export const INITIAL_DEVICE: DeviceForm = {
  brand: '', model_id: null, model_name: '', phone_value: '',
}

export const INITIAL_BILLING: BillingForm = {
  billing_method: '', billing_start_date: '', bank_id: null, branch_id: null,
  account_number: '', account_type: '', billing_cell: '',
}

export const INITIAL_COVERAGE: CoverageForm = {
  coverage_id: null, coverage_name: '', risk_address_id: null,
  coverage_value: '', rate: '', calculated_value: '',
  discount_type: '', discount_value: '', notes: '',
  ratefactor_type: '', ratefactor_value: '', ratefactor_value_check: '',
  ratefactor_AnnualWages: '', ratefactor_deposit_min_pre: '',
  retroactive_date: '',
  burglar_alarm_warranty: '',
  stated_benefits: '',
  memoranda_warranty: '',
  cash_warranty: '',
}

// Min date for policy start = July 1 of previous year
export const MIN_START_DATE = `${new Date().getFullYear() - 1}-07-01`
