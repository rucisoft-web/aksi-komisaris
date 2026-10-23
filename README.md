# AKSI Komisaris

Aplikasi kerja pengawasan untuk Dewan Komisaris dan komitenya. Satu instalasi melayani banyak perusahaan (pola BUMN induk dan anak usaha), dengan data tiap perusahaan terpisah.

Fokus aplikasi: dokumen pengawasan, persiapan rapat, pertanyaan ke manajemen, nasihat/rekomendasi, tindak lanjut sampai ditutup dengan bukti, serta asisten AI (OpenAI) yang hanya menjawab dari dokumen yang boleh dibaca pengguna dan selalu menyertakan rujukan.

> Catatan pengawasan mendukung akuntabilitas, tetapi aplikasi ini tidak memberikan jaminan pembebasan tanggung jawab hukum. Seluruh keluaran AI berstatus draf.

## Teknologi

- PHP 8.3, Laravel 13, Filament 5 (Livewire). Tidak perlu Node.js di server.
- MySQL 8 / MariaDB 10.6+. Pencarian teks memakai indeks FULLTEXT.
- Antrean `database` yang dijalankan cron `schedule:run` (cocok untuk cPanel).
- Berkas disimpan privat di `storage/app/private` dan hanya bisa diunduh lewat pemeriksaan akses.
- Ekstraksi teks: PDF (pdftotext bila ada, cadangan pustaka PHP), DOCX, PPTX, XLSX/CSV (per sel), TXT; OCR tesseract bila tersedia.
- OpenAI Responses API dengan `store: false`, keluaran terstruktur (JSON schema), dan pemanggilan fungsi.

## Fitur utama

| Area | Isi |
|---|---|
| Beranda Saya | Isu material, persiapan rapat, tindak lanjut lewat tenggat, dokumen wajib yang belum masuk, perubahan 14 hari, brief AI, catatan pribadi |
| Portofolio | Ringkasan hitungan lintas perusahaan untuk komisaris yang menjabat di beberapa perusahaan |
| Pusat Dokumen | Versi tak berubah, klasifikasi (publik/internal/rahasia/sangat terbatas), kebijakan AI per dokumen, ruang pribadi/dewan/komite/terbatas, anotasi |
| Dokumen Wajib | Jadwal per periode, status diterima/terlambat/belum diterima |
| Rapat | Agenda, bahan, pembekuan paket (versi terkunci + sidik SHA-256), benturan kepentingan, kehadiran, keputusan dan dissent, risalah |
| Pertanyaan & Jawaban | Draf pribadi → diajukan → disetujui/dikirim → dijawab → dinilai → ditutup, dengan klarifikasi |
| Nasihat & Rekomendasi | Draf → disahkan → disampaikan → ditanggapi |
| Tindak Lanjut | Status pelaksanaan terpisah dari ketepatan waktu, bukti wajib diverifikasi sebelum ditutup, perubahan tenggat perlu persetujuan, pengingat dan eskalasi otomatis |
| Asisten AI | 7 mode (siapkan rapat, jelaskan dokumen, bandingkan laporan, telaah proposal, periksa jawaban, susun nasihat, tanya dokumen); setiap klaim dicek terhadap kutipan sumber |
| Jejak Audit | Siapa melakukan apa, kapan, dari IP mana |
| Admin Platform (`/platform`) | Perusahaan, akun, keanggotaan, reset MFA |

## Menjalankan di komputer sendiri

```bash
composer install
cp .env.example .env            # ubah DB_*, APP_ENV=local, APP_DEBUG=true
php artisan key:generate
php artisan migrate --seed      # AKSI_DEMO=true untuk data demo
php artisan serve
```

Buka `http://localhost:8000/app`. Untuk demo lokal tanpa MFA, isi `AKSI_MFA_REQUIRED=false`.

Uji otomatis: `php artisan test` (15 tes: isolasi antarperusahaan, ruang pribadi, kebijakan AI, paket rapat beku, penutupan tindak lanjut, masa jabatan, validasi rujukan, MFA, dan semua halaman untuk setiap peran).

## Pemasangan di cPanel

Lihat [PANDUAN-CPANEL.md](PANDUAN-CPANEL.md).

## Perintah artisan

| Perintah | Fungsi |
|---|---|
| `aksi:create-admin email "Nama" --password=...` | Buat admin platform |
| `aksi:demo` | Muat data demo BUMN fiktif |
| `aksi:doctor` | Periksa kesiapan server |
| `aksi:required-documents` | Buat jadwal dokumen wajib per periode (harian otomatis) |
| `aksi:followups` | Pengingat H-7/H-1 dan eskalasi tindak lanjut (harian otomatis) |
| `aksi:deactivate-expired` | Nonaktifkan keanggotaan yang masa jabatannya habis (harian otomatis) |
