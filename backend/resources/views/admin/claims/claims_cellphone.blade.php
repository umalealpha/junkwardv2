<div class="kt-portlet">
      <div class="kt-portlet__head">
          <div class="kt-portlet__head-label">
              <h3 class="kt-portlet__head-title">
                   Cellphone Device Details
              </h3>
          </div>
      </div>
      <div class="kt-portlet_body">
                    <div class="kt-section">
                        <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                            <table class="table table-striped m-table">
                                <tbody>
                                <tr>
                                    <th>Device status</th>
                                        @if($claimCellphone->damage_extent != null)
                                        <td>{!! $claimCellphone->damage_extent !!}</td>
                                        @else
                                            <td>-</td>
                                        @endif

                                    <th>Date of Loss</th>
                                        @if($claimCellphone->lossDate != null)
                                            <td> {!!\Carbon\Carbon::createFromFormat('Y-m-d', $claimCellphone->lossDate)->format('d-m-Y')  !!}</td>
                                        @else
                                         <td>-</td>
                                        @endif

                                        
                                </tr>

                                <tr>
                                    <th>Device</th>
                                    @if($claimCellphone->mileage != null)
                                        <td> {!! $claimCellphone->mileage !!}</td>
                                    @else
                                        <td>-</td>
                                    @endif

                                    <th>Date Of Claim Registered</th>
                                    @if ($claims->registered_claim)
                                        <td>{!!  \Carbon\Carbon::createFromFormat('Y-m-d', $claims->registered_claim)->format('d-m-Y')  !!}</td>
                                    @else
                                        <td>N/A</td>
                                    @endif


                                </tr>
                                {{-- <tr>
                                    <th>Front Image</th>
                                    <td>
                                        <div class="col-md-4">
                                           <div class="kt-avatar" style="float: left; clear: left;">
                                                @if($claimCellphone->front == NULL)
                                                    <div class="kt-avatar__holder"
                                                        style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                    </div>
                                                @else
                                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claimCellphone->front) !!}" target="_blank" download>
                                                    <div class="kt-avatar__holder"
                                                        style="background-image:url({{ \AlphaDirect\Helper::getCloudFrontURL($claimCellphone->front) }}) !important;">
                                                    </div>
                                                </a>
                                                @endif
                                           </div>
                                        </div>
                                    </td>
                                    <th>Back Image</th>
                                    <td>
                                        <div class="col-md-4">
                                            <div class="kt-avatar" style="float: left; clear: left;">
                                                @if($claimCellphone->back == NULL)
                                                    <div class="kt-avatar__holder"
                                                        style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                    </div>
                                                @else
                                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claimCellphone->back) !!}" target="_blank" download>
                                                    <div class="kt-avatar__holder"
                                                        style="background-image:url({{ \AlphaDirect\Helper::getCloudFrontURL($claimCellphone->back) }}) !important;">
                                                    </div>
                                                </a>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <th>left Image</th>
                                    <td>
                                        <div class="col-md-4">
                                            <div class="kt-avatar" style="float: left; clear: left;">
                                                @if($claimCellphone->left == NULL)
                                                    <div class="kt-avatar__holder"
                                                        style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                    </div>
                                                @else
                                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claimCellphone->left) !!}" target="_blank" download>
                                                    <div class="kt-avatar__holder"
                                                        style="background-image:url({{ \AlphaDirect\Helper::getCloudFrontURL($claimCellphone->left) }}) !important;">
                                                    </div>
                                                </a>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                </tr> --}}

                                {{-- <tr>
                                    <th>Right Image</th>
                                    <td>
                                        <div class="col-md-4">
                                            <div class="kt-avatar" style="float: left; clear: left;">
                                                @if($claimCellphone->right == NULL)
                                                    <div class="kt-avatar__holder"
                                                        style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                    </div>
                                                @else
                                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claimCellphone->right) !!}" target="_blank" download>
                                                    <div class="kt-avatar__holder"
                                                        style="background-image:url({{ \AlphaDirect\Helper::getCloudFrontURL($claimCellphone->right) }}) !important;">
                                                    </div>
                                                </a>
                                                @endif
                                           </div>
                                        </div>
                                    </td>
                                    <th>Top Image</th>
                                    <td>
                                        <div class="col-md-4">
                                            <div class="kt-avatar" style="float: left; clear: left;">
                                                @if($claimCellphone->top == NULL)
                                                    <div class="kt-avatar__holder"
                                                        style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                    </div>
                                                @else
                                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claimCellphone->top) !!}" target="_blank" download>
                                                    <div class="kt-avatar__holder"
                                                        style="background-image:url({{ \AlphaDirect\Helper::getCloudFrontURL($claimCellphone->top) }}) !important;">
                                                    </div>
                                                </a>
                                                @endif
                                           </div>
                                        </div>
                                    </td>
                                    <th>Bottom Image</th>
                                    <td>
                                       <div class="col-md-4">
                                          <div class="kt-avatar" style="float: left; clear: left;">
                                                @if($claimCellphone->bottom == NULL)
                                                    <div class="kt-avatar__holder"
                                                        style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                    </div>
                                                @else
                                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claimCellphone->bottom) !!}" target="_blank" download>
                                                    <div class="kt-avatar__holder"
                                                        style="background-image:url({{ \AlphaDirect\Helper::getCloudFrontURL($claimCellphone->bottom) }}) !important;">
                                                    </div>
                                                </a>
                                                @endif
                                         </div>
                                      </div>
                                    </td>
                                </tr> --}}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                {{-- ///////////////////// --}}
                <form id="storeClaim" action="{{ route('admin.claims.update',$claims->id) }}" method="POST" enctype="multipart/form-data">
                    {{ method_field('PATCH') }}
                    {{csrf_field()}}
                    <div class="kt-portlet_body">
                        <div class="kt-section">
                            <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                                <div class="form-group row">
                                    <div class="col-md-2">
                                        <h3 class="col-form-label" style="float: left;">Front Image</h3>
                                         <div class="kt-avatar"
                                             style="float: left; clear: left;">
                                             @if($claimCellphone->front == NULL)
                                                 <div class="kt-avatar__holder"
                                                     style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                 </div>
                                             @else
                                             <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claimCellphone->bottom) !!}" target="_blank" download>
                                                 <div class="kt-avatar__holder"
                                                     style="background-image:url({{ \AlphaDirect\Helper::getCloudFrontURL($claimCellphone->front) }}) !important;">
                                                 </div>
                                             </a>
                                             @endif
                                             <label class="kt-avatar__upload"
                                                 data-toggle="kt-tooltip" title="Left">
                                                 <i class="fa fa-pen"></i>
                                                 <input type='file' class="cell_phone_front" name="cell_phone_front"
                                                 <?php echo config('app.accept_attr'); ?>
                                                     <?php echo config('app.accept_msg'); ?> />
                                             </label>
                                             <span class="kt-avatar__cancel" data-toggle="kt-tooltip"
                                                 title="Cancel Image"> <i class="fa fa-times"></i>
                                         </span>
                                         </div>
                                    </div>

                                    <div class="col-md-2">
                                        <h3 class="col-form-label" style="float: left;">Back Image</h3>
                                         <div class="kt-avatar"
                                             style="float: left; clear: left;">
                                             @if($claimCellphone->back == NULL)
                                                 <div class="kt-avatar__holder"
                                                     style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                 </div>
                                             @else
                                             <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claimCellphone->back) !!}" target="_blank" download>
                                                 <div class="kt-avatar__holder"
                                                     style="background-image:url({{ \AlphaDirect\Helper::getCloudFrontURL($claimCellphone->back) }}) !important;">
                                                 </div>
                                             </a>   
                                             @endif
                                             <label class="kt-avatar__upload"
                                                 data-toggle="kt-tooltip" title="Left">
                                                 <i class="fa fa-pen"></i>
                                                 <input type='file' class="cell_phone_back" name="cell_phone_back"
                                                 <?php echo config('app.accept_attr'); ?>
                                                     <?php echo config('app.accept_msg'); ?> />
                                             </label>
                                             <span class="kt-avatar__cancel" data-toggle="kt-tooltip"
                                                 title="Cancel Image"> <i class="fa fa-times"></i>
                                         </span>
                                         </div>
                                    </div>


                                    <div class="col-md-2">
                                        <h3 class="col-form-label" style="float: left;">Left Image</h3>
                                         <div class="kt-avatar"
                                             style="float: left; clear: left;">
                                             @if($claimCellphone->left == NULL)
                                                 <div class="kt-avatar__holder"
                                                     style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                 </div>
                                             @else
                                             <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claimCellphone->left) !!}" target="_blank" download>
                                                 <div class="kt-avatar__holder"
                                                     style="background-image:url({{ \AlphaDirect\Helper::getCloudFrontURL($claimCellphone->left) }}) !important;">
                                                 </div>
                                             </a>
                                             @endif
                                             <label class="kt-avatar__upload"
                                                 data-toggle="kt-tooltip" title="Left">
                                                 <i class="fa fa-pen"></i>
                                                 <input type='file' class="cell_phone_left" name="cell_phone_left"
                                                 <?php echo config('app.accept_attr'); ?>
                                                     <?php echo config('app.accept_msg'); ?> />
                                             </label>
                                             <span class="kt-avatar__cancel" data-toggle="kt-tooltip"
                                                 title="Cancel Image"> <i class="fa fa-times"></i>
                                         </span>
                                         </div>
                                    </div>

                                    <div class="col-md-2">
                                        <h3 class="col-form-label" style="float: left;">Right Image</h3>
                                         <div class="kt-avatar"
                                             style="float: left; clear: left;">
                                             @if($claimCellphone->right == NULL)
                                                 <div class="kt-avatar__holder"
                                                     style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                 </div>
                                             @else
                                             <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claimCellphone->right) !!}" target="_blank" download>
                                                 <div class="kt-avatar__holder"
                                                     style="background-image:url({{ \AlphaDirect\Helper::getCloudFrontURL($claimCellphone->right) }}) !important;">
                                                 </div>
                                             </a>
                                             @endif
                                             <label class="kt-avatar__upload"
                                                 data-toggle="kt-tooltip" title="Left">
                                                 <i class="fa fa-pen"></i>
                                                 <input type='file' class="cell_phone_right" name="cell_phone_right"
                                                 <?php echo config('app.accept_attr'); ?>
                                                     <?php echo config('app.accept_msg'); ?> />
                                             </label>
                                             <span class="kt-avatar__cancel" data-toggle="kt-tooltip"
                                                 title="Cancel Image"> <i class="fa fa-times"></i>
                                         </span>
                                         </div>
                                    </div>

                                    <div class="col-md-2">
                                        <h3 class="col-form-label" style="float: left;">Top Image</h3>
                                         <div class="kt-avatar"
                                             style="float: left; clear: left;">
                                             @if($claimCellphone->top == NULL)
                                                 <div class="kt-avatar__holder"
                                                     style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                 </div>
                                             @else
                                             <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claimCellphone->top) !!}" target="_blank" download>
                                                 <div class="kt-avatar__holder"
                                                     style="background-image:url({{ \AlphaDirect\Helper::getCloudFrontURL($claimCellphone->top) }}) !important;">
                                                 </div>
                                             </a>   
                                             @endif
                                             <label class="kt-avatar__upload"
                                                 data-toggle="kt-tooltip" title="Left">
                                                 <i class="fa fa-pen"></i>
                                                 <input type='file' class="cell_phone_top" name="cell_phone_top"
                                                 <?php echo config('app.accept_attr'); ?>
                                                     <?php echo config('app.accept_msg'); ?> />
                                             </label>
                                             <span class="kt-avatar__cancel" data-toggle="kt-tooltip"
                                                 title="Cancel Image"> <i class="fa fa-times"></i>
                                         </span>
                                         </div>
                                    </div>
                                    <div class="col-md-2">
                                        <h3 class="col-form-label" style="float: left;">Bottom Image</h3>
                                         <div class="kt-avatar"
                                             style="float: left; clear: left;">
                                             @if($claimCellphone->bottom == NULL)
                                                 <div class="kt-avatar__holder"
                                                     style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)">
                                                 </div>
                                             @else
                                             <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($claimCellphone->bottom) !!}" target="_blank" download>
                                                 <div class="kt-avatar__holder"
                                                     style="background-image:url({{ \AlphaDirect\Helper::getCloudFrontURL($claimCellphone->bottom) }}) !important;">
                                                 </div>
                                             </a>
                                             @endif
                                             <label class="kt-avatar__upload"
                                                 data-toggle="kt-tooltip" title="Left">
                                                 <i class="fa fa-pen"></i>
                                                 <input type='file' class="cell_phone_bottom" name="cell_phone_bottom"
                                                 <?php echo config('app.accept_attr'); ?>
                                                     <?php echo config('app.accept_msg'); ?> />
                                             </label>
                                             <span class="kt-avatar__cancel" data-toggle="kt-tooltip"
                                                 title="Cancel Image"> <i class="fa fa-times"></i>
                                         </span>
                                         </div>
                                    </div>
                                    @if ($claims && $claims->status != 'Closed')
                                        <div class="kt-repeater__add-data" style="padding-left:20px; padding-right: 20px;">
                                            <button type="submit" class="btn btn-primary">Update Device Images</button>
                                        </div>
                                    @endif
                                </div>                              
                            </div>
                        </div>
                    </div>
                </form>
            
                {{-- //////////////////// --}}

      <div class="kt-separator kt-separator--space-sm kt-separator--border-dashed"></div>
      <div class="kt-portlet__head">
                    <div class="kt-portlet__head-label">
                        <h3 class="kt-portlet__head-title">
                            For Theft or Loss :
                        </h3>
                    </div>
                </div>
      <div class="kt-portlet_body">
                    <div class="kt-section">
                        <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
                            <table class="table table-striped m-table">
                                <tbody>

                                {{-- <tr>
                                    <th>Police Station :</th>
                                        @if($claimCellphone->police_station != null)
                                        <td> {!! $claimCellphone->police_station !!}</td>
                                        @else
                                            <td>-</td>
                                        @endif

                                    <th>Case Number :</th>
                                        @if($claimCellphone->case_number != null)
                                            <td>{!! $claimCellphone->case_number !!}</td>
                                        @else
                                            <td>-</td>
                                        @endif
                                </tr> --}}
                                <tr>
                                     <th>Contact Number:</th>
                                        @if($claimCellphone->contact_number != null)
                                        <td> {!! $claimCellphone->contact_number !!}</td>
                                        @else
                                        <td>-</td>
                                        @endif

                                    {{-- <th>ITC Reference Number:</th>

                                        @if($claimCellphone->ITC_reference_number != null)
                                        <td>{!! $claimCellphone->ITC_reference_number !!} </td>
                                        @else
                                            <td>-</td>
                                        @endif --}}
                                        <th>Date Reported To Alpha:</th>
                                        @if($claimCellphone->date_reported_to_alpha != null)
                                        <td> {!!  \Carbon\Carbon::createFromFormat('Y-m-d', $claimCellphone->date_reported_to_alpha)->format('d-m-Y')  !!} </td>
                                        @else
                                        <td>-</td>
                                        @endif
                                    
                                </tr>
                                <tr>
                                    

                                    <th>Description of Loss:</th>
                                        @if($claimCellphone->descriptionofLoss != null)
                                            <td> {{ $claimCellphone->descriptionofLoss }} </td>
                                        @else
                                            <td>N/A</td>
                                        @endif

                                </tr>

                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
</div>



