@extends('cashback::layouts.master')
@section('breadcrum')
	@include("cashback::".$breadcrum)
@endsection
@section('content')
<div class="kt-portlet kt-portlet--mobile">
	<div class="kt-portlet__body">
		@livewire("cashback::".$tablename)
    </div>
</div>
@endsection
