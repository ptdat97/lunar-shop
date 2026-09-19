/**
 * Open the shop's file manager in a popup and wait for the admin's choice.
 *
 *     const assets = await openFileManager({ url, type: 'image', multiple: true });
 *     // → [{ id, name, url, thumb, large, alt, … }]  or  null when cancelled
 *
 * The popup is an iframe of the manager's own URL (`panel.shop.media.picker`),
 * not a second copy of the manager mounted here. That is what makes it the
 * single way into the library: listing, uploading, folders and alt text are
 * the manager's, and a caller only ever receives the finished pick — the same
 * shape VaniCommerce's `fileManagerIframe(callback)` has.
 *
 * Unlike that one, the answer comes back by postMessage rather than by
 * reaching into the frame to plant a callback: the parent never touches the
 * frame's globals, and the origin + frame + channel checks below mean a message
 * from anything else is ignored.
 *
 * Plain DOM on purpose, so any script on a panel page can call it — also
 * published as `window.ShopFileManager.open` by the add-on bundle.
 */

const SOURCE = 'shop-file-manager';
const STYLE_ID = 'shop-file-manager-style';

// Panel colour tokens, so the frame's surround follows light/dark mode.
const CSS = `
.shop-fm-overlay{position:fixed;inset:0;z-index:60;display:grid;place-items:center;padding:16px;background:color-mix(in srgb,var(--color-ink-900,#111) 45%,transparent);animation:shop-fm-in .12s ease-out}
.shop-fm-box{position:relative;width:min(1280px,100%);height:min(760px,100%);border-radius:12px;overflow:hidden;background:var(--color-paper,#fff);border:1px solid var(--color-line,#e5e7eb);box-shadow:0 24px 64px rgba(0,0,0,.28)}
.shop-fm-box iframe{display:block;width:100%;height:100%;border:0;background:var(--color-paper,#fff)}
.shop-fm-loading{position:absolute;inset:0;display:grid;place-items:center;color:var(--color-ink-500,#6b7280);font-size:12px;pointer-events:none}
@keyframes shop-fm-in{from{opacity:0}to{opacity:1}}
@media (max-width:640px){.shop-fm-overlay{padding:0}.shop-fm-box{width:100%;height:100%;border-radius:0}}
`;

let active = null;

function injectStyle() {
    if (document.getElementById(STYLE_ID)) {
        return;
    }

    const style = document.createElement('style');
    style.id = STYLE_ID;
    style.textContent = CSS;
    document.head.appendChild(style);
}

/**
 * @param {object}  options
 * @param {string}  options.url       the picker URL (shared as `fileManager.url`)
 * @param {string}  [options.type]    image | video | document | all
 * @param {boolean} [options.multiple]
 * @param {string}  [options.folder]  folder slug to open on
 * @param {string}  [options.uploadFolder]  where uploads go while "All files"
 *                  is open — a product page files new photos under `products`
 * @param {string}  [options.title]   accessible name for the dialog
 * @returns {Promise<Array<object>|null>}
 */
export function openFileManager({ url, type = 'image', multiple = false, folder = '', uploadFolder = '', title = '' } = {}) {
    if (!url) {
        return Promise.reject(new Error('openFileManager: no picker URL. Is the Assets module enabled?'));
    }

    // One picker at a time: a second open cancels the first rather than
    // stacking two frames that would both answer.
    active?.close(null);

    injectStyle();

    return new Promise((resolve) => {
        const channel = `${Date.now().toString(36)}${Math.random().toString(36).slice(2)}`;
        const src = new URL(url, window.location.origin);

        src.searchParams.set('type', type);
        src.searchParams.set('multiple', multiple ? '1' : '0');
        src.searchParams.set('channel', channel);

        if (folder) {
            src.searchParams.set('folder', folder);
        }

        if (uploadFolder) {
            src.searchParams.set('upload_folder', uploadFolder);
        }

        const previousFocus = document.activeElement;
        const previousOverflow = document.documentElement.style.overflow;

        const overlay = document.createElement('div');
        overlay.className = 'shop-fm-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');

        if (title) {
            overlay.setAttribute('aria-label', title);
        }

        const box = document.createElement('div');
        box.className = 'shop-fm-box';

        const loading = document.createElement('div');
        loading.className = 'shop-fm-loading';
        loading.textContent = '…';

        const frame = document.createElement('iframe');
        frame.src = src.toString();
        frame.title = title || 'File manager';
        frame.addEventListener('load', () => {
            loading.remove();
            frame.focus();
        });

        box.append(loading, frame);
        overlay.append(box);

        const onMessage = (event) => {
            const data = event.data;

            if (
                event.origin !== window.location.origin
                || event.source !== frame.contentWindow
                || !data
                || data.source !== SOURCE
                || data.channel !== channel
            ) {
                return;
            }

            if (data.type === 'select') {
                close(Array.isArray(data.assets) ? data.assets : []);
            } else if (data.type === 'cancel') {
                close(null);
            }
        };

        // Escape while focus is still on the parent page. Inside the frame the
        // manager handles it and posts `cancel`.
        const onKey = (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                close(null);
            }
        };

        function close(result) {
            window.removeEventListener('message', onMessage);
            document.removeEventListener('keydown', onKey);
            overlay.remove();
            document.documentElement.style.overflow = previousOverflow;
            active = null;
            previousFocus?.focus?.();
            resolve(result);
        }

        window.addEventListener('message', onMessage);
        document.addEventListener('keydown', onKey);

        document.documentElement.style.overflow = 'hidden';
        document.body.appendChild(overlay);

        active = { close };
    });
}

/**
 * The manager's side of the conversation: where a pick is sent. `parent` when
 * framed by openFileManager(), `opener` when the picker URL was opened with
 * window.open(); null when the page is simply being browsed.
 */
export function pickerTarget() {
    if (window.parent && window.parent !== window) {
        return window.parent;
    }

    return window.opener && !window.opener.closed ? window.opener : null;
}

export function postPick(channel, payload) {
    // A JSON round trip, not the payload itself: the assets come out of the
    // manager's reactive state as Vue proxies, and structured clone refuses a
    // Proxy (DataCloneError) — the pick would silently never arrive.
    const message = JSON.parse(JSON.stringify({ source: SOURCE, channel, ...payload }));

    pickerTarget()?.postMessage(message, window.location.origin);
}
