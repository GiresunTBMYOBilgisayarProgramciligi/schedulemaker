/**
 * Öncesinde myHTMLElemens.js yüklenmeli
 */
document.addEventListener("DOMContentLoaded", function () {
    const body = document.body;

    // Formlara olay dinleyicileri ekleme
    document.querySelectorAll(".ajaxForm").forEach((form) => {
        form.addEventListener("submit", handleAjaxForm);
    });

    document.querySelectorAll(".ajaxFormDelete").forEach((form) => {
        form.addEventListener("submit", handleAjaxDelete);
    });

    /**
     *
     * @type {Modal}
     * @see /assets/js/myHTMLElements.js
     */
    const modal = new Modal();

    function handleAjaxForm(event) {
        event.preventDefault();

        const form = event.target;

        modal.prepareModal(form.getAttribute("title"));
        modal.addSpinner();
        modal.showModal();

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
        modal.showModal()
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
