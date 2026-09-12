<!DOCTYPE html>

<html lang="en" >
    <!-- begin::Head -->
             @include('Agents.Layout.header')
<script>console.log(1);</script>
    <!-- end::Head -->
    <!-- begin::Body -->

        <!-- end:: Header Mobile -->
        <!-- begin:: Root -->
        <div class="kt-grid kt-grid--hor kt-grid--root">
            <!-- begin:: Page -->
            <div class="kt-grid__item kt-grid__item--fluid kt-grid kt-grid--ver kt-page">

            
                @include('Agents.Layout.sidebar')



           @include('Agents.Layout.topNav')
                        <!-- begin:: Content -->
                        <div class="kt-content kt-grid__item kt-grid__item--fluid" id="kt_content">

<!---new--->
 <div class="row orc">
 <h1>Create a new policy for our potential customer,Use OCR or manually enter the data</h1>
	 <div class="col-lg-6 order-lg-1 order-xl-1">
 <!--begin::Portlet-->
 <div class="kt-portlet kt-portlet--height-fluid">
 <div class="kt-portlet__head kt-portlet__head">
    <div class="kt-portlet__head-label">
     <h3 class="kt-portlet__head-title">From OCR</h3></div>                                    
 </div>
 <div class="kt-portlet__body kt-portlet__body--fluid">
       <img src="{{asset('images/orc.jpg')}}" data-toggle="modal" data-target="#ocrAdd" alt=""/> </div>
 </div>
 <!--end::Portlet-->
 </div>
	 <div class="col-lg-6 order-lg-1 order-xl-1">
 <!--begin::Portlet-->
  <div class="kt-portlet kt-portlet--height-fluid">
   <div class="kt-portlet__head kt-portlet__head">
       <div class="kt-portlet__head-label">
         <h3 class="kt-portlet__head-title">Manually</h3></div>
       </div>
   <div class="kt-portlet__body kt-portlet__body--fluid">
{{--   <a href="{{Route('makePolicy')}}">--}}
{{--        <img src="{{asset('images/manually.jpg')}}" alt=""/>--}}
{{--       </a>--}}
     </div>
   </div>
 <!--end::Portlet-->
 </div>
</div>
<!---/new--->
						</div>

                                <!-- end:: Scrolltop -->
<!-- Large Modal1 -->
 <div id="addPolicy"class="modal fade bd-example-modal-xl" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true">
   <div class="modal-dialog modal-xl">
     <div class="modal-content">
        <div class="modal-header">
        <h5 class="modal-title">Add Policy</h5>
        <!-- <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button> -->
        </div>
        <form action="{{Route('agent-savePolicy')}}" method="POST">
      {{ csrf_field() }}
        <div class="modal-body">
            <div class="row">
				  <div class="col-md-3 form-group">
						<label>First Name :</label>
						<input type="text" class="form-control" id="fname" name="fname" aria-describedby="emailHelp" placeholder="First Name" required>
					</div>
				  <div class="col-md-3 form-group">
						<label>Last Name :</label>
						<input type="text" class="form-control" id="lname" name="lname" aria-describedby="emailHelp" placeholder="Last Name" required>
					</div>
				  <div class="col-md-3 form-group">
						<label>Omang :</label>
						<input type="text" class="form-control" id="id" name="id" aria-describedby="emailHelp" placeholder="Omang ID" required>
					</div>
                    <div class="col-md-3 form-group">
						<label>Cellphone No :</label>
						<input type="text" class="form-control" name="cellphone" aria-describedby="emailHelp" placeholder="Cellphone Number" required>
					</div>
              </div>
                <div class="row">
				  <div class="col-md-3 form-group">
						<label>Policy Plan:</label>

                        <select class="form-control" name="policyPlan" id="exampleSelect1">
                            @foreach ($policyPlan as $plan )
                            <option value="{{$plan->premium}}">{{$plan->plan}}</option>
                            @endforeach
						</select>
					</div>
                    <div class="col-md-3 form-group">
						<label>License Plate:</label>
						<input type="text" class="form-control" name="license" aria-describedby="emailHelp" placeholder="Motor Reg. No" required>
					</div>

                    <div class="col-md-3 form-group">
                            <label>Date Of Birth :</label>
                        <input  type="date" class="form-control" id="dob" name="dob" placeholder="DD/MM/YYYY"
							                        onclick="datePicker(event)" data-relmax="-18">                    
                            </div>

                    <div class="col-md-3 form-group">
						<label for="exampleSelect1">Billing Method:</label>
							<select id="billing" class="form-control" name="billing" id="exampleSelect1" onchange="disableOptions(event)">
								<option value="Bank">Bank</option>
								<option value="MyZaka">MyZaka</option>
                                <option value="orangeMoney">Orange Money</option>
							</select>
					</div>
             </div>
              <div class="row">
				  <div class="col-md-3 form-group">
						<label>Myzaka/Orange Cell:</label>
						<input id="billingCell" type="text" class="form-control" name="billingCell" aria-describedby="emailHelp" placeholder="Myzaka/Orange Cell" >
					</div>

                    <div class="col-md-3 form-group">
						<label for="exampleSelect1">Bank Name:</label>
							<select id="bankName" class="form-control" name="bankName" id="exampleSelect1">
                                <option value="N/A">Please Select the Bank</option>
								<option value="First National Bank">FNB</option>
								<option value="Barclays">Barclays(ABSA)</option>
                                <option value="Standard Chartered">Standard Chartered</option>
                                <option value="Capital Bank">Capital Bank(First Capital Bank)</option>
                                <option value="Stanbic Bank">Standbic Bank</option>
                                <option value="Bank ABC">Bank ABC</option>
							</select>
					</div>

                    <div class="col-md-3 form-group">
						<label>Branch Code:</label>
						<input id="branchCode" type="text" class="form-control" name="branchCode" aria-describedby="emailHelp" placeholder="Branch Code">
					</div>
                    <div class="col-md-3 form-group">
						<label>Account Number:</label>
						<input id="accountNumber" type="text" class="form-control" name="accountNumber" aria-describedby="emailHelp" placeholder="Account Number" >
					</div>
             </div>
              
        </div>
       <div class="modal-footer">
       <button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button>
       <button type="submit" class="btn btn-outline-brand">Save changes</button>
    </div>
    </form>
  </div>
</div>
</div>
<!-- /Large Modal1 -->
       <!-- Large Modal -->
                                                    <div id="ocrAdd" class="modal fade bd-example-modal-xl" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true">
                                                        <div class="modal-dialog modal-xl">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">
                                                                        ORC title
                                                                    </h5>
                                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"> <span aria-hidden="true">&times;</span> </button>
                                                                </div>
                                                                <div class="modal-body">
                                                                   <form action="{{Route('documentScanner')}}" method="POST" accept="application/pdf , image/jpeg , image/bmp , image/png" enctype="multipart/form-data">
                                                                  {{csrf_field()}}
                                                                   <div class="row">
    <div class="col-md-6 orcform">
        <div class="form-group">
            <select class="form-control" name="documentType" id="exampleSelect1">
                <option value="omang">Omang</option>
                <option value="passport">Passport</option>
                <option value="driversLicense">Drivers License</option>
                <option value="bluebook">Vehicle Registration</option>
            </select>
        </div>
        <div class="kt-avatar" id="kt_profile_avatar_1">
            <div class="kt-avatar__holder" style="background-image: url({{asset('images/ocrDocument.png')}})"></div>
          
            <label class="kt-avatar__upload" data-toggle="kt-tooltip" title="" data-original-title="Upload Document">
                <i class="flaticon-edit"></i>
                <input type="file" name="document" accept=".png, .jpg, .jpeg">
            </label>
            <span class="kt-avatar__cancel" data-toggle="kt-tooltip" title="" data-original-title="Cancel avatar"> <i class="fa fa-times"></i> </span>
        </div>

        <div class="form-group form-group-last row">

        </div>
        <div>
            <div><img class="w-100" id="img" /></div>

        </div>

    </div>


<div class="col-md-6 orcname">
    <label>Name</label>
    <span><input id="customerName" class="form-control" name="customer" type="text"></span>
    <br>
    <label>Omang</label>
    <span><input id="omangNumber" class="form-control" name="omangNumber" type="text"></span>
    <br>
  
    <label>D.O.B</label>
    <span><input id="dateOfBirth" class="form-control" name="dob" type="text"></span>
    <br>
    <label>Place Of Birth</label>
    <span><input id="placeOfBirth" class="form-control" name="address" type="text"></span>
</div>

</div>
                                                                   </form>

                                                                    <form id="imageForm" role="form" enctype="multipart/form-data" >

                                                                        <div id="image-preview"></div>

                                                                        <input type="hidden" name="_csrf" />

                                                                        <div class="form-group">

                                                                        <div class="offset-sm-3 col-md-7 pl-2">

                                                                            <div class="row">

                                                                            <label class="col-md-3 col-form-label font-weight-bold text-right" for="photo">Omang</label>

                                                                            <label class="col-md-6 file-upload-button btn btn-default custom-file-upload" for="photo">Select Image</label>

                                                                            <input class="form-control d-none" type="file" accept="image/x-png,image/jpeg,image/bmp/pdf,image" onchange="PreviewImage()" name="photo" id="photo" required="required" />

                                                                            </div>

                                                              
                                                                   			<div class="col-md-6">
                                                                   				<button type="submit" class="btn btn-success btn-elevate btn-pill">Submit</button>
                                                                   			</div>
                                                                   </form>

                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-outline-brand" data-dismiss="modal">Close</button>
                                                                    <button type="button" id="saveChanges" class="btn btn-outline-brand">Save changes</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <!-- Large Modal -->
       
        <!-- begin:: Scrolltop -->
        <div id="kt_scrolltop" class="kt-scrolltop"> <i class="la la-arrow-up"></i> </div>
        <!-- end:: Scrolltop -->


                
                
                
                
                
        <!-- begin::Global Config(global config for global JS sciprts) -->
        <script>
            var KTAppOptions = {

    "colors": {

        "state": {

            "brand": "#5d78ff",

            "metal": "#c4c5d6",

            "light": "#ffffff",

            "accent": "#00c5dc",

            "primary": "#5867dd",

            "success": "#34bfa3",

            "info": "#36a3f7",

            "warning": "#ffb822",

            "danger": "#fd3995",

            "focus": "#9816f4"
        },

        "base": {

            "label": [

                "#c5cbe3",

                "#a1a8c3",

                "#3d4465",

                "#3e4466"
            ],

            "shape": [

                "#f0f3ff",

                "#d9dffa",

                "#afb4d4",

                "#646c9a"
            ]
        }
    }
};
        </script>
        <!-- end::Global Config -->
               @include('Agents.Layout.scripts')

<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.1/cropper.min.js"></script>

        <script>

        var oFReader = new FileReader();

		function dataURItoBlob(dataURI) {

			// convert base64 to raw binary data held in a string

			// doesn't handle URLEncoded DataURIs - see SO answer #6850276 for code that does this

			var byteString = atob(dataURI.split(',')[1]);

			// separate out the mime component

			var mimeString = dataURI.split(',')[0].split(':')[1].split(';')[0];

			// write the bytes of the string to an ArrayBuffer

			var ab = new ArrayBuffer(byteString.length);

			var dw = new DataView(ab);

			for(var i = 0; i < byteString.length; i++) {

				dw.setUint8(i, byteString.charCodeAt(i));

			}

			// write the ArrayBuffer to a blob, and you're done

			return new Blob([ab], {type: mimeString});

		}



		$('#clear-image').on('click', function(){

			$('#img').hide();

			$("#clear-image").hide();

		})
        
        $("#saveChanges").on("click", ()=>{
            $("#ocrAdd").modal("hide");
            $("#addPolicy").modal("show");
            $("#fname").val($("#customerName").val().split(" ")[0]);
            $("#lname").val($("#customerName").val().split(" ")[1]);
            $("#id").val($("#omangNumber").val());
        })

		$("#imageForm").on("submit", function (e) {

			e.preventDefault();

			//- let formData = new FormData();

			const cropper = $("#img").data('cropper');

			//- formData.append('photo', dataURItoBlob(cropper.url));

			//- console.log(JSON.stringify(formData));

			//- formData.append('_csrf', "#{_csrf}");

			//- $("#photo").val(cropper.url);

			//- $("#imageForm").append(`<input type='file' accept="image/x-png,image/jpeg,image/bmp" name="cropped" value='${cropper.url}' >`);

			//- $("#imageForm")[0].submit();

			//- $.post("/scanFront", {data: formData}, (data)=>{


			//- });
            var urlValue = '{{ \Config::get('values.graphite_url') }}' 
			$.ajax(urlValue+'/ocr/scanOmang', {

				method: "POST",

				data: {photo: cropper.getCroppedCanvas().toDataURL('image/jpeg')},


				success: function (data) {
                    
                    $("#customerName").val(data.fname +' '+ data.lname);
                    $("#omangNumber").val(data.id);
                    $("#dateOfBirth").val(data.dob);
                    $("#placeOfBirth").val(data.city);
					console.log(data);

				},

				error: function (e) {

					console.log(e.toString());

				}

			});

			return true;

		});



		function PreviewImage() {

			oFReader.readAsDataURL(document.getElementById("photo").files[0]);



			oFReader.onload = function (oFREvent) {

				if (oFREvent.target.result) {

					$("#img").attr('src', oFREvent.target.result);

					$("#img").show();

					$("#clear-image").show();



					$("#img").cropper();

				} else {

					$("#img").attr('src', "");

					$("#img").hide();

					$("#clear-image").hide();

				}

			};

		}
        </script>
       <script>
        @if(Session::has('userAccountExists'))
        Toastify({
            text: "{{ Session::get('userAccountExists') }}",
            duration: 4000,
            newWindow: true,
            gravity: "top", // `top` or `bottom`
            positionRight: true, // `true` or `false`
            backgroundColor: "#CC0000",
            }).showToast();   
        @endif
  
        </script>

       <script>
        @if(Session::has('policySaved'))
        Toastify({
            text: "{{ Session::get('policySaved') }}",
            duration: 4000,
            newWindow: true,
            gravity: "top", // `top` or `bottom`
            positionRight: true, // `true` or `false`
            backgroundColor: "#00C851",
            }).showToast();   
        @endif
  
        </script>

               <script>

	function datePicker(evt) {
			$('input[data-relmax]').each(function () {
				let oldVal = $(this).prop('value');
				let relmax = $(this).data('relmax');
				let max = new Date();
				max.setFullYear(max.getFullYear() + relmax);
				$.prop(this, 'max', $(this).prop('valueAsDate', max).val());
				$.prop(this, 'value', oldVal);
			});
		}
</script>
<script>

    function disableOptions(evt){

    var billingMethod = document.getElementById("billing").value;


    if(billingMethod === 'Bank'){

            document.getElementById("billingCell").disabled = true; 
            document.getElementById("branchCode").disabled = false; 
            document.getElementById("accountNumber").disabled = false; 
            document.getElementById("bankName").disabled = false; 

    } else{

            document.getElementById("billingCell").disabled = false; 
            document.getElementById("branchCode").disabled = true; 
            document.getElementById("accountNumber").disabled = true; 
            document.getElementById("bankName").disabled = true; 


     }
    }

 
</script>
<script>console.log(2);</script>
    </body>
    <!-- end::Body -->
</html>
