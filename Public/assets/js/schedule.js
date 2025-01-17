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
                  <div id="lesson-${lesson.id}" draggable="true" class="d-flex justify-content-between align-items-start mb-2 p-2 rounded text-bg-primary">
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
    console.log(element, "bırakıldı")
    const data = event.dataTransfer.getData("id");
    console.log(event);
    element.appendChild(document.getElementById(data));
}
/**
 *
 * @param element dürükleme işleminin başlatıldığı element
 * @param event sürükleme olayı
 */
function dragStartHandler(element, event) {
    event.dataTransfer.effectAllowed = "move";
    console.log(`Dragging started for element:`, element);
    console.log("drag event:", event);
    // Ekstra işlemleri burada yapabilirsiniz
    event.dataTransfer.setData("id", event.target.id);//format kısmına genelde text/plain, text/html gibi terimler yasılıyor. Ama anladığım kadarıyla buraya ne yazdıysak get Data kısmına da aynısını yazmamız yeterli
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
        let bootstrapModal = new bootstrap.Modal(modal.modal);

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