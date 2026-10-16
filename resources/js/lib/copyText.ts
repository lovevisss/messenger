export async function copyText(value: string): Promise<boolean> {
    if (navigator.clipboard?.writeText) {
        try {
            await navigator.clipboard.writeText(value);
            return true;
        } catch {
            // Local HTTP sites may not have permission to use the Clipboard API.
        }
    }

    const input = document.createElement('textarea');
    const previousFocus = document.activeElement as HTMLElement | null;
    input.value = value;
    input.readOnly = true;
    input.style.position = 'fixed';
    input.style.left = '-9999px';
    input.style.top = '0';
    document.body.appendChild(input);
    input.focus();
    input.select();

    try {
        return document.execCommand('copy');
    } catch {
        return false;
    } finally {
        input.remove();
        previousFocus?.focus();
    }
}
