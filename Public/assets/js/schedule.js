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
            data.append("type", "lesson")
            promises.push(fetchAvailableLessons(data, availableItemElements[0]));
            promises.push(fetchScheduleTable(data, scheduleTableElements[0]));
        } else {
            for (var i = 0; i < scheduleTableElements.length; i++) {
                data = new FormData(); // eski verileri silmek için yenile
                data.append("type", "lesson")
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

    /**
     *
     * @param scheduleTime eklenecek dersin ilk saati
     */
    function fetchAvailableClassrooms(scheduleTime, dayIndex, classroomSelect, event) {
        console.log("Dersler alınıyor")
        let data = new FormData();
        data.append("hours", event.target.value);
        data.append("time", scheduleTime)
        data.append("day", "day" + dayIndex)
        data.append("type", "lesson")
        data.append("owner_type", "classroom")
        //clear classroomSelect
        classroomSelect.innerHTML = `<option value=""> Bir Sınıf Seçin</option>`;
        fetch("/ajax/getAvailableClassroomForSchedule", {
            method: "POST",
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: data,
        })
            .then(response => response.json())
            .then((data) => {
                if (data.status === "error") {
                    new Toast().prepareToast("Hata", "Uygun ders listesi alınırken hata oluştu", "danger");
                    console.error(data.msg)
                } else {
                    data.classrooms.forEach((classroom) => {
                        let option = document.createElement("option")
                        option.value = classroom.name
                        option.innerText = classroom.name
                        classroomSelect.appendChild(option)
                    })
                }

            })
            .catch((error) => {
                new Toast().prepareToast("Hata", "Uygun ders listesi alınırken hata oluştu", "danger");
                console.error(error);
            });

    }

    function saveSchedule(lessonId, scheduleTime, lessonHours, day) {
        let data = new FormData();
        data.append("lesson_id", lessonId);
        data.append("time_start", scheduleTime);
        data.append("lesson_hours", lessonHours);
        data.append("day", day)
        return fetch("/ajax/saveSchedule", {
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
                    return false;
                } else {
                    console.log(data);
                    return true;
                }
            })
            .catch((error) => {
                console.error(error);
                return false;
            });
    }

    function dropListToTable(listElement, draggedElement, dropZone) {
        const table = dropZone.closest("table");
        /*
         Dönem kontrolü yapılıyor.
         */
        if (!checkSeason(table.dataset['season'], draggedElement.dataset['season'])) return;
        /*
         Saat ve sınıf seçimi için Modal hazırlanıyor.
         */
        let scheduleModal = new Modal();
        // Dersin kaç saat olduğu bilgisi alınıyor.
        let lessonHours = draggedElement.querySelector("span.badge").innerText
        //dersin bırakıldığı satırın tablo içindeki index numarası
        let droppedRowIndex = dropZone.closest("tr").rowIndex
        //dersin bırakıldığı sütunun satır içerisindeki index numarası
        let droppedCellIndex = dropZone.cellIndex
        // dersin bırakıldığı saat örn. 08.00-08.50
        let scheduleTime = table.rows[droppedRowIndex].cells[0].innerText;
        // Modal içerisine saat ve derslik seçimi için form ekleniyor.
        let modalContentHTML = `
            <form>
                <div class="form-floating mb-3">
                    <input class="form-control" id="selected_hours" type="number" value="${lessonHours}" min=1 max=${lessonHours}>
                    <label for="selected_hours" >Eklenecek Ders Saati</label>
                </div>
                <div class="mb-3">
                    <select id="classroom" class="form-select" required>
                    </select>
                </div>
            </form>
            `;

        scheduleModal.prepareModal("Sınıf ve Saat seçimi", modalContentHTML, true, false);
        scheduleModal.showModal();

        let selectedHoursInput = scheduleModal.body.querySelector("#selected_hours")
        let classroomSelect = scheduleModal.body.querySelector("#classroom")
        // ders saati değişince ders listesi çekilecek
        selectedHoursInput.addEventListener("change", fetchAvailableClassrooms.bind(this, scheduleTime, droppedCellIndex - 1, classroomSelect))
        selectedHoursInput.dispatchEvent(new Event("change"))

        let classroomSelectForm = scheduleModal.body.querySelector("form");
        scheduleModal.confirmButton.addEventListener("click", () => {
            classroomSelectForm.dispatchEvent(new Event("submit"));
        })

        classroomSelectForm.addEventListener("submit", async function (event) {
            event.preventDefault();
            /**
             * Modal içerisinden seçilmiş derslik verisini al
             */
            let selectedClassroom = classroomSelect.value
            if (selectedClassroom === "") {
                new Toast().prepareToast("Dikkat", "Bir derslik seçmelisiniz.", "danger");
                return;
            }

            let selectedHours = selectedHoursInput.value

            let checkedHours = 0; //drop-zone olmayan alanlar atlanacağından Kontrol edilen saatlerin sayısını takip ediyoruz
            // çakışmaları kontrol et
            for (let i = 0; checkedHours < selectedHours; i++) {
                let row = table.rows[droppedRowIndex + i];
                let cell = row.cells[droppedCellIndex];
                // Eğer hücre "drop-zone" sınıfına sahip değilse döngüyü atla öğle arası atlanıyor
                if (!cell.classList.contains("drop-zone")) {
                    continue;
                }
                if (!checkLessonCrash(cell, draggedElement)) {
                    new Toast().prepareToast("Çakışma", (i + 1) + ". satte çakışma var")
                    return;
                }
                checkedHours++
            }

            /**
             * Eklenecek ders sayısı kadar döngü oluşturup dersleri hücerelere ekleyeceğiz
             */
            let addedHours = 0; // drop-zone olmayan alanlar atlanacağından eklenen saatlerin sayısını takip ediyoruz
            for (let i = 0; addedHours < selectedHours; i++) {
                let row = table.rows[droppedRowIndex + i];
                let cell = row.cells[droppedCellIndex];

                // Eğer hücre "drop-zone" sınıfına sahip değilse döngüyü atla öğle arası atlanıyor
                if (!cell.classList.contains("drop-zone")) {
                    continue;
                }

                let lessonId = draggedElement.id.match(/\d+$/)[0];//sondaki sayı aılınıyor
                let result = await saveSchedule(lessonId, scheduleTime, lessonHours, droppedCellIndex - 1);
                if (result) {
                    let lesson = draggedElement.cloneNode(true)
                    lesson.querySelector("span.badge").innerHTML = `<i class="bi bi-door-open"></i>${selectedClassroom}`;
                    cell.appendChild(lesson);

                    //id kısmına ders saatini de ekliyorum aksi halde aynı id değerine sahip birden fazla element olur.
                    lesson.id = lesson.id.replace("available", "scheduleTable")
                    let existLessonInTableCount = table.querySelectorAll('[id^=\"' + lesson.id + '\"]').length
                    lesson.id = lesson.id + '-' + (existLessonInTableCount) // bu ekleme ders saati birimini gösteriyor. scheduleTable-lesson-1-1 scheduleTable-lesson-1-2 ...
                    //klonlanan yeni elemente de drag start olay dinleyicisi ekleniyor.
                    lesson.addEventListener('dragstart', dragStartHandler);
                    addedHours++;
                } else {
                    scheduleModal.prepareModal("Çakışma", "Ders programı uygun değil", false,true)
                    scheduleModal.body.classList.add("text-bg-danger");
                }
            }

            /*
            Dersin tamamının eklenip eklenmediğini kontrol edip duruma göre ders listede güncellenir
             */
            if (lessonHours !== selectedHours) {
                draggedElement.querySelector("span.badge").innerHTML = lessonHours - selectedHours;
            } else {
                //saatlerin tamamı bittiyse listeden sil
                draggedElement.remove();
            }
            scheduleModal.closeModal();
        })
    }

    function dropTableToList(tableElement, draggedElement, dropZone) {
        if (!checkSeason(dropZone.dataset['season'], draggedElement.dataset['season'])) return;
        //listede taşınan dersin varlığını kontrol et
        let draggedElementIdInList = draggedElement.id.replace("scheduleTable", "available"); // bırakılan dersin liste id numarası
        draggedElementIdInList = draggedElementIdInList.replace(/-\d+$/, ""); // Sondaki "-X" (ör. -1, -2, -3) kısmını kaldırır
        if (dropZone.querySelector("#" + draggedElementIdInList)) {
            // Eğer sürüklenen dersten tabloda varsa ders saati bir arttırılır
            let lessonInlist = dropZone.querySelector("#" + draggedElementIdInList);
            let hoursInList = lessonInlist.querySelector("span.badge").innerText
            lessonInlist.querySelector("span.badge").innerText = parseInt(hoursInList) + 1
            draggedElement.remove()

        } else {
            //eğer listede yoksa o ders listeye eklenir
            draggedElement.id = draggedElementIdInList
            dropZone.appendChild(draggedElement)
            draggedElement.querySelector("span.badge").innerText = 1
        }


    }

    function dropTableToTable(table, draggedElement, dropZone) {
        //dersin bırakıldığı satırın tablo içindeki index numarası
        let droppedRowIndex = dropZone.closest("tr").rowIndex
        //dersin bırakıldığı sütunun satır içerisindeki index numarası
        let droppedCellIndex = dropZone.cellIndex
        let row = table.rows[droppedRowIndex];
        let cell = row.cells[droppedCellIndex];
        if (!checkLessonCrash(cell, draggedElement)) return;
        cell.appendChild(draggedElement);

        /* dersleri toplu halde taşıma işlemi için aşağıdaki kodları yazdım. ama çok sorun çıkartıyor tek tek taşıyacağım
        let draggedLessonOrder = draggedElement.id.match(/-(\d+)$/)[1];
        let lessonID = draggedElement.id.replace(/-\d+$/, "");
        let lessonsInTable = table.querySelectorAll('[id^="' + lessonID + '"]');
        lessonsInTable.forEach((lesson) => {
            lesson.remove()
        })
        //dersin bırakıldığı satırın tablo içindeki index numarası
        let droppedRowIndex = dropZone.closest("tr").rowIndex
        //dersin bırakıldığı sütunun satır içerisindeki index numarası
        let droppedCellIndex = dropZone.cellIndex

        function checkTable(rowIndex) {
            // tablo sınırlarına dışına çıkarsa 0. satır başlık satırı
            if (rowIndex < 1 || rowIndex > table.rows.length - 1) {
                new Toast().prepareToast("Hata", "Ders belirtilen alana sığmadı", "danger")
                return false;
            }
            return true;
        }

        let luchtimePassed = false;
        for (let i = 0; i < lessonsInTable.length; i++) {
            let lesson = lessonsInTable[i];
            let lessonOrder = lesson.id.match(/-(\d+)$/)[1];
            let shift = parseInt(draggedLessonOrder) - parseInt(lessonOrder);//dersin sürüklenen derse göre konumunu belirlemek için kullaılır
            let newRowIndex = parseInt(droppedRowIndex) - shift
            if (luchtimePassed) newRowIndex += 1;
            if (!checkTable(newRowIndex)) return;
            let row = table.rows[newRowIndex];
            let cell = row.cells[droppedCellIndex];
            if (!checkTable(cell, newRowIndex)) return;
            if (!checkLessonCrash(cell, lesson)) return;
            // Eğer hücre "drop-zone" sınıfına sahip değilse satırı bir arttır. Öğle arası kontrolü
            if (!cell.classList.contains("drop-zone")) {
                newRowIndex += 1;
                luchtimePassed = true;
                let row = table.rows[newRowIndex];
                cell = row.cells[droppedCellIndex];
            }
            cell.appendChild(lesson);
        }*/
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
        /**
         * alanlar snake case olmalı
         * @type {{}}
         */
        let transferredData = {};
        /**
         * Sürükleme olayı ile gönderilen veriler transferredData objesine aktarılıyor.
         */
        for (let data_index in event.dataTransfer.types) {
            transferredData[event.dataTransfer.types[data_index]] = event.dataTransfer.getData(event.dataTransfer.types[data_index])
        }
        /**
         * Sürüklenen ve bırakılacak olan element
         * @type {Element}
         */
        const draggedElement = document.getElementById(event.dataTransfer.getData("id"))
        switch (transferredData.start_element) {
            case "list":
                if (droppedZone.classList.contains("available-schedule-items")) {
                    // Listeden Listeye
                    return;
                } else {
                    // Listeden Tabloya bırakma işlemleri
                    let list = draggedElement.closest(".available-schedule-items");
                    dropListToTable(list, draggedElement, droppedZone)
                }
                break;
            case "table":
                if (droppedZone.classList.contains("available-schedule-items")) {
                    //Tablodan Listeye
                    let table = draggedElement.closest("table");
                    dropTableToList(table, draggedElement, droppedZone)
                } else {
                    //Tablodan Tabloya
                    let table = draggedElement.closest("table");
                    dropTableToTable(table, draggedElement, droppedZone)
                }
                break;
        }
    }

    /**
     * todo Ders sürüklenmeye başladığında hoca programı kontrol edilerek uygun olmayan alanlar kırmızı olarak vurgulanacak
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
        if (event.target.closest("table")) {
            event.dataTransfer.setData("start_element", "table")
        } else if (event.target.closest(".available-schedule-items")) {
            event.dataTransfer.setData("start_element", "list")
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