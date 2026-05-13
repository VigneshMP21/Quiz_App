<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate of Achievement</title>
    <style>
        @page { margin: 0; }
        body { 
            margin: 0;
            font-family: 'Times New Roman', serif;
            background: url('assets/images/certificate_bg.jpg') no-repeat center center;
            background-size: cover;
            height: 100vh;
            width: 100vw;
        }
        .certificate-container {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 50px;
            box-sizing: border-box;
        }
        .certificate-header {
            margin-bottom: 40px;
        }
        .certificate-title {
            font-size: 36px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        .certificate-subtitle {
            font-size: 18px;
            color: #7f8c8d;
            margin-bottom: 30px;
        }
        .certificate-body {
            margin-bottom: 40px;
        }
        .certificate-text {
            font-size: 24px;
            line-height: 1.6;
            color: #34495e;
            margin-bottom: 20px;
        }
        .certificate-user {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
            margin: 20px 0;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
            display: inline-block;
        }
        .certificate-details {
            font-size: 18px;
            color: #7f8c8d;
            margin: 10px 0;
        }
        .certificate-footer {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
            width: 100%;
        }
        .signature {
            display: inline-block;
            margin-top: 40px;
        }
        .signature-line {
            border-top: 1px solid #2c3e50;
            width: 200px;
            margin: 0 auto;
        }
        .signature-name {
            margin-top: 10px;
            font-weight: bold;
        }
        .certificate-id {
            font-size: 14px;
            color: #95a5a6;
            margin-top: 30px;
        }
        .logo {
            max-width: 150px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="certificate-container">
        <div class="certificate-header">
            <img src="assets/images/logo.png" alt="Quiz App Logo" class="logo">
            <div class="certificate-title">Certificate of Achievement</div>
            <div class="certificate-subtitle">This certificate is proudly presented to</div>
        </div>
        
        <div class="certificate-body">
            <div class="certificate-user"><?php echo htmlspecialchars($attempt['username']); ?></div>
            <div class="certificate-text">
                for successfully completing the quiz<br>
                <strong><?php echo htmlspecialchars($attempt['title']); ?></strong><br>
                with a score of <?php echo $attempt['score']; ?> out of <?php echo $attempt['total_marks']; ?>
            </div>
            <div class="certificate-details">
                Completion Date: <?php echo date('F j, Y', strtotime($attempt['completed_at'])); ?>
            </div>
        </div>
        
        <div class="certificate-footer">
            <div class="signature">
                <div class="signature-line"></div>
                <div class="signature-name">Quiz Administrator</div>
            </div>
            <div class="signature">
                <div class="signature-line"></div>
                <div class="signature-name">Date: <?php echo date('F j, Y'); ?></div>
            </div>
        </div>
        
        <div class="certificate-id">
            Certificate ID: CERT-<?php echo strtoupper(uniqid()); ?>
        </div>
    </div>
</body>
</html>