<div>
{{-- Only show page loader for specific actions, not for polling --}}
<div wire:loading.class="page-loading" wire:target="startPdfGeneration,resetPdfJobAndDownload,refreshEndorse,issuePolicy,inApproval,submitToApproval,submitToReject,calculatePremium">
    <div class="page-loader flex-column bg-dark bg-opacity-25 ">
        <span class="spinner-border text-primary" role="status"></span>
        <span class="text-gray-800 fs-6 fw-semibold mt-5">Loading...</span>
    </div><br>
{{-- <div class="d-flex justify-content-end align-items-center flex-wrap gap-2">
{{-- {{$pdfJobId}} --}}
    {{-- LEFT: V2 Quote Sheet or Status or Download --}}
{{-- Download button section - only this div will reload --}}
<div 
    class="d-flex justify-content-end align-items-center flex-wrap gap-2"
    wire:key="pdf-download-section-{{ $pdfJobId ?? 'none' }}-{{ $status ?? 'none' }}-{{ $downloadLink ? 'link' : 'nolink' }}"
    @if($pdfJobId && (!$status || ($status !== 'completed' && $status !== 'failed')))
        wire:poll.3s.keep-alive="checkPdfJobStatus"
    @elseif($pdfJobId && $status === 'completed' && !$downloadLink)
        wire:poll.2s="checkPdfJobStatus"
    @endif
> 
    {{-- {{$pdfJobId}} --}}
    {{-- LEFT: V2 Quote Sheet or Status or Download --}}
  
    
    @if($status === 'completed' && $downloadLink)
        @if($hasActivityAfterPdf)
            {{-- Activity detected after PDF generation - show generate button --}}
            <button wire:click="startPdfGeneration"
                    class="btn btn-sm me-2"
                    style="background-color: #7239EA; border-color: #7239EA; color: white;">
                Generate V2-1.6 Quote Sheet
            </button>
        @else
           {{-- No activity - show download button --}}
        {{-- @push('scripts')
            <script>
                window.dispatchEvent(new CustomEvent('pdf-ready'));
            </script>
        @endpush --}}
<button
    wire:click="resetPdfJobAndDownload"
    class="btn btn-success btn-sm me-2">
    Download V2-1.6 Quote Sheet
</button>
        @endif

    @elseif($status === 'queued')
        <span class="text-warning">Generating Pdf…</span>

    @elseif($status === 'processing')
        <span class="text-warning">Processing Pdf…</span>
            
    @elseif($status === 'failed')
        <span class="text-danger me-2">❌ Job failed. Please retry.</span>
        <button wire:click="startPdfGeneration"
                class="btn btn-sm btn-warning me-2">
            Retry
        </button>

    @else
        {{-- Show generate button if no PDF job, status is null, or any other case --}}
        <button wire:click="startPdfGeneration"
                class="btn btn-sm me-2"
                style="background-color: #7239EA; border-color: #7239EA; color: white;">
           Generate V2-1.6 Quote Sheet
        </button>
    @endif
</div>
<br/>
    <div class="d-flex flex-wrap justify-content-end gap-2 policy-action-buttons">
          
        @if($this->policy->product_id == 7 || $this->policy->product_id == 8)
            <a href="{{ route('admin.policy.v2_quotationPdf_new', [
                    'policyId' => $this->policy->id,
                    'termId'   => $termId,
                    'actionId' => $this->dataShowForActionId
                ]) }}"
            target="_blank"
            class="btn btn-sm btn-info">
                <i class="fa fa-file-text-o me-1"></i> V2-1.6 Quote Sheet
            </a>
        @endif

        @if($this->policy->product_id == 17)
            <a href="{{ route('admin.policy.v2_quotationPdfSpecialistProduct', [
                    'policyId' => $this->policy->id,
                    'termId'   => $termId,
                    'actionId' => $this->dataShowForActionId,
                    'flag' => 'v2_quotationPdfSpecialistProduct'
                ]) }}"
            target="_blank"
            class="btn btn-sm btn-info">
                <i class="fa fa-cogs me-1"></i> V2 Quote Sheet (Specialist Product)
            </a>
        @endif

        @if($this->policy->product_id == 16)
            <a href="{{ route('admin.policy.v2_quotationPdfEngineering', [
                    'policyId' => $this->policy->id,
                    'termId'   => $termId,
                    'actionId' => $this->dataShowForActionId,
                    'flag' => 'v2_quotationPdfEngineering'
                ]) }}"
            target="_blank"
            class="btn btn-sm btn-info">
                <i class="fa fa-cogs me-1"></i> Download Engineering Quote Sheet
            </a>
        @endif

        @if(Auth::user()->hasPermissionTo('policy_refersh_endorse'))
            <button class="btn btn-sm btn-warning" wire:click="refreshEndorse">
                <i class="fa fa-refresh me-1"></i> Refresh Endorsement
            </button>
        @endif

        @if($this->action->premium)
            <a href="{{ route('admin.policy.coverageRateSheet', [
                    'policyId' => $this->policy->id,
                    'termId'   => $termId,
                    'actionId' => $this->dataShowForActionId
                ]) }}"
            target="_blank"
            class="btn btn-sm btn-primary">
                <i class="fa fa-bar-chart me-1"></i> Rate Sheet
            </a>
        @endif

    </div>
    <br/>
    @php $date = date('Y-m-d');
    @endphp
    @if ($this->action->status != "ISSUED" && $this->action->transaction_type == 'ENDORSE' && $this->action->effective_from < $date)
    @can('policy_reinstate')   
      @if($this->rateFLag == 1)
        <div class="text-center"><br>
                @if (Auth::user()->hasPermissionTo('policy_submit_to_issue'))
                  <button class="btn btn-primary" wire:click="issuePolicy" >Submit To Issue</button>
                @endif
            @if ($this->action->status != "IN_APPROVAL" && $this->action->status != "APPROVED" && $this->action->status != "REJECTED")
               @if (Auth::user()->hasPermissionTo('policy_submit_to_approval'))
                 <button class="btn btn-secondary" wire:click="inApproval" >Submit To Approval</button>
               @endif
            @endif

            @if ($this->action->status == "IN_APPROVAL")
                  @if (Auth::user()->hasPermissionTo('policy_approved'))
                    <a wire:click="submitToApproval" class="btn btn-success">APPROVED</a>
                  @endif
                @if ($this->action->status != "REJECTED")
                   @if (Auth::user()->hasPermissionTo('policy_rejected'))
                    <a wire:click="submitToReject" class="btn btn-danger">REJECTED</a>
                   @endif
                @endif
            @endif
        </div><br>
     @endif
    @endcan
    @if ($this->action->transaction_type != "CANCEL")
        <div class="card-footer text-center">
            <input type="button" id="rateQuoteSheet" class="btn btn-warning" value="Rate" wire:click.prevent="calculatePremium" style="color: black;">
            {{-- <input type="submit" class="btn btn-danfger" value="Save"> --}}
            <br><br>
            <!-- @if($this->rateFLag == 1 && $this->showSubmitButtons)
                <div class="text-center"><br>
                    @if (Auth::user()->hasPermissionTo('policy_submit_to_issue'))
                        <button class="btn btn-primary" wire:click="issuePolicy" >Submit To Issue</button>
                    @endif
                    @if ($this->action->status != "IN_APPROVAL" && $this->action->status != "APPROVED" && $this->action->status != "REJECTED")
                        @if (Auth::user()->hasPermissionTo('policy_submit_to_approval'))
                            <button class="btn btn-secondary" wire:click="inApproval" >Submit To Approval</button>
                        @endif
                    @endif
                </div><br>
            @endif -->
        </div>
    @endif
    @elseif ($this->action->status != "ISSUED")
    @if($this->rateFLag == 1)
        <div class="text-center"><br>
            @if ($this->action->status == "IN_APPROVAL")
                  @if (Auth::user()->hasPermissionTo('policy_approved'))
                    <a wire:click="submitToApproval" class="btn btn-success">APPROVED</a>
                  @endif
                @if ($this->action->status != "REJECTED")
                   @if (Auth::user()->hasPermissionTo('policy_rejected'))
                    <a wire:click="submitToReject" class="btn btn-danger">REJECTED</a>
                   @endif
                @endif
            @endif
        </div><br>
    @elseif($this->action->transaction_type == "CANCEL")
    <div class="text-center"><br>
                @if (Auth::user()->hasPermissionTo('policy_submit_to_issue'))
                  <button class="btn btn-primary" wire:click="issuePolicy" >Submit To Issue</button>
                @endif
            @if ($this->action->status != "IN_APPROVAL" && $this->action->status != "APPROVED" && $this->action->status != "REJECTED")
               @if (Auth::user()->hasPermissionTo('policy_submit_to_approval'))
                 <button class="btn btn-secondary" wire:click="inApproval" >Submit To Approval</button>
               @endif
            @endif

            @if ($this->action->status == "IN_APPROVAL")
                  @if (Auth::user()->hasPermissionTo('policy_approved'))
                    <a wire:click="submitToApproval" class="btn btn-success">APPROVED</a>
                  @endif
                @if ($this->action->status != "REJECTED")
                   @if (Auth::user()->hasPermissionTo('policy_rejected'))
                    <a wire:click="submitToReject" class="btn btn-danger">REJECTED</a>
                   @endif
                @endif
            @endif
        </div><br>    
    @endif    
        <div class="card-footer text-center">
            <input type="button" id="rateQuoteSheet" class="btn btn-warning" value="Rate" wire:click.prevent="calculatePremium" style="color: black;">
            {{-- <input type="submit" class="btn btn-danger" value="Save"> --}}
            <br><br>
            @if($this->rateFLag == 1 && $this->showSubmitButtons)
                <div class="text-center"><br>
                    @if (Auth::user()->hasPermissionTo('policy_submit_to_issue'))
                        <button class="btn btn-primary" wire:click="issuePolicy" >Submit To Issue</button>
                    @endif
                    @if ($this->action->status != "IN_APPROVAL" && $this->action->status != "APPROVED" && $this->action->status != "REJECTED")
                        @if (Auth::user()->hasPermissionTo('policy_submit_to_approval'))
                            <button class="btn btn-secondary" wire:click="inApproval" >Submit To Approval</button>
                        @endif
                    @endif
                </div><br>
            @endif
        </div>
    @elseif($this->action->status == "ISSUED" && $this->check_invoice_exists == 0)
    @can('policy_unissue')  
    <!-- <div class="card-footer text-center">
            <input type="button" id="generateInvoice" class="btn btn-warning" value="Generate Invoice" wire:click.prevent="generateInvoice" style="color: black;">
            <br><br>
        </div> -->
    @endcan
    @endif

        <div class="card-footer text-center">
            <a class="align-items-center text-gray-800 text-hover-primary fs-2 fw-bold me-3">
                Premium : P {{ number_format((float)$this->action?->premium ?? "", 2, '.', ',') }}
            </a>
        </div>
        @can('policy_unissue')  
        <!-- <div class="card-footer text-center">
            <input type="button" id="rateQuoteSheet" class="btn btn-warning" value="Rate" wire:click.prevent="calculatePremium" style="color: black;">
            {{-- <input type="submit" class="btn btn-danger" value="Save"> --}}
            <br><br>
        </div> -->
        @endcan
</div>
</div>
@push('scripts')
{{-- <script>
    window.addEventListener('pdf-ready', function () {
        location.reload();
    });
</script> --}}
<script>
    // Listen for download file event
    window.addEventListener('download-file', event => {
        const link = document.createElement('a');
        link.href = event.detail.url;
        link.download = event.detail.filename || '';
        link.target = '_blank';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });

    // Refresh page once the "Download V2-1.6 Quote Sheet" button is visible
    // (function () {
    //     let hasRefreshed = false;

    //     function isDownloadVisible() {
    //         const btn = Array.from(document.querySelectorAll('button, a, [role="button"]'))
    //             .find(el => (el.textContent || el.innerText || '').includes('Download V2-1.6 Quote Sheet'));
    //         if (!btn) return false;

    //         const style = window.getComputedStyle(btn);
    //         return style.display !== 'none' &&
    //             style.visibility !== 'hidden' &&
    //             style.opacity !== '0' &&
    //             btn.offsetWidth > 0 &&
    //             btn.offsetHeight > 0;
    //     }

    //     function triggerRefreshIfVisible() {
    //         if (hasRefreshed) return;
    //         if (isDownloadVisible()) {
    //             hasRefreshed = true;
    //             window.location.reload();
    //         }
    //     }

    //     // Initial check
    //     triggerRefreshIfVisible();

    //     // Observe DOM changes to catch when the button appears
    //     const observer = new MutationObserver(() => triggerRefreshIfVisible());
    //     observer.observe(document.body, {
    //         childList: true,
    //         subtree: true,
    //         attributes: true,
    //         attributeFilter: ['style', 'class']
    //     });

    //     // Fallback periodic check
    //     const interval = setInterval(() => {
    //         triggerRefreshIfVisible();
    //         if (hasRefreshed) {
    //             clearInterval(interval);
    //             observer.disconnect();
    //         }
    //     }, 500);
    // })();
</script>
@endpush