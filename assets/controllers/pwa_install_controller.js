import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['button', 'iosHelp'];

    connect() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js').catch(() => {});
        }

        this.deferredPrompt = null;
        this.isIos = /iphone|ipad|ipod/i.test(window.navigator.userAgent);
        this.isStandalone = window.navigator.standalone === true
            || window.matchMedia('(display-mode: standalone)').matches;

        this._onBeforeInstall = (e) => {
            e.preventDefault();
            this.deferredPrompt = e;
            this.showButton();
        };
        window.addEventListener('beforeinstallprompt', this._onBeforeInstall);

        if (this.isIos && !this.isStandalone) {
            this.showButton();
        }
    }

    disconnect() {
        window.removeEventListener('beforeinstallprompt', this._onBeforeInstall);
    }

    showButton() {
        if (this.hasButtonTarget) {
            this.buttonTarget.hidden = false;
        }
    }

    async install() {
        if (this.deferredPrompt) {
            this.deferredPrompt.prompt();
            await this.deferredPrompt.userChoice;
            this.deferredPrompt = null;
            this.buttonTarget.hidden = true;
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
