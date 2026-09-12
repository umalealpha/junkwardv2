<div class="kt-portlet__head">
    <div class="kt-portlet__head-label">
        <h3 class="kt-portlet__head-title">
            Recipient KYC
        </h3>
    </div>
</div>
<div class="kt-portlet_body">
    <div class="kt-section">
        <div class="kt-section__content" style="padding-left:20px; padding-right: 20px;">
            <table class="table table-striped m-table">
                <tbody>
                <div class="form-group row">
                    <div class="col-md-2">
                        <h3 class="col-form-label" style="float: left;">Driving License</h3>
                        <div class="kt-avatar" id="driving_license" style="float: left; clear: left;">
                            @if($recipientKyc && $recipientKyc->driving_license == NULL)
                                <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                            @else
                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($recipientKyc->driving_license) !!}" target="_blank" download>
                                <div class="kt-avatar__holder" style="background-image: url({!! \AlphaDirect\Helper::getCloudFrontURL($recipientKyc->driving_license) !!})"></div>
                                </a>
                            @endif

                            <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="Cancel Image"> <i class="fa fa-times"></i> </span>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <h3 class="col-form-label" style="float: left;">Omang ID</h3>
                        <div class="kt-avatar" id="omang_pic" style="float: left; clear: left;">
                            @if($recipientKyc && $recipientKyc->omang == NULL)
                                <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                            @else
                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($recipientKyc->omang) !!}" target="_blank" download>
                                <div class="kt-avatar__holder" style="background-image: url({!! \AlphaDirect\Helper::getCloudFrontURL($recipientKyc->omang) !!})"></div>
                                </a>
                            @endif

                        </div>
                    </div>
                    <div class="col-md-2">
                        <h3 class="col-form-label" style="float: left;">Proof of Residence</h3>
                        <div class="kt-avatar" id="proof_residence" style="float: left; clear: left;">
                            @if($recipientKyc && $recipientKyc->proof_residence == NULL)
                                <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                            @else
                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($recipientKyc->proof_residence) !!}" target="_blank" download>
                                    <div class="kt-avatar__holder" style="background-image: url({!! \AlphaDirect\Helper::getCloudFrontURL($recipientKyc->proof_residence) !!})"></div>
                                </a>

                            @endif

                        </div>
                    </div>
                    <div class="col-md-2">
                        <h3 class="col-form-label" style="float: left;">Proof Of Income</h3>
                        <div class="kt-avatar" id="proof_income" style="float: left; clear: left;">
                            @if($recipientKyc && $recipientKyc->proof_income == NULL)
                                <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                            @else
                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($recipientKyc->proof_income) !!}" target="_blank" download>
                                    <div class="kt-avatar__holder" style="background-image: url({!! \AlphaDirect\Helper::getCloudFrontURL($recipientKyc->proof_income) !!})"></div>
                                </a>
                            @endif
                        </div>
                    </div>

                    <div class="col-md-2">
                        <h3 class="col-form-label" style="float: left;">Passport</h3>
                        <div class="kt-avatar" id="passport_pic" style="float: left; clear: left;">
                            @if($recipientKyc && $recipientKyc->passport == NULL)
                                <div class="kt-avatar__holder" style="background-image: url(https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Place_holder_image/200x200.png)"></div>
                            @else
                                <a href="{!! \AlphaDirect\Helper::getCloudFrontURL($recipientKyc->passport) !!}" target="_blank" download>
                                <div class="kt-avatar__holder" style="background-image: url({!! \AlphaDirect\Helper::getCloudFrontURL($recipientKyc->passport) !!})"></div>
                                </a>
                            @endif

                        </div>
                    </div>
                </div>
                </tbody>
            </table>
        </div>
    </div>
</div>