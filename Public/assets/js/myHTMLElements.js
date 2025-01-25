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
        this.isOpen = false;

        this.initializeModal();
    }

    /**
     *
     * @param size sm,lg,xl modal size
     */
    initializeModal(size = "sm") {//todo arkaplan rengi için de sını f seçimi ekle Tosastta olduğu gibi
        let sizes = {"sm": "modal-sm", "lg": "modal-lg", "xl": "modal-xl"}

        this.modal = document.createElement("div");
        this.modal.classList.add("modal", "fade");
        this.modal.id = "ajaxModal";
        this.modal.setAttribute("tabindex", "-1");
        this.modal.setAttribute("aria-labelledby", "ajaxModalLabel");
        this.modal.setAttribute("aria-hidden", "true");

        this.dialog = document.createElement("div");
        this.dialog.classList.add("modal-dialog", sizes[size]);

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

    prepareModal(title = "", content = "", showConfirmButton = false, showCancelButton = true) {
        this.title.innerHTML = title.trim();
        this.body.innerHTML = content.trim()

        if (!showConfirmButton) {
            this.confirmButton.remove();
        } else {
            this.footer.appendChild(this.confirmButton);
            this.confirmButton.textContent = gettext.ok;
        }
        if (!showCancelButton) {
            this.cancelButton.remove()
        } else {
            this.footer.appendChild(this.cancelButton);
            this.cancelButton.textContent = gettext.close;
        }
        //Kapatıldığında modal sayfadan silinecek
        this.cancelButton.addEventListener("click", () => {
            this.body.classList.remove("text-bg-danger", "text-bg-success")
            this.modal.remove()
        })
    }

    showModal() {
        if (this.isOpen) {
            console.warn("Modal zaten açık.");
            return;
        }
        this.isOpen = true;
        const bootstrapModal = new bootstrap.Modal(this.modal);
        bootstrapModal.show();
        this.modal.addEventListener("hidden.bs.modal", () => {
            this.isOpen = false;
            this.modal.remove();
        });
    }

    closeModal() {
        // Bootstrap modal kapatma işlemi
        const bootstrapModal = bootstrap.Modal.getInstance(this.modal);
        if (bootstrapModal) {
            bootstrapModal.hide(); // Bootstrap üzerinden kapat
        }

        // Modal kapatıldıktan sonra DOM'dan kaldır
        this.modal.addEventListener("hidden.bs.modal", () => {
            this.modal.remove();
        });
    }
}

class Toast {
    constructor() {
        this.toast = null;
        this.header = null;
        this.body = null;
        this.closeButton = null;

        this.createToastContainer();
        this.createToast();
    }

    /**
     * Toast için bir container oluşturur
     */
    createToastContainer() {
        if (!document.querySelector("#toastContainer")) {
            const container = document.createElement("div");
            container.id = "toastContainer";
            container.classList.add("toast-container", "p-3");
            document.body.appendChild(container);
        }
    }

    /**
     * Bootstrap Toast yapılandırmasını oluşturur
     */
    createToast() {
        this.toast = document.createElement("div");
        this.toast.classList.add("toast");
        this.toast.setAttribute("role", "alert");
        this.toast.setAttribute("aria-live", "assertive");
        this.toast.setAttribute("aria-atomic", "true");

        // Toast header
        this.header = document.createElement("div");
        this.header.classList.add("toast-header");

        this.closeButton = document.createElement("button");
        this.closeButton.type = "button";
        this.closeButton.classList.add("btn-close");
        this.closeButton.setAttribute("data-bs-dismiss", "toast");
        this.closeButton.setAttribute("aria-label", "Close");

        this.body = document.createElement("div");
        this.body.classList.add("toast-body");

        this.header.appendChild(this.closeButton);
        this.toast.appendChild(this.header);
        this.toast.appendChild(this.body);
    }

    /**
     * Toast içeriğini hazırlar
     * @param {string} title Başlık
     * @param {string} message Mesaj
     * @param {string} type Toast türü: success, danger, info, warning
     * @param {boolean} autohide Otomatik gizleme
     * @param {number} delay Gizlenme süresi (ms)
     * @param {string} position Toast pozisyonu: top-left, top-right, bottom-left, bottom-right
     */
    prepareToast(title = "", message = "", type = "info", autohide = true, delay = 5000, position = "bottom-right") {
        this.header.innerHTML = `
            <i class="bi bi-info-square-fill me-1"></i> 
            <strong class="me-auto">${title}</strong>
        `;
        this.header.appendChild(this.closeButton);

        this.body.textContent = message;

        // Tipine göre sınıf ekle
        this.toast.classList.remove("text-bg-success", "text-bg-danger", "text-bg-info", "text-bg-warning");
        this.toast.classList.add(`text-bg-${type}`);

        // Container pozisyonunu ayarla
        const container = document.querySelector("#toastContainer");
        container.className = "toast-container p-3"; // Eski pozisyonları temizle
        const positions = {
            "top-left": ["top-0", "start-0"],
            "top-right": ["top-0", "end-0"],
            "bottom-left": ["bottom-0", "start-0"],
            "bottom-right": ["bottom-0", "end-0"],
        };
        container.classList.add("position-fixed", ...(positions[position] || positions["bottom-right"]));

        container.appendChild(this.toast);

        // Bootstrap Toast opsiyonları
        const toastOptions = {
            animation: true,
            autohide: autohide,
            delay: delay,
        };

        const bsToast = new bootstrap.Toast(this.toast, toastOptions);

        // Toast kapatıldığında DOM'dan kaldır
        this.toast.addEventListener("hidden.bs.toast", () => {
            this.toast.remove();
        });

        // Toast'u göster
        bsToast.show();
    }
}
