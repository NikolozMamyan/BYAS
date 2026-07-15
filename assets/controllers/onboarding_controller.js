import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'card',
        'continue',
        'search',
        'previous',
        'next',
        'status',
        'empty',
    ];

    static values = {
        batchSize: { type: Number, default: 6 },
    };

    connect() {
        this.currentPage = 0;
        this.query = '';
        this.render();
    }

    select(event) {
        this.setSelectedCard(event.currentTarget);
    }

    selectByKeyboard(event) {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        event.preventDefault();
        this.setSelectedCard(event.currentTarget);
    }

    search() {
        this.query = this.searchTarget.value.trim().toLocaleLowerCase();
        this.currentPage = 0;
        this.render();
    }

    previous() {
        if (this.currentPage === 0) {
            return;
        }

        this.currentPage -= 1;
        this.render();
    }

    next() {
        const pageCount = this.pageCount();

        if (this.currentPage >= pageCount - 1) {
            return;
        }

        this.currentPage += 1;
        this.render();
    }

    setSelectedCard(selectedCard) {
        this.cardTargets.forEach((card) => {
            const isSelected = card === selectedCard;
            card.classList.toggle('is-selected', isSelected);
            card.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
        });

        this.updateContinueUrl();
    }

    filteredCards() {
        if (this.query === '') {
            return this.cardTargets;
        }

        return this.cardTargets.filter((card) => (
            card.dataset.fandomName.includes(this.query)
            || card.dataset.fandomLabel.includes(this.query)
        ));
    }

    pageCount() {
        return Math.max(1, Math.ceil(this.filteredCards().length / this.batchSizeValue));
    }

    render() {
        const filtered = this.filteredCards();
        const pageCount = this.pageCount();
        this.currentPage = Math.min(this.currentPage, pageCount - 1);

        const start = this.currentPage * this.batchSizeValue;
        const visibleCards = new Set(filtered.slice(start, start + this.batchSizeValue));

        this.cardTargets.forEach((card) => {
            card.hidden = !visibleCards.has(card);
        });

        this.emptyTarget.hidden = filtered.length !== 0;
        this.previousTarget.disabled = this.currentPage === 0 || filtered.length === 0;
        this.nextTarget.disabled = this.currentPage >= pageCount - 1 || filtered.length === 0;
        this.statusTarget.textContent = `${this.currentPage + 1} / ${pageCount}`;
        this.updateContinueUrl();
    }

    updateContinueUrl() {
        const selected = this.cardTargets.find((card) => card.classList.contains('is-selected'));

        if (!selected) {
            return;
        }

        const url = new URL(this.continueTarget.href, window.location.origin);
        url.searchParams.set('fandom', selected.dataset.fandomSlug);
        this.continueTarget.href = url.pathname + url.search;
    }
}
