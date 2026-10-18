import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['button', 'iosHelp'];

    // Le service worker et la capture de beforeinstallprompt sont faits dans le
    // <head> du layout (base/landing.html.twig), avant le chargement de ce module.
    connect() {
        this.isIos = /iphone|ipad|ipod/i.test(window.navigator.userAgent)
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1); // iPadOS
        this.isStandalone = window.navigator.standalone === true
            || window.matchMedia('(display-mode: standalone)').matches;

        if (this.isStandalone) {
            return;
        }

        this._onInstallable = () => this.showButton();
        window.addEventListener('np:installable', this._onInstallable);
        this._onInstalled = () => this.hideButton();
        window.addEventListener('appinstalled', this._onInstalled);

        if (window.npInstallPrompt || this.isIos) {
            this.showButton();
        }
    }

    disconnect() {
        window.removeEventListener('np:installable', this._onInstallable);
        window.removeEventListener('appinstalled', this._onInstalled);
    }

    hideButton() {
        if (this.hasButtonTarget) {
            this.buttonTarget.hidden = true;
        }
    }

    showButton() {
        if (this.hasButtonTarget) {
            this.buttonTarget.hidden = false;
        }
    }

    async install() {
        const prompt = window.npInstallPrompt;
        if (prompt) {
            prompt.prompt();
            await prompt.userChoice;
            // Un événement ne sert qu'une fois ; le navigateur en renverra un si besoin.
            window.npInstallPrompt = null;
            this.hideButton();
        } else if (this.isIos && this.hasIosHelpTarget) {
            this.iosHelpTarget.hidden = false;
        }
    }

    closeHelp() {
        if (this.hasIosHelpTarget) {
            this.iosHelpTarget.hidden = true;
        }
    }
}
