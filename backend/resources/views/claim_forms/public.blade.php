<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Your Alpha Direct claim form</title>
    @include('claim_forms._style')
    <style>
        /* Mobile first — most claimants open this on a phone. */
        .wrap  { padding: 14px 0 40px; }
        .bar   { position: sticky; bottom: 0; background: #fff; border-top: 1px solid #e4e7ec;
                 padding: 12px 24px; text-align: right; }
        .btn   { background: #F4A623; color: #0D1B2A; border: 0; border-radius: 6px;
                 padding: 12px 22px; font: bold 14px Arial, Helvetica, sans-serif; cursor: pointer; }
        .btn[disabled] { opacity: .55; cursor: default; }
        .msg   { margin: 16px 24px; padding: 12px 14px; border-radius: 6px; font-size: 13px; display: none; }
        .msg.ok  { background: #edf8f0; border-left: 3px solid #2e7d4f; display: block; }
        .msg.bad { background: #fdeeee; border-left: 3px solid #c0392b; display: block; }
        .up    { margin: 0 24px; padding: 14px; border: 1px dashed #cfd6e0; border-radius: 6px; }
        .up h4 { margin: 0 0 6px; font-size: 13px; }
        .up p  { margin: 0 0 10px; font-size: 12px; color: #55606e; }
        .files { margin: 8px 0 0; font-size: 12px; color: #2e7d4f; }
        .load  { padding: 40px 24px; text-align: center; color: #55606e; }
        @media (max-width: 620px) {
            .l { width: 100%; display: block; }
            .v { width: 100%; display: block; }
            .in { width: 100%; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div id="app" class="load">Loading your claim form&hellip;</div>
</div>

<script>
(function () {
    var token = @json($token);
    var base  = '/api/public/v1/claim-form/' + encodeURIComponent(token);
    var app   = document.getElementById('app');

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    function dead(text) {
        app.className = '';
        app.innerHTML = '<div class="doc"><div class="hdr"><div class="brand">Alpha Direct Insurance</div>'
            + '<div class="ttl">Claim form</div></div>'
            + '<p style="margin:22px 24px;">' + esc(text) + '</p></div>';
    }

    fetch(base, { headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (res) {
            if (!res.ok) { return dead(res.j.message || 'This link is no longer active.'); }
            render(res.j.data);
        })
        .catch(function () { dead('We could not load your form. Please try again shortly.'); });

    function field(label, name, value) {
        return '<label class="f"><span class="l">' + esc(label) + '</span>'
             + '<input class="v in" name="prefill[' + esc(name) + ']" value="' + esc(value) + '"></label>';
    }

    function question(q) {
        var req = q.req ? ' required' : '';
        var star = q.req ? '<span class="req"> *</span>' : '';
        var input;
        if (q.type === 'textarea') {
            input = '<textarea name="answers[' + esc(q.key) + ']" rows="3"' + req + '></textarea>';
        } else if (q.type === 'yesno') {
            input = '<select name="answers[' + esc(q.key) + ']"' + req + '>'
                  + '<option value="">Please choose</option><option value="yes">Yes</option>'
                  + '<option value="no">No</option></select>';
        } else {
            var t = (q.type === 'date' || q.type === 'time') ? q.type : 'text';
            input = '<input type="' + t + '" name="answers[' + esc(q.key) + ']"' + req + '>';
        }
        return '<label class="q"><span class="ql">' + esc(q.label) + star + '</span>' + input + '</label>';
    }

    function group(title, pairs) {
        var rows = pairs.filter(function (p) { return p[2] !== null && p[2] !== undefined; })
                        .map(function (p) { return field(p[0], p[1], p[2]); }).join('');
        return rows ? '<div class="sec">' + esc(title) + '</div><div class="grid">' + rows + '</div>' : '';
    }

    function render(d) {
        var p = d.prefill || {}, ins = p.insured || {}, pol = p.policy || {}, veh = p.vehicle || {};
        var html = '<form id="f" class="doc">'
            + '<div class="hdr"><div class="brand">Alpha Direct Insurance Company (Pty) Ltd</div>'
            + '<div class="ttl">Your claim form</div>'
            + '<div class="ref">Claim ' + esc(d.claimNumber || '') + '</div></div>'
            + '<p class="intro">We have already filled in everything we hold. Please check it, '
            + 'correct anything that is wrong, and answer the questions at the bottom &mdash; '
            + 'those are the parts only you can know.</p>'
            + group('Your details', [
                ['Name of insured', 'insured_name', ins.name],
                ['Email address', 'insured_email', ins.email],
                ['Contact number', 'insured_phone', ins.phone]
              ])
            + group('Your policy', [
                ['Policy number', 'policy_number', pol.number],
                ['Product', 'policy_product', pol.product],
                ['Cover started', 'policy_inception', pol.inception],
                ['Cover ends', 'policy_expiry', pol.expiry],
                ['Sum insured', 'policy_sum_insured', pol.sum_insured],
                ['Excess', 'policy_excess', pol.excess]
              ])
            + group('Your vehicle', [
                ['Registration number', 'vehicle_registration', veh.registration],
                ['Make', 'vehicle_make', veh.make],
                ['Model', 'vehicle_model', veh.model],
                ['Year', 'vehicle_year', veh.year],
                ['Chassis number', 'vehicle_chassis', veh.chassis]
              ]);

        if (veh.glass) {
            var g = veh.glass;
            html += group('The damaged glass', [
                ['Type of glass', 'glass_type', g.type_of_glass],
                ['Size of broken pane', 'glass_size', g.plate_size],
                ['How it was damaged', 'glass_cause', g.damage_cause],
                ['Extent of the damage', 'glass_extent', g.damage_extent],
                ['Where the vehicle is', 'glass_where', g.vehicle_situated],
                ['Replacement estimate', 'glass_estimate', g.estimate]
            ]);
        }

        html += '<div class="sec">What happened</div><div class="qs">'
             + (p.claimant_to_complete || []).map(question).join('') + '</div>'
             + '<div class="sec">Your documents</div>'
             + '<div class="up"><h4>Send us anything you have</h4>'
             + '<p>The police report, your quotations, photographs. You can add them one at a time.</p>'
             + '<input type="file" id="file"><div class="files" id="files"></div></div>'
             + '<div class="msg" id="msg"></div>'
             + '<div class="bar"><button class="btn" type="submit">Send my form</button></div>'
             + '</form>';

        app.className = '';
        app.innerHTML = html;

        document.getElementById('file').addEventListener('change', upload);
        document.getElementById('f').addEventListener('submit', submit);
    }

    function say(kind, text) {
        var m = document.getElementById('msg');
        m.className = 'msg ' + kind;
        m.textContent = text;
    }

    function upload(e) {
        var f = e.target.files && e.target.files[0];
        if (!f) { return; }
        var fd = new FormData();
        fd.append('file', f);
        say('ok', 'Sending ' + f.name + '…');
        fetch(base + '/upload', { method: 'POST', body: fd, headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (!res.ok) { return say('bad', res.j.message || 'That file could not be saved.'); }
                var list = document.getElementById('files');
                list.innerHTML += '✓ ' + esc(f.name) + '<br>';
                say('ok', 'Saved. You can add another if you have one.');
                e.target.value = '';
            })
            .catch(function () { say('bad', 'That file could not be saved. Please try again.'); });
    }

    function submit(e) {
        e.preventDefault();
        var form = e.target;
        var btn  = form.querySelector('button[type=submit]');
        btn.disabled = true;
        say('ok', 'Sending…');

        var payload = { answers: {}, prefill: {} };
        Array.prototype.forEach.call(form.elements, function (el) {
            var m = /^(answers|prefill)\[(.+)\]$/.exec(el.name || '');
            if (m) { payload[m[1]][m[2]] = el.value; }
        });

        fetch(base + '/submit', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (!res.ok) { btn.disabled = false; return say('bad', res.j.message || 'We could not send it.'); }
                app.innerHTML = '<div class="doc"><div class="hdr">'
                    + '<div class="brand">Alpha Direct Insurance</div>'
                    + '<div class="ttl">Thank you</div></div>'
                    + '<p style="margin:22px 24px;">We have your form. Your claims handler will be in touch. '
                    + 'If you find another document later, open this same link again and add it.</p></div>';
                window.scrollTo(0, 0);
            })
            .catch(function () { btn.disabled = false; say('bad', 'We could not send it. Please try again.'); });
    }
})();
</script>
</body>
</html>
