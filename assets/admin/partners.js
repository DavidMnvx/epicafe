/*
 * Partenaires — back-office.
 *
 * Mêmes gestes que la galerie : glisser pour ordonner (et changer de section,
 * donc de mise en avant sur le site), masquer/afficher et supprimer sur la
 * carte, confirmation de suppression en deux clics dans le bouton.
 */

import Sortable from 'sortablejs';

const root = document.querySelector('[data-partners]');

if (root) {
    const routes = JSON.parse(root.dataset.routes);
    const token = root.dataset.token;

    async function post(url, data) {
        const body = new URLSearchParams();
        body.append('_token', token);

        for (const [key, value] of Object.entries(data)) {
            if (Array.isArray(value)) {
                value.forEach((v) => body.append(`${key}[]`, v));
            } else {
                body.append(key, value);
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
                ? `<span class="text-danger" title="${message.replaceAll('"', '&quot;')}">✕</span>`
                : (LABELS[state] ?? '');

        if (state === 'saved') {
            setTimeout(() => {
                if (target.innerHTML === LABELS.saved) target.innerHTML = '';
            }, 1800);
        }
    }

    // ------------------------------------------------------- glisser-déposer

    root.querySelectorAll('[data-cards]').forEach((list) => {
        Sortable.create(list, {
            group: 'partners',
            handle: '[data-handle]',
            animation: 150,
            ghostClass: 'vv-sortable-ghost',
            fallbackOnBody: true,
            onEnd: async (event) => {
                await persistOrder(event.to, event.item);

                if (event.from !== event.to) {
                    await persistOrder(event.from);
                    refreshSection(event.from);
                }

                refreshSection(event.to);
            },
        });
    });

    async function persistOrder(list, feedbackCard = null) {
        const ids = [...list.querySelectorAll('[data-card]')].map((el) => el.dataset.id);

        flash(feedbackCard, 'saving');
        try {
            await post(routes.reorder, { type: list.dataset.type, ids });
            flash(feedbackCard, 'saved');
        } catch (error) {
            flash(feedbackCard, 'error', error.message);
        }
    }

    function refreshSection(list) {
        const section = list.closest('[data-section]');
        const count = list.querySelectorAll('[data-card]').length;

        section.querySelector('[data-count]').textContent = count;
        section.querySelector('[data-empty]').hidden = count > 0;
    }

    // ------------------------------------------------------------- actions

    root.addEventListener('click', async (event) => {
        const publishButton = event.target.closest('[data-toggle-published]');
        if (publishButton) {
            const card = publishButton.closest('[data-card]');
            const willHide = !card.classList.contains('is-hidden');

            flash(card, 'saving');
            try {
                await post(routes.update, { id: card.dataset.id, isPublished: willHide ? '0' : '1' });

                card.classList.toggle('is-hidden', willHide);
                publishButton.textContent = willHide ? 'Afficher' : 'Masquer';

                const thumb = card.querySelector('.vv-part__thumb');
                thumb.querySelector('[data-hidden-badge]')?.remove();

                if (willHide) {
                    const badge = document.createElement('span');
                    badge.className = 'vv-part__badge';
                    badge.setAttribute('data-hidden-badge', '');
                    badge.textContent = 'Masqué';
                    thumb.append(badge);
                }

                flash(card, 'saved');
            } catch (error) {
                flash(card, 'error', error.message);
            }
            return;
        }

        const deleteButton = event.target.closest('[data-delete]');
        if (deleteButton) {
            if (!deleteButton.classList.contains('is-confirming')) {
                deleteButton.classList.add('is-confirming');
                deleteButton.textContent = 'Confirmer ?';

                deleteButton._confirmTimer = setTimeout(() => {
                    deleteButton.classList.remove('is-confirming');
                    deleteButton.textContent = 'Supprimer';
                }, 4000);

                return;
            }

            clearTimeout(deleteButton._confirmTimer);

            const card = deleteButton.closest('[data-card]');
            const list = card.closest('[data-cards]');

            flash(card, 'saving');
            try {
                await post(routes.delete, { id: card.dataset.id });
                card.remove();
                refreshSection(list);
            } catch (error) {
                flash(card, 'error', error.message);
            }
        }
    });
}
