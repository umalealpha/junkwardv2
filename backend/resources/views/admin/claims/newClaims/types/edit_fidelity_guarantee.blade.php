<h3>Give the name of defaulting employees and their respective positions:</h3>
<input type="hidden" name="fg_id" value="{{ $fg->id }}" />
<div class="row">
    <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input">Claim Sub Type</label>
            <select class="form-control" id="ClaimSubType" name="ClaimSubType">
                @foreach($claimSubType as $type)
                    <option value="{{$type->id}}" {{ $type->id == $newclaim->claim_sub_type_id ? 'selected' : '' }}>{{$type->value}}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="form-group">
                <label for="example-text-input">Defaulting Employees Name & Position</label>
            @php $plus = 0; @endphp
                @if(isset($fg->defaulting_employees_name) && $fg->defaulting_employees_name != null)
                    @foreach($fg->defaulting_employees_name as $name => $position)
                    <div class="form-group row">
                        <input type="text" class="form-control col-6" name="defaulting_employees_name[]" value="{{ $name }}" placeholder="Enter Defaulting Employees Name">
                        <input type="text" class="form-control col-5" name="defaulting_employees_position[]" value="{{ $position }}" placeholder="Enter Defaulting Employees Position">
                        @if($plus == 0 )
                            <a href="javascript:void(0);" class="add_button col-1" title="Add field"><i class="fas fa-plus" style="font-size:23px;"></i></a>
                        @else
                            <a href="javascript:void(0);" class="remove_button col-1" title="Add field"><i class="fas fa-minus" style="font-size:23px;  color:red;"></i></a>
                        @endif
            @php $plus++; @endphp
                    </div>
                    @endforeach
                @else
                    <div class="form-group row">
                        <input type="text" class="form-control col-6" name="defaulting_employees_name[]" value="" placeholder="Enter Defaulting Employees Name">
                        <input type="text" class="form-control col-5" name="defaulting_employees_position[]" value="" placeholder="Enter Defaulting Employees Position">
                        <a href="javascript:void(0);" class="add_button col-1" title="Add field"><i class="fas fa-plus" style="font-size:23px;"></i></a>
                    </div>
                @endif
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input">Have the employees been involved in or been suspected of any previous loss?</label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="employees_been_involved" {{ $fg->employees_been_involved == 1 ? 'checked' : '' }}   class="form-control condition" value="1">Yes<span></span>
            </label>
            <label class="kt-radio" style="margin-left: 10px;">
                <input type="radio" name="employees_been_involved"  {{ $fg->employees_been_involved == 0 ? 'checked' : '' }} class="form-control  condition"
                       value="0">No<span></span>
            </label>
        </div>
    </div>
    {{-- <div class="col-lg-6">
        <div class="form-group">
            <label for="example-text-input">Give full details of the circumstances of the loss and how it was discovered.</label>
            <textarea type="text" class="form-control" name="circumstances"   placeholder="Give full details of the circumstances of the
                loss and how it was discovered.">{{ $fg->circumstances }}</textarea>
        </div>
    </div> --}}
</div>
