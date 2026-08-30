/*
 * Builder de carte — back-office.
 *
 * Deux principes :
 *  - l'ordre se règle en glissant les lignes, jamais en tapant un numéro ;
 *  - une modification part au serveur dès qu'on quitte le champ, sans bouton
 *    « Enregistrer » à cliquer ligne par ligne.
 *
 * Pour le réordonnancement, le serveur reçoit toujours la liste COMPLÈTE des
 * ids d'une catégorie dans leur nouvel ordre : c'est lui qui réécrit les
 * positions de 0 à n.
 */

import Sortable from 'sortablejs';

const root = document.querySelector('[data-menu-builder]');

if (root) {
    const routes = JSON.parse(root.dataset.routes);
    const token = root.dataset.token;

    // ---------------------------------------------------------------- réseau

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

    // --------------------------------------------------------------- retours

    const LABELS = {
        saving: '<span class="text-muted">enregistrement…</span>',
        saved: '<span class="text-success">✓ enregistré</span>',
    };

    /**
     * Affiche un état transitoire sur une ligne. Le conteneur de statut doit
     * précéder les sous-listes dans le DOM pour qu'une ligne ne récupère pas
     * le statut d'une de ses variantes.
     */
    function flash(element, state, message = '') {
        const target = element?.querySelector('[data-status]') ?? root.querySelector('[data-global-status]');
        if (!target) return;

        target.innerHTML =
            state === 'error'
                ? `<span class="text-danger">✕ ${escapeHtml(message)}</span>`
                : (LABELS[state] ?? '');

        if (state === 'saved') {
            setTimeout(() => {
                if (target.innerHTML === LABELS.saved) target.innerHTML = '';
            }, 2000);
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

    // -------------------------------------------------- glisser-déposer

    const DRAG_OPTIONS = {
        animation: 150,
        ghostClass: 'vv-sortable-ghost',
        fallbackOnBody: true,
    };

    // Un seul groupe partagé pour toutes les listes de lignes : glisser une
    // ligne vers une autre catégorie la rattache à la catégorie d'arrivée.
    document.querySelectorAll('[data-items]').forEach((list) => {
        Sortable.create(list, {
            ...DRAG_OPTIONS,
            group: 'menu-items',
            handle: '[data-handle]',
            onEnd: async (event) => {
                await persistOrder(
                    routes.itemReorder,
                    { categoryId: event.to.dataset.categoryId },
                    event.to,
                    '[data-row]',
                    event.item,
                );

                // La liste de départ se renumérote aussi, sinon elle garde un
                // trou dans ses positions.
                if (event.from !== event.to) {
                    await persistOrder(
                        routes.itemReorder,
                        { categoryId: event.from.dataset.categoryId },
                        event.from,
                        '[data-row]',
                    );
                    refreshEmptyState(event.from);
                }

                refreshEmptyState(event.to);
            },
        });
    });

    // Les racines ne se mélangent pas aux sous-catégories : deux groupes.
    document.querySelectorAll('[data-categories]').forEach((list) => {
        Sortable.create(list, {
            ...DRAG_OPTIONS,
            group: list.dataset.level === 'root' ? 'menu-roots' : 'menu-subcategories',
            handle: '[data-category-handle]',
            onEnd: async (event) => {
                await persistOrder(
                    routes.categoryReorder,
                    { parentId: event.to.dataset.parentId ?? '' },
                    event.to,
                    '[data-category]',
                    event.item,
                );

                if (event.from !== event.to) {
                    await persistOrder(
                        routes.categoryReorder,
                        { parentId: event.from.dataset.parentId ?? '' },
                        event.from,
                        '[data-category]',
                    );
                }
            },
        });
    });

    /** Envoie l'ordre courant d'une liste au serveur. */
    async function persistOrder(url, payload, list, rowSelector, feedbackRow = null) {
        const ids = [...list.querySelectorAll(`:scope > ${rowSelector}`)].map((el) => el.dataset.id);

        flash(feedbackRow, 'saving');
        try {
            await post(url, { ...payload, ids });
            flash(feedbackRow, 'saved');
        } catch (error) {
            flash(feedbackRow, 'error', error.message);
        }
    }

    /** Montre ou cache le « aucune ligne » d'une catégorie selon son contenu. */
    function refreshEmptyState(list) {
        const placeholder = list.parentElement?.querySelector('[data-empty]');
        if (placeholder) {
            placeholder.hidden = list.querySelector('[data-row]') !== null;
        }
    }

    // ------------------------------------------- enregistrement au fil de l'eau

    // Une modification part au serveur quand le champ perd le focus, et
    // seulement si la valeur a réellement changé.
    root.addEventListener(
        'focusin',
        (event) => {
            const field = event.target.closest('[data-field]');
            if (field) field.dataset.initial = fieldValue(field);
        },
        true,
    );

    root.addEventListener(
        'focusout',
        (event) => {
            const field = event.target.closest('[data-field]');
            if (!field || fieldValue(field) === field.dataset.initial) return;

            save(field);
        },
        true,
    );

    // Les cases à cocher n'attendent pas la perte de focus.
    root.addEventListener('change', (event) => {
        const field = event.target.closest('[data-field]');
        if (field?.type === 'checkbox') save(field);
    });

    function fieldValue(field) {
        return field.type === 'checkbox' ? String(field.checked) : field.value;
    }

    function save(field) {
        const variantRow = field.closest('[data-variant-row]');

        return variantRow ? saveVariant(variantRow) : saveRow(field.closest('[data-row]'));
    }

    async function saveRow(row) {
        if (!row) return;

        const list = row.closest('[data-items]');

        flash(row, 'saving');
        try {
            await post(routes.itemUpdate, {
                id: row.dataset.id,
                categoryId: list?.dataset.categoryId ?? '',
                name: row.querySelector('[data-name]')?.value.trim() ?? '',
                unit: row.querySelector('[data-unit]')?.value.trim() ?? '',
                price: row.querySelector('[data-price]')?.value.trim() ?? '',
                note: row.querySelector('[data-note]')?.value.trim() ?? '',
                // La position vient désormais du rang de la ligne dans sa liste,
                // plus d'un champ saisi à la main.
                position: [...(list?.querySelectorAll(':scope > [data-row]') ?? [])].indexOf(row),
                isPublished: row.querySelector('[data-published]')?.checked ? '1' : '0',
            });
            flash(row, 'saved');
        } catch (error) {
            flash(row, 'error', error.message);
        }
    }

    async function saveVariant(variantRow) {
        const label = variantRow.querySelector('[data-variant-label]')?.value.trim() ?? '';
        if (label === '') {
            flash(variantRow, 'error', 'nom requis');
            return;
        }

        flash(variantRow, 'saving');
        try {
            await post(routes.variantUpdate, {
                id: variantRow.dataset.id,
                itemId: variantRow.dataset.itemId,
                label,
                price: variantRow.querySelector('[data-variant-price]')?.value.trim() ?? '',
            });
            flash(variantRow, 'saved');
        } catch (error) {
            flash(variantRow, 'error', error.message);
        }
    }

    // ------------------------------------------------- suppressions et ajouts

    root.addEventListener('click', async (event) => {
        const deleteItem = event.target.closest('[data-delete]');
        if (deleteItem) {
            const row = deleteItem.closest('[data-row]');
            const name = row.querySelector('[data-name]')?.value || 'cette ligne';
            if (!confirm(`Supprimer « ${name} » ?`)) return;

            const list = row.closest('[data-items]');
            flash(row, 'saving');
            try {
                await post(routes.itemDelete, { id: row.dataset.id });
                row.remove();
                refreshEmptyState(list);
            } catch (error) {
                flash(row, 'error', error.message);
            }
            return;
        }

        const deleteVariant = event.target.closest('[data-variant-delete]');
        if (deleteVariant) {
            const variantRow = deleteVariant.closest('[data-variant-row]');
            if (!confirm('Supprimer cette déclinaison ?')) return;

            flash(variantRow, 'saving');
            try {
                await post(routes.variantDelete, { id: variantRow.dataset.id });
                variantRow.remove();
            } catch (error) {
                flash(variantRow, 'error', error.message);
            }
            return;
        }

        const addVariant = event.target.closest('[data-add-variant]');
        if (addVariant) {
            appendVariantRow(addVariant.closest('[data-row]'));
        }
    });

    /** Ajoute une déclinaison vide, enregistrée dès que le nom est saisi. */
    function appendVariantRow(row) {
        const list = row.querySelector('[data-variants]');
        if (!list || list.querySelector('[data-variant-new]')) return;

        const li = document.createElement('li');
        li.className = 'vv-builder__variant';
        li.setAttribute('data-variant-new', '');
        li.innerHTML = `
            <span class="vv-builder__variantMark" aria-hidden="true">↳</span>
            <input class="form-control form-control-sm" data-new-label placeholder="Déclinaison (ex : 25 cl)">
            <input class="form-control form-control-sm" data-new-price placeholder="Prix">
            <button class="btn btn-sm btn-primary" type="button" data-new-save>Ajouter</button>
            <button class="btn btn-sm btn-link text-muted" type="button" data-new-cancel>Annuler</button>
            <span class="vv-builder__status" data-status></span>
        `;

        list.append(li);
        li.querySelector('[data-new-label]').focus();

        li.querySelector('[data-new-cancel]').addEventListener('click', () => li.remove());

        li.querySelector('[data-new-save]').addEventListener('click', async () => {
            const label = li.querySelector('[data-new-label]').value.trim();
            if (label === '') {
                flash(li, 'error', 'nom requis');
                return;
            }

            flash(li, 'saving');
            try {
                const json = await post(routes.variantCreate, {
                    itemId: row.dataset.id,
                    label,
                    price: li.querySelector('[data-new-price]').value.trim(),
                });

                li.replaceWith(buildVariantRow(json.id, row.dataset.id, json.label, json.price));
            } catch (error) {
                flash(li, 'error', error.message);
            }
        });
    }

    // ------------------------------------------------------------- créations

    root.addEventListener('submit', async (event) => {
        const categoryForm = event.target.closest('[data-cat-create]');
        if (categoryForm) {
            event.preventDefault();
            await createCategory(categoryForm);
            return;
        }

        const itemForm = event.target.closest('[data-item-create]');
        if (itemForm) {
            event.preventDefault();
            await createItem(itemForm);
        }
    });

    async function createCategory(form) {
        const name = form.querySelector('[name="name"]').value.trim();
        if (name === '') return;

        try {
            await post(routes.categoryCreate, {
                name,
                parentId: form.querySelector('[name="parentId"]').value,
            });

            // Une nouvelle catégorie change la structure de l'arbre et les
            // listes déposables : on recharge plutôt que de la reconstruire.
            window.location.reload();
        } catch (error) {
            flash(null, 'error', error.message);
        }
    }

    async function createItem(form) {
        const nameField = form.querySelector('[name="name"]');
        const name = nameField.value.trim();
        if (name === '') return;

        const unitField = form.querySelector('[name="unit"]');
        const priceField = form.querySelector('[name="price"]');
        const categoryId = form.dataset.categoryId;

        flash(form, 'saving');
        try {
            const json = await post(routes.itemCreate, {
                categoryId,
                name,
                unit: unitField.value.trim(),
                price: priceField.value.trim(),
            });

            const list = form.parentElement.querySelector(`[data-items][data-category-id="${categoryId}"]`);
            list.append(buildItemRow(json.id, name, unitField.value.trim(), priceField.value.trim()));
            refreshEmptyState(list);

            form.reset();
            nameField.focus();
            flash(form, 'saved');
        } catch (error) {
            flash(form, 'error', error.message);
        }
    }

    /** Reconstruit une ligne enregistrée, alignée sur le rendu Twig. */
    function buildItemRow(id, name, unit, price) {
        const li = document.createElement('li');
        li.className = 'vv-builder__item';
        li.setAttribute('data-row', '');
        li.dataset.id = id;
        li.innerHTML = `
            <div class="vv-builder__itemMain">
                <span class="vv-builder__handle" data-handle title="Glisser pour déplacer" aria-label="Déplacer">⠿</span>
                <input class="form-control form-control-sm vv-builder__name" data-field data-name value="${escapeHtml(name)}" placeholder="Nom">
                <input class="form-control form-control-sm vv-builder__unit" data-field data-unit value="${escapeHtml(unit)}" placeholder="Unité">
                <input class="form-control form-control-sm vv-builder__price" data-field data-price value="${escapeHtml(price)}" placeholder="Prix">
                <input class="form-control form-control-sm vv-builder__note" data-field data-note value="" placeholder="Note">
                <label class="vv-builder__publish" title="Visible sur le site">
                    <input class="form-check-input" type="checkbox" data-field data-published checked>
                    <span class="visually-hidden">Visible sur le site</span>
                </label>
                <button class="btn btn-sm btn-link text-muted" type="button" data-add-variant title="Ajouter une déclinaison">+ décl.</button>
                <button class="btn btn-sm btn-link text-danger" type="button" data-delete title="Supprimer">✕</button>
                <span class="vv-builder__status" data-status></span>
            </div>
            <ul class="vv-builder__variants" data-variants></ul>
        `;

        return li;
    }

    /** Reconstruit une déclinaison enregistrée, alignée sur le rendu Twig. */
    function buildVariantRow(id, itemId, label, price) {
        const li = document.createElement('li');
        li.className = 'vv-builder__variant';
        li.setAttribute('data-variant-row', '');
        li.dataset.id = id;
        li.dataset.itemId = itemId;
        li.innerHTML = `
            <span class="vv-builder__variantMark" aria-hidden="true">↳</span>
            <input class="form-control form-control-sm" data-field data-variant-label value="${escapeHtml(label)}" placeholder="Déclinaison">
            <input class="form-control form-control-sm" data-field data-variant-price value="${escapeHtml(price ?? '')}" placeholder="Prix">
            <button class="btn btn-sm btn-link text-danger" type="button" data-variant-delete title="Supprimer">✕</button>
            <span class="vv-builder__status" data-status></span>
        `;

        return li;
    }
}
