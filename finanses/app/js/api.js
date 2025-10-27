function detectBasePath() {
    const segments = window.location.pathname.split('/').filter(Boolean);
    if (!segments.length) {
        return '';
    }
    return '/' + segments[0];
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
    return request(`${resource}?action=${encodeURIComponent(action)}`, {
        method: 'POST',
        headers,
        body: JSON.stringify(body)
    });
}

export const apiClient = {
    get,
    post,
    getCsrfToken
};
