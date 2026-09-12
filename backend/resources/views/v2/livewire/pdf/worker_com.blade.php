<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
    <title>Tax Invoice</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,500;1,600;1,700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link rel="license" href="https://www.opensource.org/licenses/mit-license/"> --}}

    <link rel="icon" href="images/favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.cdnfonts.com/css/arial" rel="stylesheet">
    <link href="https://fonts.cdnfonts.com/css/montserrat" rel="stylesheet">
	<style>
        @import url('https://fonts.cdnfonts.com/css/arial');
		@import url('https://fonts.cdnfonts.com/css/montserrat');

        body {
            font-family: 'Montserrat','Arial', 'sans-serif' !important;
            font-weight: 500;
            font-size: 12px;
            line-height: 1 !important;
        }
    </style>
</head>

<body style="margin: 0;">

<div id="p1" style="overflow: hidden; position: relative; background-color: white;">

<!-- Begin shared CSS values -->
<style class="shared-css" type="text/css" >
.heading
{
	padding: 0 0 0 40px;
    font-size: 14px;
    font-weight: 600;
}
.sub-heading
{
	padding: 0 0 0 40px;
    font-size: 14px;
}
</style>
<!-- End shared CSS values -->


<!-- Begin inline CSS -->
<style type="text/css" >

</style>
<!-- End inline CSS -->

<!-- Begin page background -->
<div style="border:3px solid black; background-color:rgba(0,0,0,0);height:98%; ">
	
	<table>
		<tr>
			<td>
				<img src="{{ URL::to('/motor/img/2.png') }}"  style="width: 250px;"/>
			</td>
			<td style="margin: 0 0 0 15em;font-size: 15px;font-weight: 800;">
				Alpha Direct Insurance Company
					<br/>(Proprietary) Limited
			</td>	
		</tr>
		<tr>
			<td colspan="2" style="font-size: 15px;font-weight: 600; text-align: center;">WORKER’S COMPENSATION ACT, 1998<br/>(23 OF 1998)<br/>(CAP. 47:03)<br/>(SECTION 32)
			</td>
		</tr>
		
		<tr>
			<td class="heading">Policy Number :&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ $policyNumber }}</td>
		</tr>
		<tr>
			<td class="heading">Agency :&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ $policyAgency }}</td>
		</tr>
		
		<tr>
			<td class="heading" colspan="2">This is to certify that&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ $policyCompany }}
                <br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ $customerRiskAddress }}
                <br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ $customerCityAddress }}</td>
		</tr>
		<tr>
			<td colspan="2" style="font-size: 15px;font-weight: 600;padding: 0 0 0 2em;">
                <br/>
				<p>Is fully insured with this company against liability under the Worker’s Compensation <br/>Act, 1998.</p>
			</td>
		</tr>	
		<tr>
			<td class="heading" colspan="2">Period of Insurance :&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;From {{ $startDate }} to {{ $endWorkerComDate }}</td>
		</tr>
		<tr>
			<td class="heading">Date :&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ date('d/m/Y')}}</td>
			<td class="sub-heading"><p style="width: 10em;">Signature : </p>
				<p><img src="{{ URL::to('/motor/img/1.jpg') }}" style="width: 50%;border-bottom: 1px solid;"></p>
			<p>Authorized Signatory</p></td>
		</tr>
		<tr>
			<td class="heading"></td>
			<td class="sub-heading"><p style="width: 10em;">Company Seal : </p>
				<p><img src="{{ URL::to('/motor/img/3.jpg') }}" style="width: 50%;border-bottom: 1px solid;"></p>
			</td>
		</tr>
		
		<tr>
			<td class="heading" colspan="2">The Schedule : </td>
		</tr>
		
		<tr style="padding: top 25px;">
			<td class="heading" ><br/><br/>No. of Employees</td>
			<td class="sub-heading" >Est. Annual Earnings</td>
		</tr>
		
		<tr style="padding: top 25px;">
			<td class="heading" ><br/><br/>{{ $workerEmpType }}</td>
			<td class="sub-heading" >P {{number_format((float)$workerEmpWages ?? "", 2, '.', ',')}}</td>
		</tr>
		
	</table>

</div>
</body>
</html>
