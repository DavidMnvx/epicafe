/*
 * Galerie photo — back-office.
 *
 * Un seul écran : dépôt de fichiers, tuiles regroupées par mois, ordre réglé
 * au glisser-déposer, titre et visibilité modifiables sur place.
 *
 * L'ordre envoyé au serveur est toujours la liste complète des photos dans
 * l'ordre du DOM, tous mois confondus : les positions sont globales, les
 * renuméroter partiellement décalerait les autres mois.
 */

import Sortable from 'sortablejs';

const root = document.querySelector('[data-gallery]');

if (root) {
    const routes = JSON.parse(root.dataset.routes);
    const token = root.dataset.token;
    const basePath = root.dataset.basePath;

    // ---------------------------------------------------------------- réseau

    async function post(url, data, { asFormData = false } = {}) {
        let body;

        if (asFormData) {
            body = data;
            body.append('_token', token);
        } else {
            body = new URLSearchParams();
            body.append('_token', token);
            for (const [key, value] of Object.entries(data)) {
                if (Array.isArray(value)) {
                    value.forEach((v) => body.append(`${key}[]`, v));
                } else {
                    body.append(key, value);
                }
            }
        }

        const response = await fetch(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body,
        });

        const json = await response.json().catch(() => ({}));

        if (!response.ok || json.ok === false) {
            throw new Error(json.message || `Erreur ${response.status}`);
        }

        return json;
    }

    const LABELS = {
        saving: '<span class="text-muted">…</span>',
        saved: '<span class="text-success">✓</span>',
    };

    function flash(element, state, message = '') {
        const target = element?.querySelector('[data-status]') ?? root.querySelector('[data-global-status]');
        if (!target) return;

        target.innerHTML =
            state === 'error'
                ? `<span class="text-danger" title="${escapeHtml(message)}">✕</span>`
                : (LABELS[state] ?? '');

        if (state === 'saved') {
            setTimeout(() => {
                if (target.innerHTML === LABELS.saved) target.innerHTML = '';
            }, 1800);
        }
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    // ------------------------------------------------------- dépôt de fichiers

    const dropzone = root.querySelector('[data-dropzone]');
    const fileInput = root.querySelector('[data-file-input]');

    dropzone.addEventListener('click', (event) => {
        // Le champ de date vit dans la zone : cliquer dedans ne doit pas
        // rouvrir le sélecteur de fichiers.
        if (event.target.closest('[data-taken-at], label')) return;
        fileInput.click();
    });

    dropzone.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            fileInput.click();
        }
    });

    fileInput.addEventListener('change', () => {
        if (fileInput.files.length) upload([...fileInput.files]);
        fileInput.value = '';
    });

    ['dragenter', 'dragover'].forEach((type) =>
        dropzone.addEventListener(type, (event) => {
            event.preventDefault();
            dropzone.classList.add('is-dragover');
        }),
    );

    ['dragleave', 'drop'].forEach((type) =>
        dropzone.addEventListener(type, (event) => {
            event.preventDefault();
            if (type === 'dragleave' && dropzone.contains(event.relatedTarget)) return;
            dropzone.classList.remove('is-dragover');
        }),
    );

    dropzone.addEventListener('drop', (event) => {
        const files = [...(event.dataTransfer?.files ?? [])];
        if (files.length) upload(files);
    });

    // Empêche le navigateur d'ouvrir un fichier lâché à côté de la zone.
    ['dragover', 'drop'].forEach((type) =>
        window.addEventListener(type, (event) => {
            if (!dropzone.contains(event.target)) event.preventDefault();
        }),
    );

    const progress = root.querySelector('[data-progress]');
    const progressBar = root.querySelector('[data-progress-bar]');
    const progressText = root.querySelector('[data-progress-text]');
    const errorBox = root.querySelector('[data-errors]');

    /**
     * Envoie les fichiers un par un plutôt qu'en un seul lot : la progression
     * est réelle, et un fichier refusé n'emporte pas les autres.
     */
    async function upload(files) {
        errorBox.hidden = true;
        errorBox.innerHTML = '';
        progress.hidden = false;

        const takenAt = root.querySelector('[data-taken-at]').value;
        const failures = [];
        let done = 0;

        for (const file of files) {
            progressText.textContent = `Envoi de ${file.name} (${done + 1}/${files.length})…`;

            const form = new FormData();
            form.append('files[]', file);
            if (takenAt) form.append('takenAt', takenAt);

            try {
                const json = await post(routes.upload, form, { asFormData: true });
                json.added.forEach(insertTile);
                failures.push(...(json.failed ?? []));
            } catch (error) {
                failures.push({ name: file.name, reason: error.message });
            }

            done += 1;
            progressBar.style.width = `${Math.round((done / files.length) * 100)}%`;
        }

        progressText.textContent = `${done - failures.length} photo(s) ajoutée(s).`;

        if (failures.length) {
            errorBox.hidden = false;
            errorBox.innerHTML = `
                <div class="alert alert-warning mb-0">
                    <strong>${failures.length} fichier(s) non importé(s)</strong>
                    <ul class="mb-0 mt-1">
                        ${failures.map((f) => `<li>${escapeHtml(f.name)} — ${escapeHtml(f.reason)}</li>`).join('')}
                    </ul>
                </div>`;
        }

        setTimeout(() => {
            progress.hidden = true;
            progressBar.style.width = '0';
        }, 2500);
    }

    /** Place une photo fraîchement envoyée dans le mois qui lui correspond. */
    function insertTile(photo) {
        root.querySelector('[data-empty-gallery]')?.remove();

        let section = root.querySelector(`[data-month="${photo.month}"]`);

        if (!section) {
            section = buildMonthSection(photo.month, photo.monthLabel);
            insertMonthInOrder(section);
        }

        const list = section.querySelector('[data-photos]');
        list.prepend(buildTile(photo));

        refreshCounts();
    }

    function buildMonthSection(key, label) {
        const section = document.createElement('section');
        section.className = 'vv-gal__month';
        section.dataset.month = key;
        section.innerHTML = `
            <button class="vv-gal__monthHead" type="button" data-month-toggle aria-expanded="true">
                <span class="vv-gal__chevron" aria-hidden="true">▾</span>
                <span class="vv-gal__monthName">${escapeHtml(label)}</span>
                <span class="vv-gal__monthCount" data-month-count>0</span>
            </button>
            <ul class="vv-gal__grid" data-photos></ul>`;

        makeSortable(section.querySelector('[data-photos]'));

        return section;
    }

    /** Les mois restent classés du plus récent au plus ancien. */
    function insertMonthInOrder(section) {
        const container = root.querySelector('[data-months]');
        const existing = [...container.querySelectorAll('[data-month]')];
        const next = existing.find((el) => el.dataset.month < section.dataset.month);

        next ? container.insertBefore(section, next) : container.append(section);
    }

    function buildTile(photo) {
        const li = document.createElement('li');
        li.className = 'vv-gal__tile';
        li.setAttribute('data-photo', '');
        li.dataset.id = photo.id;
        li.innerHTML = `
            <div class="vv-gal__thumb">
                <img src="${basePath}/${encodeURIComponent(photo.fileName)}" alt="${escapeHtml(photo.title)}" loading="lazy" decoding="async">
                <span class="vv-gal__handle" data-handle title="Glisser pour réordonner" aria-hidden="true">⠿</span>
            </div>
            <input class="form-control form-control-sm vv-gal__title" data-title value="${escapeHtml(photo.title)}" placeholder="Sans titre">
            <div class="vv-gal__tileActions">
                <button class="btn btn-sm btn-link" type="button" data-toggle-published>Masquer</button>
                <button class="btn btn-sm btn-link text-danger" type="button" data-delete title="Supprimer">✕</button>
                <span class="vv-gal__status" data-status></span>
            </div>`;

        return li;
    }

    // ------------------------------------------------------- glisser-déposer

    function makeSortable(list) {
        Sortable.create(list, {
            group: 'gallery',
            handle: '[data-handle]',
            animation: 150,
            ghostClass: 'vv-sortable-ghost',
            fallbackOnBody: true,
            onEnd: persistOrder,
        });
    }

    root.querySelectorAll('[data-photos]').forEach(makeSortable);

    async function persistOrder(event) {
        // Ordre global : toutes les tuiles de la page, dans l'ordre du DOM.
        const ids = [...root.querySelectorAll('[data-photo]')].map((el) => el.dataset.id);
        const tile = event?.item ?? null;

        flash(tile, 'saving');
        try {
            await post(routes.reorder, { ids });
            flash(tile, 'saved');
            refreshCounts();
        } catch (error) {
            flash(tile, 'error', error.message);
        }
    }

    // ------------------------------------------------------------ modifications

    root.addEventListener(
        'focusin',
        (event) => {
            const field = event.target.closest('[data-title]');
            if (field) field.dataset.initial = field.value;
        },
        true,
    );

    root.addEventListener(
        'focusout',
        (event) => {
            const field = event.target.closest('[data-title]');
            if (!field || field.value === field.dataset.initial) return;

            const tile = field.closest('[data-photo]');
            save(tile, { title: field.value.trim() });
        },
        true,
    );

    root.addEventListener('click', async (event) => {
        const toggle = event.target.closest('[data-month-toggle]');
        if (toggle) {
            const expanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', String(!expanded));
            toggle.parentElement.querySelector('[data-photos]').hidden = expanded;
            return;
        }

        const publishButton = event.target.closest('[data-toggle-published]');
        if (publishButton) {
            const tile = publishButton.closest('[data-photo]');
            const willHide = !tile.classList.contains('is-hidden');

            await save(tile, { isPublished: willHide ? '0' : '1' });

            tile.classList.toggle('is-hidden', willHide);
            publishButton.textContent = willHide ? 'Afficher' : 'Masquer';

            const thumb = tile.querySelector('.vv-gal__thumb');
            thumb.querySelector('[data-hidden-badge]')?.remove();

            if (willHide) {
                const badge = document.createElement('span');
                badge.className = 'vv-gal__badge';
                badge.setAttribute('data-hidden-badge', '');
                badge.textContent = 'Masquée';
                thumb.append(badge);
            }
            return;
        }

        const deleteButton = event.target.closest('[data-delete]');
        if (deleteButton) {
            const tile = deleteButton.closest('[data-photo]');
            const title = tile.querySelector('[data-title]')?.value || 'cette photo';

            if (!confirm(`Supprimer « ${title} » ? Cette action est définitive.`)) return;

            flash(tile, 'saving');
            try {
                await post(routes.delete, { id: tile.dataset.id });

                const section = tile.closest('[data-month]');
                tile.remove();

                // Un mois vidé de ses photos n'a plus lieu d'être affiché.
                if (!section.querySelector('[data-photo]')) section.remove();

                refreshCounts();
            } catch (error) {
                flash(tile, 'error', error.message);
            }
        }
    });

    async function save(tile, payload) {
        flash(tile, 'saving');
        try {
            await post(routes.update, { id: tile.dataset.id, ...payload });
            flash(tile, 'saved');
        } catch (error) {
            flash(tile, 'error', error.message);
        }
    }

    /** Recalcule le compteur global et ceux de chaque mois. */
    function refreshCounts() {
        const total = root.querySelectorAll('[data-photo]').length;
        root.querySelector('[data-total]').textContent = total;

        root.querySelectorAll('[data-month]').forEach((section) => {
            section.querySelector('[data-month-count]').textContent =
                section.querySelectorAll('[data-photo]').length;
        });
    }
}
