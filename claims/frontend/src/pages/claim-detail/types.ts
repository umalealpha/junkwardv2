export interface ClaimData {
  id: number
  claim_number: string
  claim_type: string
  status: string
  priority: string
  date_of_loss: string
  location: string
  description: string
  reported_by: string
  assigned_to: string
  created_at: string
  days_open: number
  reserve_total: number
  paid_total: number
  balance: number
  policy: {
    policy_number: string
    product: string
    premium: number
    customer_name: string
    agent_name: string
  }
  dynamic_fields: Record<string, string>
  status_history: Array<{
    status: string
    date: string
    user: string
    notes?: string
  }>
}

export interface Document {
  id: number
  filename: string
  type: string
  size: number
  uploaded_by: string
  created_at: string
  url: string
}

export interface ReserveEntry {
  id: number
  date: string
  transaction_type: string
  coverage: string
  amount: number
  payee: string
  invoice_number: string
  memo: string
  status: string
}

export interface CoverageBreakdown {
  coverage_name: string
  reserve: number
  paid: number
  balance: number
  salvage_reserve: number
  salvage_paid: number
}

export interface Assessment {
  assessor_name: string
  assigned_date: string
  valuation_amount: number
  notes: string
  report_url?: string
  quotations_url?: string
}

export interface ThirdParty {
  id: number
  name: string
  phone: string
  email: string
  id_number: string
  vehicle_reg: string
  insurer: string
  policy_number: string
  bank_name: string
  bank_account: string
  bank_branch: string
}

export interface Quote {
  id: number
  supplier: string
  amount: number
  status: string
  file_url?: string
  notes: string
  created_at: string
}

export interface TimelineEvent {
  id: number
  type: string
  title: string
  description: string
  user: string
  timestamp: string
}

export const statusColors: Record<string, string> = {
  'New': 'bg-blue-100 text-blue-700',
  'Pending Assessment': 'bg-yellow-100 text-yellow-700',
  'Under Review': 'bg-orange-100 text-orange-700',
  'Approved': 'bg-green-100 text-green-700',
  'Rejected': 'bg-red-100 text-red-700',
  'Settled': 'bg-emerald-100 text-emerald-700',
  'Closed': 'bg-gray-100 text-gray-600',
}

export const priorityColors: Record<string, string> = {
  'Low': 'bg-gray-100 text-gray-600',
  'Medium': 'bg-blue-100 text-blue-700',
  'High': 'bg-orange-100 text-orange-700',
  'Critical': 'bg-red-100 text-red-700',
}

export function formatCurrency(amount: number): string {
  return 'P ' + amount.toLocaleString('en-BW', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
