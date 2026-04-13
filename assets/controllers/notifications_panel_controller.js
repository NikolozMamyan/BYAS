import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['button', 'badge', 'panel', 'content'];
    static values = {
        url: String,
    };

    connect() {
        this.isOpen = false;
        this.handleDocumentClick = this.handleDocumentClick.bind(this);
        this.handleEscape = this.handleEscape.bind(this);
        document.addEventListener('click', this.handleDocumentClick);
        document.addEventListener('keydown', this.handleEscape);
        this.loadPanel();
    }

    disconnect() {
        document.removeEventListener('click', this.handleDocumentClick);
        document.removeEventListener('keydown', this.handleEscape);
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
        this.buttonTarget.setAttribute('aria-expanded', 'true');
    }

    close() {
        this.isOpen = false;
        this.panelTarget.hidden = true;
        this.panelTarget.classList.remove('is-open');
        this.buttonTarget.setAttribute('aria-expanded', 'false');
    }

    async loadPanel() {
        if (!this.hasUrlValue) {
            return;
        }

        const response = await fetch(this.urlValue, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

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
}
