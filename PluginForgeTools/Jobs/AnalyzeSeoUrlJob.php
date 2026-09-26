<?php
/**
 * Copyright (c) Since 2024 InnoShop - All Rights Reserved
 *
 * @link       https://www.innoshop.com
 * @author     InnoShop <team@innoshop.com>
 * @license    https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace Plugin\PluginForgeTools\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;
use Plugin\PluginForgeTools\Models\SeoReport;
use Plugin\PluginForgeTools\Services\PageFetcher;
use Plugin\PluginForgeTools\Services\SeoAnalyzer;
use Plugin\PluginForgeTools\Services\SeoScoreCalculator;
use Throwable;

class AnalyzeSeoUrlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [15, 60, 180];

    public int $timeout = 60;

    public function __construct(
        public int $reportId,
    ) {}

    public function handle(PageFetcher $fetcher, SeoAnalyzer $analyzer, SeoScoreCalculator $calculator): void
    {
        /** @var SeoReport|null $report */
        $report = SeoReport::query()->find($this->reportId);
        if (! $report) {
            return;
        }

        if ($report->status !== SeoReport::STATUS_PENDING) {
            return;
        }

        try {
            $targetUrl = (string) $report->target_url;
            $host      = (string) $report->target_host;

            $fetchResult = $fetcher->fetch($targetUrl);

            $robotsResult  = $fetcher->fetchRobotsTxt($host, $targetUrl);
            $sitemapResult = $fetcher->detectSitemap($host, (string) ($fetchResult['final_url'] ?? $targetUrl), (string) ($robotsResult['content'] ?? ''));

            $analysis = $analyzer->analyze($fetchResult, $robotsResult, $sitemapResult);
            $score    = $calculator->calculate($analysis['checks'] ?? []);

            $report->update([
                'status'        => SeoReport::STATUS_COMPLETED,
                'final_url'     => (string) ($fetchResult['final_url'] ?? $targetUrl),
                'http_status'   => (int) ($fetchResult['http_status'] ?? null),
                'score'         => $score,
                'summary'       => $analysis['summary'] ?? null,
                'checks'        => $analysis['checks'] ?? null,
                'issues'        => $analysis['issues'] ?? null,
                'analyzed_at'   => now(),
                'error_code'    => null,
                'error_message' => null,
            ]);
        } catch (InvalidArgumentException $e) {
            $report->update([
                'status'        => SeoReport::STATUS_FAILED,
                'error_code'    => $e->getMessage() ?: 'validation_error',
                'error_message' => null,
                'analyzed_at'   => now(),
            ]);
        } catch (Throwable $e) {
            $code = $e->getMessage();
            if ($code === '' || ! is_string($code) || strlen($code) > 64) {
                $code = 'runtime_error';
            }

            $report->update([
                'status'        => SeoReport::STATUS_FAILED,
                'error_code'    => $code,
                'error_message' => $e->getMessage(),
                'analyzed_at'   => now(),
            ]);

            throw $e;
        }
    }
}
