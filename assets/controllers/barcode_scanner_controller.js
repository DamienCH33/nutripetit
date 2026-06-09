import { Controller } from "@hotwired/stimulus";
import { BrowserMultiFormatReader } from "@zxing/browser";
import { BarcodeFormat, DecodeHintType } from "@zxing/library";

/**
 * Barcode Scanner Controller
 *
 * Active la caméra, détecte un code-barres EAN-13 et redirige vers /app/scan/{ean}.
 */
export default class extends Controller {
    static targets = ["video", "status", "error", "startBtn", "stopBtn"];
    static values = {
        scanUrlPattern: String,
    };

    connect() {
        const hints = new Map();
        hints.set(DecodeHintType.POSSIBLE_FORMATS, [
            BarcodeFormat.EAN_13,
            BarcodeFormat.EAN_8,
            BarcodeFormat.UPC_A,
            BarcodeFormat.UPC_E,
        ]);
        hints.set(DecodeHintType.TRY_HARDER, true);
        hints.set(DecodeHintType.ASSUME_GS1, false);

        this.codeReader = new BrowserMultiFormatReader(hints, {
            delayBetweenScanAttempts: 100,
            delayBetweenScanSuccess: 1000,
        });
        this.controls = null;
        this.scanning = false;
        this.activeStream = null;
        this.consecutiveDetections = new Map();

        setTimeout(() => this.start(), 300);
    }

    disconnect() {
        this.stop();
    }

    async start() {
        if (this.scanning) return;

        this.hideError();
        this.updateStatus("Démarrage de la caméra...");

        try {
            // Configuration optimisée pour codes-barres
            const constraints = {
                video: {
                    facingMode: { ideal: "environment" },
                    width: { ideal: 1920 },
                    height: { ideal: 1080 },
                    focusMode: "continuous",
                    advanced: [
                        { focusMode: "continuous" },
                        { exposureMode: "continuous" },
                        { whiteBalanceMode: "continuous" },
                    ],
                },
            };

            const stream =
                await navigator.mediaDevices.getUserMedia(constraints);
            const track = stream.getVideoTracks()[0];

            // Tenter d'activer la torche/flash si dispo
            const capabilities = track.getCapabilities
                ? track.getCapabilities()
                : {};
            if (capabilities.torch) {
                try {
                    await track.applyConstraints({
                        advanced: [{ torch: false }],
                    });
                } catch (e) {}
            }

            this.videoTarget.srcObject = stream;
            await this.videoTarget.play();
            this.activeStream = stream;
            this.scanning = true;
            this.toggleButtons(true);
            this.updateStatus("Pointez le code-barres dans le cadre.");

            // Lancer ZXing sur le flux
            this.controls = await this.codeReader.decodeFromVideoElement(
                this.videoTarget,
                (result, err) => {
                    if (result) {
                        this.handleResult(result.getText());
                    }
                },
            );
        } catch (err) {
            this.scanning = false;
            this.toggleButtons(false);

            if (err.name === "NotAllowedError") {
                this.showError(
                    "Accès caméra refusé. Activez-le dans les paramètres du navigateur.",
                );
            } else if (err.name === "NotFoundError") {
                this.showError("Aucune caméra disponible.");
            } else {
                this.showError("Erreur caméra : " + err.message);
            }
        }
    }

    stop() {
        if (this.controls) {
            this.controls.stop();
            this.controls = null;
        }
        if (this.activeStream) {
            this.activeStream.getTracks().forEach((t) => t.stop());
            this.activeStream = null;
        }
        this.scanning = false;
        this.toggleButtons(false);
        this.updateStatus("Scanner arrêté.");
    }

    toggleButtons(scanning) {
        if (this.hasStartBtnTarget) {
            this.startBtnTarget.hidden = scanning;
        }
        if (this.hasStopBtnTarget) {
            this.stopBtnTarget.hidden = !scanning;
        }
    }

    updateStatus(message) {
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = message;
        }
    }

    showError(message) {
        if (this.hasErrorTarget) {
            this.errorTarget.textContent = message;
            this.errorTarget.hidden = false;
        }
    }

    hideError() {
        if (this.hasErrorTarget) {
            this.errorTarget.hidden = true;
        }
    }
    handleResult(ean) {
        if (!/^\d{13}$/.test(ean)) {
            this.updateStatus(`Continuez à scanner...`);
            return;
        }

        // Validation checksum EAN-13 côté JS
        if (!this.isValidEan13(ean)) {
            this.updateStatus("Code détecté invalide, continuez...");
            return;
        }

        // Demander 2 détections identiques avant d'accepter
        const count = (this.consecutiveDetections.get(ean) || 0) + 1;
        this.consecutiveDetections.set(ean, count);

        if (count < 2) {
            this.updateStatus(`Validation en cours... (${count}/2)`);
            return;
        }

        this.stop();
        this.updateStatus(`Code validé : ${ean}. Redirection...`);

        let url = this.scanUrlPatternValue.replace("__EAN__", ean);
        const age = localStorage.getItem("np_baby_age_months");
        if (age && /^\d+$/.test(age)) {
            url += "?age=" + age;
        }
        window.location.href = url;
    }

    isValidEan13(ean) {
        if (!/^\d{13}$/.test(ean)) return false;
        const digits = ean.split("").map(Number);
        const checksum = digits.pop();
        let sum = 0;
        digits.forEach((d, i) => {
            sum += d * (i % 2 === 0 ? 1 : 3);
        });
        return (10 - (sum % 10)) % 10 === checksum;
    }
}
