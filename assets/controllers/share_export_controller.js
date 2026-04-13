import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['card', 'qr', 'download', 'status'];
    static values = {
        url: String,
    };

    connect() {
        this.initQr();
    }

    print() {
        window.print();
    }

    async download() {
        if (!window.html2canvas) {
            this.setStatus('PNG export is still loading. Try again in a moment.');
            return;
        }

        this.downloadTarget.disabled = true;
        this.downloadTarget.textContent = 'Rendering...';
        this.setStatus('Rendering your story card...');

        try {
            await this.waitForImages();
            const canvas = await this.renderCard();
            await this.deliverCanvas(canvas);
        } catch (error) {
            this.setStatus('PNG export failed. Try again in a moment.');
        } finally {
            this.downloadTarget.disabled = false;
            this.downloadTarget.textContent = 'Download PNG';
        }
    }

    async renderCard() {
        return await window.html2canvas(this.cardTarget, {
            backgroundColor: '#0B0E14',
            scale: this.getRenderScale(),
            useCORS: true,
            logging: false,
            imageTimeout: 8000,
            removeContainer: true,
            windowWidth: this.cardTarget.scrollWidth,
            windowHeight: this.cardTarget.scrollHeight,
        });
    }

    getRenderScale() {
        const isMobileViewport = window.innerWidth <= 768;

        if (!isMobileViewport) {
            const devicePixelRatio = Math.max(1, window.devicePixelRatio || 1);

            return Math.min(3, devicePixelRatio * 2);
        }

        return 1;
    }

    async deliverCanvas(canvas) {
        const fileName = 'byas-passport-story.png';
        const blob = await this.canvasToBlob(canvas);

        if (!blob) {
            throw new Error('Canvas export failed');
        }

        const link = document.createElement('a');
        const blobUrl = URL.createObjectURL(blob);
        link.download = fileName;
        link.href = blobUrl;
        link.rel = 'noopener';
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(blobUrl), 60000);
        this.setStatus('PNG ready for download.');
    }

    canvasToBlob(canvas) {
        return new Promise((resolve) => {
            canvas.toBlob((blob) => resolve(blob), 'image/png');
        });
    }

    async waitForImages() {
        const images = Array.from(this.cardTarget.querySelectorAll('img'));

        if (images.length === 0) {
            return;
        }

        await Promise.all(images.map((image) => {
            if (image.complete && image.naturalWidth > 0) {
                return Promise.resolve();
            }

            return new Promise((resolve) => {
                const done = () => {
                    image.removeEventListener('load', done);
                    image.removeEventListener('error', done);
                    resolve();
                };

                image.addEventListener('load', done, { once: true });
                image.addEventListener('error', done, { once: true });
            });
        }));
    }

    initQr() {
        if (!window.QRCode) {
            this.qrTarget.textContent = 'QR';
            return;
        }

        this.qrTarget.innerHTML = '';
        new window.QRCode(this.qrTarget, {
            text: this.urlValue,
            width: 72,
            height: 72,
            colorDark: '#0B0E14',
            colorLight: '#FFFFFF',
            correctLevel: window.QRCode.CorrectLevel.M,
        });
    }

    setStatus(message) {
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = message;
        }
    }
}
