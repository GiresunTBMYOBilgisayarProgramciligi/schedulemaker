/**
 * Program Gösterme işlemleri
 * Öncesinde myHTMLElemens.js yüklenmeli
 */
// Yeni bir custom event oluştur
const scheduleLoaded = new Event("scheduleLoaded");
document.addEventListener("DOMContentLoaded", function () {
    const departmentSelect = document.getElementById("department_id")
    const programSelect = document.getElementById("program_id")
    const departmentAndProgramScheduleButton = document.getElementById('departmentAndProgramScheduleButton')
    const lecturerScheduleButton = document.getElementById('lecturerScheduleButton')
    const lecturerSelect = document.getElementById("lecturer_id");
    const classroomScheduleButton = document.getElementById('classroomScheduleButton')
    const classroomSelect = document.getElementById("classroom_id");
    const toast = new Toast();
    if (departmentAndProgramScheduleButton) {
        departmentAndProgramScheduleButton.addEventListener("click", async function () {
            let data = new FormData();
            data.append("type", "lesson");
            data.append("semester", document.getElementById("semester").value);
            data.append("academic_year", document.getElementById("academic_year").value);
            data.append("only_table", departmentAndProgramScheduleButton.dataset.onlyTable)
            if (programSelect.value > 0) {
                data.append("owner_type", "program");
                data.append("owner_id", programSelect.value);
                toast.prepareToast("Yükleniyor", "Ders Programı Yükleniyor...", "info", false)
                await getSchedulesHTML(data);
            } else {
                new Toast().prepareToast("Hata", "Bir Program seçmelisiniz.", "danger");
            }
        });
    }
    if (lecturerScheduleButton) {
        lecturerScheduleButton.addEventListener("click", async function () {
            let data = new FormData();
            data.append("type", "lesson");
            data.append("semester", document.getElementById("semester").value);
            data.append("academic_year", document.getElementById("academic_year").value);
            data.append("semester_no", "birleştir");
            data.append("only_table", lecturerScheduleButton.dataset.onlyTable)
            if (lecturerSelect.value > 0) {
                data.append("owner_type", "user");
                data.append("owner_id", lecturerSelect.value);
                toast.prepareToast("Yükleniyor", "Ders Programı Yükleniyor...", "info", false)
                await getSchedulesHTML(data);
            } else {
                new Toast().prepareToast("Hata", "Bir hoca seçmelisiniz.", "danger");
            }

        });
    }
    if (classroomScheduleButton) {
        classroomScheduleButton.addEventListener("click", async function () {
            let data = new FormData();
            data.append("type", "lesson");
            data.append("semester", document.getElementById("semester").value);
            data.append("academic_year", document.getElementById("academic_year").value);
            data.append("semester_no", "birleştir");
            data.append("only_table", classroomScheduleButton.dataset.onlyTable)
            if (classroomSelect.value > 0) {
                data.append("owner_type", "classroom");
                data.append("owner_id", classroomSelect.value);
                toast.prepareToast("Yükleniyor", "Ders Programı Yükleniyor...", "info", false)
                await getSchedulesHTML(data);
            } else {
                new Toast().prepareToast("Hata", "Bir derslik seçmelisiniz.", "danger");
            }

        });
    }

    function getSchedulesHTML(scheduleData = new FormData()) {
        const container = document.getElementById('schedule_container');
        container.innerHTML = "";
        return fetch("/ajax/getScheduleHTML", {
            method: "POST",
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: scheduleData,
        })
            .then(response => response.json())
            .then((data) => {
                if (data['status'] !== 'error') {
                    container.innerHTML = data['HTML'];
                    /**
                     * Bağlı derslerde gösterilecek popoverları aktif etmek için eklendi.
                     * @type {*[]}
                     */
                    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
                    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
                        return new bootstrap.Popover(popoverTriggerEl, {trigger: 'hover'})
                    })
                    toast.closeToast()
                    document.dispatchEvent(scheduleLoaded);
                } else {
                    new Toast().prepareToast("Hata", data['msg'], "danger");
                    toast.closeToast()
                    console.error(data['msg']);
                }
            })
            .catch((error) => {
                new Toast().prepareToast("Hata", "Ders programı oluşturulurken hata oluştu. Detaylar için geliştirici konsoluna bakın", "danger");
                console.error(error);
            });
    }
});
