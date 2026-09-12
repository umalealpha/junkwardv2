<h1>Hi, {{ $name }} </h1>

<h3>Your Policy: #{{ $policy->policyNumber }} has been processed successfully</h3>

<br>
@if($has_wordings == 1)
    @if($documents)
        <br>
        <p>Please find below documents for future use.</p>
        <br>
        @foreach($documents as $d)
            <a href ="{{ 'https://alphadirect.s3.ap-south-1.amazonaws.com/'.$d->link }}" target= "_blank">{{ $d->name }}</a>
        @endforeach
    @endif
@endif

@if($has_schedule == 1)
        <a href ="{{ 'https://graphite.alphadirect.co.bw/api/generatePolicyDocument/'.$policy->id }}">Policy Document</a>
@endif


