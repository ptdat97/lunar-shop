/**
 * Client for the file manager's JSON API (modules/Assets, AssetsSection).
 *
 * Not the panel's `http`: that one JSON-encodes every body, so a FormData
 * upload goes out as `{}` — which is how the old picker's upload button never
 * delivered a file. Uploads here go through XMLHttpRequest for the progress
 * event fetch() still does not have.
 */

export class ApiError extends Error {
    constructor(status, message, errors = {}) {
        super(message || `Request failed with status ${status}.`);
        this.name = 'ApiError';
        this.status = status;
        this.errors = errors;
    }

    /** The first validation message, which is the one worth showing. */
    get firstError() {
        return Object.values(this.errors ?? {}).flat()[0] ?? null;
    }
}

const xsrfToken = () => {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
};

const headers = (json) => ({
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-XSRF-TOKEN': xsrfToken(),
    ...(json ? { 'Content-Type': 'application/json' } : {}),
});

async function request(method, url, body) {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: headers(body !== undefined),
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    const payload = await response.json().catch(() => null);

    if (!response.ok) {
        throw new ApiError(response.status, payload?.message, payload?.errors);
    }

    return payload;
}

function upload(url, fields, onProgress) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        const body = new FormData();

        Object.entries(fields).forEach(([key, value]) => {
            if (value !== null && value !== undefined && value !== '') {
                body.append(key, value);
            }
        });

        xhr.open('POST', url);
        xhr.withCredentials = true;
        Object.entries(headers(false)).forEach(([key, value]) => xhr.setRequestHeader(key, value));

        xhr.upload.onprogress = (event) => {
            if (event.lengthComputable) {
                onProgress?.(Math.round((event.loaded / event.total) * 100));
            }
        };

        xhr.onload = () => {
            let payload = null;

            try {
                payload = JSON.parse(xhr.responseText);
            } catch {
                // A 413 from the web server has an HTML body; the status says enough.
            }

            if (xhr.status >= 200 && xhr.status < 300) {
                resolve(payload);
            } else {
                reject(new ApiError(xhr.status, payload?.message, payload?.errors));
            }
        };

        xhr.onerror = () => reject(new ApiError(0, 'Network error'));

        xhr.send(body);
    });
}

/**
 * @param {string} base  the manager's root URL (`panel.shop.media.index`)
 */
export function mediaApi(base) {
    const root = base.replace(/\/+$/, '');
    const files = `${root}/files`;

    return {
        list: (params) => {
            const query = new URLSearchParams();

            Object.entries(params).forEach(([key, value]) => {
                if (value !== null && value !== undefined && value !== '') {
                    query.set(key, String(value));
                }
            });

            return request('GET', `${files}?${query}`);
        },
        show: (id) => request('GET', `${files}/${id}`),
        upload: (file, folder, onProgress) => upload(files, { file, folder }, onProgress),
        update: (id, attributes) => request('PATCH', `${files}/${id}`, attributes),
        replace: (id, file, onProgress) => upload(`${files}/${id}/replace`, { file }, onProgress),
        move: (ids, folder) => request('POST', `${files}/move`, { ids, folder }),
        destroy: (ids) => request('DELETE', files, { ids }),
        renameFolder: (folder, name) => request('PATCH', `${root}/folders/${encodeURIComponent(folder)}`, { name }),
    };
}

/** Replace `:name` placeholders — the lang files use Laravel's syntax. */
export function fmt(text, params = {}) {
    return Object.entries(params).reduce(
        (carry, [key, value]) => carry.replaceAll(`:${key}`, String(value)),
        String(text ?? ''),
    );
}
