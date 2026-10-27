# Web MQ An-Nuur — Central CMS & Visual Page Builder

Website CMS + page builder berbasis PHP native, MariaDB, dan GrapesJS. Proyek ini berjalan di folder `web-mqannuur` dengan database MariaDB terpisah dari EduMQ.

## Rute utama

- Login: `login.php` → redirect ke `dashboard.php`
- Dashboard: `dashboard.php`
- Editor visual: `editor.php?slug=home` (juga menerima `?page=home`)
- Halaman publik: `index.php?page=home` atau `index.php` untuk home
- Berita publik: `post.php?slug=judul-berita`
- Unduhan publik: `download.php?id=1`
- API halaman: `api.php?action=load&slug=home` dan `api.php?action=save`

## Modul admin

- `dashboard.php` — statistik halaman, berita, galeri, pesan; aksi cepat; aktivitas terbaru.
- `admin-pages.php` — daftar/buat/ubah/hapus halaman, tombol Customize ke editor (home dilindungi).
- `admin-posts.php` — CRUD berita dengan sanitasi HTML dan status draft/published.
- `admin-events.php` — CRUD agenda/pengumuman dengan tanggal, lokasi, gambar, dan status.
- `admin-staff.php` — CRUD guru/staf/instruktur, foto, kategori, urutan, dan status tampil.
- `admin-downloads.php` — CRUD dokumen, unggah berkas atau URL eksternal, penghitung unduhan.
- `admin-gallery.php` — unggah/edit/hapus foto galeri, konversi WebP bila GD tersedia.
- `admin-links.php` — CRUD link aplikasi/menu, kategori, ikon, urutan, dan status aktif.
- `admin-themes.php` — pilih tema aktif, preview, dan lihat daftar template (Super Admin).
- `admin-settings.php` — identitas situs, logo, kontak, media sosial, dan teks footer (Super Admin).
- `admin-seo.php` — Meta Title, Meta Description, Open Graph image, generator `sitemap.xml` dan `robots.txt` (Super Admin).
- `admin-analytics.php` — kunjungan, unique visitor, grafik tren, halaman terpopuler, dan IP pengunjung (Super Admin).
- `admin-messages.php` — pesan formulir, export CSV, hapus pesan.
- `admin-users.php` — CRUD akun Super Admin/editor, reset kata sandi, proteksi Super Admin terakhir (Super Admin).
- `admin-logs.php` — audit log aktivitas CRUD, login/logout, IP, role, dan detail aksi dengan filter (Super Admin).
- `site_backup.php` — backup ZIP penuh (database SQL + `uploads/`), backup JSON, restore JSON, retensi, dan jadwal otomatis via cron (Super Admin).

Semua modul memakai layout modular bersama: `includes/header.php`, `includes/sidebar.php`, `includes/navbar.php`, dan `includes/footer.php`, dengan CSS/JS di `assets/css/admin.css` dan `assets/js/admin.js`.

## Peran pengguna

- **Super Admin** (`super_admin`) — akses penuh: halaman, editor visual, berita, agenda, guru/staf, galeri, dokumen, link, tema, identitas situs, SEO, analytics, user, audit log, dan backup/restore. Nilai lama `admin`/`operator` dinormalisasi menjadi `super_admin`.
- **Editor** (`editor`) — hanya dapat mengakses dashboard serta modul `admin-posts.php`, `admin-events.php`, `admin-staff.php`, `admin-downloads.php`, `admin-gallery.php`, dan `admin-messages.php`.

RBAC ditegakkan di `auth.php` melalui `hasRole($role)` dan `checkRole($allowed_roles)`. Setiap modul admin memanggil `checkRole()` di bagian atas. Editor yang membuka URL sensitif (`admin-users.php`, `admin-logs.php`, `admin-settings.php`, `admin-themes.php`, `admin-seo.php`, `admin-analytics.php`, `admin-pages.php`, `admin-links.php`, `site_backup.php`, atau `editor.php`) otomatis dialihkan ke `dashboard.php` dengan flash peringatan akses ditolak. API pengelolaan halaman/editor juga ditolak untuk editor. Menu sidebar, topbar, dan aksi dashboard disaring sesuai peran.

## Struktur folder

```text
web-mqannuur/
├── assets/
│   ├── css/            # admin.css, editor.css
│   └── js/             # admin.js, editor.js
├── includes/           # header, sidebar, navbar, footer admin, analytics & backup helper
├── themes/
│   ├── _shared/        # header/footer/page/single + CSS/JS dasar tema baru
│   ├── default/        # tema bawaan lengkap
│   ├── sekolah/        # tema akademik modern
│   ├── profil-lembaga/ # tema institusional editorial
│   └── portal-kegiatan/# tema portal agenda
│       ├── assets/css/theme.css
│       ├── theme.json  # metadata katalog tema
│       ├── header.php  # wrapper tema
│       ├── footer.php
│       ├── index.php   # beranda tema
│       ├── page.php
│       └── single-post.php
├── uploads/            # gallery, downloads, settings, seo
├── database/
│   ├── schema.sql
│   ├── start-db.sh
│   └── migrations/
└── *.php               # admin modules, public entry, API
```

## Database

Proyek memakai instance dan socket MariaDB sendiri:

- Socket: `/tmp/webmq-mariadb.sock`
- Datadir: `/var/lib/webmq-mariadb`
- Database: `web_mqannuur`
- User aplikasi: `web_mq_local` tanpa password (hanya akses ke database aplikasi)

Jalankan dari root/termux:

```sh
sh database/start-db.sh
sh database/stop-db.sh
```

`start-db.sh` bersifat idempotent: menyiapkan datadir bila belum ada, menyalakan server, membuat database/user, mengimpor `database/schema.sql` pada instalasi baru, dan otomatis menerapkan migrasi dashboard, RBAC, SEO/Analytics, serta Security/Audit/Backup bila tabel atau pengaturan terkait belum ada.

Konfigurasi database untuk hosting dibaca dari `config.local.php` (prioritas utama), lalu environment `WEB_MQ_DB_NAME`, `WEB_MQ_DB_USER`, `WEB_MQ_DB_PASS`, `WEB_MQ_DB_HOST`, `WEB_MQ_DB_PORT`, dan `WEB_MQ_DB_SOCKET`. Pada lingkungan pengembangan, aplikasi tetap memakai socket MariaDB lokal bila tidak ada konfigurasi lain.

Tabel utama: `pages`, `posts`, `events`, `staff`, `downloads`, `gallery`, `links`, `settings`, `templates`, `form_submissions`, `visits`, `users`, `activity_logs`, dan `login_attempts`. Tabel `visits` menyimpan path, judul halaman, IP, hash visitor, user agent, dan tanggal kunjungan. `activity_logs` menyimpan username, role, aksi, entitas, detail, IP, user agent, dan waktu; `login_attempts` menyimpan percobaan login gagal untuk pembatasan brute-force.

## Akun & migrasi

Autentikasi memakai tabel `users`, session PHP, CSRF token, dan pembatasan brute-force berbasis database: maksimal 5 percobaan gagal per IP dalam 15 menit. Akun lama di `page_builder_users` tetap dapat dipakai.

```sh
php84 database/seed_admin.php
```

Seeder hanya membuat akun jika tabel `users` masih kosong. Kredensial awal `admin` / `admin123`; segera ganti setelah login. Untuk database lama, migrasi dapat dijalankan manual:

```sh
mariadb -u <db-admin> -p web_mqannuur < database/migrations/20261026_create_admin_auth.sql
mariadb -u <db-admin> -p web_mqannuur < database/migrations/20261026_create_form_submissions.sql
mariadb -u <db-admin> -p web_mqannuur < database/migrations/20261026_create_dashboard_modules.sql
mariadb -u <db-admin> -p web_mqannuur < database/migrations/20261026_add_theme_setting.sql
mariadb -u <db-admin> -p web_mqannuur < database/migrations/20261026_rbac_super_admin.sql
mariadb -u <db-admin> -p web_mqannuur < database/migrations/20261026_seo_analytics.sql
mariadb -u <db-admin> -p web_mqannuur < database/migrations/20261026_security_audit_backup.sql
```

Migrasi dashboard menambahkan tabel `events`, `staff`, `downloads`, `gallery`, `settings`, `posts`, `links`, kolom `users.role`, serta pengaturan situs bawaan. Migrasi tema menambahkan setting `active_theme` dengan nilai awal `default`. Migrasi RBAC mengubah kolom `users.role` menjadi `ENUM('super_admin','editor')`, memetakan akun `admin`/`operator` lama ke `super_admin`, dan menormalkan nilai lain menjadi `editor`. Migrasi SEO dan Analytics menambahkan tabel `visits` serta pengaturan `seo_meta_title`, `seo_meta_description`, `seo_og_image`, dan `seo_site_url`. Migrasi Security/Audit/Backup menambahkan tabel `activity_logs`, `login_attempts`, serta pengaturan `backup_schedule`, `backup_retention`, `backup_token`, `backup_last_run`, `backup_last_status`, dan `backup_last_file`.

## Upload ke hosting (cPanel/php.id)

Kebutuhan server: PHP **8.0+** (disarankan 8.2/8.3), ekstensi `pdo_mysql`, `dom`, `zlib`, `mbstring`, `curl`, dan `fileinfo`, serta Apache/LiteSpeed agar file `.htaccess` bekerja. Ekstensi `gd` opsional untuk konversi WebP otomatis.

1. Ekstrak paket hosting ke `public_html` (domain utama) atau subfolder yang diinginkan.
2. Buat database dan user MySQL dari cPanel. Catat host (biasanya `localhost`), nama database, username, dan password.
3. Buka phpMyAdmin, pilih database tersebut, lalu impor `database/hosting_install.sql`. File ini berisi seluruh tabel dan akun awal `admin` / `admin123`.
4. Salin `config.example.php` menjadi `config.local.php`, lalu isi `db_host`, `db_name`, `db_user`, dan `db_pass`. Jangan biarkan file contoh dipakai apa adanya.
5. Buat folder backup di luar `public_html`, misalnya `/home/USER/web-mqannuur-backups`, lalu isi `backup_dir` di `config.local.php` dengan path absolut tersebut. Pastikan folder dapat ditulis PHP.
6. Pastikan folder `uploads/` dapat ditulis PHP. Login, segera ganti password `admin123`, lalu atur **SEO → URL situs** sesuai domain. Setelah itu klik **Generate sitemap.xml** dan **Generate robots.txt** agar kedua file memakai domain hosting, bukan alamat lokal.
7. Untuk backup otomatis, aktifkan jadwal di `site_backup.php`, lalu buat cron cPanel. Contoh perintah CLI:
   ```sh
   /usr/local/bin/php /home/USER/public_html/backup-cron.php
   ```
   Alternatif HTTP memakai URL bertoken yang ditampilkan di halaman backup.
8. Aktifkan HTTPS dari cPanel. Cookie session otomatis memakai atribut `Secure` saat HTTPS aktif.

File `.htaccess` di root memblokir `config.local.php`, file SQL, README, dan dokumentasi; folder `database/`, `includes/`, serta `uploads/` juga memiliki aturan akses sendiri. Jangan mengunggah folder `.codewhale` atau file `config.local.php` ke repositori publik. Jika hosting memakai Nginx (bukan Apache/LiteSpeed), minta hosting menambahkan aturan deny untuk folder dan file tersebut.

## Tema website

Tema aktif disimpan pada setting `active_theme` dan dapat diganti dari `admin-themes.php` (Super Admin). Katalog tema dibaca dari subfolder `themes/` yang memiliki `header.php`, `footer.php`, `index.php`, `page.php`, dan `single-post.php`; metadata opsional dibaca dari `theme.json`. Folder `themes/_shared/` berisi komponen dasar yang dipakai tema `sekolah`, `profil-lembaga`, dan `portal-kegiatan`, sehingga ketiganya tetap menampilkan berita, pengumuman, dan kontak meskipun konten Customize kosong.

Preview tema lain memakai `index.php?theme=slug` dan hanya dapat dibuka Super Admin. Pengunjung selalu melihat tema aktif. Konten Customize `home` tetap tersimpan saat tema diganti dan dirender sebagai section tambahan pada tema baru.

## Fitur editor

- Panel kiri: blok dengan pencarian + navigator layer.
- Panel kanan: Gaya, Atribut, dan pengaturan Halaman (termasuk judul).
- Device preview: Desktop, Tablet, Mobile 320, Mobile 375.
- Zoom kanvas, undo/redo, autosave, preview draft, template library.
- Tombol kembali ke dashboard dan deep link `editor.php?slug=...`.
- Layout responsif: panel menjadi drawer di tablet/mobile, topbar menyesuaikan tinggi otomatis.
- Style manager mencakup layout, flexbox, grid, ukuran, tipografi, latar, jarak, border, dan efek.

### Blok visual & animasi

Blok dikelompokkan menjadi **Visual**, **Media**, dan **Layout**; setiap kategori dan item memakai ikon Font Awesome. Lima blok khusus tersedia di panel:

- **Typing Text** — teks berjalan dengan Typed.js; atur teks awal, daftar kata (satu per baris), kecepatan, dan opsi ulang.
- **Counter Up Statistik** — angka animasi dengan format `angka|suffix|label`, satu baris per item (maksimal 8).
- **TikTok Auto Feed** — kartu video dari URL TikTok; data diambil melalui oEmbed dan di-cache.
- **Floating WhatsApp** — tombol melayang dengan nomor, pesan otomatis, label, posisi, warna, dan mode ikon di mobile.
- **Shape Divider Wave** — pembatas gelombang berbasis CSS `mask-image` agar tetap aman setelah sanitasi HTML.

Setiap komponen yang dipilih otomatis mendapat trait **Animasi AOS** (fade, zoom, flip). Pilihan ini menulis atribut `data-aos`; halaman publik memanggil `AOS.init()`, sedangkan kanvas editor memberi class `aos-animate` agar elemen tetap terlihat. Trait tersedia untuk blok bawaan maupun blok baru.

Halaman publik memuat jQuery, Waypoints, Counter-Up, Typed.js, dan AOS dari CDN. `themes/default/assets/js/theme-enhancements.js` menjalankan animasi, mengambil data TikTok, dan menyegarkan ulang seluruh efek setelah draft preview mengganti isi `body`. Bila CDN AOS gagal dimuat, elemen `[data-aos]` langsung ditampilkan tanpa animasi.

TikTok oEmbed memakai cache di `PB_TIKTOK_CACHE_DIR` atau `sys_get_temp_dir()/web-mq-tiktok-cache` dengan TTL 6 jam dan fallback data lama. Endpoint publiknya adalah `api.php?action=tiktok_oembed&url=...`; URL harus menunjuk ke host `tiktok.com` dengan format video/photo yang valid.

## Catatan penting

- Sanitasi HTML memerlukan ekstensi PHP DOM.
- **GD tidak tersedia di lingkungan ini**, sehingga konversi WebP otomatis tidak aktif. Galeri tetap menyimpan gambar dalam format asli dan menampilkan peringatan di admin; konversi akan aktif otomatis bila ekstensi GD tersedia.
- Backup ZIP penuh dibuat tanpa ekstensi `ZipArchive`: SQL dump database + seluruh `uploads/` + `manifest.json` dikemas oleh writer ZIP berbasis zlib. Backup JSON tetap tersedia untuk restore data database. Simpan file backup di tempat aman karena berisi hash kata sandi.
- Restore antarmuka menerima backup JSON versi 2 (mengganti seluruh tabel) dan versi 1 (hanya tabel lama). File ZIP penuh disediakan untuk arsip/offsite, bukan untuk restore satu klik.
- Editor menyimpan gambar di `uploads/` (maksimum 5 MB per gambar). Folder harus dapat ditulis PHP; SVG disanitasi saat upload dan eksekusi script diblokir oleh `.htaccess` pada Apache.
- CDN GrapesJS, Font Awesome, font, jQuery, Waypoints, Counter-Up, Typed.js, dan AOS memerlukan koneksi internet. Editor tetap dapat dibuka tanpa CDN animasi, tetapi blok visual tertentu hanya menampilkan versi statis.


## SEO & Analytics

`admin-seo.php` mengelola Meta Title, Meta Description, URL situs, dan gambar Open Graph. Nilai tersebut dipakai oleh header semua tema; judul halaman non-beranda tetap memakai judul halaman sebagai awalan. Gambar unggulan berita tetap menjadi prioritas Open Graph, lalu gambar SEO, lalu logo situs. Tombol **Generate sitemap.xml** membuat sitemap berisi home, seluruh halaman, dan berita berstatus published. Tombol **Generate robots.txt** memblokir area admin, API, editor, login, backup, folder database, dan folder includes, serta mencantumkan lokasi sitemap.

`includes/analytics.php` mencatat kunjungan halaman publik secara otomatis dari header tema. Setiap request GET mencatat path, judul halaman, IP, hash visitor (IP + user agent), user agent, dan tanggal. Sesi admin, preview tema, respons error (4xx/5xx), dan request non-GET diabaikan. `admin-analytics.php` menampilkan grafik pageview/unique visitor, statistik 7/30/90 hari, halaman terpopuler, dan kunjungan terbaru. Dashboard Super Admin juga menampilkan widget ringkasan 7 hari. Kedua modul hanya dapat diakses `super_admin`.

## Security, Audit Log & Auto-Backup

Aplikasi mengirim header keamanan dasar (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, dan `Cross-Origin-Opener-Policy`), memakai cookie session `HttpOnly`/`SameSite=Lax`, serta `session.use_strict_mode`. Form login dan seluruh form/aksi admin memakai token CSRF; aksi API editor memakai header `X-CSRF-Token`.

Login dibatasi maksimal **5 percobaan gagal per IP dalam 15 menit**. Hitungan disimpan di tabel `login_attempts` sehingga tidak bisa di-reset hanya dengan menghapus cookie. Login berhasil menghapus hitungan untuk IP tersebut. Percobaan gagal, login, logout, dan seluruh aksi CRUD admin dicatat oleh `logActivity()` ke `activity_logs`; `admin-logs.php` menampilkan username, role, aksi, entitas, detail, IP, user agent, dan waktu dengan filter serta pagination.

`site_backup.php` dapat membuat **Full Backup ZIP** berisi `database.sql`, `manifest.json`, dan folder `uploads/`. File disimpan di luar document root secara default pada `../web-mqannuur-backups/` (dapat diubah lewat `WEB_MQ_BACKUP_DIR`). Retensi default 10 file dan dapat diatur 1–50. Backup manual dan backup terjadwal memakai lock agar tidak berjalan bersamaan.

Aktifkan jadwal di `site_backup.php` (harian/mingguan/bulanan), lalu panggil URL cron yang ditampilkan secara berkala:

```sh
# contoh crontab: cek setiap jam
0 * * * * curl -fsS "https://domain-anda/backup-cron.php?token=TOKEN_RAHASIA" >/dev/null 2>&1
# atau via CLI
php84 /path/ke/web-mqannuur/backup-cron.php
```

Endpoint hanya membuat backup jika interval jadwal sudah terlewati. Token dapat dibuat ulang dari antarmuka; menyimpan jadwal pertama kali akan membuat token otomatis. Jangan bagikan URL cron karena token tersebut memberi izin memicu pembuatan backup.
