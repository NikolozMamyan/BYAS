import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['button', 'badge', 'panel', 'content', 'backdrop'];
    static values = {
        url: String,
    };

    connect() {
        this.isOpen = false;
        this.refreshInterval = null;
        this.handleDocumentClick = this.handleDocumentClick.bind(this);
        this.handleEscape = this.handleEscape.bind(this);
        this.handleVisibility = this.handleVisibility.bind(this);
        document.addEventListener('click', this.handleDocumentClick);
        document.addEventListener('keydown', this.handleEscape);
        document.addEventListener('visibilitychange', this.handleVisibility);
        this.loadPanel();
        this.refreshInterval = window.setInterval(() => this.loadPanel(), 45000);
    }

    disconnect() {
        document.removeEventListener('click', this.handleDocumentClick);
        document.removeEventListener('keydown', this.handleEscape);
        document.removeEventListener('visibilitychange', this.handleVisibility);
        if (this.refreshInterval) {
            window.clearInterval(this.refreshInterval);
        }
    }

    async toggle(event) {
        event.preventDefault();

        if (this.isOpen) {
            this.close();
            return;
        }

        await this.loadPanel();
        this.open();
    }

    open() {
        this.isOpen = true;
        this.panelTarget.hidden = false;
        this.panelTarget.classList.add('is-open');
        if (this.hasBackdropTarget && this.isCompactLayout()) {
            this.backdropTarget.hidden = false;
            window.requestAnimationFrame(() => {
                if (this.hasBackdropTarget) {
                    this.backdropTarget.classList.add('is-visible');
                }
            });
            document.body.classList.add('sheet-open');
        }
        this.buttonTarget.setAttribute('aria-expanded', 'true');
    }

    close(event) {
        if (event) {
            event.preventDefault();
        }

        this.isOpen = false;
        this.panelTarget.hidden = true;
        this.panelTarget.classList.remove('is-open');
        if (this.hasBackdropTarget) {
            this.backdropTarget.classList.remove('is-visible');
            this.backdropTarget.hidden = true;
        }
        document.body.classList.remove('sheet-open');
        this.buttonTarget.setAttribute('aria-expanded', 'false');
    }

    async loadPanel() {
        if (!this.hasUrlValue) {
            return;
        }

        let response;
        const requestUrl = new URL(this.urlValue, window.location.origin);
        requestUrl.searchParams.set('t', String(Date.now()));

        try {
            response = await fetch(requestUrl.toString(), {
                cache: 'no-store',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Cache-Control': 'no-cache',
                    Pragma: 'no-cache',
                },
            });
        } catch (error) {
            return;
        }

        if (!response.ok) {
            return;
        }

        const data = await response.json();
        this.contentTarget.innerHTML = data.html;
        this.updateBadge(data.unreadCount ?? 0);
    }

    updateBadge(count) {
        if (count > 0) {
            this.badgeTarget.textContent = count > 99 ? '99+' : String(count);
            this.badgeTarget.classList.add('is-visible');
            return;
        }

        this.badgeTarget.textContent = '';
        this.badgeTarget.classList.remove('is-visible');
    }

    handleDocumentClick(event) {
        if (!this.isOpen) {
            return;
        }

        if (this.element.contains(event.target)) {
            return;
        }

        this.close();
    }

    handleEscape(event) {
        if (event.key === 'Escape' && this.isOpen) {
            this.close();
        }
    }

    handleVisibility() {
        if (document.visibilityState === 'visible') {
            this.loadPanel();
        }
    }

    isCompactLayout() {
        return window.matchMedia('(max-width: 768px)').matches;
    }
}
