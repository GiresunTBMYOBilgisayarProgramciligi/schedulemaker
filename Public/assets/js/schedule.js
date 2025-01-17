/**
 * Program düzenleme işlemlerinde kullanılacak işlemler
 * Öncesinde myHTMLElemens.js yüklenmeli
 */
/**
 * Program tablosunu alır ve tableElement ile berlirtilen elemente yazar
 */
function fetchScheduleTable(tableData = new FormData(), tableElement) {
    return fetch("/ajax/getScheduleTable", {
        method: "POST",
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: tableData,
    })
        .then(response => response.json())
        .then((data) => {
            tableElement.innerHTML = data
        })
        .catch((error) => {
            console.error(error);
        });
}

/**
 * Uygun ders listesini alır ve lessonsElement ile belirtilen elemente yazar
 */
function fetchAvailableLessons(data = new FormData(), lessonsElement) {
    return fetch("/ajax/getAvailableLessonsForSchedule", {
        method: "POST",
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: data,
    })
        .then(response => response.json())
        .then((data) => {
            var out = ``;
            data.forEach((lesson) => {
                out += `
                  <div data-id="lesson-${lesson.id}" draggable="true" class="d-flex justify-content-between align-items-start mb-2 p-2 rounded text-bg-primary">
                    <div class="ms-2 me-auto">
                      <div class="fw-bold"><i class="bi bi-book"></i> ${lesson.code} ${lesson.name}</div>
                      ${lesson.lecturer_name}
                    </div>
                    <span class="badge bg-info rounded-pill">${lesson.hours}</span>
                  </div>`;
            })
            out += ``;
            lessonsElement.innerHTML = (out);

        })
        .catch((error) => {
            console.error(error);
        });

}

/**
 *
 * @param element bırakma işleminin yapıldığı element
 * @param event bırakma olayı
 */
function dropHandler(element, event) {
    event.preventDefault();
    if (element.classList.contains("available-schedule-items")) {
        /*
         * Geri bırakma işlemleri
         */
        console.log(element);
        console.log(event.target);
    } else {
        /*
         * Tabloya bırakma işlemleri
         */
        console.log(element)
        console.log(event)
        if (element.querySelector("[data-id]")) {
            let toast = new Toast();
            toast.prepareToast("Hata", "Bu alana ders ekleyemezsiniz", "danger");
            return;
        }
        const dragElementId = event.dataTransfer.getData("id");
        let dragedElement = document.querySelector('[data-id="' + dragElementId + '"');//todo document yerine uygun bir element seçilmeli. data-id document içerinde birden fazla olabilir.
        let scheduleModal = new Modal();
        let lesson_hours = dragedElement.querySelector("span.badge").innerText
        let modalContentHTML = `
            <form>
                <div class="form-floating mb-3">
                    <input class="form-control" id="selected_hours" type="number" value="${lesson_hours}" min=1 max=${lesson_hours}>
                    <label for="selected_hours" >Eklenecek Ders Saati</label>
                </div>
                <div class="mb-3">
                    <select id="classroom" class="form-select" required>
                      <option value=""> Bir Sınıf Seçin</option>
                      <option value="D1">D1</option>
                      <option value="D2">D2</option>
                      <option value="D3">D3</option>
                    </select>
                </div>
            </form>
            `;

        scheduleModal.prepareModal("Sınıf ve Saat seçimi", "", true, false);
        scheduleModal.showModal();
        scheduleModal.addSpinner();
        //todo Uygun sınıf listesini al modalContentHTML a ekle
        scheduleModal.prepareModal("Sınıf ve Saat seçimi", modalContentHTML, true, false);
        let classroomSelectForm=scheduleModal.body.querySelector("form");
        scheduleModal.confirmButton.addEventListener("click", () => {
            classroomSelectForm.dispatchEvent(new Event("submit"));
        })
        classroomSelectForm.addEventListener("submit", function() {
            event.preventDefault();
            /**
             * Modal içerisinden seçilmiş derslik verizini al
             */
            let selectedClassroom = scheduleModal.body.querySelector("#classroom").value
            if (selectedClassroom === "") {
                new Toast().prepareToast("Dikkat", "Bir derslik seçmelisiniz.", "danger");
                return;
            }
            let selectedHours = scheduleModal.body.querySelector("#selected_hours").value
            let scheduleTable = element.closest("table")
            let droppedRowIndex = element.closest("tr").rowIndex
            let droppedCellIndex = element.cellIndex

            for (let i = 0; i < selectedHours; i++) {
                let row = scheduleTable.rows[droppedRowIndex + i];
                let cell = row.cells[droppedCellIndex];
                let lesson = dragedElement.cloneNode(true) // todo klon olduğu için event listenner ların yeniden tanımlanması lazım.
                lesson.querySelector("span.badge").innerHTML = `<i class="bi bi-door-open"></i>${selectedClassroom}`;
                cell.appendChild(lesson);
            }
            if (lesson_hours !== selectedHours) {
                dragedElement.querySelector("span.badge").innerHTML = lesson_hours - selectedHours;
            } else {
                //saatlerin tamamı bittiyse listeden sil
                dragedElement.remove();
            }
            scheduleModal.closeModal();
        })
    }
}

/**
 *
 * @param element dürükleme işleminin başlatıldığı element
 * @param event sürükleme olayı
 */
function dragStartHandler(element, event) {
    event.dataTransfer.effectAllowed = "move";
    // Ekstra işlemleri burada yapabilirsiniz
    event.dataTransfer.setData("id", event.target.getAttribute("data-id"));//format kısmına genelde text/plain, text/html gibi terimler yasılıyor. Ama anladığım kadarıyla buraya ne yazdıysak get Data kısmına da aynısını yazmamız yeterli
}

/**
 *
 * @param element
 * @param event
 */
function dragOverHandler(element, event) {
    event.preventDefault();
    event.dataTransfer.effectAllowed = "move";
}

document.addEventListener("DOMContentLoaded", function () {
    const programSelect = document.getElementById("program_id");
    const scheduleTableElements = document.querySelectorAll(".schedule-table");
    const availableItemElements = document.querySelectorAll(".available-schedule-items");
    programSelect.addEventListener("change", programChangeHandle);

    function programChangeHandle() {
        // todo program body içerisine spinner ekle
        let modal = new Modal();

        let promises = []; // Asenkron işlemleri takip etmek için bir dizi
        var data = new FormData();
        if (scheduleTableElements.length < 1) {
            data.append("owner_type", "program");
            data.append("owner_id", programSelect.value);
            promises.push(fetchAvailableLessons(data, availableItemElements[0]));
            promises.push(fetchScheduleTable(data, scheduleTableElements[0]));
        } else {
            for (var i = 0; i < scheduleTableElements.length; i++) {
                data = new FormData(); // eski verileri silmek için yenile
                data.append("owner_type", "program");
                data.append("owner_id", programSelect.value);
                data.append("season", scheduleTableElements[i].getAttribute("data-season"));

                promises.push(fetchAvailableLessons(data, availableItemElements[i]));
                promises.push(fetchScheduleTable(data, scheduleTableElements[i]));
            }
        }

        // Tüm işlemlerin tamamlanmasını bekle
        Promise.all(promises)
            .then(() => {
                console.log("Tüm işlemler tamamlandı!");
                // Tüm işlemler tamamlandıktan sonra başlatılacak işlem
                afterAllTasksComplete();
            })
            .catch((error) => {
                console.error("Bir hata oluştu:", error);
            });
    }

// Tüm işlemler tamamlandığında yapılacak işlem
    function afterAllTasksComplete() {
        console.log("Artık işlemler sonrası bir şey yapabilirsiniz!");
        // draggable="true" olan tüm elementleri seç
        const dragableElements = document.querySelectorAll('[draggable="true"]');
        const dropZones = document.querySelectorAll('.drop-zone');
        // Her bir draggable öğeye event listener ekle
        dragableElements.forEach(element => {
            element.addEventListener('dragstart', dragStartHandler.bind(this, element));
        });
        dropZones.forEach(element => {
            element.addEventListener("drop", dropHandler.bind(this, element));
            element.addEventListener("dragover", dragOverHandler.bind(this, element)) // bu olmadan çalışmıyor
        });
    }

});