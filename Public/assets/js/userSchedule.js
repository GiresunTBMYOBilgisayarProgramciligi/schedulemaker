document.addEventListener("DOMContentLoaded", function () {
    let scheduleTables = document.querySelectorAll(".schedule-table table");
    scheduleTables.forEach((scheduleTable) => {
        let rows = scheduleTable.querySelectorAll("tr");
        rows.forEach((row) => {
            let cells = row.querySelectorAll("td");
            cells.forEach((cell) => {
                cell.addEventListener("click", clickHandler)
            })
        })
    })

    async function clickHandler(event) {
        let cell = event.target;
        let row = cell.closest("tr");
        let table = cell.closest("table");
        let user_id = document.querySelector("input[name=id]").value;
        if (cell.tagName !== "TD") return;
        console.log("cellIndex", event.target.cellIndex);
        console.log("rowIndex", row.rowIndex);
        console.log("user_id", user_id);
        let data = {
            "semester_no": table.dataset.semesterNo,
            "time": table.rows[row.rowIndex].cells[0].innerText,
            "owner_id": user_id,
            "owner_type": "user",
            "type": "lesson",
        }

        if (!cell.classList.contains("bg-success") && !cell.classList.contains("bg-danger")) {
            // 1. tıklama: bg-success ekle
            console.log("Birinci tıklama");
            data["day" + (cell.cellIndex - 1)] = 1;
            let result = await saveSchedulePreference(data);
            if (result) {
                cell.classList.add("bg-success");
            } else {
                new Toast().prepareToast("Hata", "İşlem yapılırken bir hata oluştu", "danger");
                return false;
            }
        } else if (cell.classList.contains("bg-success")) {
            // 2. tıklama: bg-success kaldır, bg-danger ekle
            console.log("ikinci tıklama");
            data["day" + (cell.cellIndex - 1)] = 0;//false yazıldığındda veri tabanına true olarak geçiyor. Bu nedenle 1 ve 0 olarak değer veriyorum
            let result = await saveSchedulePreference(data);
            if (result) {
                cell.classList.remove("bg-success");
                cell.classList.add("bg-danger");
            } else {
                new Toast().prepareToast("Hata", "İşlem yapılırken bir hata oluştu", "danger");
                return false;
            }
        } else if (cell.classList.contains("bg-danger")) {
            // 3. tıklama: bg-danger kaldır
            console.log("üçüncü tıklama tıklama");
            data["day_index"] = (cell.cellIndex - 1);
            let result = await deleteSchedule(data);
            if (result) {
                cell.classList.remove("bg-danger");
            }else {
                new Toast().prepareToast("Hata", "İşlem yapılırken bir hata oluştu", "danger");
                return false;
            }
        }
    }

    async function saveSchedulePreference(scheduleData) {
        let data = new FormData();
        // scheduleData içindeki tüm verileri otomatik olarak FormData'ya ekle
        Object.entries(scheduleData).forEach(([key, value]) => {
            data.append(key, value);
        });
        return fetch("/ajax/saveSchedulePreference", {
            method: "POST",
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: data,
        })
            .then(response => response.json())
            .then((data) => {
                if (data.status === "error") {
                    console.error(data);
                    new Toast().prepareToast("Hata", data.msg, "danger")
                    return false;
                } else {
                    console.log('Program Kaydedildi')
                    console.log(data)
                    return true;
                }
            })
            .catch((error) => {
                new Toast().prepareToast("Hata", "Program kaydedilirken hata oluştu. Detaylar için geliştirici konsoluna bakın", "danger");
                console.error(error);
                return false;
            });
    }

    async function deleteSchedule(scheduleData) {
        let data = new FormData();
        // scheduleData içindeki tüm verileri otomatik olarak FormData'ya ekle
        Object.entries(scheduleData).forEach(([key, value]) => {
            data.append(key, value);
        });
        return fetch("/ajax/deleteSchedule", {
            method: "POST",
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: data,
        })
            .then(response => response.json())
            .then((data) => {
                if (data.status === "error") {
                    console.error(data.msg);
                    new Toast().prepareToast("Hata", data.msg, "danger")
                    return false;
                } else {
                    console.log("Program Silindi")
                    console.log(data)
                    return true;
                }
            })
            .catch((error) => {
                new Toast().prepareToast("Hata", "Program Silinirken hata oluştu. Detaylar için geliştirici konsoluna bakın", "danger");
                console.error(error);
                return false;
            });
    }

});