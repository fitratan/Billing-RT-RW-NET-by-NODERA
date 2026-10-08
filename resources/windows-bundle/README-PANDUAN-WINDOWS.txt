================================================================================
   NODERA BILLING — PANDUAN INSTALASI WINDOWS (1-CLICK DOCKER)
   Website : https://example.com | https://billing.example.com
================================================================================

PRASYARAT WAJIB:
1. Pastikan di laptop/PC Windows Anda sudah terinstall "Docker Desktop".
   Unduh gratis di: https://www.docker.com/products/docker-desktop/
2. BUKA aplikasi Docker Desktop dan pastikan status Engine sudah "Running" 
   (Ikon paus di taskbar pojok kanan bawah berwarna hijau/tidak berkedip).

CARA INSTALASI PERTAMA KALI:
1. Ekstrak file ZIP ini ke folder (misal: C:\NODERA-BILLING).
2. Klik 2x pada file: "SETUP-WINDOWS.bat"
   - Masukkan License Key Anda (dari portal billing.example.com/isp-billing).
   - Masukkan Email & Password admin yang diinginkan.
   - Script akan otomatis mendownload container & migrasi database.
3. Setelah selesai, browser Anda akan otomatis membuka http://localhost:8080.

PENGGUNAAN HARIAN:
- Jalankan "START-NODERA.bat" untuk membuka aplikasi.
- Jalankan "STOP-NODERA.bat" untuk mematikan container.
- Jalankan "UPDATE-NODERA.bat" untuk update versi terbaru.

SOLUSI JIKA TIDAK BISA DIBUKA (BLANK / CONNECTION REFUSED):
- Penyebab: Aplikasi Docker Desktop belum dibuka / belum menyala saat menjalankan script.
- Solusi: Buka Docker Desktop di Windows, tunggu 1-2 menit hingga status Engine "Running", lalu jalankan kembali "START-NODERA.bat" atau "SETUP-WINDOWS.bat".
================================================================================

