<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
    <meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />
    <title>Invoice</title>
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
            margin-left: 70%;
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
        <form id="form" action="{{ route('supplier.quoteSubmit') }}" method="POST" enctype="multipart/form-data">
            <!-- CSRF Token -->
            <input type="hidden" name="_token" value="{{ csrf_token() }}" />
            <input type="hidden" name="quote_id" value="{!! $quote_id !!}" />
            <input type="hidden" name="claim_id" value="{!! $claim_id !!}" />
            <input type="hidden" name="repair_center_id" value="{!! $repair_center_id !!}" />

         <div id="customer">
            <h1 id="customer-title">{!! $vehicle->make !!} {!! $vehicle->model !!}</h1>
            <table id="meta">
                <tr>
                    <td class="meta-head">Invoice No</td>
                    <td><p>{!! $claim_id !!}</p></td>
                </tr>
                <tr>

                    <td class="meta-head">Date</td>
                    <td><p id="date">{!! \Carbon\Carbon::now()->format('Y-m-d') !!}</p></td>
                </tr>
            </table>
         </div>


		<table id="items" >
            <tr style="font-family: sans-serif;">
		      <th>Item</th>
		      <th>Description</th>
		      <th>Rate</th>
		      <th>Quantity</th>
		      <th>Price</th>
		  </tr>

		  <tr class="item-row">
		      <td class="item-name"><div class="delete-wpr"><textarea name="item[]" style="font-family: sans-serif;"placeholder="Item name" required></textarea><a class="delete" href="javascript:;" title="Remove row">X</a></div></td>
		      <td class="description"><textarea name="description[]" style="font-family: sans-serif;" placeholder="Item description" required></textarea></td>
		      <td><textarea class="cost" name="rate[]" style="font-family: sans-serif;" placeholder="P0.00" required></textarea></td>
		      <td><textarea class="qty" name="qty[]" style="font-family: sans-serif;"placeholder="0" required></textarea></td>
		      <td><span class="price" style="font-family: sans-serif;" required></span></td>
		  </tr>

{{--		  <tr class="item-row" style="font-family: sans-serif;">--}}
{{--		      <td class="item-name"><div class="delete-wpr"><textarea name="item[]" style="font-family: sans-serif;" placeholder="Item name" required></textarea><a class="delete" href="javascript:;" title="Remove row">X</a></div></td>--}}

{{--		      <td class="description"><textarea name="description[]" style="font-family: sans-serif;" placeholder="Item description" required></textarea></td>--}}
{{--		      <td><textarea name="rate[]" class="cost" style="font-family: sans-serif;" placeholder="P0.00" required></textarea></td>--}}
{{--		      <td><textarea name="qty[]" class="qty" style="font-family: sans-serif;"placeholder="0" required></textarea></td>--}}
{{--		      <td><span class="price" style="font-family: sans-serif;" required></span></td>--}}
{{--		  </tr>--}}

        </table>
            <tr id="hiderow">
                <td colspan="5"><a id="addrow" href="javascript:;" title="Add a row">Add a row</a></td>
            </tr>


		  <table id="subtotal_table">
		  <tr style="font-family: sans-serif;">
		      <td colspan="2" class="total-line" style="font-family: sans-serif;">Subtotal</td>
		      <td class="total-value"><div id="subtotal" style="font-family: sans-serif;">P0.00</div></td>
		  </tr>
		  <tr>
              <td colspan="2" class="total-line " style="font-family: sans-serif;">VAT</td>
              <td class="total-value vat"><textarea id="paid" name="vat" placeholder="P0.00" onfocusout="getvat()" style="font-family: sans-serif;" required></textarea></td>
		  </tr>
		  <tr>
              <td colspan="2" class="total-line" style="font-family: sans-serif;">Total</td>
              <td class="total-value"><p id="total" class="total" style="font-family: sans-serif;"></p></td>
		  </tr>
              <input type="hidden" id="final_total" name="total" value="">

		</table>
        <table>
            <tbody>
            <tr id="file_tr">
            <td style="font-size: 16px; font-weight: 500;font-family: sans-serif;">Additional File</td>
            <td><input type="file" class="file" name="file" accept="image/*" required></td>
            </tr>
            </tbody>
        </table><br><br>
        <aside>
            <h1><span style="font-family: sans-serif;">Additional Notes</span></h1>
            <textarea class="note" name="note" placeholder="Add Note" rows="3" style="width: 100%; border: solid 2px #bbb;" required></textarea>
        </aside>
        <button id="submit" type="submit" style="margin: 40px auto; padding: 10px 25px; background-color: #000053; color: #fff;">Submit</button>
        </form>

	</div>

    {{--<script>

        $("#form").on('submit',(function(e) {
            e.preventDefault();

            var item = $(".item-name").map(function() {
                return $(this).text();
            }).get();

            var description = $(".description").map(function() {
                return $(this).text();
            }).get();
            var rate = $(".cost").map(function() {
                return $(this).text();
            }).get();
            var qty = $(".qty").map(function() {
                return $(this).text();
            }).get();
            var price = $(".price").map(function() {
                return $(this).text();
            }).get();

            if(item == '') {
                alert('Please enter Item details');
                return false;
            } else if(jQuery.inArray('0', qty) != '-1') {
                alert('Please enter Quantity. Quantity cannot be 0');
                return false;
            }

            var formData = new FormData(this);
            formData.append('item', item);
            formData.append('description', description);
            formData.append('rate', rate);
            formData.append('qty', qty);
            formData.append('price', price);
            formData.append('vat', $(".vat").text());
            formData.append('total', $(".total").val());
            formData.append('note', $(".note").val());

            $.ajax({
                url: "{{ route('supplier.quoteSubmit') }}",
                type: "POST",
                data:  formData,
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

    </script>--}}
</body>

</html>