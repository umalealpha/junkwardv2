import { useState, useEffect, useCallback, Fragment } from 'react'
import { useParams, useNavigate, useSearchParams, Link } from 'react-router-dom'
import {
  fetchSpecialistCoverageByType,
  createSpecialistCoverage,
  updateSpecialistCoverage,
  fetchPolicyRiskAddresses,
} from '../../api/policies'
import apiClient from '../../api/client'
import { toWordsShort } from '../../utils/format'
import { reportErrorToTeam } from '../../utils/reportError'

// ─── Field & Section config types ────────────────────────────────────────────

type FieldType = 'text' | 'number' | 'date' | 'textarea' | 'checkbox' | 'select' | 'json-array' | 'checkbox-list' | 'heading' | 'help-text' | 'date-range' | 'field-group'

interface JsonColumnDef {
  key: string
  label: string
  type?: 'text' | 'number' | 'select'
  options?: { value: string; label: string }[]
  readonly?: boolean
}

interface FieldConfig {
  key: string
  label: string
  type: FieldType
  options?: { value: string; label: string }[]
  cols?: JsonColumnDef[]
  colSpan?: 1 | 2 | 3 | 4
  placeholder?: string
  /** Seed rows for json-array fields when creating a new record (ignored on edit). */
  defaultRows?: Record<string, string>[]
  /** Lock a json-array to its seeded rows: hides "Add Row" and the per-row remove button. */
  locked?: boolean
  /** Custom label for the json-array "Add Row" button (defaults to "Add Row"). */
  addButtonLabel?: string
  /** Number of leading seeded rows whose column-level `readonly` is enforced — rows added
   *  beyond this count (via Add Row/Add More) are fully editable regardless of column config,
   *  so a fixed catalog (e.g. standard Bond Types) can coexist with free-form "Other" rows. */
  lockedRowCount?: number
  /** Renders a full-width banner row before the row at `beforeIndex` — used to group a
   *  json-array's seeded rows into labelled sections (e.g. "CONTRACT BONDS" / "COMMERCIAL BONDS"). */
  groupBreaks?: { beforeIndex: number; label: string }[]
  /** Render the input as read-only (used for fields auto-filled from the parent policy). */
  readonly?: boolean
  /** Overrides the default "(auto-filled from policy)" hint next to a read-only label —
   *  e.g. a total computed from the schedule's own rows rather than copied off the policy. */
  readonlyNote?: string
  /** Render the label inline (left ~33% of the row) and the input on the right ~67% — used to mirror the v2 blade's table-style sections (e.g. Per Conveyance Limit). */
  inlineLabel?: boolean
  /** Optional muted description shown under a 'date-range' field's heading. */
  description?: string
  /** For 'date-range': scalar keys bound to the From / To date inputs. */
  fromKey?: string
  toKey?: string
  /** For 'field-group': a left section label with these sub-fields laid out in a 2-column grid on the right. */
  subFields?: FieldConfig[]
}

interface SectionConfig {
  title: string
  /** Optional muted note rendered between the section title bar and the fields. */
  description?: string
  /** Override the default 3-column section grid (e.g. 4 to render Earthquake/Storm rows as Covered | Limit | Deductible | Premium on a single line). */
  gridCols?: 2 | 3 | 4
  /** Render this section AFTER the Policy Wording upload block instead of with the rest of the sections. */
  afterPolicyWording?: boolean
  /** Bonds only: append the EXCO collateral-confirmation banner + button to this section. */
  collateralGate?: boolean
  /** Bonds only: render the collateral DOCUMENT list + upload/approve panel in this section.
   *  Anyone may upload; only holders of `bonds-approve` (EXCO) may approve or reject,
   *  and Issue is blocked until one document is approved — backend BondsIssuanceGate. */
  collateralDocs?: boolean
  fields: FieldConfig[]
}

interface TypeConfig {
  label: string
  slug: string
  sections: SectionConfig[]
}

/** One row of bonds_collateral_documents, as returned by the API. */
interface CollateralDoc {
  id: number
  action_id: number | null
  document_type: string | null
  notes: string | null
  file_name: string
  file_size: number | null
  status: 'PENDING' | 'APPROVED' | 'REJECTED'
  uploaded_by: number | null
  uploaded_by_name: string | null
  uploaded_at: string | null
  approved_by_name: string | null
  approved_at: string | null
  rejection_reason: string | null
}

// ─── Number formatting helpers (comma-separated display) ──────────────────────

/** Convert a raw numeric string to comma-separated display. Empty/invalid → ''. */
function formatNumberInput(v: string | number | null | undefined): string {
  if (v === null || v === undefined || v === '') return ''
  const str = String(v).replace(/,/g, '')
  // Allow trailing decimal during typing: "1234." or "1234.5"
  const match = str.match(/^(-?)(\d+)(\.\d*)?$/)
  if (!match) return str // invalid input — show as-is so user can correct
  const [, sign, intPart, decPart = ''] = match
  return sign + Number(intPart).toLocaleString('en-US') + decPart
}

/** Strip commas from a formatted number for storage/calculation. */
function unformatNumber(v: string): string {
  return String(v).replace(/,/g, '')
}

// ─── Form configurations for each specialist type ─────────────────────────────

const YES_NO = [
  { value: '1', label: 'Yes' },
  { value: '0', label: 'No' },
]

const NEW_ALTERED = [
  { value: 'New Business', label: 'New Business' },
  { value: 'Altered', label: 'Altered' },
  { value: 'Renewal', label: 'Renewal' },
]

const POLICY_PERIOD = [
  { value: '12', label: 'Allow up to 12 months max' },
  { value: '24', label: 'Allow up to 24 months max' },
  { value: '36', label: 'Allow up to 36 months max' },
]

const FORM_CONFIGS: Record<string, TypeConfig> = {
  ear: {
    label: 'EAR Coverage',
    slug: 'ear',
    sections: [
      {
        title: 'Erection All Risks – Policy Schedule',
        fields: [
          { key: 'name_of_insured', label: 'Name of Insured', type: 'text', colSpan: 3 },
          { key: 'site_of_erection', label: 'Site of Erection', type: 'text', colSpan: 3 },
          { key: 'project_name', label: 'Project Name', type: 'text', colSpan: 2 },
          { key: 'policy_period_months', label: 'Policy Period', type: 'select', options: POLICY_PERIOD },
          { key: 'is_renewable', label: 'Renewable Policy', type: 'select', options: [
            { value: 'Yes', label: 'Yes - Renewable Policy' },
            { value: 'No', label: 'No' },
          ]},
          { key: 'is_project_specific', label: 'Project Specific Policy', type: 'select', options: [
            { value: 'Yes', label: 'Yes - Project Specific Policy' },
            { value: 'No', label: 'No' },
          ]},
          { key: 'maintenance_period_months', label: 'Maintenance Period (Months after expiry)', type: 'number' },
        ],
      },
      {
        title: 'Section 1 – Material Damage',
        fields: [
          {
            key: 'section1_items',
            label: 'Section 1 Items',
            type: 'json-array',
            colSpan: 3,
            cols: [
              { key: 'item_type', label: 'Insured Items', type: 'select', options: [
                { value: 'Erection Work', label: 'Erection Work' },
                { value: 'Items to be erected', label: 'Items to be erected (require install and/or testing)' },
                { value: 'Freight', label: 'Freight' },
                { value: 'Customs Duties and Dues', label: 'Customs Duties and Dues' },
                { value: 'Cost of Erection', label: 'Cost of Erection' },
                { value: 'Civil Engineering Work', label: 'Civil Engineering Work' },
                { value: 'Clearance of Debris', label: 'Clearance of Debris' },
                { value: 'Other', label: 'Other (Specify in notes)' },
              ]},
              { key: 'description', label: 'Additional description', type: 'text' },
              { key: 'sum_insured', label: 'Sum Insured', type: 'number' },
              { key: 'deductible', label: 'Deductible', type: 'text' },
              { key: 'rate', label: 'Rate %', type: 'number' },
              { key: 'premium', label: 'Premium', type: 'number' },
            ],
          },
          { key: 'section1_total_sum_insured', label: 'Total Sum Insured under Section 1', type: 'number' },
          { key: 'section1_total_premium', label: 'Total Premium under Section 1', type: 'number' },
        ],
      },
      {
        title: 'Risk Coverage',
        gridCols: 4,
        fields: [
          { key: 'risk_earthquake_covered', label: 'Earthquake, volcanism, tsunami – Covered?', type: 'select', options: YES_NO },
          { key: 'risk_earthquake_limit_indemnity', label: 'Earthquake Limit of Indemnity', type: 'number' },
          { key: 'risk_earthquake_deductible', label: 'Earthquake Deductible', type: 'text' },
          { key: 'risk_earthquake_premium', label: 'Earthquake Premium', type: 'number' },
          { key: 'risk_storm_covered', label: 'Storm, cyclone, flood, inundation, landslide – Covered?', type: 'select', options: YES_NO },
          { key: 'risk_storm_limit_indemnity', label: 'Storm Limit of Indemnity', type: 'number' },
          { key: 'risk_storm_deductible', label: 'Storm Deductible', type: 'text' },
          { key: 'risk_storm_premium', label: 'Storm Premium', type: 'number' },
        ],
      },
      {
        title: 'Section 3 – Third Party Liability',
        fields: [
          {
            key: 'section3_items',
            label: 'Section 3 Items',
            type: 'json-array',
            colSpan: 3,
            cols: [
              { key: 'item_type', label: 'Insured Items', type: 'select', options: [
                { value: 'Bodily Injury', label: 'Bodily Injury' },
                { value: 'Each and every person', label: '– Each and every person' },
                { value: 'total', label: '– total' },
                { value: 'Property Damage', label: 'Property Damage' },
                { value: 'Other', label: 'Other (Specify below)' },
              ]},
              { key: 'description', label: 'Additional description (optional)', type: 'text' },
              { key: 'limit_of_indemnity', label: 'Limits of Indemnity', type: 'number' },
              { key: 'deductible', label: 'Deductibles', type: 'text' },
              { key: 'rate', label: 'Rate %', type: 'number' },
              { key: 'premium', label: 'Premium', type: 'number' },
            ],
          },
          { key: 'section3_total_limit', label: 'Total Limit of Indemnity under Section 3', type: 'number' },
          { key: 'section3_total_premium', label: 'Total Premium under Section 3', type: 'number' },
        ],
      },
      {
        title: 'Period of Insurance',
        fields: [
          { key: 'period_from', label: 'From', type: 'date' },
          { key: 'period_to', label: 'To (including weeks of testing)', type: 'date' },
          { key: 'weeks_of_testing', label: 'Number of weeks of testing', type: 'number' },
        ],
      },
      {
        title: 'Endorsements / Extensions',
        fields: [
          { key: 'endorsement_1', label: 'Endorsement 1', type: 'textarea', colSpan: 3 },
          { key: 'endorsement_2', label: 'Endorsement 2', type: 'textarea', colSpan: 3 },
          { key: 'endorsement_3', label: 'Endorsement 3', type: 'textarea', colSpan: 3 },
          { key: 'endorsement_4', label: 'Endorsement 4', type: 'textarea', colSpan: 3 },
        ],
      },
      {
        title: 'Total Premium',
        fields: [
          { key: 'total_premium', label: 'Total Premium (inclusive of extra premiums for endorsements)', type: 'number' },
        ],
      },
      {
        title: 'Additional Notes',
        fields: [
          { key: 'additional_notes', label: 'Additional Notes', type: 'textarea', colSpan: 3 },
        ],
      },
      {
        title: 'Execution Details',
        fields: [
          { key: 'executed_at', label: 'Executed At', type: 'text' },
          { key: 'execution_date', label: 'Date', type: 'date' },
          { key: 'signature', label: 'Signature', type: 'text' },
        ],
      },
    ],
  },

  car: {
    label: 'CAR Coverage',
    slug: 'car',
    sections: [
      {
        title: 'Contractors All Risk – Policy Schedule',
        fields: [
          { key: 'branch', label: 'Branch', type: 'text' },
          { key: 'currency', label: 'Currency', type: 'select', options: [{ value: 'BWP', label: 'BWP' }] },
          { key: 'declaration_no', label: 'Declaration No.', type: 'text' },
          { key: 'today_date', label: "Today's Date", type: 'date' },
          { key: 'new_altered', label: 'New / Altered', type: 'select', options: [
            { value: 'New', label: 'New' },
            { value: 'Altered', label: 'Altered' },
          ]},
          { key: 'insured_name', label: 'Name of Insured', type: 'text', colSpan: 2 },
          { key: 'insured_street', label: 'Insured Street', type: 'text', colSpan: 2 },
          { key: 'insured_postal_code', label: 'Insured Postal Code & City', type: 'text' },
          { key: 'title_of_contract', label: 'Title of Contract', type: 'text', colSpan: 2 },
          { key: 'project_name', label: 'Project Name', type: 'text', colSpan: 2 },
          { key: 'risk_street', label: 'Risk Street', type: 'text', colSpan: 2 },
          { key: 'risk_postal_code', label: 'Risk Postal Code & City', type: 'text' },
          { key: 'city_town_village', label: 'City / Town / Village of Risk', type: 'text', colSpan: 2 },
          { key: 'policy_inception_date', label: 'Policy Inception Date', type: 'date' },
          { key: 'policy_expiry_date', label: 'Policy Expiry Date', type: 'date' },
          { key: 'policy_period_months', label: 'Policy Period', type: 'select', options: POLICY_PERIOD },
          { key: 'is_renewable', label: 'Renewable Policy', type: 'select', options: [
            { value: 'Yes', label: 'Yes - Renewable Policy' },
            { value: 'No', label: 'No' },
          ]},
          { key: 'is_project_specific', label: 'Project Specific Policy', type: 'select', options: [
            { value: 'Yes', label: 'Yes - Project Specific Policy' },
            { value: 'No', label: 'No' },
          ]},
          { key: 'maintenance_period_months', label: 'Maintenance Period (Months after expiry)', type: 'number' },
        ],
      },
      {
        title: 'Section 1 – Material Damage',
        fields: [
          {
            key: 'section1_items',
            label: 'Section 1 Items',
            type: 'json-array',
            colSpan: 3,
            cols: [
              { key: 'item_type', label: 'Item Type', type: 'select', options: [
                { value: 'Contract works - Contract price', label: 'Contract works – Contract price (permanent and temporary works, incl. materials)' },
                { value: 'Contract works - Materials or items supplied by Principal', label: 'Contract works – Materials or items supplied by the Principal(s)' },
                { value: 'Construction plant and equipment', label: 'Construction plant and equipment' },
                { value: 'Construction machinery according to attached list', label: 'Construction machinery according to attached list' },
                { value: 'Clearance of debris', label: 'Clearance of debris' },
                { value: 'Other', label: 'Other (Specify in description)' },
              ]},
              { key: 'description', label: 'Additional description', type: 'text' },
              { key: 'sum_insured', label: 'Sum Insured', type: 'number' },
              { key: 'deductible', label: 'Deductible', type: 'text' },
              { key: 'rate', label: 'Rate %', type: 'number' },
              { key: 'premium', label: 'Premium', type: 'number' },
            ],
          },
          { key: 'section1_total_sum_insured', label: 'Total Sum Insured under Section 1', type: 'number' },
          { key: 'section1_total_premium', label: 'Total Premium under Section 1', type: 'number' },
        ],
      },
      {
        title: 'List of Plant (Adds up to Summary Sum Insured and Premium above)',
        fields: [
          {
            key: 'plant_list_items',
            label: 'Plant List (adds up to summary above)',
            type: 'json-array',
            colSpan: 3,
            cols: [
              { key: '_item_no', label: 'No.', readonly: true },
              { key: 'description', label: 'Description of Plant', type: 'text' },
              { key: 'sum_insured', label: 'Sum Insured', type: 'number' },
              { key: 'premium', label: 'Premium', type: 'number' },
            ],
          },
          { key: 'plant_list_total_sum_insured', label: 'Total Sum Insured (from rows above)', type: 'number', readonly: true },
          { key: 'plant_list_total_premium', label: 'Total Premium (from rows above)', type: 'number', readonly: true },
        ],
      },
      {
        title: 'Risk',
        fields: [
          { key: 'section1_risk_earthquake', label: 'Earthquake, volcanism, tsunami – Covered?', type: 'select', options: YES_NO },
          { key: 'section1_earthquake_limit_indemnity', label: 'Earthquake Limit of Indemnity', type: 'number' },
          { key: 'section1_earthquake_deductible', label: 'Earthquake Deductible', type: 'text' },
          { key: 'section1_earthquake_premium', label: 'Earthquake Premium', type: 'number' },
          { key: 'section1_risk_storm', label: 'Storm, cyclone, flood, inundation, landslide – Covered?', type: 'select', options: YES_NO },
          { key: 'section1_storm_limit_indemnity', label: 'Storm Limit of Indemnity', type: 'number' },
          { key: 'section1_storm_deductible', label: 'Storm Deductible', type: 'text' },
          { key: 'section1_storm_premium', label: 'Storm Premium', type: 'number' },
        ],
      },
      {
        title: 'Section 2 – Third Party Liability',
        fields: [
          {
            key: 'section2_items',
            label: 'Section 2 Items',
            type: 'json-array',
            colSpan: 3,
            cols: [
              { key: 'item_type', label: 'Item Type', type: 'select', options: [
                { value: 'Bodily Injury', label: 'Bodily Injury' },
                { value: 'Bodily Injury - any one person', label: 'Bodily Injury – any one person' },
                { value: 'Bodily Injury - total', label: 'Bodily Injury – total' },
                { value: 'Property Damage', label: 'Property Damage' },
                { value: 'Other', label: 'Other (Specify below)' },
              ]},
              { key: 'description', label: 'Additional description (optional)', type: 'text' },
              { key: 'limit_of_indemnity', label: 'Limit of Indemnity', type: 'number' },
              { key: 'deductible', label: 'Deductible', type: 'text' },
              { key: 'rate', label: 'Rate %', type: 'number' },
              { key: 'premium', label: 'Premium', type: 'number' },
            ],
          },
          { key: 'section2_total_limit', label: 'Total Limit of Indemnity under Section 2', type: 'number' },
          { key: 'section2_total_premium', label: 'Total Premium under Section 2', type: 'number' },
        ],
      },
      {
        title: 'Section 3 – Principal\'s Loss of Profits',
        gridCols: 4,
        fields: [
          { key: '_heading_s3_gross_profit', label: 'Gross Profit', type: 'heading', colSpan: 4 },
          { key: 'section3_gross_profit_insured_interest', label: 'Insured interest', type: 'text', placeholder: 'Gross profit' },
          { key: 'section3_gross_profit_annual_sum_insured', label: 'Annual sum insured', type: 'number' },
          { key: 'section3_gross_profit_rate', label: 'Rate %', type: 'number' },
          { key: 'section3_gross_profit_premium', label: 'Premium', type: 'number' },

          { key: '_heading_s3_increased_cost', label: 'Increased Cost of Working', type: 'heading', colSpan: 4 },
          { key: 'section3_increased_cost_insured_interest', label: 'Insured interest', type: 'text', placeholder: 'Increased cost of working' },
          { key: 'section3_increased_cost_sum_insured', label: 'Sum insured for maximum indemnity period', type: 'number' },
          { key: 'section3_increased_cost_rate', label: 'Rate %', type: 'number' },
          { key: 'section3_increased_cost_premium', label: 'Premium', type: 'number' },

          { key: '_heading_s3_period', label: 'Period of Insurance & Indemnity', type: 'heading', colSpan: 4 },
          { key: 'section3_period_insurance_from', label: 'Period Of Insurance From', type: 'date' },
          { key: 'section3_period_insurance_to', label: 'To', type: 'date' },
          { key: 'section3_maximum_indemnity', label: 'Maximum Indemnity', type: 'text' },
          { key: 'section3_time_excess', label: 'Time Excess (Months)', type: 'text' },

          { key: '_heading_s3_limits', label: 'Limits of Indemnity & Scheduled Dates', type: 'heading', colSpan: 4 },
          { key: 'section3_limit_indemnity_each_loss', label: '¹ Limit Of Indemnity In Respect Of Each And Every Loss Or Damage And/Or Series Of Losses Arising Out Of Any One Event.', type: 'number', colSpan: 4, placeholder: 'Limit of indemnity' },
          { key: 'section3_scheduled_date_completion', label: '² Scheduled Date Of Completion.', type: 'date', colSpan: 4, placeholder: 'Scheduled date of completion' },
          { key: 'section3_scheduled_date_commencement', label: '³ Scheduled Date Of Commencement Of Insured Business, But Not Earlier Than The Scheduled Date Of Completion.', type: 'date', colSpan: 4, placeholder: 'Scheduled date of commencement' },
        ],
      },
      {
        title: 'Section 3 – Contract Works Insured',
        fields: [
          {
            key: 'section3_contract_works',
            label: 'Contract Works',
            type: 'json-array',
            colSpan: 3,
            cols: [
              { key: '_item_no', label: 'Item No.', readonly: true },
              { key: 'description', label: 'Description of items', type: 'text' },
              { key: 'loss_minimization', label: 'Possible loss minimization', type: 'number' },
            ],
          },
        ],
      },
      {
        title: 'Endorsements / Extensions',
        fields: [
          { key: 'endorsement_1', label: 'Endorsement 1', type: 'textarea', colSpan: 3 },
          { key: 'endorsement_2', label: 'Endorsement 2', type: 'textarea', colSpan: 3 },
          { key: 'endorsement_3', label: 'Endorsement 3', type: 'textarea', colSpan: 3 },
          { key: 'endorsement_4', label: 'Endorsement 4', type: 'textarea', colSpan: 3 },
        ],
      },
      {
        title: 'Additional Notes',
        fields: [
          { key: 'additional_notes', label: 'Additional Notes', type: 'textarea', colSpan: 3 },
        ],
      },
      {
        title: 'Execution Details',
        fields: [
          { key: 'executed_at', label: 'Executed At', type: 'text' },
          { key: 'execution_date', label: 'Date', type: 'date' },
          { key: 'signature', label: 'Signature', type: 'text' },
        ],
      },
    ],
  },

  par: {
    label: 'PAR Coverage',
    slug: 'par',
    sections: [
      {
        title: 'Policy Information',
        fields: [
          { key: 'project_name', label: 'Project Name', type: 'text', colSpan: 2 },
          { key: 'policy_period_months', label: 'Policy Period', type: 'select', options: POLICY_PERIOD },
          { key: 'is_renewable', label: 'Renewable Policy', type: 'select', options: [
            { value: 'Yes', label: 'Yes - Renewable Policy' },
            { value: 'No', label: 'No' },
          ]},
          { key: 'is_project_specific', label: 'Project Specific Policy', type: 'select', options: [
            { value: 'Yes', label: 'Yes - Project Specific Policy' },
            { value: 'No', label: 'No' },
          ]},
        ],
      },
      {
        title: 'Specification of Insured Items',
        fields: [
          {
            key: 'insured_items',
            label: 'Insured Items',
            type: 'json-array',
            colSpan: 3,
            cols: [
              { key: '_item_no', label: 'Item No', readonly: true },
              { key: 'qty', label: 'Qty', type: 'text' },
              { key: 'description', label: 'Description of items (type, manufacturer, capacity)', type: 'text' },
              { key: 'year_of_manufacture', label: 'Year of manufacture', type: 'text' },
              { key: 'sum_insured', label: 'Sum Insured', type: 'number' },
              { key: 'deductible', label: 'Deductible', type: 'text' },
              { key: 'rate', label: 'Rate %', type: 'number' },
              { key: 'premium', label: 'Premium', type: 'number' },
            ],
          },
          { key: 'total_sum_insured', label: 'Total Sum Insured', type: 'number' },
          { key: 'total_premium', label: 'Total Premium', type: 'number' },
        ],
      },
      {
        title: 'Section II – Third Party Liability',
        fields: [
          {
            key: 'section2_items',
            label: 'Section II Items',
            type: 'json-array',
            colSpan: 3,
            cols: [
              { key: 'item_type', label: 'Insured Items', type: 'select', options: [
                { value: 'Bodily Injury', label: 'Bodily Injury' },
                { value: 'Anyone person', label: '- anyone person' },
                { value: 'Total', label: '- total' },
                { value: 'Property Damage', label: 'Property Damage' },
                { value: 'Other', label: 'Other (Specify below)' },
              ]},
              { key: 'description', label: 'Additional description (optional)', type: 'text' },
              { key: 'limit_of_indemnity', label: 'Limits of indemnity', type: 'number' },
              { key: 'deductible', label: 'Deductibles', type: 'text' },
              { key: 'rate', label: 'Rate %', type: 'number' },
              { key: 'premium', label: 'Premium', type: 'number' },
            ],
          },
          { key: 'section2_total_limit', label: 'Total Limit of Indemnity Under Section II', type: 'number' },
          { key: 'section2_total_premium', label: 'Total Premium Under Section II', type: 'number' },
        ],
      },
      {
        title: 'Additional Notes',
        fields: [
          { key: 'additional_notes', label: 'Additional Notes', type: 'textarea', colSpan: 3 },
        ],
      },
      {
        title: 'Execution Details',
        fields: [
          { key: 'executed_at', label: 'Executed At', type: 'text' },
          { key: 'execution_date', label: 'Date', type: 'date' },
          { key: 'signature', label: 'Signature', type: 'text' },
        ],
      },
    ],
  },

  'travel-insurance': {
    label: 'Travel Insurance',
    slug: 'travel-insurance',
    sections: [
      {
        title: 'Travel Insurance - Policy Details',
        fields: [
          { key: 'policyholder', label: 'Policyholder', type: 'text' },
          { key: 'passport', label: 'Passport', type: 'text' },
          { key: 'phone_num', label: 'Phone Number', type: 'text' },
          { key: 'policy_number', label: 'Policy Number (Auto-Generated)', type: 'text' },
          { key: 'number_passengers', label: 'Number. Passengers', type: 'number' },
        ],
      },
      {
        title: 'Coverage Period',
        fields: [
          { key: 'effective_from', label: 'Effective From', type: 'date' },
          { key: 'expiry', label: 'Expiry', type: 'date' },
        ],
      },
      {
        title: 'Policy Financials',
        fields: [
          { key: 'policy_period_months', label: 'Policy Period', type: 'select', options: POLICY_PERIOD },
          { key: 'is_renewable', label: 'Renewable Policy', type: 'select', options: [
            { value: 'Yes', label: 'Yes - Renewable Policy' },
            { value: 'No', label: 'No' },
          ]},
          { key: 'policy_amount', label: 'Policy Amount', type: 'number' },
          { key: 'vat', label: 'VAT (Auto-Calculated)', type: 'number' },
          { key: 'total', label: 'TOTAL (Auto-Calculated)', type: 'number' },
        ],
      },
      {
        title: 'Destination and Origin Information',
        fields: [
          { key: 'destination_area', label: 'Destination Area', type: 'textarea', colSpan: 3 },
          { key: 'country_of_origin', label: 'Country Of Origin', type: 'text' },
          { key: 'product', label: 'Product', type: 'text' },
          { key: 'code', label: 'Code', type: 'text' },
          { key: 'insurance_company', label: 'Insurance Company', type: 'text', colSpan: 2 },
          { key: 'company_location', label: 'Company Location', type: 'text' },
        ],
      },
      {
        title: 'Benefits and Coverage',
        fields: [
          {
            key: 'benefits',
            label: 'Benefits',
            type: 'json-array',
            colSpan: 3,
            cols: [
              { key: 'category', label: 'Category', type: 'text' },
              { key: 'description', label: 'Description Summary', type: 'text' },
              { key: 'sum_insured', label: 'Sum Insured', type: 'number' },
              { key: 'excess', label: 'Excess', type: 'text' },
            ],
            defaultRows: [
              { category: 'Personal Assistance', description: 'Relay Of Urgent Messages' },
              { category: 'Personal Assistance', description: 'Dispatch Of Medication' },
              { category: 'Personal Assistance', description: 'General Information Included' },
              { category: 'Personal Assistance', description: 'Hijack' },
              { category: 'Medical Transportation and Repatriation', description: 'Medical Transportation Or Repatriation' },
              { category: 'Medical Transportation and Repatriation', description: 'Transport Of A Person Due To The Hospitalisation Of The Insured Return Tickets In Economy' },
              { category: 'Medical Transportation and Repatriation', description: 'Max 10 Days/excess' },
              { category: 'Medical Transportation and Repatriation', description: 'Transportation Or Repatriation Of The Accompanying Insureds' },
              { category: 'Medical Expenses', description: 'Medical Expenses Abroad (covid-19 Included)' },
              { category: 'Medical Expenses', description: 'Compulsory Quarantine Due To Diagnosed Covid-19' },
              { category: 'Repatriation of Mortal Remains', description: 'Transport Or Repatriation Of The Deceased Insured' },
              { category: 'Baggage', description: 'Indemnity Due To Problems With The Checked-in Luggage & Travel Documents (accidental Loss)' },
              { category: 'Baggage', description: 'Compensation For Baggage Delay', excess: 'Time Excess' },
              { category: 'Baggage', description: 'Location And Forwarding Of Baggage And Personal Belongings Actual Cost' },
              { category: 'Cancellation', description: 'Reimbursement Of The Cancellation Expenses Of The Trip' },
              { category: 'Curtailment', description: 'Curtailment Expenses' },
              { category: 'Curtailment', description: 'Early Return Due To Serious Family Matter Same Class Ticket' },
              { category: 'Personal Accident', description: 'Permanent Accidental Disability (means Of Transport)' },
              { category: 'Personal Accident', description: 'Accidental Death Means Of Transport' },
              { category: 'Personal Liability', description: 'Personal Liability Due To Material Damages To Third-parties' },
              { category: 'Personal Liability', description: 'Legal Defence (not Traffic)' },
              { category: 'Personal Liability', description: 'Deposit For Legal Costs And Expenses' },
              { category: 'Personal Liability', description: 'Personal Liability Due To Physical Damages To Third-parties' },
              { category: 'Medical Complementary Services', description: 'Hospital Compensation' },
              { category: 'Cards', description: 'Replacement Of The Passport And The Driving Licence By Emergency Documents' },
              { category: 'Delays', description: 'Indemnity Due To The Transport Departure Delay' },
              { category: 'Delays', description: 'Missed Connections' },
            ],
          },
        ],
      },
      {
        title: 'Customer Benefits and Coverage',
        fields: [
          {
            key: 'custom_benefits',
            label: 'Customer Benefits',
            type: 'json-array',
            colSpan: 3,
            cols: [
              { key: 'description', label: 'Description Summary', type: 'text' },
              { key: 'sum_insured', label: 'Sum Insured', type: 'number' },
              { key: 'excess', label: 'Excess', type: 'text' },
            ],
          },
        ],
      },
    ],
  },

  'medical-malpractice': {
    label: 'Medical Malpractice',
    slug: 'medical-malpractice',
    sections: [
      {
        // Single "Policy Details" card matching the legacy Medical Malpractice
        // layout: header/insured fields, dates, the New/Altered + Renewable +
        // Project-Specific selects, and Premium — all stacked single-column.
        // Every key maps to an existing medical_malpractice_coverages column
        // already whitelisted in SpecialistCoverageController::TYPE_MAP, so the
        // form persists with no backend change.
        title: 'Policy Details',
        fields: [
          { key: 'policy_number', label: 'Policy Number', type: 'text', colSpan: 3 },
          { key: 'type_of_document', label: 'Type of Document', type: 'text', colSpan: 3 },
          { key: 'insured', label: 'Insured', type: 'text', colSpan: 3 },
          { key: 'insured_vat_number', label: 'Insured VAT Number', type: 'text', colSpan: 3 },
          { key: 'company_registration_number', label: 'Company Registration Number', type: 'text', colSpan: 3 },
          { key: 'insured_business_description', label: 'Insured Business Description', type: 'textarea', colSpan: 3 },
          { key: 'insured_postal_address', label: 'Insured Postal Address', type: 'textarea', colSpan: 3 },
          { key: 'intermediary', label: 'Intermediary', type: 'text', colSpan: 3 },
          { key: 'policy_inception_date', label: 'Policy inception date', type: 'date', colSpan: 3 },
          { key: 'policy_expiry_date', label: 'Policy expiry date', type: 'date', colSpan: 3 },
          { key: 'today_date', label: "Today's date", type: 'date', colSpan: 3 },
          { key: 'new_altered', label: 'New/altered', type: 'select', options: NEW_ALTERED, colSpan: 3 },
          { key: 'period_of_insurance', label: 'Period of Insurance', type: 'select', options: POLICY_PERIOD, colSpan: 3 },
          { key: 'is_renewable', label: 'Renewable Policy', type: 'select', options: YES_NO, colSpan: 3 },
          { key: 'is_project_specific', label: 'Project Specific Policy', type: 'select', options: YES_NO, colSpan: 3 },
          { key: 'anniversary_renewal_date', label: 'Anniversary/Renewal Date', type: 'date', colSpan: 3 },
          { key: 'retroactive_date', label: 'Retroactive Date', type: 'date', colSpan: 3 },
          { key: 'annual_premium', label: 'Premium', type: 'number', colSpan: 3 },
        ],
      },
      {
        title: 'Risk Details',
        fields: [
          {
            key: 'risk_details',
            label: 'Risk Details',
            type: 'json-array',
            colSpan: 3,
            cols: [
              { key: 'risk_detail', label: 'Risk Details', type: 'text' },
              { key: 'value', label: 'Limit of Indemnity', type: 'number' },
            ],
            defaultRows: [
              { risk_detail: 'Limit of Indemnity' },
              { risk_detail: 'Basis of Limit' },
              { risk_detail: 'Cumulative Limit' },
              { risk_detail: 'Automatic Reinstatement' },
              { risk_detail: 'Additional Reporting Period' },
            ],
          },
        ],
      },
      {
        // Extensions Applicable, then a separate "Specific Deductible in respect
        // of:" card. They are two sections because the form renderer only shows
        // a heading per section title (a json-array field's own label is not
        // rendered), so the deductible table needs its own titled card.
        title: 'Extensions Applicable',
        fields: [
          {
            key: 'extensions',
            label: 'Extensions',
            type: 'json-array',
            colSpan: 3,
            cols: [
              { key: 'section_name', label: 'Section Name', type: 'text' },
              { key: 'limit_of_indemnity', label: 'Limit of Indemnity', type: 'number' },
              { key: 'basis_of_limit', label: 'Basis of Limit', type: 'text' },
              { key: 'deductible', label: 'Deductible', type: 'number' },
              { key: 'basis_of_deductible', label: 'Basis of Deductible', type: 'text' },
            ],
            defaultRows: [
              { section_name: 'Medical Malpractice' },
              { section_name: 'Professional Indemnity' },
              { section_name: 'Public Liability' },
              { section_name: 'Pollution Liability' },
              { section_name: 'Products Liability' },
              { section_name: 'Employers Liability' },
              { section_name: 'Breach of Confidentiality' },
              { section_name: 'Business Identity Theft' },
              { section_name: 'Defamation' },
              { section_name: 'Documents' },
              { section_name: 'Statutory Defence Costs (Sub-Limit of Public Liability)' },
              { section_name: 'Wrongful Arrest (Sub-Limit of Public Liability)' },
            ],
          },
        ],
      },
      {
        title: 'Specific Deductible in respect of:',
        fields: [
          {
            key: 'specific_deductibles',
            label: 'Specific Deductibles',
            type: 'json-array',
            colSpan: 3,
            cols: [
              { key: 'section_name', label: 'Section Name', type: 'text' },
              { key: 'limit_of_indemnity', label: 'Limit of Indemnity', type: 'number' },
              { key: 'basis_of_limit', label: 'Basis of Limit', type: 'text' },
              { key: 'deductible', label: 'Deductible', type: 'number' },
              { key: 'basis_of_deductible', label: 'Basis of Deductible', type: 'text' },
            ],
            defaultRows: [
              { section_name: 'Online Therapy' },
            ],
          },
        ],
      },
      {
        title: 'Standard Policy Conditions',
        fields: [
          { key: 'standard_policy_conditions', label: 'Medical Malpractice for Medical Professions Renewal Terms', type: 'textarea', colSpan: 3 },
        ],
      },
      {
        title: 'Note',
        fields: [
          { key: 'notes', label: '', type: 'textarea', colSpan: 3 },
        ],
      },
    ],
  },

  'professional-indemnity': {
    label: 'Professional Indemnity',
    slug: 'professional-indemnity',
    sections: [
      {
        title: 'Policy Information',
        fields: [
          { key: 'insured', label: 'Insured', type: 'text', colSpan: 2 },
          { key: 'profession_business', label: 'Profession / Business', type: 'text', colSpan: 2 },
          { key: 'basis_of_cover', label: 'Basis of Cover', type: 'select', options: [
            { value: 'Claims Made', label: 'Claims Made' },
            { value: 'Claims Occurring', label: 'Claims Occurring' },
          ]},
          { key: 'period_of_insurance', label: 'Period of Insurance', type: 'select', options: POLICY_PERIOD },
          { key: 'policy_inception_date', label: 'Inception Date', type: 'date' },
          { key: 'policy_expiry_date', label: 'Expiry Date', type: 'date' },
          { key: 'today_date', label: "Today's Date", type: 'date' },
          { key: 'retroactive_date', label: 'Retroactive Date', type: 'date' },
        ],
      },
      {
        title: 'Settings',
        fields: [
          { key: 'new_altered', label: 'New / Altered', type: 'select', options: NEW_ALTERED },
          { key: 'is_renewable', label: 'Renewable Policy', type: 'select', options: YES_NO },
          { key: 'is_project_specific', label: 'Project Specific Policy', type: 'select', options: YES_NO },
          // Premium = scalar contract premium for this PI schedule. Read by
          // calculatePremium API ($specialistTables['professional_indemnity_coverages']
          // = ['premium']) and getProfessionalIndemnityTotal so Rate sums correctly.
          { key: 'premium', label: 'Premium', type: 'number', colSpan: 3 },
        ],
      },
      {
        // Rendered after the Policy Wording upload (Sonali's request). Kept as a
        // config section so the `notes` scalar stays wired into load/save.
        title: 'Notes',
        afterPolicyWording: true,
        fields: [
          { key: 'notes', label: 'Notes', type: 'textarea', colSpan: 3 },
        ],
      },
      {
        title: 'Insured Persons',
        fields: [
          {
            key: 'insured_persons',
            label: 'Insured Persons',
            type: 'json-array',
            colSpan: 3,
            cols: [
              { key: 'description', label: 'Description', type: 'select', options: [
                { value: 'Blanket', label: 'Blanket' },
                { value: 'Named Persons', label: 'Named Persons' },
              ]},
              { key: 'insured_person', label: 'Insured Person', type: 'text' },
              { key: 'length_of_service', label: 'Length of Service', type: 'text' },
              { key: 'limit_of_liability', label: 'Limit of Liability', type: 'number' },
              { key: 'designation', label: 'Designation', type: 'text' },
            ],
          },
        ],
      },
      {
        title: 'Extensions',
        fields: [
          {
            key: 'extensions',
            label: 'Extensions',
            type: 'json-array',
            colSpan: 3,
            cols: [
              { key: 'extension', label: 'Extension', type: 'text' },
              { key: 'limit_of_liability', label: 'Limit of Liability', type: 'number' },
              { key: 'premium', label: 'Premium', type: 'number' },
            ],
            // Standard PI extension schedule — operators can edit/add/delete.
            defaultRows: [
              { extension: 'Sub Contracted Duties' },
              { extension: 'Liability Following Employee Dishonesty' },
              { extension: 'Mitigation of Loss' },
              { extension: 'Computer Crime' },
              { extension: 'Defamation' },
              { extension: 'Criminal and Statutory Defence Costs' },
              { extension: 'Loss Of Documents' },
              { extension: 'Fee Recovery' },
              { extension: 'Business Identity Theft' },
              { extension: 'Claims Preparation Costs' },
              { extension: 'Commercial Crime' },
              { extension: 'Directors & Officers Liability' },
              { extension: 'General Public Liability' },
            ],
          },
        ],
      },
      {
        title: 'Excesses',
        fields: [
          {
            key: 'excesses',
            label: 'Excesses',
            type: 'json-array',
            colSpan: 3,
            cols: [
              { key: 'description', label: 'Excess Type', type: 'text' },
              { key: 'percent', label: '%', type: 'number' },
              { key: 'minimum_excess', label: 'Minimum Excess', type: 'number' },
            ],
            // Default: Basic + one Others row, matching the legacy layout.
            defaultRows: [
              { description: 'Basic' },
              { description: 'Others' },
            ],
          },
        ],
      },
    ],
  },

  'machinery-breakdown': {
    label: 'Machinery Breakdown',
    slug: 'machinery-breakdown',
    sections: [
      {
        title: 'Policy Schedule',
        fields: [
          { key: 'policy_number', label: 'Policy No.', type: 'text' },
          { key: 'company_name', label: 'Insured Name', type: 'text', colSpan: 2, readonly: true },
          { key: 'company_address', label: 'Company Address', type: 'text', colSpan: 3, readonly: true },
          { key: 'inception_date', label: 'Inception Date', type: 'date', readonly: true },
          { key: 'expiry_date', label: 'Expiry Date', type: 'date', readonly: true },
          { key: 'today_date', label: "Today's Date", type: 'date' },
          { key: 'is_renewable', label: 'Renewable Policy', type: 'select', options: [
            { value: 'Yes', label: 'Yes – Renewable Policy' },
            { value: 'No', label: 'No' },
          ]},
          { key: 'currency', label: 'Currency', type: 'text' },
          // Read-only total. Machinery Breakdown is priced section by section,
          // so the cover's premium is the sum of the four priced blocks below
          // (Section 1 + Machinery Listing + Section 2 + Section 3) — see the
          // cascade effect keyed on `machinery-breakdown`. Leaving this
          // hand-typed let it drift from the schedule, and since the Rate
          // banner, V2 quote sheet and Policy Document all read this one
          // figure, a schedule with every section priced still rated P 0.00.
          { key: 'premium', label: 'Premium', type: 'number', readonly: true,
            readonlyNote: '(total of Sections 1–3 + Machinery Listing)' },
        ],
      },
      {
        title: 'SECTION 1 – Equipment Damage and Breakdown (Specified Cover Basis)',
        fields: [
          {
            key: 'section1_items',
            label: 'Section 1 Items',
            type: 'json-array',
            colSpan: 3,
            locked: true,
            cols: [
              { key: 'name', label: 'Coverage Item / Description', type: 'text', readonly: true },
              { key: 'limit_status', label: 'Limit / Status', type: 'number' },
              { key: 'premium', label: 'Premium', type: 'number' },
            ],
            defaultRows: [
              { name: 'Damage to insured property (per occurrence)', limit_status: '', premium: '' },
              { name: 'Cost of replacing undamaged non-compatible parts', limit_status: '', premium: '' },
              { name: 'Total insured value', limit_status: '', premium: '' },
            ],
          },
          { key: 'section1_total_premium', label: 'Section 1 Total Premium', type: 'number', readonly: true,
            readonlyNote: '(sum of the rows above)' },
        ],
      },
      {
        title: 'Machinery Listing',
        fields: [
          {
            key: 'machinery_listing',
            label: 'Machinery Items',
            type: 'json-array',
            colSpan: 3,
            cols: [
              { key: '_item_no', label: 'Item No.', readonly: true },
              { key: 'quantity', label: 'Qty', type: 'text' },
              { key: 'description', label: 'Description', type: 'text' },
              { key: 'year_of_manufacture', label: 'Year of Manufacture', type: 'text' },
              { key: 'deductible', label: 'Deductible', type: 'number' },
              { key: 'rate', label: 'Rate %', type: 'number' },
              { key: 'premium', label: 'Premium', type: 'number' },
            ],
          },
          { key: 'machinery_listing_total_premium', label: 'Machinery Listing Total Premium', type: 'number', readonly: true,
            readonlyNote: '(sum of the rows above)' },
        ],
      },
      {
        title: 'Extensions – Section 1 (limits per occurrence, on top of limit of liability)',
        fields: [
          {
            key: 'extra_cover_section1',
            label: 'Extra Cover',
            type: 'json-array',
            colSpan: 3,
            cols: [
              { key: 'name', label: 'Cover', type: 'text' },
              { key: 'limit', label: 'Limit', type: 'number' },
            ],
            defaultRows: [
              { name: 'Contamination', limit: '' },
              { name: 'Emergency Services', limit: '' },
              { name: 'Energy Efficiency Improvements', limit: '' },
              { name: 'Hazardous Substances', limit: '' },
              { name: 'Hire Charges for Substitute Equipment', limit: '' },
              { name: 'Lifted Goods', limit: '' },
              { name: 'Movement of Insured Property', limit: '' },
              { name: 'Newly Acquired Property', limit: '' },
              { name: 'Own Surrounding Property', limit: '' },
              { name: "Public Authorities' Requirements", limit: '' },
              { name: 'Removing Debris', limit: '' },
              { name: 'Storage Tank Contents', limit: '' },
              { name: 'Temporary and Fast-Tracked Repair', limit: '' },
              { name: 'Temporary Plant', limit: '' },
            ],
          },
        ],
      },
      {
        title: 'SECTION 2 – Deterioration of Stock',
        fields: [
          {
            key: 'section2_items',
            label: 'Section 2 Items',
            type: 'json-array',
            colSpan: 3,
            locked: true,
            cols: [
              { key: 'name', label: 'Item', type: 'text', readonly: true },
              { key: 'rate', label: 'Rate %', type: 'number' },
              { key: 'premium', label: 'Premium', type: 'number' },
            ],
            defaultRows: [
              { name: 'Deterioration of insured stock', rate: '', premium: '' },
              { name: 'Type of cold chamber / Location / Max value of insured stock', rate: '', premium: '' },
            ],
          },
          { key: 'section2_total_premium', label: 'Section 2 Total Premium', type: 'number', readonly: true,
            readonlyNote: '(sum of the rows above)' },
        ],
      },
      {
        title: 'Extensions – Section 2',
        fields: [
          {
            key: 'extra_cover_section2',
            label: 'Extra Cover',
            type: 'json-array',
            colSpan: 3,
            cols: [
              { key: 'name', label: 'Cover', type: 'text' },
              { key: 'limit', label: 'Limit', type: 'number' },
            ],
            defaultRows: [
              { name: 'Cleaning and Disinfection', limit: '' },
              { name: 'Disposal of Insured Stock', limit: '' },
              { name: 'Refrigerated Vehicles', limit: '' },
            ],
          },
        ],
      },
      {
        title: 'SECTION 3 – Loss of Income',
        fields: [
          {
            key: 'section3_items',
            label: 'Section 3 Items',
            type: 'json-array',
            colSpan: 3,
            locked: true,
            cols: [
              { key: 'name', label: 'Item', type: 'text', readonly: true },
              { key: 'value', label: 'Gross Profit', type: 'number' },
              { key: 'rate', label: 'Rate %', type: 'number' },
              { key: 'premium', label: 'Premium', type: 'number' },
            ],
            defaultRows: [
              { name: 'Financial loss during indemnity period (per occurrence)', value: '', rate: '', premium: '' },
              { name: 'Estimated Gross Income', value: '', rate: '', premium: '' },
              { name: 'Indemnity Period', value: '', rate: '', premium: '' },
            ],
          },
          { key: 'section3_total_premium', label: 'Section 3 Total Premium', type: 'number', readonly: true,
            readonlyNote: '(sum of the rows above)' },
        ],
      },
      {
        title: 'Extensions – Section 3',
        fields: [
          {
            key: 'extra_cover_section3',
            label: 'Extra Cover',
            type: 'json-array',
            colSpan: 3,
            cols: [
              { key: 'name', label: 'Cover', type: 'text' },
              { key: 'limit', label: 'Limit', type: 'number' },
            ],
            defaultRows: [
              { name: 'Anchor Location', limit: '' },
              { name: 'Brands and Labels', limit: '' },
              { name: "Claims Preparation and Accountants' Fees", limit: '' },
              { name: "Customer's Extension", limit: '' },
              { name: 'Deterioration', limit: '' },
              { name: 'Public Relations Costs', limit: '' },
              { name: 'Public Utilities', limit: '' },
              { name: 'Reinstatement of Data', limit: '' },
              { name: "Supplier's Extension", limit: '' },
            ],
          },
        ],
      },
      {
        title: 'Extension – Applying to All Sections',
        fields: [
          {
            key: 'extra_cover_all_sections',
            label: 'Extra Cover (All Sections)',
            type: 'json-array',
            colSpan: 3,
            cols: [
              { key: 'name', label: 'Cover', type: 'text' },
              { key: 'limit', label: 'Limit', type: 'number' },
            ],
            defaultRows: [
              { name: 'Investigation Cost (per occurrence)', limit: '' },
              { name: 'Loss Prevention Measures (per occurrence)', limit: '' },
            ],
          },
        ],
      },
      {
        title: 'EXCESS DETAILS',
        fields: [
          {
            key: 'excess_details',
            label: 'Excess Details',
            type: 'json-array',
            colSpan: 3,
            cols: [
              { key: 'name', label: 'Description', type: 'text' },
              { key: 'value', label: 'Minimum Excess', type: 'number' },
              { key: 'rate', label: 'Rate %', type: 'number' },
            ],
            defaultRows: [
              { name: 'Equipment Damage and Breakdown (per occurrence)', value: '', rate: '' },
              { name: "Extra Cover 'Lifted Goods'", value: '', rate: '' },
              { name: 'Deterioration of Stock', value: '', rate: '' },
              { name: 'Loss of Income (time excess)', value: '', rate: '' },
              { name: 'Extra Cover Public Utilities - Franchise', value: '', rate: '' },
            ],
          },
        ],
      },
      {
        title: 'Endorsements / Notes',
        fields: [
          { key: 'endorsements', label: 'Endorsements', type: 'textarea', colSpan: 3 },
          { key: 'notes', label: 'Notes', type: 'textarea', colSpan: 3 },
        ],
      },
    ],
  },

  'marine-cargo-once-off': {
    label: 'Marine Cargo Once-Off',
    slug: 'marine-cargo-once-off',
    sections: [
      {
        title: 'Name & Address of the Assured',
        fields: [
          // Legacy "Name & Address of the Assured" block: a left section label
          // with the four identity fields in a 2x2 grid on the right (Name /
          // Open Policy No. on top, Address / Agent-Broker below). Sub-field keys
          // are unchanged (assured_name auto-fills, policy_number, assured_address,
          // agent_broker_code_no) so prefill + save are untouched.
          {
            key: 'assured_block',
            label: 'Name & Address of the Assured',
            type: 'field-group',
            colSpan: 3,
            subFields: [
              { key: 'assured_name', label: 'Name:', type: 'text', readonly: true, placeholder: 'Name of the Assured' },
              { key: 'policy_number', label: 'Open Policy No.:', type: 'text' },
              { key: 'assured_address', label: 'Address:', type: 'textarea', placeholder: 'Address of the Assured' },
              { key: 'agent_broker_code_no', label: 'Agent/Broker Code No.:', type: 'text', placeholder: 'Agent/Broker Code No.' },
            ],
          },
          // Conveyance: left label with the input in the middle column (same
          // position/width as the "From" date box). Rendered via field-group
          // with a single sub-field so the saved key stays 'conveyance'.
          {
            key: 'conveyance_group',
            label: 'Conveyance',
            type: 'field-group',
            colSpan: 3,
            subFields: [
              { key: 'conveyance', label: '', type: 'text' },
            ],
          },
          // Policy Period (auto-filled from the parent policy term, read-only) and
          // Voyage rendered as the legacy "From: / To:" date-range layout. The
          // underlying scalar keys (policy_period_from/to, voyage_from/to) are
          // unchanged, so prefill + save are untouched.
          { key: 'policy_period', label: 'Policy Period', type: 'date-range', colSpan: 3, readonly: true,
            fromKey: 'policy_period_from', toKey: 'policy_period_to',
            description: 'This open policy is to remain in force a period of 12 months unless sum insured is previously exhausted by declarations.' },
          { key: 'voyage', label: 'Voyage', type: 'date-range', colSpan: 3,
            fromKey: 'voyage_from', toKey: 'voyage_to' },
          { key: 'commodities_covered', label: 'Commodities Covered', type: 'text', colSpan: 2 },
          { key: 'nature_of_packing', label: 'Nature of Packing', type: 'text' },
          { key: 'terms_of_cover', label: 'Terms of Cover', type: 'text', colSpan: 2 },
          { key: 'annual_estimated_turnover', label: 'Annual Estimated Turnover', type: 'number' },
          { key: 'location_limit', label: 'Location Limit', type: 'number' },
          { key: 'premium_rate', label: 'Premium Rate (%)', type: 'number' },
          { key: 'sum_insured', label: 'Sum Insured', type: 'number' },
          { key: 'premium', label: 'Premium', type: 'number' },
          { key: 'currency', label: 'Currency', type: 'text' },
          { key: 'today_date', label: "Today's Date", type: 'date' },
        ],
      },
      {
        title: 'Special Conditions & Warranties: This Insurance is subject to the following Clause & Conditions as printed herein or attached hereto',
        description: 'CLAUSES (TICK THE APPLICABLE CLAUSES)',
        fields: [
          { key: 'clauses', label: '', type: 'checkbox-list', colSpan: 3, options: [
            { value: '1',  label: 'Institute Cargo Clause (A)' },
            { value: '2',  label: 'Institute Cargo Clause (B)' },
            { value: '3',  label: 'Institute Cargo Clause (C)' },
            { value: '4',  label: 'Malicious Damage Clause' },
            { value: '5',  label: 'Institute Theft, Pilferage, Nondelivery Clause' },
            { value: '6',  label: 'Institute Replacement Clause' },
            { value: '7',  label: 'Replacement Clause (Second Hand Machinery)' },
            { value: '8',  label: 'Label Clause' },
            { value: '9',  label: 'Pair and Set Clause' },
            { value: '10', label: 'Institute War Clause (Cargo)' },
            { value: '11', label: 'Institute Strikes Clause (Cargo)' },
            { value: '12', label: 'Institute Cargo Clause (Air) (excluding sendings by post)' },
            { value: '13', label: 'Institute War Clauses (sendings by post)' },
            { value: '14', label: 'Institute War Clauses (Air Cargo) (excluding sendings by post)' },
            { value: '15', label: 'Institute Strikes Clause (Air Cargo)' },
            { value: '16', label: 'Institute War Cancellation Clause (Cargo)' },
            { value: '17', label: 'Institute Classification Clause' },
            { value: '18', label: 'Inland Transit (Rail or Road) A – All Risks' },
            { value: '19', label: 'Inland Transit (Rail or Road) B – Basic Cover' },
            { value: '20', label: 'Inland Transit (Rail or Road) C – Fire Risk' },
            { value: '21', label: 'Inland SRCC Clause' },
            { value: '22', label: 'Inland Transit (Inland Vessels) Clause' },
            { value: '23', label: 'Sailing Vessels Clause' },
            { value: '24', label: 'Important Notice' },
            { value: '25', label: 'Duty Clause' },
            { value: '26', label: 'Increased Value Insurance Clause' },
            { value: '27', label: 'Institute Radioactive Contamination Exclusion Clause' },
            { value: '28', label: 'Terrorism Exclusion Clause' },
          ]},
        ],
      },
      {
        title: 'Survey and Claim Settlement',
        description: 'THE ATTACHED CLAUSES AND ENDORSEMENTS FORM PART OF THE POLICY',
        fields: [
          { key: 'survey_claim_settlement', label: 'Survey and Claim Settlement', type: 'textarea', colSpan: 3,
            placeholder: 'In the event of loss or damage which may involve a claim under this Insurance, immediate notice thereof and application for survey should be given to:' },
          { key: 'claim_payable_at', label: 'Claim Payable at', type: 'text', colSpan: 3, placeholder: 'Claim Payable at' },
          { key: 'claim_payable_by', label: 'Claim Payable by', type: 'text', colSpan: 3, placeholder: 'Claim Payable by' },
        ],
      },
      {
        title: 'In Witness Whereof',
        description: 'IN WITNESS WHEREOF signed for and on behalf of the Company',
        fields: [
          { key: 'place', label: 'Place', type: 'text' },
          { key: 'signing_date', label: 'Signing Date', type: 'date' },
          { key: 'examined_by', label: 'Examined By', type: 'text' },
        ],
      },
      {
        title: 'Memorandum Attaching to and Forming Part of Policy',
        fields: [
          { key: '_heading_basis', label: 'Basis of Valuation / Inspection of Records', type: 'heading', colSpan: 3 },
          { key: 'basis_of_valuation', label: '', type: 'textarea', colSpan: 3, placeholder: 'Basis of Valuation' },
          { key: '_heading_per_conveyance', label: 'Per Conveyance Limit', type: 'heading', colSpan: 3 },
          { key: '_helptext_per_conveyance', label: "Warranted that the limit of the Insurer's liability in respect of any one accident or series of accidents arising from the same events shall not exceed:", type: 'help-text', colSpan: 3 },
          { key: 'per_conveyance_rail',   label: 'a. Per any one rail transit',                          type: 'number', colSpan: 3, inlineLabel: true, placeholder: 'Amount' },
          { key: 'per_conveyance_road',   label: 'b. Per any one road vehicle',                          type: 'number', colSpan: 3, inlineLabel: true, placeholder: 'Amount' },
          { key: 'per_conveyance_air',    label: 'c. Per any one air transit and connecting conveyance', type: 'number', colSpan: 3, inlineLabel: true, placeholder: 'Amount' },
          { key: 'per_conveyance_post',   label: 'd. Per any one registered post & or courier',          type: 'number', colSpan: 3, inlineLabel: true, placeholder: 'Amount' },
          { key: 'per_conveyance_vessel', label: 'e. Per any one vessel and connecting conveyance',      type: 'number', colSpan: 3, inlineLabel: true, placeholder: 'Amount' },
          { key: '_heading_location_limit', label: 'Location Limit', type: 'heading', colSpan: 3 },
          { key: '_helptext_location_limit', label: 'Not withstanding anything to the contrary stated herein, in the event of loss and/or damage to the subject matter insured or any expense incurred by way of sue and labour, the total liability of the company in any particular location, for all the insured consignments covered under this Open Policy in respect of any one accident/occurrence or a series of accidents and/or occurrences arising out of the same event, shall not exceed Location Limit stated in the Schedule.', type: 'help-text', colSpan: 3 },
          { key: '_heading_deductible', label: 'Deductible', type: 'heading', colSpan: 3 },
          { key: '_helptext_deductible', label: 'The Policy is subject to the following Deductibles (Deductible is the amount of loss to borne by the Insured under each and every loss)', type: 'help-text', colSpan: 3 },
          { key: 'deductible', label: '', type: 'textarea', colSpan: 3, placeholder: 'Deductible details' },
        ],
      },
      {
        title: 'Notice of Cancellation & Refund',
        fields: [
          { key: 'notice_of_cancellation', label: 'Notice of Cancellation', type: 'textarea', colSpan: 3,
            placeholder: 'This policy is subject to cancellation by either side after giving 30 days time of cancellation in writing. SRCC risks are subject to 48 hours notice of cancellation.' },
          { key: 'refund', label: 'Refund', type: 'textarea', colSpan: 3,
            placeholder: 'In the event of cancellation as above pro-rata refund of premium will be made in respect of undeclared balance.' },
        ],
      },
      {
        title: 'Additional Notes',
        afterPolicyWording: true,
        fields: [
          { key: 'notes', label: 'Notes', type: 'textarea', colSpan: 3 },
        ],
      },
    ],
  },

  'marine-cargo-open': {
    label: 'Marine Cargo Open',
    slug: 'marine-cargo-open',
    sections: [
      {
        title: 'Name & Address of the Assured',
        fields: [
          // Legacy "Name & Address of the Assured" block: a left section label
          // with the four identity fields in a 2x2 grid on the right (Name /
          // Open Policy No. on top, Address / Agent-Broker below). Sub-field keys
          // are unchanged (assured_name + open_policy_no auto-fill / read-only,
          // agent_broker_code, assured_address) so prefill + save are untouched.
          {
            key: 'assured_block',
            label: 'Name & Address of the Assured',
            type: 'field-group',
            colSpan: 3,
            subFields: [
              { key: 'assured_name', label: 'Name:', type: 'text', readonly: true, placeholder: 'Name of the Assured' },
              { key: 'open_policy_no', label: 'Open Policy No.:', type: 'text', readonly: true },
              { key: 'assured_address', label: 'Address:', type: 'textarea', placeholder: 'Address of the Assured' },
              { key: 'agent_broker_code', label: 'Agent/Broker Code No.:', type: 'text', placeholder: 'Agent/Broker Code No.' },
            ],
          },
          // Conveyance (label left / input column), Policy Period (auto-filled
          // from the parent policy term, read-only) and Voyage rendered in the
          // legacy From/To date-range layout. Underlying scalar keys are
          // unchanged (conveyance, policy_period_from/to, voyage_from/to) so
          // prefill + save are untouched.
          {
            key: 'conveyance_group',
            label: 'Conveyance',
            type: 'field-group',
            colSpan: 3,
            subFields: [
              { key: 'conveyance', label: '', type: 'text' },
            ],
          },
          { key: 'policy_period', label: 'Policy Period', type: 'date-range', colSpan: 3, readonly: true,
            fromKey: 'policy_period_from', toKey: 'policy_period_to',
            description: 'This open policy is to remain in force a period of 12 months unless sum insured is previously exhausted by declarations.' },
          { key: 'voyage', label: 'Voyage', type: 'date-range', colSpan: 3,
            fromKey: 'voyage_from', toKey: 'voyage_to' },
          { key: 'commodities_covered', label: 'Commodities Covered', type: 'text', colSpan: 2 },
          { key: 'nature_of_packing', label: 'Nature of Packing', type: 'text' },
          { key: 'terms_of_cover', label: 'Terms of Cover', type: 'text', colSpan: 2 },
          { key: 'annual_estimated_turnover', label: 'Annual Estimated Turnover', type: 'number' },
          { key: 'location_limit', label: 'Location Limit', type: 'number' },
          { key: 'premium_rate', label: 'Premium Rate (%)', type: 'number' },
          { key: 'sum_insured', label: 'Sum Insured', type: 'number' },
          { key: 'premium', label: 'Premium', type: 'number' },
          { key: 'today_date', label: "Today's Date", type: 'date' },
        ],
      },
      {
        title: 'Special Conditions & Warranties — Clauses (tick the applicable clauses)',
        fields: [
          { key: 'clauses', label: '', type: 'checkbox-list', colSpan: 3, options: [
            { value: '1',  label: 'Institute Cargo Clause (A)' },
            { value: '2',  label: 'Institute Cargo Clause (B)' },
            { value: '3',  label: 'Institute Cargo Clause (C)' },
            { value: '4',  label: 'Malicious Damage Clause' },
            { value: '5',  label: 'Institute Theft, Pilferage, Nondelivery Clause' },
            { value: '6',  label: 'Institute Replacement Clause' },
            { value: '7',  label: 'Replacement Clause (Second Hand Machinery)' },
            { value: '8',  label: 'Label Clause' },
            { value: '9',  label: 'Pair and Set Clause' },
            { value: '10', label: 'Institute War Clause (Cargo)' },
            { value: '11', label: 'Institute Strikes Clause (Cargo)' },
            { value: '12', label: 'Institute Cargo Clause (Air) (excluding sendings by post)' },
            { value: '13', label: 'Institute War Clauses (sendings by post)' },
            { value: '14', label: 'Institute War Clauses (Air Cargo) (excluding sendings by post)' },
            { value: '15', label: 'Institute Strikes Clause (Air Cargo)' },
            { value: '16', label: 'Institute War Cancellation Clause (Cargo)' },
            { value: '17', label: 'Institute Classification Clause' },
            { value: '18', label: 'Inland Transit (Rail or Road) A – All Risks' },
            { value: '19', label: 'Inland Transit (Rail or Road) B – Basic Cover' },
            { value: '20', label: 'Inland Transit (Rail or Road) C – Fire Risk' },
            { value: '21', label: 'Inland SRCC Clause' },
            { value: '22', label: 'Inland Transit (Inland Vessels) Clause' },
            { value: '23', label: 'Sailing Vessels Clause' },
            { value: '24', label: 'Important Notice' },
            { value: '25', label: 'Duty Clause' },
            { value: '26', label: 'Increased Value Insurance Clause' },
            { value: '27', label: 'Institute Radioactive Contamination Exclusion Clause' },
            { value: '28', label: 'Terrorism Exclusion Clause' },
          ]},
        ],
      },
      {
        title: 'Survey & Claim Settlement',
        fields: [
          { key: 'survey_claim_settlement', label: 'Survey & Claim Settlement Details', type: 'textarea', colSpan: 3 },
          { key: 'claim_payable_at', label: 'Claim Payable At', type: 'text' },
          { key: 'claim_payable_by', label: 'Claim Payable By', type: 'text' },
        ],
      },
      {
        // In Witness Whereof — Place / Signing Date / Examined By rendered inline
        // (label on the left, input on the right) to match the reference. Keys
        // are unchanged (place, signing_date, examined_by); they were moved out
        // of the Survey & Claim Settlement section above into their own section,
        // so save/load are untouched.
        title: 'In Witness Whereof',
        description: 'IN WITNESS WHEREOF signed for and on behalf of the Company',
        fields: [
          { key: 'place', label: 'Place', type: 'text', colSpan: 3, inlineLabel: true },
          { key: 'signing_date', label: 'Signing Date', type: 'date', colSpan: 3, inlineLabel: true },
          { key: 'examined_by', label: 'Examined By', type: 'text', colSpan: 3, inlineLabel: true },
        ],
      },
      {
        title: 'Memorandum Attaching to and Forming Part of Open Policy',
        fields: [
          { key: '_heading_declaration', label: 'Declaration', type: 'heading', colSpan: 3 },
          { key: 'declaration', label: '', type: 'textarea', colSpan: 3 },
          { key: '_heading_basis', label: 'Basis of Valuation / Inspection of Records', type: 'heading', colSpan: 3 },
          { key: '_helptext_basis', label: "The Company and / or its agents will have the right at any time during business hours to inspect assured's records of dispatches made within the terms of the policy.", type: 'help-text', colSpan: 3 },
          { key: 'basis_of_valuation', label: '', type: 'textarea', colSpan: 3, placeholder: 'Basis of Valuation' },
          { key: '_heading_location_limit', label: 'Location Limit', type: 'heading', colSpan: 3 },
          { key: '_helptext_location_limit', label: 'Not withstanding anything to the contrary stated herein, in the event of loss and/or damage to the subject matter insured or any expense incurred by way of sue and labour, the total liability of the company in any particular location, for all the insured consignments covered under this Open Policy in respect of any one accident/occurrence or a series of accidents and/or occurrences arising out of the same event, shall not exceed Location Limit stated in the Schedule.', type: 'help-text', colSpan: 3 },
          { key: '_heading_deductible', label: 'Deductible', type: 'heading', colSpan: 3 },
          { key: 'deductible', label: '', type: 'textarea', colSpan: 3, placeholder: 'Deductible details' },
        ],
      },
      {
        title: 'Per Conveyance Limit',
        fields: [
          { key: '_helptext_per_conveyance', label: "Warranted that the limit of the Insurer's liability in respect of any one accident or series of accidents arising from the same events shall not exceed:", type: 'help-text', colSpan: 3 },
          { key: 'per_conveyance_rail',   label: 'a. Per any one rail transit',                          type: 'number', colSpan: 3, inlineLabel: true, placeholder: 'Amount' },
          { key: 'per_conveyance_road',   label: 'b. Per any one road vehicle',                          type: 'number', colSpan: 3, inlineLabel: true, placeholder: 'Amount' },
          { key: 'per_conveyance_air',    label: 'c. Per any one air transit and connecting conveyance', type: 'number', colSpan: 3, inlineLabel: true, placeholder: 'Amount' },
          { key: 'per_conveyance_post',   label: 'd. Per any one registered post & or courier',          type: 'number', colSpan: 3, inlineLabel: true, placeholder: 'Amount' },
          { key: 'per_conveyance_vessel', label: 'e. Per any one vessel and connecting conveyance',      type: 'number', colSpan: 3, inlineLabel: true, placeholder: 'Amount' },
        ],
      },
      {
        title: 'Notice of Cancellation & Refund',
        fields: [
          { key: 'notice_of_cancellation', label: 'Notice of Cancellation', type: 'textarea', colSpan: 3 },
          { key: 'refund', label: 'Refund', type: 'textarea', colSpan: 3 },
        ],
      },
      {
        title: 'Over Declaration',
        fields: [
          { key: 'over_declaration', label: '', type: 'textarea', colSpan: 3, placeholder: 'Over Declaration' },
        ],
      },
      {
        title: 'Additional Notes',
        afterPolicyWording: true,
        fields: [
          { key: 'notes', label: 'Notes', type: 'textarea', colSpan: 3 },
        ],
      },
    ],
  },

  'marine-directors-officers': {
    label: 'Directors & Officers',
    slug: 'marine-directors-officers',
    sections: [
      {
        title: 'Policy Details',
        fields: [
          { key: 'policy_number', label: 'Policy Number', type: 'text' },
          { key: 'company_name', label: 'Company Name (Policyholder)', type: 'text', colSpan: 2 },
          { key: 'company_address', label: 'Company Address', type: 'text', colSpan: 3 },
          { key: 'inception_date', label: 'Inception Date', type: 'date' },
          { key: 'expiry_date', label: 'Expiry Date', type: 'date' },
          { key: 'today_date', label: "Today's Date", type: 'date' },
          { key: 'new_altered', label: 'New / Altered', type: 'select', options: [
            { value: 'New', label: 'New' },
            { value: 'Altered', label: 'Altered' },
          ]},
          { key: 'period_of_insurance', label: 'Period of Insurance', type: 'select', options: POLICY_PERIOD },
          { key: 'is_renewable', label: 'Renewable Policy', type: 'select', options: [
            { value: 'Yes', label: 'Yes – Renewable Policy' },
            { value: 'No', label: 'No' },
          ]},
          { key: 'is_project_specific', label: 'Project Specific Policy', type: 'select', options: [
            { value: 'Yes', label: 'Yes – Project Specific Policy' },
            { value: 'No', label: 'No' },
          ]},
          { key: 'limit_of_liability', label: 'Limit of Liability', type: 'number' },
          { key: 'currency', label: 'Currency', type: 'text' },
          { key: 'premium', label: 'Premium (As agreed)', type: 'number' },
        ],
      },
      {
        title: 'Insuring Clauses',
        fields: [
          {
            key: 'insuring_clauses',
            label: 'Insuring Clauses',
            type: 'json-array',
            colSpan: 3,
            cols: [
              { key: 'section', label: 'Section', type: 'text' },
              { key: 'name', label: 'Insuring Clause', type: 'text' },
              { key: 'included', label: 'Included/Not Included', type: 'select', options: [
                { value: 'Included', label: 'Included' },
                { value: 'Not Included', label: 'Not Included' },
              ]},
              { key: 'limit_of_liability', label: 'Insuring Clause Limit of Liability', type: 'number' },
              { key: 'retention', label: 'Retention', type: 'number' },
            ],
            defaultRows: [
              { section: '1.1', name: 'Side A – Directors & Officers Liability',              included: '', limit_of_liability: '', retention: '' },
              { section: '1.2', name: 'Side B – Organisation Reimbursement',                  included: '', limit_of_liability: '', retention: '' },
              { section: '1.3', name: 'Side C – Organisation Liability for Securities Claims', included: '', limit_of_liability: '', retention: '' },
              { section: '1.4', name: 'Investigations',                                       included: '', limit_of_liability: '', retention: '' },
            ],
          },
        ],
      },
      {
        title: 'Extensions',
        fields: [
          {
            key: 'extensions',
            label: 'Extensions',
            type: 'json-array',
            colSpan: 3,
            cols: [
              { key: 'section', label: 'Section', type: 'text' },
              { key: 'name', label: 'Extension', type: 'text' },
              { key: 'included', label: 'Included/Not Included', type: 'select', options: [
                { value: 'Included', label: 'Included' },
                { value: 'Not Included', label: 'Not Included' },
              ]},
              { key: 'limit', label: 'Additional Limit / Sub Limit of Liability', type: 'text' },
              { key: 'retention', label: 'Retention', type: 'text' },
            ],
            defaultRows: [
              { section: '2.1',  name: 'Additional Dedicated Limit of Liability for Directors & Officers', included: '', limit: '',                     retention: ''    },
              { section: '2.2',  name: 'Complimentary Legal Advice',                                       included: '', limit: 'One hour per enquiry', retention: 'Nil' },
              { section: '2.3',  name: 'Court and Investigation Attendance and Expense',                   included: '', limit: '$500 Per Day',         retention: 'Nil' },
              { section: '2.4',  name: 'Deprivation of Asset Expenses',                                    included: '', limit: '',                     retention: ''    },
              { section: '2.5',  name: 'Derivative Investigation Costs',                                   included: '', limit: '',                     retention: ''    },
              { section: '2.6',  name: 'Emergency Costs',                                                  included: '', limit: '',                     retention: ''    },
              { section: '2.7',  name: 'Extradition Costs',                                                included: '', limit: '',                     retention: ''    },
              { section: '2.8',  name: 'Loss Mitigation',                                                  included: '', limit: '',                     retention: ''    },
              { section: '2.9',  name: 'Prosecution Costs',                                                included: '', limit: '',                     retention: ''    },
              { section: '2.10', name: 'Public Relations and Reputation Expenses',                        included: '', limit: '',                     retention: ''    },
              { section: '2.11', name: 'Work, Health and Safety Costs',                                   included: '', limit: '',                     retention: ''    },
            ],
          },
        ],
      },
      {
        title: 'Coverage Extensions',
        fields: [
          {
            key: 'coverage_extensions',
            label: 'Coverage Extensions',
            type: 'json-array',
            colSpan: 3,
            cols: [
              { key: 'section', label: 'Section', type: 'text' },
              { key: 'name', label: 'Extension', type: 'text' },
              { key: 'included', label: 'Included/Not Included', type: 'select', options: [
                { value: 'Included', label: 'Included' },
                { value: 'Not Included', label: 'Not Included' },
              ]},
            ],
            defaultRows: [
              { section: '3.1',  name: 'Automatic Cover for New Subsidiaries',           included: '' },
              { section: '3.2',  name: 'Backdated Continuity of Cover',                  included: '' },
              { section: '3.3',  name: 'Fines & Penalties',                              included: '' },
              { section: '3.4',  name: 'Continuity of Cover',                            included: '' },
              { section: '3.5',  name: 'Extended Discovery',                             included: '' },
              { section: '3.6',  name: 'Lifetime Cover for Retired Insured Persons',     included: '' },
              { section: '3.7',  name: 'Outside Directorship Liability',                 included: '' },
              { section: '3.8',  name: 'Personal Taxation and Superannuation Liability', included: '' },
              { section: '3.9',  name: 'Run Off Cover for Prior Subsidiaries',           included: '' },
              { section: '3.10', name: 'Transaction Run-Off',                            included: '' },
            ],
          },
        ],
      },
      {
        title: 'Previous Insurance Details',
        fields: [
          { key: 'previous_insurer_name', label: 'Name of Insurer', type: 'text' },
          { key: 'previous_policy_type', label: 'Type of Policy', type: 'text' },
          { key: 'previous_policyholder', label: 'Policyholder', type: 'text' },
          { key: 'previous_policy_number', label: 'Policy Number', type: 'text' },
          { key: 'previous_policy_period', label: 'Policy Period', type: 'text' },
          { key: 'backdated_continuity_date', label: 'Backdated Continuity Date', type: 'date' },
          { key: 'jurisdictional_cover', label: 'Jurisdictional Cover', type: 'text', colSpan: 2 },
        ],
      },
      {
        title: 'Additional Notes',
        fields: [
          { key: 'notes', label: 'Notes', type: 'textarea', colSpan: 3 },
        ],
      },
    ],
  },
  'medical-evacuation': {
    label: 'Medical Evacuation',
    slug: 'medical-evacuation',
    sections: [
      {
        title: 'Medical Evacuation Insurance – Policy Schedule',
        fields: [
          { key: 'policy_number', label: 'Policy No.', type: 'text' },
          { key: 'company_name', label: 'Insured Name', type: 'text', colSpan: 2, readonly: true },
          { key: 'company_address', label: 'Company Address', type: 'text', colSpan: 3, readonly: true },
          { key: 'inception_date', label: 'Period of Cover (From)', type: 'date', readonly: true },
          { key: 'expiry_date', label: 'Period of Cover (To)', type: 'date', readonly: true },
          { key: 'today_date', label: "Today's Date", type: 'date' },
          // Fixed for this schedule — the PDF hardcodes the same value
          // (medical_evacuation_pdf.blade.php). Display-only, see the
          // medical-evacuation effect below.
          { key: 'class_of_business', label: 'Class of Business', type: 'text', readonly: true },
          { key: 'type_of_cover', label: 'Type of Cover', type: 'text' },
          { key: 'original_insured_scheme', label: 'Original Insured / Scheme', type: 'text', colSpan: 2 },
          { key: 'territorial_limit', label: 'Territorial Limit', type: 'text' },
          { key: 'sum_insured', label: 'Sum Insured / Limit per Event', type: 'number' },
          { key: 'aggregate_limit', label: 'Aggregate Limit', type: 'number' },
          { key: 'broker', label: 'Broker', type: 'text' },
          // Currency, Gross Written Premium (BWP) and Renewable Policy were
          // removed from the schedule. The premium column is still written —
          // it is derived from the Description of Cover rows below.
        ],
      },
      {
        title: 'Description of Cover',
        fields: [
          {
            key: 'description_items',
            label: 'Description of Cover',
            type: 'json-array',
            colSpan: 3,
            cols: [
              { key: 'description', label: 'Description', type: 'text' },
              { key: 'limit', label: 'Limit', type: 'number' },
              { key: 'premium', label: 'Premium', type: 'number' },
            ],
          },
        ],
      },
      {
        title: 'Extensions',
        fields: [
          {
            key: 'extension_items',
            label: 'Extensions',
            type: 'json-array',
            colSpan: 3,
            cols: [
              { key: 'name', label: 'Extension', type: 'text' },
              { key: 'sub_limit', label: 'Sub Limit', type: 'number' },
            ],
          },
        ],
      },
      {
        title: 'Notes',
        afterPolicyWording: true,
        fields: [
          { key: 'notes', label: 'Notes', type: 'textarea', colSpan: 3 },
        ],
      },
    ],
  },
  'commercial-crime': {
    label: 'Commercial Crime',
    slug: 'commercial-crime',
    sections: [
      {
        title: 'Commercial Crime Insurance – Policy Schedule',
        fields: [
          { key: 'policy_number', label: 'Policy No.', type: 'text' },
          { key: 'company_name', label: 'Insured Name', type: 'text', colSpan: 2, readonly: true },
          { key: 'company_address', label: 'Company Address', type: 'text', colSpan: 3, readonly: true },
          { key: 'inception_date', label: 'Period of Cover (From)', type: 'date', readonly: true },
          { key: 'expiry_date', label: 'Period of Cover (To)', type: 'date', readonly: true },
          { key: 'today_date', label: "Today's Date", type: 'date' },
          // Fixed for this schedule — the PDF hardcodes the same value
          // (commercial_crime_pdf.blade.php). Display-only, see
          // FIXED_CLASS_OF_BUSINESS.
          { key: 'class_of_business', label: 'Class of Business', type: 'text', readonly: true },
          { key: 'cover_type', label: 'Cover Type', type: 'text' },
          { key: 'industry_sector', label: 'Industry / Sector', type: 'text' },
          { key: 'coverage_basis', label: 'Coverage Basis', type: 'text' },
          { key: 'retroactive_date', label: 'Retroactive Date', type: 'date' },
          { key: 'total_limit', label: 'Total Limit (BWP)', type: 'number' },
          { key: 'annual_aggregate_limit', label: 'Annual Aggregate Limit (BWP)', type: 'number' },
          { key: 'excess', label: 'Excess (BWP)', type: 'number' },
          { key: 'broker', label: 'Broker', type: 'text' },
          // Currency and Insured / Insured Group removed from this schedule.
          { key: 'premium', label: 'Gross Written Premium (BWP)', type: 'number' },
          { key: 'is_renewable', label: 'Renewable Policy', type: 'select', options: [
            { value: 'Yes', label: 'Yes – Renewable Policy' },
            { value: 'No', label: 'No' },
          ]},
        ],
      },
      {
        title: 'Section A – Insuring Clauses & Sub-Limits',
        fields: [
          {
            key: 'insuring_clauses',
            label: 'Insuring Clauses & Sub-Limits',
            type: 'json-array',
            colSpan: 3,
            // lockedRowCount instead of locked: the 7 seeded clauses stay
            // read-only and non-removable, but operators can append their own
            // clauses below — those rows get an editable Insuring Clause name
            // and an ✕ to remove.
            lockedRowCount: 7,
            addButtonLabel: 'Add More',
            cols: [
              { key: 'clause', label: 'Insuring Clause', type: 'text', readonly: true },
              { key: 'sub_limit', label: 'Sub-Limit (BWP)', type: 'number' },
              { key: 'aggregate', label: 'Aggregate?', type: 'select', options: [
                { value: '', label: '—' },
                { value: 'Y', label: 'Y' },
                { value: 'N', label: 'N' },
              ]},
              { key: 'comments', label: 'Comments / Conditions', type: 'text' },
            ],
            defaultRows: [
              { clause: 'Employee Dishonesty / Fidelity', sub_limit: '', aggregate: '', comments: '' },
              { clause: 'Theft of Money & Securities (on premises)', sub_limit: '', aggregate: '', comments: '' },
              { clause: 'Theft of Money & Securities (in transit)', sub_limit: '', aggregate: '', comments: '' },
              { clause: 'Forgery or Alteration', sub_limit: '', aggregate: '', comments: '' },
              { clause: 'Computer Fraud / Funds Transfer Fraud', sub_limit: '', aggregate: '', comments: '' },
              { clause: 'Social Engineering Fraud', sub_limit: '', aggregate: '', comments: '' },
              { clause: 'Client / Third-Party Property', sub_limit: '', aggregate: '', comments: '' },
            ],
          },
        ],
      },
      {
        title: 'Section B – Layered Excess of Loss Structure',
        fields: [
          {
            key: 'excess_layers',
            label: 'Layered Excess of Loss Structure',
            type: 'json-array',
            colSpan: 2,
            cols: [
              { key: 'layer', label: 'Layer', type: 'text' },
              { key: 'layer_limit', label: 'Layer Limit (BWP)', type: 'number' },
            ],
            defaultRows: [
              { layer: 'Primary', layer_limit: '' },
              { layer: '1st Excess', layer_limit: '' },
              { layer: '2nd Excess', layer_limit: '' },
              { layer: '3rd Excess', layer_limit: '' },
              { layer: '4th Excess', layer_limit: '' },
              { layer: 'Top Layer', layer_limit: '' },
            ],
          },
        ],
      },
      {
        title: 'Section C – Endorsements and Extensions',
        fields: [
          {
            key: 'endorsements_extensions',
            label: 'Endorsements and Extensions',
            type: 'json-array',
            colSpan: 3,
            cols: [
              { key: 'insuring_clause', label: 'Insuring Clause', type: 'text' },
              { key: 'sub_limit', label: 'Sub-Limit (BWP)', type: 'number' },
            ],
          },
        ],
      },
      {
        title: 'Notes',
        afterPolicyWording: true,
        fields: [
          { key: 'notes', label: 'Notes', type: 'textarea', colSpan: 3 },
        ],
      },
    ],
  },
  'environmental-liability': {
    label: 'Environmental Liability',
    slug: 'environmental-liability',
    sections: [
      {
        title: 'Environmental Liability Insurance – Named Insured & Contact Details',
        fields: [
          { key: 'policy_number', label: 'Policy Number', type: 'text' },
          { key: 'company_name', label: 'Named Insured', type: 'text', colSpan: 2, readonly: true },
          { key: 'company_address', label: 'Postal / Physical Address', type: 'text', colSpan: 3, readonly: true },
          { key: 'nature_of_business', label: 'Nature of Business', type: 'text', colSpan: 2 },
          { key: 'contact_person', label: 'Contact Person', type: 'text' },
          { key: 'telephone', label: 'Telephone', type: 'text' },
          { key: 'email', label: 'Email', type: 'text', colSpan: 2 },
        ],
      },
      {
        title: 'Period of Insurance',
        fields: [
          { key: 'inception_date', label: 'Policy Inception Date', type: 'date', readonly: true },
          { key: 'expiry_date', label: 'Policy Expiry Date', type: 'date', readonly: true },
          { key: 'today_date', label: "Today's Date", type: 'date' },
          { key: 'retroactive_date', label: 'Retroactive Date', type: 'date' },
          { key: 'basis_of_cover', label: 'Basis of Cover', type: 'select', options: [
            { value: 'Claims Made', label: 'Claims Made' },
            { value: 'Claims Occurring', label: 'Claims Occurring' },
          ]},
          { key: 'policy_duration', label: 'Policy Duration', type: 'text' },
          { key: 'is_renewable', label: 'Renewable', type: 'select', options: [
            { value: 'Yes', label: 'Yes – Renewable Policy' },
            { value: 'No', label: 'No' },
          ]},
          { key: 'currency', label: 'Currency', type: 'select', options: [
            { value: 'BWP', label: 'BWP' },
            { value: 'USD', label: 'USD' },
            { value: 'ZAR', label: 'ZAR' },
            { value: 'GBP', label: 'GBP' },
          ]},
          { key: 'broker', label: 'Broker', type: 'text' },
        ],
      },
      {
        title: 'Insured Locations / Scheduled Sites',
        fields: [
          {
            key: 'sites',
            label: 'Insured Locations / Scheduled Sites',
            type: 'json-array',
            colSpan: 3,
            cols: [
              { key: 'site_no', label: 'Site No.', type: 'text' },
              { key: 'site_name', label: 'Site Name', type: 'text' },
              { key: 'physical_address', label: 'Physical Address', type: 'text' },
              { key: 'nature_of_operations', label: 'Nature of Operations at Site', type: 'text' },
            ],
          },
        ],
      },
      {
        title: 'Coverage Sections & Limits of Indemnity',
        fields: [
          {
            key: 'coverage_sections',
            label: 'Coverage Sections & Limits of Indemnity',
            type: 'json-array',
            colSpan: 3,
            // The 11 standard coverage sections stay a fixed catalog (Coverage
            // Section description locked to its seeded name); rows added past
            // that via "Add Row" are fully free-form, mirroring bond_schedule.
            lockedRowCount: 11,
            cols: [
              { key: 'description', label: 'Coverage Section', type: 'text', readonly: true },
              { key: 'included', label: 'Included', type: 'select', options: [
                { value: 'Yes', label: 'Yes' },
                { value: 'No', label: 'No' },
                { value: 'Optional', label: 'Optional' },
              ]},
              { key: 'limit_of_indemnity', label: 'Limit of Indemnity (BWP)', type: 'number' },
              { key: 'sub_limit', label: 'Sub-Limit (BWP)', type: 'number' },
            ],
            defaultRows: [
              { description: 'A. Third-Party Bodily Injury (arising from pollution conditions)', included: 'Yes', limit_of_indemnity: '', sub_limit: '' },
              { description: 'B. Third-Party Property Damage (arising from pollution conditions)', included: 'Yes', limit_of_indemnity: '', sub_limit: '' },
              { description: 'C. On-Site Remediation / Clean-Up Costs (statutory regulatory requirements)', included: 'Yes', limit_of_indemnity: '', sub_limit: '' },
              { description: 'D. Off-Site Remediation / Clean-Up Costs (migration of pollutants beyond insured site)', included: 'Yes', limit_of_indemnity: '', sub_limit: '' },
              { description: 'E. Emergency Response Costs', included: 'Yes', limit_of_indemnity: '', sub_limit: '' },
              { description: 'F. Legal Defence Costs & Expenses', included: 'Yes', limit_of_indemnity: '', sub_limit: '' },
              { description: 'G. Historic / Legacy Pollution Conditions', included: 'Yes', limit_of_indemnity: '', sub_limit: '' },
              { description: 'H. Transportation Pollution Liability (in transit to/from insured site)', included: 'Yes', limit_of_indemnity: '', sub_limit: '' },
              { description: 'I. Business Interruption (first-party, arising from pollution event)', included: 'Optional', limit_of_indemnity: '', sub_limit: '' },
              { description: 'J. Environmental Damage — Biodiversity / Natural Resource Damage (EU ELD-equivalent)', included: 'Yes', limit_of_indemnity: '', sub_limit: '' },
              { description: 'K. Contractors Pollution Liability (operations at third-party/customer sites)', included: 'Yes', limit_of_indemnity: '', sub_limit: '' },
            ],
          },
          { key: 'overall_annual_aggregate_limit', label: 'Overall Annual Aggregate Limit (BWP)', type: 'number', colSpan: 3 },
        ],
      },
      {
        title: 'Deductible / Self-Insured Retention (SIR)',
        fields: [
          {
            key: 'deductibles',
            label: 'Deductible / Self-Insured Retention (SIR)',
            type: 'json-array',
            colSpan: 2,
            // The 5 standard categories stay a fixed catalog; rows added past
            // that via "Add Row" are fully free-form, mirroring bond_schedule.
            lockedRowCount: 5,
            cols: [
              { key: 'coverage_section', label: 'Coverage Section', type: 'text', readonly: true },
              { key: 'deductible', label: 'Deductible (BWP)', type: 'number' },
            ],
            defaultRows: [
              { coverage_section: 'Third-Party Bodily Injury & Property Damage', deductible: '' },
              { coverage_section: 'Clean-Up / Remediation Costs', deductible: '' },
              { coverage_section: 'Emergency Response Costs', deductible: '' },
              { coverage_section: 'Legal Defence Costs', deductible: '' },
              { coverage_section: 'Business Interruption', deductible: '' },
            ],
          },
        ],
      },
      {
        title: 'Premium',
        fields: [
          { key: 'premium', label: 'Annual Premium (excl. taxes/levies) (BWP)', type: 'number' },
          { key: 'tax_levies', label: 'Applicable Taxes / Levies (BWP)', type: 'number' },
          { key: 'total_premium_payable', label: 'Total Premium Payable (BWP)', type: 'number' },
          { key: 'premium_payment_terms', label: 'Premium Payment Terms', type: 'select', options: [
            { value: 'Annual', label: 'Annual' },
            { value: 'Quarterly', label: 'Quarterly' },
            { value: 'Monthly', label: 'Monthly' },
          ]},
          { key: 'premium_due_date', label: 'Due Date', type: 'date' },
        ],
      },
      {
        title: 'Pollutants Covered',
        fields: [
          {
            key: 'pollutants_covered',
            label: 'Pollutants Covered',
            type: 'json-array',
            colSpan: 2,
            cols: [
              { key: 'pollutant', label: 'Pollutant', type: 'text' },
              { key: 'included', label: 'Covered', type: 'select', options: [
                { value: 'Yes', label: 'Yes' },
                { value: 'No', label: 'No' },
              ]},
            ],
            defaultRows: [
              { pollutant: 'Hazardous chemicals, solvents, and compounds', included: 'Yes' },
              { pollutant: 'Petroleum products and hydrocarbons', included: 'Yes' },
              { pollutant: 'Heavy metals (lead, mercury, arsenic, chromium, etc.)', included: 'Yes' },
              { pollutant: 'Asbestos-containing materials', included: 'Yes' },
              { pollutant: 'Biological agents including Legionella and mould', included: 'Yes' },
              { pollutant: 'Waste materials (solid, liquid, gaseous)', included: 'Yes' },
              { pollutant: 'Pesticides and herbicides', included: 'Yes' },
              { pollutant: 'Any other pollutant as defined in the policy wording', included: 'Yes' },
            ],
          },
        ],
      },
      {
        title: 'Key Exclusions (Summary)',
        fields: [
          {
            key: 'key_exclusions',
            label: 'Key Exclusions',
            type: 'json-array',
            colSpan: 2,
            // The 8 standard exclusions stay a fixed catalog; rows added past
            // that via "Add Row" are fully free-form, mirroring bond_schedule.
            lockedRowCount: 8,
            cols: [
              { key: 'exclusion', label: 'Exclusion', type: 'text', readonly: true },
            ],
            defaultRows: [
              { exclusion: 'Pollution conditions known to the insured prior to the retroactive date' },
              { exclusion: "Bodily injury to employees of the insured (covered under Employers' Liability)" },
              { exclusion: 'War, invasion, terrorism, or nuclear / radioactive contamination' },
              { exclusion: 'Wilful, deliberate, or intentional acts of pollution by the insured' },
              { exclusion: 'Fines, penalties, and punitive damages' },
              { exclusion: 'Development / change-of-use cost overruns' },
              { exclusion: 'Asbestos removal unless specifically endorsed' },
              { exclusion: "Damage to the insured's own property (unless first-party clean-up is included)" },
            ],
          },
        ],
      },
      {
        title: 'Endorsements & Special Conditions',
        fields: [
          {
            key: 'endorsements',
            label: 'Endorsements & Special Conditions',
            type: 'json-array',
            colSpan: 3,
            cols: [
              { key: 'no', label: 'No.', type: 'text' },
              { key: 'title', label: 'Endorsement Title', type: 'text' },
              { key: 'effect', label: 'Effect / Detail', type: 'text' },
            ],
            defaultRows: [
              { no: '001', title: 'Additional Insured Endorsement', effect: '' },
              { no: '002', title: 'Waiver of Subrogation', effect: '' },
              { no: '003', title: 'Cross Liability Clause', effect: 'Each insured treated as a separate insured' },
              { no: '004', title: 'Site-Specific Environmental Assessment Required', effect: '' },
            ],
          },
        ],
      },
      {
        title: 'Notes',
        afterPolicyWording: true,
        fields: [
          { key: 'notes', label: 'Notes', type: 'textarea', colSpan: 3 },
        ],
      },
    ],
  },
  bonds: {
    label: 'Bonds and Guarantees',
    slug: 'bonds',
    sections: [
      {
        title: 'Bonds Insurance – Section 1: Policy Details',
        fields: [
          { key: 'policy_number', label: 'Policy No.', type: 'text' },
          { key: 'company_name', label: 'Insured / Principal', type: 'text', colSpan: 2, readonly: true },
          { key: 'company_address', label: 'Postal Address', type: 'text', colSpan: 3, readonly: true },
          { key: 'risk_address', label: 'Risk Address', type: 'text', colSpan: 3, readonly: true },
          { key: 'inception_date', label: 'Effective Date', type: 'date', readonly: true },
          { key: 'expiry_date', label: 'Expiry Date', type: 'date', readonly: true },
          { key: 'today_date', label: "Today's Date", type: 'date' },
          { key: 'type_of_bond', label: 'Type of Bond', type: 'text' },
          { key: 'limit_insured', label: 'Limit Insured (BWP)', type: 'number' },
          // Frozen: derived from Section 2's Bond Coverage Schedule (the sum of
          // its Annual Premium column) by the bonds effect below, so the header
          // figure can never disagree with the schedule the operator priced.
          { key: 'premium', label: 'Premium (BWP)', type: 'number', readonly: true,
            readonlyNote: '(sum of the Bond Coverage Schedule)' },
          { key: 'excess', label: 'Excess (BWP)', type: 'number' },
          // Currency, Broker and Renewable Policy removed from this schedule.
          // bonds_coverages.premium is what the Rate button sums for
          // BONDSANDGUARANTEES and what the V2 quote sheet reads.
        ],
      },
      {
        title: 'Section 2 – Bond Coverage Schedule',
        fields: [
          {
            key: 'bond_schedule',
            label: 'Bond Coverage Schedule',
            type: 'json-array',
            colSpan: 3,
            // The 10 standard bond types stay a fixed catalog (Bond Type
            // locked to its seeded name); rows added past that via "Add
            // More" are fully free-form, mirroring the schedule's "Other"
            // line.
            lockedRowCount: 10,
            addButtonLabel: 'Add More',
            groupBreaks: [
              { beforeIndex: 0, label: 'CONTRACT BONDS' },
              { beforeIndex: 6, label: 'COMMERCIAL BONDS' },
            ],
            cols: [
              { key: 'bond_type', label: 'Bond Type', type: 'text', readonly: true },
              { key: 'sum_insured', label: 'Sum Insured (BWP)', type: 'number' },
              { key: 'rate', label: 'Rate (%)', type: 'number' },
              { key: 'annual_premium', label: 'Annual Premium (BWP)', type: 'number' },
              { key: 'principal_contractor', label: 'Principal / Contractor', type: 'text' },
              { key: 'duration', label: 'Duration', type: 'text' },
              { key: 'status', label: 'Status', type: 'select', options: [
                { value: 'Active', label: 'Active' },
                { value: 'Inactive', label: 'Inactive' },
              ]},
            ],
            defaultRows: [
              { bond_type: 'Bid Bond', sum_insured: '', rate: '0.250', annual_premium: '', principal_contractor: '', duration: 'Until Award', status: 'Active' },
              { bond_type: 'Performance Bond', sum_insured: '', rate: '0.500', annual_premium: '', principal_contractor: '', duration: 'Contract Period', status: 'Active' },
              { bond_type: 'Advance Payment Bond', sum_insured: '', rate: '0.400', annual_premium: '', principal_contractor: '', duration: 'Until Recovery', status: 'Active' },
              { bond_type: 'Maintenance / Warranty Bond', sum_insured: '', rate: '0.300', annual_premium: '', principal_contractor: '', duration: '12-24 Months', status: 'Active' },
              { bond_type: 'Labor & Material Payment Bond', sum_insured: '', rate: '0.400', annual_premium: '', principal_contractor: '', duration: 'Contract Period', status: 'Active' },
              { bond_type: 'Retention Bond', sum_insured: '', rate: '0.300', annual_premium: '', principal_contractor: '', duration: 'Defects Period', status: 'Active' },
              { bond_type: 'Customs Bond', sum_insured: '', rate: '0.200', annual_premium: '', principal_contractor: '', duration: 'Annual', status: 'Active' },
              { bond_type: 'Tax Bond', sum_insured: '', rate: '0.200', annual_premium: '', principal_contractor: '', duration: 'Annual', status: 'Active' },
              { bond_type: 'License & Permit Bond', sum_insured: '', rate: '0.150', annual_premium: '', principal_contractor: '', duration: 'Annual', status: 'Active' },
              { bond_type: 'Court / Judicial Bond', sum_insured: '', rate: '0.300', annual_premium: '', principal_contractor: '', duration: 'Per Proceeding', status: 'Active' },
            ],
          },
        ],
      },
      {
        title: 'Section 3 – Key Policy Conditions',
        fields: [
          { key: 'insuring_agreement', label: 'Insuring Agreement', type: 'textarea', colSpan: 3 },
          { key: 'trigger_event', label: 'Trigger Event', type: 'textarea', colSpan: 3 },
          { key: 'subrogation_right', label: 'Subrogation Right', type: 'textarea', colSpan: 3 },
          { key: 'non_cancellable_clause', label: 'Non-Cancellable', type: 'textarea', colSpan: 3 },
          { key: 'collateral_security', label: 'Collateral & Security', type: 'textarea', colSpan: 3 },
          { key: 'exclusions', label: 'Exclusions', type: 'textarea', colSpan: 3 },
          { key: 'dispute_resolution', label: 'Dispute Resolution', type: 'textarea', colSpan: 3 },
        ],
      },
      {
        // The Collateral & Security clause in Section 3 is policy wording and
        // is pre-filled with boilerplate. THIS section is the security actually
        // held, and it is what the issue gate reads — no confirmed collateral,
        // no bond. Backend: Services\Bonds\BondsIssuanceGate.
        title: 'Section 3A – Collateral Held (required before issue)',
        description: 'A bond cannot be issued until collateral is captured here and confirmed by EXCO.',
        collateralGate: true,
        fields: [
          { key: 'collateral_type', label: 'Collateral Type', type: 'select', options: [
            { value: 'Cash Deposit', label: 'Cash Deposit' },
            { value: 'Bank Guarantee', label: 'Bank Guarantee' },
            { value: 'Parental / Corporate Guarantee', label: 'Parental / Corporate Guarantee' },
            { value: 'Cession of Investment', label: 'Cession of Investment' },
            { value: 'Property / Asset Pledge', label: 'Property / Asset Pledge' },
            { value: 'Counter-Indemnity', label: 'Counter-Indemnity' },
            { value: 'Other', label: 'Other' },
          ]},
          { key: 'collateral_value', label: 'Collateral Value (BWP)', type: 'number' },
          { key: 'collateral_reference', label: 'Collateral Reference', type: 'text', placeholder: 'Guarantee no. / receipt / cession ref' },
          { key: 'collateral_expiry_date', label: 'Collateral Expiry Date', type: 'date' },
        ],
      },
      {
        // The proof behind Section 3A. 3A records WHAT security is held; this
        // holds the instrument itself (bank guarantee, cession, deposit
        // receipt) as an uploaded file, per policy AND per action. Anyone may
        // upload it — only EXCO (`bonds-approve`) may approve it,
        // and the policy cannot be issued until they have. Backend:
        // Api\V1\BondsCollateralDocumentController + BondsIssuanceGate.
        title: 'Section 3B – Collateral Document (required before issue)',
        description: 'Anyone may upload the collateral document. Only EXCO can approve it, and the policy cannot be issued until they do.',
        collateralDocs: true,
        fields: [],
      },
      {
        title: 'Section 4 – Extensions/Endorsements',
        fields: [
          {
            key: 'extensions_endorsements',
            label: 'Extensions/Endorsements',
            type: 'json-array',
            colSpan: 3,
            addButtonLabel: 'Add More',
            cols: [
              { key: 'description', label: 'Extension / Endorsement', type: 'text' },
            ],
            defaultRows: [
              { description: '' },
            ],
          },
        ],
      },
      {
        title: 'Notes',
        afterPolicyWording: true,
        fields: [
          { key: 'notes', label: 'Notes', type: 'textarea', colSpan: 3 },
        ],
      },
    ],
  },
}

// ─── Json Array field component ────────────────────────────────────────────────

interface JsonArrayFieldProps {
  cols: JsonColumnDef[]
  rows: Record<string, string>[]
  onChange: (rows: Record<string, string>[]) => void
  locked?: boolean
  addButtonLabel?: string
  /** Rows at/after this index ignore column-level `readonly` — lets a fixed
   *  seeded catalog coexist with free-form rows added via Add Row/Add More. */
  lockedRowCount?: number
  /** Renders a full-width banner row before the row at `beforeIndex`. */
  groupBreaks?: { beforeIndex: number; label: string }[]
}

function JsonArrayField({ cols, rows, onChange, locked = false, addButtonLabel = 'Add Row', lockedRowCount, groupBreaks }: JsonArrayFieldProps) {
  const addRow = () => {
    const blank: Record<string, string> = {}
    cols.forEach(c => { blank[c.key] = c.type === 'select' && c.options?.length ? c.options[0].value : '' })
    onChange([...rows, blank])
  }

  const removeRow = (idx: number) => {
    onChange(rows.filter((_, i) => i !== idx))
  }

  const updateCell = (rowIdx: number, colKey: string, val: string) => {
    const updated = rows.map((r, i) => {
      if (i !== rowIdx) return r
      const next: Record<string, string> = { ...r, [colKey]: val }
      // Auto-calc premium = (sum_insured OR limit_of_indemnity) × rate / 100
      // Runs whenever SI or rate changes. When the user types directly into
      // the premium column and an SI is already set, back-calc the rate
      // instead (mirrors legacy calculate*ItemRateFromPremium functions).
      //
      // The premium column is resolved by name rather than hard-coded to
      // `premium`: the Bonds schedule calls its column `annual_premium`, so
      // while this looked only for `premium` the Bond rows never priced —
      // Sum Insured × Rate produced nothing and Annual Premium stayed 0,
      // which left bonds_coverages.premium (the figure the Rate button and
      // the V2 quote sheet read) at zero too. `premium` is preferred when a
      // schedule happens to carry both, so no existing schedule changes.
      const siKeys = ['sum_insured', 'limit_of_indemnity', 'limit_indemnity', 'annual_sum_insured']
      const premKey = ['premium', 'annual_premium'].find(k => cols.some(c => c.key === k))
      const hasRate = cols.some(c => c.key === 'rate')
      const siKey = siKeys.find(k => cols.some(c => c.key === k))
      const si   = parseFloat((siKey && next[siKey]) || '0')
      const rate = parseFloat(next.rate || '0')
      const prem = parseFloat((premKey && next[premKey]) || '0')

      if (premKey && (siKeys.includes(colKey) || colKey === 'rate')) {
        if (!isNaN(si) && !isNaN(rate) && si > 0 && rate > 0) {
          next[premKey] = String(+(si * rate / 100).toFixed(2))
        }
      }
      if (hasRate && premKey && colKey === premKey && si > 0 && prem > 0) {
        // Reverse calc — user entered premium directly
        next.rate = String(+(prem / si * 100).toFixed(4))
      }
      return next
    })
    onChange(updated)
  }

  const cellCls = 'w-full text-xs border border-gray-200 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-indigo-400'
  const colCount = cols.length + (locked ? 0 : 1)

  return (
    <div className="space-y-2">
      {rows.length > 0 && (
        <div className="overflow-x-auto rounded border border-gray-200">
          <table className="w-full text-sm">
            <thead>
              <tr className="bg-gray-50 border-b">
                {cols.map(c => (
                  <th key={c.key} className="px-3 py-2 text-left text-xs font-medium text-gray-600 whitespace-nowrap">
                    {c.label}
                  </th>
                ))}
                {!locked && <th className="px-2 py-2 w-8" />}
              </tr>
            </thead>
            <tbody className="divide-y">
              {rows.map((row, ri) => {
                const banner = groupBreaks?.find(g => g.beforeIndex === ri)
                // Rows past the seeded catalog (added via Add Row/Add More)
                // ignore column-level `readonly` so their fields — e.g. a
                // free-text Bond Type on an "Other" row — stay editable.
                const rowIgnoresReadonly = lockedRowCount !== undefined && ri >= lockedRowCount
                // Fields without lockedRowCount keep today's behaviour (every
                // row removable when unlocked); fields that DO set it protect
                // the seeded catalog and only allow removing rows added past it.
                const canRemoveRow = lockedRowCount === undefined || ri >= lockedRowCount
                return (
                <Fragment key={ri}>
                  {banner && (
                    <tr key={`banner-${ri}`}>
                      <td colSpan={colCount} className="bg-indigo-600 text-white font-semibold text-xs px-3 py-2">
                        {banner.label}
                      </td>
                    </tr>
                  )}
                  <tr>
                  {cols.map(c => (
                    <td key={c.key} className="px-2 py-1">
                      {c.readonly && !rowIgnoresReadonly ? (
                        <input
                          type="text"
                          value={c.key === '_item_no' ? String(ri + 1) : (row[c.key] ?? '')}
                          readOnly
                          className={`${cellCls} bg-gray-50${c.key === '_item_no' ? ' text-center' : ''}`}
                        />
                      ) : c.type === 'select' ? (
                        <select
                          value={row[c.key] ?? ''}
                          onChange={e => updateCell(ri, c.key, e.target.value)}
                          className={cellCls}
                        >
                          {c.options?.map(o => (
                            <option key={o.value} value={o.value}>{o.label}</option>
                          ))}
                        </select>
                      ) : c.type === 'number' ? (
                        <input
                          type="text"
                          inputMode="decimal"
                          value={formatNumberInput(row[c.key] ?? '')}
                          onChange={e => updateCell(ri, c.key, unformatNumber(e.target.value))}
                          className={cellCls}
                          placeholder="0"
                        />
                      ) : (
                        <input
                          type="text"
                          value={row[c.key] ?? ''}
                          onChange={e => updateCell(ri, c.key, e.target.value)}
                          className={cellCls}
                        />
                      )}
                    </td>
                  ))}
                  {!locked && (
                    <td className="px-2 py-1">
                      {/* The seeded catalog rows (bond_type etc. locked via
                          lockedRowCount) are structural and can't be
                          removed — only rows added via Add Row/Add More. */}
                      {canRemoveRow && (
                        <button
                          type="button"
                          onClick={() => removeRow(ri)}
                          className="text-red-400 hover:text-red-600 text-xs font-bold"
                          title="Remove row"
                        >
                          ✕
                        </button>
                      )}
                    </td>
                  )}
                  </tr>
                </Fragment>
                )
              })}
            </tbody>
          </table>
        </div>
      )}
      {!locked && (
        <button
          type="button"
          onClick={addRow}
          className="text-xs text-indigo-600 hover:text-indigo-800 font-medium flex items-center gap-1"
        >
          <span className="text-base leading-none">+</span> {addButtonLabel}
        </button>
      )}
    </div>
  )
}

// ─── Helper: normalise a DB date value to YYYY-MM-DD for <input type="date"> ──

function normalizeDateValue(val: string): string {
  if (!val) return ''
  // Handles "2026-01-01 00:00:00", "2026-01-01T00:00:00.000Z", "2026-01-01"
  const match = val.match(/^(\d{4}-\d{2}-\d{2})/)
  return match ? match[1] : val
}

// ─── Helper: parse incoming value for JSON array fields ────────────────────────

/**
 * Decode a JSON value coming off a specialist coverage row.
 *
 * The API returns these columns with the raw query builder, so they arrive as
 * strings rather than parsed arrays. Some rows are also DOUBLE-encoded: the
 * legacy Livewire CAR save path json_encode()d a value that Eloquent's 'array'
 * cast then encoded again, storing "[{\"a\":1}]" — a JSON string — instead of
 * [{"a":1}]. Those rows made every CAR items grid render empty even though the
 * data was present, because the old check only accepted strings starting with
 * '['. The CAR schedule PDF guards against the same thing (see
 * contractors_all_risk.blade.php). Unwraps up to 3 levels and returns anything
 * that isn't JSON untouched.
 */
function decodeJsonValue(val: any): any {
  let out = val
  for (let i = 0; i < 3 && typeof out === 'string'; i++) {
    const trimmed = out.trim()
    if (!/^[[{"]/.test(trimmed)) return out
    try {
      out = JSON.parse(trimmed)
    } catch {
      return out
    }
  }
  return out
}

function parseJsonArrayValue(val: any): Record<string, string>[] {
  const decoded = decodeJsonValue(val)
  if (!Array.isArray(decoded)) return []
  return decoded.map(r => {
    const out: Record<string, string> = {}
    // Guard non-object rows — Object.entries(null) throws.
    if (r !== null && typeof r === 'object') {
      Object.entries(r).forEach(([k, v]) => { out[k] = v !== null && v !== undefined ? String(v) : '' })
    }
    return out
  })
}

/** Parse a backend value into a flat string[] for checkbox-list fields. */
function parseStringArrayValue(val: any): string[] {
  const decoded = decodeJsonValue(val)
  return Array.isArray(decoded) ? decoded.map(v => String(v)) : []
}

// ─── Fixed Class of Business per schedule ─────────────────────────────────────

/**
 * Schedules whose Class of Business is a constant, shown read-only on the form
 * rather than retyped. Values must match what the corresponding PDF hardcodes
 * (medical_evacuation_pdf / commercial_crime_pdf blade templates).
 *
 * Display-only: none of these tables has a class_of_business column, and
 * SpecialistCoverageController::buildPayload does $request->only($spec['fields']),
 * so the key is dropped on save. Adding an entry here needs no migration — but
 * the form field itself still has to be declared in FORM_CONFIGS above.
 */
const FIXED_CLASS_OF_BUSINESS: Record<string, string> = {
  'medical-evacuation': 'Medical Evacuation',
  'commercial-crime': 'Commercial Crime',
}

// ─── Bonds and Guarantees: Section 3 standard wording ─────────────────────────

/**
 * Standard Key Policy Conditions wording for a surety bond schedule.
 *
 * Applied on CREATE only (see the prefill effect), and each field falls back to
 * whatever is already there — so an underwriter's edit is never overwritten and
 * an existing record is never rewritten. The fields stay ordinary editable
 * textareas; this is a starting point, not a locked catalog.
 *
 * The Bonds PDF (bonds_pdf.blade.php, Section 3) renders each row only when its
 * column is non-empty, so before this every new bond printed the section blank.
 */
const BONDS_KEY_POLICY_CONDITIONS: Record<string, string> = {
  insuring_agreement:
    "The Insurer indemnifies the Beneficiary against loss from the Principal's failure to fulfil obligations under the Underlying Contract, subject to policy terms.",
  trigger_event:
    'Loss triggered when Principal: (a) fails to perform contractual obligations; (b) becomes insolvent/enters administration; or (c) abandons the contract.',
  subrogation_right:
    'Upon payment of a claim the Insurer acquires all rights to recover the loss from the Principal. Principal must execute an indemnity agreement prior to bond issuance.',
  non_cancellable_clause:
    'Surety bonds are generally non-cancellable until the underlying obligation is fulfilled. Non-payment of premium does not release the Surety from its obligations to the Beneficiary.',
  collateral_security:
    'The Insurer may require the Principal to provide collateral (cash, assets, parental guarantee) prior to bond issuance to enhance recovery prospects.',
  exclusions:
    'Financial guarantees / credit enhancement | Consumer credit risk products | Asset valuation / market risk / output guarantees | War & political violence (unless endorsed) | Fraud by Beneficiary.',
  dispute_resolution:
    'Arbitration in accordance with the agreed arbitration clause. Governing law as specified in the Policy Schedule.',
}

// ─── Parent-coverage recovery ─────────────────────────────────────────────────

/**
 * s_CoverageCode aliases per specialist type. Used ONLY to recover the parent
 * policy_coverage when neither the URL nor the loaded record supplies
 * policy_coverage_id — which happens for every schedule opened through the
 * Policy Actions tab's "Open … Schedule" button before a record exists
 * (PolicyDetailPage.tsx passes no params on that link, so the created row has
 * policy_coverage_id NULL).
 *
 * Keep in sync with SpecialistCoverageController::TABLE_COVERAGE_CODES, which
 * does the same recovery server-side on save. Alias sets mirror
 * PolicyCreateController::singletonCoverageFamilies() and
 * PolicyCoverage::ONE_PER_ADDRESS_COVERAGE_GROUPS.
 */
const TYPE_COVERAGE_CODES: Record<string, string[]> = {
  'ear':                       ['ERECTIONALLRISKS', 'EAR'],
  'car':                       ['CONTRACTORSALLRISKS', 'CAR'],
  'par':                       ['PLANTALLRISKS', 'PAR'],
  'machinery-breakdown':       ['MACHINERYBREAKDOWN'],
  'medical-malpractice':       ['MEDICAMALPRACTICEINSURANCE', 'MEDICALMALPRACTICE', 'MM'],
  'professional-indemnity':    ['PROFESSIONALINDEMNITY', 'PROFESSIONAL_INDEMNITY', 'PI'],
  'travel-insurance':          ['TRAVELINSURANCE', 'TRAVEL'],
  'marine-cargo-open':         ['MARINEOPENCOVER'],
  'marine-cargo-once-off':     ['MARINEONCEOFFCOVER', 'MARINECARGOONCEOFF'],
  'marine-directors-officers': ['MARINEDIRECTORSOFFICERS', 'DIRECTORSOFFICERSLIABILITY'],
  'medical-evacuation':        ['MEDICALEVACUATION'],
  'commercial-crime':          ['COMMERCIALCRIME'],
  'environmental-liability':   ['ENVIRONMENTALLIABILITY'],
  'bonds':                     ['BONDSANDGUARANTEES'],
}

// ─── Main Page Component ───────────────────────────────────────────────────────

export default function SpecialistCoveragePage() {
  const { id: policyIdStr, type = '' } = useParams<{ id: string; type: string }>()
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const policyId = Number(policyIdStr)

  const recordIdParam = searchParams.get('recordId')
  const policyCoverageIdParam = searchParams.get('policy_coverage_id')
  const policyCoverageId = policyCoverageIdParam ? Number(policyCoverageIdParam) : null
  const [resolvedRecordId, setResolvedRecordId] = useState<number | null>(null)
  const recordId = recordIdParam ? Number(recordIdParam) : resolvedRecordId

  const config = FORM_CONFIGS[type]

  // Collect all JSON-array field keys for this type
  const jsonKeys = new Set<string>()
  const checkboxListKeys = new Set<string>()
  config?.sections.forEach(s => s.fields.forEach(f => {
    if (f.type === 'json-array') jsonKeys.add(f.key)
    if (f.type === 'checkbox-list') checkboxListKeys.add(f.key)
  }))

  // form state: scalar fields + json-array fields + flat-array fields separately
  const [scalars, setScalars] = useState<Record<string, string>>({})
  const [jsonArrays, setJsonArrays] = useState<Record<string, Record<string, string>[]>>({})
  const [arrayScalars, setArrayScalars] = useState<Record<string, string[]>>({})
  const [loading, setLoading] = useState(false)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [approvalModal, setApprovalModal] = useState<{ pendingValue: string } | null>(null)
  const [approvingPeriod, setApprovingPeriod] = useState(false)
  const [periodApproved, setPeriodApproved] = useState<{ by: string; at: string } | null>(null)
  // Bonds collateral confirmation — the second half of the issue gate.
  const [confirmingCollateral, setConfirmingCollateral] = useState(false)
  // Bonds collateral DOCUMENTS — the proof behind the confirmation. Uploaded
  // by anyone, approved only by EXCO (`bonds-approve`), and the
  // policy cannot be issued until one is approved (BondsIssuanceGate).
  const [collateralDocs, setCollateralDocs] = useState<CollateralDoc[]>([])
  const [collateralDocsLoading, setCollateralDocsLoading] = useState(false)
  const [collateralDocsError, setCollateralDocsError] = useState('')
  const [collateralUploading, setCollateralUploading] = useState(false)
  const [collateralActingId, setCollateralActingId] = useState<number | null>(null)
  // Server-side answer to "may this user approve?" — the same
  // BondsIssuanceGate::userMayApprove() the approve endpoint enforces, so the
  // buttons and the API can never disagree. Falls back to the cached
  // permission list until the first fetch lands.
  const [mayApproveCollateral, setMayApproveCollateral] = useState<boolean>(() => {
    try { return (JSON.parse(localStorage.getItem('user_permissions') || '[]') as string[]).includes('bonds-approve') } catch { return false }
  })

  // The parent policy_coverage this schedule belongs to, whether or not the URL
  // says so. Only the Added Coverages list links here with
  // ?policy_coverage_id=N — the Policy Actions tab's specialist records list
  // links with ?recordId=N (PolicyDetailPage.tsx), and handleSubmit navigates to
  // that same shape after a create. So on most edits policyCoverageId is null
  // and anything keyed on it silently disappears — that's why Miscellaneous
  // Items didn't render when opening a saved Medical Evacuation schedule from
  // Policy Actions. The loaded record carries policy_coverage_id (show() does a
  // SELECT *, and the column is in every TYPE_MAP fields list), so fall back to
  // it.
  //
  // Third fallback (matchedCoverageId, resolved by coverage code below): records
  // created through the Policy Actions tab's "Open … Schedule" button were saved
  // with policy_coverage_id NULL, because that link passes no params before a
  // record exists. Those rows can't self-identify, so match the policy's own
  // coverage of this type instead.
  //
  // Render-only: the effects below deliberately keep using the URL param, which
  // is what distinguishes "arrived from a coverage" from "editing a record".
  const [matchedCoverageId, setMatchedCoverageId] = useState<number | null>(null)
  const recordCoverageId = scalars.policy_coverage_id ? Number(scalars.policy_coverage_id) : null
  const effectivePolicyCoverageId = policyCoverageId ?? recordCoverageId ?? matchedCoverageId
  // Track how many endorsement slots are revealed per section (Sonali "+Add Endorsement")
  const [visibleEndorsements, setVisibleEndorsements] = useState<Record<string, number>>({})
  // UAT 2026-05-29: bump to retry the load-record effect when the user
  // hits "Try again" on a failed fetch. Keeps the existing useEffect
  // shape intact instead of a useCallback refactor across 50+ lines.
  const [loadNonce, setLoadNonce] = useState(0)

  const apiBase = `${(import.meta as any).env.VITE_API_URL}/api/v1`
  const token = localStorage.getItem('sanctum_token')

  // ── Auto-sum maps: for each total field, which json-array + cols to sum ──
  // When the user enters data in section items, update the corresponding total
  const TOTAL_MAPPINGS: Record<string, { arrayKey: string; col: string }> = {
    // Sum Insured / Limit of Indemnity totals
    'section1_total_sum_insured': { arrayKey: 'section1_items', col: 'sum_insured' },
    'section1_total_premium':     { arrayKey: 'section1_items', col: 'premium' },
    'section2_total_limit':       { arrayKey: 'section2_items', col: 'limit_of_indemnity' },
    'section2_total_premium':     { arrayKey: 'section2_items', col: 'premium' },
    'section3_total_limit':       { arrayKey: 'section3_items', col: 'limit_of_indemnity' },
    'section3_total_premium':     { arrayKey: 'section3_items', col: 'premium' },
    'section3_total_sum_insured': { arrayKey: 'section3_items', col: 'sum_insured' },
    // Machinery Breakdown's fourth priced block. Display-only like the CAR
    // plant list below (no DB column; buildPayload's $request->only() drops it)
    // — it exists so the Machinery Listing's contribution to the cover premium
    // is visible, and it feeds the Premium cascade further down.
    'machinery_listing_total_premium': { arrayKey: 'machinery_listing', col: 'premium' },
    // CAR Section 1 – List of Plant: read-only totals computed from the
    // plant_list_items rows. Not persisted (no DB column / not in backend
    // allowed-list); rederived on every load via the effect below.
    'plant_list_total_sum_insured': { arrayKey: 'plant_list_items', col: 'sum_insured' },
    'plant_list_total_premium':     { arrayKey: 'plant_list_items', col: 'premium' },
  }

  // Recompute totals whenever jsonArrays change
  useEffect(() => {
    const updates: Record<string, string> = {}
    Object.entries(TOTAL_MAPPINGS).forEach(([totalKey, { arrayKey, col }]) => {
      const rows = jsonArrays[arrayKey]
      if (!rows) return
      const sum = rows.reduce((acc, row) => {
        const v = parseFloat(String(row[col] ?? '').replace(/,/g, '')) || 0
        return acc + v
      }, 0)
      updates[totalKey] = sum > 0 ? sum.toFixed(2) : ''
    })
    if (Object.keys(updates).length > 0) {
      setScalars(prev => ({ ...prev, ...updates }))
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [jsonArrays])

  // EAR-only: cascade Total Premium = Section 1 Total Premium + Section 3
  // Total Premium. Driven off scalars so it picks up changes from the
  // section item rows (which feed section1_total_premium / section3_total_premium
  // via the TOTAL_MAPPINGS effect above) and from manual edits.
  useEffect(() => {
    if (type !== 'ear') return
    const s1 = parseFloat(String(scalars.section1_total_premium ?? '').replace(/,/g, '')) || 0
    const s3 = parseFloat(String(scalars.section3_total_premium ?? '').replace(/,/g, '')) || 0
    const total = s1 + s3
    const next = total > 0 ? total.toFixed(2) : ''
    setScalars(prev => prev.total_premium === next ? prev : { ...prev, total_premium: next })
  }, [type, scalars.section1_total_premium, scalars.section3_total_premium])

  // Machinery-Breakdown-only: cascade Premium = Section 1 + Machinery Listing +
  // Section 2 + Section 3 total premiums. Same shape as the EAR cascade above,
  // but it targets `premium` because that scalar IS machinery_breakdown_
  // coverages' only premium column — the Rate banner's specialist bucket, the
  // V2 quote sheet's Index of Sections and the engineering Policy Document all
  // read it, and none of them look at the section rows. While it was a
  // hand-typed box an operator could price all four sections and still leave
  // it blank, which rated the whole cover at P 0.00.
  //
  // Blank total leaves `premium` untouched rather than clearing it: records
  // captured before this change may hold a hand-entered figure with no section
  // premiums behind it, and wiping it on mere page load would destroy premium
  // nobody asked us to touch. Once any section is priced the computed value
  // takes over. The same rule is enforced server-side in
  // SpecialistCoverageController::buildPayload.
  useEffect(() => {
    if (type !== 'machinery-breakdown') return
    const part = (k: string) => parseFloat(String(scalars[k] ?? '').replace(/,/g, '')) || 0
    const total = part('section1_total_premium')
      + part('machinery_listing_total_premium')
      + part('section2_total_premium')
      + part('section3_total_premium')
    if (total <= 0) return
    const next = total.toFixed(2)
    setScalars(prev => prev.premium === next ? prev : { ...prev, premium: next })
  }, [
    type,
    scalars.section1_total_premium,
    scalars.machinery_listing_total_premium,
    scalars.section2_total_premium,
    scalars.section3_total_premium,
  ])

  // Recover the parent policy_coverage for orphaned records — see
  // effectivePolicyCoverageId. Matches on the coverage CODE, and only accepts an
  // unambiguous single hit: these covers are one-per-address, but a policy can
  // legitimately carry the same cover on two risk addresses, and guessing wrong
  // would attach Miscellaneous Items to the wrong coverage. Two hits leaves the
  // section hidden, exactly as before.
  useEffect(() => {
    if (policyCoverageId || recordCoverageId) { setMatchedCoverageId(null); return }
    const codes = TYPE_COVERAGE_CODES[type]
    if (!codes || !policyId) return
    let cancelled = false
    fetch(`${apiBase}/policies/${policyId}/coverages`, {
      headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
    })
      .then(r => r.ok ? r.json() : null)
      .then(res => {
        if (cancelled) return
        const list = Array.isArray(res?.data) ? res.data : []
        const hits = list.filter((c: any) => codes.includes(String(c.coverageCode ?? '').toUpperCase()))
        setMatchedCoverageId(hits.length === 1 ? Number(hits[0].id) : null)
      })
      .catch(() => {})
    return () => { cancelled = true }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [policyId, type, policyCoverageId, recordCoverageId])

  // Class of Business is fixed per schedule (FIXED_CLASS_OF_BUSINESS), so it is
  // shown read-only instead of being retyped — the PDFs hardcode the same
  // value. Display-only: there is no class_of_business column on these tables,
  // and buildPayload's $request->only($spec['fields']) drops unknown keys, so
  // sending it is a no-op.
  const fixedClassOfBusiness = FIXED_CLASS_OF_BUSINESS[type]
  useEffect(() => {
    if (!fixedClassOfBusiness) return
    setScalars(prev => prev.class_of_business === fixedClassOfBusiness
      ? prev
      : { ...prev, class_of_business: fixedClassOfBusiness })
  }, [fixedClassOfBusiness, scalars.class_of_business])

  // Medical Evacuation: Gross Written Premium was removed from the form, but
  // medical_evacuation_coverages.premium is the ONLY column the Rate button
  // sums for MEDEVAC (PolicyCreateController::SPECIALIST_PREMIUM_TABLES) and
  // the only one GenerateQuotationPdfJob's getMedicalEvacuationTotal reads.
  // Derive it from the Description of Cover rows so the scalar still saves and
  // the coverage keeps rating.
  //
  // Only overwrites `premium` when the rows actually carry one. Records
  // captured before this change have a hand-entered premium and no per-row
  // amounts; blanking those on the next save would drop them out of the rate.
  useEffect(() => {
    if (type !== 'medical-evacuation') return
    const rows = jsonArrays.description_items ?? []
    const total = rows.reduce(
      (acc, r) => acc + (parseFloat(String(r.premium ?? '').replace(/,/g, '')) || 0),
      0,
    )
    if (total <= 0) return
    const next = total.toFixed(2)
    setScalars(prev => prev.premium === next ? prev : { ...prev, premium: next })
  }, [type, jsonArrays])

  // Bonds: price any schedule row that has a Sum Insured and a Rate but no
  // Annual Premium. The per-row calc in updateCell only fires when a cell is
  // edited, so rows saved before that calc existed (and the seeded catalog,
  // which ships with a Rate but a blank premium) would otherwise sit at 0 and
  // rate the whole cover at P 0.00 until someone retyped every Sum Insured.
  //
  // Fills BLANKS only — a row that already carries an Annual Premium is left
  // exactly as captured, including a deliberate override that doesn't match
  // SI × Rate. Bails out when nothing needs filling so it can't loop.
  useEffect(() => {
    if (type !== 'bonds') return
    const rows = jsonArrays.bond_schedule
    if (!rows?.length) return
    let changed = false
    const filled = rows.map(r => {
      const existing = parseFloat(String(r.annual_premium ?? '').replace(/,/g, '')) || 0
      if (existing > 0) return r
      const si   = parseFloat(String(r.sum_insured ?? '').replace(/,/g, '')) || 0
      const rate = parseFloat(String(r.rate ?? '').replace(/,/g, '')) || 0
      if (si <= 0 || rate <= 0) return r
      changed = true
      return { ...r, annual_premium: String(+(si * rate / 100).toFixed(2)) }
    })
    if (changed) setJsonArrays(prev => ({ ...prev, bond_schedule: filled }))
  }, [type, jsonArrays])

  // Bonds: Section 1's Premium (BWP) is the sum of the Bond Coverage Schedule's
  // Annual Premium column. bonds_coverages.premium is the ONLY column
  // BondsCoverage::getBondsTotal / getPremium sum for BONDSANDGUARANTEES — the
  // Rate button, the Rate Sheet and the V2 quote sheet all read it and none of
  // them look at bond_schedule — so a hand-typed header box could (and did)
  // disagree with the priced schedule. The field is now read-only and fed from
  // here; each row's Annual Premium comes from Sum Insured × Rate in
  // updateCell above.
  //
  // Same guard as Medical Evacuation: a zero total leaves `premium` untouched
  // rather than clearing it, so a record captured before this change keeps its
  // hand-entered figure until the schedule is actually priced. The rule is
  // mirrored server-side in SpecialistCoverageController::buildPayload.
  useEffect(() => {
    if (type !== 'bonds') return
    const rows = jsonArrays.bond_schedule ?? []
    const total = rows.reduce(
      (acc, r) => acc + (parseFloat(String(r.annual_premium ?? '').replace(/,/g, '')) || 0),
      0,
    )
    if (total <= 0) return
    const next = total.toFixed(2)
    setScalars(prev => prev.premium === next ? prev : { ...prev, premium: next })
  }, [type, jsonArrays])

  // Bonds: Section 1's Limit Insured is the aggregate exposure of the Bond
  // Coverage Schedule — the sum of its Sum Insured column. Unlike Premium the
  // field stays editable (an aggregate limit can legitimately sit below the
  // sum of the individual bonds), so this fills a BLANK box only and never
  // touches a figure an underwriter typed.
  useEffect(() => {
    if (type !== 'bonds') return
    const rows = jsonArrays.bond_schedule ?? []
    const total = rows.reduce(
      (acc, r) => acc + (parseFloat(String(r.sum_insured ?? '').replace(/,/g, '')) || 0),
      0,
    )
    if (total <= 0) return
    setScalars(prev => prev.limit_insured ? prev : { ...prev, limit_insured: total.toFixed(2) })
  }, [type, jsonArrays])

  // When opened with ?policy_coverage_id=N (no recordId), look up the EAR row
  // already linked to that coverage and switch into edit mode if it exists.
  // Otherwise stay in create mode and stamp policy_coverage_id into scalars
  // so it is persisted with the new row.
  useEffect(() => {
    if (recordIdParam || !policyCoverageId || !config) return
    let cancelled = false
    fetchSpecialistCoverageByType(policyId, type, { policy_coverage_id: policyCoverageId })
      .then(res => {
        if (cancelled) return
        const existing = res.data?.[0]
        if (existing && existing.id) {
          setResolvedRecordId(Number(existing.id))
        } else {
          setScalars(prev => ({ ...prev, policy_coverage_id: String(policyCoverageId) }))
        }
      })
      .catch(() => {})
    return () => { cancelled = true }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [policyId, type, policyCoverageId, recordIdParam])

  // Prefill on new records:
  //  - today_date           → today (ISO)
  //  - policy_inception_date → policy term start
  //  - policy_expiry_date    → policy term end
  //  - anniversary_renewal_date → term end + 1 day
  // Only runs when recordId is null (create mode); edits keep whatever
  // was saved so operators can override.
  //
  // Exception: Marine Cargo Open / Once-Off and Machinery Breakdown mirror
  // identity + date fields from the parent policy as read-only inputs, so
  // the fetch runs on edit too and overwrites whatever was saved.
  useEffect(() => {
    const isMarineCargoOpen = type === 'marine-cargo-open'
    const isMarineCargoOnceOff = type === 'marine-cargo-once-off'
    const isMarineMirrored = isMarineCargoOpen || isMarineCargoOnceOff
    const isMachineryBreakdown = type === 'machinery-breakdown'
    const isEar = type === 'ear'
    const isCar = type === 'car'
    const isMedicalMalpractice = type === 'medical-malpractice'
    const isMarineDirectorsOfficers = type === 'marine-directors-officers'
    const isMedicalEvacuation = type === 'medical-evacuation'
    const isCommercialCrime = type === 'commercial-crime'
    const isEnvironmentalLiability = type === 'environmental-liability'
    const isBonds = type === 'bonds'
    if (recordId && !isMarineMirrored && !isMachineryBreakdown && !isEar && !isCar && !isMedicalEvacuation && !isCommercialCrime && !isEnvironmentalLiability && !isBonds) return
    if (!recordId) {
      const todayIso = new Date().toISOString().slice(0, 10)
      setScalars(prev => ({ ...prev, today_date: prev.today_date || todayIso }))

      // Marine Cargo Open: editable boilerplate defaults for the Survey &
      // Claim Settlement, Memorandum, and Over Declaration sections. Applied
      // on create only — never overwrites an underwriter's edits on an
      // existing record.
      if (isMarineCargoOpen) {
        setScalars(prev => ({
          ...prev,
          survey_claim_settlement: prev.survey_claim_settlement
            || 'In the event of loss or damage which may involve a claim under this Insurance, immediate notice thereof and application for survey should be given to:',
          claim_payable_at: prev.claim_payable_at || 'Claim Payable at',
          claim_payable_by: prev.claim_payable_by || 'Claim Payable by',
          declaration: prev.declaration
            || "It is a condition of this insurance that the assured is bound to and will declare each and every sending/dispatch coming under the scope of this policy/cover without any exception and in case of an open cover obtain certificate of insurance/specific policy for each dispatch.\n\nA certified statement of such declarations shall be submitted to the Company immediately after dispatch or at a regular interval of 15 days mentioning the description of interest, number of packages, their value, carrier's receipt number and date.",
          deductible: prev.deductible
            || 'The Policy is subject to the following Deductibles (Deductible is the amount of loss to borne by the Insured under each and every loss)',
          over_declaration: prev.over_declaration
            || 'No liability to attach in respect of declarations in excess of amount insured by this open policy or subsequent endorsements.\n\nWarranted that except, where a deposit premium or the actual premium is paid in advance, the liability of the company shall commence from the time payment of the premium is made to the company in respect of each declaration of dispatch.',
        }))
      }

      // Bonds and Guarantees: standard Section 3 – Key Policy Conditions
      // wording. Same create-only, never-overwrite semantics as Marine Cargo
      // Open above — the operator can edit or clear any of it.
      if (isBonds) {
        setScalars(prev => {
          const next = { ...prev }
          for (const [key, text] of Object.entries(BONDS_KEY_POLICY_CONDITIONS)) {
            if (!next[key]) next[key] = text
          }
          return next
        })
      }
    }

    fetch(`${apiBase}/policies/${policyId}`, { headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' } })
      .then(r => r.ok ? r.json() : null)
      .then(res => {
        if (!res?.data) return
        const p = res.data
        // Prefer the latest policy_action's effective_from/effective_to so an
        // ENDORSE/RENEW window drives the dates rather than the parent term.
        // Falls through to term dates only when no action exists yet (e.g.
        // brand new policy without any action row).
        const inceptionSrc = p.latestAction?.effectiveFrom
          ?? p.startDate ?? p.term_start_date ?? p.billingStartDate ?? p.policyActivatedDate
        let expirySrc: string | null = p.latestAction?.effectiveTo
          ?? p.endDate ?? p.term_end_date ?? p.expiry_date ?? null

        // Last-resort fallback: derive expiry as inception + 1 year - 1 day
        // (annual policy default). Covers the case where both
        // policy_actions.effective_to and policy_terms.term_end_date are null
        // — common on legacy quote rows that were never closed out.
        if (!expirySrc && inceptionSrc) {
          const d = new Date(inceptionSrc)
          if (!isNaN(d.getTime())) {
            d.setFullYear(d.getFullYear() + 1)
            d.setDate(d.getDate() - 1)
            expirySrc = d.toISOString().slice(0, 10)
          }
        }

        if (inceptionSrc) {
          const iso = new Date(inceptionSrc).toISOString().slice(0, 10)
          setScalars(prev => ({
            ...prev,
            policy_inception_date: prev.policy_inception_date || iso,
            // Travel Insurance uses effective_from/expiry keys instead of
            // policy_inception_date/policy_expiry_date — populate both so the
            // same prefill effect works for any specialist type.
            effective_from: prev.effective_from || iso,
            // Marine Cargo Open / Once-Off: policy_period_from is a read-only
            // mirror of the parent policy — always overwrite, even on edit.
            ...(isMarineMirrored ? { policy_period_from: iso } : {}),
            // Machinery Breakdown: inception_date (no policy_ prefix) is a
            // read-only mirror — always overwrite, even on edit.
            ...(isMachineryBreakdown ? { inception_date: iso } : {}),
            // Marine Directors & Officers: inception_date is an editable field
            // (not a read-only mirror like MB) — fill only when blank so an
            // operator's edit is never clobbered.
            ...(isMarineDirectorsOfficers ? { inception_date: prev.inception_date || iso } : {}),
            // Medical Evacuation: inception_date is a read-only mirror, same
            // treatment as Machinery Breakdown — always overwrite, even on edit.
            ...(isMedicalEvacuation ? { inception_date: iso } : {}),
            ...(isCommercialCrime ? { inception_date: iso } : {}),
            ...(isEnvironmentalLiability ? { inception_date: iso } : {}),
            ...(isBonds ? { inception_date: iso } : {}),
            ...(isEar ? { period_from: prev.period_from || iso } : {}),
          }))
        }
        if (expirySrc) {
          const iso = new Date(expirySrc).toISOString().slice(0, 10)
          const anniv = new Date(expirySrc); anniv.setDate(anniv.getDate() + 1)
          const annivIso = anniv.toISOString().slice(0, 10)
          setScalars(prev => ({
            ...prev,
            policy_expiry_date: prev.policy_expiry_date || iso,
            expiry: prev.expiry || iso,
            anniversary_renewal_date: prev.anniversary_renewal_date || annivIso,
            ...(isMarineMirrored ? { policy_period_to: iso } : {}),
            ...(isMachineryBreakdown ? { expiry_date: iso } : {}),
            ...(isMarineDirectorsOfficers ? { expiry_date: prev.expiry_date || iso } : {}),
            ...(isMedicalEvacuation ? { expiry_date: iso } : {}),
            ...(isCommercialCrime ? { expiry_date: iso } : {}),
            ...(isEnvironmentalLiability ? { expiry_date: iso } : {}),
            ...(isBonds ? { expiry_date: iso } : {}),
            ...(isEar ? { period_to: prev.period_to || iso } : {}),
          }))
        }
        if (isEar && inceptionSrc && expirySrc) {
          const d1 = new Date(inceptionSrc), d2 = new Date(expirySrc)
          if (!isNaN(d1.getTime()) && !isNaN(d2.getTime()) && d2 >= d1) {
            const months = Math.round((d2.getTime() - d1.getTime()) / 86_400_000 / 30.4375)
            const snapped = [12, 24, 36].find(n => Math.abs(months - n) <= 1)
            if (snapped) {
              setScalars(prev => ({ ...prev, policy_period_months: prev.policy_period_months || String(snapped) }))
            }
          }
        }

        // Medical Malpractice + similar specialist forms: prefill identity
        // fields from the policy/customer/company so operators don't retype
        // data that's already on the policy.
        const customerName = (p.customer?.fullName
          ?? `${p.customer?.firstName ?? ''} ${p.customer?.lastName ?? ''}`.trim()) || null
        const companyName = p.profile?.companyName ?? null
        // Organisation customers store the legal entity name in p.profile.companyName.
        // DB column customer_profile.entity_type stores 'Organisation' (not 'Company') —
        // the earlier 'company' string here never matched and quietly fell back to
        // the personal customerName, autofilling the policyholder's individual name
        // into specialist Insured-Name fields. Compare against 'organisation'.
        const isCompany = (p.profile?.entityType ?? '').toLowerCase() === 'organisation'
        const insured = (isCompany ? companyName : customerName) ?? customerName ?? companyName

        const updates: Record<string, string> = {}
        if (p.policyNumber)                            updates.policy_number               = p.policyNumber
        // Medical Malpractice's Insured field follows the same Organisation-only
        // rule as EAR/CAR/MB — skip the generic write for MM and apply the
        // gated write just below. Other coverages that read `insured` (e.g.
        // Professional Indemnity) keep the existing fill-with-personal-name
        // behaviour unless explicitly opted in.
        if (insured && !isMedicalMalpractice)          updates.insured                     = insured
        if (isMedicalMalpractice && isCompany && companyName) updates.insured              = companyName
        // EAR/CAR/MB Insured-Name fields autofill ONLY for Organisation customers
        // (mirrors the underwriter rule: Individuals leave Insured Name blank so
        // an operator types the trading/project name explicitly).
        if (isCompany && companyName && isEar)         updates.name_of_insured             = companyName
        if (p.profile?.companyVatNumber)               updates.insured_vat_number          = p.profile.companyVatNumber
        else if (p.profile?.taxIdNumber)               updates.insured_vat_number          = p.profile.taxIdNumber
        if (p.profile?.companyRegistrationNumber)      updates.company_registration_number = p.profile.companyRegistrationNumber

        // CAR (Contractors All Risk) – Policy Schedule: prefill the
        // policyholder identity + address onto the Name & Address of
        // Insured and the Address of Risk blocks. Same "fill only if
        // empty" semantics as MM/PI above — the underwriter can still
        // override (project site often differs from company HQ).
        if (isCar) {
          if (isCompany && companyName) updates.insured_name   = companyName
          if (p.profile?.address)  updates.insured_street      = p.profile.address
          if (p.profile?.city)     updates.insured_postal_code = p.profile.city
          if (p.profile?.address)  updates.risk_street         = p.profile.address
          if (p.profile?.city)     updates.risk_postal_code    = p.profile.city
          if (p.profile?.city)     updates.city_town_village   = p.profile.city
        }

        // Marine Directors & Officers – Policy Details: prefill the
        // policyholder identity + address onto Company Name (Policyholder)
        // and Company Address. Company Name fills for BOTH customer types
        // (company name for Organisations, full name for Individuals) to match
        // the backend loader. Fields are editable, so "fill only if empty"
        // applies below — an operator override is never overwritten.
        if (isMarineDirectorsOfficers) {
          if (insured)             updates.company_name    = insured
          if (p.profile?.address)  updates.company_address = p.profile.address
        }

        if (Object.keys(updates).length > 0) {
          setScalars(prev => {
            const next = { ...prev }
            for (const [k, v] of Object.entries(updates)) {
              if (!next[k]) next[k] = v
            }
            return next
          })
        }

        // Marine Cargo Open / Once-Off: assured_name is a read-only mirror
        // of the policy's insured/customer name — always overwrite
        // (create + edit). Marine Cargo Open additionally mirrors the
        // parent policy_number into open_policy_no.
        if (isMarineMirrored) {
          setScalars(prev => {
            const next = { ...prev }
            if (insured) next.assured_name = insured
            if (isMarineCargoOpen && p.policyNumber) {
              next.open_policy_no = p.policyNumber
            }
            return next
          })
        }

        // Machinery Breakdown: company_name (Insured Name) and
        // company_address are read-only mirrors of the policy's
        // insured/customer/company name + profile address — always
        // overwrite (create + edit).
        if (isMachineryBreakdown) {
          const mbUpdates: Record<string, string> = {}
          if (isCompany && companyName) mbUpdates.company_name = companyName
          if (p.profile?.address) mbUpdates.company_address = p.profile.address
          if (Object.keys(mbUpdates).length > 0) {
            setScalars(prev => ({ ...prev, ...mbUpdates }))
          }
        }

        // Medical Evacuation: company_name (Insured Name) and
        // company_address are read-only mirrors of the policy's
        // insured/customer/company name + profile address — always
        // overwrite (create + edit).
        //
        // Insured Name fills for BOTH customer types — `insured` resolves to
        // the company name for Organisations and the customer's full name for
        // Individuals, same as Marine D&O above. The Organisation-only rule
        // used here previously belongs to EAR/CAR/MB, where an Individual's
        // Insured Name is left blank on purpose so the operator types the
        // trading/project name. Medical Evacuation has no such field to type
        // into — company_name is readonly — so that rule just left Insured
        // Name permanently empty on the form and the schedule PDF for every
        // individual policyholder.
        if (isMedicalEvacuation) {
          const mevUpdates: Record<string, string> = {}
          if (insured) mevUpdates.company_name = insured
          if (p.profile?.address) mevUpdates.company_address = p.profile.address
          if (Object.keys(mevUpdates).length > 0) {
            setScalars(prev => ({ ...prev, ...mevUpdates }))
          }
        }

        // Commercial Crime: company_name (Insured Name) and company_address
        // are read-only mirrors of the policy's insured/customer/company name
        // + profile address — always overwrite (create + edit), same
        // treatment as Medical Evacuation / Machinery Breakdown.
        if (isCommercialCrime) {
          const ccUpdates: Record<string, string> = {}
          if (isCompany && companyName) ccUpdates.company_name = companyName
          if (p.profile?.address) ccUpdates.company_address = p.profile.address
          if (Object.keys(ccUpdates).length > 0) {
            setScalars(prev => ({ ...prev, ...ccUpdates }))
          }
        }

        // Environmental Liability: company_name (Named Insured) and
        // company_address are read-only mirrors of the policy's
        // insured/customer/company name + profile address — always
        // overwrite (create + edit), same treatment as Commercial Crime /
        // Medical Evacuation / Machinery Breakdown.
        if (isEnvironmentalLiability) {
          const elUpdates: Record<string, string> = {}
          if (isCompany && companyName) elUpdates.company_name = companyName
          if (p.profile?.address) elUpdates.company_address = p.profile.address
          if (Object.keys(elUpdates).length > 0) {
            setScalars(prev => ({ ...prev, ...elUpdates }))
          }
        }

        // Bonds and Guarantees: company_name (Insured / Principal) and
        // company_address (Postal Address) are read-only mirrors of the
        // policy's insured/customer/company name + profile address —
        // always overwrite (create + edit), same treatment as Commercial
        // Crime / Environmental Liability / Medical Evacuation.
        if (isBonds) {
          const bondsUpdates: Record<string, string> = {}
          // Insured / Principal fills for BOTH customer types — `insured`
          // resolves to the company name for Organisations and the customer's
          // full name for Individuals, same rule as Medical Evacuation above.
          // The Organisation-only gate that stood here left the field blank on
          // the form AND on the bond schedule PDF for every Individual
          // policyholder, and for any Organisation whose customer_profile has
          // no linked company row (companyName is null there) — and because
          // the field is readonly nobody could type it in either.
          if (insured) bondsUpdates.company_name = insured
          // Fallback only: the risk-address effect below overwrites
          // company_address (Postal Address) with the coverage's risk address,
          // which is what bonds actually print.
          if (p.profile?.address) bondsUpdates.company_address = p.profile.address
          if (Object.keys(bondsUpdates).length > 0) {
            setScalars(prev => ({ ...prev, ...bondsUpdates }))
          }
          // risk_address is handled by its own effect below — it depends on the
          // parent coverage, which resolves after this effect has run.
        }
      })
      .catch(() => {})
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [policyId, recordId, type])

  // Bonds and Guarantees: Risk Address is a read-only mirror of the Risk
  // Address the operator picked against this coverage in the Add Coverage step
  // (policy_coverages.risk_address_id) — always overwritten on create + edit,
  // same treatment as company_name / company_address.
  //
  // Keyed on effectivePolicyCoverageId rather than the raw ?policy_coverage_id=
  // URL param. Bonds is one of the SMART_SCHEDULE_LABELS types, so the Policy
  // Actions tab opens it with ?recordId= (or with no params at all before a
  // record exists) — the old gate saw no parent coverage and left the field
  // permanently blank, and because it is readonly nobody could type it either.
  //
  // Its own effect because the parent coverage resolves asynchronously: from
  // the loaded record's policy_coverage_id, or by coverage-code match for rows
  // saved without one. The prefill effect above has already finished by then.
  // Two fallbacks behind the parent coverage, because the field is readonly and
  // a miss leaves it permanently blank:
  //   1. the policy's own BONDSANDGUARANTEES coverage, matched by code (same
  //      unambiguous-single-hit rule as matchedCoverageId) — covers a record
  //      saved with policy_coverage_id NULL that the code match couldn't
  //      resolve in time for this effect;
  //   2. the policy's risk address when it carries exactly one — covers a
  //      coverage row saved with risk_address_id NULL.
  // Each source falls through to the physical address when address_name is
  // blank, so a risk address captured without a name still prints.
  useEffect(() => {
    if (type !== 'bonds') return
    let cancelled = false
    const pickAddress = (c: any): string => c?.riskAddressName || c?.riskAddress || ''
    fetch(`${apiBase}/policies/${policyId}/coverages`, {
      headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
    })
      .then(r => r.ok ? r.json() : null)
      .then(async covRes => {
        if (cancelled) return
        const list = Array.isArray(covRes?.data) ? covRes.data : []
        let addressName = effectivePolicyCoverageId
          ? pickAddress(list.find((c: any) => Number(c.id) === Number(effectivePolicyCoverageId)))
          : ''
        if (!addressName) {
          const codes = TYPE_COVERAGE_CODES['bonds'] ?? []
          const hits = list.filter((c: any) => codes.includes(String(c.coverageCode ?? '').toUpperCase()))
          if (hits.length === 1) addressName = pickAddress(hits[0])
        }
        if (!addressName) {
          const addrs = await fetchPolicyRiskAddresses(policyId).catch(() => [])
          if (addrs.length === 1) addressName = addrs[0].addressName || addrs[0].address || ''
        }
        if (cancelled) return
        if (addressName) {
          // Postal Address mirrors the same resolved risk address: bonds have
          // no separate postal capture, and the customer profile address it
          // used to fall back to is blank on most Organisation policies, so
          // the field sat permanently empty (it is readonly, so nobody could
          // type it either). The profile address set by the prefill effect
          // above stays as the fallback for when no risk address resolves.
          setScalars(prev => prev.risk_address === addressName && prev.company_address === addressName
            ? prev
            : { ...prev, risk_address: addressName, company_address: addressName })
        }
      })
      .catch(() => {})
    return () => { cancelled = true }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [policyId, type, effectivePolicyCoverageId])

  async function approvePeroidConfirm() {
    if (!recordId || !approvalModal) return
    setApprovingPeriod(true)
    try {
      const r = await fetch(`${apiBase}/policies/${policyId}/specialist-coverages/${type}/${recordId}/approve-period`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
      })
      const d = await r.json()
      if (!r.ok) { alert(d.error || 'Approval failed'); return }
      setScalars(prev => ({ ...prev, policy_period_months: approvalModal.pendingValue }))
      setPeriodApproved({ by: d.approved_by ?? 'You', at: d.approved_at ?? '' })
      setApprovalModal(null)
    } catch (e: any) { alert(e.message) }
    setApprovingPeriod(false)
  }

  /**
   * Confirm (or revoke) that the collateral captured on this bond schedule is
   * actually held. EXCO-only — the backend re-checks the `bonds-approve`
   * permission and returns 403 otherwise. Until this is stamped, the policy
   * cannot be issued.
   */
  async function toggleCollateralConfirmation(revoke: boolean) {
    if (!recordId) { alert('Save the bond schedule before confirming collateral.'); return }
    setConfirmingCollateral(true)
    try {
      const r = await fetch(
        `${apiBase}/policies/${policyId}/specialist-coverages/${type}/${recordId}/confirm-collateral`,
        {
          method: 'POST',
          headers: { Authorization: `Bearer ${token}`, Accept: 'application/json', 'Content-Type': 'application/json' },
          body: JSON.stringify({ revoke }),
        },
      )
      const d = await r.json()
      if (!r.ok) { alert(d.error || d.message || 'Collateral confirmation failed'); return }
      setScalars(prev => ({
        ...prev,
        collateral_confirmed:    String(d.collateral_confirmed ?? (revoke ? 0 : 1)),
        collateral_confirmed_by: d.collateral_confirmed_by ? String(d.collateral_confirmed_by) : '',
        collateral_confirmed_at: d.collateral_confirmed_at ?? '',
      }))
    } catch (e: any) { alert(e.message) }
    setConfirmingCollateral(false)
  }

  // ── Bonds collateral documents ─────────────────────────────────────────
  // The whole panel is one API surface: list / upload / approve / remove.
  // Upload is open to everyone; approve is refused server-side for anyone
  // without `bonds-approve`, so these handlers never carry the decision.

  const loadCollateralDocs = useCallback(async () => {
    if (type !== 'bonds') return
    setCollateralDocsLoading(true)
    setCollateralDocsError('')
    try {
      const { data } = await apiClient.get(`/policies/${policyId}/bonds/collateral-documents`)
      setCollateralDocs(Array.isArray(data?.data) ? data.data : [])
      if (typeof data?.may_approve === 'boolean') setMayApproveCollateral(data.may_approve)
      if (data?.warning) setCollateralDocsError(data.warning)
    } catch (err: any) {
      setCollateralDocsError(err?.response?.data?.error || err?.message || 'Could not load collateral documents.')
    } finally {
      setCollateralDocsLoading(false)
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [policyId, type])

  useEffect(() => { loadCollateralDocs() }, [loadCollateralDocs])

  async function uploadCollateralDoc(file: File) {
    setCollateralUploading(true)
    setCollateralDocsError('')
    try {
      const fd = new FormData()
      fd.append('collateral_file', file)
      // record_id scopes the document to this bond schedule row AND its action.
      // Without it the backend falls back to the policy's latest action, which
      // is right for a schedule that has not been saved yet.
      if (recordId) fd.append('record_id', String(recordId))
      if (scalars.collateral_type) fd.append('document_type', scalars.collateral_type)
      await apiClient.post(`/policies/${policyId}/bonds/collateral-documents`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      await loadCollateralDocs()
    } catch (err: any) {
      const v = err?.response?.data?.errors
      const flat = v && typeof v === 'object' ? Object.values(v).flat().join(', ') : ''
      setCollateralDocsError(flat || err?.response?.data?.error || err?.message || 'Upload failed.')
    } finally {
      setCollateralUploading(false)
    }
  }

  async function decideCollateralDoc(docId: number, reject: boolean) {
    const reason = reject ? (window.prompt('Reason for rejecting this collateral document (optional):') ?? '') : ''
    setCollateralActingId(docId)
    setCollateralDocsError('')
    try {
      await apiClient.post(`/policies/${policyId}/bonds/collateral-documents/${docId}/approve`, { reject, reason })
      await loadCollateralDocs()
    } catch (err: any) {
      setCollateralDocsError(err?.response?.data?.error || err?.message || 'Decision failed.')
    } finally {
      setCollateralActingId(null)
    }
  }

  /** Open a stored document. The route is token-authenticated, so it is
   *  fetched as a blob rather than linked to directly. */
  async function viewCollateralDoc(doc: CollateralDoc) {
    setCollateralDocsError('')
    try {
      const res = await apiClient.get(
        `/policies/${policyId}/bonds/collateral-documents/${doc.id}/download`,
        { responseType: 'blob' },
      )
      const url = URL.createObjectURL(res.data as Blob)
      window.open(url, '_blank', 'noopener')
      // Give the new tab time to take the blob before revoking it.
      setTimeout(() => URL.revokeObjectURL(url), 60_000)
    } catch (err: any) {
      setCollateralDocsError(err?.response?.data?.error || err?.message || 'Could not open the document.')
    }
  }

  async function removeCollateralDoc(docId: number) {
    if (!window.confirm('Remove this collateral document?')) return
    setCollateralActingId(docId)
    setCollateralDocsError('')
    try {
      await apiClient.delete(`/policies/${policyId}/bonds/collateral-documents/${docId}`)
      await loadCollateralDocs()
    } catch (err: any) {
      setCollateralDocsError(err?.response?.data?.error || err?.message || 'Remove failed.')
    } finally {
      setCollateralActingId(null)
    }
  }

  // Collect all json-array field configs by key
  const jsonFieldCols = useCallback((): Record<string, JsonColumnDef[]> => {
    const result: Record<string, JsonColumnDef[]> = {}
    config?.sections.forEach(s => s.fields.forEach(f => {
      if (f.type === 'json-array' && f.cols) result[f.key] = f.cols
    }))
    return result
  }, [config])

  // Collect all date-type field keys for this config (to normalize on load)
  const dateKeys = new Set<string>()
  config?.sections.forEach(s => s.fields.forEach(f => {
    if (f.type === 'date') dateKeys.add(f.key)
    if (f.type === 'date-range') {
      if (f.fromKey) dateKeys.add(f.fromKey)
      if (f.toKey) dateKeys.add(f.toKey)
    }
    if (f.type === 'field-group') {
      f.subFields?.forEach(sf => { if (sf.type === 'date') dateKeys.add(sf.key) })
    }
  }))

  // Seed json-array fields with defaultRows on create mode (no recordId).
  // Skips if user already touched the array, and skips entirely on edit
  // so saved data is never overwritten with defaults.
  useEffect(() => {
    if (!config || recordId) return
    const seeds: Record<string, Record<string, string>[]> = {}
    config.sections.forEach(s => s.fields.forEach(f => {
      if (f.type === 'json-array' && f.defaultRows && f.defaultRows.length > 0) {
        if (!jsonArrays[f.key] || jsonArrays[f.key].length === 0) {
          seeds[f.key] = f.defaultRows.map(r => ({ ...r }))
        }
      }
    }))
    if (Object.keys(seeds).length > 0) {
      setJsonArrays(prev => ({ ...prev, ...seeds }))
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [type, recordId])

  // Load existing record if editing
  useEffect(() => {
    if (!config || !recordId) return
    setLoading(true)
    setError(null)
    fetchSpecialistCoverageByType(policyId, type)
      .then(res => {
        const row = res.data.find((r: any) => r.id === recordId)
        if (!row) { setError('Record not found.'); return }

        const newScalars: Record<string, string> = {}
        const newJsonArrays: Record<string, Record<string, string>[]> = {}
        const newArrayScalars: Record<string, string[]> = {}

        // Look up defaultRows + cols per field so locked readonly text columns
        // (e.g. MB "Coverage Item / Description") that were saved blank by an
        // older save can still display their regulator-fixed labels.
        const fieldDefaults: Record<string, Record<string, string>[]> = {}
        const fieldCols: Record<string, JsonColumnDef[]> = {}
        config.sections.forEach(s => s.fields.forEach(f => {
          if (f.type === 'json-array') {
            if (f.defaultRows) fieldDefaults[f.key] = f.defaultRows
            if (f.cols) fieldCols[f.key] = f.cols
          }
        }))

        Object.entries(row).forEach(([key, val]) => {
          if (jsonKeys.has(key)) {
            const parsed = parseJsonArrayValue(val)
            const defaults = fieldDefaults[key]
            const cols = fieldCols[key]
            if (defaults && cols) {
              cols.forEach(c => {
                if (!c.readonly || c.key.startsWith('_')) return
                parsed.forEach((r, i) => {
                  if (!r[c.key] && defaults[i]?.[c.key]) r[c.key] = defaults[i][c.key]
                })
              })
            }
            newJsonArrays[key] = parsed
          } else if (checkboxListKeys.has(key)) {
            newArrayScalars[key] = parseStringArrayValue(val)
          } else {
            const strVal = val !== null && val !== undefined ? String(val) : ''
            // Normalize date fields so <input type="date"> can display them correctly
            newScalars[key] = dateKeys.has(key) ? normalizeDateValue(strVal) : strVal
          }
        })
        setScalars(newScalars)
        setJsonArrays(newJsonArrays)
        setArrayScalars(newArrayScalars)
      })
      .catch((err: any) => {
        const message = err?.response?.data?.message ?? err?.message ?? 'Failed to load record.'
        setError(message)
        reportErrorToTeam({ error: message, stack: err?.stack, context: 'SpecialistCoveragePage:loadRecord' })
      })
      .finally(() => setLoading(false))
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [policyId, type, recordId, loadNonce])

  if (!config) {
    return (
      <div className="p-8 text-center text-red-600 font-medium">
        Unknown specialist coverage type: <code>{type}</code>
      </div>
    )
  }

  const setScalar = (key: string, val: string) => {
    if (key === 'policy_period_months' && (val === '24' || val === '36')) {
      // Require authorization before applying 24/36 month periods
      setApprovalModal({ pendingValue: val })
      return
    }
    setScalars(prev => {
      const next = { ...prev, [key]: val }
      // EAR Period of Insurance auto-generate: when both from+to dates are set,
      // compute policy_period_months and weeks_of_testing from the delta so
      // Sonali's "Period of Insurance information need to auto generated" is met.
      if (key === 'period_from' || key === 'period_to') {
        const from = key === 'period_from' ? val : next.period_from
        const to   = key === 'period_to'   ? val : next.period_to
        if (from && to) {
          const d1 = new Date(from), d2 = new Date(to)
          if (!isNaN(d1.getTime()) && !isNaN(d2.getTime()) && d2 >= d1) {
            const days = Math.round((d2.getTime() - d1.getTime()) / 86_400_000)
            const months = Math.round(days / 30.4375)
            const weeks = Math.round(days / 7)
            if (!next.policy_period_months || next.policy_period_months === '') {
              next.policy_period_months = String(months)
            }
            if (!next.weeks_of_testing || next.weeks_of_testing === '') {
              next.weeks_of_testing = String(weeks)
            }
          }
        }
      }
      // CAR/Medical Malpractice: same logic for policy_inception_date / policy_expiry_date
      // Also derives Anniversary / Renewal Date = expiry + 1 day so the
      // operator doesn't have to enter it twice. Only fills when the target
      // is empty — manual overrides stay intact.
      if (key === 'policy_inception_date' || key === 'policy_expiry_date') {
        const from = key === 'policy_inception_date' ? val : next.policy_inception_date
        const to   = key === 'policy_expiry_date'    ? val : next.policy_expiry_date
        if (from && to) {
          const d1 = new Date(from), d2 = new Date(to)
          if (!isNaN(d1.getTime()) && !isNaN(d2.getTime()) && d2 >= d1) {
            const months = Math.round((d2.getTime() - d1.getTime()) / 86_400_000 / 30.4375)
            if (!next.policy_period_months || next.policy_period_months === '') {
              next.policy_period_months = String(months)
            }
            if ('anniversary_renewal_date' in prev && !next.anniversary_renewal_date) {
              const anniv = new Date(d2); anniv.setDate(anniv.getDate() + 1)
              next.anniversary_renewal_date = anniv.toISOString().slice(0, 10)
            }
          }
        }
      }
      // Marine Cargo Open / Once-Off + Machinery Breakdown:
      // inception_date / expiry_date naming instead of policy_inception/expiry.
      if (key === 'inception_date' || key === 'expiry_date') {
        const from = key === 'inception_date' ? val : next.inception_date
        const to   = key === 'expiry_date'    ? val : next.expiry_date
        if (from && to) {
          const d1 = new Date(from), d2 = new Date(to)
          if (!isNaN(d1.getTime()) && !isNaN(d2.getTime()) && d2 >= d1) {
            const months = Math.round((d2.getTime() - d1.getTime()) / 86_400_000 / 30.4375)
            if (!next.policy_period_months || next.policy_period_months === '') {
              next.policy_period_months = String(months)
            }
            if (!next.period_of_insurance || next.period_of_insurance === '') {
              next.period_of_insurance = String(months)
            }
          }
        }
      }
      // Premium + 14% VAT + 8% Service Charge + 14% VAT-on-SC breakdown.
      // Mirrors legacy calculateTravelVatAndTotal / calculate*VatAndGrandTotal:
      //   vat          = premium * 0.14
      //   service_charge = premium * 0.08
      //   vat_on_service = service_charge * 0.14
      //   grand_total  = premium + vat + service_charge + vat_on_service
      // Only fires when the target keys are part of the current form schema
      // (setScalar runs on every form; we don't want to stamp vat on forms
      // that don't declare it).
      if (key === 'premium' || key === 'annual_premium' || key === 'total_premium') {
        const premKey = key
        const premRaw = (next[premKey] || '').toString().replace(/,/g, '')
        const prem = parseFloat(premRaw) || 0
        if (prem > 0) {
          const vat = +(prem * 0.14).toFixed(2)
          const sc  = +(prem * 0.08).toFixed(2)
          const vatSc = +(sc * 0.14).toFixed(2)
          // Only write the derived fields if the key already exists on the
          // current form object (prev) — otherwise we'd be adding noise to
          // unrelated products.
          if ('vat' in prev)                     next.vat = String(vat)
          if ('service_charge' in prev)          next.service_charge = String(sc)
          if ('vat_on_service_charge' in prev)   next.vat_on_service_charge = String(vatSc)
          if ('grand_total_premium' in prev)     next.grand_total_premium = String(+(prem + vat + sc + vatSc).toFixed(2))
          if ('total' in prev)                   next.total = String(+(prem + vat + sc + vatSc).toFixed(2))
        }
      }

      // Travel Insurance: bidirectional auto-calc between policy_amount, vat,
      // and total. policy_amount is the premium excl. VAT; whichever of the
      // three the operator edits, the other two recompute from:
      //   vat   = policy_amount * VAT_RATE
      //   total = policy_amount + vat   ( = policy_amount * (1 + VAT_RATE) )
      // Gated on `policy_amount in prev` so the block only fires for the
      // Travel form — other specialist forms also have `vat`/`total` keys
      // and would be miscalculated by this logic.
      if ('policy_amount' in prev && (key === 'policy_amount' || key === 'vat' || key === 'total')) {
        const VAT_RATE = 0.14
        const num = (k: string) => parseFloat((next[k] || '').toString().replace(/,/g, '')) || 0
        if (key === 'policy_amount') {
          const amt = num('policy_amount')
          if (amt > 0) {
            const vat = +(amt * VAT_RATE).toFixed(2)
            const total = +(amt + vat).toFixed(2)
            next.vat = String(vat)
            next.total = String(total)
          } else {
            next.vat = ''
            next.total = ''
          }
        } else if (key === 'total') {
          // Inverse: policy_amount = total / (1 + VAT_RATE)
          const tot = num('total')
          if (tot > 0) {
            const amt = +(tot / (1 + VAT_RATE)).toFixed(2)
            const vat = +(tot - amt).toFixed(2)
            next.policy_amount = String(amt)
            next.vat = String(vat)
          } else {
            next.policy_amount = ''
            next.vat = ''
          }
        } else if (key === 'vat') {
          // Inverse: policy_amount = vat / VAT_RATE
          const v = num('vat')
          if (v > 0) {
            const amt = +(v / VAT_RATE).toFixed(2)
            const total = +(amt + v).toFixed(2)
            next.policy_amount = String(amt)
            next.total = String(total)
          } else {
            next.policy_amount = ''
            next.total = ''
          }
        }
      }

      // Scalar SI/rate/premium pair calcs. Each triplet auto-computes
      // premium when SI or rate changes, and back-calcs rate when the
      // user edits premium directly. Mirrors every legacy calculate*
      // and calculate*RateFromPremium helper that targets scalar fields
      // (the JsonArrayField already does the equivalent for array rows).
      //
      // Covers: CAR Section 3 Gross Profit + Increased Cost, Marine
      // Cargo Once-Off / Open (premium_rate is the rate key), Medical
      // Malpractice annual_premium pair.
      //
      // NOT Machinery Breakdown: its Premium is the sum of the four priced
      // section blocks (see the cascade effect keyed on 'machinery-breakdown')
      // and the field renders read-only, so no scalar SI × rate pair applies.
      // The limit_of_liability triplet below is inert for MB — that field was
      // removed from the MB schedule — and is kept for the covers that still
      // expose it.
      const section3Pairs = [
        { si: 'section3_gross_profit_annual_sum_insured', rate: 'section3_gross_profit_rate', prem: 'section3_gross_profit_premium' },
        { si: 'section3_increased_cost_sum_insured',      rate: 'section3_increased_cost_rate', prem: 'section3_increased_cost_premium' },
        // Marine Cargo Once-Off + Open (scalar rate/SI/premium on the coverage row)
        { si: 'sum_insured',                              rate: 'premium_rate',                prem: 'premium' },
        // Marine D&O / covers that still expose a scalar limit_of_liability.
        { si: 'limit_of_liability',                       rate: 'rate',                        prem: 'premium' },
      ]
      for (const p of section3Pairs) {
        // Machinery Breakdown owns `premium` through the section cascade — a
        // stray SI/rate pair must never write it back to SI × rate.
        if (type === 'machinery-breakdown' && p.prem === 'premium') continue
        if (key !== p.si && key !== p.rate && key !== p.prem) continue
        const si   = parseFloat((next[p.si]   || '').toString().replace(/,/g, '')) || 0
        const rate = parseFloat(next[p.rate] || '0') || 0
        const prem = parseFloat((next[p.prem] || '').toString().replace(/,/g, '')) || 0
        if (key === p.prem && si > 0 && prem > 0) {
          // User typed a premium — back-calc the rate
          next[p.rate] = (prem / si * 100).toFixed(4).replace(/\.?0+$/, '')
        } else if ((key === p.si || key === p.rate) && si > 0 && rate > 0) {
          // User typed SI or rate — recompute premium
          next[p.prem] = (si * rate / 100).toFixed(2)
        } else if ((key === p.si || key === p.rate) && si > 0 && rate === 0) {
          next[p.prem] = '0'
        }
      }

      // CAR Section 3 totals:
      //   section3_total_annual_sum = gross_profit_annual_sum + increased_cost_sum
      //   section3_total_premium    = gross_profit_premium    + increased_cost_premium
      const changedS3 = section3Pairs.some(p => key === p.si || key === p.rate || key === p.prem)
      if (changedS3) {
        const gpSi  = parseFloat((next.section3_gross_profit_annual_sum_insured || '').toString().replace(/,/g, '')) || 0
        const icSi  = parseFloat((next.section3_increased_cost_sum_insured      || '').toString().replace(/,/g, '')) || 0
        const gpPr  = parseFloat((next.section3_gross_profit_premium            || '').toString().replace(/,/g, '')) || 0
        const icPr  = parseFloat((next.section3_increased_cost_premium          || '').toString().replace(/,/g, '')) || 0
        next.section3_total_annual_sum = (gpSi + icSi).toFixed(2)
        next.section3_total_premium    = (gpPr + icPr).toFixed(2)
      }

      return next
    })
  }

  const setJsonArray = (key: string, rows: Record<string, string>[]) => {
    setJsonArrays(prev => ({ ...prev, [key]: rows }))
    // Auto-update matching *_total_sum_insured / *_total_premium / total_premium /
    // total_sum_insured scalars so the UI always reflects the sum of the rows.
    const sumCol = (col: string) => rows.reduce((a, r) => a + (parseFloat(r[col] || '0') || 0), 0)
    const si = sumCol('sum_insured') + sumCol('limit_of_indemnity') + sumCol('limit_indemnity') + sumCol('annual_sum_insured')
    const prem = sumCol('premium')
    const updates: Record<string, string> = {}
    // Match total keys by coverage section prefix derived from the JSON array key
    // (e.g. key='section1_items' → section1_total_sum_insured / section1_total_premium)
    const m = key.match(/^(section\d+)_items$/)
    if (m) {
      updates[`${m[1]}_total_sum_insured`] = si > 0 ? String(+si.toFixed(2)) : ''
      updates[`${m[1]}_total_premium`] = prem > 0 ? String(+prem.toFixed(2)) : ''
    }
    // Generic PAR/Travel/etc: key === 'insured_items' or 'items' → plain totals
    if (key === 'insured_items' || key === 'items') {
      updates['total_sum_insured'] = si > 0 ? String(+si.toFixed(2)) : ''
      updates['total_premium'] = prem > 0 ? String(+prem.toFixed(2)) : ''
    }
    if (Object.keys(updates).length > 0) {
      setScalars(prev => ({ ...prev, ...updates }))
    }
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setSaving(true)
    setError(null)
    setSuccess(null)

    // Build payload: scalar fields + JSON-array fields + flat-array fields
    const payload: Record<string, any> = { ...scalars }
    // Never let the main form save touch the upload-managed columns. The
    // /upload-wording endpoint is the single writer; including a stale
    // empty value here would overwrite a freshly-uploaded path on update.
    delete payload.policy_wording_path
    delete payload.policy_wording_filename
    delete payload.policy_wording
    const colsMap = jsonFieldCols()
    // Look up defaultRows per field key so locked rows with readonly text
    // columns (e.g. the regulator-fixed "Coverage Item / Description" / "Item"
    // names on MB Sections 1/2/3) can fall back to the configured label when
    // the form value is missing — otherwise the save would persist blanks
    // and the next reload would render the rows empty.
    const defaultsMap: Record<string, Record<string, string>[]> = {}
    config.sections.forEach(s => s.fields.forEach(f => {
      if (f.type === 'json-array' && f.defaultRows) defaultsMap[f.key] = f.defaultRows
    }))
    jsonKeys.forEach(key => {
      const rows = jsonArrays[key] ?? []
      // Convert numeric-typed col values to numbers before sending
      const cols = colsMap[key] ?? []
      const defaults = defaultsMap[key] ?? []
      payload[key] = rows.map((row, idx) => {
        const converted: Record<string, any> = {}
        cols.forEach(c => {
          // Skip purely-computed display columns (convention: leading underscore,
          // e.g. _item_no). Real readonly columns like the locked `name` on MB
          // sections still need to round-trip, otherwise saving blanks them.
          if (c.key.startsWith('_')) return
          const raw = row[c.key]
          const isEmpty = raw === undefined || raw === null || raw === ''
          // For readonly text columns, fall back to the row's defaultRows value
          // so the locked label is preserved through save/reload.
          const fallback = c.readonly && isEmpty ? defaults[idx]?.[c.key] ?? '' : raw
          converted[c.key] = c.type === 'number' && fallback !== ''
            ? Number(fallback)
            : fallback ?? ''
        })
        return converted
      })
    })
    // checkbox-list fields → flat array of selected option values
    checkboxListKeys.forEach(key => {
      payload[key] = arrayScalars[key] ?? []
    })

    // Convert 'number' scalar fields to numbers
    config.sections.forEach(s => s.fields.forEach(f => {
      if (f.type === 'number' && payload[f.key] !== '' && payload[f.key] !== undefined) {
        payload[f.key] = Number(payload[f.key])
      }
    }))

    try {
      let res: any
      if (recordId) {
        res = await updateSpecialistCoverage(policyId, type, recordId, payload)
        setSuccess(res?.warning ? `Updated — ${res.warning}` : 'Record updated successfully.')
      } else {
        res = await createSpecialistCoverage(policyId, type, payload)
        setSuccess(res?.warning ? `Created — ${res.warning}` : 'Record created successfully.')
        navigate(`/policies/${policyId}/specialist-coverage/${type}?recordId=${res.id}`, { replace: true })
      }
    } catch (err: any) {
      setError(err?.response?.data?.error ?? err?.message ?? 'Save failed.')
    } finally {
      setSaving(false)
    }
  }

  const renderField = (field: FieldConfig) => {
    const val = scalars[field.key] ?? ''
    const colClass = field.colSpan === 4 ? 'col-span-4' : field.colSpan === 3 ? 'col-span-3' : field.colSpan === 2 ? 'col-span-2' : 'col-span-1'
    const inputCls = 'w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent'

    // Display-only field types: render visually, no input/state binding.
    if (field.type === 'heading') {
      return (
        <div key={field.key} className={colClass}>
          <h3 className="text-sm font-bold text-gray-900 underline mb-1 mt-2">{field.label}</h3>
        </div>
      )
    }
    if (field.type === 'help-text') {
      return (
        <div key={field.key} className={colClass}>
          <p className="text-xs text-gray-500 mb-1">{field.label}</p>
        </div>
      )
    }

    if (field.type === 'json-array') {
      const rows = jsonArrays[field.key] ?? []
      return (
        <div key={field.key} className={colClass}>
          <JsonArrayField
            cols={field.cols ?? []}
            rows={rows}
            onChange={r => setJsonArray(field.key, r)}
            locked={field.locked}
            addButtonLabel={field.addButtonLabel}
            lockedRowCount={field.lockedRowCount}
            groupBreaks={field.groupBreaks}
          />
        </div>
      )
    }

    if (field.type === 'date-range') {
      const dateInput = (k: string) => (
        <input
          type="date"
          value={scalars[k] ?? ''}
          onChange={e => setScalar(k, e.target.value)}
          readOnly={field.readonly}
          className={`${inputCls}${field.readonly ? ' bg-gray-50 text-gray-500' : ''}`}
        />
      )
      return (
        <div key={field.key} className={`${colClass} grid grid-cols-3 gap-4 items-start`}>
          <div>
            <p className="text-sm font-bold text-gray-900 underline">{field.label}</p>
            {field.description && (
              <p className="text-xs text-gray-500 mt-1 leading-snug">{field.description}</p>
            )}
          </div>
          <div>
            <label className="block text-xs font-medium text-gray-700 mb-1">From:</label>
            {dateInput(field.fromKey ?? '')}
          </div>
          <div>
            <label className="block text-xs font-medium text-gray-700 mb-1">To:</label>
            {dateInput(field.toKey ?? '')}
          </div>
        </div>
      )
    }

    if (field.type === 'field-group') {
      return (
        <div key={field.key} className={`${colClass} grid grid-cols-3 gap-4 items-start`}>
          <div className="pt-2">
            <p className="text-sm font-bold text-gray-900">{field.label}</p>
          </div>
          <div className="col-span-2 grid grid-cols-2 gap-4">
            {field.subFields?.map(sf => renderField(sf))}
          </div>
        </div>
      )
    }

    if (field.type === 'checkbox-list') {
      const selected = arrayScalars[field.key] ?? []
      const opts = field.options ?? []
      const toggle = (value: string, checked: boolean) => {
        setArrayScalars(prev => {
          const cur = new Set(prev[field.key] ?? [])
          if (checked) cur.add(value); else cur.delete(value)
          return { ...prev, [field.key]: Array.from(cur) }
        })
      }
      // Two-column layout matching the v2 blade: first half stacks in the
      // left column (1..14), second half in the right column (15..28).
      const half = Math.ceil(opts.length / 2)
      const left = opts.slice(0, half)
      const right = opts.slice(half)
      const renderOpt = (o: { value: string; label: string }, idx: number) => (
        <label key={o.value} className="flex items-start gap-2 py-1.5 cursor-pointer">
          <input
            type="checkbox"
            checked={selected.includes(o.value)}
            onChange={e => toggle(o.value, e.target.checked)}
            className="mt-0.5 h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-400"
          />
          <span className="text-sm text-gray-700">{idx + 1}. {o.label}</span>
        </label>
      )
      return (
        <div key={field.key} className={colClass}>
          {field.label && (
            <label className="block text-xs font-medium text-gray-700 mb-2">{field.label}</label>
          )}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-x-6">
            <div className="flex flex-col">{left.map((o, i) => renderOpt(o, i))}</div>
            <div className="flex flex-col">{right.map((o, i) => renderOpt(o, half + i))}</div>
          </div>
        </div>
      )
    }

    const ro = !!field.readonly
    const roCls = ro ? ' bg-gray-50 cursor-not-allowed' : ''

    const labelEl = (
      <label className={field.inlineLabel
        ? 'text-sm font-semibold text-gray-800'
        : 'block text-xs font-medium text-gray-700 mb-1'
      }>
        {field.label}
        {ro && <span className="ml-1 text-[10px] font-normal text-ink-faint">{field.readonlyNote ?? '(auto-filled from policy)'}</span>}
      </label>
    )

    let inputEl: JSX.Element
    if (field.type === 'textarea') {
      inputEl = (
        <textarea
          value={val}
          onChange={e => setScalar(field.key, e.target.value)}
          rows={3}
          readOnly={ro}
          className={`${inputCls} resize-y${roCls}`}
          placeholder={field.placeholder}
        />
      )
    } else if (field.type === 'select') {
      inputEl = (
        <div>
          <select
            value={val}
            onChange={e => setScalar(field.key, e.target.value)}
            className={inputCls}
          >
            <option value="">— Select —</option>
            {field.options?.map(o => (
              <option key={o.value} value={o.value}>{o.label}</option>
            ))}
          </select>
          {field.key === 'policy_period_months' && (val === '24' || val === '36') && (
            periodApproved
              ? <p className="text-xs text-green-600 mt-1">Authorized</p>
              : <p className="text-xs text-amber-600 mt-1">Requires authorization — click the field to approve</p>
          )}
        </div>
      )
    } else if (field.type === 'checkbox') {
      inputEl = (
        <input
          type="checkbox"
          checked={val === '1' || val === 'true'}
          onChange={e => setScalar(field.key, e.target.checked ? '1' : '0')}
          className="h-4 w-4 text-indigo-600 border-gray-300 rounded"
        />
      )
    } else if (field.type === 'number') {
      inputEl = (
        <div>
          <input
            type="text"
            inputMode="decimal"
            value={formatNumberInput(val)}
            onChange={e => setScalar(field.key, unformatNumber(e.target.value))}
            className={inputCls}
            placeholder={field.placeholder}
          />
          {(() => {
            const hint = toWordsShort(val)
            return hint ? <p className="text-xs text-gray-500 mt-0.5">{hint}</p> : null
          })()}
        </div>
      )
    } else {
      inputEl = (
        <input
          type={field.type === 'date' ? 'date' : 'text'}
          value={val}
          onChange={e => setScalar(field.key, e.target.value)}
          readOnly={ro}
          className={`${inputCls}${roCls}`}
          placeholder={field.placeholder}
        />
      )
    }

    if (field.inlineLabel) {
      return (
        <div key={field.key} className={`${colClass} grid grid-cols-12 gap-3 items-start py-2`}>
          <div className="col-span-4 pt-2">{labelEl}</div>
          <div className="col-span-8">{inputEl}</div>
        </div>
      )
    }

    return (
      <div key={field.key} className={colClass}>
        {labelEl}
        {inputEl}
      </div>
    )
  }

  // Renders one section card. Extracted so sections can render either with the
  // main group or, when section.afterPolicyWording is set, after the Policy
  // Wording upload block.
  const renderSection = (section: SectionConfig, si: number) => {
    // Progressive rendering for endorsement_N slots — show only the
    // filled ones plus the next one, with an "+ Add Endorsement" button
    // to reveal further slots (Sonali's feedback for EAR/CAR).
    const endorsementKeys = section.fields.filter(f => /^endorsement_\d+$/.test(f.key))
    const isEndorsementSection = endorsementKeys.length > 0 && endorsementKeys.length === section.fields.length
    let visibleFields = section.fields
    if (isEndorsementSection) {
      const filled = endorsementKeys.filter(f => (scalars[f.key] || '').trim().length > 0).length
      const reveal = Math.max(visibleEndorsements[section.title] ?? 1, filled + (filled < endorsementKeys.length ? 1 : 0))
      visibleFields = endorsementKeys.slice(0, Math.min(reveal, endorsementKeys.length))
    }
    return (
      <div key={si} className="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
        <div className="bg-indigo-50 px-4 py-3 border-b border-indigo-100 flex items-center justify-between">
          <h2 className="text-sm font-semibold text-indigo-800">{section.title}</h2>
          {isEndorsementSection && (
            <button
              type="button"
              onClick={() => setVisibleEndorsements(prev => ({
                ...prev,
                [section.title]: Math.min((prev[section.title] ?? 1) + 1, endorsementKeys.length),
              }))}
              disabled={(visibleEndorsements[section.title] ?? 1) >= endorsementKeys.length}
              className="text-xs font-medium text-indigo-700 bg-white border border-indigo-300 rounded px-2 py-1 hover:bg-indigo-100 disabled:opacity-40"
            >
              + Add Endorsement
            </button>
          )}
        </div>
        {section.description && (
          <p className="px-4 pt-3 text-xs text-gray-500 uppercase tracking-wide">{section.description}</p>
        )}
        <div className={`p-4 grid gap-4 ${section.gridCols === 4 ? 'grid-cols-4' : section.gridCols === 2 ? 'grid-cols-2' : 'grid-cols-3'}`}>
          {visibleFields.map(field => renderField(field))}
        </div>
        {section.collateralDocs && (() => {
          const approved = collateralDocs.find(d => d.status === 'APPROVED')
          const pending  = collateralDocs.filter(d => d.status === 'PENDING')
          const banner = approved
            ? `Collateral document approved${approved.approved_by_name ? ` by ${approved.approved_by_name}` : ''}${approved.approved_at ? ` on ${String(approved.approved_at).slice(0, 10)}` : ''}. This bond may be issued once the transaction is approved by EXCO.`
            : pending.length > 0
              ? 'EXCO approval needed — the collateral document is uploaded but not yet approved. The policy cannot be submitted for issue until EXCO approves it.'
              : 'No collateral document on file. Upload the bank guarantee / cession / deposit receipt, then EXCO must approve it. The policy cannot be issued until then.'
          return (
            <div className="border-t border-gray-200">
              <div className="p-4 space-y-3">
                {collateralDocsError && (
                  <p className="text-xs text-red-600">{collateralDocsError}</p>
                )}
                {collateralDocsLoading && collateralDocs.length === 0 && (
                  <p className="text-xs text-gray-500">Loading documents…</p>
                )}
                {collateralDocs.length > 0 && (
                  <div className="overflow-x-auto">
                    <table className="min-w-full text-xs">
                      <thead>
                        <tr className="text-left text-gray-500 border-b border-gray-200">
                          <th className="py-2 pr-3 font-medium">Document</th>
                          <th className="py-2 pr-3 font-medium">Type</th>
                          <th className="py-2 pr-3 font-medium">Uploaded By</th>
                          <th className="py-2 pr-3 font-medium">Status</th>
                          <th className="py-2 pr-3 font-medium text-right">Action</th>
                        </tr>
                      </thead>
                      <tbody>
                        {collateralDocs.map(doc => (
                          <tr key={doc.id} className="border-b border-gray-100 align-top">
                            <td className="py-2 pr-3">
                              <button
                                type="button"
                                onClick={() => viewCollateralDoc(doc)}
                                className="text-indigo-600 hover:text-indigo-800 underline break-all text-left"
                              >
                                {doc.file_name}
                              </button>
                              {doc.rejection_reason && (
                                <p className="text-[11px] text-red-600 mt-0.5">Reason: {doc.rejection_reason}</p>
                              )}
                            </td>
                            <td className="py-2 pr-3 text-gray-700">{doc.document_type || '—'}</td>
                            <td className="py-2 pr-3 text-gray-700">
                              {doc.uploaded_by_name || '—'}
                              {doc.uploaded_at && (
                                <span className="block text-[11px] text-gray-400">{String(doc.uploaded_at).slice(0, 16).replace('T', ' ')}</span>
                              )}
                            </td>
                            <td className="py-2 pr-3">
                              <span className={`inline-block rounded px-2 py-0.5 text-[11px] font-medium ${
                                doc.status === 'APPROVED' ? 'bg-green-100 text-green-700'
                                  : doc.status === 'REJECTED' ? 'bg-red-100 text-red-700'
                                  : 'bg-amber-100 text-amber-700'
                              }`}>
                                {doc.status === 'PENDING' ? 'AWAITING EXCO' : doc.status}
                              </span>
                              {doc.status === 'APPROVED' && doc.approved_by_name && (
                                <span className="block text-[11px] text-gray-400">{doc.approved_by_name}</span>
                              )}
                            </td>
                            <td className="py-2 pr-3 text-right whitespace-nowrap">
                              {mayApproveCollateral && doc.status !== 'APPROVED' && (
                                <button
                                  type="button"
                                  onClick={() => decideCollateralDoc(doc.id, false)}
                                  disabled={collateralActingId === doc.id}
                                  className="text-[11px] font-medium rounded px-2 py-1 bg-green-600 text-white hover:bg-green-700 disabled:opacity-40"
                                >
                                  Approve
                                </button>
                              )}
                              {mayApproveCollateral && doc.status !== 'REJECTED' && (
                                <button
                                  type="button"
                                  onClick={() => decideCollateralDoc(doc.id, true)}
                                  disabled={collateralActingId === doc.id}
                                  className="ml-1 text-[11px] font-medium rounded px-2 py-1 bg-white border border-red-300 text-red-700 hover:bg-red-50 disabled:opacity-40"
                                >
                                  Reject
                                </button>
                              )}
                              <button
                                type="button"
                                onClick={() => removeCollateralDoc(doc.id)}
                                disabled={collateralActingId === doc.id}
                                className="ml-1 text-[11px] font-medium rounded px-2 py-1 bg-white border border-gray-300 text-gray-600 hover:bg-gray-50 disabled:opacity-40"
                              >
                                Remove
                              </button>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}

                <div>
                  <label className="block text-xs font-medium text-gray-700 mb-1">Upload Collateral Document</label>
                  <input
                    type="file"
                    accept=".pdf,.jpg,.jpeg,.png"
                    disabled={collateralUploading}
                    onChange={e => {
                      const file = e.target.files?.[0]
                      e.target.value = ''
                      if (file) uploadCollateralDoc(file)
                    }}
                    className="block w-full text-xs text-gray-700 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 disabled:opacity-40"
                  />
                  <p className="text-[11px] text-gray-500 mt-1">
                    PDF or image, max 20MB. {collateralUploading ? 'Uploading…' : 'Anyone may upload — only EXCO can approve.'}
                  </p>
                </div>
              </div>

              <div className={`px-4 py-3 border-t flex items-center justify-between gap-4 ${approved ? 'bg-green-50 border-green-200' : 'bg-amber-50 border-amber-200'}`}>
                <p className={`text-xs ${approved ? 'text-green-700' : 'text-amber-700'}`}>{banner}</p>
                {!mayApproveCollateral && !approved && (
                  <span className="shrink-0 text-[11px] text-amber-700 border border-amber-300 rounded px-2 py-1">
                    EXCO approval required
                  </span>
                )}
              </div>
            </div>
          )
        })()}
        {section.collateralGate && (() => {
          const confirmed = scalars.collateral_confirmed === '1' || scalars.collateral_confirmed === 'true'
          const captured  = (scalars.collateral_type || '').trim() !== ''
            && Number(String(scalars.collateral_value || '').replace(/,/g, '')) > 0
          return (
            <div className={`px-4 py-3 border-t flex items-center justify-between gap-4 ${confirmed ? 'bg-green-50 border-green-200' : 'bg-amber-50 border-amber-200'}`}>
              <p className={`text-xs ${confirmed ? 'text-green-700' : 'text-amber-700'}`}>
                {confirmed
                  ? `Collateral confirmed${scalars.collateral_confirmed_at ? ` on ${scalars.collateral_confirmed_at}` : ''}${scalars.collateral_confirmed_by ? ` by user ${scalars.collateral_confirmed_by}` : ''}. This bond may be issued once the transaction is approved by EXCO.`
                  : captured
                    ? 'Collateral captured but NOT confirmed. An EXCO approver must confirm it is held — the policy cannot be issued until then.'
                    : 'No collateral captured. Record the collateral type and value, save, then have EXCO confirm it. The policy cannot be issued until then.'}
              </p>
              <button
                type="button"
                onClick={() => toggleCollateralConfirmation(confirmed)}
                disabled={confirmingCollateral || (!confirmed && !captured)}
                className={`shrink-0 text-xs font-medium rounded px-3 py-1.5 text-white disabled:opacity-40 ${confirmed ? 'bg-gray-600 hover:bg-gray-700' : 'bg-green-600 hover:bg-green-700'}`}
              >
                {confirmingCollateral
                  ? 'Working…'
                  : confirmed ? 'Revoke Confirmation' : 'Confirm Collateral Held (EXCO)'}
              </button>
            </div>
          )
        })()}
      </div>
    )
  }

  return (
    <div className="max-w-5xl mx-auto px-4 py-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <Link
            to={`/policies/${policyId}`}
            className="text-sm text-indigo-600 hover:text-indigo-800 flex items-center gap-1 mb-1"
          >
            ← Back to Policy
          </Link>
          <h1 className="text-xl font-bold text-gray-900">
            {recordId ? 'Edit' : 'Add'} {config.label}
          </h1>
          <p className="text-sm text-gray-500">Policy #{policyId}</p>
        </div>
      </div>

      {/* Status messages */}
      {error && (
        <div className="bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700 flex items-center gap-3">
          <span className="flex-1">{error}</span>
          {/* Only show Try again when we have a recordId — load failures
              originate from the fetchSpecialistCoverageByType call. Save
              errors share the same banner but aren't retried this way. */}
          {recordId && (
            <button onClick={() => setLoadNonce(n => n + 1)}
              className="px-2.5 py-1 bg-red-600 text-white rounded text-xs font-medium hover:bg-red-700">
              Try again
            </button>
          )}
          <button onClick={() => setError(null)} className="text-red-500 hover:text-red-700">&times;</button>
        </div>
      )}
      {success && (
        <div className="bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-sm text-green-700">
          {success}
        </div>
      )}

      {periodApproved && (
        <div className="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 text-sm text-blue-700">
          Policy period approved by user {periodApproved.by} at {periodApproved.at}
        </div>
      )}

      {loading ? (
        <div className="text-center py-12 text-gray-500">Loading record…</div>
      ) : (
        <form onSubmit={handleSubmit} className="space-y-6">
          {config.sections.map((section, si) => section.afterPolicyWording ? null : renderSection(section, si))}

          {/* Policy Wording Upload */}
          <PolicyWordingSection
            policyId={policyId}
            type={type}
            recordId={recordId}
            existingPath={scalars.policy_wording_path}
            existingFilename={scalars.policy_wording_filename || scalars.policy_wording}
          />

          {/* Sections flagged afterPolicyWording render here (e.g. PI Notes) */}
          {config.sections.map((section, si) => section.afterPolicyWording ? renderSection(section, si) : null)}

          {/* Miscellaneous Items (dropdown + auto-rate, mirrors wizard).
              Mounted for Travel Insurance, the engineering specialist forms
              (EAR / CAR / PAR / Machinery Breakdown), the marine cargo
              schedules, the liability specialist forms (Medical
              Malpractice / Professional Indemnity / Directors & Officers),
              and Medical Evacuation (Miscellaneous product) so operators can
              attach specified-coverage-item rows directly from the schedule
              page. Other specialist forms keep their own item tables driven
              by the json-array config.
              Gated on effectivePolicyCoverageId, not the raw URL param — see
              its declaration. */}
          {['travel-insurance', 'ear', 'car', 'par', 'machinery-breakdown', 'marine-cargo-open', 'marine-cargo-once-off', 'medical-malpractice', 'professional-indemnity', 'marine-directors-officers', 'medical-evacuation', 'commercial-crime', 'environmental-liability', 'bonds'].includes(type) && effectivePolicyCoverageId && (
            <CoverageMiscItemsSection policyId={policyId} policyCoverageId={effectivePolicyCoverageId} />
          )}

          {/* Submit bar */}
          <div className="flex items-center justify-between bg-white border border-gray-200 rounded-xl px-5 py-4 shadow-sm">
            <Link
              to={`/policies/${policyId}`}
              className="text-sm text-gray-600 hover:text-gray-800 font-medium"
            >
              Cancel
            </Link>
            <button
              type="submit"
              disabled={saving}
              className="bg-indigo-600 text-white text-sm font-semibold px-6 py-2.5 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition"
            >
              {saving ? 'Saving…' : recordId ? 'Update Record' : 'Create Record'}
            </button>
          </div>
        </form>
      )}

      {/* Policy Period Approval Modal */}
      {approvalModal && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-start justify-center p-4 pt-10 overflow-y-auto" onClick={(e) => { if (e.target === e.currentTarget) setApprovalModal(null) }}>
          <div className="bg-white rounded-xl shadow-xl w-full max-w-md max-h-[85vh] flex flex-col overflow-hidden">
            <div className="px-5 py-3 border-b flex items-center justify-between shrink-0">
              <h3 className="font-semibold text-gray-800">Authorization Required</h3>
              <button onClick={() => setApprovalModal(null)} className="text-gray-400 hover:text-gray-600 text-xl leading-none">✕</button>
            </div>
            <div className="px-5 py-4 space-y-3 overflow-y-auto grow min-h-0">
              <div className="bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-sm text-amber-800">
                A policy period of <strong>{approvalModal.pendingValue} months</strong> requires management authorization.
              </div>
              {recordId ? (
                <p className="text-sm text-gray-600">Click <strong>Approve</strong> to authorize and apply this policy period. This action is logged.</p>
              ) : (
                <p className="text-sm text-gray-600">Please save the record first, then return to authorize the policy period.</p>
              )}
            </div>
            <div className="flex justify-end gap-2 px-5 py-4 border-t bg-gray-50 shrink-0">
              <button onClick={() => setApprovalModal(null)} className="px-4 py-2 text-sm border rounded hover:bg-gray-100">Cancel</button>
              {recordId && (
                <button onClick={approvePeroidConfirm} disabled={approvingPeriod}
                  className="px-4 py-2 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-50">
                  {approvingPeriod ? 'Approving…' : 'Approve Policy Period'}
                </button>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

/** Policy Wording upload/download section — shown for all specialist products */
function PolicyWordingSection({ policyId, type, recordId, existingPath, existingFilename }: { policyId: number; type: string; recordId: number | null; existingPath?: string; existingFilename?: string }) {
  const [uploading, setUploading] = useState(false)
  const [currentPath, setCurrentPath] = useState(existingPath || '')
  const [currentFilename, setCurrentFilename] = useState<string>(existingFilename || '')
  const [lastUploadedName, setLastUploadedName] = useState<string>('')
  const [pendingFile, setPendingFile] = useState<File | null>(null)
  // The most-recently-picked file from the <input>, kept visible from the
  // moment of selection through to upload completion. Operators reported
  // "still not showing which file is uploading" — the browser's own input
  // text gets reset, so we mirror it into a state we control.
  const [selectedFile, setSelectedFile] = useState<File | null>(null)
  const [uploadError, setUploadError] = useState<string>('')

  useEffect(() => {
    if (existingPath !== undefined) setCurrentPath(existingPath || '')
  }, [existingPath])

  useEffect(() => {
    if (existingFilename !== undefined) setCurrentFilename(existingFilename || '')
  }, [existingFilename])

  // Friendly filename for the "Current Policy Wording" block: prefer the
  // explicit policy_wording_filename column, fall back to extracting the
  // basename from the storage path, fall back to the path itself.
  const displayFilename = currentFilename
    || (currentPath ? currentPath.split('/').pop() || currentPath : '')

  async function uploadFile(file: File, rid: number) {
    setUploading(true)
    setUploadError('')
    try {
      const fd = new FormData()
      fd.append('policy_wording_file', file)
      fd.append('record_id', String(rid))
      const { data } = await apiClient.post(
        `/policies/${policyId}/specialist-coverages/${type}/upload-wording`,
        fd,
        { headers: { 'Content-Type': 'multipart/form-data' } },
      )
      const persisted = data.path || data.url || ''
      setCurrentPath(persisted || file.name)
      setCurrentFilename(data.filename || file.name)
      setLastUploadedName(file.name)
      setPendingFile(null)
    } catch (err: any) {
      const v = err?.response?.data?.errors
      const flat = v && typeof v === 'object'
        ? Object.values(v).flat().join('\n')
        : ''
      const msg = flat || err.response?.data?.message || err.response?.data?.error || err.message || 'Upload failed'
      setUploadError(msg)
    } finally {
      setUploading(false)
    }
  }

  useEffect(() => {
    if (recordId && pendingFile) {
      uploadFile(pendingFile, recordId)
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [recordId])

  async function handleUpload(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0]
    if (!file) return
    setSelectedFile(file)
    setUploadError('')
    if (!recordId) {
      setPendingFile(file)
      return
    }
    await uploadFile(file, recordId)
  }

  function fmtSize(bytes: number) {
    if (bytes < 1024) return bytes + ' B'
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
    return (bytes / 1024 / 1024).toFixed(2) + ' MB'
  }

  async function handleDownload() {
    try {
      const resp = await apiClient.get(
        `/policies/${policyId}/specialist-coverages/${type}/download-wording/${recordId}`,
        { responseType: 'blob' }
      )
      // The backend returns the PDF as octet stream; if the server actually
      // sent JSON (e.g. 200 wrapped error) the blob's MIME type will reveal it.
      const blob: Blob = resp.data
      if (blob.type && blob.type.includes('json')) {
        const text = await blob.text()
        let msg = 'Could not download policy wording.'
        try { msg = (JSON.parse(text).error || JSON.parse(text).message) ?? msg } catch { /* keep generic */ }
        alert(msg)
        return
      }
      const url = URL.createObjectURL(blob)
      window.open(url, '_blank')
    } catch (err: any) {
      // axios threw — most likely a non-2xx response. With responseType:'blob'
      // the JSON error body comes through as a Blob, so read it back as text
      // and surface the real reason instead of the generic alert.
      let msg = err?.message || 'Could not download policy wording.'
      const data = err?.response?.data
      if (data instanceof Blob) {
        try {
          const text = await data.text()
          const parsed = JSON.parse(text)
          msg = parsed.error || parsed.message || msg
        } catch { /* leave default */ }
      } else if (data && typeof data === 'object') {
        msg = data.error || data.message || msg
      }
      alert(`Could not download policy wording: ${msg}`)
    }
  }

  return (
    <div className="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
      <div className="bg-amber-50 px-4 py-3 border-b border-amber-100">
        <h2 className="text-sm font-semibold text-amber-800">Policy Wording</h2>
      </div>
      <div className="p-4 space-y-3">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Upload Policy Wording Document</label>
          <input
            type="file"
            accept=".pdf"
            onChange={handleUpload}
            disabled={uploading}
            className="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-md file:border file:border-gray-300 file:text-sm file:font-medium file:bg-gray-50 file:text-gray-700 hover:file:bg-gray-100"
          />
          <p className="mt-1 text-xs text-gray-400">Accepted format: PDF only (max 20MB)</p>

          {/* Picked-file pill — visible from the moment the user picks the
              file all the way through to upload completion. Replaces the
              browser's native "no file chosen" text which resets too eagerly. */}
          {selectedFile && (
            <div className={`mt-2 flex items-center gap-2 px-3 py-2 rounded border text-xs
              ${uploading ? 'bg-blue-50 border-blue-200 text-blue-800'
                : uploadError ? 'bg-red-50 border-red-200 text-red-800'
                : lastUploadedName === selectedFile.name ? 'bg-green-50 border-green-200 text-green-800'
                : 'bg-amber-50 border-amber-200 text-amber-800'}`}>
              <span className="font-mono text-[14px]">📄</span>
              <div className="flex-1 min-w-0">
                <div className="font-medium truncate">{selectedFile.name}</div>
                <div className="text-[10px] opacity-75">{fmtSize(selectedFile.size)}</div>
              </div>
              <span className="text-xs font-medium whitespace-nowrap">
                {uploading ? '⏳ Uploading…'
                  : uploadError ? '✕ Failed'
                  : lastUploadedName === selectedFile.name ? '✓ Uploaded'
                  : !recordId ? 'Will upload after Create Record'
                  : 'Selected'}
              </span>
            </div>
          )}

          {uploadError && (
            <p className="mt-2 text-xs text-red-700 bg-red-50 border border-red-200 rounded px-2 py-1 whitespace-pre-line">
              <strong>Upload failed:</strong> {uploadError}
            </p>
          )}

          {!recordId && !selectedFile && (
            <p className="mt-1 text-xs text-gray-500 italic">
              You can pick a file now — it'll upload automatically the moment you click Create Record below.
            </p>
          )}
        </div>

        {currentPath ? (
          <div className="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3">
            <p className="text-sm font-medium text-blue-800">Current Policy Wording</p>
            <div className="mt-1 flex items-center gap-2">
              <span className="text-base">📄</span>
              <span className="text-sm font-medium text-blue-900 truncate" title={currentPath}>
                {displayFilename}
              </span>
            </div>
            {currentPath !== displayFilename && (
              <p className="text-[10px] text-blue-500/70 mt-1 truncate font-mono" title={currentPath}>
                {currentPath}
              </p>
            )}
            <button
              onClick={handleDownload}
              className="mt-2 px-4 py-1.5 bg-blue-600 text-white text-xs rounded-md hover:bg-blue-700"
            >
              View / Download Policy Wording
            </button>
          </div>
        ) : recordId ? (
          <p className="text-xs text-gray-500 italic">
            No policy wording uploaded yet for this record.
          </p>
        ) : null}
      </div>
    </div>
  )
}

// ─── Coverage-level Miscellaneous Items ──────────────────────────────────────
// Self-contained section mirroring the wizard's Misc Items: dropdown picks
// from specified_coverage_items for the policy_coverage, rate auto-fills from
// the master, premium = sum × rate / 100. Add immediately POSTs; delete
// soft-deletes via the existing PUT endpoint (no in-place edit by design —
// matches the motor-modal pattern in PolicyDetailPage). Used on Travel
// Insurance and engineering specialist schedules (EAR/CAR/PAR/Machinery
// Breakdown) so operators don't have to bounce back to the wizard.

interface MiscItemMaster {
  id: number
  name: string
  rate: string
}

interface MiscItemRow {
  id: number
  specified_coverage_id: number | null
  name: string
  isCustom: boolean
  sum_insured: string
  rate: string
  calculated_value: string
  deleted_at: string | null
}

function CoverageMiscItemsSection({ policyId, policyCoverageId }: { policyId: number; policyCoverageId: number }) {
  const [rows, setRows] = useState<MiscItemRow[]>([])
  const [masterItems, setMasterItems] = useState<MiscItemMaster[]>([])
  const [loading, setLoading] = useState(false)
  const [adding, setAdding] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [addOpen, setAddOpen] = useState(false)
  const [newMasterId, setNewMasterId] = useState<string>('')
  const [newSum, setNewSum] = useState<string>('')
  const [newRate, setNewRate] = useState<string>('')

  const loadAll = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const { data } = await apiClient.get(`/policies/${policyId}/coverages/${policyCoverageId}/specified-items`)
      setRows(data?.data ?? [])
      const coverageMasterId = data?.coverage_id
      if (coverageMasterId) {
        try {
          const { data: opts } = await apiClient.get(`/lookups/coverages/${coverageMasterId}/specified-items`)
          setMasterItems((opts?.data ?? []).map((s: any) => ({ id: s.id, name: s.name, rate: String(s.rate ?? 0) })))
        } catch {
          setMasterItems([])
        }
      }
    } catch (e: any) {
      setError(e?.response?.data?.error || e?.message || 'Failed to load miscellaneous items.')
    } finally {
      setLoading(false)
    }
  }, [policyId, policyCoverageId])

  useEffect(() => { loadAll() }, [loadAll])

  const resetAdd = () => { setNewMasterId(''); setNewSum(''); setNewRate('') }

  const computedPremium = (() => {
    const s = parseFloat(newSum); const r = parseFloat(newRate)
    return (!isNaN(s) && !isNaN(r)) ? (s * r / 100).toFixed(2) : ''
  })()

  const submitAdd = async () => {
    if (!newMasterId || !newSum) return
    setAdding(true)
    setError(null)
    try {
      const { data } = await apiClient.post(`/policies/${policyId}/coverages/${policyCoverageId}/specified-items`, {
        specified_coverage_id: Number(newMasterId),
        sum_insured: parseFloat(newSum),
        rate: parseFloat(newRate || '0'),
      })
      setRows(prev => [...prev, data.data])
      resetAdd()
      setAddOpen(false)
    } catch (e: any) {
      setError(e?.response?.data?.error || e?.message || 'Failed to add item.')
    } finally {
      setAdding(false)
    }
  }

  const remove = async (id: number) => {
    if (!window.confirm('Remove this miscellaneous item?')) return
    try {
      await apiClient.put(`/policies/${policyId}/coverages/${policyCoverageId}/specified-items/${id}`, {
        deleted_at: new Date().toISOString(),
      })
      setRows(prev => prev.filter(r => r.id !== id))
    } catch (e: any) {
      setError(e?.response?.data?.error || e?.message || 'Failed to remove item.')
    }
  }

  const visibleRows = rows.filter(r => !r.deleted_at)

  return (
    <div className="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
      <div className="bg-amber-50 px-4 py-3 border-b border-amber-100 flex items-center justify-between">
        <h2 className="text-sm font-semibold text-amber-800">
          Miscellaneous Items {visibleRows.length > 0 && <span className="text-gray-600 font-normal">({visibleRows.length})</span>}
        </h2>
        <button
          type="button"
          onClick={() => { if (addOpen) { resetAdd(); setAddOpen(false) } else setAddOpen(true) }}
          disabled={loading || masterItems.length === 0}
          title={masterItems.length === 0 ? 'No specified-item master entries configured for this coverage' : ''}
          className="text-xs font-medium text-amber-800 bg-amber-100 border border-amber-300 rounded px-3 py-1 hover:bg-amber-200 disabled:opacity-40"
        >
          {addOpen ? 'Cancel' : '+ Add Item'}
        </button>
      </div>

      {error && <div className="px-4 py-2 text-xs text-red-700 bg-red-50 border-b border-red-100">{error}</div>}

      {loading ? (
        <p className="px-4 py-3 text-xs text-gray-500 italic">Loading miscellaneous items…</p>
      ) : (
        <>
          {visibleRows.length === 0 ? (
            <p className="px-4 py-3 text-xs text-gray-500 italic">
              {masterItems.length === 0
                ? 'No specified-item master entries configured for this coverage. Add rows via Coverage Management → Specified Coverage Items.'
                : 'No items added yet. Click + Add Item to pick from the configured items for this coverage.'}
            </p>
          ) : (
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-[10px] text-gray-500 border-b bg-gray-50">
                  <th className="px-3 py-2 w-[40%]">Item Description</th>
                  <th className="px-3 py-2 w-[20%]">Sum Insured</th>
                  <th className="px-3 py-2 w-[14%]">Rate %</th>
                  <th className="px-3 py-2 w-[20%]">Premium</th>
                  <th className="px-3 py-2 w-[6%]"></th>
                </tr>
              </thead>
              <tbody>
                {visibleRows.map(r => (
                  <tr key={r.id} className="border-b hover:bg-gray-50">
                    <td className="px-3 py-2 text-gray-700">{r.name || `Item #${r.id}`}</td>
                    <td className="px-3 py-2">P {Number(r.sum_insured || 0).toLocaleString()}</td>
                    <td className="px-3 py-2">{r.rate ? Number(r.rate).toFixed(4).replace(/\.?0+$/, '') : '—'}</td>
                    <td className="px-3 py-2 font-medium">P {Number(r.calculated_value || 0).toLocaleString()}</td>
                    <td className="px-3 py-2 text-center">
                      <button type="button" onClick={() => remove(r.id)} className="text-red-500 hover:text-red-700 text-sm font-bold">✕</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}

          {addOpen && masterItems.length > 0 && (
            <div className="px-4 py-3 bg-amber-50/40 border-t border-amber-100 grid grid-cols-12 gap-2 items-end">
              <div className="col-span-5">
                <label className="block text-[10px] text-gray-500 mb-0.5">Item</label>
                <select
                  value={newMasterId}
                  onChange={e => {
                    const id = e.target.value
                    setNewMasterId(id)
                    const m = masterItems.find(mi => String(mi.id) === id)
                    setNewRate(m ? m.rate : '')
                  }}
                  className="w-full px-2 py-1.5 border rounded text-xs bg-white"
                >
                  <option value="">— Select item —</option>
                  {masterItems.map(m => (
                    <option key={m.id} value={m.id}>{m.name}{m.rate ? ` (rate ${m.rate}%)` : ''}</option>
                  ))}
                </select>
              </div>
              <div className="col-span-3">
                <label className="block text-[10px] text-gray-500 mb-0.5">Sum Insured</label>
                <input
                  type="number" step="0.01" min="0"
                  value={newSum}
                  onChange={e => setNewSum(e.target.value)}
                  placeholder="0.00"
                  className="w-full px-2 py-1.5 border rounded text-xs"
                />
              </div>
              <div className="col-span-2">
                <label className="block text-[10px] text-gray-500 mb-0.5">Rate %</label>
                <input
                  type="text" value={newRate} readOnly
                  title="Rate is managed on the specified-item master"
                  className="w-full px-2 py-1.5 border rounded text-xs bg-gray-100 text-gray-600"
                />
              </div>
              <div className="col-span-1">
                <label className="block text-[10px] text-gray-500 mb-0.5">Premium</label>
                <div className="w-full px-2 py-1.5 border rounded text-xs bg-gray-50 text-gray-600">{computedPremium || '—'}</div>
              </div>
              <div className="col-span-1">
                <button
                  type="button"
                  onClick={submitAdd}
                  disabled={adding || !newMasterId || !newSum}
                  className="w-full px-3 py-1.5 text-xs font-medium text-white bg-amber-600 rounded hover:bg-amber-700 disabled:opacity-50"
                >
                  {adding ? '…' : '+ Add'}
                </button>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  )
}
