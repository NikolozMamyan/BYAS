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
    const transitionMs = 180;
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
        if (!canAnimate() || isLeaving) {
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

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js').catch((error) => {
            console.error('Service worker registration failed:', error);
        });
    });
}
