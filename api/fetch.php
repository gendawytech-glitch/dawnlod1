<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['url']) || empty($data['url'])) {
    http_response_code(400);
    echo json_encode(['message' => 'URL is required']);
    exit;
}

require_once __DIR__ . '/StandaloneTikTokVideo.php';
require_once __DIR__ . '/StandaloneTikTok.php';

try {
    $tiktok = new StandaloneTikTok();
    $video = $tiktok->getVideo($data['url']);

    $downloadUrls = [];
    $downloads = $video->downloads ?? [];

    if (is_array($downloads)) {
        usort($downloads, function($a, $b) {
            return ($b['bitrate'] ?? 0) - ($a['bitrate'] ?? 0);
        });

        $idx = 0;
        foreach ($downloads as $key => $downloadData) {
            $isHD = $key === 0;
            $urls = $downloadData['urls'] ?? [];

            if (is_array($urls)) {
                $limit = 2;
                $count = 0;

                foreach ($urls as $url) {
                    if ($count >= $limit) break;
                    if (!empty($url)) {
                        $downloadUrls[] = [
                            'url' => $url,
                            'isHD' => $isHD,
                            'size' => $downloadData['size'] ?? null,
                            'idx' => $idx
                        ];
                        $idx++;
                        $count++;
                    }
                }
            }
        }
    }

    $author = $video->author ?? [];
    $music = $video->music ?? [];
    $cover = $video->cover ?? [];
    $watermark = $video->watermark ?? [];

    $response = [
        'author' => [
            'username' => $author['username'] ?? null,
            'avatar' => isset($author['avatar']) ? ($author['avatar']['url'] ?? null) : null,
        ],
        'mp3URL' => $music['downloadUrl'] ?? null,
        'coverURL' => $cover['url'] ?? null,
        'watermark' => $watermark,
        'downloadUrls' => $downloadUrls,
        'caption' => $video->caption ?? ''
    ];

    http_response_code(200);
    echo json_encode($response);

} catch (Exception $e) {
    $message = $e->getMessage();
    $code = $e->getCode() ?: 500;

    http_response_code($code >= 400 && $code < 600 ? $code : 500);
    echo json_encode([
        'message' => "[Error: $code]: $message"
    ]);
}
