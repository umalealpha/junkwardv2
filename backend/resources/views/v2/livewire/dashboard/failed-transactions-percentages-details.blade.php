<div class="card-body pt-5">
            <!--begin::Items-->
            <div class="d-flex flex-stack">
                <!--begin::Section-->
                <div class="d-flex align-items-center flex-stack flex-wrap flex-row-fluid d-grid gap-2">
                    <!--begin::Content-->
                    <div class="me-5">
                        <!--begin::Title-->
                        <a href="#" class="text-gray-400 fw-semibold fs-7 d-block text-start ps-0">Reasons</a>
                        <!--end::Title-->
                        <!--begin::Desc-->
                        <!-- <span class="text-gray-400 fw-semibold fs-7 d-block text-start ps-0">Community</span> -->
                        <!--end::Desc-->
                    </div>
                    <!--end::Content-->
                    <!--begin::Wrapper-->
                    <div class="d-flex align-items-center">
                        <!--begin::Number-->
                        <span class="text-gray-400 fw-semibold fs-7 d-block text-start ps-0">current &nbsp;</span>
                        <span class="text-gray-400 fw-semibold fs-7 d-block text-start ps-0"> (previous) &nbsp; &nbsp;</span>
                        <!--end::Number-->
                        <!--begin::Info-->
                        <div class="m-0">
                            <!--begin::Label-->
                            
                            <!--end::Label-->
                        </div>
                        <!--end::Info-->
                    </div>
                    <!--end::Wrapper-->
                </div>
                <!--end::Section-->
                </div>
            <!--end::Item-->
            <!--begin::Separator-->
            <div class="separator separator-dashed my-2"></div>
            <div class="">
                @php $this->totalPolicyCount = 0 ;  @endphp
                @if(Count($this->PaymentTransaction)>0)
                @foreach($this->PaymentTransaction as $key =>$transaction)
               
                <!--begin::Item-->
                <div class="d-flex flex-stack">
                    <!--begin::Section-->
                    <div class="d-flex align-items-center me-5">
                        <!--begin::Flag-->
                        <!-- <img src="assets/media/svg/brand-logos/atica.svg" class="me-4 w-30px" style="border-radius: 4px" alt="" /> -->
                        <!--end::Flag-->
                        <!--begin::Content-->
                        <div class="me-5">
                            <!--begin::Title-->
                            <a href="#" class="text-gray-800 fw-bold text-hover-dark fs-6">{{$transaction->note}}</a>
                            <!--end::Title-->
                            <!--begin::Desc-->
                            <!-- <span class="text-gray-400 fw-semibold fs-7 d-block text-start ps-0">Community</span> -->
                            <!--end::Desc-->
                        </div>
                        <!--end::Content-->
                    </div>
                    <!--end::Section-->
                    <!--begin::Wrapper-->
                    <div class="d-flex align-items-center">
                        <!--begin::Number-->
                        <span class="text-gray-800 fw-bold fs-4 me-3">{{$transaction->count_of_notes}}</span>
                        <span class="text-gray-800 fw-bold fs-4 me-3">
                            (
                                <?php echo  $previousNoteCount = $this->getPreviousNotesCount((clone $this->startDate)->subDay($this->differenceBetweenDates)->format('Y-m-d'),
                                        (clone $this->endDate)->subDay($this->differenceBetweenDates)->format('Y-m-d'),
                                        $transaction->note) 
                                ?>
                            )</span>
                        <!--end::Number-->
                        <!--begin::Info-->
                        <div class="m-0">
                            <!--begin::Label-->
                            @if(($transaction->count_of_notes) > ($previousNoteCount))
                            <span class="badge badge-light-success fs-base" style="width: 70px;">
                            <span class="svg-icon svg-icon-5 svg-icon-success ms-n1">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect opacity="0.5" x="13" y="6" width="13" height="2" rx="1" transform="rotate(90 13 6)" fill="currentColor" />
                                    <path d="M12.5657 8.56569L16.75 12.75C17.1642 13.1642 17.8358 13.1642 18.25 12.75C18.6642 12.3358 18.6642 11.6642 18.25 11.25L12.7071 5.70711C12.3166 5.31658 11.6834 5.31658 11.2929 5.70711L5.75 11.25C5.33579 11.6642 5.33579 12.3358 5.75 12.75C6.16421 13.1642 6.83579 13.1642 7.25 12.75L11.4343 8.56569C11.7467 8.25327 12.2533 8.25327 12.5657 8.56569Z" fill="currentColor" />
                                </svg>
                            </span>
                            {{round($this->getPercentage($transaction->count_of_notes,$previousNoteCount),1)}}%</span>
                            @elseif(($transaction->count_of_notes)<($previousNoteCount))
                                <span class="badge badge-light-danger fs-base" style="width: 70px;">
                                <span class="svg-icon svg-icon-5 svg-icon-danger ms-n1">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <rect opacity="0.5" x="11" y="18" width="13" height="2" rx="1" transform="rotate(-90 11 18)" fill="currentColor" />
                                        <path d="M11.4343 15.4343L7.25 11.25C6.83579 10.8358 6.16421 10.8358 5.75 11.25C5.33579 11.6642 5.33579 12.3358 5.75 12.75L11.2929 18.2929C11.6834 18.6834 12.3166 18.6834 12.7071 18.2929L18.25 12.75C18.6642 12.3358 18.6642 11.6642 18.25 11.25C17.8358 10.8358 17.1642 10.8358 16.75 11.25L12.5657 15.4343C12.2533 15.7467 11.7467 15.7467 11.4343 15.4343Z" fill="currentColor" />
                                    </svg>
                                </span>
                            {{round($this->getPercentage($transaction->count_of_notes,$previousNoteCount),1)}}%</span>
                            @elseif(($transaction->count_of_notes)==($previousNoteCount))
                                <span class="badge badge-light-warning fs-base" style="width: 70px;">
                                <span class="svg-icon svg-icon-5 svg-icon-warning-700">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M14.4 11H3C2.4 11 2 11.4 2 12C2 12.6 2.4 13 3 13H14.4V11Z" fill="currentColor" />
                                        <path opacity="0.3" d="M14.4 20V4L21.7 11.3C22.1 11.7 22.1 12.3 21.7 12.7L14.4 20Z" fill="currentColor" />
                                    </svg>
                                </span>
                            {{round($this->getPercentage($transaction->count_of_notes,$previousNoteCount),1)}}%</span>
                            @endif
                            <!--end::Label-->
                        </div>
                        <!--end::Info-->
                    </div>
                    <!--end::Wrapper-->
                </div>
                <!--end::Item-->
                <!--begin::Separator-->
                <div class="separator separator-dashed my-1"></div>
                <!--end::Separator-->
                @endforeach
                @else
                No data available.
                @endif
            </div>
            <!--end::Items-->
        </div>