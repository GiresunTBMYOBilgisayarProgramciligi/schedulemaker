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

        // Bölüm/Program bazlı dışa aktarma
        if (button.id === "departmentAndProgramExport") {
            let data = new FormData();
            data.append("type", "lesson");
            data.append("semester", document.getElementById("semester").value);
            data.append("academic_year", document.getElementById("academic_year").value);

            if (programSelect && programSelect.value > 0) {
                data.append("owner_type", "program");
                data.append("owner_id", programSelect.value);
            } else if (departmentSelect && departmentSelect.value > 0) {
                data.append("owner_type", "department");
                data.append("owner_id", departmentSelect.value);
            } else {
                data.append("owner_type", "program");
            }

            spinner.showSpinner(document.getElementById("schedule_container"));
            await fetchExportSchedule(data);
        }

        // Hoca bazlı dışa aktarma
        if (button.id === "lecturerExport") {
            let data = new FormData();
            data.append("type", "lesson");
            data.append("semester", document.getElementById("semester").value);
            data.append("academic_year", document.getElementById("academic_year").value);

            if (lecturerSelect && lecturerSelect.value > 0) {
                data.append("owner_type", "user");
                data.append("owner_id", lecturerSelect.value);
            } else {
                data.append("owner_type", "user");
            }

            spinner.showSpinner(document.getElementById("schedule_container"));
            await fetchExportSchedule(data);
        }

        // Derslik bazlı dışa aktarma
        if (button.id === "classroomExport") {
            let data = new FormData();
            data.append("type", "lesson");
            data.append("semester", document.getElementById("semester").value);
            data.append("academic_year", document.getElementById("academic_year").value);

            if (classroomSelect && classroomSelect.value > 0) {
                data.append("owner_type", "classroom");
                data.append("owner_id", classroomSelect.value);
            } else {
                data.append("owner_type", "classroom");
            }

            spinner.showSpinner(document.getElementById("schedule_container"));
            await fetchExportSchedule(data);
        }

        // Tek sayfalık dışa aktarma (dinamik eklenen buton)
        if (button.id === "singlePageExport") {
            let data = new FormData();
            data.append("type", "lesson");
            data.append("owner_type", button.dataset.ownerType);
            data.append("owner_id", button.dataset.ownerId);

            spinner.showSpinner(document.getElementById("schedule-card"));
            await fetchExportSchedule(data);
        }
    });

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
});
