# Web MQ An-Nuur

Website dengan GrapesJS Page Builder dan CMS PHP Native.

## File dan rute

- `login.php` — login admin berbasis session.
- `editor.php?page=home` — editor halaman yang memerlukan login.
- `admin-messages.php` — dashboard pesan masuk yang memerlukan login.
- `admin-posts.php` — dashboard untuk membuat dan menghapus artikel blog.
- `post.php?slug=...` — halaman baca artikel yang dipublikasikan.
- `logout.php` — mengakhiri sesi admin.
- `index.php?page=home` — render halaman publik.
- `api.php` — API halaman, template, aset, dan pesan formulir.
- `database/schema.sql` — membuat database dan tabel CMS, termasuk `page_revisions`.
- Tabel `posts` di schema menyediakan tiga artikel contoh untuk pratinjau grid blog.
- `database/migrate_seo.php` — menambahkan kolom SEO pada tabel `pages` yang sudah ada.
- `database/seed_admin.php` — membuat akun admin awal jika tabel `users` masih kosong.

## Setup database dan akun awal

1. Impor `database/schema.sql` ke MySQL/MariaDB.
   Jika database lama sudah ada, jalankan `php database/migrate_seo.php` untuk menambahkan kolom metadata SEO.
2. Atur koneksi database melalui `DB_HOST`, `DB_PORT`, `DB_SOCKET`, `DB_NAME`, `DB_USER`, dan `DB_PASS` bila nilai default tidak sesuai.
3. Jalankan sekali melalui PHP CLI: `php database/seed_admin.php`.
4. Login melalui `login.php` memakai akun awal `admin` / `admin123`, lalu ganti password awal sebelum situs digunakan.

Seed hanya membuat akun default jika belum ada user. Setelah akun admin lain tersedia, skrip tidak menambahkan atau mengubah akun.

Aktifkan ekstensi PHP PDO MySQL, DOM, dan Fileinfo. Alamat email notifikasi formulir dapat diatur dengan `FORM_ADMIN_EMAIL`. Pengiriman email memakai konfigurasi `mail()` dari PHP/server.

## Menjalankan server lokal

Jalankan server PHP dari folder proyek menggunakan konfigurasi database yang sesuai, lalu buka `login.php` untuk administrasi dan `index.php?page=home` untuk halaman publik.
