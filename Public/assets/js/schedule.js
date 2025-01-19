/**
 * Program düzenleme işlemlerinde kullanılacak işlemler
 * Öncesinde myHTMLElemens.js yüklenmeli
 */
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


    /**
     * Program tablosu ve uygun deers listesi tablosu alınma işlemi bittikten sonra çalıştırılan işlemler
     * Tablo ve sürükle bırak işlemleri çin eklenen dersler eklendikten sonra onlara olay dinleyicileri eklenir.
     */
    function afterAllTasksComplete() {
        // draggable="true" olan tüm elementleri seç
        const dragableElements = document.querySelectorAll('[draggable="true"]');
        //drop-zone sınıfına sahip tüm elementler
        const dropZones = document.querySelectorAll('.drop-zone');
        // Her bir draggable öğeye event listener ekle
        dragableElements.forEach(element => {
            element.addEventListener('dragstart', dragStartHandler);
        });
        //tüm drop-zone alanları için olay dinleyicisi ekleniyor
        dropZones.forEach(element => {
            element.addEventListener("drop", dropHandler.bind(this, element));
            element.addEventListener("dragover", dragOverHandler.bind(this, element)) // bu olmadan çalışmıyor
        });
    }

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
                  <div id="available-lesson-${lesson.id}" draggable="true" 
                  class="d-flex justify-content-between align-items-start mb-2 p-2 rounded text-bg-primary"
                  data-season="${lesson.season}"
                  data-lesson-code="${lesson.code}">
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
     * Bırakılan dersin doğru dönem dersine ait olup olmadığını belirler
     * @param droppedSeason
     * @param transferredSeason
     * @returns {boolean}
     */
    function checkSeason(droppedSeason, transferredSeason) {
        if (droppedSeason !== transferredSeason) {
            new Toast().prepareToast("Dikkat", "Bu işlem yapılamaz", "danger");
            return false;
        } else return true;
    }

    /**
     * bırakılan alanda başka ders olup olmadığını ve grup işlemlerini kontrol eder
     * Bırakılan alandaki ders ile bırakılan derslerin gruplarının olup olmadığını varsa farklı olup olmadığını kontrol eder
     * @param dropZone
     * @param draggedElement
     */
    function checkLessonCrash(dropZone, draggedElement) {
        //todo bu fonksiyona tablo parametresi ve saat de girilecek. sonraki saatler için de çalışma kontrolü yapılacak. sonrasında ekleme yapılacka
        if (dropZone.querySelectorAll('[id^=\"scheduleTable-\"]').length !== 0) {
            //eğer zeten iki grup eklenmişse
            if (dropZone.querySelectorAll('[id^=\"scheduleTable-\"]').length > 1) {
                console.log("Zaten iki ders var")
                new Toast().prepareToast("Hata", "Bu alana ders ekleyemezsiniz", "danger");
                return false;
            } else {
                let existLesson = dropZone.querySelector('[id^=\"scheduleTable-\"]')
                let existCode = existLesson.getAttribute("data-lesson-code")
                let currentCode = draggedElement.getAttribute("data-lesson-code")
                let existMatch = existCode.match(/^(.+)\.(\d+)$/);// 0=> tm kod 1=> noktadan öncesi 2=>noktasan sonrası
                let currentMatch = currentCode.match(/^(.+)\.(\d+)$/);// 0=> tm kod 1=> noktadan öncesi 2=>noktasan sonrası

                if (existMatch && currentMatch) {
                    if (existMatch[1] === currentMatch[1]) {
                        // eğer iki ders aynı ise
                        console.log("İki ders aynı")
                        new Toast().prepareToast("Hata", "Lütfen farklı bir ders seçin", "danger");
                        return false;
                    }
                    let existGroup = existMatch[2]; // Noktadan sonraki sayı

                    let currentGroup = currentMatch[2]; // Noktadan sonraki sayı
                    if (existGroup === currentGroup) {
                        console.log("Gruplar aynı")
                        new Toast().prepareToast("Hata", "Gruplar aynı olamaz", "danger");
                        return false;
                    }
                } else {
                    console.log("burada bir ders var ve gruplu değil, yada eklenen ders gruplu değil")
                    new Toast().prepareToast("Hata", "Bu alana ders ekleyemezsiniz", "danger");
                    return false;
                }
            }
        }
        return true;
    }

    function dropListToTable(listElement, draggedElement, dropZone) {
        const table = dropZone.closest("table")
        if (!checkSeason(table.dataset['season'], draggedElement.dataset['season'])) return;

        let scheduleModal = new Modal();
        let lesson_hours = draggedElement.querySelector("span.badge").innerText
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

        let classroomSelectForm = scheduleModal.body.querySelector("form");
        scheduleModal.confirmButton.addEventListener("click", () => {
            classroomSelectForm.dispatchEvent(new Event("submit"));
        })

        classroomSelectForm.addEventListener("submit", function (event) {
            event.preventDefault();
            /**
             * Modal içerisinden seçilmiş derslik verisini al
             */
            let selectedClassroom = scheduleModal.body.querySelector("#classroom").value
            if (selectedClassroom === "") {
                new Toast().prepareToast("Dikkat", "Bir derslik seçmelisiniz.", "danger");
                return;
            }

            let selectedHours = scheduleModal.body.querySelector("#selected_hours").value
            //dersin bırakıldığı satırın tablo içindeki index numarası
            let droppedRowIndex = dropZone.closest("tr").rowIndex
            //dersin bırakıldığı sütunun satır içerisindeki index numarası
            let droppedCellIndex = dropZone.cellIndex

            // çakışmaları kontrol et
            for (let i = 0; i < selectedHours; i++) {
                let row = table.rows[droppedRowIndex + i];
                let cell = row.cells[droppedCellIndex];
                if (!checkLessonCrash(cell,draggedElement)){
                    new Toast().prepareToast("Çakışma",(i+1)+". satte çakışma var")
                    return;
                }
            }

            /**
             * Eklenecek ders sayısı kadar döngü oluşturup dersleri hücerelere ekleyeceğiz
             */
            for (let i = 0; i < selectedHours; i++) {
                let row = table.rows[droppedRowIndex + i];
                let cell = row.cells[droppedCellIndex];

                let lesson = draggedElement.cloneNode(true)
                lesson.querySelector("span.badge").innerHTML = `<i class="bi bi-door-open"></i>${selectedClassroom}`;
                //todo sonraki alanların dolu olup olmadığı kontrol edilmeli
                cell.appendChild(lesson);
                //id kısmına ders saatini de ekliyorum aksi halde aynı id değerine sahip birden fazla element olur.
                lesson.id = lesson.id.replace("available", "scheduleTable") + '-' + (i + 1) // i+1 kısmı ders saati birimini gösteriyor. scheduleTable-lesson-1-1 scheduleTable-lesson-1-2 ...
                //klonlanan yeni elemente de drag start olay dinleyicisi ekleniyor.
                lesson.addEventListener('dragstart', dragStartHandler);
            }
            if (lesson_hours !== selectedHours) {
                draggedElement.querySelector("span.badge").innerHTML = lesson_hours - selectedHours;
            } else {
                //saatlerin tamamı bittiyse listeden sil
                draggedElement.remove();
            }
            scheduleModal.closeModal();
        })
    }

    function dropTableToList(tableElement, draggedElement, dropZone) {
        console.log("Table to List")
        if (!checkSeason(dropZone.dataset['season'], draggedElement.dataset['season'])) return;
        console.log("tableElement:", tableElement)
        console.log("draggedElement:", draggedElement)
        console.log("dropZone:", dropZone)
        //listede taşınan dersin varlığını kontrol et
        console.log("draggedElement.id:", draggedElement.id)

    }

    function dropTableToTable(startTableElement, draggedElement, finishTableElement) {

    }

    /**
     *
     * @param element bırakma işleminin yapıldığı element
     * @param event bırakma olayı
     */
    function dropHandler(element, event) {
        event.preventDefault();
        /**
         * Bırakma eyleminin yapıldığı ana element (eventListenner'ı olan)
         */
        const droppedZone = element;
        /**
         * Bırakma eyleminin yapıldığı elementin çocuklarından birisi. üzerine bırakılan element
         */
        const droppedTargetElement = event.target
        let transferredData = {};
        /**
         * Sürükleme olayı ile gönderilen veriler transferredData objesine aktarılıyor.
         * todo sanki id den başkası burada kullanılmıyor. diğerlerine fonksiyon içerisinde ihtiyaç duyulabilir
         */
        for (let data_index in event.dataTransfer.types) {
            transferredData[event.dataTransfer.types[data_index]] = event.dataTransfer.getData(event.dataTransfer.types[data_index])
        }
        /**
         * Sürüklenen ve bırakılacak olan element
         * @type {Element}
         */
        const draggedElement = document.getElementById(event.dataTransfer.getData("id"))

        // bırakma alanının sınıf bilgisine göre neresi olduğunu tespit ediyoruz.
        if (droppedZone.classList.contains("available-schedule-items")) {
            let table = draggedElement.closest("table");
            dropTableToList(table, draggedElement, droppedZone)
        } else {
            // Tabloya bırakma işlemleri
            let list = draggedElement.closest(".available-schedule-items");
            dropListToTable(list, draggedElement, droppedZone)
        }
    }

    /**
     *
     * @param event sürükleme olayı
     */
    function dragStartHandler(event) {
        event.dataTransfer.effectAllowed = "move";
        event.dataTransfer.dropEffect = "move";
        // id bilgisi aktarılyor
        event.dataTransfer.setData("id", event.target.id)
        //tüm data bilgileri aktarılıyor
        for (let data in event.target.dataset) {
            //format kısmına genelde text/plain, text/html gibi terimler yasılıyor. Ama anladığım kadarıyla buraya ne yazdıysak get Data kısmına da aynısını yazmamız yeterli
            event.dataTransfer.setData(data, event.target.dataset[data])
        }
    }

    /**
     * Sürükleme işlemi sürdüğü sürece çalışır
     * @param element
     * @param event
     */
    function dragOverHandler(element, event) {
        event.preventDefault();
        event.dataTransfer.effectAllowed = "move";
    }


});