/**
 * Program dışa aktarma işlemlerinde kullanılacak işlemler
 * Öncesinde myHTMLElemens.js yüklenmeli
 */
document.addEventListener("DOMContentLoaded", function () {
    const departmentSelect = document.getElementById("department_id");
    const programSelect = document.getElementById("program_id");
    const lecturerSelect = document.getElementById("lecturer_id");
    const classroomSelect = document.getElementById("classroom_id");

    // Tüm click eventlerini tek noktadan yakala
    document.addEventListener("click", async function (event) {
        const button = event.target.closest("button"); // En yakın button'u bul
        if (!button) return; // Eğer button değilse devam etme

        // Sadece Excel dışa aktarma butonları için (id sonunda Export olanlar)
        if (button.id.endsWith("Export")) {
            const ownerType = button.id === "singlePageExport" ? button.dataset.ownerType :
                button.id === "lecturerExport" ? "user" :
                    button.id === "classroomExport" ? "classroom" : "program";

            showExportOptionsModal(ownerType, async (options) => {
                let data = new FormData();
                data.append("type", "lesson");
                data.append("semester", document.getElementById("semester")?.value || "");
                data.append("academic_year", document.getElementById("academic_year")?.value || "");
                data.append("owner_type", ownerType);

                // Seçenekleri ekle
                Object.keys(options).forEach(key => data.append(key, options[key] ? 1 : 0));

                if (button.id === "singlePageExport") {
                    data.append("owner_id", button.dataset.ownerId);
                } else {
                    const selectId = button.id === "lecturerExport" ? "lecturer_id" :
                        button.id === "classroomExport" ? "classroom_id" :
                            button.id === "departmentAndProgramExport" ? (programSelect && programSelect.value > 0 ? "program_id" : "department_id") : "";

                    if (selectId) {
                        const selectElement = document.getElementById(selectId);
                        if (selectElement && selectElement.value > 0) {
                            if (selectId === "department_id") data.set("owner_type", "department");
                            data.append("owner_id", selectElement.value);
                        }
                    }
                }

                // Spinner container belirle
                let spinnerContainer = document.getElementById("schedule_container");
                if (!spinnerContainer) {
                    spinnerContainer = button.closest(".card")?.querySelector(".card-body") || document.body;
                }
                spinner.showSpinner(spinnerContainer);
                await fetchExportSchedule(data);
            });
            return;
        }

        // ICS (Takvim) butonları için mevcut mantık devam ediyor
        if (button.id.endsWith("Calendar")) {
            let data = new FormData();
            data.append("type", "lesson");
            data.append("semester", document.getElementById("semester")?.value || "");
            data.append("academic_year", document.getElementById("academic_year")?.value || "");

            if (button.id === "singlePageCalendar") {
                data.append("owner_type", button.dataset.ownerType);
                data.append("owner_id", button.dataset.ownerId);
            } else {
                if (button.id === "lecturerCalendar") {
                    data.append("owner_type", "user");
                    if (lecturerSelect && lecturerSelect.value > 0) data.append("owner_id", lecturerSelect.value);
                } else if (button.id === "classroomCalendar") {
                    data.append("owner_type", "classroom");
                    if (classroomSelect && classroomSelect.value > 0) data.append("owner_id", classroomSelect.value);
                } else if (button.id === "departmentAndProgramCalendar") {
                    if (programSelect && programSelect.value > 0) {
                        data.append("owner_type", "program");
                        data.append("owner_id", programSelect.value);
                    } else if (departmentSelect && departmentSelect.value > 0) {
                        data.append("owner_type", "department");
                        data.append("owner_id", departmentSelect.value);
                    } else {
                        data.append("owner_type", "program");
                    }
                }
            }
            // Spinner container belirle
            let spinnerContainer = document.getElementById("schedule_container");
            if (!spinnerContainer) {
                spinnerContainer = button.closest(".card")?.querySelector(".card-body") || document.body;
            }
            spinner.showSpinner(spinnerContainer);
            await fetchExportIcs(data);
        }
    });

    /**
     * Dışa aktarma seçeneklerini soran modalı gösterir
     */
    function showExportOptionsModal(ownerType, onConfirm) {
        const modal = new Modal();
        let content = `<div class="p-2">
            <p class="mb-3 border-bottom pb-2">Excel tablosunda görünmesini istediğiniz alanları seçin:</p>
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="show_code" checked>
                <label class="form-check-label" for="show_code">Ders Kodu</label>
            </div>`;

        if (ownerType === "program" || ownerType === "classroom" || ownerType === "department") {
            content += `<div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="show_lecturer" checked>
                <label class="form-check-label" for="show_lecturer">Hoca Adı</label>
            </div>`;
        }

        if (ownerType === "user" || ownerType === "classroom") {
            content += `<div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="show_program" checked>
                <label class="form-check-label" for="show_program">Program/Bölüm Adı</label>
            </div>`;
        }

        content += `</div>`;

        modal.prepareModal("Dışa Aktarma Seçenekleri", content, true, true, "md");
        modal.confirmButton.textContent = "Dışa Aktar";
        modal.showModal();

        modal.confirmButton.addEventListener("click", () => {
            const options = {};
            if (document.getElementById("show_code")) options.show_code = document.getElementById("show_code").checked;
            if (document.getElementById("show_lecturer")) options.show_lecturer = document.getElementById("show_lecturer").checked;
            if (document.getElementById("show_program")) options.show_program = document.getElementById("show_program").checked;

            modal.closeModal();
            onConfirm(options);
        });
    }

    // Export isteği gönderme ve indirme işlemi
    function fetchExportSchedule(data) {
        return fetch("/ajax/exportSchedule", {
            method: "POST",
            headers: {
                "X-Requested-With": "XMLHttpRequest",
            },
            body: data,
        })
            .then((response) => {
                const disposition = response.headers.get("Content-Disposition");
                let filename = "Ders Programı.xlsx"; // Varsayılan isim

                if (disposition && disposition.includes("filename=")) {
                    let matches = disposition.match(/filename=\"?(.+?)\"?(;|$)/);
                    if (matches && matches[1]) {
                        try {
                            const decoder = new TextDecoder("utf-8");
                            const bytes = new Uint8Array(
                                matches[1].split("").map((c) => c.charCodeAt(0))
                            );
                            filename = decoder.decode(bytes);
                        } catch (e) {
                            filename = matches[1]; // fallback
                        }
                    }
                }
                return response.blob().then((blob) => ({ blob, filename }));
            })
            .then(({ blob, filename }) => {
                spinner.removeSpinner();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement("a");
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);
            })
            .catch((error) => {
                spinner.removeSpinner();
                new Toast().prepareToast(
                    "Hata",
                    "Dışa aktarma sırasında hata oluştu. Detaylar için geliştirici konsoluna bakın",
                    "danger"
                );
                console.error(error);
            });
    }

    // ICS Export isteği gönderme ve indirme işlemi
    function fetchExportIcs(data) {
        return fetch("/ajax/exportScheduleIcs", {
            method: "POST",
            headers: {
                "X-Requested-With": "XMLHttpRequest",
            },
            body: data,
        })
            .then((response) => {
                const disposition = response.headers.get("Content-Disposition");
                let filename = "Ders Programı.ics"; // Varsayılan isim
                if (disposition && disposition.includes("filename=")) {
                    let matches = disposition.match(/filename=\"?(.+?)\"?(;|$)/);
                    if (matches && matches[1]) {
                        try {
                            const decoder = new TextDecoder("utf-8");
                            const bytes = new Uint8Array(
                                matches[1].split("").map((c) => c.charCodeAt(0))
                            );
                            filename = decoder.decode(bytes);
                        } catch (e) {
                            filename = matches[1]; // fallback
                        }
                    }
                }
                return response.blob().then((blob) => ({ blob, filename }));
            })
            .then(({ blob, filename }) => {
                spinner.removeSpinner();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement("a");
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);
            })
            .catch((error) => {
                spinner.removeSpinner();
                new Toast().prepareToast(
                    "Hata",
                    "Takvime kaydederken hata oluştu. Detaylar için geliştirici konsoluna bakın",
                    "danger"
                );
                console.error(error);
            });
    }
});
