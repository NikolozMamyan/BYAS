import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        fallback: String,
    };

    go(event) {
        event.preventDefault();

        if (window.history.length > 1) {
            window.history.back();
            return;
        }

        if (this.fallbackValue) {
            this.visit(this.fallbackValue);
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
