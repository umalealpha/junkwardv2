{{-- Verified correctly, but no document exists for that policy and action, or
     the stored file is missing from every disk. This is our fault, not the
     client's, and the wording says so. The failure is already recorded in
     policy_document_access_logs with the reason. --}}
@include('coversheet._shell', [
    'title' => 'We cannot open your document yet',
    'body'  => '<p>We verified you, but your policy document is not ready to download right now.</p>
                <p class="muted">Please call us on <strong>+267 392 8264</strong> or email
                <strong>debtors@alphadirect.co.bw</strong> and we will send it to you straight away.</p>',
])
