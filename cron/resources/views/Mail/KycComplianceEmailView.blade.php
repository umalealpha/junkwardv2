@component('mail::layout')
{{-- Header --}}
@slot('header')
@component('mail::header', ['url' => 'https://alphadirect.co.bw'])
<div class="clearfix float-my-children">
    <img style="width: 200px; " src="https://pay.alphadirect.co.bw/images/alphadirect_logo.png">
</div>
@endcomponent
@endslot
{{-- Body --}}
<div class="container">
    Hi @isset($mail_data['firstName']) {!! $mail_data['firstName'] !!} @endisset @isset($mail_data['lastName']) {!! $mail_data['lastName'] !!} @endisset,
    <br><br>
    Please upload your documents to complete <strong>KYC</strong> by clicking on <strong>Verify me</strong> button
    <br>
    <ol>
    @isset($upload_documents)
        @foreach($upload_documents as $document)
            <li>{!! ucfirst(str_replace('_', ' ', $document)) !!}</li>
        @endforeach
    @endisset
    </ol>
    <br>
    <div class="row" style="text-align: center;">
        <div class="form-group col-md-4">
            <script src="https://web-button.getmati.com/button.js"></script>
            <a href='{!! url($mati_link) !!}'><button type="button" id="mati-button" style="background-color: #4CAF50; color: white; border: 2px solid #4CAF50; font-size: 16px; padding: 10px 24px; border-radius: 4px;">Verify me</button></a>
        </div>
    </div>
    <br><br>
</div>
Regards,
{{-- Footer --}}
@slot('footer')
@component('mail::footer')
&copy; Copyright {{ now()->year }} Alpha Direct, All Rights Reserved.
<script src="{{asset('css/vendors/general/jquery/dist/jquery.js')}}" type="text/javascript"></script>
<script>
    alert();
    const button = document.getElementById("mati-button");
    // setup callbacks
    button.addEventListener('mati:loaded', ({ detail }) => {
    console.log('loaded payload', detail)
    });
    button.addEventListener('mati:userFinishedSdk', ({ detail }) => {
    console.log('finished payload', detail);
    console.log('finished payload', detail['identityId']);
    $('input[name="mati-identityId"]').val(detail['identityId']);
    $('#matiModal-submit').attr('disabled',false);
    $('#id-error').hide();
    });
    button.addEventListener('mati:exitedSdk', ({ detail }) => {
    console.log('exited payload', detail)
    });
</script>
@endcomponent
@endslot
@endcomponent
