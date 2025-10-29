<?php

class StandaloneTikTok
{
    const MAX_RETRIES = 2;

    public function getVideo(string $url): StandaloneTikTokVideo
    {
        $retries = 0;

        do {
            $retries++;

            $response = $this->curlRequest($url);
            $body = $response['body'];
            $status = $response['status'];

            if ($status !== 200 || !str_contains($body, "id=\"SIGI_STATE\"")) {
                continue;
            }

            return $this->processResponse($body);

        } while ($retries <= self::MAX_RETRIES);

        throw new Exception("Unable to fetch video. The Video may be private, restricted or deleted.", 10);
    }

    protected function processResponse(string $html): StandaloneTikTokVideo
    {
        $id = $this->parseVideoId($html);
        $username = $this->parseString($html, 'uniqueId');
        $url = "https://www.tiktok.com/@$username/video/$id";

        if (!$id || !$username) {
            throw new Exception("Unable to fetch video. The Video may be private, restricted or deleted.", 20);
        }

        $downloadAddr = $this->parseString($html, 'downloadAddr');
        $playAddr = $this->parseString($html, 'playAddr');
        $downloadUrl = $this->escapeURL(is_string($downloadAddr) ? $downloadAddr : $playAddr);
        $size = $this->parseString($html, 'DataSize');

        $avatar = $this->parseString($html, 'avatarThumb');
        $nickname = $this->parseString($html, 'nickname');
        $caption = $this->parseString($html, 'desc');
        $coverUrl = $this->parseString($html, 'originCover');
        $musicUrl = $this->parseString($html, 'playUrl');
        $musicTitle = $this->parseString($html, 'title');

        $bitrateInfo = $this->extractBitrateInfo($html);

        $data = [
            'id' => $id,
            'url' => $url,
            'caption' => $caption,
            'author' => [
                'username' => $username,
                'nickname' => $nickname,
                'avatar' => [
                    'url' => $avatar
                ]
            ],
            'cover' => [
                'url' => $coverUrl
            ],
            'watermark' => [
                'url' => $downloadUrl,
                'size' => $size
            ],
            'downloads' => $bitrateInfo,
            'music' => [
                'downloadUrl' => $musicUrl,
                'title' => $musicTitle
            ]
        ];

        return new StandaloneTikTokVideo($data);
    }

    protected function extractBitrateInfo(string $html): array
    {
        $downloads = [];

        if (preg_match('/"bitrateInfo":\s*(\[.*?\])/s', $html, $matches)) {
            $bitrateJson = $matches[1];
            $bitrateData = json_decode($bitrateJson, true);

            if (is_array($bitrateData)) {
                foreach ($bitrateData as $item) {
                    if (isset($item['PlayAddr']['UrlList']) && is_array($item['PlayAddr']['UrlList'])) {
                        $downloads[] = [
                            'bitrate' => $item['Bitrate'] ?? 0,
                            'urls' => $item['PlayAddr']['UrlList'],
                            'size' => $item['PlayAddr']['DataSize'] ?? null
                        ];
                    }
                }
            }
        }

        if (empty($downloads)) {
            $playAddr = $this->parseString($html, 'playAddr');
            if ($playAddr) {
                $downloads[] = [
                    'bitrate' => 0,
                    'urls' => [$playAddr],
                    'size' => null
                ];
            }
        }

        usort($downloads, function($a, $b) {
            return ($b['bitrate'] ?? 0) - ($a['bitrate'] ?? 0);
        });

        return $downloads;
    }

    protected function curlRequest(string $url): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Linux; Android 12; SM-G996U) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/104.0.0.0 Mobile Safari/537.36');
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'body' => $body ?: '',
            'status' => $status
        ];
    }

    protected function parseVideoId(string $html): ?string
    {
        preg_match("/\"id\":\s?\"((\d|\w){6,})\"/", $html, $matches);
        return $matches[1] ?? null;
    }

    protected function parseString(string $html, string $key, $default = null): ?string
    {
        preg_match("/\"$key\":\s?\"([^\"]+?)\"/", $html, $matches);
        return isset($matches[1]) ? (string)$matches[1] : $default;
    }

    protected function escapeURL(?string $str): ?string
    {
        if (is_null($str)) return null;

        $regex = '/\\\u([dD][89abAB][\da-fA-F]{2})\\\u([dD][c-fC-F][\da-fA-F]{2})
              |\\\u([\da-fA-F]{4})/sx';

        return preg_replace_callback($regex, function ($matches) {

            if (isset($matches[3])) {
                $cp = hexdec($matches[3]);
            } else {
                $lead = hexdec($matches[1]);
                $trail = hexdec($matches[2]);
                $cp = ($lead << 10) + $trail + 0x10000 - (0xD800 << 10) - 0xDC00;
            }

            if ($cp > 0xD7FF && 0xE000 > $cp) {
                $cp = 0xFFFD;
            }

            if ($cp < 0x80) {
                return chr($cp);
            } else if ($cp < 0xA0) {
                return chr(0xC0 | $cp >> 6) . chr(0x80 | $cp & 0x3F);
            }

            return html_entity_decode('&#' . $cp . ';');
        }, $str);
    }
}
