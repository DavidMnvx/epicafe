/*
 * Événements — back-office.
 *
 * Accordéon par mois comme la galerie ; pas de glisser-déposer ici : les
 * événements datés se classent par leur date, les hebdomadaires par jour de
 * semaine. Masquer/afficher et suppression en deux clics sur la tuile.
 */

const root = document.querySelector('[data-events]');

if (root) {
    const routes = JSON.parse(root.dataset.routes);
    const token = root.dataset.token;

    async function post(url, data) {
        const body = new URLSearchParams();
        body.append('_token', token);
        for (const [key, value] of Object.entries(data)) {
            body.append(key, value);
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

    root.addEventListener('click', async (event) => {
        const toggle = event.target.closest('[data-month-toggle]');
        if (toggle) {
            const expanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', String(!expanded));
            toggle.parentElement.querySelector('[data-tiles]').hidden = expanded;
            return;
        }

        const publishButton = event.target.closest('[data-toggle-published]');
        if (publishButton) {
            const tile = publishButton.closest('[data-event]');
            const willHide = !tile.classList.contains('is-hidden');

            flash(tile, 'saving');
            try {
                await post(routes.update, { id: tile.dataset.id, isPublished: willHide ? '0' : '1' });

                tile.classList.toggle('is-hidden', willHide);
                publishButton.textContent = willHide ? 'Afficher' : 'Masquer';

                const thumb = tile.querySelector('.vv-ev__thumb');
                thumb.querySelector('[data-hidden-badge]')?.remove();

                if (willHide) {
                    const badge = document.createElement('span');
                    badge.className = 'vv-ev__badge';
                    badge.setAttribute('data-hidden-badge', '');
                    badge.textContent = 'Masqué';
                    thumb.append(badge);
                }

                flash(tile, 'saved');
            } catch (error) {
                flash(tile, 'error', error.message);
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

            const tile = deleteButton.closest('[data-event]');
            const section = tile.closest('[data-month], [data-section]');

            flash(tile, 'saving');
            try {
                await post(routes.delete, { id: tile.dataset.id });
                tile.remove();

                // Un mois vidé disparaît ; les sections fixes (hebdomadaires,
                // sans date) restent.
                if (section.hasAttribute('data-month') && !section.querySelector('[data-event]')) {
                    section.remove();
                }
            } catch (error) {
                flash(tile, 'error', error.message);
            }
        }
    });
}
