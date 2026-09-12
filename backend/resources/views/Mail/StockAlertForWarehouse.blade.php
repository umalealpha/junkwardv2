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
    <p>Hi {!! $warehouseContact !!}!</p>
    <h3>Inventory For Your Warehouse</h3>
    <div class="row">
    <table>
        <tr>
            <th>Product Name</th>
            <th>Product Plan Name</th>
            <th>Stock Left</th>
         </tr>
        @foreach ($warehouseInventory as $stock)
        <tr>
            <td>{!! $stock->product->name !!}</td>
            <td>{!! $stock->plan->name !!}</td>
               @if ($stock->id == $inventory->id)
                <td style="background-color: #bb3636c7 !important;color:white;">{!! $stock->counter !!}</td>
               @else
               <td>{!! $stock->counter !!}</td>
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
