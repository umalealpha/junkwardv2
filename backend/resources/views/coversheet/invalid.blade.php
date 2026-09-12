{{-- Unknown, malformed or revoked QR token. Deliberately says the same thing
     for all three, so a scanned sheet cannot be used to probe which tokens
     are real. --}}
@include('coversheet._shell', [
    'title' => 'This link is not valid',
    'body'  => '<p>We could not open a policy from this QR code. It may have been reprinted, or the code did not scan cleanly.</p>
                <p class="muted">Try scanning again in better light. If it still does not work, call us on
                <strong>+267 392 8264</strong> and we will send your policy document.</p>',
])
