import { Controller } from '@hotwired/stimulus';

/**
 * Baby Profile Controller
 *
 * Gère la configuration du profil bébé en V1 :
 * - Lecture/écriture localStorage (clés "babyName" et "babyAgeMonths")
 * - Mise à jour réactive de l'affichage (âge, tranche, prénom)
 * - Toast de confirmation à chaque sauvegarde
 *
 * En V2, sera remplacé par un Live Component connecté à une entité Doctrine.
 */
export default class extends Controller {
    static targets = [
        'nameInput',
        'ageSlider',
        'ageDisplay',
        'rangeCard',
        'rangeLabel',
        'rangeDesc',
        'currentProfile',
        'currentAgeText',
        'currentRangeText',
        'titleDisplay',
        'toast',
        'resetButton',
    ];

    static values = {
        ageRanges: Array,
    };

    connect() {
        this.toastTimeout = null;
        this.loadProfile();
        this.updateRangeDisplay();
    }

    /**
     * Charge le profil depuis localStorage et hydrate l'UI
     */
    loadProfile() {
        const savedName = localStorage.getItem('babyName') || '';
        const savedAge = parseInt(localStorage.getItem('babyAgeMonths') || '12', 10);

        if (this.hasNameInputTarget) {
            this.nameInputTarget.value = savedName;
        }
        if (this.hasAgeSliderTarget) {
            this.ageSliderTarget.value = savedAge;
        }

        this.updateAgeDisplay(savedAge);
        this.updateTitle(savedName);
        this.updateCurrentProfile(savedName, savedAge);
    }

    /**
     * Action : prénom modifié
     */
    updateName(event) {
        const name = event.target.value.trim();

        if (name) {
            localStorage.setItem('babyName', name);
        } else {
            localStorage.removeItem('babyName');
        }

        this.updateTitle(name);
        this.updateCurrentProfile(name, this.getCurrentAge());
        this.showToast();
    }

    /**
     * Action : âge modifié (slider)
     */
    updateAge(event) {
        const age = parseInt(event.target.value, 10);

        localStorage.setItem('babyAgeMonths', age.toString());

        this.updateAgeDisplay(age);
        this.updateRangeDisplay();
        this.updateCurrentProfile(this.getCurrentName(), age);
        this.showToast();
    }

    /**
     * Action : réinitialiser le profil
     */
    reset() {
        if (!confirm('Effacer le profil de votre enfant ?')) {
            return;
        }

        localStorage.removeItem('babyName');
        localStorage.removeItem('babyAgeMonths');

        if (this.hasNameInputTarget) {
            this.nameInputTarget.value = '';
        }
        if (this.hasAgeSliderTarget) {
            this.ageSliderTarget.value = 12;
        }

        this.updateAgeDisplay(12);
        this.updateTitle('');
        this.updateRangeDisplay();
        this.updateCurrentProfile('', null);
    }

    /**
     * Met à jour le display de l'âge (gestion singulier/pluriel + mois/an)
     */
    updateAgeDisplay(months) {
        if (!this.hasAgeDisplayTarget) return;

        const valueEl = this.ageDisplayTarget.querySelector('.np-baby-age-display__value');
        const unitEl = this.ageDisplayTarget.querySelector('.np-baby-age-display__unit');

        if (months === 0) {
            valueEl.textContent = '0';
            unitEl.textContent = 'mois (à la naissance)';
        } else if (months < 12) {
            valueEl.textContent = months;
            unitEl.textContent = months > 1 ? 'mois' : 'mois';
        } else {
            const years = Math.floor(months / 12);
            const remainingMonths = months % 12;
            if (remainingMonths === 0) {
                valueEl.textContent = years;
                unitEl.textContent = years > 1 ? 'ans' : 'an';
            } else {
                valueEl.textContent = `${years}a ${remainingMonths}m`;
                unitEl.textContent = '';
            }
        }
    }

    /**
     * Met à jour le titre de la page avec le prénom si présent
     */
    updateTitle(name) {
        if (!this.hasTitleDisplayTarget) return;
        this.titleDisplayTarget.textContent = name ? name : 'Mon enfant';
    }

    /**
     * Met à jour la carte tranche d'âge en fonction du slider
     */
    updateRangeDisplay() {
        const age = this.getCurrentAge();
        const range = this.findAgeRange(age);

        if (!range) return;

        if (this.hasRangeLabelTarget) {
            this.rangeLabelTarget.textContent = range.label;
        }
        if (this.hasRangeDescTarget) {
            this.rangeDescTarget.textContent = `${range.minMonths}-${range.maxMonths} mois — ${range.description}`;
        }
    }

    /**
     * Met à jour la carte "Profil enregistré" visible quand le profil existe
     */
    updateCurrentProfile(name, age) {
        if (!this.hasCurrentProfileTarget) return;

        const hasProfile = name || (age !== null && localStorage.getItem('babyAgeMonths'));

        if (hasProfile) {
            this.currentProfileTarget.hidden = false;
            if (this.hasResetButtonTarget) {
                this.resetButtonTarget.hidden = false;
            }

            if (this.hasCurrentAgeTextTarget) {
                const displayName = name || 'Votre enfant';
                this.currentAgeTextTarget.textContent = `${displayName} — ${age} mois`;
            }
            if (this.hasCurrentRangeTextTarget) {
                const range = this.findAgeRange(age);
                this.currentRangeTextTarget.textContent = range ? range.label : '';
            }
        } else {
            this.currentProfileTarget.hidden = true;
            if (this.hasResetButtonTarget) {
                this.resetButtonTarget.hidden = true;
            }
        }
    }

    /**
     * Affiche le toast de confirmation (auto-hide 2s)
     */
    showToast() {
        if (!this.hasToastTarget) return;

        clearTimeout(this.toastTimeout);
        this.toastTarget.hidden = false;
        this.toastTarget.classList.add('np-baby-toast--visible');

        this.toastTimeout = setTimeout(() => {
            this.toastTarget.classList.remove('np-baby-toast--visible');
            setTimeout(() => {
                this.toastTarget.hidden = true;
            }, 300);
        }, 2000);
    }

    /**
     * Utilitaires
     */
    getCurrentAge() {
        return this.hasAgeSliderTarget ? parseInt(this.ageSliderTarget.value, 10) : 12;
    }

    getCurrentName() {
        return this.hasNameInputTarget ? this.nameInputTarget.value.trim() : '';
    }

    findAgeRange(months) {
        return this.ageRangesValue.find(
            r => months >= r.minMonths && months <= r.maxMonths
        );
    }
}
