<?php

namespace AlphaDirect\Services\Claims;

/**
 * Renders a ClaimReportAssembler payload to a self-contained, inline-styled
 * HTML email (the pattern used by ClaimFormEmailListener — no Blade view, so it
 * survives the direct-Mailgun send path).
 *
 * Brand colours are the org-canonical values: navy #010066 (structure/data),
 * orange #FE7F0C (accent, sparingly). bgcolor attributes are set alongside CSS
 * background-color so Outlook keeps the header/footer fills (per the AD Outlook
 * paste lesson). Amounts are rendered in BWP.
 */
class ClaimReportRenderer
{
    private const NAVY   = '#010066';
    private const ORANGE = '#FE7F0C';
    private const INK    = '#202020';
    private const GREY   = '#666666';
    private const LINE   = '#e5e7eb';
    private const SOFT   = '#f8f8f8';

    public function subject(array $report): string
    {
        return 'Alpha Direct Claims — ' . ($report['reportTypeLabel'] ?? 'Report')
            . ' (' . ($report['period']['label'] ?? '') . ')';
    }

    public function render(array $report): string
    {
        $isDigest = ($report['reportType'] ?? '') === 'pending_digest';

        $header = $this->header($report);
        $body   = $isDigest
            ? $this->pendingSection($report) . $this->kpiCards($report)
            : $this->kpiCards($report)
                . $this->twoCol($this->byTypeTable($report), $this->byStatusTable($report))
                . $this->topClaimsTable($report)
                . $this->pendingSection($report);
        $footer = $this->footer($report);

        return '<!DOCTYPE html><html><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0"></head>'
            . '<body style="margin:0;padding:0;background-color:' . self::SOFT . ';">'
            . '<div style="max-width:720px;margin:0 auto;padding:16px;'
            . 'font-family:Inter,Arial,Helvetica,sans-serif;color:' . self::INK . ';">'
            . $header . $body . $footer
            . '</div></body></html>';
    }

    private function header(array $report): string
    {
        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" '
            . 'bgcolor="' . self::NAVY . '" style="background-color:' . self::NAVY . ';border-radius:8px 8px 0 0;">'
            . '<tr><td style="padding:22px 24px;">'
            . '<div style="font-size:12px;letter-spacing:2px;text-transform:uppercase;color:' . self::ORANGE . ';font-weight:700;">Alpha Direct Insurance</div>'
            . '<div style="font-size:22px;font-weight:800;color:#ffffff;margin-top:4px;font-family:Montserrat,Inter,Arial,sans-serif;">'
            . $this->e($report['reportTypeLabel'] ?? 'Claims Report') . '</div>'
            . '<div style="font-size:13px;color:#c9c9e6;margin-top:6px;">Reporting period: '
            . $this->e($report['period']['label'] ?? '') . '</div>'
            . '</td></tr></table>';
    }

    private function kpiCards(array $report): string
    {
        $k = $report['kpis'] ?? [];
        $cards = [
            ['New claims',       $this->num($k['new'] ?? null)],
            ['Completed',        $this->num($k['completed'] ?? null)],
            ['Open',             $this->num($k['open'] ?? null)],
            ['SLA breaches',     $this->numOrNa($k['breaches'] ?? null)],
            ['Total reserve',    $this->money($k['totalReserve'] ?? null)],
            ['Total paid',       $this->money($k['totalPaid'] ?? null)],
            ['Paid in period',   $this->money($k['paidInPeriod'] ?? null)],
            ['Major claims',     $this->numOrNa($k['major'] ?? null)],
            ['FAC claims',       $this->numOrNa($k['fac'] ?? null)],
        ];

        $cells = '';
        foreach ($cards as $i => [$label, $value]) {
            if ($i % 3 === 0) {
                $cells .= ($i === 0 ? '' : '</tr>') . '<tr>';
            }
            $cells .= '<td width="33%" style="padding:6px;" valign="top">'
                . '<div style="border:1px solid ' . self::LINE . ';border-radius:8px;padding:14px 12px;background:#ffffff;">'
                . '<div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:' . self::GREY . ';">' . $this->e($label) . '</div>'
                . '<div style="font-size:20px;font-weight:800;color:' . self::NAVY . ';margin-top:6px;">' . $value . '</div>'
                . '</div></td>';
        }
        $cells .= '</tr>';

        return $this->sectionTitle('Key metrics')
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $cells . '</table>';
    }

    private function byTypeTable(array $report): string
    {
        $rows = '';
        foreach (array_slice($report['byType'] ?? [], 0, 12) as $r) {
            $rows .= '<tr><td style="' . $this->td() . '">' . $this->e($r['claimType']) . '</td>'
                . '<td style="' . $this->td() . 'text-align:right;font-weight:700;">' . $this->num($r['count']) . '</td></tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td style="' . $this->td() . '" colspan="2">No data</td></tr>';
        }
        return $this->sectionTitle('Claims by type')
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid ' . self::LINE . ';border-radius:8px;overflow:hidden;">'
            . '<tr>' . $this->th('Type') . $this->th('Count', true) . '</tr>' . $rows . '</table>';
    }

    private function byStatusTable(array $report): string
    {
        $rows = '';
        foreach ($report['byStatus'] ?? [] as $r) {
            $rows .= '<tr><td style="' . $this->td() . '">' . $this->e($r['status']) . '</td>'
                . '<td style="' . $this->td() . 'text-align:right;font-weight:700;">' . $this->num($r['count']) . '</td></tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td style="' . $this->td() . '" colspan="2">No data</td></tr>';
        }
        return $this->sectionTitle('Claims by status')
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid ' . self::LINE . ';border-radius:8px;overflow:hidden;">'
            . '<tr>' . $this->th('Status') . $this->th('Count', true) . '</tr>' . $rows . '</table>';
    }

    private function topClaimsTable(array $report): string
    {
        $rows = '';
        foreach ($report['topClaims'] ?? [] as $r) {
            $rows .= '<tr>'
                . '<td style="' . $this->td() . '">' . $this->e($r['claimNumber'] ?? ('#' . $r['claimId'])) . '</td>'
                . '<td style="' . $this->td() . '">' . $this->e($r['claimType']) . '</td>'
                . '<td style="' . $this->td() . '">' . $this->e($r['status']) . '</td>'
                . '<td style="' . $this->td() . 'text-align:right;font-weight:700;color:' . self::NAVY . ';">' . $this->money($r['totalReserve']) . '</td>'
                . '</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td style="' . $this->td() . '" colspan="4">No data</td></tr>';
        }
        return $this->sectionTitle('Top 10 claims by reserve')
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid ' . self::LINE . ';border-radius:8px;overflow:hidden;">'
            . '<tr>' . $this->th('Claim') . $this->th('Type') . $this->th('Status') . $this->th('Reserve', true) . '</tr>'
            . $rows . '</table>';
    }

    private function pendingSection(array $report): string
    {
        $p = $report['pending'] ?? ['total' => 0, 'shown' => 0, 'rows' => []];
        $rows = '';
        foreach ($p['rows'] ?? [] as $r) {
            $rows .= '<tr>'
                . '<td style="' . $this->td() . '">' . $this->e($r['claimNumber'] ?? ('#' . $r['claimId'])) . '</td>'
                . '<td style="' . $this->td() . '">' . $this->e($r['claimType']) . '</td>'
                . '<td style="' . $this->td() . '">' . $this->e($r['status']) . '</td>'
                . '<td style="' . $this->td() . 'text-align:right;">' . $this->num($r['ageDays']) . '</td>'
                . '<td style="' . $this->td() . 'text-align:right;font-weight:700;">' . $this->money($r['totalReserve']) . '</td>'
                . '</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td style="' . $this->td() . '" colspan="5">No pending claims</td></tr>';
        }
        $caption = 'Showing ' . (int) ($p['shown'] ?? 0) . ' of ' . (int) ($p['total'] ?? 0) . ' pending claim(s).';
        return $this->sectionTitle('Pending claims digest')
            . '<div style="font-size:12px;color:' . self::GREY . ';margin:0 0 8px;">' . $this->e($caption) . '</div>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid ' . self::LINE . ';border-radius:8px;overflow:hidden;">'
            . '<tr>' . $this->th('Claim') . $this->th('Type') . $this->th('Status') . $this->th('Age (d)', true) . $this->th('Reserve', true) . '</tr>'
            . $rows . '</table>';
    }

    private function footer(array $report): string
    {
        $caveats = '';
        foreach ($report['caveats'] ?? [] as $c) {
            $caveats .= '<li style="margin:2px 0;">' . $this->e($c) . '</li>';
        }
        $caveatBlock = $caveats === '' ? '' :
            '<div style="margin-top:16px;padding:12px 14px;background:' . self::SOFT . ';border:1px solid ' . self::LINE . ';border-radius:8px;">'
            . '<div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:' . self::GREY . ';font-weight:700;">Data notes</div>'
            . '<ul style="margin:8px 0 0;padding-left:18px;font-size:12px;color:' . self::GREY . ';">' . $caveats . '</ul></div>';

        return $caveatBlock
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="' . self::NAVY . '" '
            . 'style="background-color:' . self::NAVY . ';border-radius:0 0 8px 8px;margin-top:16px;">'
            . '<tr><td style="padding:14px 24px;font-size:11px;color:#c9c9e6;">'
            . 'Generated ' . $this->e($report['generatedAt'] ?? '') . ' · Alpha Direct Insurance Company (Pty) Ltd. '
            . 'Automated claims report — figures are indicative, subject to reconciliation.'
            . '</td></tr></table>';
    }

    // ── small helpers ────────────────────────────────────────────────────

    private function sectionTitle(string $t): string
    {
        return '<div style="font-size:13px;font-weight:800;color:' . self::NAVY . ';margin:22px 0 10px;'
            . 'font-family:Montserrat,Inter,Arial,sans-serif;">' . $this->e($t) . '</div>';
    }

    private function twoCol(string $left, string $right): string
    {
        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
            . '<td width="50%" valign="top" style="padding-right:8px;">' . $left . '</td>'
            . '<td width="50%" valign="top" style="padding-left:8px;">' . $right . '</td>'
            . '</tr></table>';
    }

    private function th(string $t, bool $right = false): string
    {
        return '<th style="text-align:' . ($right ? 'right' : 'left') . ';font-size:11px;text-transform:uppercase;'
            . 'letter-spacing:1px;color:#ffffff;background-color:' . self::NAVY . ';padding:8px 10px;font-weight:700;">'
            . $this->e($t) . '</th>';
    }

    private function td(): string
    {
        return 'padding:8px 10px;border-top:1px solid ' . self::LINE . ';font-size:13px;color:' . self::INK . ';';
    }

    private function e($v): string
    {
        return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
    }

    private function num($v): string
    {
        if ($v === null) {
            return '—';
        }
        return number_format((float) $v, 0);
    }

    private function numOrNa($v): string
    {
        return $v === null ? 'N/A' : $this->num($v);
    }

    private function money($v): string
    {
        if ($v === null) {
            return '—';
        }
        return 'BWP ' . number_format((float) $v, 2);
    }
}
