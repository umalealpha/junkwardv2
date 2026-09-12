<div>
    <div class="avatar-upload">
        <div class="avatar-edit">
            <input type='file' name="{{$this->imageField}}" id="imageUpload_{{$this->imageField}}"/>
            <label for="imageUpload_{{$this->imageField}}">
                <i class="fa fa-pen" aria-hidden="true" title="Customer data edit" style="margin: 8px;"></i>
            </label>

            @if(isset($this->imageExist) && $this->imageExist != null)
                <button type="button"
                        onclick="deleteImage('{{ route('admin.image.delete') }}', '{{ $this->imageField }}', '{{ $this->imageExist }}', '{{ $this->customer }}')"
                        style="border: none; background: transparent; cursor: pointer;" title="Delete Image">
                    <i class="fa fa-trash" aria-hidden="true" style="margin: 8px; color: red;"></i>
                </button>
            @endif
        </div>

        @if(isset($this->imageExist))
            @if($this->imageExist != null)
                <a id="linkImagePreview_{{$this->imageField}}" href="{!! \AlphaDirect\Helper::getCloudFrontURL($this->imageExist) . '?v=' . time() !!}" target="_blank">
                    <div class="avatar-preview">
                        @php $ext = pathinfo($this->imageExist, PATHINFO_EXTENSION); @endphp
                        @if ($ext === 'pdf')
                            <img id="imagePreview_{{$this->imageField}}" src="{{ asset('v2/image-plugin/image/pdf.png') }}">
                        @elseif (in_array($ext, ['docx', 'doc', 'docm']))
                            <img id="imagePreview_{{$this->imageField}}" src="{{ asset('v2/image-plugin/image/word.png') }}">
                        @elseif ($ext === 'txt')
                            <img id="imagePreview_{{$this->imageField}}" src="{{ asset('v2/image-plugin/image/text.jpg') }}">
                        @elseif (in_array($ext, ['xls', 'xlsx', 'csv']))
                            <img id="imagePreview_{{$this->imageField}}" src="{{ asset('v2/image-plugin/image/excel.png') }}">
                        @else
                            <img id="imagePreview_{{$this->imageField}}" src="{!! \AlphaDirect\Helper::getCloudFrontURL($this->imageExist) . '?v=' . time() !!}">
                        @endif
                    </div>
                </a>
            @else
                <a id="linkImagePreview_{{$this->imageField}}" href="{{ asset('v2/image-plugin/image/avatar.jpg') }}" target="_blank">
                    <div class="avatar-preview">
                        <img id="imagePreview_{{$this->imageField}}" src="{{ asset('v2/image-plugin/image/avatar.jpg') }}">
                    </div>
                </a>
            @endif
        @else
            <a id="linkImagePreview_{{$this->imageField}}" href="{{ asset('v2/image-plugin/image/avatar.jpg') }}" target="_blank">
                <div class="avatar-preview">
                    <img id="imagePreview_{{$this->imageField}}" src="{{ asset('v2/image-plugin/image/avatar.jpg') }}">
                </div>
            </a>
        @endif
    </div>
    <div class="ml-4 mt-1">{{ $this->imageTitle }}</div>
</div>

@push('scripts')
<script>
    $("#imageUpload_{{$this->imageField}}").change(function () {
        readImageUploadComponent(this, "imagePreview_{{$this->imageField}}", "imageUpload_{{$this->imageField}}", "linkImagePreview_{{$this->imageField}}");
    });

    function deleteImage(route, field, path, customerId) {
        if (!confirm('Are you sure you want to delete this file?')) return;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = route;

        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_token';
        csrf.value = '{{ csrf_token() }}';
        form.appendChild(csrf);

        const method = document.createElement('input');
        method.type = 'hidden';
        method.name = '_method';
        method.value = 'DELETE';
        form.appendChild(method);

        const input1 = document.createElement('input');
        input1.type = 'hidden';
        input1.name = 'imageField';
        input1.value = field;
        form.appendChild(input1);

        const input2 = document.createElement('input');
        input2.type = 'hidden';
        input2.name = 'imagePath';
        input2.value = path;
        form.appendChild(input2);

        const input3 = document.createElement('input');
        input3.type = 'hidden';
        input3.name = 'customer_id';
        input3.value = customerId;
        form.appendChild(input3);

        document.body.appendChild(form);
        form.submit();
    }
</script>
@endpush
