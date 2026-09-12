

                        <h5>Give the name of defaulting employees and their respective positions:</h5>
                        <hr>
                        <div class="form-group row">

                            <label for="example-text-input" class="col-3 col-form-label">Defaulting Employees Name & Position</label>
                             <div class="Production col-8" style="margin-left: 10px;">
                                <div class="form-group row">
                                    <input type="text" class="form-control col-6" name="defaulting_employees_name[]" value="" placeholder="Enter Defaulting Employees Name">
                                    <input type="text" class="form-control col-5" name="defaulting_employees_position[]" value="" placeholder="Enter Defaulting Employees Position">
                                    <a href="javascript:void(0);" class="add_button col-1" title="Add field"><i class="fas fa-plus" style="font-size:23px;"></i></a>
                                 </div>
                               </div>

                         </div>
                         <div class="form-group row">

                            <label for="example-text-input" class="col-3 col-form-label">Have the employees been involved in or
been suspected of any previous loss?</label>
                            <div class="col-8">
                            <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="employees_been_involved"    class="form-control condition" value="1">Yes<span></span>
                                                    </label>
                                                    <label class="kt-radio" style="margin-left: 10px;">
                                                        <input type="radio" name="employees_been_involved"  class="form-control  condition"
                                                               value="0">No<span></span>
                                                    </label>
                            </div>
                        </div>

                    {{-- <div class="form-group row">
                            <label for="example-text-input" class="col-3 col-form-label">Give full details of the circumstances of the
loss and how it was discovered.</label>
                            <div class="col-8">
                                        <textarea type="text" class="form-control" name="circumstances" value=""  placeholder="Give full details of the circumstances of the
loss and how it was discovered."></textarea>

                        </div>
                    </div> --}}
