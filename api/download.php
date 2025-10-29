<?php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed';
    exit;
}

if (!isset($_POST['url']) || empty($_POST['url'])) {
    http_response_code(400);
    echo 'URL is required';
    exit;
}

$encodedUrl = $_POST['url'];
$extension = $_POST['extension'] ?? 'mp4';
$size = $_POST['size'] ?? null;

$url = base64_decode($encodedUrl);

if (!filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo 'Invalid URL';
    exit;
}

// تحقق أن الرابط من TikTok أو مصدر موثوق
if (strpos($url, 'tiktok') === false) {
    http_response_code(400);
    echo 'Invalid TikTok URL';
    exit;
}

$filename = 'TikTok-Downloader-' . time() . '.' . $extension;

// إعدادات الهيدر
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
if ($size) {
    header('Content-Length: ' . $size);
}

// بدء buffer إذا لم يكن مفعلًا
if (!ob_get_level()) {
    ob_start();
}

$ch = curl_init();

$options = [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => false,
    CURLOPT_HEADER => false,
    CURLOPT_HTTPHEADER => ['Range: bytes=0-'],
    CURLOPT_FOLLOWLOCATION => true,
    CURLINFO_HEADER_OUT => true,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
    CURLOPT_ENCODING => 'utf-8',
    CURLOPT_AUTOREFERER => true,
    CURLOPT_REFERER => 'https://www.tiktok.com/',
    CURLOPT_CONNECTTIMEOUT => 30,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_WRITEFUNCTION => function ($curl, $data) {
        echo $data;
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
        return strlen($data);
    },
    CURLOPT_FAILONERROR => true, // إضافة هذا الخيار للكشف عن الأخطاء
];

curl_setopt_array($ch, $options);

if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
}

$response = curl_exec($ch);
$error = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($error) {
    error_log("Download error: " . $error);
    // إذا فشل التحميل، حاول طريقة بديلة باستخدام file_get_contents إذا كان مسموحًا
    $context = stream_context_create([
        'http' => [
            'header' => 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'timeout' => 30
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);
    
    $content = @file_get_contents($url, false, $context);
    if ($content !== false) {
        echo $content;
    } else {
        http_response_code(500);
        echo 'Download failed. Please try again.';
    }
}

if (ob_get_level()) {
    ob_end_flush();
}