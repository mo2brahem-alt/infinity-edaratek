import { computed, ref } from 'vue';

const STORAGE_KEY = 'edaratek:theme-mode';
const MODE_DARK = 'dark';
const MODE_LIGHT = 'light';

const themeMode = ref(MODE_DARK);

let bootstrapped = false;
let storageListenerAttached = false;

const isClient = () => typeof window !== 'undefined' && typeof document !== 'undefined';

const normalizeMode = (mode) => (mode === MODE_LIGHT ? MODE_LIGHT : MODE_DARK);

const safeReadStoredMode = () => {
    if (!isClient()) return null;

    try {
        const stored = String(window.localStorage.getItem(STORAGE_KEY) || '').trim();
        if (stored === MODE_LIGHT || stored === MODE_DARK) {
            return stored;
        }
    } catch {
        // no-op
    }

    return null;
};

const safeWriteStoredMode = (mode) => {
    if (!isClient()) return;

    try {
        window.localStorage.setItem(STORAGE_KEY, normalizeMode(mode));
    } catch {
        // no-op
    }
};

const preferredModeFromSystem = () => {
    if (!isClient() || typeof window.matchMedia !== 'function') {
        return MODE_DARK;
    }

    return window.matchMedia('(prefers-color-scheme: light)').matches
        ? MODE_LIGHT
        : MODE_DARK;
};

const applyThemeToDom = (mode) => {
    if (!isClient()) return;

    const normalizedMode = normalizeMode(mode);
    const root = document.documentElement;

    root.classList.toggle('theme-light', normalizedMode === MODE_LIGHT);
    root.classList.toggle('theme-dark', normalizedMode === MODE_DARK);
    root.setAttribute('data-theme', normalizedMode);
    root.style.colorScheme = normalizedMode;
};

const syncModeFromStorageEvent = (event) => {
    if (event.key !== STORAGE_KEY) return;

    const nextMode = normalizeMode(event.newValue || MODE_DARK);
    themeMode.value = nextMode;
    applyThemeToDom(nextMode);
};

const ensureThemeMode = () => {
    if (!isClient() || bootstrapped) {
        return;
    }

    const storedMode = safeReadStoredMode();
    const initialMode = normalizeMode(storedMode || preferredModeFromSystem());

    themeMode.value = initialMode;
    applyThemeToDom(initialMode);

    if (!storageListenerAttached) {
        window.addEventListener('storage', syncModeFromStorageEvent);
        storageListenerAttached = true;
    }

    bootstrapped = true;
};

const setThemeMode = (mode) => {
    const normalizedMode = normalizeMode(mode);
    themeMode.value = normalizedMode;
    applyThemeToDom(normalizedMode);
    safeWriteStoredMode(normalizedMode);
};

const toggleThemeMode = () => {
    setThemeMode(themeMode.value === MODE_LIGHT ? MODE_DARK : MODE_LIGHT);
};

export const useThemeMode = () => {
    ensureThemeMode();

    return {
        themeMode,
        isLightMode: computed(() => themeMode.value === MODE_LIGHT),
        isDarkMode: computed(() => themeMode.value === MODE_DARK),
        setThemeMode,
        toggleThemeMode,
        ensureThemeMode,
    };
};

export { MODE_DARK, MODE_LIGHT, ensureThemeMode };
