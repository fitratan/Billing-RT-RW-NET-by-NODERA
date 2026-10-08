# NODERA — Spesifikasi UI/UX & Perbaikan Layout (Mobile-First)

> Target: **90% pengguna memakai mobile**. Desain ditulis untuk dioperasikan *dari* mobile
> (bukan sekadar menyusut dari desktop), namun tetap responsif dan nyaman di desktop.

## 1. Prinsip Utama (Mobile-First, bukan Desktop-to-Mobile)

1. **Desain dari layar terkecil dulu.** Semua layout dipecah mulai dari viewport `< 375px`,
   lalu "naik" untuk tablet/desktop. Jangan mulai dari desain 1200px lalu diratakan.
2. **Satu breakpoint, satu sistem.** Semua panel memakai `1024px`. Sebelumnya ada yang 768,
   575, 992 → bentrok dan membuat perilaku tidak konsisten antar panel.
3. **Jempol dalam jangkauan.** Kontrol utama (nav bawah, tombol aksi, input) berada di bagian
   bawah/area nyaman ibu jari. Tap target minimal **44×44px** (WCAG 2.5.5).
4. **Scroll adalah raja.** Nav horizontal, jangan modal yang memaksa scroll panjang; konten
   mengalir vertikal satu kolom.
5. **Zoom tetap bisa.** Hapus `maximum-scale=1,user-scalable=no` (sudah diterapkan) agar
   pengguna tetap bisa memperbesar teks.

## 2. Kerangka Chrome yang Diseragamkan

Setiap panel (Superadmin, Admin, Kolektor, Teknisi, Portal Pelanggan, VPN) memakai **pola
yang sama**, dengan per-role hanya mengubah konten:

| Zona | Mobile (<1024px) | Desktop (>=1024px) |
|------|------------------|---------------------|
| **Header** | Sticky, blur, min-h 52px, ada tombol kembali/refresh/theme | **Admin/Teknisi/Superadmin: disembunyikan** (digantikan sidebar). Kolektor: tetap (tidak ada sidebar) |
| **Nav utama** | Bottom nav (bnav) di bawah, ikon + label, safe-area | **Sidebar kiri** (warna navy enterprise), tanpa bnav |
| **Konten** | 1 kolom, padding 16px, clearance bawah utk bnav | full-width, padding 28px 36px, `max-width` diff per-halaman |
| **Chrome backdrop** | Sticky header menutup konten secara benar (tanpa overlap) | - |

Aturan baku yang sudah diseragamkan di `public/css/corporate.css`:
- `.bnav` **selalu disembunyikan di desktop** (`@media(min-width:1024px){.bnav{display:none}}`).
- Bottom padding konten saat ada bnav memakai `body.has-bnav .content{padding-bottom:...}`
  ditambah `env(safe-area-inset-bottom)` untuk iPhone.
- `stats-grid`: 2 kolom (mobile) → 4 kolom (desktop). konsisten.
- Efek `:hover` dinonaktifkan pada layar sentuh (`@media (hover:none)`).

## 3. Status Perbaikan (tertanggung minggu ini)

| Masalah | Status | Catatan |
|---------|--------|---------|
| `maximum-scale=1,user-scalable=no` di 20 file → zoom diblokir | **DIPERBAIKI** | Semua viewport kini `width=device-width,initial-scale=1,viewport-fit=cover` |
| Bottom-nav menutupi konten di mobile / padding tidak konsisten | **DIPERBAIKI** (sentral di corporate.css) | `has-bnav` + safe-area diterapkan untuk semua panel |
| Bottom nav tetap tampil di desktop beberapa panel (mengganggu) | **DIPERBAIKI** | `.bnav{display:none}` di ≥1024 |
| Inkonsistensi breakpoint (1024 vs 768 vs 575) | **DIPERBAIKI** (sentralisasi) | Standar tunggal `1024px` |
| Dua sistem UI (React download vs Blade) | **DIPERBAIKI (parsial)** | Bundle React dihapus dari pipeline build; sumber tetap ada (lihat §4) |
| `.header` tersembunyi tidak konsisten antar panel | ✅ konsisten | Hanya di panel yang punya sidebar |

## 4. Keputusan Arsitektur: Blade + corporate.css (bukan React)

**Kondisi:** `resources/js/superadmin/*` (React + Tailwind) di-compile oleh Vite tetapi
**tidak pernah di-mount** di halaman mana pun ({`sa-root`} tidak ada di Blade). Sementara itu
seluruh aplikasi (92 view) berjalan di Blade terserahkan + `corporate.css`.

**Rekomendasi:** Standardkan ke **satu sistem Blade + `corporate.css`**.
- Karena 100% halaman sudah Blade & pending `css/corporate.css`, menambah React hanya
  menambah beban & dua sumber kebenaran (dua set token/dua theme toggle).
- Langkah lanjut yang disarankan (opsional, terpisah):
  - Hapus folder `resources/js/superadmin/` (source) — **SAAT INI DIBIARKAN** agar kau bisa
    revisi/pulihkan. Sudah di-deprecate dari `vite.config.js`.
  - Hapus `public/css/app.css` (tema "rail" lama yang tidak dipakai) & artefak build tua.

## 5. Rekomendasi UI-UX Lanjutan (untuk 90% mobile) — PRIORITAS

### P0 — Perbaiki baseline (sudah dikerjakan)
- ✅ Zoom diperbolehkan (hapus maxScale).
- Konsistensi chrome (header/sidebar/bnav) di semua panel.
- Clearance safe-area & bnav di semua layout.

### P1 — Lakukan ketika ada waktu (dampak pengguna terbesar)
1. **Kembali yang andah**: header kini mendukung `@section('back_url')` untuk target nyata;
   tanpa itu tetap pakai `history.back()` yang aman. Ada utility `a.back-link`. ⚙️ **diterapkan**
   (dukungan `back_url` di layout admin).
2. **Filter/toolbar sticky di atas konten** di mobile — utility `.toolbar-sticky` (CSS global;
   contoh diterapkan di halaman `admin/billing/invoices`). ⚙️ **utilitas diterapkan**
3. **Fixed action bar** untuk tombol utama (Simpan/Bayar/Kirim) — utility `.action-bar-fixed`
   (menyesuaikan `has-bnav` + safe-area). Gunakan pada form panjang. ⚙️ **utilitas diterapkan**
4. **Tabel di mobile → kartu** — utility `.table-cards` (CSS: `<table table-cards>` + `data-label`
   di tiap `td`; desktop tetap tabel). ⚙️ **utilitas diterapkan**, pakai pada tabel dense bertahap.
5. **Pull-to-refresh seragam** di semua daftar (sudah ada di sebagian besar layout).
6. **Batas lebar konten desktop**: `.content` kini `max-width:1280px` (variabel `--content-max`).
   ⚙️ **diterapkan global**.

### P2 — Tokoh sentuh & Pola mobile
- Tombol & baris klik `min-height:44px` (WA). Beri jarak antar item list ≥ 12px.
- Icon + label untuk semua aksi (bukan ikon doang) pada bottom nav (sudah).
- Support `dark`/`light` tema konsisten & simpan ke `localStorage('n_theme')` (sudah).

## 6. Checklist Verifikasi Mobile
Buka tiap panel di DevTools lebar 360px & 414px, pastikan:
- [ ] Tidak ada scroll-X (tidak ada elemen melebihi layar).
- [ ] Bottom nav / header tidak menutup konten (clearance benar, safe-area).
- [ ] Semua tombol ≥ 44px dan mudah diketuk tanpa salah pencet.
- [ ] `zoom` bisa (double-tap zoom bekerja).
- [ ] Teks formulir `font-size:16px` (hindari auto-zoom iPhone saat mengetik input) — sudah diterapkan.
- [ ] Di 1024+ sidebar muncul, header/bottom-nav hilang (atau tetap utk kolektor), konten lega.