import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['sheet', 'copyButton', 'status', 'scanner', 'scannerVideo', 'scannerStatus'];
    static values = {
        publicUrl: String,
    };

    connect() {
        this.isOpen = false;
        this.stream = null;
        this.scanFrame = null;
        this.barcodeDetector = 'BarcodeDetector' in window
            ? new window.BarcodeDetector({ formats: ['qr_code'] })
            : null;

        this.handleDocumentClick = this.handleDocumentClick.bind(this);
        this.handleEscape = this.handleEscape.bind(this);
        document.addEventListener('click', this.handleDocumentClick);
        document.addEventListener('keydown', this.handleEscape);
    }

    disconnect() {
        document.removeEventListener('click', this.handleDocumentClick);
        document.removeEventListener('keydown', this.handleEscape);
        this.stopScanner();
    }

    toggle(event) {
        event.preventDefault();

        if (this.isOpen) {
            this.close();
            return;
        }

        this.open();
    }

    close() {
        this.isOpen = false;
        this.stopScanner();
        this.sheetTarget.hidden = true;
        this.element.classList.remove('is-open');
        this.setStatus('');
    }

    open() {
        this.isOpen = true;
        this.sheetTarget.hidden = false;
        this.element.classList.add('is-open');
    }

    async copy(event) {
        event.preventDefault();

        if (!this.hasPublicUrlValue || this.publicUrlValue === '') {
            this.setStatus('Public link unavailable.');
            return;
        }

        try {
            await navigator.clipboard.writeText(this.publicUrlValue);
            this.copyButtonTarget.textContent = 'Copied';
            this.setStatus('Public link copied.');
        } catch (error) {
            this.setStatus('Copy failed.');
        }

        window.setTimeout(() => {
            if (this.hasCopyButtonTarget) {
                this.copyButtonTarget.textContent = 'Copy link';
            }
        }, 1200);
    }

    async startScanner(event) {
        event.preventDefault();

        if (!navigator.mediaDevices?.getUserMedia) {
            this.setScannerStatus('Camera unavailable on this device.');
            this.openScanner();
            return;
        }

        if (!this.barcodeDetector) {
            this.setScannerStatus('QR scanning is not supported in this browser.');
            this.openScanner();
            return;
        }

        this.openScanner();
        this.setScannerStatus('Starting camera...');

        try {
            this.stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' },
                audio: false,
            });

            this.scannerVideoTarget.srcObject = this.stream;
            await this.scannerVideoTarget.play();
            this.setScannerStatus('Point the camera at a QR code.');
            this.scanLoop();
        } catch (error) {
            this.setScannerStatus('Camera access denied or unavailable.');
        }
    }

    closeScanner(event) {
        if (event) {
            event.preventDefault();
        }

        this.stopScanner();
        this.scannerTarget.hidden = true;
    }

    openScanner() {
        this.scannerTarget.hidden = false;
    }

    stopScanner() {
        if (this.scanFrame) {
            window.cancelAnimationFrame(this.scanFrame);
            this.scanFrame = null;
        }

        if (this.hasScannerVideoTarget) {
            this.scannerVideoTarget.pause();
            this.scannerVideoTarget.srcObject = null;
        }

        if (this.stream) {
            this.stream.getTracks().forEach((track) => track.stop());
            this.stream = null;
        }
    }

    async scanLoop() {
        if (!this.barcodeDetector || !this.hasScannerVideoTarget) {
            return;
        }

        try {
            const barcodes = await this.barcodeDetector.detect(this.scannerVideoTarget);
            const qrCode = barcodes.find((barcode) => barcode.rawValue);

            if (qrCode?.rawValue) {
                this.setScannerStatus('QR detected. Opening...');
                this.stopScanner();
                window.location.href = qrCode.rawValue;
                return;
            }
        } catch (error) {
            this.setScannerStatus('Unable to scan this QR code.');
        }

        this.scanFrame = window.requestAnimationFrame(() => this.scanLoop());
    }

    handleDocumentClick(event) {
        if (!this.isOpen) {
            return;
        }

        if (this.element.contains(event.target)) {
            return;
        }

        this.close();
    }

    handleEscape(event) {
        if (event.key !== 'Escape') {
            return;
        }

        if (!this.isOpen) {
            return;
        }

        if (this.hasScannerTarget && !this.scannerTarget.hidden) {
            this.closeScanner();
            return;
        }

        this.close();
    }

    setStatus(message) {
        if (!this.hasStatusTarget) {
            return;
        }

        this.statusTarget.textContent = message;
    }

    setScannerStatus(message) {
        if (!this.hasScannerStatusTarget) {
            return;
        }

        this.scannerStatusTarget.textContent = message;
    }
}
