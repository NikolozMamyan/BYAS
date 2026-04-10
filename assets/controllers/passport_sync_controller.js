import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['syncButton', 'feedback', 'feedbackText', 'logoutButton'];

    async sync() {
        this.syncButtonTarget.disabled = true;
        this.syncButtonTarget.innerHTML = 'Syncing <i class="fab fa-spotify" style="color: #1DB954;"></i> <i class="fas fa-rotate fa-spin"></i>';
        this.setFeedback('Synchronisation Spotify en cours...', '');

        try {
            const response = await fetch('/api/streaming/sync', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                },
            });

            const payload = await response.json().catch(() => null);

            if (!response.ok) {
                throw new Error(payload?.error || payload?.message || `Statut de reponse : ${response.status}`);
            }

            const result = payload?.result || {};
            const inserted = result.totalInserted ?? 0;
            const skipped = result.totalSkipped ?? 0;
            const xpAwarded = result.totalXpAwarded ?? 0;

            this.setFeedback(
                `Sync terminee : ${inserted} nouveaux streams, ${skipped} ignores, +${xpAwarded} XP.`,
                'success'
            );

            if (inserted > 0 || xpAwarded > 0) {
                window.setTimeout(() => window.location.reload(), 900);
            }
        } catch (error) {
            this.setFeedback(error.message || 'La sync a echoue.', 'error');
        } finally {
            this.syncButtonTarget.disabled = false;
            this.syncButtonTarget.innerHTML = 'Sync <i class="fab fa-spotify" style="color: #1DB954;"></i> <i class="fas fa-rotate"></i>';
        }
    }

    async logout() {
        if (this.hasLogoutButtonTarget) {
            this.logoutButtonTarget.disabled = true;
        }

        try {
            const response = await fetch('/api/logout', {
                method: 'POST',
            });

            if (!response.ok) {
                throw new Error(`Statut de reponse : ${response.status}`);
            }

            await response.json();
            window.location.href = '/?t=' + Date.now();
        } catch (error) {
            this.setFeedback(error.message || 'Deconnexion impossible.', 'error');

            if (this.hasLogoutButtonTarget) {
                this.logoutButtonTarget.disabled = false;
            }
        }
    }

    setFeedback(message, type) {
        this.feedbackTarget.hidden = false;
        this.feedbackTarget.classList.remove('is-success', 'is-error');

        if (type === 'success') {
            this.feedbackTarget.classList.add('is-success');
        }

        if (type === 'error') {
            this.feedbackTarget.classList.add('is-error');
        }

        this.feedbackTextTarget.textContent = message;
    }
}
