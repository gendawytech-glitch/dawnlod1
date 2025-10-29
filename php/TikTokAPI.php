<?php

namespace App\Service\TikTok\Contracts;

use App\Exceptions\TikTokException;
use App\Service\TikTok\TikTokVideo;

interface TikTokAPI
{
    /**
     * @param string $url
     * @param string $id
     * @return TikTokVideo
     * @throws TikTokException
     */
    public function getVideo(string $url, string $id): TikTokVideo;
}
