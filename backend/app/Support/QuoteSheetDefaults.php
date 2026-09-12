<?php

namespace AlphaDirect\Support;

/**
 * Central defaults for every variable the v2-quote-sheet blade references.
 *
 * The legacy graphiteBWV8 PolicyController pipes ~180 named scalars/collections
 * into this blade (endorse/cancel/renew pro-rata sums, motor buckets,
 * extension rollups, action dates, etc.). In V2 we generate the PDF via an
 * async job (GenerateQuotationPdfJob) that only computes what it strictly
 * needs — so the blade crashes with "Undefined variable \$foo" the moment it
 * references one we didn't pass.
 *
 * Rather than chase each missing variable individually (we've done that 5
 * times now), this file enumerates every symbol the blade uses and returns
 * a safe zero/empty-collection default. Every caller merges this into their
 * view data BEFORE their own values — so their real computations still win,
 * but any variable they didn't populate still has a defined default.
 *
 * Update-policy: when a new variable is added to the blade, add it here too.
 * The scan-once script lives at scripts/blade_vars_audit.py (listed on stdout
 * for convenience).
 */
class QuoteSheetDefaults
{
    /**
     * Every scalar defaults to 0, every collection to collect(),
     * every string to ''. Caller overrides real values by merging AFTER.
     */
    public static function all(): array
    {
        return [
            // ── Numeric scalars (pro-rata, motor buckets, totals) ──
            'ServiceCharge8'                                    => 0,
            'SumIndexExtCalculated'                             => 0,
            'SumMotorCom'                                       => 0,
            'commMotorSumInsuredTotal'                          => 0,
            'commMotorTotal'                                    => 0,
            'commMotorTotalEndors'                              => 0,
            'commMotorTotalPre'                                 => 0,
            'diff_in_days_main'                                 => 0,
            'diff_in_days_new_coverage'                         => 0,
            'diff_in_days_old_coverage'                         => 0,
            'fidelityGurntee'                                   => 0,
            'finalMotorPersonaProrata'                          => 0,
            'finalMotorPersonalSum'                             => 0,
            'finalMotorPersonalSumCancel'                       => 0,
            'finalMotorSum'                                     => 0,
            'finalMotorSumCancel'                               => 0,
            'finalMotorSumInternal'                             => 0,
            'finalMotorSumInternalCancel'                       => 0,
            'finalMotorSumInternalProRata'                      => 0,
            'finalMotorSumPrev'                                 => 0,
            'finalMotorSumPrevPersonal'                         => 0,
            'finalMotorSumProRata'                              => 0,
            'finalMotorexternalProRata'                         => 0,
            'finalMotorexternalSum'                             => 0,
            'finalMotorexternalSumCancel'                       => 0,
            'moneyRatefactorValue'                              => 0,
            'policyCoveragesDataEndorseSum'                     => 0,
            'policyCoveragesDataSum'                            => 0,
            'premiumExcludingVAT'                               => 0,
            'prorataCoverage'                                   => 0,
            'regionVat'                                         => 14,
            'sum'                                               => 0,
            'sumIndex'                                          => 0,
            'sumIndexInsuredCalculated'                         => 0,
            'sumIndexInsuredTotal'                              => 0,
            'sumInsuredCalculated'                              => 0,
            'sumInsuredCalculatedExt'                           => 0,
            'sumInsuredCalculatedNew'                           => 0,
            'sumInsuredIndex'                                   => 0,
            'sumInsuredTotal'                                   => 0,
            'sumInsuredTotalExt'                                => 0,
            'sumInsuredTotalNew'                                => 0,
            'sumMotor'                                          => 0,
            'sumMotorInternal'                                  => 0,
            'sumMotorPersonal'                                  => 0,
            'sumSubCOverages'                                   => 0,
            'sumSubCOverages1'                                  => 0,
            'sum_insured'                                       => 0,
            'sum_insuredMotor'                                  => 0,
            'sum_insuredMotorInternal'                          => 0,
            'theftRatefactorValue'                              => 0,
            'totalCommercial'                                   => 0,
            'totalCommercialPremium'                            => 0,
            'totalCoverageCalculatedValue'                      => 0,
            'totalExtensionsOfCommericialMotor'                 => 0,
            'totalExtensionsOfCommericialMotorPremuim'          => 0,
            'totalExtensionsOfCommericialMotorPremuimInternal'  => 0,
            'totalExtensionsOfMotorPremuim'                     => 0,
            'totalExtensionsOfPersonalMotor'                    => 0,
            'totalExtensionsOfPersonalMotorSumInsured'          => 0,
            'totalExtensionsOfPersonallMotorPremuim'            => 0,
            'totalExternalExcessPremium'                        => 0,
            'totalExternalExcessSumInsured'                     => 0,
            'totalExternalExtentionPremium'                     => 0,
            'totalExternalExtentionSumInsured'                  => 0,
            'totalExternalPremium'                              => 0,
            'totalExternalSumInsured'                           => 0,
            'totalIndexSumExtCalculated'                        => 0,
            'totalIndexSumInsuredCalculated'                    => 0,
            'totalInternalExcessPremium'                        => 0,
            'totalInternalExcessSumInsured'                     => 0,
            'totalInternalExtentionPremium'                     => 0,
            'totalInternalExtentionSumInsured'                  => 0,
            'totalInternalPremium'                              => 0,
            'totalInternalSumInsured'                           => 0,
            'totalMiniAmountExcessesOfCommericialMotor'         => 0,
            'totalMiniAmountExcessesOfPersonalMotor'            => 0,
            'totalMiniPerExcessesOfCommericialMotor'            => 0,
            'totalMiniPerExcessesOfPersonalMotor'               => 0,
            'totalProRata'                                      => 0,
            'totalProRataPremium'                               => 0,
            'totalProRataPremiumVatFreq'                        => 0,
            'totalProRataPremiumVatFreqCancel'                  => 0,
            'totalSumOfCalculatedValues'                        => 0,
            'totalSumOfCoveragesValues'                         => 0,
            'totalSumOfExtentionCalculatedValues'               => 0,
            'totalSumOfExtentionCoveragesValues'                => 0,
            'totalSumOfPerilsCalculatedValues'                  => 0,
            'totalSumOfPerilsCoveragesValues'                   => 0,
            'total_SumOfCoveragesValues'                        => 0,
            'total_SumOfCoveragesValues1'                       => 0,
            'vat'                                               => 0,
            'vatServiceCharge'                                  => 0,
            'vat_month'                                         => 0,
            'vat_pro_data'                                      => 0,

            // ── Specialist product totals (specialist-product.blade.php) ──
            // Added when MM/PI/Travel quote sheets started crashing with
            // "Undefined variable $getMedicalTotal" — async PDF jobs that
            // don't pass these still get a safe 0 so the grand-total math
            // doesn't fatal.
            'getMedicalTotal'              => 0,
            'getProfessionalIndemnityTotal'=> 0,
            'getTravelTotal'               => 0,
            'getlimitIndemnity'            => 0,
            'getMachineryBreakdownTotal'   => 0,
            'getMedicalEvacuationTotal'    => 0,
            'getCommercialCrimeTotal'      => 0,
            'getEnvironmentalLiabilityTotal' => 0,
            'getBondsTotal'                => 0,
            'getMarineCargoOnceOffTotal'   => 0,
            'getMarineCargoOpenTotal'      => 0,
            'getMarineDirectorsOfficersTotal' => 0,
            'getDirectorsOfficersTotal'    => 0,

            // ── Collections (models, groups, lookups) ──
            'PolicyBusiExcessesData'         => [],
            'actionDates'                    => [],
            'all_coverages'                  => [],
            'all_sub_coverages'              => [],
            'covragesIds'                    => [],
            'entitiesData'                   => [],
            'extention_items'                => [],
            'motorData'                      => [],
            'motorDataDom'                   => [],
            'motorDataInternal'              => [],
            'motorDatas'                     => [],
            'motorTradersData'               => [],
            'pBusiExData'                    => [],
            'pData'                          => [],
            'pExData'                        => [],
            'personalMotorData'              => [],
            'policyComCover'                 => [],
            'policyComCoverDom'              => [],
            'policyCoverageDetail'           => [],
            'policyCoveragesData'            => [],
            'policyCoveragesDataSumCover'    => [],
            'policyExcessesData'             => [],
            'policyExtention'                => [],
            'policyMotorIndex'               => [],
            'policyMotorIndexInternal'       => [],
            'specifed_items'                 => [],
            'specified_items'               => [],
            'sub_coverages'                  => [],
            'tbCvgpcextentionlimits'         => [],
            'tbCvgpclimits'                  => [],
            'tradersData'                    => [],
            'uniqueCoverages'                => [],
            'premium_coverages'              => [],
            'indexSections'                  => [],
            // Per-section specialist pro-rata (charge/refund incl VAT) keyed by
            // coverage screen name. Populated by GenerateQuotationPdfJob via
            // SpecialistEndorseCalculator::proRataInclVatByScreenName; empty
            // default so non-endorse / non-specialist renders don't fatal.
            'specialistProRata'              => [],
            // Selected-transaction coverage ids — GenerateQuotationPdfJob sets
            // these so the specialist detail blades scope to one transaction
            // (no cross-transaction duplication). Empty default → blades fall
            // back to policy-wide so they never go blank on other render paths.
            'specialistPcIds'                => [],
            'specialistProRataTotalRefundInclVat'  => 0,
            'specialistProRataTotalPremiumInclVat' => 0,
            'specialistProRataFinalInclVat'        => 0,
            'specialistProRataFinalExclVat'        => 0,
            'specialistProRataFinalVat'            => 0,

            // ── String scalars (headers, labels, placeholders) ──
            'aboutAlpha'           => '',
            'currentlyInsured'     => '',
            'entitiesName'         => '',
            'exclusionText'        => '',
            'flag'                 => '',
            'flagCom'              => '',
            'format'               => 'A4',
            'header'               => '',
            'look_up_non'          => '',
            'noteMotor'            => '',
            'periodLabel'          => '',
            'propertyBusinessBeing'=> '',
            'property_business_being' => '',

            // ── Date strings (already set by caller; defaults are just empty) ──
            'annRenewEnd'         => '',
            'annRenewStart'       => '',
            'annualPeriodEnd'     => '',
            'annualPeriodStart'   => '',
            'endorENd'            => '',
            'endorStart'          => '',
            'fromDateEnd'         => '',
            'fromDateStart'       => '',
            'renewEnd'            => '',
            'renewStart'          => '',
            'today'               => '',

            // ── Booleans / flags ──
            'getSubCoverPresent'  => false,
            'getSubCoverPresent1' => false,
            'matchFound'          => false,
            'sub_coverage_found'  => false,

            // ── Misc scalars ──
            'getActionId' => null,
            'dateValue'   => null,
            'parsed'      => null,
            'previousRisk'=> null,
            'policyTerm'  => null,
        ];
    }
}
