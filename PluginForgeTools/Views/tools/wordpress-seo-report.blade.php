@extends('layouts.app')

@push('header')
<title>{{ $metaTitle }}</title>
<meta name="description" content="{{ $metaDescription }}">
@if(!empty($noindex))
<meta name="robots" content="noindex, follow">
@endif
@if(!empty($canonical))
<link rel="canonical" href="{{ $canonical }}">
@endif
@foreach($alternatives as $locale => $altUrl)
    @if(!empty($altUrl))
    <link rel="alternate" hreflang="{{ $locale }}" href="{{ $altUrl }}">
    @endif
@endforeach
@if(!empty($x_default))
<link rel="alternate" hreflang="x-default" href="{{ $x_default }}">
@endif
<meta property="og:title" content="{{ $ogTitle }}">
<meta property="og:description" content="{{ $ogDescription }}">
<meta property="og:type" content="article">
@if(!empty($canonical))
<meta property="og:url" content="{{ $canonical }}">
@endif
<meta property="og:locale" content="{{ str_replace('-', '_', $current_locale) }}">
@foreach($alternatives as $locale => $altUrl)
    @if(!empty($altUrl) && $locale !== $current_locale)
    <meta property="og:locale:alternate" content="{{ str_replace('-', '_', $locale) }}">
    @endif
@endforeach
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $ogTitle }}">
<meta name="twitter:description" content="{{ $ogDescription }}">
@endpush

@section('content')
<div class="container py-5" id="seoReportWrap" data-public-id="{{ e($report->public_id) }}" data-status-url="{{ front_route('tools.wordpress_seo_checker.report.status', ['publicId' => $report->public_id]) }}" data-status-pending="{{ $report->status === \Plugin\PluginForgeTools\Models\SeoReport::STATUS_PENDING ? '1' : '0' }}">
    <div class="row justify-content-center mb-4">
        <div class="col-lg-10">
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="{{ front_route('tools.wordpress_seo_checker.index') }}" class="text-decoration-none">
                            {{ trans('PluginForgeTools::common.breadcrumb.home') }}
                        </a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        {{ trans('PluginForgeTools::common.breadcrumb.report') }}
                    </li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row justify-content-center mb-4">
        <div class="col-lg-10">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <div class="row align-items-center g-3">
                        <div class="col-md-8">
                            <div class="mb-2">
                                <span class="badge bg-light text-dark border px-3 py-1" id="statusBadge">
                                    @if($report->status === \Plugin\PluginForgeTools\Models\SeoReport::STATUS_PENDING)
                                        {{ trans('PluginForgeTools::common.status.pending') }}
                                    @elseif($report->status === \Plugin\PluginForgeTools\Models\SeoReport::STATUS_COMPLETED)
                                        {{ trans('PluginForgeTools::common.status.completed') }}
                                    @elseif($report->status === \Plugin\PluginForgeTools\Models\SeoReport::STATUS_FAILED)
                                        {{ trans('PluginForgeTools::common.status.failed') }}
                                    @else
                                        {{ trans('PluginForgeTools::common.status.expired') }}
                                    @endif
                                </span>
                            </div>
                            <h1 class="h4 fw-bold mb-2 text-break">
                                @if(!empty($report->human_url))
                                <a href="{{ e($report->human_url) }}" target="_blank" rel="ugc nofollow noopener noreferrer" class="text-decoration-none">
                                    {{ e($report->human_url) }}
                                </a>
                                @else
                                {{ trans('PluginForgeTools::common.report.untitled') }}
                                @endif
                            </h1>
                            <div class="small text-muted mb-0" id="finalUrlLine">
                                @if(!empty($report->final_url) && $report->final_url !== $report->target_url)
                                {{ trans('PluginForgeTools::common.report.redirected_to') }}
                                <a href="{{ e($report->final_url) }}" target="_blank" rel="ugc nofollow noopener noreferrer" class="text-muted">
                                    {{ e($report->final_url) }}
                                </a>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex flex-column align-items-center align-items-md-end">
                                <div class="display-3 fw-bold" id="scoreValue">
                                    @if($report->score !== null)
                                        {{ $report->score }}
                                    @else
                                        —
                                    @endif
                                </div>
                                <div class="small text-muted">{{ trans('PluginForgeTools::common.report.score_label') }}</div>
                            </div>
                        </div>
                    </div>

                    @if($report->status === \Plugin\PluginForgeTools\Models\SeoReport::STATUS_PENDING)
                    <div class="mt-4 progress" role="progressbar" aria-label="{{ trans('PluginForgeTools::common.report.analyzing') }}" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar progress-bar-striped progress-bar-animated w-100"></div>
                    </div>
                    <div class="mt-2 small text-muted text-center" id="pendingHint">
                        {{ trans('PluginForgeTools::common.report.pending_hint') }}
                    </div>
                    @endif

                    @if($report->status === \Plugin\PluginForgeTools\Models\SeoReport::STATUS_FAILED)
                    <div class="mt-4 alert alert-danger mb-0" role="alert" id="failedAlert">
                        @php
                            $err = (string)($report->error_code ?? 'runtime_error');
                            $tKey = "PluginForgeTools::common.errors.$err";
                            $tMsg = trans($tKey);
                            if ($tMsg === $tKey) {
                                $tMsg = trans('PluginForgeTools::common.errors.runtime_error');
                            }
                        @endphp
                        {{ $tMsg }}
                    </div>
                    @endif
                </div>

                @if($report->status === \Plugin\PluginForgeTools\Models\SeoReport::STATUS_COMPLETED || ($report->checks || $report->issues || $report->summary))
                <div class="card-body border-top pt-0">
                    @if(is_array($report->issues) && $report->issues !== [])
                    <div class="mt-4">
                        <h2 class="h5 fw-bold mb-3">{{ trans('PluginForgeTools::common.sections.issues') }}</h2>
                        <div class="row g-3">
                            @foreach($report->issues as $idx => $issue)
                                @php
                                    $level = $issue['level'] ?? 'info';
                                    $title = $issue['title'] ?? null;
                                    if (empty($title) && !empty($issue['code'])) {
                                        $code = $issue['code'];
                                        $recKey = "PluginForgeTools::common.recommendations.$code";
                                        $recTitle = trans("$recKey.title");
                                        $title = $recTitle !== "$recKey.title" ? $recTitle : $code;
                                    }
                                    $desc = $issue['description'] ?? null;
                                    if (empty($desc) && !empty($issue['code'])) {
                                        $code = $issue['code'];
                                        $recKey = "PluginForgeTools::common.recommendations.$code";
                                        $recDesc = trans("$recKey.description");
                                        if ($recDesc !== "$recKey.description") {
                                            $desc = $recDesc;
                                        }
                                    }
                                    $badgeClass = match($level) {
                                        'fail','critical','error','high' => 'bg-danger',
                                        'warn','warning','medium' => 'bg-warning text-dark',
                                        default => 'bg-info text-dark',
                                    };
                                    $labelKey = "PluginForgeTools::common.labels.$level";
                                    $label = trans($labelKey);
                                    if ($label === $labelKey) {
                                        $label = match($level) {
                                            'fail','critical','error','high' => trans('PluginForgeTools::common.labels.fail'),
                                            'warn','warning','medium' => trans('PluginForgeTools::common.labels.warn'),
                                            'pass','ok' => trans('PluginForgeTools::common.labels.pass'),
                                            default => trans('PluginForgeTools::common.labels.info'),
                                        };
                                    }
                                @endphp
                                <div class="col-lg-6">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <span class="badge {{ $badgeClass }}">{{ $label }}</span>
                                                <h3 class="h6 mb-0 flex-grow-1">{{ e($title) }}</h3>
                                            </div>
                                            @if(!empty($desc))
                                            <p class="card-text text-muted small mb-0">{!! nl2br(e($desc)) !!}</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    @if(is_array($report->checks) && $report->checks !== [])
                    <div class="mt-5">
                        <h2 class="h5 fw-bold mb-3">{{ trans('PluginForgeTools::common.sections.checks') }}</h2>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle border-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="w-50">{{ trans('PluginForgeTools::common.report.check_col') }}</th>
                                        <th>{{ trans('PluginForgeTools::common.report.status_col') }}</th>
                                        <th>{{ trans('PluginForgeTools::common.report.value_col') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($report->checks as $key => $check)
                                        @php
                                            $labelKey = "PluginForgeTools::common.checks.$key";
                                            $label = trans($labelKey);
                                            if ($label === $labelKey) {
                                                $label = $key;
                                            }
                                            $status = $check['status'] ?? 'info';
                                            $statusBadgeClass = match($status) {
                                                'pass','ok' => 'bg-success',
                                                'warn','warning' => 'bg-warning text-dark',
                                                'fail','error','critical' => 'bg-danger',
                                                default => 'bg-info text-dark',
                                            };
                                            $statusLabelKey = "PluginForgeTools::common.labels.$status";
                                            $statusLabel = trans($statusLabelKey);
                                            if ($statusLabel === $statusLabelKey) {
                                                $statusLabel = $status;
                                            }
                                            $value = $check['value'] ?? null;
                                            $message = $check['message'] ?? null;
                                        @endphp
                                        <tr>
                                            <td class="fw-medium">{{ e($label) }}</td>
                                            <td><span class="badge {{ $statusBadgeClass }}">{{ $statusLabel }}</span></td>
                                            <td class="small text-muted text-break">
                                                @if(is_string($value) || is_numeric($value))
                                                    @if(in_array($key, ['final_url','canonical'], true) || str_ends_with($key, '_url') || (is_string($value) && (str_starts_with($value, 'http://') || str_starts_with($value, 'https://'))))
                                                        <a href="{{ e($value) }}" target="_blank" rel="ugc nofollow noopener noreferrer" class="text-muted">{{ e($value) }}</a>
                                                    @else
                                                        {{ e($value) }}
                                                    @endif
                                                @elseif(is_array($value))
                                                    @include('PluginForgeTools::tools.partials._value', ['value' => $value])
                                                @elseif($value !== null)
                                                    {{ var_export($value, true) }}
                                                @elseif(!empty($message))
                                                    {{ e($message) }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif
                </div>
                @endif

                <div class="card-body border-top bg-light-subtle">
                    <div class="row align-items-center g-3">
                        <div class="col-md-7">
                            <label class="form-label small text-muted mb-1">{{ trans('PluginForgeTools::common.share.label') }}</label>
                            <div class="input-group input-group-sm">
                                <input type="text" readonly class="form-control form-control-sm bg-white" id="shareUrlInput" value="{{ e($canonical) }}" aria-label="{{ trans('PluginForgeTools::common.share.label') }}">
                                <button type="button" class="btn btn-outline-secondary" id="copyShareBtn" data-copy-target="shareUrlInput">
                                    {{ trans('PluginForgeTools::common.share.copy') }}
                                </button>
                            </div>
                        </div>
                        <div class="col-md-5 d-flex align-items-center justify-content-md-end gap-2">
                            <form method="POST" action="{{ front_route('tools.wordpress_seo_checker.store') }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="url" value="{{ e($report->target_url) }}">
                                <input type="hidden" name="force" value="1">
                                <button type="submit" class="btn btn-outline-primary btn-sm">
                                    {{ trans('PluginForgeTools::common.report.reanalyze') }}
                                </button>
                            </form>
                            <span class="badge bg-primary rounded-pill px-3 py-2">{{ trans('PluginForgeTools::common.badge_name') }}</span>
                            <span class="small text-muted">
                                <a href="{{ front_route('tools.wordpress_seo_checker.index') }}" class="text-decoration-none">
                                    {{ trans('PluginForgeTools::common.report.powered_by') }}
                                </a>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-10 text-center">
            <a href="{{ front_route('tools.wordpress_seo_checker.index') }}" class="btn btn-outline-primary">
                {{ trans('PluginForgeTools::common.report.another_url') }}
            </a>
        </div>
    </div>
</div>

@push('footer')
<script>
(function () {
    var wrap = document.getElementById('seoReportWrap');
    if (!wrap) return;
    var statusBadge = document.getElementById('statusBadge');
    var scoreValue = document.getElementById('scoreValue');
    var statusUrl = wrap.getAttribute('data-status-url');
    var statusPending = wrap.getAttribute('data-status-pending') === '1';

    var copyBtn = document.getElementById('copyShareBtn');
    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            var targetId = copyBtn.getAttribute('data-copy-target');
            var el = document.getElementById(targetId);
            if (!el) return;
            try {
                el.select();
                el.setSelectionRange(0, 99999);
                var ok = document.execCommand && document.execCommand('copy');
                if (!ok && navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(el.value || '');
                }
            } catch (e) {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(el.value || '');
                }
            }
        });
    }

    if (!statusPending || !statusUrl) return;

    var polled = 0;
    var maxPolls = 60;
    var timer = setInterval(function () {
        polled += 1;
        if (polled >= maxPolls) {
            clearInterval(timer);
            return;
        }
        try {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', statusUrl, true);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.onload = function () {
                if (xhr.status !== 200) return;
                var data;
                try { data = JSON.parse(xhr.responseText); } catch (e) { return; }
                if (!data || !data.success) return;
                if (data.is_finished) {
                    clearInterval(timer);
                    window.location.reload();
                    return;
                }
                if (typeof data.score === 'number' && scoreValue) {
                    scoreValue.textContent = String(data.score);
                }
            };
            xhr.send();
        } catch (e) {
            clearInterval(timer);
        }
    }, 2500);
})();
</script>
@endpush
@endsection
