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
        <span class="vv-dz__text">
            <strong>Glisse ton image ici</strong> ou clique pour la choisir<br>
            <small>JPG, PNG, WebP ou PDF — elle est optimisée automatiquement.</small>
        </span>
        <span class="vv-dz__preview" data-preview hidden></span>
    `;

    // La zone remplace visuellement le widget ; l'input reste fonctionnel
    // mais caché (il garde le focus clavier via le clic sur la zone).
    input.parentElement.insertBefore(zone, input);
    input.style.position = 'absolute';
    input.style.opacity = '0';
    input.style.width = '1px';
    input.style.height = '1px';

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
    .vv-dz {
        display: flex; align-items: center; gap: .9rem; flex-wrap: wrap;
        border: 2px dashed #9dbdb6; border-radius: .5rem;
        background: #f7faf9; padding: .9rem 1.1rem; cursor: pointer;
        transition: border-color .15s ease, background .15s ease;
    }
    .vv-dz:hover { border-color: #2d685f; background: #eef5f3; }
    .vv-dz.is-dragover { border-color: #2d685f; background: #e3efec; border-style: solid; }
    .vv-dz__icon { font-size: 1.4rem; }
    .vv-dz__text { color: #55606d; font-size: .85rem; line-height: 1.35; }
    .vv-dz__text strong { color: #2d685f; }
    .vv-dz__preview { display: flex; align-items: center; gap: .6rem; }
    .vv-dz__preview img {
        width: 72px; height: 54px; object-fit: cover;
        border-radius: .35rem; border: 1px solid #dfe8e6;
    }
    .vv-dz__name { font-size: .8rem; color: #2d685f; font-weight: 600; }
`;
document.head.append(style);
