import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['syncButton', 'feedback', 'feedbackText', 'logoutButton'];
    static values = {
        providers: Array,
        syncLabel: String,
        syncingLabel: String,
        noProvidersMessage: String,
        syncingMessage: String,
        successMessage: String,
        timeoutMessage: String,
        failedMessage: String,
        responseStatusPrefix: String,
        logoutFailedMessage: String,
        noProviderLabel: String,
        detailsPrefix: String,
        providerFallbackLabel: String,
        unknownStatusLabel: String,
        newLabel: String,
    };

    connect() {
        this.renderIdleButton();
    }

    async sync() {
        if (this.providersValue.length === 0) {
            this.setFeedback(this.noProvidersMessageValue, 'error');
            return;
        }

        this.syncButtonTarget.disabled = true;
        this.syncButtonTarget.innerHTML = `${this.syncingLabelValue} ${this.renderProviderIcons()} <i class="fas fa-rotate fa-spin"></i>`;
        this.setFeedback(this.syncingMessageValue.replace('%providers%', this.providersLabel()), '');
        const controller = new AbortController();
        const timeoutId = window.setTimeout(() => controller.abort(), 45000);

        try {
            const response = await fetch('/api/streaming/sync', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                },
                signal: controller.signal,
            });

            const payload = await response.json().catch(() => null);

            if (!response.ok) {
                throw new Error(payload?.error || payload?.message || `${this.responseStatusPrefixValue} ${response.status}`);
            }

            const result = payload?.result || {};
            const inserted = result.totalInserted ?? 0;
            const skipped = result.totalSkipped ?? 0;
            const xpAwarded = result.totalXpAwarded ?? 0;
            const providerSummary = this.buildProviderSummary(result.providers ?? []);

            this.setFeedback(
                this.successMessageValue
                    .replace('%inserted%', String(inserted))
                    .replace('%skipped%', String(skipped))
                    .replace('%xp%', String(xpAwarded))
                    .replace('%details%', providerSummary),
                'success'
            );

            if (inserted > 0 || xpAwarded > 0) {
                window.setTimeout(() => window.location.reload(), 900);
            }
        } catch (error) {
            const message = error?.name === 'AbortError'
                ? this.timeoutMessageValue
                : (error.message || this.failedMessageValue);

            this.setFeedback(message, 'error');
        } finally {
            window.clearTimeout(timeoutId);
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
                throw new Error(`${this.responseStatusPrefixValue} ${response.status}`);
            }

            await response.json();
            window.location.href = '/?t=' + Date.now();
        } catch (error) {
            this.setFeedback(error.message || this.logoutFailedMessageValue, 'error');

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
        this.syncButtonTarget.innerHTML = `${this.syncLabelValue} ${this.renderProviderIcons()} <i class="fas fa-rotate"></i>`;
    }

    renderProviderIcons() {
        if (this.providersValue.length === 0) {
            return '<span class="sync-providers"><i class="fas fa-link-slash sync-provider-icon sync-provider-icon--empty"></i></span>';
        }

        const icons = this.providersValue.map((provider) => {
            if (provider === 'spotify') {
                return '<i class="fab fa-spotify sync-provider-icon sync-provider-icon--spotify"></i>';
            }

            if (provider === 'youtube') {
                return '<i class="fab fa-youtube sync-provider-icon sync-provider-icon--youtube"></i>';
            }

            if (provider === 'apple_music') {
                return '<i class="fab fa-apple sync-provider-icon sync-provider-icon--apple"></i>';
            }

            return '<i class="fas fa-wave-square"></i>';
        }).join('');

        return `<span class="sync-providers">${icons}</span>`;
    }

    providersLabel() {
        if (this.providersValue.length === 0) {
            return this.noProviderLabelValue;
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
            const provider = providerResult.provider ?? this.providerFallbackLabelValue;
            const inserted = providerResult.inserted ?? 0;
            const xpAwarded = providerResult.xpAwarded ?? 0;
            const status = providerResult.status ?? this.unknownStatusLabelValue;

            return `${provider}: ${status}, ${inserted} ${this.newLabelValue}, +${xpAwarded} XP`;
        });

        return ` ${this.detailsPrefixValue} ${chunks.join(' | ')}.`;
    }
}
