'use strict';
/**
 * dashboardPage.js — the single-file, self-contained Alpha Brain dashboard.
 *
 * Returns one HTML document with ALL CSS and JS inlined: no CDN, no external
 * fonts, no build step, no framework (vanilla JS). CSP-safe by construction —
 * nothing is fetched off the box except this dashboard's own same-origin /api.
 *
 * Every control the page shows is ALSO enforced server-side (lib/rbac). The
 * client hides what a role can't use purely for tidiness; the server is the
 * authority and returns 403 regardless of what the page renders.
 *
 * Brand: navy #010066, orange #FE7F0C, near-black #202020; serif headings
 * (Book Antiqua / Georgia), Inter/system body. Responsive 360–2560px, 44px
 * tap targets, dark-mode aware.
 *
 * NOTE: the inner page script deliberately avoids template literals (backticks)
 * so this module can hold it inside a normal template literal without escaping.
 */

function page() {
  return `<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Alpha Brain — Internal Dashboard</title>
<style>
:root{
  --navy:#010066;--orange:#FE7F0C;--ink:#202020;--white:#fff;
  --mut:#666;--mut2:#999;--line:#e6e9ef;--bg:#f4f6fa;--card:#fff;
  --hi:#DC2626;--md:#FE7F0C;--lo:#9aa4b2;--ok:#00B894;--warn:#FFC300;
  --serif:'Book Antiqua',Georgia,'Times New Roman',serif;
  --sans:Inter,-apple-system,'Segoe UI',Roboto,system-ui,sans-serif;
}
@media (prefers-color-scheme:dark){:root{
  --ink:#e8eaf0;--mut:#9aa4b2;--mut2:#6B7280;--line:#233;--bg:#0b1020;--card:#141a2e;--white:#141a2e;
}}
*{box-sizing:border-box}
html,body{margin:0}
body{font:15px/1.5 var(--sans);color:var(--ink);background:var(--bg);-webkit-text-size-adjust:100%}
a{color:var(--navy)}
header{background:var(--navy);color:#fff;padding:14px 20px;display:flex;align-items:center;gap:14px;flex-wrap:wrap}
header .dot{width:10px;height:34px;background:var(--orange);border-radius:3px;flex:0 0 auto}
header h1{font:700 18px/1.15 var(--serif);margin:0}
header .sub{color:#aebbcf;font-size:12px;margin-top:2px}
header .who{margin-left:auto;text-align:right;font-size:12px;color:#cdd6e6}
header .who b{color:#fff}
.badge-role{display:inline-block;background:var(--orange);color:#241000;border-radius:20px;padding:2px 10px;font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:.04em}
nav{background:var(--card);border-bottom:1px solid var(--line);display:flex;gap:2px;padding:0 12px;overflow-x:auto;position:sticky;top:0;z-index:5}
nav button{background:none;border:0;border-bottom:3px solid transparent;color:var(--mut);font:600 13px/1 var(--sans);padding:0 14px;min-height:44px;cursor:pointer;white-space:nowrap}
nav button.active{color:var(--navy);border-bottom-color:var(--orange)}
@media (prefers-color-scheme:dark){nav button.active{color:#fff}}
main{max-width:1180px;margin:20px auto;padding:0 16px}
h2{font:700 20px/1.2 var(--serif);margin:0 0 4px}
.hint{color:var(--mut);font-size:12.5px;margin:0 0 16px}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:14px;margin-bottom:20px}
.stat{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:16px}
.stat .k{font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:var(--mut)}
.stat .v{font:700 30px/1.1 var(--serif);color:var(--navy);margin-top:6px}
@media (prefers-color-scheme:dark){.stat .v{color:#fff}}
.stat .v small{font:600 12px/1 var(--sans);color:var(--mut)}
.panel{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:16px;margin-bottom:18px;overflow-x:auto}
.panel h3{font:700 14px/1 var(--sans);text-transform:uppercase;letter-spacing:.05em;color:var(--mut);margin:0 0 12px}
table{width:100%;border-collapse:collapse;font-size:13.5px;min-width:640px}
th,td{text-align:left;padding:9px 10px;border-bottom:1px solid var(--line);vertical-align:top}
th{font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--mut);white-space:nowrap}
tr.uncertain{background:rgba(255,195,0,.14)}
tr.uncertain td:first-child{border-left:4px solid var(--warn)}
.tag{display:inline-block;border-radius:6px;padding:2px 8px;font-size:11px;font-weight:700;white-space:nowrap}
.tag.clean{background:rgba(0,184,148,.16);color:#046b53}
.tag.uncertain{background:var(--warn);color:#3a2a00}
.tag.stage{background:#eef;color:var(--navy)}
@media (prefers-color-scheme:dark){.tag.stage{background:#22305a;color:#cdd6e6}.tag.clean{color:#00B894}}
.pri{font:700 11px/1 monospace;color:#fff;border-radius:5px;padding:3px 6px;display:inline-block}
.pri.hi{background:var(--hi)}.pri.md{background:var(--orange);color:#3a2a00}.pri.lo{background:var(--lo)}
button.act{background:var(--navy);color:#fff;border:0;border-radius:8px;padding:0 16px;min-height:44px;cursor:pointer;font:600 13px/1 var(--sans)}
button.act:hover{background:#0a0a8c}button.act.ghost{background:transparent;color:var(--navy);border:1px solid var(--navy)}
button.act.warn{background:var(--orange);color:#241000}button.act:disabled{opacity:.5;cursor:default}
@media (prefers-color-scheme:dark){button.act.ghost{color:#cdd6e6;border-color:#33406a}}
.ok{color:var(--ok);font-weight:700;font-size:12px}
.row-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.controls{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:14px}
select,input[type=text]{font:14px var(--sans);padding:0 10px;min-height:44px;border:1px solid var(--line);border-radius:8px;background:var(--card);color:var(--ink)}
label{font-size:12px;color:var(--mut);display:block;margin:10px 0 4px}
.formgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}
.notice{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:14px}
.notice.arms{background:rgba(0,184,148,.14);color:#046b53;border:1px solid rgba(0,184,148,.4)}
.notice.sample{background:rgba(255,195,0,.14);color:#5a4600;border:1px solid rgba(255,195,0,.5)}
.wall{max-width:420px;margin:12vh auto;text-align:center;padding:28px;background:var(--card);border:1px solid var(--line);border-radius:14px}
.wall h2{color:var(--navy)}
.empty{color:var(--mut);padding:20px;text-align:center}
.tok-once{font-family:monospace;background:#111;color:#7CFC7C;padding:8px 10px;border-radius:6px;word-break:break-all;user-select:all}
footer{max-width:1180px;margin:8px auto 40px;padding:0 16px;color:var(--mut2);font-size:11px}
</style></head>
<body>
<div id="app"></div>
<script>
/* ---- state ---- */
var ME=null, HEALTH=null;
function tok(){return sessionStorage.getItem('brainTok')||'';}
function setTok(t){sessionStorage.setItem('brainTok',t);}
function esc(s){return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}
function can(p){return !!(ME&&ME.permissions&&ME.permissions.indexOf(p)>=0);}
function money(n){var x=Number(n);return isNaN(x)?esc(n):'BWP '+x.toLocaleString('en-BW',{minimumFractionDigits:2,maximumFractionDigits:2});}
function fmtDate(s){if(!s)return '—';var d=new Date(s);return isNaN(d)?esc(s):d.toISOString().replace('T',' ').slice(0,16)+'Z';}

async function api(path,opts){
  opts=opts||{};opts.headers=opts.headers||{};
  if(tok())opts.headers['authorization']='Bearer '+tok();
  if(opts.body&&typeof opts.body!=='string'){opts.headers['content-type']='application/json';opts.body=JSON.stringify(opts.body);}
  var r=await fetch(path,opts);
  if(r.status===401){sessionStorage.removeItem('brainTok');ME=null;renderAuthWall('Session expired or invalid token.');throw new Error('unauthorized');}
  var ct=r.headers.get('content-type')||'';
  var data=ct.indexOf('application/json')>=0?await r.json():await r.text();
  return {status:r.status,data:data,ok:r.ok};
}

/* ---- shell ---- */
function el(id){return document.getElementById(id);}
function renderAuthWall(msg){
  el('app').innerHTML='<div class="wall"><h2>Alpha Brain</h2>'
    +'<p class="hint">Internal dashboard — role-gated. Enter your personal access token.</p>'
    +(msg?'<p style="color:var(--hi);font-size:13px">'+esc(msg)+'</p>':'')
    +'<input id="tokin" type="text" placeholder="access token" style="width:100%;margin:8px 0">'
    +'<button class="act" style="width:100%" data-act="login">Enter</button>'
    +'<p class="hint" style="margin-top:14px">Tokens are issued by an admin. No token = no access (fail-closed).</p></div>';
  var i=el('tokin');if(i){i.focus();i.onkeydown=function(e){if(e.key==="Enter")doLogin();};}
}
function doLogin(){var v=(el('tokin')||{}).value||'';if(v.trim()){setTok(v.trim());boot();}}
function logout(){sessionStorage.removeItem('brainTok');ME=null;renderAuthWall('Signed out.');}

var TABS=[
  {id:'overview',label:'Overview',show:function(){return can('view.overview');}},
  {id:'affected',label:'Affected Policies',show:function(){return can('view.affected');}},
  {id:'approvals',label:'Approvals',show:function(){return can('view.queue')||can('view.queue.claims');}},
  {id:'activity',label:'Activity',show:function(){return can('view.activity');}},
  {id:'admin',label:'Admin',show:function(){return can('admin.users')||can('admin.settings');}}
];
var current='overview';

function shell(){
  var nav=TABS.filter(function(t){return t.show();}).map(function(t){
    return '<button class="'+(t.id===current?'active':'')+'" data-act="go" data-id="'+t.id+'">'+esc(t.label)+'</button>';
  }).join('');
  el('app').innerHTML=
    '<header><div class="dot"></div>'
    +'<div><h1>Alpha Brain — Internal Dashboard</h1>'
    +'<div class="sub">isolated monitoring sidecar · reads Graphite read-only · arms '+(HEALTH&&HEALTH.liveArms?'<b style="color:#fff">ON</b>':'OFF')+'</div></div>'
    +'<div class="who">Signed in as <b>'+esc(ME.user.name)+'</b><br><span class="badge-role">'+esc(ME.role)+'</span> '
    +'<button class="act ghost" style="min-height:32px;padding:0 10px;margin-left:6px" data-act="logout">Sign out</button></div>'
    +'</header>'
    +'<nav>'+nav+'</nav>'
    +'<main id="view"></main>'
    +'<footer>Alpha Brain · localhost-only sidecar · ARMS OFF: approvals are recorded but nothing is sent, moved, or cancelled. Customer data shown here never leaves this box.</footer>';
  renderView();
}
function go(id){current=id;shell();}

function renderView(){
  var v=el('view');v.innerHTML='<div class="empty">Loading…</div>';
  if(current==='overview')return viewOverview(v);
  if(current==='affected')return viewAffected(v);
  if(current==='approvals')return viewApprovals(v);
  if(current==='activity')return viewActivity(v);
  if(current==='admin')return viewAdmin(v);
}

/* ---- Overview ---- */
async function viewOverview(v){
  var q=await api('/api/queue').catch(function(){return {data:{}};});
  var act=can('view.activity')?await api('/api/activity?limit=8').catch(function(){return {data:{entries:[]}};}):{data:{entries:[]}};
  var counts=(q.data&&q.data.counts)||{};
  var total=(q.data&&q.data.total)||0;
  var cards=[
    ['Exceptions queued',total,''],
    ['Teams affected',Object.keys(counts).length,''],
    ['Next sweep',HEALTH?fmtDate(HEALTH.nextSweepUtc):'—',''],
    ['Last generated',HEALTH&&HEALTH.generatedAt?fmtDate(HEALTH.generatedAt):'never','']
  ];
  var html='<h2>Overview</h2><p class="hint">Live counts from the current sweep. The sweep runs 05:45 UTC (07:45 Gaborone), after the nightly debit window.</p>';
  html+='<div class="notice arms">Live arms are <b>'+(HEALTH&&HEALTH.liveArms?'ON':'OFF')+'</b>. With arms off the brain only decides and queues — it sends and executes nothing.</div>';
  html+='<div class="grid">'+cards.map(function(c){return '<div class="stat"><div class="k">'+esc(c[0])+'</div><div class="v">'+esc(c[1])+'</div></div>';}).join('')+'</div>';
  html+='<div class="panel"><h3>Queue by team</h3><table><thead><tr><th>Team</th><th>Items</th></tr></thead><tbody>';
  var teams=Object.keys(counts);
  html+= teams.length?teams.map(function(t){return '<tr><td>'+esc(t)+'</td><td>'+esc(counts[t])+'</td></tr>';}).join(''):'<tr><td colspan="2" class="empty">Queue is empty.</td></tr>';
  html+='</tbody></table></div>';
  if(can('view.activity')){
    var rows=(act.data.entries||[]).map(auditRow).join('');
    html+='<div class="panel"><h3>Recent activity</h3><table><thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Target</th></tr></thead><tbody>'+(rows||'<tr><td colspan="4" class="empty">No activity yet.</td></tr>')+'</tbody></table></div>';
  }
  v.innerHTML=html;
}
function auditRow(e){
  return '<tr><td>'+fmtDate(e.at)+'</td><td>'+esc(e.actor||e.by||'system')+(e.actorRole?' <span class="badge-role">'+esc(e.actorRole)+'</span>':'')+'</td><td>'+esc(e.action||(e.source?'sweep':'—'))+'</td><td>'+esc(e.target||e.id||e.source||'')+'</td></tr>';
}

/* ---- Affected policies ---- */
var affStage='';
async function viewAffected(v){
  var qs=affStage?'?stage='+encodeURIComponent(affStage):'';
  var r=await api('/api/affected'+qs).catch(function(){return {data:{affected:[]}};});
  var d=r.data;var rows=d.affected||[];
  var html='<h2>Exceptions — Affected Policies</h2><p class="hint">Collections / arrears candidates. Rows flagged <b>uncertain</b> need human verification before any action.</p>';
  html+='<div class="notice sample">'+esc(d.source||'sample')+' — this feed is sample data until the live affected-list is wired.</div>';
  html+='<div class="controls"><label style="margin:0">Stage</label><select data-act="stage">'
    +['','deactivate_candidate','grace','cancel_candidate'].map(function(s){return '<option value="'+s+'"'+(s===affStage?' selected':'')+'>'+(s||'all stages')+'</option>';}).join('')+'</select>';
  if(can('export'))html+='<button class="act ghost" data-act="export">Export CSV</button>';
  html+='<span class="hint" style="margin-left:auto">'+rows.length+' rows</span></div>';
  html+='<div class="panel"><table><thead><tr><th>Policy</th><th>Customer</th><th>Product</th><th>Agent / Channel</th><th>Bill</th><th>Overdue</th><th>Months</th><th>Days</th><th>Stage</th><th>Grace ends</th><th>Confidence</th><th>Reason</th></tr></thead><tbody>';
  html+= rows.length?rows.map(function(p){
    var unc=p.signalConfidence==='uncertain';
    return '<tr class="'+(unc?'uncertain':'')+'">'
      +'<td><b>'+esc(p.policyNumber)+'</b></td><td>'+esc(p.customerName)+'</td>'
      +'<td>'+esc(p.product)+'</td><td>'+esc(p.agent)+'<br><span class="hint">'+esc(p.channel)+'</span></td>'
      +'<td>'+esc(p.billingType)+'</td><td>'+money(p.amountOverdue)+'</td><td>'+esc(p.monthsUnpaid)+'</td><td>'+esc(p.daysOverdue)+'</td>'
      +'<td><span class="tag stage">'+esc(p.stage)+'</span></td><td>'+fmtDate(p.graceEndsAt)+'</td>'
      +'<td><span class="tag '+(unc?'uncertain':'clean')+'">'+esc(p.signalConfidence)+'</span></td>'
      +'<td class="hint" style="max-width:260px">'+esc(p.reason)+'</td></tr>';
  }).join(''):'<tr><td colspan="12" class="empty">No affected policies for this filter.</td></tr>';
  html+='</tbody></table></div>';
  v.innerHTML=html;
}
async function exportAffected(){
  var qs=affStage?'&stage='+encodeURIComponent(affStage):'';
  var r=await fetch('/api/affected?format=csv'+qs,{headers:{authorization:'Bearer '+tok()}});
  if(!r.ok){alert('Export refused ('+r.status+').');return;}
  var blob=await r.blob();var a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='affected-policies.csv';a.click();URL.revokeObjectURL(a.href);
}

/* ---- Approvals ---- */
async function viewApprovals(v){
  var r=await api('/api/queue').catch(function(){return {data:{teams:{}}};});
  var teams=(r.data&&r.data.teams)||{};
  var items=[];
  Object.keys(teams).forEach(function(t){(teams[t]||[]).forEach(function(it){it._team=t;items.push(it);});});
  var pending=items.filter(function(i){return i.needsApproval&&i.status!=='approved';});
  var done=items.filter(function(i){return i.status==='approved';});
  var html='<h2>Approvals</h2><p class="hint">A named human records an approval. <b>Arms are off</b> — this records the decision only; it does not execute, send, or cancel anything.</p>';
  if(!can('approve'))html+='<div class="notice sample">Your role ('+esc(ME.role)+') is view-only here. Approve is disabled.</div>';
  html+='<div class="panel"><h3>Awaiting approval ('+pending.length+')</h3><table><thead><tr><th>Priority</th><th>Team</th><th>Item</th><th>Detail</th><th></th></tr></thead><tbody>';
  html+= pending.length?pending.map(function(i){
    var p=Number(i.priority)||0;var cls=p>=85?'hi':p>=55?'md':'lo';
    var btn=can('approve')?'<button class="act" data-act="approve" data-id="'+esc(i.id)+'">Approve</button>':'<span class="hint">no permission</span>';
    return '<tr><td><span class="pri '+cls+'">'+esc(i.priority)+'</span></td><td>'+esc(i._team)+'</td><td><b>'+esc(i.title)+'</b><br><span class="hint">'+esc(i.id)+'</span></td><td class="hint">'+esc(i.detail||i.ref||'')+'</td><td>'+btn+'</td></tr>';
  }).join(''):'<tr><td colspan="5" class="empty">Nothing awaiting approval.</td></tr>';
  html+='</tbody></table></div>';
  html+='<div class="panel"><h3>Recently approved ('+done.length+')</h3><table><thead><tr><th>Team</th><th>Item</th><th>Approved by</th><th>When</th></tr></thead><tbody>';
  html+= done.length?done.map(function(i){return '<tr><td>'+esc(i._team)+'</td><td>'+esc(i.title)+'</td><td>'+esc(i.approvedBy||'')+'</td><td>'+fmtDate(i.approvedAt)+'</td></tr>';}).join(''):'<tr><td colspan="4" class="empty">None yet.</td></tr>';
  html+='</tbody></table></div>';
  v.innerHTML=html;
}
async function approve(id){
  var name=prompt('Approver name (recorded in the audit log):',ME.user.name||'');
  if(!name||!name.trim())return;
  var r=await api('/api/approve',{method:'POST',body:{id:id,approver:name.trim()}}).catch(function(e){return {ok:false,data:{error:e.message}};});
  if(r.ok){renderView();}
  else{alert('Approval refused ('+r.status+'): '+((r.data&&r.data.error)||''));}
}

/* ---- Activity ---- */
var actOffset=0;
async function viewActivity(v){
  var r=await api('/api/activity?limit=50&offset='+actOffset).catch(function(){return {data:{entries:[],total:0}};});
  var d=r.data;var rows=(d.entries||[]).map(auditRow).join('');
  var html='<h2>Activity</h2><p class="hint">Append-only audit log — every approval, user change, settings change, and sweep. Newest first. Entries can never be edited or deleted.</p>';
  html+='<div class="panel"><table><thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Target</th></tr></thead><tbody>'+(rows||'<tr><td colspan="4" class="empty">No activity yet.</td></tr>')+'</tbody></table></div>';
  html+='<div class="controls"><button class="act ghost" data-act="page" data-off="newer" '+(actOffset<=0?'disabled':'')+'>Newer</button>'
    +'<button class="act ghost" data-act="page" data-off="older" '+(actOffset+50>=(d.total||0)?'disabled':'')+'>Older</button>'
    +'<span class="hint">'+ (d.total||0) +' total</span></div>';
  v.innerHTML=html;
}

/* ---- Admin ---- */
var LAST_TOKEN=null;
async function viewAdmin(v){
  var html='<h2>Admin</h2><p class="hint">User management and settings. Admin only.</p>';
  if(LAST_TOKEN)html+='<div class="notice sample">Token for <b>'+esc(LAST_TOKEN.name)+'</b> (shown once — copy it now): <span class="tok-once">'+esc(LAST_TOKEN.token)+'</span></div>';
  if(can('admin.users')){
    var u=await api('/api/admin/users').catch(function(){return {data:{users:[]}};});
    var users=(u.data&&u.data.users)||[];
    html+='<div class="panel"><h3>Users</h3><table><thead><tr><th>Name</th><th>Role</th><th>Created</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
    html+=users.map(function(x){
      return '<tr><td><b>'+esc(x.name)+'</b><br><span class="hint">'+esc(x.id)+'</span></td>'
        +'<td>'+roleSelect(x.id,x.role)+'</td><td>'+fmtDate(x.createdAt)+'</td>'
        +'<td>'+(x.disabled?'<span class="tag uncertain">disabled</span>':'<span class="ok">active</span>')+'</td>'
        +'<td class="row-actions">'
        +'<button class="act ghost" data-act="rotate" data-id="'+esc(x.id)+'">Rotate token</button>'
        +'<button class="act ghost" data-act="toggle" data-id="'+esc(x.id)+'" data-dis="'+(x.disabled?'false':'true')+'">'+(x.disabled?'Enable':'Disable')+'</button>'
        +'<button class="act warn" data-act="del" data-id="'+esc(x.id)+'" data-name="'+esc(x.name)+'">Delete</button>'
        +'</td></tr>';
    }).join('');
    html+='</tbody></table>';
    html+='<h3 style="margin-top:18px">Create user</h3><div class="formgrid">'
      +'<div><label>Name</label><input id="nu_name" type="text" placeholder="Full name"></div>'
      +'<div><label>Role</label>'+roleSelect('nu','viewer')+'</div>'
      +'<div><label>&nbsp;</label><button class="act" data-act="create">Create user</button></div></div>'
      +'<div id="tokout"></div></div>';
  }
  if(can('admin.settings')){
    var s=await api('/api/admin/settings').catch(function(){return {data:{}};});
    var d=s.data||{};var ft=d.featureToggles||{};
    html+='<div class="panel"><h3>Settings</h3><div class="formgrid">'
      +'<div><label>Teams webhook URL</label><input id="s_webhook" type="text" value="'+esc(d.teamsWebhook||'')+'"></div>'
      +'<div><label>Report recipients (comma-separated)</label><input id="s_recip" type="text" value="'+esc((d.reportRecipients||[]).join(', '))+'"></div>'
      +'</div><div style="margin-top:10px">'
      +'<label><input type="checkbox" id="s_uncertain" '+(ft.showUncertain?'checked':'')+'> Show uncertain rows</label>'
      +'<label><input type="checkbox" id="s_daily" '+(ft.dailyEmail?'checked':'')+'> Daily email digest (arms off — no-op)</label>'
      +'</div><button class="act" style="margin-top:12px" data-act="save">Save settings</button>'
      +'<p class="hint">Webhook/recipients are stored only; nothing is sent while arms are off.</p></div>';
  }
  v.innerHTML=html;
}
function roleSelect(id,cur){
  var roles=['admin','finance','underwriting','claims','viewer'];
  var attr=id==='nu'?'':' data-act="role" data-id="'+esc(id)+'"';
  return '<select id="role_'+id+'"'+attr+'>'+roles.map(function(r){return '<option'+(r===cur?' selected':'')+'>'+r+'</option>';}).join('')+'</select>';
}
async function createUser(){
  var name=(el('nu_name')||{}).value||'';var role=(el('role_nu')||{}).value||'viewer';
  if(!name.trim()){alert('Name required.');return;}
  var r=await api('/api/admin/users',{method:'POST',body:{name:name.trim(),role:role}});
  if(r.ok){LAST_TOKEN={name:r.data.user.name,token:r.data.token};renderView();}
  else alert('Create failed: '+((r.data&&r.data.error)||r.status));
}
async function changeRole(id,role){
  var r=await api('/api/admin/users',{method:'PUT',body:{id:id,role:role}});
  if(!r.ok){alert('Update failed: '+((r.data&&r.data.error)||r.status));renderView();}
}
async function toggleDisabled(id,dis){
  var r=await api('/api/admin/users',{method:'PUT',body:{id:id,disabled:dis==='true'||dis===true}});
  if(!r.ok)alert('Update failed: '+((r.data&&r.data.error)||r.status));
  renderView();
}
async function rotate(id){
  var r=await api('/api/admin/users',{method:'PUT',body:{id:id,rotateToken:true}});
  if(r.ok&&r.data.token){el('view').insertAdjacentHTML('afterbegin','<div class="notice sample">New token (shown once): <span class="tok-once">'+esc(r.data.token)+'</span></div>');}
  else alert('Rotate failed: '+((r.data&&r.data.error)||r.status));
}
async function delUser(id,name){
  if(!confirm('Delete user "'+name+'"? This cannot be undone.'))return;
  var r=await api('/api/admin/users',{method:'DELETE',body:{id:id}});
  if(!r.ok)alert('Delete failed: '+((r.data&&r.data.error)||r.status));
  renderView();
}
async function saveSettings(){
  var body={teamsWebhook:(el('s_webhook')||{}).value||'',reportRecipients:(el('s_recip')||{}).value||'',featureToggles:{showUncertain:!!(el('s_uncertain')||{}).checked,dailyEmail:!!(el('s_daily')||{}).checked}};
  var r=await api('/api/admin/settings',{method:'PUT',body:body});
  if(r.ok)alert('Settings saved.');else alert('Save failed: '+((r.data&&r.data.error)||r.status));
}

/* ---- boot ---- */
async function boot(){
  if(!tok()){renderAuthWall();return;}
  try{
    var me=await api('/api/me');ME=me.data;
    var h=await fetch('/health');HEALTH=await h.json();
    shell();
  }catch(e){/* 401 already handled */}
}
/* Delegated event handling — no inline JS. XSS-safe: user/DB values live in
   data-* attributes (HTML-escaped, read via getAttribute at runtime) and are
   never interpolated into an executable JS context. */
document.addEventListener('click',function(e){
  var b=e.target&&e.target.closest?e.target.closest('[data-act]'):null;
  if(!b)return;
  var a=b.getAttribute('data-act'),id=b.getAttribute('data-id');
  if(a==='login')return doLogin();
  if(a==='logout')return logout();
  if(a==='go')return go(id);
  if(a==='export')return exportAffected();
  if(a==='approve')return approve(id);
  if(a==='page'){actOffset=Math.max(0,actOffset+(b.getAttribute('data-off')==='older'?50:-50));return renderView();}
  if(a==='rotate')return rotate(id);
  if(a==='toggle')return toggleDisabled(id,b.getAttribute('data-dis')==='true');
  if(a==='del')return delUser(id,b.getAttribute('data-name'));
  if(a==='create')return createUser();
  if(a==='save')return saveSettings();
});
document.addEventListener('change',function(e){
  var s=e.target; if(!s||!s.matches)return;
  if(s.matches('select[data-act="role"]'))return changeRole(s.getAttribute('data-id'),s.value);
  if(s.matches('select[data-act="stage"]')){affStage=s.value;renderView();}
});
boot();
</script>
</body></html>`;
}

module.exports = page;
