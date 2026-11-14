import './bootstrap';

/**
 * Alternância de tema (claro/escuro) com preferência persistida.
 * O estado inicial é aplicado antes da pintura por um script inline no
 * <head> (ver layouts), evitando "flash" de tema incorreto.
 */
const THEME_KEY = 'spi-theme';

function currentTheme() {
    return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
}

function syncThemeToggles(theme) {
    const isDark = theme === 'dark';

    document.querySelectorAll('[data-theme-toggle]').forEach((el) => {
        el.setAttribute('aria-pressed', isDark ? 'true' : 'false');
        el.setAttribute('title', isDark ? 'Mudar para o tema claro' : 'Mudar para o tema escuro');
    });

    document.querySelectorAll('[data-theme-icon="sun"]').forEach((el) => el.classList.toggle('hidden', !isDark));
    document.querySelectorAll('[data-theme-icon="moon"]').forEach((el) => el.classList.toggle('hidden', isDark));
}

function setTheme(theme) {
    document.documentElement.classList.toggle('dark', theme === 'dark');

    try {
        localStorage.setItem(THEME_KEY, theme);
    } catch (e) {
        /* armazenamento indisponível — segue apenas na sessão */
    }

    syncThemeToggles(theme);
}

function initTheme() {
    syncThemeToggles(currentTheme());

    document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
        btn.addEventListener('click', () => {
            setTheme(currentTheme() === 'dark' ? 'light' : 'dark');
        });
    });

    // Acompanha a preferência do sistema enquanto o usuário não escolher manualmente.
    const media = window.matchMedia('(prefers-color-scheme: dark)');
    media.addEventListener?.('change', (event) => {
        let stored = null;
        try {
            stored = localStorage.getItem(THEME_KEY);
        } catch (e) {
            /* ignora */
        }

        if (!stored) {
            document.documentElement.classList.toggle('dark', event.matches);
            syncThemeToggles(event.matches ? 'dark' : 'light');
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTheme);
} else {
    initTheme();
}
