/**
 * DOM/COM Policy Creation Wizard
 *
 * This wizard creates Domestic (DOMG) and Commercial (COMG) policies only.
 * MIS/Instant policies are created from Start.alphadirect.co.bw and LiveQuote — not here.
 *
 * Flow (mirrors old Graphite exactly):
 *   Step 1: Policy & Customer Details (+ Binder Date, COMG fields)
 *   Step 2: Risk Addresses (property details, safety features)
 *   Step 3: Coverages (select from master, sum insured, rate, premium)
 *   Step 4: Review & Submit (KYC docs, summary, submit for approval → issue)
 */
import { useState, useCallback, useEffect, useRef, type ReactNode } from 'react'
import { useNavigate, useParams, useLocation, Link } from 'react-router-dom'
import { useMutation } from '@tanstack/react-query'
import { createPolicy, updatePolicy, fetchPolicyEditData, addRiskAddress, updateRiskAddress, deleteRiskAddress, reinstateRiskAddress, addCoverage, updateCoverage, deleteCoverage, reinstateCoverage, downloadExcelTemplate, importExcelData } from '../../api/policyCreate'
import type { CreatePolicyPayload, RiskAddressPayload, UpdatePolicyPayload, ExcelImportType } from '../../api/policyCreate'
import { useCoveragesByProduct } from '../../hooks/useLookups'
import SmartUwPrefillPanel from './CreateWizard/SmartUwPrefillPanel'
import { applyExtraction, buildSectionChildren, toFormChildren,
  applyVehicles, attachVehicleToMotor, motorCoverageTargets,
  type ApplyResult, type SmartUwMotorRow } from './CreateWizard/smartUwApply'
import { numStr, ratePercent, isMotorCoverageCode, looksLikeExcess, insuredMatchesPolicy,
  type SmartUwRisk, type SmartUwCoverage, type SmartUwDetail,
  type CoverageMasterLite } from './CreateWizard/smartUwPrefill'
import { getCachedLookups } from '../../api/auth'
import apiClient from '../../api/client'
import LoadingSpinner from '../../components/common/LoadingSpinner'
import { StepPolicyDetails, StepRiskAddresses, StepCoverages } from './CreateWizard'
import { generateTestPolicy } from './CreateWizard/testData'
import {
  type PolicyFormData, type RiskAddressForm, type SavedRiskAddress,
  type CoverageForm,
  // KycDocuments — kept in sync with the commented-out KYC Documents
  // section below; un-comment when restoring the inline KYC upload UI.
  // type KycDocuments,
  type PolicyResult,
  INITIAL_FORM, INITIAL_RISK, INITIAL_COVERAGE,
} from './CreateWizard/types'
// Imports kept in sync with the commented-out KYC Documents section below
// (search "KYC Documents — commented out"); un-comment both when restoring.
// import { FileUpload, Section } from './CreateWizard/FormField'
import { formatCurrency, isCommercialInsuranceProduct, isCompanyOnlyProduct, isOrganisationOnlyProduct, ANNUAL_FREQ } from './CreateWizard/helpers'

const DRAFT_KEY = 'domcom_policy_draft'

// UAT 2026-06-03 (Satyajeet): products that use the coverage-based wizard
// (Commercial / Domestic / Specialist). Mirrors PolicyDetailPage's
// COVERAGE_PRODUCT_IDS — kept in sync to avoid drift. MIS retail products
// (ACD / HCB / Legal / Mobile&Electronic / Third Party Car) don't use
// policy_coverages and don't have a working V2 edit flow yet.
const COVERAGE_PRODUCT_IDS = [7, 8, 16, 17, 18, 20, 22, 23, 24]

/**
 * Transaction types whose action OWNS a full policy period, so the Term Start /
 * Expiry dates on it ARE the policy term and are legitimately editable while the
 * action is still a QUOTE.
 *
 * Mirrors the backend's `$fullPeriodTypes` in PolicyCreateController — keep the
 * two in step. ENDORSE is deliberately absent: an endorsement's effective range
 * is a mid-term sub-period, and editing the term from there would silently move
 * the whole policy period.
 */
const FULL_PERIOD_TX_TYPES = ['NEWBUSINESS', 'ANNIVERSARY-RENEW', 'RENEW', 'REINSTATE', 'REISSUE']

/**
 * risk_address API row → wizard card/form shape.
 *
 * Used by every post-mutation refresh (cancel / reinstate / edit) so a
 * refreshed card carries the same fields the Edit form binds to. The cancel
 * and reinstate handlers previously spread the raw row (`{...ra}`), which
 * left booleans as 0/1 and the numeric columns as numbers — fine for the
 * badges, wrong as a prefill source for the edit form.
 */
const hydrateRiskAddress = (ra: any): SavedRiskAddress => ({
  id:                  ra.id,
  address_name:        ra.address_name ?? '',
  physical_address:    ra.physical_address ?? '',
  lat:                 ra.lat != null ? String(ra.lat) : '',
  lng:                 ra.lng != null ? String(ra.lng) : '',
  risk_state:          ra.risk_state ?? null,
  risk_city:           ra.risk_city ?? null,
  const_type:          ra.const_type ?? '',
  central_fire:        !!ra.central_fire,
  central_burglar:     !!ra.central_burglar,
  gated_community:     !!ra.gated_community,
  automatic:           !!ra.automatic,
  extension:           ra.extension ?? '',
  occupation:          ra.occupation ?? '',
  year_built:          ra.year_built ?? '',
  area:                ra.area ?? '',
  structure_type:      ra.structure_type ?? '',
  town_class:          ra.town_class ?? '',
  risk_class:          ra.risk_class ?? '',
  iso_rcv:             ra.iso_rcv != null ? String(ra.iso_rcv) : '',
  distance_to_water:   ra.distance_to_water ?? '',
  distance_to_fire:    ra.distance_to_fire ?? '',
  distance_to_hydrant: ra.distance_to_hydrant ?? '',
  usage:               ra.usage ?? '',
  occupancy_type:      ra.occupancy_type ?? '',
  // Cancelled-state marker — drives the Reinstate button on the card.
  deleted_at:          ra.deleted_at ?? null,
} as SavedRiskAddress)

export default function PolicyCreatePage() {
  const navigate = useNavigate()
  const location = useLocation()
  const { id: editPolicyId } = useParams<{ id: string }>()
  const isEditMode = !!editPolicyId
  // Query string can pre-select which action to edit. Mirrors legacy
  // EditWizard which was opened with an explicit actionId. Operators landing
  // from the Policy Actions tab want THAT action, not a guessed latest.
  const initialActionIdFromUrl = (() => {
    try { return Number(new URLSearchParams(window.location.search).get('action_id')) || null }
    catch { return null }
  })()

  // Declared up here, beside its initialiser, because render-time derivations
  // further down read it — smartUwApplyCtx() is *called* during render by
  // `smartUwMotorTargets`. A const declared below its first read throws
  // "Cannot access 'x' before initialization", which is what crashed
  // /policies/{id}/edit on load (create mode was safe: policyResult is null
  // there, so the ctx was never built).
  const [selectedActionId, setSelectedActionId] = useState<number | null>(initialActionIdFromUrl)

  // ─── Core State ──────────────────────────────────────
  const [isEditLoading, setIsEditLoading] = useState(isEditMode)
  // UAT 2026-06-03: set when an MIS policy is opened via direct URL — wizard
  // is hardwired to the DomCom shape and would render an empty Risk Addresses
  // section. We catch the case in the edit-mode loader and render a notice
  // instead of dumping the user into the broken wizard.
  const [misNotEditable, setMisNotEditable] = useState(false)
  // Accordion: track which sections are open.
  // Edit mode → all open. Create mode → only 'policy' open initially.
  // Section order: policy → risk-address-import → excel → risk → coverages → review
  const [openSections, setOpenSections] = useState<Set<string>>(
    isEditMode ? new Set(['policy', 'risk-address-import', 'excel', 'risk', 'coverages', 'review']) : new Set(['policy'])
  )
  const toggleSection = (key: string) => setOpenSections(prev => {
    const next = new Set(prev); next.has(key) ? next.delete(key) : next.add(key); return next
  })
  const [form, setForm] = useState<PolicyFormData>({ ...INITIAL_FORM })
  const [riskForm, setRiskForm] = useState<RiskAddressForm>({ ...INITIAL_RISK })
  const [savedAddresses, setSavedAddresses] = useState<SavedRiskAddress[]>([])
  // Set while a saved risk address is being edited in place (mirrors
  // editingCoverageId below). Null = the form is an "add" form.
  const [editingRiskAddressId, setEditingRiskAddressId] = useState<number | null>(null)
  const [coverageForm, setCoverageForm] = useState<CoverageForm>({ ...INITIAL_COVERAGE })
  const [editingCoverageId, setEditingCoverageId] = useState<number | null>(null)
  const [editingCoverageIndex, setEditingCoverageIndex] = useState<number | null>(null)
  const editCovIndexRef = useRef<number | null>(null)
  const initialCoveragesLoaded = useRef(false)
  const lastSubmittedCovRef = useRef<CoverageForm>(coverageForm)
  const [savedCoverages, setSavedCoverages] = useState<CoverageForm[]>([])

  // Coverages still need API (product-specific, too large to cache).
  // Hoisted above the Smart Underwriting block for the same reason as
  // selectedActionId above: smartUwApplyCtx() reads it during render.
  const { data: availableCoverages = [] } = useCoveragesByProduct(form.product_id && form.product_id > 0 ? form.product_id : null)

  // ─── Smart Underwriting prefill ──────────────────────
  // Arriving from the Smart Underwriting Upload page's "Review & Issue" button:
  // the extracted broker schedule rides in location.state. Pre-fill the
  // unambiguous customer / date / risk-address fields so the underwriter only
  // confirms product / agency / plan / coverages and issues via the normal flow.
  // Guarded + one-shot — a normal /policies/create visit carries no such state,
  // so this is a no-op and the standard wizard behaviour is unchanged.
  const smartUwApplied = useRef(false)
  useEffect(() => {
    const risk = (location.state as any)?.smartUwRisk
    if (!risk || smartUwApplied.current) return
    // Edit mode = the schedule was uploaded against an EXISTING policy and a
    // chosen action. That policy already has a customer, a term and a risk
    // address on file, all of them authoritative. Overwriting them from a
    // broker schedule would silently rewrite live policy data, so the prefill
    // is new-business only. (Coverage prefill, when it lands, is the part that
    // is genuinely wanted here — it is additive and safe in both modes.)
    if (isEditMode) return
    smartUwApplied.current = true
    try {
      const cust = risk.customer || {}
      const isOrg = cust.entity_type === 'Organisation'
      const parts = String(cust.name || '').trim().split(/\s+/).filter(Boolean)
      const firstName = parts.shift() || ''
      const lastName = parts.join(' ')
      setForm(prev => ({
        ...prev,
        entity_type: isOrg ? 'Organisation' : prev.entity_type,
        first_name: isOrg ? prev.first_name : (firstName || prev.first_name),
        last_name: isOrg ? prev.last_name : (lastName || prev.last_name),
        email: cust.email || prev.email,
        cellphone: cust.phone || prev.cellphone,
        // postal FIRST — the field is the customer's postal address, and a
        // schedule that carries both was writing the physical one into it.
        post_address: cust.postal_address || cust.physical_address || prev.post_address,
        term_start_date: risk.policy?.term_start_date || prev.term_start_date,
        expiry_date: risk.policy?.expiry_date || prev.expiry_date,
      }))
      const loc = risk.risk_location || {}
      if (loc.name || loc.physical_address) {
        setRiskForm(prev => ({
          ...prev,
          address_name: loc.name || prev.address_name,
          physical_address: loc.physical_address || prev.physical_address,
        }))
        // Reveal the Risk Address section. On a normal /policies/create visit
        // only 'policy' is open, so a prefilled risk location sat inside a
        // collapsed panel: the operator never saw the extracted site, saved
        // the policy without it, and the address had to be re-typed later.
        // State/city/construction type are still required and still theirs to
        // pick — a broker schedule carries none of them.
        setOpenSections(prev => new Set(prev).add('risk'))
      }
    } catch {
      /* best-effort prefill — never block the wizard */
    }
  }, [location.state, isEditMode])

  // Canonical Total Premium from /edit-data (= policy_actions.annual_premium,
  // covers all 5 buckets including policy_coverages_data which the local
  // reduce can't see). recomputeActionTotals keeps it fresh, so this matches
  // Rate banner / V2 Quote / Policy Actions Total Coverage Premium.
  const [backendTotalPremium, setBackendTotalPremium] = useState<number>(0)
  // Pro-rata billable for ENDORSE actions — = policy_actions.premium, the
  // delta the operator will be charged/refunded on this endorsement (negative
  // on cancel-risk-address / cancel-coverage). 0 for non-ENDORSE.
  const [backendProRataPremium, setBackendProRataPremium] = useState<number>(0)
  // KYC state kept in sync with the commented-out KYC Documents section
  // below — un-comment both when restoring the inline KYC upload UI.
  // const [kycDocs, setKycDocs] = useState<KycDocuments>({ driving_license: null, omang_doc: null, proof_of_residence: null, proof_of_income: null, passport_doc: null })
  // const [kycSaving, setKycSaving] = useState(false)
  // const [kycMessage, setKycMessage] = useState<{ type: 'ok' | 'err'; text: string } | null>(null)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [policyResult, setPolicyResult] = useState<PolicyResult | null>(null)
  const [showDraftBanner, setShowDraftBanner] = useState(false)

  // ─── Smart Underwriting: risk address on an EXISTING action ─────────
  // The prefill above is new-business only, because overwriting a live
  // policy's customer/term from a broker schedule would rewrite authoritative
  // data. The risk address is the one exception, and it is additive:
  //
  //   this action has NO risk address  -> offer the extracted one (form filled,
  //                                       section opened; the operator saves it)
  //   this action already has one      -> leave it alone and use it
  //
  // Nothing is written without the operator clicking Add — a schedule must not
  // silently create rows on a live policy. It also has to wait for the action's
  // addresses to load, or an empty savedAddresses on first render would look
  // like "none" on every policy. Note the backend only accepts risk-address
  // writes on a QUOTE action, so on an issued one the form is prefilled but
  // saving is (correctly) refused there.
  const smartUwRiskAddrApplied = useRef(false)
  useEffect(() => {
    const risk = (location.state as any)?.smartUwRisk
    if (!risk || !isEditMode || smartUwRiskAddrApplied.current) return
    // Wait for this action's own addresses; 'loaded' is stamped by the
    // edit-data hydrator once risk_addresses have been applied.
    if (policyResult?.status !== 'loaded') return
    smartUwRiskAddrApplied.current = true

    // Already has one on this action — reuse it, add nothing.
    if (savedAddresses.length > 0) return

    const loc = risk.risk_location || {}
    if (!loc.name && !loc.physical_address) return
    setRiskForm(prev => ({
      ...prev,
      address_name: loc.name || prev.address_name,
      physical_address: loc.physical_address || prev.physical_address,
    }))
    setOpenSections(prev => new Set(prev).add('risk'))
  }, [location.state, isEditMode, policyResult?.status, savedAddresses.length])

  // ─── Smart Underwriting: the extracted schedule, inside the wizard ────
  // The two effects above carry the customer / dates / risk address across.
  // Everything else the extractor read — the coverage sections, their sums
  // insured and rates, the vehicle schedule — had no consumer at all, so an
  // operator who had just watched an extraction succeed saw none of it here.
  // The panel below renders it and loads a section into the coverage form on
  // demand; it never writes, so Add Coverage stays the only write path.
  const smartUwRisk = (location.state as any)?.smartUwRisk as SmartUwRisk | undefined
  const smartUwSegment = (location.state as any)?.smartUwSegment as string | undefined
  const [smartUwLoadedIndex, setSmartUwLoadedIndex] = useState<number | null>(null)
  // Detail lines waiting to be written into the selected coverage's
  // subcoverage rows. StepCoverages owns those rows — it fetches the master
  // template when coverage_id changes — so the lines are handed down and
  // applied there once the template has actually arrived.
  const [smartUwLines, setSmartUwLines] =
    useState<{ coverageId: number; lines: SmartUwDetail[] } | null>(null)

  const [smartUwAddrLoaded, setSmartUwAddrLoaded] = useState(false)

  // Extensions the loaded section named but this coverage does not offer.
  // Reported, never invented — the operator adds them by hand or picks another
  // coverage.
  const [smartUwUnplaced, setSmartUwUnplaced] = useState<string[]>([])
  // Which section is mid-load. Loading fetches the coverage's extension and
  // misc-item masters before it can fill the grids, so the button has to say
  // it is working rather than look ignored.
  const [smartUwLoadingIndex, setSmartUwLoadingIndex] = useState<number | null>(null)

  // Load the schedule's site into the risk-address form. Deliberately separate
  // from the two prefill effects above, which only fire on a BLANK slate (new
  // business, or an action with no address at all). On an endorsement of a
  // policy that already carries an address they add nothing and say nothing,
  // so "the risk address from the sheet did not come through" was the expected
  // behaviour with no way to override it. This button is that override: the
  // operator asks for it, and still presses Add themselves.
  /**
   * Scroll the form a Smart Upload "Load into form" just filled into view.
   *
   * The panel sits above forms that can be a screen or two further down, so
   * the fill happened off-screen and the button read as doing nothing. The
   * anchor may not be in the DOM yet — the accordion section opens in the
   * same state update — so poll briefly instead of firing once into an empty
   * document.
   */
  const scrollToFormAnchor = (anchorId: string, attempt = 0) => {
    const el = document.getElementById(anchorId)
    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'start' })
      return
    }
    if (attempt < 10) {
      setTimeout(() => scrollToFormAnchor(anchorId, attempt + 1), 50)
    }
  }

  const handleSmartUwLoadRiskAddress = (
    name: string, physicalAddress: string, occupation: string
  ) => {
    if (smartUwInsuredMismatch) {
      setErrors({ _general:
        'Not matching — the schedule is for "' + smartUwInsuredMismatch.insured
        + '" but this policy is "' + smartUwInsuredMismatch.policy
        + '". Nothing was loaded.' })
      return
    }

    setRiskForm((prev) => ({
      ...prev,
      address_name: name || prev.address_name,
      physical_address: physicalAddress || prev.physical_address,
      // Business description doubles as the risk occupation on a commercial
      // schedule; state / city / construction type are required and the
      // schedule carries none, so they stay the operator's to pick.
      occupation: occupation || prev.occupation,
    }))
    setEditingRiskAddressId(null)
    setSmartUwAddrLoaded(true)
    setOpenSections((prev) => new Set(prev).add('risk'))
    scrollToFormAnchor('add-risk-address-form')
  }

  // ── Apply the extraction for real ────────────────────────────────────
  // "Load into form" stops at the form; this writes. It goes through the same
  // addRiskAddress / addCoverage / updateCoverage calls a human Add Coverage
  // makes (see CreateWizard/smartUwApply), so the backend validators, the
  // singleton-family rules, action scoping and the endorse pro-rata stamps all
  // behave identically — and a coverage already on this transaction is EDITED
  // rather than duplicated. Afterwards the wizard reloads from the server, so
  // what you see is what was actually saved, not what we hoped we saved.
  const [smartUwApplying, setSmartUwApplying] = useState(false)
  const [smartUwResult, setSmartUwResult] = useState<ApplyResult | null>(null)

  const handleSmartUwApply = async (
    name: string, physicalAddress: string, occupation: string
  ) => {
    if (!smartUwRisk) return
    // Belt and braces: the button is disabled on a mismatch, but this is the
    // write path and it must never depend on the button being right.
    if (smartUwInsuredMismatch) {
      setErrors({ _general:
        'Not matching — the schedule is for "' + smartUwInsuredMismatch.insured
        + '" but this policy is "' + smartUwInsuredMismatch.policy
        + '". Nothing was written. Open the right policy, or fix the insured name.' })
      return
    }
    if (!policyResult?.policy_id) {
      setErrors({ _general: 'Save the policy details first — coverages are saved against a policy.' })
      return
    }
    setSmartUwApplying(true)
    setSmartUwResult(null)
    try {
      // The fleet as it stands, so applyVehicles can tell add from skip by
      // plate instead of leaning on the backend to reject a duplicate.
      let existingVehicles: Array<{ id: number; vehiclePlate?: string | null }> = []
      try {
        const fleetRes = await apiClient.get(
          `/policies/${policyResult.policy_id}/vehicles`,
          { params: { action_id: selectedActionId ?? policyResult.action_id } }
        )
        existingVehicles = (fleetRes.data?.data ?? []).map((v: any) => ({
          id: Number(v.id), vehiclePlate: v.vehiclePlate,
        }))
      } catch {
        // An unreadable fleet is not a reason to abort the whole apply — the
        // backend still guards the duplicate, it just reports it as a failure
        // instead of a skip.
      }

      const res = await applyExtraction(
        {
          policyId: policyResult.policy_id,
          termId: policyResult.term_id,
          actionId: selectedActionId ?? policyResult.action_id,
          productId: form.product_id,
          availableCoverages,
          savedAddresses,
          savedCoverages: savedCoverages as any,
          riskForm,
        },
        smartUwRisk,
        { name, physicalAddress, occupation },
        existingVehicles
      )
      setSmartUwResult(res)
      // Reload both, in this order: a coverage that was just attached to a
      // brand-new address needs that address in the list before the coverage
      // cards can resolve its name.
      await reloadRiskAddressesFromServer()
      await reloadCoveragesFromServer()
      // Vehicles registered by the apply now exist — refresh so their rows
      // offer "Add to cover" instead of still reading "not registered".
      await refreshSmartUwVehicleState()
    } catch (err: any) {
      setErrors({ _general: err?.response?.data?.message || 'Applying the schedule failed.' })
    } finally {
      setSmartUwApplying(false)
    }
  }

  // ── Vehicles from the schedule: register, then attach to cover ────────
  // The fleet lives in two tables and the extracted rows are in neither, which
  // is why they used to render as a read-only list that "would not load": the
  // Vehicles step and the motor coverage's vehicle picker both read `vehicle`,
  // and nothing had written it. Step 1 registers (POST /policies/{id}/vehicles);
  // step 2 attaches a registered vehicle to a motor coverage
  // (POST /policies/{id}/coverages/{covId}/motor), which is where the sum
  // insured and premium live. Step 2 is per-vehicle because accepting a vehicle
  // onto cover is an underwriting decision, not a data import.
  const [smartUwVehicleState, setSmartUwVehicleState] =
    useState<Record<string, { vehicleId: number | null; onCover: boolean }>>({})
  const [smartUwMotorTargetId, setSmartUwMotorTargetId] = useState<number | null>(null)
  const [smartUwAttaching, setSmartUwAttaching] = useState<string | null>(null)

  const smartUwApplyCtx = () => ({
    policyId: policyResult!.policy_id,
    termId: policyResult!.term_id,
    actionId: selectedActionId ?? policyResult!.action_id,
    productId: form.product_id,
    availableCoverages,
    savedAddresses,
    savedCoverages: savedCoverages as any,
    riskForm,
  })

  const smartUwMotorTargets = policyResult?.policy_id
    ? motorCoverageTargets(smartUwApplyCtx() as any)
    : []

  /**
   * Read the truth back from the server, never from what we just sent.
   *
   * Two questions per plate: is it in the fleet (`vehicle`), and is it on the
   * selected motor coverage (`motor`). Both come from the same endpoints the
   * Vehicles step and StepCoverages use, so the panel cannot drift from what
   * those screens show.
   */
  const refreshSmartUwVehicleState = async (coverageId?: number | null) => {
    if (!policyResult?.policy_id) return
    const covId = coverageId ?? smartUwMotorTargetId
    try {
      const [fleetRes, motorRes] = await Promise.all([
        apiClient.get(`/policies/${policyResult.policy_id}/vehicles`, {
          params: { action_id: selectedActionId ?? policyResult.action_id },
        }),
        covId
          ? apiClient.get(`/policies/${policyResult.policy_id}/coverages/${covId}/motor`)
          : Promise.resolve({ data: { data: [] } } as any),
      ])
      const plateKey = (v: any) => String(v ?? '').trim().toUpperCase()
      const onCover = new Set<string>(
        (motorRes.data?.data ?? [])
          .filter((m: any) => !m.deleted_at)
          .map((m: any) => plateKey(m.registration_no))
      )
      const next: Record<string, { vehicleId: number | null; onCover: boolean }> = {}
      for (const v of (fleetRes.data?.data ?? [])) {
        const p = plateKey(v.vehiclePlate)
        if (p) next[p] = { vehicleId: Number(v.id) || null, onCover: onCover.has(p) }
      }
      setSmartUwVehicleState(next)
    } catch {
      // A failed refresh must not wipe what we know — the buttons would flip
      // back to "not registered" for vehicles that are on the policy.
    }
  }

  /** Step 1 — register every extracted plate on the policy. */
  const handleSmartUwRegisterVehicles = async () => {
    if (!smartUwRisk) return
    if (smartUwInsuredMismatch) {
      setErrors({ _general:
        'Not matching — the schedule is for "' + smartUwInsuredMismatch.insured
        + '" but this policy is "' + smartUwInsuredMismatch.policy
        + '". No vehicle was registered.' })
      return
    }
    if (!policyResult?.policy_id) {
      setErrors({ _general: 'Save the policy details first — vehicles are saved against a policy.' })
      return
    }
    if (!savedAddresses.length) {
      setErrors({ _general: 'Add the risk address first — a vehicle is registered against one.' })
      return
    }
    const rows = (Array.isArray(smartUwRisk.motor) ? smartUwRisk.motor : []) as SmartUwMotorRow[]
    if (!rows.length) return

    setSmartUwApplying(true)
    try {
      const fleetRes = await apiClient.get(
        `/policies/${policyResult.policy_id}/vehicles`,
        { params: { action_id: selectedActionId ?? policyResult.action_id } }
      )
      const existing = (fleetRes.data?.data ?? []).map((v: any) => ({
        id: Number(v.id), vehiclePlate: v.vehiclePlate,
      }))
      const outcomes = await applyVehicles(
        smartUwApplyCtx() as any, rows, savedAddresses[0]?.id ?? null, existing
      )
      setSmartUwResult((prev) => ({
        riskAddressId: prev?.riskAddressId ?? (savedAddresses[0]?.id ?? null),
        riskAddress: prev?.riskAddress ?? null,
        coverages: prev?.coverages ?? [],
        vehicles: outcomes,
      }))
      await refreshSmartUwVehicleState()
    } catch (err: any) {
      setErrors({ _general: err?.response?.data?.message || 'Registering the vehicles failed.' })
    } finally {
      setSmartUwApplying(false)
    }
  }

  /** Step 2 — attach one registered vehicle to the selected motor coverage. */
  const handleSmartUwAttachToMotor = async (motorIndex: number) => {
    if (!smartUwRisk || !policyResult?.policy_id) return
    if (smartUwInsuredMismatch) return
    const rows = (Array.isArray(smartUwRisk.motor) ? smartUwRisk.motor : []) as SmartUwMotorRow[]
    const row = rows[motorIndex]
    if (!row) return
    const plate = String(row.registration ?? '').trim().toUpperCase()
    const state = smartUwVehicleState[plate]
    const covId = smartUwMotorTargetId ?? smartUwMotorTargets[0]?.coverageId ?? null
    if (!plate || !state?.vehicleId || !covId) return

    setSmartUwAttaching(plate)
    try {
      const outcome = await attachVehicleToMotor(
        smartUwApplyCtx() as any, covId, state.vehicleId, row
      )
      if (outcome.status === 'failed') {
        setErrors({ _general: outcome.message || 'Could not attach the vehicle to motor cover.' })
      }
      await refreshSmartUwVehicleState(covId)
      await reloadCoveragesFromServer()
    } finally {
      setSmartUwAttaching(null)
    }
  }

  // Default the attach target to the transaction's only motor section, and
  // re-read the per-plate state whenever the target changes — "on cover" is a
  // question about one coverage, so the answer changes with it.
  useEffect(() => {
    const first = smartUwMotorTargets[0]?.coverageId ?? null
    const stillValid = smartUwMotorTargetId
      && smartUwMotorTargets.some((t) => t.coverageId === smartUwMotorTargetId)
    const next = stillValid ? smartUwMotorTargetId : first
    if (next !== smartUwMotorTargetId) setSmartUwMotorTargetId(next)
    if (smartUwRisk && next) refreshSmartUwVehicleState(next)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [smartUwMotorTargets.map((t) => t.coverageId).join(','), !!smartUwRisk])

  /** Coverage codes compare on letters and digits only, as everywhere else. */
  const norm = (v: any) => String(v ?? '').toUpperCase().replace(/[^A-Z0-9]/g, '')

  const handleSmartUwLoad = async (section: SmartUwCoverage, master: CoverageMasterLite) => {
    // A loaded section is one Add Coverage away from the policy, so the
    // insured check applies here too — not only to Apply.
    if (smartUwInsuredMismatch) {
      setErrors({ _general:
        'Not matching — the schedule is for "' + smartUwInsuredMismatch.insured
        + '" but this policy is "' + smartUwInsuredMismatch.policy
        + '". Nothing was loaded.' })
      return
    }

    const allLines = Array.isArray(section.details) ? section.details : []
    // An excess written as a cover line ("EXCESS ON TRUCKS 10% MIN 10000") is
    // an excess row, not a detail row. Apply already routes those out; Load
    // has to as well, or the same line lands in both grids.
    const excessLines = allLines.filter((d) => looksLikeExcess(d.description))
    const lines = allLines.filter((d) => !looksLikeExcess(d.description))
    const idx = (smartUwRisk?.coverages || []).indexOf(section)
    const isDomCom = form.product_id === 7 || form.product_id === 8
    const isMotor = isMotorCoverageCode(master.s_CoverageCode)

    // Money lines only. An excess note ("EXCESS ON TRUCKS 10% MIN 10000") or a
    // bare "Third party" heading carries no figures and must never overwrite
    // one.
    const money = lines.filter(
      (d) => Number(d.sum_insured ?? 0) > 0 || Number(d.premium ?? 0) > 0
    )
    // Lines with no figures ("Transporting groceries (Bokomo Agent)") are
    // underwriting information, not money — Apply keeps them for the same
    // reason.
    const descriptive = lines
      .filter((d) => !Number(d.sum_insured ?? 0) && !Number(d.premium ?? 0))
      .map((d) => String(d.description ?? '').trim())
      .filter(Boolean)
    const siTotal = money.reduce((a, d) => a + Number(d.sum_insured || 0), 0)
    const premTotal = section.section_premium !== null && section.section_premium !== undefined
      ? Number(section.section_premium)
      : money.reduce((a, d) => a + Number(d.premium || 0), 0)

    // The extension / misc / excess rows are fetched BEFORE the form is set,
    // and the form is then set ONCE.
    //
    // This ordering is not cosmetic. StepCoverages seeds its grids in a
    // useEffect keyed on coverage_id: it reads coverageForm.extensions /
    // .specified_items / .excesses, then merges them onto the master template
    // it fetches. Setting coverage_id first and the child rows a moment later
    // means that effect has already run and read empty arrays — the extensions
    // would sit in state, never reach the grid, and never be saved. One
    // update, with everything in it, is what makes them arrive.
    let formRows: { extensions: any[]; specified_items: any[]; excesses: any[] } | null = null
    if (!isMotor) {
      setSmartUwLoadingIndex(idx >= 0 ? idx : null)
      try {
        const children = await buildSectionChildren(section, master, excessLines)
        // toFormChildren, not the raw rows: the grids read all-string entries
        // and merge them onto the master template, while Apply POSTs numbers.
        const rows = toFormChildren(children)
        formRows = rows
        setSmartUwUnplaced([...children.unplacedExts, ...rows.unplacedItems])
      } catch {
        // The lookups are best-effort: a failed fetch must still load the
        // coverage figures rather than losing the whole click.
        setSmartUwUnplaced([])
      } finally {
        setSmartUwLoadingIndex(null)
      }
    } else {
      setSmartUwUnplaced([])
    }

    setCoverageForm((prev) => ({
      ...INITIAL_COVERAGE,
      coverage_id: master.id,
      coverage_name: master.s_CoverageName || master.s_CoverageCode || '',
      // One saved address is the overwhelming case for a schedule segment.
      // With several, the operator picks — the schedule does not say which.
      risk_address_id: savedAddresses.length === 1 ? savedAddresses[0].id : prev.risk_address_id,
      // COM/DOM (7/8) keep every figure in subcoverage rows: the
      // coverage-level Sum Insured trio is not even rendered for those
      // products, so filling it would write a value the operator can neither
      // see nor correct. Motor is the same story — its money lives on the
      // vehicle rows, not on the coverage.
      coverage_value: isDomCom || isMotor ? '' : numStr(siTotal || null),
      rate: isDomCom || isMotor ? '' : ratePercent(siTotal, premTotal, money[0]?.rate ?? null),
      calculated_value: isDomCom || isMotor ? '' : numStr(premTotal || null),
      notes: [section.section, smartUwSegment && `Smart UW: ${smartUwSegment}`]
        .filter(Boolean).join(' · '),
      // Goods In Transit keeps "what is being carried" in its own
      // policy_coverages column. Apply fills it; Load did not, so the same
      // schedule produced a populated column one way and NULL the other.
      ...(norm(master.s_CoverageCode) === 'GOODSINTRANSIT' && descriptive.length > 0
        ? { property_business_being: descriptive.join('; ') }
        : {}),
      // Motor gets none of these: its money lives on the vehicle rows.
      ...(formRows ? {
        extensions: formRows.extensions,
        specified_items: formRows.specified_items,
        excesses: formRows.excesses,
      } : {}),
      // Forces StepCoverages to re-seed its ext / misc / excess grids even
      // when coverage_id has not changed — a second section mapping to the
      // same coverage would otherwise keep the first one's rows.
      _smartUwSeed: Date.now(),
    } as any))
    setSmartUwLines(isMotor ? null : { coverageId: master.id, lines })
    setSmartUwLoadedIndex(idx >= 0 ? idx : null)
    setOpenSections((prev) => new Set(prev).add('coverages'))
    scrollToFormAnchor('add-coverage-form')
  }

  // ─── Action switcher (edit mode) ─────────────────────
  const [availableActions, setAvailableActions] = useState<Array<{
    id: number; transaction_type: string; status: string;
    effective_from: string | null; effective_to: string | null;
    transaction_reason?: string | null;
  }>>([])
  // selectedActionId is declared at the top of the component — see the note there.
  const [reloading, setReloading] = useState(false)

  // ─── Excel Import State ──────────────────────────────
  const [excelFiles, setExcelFiles] = useState<Record<ExcelImportType, File | null>>({
    'risk-address': null, 'coverages': null, 'specified-items': null, 'beneficiaries': null,
  })
  const [excelStatus, setExcelStatus] = useState<Record<ExcelImportType, 'idle' | 'importing' | 'downloading' | 'success' | 'error'>>({
    'risk-address': 'idle', 'coverages': 'idle', 'specified-items': 'idle', 'beneficiaries': 'idle',
  })
  const [excelMessages, setExcelMessages] = useState<Record<ExcelImportType, string>>({
    'risk-address': '', 'coverages': '', 'specified-items': '', 'beneficiaries': '',
  })
  // Import confirmation preview — fetched after any successful import
  const [importPreview, setImportPreview] = useState<{
    risk_addresses: any[]
    coverages: any[]
    beneficiaries_count: number
  } | null>(null)
  const [previewLoading, setPreviewLoading] = useState(false)
  const [importConfirmed, setImportConfirmed] = useState(false)

  // ─── Risk Address Lookups (must be declared BEFORE lookups object) ──
  const [riskAddressLookups, setRiskAddressLookups] = useState<any>({
    extensions: [], occupation_types: [], structure_types: [], usage_types: [], occupancy_types: [], construction_types: [],
  })

  // ─── Lookups — ALL from login cache (zero API calls) ─────
  const cached = getCachedLookups()
  const lookups = {
    products: cached?.products ?? [
      { id: 7, name: 'Commercial Insurance' }, { id: 8, name: 'Domestic Insurance' },
      { id: 16, name: 'Engineering' }, { id: 17, name: 'Commercial Insurance' },
      { id: 18, name: 'Domestic Insurance' }, { id: 20, name: 'Commercial Liabilities' },{ id: 22, name: 'Marine' },
    ],
    premium_frequencies: [
      { id: '1', name: 'MONTHLY' }, { id: '3', name: 'ANNUAL' },
      { id: '5', name: 'QUARTERLY' }, { id: '6', name: 'MANUAL INPUT' },
    ],
    entity_types: [{ id: 'Individual', name: 'Individual' }, { id: 'Organisation', name: 'Organisation' }],
    genders: [{ id: 'Male', name: 'Male' }, { id: 'Female', name: 'Female' }],
    marital_statuses: [{ id: 'Single', name: 'Single' }, { id: 'Married', name: 'Married' }, { id: 'Divorced', name: 'Divorced' }, { id: 'Widowed', name: 'Widowed' }],
    source_of_income: [
      { id: 'unemployed', name: 'Unemployed' }, { id: 'employment', name: 'Employment' },
      { id: 'pensioner_retired', name: 'Pensioner/Retired' }, { id: 'bussiness', name: 'Self-Employment/Business' },
      { id: 'inheritance', name: 'Inheritance' }, { id: 'gifts', name: 'Gifts' }, { id: 'investments', name: 'Investments' },
    ],
    currently_insured: cached?.currently_insured ?? [{ id: 'Not Insured', name: 'Not Insured' }],
    hear_about_alpha: cached?.hear_about_alpha ?? [{ id: 'Referral', name: 'Referral' }],
    uw_statuses: cached?.uw_statuses ?? [
      { id: 'UWOPEN', name: 'UW Open' }, { id: 'PENDINGUW', name: 'Pending UW' },
      { id: 'APPROVEDUW', name: 'Approved UW' },
    ],
    states: cached?.states ?? [],
    banks: cached?.banks ?? [],
    // Risk address lookups (fetched via API)
    extensions: riskAddressLookups.extensions,
    occupation_types: riskAddressLookups.occupation_types,
    structure_types: riskAddressLookups.structure_types,
    usage_types: riskAddressLookups.usage_types,
    occupancy_types: riskAddressLookups.occupancy_types,
    construction_types: riskAddressLookups.construction_types,
  }
  const agencies: any[] = cached?.agencies ?? []
  const agenciesLoading = false
  const companies: any[] = cached?.companies ?? []
  // ── The insured must be the policy holder ────────────────────────────
  // The INSURED on the schedule is the policy name: the company on an
  // Organisation policy, the person on an individual one. If the schedule
  // names someone else, nothing here may be written to this policy — the
  // figures would all be valid and all on the wrong client.
  const policyHolderName = (() => {
    if (form.entity_type === 'Organisation') {
      const c = (companies ?? []).find((x: any) =>
        Number(x.id ?? x.value) === Number(form.company_id))

      return String(c?.name ?? c?.label ?? c?.company_name ?? '').trim()
    }

    return [form.first_name, form.last_name].filter(Boolean).join(' ').trim()
  })()

  const smartUwInsuredName = String((smartUwRisk?.customer as any)?.name ?? '').trim()
  const smartUwInsuredMismatch =
    smartUwInsuredName !== '' && policyHolderName !== ''
      && !insuredMatchesPolicy(smartUwInsuredName, policyHolderName)
      ? { insured: smartUwInsuredName, policy: policyHolderName }
      : null

  // Plans from cache (small dataset), agents/cities via API (too large to cache)
  const allPlans: any[] = cached?.plans ?? []
  const cachedPlans = form.product_id ? allPlans.filter((p: any) => String(p.product_id) === String(form.product_id)) : []
  // Live-fetch fallback: `cached_lookups` is written once at login and never
  // refreshes until the next logout/login, so a user whose cache predates a
  // product's plans (e.g. Engineering / product 16) saw "No options" forever.
  // When the cache has no plans for the selected product, fetch them live from
  // the (already-working) /lookups/products/{id}/plans endpoint so the dropdown
  // self-heals without forcing a re-login.
  const [livePlans, setLivePlans] = useState<any[]>([])
  const [livePlansLoading, setLivePlansLoading] = useState(false)
  const plans = cachedPlans.length ? cachedPlans : livePlans
  const plansLoading = livePlansLoading

  // Agents + Cities fetched on demand (too many rows to cache in login)
  const [agents, setAgents] = useState<any[]>([])
  const [agentsLoading, setAgentsLoading] = useState(false)
  const [cities, setCities] = useState<any[]>([])
  const [citiesLoading, setCitiesLoading] = useState(false)
  const [riskCities, setRiskCities] = useState<any[]>([])
  const [riskCitiesLoading, setRiskCitiesLoading] = useState(false)

  useEffect(() => {
    if (!form.agency_id) { setAgents([]); return }
    setAgentsLoading(true)
    apiClient.get(`/lookups/agencies/${form.agency_id}/agents`).then(r => setAgents(r.data.data ?? r.data ?? [])).catch(() => {}).finally(() => setAgentsLoading(false))
  }, [form.agency_id])

  useEffect(() => {
    // Only fall back to a live fetch when the login cache has no plans for the
    // chosen product — otherwise the cached list (instant) is authoritative.
    if (!form.product_id || cachedPlans.length) { setLivePlans([]); return }
    setLivePlansLoading(true)
    apiClient.get(`/lookups/products/${form.product_id}/plans`)
      .then(r => setLivePlans(r.data.data ?? r.data ?? []))
      .catch(() => {})
      .finally(() => setLivePlansLoading(false))
  }, [form.product_id, cachedPlans.length])

  useEffect(() => {
    if (!form.state) { setCities([]); return }
    setCitiesLoading(true)
    apiClient.get(`/lookups/states/${form.state}/cities`).then(r => setCities(r.data.data ?? r.data ?? [])).catch(() => {}).finally(() => setCitiesLoading(false))
  }, [form.state])

  useEffect(() => {
    if (!riskForm.risk_state) { setRiskCities([]); return }
    setRiskCitiesLoading(true)
    apiClient.get(`/lookups/states/${riskForm.risk_state}/cities`).then(r => setRiskCities(r.data.data ?? r.data ?? [])).catch(() => {}).finally(() => setRiskCitiesLoading(false))
  }, [riskForm.risk_state])

  // Fetch risk address lookups on component mount
  useEffect(() => {
    const baseUrl = import.meta.env.VITE_API_URL || 'http://localhost:8000'
    Promise.all([
      fetch(`${baseUrl}/api/risk-address/extensions`).then(r => r.json()).then(d => d.data ?? []),
      fetch(`${baseUrl}/api/risk-address/occupation-types`).then(r => r.json()).then(d => d.data ?? []),
      fetch(`${baseUrl}/api/risk-address/structure-types`).then(r => r.json()).then(d => d.data ?? []),
      fetch(`${baseUrl}/api/risk-address/usage-types`).then(r => r.json()).then(d => d.data ?? []),
      fetch(`${baseUrl}/api/risk-address/occupancy-types`).then(r => r.json()).then(d => d.data ?? []),
      fetch(`${baseUrl}/api/risk-address/construction-types`).then(r => r.json()).then(d => d.data ?? []),
    ]).then(([extensions, occupations, structures, usages, occupancies, constructions]) => {
      setRiskAddressLookups({
        extensions, occupation_types: occupations, structure_types: structures,
        usage_types: usages, occupancy_types: occupancies, construction_types: constructions,
      })
    }).catch((err) => {
      console.error('Failed to fetch risk address lookups:', err)
    })
  }, [])

  // availableCoverages is declared at the top of the component — see the note there.

  // COM/DOM (product 7/8): only policy_extention_detail rows of type='Extention'
  // count towards premium. Excess / Perils / Memoranda / FirstAmountPayable /
  // BurglarAlarmWarranty rows reuse extention_calculated_value for a limit or
  // excess amount, and the canonical annual (recomputeActionTotals /
  // calculatePremium) excludes them. Mirrors StepCoverages' isDomComProduct.
  const isDomComProduct = form.product_id === 7 || form.product_id === 8
  const isPremiumExtension = (e: any) => !isDomComProduct || ((e?.type as string) || 'Extention') === 'Extention'

// ─── Draft Management ────────────────────────────────
  useEffect(() => {
    if (!isEditMode && localStorage.getItem(DRAFT_KEY)) setShowDraftBanner(true)
  }, [isEditMode])

  const handleLoadDraft = useCallback(() => {
    try {
      const draft = JSON.parse(localStorage.getItem(DRAFT_KEY) || '{}')
      if (draft.form) setForm(draft.form)
      if (draft.savedAddresses) setSavedAddresses(draft.savedAddresses)
      if (draft.savedCoverages) setSavedCoverages(draft.savedCoverages)
      setShowDraftBanner(false)
    } catch { setShowDraftBanner(false) }
  }, [])

  // Auto-save draft every 30s
  useEffect(() => {
    if (isEditMode || !form.first_name) return
    const timer = setInterval(() => {
      localStorage.setItem(DRAFT_KEY, JSON.stringify({ form, savedAddresses, savedCoverages }))
    }, 30000)
    return () => clearInterval(timer)
  }, [form, savedAddresses, savedCoverages, isEditMode])

  // ─── Edit Mode: Load existing policy data ────────────
  useEffect(() => {
    if (!isEditMode || !editPolicyId) return
    setIsEditLoading(true)
    fetchPolicyEditData(Number(editPolicyId), selectedActionId)
      .then(d => {
        // UAT 2026-06-03 (Satyajeet): defence-in-depth for URL-typed edit
        // attempts on MIS policies. The Edit Policy button on PolicyDetailPage
        // is already gated to COVERAGE_PRODUCT_IDS, but a CSR can still paste
        // a URL. Detect the MIS shape and short-circuit to the notice render
        // before any setForm / step rendering happens.
        const productId = d.policy?.product_id ?? 0
        if (productId && !COVERAGE_PRODUCT_IDS.includes(productId)) {
          setMisNotEditable(true)
          setIsEditLoading(false)
          return
        }
        // Capture available actions + which was loaded — so we can render a
        // switcher and re-fetch when the user picks a different action.
        const actions = (d as any).actions ?? []
        if (actions.length > 0) setAvailableActions(actions)
        if (d.action_id && selectedActionId === null) setSelectedActionId(d.action_id)
        // UAT 2026-05-27 (Arjun B3): pre-seed the City dropdown options with
        // the cities-for-saved-state bundled in the response, BEFORE the
        // setForm call below. Otherwise the useEffect on [form.state] fires
        // a fresh /lookups/states/{X}/cities request and the City dropdown
        // renders blank for ~100-300 ms until that resolves — exactly Arjun
        // B3's "City empty after edit-open" repro on MIS2026212484.
        const preFetchedCities = (d as any).cities_for_saved_state ?? []
        if (preFetchedCities.length > 0) {
          setCities(preFetchedCities)
        }
        // Pre-populate form with existing policy + customer data
        setForm({
          product_id:        d.policy.product_id,
          plan_id:           d.policy.plan_id,
          agency_id:         d.policy.agency_id,
          agent_id:          d.policy.agent_id,
          premium_freq:      d.policy.premium_freq ?? '3',
          term_start_date:   d.policy.term_start_date ?? '',
          expiry_date:       d.policy.expiry_date ?? '',
          gfs_policy_no:     d.policy.gfs_policy_no ?? '',
          binder_date:       d.policy.billing_start_date ?? '',
          note:              d.policy.note ?? '',
          entity_type:       d.customer.entity_type ?? 'Individual',
          company_id:        d.customer.company_id ?? null,
          first_name:        d.customer.first_name ?? '',
          middle_name:       d.customer.middle_name ?? '',
          last_name:         d.customer.last_name ?? '',
          email:             d.customer.email ?? '',
          cellphone:         d.customer.cellphone ?? '',
          gender:            d.customer.gender ?? '',
          dob:               d.customer.dob ?? '',
          marital_status:    d.customer.marital_status ?? '',
          omang:             d.customer.omang ?? '',
          passport:          d.customer.passport ?? '',
          state:             d.customer.state ?? null,
          city:              d.customer.city ?? null,
          post_address:      d.customer.post_address ?? '',
          source_of_income:  d.customer.source_of_income ?? '',
          employment:        d.customer.employment ?? {},
          currently_insured: d.customer.currently_insured ?? '',
          current_insurer_detail: d.customer.current_insurer_detail ?? '',
          hear_about_alpha:  d.customer.hear_about_alpha ?? '',
          decline_proposal:  d.customer.decline_proposal ?? false,
          refused_policy:    d.customer.refused_policy ?? false,
          cancel_policy:     d.customer.cancel_policy ?? false,
          business_note:     d.customer.business_note ?? '',
          firm_member:       d.customer.firm_member ?? false,
          books:             d.customer.books ?? false,
          date:              d.customer.date ?? '',
        })
        // Set policyResult so later steps know the policy ID
        setPolicyResult({
          policy_id:   d.policy.id,
          policy_number: d.policy.policy_number,
          term_id:     d.term_id ?? 0,
          action_id:   d.action_id ?? 0,
          customer_id: d.customer.id ?? 0,
          status:      'loaded',
        })
        // Pre-populate saved risk addresses from DB columns.
        // Include the legacy underwriting-classification fields so
        // edit-mode shows what was previously entered and the operator
        // can adjust without losing data.
        setSavedAddresses(
          (d.risk_addresses ?? []).map((ra: any) => ({
            id:                ra.id,
            address_name:      ra.address_name ?? '',
            physical_address:  ra.physical_address ?? '',
            // UAT 2026-05-29 (BUG-005 next pass): hydrate lat/lng on edit.
            // Without this, the Lat/Lng row shipped on the create form
            // (PR #803) appeared empty on Edit Policy even after a save —
            // the columns came back in the API response but the hydrator
            // dropped them. Stored on the DB as nullable strings.
            lat:               ra.lat != null ? String(ra.lat) : '',
            lng:               ra.lng != null ? String(ra.lng) : '',
            risk_state:        ra.risk_state ?? null,
            risk_city:         ra.risk_city ?? null,
            const_type:        ra.const_type ?? '',
            central_fire:      !!ra.central_fire,
            central_burglar:   !!ra.central_burglar,
            gated_community:   !!ra.gated_community,
            automatic:         !!ra.automatic,
            extension:         ra.extension ?? '',
            occupation:        ra.occupation ?? '',
            year_built:        ra.year_built ?? '',
            area:              ra.area ?? '',
            structure_type:    ra.structure_type ?? '',
            town_class:        ra.town_class ?? '',
            risk_class:        ra.risk_class ?? '',
            iso_rcv:           ra.iso_rcv != null ? String(ra.iso_rcv) : '',
            distance_to_water: ra.distance_to_water ?? '',
            distance_to_fire:  ra.distance_to_fire ?? '',
            distance_to_hydrant: ra.distance_to_hydrant ?? '',
            usage:             ra.usage ?? '',
            occupancy_type:    ra.occupancy_type ?? '',
            // Cancelled-state marker — drives Reinstate button on the
            // saved address card (greyed out + line-through when set).
            deleted_at:        ra.deleted_at ?? null,
          }))
        )
        // Pre-populate saved coverages
        setSavedCoverages(
          (d.coverages ?? []).map((c: any) => ({
            _dbId:            c.id ?? null,
            coverage_id:      c.coverage_id ?? null,
            coverage_name:    c.coverage_name ?? '',
            coverage_code:    c.coverage_code ?? '',
            motor_count:      c.motor_count ?? 0,
            risk_address_id:  c.risk_address_id ?? null,
            coverage_value:   c.coverage_value != null ? String(c.coverage_value) : '',
            rate:             c.rate != null ? String(c.rate) : '',
            calculated_value: c.calculated_value != null ? String(c.calculated_value) : '',
            discount_type:    '',
            discount_value:   '',
            notes:            c.notes ?? '',
            // Extract ratefactor fields from first "Free text" subcoverage (if exists)
            ...(() => {
              const freeTextSub = c.subcoverages?.find((s: any) => s.s_ScreenName === 'Free text')
              return {
                ratefactor_type: freeTextSub?.ratefactor_type ?? '',
                ratefactor_value: freeTextSub?.ratefactor_value ? String(freeTextSub.ratefactor_value) : '',
                ratefactor_value_check: freeTextSub?.ratefactor_value_check ?? '',
                ratefactor_AnnualWages: freeTextSub?.ratefactor_AnnualWages ? String(freeTextSub.ratefactor_AnnualWages) : '',
                ratefactor_deposit_min_pre: freeTextSub?.ratefactor_deposit_min_pre ? String(freeTextSub.ratefactor_deposit_min_pre) : '',
              }
            })(),
            subcoverages:     (c.subcoverages ?? []).map((s: any) => ({
              id: s.sub_coverage_id ?? s.id,
              detail_id: s.detail_id ?? s.id,
              sub_coverage_id: s.sub_coverage_id ?? s.id,
              s_ScreenName: s.s_ScreenName ?? '',
              s_CoverageGroupName: s.s_CoverageGroupName ?? '',
              s_SubCoverageMainName: s.s_SubCoverageMainName ?? '',
              coverage_value: String(s.coverage_value ?? ''),
              rate: String(s.rate ?? ''),
              calculated_value: String(s.calculated_value ?? ''),
              // Workers Compensation per-row fields. Backend persists & returns
              // these via the editData loader; without passing them through
              // here, StepCoverages reads `existing?.ratefactor_value` as
              // undefined and the inputs render blank, masking saves that
              // actually landed in the DB.
              coverage_value_string:      s.coverage_value_string ?? '',
              // DROPDOWN/RADIO second-field pick. Same reason as the ratefactor
              // fields: without passing it through, StepCoverages reads
              // `existing?.limit_id` as undefined and the select renders
              // "- Select -", masking a selection that IS in the DB.
              limit_id:                   s.limit_id != null ? String(s.limit_id) : '',
              ratefactor_type:            s.ratefactor_type ?? '',
              ratefactor_value:           s.ratefactor_value != null ? String(s.ratefactor_value) : '',
              ratefactor_value_check:     s.ratefactor_value_check ?? '',
              ratefactor_AnnualWages:     s.ratefactor_AnnualWages != null ? String(s.ratefactor_AnnualWages) : '',
              ratefactor_deposit_min_pre: s.ratefactor_deposit_min_pre != null ? String(s.ratefactor_deposit_min_pre) : '',
              discount_surcharge:         s.discount_surcharge ?? '',
              discount_surcharge_type:    s.discount_surcharge_type ?? '',
              discount_surcharge_value:   s.discount_surcharge_value != null ? String(s.discount_surcharge_value) : '',
            })),
            extensions:       (c.extensions ?? []),
            specified_items:  (c.specified_items ?? []),
            excesses:         (c.excesses ?? []),
            fidelity_data:    (c.fidelity_data ?? []),
            theft_questions:  (c.theft_questions ?? {}),
            property_business_being: c.property_business_being ?? '',
            burglar_alarm_warranty: c.burglar_alarm_warranty ?? '',
            stated_benefits:  c.stated_benefits ?? '',
            // Public Liability retroactive date — kept in sync with the
            // create-refresh / post-create / import hydrators below. Without
            // this, editing a saved PL coverage renders an empty Retroactive
            // Date even though publicliability_date is set in the DB.
            retroactive_date: c.publicliability_date || '',
            // Cancelled-state marker — drives the Reinstate button on the
            // saved-coverage row (greyed out + green Reinstate link).
            deleted_at:       c.deleted_at ?? null,
          }))
        )
        // Backend canonical total (covers Workers Comp / Fidelity premiums
        // stored in policy_coverages_data — invisible to the local reduce).
        setBackendTotalPremium(parseFloat((d as any)?.totalPremium ?? 0) || 0)
        setBackendProRataPremium(parseFloat((d as any)?.proRataPremium ?? 0) || 0)
        // Mark as loaded so the secondary policyResult effect doesn't overwrite
        initialCoveragesLoaded.current = true
      })
      .catch((err: any) => {
        const msg = err?.response?.data?.error || err?.response?.data?.message || err?.message || 'Failed to load policy for editing.'
        setErrors({ _general: msg })
      })
      .finally(() => { setIsEditLoading(false); setReloading(false) })
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [editPolicyId, selectedActionId])

  // Action switcher — pick a different action to edit.
  const changeAction = (newActionId: number) => {
    if (newActionId === selectedActionId) return
    setReloading(true)
    initialCoveragesLoaded.current = false
    // Risk address edits are action-wise — drop any in-progress edit so the
    // form can't PUT the previous action's row after the switch.
    setEditingRiskAddressId(null)
    setRiskForm({ ...INITIAL_RISK })
    setSelectedActionId(newActionId)
  }

  // ─── Fetch existing risk addresses & coverages when policy is available ───
  // Only runs in create mode (after new policy creation) — skipped in edit mode
  // because the first effect already loaded everything and set initialCoveragesLoaded.
  useEffect(() => {
    if (!policyResult?.policy_id) return
    if (initialCoveragesLoaded.current) return   // edit mode already loaded — don't overwrite
    initialCoveragesLoaded.current = true
    fetchPolicyEditData(policyResult.policy_id)
      .then(d => {
        const addresses = (d.risk_addresses ?? []).map((ra: any) => ({
          id: ra.id, address_name: ra.address_name ?? '', physical_address: ra.physical_address ?? '',
          // Lat/Lng — kept in sync with the edit-mode hydrator above.
          lat: ra.lat != null ? String(ra.lat) : '',
          lng: ra.lng != null ? String(ra.lng) : '',
          risk_state: ra.risk_state ?? null, risk_city: ra.risk_city ?? null, const_type: ra.const_type ?? '',
          central_fire: !!ra.central_fire, central_burglar: !!ra.central_burglar,
          gated_community: !!ra.gated_community, automatic: !!ra.automatic,
          extension: ra.extension ?? '', occupation: ra.occupation ?? '',
          year_built: ra.year_built ?? '', area: ra.area ?? '', structure_type: ra.structure_type ?? '',
          // Legacy UW classification (kept in sync with the primary
          // hydrator above + Step 2 UI + backend save payload).
          town_class: ra.town_class ?? '', risk_class: ra.risk_class ?? '',
          iso_rcv: ra.iso_rcv != null ? String(ra.iso_rcv) : '',
          distance_to_water: ra.distance_to_water ?? '',
          distance_to_fire: ra.distance_to_fire ?? '',
          distance_to_hydrant: ra.distance_to_hydrant ?? '',
          usage: ra.usage ?? '', occupancy_type: ra.occupancy_type ?? '',
        }))
        if (addresses.length > 0) setSavedAddresses(addresses)

        const coverages = (d.coverages ?? []).map((c: any) => {
          const cov = {
            _dbId:            c.id ?? null,          // required for edit → updateCoverage
            coverage_id:      c.coverage_id ?? null,
            coverage_name:    c.coverage_name ?? '',
            // Required for SavedCoverageRow to detect specialist families
            // and render the "Open X Schedule" buttons after a fresh
            // policy is created. Matches the edit-mode hydrator above.
            coverage_code:    c.coverage_code ?? '',
            motor_count:      c.motor_count ?? 0,
            risk_address_id:  c.risk_address_id ?? null,
            coverage_value:   c.coverage_value != null ? String(c.coverage_value) : '',
            rate:             c.rate != null ? String(c.rate) : '',
            calculated_value: c.calculated_value != null ? String(c.calculated_value) : '',
            // notes came from the server, so read it. Hardcoding '' meant
            // opening a post-create coverage in the grid and saving it upserted
            // policy_coverage_notes with a blank — the underwriter's note gone,
            // with nothing on screen to show it had been there.
            discount_type: '', discount_value: '', notes: c.notes ?? '',
            retroactive_date: c.publicliability_date || '',
            subcoverages: (c.subcoverages ?? []).map((s: any) => ({
              id: s.sub_coverage_id ?? s.id,
              s_ScreenName: s.s_ScreenName ?? '',
              s_CoverageGroupName: s.s_CoverageGroupName ?? '',
              s_SubCoverageMainName: s.s_SubCoverageMainName ?? '',
              coverage_value: String(s.coverage_value ?? ''),
              rate: String(s.rate ?? ''),
              calculated_value: String(s.calculated_value ?? ''),
              // Workers Compensation per-row fields — see primary hydrator
              // above for rationale. Kept in sync to avoid drift.
              coverage_value_string:      s.coverage_value_string ?? '',
              limit_id:                   s.limit_id != null ? String(s.limit_id) : '',
              ratefactor_type:            s.ratefactor_type ?? '',
              ratefactor_value:           s.ratefactor_value != null ? String(s.ratefactor_value) : '',
              ratefactor_value_check:     s.ratefactor_value_check ?? '',
              ratefactor_AnnualWages:     s.ratefactor_AnnualWages != null ? String(s.ratefactor_AnnualWages) : '',
              ratefactor_deposit_min_pre: s.ratefactor_deposit_min_pre != null ? String(s.ratefactor_deposit_min_pre) : '',
              discount_surcharge:         s.discount_surcharge ?? '',
              discount_surcharge_type:    s.discount_surcharge_type ?? '',
              discount_surcharge_value:   s.discount_surcharge_value != null ? String(s.discount_surcharge_value) : '',
            })),
            // The coverage's own child rows, from the same payload the edit
            // hydrator reads them off. Without them a Smart UW Apply onto this
            // coverage sees no existing extensions or misc items to carry
            // forward, and updateCoverage's replace-set soft-deletes the lot —
            // refunding them on an ENDORSE. The Apply merge reads exactly
            // `extensions` (excesses included, stored as type='Excess') and
            // `specified_items`; the rest are here so this hydrator matches
            // the edit one it was diverging from.
            extensions:       (c.extensions ?? []),
            specified_items:  (c.specified_items ?? []),
            excesses:         (c.excesses ?? []),
            fidelity_data:    (c.fidelity_data ?? []),
            theft_questions:  (c.theft_questions ?? {}),
            property_business_being: c.property_business_being ?? '',
            burglar_alarm_warranty: c.burglar_alarm_warranty ?? '',
            stated_benefits:  c.stated_benefits ?? '',
          }
          return cov
        })
        if (coverages.length > 0) setSavedCoverages(coverages)
      })
      .catch(() => {})
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [policyResult?.policy_id])

  // ─── Mutations ───────────────────────────────────────
  const createMutation = useMutation({
    mutationFn: (payload: CreatePolicyPayload) => createPolicy(payload),
    onSuccess: (data) => {
      setPolicyResult(data.data)
      localStorage.removeItem(DRAFT_KEY)
      // Open Risk Address & Excel import sections immediately after policy creation; rest unlocked but collapsed
      setOpenSections(new Set(['policy', 'risk-address-import', 'excel']))
    },
    onError: (err: any) => {
      const data = err?.response?.data
      if (data?.errors) {
        const fieldErrors: Record<string, string> = {}
        Object.entries(data.errors).forEach(([k, v]: [string, any]) => { fieldErrors[k] = Array.isArray(v) ? v[0] : v })
        setErrors(fieldErrors)
      } else {
        setErrors({ _general: data?.message || 'Failed to create policy. Please check all fields.' })
      }
    },
  })

  const updateMutation = useMutation({
    // Target id is editPolicyId when editing an existing policy, OR the
    // policyResult from the current session once section 1 has been created.
    // Without the fallback, correcting a field right after a fresh create
    // hits /policies/NaN and the backend round-trips as a new policy.
    mutationFn: (payload: UpdatePolicyPayload) => {
      const targetId = Number(editPolicyId) || policyResult?.policy_id
      if (!targetId) throw new Error('No policy id available to update')
      return updatePolicy(targetId, payload)
    },
    onSuccess: () => {
      // Keep all sections open after saving in edit mode
      setOpenSections(new Set(['policy', 'risk-address-import', 'excel', 'risk', 'coverages', 'review']))
      setErrors({})
    },
    onError: (err: any) => {
      const data = err?.response?.data
      if (data?.errors) {
        const fieldErrors: Record<string, string> = {}
        Object.entries(data.errors).forEach(([k, v]: [string, any]) => { fieldErrors[k] = Array.isArray(v) ? v[0] : v })
        setErrors(fieldErrors)
      } else {
        setErrors({ _general: data?.message || 'Failed to update policy.' })
      }
    },
  })

  const riskMutation = useMutation({
    mutationFn: (payload: RiskAddressPayload) => addRiskAddress(policyResult!.policy_id, payload),
    onSuccess: (data) => {
      setSavedAddresses(prev => [...prev, { ...riskForm, id: data.data.id } as SavedRiskAddress])
      setRiskForm({ ...INITIAL_RISK })
    },
    onError: (err: any) => {
      setErrors({ _general: err?.response?.data?.message || 'Failed to save risk address.' })
    },
  })

  // Reload the risk address cards for the action currently open.
  // risk_address rows are replicated per action, so the refresh MUST carry
  // action_id — without it editData falls back to "latest QUOTE by id" and
  // the cards can come back from a different transaction than the one on
  // screen (the cancel/reinstate refreshes had exactly that hole).
  const reloadRiskAddressesFromServer = async () => {
    if (!policyResult?.policy_id) return
    try {
      const fresh = await fetchPolicyEditData(
        policyResult.policy_id,
        selectedActionId ?? policyResult.action_id ?? null,
      )
      setSavedAddresses((fresh.risk_addresses ?? []).map(hydrateRiskAddress))
    } catch {}
  }

  const riskUpdateMutation = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Partial<RiskAddressPayload> }) =>
      updateRiskAddress(policyResult!.policy_id, id, payload),
    onSuccess: async () => {
      setEditingRiskAddressId(null)
      setRiskForm({ ...INITIAL_RISK })
      await reloadRiskAddressesFromServer()
    },
    onError: (err: any) => {
      const data = err?.response?.data
      setErrors({ _general: data?.error || data?.message || 'Failed to update risk address.' })
    },
  })

  // Reload coverages from server after any mutation
  const reloadCoveragesFromServer = async () => {
    if (!policyResult) return
    try {
      const fresh = await fetchPolicyEditData(policyResult.policy_id)
      setSavedCoverages(
        (fresh.coverages ?? []).map((c: any) => {
          const cov = {
            _dbId: c.id ?? null,
            coverage_id: c.coverage_id ?? null,
            coverage_name: c.coverage_name ?? '',
            // Required for SavedCoverageRow to detect specialist families
            // (CAR/EAR/PAR/MB/MM/PI/D&O/Marine Open/Marine Once-Off/Travel)
            // and render the "Open X Schedule" buttons. Without this the
            // covCode check resolves to '' and the buttons never render
            // after an add/update mutation.
            coverage_code: c.coverage_code ?? '',
            motor_count: c.motor_count ?? 0,
            risk_address_id: c.risk_address_id ?? null,
            coverage_value: c.coverage_value != null ? String(c.coverage_value) : '',
            rate: c.rate != null ? String(c.rate) : '',
            calculated_value: c.calculated_value != null ? String(c.calculated_value) : '',
            discount_type: '', discount_value: '',
            notes: c.notes ?? '',
            subcoverages: (c.subcoverages ?? []).map((s: any) => ({
              id: s.sub_coverage_id ?? s.id,
              detail_id: s.detail_id ?? s.id,
              sub_coverage_id: s.sub_coverage_id ?? s.id,
              isCustom: false,  // Explicitly mark as not custom (previously saved)
              wasLoadedFromServer: true,  // Mark rows loaded from database - these should NOT be re-sent
              s_ScreenName: s.s_ScreenName ?? '',
              s_CoverageGroupName: s.s_CoverageGroupName ?? '',
              s_SubCoverageMainName: s.s_SubCoverageMainName ?? '',
              // CRITICAL: Include field configuration for proper form rendering
              s_CoverageCode: s.s_CoverageCode ?? '',
              s_ParentCoverageCode: s.s_ParentCoverageCode ?? '',
              s_LimitTypeCode: s.s_LimitTypeCode ?? '',  // Determines which second field (Free Text, dropdown, etc.)
              dropdown_options: s.dropdown_options ?? [],
              radio_options: s.radio_options ?? [],
              limit_id: s.limit_id ?? '',
              coverage_value: String(s.coverage_value ?? ''),
              rate: String(s.rate ?? ''),
              calculated_value: String(s.calculated_value ?? ''),
              // Workers Compensation per-row fields — kept in sync with the
              // two other hydrators above.
              coverage_value_string:      s.coverage_value_string ?? '',
              ratefactor_type:            s.ratefactor_type ?? '',
              ratefactor_value:           s.ratefactor_value != null ? String(s.ratefactor_value) : '',
              ratefactor_value_check:     s.ratefactor_value_check ?? '',
              ratefactor_AnnualWages:     s.ratefactor_AnnualWages != null ? String(s.ratefactor_AnnualWages) : '',
              ratefactor_deposit_min_pre: s.ratefactor_deposit_min_pre != null ? String(s.ratefactor_deposit_min_pre) : '',
              discount_surcharge:         s.discount_surcharge ?? '',
              discount_surcharge_type:    s.discount_surcharge_type ?? '',
              discount_surcharge_value:   s.discount_surcharge_value != null ? String(s.discount_surcharge_value) : '',
            })),
            extensions: (c.extensions ?? []),
            specified_items: (c.specified_items ?? []),
            excesses: (c.excesses ?? []),
            fidelity_data: (c.fidelity_data ?? []),
            theft_questions: (c.theft_questions ?? {}),
            property_business_being: c.property_business_being ?? '',
            burglar_alarm_warranty: c.burglar_alarm_warranty ?? '',
            stated_benefits: c.stated_benefits ?? '',
            retroactive_date: c.publicliability_date || '',
            deleted_at: c.deleted_at ?? null,
          }
          return cov
        })
      )
      setBackendTotalPremium(parseFloat((fresh as any)?.totalPremium ?? 0) || 0)
      setBackendProRataPremium(parseFloat((fresh as any)?.proRataPremium ?? 0) || 0)
    } catch {}
  }

  const coverageMutation = useMutation({
    mutationFn: (payload: any) => addCoverage(policyResult!.policy_id, payload),
    onSuccess: async () => {
      setCoverageForm({ ...INITIAL_COVERAGE })
      setEditingCoverageId(null)
      await reloadCoveragesFromServer()
    },
    onError: (err: any) => {
      const data = err?.response?.data
      if (data?.errors) {
        const fieldErrors: Record<string, string> = {}
        Object.entries(data.errors).forEach(([k, v]: [string, any]) => { fieldErrors[k] = Array.isArray(v) ? v[0] : v })
        setErrors(fieldErrors)
      } else {
        const msg = data?.message || data?.error || 'Failed to save coverage.'
        // Singleton-family duplicate errors render above Miscellaneous Items, not the top page banner.
        if (/already has a .+ coverage\. Only one .+ coverage is allowed per risk address\./.test(msg)) {
          setErrors({ cov_duplicate_family: msg })
        } else {
          setErrors({ _general: msg })
        }
      }
    },
  })

  const updateCovMutation = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: any }) => updateCoverage(policyResult!.policy_id, id, payload),
    onSuccess: async () => {
      setCoverageForm({ ...INITIAL_COVERAGE })
      setEditingCoverageId(null)
      await reloadCoveragesFromServer()
      setEditingCoverageIndex(null)
      editCovIndexRef.current = null
    },
    onError: (err: any) => {
      // Coverage was NOT removed from the list (new UX: keep in list while editing)
      setEditingCoverageId(null)
      setEditingCoverageIndex(null)
      editCovIndexRef.current = null
      const data = err?.response?.data
      if (data?.errors) {
        const fieldErrors: Record<string, string> = {}
        Object.entries(data.errors).forEach(([k, v]: [string, any]) => { fieldErrors[k] = Array.isArray(v) ? v[0] : v })
        setErrors(fieldErrors)
      } else {
        setErrors({ _general: data?.message || data?.error || 'Failed to update coverage.' })
      }
    },
  })

  // ─── Step Handlers ───────────────────────────────────
  const validateStep1 = (): boolean => {
    const errs: Record<string, string> = {}
    if (!form.product_id) errs.product_id = 'Product is required'
    if (!form.plan_id) errs.plan_id = 'Plan is required'
    if (!form.premium_freq) errs.premium_freq = 'Premium frequency is required'
    if (!form.term_start_date) errs.term_start_date = 'Start date is required'
    // In edit mode the start date may be historical — only enforce future-date for new policies
    // Backdated start dates are allowed (matches legacy graphiteBWV8 which
    // never blocked them — underwriters backdate for endorsements and
    // corrections regularly). Keep the field required, but do not block on
    // "today or future".
    if (!form.agency_id) errs.agency_id = 'Agency is required'
    if (form.entity_type === 'Organisation' && !form.company_id) errs.company_id = 'Company is required'
    // Commercial Liabilities (20) / Guarantee (23) / Miscellaneous (24) are
    // COMG company lines on an Annual term only. Guarantee / Miscellaneous
    // also require an Organisation holder; Commercial Liabilities takes
    // either holder (UW 2026-08-26), so only the frequency is enforced there.
    // The step locks these fields in the UI — this is the save-time backstop
    // for a stale draft or an existing policy loaded in edit mode.
    if (isCompanyOnlyProduct(form.product_id)) {
      if (form.premium_freq !== ANNUAL_FREQ) errs.premium_freq = 'Only Annual frequency is allowed on this product'
    }
    if (isOrganisationOnlyProduct(form.product_id) && !form.company_id) {
      errs.company_id = 'Company is required — this product is issued to an Organisation only'
    }
    // Omang/Passport mandatory for Individual policy holders only —
    // Commercial (Organisation) policies are held by a company.
    if (form.entity_type === 'Individual') {
      if (!form.first_name) errs.first_name = 'First name is required'
      else if (form.first_name.length > 16) errs.first_name = 'Max 16 characters'
      if (!form.last_name) errs.last_name = 'Last name is required'
      else if (form.last_name.length > 16) errs.last_name = 'Max 16 characters'
      if (!form.cellphone) errs.cellphone = 'Phone is required'
      else if (!/^7\d{7}$/.test(form.cellphone)) errs.cellphone = '8 digits starting with 7'
      if (form.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) errs.email = 'Invalid email'
      if (!form.gender) errs.gender = 'Gender is required'
      if (!form.dob) errs.dob = 'Date of birth is required'
      else {
        const age = Math.floor((Date.now() - new Date(form.dob).getTime()) / (365.25 * 24 * 60 * 60 * 1000))
        if (age < 18) errs.dob = 'Must be at least 18'
        if (age > 100) errs.dob = 'Age cannot exceed 100'
      }
      // Commercial Insurance individuals are exempt from the Omang/Passport
      // requirement (per UW). Format checks below still apply if a value is typed.
      if (!isCommercialInsuranceProduct(form.product_id) && !form.omang && !form.passport) errs.omang = 'Omang or Passport required'
      if (form.omang && !/^\d{4}[12]\d{4}$/.test(form.omang)) errs.omang = '9 digits, 5th must be 1 or 2'
      if (form.passport && (!/^[a-zA-Z0-9]+$/.test(form.passport) || form.passport.length > 16)) errs.passport = 'Alphanumeric, max 16'
      if (!form.marital_status) errs.marital_status = 'Marital status is required'
      if (!form.state) errs.state = 'Province is required'
      if (!form.city) errs.city = 'City is required'
      if (!form.post_address) errs.post_address = 'Post address is required'
      else if (form.post_address.length > 80) errs.post_address = 'Max 80 characters'
      if (!form.source_of_income) errs.source_of_income = 'Source of income is required'
    }
    if (Object.keys(errs).length > 0) {
      setErrors(errs)
      // Scroll to the top of the form so user can see highlighted fields
      setTimeout(() => window.scrollTo({ top: 0, behavior: 'smooth' }), 30)
      return false
    }
    return true
  }

  const handleCreatePolicy = () => {
    if (!validateStep1()) return
    createMutation.mutate(form as any)
  }

  const handleUpdatePolicy = () => {
    if (!validateStep1()) return
    updateMutation.mutate(form as any)
  }

  const handleSaveRiskAddress = () => {
    const errs: Record<string, string> = {}
    if (!riskForm.address_name) errs.ra_address_name = 'Required'
    if (!riskForm.physical_address) errs.ra_physical_address = 'Required'
    if (!riskForm.risk_state) errs.ra_risk_state = 'Required'
    if (!riskForm.risk_city) errs.ra_risk_city = 'Required'
    if (!riskForm.const_type) errs.ra_const_type = 'Required'
    if (Object.keys(errs).length > 0) { setErrors(errs); return }
    if (!policyResult) return

    // Editing an existing card → PUT the same row. action_id is sent so the
    // backend can refuse the write if the row belongs to another transaction
    // (stale tab / action switched underneath).
    if (editingRiskAddressId) {
      setErrors({})
      riskUpdateMutation.mutate({
        id: editingRiskAddressId,
        payload: {
          ...riskForm,
          action_id: selectedActionId ?? policyResult.action_id,
        } as any,
      })
      return
    }

    riskMutation.mutate({
      ...riskForm,
      term_id: policyResult.term_id,
      action_id: policyResult.action_id,
    } as any)
  }

  const handleSaveCoverage = (finalForm?: any) => {
    const form = finalForm || coverageForm
    const errs: Record<string, string> = {}
    // Defensive: even if UI lock is bypassed somehow, a coverage write
    // without any Risk Address on the policy must fail at the FE level
    // before round-tripping to the backend (which also rejects it).
    if (savedAddresses.length === 0) {
      setErrors({ cov_risk_address_id: 'Add a Risk Address to this policy before adding coverages.' })
      return
    }
    if (!form.coverage_id) errs.cov_coverage_id = 'Required'
    if (!form.risk_address_id) errs.cov_risk_address_id = 'Required'

    // Check if this is Fidelity Guarantee (stores data in policy_coverages_data, not policy_coverage_detail)
    // or Workers Compensation (for later use below)
    const currentCov = availableCoverages.find((c: any) => c.id === form.coverage_id)
    const covCodeUpper = String(currentCov?.s_CoverageCode || '').toUpperCase()
    const isFidelityGuarantee = covCodeUpper === 'FIDELITYGUARANTEE'
    // Coverages where the main Sum Insured trio is not rendered (see
    // HIDE_MAIN_COVERAGE_FIELDS_CODES in StepCoverages.tsx) — skip the
    // required check so save isn't blocked by an invisible field.
    const hidesMainCoverageFields = [
      'MEDICAMALPRACTICEINSURANCE', 'PROFESSIONALINDEMNITY', 'TRAVELINSURANCE',
      'CONTRACTORSALLRISKS', 'PLANTALLRISKS', 'ERECTIONALLRISKS',
      'DIRECTORSOFFICERSLIABILITY', 'MACHINERYBREAKDOWN',
      'MARINEONCEOFFCOVER', 'MARINEOPENCOVER',
      'MOTORTRADERSEXTERNAL', 'MOTORTRADERSINTERNAL',
      // Kept in sync with HIDE_MAIN_COVERAGE_FIELDS_CODES in StepCoverages.tsx
      // (2026-06-24 Guarantee/Miscellaneous/Environmental Liability migration)
      // — this list had drifted, so Bonds and Guarantees (and the other 3)
      // hid the Sum Insured input but this validator still required it,
      // blocking Add Coverage with an unfixable "Required" error.
      'MEDICALEVACUATION', 'COMMERCIALCRIME', 'ENVIRONMENTALLIABILITY', 'BONDSANDGUARANTEES',
    ].includes(covCodeUpper)

    // If subcoverages exist, coverage_value is auto-calculated — skip validation
    // For Fidelity Guarantee, coverage_value is not used (data goes to policy_coverages_data) — skip validation
    const hasSubs = form.subcoverages && form.subcoverages.length > 0
    if (!hasSubs && !isFidelityGuarantee && !hidesMainCoverageFields && !form.coverage_value) errs.cov_coverage_value = 'Required'

    // Singleton coverage families — at most one row of each per risk address.
    // Block duplicates client-side so the user sees the message immediately;
    // backend addCoverage/reinstateCoverage enforce the same rule via
    // PolicyCreateController::singletonCoverageFamilies(). Keep this list in
    // sync with that PHP method. Resolve the code from availableCoverages
    // (currentCov.s_CoverageCode) because the dropdown's onChange only sets
    // coverage_id/coverage_name — form.coverage_code is empty for newly
    // picked coverages.
    const codeUpper = covCodeUpper || String(form.coverage_code || '').toUpperCase()
    const SINGLETON_FAMILIES: Record<string, string[]> = {
      EAR: ['ERECTIONALLRISKS', 'EAR'],
      CAR: ['CONTRACTORSALLRISKS', 'CAR'],
      PAR: ['PLANTALLRISKS', 'PAR'],
      'Machinery Breakdown': ['MACHINERYBREAKDOWN'],
      'Medical Malpractice': ['MEDICAMALPRACTICEINSURANCE'],
      'Professional Indemnity': ['PROFESSIONALINDEMNITY'],
      'Directors & Officers': ['DIRECTORSOFFICERSLIABILITY', 'MARINEDIRECTORSOFFICERS'],
      'Marine Open Cover': ['MARINEOPENCOVER'],
      'Marine Once-Off Cover': ['MARINEONCEOFFCOVER', 'MARINECARGOONCEOFF'],
      'Travel Insurance': ['TRAVELINSURANCE', 'TRAVEL'],
    }
    const family = (Object.entries(SINGLETON_FAMILIES).find(([, codes]) => codes.includes(codeUpper)) ?? [null])[0]
    if (family && form.risk_address_id) {
      const dup = savedCoverages.some((c: any) => {
        if (editingCoverageId && c._dbId === editingCoverageId) return false
        if (c.risk_address_id !== form.risk_address_id) return false
        const otherCode = String(c.coverage_code || '').toUpperCase()
        return SINGLETON_FAMILIES[family].includes(otherCode)
      })
      if (dup) {
        // Render above Miscellaneous Items (see StepCoverages.tsx) instead of
        // under the Coverage Type dropdown / in the top-page banner.
        errs.cov_duplicate_family = `This risk address already has a ${family} coverage. Only one ${family} coverage is allowed per risk address.`
      }
    }

    // One coverage per risk address (one-to-one) for the specialist single-site
    // products (Engineering 16/17, Specialist 18, Commercial Liabilities 20,
    // Marine 22). Stricter than the per-family check above: under these products
    // a risk address may hold only ONE coverage of ANY type. Backend
    // PolicyCreateController enforces the same rule. Editing the same coverage is
    // allowed (excluded by _dbId); any other coverage on the address is blocked.
    const ONE_COVERAGE_PER_ADDRESS_PRODUCTS = [16, 17, 18, 20, 22, 23, 24]
    if (ONE_COVERAGE_PER_ADDRESS_PRODUCTS.includes(Number(form.product_id)) && form.risk_address_id && !errs.cov_duplicate_family) {
      const otherCoverage = savedCoverages.some((c: any) => {
        if (editingCoverageId && c._dbId === editingCoverageId) return false
        return c.risk_address_id === form.risk_address_id
      })
      if (otherCoverage) {
        errs.cov_duplicate_family = 'This risk address already has a coverage. Only one coverage is allowed per risk address.'
      }
    }

    if (Object.keys(errs).length > 0) { setErrors(errs); return }
    if (!policyResult) return

    // Map subcoverages to `details` for backend policy_coverage_detail rows
    // CRITICAL FIX: In EDIT mode (existing coverage), send ALL detail rows (existing + new)
    // In ADD mode (new coverage), only send newly added rows (isCustom=true)
    // This ensures Houseowners-Buildings and other coverages save detail rows correctly

    // Check if this is Workers Compensation or Stated Benefits coverage.
    // Both share the same per-row composite layout (free-text note,
    // discount triplet, ratefactor fields on the "Free text" master row)
    // but stay separate coverage records — STATEDBENEFITS persists under
    // its own coverage_id (13), not under WORKERSCOMPENSATION (20).
    const isWorkersCom     = covCodeUpper === 'WORKERSCOMPENSATION'
    const isStatedBenefits = covCodeUpper === 'STATEDBENEFITS'
    const isWcLike         = isWorkersCom || isStatedBenefits

    // ENDORSE "Cancel Sub Coverage": on an endorsement whose reason is
    // "Cancel Sub Coverage", zeroing an existing subcoverage (Sum Insured = 0
    // AND Premium = 0) must PERSIST the 0 so the pro-rata refund is computed,
    // instead of being dropped as an empty row (the normal rule everywhere
    // else). Scoped to ENDORSE + that exact reason only — every other action
    // type / reason keeps the existing "> 0 to save" behaviour untouched. The
    // backend (updateCoverage) is the real gate: it re-checks the reason and
    // only zeroes a row that already exists as a persisted detail row, so this
    // never creates phantom 0 rows from untouched master-template blanks.
    // transaction_reason stores the subtype CODE ("SUBCOVCANCEL"), not the
    // display label ("Cancel Sub Coverage"). Accept either, normalized.
    // NOTE: the DomCom product (7, 8) restriction is enforced authoritatively
    // on the backend (updateCoverage). We deliberately do NOT gate on
    // product here: `form` inside handleSaveCoverage is the COVERAGE form,
    // whose product_id is null (never hydrated), so a frontend product check
    // wrongly dropped the zeroed row before it was ever sent. Sending the row
    // for any cancel-sub endorse is safe — the backend skips it for non-7/8.
    const selActionForCancel = availableActions.find(a => a.id === selectedActionId)
    const cancelReasonNorm = (selActionForCancel?.transaction_reason ?? '').replace(/\s+/g, '').toLowerCase()
    const isEndorseAction =
      selActionForCancel?.transaction_type === 'ENDORSE' &&
      (cancelReasonNorm === 'subcovcancel' || cancelReasonNorm === 'cancelsubcoverage')

    const details = (form.subcoverages || [])
      .filter((sub: any) => {
        // Send every row that carries real data, in BOTH add and edit mode.
        // Previously add mode required isCustom===true, which only the "+"
        // duplicated rows have — standard master-template rows are merged in
        // without that flag (StepCoverages merge loop), so values typed into
        // them were dropped on the first save and only persisted after the
        // coverage was re-opened in edit mode. The data-validity check below
        // already excludes blank / rate-only template rows, so the isCustom
        // gate was both unnecessary and the cause of the lost first-save data.
        const coverageValue = parseFloat(sub.coverage_value) || 0
        const calculatedValue = parseFloat(sub.calculated_value) || 0
        // WC / Stated Benefits rows can have only Premium (hand-entered)
        // and no Sum Insured — include the row when EITHER coverage_value
        // OR calculated_value is set, OR when the composite discount /
        // note / ratefactor fields carry data so the operator's input
        // isn't silently dropped.
        const hasWcContent = isWcLike && (
          (sub.coverage_value_string ?? '').trim() !== '' ||
          (sub.discount_surcharge ?? '') !== '' ||
          (sub.ratefactor_AnnualWages ?? '') !== '' ||
          (sub.ratefactor_value ?? '') !== ''
        )
        // ENDORSE cancel-subcoverage: keep a zeroed row so the backend can
        // persist the 0 (cancel). Backend only applies it to existing rows.
        const isEndorseCancel = isEndorseAction && coverageValue === 0 && calculatedValue === 0
        // Any action: a zeroed EXISTING (already-saved) row must still be sent
        // so the backend can persist the 0 — mirrors the backend, which now
        // applies a 0 to an existing detail row on every action (not just
        // endorse-cancel). New/unsaved zero rows have no detail id, so they stay
        // excluded and no phantom 0 rows are created.
        const isZeroedExisting = !!(sub.detail_id || (sub.id && Number(sub.id) > 0))
          && coverageValue === 0 && calculatedValue === 0
        // A DROPDOWN/RADIO row can be meaningful with nothing but the select
        // filled (Public Liability "Basis of cover" → Claims Made). Treat the
        // literal "0" as "no selection" — some coverages seed limit_id with it.
        const limitPick = String(sub.limit_id ?? '').trim()
        const hasLimitSelection = limitPick !== '' && limitPick !== '0'
        return coverageValue > 0 || calculatedValue > 0 || hasWcContent || hasLimitSelection || isEndorseCancel || isZeroedExisting
      })
      .map((sub: any) => {
        console.log(`[DETAIL ROW] ${sub.s_ScreenName}: id=${sub.id}, sub_coverage_id=${sub.sub_coverage_id}`)
        const detailRow: any = {
          description: sub.s_ScreenName,
          coverage_id: sub.sub_coverage_id ?? sub.id,  // Use sub_coverage_id (master coverage ID from API), fallback to id
          coverage_value: parseFloat(sub.coverage_value) || 0,
          rate: parseFloat(sub.rate) || 0,
          calculated_value: parseFloat(sub.calculated_value) || 0,
          // Endorse pro-rata gate: false only when StepCoverages is certain this
          // carried-forward row was left untouched this session. Backend then
          // saves it but excludes it from the endorsement pro-rata. Any real
          // add/edit (or unknown) sends true → charged exactly as before.
          changed: sub._changed !== false,
        }
        console.log(`[DETAIL PAYLOAD] Sending coverage_id=${detailRow.coverage_id} (sub_coverage_id=${sub.sub_coverage_id}) for ${sub.s_ScreenName}`)

        // For existing detail rows (detail_id present), always send text fields
        // so the backend knows to clear them if the user removed the value.
        // For new rows, only send if non-empty to avoid noise.
        const isExistingRow = !!sub.detail_id

        // Include coverage_value_string for ALL coverages (Public Liability,
        // Electronic Equipment, etc.), not just WC/Stated Benefits
        if (isExistingRow || (sub.coverage_value_string ?? '').trim() !== '') {
          detailRow.coverage_value_string = sub.coverage_value_string ?? ''
        }

        // DROPDOWN / RADIO second-field selection (tb_cvgpclimits PK), e.g.
        // Public Liability "Basis of cover" → Claims Made, Fire > Rent months,
        // Theft radio answers. Stored in policy_coverage_detail.limit_id.
        //
        // Deliberately NOT gated on isExistingRow: StepCoverages' merge loop
        // builds each master-matched row with `id` holding the detail row's DB
        // id and no `detail_id` key at all, so isExistingRow is false even for
        // long-persisted rows. Gating on it would send the pick (non-empty) but
        // never the reset (empty), leaving a cleared select stuck on its old
        // value. `id > 0` is the reliable "already in the DB" signal here.
        // Kept local to limit_id so the other fields' send conditions — and
        // whatever clearing behaviour they currently have — are untouched.
        const subHasDbRow = !!sub.detail_id || Number(sub.id) > 0
        if (subHasDbRow || String(sub.limit_id ?? '').trim() !== '') {
          detailRow.limit_id = String(sub.limit_id ?? '')
        }

        // Include ratefactor fields for ALL coverages (House Holders, Electronic
        // Equipment, etc.), not just WC. These store free-text values, dropdowns,
        // and numeric fields for two-field subcoverage patterns.
        if (isExistingRow || (sub.ratefactor_type ?? '').trim() !== '') {
          detailRow.ratefactor_type = sub.ratefactor_type ?? ''
        }
        if (isExistingRow || (sub.ratefactor_value ?? '').trim() !== '') {
          detailRow.ratefactor_value = sub.ratefactor_value ?? ''
        }
        if (isExistingRow || (sub.ratefactor_value_check ?? '').trim() !== '') {
          detailRow.ratefactor_value_check = sub.ratefactor_value_check ?? ''
        }
        if (isExistingRow || (sub.ratefactor_AnnualWages && sub.ratefactor_AnnualWages !== '')) {
          detailRow.ratefactor_AnnualWages = sub.ratefactor_AnnualWages ? parseFloat(sub.ratefactor_AnnualWages) : null
        }
        if (isExistingRow || (sub.ratefactor_deposit_min_pre && sub.ratefactor_deposit_min_pre !== '')) {
          detailRow.ratefactor_deposit_min_pre = sub.ratefactor_deposit_min_pre ? parseFloat(sub.ratefactor_deposit_min_pre) : null
        }

        // Workers Compensation / Stated Benefits per-row fields.
        if (isWcLike) {
          if (isExistingRow || sub.discount_surcharge) {
            detailRow.discount_surcharge      = sub.discount_surcharge ?? ''
            detailRow.discount_surcharge_type = sub.discount_surcharge_type || null
            detailRow.discount_surcharge_value = sub.discount_surcharge_value
              ? parseFloat(sub.discount_surcharge_value) : 0
          }
        }

        return detailRow
      })

    lastSubmittedCovRef.current = form

    // For Theft coverage, convert excesses to extensions with type='Excess'
    // (matching old project storage in policy_extention_detail)
    const covCode = (form.coverage_code || '').toUpperCase()
    const excessesAsExtensions = covCode === 'THEFT' && form.excesses
      ? (form.excesses as any[]).map(exc => ({
          type: 'Excess',
          s_ScreenName: exc.excesses || 'Excess',
          s_CoverageCode: 'THEFT',
          extension_type: 'NOEDIT',
          extention_excess_min_value: exc.min_percent || null,
          extention_excess_max_value: exc.min_amt || null,
          extention_discount_surcharge: exc.discount_surcharge || null,
          extention_discount_surcharge_type: exc.discount_surcharge_type || null,
          extention_discount_surcharge_value: exc.discount_surcharge_value || null,
          extention_calculated_value: exc.premium || null,
        }))
      : []

    const payload = {
      // Base coverage fields (spread all properties)
      ...form,
      // Override numeric fields
      coverage_value:   !form.coverage_value ? undefined : (parseFloat(form.coverage_value) || 0),
      rate:             !form.rate ? undefined : (parseFloat(form.rate) || 0),
      calculated_value: !form.calculated_value ? undefined : (parseFloat(form.calculated_value) || 0),
      // Policy context
      term_id: policyResult.term_id,
      action_id: policyResult.action_id,
      // Details (subcoverages)
      details: details.length > 0 ? details : undefined,
      // For Theft, merge excesses into extensions with type='Excess'
      extensions: covCode === 'THEFT'
        ? [...(form.extensions || []), ...excessesAsExtensions]
        : form.extensions,
      // Add address_id to specified_items from risk_address_id
      specified_items: (form.specified_items || []).map((item: any) => ({
        ...item,
        address_id: item.address_id ?? form.risk_address_id,
      })) || undefined,
      // Only send excesses for non-Theft coverages (Theft uses extensions)
      excesses: covCode !== 'THEFT' ? form.excesses : undefined,
      fidelity_data: form.fidelity_data,
      notes: form.notes,
      retroactive_date: form.retroactive_date,
    }

    // DEBUG: Log what we're sending
    console.log('=== DEBUG: Coverage Save ===')
    console.log('DETAILS ARRAY (WHAT WE ARE SENDING TO BACKEND):')
    console.table((details || []).map((d: any) => ({
      description: d.description,
      coverage_id: d.coverage_id,
      coverage_value: d.coverage_value,
      calculated_value: d.calculated_value,
      ratefactor_AnnualWages: d.ratefactor_AnnualWages,
      ratefactor_value: d.ratefactor_value,
      ratefactor_value_check: d.ratefactor_value_check,
      ratefactor_type: d.ratefactor_type,
    })))
    console.log('FORM SUBCOVERAGES (BEFORE FILTER):')
    console.table((form.subcoverages || []).map((s: any) => ({
      name: s.s_ScreenName,
      id: s.id,
      detail_id: s.detail_id,
      sub_coverage_id: s.sub_coverage_id,
      wasLoadedFromServer: s.wasLoadedFromServer,
      isCustom: s.isCustom,
      coverage_value: s.coverage_value,
    })))
    console.log('\nDETAILS ARRAY (AFTER FILTER):')
    console.table(details.map((d: any) => ({
      description: d.description,
      coverage_id: d.coverage_id,
      coverage_value: d.coverage_value,
    })))
    console.log('\nPAYLOAD TO SEND:')
    console.log(JSON.stringify({
      ...payload,
      details: payload.details ? payload.details.map((d: any) => ({
        description: d.description,
        coverage_id: d.coverage_id,
        coverage_value: d.coverage_value,
      })) : undefined
    }, null, 2))
    if (editingCoverageId) {
      console.log(`Updating coverage ${editingCoverageId}`)
      updateCovMutation.mutate({ id: editingCoverageId, payload })
    } else {
      console.log('Adding new coverage')
      coverageMutation.mutate(payload)
    }
  }

  // Total Premium — prefer the backend canonical total (covers
  // policy_coverages_data — Workers Comp / Fidelity / etc — which the
  // per-coverage reduce can't see). Falls back to the local sum so create
  // mode (no action yet) still shows a sensible number.
  const localCoverageSum = savedCoverages.reduce((sum, c: any) => {
    const num = (v: any) => parseFloat(v ?? 0) || 0
    const subPremium = (c.subcoverages || []).reduce((s: number, sub: any) => s + num(sub.calculated_value), 0)
    const extPremium = (c.extensions || []).filter(isPremiumExtension).reduce((s: number, e: any) => s + num(e.extention_calculated_value ?? e.calculatedValue), 0)
    const siPremium = (c.specified_items || []).reduce((s: number, x: any) => s + num(x.calculated_value ?? x.calculatedValue), 0)
    const motorPremium = (c.motor || c.vehicles || []).reduce((s: number, v: any) => {
      const vSpecified = (v.specifiedItems || v.specified_items || []).reduce((ss: number, x: any) => ss + num(x.calculated_value ?? x.calculatedValue), 0)
      return s + num(v.calculated_value ?? v.calculatedValue) + vSpecified
    }, 0)
    // Fidelity Guarantee premium lives in policy_coverages_data (fidelity_data).
    const fidelityPremium = (c.fidelity_data || []).filter((f: any) => !f.deleted_at).reduce((s: number, f: any) => s + num(f.premium), 0)
    const lineSum = subPremium + extPremium + siPremium + motorPremium + fidelityPremium
    return sum + (lineSum > 0 ? lineSum : num(c.calculated_value))
  }, 0)
  const totalPremium = backendTotalPremium > 0 ? backendTotalPremium : localCoverageSum

  const handleFinish = () => {
    if (policyResult) {
      localStorage.removeItem(DRAFT_KEY)
      navigate(`/policies/${policyResult.policy_id}`)
    }
  }

  // ─── Excel Import Handlers ───────────────────────────
  const handleExcelDownload = async (type: ExcelImportType) => {
    if (!policyResult) return
    setExcelStatus(s => ({ ...s, [type]: 'downloading' }))
    setExcelMessages(s => ({ ...s, [type]: '' }))
    try {
      await downloadExcelTemplate(policyResult.policy_id, type)
      setExcelStatus(s => ({ ...s, [type]: 'idle' }))
    } catch (err: any) {
      setExcelStatus(s => ({ ...s, [type]: 'error' }))
      setExcelMessages(s => ({ ...s, [type]: err?.message || 'Download failed.' }))
    }
  }

  const handleExcelImport = async (type: ExcelImportType) => {
    const file = excelFiles[type]
    if (!file || !policyResult) return
    setExcelStatus(s => ({ ...s, [type]: 'importing' }))
    setExcelMessages(s => ({ ...s, [type]: '' }))
    try {
      // Pass the currently-loaded action (respects an explicit action_id
      // from the page URL) so the import targets that action rather than
      // whatever the backend would otherwise treat as "latest".
      const res = await importExcelData(policyResult.policy_id, type, file, policyResult.action_id)
      setExcelStatus(s => ({ ...s, [type]: 'success' }))
      setExcelMessages(s => ({ ...s, [type]: res.message || 'Import successful.' }))
      setExcelFiles(f => ({ ...f, [type]: null }))
      setImportConfirmed(false) // reset confirmation whenever new data is imported

      // Fetch fresh policy data so we can show confirmation preview
      setPreviewLoading(true)
      try {
        const fresh = await fetchPolicyEditData(policyResult.policy_id)
        setImportPreview({
          risk_addresses: fresh.risk_addresses ?? [],
          coverages: fresh.coverages ?? [],
          beneficiaries_count: fresh.beneficiaries?.length ?? 0,
        })
      } catch { /* preview is best-effort */ } finally {
        setPreviewLoading(false)
      }
    } catch (err: any) {
      setExcelStatus(s => ({ ...s, [type]: 'error' }))
      const data = err?.response?.data ?? {}
      const msg = data.error || err?.message || 'Import failed.'
      let detail = ''
      // Preferred: our grouped, per-sheet row errors (specified-items import).
      const sheetErrors = data.sheetErrors as Record<string, { row: number; message: string }[]> | undefined
      // Flat per-row errors (risk-address import): a single list of
      // { row, message } — prefix each with its Excel row number so the
      // operator can locate and fix every bad row before re-uploading.
      const rowErrors = data.rowErrors as { row: number; message: string }[] | undefined
      if (sheetErrors && Object.keys(sheetErrors).length > 0) {
        detail = '\n\n' + Object.entries(sheetErrors)
          .map(([sheet, rows]) =>
            `${sheet}:\n` + rows.map(r => `   • Row ${r.row}: ${r.message}`).join('\n'))
          .join('\n\n')
      } else if (rowErrors && rowErrors.length > 0) {
        detail = '\n\n' + rowErrors.map(r => `   • Row ${r.row}: ${r.message}`).join('\n')
      } else {
        // Fallback: Laravel-Excel ValidationException failures.
        const failures: any[] = data.failures ?? []
        detail = failures.length > 0
          ? `\n${failures.slice(0, 3).map((f: any) => `Row ${f.row}: ${(f.errors || []).join(', ')}`).join('\n')}${failures.length > 3 ? `\n…and ${failures.length - 3} more` : ''}`
          : ''
      }
      setExcelMessages(s => ({ ...s, [type]: msg + detail }))
    }
  }

  // Confirm imported data: populate savedAddresses/savedCoverages from preview and open Section 4
  const handleConfirmImport = () => {
    if (!importPreview) return
    setSavedAddresses(
      importPreview.risk_addresses.map((ra: any) => ({
        id:               ra.id,
        address_name:     ra.address_name ?? '',
        physical_address: ra.physical_address ?? '',
        // UAT 2026-06-02 follow-up to PR #803/#807: kept this third
        // hydrator in sync with the two above so Excel-imported policies
        // also surface Lat/Lng + UW classification on the saved cards.
        // importPreview.risk_addresses comes from fetchPolicyEditData so
        // every column the BE returns is available here.
        lat:              ra.lat != null ? String(ra.lat) : '',
        lng:              ra.lng != null ? String(ra.lng) : '',
        risk_state:       ra.risk_state ?? null,
        risk_city:        ra.risk_city ?? null,
        const_type:       ra.const_type ?? '',
        central_fire:     !!ra.central_fire,
        central_burglar:  !!ra.central_burglar,
        gated_community:  !!ra.gated_community,
        automatic:        !!ra.automatic,
        extension:        ra.extension ?? '',
        occupation:       ra.occupation ?? '',
        year_built:       ra.year_built ?? '',
        area:             ra.area ?? '',
        structure_type:   ra.structure_type ?? '',
        town_class:       ra.town_class ?? '',
        risk_class:       ra.risk_class ?? '',
        iso_rcv:          ra.iso_rcv != null ? String(ra.iso_rcv) : '',
        distance_to_water: ra.distance_to_water ?? '',
        distance_to_fire:  ra.distance_to_fire ?? '',
        distance_to_hydrant: ra.distance_to_hydrant ?? '',
        usage:            ra.usage ?? '',
        occupancy_type:   ra.occupancy_type ?? '',
      }))
    )
    setSavedCoverages(
      importPreview.coverages.map((c: any) => ({
        // UAT 2026-06-03: bring the Excel-import coverage hydrator in line
        // with the post-create refresh hydrator (~line 492). Previously
        // dropped `_dbId`, `coverage_code`, `motor_count`, `retroactive_date`
        // and the subcoverages array — the missing `_dbId` was a real bug:
        // any post-import coverage edit would have routed to createCoverage
        // instead of updateCoverage (FE checks `_dbId` to decide). Also
        // matters for specialist family detection in SavedCoverageRow.
        _dbId:            c.id ?? null,
        coverage_id:      c.coverage_id ?? null,
        coverage_name:    c.coverage_name ?? '',
        coverage_code:    c.coverage_code ?? '',
        motor_count:      c.motor_count ?? 0,
        risk_address_id:  c.risk_address_id ?? null,
        coverage_value:   c.coverage_value != null ? String(c.coverage_value) : '',
        rate:             c.rate != null ? String(c.rate) : '',
        calculated_value: c.calculated_value != null ? String(c.calculated_value) : '',
        discount_type:    '',
        discount_value:   '',
        notes:            c.notes ?? '',
        retroactive_date: c.publicliability_date || '',
        subcoverages: (c.subcoverages ?? []).map((s: any) => ({
          id: s.sub_coverage_id ?? s.id,
          s_ScreenName: s.s_ScreenName ?? '',
          s_CoverageGroupName: s.s_CoverageGroupName ?? '',
          s_SubCoverageMainName: s.s_SubCoverageMainName ?? '',
          coverage_value: String(s.coverage_value ?? ''),
          rate: String(s.rate ?? ''),
          calculated_value: String(s.calculated_value ?? ''),
          // Workers Compensation per-row fields — kept in sync with the
          // other two coverage hydrators in this file.
          coverage_value_string:      s.coverage_value_string ?? '',
          ratefactor_type:            s.ratefactor_type ?? '',
          ratefactor_value:           s.ratefactor_value != null ? String(s.ratefactor_value) : '',
          ratefactor_value_check:     s.ratefactor_value_check ?? '',
          ratefactor_AnnualWages:     s.ratefactor_AnnualWages != null ? String(s.ratefactor_AnnualWages) : '',
          ratefactor_deposit_min_pre: s.ratefactor_deposit_min_pre != null ? String(s.ratefactor_deposit_min_pre) : '',
          discount_surcharge:         s.discount_surcharge ?? '',
          discount_surcharge_type:    s.discount_surcharge_type ?? '',
          discount_surcharge_value:   s.discount_surcharge_value != null ? String(s.discount_surcharge_value) : '',
        })),
        // Child rows, same reason as the post-create hydrator above: an
        // imported coverage that reaches Smart UW Apply without them has its
        // extensions and misc items soft-deleted by updateCoverage's
        // replace-set, and refunded on an ENDORSE. importPreview.coverages
        // comes from fetchPolicyEditData, so these are already in scope.
        extensions:       (c.extensions ?? []),
        specified_items:  (c.specified_items ?? []),
        excesses:         (c.excesses ?? []),
        fidelity_data:    (c.fidelity_data ?? []),
        theft_questions:  (c.theft_questions ?? {}),
        property_business_being: c.property_business_being ?? '',
        burglar_alarm_warranty: c.burglar_alarm_warranty ?? '',
        stated_benefits:  c.stated_benefits ?? '',
      }))
    )
    setImportConfirmed(true)
    setImportPreview(null)
    // Open Section 4 (Risk Addresses) and close Excel section
    setOpenSections(prev => {
      const next = new Set(prev)
      next.delete('excel')
      next.add('risk')
      return next
    })
  }

  // ─── Accordion helpers ───────────────────────────────
  const sectionsUnlocked = !!policyResult  // risk/coverages/review unlock once policy exists

  // ExcelImportRow and AccordionPanel are defined as top-level components (below) to prevent focus loss


  // ─── Render ──────────────────────────────────────────
  if (!lookups) return <div className="flex justify-center py-20"><LoadingSpinner size="lg" /></div>
  if (isEditLoading) return (
    <div className="flex flex-col items-center py-20 gap-3">
      <LoadingSpinner size="lg" />
      <p className="text-sm text-gray-500">Loading policy for editing…</p>
    </div>
  )
  if (misNotEditable) return (
    <div className="p-6">
      <div className="max-w-xl mx-auto bg-amber-50 border border-amber-300 rounded-lg p-6 text-center">
        <h2 className="text-lg font-semibold text-amber-900 mb-2">MIS policies aren't editable on V2 yet</h2>
        <p className="text-sm text-amber-800 mb-4">
          The V2 Edit Policy wizard is for Commercial / Domestic / Specialist coverage policies.
          For MIS retail policies (Accidental Death, Hospital Cashback, Legal, Mobile &amp; Electronic
          Device, Third Party Car), use V1 admin for now — a dedicated V2 MIS edit flow is in progress.
        </p>
        <Link to={`/policies/${editPolicyId}`}
          className="inline-block px-4 py-2 bg-amber-600 text-white rounded text-sm font-medium hover:bg-amber-700">
          ← Back to Policy
        </Link>
      </div>
    </div>
  )

  const errCount = Object.keys(errors).filter(k => k !== '_general' && k !== 'cov_duplicate_family' && errors[k]).length

  const missingFields = Object.keys(errors)
    .filter(k => k !== '_general' && k !== 'cov_duplicate_family' && errors[k])
    .map(k => errors[k])
    .join(', ')

  // Term Start / Expiry are freely editable on any QUOTE action that owns a full
  // policy period. In create mode the policy is always a new-business quote. In
  // edit mode, honor the selected action: QUOTE + a full-period transaction type.
  // This unlocks the Expiry (end) date which is otherwise auto-calculated from
  // the premium frequency (manual entry allowed only for freq 6 / MANUAL INPUT).
  //
  // This used to require NEWBUSINESS, which locked Expiry on an Anniversary
  // Renewal quote even though the backend update endpoint accepts the dates and
  // propagates them to the action and its term for exactly these products
  // (PolicyCreateController::DATE_SYNC_PRODUCT_IDS — Engineering 16 included).
  // The dates could then only be changed via Edit Transaction, which writes the
  // same rows by a different route.
  const selectedAction = availableActions.find(a => a.id === selectedActionId)
  const datesEditable = !isEditMode || (
    selectedAction?.status === 'QUOTE'
    && FULL_PERIOD_TX_TYPES.includes(String(selectedAction?.transaction_type ?? '').toUpperCase())
  )

  return (
    <div className="max-w-5xl mx-auto py-6 px-4">
      {/* ── Action switcher (edit mode, multiple actions) ── */}
      {isEditMode && availableActions.length > 1 && (
        <div className="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-lg flex flex-wrap items-center gap-3">
          <span className="text-xs font-semibold text-amber-700 uppercase">Editing Action:</span>
          <select value={selectedActionId ?? ''} onChange={e => changeAction(Number(e.target.value))}
            disabled={reloading}
            className="px-3 py-1.5 border border-amber-300 rounded-md text-sm bg-white min-w-[360px]">
            {[...availableActions].sort((a, b) => {
              const da = a.effective_from ? new Date(a.effective_from).getTime() : 0
              const db = b.effective_from ? new Date(b.effective_from).getTime() : 0
              if (db !== da) return db - da
              return b.id - a.id
            }).map(a => {
              const from = a.effective_from ? new Date(a.effective_from).toLocaleDateString('en-GB') : '?'
              const to   = a.effective_to   ? new Date(a.effective_to).toLocaleDateString('en-GB')   : '?'
              return (
                <option key={a.id} value={a.id}>
                  {a.transaction_type} — {a.status} ({from} – {to})
                </option>
              )
            })}
          </select>
          {reloading && <span className="text-xs text-amber-700 animate-pulse">Reloading…</span>}
          <span className="text-xs text-gray-500">
            {(() => {
              const s = availableActions.find(a => a.id === selectedActionId)?.status
              if (s === 'QUOTE') return 'Quote / draft — edits will be saved.'
              if (s) return `⚠ This action is ${s} (frozen). Endorse or unissue to edit.`
              return ''
            })()}
          </span>
        </div>
      )}

      {/* ── Frozen-action banner ── */}
      {(() => {
        const status = availableActions.find(a => a.id === selectedActionId)?.status
        if (!isEditMode || !status || status === 'QUOTE') return null
        // Only QUOTE rows are editable (legacy graphiteBWV8 EditWizard rule).
        // ISSUED → create Endorsement / Anniversary-Renew / Cancel / Reinstate
        // IN_APPROVAL / APPROVED → Unissue first to drop back to QUOTE
        const bgClass = status === 'ISSUED'
          ? 'bg-red-50 border-red-200 text-red-800'
          : 'bg-amber-50 border-amber-200 text-amber-800'
        const instructions = status === 'ISSUED'
          ? 'Issued policy actions are frozen. To modify coverage, vehicles, extensions, or premium, create a new Endorsement / Anniversary-Renew from the Policy Actions tab — that spawns a QUOTE you can edit here.'
          : `This action is ${status}. Only QUOTE actions are editable. Go to the Policy Actions tab and click "Unissue" to drop this row back to QUOTE before editing.`
        // The dead end this removes: opening Edit Policy from the Policy Actions
        // tab carries that tab's selected action, so a policy with an open
        // Anniversary Renewal quote lands on the frozen ISSUED new-business row.
        // The editable quote was already in the "Editing Action" dropdown, but
        // nothing said so — the page just looked read-only, which reads as
        // "the dates cannot be changed" rather than "you are on the wrong
        // action". The explicit choice is still honoured; this only offers the
        // switch, it does not make it silently.
        const editableQuote = availableActions.find(a => a.status === 'QUOTE')
        return (
          <div className={`mb-4 p-4 border-2 rounded-lg ${bgClass}`}>
            <div className="flex items-start gap-3">
              <span className="text-xl">🔒</span>
              <div className="flex-1">
                <h3 className="font-semibold">This action is {status} — edits are disabled</h3>
                <p className="text-sm mt-1">{instructions}</p>
                {editableQuote && (
                  <p className="text-sm mt-2">
                    This policy has an editable quote:{' '}
                    <button
                      type="button"
                      onClick={() => changeAction(editableQuote.id)}
                      className="underline font-semibold hover:no-underline">
                      switch to {editableQuote.transaction_type} — QUOTE
                      {editableQuote.effective_from
                        ? ` (${new Date(editableQuote.effective_from).toLocaleDateString('en-GB')}`
                          + `${editableQuote.effective_to ? ' – ' + new Date(editableQuote.effective_to).toLocaleDateString('en-GB') : ''})`
                        : ''}
                    </button>
                    {' '}to change its dates, coverage or premium.
                  </p>
                )}
                <Link to={`/policies/${editPolicyId}`}
                  className="inline-block mt-2 text-sm underline font-medium">
                  ← Open Policy Actions
                </Link>
              </div>
            </div>
          </div>
        )
      })()}

      {/* ── Header ── */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">
            {isEditMode ? 'Edit Policy' : 'Create DOM/COM Policy'}
          </h1>
          {policyResult && (
            <p className="text-sm text-gray-500 mt-1">
              Policy <span className="text-blue-600 font-semibold">{policyResult.policy_number}</span>
            </p>
          )}
        </div>
        <div className="flex gap-2">
          {!isEditMode && import.meta.env.MODE !== 'production' && (
            <button
              onClick={() => {
                const testData = generateTestPolicy()
                // Use real agencies/states/plans from cache for valid IDs
                const agencyId = agencies.length > 0
                  ? agencies[Math.floor(Math.random() * agencies.length)].id
                  : testData.agency_id
                const stateId = lookups.states.length > 0
                  ? (lookups.states[Math.floor(Math.random() * lookups.states.length)] as any).id
                  : testData.state
                const productPlans = allPlans.filter((p: any) => String(p.product_id) === String(testData.product_id))
                const planId = productPlans.length > 0
                  ? productPlans[Math.floor(Math.random() * productPlans.length)].id
                  : null
                setForm({ ...testData, agency_id: agencyId, agent_id: null, state: stateId, city: null, plan_id: planId })
                setErrors({})
              }}
              className="px-3 py-1.5 text-sm text-emerald-700 border border-emerald-300 rounded-md hover:bg-emerald-50"
              title="Fill all fields with randomised test data (different each time)"
            >
              Fill Test Data
            </button>
          )}
          {isEditMode && (
            <button onClick={() => navigate(`/policies/${editPolicyId}`)}
              className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
              ← Back to Policy
            </button>
          )}
          {policyResult && !isEditMode && (
            <button onClick={handleFinish}
              className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
              View Policy →
            </button>
          )}
          <button onClick={() => navigate('/policies')}
            className="px-3 py-1.5 text-sm text-gray-600 border rounded-md hover:bg-gray-50">
            Back to List
          </button>
        </div>
      </div>

      {/* Draft Banner */}
      {showDraftBanner && !isEditMode && (
        <div className="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-md text-amber-800 text-sm flex items-center justify-between">
          <span>You have an unsaved draft.</span>
          <div className="flex gap-2">
            <button onClick={handleLoadDraft} className="px-3 py-1 bg-amber-600 text-white rounded text-xs font-medium">Resume</button>
            <button onClick={() => { localStorage.removeItem(DRAFT_KEY); setShowDraftBanner(false) }}
              className="px-3 py-1 border border-amber-300 rounded text-xs">Discard</button>
          </div>
        </div>
      )}

      {/* Error Banner */}
      {errors._general && (
        <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-md text-red-700 text-sm flex items-center justify-between">
          <span>{errors._general}</span>
          <button onClick={() => setErrors(p => ({ ...p, _general: '' }))} className="ml-2 text-red-400 hover:text-red-600">✕</button>
        </div>
      )}
      {errCount > 0 && (
        <div className="mb-4 flex items-start gap-2 px-4 py-2.5 bg-red-50 border border-red-300 rounded-lg text-sm text-red-700 font-medium">
          <span className="mt-0.5">⚠</span>
          <div className="flex-1">
            <div>{errCount} required field{errCount > 1 ? 's' : ''} missing:</div>
            <div className="text-xs font-normal mt-1.5 text-red-600">{missingFields}</div>
          </div>
        </div>
      )}

      {/* All form sections — wrapped in a disabled fieldset when the current
          action is non-QUOTE. Only QUOTE rows are editable per legacy
          graphiteBWV8 EditWizard. ISSUED / IN_APPROVAL / APPROVED all lock
          the form. */}
      <fieldset disabled={isEditMode && (() => {
          const s = availableActions.find(a => a.id === selectedActionId)?.status
          return !!s && s !== 'QUOTE'
        })()}
        className="contents">
      {/* ── Section 1: Policy & Customer Details ── */}
      <AccordionPanel
        id="policy"
        title="1. Policy & Customer Details"
        badge={policyResult ? `#${policyResult.policy_number}` : undefined}
        openSections={openSections}
        onToggle={toggleSection}
      >
        <div className="pt-4">
          <StepPolicyDetails
            form={form} setForm={setForm as any}
            errors={errors} setErrors={setErrors as any}
            lookups={lookups} agencies={agencies} agents={agents}
            cities={cities} plans={plans} companies={companies}
            agenciesLoading={agenciesLoading} agentsLoading={agentsLoading}
            citiesLoading={citiesLoading} plansLoading={plansLoading}
            isEditMode={isEditMode || !!policyResult}
            datesEditable={datesEditable}
          />
        </div>
        <div className="flex justify-end mt-4 pt-4 border-t">
          <button
            // Once a policy exists (either /edit mode OR the user already
            // clicked Create in this session and we have a policyResult),
            // subsequent clicks must PUT to the same policy instead of
            // POST-ing a new one. Without this guard, a DOB typo gets
            // corrected and the wizard creates a second duplicate policy.
            onClick={(isEditMode || policyResult) ? handleUpdatePolicy : handleCreatePolicy}
            disabled={createMutation.isPending || updateMutation.isPending}
            className="px-6 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-50"
          >
            {createMutation.isPending ? 'Creating…' :
             updateMutation.isPending ? 'Saving…' :
             isEditMode ? 'Save Policy Details' :
             policyResult ? 'Update Details' : 'Create Policy & Continue →'}
          </button>
        </div>
      </AccordionPanel>

      {/* ── Section 2: Import Risk Addresses ── */}
      <AccordionPanel
        id="risk-address-import"
        title="2. Import Risk Addresses"
        openSections={openSections}
        onToggle={toggleSection}
        badge={sectionsUnlocked ? 'Bulk import risk addresses from Excel' : undefined}
        locked={!sectionsUnlocked}
      >
        <div className="pt-4 space-y-6">
          <p className="text-sm text-gray-600">
            Upload an Excel file to bulk-import risk addresses. This is useful for policies with many locations.
            <span className="ml-1 text-gray-400 italic">You can also skip this section and add addresses manually in Section 4.</span>
          </p>

          <ExcelImportRow
            label="Risk Address Data"
            description="Single-sheet Excel file with risk address details. Columns: Address Name, Physical Address, Risk City, Risk District, Construction Type, etc."
            type="risk-address"
            file={excelFiles['risk-address']}
            status={excelStatus['risk-address']}
            message={excelMessages['risk-address']}
            onDownload={() => handleExcelDownload('risk-address')}
            onFileChange={f => setExcelFiles(e => ({ ...e, 'risk-address': f }))}
            onImport={() => handleExcelImport('risk-address')}
          />
        </div>
      </AccordionPanel>

      {/* ── Section 3: Import from Excel ── */}
      <AccordionPanel
        id="excel"
        title="3. Import from Excel"
        openSections={openSections}
        onToggle={toggleSection}
        badge={
          importConfirmed
            ? `✓ Confirmed — ${savedAddresses.length} addresses · ${savedCoverages.length} coverages`
            : sectionsUnlocked ? 'Bulk import coverages & beneficiaries' : undefined
        }
        locked={!sectionsUnlocked}
      >
        <div className="pt-4 space-y-6">
          <p className="text-sm text-gray-600">
            For large policies — upload Excel files to bulk-import coverages, specified items and beneficiaries.
            After uploading, review and confirm the imported data before moving to manual editing in Sections 4 &amp; 5.
            <span className="ml-1 text-gray-400 italic">You can also skip this section and enter data manually.</span>
          </p>

          {/* ── Coverage Data (imports risk addresses + coverages) ── */}
          <ExcelImportRow
            label="Coverage Data"
            description="Multi-sheet workbook: one sheet per coverage type (FIRE, THEFT, etc.). Imports risk addresses and all coverage lines in one file."
            type="coverages"
            file={excelFiles['coverages']}
            status={excelStatus['coverages']}
            message={excelMessages['coverages']}
            onDownload={() => handleExcelDownload('coverages')}
            onFileChange={f => setExcelFiles(e => ({ ...e, 'coverages': f }))}
            onImport={() => handleExcelImport('coverages')}
          />

          {/* ── Specified Items ── */}
          <ExcelImportRow
            label="Specified Items"
            description="Items specifically listed in the policy (jewellery, computers, equipment, etc.). Columns: Risk Address, Description, Sum Insured."
            type="specified-items"
            file={excelFiles['specified-items']}
            status={excelStatus['specified-items']}
            message={excelMessages['specified-items']}
            onDownload={() => handleExcelDownload('specified-items')}
            onFileChange={f => setExcelFiles(e => ({ ...e, 'specified-items': f }))}
            onImport={() => handleExcelImport('specified-items')}
          />

          {/* ── Beneficiary Data ── */}
          <ExcelImportRow
            label="Beneficiary Data"
            description="Policy beneficiaries. Columns: First Name, Middle Name, Last Name, Relation, Gender, Payment, Omang, Passport, Date Of Birth."
            type="beneficiaries"
            file={excelFiles['beneficiaries']}
            status={excelStatus['beneficiaries']}
            message={excelMessages['beneficiaries']}
            onDownload={() => handleExcelDownload('beneficiaries')}
            onFileChange={f => setExcelFiles(e => ({ ...e, 'beneficiaries': f }))}
            onImport={() => handleExcelImport('beneficiaries')}
          />

          {/* ── Confirmation Preview ── */}
          {previewLoading && (
            <div className="flex items-center gap-2 py-4 text-sm text-gray-500">
              <span className="w-4 h-4 border-2 border-blue-400 border-t-transparent rounded-full animate-spin inline-block" />
              Loading imported data for confirmation…
            </div>
          )}

          {!previewLoading && importPreview && (
            <div className="border-2 border-blue-200 rounded-xl bg-blue-50 p-4 space-y-4">
              <div className="flex items-center gap-2">
                <span className="text-blue-600 font-semibold text-sm">📋 Review Imported Data</span>
                <span className="text-xs text-blue-500">Confirm below to populate Sections 4 &amp; 5</span>
              </div>

              {/* Risk Addresses preview */}
              <div>
                <p className="text-xs font-semibold text-gray-600 uppercase tracking-wider mb-1">
                  Risk Addresses ({importPreview.risk_addresses.length})
                </p>
                {importPreview.risk_addresses.length === 0 ? (
                  <p className="text-xs text-gray-400 italic">No risk addresses imported.</p>
                ) : (
                  <div className="overflow-x-auto rounded border border-blue-200 bg-white">
                    <table className="w-full text-xs">
                      <thead className="bg-gray-50 border-b">
                        <tr>
                          <th className="px-3 py-1.5 text-left font-medium text-gray-500">Address Name</th>
                          <th className="px-3 py-1.5 text-left font-medium text-gray-500">Physical Address</th>
                          <th className="px-3 py-1.5 text-left font-medium text-gray-500">Construction</th>
                          <th className="px-3 py-1.5 text-left font-medium text-gray-500">Extension</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y">
                        {importPreview.risk_addresses.map((ra: any, i: number) => (
                          <tr key={i} className="hover:bg-gray-50">
                            <td className="px-3 py-1.5 font-medium text-gray-800">{ra.address_name || '—'}</td>
                            <td className="px-3 py-1.5 text-gray-600">{ra.physical_address || '—'}</td>
                            <td className="px-3 py-1.5 text-gray-600">{ra.const_type || '—'}</td>
                            <td className="px-3 py-1.5 text-gray-600">{ra.extension || '—'}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>

              {/* Coverages preview */}
              <div>
                <p className="text-xs font-semibold text-gray-600 uppercase tracking-wider mb-1">
                  Coverages ({importPreview.coverages.length})
                </p>
                {importPreview.coverages.length === 0 ? (
                  <p className="text-xs text-gray-400 italic">No coverages imported.</p>
                ) : (
                  <div className="overflow-x-auto rounded border border-blue-200 bg-white">
                    <table className="w-full text-xs">
                      <thead className="bg-gray-50 border-b">
                        <tr>
                          <th className="px-3 py-1.5 text-left font-medium text-gray-500">Coverage</th>
                          <th className="px-3 py-1.5 text-left font-medium text-gray-500">Risk Address</th>
                          <th className="px-3 py-1.5 text-right font-medium text-gray-500">Sum Insured</th>
                          <th className="px-3 py-1.5 text-right font-medium text-gray-500">Rate %</th>
                          <th className="px-3 py-1.5 text-right font-medium text-gray-500">Premium</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y">
                        {importPreview.coverages.map((c: any, i: number) => (
                          <tr key={i} className="hover:bg-gray-50">
                            <td className="px-3 py-1.5 font-medium text-gray-800">{c.coverage_name || '—'}</td>
                            <td className="px-3 py-1.5 text-gray-600">{c.risk_address_name || c.risk_address_id || '—'}</td>
                            <td className="px-3 py-1.5 text-right text-gray-700">{formatCurrency(c.coverage_value)}</td>
                            <td className="px-3 py-1.5 text-right text-gray-700">{c.rate ? `${c.rate}%` : '—'}</td>
                            <td className="px-3 py-1.5 text-right font-medium text-gray-800">{formatCurrency(c.calculated_value)}</td>
                          </tr>
                        ))}
                        <tr className="bg-gray-50 font-semibold">
                          <td colSpan={4} className="px-3 py-1.5 text-right text-gray-700">Total Premium:</td>
                          <td className="px-3 py-1.5 text-right text-blue-700">
                            {formatCurrency(importPreview.coverages.reduce((s: number, c: any) => s + parseFloat(c.calculated_value || '0'), 0))}
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                )}
              </div>

              {/* Beneficiaries count */}
              {importPreview.beneficiaries_count > 0 && (
                <p className="text-xs text-gray-600">
                  <span className="font-semibold">{importPreview.beneficiaries_count}</span> beneficiar{importPreview.beneficiaries_count === 1 ? 'y' : 'ies'} imported.
                </p>
              )}

              {/* Confirm button */}
              <div className="flex items-center justify-between pt-2 border-t border-blue-200">
                <p className="text-xs text-gray-500">
                  Confirming will pre-populate Sections 4 &amp; 5 with the data above. You can still add or edit entries manually.
                </p>
                <button
                  type="button"
                  onClick={handleConfirmImport}
                  className="ml-4 shrink-0 px-5 py-2 text-sm font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700 transition"
                >
                  Confirm &amp; Continue to Risk Addresses →
                </button>
              </div>
            </div>
          )}

          {/* Already confirmed banner */}
          {importConfirmed && !importPreview && (
            <div className="flex items-center gap-3 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
              <span className="text-green-500 text-base">✓</span>
              <span>
                Import confirmed — <strong>{savedAddresses.length}</strong> risk address{savedAddresses.length !== 1 ? 'es' : ''} and{' '}
                <strong>{savedCoverages.length}</strong> coverage{savedCoverages.length !== 1 ? 's' : ''} loaded into Sections 4 &amp; 5.
              </span>
            </div>
          )}
        </div>
      </AccordionPanel>

      {smartUwRisk && (
        <SmartUwPrefillPanel
          risk={smartUwRisk}
          segment={smartUwSegment}
          availableCoverages={availableCoverages}
          hasRiskAddress={savedAddresses.length > 0}
          productSelected={!!form.product_id && form.product_id > 0}
          onLoad={handleSmartUwLoad}
          loadedIndex={smartUwLoadedIndex}
          unplacedExtensions={smartUwUnplaced}
          loadingIndex={smartUwLoadingIndex}
          insuredMismatch={smartUwInsuredMismatch}
          savedAddressCount={savedAddresses.length}
          onLoadRiskAddress={handleSmartUwLoadRiskAddress}
          riskAddressLoaded={smartUwAddrLoaded}
          existingCoverageIds={savedCoverages
            .map((c: any) => Number(c.coverage_id))
            .filter((n) => n > 0)}
          onApply={handleSmartUwApply}
          applying={smartUwApplying}
          applyResult={smartUwResult}
          onRegisterVehicles={handleSmartUwRegisterVehicles}
          vehicleState={smartUwVehicleState}
          motorTargets={smartUwMotorTargets}
          motorTargetId={smartUwMotorTargetId}
          onMotorTargetChange={(id) => {
            setSmartUwMotorTargetId(id)
            refreshSmartUwVehicleState(id)
          }}
          onAttachToMotor={handleSmartUwAttachToMotor}
          attachingPlate={smartUwAttaching}
        />
      )}

      {/* ── Section 4: Risk Addresses ── */}
      <AccordionPanel
        id="risk"
        title="4. Risk Addresses"
        badge={savedAddresses.length > 0 ? `${savedAddresses.length} saved` : undefined}
        locked={!sectionsUnlocked}
        openSections={openSections}
        onToggle={toggleSection}
      >
        <div className="pt-4">
          <StepRiskAddresses
            riskForm={riskForm} setRiskForm={setRiskForm}
            savedAddresses={savedAddresses}
            onSave={handleSaveRiskAddress}
            onDelete={async (id) => {
              // Cancel = soft-delete the risk address + every coverage and
              // child row under it. Backend stamps endors_flag/previousActionIdCov
              // so Rate's pro-rata computes a refund.
              if (!policyResult?.policy_id) {
                setSavedAddresses(p => p.filter(a => a.id !== id))
                return
              }
              if (!confirm('Cancel this risk address? All coverages under it will be cancelled (soft-delete; can be reinstated). Pro-rata applies on the next Rate.')) return
              try {
                await deleteRiskAddress(policyResult.policy_id, id)
                if (editingRiskAddressId === id) { setEditingRiskAddressId(null); setRiskForm({ ...INITIAL_RISK }) }
                await reloadCoveragesFromServer()
                // Refresh risk addresses too — backend now flags this one
                // as deleted_at != null but still returns it.
                await reloadRiskAddressesFromServer()
              } catch (err: any) {
                setErrors({ _general: err?.response?.data?.message || 'Failed to cancel risk address.' })
              }
            }}
            onReinstate={async (id) => {
              if (!policyResult?.policy_id) return
              if (!confirm('Reinstate this risk address? All its coverages return; pro-rata applies on the next Rate.')) return
              try {
                await reinstateRiskAddress(policyResult.policy_id, id)
                await reloadCoveragesFromServer()
                await reloadRiskAddressesFromServer()
              } catch (err: any) {
                setErrors({ _general: err?.response?.data?.message || 'Failed to reinstate risk address.' })
              }
            }}
            onEdit={(addr) => {
              // Prefill the form from the card (only the RiskAddressForm keys —
              // id/deleted_at are card-only). The risk_state change re-fetches
              // the City options via the existing effect.
              const { id: _id, deleted_at: _del, state_name: _sn, city_name: _cn, ...formFields } = addr as any
              setEditingRiskAddressId(addr.id)
              setRiskForm({ ...INITIAL_RISK, ...formFields })
              setErrors({})
              setOpenSections(prev => new Set(prev).add('risk'))
            }}
            onCancelEdit={() => { setEditingRiskAddressId(null); setRiskForm({ ...INITIAL_RISK }); setErrors({}) }}
            editingId={editingRiskAddressId}
            errors={errors} setErrors={setErrors as any}
            lookups={lookups}
            riskCities={riskCities} riskCitiesLoading={riskCitiesLoading}
            saving={riskMutation.isPending || riskUpdateMutation.isPending}
          />
        </div>
      </AccordionPanel>

      {/* ── Section 4: Coverages ──
          UAT 2026-05-27 (Muskan QA P2): "Coverages added without Risk Address."
          Backend already enforces risk_address_id required at addCoverage
          (PolicyCreateController:1565), and StepCoverages renders an empty-state
          warning inside the Add Coverage section when savedAddresses is empty.
          Adding a third defence: lock the entire accordion until at least one
          Risk Address has been saved, so the section can't even be expanded
          when there's nothing for the coverage to attach to. Belt + suspenders. */}
      <AccordionPanel
        id="coverages"
        title="5. Coverages"
        badge={savedCoverages.length > 0 ? `${savedCoverages.length} saved · ${formatCurrency(totalPremium)}` : undefined}
        locked={!sectionsUnlocked || savedAddresses.length === 0}
        lockedReason={!sectionsUnlocked ? 'save Section 1 first' : 'add a Risk Address first'}
        openSections={openSections}
        onToggle={toggleSection}
      >
        <div className="pt-4">
          <StepCoverages
            coverageForm={coverageForm} setCoverageForm={setCoverageForm}
            savedCoverages={savedCoverages} setSavedCoverages={setSavedCoverages}
            savedAddresses={savedAddresses} availableCoverages={availableCoverages}
            smartUwLines={smartUwLines}
            onSmartUwApplied={() => setSmartUwLines(null)}
            policyId={policyResult?.policy_id}
            productId={form.product_id}
            onSave={handleSaveCoverage}
            onDelete={async (i) => {
              // Persisted coverages (with _dbId) need a backend DELETE so they
              // don't resurrect on the next page load. Unsaved ones (local
              // state only) just drop from the array. Mirrors legacy
              // ManageCoverages::removeCoverage — it soft-deletes policy_coverages
              // + its subcoverages/extensions/specified_items/motor rows.
              const cov = savedCoverages[i] as any
              if (!confirm(`Cancel ${cov?.coverage_name || 'this coverage'}? It will be soft-deleted and can be reinstated.`)) return
              if (cov?._dbId && policyResult?.policy_id) {
                try {
                  await deleteCoverage(policyResult.policy_id, cov._dbId)
                } catch (err: any) {
                  setErrors({ _general: err?.response?.data?.message || 'Failed to remove coverage on server.' })
                  return
                }
              }
              setSavedCoverages(p => p.filter((_, idx) => idx !== i))
            }}
            editingCoverageIndex={editingCoverageIndex}
            onEdit={(i) => {
              // Handle cancel edit (i < 0)
              if (i < 0) {
                setEditingCoverageIndex(null)
                setEditingCoverageId(null)
                editCovIndexRef.current = null
                return
              }
              const cov = savedCoverages[i]
              setCoverageForm({ ...cov })
              setEditingCoverageId((cov as any)._dbId || null)
              setEditingCoverageIndex(i)
              editCovIndexRef.current = i
              // Keep coverage in the list — it will be updated in-place on save
              setTimeout(() => document.getElementById('add-coverage-form')?.scrollIntoView({ behavior: 'smooth' }), 50)
            }}
            onReinstate={async (i) => {
              const cov = savedCoverages[i] as any
              if (!cov?._dbId || !policyResult?.policy_id) return
              if (!confirm(`Reinstate ${cov?.coverage_name || 'this coverage'}? Pro-rata applies on the next Rate.`)) return
              try {
                await reinstateCoverage(policyResult.policy_id, cov._dbId)
                await reloadCoveragesFromServer()
              } catch (err: any) {
                setErrors({ _general: err?.response?.data?.message || 'Failed to reinstate coverage.' })
              }
            }}
            errors={errors} setErrors={setErrors as any}
            saving={coverageMutation.isPending || updateCovMutation.isPending}
            backendTotalPremium={backendTotalPremium}
          />
        </div>
      </AccordionPanel>

      {/* Members / Beneficiaries / Vehicles / Devices wizard steps exist as
         components (CreateWizard/Step*.tsx) but are intentionally NOT mounted
         here — this wizard only supports DomCom/Engineering products
         (7, 8, 16, 17, 18, 19) where:
           - Vehicles are attached under COMMERCIALMOTOR coverage in Section 5
           - Members / Beneficiaries / Devices are not part of the flow
         For retail products (MIS 1–6, 9, 12, 13), users add
         members/beneficiaries/vehicles/devices via the policy detail tabs
         after the policy is created, or bulk-import via Excel in Section 3. */}

      {/* ── Review & Actions ── */}
      <AccordionPanel
        id="review"
        title="6. Review & Actions"
        badge={policyResult ? 'Ready' : undefined}
        locked={!sectionsUnlocked}
        openSections={openSections}
        onToggle={toggleSection}
      >
        <div className="space-y-6 pt-4">
          {/* Summary stats */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div className="text-center p-3 bg-blue-50 rounded-lg">
              <p className="text-2xl font-bold text-blue-700">{policyResult?.policy_number || '-'}</p>
              <p className="text-xs text-blue-600">Policy Number</p>
            </div>
            <div className="text-center p-3 bg-green-50 rounded-lg">
              <p className="text-2xl font-bold text-green-700">{savedAddresses.length}</p>
              <p className="text-xs text-green-600">Risk Addresses</p>
            </div>
            <div className="text-center p-3 bg-purple-50 rounded-lg">
              <p className="text-2xl font-bold text-purple-700">{savedCoverages.length}</p>
              <p className="text-xs text-purple-600">Coverages</p>
            </div>
            <div className="text-center p-3 bg-amber-50 rounded-lg">
              <p className="text-2xl font-bold text-amber-700">{formatCurrency(totalPremium)}</p>
              <p className="text-xs text-amber-600">Total Premium</p>
            </div>
            {Math.abs(backendProRataPremium) > 0.001 && (
              <div className={`text-center p-3 rounded-lg ${backendProRataPremium < 0 ? 'bg-red-50' : 'bg-blue-50'}`}>
                <p className={`text-2xl font-bold ${backendProRataPremium < 0 ? 'text-red-700' : 'text-blue-700'}`}>
                  {formatCurrency(backendProRataPremium)}
                </p>
                <p className={`text-xs ${backendProRataPremium < 0 ? 'text-red-600' : 'text-blue-600'}`}>
                  Pro-Rata {backendProRataPremium < 0 ? '(Refund)' : '(Charge)'}
                </p>
              </div>
            )}
          </div>

          {/* Customer + Policy quick-view */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="p-4 bg-gray-50 rounded-lg">
              <h4 className="text-sm font-semibold text-gray-700 mb-1">Customer</h4>
              <p className="text-sm">{form.first_name} {form.middle_name} {form.last_name}</p>
              <p className="text-xs text-gray-500 mt-0.5">{form.omang || form.passport} | {form.cellphone} | {form.email}</p>
            </div>
            <div className="p-4 bg-gray-50 rounded-lg">
              <h4 className="text-sm font-semibold text-gray-700 mb-1">Policy</h4>
              <p className="text-sm">{lookups?.products?.find((p: any) => p.id === form.product_id)?.name}</p>
              <p className="text-xs text-gray-500 mt-0.5">{form.term_start_date} → {form.expiry_date}</p>
            </div>
          </div>

          {/* Coverage summary table */}
          {savedCoverages.length > 0 && (
            <div>
              <h4 className="text-sm font-semibold text-gray-700 mb-2">Coverages</h4>
              <table className="w-full text-sm border rounded-lg overflow-hidden">
                <thead>
                  <tr className="bg-gray-50 text-xs text-gray-600">
                    <th className="text-left p-2 border-b">Coverage</th>
                    <th className="text-right p-2 border-b">Sum Insured</th>
                    <th className="text-right p-2 border-b">Rate</th>
                    <th className="text-right p-2 border-b">Premium</th>
                  </tr>
                </thead>
                <tbody>
                  {savedCoverages.map((c, i) => {
                    const num = (v: any) => parseFloat(v ?? 0) || 0
                    const sumInsured = (() => {
                      const subValue = (c.subcoverages || []).reduce((s, sub) => s + num(sub.coverage_value), 0)
                      const siValue = ((c as any).specified_items || []).reduce((s: number, x: any) => s + num(x.sum_insured), 0)
                      const motorValue = ((c as any).motor || (c as any).vehicles || []).reduce((s: number, v: any) => s + num(v.coverage_value), 0)
                      return (subValue + siValue + motorValue) || num(c.coverage_value)
                    })()
                    const premium = (() => {
                      const subPremium = (c.subcoverages || []).reduce((s, sub) => s + num(sub.calculated_value), 0)
                      const extPremium = ((c as any).extensions || []).filter(isPremiumExtension).reduce((s: number, e: any) => s + num(e.extention_calculated_value ?? e.calculatedValue), 0)
                      const siPremium = ((c as any).specified_items || []).reduce((s: number, x: any) => s + num(x.calculated_value ?? x.calculatedValue), 0)
                      const motorPremium = ((c as any).motor || (c as any).vehicles || []).reduce((s: number, v: any) => {
                        const vSpecified = (v.specifiedItems || v.specified_items || []).reduce((ss: number, x: any) => ss + num(x.calculated_value ?? x.calculatedValue), 0)
                        return s + num(v.calculated_value ?? v.calculatedValue) + vSpecified
                      }, 0)
                      // Fidelity Guarantee premium lives in policy_coverages_data (fidelity_data).
                      const fidelityPremium = ((c as any).fidelity_data || []).filter((f: any) => !f.deleted_at).reduce((s: number, f: any) => s + num(f.premium), 0)
                      return (subPremium + extPremium + siPremium + motorPremium + fidelityPremium) || num(c.calculated_value)
                    })()
                    // Step 6 Rate display: c.rate is the coverage-level
                    // rate field, which is 0 / empty for most commercial
                    // coverages because the real rate lives on sub-coverages
                    // and extensions. UAT 2026-05-26 (Prathap BUG-017)
                    // reported "Rate = 0%" on the Review table even though
                    // the engine had computed the right premium — the row
                    // was showing the raw c.rate. Derive the effective rate
                    // from premium / sumInsured when c.rate is missing so
                    // operators see a meaningful number.
                    const displayRate = (() => {
                      const r = parseFloat(c.rate as any)
                      if (!isNaN(r) && r > 0) return `${r.toFixed(2)}%`
                      if (sumInsured > 0 && premium > 0) {
                        return `${((premium / sumInsured) * 100).toFixed(2)}%`
                      }
                      return '—'
                    })()
                    return (
                      <tr key={i} className="border-b border-gray-100">
                        <td className="p-2">{c.coverage_name}</td>
                        <td className="p-2 text-right">{formatCurrency(sumInsured)}</td>
                        <td className="p-2 text-right">{displayRate}</td>
                        <td className="p-2 text-right font-medium">{formatCurrency(premium)}</td>
                      </tr>
                    )
                  })}
                  <tr className="font-semibold bg-gray-50">
                    <td colSpan={3} className="p-2 text-right">Total Premium:</td>
                    <td className="p-2 text-right text-blue-700">{formatCurrency(totalPremium)}</td>
                  </tr>
                  {Math.abs(backendProRataPremium) > 0.001 && (
                    <tr className="font-semibold bg-gray-50">
                      <td colSpan={3} className="p-2 text-right">
                        Pro-Rata {backendProRataPremium < 0 ? '(Refund)' : '(Charge)'}:
                      </td>
                      <td className={`p-2 text-right ${backendProRataPremium < 0 ? 'text-red-700' : 'text-blue-700'}`}>
                        {formatCurrency(backendProRataPremium)}
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          )}

          {/* KYC Documents — commented out per ops request 2026-06-02.
              Per-doc uploads now live on the dedicated `KYC Documents` tab
              of the policy detail page (V8-style upload grid: tier-aware
              cards with pencil/replace + trash/delete + Submit). Keeping
              the inline 5-input flow here duplicated the work and the
              backend endpoint silently rerouted some slots, so the
              operator team asked us to remove the section from create/
              edit. Restore by un-commenting if a single-shot upload is
              ever needed again at policy creation. */}
          {/*
          <Section title="KYC Documents">
            <p className="text-xs text-gray-500 -mt-2">
              Upload copies of customer documents (images or PDF). Click <b>Save KYC Documents</b> to persist — files upload to S3 and
              the KYC Compliance status flips to "awaiting verification" once received.
            </p>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <FileUpload label="Omang Copy" file={kycDocs.omang_doc} accept="image/*,application/pdf"
                onChange={f => setKycDocs(k => ({ ...k, omang_doc: f }))} />
              <FileUpload label="Passport Copy" file={kycDocs.passport_doc} accept="image/*,application/pdf"
                onChange={f => setKycDocs(k => ({ ...k, passport_doc: f }))} />
              <FileUpload label="Proof of Residence" file={kycDocs.proof_of_residence} accept="image/*,application/pdf"
                onChange={f => setKycDocs(k => ({ ...k, proof_of_residence: f }))} />
              <FileUpload label="Proof of Income" file={kycDocs.proof_of_income} accept="image/*,application/pdf"
                onChange={f => setKycDocs(k => ({ ...k, proof_of_income: f }))} />
              <FileUpload label="Driving License" file={kycDocs.driving_license} accept="image/*,application/pdf"
                onChange={f => setKycDocs(k => ({ ...k, driving_license: f }))} />
            </div>
            <div className="flex justify-end pt-2 border-t mt-3">
              <button type="button"
                disabled={kycSaving || !Object.values(kycDocs).some(Boolean) || !policyResult?.policy_id}
                onClick={async () => {
                  if (!policyResult?.policy_id) return
                  setKycSaving(true); setKycMessage(null)
                  try {
                    const fd = new FormData()
                    Object.entries(kycDocs).forEach(([k, f]) => { if (f) fd.append(k, f as File) })
                    const { data } = await apiClient.post(`/policies/${policyResult.policy_id}/kyc-documents`, fd, {
                      headers: { 'Content-Type': 'multipart/form-data' },
                    })
                    setKycMessage({ type: 'ok', text: data?.message || 'KYC documents saved.' })
                    // Clear picked files so the operator sees they've been uploaded
                    setKycDocs({ driving_license: null, omang_doc: null, proof_of_residence: null, proof_of_income: null, passport_doc: null })
                  } catch (e: any) {
                    setKycMessage({ type: 'err', text: e?.response?.data?.error || e?.response?.data?.message || 'Save failed.' })
                  }
                  setKycSaving(false)
                }}
                className="px-5 py-2 text-sm font-medium bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                {kycSaving ? 'Uploading...' : 'Save KYC Documents'}
              </button>
            </div>
            {kycMessage && (
              <p className={`text-sm mt-2 ${kycMessage.type === 'ok' ? 'text-green-600' : 'text-red-600'}`}>{kycMessage.text}</p>
            )}
          </Section>
          */}

          {/* Action buttons */}
          <div className="flex flex-wrap gap-3 pt-2 border-t">
            <button onClick={handleFinish}
              className="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
              View Policy Details →
            </button>
          </div>
        </div>
      </AccordionPanel>
      </fieldset>
    </div>
  )
}

/** A single Excel import row: download template + file picker + import button */
function ExcelImportRow({
  label, description, file, status, message, onDownload, onFileChange, onImport,
}: {
  label: string
  description: string
  type: ExcelImportType
  file: File | null
  status: 'idle' | 'importing' | 'downloading' | 'success' | 'error'
  message: string
  onDownload: () => void
  onFileChange: (f: File | null) => void
  onImport: () => void
}) {
  return (
    <div className="border rounded-lg p-4 bg-gray-50">
      <div className="flex items-start justify-between gap-4 flex-wrap">
        <div className="flex-1 min-w-0">
          <p className="font-medium text-gray-800 text-sm">{label}</p>
          <p className="text-xs text-gray-500 mt-0.5">{description}</p>
        </div>
        <button
          type="button"
          onClick={onDownload}
          disabled={status === 'downloading'}
          className="shrink-0 px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded hover:bg-blue-100 disabled:opacity-50 whitespace-nowrap"
        >
          {status === 'downloading' ? 'Downloading…' : '⬇ Download Template'}
        </button>
      </div>

      <div className="mt-3 flex items-center gap-2 flex-wrap">
        <label className="flex-1 min-w-0">
          <input
            type="file"
            accept=".xlsx,.xls"
            className="block w-full text-xs text-gray-600 border border-gray-200 rounded cursor-pointer bg-white
              file:mr-3 file:py-1.5 file:px-3 file:rounded-l file:border-0 file:text-xs file:font-medium
              file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200"
            onChange={e => onFileChange(e.target.files?.[0] ?? null)}
          />
        </label>
        <button
          type="button"
          onClick={onImport}
          disabled={!file || status === 'importing'}
          className="shrink-0 px-3 py-1.5 text-xs font-medium text-white bg-green-600 rounded hover:bg-green-700 disabled:opacity-40 whitespace-nowrap"
        >
          {status === 'importing' ? 'Importing…' : '⬆ Import from Excel'}
        </button>
      </div>

      {status === 'success' && message && (
        <p className="mt-2 text-xs text-green-700 bg-green-50 border border-green-200 rounded px-3 py-1.5">
          ✓ {message}
        </p>
      )}
      {status === 'error' && message && (
        <pre className="mt-2 text-xs text-red-700 bg-red-50 border border-red-200 rounded px-3 py-1.5 whitespace-pre-wrap break-words">
          {message}
        </pre>
      )}
    </div>
  )
}

/** A collapsible accordion panel */
function AccordionPanel({
  id, title, badge, locked, lockedReason, openSections, onToggle, children,
}: {
  id: string
  title: string
  badge?: string
  locked?: boolean
  lockedReason?: string
  openSections: Set<string>
  onToggle: (key: string) => void
  children: ReactNode
}) {
  const isOpen = openSections.has(id)
  return (
    <div className={`bg-white rounded-lg shadow-sm border mb-4 overflow-hidden ${locked ? 'opacity-60' : ''}`}>
      <button
        type="button"
        disabled={locked}
        onClick={() => !locked && onToggle(id)}
        className={`w-full flex items-center justify-between px-6 py-4 text-left transition-colors
          ${locked ? 'cursor-not-allowed' : 'cursor-pointer hover:bg-gray-50'}`}
      >
        <div className="flex items-center gap-3">
          <span className="font-semibold text-gray-800">{title}</span>
          {badge && <span className="px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-700 font-medium">{badge}</span>}
          {locked && <span className="text-xs text-amber-600 font-medium">— {lockedReason ?? 'save Section 1 first'}</span>}
        </div>
        {!locked && (
          <span className="text-gray-400 text-sm select-none">{isOpen ? '▲' : '▼'}</span>
        )}
      </button>
      {isOpen && !locked && (
        <div className="px-6 pb-6 border-t border-gray-100">
          {children}
        </div>
      )}
    </div>
  )
}
