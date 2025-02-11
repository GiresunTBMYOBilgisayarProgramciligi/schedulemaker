/**
 * Uygyn dersler listesinden ders sürüklenmeye başladığında aynı sezondaki tablo içerisinde uygun olmayan hücrelerin listesi
 * todo Bu yapı özel bulutta matematik web-dev dalında olduğu gibi tamamiyle olaylar üzerinden çalıştırılabilir.
 */
let unavailableCell;
/**
 * Program düzenleme işlemlerinde kullanılacak işlemler
 * Öncesinde myHTMLElemens.js yüklenmeli
 */
document.addEventListener("DOMContentLoaded", function () {
    const programSelect = document.getElementById("program_id");
    const programSelectButton = document.getElementById("programSelect");

    programSelectButton.addEventListener("click", function () {
        let promises = []; // Asenkron işlemleri takip etmek için bir dizi
        let data = new FormData();
        data.append("type", "lesson")
        data.append("owner_type", "program");
        data.append("owner_id", programSelect.value);
        data.append("semester", document.getElementById("semester").value)
        data.append("academic_year", document.getElementById("academic_year").value);
        promises.push(getSchedulesHTML(data));

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
    })

    function getSchedulesHTML(scheduleData =new FormData()) {
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
     * Bırakılan dersin doğru dönem dersine ait olup olmadığını belirler
     * @param droppedSemesterNo
     * @param transferredSemesterNo
     * @returns {boolean}
     */
    function checkSemesters(droppedSemesterNo, transferredSemesterNo) {
        if (droppedSemesterNo !== transferredSemesterNo) {
            new Toast().prepareToast("Dikkat", "Bu işlem yapılamaz", "danger");
            console.error("Dönemler Uyumsuz")
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
        //todo backend ile de kontrol eklenebilir.
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
     * @param dayIndex
     * @param classroomSelect
     * @param event
     */
    function fetchAvailableClassrooms(scheduleTime, dayIndex, classroomSelect, event) {
        console.log("Derslikler alınıyor")
        let data = new FormData();
        data.append("hours", event.target.value);
        data.append("time", scheduleTime)
        data.append("day", "day" + dayIndex)
        data.append("type", "lesson")
        data.append("owner_type", "classroom")
        data.append("semester", document.getElementById("semester").value)
        data.append("academic_year", document.getElementById("academic_year").value);
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
                        option.value = classroom.name//todo value yerine id nasıl olur?
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

    async function saveSchedule(scheduleData) {
        let data = new FormData();
        data.append("lesson_id", scheduleData.lesson_id);
        data.append("time_start", scheduleData.schedule_time);
        data.append("lesson_hours", scheduleData.lesson_hours);
        data.append("day_index", scheduleData.day_index);
        data.append("classroom_name", scheduleData.selected_classroom);
        data.append("semester_no", scheduleData.semester_no)
        data.append("semester", document.getElementById("semester").value)
        data.append("academic_year", document.getElementById("academic_year").value);
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

    /**
     * Belirtilen tabloda dersin hocasının dolu günleri vurgulanır
     * @param lessonId
     * @param table
     * @returns {Promise<boolean>}
     */
    function highlightUnavailableCells(lessonId, table) {
        let data = new FormData()
        data.append("lesson_id", lessonId);
        data.append("semester", document.getElementById("semester").value)
        data.append("academic_year", document.getElementById("academic_year").value);
        return fetch("/ajax/checkLecturerSchedule", {
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
                    //todo tabloya yükleniyor efekti
                    unavailableCell = data.unavailableCells
                    if (unavailableCell) {
                        for (let i = 0; i <= 9; i++) {
                            for (let cell in unavailableCell[i]) {
                                table.rows[i].cells[cell].classList.add("text-bg-danger")
                            }
                        }
                    }
                    return true;
                }
            })
            .catch((error) => {
                new Toast().prepareToast("Hata", "Hoca programı alınırken hata oluştu. Detaylar için geliştirici konsoluna bakın", "danger");
                console.error(error);
                return false;
            });
    }

    function dropListToTable(listElement, draggedElement, dropZone) {
        const table = dropZone.closest("table");
        /*
         Dönem kontrolü yapılıyor.
         */
        if (!checkSemesters(table.dataset.semesterNo, draggedElement.dataset.semesterNo)) return;
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
                if (!row) {
                    new Toast().prepareToast("Hata", "Eklelen ders saatleri programın dışına taşıyor.", "danger")
                    return;
                }
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

            let lessonId = draggedElement.dataset['lessonId'];
            let result = await saveSchedule(
                {
                    "lesson_id": lessonId,
                    "schedule_time": scheduleTime,
                    "lesson_hours": lessonHours,
                    "day_index": droppedCellIndex - 1,
                    "selected_classroom": selectedClassroom,
                    "semester_no": draggedElement.dataset.semesterNo
                });
            if (result) {
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

                    let lesson = draggedElement.cloneNode(true)
                    lesson.dataset['scheduleDay'] = droppedCellIndex - 1;
                    lesson.dataset['scheduleTime'] = row.cells[0].innerText;
                    lesson.querySelector("span.badge").innerHTML = `<i class="bi bi-door-open"></i>${selectedClassroom}`;
                    cell.appendChild(lesson);

                    //id kısmına ders saatini de ekliyorum aksi halde aynı id değerine sahip birden fazla element olur.
                    lesson.id = lesson.id.replace("available", "scheduleTable")
                    let existLessonInTableCount = table.querySelectorAll('[id^=\"' + lesson.id + '\"]').length
                    lesson.id = lesson.id + '-' + (existLessonInTableCount) // bu ekleme ders saati birimini gösteriyor. scheduleTable-lesson-1-1 scheduleTable-lesson-1-2 ...
                    //klonlanan yeni elemente de drag start olay dinleyicisi ekleniyor.
                    lesson.addEventListener('dragstart', dragStartHandler);
                    addedHours++;
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
                clearCells(table);
            } else {
                scheduleModal.prepareModal("Çakışma", "Ders programı uygun değil", false, true)
                scheduleModal.body.classList.add("text-bg-danger");
            }
        })
    }

    /**
     * Ders sürükleme işlemi başlatıldığında tablo üzerinde hocanın uygun olmayan saatleri kırmızı ile vurgulanıyor.
     * Bu fonksiyon o vurguları siler
     * @param table
     */
    function clearCells(table) {
        for (let i = 0; i < table.rows.length; i++) {
            for (let j = 0; j < table.rows[i].cells.length; j++) {
                /*
                Öğle arası bg-danger ile vurgulandığı için bu işlem o saatlari etkilemiyor
                 */
                table.rows[i].cells[j].classList.remove("text-bg-danger")
            }
        }
        /**
         * Veri Tabanından alınan Uygun olmayan hücreler bilgisi temizleniyor
         * @type {null}
         */
        unavailableCell = null;
    }

    async function deleteSchedule(scheduleData) {
        let data = new FormData();
        data.append("lesson_id", scheduleData.lesson_id);
        data.append("time", scheduleData.schedule_time);
        data.append("day_index", scheduleData.day_index);
        data.append("semester_no", scheduleData.semester_no);
        data.append("classroom_name", scheduleData.classroom_name);
        data.append("semester", document.getElementById("semester").value)
        data.append("academic_year", document.getElementById("academic_year").value);
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

    /**
     * Tablodan alınarak listeye geri bırakılan dersler için yapılan işlemler
     * @param tableElement
     * @param draggedElement
     * @param dropZone
     * @returns {Promise<void>}
     */
    async function dropTableToList(tableElement, draggedElement, dropZone) {
        console.log(draggedElement.dataset);
        console.log(dropZone.dataset.semesterNo);

        if (!checkSemesters(dropZone.dataset.semesterNo, draggedElement.dataset.semesterNo)) return;
        let result = await deleteSchedule(
            {
                "lesson_id": draggedElement.dataset['lessonId'],
                "schedule_time": draggedElement.dataset.scheduleTime,
                "day_index": draggedElement.dataset.scheduleDay,
                "semester_no": draggedElement.dataset.semesterNo,
                "classroom_name": draggedElement.querySelector("span.badge").innerText
            });
        if (result) {
            let draggedElementIdInList = "available-lesson-" + draggedElement.dataset.lessonId;
            //listede taşınan dersin varlığını kontrol et
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
                delete draggedElement.dataset.scheduleTime
                delete draggedElement.dataset.scheduleDay
            }
        }

    }

    async function dropTableToTable(table, draggedElement, dropZone) {
        //dersin bırakıldığı satırın tablo içindeki index numarası
        let droppedRowIndex = dropZone.closest("tr").rowIndex
        //dersin bırakıldığı sütunun satır içerisindeki index numarası
        let droppedCellIndex = dropZone.cellIndex
        let row = table.rows[droppedRowIndex];
        let cell = row.cells[droppedCellIndex];
        if (!checkLessonCrash(cell, draggedElement)) return;
        let deleteResult = await deleteSchedule(
            {
                "lesson_id": draggedElement.dataset.lessonId,
                "schedule_time": draggedElement.dataset.scheduleTime,
                "day_index": draggedElement.dataset.scheduleDay,
                "semester_no": draggedElement.dataset.semesterNo,
                "classroom_name": draggedElement.querySelector("span.badge").innerText
            });
        if (deleteResult) {
            console.log("Eski ders Silindi");
        } else console.log("Eski ders Silinemedi");

        let saveResult = await saveSchedule(
            {
                "lesson_id": draggedElement.dataset.lessonId,
                "schedule_time": table.rows[droppedRowIndex].cells[0].innerText,
                "lesson_hours": 1,
                "day_index": droppedCellIndex - 1,
                "selected_classroom": draggedElement.querySelector("span.badge").innerText,
                "semester_no": draggedElement.dataset.semesterNo
            });
        if (saveResult) {
            console.log("Yeni ders eklendi");


            cell.appendChild(draggedElement);
        } else console.error("Yeni ders Eklenemedi")


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
            let table = event.target.closest("table")
            let lessonID = event.target.dataset['lessonId'];
            clearCells(table);
            let result = highlightUnavailableCells(lessonID, table);
        } else if (event.target.closest(".available-schedule-items")) {
            event.dataTransfer.setData("start_element", "list")
            let table = document.querySelector('table[data-semester-no="' + event.dataTransfer.getData("semesterNo") + '"]')
            let lessonID = event.target.dataset['lessonId'];
            clearCells(table);
            let result = highlightUnavailableCells(lessonID, table);
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