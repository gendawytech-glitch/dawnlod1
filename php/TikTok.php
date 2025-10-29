<?php /** @noinspection SpellCheckingInspection */

namespace App\Service\TikTok;

use App\Exceptions\ProxyException;
use App\Exceptions\TikTokAPIException;
use App\Exceptions\TikTokVideoNotFoundException;
use App\Models\Proxy;
use App\Service\TikTok\Contracts\TikTokAPI;
use GuzzleHttp\Cookie\FileCookieJar;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class TikTok
{
    const MAX_RETRIES = 2;

    public function getVideo(string $url): TikTokVideo
    {
        $retries = 0;

        $proxies = Proxy::enabled()->get();

        do {
            $retries++;

            $response = TikTok::curlV2($url, $proxies);
            $body = $response->body();

            if (!$response->ok() || !str_contains($body, "id=\"SIGI_STATE\"")) {
                continue;
            }

            return $this->processResponse($body);

        } while ($retries <= TikTok::MAX_RETRIES);

        throw new TikTokVideoNotFoundException(null, 10);
    }


    protected function processResponse(string $html): TikTokVideo
    {
        $id = $this->parseVideoId($html);
        $username = $this->parseString($html, 'uniqueId');
        $url = "https://www.tiktok.com/@$username/video/$id";

        if (!$id || !$username) {
            throw new TikTokVideoNotFoundException("Unable to fetch video. The Video may be private, restricted or deleted.", 20);
        }

        $data = TikTok::hasLocalAPI() ?
            TikTok::useLocalAPI($url, $id) :
            TikTok::useRemoteAPI($url, $id);

        $downloadAddr = $this->parseString($html, 'downloadAddr');
        $playAddr = $this->parseString($html, 'playAddr');

        $downloadUrl = $this->escapeURL(is_string($downloadAddr) ? $downloadAddr : $playAddr);
        $size = $this->parseString($html, 'DataSize');

        $data = array_merge($data, [
            'watermark' => array_filter([
                'url' => $downloadUrl,
                'size' => $size
            ])
        ]);

        if (data_get($data, 'author') && !data_get($data, 'author.username')) {
            data_set($data, 'author.username', $username);
        }

        return new TikTokVideo($data);
    }

    protected static function useLocalAPI(string $url, string $id): array
    {
        /** @var TikTokAPI $api */
        $api = new API;
        return $api->getVideo($url, $id)->toArray();
    }

    protected static function useRemoteAPI(string $url, string $id): array
    {
        $response = Http::timeout(30)
            ->baseUrl(config("services.codespikex.api"))
            ->withUserAgent(request()->userAgent())
            ->acceptJson()
            ->withoutVerifying()
            ->get('/api/v2/tiktok/get-video', [
                'url' => $url,
                'id' => $id,
                'ip' => request()->ip(),
                'license' => config('app.license_key'),
                'domain' => config('app.url'),
            ]);

        if ($response->failed()) {
            throw new TikTokAPIException(
                $response->json('message', "Failed to connect to TikTok API."),
                $response->json('code', 500),
                $response->status()
            );
        }

        return $response->json();
    }

    public static function hasLocalAPI(): bool
    {
        return class_exists(\App\Service\TikTok\API::class);
    }

    protected static function curlV2(string $url, Collection $proxies): Response
    {
        /** @var Proxy $proxy */
        $proxy = null;

        if ($proxies->count() > 0) {
            $proxy = $proxies->random();
        }

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
            //browser's user agent string (UA)
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Linux; Android 12; SM-G996U) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/104.0.0.0 Mobile Safari/537.36');
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            if ($proxy) {
                curl_setopt($ch, CURLOPT_PROXY, $proxy->getHost());
                if ($proxy->auth)
                    curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy->getAuth());
            }

            $data = curl_exec($ch);
            curl_close($ch);
            $body = strval($data);
            return new Response(new GuzzleResponse(200, [], $body));
        } catch (Throwable $exception) {
            logger()->error($exception);
            throw new TikTokAPIException(null, 50, 500, $exception);
        }
    }

    protected static function http(string $url, Collection $proxies): Response
    {
        $jar = storage_path("/app/tiktok-cookies-v2.txt");

        /** @var Proxy $proxy */
        $proxy = null;

        if ($proxies->count() > 0) {
            $proxy = $proxies->random();
        }

        try {

            $client = Http::timeout(30)
                ->connectTimeout(30)
                ->withUserAgent('Mozilla/5.0 (Linux; Android 12; SM-G996U) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/104.0.0.0 Mobile Safari/537.36')
                ->withoutVerifying()
                ->maxRedirects(10)
                ->withHeaders([
                    'Referer' => 'https://www.tiktok.com/',
                    'Accept-Encoding' => 'gzip, deflate'
                ])
                ->withOptions([
                    'cookies' => new FileCookieJar($jar),
                ]);

            if ($proxy) {
                $client->withOptions([
                    'proxy' => $proxy->toUrl(),
                ]);
            }

            return $client->get($url);

        } catch (RequestException $exception) {
            if ($exception->getCode() == 407) {
                $proxy->disable();
                throw new ProxyException("The proxy is not working. Disabling proxy.");
            }
            logger()->error($exception);
        } catch (Throwable $exception) {
            logger()->error($exception);
            throw new TikTokAPIException(null, 50, 500, $exception);
        }

        return new Response(new GuzzleResponse(500));
    }

    /**
     * @deprecated
     */
    protected static function curl(string $url, Collection $proxies): Response
    {
        $ch = curl_init();

        $jar = storage_path("/app/tiktok-curl-cookies.txt");

        $options = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Linux; Android 5.0; SM-G900P Build/LRX21T) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Mobile Safari/537.36',
            CURLOPT_ENCODING => "utf-8",
            CURLOPT_AUTOREFERER => false,
            CURLOPT_COOKIEJAR => $jar,
            CURLOPT_COOKIEFILE => $jar,
            CURLOPT_REFERER => 'https://www.tiktok.com/',
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_MAXREDIRS => 10,
        );

        curl_setopt_array($ch, $options);

        if (
            defined('CURLOPT_IPRESOLVE') &&
            defined('CURL_IPRESOLVE_V4')
        ) {
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }

        if ($proxies->count() > 0) {
            /** @var Proxy $proxy */
            $proxy = $proxies->random();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_PROXY, $proxy->getHost());
            if (isset($proxy->auth))
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy->getAuth());
        }

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        $body = strval($body);

        return new Response(new GuzzleResponse($status, [], $body));
    }

    protected function parseVideoId(string $html): string|null
    {
        preg_match("/\"id\":\s?\"((\d|\w){6,})\"/", $html, $matches);
        return $matches[1] ?? null;
    }

    protected function parseString(string $html, string $key, $default = null): string|null
    {
        preg_match("/\"$key\":\s?\"([^\"]+?)\"/", $html, $matches);
        return isset($matches[1]) ? (string)$matches[1] : $default;
    }

    protected function escapeURL(string|null $str): string|null
    {
        if (is_null($str)) return null;

        // [U+D800 - U+DBFF][U+DC00 - U+DFFF]|[U+0000 - U+FFFF]
        $regex = '/\\\u([dD][89abAB][\da-fA-F]{2})\\\u([dD][c-fC-F][\da-fA-F]{2})
              |\\\u([\da-fA-F]{4})/sx';

        return preg_replace_callback($regex, function ($matches) {

            if (isset($matches[3])) {
                $cp = hexdec($matches[3]);
            } else {
                $lead = hexdec($matches[1]);
                $trail = hexdec($matches[2]);

                // http://unicode.org/faq/utf_bom.html#utf16-4
                $cp = ($lead << 10) + $trail + 0x10000 - (0xD800 << 10) - 0xDC00;
            }

            // https://tools.ietf.org/html/rfc3629#section-3
            // Characters between U+D800 and U+DFFF are not allowed in UTF-8
            if ($cp > 0xD7FF && 0xE000 > $cp) {
                $cp = 0xFFFD;
            }

            // https://github.com/php/php-src/blob/php-5.6.4/ext/standard/html.c#L471
            // php_utf32_utf8(unsigned char *buf, unsigned k)

            if ($cp < 0x80) {
                return chr($cp);
            } else if ($cp < 0xA0) {
                return chr(0xC0 | $cp >> 6) . chr(0x80 | $cp & 0x3F);
            }

            return html_entity_decode('&#' . $cp . ';');
        }, $str);
    }
}
