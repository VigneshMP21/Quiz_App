<?php

require_once __DIR__ . '/env.php';

return [
    'api_url' => rtrim((string) (env('CERTIFICATE_IMAGE_API_URL', 'https://www.html2image.net/api/api.php') ?? ''), "?& \t\n\r\0\x0B"),
    'api_key' => trim((string) (env('CERTIFICATE_IMAGE_API_KEY', '') ?? '')),
    'type' => strtolower((string) (env('CERTIFICATE_IMAGE_TYPE', 'png') ?? 'png')),
    'width' => (int) (env('CERTIFICATE_IMAGE_WIDTH', '1600') ?? '1600'),
    'height' => (int) (env('CERTIFICATE_IMAGE_HEIGHT', '1131') ?? '1131'),
    'quality' => (int) (env('CERTIFICATE_IMAGE_QUALITY', '100') ?? '100'),
    'zoom' => (float) (env('CERTIFICATE_IMAGE_ZOOM', '1') ?? '1'),
    'delay' => (int) (env('CERTIFICATE_IMAGE_DELAY', '0') ?? '0'),
    'fullpage' => filter_var((string) (env('CERTIFICATE_IMAGE_FULLPAGE', 'false') ?? 'false'), FILTER_VALIDATE_BOOLEAN),
    'transparent' => filter_var((string) (env('CERTIFICATE_IMAGE_TRANSPARENT', 'false') ?? 'false'), FILTER_VALIDATE_BOOLEAN),
    'grayscale' => filter_var((string) (env('CERTIFICATE_IMAGE_GRAYSCALE', 'false') ?? 'false'), FILTER_VALIDATE_BOOLEAN),
    'timeout' => (int) (env('CERTIFICATE_IMAGE_TIMEOUT', '60') ?? '60'),
    'verify_ssl' => filter_var((string) (env('CERTIFICATE_IMAGE_VERIFY_SSL', 'true') ?? 'true'), FILTER_VALIDATE_BOOLEAN),
];
