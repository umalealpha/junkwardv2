<br><br>
<h3 class="card-title align-items-start flex-column">
    <span class="card-label font-weight-bolder font-size-h4 text-dark-75">Customer KYC:</span>
</h3>

<div class="form-group row mt-3">
    <label class="col-lg-1 col-form-label text-lg-right">Driving License:</label>
    <div class="col-lg-2">
        <div class="image-input" id="kt_image_7">
            @if(isset($kyc) && !empty($kyc) && $kyc->driving_license!="")
            <div class="image-input-wrapper"
                style="background-image: url({!! \Helper::getCloudFrontURL($kyc->driving_license) !!})"></div>
            <label class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                data-action="change" title="" data-original-title="Change avatar">
                <i class="fa fa-pen icon-sm text-muted"></i>
                <input type="file" name="driving_license" accept=".png, .jpg, .jpeg" />
            </label>
            <span class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                data-action="cancel" title="Cancel avatar">
                <i class="ki ki-bold-close icon-xs text-muted"></i>
            </span>
            @else
            <div class="image-input-wrapper" style="background-image: url({{ asset('/img/200x200.png')}})"></div>
            <label class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                data-action="change" title="" data-original-title="Change avatar">
                <i class="fa fa-pen icon-sm text-muted"></i>
                <input type="file" name="driving_license" accept=".png, .jpg, .jpeg" />
            </label>
            <span class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                data-action="cancel" title="Cancel avatar">
                <i class="ki ki-bold-close icon-xs text-muted"></i>
            </span>
            @endif
        </div>
    </div>
    <label class="col-lg-1 col-form-label text-lg-right">National ID:</label>
    <div class="col-lg-2">
        <div class="image-input" id="kt_image_8">
            @if(isset($kyc) && !empty($kyc) && $kyc->omang_front!="")
            <div class="image-input-wrapper"
                style="background-image: url({!! \Helper::getCloudFrontURL($kyc->omang_front) !!})"></div>
            <label class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                data-action="change" title="" data-original-title="Change avatar">
                <i class="fa fa-pen icon-sm text-muted"></i>
                <input type="file" name="omang" accept=".png, .jpg, .jpeg" />
            </label>
            <span class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                data-action="cancel" title="Cancel avatar">
                <i class="ki ki-bold-close icon-xs text-muted"></i>
            </span>
            @else
            <div class="image-input-wrapper" style="background-image: url({{ asset('/img/200x200.png')}})"></div>
            <label class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                data-action="change" title="" data-original-title="Change avatar">
                <i class="fa fa-pen icon-sm text-muted"></i>
                <input type="file" name="omang" accept=".png, .jpg, .jpeg" />
            </label>
            <span class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                data-action="cancel" title="Cancel avatar">
                <i class="ki ki-bold-close icon-xs text-muted"></i>
            </span>
            @endif
        </div>
    </div>
    <label class="col-lg-1 col-form-label text-lg-right">Proof of Residence:</label>
    <div class="col-lg-2">
        <div class="image-input" id="kt_image_9">
            @if(isset($kyc) && !empty($kyc) && $kyc->proof_residence!="")
            <div class="image-input-wrapper"
                style="background-image: url({!! \Helper::getCloudFrontURL($kyc->proof_residence) !!})"></div>
            <label class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                data-action="change" title="" data-original-title="Change avatar">
                <i class="fa fa-pen icon-sm text-muted"></i>
                <input type="file" name="proof_residence" accept=".png, .jpg, .jpeg" />
            </label>
            <span class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                data-action="cancel" title="Cancel avatar">
                <i class="ki ki-bold-close icon-xs text-muted"></i>
            </span>
            @else
            <div class="image-input-wrapper" style="background-image: url({{ asset('/img/200x200.png')}})"></div>
            <label class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                data-action="change" title="" data-original-title="Change avatar">
                <i class="fa fa-pen icon-sm text-muted"></i>
                <input type="file" name="proof_residence" accept=".png, .jpg, .jpeg" />
            </label>
            <span class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                data-action="cancel" title="Cancel avatar">
                <i class="ki ki-bold-close icon-xs text-muted"></i>
            </span>
            @endif
        </div>
    </div>
    <label class="col-lg-1 col-form-label text-lg-right">Proof of Income:</label>
    <div class="col-lg-2">
        <div class="image-input" id="kt_image_10">
            @if(isset($kyc) && !empty($kyc) && $kyc->proof_income!="")
            <div class="image-input-wrapper"
                style="background-image: url({!! \Helper::getCloudFrontURL($kyc->proof_income) !!})"></div>
            <label class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                data-action="change" title="" data-original-title="Change avatar">
                <i class="fa fa-pen icon-sm text-muted"></i>
                <input type="file" name="proof_income" accept=".png, .jpg, .jpeg" />
            </label>
            <span class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                data-action="cancel" title="Cancel avatar">
                <i class="ki ki-bold-close icon-xs text-muted"></i>
            </span>
            @else
            <div class="image-input-wrapper" style="background-image: url({{ asset('/img/200x200.png')}})"></div>
            <label class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                data-action="change" title="" data-original-title="Change avatar">
                <i class="fa fa-pen icon-sm text-muted"></i>
                <input type="file" name="proof_income" accept=".png, .jpg, .jpeg" />
            </label>
            <span class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                data-action="cancel" title="Cancel avatar">
                <i class="ki ki-bold-close icon-xs text-muted"></i>
            </span>
            @endif
        </div>
    </div>
</div>
<div class="form-group row mt-3">
    <label class="col-lg-1 col-form-label text-lg-right">Passport:</label>
    <div class="col-lg-2">
        <div class="image-input" id="kt_image_11">
            @if(isset($kyc) && !empty($kyc) && $kyc->passport!="")
            <div class="image-input-wrapper"
                style="background-image: url({!! \Helper::getCloudFrontURL($kyc->passport) !!})"></div>
            <label class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                data-action="change" title="" data-original-title="Change avatar">
                <i class="fa fa-pen icon-sm text-muted"></i>
                <input type="file" name="passport_image" accept=".png, .jpg, .jpeg" />
            </label>
            <span class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                data-action="cancel" title="Cancel avatar">
                <i class="ki ki-bold-close icon-xs text-muted"></i>
            </span>
            @else
            <div class="image-input-wrapper" style="background-image: url({{ asset('/img/200x200.png')}})"></div>
            <label class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                data-action="change" title="" data-original-title="Change avatar">
                <i class="fa fa-pen icon-sm text-muted"></i>
                <input type="file" name="passport_image" accept=".png, .jpg, .jpeg" />
            </label>
            <span class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow"
                data-action="cancel" title="Cancel avatar">
                <i class="ki ki-bold-close icon-xs text-muted"></i>
            </span>
            @endif
        </div>
    </div>
</div>
