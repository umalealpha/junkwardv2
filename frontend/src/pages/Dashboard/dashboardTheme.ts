/**
 * Shared visual metadata for the Sales + Finance dashboards, so the two screens
 * stay in lockstep: identical product-tile icons + gradient palette, and a
 * single Recharts colour/axis/grid/tooltip theme that is legible in BOTH light
 * and dark mode.
 *
 * Brand: navy #010066, orange #FE7F0C. Product palette standardised as:
 *   Commercial → brand navy/blue, Domestic → emerald, Instant → brand orange.
 * (Finance previously used blue/emerald/purple + emoji icons; now unified.)
 */

import type { CSSProperties } from 'react'

export type ProductKey = 'commercial' | 'domestic' | 'instant'

export interface ProductTileMeta {
  label: string
  /** Tailwind gradient `from-…`/`to-…` classes for the tile header. */
  gradient: string
  /** Heroicons-style outline path (single `d`). */
  icon: string
}

export const PRODUCT_TILE_META: Record<ProductKey, ProductTileMeta> = {
  commercial: {
    label: 'Commercial',
    gradient: 'from-brand-navy to-brand-navy-light',
    // office building
    icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
  },
  domestic: {
    label: 'Domestic',
    gradient: 'from-emerald-600 to-emerald-800',
    // house
    icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1',
  },
  instant: {
    label: 'Instant',
    gradient: 'from-brand-orange to-brand-orange-light',
    // lightning bolt
    icon: 'M13 10V3L4 14h7v7l9-11h-7z',
  },
}

// ─── Recharts theming ─────────────────────────────────────────────────────
// Recharts needs concrete colour strings (it can't consume Tailwind classes),
// so we branch on the resolved `isDark` from useTheme() and return hex/rgba
// values that keep grid lines, axes and tooltips legible on each canvas.

export interface ChartTheme {
  grid: string
  axis: string
  tooltip: CSSProperties
  tooltipLabel: string
  cursor: string
}

export function chartTheme(isDark: boolean): ChartTheme {
  if (isDark) {
    return {
      grid: 'rgba(255,255,255,0.10)',
      axis: '#A6A6C2', // --text-muted (dark)
      tooltip: {
        borderRadius: '8px',
        border: '1px solid rgb(44 44 86)', // --border (dark)
        backgroundColor: 'rgb(20 20 46)',  // --surface (dark)
        fontSize: '13px',
      },
      tooltipLabel: '#ECECF5', // --text (dark)
      cursor: 'rgba(255,255,255,0.06)',
    }
  }
  return {
    grid: 'rgba(0,0,0,0.08)',
    axis: '#666666', // --text-muted (light)
    tooltip: {
      borderRadius: '8px',
      border: '1px solid rgb(228 230 242)', // --border (light)
      backgroundColor: '#ffffff',           // --surface (light)
      fontSize: '13px',
    },
    tooltipLabel: '#202020', // --text (light)
    cursor: 'rgba(0,0,0,0.04)',
  }
}

// Series colours: brand + semantic status, chosen to stay legible on both
// canvases (mid-tone saturated values read on white and on navy alike).
export const seriesColors = {
  total: '#010066',     // brand navy — total sales
  active: '#16a34a',    // success green
  cancelled: '#dc2f2b', // danger red
  premium: '#FE7F0C',   // brand orange — premium area
}
