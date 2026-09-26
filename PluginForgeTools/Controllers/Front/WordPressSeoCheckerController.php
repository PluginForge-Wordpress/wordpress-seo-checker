<?php
/**
 * Copyright (c) Since 2024 InnoShop - All Rights Reserved
 *
 * @link       https://www.innoshop.com
 * @author     InnoShop <team@innoshop.com>
 * @license    https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace Plugin\PluginForgeTools\Controllers\Front;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Plugin\PluginForgeTools\Jobs\AnalyzeSeoUrlJob;
use Plugin\PluginForgeTools\Models\SeoReport;
use Plugin\PluginForgeTools\Requests\AnalyzeUrlRequest;
use Plugin\PluginForgeTools\Services\UrlSecurityValidator;

class WordPressSeoCheckerController extends Controller
{
    public function index()
    {
        $routeParams   = [];
        $pureRouteName = 'tools.wordpress_seo_checker.index';
        $seoData       = $this->buildSeoData($pureRouteName, $routeParams, true);

        return view('PluginForgeTools::tools.wordpress-seo-checker', $seoData + [
            'ogTitle'         => trans('PluginForgeTools::common.og_title'),
            'ogDescription'   => trans('PluginForgeTools::common.og_description'),
            'metaTitle'       => trans('PluginForgeTools::common.meta_title'),
            'metaDescription' => trans('PluginForgeTools::common.meta_description'),
            'report'          => null,
        ]);
    }

    public function store(AnalyzeUrlRequest $request)
    {
        $validated = $request->validated();
        $rawUrl    = (string) ($validated['url'] ?? '');

        $cleanUrl       = app(UrlSecurityValidator::class)->validate($rawUrl);
        $host           = (string) parse_url($cleanUrl, PHP_URL_HOST);
        $hostNormalized = strtolower($host);

        $ip     = (string) $request->ip();
        $ipHash = $ip !== '' ? crc32($ip) : null;

        $forceNew = $request->boolean('force');

        // Janela curta só para evitar reenvios em rajada (duplo-clique, refresh);
        // não deve esconder uma reanálise legítima depois que o site mudou.
        $recent = $forceNew ? null : SeoReport::query()
            ->where('target_url', $cleanUrl)
            ->whereIn('status', [SeoReport::STATUS_PENDING, SeoReport::STATUS_COMPLETED])
            ->where('created_at', '>=', now()->subMinutes(2))
            ->orderBy('created_at', 'desc')
            ->first();

        if ($recent instanceof SeoReport) {
            $report = $recent;
        } else {
            $publicId = Str::lower(Str::random(32));

            /** @var SeoReport $report */
            $report = SeoReport::query()->create([
                'public_id'   => $publicId,
                'status'      => SeoReport::STATUS_PENDING,
                'target_url'  => $cleanUrl,
                'target_host' => $hostNormalized,
                'ip_hash'     => $ipHash,
            ]);

            AnalyzeSeoUrlJob::dispatch($report->getKey());
        }

        $locale = front_locale_code();
        if ($locale && ! hide_url_locale()) {
            $showRouteName = "$locale.front.tools.wordpress_seo_checker.report.show";
            if (! Route::has($showRouteName)) {
                $showRouteName = 'front.tools.wordpress_seo_checker.report.show';
            }
        } else {
            $showRouteName = 'front.tools.wordpress_seo_checker.report.show';
            if (! Route::has($showRouteName)) {
                $fallback = front_locale_code();
                if ($fallback && Route::has("$fallback.front.tools.wordpress_seo_checker.report.show")) {
                    $showRouteName = "$fallback.front.tools.wordpress_seo_checker.report.show";
                }
            }
        }

        $redirectUrl = route($showRouteName, ['publicId' => $report->public_id]);

        if ($request->expectsJson()) {
            return response()->json([
                'success'   => true,
                'public_id' => $report->public_id,
                'status'    => $report->status,
                'url'       => $redirectUrl,
            ]);
        }

        return redirect()->to($redirectUrl);
    }

    public function show(Request $request, string $publicId)
    {
        $report = SeoReport::findByPublicId($publicId);
        if (! $report) {
            abort(404, trans('PluginForgeTools::common.errors.report_not_found'));
        }

        $pureRouteName = 'tools.wordpress_seo_checker.report.show';
        $routeParams   = ['publicId' => $report->public_id];
        $seoData       = $this->buildSeoData($pureRouteName, $routeParams, false);

        $humanUrl   = $report->human_url ?? $report->target_url;
        $scoreLabel = $report->score !== null ? (string) $report->score : '—';
        $safeHost   = e($humanUrl);

        return view('PluginForgeTools::tools.wordpress-seo-report', $seoData + [
            'report'          => $report,
            'metaTitle'       => trans('PluginForgeTools::common.report.meta_title_prefix').' '.$scoreLabel.' / '.$safeHost,
            'metaDescription' => trans('PluginForgeTools::common.report.meta_description_prefix').' '.$safeHost,
            'ogTitle'         => trans('PluginForgeTools::common.report.og_title_prefix').' '.$scoreLabel,
            'ogDescription'   => trans('PluginForgeTools::common.report.og_description_prefix').' '.$safeHost,
            'noindex'         => true,
        ]);
    }

    public function status(Request $request, string $publicId): JsonResponse
    {
        $report = SeoReport::findByPublicId($publicId);
        if (! $report) {
            return response()->json([
                'success' => false,
                'error'   => 'report_not_found',
                'message' => trans('PluginForgeTools::common.errors.report_not_found'),
            ], 404);
        }

        $payload = [
            'success'     => true,
            'public_id'   => $report->public_id,
            'status'      => $report->status,
            'is_finished' => $report->isFinished(),
            'score'       => $report->score,
            'error_code'  => $report->error_code,
        ];

        if ($report->status === SeoReport::STATUS_COMPLETED) {
            $payload['final_url']    = $report->final_url;
            $payload['http_status']  = $report->http_status;
            $payload['summary']      = $report->summary;
            $payload['checks_count'] = is_array($report->checks) ? count($report->checks) : 0;
            $payload['issues_count'] = is_array($report->issues) ? count($report->issues) : 0;
        } elseif ($report->status === SeoReport::STATUS_FAILED) {
            $errorKey   = (string) ($report->error_code ?: 'runtime_error');
            $transKey   = "PluginForgeTools::common.errors.$errorKey";
            $translated = trans($transKey);
            if ($translated === $transKey) {
                $translated = trans('PluginForgeTools::common.errors.runtime_error');
            }
            $payload['message'] = $translated;
        }

        return response()->json($payload);
    }

    /**
     * @param  string  $pureRouteName  e.g. "tools.wordpress_seo_checker.index" (sem prefixo locale/front)
     * @param  array<string,mixed>  $routeParams
     * @return array{canonical:string,alternatives:array<string,string|null>,x_default:string|null,current_locale:string,locales:array<string,string>}
     */
    private function buildSeoData(string $pureRouteName, array $routeParams = [], bool $indexable = true): array
    {
        $currentLocale = front_locale_code() ?: setting_locale_code() ?: 'en';
        $localeCodes   = enabled_locale_codes();
        if ($localeCodes === []) {
            $localeCodes = [$currentLocale];
        }

        $alternatives = [];
        $canonical    = null;
        foreach ($localeCodes as $locale) {
            if (hide_url_locale()) {
                $routeName = "front.$pureRouteName";
            } else {
                $routeName = "$locale.front.$pureRouteName";
                if (! Route::has($routeName)) {
                    $routeName = "front.$pureRouteName";
                }
            }

            if (Route::has($routeName)) {
                try {
                    $url = route($routeName, $routeParams, true);
                } catch (\Throwable $e) {
                    $url = null;
                }
            } else {
                $url = null;
            }

            $alternatives[$locale] = $url;
            if ($locale === $currentLocale && $url !== null) {
                $canonical = $url;
            }
        }

        if ($canonical === null) {
            foreach ($alternatives as $locale => $url) {
                if ($url !== null) {
                    $canonical = $url;
                    break;
                }
            }
        }

        $defaultLocale = setting_locale_code() ?: ($localeCodes[0] ?? 'en');
        $xDefault      = null;
        if (isset($alternatives[$defaultLocale])) {
            $xDefault = $alternatives[$defaultLocale];
        } elseif (hide_url_locale() && Route::has("front.$pureRouteName")) {
            try {
                $xDefault = route("front.$pureRouteName", $routeParams, true);
            } catch (\Throwable $e) {
                $xDefault = $canonical;
            }
        } else {
            $xDefault = $canonical;
        }

        $localeLabels = [];
        foreach ($localeCodes as $code) {
            $localeLabels[$code] = $code;
        }

        return [
            'canonical'      => (string) $canonical,
            'alternatives'   => $alternatives,
            'x_default'      => $xDefault,
            'current_locale' => (string) $currentLocale,
            'locales'        => $localeLabels,
            'indexable'      => $indexable,
        ];
    }
}
