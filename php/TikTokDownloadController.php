<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Service\TikTok\TikTok;
use App\Http\Requests\FetchRequest;

class TikTokDownloadController extends Controller
{
    /**
     * Handle TikTok video fetch request
     */
    public function fetch(FetchRequest $request): JsonResponse
    {
        try {
            $video = (new TikTok)->getVideo($request->url);
            
            $downloadUrls = collect($video->downloads)
                ->sortByDesc('bitrate')
                ->map(function ($data, $key) {
                    $isHD = $key == 0;
                    return collect(data_get($data, 'urls', []))
                        ->take(config('app.link_per_bitrate', 2))
                        ->map(fn($url) => [
                            'url' => $url,
                            'isHD' => $isHD,
                            'size' => data_get($data, 'size')
                        ])->all();
                })
                ->flatten(1)
                ->reject(fn($item) => empty(data_get($item, 'url')))
                ->map(fn($data, $idx) => array_merge($data, compact('idx')))
                ->values();

            $data = [
                'author' => [
                    'username' => data_get($video, 'author.username'),
                    'avatar' => data_get($video, 'author.avatar.url'),
                ],
                'mp3URL' => data_get($video, 'music.downloadUrl'),
                'coverURL' => data_get($video, 'cover.url'),
                'watermark' => data_get($video, 'watermark'),
                'downloadUrls' => $downloadUrls,
                'caption' => $video->caption
            ];

            return response()->json($data);
        } catch (\Exception $exception) {
            $message = $exception->getMessage();
            $code = $exception->getCode();
            $status = $exception->getCode() ?: 500;

            $message = "[Error: $code]: $message";

            return response()->json(compact('message'), $status);
        }
    }

    /**
     * Handle TikTok video download request
     */
    public function download(Request $request)
    {
        if (!$request->filled('url') || empty($request->get('url')))
            return redirect()->route('home');

        return $this->streamDownloadResponse($request);
    }

    /**
     * Stream download response
     */
    private function streamDownloadResponse(Request $request)
    {
        $url = $request->get('url');
        $extension = $request->get('extension', 'mp4');
        $url = base64_decode($url);

        $filename = config("app.name", "TikTok Downloader") . '-' . time() . '.' . $extension;

        if (!ob_get_level()) ob_start();

        return response()->streamDownload(
            fn() => $this->streamFileContent($url),
            $filename,
            array_filter([
                'Content-Type' => 'application/octet-stream',
                'Content-Length' => $request->get('size'),
            ])
        );
    }

    /**
     * Stream file content
     */
    private function streamFileContent(string $url)
    {
        $ch = curl_init();
        $headers = array(
            'Range: bytes=0-',
        );
        $options = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FOLLOWLOCATION => true,
            CURLINFO_HEADER_OUT => true,
            CURLOPT_USERAGENT => 'okhttp',
            CURLOPT_ENCODING => "utf-8",
            CURLOPT_AUTOREFERER => true,
            CURLOPT_REFERER => 'https://www.tiktok.com/',
            CURLOPT_CONNECTTIMEOUT => 600,
            CURLOPT_TIMEOUT => 600,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_WRITEFUNCTION => function ($curl, $data) {
                echo $data;
                ob_flush();
                flush();
                return strlen($data);
            }
        );
        
        curl_setopt_array($ch, $options);
        
        if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }

        curl_exec($ch);
        curl_close($ch);
    }
}