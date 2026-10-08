@extends('seo.layout')

@section('title', $item['title'])
@section('meta_description', $item['meta_description'])
@section('meta_keywords', implode(', ', array_merge([$item['primary_keyword']], $item['secondary_keywords'] ?? [])))

@section('schema')
@php
    $canonicalHost = preg_replace('/^www\./i', '', request()->getHost());
    $pageUrl = 'https://' . $canonicalHost . '/' . $item['slug'];

    $breadcrumbItems = [];
    foreach ($item['breadcrumbs'] as $idx => $bc) {
        $breadcrumbItems[] = [
            '@type' => 'ListItem',
            'position' => $idx + 1,
            'name' => $bc['title'],
            'item' => str_starts_with($bc['url'], 'http') ? $bc['url'] : 'https://' . $canonicalHost . $bc['url'],
        ];
    }

    $schemaGraph = [
        [
            '@type' => 'BreadcrumbList',
            'itemListElement' => $breadcrumbItems,
        ],
        [
            '@type' => 'SoftwareApplication',
            'name' => $item['title'],
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Cloud, Web, Android, Linux, MikroTik RouterOS',
            'description' => $item['meta_description'],
            'url' => $pageUrl,
            'offers' => [
                '@type' => 'Offer',
                'price' => '30000',
                'priceCurrency' => 'IDR',
                'priceValidUntil' => '2030-12-31',
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'NODERA - DGTL Net Solution',
                'url' => 'https://' . $canonicalHost,
                'logo' => 'https://' . $canonicalHost . '/images/logo.png',
            ],
        ],
    ];

    if (!empty($item['faqs'])) {
        $faqEntities = [];
        foreach ($item['faqs'] as $faq) {
            $faqEntities[] = [
                '@type' => 'Question',
                'name' => $faq['q'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['a'],
                ],
            ];
        }
        $schemaGraph[] = [
            '@type' => 'FAQPage',
            'mainEntity' => $faqEntities,
        ];
    }
@endphp
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@graph' => $schemaGraph,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
</script>
@endsection

@section('content')
<!-- Breadcrumbs (Semantic & Visible) -->
<div class="breadcrumb-container">
    <div class="container">
        <ul class="breadcrumb-list">
            @foreach($item['breadcrumbs'] as $idx => $bc)
                @if($loop->last)
                    <li class="active">{{ $bc['title'] }}</li>
                @else
                    <li><a href="{{ $bc['url'] }}">{{ $bc['title'] }}</a></li>
                    <span>/</span>
                @endif
            @endforeach
        </ul>
    </div>
</div>

<!-- Hero Section -->
<section class="seo-hero">
    <div class="container">
        <div class="seo-badge">{{ $item['tagline'] }}</div>
        <h1 class="seo-h1">{{ $item['h1'] }}</h1>
        <p class="seo-subtitle">{{ $item['hero_subtitle'] }}</p>
        <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
            <a href="/register" class="btn btn-cyan btn-circle btn-lg" style="padding: 12px 30px; font-weight: 800;">
                <i class="fa fa-rocket"></i> Coba Gratis Sekarang
            </a>
            <a href="https://wa.me/{{ !empty($company['phone_wa']) ? $company['phone_wa'] : '6285155173547' }}?text={{ urlencode('Halo NODERA, saya ingin konsultasi mengenai ' . $item['h1']) }}" target="_blank" class="btn btn-circle btn-lg" style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.2); color: #FFF; padding: 12px 24px;">
                <i class="fa fa-whatsapp text-success"></i> Konsultasi Teknis
            </a>
        </div>
    </div>
</section>

<!-- Features Grid -->
<section style="padding: 70px 0; background: #080C14;">
    <div class="container">
        <div class="text-center" style="margin-bottom: 50px;">
            <span style="color: #00E5FF; font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: 1px;">Fitur Unggulan</span>
            <h2 style="color: #FFF; font-size: 28px; font-weight: 800; margin-top: 8px;">Dirancang Khusus untuk Kebutuhan ISP &amp; RT/RW Net</h2>
        </div>
        <div class="row">
            @foreach($item['features'] as $feat)
                <div class="col-md-4 col-sm-6" style="margin-bottom: 30px;">
                    <div class="feature-card">
                        <div class="feature-icon-box">
                            <i class="fa {{ $feat['icon'] }}"></i>
                        </div>
                        <h3 class="feature-title">{{ $feat['title'] }}</h3>
                        <p class="feature-desc">{{ $feat['desc'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

<!-- Rich Editorial Content Sections -->
<section class="seo-content-section" style="background: #070B11;">
    <div class="container">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                @foreach($item['sections'] as $sec)
                    <div style="margin-bottom: 45px;">
                        <h2>{{ $sec['title'] }}</h2>
                        <p>{{ $sec['content'] }}</p>
                    </div>
                @endforeach

                <!-- Interactive Architecture Diagram Box -->
                <div style="background: #0B111E; border: 1px solid rgba(0, 229, 255, 0.2); border-radius: 12px; padding: 30px; margin: 40px 0;">
                    <h3 style="color: #00E5FF; margin-top: 0; font-size: 18px;"><i class="fa fa-cogs"></i> Alur Kerja Terintegrasi Otomatis</h3>
                    <p style="font-size: 14px; color: #CBD5E1; line-height: 1.7;">
                        Data pelanggan di <strong style="color: #FFF;">NODERA</strong> tersinkronisasi dua arah ke <strong style="color: #FFF;">MikroTik RouterOS</strong> &amp; <strong style="color: #FFF;">OLT</strong>. Tagihan terkirim via WhatsApp, pelanggan bayar via QRIS realtime, dan sistem otomatis melepas isolir dalam 0 detik tanpa bantuan staf.
                    </p>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 15px;">
                        <span style="background: rgba(0, 229, 255, 0.1); border: 1px solid rgba(0, 229, 255, 0.3); color: #00E5FF; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">RouterOS v6 &amp; v7</span>
                        <span style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: #10B981; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">WhatsApp Gateway</span>
                        <span style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); color: #F59E0B; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">QRIS Dinamis</span>
                        <span style="background: rgba(139, 92, 246, 0.1); border: 1px solid rgba(139, 92, 246, 0.3); color: #8B5CF6; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">Printer Thermal</span>
                    </div>
                </div>

                <!-- FAQ Section (Visible & SEO Accordion) -->
                @if(!empty($item['faqs']))
                    <div style="margin-top: 60px;">
                        <h2 style="margin-bottom: 25px;">Pertanyaan yang Sering Diajukan (FAQ)</h2>
                        <div class="panel-group" id="faqAccordion" role="tablist">
                            @foreach($item['faqs'] as $fIdx => $faq)
                                <div class="panel panel-default" style="background: #0B111E; border: 1px solid rgba(255,255,255,0.08); margin-bottom: 12px; border-radius: 8px;">
                                    <div class="panel-heading" role="tab" style="background: transparent; padding: 16px 20px;">
                                        <h4 class="panel-title" style="font-size: 15px; font-weight: 700;">
                                            <a role="button" data-toggle="collapse" data-parent="#faqAccordion" href="#faqCollapse{{ $fIdx }}" style="color: #FFF; text-decoration: none; display: flex; justify-content: space-between; align-items: center;">
                                                <span>{{ $faq['q'] }}</span>
                                                <i class="fa fa-chevron-down" style="font-size: 12px; color: #00E5FF;"></i>
                                            </a>
                                        </h4>
                                    </div>
                                    <div id="faqCollapse{{ $fIdx }}" class="panel-collapse collapse {{ $fIdx === 0 ? 'in' : '' }}" role="tabpanel">
                                        <div class="panel-body" style="border-top: 1px solid rgba(255,255,255,0.05); color: #94A3B8; font-size: 14px; line-height: 1.7; padding: 18px 20px;">
                                            {{ $faq['a'] }}
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- CTA Banner -->
                <div class="cta-banner">
                    <h3>Siap Mengotomasi Billing ISP Anda?</h3>
                    <p>Tingkatkan efisiensi bisnis internet Anda sekarang. Bergabunglah dengan ratusan ISP dan pengelola RT/RW Net di seluruh Indonesia.</p>
                    <a href="/register" class="btn btn-cyan btn-circle btn-lg" style="padding: 12px 36px; font-weight: 800;">
                        <i class="fa fa-bolt"></i> Mulai Uji Coba Sekarang
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Related Solutions Internal Links -->
<section style="padding: 60px 0; background: #080C14; border-top: 1px solid rgba(255,255,255,0.06);">
    <div class="container">
        <h3 style="color: #FFF; font-size: 20px; font-weight: 700; margin-bottom: 25px;">Solusi Terkait Lainnya</h3>
        <div class="row">
            @foreach($allSolutions as $sSlug => $sData)
                @if($sSlug !== $item['slug'])
                    <div class="col-md-4 col-sm-6" style="margin-bottom: 20px;">
                        <a href="/{{ $sSlug }}" style="text-decoration: none; display: block; background: #0B111E; border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 18px 20px; transition: all 0.2s;" onmouseover="this.style.borderColor='#00E5FF'" onmouseout="this.style.borderColor='rgba(255,255,255,0.08)'">
                            <h4 style="color: #FFF; font-size: 15px; font-weight: 700; margin-top: 0; margin-bottom: 6px;">{{ $sData['h1'] }}</h4>
                            <p style="color: #94A3B8; font-size: 12.5px; margin-bottom: 0; line-height: 1.5;">{{ Str::limit($sData['meta_description'], 85) }}</p>
                        </a>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</section>
@endsection
