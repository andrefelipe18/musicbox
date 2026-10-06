const flag = Symbol.for('mcback.forceDarkTheme');
const storageKey = 'theme';

if (!document[flag]) {
    document[flag] = true;

    localStorage.setItem(storageKey, 'dark');

    const style = document.createElement('style');
    style.textContent = '.fi-theme-switcher{display:none!important}';
    document.head.append(style);

    const forceDark = () => localStorage.setItem(storageKey, 'dark');

    document.addEventListener('theme-changed', forceDark);
    window.addEventListener('storage', (event) => {
        if (event.key === storageKey) {
            forceDark();
        }
    });
}
