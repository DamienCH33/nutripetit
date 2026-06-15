import { Controller } from "@hotwired/stimulus";
export default class extends Controller {
    connect() {
        if (!localStorage.getItem("np_baby_age_months")) {
            this.element.classList.add("np-age-prompt--visible");
        }
    }
}
