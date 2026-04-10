import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['email', 'password', 'submit', 'error'];

    async submit(event) {
        event.preventDefault();
        this.setError('');
        this.setLoading(true);

        try {
            const response = await fetch('/api/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    email: this.emailTarget.value,
                    password: this.passwordTarget.value,
                }),
                credentials: 'include',
            });

            if (!response.ok) {
                const payload = await response.json().catch(() => null);
                throw new Error(payload?.message || payload?.error || `Statut de reponse : ${response.status}`);
            }

            window.location.href = '/app/passport';
        } catch (error) {
            this.setError(error.message || 'Connexion impossible pour le moment.');
            this.setLoading(false);
        }
    }

    setLoading(isLoading) {
        if (!this.hasSubmitTarget) {
            return;
        }

        this.submitTarget.disabled = isLoading;
        this.submitTarget.textContent = isLoading ? 'Connexion...' : 'Login to Passport';
    }

    setError(message) {
        if (!this.hasErrorTarget) {
            return;
        }

        this.errorTarget.hidden = message === '';
        this.errorTarget.textContent = message;
    }
}
