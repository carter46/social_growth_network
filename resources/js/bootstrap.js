import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// HTTP 419 = the session expired while this page was open. Reloading lets the auth
// middleware send guests to the login page (and back here after login).
// The timestamp guard stops a reload loop if the server cannot keep sessions at all.
const SESSION_RELOAD_KEY = 'sgn:session-expired-reload';

function reloadAfterSessionExpired() {
    const last = Number(window.sessionStorage?.getItem(SESSION_RELOAD_KEY) || 0);
    if (Date.now() - last < 15000) {
        return;
    }
    window.sessionStorage?.setItem(SESSION_RELOAD_KEY, String(Date.now()));
    window.location.reload();
}

const nativeFetch = window.fetch.bind(window);
window.fetch = async (...args) => {
    const response = await nativeFetch(...args);
    if (response.status === 419) {
        reloadAfterSessionExpired();
    }
    return response;
};

window.axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error?.response?.status === 419) {
            reloadAfterSessionExpired();
        }
        return Promise.reject(error);
    },
);
