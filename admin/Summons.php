<?php
require_once 'admin_auth.php';

$blotterId = filter_input(INPUT_GET, 'blotter_id', FILTER_VALIDATE_INT) ?: 0;
$blotter = [];
$summonsError = '';

if ($blotterId > 0) {
    try {
        $stmt = $pdo->prepare('SELECT blotter_no, complainant_name, respondent_name FROM blotters WHERE id = ?');
        $stmt->execute([$blotterId]);
        $blotter = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        if (!$blotter) {
            $summonsError = 'The selected blotter record was not found.';
        }
    } catch (PDOException $e) {
        error_log('Summons blotter lookup failed: ' . $e->getMessage());
        $summonsError = 'The blotter record could not be loaded.';
    }
}

$fieldValue = static function (string $key) use ($blotter): string {
    return htmlspecialchars((string)($blotter[$key] ?? ''), ENT_QUOTES, 'UTF-8');
};
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
            font-family: "Times New Roman", Times, serif;
            color: #111;
        }

        .paper {
            width: 8.5in;
            min-height: 11in;
            margin: 24px auto;
            background: #fff;
            padding: 0.55in 0.62in 0.45in;
            box-sizing: border-box;
            box-shadow: 0 0 8px rgba(0,0,0,.18);
        }

        .actions {
            width: 8.5in;
            margin: 24px auto 0;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .actions button {
            border: 0;
            border-radius: 4px;
            padding: 9px 16px;
            background: #1f4e79;
            color: #fff;
            font-size: 14px;
            cursor: pointer;
        }

        .actions button:hover { background: #173b5c; }

        .record-status {
            width: 8.5in;
            margin: 12px auto 0;
            color: #7a1f1f;
            font-size: 13px;
        }

        .title {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 24px;
        }

        .center {
            text-align: center;
        }

        .address-line {
            font-size: 15px;
            line-height: 1.12;
            margin: 0;
        }

        .office-title {
            margin: 26px 0 16px;
            text-align: center;
            font-size: 15px;
            font-weight: 700;
        }

        .case-layout {
            display: grid;
            grid-template-columns: 1fr 1.55fr;
            column-gap: 26px;
            align-items: start;
            font-size: 14px;
        }

        .case-parties {
            padding-top: 14px;
        }

        .case-party {
            margin: 4px 0 10px;
        }

        .case-party-label {
            display: block;
            margin-top: 2px;
        }

        .case-meta .row {
            margin: 1px 0;
        }

        .case-meta .row > span {
            white-space: nowrap;
        }

        .return-page {
            width: 86%;
            margin: 0 auto;
        }

        .section-title {
            font-size: 16px;
            font-weight: 700;
            margin: 26px 0 18px;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.22em;
        }

        .line {
            display: inline-block;
            border: 0;
            border-bottom: 1px solid #111;
            border-radius: 0;
            min-height: 18px;
            vertical-align: bottom;
            padding: 0 2px;
            box-sizing: border-box;
            background: transparent;
            color: #111;
            font: inherit;
            outline: none;
            appearance: none;
            box-shadow: none;
        }

        input.line { width: 100%; }
        input.line:focus { border-bottom: 2px solid #1f4e79; }

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
            border: 0;
            border-bottom: 1px solid #111;
            min-height: 18px;
        }

        .text-block {
            margin-top: 14px;
            font-size: 14px;
            line-height: 1.22;
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
            .actions { display: none; }

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

            input.line { border-bottom-color: #111; }
        }
    </style>
</head>
<body>
    <?php if ($summonsError): ?>
        <div class="record-status"><?= htmlspecialchars($summonsError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <div class="actions">
        <button type="button" onclick="window.print()">Print summons</button>
    </div>
    <div class="paper">
        <div class="title">KP FORM NO. 9</div>

        <div class="center address-line">Republic of the Philippines</div>
        <div class="center address-line">Barangay San Agustin Novaliches</div>
        <div class="center address-line">Province of NCR</div>
        <div class="center address-line">CITY/MUNICIPALITY OF Quezon City</div>
        <div class="center address-line">Barangay San Agustine <?= $fieldValue('barangay') ?></div>

        <div class="office-title">OFFICE OF THE LUPON TAGAPAMAYAPA</div>

        <div class="case-layout">
            <div class="case-parties">
                <div class="case-party">
                    <span class="case-party-label"></span>
                </div>
                <div>
                     <br>
                    <br>
 
                </div>
                <div class="case-party">
                    <span class="case-party-label">-Against-</span>
                </div>
                <div class="case-party">
                    <input class="line" style="width: 100%;" type="text" name="respondents" aria-label="Respondent or respondents" value="<?= $fieldValue('respondent_name') ?>">
                    <span class="case-party-label">Respondent/s</span>
                </div>
            </div>
            <div class="case-meta">
                <div class="row">
                    <span>Barangay Case No.</span>
                    <input class="line" style="width: 150px;" type="text" name="barangay_case_no" aria-label="Barangay Case No." value="<?= $fieldValue('blotter_no') ?>">
                </div>

                <div class="row">
                    <span>For Complainant/s</span>
                    <input class="line" style="width: 150px;" type="text" name="complainants" aria-label="Complainant or complainants"  value="<?= $fieldValue('complainant_name') ?>">
                </div>
            </div>
        </div>
        

        <div class="section-title">SUMMONS</div>

        <div class="row">
            <span>TO:</span>
            <input class="line" style="flex: 1;" type="text" name="to" aria-label="To" value="<?= $fieldValue('respondent_name') ?>">
        </div>

        <div class="text-block">
            You are hereby summoned to appear before me in person together with your witnesses on the
            <input class="line" style="width: 110px;" type="text" name="appearance_day" aria-label="Appearance day"> day of
            <input class="line" style="width: 90px;" type="text" name="appearance_month" aria-label="Appearance month">, 20
            <input class="line" style="width: 38px;" type="text" name="appearance_year" aria-label="Appearance year"> at
            <input class="line" style="width: 170px;" type="text" name="appearance_time" aria-label="Appearance time"> o'clock in the afternoon/noon, then and there to answer to a complaint made before me, copy of which is attached hereto, for mediation/conciliation of your dispute with complainant/s.
        </div>

        <div class="text-block">
            You are hereby warned that if you refuse or willfully fail to appear in obedience to this summons, you may be barred from filing any counterclaim arising from said complaint.
        </div>

        <div class="text-block mt-24">
            FAIL NOT or else, face punishment as for contempt of court.
            This <input class="line" style="width: 150px;" type="text" name="issue_day" aria-label="Issue day"> day of <input class="line" style="width: 120px;" type="text" name="issue_month" aria-label="Issue month"> 20 <input class="line" style="width: 44px;" type="text" name="issue_year" aria-label="Issue year">
        </div>

        <div class="signature-line">
            Punong Barangay/Pangkat Chairperson<br>
            (Cross out which ever is not applicable)
        </div>

        <div class="return-page">
        <div class="section-title" style="font-size: 15px; margin-top: 26px;">KP FORM NO. 9, Page 2</div>

        <div class="center fw-bold" style="margin-top: 6px;">OFFICER'S RETURN</div>

        <div class="text-block">
            I served this summon upon respondent <input class="line" style="width: 150px;" type="text" name="served_respondent_one" aria-label="First served respondent" value="<?= $fieldValue('respondent_name') ?>"> on the
            <input class="line" style="width: 70px;" type="text" name="served_day" aria-label="Served day"> day of <input class="line" style="width: 80px;" type="text" name="served_month" aria-label="Served month"> 20
            <input class="line" style="width: 40px;" type="text" name="served_year" aria-label="Served year"> and upon respondent <input class="line" style="width: 130px;" type="text" name="served_respondent_two" aria-label="Second served respondent">
            by: (Write name of respondent/s) or by handing to him/her the same.
        </div>

        <div class="text-block">
            <input class="line" style="width: 280px;" type="text" name="received_by" aria-label="Received by respondent or respondents"> Respondent/s
        </div>

        <div class="text-block">
            <input class="line" style="width: 460px;" type="text" name="service_method" aria-label="Service method">
        </div>

        <div class="text-block">
            <input class="line" style="width: 220px;" type="text" name="officer_name" aria-label="Officer name">
            <input class="line" style="width: 120px;" type="text" name="officer_date" aria-label="Officer date">
        </div>

        <div class="text-block">
            <input class="line" style="width: 430px;" type="text" name="officer_signature" aria-label="Officer signature">
        </div>

        <div class="text-block">
            Officer
        </div>

        <div class="text-block mt-24">
            Received by Respondent/s representative/s:
        </div>

        <div class="row mt-24">
            <span>(Signature) [Date]</span>
            <input class="fill" type="text" name="respondent_signature_one" aria-label="First respondent signature and date">
        </div>

        <div class="row mt-18">
            <span>(Signature) [Date]</span>
            <input class="fill" type="text" name="respondent_signature_two" aria-label="Second respondent signature and date">
        </div>
        </div>
    </div>
    <div class="actions back-actions">
        <button type="button" onclick="window.history.back()">Back</button>
    </div>
</body>
</html>
