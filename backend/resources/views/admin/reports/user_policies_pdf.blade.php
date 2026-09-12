<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>User Policies Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            color: #333;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0;
            color: #666;
        }
        .report-info {
            margin-bottom: 20px;
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
        }
        .report-info p {
            margin: 5px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
            color: #333;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        .no-data {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $policies->first()->employer_group_name ?? 'User Policies Report' }}</h1>
        <p>Generated for: {{ $user->firstName }} {{ $user->lastName }}</p>
        <p>Generated on: {{ $generated_at }}</p>
    </div>

    <div class="report-info">
        <p><strong>Report Summary:</strong></p>
        <p>Total Policies: {{ $policies->count() }}</p>
        <p>Report Type: Policies created by logged-in user through AD Group uploads</p>
    </div>

    @if($policies->count() > 0)
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Customer Name</th>
                    <th>Plan Name</th>
                    <th>Premium</th>
                </tr>
            </thead>
            <tbody>
                @foreach($policies as $index => $policy)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>
                            {{ $policy->firstName }} 
                            {{ $policy->middleName ? $policy->middleName . ' ' : '' }}
                            {{ $policy->lastName }}
                        </td>
                        <td>{{ $policy->plan_name ?? 'N/A' }}</td>
                        <td>
                            @if($policy->premium)
                                P{{ number_format($policy->premium, 2) }}
                            @else
                                N/A
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="no-data">
            <p>No policies found for the logged-in user.</p>
        </div>
    @endif

    <div class="footer">
        <p>This report was generated automatically by the system.</p>
        <p>For any queries, please contact the system administrator.</p>
    </div>
</body>
</html>
