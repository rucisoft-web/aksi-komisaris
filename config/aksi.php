<?php

/*
| Konfigurasi AKSI Komisaris.
|
| Izin per peran adalah templat bawaan. Nama peran dan organ di perusahaan
| boleh berbeda; yang dipetakan di sini hanya kemampuan teknisnya.
*/

return [

    'disclaimer' => 'Catatan pengawasan mendukung akuntabilitas, tetapi aplikasi ini tidak memberikan jaminan pembebasan tanggung jawab hukum.',

    // MFA wajib bagi semua pengguna. Bisa dimatikan sementara untuk demo lokal.
    'mfa_required' => (bool) env('AKSI_MFA_REQUIRED', true),

    // Kirim juga notifikasi lewat email (isi email tidak memuat isi rahasia).
    'notify_mail' => (bool) env('AKSI_NOTIFY_MAIL', false),

    'permissions' => [
        'komisaris_utama' => [
            'dokumen.lihat_dewan', 'dokumen.unggah', 'rapat.lihat', 'risalah.sahkan',
            'permintaan.buat', 'permintaan.setujui', 'permintaan.nilai', 'nasihat.buat', 'nasihat.sahkan',
            'tindaklanjut.lihat', 'tindaklanjut.kelola', 'tindaklanjut.periksa', 'tenggat.setujui',
            'isu.kelola', 'ai.gunakan', 'audit.lihat',
        ],
        'komisaris_independen' => [
            'dokumen.lihat_dewan', 'dokumen.unggah', 'rapat.lihat', 'permintaan.buat', 'permintaan.nilai',
            'nasihat.buat', 'tindaklanjut.lihat', 'tindaklanjut.periksa', 'isu.kelola', 'ai.gunakan',
        ],
        'komisaris' => [
            'dokumen.lihat_dewan', 'dokumen.unggah', 'rapat.lihat', 'permintaan.buat', 'permintaan.nilai',
            'nasihat.buat', 'tindaklanjut.lihat', 'tindaklanjut.periksa', 'isu.kelola', 'ai.gunakan',
        ],
        'anggota_komite' => [
            'dokumen.unggah', 'rapat.lihat', 'permintaan.buat', 'nasihat.buat', 'tindaklanjut.lihat',
            'isu.kelola', 'ai.gunakan',
        ],
        'sekretariat' => [
            'dokumen.lihat_dewan', 'dokumen.unggah', 'dokumen.kelola', 'rapat.lihat', 'rapat.kelola',
            'permintaan.buat', 'permintaan.kirim', 'tindaklanjut.lihat', 'tindaklanjut.kelola', 'isu.kelola',
        ],
        'penghubung' => [
            'permintaan.jawab', 'tindaklanjut.tanggapi', 'dokumen.unggah',
        ],
        'admin_perusahaan' => [
            'pengaturan.kelola', 'audit.lihat',
        ],
    ],

    // Peran yang otomatis menjadi anggota ruang resmi dewan.
    'board_space_roles' => ['komisaris_utama', 'komisaris_independen', 'komisaris', 'sekretariat'],

    // Peran yang mendapat ruang pribadi.
    'private_space_roles' => ['komisaris_utama', 'komisaris_independen', 'komisaris', 'anggota_komite'],

    'uploads' => [
        'disk' => env('AKSI_DISK', 'local'),
        'max_kb' => (int) env('AKSI_MAX_UPLOAD_KB', 51200),
        'mimes' => ['pdf', 'docx', 'xlsx', 'csv', 'pptx', 'txt', 'png', 'jpg', 'jpeg'],
    ],

    'extraction' => [
        'pdftotext' => env('AKSI_PDFTOTEXT_PATH', '/usr/bin/pdftotext'),
        'tesseract' => env('AKSI_TESSERACT_PATH', 'tesseract'),
        // Halaman dengan teks kurang dari ini dianggap hasil pindai/kualitas rendah.
        'min_chars_per_page' => 80,
        'chunk_chars' => 1800,
    ],

    'followups' => [
        'remind_days_before' => [7, 1],
        'escalate_days_after' => 14,
    ],

    'ai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('OPENAI_MODEL', 'gpt-5-mini'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 120),
        'max_sources' => (int) env('AKSI_AI_MAX_SOURCES', 24),
        'max_tool_rounds' => 4,
        // true = permintaan AI dijalankan langsung saat dikirim (tanpa antrean/cron).
        'run_sync' => (bool) env('AKSI_AI_RUN_SYNC', false),
        'circuit_breaker_failures' => 3,
        'circuit_breaker_minutes' => 10,
    ],
];
