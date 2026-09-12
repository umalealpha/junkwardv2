{{-- The identity check passed, but the short window has run out (or the link
     was copied to another device). Offer the way back rather than a dead end. --}}
@include('coversheet._shell', [
    'title' => 'Your session has expired',
    'body'  => '<p>For your protection, we only keep you signed in for a short time.</p>
                <a class="btn" href="/p/d/' . e($token) . '">Start again</a>
                <p class="muted">You will be sent a new one-time code.</p>',
])
