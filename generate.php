<?php
// Load Composer autoloader (if using Composer)
require __DIR__ . '/vendor/autoload.php'; // Full path

// If manual install, use:
// require 'includes/dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Set data for the certificate
$student_name = "John Doe";
$course_name = "Advanced PHP Programming";
$score = "95";

// Enable remote images (if needed)
$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

// Load HTML content (you can also use file_get_contents)
$html = "
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; }
        .certificate { border: 2px solid #000; padding: 20px; margin: 0 auto; width: 800px; }
        h1 { color: #0066cc; }
        .signature { margin-top: 50px; }
    </style>
</head>
<body>
    <div class='certificate'>
        <h1>🎓 Certificate of Achievement</h1>
        <p>This is to certify that</p>
        <h2>$student_name</h2>
        <p>has successfully completed the course</p>
        <h3>$course_name</h3>
        <p>with a score of <strong>$score%</strong></p>
        <div class='signature'>
            <p>_________________________</p>
            <p>Authorized Signature</p>
        </div>
    </div>
</body>
</html>
";

// Load HTML into Dompdf
$dompdf->loadHtml($html);

// Set paper size (A4, Letter, etc.)
$dompdf->setPaper('A4', 'landscape');

// Render PDF
$dompdf->render();

// Output PDF (inline or download)
$dompdf->stream("certificate_$student_name.pdf", [
    'Attachment' => true // true = download, false = preview
]);
?>