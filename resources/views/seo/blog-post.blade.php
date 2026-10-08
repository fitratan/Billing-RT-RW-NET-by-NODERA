@extends('seo.layout')

@section('title', $article['title'] . ' | Blog NODERA')
@section('meta_description', $article['meta_description'])
@section('meta_keywords', 'tutorial ' . strtolower($article['category']) . ', ' . strtolower($article['title']) . ', software billing isp, mikrotik nodera')
@section('og_type', 'article')

@section('schema')
@php
    $canonicalHost = preg_replace('/^www\./i', '', request()->getHost());
    $pageUrl = 'https://' . $canonicalHost . '/blog/' . $article['slug'];

    $schemaGraph = [
        [
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'Beranda',
                    'item' => 'https://' . $canonicalHost,
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => 'Blog & Panduan',
                    'item' => 'https://' . $canonicalHost . '/blog',
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 3,
                    'name' => $article['title'],
                    'item' => $pageUrl,
                ],
            ],
        ],
        [
            '@type' => 'BlogPosting',
            'headline' => $article['h1'],
            'description' => $article['meta_description'],
            'datePublished' => $article['date'],
            'dateModified' => $article['date'],
            'author' => [
                '@type' => 'Organization',
                'name' => $article['author'] ?? 'Tim NODERA',
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'NODERA - DGTL Net Solution',
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => 'https://' . $canonicalHost . '/images/logo.png',
                ],
            ],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $pageUrl,
            ],
        ],
    ];
@endphp
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@graph' => $schemaGraph,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
</script>
@endsection

@section('content')
<!-- Breadcrumbs -->
<div class="breadcrumb-container">
    <div class="container">
        <ul class="breadcrumb-list">
            <li><a href="/">Beranda</a></li>
            <span>/</span>
            <li><a href="/blog">Blog &amp; Panduan</a></li>
            <span>/</span>
            <li class="active">{{ $article['title'] }}</li>
        </ul>
    </div>
</div>

<!-- Article Header -->
<section style="padding: 130px 0 40px; background: radial-gradient(circle at 50% 10%, rgba(0, 229, 255, 0.08) 0%, rgba(7, 11, 17, 1) 75%); border-bottom: 1px solid rgba(255,255,255,0.06);">
    <div class="container">
        <div class="row">
            <div class="col-md-10 col-md-offset-1 text-center">
                <div style="display: flex; gap: 10px; justify-content: center; align-items: center; margin-bottom: 18px;">
                    <span style="background: rgba(0, 229, 255, 0.1); border: 1px solid rgba(0, 229, 255, 0.3); color: #00E5FF; padding: 4px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase;">{{ $article['category'] }}</span>
                    <span style="color: #64748B; font-size: 13px;"><i class="fa fa-calendar"></i> {{ date('d F Y', strtotime($article['date'])) }}</span>
                    <span style="color: #64748B; font-size: 13px;"><i class="fa fa-clock-o"></i> {{ $article['reading_time'] }} baca</span>
                </div>
                <h1 class="seo-h1" style="font-size: 32px; max-width: 900px; margin: 0 auto 20px;">{{ $article['h1'] }}</h1>
                <p style="color: #94A3B8; font-size: 15px; max-width: 750px; margin: 0 auto;">
                    {{ $article['excerpt'] }}
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Article Main Body -->
<section class="seo-content-section" style="background: #070B11; padding: 50px 0 80px;">
    <div class="container">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <article style="background: #090E14; border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 40px; box-shadow: 0 10px 40px rgba(0,0,0,0.5);">
                    {!! $article['content'] !!}

                    <!-- Author Info & Solution Link Box -->
                    @if($relatedSolution)
                        <div style="background: linear-gradient(135deg, #0B1626 0%, #061B2E 100%); border: 1px solid rgba(0, 229, 255, 0.3); border-radius: 10px; padding: 24px; margin-top: 50px;">
                            <div style="display: flex; gap: 15px; align-items: center;">
                                <div style="width: 50px; height: 50px; border-radius: 50%; background: #00E5FF; color: #000; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 800; flex-shrink: 0;">
                                    <i class="fa fa-rocket"></i>
                                </div>
                                <div>
                                    <h4 style="color: #FFF; font-size: 16px; font-weight: 700; margin-top: 0; margin-bottom: 5px;">Otomasi Fitur Ini Bersama NODERA</h4>
                                    <p style="color: #CBD5E1; font-size: 13px; margin-bottom: 10px; line-height: 1.5;">
                                        Terapkan sistem ini secara otomatis tanpa konfigurasi manual yang melelahkan menggunakan <strong style="color: #00E5FF;">{{ $relatedSolution['h1'] }}</strong>.
                                    </p>
                                    <a href="/{{ $relatedSolution['slug'] }}" class="btn btn-cyan btn-xs" style="font-weight: 700; padding: 6px 16px;">
                                        Pelajari Solusi {{ $relatedSolution['title'] }} <i class="fa fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Back to Blog Button -->
                    <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.08); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                        <a href="/blog" style="color: #94A3B8; font-size: 13px; text-decoration: none;">
                            <i class="fa fa-arrow-left"></i> Kembali ke Daftar Artikel
                        </a>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <span style="color: #64748B; font-size: 12px;">Bagikan:</span>
                            <a href="https://api.whatsapp.com/send?text={{ urlencode($article['title'] . ' ' . $pageUrl) }}" target="_blank" class="btn btn-xs" style="background: #25D366; color: #FFF;"><i class="fa fa-whatsapp"></i></a>
                            <a href="https://twitter.com/intent/tweet?text={{ urlencode($article['title']) }}&url={{ urlencode($pageUrl) }}" target="_blank" class="btn btn-xs" style="background: #1DA1F2; color: #FFF;"><i class="fa fa-twitter"></i></a>
                        </div>
                    </div>
                </article>

                <!-- Related Articles Section -->
                @if(!empty($relatedArticles))
                    <div style="margin-top: 60px;">
                        <h3 style="color: #FFF; font-size: 20px; font-weight: 700; margin-bottom: 20px;">Artikel Terkait Lainnya</h3>
                        <div class="row">
                            @foreach($relatedArticles as $rSlug => $rArt)
                                <div class="col-sm-4" style="margin-bottom: 20px;">
                                    <a href="/blog/{{ $rSlug }}" style="text-decoration: none; display: block; background: #0B111E; border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 18px; height: 100%; transition: all 0.2s;" onmouseover="this.style.borderColor='#00E5FF'" onmouseout="this.style.borderColor='rgba(255,255,255,0.08)'">
                                        <span style="color: #00E5FF; font-size: 10.5px; font-weight: 700; text-transform: uppercase;">{{ $rArt['category'] }}</span>
                                        <h4 style="color: #FFF; font-size: 14px; font-weight: 700; margin-top: 6px; margin-bottom: 8px; line-height: 1.4;">{{ $rArt['title'] }}</h4>
                                        <span style="color: #64748B; font-size: 11px;"><i class="fa fa-clock-o"></i> {{ $rArt['reading_time'] }}</span>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
@endsection
