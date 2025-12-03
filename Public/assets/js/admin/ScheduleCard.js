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
            'observer_id': null,//todo observer_id olarak ayrı bir id tanımlamaya gerek yok bence. Sınav programına eklenen hoca id si zaten gözetmen id si olacak dersin hocasının bilgisi alınacaksa zaten ders üzerinden alınır. Program hocası ile dersin hocası birbirinden ayrı düşünülmeli.
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
        data.append("type", this.type);

        let toast = new Toast();
        toast.prepareToast("Yükleniyor", "Program durumu kontrol ediliyor...");

        try {
            let classroomData = null;
            let programData = null;
            let lecturerData = null;

            // 👇 owner_type'a göre sadece gerekli iki isteği oluştur
            switch (this.owner_type) {
                case 'user': {
                    const [classroomRes, programRes] = await Promise.all([
                        fetch("/ajax/checkClassroomSchedule", {
                            method: "POST",
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                            body: data,
                        }),
                        fetch("/ajax/checkProgramSchedule", {
                            method: "POST",
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                            body: data,
                        })
                    ]);
                    classroomData = await classroomRes.json();
                    programData = await programRes.json();
                    break;
                }
                case 'program': {
                    const [classroomRes, lecturerRes] = await Promise.all([
                        fetch("/ajax/checkClassroomSchedule", {
                            method: "POST",
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                            body: data,
                        }),
                        fetch("/ajax/checkLecturerSchedule", {
                            method: "POST",
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                            body: data,
                        })
                    ]);
                    classroomData = await classroomRes.json();
                    lecturerData = await lecturerRes.json();
                    break;
                }
                case 'classroom': {
                    const [programRes, lecturerRes] = await Promise.all([
                        fetch("/ajax/checkProgramSchedule", {
                            method: "POST",
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                            body: data,
                        }),
                        fetch("/ajax/checkLecturerSchedule", {
                            method: "POST",
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                            body: data,
                        })
                    ]);
                    programData = await programRes.json();
                    lecturerData = await lecturerRes.json();
                    break;
                }
            }

            toast.closeToast();

            const applyCells = (map, classes = []) => {
                if (!map) return;
                Object.keys(map).forEach(rowKey => {
                    const r = parseInt(rowKey, 10);
                    if (!isNaN(r) && this.table.rows[r]) {
                        Object.keys(map[rowKey]).forEach(colKey => {
                            const c = parseInt(colKey, 10);
                            if (!isNaN(c) && this.table.rows[r].cells[c]) {
                                this.table.rows[r].cells[c].classList.add(...classes);
                            }
                        });
                    }
                });
            };

            // Derslik
            if (classroomData && classroomData.status !== "error") {
                applyCells(classroomData.unavailableCells, ["text-bg-danger", "unavailable-for-classroom"]);
            }
            // Hoca
            if (lecturerData && lecturerData.status !== "error") {
                applyCells(lecturerData.unavailableCells, ["text-bg-danger", "unavailable-for-lecturer"]);
                applyCells(lecturerData.preferredCells, ["text-bg-success"]);
            }
            // Program
            if (programData && programData.status !== "error") {
                applyCells(programData.unavailableCells, ["text-bg-danger", "unavailable-for-program"]);
            }

            return true;
        } catch (error) {
            toast.closeToast();
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
        data.append("day_index", this.draggedLesson.day_index)
        data.append("type", "lesson")
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
                classroomSelectForm.dispatchEvent(new SubmitEvent("submit", { cancelable: true }));
            });

            classroomSelectForm.addEventListener("submit", function (event) {
                event.preventDefault();
                // mevcut silinerek sadece derslik adı alınıyor
                let classroom_name = classroomSelect.selectedOptions[0].text.replace(/\s*\(.*\)$/, "");
                let selectedClassroom = { 'id': classroomSelect.value, 'name': classroom_name };
                let selectedHours = selectedHoursInput.value;

                if (classroomSelect.value === "") {
                    new Toast().prepareToast("Dikkat", "Bir derslik seçmelisiniz.", "danger");
                    return;
                }
                scheduleModal.closeModal();
                resolve({ classroom: selectedClassroom, hours: selectedHours });
            });
        });
    }

    selectClassroomAndObserver() {
        return new Promise((resolve, reject) => {
            let scheduleModal = new Modal();
            let modalContentHTML = `
            <form>
                <div class="mb-3">
                    <label class="form-label">Derslik Seçin</label>
                    <select id="classroom" class="form-select" required></select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Gözetmen Seçin</label>
                    <select id="observer" class="form-select" required></select>
                </div>
            </form>`;

            scheduleModal.prepareModal("Derslik ve Gözetmen Seçimi", modalContentHTML, true, false);
            scheduleModal.showModal();

            let classroomSelect = scheduleModal.body.querySelector("#classroom");
            let observerSelect = scheduleModal.body.querySelector("#observer");

            // populate options from hidden templates in page
            const classroomTemplate = document.getElementById("classroom_options_template");
            const lecturerTemplate = document.getElementById("lecturer_options_template");
            if (classroomTemplate) {
                classroomSelect.innerHTML = classroomTemplate.innerHTML;
            }
            if (lecturerTemplate) {
                observerSelect.innerHTML = lecturerTemplate.innerHTML;
            }

            const formEl = scheduleModal.body.querySelector("form");
            scheduleModal.confirmButton.addEventListener("click", (event) => {
                event.preventDefault();
                formEl.dispatchEvent(new SubmitEvent("submit", { cancelable: true }));
            });

            formEl.addEventListener("submit", function (event) {
                event.preventDefault();
                if (!classroomSelect.value || !observerSelect.value) {
                    new Toast().prepareToast("Dikkat", "Derslik ve gözetmen seçmelisiniz.", "danger");
                    return;
                }
                const classroom_name = classroomSelect.selectedOptions[0].text.replace(/\s*\(.*\)$/, "");
                const examSize = parseInt(classroomSelect.selectedOptions[0].dataset.examSize || '0');
                const selectedClassroom = { id: classroomSelect.value, name: classroom_name, exam_size: examSize };
                const selectedObserver = { id: observerSelect.value };
                scheduleModal.closeModal();
                resolve({ classroom: selectedClassroom, observer: selectedObserver });
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
                resolve({ hours: selectedHours });
            });
        });
    }

    /**
     * bırakılan alanda başka ders olup olmadığını ve grup işlemlerini kontrol eder
     * Bırakılan alandaki ders ile bırakılan derslerin gruplarının olup olmadığını varsa farklı olup olmadığını kontrol eder
     * @param selectedHours kaç saat ders ekleneceğini belirtir
     */
    checkCrash(selectedHours, classroom = null) {
        return new Promise((resolve, reject) => {
            let checkedHours = 0;
            const newLessonCode = this.draggedLesson.lesson_code;
            const newClassroomId = classroom ? classroom.id : this.draggedLesson.classroom_id;
            const newLecturerId = this.draggedLesson.observer_id || this.draggedLesson.lecturer_id;

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

                let lessons = cell.querySelectorAll('[id^="scheduleTable-"]');
                if (lessons.length !== 0) {
                    if (this.type === 'exam') {
                        // Sınav Programı Kuralları
                        for (let existingLesson of lessons) {
                            const existCode = existingLesson.getAttribute("data-lesson-code");
                            const existClassroomId = existingLesson.getAttribute("data-classroom-id");
                            const existLecturerId = existingLesson.getAttribute("data-lecturer-id");

                            // 1. Aynı ders kontrolü (Base code kontrolü)
                            let existMatch = existCode.match(/^(.+)\.(\d+)$/);
                            let currentMatch = newLessonCode.match(/^(.+)\.(\d+)$/);
                            let existBase = existMatch ? existMatch[1] : existCode;
                            let currentBase = currentMatch ? currentMatch[1] : newLessonCode;

                            if (existBase !== currentBase) {
                                reject("Sınav programında aynı saate farklı dersler konulamaz.");
                                return;
                            }

                            // 2. Farklı Derslik Kontrolü
                            if (existClassroomId == newClassroomId) {
                                reject("Aynı derslikte aynı saatte birden fazla sınav olamaz.");
                                return;
                            }

                            // 3. Farklı Gözetmen Kontrolü
                            if (existLecturerId == newLecturerId) {
                                reject("Aynı gözetmen aynı saatte birden fazla sınavda görev alamaz.");
                                return;
                            }
                        }
                    } else {
                        // Ders Programı Kuralları
                        if (lessons.length > 1) {
                            reject("Bu alana ders ekleyemezsiniz.");
                            return;
                        } else {
                            let existLesson = cell.querySelector('[id^="scheduleTable-"]');
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
            if (this.type === 'exam' && this.draggedLesson.observer_id) {
                lesson.dataset['lecturerId'] = this.draggedLesson.observer_id;
            }
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
        if (this.type === 'exam') {
            const currentRemaining = parseInt(this.draggedLesson.HTMLElement.querySelector("span.badge").innerText) || parseInt(this.draggedLesson.lesson_hours) || 0;
            const decrement = parseInt(classroom.exam_size || 0);
            const newRemaining = Math.max(0, currentRemaining - (isNaN(decrement) ? 0 : decrement));
            if (newRemaining > 0) {
                this.draggedLesson.HTMLElement.querySelector("span.badge").innerText = newRemaining.toString();
                this.draggedLesson.HTMLElement.dataset.lessonHours = newRemaining.toString();
            } else {
                this.draggedLesson.HTMLElement.closest("div.frame")?.remove();
                this.draggedLesson.HTMLElement.remove();
            }
        } else {
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
    }

    async checkCrashBackEnd(hours, classroom) {
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
        return fetch("/ajax/checkScheduleCrash", {
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
                    return true;
                }
            })
            .catch((error) => {
                new Toast().prepareToast("Hata", "Program kaydedilirken hata oluştu. Detaylar için geliştirici konsoluna bakın", "danger");
                console.error(error);
                return false;
            });
    }

    async saveSchedule(hours, classroom) {
        let data = new FormData();
        data.append("type", this.type);
        data.append("lesson_id", this.draggedLesson.lesson_id);
        data.append("time", this.draggedLesson.time);
        data.append("lesson_hours", hours);
        data.append("day_index", this.draggedLesson.day_index);
        data.append("classroom_id", classroom.id);
        if (this.type === 'exam' && this.draggedLesson.observer_id) {
            data.append("lecturer_id", this.draggedLesson.observer_id);
        }
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
            let classroom, hours, observer;
            if (this.type === 'exam') {
                const result = await this.selectClassroomAndObserver();
                classroom = result.classroom;
                observer = result.observer;
                this.draggedLesson.observer_id = observer.id;
                hours = 1; // sınav için saat birimi slot bazında
            } else {
                const result = await this.selectClassroomAndHours();
                classroom = result.classroom;
                hours = result.hours;
            }
            try {
                await this.checkCrash(hours, classroom);
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
                let { hours } = await this.selectHours();
                let classroom = { 'id': this.owner_id, 'name': this.owner_name }
                await this.checkCrash(hours, classroom);
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
            /**
             * Dersin alındığı hücrenin gün bilgisi. silme işlem için kullanılacak
             * @type {null|*}
             */
            let temp_day_index = this.draggedLesson.day_index;
            /**
             * Dersin alındığı hücrenin saat bilgisi ders silinirken kullanılacak.
             */
            let temp_time = this.draggedLesson.time
            /*
             Dersin gün bilgisi bırakıldığı hücrenin gün bilgisi ile değiştiriliyor.
             */
            this.draggedLesson.day_index = this.draggedLesson.dropped_cell_index - 1 // ilk sütun saat bilgisi çıkartılıyor
            // dersin bırakıldığı saat örn. 08.00-08.50
            this.draggedLesson.time = this.table.rows[this.draggedLesson.dropped_row_index].cells[0].innerText;
            /*
                Dersin bırakıldığı gün ve saat için çakışma olup olmadığı kontrol ediliyor.
             */
            let checkCrashBackEndResult = await this.checkCrashBackEnd(1, { 'id': this.draggedLesson.classroom_id })

            if (checkCrashBackEndResult) {
                /*
                Sürükleme işlemi başlatıldığında dersin bulunduğu hücrenin bilgileri silme işlemi için güncelleniyor.
                 */
                this.draggedLesson.day_index = temp_day_index
                // dersin bırakıldığı saat örn. 08.00-08.50
                this.draggedLesson.time = temp_time;

                let deleteScheduleResult = await this.deleteSchedule(this.draggedLesson.classroom_id);
                if (deleteScheduleResult) {
                    /*
                    Kaydetme işlemi için dersin bırakıldığı hücrenin gün ve saat bilgisi ayarlanıyor
                     */
                    this.draggedLesson.day_index = this.draggedLesson.dropped_cell_index - 1 // ilk sütun saat bilgisi çıkartılıyor
                    // dersin bırakıldığı saat örn. 08.00-08.50
                    this.draggedLesson.time = this.table.rows[this.draggedLesson.dropped_row_index].cells[0].innerText;
                    let saveScheduleResult = await this.saveSchedule(1, { 'id': this.draggedLesson.classroom_id });
                    if (saveScheduleResult) {
                        //update dataset
                        this.draggedLesson.HTMLElement.dataset.time = this.draggedLesson.time
                        this.draggedLesson.HTMLElement.dataset.dayIndex = this.draggedLesson.day_index
                        cell.appendChild(this.draggedLesson.HTMLElement);
                    } else console.error("Yeni ders Eklenemedi")
                } else console.error("Eski ders Silinemedi");
            }
        } catch (errorMessage) {
            console.error(errorMessage)
            new Toast().prepareToast("Hata", errorMessage, "danger");
        }

        this.resetDraggedLesson();
    }
}