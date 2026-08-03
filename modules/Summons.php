<?php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Summons</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 0.5in;
        }

        body {
            margin: 0;
            background: #ddd;
            font-family: Arial, Helvetica, sans-serif;
            color: #111;
        }

        .paper {
            width: 8.2in;
            min-height: 11in;
            margin: 24px auto;
            background: #fff;
            padding: 34px 42px 28px;
            box-sizing: border-box;
            box-shadow: 0 0 8px rgba(0,0,0,.18);
        }

        .title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 18px;
        }

        .center {
            text-align: center;
        }

        .address-line {
            font-size: 14px;
            line-height: 1.5;
            margin: 2px 0;
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            margin: 18px 0 12px;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .line {
            display: inline-block;
            border-bottom: 1px solid #111;
            min-height: 18px;
            vertical-align: bottom;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 4px;
        }

        .row {
            display: flex;
            align-items: flex-end;
            gap: 8px;
            margin: 6px 0;
        }

        .row > span {
            white-space: nowrap;
        }

        .fill {
            flex: 1;
            border-bottom: 1px solid #111;
            min-height: 18px;
        }

        .text-block {
            margin-top: 12px;
            font-size: 14px;
            line-height: 1.55;
            text-align: justify;
        }

        .signature-line {
            width: 100%;
            border-top: 1px solid #111;
            margin-top: 36px;
            text-align: center;
            padding-top: 8px;
            font-size: 13px;
        }

        .small {
            font-size: 13px;
        }

        .mt-18 { margin-top: 18px; }
        .mt-24 { margin-top: 24px; }
        .fw-bold { font-weight: 700; }

        @media print {
            body {
                background: #ffffff;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .paper {
                width: 100%;
                min-height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="paper">
        <div class="title">KP FORM NO. 9</div>

        <div class="center address-line">Republic of the Philippines</div>
        <div class="center address-line">Barangay San Agustin Novaliches</div>
        <div class="center address-line">Province of NCR</div>
        <div class="center address-line">CITY/MUNICIPALITY OF Quezon City</div>
        <div class="center address-line">OFFICE OF THE LUPON TAGAPAMAYAPA</div>

        <div class="row mt-24">
            <span>Barangay Case No.</span>
            <div class="fill"></div>
        </div>

        <div class="row">
            <span>For:</span>
            <div class="fill"></div>
        </div>

        <div class="row">
            <span>Complainant/s</span>
            <div class="fill"></div>
        </div>

        <div class="row mt-18">
            <span>Against:</span>
            <div class="fill"></div>
        </div>

        <div class="row mt-18">
            <span>Respondent/s</span>
            <div class="fill"></div>
        </div>

        <div class="section-title">SUMMONS</div>

        <div class="row">
            <span>TO:</span>
            <div class="fill"></div>
        </div>

        <div class="text-block">
            You are hereby summoned to appear before me in person together with your witnesses on the
            <span class="line" style="width: 110px;"></span> day of
            <span class="line" style="width: 90px;"></span>, 20
            <span class="line" style="width: 38px;"></span> at
            <span class="line" style="width: 170px;"></span> o'clock in the afternoon/noon, then and there to answer to a complaint made before me, copy of which is attached hereto, for mediation/conciliation of your dispute with complainant/s.
        </div>

        <div class="text-block">
            You are hereby warned that if you refuse or willfully fail to appear in obedience to this summons, you may be barred from filing any counterclaim arising from said complaint.
        </div>

        <div class="text-block mt-24">
            FAIL NOT or else, face punishment as for contempt of court.
            This <span class="line" style="width: 150px;"></span> day of <span class="line" style="width: 120px;"></span> 20 <span class="line" style="width: 44px;"></span>
        </div>

        <div class="signature-line">
            Punong Barangay/Pangkat Chairperson<br>
            (Cross out which ever is not applicable)
        </div>

        <div class="section-title" style="font-size: 15px; margin-top: 26px;">KP FORM NO. 9, Page 2</div>

        <div class="center fw-bold" style="margin-top: 6px;">OFFICER'S RETURN</div>

        <div class="text-block">
            I served this summon upon respondent <span class="line" style="width: 150px;"></span> on the
            <span class="line" style="width: 70px;"></span> day of <span class="line" style="width: 80px;"></span> 20
            <span class="line" style="width: 40px;"></span> and upon respondent <span class="line" style="width: 130px;"></span>
            by: (Write name of respondent/s) or by handing to him/her the same.
        </div>

        <div class="text-block">
            <span class="line" style="width: 280px;"></span> Respondent/s
        </div>

        <div class="text-block">
            <span class="line" style="width: 460px;"></span>
        </div>

        <div class="text-block">
            <span class="line" style="width: 220px;"></span>
            <span class="line" style="width: 120px;"></span>
        </div>

        <div class="text-block">
            <span class="line" style="width: 430px;"></span>
        </div>

        <div class="text-block">
            Officer
        </div>

        <div class="text-block mt-24">
            Received by Respondent/s representative/s:
        </div>

        <div class="row mt-24">
            <span>(Signature) [Date]</span>
            <div class="fill"></div>
        </div>

        <div class="row mt-18">
            <span>(Signature) [Date]</span>
            <div class="fill"></div>
        </div>
    </div>
</body>
</html>
