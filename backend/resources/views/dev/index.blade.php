@extends('dev.layout', ['title' => 'Dev Portal'])

@section('content')
<h2>Tools</h2>
<p class="muted">Environment: <code>{{ app()->environment() }}</code> &nbsp;&middot;&nbsp; Laravel {{ app()->version() }} &nbsp;&middot;&nbsp; PHP {{ PHP_VERSION }}</p>

@foreach ($tools as $t)
    <a class="card" href="{{ $t['url'] }}" style="display: block;">
        <h3>{{ $t['label'] }}</h3>
        <p>{{ $t['desc'] }}</p>
    </a>
@endforeach

<h2 style="margin-top: 32px;">Access</h2>
<div class="card">
    <h3>Roles</h3>
    <p>
        Assign one Spatie role per user (roles are additive where they overlap):<br>
        <code>developer</code> — every page.<br>
        <code>dev_viewer</code> — endpoints + openapi + docs (read-only API reference).<br>
        <code>dev_log_viewer</code> — logs + docs (support/ops triaging prod incidents).<br>
        <code>dev_docs_viewer</code> — docs only.<br>
        Seed with <code>php artisan db:seed --class="Database\\Seeders\\DevPortalRolesSeeder"</code>, assign via the Admin UI or <code>$user-&gt;assignRole('developer')</code>.
    </p>
</div>
<div class="card">
    <h3>Auth</h3>
    <p>POST <code>/api/v1/auth/login</code> with <code>{ email, password }</code> → returns a Sanctum bearer token. Prepend with <code>Authorization: Bearer &lt;token&gt;</code> on every protected request.</p>
</div>
<div class="card">
    <h3>Webhooks (no auth)</h3>
    <p>DPO, Infobip, Mailgun, WhatsApp — each verifies inbound payloads through its own provider-specific signature.</p>
</div>
<div class="card">
    <h3>Regenerating docs</h3>
    <p>Routes change frequently. Run <code>php artisan dev:generate-docs</code> after adding/removing endpoints to refresh <code>backend/dev/API_ENDPOINTS.md</code> and <code>backend/dev/openapi.json</code>.</p>
</div>
@endsection
