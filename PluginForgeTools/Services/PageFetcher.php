<?php
/**
 * Copyright (c) Since 2024 InnoShop - All Rights Reserved
 *
 * @link       https://www.innoshop.com
 * @author     InnoShop <team@innoshop.com>
 * @license    https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace Plugin\PluginForgeTools\Services;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Psr\Http\Message\UriInterface;
use RuntimeException;

class PageFetcher
{
    public const MAX_REDIRECTS = 5;

    private const TIMEOUT = 12;

    private const CONNECT_TIMEOUT = 8;

    private const MAX_BODY_BYTES = 1572864;

    private const ALLOWED_MIME_PREFIXES = [
        'text/html',
        'application/xhtml',
        'application/xml',
        'text/xml',
    ];

    private UrlSecurityValidator $validator;

    public function __construct(UrlSecurityValidator $validator)
    {
        $this->validator = $validator;
    }

    public static function getInstance(): self
    {
        return new self(new UrlSecurityValidator);
    }

    /**
     * @return array{url:string,host:string,final_url:string,http_status:int,body:string,content_type:string,robots_header:string[],x_robots:string[],headers:array<string,mixed>}
     */
    public function fetch(string $inputUrl): array
    {
        $url = $this->validator->validate($inputUrl);

        $base = parse_url($url);
        if ($base === false || empty($base['host'])) {
            throw new InvalidArgumentException('url_invalid');
        }
        $host = strtolower((string) $base['host']);

        $pending = $this->newClient($host);

        try {
            $response = $pending->get($url);
        } catch (ConnectionException $e) {
            if (str_contains(strtolower($e->getMessage()), 'redirect')) {
                throw new RuntimeException('too_many_redirects', 0, $e);
            }
            throw new RuntimeException('timeout', 0, $e);
        } catch (RequestException $e) {
            $response = $e->response;
            if ($response === null) {
                throw new RuntimeException('fetch_failed', 0, $e);
            }
        }

        $finalUri = (string) ($response->effectiveUri() ?? $url);
        if ($finalUri !== '') {
            $this->validator->validate($finalUri);
        } else {
            $finalUri = $url;
        }

        $status = $response->status();
        if ($status >= 500) {
            throw new RuntimeException('fetch_failed');
        }

        $contentType = strtolower((string) $response->header('content-type'));
        $body        = (string) $response->body();

        $isValidType = false;
        foreach (self::ALLOWED_MIME_PREFIXES as $prefix) {
            if ($contentType === '' || str_starts_with($contentType, $prefix)) {
                $isValidType = true;
                break;
            }
        }
        if (! $isValidType) {
            throw new RuntimeException('invalid_content');
        }

        if (function_exists('mb_strlen')) {
            $body = mb_strcut($body, 0, self::MAX_BODY_BYTES);
        } else {
            $body = substr($body, 0, self::MAX_BODY_BYTES);
        }

        if ($status >= 400 && $body === '') {
            throw new RuntimeException('blocked');
        }

        $robotsHeaderRaw = $response->header('x-robots-tag');
        if (is_array($robotsHeaderRaw)) {
            $robotsHeader = array_map('strval', $robotsHeaderRaw);
        } elseif (is_string($robotsHeaderRaw) && $robotsHeaderRaw !== '') {
            $robotsHeader = [$robotsHeaderRaw];
        } else {
            $robotsHeader = [];
        }
        $headerBag = method_exists($response, 'headers') ? $response->headers() : [];
        if (is_callable([$response, 'header'])) {
            $xRobotsRaw = $response->header('x-robots-tag');
            if (is_array($xRobotsRaw)) {
                $robotsHeader = $xRobotsRaw;
            } elseif (is_string($xRobotsRaw) && $xRobotsRaw !== '') {
                $robotsHeader = [$xRobotsRaw];
            }
        }

        return [
            'url'           => $url,
            'host'          => $host,
            'final_url'     => $finalUri,
            'http_status'   => (int) $status,
            'body'          => $body,
            'content_type'  => $contentType,
            'robots_header' => $robotsHeader,
            'x_robots'      => $robotsHeader,
            'headers'       => is_array($headerBag) ? $headerBag : iterator_to_array($headerBag),
        ];
    }

    /**
     * @return array{found:bool,content:null|string,status:null|int,applied_to_url:bool,url:string}
     */
    public function fetchRobotsTxt(string $host, string $pageUrl): array
    {
        $parts  = parse_url($pageUrl);
        $scheme = ($parts['scheme'] ?? null) === 'https' ? 'https' : 'https';
        $url    = "{$scheme}://{$host}/robots.txt";

        $pending = $this->newClient($host);
        try {
            $response = $pending->withOptions(['verify' => true])->get($url);
        } catch (\Throwable $e) {
            return [
                'found'          => false,
                'content'        => null,
                'status'         => null,
                'applied_to_url' => true,
                'url'            => $url,
            ];
        }

        $status = $response->status();
        if ($status < 200 || $status >= 400) {
            return [
                'found'          => false,
                'content'        => null,
                'status'         => $status,
                'applied_to_url' => true,
                'url'            => $url,
            ];
        }

        $content = (string) $response->body();

        return [
            'found'          => true,
            'content'        => $content,
            'status'         => $status,
            'applied_to_url' => $this->robotsAllowsPath($content, '*', $parts['path'] ?? '/'),
            'url'            => $url,
        ];
    }

    /**
     * @return array{found:bool,url:string,status:null|int,urls:array<int,string>}
     */
    public function detectSitemap(string $host, string $finalUrl, string $robotsContent): array
    {
        $parsed = parse_url($finalUrl);
        $scheme = ($parsed['scheme'] ?? null) === 'http' ? 'http' : 'https';
        $urls   = [];
        if (is_string($robotsContent) && $robotsContent !== '') {
            $lines = preg_split("/\r\n|\n|\r/", $robotsContent) ?: [];
            foreach ($lines as $line) {
                $line = trim((string) $line);
                if (stripos($line, 'Sitemap:') === 0) {
                    $sitemapUrl = trim(substr($line, strlen('Sitemap:')));
                    if (filter_var($sitemapUrl, FILTER_VALIDATE_URL)) {
                        try {
                            $this->validator->validate($sitemapUrl);
                            $urls[] = $sitemapUrl;
                        } catch (\Throwable $e) {
                        }
                    }
                }
            }
        }

        $candidate = "{$scheme}://{$host}/sitemap.xml";
        $urls[]    = $candidate;

        foreach (array_unique($urls) as $sitemapUrl) {
            $status = $this->probeSitemap($sitemapUrl, $host);
            if ($status !== null) {
                return [
                    'found'  => true,
                    'url'    => $sitemapUrl,
                    'status' => $status,
                    'urls'   => array_values(array_unique($urls)),
                ];
            }
        }

        return [
            'found'  => false,
            'url'    => '',
            'status' => null,
            'urls'   => array_values(array_unique($urls)),
        ];
    }

    private function probeSitemap(string $url, string $host): ?int
    {
        $pending = $this->newClient($host);
        try {
            $response = $pending->head($url);
        } catch (\Throwable $e) {
            try {
                $response = $pending->get($url);
            } catch (\Throwable $e2) {
                return null;
            }
        }

        $status = $response->status();

        return $status >= 200 && $status < 400 ? $status : null;
    }

    private function robotsAllowsPath(string $robots, string $userAgent, string $path): bool
    {
        $allows    = [];
        $disallows = [];
        $currentUa = null;
        $lines     = preg_split("/\r\n|\n|\r/", $robots) ?: [];
        foreach ($lines as $raw) {
            $line = trim((string) $raw);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (stripos($line, 'User-agent:') === 0) {
                $currentUa = strtolower(trim(substr($line, strlen('User-agent:'))));

                continue;
            }
            if ($currentUa !== $userAgent && $currentUa !== '*') {
                continue;
            }
            if (stripos($line, 'Disallow:') === 0) {
                $value = trim(substr($line, strlen('Disallow:')));
                if ($value !== '') {
                    $disallows[] = $value;
                }
            } elseif (stripos($line, 'Allow:') === 0) {
                $value = trim(substr($line, strlen('Allow:')));
                if ($value !== '') {
                    $allows[] = $value;
                }
            }
        }

        if ($allows === [] && $disallows === []) {
            return true;
        }

        $mostSpecific = null;
        $isAllow      = false;

        $patterns = [];
        foreach ($allows as $pattern) {
            $patterns[] = ['pattern' => $pattern, 'allow' => true];
        }
        foreach ($disallows as $pattern) {
            $patterns[] = ['pattern' => $pattern, 'allow' => false];
        }

        foreach ($patterns as $row) {
            $regex = $this->robotsPatternToRegex($row['pattern']);
            if (preg_match($regex, $path)) {
                if ($mostSpecific === null || strlen($row['pattern']) >= strlen($mostSpecific)) {
                    $mostSpecific = $row['pattern'];
                    $isAllow      = $row['allow'];
                }
            }
        }

        return $mostSpecific === null ? true : $isAllow;
    }

    private function robotsPatternToRegex(string $pattern): string
    {
        $escaped = preg_quote($pattern, '/');
        $escaped = str_replace('\*', '.*', (string) $escaped);
        if (str_ends_with($pattern, '$')) {
            $escaped = substr_replace($escaped, '$', -1);
        } else {
            $escaped .= '.*';
        }

        return '/^'.$escaped.'/';
    }

    private function newClient(string $host): PendingRequest
    {
        return Http::withHeaders([
            'User-Agent'      => 'PluginForgeTools/1.0 (+https://pluginforge.example; seo-checker)',
            'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.6',
            'Accept-Language' => 'en-US,en;q=0.9,pt-BR;q=0.8,es;q=0.7,zh-CN;q=0.6',
        ])
            ->timeout(self::TIMEOUT)
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->maxRedirects(self::MAX_REDIRECTS)
            ->withOptions([
                'on_redirect' => function (Request $request, Response $response, UriInterface $nextUri) {
                    $next = (string) $nextUri;
                    $this->validator->validate($next);
                },
            ]);
    }
}
