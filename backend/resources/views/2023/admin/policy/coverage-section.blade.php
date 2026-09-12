
<h3 class="card-title align-items-start flex-column">
		<span class="card-label font-weight-bolder font-size-h4 text-dark-75">Coverages:</span>
</h3>
	<div class="row">
		<table class="table table-striped">
			<thead>
				<tr>
					<th width="20%"><strong>Main</strong></th>  
					<th width="20%"><strong>Coverage Value</strong></th>
					<th width="20%"><strong>Disc/Surcharge</strong></th>
					<th width="20%"><strong>Flat/%</strong></th>
					<th width="20%"><strong>Discount Value</strong></th>
				</tr> 
			</thead>
			<tbody>
			@foreach($coverage as $k=>$v)
				<tr>
					<td width="20%">{{$v->name}}{{Form::hidden('main[]', $v->name,['class'=>'form-control ','id'=>'main_'.$k,"placeholder"=>""])}}</td>  
					<td width="20%">
						{{Form::number('cover_value[]', null,['class'=>'form-control ','id'=>'cover_value_'.$k,"placeholder"=>"Coverage Value"])}}
					</td>
					<td width="20%">
						{{Form::select('type[]',['1'=>'Discount','2'=>'Surcharge'],null,['class'=>'form-control  kt_datepicker_1','id'=>'type_'.$k,'placeholder'=>' --Select-- '])}}
					</td>
					<td width="20%">{{Form::select('disccount_type[]',['1'=>'Flat','2'=>'%'],null,['class'=>'form-control  kt_datepicker_1','id'=>'disccount_type_'.$k,'placeholder'=>' --Select-- '])}}</td>
					<td width="20%">{{Form::number('type_value[]', null,['class'=>'form-control ','id'=>'type_value_'.$k,"placeholder"=>"Discount Value"])}}</td>
				</tr>
			@endforeach
			</tbody>
		</table>
	</div>