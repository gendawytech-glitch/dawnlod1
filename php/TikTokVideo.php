<?php

namespace App\Service\TikTok;

use Illuminate\Support\Fluent;

/**
 * @property-read string|null $id
 * @property-read string|null $caption
 * @property-read string|null $url
 * @property-read array $author
 * @property-read array $cover
 * @property-read array $watermark
 * @property-read array $downloads
 * @property-read array $statistics
 * @property-read array|null $music
 */
class TikTokVideo extends Fluent
{

}
