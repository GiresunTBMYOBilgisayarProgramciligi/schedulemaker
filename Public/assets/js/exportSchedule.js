/**
 * Program dışa aktarma işlemlerinde kullanılacak işlemler
 * Öncesinde myHTMLElemens.js yüklenmeli
 */
document.addEventListener("DOMContentLoaded", function () {
    const unitSelect = document.getElementById("unit_id");
    const departmentSelect = document.getElementById("department_id");
    const programSelect = document.getElementById("program_id");
    const lecturerSelect = document.getElementById("lecturer_id");
    const classroomUnitSelect = document.getElementById("classroom_unit_id");
    const classroomBuildingSelect = document.getElementById("classroom_building_id");
    const classroomSelect = document.getElementById("classroom_id");

    /**
     * Yarıyıl numarasına göre kullanıcı dostu etiket döner (örn: "1. Sınıf (1. Yarıyıl)")
     */
    function getSemesterLabel(semesterNo) {
        const no = parseInt(semesterNo, 10);
        if (!no) return "";
        const classNo = Math.ceil(no / 2);
        return `${classNo}. Sınıf (${no}. Yarıyıl)`;
    }

    // Tüm click eventlerini tek noktadan yakala
    document.addEventListener("click", async function (event) {
        const button = event.target.closest("button"); // En yakın button'u bul
        if (!button) return; // Eğer button değilse devam etme

        // Seçilen program türünü al (kartın data-type'ı öncelikli, yoksa schedule_type seçicisi)
        const scheduleCard = button.closest(".schedule-card");
        const cardScheduleType = scheduleCard ? scheduleCard.dataset.type : null;
        
        const scheduleTypeSelect = document.getElementById("schedule_type");
        const scheduleType = cardScheduleType || scheduleTypeSelect?.value || "lesson";
        const semesterNo = button.dataset.semesterNo || scheduleCard?.dataset.semesterNo || null;

        // Sadece Excel dışa aktarma butonları için (id sonunda Export olanlar)
        if (button.id.endsWith("Export")) {
            const ownerType = button.id === "singlePageExport" ? button.dataset.ownerType :
                button.id === "lecturerExport" ? "user" :
                button.id === "classroomExport" ? (
                    (classroomSelect && classroomSelect.value > 0) ? "classroom" :
                    (classroomBuildingSelect && classroomBuildingSelect.value > 0) ? "building" :
                    (classroomUnitSelect && classroomUnitSelect.value > 0) ? "classroom_unit" : "classroom"
                ) :
                (programSelect && programSelect.value > 0) ? "program" :
                (departmentSelect && departmentSelect.value > 0) ? "department" :
                (unitSelect && unitSelect.value > 0) ? "unit" : "program";

            showExportOptionsModal(ownerType, scheduleType, semesterNo, async (options) => {
                let data = new FormData();
                data.append("type", scheduleType);
                data.append("semester", document.getElementById("semester")?.value || "");
                data.append("academic_year", document.getElementById("academic_year")?.value || "");
                data.append("owner_type", ownerType);

                if (options.semester_no) {
                    data.append("semester_no", options.semester_no);
                }

                // Seçenekleri ekle
                Object.keys(options).forEach(key => {
                    if (key !== "semester_no") {
                        data.append(key, options[key] ? 1 : 0);
                    }
                });

                if (button.id === "singlePageExport") {
                    data.append("owner_id", button.dataset.ownerId);
                } else if (button.id === "classroomExport") {
                    let ownerId = (classroomSelect && classroomSelect.value > 0) ? classroomSelect.value :
                        (classroomBuildingSelect && classroomBuildingSelect.value > 0) ? classroomBuildingSelect.value :
                        (classroomUnitSelect && classroomUnitSelect.value > 0) ? classroomUnitSelect.value : "";
                    if (ownerId) {
                        data.append("owner_id", ownerId);
                    }
                } else {
                    const selectId = button.id === "lecturerExport" ? "lecturer_id" :
                        button.id === "departmentAndProgramExport" ? (
                            programSelect && programSelect.value > 0 ? "program_id" :
                                departmentSelect && departmentSelect.value > 0 ? "department_id" :
                                    unitSelect && unitSelect.value > 0 ? "unit_id" : ""
                        ) : "";

                    if (selectId) {
                        const selectElement = document.getElementById(selectId);
                        if (selectElement && selectElement.value > 0) {
                            if (selectId === "department_id") data.set("owner_type", "department");
                            else if (selectId === "unit_id") data.set("owner_type", "unit");
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

        // ICS (Takvim) butonları için
        if (button.id.endsWith("Calendar")) {
            const ownerType = button.id === "singlePageCalendar" ? button.dataset.ownerType :
                button.id === "lecturerCalendar" ? "user" :
                button.id === "classroomCalendar" ? (
                    (classroomSelect && classroomSelect.value > 0) ? "classroom" :
                    (classroomBuildingSelect && classroomBuildingSelect.value > 0) ? "building" :
                    (classroomUnitSelect && classroomUnitSelect.value > 0) ? "classroom_unit" : "classroom"
                ) :
                (programSelect && programSelect.value > 0) ? "program" :
                (departmentSelect && departmentSelect.value > 0) ? "department" :
                (unitSelect && unitSelect.value > 0) ? "unit" : "program";

            const executeCalendarExport = async (selectedSemesterNo = null) => {
                let data = new FormData();
                data.append("type", scheduleType);
                data.append("semester", document.getElementById("semester")?.value || "");
                data.append("academic_year", document.getElementById("academic_year")?.value || "");
                data.append("owner_type", ownerType);

                if (selectedSemesterNo) {
                    data.append("semester_no", selectedSemesterNo);
                }

                if (button.id === "singlePageCalendar") {
                    data.append("owner_id", button.dataset.ownerId);
                } else if (button.id === "classroomCalendar") {
                    let ownerId = (classroomSelect && classroomSelect.value > 0) ? classroomSelect.value :
                        (classroomBuildingSelect && classroomBuildingSelect.value > 0) ? classroomBuildingSelect.value :
                        (classroomUnitSelect && classroomUnitSelect.value > 0) ? classroomUnitSelect.value : "";
                    if (ownerId) {
                        data.append("owner_id", ownerId);
                    }
                } else {
                    const selectId = button.id === "lecturerCalendar" ? "lecturer_id" :
                        button.id === "departmentAndProgramCalendar" ? (
                            programSelect && programSelect.value > 0 ? "program_id" :
                                departmentSelect && departmentSelect.value > 0 ? "department_id" :
                                    unitSelect && unitSelect.value > 0 ? "unit_id" : ""
                        ) : "";

                    if (selectId) {
                        const selectElement = document.getElementById(selectId);
                        if (selectElement && selectElement.value > 0) {
                            if (selectId === "department_id") data.set("owner_type", "department");
                            else if (selectId === "unit_id") data.set("owner_type", "unit");
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
                await fetchExportIcs(data);
            };

            if (ownerType === "program" && semesterNo) {
                showCalendarOptionsModal(ownerType, scheduleType, semesterNo, async (options) => {
                    await executeCalendarExport(options.semester_no);
                });
            } else {
                await executeCalendarExport();
            }
            return;
        }
    });


    /**
     * Dışa aktarma seçeneklerini soran modalı gösterir
     */
    function showExportOptionsModal(ownerType, scheduleType, semesterNo, onConfirm) {
        const modal = new Modal();
        const isExam = scheduleType !== "lesson";
        const typeLabel = isExam ? "Sınav" : "Ders";

        let semesterSection = "";
        if (ownerType === "program" && semesterNo) {
            const semLabel = getSemesterLabel(semesterNo);
            semesterSection = `
            <div class="mb-3 border-bottom pb-2">
                <label class="form-label fw-semibold mb-2">Çıktı Alınacak Dönem:</label>
                <div class="form-check mb-1">
                    <input class="form-check-input" type="radio" name="export_scope" id="scope_single" value="single" checked>
                    <label class="form-check-label" for="scope_single">Sadece bu dönem (${semLabel})</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="export_scope" id="scope_all" value="all">
                    <label class="form-check-label" for="scope_all">Tüm dönemler</label>
                </div>
            </div>`;
        }

        let content = `<div class="p-2">
            ${semesterSection}
            <p class="mb-3 border-bottom pb-2">Excel tablosunda görünmesini istediğiniz alanları seçin:</p>
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="show_code" checked>
                <label class="form-check-label" for="show_code">Ders Kodu</label>
            </div>`;

        if (ownerType === "program" || ownerType === "classroom" || ownerType === "building" || ownerType === "classroom_unit" || ownerType === "department" || ownerType === "unit" || (isExam && ownerType === "user")) {
            content += `<div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="show_lecturer" checked>
                <label class="form-check-label" for="show_lecturer">Hoca Adı</label>
            </div>`;
        }

        if (ownerType === "user" || ownerType === "classroom" || ownerType === "building" || ownerType === "classroom_unit") {
            content += `<div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="show_program" checked>
                <label class="form-check-label" for="show_program">Program/Bölüm Adı</label>
            </div>`;
        }

        // Sınav türleri için gözetmen seçeneği (Hoca hariç)
        if (isExam && ownerType !== "user") {
            content += `<div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="show_observer" checked>
                <label class="form-check-label" for="show_observer">Gözetmen İsimleri</label>
            </div>`;
        }

        // Bölüm/Program ders programları için staj tablosu seçeneği (Birim, Bölüm veya Program seçildiğinde)
        // Public (öğrenci/misafir) sayfasında staj programı seçeneği gösterilmez
        const isPublicPage = !!document.querySelector(".app-public-wrapper") || !!document.querySelector("[data-action='public']");
        const isProgramScheduleExport = ownerType === "program" || ownerType === "department" || ownerType === "unit";
        if (!isExam && isProgramScheduleExport && !isPublicPage) {
            content += `<div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="show_internship" checked>
                <label class="form-check-label" for="show_internship">Staj / İşletmede Mesleki Eğitim Tablosu</label>
            </div>`;
        }

        content += `</div>`;

        modal.prepareModal(typeLabel + " Programı Dışa Aktarma Seçenekleri", content, true, true, "md");
        modal.confirmButton.textContent = "Dışa Aktar";
        modal.showModal();

        modal.confirmButton.addEventListener("click", () => {
            const options = {};
            if (document.getElementById("scope_single")) {
                if (document.getElementById("scope_single").checked) {
                    options.semester_no = semesterNo;
                }
            }
            if (document.getElementById("show_code")) options.show_code = document.getElementById("show_code").checked;
            if (document.getElementById("show_lecturer")) options.show_lecturer = document.getElementById("show_lecturer").checked;
            if (document.getElementById("show_program")) options.show_program = document.getElementById("show_program").checked;
            if (document.getElementById("show_observer")) options.show_observer = document.getElementById("show_observer").checked;
            if (document.getElementById("show_internship")) {
                options.show_internship = document.getElementById("show_internship").checked;
            } else if (isPublicPage) {
                options.show_internship = false;
            }

            modal.closeModal();
            onConfirm(options);
        });
    }

    /**
     * Takvim (ICS) dışa aktarma seçeneklerini soran modalı gösterir
     */
    function showCalendarOptionsModal(ownerType, scheduleType, semesterNo, onConfirm) {
        const modal = new Modal();
        const isExam = scheduleType !== "lesson";
        const typeLabel = isExam ? "Sınav" : "Ders";
        const semLabel = getSemesterLabel(semesterNo);

        let content = `<div class="p-2">
            <p class="mb-3 border-bottom pb-2">Takvime kaydetmek istediğiniz dönem kapsamını seçin:</p>
            <div class="form-check mb-2">
                <input class="form-check-input" type="radio" name="cal_export_scope" id="cal_scope_single" value="single" checked>
                <label class="form-check-label" for="cal_scope_single">Sadece bu dönem (${semLabel})</label>
            </div>
            <div class="form-check mb-2">
                <input class="form-check-input" type="radio" name="cal_export_scope" id="cal_scope_all" value="all">
                <label class="form-check-label" for="cal_scope_all">Tüm dönemler</label>
            </div>
        </div>`;

        modal.prepareModal(typeLabel + " Takvime Kaydetme Seçenekleri", content, true, true, "md");
        modal.confirmButton.textContent = "Takvime Kaydet";
        modal.showModal();

        modal.confirmButton.addEventListener("click", () => {
            const options = {};
            if (document.getElementById("cal_scope_single")?.checked) {
                options.semester_no = semesterNo;
            }
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
                const contentType = response.headers.get("Content-Type") || "";

                // Debug modu: JSON response geldiğinde Toast ile göster
                if (contentType.includes("application/json")) {
                    return response.json().then((json) => {
                        spinner.removeSpinner();
                        if (json.status === "debug") {
                            new Toast().prepareToast(
                                "Debug Modu",
                                json.message + " | Tür: " + json.type + " | Sahip: " + json.owner_type + " | Filtre Sayısı: " + json.filter_count,
                                "info"
                            );
                            console.log("Export Debug Response:", json);
                        } else {
                            new Toast().prepareToast("Hata", json.message || "Beklenmeyen yanıt", "danger");
                        }
                        return null; // İndirme yapma
                    });
                }

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
            .then((result) => {
                if (!result) return; // Debug modunda indirme yapma
                const { blob, filename } = result;
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
                const contentType = response.headers.get("Content-Type") || "";

                // Debug modu: JSON response geldiğinde Toast ile göster
                if (contentType.includes("application/json")) {
                    return response.json().then((json) => {
                        spinner.removeSpinner();
                        if (json.status === "debug") {
                            new Toast().prepareToast(
                                "Debug Modu",
                                json.message + " | Tür: " + json.type + " | Sahip: " + json.owner_type + " | Filtre Sayısı: " + json.filter_count,
                                "info"
                            );
                            console.log("ICS Export Debug Response:", json);
                        } else {
                            new Toast().prepareToast("Hata", json.message || "Beklenmeyen yanıt", "danger");
                        }
                        return null;
                    });
                }

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
            .then((result) => {
                if (!result) return; // Debug modunda indirme yapma
                const { blob, filename } = result;
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
