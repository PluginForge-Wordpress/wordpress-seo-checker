<?php
/**
 * Copyright (c) Since 2024 InnoShop - All Rights Reserved
 *
 * @link       https://www.innoshop.com
 * @author     InnoShop <team@innoshop.com>
 * @license    https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace Plugin\PluginForgeTools\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property string $status
 * @property string $target_url
 * @property string $target_host
 * @property string|null $final_url
 * @property int|null $http_status
 * @property int|null $score
 * @property int|null $ip_hash
 * @property string|null $error_code
 * @property string|null $error_message
 * @property array<string,mixed>|null $summary
 * @property array<string,mixed>|null $checks
 * @property array<int,mixed>|null $issues
 * @property Carbon|null $analyzed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $human_url
 */
class SeoReport extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    protected $table = 'pluginforge_seo_reports';

    protected $fillable = [
        'public_id',
        'status',
        'target_url',
        'target_host',
        'final_url',
        'http_status',
        'score',
        'ip_hash',
        'error_code',
        'error_message',
        'summary',
        'checks',
        'issues',
        'analyzed_at',
    ];

    protected $casts = [
        'summary'     => 'array',
        'checks'      => 'array',
        'issues'      => 'array',
        'http_status' => 'integer',
        'score'       => 'integer',
        'analyzed_at' => 'datetime',
    ];

    public static function findByPublicId(string $publicId): ?self
    {
        $trimmed = preg_replace('/[^a-zA-Z0-9]/', '', $publicId);
        if ($trimmed === '') {
            return null;
        }

        return self::query()->where('public_id', $trimmed)->first();
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_EXPIRED], true);
    }

    public function humanUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->final_url ?: $this->target_url
        );
    }
}
