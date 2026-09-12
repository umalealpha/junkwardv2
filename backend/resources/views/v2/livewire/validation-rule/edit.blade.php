<div>
    <x-slot name="breadcrum">
        <!--begin::Page title-->
        <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
            <!--begin::Title-->
            <h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">Validation Rule</h1>
            <!--end::Title-->
            <!--begin::Breadcrumb-->
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                <!--begin::Item-->
                <li class="breadcrumb-item text-muted">
                    <a href="{{route('admin-dashboard')}}" class="text-muted text-hover-primary">Home</a>
                </li>
                <!--end::Item-->
                <!--begin::Item-->
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-400 w-5px h-2px"></span>
                </li>
                <!--end::Item-->
                <!--begin::Item-->
                <li class="breadcrumb-item text-muted">
                    <a href="{{route('policy')}}" class="text-muted text-hover-primary">Validation Rules</a>
                </li>
                <!--end::Item-->
                <!--begin::Item-->
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-400 w-5px h-2px"></span>
                </li>
                <!--end::Item-->
                <!--begin::Item-->
                <li class="breadcrumb-item text-muted">Add</li>
                <!--end::Item-->
            </ul>
            <!--end::Breadcrumb-->
        </div>
        <!--end::Page title-->
    </x-slot>
    <!--begin::Content container-->
    <div id="kt_app_content_container" class="app-container container-fluid" wire:loading.class="page-loading">
        <!--begin::Page loading(append to body)-->
        <div class="page-loader flex-column bg-dark bg-opacity-25 ">
            <span class="spinner-border text-primary" role="status"></span>
            <span class="text-gray-800 fs-6 fw-semibold mt-5">Loading...</span>
        </div>
        <!--end::Page loading-->
        <form wire:submit.prevent="submit" autocomplete="off">
            <!--begin::Card-->
            <div class="card">
                <!--begin::Card body-->
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-form-input type="text" name="rule_code"  placeholder="Rule Code" wire:model.defer='rule_code'/>
                                <x-form-label for="rule_code" required value="{{ __('Rule Code') }}"/>
                                <x-form-input-error name="rule_code"/>
                            </div>
                        </div>

                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-form-input type="text" name="rule_description"  placeholder="Description of Rule" wire:model.defer='rule_description'/>
                                <x-form-label for="rule_description" required value="{{ __('Description Of Rule') }}"/>
                                <x-form-input-error name="rule_description"/>
                            </div>
                        </div>

                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-select
                                    aria-label="Select Product"
                                    :options="$this->getProduct()"
                                    wire:model.defer="product_id"
                                />
                                <x-form-label for="product_id" required value="{{ __('Select Product') }}"/>
                                <x-form-input-error name="product_id"/>
                            </div>
                        </div>

                        {{--                        <div class="col-sm-3">--}}
                        {{--                            <div class="form-floating mb-3">--}}
                        {{--                                <x-select--}}
                        {{--                                    aria-label="Select Product Type"--}}
                        {{--                                    :options="$this->getProductTypes()"--}}
                        {{--                                    wire:model.defer='product_type'--}}
                        {{--                                />--}}
                        {{--                                <x-form-label for="select_product_type" required value="{{ __('Select Product Type') }}"/>--}}
                        {{--                                <x-form-input-error name="select_product_type"/>--}}
                        {{--                            </div>--}}
                        {{--                        </div>--}}

                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-form-input type="text" name="screen_error_msg"  placeholder="Screen Error Msg" wire:model.defer='screen_error_msg'/>
                                <x-form-label for="screen_error_msg" required value="{{ __('Screen Error Msg') }}"/>
                                <x-form-input-error name="screen_error_msg"/>
                            </div>
                        </div>

                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-select
                                    aria-label="Rule Apply On"
                                    :options="$this->getRulesApplidOn()"
                                    wire:model.defer='rule_apply_on'
                                />
                                <x-form-label for="rule_apply_on" required value="{{ __('Rule Apply On') }}"/>
                                <x-form-input-error name="rule_apply_on"/>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="center text-end">
                            <button class="btn btn-sm btn-primary" wire:click.prevent="addRow({{$i}})">Add Row</button>
                        </div>
                        @foreach($inputs as $key => $value)
                            <div class="row">
                                <div class="col-sm-3">
                                    <div class="form-floating mb-3">
                                        <x-select
                                            aria-label="Select Rule For"
                                            :options="$this->getAlpharicvggroups()"
                                            wire:model.defer='rule_for.{{ $key }}'
                                        />
                                        <x-form-label for="rule_for.{{ $key }}" required value="{{ __('Select Rule For') }}"/>
                                        <x-form-input-error name="rule_for.{{ $key }}"/>
                                    </div>
                                </div>

                                <div class="col-sm-3">
                                    <div class="form-floating mb-3">
                                        <x-select
                                            aria-label="Select Formula Expression"
                                            :options="$this->getFormulaExpression()"
                                            wire:model.defer='formula_expression.{{ $key }}'
                                        />
                                        <x-form-label for="formula_expression.{{ $key }}" required value="{{ __('Select Formula Expression') }}"/>
                                        <x-form-input-error name="formula_expression.{{ $key }}"/>
                                    </div>
                                </div>


                                <div class="col-sm-2">
                                    <div class="form-floating mb-3">
                                        <x-form-input type="text" name="value"  placeholder="Value" wire:model.defer='value.{{ $key }}'/>
                                        <x-form-label for="value.{{ $key }}" required value="{{ __('Value') }}"/>
                                        <x-form-input-error name="value.{{ $key }}"/>
                                    </div>
                                </div>

                                <div class="col-sm-2">
                                    <div class="form-floating mb-3">
                                        <x-form-input type="text" name="value_to"  placeholder="To Value" wire:model.defer='value_to.{{ $key }}'/>
                                        <x-form-label for="value_to.{{ $key }}" required  value="{{ __('Value To') }}"/>
                                        <x-form-input-error name="value_to.{{ $key }}"/>
                                    </div>
                                </div>

                                <div class="col-sm-1">
                                    <button type="button" class="btn btn-danger btn-small" wire:click.prevent="remove({{$key}})">&times;</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <hr>

                    <div class="row">
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-select
                                    aria-label="Can Rate Policy"
                                    :options="$this->getYesNoArray()"
                                    wire:model.defer='can_rate_policy'
                                />
                                <x-form-label for="can_rate_policy" required value="{{ __('Can Rate Policy') }}"/>
                                <x-form-input-error name="can_rate_policy"/>
                            </div>
                        </div>

                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-select
                                    aria-label="Can Print Quote"
                                    :options="$this->getYesNoArray()"
                                    wire:model.defer='can_print_quote'
                                />
                                <x-form-label for="can_print_quote" required value="{{ __('Can Print Quote') }}"/>
                                <x-form-input-error name="can_print_quote"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-select
                                    aria-label="Can Print Application"
                                    :options="$this->getYesNoArray()"
                                    wire:model.defer='can_print_application'
                                />
                                <x-form-label for="can_print_application" required value="{{ __('Can Print Application') }}"/>
                                <x-form-input-error name="can_print_application"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-select
                                    aria-label="Can Bind Application"
                                    :options="$this->getYesNoArray()"
                                    wire:model.defer='can_bind_application'
                                />
                                <x-form-label for="can_bind_application" required value="{{ __('Can Bind Application') }}"/>
                                <x-form-input-error name="can_bind_application"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-select
                                    aria-label="Can Submit Un Bound Application"
                                    :options="$this->getYesNoArray()"
                                    wire:model.defer='can_submit_un_bound_application'
                                />
                                <x-form-label for="can_submit_un_bound_application" required value="{{ __('Can Submit Un Bound Application') }}"/>
                                <x-form-input-error name="can_submit_un_bound_application"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-select
                                    aria-label="Can Issue Policy"
                                    :options="$this->getYesNoArray()"
                                    wire:model.defer='can_issue_policy'
                                />
                                <x-form-label for="can_issue_policy" required value="{{ __('Can Issue Policy') }}"/>
                                <x-form-input-error name="can_issue_policy"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-form-date type="text" class="kt_datepicker_1" id="rule_start_date"  name="rule_start_date"  placeholder="Rule Start Date" wire:model.defer='rule_start_date'/>
                                <x-form-label for="rule_start_date" required value="{{ __('Rule Start Date') }}"/>
                                <x-form-input-error name="rule_start_date"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-form-date type="text" class="kt_datepicker_1" id="rule_end_date" name="rule_end_date" placeholder="Rule End Date" wire:model.defer='rule_end_date'/>
                                <x-form-label for="rule_end_date" required value="{{ __('Rule End Date') }}"/>
                                <x-form-input-error name="rule_end_date"/>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-floating mb-3">
                                <x-check-box label="{{ __('Rule Activate') }}" id="rule_activate" wire:model.defer="rules_status"/>
                                <x-form-input-error name="rule_activate"/>
                            </div>
                        </div>

                    </div>
                </div>
                <!--end::Card body-->
                <div class="card-footer text-center">
                    <input type="submit" class="btn btn-primary" value="Submit">
                    <a href="{{ route('validationrule') }}" class="btn btn-secondary" value="Cancel">Cancel</a>
                </div>
            </div>
            <!--end::Card-->
        </form>
    </div>
    <!--end::Content container-->
</div>
