<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
    <meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />
   
    @if ($claim->claim_type == 'Cellphone')
    <title>Quote</title>
     @else
     <title>Invoice</title>
    @endif
    <script src="{{asset('css/vendors/general/jquery/dist/jquery-1.3.2.min.js')}}" type="text/javascript"></script>
    <script src="{{asset('css/vendors/general/jquery/dist/example.js')}}" type="text/javascript"></script>
    <style>
        * { margin: 0; padding: 0; }
        body { font: 14px/1.4 Georgia, serif;background-color: darkgrey;margin-top: 25px; }
        #page-wrap { width: 800px; margin: 0 auto; background-color: white;padding-left: 35px;padding-right: 35px;padding-top: 1px; }

        textarea { border: 0; font: 14px Georgia, Serif; overflow: hidden; resize: none; }
        table { border-collapse: collapse; }
        table td, table th { border: 1px solid black; padding: 5px; }

        #header { height: 15px; width: 100%; margin: 20px 0; background: #485779; text-align: center; color: white; font: bold 15px Helvetica, Sans-Serif; text-decoration: uppercase; letter-spacing: 20px; padding: 8px 0px; }
        #addrow{ color: #485779;text-decoration: none;}


        #address { width: 300px; height: 150px; font-family:sans-serif;float: left; line-height: 1.5em;}
        #customer { overflow: hidden; }
        #subtotal_table{
            margin-top: 30px;
            margin-left: 75%;
            text-align: left;
        }

        #subtotal_table tr td{
            width: 400px;
        }

        #logo { text-align: right; float: right; position: relative; margin-top: 25px; border: 1px solid #fff; max-width: 540px; max-height: 100px; overflow: hidden; }
        #logo:hover, #logo.edit { border: 1px solid #000; margin-top: 0px; max-height: 125px; }
        #logoctr { display: none; }
        #logo:hover #logoctr, #logo.edit #logoctr { display: block; text-align: right; line-height: 25px; background: #eee; padding: 0 5px; }
        #logohelp { text-align: left; display: none; font-style: italic; padding: 10px 5px;}
        #logohelp input { margin-bottom: 5px; }
        .edit #logohelp { display: block; }
        .edit #save-logo, .edit #cancel-logo { display: inline; }
        .edit #image, #save-logo, #cancel-logo, .edit #change-logo, .edit #delete-logo { display: none; }
        #customer-title {font-weight: bold; font-size:2.7em; text-align:center;padding-bottom:20px;font-family: sans-serif;}

        #meta { margin-top: 20px; width: 300px; float: right; font-family: sans-serif;}
        #meta td { text-align: right;  }
        #meta td.meta-head { text-align: left; background: #eee; }
        #meta td textarea { width: 100%; height: 20px; text-align: right; }

        #items { clear: both; width: 100%; margin: 30px 0 0 0; border: 1px solid black; }
        #items th,td,tr{
            border:1px solid black;
        }
        #items th { background: #eee;}
        #items textarea { width: 80px; height: 50px; }
        #items tr.item-row td {vertical-align: top; }
        #items td.description { width: 300px; }
        #items td.item-name { width: 175px; }
        #items td.description textarea, #items td.item-name textarea { width: 100%; }
        #items td.total-line {  text-align: right; }
        #items td.total-value {  padding: 10px; }
        #items td.total-value textarea { height: 20px; background: none; }
        #items td.balance { background: #eee; }


        #terms { text-align: center; margin: 20px 0 0 0; }
        #terms h5 { text-transform: uppercase; font: 13px Helvetica, Sans-Serif; letter-spacing: 10px; border-bottom: 1px solid black; padding: 0 0 8px 0; margin: 0 0 8px 0; }
        #terms textarea { width: 100%; text-align: center;}

        textarea:hover, textarea:focus, #items td.total-value textarea:hover, #items td.total-value textarea:focus, .delete:hover { background-color:#EEFF88; }

        .delete-wpr { position: relative; }
        .delete { display: block; color: #000; text-decoration: none; position: absolute; background: #EEEEEE; font-weight: bold; padding: 0px 3px; border: 1px solid; top: -6px; left: -22px;  font-size: 12px; }
        #hiderow,
        .delete {
            display: none;
        }
    </style>
</head>
<body>
    <div id="page-wrap">

    <div id="header">QUOTE REQUEST</div>
    <div id="identity">
        <p id="address">Alpha Direct Insurance Co.(Pty) Ltd.<br>Unit 12,Plot 103 G.I.C.P. | P.O. BOX  <br> 26 ADC Gaborone,Botswana <br>
            Phone +267 392 8264 | Fax +267 392 8265 <br> debtors@alphadirect.co.bw | <br>www.alphadirect.co.bw</p>

        <div id="logo">

            <div id="logoctr">
                <a href="javascript:;" id="change-logo" title="Change logo">Change Logo</a>
                <a href="javascript:;" id="save-logo" title="Save changes">Save</a>
                |
                <a href="javascript:;" id="delete-logo" title="Delete logo">Delete Logo</a>
                <a href="javascript:;" id="cancel-logo" title="Cancel changes">Cancel</a>
            </div>

            <img src="{!! asset('images/logo.png') !!}">
        </div>

    </div>

    <div style="clear:both"></div>

        <!-- CSRF Token -->
        <input type="hidden" name="quote_id" value="{!! $quote_id !!}" />

        <div id="customer">
            <h1 id="customer-title">{!! $vehicle->make !!} {!! $vehicle->model !!}</h1>

            <table id="meta">
                <tr>
                @if ($claim->claim_type == 'Cellphone')
                    <td class="meta-head">quote No.</td>
                    <td><p>{!! $quote_id !!}</p></td>
                
                @else
                    <td class="meta-head">Invoice No</td>
                    <td><p>{!! $quote_id !!}</p></td>
                
                @endif
                   
                </tr>
                <tr>
                    <td class="meta-head">Date</td>
                    <td><p id="date">{!! \Carbon\Carbon::now()->format('Y-m-d') !!}</p></td>
                </tr>
            </table>
        </div>

        <table id="items">
            <thead>
            <tr style="font-family: sans-serif;">
                <th><span>Item</span></th>
                <th><span>Description</span></th>
                <th><span>Rate</span></th>
                <th><span>Quantity</span></th>
                <th><span>Price</span></th>
            </tr>
            @foreach($quotedetails as $key => $quotedetail)
                <tr style="font-family: sans-serif;">
                    <td><span class="item"></span>{!! $quotedetail->item !!}</td>
                    <td><span class="description"></span>{!! $quotedetail->description !!}</td>
                    <td><span data-prefix>P</span><span class="rate"></span>{!! $quotedetail->rate !!}</td>
                    <td><span class="qty"></span>{!! $quotedetail->quantity !!}</td>
                    <td><span data-prefix>P</span><span class="price">{!! $quotedetail->rate * $quotedetail->quantity !!}</span></td>
                </tr>
            @endforeach
            </thead>
        </table>



        <table id="subtotal_table">
            <tr style="font-family: sans-serif;">
                <th><span>SubTotal</span></th>
                <td><span data-prefix>P</span><span>{!! $quote->total - $quote->vat !!}</span></td>
            </tr>
            <tr style="font-family: sans-serif;">
                <th><span>VAT</span></th>
                <td><span data-prefix>P</span><span class="vat"></span>{!! $quote->vat !!}</td>
            </tr>

            <tr style="font-family: sans-serif;">
                <th><span>Total</span></th>
                <td><span data-prefix>P</span><span class="total"></span>{!! $quote->total !!}</td>
            </tr>
        </table>


            <h3 style="margin-bottom: 16px; font-family: sans-serif;">Additional File</h3>
            @if($quote->addn_file != NULL)

                    <a href="{!! str_replace(env('AWS_URL'),env('AWS_CLOUDFRONT'),Storage::disk('s3')->url($quote->addn_file)) !!}" target="_blank" download>
                        @if(pathinfo($quote->addn_file, PATHINFO_EXTENSION) == 'pdf')
                            <img src="{{asset('images/pdf.ico')}}" width="20%" height="auto" style="padding-bottom: 20px;">
                        @elseif(pathinfo($quote->addn_file, PATHINFO_EXTENSION) == 'docx' || pathinfo($quote->addn_file, PATHINFO_EXTENSION) == 'doc' || pathinfo($quote->addn_file, PATHINFO_EXTENSION) == 'docm')
                            <img src="{{asset('images/word.ico')}}" width="20%" height="auto"  style="padding-bottom: 20px;">
                        @elseif(pathinfo($quote->addn_file, PATHINFO_EXTENSION) == 'xls' || pathinfo($quote->addn_file, PATHINFO_EXTENSION) == 'xlsx' || pathinfo($quote->addn_file, PATHINFO_EXTENSION) == 'csv')
                            <img src="{{asset('images/excel.png')}}" width="20%" height="auto"  style="padding-bottom: 20px;">
                        @elseif(pathinfo($quote->addn_file, PATHINFO_EXTENSION) == 'jpeg' || pathinfo($quote->addn_file, PATHINFO_EXTENSION) == 'jpg' || pathinfo($quote->addn_file, PATHINFO_EXTENSION) == 'png')
                            <img src="{{str_replace(env('AWS_URL'),env('AWS_CLOUDFRONT'),Storage::disk('s3')->url($quote->addn_file))}}" width="20%" height="auto"  style="padding-bottom: 20px;">
                        @else
                            <img src="{{asset('images/doc.png')}}" width="20%" height="auto"  style="padding-bottom: 20px;">
                        @endif
                    </a>

            @else
                <td style="font-family: sans-serif;">No File Selected</td>
            @endif

        <div style="padding-bottom: 80px;margin-top: 20px;">
            <h3 style="font-family: sans-serif;">Additional Notes</h3>
            <p style="padding-top: 10px;font-family: sans-serif;">{!! $quote->notes !!}</p>
        </div>


</div>
</body>
</html>

