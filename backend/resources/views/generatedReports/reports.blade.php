<!DOCTYPE html>
<html>
    <table>
        @if(count($sent) > 0)
            <tr>
                <th>Policy Number</th>
                <th>Email Status</th>
            </tr>
        @foreach($sent as $s)
            <tr>
                <th>{{ $s->policyNumber }}</th>
                <td>
                    @if($s->status = 1)
                        Document sent successfully
                    @else
                        -
                    @endif

                </td>
            </tr>
        @endforeach
        @else
        <h3>No policy document has been sent to any customer.</h3>
            @endif
    </table>
</html>