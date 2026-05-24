import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['search', 'provider', 'type', 'item', 'sheet', 'backdrop'];

    connect() {
        this.isSheetOpen = false;
        this.handleResize = this.handleResize.bind(this);
        window.addEventListener('resize', this.handleResize);
        this.showInitialItems();
        this.observeItems();
        this.closeFilters(true);
    }

    disconnect() {
        this.observer?.disconnect();
        window.removeEventListener('resize', this.handleResize);
        document.body.classList.remove('sheet-open');
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
            const provider = this.normalizeProvider(item.dataset.provider || '');
            const type = (item.dataset.type || '').toLowerCase();

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

        if (window.matchMedia('(max-width: 768px)').matches) {
            this.closeFilters();
        }
    }

    openFilters() {
        if (!this.hasSheetTarget || !window.matchMedia('(max-width: 768px)').matches) {
            return;
        }

        this.isSheetOpen = true;
        this.sheetTarget.classList.add('is-open');
        if (this.hasBackdropTarget) {
            this.backdropTarget.hidden = false;
            window.requestAnimationFrame(() => {
                this.backdropTarget.classList.add('is-visible');
            });
        }
        document.body.classList.add('sheet-open');
    }

    closeFilters(skipDelay = false) {
        if (!this.hasSheetTarget) {
            return;
        }

        this.isSheetOpen = false;
        this.sheetTarget.classList.remove('is-open');
        if (this.hasBackdropTarget) {
            this.backdropTarget.classList.remove('is-visible');
            window.setTimeout(() => {
                if (!this.isSheetOpen && this.hasBackdropTarget) {
                    this.backdropTarget.hidden = true;
                }
            }, skipDelay ? 0 : 180);
        }
        document.body.classList.remove('sheet-open');
    }

    handleResize() {
        if (!window.matchMedia('(max-width: 768px)').matches) {
            this.closeFilters(true);
        }
    }

    normalizeProvider(provider) {
        const normalized = provider.toLowerCase();

        if (normalized === 'apple_music' || normalized === 'apple-music') {
            return 'apple';
        }

        if (normalized === 'youtube_music') {
            return 'youtube';
        }

        return normalized;
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
