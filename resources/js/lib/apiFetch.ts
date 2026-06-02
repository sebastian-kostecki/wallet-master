function readXsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export async function apiFetch(input: RequestInfo | URL, init: RequestInit = {}): Promise<Response> {
    const headers = new Headers(init.headers);

    if (!headers.has('Accept')) {
        headers.set('Accept', 'application/json');
    }

    const xsrf = readXsrfToken();
    if (xsrf !== '') {
        headers.set('X-XSRF-TOKEN', xsrf);
    }

    if (!headers.has('X-Requested-With')) {
        headers.set('X-Requested-With', 'XMLHttpRequest');
    }

    return fetch(input, {
        ...init,
        headers,
        credentials: init.credentials ?? 'same-origin',
    });
}
