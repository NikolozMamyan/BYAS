import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['button', 'status'];
    static values = {
        developerToken: String,
        appName: String,
        linkUrl: String,
        csrfToken: String,
    };

    async connect(event) {
        event.preventDefault();
        this.setLoading(true);
        this.setStatus('Opening Apple Music authorization...');

        try {
            const MusicKit = await this.waitForMusicKit();

            MusicKit.configure({
                developerToken: this.developerTokenValue,
                app: {
                    name: this.appNameValue || 'BYAS',
                    build: '1.0.0',
                },
            });

            const music = MusicKit.getInstance();
            const musicUserToken = await music.authorize();

            if (!musicUserToken) {
                throw new Error('Apple Music authorization failed.');
            }

            this.setStatus('Linking Apple Music...');

            const response = await fetch(this.linkUrlValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({
                    _token: this.csrfTokenValue,
                    musicUserToken,
                    storefrontId: music.storefrontId || null,
                }),
            });

            const payload = await response.json().catch(() => null);

            if (!response.ok) {
                throw new Error(payload?.message || `Response status: ${response.status}`);
            }

            window.location.href = payload?.redirectTo || '/app/passport/settings';
        } catch (error) {
            this.setStatus(error.message || 'Apple Music connection failed.');
            this.setLoading(false);
        }
    }

    async waitForMusicKit() {
        if (window.MusicKit) {
            return window.MusicKit;
        }

        return await new Promise((resolve, reject) => {
            let attempts = 0;

            const interval = window.setInterval(() => {
                attempts += 1;

                if (window.MusicKit) {
                    window.clearInterval(interval);
                    resolve(window.MusicKit);
                    return;
                }

                if (attempts >= 80) {
                    window.clearInterval(interval);
                    reject(new Error('MusicKit script failed to load.'));
                }
            }, 100);
        });
    }

    setLoading(isLoading) {
        if (!this.hasButtonTarget) {
            return;
        }

        this.buttonTarget.disabled = isLoading;
        this.buttonTarget.textContent = isLoading ? 'Connecting...' : 'Connect Apple Music';
    }

    setStatus(message) {
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = message;
        }
    }
}
