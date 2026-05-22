import { Controller } from '@hotwired/stimulus';

const DISMISS_KEY = 'byas-pwa-install-dismissed-at';
const INSTALLED_KEY = 'byas-pwa-installed';
const DISMISS_TTL = 7 * 24 * 60 * 60 * 1000;

export default class extends Controller {
    static targets = ['dialog', 'status', 'installButton'];

    connect() {
        this.deferredPrompt = null;
        this.installAvailable = false;
        this.dismissedForSession = false;
        this.isStandalone = this.detectInstalledDisplayMode();

        if (this.isStandalone) {
            window.localStorage.setItem(INSTALLED_KEY, '1');
        }

        this.handleBeforeInstallPrompt = this.handleBeforeInstallPrompt.bind(this);
        this.handleAppInstalled = this.handleAppInstalled.bind(this);
        this.handleEscape = this.handleEscape.bind(this);

        if (this.isInstalled()) {
            this.close();
            return;
        }

        window.addEventListener('beforeinstallprompt', this.handleBeforeInstallPrompt);
        window.addEventListener('appinstalled', this.handleAppInstalled);
        document.addEventListener('keydown', this.handleEscape);
    }

    disconnect() {
        window.removeEventListener('beforeinstallprompt', this.handleBeforeInstallPrompt);
        window.removeEventListener('appinstalled', this.handleAppInstalled);
        document.removeEventListener('keydown', this.handleEscape);
    }

    handleBeforeInstallPrompt(event) {
        event.preventDefault();
        this.deferredPrompt = event;
        this.installAvailable = true;

        if (this.isInstalled() || this.dismissedForSession || this.wasDismissedRecently()) {
            return;
        }

        this.open();
    }

    handleAppInstalled() {
        this.isStandalone = true;
        window.localStorage.setItem(INSTALLED_KEY, '1');
        this.deferredPrompt = null;
        this.installAvailable = false;
        this.setStatus('BYAS is now installed on your device.');
        this.close();
    }

    async install(event) {
        event.preventDefault();

        if (!this.installAvailable || this.deferredPrompt === null) {
            this.setStatus('Installation is not available yet on this device.');
            return;
        }

        this.setStatus('');
        await this.deferredPrompt.prompt();

        const { outcome } = await this.deferredPrompt.userChoice;
        if (outcome === 'accepted') {
            window.localStorage.setItem(INSTALLED_KEY, '1');
        }

        this.deferredPrompt = null;
        this.installAvailable = false;

        if (outcome === 'accepted') {
            this.setStatus('Installation started.');
            this.close();
            return;
        }

        this.remindLater();
    }

    later(event) {
        event.preventDefault();
        this.remindLater();
    }

    remindLater() {
        this.dismissedForSession = true;
        window.localStorage.setItem(DISMISS_KEY, String(Date.now()));
        this.deferredPrompt = null;
        this.installAvailable = false;
        this.setStatus('No problem. We will remind you later.');
        this.close();
    }

    open() {
        if (!this.hasDialogTarget || this.isInstalled()) {
            return;
        }

        this.dialogTarget.hidden = false;
        this.dialogTarget.setAttribute('aria-hidden', 'false');
        document.body.classList.add('pwa-install-open');
    }

    close() {
        if (!this.hasDialogTarget) {
            return;
        }

        this.dialogTarget.hidden = true;
        this.dialogTarget.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('pwa-install-open');
    }

    handleEscape(event) {
        if (event.key === 'Escape' && this.hasDialogTarget && !this.dialogTarget.hidden) {
            this.remindLater();
        }
    }

    wasDismissedRecently() {
        const rawValue = window.localStorage.getItem(DISMISS_KEY);

        if (rawValue === null) {
            return false;
        }

        const timestamp = Number.parseInt(rawValue, 10);

        if (Number.isNaN(timestamp)) {
            return false;
        }

        return (Date.now() - timestamp) < DISMISS_TTL;
    }

    isInstalled() {
        return this.detectInstalledDisplayMode() || window.localStorage.getItem(INSTALLED_KEY) === '1';
    }

    detectInstalledDisplayMode() {
        const displayModes = [
            '(display-mode: standalone)',
            '(display-mode: minimal-ui)',
            '(display-mode: fullscreen)',
            '(display-mode: window-controls-overlay)',
        ];

        return displayModes.some((query) => window.matchMedia(query).matches)
            || window.navigator.standalone === true
            || document.referrer.startsWith('android-app://');
    }

    setStatus(message) {
        if (!this.hasStatusTarget) {
            return;
        }

        this.statusTarget.textContent = message;
    }
}
