<x-guest-layout>
<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 50%,#1e40af 100%);font-family:'Poppins',sans-serif;padding:20px;">

    {{-- Decorative shapes --}}
    <div style="position:fixed;top:-120px;right:-120px;width:400px;height:400px;border-radius:50%;background:rgba(249,115,22,0.08);pointer-events:none;"></div>
    <div style="position:fixed;bottom:-80px;left:-80px;width:300px;height:300px;border-radius:50%;background:rgba(249,115,22,0.05);pointer-events:none;"></div>

    <div style="width:100%;max-width:440px;">
        {{-- Logo + Title --}}
        <div style="text-align:center;margin-bottom:32px;">
            <div style="display:inline-flex;align-items:center;justify-content:center;width:64px;height:64px;border-radius:16px;background:rgba(255,255,255,0.1);backdrop-filter:blur(10px);margin-bottom:16px;">
                <img src="{{asset('Logo.png')}}" alt="Alpha Direct" style="max-height:40px;filter:brightness(0) invert(1);">
            </div>
            <h1 style="color:#fff;font-size:24px;font-weight:700;margin:0 0 4px;">Alpha Direct Insurance</h1>
            <p style="color:rgba(255,255,255,0.5);font-size:13px;margin:0;">Admin Configuration Panel</p>
        </div>

        {{-- Login Card --}}
        <div style="background:#fff;border-radius:16px;padding:36px;box-shadow:0 20px 60px rgba(0,0,0,0.3);">

            <x-jet-validation-errors class="mb-4" />

            @if ($message = Session::get('error'))
            <div style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626;padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:8px;">
                <i class="fas fa-exclamation-circle"></i>
                {{ $message }}
            </div>
            @endif

            <form action="{{ route('login') }}" method="POST">
                {{ csrf_field() }}

                {{-- Email --}}
                <div style="margin-bottom:20px;">
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px;">Email Address</label>
                    <div style="position:relative;">
                        <i class="fas fa-envelope" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:14px;"></i>
                        <input type="email" name="email" value="{{ old('email') }}" required autofocus
                            style="width:100%;padding:12px 14px 12px 42px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:14px;color:#1e293b;outline:none;transition:border-color 0.2s,box-shadow 0.2s;background:#f8fafc;"
                            onfocus="this.style.borderColor='#f97316';this.style.boxShadow='0 0 0 3px rgba(249,115,22,0.12)';this.style.background='#fff'"
                            onblur="this.style.borderColor='#e2e8f0';this.style.boxShadow='none';this.style.background='#f8fafc'"
                            placeholder="your@email.com">
                    </div>
                </div>

                {{-- Password --}}
                <div style="margin-bottom:24px;">
                    <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px;">Password</label>
                    <div style="position:relative;">
                        <i class="fas fa-lock" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:14px;"></i>
                        <input type="password" name="password" required autocomplete="off"
                            style="width:100%;padding:12px 14px 12px 42px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:14px;color:#1e293b;outline:none;transition:border-color 0.2s,box-shadow 0.2s;background:#f8fafc;"
                            onfocus="this.style.borderColor='#f97316';this.style.boxShadow='0 0 0 3px rgba(249,115,22,0.12)';this.style.background='#fff'"
                            onblur="this.style.borderColor='#e2e8f0';this.style.boxShadow='none';this.style.background='#f8fafc'"
                            placeholder="Enter your password">
                    </div>
                </div>

                {{-- Login Button --}}
                <button type="submit" style="width:100%;padding:13px;background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:600;cursor:pointer;transition:all 0.2s;box-shadow:0 4px 14px rgba(249,115,22,0.3);"
                    onmouseover="this.style.boxShadow='0 6px 20px rgba(249,115,22,0.4)';this.style.transform='translateY(-1px)'"
                    onmouseout="this.style.boxShadow='0 4px 14px rgba(249,115,22,0.3)';this.style.transform='translateY(0)'">
                    Sign In
                </button>
            </form>

            {{-- Microsoft SSO --}}
            @if(config('services.microsoft.client_id'))
            <div style="display:flex;align-items:center;gap:12px;margin:20px 0;">
                <div style="flex:1;height:1px;background:#e2e8f0;"></div>
                <span style="color:#9ca3af;font-size:12px;font-weight:500;">OR</span>
                <div style="flex:1;height:1px;background:#e2e8f0;"></div>
            </div>
            <a href="{{ route('auth.microsoft') }}" style="display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:12px;background:#fff;border:1.5px solid #e2e8f0;border-radius:10px;color:#374151;font-size:14px;font-weight:500;text-decoration:none;transition:all 0.2s;"
                onmouseover="this.style.background='#f8fafc';this.style.borderColor='#cbd5e1'"
                onmouseout="this.style.background='#fff';this.style.borderColor='#e2e8f0'">
                <svg width="18" height="18" viewBox="0 0 21 21"><rect x="1" y="1" width="9" height="9" fill="#f25022"/><rect x="11" y="1" width="9" height="9" fill="#7fba00"/><rect x="1" y="11" width="9" height="9" fill="#00a4ef"/><rect x="11" y="11" width="9" height="9" fill="#ffb900"/></svg>
                Sign in with Microsoft
            </a>
            @endif

            {{-- First time login --}}
            <div style="text-align:center;margin-top:20px;">
                <a href="{{ route('firstTimeLogin') }}" style="color:#f97316;font-size:13px;text-decoration:none;font-weight:500;"
                    onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
                    First time logging in?
                </a>
            </div>
        </div>

        {{-- Footer --}}
        <div style="text-align:center;margin-top:24px;">
            <p style="color:rgba(255,255,255,0.3);font-size:11px;margin:0;">Alpha Direct Insurance · Gaborone, Botswana</p>
            <p style="color:rgba(255,255,255,0.2);font-size:10px;margin-top:4px;">Graphite Admin Panel v2 · {{ date('Y') }}</p>
        </div>
    </div>
</div>
</x-guest-layout>
