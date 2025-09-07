/**
 * Ders Programı düzenleme sayfasında Programı temsil eden sınıf.
 */
let lessonDrop = new Event("lessonDrop");

class ScheduleCard {

    constructor(scheduleCardElement = null) {
        /**
         * Ders programının gösterildiği tablo elementi
         * @type {HTMLElement}
         */
        this.card = null;
        /**
         * Ders programının gösterildiği tablo elementi
         * @type {HTMLElement}
         */
        this.table = null;
        /**
         * Ders programına eklenebilecek derslerin bulunduğu liste elementi
         * @type {HTMLElement}
         */
        this.list = null;
        /**
         * Ders programının ait olduğu akademik yıl. Örn. 2025-2026
         * @type {string}
         */
        this.academic_year = null;
        /**
         * Dersprogramının ait olduğu dönem. Örn. Güz
         * @type {string}
         */
        this.semester = null;
        /**
         * Ders programının ait olduğu yarıyıl. Örn. 1
         * @type {int} 1..12
         */
        this.semester_no = null;
        /**
         * Ders programının sahibinin türü. Örn. user
         * @type {string} user, lesson, classroom, program
         */
        this.owner_type = null;
        /**
         * Ders programının sahibinin id numarası. Örn. 1
         * @type {int}
         */
        this.owner_id = null;
        /**
         * Programının türü. Örn. lesson yada exam
         * @type {string} lesson, exam
         */
        this.type = null;
        /**
         * Programın düzenlenmesi sırasında sürüklenen ders elementi
         * @type {{}}
         * todo sadece lesson id alında oradan diğer bilgiler çekilse ?
         */
        this.draggedLesson = {
            'start_element': null,
            'end_element': null,
            'semester_no': null,
            'lesson_code': null,
            'lesson_id': null,
            'lecturer_id': null,
            'time': null,
            'day_index': null,
            'semester': null,
            'academic_year': null,
            'classroom_id': null,
            'HTMLElement': null,
            'lesson_hours': null,
            'dropped_row_index': null,
            'dropped_cell_index': null
        };
        /**
         * Ders sürükleme işleminin devam edip etmediği bilgisini tutar
         * @type {boolean}
         */
        this.isDragging = false;
        /**
         * Ders bırakmaişleminin yapıldığı element
         * @type {null}
         */
        this.dropZone = null;
        this.removeLessonDropZone = null;
        /**
         * Ders Programının Sahibinin adı
         * Daha çok derslik programında işe yarıyor.
         * @type {string}
         */
        this.owner_name = null;

        if (scheduleCardElement) {
            this.initialize(scheduleCardElement)
        } else {
            new Toast().prepareToast("Hata", "Ders programı nesnesi tanımlanamadı", "danger");
        }
    }

    /**
     * Ders programı kartı yüklendikten sonra çalıştırılarak kart nesnesinin verilerini oluşturur
     * @param scheduleCardElement
     */
    initialize(scheduleCardElement) {
        this.card = scheduleCardElement;
        this.type = this.card.dataset.type ?? null;
        this.list = this.card.querySelector(".available-schedule-items");
        this.table = this.card.querySelector("table");
        this.getDataSetValue(this, this.card);
        // draggable="true" olan tüm elementleri seç
        const dragableElements = this.card.querySelectorAll('[draggable="true"]');
        //drop-zone sınıfına sahip tüm elementler
        const dropZones = this.card.querySelectorAll('.drop-zone');
        // Her bir draggable öğeye event listener ekle
        dragableElements.forEach(element => {
            element.addEventListener('dragstart', this.dragStartHandler.bind(this));
        });
        //tüm drop-zone alanları için olay dinleyicisi ekleniyor
        dropZones.forEach(element => {
            element.addEventListener("drop", this.dropHandler.bind(this, element));
            element.addEventListener("dragover", this.dragOverHandler.bind(this)) // bu olmadan çalışmıyor
        });

        //Cardiçerisindeki tüm tooltiplerin aktif edilmesi için
        var tooltipTriggerList = [].slice.call(this.card.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        this.removeLessonDropZone = this.card.querySelector(".available-schedule-items.drop-zone")
    }

    resetDraggedLesson() {
        // Önce tüm değerleri null yap
        Object.keys(this.draggedLesson).forEach(key => {
            this.draggedLesson[key] = null;
        });
    }

    getDataSetValue(object, dataSetObject) {
        function toSnakeCase(str) {
            return str.replace(/[A-Z]/g, letter => "_" + letter.toLowerCase());
        }

        Object.keys(object).forEach(key => {
            // dataset keylerini snake_case'e çevir
            for (let dataKey in dataSetObject.dataset) {
                if (toSnakeCase(dataKey) === key) {
                    object[key] = dataSetObject.dataset[dataKey];
                }
            }
        });
    }

    setDraggedLesson(lessonElement) {
        this.resetDraggedLesson();

        this.getDataSetValue(this.draggedLesson, lessonElement);
        if (event.target.closest("table")) {
            this.draggedLesson.start_element = "table";
        } else if (event.target.closest(".available-schedule-items")) {
            this.draggedLesson.start_element = "list";
        }
        this.draggedLesson.HTMLElement = lessonElement;
    }

    async highlightUnavailableCells() {
        this.clearCells();
        let data = new FormData();
        data.append("lesson_id", this.draggedLesson.lesson_id);
        data.append("semester", this.draggedLesson.semester);
        data.append("academic_year", this.draggedLesson.academic_year);

        let toast = new Toast();
        toast.prepareToast("Yükleniyor", "Kontrol ediliyor...");

        try {
            // Derslik kontrolü
            let classroomResponse = await fetch("/ajax/checkClassroomSchedule", {
                method: "POST",
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                body: data,
            });
            let classroomData = await classroomResponse.json();

            if (classroomData.status === "error") {
            } else {
                let unavailableCells = classroomData.unavailableCells;
                if (unavailableCells) {
                    for (let i = 0; i <= 9; i++) {
                        for (let cell in unavailableCells[i]) {
                            if (unavailableCells[i][cell]) {
                                this.table.rows[i].cells[cell].classList.add("text-bg-danger", "unavailable-for-classroom");
                            }
                        }
                    }
                }
            }

            // Hoca kontrolü
            let lecturerResponse = await fetch("/ajax/checkLecturerSchedule", {
                method: "POST",
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                body: data,
            });
            let lecturerData = await lecturerResponse.json();

            toast.closeToast();

            if (lecturerData.status === "error") {
            } else {
                let unavailableCells = lecturerData.unavailableCells;
                if (unavailableCells) {
                    for (let i = 0; i <= 9; i++) {
                        for (let cell in unavailableCells[i]) {
                            this.table.rows[i].cells[cell].classList.add("text-bg-danger", "unavailable-for-lecturer");
                        }
                    }
                }

                let preferredCells = lecturerData.preferredCells;
                if (preferredCells) {
                    for (let i = 0; i <= 9; i++) {
                        for (let cell in preferredCells[i]) {
                            this.table.rows[i].cells[cell].classList.add("text-bg-success");
                        }
                    }
                }
            }

            return true; // işlem tamamlandı
        } catch (error) {
            new Toast().prepareToast("Hata", "Veriler alınırken hata oluştu", "danger");
            console.error(error);
            return false;
        }
    }

    /**
     * Ders sürükleme işlemi başlatıldığında tablo üzerinde hocanın uygun olmayan saatleri kırmızı ile vurgulanıyor.
     * Bu fonksiyon o vurguları siler
     */
    clearCells() {
        for (let i = 0; i < this.table.rows.length; i++) {
            for (let j = 0; j < this.table.rows[i].cells.length; j++) {
                /*
                Öğle arası bg-danger ile vurgulandığı için bu işlem o saatlari etkilemiyor
                 */
                this.table.rows[i].cells[j].classList.remove("text-bg-danger")
                this.table.rows[i].cells[j].classList.remove("text-bg-success")
                this.table.rows[i].cells[j].classList.remove("unavailable-for-lecturer")
                this.table.rows[i].cells[j].classList.remove("unavailable-for-classroom")
            }
        }
    }

    async fetchAvailableClassrooms(classroomSelect, event) {
        let data = new FormData();
        data.append("hours", event.target.value);
        data.append("time", this.draggedLesson.time)
        data.append("day", "day" + this.draggedLesson.day_index)
        data.append("type", "lesson")
        data.append("owner_type", "classroom")
        data.append("semester", this.draggedLesson.semester)
        data.append("academic_year", this.draggedLesson.academic_year);
        data.append("lesson_id", this.draggedLesson.lesson_id);
        //clear classroomSelect
        classroomSelect.innerHTML = `<option value=""></option>`;

        let spiner = new Spinner();
        spiner.showSpinner(classroomSelect.querySelector("option"))

        await fetch("/ajax/getAvailableClassroomForSchedule", {
            method: "POST",
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: data,
        })
            .then(response => response.json())
            .then((data) => {
                spiner.removeSpinner();
                classroomSelect.innerHTML = `<option value=""> Bir Sınıf Seçin</option>`;
                if (data.status === "error") {
                    new Toast().prepareToast("Hata", "Uygun ders listesi alınırken hata oluştu", "danger");
                    console.error(data.msg)
                } else {
                    data.classrooms.forEach((classroom) => {
                        let option = document.createElement("option")
                        option.value = classroom.id
                        option.innerText = classroom.name + " (" + classroom.class_size + ")"
                        classroomSelect.appendChild(option)
                    })
                }
            })
            .catch((error) => {
                new Toast().prepareToast("Hata", "Uygun ders listesi alınırken hata oluştu", "danger");
                console.error(error);
            });
    }

    selectClassroomAndHours() {
        return new Promise((resolve, reject) => {
            let scheduleModal = new Modal();
            let modalContentHTML = `
            <form>
                <div class="form-floating mb-3">
                    <input class="form-control" id="selected_hours" type="number" 
                           value="${this.draggedLesson.lesson_hours}" 
                           min=1 max=${this.draggedLesson.lesson_hours}>
                    <label for="selected_hours">Eklenecek Ders Saati</label>
                </div>
                <div class="mb-3">
                    <select id="classroom" class="form-select" required></select>
                </div>
            </form>`;

            scheduleModal.prepareModal("Sınıf ve Saat seçimi", modalContentHTML, true, false);
            scheduleModal.showModal();

            let selectedHoursInput = scheduleModal.body.querySelector("#selected_hours");
            let classroomSelect = scheduleModal.body.querySelector("#classroom");

            selectedHoursInput.addEventListener("change", this.fetchAvailableClassrooms.bind(this, classroomSelect));
            selectedHoursInput.dispatchEvent(new Event("change"));

            let classroomSelectForm = scheduleModal.body.querySelector("form");

            scheduleModal.confirmButton.addEventListener("click", (event) => {
                event.preventDefault();
                classroomSelectForm.dispatchEvent(new SubmitEvent("submit", {cancelable: true}));
            });

            classroomSelectForm.addEventListener("submit", function (event) {
                event.preventDefault();
                // mevcut silinerek sadece derslik adı alınıyor
                let classroom_name = classroomSelect.selectedOptions[0].text.replace(/\s*\(.*\)$/, "");
                let selectedClassroom = {'id': classroomSelect.value, 'name': classroom_name};
                let selectedHours = selectedHoursInput.value;

                if (classroomSelect.value === "") {
                    new Toast().prepareToast("Dikkat", "Bir derslik seçmelisiniz.", "danger");
                    return;
                }
                scheduleModal.closeModal();
                resolve({classroom: selectedClassroom, hours: selectedHours});
            });
        });
    }

    /**
     * Derslik programı düzenlenirken eklenecek ders saati miktarını seçmek için
     * @returns {Promise<unknown>}
     */
    selectHours() {
        return new Promise((resolve, reject) => {
            let scheduleModal = new Modal();
            let modalContentHTML = `
            <form>
                <div class="form-floating mb-3">
                    <input class="form-control" id="selected_hours" type="number" 
                           value="${this.draggedLesson.lesson_hours}" 
                           min=1 max=${this.draggedLesson.lesson_hours}>
                    <label for="selected_hours">Eklenecek Ders Saati</label>
                </div>
            </form>`;

            scheduleModal.prepareModal("Saat seçimi", modalContentHTML, true, false);
            scheduleModal.showModal();

            let selectedHoursInput = scheduleModal.body.querySelector("#selected_hours");

            scheduleModal.confirmButton.addEventListener("click", (event) => {
                event.preventDefault();
                let selectedHours = selectedHoursInput.value;
                scheduleModal.closeModal();
                resolve({hours: selectedHours});
            });
        });
    }

    /**
     * bırakılan alanda başka ders olup olmadığını ve grup işlemlerini kontrol eder
     * Bırakılan alandaki ders ile bırakılan derslerin gruplarının olup olmadığını varsa farklı olup olmadığını kontrol eder
     * @param selectedHours kaç saat ders ekleneceğini belirtir
     */
    checkCrash(selectedHours) {
        return new Promise((resolve, reject) => {
            let checkedHours = 0;

            for (let i = 0; checkedHours < selectedHours; i++) {
                let row = this.table.rows[this.draggedLesson.dropped_row_index + i];
                if (!row) {
                    reject("Eklenen ders saatleri programın dışına taşıyor.");
                    return;
                }

                let cell = row.cells[this.draggedLesson.dropped_cell_index];
                if (!cell.classList.contains("drop-zone")) {
                    continue; // öğle arası gibi drop-zone olmayan hücreleri atla
                }

                let lessons = this.dropZone.querySelectorAll('[id^="scheduleTable-"]');
                if (lessons.length !== 0) {
                    if (lessons.length > 1) {
                        reject("Bu alana ders ekleyemezsiniz.");
                        return;
                    } else {
                        let existLesson = this.dropZone.querySelector('[id^="scheduleTable-"]');
                        let existCode = existLesson.getAttribute("data-lesson-code");
                        let currentCode = this.draggedLesson.lesson_code;

                        let existMatch = existCode.match(/^(.+)\.(\d+)$/);
                        let currentMatch = currentCode.match(/^(.+)\.(\d+)$/);

                        if (existMatch && currentMatch) {
                            if (existMatch[1] === currentMatch[1]) {
                                reject("Lütfen farklı bir ders seçin.");
                                return;
                            }

                            let existGroup = existMatch[2];
                            let currentGroup = currentMatch[2];

                            if (existGroup === currentGroup) {
                                reject("Gruplar aynı olamaz.");
                                return;
                            }
                        } else {
                            reject("Çakışma var, bu alana ders eklenemez.");
                            return;
                        }
                    }
                }

                checkedHours++;
            }

            resolve(true); // hiçbir sorun yoksa başarıyla tamamla
        });
    }

    moveLessonListToTable(classroom, hours) {
        /**
         * Eklenecek ders sayısı kadar döngü oluşturup dersleri hücerelere ekleyeceğiz
         */
        let addedHours = 0; // drop-zone olmayan alanlar atlanacağından eklenen saatlerin sayısını takip ediyoruz
        for (let i = 0; addedHours < hours; i++) {
            let row = this.table.rows[this.draggedLesson.dropped_row_index + i];
            let cell = row.cells[this.draggedLesson.dropped_cell_index];
            // Eğer hücre "drop-zone" sınıfına sahip değilse döngüyü atla öğle arası atlanıyor
            if (!cell.classList.contains("drop-zone")) {
                continue;
            }
            let lesson = this.draggedLesson.HTMLElement.cloneNode(true)
            lesson.dataset['dayIndex'] = this.draggedLesson.day_index;
            lesson.dataset['time'] = this.draggedLesson.time;
            lesson.dataset['classroomId'] = classroom.id
            lesson.querySelector("span.badge").innerHTML = `<a href="/admin/classroom/${classroom.id}" class="link-light link-underline-opacity-0" target="_blank">
                                                                                <i class="bi bi-door-open"></i>${classroom.name}
                                                                             </a>`;
            cell.appendChild(lesson);

            //id kısmına ders saatini de ekliyorum aksi halde aynı id değerine sahip birden fazla element olur.
            lesson.id = lesson.id.replace("available", "scheduleTable")
            let existLessonInTableCount = this.table.querySelectorAll('[id^=\"' + lesson.id + '\"]').length
            lesson.id = lesson.id + '-' + (existLessonInTableCount) // bu ekleme ders saati birimini gösteriyor. scheduleTable-lesson-1-1 scheduleTable-lesson-1-2 ...
            //klonlanan yeni elemente de drag start olay dinleyicisi ekleniyor.
            lesson.addEventListener('dragstart', this.dragStartHandler.bind(this));
            //ders kodu tooltip'i aktif ediliyor
            let codeTooltip = new bootstrap.Tooltip(lesson.querySelector('.lesson-title'))
            addedHours++;
        }
        /*
            Dersin tamamının eklenip eklenmediğini kontrol edip duruma göre ders listede güncellenir
        */
        if (this.draggedLesson.lesson_hours !== hours) {
            this.draggedLesson.HTMLElement.querySelector("span.badge").innerHTML = (this.draggedLesson.lesson_hours - hours).toString();
        } else {
            /**
             * Liste içerisinde her ders bir frame içerisinde bulunuyor.
             */
            this.draggedLesson.HTMLElement.closest("div.frame").remove();
            //saatlerin tamamı bittiyse listeden sil
            this.draggedLesson.HTMLElement.remove();

        }
    }

    async saveSchedule(hours, classroom) {
        let data = new FormData();
        data.append("type", this.type);
        data.append("lesson_id", this.draggedLesson.lesson_id);
        data.append("time", this.draggedLesson.time);
        data.append("lesson_hours", hours);
        data.append("day_index", this.draggedLesson.day_index);
        data.append("classroom_id", classroom.id);
        data.append("semester_no", isNaN(this.semester_no) ? null : this.semester_no);
        data.append("academic_year", this.academic_year);
        data.append("semester", this.semester);
        return fetch("/ajax/saveSchedule", {
            method: "POST",
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: data,
        })
            .then(response => response.json())
            .then((data) => {
                if (data && data.status === "error") {
                    console.error(data.msg);
                    new Toast().prepareToast("Hata", data.msg, "danger")
                    return false;
                } else {
                    console.info(data)
                    new Toast().prepareToast("Başarılı", "Program Kaydedildi.", "success")
                    return true;
                }
            })
            .catch((error) => {
                new Toast().prepareToast("Hata", "Program kaydedilirken hata oluştu. Detaylar için geliştirici konsoluna bakın", "danger");
                console.error(error);
                return false;
            });
    }

    async deleteSchedule(classroom_id) {
        let data = new FormData();
        data.append("type", this.type);
        data.append("lesson_id", this.draggedLesson.lesson_id);
        data.append("lecturer_id", this.draggedLesson.lecturer_id);
        data.append("time", this.draggedLesson.time);
        data.append("day_index", this.draggedLesson.day_index);
        data.append("classroom_id", classroom_id);
        data.append("semester_no", isNaN(this.semester_no) ? null : this.semester_no);
        data.append("academic_year", this.academic_year);
        data.append("semester", this.semester);

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
                    console.info(data)
                    return true;
                }
            })
            .catch((error) => {
                new Toast().prepareToast("Hata", "Program Silinirken hata oluştu. Detaylar için geliştirici konsoluna bakın", "danger");
                console.error(error);
                return false;
            });
    }

    dragStartHandler(event) {
        this.isDragging = true;
        event.dataTransfer.effectAllowed = "move";
        event.dataTransfer.dropEffect = "move";
        let lessonElement = event.target.closest('[draggable="true"]');
        this.setDraggedLesson(lessonElement)
        if (this.draggedLesson.start_element === "table") {
            /*
            * silmek için buraya sürükleyin yazısını göstermek için
            * */
            this.removeLessonDropZone.style.border = "2px dashed"
            // Bootstrap tooltip nesnesini oluştur
            const tooltip = new bootstrap.Tooltip(this.removeLessonDropZone);
            tooltip.show();
        }
        this.highlightUnavailableCells().then(() => {
            if (!this.isDragging) {
                this.clearCells();
            }
        });
    }

    dropHandler(element, event) {
        event.preventDefault();
        this.isDragging = false;
        this.clearCells();
        /*
        * silmek için buraya sürükleyin yazısını göstermek için eklenen tooltip kaldırılıyor
        * */
        this.removeLessonDropZone.style.border = ""
        const tooltip = bootstrap.Tooltip.getInstance(this.removeLessonDropZone);
        if (tooltip)
            tooltip.hide()

        this.dropZone = element;
        this.draggedLesson.end_element = this.dropZone;

        switch (this.draggedLesson.start_element) {
            case "list":
                if (this.dropZone.classList.contains("available-schedule-items")) {
                    // Listeden Listeye
                    return;
                } else {
                    this.draggedLesson.dropped_row_index = this.dropZone.closest("tr").rowIndex;
                    this.draggedLesson.dropped_cell_index = this.dropZone.cellIndex;
                    //dersin bırakıldığı sütunun satır içerisindeki index numarası
                    this.draggedLesson.day_index = this.draggedLesson.dropped_cell_index - 1 // ilk sütun saat bilgisi çıkartılıyor
                    // dersin bırakıldığı saat örn. 08.00-08.50
                    this.draggedLesson.time = this.table.rows[this.draggedLesson.dropped_row_index].cells[0].innerText;
                    // Listeden Tabloya bırakma işlemleri
                    this.dropListToTable()
                }
                break;
            case "table":
                if (this.dropZone.classList.contains("available-schedule-items")) {
                    //Tablodan Listeye
                    this.dropTableToList()
                } else {
                    this.draggedLesson.dropped_row_index = this.dropZone.closest("tr").rowIndex;
                    this.draggedLesson.dropped_cell_index = this.dropZone.cellIndex;
                    //Tablodan Tabloya
                    this.dropTableToTable()
                }
                break;
        }
        document.dispatchEvent(lessonDrop);
    }

    /**
     * Sürükleme işlemi sürdüğü sürece çalışır
     * @param event
     */
    dragOverHandler(event) {
        event.preventDefault();
        event.dataTransfer.effectAllowed = "move";
    }

    async dropListToTable() {
        if (this.owner_type !== 'classroom') {
            let {classroom, hours} = await this.selectClassroomAndHours();
            try {
                await this.checkCrash(hours);
                let saveScheduleToast = new Toast();
                saveScheduleToast.prepareToast("Yükleniyor...", "Ders, programa kaydediliyor...")

                let saveScheduleResult = await this.saveSchedule(hours, classroom);
                if (saveScheduleResult) {
                    saveScheduleToast.closeToast()
                    this.moveLessonListToTable(classroom, hours);
                } else {
                    saveScheduleToast.closeToast();
                    new Toast().prepareToast("Çakışma", "Ders programında çakışma var!", "danger");
                }
            } catch (errorMessage) {
                console.error(errorMessage)
                new Toast().prepareToast("Hata", errorMessage, "danger");
            }
        } else {
            try {
                let {hours} = await this.selectHours();
                let classroom = {'id': this.owner_id, 'name': this.owner_name}
                await this.checkCrash(hours);
                let saveScheduleToast = new Toast();
                saveScheduleToast.prepareToast("Yükleniyor...", "Ders, programa kaydediliyor...")
                let saveScheduleResult = await this.saveSchedule(hours, classroom);
                if (saveScheduleResult) {
                    saveScheduleToast.closeToast()
                    this.moveLessonListToTable(classroom, hours);
                }
            } catch (errorMessage) {
                new Toast().prepareToast("Hata", errorMessage, "danger");
                console.error(errorMessage);
            }
        }

        this.resetDraggedLesson();
    }

    async dropTableToList() {

        let deleteScheduleResult = await this.deleteSchedule(this.draggedLesson.classroom_id);

        if (deleteScheduleResult) {
            let draggedElementIdInList = "available-lesson-" + this.draggedLesson.lesson_id;
            //listede taşınan dersin varlığını kontrol et
            if (this.dropZone.querySelector("#" + draggedElementIdInList)) {
                // Eğer sürüklenen dersten tabloda varsa ders saati bir arttırılır
                let lessonInlist = this.dropZone.querySelector("#" + draggedElementIdInList);
                let hoursInList = lessonInlist.querySelector("span.badge").innerText
                lessonInlist.querySelector("span.badge").innerText = parseInt(hoursInList) + 1
                lessonInlist.dataset.lessonHours = (parseInt(lessonInlist.dataset.lessonHours) + 1).toString()
                this.draggedLesson.HTMLElement.remove()
            } else {
                //eğer listede yoksa o ders listeye eklenir
                this.draggedLesson.HTMLElement.id = draggedElementIdInList
                let draggedElementFrameDiv = document.createElement("div");
                draggedElementFrameDiv.classList.add("frame", "col-md-4", "p-0", "ps-1");
                this.list.appendChild(draggedElementFrameDiv)
                this.draggedLesson.HTMLElement.querySelector("span.badge").innerText = 1
                delete this.draggedLesson.HTMLElement.dataset.time
                delete this.draggedLesson.HTMLElement.dataset.dayIndex
                delete this.draggedLesson.HTMLElement.dataset.classroomId
                this.draggedLesson.HTMLElement.dataset.lessonHours = '1';
                //klonlanan yeni elemente de drag start olay dinleyicisi ekleniyor.
                this.draggedLesson.HTMLElement.addEventListener('dragstart', this.dragStartHandler.bind(this));
                draggedElementFrameDiv.appendChild(this.draggedLesson.HTMLElement)
            }
        }

        this.resetDraggedLesson();
    }

    async dropTableToTable() {
        let row = this.table.rows[this.draggedLesson.dropped_row_index];
        let cell = row.cells[this.draggedLesson.dropped_cell_index];
        try {
            await this.checkCrash(1);
            let deleteScheduleResult = await this.deleteSchedule(this.draggedLesson.classroom_id);
            if (deleteScheduleResult) {
                this.draggedLesson.day_index = this.draggedLesson.dropped_cell_index - 1 // ilk sütun saat bilgisi çıkartılıyor
                // dersin bırakıldığı saat örn. 08.00-08.50
                this.draggedLesson.time = this.table.rows[this.draggedLesson.dropped_row_index].cells[0].innerText;
                let saveScheduleResult = await this.saveSchedule(1, {'id': this.draggedLesson.classroom_id});
                if (saveScheduleResult) {
                    //update dataset
                    this.draggedLesson.HTMLElement.dataset.time = this.draggedLesson.time
                    this.draggedLesson.HTMLElement.dataset.dayIndex = this.draggedLesson.day_index
                    cell.appendChild(this.draggedLesson.HTMLElement);
                } else console.error("Yeni ders Eklenemedi")
            } else console.error("Eski ders Silinemedi");
        } catch (errorMessage) {
            console.error(errorMessage)
            new Toast().prepareToast("Hata", errorMessage, "danger");
        }

        this.resetDraggedLesson();
    }
}