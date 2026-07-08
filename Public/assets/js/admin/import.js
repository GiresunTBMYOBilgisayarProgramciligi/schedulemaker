/**
 * İçe Aktarma (Import) işlemlerinde kullanılacak form fonksiyonları.
 * myHTMLElements.js'ye bağımlıdır (Modal ve Toast kullanır).
 */
document.addEventListener("DOMContentLoaded", function () {
    const importForms = document.querySelectorAll(".js-import-form");

    importForms.forEach(form => {
        form.addEventListener("submit", function (event) {
            event.preventDefault();
            
            const fileInput = form.querySelector("input[type='file']");
            if (!fileInput || !fileInput.files.length) {
                new Toast().prepareToast("Hata", "Lütfen bir dosya seçin", "danger");
                return;
            }

            let data = new FormData(form);
            data.append("file", fileInput.files[0]);
            let modal = new Modal();
            modal.prepareModal(form.getAttribute("title") || "İçe Aktarma İşlemi", "", false, true, "lg");
            spinner.showSpinner(modal.body);
            modal.showModal();

            fetch(form.action, {
                method: form.method || "POST",
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: data,
            })
            .then(response => response.json())
            .then(data => {
                spinner.removeSpinner();
                const statusClass = data.status === "error" ? "danger" : data.status;
                modal.body.classList.add("text-bg-" + statusClass);
                
                let message = Array.isArray(data.msg)
                    ? `<ul>${data.msg.map((item) => `<li>${item}</li>`).join("")}</ul>`
                    : data.msg;

                const addedItems = data.addedLessons || data.addedUsers;
                if (addedItems && Object.keys(addedItems).length > 0) {
                    const addedList = Object.values(addedItems).map(name => `<li>${name}</li>`).join('');
                    message += `<hr><details class="mt-2 text-start">
                        <summary class="fw-bold" style="cursor: pointer; padding: 10px; background: rgba(255,255,255,0.2); border-radius: 5px;">
                            <i class="bi bi-plus-circle me-2"></i> Eklenen Kayıtlar (${Object.keys(addedItems).length})
                        </summary>
                        <div style="max-height: 200px; overflow-y: auto; background: rgba(0,0,0,0.05); padding: 10px; border-radius: 0 0 5px 5px;">
                            <ul class="small mb-0">
                                ${addedList}
                            </ul>
                        </div>
                    </details>`;
                }

                const updatedItems = data.updatedLessons || data.updatedUsers;
                if (updatedItems && Object.keys(updatedItems).length > 0) {
                    const updatedList = Object.values(updatedItems).map(name => `<li>${name}</li>`).join('');
                    message += `<hr><details class="mt-2 text-start">
                        <summary class="fw-bold" style="cursor: pointer; padding: 10px; background: rgba(255,255,255,0.2); border-radius: 5px;">
                            <i class="bi bi-pencil-square me-2"></i> Güncellenen Kayıtlar (${Object.keys(updatedItems).length})
                        </summary>
                        <div style="max-height: 200px; overflow-y: auto; background: rgba(0,0,0,0.05); padding: 10px; border-radius: 0 0 5px 5px;">
                            <ul class="small mb-0">
                                ${updatedList}
                            </ul>
                        </div>
                    </details>`;
                }

                if (data.errors && Array.isArray(data.errors) && data.errors.length > 0) {
                    message += `<hr><details class="error-list text-start mt-2" open>
                        <summary class="fw-bold text-bg-danger" style="cursor: pointer; padding: 10px; border-radius: 5px;">
                            <i class="bi bi-exclamation-triangle me-2"></i> Hata Detayları (${data.errors.length}):
                        </summary>
                        <div class="text-bg-danger" style="max-height: 300px; overflow-y: auto; padding: 10px; border-radius: 0 0 5px 5px;">
                            <ul class="small mb-0">
                                ${data.errors.map(err => `<li>${err}</li>`).join('')}
                            </ul>
                        </div>
                    </details>`;
                }

                modal.body.innerHTML = message;

                if (data.status === "success" && form.classList.contains("js-reset-on-success")) {
                    form.reset();
                }
            })
            .catch(error => {
                spinner.removeSpinner();
                modal.title.textContent = "Hata";
                modal.body.classList.add("text-bg-danger");
                modal.body.innerHTML = "Bir hata oluştu: " + error;
                console.error(error);
            });
        });
    });
});
