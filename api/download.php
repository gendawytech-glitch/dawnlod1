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

$filename = 'TikTok-Downloader-' . time() . '.' . $extension;

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
if ($size) {
    header('Content-Length: ' . $size);
}

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
    CURLOPT_USERAGENT => 'okhttp',
    CURLOPT_ENCODING => 'utf-8',
    CURLOPT_AUTOREFERER => true,
    CURLOPT_REFERER => 'https://www.tiktok.com/',
    CURLOPT_CONNECTTIMEOUT => 600,
    CURLOPT_TIMEOUT => 600,
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
    }
];

curl_setopt_array($ch, $options);

if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
}

curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    error_log("Download error: " . $error);
}

if (ob_get_level()) {
    ob_end_flush();
}
