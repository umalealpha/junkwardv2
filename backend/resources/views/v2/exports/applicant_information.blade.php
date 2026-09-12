<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Applicant Information</title>

    <style>
        table {
            border : 1px solid;
            width:100%
        }
        tr td {
            border : 1px solid
        }
    </style>
</head>
<body>
    <table>
        <tr></tr>
        <tr>
            <td></td>
            <td colspan="3"><h1>Applicant Information</h1></td>
        </tr>
        <tr>
            <td></td>
            <td>Entity Type</td>
            <td colspan="2">{{ $policy->profile->entity_type }}</td>
        </tr>
        <tr>
            <td></td>
            <td>Name of Group of Companies</td>
            <td colspan="2">{{ $policy->profile->company?->name ?? '' }}</td>
        </tr>
        <tr>
            <td></td>
            <td>Postal Address</td>
            <td colspan="2">{{ $policy->profile->company?->postal_address ?? '' }}</td>
        </tr>
        <tr>
            <td></td>
            <td>Company Registration Number</td>
            <td colspan="2">{{ $policy->profile->company?->company_registration_number ?? '' }}</td>
        </tr>
        <tr>
            <td></td>
            <td>VAT Number</td>
            <td colspan="2">{{ $policy->profile->company?->VAT_registration_number ?? '' }}</td>
        </tr>
        <tr>
            <td></td>
            <td>Contact Person</td>
            <td colspan="2">{{ $policy->profile->company?->contact_person ?? '' }}</td>
        </tr>
        <tr>
            <td></td>
            <td>Contact Person's Number</td>
            <td colspan="2">{{ $policy->profile->company?->contact_person_number ?? '' }}</td>
        </tr>
        <tr>
            <td></td>
            <td>Primary Email</td>
            <td colspan="2">{{ $policy->profile->company?->primary_email ?? '' }}</td>
        </tr>
        <tr>
            <td></td>
            <td>Secondary Email</td>
            <td colspan="2">{{ $policy->profile->company?->secondary_email ?? '' }}</td>
        </tr>
    </table>

</body>
</html>
