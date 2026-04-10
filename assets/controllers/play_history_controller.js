import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['search', 'provider', 'type', 'item'];

    connect() {
        this.showInitialItems();
        this.observeItems();
    }

    disconnect() {
        this.observer?.disconnect();
    }

    toggleDetails(event) {
        event.stopPropagation();

        const playItem = event.currentTarget.closest('.play-item');
        const icon = event.currentTarget.querySelector('i');

        playItem.classList.toggle('expanded');
        icon.style.transform = playItem.classList.contains('expanded') ? 'rotate(180deg)' : 'rotate(0deg)';
    }

    filter() {
        const searchTerm = this.hasSearchTarget ? this.searchTarget.value.toLowerCase() : '';
        const providerValue = this.hasProviderTarget ? this.providerTarget.value.toLowerCase() : '';
        const typeValue = this.hasTypeTarget ? this.typeTarget.value.toLowerCase() : '';

        this.itemTargets.forEach((item) => {
            const title = item.dataset.title || '';
            const artist = item.dataset.artist || '';
            const album = item.dataset.album || '';
            const provider = item.dataset.provider || '';
            const type = item.dataset.type || '';

            const matchesSearch = !searchTerm || title.includes(searchTerm) || artist.includes(searchTerm) || album.includes(searchTerm);
            const matchesProvider = !providerValue || provider === providerValue;
            const matchesType = !typeValue || type === typeValue;

            if (matchesSearch && matchesProvider && matchesType) {
                item.style.display = '';
                window.setTimeout(() => item.classList.add('visible'), 10);
            } else {
                item.style.display = 'none';
                item.classList.remove('visible');
            }
        });
    }

    showInitialItems() {
        window.setTimeout(() => {
            this.itemTargets.forEach((item, index) => {
                window.setTimeout(() => {
                    item.classList.add('visible');
                }, index * 50);
            });
        }, 100);
    }

    observeItems() {
        if (!window.IntersectionObserver) {
            return;
        }

        this.observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, { threshold: 0.1 });

        this.itemTargets.forEach((item) => this.observer.observe(item));
    }
}
