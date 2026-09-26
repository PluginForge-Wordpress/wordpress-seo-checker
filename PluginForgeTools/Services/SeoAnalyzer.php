<?php
/**
 * Copyright (c) Since 2024 InnoShop - All Rights Reserved
 *
 * @link       https://www.innoshop.com
 * @author     InnoShop <team@innoshop.com>
 * @license    https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace Plugin\PluginForgeTools\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;

class SeoAnalyzer
{
    public const LINK_LIMIT = 150;

    public const MAX_IMAGE_SCAN = 300;

    /**
     * @param  array<string,mixed>  $fetch
     * @param  array<string,mixed>  $robots
     * @param  array<string,mixed>  $sitemap
     * @return array{summary:array<string,mixed>,checks:array<string,array{status:string,value:mixed,message:string|null}>,issues:array<int,array{code:string,level:string,title:string,description:string,check_key:string|null}>,blocks:array<string,string>}
     */
    public function analyze(array $fetch, array $robots, array $sitemap): array
    {
        $body = (string) ($fetch['body'] ?? '');
        if ($body === '') {
            throw new RuntimeException('invalid_content');
        }

        $dom   = $this->loadDom($body);
        $xpath = new DOMXPath($dom);

        $targetParts  = parse_url((string) ($fetch['final_url'] ?? $fetch['url'] ?? ''));
        $targetHost   = strtolower((string) ($targetParts['host'] ?? ($fetch['host'] ?? '')));
        $targetScheme = strtolower((string) ($targetParts['scheme'] ?? 'https'));

        $title              = $this->extractText($xpath, '//head/title');
        $metaDescription    = $this->extractMetaContent($xpath, 'description');
        $metaRobots         = strtolower(trim((string) $this->extractMetaContent($xpath, 'robots')));
        $canonical          = (string) $this->extractLinkHref($xpath, 'canonical');
        $viewport           = $this->extractMetaContent($xpath, 'viewport');
        $htmlLang           = (string) $this->extractAttribute($xpath, '//*[local-name()="html"][1]', 'lang');
        $hreflangAlternates = $this->extractHreflang($xpath);

        $h1s     = $this->extractTextList($xpath, '//h1');
        $h2s     = $this->extractTextList($xpath, '//h2');
        $og      = $this->extractPrefixedMeta($xpath, 'og:');
        $twitter = $this->extractPrefixedMeta($xpath, 'twitter:');
        $schema  = $this->extractJsonLd($xpath);

        $images = $this->scanImages($xpath, $targetScheme, $targetHost);
        $links  = $this->scanLinks($xpath, $targetScheme, $targetHost, (string) ($fetch['final_url'] ?? $fetch['url'] ?? ''));

        $wpSignals        = $this->detectWordPress($body, $links);
        $wordpressPresent = $wpSignals['is_wp'];

        $xRobotsHeader   = array_map('strtolower', (array) ($fetch['x_robots'] ?? []));
        $xRobotsCombined = implode(',', $xRobotsHeader);

        $blocks             = [];
        $indexabilityStatus = 'pass';
        $status             = (int) ($fetch['http_status'] ?? 0);
        if ($status < 200 || $status >= 400) {
            $blocks['status_not_ok'] = 'status_not_ok';
            $indexabilityStatus      = 'fail';
        }
        if (str_contains($metaRobots, 'noindex')) {
            $blocks['meta_robots'] = 'blocked_meta_robots';
            $indexabilityStatus    = 'fail';
        }
        if (str_contains($xRobotsCombined, 'noindex')) {
            $blocks['x_robots'] = 'blocked_x_robots';
            $indexabilityStatus = 'fail';
        }
        if (isset($robots['applied_to_url']) && $robots['applied_to_url'] === false) {
            $blocks['robots_txt'] = 'blocked_robots_txt';
            $indexabilityStatus   = 'fail';
        }

        $redirectCount = (int) ($fetch['redirects'] ?? 0);
        if ($redirectCount >= PageFetcher::MAX_REDIRECTS) {
            $blocks['redirect_loop'] = 'redirect_loop';
            $indexabilityStatus      = 'warn';
        }

        $checks = [];
        $issues = [];

        $checks['title']            = $this->titleCheck($title, $issues);
        $checks['meta_description'] = $this->metaDescriptionCheck($metaDescription, $issues);
        $checks['h1']               = $this->h1Check($h1s, $issues);
        $checks['headings']         = $this->headingsCheck($h1s, $h2s, $issues);
        $checks['canonical']        = $this->canonicalCheck($canonical, (string) ($fetch['final_url'] ?? $fetch['url'] ?? ''), $issues);
        $checks['meta_robots']      = $this->metaRobotsCheck($metaRobots, $issues);
        $checks['x_robots_tag']     = $this->xRobotsCheck($xRobotsHeader, $issues);
        $checks['hreflang']         = $this->hreflangCheck($hreflangAlternates, $issues);
        $checks['https']            = $this->httpsCheck($targetScheme, $issues);
        $checks['viewport']         = $this->viewportCheck($viewport, $issues);
        $checks['html_lang']        = $this->htmlLangCheck($htmlLang, $issues);
        $checks['og_tags']          = $this->ogCheck($og, $issues);
        $checks['twitter_tags']     = $this->twitterCheck($twitter, $issues);
        $checks['schema']           = $this->schemaCheck($schema, $issues);
        $checks['images_alt']       = $this->imagesCheck($images, $issues);
        $checks['internal_links']   = $this->internalLinksCheck($links, $issues, $wordpressPresent);
        $checks['external_links']   = $this->externalLinksCheck($links, $issues);
        $checks['robots_txt']       = $this->robotsCheck($robots, $issues);
        $checks['sitemap']          = $this->sitemapCheck($sitemap, $issues);
        $checks['indexability']     = [
            'status'  => $blocks === [] ? 'pass' : $indexabilityStatus,
            'value'   => $blocks === [] ? true : false,
            'message' => $blocks === [] ? null : implode(', ', array_keys($blocks)),
            'meta'    => ['blockers' => $blocks],
        ];
        if ($blocks !== []) {
            $issues[] = [
                'code'        => 'not_indexable',
                'level'       => 'fail',
                'title'       => trans('PluginForgeTools::common.recommendations.not_indexable'),
                'description' => implode(', ', $blocks),
                'check_key'   => 'indexability',
            ];
        }

        $summary = [
            'input_url'          => (string) ($fetch['url'] ?? ''),
            'final_url'          => (string) ($fetch['final_url'] ?? ''),
            'host'               => (string) ($fetch['host'] ?? ''),
            'scheme'             => $targetScheme,
            'http_status'        => (int) ($fetch['http_status'] ?? 0),
            'content_type'       => (string) ($fetch['content_type'] ?? ''),
            'redirects'          => $redirectCount,
            'title'              => $title,
            'meta_description'   => $metaDescription,
            'canonical'          => $canonical,
            'meta_robots'        => $metaRobots,
            'x_robots_tag'       => $xRobotsHeader,
            'html_lang'          => $htmlLang,
            'viewport'           => (bool) $viewport,
            'h1_count'           => count($h1s),
            'h2_count'           => count($h2s),
            'hreflang_count'     => count($hreflangAlternates),
            'hreflang'           => $hreflangAlternates,
            'og'                 => $og,
            'twitter'            => $twitter,
            'schema_types'       => array_values(array_unique(array_filter(array_column($schema, 'type')))),
            'schema_count'       => count($schema),
            'images_total'       => (int) ($images['total'] ?? 0),
            'images_missing_alt' => (int) ($images['missing_alt'] ?? 0),
            'internal_links'     => (int) ($links['internal']['count'] ?? 0),
            'external_links'     => (int) ($links['external']['count'] ?? 0),
            'nofollow_internal'  => (int) ($links['internal']['nofollow_count'] ?? 0),
            'nofollow_external'  => (int) ($links['external']['nofollow_count'] ?? 0),
            'wordpress_signals'  => $wpSignals,
            'robots_txt'         => [
                'found'      => (bool) ($robots['found'] ?? false),
                'url'        => (string) ($robots['url'] ?? ''),
                'status'     => $robots['status'] ?? null,
                'allows_url' => (bool) ($robots['applied_to_url'] ?? true),
            ],
            'sitemap' => [
                'found'      => (bool) ($sitemap['found'] ?? false),
                'url'        => (string) ($sitemap['url'] ?? ''),
                'status'     => $sitemap['status'] ?? null,
                'candidates' => (array) ($sitemap['urls'] ?? []),
            ],
            'indexability' => [
                'is_indexable' => $blocks === [],
                'blockers'     => array_keys($blocks),
            ],
        ];

        return [
            'summary' => $summary,
            'checks'  => $checks,
            'issues'  => $issues,
            'blocks'  => $blocks,
        ];
    }

    private function loadDom(string $html): DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        $dom      = new DOMDocument;

        $prepend = '';
        if (! str_contains(strtolower($html), '<!doctype')) {
            $prepend = '<!DOCTYPE html>';
        }
        $wrapped = $prepend.$html;
        if (function_exists('mb_convert_encoding')) {
            $wrapped = mb_convert_encoding($wrapped, 'HTML-ENTITIES', 'UTF-8');
        }
        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $dom;
    }

    /**
     * @return array{total:int,missing_alt:int,samples:array<int,array{src:string,alt:string|null}>}
     */
    private function scanImages(DOMXPath $xpath, string $scheme, string $host): array
    {
        $nodes      = $xpath->query('//img');
        $total      = 0;
        $missingAlt = 0;
        $samples    = [];
        if ($nodes === false) {
            return ['total' => 0, 'missing_alt' => 0, 'samples' => []];
        }
        foreach ($nodes as $node) {
            if ($total >= self::MAX_IMAGE_SCAN) {
                break;
            }
            $total++;
            $alt = $this->nodeAttr($node, 'alt');
            $src = $this->nodeAttr($node, 'src');
            if ($alt === '') {
                $missingAlt++;
                if (count($samples) < 10) {
                    $samples[] = [
                        'src' => $this->absolutize($src, $scheme, $host),
                        'alt' => null,
                    ];
                }
            }
        }

        return [
            'total'       => $total,
            'missing_alt' => $missingAlt,
            'samples'     => $samples,
        ];
    }

    /**
     * @return array{internal:array<string,mixed>,external:array<string,mixed>}
     */
    private function scanLinks(DOMXPath $xpath, string $scheme, string $host, string $baseUrl): array
    {
        $nodes            = $xpath->query('//a[@href]');
        $internalCount    = 0;
        $externalCount    = 0;
        $internalNofollow = 0;
        $externalNofollow = 0;
        $internalSamples  = [];
        $externalSamples  = [];

        $seen = [];

        if ($nodes !== false) {
            foreach ($nodes as $node) {
                $total = $internalCount + $externalCount;
                if ($total >= self::LINK_LIMIT) {
                    break;
                }
                $href = $this->nodeAttr($node, 'href');
                if ($href === '') {
                    continue;
                }
                if (preg_match('/^(mailto|tel|javascript|data|sms|whatsapp):/i', $href)) {
                    continue;
                }
                $abs = $this->absolutize($href, $scheme, $host, $baseUrl);
                if ($abs === null) {
                    continue;
                }
                $parts = parse_url($abs);
                if (empty($parts['scheme']) || empty($parts['host'])) {
                    continue;
                }
                if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
                    continue;
                }
                $linkHost    = strtolower((string) $parts['host']);
                $rel         = strtolower($this->nodeAttr($node, 'rel'));
                $hasNofollow = str_contains($rel, 'nofollow');

                $isInternal  = $linkHost === $host || str_ends_with($linkHost, '.'.$host);
                $fingerprint = $abs;
                if (isset($seen[$fingerprint])) {
                    continue;
                }
                $seen[$fingerprint] = true;

                if ($isInternal) {
                    $internalCount++;
                    if ($hasNofollow) {
                        $internalNofollow++;
                    }
                    if (count($internalSamples) < 10) {
                        $internalSamples[] = ['href' => $abs, 'nofollow' => $hasNofollow];
                    }
                } else {
                    $externalCount++;
                    if ($hasNofollow) {
                        $externalNofollow++;
                    }
                    if (count($externalSamples) < 10) {
                        $externalSamples[] = ['href' => $abs, 'nofollow' => $hasNofollow];
                    }
                }
            }
        }

        return [
            'internal' => [
                'count'          => $internalCount,
                'nofollow_count' => $internalNofollow,
                'samples'        => $internalSamples,
            ],
            'external' => [
                'count'          => $externalCount,
                'nofollow_count' => $externalNofollow,
                'samples'        => $externalSamples,
            ],
        ];
    }

    /**
     * @return array{is_wp:bool,signals:array<string,bool>}
     */
    private function detectWordPress(string $body, array $links): array
    {
        $signals = [];

        $signals['wp_content']   = stripos($body, '/wp-content/') !== false;
        $signals['wp_includes']  = stripos($body, '/wp-includes/') !== false;
        $signals['generator_wp'] = preg_match('/<meta\s+name=["\']generator["\'][^>]*WordPress/i', $body) === 1;
        $signals['api_link']     = stripos($body, 'wp-json/wp/v2') !== false;
        $signals['wlwmanifest']  = stripos($body, 'wlwmanifest') !== false;
        $signals['shortlink_wp'] = preg_match('/<link\s+rel=["\']shortlink["\'][^>]+p=/i', $body) === 1;

        $combined             = implode(' ', array_column(array_merge($links['external']['samples'] ?? [], $links['internal']['samples'] ?? []), 'href'));
        $signals['feed_rss2'] = stripos($body, '/feed/') !== false || stripos($body, 'rss2') !== false || stripos($combined, '/feed/') !== false;

        $trueCount = count(array_filter($signals));

        return [
            'is_wp'   => $trueCount >= 1,
            'signals' => $signals,
        ];
    }

    /**
     * @return array<int, array{href:string,hreflang:string}>
     */
    private function extractHreflang(DOMXPath $xpath): array
    {
        $nodes = $xpath->query('//head/link[@rel="alternate" and @hreflang]');
        $out   = [];
        if ($nodes === false) {
            return $out;
        }
        foreach ($nodes as $node) {
            $href = $this->nodeAttr($node, 'href');
            $lang = $this->nodeAttr($node, 'hreflang');
            if ($href !== '' && $lang !== '') {
                $out[] = ['href' => $href, 'hreflang' => $lang];
            }
        }

        return $out;
    }

    /**
     * @return array<int, array{type:string|null,raw:mixed,context:string|null}>
     */
    private function extractJsonLd(DOMXPath $xpath): array
    {
        $nodes = $xpath->query('//script[@type="application/ld+json"]');
        $items = [];
        if ($nodes === false) {
            return $items;
        }
        foreach ($nodes as $node) {
            $raw = trim((string) $node->nodeValue);
            if ($raw === '') {
                continue;
            }
            $data = json_decode($raw, true);
            if (! is_array($data)) {
                continue;
            }
            $this->walkJsonLd($data, $items);
        }

        return $items;
    }

    /**
     * @param  array<string,mixed>  $node
     * @param  array<int, array{type:string|null,raw:mixed,context:string|null}>  $items
     */
    private function walkJsonLd(array $node, array &$items): void
    {
        if (isset($node['@type'])) {
            $type = $node['@type'];
            if (is_array($type)) {
                // @type pode ser uma lista (ex.: ["Person","Organization"]).
                // É o MESMO node com múltiplos tipos, não nodes diferentes:
                // um único item, com os tipos juntos, em vez de repetir o
                // dump inteiro do node uma vez por tipo.
                $typeLabel = implode(', ', array_values(array_filter($type, 'is_string')));
                $items[] = [
                    'type'    => $typeLabel !== '' ? $typeLabel : null,
                    'raw'     => $node,
                    'context' => $node['@context'] ?? null,
                ];
            } else {
                $items[] = [
                    'type'    => is_string($type) ? $type : null,
                    'raw'     => $node,
                    'context' => $node['@context'] ?? null,
                ];
            }
            if (isset($node['@graph']) && is_array($node['@graph'])) {
                foreach ($node['@graph'] as $child) {
                    if (is_array($child)) {
                        $this->walkJsonLd($child, $items);
                    }
                }
            }
        } else {
            foreach ($node as $value) {
                if (is_array($value)) {
                    $this->walkJsonLd($value, $items);
                }
            }
        }
    }

    private function extractText(DOMXPath $xpath, string $query): string
    {
        $nodes = $xpath->query($query);
        if ($nodes === false || $nodes->length === 0) {
            return '';
        }

        return trim((string) $nodes->item(0)->nodeValue);
    }

    /**
     * @return array<int,string>
     */
    private function extractTextList(DOMXPath $xpath, string $query): array
    {
        $out   = [];
        $nodes = $xpath->query($query);
        if ($nodes === false) {
            return $out;
        }
        foreach ($nodes as $node) {
            $val = trim((string) $node->nodeValue);
            if ($val !== '') {
                $out[] = $val;
            }
        }

        return $out;
    }

    private function extractMetaContent(DOMXPath $xpath, string $name): ?string
    {
        $queries = [
            '//head/meta[@name="'.$name.'" and @content]',
            '//head/meta[@property="'.$name.'" and @content]',
        ];
        foreach ($queries as $q) {
            $nodes = $xpath->query($q);
            $value = $this->firstNodeAttr($nodes, 'content');
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array<string,string>
     */
    private function extractPrefixedMeta(DOMXPath $xpath, string $prefix): array
    {
        $out  = [];
        $meta = $xpath->query('//head/meta[starts-with(@name,"'.$prefix.'") or starts-with(@property,"'.$prefix.'")]');
        if ($meta === false) {
            return $out;
        }
        foreach ($meta as $node) {
            $nameProp = $this->nodeAttr($node, 'name');
            if ($nameProp === '') {
                $nameProp = $this->nodeAttr($node, 'property');
            }
            $key   = $nameProp;
            $value = $this->nodeAttr($node, 'content');
            if ($key !== '' && str_starts_with(strtolower($key), strtolower($prefix))) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    private function extractLinkHref(DOMXPath $xpath, string $rel): ?string
    {
        $nodes = $xpath->query('//head/link[@rel="'.$rel.'" and @href]');

        return $this->firstNodeAttr($nodes, 'href');
    }

    private function extractAttribute(DOMXPath $xpath, string $query, string $attr): ?string
    {
        $nodes = $xpath->query($query);

        $value = $this->firstNodeAttr($nodes, $attr);
        if ($value === null || $value === '') {
            return null;
        }

        return trim((string) $value);
    }

    private function absolutize(string $href, string $scheme, string $host, string $baseUrl = ''): ?string
    {
        if ($href === '') {
            return null;
        }
        if (str_starts_with(strtolower($href), 'http://') || str_starts_with(strtolower($href), 'https://')) {
            return $href;
        }
        if (str_starts_with($href, '//')) {
            return $scheme.':'.$href;
        }
        if (str_starts_with($href, '/')) {
            return $scheme.'://'.$host.$href;
        }
        if (str_starts_with($href, '#') || str_starts_with($href, '?')) {
            if ($baseUrl === '') {
                return $scheme.'://'.$host.'/'.$href;
            }

            return explode('#', $baseUrl, 2)[0].$href;
        }

        if ($baseUrl !== '') {
            $baseParts = parse_url($baseUrl);
            if (! empty($baseParts['path'])) {
                $lastSlash = strrpos($baseParts['path'], '/');
                $dir       = $lastSlash === false ? '/' : substr($baseParts['path'], 0, $lastSlash + 1);
            } else {
                $dir = '/';
            }
            $s = $baseParts['scheme'] ?? $scheme;
            $h = $baseParts['host'] ?? $host;

            return $s.'://'.$h.$dir.$href;
        }

        return $scheme.'://'.$host.'/'.ltrim($href, '/');
    }

    /**
     * @param  array<int,array{code:string,level:string,title:string,description:string,check_key:string|null}>  $issues
     */
    private function titleCheck(?string $title, array &$issues): array
    {
        $len = $title === null ? 0 : mb_strlen($title);
        if ($title === null || $title === '') {
            $issues[] = $this->newIssue('title_missing', 'fail', 'title');

            return ['status' => 'fail', 'value' => null, 'message' => null];
        }
        $status = 'pass';
        if ($len < 30) {
            $issues[] = $this->newIssue('title_short', 'warn', 'title');
            $status   = 'warn';
        } elseif ($len > 60) {
            $issues[] = $this->newIssue('title_long', 'warn', 'title');
            $status   = 'warn';
        }

        return ['status' => $status, 'value' => compact('title', 'len'), 'message' => null];
    }

    /**
     * @param  array<int,array{code:string,level:string,title:string,description:string,check_key:string|null}>  $issues
     */
    private function metaDescriptionCheck(?string $desc, array &$issues): array
    {
        $len = $desc === null ? 0 : mb_strlen($desc);
        if ($desc === null || $desc === '') {
            $issues[] = $this->newIssue('meta_description_missing', 'warn', 'meta_description');

            return ['status' => 'warn', 'value' => null, 'message' => null];
        }
        $status = 'pass';
        if ($len < 70) {
            $issues[] = $this->newIssue('meta_description_short', 'info', 'meta_description');
            $status   = 'info';
        } elseif ($len > 160) {
            $issues[] = $this->newIssue('meta_description_long', 'warn', 'meta_description');
            $status   = 'warn';
        }

        return ['status' => $status, 'value' => compact('desc', 'len'), 'message' => null];
    }

    /**
     * @param  array<int, string>  $h1s
     * @param  array<int,array{code:string,level:string,title:string,description:string,check_key:string|null}>  $issues
     */
    private function h1Check(array $h1s, array &$issues): array
    {
        if (count($h1s) === 0) {
            $issues[] = $this->newIssue('h1_missing', 'fail', 'h1');

            return ['status' => 'fail', 'value' => 0, 'message' => null];
        }
        if (count($h1s) > 1) {
            $issues[] = $this->newIssue('h1_multiple', 'warn', 'h1');

            return ['status' => 'warn', 'value' => count($h1s), 'message' => null];
        }

        return ['status' => 'pass', 'value' => 1, 'message' => $h1s[0] ?? null];
    }

    /**
     * @param  array<int, string>  $h1s
     * @param  array<int, string>  $h2s
     * @param  array<int,array{code:string,level:string,title:string,description:string,check_key:string|null}>  $issues
     */
    private function headingsCheck(array $h1s, array $h2s, array &$issues): array
    {
        // H1 ausente/duplicado já é reportado por h1Check(); aqui só cuidamos
        // da estrutura de H2. H2 >= 1 indica alguma hierarquia, então não repetimos
        // aviso mesmo que H1 não seja exatamente 1.
        if (count($h2s) >= 1) {
            return ['status' => 'pass', 'value' => ['h1' => count($h1s), 'h2' => count($h2s)], 'message' => null];
        }

        $issues[] = $this->newIssue('headings_flat', 'info', 'headings');

        return ['status' => 'info', 'value' => ['h1' => count($h1s), 'h2' => 0], 'message' => null];
    }

    /**
     * @param  array<int,array{code:string,level:string,title:string,description:string,check_key:string|null}>  $issues
     */
    private function canonicalCheck(?string $canonical, string $finalUrl, array &$issues): array
    {
        if ($canonical === null || $canonical === '') {
            $issues[] = $this->newIssue('canonical_missing', 'warn', 'canonical');

            return ['status' => 'warn', 'value' => null, 'message' => null];
        }
        $a = preg_replace('/#.*$/', '', rtrim($canonical, '/'));
        $b = preg_replace('/#.*$/', '', rtrim($finalUrl, '/'));
        if (strcasecmp((string) $a, (string) $b) !== 0) {
            $issues[] = $this->newIssue('canonical_mismatch', 'info', 'canonical');

            return ['status' => 'info', 'value' => ['canonical' => $canonical, 'final' => $finalUrl], 'message' => 'mismatch'];
        }

        return ['status' => 'pass', 'value' => $canonical, 'message' => null];
    }

    /**
     * @param  array<int,array{code:string,level:string,title:string,description:string,check_key:string|null}>  $issues
     */
    private function metaRobotsCheck(string $robots, array &$issues): array
    {
        $value = [
            'raw'      => $robots,
            'noindex'  => str_contains($robots, 'noindex'),
            'nofollow' => str_contains($robots, 'nofollow'),
            'none'     => str_contains($robots, 'none'),
        ];
        if ($value['noindex'] || $value['none']) {
            $issues[] = $this->newIssue('meta_robots_noindex', 'fail', 'meta_robots');

            return ['status' => 'fail', 'value' => $value, 'message' => null];
        }

        return ['status' => 'pass', 'value' => $value, 'message' => null];
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int,array{code:string,level:string,title:string,description:string,check_key:string|null}>  $issues
     */
    private function xRobotsCheck(array $headers, array &$issues): array
    {
        $combined = implode(',', $headers);
        $value    = [
            'headers' => $headers,
            'noindex' => str_contains($combined, 'noindex'),
            'none'    => str_contains($combined, 'none'),
        ];
        if ($value['noindex'] || $value['none']) {
            $issues[] = $this->newIssue('x_robots_noindex', 'fail', 'x_robots_tag');

            return ['status' => 'fail', 'value' => $value, 'message' => null];
        }

        return ['status' => 'pass', 'value' => $value, 'message' => null];
    }

    /**
     * @param  array<int, array{href:string,hreflang:string}>  $alternates
     * @param  array<int,array{code:string,level:string,title:string,description:string,check_key:string|null}>  $issues
     */
    private function hreflangCheck(array $alternates, array &$issues): array
    {
        if (count($alternates) === 0) {
            $issues[] = $this->newIssue('hreflang_missing', 'info', 'hreflang');

            return ['status' => 'info', 'value' => 0, 'message' => null];
        }

        return ['status' => 'pass', 'value' => $alternates, 'message' => null];
    }

    /**
     * @param  array<int,array{code:string,level:string,title:string,description:string,check_key:string|null}>  $issues
     */
    private function httpsCheck(string $scheme, array &$issues): array
    {
        if ($scheme !== 'https') {
            $issues[] = $this->newIssue('https_off', 'fail', 'https');

            return ['status' => 'fail', 'value' => $scheme, 'message' => null];
        }

        return ['status' => 'pass', 'value' => $scheme, 'message' => null];
    }

    /**
     * @param  array<int,array{code:string,level:string,title:string,description:string,check_key:string|null}>  $issues
     */
    private function viewportCheck(?string $viewport, array &$issues): array
    {
        if ($viewport === null || $viewport === '') {
            $issues[] = $this->newIssue('viewport_missing', 'warn', 'viewport');

            return ['status' => 'warn', 'value' => null, 'message' => null];
        }

        return ['status' => 'pass', 'value' => $viewport, 'message' => null];
    }

    /**
     * @param  array<int,array{code:string,level:string,title:string,description:string,check_key:string|null}>  $issues
     */
    private function htmlLangCheck(?string $lang, array &$issues): array
    {
        if ($lang === null || $lang === '') {
            $issues[] = $this->newIssue('html_lang_missing', 'info', 'html_lang');

            return ['status' => 'info', 'value' => null, 'message' => null];
        }

        return ['status' => 'pass', 'value' => $lang, 'message' => null];
    }

    /**
     * @param  array<string,string>  $og
     * @param  array<int,array{code:string,level:string,title:string,description:string,check_key:string|null}>  $issues
     */
    private function ogCheck(array $og, array &$issues): array
    {
        $required = ['og:title', 'og:description', 'og:image', 'og:url'];
        $missing  = [];
        foreach ($required as $k) {
            if (! isset($og[$k]) || $og[$k] === '') {
                $missing[] = $k;
            }
        }
        if ($missing !== []) {
            $issues[] = $this->newIssue('og_missing', 'warn', 'og_tags');

            return ['status' => 'warn', 'value' => ['tags' => $og, 'missing' => $missing], 'message' => null];
        }

        return ['status' => 'pass', 'value' => ['tags' => $og], 'message' => null];
    }

    /**
     * @param  array<string,string>  $twitter
     * @param  array<int,array{code:string,level:string,title:string,description:string,check_key:string|null}>  $issues
     */
    private function twitterCheck(array $twitter, array &$issues): array
    {
        $card = $twitter['twitter:card'] ?? null;
        if ($card === null || $card === '') {
            $issues[] = $this->newIssue('twitter_missing', 'info', 'twitter_tags');

            return ['status' => 'info', 'value' => null, 'message' => null];
        }

        return ['status' => 'pass', 'value' => $twitter, 'message' => null];
    }

    /**
     * @param  array<int, mixed>  $schema
     * @param  array<int,array{code:string,level:string,title:string,description:string,check_key:string|null}>  $issues
     */
    private function schemaCheck(array $schema, array &$issues): array
    {
        if (count($schema) === 0) {
            $issues[] = $this->newIssue('schema_missing', 'info', 'schema');

            return ['status' => 'info', 'value' => [], 'message' => null];
        }

        return ['status' => 'pass', 'value' => $schema, 'message' => null];
    }

    /**
     * Safely read an attribute from a DOM node only when it is a DOMElement.
     * Prevents runtime errors and satisfies static analysers that do not
     * narrow DOMNodeList results.
     */
    private function nodeAttr(DOMNode $node, string $attr): string
    {
        if ($node instanceof DOMElement) {
            return trim((string) $node->getAttribute($attr));
        }

        return '';
    }

    /**
     * Same as nodeAttr() but for the first item of a DOMNodeList from
     * DOMXPath::item(0) — returns null when no item exists or it is not a DOMElement.
     *
     * @param  \DOMNodeList<DOMNode>|false  $nodes
     */
    private function firstNodeAttr(mixed $nodes, string $attr): ?string
    {
        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        $first = $nodes->item(0);
        if (! $first instanceof DOMElement) {
            return null;
        }

        $value = trim((string) $first->getAttribute($attr));
        if ($value === '') {
            return null;
        }

        return $value;
    }

    /**
     * @param  array<string,mixed>  $images
     * @param  array<int,array{code:string,level:string,title:string,description:string,check_key:string|null}>  $issues
     */
    private function imagesCheck(array $images, array &$issues): array
    {
        $total   = (int) ($images['total'] ?? 0);
        $missing = (int) ($images['missing_alt'] ?? 0);
        if ($total > 0 && $missing > 0) {
            $issues[] = $this->newIssue('images_missing_alt', 'warn', 'images_alt');

            return ['status' => 'warn', 'value' => $images, 'message' => null];
        }

        return ['status' => 'pass', 'value' => $images, 'message' => null];
    }

    /**
     * @param  array<string,mixed>  $links
     * @param  array<int,array{code:string,level:string,title:string,description:string,check_key:string|null}>  $issues
     */
    private function internalLinksCheck(array $links, array &$issues, bool $wordpressPresent): array
    {
        $count = (int) ($links['internal']['count'] ?? 0);
        if ($count === 0 && $wordpressPresent) {
            $issues[] = $this->newIssue('no_internal_links', 'warn', 'internal_links');

            return ['status' => 'warn', 'value' => $links['internal'], 'message' => null];
        }

        return ['status' => 'pass', 'value' => $links['internal'], 'message' => null];
    }

    /**
     * @param  array<string,mixed>  $links
     * @param  array<int,array{code:string,level:string,title:string,description:string,check_key:string|null}>  $issues
     */
    private function externalLinksCheck(array $links, array &$issues): array
    {
        $count = (int) ($links['external']['count'] ?? 0);
        if ($count === 0) {
            $issues[] = $this->newIssue('no_external_links', 'info', 'external_links');

            return ['status' => 'info', 'value' => $links['external'], 'message' => null];
        }

        return ['status' => 'pass', 'value' => $links['external'], 'message' => null];
    }

    /**
     * @param  array<string,mixed>  $robots
     * @param  array<int,array{code:string,level:string,title:string,description:string,check_key:string|null}>  $issues
     */
    private function robotsCheck(array $robots, array &$issues): array
    {
        if (empty($robots['found'])) {
            $issues[] = $this->newIssue('robots_txt_missing', 'warn', 'robots_txt');

            return ['status' => 'warn', 'value' => $robots, 'message' => null];
        }

        return ['status' => 'pass', 'value' => $robots, 'message' => null];
    }

    /**
     * @param  array<string,mixed>  $sitemap
     * @param  array<int,array{code:string,level:string,title:string,description:string,check_key:string|null}>  $issues
     */
    private function sitemapCheck(array $sitemap, array &$issues): array
    {
        if (empty($sitemap['found'])) {
            $issues[] = $this->newIssue('sitemap_missing', 'warn', 'sitemap');

            return ['status' => 'warn', 'value' => $sitemap, 'message' => null];
        }

        return ['status' => 'pass', 'value' => $sitemap, 'message' => null];
    }

    /**
     * @return array{code:string,level:string,title:string,description:string,check_key:string|null}
     */
    private function newIssue(string $code, string $level, ?string $checkKey = null): array
    {
        return [
            'code'        => $code,
            'level'       => $level,
            'title'       => (string) trans('PluginForgeTools::common.recommendations.'.$code),
            'description' => (string) trans('PluginForgeTools::common.recommendations.'.$code),
            'check_key'   => $checkKey,
        ];
    }
}
