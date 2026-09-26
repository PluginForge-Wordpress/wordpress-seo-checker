@extends('layouts.app')

@push('header')
<title>{{ $metaTitle }}</title>
<meta name="description" content="{{ $metaDescription }}">
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
<meta property="og:type" content="website">
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
<script type="application/ld+json">
{
  "@@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    @php $first = true; @endphp
    @foreach(range(1, 10) as $n)
        @php
            $qKey = "PluginForgeTools::common.faq.q{$n}";
            $aKey = "PluginForgeTools::common.faq.a{$n}";
            $q = trans($qKey);
            $a = trans($aKey);
        @endphp
        @if($q !== $qKey && $a !== $aKey)
            @if(!$first),@endif
            {
              "@type": "Question",
              "name": {!! json_encode($q, JSON_UNESCAPED_UNICODE) !!},
              "acceptedAnswer": {
                "@type": "Answer",
                "text": {!! json_encode($a, JSON_UNESCAPED_UNICODE) !!}
              }
            }
            @php $first = false; @endphp
        @endif
    @endforeach
  ]
}
</script>
@endpush

@section('content')
<div class="container py-5">
    <div class="row justify-content-center mb-5">
        <div class="col-lg-10 text-center">
            <div class="mb-3">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
                    {{ trans('PluginForgeTools::common.family_badge') }}
                </span>
            </div>
            <h1 class="display-5 fw-bold mb-3">{{ trans('PluginForgeTools::common.hero_title') }}</h1>
            <p class="lead text-muted mb-4">{{ trans('PluginForgeTools::common.hero_subtitle') }}</p>

            <form method="POST" action="{{ front_route('tools.wordpress_seo_checker.store') }}" class="mx-auto" style="max-width: 640px;">
                @csrf
                <div class="card shadow-sm border-0 p-2">
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-white border-end-0 text-muted">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                        </span>
                        <input
                            type="url"
                            name="url"
                            id="seo_url"
                            class="form-control border-start-0 border-end-0 @error('url') is-invalid @enderror"
                            placeholder="{{ trans('PluginForgeTools::common.form.placeholder') }}"
                            required
                            maxlength="2048"
                            value="{{ old('url') }}"
                            autocomplete="off"
                        >
                        <button type="submit" class="btn btn-primary rounded-end px-4">
                            {{ trans('PluginForgeTools::common.form.submit') }}
                        </button>
                    </div>
                    @error('url')
                        <div class="text-start text-danger small px-3 pt-2">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mt-3 small text-muted">
                    {{ trans('PluginForgeTools::common.form.disclaimer') }}
                </div>
            </form>
        </div>
    </div>

    <div class="row g-4 mb-6">
        @foreach(['1','2','3','4','5','6'] as $n)
            @php
                $featTitleKey = "PluginForgeTools::common.features.f{$n}_title";
                $featDescKey  = "PluginForgeTools::common.features.f{$n}_desc";
                $featTitle = trans($featTitleKey);
                $featDesc  = trans($featDescKey);
            @endphp
            @if($featTitle !== $featTitleKey && $featDesc !== $featDescKey)
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title mb-2">{{ $featTitle }}</h5>
                        <p class="card-text text-muted small">{{ $featDesc }}</p>
                    </div>
                </div>
            </div>
            @endif
        @endforeach
    </div>

    <div class="row justify-content-center my-7">
        <div class="col-lg-8">
            <div class="card border-0 bg-primary-subtle">
                <div class="card-body text-center p-5">
                    <h2 class="h4 fw-bold mb-3">{{ trans('PluginForgeTools::common.badge.headline') }}</h2>
                    <p class="text-muted mb-4">{{ trans('PluginForgeTools::common.badge.subline') }}</p>
                    <div class="d-inline-flex align-items-center gap-2 bg-white px-4 py-3 rounded shadow-sm border">
                        <span class="badge bg-primary rounded-pill px-3 py-2">{{ trans('PluginForgeTools::common.badge_name') }}</span>
                        <span class="text-muted small">{{ trans('PluginForgeTools::common.badge.caption') }}</span>
                    </div>
                    <div class="mt-4 small text-muted">
                        <a href="{{ front_route('tools.wordpress_seo_checker.index') }}" class="text-decoration-none">
                            {{ trans('PluginForgeTools::common.badge.link_text') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row justify-content-center mb-5">
        <div class="col-lg-9">
            <h2 class="h3 text-center mb-4">{{ trans('PluginForgeTools::common.faq.title') }}</h2>
            <div class="accordion" id="seoFaq">
                @foreach(range(1, 10) as $n)
                    @php
                        $qKey = "PluginForgeTools::common.faq.q{$n}";
                        $aKey = "PluginForgeTools::common.faq.a{$n}";
                        $q = trans($qKey);
                        $a = trans($aKey);
                    @endphp
                    @if($q !== $qKey && $a !== $aKey)
                    <div class="accordion-item border-0 shadow-sm mb-2 rounded">
                        <h3 class="accordion-header" id="faqHeading{{ $n }}">
                            <button class="accordion-button collapsed bg-transparent" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse{{ $n }}" aria-expanded="false" aria-controls="faqCollapse{{ $n }}">
                                {{ $q }}
                            </button>
                        </h3>
                        <div id="faqCollapse{{ $n }}" class="accordion-collapse collapse" aria-labelledby="faqHeading{{ $n }}" data-bs-parent="#seoFaq">
                            <div class="accordion-body text-muted">
                                {!! nl2br(e($a)) !!}
                            </div>
                        </div>
                    </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
