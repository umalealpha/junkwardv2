<?php

namespace AlphaDirect\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreKycCompliaceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'compliance_name'      => 'required|alpha_dash|max:30|min:1',
            'flow_id'              => 'required',
            'data.*.check'         => 'required|in:1,2,3',
            'data.*.other'         => 'required_if:data.*.check,==,2|',
        ];
    }

    public function messages()
    {
        return [
            'data.*.check.required' => 'This field is required.',
            'data.*.check.in'       => 'The value id invalid.',
            'data.*.check.other'    => 'This field is required.',
        ];

    }
}
