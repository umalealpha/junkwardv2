<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<title>Track your claim — Alpha Direct</title>
{{--
  Claimant self-service claim-status page (Claims Tracker -> Graphite Phase 2).
  Self-contained, mobile-first, Alpha Direct brand (navy #010066 / orange #FE7F0C).
  Renders NO claim data server-side — all data comes from the OTP-gated
  /api/public/v1/claim-tracking/* API after the claimant verifies ownership.
--}}
<style>
  :root{
    --navy:#010066; --orange:#FE7F0C; --ink:#202020; --white:#fff;
    --grey:#666; --line:#e4e7ec; --bg:#f4f5f9; --ok:#00B894; --bad:#d64545;
    --radius:12px;
  }
  *{box-sizing:border-box}
  html,body{margin:0;padding:0}
  body{
    font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;
    color:var(--ink); background:var(--bg); line-height:1.5;
    -webkit-font-smoothing:antialiased;
  }
  .topbar{
    background:var(--navy); color:var(--white);
    padding:16px 18px; display:flex; align-items:center; gap:10px;
  }
  .brand{font-weight:800; font-size:18px; letter-spacing:.3px}
  .brand .o{color:var(--orange)}
  .wrap{max-width:520px; margin:0 auto; padding:18px 16px 48px}
  h1{font-size:20px; margin:6px 0 4px; color:var(--navy)}
  .sub{color:var(--grey); font-size:14px; margin:0 0 18px}
  .card{
    background:var(--white); border:1px solid var(--line);
    border-radius:var(--radius); padding:20px 18px; margin-bottom:16px;
    box-shadow:0 1px 2px rgba(16,24,40,.04);
  }
  label{display:block; font-weight:600; font-size:14px; margin:0 0 6px}
  input[type=text],input[type=tel]{
    width:100%; padding:14px; font-size:16px; /* 16px avoids iOS zoom */
    border:1px solid #cfd4dc; border-radius:10px; background:#fff; color:var(--ink);
  }
  input:focus{outline:none; border-color:var(--navy); box-shadow:0 0 0 3px rgba(1,0,102,.12)}
  .otp{letter-spacing:8px; text-align:center; font-size:22px; font-weight:700}
  .btn{
    display:block; width:100%; min-height:48px; /* >=44px tap target */
    border:0; border-radius:10px; font-size:16px; font-weight:700;
    cursor:pointer; margin-top:14px; padding:12px 16px;
  }
  .btn-primary{background:var(--orange); color:var(--navy)}
  .btn-primary:active{filter:brightness(.95)}
  .btn-ghost{background:transparent; color:var(--navy); text-decoration:underline; min-height:44px; font-weight:600}
  .btn[disabled]{opacity:.55; cursor:not-allowed}
  .hint{font-size:13px; color:var(--grey); margin-top:10px}
  .msg{padding:12px 14px; border-radius:10px; font-size:14px; margin-top:14px; display:none}
  .msg.info{background:#eef2ff; color:#1e2a78; display:block}
  .msg.error{background:#fdecec; color:#8a1f1f; display:block}
  .hidden{display:none !important}
  /* status view */
  .pill{display:inline-block; padding:5px 12px; border-radius:999px; font-weight:700; font-size:13px}
  .pill.received{background:#eef2ff; color:var(--navy)}
  .pill.progress{background:#fff2e2; color:#a85a00}
  .pill.approved,.pill.completed{background:#e6f8f2; color:#00795f}
  .pill.decision,.pill.reopened{background:#f0f0f0; color:#444}
  .kv{display:flex; justify-content:space-between; gap:12px; padding:10px 0; border-bottom:1px solid var(--line); font-size:14px}
  .kv:last-child{border-bottom:0}
  .kv .k{color:var(--grey)}
  .kv .v{font-weight:600; text-align:right}
  .next{background:#f7f9ff; border:1px solid #e3e8ff; border-radius:10px; padding:12px 14px; font-size:14px; margin-top:8px}
  .tl{list-style:none; margin:14px 0 0; padding:0}
  .tl li{position:relative; padding:0 0 18px 28px}
  .tl li:before{
    content:''; position:absolute; left:6px; top:2px; width:12px; height:12px;
    border-radius:50%; background:#cfd4dc; border:2px solid #fff; box-shadow:0 0 0 1px #cfd4dc;
  }
  .tl li.done:before{background:var(--ok); box-shadow:0 0 0 1px var(--ok)}
  .tl li:after{content:''; position:absolute; left:11px; top:14px; bottom:0; width:2px; background:var(--line)}
  .tl li:last-child:after{display:none}
  .tl .lbl{font-weight:600; font-size:14px}
  .tl .dt{font-size:12px; color:var(--grey)}
  .foot{text-align:center; color:#9aa0ac; font-size:12px; margin-top:22px}
  .spin{display:inline-block; width:16px; height:16px; border:2px solid rgba(1,0,102,.25); border-top-color:var(--navy); border-radius:50%; animation:sp .7s linear infinite; vertical-align:-3px; margin-right:8px}
  @keyframes sp{to{transform:rotate(360deg)}}
</style>
</head>
<body>
  <div class="topbar">
    <span class="brand">Alpha<span class="o">Direct</span></span>
  </div>

  <div class="wrap">
    <h1>Track your claim</h1>
    <p class="sub">Check the status of your claim securely. We will send a one-time code to the phone number or email we have on file.</p>

    {{-- Step 1: enter reference (or, if a link token is present, just send) --}}
    <div class="card" id="step-request">
      <div id="ref-field">
        <label for="reference">Your claim reference</label>
        <input type="text" id="reference" inputmode="text" autocomplete="off" placeholder="e.g. CLM-000123" />
      </div>
      <div id="link-note" class="hint hidden">We recognise your claim link. Tap below to send your verification code.</div>
      <button class="btn btn-primary" id="btn-send">Send my code</button>
      <div class="msg" id="msg-request"></div>
    </div>

    {{-- Step 2: enter OTP --}}
    <div class="card hidden" id="step-verify">
      <label for="code">Enter the 6-digit code</label>
      <input type="tel" id="code" class="otp" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="••••••" />
      <button class="btn btn-primary" id="btn-verify">Verify</button>
      <button class="btn btn-ghost" id="btn-resend" type="button">Resend code</button>
      <div class="msg" id="msg-verify"></div>
    </div>

    {{-- Step 3: status --}}
    <div class="card hidden" id="step-status">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px">
        <strong id="s-ref" style="color:var(--navy)"></strong>
        <span class="pill" id="s-status"></span>
      </div>
      <div class="next" id="s-next"></div>
      <div style="margin-top:14px">
        <div class="kv"><span class="k">Claim type</span><span class="v" id="s-type"></span></div>
        <div class="kv"><span class="k">Registered on</span><span class="v" id="s-registered"></span></div>
        <div class="kv"><span class="k">Current stage</span><span class="v" id="s-stage"></span></div>
        <div class="kv"><span class="k">Last updated</span><span class="v" id="s-updated"></span></div>
      </div>
      <h1 style="font-size:15px; margin:18px 0 0">Progress</h1>
      <ul class="tl" id="s-timeline"></ul>
      <button class="btn btn-ghost" id="btn-refresh" type="button">Refresh status</button>
    </div>

    <div class="foot">Alpha Direct Insurance · Your data is protected. This page only shows your own claim.</div>
  </div>

<script>
(function(){
  "use strict";
  var TOKEN = @json($token);              // pre-issued claim link token, or null
  var API = "{{ url('/api/public/v1/claim-tracking') }}";
  var identifier = TOKEN || null;         // token if deep-linked, else the reference typed
  var sessionToken = null;

  function $(id){ return document.getElementById(id); }
  function show(el){ el.classList.remove('hidden'); }
  function hide(el){ el.classList.add('hidden'); }
  function setMsg(el, text, kind){ el.textContent = text; el.className = 'msg ' + (kind||'info'); }
  function clearMsg(el){ el.textContent=''; el.className='msg'; }
  function busy(btn, on, label){
    btn.disabled = on;
    if(on){ btn.dataset.txt = btn.textContent; btn.innerHTML = '<span class="spin"></span>' + (label||'Please wait…'); }
    else if(btn.dataset.txt){ btn.textContent = btn.dataset.txt; }
  }

  function post(path, body){
    return fetch(API + path, {
      method:'POST',
      headers:{'Content-Type':'application/json','Accept':'application/json'},
      body: JSON.stringify(body)
    }).then(function(r){ return r.json().then(function(j){ return {status:r.status, body:j}; }); });
  }

  // If a link token is present, hide the reference input.
  if (TOKEN){ hide($('ref-field')); show($('link-note')); }

  function requestOtp(){
    clearMsg($('msg-request'));
    if (!TOKEN){
      var ref = ($('reference').value || '').trim();
      if (!ref){ setMsg($('msg-request'), 'Please enter your claim reference.', 'error'); return; }
      identifier = ref;
    }
    busy($('btn-send'), true, 'Sending…');
    var payload = TOKEN ? {token: identifier} : {reference: identifier};
    post('/request-otp', payload).then(function(res){
      busy($('btn-send'), false);
      // Always generic — we never reveal whether the reference matched.
      show($('step-verify'));
      $('code').focus();
      setMsg($('msg-request'), res.body.message || 'If we found your claim, a code has been sent.', 'info');
    }).catch(function(){
      busy($('btn-send'), false);
      setMsg($('msg-request'), 'Something went wrong. Please try again.', 'error');
    });
  }

  function verifyOtp(){
    clearMsg($('msg-verify'));
    var code = ($('code').value || '').replace(/\D/g,'');
    if (code.length < 4){ setMsg($('msg-verify'), 'Enter the code we sent you.', 'error'); return; }
    busy($('btn-verify'), true, 'Verifying…');
    var payload = TOKEN ? {token: identifier, code: code} : {reference: identifier, code: code};
    post('/verify-otp', payload).then(function(res){
      busy($('btn-verify'), false);
      if (res.body && res.body.ok && res.body.token){
        sessionToken = res.body.token;
        loadStatus();
      } else {
        var err = (res.body && res.body.error) || 'invalid';
        var m = err === 'expired' ? 'That code has expired. Please request a new one.'
              : err === 'locked'  ? 'Too many attempts. Please request a new code.'
              : 'That code is not correct. Please check and try again.';
        setMsg($('msg-verify'), m, 'error');
      }
    }).catch(function(){
      busy($('btn-verify'), false);
      setMsg($('msg-verify'), 'Something went wrong. Please try again.', 'error');
    });
  }

  function loadStatus(){
    if (!sessionToken) return;
    busy($('btn-refresh'), true, 'Refreshing…');
    post('/status', {session_token: sessionToken}).then(function(res){
      busy($('btn-refresh'), false);
      if (res.body && res.body.ok && res.body.claim){
        renderStatus(res.body.claim);
        hide($('step-request')); hide($('step-verify')); show($('step-status'));
      } else {
        setMsg($('msg-verify'), 'Your session has expired. Please verify again.', 'error');
        show($('step-verify'));
      }
    }).catch(function(){
      busy($('btn-refresh'), false);
    });
  }

  function pillClass(status){
    var s = (status||'').toLowerCase();
    if (s.indexOf('receiv')>=0) return 'received';
    if (s.indexOf('approv')>=0) return 'approved';
    if (s.indexOf('complet')>=0) return 'completed';
    if (s.indexOf('decision')>=0) return 'decision';
    if (s.indexOf('reopen')>=0) return 'reopened';
    return 'progress';
  }

  function renderStatus(c){
    $('s-ref').textContent = c.reference || 'Your claim';
    var pill = $('s-status');
    pill.textContent = c.status || 'In progress';
    pill.className = 'pill ' + pillClass(c.status);
    $('s-next').textContent = c.next_step || 'Your claim is being processed.';
    $('s-type').textContent = c.type || '—';
    $('s-registered').textContent = c.registered_on || '—';
    $('s-stage').textContent = c.current_stage || '—';
    $('s-updated').textContent = c.last_updated || '—';

    var ul = $('s-timeline'); ul.innerHTML = '';
    (c.timeline || []).forEach(function(m){
      var li = document.createElement('li');
      if (m.done) li.className = 'done';
      var lbl = document.createElement('div'); lbl.className='lbl'; lbl.textContent = m.label;
      li.appendChild(lbl);
      if (m.date){ var dt = document.createElement('div'); dt.className='dt'; dt.textContent = m.date; li.appendChild(dt); }
      ul.appendChild(li);
    });
  }

  $('btn-send').addEventListener('click', requestOtp);
  $('btn-verify').addEventListener('click', verifyOtp);
  $('btn-resend').addEventListener('click', requestOtp);
  $('btn-refresh').addEventListener('click', loadStatus);
  $('reference').addEventListener('keydown', function(e){ if(e.key==='Enter') requestOtp(); });
  $('code').addEventListener('keydown', function(e){ if(e.key==='Enter') verifyOtp(); });
})();
</script>
</body>
</html>
