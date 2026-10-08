@extends('seo.layout')

@section('title', 'Blog & Panduan Lengkap ISP, RT/RW Net & MikroTik | NODERA')
@section('meta_description', 'Pusat panduan teknis, tutorial MikroTik, konfigurasi OLT, manajemen jaringan FTTH, dan tips operasional software billing ISP & RT/RW Net terbaik.')
@section('meta_keywords', 'tutorial mikrotik, panduan rtrw net, konfigurasi olt gpon, software billing isp, tips bisnis wifi')

@section('schema')
@php
    $canonicalHost = preg_replace('/^www\./i', '', request()->getHost());
    $schemaData = [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => 'Blog & Panduan Billing ISP NODERA',
        'description' => 'Koleksi panduan teknis operasional ISP, konfigurasi MikroTik, NMS OLT, dan sistem billing.',
        'url' => 'https://' . $canonicalHost . '/blog',
        'publisher' => [
            '@type' => 'Organization',
            'name' => 'NODERA - DGTL Net Solution',
            'logo' => 'https://' . $canonicalHost . '/images/logo.png',
        ],
    ];
@endphp
<script type="application/ld+json">
{!! json_encode($schemaData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
</script>
@endsection

@section('content')
<!-- Hero Section -->
<section class="seo-hero">
    <div class="container">
        <div class="seo-badge">Knowledge Hub &amp; Dokumentasi Praktis</div>
        <h1 class="seo-h1">Blog &amp; Panduan Lengkap ISP &amp; RT/RW Net</h1>
        <p class="seo-subtitle">
            Kumpulan artikel tutorial teknis, best practice manajemen jaringan MikroTik &amp; FTTH, serta strategi mengoptimalkan pendapatan bisnis penyedia internet Anda.
        </p>

        <!-- Category Badges -->
        <div style="display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; margin-top: 20px;">
            <a href="/blog" class="btn btn-circle btn-xs {{ empty($category) ? 'btn-cyan' : '' }}" style="padding: 6px 14px; font-weight: 600; {{ !empty($category) ? 'background: #0f172a; color: #cbd5e1; border: 1px solid rgba(255,255,255,0.1);' : '' }}">Semua Topik</a>
            <a href="/blog?kategori=Tutorial+MikroTik" class="btn btn-circle btn-xs {{ $category === 'Tutorial MikroTik' ? 'btn-cyan' : '' }}" style="padding: 6px 14px; font-weight: 600; {{ $category !== 'Tutorial MikroTik' ? 'background: #0f172a; color: #cbd5e1; border: 1px solid rgba(255,255,255,0.1);' : '' }}">Tutorial MikroTik</a>
            <a href="/blog?kategori=Tutorial+RT/RW+Net" class="btn btn-circle btn-xs {{ $category === 'Tutorial RT/RW Net' ? 'btn-cyan' : '' }}" style="padding: 6px 14px; font-weight: 600; {{ $category !== 'Tutorial RT/RW Net' ? 'background: #0f172a; color: #cbd5e1; border: 1px solid rgba(255,255,255,0.1);' : '' }}">Tutorial RT/RW Net</a>
            <a href="/blog?kategori=Fiber+Optic+&+FTTH" class="btn btn-circle btn-xs {{ $category === 'Fiber Optic & FTTH' ? 'btn-cyan' : '' }}" style="padding: 6px 14px; font-weight: 600; {{ $category !== 'Fiber Optic & FTTH' ? 'background: #0f172a; color: #cbd5e1; border: 1px solid rgba(255,255,255,0.1);' : '' }}">Fiber Optic &amp; OLT</a>
            <a href="/blog?kategori=Wawasan+ISP" class="btn btn-circle btn-xs {{ $category === 'Wawasan ISP' ? 'btn-cyan' : '' }}" style="padding: 6px 14px; font-weight: 600; {{ $category !== 'Wawasan ISP' ? 'background: #0f172a; color: #cbd5e1; border: 1px solid rgba(255,255,255,0.1);' : '' }}">Wawasan Bisnis ISP</a>
        </div>
    </div>
</section>

<!-- Articles Grid -->
<section style="padding: 60px 0; background: #070B11;">
    <div class="container">
        <div class="row">
            @foreach($articles as $aSlug => $art)
                <div class="col-md-4 col-sm-6" style="margin-bottom: 30px;">
                    <div class="feature-card" style="display: flex; flex-direction: column; justify-content: space-between;">
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                <span style="background: rgba(0, 229, 255, 0.1); color: #00E5FF; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700;">{{ $art['category'] }}</span>
                                <span style="color: #64748B; font-size: 11.5px;"><i class="fa fa-clock-o"></i> {{ $art['reading_time'] }}</span>
                            </div>
                            <h3 style="color: #FFF; font-size: 17px; font-weight: 700; line-height: 1.4; margin-top: 5px; margin-bottom: 12px;">
                                <a href="/blog/{{ $aSlug }}" style="color: #FFF; text-decoration: none;" onmouseover="this.style.color='#00E5FF'" onmouseout="this.style.color='#FFF'">{{ $art['title'] }}</a>
                            </h3>
                            <p style="color: #94A3B8; font-size: 13px; line-height: 1.6; margin-bottom: 20px;">
                                {{ $art['excerpt'] }}
                            </p>
                        </div>
                        <div style="border-top: 1px solid rgba(255,255,255,0.05); padding-top: 14px; display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: #64748B; font-size: 12px;">{{ date('d M Y', strtotime($art['date'])) }}</span>
                            <a href="/blog/{{ $aSlug }}" style="color: #00E5FF; font-size: 12px; font-weight: 700; text-decoration: none;">
                                Baca Selengkapnya <i class="fa fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- CTA Banner -->
        <div class="cta-banner" style="margin-top: 40px;">
            <h3>Ingin Sistem Billing Otomatis untuk Jaringan Anda?</h3>
            <p>Implementasikan seluruh panduan di atas dengan bantuan software billing ISP NODERA yang siap pakai tanpa instalasi rumit.</p>
            <a href="/register" class="btn btn-cyan btn-circle btn-lg" style="padding: 12px 36px; font-weight: 800;">
                <i class="fa fa-rocket"></i> Daftar &amp; Uji Coba Gratis
            </a>
        </div>
    </div>
</section>
@endsection
