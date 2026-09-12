<div>
    <form  method="POST" enctype="multipart/form-data" class="kt-form">
       @csrf
       <div class="row">
           <div class="col-sm-6">
               <div class="form-group">
                   <label for="name" class="col-form-label">Reason</label>
                   <select class="form-control">
                       <option>- Please Select Reason -</option>
                       <option value="1">Damaged</option>
                       <option value="2">Transafered to another store </option>
                   </select>
               </div>


               <div class="form-group">
				<label class="col-form-label">Note</label>
				<input type="text" class="form-control" required />
			</div>
           </div>

       </div>
   </form>
</div>
