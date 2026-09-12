<!DOCTYPE html>
<html lang="en" >
<head>
    <meta charset="utf-8">
    <title>Alphadirect</title>
    <link rel="license" href="https://www.opensource.org/licenses/mit-license/">
</head>
<style>
    *
    {
        border: 0;
        box-sizing: content-box;
        color: inherit;
        font-family: inherit;
        font-size: inherit;
        font-style: inherit;
        font-weight: inherit;
        line-height: inherit;
        list-style: none;
        margin: 0;
        padding: 0;
        text-decoration: none;
        vertical-align: top;
    }

    /* content editable */

    *[contenteditable] { border-radius: 0.25em; min-width: 1em; outline: 0; }

    *[contenteditable] { cursor: pointer; }

    *[contenteditable]:hover, *[contenteditable]:focus, td:hover *[contenteditable], td:focus *[contenteditable], img.hover { background: #DEF; box-shadow: 0 0 1em 0.5em #DEF; }

    span[contenteditable] { display: inline-block; }

    /* heading */

    h1 { font: bold 100% sans-serif; letter-spacing: 0.5em; text-align: center; text-transform: uppercase; }

    /* table */

    table { font-size: 90%; table-layout: fixed; width: 100%; }
    table { border-collapse: separate; border-spacing: 2px; }
    th, td { border-width: 1px; padding: 0.5em; position: relative; text-align: left; }
    th, td { border-radius: 0.25em; border-style: solid; }
    th { background: #EEE; border-color: #BBB; }
    td { border-color: #DDD; }

    /* page */

    html { font: 16px/1 'Open Sans', sans-serif; overflow: auto; padding: 0.5in; }
    html { background: #999; cursor: default; }

    body { box-sizing: border-box; height: auto; margin: 0 auto; overflow: hidden; padding: 0.5in; width: 8.5in; }
    body { background: #FFF; border-radius: 1px; box-shadow: 0 0 1in -0.25in rgba(0, 0, 0, 0.5); }

    /* header */

    header { margin: 0 0 3em; }
    header:after { clear: both; content: ""; display: table; }

    header h1 { background: #000053; border-radius: 0.25em; color: #FFF; margin: 0 0 1em; padding: 0.5em 0; }
    header address { float: left; font-size: 90%; font-style: normal; line-height: 1.25; margin: 0 1em 1em 0; }
    header address p { margin: 0 0 0.25em; }
    header span, header img { display: block; float: right; }
    header span { margin: 0 0 1em 1em; max-height: 25%; max-width: 60%; position: relative; }
    header img { max-height: 100%; max-width: 100%; }
    header input { cursor: pointer; -ms-filter:"progid:DXImageTransform.Microsoft.Alpha(Opacity=0)"; height: 100%; left: 0; opacity: 0; position: absolute; top: 0; width: 100%; }

    /* article */

    article, article address, table.meta, table.inventory { margin: 0 0 3em; }
    article:after { clear: both; content: ""; display: table; }
    article h1 { clip: rect(0 0 0 0); position: absolute; }

    article address { float: left; font-size: 125%; font-weight: bold; }

    /* table meta & balance */

    table.meta, table.balance { float: right; width: 36%; }
    table.meta:after, table.balance:after { clear: both; content: ""; display: table; }

    /* table meta */

    table.meta th { width: 40%; }
    table.meta td { width: 60%; }

    /* table items */

    table.inventory { clear: both; width: 100%; }
    table.inventory th { font-weight: bold; text-align: center; }

    table.inventory td:nth-child(1) { width: 26%; }
    table.inventory td:nth-child(2) { width: 38%; }
    table.inventory td:nth-child(3) { text-align: right; width: 12%; }
    table.inventory td:nth-child(4) { text-align: right; width: 12%; }
    table.inventory td:nth-child(5) { text-align: right; width: 12%; }

    /* table balance */

    table.balance th, table.balance td { width: 50%; }
    table.balance td { text-align: right; }

    /* aside */

    aside h1 { border: none; border-width: 0 0 1px; margin: 0 0 1em; }
    aside h1 { border-color: #999; border-bottom-style: solid; }

    /* javascript */

    .add, .cut
    {
        border-width: 1px;
        display: block;
        font-size: .8rem;
        padding: 0.25em 0.5em;
        float: left;
        text-align: center;
        width: 0.6em;
    }

    .add, .cut
    {
        background: #9AF;
        box-shadow: 0 1px 2px rgba(0,0,0,0.2);
        background-image: -moz-linear-gradient(#00ADEE 5%, #0078A5 100%);
        background-image: -webkit-linear-gradient(#00ADEE 5%, #0078A5 100%);
        border-radius: 0.5em;
        border-color: #0076A3;
        color: #FFF;
        cursor: pointer;
        font-weight: bold;
        text-shadow: 0 -1px 2px rgba(0,0,0,0.333);
    }

    .add { margin: -2.5em 0 0; }

    .add:hover { background: #00ADEE; }

    .cut { opacity: 0; position: absolute; top: 0; left: -1.5em; }
    .cut { -webkit-transition: opacity 100ms ease-in; }

    tr:hover .cut { opacity: 1; }

    @media print {
        * { -webkit-print-color-adjust: exact; }
        html { background: none; padding: 0; }
        body { box-shadow: none; margin: 0; }
        span:empty { display: none; }
        .add, .cut { display: none; }
    }
    @page { margin: 0; }

.submitbtn {
  background-color: #000053;
  border: none;
  color: white;
  padding: 16px 32px;
  text-align: center;
  font-size: 16px;
  margin: 16px 2px;
  transition: 0.3s;
}

.submitbtn:hover {
  background-color: #0000538c;
  cursor:pointer;
  color: white;
}
</style>

<body>
<header>
    <h1>Invoice Request</h1>
    <address>
        <p>Alpha Direct Insurance Co.(Pty) Ltd.<br>Unit 12,Plot 103 G.I.C.P. | P.O. BOX  <br> 26 ADC Gaborone,Botswana <br>
                 Phone +267 392 8264 | Fax +267 392 8265 <br> debtors@alphadirect.co.bw | <br>www.alphadirect.co.bw</p>
    </address>
    <img src="{!! asset('images/logo.png') !!}">
</header>
<form id="form" action="{{ route('supplier.invoiceSubmit') }}" method="post" enctype="multipart/form-data">
    <!-- CSRF Token -->
    <input type="hidden" name="_token" value="{{ csrf_token() }}" />
    <input type="hidden" name="claim_id" value="{!! $claim_id !!}" />
    <input type="hidden" name="quote_id" value="{!! $quote_id !!}" />
<article>
    <h1>Recipient</h1>
    <address>
        @if ($vehicle != NULL)
        <p style="font-size: 50px;">{!! $vehicle->make !!} {!! $vehicle->model !!}</p>
        @endif
    </address>
    <table class="meta">
        <tr>
            <th><span>Invoice</span></th>
            <td><span>{!! $claim_id !!}</span></td>
        </tr>
        <tr>
            <th><span>Date</span></th>
            <td><span>{!! \Carbon\Carbon::now()->format('Y-m-d') !!}</span></td>
        </tr>
    </table>
    <table class="inventory">
        <tbody>
        @if($quote != NULL && $quote->invoice != NULL)
            <tr>
                <td style="font-size: 16px; font-weight: 500;">Resent Invoice Notes</td>
                <td style="font-size: 16px; font-weight: 500;">{!! $quote->resent_invoice_notes !!}</td>
            </tr>
        @endif
        <tr>
            <td style="font-size: 16px; font-weight: 500;">Select Invoice</td>
            <td><input type="file" class="invoice" name="invoice" required></td>
        </tr>
        </tbody>
    </table>
</article>
<aside>
    <h1><span>After Repair Images</span></h1>
    <table class="inventory">
        <tbody>
            
        <tr>
            <td style="font-size: 16px; font-weight: 500;">Select Front Image</td>
            <td><input type="file" class="invoice" name="after_repair_front" required></td>
        </tr>
        <tr>
            <td style="font-size: 16px; font-weight: 500;">Select Back Image</td>
            <td><input type="file" class="invoice" name="after_repair_back" required></td>
        </tr>
        <tr>
            <td style="font-size: 16px; font-weight: 500;">Select Left Image</td>
            <td><input type="file" class="invoice" name="after_repair_left" required></td>
        </tr>
        <tr>
            <td style="font-size: 16px; font-weight: 500;">Select Right Image</td>
            <td><input type="file" class="invoice" name="after_repair_right" required></td>
        </tr>
        <tr>
            <td style="font-size: 16px; font-weight: 500;">Select Top Image</td>
            <td><input type="file" class="invoice" name="after_repair_top" required></td>
        </tr>
        <tr>
            <td style="font-size: 16px; font-weight: 500;">Select Bottom Image</td>
            <td><input type="file" class="invoice" name="after_repair_bottom" required></td>
        </tr>
        </tbody>
    </table>
</aside>

<aside>
    <h1><span>Additional Notes</span></h1>
    <textarea class="note" name="note" placeholder="Add Note" rows="3" style="width: 100%; border: solid 2px #bbb;"></textarea>
</aside>

<button id="submit" class="submitbtn">Submit</button>

<br>
<br>
</form>
</body>
</html>
<script src="{{asset('css/vendors/general/jquery/dist/jquery.js')}}" type="text/javascript"></script>

<script>
    $(document).ready(function (e) {
        $("#form").on('submit',(function(e) {
            e.preventDefault();
            $.ajax({
                url: "{{ route('supplier.invoiceSubmit') }}",
                type: "POST",
                data:  new FormData(this),
                contentType: false,
                cache: false,
                processData:false,

                success: function(data)
                {
                    if(data=='invalid')
                    {
                        alert('Invalid');
                    }
                    else
                    {
                        document.location.href="{!! route('thankyou'); !!}";
                    }
                },
                error: function(e)
                {
                    $("#err").html(e).fadeIn();
                }
            });
        }));
    });

    /*$("#submit").click(function(){

        var invoice = $(".invoice").val();

        if(invoice == '') {
            alert('Please Upload Invoice');
            return false;
        }

        $.ajax({
            url: '{{ route('supplier.invoiceSubmit') }}',
            data: {
                "_token": "{{ csrf_token() }}",
                "invoice": invoice,
                "note": $(".note").val(),
                "data":  new FormData(this),
                contentType: false,
                cache: false,
                processData:false,
                "claim_id": {!! $claim_id !!},
            },
            type: 'post',
            datatype : 'json',
            success: function(data) {
                window.location.href = 'https://alphadirect.co.bw/';
            },
        });

    });*/

</script>
