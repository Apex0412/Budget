function detectBasePath() {
    const url = new URL('.', window.location.href);
    const pathname = url.pathname.replace(/\/+$/, '');
    if (pathname === '' || pathname === '/') {
        return '';
    }

    const segments = pathname.split('/').filter(Boolean);
    const finansesIndex = segments.lastIndexOf('finanses');
    if (finansesIndex !== -1) {
        return '/' + segments.slice(0, finansesIndex + 1).join('/');
    }

    return '/' + segments.join('/');
}

const API_BASE = `${detectBasePath()}/api`;

async function request(url, options = {}) {
    const response = await fetch(`${API_BASE}${url}`, {
        credentials: 'include',
        headers: {
            'Accept': 'application/json',
            ...options.headers
        },
        ...options
    });

    const contentType = response.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) {
        return { ok: false, error: 'Некорректный ответ сервера' };
    }

    const data = await response.json();
    return data;
}

async function getCsrfToken() {
    const result = await request('/csrf.php');
    if (!result.ok) {
        throw new Error(result.error || 'CSRF недоступен');
    }
    return result.data.token;
}

async function get(url) {
    return request(url, { method: 'GET' });
}

async function post(resource, action, body = {}, csrfToken) {
    const headers = { 'Content-Type': 'application/json' };
    if (csrfToken) {
        headers['X-CSRF'] = csrfToken;
    }
    const normalizedAction = String(action).replace(/^\/+/, '');
    return request(`${resource}?action=${encodeURIComponent(normalizedAction)}`, {
        method: 'POST',
        headers,
        body: JSON.stringify(body)
    });
}

async function upload(resource, action, formData, csrfToken) {
    const headers = {};
    if (csrfToken) {
        headers['X-CSRF'] = csrfToken;
    }
    const normalizedAction = String(action).replace(/^\/+/, '');
    return request(`${resource}?action=${encodeURIComponent(normalizedAction)}`, {
        method: 'POST',
        headers,
        body: formData
    });
}

export const apiClient = {
    get,
    post,
    upload,
    getCsrfToken
};
