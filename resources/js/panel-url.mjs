export function panelUrl(path = '') {
    const base = (typeof document === 'undefined' ? '' : (document.body.dataset.qopenpanelPath || '')).replace(/\/+$/, '');
    return `${base}${path}` || '/';
}
