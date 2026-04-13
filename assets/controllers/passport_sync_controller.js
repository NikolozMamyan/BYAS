import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['syncButton', 'feedback', 'feedbackText', 'logoutButton'];
    static values = {
        providers: Array,
    };

    connect() {
        this.renderIdleButton();
    }

    async sync() {
        if (this.providersValue.length === 0) {
            this.setFeedback('Aucun provider connecte a synchroniser.', 'error');
            return;
        }

        this.syncButtonTarget.disabled = true;
        this.syncButtonTarget.innerHTML = `Syncing ${this.renderProviderIcons()} <i class="fas fa-rotate fa-spin"></i>`;
        this.setFeedback(`Synchronisation en cours: ${this.providersLabel()}.`, '');

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
            const providerSummary = this.buildProviderSummary(result.providers ?? []);

            this.setFeedback(
                `Sync terminee : ${inserted} nouveaux streams, ${skipped} ignores, +${xpAwarded} XP.${providerSummary}`,
                'success'
            );

            if (inserted > 0 || xpAwarded > 0) {
                window.setTimeout(() => window.location.reload(), 900);
            }
        } catch (error) {
            this.setFeedback(error.message || 'La sync a echoue.', 'error');
        } finally {
            this.syncButtonTarget.disabled = false;
            this.renderIdleButton();
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

    renderIdleButton() {
        this.syncButtonTarget.innerHTML = `Sync ${this.renderProviderIcons()} <i class="fas fa-rotate"></i>`;
    }

    renderProviderIcons() {
        if (this.providersValue.length === 0) {
            return '<span class="sync-providers"><i class="fas fa-link-slash" style="color: #9CA3AF;"></i></span>';
        }

        const icons = this.providersValue.map((provider) => {
            if (provider === 'spotify') {
                return '<i class="fab fa-spotify" style="color: #1DB954;"></i>';
            }

            if (provider === 'youtube') {
                return '<i class="fab fa-youtube" style="color: #ff3d5a;"></i>';
            }

            if (provider === 'apple_music') {
                return '<i class="fab fa-apple" style="color: #fc3c44;"></i>';
            }

            return '<i class="fas fa-wave-square"></i>';
        }).join('');

        return `<span class="sync-providers">${icons}</span>`;
    }

    providersLabel() {
        if (this.providersValue.length === 0) {
            return 'aucun provider';
        }

        return this.providersValue.map((provider) => {
            if (provider === 'spotify') {
                return 'Spotify';
            }

            if (provider === 'youtube') {
                return 'YouTube';
            }

            if (provider === 'apple_music') {
                return 'Apple Music';
            }

            return provider;
        }).join(', ');
    }

    buildProviderSummary(providers) {
        if (!Array.isArray(providers) || providers.length === 0) {
            return '';
        }

        const chunks = providers.map((providerResult) => {
            const provider = providerResult.provider ?? 'provider';
            const inserted = providerResult.inserted ?? 0;
            const xpAwarded = providerResult.xpAwarded ?? 0;
            const status = providerResult.status ?? 'unknown';

            return `${provider}: ${status}, ${inserted} new, +${xpAwarded} XP`;
        });

        return ` Details: ${chunks.join(' | ')}.`;
    }
}
