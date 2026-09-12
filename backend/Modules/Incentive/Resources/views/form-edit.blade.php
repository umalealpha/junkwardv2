@extends('incentive::layouts.master')
@section('breadcrum')
	@include("incentive::".$breadcrum)
@endsection
@section('content')
<div class="kt-portlet kt-portlet--mobile">
	<div class="kt-portlet__body">
		@livewire("incentive::".$tablename,  ["data"=>$data])
    </div>
</div>
@endsection
