<!DOCTYPE HTML PUBLIC "-//W3C//Dtd HTML 4.0 transitional//EN">
<html>

<head>
    <TITLE>Policy Schedule</TITLE>
    <link rel="icon" href="https://d20dgglp0tqnyi.cloudfront.net/Document/Policy_Document/Header/logo_1.png" type="Alpha Direct-Logo">
    <link
        href="https://fonts.googleapis.com/css?family=Lato:300,400,400i,700|Montserrat:300,400,500,600,700|Crete+Round:400i"
        rel="stylesheet" type="text/css" />
    <link href="{{asset('css/pdf_dom.css')}}" rel="stylesheet" type="text/css" />
    <!-- Latest compiled and minified CSS -->
</head>

<body LANG="en-ZA" LINK="#0000ff" DIR="Ltr">
    <section id="content" style="margin-bottom: 0px;">
        <div class="content-wrap">
            <div class="container clearfix">
                <div class="center" style="margin-bottom: 0in;text-align: center;">
                    <img src="logo_1.png">
                    <br>
                </div>
                <p align=right style="margin-bottom: 0in"><br></p>
                <p align=right style="margin-bottom: 0in"><span>{{ $today }}</span></p>
                <p style="margin-bottom: 0in"><br>Dear<span> {{ $name }}</span></p>
                <p align=center style="margin-bottom: 0in"><br></p>
                <p align=center style="margin-bottom: 0in"><span><U><B>Alpha Direct Domestic Motor Policy</B>
                        </U></span></p>
                <p style="margin-bottom: 0in"><B>Insured:</B><span> {{ $name }}</span>
                    <br>
                    <B>Policy Number:</B><span>{{ $policy->policyNumber }}</span>
                </p>
                <p style="margin-left: 0.1in; margin-bottom: 0in"><br></p>
                <p style="margin-bottom: 0in"><span>Thank you for activating a </span>
                    <span><B>digital insurance policy</B></span>{{ $policy->policyNumber }}
                    <span>with Alpha Direct Insurance Company.</span></p>

                <p style="margin-bottom: 0in">Should you require any <span>changes to your policy, or other information
                        or assistance,
                        you may reach our contact centre toll free at (0800) 601 029, or by email at:</span>
                    <A HREF="mailto:underwriting@alphadirect.co.bw">
                        <font color="#0563c1"><U>underwriting@alphadirect.co.bw</U></font>
                    </A>.
                </p>
                <p style="margin-bottom: 0in"><span>Most policy changes can be effected through our customer service
                        portal
                        at: </span><A href="http://www.alphadirect.co.bw">
                        <font color="#0563c1">
                            <span><U>www.alphadirect.co.bw</U></span>
                        </font>
                    </A>
                </p>

                <p style="margin-bottom: 0in"><span>Please
                        feel free to contact us should you require any details or information
                        on the information contained in this document.</span></p>

                <p style="margin-bottom: 0in"><span>We
                        are delighted to extend our incredible customer experience to you,
                        and trust you have found the process of working with us to be
                        effortless, simple and affordable.</span></p>

                <p style="margin-bottom: 0in"><span>Thank you for being a part of the Alpha Direct family.</span></p>
                <table style="border: 1px solid #ffffff;" class="table-responsive" width="100%">
                    <tr>
                        <td colspan=3 align=left>
                            <img src="sign.png" alt="">
                        </td>
                    </tr>
                    <tr>
                        <td colspan=4 align=right>
                            <img valign=top src="stamp.png" alt="">
                        </td>
                    </tr>
                </table>
                <Div align=center style="margin-bottom: 0in;">
                    <span>Toll Free: 0800 601 029 | Tel: (+267) 392 8264 | Fax: (+267) 392 8265 |</span><br>
                    <span>Office: Floor 2, Bar 2, Botswana Innovation Hub Icon Building, Plot 69184 Block 8
                        Industrial</span><br>
                    <span>Postal Address: P.O. Box 26ADC Gaborone, Botswana</span>
                    <hr>
                    <span style="color: #1f497D;">Kiosks Located Nationwide: Choppies Lobatse Barclays Mall | Choppies
                        Mahalapye Watershed Mall Choppies Francistown Loja Mall | Choppies Letlhakane | Choppies
                        Phakalane Mowana </span><br>
                    <span style="color: #1f497D;">Choppies Game City | Choppies Maun Boseja | Choppies Supa Save
                        Palapye</span>
                </div>
                {{--            <img class="img-fluid"  align="left" src="foot.png">--}}
                {{--            <br>--}}
                <p align=center style="margin-bottom: 0in; page-break-before: always">
                    <img src="logo_1.png">
                    <br>
                </p>
                <p align=center style="margin-bottom: 0in">
                    <font color="#1f497d">
                        <font face="Arial, serif">
                            <font SIZE=3>
                                <B>
                                    *This Policy should be read in conjunction with the Policy wording
                                </B></font>
                        </font>
                    </font>
                </p>
                <p style="margin-bottom: 0in"><br>
                </p>
                <div>
                    <table class="table-responsive" style="width: 100%;">
                        <tr>
                            <td height=33 bgcolor="#dbe5f1" style="border: 1px solid #000001; padding: 0.01in">
                                <p style="margin-top: 0.17in">
                                    <font color="#000000"><B>POLICY NUMBER </B></font>
                                </p>
                            </td>
                            <td colspan=4 style="border: 1px solid #000001; padding: 0.01in">
                                <p style="margin-top: 0.17in;text-align: center;">{{ $policy->policyNumber }}</p>
                            </td>
                        </tr>
                        <tr>
                            <td height=33 bgcolor="#dbe5f1" style="border: 1px solid #000001; padding: 0.01in">
                                <p style="margin-top: 0.18in">
                                    <font color="#000000"><B>TRANSACTION TYPE </B></font>
                                </p>
                            </td>
                            <td colspan=4 style="border: 1px solid #000001; padding: 0.01in">
                                <p style="margin-top: 0.17in;text-align: center;">
                                    {{ $schedule->transaction_type }}
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td height=30 bgcolor="#dbe5f1" style="border: 1px solid #000001; padding: 0.01in">
                                <p style="margin-top: 0.17in">
                                    <font color="#000000"><B>VERSION NUMBER </B></font>
                                </p>
                            </td>
                            <td colspan=4 style="border: 1px solid #000001; padding: 0.01in">
                                <p style="margin-top: 0.17in;text-align: center;">
                                    {{ $schedule->version_number }}
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td height=33 bgcolor="#dbe5f1" style="border: 1px solid #000001; padding: 0.01in">
                                <p style="margin-top: 0.17in">
                                    <font color="#000000"><B>INSURED
                                            / YOU </B></font>
                                </p>
                            </td>
                            <td colspan=4 style="border: 1px solid #000001; padding: 0.01in">
                                <p style="margin-top: 0.17in;text-align: center;">
                                    {{ $name }}
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td height=33 bgcolor="#dbe5f1" style="border: 1px solid #000001; padding: 0.01in">
                                <p style="margin-top: 0.17in">
                                    <font color="#000000"><B>YOUR
                                            TAX NUMBER </B></font>
                                </p>
                            </td>
                            <td colspan=4 style="border: 1px solid #000001; padding: 0.01in">
                                <p style="margin-top: 0.17in">

                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td height=53 bgcolor="#dbe5f1" style="border: 1px solid #000001; padding: 0.01in">
                                <p style="margin-top: 0.17in">
                                    <font color="#000000"><B>INSURED
                                            BUSINESS DESCRIPTION </B></font>
                                </p>
                            </td>
                            <td colspan=4 style="border: 1px solid #000001; padding: 0.01in">
                                <p style="margin-top: 0.17in">

                                </p>
                            </td>
                        </tr>
                        <tr style="border: 1px solid #000001;">
                            <td height=33 bgcolor="#dbe5f1" style="border: 1px solid #000001; padding: 0.01in">
                                <p style="margin-top: 0.17in">
                                    <font color="#000000">
                                        <B>YOUR POSTAL ADDRESS </B></font>
                                </p>
                            </td>
                            <td colspan=4 style="border: 1px solid #000001; padding: 0.01in">
                                <p style="margin-top: 0.17in;text-align: center;">
                                    {{ $profile->address.','.$profile->city }}
                                </p>
                            </td>
                        </tr>
                        <tr style="border: 1px solid #000001;">
                            <td height=33 bgcolor="#dbe5f1" style="border: 1px solid #000001; padding: 0.01in">
                                <p style="margin-top: 0.17in">
                                    <font color="#000000"><B>INTERMEDIARY</B></font>
                                </p>
                            </td>
                            <td colspan=4 style="border: 1px solid #000001; padding: 0.01in">
                                <p style="margin-top: 0.17in;text-align: center;">
                                    {{ $policy->agent_id }}
                                </p>
                            </td>
                        </tr>
                        <tr style="border: 1px solid #000001;">
                            <td height=33 bgcolor="#dbe5f1" style="border: 1px solid #000001; padding: 0.01in">
                                <p style="margin-top: 0.17in">
                                    <font color="#000000"><B>INSURER</B></font>
                                </p>
                            </td>
                            <td colspan=4 style="border: 1px solid #000001;text-align:center; padding: 0.01in;">
                                <p style="margin-top: 0.17in">
                                    <B> Alpha Direct Insurance Company</B>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td height=33 bgcolor="#dbe5f1" style="border:1px solid #000001; padding: 0.01in">
                                <p style="margin-top: 0.17in">
                                    <font color="#000000"><B>TERRITORIAL LIMITS </B></font>
                                </p>
                            </td>
                            <td colspan=4 style="border: 1px solid #000001; padding: 0.01in">
                                <p style="margin-top: 0.17in;text-align: center;">
                                    {{ $schedule->territorial_limits }}
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td height=118 bgcolor="#dbe5f1" style="border: 1px solid #000001; padding: 0.01in">
                                <p style="margin-top: 0.17in">
                                    <font color="#000000"><B>PERIOD OF INSURANCE </B></font>
                                </p>
                            </td>
                            <td colspan=4 style="border: 1px solid #000001; padding: 0.01in">
                                <p style="margin-left: 0.12in; margin-top: 0.17in; margin-bottom: 0.18in">
                                    <B>From: {{ $fromDate }}</B>
                                </p>
                                <p style="margin-left: 0.12in; margin-top: 0.17in; margin-bottom: 0.18in">
                                    <B>To: {{ $toDate }}</B>
                                    <font color="#1f497d">:
                                    </font><br><span><br>Including both dates.</span>
                                </p>
                                <p style="margin-left: 0.12in; margin-top: 0.15in">and
                                    any subsequent period <span>the Company </span>agrees
                                    to renew this Policy or any section thereof subject to any revised
                                    terms required by the Company
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td height=33 bgcolor="#dbe5f1" style="border: 1px solid #000001; padding: 0.01in">
                                <p style="margin-top: 0.17in">
                                    <font color="#000000"><span><B>ANNIVERSARY
                                                / </B></span></font>
                                    <font color="#000000"><B>RENEWAL
                                            DATE </B></font>
                                </p>
                            </td>
                            <td colspan=4 style="border: 1px solid #000001; padding: 0.01in;text-align: center">
                                <p style="margin-top: 0.17in">
                                    {{ $anniversaryDate }}
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td height=33 bgcolor="#dbe5f1" style="border: 1px solid #000001; padding: 0.01in">
                                <p style="margin-top: 0.17in">
                                    <font color="#000000"><B>TYPE
                                            OF CONTRACT </B></font>
                                </p>
                            </td>
                            <td colspan=4 style="border: 1px solid #000001; padding: 0.01in;text-align: center">
                                <p style="margin-top: 0.17in">
                                    @if($policy->premium_freq == null)
                                    {{-- {{ $schedule->contract_type }}--}}
                                    Monthly
                                    @elseif($policy->premium_freq == 1)
                                    Monthly
                                    @elseif($policy->premium_freq == 2)
                                    3 Installments
                                    @elseif($policy->premium_freq == 1)
                                    Yearly
                                    @endif

                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td height=33 bgcolor="#dbe5f1" style="border: 1px solid #000001; padding: 0.01in">
                                <p style="margin-top: 0.17in">
                                    <font color="#000000"><B>EFFECTIVE DATE </B></font>
                                </p>
                            </td>
                            <td colspan=4 style="border: 1px solid #000001; padding: 0.01in;text-align: center">
                                <span style="margin-top: 0.17in;text-align: center">
                                    {{ $fromDate }}
                                </span><br>
                                <small style="text-align: center;">* Subject to successful pre-inspection</small>
                            </td>
                        </tr>
                        <tr>
                            <td height=33 bgcolor="#dbe5f1" style="border: 1px solid #000001; padding: 0.01in">
                                <p style="margin-top: 0.17in">
                                    <font color="#000000"><B>ISSUED BY </B></font>
                                </p>
                            </td>
                            <td colspan=4 style="border: 1px solid #000001; padding: 0.01in;text-align: center">
                                <p style="margin-top: 0.17in">
                                    {{ $agentName }}
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td height=32 bgcolor="#dbe5f1" style="border: 1px solid #000001; padding: 0.01in">
                                <p style="margin-top: 0.17in">
                                    <font color="#000000"><B>PAYMENT TYPE </B></font>
                                </p>
                            </td>
                            <td colspan=4 style="border: 1px solid #000001; padding: 0.01in;text-align: center">
                                <p style="margin-top: 0.17in">
                                    @if($bankingDetail->billing == "RealPay")

                                    @if($policy->premium_freq == null)
                                    {{-- {{ $schedule->contract_type }}--}}
                                    Monthly through Bank Direct Debit Authorisation
                                    @elseif($policy->premium_freq == 1)
                                    Monthly through Bank Direct Debit Authorisation
                                    @elseif($policy->premium_freq == 2)
                                    3 Installments through Bank Direct Debit Authorisation
                                    @elseif($policy->premium_freq == 3)
                                    Yearly through Bank Direct Debit Authorisation
                                    @endif
                                    @else
                                    {{ $bankingDetail->billing }}
                                    @endif

                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                <p style="margin-bottom: 0in"><br>
                </p>
                <table style="border: 1px solid #ffffff;" class="table-responsive" width="100%">
                    <tr>
                        <td colspan=4 align=left>
                            <img src="sign.png" alt="">
                        </td>
                    </tr>
                    <tr>
                        <td colspan=4 align=right>
                            <img valign=top src="stamp.png" alt="">
                        </td>
                    </tr>
                </table>
                <p style="margin-bottom: 0in">
                    <span>SIGNED ON BEHALF OF ALPHA DIRECT INSURANCE CO. </span>
                </p>
                <p style="margin-bottom: 0in"><br>
                </p>
                <p style="margin-bottom: 0in"><span>ON: {{ $today }}</span></p>


























                <br>
                <p align=center style="margin-top: 0.18in; margin-bottom: 0.18in">
                    <font color="#1f497d">
                        <font face="Calibri, serif">
                            <font SIZE=4><B>PREMIUM
                                    SCHEDULE AND INDEX OF SECTIONS</B></font>
                        </font>
                    </font>
                </p>
                <table class="table-responsive" style="width: 100%;">

                    <tr valign=top>
                        <td height=32 bgcolor="#002060"
                            style="border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p style="margin-top: 0.18in">
                                <font face="Times New Roman, serif">
                                    <font SIZE=3>
                                        <font color="#ffffff">
                                            <font face="Calibri, serif"><B>Policy Sections</B>
                                                <font color="#ffffff">
                                                    <font face="Calibri, serif"><span><B>
                                                                Available</B></span>
                                                        <font color="#ffffff">
                                                            <font face="Calibri, serif"><B></B>
                            </p>
                        </td>
                        <td bgcolor="#002060"
                            style="border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p style="margin-top: 0.18in">
                                <font face="Times New Roman, serif">
                                    <font SIZE=3>
                                        <font color="#ffffff">
                                            <font face="Calibri, serif"><B>Section
                                                </B>
                                                <font color="#ffffff">
                                                    <font face="Calibri, serif"><span><B>Taken</B></span>
                            </p>
                        </td>
                        <td bgcolor="#002060"
                            style="border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p style="margin-top: 0.18in">
                                <font color="#ffffff">
                                    <font face="Calibri, serif"><B>PRO. RATA PREMIUM</B></font>
                                </font>
                                <font color="#f79646"><span><B>(Incl.VAT)</B></span></font></SUp>
                            </p>
                        </td>
                        <td width=117 bgcolor="#002060"
                            style="border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p style="margin-top: 0.18in">
                                <font color="#ffffff">
                                    <font face="Calibri, serif"><B>ANNUAL GROSS</B></font>
                                </font>
                                <font color="#f79646"><span><B>(Incl.VAT)</B></span></font></SUp>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=15
                            style="border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>
                                <font color="#002060">
                                    <font face="Calibri, serif"><B>Fire</B>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p align=center>
                                <font face="Calibri, serif">No</font>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=15
                            style="border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>
                                <font color="#002060">
                                    <font face="Calibri, serif"><B>Buildings</B>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p align=center>
                                <font face="Calibri, serif">No</font>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=15
                            style="border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>
                                <font color="#002060">
                                    <font face="Calibri, serif"><B>Office
                                            Contents </B>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p align=center>
                                <font face="Calibri, serif">No</font>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=15
                            style="border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>
                                <font color="#002060">
                                    <font face="Calibri, serif"><B>Business
                                            Interruption </B>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p align=center>
                                <font face="Calibri, serif">No</font>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=15
                            style="border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>
                                <font color="#002060">
                                    <font face="Calibri, serif"><B>Accounts
                                            Receivable </B>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p align=center>
                                <font face="Calibri, serif">No</font>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=15
                            style="border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>
                                <font color="#002060">
                                    <font face="Calibri, serif"><B>Theft</B>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p align=center>
                                <font face="Calibri, serif">No</font>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=18
                            style="border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>
                                <font color="#002060">
                                    <font face="Calibri, serif"><B>Money</B>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p align=center>
                                <font face="Calibri, serif">No</font>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=15
                            style="border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>
                                <font color="#002060">
                                    <font face="Calibri, serif"><B>Glass</B>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p align=center>
                                <font face="Calibri, serif">No</font>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=15
                            style="border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>
                                <font color="#002060">
                                    <font face="Calibri, serif"><B>Fidelity</B>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p align=center>
                                <font face="Calibri, serif">No</font>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=15
                            style="border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>
                                <font color="#002060">
                                    <font face="Calibri, serif"><B>Goods In
                                            Transit </B>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p align=center>
                                <font face="Calibri, serif">No</font>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=15
                            style="border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>
                                <font color="#002060">
                                    <font face="Calibri, serif"><B>Business
                                            All Risks</B>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p align=center>
                                <font face="Calibri, serif">No</font>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=15
                            style="border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>
                                <font color="#002060">
                                    <font face="Calibri, serif"><B>Accidental
                                            Damage</B>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p align=center>
                                <font face="Calibri, serif">No</font>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=15
                            style="border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>
                                <font color="#002060">
                                    <font face="Calibri, serif"><B>Employers
                                            Liability</B>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p align=center>
                                <font face="Calibri, serif">No</font>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=18
                            style="border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>
                                <font color="#002060">
                                    <font face="Calibri, serif"><B>Motor
                                            Fleet</B>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p align=center>
                                <font face="Calibri, serif">No</font>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=15
                            style="border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>
                                <font color="#002060">
                                    <font face="Calibri, serif"><B>Stated
                                            Benefits</B>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p align=center>
                                <font face="Calibri, serif">No</font>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=15
                            style="border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>
                                <font color="#002060">
                                    <font face="Calibri, serif"><B>Personal Accident </B>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p align=center>
                                <font face="Calibri, serif">No</font>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=15
                            style="border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>
                                <font color="#002060">
                                    <font face="Calibri, serif"><B>Motor
                                        </B>
                                        <font color="#002060">
                                            <font face="Calibri, serif"><span><B>Personal Lines</B></span>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p align=center>
                                <font color="#0070c0">
                                    <font face="Calibri, serif"><span><B>Yes</B></span>
                            </p>
                        </td>

                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            @if($policy->first_premium != null && $policy->premium_freq == 1)
                            <p>P {{ number_format((float)$policy->first_premium_wvat, 2, '.', '') }}<br></p>
                            @else
                            <p>P 0.00<br></p>
                            @endif
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>P {{ number_format((float)$premium, 2, '.', '') }}<br></p>
                        </td>
                    </tr>
                    <tr>
                        <td height=15
                            style="border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>
                                <font color="#002060">
                                    <font face="Calibri, serif"><B>Motor
                                            Specified</B>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p align=center>
                                <font face="Calibri, serif">No</font>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=15
                            style="border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>
                                <font color="#002060">
                                    <font face="Calibri, serif"><B>Motor
                                            Traders External</B>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p align=center>
                                <font face="Calibri, serif">No</font>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=18
                            style="border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>
                                <font color="#002060">
                                    <font face="Calibri, serif"><B>Motor
                                            Traders Internal </B>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p align=center>
                                <font face="Calibri, serif">No</font>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=15
                            style="border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>
                                <font color="#002060">
                                    <font face="Calibri, serif"><B>House
                                            Holders</B>
                                    </font>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p align=center>
                                <font face="Calibri, serif">No</font>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=15
                            style="border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>
                                <font color="#002060">
                                    <font face="Calibri, serif"><B>House
                                            Owners</B>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p align=center>
                                <font face="Calibri, serif">No</font>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=15
                            style="border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>
                                <font color="#002060">
                                    <font face="Calibri, serif"><B>Electronic
                                            Equipment</B>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p align=center>
                                <font face="Calibri, serif">No</font>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=15
                            style="border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>
                                <font color="#002060">
                                    <font face="Calibri, serif"><B>Workers'
                                            Compensation </B>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p align=center>
                                <font face="Calibri, serif">No</font>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=15
                            style="border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>
                                <font color="#002060">
                                    <font face="Calibri, serif"><B>Public Liability</B>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p align=center>
                                <font face="Calibri, serif">No</font>
                            </p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br></p>
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>0<br></p>
                        </td>
                    </tr>
                    <tr>
                        <td colspan=2 gcolor="#002060"
                            style="border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p align=right>
                                <font color="#ffffff"><span><B>TOTAL PREMIUM:</B></span></font>
                            </p>
                        </td>

                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            @if($policy->first_premium_wvat != null && $policy->premium_freq == 1)
                            <p>P {{ number_format((float)$policy->first_premium_wvat, 2, '.', '') }}<br></p>
                            @else
                            <p>P 0.00<br></p>
                            @endif
                        </td>
                        <td valign=top
                            style="text-align:center;border: 1px solid #00000a; padding-top: 0in; padding-bottom: 0in; padding-left: 0.08in; padding-right: 0.08in">
                            <p>P {{ number_format((float)$premium, 2, '.', '') }}<br></p>
                        </td>
                    </tr>
                </table>
                <p style="margin-bottom: 0in"><br>
                </p>
                <table class="table-responsive" style="width: 100%;" cellpadding="1" cellspacing="0">
                    <colgroup>
                        <col width="auto">
                        <col width="auto">
                        <col width="auto">
                        <col width="auto">
                    </colgroup>
                    <tbody>
                        <tr>
                            <td width="auto" bgcolor="#002060"
                                style="border: 1px solid #000001; padding-top: 0.01in; padding-bottom: 0.01in; padding-left: 0.01in; padding-right: 0.01in">
                                <p align=center style="margin-top: 0.18in">
                                    <font color="#ffffff"><B>Effective Date</B></font>
                                </p>
                            </td>
                            <td width="auto" bgcolor="#ffffff"
                                style="border: 1px solid #000001; padding-top: 0.01in; padding-bottom: 0.01in; padding-left: 0.01in; padding-right: 0.01in">
                                <span style="margin-top: 0.18in;text-align: center">
                                    {{ $today }}</span><br>
                                <small style="text-align: center;">* Subject to successful pre-inspection</small>
                            </td>
                            <td width="auto" bgcolor="#002060"
                                style="border: 1px solid #000001; padding-top: 0.01in; padding-bottom: 0.01in; padding-left: 0.01in; padding-right: 0.01in">
                                <p align=center style="margin-top: 0.18in">
                                    <font color="#ffffff"><B>TOTAL PREMIUM</B></font>
                                </p>
                            </td>
                            <td width="auto" bgcolor="#ffffff"
                                style="border: 1px solid #000001; padding-top: 0.01in; padding-bottom: 0.01in; padding-left: 0.01in; padding-right: 0.01in">
                                <p style="margin-top: 0.18in;text-align: center">P
                                    {{ number_format((float)$premium, 2, '.', '') }}</p>
                            </td>
                        </tr>
                        <tr>
                            <td rowspan="2" width="auto" height="11" bgcolor="#ffffff"
                                style="border: 1px solid #000001; padding-top: 0.01in; padding-bottom: 0.01in; padding-left: 0.01in; padding-right: 0.01in">
                                <p style="margin-top: 0.18in">
                                    <font color="#1f497d">
                                        <font SIZE=2><B>PHYSICAL LOCATION</B>
                                </p>
                            </td>
                            <td colspan="3" width="auto" bgcolor="#ffffff"
                                style="border: 1px solid #000001; padding-top: 0.01in; padding-bottom: 0.01in; padding-left: 0.01in; padding-right: 0.01in">
                                <p style="margin-top: 0.18in;text-align: center;"><br>{{ $profile->address }}</p>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="3" width="auto" bgcolor="#ffffff"
                                style="border: 1px solid #000001; padding-top: 0.01in; padding-bottom: 0.01in; padding-left: 0.01in; padding-right: 0.01in">
                                <p style="margin-top: 0.18in;text-align: center;">
                                    <font color="#1f497d">{{ $profile->city }}</font>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="4" width="auto" height="11" bgcolor="#ffffff"
                                style="border: 1px solid #000001; padding-top: 0.01in; padding-bottom: 0.01in; padding-left: 0.01in; padding-right: 0.01in">
                                <p style="margin-top: 0.18in;text-align: center;">
                                    <font color="#1f497d">{{--{{ $profile->state }}--}}</font>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="4" width="auto" height="11" bgcolor="#ffffff"
                                style="border: 1px solid #000001; padding-top: 0.01in; padding-bottom: 0.01in; padding-left: 0.01in; padding-right: 0.01in">
                                <p style="margin-top: 0.18in">
                                    <font color="#1f497d"><B>DETAILS OF COVER</B></font>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" width="auto" height="11" bgcolor="#002060"
                                style="border: 1px solid #000001; padding-top: 0.01in; padding-bottom: 0.01in; padding-left: 0.01in; padding-right: 0.01in">
                                <p style="margin-top: 0.19in;color: white;text-align: center"><b>DESCRIPTION</b>
                                </p>
                            </td>
                            <td width="auto" bgcolor="#002060"
                                style="border: 1px solid #000001; padding-top: 0.01in; padding-bottom: 0.01in; padding-left: 0.01in; padding-right: 0.01in">
                                <p style="margin-top: 0.19in;color: white;text-align: center""><b>SUM INSURED</B></p>
                    </td>
                    <td width=" auto" bgcolor="#002060"
                                    style="border: 1px solid #000001; padding-top: 0.01in; padding-bottom: 0.01in; padding-left: 0.01in; padding-right: 0.01in">
                                    <p style="margin-top: 0.19in;color: white;text-align: center"><b>PREMIUM</B></p>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" width="auto" height="14"
                                style="border: 1px solid #000001; padding-top: 0.01in; padding-bottom: 0.01in; padding-left: 0.01in; padding-right: 0.01in">
                                <p style="margin-top: 0.18in;text-align: center;"><span>{{ $product->name }}</span></p>
                            </td>
                            <td width="auto"
                                style="border: 1px solid #000001; padding-top: 0.01in; padding-bottom: 0.01in; padding-left: 0.01in; padding-right: 0.01in">
                                <p style="margin-top: 0.19in;text-align: center;">
                                    P{{ number_format((float)$policy->sum_assured, 2, '.', '') }}<br>
                                </p>
                            </td>
                            <td width="auto"
                                style="border: 1px solid #000001; padding-top: 0.01in; padding-bottom: 0.01in; padding-left: 0.01in; padding-right: 0.01in">
                                <p style="margin-top: 0.19in;text-align: center;">P
                                    {{ number_format((float)$premium, 2, '.', '')  }}<br>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <table class="table-responsive" style="width: 100%;">
                    <tr>
                        <td colspan=3 height=12 valign=top bgcolor="#002060"
                            style="border: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.18in">
                                <font color="#ffffff"><B>VEHICLE DETAILS</B></font>
                            </p>
                        </td>
                        <td colspan=4 bgcolor="#002060" style="border: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.18in"><br></p>
                        </td>
                    </tr>
                    <tr>
                        <td height=13 bgcolor="#dbe5f1" style="border: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.18in"><B>MAKE</B></p>
                        </td>
                        <td bgcolor="#dbe5f1" style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.18in"><B>MODEL</B></p>
                        </td>
                        <td bgcolor="#dbe5f1" style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.18in"><B>REG NUMBER</B></p>
                        </td>
                        <td bgcolor="#dbe5f1" style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.18in"><span><B>VIN NUMBER</B></span></p>
                        </td>
                        <td bgcolor="#dbe5f1" style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.18in"><B>YEAR</B></p>
                        </td>
                        <td bgcolor="#dbe5f1" style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.18in; margin-bottom: 0.18in"><span><B>FINANCIAL
                                        INTEREST</B></span></p>
                        </td>
                        <td bgcolor="#dbe5f1" style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.18in"><B>SUM INSURED</B></p>
                        </td>
                    </tr>
                    <tr>
                        <td height=15 style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>{{ $vehicle->make }}</p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>{{ $vehicle->model }}</p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>{{ $vehicle->vehiclePlate }}</p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>To be provided on web
                                portal{{--{{ $vehicle->vinnumber }}--}}</p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>{{ $vehicle->year }}</p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                                @if($vehicle->financial_interest)
                                {{ $vehicle->financial_interest }}
                                @else
                                {{ $vehicle->financial_interest_other }}
                                @endif
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in">
                                <br>P{{ number_format((float)$policy->sum_assured, 2, '.', '') }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td height=15 style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br></p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br></p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br></p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br></p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br></p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br></p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br></p>
                        </td>
                    </tr>
                    <tr>
                        <td height=15 style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br></p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br></p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br></p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br></p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br></p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br></p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br></p>
                        </td>
                    </tr>
                    <tr>
                        <td height=15 style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br></p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br></p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br></p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br></p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br></p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br></p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br></p>
                        </td>
                    </tr>
                    <tr>
                        <td height=15 style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br></p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=15 style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=15 style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=15 style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=15 style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=15 style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=15 style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=15 style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=15
                            style="border-top: 1px solid #000001;text-align: center; border-bottom: 4.50pt solid #000001; border-left: 1px solid #000001; border-right: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td
                            style="border-top: 1px solid #000001;text-align: center; border-bottom: 4.50pt solid #000001; border-left: 1px solid #000001; border-right: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td
                            style="border-top: 1px solid #000001;text-align: center; border-bottom: 4.50pt solid #000001; border-left: 1px solid #000001; border-right: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td valign=top
                            style="border-top: 1px solid #000001;text-align: center; border-bottom: 4.50pt solid #000001; border-left: 1px solid #000001; border-right: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td
                            style="border-top: 1px solid #000001;text-align: center; border-bottom: 4.50pt solid #000001; border-left: 1px solid #000001; border-right: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td valign=top
                            style="border-top: 1px solid #000001;text-align: center; border-bottom: 4.50pt solid #000001; border-left: 1px solid #000001; border-right: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td
                            style="border-top: 1px solid #000001;text-align: center; border-bottom: 4.50pt solid #000001; border-left: 1px solid #000001; border-right: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td colspan=3 height=13 valign=top bgcolor="#002060"
                            style="border-top: 4.50pt solid #000001; border-bottom: 1px solid #000001; border-left: 1px solid #000001; border-right: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.18in">
                                <font color="#ffffff"><B>FIRST AMOUNT PAYABLE</B> (Excess)</font>
                            </p>
                        </td>
                        <td colspan=4 bgcolor="#002060"
                            style="border-top: 4.50pt solid #000001; border-bottom: 1px solid #000001; border-left: 1px solid #000001; border-right: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td colspan=2 height=13 bgcolor="#dbe5f1" style="border: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.18in"><B>DESCRIPTION
                                </B>
                            </p>
                        </td>
                        <td bgcolor="#dbe5f1" style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.18in"><B>MINIMUM
                                    %</B></p>
                        </td>
                        <td valign=top bgcolor="#dbe5f1" style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td bgcolor="#dbe5f1" style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.18in"><B>MINIMUM
                                    AMOUNT</B></p>
                        </td>
                        <td valign=top bgcolor="#dbe5f1" style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td bgcolor="#dbe5f1" style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.18in"><B>FAP
                                    BASIS</B></p>
                        </td>
                    </tr>
                    <tr>
                        <td colspan=2 height=13 style="border: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.18in">Own
                                Damage
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                                {{ $schedule->own_damage_min_perc }}
                            </p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                                {{ $schedule->own_damage_min_amt }}
                            </p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                                {{ $schedule->own_damage_fap_basis }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td colspan=2 height=14 style="border: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.18in">Theft/Hijacking
                                Excess<span>
                                    (each claim)</span></p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                                {{ $schedule->theft_min_perc }}
                            </p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                                {{ $schedule->theft_min_amt }}
                            </p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                                {{ $schedule->theft_min_fap_basis }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td colspan=2 height=14 style="border: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.18in">Underage
                                Driver &lt;30 Years</p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                                {{ $schedule->underage_min_perc }}
                            </p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                                Cumulative
                                {{--                            {{ $schedule->underage_min_amt }}--}}
                            </p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                                {{ $schedule->underage_fap_basis }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td colspan=2 height=14 style="border: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.18in">License
                                Issue &lt;2 Years from PolicyIssue</p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                                {{ $schedule->license_issue_min_perc }}
                            </p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in">
                                {{ $schedule->license_issue_min_amt }}
                            </p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                                {{ $schedule->license_issue_fap_basis }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td colspan=2 height=14 style="border: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.18in">Windscreen
                                <span>/
                                    Glass</span></p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                                {{ $schedule->windscreen_min_perc }}
                            </p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                                {{ $schedule->windscreen_min_amt }}
                            </p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                                {{ $schedule->windscreen_fap_basis }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td colspan=2 height=13 style="border: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.18in"><span>Loss
                                    of Keys</span></p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                                {{ $schedule->key_loss_min_perc }}
                            </p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                                {{ $schedule->key_loss_min_amt }}
                            </p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.18in"><br>
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.18in"><br>
                                {{ $schedule->key_loss_fap_basis }}
                            </p>
                        </td>
                    </tr>
                </table>













                <table class="table-responsive" style="width: 100%;">
                    <tr>
                        <td height=13 valign=top bgcolor="#002060" style="border: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.17in">
                                <font color="#ffffff"><span><B>EX</B></span></font>
                                <font color="#ffffff"><B>TENSIONS</B></font>
                            </p>
                        </td>
                        <td colspan=6 bgcolor="#002060" style="border: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.17in"><br>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=14 bgcolor="#dbe5f1" style="border: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.17in"><B>DESCRIPTION</B></p>
                        </td>
                        <td valign=top bgcolor="#dbe5f1" style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><span><B>INCLUDED</B></span></p>
                        </td>
                        <td bgcolor="#dbe5f1" style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in; margin-bottom: 0.18in"><B>SUM INSURED</B></p>
                        </td>
                        <td bgcolor="#dbe5f1" style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><B>PREMIUM</B></p>
                        </td>
                        <td bgcolor="#dbe5f1" style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in; margin-bottom: 0.18in"><B>FAP %</B></p>
                        </td>
                        <td bgcolor="#dbe5f1" style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in; margin-bottom: 0.18in"><B>FAP AMOUNT</B></p>
                        </td>
                        <td bgcolor="#dbe5f1" style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in; margin-bottom: 0.18in"><B>FAP BASIS</B></p>
                        </td>
                    </tr>
                    <tr>
                        <td height=18 style="border: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.17in">Riot And Strike</p>
                        </td>
                        <td valign=top style="border: 1px solid #000001;text-align: center; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->riots_strikes_included }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in;text-align: center;">
                            <p style="margin-top: 0.17in"><br>
                                {{ $schedule->riots_strikes_sum_insured }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->riots_strikes_premium }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->riots_strikes_fap_perc }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->riots_strikes_fap_amt }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->riots_strikes_fap_basis }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=14 style="border: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.17in">Fire And Explosion</p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->fire_explosion_included }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->fire_explosion_sum_insured }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->fire_explosion_premium }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->fire_explosion_fap_perc }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->fire_explosion_fap_amt }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->fire_explosion_fap_basis }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=14 style="border: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.17in">Window <span>G</span>lass</p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->window_glass_included }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->window_glass_sum_insured }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->window_glass_premium }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->window_glass_fap_perc }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->window_glass_fap_amt }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->window_glass_fap_basis }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td height=14 style="border: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.17in">Loss Of Keys <span>(Locks and keys)</span></p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->keylock_loss_included }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->keylock_sum_insured }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->keylock_premium }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->keylock_fap_perc }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->keylock_fap_amt }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->keylock_fap_basis }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=14 style="border: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.17in">Third Party Liability</p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->third_party_liability_included }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->third_party_liability_sum_insured }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->third_party_liability_premium }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->third_party_liability_fap_perc }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->third_party_liability_fap_amt }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->third_party_liability_fap_basis }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=14 style="border: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.17in">Wreckage Removal</p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->wreckage_removal_included }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->wreckage_removal_sum_insured }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->wreckage_removal_premium }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->wreckage_removal_fap_perc }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->wreckage_removal_fap_amnt }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->wreckage_removal_fap_basis }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=16 style="border: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.17in">Tow In Costs</p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->tow_in_cost_included }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->tow_in_cost_sum_insured }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br> {{ $schedule->tow_in_cost_premium }}</p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br> {{ $schedule->tow_in_cost_fap_perc }}</p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br> {{ $schedule->tow_in_cost_fap_amt }}</p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br> {{ $schedule->tow_in_cost_fap_basis }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td height=16 style="border: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.17in"><span>Audio Accessories</span></p>
                        </td>

                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br> {{ $schedule->audio_accessories_included }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>
                                {{ $schedule->audio_accessories_sum_insured }}</p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br> {{ $schedule->audio_accessories_premium }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br> {{ $schedule->audio_accessories_fap_perc }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br> {{ $schedule->audio_accessories_fap_amt }}
                            </p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br>{{ $schedule->audio_accessories_fap_basis }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td height=16 style="border: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.17in"><span>Car
                                    Hire – Theft/Hijack of the Vehicle</span></p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br> {{ $schedule->car_hire_included }}</p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br> {{ $schedule->car_hire_sum_insured }}</p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br> {{ $schedule->car_hire_premium }}</p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br> {{ $schedule->car_hire_fap_perc }}</p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br> {{ $schedule->car_hire_fap_amt }}</p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br> {{ $schedule->car_hire_fap_basis }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td height=15 style="border: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.17in"><span>Medical Expenses (Bodily Injury)</span></p>
                        </td>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br> {{ $schedule->medical_expense_included }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br> {{ $schedule->medical_expense_sum_insured }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br> {{ $schedule->medical_expense_premium }}</p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br> {{ $schedule->medical_expense_fap_perc }}
                            </p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br> {{ $schedule->medical_expense_fap_amt }}</p>
                        </td>
                        <td style="border: 1px solid #000001; padding: 0.01in">
                            <p align=center style="margin-top: 0.17in"><br> {{ $schedule->medical_expense_fap_basis }}
                            </p>
                        </td>
                    </tr>
                    <span>* FAP - First Amount Payable (Excess)</span>
                </table>
                {{--            <p  style="margin-bottom: 0in"><br>--}}
                </p>
                <p align="center" style="margin-bottom: 0in; page-break-before: always">
                    <img src="logo_1.png">
                </p>
                <table class="table-responsive" style="width: 100%;">
                    <tr>
                        <td bgcolor="#002060" style="border: 1px solid #000001; padding: 0.01in">
                            <p style="margin-top: 0.18in">
                                <font color="#ffffff"><span><B>Important Points</B></span></font>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td valign=top style="border: 1px solid #000001; padding: 0.01in">
                            <textarea style="width: 100%;" rows="4" cols="50">{{  $schedule->memo }}</textarea>
                        </td>
                    </tr>
                </table>
                <p style="margin-bottom: 0in"><br>
                </p>
                <p style="margin-bottom: 0in"><br>
                </p>
                <p style="margin-bottom: 0in"><br>
                </p>
                <p style="margin-bottom: 0in">
                    <font color="#002060">
                        <font SIZE=3><span><U><B>Important points:</B></U></span>
                </p>
                <p style="margin-bottom: 0in"><br>
                </p>
                <p style="margin-bottom: 0in"><br>
                </p>
                <p style="margin-bottom: 0in">
                    <font color="#002060"><B>Your </B></font>
                    <font color="#002060"><span><B>Alpha Direct Policy</B></span></font>
                </p>
                <p style="margin-bottom: 0in"><br>
                </p>
                <p style="margin-bottom: 0in">Your contract with us (<span>Alpha
                        Direct Insurance Company</span>) consists of this Policy schedule,
                    your Policy<span>wordings</span>, all written <span>and
                        digital </span>correspondence<span> and declarations</span>
                    and verbal agreements. You need to ensure that all the information is
                    correct. Incorrect information may influence the validity of the
                    contract and/or the outcome of your claim.
                </p>
                <p style="margin-bottom: 0in"><br>
                </p>
                <p style="margin-bottom: 0in">If anything (at all) is not correct,
                    please contact us immediately to have it updated.</p>

                <p style="margin-bottom: 0.100in"><br>
                </p>
                <p style="margin-bottom: 0.100in"><br>
                </p>
                <p style="margin-bottom: 0.100in"><br>
                </p>

                <Div align=center style="margin-bottom: 0in;">
                    <span>Toll Free: 0800 601 029 | Tel: (+267) 392 8264 | Fax: (+267) 392 8265 |</span><br>
                    <span>Office: Floor 2, Bar 2, Botswana Innovation Hub Icon Building, Plot 69184 Block 8
                        Industrial</span><br>
                    <span>Postal Address: P.O. Box 26ADC Gaborone, Botswana</span>
                    <hr>
                    <span style="color: #1f497D;">Kiosks Located Nationwide: Choppies Lobatse Barclays Mall | Choppies
                        Mahalapye Watershed Mall Choppies Francistown Loja Mall | Choppies Letlhakane | Choppies
                        Phakalane Mowana </span><br>
                    <span style="color: #1f497D;">Choppies Game City | Choppies Maun Boseja | Choppies Supa Save
                        Palapye</span>
                </div>
                <br>
            </div>
            <img class="img-fluid" style="float: left;" src="foot.png">
        </div>
    </section>
</body>

</html>
