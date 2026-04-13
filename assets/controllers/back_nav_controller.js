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
            window.location.href = this.fallbackValue;
        }
    }
}
