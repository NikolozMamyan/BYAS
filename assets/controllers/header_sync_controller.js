import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['button'];

    static values = {
        failedMessage: String,
    };

    async sync() {
        this.buttonTarget.disabled = true;
        this.buttonTarget.classList.add('is-syncing');

        try {
            const response = await fetch('/api/streaming/sync', {
                method: 'POST',
                headers: { Accept: 'application/json' },
                credentials: 'include',
            });
            const payload = await response.json().catch(() => null);

            if (!response.ok) {
                throw new Error(payload?.message || this.failedMessageValue);
            }

            if ((payload?.result?.accounts ?? 0) === 0) {
                this.visit('/app/connect/spotify');
                return;
            }

            window.location.reload();
        } catch (error) {
            this.buttonTarget.disabled = false;
            this.buttonTarget.classList.remove('is-syncing');
            window.alert(error.message || this.failedMessageValue);
        }
    }

    visit(url) {
        if (window.BYASPageTransition) {
            window.BYASPageTransition.leave(url);
            return;
        }

        window.location.href = url;
    }
}
