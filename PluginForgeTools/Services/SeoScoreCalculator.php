<?php
/**
 * Copyright (c) Since 2024 InnoShop - All Rights Reserved
 *
 * @link       https://www.innoshop.com
 * @author     InnoShop <team@innoshop.com>
 * @license    https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace Plugin\PluginForgeTools\Services;

class SeoScoreCalculator
{
    private const WEIGHTS = [
        'title'            => 8,
        'meta_description' => 7,
        'h1'               => 8,
        'headings'         => 5,
        'canonical'        => 6,
        'meta_robots'      => 8,
        'x_robots_tag'     => 5,
        'hreflang'         => 4,
        'https'            => 10,
        'viewport'         => 6,
        'html_lang'        => 3,
        'og_tags'          => 6,
        'twitter_tags'     => 3,
        'schema'           => 5,
        'images_alt'       => 6,
        'internal_links'   => 6,
        'external_links'   => 2,
        'robots_txt'       => 4,
        'sitemap'          => 4,
        'indexability'     => 5,
    ];

    /**
     * @param  array<string,array{status:string,value:mixed,message:string|null}>  $checks
     */
    public function calculate(array $checks): int
    {
        $score = 0;
        $max   = 0;

        foreach (self::WEIGHTS as $key => $weight) {
            $max += $weight;
            $check = $checks[$key] ?? null;
            if ($check === null) {
                continue;
            }
            $status = (string) ($check['status'] ?? 'info');
            if ($status === 'pass') {
                $score += $weight;
            } elseif ($status === 'warn') {
                $score += (int) round($weight * 0.5);
            } elseif ($status === 'info') {
                $score += (int) round($weight * 0.8);
            }
        }

        if ($max <= 0) {
            return 0;
        }

        $scaled = (int) round($score * 100 / $max);

        return max(0, min(100, $scaled));
    }
}
