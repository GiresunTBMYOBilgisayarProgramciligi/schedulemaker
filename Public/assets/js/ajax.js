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

    function toUpperCaseTR(value) {
        return value.toLocaleUpperCase('tr-TR');
    }

    // Input elementi için maskeleme fonksiyonu
    function initializeCodeMask(inputElement) {
        // Değişiklikleri dinle
        inputElement.addEventListener('input', function (e) {
            let value = toUpperCaseTR(e.target.value); // Otomatik büyük harfe çevir
            // Sadece harf ve rakamları al (tire ve noktayı kaldır)
            value = value.replace(/[^A-ZĞÜŞİÖÇI0-9]/g, '');

            let formattedValue = '';

            // İlk kısım (harf kısmı)
            const letters = value.match(/[A-ZĞÜŞİÖÇI]+/)?.[0] || '';
            formattedValue = letters;

            // Eğer harf kısmından sonra rakam varsa, otomatik tire ekle
            const numbers = value.slice(letters.length);
            if (numbers.length > 0) {
                formattedValue += '-' + numbers;
            }

            // Nokta ekleme kontrolü (eğer son kısımda nokta ve rakam varsa)
            if (formattedValue.includes('-') && numbers.length > 3) {
                const mainPart = numbers.slice(0, 3);
                const decimal = numbers.slice(3);
                formattedValue = letters + '-' + mainPart;
                if (decimal) {
                    formattedValue += '.' + decimal;
                }
            }

            // Maksimum uzunluk kontrolü
            formattedValue = formattedValue.slice(0, 10);

            // Değeri güncelle
            e.target.value = formattedValue;
        });

        // Blur olduğunda format kontrolü
        inputElement.addEventListener('input', function (e) {
            const value = e.target.value;
            const isValid = validateCourseCode(value);

            // Geçerlilik durumuna göre görsel geri bildirim
            if (isValid) {
                inputElement.classList.remove('is-invalid');
                inputElement.classList.add('is-valid');
            } else {
                inputElement.classList.remove('is-valid');
                inputElement.classList.add('is-invalid');
            }
        });
    }

    // Ders kodu formatını doğrula
    function validateCourseCode(code) {
        // Format: AAA-NNN veya AAA-NNN.N
        const pattern = /^[A-ZĞÜŞİÖÇI]{2,4}-\d{3}(?:\.\d)?$/;
        return pattern.test(code);
    }

    //code id li bir input varsa
    const codeInput = document.querySelector("input#code");
    if (codeInput) {
        initializeCodeMask(codeInput);
    }
    /**
     *
     * @type {Modal}
     * @see /assets/js/myHTMLElements.js
     */
    const modal = new Modal();

    function handleAjaxForm(event) {
        event.preventDefault();
        if (codeInput) {
            if (!validateCourseCode(codeInput.value)) {
                return;
            }
        }
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
                if (data.status === "error") {
                    const statusClass = data.status === "error" ? "danger" : data.status;
                    modal.body.classList.add("text-bg-" + statusClass)
                    const message = Array.isArray(data.msg)
                        ? `<ul>${data.msg.map((item) => `<li>${item}</li>`).join("")}</ul>`
                        : data.msg;
                    modal.removeSpinner()
                    modal.body.innerHTML = message;
                } else {
                    modal.body.classList.add("text-bg-" + data.status)

                    const message = Array.isArray(data.msg)
                        ? `<ul>${data.msg.map((item) => `<li>${item}</li>`).join("")}</ul>`
                        : data.msg;
                    modal.removeSpinner()
                    modal.body.innerHTML = message;
                }

                modal.cancelButton.addEventListener("click", () => {
                    if (data.redirect) {
                        if (data.redirect === "back") {
                            window.history.back()
                        } else
                            window.location.href = data.redirect;
                    }
                    if (!form.classList.contains("updateForm")){
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
                    if (data.status === "error") {
                        const statusClass = data.status === "error" ? "danger" : data.status;
                        modal.body.classList.add("text-bg-" + statusClass)
                        const message = Array.isArray(data.msg)
                            ? `<ul>${data.msg.map((item) => `<li>${item}</li>`).join("")}</ul>`
                            : data.msg;
                        modal.removeSpinner()
                        modal.body.innerHTML = message;
                    } else {
                        modal.body.classList.add("text-bg-" + data.status)

                        const message = Array.isArray(data.msg)
                            ? `<ul>${data.msg.map((item) => `<li>${item}</li>`).join("")}</ul>`
                            : data.msg;
                        modal.removeSpinner()
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
});
