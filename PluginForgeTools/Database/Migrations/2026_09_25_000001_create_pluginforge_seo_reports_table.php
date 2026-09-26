<?php
/**
 * Copyright (c) Since 2024 InnoShop - All Rights Reserved
 *
 * @link       https://www.innoshop.com
 * @author     InnoShop <team@innoshop.com>
 * @license    https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pluginforge_seo_reports', function (Blueprint $table) {
            $table->id();

            $table->string('public_id', 32)->unique();
            $table->string('status', 16)->default('pending');
            $table->text('target_url');
            $table->string('target_host', 191);
            $table->text('final_url')->nullable();
            $table->smallInteger('http_status')->nullable();

            $table->unsignedSmallInteger('score')->nullable();
            $table->unsignedInteger('ip_hash')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();

            $table->json('summary')->nullable();
            $table->json('checks')->nullable();
            $table->json('issues')->nullable();

            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();

            $table->index(['target_host', 'created_at'], 'pluginforge_seo_reports_host_created_idx');
            $table->index(['status', 'created_at'], 'pluginforge_seo_reports_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pluginforge_seo_reports');
    }
};
