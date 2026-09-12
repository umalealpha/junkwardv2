{!! $mail_template !!}

@if(isset($vehicleId))
    <a href="{!! route('supplierViewQuote',['vehicleId'=>$vehicleId,'supplierId'=>$supplier_id]) !!}">Submit Quote</a>
@endif