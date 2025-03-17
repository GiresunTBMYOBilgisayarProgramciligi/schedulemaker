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

    /**
     *
     * @type {Modal}
     * @see /assets/js/myHTMLElements.js
     */
    const modal = new Modal();
    const spinner = new Spinner();

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
                return;
            }
        }
        const form = event.target;
        let data = new FormData(form);
        if (importFileInput) {
            let file = importFileInput.files[0]
            data.append("file", file)
        }
        modal.prepareModal(form.getAttribute("title"));
        spinner.showSpinner(modal.body)
        modal.showModal();

        fetch(form.action, {
            method: form.method || "POST",
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: data,
        })
            .then(response => response.json())
            .then((data) => {
                console.log(data)
                if (data.errors) {
                    console.log(data.errors)
                }
                if (data.status === "error") {

                    const statusClass = data.status === "error" ? "danger" : data.status;
                    modal.body.classList.add("text-bg-" + statusClass)
                    const message = Array.isArray(data.msg)
                        ? `<ul>${data.msg.map((item) => `<li>${item}</li>`).join("")}</ul>`
                        : data.msg;
                    spinner.removeSpinner();
                    modal.body.innerHTML = message;
                } else {
                    modal.body.classList.add("text-bg-" + data.status)

                    const message = Array.isArray(data.msg)
                        ? `<ul>${data.msg.map((item) => `<li>${item}</li>`).join("")}</ul>`
                        : data.msg;
                    spinner.removeSpinner();
                    modal.body.innerHTML = message;
                }

                modal.cancelButton.addEventListener("click", () => {
                    if (data.redirect) {
                        if (data.redirect === "back") {
                            window.history.back()
                        } else
                            window.location.href = data.redirect;
                    }
                    if (!form.classList.contains("updateForm")) {
                        form.reset();
                    }
                });

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
        let lessonRow = event.target.closest('tr');
        event.preventDefault();
        const form = event.target;

        modal.prepareModal(gettext.confirmDelete, gettext.deleteMessage, true)
        modal.confirmButton.textContent = gettext.delete
        modal.showModal()
        modal.confirmButton.addEventListener("click", () => {

            spinner.showSpinner(modal.body)
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
                    if (data.status === "error") {
                        const statusClass = data.status === "error" ? "danger" : data.status;
                        modal.body.classList.add("text-bg-" + statusClass)
                        const message = Array.isArray(data.msg)
                            ? `<ul>${data.msg.map((item) => `<li>${item}</li>`).join("")}</ul>`
                            : data.msg;
                        spinner.removeSpinner()
                        modal.body.innerHTML = message;
                    } else {
                        modal.body.classList.add("text-bg-" + data.status)

                        const message = Array.isArray(data.msg)
                            ? `<ul>${data.msg.map((item) => `<li>${item}</li>`).join("")}</ul>`
                            : data.msg;
                        spinner.removeSpinner()
                        modal.body.innerHTML = message;
                        if (lessonRow) {
                            lessonRow.remove();
                        } else {
                            window.history.back()
                        }
                    }

                    if (data.redirect) {
                        modal.cancelButton.addEventListener("click", () => {
                            if (data.redirect === "back") {
                                window.history.back()
                            } else
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
            console.log("Buders başka derse bağlanacak");
            data.append('child_lesson_id', lessonId);
            let conbineModal = new Modal("CombineLessonModal");
            conbineModal.closeModal();
            fetchForm(form, data)

        } else if (childLessonId != 0) {
            console.log("Bu derse bir ders bağlanacak");
            data.append("parent_lesson_id", lessonId)
            let conbineModal = new Modal("CombineLessonModal");
            conbineModal.closeModal();
            fetchForm(form, data)
        }
    }

    function fetchForm(form,data) {
        let modal = new Modal();
        modal.prepareModal(form.getAttribute("title"));
        spinner.showSpinner(modal.body)
        modal.showModal();
        return fetch(form.action, {
            method: form.method || "POST",
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: data,
        })
            .then(response => response.json())
            .then((data) => {
                console.log(data)
                if (data.errors) {
                    console.log(data.errors)
                }
                if (data.status === "error") {

                    const statusClass = data.status === "error" ? "danger" : data.status;
                    modal.body.classList.add("text-bg-" + statusClass)
                    const message = Array.isArray(data.msg)
                        ? `<ul>${data.msg.map((item) => `<li>${item}</li>`).join("")}</ul>`
                        : data.msg;
                    spinner.removeSpinner();
                    modal.body.innerHTML = message;
                } else {
                    modal.body.classList.add("text-bg-" + data.status)

                    const message = Array.isArray(data.msg)
                        ? `<ul>${data.msg.map((item) => `<li>${item}</li>`).join("")}</ul>`
                        : data.msg;
                    spinner.removeSpinner();
                    modal.body.innerHTML = message;
                }

                modal.cancelButton.addEventListener("click", () => {
                    if (data.redirect) {
                        if (data.redirect === "back") {
                            window.history.back()
                        } else
                            window.location.href = data.redirect;
                    }
                    if (!form.classList.contains("updateForm")) {
                        form.reset();
                    }
                });

            })
            .catch((error) => {
                modal.title.textContent = "Hata";
                modal.body.classList.add("text-bg-danger");
                modal.body.innerHTML = error;
                console.error(error);
            });
    }
});
