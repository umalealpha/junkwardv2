@component('mail::layout')
{{-- Header --}}
@slot('header')
@component('mail::header', ['url' => 'https://alphadirect.co.bw'])
<div class="clearfix float-my-children">
    <img style="width: 200px; " src="https://graphite.alphadirect.co.bw/Logo.png">
</div>
<style>
    td, th {
  border: 1px solid #dddddd;
  text-align: left;
  padding: 8px;
}

tr:nth-child(even) {
  background-color: #dddddd;
}
</style>
@endcomponent
@endslot

{{-- Body --}}
<div style="text-align: center;">
    <p>Hi {!! $storeContact !!}!</p>
    <p>Low stock has been detected for your store {!! $storename !!} from {!! $storecity !!} , {!! $storestate !!}</p>
    <h3>Stock Inventory for For Your Store</h3>
    <div class="row">
    <table>
        <tr>
            <th>Product Name</th>
            <th>Product Plan Name</th>
            <th>Stock Left</th>
         </tr>
        @foreach ($storeInventory as $v)
        <tr>
            <td>{!! $v->product->name !!}</td>
            <td>{!! $v->plan->name !!}</td>
               @if ($v->id == $inventory->id)
                <td style="background-color: #bb3636c7 !important;color:white;">{!! $v->counter !!}</td>
               @else
               <td>{!! $v->counter !!}</td>
               @endif
        </tr>
        @endforeach
    </table>
    </div>
</div>

{{-- Footer --}}
@slot('footer')
@component('mail::footer')
&copy; Copyright {{ now()->year }} Alpha Direct, All Rights Reserved.
@endcomponent
@endslot
@endcomponent
