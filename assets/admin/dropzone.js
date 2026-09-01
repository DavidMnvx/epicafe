/*
 * Zone de glisser-déposer sur tous les champs fichier des formulaires
 * EasyAdmin (photo d'un événement, images d'un partenaire, d'une catégorie
 * boutique, d'une image du site…).
 *
 * Le champ natif reste la source de vérité : un fichier déposé est assigné à
 * l'input via DataTransfer, la soumission suit le flux EasyAdmin normal, et
 * la conversion (redimensionnement, PDF → image) se fait côté serveur.
 */

const FIELD_SELECTOR = 'form input[type="file"]';

function enhance(input) {
    if (input.closest('[data-vv-dropzone]') || input.dataset.vvEnhanced) return;

    input.dataset.vvEnhanced = '1';

    const zone = document.createElement('div');
    zone.setAttribute('data-vv-dropzone', '');
    zone.className = 'vv-dz';
    zone.innerHTML = `
        <span class="vv-dz__icon" aria-hidden="true">🖼️</span>
        <span class="vv-dz__title">Glisse ton image ici</span>
        <span class="vv-dz__hint">ou clique pour la choisir — JPG, PNG, WebP et PDF acceptés.<br>Elle est optimisée automatiquement.</span>
        <span class="vv-dz__preview" data-preview hidden></span>
    `;

    // La zone REMPLACE la barre de fichier d'EasyAdmin (div.input-group) :
    // elle est insérée à sa place et la barre est masquée. L'input, déjà
    // caché par EasyAdmin (d-none), reste la source de vérité du formulaire ;
    // l'aperçu d'une image déjà en place (page d'édition) n'est pas touché.
    const bar = input.closest('.input-group');
    if (bar) {
        bar.parentElement.insertBefore(zone, bar);
        bar.style.display = 'none';
    } else {
        input.parentElement.insertBefore(zone, input);
    }

    zone.addEventListener('click', () => input.click());

    ['dragenter', 'dragover'].forEach((type) =>
        zone.addEventListener(type, (event) => {
            event.preventDefault();
            zone.classList.add('is-dragover');
        }),
    );

    ['dragleave', 'drop'].forEach((type) =>
        zone.addEventListener(type, (event) => {
            event.preventDefault();
            if (type === 'dragleave' && zone.contains(event.relatedTarget)) return;
            zone.classList.remove('is-dragover');
        }),
    );

    zone.addEventListener('drop', (event) => {
        const files = event.dataTransfer?.files;
        if (!files?.length) return;

        // Champ simple : seul le premier fichier déposé compte.
        const transfer = new DataTransfer();
        transfer.items.add(files[0]);
        input.files = transfer.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });

    input.addEventListener('change', () => showPreview(zone, input.files?.[0] ?? null));
}

function showPreview(zone, file) {
    const preview = zone.querySelector('[data-preview]');
    preview.innerHTML = '';
    preview.hidden = !file;
    zone.classList.toggle('has-file', !!file);

    if (!file) return;

    if (file.type.startsWith('image/')) {
        const img = document.createElement('img');
        img.src = URL.createObjectURL(file);
        img.onload = () => URL.revokeObjectURL(img.src);
        preview.append(img);
    }

    const name = document.createElement('span');
    name.className = 'vv-dz__name';
    name.textContent = file.type === 'application/pdf'
        ? `📄 ${file.name} — la première page deviendra l'image`
        : file.name;
    preview.append(name);
}

// Champs présents au chargement + ceux qu'EasyAdmin ajoute dynamiquement
// (collections, onglets rendus en différé).
document.querySelectorAll(FIELD_SELECTOR).forEach(enhance);

new MutationObserver((mutations) => {
    for (const mutation of mutations) {
        for (const node of mutation.addedNodes) {
            if (node.nodeType !== Node.ELEMENT_NODE) continue;
            if (node.matches?.(FIELD_SELECTOR)) enhance(node);
            node.querySelectorAll?.(FIELD_SELECTOR).forEach(enhance);
        }
    }
}).observe(document.body, { childList: true, subtree: true });

// Styles injectés par le module : la dropzone vit dans les formulaires
// EasyAdmin, hors de nos templates custom.
const style = document.createElement('style');
style.textContent = `
    /* Même style que la zone de dépôt de la page Photos. */
    .vv-dz {
        display: block; text-align: center;
        border: 2px dashed #9dbdb6; border-radius: .6rem;
        background: #f7faf9; padding: 1.75rem 1.25rem; cursor: pointer;
        transition: border-color .15s ease, background .15s ease;
    }
    .vv-dz:hover { border-color: #2d685f; background: #eef5f3; }
    .vv-dz.is-dragover { border-color: #2d685f; background: #e3efec; border-style: solid; }
    .vv-dz__icon { font-size: 1.75rem; display: block; }
    .vv-dz__title { display: block; font-weight: 700; color: #2d685f; margin: .4rem 0 .2rem; font-size: 1.05rem; }
    .vv-dz__hint { display: block; color: #55606d; font-size: .85rem; }
    .vv-dz__preview {
        display: flex; align-items: center; justify-content: center; gap: .6rem;
        margin-top: 1rem; padding-top: .9rem; border-top: 1px solid #dfe8e6;
    }
    .vv-dz__preview img {
        width: 96px; height: 72px; object-fit: cover;
        border-radius: .4rem; border: 1px solid #e4dbd0;
        box-shadow: 0 2px 6px rgba(20, 45, 40, .08);
    }
    .vv-dz__name { font-size: .82rem; color: #2d685f; font-weight: 600; }
`;
document.head.append(style);
