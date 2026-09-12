import type { PolicyFormData, RiskAddressForm, MemberForm, BeneficiaryForm, VehicleForm, DeviceForm, BillingForm } from './types'

const pick = <T,>(arr: T[]): T => arr[Math.floor(Math.random() * arr.length)]
const rand = (min: number, max: number) => Math.floor(Math.random() * (max - min + 1)) + min

const FIRST_NAMES = ['Kelebogile', 'Thato', 'Mpho', 'Kagiso', 'Tshepiso', 'Boitumelo', 'Lethabo', 'Oratile', 'Keabetswe', 'Neo', 'Gorata', 'Tsholofelo', 'Phenyo', 'Refilwe', 'Amantle', 'Bakang', 'Kabelo', 'Lesego', 'Mothusi', 'Kgomotso']
const LAST_NAMES = ['Mosweu', 'Modise', 'Kgosidintsi', 'Mothibi', 'Seretse', 'Magang', 'Tsheko', 'Molapo', 'Gabotlotlege', 'Mmolawa', 'Nkoane', 'Dikgomo', 'Ramotswe', 'Pilane', 'Kebafetse', 'Mokgosi', 'Letsholo', 'Rapetswa', 'Moesi', 'Tlou']
const EMPLOYERS = ['Alpha Direct Insurance', 'Botswana Power Corp', 'BTC', 'First National Bank', 'Debswana', 'Barloworld', 'Stanbic Bank', 'Choppies', 'Mascom', 'Orange Botswana', 'Water Utilities Corp', 'BotswanaPost', 'Letshego', 'Air Botswana']
const STREETS = ['Main Mall', 'Nelson Mandela Dr', 'Independence Ave', 'Queens Rd', 'Nyerere Dr', 'Segoditshane Way', 'Kubu St', 'Limpopo Dr', 'Western Bypass', 'Airport Rd', 'Khama Crescent', 'Kgale View', 'Tlokweng Rd']
const AREAS = ['Gaborone', 'Francistown', 'Maun', 'Kasane', 'Palapye', 'Serowe', 'Lobatse', 'Selebi-Phikwe', 'Jwaneng', 'Molepolole', 'Kanye', 'Mahalapye', 'Letlhakane']
const PRODUCTS = [7, 8, 16,17,18,20,22,23,24]

function randomDob(): string {
  const y = rand(1960, 2000)
  const m = String(rand(1, 12)).padStart(2, '0')
  const d = String(rand(1, 28)).padStart(2, '0')
  return `${y}-${m}-${d}`
}

function randomOmang(dob: string): string {
  return dob.slice(2, 4) + dob.slice(5, 7) + dob.slice(8, 10) + String(rand(1000, 9999))
}

export function generateTestPolicy(): PolicyFormData {
  const fn = pick(FIRST_NAMES)
  const ln = pick(LAST_NAMES)
  const dob = randomDob()

  return {
    product_id: pick(PRODUCTS),
    plan_id: null,
    agency_id: rand(1, 5),
    agent_id: null,
    premium_freq: pick(['1', '3', '5']),
    term_start_date: new Date().toISOString().split('T')[0],
    expiry_date: new Date(Date.now() + 365 * 86400000).toISOString().split('T')[0],
    binder_date: new Date().toISOString().split('T')[0],
    gfs_policy_no: `GFS-${rand(1000, 9999)}`,
    note: pick(['', '', 'New business enquiry', 'Referred by existing client', 'Walk-in customer']),
    entity_type: 'Individual',
    company_id: null,
    first_name: fn,
    middle_name: pick(['', '', '', 'B.', 'K.', 'M.', 'T.']),
    last_name: ln,
    email: `${fn.toLowerCase()}.${ln.toLowerCase()}${rand(1, 99)}@test.alphadirect.co.bw`,
    cellphone: '7' + String(rand(1000000, 9999999)),
    gender: pick(['Male', 'Female']),
    dob,
    marital_status: pick(['Single', 'Married', 'Divorced', 'Widowed']),
    omang: randomOmang(dob),
    passport: '',
    state: rand(1, 10),
    city: rand(1, 5),
    post_address: `Plot ${rand(100, 9999)}, ${pick(STREETS)}, ${pick(AREAS)}`,
    source_of_income: pick(['employment', 'bussiness', 'pensioner_retired', 'investments']),
    employment: {
      'where_are_you_employed_?': pick(EMPLOYERS),
      'what_is_your_monthly_salary_?': String(rand(5000, 50000)),
    },
    currently_insured: pick(['Yes', 'No']),
    hear_about_alpha: pick(['Referral', 'Social Media', 'Walk-in', 'Agent', 'Online']),
    decline_proposal: false,
    refused_policy: false,
    cancel_policy: false,
    business_note: '',
    firm_member: Math.random() > 0.5,
    books: Math.random() > 0.5,
    date: new Date().toISOString().split('T')[0],
  }
}

export function generateTestRiskAddress(): RiskAddressForm {
  // Gaborone city centre: -24.6282, 25.9231. Jitter ±0.05° for variation.
  const latJitter = (Math.random() - 0.5) * 0.1
  const lngJitter = (Math.random() - 0.5) * 0.1
  return {
    address_name: `${pick(AREAS)} Office ${rand(1, 50)}`,
    physical_address: `Plot ${rand(100, 9999)}, ${pick(STREETS)}, ${pick(AREAS)}`,
    risk_state: rand(1, 10),
    risk_city: rand(1, 5),
    const_type: pick(['Brick', 'Concrete', 'Steel', 'Mixed', 'Wood']),
    lat: (-24.6282 + latJitter).toFixed(4),
    lng: (25.9231 + lngJitter).toFixed(4),
    extension: `Ext ${rand(1, 30)}`,
    occupation: pick(['Office', 'Warehouse', 'Retail', 'Residential', 'Industrial']),
    year_built: String(rand(1980, 2024)),
    area: String(rand(50, 5000)),
    structure_type: pick(['Single Story', 'Multi Story', 'Warehouse', 'Office Block']),
    central_fire: Math.random() > 0.3,
    central_burglar: Math.random() > 0.3,
    gated_community: Math.random() > 0.5,
    automatic: Math.random() > 0.5,
  }
}

export function generateTestMember(): MemberForm {
  return {
    relation: pick(['Spouse', 'Child', 'Parent', 'Sibling']),
    first_name: pick(FIRST_NAMES),
    last_name: pick(LAST_NAMES),
    dob: randomDob(),
    gender: pick(['Male', 'Female']),
  }
}

export function generateTestBeneficiary(): BeneficiaryForm {
  const dob = randomDob()
  return {
    relation: pick(['Spouse', 'Child', 'Parent', 'Sibling', 'Friend']),
    omang: randomOmang(dob),
    passport: '',
    first_name: pick(FIRST_NAMES),
    last_name: pick(LAST_NAMES),
    dob,
    gender: pick(['Male', 'Female']),
    payment_percent: pick([100, 50, 25, 33]),
  }
}

export function generateTestVehicle(): VehicleForm {
  const plates = ['B', 'BW', 'BAG', 'BFN', 'BMN']
  return {
    plate_number: `${pick(plates)} ${rand(100, 999)} ${pick(['AAA', 'ABB', 'ACC', 'BCC', 'DDD'])}`,
    chassis_number: `WBA${rand(100000, 999999)}${rand(10000, 99999)}`,
    odometer: String(rand(1000, 200000)),
    purpose: pick(['Private', 'Commercial']),
    condition: pick(['Good', 'Very Good']),
    year: String(rand(2010, 2025)),
    make_id: rand(1, 20),
    model_id: null,
    engine_number: `ENG${rand(10000, 99999)}`,
    seats: String(rand(2, 8)),
    cylinders: String(pick([3, 4, 6, 8])),
    cubic_capacity: String(pick([1200, 1400, 1600, 1800, 2000, 2500, 3000])),
    is_imported: Math.random() > 0.7,
    has_tracking: Math.random() > 0.5,
    is_private: true,
    is_modified: false,
    estimated_value: String(rand(50000, 500000)),
    claim_count: String(rand(0, 3)),
  }
}

export function generateTestDevice(): DeviceForm {
  return {
    brand: pick(['Samsung', 'Apple', 'Huawei', 'Xiaomi', 'Nokia']),
    model_id: null,
    model_name: pick(['Galaxy S24', 'iPhone 15', 'P60 Pro', 'Redmi Note 13', 'G42']),
    phone_value: String(rand(2000, 25000)),
  }
}

export function generateTestBilling(): BillingForm {
  return {
    billing_method: pick(['DPO', 'RealPay', 'Cash']),
    billing_start_date: new Date().toISOString().split('T')[0],
    bank_id: rand(1, 5),
    branch_id: null,
    account_number: String(rand(10000000, 99999999)),
    account_type: pick(['Savings', 'Current']),
    billing_cell: '7' + String(rand(1000000, 9999999)),
  }
}
