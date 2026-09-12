@extends('admin.layouts.app')

@section('title', 'AI Assistant Configuration')

@section('content')
<div class="kt-subheader kt-grid__item" id="kt_subheader">
    <div class="kt-subheader__main">
        <h3 class="kt-subheader__title">AI Assistant Configuration</h3>
        <span class="kt-subheader__separator kt-hidden"></span>
        <div class="kt-subheader__breadcrumbs">
            <a href="#" class="kt-subheader__breadcrumbs-home"><i class="flaticon2-shelter"></i></a>
            <span class="kt-subheader__breadcrumbs-separator"></span>
            <a href="{{ route('admin-dashboard') }}" class="kt-subheader__breadcrumbs-link">Dashboard</a>
            <span class="kt-subheader__breadcrumbs-separator"></span>
            <span class="kt-subheader__breadcrumbs-link kt-subheader__breadcrumbs-link--active">AI Configuration</span>
        </div>
    </div>
</div>

<div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <strong>Saved!</strong> {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.ai-config.update') }}">
        @csrf
        @method('POST')

        <div class="row">

            {{-- ── Left: Provider Selection ─────────────────────────── --}}
            <div class="col-md-4">
                <div class="kt-portlet">
                    <div class="kt-portlet__head">
                        <div class="kt-portlet__head-label">
                            <span class="kt-portlet__head-icon"><i class="fa fa-robot text-primary"></i></span>
                            <h3 class="kt-portlet__head-title">Provider</h3>
                        </div>
                    </div>
                    <div class="kt-portlet__body">

                        {{-- Enable/Disable toggle --}}
                        <div class="form-group">
                            <label class="kt-checkbox kt-checkbox--bold kt-checkbox--brand">
                                <input type="checkbox" name="ai_enabled" value="1"
                                    {{ ($settings['ai_enabled'] ?? '1') === '1' ? 'checked' : '' }}>
                                Enable AI Assistant
                                <span></span>
                            </label>
                            <span class="form-text text-muted">When disabled, the AI button is hidden for all users.</span>
                        </div>

                        <div class="kt-separator kt-separator--dashed"></div>

                        {{-- Provider radio --}}
                        <div class="form-group">
                            <label class="form-label font-weight-bold">Active Provider</label>

                            <div class="kt-radio-list mt-2">
                                <label class="kt-radio kt-radio--bold kt-radio--brand">
                                    <input type="radio" name="ai_provider" value="groq"
                                        {{ ($settings['ai_provider'] ?? 'groq') === 'groq' ? 'checked' : '' }}
                                        onchange="showProvider('groq')">
                                    <span></span>
                                    <span class="ml-2">
                                        <strong>Groq</strong>
                                        <span class="badge badge-success ml-1">Free</span>
                                        <small class="d-block text-muted">Llama 3.3 70B — Fast &amp; free tier</small>
                                    </span>
                                </label>
                                <label class="kt-radio kt-radio--bold kt-radio--brand mt-3">
                                    <input type="radio" name="ai_provider" value="anthropic"
                                        {{ ($settings['ai_provider'] ?? 'groq') === 'anthropic' ? 'checked' : '' }}
                                        onchange="showProvider('anthropic')">
                                    <span></span>
                                    <span class="ml-2">
                                        <strong>Anthropic Claude</strong>
                                        <span class="badge badge-primary ml-1">Recommended</span>
                                        <small class="d-block text-muted">Claude Opus 4 — Most capable</small>
                                    </span>
                                </label>
                                <label class="kt-radio kt-radio--bold kt-radio--brand mt-3">
                                    <input type="radio" name="ai_provider" value="gemini"
                                        {{ ($settings['ai_provider'] ?? 'groq') === 'gemini' ? 'checked' : '' }}
                                        onchange="showProvider('gemini')">
                                    <span></span>
                                    <span class="ml-2">
                                        <strong>Google Gemini</strong>
                                        <span class="badge badge-info ml-1">Vision + PDF</span>
                                        <small class="d-block text-muted">Gemini 2.5 Flash — reads images &amp; PDFs natively</small>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="kt-separator kt-separator--dashed"></div>

                        {{-- Current status --}}
                        <div class="form-group mb-0">
                            <label class="form-label font-weight-bold">Current Status</label>
                            <div id="status-box" class="p-3 rounded" style="background:#f8f9fa; border:1px solid #e9ecef;">
                                <div class="d-flex align-items-center">
                                    <span id="status-dot" class="rounded-circle d-inline-block mr-2"
                                          style="width:10px;height:10px;background:#6c757d;"></span>
                                    <span id="status-text" class="text-muted small">Click "Test Connection" to check</span>
                                </div>
                                <small id="status-model" class="text-muted d-none mt-1 d-block"></small>
                            </div>
                            <button type="button" class="btn btn-outline-info btn-sm mt-2 w-100" onclick="testConnection()">
                                <i class="fa fa-plug"></i> Test Connection
                            </button>
                        </div>

                    </div>
                </div>
            </div>

            {{-- ── Right: API Keys + Models ─────────────────────────── --}}
            <div class="col-md-8">

                {{-- Groq Settings --}}
                <div class="kt-portlet" id="groq-section"
                     style="{{ ($settings['ai_provider'] ?? 'groq') !== 'groq' ? 'opacity:0.5' : '' }}">
                    <div class="kt-portlet__head">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title">
                                <img src="https://groq.com/favicon.ico" width="16" class="mr-1" onerror="this.remove()">
                                Groq Settings
                                <span class="badge badge-success ml-2">Free Tier Available</span>
                            </h3>
                        </div>
                        <div class="kt-portlet__head-toolbar">
                            <a href="https://console.groq.com/keys" target="_blank" class="btn btn-sm btn-label-info">
                                <i class="fa fa-key"></i> Get API Key
                            </a>
                        </div>
                    </div>
                    <div class="kt-portlet__body">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label>API Key</label>
                                    <div class="input-group">
                                        <input type="password" name="groq_api_key" id="groq_api_key"
                                            class="form-control"
                                            placeholder="{{ !empty($settings['groq_api_key']) ? '••••••••••••••••••••' . substr($settings['groq_api_key'], -6) : 'gsk_...' }}"
                                            autocomplete="new-password">
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-secondary" onclick="toggleKey('groq_api_key')">
                                                <i class="fa fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <span class="form-text text-muted">Leave blank to keep existing key.</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Model</label>
                                    <select name="groq_model" class="form-control">
                                        <option value="llama-3.3-70b-versatile"
                                            {{ ($settings['groq_model'] ?? 'llama-3.3-70b-versatile') === 'llama-3.3-70b-versatile' ? 'selected' : '' }}>
                                            Llama 3.3 70B (Recommended)
                                        </option>
                                        <option value="llama-3.1-70b-versatile"
                                            {{ ($settings['groq_model'] ?? '') === 'llama-3.1-70b-versatile' ? 'selected' : '' }}>
                                            Llama 3.1 70B
                                        </option>
                                        <option value="llama3-70b-8192"
                                            {{ ($settings['groq_model'] ?? '') === 'llama3-70b-8192' ? 'selected' : '' }}>
                                            Llama 3 70B (Fast)
                                        </option>
                                        <option value="llama-3.1-8b-instant"
                                            {{ ($settings['groq_model'] ?? '') === 'llama-3.1-8b-instant' ? 'selected' : '' }}>
                                            Llama 3.1 8B (Fastest)
                                        </option>
                                        <option value="mixtral-8x7b-32768"
                                            {{ ($settings['groq_model'] ?? '') === 'mixtral-8x7b-32768' ? 'selected' : '' }}>
                                            Mixtral 8x7B
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="kt-alert kt-alert--outline alert alert-info" role="alert">
                            <span class="kt-alert__icon"><i class="fa fa-info-circle"></i></span>
                            <div class="kt-alert__text">
                                <strong>Free limits:</strong> 100,000 tokens/day · 30 requests/minute · No credit card required.
                                Sign up at <a href="https://groq.com" target="_blank">groq.com</a>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Anthropic Settings --}}
                <div class="kt-portlet" id="anthropic-section"
                     style="{{ ($settings['ai_provider'] ?? 'groq') !== 'anthropic' ? 'opacity:0.5' : '' }}">
                    <div class="kt-portlet__head">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title">
                                Anthropic Claude Settings
                                <span class="badge badge-primary ml-2">Most Capable</span>
                            </h3>
                        </div>
                        <div class="kt-portlet__head-toolbar">
                            <a href="https://console.anthropic.com/settings/keys" target="_blank" class="btn btn-sm btn-label-primary">
                                <i class="fa fa-key"></i> Get API Key
                            </a>
                        </div>
                    </div>
                    <div class="kt-portlet__body">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label>API Key</label>
                                    <div class="input-group">
                                        <input type="password" name="anthropic_api_key" id="anthropic_api_key"
                                            class="form-control"
                                            placeholder="{{ !empty($settings['anthropic_api_key']) ? '••••••••••••••••••••' . substr($settings['anthropic_api_key'], -6) : 'sk-ant-api03-...' }}"
                                            autocomplete="new-password">
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-secondary" onclick="toggleKey('anthropic_api_key')">
                                                <i class="fa fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <span class="form-text text-muted">Leave blank to keep existing key.</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Model</label>
                                    <select name="anthropic_model" class="form-control">
                                        <option value="claude-opus-4-6"
                                            {{ ($settings['anthropic_model'] ?? 'claude-opus-4-6') === 'claude-opus-4-6' ? 'selected' : '' }}>
                                            Claude Opus 4.6 (Best)
                                        </option>
                                        <option value="claude-sonnet-4-6"
                                            {{ ($settings['anthropic_model'] ?? '') === 'claude-sonnet-4-6' ? 'selected' : '' }}>
                                            Claude Sonnet 4.6 (Balanced)
                                        </option>
                                        <option value="claude-haiku-4-5-20251001"
                                            {{ ($settings['anthropic_model'] ?? '') === 'claude-haiku-4-5-20251001' ? 'selected' : '' }}>
                                            Claude Haiku 4.5 (Fast)
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="kt-alert kt-alert--outline alert alert-warning" role="alert">
                            <span class="kt-alert__icon"><i class="fa fa-exclamation-triangle"></i></span>
                            <div class="kt-alert__text">
                                Anthropic Claude requires a paid API plan. Visit
                                <a href="https://console.anthropic.com" target="_blank">console.anthropic.com</a> to add billing.
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Gemini Settings --}}
                <div class="kt-portlet" id="gemini-section"
                     style="{{ ($settings['ai_provider'] ?? 'groq') !== 'gemini' ? 'opacity:0.5' : '' }}">
                    <div class="kt-portlet__head">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title">
                                Google Gemini Settings
                                <span class="badge badge-info ml-2">Vision + PDF</span>
                            </h3>
                        </div>
                        <div class="kt-portlet__head-toolbar">
                            <a href="https://aistudio.google.com/app/apikey" target="_blank" class="btn btn-sm btn-label-info">
                                <i class="fa fa-key"></i> Get API Key
                            </a>
                        </div>
                    </div>
                    <div class="kt-portlet__body">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label>API Key</label>
                                    <div class="input-group">
                                        <input type="password" name="gemini_api_key" id="gemini_api_key"
                                            class="form-control"
                                            placeholder="{{ !empty($settings['gemini_api_key']) ? '••••••••••••••••••••' . substr($settings['gemini_api_key'], -6) : 'AIza...' }}"
                                            autocomplete="new-password">
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-secondary" onclick="toggleKey('gemini_api_key')">
                                                <i class="fa fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <span class="form-text text-muted">Leave blank to keep existing key.</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Model</label>
                                    <select name="gemini_model" class="form-control">
                                        <option value="gemini-2.5-flash"
                                            {{ ($settings['gemini_model'] ?? 'gemini-2.5-flash') === 'gemini-2.5-flash' ? 'selected' : '' }}>
                                            Gemini 2.5 Flash (Recommended)
                                        </option>
                                        <option value="gemini-2.5-pro"
                                            {{ ($settings['gemini_model'] ?? '') === 'gemini-2.5-pro' ? 'selected' : '' }}>
                                            Gemini 2.5 Pro (Most capable)
                                        </option>
                                        <option value="gemini-2.0-flash"
                                            {{ ($settings['gemini_model'] ?? '') === 'gemini-2.0-flash' ? 'selected' : '' }}>
                                            Gemini 2.0 Flash
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="kt-alert kt-alert--outline alert alert-info" role="alert">
                            <span class="kt-alert__icon"><i class="fa fa-info-circle"></i></span>
                            <div class="kt-alert__text">
                                Reads images <strong>and PDFs</strong> natively (no Anthropic fallback needed). Get a key at
                                <a href="https://aistudio.google.com/app/apikey" target="_blank">aistudio.google.com</a>.
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        {{-- Save button --}}
        <div class="d-flex justify-content-end mb-4">
            <button type="submit" class="btn btn-brand btn-lg px-5">
                <i class="fa fa-save"></i> Save Configuration
            </button>
        </div>

    </form>
</div>

<script>
function showProvider(provider) {
    const sections = { groq: 'groq-section', anthropic: 'anthropic-section', gemini: 'gemini-section' };
    Object.entries(sections).forEach(([name, id]) => {
        const el = document.getElementById(id);
        if (el) el.style.opacity = provider === name ? '1' : '0.5';
    });
}

function toggleKey(id) {
    const input = document.getElementById(id);
    input.type = input.type === 'password' ? 'text' : 'password';
}

function testConnection() {
    const provider = document.querySelector('input[name="ai_provider"]:checked').value;
    const btn      = event.target;
    const statusDot  = document.getElementById('status-dot');
    const statusText = document.getElementById('status-text');
    const statusModel = document.getElementById('status-model');

    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Testing...';
    statusDot.style.background  = '#ffc107';
    statusText.textContent      = 'Connecting...';
    statusText.className        = 'text-warning small';
    statusModel.classList.add('d-none');

    fetch('{{ route("admin.ai-config.test") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ provider })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            statusDot.style.background = '#28a745';
            statusText.textContent     = data.message;
            statusText.className       = 'text-success small font-weight-bold';
            if (data.model) {
                statusModel.textContent = 'Model: ' + data.model;
                statusModel.classList.remove('d-none');
            }
        } else {
            statusDot.style.background = '#dc3545';
            statusText.textContent     = data.message;
            statusText.className       = 'text-danger small';
        }
    })
    .catch(() => {
        statusDot.style.background = '#dc3545';
        statusText.textContent     = 'Request failed — check network';
        statusText.className       = 'text-danger small';
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-plug"></i> Test Connection';
    });
}
</script>
@endsection
