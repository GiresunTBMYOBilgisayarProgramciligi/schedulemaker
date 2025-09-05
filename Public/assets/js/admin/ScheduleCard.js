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
            'lesson_hours': null
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
        this.academic_year = this.card.dataset.academicYear ?? null;
        this.semester = this.card.dataset.semester ?? null;
        this.semester_no = parseInt(this.card.dataset.semesterNo);
        this.owner_type = this.card.dataset.ownerType;
        this.owner_id = parseInt(this.card.dataset.ownerId);


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

    setDraggedLesson(lessonElement) {
        function toSnakeCase(str) {
            return str.replace(/[A-Z]/g, letter => "_" + letter.toLowerCase());
        }

        this.resetDraggedLesson();

        Object.keys(this.draggedLesson).forEach(key => {
            // dataset keylerini snake_case'e çevir
            for (let dataKey in lessonElement.dataset) {
                if (toSnakeCase(dataKey) === key) {
                    this.draggedLesson[key] = lessonElement.dataset[dataKey];
                }
            }
        });
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

                let selectedClassroom = classroomSelect.value;
                let selectedHours = selectedHoursInput.value;

                if (selectedClassroom === "") {
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
            let droppedRowIndex = this.dropZone.closest("tr").rowIndex;
            let droppedCellIndex = this.dropZone.cellIndex;

            for (let i = 0; checkedHours < selectedHours; i++) {
                let row = this.table.rows[droppedRowIndex + i];
                if (!row) {
                    reject("Eklenen ders saatleri programın dışına taşıyor.");
                    return;
                }

                let cell = row.cells[droppedCellIndex];
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

    async saveSchedule(hours, classroom_id = null) {
        let data = new FormData();
        data.append("type", this.type);
        data.append("lesson_id", this.draggedLesson.lesson_id);
        data.append("time", this.draggedLesson.time);
        data.append("lesson_hours", hours);
        data.append("day_index", this.draggedLesson.day_index);
        data.append("classroom_id", classroom_id ?? this.owner_id.toString());
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
                    // Listeden Tabloya bırakma işlemleri
                    this.dropListToTable()
                }
                break;
            case "table":
                if (this.dropZone.classList.contains("available-schedule-items")) {
                    //Tablodan Listeye
                    this.dropTableToList()
                } else {
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
        /**
         * Liste içerisinde her ders bir frame içerisinde bulunuyor.
         */
        const draggedElementFrameDiv = this.draggedLesson.HTMLElement.closest("div.frame");

        //dersin bırakıldığı satırın tablo içindeki index numarası
        let droppedRowIndex = this.dropZone.closest("tr").rowIndex;
        //dersin bırakıldığı sütunun satır içerisindeki index numarası
        this.draggedLesson.day_index = this.dropZone.cellIndex - 1 // ilk sütun saat bilgisi çıkartılıyor
        // dersin bırakıldığı saat örn. 08.00-08.50
        let time = this.table.rows[droppedRowIndex].cells[0].innerText;
        this.draggedLesson.time = time;
        if (this.owner_type !== 'classroom') {
            let {classroom, hours} = await this.selectClassroomAndHours();
            try {
                await this.checkCrash(hours);
                let saveScheduleToast = new Toast();
                saveScheduleToast.prepareToast("Yükleniyor...", "Ders, programa kaydediliyor...")

                let saveScheduleResult = await this.saveSchedule(hours, classroom);

            } catch (errorMessage) {
                new Toast().prepareToast("Hata", errorMessage, "danger");
            }
        } else {
            let {hours} = await this.selectHours();
            try {
                await this.checkCrash(hours);
                let saveScheduleToast = new Toast();
                saveScheduleToast.prepareToast("Yükleniyor...", "Ders, programa kaydediliyor...")
                let saveScheduleResult = await this.saveSchedule(hours);

            } catch (errorMessage) {
                new Toast().prepareToast("Hata", errorMessage, "danger");
            }
        }

        this.resetDraggedLesson();
    }

    dropTableToList() {
        console.log("TAblodan Listeye bırakıldı")

        this.resetDraggedLesson();
    }

    dropTableToTable() {
        console.log("TAblodan tabloya bırakıldı")

        this.resetDraggedLesson();
    }
}