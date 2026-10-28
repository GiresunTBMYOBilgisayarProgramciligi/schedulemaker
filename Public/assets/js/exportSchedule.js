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
     * Giriş yapmamış (misafir) kullanıcı mı? Yalnızca public temada data-guest="true" olur.
     * Giriş yapmış kullanıcı public sayfada da paneldeki seçenekleri görür.
     */
    function isGuestUser() {
        return document.querySelector(".app-public-wrapper")?.dataset.guest === "true";
    }

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
        const semesterNoSelect = document.getElementById("semester_no");
        const semesterNo = (button.dataset.semesterNo !== undefined && button.dataset.semesterNo !== null) 
            ? (button.dataset.semesterNo ? button.dataset.semesterNo : null) 
            : (scheduleCard?.dataset.semesterNo || (semesterNoSelect && semesterNoSelect.value ? semesterNoSelect.value : null));

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

        // Liste Formatı (satır bazlı) dışa aktarma butonları için
        if (button.id.endsWith("List")) {
            const ownerType = button.id === "singlePageList" ? button.dataset.ownerType :
                button.id === "lecturerList" ? "user" :
                button.id === "classroomList" ? (
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

                Object.keys(options).forEach(key => {
                    if (key !== "semester_no") {
                        data.append(key, options[key] ? 1 : 0);
                    }
                });

                if (button.id === "singlePageList") {
                    data.append("owner_id", button.dataset.ownerId);
                } else if (button.id === "classroomList") {
                    let ownerId = (classroomSelect && classroomSelect.value > 0) ? classroomSelect.value :
                        (classroomBuildingSelect && classroomBuildingSelect.value > 0) ? classroomBuildingSelect.value :
                        (classroomUnitSelect && classroomUnitSelect.value > 0) ? classroomUnitSelect.value : "";
                    if (ownerId) {
                        data.append("owner_id", ownerId);
                    }
                } else {
                    const selectId = button.id === "lecturerList" ? "lecturer_id" :
                        button.id === "departmentAndProgramList" ? (
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

                let spinnerContainer = document.getElementById("schedule_container");
                if (!spinnerContainer) {
                    spinnerContainer = button.closest(".card")?.querySelector(".card-body") || document.body;
                }
                spinner.showSpinner(spinnerContainer);
                await fetchExportScheduleList(data);
            }, "list");
            return;
        }

        // JSON formatı dışa aktarma butonları için
        if (button.id.endsWith("Json")) {
            const ownerType = button.id === "singlePageJson" ? button.dataset.ownerType :
                button.id === "lecturerJson" ? "user" :
                button.id === "classroomJson" ? (
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

                Object.keys(options).forEach(key => {
                    if (key !== "semester_no") {
                        data.append(key, options[key] ? 1 : 0);
                    }
                });

                if (button.id === "singlePageJson") {
                    data.append("owner_id", button.dataset.ownerId);
                } else if (button.id === "classroomJson") {
                    let ownerId = (classroomSelect && classroomSelect.value > 0) ? classroomSelect.value :
                        (classroomBuildingSelect && classroomBuildingSelect.value > 0) ? classroomBuildingSelect.value :
                        (classroomUnitSelect && classroomUnitSelect.value > 0) ? classroomUnitSelect.value : "";
                    if (ownerId) {
                        data.append("owner_id", ownerId);
                    }
                } else {
                    const selectId = button.id === "lecturerJson" ? "lecturer_id" :
                        button.id === "departmentAndProgramJson" ? (
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

                let spinnerContainer = document.getElementById("schedule_container");
                if (!spinnerContainer) {
                    spinnerContainer = button.closest(".card")?.querySelector(".card-body") || document.body;
                }
                spinner.showSpinner(spinnerContainer);
                await fetchExportScheduleJson(data);
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

            await executeCalendarExport(semesterNo);
            return;
        }
    });


    /**
     * Dışa aktarma modalında gösterilecek seçenekleri belirler (tüm seçenek kuralları burada).
     *
     * Modalda yer almayan seçenekler isteğe eklenmez ve sunucu varsayılanı kullanılır.
     * Misafir sınav çıktısında gözetmen ve program adı sunucuda da kapatılır (GuestScheduleVisibility).
     *
     * @param {string} ownerType program | department | unit | user | classroom | building | classroom_unit
     * @param {boolean} isExam Sınav programı mı
     * @param {string} format "excel" | "list" | "json"
     * @param {boolean} isGuest Giriş yapmamış kullanıcı mı
     * @returns {{id: string, label: string, checked: boolean}[]}
     */
    function getExportOptionFields(ownerType, isExam, format, isGuest) {
        const isProgramOwner = ["program", "department", "unit"].includes(ownerType);
        const isClassroomOwner = ["classroom", "building", "classroom_unit"].includes(ownerType);
        const isUserOwner = ownerType === "user";
        const fields = [{id: "show_code", label: "Ders Kodu", checked: true}];

        // Hoca ders programında hoca zaten programın sahibidir
        if (isProgramOwner || isClassroomOwner || (isExam && isUserOwner)) {
            fields.push({id: "show_lecturer", label: "Hoca Adı", checked: true});
        }

        // Misafir sınav çıktısı: yalnızca ders kodu ve hoca adı
        if (isGuest && isExam) {
            return fields;
        }

        // Sınavlarda birleştirilmiş dersler farklı programlara ait olabileceğinden program adı her çıktı türünde seçilebilir
        if (isExam || isUserOwner || isClassroomOwner) {
            fields.push({id: "show_program", label: "Program/Bölüm Adı", checked: true});
        }
        // Hocanın kendi sınav programında gözetmen seçeneği yok
        if (isExam && !isUserOwner) {
            fields.push({id: "show_observer", label: "Gözetmen İsimleri", checked: true});
        }
        // Staj tablosu yalnızca birim/bölüm/program ders programında ve giriş yapmış kullanıcıya
        if (!isExam && isProgramOwner && !isGuest) {
            fields.push({id: "show_internship", label: "Staj / İşletmede Mesleki Eğitim Tablosu", checked: true});
        }
        // Derslik bazlı sınav listesinde derslik başlıkları yerine tek tablo ve "Derslik" sütunu
        if (isExam && isClassroomOwner && format === "list") {
            fields.push({id: "hide_titles", label: "Derslik başlıklarını gösterme (tüm derslikler tek tabloda, derslik adı ilk sütunda)", checked: false});
        }
        return fields;
    }

    /**
     * Dışa aktarma seçeneklerini soran modalı gösterir
     * @param {string} format Dışa aktarma biçimi ("excel" | "list" | "json"); biçime özel seçenekler için
     */
    function showExportOptionsModal(ownerType, scheduleType, semesterNo, onConfirm, format = "excel") {
        const modal = new Modal();
        const isExam = scheduleType !== "lesson";
        const typeLabel = isExam ? "Sınav" : "Ders";
        const isGuest = isGuestUser();
        const fields = getExportOptionFields(ownerType, isExam, format, isGuest);

        const content = `<div class="p-2">
            <p class="mb-3 border-bottom pb-2">Excel tablosunda görünmesini istediğiniz alanları seçin:</p>
            ${fields.map(field => `<div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="${field.id}"${field.checked ? " checked" : ""}>
                <label class="form-check-label" for="${field.id}">${field.label}</label>
            </div>`).join("")}
        </div>`;

        modal.prepareModal(typeLabel + " Programı Dışa Aktarma Seçenekleri", content, true, true, "md");
        modal.confirmButton.textContent = "Dışa Aktar";
        modal.showModal();

        modal.confirmButton.onclick = () => {
            const options = {};
            if (semesterNo) {
                options.semester_no = semesterNo;
            }
            fields.forEach(field => {
                options[field.id] = document.getElementById(field.id).checked;
            });
            // Misafire staj tablosu çıkarılmaz (sunucu varsayılanı açık olduğundan açıkça kapatılır)
            if (isGuest && !("show_internship" in options)) {
                options.show_internship = false;
            }

            modal.closeModal();
            onConfirm(options);
        };
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
    // Liste Formatı Export isteği gönderme ve indirme işlemi
    function fetchExportScheduleList(data) {
        return fetch("/ajax/exportScheduleList", {
            method: "POST",
            headers: {
                "X-Requested-With": "XMLHttpRequest",
            },
            body: data,
        })
            .then((response) => {
                const contentType = response.headers.get("Content-Type") || "";

                if (contentType.includes("application/json")) {
                    return response.json().then((json) => {
                        spinner.removeSpinner();
                        if (json.status === "debug") {
                            new Toast().prepareToast(
                                "Debug Modu",
                                json.message + " | Tür: " + json.type + " | Sahip: " + json.owner_type + " | Filtre Sayısı: " + json.filter_count,
                                "info"
                            );
                            console.log("List Export Debug Response:", json);
                        } else {
                            new Toast().prepareToast("Hata", json.message || "Beklenmeyen yanıt", "danger");
                        }
                        return null;
                    });
                }

                const disposition = response.headers.get("Content-Disposition");
                let filename = "Ders Programı Liste.xlsx";

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
                            filename = matches[1];
                        }
                    }
                }
                return response.blob().then((blob) => ({ blob, filename }));
            })
            .then((result) => {
                if (!result) return;
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
                    "Liste formatı dışa aktarma sırasında hata oluştu. Detaylar için geliştirici konsoluna bakın",
                    "danger"
                );
                console.error(error);
            });
    }
    // JSON Format Export isteği gönderme ve indirme işlemi
    function fetchExportScheduleJson(data) {
        return fetch("/ajax/exportScheduleJson", {
            method: "POST",
            headers: {
                "X-Requested-With": "XMLHttpRequest",
            },
            body: data,
        })
            .then((response) => {
                const contentType = response.headers.get("Content-Type") || "";

                if (contentType.includes("application/json") && !contentType.includes("charset")) {
                    // Hata durumunda JSON yanıtı — charset yoksa hata mesajı
                    return response.json().then((json) => {
                        spinner.removeSpinner();
                        if (json.status === "debug") {
                            new Toast().prepareToast(
                                "Debug Modu",
                                json.message + " | Tür: " + json.type + " | Sahip: " + json.owner_type,
                                "info"
                            );
                            console.log("JSON Export Debug Response:", json);
                        } else {
                            new Toast().prepareToast("Hata", json.message || "Beklenmeyen yanıt", "danger");
                        }
                        return null;
                    });
                }

                const disposition = response.headers.get("Content-Disposition");
                let filename = "Ders Programı Liste.json";

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
                            filename = matches[1];
                        }
                    }
                }
                return response.blob().then((blob) => ({ blob, filename }));
            })
            .then((result) => {
                if (!result) return;
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
                    "JSON dışa aktarma sırasında hata oluştu. Detaylar için geliştirici konsoluna bakın",
                    "danger"
                );
                console.error(error);
            });
    }
});
