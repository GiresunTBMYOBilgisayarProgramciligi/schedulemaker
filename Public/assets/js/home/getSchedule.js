/**
 * Program Gösterme işlemleri
 * Öncesinde myHTMLElemens.js yüklenmeli
 */
document.addEventListener("DOMContentLoaded", function () {
    const departmentSelect = document.getElementById("department_id")
    const programSelect = document.getElementById("program_id")
    //todo buton isimlerini düzenle
    const departmentAndProgramScheduleButton = document.getElementById('departmentAndProgramScheduleButton')
    const lecturerScheduleButton = document.getElementById('lecturerScheduleButton')
    const lecturerSelect = document.getElementById("lecturer_id");
    const classroomScheduleButton = document.getElementById('classroomScheduleButton')
    const classroomSelect = document.getElementById("classroom_id");

    if (departmentAndProgramScheduleButton) {
        departmentAndProgramScheduleButton.addEventListener("click", async function () {
            let data = new FormData();
            data.append("type", "lesson");
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
            await getSchedulesHTML(data);

        });
    }
    if (lecturerScheduleButton) {
        lecturerScheduleButton.addEventListener("click", async function () {
            let data = new FormData();
            data.append("type", "lesson");
            data.append("semester", document.getElementById("semester").value);
            data.append("academic_year", document.getElementById("academic_year").value);
            if (lecturerSelect.value > 0) {
                data.append("owner_type", "user");
                data.append("owner_id", lecturerSelect.value);
            } else {
                data.append("owner_type", "user");
            }
            spinner.showSpinner(document.getElementById("schedule_container"));
            await getSchedulesHTML(data);
        });
    }
    if (classroomScheduleButton) {
        classroomScheduleButton.addEventListener("click", async function () {
            let data = new FormData();
            data.append("type", "lesson");
            data.append("semester", document.getElementById("semester").value);
            data.append("academic_year", document.getElementById("academic_year").value);
            if (classroomSelect.value > 0) {
                data.append("owner_type", "classroom");
                data.append("owner_id", classroomSelect.value);
            } else {
                data.append("owner_type", "classroom");
            }
            spinner.showSpinner(document.getElementById("schedule_container"));
            await getSchedulesHTML(data);
        });
    }

    function getSchedulesHTML(scheduleData = new FormData()) {
        scheduleData.append("only_table",true)
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
                    const container = document.getElementById('schedule_container');
                    container.innerHTML = data['HTML'];
                    /**
                     * Bağlı derslerde gösterilecek popoverları aktif etmek için eklendi.
                     * @type {*[]}
                     */
                    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
                    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
                        return new bootstrap.Popover(popoverTriggerEl, {trigger: 'hover'})
                    })
                } else {
                    new Toast().prepareToast("Hata", data['msg'], "danger");
                    console.error(data['msg']);
                }
            })
            .catch((error) => {
                new Toast().prepareToast("Hata", "Uygun ders listesi oluşturulurken hata oluştu. Detaylar için geliştirici konsoluna bakın", "danger");
                console.error(error);
            });
    }
});
