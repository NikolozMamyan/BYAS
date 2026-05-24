import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.revealTimer = null;
        this.revealFrame = window.requestAnimationFrame(() => {
            this.revealTimer = window.setTimeout(() => {
                this.element.classList.remove('is-hydrating');
            }, 220);
        });
    }

    disconnect() {
        if (this.revealFrame) {
            window.cancelAnimationFrame(this.revealFrame);
        }

        if (this.revealTimer) {
            window.clearTimeout(this.revealTimer);
        }
    }
}
