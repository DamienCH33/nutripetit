import { Controller } from '@hotwired/stimulus';

/**
 * Product Tabs Controller
 *
 * Gère le switch entre les 3 onglets de la page produit :
 * Détails / Nutriments / Sources
 */
export default class extends Controller {
    static targets = ['tab', 'panel'];

    switch(event) {
        const targetTab = event.currentTarget.dataset.tab;

        // Mettre à jour les boutons (état actif)
        this.tabTargets.forEach(tab => {
            if (tab.dataset.tab === targetTab) {
                tab.classList.add('np-tabs__tab--active');
            } else {
                tab.classList.remove('np-tabs__tab--active');
            }
        });

        // Mettre à jour les panneaux (visibilité)
        this.panelTargets.forEach(panel => {
            if (panel.dataset.tab === targetTab) {
                panel.classList.add('np-tabs__panel--active');
                panel.hidden = false;
            } else {
                panel.classList.remove('np-tabs__panel--active');
                panel.hidden = true;
            }
        });
    }
}
