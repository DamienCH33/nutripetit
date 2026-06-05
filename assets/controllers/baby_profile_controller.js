import { Controller } from "@hotwired/stimulus";

/**
 * Baby Profile Controller
 *
 * Stockage localStorage uniquement : âge en mois.
 * Aucune donnée nominative (prénom supprimé pour conformité RGPD renforcée).
 */
export default class extends Controller {
    static targets = [
        "ageSlider",
        "ageDisplay",
        "rangeCard",
        "rangeLabel",
        "rangeDesc",
        "currentProfile",
        "currentAgeText",
        "currentRangeText",
        "toast",
        "resetButton",
    ];

    static values = {
        ageRanges: Array,
    };

    connect() {
        this.toastTimeout = null;
        this.loadProfile();
        this.updateRangeDisplay();
    }

    loadProfile() {
        const savedAge = parseInt(
            localStorage.getItem("babyAgeMonths") || "12",
            10,
        );

        if (this.hasAgeSliderTarget) {
            this.ageSliderTarget.value = savedAge;
        }

        this.updateAgeDisplay(savedAge);
        this.updateCurrentProfile(savedAge);
    }

    updateAge(event) {
        const age = parseInt(event.target.value, 10);

        localStorage.setItem("babyAgeMonths", age.toString());

        this.updateAgeDisplay(age);
        this.updateRangeDisplay();
        this.updateCurrentProfile(age);
        this.showToast();
    }

    reset() {
        if (!confirm("Effacer le profil de votre enfant ?")) {
            return;
        }

        localStorage.removeItem("babyAgeMonths");
        localStorage.removeItem("babyName"); // Au cas où une ancienne version aurait stocké

        if (this.hasAgeSliderTarget) {
            this.ageSliderTarget.value = 12;
        }

        this.updateAgeDisplay(12);
        this.updateRangeDisplay();
        this.updateCurrentProfile(null);
    }

    updateAgeDisplay(months) {
        if (!this.hasAgeDisplayTarget) return;

        const valueEl = this.ageDisplayTarget.querySelector(
            ".np-baby-age-display__value",
        );
        const unitEl = this.ageDisplayTarget.querySelector(
            ".np-baby-age-display__unit",
        );

        if (months === 0) {
            valueEl.textContent = "0";
            unitEl.textContent = "mois (à la naissance)";
        } else {
            valueEl.textContent = months;
            unitEl.textContent = months > 1 ? "mois" : "mois";
        }
    }

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

    updateCurrentProfile(age) {
        if (!this.hasCurrentProfileTarget) return;

        const hasProfile =
            age !== null && localStorage.getItem("babyAgeMonths");

        if (hasProfile) {
            this.currentProfileTarget.hidden = false;
            if (this.hasResetButtonTarget) {
                this.resetButtonTarget.hidden = false;
            }

            if (this.hasCurrentAgeTextTarget) {
                this.currentAgeTextTarget.textContent = `${age} mois`;
            }
            if (this.hasCurrentRangeTextTarget) {
                const range = this.findAgeRange(age);
                this.currentRangeTextTarget.textContent = range
                    ? range.label
                    : "";
            }
        } else {
            this.currentProfileTarget.hidden = true;
            if (this.hasResetButtonTarget) {
                this.resetButtonTarget.hidden = true;
            }
        }
    }

    showToast() {
        if (!this.hasToastTarget) return;

        clearTimeout(this.toastTimeout);
        this.toastTarget.hidden = false;
        this.toastTarget.classList.add("np-baby-toast--visible");

        this.toastTimeout = setTimeout(() => {
            this.toastTarget.classList.remove("np-baby-toast--visible");
            setTimeout(() => {
                this.toastTarget.hidden = true;
            }, 300);
        }, 2000);
    }

    getCurrentAge() {
        return this.hasAgeSliderTarget
            ? parseInt(this.ageSliderTarget.value, 10)
            : 12;
    }

    findAgeRange(months) {
        return this.ageRangesValue.find(
            (r) => months >= r.minMonths && months <= r.maxMonths,
        );
    }
}
