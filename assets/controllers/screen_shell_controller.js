import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.revealFrame = window.requestAnimationFrame(() => {
            window.setTimeout(() => {
                this.element.classList.remove('is-hydrating');
            }, 80);
        });
    }

    disconnect() {
        if (this.revealFrame) {
            window.cancelAnimationFrame(this.revealFrame);
        }
    }
}
