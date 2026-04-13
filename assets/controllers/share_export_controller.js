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

            const canvas = await window.html2canvas(this.cardTarget, {
                backgroundColor: '#0B0E14',
                scale: 3,
                useCORS: true,
            });

            const link = document.createElement('a');
            link.download = 'byas-passport-story.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
            this.setStatus('PNG ready for Instagram Stories.');
        } catch (error) {
            this.setStatus('PNG export failed. Try again in a moment.');
        } finally {
            this.downloadTarget.disabled = false;
            this.downloadTarget.textContent = 'Download PNG';
        }
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
