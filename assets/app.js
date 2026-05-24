import './stimulus_bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

const pageTransition = (() => {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const transitionMs = 0;
    let isLeaving = false;

    function canAnimate() {
        return !reducedMotion.matches;
    }

    function isPlainLeftClick(event) {
        return event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey;
    }

    function isSamePageHash(url) {
        return url.origin === window.location.origin
            && url.pathname === window.location.pathname
            && url.search === window.location.search
            && url.hash;
    }

    function shouldAnimateLink(anchor, event) {
        if (!anchor || !isPlainLeftClick(event) || event.defaultPrevented) {
            return false;
        }

        if (anchor.target && anchor.target !== '_self') {
            return false;
        }

        if (anchor.hasAttribute('download') || anchor.dataset.noPageTransition === 'true') {
            return false;
        }

        const href = anchor.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) {
            return false;
        }

        const url = new URL(anchor.href, window.location.href);
        return url.origin === window.location.origin && !isSamePageHash(url);
    }

    function leave(url, replace = false) {
        if (!canAnimate() || isLeaving || transitionMs === 0) {
            if (replace) {
                window.location.replace(url);
            } else {
                window.location.assign(url);
            }
            return;
        }

        isLeaving = true;
        document.documentElement.classList.add('is-page-leaving');

        window.setTimeout(() => {
            if (replace) {
                window.location.replace(url);
            } else {
                window.location.assign(url);
            }
        }, transitionMs);
    }

    function bind() {
        document.addEventListener('click', (event) => {
            const target = event.target instanceof Element ? event.target : event.target.parentElement;
            const anchor = target?.closest('a[href]');
            if (!shouldAnimateLink(anchor, event)) {
                return;
            }

            event.preventDefault();
            leave(anchor.href);
        });

        window.addEventListener('pageshow', () => {
            isLeaving = false;
            document.documentElement.classList.remove('is-page-leaving');
        });
    }

    return { bind, leave };
})();

pageTransition.bind();
window.BYASPageTransition = pageTransition;

function warmNavigationScreens() {
    const shell = document.getElementById('appShell');

    if (!shell) {
        return;
    }

    [shell.dataset.swipePrevUrl, shell.dataset.swipeNextUrl]
        .filter((url, index, entries) => typeof url === 'string' && url !== '' && entries.indexOf(url) === index)
        .forEach((url) => {
            window.setTimeout(() => {
                fetch(url, {
                    credentials: 'include',
                    headers: {
                        'X-BYAS-Prefetch': '1',
                    },
                }).catch(() => undefined);
            }, 120);
        });
}

const edgeSwipeNavigation = (() => {
    const EDGE_SIZE = 28;
    const ACTIVATE_DISTANCE = 92;
    const CANCEL_VERTICAL_DISTANCE = 22;
    const MAX_OFF_AXIS_DISTANCE = 56;
    const MAX_TRANSLATE = 188;
    const isTouchCapable = window.matchMedia('(pointer: coarse)').matches;
    const isStandaloneApp = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    let tracking = false;
    let active = false;
    let startX = 0;
    let startY = 0;
    let currentX = 0;
    let swipeDirection = 0;
    let pendingDistance = 0;
    let translateFrame = 0;

    function getShell() {
        return document.getElementById('appShell');
    }

    function getHorizontalTarget(direction) {
        const shell = getShell();

        if (!shell) {
            return '';
        }

        return direction < 0
            ? (shell.dataset.swipePrevUrl || '')
            : (shell.dataset.swipeNextUrl || '');
    }

    function hasTabSwipeTarget() {
        return getHorizontalTarget(-1) !== '' || getHorizontalTarget(1) !== '';
    }

    function canStart(target, clientX) {
        if (!isTouchCapable) {
            return false;
        }

        if (document.body.classList.contains('pwa-install-open')) {
            return false;
        }

        if (!(target instanceof Element)) {
            return true;
        }

        if (target.closest('input, textarea, select, video, iframe, [data-no-edge-swipe="true"]')) {
            return false;
        }

        if (hasTabSwipeTarget()) {
            return true;
        }

        return clientX <= EDGE_SIZE;
    }

    function setSwipeState(offset, progress, direction) {
        document.documentElement.style.setProperty('--swipe-nav-offset', `${offset}px`);
        document.documentElement.style.setProperty('--swipe-nav-progress', String(progress));
        document.documentElement.style.setProperty('--swipe-nav-direction', String(direction));
        document.documentElement.style.setProperty('--swipe-preview-left-visible', direction > 0 ? '1' : '0');
        document.documentElement.style.setProperty('--swipe-preview-right-visible', direction < 0 ? '1' : '0');
    }

    function applyTranslate(distance) {
        const viewportWidth = Math.max(window.innerWidth, 1);
        const damping = isStandaloneApp ? 0.34 : 0.98;
        const translate = distance * damping;
        const maxTranslate = isStandaloneApp ? 72 : MAX_TRANSLATE;
        const visibleTranslate = Math.sign(translate) * Math.min(Math.abs(translate), MAX_TRANSLATE);
        const progress = Math.max(0, Math.min(Math.abs(translate) / Math.max(ACTIVATE_DISTANCE, viewportWidth * 0.28), 1));
        document.documentElement.classList.add('is-edge-swiping');
        setSwipeState(Math.sign(visibleTranslate) * Math.min(Math.abs(visibleTranslate), maxTranslate), progress, translate < 0 ? -1 : 1);
        const shell = getShell();
        if (shell) {
            shell.style.transition = 'none';
        }
    }

    function setTranslate(distance) {
        pendingDistance = distance;

        if (translateFrame) {
            return;
        }

        translateFrame = window.requestAnimationFrame(() => {
            translateFrame = 0;
            applyTranslate(pendingDistance);
        });
    }

    function reset(animate = true) {
        document.documentElement.classList.remove('is-edge-swiping');
        const shell = getShell();

        if (animate) {
            if (shell) {
                shell.style.transition = 'transform 160ms ease, box-shadow 160ms ease';
            }
        }

        setSwipeState(0, 0, 1);
        tracking = false;
        active = false;
        startX = 0;
        startY = 0;
        currentX = 0;
        swipeDirection = 0;
        pendingDistance = 0;

        if (translateFrame) {
            window.cancelAnimationFrame(translateFrame);
            translateFrame = 0;
        }

        window.setTimeout(() => {
            if (!tracking && !active) {
                if (shell) {
                    shell.style.transition = '';
                }
            }
        }, 160);
    }

    function prepareNavigationExit() {
        tracking = false;
        active = false;
        startX = 0;
        startY = 0;
        currentX = 0;
        swipeDirection = 0;
    }

    function onTouchStart(event) {
        const touch = event.touches[0];

        if (!touch || !canStart(event.target, touch.clientX)) {
            return;
        }

        tracking = true;
        active = false;
        startX = touch.clientX;
        startY = touch.clientY;
        currentX = touch.clientX;
    }

    function onTouchMove(event) {
        if (!tracking) {
            return;
        }

        const touch = event.touches[0];

        if (!touch) {
            reset(false);
            return;
        }

        const deltaX = touch.clientX - startX;
        const deltaY = touch.clientY - startY;

        if (!active) {
            if (Math.abs(deltaY) > CANCEL_VERTICAL_DISTANCE && Math.abs(deltaY) > Math.abs(deltaX)) {
                reset(false);
                return;
            }

            if (Math.abs(deltaX) <= 10) {
                return;
            }

            if (Math.abs(deltaY) > MAX_OFF_AXIS_DISTANCE) {
                reset(false);
                return;
            }

            swipeDirection = deltaX > 0 ? -1 : 1;
            const targetUrl = getHorizontalTarget(swipeDirection);

            if (targetUrl === '') {
                if (!(startX <= EDGE_SIZE && deltaX > 0 && window.history.length > 1)) {
                    reset(false);
                    return;
                }
            }

            active = true;
        }

        currentX = touch.clientX;
        setTranslate(deltaX);
    }

    function onTouchEnd() {
        if (!tracking) {
            return;
        }

        const deltaX = currentX - startX;
        const targetUrl = getHorizontalTarget(swipeDirection);
        const shell = getShell();

        if (active && Math.abs(deltaX) >= ACTIVATE_DISTANCE && targetUrl !== '') {
            document.documentElement.classList.add('is-edge-swiping');
            if (shell) {
                shell.style.transition = 'transform 160ms ease, box-shadow 160ms ease';
            }
            setSwipeState(swipeDirection === 1 ? -window.innerWidth : window.innerWidth, 1, swipeDirection === 1 ? -1 : 1);
            prepareNavigationExit();
            window.setTimeout(() => pageTransition.leave(targetUrl), 95);
            return;
        }

        if (active && deltaX >= ACTIVATE_DISTANCE && startX <= EDGE_SIZE && window.history.length > 1) {
            document.documentElement.classList.add('is-edge-swiping');
            if (shell) {
                shell.style.transition = 'transform 160ms ease, box-shadow 160ms ease';
            }
            setSwipeState(window.innerWidth, 1, 1);
            prepareNavigationExit();
            window.setTimeout(() => window.history.back(), 95);
            return;
        }

        reset();
    }

    function bind() {
        if (!isTouchCapable) {
            return;
        }

        document.addEventListener('touchstart', onTouchStart, { passive: true });
        document.addEventListener('touchmove', onTouchMove, { passive: true });
        document.addEventListener('touchend', onTouchEnd, { passive: true });
        document.addEventListener('touchcancel', () => reset(), { passive: true });
        window.addEventListener('pageshow', () => reset(false));
    }

    return { bind };
})();

edgeSwipeNavigation.bind();
warmNavigationScreens();

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js').catch((error) => {
            console.error('Service worker registration failed:', error);
        });
    });
}
