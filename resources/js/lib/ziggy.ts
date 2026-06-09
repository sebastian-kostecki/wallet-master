export function bootstrapZiggyFromDom(): void {
    if (typeof globalThis.Ziggy !== 'undefined') {
        return;
    }

    const element = document.getElementById('ziggy-routes-json');

    if (element?.textContent) {
        globalThis.Ziggy = JSON.parse(element.textContent);
    }
}
