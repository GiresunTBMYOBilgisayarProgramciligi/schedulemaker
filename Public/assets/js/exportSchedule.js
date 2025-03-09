let spinner = new Spinner();
/**
 * Program düzenleme işlemlerinde kullanılacak işlemler
 * Öncesinde myHTMLElemens.js yüklenmeli
 */
document.addEventListener("DOMContentLoaded", function () {
    const departmentSelect = document.getElementById("department_id")
    const programSelect = document.getElementById("program_id")
    const exportButton = document.getElementById('export')
    const exportButton2 = document.getElementById('export2')
    const lecturerSelect = document.getElementById("lecturer_id");
    const exportButton3 = document.getElementById('export3')
    const classroomSelect = document.getElementById("classroom_id");
    let data = new FormData();
    data.append("type", "lesson");
    data.append("semester", document.getElementById("semester").value);
    data.append("academic_year", document.getElementById("academic_year").value);

    exportButton.addEventListener("click", async function () {

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

    exportButton2.addEventListener("click", async function () {
        if (lecturerSelect.value > 0) {
            data.append("owner_type", "user");
            data.append("owner_id", programSelect.value);
        } else {
            data.append("owner_type", "user");
        }
        spinner.showSpinner(document.getElementById("schedule_container"));
        await fetchExportSchedule(data);
    });

    exportButton3.addEventListener("click", async function () {
        if (classroomSelect.value > 0) {
            data.append("owner_type", "classroom");
            data.append("owner_id", programSelect.value);
        } else {
            data.append("owner_type", "classroom");
        }
        spinner.showSpinner(document.getElementById("schedule_container"));
        await fetchExportSchedule(data);
    });

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
