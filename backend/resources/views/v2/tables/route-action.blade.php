
<div class="">
	@foreach($action as $k=>$v)
		@if(isset($v['href']))
			<a href="{{$v['href']}}" @if(isset($v['target'])) target="{{$v['target']}}" @endif class="btn btn-outline btn-sm btn-outline-dashed hover-scale btn-outline-primary btn-active-light-primary">{{strtoupper($k)}}</a>
        @elseif(isset($v['fun']))
            <a wire:click="{{$v['fun']}}({{ $v['arg'] ?? '' }})" class="btn btn-outline btn-sm hover-scale btn-outline-dashed btn-outline-success btn-active-light-primary">{{strtoupper($k)}}</a>
        @elseif(isset($v['onClick']))
            <a onClick="{{$v['onClick']}}({{ $v['arg'] ?? '' }})" class="btn btn-outline btn-sm hover-scale btn-outline-dashed btn-outline-success btn-active-light-primary">{{strtoupper($k)}}</a>
        @else
			<a href="#" class="btn btn-outline btn-sm btn-outline-dashed hover-scale btn-outline-warning btn-active-light-primary">{{strtoupper($k)}}</a>
		@endif
	@endforeach
</div>
