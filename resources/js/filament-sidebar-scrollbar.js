const listenerKey = Symbol.for('mcback.filamentSidebarScrollbarListener');
const scrollingClass = 'fi-sidebar-nav--scrolling';

if (!document[listenerKey]) {
    document[listenerKey] = true;
    document.documentElement.classList.add('fi-sidebar-scrollbar-enhanced');

    const timeouts = new WeakMap();

    document.addEventListener(
        'scroll',
        (event) => {
            const navigation = event.target;

            if (!(navigation instanceof HTMLElement) || !navigation.matches('.fi-sidebar-nav')) {
                return;
            }

            navigation.classList.add(scrollingClass);
            window.clearTimeout(timeouts.get(navigation));
            timeouts.set(navigation, window.setTimeout(() => {
                navigation.classList.remove(scrollingClass);
                timeouts.delete(navigation);
            }, 700));
        },
        { capture: true, passive: true },
    );
}
