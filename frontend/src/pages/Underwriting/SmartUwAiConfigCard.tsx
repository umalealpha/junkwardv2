/**
 * Smart UW — AI provider configuration, in the app.
 *
 * The extractor reads its commercial provider key from the Credentials Vault,
 * whose only UI lives in the V1 admin panel — and BlockV1AdminPanel 404s that
 * panel unless V1_ADMIN_PANEL_ENABLED is on, which it is not on the deployed
 * envs. The key is also wired into ECS from SSM for the sidecar container
 * ONLY, so anyone without AWS access had no way to fix "No Gemini API key"
 * (test env, 2026-09-02). This card is that missing screen.
 *
 * It never displays a key. The API returns only whether one is set, so a key
 * cannot be read back out of the app once saved.
 *
 * Renders nothing for a user without the admin role — the endpoint 403s and we
 * stay silent rather than showing a control that cannot work.
 */
import { useEffect, useState } from 'react'
import apiClient from '../../api/client'

interface AiConfig {
  provider: 'gemini' | 'deepseek'
  gemini_configured: boolean
  deepseek_configured: boolean
  gemini_in_vault: boolean
  deepseek_in_vault: boolean
  gemini_from_env: boolean
  deepseek_from_env: boolean
  prefer_php: boolean
  prefer_php_set: boolean
  engine_url: string
  // Keys the KYC document reader already runs on. The extractor falls back to
  // these, so "no Smart UW key" is not the same as "extraction will fail".
  shared_gemini: boolean
  shared_anthropic: boolean
  shared_groq: boolean
}

export default function SmartUwAiConfigCard() {
  const [cfg, setCfg] = useState<AiConfig | null>(null)
  const [allowed, setAllowed] = useState(true)
  const [open, setOpen] = useState(false)
  const [provider, setProvider] = useState<'gemini' | 'deepseek'>('deepseek')
  const [apiKey, setApiKey] = useState('')
  const [preferPhp, setPreferPhp] = useState(true)
  const [saving, setSaving] = useState(false)
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null)

  const load = async () => {
    try {
      const r = await apiClient.get('/underwriting/smart-upload/ai-config')
      const d: AiConfig = r.data
      setCfg(d)
      setProvider(d.provider)
      // Never chosen -> show the RECOMMENDED state (on), not the env default
      // of off. Otherwise a first-time setup saves a key with the sidecar
      // still in charge, and the sidecar cannot read an ordinary schedule.
      setPreferPhp(d.prefer_php_set ? d.prefer_php : true)
      // Open on arrival when there is nothing configured — that is the state
      // that blocks every upload, so it should not be behind a click. A key
      // shared with KYC document reading counts as configured: extraction
      // works, and nagging for a second copy of the same key is noise.
      const configured = d.provider === 'deepseek' ? d.deepseek_configured : d.gemini_configured
      const shared = d.shared_gemini || d.shared_anthropic || d.shared_groq
      if (!configured && !shared) setOpen(true)
    } catch (err: any) {
      if (err?.response?.status === 403) setAllowed(false)
    }
  }

  useEffect(() => { load() }, [])

  if (!allowed || !cfg) return null

  const configured = provider === 'deepseek' ? cfg.deepseek_configured : cfg.gemini_configured
  const inVault = provider === 'deepseek' ? cfg.deepseek_in_vault : cfg.gemini_in_vault
  const fromEnv = provider === 'deepseek' ? cfg.deepseek_from_env : cfg.gemini_from_env
  // Fallback the extractor uses when Smart UW has no key of its own: the same
  // credential KYC PDF reading uses (Gemini for the Gemini provider, otherwise
  // Anthropic, which is what OcrExtractor sends PDFs to).
  // Order mirrors PhpScheduleExtractor::complete() exactly: Anthropic, then
  // Gemini, then Groq. Gemini used to be offered only when it was ALSO the
  // selected provider, so with DeepSeek selected and only a Gemini key present
  // the card said "extraction will fail" while the extractor read the schedule
  // perfectly well on that key.
  const sharedKey = provider === 'gemini' && cfg.shared_gemini
    ? 'gemini'
    : cfg.shared_anthropic ? 'Anthropic'
    : cfg.shared_gemini ? 'Gemini'
    // Groq reads text segments only — a scanned PDF still needs Gemini or
    // Anthropic, so the label says so rather than implying full cover.
    : cfg.shared_groq ? 'Groq (text schedules only)' : null

  const save = async () => {
    setSaving(true)
    setMsg(null)
    try {
      const r = await apiClient.post('/underwriting/smart-upload/ai-config', {
        provider,
        api_key: apiKey.trim() || undefined,
        prefer_php: preferPhp,
      })
      setApiKey('')
      setMsg({ ok: true, text: r.data?.message || 'Saved.' })
      await load()
    } catch (err: any) {
      const d = err?.response?.data
      const first = d?.errors ? (Object.values(d.errors)[0] as any) : null
      setMsg({
        ok: false,
        text: (Array.isArray(first) ? first[0] : first) || d?.message || 'Could not save.',
      })
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="rounded-lg border border-line bg-surface p-4 space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <div className="text-sm font-semibold text-ink">AI extraction engine</div>
          <div className="text-xs mt-0.5">
            {configured
              ? <span className="text-green-700">
                  {provider} key configured
                  {inVault ? ' (saved here)' : fromEnv ? ' (from an environment variable)' : ''}
                </span>
              : sharedKey
                ? <span className="text-amber-700">
                    No {provider} key — schedules will be read by {sharedKey}, using the
                    key already configured for KYC document reading. Extraction works, but
                    {' '}{provider} is NOT the engine doing the mapping until its key is set.
                  </span>
                : <span className="text-red-700">
                    No {provider} key — extraction will fail until one is set
                  </span>}
          </div>
        </div>
        <button
          type="button"
          onClick={() => setOpen((o) => !o)}
          className="px-3 py-1.5 text-sm border border-line text-ink-muted rounded-md
            hover:border-ink-faint transition"
        >
          {open ? 'Close' : configured || sharedKey ? 'Change' : 'Set up'}
        </button>
      </div>

      {open && (
        <div className="space-y-3 pt-1">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
            <label className="block">
              <span className="text-xs font-medium text-ink-muted">Provider</span>
              <select
                value={provider}
                disabled={saving}
                onChange={(e) => setProvider(e.target.value as 'gemini' | 'deepseek')}
                className="mt-1 w-full px-3 py-2 border border-line rounded-md text-sm
                  focus:outline-none focus:ring-1 focus:ring-orange-400 disabled:opacity-50"
              >
                <option value="deepseek">DeepSeek (schedule mapping)</option>
                <option value="gemini">Gemini</option>
              </select>
              <span className="block text-xs text-ink-muted mt-1">
                {/* DeepSeek reads the client's schedule and maps it into our
                    format. It has no file input, so a scan or a photograph is
                    still read by Gemini or by the Anthropic key KYC document
                    reading uses — that routing is automatic. */}
                DeepSeek maps the client's schedule into our format (Coverage, Extension,
                Miscellaneous Item, Excess). A scanned or photographed schedule is read by
                Gemini or Anthropic automatically — DeepSeek takes no file input.
              </span>
            </label>
            <label className="block md:col-span-2">
              <span className="text-xs font-medium text-ink-muted">
                API key {configured && <span className="text-ink-faint">(leave blank to keep the current one)</span>}
              </span>
              <input
                type="password"
                value={apiKey}
                disabled={saving}
                autoComplete="new-password"
                onChange={(e) => setApiKey(e.target.value)}
                placeholder={configured ? '••••••••••••' : 'Paste the provider key'}
                className="mt-1 w-full px-3 py-2 border border-line rounded-md text-sm
                  focus:outline-none focus:ring-1 focus:ring-orange-400 disabled:opacity-50"
              />
            </label>
          </div>

          <label className="flex items-start gap-2 text-sm text-ink">
            <input
              type="checkbox"
              checked={preferPhp}
              disabled={saving}
              onChange={(e) => setPreferPhp(e.target.checked)}
              className="mt-1"
            />
            <span>
              Read schedules in-process (recommended)
              <span className="block text-xs text-ink-muted">
                {/* This is not a performance setting. The Python sidecar
                    hard-routes any segment carrying a "Contact Person" or
                    "Contact Number" block to a LOCAL model that is not
                    deployed, so an ordinary broker schedule fails there. The
                    in-process reader drops those identifier lines and sends
                    the rest, which is the anonymisation the DPA standard asks
                    for. */}
                The sidecar sends any schedule with a contact block to a local model that is
                not deployed, so it fails on ordinary broker schedules. The in-process reader
                removes the identifier lines and reads the rest.
                {cfg.engine_url
                  ? ` A sidecar is configured at ${cfg.engine_url}, so leave this on.`
                  : ' No sidecar is configured, so this is the only reader available.'}
              </span>
            </span>
          </label>

          {fromEnv && (
            <div className="text-xs text-amber-800 bg-amber-50 rounded-md p-2">
              An environment variable also supplies a {provider} key on this server. The key
              saved here takes priority for Smart UW; the environment one is only used if
              this is left empty.
            </div>
          )}

          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={save}
              disabled={saving || (!configured && !sharedKey && apiKey.trim() === '')}
              className="px-4 py-2 text-sm bg-primary text-primary-contrast rounded-md disabled:opacity-40"
            >
              {saving ? 'Saving…' : 'Save'}
            </button>
            <span className="text-xs text-ink-faint">
              The key is stored encrypted in the Credentials Vault and is never shown again.
            </span>
          </div>

          {msg && (
            <div className={`text-xs rounded-md p-2 ${msg.ok ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'}`}>
              {msg.text}
            </div>
          )}
        </div>
      )}
    </div>
  )
}
