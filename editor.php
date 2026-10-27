<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (empty($_SESSION['page_builder_csrf'])) {
    $_SESSION['page_builder_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['page_builder_csrf'];
$initialSlug = $_GET['page'] ?? $_GET['slug'] ?? 'home';
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
    <script>
        (() => {
            try {
                const saved = localStorage.getItem('editor_theme');
                const hasSystemPreference = typeof window.matchMedia === 'function';
                const systemDark = hasSystemPreference && window.matchMedia('(prefers-color-scheme: dark)').matches;
                const initial = saved === 'dark' || saved === 'light'
                    ? saved
                    : (hasSystemPreference ? (systemDark ? 'dark' : 'light') : 'dark');
                document.documentElement.dataset.theme = initial;
            } catch (_) {
                document.documentElement.dataset.theme = 'dark';
            }
        })();
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/grapesjs/0.23.6/css/grapes.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&amp;family=Lato:wght@300;400;700&amp;family=Merriweather:wght@300;400;700&amp;family=Montserrat:wght@300;400;600;700&amp;family=Nunito:wght@300;400;600;700&amp;family=Open+Sans:wght@300;400;600;700&amp;family=Oswald:wght@400;600&amp;family=Playfair+Display:wght@400;700&amp;family=Poppins:wght@300;400;600;700&amp;family=Raleway:wght@300;400;600;700&amp;family=Roboto:wght@300;400;500;700&amp;display=swap">
    <style>
        :root {
            color-scheme: light;
            --topbar: 58px;
            --sidebar: 230px;
            --panel: 270px;
            --bg-primary: #f1f5f9;
            --bg-secondary: #ffffff;
            --text-color: #172033;
            --border-color: #e2e8f0;
            --gjs-main-color: #0f9f78;
            --topbar-bg: #102a43;
            --topbar-text: #ffffff;
            --panel-heading-bg: #f8fafc;
            --muted-text: #64748b;
        }
        :root[data-theme="dark"] {
            color-scheme: dark;
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --text-color: #e2e8f0;
            --border-color: #334155;
            --gjs-main-color: #34d399;
            --topbar-bg: #020617;
            --topbar-text: #f8fafc;
            --panel-heading-bg: #1e293b;
            --muted-text: #a8b6c8;
        }
        * { box-sizing: border-box; }
        html, body { width: 100%; height: 100%; margin: 0; font-family: Inter, system-ui, -apple-system, "Segoe UI", sans-serif; color: var(--text-color); }
        body { background: var(--bg-primary); }
        .topbar { height: var(--topbar); display: flex; align-items: center; gap: 10px; padding: 0 12px; overflow-x: auto; background: var(--topbar-bg); color: var(--topbar-text); }
        .brand { font-size: 15px; font-weight: 700; white-space: nowrap; }
        .device-switcher { display: flex; flex: 0 0 auto; align-items: center; gap: 4px; padding: 3px; border: 1px solid var(--border-color); border-radius: 8px; background: color-mix(in srgb, var(--topbar-bg), white 8%); }
        .topbar .device-button { padding: 6px 9px; color: #d9e2ec; background: transparent; font-size: 11px; }
        .topbar .device-button:hover, .topbar .device-button[aria-pressed="true"] { color: #102a43; background: #fff; }
        .topbar .history-button { display: flex; flex: 0 0 auto; align-items: center; gap: 6px; padding: 7px 9px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-color); }
        .topbar .history-button:disabled { opacity: .5; cursor: not-allowed; }
        .topbar .history-button:not(:disabled) { opacity: 1; cursor: pointer; }
        .topbar .sidebar-toggle { display: inline-flex; flex: 0 0 auto; align-items: center; justify-content: center; gap: 7px; min-height: 40px; border: 1px solid var(--border-color); background: color-mix(in srgb, var(--topbar-bg), white 8%); color: var(--topbar-text); transition: all .3s ease; }
        .topbar .sidebar-toggle[aria-expanded="true"] { background: var(--gjs-main-color); color: #fff; }
        .slug-wrap { display: flex; align-items: center; gap: 8px; margin-left: auto; }
        .slug-wrap label { color: var(--topbar-text); font-size: 12px; }
        #slug { width: 150px; padding: 8px 10px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-secondary); color: var(--text-color); }
        .language-wrap { display: flex; align-items: center; gap: 6px; white-space: nowrap; color: var(--topbar-text); font-size: 12px; }
        #language-switcher { min-width: 72px; padding: 7px 9px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-secondary); color: var(--text-color); }
        #btn-add-translation { background: #6251c7; color: #fff; border-color: #6251c7; }
        #btn-site-backup { background: #0f766e; color: #fff; border-color: #0f766e; white-space: nowrap; }
        .backup-dialog { width: min(650px, calc(100vw - 28px)); }
        .backup-content { display: grid; gap: 16px; padding: 20px; color: var(--text-color); }
        .backup-section { display: grid; gap: 11px; padding: 16px; border: 1px solid var(--border-color); border-radius: 10px; background: var(--bg-primary); }
        .backup-section h3 { margin: 0; font-size: 16px; }
        .backup-section p { margin: 0; color: var(--muted-text); font-size: 13px; line-height: 1.5; }
        .backup-section input[type="file"] { max-width: 100%; padding: 9px; border: 1px solid var(--border-color); border-radius: 7px; background: var(--bg-secondary); color: var(--text-color); }
        .backup-export-button { background: #0f9f78 !important; color: #fff !important; }
        .backup-import-button { background: #dc5b32 !important; color: #fff !important; }
        #backup-status { min-height: 18px; margin: 0; color: var(--muted-text); font-size: 13px; }
        .translation-dialog { width: min(440px, calc(100vw - 28px)); }
        .translation-form { display: grid; gap: 16px; padding: 20px; color: var(--text-color); }
        .translation-form select { width: 100%; padding: 11px; border: 1px solid var(--border-color); border-radius: 7px; background: var(--bg-secondary); color: var(--text-color); }
        .translation-actions { display: flex; justify-content: flex-end; gap: 9px; }
        .topbar button, .topbar a { border: 0; border-radius: 6px; padding: 9px 13px; font-size: 13px; font-weight: 650; text-decoration: none; cursor: pointer; }
        #save-page { color: #fff; background: #0f9f78; }
        #save-page:disabled { opacity: .65; cursor: wait; }
        #btn-export { color: #fff; background: #334e68; }
        #btn-import { color: #102a43; background: #d9e2ec; }
        #btn-templates { color: #fff; background: linear-gradient(135deg, #7c3aed, #4f46e5); }
        #btn-save-template { color: #fff; background: #0e7490; }
        #btn-seo-manager { color: #fff; background: #475569; }
        #btn-revisions { color: #fff; background: #7c3aed; }
        #btn-admin-posts { flex: 0 0 auto; color: #fff; background: #0f766e; }
        #btn-code-editor { color: #fff; background: #475569; }
        #btn-preview { color: #fff; background: #2563eb; }
        #btn-shortcuts { flex: 0 0 auto; padding: 7px 9px; background: transparent; color: var(--topbar-text); border: 1px solid var(--border-color); }
        #btn-theme-toggle { flex: 0 0 auto; min-width: 40px; padding: 8px 10px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-color); font-size: 17px; }
        #preview-page { color: #fff; background: #334e68; }
        #btn-logout { flex: 0 0 auto; color: #fff; background: #b42318; }
        #status { min-width: 135px; color: #d9e2ec; font-size: 12px; }
        #autosave-status { flex: 0 0 auto; max-width: 190px; overflow: hidden; color: #d9e2ec; font-size: 11px; text-overflow: ellipsis; white-space: nowrap; }
        #autosave-status[data-state="dirty"] { color: #fcd34d; }
        #autosave-status[data-state="saving"] { color: #bfdbfe; }
        #autosave-status[data-state="saved"] { color: #a7f3d0; }
        #autosave-status[data-state="error"] { color: #fca5a5; }
        .template-modal { position: fixed; z-index: 10000; inset: 0; display: none; align-items: center; justify-content: center; padding: 20px; background: rgba(15, 23, 42, .68); }
        .template-dialog { width: min(960px, 100%); max-height: min(760px, 92vh); overflow: auto; border: 1px solid var(--border-color); border-radius: 16px; background: var(--bg-secondary); color: var(--text-color); box-shadow: 0 24px 80px rgba(15, 23, 42, .3); }
        .pages-dialog { width: min(900px, 100%); }
        .seo-dialog { width: min(980px, 100%); }
        .seo-header-title { display: grid; gap: 3px; }
        .seo-header-title small { color: var(--muted-text); font-size: 11px; font-weight: 600; }
        .seo-layout { display: grid; grid-template-columns: minmax(0, 1.05fr) minmax(270px, .95fr); gap: 22px; padding: 20px 22px; }
        .seo-fields { display: grid; align-content: start; gap: 14px; }
        .seo-field { display: grid; gap: 7px; color: var(--text-color); font-size: 12px; font-weight: 750; }
        .seo-field input, .seo-field textarea { width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 8px; outline-color: var(--gjs-main-color); background: var(--bg-primary); color: var(--text-color); font-family: inherit; font-size: 13px; line-height: 1.5; }
        .seo-field textarea { min-height: 76px; resize: vertical; }
        .seo-field small { color: var(--muted-text); font-size: 10px; font-weight: 500; }
        .seo-previews { display: grid; align-content: start; gap: 12px; }
        .seo-previews h3 { margin: 0; color: var(--text-color); font-size: 12px; }
        .seo-google-preview { padding: 14px; border: 1px solid var(--border-color); border-radius: 9px; background: var(--bg-primary); font-family: Arial, sans-serif; }
        .seo-google-url { overflow: hidden; color: #188038; font-size: 11px; text-overflow: ellipsis; white-space: nowrap; }
        .seo-google-title { display: block; margin: 5px 0 4px; overflow: hidden; color: #1a0dab; font-size: 18px; text-decoration: none; text-overflow: ellipsis; white-space: nowrap; }
        .seo-google-description { display: -webkit-box; overflow: hidden; color: #4d5156; font-size: 12px; line-height: 1.5; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
        .seo-social-preview { overflow: hidden; border: 1px solid var(--border-color); border-radius: 10px; background: var(--bg-primary); }
        .seo-social-image { display: grid; width: 100%; aspect-ratio: 1.91 / 1; place-items: center; overflow: hidden; background: linear-gradient(135deg, #d1fae5, #dbeafe); color: #475569; font-size: 12px; }
        .seo-social-image img { width: 100%; height: 100%; object-fit: cover; }
        .seo-social-copy { padding: 11px 13px; }
        .seo-social-domain { color: var(--muted-text); font-size: 10px; text-transform: uppercase; }
        .seo-social-title { margin: 4px 0; overflow: hidden; color: var(--text-color); font-size: 13px; font-weight: 750; text-overflow: ellipsis; white-space: nowrap; }
        .seo-social-description { display: -webkit-box; overflow: hidden; color: var(--muted-text); font-size: 11px; line-height: 1.45; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
        .seo-footer { display: flex; justify-content: flex-end; gap: 9px; padding: 14px 22px; border-top: 1px solid var(--border-color); }
        .seo-footer button { padding: 9px 14px; border: 0; border-radius: 7px; font-size: 12px; font-weight: 750; cursor: pointer; }
        #btn-cancel-seo { background: var(--bg-primary); color: var(--text-color); }
        #btn-save-seo { background: #0f9f78; color: #fff; }
        .revisions-dialog { width: min(760px, 100%); }
        .revision-list { display: grid; gap: 10px; padding: 18px 22px; }
        .revision-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: 16px; padding: 13px 14px; border: 1px solid var(--border-color); border-radius: 10px; background: var(--bg-primary); }
        .revision-info { display: grid; gap: 6px; min-width: 0; }
        .revision-date { color: var(--text-color); font-size: 13px; font-weight: 750; }
        .revision-badge { width: fit-content; padding: 4px 8px; border-radius: 999px; background: #e0e7ff; color: #4338ca; font-size: 10px; font-weight: 750; }
        .revision-badge.is-latest { background: #dcfce7; color: #166534; }
        .revision-restore { padding: 9px 12px; border: 0; border-radius: 7px; background: #7c3aed; color: #fff; font-size: 11px; font-weight: 750; cursor: pointer; }
        .revision-restore:disabled { opacity: .6; cursor: wait; }
        .revisions-empty { margin: 0; padding: 30px 14px; color: var(--muted-text); text-align: center; font-size: 13px; }
        .revision-footer { display: flex; justify-content: flex-end; padding: 12px 22px; border-top: 1px solid var(--border-color); }
        #btn-close-revisions { padding: 9px 14px; border: 0; border-radius: 7px; background: var(--bg-primary); color: var(--text-color); font-weight: 700; cursor: pointer; }
        .page-create-form { display: grid; grid-template-columns: 1fr 1fr auto; align-items: end; gap: 12px; padding: 20px 22px; border-bottom: 1px solid var(--border-color); }
        .page-form-field { display: grid; gap: 6px; color: var(--text-color); font-size: 12px; font-weight: 700; }
        .page-form-field input { width: 100%; min-width: 0; padding: 10px 11px; border: 1px solid var(--border-color); border-radius: 7px; outline-color: var(--gjs-main-color); background: var(--bg-primary); color: var(--text-color); font: inherit; }
        #btn-create-page { padding: 10px 14px; border: 0; border-radius: 7px; background: #0f9f78; color: #fff; font-weight: 700; cursor: pointer; }
        #btn-create-page:disabled { opacity: .65; cursor: wait; }
        .pages-list-wrap { max-height: 45vh; overflow: auto; padding: 0 22px 20px; }
        .pages-table { width: 100%; border-collapse: collapse; color: var(--text-color); font-size: 13px; text-align: left; }
        .pages-table th, .pages-table td { padding: 11px 9px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        .pages-table th { position: sticky; top: 0; background: var(--bg-secondary); color: var(--muted-text); font-size: 11px; text-transform: uppercase; }
        .pages-table code { color: var(--muted-text); }
        .pages-table td:last-child { white-space: nowrap; }
        .pages-table td:last-child button { margin: 2px; padding: 7px 10px; border: 0; border-radius: 6px; background: #2563eb; color: #fff; font-weight: 650; cursor: pointer; }
        .pages-table td:last-child button[data-page-action="delete"] { background: #dc2626; }
        .pages-table td:last-child button:disabled { opacity: .6; cursor: wait; }
        #pages-feedback { min-height: 18px; margin: 0; padding: 0 22px 14px; color: #b91c1c; font-size: 13px; }
        .template-modal-header { position: sticky; z-index: 1; top: 0; display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 18px 22px; border-bottom: 1px solid var(--border-color); background: var(--bg-secondary); }
        .template-modal-header h2 { margin: 0; color: var(--text-color); font-size: 18px; }
        #template-close, #seo-modal-close, #revisions-modal-close { width: 36px; height: 36px; border: 0; border-radius: 8px; background: var(--bg-primary); color: var(--text-color); font-size: 22px; cursor: pointer; }
        .template-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 16px; padding: 22px; }
        .template-card { display: flex; min-height: 220px; flex-direction: column; padding: 20px; border: 1px solid var(--border-color); border-radius: 12px; background: var(--bg-secondary); }
        .template-card-mark { display: grid; width: 42px; height: 42px; place-items: center; border-radius: 11px; background: #ede9fe; color: #6d28d9; font-size: 19px; }
        .template-card h3 { margin: 16px 0 8px; color: var(--text-color); font-size: 16px; }
        .template-card p { flex: 1; margin: 0 0 18px; color: var(--muted-text); font-size: 13px; line-height: 1.6; }
        .template-use { padding: 10px 13px; border: 0; border-radius: 8px; background: #5b45d6; color: #fff; font-weight: 700; cursor: pointer; }
        .template-use:hover { background: #4934bd; }
        .template-message { grid-column: 1 / -1; margin: 0; padding: 18px; color: var(--muted-text); text-align: center; }
        .template-save-dialog { width: min(560px, 100%); }
        .template-save-form { display: grid; gap: 9px; padding: 22px; }
        .template-save-form label { margin-top: 7px; color: var(--text-color); font-size: 13px; font-weight: 700; }
        .template-save-form input, .template-save-form textarea { width: 100%; padding: 11px 12px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-primary); color: var(--text-color); font: inherit; }
        .template-save-form textarea { min-height: 100px; resize: vertical; }
        .template-save-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 14px; }
        .template-save-actions button { padding: 10px 14px; border: 0; border-radius: 8px; font-weight: 700; cursor: pointer; }
        #btn-cancel-template { background: var(--bg-primary); color: var(--text-color); }
        #btn-submit-template { background: #0e7490; color: #fff; }
        #btn-submit-template:disabled { opacity: .65; cursor: wait; }
        #template-save-error { min-height: 18px; margin: 0; color: #b91c1c; font-size: 13px; }
        .shortcuts-dialog { width: min(640px, 100%); }
        .shortcuts-list { display: grid; gap: 0; margin: 0; padding: 8px 22px 20px; list-style: none; }
        .shortcuts-list li { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 12px 0; border-bottom: 1px solid var(--border-color); color: var(--text-color); font-size: 13px; }
        .shortcuts-list li:last-child { border-bottom: 0; }
        .shortcut-keys { display: flex; flex: 0 0 auto; align-items: center; gap: 5px; color: var(--text-color); }
        .shortcut-keys kbd { padding: 4px 7px; border: 1px solid var(--border-color); border-radius: 5px; background: var(--bg-primary); font: 600 11px/1.2 ui-monospace, monospace; }
        .code-editor-modal { position: fixed; z-index: 10002; inset: 0; display: none; align-items: center; justify-content: center; padding: 16px; background: rgba(15, 23, 42, .72); }
        .code-editor-dialog { display: flex; width: min(1120px, 100%); height: min(820px, 92vh); flex-direction: column; overflow: hidden; border: 1px solid var(--border-color); border-radius: 14px; background: var(--bg-secondary); color: var(--text-color); box-shadow: 0 24px 80px rgba(15, 23, 42, .35); }
        .code-editor-header, .code-editor-footer { display: flex; flex: 0 0 auto; align-items: center; justify-content: space-between; gap: 14px; padding: 15px 20px; border-bottom: 1px solid var(--border-color); background: var(--bg-secondary); }
        .code-editor-header h2 { margin: 0; color: var(--text-color); font-size: 17px; }
        #code-editor-close { width: 36px; height: 36px; border: 0; border-radius: 8px; background: var(--bg-primary); color: var(--text-color); font-size: 22px; cursor: pointer; }
        .code-editor-body { display: grid; min-height: 0; flex: 1 1 auto; grid-template-columns: 1fr 1fr; gap: 14px; padding: 16px; }
        .code-editor-field { display: flex; min-width: 0; min-height: 0; flex-direction: column; gap: 8px; color: var(--text-color); font-size: 13px; font-weight: 700; }
        .code-editor-field textarea { width: 100%; min-height: 0; flex: 1 1 auto; resize: none; padding: 14px; border: 1px solid var(--border-color); border-radius: 9px; outline-color: var(--gjs-main-color); background: var(--bg-primary); color: var(--text-color); font: 13px/1.55 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; tab-size: 2; }
        .code-editor-footer { justify-content: flex-end; border-top: 1px solid var(--border-color); border-bottom: 0; }
        .code-editor-footer button { padding: 10px 15px; border: 0; border-radius: 8px; font-weight: 700; cursor: pointer; }
        #btn-apply-code { background: #0f9f78; color: #fff; }
        #btn-cancel-code { background: var(--bg-primary); color: var(--text-color); }
        .workspace { height: calc(100vh - var(--topbar)); min-height: 0; display: grid; grid-template-columns: var(--sidebar) minmax(0, 1fr) var(--panel); background: var(--bg-primary); transition: all .3s ease; }
        .sidebar { min-width: 0; overflow: auto; background: var(--bg-secondary); color: var(--text-color); border-right: 1px solid var(--border-color); transition: all .3s ease; }
        .leftbar { grid-column: 1; }
        #gjs { grid-column: 2; }
        .rightbar { grid-column: 3; }
        .workspace.left-collapsed { grid-template-columns: 0 minmax(0, 1fr) var(--panel); }
        .workspace.left-collapsed .leftbar { visibility: hidden; opacity: 0; overflow: hidden; }
        .workspace.right-collapsed { grid-template-columns: var(--sidebar) minmax(0, 1fr) 0; }
        .workspace.right-collapsed .rightbar { visibility: hidden; opacity: 0; overflow: hidden; }
        .workspace.left-collapsed.right-collapsed { grid-template-columns: 0 minmax(0, 1fr) 0; }
        .workspace.left-collapsed.right-collapsed #gjs { grid-column: 2; }
        .workspace.canvas-fullscreen { grid-template-columns: 0 minmax(0, 1fr) 0 !important; }
        .workspace.canvas-fullscreen #gjs { grid-column: 2 !important; width: 100%; }
        .rightbar { border-right: 0; border-left: 1px solid var(--border-color); }
        #btn-navigator[aria-expanded="true"] { background: #6d28d9; color: #fff; }
        .navigator-panel { position: fixed; z-index: 9998; top: var(--topbar); right: 0; bottom: 0; display: flex; width: min(330px, calc(100vw - 24px)); flex-direction: column; border: 1px solid var(--border-color); border-right: 0; border-radius: 12px 0 0 0; background: var(--bg-secondary); color: var(--text-color); box-shadow: 0 16px 48px rgba(15, 23, 42, .28); transform: translateX(105%); visibility: hidden; pointer-events: none; transition: transform .2s ease, visibility .2s ease; }
        .navigator-panel.is-open { transform: translateX(0); visibility: visible; pointer-events: auto; }
        .navigator-header { display: flex; flex: 0 0 auto; align-items: center; justify-content: space-between; gap: 12px; min-height: 48px; padding: 8px 12px 8px 16px; border-bottom: 1px solid var(--border-color); background: var(--bg-secondary); }
        .navigator-header h2 { margin: 0; color: var(--text-color); font-size: 13px; font-weight: 750; }
        #btn-navigator-close { width: 32px; height: 32px; border: 0; border-radius: 7px; background: var(--bg-primary); color: var(--text-color); font-size: 21px; cursor: pointer; }
        #layers-container { min-height: 0; flex: 1 1 auto; overflow: auto; padding: 6px; overscroll-behavior: contain; background: var(--bg-secondary); color: var(--text-color); scrollbar-color: var(--border-color) transparent; }
        #layers-container .gjs-layer, #layers-container .gjs-layer-item { background: var(--bg-secondary) !important; color: var(--text-color) !important; border-color: var(--border-color) !important; }
        #layers-container .gjs-layer:hover, #layers-container .gjs-layer.gjs-selected { background: var(--bg-primary) !important; }
        .panel-heading { margin: 0; padding: 12px 14px; color: var(--text-color); background: var(--panel-heading-bg); border-bottom: 1px solid var(--border-color); font-size: 12px; text-transform: uppercase; letter-spacing: .06em; }
        .panel-section { border-color: var(--border-color); }
        .panel-section + .panel-section { border-top: 1px solid var(--border-color); }
        #gjs { height: 100%; min-width: 0; overflow: hidden; background: var(--bg-primary); }
        .gjs-one-bg { background-color: var(--bg-secondary) !important; }
        .gjs-two-color { color: var(--text-color) !important; }
        .gjs-three-bg { background-color: var(--gjs-main-color) !important; color: #fff !important; }
        .gjs-four-color, .gjs-four-color-h:hover { color: var(--gjs-main-color) !important; }
        .gjs-pn-views-container, .gjs-pn-views, .gjs-pn-panel,
        .gjs-blocks-c, .gjs-sm-sector, .gjs-layer, .gjs-trt-traits,
        .gjs-field, .gjs-input-holder, .gjs-select, .gjs-clm-tags {
            background-color: var(--bg-secondary) !important;
            color: var(--text-color) !important;
            border-color: var(--border-color) !important;
        }
        .gjs-pn-views-container { border-left: 1px solid var(--border-color); }
        .gjs-cv-canvas { background-color: var(--bg-primary) !important; }
        .gjs-sm-sector-title, .gjs-clm-tags .gjs-sm-title,
        .gjs-block-category .gjs-title, .gjs-layer-title { color: var(--text-color) !important; }
        .gjs-sm-sector-title, .gjs-block-category .gjs-title { min-height: 42px; padding: 12px 14px !important; border-bottom: 1px solid var(--border-color) !important; background: var(--panel-heading-bg) !important; }
        .gjs-block { display: flex !important; min-height: 65px !important; height: auto !important; flex-direction: column; align-items: center; justify-content: center; gap: 5px; padding: 8px 5px !important; border: 1px solid var(--border-color) !important; border-radius: 8px !important; background: var(--bg-secondary) !important; color: var(--text-color) !important; }
        .gjs-blocks-c { display: grid !important; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 7px; padding: 9px !important; }
        .gjs-block:hover { border-color: var(--gjs-main-color) !important; box-shadow: 0 3px 10px rgba(15, 23, 42, .10); }
        .gjs-block__media { display: grid; min-height: 22px; place-items: center; color: var(--gjs-main-color); font-size: 18px; }
        .gjs-block__media svg { max-height: 22px; }
        .gjs-block-label { color: var(--text-color) !important; font-size: 10px; line-height: 1.25; text-align: center; }
        .gjs-field input, .gjs-field select, .gjs-field textarea, .gjs-sm-property input, .gjs-sm-property select { min-height: 44px; padding: 9px 11px !important; border-radius: 7px !important; font-size: 14px !important; }
        .gjs-sm-property .gjs-field, .gjs-sm-property .gjs-input-holder { min-height: 44px; }
        .gjs-sm-sector .gjs-sm-property { padding: 7px 10px; }
        .gjs-sm-sector input[type="color"], .gjs-field-color input[type="color"] { width: 44px; min-width: 44px; height: 44px; min-height: 44px; padding: 4px !important; }
        .gjs-mdl-container { background-color: rgba(2, 6, 23, .72) !important; }
        .gjs-mdl-dialog, .gjs-mdl-header, .gjs-mdl-content {
            background-color: var(--bg-secondary) !important;
            color: var(--text-color) !important;
            border-color: var(--border-color) !important;
        }
        .asset-search-toolbar { display: flex; align-items: center; gap: 8px; padding: 10px 12px; border-bottom: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-color); }
        .asset-search-toolbar i { color: var(--muted-text); }
        .asset-search-toolbar input { width: 100%; min-width: 0; padding: 9px 11px; border: 1px solid var(--border-color); border-radius: 7px; outline: none; background: var(--bg-primary); color: var(--text-color); font: inherit; }
        .asset-search-toolbar input:focus { border-color: var(--gjs-main-color); box-shadow: 0 0 0 2px color-mix(in srgb, var(--gjs-main-color), transparent 75%); }
        @media (max-width: 900px) {
            :root { --sidebar: 175px; --panel: 205px; }
            .topbar { gap: 7px; padding: 0 9px; }
            .brand { font-size: 13px; }
            .topbar .device-button { padding: 6px; }
            #status { min-width: 90px; }
            .workspace { grid-template-columns: minmax(0, 1fr); }
            .workspace.left-collapsed, .workspace.right-collapsed,
            .workspace.left-collapsed.right-collapsed { grid-template-columns: minmax(0, 1fr); }
            .workspace.canvas-fullscreen { grid-template-columns: minmax(0, 1fr) !important; }
            .workspace.canvas-fullscreen #gjs { grid-column: 1 !important; }
            .workspace .sidebar { position: fixed; z-index: 100; top: var(--topbar); bottom: 0; display: block; width: min(300px, 86vw); border: 1px solid var(--border-color); box-shadow: 0 16px 40px rgba(2, 6, 23, .28); transition: all .3s ease; }
            .workspace .leftbar { left: 0; transform: translateX(-105%); }
            .workspace .rightbar { right: 0; transform: translateX(105%); }
            .workspace:not(.left-collapsed) .leftbar { transform: translateX(0); visibility: visible; opacity: 1; pointer-events: auto; }
            .workspace:not(.right-collapsed) .rightbar { transform: translateX(0); visibility: visible; opacity: 1; pointer-events: auto; }
            .workspace.left-collapsed .leftbar, .workspace.right-collapsed .rightbar { display: block; visibility: visible; opacity: 0; pointer-events: none; }
            .workspace #gjs { grid-column: 1 !important; }
        }
        @media (max-width: 650px) {
            :root { --topbar: 54px; --sidebar: 125px; --panel: 150px; }
            .slug-wrap label, #status { display: none; }
            #slug { width: 90px; }
            .topbar button, .topbar a { padding: 8px; font-size: 12px; }
            .panel-heading { padding: 9px 7px; font-size: 10px; }
            .topbar .sidebar-toggle { width: 38px; min-width: 38px; padding: 7px !important; }
            .brand { font-size: 12px; }
            .sidebar-toggle-label { display: none; }
            .workspace .sidebar { width: min(280px, 88vw); }
        }
        @media (max-width: 760px) {
            .code-editor-dialog { height: 94vh; }
            .code-editor-body { grid-template-columns: 1fr; grid-template-rows: 1fr 1fr; gap: 10px; padding: 10px; }
            .code-editor-field textarea { min-height: 0; }
        }
        @media (max-width: 650px) {
            .page-create-form { grid-template-columns: 1fr; align-items: stretch; }
            .pages-list-wrap { padding-right: 12px; padding-left: 12px; }
            .pages-table { min-width: 600px; }
            .seo-layout { grid-template-columns: 1fr; padding: 15px; }
            .seo-footer { padding: 12px 15px; }
            .revision-row { grid-template-columns: 1fr; gap: 10px; }
            .revision-restore { width: 100%; }
            .revision-list { padding: 14px; }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="brand">Web MQ An-Nuur · Page Builder</div>
        <div class="device-switcher" role="group" aria-label="Ukuran tampilan kanvas">
            <button class="device-button" type="button" data-device="Desktop" aria-pressed="true" title="Desktop, lebar penuh">Desktop</button>
            <button class="device-button" type="button" data-device="Tablet" aria-pressed="false" title="Tablet, 768 piksel">Tablet</button>
            <button class="device-button" type="button" data-device="Mobile" aria-pressed="false" title="Mobile, 375 piksel">Mobile 375</button>
            <button class="device-button" type="button" data-device="Mobile 320" aria-pressed="false" title="Mobile kecil, 320 piksel">Mobile 320</button>
        </div>
        <button id="toggle-blocks-btn" class="sidebar-toggle" type="button" aria-controls="left-sidebar" aria-expanded="true" title="Tampilkan/sembunyikan panel blok">
            <i class="fa-solid fa-cubes" aria-hidden="true"></i><span class="sidebar-toggle-label">Blok</span>
        </button>
        <button id="toggle-styles-btn" class="sidebar-toggle" type="button" aria-controls="right-sidebar" aria-expanded="true" title="Tampilkan/sembunyikan panel gaya">
            <i class="fa-solid fa-sliders" aria-hidden="true"></i><span class="sidebar-toggle-label">Gaya</span>
        </button>
        <button id="btn-undo" class="history-button" type="button" disabled aria-label="Undo" title="Undo">
            <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
        </button>
        <button id="btn-redo" class="history-button" type="button" disabled aria-label="Redo" title="Redo">
            <i class="fa-solid fa-rotate-right" aria-hidden="true"></i>
        </button>
        <button id="btn-navigator" type="button" aria-expanded="false" aria-controls="navigator-panel" title="Buka Navigator (Ctrl/Cmd + Shift + N)"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Navigator</button>
        <div class="slug-wrap">
            <label for="slug">Halaman Aktif</label>
            <select id="slug" aria-label="Halaman aktif">
                <option value="<?= htmlspecialchars($initialSlug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars(pbTitleFromSlug($initialSlug), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> (<?= htmlspecialchars($initialSlug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>)</option>
            </select>
        </div>
        <div class="language-wrap">
            <label for="language-switcher">🌐 Bahasa:</label>
            <select id="language-switcher" aria-label="Pindah bahasa halaman">
                <option value="<?= htmlspecialchars($initialSlug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">ID</option>
            </select>
        </div>
        <button id="btn-add-translation" type="button" title="Buat salinan halaman dalam bahasa lain">+ Tambah Terjemahan</button>
        <button id="btn-site-backup" type="button"><i class="fa-solid fa-box-archive" aria-hidden="true"></i> Backup/Restore Site (ZIP)</button>
        <span id="status" role="status" aria-live="polite">Memuat editor…</span>
        <a id="preview-page" href="index.php?page=<?= rawurlencode($initialSlug) ?>" target="_blank" rel="noopener">Lihat</a>
        <button id="btn-manage-pages" type="button">Kelola Halaman</button>
        <button id="btn-new-page" type="button">+ Halaman Baru</button>
        <button id="btn-seo-manager" type="button"><i class="fa-solid fa-share-nodes" aria-hidden="true"></i> SEO &amp; Social</button>
        <button id="btn-revisions" type="button"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Riwayat Revisi</button>
        <a id="btn-admin-posts" href="admin-posts.php"><i class="fa-solid fa-newspaper" aria-hidden="true"></i> Artikel Blog</a>
        <button id="btn-save-template" type="button">Simpan sbg Template</button>
        <button id="save-page" type="button">Simpan Halaman</button>
        <span id="autosave-status" data-state="saved" role="status" aria-live="polite">Semua perubahan tersimpan</span>
        <button id="btn-export" type="button">Export JSON</button>
        <button id="btn-import" type="button">Import JSON</button>
        <button id="btn-templates" type="button">Template Library</button>
        <button id="btn-code-editor" type="button" aria-label="Code Editor" title="Code Editor"><i class="fa-solid fa-code" aria-hidden="true"></i> Code Editor</button>
        <button id="btn-preview" type="button" aria-label="Preview draft di tab baru" title="Preview draft di tab baru"><i class="fa-solid fa-eye" aria-hidden="true"></i> Preview</button>
        <button id="btn-shortcuts" type="button" aria-label="Tampilkan pintasan keyboard">⌨️ Shortcuts</button>
        <button id="btn-theme-toggle" type="button" aria-label="Ganti tema editor" aria-pressed="false" title="Ganti tema editor">☀️</button>
        <a id="btn-logout" href="logout.php">Logout</a>
        <input type="file" id="import-file-input" accept=".json" style="display:none;">
    </header>

    <main class="workspace">
        <aside id="left-sidebar" class="sidebar leftbar">
            <section class="panel-section">
                <h2 class="panel-heading">Blok</h2>
                <div class="blocks-container"></div>
            </section>
        </aside>
        <div id="gjs" aria-label="Kanvas editor halaman"></div>
        <aside id="right-sidebar" class="sidebar rightbar">
            <section class="panel-section">
                <h2 class="panel-heading">Pengaturan</h2>
                <div class="traits-container"></div>
            </section>
            <section class="panel-section">
                <h2 class="panel-heading">Gaya</h2>
                <div class="styles-container"></div>
            </section>
        </aside>
    </main>

    <aside id="navigator-panel" class="navigator-panel" aria-label="Navigator layer" aria-hidden="true">
        <header class="navigator-header">
            <h2>Navigator Layer</h2>
            <button id="btn-navigator-close" type="button" aria-label="Tutup Navigator">×</button>
        </header>
        <div id="layers-container" aria-label="Struktur komponen halaman"></div>
    </aside>

    <div id="template-modal" class="template-modal" role="dialog" aria-modal="true" aria-labelledby="template-modal-title" aria-hidden="true">
        <section class="template-dialog">
            <header class="template-modal-header">
                <h2 id="template-modal-title">Pilih Preset Template</h2>
                <button id="template-close" type="button" aria-label="Tutup">×</button>
            </header>
            <div id="template-grid" class="template-grid">
                <p class="template-message">Memuat preset…</p>
            </div>
        </section>
    </div>

    <div id="pages-modal" class="template-modal" role="dialog" aria-modal="true" aria-labelledby="pages-modal-title" aria-hidden="true">
        <section class="template-dialog pages-dialog">
            <header class="template-modal-header">
                <h2 id="pages-modal-title">Kelola / Tambah Halaman Baru</h2>
                <button id="pages-modal-close" type="button" aria-label="Tutup">×</button>
            </header>
            <form id="create-page-form" class="page-create-form">
                <label class="page-form-field" for="new-page-title">Judul Halaman
                    <input id="new-page-title" name="title" type="text" maxlength="255" required placeholder="Tentang Kami">
                </label>
                <label class="page-form-field" for="new-page-slug">Slug URL
                <input id="new-page-slug" name="slug" type="text" maxlength="100" required placeholder="about">
                </label>
                <button id="btn-create-page" type="submit">Buat Halaman</button>
            </form>
            <p id="pages-feedback" role="alert" aria-live="polite"></p>
            <div class="pages-list-wrap">
                <table class="pages-table">
                    <thead><tr><th>Judul</th><th>Slug</th><th>Diperbarui</th><th>Aksi</th></tr></thead>
                    <tbody id="pages-list"><tr><td colspan="4">Memuat daftar halaman…</td></tr></tbody>
                </table>
            </div>
        </section>
    </div>

    <div id="translation-modal" class="template-modal" role="dialog" aria-modal="true" aria-labelledby="translation-modal-title" aria-hidden="true">
        <section class="template-dialog translation-dialog">
            <header class="template-modal-header">
                <h2 id="translation-modal-title">Tambah Terjemahan Halaman</h2>
                <button id="translation-modal-close" type="button" aria-label="Tutup">×</button>
            </header>
            <div class="translation-form">
                <p>Salinan konten halaman ini akan dibuat sebagai draf awal. Terjemahkan teksnya setelah editor berpindah ke halaman baru.</p>
                <label for="target-language">Bahasa tujuan
                    <select id="target-language">
                        <option value="en">🇬🇧 English (EN)</option>
                        <option value="id">🇮🇩 Bahasa Indonesia (ID)</option>
                    </select>
                </label>
                <p id="translation-feedback" role="status" aria-live="polite"></p>
                <div class="translation-actions">
                    <button id="btn-cancel-translation" type="button">Batal</button>
                    <button id="btn-confirm-translation" type="button">Buat Terjemahan</button>
                </div>
            </div>
        </section>
    </div>

    <div id="site-backup-modal" class="template-modal" role="dialog" aria-modal="true" aria-labelledby="site-backup-title" aria-hidden="true">
        <section class="template-dialog backup-dialog">
            <header class="template-modal-header">
                <h2 id="site-backup-title">Site Backup &amp; Migration Tool</h2>
                <button id="site-backup-close" type="button" aria-label="Tutup">×</button>
            </header>
            <div class="backup-content">
                <section class="backup-section">
                    <h3>Export seluruh website</h3>
                    <p>Mengunduh data halaman, template, artikel, pesan, akun admin, dan media dalam satu arsip ZIP.</p>
                    <button id="btn-do-export-zip" class="backup-export-button" type="button">Download Full Site Backup (.ZIP)</button>
                </section>
                <section class="backup-section">
                    <h3>Restore paket website</h3>
                    <p>Restore mengganti data website saat ini dengan data dari backup dan menyalin file media di dalamnya.</p>
                    <input type="file" accept=".zip,application/zip" id="import-zip-file" aria-label="Pilih file backup ZIP">
                    <button id="btn-do-import-zip" class="backup-import-button" type="button">Restore Full Site</button>
                </section>
                <p id="backup-status" role="status" aria-live="polite"></p>
            </div>
        </section>
    </div>

    <div id="seo-modal" class="template-modal" role="dialog" aria-modal="true" aria-labelledby="seo-modal-title" aria-hidden="true">
        <section class="template-dialog seo-dialog">
            <header class="template-modal-header">
                <div class="seo-header-title">
                    <h2 id="seo-modal-title">SEO &amp; Social Media Settings</h2>
                    <small id="seo-page-label">Pengaturan SEO &amp; Meta Tags</small>
                </div>
                <button id="seo-modal-close" type="button" aria-label="Tutup">×</button>
            </header>
            <div class="seo-layout">
                <div class="seo-fields">
                    <label class="seo-field" for="seo-title">Meta Title
                        <input id="seo-title" type="text" maxlength="60" placeholder="Judul Halaman di Google (max 60 karakter)">
                        <small><span id="seo-title-count">0</span>/60 karakter</small>
                    </label>
                    <label class="seo-field" for="seo-description">Meta Description
                        <textarea id="seo-description" rows="3" maxlength="160" placeholder="Deskripsi singkat halaman di mesin pencari (max 160 karakter)"></textarea>
                        <small><span id="seo-description-count">0</span>/160 karakter</small>
                    </label>
                    <label class="seo-field" for="seo-keywords">Meta Keywords
                        <input id="seo-keywords" type="text" maxlength="2000" placeholder="kata kunci 1, kata kunci 2, kata kunci 3">
                    </label>
                    <label class="seo-field" for="seo-og-image">OpenGraph / Social Share Image URL
                        <input id="seo-og-image" type="url" maxlength="500" placeholder="https://domain.com/uploads/banner.jpg">
                        <small>Gunakan URL gambar publik yang dapat diakses pengunjung.</small>
                    </label>
                </div>
                <aside class="seo-previews" aria-label="Pratinjau metadata">
                    <h3>Pratinjau Google Search</h3>
                    <div class="seo-google-preview">
                        <div id="seo-google-url" class="seo-google-url"></div>
                        <span id="seo-google-title" class="seo-google-title"></span>
                        <div id="seo-google-description" class="seo-google-description"></div>
                    </div>
                    <h3>Pratinjau WhatsApp / Social Share</h3>
                    <div class="seo-social-preview">
                        <div id="seo-social-image" class="seo-social-image">Pratinjau gambar sosial</div>
                        <div class="seo-social-copy">
                            <div id="seo-social-domain" class="seo-social-domain"></div>
                            <div id="seo-social-title" class="seo-social-title"></div>
                            <div id="seo-social-description" class="seo-social-description"></div>
                        </div>
                    </div>
                </aside>
            </div>
            <footer class="seo-footer">
                <button id="btn-cancel-seo" type="button">Batal</button>
                <button id="btn-save-seo" type="button">Terapkan SEO</button>
            </footer>
        </section>
    </div>

    <div id="revisions-modal" class="template-modal" role="dialog" aria-modal="true" aria-labelledby="revisions-modal-title" aria-hidden="true">
        <section class="template-dialog revisions-dialog">
            <header class="template-modal-header">
                <h2 id="revisions-modal-title">Riwayat Revisi</h2>
                <button id="revisions-modal-close" type="button" aria-label="Tutup">×</button>
            </header>
            <div id="revision-list" class="revision-list" aria-live="polite">
                <p class="revisions-empty">Memuat riwayat…</p>
            </div>
            <footer class="revision-footer">
                <button id="btn-close-revisions" type="button">Tutup</button>
            </footer>
        </section>
    </div>

    <div id="save-template-modal" class="template-modal" role="dialog" aria-modal="true" aria-labelledby="save-template-title" aria-hidden="true">
        <section class="template-dialog template-save-dialog">
            <header class="template-modal-header">
                <h2 id="save-template-title">Simpan Desain sebagai Template</h2>
                <button id="close-save-template" type="button" aria-label="Tutup">×</button>
            </header>
            <form id="save-template-form" class="template-save-form">
                <label for="custom-template-name">Nama Template <span aria-hidden="true">*</span></label>
                <input id="custom-template-name" name="name" type="text" maxlength="255" required autocomplete="off" placeholder="Contoh: Halaman Program Unggulan">
                <label for="custom-template-description">Deskripsi Singkat</label>
                <textarea id="custom-template-description" name="description" maxlength="5000" placeholder="Jelaskan isi atau kegunaan template ini"></textarea>
                <p id="template-save-error" role="alert" aria-live="polite"></p>
                <div class="template-save-actions">
                    <button id="btn-cancel-template" type="button">Batal</button>
                    <button id="btn-submit-template" type="submit">Simpan Template</button>
                </div>
            </form>
        </section>
    </div>

    <div id="code-editor-modal" class="code-editor-modal" role="dialog" aria-modal="true" aria-labelledby="code-editor-title" aria-hidden="true">
        <section class="code-editor-dialog">
            <header class="code-editor-header">
                <h2 id="code-editor-title">Custom Code Editor (HTML &amp; CSS)</h2>
                <button id="code-editor-close" type="button" aria-label="Tutup">×</button>
            </header>
            <div class="code-editor-body">
                <label class="code-editor-field" for="code-html-input">
                    HTML
                    <textarea id="code-html-input" spellcheck="false" aria-label="Kode HTML"></textarea>
                </label>
                <label class="code-editor-field" for="code-css-input">
                    CSS
                    <textarea id="code-css-input" spellcheck="false" aria-label="Kode CSS"></textarea>
                </label>
            </div>
            <footer class="code-editor-footer">
                <button id="btn-cancel-code" type="button">Batal</button>
                <button id="btn-apply-code" type="button">Terapkan Perubahan</button>
            </footer>
        </section>
    </div>

    <div id="shortcuts-modal" class="template-modal" role="dialog" aria-modal="true" aria-labelledby="shortcuts-title" aria-hidden="true">
        <section class="template-dialog shortcuts-dialog">
            <header class="template-modal-header">
                <h2 id="shortcuts-title">Keyboard Shortcuts</h2>
                <button id="shortcuts-close" type="button" aria-label="Tutup">×</button>
            </header>
            <ul class="shortcuts-list">
                <li><span>Simpan halaman</span><span class="shortcut-keys"><kbd>Ctrl</kbd>/<kbd>⌘</kbd> + <kbd>S</kbd></span></li>
                <li><span>Undo</span><span class="shortcut-keys"><kbd>Ctrl</kbd>/<kbd>⌘</kbd> + <kbd>Z</kbd></span></li>
                <li><span>Redo</span><span class="shortcut-keys"><kbd>Ctrl</kbd>/<kbd>⌘</kbd> + <kbd>Y</kbd> atau <kbd>Ctrl</kbd>/<kbd>⌘</kbd> + <kbd>Shift</kbd> + <kbd>Z</kbd></span></li>
                <li><span>Preview draft di tab baru</span><span class="shortcut-keys"><kbd>Ctrl</kbd>/<kbd>⌘</kbd> + <kbd>P</kbd></span></li>
                <li><span>Buka/tutup Code Editor</span><span class="shortcut-keys"><kbd>Ctrl</kbd>/<kbd>⌘</kbd> + <kbd>E</kbd></span></li>
                <li><span>Buka/tutup Navigator</span><span class="shortcut-keys"><kbd>Ctrl</kbd>/<kbd>⌘</kbd> + <kbd>Shift</kbd> + <kbd>N</kbd></span></li>
                <li><span>Tutup modal yang terbuka</span><span class="shortcut-keys"><kbd>Esc</kbd></span></li>
            </ul>
        </section>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/grapesjs/0.23.6/grapes.min.js"></script>
    <script>
    (() => {
        const status = document.getElementById('status');
        const workspace = document.querySelector('.workspace');
        const toggleLeftPanelButton = document.getElementById('toggle-blocks-btn');
        const toggleRightPanelButton = document.getElementById('toggle-styles-btn');
        const slugInput = document.getElementById('slug');
        const saveButton = document.getElementById('save-page');
        const exportButton = document.getElementById('btn-export');
        const importButton = document.getElementById('btn-import');
        const importFileInput = document.getElementById('import-file-input');
        const templatesButton = document.getElementById('btn-templates');
        const templateModal = document.getElementById('template-modal');
        const templateGrid = document.getElementById('template-grid');
        const templateCloseButton = document.getElementById('template-close');
        const saveTemplateButton = document.getElementById('btn-save-template');
        const saveTemplateModal = document.getElementById('save-template-modal');
        const saveTemplateForm = document.getElementById('save-template-form');
        const customTemplateName = document.getElementById('custom-template-name');
        const customTemplateDescription = document.getElementById('custom-template-description');
        const saveTemplateSubmit = document.getElementById('btn-submit-template');
        const saveTemplateError = document.getElementById('template-save-error');
        const codeEditorButton = document.getElementById('btn-code-editor');
        const codeEditorModal = document.getElementById('code-editor-modal');
        const codeEditorCloseButton = document.getElementById('code-editor-close');
        const codeHtmlInput = document.getElementById('code-html-input');
        const codeCssInput = document.getElementById('code-css-input');
        const applyCodeButton = document.getElementById('btn-apply-code');
        const cancelCodeButton = document.getElementById('btn-cancel-code');
        const previewButton = document.getElementById('btn-preview');
        const shortcutsButton = document.getElementById('btn-shortcuts');
        const shortcutsModal = document.getElementById('shortcuts-modal');
        const shortcutsCloseButton = document.getElementById('shortcuts-close');
        const themeToggleButton = document.getElementById('btn-theme-toggle');
        const navigatorButton = document.getElementById('btn-navigator');
        const navigatorPanel = document.getElementById('navigator-panel');
        const navigatorCloseButton = document.getElementById('btn-navigator-close');
        const layersContainer = document.getElementById('layers-container');
        const autoSaveStatus = document.getElementById('autosave-status');
        const pagesModal = document.getElementById('pages-modal');
        const translationModal = document.getElementById('translation-modal');
        const translationCloseButton = document.getElementById('translation-modal-close');
        const translationCancelButton = document.getElementById('btn-cancel-translation');
        const translationConfirmButton = document.getElementById('btn-confirm-translation');
        const translationButton = document.getElementById('btn-add-translation');
        const targetLanguageSelect = document.getElementById('target-language');
        const translationFeedback = document.getElementById('translation-feedback');
        const languageSwitcher = document.getElementById('language-switcher');
        const siteBackupButton = document.getElementById('btn-site-backup');
        const siteBackupModal = document.getElementById('site-backup-modal');
        const siteBackupCloseButton = document.getElementById('site-backup-close');
        const exportZipButton = document.getElementById('btn-do-export-zip');
        const importZipButton = document.getElementById('btn-do-import-zip');
        const importZipFile = document.getElementById('import-zip-file');
        const backupStatus = document.getElementById('backup-status');
        const pagesModalClose = document.getElementById('pages-modal-close');
        const pagesList = document.getElementById('pages-list');
        const pagesFeedback = document.getElementById('pages-feedback');
        const createPageForm = document.getElementById('create-page-form');
        const newPageTitle = document.getElementById('new-page-title');
        const newPageSlug = document.getElementById('new-page-slug');
        const createPageButton = document.getElementById('btn-create-page');
        const managePagesButton = document.getElementById('btn-manage-pages');
        const newPageButton = document.getElementById('btn-new-page');
        const seoButton = document.getElementById('btn-seo-manager');
        const seoModal = document.getElementById('seo-modal');
        const seoCloseButton = document.getElementById('seo-modal-close');
        const seoCancelButton = document.getElementById('btn-cancel-seo');
        const seoApplyButton = document.getElementById('btn-save-seo');
        const seoModalTitle = document.getElementById('seo-modal-title');
        const seoTitleInput = document.getElementById('seo-title');
        const seoDescriptionInput = document.getElementById('seo-description');
        const seoKeywordsInput = document.getElementById('seo-keywords');
        const seoOgImageInput = document.getElementById('seo-og-image');
        const seoTitleCount = document.getElementById('seo-title-count');
        const seoDescriptionCount = document.getElementById('seo-description-count');
        const seoGoogleUrl = document.getElementById('seo-google-url');
        const seoGoogleTitle = document.getElementById('seo-google-title');
        const seoGoogleDescription = document.getElementById('seo-google-description');
        const seoSocialImage = document.getElementById('seo-social-image');
        const seoSocialDomain = document.getElementById('seo-social-domain');
        const seoSocialTitle = document.getElementById('seo-social-title');
        const seoSocialDescription = document.getElementById('seo-social-description');
        const revisionsButton = document.getElementById('btn-revisions');
        const revisionsModal = document.getElementById('revisions-modal');
        const revisionsModalTitle = document.getElementById('revisions-modal-title');
        const revisionsCloseButton = document.getElementById('revisions-modal-close');
        const revisionsFooterButton = document.getElementById('btn-close-revisions');
        const revisionList = document.getElementById('revision-list');
        let templateLibrary = [];
        let isDirty = false;
        let autoSaveInterval = null;
        let isLoadingPage = true;
        let changeVersion = 0;
        let saveInProgress = false;
        let currentPageTitle = 'Halaman';
        let activeLang = 'id';
        let activeTranslationGroup = '';
        let pageRecords = [];
        let seoMetadata = { meta_title: '', meta_description: '', meta_keywords: '', og_image: '' };

        /** Apply a theme to editor chrome and keep the choice between visits. */
        function setTheme(themeName) {
            const theme = themeName === 'dark' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', theme);
            try {
                localStorage.setItem('editor_theme', theme);
            } catch (_) {
                // The current session still changes theme if browser storage is unavailable.
            }
            themeToggleButton.textContent = theme === 'dark' ? '🌙' : '☀️';
            themeToggleButton.setAttribute('aria-pressed', String(theme === 'dark'));
            themeToggleButton.setAttribute('aria-label', `Mode ${theme === 'dark' ? 'gelap' : 'terang'} aktif. Klik untuk mengganti tema.`);
            themeToggleButton.title = `Mode ${theme === 'dark' ? 'gelap' : 'terang'} aktif`;
        }

        let initialTheme = 'dark';
        try {
            const savedTheme = localStorage.getItem('editor_theme');
            if (savedTheme === 'dark' || savedTheme === 'light') {
                initialTheme = savedTheme;
            } else if (typeof window.matchMedia === 'function') {
                initialTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
        } catch (_) {
            initialTheme = 'dark';
        }
        setTheme(initialTheme);
        themeToggleButton.addEventListener('click', () => {
            setTheme(document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark');
        });
        const previewLink = document.getElementById('preview-page');
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const params = new URLSearchParams(window.location.search);
        const panelViewport = window.matchMedia('(max-width: 900px)');
        const panelPreferenceKey = side => 'editor_' + side + '_panel_collapsed_' + (panelViewport.matches ? 'compact' : 'wide');
        function syncCanvasFullscreen() {
            const bothCollapsed = workspace.classList.contains('left-collapsed')
                && workspace.classList.contains('right-collapsed');
            workspace.classList.toggle('canvas-fullscreen', bothCollapsed);
        }
        function setPanelCollapsed(side, collapsed, persist = false) {
            const className = side + '-collapsed';
            const button = side === 'left' ? toggleLeftPanelButton : toggleRightPanelButton;
            workspace.classList.toggle(className, collapsed);
            button.setAttribute('aria-expanded', String(!collapsed));
            syncCanvasFullscreen();
            if (persist) {
                try {
                    localStorage.setItem(panelPreferenceKey(side), String(collapsed));
                } catch (_) {}
            }
        }
        function applyDefaultPanelLayout() {
            let leftPreference = null;
            let rightPreference = null;
            try {
                leftPreference = localStorage.getItem(panelPreferenceKey('left'));
                rightPreference = localStorage.getItem(panelPreferenceKey('right'));
            } catch (_) {}
            setPanelCollapsed('left', leftPreference === null ? panelViewport.matches : leftPreference === 'true');
            setPanelCollapsed('right', rightPreference === null ? panelViewport.matches : rightPreference === 'true');
        }
        toggleLeftPanelButton.addEventListener('click', () => {
            setPanelCollapsed('left', toggleLeftPanelButton.getAttribute('aria-expanded') === 'true', true);
        });
        toggleRightPanelButton.addEventListener('click', () => {
            setPanelCollapsed('right', toggleRightPanelButton.getAttribute('aria-expanded') === 'true', true);
        });
        applyDefaultPanelLayout();
        if (typeof panelViewport.addEventListener === 'function') {
            panelViewport.addEventListener('change', applyDefaultPanelLayout);
        }
        const validSlug = value => /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(value) && value.length <= 100;
        const requestedSlug = params.get('page') || params.get('slug');
        if (requestedSlug && validSlug(requestedSlug)) slugInput.value = requestedSlug;
        let activeSlug = slugInput.value;

        const setStatus = (message, isError = false) => {
            status.textContent = message;
            status.style.color = isError ? '#fecaca' : '#d9e2ec';
        };
        const updateStatusUI = (message, state = 'saved') => {
            autoSaveStatus.textContent = message;
            autoSaveStatus.dataset.state = state;
        };
        const updatePreview = () => {
            previewLink.href = `index.php?page=${encodeURIComponent(activeSlug)}&lang=${encodeURIComponent(activeLang)}`;
        };
        slugInput.addEventListener('change', () => switchToPage(slugInput.value));

        function openSiteBackupModal() {
            backupStatus.textContent = '';
            siteBackupModal.style.display = 'flex';
            siteBackupModal.setAttribute('aria-hidden', 'false');
        }
        function closeSiteBackupModal() {
            siteBackupModal.style.display = 'none';
            siteBackupModal.setAttribute('aria-hidden', 'true');
        }
        siteBackupButton.addEventListener('click', openSiteBackupModal);
        siteBackupCloseButton.addEventListener('click', closeSiteBackupModal);
        siteBackupModal.addEventListener('click', event => {
            if (event.target === siteBackupModal) closeSiteBackupModal();
        });
        exportZipButton.addEventListener('click', () => {
            window.location.href = 'api.php?action=export_full_site';
        });
        importZipButton.addEventListener('click', async () => {
            const file = importZipFile.files && importZipFile.files[0];
            if (!file) {
                backupStatus.textContent = 'Pilih file backup .ZIP terlebih dahulu.';
                return;
            }
            if (!confirm('Peringatan: Restore akan memperbarui data website saat ini. Lanjutkan?')) return;
            importZipButton.disabled = true;
            backupStatus.textContent = 'Menginstal paket website…';
            try {
                const formData = new FormData();
                formData.append('backup_zip', file);
                const response = await fetch('api.php?action=import_full_site', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
                    body: formData
                });
                const result = await response.json();
                if (!response.ok || result.status !== 'success') {
                    throw new Error(result.message || 'Restore paket website gagal.');
                }
                backupStatus.textContent = result.message || 'Seluruh paket website berhasil di-restore!';
                window.setTimeout(() => {
                    window.location.href = 'editor.php?page=home';
                }, 700);
            } catch (error) {
                backupStatus.textContent = error.message || 'Restore paket website gagal.';
            } finally {
                importZipButton.disabled = false;
            }
        });

        if (typeof grapesjs === 'undefined') {
            setStatus('GrapesJS gagal dimuat dari CDN.', true);
            saveButton.disabled = true;
            return;
        }

        const googleFontsCssUrl = 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Lato:wght@300;400;700&family=Merriweather:wght@300;400;700&family=Montserrat:wght@300;400;600;700&family=Nunito:wght@300;400;600;700&family=Open+Sans:wght@300;400;600;700&family=Oswald:wght@400;600&family=Playfair+Display:wght@400;700&family=Poppins:wght@300;400;600;700&family=Raleway:wght@300;400;600;700&family=Roboto:wght@300;400;500;700&display=swap';
        const fontOptions = [
            { value: 'Arial, Helvetica, sans-serif', name: 'Arial' },
            { value: '"Poppins", sans-serif', name: 'Poppins' },
            { value: '"Roboto", sans-serif', name: 'Roboto' },
            { value: '"Montserrat", sans-serif', name: 'Montserrat' },
            { value: '"Inter", sans-serif', name: 'Inter' },
            { value: '"Open Sans", sans-serif', name: 'Open Sans' },
            { value: '"Lato", sans-serif', name: 'Lato' },
            { value: '"Oswald", sans-serif', name: 'Oswald' },
            { value: '"Raleway", sans-serif', name: 'Raleway' },
            { value: '"Playfair Display", serif', name: 'Playfair Display' },
            { value: '"Merriweather", serif', name: 'Merriweather' },
            { value: '"Nunito", sans-serif', name: 'Nunito' }
        ];

        const editor = grapesjs.init({
            container: '#gjs',
            height: '100%',
            width: 'auto',
            fromElement: false,
            storageManager: false,
            components: '',
            style: '',
            canvas: {
                styles: [
                    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
                    googleFontsCssUrl
                ]
            },
            deviceManager: {
                devices: [
                    { name: 'Desktop' },
                    { name: 'Tablet', width: '768px', widthMedia: '768px' },
                    { name: 'Mobile', width: '375px', widthMedia: '375px' },
                    { name: 'Mobile 320', width: '320px', widthMedia: '320px' }
                ]
            },
            assetManager: {
                assets: [],
                upload: 'api.php?action=upload_asset',
                uploadName: 'files',
                autoAdd: true,
                multiUpload: true,
                openAssetsOnDrop: 1,
                embedAsBase64: 0,
                dropzone: 1,
                credentials: 'include',
                headers: { 'X-CSRF-Token': csrfToken }
            },
            blockManager: { appendTo: '.blocks-container' },
            layerManager: {
                appendTo: '#layers-container',
                showWrapper: true,
                sortable: true,
                hydratable: true
            },
            styleManager: {
                appendTo: '.styles-container',
                sectors: [
                    {
                        name: 'Typography',
                        open: true,
                        properties: [
                            {
                                type: 'select', label: 'Font Family', property: 'font-family', default: 'Arial, Helvetica, sans-serif',
                                options: fontOptions.map(font => ({ id: font.value, label: font.name }))
                            },
                            { type: 'number', label: 'Font Size', property: 'font-size', units: ['px', 'rem', 'em'], min: 0 },
                            {
                                type: 'select', label: 'Font Weight', property: 'font-weight', default: '400',
                                options: [
                                    { id: '300', label: '300 — Light' },
                                    { id: '400', label: '400 — Regular' },
                                    { id: '500', label: '500 — Medium' },
                                    { id: '600', label: '600 — Semibold' },
                                    { id: '700', label: '700 — Bold' }
                                ]
                            },
                            { type: 'number', label: 'Line Height', property: 'line-height', units: ['', 'px', 'em'], min: 0 },
                            { type: 'color', label: 'Text Color', property: 'color' }
                        ]
                    },
                    {
                        name: 'Background',
                        open: false,
                        properties: [
                            { type: 'color', label: 'Background Color', property: 'background-color' },
                            { type: 'base', label: 'Background Image URL', property: 'background-image', default: 'none' },
                            {
                                type: 'select', label: 'Background Size', property: 'background-size', default: 'cover',
                                options: [
                                    { id: 'cover', label: 'Cover' },
                                    { id: 'contain', label: 'Contain' },
                                    { id: 'auto', label: 'Auto' }
                                ]
                            },
                            {
                                type: 'select', label: 'Background Position', property: 'background-position', default: 'center',
                                options: [
                                    { id: 'center', label: 'Center' },
                                    { id: 'top', label: 'Top' },
                                    { id: 'bottom', label: 'Bottom' },
                                    { id: 'left', label: 'Left' },
                                    { id: 'right', label: 'Right' }
                                ]
                            }
                        ]
                    },
                    {
                        name: 'Spacing',
                        open: false,
                        properties: [
                            {
                                type: 'composite', label: 'Margin', property: 'margin',
                                properties: [
                                    { type: 'number', property: 'margin-top', units: ['px', '%', 'em', 'rem'], default: '0' },
                                    { type: 'number', property: 'margin-right', units: ['px', '%', 'em', 'rem'], default: '0' },
                                    { type: 'number', property: 'margin-bottom', units: ['px', '%', 'em', 'rem'], default: '0' },
                                    { type: 'number', property: 'margin-left', units: ['px', '%', 'em', 'rem'], default: '0' }
                                ]
                            },
                            {
                                type: 'composite', label: 'Padding', property: 'padding',
                                properties: [
                                    { type: 'number', property: 'padding-top', units: ['px', '%', 'em', 'rem'], default: '0' },
                                    { type: 'number', property: 'padding-right', units: ['px', '%', 'em', 'rem'], default: '0' },
                                    { type: 'number', property: 'padding-bottom', units: ['px', '%', 'em', 'rem'], default: '0' },
                                    { type: 'number', property: 'padding-left', units: ['px', '%', 'em', 'rem'], default: '0' }
                                ]
                            }
                        ]
                    },
                    {
                        name: 'Borders & Shadow',
                        open: false,
                        properties: [
                            { type: 'number', label: 'Border Radius', property: 'border-radius', units: ['px', '%'], min: 0 },
                            { type: 'number', label: 'Border Width', property: 'border-width', units: ['px'], min: 0 },
                            { type: 'color', label: 'Border Color', property: 'border-color' },
                            'box-shadow'
                        ]
                    }
                ]
            },
            panels: { defaults: [] },
            selectorManager: { componentFirst: true }
        });

        /** Toggle the slide-out layer tree and keep its accessibility state in sync. */
        function setNavigatorOpen(open) {
            navigatorPanel.classList.toggle('is-open', open);
            navigatorPanel.setAttribute('aria-hidden', String(!open));
            navigatorButton.setAttribute('aria-expanded', String(open));
            if (open) {
                window.requestAnimationFrame(() => {
                    const selectedLayer = layersContainer.querySelector('.gjs-layer.gjs-selected');
                    if (selectedLayer) selectedLayer.scrollIntoView({ block: 'nearest' });
                });
            }
        }
        navigatorButton.addEventListener('click', () => {
            setNavigatorOpen(!navigatorPanel.classList.contains('is-open'));
        });
        navigatorCloseButton.addEventListener('click', () => setNavigatorOpen(false));

        // GrapesJS Layer Manager selects components on its own; this resolves the clicked layer model as a fallback.
        layersContainer.addEventListener('click', event => {
            const layerElement = event.target.closest('.gjs-layer');
            if (!layerElement || event.target.closest('.gjs-layer-caret, .gjs-layer-vis, button')) return;
            const modelCid = layerElement.getAttribute('data-model-cid');
            if (!modelCid) return;
            const wrapper = editor.getWrapper();
            const candidates = [wrapper, ...wrapper.find('*')];
            const component = candidates.find(item => String(item.cid) === modelCid);
            if (component) editor.select(component);
        });

        // Replace GrapesJS's built-in history bindings so each key press performs one action.
        ['core:undo', 'core:redo'].forEach(keymapId => {
            if (editor.Keymaps.get(keymapId)) editor.Keymaps.remove(keymapId);
        });

        const um = editor.UndoManager;
        const undoButton = document.getElementById('btn-undo');
        const redoButton = document.getElementById('btn-redo');
        editor.on('component:add component:remove component:update styleUpdate', () => {
            if (isLoadingPage) return;
            isDirty = true;
            changeVersion += 1;
            updateStatusUI('Ada perubahan belum disimpan', 'dirty');
        });
        function updateUndoRedoStatus() {
            const canUndo = um.hasUndo();
            const canRedo = um.hasRedo();
            undoButton.disabled = !canUndo;
            redoButton.disabled = !canRedo;
        }
        undoButton.addEventListener('click', () => um.undo());
        redoButton.addEventListener('click', () => um.redo());
        editor.on('undo redo component:add component:remove component:update styleUpdate', updateUndoRedoStatus);
        updateUndoRedoStatus();

        function openCodeEditor() {
            codeHtmlInput.value = editor.getHtml();
            codeCssInput.value = editor.getCss();
            codeEditorModal.style.display = 'flex';
            codeEditorModal.setAttribute('aria-hidden', 'false');
            codeHtmlInput.focus();
        }
        function closeCodeEditor() {
            codeEditorModal.style.display = 'none';
            codeEditorModal.setAttribute('aria-hidden', 'true');
        }
        function openShortcutsModal() {
            shortcutsModal.style.display = 'flex';
            shortcutsModal.setAttribute('aria-hidden', 'false');
            shortcutsCloseButton.focus();
        }
        function closeShortcutsModal() {
            shortcutsModal.style.display = 'none';
            shortcutsModal.setAttribute('aria-hidden', 'true');
        }
        function previewPage() {
            try {
                localStorage.setItem('grapesjs_preview_html', editor.getHtml());
                localStorage.setItem('grapesjs_preview_css', editor.getCss());
                const previewWindow = window.open(
                    `index.php?preview=true&page=${encodeURIComponent(activeSlug)}&lang=${encodeURIComponent(activeLang)}`,
                    '_blank'
                );
                if (!previewWindow) setStatus('Browser memblokir tab preview. Izinkan pop-up lalu coba lagi.', true);
            } catch (error) {
                setStatus(error.message || 'Draft preview tidak dapat disimpan di browser.', true);
            }
        }
        previewButton.addEventListener('click', previewPage);
        codeEditorButton.addEventListener('click', openCodeEditor);
        codeEditorCloseButton.addEventListener('click', closeCodeEditor);
        cancelCodeButton.addEventListener('click', closeCodeEditor);
        shortcutsButton.addEventListener('click', openShortcutsModal);
        shortcutsCloseButton.addEventListener('click', closeShortcutsModal);
        shortcutsModal.addEventListener('click', event => {
            if (event.target === shortcutsModal) closeShortcutsModal();
        });
        codeEditorModal.addEventListener('click', event => {
            if (event.target === codeEditorModal) closeCodeEditor();
        });
        applyCodeButton.addEventListener('click', () => {
            editor.setComponents(codeHtmlInput.value);
            editor.setStyle(codeCssInput.value);
            closeCodeEditor();
            setStatus('Kode berhasil diterapkan ke canvas!');
        });

        const deviceButtons = [...document.querySelectorAll('.device-button')];
        const syncDeviceButtons = () => {
            const activeDevice = editor.getDevice();
            deviceButtons.forEach(button => {
                button.setAttribute('aria-pressed', String(button.dataset.device === activeDevice));
            });
        };
        deviceButtons.forEach(button => {
            button.addEventListener('click', () => editor.setDevice(button.dataset.device));
        });
        editor.on('device:select', syncDeviceButtons);
        syncDeviceButtons();

        const iconOptions = [
            { id: 'fa-star', label: 'Bintang' },
            { id: 'fa-heart', label: 'Hati' },
            { id: 'fa-user', label: 'Pengguna' },
            { id: 'fa-check', label: 'Centang' },
            { id: 'fa-house', label: 'Beranda' },
            { id: 'fa-envelope', label: 'Email' },
            { id: 'fa-gear', label: 'Pengaturan' },
            { id: 'fa-thumbs-up', label: 'Suka' },
            { id: 'fa-bell', label: 'Notifikasi' },
            { id: 'fa-download', label: 'Unduh' },
            { id: 'fa-cart-shopping', label: 'Keranjang' },
            { id: 'fa-share', label: 'Bagikan' }
        ];

        // Treat the visible <i> as the icon component so its selector appears when the icon is clicked.
        editor.Components.addType('fa-icon', {
            isParsedNode: node => node.tagName === 'i'
                && (node.getAttribute('class') || '').split(/\s+/).includes('fa-solid')
                ? { type: 'fa-icon' }
                : false,
            model: {
                defaults: {
                    iconName: 'fa-star',
                    traits: [{
                        type: 'select',
                        name: 'iconName',
                        label: 'Pilih Ikon',
                        changeProp: true,
                        options: iconOptions
                    }]
                },
                init() {
                    this.on('change:iconName', this.updateIconClass, this);
                    this.updateIconClass();
                },
                updateIconClass() {
                    const managedClasses = this.getClasses().filter(className =>
                        className === 'fa-solid' || iconOptions.some(option => option.id === className)
                    );
                    if (managedClasses.length) this.removeClass(managedClasses);
                    this.addClass('fa-solid');
                    this.addClass(this.get('iconName') || 'fa-star');
                }
            }
        });

        const blocks = editor.BlockManager;
        blocks.add('section-container', {
            label: 'Section Container',
            category: 'Dasar',
            content: {
                tagName: 'section',
                attributes: { class: 'section-container' },
                components: [{
                    tagName: 'div',
                    attributes: { class: 'container' },
                    components: [
                        { tagName: 'h2', type: 'text', content: 'Judul bagian' },
                        { tagName: 'p', type: 'text', content: 'Tambahkan deskripsi bagian di sini.' }
                    ]
                }]
            }
        });
        blocks.add('heading', {
            label: 'Heading',
            category: 'Dasar',
            content: { tagName: 'h2', type: 'text', content: 'Judul baru' }
        });
        blocks.add('paragraph', {
            label: 'Text Paragraph',
            category: 'Dasar',
            content: { tagName: 'p', type: 'text', content: 'Tulis paragraf di sini.' }
        });
        blocks.add('button', {
            label: 'Button',
            category: 'Dasar',
            content: { tagName: 'a', attributes: { class: 'button', href: '#' }, content: 'Tombol' }
        });
        blocks.add('image', {
            label: 'Image',
            category: 'Dasar',
            content: { type: 'image', attributes: { alt: 'Gambar halaman' }, style: { width: '100%', height: 'auto' } }
        });
        blocks.add('contact-form-block', {
            label: 'Form Kontak / Feedback',
            category: 'Formulir',
            content: `<form action="api.php?action=submit_form" method="POST" class="custom-form" style="padding: 20px; background: #ffffff; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
  <input type="hidden" name="page_slug" value="home">
  <div style="position:absolute;left:-10000px;" aria-hidden="true"><label>Biarkan kosong<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
  <div style="margin-bottom: 15px;">
    <label style="display:block; margin-bottom: 5px; font-weight: bold;">Nama Lengkap</label>
    <input type="text" name="name" required placeholder="Nama Anda" maxlength="150" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
  </div>
  <div style="margin-bottom: 15px;">
    <label style="display:block; margin-bottom: 5px; font-weight: bold;">Alamat Email</label>
    <input type="email" name="email" required placeholder="email@domain.com" maxlength="254" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
  </div>
  <div style="margin-bottom: 15px;">
    <label style="display:block; margin-bottom: 5px; font-weight: bold;">Pesan</label>
    <textarea name="message" rows="4" required placeholder="Tuliskan pesan Anda..." maxlength="5000" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;"></textarea>
  </div>
  <button type="submit" style="background: #3b82f6; color: white; border: none; padding: 12px 24px; border-radius: 4px; cursor: pointer; font-weight: bold; width: 100%;">Kirim Pesan</button>
</form>`
        });
        blocks.add('fontawesome-icon', {
            label: 'Ikon FontAwesome',
            category: 'Widget',
            content: '<div class="icon-wrapper" style="padding: 10px; text-align: center;"><i class="fa-solid fa-star" style="font-size: 48px; color: #3b82f6;"></i></div>'
        });
        blocks.add('card-component', {
            label: 'Card Component',
            category: 'Widget',
            content: `
                <article class="mq-card" style="overflow:hidden;background-color:#ffffff;border:1px solid #e2e8f0;border-radius:14px;box-shadow:0 8px 24px rgba(15,23,42,.10);">
                    <img src="https://placehold.co/640x360/png?text=Gambar+Card" alt="Gambar card" style="display:block;width:100%;height:auto;">
                    <div style="padding:20px;">
                        <h3 style="margin:0 0 10px;font-size:22px;">Judul Card</h3>
                        <p style="margin:0 0 18px;line-height:1.6;color:#475569;">Tambahkan deskripsi singkat untuk menjelaskan informasi pada card ini.</p>
                        <a href="#" style="display:inline-block;padding:10px 16px;border-radius:7px;background:#0f9f78;color:#ffffff;text-decoration:none;">Selengkapnya</a>
                    </div>
                </article>
            `
        });
        blocks.add('accordion-faq', {
            label: 'Accordion / FAQ',
            category: 'Widget',
            content: `
                <section class="mq-faq" style="display:grid;gap:10px;">
                    <details style="padding:14px 16px;border:1px solid #d9e2ec;border-radius:9px;background:#ffffff;">
                        <summary style="cursor:pointer;font-weight:700;">Bagaimana cara mendaftar?</summary>
                        <p style="margin:12px 0 0;line-height:1.6;">Isi jawaban pertanyaan ini dengan informasi yang dibutuhkan pengunjung.</p>
                    </details>
                    <details style="padding:14px 16px;border:1px solid #d9e2ec;border-radius:9px;background:#ffffff;">
                        <summary style="cursor:pointer;font-weight:700;">Kapan pendaftaran dibuka?</summary>
                        <p style="margin:12px 0 0;line-height:1.6;">Tambahkan jadwal atau informasi pendaftaran di sini.</p>
                    </details>
                </section>
            `
        });
        blocks.add('two-column-grid', {
            label: 'Grid 2 Kolom',
            category: 'Widget',
            content: `
                <div class="mq-grid-two" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,250px),1fr));gap:24px;width:100%;">
                    <section style="min-width:0;padding:20px;border-radius:10px;background:#f1f5f9;">
                        <h3 style="margin-top:0;">Kolom pertama</h3>
                        <p style="margin-bottom:0;line-height:1.6;">Tambahkan teks, gambar, atau komponen lain di kolom ini.</p>
                    </section>
                    <section style="min-width:0;padding:20px;border-radius:10px;background:#f1f5f9;">
                        <h3 style="margin-top:0;">Kolom kedua</h3>
                        <p style="margin-bottom:0;line-height:1.6;">Grid akan otomatis menjadi satu kolom saat ruang layar sempit.</p>
                    </section>
                </div>
            `
        });
        blocks.add('video-player', {
            label: 'Video Player',
            category: 'Widget',
            content: `
                <div class="mq-video-shell" style="width:100%;max-width:900px;aspect-ratio:16/9;overflow:hidden;border-radius:10px;background:#0f172a;">
                    <video controls playsinline preload="metadata" style="display:block;width:100%;height:100%;object-fit:contain;">
                        <source src="https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4" type="video/mp4">
                        Browser Anda belum mendukung pemutar video HTML5.
                    </video>
                </div>
            `
        });
        blocks.add('blog-grid-block', {
            label: 'Grid Blog / Artikel',
            category: 'Blog',
            content: `<div class="blog-grid-container" data-posts-limit="6" style="padding: 20px 0;">
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;" class="blog-posts-wrapper">
    <div style="border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; background: #fff;">
      <div style="height: 160px; background: #cbd5e1;"></div>
      <div style="padding: 15px;">
        <span style="font-size: 12px; color: #3b82f6; font-weight: bold;">KATEGORI</span>
        <h4 style="margin: 8px 0; font-size: 18px;">Judul Artikel Sampel</h4>
        <p style="font-size: 14px; color: #64748b; margin-bottom: 12px;">Ringkasan singkat isi artikel blog yang diambil dari database...</p>
        <a href="#" style="color: #3b82f6; text-decoration: none; font-weight: bold; font-size: 14px;">Baca Selengkapnya →</a>
      </div>
    </div>
  </div>
</div>`
        });
        blocks.add('lang-switcher-block', {
            label: 'Language Switcher',
            category: 'Widget',
            content: `<div class="language-switcher" style="display: inline-flex; gap: 8px; padding: 5px 10px; background: rgba(0,0,0,0.05); border-radius: 4px;">
  <a href="?page=home&lang=id" data-lang="id" style="font-weight: bold; text-decoration: none; color: #333;">🇮🇩 ID</a>
  <span>|</span>
  <a href="?page=en-home&lang=en" data-lang="en" style="text-decoration: none; color: #666;">🇬🇧 EN</a>
</div>`
        });
        const blockIcons = {
            'section-container': 'fa-layer-group',
            heading: 'fa-heading',
            paragraph: 'fa-font',
            button: 'fa-hand-pointer',
            image: 'fa-image',
            'contact-form-block': 'fa-envelope',
            'fontawesome-icon': 'fa-icons',
            'card-component': 'fa-id-card',
            'accordion-faq': 'fa-circle-question',
            'two-column-grid': 'fa-table-columns',
            'video-player': 'fa-circle-play',
            'blog-grid-block': 'fa-newspaper',
            'lang-switcher-block': 'fa-language'
        };
        Object.entries(blockIcons).forEach(([blockId, iconName]) => {
            const block = blocks.get(blockId);
            if (block) block.set('media', `<i class="fa-solid ${iconName}" aria-hidden="true"></i>`);
        });

        /** Fill the SEO form from current page state after opening or loading a page. */
        function syncSeoInputs() {
            seoTitleInput.value = seoMetadata.meta_title || '';
            seoDescriptionInput.value = seoMetadata.meta_description || '';
            seoKeywordsInput.value = seoMetadata.meta_keywords || '';
            seoOgImageInput.value = seoMetadata.og_image || '';
            seoModalTitle.textContent = `SEO & Social Media Settings - ${currentPageTitle}`;
            updateSeoPreview();
        }

        /** Update Google and social card previews using text nodes and safe image URLs. */
        function updateSeoPreview() {
            const title = seoTitleInput.value.trim() || currentPageTitle || 'Judul halaman';
            const description = seoDescriptionInput.value.trim() || 'Deskripsi halaman akan tampil di sini.';
            seoTitleCount.textContent = String(seoTitleInput.value.length);
            seoDescriptionCount.textContent = String(seoDescriptionInput.value.length);
            seoGoogleUrl.textContent = `${window.location.host}/index.php?page=${activeSlug}`;
            seoGoogleTitle.textContent = title;
            seoGoogleDescription.textContent = description;
            seoSocialDomain.textContent = window.location.host;
            seoSocialTitle.textContent = title;
            seoSocialDescription.textContent = description;

            seoSocialImage.replaceChildren();
            const imageValue = seoOgImageInput.value.trim();
            if (imageValue) {
                try {
                    const imageUrl = new URL(imageValue, window.location.href);
                    if (imageUrl.protocol === 'https:' || imageUrl.protocol === 'http:') {
                        const image = document.createElement('img');
                        image.src = imageUrl.href;
                        image.alt = 'Pratinjau gambar OpenGraph';
                        image.loading = 'lazy';
                        image.onerror = () => { seoSocialImage.textContent = 'Gambar tidak dapat dimuat'; };
                        seoSocialImage.appendChild(image);
                        return;
                    }
                } catch (_) {
                    // Show a neutral placeholder while an incomplete URL is being typed.
                }
            }
            seoSocialImage.textContent = 'Pratinjau gambar sosial';
        }

        function openSeoModal() {
            syncSeoInputs();
            seoModal.style.display = 'flex';
            seoModal.setAttribute('aria-hidden', 'false');
            seoTitleInput.focus();
        }

        function closeSeoModal() {
            seoModal.style.display = 'none';
            seoModal.setAttribute('aria-hidden', 'true');
        }

        [seoTitleInput, seoDescriptionInput, seoKeywordsInput, seoOgImageInput].forEach(input => {
            input.addEventListener('input', updateSeoPreview);
        });
        seoButton.addEventListener('click', openSeoModal);
        seoCloseButton.addEventListener('click', closeSeoModal);
        seoCancelButton.addEventListener('click', closeSeoModal);
        seoModal.addEventListener('click', event => {
            if (event.target === seoModal) closeSeoModal();
        });
        seoApplyButton.addEventListener('click', () => {
            seoMetadata = {
                meta_title: seoTitleInput.value.trim(),
                meta_description: seoDescriptionInput.value.trim(),
                meta_keywords: seoKeywordsInput.value.trim(),
                og_image: seoOgImageInput.value.trim()
            };
            isDirty = true;
            changeVersion += 1;
            updateStatusUI('Ada perubahan belum disimpan', 'dirty');
            setStatus('Pengaturan SEO diterapkan. Simpan halaman untuk menerbitkannya.');
            closeSeoModal();
        });

        function closeRevisionsModal() {
            revisionsModal.style.display = 'none';
            revisionsModal.setAttribute('aria-hidden', 'true');
        }

        function formatRevisionDate(value) {
            const date = new Date(String(value).replace(' ', 'T'));
            if (Number.isNaN(date.getTime())) return String(value || 'Waktu tidak diketahui');
            return new Intl.DateTimeFormat('id-ID', {
                day: '2-digit', month: 'short', year: 'numeric',
                hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23'
            }).format(date);
        }

        function renderRevisions(revisions) {
            revisionList.replaceChildren();
            if (!revisions.length) {
                const empty = document.createElement('p');
                empty.className = 'revisions-empty';
                empty.textContent = 'Belum ada revisi tersimpan untuk halaman ini.';
                revisionList.appendChild(empty);
                return;
            }
            revisions.forEach((revision, index) => {
                const row = document.createElement('article');
                row.className = 'revision-row';
                const info = document.createElement('div');
                info.className = 'revision-info';
                const date = document.createElement('time');
                date.className = 'revision-date';
                date.dateTime = String(revision.created_at || '');
                date.textContent = formatRevisionDate(revision.created_at);
                const badge = document.createElement('span');
                badge.className = `revision-badge${index === 0 ? ' is-latest' : ''}`;
                badge.textContent = index === 0 ? 'Versi Terakhir / Draf' : `Versi Lama · #${revision.id}`;
                info.append(date, badge);
                const restoreButton = document.createElement('button');
                restoreButton.type = 'button';
                restoreButton.className = 'revision-restore';
                restoreButton.dataset.revisionId = String(revision.id);
                restoreButton.textContent = 'Pulihkan Versi Ini';
                row.append(info, restoreButton);
                revisionList.appendChild(row);
            });
        }

        async function openRevisionsModal() {
            revisionsModalTitle.textContent = `Riwayat Revisi - ${currentPageTitle}`;
            revisionsModal.style.display = 'flex';
            revisionsModal.setAttribute('aria-hidden', 'false');
            revisionList.replaceChildren();
            const loading = document.createElement('p');
            loading.className = 'revisions-empty';
            loading.textContent = 'Memuat riwayat…';
            revisionList.appendChild(loading);
            try {
                const response = await fetch(`api.php?action=get_revisions&page_slug=${encodeURIComponent(activeSlug)}`, {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                });
                const result = await response.json();
                if (!response.ok || !result.success || !Array.isArray(result.data)) {
                    throw new Error(result.message || 'Riwayat revisi gagal dimuat.');
                }
                renderRevisions(result.data);
            } catch (error) {
                revisionList.replaceChildren();
                const message = document.createElement('p');
                message.className = 'revisions-empty';
                message.textContent = error.message || 'Riwayat revisi gagal dimuat.';
                revisionList.appendChild(message);
            }
        }

        revisionsButton.addEventListener('click', openRevisionsModal);
        revisionsCloseButton.addEventListener('click', closeRevisionsModal);
        revisionsFooterButton.addEventListener('click', closeRevisionsModal);
        revisionsModal.addEventListener('click', event => {
            if (event.target === revisionsModal) closeRevisionsModal();
        });
        revisionList.addEventListener('click', async event => {
            const restoreButton = event.target.closest('[data-revision-id]');
            if (!restoreButton) return;
            if (saveInProgress) {
                setStatus('Penyimpanan sedang berlangsung. Coba pulihkan lagi sebentar.');
                return;
            }
            const revisionId = Number(restoreButton.dataset.revisionId);
            if (!Number.isSafeInteger(revisionId) || revisionId < 1) return;
            const warning = isDirty ? '\nPerubahan yang belum disimpan akan dibuang.' : '';
            if (!confirm(`Apakah Anda yakin ingin mengembalikan halaman ke versi ini?${warning}`)) return;

            restoreButton.disabled = true;
            try {
                const response = await fetch('api.php?action=restore_revision', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({ revision_id: revisionId })
                });
                const result = await response.json();
                if (!response.ok || !result.success || !result.page || result.page.slug !== activeSlug) {
                    throw new Error(result.message || 'Versi halaman gagal dipulihkan.');
                }
                isLoadingPage = true;
                editor.setComponents(result.page.json || []);
                editor.setStyle(result.page.css || '');
                isLoadingPage = false;
                changeVersion += 1;
                isDirty = false;
                updateStatusUI('Versi tersimpan di database', 'saved');
                setStatus('Halaman berhasil dipulihkan ke versi lama!');
                updateUndoRedoStatus();
                closeRevisionsModal();
            } catch (error) {
                isLoadingPage = false;
                setStatus(error.message || 'Versi halaman gagal dipulihkan.', true);
                restoreButton.disabled = false;
            }
        });

        async function loadPage() {
            const slug = activeSlug.trim();
            if (!validSlug(slug)) {
                setStatus('Slug tidak valid.', true);
                return;
            }
            setStatus('Memuat halaman…');
            updateStatusUI('Memuat halaman…', 'saving');
            try {
                const response = await fetch(`api.php?action=load&slug=${encodeURIComponent(slug)}`, {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Gagal memuat halaman.');
                editor.setComponents(result.page.json || []);
                editor.setStyle(result.page.css || '');
                currentPageTitle = result.page.title || slug;
                activeLang = String(result.page.lang || 'id').toLowerCase();
                activeTranslationGroup = String(result.page.translation_group || '');
                updateLanguageUi();
                seoMetadata = {
                    meta_title: result.page.meta_title || '',
                    meta_description: result.page.meta_description || '',
                    meta_keywords: result.page.meta_keywords || '',
                    og_image: result.page.og_image || ''
                };
                syncSeoInputs();
                isDirty = false;
                setStatus(`Halaman “${result.page.title || slug}” siap.`);
                updateStatusUI('Semua perubahan tersimpan', 'saved');
            } catch (error) {
                setStatus(error.message || 'Gagal memuat halaman.', true);
                updateStatusUI('Halaman belum termuat', 'error');
            }
        }

        async function loadAssets() {
            try {
                const response = await fetch('api.php?action=get_assets', {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                });
                const result = await response.json();
                if (!response.ok || (!Array.isArray(result) && !result.success)) throw new Error(result.message || 'Gagal memuat gambar.');
                const assets = Array.isArray(result) ? result : (result.data || []);
                assets.forEach(asset => {
                    if (typeof asset === 'string' && asset) {
                        editor.AssetManager.add({ src: asset, type: 'image', name: asset.split('/').pop() });
                    } else if (asset && typeof asset.src === 'string') {
                        editor.AssetManager.add({ ...asset, type: 'image' });
                    }
                });
                filterAssetTiles();
            } catch (error) {
                setStatus(error.message || 'Gagal memuat daftar gambar.', true);
            }
        }

        /** Filter GrapesJS asset tiles by their stored filename or source URL. */
        function filterAssetTiles() {
            const root = editor.AssetManager.getContainer();
            if (!root) return;
            const input = root.querySelector('#asset-manager-search');
            const query = (input?.value || '').trim().toLocaleLowerCase();
            const collection = editor.AssetManager.getAll();
            const models = collection?.models || collection || [];
            const assetsBySrc = new Map();
            Array.from(models).forEach(asset => {
                const get = key => typeof asset?.get === 'function' ? asset.get(key) : asset?.[key];
                const src = get('src');
                if (src) assetsBySrc.set(src, get('name') || src.split('/').pop() || src);
            });
            root.querySelectorAll('.gjs-am-asset').forEach(tile => {
                const image = tile.querySelector('img');
                const imageBox = tile.querySelector('[style*="background-image"]');
                const background = imageBox?.style.backgroundImage || '';
                const backgroundMatch = background.match(/url\(["']?(.*?)["']?\)/i);
                const src = image?.getAttribute('src') || backgroundMatch?.[1] || '';
                const visibleLabel = tile.querySelector('.gjs-am-asset-name')?.textContent || tile.textContent || '';
                const name = String(assetsBySrc.get(src) || tile.getAttribute('title') || visibleLabel || src.split('/').pop() || '').toLocaleLowerCase();
                tile.style.display = !query || name.includes(query) ? '' : 'none';
            });
        }

        /** Add a small filename search control to GrapesJS's built-in asset modal. */
        function installAssetSearch() {
            const root = editor.AssetManager.getContainer();
            if (!root || root.querySelector('#asset-manager-search')) return;
            const toolbar = document.createElement('label');
            toolbar.className = 'asset-search-toolbar';
            toolbar.innerHTML = '<i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input id="asset-manager-search" type="search" autocomplete="off" placeholder="Cari gambar berdasarkan nama file…" aria-label="Cari gambar">';
            const grid = root.querySelector('.gjs-am-assets');
            if (grid?.parentNode) grid.parentNode.insertBefore(toolbar, grid);
            else root.prepend(toolbar);
            toolbar.querySelector('input').addEventListener('input', filterAssetTiles);
            filterAssetTiles();
        }

        editor.on('asset:open', () => window.setTimeout(installAssetSearch, 0));
        editor.on('asset:add', () => window.setTimeout(() => {
            installAssetSearch();
            filterAssetTiles();
        }, 0));

        /** Persist the current page for either an automatic or manual save. */
        async function savePage(isAutomatic = false, slugOverride = null) {
            if (saveInProgress) return false;

            const slug = (slugOverride || activeSlug).trim();
            if (!validSlug(slug)) {
                if (!isAutomatic) {
                    setStatus('Slug hanya boleh huruf kecil, angka, dan tanda hubung.', true);
                    slugInput.focus();
                }
                updateStatusUI('Ada perubahan belum disimpan', 'dirty');
                return false;
            }

            const versionAtStart = changeVersion;
            saveInProgress = true;
            saveButton.disabled = true;
            if (isAutomatic) {
                updateStatusUI('Menyimpan otomatis…', 'saving');
            } else {
                setStatus('Menyimpan halaman…');
                updateStatusUI('Menyimpan…', 'saving');
            }

            try {
                const response = await fetch('api.php?action=save', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({
                        slug,
                        json: editor.getComponents().toJSON(),
                        css: editor.getCss(),
                        html: editor.getHtml(),
                        meta_title: seoMetadata.meta_title,
                        meta_description: seoMetadata.meta_description,
                        meta_keywords: seoMetadata.meta_keywords,
                        og_image: seoMetadata.og_image
                    })
                });
                const result = await response.json();
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Gagal menyimpan halaman.');
                }

                updatePreview();
                const savedTime = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
                if (changeVersion === versionAtStart) {
                    isDirty = false;
                    updateStatusUI(`${isAutomatic ? 'Tersimpan otomatis' : 'Tersimpan'} ${savedTime}`, 'saved');
                } else {
                    isDirty = true;
                    updateStatusUI('Ada perubahan belum disimpan', 'dirty');
                }
                setStatus(result.message || 'Halaman berhasil disimpan.');
                return changeVersion === versionAtStart;
            } catch (error) {
                updateStatusUI('Ada perubahan belum disimpan', 'dirty');
                setStatus(error.message || 'Gagal menyimpan halaman.', true);
                return false;
            } finally {
                saveInProgress = false;
                saveButton.disabled = false;
            }
        }

        /** Save only when the page has unsaved changes. */
        async function triggerAutoSave() {
            if (!isDirty || saveInProgress) return;
            await savePage(true);
        }

        /** Load page records, populate the selector, and safely render the management list. */
        async function loadAllPages() {
            try {
                const response = await fetch('api.php?action=get_all_pages', {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                });
                const result = await response.json();
                if (!response.ok || !result.success || !Array.isArray(result.data)) {
                    throw new Error(result.message || 'Gagal memuat daftar halaman.');
                }
                const pages = result.data;
                pageRecords = pages;
                slugInput.replaceChildren();
                pages.forEach(page => {
                    const option = document.createElement('option');
                    option.value = page.slug;
                    option.textContent = `${page.title} (${page.slug})`;
                    slugInput.appendChild(option);
                });
                if (!pages.some(page => page.slug === activeSlug)) {
                    const current = document.createElement('option');
                    current.value = activeSlug;
                    current.textContent = `${activeSlug} (halaman aktif)`;
                    slugInput.appendChild(current);
                }
                slugInput.value = activeSlug;
                updateLanguageUi();
                renderPagesList(pages);
                return pages;
            } catch (error) {
                if (pagesFeedback) pagesFeedback.textContent = error.message || 'Gagal memuat daftar halaman.';
                return [];
            }
        }

        /** Populate the language picker with pages in the active translation group. */
        function updateLanguageUi() {
            if (!languageSwitcher) return;
            const activeRecord = pageRecords.find(page => page.slug === activeSlug);
            if (activeRecord) {
                activeLang = String(activeRecord.lang || activeLang || 'id').toLowerCase();
                activeTranslationGroup = String(activeRecord.translation_group || '');
            }
            const variants = activeTranslationGroup
                ? pageRecords.filter(page => String(page.translation_group || '') === activeTranslationGroup)
                : pageRecords.filter(page => page.slug === activeSlug);
            languageSwitcher.replaceChildren();
            const options = variants.length ? variants : [{ slug: activeSlug, lang: activeLang }];
            options.forEach(page => {
                const option = document.createElement('option');
                option.value = page.slug;
                option.textContent = String(page.lang || 'id').toUpperCase();
                languageSwitcher.appendChild(option);
            });
            languageSwitcher.value = activeSlug;
            updatePreview();
        }

        /** Build the management table with text nodes to avoid interpreting database values as HTML. */
        function renderPagesList(pages) {
            pagesList.replaceChildren();
            if (!pages.length) {
                const row = pagesList.insertRow();
                const cell = row.insertCell();
                cell.colSpan = 4;
                cell.textContent = 'Belum ada halaman.';
                return;
            }
            pages.forEach(page => {
                const row = pagesList.insertRow();
                [page.title || '', page.slug || '', page.updated_at || '—'].forEach(value => {
                    const cell = row.insertCell();
                    cell.textContent = String(value);
                });
                const actions = row.insertCell();
                const edit = document.createElement('button');
                edit.type = 'button';
                edit.textContent = 'Edit';
                edit.dataset.pageAction = 'edit';
                edit.dataset.slug = page.slug;
                actions.appendChild(edit);
                if (page.slug !== 'home') {
                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.textContent = 'Hapus';
                    remove.dataset.pageAction = 'delete';
                    remove.dataset.slug = page.slug;
                    actions.appendChild(remove);
                }
            });
        }

        /** Switch pages only after the current dirty draft has been saved or explicitly discarded. */
        async function switchToPage(nextSlug) {
            if (!validSlug(nextSlug) || nextSlug === activeSlug) {
                slugInput.value = activeSlug;
                return;
            }
            if (isDirty) {
                const shouldSave = confirm('Ada perubahan belum disimpan. Simpan sebelum pindah halaman?');
                if (shouldSave) {
                    const saved = await savePage(false, activeSlug);
                    if (!saved) {
                        slugInput.value = activeSlug;
                        return;
                    }
                } else {
                    const discard = confirm('Buang perubahan yang belum disimpan dan lanjut pindah halaman?');
                    if (!discard) {
                        slugInput.value = activeSlug;
                        return;
                    }
                }
            }
            window.location.href = `editor.php?page=${encodeURIComponent(nextSlug)}`;
        }

        function openPagesModal(focusForm = false) {
            pagesFeedback.textContent = '';
            pagesModal.style.display = 'flex';
            pagesModal.setAttribute('aria-hidden', 'false');
            loadAllPages().then(() => {
                if (focusForm) newPageTitle.focus();
            });
        }

        function closePagesModal() {
            pagesModal.style.display = 'none';
            pagesModal.setAttribute('aria-hidden', 'true');
        }

        managePagesButton.addEventListener('click', () => openPagesModal(false));
        newPageButton.addEventListener('click', () => openPagesModal(true));
        pagesModalClose.addEventListener('click', closePagesModal);
        pagesModal.addEventListener('click', event => {
            if (event.target === pagesModal) closePagesModal();
        });
        function closeTranslationModal() {
            translationModal.style.display = 'none';
            translationModal.setAttribute('aria-hidden', 'true');
        }

        async function openTranslationModal() {
            translationFeedback.textContent = '';
            const pages = await loadAllPages();
            const activeRecord = pages.find(page => page.slug === activeSlug);
            const sourceLang = String(activeRecord?.lang || activeLang || 'id').toLowerCase();
            const group = String(activeRecord?.translation_group || activeTranslationGroup || '');
            const existingLanguages = new Set(
                pages
                    .filter(page => group && String(page.translation_group || '') === group)
                    .map(page => String(page.lang || 'id').toLowerCase())
            );
            if (!existingLanguages.size) existingLanguages.add(sourceLang);
            const availableLanguages = ['en', 'id'].filter(lang => lang !== sourceLang && !existingLanguages.has(lang));
            if (!availableLanguages.length) {
                setStatus('Versi bahasa ID dan EN sudah tersedia untuk halaman ini.');
                return;
            }
            targetLanguageSelect.replaceChildren();
            availableLanguages.forEach(lang => {
                const option = document.createElement('option');
                option.value = lang;
                option.textContent = lang === 'en' ? '🇬🇧 English (EN)' : '🇮🇩 Bahasa Indonesia (ID)';
                targetLanguageSelect.appendChild(option);
            });
            translationModal.style.display = 'flex';
            translationModal.setAttribute('aria-hidden', 'false');
            targetLanguageSelect.focus();
        }

        translationButton.addEventListener('click', openTranslationModal);
        translationCloseButton.addEventListener('click', closeTranslationModal);
        translationCancelButton.addEventListener('click', closeTranslationModal);
        translationModal.addEventListener('click', event => {
            if (event.target === translationModal) closeTranslationModal();
        });
        languageSwitcher.addEventListener('change', () => switchToPage(languageSwitcher.value));
        translationConfirmButton.addEventListener('click', async () => {
            const targetLang = targetLanguageSelect.value;
            translationConfirmButton.disabled = true;
            translationFeedback.textContent = 'Menyiapkan salinan halaman…';
            try {
                if (isDirty) {
                    const saved = await savePage(false, activeSlug);
                    if (!saved) throw new Error('Simpan halaman terlebih dahulu sebelum membuat terjemahan.');
                }
                const response = await fetch('api.php?action=create_translation', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({ parent_slug: activeSlug, target_lang: targetLang })
                });
                const result = await response.json();
                if (!response.ok || !result.success || !result.page?.slug) {
                    throw new Error(result.message || 'Terjemahan gagal dibuat.');
                }

                closeTranslationModal();
                await loadAllPages();
                activeSlug = result.page.slug;
                slugInput.value = activeSlug;
                window.history.replaceState({}, '', `editor.php?page=${encodeURIComponent(activeSlug)}`);
                updatePreview();
                await loadPage();
                await loadAllPages();
                setStatus(result.existing ? 'Membuka halaman terjemahan yang sudah ada.' : 'Draf terjemahan berhasil dibuat.');
            } catch (error) {
                translationFeedback.textContent = error.message || 'Terjemahan gagal dibuat.';
            } finally {
                translationConfirmButton.disabled = false;
            }
        });
        pagesList.addEventListener('click', async event => {
            const button = event.target.closest('[data-page-action]');
            if (!button) return;
            const { pageAction, slug } = button.dataset;
            if (pageAction === 'edit') {
                closePagesModal();
                await switchToPage(slug);
                return;
            }
            if (pageAction === 'delete') {
                if (slug === 'home' || !confirm(`Hapus halaman “${slug}” secara permanen?`)) return;
                button.disabled = true;
                try {
                    if (slug === activeSlug && isDirty) {
                        const saved = await savePage(false, activeSlug);
                        if (!saved) throw new Error('Simpan perubahan halaman terlebih dahulu sebelum menghapusnya.');
                    }
                    const response = await fetch('api.php?action=delete_page', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
                        body: JSON.stringify({ slug })
                    });
                    const result = await response.json();
                    if (!response.ok || !result.success) throw new Error(result.message || 'Halaman gagal dihapus.');
                    if (slug === activeSlug) {
                        window.location.href = 'editor.php?page=home';
                        return;
                    }
                    pagesFeedback.textContent = result.message || 'Halaman berhasil dihapus.';
                    await loadAllPages();
                } catch (error) {
                    pagesFeedback.textContent = error.message || 'Halaman gagal dihapus.';
                    button.disabled = false;
                }
            }
        });
        createPageForm.addEventListener('submit', async event => {
            event.preventDefault();
            const title = newPageTitle.value.trim();
            const slug = newPageSlug.value.trim();
            if (!title || !slug) return;
            createPageButton.disabled = true;
            pagesFeedback.textContent = 'Membuat halaman…';
            try {
                const response = await fetch('api.php?action=create_page', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrfToken },
                    body: JSON.stringify({ title, slug })
                });
                const result = await response.json();
                if (!response.ok || !result.success || !result.slug) throw new Error(result.message || 'Halaman gagal dibuat.');
                createPageForm.reset();
                window.location.href = `editor.php?page=${encodeURIComponent(result.slug)}`;
            } catch (error) {
                pagesFeedback.textContent = error.message || 'Halaman gagal dibuat.';
            } finally {
                createPageButton.disabled = false;
            }
        });

        /** Download the current GrapesJS component tree and style data as a JSON template. */
        function exportTemplate() {
            const componentCollection = editor.getComponents();
            const data = {
                components: typeof componentCollection.toJSON === 'function'
                    ? componentCollection.toJSON()
                    : componentCollection,
                style: editor.getStyle()
            };
            const json = JSON.stringify(data, null, 2);
            const blobUrl = URL.createObjectURL(new Blob([json], { type: 'application/json;charset=utf-8' }));
            const safeSlug = slugInput.value.trim().replace(/[^a-z0-9-]+/gi, '-') || 'home';
            const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
            const link = document.createElement('a');
            link.href = blobUrl;
            link.download = `template-page-${safeSlug}-${timestamp}.json`;
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.setTimeout(() => URL.revokeObjectURL(blobUrl), 1000);
        }

        /** Read and validate a JSON template before replacing the current canvas. */
        function importTemplate(file) {
            const reader = new FileReader();
            reader.addEventListener('load', () => {
                try {
                    const data = JSON.parse(String(reader.result || ''));
                    if (!data || typeof data !== 'object') {
                        throw new Error('File JSON tidak berisi objek template.');
                    }
                    const validComponents = Array.isArray(data.components)
                        || (typeof data.components === 'object' && data.components !== null)
                        || typeof data.components === 'string';
                    const validStyle = Array.isArray(data.style)
                        || (typeof data.style === 'object' && data.style !== null)
                        || typeof data.style === 'string';

                    if (!data || !validComponents || !validStyle) {
                        throw new Error('Struktur file harus berisi data components dan style yang valid.');
                    }

                    editor.setComponents(data.components);
                    editor.setStyle(data.style);
                    setStatus('Template JSON berhasil di-import.');
                    alert('Template JSON berhasil di-import!');
                } catch (error) {
                    alert(error.message || 'File JSON tidak valid.');
                    setStatus('Gagal meng-import template JSON.', true);
                } finally {
                    importFileInput.value = '';
                }
            });
            reader.addEventListener('error', () => {
                importFileInput.value = '';
                setStatus('File JSON tidak dapat dibaca.', true);
                alert('File JSON tidak dapat dibaca.');
            });
            reader.readAsText(file);
        }

        function closeTemplateModal() {
            templateModal.style.display = 'none';
            templateModal.setAttribute('aria-hidden', 'true');
        }

        function openSaveTemplateModal() {
            saveTemplateForm.reset();
            saveTemplateError.textContent = '';
            saveTemplateModal.style.display = 'flex';
            saveTemplateModal.setAttribute('aria-hidden', 'false');
            customTemplateName.focus();
        }

        function closeSaveTemplateModal() {
            saveTemplateModal.style.display = 'none';
            saveTemplateModal.setAttribute('aria-hidden', 'true');
            saveTemplateForm.reset();
            saveTemplateError.textContent = '';
        }

        function renderTemplateCards(templates) {
            templateGrid.replaceChildren();
            if (!templates.length) {
                const emptyMessage = document.createElement('p');
                emptyMessage.className = 'template-message';
                emptyMessage.textContent = 'Belum ada preset template.';
                templateGrid.appendChild(emptyMessage);
                return;
            }

            templates.forEach(template => {
                const card = document.createElement('article');
                card.className = 'template-card';

                const mark = document.createElement('span');
                mark.className = 'template-card-mark';
                mark.setAttribute('aria-hidden', 'true');
                mark.textContent = '✦';

                const title = document.createElement('h3');
                title.textContent = template.name || 'Template';

                const description = document.createElement('p');
                description.textContent = template.description || '';

                const useButton = document.createElement('button');
                useButton.className = 'template-use';
                useButton.type = 'button';
                useButton.dataset.templateId = template.id || '';
                useButton.textContent = 'Gunakan Template';

                card.append(mark, title, description, useButton);
                templateGrid.appendChild(card);
            });
        }

        async function openTemplateLibrary() {
            templateModal.style.display = 'flex';
            templateModal.setAttribute('aria-hidden', 'false');
            templateGrid.replaceChildren();
            const loadingMessage = document.createElement('p');
            loadingMessage.className = 'template-message';
            loadingMessage.textContent = 'Memuat preset…';
            templateGrid.appendChild(loadingMessage);

            try {
                const response = await fetch('api.php?action=get_templates', {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                });
                const result = await response.json();
                if (!response.ok || !result.success || !Array.isArray(result.data)) {
                    throw new Error(result.message || 'Daftar preset gagal dimuat.');
                }
                templateLibrary = result.data;
                renderTemplateCards(result.data);
            } catch (error) {
                templateGrid.replaceChildren();
                const errorMessage = document.createElement('p');
                errorMessage.className = 'template-message';
                errorMessage.textContent = error.message || 'Daftar preset gagal dimuat.';
                templateGrid.appendChild(errorMessage);
            }
        }

        templatesButton.addEventListener('click', openTemplateLibrary);
        saveTemplateButton.addEventListener('click', openSaveTemplateModal);
        document.getElementById('close-save-template').addEventListener('click', closeSaveTemplateModal);
        document.getElementById('btn-cancel-template').addEventListener('click', closeSaveTemplateModal);
        saveTemplateModal.addEventListener('click', event => {
            if (event.target === saveTemplateModal) closeSaveTemplateModal();
        });
        saveTemplateForm.addEventListener('submit', async event => {
            event.preventDefault();
            const name = customTemplateName.value.trim();
            const description = customTemplateDescription.value.trim();
            if (!name) {
                customTemplateName.focus();
                saveTemplateError.textContent = 'Nama template wajib diisi.';
                return;
            }

            saveTemplateSubmit.disabled = true;
            saveTemplateError.textContent = '';
            try {
                const response = await fetch('api.php?action=save_custom_template', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({
                        name,
                        description,
                        json: editor.getComponents().toJSON(),
                        css: editor.getCss()
                    })
                });
                const result = await response.json();
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Custom template gagal disimpan.');
                }
                alert('Custom Template berhasil disimpan!');
                setStatus(result.message || 'Custom template berhasil disimpan.');
                closeSaveTemplateModal();
            } catch (error) {
                saveTemplateError.textContent = error.message || 'Custom template gagal disimpan.';
            } finally {
                saveTemplateSubmit.disabled = false;
            }
        });
        templateCloseButton.addEventListener('click', closeTemplateModal);
        templateModal.addEventListener('click', event => {
            if (event.target === templateModal) closeTemplateModal();
        });

        /** Handle shortcuts both in the editor shell and inside GrapesJS's canvas iframe. */
        function handleEditorShortcuts(event) {
            const key = event.key.toLowerCase();
            if (key === 'escape') {
                const assetManagerOpen = editor.AssetManager.isOpen();
                const anyModalOpen = templateModal.style.display === 'flex'
                    || navigatorPanel.classList.contains('is-open')
                    || translationModal.style.display === 'flex'
                    || siteBackupModal.style.display === 'flex'
                    || seoModal.style.display === 'flex'
                    || revisionsModal.style.display === 'flex'
                    || pagesModal.style.display === 'flex'
                    || saveTemplateModal.style.display === 'flex'
                    || codeEditorModal.style.display === 'flex'
                    || shortcutsModal.style.display === 'flex'
                    || assetManagerOpen;
                if (!anyModalOpen) return;

                event.preventDefault();
                if (templateModal.style.display === 'flex') closeTemplateModal();
                if (navigatorPanel.classList.contains('is-open')) setNavigatorOpen(false);
                if (translationModal.style.display === 'flex') closeTranslationModal();
                if (siteBackupModal.style.display === 'flex') closeSiteBackupModal();
                if (seoModal.style.display === 'flex') closeSeoModal();
                if (revisionsModal.style.display === 'flex') closeRevisionsModal();
                if (pagesModal.style.display === 'flex') closePagesModal();
                if (saveTemplateModal.style.display === 'flex') closeSaveTemplateModal();
                if (codeEditorModal.style.display === 'flex') closeCodeEditor();
                if (shortcutsModal.style.display === 'flex') closeShortcutsModal();
                if (assetManagerOpen) editor.AssetManager.close();
                return;
            }

            if (!(event.ctrlKey || event.metaKey) || event.altKey) return;

            if (key === 'n' && event.shiftKey) {
                event.preventDefault();
                setNavigatorOpen(!navigatorPanel.classList.contains('is-open'));
                return;
            }

            if (key === 's') {
                event.preventDefault();
                savePage(false);
            } else if ((key === 'z' && event.shiftKey) || key === 'y') {
                event.preventDefault();
                um.redo();
            } else if (key === 'z') {
                event.preventDefault();
                um.undo();
            } else if (key === 'p') {
                event.preventDefault();
                previewPage();
            } else if (key === 'e') {
                event.preventDefault();
                if (codeEditorModal.style.display === 'flex') closeCodeEditor();
                else openCodeEditor();
            }
        }

        document.addEventListener('keydown', handleEditorShortcuts);
        const shortcutDocuments = new WeakSet();
        function attachCanvasShortcuts() {
            const canvasDocument = editor.Canvas.getDocument();
            if (canvasDocument && !shortcutDocuments.has(canvasDocument)) {
                canvasDocument.addEventListener('keydown', handleEditorShortcuts);
                shortcutDocuments.add(canvasDocument);
            }
        }
        editor.on('canvas:frame:load', attachCanvasShortcuts);
        attachCanvasShortcuts();

        templateGrid.addEventListener('click', event => {
            const useButton = event.target.closest('[data-template-id]');
            if (!useButton) return;

            const templateId = useButton.dataset.templateId;
            const template = templateLibrary.find(item => item.id === templateId);
            if (!template || !template.components || typeof template.style !== 'string') {
                setStatus('Data preset tidak lengkap.', true);
                return;
            }
            if (!confirm('Impor template ini ke canvas?')) return;

            editor.setComponents(template.components);
            editor.setStyle(template.style);
            setStatus(`Preset “${template.name}” siap diedit.`);
            closeTemplateModal();
        });

        exportButton.addEventListener('click', exportTemplate);
        importButton.addEventListener('click', () => importFileInput.click());
        importFileInput.addEventListener('change', () => {
            const file = importFileInput.files && importFileInput.files[0];
            if (file) importTemplate(file);
        });

        saveButton.addEventListener('click', () => savePage(false));

        loadPage().finally(() => {
            isLoadingPage = false;
            updateUndoRedoStatus();
        });
        loadAllPages();
        autoSaveInterval = window.setInterval(triggerAutoSave, 120000);
        loadAssets();
        if (params.get('openBackup') === '1') openSiteBackupModal();
    })();
    </script>
</body>
</html>
