import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['displayName', 'email', 'password', 'submit', 'error'];

    async submit(event) {
        event.preventDefault();
        this.setError('');
        this.setLoading(true);

        try {
            const response = await fetch('/api/register', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    displayName: this.displayNameTarget.value,
                    email: this.emailTarget.value,
                    password: this.passwordTarget.value,
                }),
                credentials: 'include',
            });

            if (!response.ok) {
                const payload = await response.json().catch(() => null);
                throw new Error(payload?.message || payload?.error || `Statut de reponse : ${response.status}`);
            }

            window.location.href = this.redirectTo();
        } catch (error) {
            this.setError(error.message || 'Creation de compte impossible pour le moment.');
            this.setLoading(false);
        }
    }

    redirectTo() {
        const next = this.element.dataset.next;

        if (next && next.startsWith('/') && !next.startsWith('//')) {
            return next;
        }

        return '/app/passport';
    }

    setLoading(isLoading) {
        if (!this.hasSubmitTarget) {
            return;
        }

        this.submitTarget.disabled = isLoading;
        this.submitTarget.textContent = isLoading ? 'Creation...' : 'Join the Passport';
    }

    setError(message) {
        if (!this.hasErrorTarget) {
            return;
        }

        this.errorTarget.hidden = message === '';
        this.errorTarget.textContent = message;
    }
}
