# Panduan Instalasi AKSI Komisaris di cPanel

Panduan ini untuk hosting cPanel biasa (shared hosting atau VPS dengan cPanel). Ada dua jalur: **dengan Terminal/SSH** dan **tanpa Terminal** (memakai File Manager dan Cron Jobs).

Contoh di panduan memakai akun cPanel `userku`, folder program `/home/userku/aksi-komisaris`, dan alamat `https://aksi.domainanda.com`. Sesuaikan dengan milik Anda.

## 1. Prasyarat hosting

| Komponen | Ketentuan | Tempat di cPanel |
|---|---|---|
| PHP | **8.3 atau lebih baru** | *MultiPHP Manager* atau *Select PHP Version* (CloudLinux) |
| Ekstensi PHP | `pdo_mysql`, `mbstring`, `intl`, `zip`, `fileinfo`, `openssl`, `bcmath`, `ctype`, `tokenizer`, `xml`, `dom`, `curl`, `gd` | *Select PHP Version › Extensions* |
| Database | MySQL 8 atau MariaDB 10.6+ | *MySQL Databases* |
| Cron | **Wajib** (antrean ekstraksi dokumen, AI, pengingat) | *Cron Jobs* |
| SSL | Wajib untuk MFA dan cookie aman | *SSL/TLS Status* › Run AutoSSL |
| Batas unggah | `upload_max_filesize` dan `post_max_size` minimal 50M | *MultiPHP INI Editor* |

Opsional: `pdftotext` (poppler-utils) membuat ekstraksi PDF lebih rapi, dan `tesseract` untuk OCR dokumen hasil pindai. Di shared hosting biasanya tidak ada; aplikasi tetap jalan memakai pembaca PDF bawaan PHP, dan dokumen pindaian ditandai *perlu tinjauan*.

## 2. Buat database

1. Buka **MySQL Databases**.
2. *Create New Database*: misalnya `aksi` (menjadi `userku_aksi`).
3. *Add New User*: misalnya `aksi` (menjadi `userku_aksi`) dengan kata sandi kuat.
4. *Add User To Database* › centang **ALL PRIVILEGES** › Make Changes.

Pastikan collation database `utf8mb4_unicode_ci` (bawaan cPanel modern sudah utf8mb4).

## 3. Taruh program di server

Program diletakkan **di luar `public_html`**, supaya `.env`, kode, dan berkas dokumen tidak bisa dibuka dari browser.

### Jalur A: dengan Terminal

```bash
cd ~
git clone https://github.com/rucisoft-web/aksi-komisaris.git
cd aksi-komisaris
composer install --no-dev --optimize-autoloader
```

Repo privat meminta username GitHub dan *personal access token*. Bila `php -v` bukan 8.3, panggil PHP 8.3 langsung, misalnya `/opt/cpanel/ea-php83/root/usr/bin/php` atau `/opt/alt/php83/usr/bin/php`.

### Jalur B: tanpa Terminal (File Manager)

1. Pakai paket **`aksi-komisaris-cpanel.zip`** (sudah berisi `vendor`, tidak perlu Composer).
2. *File Manager* › `/home/userku` › **Upload** zip tersebut.
3. Klik kanan › **Extract** ke `/home/userku`. Hasilnya folder `/home/userku/aksi-komisaris`.
4. Hapus file zip setelah diekstrak.

## 4. Arahkan domain ke folder `public`

### Pilihan 1 (disarankan): subdomain atau addon domain

1. *Domains* › **Create A New Domain**, misalnya `aksi.domainanda.com`.
2. Matikan *Share document root*, isi **Document Root**: `aksi-komisaris/public`.
3. Aktifkan SSL untuk domain itu.

### Pilihan 2: domain utama yang terkunci di `public_html`

1. Salin **seluruh isi** `aksi-komisaris/public` ke `public_html`, termasuk `.htaccess` (*Settings* › *Show Hidden Files*).
2. Edit `public_html/index.php`, ganti tiga baris yang berisi `__DIR__.'/../` menjadi:

```php
if (file_exists($maintenance = __DIR__.'/../aksi-komisaris/storage/framework/maintenance.php')) {
require __DIR__.'/../aksi-komisaris/vendor/autoload.php';
$app = require_once __DIR__.'/../aksi-komisaris/bootstrap/app.php';
```

3. Setiap memperbarui program, salin ulang isi `public` ke `public_html` (kecuali `index.php` yang sudah diedit).

> Jangan mengekstrak seluruh program langsung ke `public_html`. Bila terlanjur, file `.htaccess` di akar program menolak akses ke `.env` dan `storage`, tetapi segera pindahkan programnya.

## 5. Isi file `.env`

Di folder `aksi-komisaris`, salin `.env.example` menjadi `.env`, lalu edit:

```dotenv
APP_NAME="AKSI Komisaris"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://aksi.domainanda.com
APP_TIMEZONE=Asia/Jakarta

DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=userku_aksi
DB_USERNAME=userku_aksi
DB_PASSWORD="kata-sandi-database"

SESSION_SECURE_COOKIE=true

# MFA wajib (Google Authenticator/Microsoft Authenticator). Untuk situs demo boleh false.
AKSI_MFA_REQUIRED=true

# Admin platform pertama (dibuat saat migrate --seed). Kosongkan lagi setelah instalasi.
AKSI_ADMIN_EMAIL=admin@domainanda.com
AKSI_ADMIN_PASSWORD="SandiSementara#2026"

# Data demo BUMN fiktif. false untuk pemakaian sungguhan.
AKSI_DEMO=false

# Asisten AI (opsional). Tanpa kunci, fitur lain tetap jalan.
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-5-mini
```

`APP_KEY=` dibiarkan kosong; diisi oleh `key:generate` di langkah 6. Kata sandi yang mengandung `#` atau spasi harus diberi tanda kutip.

## 6. Kunci aplikasi, tabel, dan admin

### Dengan Terminal

```bash
cd ~/aksi-komisaris
php artisan key:generate --force
php artisan migrate --seed --force
php artisan optimize
php artisan aksi:doctor
```

Admin platform juga bisa dibuat kapan saja: `php artisan aksi:create-admin admin@domainanda.com "Admin Platform" --password='SandiKuat#2026'`.

### Tanpa Terminal (Cron Jobs sekali jalan)

Tambahkan perintah berikut satu per satu di **Cron Jobs** dengan jadwal *Once Per Minute*. Tunggu 1–2 menit, periksa hasilnya, lalu **hapus** cron itu sebelum menambah yang berikutnya.

1. `cd /home/userku/aksi-komisaris && php artisan key:generate --force > storage/logs/setup-1.txt 2>&1`
2. `cd /home/userku/aksi-komisaris && php artisan migrate --seed --force > storage/logs/setup-2.txt 2>&1`
3. `cd /home/userku/aksi-komisaris && php artisan optimize > storage/logs/setup-3.txt 2>&1`
4. `cd /home/userku/aksi-komisaris && php artisan aksi:doctor > storage/logs/setup-4.txt 2>&1`

Hasil tiap langkah ada di `aksi-komisaris/storage/logs/setup-*.txt`. Setelah berhasil, kosongkan `AKSI_ADMIN_PASSWORD` di `.env` dan jalankan `php artisan optimize` lagi.

## 7. Cron permanen (wajib)

Satu cron, jadwal *Once Per Minute*, dibiarkan selamanya:

```
cd /home/userku/aksi-komisaris && php artisan schedule:run >> /dev/null 2>&1
```

Cron ini menjalankan antrean (ekstraksi teks dokumen dan permintaan AI) setiap menit, membuat jadwal dokumen wajib (06.30), pengingat dan eskalasi tindak lanjut (07.00), serta menonaktifkan keanggotaan yang masa jabatannya habis. Tidak perlu `queue:work` terpisah.

Tanpa cron, dokumen yang diunggah akan tertahan di status *Antre* dan permintaan AI tidak pernah selesai. Bila hosting tidak mengizinkan cron per menit, isi `AKSI_AI_RUN_SYNC=true` agar AI berjalan langsung (layar menunggu lebih lama), dan pakai cron tiap 5 menit.

## 8. Mulai memakai

1. Buka `https://aksi.domainanda.com/platform`, masuk sebagai admin platform. Pindai kode QR dengan aplikasi autentikator dan simpan kode pemulihan.
2. **Perusahaan** › tambah perusahaan (kode, nama, bentuk: BUMN/anak BUMN/emiten). Organ standar dan *Ruang Resmi Dewan Komisaris* dibuat otomatis.
3. **Akun** › buat akun dan tetapkan keanggotaan per perusahaan: peran, jabatan, masa berlaku. Peran yang tersedia: Komisaris Utama, Komisaris Independen, Anggota Dewan Komisaris, Anggota Komite, Sekretariat Dewan Komisaris, Direksi/Penghubung, Admin Perusahaan.
4. Pengguna masuk di `https://aksi.domainanda.com/app`. Sekretariat mengatur **Dokumen Wajib**, mengunggah dokumen, dan menyiapkan **Rapat**.
5. Admin Perusahaan menyalakan asisten AI di **Pengaturan Perusahaan** (bawaan: mati).

### Situs demo / promosi

Isi di `.env`:

```dotenv
AKSI_DEMO=true
AKSI_DEMO_PASSWORD="Demo#2026aksi"
AKSI_MFA_REQUIRED=false
```

lalu `php artisan aksi:demo` (atau lewat cron sekali jalan) dan `php artisan optimize`. Data yang dibuat (semua fiktif):

- **PT Energi Nusantara (Persero)** (BUMN) dan **PT Logistik Energi Nusantara** (anak usaha), sehingga tampilan Portofolio terlihat.
- Laporan manajemen, realisasi RKAP, profil risiko, LHA SPI di ruang komite, dokumen *sangat terbatas* yang dilarang ke AI, dan catatan di ruang pribadi.
- Rapat dengan paket beku, rapat mendatang yang bahannya belum lengkap, isu material, pertanyaan ke Direksi beserta jawaban, rekomendasi, dan tindak lanjut dengan status beragam (termasuk lewat tenggat dan tereskalasi).
- Satu contoh hasil Asisten AI (*Siapkan saya untuk rapat*) sehingga fitur AI bisa diperagakan tanpa kunci OpenAI.

Akun demo (kata sandi sama, `AKSI_DEMO_PASSWORD`):

| Peran | Email |
|---|---|
| Komisaris Utama | utama@demo.aksi.id |
| Komisaris Independen (juga Komisaris Utama anak usaha) | independen@demo.aksi.id |
| Komisaris | komisaris@demo.aksi.id |
| Anggota Komite Audit | komite@demo.aksi.id |
| Sekretariat Dewan Komisaris | sekretariat@demo.aksi.id |
| Direksi (penghubung) | direksi@demo.aksi.id |
| Admin Perusahaan | admin@demo.aksi.id |

## 9. Asisten AI dan data

- Hanya potongan teks dari dokumen yang **boleh dibaca pengguna** yang dikirim ke OpenAI, dengan `store: false`.
- Dokumen *rahasia* hanya dikirim bila kebijakan AI-nya *Izin khusus*; *sangat terbatas* hanya bila perusahaan juga mengizinkan; kebijakan *Dilarang* tidak pernah dikirim. Yang dikecualikan ditampilkan di bagian *Keterbatasan*.
- Setiap kutipan diperiksa terhadap teks sumber; rujukan palsu dibuang dan angka yang tidak ada di sumber ditandai.
- Dokumen hasil pindai berkualitas rendah ditahan (*perlu tinjauan*) sampai dikonfirmasi pengguna berwenang.

## 10. Memperbarui program

Dengan Terminal:

```bash
cd ~/aksi-komisaris
php artisan down
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
php artisan up
```

Tanpa Terminal: upload zip versi baru, ekstrak menimpa folder `aksi-komisaris` (zip tidak berisi `.env`, dan berkas di `storage` tidak terhapus), lalu jalankan `php artisan migrate --force` dan `php artisan optimize` lewat cron sekali jalan.

Selalu **backup database dan folder `storage/app/private`** (berkas dokumen) sebelum memperbarui.

## 11. Masalah umum

| Gejala | Solusi |
|---|---|
| Error 500 tanpa keterangan | Lihat `storage/logs/laravel-*.log`. Sementara boleh `APP_DEBUG=true`, lalu kembalikan ke `false`. |
| `Your PHP version … does not satisfy` | Ubah PHP domain ke 8.3, dan pastikan cron/Terminal juga memakai PHP 8.3. |
| `Identifier name ... is too long` atau `Specified key was too long` | Database bukan utf8mb4 atau MySQL terlalu lama. Pakai MySQL 8 / MariaDB 10.6+. |
| Dokumen tertahan di status *Antre* | Cron `schedule:run` belum aktif (bagian 7). |
| Status *Perlu tinjauan* | Teks hasil ekstraksi sedikit (dokumen pindaian). Buka dokumen › *Konfirmasi hasil ekstraksi* bila isinya sudah memadai, atau unggah versi PDF berteks. |
| Asisten AI: "OPENAI_API_KEY belum diisi" | Isi `OPENAI_API_KEY` di `.env`, lalu `php artisan optimize`. |
| Asisten AI: "Pemrosesan AI belum diaktifkan" | Admin Perusahaan menyalakan AI di *Pengaturan Perusahaan*. |
| Unggah dokumen gagal | Naikkan `upload_max_filesize`/`post_max_size` di *MultiPHP INI Editor*; aktifkan ekstensi **fileinfo** dan **zip**. |
| Halaman tampil tanpa gaya | Document root belum ke `aksi-komisaris/public`, atau isi `public` belum tersalin lengkap. |
| Semua halaman selain beranda 404 | `.htaccess` dari `public` belum ikut tersalin. |
| `419 Page Expired` saat login | `APP_URL` harus sama persis dengan alamat yang dibuka. Bila belum SSL, hapus `SESSION_SECURE_COOKIE=true`. |
| Pengguna kehilangan ponsel MFA | Admin platform › Akun › *Reset MFA*. |
| Perubahan `.env` tidak berlaku | `php artisan optimize:clear` lalu `php artisan optimize`. |
