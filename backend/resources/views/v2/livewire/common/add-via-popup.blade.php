<div>
    <a href="#" class="btn btn-icon btn-sm btn-success flex-shrink-0 ms-4" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}">
        <span class="svg-icon svg-icon-2">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect opacity="0.5" x="11.364" y="20.364" width="16" height="2" rx="1" transform="rotate(-90 11.364 20.364)" fill="currentColor" />
                <rect x="4.36396" y="11.364" width="16" height="2" rx="1" fill="currentColor" />
            </svg>
        </span>
    </a>

    <div>
        <div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog ">
                <div class="modal-content modal-rounded">
                    <div class="modal-header py-7 d-flex justify-content-between">
                        <h2>{{ $title }}</h2>
                        <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal" wire:click="$emitUp('refreshParent')">
                            <span class="svg-icon svg-icon-1">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="currentColor" />
                                    <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="currentColor" />
                                </svg>
                            </span>
                        </div>
                    </div>
                    {{-- <div class="modal-body scroll-y m-5"> --}}
                        <div >
                            @livewire($componentName,['colSize'=>'col-md-12','modalId'=>$modalId])
                        </div>
                    {{-- </div> --}}
                </div>
            </div>
        </div>
    </div>

    <script>
        window.addEventListener('closeModal', event => {
            $('#{{ $modalId }}').modal('hide');
        })
    </script>
</div>
