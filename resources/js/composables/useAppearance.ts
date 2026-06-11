import { onMounted, ref } from 'vue';

type Appearance = 'light' | 'dark' | 'system';

function getStoredAppearance(): Appearance | null {
    if (typeof localStorage === 'undefined') {
        return null;
    }

    return localStorage.getItem('appearance') as Appearance | null;
}

function getSystemTheme(): 'light' | 'dark' {
    if (typeof window === 'undefined') {
        return 'light';
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

export function updateTheme(value: Appearance): void {
    if (typeof document === 'undefined') {
        return;
    }

    if (value === 'system') {
        document.documentElement.classList.toggle('dark', getSystemTheme() === 'dark');

        return;
    }

    document.documentElement.classList.toggle('dark', value === 'dark');
}

function handleSystemThemeChange(): void {
    updateTheme(getStoredAppearance() || 'system');
}

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    updateTheme(getStoredAppearance() || 'system');
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', handleSystemThemeChange);
}

export function useAppearance() {
    const appearance = ref<Appearance>('system');

    onMounted(() => {
        initializeTheme();

        const savedAppearance = getStoredAppearance();

        if (savedAppearance) {
            appearance.value = savedAppearance;
        }
    });

    function updateAppearance(value: Appearance): void {
        appearance.value = value;

        if (typeof localStorage !== 'undefined') {
            localStorage.setItem('appearance', value);
        }

        updateTheme(value);
    }

    return {
        appearance,
        updateAppearance,
    };
}
