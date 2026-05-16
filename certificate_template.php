<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate of Achievement</title>
    <meta name="viewport" content="width=1600">
    <style>
        html, body {
            margin: 0;
            padding: 0;
            width: 1600px;
            height: 1131px;
            overflow: hidden;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            font-family: Georgia, 'Times New Roman', serif;
            color: #20333f;
            background:
                radial-gradient(circle at top left, rgba(255, 255, 255, 0.9), transparent 32%),
                radial-gradient(circle at bottom right, rgba(194, 153, 87, 0.18), transparent 30%),
                linear-gradient(135deg, #efe4cf 0%, #fbf8f1 48%, #f1e4ca 100%);
        }
        .certificate-page {
            width: 1600px;
            height: 1131px;
            padding: 46px;
        }
        .certificate-shell {
            width: 100%;
            height: 100%;
            border: 2px solid rgba(145, 111, 49, 0.72);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.94), rgba(255, 250, 240, 0.92)),
                repeating-linear-gradient(
                    135deg,
                    rgba(194, 153, 87, 0.04) 0,
                    rgba(194, 153, 87, 0.04) 18px,
                    rgba(255, 255, 255, 0) 18px,
                    rgba(255, 255, 255, 0) 36px
                );
            padding: 34px;
            position: relative;
        }
        .certificate-frame {
            width: 100%;
            height: 100%;
            border: 10px solid #c29957;
            outline: 2px solid rgba(64, 93, 108, 0.34);
            outline-offset: -16px;
            padding: 64px 78px 56px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            text-align: center;
            position: relative;
        }
        .certificate-topline {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 36px;
            font-family: Arial, Helvetica, sans-serif;
            text-transform: uppercase;
            letter-spacing: 0.34em;
            font-size: 17px;
            color: #6a7c86;
        }
        .certificate-brand {
            font-weight: 700;
            color: #2a4e60;
        }
        .certificate-issued {
            letter-spacing: 0.16em;
            font-size: 14px;
        }
        .certificate-crest {
            width: 118px;
            height: 118px;
            margin: 0 auto 26px;
            border-radius: 50%;
            border: 3px solid rgba(194, 153, 87, 0.85);
            display: flex;
            align-items: center;
            justify-content: center;
            background:
                radial-gradient(circle at 30% 30%, rgba(255, 255, 255, 0.92), rgba(255, 255, 255, 0.2)),
                linear-gradient(135deg, #274b5d, #18303c);
            box-shadow: 0 16px 32px rgba(34, 47, 56, 0.16);
            color: #f8e6b9;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 32px;
            font-weight: bold;
            letter-spacing: 0.14em;
        }
        .certificate-kicker {
            font-family: Arial, Helvetica, sans-serif;
            text-transform: uppercase;
            letter-spacing: 0.38em;
            font-size: 17px;
            color: #857148;
            margin-bottom: 16px;
        }
        .certificate-title {
            margin: 0;
            font-size: 68px;
            line-height: 1.08;
            color: #1d3340;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .certificate-subtitle {
            margin: 28px 0 18px;
            font-size: 24px;
            color: #6f7e86;
        }
        .certificate-text {
            margin: 0 auto;
            max-width: 1060px;
            font-size: 29px;
            line-height: 1.7;
            color: #2d4652;
        }
        .certificate-user {
            margin: 18px auto 24px;
            display: inline-block;
            font-size: 64px;
            font-weight: bold;
            color: #193342;
            border-bottom: 3px solid #c29957;
            padding: 0 40px 14px;
        }
        .certificate-highlight {
            color: #8b5e1a;
            font-weight: 700;
        }
        .certificate-meta-row {
            display: flex;
            gap: 24px;
            align-items: stretch;
            justify-content: space-between;
            margin-top: 44px;
        }
        .certificate-meta-card {
            flex: 1;
            text-align: left;
            padding: 24px 28px;
            border: 1px solid rgba(42, 78, 96, 0.16);
            background: rgba(255, 255, 255, 0.66);
        }
        .certificate-meta-label {
            display: block;
            font-family: Arial, Helvetica, sans-serif;
            text-transform: uppercase;
            letter-spacing: 0.2em;
            font-size: 12px;
            color: #7c8d96;
            margin-bottom: 12px;
        }
        .certificate-meta-value {
            font-size: 28px;
            color: #213640;
        }
        .certificate-seal {
            width: 172px;
            min-width: 172px;
            height: 172px;
            border-radius: 50%;
            border: 10px double rgba(194, 153, 87, 0.95);
            background:
                radial-gradient(circle at 35% 35%, rgba(255, 255, 255, 0.8), rgba(255, 255, 255, 0.08)),
                linear-gradient(145deg, #284e60, #152934);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #f7e1af;
            font-family: Arial, Helvetica, sans-serif;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            font-size: 18px;
            box-shadow: 0 18px 28px rgba(31, 43, 51, 0.16);
        }
        .signature {
            width: 320px;
            text-align: center;
        }
        .signature-line {
            width: 100%;
            height: 1px;
            background: #355263;
            margin-bottom: 14px;
        }
        .signature-name {
            font-weight: bold;
            font-size: 24px;
            color: #1f3743;
        }
        .signature-caption {
            display: block;
            margin-top: 8px;
            font-family: Arial, Helvetica, sans-serif;
            text-transform: uppercase;
            letter-spacing: 0.16em;
            font-size: 12px;
            color: #7f8d93;
        }
        .certificate-id {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
            letter-spacing: 0.16em;
            color: #71818a;
            text-transform: uppercase;
        }
        .certificate-footer {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 20px;
        }
    </style>
</head>
<body>
    <div class="certificate-page">
        <div class="certificate-shell">
            <div class="certificate-frame">
                <div>
                    <div class="certificate-topline">
                        <div class="certificate-brand">QuizPro Achievement Archive</div>
                        <div class="certificate-issued">Issued <?php echo htmlspecialchars($issuedOnLabel); ?></div>
                    </div>

                    <div class="certificate-crest">QP</div>
                    <div class="certificate-kicker">Verified Recognition</div>
                    <h1 class="certificate-title">Certificate of Achievement</h1>
                    <div class="certificate-subtitle">This certificate is proudly presented to</div>

                    <div class="certificate-user"><?php echo htmlspecialchars($attempt['username']); ?></div>

                    <div class="certificate-text">
                        For successfully completing the quiz
                        <span class="certificate-highlight"><?php echo htmlspecialchars($attempt['title']); ?></span>
                        with a score of
                        <span class="certificate-highlight"><?php echo (int) $attempt['score']; ?>/<?php echo (int) $attempt['total_marks']; ?> (<?php echo (int) $certificatePercent; ?>%)</span>.
                    </div>

                    <div class="certificate-meta-row">
                        <div class="certificate-meta-card">
                            <span class="certificate-meta-label">Completion Date</span>
                            <div class="certificate-meta-value"><?php echo date('F j, Y', strtotime($attempt['completed_at'])); ?></div>
                        </div>
                        <div class="certificate-meta-card">
                            <span class="certificate-meta-label">Certificate ID</span>
                            <div class="certificate-meta-value"><?php echo htmlspecialchars($certificateId); ?></div>
                        </div>
                        <div class="certificate-seal">QuizPro<br>Certified</div>
                    </div>
                </div>

                <div class="certificate-footer">
                    <div class="signature">
                        <div class="signature-line"></div>
                        <div class="signature-name">Quiz Administrator</div>
                        <span class="signature-caption">Authorized Signature</span>
                    </div>

                    <div class="certificate-id">Generated on <?php echo htmlspecialchars($issuedOnLabel); ?></div>

                    <div class="signature">
                        <div class="signature-line"></div>
                        <div class="signature-name"><?php echo htmlspecialchars($issuedOnLabel); ?></div>
                        <span class="signature-caption">Issue Date</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
