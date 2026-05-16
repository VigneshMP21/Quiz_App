<?php

function getCertificateImageConfig(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $defaults = [
        'api_url' => '',
        'api_key' => '',
        'type' => 'png',
        'quality' => 100,
        'width' => 1600,
        'height' => 1131,
        'zoom' => 1,
        'delay' => 0,
        'fullpage' => false,
        'transparent' => false,
        'grayscale' => false,
        'timeout' => 60,
        'verify_ssl' => true,
    ];

    $configFile = __DIR__ . '/certificate_image_config.php';
    $fileConfig = is_file($configFile) ? require $configFile : [];

    if (!is_array($fileConfig)) {
        $fileConfig = [];
    }

    $config = array_merge($defaults, $fileConfig);

    return $config;
}

function renderCertificateImageFromHtml(string $html): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('cURL is required for HTML-to-image certificate generation.');
    }

    $config = getCertificateImageConfig();
    $apiUrl = trim((string) ($config['api_url'] ?? ''));
    $apiKey = trim((string) ($config['api_key'] ?? ''));
    $type = normalizeCertificateOutputType((string) ($config['type'] ?? 'png'));

    if ($apiUrl === '' || $apiKey === '') {
        throw new RuntimeException('Certificate image API configuration is incomplete. Set CERTIFICATE_IMAGE_API_KEY in .env.');
    }

    $width = max(1, min(4096, (int) ($config['width'] ?? 1600)));
    $height = max(1, min(4096, (int) ($config['height'] ?? 1131)));

    $url = $apiUrl . (strpos($apiUrl, '?') === false ? '?' : '') . http_build_query([
        'key' => $apiKey, 
        'type' => $type,
        'width' => $width,
        'height' => $height
    ]);

    $headers = [
        'Accept: application/json, image/*',
        'User-Agent: QuizPro Certificate Renderer',
        'Content-Type: application/x-www-form-urlencoded',
    ];
    
    // HTML2Image expects these fields in the POST body
    $requestData = [
        'key' => $apiKey,
        'type' => $type,
        'width' => $width,
        'height' => $height,
        'quality' => max(1, min(100, (int) ($config['quality'] ?? 100))),
        'zoom' => formatCertificateZoom((float) ($config['zoom'] ?? 1.0)),
        'delay' => max(0, (int) ($config['delay'] ?? 0)),
        'fullpage' => (bool) ($config['fullpage'] ?? false) ? 'true' : 'false',
        'transparent' => (bool) ($config['transparent'] ?? false) ? 'true' : 'false',
        'grayscale' => (bool) ($config['grayscale'] ?? false) ? 'true' : 'false',
        'source' => $html,
    ];

    $timeout = max(1, (int) ($config['timeout'] ?? 60));
    $verifySsl = (bool) ($config['verify_ssl'] ?? true);

    $response = sendCertificateImageRequest($url, $requestData, $headers, $timeout, $verifySsl);

    if (stripos($response['content_type'], 'image/') === 0) {
        return [
            'content' => $response['body'],
            'mime_type' => sanitizeMimeType($response['content_type']),
            'extension' => extensionFromMimeType($response['content_type'], $type),
        ];
    }

    $payload = decodeCertificateImageApiResponse($response['body']);
    if (isset($payload['__binary_content'], $payload['__binary_mime_type']) && is_string($payload['__binary_content']) && is_string($payload['__binary_mime_type'])) {
        return [
            'content' => $payload['__binary_content'],
            'mime_type' => $payload['__binary_mime_type'],
            'extension' => extensionFromMimeType($payload['__binary_mime_type'], $type),
        ];
    }

    $status = strtolower(trim((string) ($payload['Status'] ?? $payload['status'] ?? '')));

    if ($status !== '' && $status !== 'ok') {
        throw new RuntimeException(formatCertificateApiError($payload));
    }

    $imageUrl = extractCertificateImageUrl($payload);
    if ($imageUrl === null) {
        throw new RuntimeException('The certificate image API response did not contain a generated image link.');
    }

    return downloadCertificateImageFile($imageUrl, $type, $timeout, $verifySsl);
}

function sendCertificateImageRequest(string $url, array $data, array $headers, int $timeout, bool $verifySsl): array
{
    $curl = curl_init($url);

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => max(1, $timeout),
        CURLOPT_SSL_VERIFYPEER => $verifySsl,
        CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
    ]);

    $responseBody = curl_exec($curl);

    if ($responseBody === false) {
        $error = curl_error($curl);
        curl_close($curl);
        throw new RuntimeException('The certificate image API request failed: ' . $error);
    }

    $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $contentType = (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
    curl_close($curl);

    if ($statusCode >= 400) {
        throw new RuntimeException('The certificate image API returned HTTP ' . $statusCode . ': ' . extractCertificateApiError($responseBody));
    }

    return [
        'body' => $responseBody,
        'content_type' => $contentType,
    ];
}



function decodeCertificateImageApiResponse(string $body): array
{
    $decoded = json_decode($body, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    $detectedMimeType = detectImageMimeTypeFromBinary($body);
    if ($detectedMimeType !== null) {
        return [
            'Status' => 'OK',
            'Link' => null,
            '__binary_content' => $body,
            '__binary_mime_type' => $detectedMimeType,
        ];
    }

    throw new RuntimeException('The certificate image API returned an unexpected response.');
}

function normalizeCertificateOutputType(string $type): string
{
    $normalizedType = strtolower(trim($type));
    if ($normalizedType === 'jpeg') {
        return 'jpg';
    }

    return in_array($normalizedType, ['png', 'jpg', 'gif'], true) ? $normalizedType : 'png';
}

function formatCertificateZoom(float $zoom): string
{
    $formatted = number_format($zoom, 2, '.', '');
    $trimmed = rtrim(rtrim($formatted, '0'), '.');
    return $trimmed !== '' ? $trimmed : '1';
}

function formatCertificateApiError(array $payload): string
{
    $parts = [];

    $message = trim((string) ($payload['Message'] ?? $payload['message'] ?? $payload['error'] ?? ''));
    if ($message !== '') {
        $parts[] = $message;
    }

    $errorCode = trim((string) ($payload['ErrorCode'] ?? $payload['errorCode'] ?? $payload['code'] ?? ''));
    if ($errorCode !== '') {
        $parts[] = 'Code: ' . $errorCode;
    }

    $details = $payload['Details'] ?? $payload['details'] ?? null;
    if (is_string($details) && trim($details) !== '') {
        $parts[] = 'Details: ' . trim($details);
    }

    $hints = $payload['Hints'] ?? $payload['hints'] ?? null;
    if (is_array($hints) && !empty($hints)) {
        $firstHint = reset($hints);
        if (is_string($firstHint) && trim($firstHint) !== '') {
            $parts[] = 'Hint: ' . trim($firstHint);
        }
    }

    if (empty($parts)) {
        return 'Unknown API error.';
    }

    return implode(' ', $parts);
}

function extractCertificateImageUrl(array $payload): ?string
{
    $paths = [
        ['Link'],
        ['url'],
        ['link'],
        ['image_url'],
        ['imageUrl'],
        ['data', 'url'],
        ['data', 'Link'],
        ['data', 'image_url'],
        ['data', 'imageUrl'],
        ['image', 'url'],
    ];

    foreach ($paths as $path) {
        $value = $payload;

        foreach ($path as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                $value = null;
                break;
            }

            $value = $value[$segment];
        }

        if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }
    }

    return null;
}

function downloadCertificateImageFile(string $url, string $fallbackFormat, int $timeout, bool $verifySsl): array
{
    $curl = curl_init($url);

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => max(1, $timeout),
        CURLOPT_SSL_VERIFYPEER => $verifySsl,
        CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        CURLOPT_HTTPHEADER => ['Accept: image/*'],
    ]);

    $content = curl_exec($curl);

    if ($content === false) {
        $error = curl_error($curl);
        curl_close($curl);
        throw new RuntimeException('The generated certificate image could not be downloaded: ' . $error);
    }

    $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $contentType = (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
    curl_close($curl);

    if ($statusCode >= 400) {
        throw new RuntimeException('The generated certificate image could not be downloaded (HTTP ' . $statusCode . ').');
    }

    $mimeType = sanitizeMimeType($contentType);

    return [
        'content' => $content,
        'mime_type' => $mimeType,
        'extension' => extensionFromMimeType($mimeType, $fallbackFormat),
    ];
}

function sanitizeMimeType(string $contentType): string
{
    $parts = explode(';', strtolower(trim($contentType)));
    return $parts[0] !== '' ? $parts[0] : 'image/png';
}

function detectImageMimeTypeFromBinary(string $content): ?string
{
    if (strncmp($content, "\x89PNG\x0D\x0A\x1A\x0A", 8) === 0) {
        return 'image/png';
    }

    if (strncmp($content, "\xFF\xD8\xFF", 3) === 0) {
        return 'image/jpeg';
    }

    if (strncmp($content, "GIF87a", 6) === 0 || strncmp($content, "GIF89a", 6) === 0) {
        return 'image/gif';
    }

    if (strncmp($content, "RIFF", 4) === 0 && substr($content, 8, 4) === 'WEBP') {
        return 'image/webp';
    }

    return null;
}

function extensionFromMimeType(string $mimeType, string $fallbackFormat = 'png'): string
{
    $normalizedMimeType = sanitizeMimeType($mimeType);

    if ($normalizedMimeType === 'image/jpeg') {
        return 'jpg';
    }

    if ($normalizedMimeType === 'image/webp') {
        return 'webp';
    }

    if ($normalizedMimeType === 'image/gif') {
        return 'gif';
    }

    if ($normalizedMimeType === 'image/svg+xml') {
        return 'svg';
    }

    if ($normalizedMimeType === 'image/png') {
        return 'png';
    }

    $normalizedFormat = strtolower(trim($fallbackFormat));
    return in_array($normalizedFormat, ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'], true) ? ($normalizedFormat === 'jpeg' ? 'jpg' : $normalizedFormat) : 'png';
}

function extractCertificateApiError(string $body): string
{
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        return trim($body) !== '' ? trim($body) : 'Unknown API error.';
    }

    return formatCertificateApiError($decoded);
}

function getCertificateAbsolutePath(string $relativePath): string
{
    $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relativePath, '/\\'));
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . $normalized;
}

function generateAndSaveCertificate(int $attemptId, PDO $pdo): string
{
    // Fetch attempt details
    $stmt = $pdo->prepare("SELECT ua.*, q.title, q.total_marks, u.username 
                          FROM user_attempts ua
                          JOIN quizzes q ON ua.quiz_id = q.id
                          JOIN users u ON ua.user_id = u.id
                          WHERE ua.id = ?");
    $stmt->execute([$attemptId]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attempt) {
        throw new RuntimeException('Attempt not found.');
    }

    // Check if certificate already exists in DB
    $stmt = $pdo->prepare("SELECT id, certificate_path FROM certificates WHERE attempt_id = ?");
    $stmt->execute([$attemptId]);
    $existingCert = $stmt->fetch(PDO::FETCH_ASSOC);

    // Prepare template data
    $certificatePercent = $attempt['total_marks'] > 0
        ? round(((float) $attempt['score'] / (float) $attempt['total_marks']) * 100)
        : 0;
    
    $certificateId = sprintf(
        'CERT-%06d-%s',
        $attemptId,
        strtoupper(substr(sha1((string) $attempt['completed_at'] . ':' . (string) $attempt['username']), 0, 6))
    );
    $issuedOnLabel = date('F j, Y');

    // Generate HTML from template
    ob_start();
    // We need to make variables available for the template
    $attempt = $attempt; 
    include dirname(__DIR__) . '/certificate_template.php';
    $html = ob_get_clean();

    // Render image via API
    $image = renderCertificateImageFromHtml($html);

    // Prepare directory and filename
    $quizTitleSafe = sanitizePathName($attempt['title']);
    $usernameSafe = sanitizePathName($attempt['username']);
    
    $certDir = 'certificates/' . $quizTitleSafe . '/';
    $absoluteCertDir = getCertificateAbsolutePath($certDir);

    if (!is_dir($absoluteCertDir)) {
        if (!mkdir($absoluteCertDir, 0755, true) && !is_dir($absoluteCertDir)) {
            throw new RuntimeException('Failed to create certificate directory: ' . $absoluteCertDir);
        }
    }

    $filename = $usernameSafe . '.' . $image['extension'];
    $relativeFilepath = $certDir . $filename;
    $absoluteFilepath = getCertificateAbsolutePath($relativeFilepath);

    // Save file
    if (file_put_contents($absoluteFilepath, $image['content']) === false) {
        throw new RuntimeException('Failed to save certificate file to: ' . $absoluteFilepath);
    }

    // Update or insert into database
    if ($existingCert) {
        $stmt = $pdo->prepare("UPDATE certificates SET certificate_path = ?, downloaded_at = NOW() WHERE id = ?");
        $stmt->execute([$relativeFilepath, $existingCert['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO certificates (attempt_id, certificate_path, downloaded_at) VALUES (?, ?, NOW())");
        $stmt->execute([$attemptId, $relativeFilepath]);
    }

    return $relativeFilepath;
}

function sanitizePathName(string $name): string
{
    // Remove any character that isn't a letter, number, space, hyphen or underscore
    $sanitized = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $name);
    // Replace spaces with underscores
    return str_replace(' ', '_', trim($sanitized));
}

function outputCertificateDownload(string $absolutePath, ?string $downloadName = null): void
{
    if (!is_file($absolutePath)) {
        throw new RuntimeException('Certificate file not found.');
    }

    $mimeType = 'application/octet-stream';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected = finfo_file($finfo, $absolutePath);
            if (is_string($detected) && $detected !== '') {
                $mimeType = $detected;
            }
            finfo_close($finfo);
        }
    }

    if ($downloadName === null || $downloadName === '') {
        $downloadName = basename($absolutePath);
    }

    $downloadName = str_replace(["\r", "\n", '"'], '', $downloadName);

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . (string) filesize($absolutePath));
    header("Content-Disposition: attachment; filename=\"{$downloadName}\"; filename*=UTF-8''" . rawurlencode($downloadName));

    readfile($absolutePath);
    exit;
}
