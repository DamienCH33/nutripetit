import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'submitBtn', 'hint'];

    validate() {
        const value = this.inputTarget.value.replace(/\D/g, '');
        this.inputTarget.value = value;

        const remaining = 13 - value.length;
        if (remaining > 0) {
            this.hintTarget.textContent = remaining + ' chiffres restants';
            this.submitBtnTarget.disabled = true;
        } else if (this.isValidEan13(value)) {
            this.hintTarget.textContent = '✓ Code-barres valide';
            this.hintTarget.style.color = 'var(--np-score-ideal)';
            this.submitBtnTarget.disabled = false;
        } else {
            this.hintTarget.textContent = '✗ Code-barres invalide (somme de contrôle)';
            this.hintTarget.style.color = 'var(--np-score-discouraged)';
            this.submitBtnTarget.disabled = true;
        }
    }

    submit() {
        const ean = this.inputTarget.value;
        if (!this.isValidEan13(ean)) return;

        let url = '/app/scan/' + ean;
        const age = localStorage.getItem('np_baby_age_months');
        if (age && /^\d+$/.test(age)) {
            url += '?age=' + age;
        }
        window.location.href = url;
    }

    isValidEan13(ean) {
        if (!/^\d{13}$/.test(ean)) return false;
        const digits = ean.split('').map(Number);
        const checksum = digits.pop();
        let sum = 0;
        digits.forEach((d, i) => {
            sum += d * (i % 2 === 0 ? 1 : 3);
        });
        return (10 - (sum % 10)) % 10 === checksum;
    }
}
