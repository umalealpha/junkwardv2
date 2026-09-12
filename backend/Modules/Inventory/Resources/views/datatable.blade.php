@extends('inventory::layouts.master')
@section('breadcrum')
	@include("inventory::".$breadcum)
@endsection
@section('content')
<div class="kt-portlet kt-portlet--mobile">
	<div class="kt-portlet__body">
		@livewire("inventory::".$tablename)
    </div>
</div>
@endsection
