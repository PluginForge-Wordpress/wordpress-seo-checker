<?php
/**
 * Copyright (c) Since 2024 InnoShop - All Rights Reserved
 *
 * @link       https://www.innoshop.com
 * @author     InnoShop <team@innoshop.com>
 * @license    https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

use Illuminate\Support\Facades\Route;
use Plugin\PluginForgeTools\Controllers\Front\WordPressSeoCheckerController;

Route::group([
    'prefix' => 'tools',
    'as'     => 'tools.',
], function () {
    Route::group([
        'prefix' => 'wordpress-seo-checker',
        'as'     => 'wordpress_seo_checker.',
    ], function () {
        Route::get('/', [WordPressSeoCheckerController::class, 'index'])
            ->name('index');

        Route::post('/analyze', [WordPressSeoCheckerController::class, 'store'])
            ->middleware('throttle:seo_analyze')
            ->name('store');

        Route::group([
            'prefix' => 'report/{publicId}',
            'as'     => 'report.',
        ], function () {
            Route::get('/', [WordPressSeoCheckerController::class, 'show'])
                ->name('show');

            Route::get('/status', [WordPressSeoCheckerController::class, 'status'])
                ->middleware('throttle:seo_poll')
                ->name('status');
        });
    });
});
