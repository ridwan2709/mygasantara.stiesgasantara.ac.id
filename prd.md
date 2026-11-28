# Product Requirements Document (PRD) & Master Context — SIAKAD

> Dokumen ini disusun otomatis (Reverse-PRD) oleh ZettBOT dari inspeksi kode eksisting di `d:\SERVER\www\siakad` tanpa mengubah kode aplikasi yang sudah berjalan.

---

## ZONA ATAS: SPESIFIKASI KEBUTUHAN PRODUK (BLUEPRINT SISTEM EKSISTING)

### 1. Nama Projek & Problem Statement
- **Nama Projek:** SIAKAD (Sistem Informasi Akademik) — sesuai `APP_NAME=SIAKAD` pada `.env.example` dan layout utama.
- **Problem Statement:** Mengelola proses akademik, perkuliahan/e-learning, presensi pegawai & mahasiswa, bimbingan, serta keuangan/tagihan dan honorarium dalam satu aplikasi web berbasis peran (*role-based*).

### 2. Peran Pengguna (*User Roles*)
Berdasarkan `routes/web.php`, `app/Models/User.php`, dan `resources/views/layouts/app.blade.php`:
1. **Superadmin (`superadmin`)** — Akses penuh seluruh fakultas, master data, manajemen user, KRS, kehadiran dosen, dan pengaturan GPS.
2. **Admin Fakultas (`admin_fakultas`)** — Pengelola tingkat fakultas (diarahkan ke `admin.dashboard`).
3. **Admin Prodi (`admin_prodi`)** — Pengelola tingkat program studi (data mahasiswa prodi, data & approval KRS, serta fitur dosen jika merangkap).
4. **Dosen (`dosen`)** — Bimbingan mahasiswa, input nilai (`penilaian`), presensi, LMS (materi & tugas), kehadiran saya, dan honorarium saya.
5. **Mahasiswa (`mahasiswa`)** — Portal akademik, transkrip/riwayat kuliah, presensi, jadwal kuliah, KHS/nilai, LMS (materi & tugas), biodata, tagihan saya, export, dan modul AI/KRS/KP/Skripsi (sebagian menu sidebar saat ini di-comment di layout).
6. **Bendahara (`bendahara`)** — Dashboard keuangan, tagihan mahasiswa (buat, kirim WA via Fonnte, tandai dibayar, batal), honorarium, pengaturan tarif, dan kehadiran saya.
7. **Staff (`staff`)** — Dashboard staff, KHS, dan kehadiran saya.

### 3. Arsitektur & Lingkungan Kerja
- **Backend Framework:** Laravel v12 (`laravel/framework: ^12.0`), PHP `^8.2` (terdeteksi rule PHP 8.4.15 di `AGENTS.md`).
- **Frontend Stack:** Blade templates (`resources/views/layouts/app.blade.php`), Tailwind CSS v3 (`tailwindcss: ^3.1.0`, `@tailwindcss/forms`), Alpine.js (`alpinejs: ^3.4.2`), Vite (`vite: ^7.0.7`).
- **Paket Utama:**
  - `laravel/breeze` (autentikasi)
  - `spatie/laravel-permission` (otorisasi/role)
  - `barryvdh/laravel-dompdf` (ekspor PDF)
  - `maatwebsite/excel` (impor/ekspor Excel)
  - `pestphp/pest` v4 (pengujian)
  - `laravel/pint` (formatter)
- **Integrasi Eksternal (`.env.example`):**
  - Gemini API (`GEMINI_API_KEY`) untuk fitur AI.
  - Fonnte WhatsApp Gateway (`FONNTE_TOKEN`, `FONNTE_API_URL`) untuk notifikasi tagihan WA.
- **Database:** Mendukung SQLite (development default di `.env.example`), MySQL, atau PostgreSQL.

### 4. Alur Kerja Aplikasi & Modul Terdaftar (berdasarkan `routes/`)
- **Autentikasi & Profil (`routes/auth.php`, `routes/profile.php`, `routes/notification.php`, `routes/health.php`):** Login, register, reset password, verifikasi email, edit/hapus profil, notifikasi in-app, serta endpoint `/health` & `/health/detailed`.
- **Modul Admin (`routes/admin/index.php`):**
  - Master Data: Fakultas, Prodi, Mata Kuliah, Kelas, Tahun Akademik, Import (`routes/admin/master-data/index.php`).
  - Manajemen: User, Mahasiswa, Dosen, Ruangan, Dokumen Dosen.
  - Akademik & Operasional: KRS & Approval KRS, Kehadiran & Pengaturan GPS, Honorarium, KP, Skripsi.
- **Modul Dosen (`routes/dosen/index.php`):** Dashboard, Bimbingan, Penilaian, Presensi, LMS, Materi, Tugas, Kehadiran, Honorarium, KP, Skripsi.
- **Modul Mahasiswa (`routes/mahasiswa/index.php`):** Dashboard, Biodata, Jadwal, KRS, KHS, Transkrip, Presensi, LMS, Materi, Tugas, Tagihan, Export, AI, KP, Skripsi.
- **Modul Bendahara, Staff & Pegawai (`routes/web.php`):** Dashboard Bendahara, Tagihan (`store`, `resend` WA, `markPaid`, `cancel`), Dashboard Staff, KHS Staff, Kehadiran Pegawai (`masuk`, `keluar` dengan middleware `can.absen`).

### 5. Desain Basis Data (Ringkasan Awal)
- Model inti yang telah diinspeksi: `User` (`app/Models/User.php` dengan kolom `name`, `email`, `password`, `role`, `fakultas_id`, `boleh_absen`, `prodi_id` serta relasi ke `Mahasiswa`, `Dosen`, `Fakultas`, `Prodi`).
- Detail tabel dan kolom modul lain (KRS, KHS, Tagihan, Honorarium, Kehadiran, LMS, dll.) akan dipetakan presisi dari `database/migrations/` dan Model terkait saat ada permintaan revisi pada modul tersebut.

### 6. Aturan Validasi, Keamanan & Rate Limiting (`bootstrap/app.php`)
- **Middleware Global:** `SecurityHeadersMiddleware`.
- **Middleware Alias:** `role`, `log.requests`, `fakultas.scope`, `can.absen`.
- **Rate Limiter:** `krs` (10/menit), `penilaian` (20/menit), `sensitive` (30/menit), `ai-chat` (10/menit).

### 7. Antarmuka Pengguna (UI)
- Layout utama (`resources/views/layouts/app.blade.php`) menggunakan desain SaaS modern dengan sidebar responsif/collapsible (Alpine.js), dukungan Dark Mode (`localStorage`), badge notifikasi real-time, serta navigasi khusus per peran (termasuk navigasi mobile terpisah untuk mahasiswa).
- Beberapa menu di sidebar saat ini dikomentari (`KRS`, `Skripsi / TA`, `Kerja Praktek`, `AI Advisor`).

### 8. Kriteria Pengujian
- Menggunakan **Pest v4** (`pestphp/pest`) melalui `php artisan test --compact`.
- Standar gaya kode menggunakan **Laravel Pint** (`vendor/bin/pint --dirty`).

---

## ZONA BAWAH: MASTER CONTEXT & LOGIKA SISTEM TERKINI (LIVING CONTEXT)

### Status Arsitektur & Lingkungan Aktif
- Laravel 12 Monolith (Blade + Tailwind CSS v3 + Alpine.js + Vite).
- Multi-role RBAC melalui kolom `users.role` + `spatie/laravel-permission` dan pembatasan lingkup fakultas (`fakultas.scope`).
- File aktif gateway ZettBOT: `ZettBOT.md` terpasang di root workspace.

### Skema & Relasi Inti Terverifikasi
- `users`: `name`, `email`, `password`, `role`, `fakultas_id`, `boleh_absen`, `prodi_id`.
- Relasi `User`: `hasOne(Mahasiswa)`, `hasOne(Dosen)`, `belongsTo(Fakultas)`, `belongsTo(Prodi)`.

### Logika Bisnis & Aturan Aktif
- `/` mengarah ke halaman `login`; `/dashboard` mengarahkan user berdasarkan `role` (`isAdminProdi()` atau pemetaan `$routes` di `routes/web.php`).
- Tagihan mahasiswa oleh bendahara mendukung pengiriman ulang notifikasi WhatsApp (Fonnte), penandaan dibayar, dan pembatalan.
- Kehadiran pegawai dilindungi middleware `can.absen` (`boleh_absen`).

### Log Pembaruan Resmi
- **Inisialisasi ZettBOT (Reverse-PRD):** Pembuatan `ZettBOT.md`, `prd.md`, dan `revisi.md` tanpa mengubah kode aplikasi eksisting.
