let spinner = new Spinner();
/**
 * Program düzenleme işlemlerinde kullanılacak işlemler
 * Öncesinde myHTMLElemens.js yüklenmeli
 */
document.addEventListener("DOMContentLoaded", function () {
    const departmentSelect = document.getElementById("department_id")
    const programSelect = document.getElementById("program_id")
    //todo buton isimlerini düzenle
    const departmentAndProgramExportButton = document.getElementById('departmentAndProgramExport')
    const lecturerExportButton = document.getElementById('lecturerExport')
    const lecturerSelect = document.getElementById("lecturer_id");
    const classroomExportExportButton = document.getElementById('classroomExport')
    const classroomSelect = document.getElementById("classroom_id");
    const singlePageExportButton = document.getElementById('singlePageExport')
    let data = new FormData();
    data.append("type", "lesson");

    if (departmentAndProgramExportButton) {
        departmentAndProgramExportButton.addEventListener("click", async function () {
            data.append("semester", document.getElementById("semester").value);
            data.append("academic_year", document.getElementById("academic_year").value);
            if (programSelect.value > 0) {
                data.append("owner_type", "program");
                data.append("owner_id", programSelect.value);
            } else if (departmentSelect.value > 0) {
                data.append("owner_type", "department");
                data.append("owner_id", departmentSelect.value);
            } else {
                data.append("owner_type", "program");
            }

            spinner.showSpinner(document.getElementById("schedule_container"));
            await fetchExportSchedule(data);

        });
    }
    if (lecturerExportButton) {
        lecturerExportButton.addEventListener("click", async function () {
            data.append("semester", document.getElementById("semester").value);
            data.append("academic_year", document.getElementById("academic_year").value);
            if (lecturerSelect.value > 0) {
                data.append("owner_type", "user");
                data.append("owner_id", lecturerSelect.value);
            } else {
                data.append("owner_type", "user");
            }
            spinner.showSpinner(document.getElementById("schedule_container"));
            await fetchExportSchedule(data);
        });
    }
    if (classroomExportExportButton) {
        classroomExportExportButton.addEventListener("click", async function () {
            data.append("semester", document.getElementById("semester").value);
            data.append("academic_year", document.getElementById("academic_year").value);
            if (classroomSelect.value > 0) {
                data.append("owner_type", "classroom");
                data.append("owner_id", classroomSelect.value);
            } else {
                data.append("owner_type", "classroom");
            }
            spinner.showSpinner(document.getElementById("schedule_container"));
            await fetchExportSchedule(data);
        });
    }
    if (singlePageExportButton) {
        singlePageExportButton.addEventListener("click", async function () {
            data.append("owner_type", singlePageExportButton.dataset.ownerType);
            data.append("owner_id", singlePageExportButton.dataset.ownerId);

            spinner.showSpinner(document.getElementById("schedule-card"));
            await fetchExportSchedule(data);
        });
    }

    function fetchExportSchedule(data) {
        return fetch("/ajax/exportSchedule", {
            method: "POST",
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: data,
        }).then(response => {
            const disposition = response.headers.get('Content-Disposition');
            let filename = "Ders Programı.xlsx"; // Varsayılan isim

            if (disposition && disposition.includes("filename=")) {
                // İçinden filename değerini çıkaralım
                let matches = disposition.match(/filename=\"?(.+?)\"?(;|$)/);
                if (matches && matches[1]) {
                    // Bazı tarayıcılarda UTF-8 header bile gelse bozuk gösterir, bunu düzeltelim
                    try {
                        const decoder = new TextDecoder('utf-8');
                        const bytes = new Uint8Array(matches[1].split('').map(c => c.charCodeAt(0)));
                        filename = decoder.decode(bytes);
                    } catch (e) {
                        filename = matches[1]; // fallback
                    }
                }
            }
            return response.blob().then(blob => ({blob, filename}));
        })
            .then(({blob, filename}) => {
                spinner.removeSpinner();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);
            })
            .catch((error) => {
                spinner.removeSpinner();
                new Toast().prepareToast("Hata", "Dışa aktarma sırasında hata oluştu. Detaylar için geliştirici konsoluna bakın", "danger");
                console.error(error);
            });
    }

});
