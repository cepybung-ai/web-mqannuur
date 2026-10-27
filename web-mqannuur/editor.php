<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
checkRole(['super_admin']);

$user = authUser();

if (empty($_SESSION['page_builder_csrf'])) {
    $_SESSION['page_builder_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['page_builder_csrf'];
$initialSlug = 'home';
if (is_string($_GET['slug'] ?? null) && $_GET['slug'] !== '') {
    $initialSlug = $_GET['slug'];
} elseif (is_string($_GET['page'] ?? null) && $_GET['page'] !== '') {
    $initialSlug = $_GET['page'];
}
if (!pbValidSlug($initialSlug)) {
    $initialSlug = 'home';
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <title>Page Builder — Web MQ An-Nuur</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/grapesjs/0.23.6/css/grapes.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&amp;family=Lato:wght@300;400;700&amp;family=Merriweather:wght@300;400;700&amp;family=Montserrat:wght@300;400;600;700&amp;family=Nunito:wght@300;400;600;700&amp;family=Open+Sans:wght@300;400;600;700&amp;family=Oswald:wght@400;600&amp;family=Playfair+Display:wght@400;700&amp;family=Poppins:wght@300;400;600;700&amp;family=Raleway:wght@300;400;600;700&amp;family=Roboto:wght@300;400;500;700&amp;display=swap">
    <link rel="stylesheet" href="assets/css/editor.css">
</head>
<body>
    <header class="topbar" id="topbar">
        <div class="topbar-group topbar-left">
            <a id="back-dashboard" class="btn" href="dashboard.php" title="Kembali ke Dashboard">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i><span class="btn-label">Dashboard</span>
            </a>
            <button id="btn-toggle-left" class="icon-btn" type="button" aria-controls="left-sidebar" aria-expanded="true" title="Tampilkan panel kiri">
                <i class="fa-solid fa-bars" aria-hidden="true"></i>
            </button>
            <a class="brand" href="editor.php?page=home" aria-label="Web MQ Studio">
                <span class="brand-mark" aria-hidden="true">MQ</span>
                <span class="brand-text"><strong>Web MQ</strong><small>Visual Studio</small></span>
            </a>
            <div class="page-switch">
                <i class="fa-solid fa-file-lines" aria-hidden="true"></i>
                <select id="page-select" aria-label="Halaman aktif">
                    <option value="<?= htmlspecialchars($initialSlug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars($initialSlug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
                </select>
            </div>
            <button id="btn-manage-pages" class="btn" type="button" title="Tambah atau kelola halaman">
                <i class="fa-solid fa-plus" aria-hidden="true"></i><span class="btn-label">Halaman</span>
            </button>
        </div>

        <div class="topbar-group topbar-center">
            <div class="device-switcher" role="group" aria-label="Ukuran pratinjau">
                <button type="button" data-device="Desktop" aria-pressed="true" title="Desktop" aria-label="Desktop"><i class="fa-solid fa-desktop" aria-hidden="true"></i></button>
                <button type="button" data-device="Tablet" aria-pressed="false" title="Tablet 768px" aria-label="Tablet"><i class="fa-solid fa-tablet-screen-button" aria-hidden="true"></i></button>
                <button type="button" data-device="Mobile" aria-pressed="false" title="Mobile 320px" aria-label="Mobile 320"><i class="fa-solid fa-mobile-screen-button" aria-hidden="true"></i></button>
                <button type="button" data-device="Mobile 375" aria-pressed="false" title="Mobile 375px" aria-label="Mobile 375"><i class="fa-solid fa-mobile-screen" aria-hidden="true"></i></button>
            </div>
            <div class="undo-redo-group" role="group" aria-label="Riwayat perubahan">
                <button id="btn-undo" type="button" aria-label="Undo" title="Undo" disabled><i class="fa-solid fa-rotate-left" aria-hidden="true"></i></button>
                <button id="btn-redo" type="button" aria-label="Redo" title="Redo" disabled><i class="fa-solid fa-rotate-right" aria-hidden="true"></i></button>
            </div>
            <button id="btn-navigator" class="btn" type="button" title="Navigator layer (Ctrl/Cmd + Shift + N)">
                <i class="fa-solid fa-layer-group" aria-hidden="true"></i><span class="btn-label">Layer</span>
            </button>
        </div>

        <div class="topbar-group topbar-right">
            <span id="autosave-status" data-state="saved" role="status" aria-live="polite">Semua perubahan tersimpan</span>
            <span id="status" role="status" aria-live="polite">Memuat editor…</span>
            <a id="preview-page" class="btn" href="index.php?page=<?= rawurlencode($initialSlug) ?>" target="_blank" rel="noopener" title="Lihat halaman publik">
                <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i><span class="btn-label">Lihat</span>
            </a>
            <button id="btn-preview" class="btn" type="button" title="Preview draft di tab baru">
                <i class="fa-solid fa-eye" aria-hidden="true"></i><span class="btn-label">Preview</span>
            </button>
            <button id="save-page" class="btn btn-primary" type="button" title="Simpan halaman (Ctrl/Cmd + S)">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i><span class="btn-label">Simpan</span>
            </button>
            <div class="more-wrap">
                <button id="btn-more" class="icon-btn" type="button" aria-haspopup="menu" aria-expanded="false" title="Menu lainnya">
                    <i class="fa-solid fa-ellipsis" aria-hidden="true"></i>
                </button>
                <div id="more-menu" class="dropdown" role="menu" hidden>
                    <button id="btn-export" type="button" role="menuitem"><i class="fa-solid fa-download" aria-hidden="true"></i> Export JSON</button>
                    <button id="btn-import" type="button" role="menuitem"><i class="fa-solid fa-upload" aria-hidden="true"></i> Import JSON</button>
                    <button id="btn-templates" type="button" role="menuitem"><i class="fa-solid fa-shapes" aria-hidden="true"></i> Template Library</button>
                    <button id="btn-save-template" type="button" role="menuitem"><i class="fa-solid fa-bookmark" aria-hidden="true"></i> Simpan sebagai Template</button>
                    <button id="btn-code-editor" type="button" role="menuitem"><i class="fa-solid fa-code" aria-hidden="true"></i> Code Editor</button>
                    <button id="btn-shortcuts" type="button" role="menuitem"><i class="fa-solid fa-keyboard" aria-hidden="true"></i> Pintasan Keyboard</button>
                    <hr>
                    <a id="btn-messages" href="admin-messages.php" role="menuitem"><i class="fa-solid fa-inbox" aria-hidden="true"></i> Pesan Masuk</a>
                    <a id="btn-logout" href="logout.php" role="menuitem"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Logout</a>
                </div>
            </div>
            <button id="btn-toggle-right" class="icon-btn" type="button" aria-controls="right-sidebar" aria-expanded="true" title="Tampilkan panel properti">
                <i class="fa-solid fa-sliders" aria-hidden="true"></i>
            </button>
        </div>
        <input id="slug" type="hidden" value="<?= htmlspecialchars($initialSlug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    </header>

    <input type="file" id="import-file-input" accept=".json,application/json" hidden>

    <main class="workspace" id="workspace">
        <aside id="left-sidebar" class="sidebar leftbar" aria-label="Panel blok dan layer">
            <div class="panel-tabs" role="tablist" aria-label="Panel kiri">
                <button id="tab-blocks" class="panel-tab" type="button" role="tab" aria-selected="true" aria-controls="panel-blocks"><i class="fa-solid fa-cubes" aria-hidden="true"></i> Blok</button>
                <button id="tab-layers" class="panel-tab" type="button" role="tab" aria-selected="false" aria-controls="panel-layers"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Layer</button>
            </div>
            <div id="panel-blocks" class="panel-body" role="tabpanel" aria-labelledby="tab-blocks">
                <div class="panel-search">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input id="block-search" type="search" placeholder="Cari blok…" autocomplete="off" aria-label="Cari blok">
                </div>
                <div class="blocks-container"></div>
            </div>
            <div id="panel-layers" class="panel-body" role="tabpanel" aria-labelledby="tab-layers" hidden>
                <div id="layers-container" aria-label="Struktur komponen halaman"></div>
            </div>
        </aside>

        <div class="canvas-area">
            <div id="gjs" aria-label="Kanvas editor halaman"></div>
            <div class="canvas-toolbar" role="toolbar" aria-label="Kontrol zoom kanvas">
                <button id="zoom-out" type="button" title="Perkecil" aria-label="Perkecil"><i class="fa-solid fa-minus" aria-hidden="true"></i></button>
                <button id="zoom-level" type="button" title="Reset ke 100%" aria-label="Reset zoom">100%</button>
                <button id="zoom-in" type="button" title="Perbesar" aria-label="Perbesar"><i class="fa-solid fa-plus" aria-hidden="true"></i></button>
                <span class="divider" aria-hidden="true"></span>
                <button id="zoom-fit" type="button" title="Sesuaikan dengan kanvas" aria-label="Sesuaikan dengan kanvas"><i class="fa-solid fa-expand" aria-hidden="true"></i></button>
            </div>
        </div>

        <aside id="right-sidebar" class="sidebar rightbar" aria-label="Panel properti">
            <div class="panel-tabs" role="tablist" aria-label="Panel kanan">
                <button id="tab-style" class="panel-tab" type="button" role="tab" aria-selected="true" aria-controls="panel-style"><i class="fa-solid fa-palette" aria-hidden="true"></i> Gaya</button>
                <button id="tab-settings" class="panel-tab" type="button" role="tab" aria-selected="false" aria-controls="panel-settings"><i class="fa-solid fa-sliders" aria-hidden="true"></i> Atribut</button>
                <button id="tab-page" class="panel-tab" type="button" role="tab" aria-selected="false" aria-controls="panel-page"><i class="fa-solid fa-file-lines" aria-hidden="true"></i> Halaman</button>
            </div>
            <div id="panel-style" class="panel-body" role="tabpanel" aria-labelledby="tab-style">
                <div class="styles-container"></div>
            </div>
            <div id="panel-settings" class="panel-body" role="tabpanel" aria-labelledby="tab-settings" hidden>
                <div class="traits-container"></div>
            </div>
            <div id="panel-page" class="panel-body" role="tabpanel" aria-labelledby="tab-page" hidden>
                <div class="page-settings">
                    <div class="field">
                        <label for="page-title-input">Judul halaman</label>
                        <input id="page-title-input" type="text" maxlength="255" placeholder="Judul halaman">
                    </div>
                    <div class="field">
                        <label for="page-slug-display">Slug URL</label>
                        <input id="page-slug-display" type="text" readonly>
                    </div>
                    <p class="hint">Judul dapat diubah kapan saja. Slug mengikuti halaman aktif dan tidak dapat diubah dari panel ini.</p>
                    <button id="btn-page-settings-save" class="btn btn-primary btn-block" type="button"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Simpan Perubahan</button>
                    <button id="btn-page-settings-preview" class="btn btn-ghost btn-block" type="button"><i class="fa-solid fa-eye" aria-hidden="true"></i> Preview Halaman</button>
                </div>
            </div>
        </aside>

        <div id="panel-overlay" class="overlay" hidden></div>
    </main>

    <div id="page-manager-modal" class="template-modal" hidden>
        <section class="template-dialog" role="dialog" aria-modal="true" aria-labelledby="page-manager-title">
            <header class="template-dialog-header">
                <h2 id="page-manager-title">Kelola / Tambah Halaman Baru</h2>
                <button id="btn-close-page-manager" type="button" aria-label="Tutup">×</button>
            </header>
            <div class="page-manager-content">
                <form id="page-create-form" class="page-create-form">
                    <label for="new-page-title">Judul Halaman
                        <input id="new-page-title" name="title" type="text" maxlength="255" placeholder="Tentang Kami" required>
                    </label>
                    <label for="new-page-slug">Slug URL
                        <input id="new-page-slug" name="slug" type="text" maxlength="100" placeholder="about" pattern="[A-Za-z0-9-]+" required>
                    </label>
                    <button type="submit">Buat Halaman</button>
                </form>
                <div>
                    <table class="page-list">
                        <thead><tr><th>Halaman</th><th>Slug</th><th>Aksi</th></tr></thead>
                        <tbody id="page-list-body"><tr><td class="page-list-empty" colspan="3">Memuat halaman…</td></tr></tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>

    <div id="template-modal" class="template-modal" hidden>
        <section class="template-dialog" role="dialog" aria-modal="true" aria-labelledby="template-modal-title">
            <header class="template-dialog-header">
                <h2 id="template-modal-title">Pilih Preset Template</h2>
                <button id="btn-close-templates" type="button" aria-label="Tutup">×</button>
            </header>
            <div id="template-grid" class="template-grid" aria-live="polite">
                <p class="template-empty">Memuat preset…</p>
            </div>
        </section>
    </div>

    <div id="save-template-modal" class="template-modal" hidden>
        <section class="template-dialog" role="dialog" aria-modal="true" aria-labelledby="save-template-modal-title">
            <header class="template-dialog-header">
                <h2 id="save-template-modal-title">Simpan Desain sebagai Template</h2>
                <button id="btn-close-save-template" type="button" aria-label="Tutup">×</button>
            </header>
            <form id="save-template-form" class="save-template-form">
                <label for="template-name">
                    Nama Template
                    <input id="template-name" name="name" type="text" maxlength="255" required autocomplete="off">
                </label>
                <label for="template-description">
                    Deskripsi Singkat
                    <textarea id="template-description" name="description" maxlength="2000"></textarea>
                </label>
                <div class="save-template-actions">
                    <button id="btn-cancel-save-template" type="button">Batal</button>
                    <button id="btn-submit-save-template" type="submit">Simpan Template</button>
                </div>
            </form>
        </section>
    </div>

    <div id="code-editor-modal" class="template-modal" hidden>
        <section class="template-dialog code-editor-dialog" role="dialog" aria-modal="true" aria-labelledby="code-editor-modal-title">
            <header class="template-dialog-header">
                <h2 id="code-editor-modal-title">Custom Code Editor (HTML &amp; CSS)</h2>
                <button id="btn-close-code-editor" type="button" aria-label="Tutup">×</button>
            </header>
            <div class="code-editor-grid">
                <label class="code-editor-field" for="code-html-input">
                    HTML
                    <textarea id="code-html-input" spellcheck="false" autocapitalize="off" autocomplete="off"></textarea>
                </label>
                <label class="code-editor-field" for="code-css-input">
                    CSS
                    <textarea id="code-css-input" spellcheck="false" autocapitalize="off" autocomplete="off"></textarea>
                </label>
            </div>
            <footer class="save-template-actions code-editor-actions">
                <button id="btn-cancel-code" type="button">Batal</button>
                <button id="btn-apply-code" type="button">Terapkan Perubahan</button>
            </footer>
        </section>
    </div>

    <div id="shortcuts-modal" class="template-modal" hidden>
        <section class="template-dialog" role="dialog" aria-modal="true" aria-labelledby="shortcuts-modal-title">
            <header class="template-dialog-header">
                <h2 id="shortcuts-modal-title">Pintasan Keyboard</h2>
                <button id="btn-close-shortcuts" type="button" aria-label="Tutup">×</button>
            </header>
            <div class="shortcut-list">
                <div class="shortcut-row"><strong><kbd>Ctrl</kbd> / <kbd>⌘</kbd> + <kbd>S</kbd></strong><span>Simpan halaman</span></div>
                <div class="shortcut-row"><strong><kbd>Ctrl</kbd> / <kbd>⌘</kbd> + <kbd>Z</kbd></strong><span>Undo</span></div>
                <div class="shortcut-row"><strong><kbd>Ctrl</kbd> + <kbd>Y</kbd> atau <kbd>Ctrl</kbd> + <kbd>Shift</kbd> + <kbd>Z</kbd></strong><span>Redo</span></div>
                <div class="shortcut-row"><strong><kbd>Ctrl</kbd> / <kbd>⌘</kbd> + <kbd>P</kbd></strong><span>Buka preview halaman</span></div>
                <div class="shortcut-row"><strong><kbd>Ctrl</kbd> / <kbd>⌘</kbd> + <kbd>E</kbd></strong><span>Buka / tutup Code Editor</span></div>
                <div class="shortcut-row"><strong><kbd>Ctrl</kbd> / <kbd>⌘</kbd> + <kbd>Shift</kbd> + <kbd>N</kbd></strong><span>Buka / tutup Navigator Layer</span></div>
                <div class="shortcut-row"><strong><kbd>Esc</kbd></strong><span>Tutup panel atau modal yang sedang terbuka</span></div>
            </div>
        </section>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/waypoints/4.0.1/jquery.waypoints.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Counter-Up/1.0.0/jquery.counterup.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/typed.js/2.1.0/typed.umd.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/grapesjs/0.23.6/grapes.min.js"></script>

    <script src="assets/js/editor.js"></script>
</body>
</html>
