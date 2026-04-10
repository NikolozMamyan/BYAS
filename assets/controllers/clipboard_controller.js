import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['source', 'button'];
    static values = {
        copiedLabel: { type: String, default: 'Copied' },
        defaultLabel: { type: String, default: 'Copy link' },
    };

    async copy() {
        try {
            await navigator.clipboard.writeText(this.sourceTarget.value);
            this.markCopied();
        } catch (error) {
            this.sourceTarget.select();
            document.execCommand('copy');
            this.markCopied();
        }
    }

    markCopied() {
        if (!this.hasButtonTarget) {
            return;
        }

        this.buttonTarget.textContent = this.copiedLabelValue;
        window.setTimeout(() => {
            this.buttonTarget.textContent = this.defaultLabelValue;
        }, 1200);
    }
}
