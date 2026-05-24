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

const edgeSwipeNavigation = (() => {
    const EDGE_SIZE = 28;
    const ACTIVATE_DISTANCE = 92;
    const CANCEL_VERTICAL_DISTANCE = 22;
    const MAX_OFF_AXIS_DISTANCE = 56;
    const MAX_TRANSLATE = 132;
    const isTouchCapable = window.matchMedia('(pointer: coarse)').matches;
    let tracking = false;
    let active = false;
    let startX = 0;
    let startY = 0;
    let currentX = 0;

    function canStart(target, clientX) {
        if (!isTouchCapable || clientX > EDGE_SIZE) {
            return false;
        }

        if (document.body.classList.contains('pwa-install-open')) {
            return false;
        }

        if (!(target instanceof Element)) {
            return true;
        }

        return !target.closest('input, textarea, select, video, iframe, [data-no-edge-swipe="true"]');
    }

    function setSwipeState(offset, progress) {
        document.documentElement.style.setProperty('--swipe-back-offset', `${offset}px`);
        document.documentElement.style.setProperty('--swipe-back-progress', String(progress));
    }

    function setTranslate(distance) {
        const viewportWidth = Math.max(window.innerWidth, 1);
        const translate = Math.max(0, distance);
        const visibleTranslate = Math.min(translate, MAX_TRANSLATE);
        const progress = Math.max(0, Math.min(translate / Math.max(ACTIVATE_DISTANCE, viewportWidth * 0.28), 1));
        document.documentElement.classList.add('is-edge-swiping');
        setSwipeState(visibleTranslate, progress);
        document.body.style.transition = 'none';
    }

    function reset(animate = true) {
        document.documentElement.classList.remove('is-edge-swiping');

        if (animate) {
            document.body.style.transition = 'transform 140ms ease, box-shadow 140ms ease';
        }

        setSwipeState(0, 0);
        tracking = false;
        active = false;
        startX = 0;
        startY = 0;
        currentX = 0;

        window.setTimeout(() => {
            if (!tracking && !active) {
                document.body.style.transition = '';
            }
        }, 160);
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

            if (deltaX <= 10) {
                return;
            }

            if (Math.abs(deltaY) > MAX_OFF_AXIS_DISTANCE) {
                reset(false);
                return;
            }

            active = true;
        }

        if (deltaX < 0) {
            reset();
            return;
        }

        currentX = touch.clientX;
        setTranslate(deltaX);
    }

    function onTouchEnd() {
        if (!tracking) {
            return;
        }

        const deltaX = currentX - startX;

        if (active && deltaX >= ACTIVATE_DISTANCE && window.history.length > 1) {
            document.documentElement.classList.add('is-edge-swiping');
            document.body.style.transition = 'transform 140ms ease, box-shadow 140ms ease';
            setSwipeState(window.innerWidth, 1);
            window.setTimeout(() => window.history.back(), 90);
            reset(false);
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
    }

    return { bind };
})();

edgeSwipeNavigation.bind();

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js').catch((error) => {
            console.error('Service worker registration failed:', error);
        });
    });
}
