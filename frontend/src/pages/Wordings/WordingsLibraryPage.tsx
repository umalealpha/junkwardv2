import { useState, useMemo } from 'react'

// Policy Wordings Library — Alpha Direct (CFO directive 2026-06-16).
// PDFs served from the Graphite documents CloudFront; manifest embedded so the
// page needs no extra endpoint. Add a wording => upload to
// s3://alphadirect/Document/WordingsLibrary/ and append a row here.
type Wording = { title: string; category: string; url: string; sizeKB: number; internal: boolean }

const WORDINGS: Wording[] = [
  {
    "title": "Accidental Damage",
    "category": "Property & Assets",
    "url": "https://d20dgglp0tqnyi.cloudfront.net/Document/WordingsLibrary/accidental-damage.pdf",
    "sizeKB": 90,
    "internal": false
  },
  {
    "title": "Building Combined",
    "category": "Property & Assets",
    "url": "https://d20dgglp0tqnyi.cloudfront.net/Document/WordingsLibrary/building-combined.pdf",
    "sizeKB": 135,
    "internal": false
  },
  {
    "title": "Business Interruptions",
    "category": "Property & Assets",
    "url": "https://d20dgglp0tqnyi.cloudfront.net/Document/WordingsLibrary/business-interruptions.pdf",
    "sizeKB": 131,
    "internal": false
  },
  {
    "title": "Electronic Equipment",
    "category": "Property & Assets",
    "url": "https://d20dgglp0tqnyi.cloudfront.net/Document/WordingsLibrary/electronic-equipment.pdf",
    "sizeKB": 144,
    "internal": false
  },
  {
    "title": "Fire and Allied Perils",
    "category": "Property & Assets",
    "url": "https://d20dgglp0tqnyi.cloudfront.net/Document/WordingsLibrary/fire-and-allied-perils.pdf",
    "sizeKB": 153,
    "internal": false
  },
  {
    "title": "Glass",
    "category": "Property & Assets",
    "url": "https://d20dgglp0tqnyi.cloudfront.net/Document/WordingsLibrary/glass.pdf",
    "sizeKB": 91,
    "internal": false
  },
  {
    "title": "Home Owners",
    "category": "Property & Assets",
    "url": "https://d20dgglp0tqnyi.cloudfront.net/Document/WordingsLibrary/home-owners.pdf",
    "sizeKB": 126,
    "internal": false
  },
  {
    "title": "Household Contents",
    "category": "Property & Assets",
    "url": "https://d20dgglp0tqnyi.cloudfront.net/Document/WordingsLibrary/household-contents.pdf",
    "sizeKB": 106,
    "internal": false
  },
  {
    "title": "Office Contents",
    "category": "Property & Assets",
    "url": "https://d20dgglp0tqnyi.cloudfront.net/Document/WordingsLibrary/office-contents.pdf",
    "sizeKB": 113,
    "internal": false
  },
  {
    "title": "Accounts Received",
    "category": "Money & Crime",
    "url": "https://d20dgglp0tqnyi.cloudfront.net/Document/WordingsLibrary/accounts-received.pdf",
    "sizeKB": 85,
    "internal": false
  },
  {
    "title": "Fidelity Guarantee",
    "category": "Money & Crime",
    "url": "https://d20dgglp0tqnyi.cloudfront.net/Document/WordingsLibrary/fidelity-guarantee.pdf",
    "sizeKB": 129,
    "internal": false
  },
  {
    "title": "Money",
    "category": "Money & Crime",
    "url": "https://d20dgglp0tqnyi.cloudfront.net/Document/WordingsLibrary/money.pdf",
    "sizeKB": 134,
    "internal": false
  },
  {
    "title": "Theft D",
    "category": "Money & Crime",
    "url": "https://d20dgglp0tqnyi.cloudfront.net/Document/WordingsLibrary/theft-d.pdf",
    "sizeKB": 82,
    "internal": false
  },
  {
    "title": "Pi Wordings-Medmal",
    "category": "Liability",
    "url": "https://d20dgglp0tqnyi.cloudfront.net/Document/WordingsLibrary/pi-wordings-medmal.pdf",
    "sizeKB": 3393,
    "internal": false
  },
  {
    "title": "Professional-Indemnity-Wordings",
    "category": "Liability",
    "url": "https://d20dgglp0tqnyi.cloudfront.net/Document/WordingsLibrary/professional-indemnity-wordings.pdf",
    "sizeKB": 82,
    "internal": false
  },
  {
    "title": "Public Liability",
    "category": "Liability",
    "url": "https://d20dgglp0tqnyi.cloudfront.net/Document/WordingsLibrary/public-liability.pdf",
    "sizeKB": 152,
    "internal": false
  },
  {
    "title": "Goods in Transit",
    "category": "Marine & Transit",
    "url": "https://d20dgglp0tqnyi.cloudfront.net/Document/WordingsLibrary/goods-in-transit.pdf",
    "sizeKB": 91,
    "internal": false
  },
  {
    "title": "Marine Cargo Wording",
    "category": "Marine & Transit",
    "url": "https://d20dgglp0tqnyi.cloudfront.net/Document/WordingsLibrary/marine-cargo-wording.pdf",
    "sizeKB": 316,
    "internal": false
  },
  {
    "title": "Motor Section",
    "category": "Motor",
    "url": "https://d20dgglp0tqnyi.cloudfront.net/Document/WordingsLibrary/motor-section.pdf",
    "sizeKB": 138,
    "internal": false
  },
  {
    "title": "Motor Traders Wording (Internal)",
    "category": "Motor",
    "url": "https://d20dgglp0tqnyi.cloudfront.net/Document/WordingsLibrary/motor-traders-wording.pdf",
    "sizeKB": 116,
    "internal": true
  },
  {
    "title": "Business All Risks (2)",
    "category": "Accident & Benefits",
    "url": "https://d20dgglp0tqnyi.cloudfront.net/Document/WordingsLibrary/business-all-risks.pdf",
    "sizeKB": 95,
    "internal": false
  },
  {
    "title": "Group Personal Accident",
    "category": "Accident & Benefits",
    "url": "https://d20dgglp0tqnyi.cloudfront.net/Document/WordingsLibrary/group-personal-accident.pdf",
    "sizeKB": 116,
    "internal": false
  },
  {
    "title": "Stated Benefits D",
    "category": "Accident & Benefits",
    "url": "https://d20dgglp0tqnyi.cloudfront.net/Document/WordingsLibrary/stated-benefits-d.pdf",
    "sizeKB": 114,
    "internal": false
  },
  {
    "title": "General Exceptions, Conditions & Provisions",
    "category": "General Terms",
    "url": "https://d20dgglp0tqnyi.cloudfront.net/Document/WordingsLibrary/general-exceptions-conditions-provisions.pdf",
    "sizeKB": 149,
    "internal": false
  }
]

const DocIcon = () => (
  <svg viewBox="0 0 24 24" fill="none" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round">
    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><path d="M14 2v6h6" /><path d="M9 13h6M9 17h4" />
  </svg>
)

export default function WordingsLibraryPage() {
  const [q, setQ] = useState('')
  const [cat, setCat] = useState('All')
  const cats = useMemo(() => ['All', ...Array.from(new Set(WORDINGS.map(w => w.category)))], [])
  const rows = useMemo(() => {
    const t = q.trim().toLowerCase()
    return WORDINGS.filter(w => (cat === 'All' || w.category === cat) &&
      (!t || w.title.toLowerCase().includes(t) || w.category.toLowerCase().includes(t)))
  }, [q, cat])

  return (
    <div className="wl-root">
      <style>{`
        .wl-root{--navy:#0D1B2A;--orange:#F4A623;--orange-soft:#ffce72;--ease:cubic-bezier(.16,1,.3,1)}
        .wl-hero{border-radius:20px;padding:34px 30px;color:#eaf0f7;position:relative;overflow:hidden;
          background:radial-gradient(700px 320px at 12% -20%,#1c3a5e,transparent 60%),
                     radial-gradient(600px 300px at 100% 0,#13335a,transparent 55%),var(--navy)}
        .wl-kicker{display:inline-block;font-size:11px;letter-spacing:.2em;text-transform:uppercase;color:var(--orange);font-weight:700}
        .wl-h1{font-family:Georgia,'Book Antiqua',serif;font-size:clamp(1.8rem,1.2rem+2vw,2.7rem);line-height:1.05;margin:10px 0 8px;color:#fff}
        .wl-h1 span{color:var(--orange-soft)}
        .wl-sub{color:#9fb0c4;max-width:560px;font-size:14.5px}
        .wl-stats{display:flex;gap:26px;margin-top:18px}
        .wl-stats b{font-family:Georgia,serif;font-size:1.5rem;color:#fff;display:block;line-height:1}
        .wl-stats span{font-size:11px;color:#9fb0c4;letter-spacing:.05em}
        .wl-search{position:relative;margin:20px 0 12px;max-width:520px}
        .wl-search input{width:100%;padding:13px 16px 13px 42px;border-radius:12px;border:1px solid #e3e8ef;font-size:14.5px;outline:none;transition:.3s var(--ease)}
        .wl-search input:focus{border-color:var(--orange);box-shadow:0 0 0 4px rgba(244,166,35,.14)}
        .wl-search svg{position:absolute;left:14px;top:50%;transform:translateY(-50%);width:17px;height:17px;stroke:#94a3b8;fill:none}
        .wl-chips{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:22px}
        .wl-chip{font-size:12.5px;font-weight:500;color:#475569;padding:7px 14px;border-radius:100px;border:1px solid #e3e8ef;background:#fff;cursor:pointer;transition:.25s var(--ease)}
        .wl-chip:hover{border-color:#cbd5e1;transform:translateY(-1px)}
        .wl-chip.on{background:linear-gradient(135deg,var(--orange),#e0930f);color:#1a1206;border-color:transparent;font-weight:600;box-shadow:0 6px 18px rgba(244,166,35,.28)}
        .wl-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:16px}
        .wl-card{position:relative;display:flex;flex-direction:column;gap:11px;padding:20px;border-radius:16px;border:1px solid #e7ebf1;background:#fff;text-decoration:none;color:inherit;cursor:pointer;
          transition:transform .45s var(--ease),border-color .35s var(--ease),box-shadow .35s var(--ease);animation:wlin .5s var(--ease) both}
        .wl-card:hover{transform:translateY(-5px);border-color:rgba(244,166,35,.5);box-shadow:0 16px 40px -16px rgba(13,27,42,.25)}
        .wl-doc{width:40px;height:40px;border-radius:10px;display:grid;place-items:center;background:linear-gradient(135deg,rgba(244,166,35,.16),rgba(244,166,35,.04));border:1px solid rgba(244,166,35,.3)}
        .wl-doc svg{width:19px;height:19px;stroke:#d4880c}
        .wl-tag{font-size:10px;letter-spacing:.12em;text-transform:uppercase;color:#d4880c;font-weight:700}
        .wl-card h3{font-family:Georgia,serif;font-weight:600;font-size:1.08rem;line-height:1.25;color:var(--navy);margin:0}
        .wl-meta{margin-top:auto;display:flex;align-items:center;justify-content:space-between;color:#64748b;font-size:12px}
        .wl-open{color:#d4880c;font-weight:600}
        .wl-badge{position:absolute;top:14px;right:14px;font-size:9.5px;font-weight:700;letter-spacing:.06em;color:#b45309;background:#fff5e6;border:1px solid #fcd9a0;padding:3px 8px;border-radius:100px}
        .wl-empty{grid-column:1/-1;text-align:center;color:#94a3b8;padding:46px 0}
        @keyframes wlin{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}
        @media (prefers-reduced-motion:reduce){.wl-card{animation:none}}
      `}</style>

      <div className="wl-hero">
        <span className="wl-kicker">Document Library</span>
        <h1 className="wl-h1">Policy Wordings, <span>one click away.</span></h1>
        <p className="wl-sub">Every Alpha Direct policy wording — searchable, categorised, always current. No more digging through email threads.</p>
        <div className="wl-stats">
          <div><b>{WORDINGS.length}</b><span>WORDINGS</span></div>
          <div><b>{cats.length - 1}</b><span>CATEGORIES</span></div>
          <div><b>2023</b><span>EDITION</span></div>
        </div>
      </div>

      <div className="wl-search">
        <svg viewBox="0 0 24 24" strokeWidth={2} strokeLinecap="round"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" /></svg>
        <input value={q} onChange={e => setQ(e.target.value)} placeholder="Search wordings — fire, marine, liability, motor…" />
      </div>
      <div className="wl-chips">
        {cats.map(c => (
          <button key={c} className={'wl-chip' + (c === cat ? ' on' : '')} onClick={() => setCat(c)}>{c}</button>
        ))}
      </div>

      <div className="wl-grid">
        {rows.map(w => (
          <a key={w.url} className="wl-card" href={w.url} target="_blank" rel="noopener noreferrer">
            {w.internal && <span className="wl-badge">INTERNAL</span>}
            <span className="wl-doc"><DocIcon /></span>
            <span className="wl-tag">{w.category}</span>
            <h3>{w.title}</h3>
            <div className="wl-meta"><span>PDF · {w.sizeKB} KB</span><span className="wl-open">Open →</span></div>
          </a>
        ))}
        {rows.length === 0 && <div className="wl-empty">No wordings match “{q}”.</div>}
      </div>
    </div>
  )
}
