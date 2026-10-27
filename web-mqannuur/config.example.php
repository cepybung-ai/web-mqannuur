<?php
declare(strict_types=1);

/*
 * Salin file ini menjadi config.local.php lalu isi kredensial hosting.
 * Jangan commit/unggah config.local.php ke repositori publik.
 */
return [
    'db_host' => 'localhost',
    'db_port' => '3306',
    'db_name' => 'USER_webmq',
    'db_user' => 'USER_webmq',
    'db_pass' => 'PASSWORD_DATABASE',

    /*
     * Opsional. Gunakan absolut path di luar public_html, contoh:
     * '/home/USER/web-mqannuur-backups'
     * Jika dikosongkan, aplikasi memakai folder di luar document root
     * berdasarkan lokasi folder aplikasi.
     */
    // 'backup_dir' => '/home/USER/web-mqannuur-backups',

    // Opsional bila hosting memakai socket MySQL, bukan localhost/TCP.
    // 'db_socket' => '/var/run/mysqld/mysqld.sock',
];
