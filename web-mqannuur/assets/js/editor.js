    (() => {
        const status = document.getElementById('status');
        const autoSaveStatus = document.getElementById('autosave-status');
        const slugInput = document.getElementById('slug');
        const pageSelect = document.getElementById('page-select');
        const managePagesButton = document.getElementById('btn-manage-pages');
        const pageManagerModal = document.getElementById('page-manager-modal');
        const closePageManagerButton = document.getElementById('btn-close-page-manager');
        const pageCreateForm = document.getElementById('page-create-form');
        const newPageTitleInput = document.getElementById('new-page-title');
        const newPageSlugInput = document.getElementById('new-page-slug');
        const pageListBody = document.getElementById('page-list-body');
        const saveButton = document.getElementById('save-page');
        const undoButton = document.getElementById('btn-undo');
        const redoButton = document.getElementById('btn-redo');
        const exportButton = document.getElementById('btn-export');
        const importButton = document.getElementById('btn-import');
        const importFileInput = document.getElementById('import-file-input');
        const templatesButton = document.getElementById('btn-templates');
        const templateModal = document.getElementById('template-modal');
        const templateGrid = document.getElementById('template-grid');
        const closeTemplatesButton = document.getElementById('btn-close-templates');
        const saveTemplateButton = document.getElementById('btn-save-template');
        const saveTemplateModal = document.getElementById('save-template-modal');
        const saveTemplateForm = document.getElementById('save-template-form');
        const templateNameInput = document.getElementById('template-name');
        const templateDescriptionInput = document.getElementById('template-description');
        const submitSaveTemplateButton = document.getElementById('btn-submit-save-template');
        const cancelSaveTemplateButton = document.getElementById('btn-cancel-save-template');
        const closeSaveTemplateButton = document.getElementById('btn-close-save-template');
        const codeEditorButton = document.getElementById('btn-code-editor');
        const codeEditorModal = document.getElementById('code-editor-modal');
        const codeHtmlInput = document.getElementById('code-html-input');
        const codeCssInput = document.getElementById('code-css-input');
        const applyCodeButton = document.getElementById('btn-apply-code');
        const cancelCodeButton = document.getElementById('btn-cancel-code');
        const closeCodeEditorButton = document.getElementById('btn-close-code-editor');
        const previewButton = document.getElementById('btn-preview');
        const shortcutsButton = document.getElementById('btn-shortcuts');
        const shortcutsModal = document.getElementById('shortcuts-modal');
        const closeShortcutsButton = document.getElementById('btn-close-shortcuts');
        const navigatorButton = document.getElementById('btn-navigator');
        const previewLink = document.getElementById('preview-page');
        const workspace = document.getElementById('workspace');
        const topbar = document.getElementById('topbar');
        const toggleLeftButton = document.getElementById('btn-toggle-left');
        const toggleRightButton = document.getElementById('btn-toggle-right');
        const panelOverlay = document.getElementById('panel-overlay');
        const moreButton = document.getElementById('btn-more');
        const moreMenu = document.getElementById('more-menu');
        const tabBlocks = document.getElementById('tab-blocks');
        const tabLayers = document.getElementById('tab-layers');
        const panelBlocks = document.getElementById('panel-blocks');
        const panelLayers = document.getElementById('panel-layers');
        const tabStyle = document.getElementById('tab-style');
        const tabSettings = document.getElementById('tab-settings');
        const tabPage = document.getElementById('tab-page');
        const panelStyle = document.getElementById('panel-style');
        const panelSettings = document.getElementById('panel-settings');
        const panelPage = document.getElementById('panel-page');
        const blockSearch = document.getElementById('block-search');
        const pageTitleInput = document.getElementById('page-title-input');
        const pageSlugDisplay = document.getElementById('page-slug-display');
        const pageSettingsSaveButton = document.getElementById('btn-page-settings-save');
        const pageSettingsPreviewButton = document.getElementById('btn-page-settings-preview');
        const zoomInButton = document.getElementById('zoom-in');
        const zoomOutButton = document.getElementById('zoom-out');
        const zoomLevelButton = document.getElementById('zoom-level');
        const zoomFitButton = document.getElementById('zoom-fit');
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        let isDirty = false;
        let autoSaveInterval = null;
        let isLoadingPage = false;
        let changeVersion = 0;
        let saveInProgress = null;
        let zoomLevel = 100;
        const params = new URLSearchParams(window.location.search);
        slugInput.value = params.get('page') || slugInput.value || 'home';
        pageSelect.value = slugInput.value;
        let activePageTitle = slugInput.value;

        const validSlug = value => /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(value) && value.length <= 100;
        const setStatus = (message, isError = false) => {
            status.textContent = message;
            status.dataset.state = isError ? 'error' : '';
        };
        const updateStatusUI = (message, state = 'saved') => {
            autoSaveStatus.textContent = message;
            autoSaveStatus.dataset.state = state;
        };
        const updatePreview = () => {
            previewLink.href = `index.php?page=${encodeURIComponent(slugInput.value.trim())}`;
        };
        const apiRequest = async (action, payload = null) => {
            const options = {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            };
            if (payload !== null) {
                options.method = 'POST';
                options.headers['Content-Type'] = 'application/json';
                options.headers['X-CSRF-Token'] = csrfToken;
                options.body = JSON.stringify(payload);
            }
            const response = await fetch(`api.php?action=${encodeURIComponent(action)}`, options);
            const result = await response.json();
            if (!response.ok || result.success === false) {
                throw new Error(result.message || 'Permintaan halaman gagal.');
            }
            return result;
        };

        if (typeof grapesjs === 'undefined') {
            setStatus('GrapesJS gagal dimuat dari CDN.', true);
            saveButton.disabled = true;
            return;
        }

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

        const editorAssetUrl = path => new URL(path, window.location.href).href;
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
                    'https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css',
                    'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Lato:wght@300;400;700&family=Merriweather:wght@300;400;700&family=Montserrat:wght@300;400;600;700&family=Nunito:wght@300;400;600;700&family=Open+Sans:wght@300;400;600;700&family=Oswald:wght@400;600&family=Playfair+Display:wght@400;700&family=Poppins:wght@300;400;600;700&family=Raleway:wght@300;400;600;700&family=Roboto:wght@300;400;500;700&display=swap',
                    editorAssetUrl('assets/css/builder-blocks.css')
                ]
            },
            deviceManager: {
                devices: [
                    { name: 'Desktop', width: '100%' },
                    { name: 'Tablet', width: '768px' },
                    { name: 'Mobile', width: '320px' },
                    { name: 'Mobile 375', width: '375px' }
                ]
            },
            blockManager: { appendTo: '.blocks-container' },
            layerManager: {
                appendTo: '#layers-container',
                showWrapper: true,
                sortable: true,
                hydratable: true
            },
            assetManager: {
                upload: 'api.php?action=upload_asset',
                uploadName: 'files',
                autoAdd: true,
                headers: { 'X-CSRF-Token': csrfToken }
            },
            styleManager: {
                appendTo: '.styles-container',
                sectors: [{
                    name: 'Tata Letak',
                    open: true,
                    buildProps: ['display', 'position', 'top', 'right', 'bottom', 'left', 'z-index', 'overflow']
                }, {
                    name: 'Flexbox',
                    open: false,
                    buildProps: ['flex-direction', 'flex-wrap', 'justify-content', 'align-items', 'align-content', 'gap', 'order', 'flex-grow', 'flex-shrink', 'flex-basis']
                }, {
                    name: 'Grid',
                    open: false,
                    buildProps: ['grid-template-columns', 'grid-template-rows', 'gap']
                }, {
                    name: 'Ukuran',
                    open: false,
                    buildProps: ['width', 'min-width', 'max-width', 'height', 'min-height', 'max-height']
                }, {
                    name: 'Tipografi',
                    open: true,
                    properties: [{
                        property: 'font-family',
                        type: 'select',
                        options: fontOptions
                    }, {
                        property: 'font-size'
                    }, {
                        property: 'font-weight'
                    }, {
                        property: 'line-height'
                    }, {
                        property: 'color'
                    }, {
                        property: 'letter-spacing'
                    }, {
                        property: 'text-align'
                    }, {
                        property: 'text-transform'
                    }, {
                        property: 'text-decoration'
                    }, {
                        property: 'text-shadow'
                    }, {
                        property: 'word-break'
                    }]
                }, {
                    name: 'Latar',
                    open: false,
                    buildProps: ['background-color', 'background-image', 'background-repeat', 'background-position', 'background-size', 'background-attachment']
                }, {
                    name: 'Jarak',
                    open: false,
                    buildProps: ['margin', 'padding']
                }, {
                    name: 'Border & Bayangan',
                    open: false,
                    buildProps: ['border-radius', 'border', 'border-width', 'border-style', 'border-color', 'box-shadow']
                }, {
                    name: 'Efek',
                    open: false,
                    buildProps: ['opacity', 'transform', 'transition', 'filter', 'cursor']
                }]
            },
            panels: { defaults: [] },
            traitManager: { appendTo: '.traits-container' },
            selectorManager: { componentFirst: true }
        });

        editor.on('component:add component:remove component:update styleUpdate', () => {
            if (isLoadingPage) return;
            isDirty = true;
            changeVersion += 1;
            updateStatusUI('Ada perubahan belum disimpan', 'pending');
        });

        async function navigateToPage(slug) {
            if (slug === slugInput.value) return;
            if (isDirty) {
                if (window.confirm('Ada perubahan belum disimpan. Simpan sebelum berpindah halaman?')) {
                    try {
                        await persistPage(false);
                    } catch {
                        return;
                    }
                } else if (!window.confirm('Perubahan akan dibuang. Tetap berpindah halaman?')) {
                    pageSelect.value = slugInput.value;
                    return;
                }
            }
            window.location.href = `editor.php?page=${encodeURIComponent(slug)}`;
        }

        function renderPageList(pages) {
            pageListBody.replaceChildren();
            if (pages.length === 0) {
                const row = document.createElement('tr');
                const cell = document.createElement('td');
                cell.colSpan = 3;
                cell.className = 'page-list-empty';
                cell.textContent = 'Belum ada halaman.';
                row.append(cell);
                pageListBody.append(row);
                return;
            }

            pages.forEach(page => {
                const row = document.createElement('tr');
                const titleCell = document.createElement('td');
                titleCell.textContent = page.title;
                const slugCell = document.createElement('td');
                slugCell.textContent = page.slug;
                const actionCell = document.createElement('td');
                const actions = document.createElement('div');
                actions.className = 'page-list-actions';
                const editButton = document.createElement('button');
                editButton.type = 'button';
                editButton.textContent = 'Edit';
                editButton.addEventListener('click', () => navigateToPage(page.slug));
                actions.append(editButton);
                if (page.slug !== 'home') {
                    const deleteButton = document.createElement('button');
                    deleteButton.type = 'button';
                    deleteButton.className = 'delete-page';
                    deleteButton.textContent = 'Hapus';
                    deleteButton.addEventListener('click', async () => {
                        if (!window.confirm(`Hapus halaman “${page.title}” (${page.slug})?`)) return;
                        if (page.slug === slugInput.value && isDirty
                            && !window.confirm('Halaman ini memiliki perubahan belum disimpan. Hapus dan buang perubahan tersebut?')) {
                            return;
                        }
                        try {
                            await apiRequest('delete_page', { slug: page.slug });
                            if (page.slug === slugInput.value) {
                                window.location.href = 'editor.php?page=home';
                                return;
                            }
                            await loadPages();
                            setStatus('Halaman berhasil dihapus.');
                        } catch (error) {
                            setStatus(error.message || 'Halaman gagal dihapus.', true);
                        }
                    });
                    actions.append(deleteButton);
                }
                actionCell.append(actions);
                row.append(titleCell, slugCell, actionCell);
                pageListBody.append(row);
            });
        }

        async function loadPages() {
            const pages = await apiRequest('get_all_pages');
            if (!Array.isArray(pages)) throw new Error('Daftar halaman tidak valid.');

            pageSelect.replaceChildren();
            const currentPage = pages.find(page => page.slug === slugInput.value);
            if (!currentPage) {
                const option = document.createElement('option');
                option.value = slugInput.value;
                option.textContent = slugInput.value;
                pageSelect.append(option);
            }
            pages.forEach(page => {
                const option = document.createElement('option');
                option.value = page.slug;
                option.textContent = page.title;
                pageSelect.append(option);
            });
            pageSelect.value = slugInput.value;
            activePageTitle = currentPage?.title || slugInput.value;
            syncPageSettings();
            updatePreview();
            renderPageList(pages);
            return pages;
        }

        function openPageManager() {
            pageManagerModal.hidden = false;
            closePageManagerButton.focus();
            loadPages().catch(error => setStatus(error.message || 'Daftar halaman gagal dimuat.', true));
        }

        function closePageManager() {
            pageManagerModal.hidden = true;
            managePagesButton.focus();
        }

        managePagesButton.addEventListener('click', openPageManager);
        closePageManagerButton.addEventListener('click', closePageManager);
        pageManagerModal.addEventListener('click', event => {
            if (event.target === pageManagerModal) closePageManager();
        });
        pageSelect.addEventListener('change', () => navigateToPage(pageSelect.value));
        newPageTitleInput.addEventListener('input', () => {
            if (!newPageSlugInput.dataset.edited) {
                newPageSlugInput.value = newPageTitleInput.value
                    .toLowerCase()
                    .normalize('NFKD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-+|-+$/g, '');
            }
        });
        newPageSlugInput.addEventListener('input', () => {
            newPageSlugInput.dataset.edited = 'true';
        });
        pageCreateForm.addEventListener('submit', async event => {
            event.preventDefault();
            if (isDirty) {
                if (window.confirm('Ada perubahan belum disimpan. Simpan sebelum membuat halaman?')) {
                    try {
                        await persistPage(false);
                    } catch {
                        return;
                    }
                } else if (!window.confirm('Perubahan akan dibuang saat membuka halaman baru. Lanjutkan?')) {
                    return;
                }
            }
            try {
                const result = await apiRequest('create_page', {
                    title: newPageTitleInput.value.trim(),
                    slug: newPageSlugInput.value.trim()
                });
                window.location.href = `editor.php?page=${encodeURIComponent(result.slug)}`;
            } catch (error) {
                setStatus(error.message || 'Halaman gagal dibuat.', true);
            }
        });

        function previewPage() {
            try {
                localStorage.setItem('grapesjs_preview_html', editor.getHtml());
                localStorage.setItem('grapesjs_preview_css', editor.getCss());
                const previewWindow = window.open('index.php?preview=true', '_blank');
                if (!previewWindow) {
                    setStatus('Tab preview diblokir browser. Izinkan pop-up untuk situs ini.', true);
                    return;
                }
                setStatus('Preview draft dibuka di tab baru.');
            } catch (error) {
                setStatus(error.message || 'Preview gagal disiapkan di browser ini.', true);
            }
        }

        previewButton.addEventListener('click', previewPage);

        function openCodeEditor() {
            codeHtmlInput.value = editor.getHtml();
            codeCssInput.value = editor.getCss();
            codeEditorModal.hidden = false;
            codeHtmlInput.focus();
        }

        function closeCodeEditor() {
            codeEditorModal.hidden = true;
            codeEditorButton.focus();
        }
        function openShortcutsModal() {
            shortcutsModal.hidden = false;
            closeShortcutsButton.focus();
        }
        function closeShortcutsModal() {
            shortcutsModal.hidden = true;
            shortcutsButton.focus();
        }
        function syncPageSettings() {
            pageTitleInput.value = activePageTitle;
            pageSlugDisplay.value = slugInput.value;
        }

        const isMobileLayout = () => window.matchMedia('(max-width: 1100px)').matches;
        const isLeftOpen = () => isMobileLayout()
            ? workspace.classList.contains('drawer-left-open')
            : !workspace.classList.contains('left-collapsed');
        const isRightOpen = () => isMobileLayout()
            ? workspace.classList.contains('drawer-right-open')
            : !workspace.classList.contains('right-collapsed');

        function syncOverlay() {
            panelOverlay.hidden = !(isMobileLayout()
                && (workspace.classList.contains('drawer-left-open') || workspace.classList.contains('drawer-right-open')));
        }

        function setLeftPanel(open) {
            if (isMobileLayout()) {
                if (open) workspace.classList.remove('drawer-right-open');
                workspace.classList.toggle('drawer-left-open', open);
            } else {
                workspace.classList.toggle('left-collapsed', !open);
            }
            toggleLeftButton.setAttribute('aria-expanded', String(open));
            syncOverlay();
        }

        function setRightPanel(open) {
            if (isMobileLayout()) {
                if (open) workspace.classList.remove('drawer-left-open');
                workspace.classList.toggle('drawer-right-open', open);
            } else {
                workspace.classList.toggle('right-collapsed', !open);
            }
            toggleRightButton.setAttribute('aria-expanded', String(open));
            syncOverlay();
        }

        function activateLeftTab(name, openPanel = true) {
            const isBlocks = name === 'blocks';
            tabBlocks.setAttribute('aria-selected', String(isBlocks));
            tabLayers.setAttribute('aria-selected', String(!isBlocks));
            panelBlocks.hidden = !isBlocks;
            panelLayers.hidden = isBlocks;
            if (openPanel) setLeftPanel(true);
        }

        function activateRightTab(name, openPanel = true) {
            [['style', tabStyle, panelStyle], ['settings', tabSettings, panelSettings], ['page', tabPage, panelPage]].forEach(([key, tab, panel]) => {
                const active = key === name;
                tab.setAttribute('aria-selected', String(active));
                panel.hidden = !active;
            });
            if (openPanel) setRightPanel(true);
        }

        function setMoreMenu(open) {
            moreMenu.hidden = !open;
            moreButton.setAttribute('aria-expanded', String(open));
        }

        function syncTopbarHeight() {
            document.documentElement.style.setProperty('--topbar-h', `${topbar.offsetHeight}px`);
        }

        const layoutQuery = window.matchMedia('(max-width: 1100px)');
        function handleLayoutChange() {
            workspace.classList.remove('drawer-left-open', 'drawer-right-open');
            workspace.classList.remove('left-collapsed', 'right-collapsed');
            const expanded = layoutQuery.matches ? 'false' : 'true';
            toggleLeftButton.setAttribute('aria-expanded', expanded);
            toggleRightButton.setAttribute('aria-expanded', expanded);
            syncOverlay();
            syncTopbarHeight();
        }
        if (typeof layoutQuery.addEventListener === 'function') {
            layoutQuery.addEventListener('change', handleLayoutChange);
        }
        handleLayoutChange();
        if (typeof ResizeObserver === 'function') {
            new ResizeObserver(syncTopbarHeight).observe(topbar);
        }
        window.addEventListener('resize', syncTopbarHeight);

        toggleLeftButton.addEventListener('click', () => setLeftPanel(!isLeftOpen()));
        toggleRightButton.addEventListener('click', () => setRightPanel(!isRightOpen()));
        panelOverlay.addEventListener('click', () => { setLeftPanel(false); setRightPanel(false); });
        tabBlocks.addEventListener('click', () => activateLeftTab('blocks'));
        tabLayers.addEventListener('click', () => activateLeftTab('layers'));
        tabStyle.addEventListener('click', () => activateRightTab('style'));
        tabSettings.addEventListener('click', () => activateRightTab('settings'));
        tabPage.addEventListener('click', () => activateRightTab('page'));
        navigatorButton.addEventListener('click', () => activateLeftTab('layers'));

        moreButton.addEventListener('click', event => {
            event.stopPropagation();
            setMoreMenu(moreMenu.hidden);
        });
        moreMenu.querySelectorAll('button, a').forEach(item => {
            item.addEventListener('click', () => setMoreMenu(false));
        });
        document.addEventListener('click', event => {
            if (!moreMenu.hidden && !moreMenu.contains(event.target) && !moreButton.contains(event.target)) {
                setMoreMenu(false);
            }
        });

        function applyZoom(value) {
            zoomLevel = Math.max(25, Math.min(150, Math.round(value)));
            editor.Canvas.setZoom(zoomLevel);
            zoomLevelButton.textContent = `${zoomLevel}%`;
        }
        function currentDeviceWidth() {
            const device = editor.Devices.get(editor.getDevice());
            const width = parseInt(String(device?.get('width') || ''), 10);
            return Number.isFinite(width) ? width : 0;
        }
        function fitCanvas() {
            const width = currentDeviceWidth();
            const canvasWidth = document.querySelector('.canvas-area')?.clientWidth || window.innerWidth;
            applyZoom(width ? ((canvasWidth - 100) / width) * 100 : 100);
        }
        zoomInButton.addEventListener('click', () => applyZoom(zoomLevel + 10));
        zoomOutButton.addEventListener('click', () => applyZoom(zoomLevel - 10));
        zoomLevelButton.addEventListener('click', () => applyZoom(100));
        zoomFitButton.addEventListener('click', fitCanvas);

        blockSearch.addEventListener('input', () => {
            const query = blockSearch.value.trim().toLowerCase();
            if (query !== '') {
                editor.BlockManager.getCategories().forEach(category => category.set('open', true));
            }
            document.querySelectorAll('.blocks-container .gjs-block-category').forEach(category => {
                let visible = 0;
                category.querySelectorAll('.gjs-block').forEach(block => {
                    const match = query === '' || (block.textContent || '').toLowerCase().includes(query);
                    block.style.display = match ? '' : 'none';
                    if (match) visible += 1;
                });
                category.style.display = visible > 0 ? '' : 'none';
            });
        });
        blockSearch.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                blockSearch.value = '';
                blockSearch.dispatchEvent(new Event('input'));
                blockSearch.blur();
            }
        });

        pageTitleInput.addEventListener('input', () => {
            activePageTitle = pageTitleInput.value;
            if (isLoadingPage) return;
            isDirty = true;
            changeVersion += 1;
            updateStatusUI('Ada perubahan belum disimpan', 'pending');
        });
        pageSettingsSaveButton.addEventListener('click', () => {
            persistPage(false).catch(error => setStatus(error.message || 'Gagal menyimpan halaman.', true));
        });
        pageSettingsPreviewButton.addEventListener('click', previewPage);
        syncPageSettings();

        codeEditorButton.addEventListener('click', openCodeEditor);
        closeCodeEditorButton.addEventListener('click', closeCodeEditor);
        cancelCodeButton.addEventListener('click', closeCodeEditor);
        shortcutsButton.addEventListener('click', openShortcutsModal);
        closeShortcutsButton.addEventListener('click', closeShortcutsModal);
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

        const um = editor.UndoManager;
        function updateUndoRedoStatus() {
            undoButton.disabled = !um.hasUndo();
            redoButton.disabled = !um.hasRedo();
        }
        undoButton.addEventListener('click', () => um.undo());
        redoButton.addEventListener('click', () => um.redo());
        editor.on('undo redo component:add component:remove component:update styleUpdate', updateUndoRedoStatus);
        updateUndoRedoStatus();

        function exportTemplate() {
            const slug = slugInput.value.trim();
            if (!validSlug(slug)) {
                setStatus('Slug tidak valid untuk nama file export.', true);
                slugInput.focus();
                return;
            }

            try {
                const data = {
                    components: editor.getComponents().toJSON(),
                    style: editor.getStyle()
                };
                const json = JSON.stringify(data, null, 2);
                const blob = new Blob([json], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
                link.href = url;
                link.download = `template-page-${slug}-${timestamp}.json`;
                document.body.appendChild(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(url);
                setStatus('Template JSON berhasil diexport.');
            } catch (error) {
                setStatus(error.message || 'Gagal mengexport template JSON.', true);
            }
        }

        function importTemplate(file) {
            const reader = new FileReader();
            reader.addEventListener('load', () => {
                try {
                    if (typeof reader.result !== 'string') {
                        throw new Error('Isi file tidak dapat dibaca.');
                    }
                    const data = JSON.parse(reader.result);
                    const validComponents = Array.isArray(data?.components)
                        || (data?.components !== null && typeof data?.components === 'object');
                    if (!validComponents || typeof data.style !== 'string') {
                        throw new Error('Format template harus memiliki components dan style yang valid.');
                    }

                    editor.setComponents(data.components);
                    editor.setStyle(data.style);
                    alert('Template JSON berhasil di-import!');
                    setStatus('Template JSON berhasil diimport.');
                } catch (error) {
                    setStatus(error.message || 'File template JSON tidak valid.', true);
                } finally {
                    importFileInput.value = '';
                }
            });
            reader.addEventListener('error', () => {
                setStatus('File template JSON gagal dibaca.', true);
                importFileInput.value = '';
            });
            reader.readAsText(file);
        }

        exportButton.addEventListener('click', exportTemplate);
        importButton.addEventListener('click', () => importFileInput.click());
        importFileInput.addEventListener('change', () => {
            const file = importFileInput.files?.[0];
            if (file) importTemplate(file);
        });

        function closeTemplateLibrary() {
            templateModal.hidden = true;
            templatesButton.focus();
        }

        function renderTemplates(templates) {
            templateGrid.replaceChildren();
            if (templates.length === 0) {
                const empty = document.createElement('p');
                empty.className = 'template-empty';
                empty.textContent = 'Preset template belum tersedia.';
                templateGrid.append(empty);
                return;
            }

            const previews = ['fa-rocket', 'fa-layer-group', 'fa-map-location-dot'];
            templates.forEach((template, index) => {
                const card = document.createElement('article');
                card.className = 'template-card';
                const preview = document.createElement('div');
                preview.className = 'template-preview';
                preview.setAttribute('aria-hidden', 'true');
                const icon = document.createElement('i');
                icon.className = `fa-solid ${previews[index % previews.length]}`;
                preview.append(icon);

                const content = document.createElement('div');
                content.className = 'template-card-content';
                const title = document.createElement('h3');
                title.textContent = template.name;
                const description = document.createElement('p');
                description.textContent = template.description;
                const useButton = document.createElement('button');
                useButton.type = 'button';
                useButton.className = 'template-use';
                useButton.textContent = 'Gunakan Template';
                useButton.addEventListener('click', () => {
                    if (!Array.isArray(template.components) || typeof template.style !== 'string') {
                        setStatus('Preset template memiliki format yang tidak valid.', true);
                        return;
                    }
                    if (!window.confirm('Impor template ini ke canvas?')) return;
                    editor.setComponents(template.components);
                    editor.setStyle(template.style);
                    closeTemplateLibrary();
                    setStatus(`Template “${template.name}” berhasil dimuat.`);
                });
                content.append(title, description, useButton);
                card.append(preview, content);
                templateGrid.append(card);
            });
        }

        async function openTemplateLibrary() {
            templateModal.hidden = false;
            templateGrid.replaceChildren();
            const loading = document.createElement('p');
            loading.className = 'template-empty';
            loading.textContent = 'Memuat preset…';
            templateGrid.append(loading);
            closeTemplatesButton.focus();

            try {
                const response = await fetch('api.php?action=get_templates', {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                });
                const templates = await response.json();
                if (!response.ok || !Array.isArray(templates)) {
                    throw new Error(templates.message || 'Gagal memuat preset template.');
                }
                renderTemplates(templates);
            } catch (error) {
                const message = document.createElement('p');
                message.className = 'template-empty';
                message.textContent = error.message || 'Gagal memuat preset template.';
                templateGrid.replaceChildren(message);
            }
        }

        function openSaveTemplateModal() {
            saveTemplateModal.hidden = false;
            templateNameInput.focus();
        }

        function closeSaveTemplateModal() {
            saveTemplateModal.hidden = true;
            saveTemplateButton.focus();
        }

        saveTemplateButton.addEventListener('click', openSaveTemplateModal);
        cancelSaveTemplateButton.addEventListener('click', closeSaveTemplateModal);
        closeSaveTemplateButton.addEventListener('click', closeSaveTemplateModal);
        saveTemplateModal.addEventListener('click', event => {
            if (event.target === saveTemplateModal) closeSaveTemplateModal();
        });
        saveTemplateForm.addEventListener('submit', async event => {
            event.preventDefault();
            const name = templateNameInput.value.trim();
            if (!name) {
                templateNameInput.setCustomValidity('Nama template wajib diisi.');
                templateNameInput.reportValidity();
                return;
            }
            templateNameInput.setCustomValidity('');
            submitSaveTemplateButton.disabled = true;
            setStatus('Menyimpan custom template…');
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
                        description: templateDescriptionInput.value.trim(),
                        json: editor.getComponents().toJSON(),
                        css: editor.getCss()
                    })
                });
                const result = await response.json();
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Custom template gagal disimpan.');
                }
                saveTemplateForm.reset();
                closeSaveTemplateModal();
                setStatus('Custom Template berhasil disimpan!');
            } catch (error) {
                setStatus(error.message || 'Custom template gagal disimpan.', true);
            } finally {
                submitSaveTemplateButton.disabled = false;
            }
        });

        templatesButton.addEventListener('click', openTemplateLibrary);
        closeTemplatesButton.addEventListener('click', closeTemplateLibrary);
        templateModal.addEventListener('click', event => {
            if (event.target === templateModal) closeTemplateLibrary();
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                if (!templateModal.hidden) closeTemplateLibrary();
                if (!saveTemplateModal.hidden) closeSaveTemplateModal();
                if (!codeEditorModal.hidden) closeCodeEditor();
                if (!shortcutsModal.hidden) closeShortcutsModal();
                if (!pageManagerModal.hidden) closePageManager();
                setMoreMenu(false);
                if (isMobileLayout()) {
                    setLeftPanel(false);
                    setRightPanel(false);
                }
                if (typeof editor.AssetManager.close === 'function') editor.AssetManager.close();
                return;
            }

            if (!(event.ctrlKey || event.metaKey) || event.altKey) return;
            const key = event.key.toLowerCase();
            if (key === 'n' && event.shiftKey) {
                event.preventDefault();
                activateLeftTab('layers');
                return;
            }
            if (key === 's' || key === 'p' || key === 'e') {
                event.preventDefault();
                if (key === 's') {
                    saveButton.click();
                } else if (key === 'p') {
                    previewPage();
                } else if (codeEditorModal.hidden) {
                    openCodeEditor();
                } else {
                    closeCodeEditor();
                }
                return;
            }

            if (key === 'z') {
                event.preventDefault();
                if (event.shiftKey) um.redo();
                else um.undo();
            } else if (key === 'y' && event.ctrlKey && !event.metaKey) {
                event.preventDefault();
                um.redo();
            }
        });

        const iconOptions = [
            { id: 'fa-star', name: 'Bintang' },
            { id: 'fa-heart', name: 'Hati' },
            { id: 'fa-user', name: 'Pengguna' },
            { id: 'fa-check', name: 'Centang' },
            { id: 'fa-house', name: 'Rumah' },
            { id: 'fa-envelope', name: 'Amplop' },
            { id: 'fa-gear', name: 'Pengaturan' },
            { id: 'fa-thumbs-up', name: 'Suka' },
            { id: 'fa-bell', name: 'Lonceng' },
            { id: 'fa-download', name: 'Unduh' },
            { id: 'fa-cart-shopping', name: 'Keranjang' },
            { id: 'fa-share', name: 'Bagikan' }
        ];
        editor.DomComponents.addType('icon-widget', {
            isComponent: element => element.classList?.contains('icon-wrapper')
                ? { type: 'icon-widget' }
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
                    this.listenTo(this, 'change:iconName', this.updateIconClass);
                    this.updateIconClass();
                },
                updateIconClass() {
                    const iconName = iconOptions.some(option => option.id === this.get('iconName'))
                        ? this.get('iconName')
                        : 'fa-star';
                    this.find('i').forEach(icon => {
                        icon.setAttributes({
                            ...icon.getAttributes(),
                            class: `fa-solid ${iconName}`
                        });
                    });
                }
            }
        });

        const aosOptions = [
            { id: 'none', label: 'Tanpa animasi' },
            { id: 'fade-up', label: 'Fade Up' },
            { id: 'fade-down', label: 'Fade Down' },
            { id: 'fade-left', label: 'Fade Left' },
            { id: 'fade-right', label: 'Fade Right' },
            { id: 'zoom-in', label: 'Zoom In' },
            { id: 'zoom-out', label: 'Zoom Out' },
            { id: 'flip-left', label: 'Flip Left' },
            { id: 'flip-right', label: 'Flip Right' },
            { id: 'flip-up', label: 'Flip Up' },
            { id: 'flip-down', label: 'Flip Down' }
        ];
        const aosTrait = () => ({
            type: 'select',
            name: 'data-aos',
            label: 'Animasi AOS',
            options: aosOptions
        });

        const counterParseItems = value => {
            const items = String(value || '').split(/\r?\n/).map(line => line.trim()).filter(Boolean).map(line => {
                const parts = line.split('|').map(part => part.trim());
                return {
                    number: String(parts[0] || '0').replace(/[^\d]/g, '') || '0',
                    suffix: parts[1] || '',
                    label: parts.slice(2).join(' | ') || 'Statistik'
                };
            });
            return (items.length ? items : [{ number: '100', suffix: '+', label: 'Data' }]).slice(0, 8);
        };
        const counterItemComponents = items => items.map(item => ({
            tagName: 'div',
            attributes: { class: 'pb-counter-item' },
            components: [
                {
                    tagName: 'span',
                    attributes: { class: 'pb-counter-number' },
                    components: [
                        { tagName: 'span', attributes: { class: 'pb-counter-value', 'data-count-to': item.number }, components: [{ type: 'textnode', content: item.number }] },
                        { tagName: 'span', attributes: { class: 'pb-counter-suffix' }, components: [{ type: 'textnode', content: item.suffix }] }
                    ]
                },
                { tagName: 'p', attributes: { class: 'pb-counter-label' }, components: [{ type: 'textnode', content: item.label }] }
            ]
        }));

        editor.DomComponents.addType('typing-text', {
            isComponent: element => element.classList?.contains('pb-typing') ? { type: 'typing-text' } : false,
            model: {
                defaults: {
                    tagName: 'div',
                    attributes: {
                        class: 'pb-typing',
                        'data-typed-strings': 'membangun website\nmengelola konten\nmenumbuhkan komunitas',
                        'data-typed-speed': '55',
                        'data-typed-loop': '1'
                    },
                    droppable: false,
                    editable: false,
                    typedPrefix: 'Kami siap ',
                    typedWords: 'membangun website\nmengelola konten\nmenumbuhkan komunitas',
                    typedSpeed: 55,
                    typedLoop: true,
                    components: [
                        { tagName: 'span', attributes: { class: 'pb-typing-prefix' }, selectable: false, components: [{ type: 'textnode', content: 'Kami siap ' }] },
                        { tagName: 'span', attributes: { class: 'pb-typing-target' }, selectable: false, components: [{ type: 'textnode', content: 'membangun website' }] }
                    ],
                    traits: [
                        { type: 'text', name: 'typedPrefix', label: 'Teks Awal', changeProp: true },
                        { type: 'textarea', name: 'typedWords', label: 'Kata Bergantian (satu per baris)', changeProp: true },
                        { type: 'number', name: 'typedSpeed', label: 'Kecepatan (ms)', changeProp: true, min: 10, max: 500 },
                        { type: 'checkbox', name: 'typedLoop', label: 'Ulangi Animasi', changeProp: true },
                        aosTrait()
                    ]
                },
                init() {
                    this.syncTyping();
                    this.on('change:typedPrefix change:typedWords change:typedSpeed change:typedLoop', this.syncTyping);
                },
                syncTyping() {
                    const words = String(this.get('typedWords') || '').split(/\r?\n/).map(item => item.trim()).filter(Boolean);
                    const prefix = String(this.get('typedPrefix') || '');
                    const speed = Math.max(10, Math.min(500, parseInt(this.get('typedSpeed'), 10) || 55));
                    const loop = this.get('typedLoop') !== false;
                    this.setAttributes({
                        ...this.getAttributes(),
                        'data-typed-strings': words.join('\n'),
                        'data-typed-speed': String(speed),
                        'data-typed-loop': loop ? '1' : '0'
                    });
                    const prefixElement = this.find('.pb-typing-prefix')[0];
                    if (prefixElement) prefixElement.components([{ type: 'textnode', content: prefix }]);
                    const targetElement = this.find('.pb-typing-target')[0];
                    if (targetElement) targetElement.components([{ type: 'textnode', content: words[0] || '' }]);
                    scheduleCanvasRefresh();
                }
            }
        });

        editor.DomComponents.addType('counter-up', {
            isComponent: element => element.classList?.contains('pb-counter-up') ? { type: 'counter-up' } : false,
            model: {
                defaults: {
                    tagName: 'div',
                    attributes: { class: 'pb-counter-up', 'data-counter-duration': '1200' },
                    droppable: false,
                    editable: false,
                    counterItems: '120|+|Siswa Aktif\n15|+|Guru\n98|%|Kepuasan\n24|/7|Dukungan',
                    counterDuration: 1200,
                    components: [],
                    traits: [
                        { type: 'textarea', name: 'counterItems', label: 'Statistik (angka|suffix|label)', changeProp: true },
                        { type: 'number', name: 'counterDuration', label: 'Durasi Animasi (ms)', changeProp: true, min: 300, max: 5000 },
                        aosTrait()
                    ]
                },
                init() {
                    this.syncCounterItems();
                    this.on('change:counterItems change:counterDuration', this.syncCounterItems);
                },
                syncCounterItems() {
                    const duration = Math.max(300, Math.min(5000, parseInt(this.get('counterDuration'), 10) || 1200));
                    this.setAttributes({ ...this.getAttributes(), 'data-counter-duration': String(duration) });
                    this.components(counterItemComponents(counterParseItems(this.get('counterItems'))));
                    scheduleCanvasRefresh();
                }
            }
        });

        editor.DomComponents.addType('tiktok-feed', {
            isComponent: element => element.classList?.contains('pb-tiktok-feed') ? { type: 'tiktok-feed' } : false,
            model: {
                defaults: {
                    tagName: 'div',
                    attributes: { class: 'pb-tiktok-feed', 'data-tiktok-url': 'https://www.tiktok.com/@tiktok/video/7106594312292453675' },
                    droppable: false,
                    editable: false,
                    tiktokUrl: 'https://www.tiktok.com/@tiktok/video/7106594312292453675',
                    tiktokCaption: 'Ikuti konten terbaru kami di TikTok.',
                    components: [{
                        tagName: 'div',
                        attributes: { class: 'pb-tiktok-card' },
                        components: [
                            { tagName: 'div', attributes: { class: 'pb-tiktok-cover' }, components: [{ tagName: 'i', attributes: { class: 'fa-brands fa-tiktok', 'aria-hidden': 'true' } }] },
                            {
                                tagName: 'div',
                                attributes: { class: 'pb-tiktok-body' },
                                components: [
                                    { tagName: 'span', attributes: { class: 'pb-tiktok-eyebrow' }, components: [{ tagName: 'i', attributes: { class: 'fa-brands fa-tiktok', 'aria-hidden': 'true' } }, { type: 'textnode', content: 'TikTok' }] },
                                    { tagName: 'strong', attributes: { class: 'pb-tiktok-author' }, components: [{ type: 'textnode', content: '@tiktok' }] },
                                    { tagName: 'p', attributes: { class: 'pb-tiktok-caption' }, components: [{ type: 'textnode', content: 'Ikuti konten terbaru kami di TikTok.' }] },
                                    { tagName: 'a', type: 'link', attributes: { class: 'pb-tiktok-link', href: 'https://www.tiktok.com', target: '_blank', rel: 'noopener noreferrer' }, components: [{ tagName: 'i', attributes: { class: 'fa-brands fa-tiktok', 'aria-hidden': 'true' } }, { type: 'textnode', content: 'Buka di TikTok' }] }
                                ]
                            }
                        ]
                    }],
                    traits: [
                        { type: 'text', name: 'tiktokUrl', label: 'URL Video TikTok', changeProp: true },
                        { type: 'textarea', name: 'tiktokCaption', label: 'Teks Cadangan', changeProp: true },
                        aosTrait()
                    ]
                },
                init() {
                    this.syncTikTok();
                    this.on('change:tiktokUrl change:tiktokCaption', this.syncTikTok);
                },
                syncTikTok() {
                    const url = String(this.get('tiktokUrl') || '').trim();
                    const caption = String(this.get('tiktokCaption') || '').trim();
                    this.setAttributes({ ...this.getAttributes(), 'data-tiktok-url': url });
                    const link = this.find('.pb-tiktok-link')[0];
                    if (link) link.setAttributes({ ...link.getAttributes(), href: url || 'https://www.tiktok.com', target: '_blank', rel: 'noopener noreferrer' });
                    const captionElement = this.find('.pb-tiktok-caption')[0];
                    if (captionElement) captionElement.components([{ type: 'textnode', content: caption || 'Ikuti konten terbaru kami di TikTok.' }]);
                    if (this.getEl()) this.getEl().removeAttribute('data-pb-loaded-url');
                    scheduleCanvasRefresh();
                }
            }
        });

        editor.DomComponents.addType('floating-whatsapp', {
            isComponent: element => element.classList?.contains('pb-wa-float') ? { type: 'floating-whatsapp' } : false,
            model: {
                defaults: {
                    tagName: 'a',
                    attributes: { class: 'pb-wa-float', href: 'https://wa.me/6281234567890', target: '_blank', rel: 'noopener noreferrer', 'aria-label': 'Hubungi kami via WhatsApp' },
                    droppable: false,
                    editable: false,
                    waPhone: '6281234567890',
                    waMessage: 'Assalamu’alaikum, saya ingin bertanya.',
                    waLabel: 'Chat WhatsApp',
                    waPosition: 'right',
                    waCompact: false,
                    waColor: '#25d366',
                    components: [
                        { tagName: 'i', attributes: { class: 'fa-brands fa-whatsapp', 'aria-hidden': 'true' } },
                        { tagName: 'span', attributes: { class: 'pb-wa-float-label' }, components: [{ type: 'textnode', content: 'Chat WhatsApp' }] }
                    ],
                    traits: [
                        { type: 'text', name: 'waPhone', label: 'Nomor WhatsApp', changeProp: true },
                        { type: 'textarea', name: 'waMessage', label: 'Pesan Otomatis', changeProp: true },
                        { type: 'text', name: 'waLabel', label: 'Label Tombol', changeProp: true },
                        { type: 'select', name: 'waPosition', label: 'Posisi', changeProp: true, options: [{ id: 'right', label: 'Kanan Bawah' }, { id: 'left', label: 'Kiri Bawah' }] },
                        { type: 'checkbox', name: 'waCompact', label: 'Ikon Saja di Mobile', changeProp: true },
                        { type: 'color', name: 'waColor', label: 'Warna Tombol', changeProp: true },
                        aosTrait()
                    ]
                },
                init() {
                    this.syncWhatsapp();
                    this.on('change:waPhone change:waMessage change:waLabel change:waPosition change:waCompact change:waColor', this.syncWhatsapp);
                },
                syncWhatsapp() {
                    let phone = String(this.get('waPhone') || '').replace(/[^\d]/g, '');
                    if (phone.startsWith('0')) phone = '62' + phone.slice(1);
                    else if (phone.startsWith('8')) phone = '62' + phone;
                    const message = String(this.get('waMessage') || '').trim();
                    const label = String(this.get('waLabel') || '').trim() || 'Chat WhatsApp';
                    const position = this.get('waPosition') === 'left' ? 'left' : 'right';
                    const compact = this.get('waCompact') === true;
                    const color = /^#[0-9a-f]{3,8}$/i.test(String(this.get('waColor') || '')) ? String(this.get('waColor')) : '#25d366';
                    const href = phone !== '' ? `https://wa.me/${phone}${message !== '' ? '?text=' + encodeURIComponent(message) : ''}` : '#';
                    this.setAttributes({
                        ...this.getAttributes(),
                        class: `pb-wa-float pb-wa-${position}${compact ? ' pb-wa-compact' : ''}`,
                        href,
                        'aria-label': label
                    });
                    this.addStyle({ 'background-color': color });
                    const labelElement = this.find('.pb-wa-float-label')[0];
                    if (labelElement) labelElement.components([{ type: 'textnode', content: label }]);
                    scheduleCanvasRefresh();
                }
            }
        });

        editor.DomComponents.addType('shape-divider', {
            isComponent: element => element.classList?.contains('pb-wave-divider') ? { type: 'shape-divider' } : false,
            model: {
                defaults: {
                    tagName: 'div',
                    attributes: { class: 'pb-wave-divider pb-wave-bottom', 'data-wave-position': 'bottom', 'aria-hidden': 'true' },
                    droppable: false,
                    editable: false,
                    selectable: true,
                    wavePosition: 'bottom',
                    waveHeight: 110,
                    waveColor: '#0f766e',
                    style: { height: '110px', 'background-color': '#0f766e' },
                    traits: [
                        { type: 'select', name: 'wavePosition', label: 'Posisi Wave', changeProp: true, options: [{ id: 'bottom', label: 'Bottom (Bawah)' }, { id: 'top', label: 'Top (Atas)' }] },
                        { type: 'number', name: 'waveHeight', label: 'Tinggi (px)', changeProp: true, min: 20, max: 300 },
                        { type: 'color', name: 'waveColor', label: 'Warna Wave', changeProp: true },
                        aosTrait()
                    ]
                },
                init() {
                    this.syncWave();
                    this.on('change:wavePosition change:waveHeight change:waveColor', this.syncWave);
                },
                syncWave() {
                    const position = this.get('wavePosition') === 'top' ? 'top' : 'bottom';
                    const height = Math.max(20, Math.min(300, parseInt(this.get('waveHeight'), 10) || 110));
                    const color = /^#[0-9a-f]{3,8}$/i.test(String(this.get('waveColor') || '')) ? String(this.get('waveColor')) : '#0f766e';
                    this.setAttributes({ ...this.getAttributes(), class: `pb-wave-divider pb-wave-${position}`, 'data-wave-position': position });
                    this.addStyle({ height: `${height}px`, 'background-color': color });
                    scheduleCanvasRefresh();
                }
            }
        });

        const ensureAosTrait = component => {
            if (!component || !component.get || ['wrapper', 'textnode'].includes(component.get('type'))) return;
            const traits = component.get('traits');
            if (!traits || typeof traits.add !== 'function') return;
            if (traits.where({ name: 'data-aos' }).length > 0) return;
            traits.add(aosTrait());
        };
        editor.on('component:selected', ensureAosTrait);

        let canvasRefreshFrame = 0;
        let canvasObserver = null;
        let canvasObserverDocument = null;
        function scheduleCanvasRefresh() {
            if (canvasRefreshFrame) return;
            canvasRefreshFrame = window.requestAnimationFrame(() => {
                canvasRefreshFrame = 0;
                refreshCanvasEnhancements();
            });
        }
        function ensureCanvasObserver(canvasDocument) {
            if (!canvasDocument.body) return;
            if (canvasObserver && canvasObserverDocument === canvasDocument) return;
            if (canvasObserver) canvasObserver.disconnect();
            canvasObserver = new MutationObserver(scheduleCanvasRefresh);
            canvasObserver.observe(canvasDocument.body, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['data-aos', 'data-tiktok-url']
            });
            canvasObserverDocument = canvasDocument;
        }
        function refreshCanvasEnhancements() {
            let canvasDocument;
            try {
                canvasDocument = editor.Canvas.getDocument();
            } catch {
                return;
            }
            if (!canvasDocument) return;
            canvasDocument.querySelectorAll('[data-aos]').forEach(element => element.classList.add('aos-animate'));
            hydrateCanvasTikTok(canvasDocument);
            ensureCanvasObserver(canvasDocument);
        }
        function hydrateCanvasTikTok(canvasDocument) {
            canvasDocument.querySelectorAll('.pb-tiktok-feed').forEach(root => {
                const url = String(root.getAttribute('data-tiktok-url') || '').trim();
                if (!url || root.dataset.pbLoadedUrl === url || root.dataset.pbLoadingUrl === url) return;
                root.dataset.pbLoadingUrl = url;
                root.dataset.loading = '1';
                fetch(`api.php?action=tiktok_oembed&url=${encodeURIComponent(url)}`, {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                })
                    .then(response => response.json())
                    .then(result => {
                        if (!result.success || !result.data) throw new Error(result.message || 'Data TikTok tidak tersedia.');
                        if (!root.isConnected || root.getAttribute('data-tiktok-url') !== url) return;
                        fillTikTokCard(canvasDocument, root, result.data);
                        root.dataset.pbLoadedUrl = url;
                    })
                    .catch(() => {})
                    .finally(() => {
                        delete root.dataset.pbLoadingUrl;
                        root.removeAttribute('data-loading');
                    });
            });
        }
        function fillTikTokCard(canvasDocument, root, data) {
            const cover = root.querySelector('.pb-tiktok-cover');
            if (cover && data.thumbnail_url) {
                const image = canvasDocument.createElement('img');
                image.src = data.thumbnail_url;
                image.alt = data.title || 'Video TikTok';
                image.loading = 'lazy';
                cover.replaceChildren(image);
            }
            const author = root.querySelector('.pb-tiktok-author');
            if (author) author.textContent = data.author_name ? `@${data.author_name}` : 'TikTok';
            const caption = root.querySelector('.pb-tiktok-caption');
            if (caption && data.title) caption.textContent = data.title;
            const link = root.querySelector('.pb-tiktok-link');
            if (link && data.url) link.setAttribute('href', data.url);
        }

        const blocks = editor.BlockManager;
        const blockMeta = {
            'section-container': ['Visual', 'fa-layer-group'],
            'heading': ['Visual', 'fa-heading'],
            'paragraph': ['Visual', 'fa-paragraph'],
            'button': ['Visual', 'fa-hand-pointer'],
            'contact-form-block': ['Visual', 'fa-envelope-open-text'],
            'card-component': ['Visual', 'fa-id-card'],
            'accordion-faq': ['Visual', 'fa-bars-staggered'],
            'hero-modern': ['Visual', 'fa-rocket'],
            'feature-grid': ['Visual', 'fa-wand-magic-sparkles'],
            'cta-band': ['Visual', 'fa-bullhorn'],
            'stats-row': ['Visual', 'fa-chart-simple'],
            'testimonial-card': ['Visual', 'fa-quote-left'],
            'pricing-card': ['Visual', 'fa-tags'],
            'image-text-split': ['Visual', 'fa-image'],
            'checklist': ['Visual', 'fa-list-check'],
            'typing-text': ['Visual', 'fa-keyboard'],
            'counter-up': ['Visual', 'fa-arrow-up-1-9'],
            'image': ['Media', 'fa-image'],
            'fontawesome-icon': ['Media', 'fa-font-awesome'],
            'video-player': ['Media', 'fa-video'],
            'gallery-grid': ['Media', 'fa-images'],
            'social-links': ['Media', 'fa-share-nodes'],
            'tiktok-feed': ['Media', 'fa-tiktok'],
            'two-column-grid': ['Layout', 'fa-table-columns'],
            'spacer-divider': ['Layout', 'fa-arrows-up-down'],
            'footer-modern': ['Layout', 'fa-window-maximize'],
            'floating-whatsapp': ['Layout', 'fa-whatsapp'],
            'shape-divider': ['Layout', 'fa-water']
        };
        const addBlock = (id, config = {}) => {
            const meta = blockMeta[id] || ['Visual', 'fa-cube'];
            const brandIcon = ['fa-tiktok', 'fa-whatsapp', 'fa-font-awesome'].includes(meta[1]);
            const iconClass = brandIcon ? 'fa-brands' : 'fa-solid';
            blocks.add(id, {
                ...config,
                category: meta[0],
                media: `<i class="${iconClass} ${meta[1]}" aria-hidden="true"></i>`
            });
        };

        const deviceButtons = document.querySelectorAll('.device-switcher button[data-device]');
        deviceButtons.forEach(button => {
            button.addEventListener('click', () => {
                const device = button.dataset.device;
                if (!device) return;
                editor.setDevice(device);
                deviceButtons.forEach(item => item.setAttribute('aria-pressed', String(item === button)));
                if (device === 'Desktop') applyZoom(100);
                else fitCanvas();
            });
        });

        editor.on('asset:upload:error', () => setStatus('Gagal mengunggah gambar.', true));
        editor.on('asset:upload:response', response => {
            if (response && response.message) setStatus(response.message, true);
        });

        async function loadAssets() {
            try {
                const response = await fetch('api.php?action=get_assets', {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                });
                const assets = await response.json();
                if (!response.ok || !Array.isArray(assets)) {
                    throw new Error(assets.message || 'Gagal memuat daftar gambar.');
                }
                editor.AssetManager.add(assets);
            } catch (error) {
                setStatus(error.message || 'Gagal memuat daftar gambar.', true);
            }
        }

        loadAssets();

        addBlock('section-container', {
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
        addBlock('heading', {
            label: 'Heading',
            category: 'Dasar',
            content: { tagName: 'h2', type: 'text', content: 'Judul baru' }
        });
        addBlock('paragraph', {
            label: 'Text Paragraph',
            category: 'Dasar',
            content: { tagName: 'p', type: 'text', content: 'Tulis paragraf di sini.' }
        });
        addBlock('button', {
            label: 'Button',
            category: 'Dasar',
            content: { tagName: 'a', attributes: { class: 'button', href: '#' }, content: 'Tombol' }
        });
        addBlock('image', {
            label: 'Image',
            category: 'Dasar',
            content: { type: 'image', attributes: { alt: 'Gambar halaman' }, style: { width: '100%', height: 'auto' } }
        });
        addBlock('contact-form-block', {
            label: 'Form Kontak / Feedback',
            category: 'Formulir',
            content: {
                tagName: 'form',
                attributes: { action: 'api.php?action=submit_form', method: 'POST', class: 'custom-form', id: 'contact-form' },
                style: { padding: '20px', background: '#ffffff', 'border-radius': '8px', 'box-shadow': '0 4px 6px -1px rgba(0,0,0,0.1)' },
                components: [
                    { tagName: 'input', attributes: { type: 'hidden', name: 'page_slug', value: 'home' } },
                    { tagName: 'div', style: { 'margin-bottom': '15px' }, components: [
                        { tagName: 'label', content: 'Nama Lengkap', style: { display: 'block', 'margin-bottom': '5px', 'font-weight': 'bold' } },
                        { tagName: 'input', attributes: { type: 'text', name: 'name', required: true, placeholder: 'Nama Anda', maxlength: '200' }, style: { width: '100%', padding: '10px', border: '1px solid #ccc', 'border-radius': '4px', 'box-sizing': 'border-box' } }
                    ] },
                    { tagName: 'div', style: { 'margin-bottom': '15px' }, components: [
                        { tagName: 'label', content: 'Alamat Email', style: { display: 'block', 'margin-bottom': '5px', 'font-weight': 'bold' } },
                        { tagName: 'input', attributes: { type: 'email', name: 'email', required: true, placeholder: 'email@domain.com', maxlength: '254' }, style: { width: '100%', padding: '10px', border: '1px solid #ccc', 'border-radius': '4px', 'box-sizing': 'border-box' } }
                    ] },
                    { tagName: 'div', style: { 'margin-bottom': '15px' }, components: [
                        { tagName: 'label', content: 'Pesan', style: { display: 'block', 'margin-bottom': '5px', 'font-weight': 'bold' } },
                        { tagName: 'textarea', attributes: { name: 'message', rows: '4', required: true, placeholder: 'Tuliskan pesan Anda...', maxlength: '10000' }, style: { width: '100%', padding: '10px', border: '1px solid #ccc', 'border-radius': '4px', 'box-sizing': 'border-box' } }
                    ] },
                    { tagName: 'div', attributes: { class: 'form-honeypot', 'aria-hidden': 'true' }, style: { position: 'absolute', left: '-10000px', width: '1px', height: '1px', overflow: 'hidden' }, components: [
                        { tagName: 'label', content: 'Website', components: [
                            { tagName: 'input', attributes: { type: 'text', name: 'website', tabindex: '-1', autocomplete: 'off' } }
                        ] }
                    ] },
                    { tagName: 'button', attributes: { type: 'submit' }, content: 'Kirim Pesan', style: { background: '#3b82f6', color: 'white', border: 'none', padding: '12px 24px', 'border-radius': '4px', cursor: 'pointer', 'font-weight': 'bold', width: '100%' } },
                    { tagName: 'p', attributes: { class: 'form-feedback', role: 'status', 'aria-live': 'polite' } }
                ]
            }
        });
        addBlock('fontawesome-icon', {
            label: 'Ikon FontAwesome',
            category: 'Media',
            content: '<div class="icon-wrapper" style="padding: 10px; text-align: center;"><i class="fa-solid fa-star" style="font-size: 48px; color: #3b82f6;"></i></div>'
        });
        addBlock('card-component', {
            label: 'Card Component',
            category: 'Komponen',
            content: {
                tagName: 'article',
                attributes: { class: 'pb-card' },
                style: {
                    'max-width': '360px',
                    overflow: 'hidden',
                    background: '#ffffff',
                    border: '1px solid #e2e8f0',
                    'border-radius': '12px',
                    'box-shadow': '0 8px 24px rgba(15, 23, 42, 0.08)'
                },
                components: [
                    {
                        tagName: 'img',
                        attributes: { src: 'https://placehold.co/720x400?text=Gambar', alt: 'Gambar kartu' },
                        style: { display: 'block', width: '100%', height: 'auto', 'object-fit': 'cover' }
                    },
                    {
                        tagName: 'div',
                        attributes: { class: 'pb-card-content' },
                        style: { padding: '22px' },
                        components: [
                            { tagName: 'h3', type: 'text', content: 'Judul kartu', style: { margin: '0 0 10px', 'font-size': '22px', color: '#172033' } },
                            { tagName: 'p', type: 'text', content: 'Tambahkan deskripsi singkat untuk menjelaskan isi kartu ini.', style: { margin: '0 0 18px', color: '#526274', 'line-height': '1.6' } },
                            {
                                tagName: 'a',
                                type: 'link',
                                attributes: { href: '#', class: 'pb-card-cta' },
                                style: { display: 'inline-block', padding: '10px 16px', color: '#ffffff', background: '#0f9f78', 'border-radius': '7px', 'text-decoration': 'none', 'font-weight': '700' },
                                components: [{ type: 'textnode', content: 'Selengkapnya' }]
                            }
                        ]
                    }
                ]
            }
        });
        addBlock('accordion-faq', {
            label: 'Accordion / FAQ',
            category: 'Komponen',
            content: {
                tagName: 'div',
                attributes: { class: 'pb-faq-list' },
                style: { display: 'grid', gap: '10px', width: '100%' },
                components: [
                    {
                        tagName: 'details',
                        attributes: { class: 'pb-faq-item', open: 'open' },
                        style: { padding: '16px 18px', background: '#ffffff', border: '1px solid #d9e2ec', 'border-radius': '8px' },
                        components: [
                            { tagName: 'summary', type: 'text', content: 'Apa pertanyaan yang sering diajukan?', style: { cursor: 'pointer', color: '#172033', 'font-weight': '700' } },
                            { tagName: 'p', type: 'text', content: 'Tulis jawaban singkat dan jelas di sini.', style: { margin: '12px 0 0', color: '#526274', 'line-height': '1.6' } }
                        ]
                    },
                    {
                        tagName: 'details',
                        attributes: { class: 'pb-faq-item' },
                        style: { padding: '16px 18px', background: '#ffffff', border: '1px solid #d9e2ec', 'border-radius': '8px' },
                        components: [
                            { tagName: 'summary', type: 'text', content: 'Bagaimana cara mendapatkan informasi lebih lanjut?', style: { cursor: 'pointer', color: '#172033', 'font-weight': '700' } },
                            { tagName: 'p', type: 'text', content: 'Tambahkan informasi atau kontak yang relevan di sini.', style: { margin: '12px 0 0', color: '#526274', 'line-height': '1.6' } }
                        ]
                    }
                ]
            }
        });
        addBlock('two-column-grid', {
            label: 'Grid 2 Kolom',
            category: 'Layout',
            content: {
                tagName: 'section',
                attributes: { class: 'pb-grid-two' },
                style: { display: 'grid', 'grid-template-columns': 'repeat(2, minmax(0, 1fr))', gap: '24px', width: '100%' },
                components: [
                    { tagName: 'div', attributes: { class: 'pb-grid-column' }, style: { padding: '24px', background: '#eaf4f1', 'border-radius': '10px' }, components: [{ tagName: 'h3', type: 'text', content: 'Kolom pertama' }, { tagName: 'p', type: 'text', content: 'Tambahkan konten kolom pertama di sini.' }] },
                    { tagName: 'div', attributes: { class: 'pb-grid-column' }, style: { padding: '24px', background: '#f1f5f9', 'border-radius': '10px' }, components: [{ tagName: 'h3', type: 'text', content: 'Kolom kedua' }, { tagName: 'p', type: 'text', content: 'Tambahkan konten kolom kedua di sini.' }] }
                ]
            }
        });
        addBlock('video-player', {
            label: 'Video Player',
            category: 'Media',
            content: {
                tagName: 'div',
                attributes: { class: 'pb-video-wrapper' },
                style: { width: '100%', 'max-width': '800px', 'aspect-ratio': '16 / 9', overflow: 'hidden', background: '#0f172a', 'border-radius': '10px' },
                components: [{
                    tagName: 'video',
                    attributes: {
                        controls: 'controls',
                        preload: 'metadata',
                        'aria-label': 'Pemutar video'
                    },
                    style: { display: 'block', width: '100%', height: '100%', 'object-fit': 'contain' },
                    components: [{
                        tagName: 'source',
                        attributes: {
                            src: 'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',
                            type: 'video/mp4'
                        }
                    }]
                }]
            }
        });
        addBlock('hero-modern', {
            label: 'Hero Modern',
            category: 'Hero & CTA',
            content: {
                tagName: 'section',
                attributes: { class: 'pb-hero-modern' },
                style: { padding: 'clamp(48px, 8vw, 96px) 6%', background: 'linear-gradient(135deg, #eef2ff 0%, #ecfeff 100%)', 'font-family': 'Inter, system-ui, sans-serif', color: '#0f172a' },
                components: [{
                    tagName: 'div',
                    style: { 'max-width': '1180px', margin: '0 auto', display: 'grid', 'grid-template-columns': 'repeat(auto-fit, minmax(280px, 1fr))', gap: 'clamp(28px, 5vw, 64px)', 'align-items': 'center' },
                    components: [{
                        tagName: 'div',
                        components: [
                            { tagName: 'span', style: { display: 'inline-block', padding: '7px 14px', 'border-radius': '999px', background: '#e0e7ff', color: '#4338ca', 'font-size': '11px', 'font-weight': '800', 'letter-spacing': '0.12em', 'text-transform': 'uppercase' }, content: 'Solusi Digital' },
                            { tagName: 'h1', style: { margin: '18px 0 16px', 'font-size': 'clamp(34px, 5vw, 60px)', 'line-height': '1.05', 'letter-spacing': '-0.04em' }, content: 'Bangun pengalaman web yang berkesan' },
                            { tagName: 'p', style: { margin: '0 0 26px', 'max-width': '560px', color: '#526274', 'font-size': 'clamp(15px, 2vw, 18px)', 'line-height': '1.7' }, content: 'Desain cepat, responsif, dan mudah dikelola langsung dari browser.' },
                            { tagName: 'div', style: { display: 'flex', 'flex-wrap': 'wrap', gap: '12px' }, components: [
                                { tagName: 'a', type: 'link', attributes: { href: '#kontak' }, style: { display: 'inline-block', padding: '14px 22px', 'border-radius': '10px', background: '#4f46e5', color: '#ffffff', 'font-weight': '700', 'text-decoration': 'none', 'box-shadow': '0 12px 24px rgba(79,70,229,.28)' }, components: [{ type: 'textnode', content: 'Mulai Sekarang' }] },
                                { tagName: 'a', type: 'link', attributes: { href: '#fitur' }, style: { display: 'inline-block', padding: '14px 22px', 'border-radius': '10px', border: '1px solid #c7d2fe', color: '#4338ca', 'font-weight': '700', 'text-decoration': 'none', background: 'rgba(255,255,255,.7)' }, components: [{ type: 'textnode', content: 'Pelajari Fitur' }] }
                            ] }
                        ]
                    }, {
                        tagName: 'img',
                        attributes: { src: 'https://placehold.co/960x720/6366f1/ffffff?text=Preview+Website', alt: 'Pratinjau website' },
                        style: { display: 'block', width: '100%', 'border-radius': '20px', 'box-shadow': '0 30px 70px rgba(15,23,42,.18)', 'object-fit': 'cover' }
                    }]
                }]
            }
        });
        addBlock('feature-grid', {
            label: 'Grid Fitur',
            category: 'Konten',
            content: {
                tagName: 'section',
                attributes: { class: 'pb-feature-grid' },
                style: { padding: 'clamp(44px, 7vw, 84px) 6%', background: '#ffffff', 'font-family': 'Inter, system-ui, sans-serif', color: '#0f172a' },
                components: [{
                    tagName: 'div',
                    style: { 'max-width': '1120px', margin: '0 auto' },
                    components: [
                        { tagName: 'div', style: { 'max-width': '640px', margin: '0 auto 42px', 'text-align': 'center' }, components: [
                            { tagName: 'p', style: { margin: '0 0 10px', color: '#4f46e5', 'font-size': '11px', 'font-weight': '800', 'letter-spacing': '0.14em', 'text-transform': 'uppercase' }, content: 'Kenapa Kami' },
                            { tagName: 'h2', style: { margin: '0 0 12px', 'font-size': 'clamp(28px, 4vw, 42px)', 'letter-spacing': '-0.03em' }, content: 'Semua yang Anda butuhkan' },
                            { tagName: 'p', style: { margin: 0, color: '#64748b', 'font-size': '16px', 'line-height': '1.7' }, content: 'Solusi praktis untuk membantu bisnis Anda tumbuh lebih cepat.' }
                        ] },
                        { tagName: 'div', style: { display: 'grid', 'grid-template-columns': 'repeat(auto-fit, minmax(240px, 1fr))', gap: '20px' }, components: [
                            { tagName: 'article', style: { padding: '28px', border: '1px solid #e2e8f0', 'border-radius': '16px', background: '#ffffff', 'box-shadow': '0 14px 34px rgba(15,23,42,.06)' }, components: [
                                { tagName: 'i', attributes: { class: 'fa-solid fa-bolt', 'aria-hidden': 'true' }, style: { display: 'grid', 'place-items': 'center', width: '50px', height: '50px', 'border-radius': '13px', background: '#eef2ff', color: '#4f46e5', 'font-size': '20px' } },
                                { tagName: 'h3', style: { margin: '18px 0 8px', 'font-size': '19px' }, content: 'Cepat & Efisien' },
                                { tagName: 'p', style: { margin: 0, color: '#64748b', 'line-height': '1.7' }, content: 'Alur kerja ringkas yang menghemat waktu dan tenaga Anda.' }
                            ] },
                            { tagName: 'article', style: { padding: '28px', border: '1px solid #e2e8f0', 'border-radius': '16px', background: '#ffffff', 'box-shadow': '0 14px 34px rgba(15,23,42,.06)' }, components: [
                                { tagName: 'i', attributes: { class: 'fa-solid fa-shield-halved', 'aria-hidden': 'true' }, style: { display: 'grid', 'place-items': 'center', width: '50px', height: '50px', 'border-radius': '13px', background: '#ecfeff', color: '#0891b2', 'font-size': '20px' } },
                                { tagName: 'h3', style: { margin: '18px 0 8px', 'font-size': '19px' }, content: 'Aman & Tepercaya' },
                                { tagName: 'p', style: { margin: 0, color: '#64748b', 'line-height': '1.7' }, content: 'Dukungan dan layanan yang dapat Anda andalkan setiap hari.' }
                            ] },
                            { tagName: 'article', style: { padding: '28px', border: '1px solid #e2e8f0', 'border-radius': '16px', background: '#ffffff', 'box-shadow': '0 14px 34px rgba(15,23,42,.06)' }, components: [
                                { tagName: 'i', attributes: { class: 'fa-solid fa-headset', 'aria-hidden': 'true' }, style: { display: 'grid', 'place-items': 'center', width: '50px', height: '50px', 'border-radius': '13px', background: '#ecfdf5', color: '#059669', 'font-size': '20px' } },
                                { tagName: 'h3', style: { margin: '18px 0 8px', 'font-size': '19px' }, content: 'Dukungan Ramah' },
                                { tagName: 'p', style: { margin: 0, color: '#64748b', 'line-height': '1.7' }, content: 'Tim kami siap membantu saat Anda membutuhkan.' }
                            ] }
                        ] }
                    ]
                }]
            }
        });
        addBlock('cta-band', {
            label: 'CTA Banner',
            category: 'Hero & CTA',
            content: {
                tagName: 'section',
                attributes: { class: 'pb-cta-band' },
                style: { padding: 'clamp(36px, 6vw, 64px) 6%', background: 'linear-gradient(135deg, #312e81, #4f46e5 60%, #0891b2)', color: '#ffffff', 'font-family': 'Inter, system-ui, sans-serif' },
                components: [{
                    tagName: 'div',
                    style: { 'max-width': '1080px', margin: '0 auto', display: 'flex', 'flex-wrap': 'wrap', gap: '24px', 'align-items': 'center', 'justify-content': 'space-between' },
                    components: [
                        { tagName: 'div', style: { flex: '1 1 320px' }, components: [
                            { tagName: 'h2', style: { margin: '0 0 10px', 'font-size': 'clamp(26px, 4vw, 38px)', 'letter-spacing': '-0.03em' }, content: 'Siap memulai proyek Anda?' },
                            { tagName: 'p', style: { margin: 0, color: '#c7d2fe', 'font-size': '16px', 'line-height': '1.7' }, content: 'Hubungi kami hari ini dan wujudkan ide Anda bersama tim kami.' }
                        ] },
                        { tagName: 'a', type: 'link', attributes: { href: '#kontak' }, style: { display: 'inline-block', padding: '15px 24px', 'border-radius': '11px', background: '#ffffff', color: '#312e81', 'font-weight': '800', 'text-decoration': 'none', 'box-shadow': '0 16px 34px rgba(2,6,23,.24)' }, components: [{ type: 'textnode', content: 'Hubungi Kami' }] }
                    ]
                }]
            }
        });
        addBlock('stats-row', {
            label: 'Statistik',
            category: 'Konten',
            content: {
                tagName: 'section',
                attributes: { class: 'pb-stats' },
                style: { padding: 'clamp(36px, 6vw, 60px) 6%', background: '#f8fafc', 'font-family': 'Inter, system-ui, sans-serif', color: '#0f172a' },
                components: [{
                    tagName: 'div',
                    style: { 'max-width': '1080px', margin: '0 auto', display: 'grid', 'grid-template-columns': 'repeat(auto-fit, minmax(160px, 1fr))', gap: '18px' },
                    components: ['120+', '15 Tahun', '98%', '24/7'].map((value, index) => ({
                        tagName: 'div',
                        style: { padding: '26px 20px', border: '1px solid #e2e8f0', 'border-radius': '16px', background: '#ffffff', 'text-align': 'center' },
                        components: [
                            { tagName: 'h3', style: { margin: '0 0 6px', color: '#4f46e5', 'font-size': 'clamp(28px, 4vw, 40px)', 'letter-spacing': '-0.03em' }, content: value },
                            { tagName: 'p', style: { margin: 0, color: '#64748b', 'font-size': '13px', 'font-weight': '600' }, content: ['Proyek Selesai', 'Pengalaman', 'Kepuasan Klien', 'Dukungan'][index] }
                        ]
                    }))
                }]
            }
        });
        addBlock('testimonial-card', {
            label: 'Testimoni',
            category: 'Konten',
            content: {
                tagName: 'section',
                attributes: { class: 'pb-testimonial' },
                style: { padding: 'clamp(40px, 6vw, 70px) 6%', background: '#ffffff', 'font-family': 'Inter, system-ui, sans-serif', color: '#0f172a' },
                components: [{
                    tagName: 'figure',
                    style: { 'max-width': '760px', margin: '0 auto', padding: 'clamp(28px, 5vw, 48px)', border: '1px solid #e2e8f0', 'border-radius': '22px', background: 'linear-gradient(135deg, #f8fafc, #eef2ff)', 'box-shadow': '0 24px 60px rgba(15,23,42,.08)' },
                    components: [
                        { tagName: 'i', attributes: { class: 'fa-solid fa-quote-left', 'aria-hidden': 'true' }, style: { color: '#6366f1', 'font-size': '26px' } },
                        { tagName: 'blockquote', style: { margin: '18px 0 24px', 'font-size': 'clamp(18px, 2.4vw, 24px)', 'line-height': '1.6', 'font-weight': '600' }, content: '“Pelayanan sangat cepat dan hasilnya melebihi ekspektasi kami. Timnya responsif dan profesional.”' },
                        { tagName: 'figcaption', style: { display: 'flex', 'align-items': 'center', gap: '12px' }, components: [
                            { tagName: 'div', style: { display: 'grid', 'place-items': 'center', width: '46px', height: '46px', 'border-radius': '999px', background: '#4f46e5', color: '#ffffff', 'font-weight': '800' }, content: 'A' },
                            { tagName: 'div', components: [
                                { tagName: 'strong', style: { display: 'block', 'font-size': '14px' }, content: 'Ahmad Fauzi' },
                                { tagName: 'span', style: { color: '#64748b', 'font-size': '12px' }, content: 'Pemilik Usaha' }
                            ] }
                        ] }
                    ]
                }]
            }
        });
        addBlock('pricing-card', {
            label: 'Kartu Harga',
            category: 'Konten',
            content: {
                tagName: 'section',
                attributes: { class: 'pb-pricing' },
                style: { padding: 'clamp(40px, 6vw, 72px) 6%', background: '#f8fafc', 'font-family': 'Inter, system-ui, sans-serif', color: '#0f172a' },
                components: [{
                    tagName: 'div',
                    style: { 'max-width': '420px', margin: '0 auto', padding: 'clamp(26px, 4vw, 36px)', border: '1px solid #c7d2fe', 'border-radius': '22px', background: '#ffffff', 'box-shadow': '0 26px 64px rgba(79,70,229,.14)' },
                    components: [
                        { tagName: 'span', style: { display: 'inline-block', padding: '6px 12px', 'border-radius': '999px', background: '#eef2ff', color: '#4338ca', 'font-size': '11px', 'font-weight': '800', 'letter-spacing': '0.1em', 'text-transform': 'uppercase' }, content: 'Populer' },
                        { tagName: 'h3', style: { margin: '16px 0 6px', 'font-size': '22px' }, content: 'Paket Bisnis' },
                        { tagName: 'p', style: { margin: '0 0 18px', color: '#64748b', 'line-height': '1.6' }, content: 'Cocok untuk bisnis yang sedang berkembang.' },
                        { tagName: 'div', style: { margin: '0 0 20px', 'font-size': '42px', 'font-weight': '800', 'letter-spacing': '-0.04em' }, content: 'Rp499rb' },
                        { tagName: 'ul', style: { display: 'grid', gap: '10px', margin: '0 0 24px', padding: 0, 'list-style': 'none', color: '#475569' }, components: [
                            { tagName: 'li', content: '5 Halaman Website' },
                            { tagName: 'li', content: 'Desain Responsif' },
                            { tagName: 'li', content: 'Dukungan 30 Hari' },
                            { tagName: 'li', content: 'Optimasi SEO Dasar' }
                        ] },
                        { tagName: 'a', type: 'link', attributes: { href: '#kontak' }, style: { display: 'block', padding: '14px 20px', 'border-radius': '10px', background: '#4f46e5', color: '#ffffff', 'font-weight': '800', 'text-align': 'center', 'text-decoration': 'none' }, components: [{ type: 'textnode', content: 'Pilih Paket' }] }
                    ]
                }]
            }
        });
        addBlock('image-text-split', {
            label: 'Gambar + Teks',
            category: 'Konten',
            content: {
                tagName: 'section',
                attributes: { class: 'pb-image-text' },
                style: { padding: 'clamp(40px, 7vw, 84px) 6%', background: '#ffffff', 'font-family': 'Inter, system-ui, sans-serif', color: '#0f172a' },
                components: [{
                    tagName: 'div',
                    style: { 'max-width': '1120px', margin: '0 auto', display: 'grid', 'grid-template-columns': 'repeat(auto-fit, minmax(280px, 1fr))', gap: 'clamp(28px, 5vw, 56px)', 'align-items': 'center' },
                    components: [
                        { tagName: 'img', attributes: { src: 'https://placehold.co/900x680/e0e7ff/4338ca?text=Gambar+Konten', alt: 'Gambar konten' }, style: { display: 'block', width: '100%', 'border-radius': '20px', 'box-shadow': '0 24px 60px rgba(15,23,42,.14)' } },
                        { tagName: 'div', components: [
                            { tagName: 'p', style: { margin: '0 0 10px', color: '#4f46e5', 'font-size': '11px', 'font-weight': '800', 'letter-spacing': '0.14em', 'text-transform': 'uppercase' }, content: 'Tentang Kami' },
                            { tagName: 'h2', style: { margin: '0 0 14px', 'font-size': 'clamp(28px, 4vw, 40px)', 'letter-spacing': '-0.03em' }, content: 'Cerita di balik layanan kami' },
                            { tagName: 'p', style: { margin: '0 0 14px', color: '#526274', 'font-size': '16px', 'line-height': '1.75' }, content: 'Kami percaya bahwa setiap bisnis layak memiliki kehadiran digital yang kuat, cepat, dan mudah dikelola.' },
                            { tagName: 'p', style: { margin: '0 0 22px', color: '#526274', 'font-size': '16px', 'line-height': '1.75' }, content: 'Tim kami menggabungkan desain modern dengan teknologi yang andal.' },
                            { tagName: 'a', type: 'link', attributes: { href: '#kontak' }, style: { display: 'inline-block', padding: '13px 20px', 'border-radius': '10px', background: '#0f172a', color: '#ffffff', 'font-weight': '700', 'text-decoration': 'none' }, components: [{ type: 'textnode', content: 'Kenali Kami' }] }
                        ] }
                    ]
                }]
            }
        });
        addBlock('gallery-grid', {
            label: 'Galeri Gambar',
            category: 'Media',
            content: {
                tagName: 'section',
                attributes: { class: 'pb-gallery' },
                style: { padding: 'clamp(36px, 6vw, 64px) 6%', background: '#ffffff', 'font-family': 'Inter, system-ui, sans-serif' },
                components: [{
                    tagName: 'div',
                    style: { 'max-width': '1120px', margin: '0 auto', display: 'grid', 'grid-template-columns': 'repeat(auto-fit, minmax(190px, 1fr))', gap: '14px' },
                    components: [1, 2, 3, 4, 5, 6].map(index => ({
                        tagName: 'img',
                        attributes: { src: `https://placehold.co/600x600/e2e8f0/475569?text=Foto+${index}`, alt: `Foto ${index}` },
                        style: { display: 'block', width: '100%', 'aspect-ratio': '1 / 1', 'object-fit': 'cover', 'border-radius': '14px' }
                    }))
                }]
            }
        });
        addBlock('checklist', {
            label: 'Daftar Centang',
            category: 'Konten',
            content: {
                tagName: 'ul',
                attributes: { class: 'pb-checklist' },
                style: { display: 'grid', gap: '12px', margin: 0, padding: '4px 0', 'list-style': 'none', 'font-family': 'Inter, system-ui, sans-serif', color: '#334155' },
                components: ['Desain responsif di semua perangkat', 'Mudah diperbarui tanpa coding', 'Performa cepat dan ringan', 'Dukungan teknis yang ramah'].map(item => ({
                    tagName: 'li',
                    style: { display: 'flex', 'align-items': 'flex-start', gap: '10px', 'line-height': '1.6' },
                    components: [
                        { tagName: 'i', attributes: { class: 'fa-solid fa-circle-check', 'aria-hidden': 'true' }, style: { color: '#10b981', 'margin-top': '3px' } },
                        { tagName: 'span', components: [{ type: 'textnode', content: item }] }
                    ]
                }))
            }
        });
        addBlock('social-links', {
            label: 'Tautan Sosial',
            category: 'Media',
            content: {
                tagName: 'div',
                attributes: { class: 'pb-social' },
                style: { display: 'flex', 'flex-wrap': 'wrap', gap: '10px', 'font-family': 'Inter, system-ui, sans-serif' },
                components: [
                    { tagName: 'a', type: 'link', attributes: { href: 'https://facebook.com', target: '_blank', rel: 'noopener noreferrer' }, style: { display: 'grid', 'place-items': 'center', width: '44px', height: '44px', 'border-radius': '12px', background: '#1877f2', color: '#ffffff', 'text-decoration': 'none' }, components: [{ tagName: 'i', attributes: { class: 'fa-brands fa-facebook-f', 'aria-hidden': 'true' } }] },
                    { tagName: 'a', type: 'link', attributes: { href: 'https://instagram.com', target: '_blank', rel: 'noopener noreferrer' }, style: { display: 'grid', 'place-items': 'center', width: '44px', height: '44px', 'border-radius': '12px', background: '#e1306c', color: '#ffffff', 'text-decoration': 'none' }, components: [{ tagName: 'i', attributes: { class: 'fa-brands fa-instagram', 'aria-hidden': 'true' } }] },
                    { tagName: 'a', type: 'link', attributes: { href: 'https://wa.me/6281234567890', target: '_blank', rel: 'noopener noreferrer' }, style: { display: 'grid', 'place-items': 'center', width: '44px', height: '44px', 'border-radius': '12px', background: '#25d366', color: '#ffffff', 'text-decoration': 'none' }, components: [{ tagName: 'i', attributes: { class: 'fa-brands fa-whatsapp', 'aria-hidden': 'true' } }] }
                ]
            }
        });
        addBlock('spacer-divider', {
            label: 'Pemisah / Spacer',
            category: 'Layout',
            content: {
                tagName: 'div',
                attributes: { class: 'pb-spacer' },
                style: { padding: '18px 6%', 'font-family': 'Inter, system-ui, sans-serif' },
                components: [{ tagName: 'hr', style: { margin: 0, border: 0, 'border-top': '1px solid #e2e8f0' } }]
            }
        });
        addBlock('footer-modern', {
            label: 'Footer Modern',
            category: 'Layout',
            content: {
                tagName: 'footer',
                attributes: { class: 'pb-footer' },
                style: { padding: 'clamp(40px, 6vw, 64px) 6% 28px', background: '#0f172a', color: '#cbd5e1', 'font-family': 'Inter, system-ui, sans-serif' },
                components: [{
                    tagName: 'div',
                    style: { 'max-width': '1120px', margin: '0 auto' },
                    components: [
                        { tagName: 'div', style: { display: 'grid', 'grid-template-columns': 'repeat(auto-fit, minmax(220px, 1fr))', gap: '28px', 'margin-bottom': '32px' }, components: [
                            { tagName: 'div', components: [
                                { tagName: 'h3', style: { margin: '0 0 10px', color: '#ffffff', 'font-size': '20px' }, content: 'Nama Brand' },
                                { tagName: 'p', style: { margin: 0, color: '#94a3b8', 'line-height': '1.7' }, content: 'Solusi digital terpercaya untuk kebutuhan bisnis Anda.' }
                            ] },
                            { tagName: 'div', components: [
                                { tagName: 'h3', style: { margin: '0 0 10px', color: '#ffffff', 'font-size': '14px' }, content: 'Navigasi' },
                                { tagName: 'ul', style: { display: 'grid', gap: '8px', margin: 0, padding: 0, 'list-style': 'none' }, components: [
                                    { tagName: 'li', components: [{ tagName: 'a', type: 'link', attributes: { href: '#beranda' }, style: { color: '#94a3b8', 'text-decoration': 'none' }, components: [{ type: 'textnode', content: 'Beranda' }] }] },
                                    { tagName: 'li', components: [{ tagName: 'a', type: 'link', attributes: { href: '#fitur' }, style: { color: '#94a3b8', 'text-decoration': 'none' }, components: [{ type: 'textnode', content: 'Fitur' }] }] },
                                    { tagName: 'li', components: [{ tagName: 'a', type: 'link', attributes: { href: '#kontak' }, style: { color: '#94a3b8', 'text-decoration': 'none' }, components: [{ type: 'textnode', content: 'Kontak' }] }] }
                                ] }
                            ] },
                            { tagName: 'div', components: [
                                { tagName: 'h3', style: { margin: '0 0 10px', color: '#ffffff', 'font-size': '14px' }, content: 'Kontak' },
                                { tagName: 'p', style: { margin: '0 0 8px', color: '#94a3b8' }, content: 'Jl. Contoh No. 1, Kota Anda' },
                                { tagName: 'p', style: { margin: 0, color: '#94a3b8' }, content: 'halo@example.com' }
                            ] }
                        ] },
                        { tagName: 'div', style: { padding: '20px 0 0', border: '1px solid transparent', 'border-top-color': 'rgba(148,163,184,.24)', color: '#64748b', 'font-size': '12px', 'text-align': 'center' }, content: '© 2026 Nama Brand. Semua hak dilindungi.' }
                    ]
                }]
            }
        });

        addBlock('typing-text', {
            label: 'Typing Text',
            content: { type: 'typing-text' }
        });
        addBlock('counter-up', {
            label: 'Counter Up Statistik',
            content: { type: 'counter-up' }
        });
        addBlock('tiktok-feed', {
            label: 'TikTok Auto Feed',
            content: { type: 'tiktok-feed' }
        });
        addBlock('floating-whatsapp', {
            label: 'Floating WhatsApp',
            content: { type: 'floating-whatsapp' }
        });
        addBlock('shape-divider', {
            label: 'Shape Divider Wave',
            content: { type: 'shape-divider' }
        });

        const categoryIcons = {
            visual: 'fa-palette',
            media: 'fa-photo-film',
            layout: 'fa-table-cells-large'
        };
        function decorateBlockCategories() {
            document.querySelectorAll('.blocks-container .gjs-block-category').forEach(category => {
                const title = category.querySelector('.gjs-title');
                if (!title || title.querySelector('.pb-category-icon')) return;
                const label = (title.textContent || '').trim().toLowerCase();
                const iconName = categoryIcons[label];
                if (!iconName) return;
                const icon = document.createElement('i');
                icon.className = `fa-solid ${iconName} pb-category-icon`;
                icon.setAttribute('aria-hidden', 'true');
                title.prepend(icon);
            });
        }
        const blocksContainer = document.querySelector('.blocks-container');
        if (blocksContainer && typeof MutationObserver === 'function') {
            new MutationObserver(decorateBlockCategories).observe(blocksContainer, { childList: true, subtree: true });
        }
        editor.on('block:add block:remove', decorateBlockCategories);
        decorateBlockCategories();

        editor.on('canvas:frame:load', scheduleCanvasRefresh);
        editor.on('load', scheduleCanvasRefresh);
        editor.on('component:add component:remove component:update styleUpdate', scheduleCanvasRefresh);

        async function loadPage() {
            const slug = slugInput.value.trim();
            if (!validSlug(slug)) {
                setStatus('Slug tidak valid.', true);
                return;
            }
            setStatus('Memuat halaman…');
            try {
                const response = await fetch(`api.php?action=load&slug=${encodeURIComponent(slug)}`, {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Gagal memuat halaman.');
                isLoadingPage = true;
                const savedCss = String(result.page.css || '');
                editor.setComponents(result.page.json || []);
                editor.setStyle(savedCss);
                if (!savedCss.includes('.pb-grid-two')) {
                    editor.addStyle('@media (max-width: 480px) { .pb-grid-two { grid-template-columns: 1fr !important; } }');
                }
                isLoadingPage = false;
                isDirty = false;
                activePageTitle = result.page.title || slug;
                syncPageSettings();
                updateStatusUI('Semua perubahan tersimpan', 'saved');
                setStatus(`Halaman “${result.page.title || slug}” siap.`);
            } catch (error) {
                isLoadingPage = false;
                setStatus(error.message || 'Gagal memuat halaman.', true);
            }
        }

        async function persistPage(automatic = false) {
            const slug = slugInput.value.trim();
            if (!validSlug(slug)) {
                const message = 'Slug hanya boleh huruf kecil, angka, dan tanda hubung.';
                if (automatic) updateStatusUI('Gagal menyimpan otomatis: slug tidak valid', 'error');
                else {
                    setStatus(message, true);
                    slugInput.focus();
                }
                throw new Error(message);
            }
            if (saveInProgress) return saveInProgress;

            const savedVersion = changeVersion;
            const payload = {
                slug,
                title: activePageTitle,
                json: editor.getComponents().toJSON(),
                css: editor.getCss(),
                html: editor.getHtml()
            };
            saveButton.disabled = true;
            if (automatic) updateStatusUI('Menyimpan otomatis…', 'pending');
            else setStatus('Menyimpan halaman…');

            saveInProgress = (async () => {
                const response = await fetch('api.php?action=save', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify(payload)
                });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Gagal menyimpan halaman.');
                updatePreview();
                syncPageSettings();
                if (changeVersion === savedVersion) {
                    isDirty = false;
                    const savedAt = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    updateStatusUI(automatic ? `Tersimpan otomatis ${savedAt}` : `Tersimpan ${savedAt}`, 'saved');
                } else {
                    isDirty = true;
                    updateStatusUI('Ada perubahan belum disimpan', 'pending');
                }
                if (!automatic) setStatus(result.message || 'Halaman berhasil disimpan.');
                return result;
            })();
            try {
                return await saveInProgress;
            } catch (error) {
                if (automatic) updateStatusUI('Gagal menyimpan otomatis; perubahan belum tersimpan', 'error');
                else setStatus(error.message || 'Gagal menyimpan halaman.', true);
                throw error;
            } finally {
                saveInProgress = null;
                saveButton.disabled = false;
            }
        }

        async function triggerAutoSave() {
            if (!isDirty || saveInProgress) return;
            try {
                await persistPage(true);
            } catch (error) {
                updateStatusUI(error.message
                    ? `Gagal menyimpan otomatis: ${error.message}`
                    : 'Gagal menyimpan otomatis; perubahan belum tersimpan', 'error');
            }
        }

        saveButton.addEventListener('click', () => {
            persistPage(false).catch(error => {
                setStatus(error.message || 'Gagal menyimpan halaman.', true);
            });
        });

        loadPages()
            .catch(error => setStatus(error.message || 'Daftar halaman gagal dimuat.', true))
            .finally(loadPage);
        autoSaveInterval = window.setInterval(triggerAutoSave, 120000);
    })();
