class Modal {
    constructor() {
        this.dialog = "";
        this.content = "";
        this.header = "";
        this.title = "";
        this.closeButton = "";
        this.body = "";
        this.footer = "";
        this.cancelButton = "";
        this.confirmButton = "";
        this.modal = "";

        this.spinner = ""

        this.createModal();
    }

    /**
     *
     * @param size sm,lg,xl modal size
     */
    createModal(size = "sm") {
        let sizes = {"sm": "modal-sm", "lg": "modal-lg", "xl": "modal-xl"}

        this.modal = document.createElement("div");
        this.modal.classList.add("modal", "fade");
        this.modal.id = "ajaxModal";
        this.modal.setAttribute("tabindex", "-1");
        this.modal.setAttribute("aria-labelledby", "ajaxModalLabel");
        this.modal.setAttribute("aria-hidden", "true");

        this.dialog = document.createElement("div");
        this.dialog.classList.add("modal-dialog",sizes[size]);

        this.content = document.createElement("div");
        this.content.classList.add("modal-content");

        this.header = document.createElement("div");
        this.header.classList.add("modal-header")

        this.title = document.createElement("div");
        this.title.classList.add("modal-title");
        this.title.id = "ajaxModalLabel";

        this.closeButton = document.createElement("button");
        this.closeButton.type = "button";
        this.closeButton.classList.add("btn-close");
        this.closeButton.setAttribute("data-bs-dismiss", "modal");
        this.closeButton.setAttribute("aria-label", "Close");

        this.body = document.createElement("div");
        this.body.classList.add("modal-body");

        this.footer = document.createElement("div");
        this.footer.classList.add("modal-footer")

        this.cancelButton = document.createElement("button");
        this.cancelButton.type = "button";
        this.cancelButton.id = "modalCancel";
        this.cancelButton.classList.add("btn", "btn-primary")
        this.cancelButton.setAttribute("data-bs-dismiss", "modal");

        this.confirmButton = document.createElement("button");
        this.confirmButton.type = "button";
        this.confirmButton.id = "modalConfirm";
        this.confirmButton.classList.add("btn", "btn-success")

        this.footer.appendChild(this.cancelButton);
        this.footer.appendChild(this.confirmButton);

        this.header.appendChild(this.title);
        this.header.appendChild(this.closeButton);

        this.content.appendChild(this.header);
        this.content.appendChild(this.body);
        this.content.appendChild(this.footer);

        this.dialog.appendChild(this.content);

        this.modal.appendChild(this.dialog);
    }

    addSpinner() {
        this.spinner = document.createElement("div");
        this.spinner.classList.add("d-flex", "justify-content-center")
        this.spinner.innerHTML = `<div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
              </div>`;

        this.body.appendChild(this.spinner)
    }

    removeSpinner() {
        this.spinner.remove();
    }

    prepareModal(title = "", content = "", confirmButton = false) {
        this.title.textContent = title.trim();
        this.body.textContent = content.trim()
        this.cancelButton.textContent = gettext.close;
        if (!confirmButton) {
            this.confirmButton.remove();
        } else {
            this.confirmButton.textContent = gettext.ok;
        }
        //Kapatıldığında modal sayfadan silinecek
        this.cancelButton.addEventListener("click", () => {
            this.body.classList.remove("text-bg-danger", "text-bg-success")
            this.modal.remove()
        })
    }

    //todo toast

}

document.addEventListener("DOMContentLoaded", function () {
    const body = document.body;

    // Formlara olay dinleyicileri ekleme
    document.querySelectorAll(".ajaxForm").forEach((form) => {
        form.addEventListener("submit", handleAjaxForm);
    });

    document.querySelectorAll(".ajaxFormDelete").forEach((form) => {
        form.addEventListener("submit", handleAjaxDelete);
    });


    const modal = new Modal();
    body.appendChild(modal.modal);

    var bootstrapModal = new bootstrap.Modal(modal.modal);

    function handleAjaxForm(event) {
        event.preventDefault();

        const form = event.target;

        modal.prepareModal(form.getAttribute("title"));
        modal.addSpinner();
        bootstrapModal.show();

        fetch(form.action, {
            method: form.method || "POST",
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: new FormData(form),
        })
            .then(response => response.json())
            .then((data) => {
                const statusClass = data.status === "error" ? "danger" : data.status;
                modal.body.classList.add("text-bg-" + statusClass)

                const message = Array.isArray(data.msg)
                    ? `<ul>${data.msg.map((item) => `<li>${item}</li>`).join("")}</ul>`
                    : data.msg;
                modal.removeSpinner()
                modal.body.innerHTML = message;

                if (data.redirect) {
                    modal.cancelButton.addEventListener("click", () => {
                        window.location.href = data.redirect;
                    });
                }
            })
            .catch((error) => {
                modal.title.textContent = "Hata";
                modal.body.classList.add("text-bg-danger");
                modal.body.innerHTML = error;
                console.error(error);
            });
    }


    // Silme işlemi için onay fonksiyonu
    function handleAjaxDelete(event) {
        event.preventDefault();
        const form = event.target;

        modal.prepareModal(gettext.confirmDelete, gettext.deleteMessage, true)
        modal.confirmButton.textContent=gettext.delete
        bootstrapModal.show()
        modal.confirmButton.addEventListener("click", () => {

            modal.addSpinner();
            modal.confirmButton.remove();
            fetch(form.action, {
                method: form.method || "POST",
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new FormData(form),
            })
                .then(response => response.json())
                .then((data) => {
                    const statusClass = data.status === "error" ? "danger" : data.status;
                    modal.body.classList.add("text-bg-" + statusClass)

                    const message = Array.isArray(data.msg)
                        ? `<ul>${data.msg.map((item) => `<li>${item}</li>`).join("")}</ul>`
                        : data.msg;
                    modal.removeSpinner()
                    modal.body.innerHTML = message;

                    if (data.redirect) {
                        modal.cancelButton.addEventListener("click", () => {
                            window.location.href = data.redirect;
                        });
                    }
                })
                .catch((error) => {
                    modal.title.textContent = "Hata";
                    modal.body.classList.add("text-bg-danger");
                    modal.body.innerHTML = error;
                    console.error(error);
                });
        });
    }
});
