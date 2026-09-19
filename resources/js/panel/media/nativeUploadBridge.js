/**
 * Lunar's own image uploaders pick from the shop's library.
 *
 * The panel's first-party screens — product, collection, brand and product
 * type galleries (MediaManager), option-value swatches (SwatchInput) — open
 * the computer's file dialog. They are compiled into the vendor bundle, so
 * they cannot be edited or swapped; what they share is a hidden
 * `<input type="file">` they `.click()` to open the dialog, and a `change`
 * listener that uploads whatever lands in it.
 *
 * That pair is the seam: a click on an image-only file input is intercepted
 * before the OS dialog opens, and the file manager (modules/Assets) opens
 * instead — pick something already in the library, or upload into it there.
 * The chosen originals are put into the input and `change` is fired, so the
 * first-party component uploads them exactly as if they came from disk: its
 * own route, validation, primary-image rule and flash message.
 *
 * Those bytes do not become a second copy. Every gallery upload goes through
 * AddMediaThroughLibrary on the server, which finds the identical library file
 * and links the gallery to it — the library stays the one place the file
 * exists. (Files dropped straight onto a gallery take the same server path, so
 * they land in the library too; nothing here needs to see them.)
 *
 * Stands down when the file manager is unavailable (no Assets module, or a
 * staff member without its permission): the uploaders then work as Lunar ships
 * them. If a Lunar release stops using this input pattern, the interception
 * simply never matches — the file dialog returns, nothing breaks.
 */
import { usePage } from '@inertiajs/vue3';
import { useToasts } from '@lunarphp/panel';
import { openFileManager } from './openFileManager';

// The page an uploader sits on decides which library folder new uploads made
// inside the picker are filed under — the same folders the server files
// direct gallery uploads under (LibraryLinks::MODELS).
const PAGE_FOLDERS = [
    { pattern: /\/products\/\d+/, folder: 'products' },
    { pattern: /\/collections\/\d+/, folder: 'collections' },
    { pattern: /\/brands\/\d+/, folder: 'brands' },
    { pattern: /\/product-types\/\d+/, folder: 'product-types' },
    { pattern: /\/product-options\//, folder: 'swatches' },
];

const folderForPage = () => PAGE_FOLDERS.find(({ pattern }) => pattern.test(window.location.pathname))?.folder ?? '';

const manager = () => usePage().props?.fileManager ?? null;

const isImageOnly = (input) => {
    const accept = (input.accept || '').split(',').map((part) => part.trim()).filter(Boolean);

    return accept.length > 0 && accept.every((part) => part.startsWith('image/'));
};

async function libraryFile(asset) {
    const response = await fetch(asset.url, { credentials: 'same-origin' });

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    const blob = await response.blob();

    return new File([blob], asset.file_name || asset.name || 'image', {
        type: blob.type || asset.mime || 'application/octet-stream',
    });
}

async function pickInto(input, config) {
    const assets = await openFileManager({
        url: config.url,
        type: 'image',
        multiple: input.multiple,
        uploadFolder: folderForPage(),
        title: config.labels?.picker_title,
    });

    if (!assets?.length) {
        return;
    }

    let files;

    try {
        files = await Promise.all(assets.map(libraryFile));
    } catch {
        useToasts().error(config.labels?.bridge_fetch_failed ?? 'Could not load the image from the library.');

        return;
    }

    const transfer = new DataTransfer();
    files.forEach((file) => transfer.items.add(file));

    input.files = transfer.files;
    input.dispatchEvent(new Event('change', { bubbles: true }));
}

function onClick(event) {
    const input = event.target;

    if (
        !(input instanceof HTMLInputElement)
        || input.type !== 'file'
        || input.disabled
        // The file manager's own inputs: intercepting those would loop.
        || input.hasAttribute('data-fm-input')
        || !isImageOnly(input)
    ) {
        return;
    }

    const config = manager();

    if (!config?.url) {
        return; // no library for this staff member — Lunar's dialog, as shipped
    }

    // Stops the OS dialog: a canceled click skips the input's activation.
    event.preventDefault();
    event.stopImmediatePropagation();

    pickInto(input, config);
}

let installed = false;

export function installNativeUploadBridge() {
    if (installed) {
        return;
    }

    installed = true;

    // Capture phase, on the document: runs before the input's own handling,
    // wherever in the panel the uploader was rendered.
    document.addEventListener('click', onClick, true);
}
