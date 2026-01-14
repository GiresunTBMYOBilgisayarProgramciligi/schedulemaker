/**
 * Öncesinde myHTMLElemens.js yüklenmeli
 */
document.addEventListener("DOMContentLoaded", function () {
    //code id li bir input varsa
    const codeInput = document.querySelector("input#code");
    const importFileInput = document.querySelector("input#importFile")
    // Formlara olay dinleyicileri ekleme
    document.querySelectorAll(".ajaxForm").forEach((form) => {
        form.addEventListener("submit", handleAjaxForm);
    });

    document.querySelectorAll(".ajaxFormDelete").forEach((form) => {
        form.addEventListener("submit", handleAjaxDelete);
    });

    document.querySelectorAll(".ajaxFormCombineLesson").forEach((form) => {
        form.addEventListener("submit", handleAjaxCombineLesson);
    })

    document.querySelectorAll(".ajaxDeleteParentLesson").forEach((form) => {
        form.addEventListener("submit", handleAjaxDeleteParentLesson);
    })

    function handleAjaxForm(event) {
        event.preventDefault();
        /**
         * Code input varsa maskeyi doğrula
         */
        if (codeInput) {
            /**
             * @see formEvents.js
             */
            if (!validateCourseCode(codeInput.value)) {
                new Toast().prepareToast('Hata', "Ders kodu kurala uygun değil", "danger");
                return;
            }
        }
        const form = event.target;
        let data = new FormData(form);
        if (importFileInput) {
            let file = importFileInput.files[0]
            data.append("file", file)
        }
        fetchForm(form, data);
    }

    // Silme işlemi için onay fonksiyonu
    function handleAjaxDelete(event) {
        let lessonRow = event.target.closest('tr');
        event.preventDefault();
        const form = event.target;
        let confirmDeleteModal = new Modal();
        confirmDeleteModal.prepareModal(gettext.confirmDelete, gettext.deleteMessage, true)
        confirmDeleteModal.confirmButton.textContent = gettext.delete
        confirmDeleteModal.showModal()
        confirmDeleteModal.confirmButton.addEventListener("click", () => {
            confirmDeleteModal.closeModal();
            fetchForm(form, new FormData(form)).then(() => {
                if (lessonRow) {
                    dataTable.row(lessonRow).remove().draw();
                    //lessonRow.remove();
                } else {
                    window.history.back()
                }
            })
        });
    }

    function handleAjaxCombineLesson(event) {
        event.preventDefault();
        const form = event.target;
        let data = new FormData(form);
        /**
         * Sayfası açık olan derse parent olarak eklenecek ders
         * @type {FormDataEntryValue}
         */
        let parentLessonId = data.get('parent_lesson_id');
        /**
         * Sayfası açık olan dersin parnt olarak ekleneceği ders
         * @type {FormDataEntryValue}
         */
        let childLessonId = data.get('child_lesson_id');
        /**
         * Sayfası açık olan ders
         * @type {FormDataEntryValue}
         */
        let lessonId = data.get('lesson_id');
        if (parentLessonId == 0 && childLessonId == 0) {
            new Toast().prepareToast('Hata', "Lütfen bir seçim yapın", "danger");
            return false;
        } else if (parentLessonId != 0 && childLessonId != 0) {
            new Toast().prepareToast('Hata', "Lütfen sadece bir seçim yapın", "danger");
            return false;
        } else if (parentLessonId != 0) {
            data.append('child_lesson_id', lessonId);
            let conbineModal = new Modal("CombineLessonModal");
            conbineModal.hideModal();
            fetchForm(form, data)

        } else if (childLessonId != 0) {
            data.append("parent_lesson_id", lessonId)
            let conbineModal = new Modal("CombineLessonModal");
            conbineModal.hideModal();
            fetchForm(form, data)
        }
    }

    function handleAjaxDeleteParentLesson(event) {
        event.preventDefault();
        const form = event.target;
        let data = new FormData(form);
        let deleteParentModal = new Modal()
        deleteParentModal.prepareModal("Bağlantı Silme Onayı", "Bu dersin bağlantısını silmek istediğinizden emin miziniz?", true, true);
        deleteParentModal.confirmButton.textContent = gettext.delete
        deleteParentModal.showModal();
        deleteParentModal.confirmButton.addEventListener("click", (event) => {
            deleteParentModal.hideModal();
            fetchForm(form, data)
        })
    }
    function fetchForm(form, data) {
        let isToast = form.getAttribute("data-toast") === "true";
        let redirectDelay = parseInt(form.getAttribute("data-redirect-delay")) || 0;
        let modal = null;
        let loadingToast = null;

        if (isToast) {
            loadingToast = new Toast();
            loadingToast.prepareToast(form.getAttribute("title") || "İşlem", "Lütfen bekleyin...", "info", false);
        } else {
            modal = new Modal();
            modal.prepareModal(form.getAttribute("title"), "", false, true, "lg");
            spinner.showSpinner(modal.body);
            modal.showModal();
        }

        return fetch(form.action, {
            method: form.method || "POST",
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: data,
        })
            .then(response => {
                if (loadingToast) loadingToast.closeToast();
                return response.json();
            })
            .then((data) => {
                console.log(data)
                const statusClass = data.status === "error" ? "danger" : data.status;
                let message = Array.isArray(data.msg)
                    ? `<ul>${data.msg.map((item) => `<li>${item}</li>`).join("")}</ul>`
                    : data.msg;

                if (data.errors && data.errors.length > 0) {
                    message += `<hr><div class="error-list text-start" style="max-height: 300px; overflow-y: auto; background: rgba(0,0,0,0.1); padding: 10px; border-radius: 5px;">
                        <h6 class="fw-bold">Hata Detayları:</h6>
                        <ul class="small mb-0">
                            ${data.errors.map(err => `<li>${err}</li>`).join('')}
                        </ul>
                    </div>`;
                }

                const handleRedirect = () => {
                    if (data.redirect) {
                        console.log("redirect")
                        if (data.redirect === "back")
                            window.history.back()
                        else if (data.redirect === "self")
                            window.location.reload();
                        else
                            window.location.href = data.redirect;
                    }
                };

                if (isToast) {
                    let toastObj = new Toast();
                    toastObj.prepareToast(form.getAttribute("title") || "İşlem", message, statusClass, true, redirectDelay || 5000);
                    if (data.redirect) {
                        toastObj.toast.addEventListener("hidden.bs.toast", handleRedirect);
                    }
                } else {
                    modal.body.classList.add("text-bg-" + statusClass)
                    spinner.removeSpinner();
                    modal.body.innerHTML = message;
                }

                if (data.status === "success") {
                    if (!form.classList.contains("updateForm")) {
                        form.reset();
                    }
                }

                if (modal) {
                    modal.cancelButton.addEventListener("click", handleRedirect);
                }
            })
            .catch((error) => {
                if (loadingToast) loadingToast.closeToast();
                if (modal) {
                    modal.title.textContent = "Hata";
                    modal.body.classList.add("text-bg-danger");
                    modal.body.innerHTML = error;
                } else {
                    new Toast().prepareToast("Hata", error, "danger");
                }
                console.error(error);
            });
    }
});
