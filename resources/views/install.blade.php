<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Instalasi AKSI Komisaris</title>
    <style>
        :root { --primary: #4f46e5; --danger: #dc2626; --ok: #16a34a; --muted: #6b7280; --line: #e5e7eb; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background: #f9fafb; color: #111827; line-height: 1.5; }
        main { max-width: 720px; margin: 32px auto; padding: 0 16px; }
        h1 { font-size: 1.5rem; margin: 0 0 4px; }
        h2 { font-size: 1.05rem; margin: 0 0 12px; }
        .muted { color: var(--muted); font-size: .9rem; }
        .card { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 20px; margin-top: 16px; }
        label { display: block; font-weight: 500; font-size: .9rem; margin: 12px 0 4px; }
        input[type=text], input[type=email], input[type=password], input[type=url], input[type=number] { width: 100%; padding: 9px 11px; border: 1px solid #d1d5db; border-radius: 8px; font: inherit; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 0 12px; }
        .check { display: flex; gap: 8px; align-items: flex-start; font-weight: 400; }
        .check input { margin-top: 4px; }
        ul.req { list-style: none; padding: 0; margin: 0; }
        ul.req li { padding: 4px 0; font-size: .9rem; }
        .ok { color: var(--ok); } .bad { color: var(--danger); }
        .error { background: #fef2f2; color: var(--danger); border-radius: 8px; padding: 10px 12px; margin-top: 16px; font-size: .9rem; }
        .field-error { color: var(--danger); font-size: .85rem; }
        button { margin-top: 20px; background: var(--primary); color: #fff; border: 0; border-radius: 8px; padding: 11px 18px; font: inherit; font-weight: 600; cursor: pointer; width: 100%; }
        code, pre { background: #f3f4f6; border-radius: 6px; padding: 2px 6px; font-size: .85rem; }
        pre { padding: 10px 12px; white-space: pre-wrap; word-break: break-all; }
        a.btn { display: inline-block; margin-top: 12px; background: var(--primary); color: #fff; text-decoration: none; padding: 10px 16px; border-radius: 8px; font-weight: 600; }
        @media (max-width: 560px) { .row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<main>
    <h1>Instalasi AKSI Komisaris</h1>

    @isset($done)
        <div class="card">
            <h2 class="ok">✓ Instalasi selesai</h2>
            <p>Halaman instalasi sudah dinonaktifkan.</p>
            @if ($noCron)
                <p>Mode tanpa cron aktif: dokumen dan permintaan AI diproses langsung (layar menunggu lebih lama). Pengingat harian tindak lanjut tidak berjalan tanpa cron.</p>
            @else
                <p><strong>Langkah terakhir (wajib):</strong> di cPanel › <em>Cron Jobs</em>, tambahkan cron <em>Once Per Minute</em> dengan perintah:</p>
                <pre>{{ $cron }}</pre>
                <p class="muted">Bila hosting memakai lokasi PHP lain, ganti <code>/usr/local/bin/php</code> (misalnya <code>/opt/cpanel/ea-php83/root/usr/bin/php</code>). Tanpa cron, dokumen yang diunggah tertahan di status "Antre".</p>
            @endif
            <p>Admin platform (kelola perusahaan dan akun): <a href="{{ $appUrl }}/platform">{{ $appUrl }}/platform</a><br>
               Aplikasi pengguna: <a href="{{ $appUrl }}/app">{{ $appUrl }}/app</a></p>
            @if ($demo)
                <p>Data demo dimuat. Akun demo: <code>utama@demo.aksi.id</code>, <code>independen@demo.aksi.id</code>, <code>sekretariat@demo.aksi.id</code>, <code>direksi@demo.aksi.id</code> (dan lainnya), kata sandi <code>Demo#2026aksi</code>.</p>
            @endif
            <a class="btn" href="{{ $appUrl }}/app">Buka aplikasi</a>
        </div>
    @else
        <p class="muted">Isi data di bawah sekali saja. Siapkan dulu database MySQL di cPanel › <em>MySQL Databases</em> (buat database, buat user, lalu tambahkan user ke database dengan ALL PRIVILEGES).</p>

        <div class="card">
            <h2>Pemeriksaan server</h2>
            <ul class="req">
                @foreach ($checks as $label => $ok)
                    <li class="{{ $ok ? 'ok' : 'bad' }}">{{ $ok ? '✓' : '✗' }} {{ $label }}</li>
                @endforeach
            </ul>
            @if (in_array(false, $checks, true))
                <p class="muted">Perbaiki yang bertanda ✗ di cPanel › <em>Select PHP Version</em> / <em>MultiPHP Manager</em>, atau ubah izin folder menjadi 755 di File Manager, lalu muat ulang halaman ini.</p>
            @endif
        </div>

        @if ($errors->any())
            <div class="error">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
        @endif

        <form method="post" action="{{ url('/install') }}">
            @csrf
            <div class="card">
                <h2>Alamat aplikasi</h2>
                <label for="app_url">URL (persis seperti dibuka di browser)</label>
                <input type="url" id="app_url" name="app_url" value="{{ old('app_url', $defaults['app_url']) }}" required>
            </div>

            <div class="card">
                <h2>Database MySQL</h2>
                <div class="row">
                    <div><label for="db_host">Host</label><input type="text" id="db_host" name="db_host" value="{{ old('db_host', 'localhost') }}" required></div>
                    <div><label for="db_port">Port</label><input type="number" id="db_port" name="db_port" value="{{ old('db_port', 3306) }}" required></div>
                </div>
                <label for="db_database">Nama database (mis. userku_aksi)</label>
                <input type="text" id="db_database" name="db_database" value="{{ old('db_database') }}" required>
                <div class="row">
                    <div><label for="db_username">User database</label><input type="text" id="db_username" name="db_username" value="{{ old('db_username') }}" required></div>
                    <div><label for="db_password">Kata sandi database</label><input type="password" id="db_password" name="db_password"></div>
                </div>
            </div>

            <div class="card">
                <h2>Admin platform</h2>
                <label for="admin_name">Nama</label>
                <input type="text" id="admin_name" name="admin_name" value="{{ old('admin_name', 'Admin Platform') }}" required>
                <label for="admin_email">Email</label>
                <input type="email" id="admin_email" name="admin_email" value="{{ old('admin_email') }}" required>
                <div class="row">
                    <div><label for="admin_password">Kata sandi (min. 10 karakter)</label><input type="password" id="admin_password" name="admin_password" required></div>
                    <div><label for="admin_password_confirmation">Ulangi kata sandi</label><input type="password" id="admin_password_confirmation" name="admin_password_confirmation" required></div>
                </div>
            </div>

            <div class="card">
                <h2>Pilihan</h2>
                <label class="check"><input type="checkbox" name="demo" value="1" @checked(old('demo', true))> <span>Muat data demo BUMN fiktif (2 perusahaan, 7 akun) untuk promosi/uji coba</span></label>
                <label class="check"><input type="checkbox" name="mfa" value="1" @checked(old('mfa'))> <span>Wajibkan MFA (aplikasi autentikator) untuk semua pengguna. Disarankan untuk pemakaian sungguhan; matikan untuk situs demo.</span></label>
                <label class="check"><input type="checkbox" name="no_cron" value="1" @checked(old('no_cron'))> <span>Hosting saya tidak bisa memakai cron (proses dokumen dan AI dijalankan langsung)</span></label>
                <label for="openai_key">Kunci OpenAI (opsional, untuk Asisten AI)</label>
                <input type="password" id="openai_key" name="openai_key" value="{{ old('openai_key') }}" placeholder="sk-...">
            </div>

            <button type="submit">Pasang sekarang</button>
            <p class="muted">Proses membutuhkan sekitar 10–60 detik. Jangan tutup halaman.</p>
        </form>
    @endisset
</main>
</body>
</html>
