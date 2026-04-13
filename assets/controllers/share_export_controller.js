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
                scale: this.getRenderScale(),
                useCORS: true,
                logging: false,
            });

            await this.deliverCanvas(canvas);
        } catch (error) {
            this.setStatus('PNG export failed. Try again in a moment.');
        } finally {
            this.downloadTarget.disabled = false;
            this.downloadTarget.textContent = 'Download PNG';
        }
    }

    getRenderScale() {
        const isMobileViewport = window.innerWidth <= 768;
        const devicePixelRatio = Math.max(1, window.devicePixelRatio || 1);

        if (!isMobileViewport) {
            return Math.min(3, devicePixelRatio * 2);
        }

        const deviceMemory = Number(window.navigator.deviceMemory || 0);

        if (deviceMemory > 0 && deviceMemory <= 4) {
            return 1.6;
        }

        return Math.min(2, Math.max(1.4, devicePixelRatio));
    }

    async deliverCanvas(canvas) {
        const fileName = 'byas-passport-story.png';

        if (this.shouldUsePreviewDownload()) {
            const blob = await this.canvasToBlob(canvas);

            if (!blob) {
                throw new Error('Canvas export failed');
            }

            const blobUrl = URL.createObjectURL(blob);
            const preview = window.open(blobUrl, '_blank', 'noopener,noreferrer');

            if (!preview) {
                window.location.href = blobUrl;
            }

            window.setTimeout(() => URL.revokeObjectURL(blobUrl), 60000);
            this.setStatus('Image opened. Long press or use share/save from your browser.');

            return;
        }

        const link = document.createElement('a');
        link.download = fileName;
        link.href = canvas.toDataURL('image/png');
        document.body.appendChild(link);
        link.click();
        link.remove();
        this.setStatus('PNG ready for download.');
    }

    shouldUsePreviewDownload() {
        const userAgent = window.navigator.userAgent || '';
        const isIOS = /iPhone|iPad|iPod/i.test(userAgent);
        const isAndroid = /Android/i.test(userAgent);
        const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;

        return isIOS || (isAndroid && isTouchDevice);
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
