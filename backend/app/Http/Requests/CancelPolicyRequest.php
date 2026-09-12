<?php

namespace AlphaDirect\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelPolicyRequest extends FormRequest
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
            'policy_id'     => 'required|numeric',
            'leadSource'    => 'required|in:devgraphite', //start.alphadirect.co.bw
            'reason'        => 'required',
            'circumstances' => 'required',
            'other'         => 'nullable'
        ];
    }
}
