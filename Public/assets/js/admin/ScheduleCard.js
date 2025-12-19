let lessonDrop = new Event("lessonDrop");
/**
 * Ders Programı düzenleme sayfasında Programı temsil eden sınıf.
 */
class ScheduleCard {

    constructor(scheduleCardElement = null) {
        /**
         * Ders programının gösterildiği tablo elementi
         * @type {HTMLElement}
         */
        this.card = null;
        /**
         * Ders programının id numarası schedule_id
         * @type {int}
         */
        this.id = null;
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
         * @type {string} lesson, midterm-exam, final-exam, makeup-exam
         */
        this.type = null;
        /**
         * Sınav programının türü. Örn. midterm-exam, final-exam, makeup-exam
         * @type {Array}
         */
        this.examTypes = ['midterm-exam', 'final-exam', 'makeup-exam']
        /**
         * Programın düzenlenmesi sırasında sürüklenen ders elementi
         * @type {{}}
         */
        this.draggedLesson = {
            'start_element': null,// table, list
            'end_element': null,
            'schedule_item_id': null,
            'lesson_id': null,
            'lesson_code': null,
            'lecturer_id': null,
            'group_no': null,
            'day_index': null,
            'classroom_id': null,
            'HTMLElement': null,
            'lesson_hours': null,
            'observer_id': null,
            'size': null,
            'classroom_exam_size': null,
            'classroom_size': null,
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
    async initialize(scheduleCardElement) {
        this.card = scheduleCardElement;
        this.id = this.card.dataset.scheduleId ?? null;
        this.duration = parseInt(this.card.dataset.duration) || 50;
        this.breakDuration = parseInt(this.card.dataset.break) || 0;
        let schedule = await this.getSchedule();
        this.list = this.card.querySelector(".available-schedule-items");
        this.table = this.card.querySelector("table.active");

        Object.keys(schedule).forEach((key) => {
            this[key] = schedule[key];
        })

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

        this.removeLessonDropZone = this.card.querySelector(".available-schedule-items.drop-zone")

        this.initStickyHeaders();
    }

    initStickyHeaders() {
        const availableList = this.card.querySelector('.available-schedule-items');
        const table = this.card.querySelector('.schedule-table');
        const thead = table.querySelector('thead');

        if (!availableList || !table || !thead) return;

        // Create a wrapper for sticky elements
        this.stickyWrapper = document.createElement('div'); // Make it a class property
        this.stickyWrapper.className = 'sticky-header-wrapper';
        this.stickyWrapper.style.position = 'fixed';

        // Calculate offset dynamically
        const navbar = document.querySelector('.app-header') || document.querySelector('.main-header') || document.querySelector('nav.navbar');
        const isNavbarFixed = navbar && (getComputedStyle(navbar).position === 'fixed' || document.body.classList.contains('layout-navbar-fixed'));
        const topOffset = isNavbarFixed ? navbar.offsetHeight : 0;

        this.stickyWrapper.style.top = topOffset + 'px';
        this.stickyWrapper.style.zIndex = '1039'; // High z-index but below modals
        this.stickyWrapper.style.display = 'none';
        this.stickyWrapper.style.width = this.card.offsetWidth + 'px';
        this.stickyWrapper.style.backgroundColor = '#fff';
        this.stickyWrapper.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';

        // Initial content population
        this.updateStickyList();

        // Clone Table Header
        const tableClone = document.createElement('table');
        tableClone.className = table.className;
        tableClone.style.marginBottom = '0';

        const theadClone = thead.cloneNode(true);
        tableClone.appendChild(theadClone);

        // Wrap table clone in a container to match structure if needed
        const tableContainer = document.createElement('div');
        tableContainer.className = 'schedule-table-container mb-0';
        tableContainer.style.overflow = 'hidden'; // Hide scrollbars on clone
        tableContainer.appendChild(tableClone);

        this.stickyWrapper.appendChild(tableContainer);

        this.card.appendChild(this.stickyWrapper);

        // Sync Widths Function
        const syncWidths = () => {
            const originalThs = thead.querySelectorAll('th');
            const cloneThs = theadClone.querySelectorAll('th');

            originalThs.forEach((th, index) => {
                if (cloneThs[index]) {
                    cloneThs[index].style.width = th.offsetWidth + 'px';
                    cloneThs[index].style.minWidth = th.offsetWidth + 'px'; // Force min-width
                    cloneThs[index].style.boxSizing = 'border-box';
                }
            });

            this.stickyWrapper.style.width = this.card.offsetWidth + 'px';
            // Sync horizontal scroll
            tableContainer.scrollLeft = this.table.parentElement.scrollLeft;
        };

        // Scroll Event Listener
        window.addEventListener('scroll', () => {
            const cardRect = this.card.getBoundingClientRect();

            // Re-calculate offset in case of resize/dynamic changes
            const navbar = document.querySelector('.app-header') || document.querySelector('.main-header') || document.querySelector('nav.navbar');
            const isNavbarFixed = navbar && (getComputedStyle(navbar).position === 'fixed' || document.body.classList.contains('layout-navbar-fixed'));
            const offset = isNavbarFixed ? navbar.offsetHeight : 0;

            // Adjust trigger point slightly to avoid flicker
            if (cardRect.top < offset && cardRect.bottom > offset + availableList.offsetHeight + thead.offsetHeight) {
                if (this.stickyWrapper.style.display !== 'block') {
                    this.updateStickyList();
                }

                this.stickyWrapper.style.display = 'block';
                this.stickyWrapper.style.left = cardRect.left + 'px';
                this.stickyWrapper.style.top = offset + 'px'; // Ensure update if navbar changes height

                // Hide original available list visibility (not display:none to keep space)
                availableList.style.visibility = 'hidden';
                thead.style.visibility = 'hidden';

                syncWidths();
            } else {
                this.stickyWrapper.style.display = 'none';
                availableList.style.visibility = 'visible';
                thead.style.visibility = 'visible';
            }
        });

        // Sync horizontal scroll
        const originalTableContainer = this.table.parentElement;
        originalTableContainer.addEventListener('scroll', (e) => {
            if (this.stickyWrapper.style.display === 'block') {
                tableContainer.scrollLeft = e.target.scrollLeft;
            }
        });

        window.addEventListener('resize', syncWidths);
    }

    updateStickyList() {
        if (!this.stickyWrapper) return;
        const availableList = this.list; // Original list
        if (!availableList) return;

        const oldList = this.stickyWrapper.querySelector('.sticky-list-clone');
        if (oldList) oldList.remove();

        const listClone = availableList.cloneNode(true);
        listClone.id = ''; // Remove ID to avoid conflicts
        listClone.classList.add('sticky-list-clone');
        listClone.style.visibility = 'visible'; // Ensure it's visible even if original is hidden

        // Remove IDs from children to prevent duplicate IDs in DOM
        listClone.querySelectorAll('[id]').forEach(el => el.removeAttribute('id'));

        // Re-bind drag events
        const dragableElements = listClone.querySelectorAll('[draggable="true"]');
        dragableElements.forEach(element => {
            element.addEventListener('dragstart', this.dragStartHandler.bind(this));
        });

        this.stickyWrapper.prepend(listClone);
    }

    async getSchedule() {
        if (!this.id) return null;

        let data = new FormData();
        data.append("id", this.id);

        return fetch("/ajax/getSchedule", {
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
                    return data.schedule;
                }
            })
            .catch((error) => {
                new Toast().prepareToast("Hata", "Program bilgisi alınırken hata oluştu.", "danger");
                console.error(error);
                return false;
            });
    }

    resetDraggedLesson() {
        // Önce tüm değerleri null yap
        Object.keys(this.draggedLesson).forEach(key => {
            this.draggedLesson[key] = null;
        });
    }

    getDatasetValue(setObject, getObject) {
        /**
         * dataset keylerini snake_case'e çevirir
         * */
        function toSnakeCase(str) {
            return str.replace(/[A-Z]/g, letter => "_" + letter.toLowerCase());
        }

        Object.keys(setObject).forEach(key => {
            for (let dataKey in getObject.dataset) {
                if (toSnakeCase(dataKey) === key) {
                    setObject[key] = getObject.dataset[dataKey];
                }
            }
        });
    }

    setDraggedLesson(lessonElement, dragEvent) {
        this.resetDraggedLesson();

        this.getDatasetValue(this.draggedLesson, lessonElement);
        if (dragEvent.target.closest("table")) {
            this.draggedLesson.start_element = "table";
        } else if (dragEvent.target.closest(".available-schedule-items")) {
            this.draggedLesson.start_element = "list";
        }
        this.draggedLesson.HTMLElement = lessonElement;
    }
    /**
     * Bu metod düzenlenen program türüne göre sürükleme işlemi başlatıldığında uygun olan yada olmayan hüceleri vurgular
     * @returns 
     */
    async highlightUnavailableCells() {
        //todo
        this.clearCells();

        let data = new FormData();
        data.append("lesson_id", this.draggedLesson.lesson_id);
        data.append("semester", this.semester);
        data.append("academic_year", this.academic_year);
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

    async fetchAvailableClassrooms(classroomSelect, hours) {
        let data = new FormData();
        data.append("schedule_id", this.id);
        data.append("hours", hours);
        data.append("startTime", this.draggedLesson.end_element.dataset.startTime)
        data.append("day_index", this.draggedLesson.end_element.dataset.dayIndex)
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
                        option.innerText = classroom.name + " (" + (this.examTypes.includes(this.type) ? classroom.exam_size : classroom.class_size) + ")"
                        option.dataset.examSize = classroom.exam_size;
                        option.dataset.size = classroom.class_size;
                        classroomSelect.appendChild(option)

                    })
                }
            })
            .catch((error) => {
                new Toast().prepareToast("Hata", "Uygun ders listesi alınırken hata oluştu", "danger");
                console.error(error);
            });
    }

    async fetchAvailableObservers(observerSelect, hours) {
        let data = new FormData();
        data.append("hours", hours); // Sınavlar genelde 1 saatlik bloklar halinde eklenir veya kontrol edilir
        data.append("time", this.draggedLesson.time)
        data.append("day_index", this.draggedLesson.day_index)
        data.append("type", this.type)
        data.append("semester", this.draggedLesson.semester)
        data.append("academic_year", this.draggedLesson.academic_year);

        //clear observerSelect
        observerSelect.innerHTML = `<option value=""></option>`;

        let spiner = new Spinner();
        spiner.showSpinner(observerSelect.querySelector("option"))

        await fetch("/ajax/getAvailableObserversForSchedule", {
            method: "POST",
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: data,
        })
            .then(response => response.json())
            .then((data) => {
                spiner.removeSpinner();
                observerSelect.innerHTML = `<option value=""> Bir Gözetmen Seçin</option>`;
                if (data.status === "error") {
                    new Toast().prepareToast("Hata", "Uygun gözetmen listesi alınırken hata oluştu", "danger");
                    console.error(data.msg)
                } else {
                    data.observers.forEach((observer) => {
                        let option = document.createElement("option")
                        option.value = observer.id
                        option.innerText = observer.title + " " + observer.name + " " + observer.last_name;
                        observerSelect.appendChild(option)
                    })
                }
            })
            .catch((error) => {
                new Toast().prepareToast("Hata", "Uygun gözetmen listesi alınırken hata oluştu", "danger");
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

            selectedHoursInput.addEventListener("change", (event) => {
                this.fetchAvailableClassrooms(classroomSelect, event.target.value);
            });
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
                <div class="form-floating mb-3">
                    <input class="form-control" id="selected_hours" type="number" 
                           value="1" 
                           min=1 max=${this.draggedLesson.size}>
                    <label for="selected_hours">Sınav Süresi (Saat)</label>
                </div>
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

            let selectedHoursInput = scheduleModal.body.querySelector("#selected_hours");
            let classroomSelect = scheduleModal.body.querySelector("#classroom");
            let observerSelect = scheduleModal.body.querySelector("#observer");

            const updateLists = () => {
                this.fetchAvailableClassrooms(classroomSelect, selectedHoursInput.value);
                this.fetchAvailableObservers(observerSelect, selectedHoursInput.value);
            };

            selectedHoursInput.addEventListener("change", updateLists);

            // Initial fetch
            updateLists();

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
                const size = parseInt(classroomSelect.selectedOptions[0].dataset.size || '0'); // classroom size
                const selectedClassroom = { id: classroomSelect.value, name: classroom_name, exam_size: examSize, size: size };
                const selectedObserver = { id: observerSelect.value, full_name: observerSelect.selectedOptions[0].text };
                const selectedHours = selectedHoursInput.value;
                scheduleModal.closeModal();
                resolve({ classroom: selectedClassroom, observer: selectedObserver, hours: selectedHours });
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
            const newGroupNo = this.draggedLesson.group_no;
            const newClassroomId = classroom ? classroom.id : this.draggedLesson.classroom_id;
            const newLecturerId = this.draggedLesson.observer_id || this.draggedLesson.lecturer_id; //todo gözetmen ve hoca farkı düşünülmeli
            for (let i = 0; checkedHours < selectedHours; i++) {
                let row = this.table.rows[this.draggedLesson.end_element.closest("tr").rowIndex + i];
                if (!row) {
                    console.error("Eklenen ders saatleri programın dışına taşıyor.")
                    reject("Eklenen ders saatleri programın dışına taşıyor.");
                    return;
                }

                let cell = row.cells[this.draggedLesson.end_element.cellIndex];
                if (!cell || !cell.classList.contains("drop-zone") || cell.querySelector('.slot-unavailable')) {
                    if (cell && cell.querySelector('.slot-unavailable')) {
                        new Toast().prepareToast("Dikkat", "Uygun olmayan ders saatleri atlandı.", "info");
                    }
                    continue; // öğle arası gibi drop-zone olmayan hücreleri atla
                }

                let lessons = cell.querySelectorAll('.lesson-card');
                if (lessons.length !== 0) {
                    if (this.examTypes.includes(this.type)) { //todo sınav çakışması kontrolü yapılacak
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
                        let isGroup = Boolean(cell.querySelector('.lesson-group-container'));

                        if (!isGroup) {
                            console.error("Bu alana ders ekleyemezsiniz.")
                            reject("Bu alana ders ekleyemezsiniz.");
                            return;
                        } else {
                            lessons.forEach((lesson) => {
                                if (this.draggedLesson.group_no < 1) {
                                    console.error("Eklenen ders gruplu değil, bu alana eklenemez")
                                    reject("Eklenen ders gruplu değil, bu alana eklenemez");
                                    return;
                                }
                                if (lesson.dataset.lessonCode === newLessonCode) {
                                    console.error("Lütfen farklı bir ders seçin.")
                                    reject("Lütfen farklı bir ders seçin.");
                                    return;
                                }
                            })

                            lessons.forEach((lesson) => {
                                if (lesson.dataset.groupNo === newGroupNo) {
                                    console.error("Grup numaraları aynı olamaz.")
                                    reject("Grup numaraları aynı olamaz.");
                                    return;
                                }
                            })

                        }
                    }
                }

                checkedHours++;
            }

            resolve(true); // hiçbir sorun yoksa başarıyla tamamla
        });
    }

    lessonHourToMinute(hours) {
        return hours * this.duration;
    }

    addMinutes(timeStr, minutes) {
        let [h, m] = timeStr.split(':').map(Number);
        let date = new Date();
        date.setHours(h, m, 0, 0);
        date.setMinutes(date.getMinutes() + minutes);
        return date.toTimeString().slice(0, 5);
    }

    generateScheduleItems(hours, classroom) {
        let scheduleItems = [];
        let currentItem = null;
        let addedHours = 0;
        let i = 0;
        let breakTime = this.breakDuration;
        let scheduleItemData = {
            "lesson_id": this.draggedLesson.lesson_id,
            "lecturer_id": this.draggedLesson.lecturer_id,
            "classroom_id": classroom.id
        }
        let status = this.draggedLesson.group_no > 0 ? "group" : "single";

        // Loop until we have filled required hours or ran out of rows
        while (addedHours < hours) {
            // Calculate row index based on drop position + offset
            let rowIndex = this.draggedLesson.end_element.closest("tr").rowIndex + i;

            // Boundary check: Stop if we go past the last row
            if (rowIndex >= this.table.rows.length) {
                break;
            }

            let row = this.table.rows[rowIndex];
            let cell = row.cells[this.draggedLesson.end_element.cellIndex];

            // Validate slot: Must be a drop-zone and not marked unavailable
            let isValid = cell && cell.classList.contains("drop-zone") && !cell.querySelector('.slot-unavailable');

            if (isValid) {
                if (!currentItem) {
                    // Start a new schedule item block
                    currentItem = {
                        'id': this.draggedLesson.schedule_item_id,
                        'schedule_id': this.id,
                        'day_index': this.draggedLesson.end_element.dataset.dayIndex,
                        'week_index': this.table.dataset.weekIndex,
                        'start_time': cell.dataset.startTime,
                        'end_time': null,
                        'status': status,
                        'data': scheduleItemData,
                        'detail': null
                    };

                    // Check if merging with an existing item ID (if applicable)
                    if (cell.dataset.scheduleItemId) {
                        currentItem.id = cell.dataset.scheduleItemId;
                    }
                }

                // Extend the current item's end time
                let slotDuration = this.lessonHourToMinute(1);

                if (currentItem.end_time) {
                    // Subsequent slot: add break time + slot duration
                    currentItem.end_time = this.addMinutes(currentItem.end_time, slotDuration + breakTime);
                } else {
                    // First slot: add only slot duration
                    currentItem.end_time = this.addMinutes(currentItem.start_time, slotDuration);
                }

                addedHours++;
            } else {
                // Gap encountered (unavailable slot or break)
                if (currentItem) {
                    // Finalize and push the current block
                    scheduleItems.push(currentItem);
                    currentItem = null;
                }
                // Continue scanning next slots without incrementing addedHours
            }
            i++;
        }

        // Push the last item if it exists
        if (currentItem) {
            scheduleItems.push(currentItem);
        }

        console.log('Generated Schedule Items:', scheduleItems);
        return scheduleItems;
    }

    moveLessonListToTable(scheduleItems, classroom) {
        console.log('moveLessonListToTable', scheduleItems, classroom);

        let addedHours = 0;

        scheduleItems.forEach(item => {
            let itemStartTime = item.start_time;
            let itemEndTime = item.end_time;
            // day_index should typically be an integer, ensure it
            let targetDayIndex = parseInt(item.day_index, 10);
            let colIndex = targetDayIndex + 1; // 0 index is time column

            // Tablo satırlarını gezerek uygun saat aralığını bul
            for (let i = 0; i < this.table.rows.length; i++) {
                let row = this.table.rows[i];
                let cell = row.cells[colIndex];

                if (!cell) continue;

                let cellStartTime = cell.dataset.startTime;
                if (!cellStartTime && row.cells[0]) {
                    cellStartTime = row.cells[0].innerText.trim().substring(0, 5);
                }

                // Hücrenin saati, itemin saati aralığında ise (başlangıç dahil, bitiş hariç)
                if (cellStartTime && cellStartTime >= itemStartTime && cellStartTime < itemEndTime) {

                    // Hücredeki empty-slot'u temizle
                    let emptySlot = cell.querySelector('.empty-slot');
                    if (emptySlot) {
                        emptySlot.remove();
                    }

                    // Hücreye schedule-item-id ata
                    cell.dataset.scheduleItemId = item.id;

                    // Group handling
                    let container;
                    if (item.status === 'group') {
                        container = cell.querySelector('.lesson-group-container');
                        if (!container) {
                            container = document.createElement('div');
                            container.classList.add('lesson-group-container');
                            cell.appendChild(container);
                        }
                    } else {
                        container = cell;
                    }

                    // Elementi Klonla
                    let lessonCard = this.draggedLesson.HTMLElement.cloneNode(true);

                    // Gereksiz classları temizle (frame, col-md-4 vb.)
                    lessonCard.className = this.draggedLesson.HTMLElement.className
                        .replace('col-md-4', '')
                        .replace('p-0', '')
                        .replace('ps-1', '')
                        .replace('frame', '')
                        .trim();

                    if (!lessonCard.classList.contains('lesson-card')) lessonCard.classList.add('lesson-card');

                    // Attribute'leri ayarla
                    lessonCard.setAttribute('draggable', 'true');
                    lessonCard.dataset.scheduleItemId = item.id;
                    lessonCard.dataset.groupNo = this.draggedLesson.group_no || 0;
                    lessonCard.dataset.size = this.draggedLesson.size || 0;
                    lessonCard.dataset.lessonId = this.draggedLesson.lesson_id;
                    lessonCard.dataset.lessonCode = this.draggedLesson.lesson_code;
                    lessonCard.dataset.classroomId = classroom.id;
                    lessonCard.dataset.classroomSize = classroom.size;
                    lessonCard.dataset.classroomExamSize = classroom.exam_size;

                    // Lecturer handling
                    let lecturerId;
                    if (this.examTypes.includes(this.type) && this.draggedLesson.observer_id) {
                        lecturerId = this.draggedLesson.observer_id;
                    } else {
                        lecturerId = this.draggedLesson.lecturer_id;
                    }
                    lessonCard.dataset.lecturerId = lecturerId;

                    // Update Classroom Name in View
                    let classroomSpan = lessonCard.querySelector('.lesson-classroom');
                    if (classroomSpan) {
                        classroomSpan.innerHTML = `${classroom.name}`;
                    }

                    // Tooltip'i yeniden tanımla (klonlandığı için)
                    // Önce eski tooltip instance'ı varsa temizlemek gerekebilir ama yeni element olduğu için sorun olmaz.
                    // Title attribute'u varsa tooltip oluşur.
                    let lessonNameSpan = lessonCard.querySelector('.lesson-name');
                    if (lessonNameSpan) new bootstrap.Tooltip(lessonNameSpan);

                    // Event Listener Ekle
                    lessonCard.addEventListener('dragstart', this.dragStartHandler.bind(this));

                    // ID Update to avoid duplicates
                    // id="available-lesson-..." formatında geliyor olabilir.
                    lessonCard.id = lessonCard.id.replace("available", "scheduleTable");
                    // Eğer tabloda aynı id varsa unique yap
                    let existLessonInTableCount = this.table.querySelectorAll('[id^=\"' + lessonCard.id + '\"]').length;
                    lessonCard.id = lessonCard.id + '-' + existLessonInTableCount;

                    // Append to Container
                    container.appendChild(lessonCard);
                    addedHours++;
                }
            }
        });

        // Available List Güncelleme Logic'i

        // Target the ORIGINAL element for updates, regardless of which one was dragged
        let targetElement = this.draggedLesson.HTMLElement;

        // If dragged element is in sticky wrapper, find the original in main list
        if (targetElement.closest('.sticky-header-wrapper')) {
            const lessonId = this.draggedLesson.lesson_id;
            // Original items have 'data-lesson-id' set (assuming structure). 
            // Or if we stripped IDs but kept dataset, we look up by dataset.
            targetElement = this.list.querySelector(`[data-lesson-id="${lessonId}"]`);

            if (!targetElement) {
                console.error('Original lesson element not found for update!', lessonId);
                // Fallback to dragged element if original not found (should not happen)
                targetElement = this.draggedLesson.HTMLElement;
            }
        }

        if (this.examTypes.includes(this.type)) {
            const currentRemaining = parseInt(this.draggedLesson.size || 0);
            const decrement = parseInt(classroom.exam_size || 0);
            const newRemaining = Math.max(0, currentRemaining - decrement);

            if (newRemaining > 0) {
                targetElement.querySelector(".lesson-classroom").innerText = newRemaining.toString();
                targetElement.dataset.size = newRemaining.toString();
                // Update draggedLesson info potentially for consistency
            } else {
                targetElement.closest("div.frame")?.remove();
                targetElement.remove();
            }
        } else {
            // Lesson Types: addedHours corresponds to hours deducted
            if (this.draggedLesson.lesson_hours > addedHours) {
                let newHours = this.draggedLesson.lesson_hours - addedHours;
                targetElement.querySelector(".lesson-classroom").innerHTML = newHours.toString() + " Saat";
                this.draggedLesson.lesson_hours = newHours; // Update local state if needed
                targetElement.dataset.lessonHours = newHours;
            } else {
                targetElement.closest("div.frame")?.remove();
                targetElement.remove();
            }
        }

        this.updateStickyList(); // Refresh the sticky view
    }
    /**
     * tablodan tabloya aktarımda  silme işlemi yapıldıktan sonra kaydetme işleminde çakışma olduğunda ders silinmiş oluyor. Önce çakışma kontrolü yapılmalı. 
     * todo kaydetme yada silme işleminden önce çakışma kontrolü backend ile de yapılmalı
     * @param {*} hours 
     * @param {*} classroom 
     * @returns 
     */
    async checkCrashBackEnd(scheduleItems) {
        let data = new FormData();
        data.append("items", JSON.stringify(scheduleItems));

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

    async saveScheduleItems(scheduleItems) {
        let data = new FormData();
        data.append('items', JSON.stringify(scheduleItems));

        return fetch("/ajax/saveScheduleItem", {
            method: "POST",
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: data
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
        this.setDraggedLesson(lessonElement, event)
        console.log('dragStartHandler', { ...this.draggedLesson })
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
        console.log('dropHandler', { ...this.draggedLesson })
        switch (this.draggedLesson.start_element) {
            case "list":
                if (this.dropZone.classList.contains("available-schedule-items")) {
                    // Listeden Listeye
                    return;
                } else {
                    this.draggedLesson.end_element.dataset.dayIndex = this.dropZone.cellIndex - 1 // ilk sütun saat bilgisi çıkartılıyor
                    // Listeden Tabloya bırakma işlemleri
                    this.dropListToTable()
                }
                break;
            case "table":
                if (this.dropZone.classList.contains("available-schedule-items")) {
                    //Tablodan Listeye
                    this.dropTableToList()
                } else {
                    this.draggedLesson.end_element.dataset.dayIndex = this.dropZone.cellIndex - 1 // ilk sütun saat bilgisi çıkartılıyor
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
    //todo
    async dropListToTable() {
        console.log('dropListToTable', { ...this.draggedLesson })
        if (this.owner_type !== 'classroom') {
            let classroom, hours, observer;
            if (this.examTypes.includes(this.type)) {
                // todo 
                const result = await this.selectClassroomAndObserver();
                classroom = result.classroom;
                observer = result.observer;
                this.draggedLesson.observer_id = observer.id;
                this.draggedLesson.observer_full_name = observer.full_name;
                hours = result.hours;
            } else {
                const result = await this.selectClassroomAndHours();
                classroom = result.classroom;
                hours = result.hours;
            }
            try {
                await this.checkCrash(hours, classroom);//buradaki reject kısımları hata fırlatıyor ve try yakalıyor. 
                let saveScheduleToast = new Toast();
                saveScheduleToast.prepareToast("Yükleniyor...", "Ders, programa kaydediliyor...")
                let scheduleItems = this.generateScheduleItems(hours, classroom);
                let crashResult = await this.checkCrashBackEnd(scheduleItems);
                console.log('crashResult', crashResult)
                if (crashResult) {
                    let saveResult = await this.saveScheduleItems(scheduleItems);
                    if (saveResult) {
                        saveScheduleToast.closeToast()
                        this.moveLessonListToTable(scheduleItems, classroom);
                    } else {
                        saveScheduleToast.closeToast();
                        console.error('saveResult', saveResult)
                        new Toast().prepareToast("Çakışma", "Kayıt yapılamadı!", "danger");
                    }
                }
            } catch (errorMessage) {
                console.error(errorMessage)
                new Toast().prepareToast("Hata", errorMessage, "danger");
            }
        } else {// classroom
            try {
                let { hours } = await this.selectHours();
                let classroom = { 'id': this.owner_id, 'name': this.owner_name }
                await this.checkCrash(hours, classroom);
                let saveScheduleToast = new Toast();
                saveScheduleToast.prepareToast("Yükleniyor...", "Ders, programa kaydediliyor...")
                let scheduleItems = this.generateScheduleItems(hours, classroom); // Added this line
                let saveScheduleResult = await this.saveSchedule(hours, classroom);
                if (saveScheduleResult) {
                    saveScheduleToast.closeToast()
                    this.moveLessonListToTable(scheduleItems, classroom);
                }
            } catch (errorMessage) {
                new Toast().prepareToast("Hata", errorMessage, "danger");
                console.error(errorMessage);
            }
        }

        this.resetDraggedLesson();
    }
    //todo
    async dropTableToList() {

        let deleteScheduleResult = await this.deleteSchedule(this.draggedLesson.classroom_id);

        if (deleteScheduleResult) {
            let draggedElementIdInList = "available-lesson-" + this.draggedLesson.lesson_id;
            // Always look in the ORIGINAL list
            let lessonInList = this.list.querySelector("#" + draggedElementIdInList);

            //listede taşınan dersin varlığını kontrol et
            if (lessonInList) {
                let badgeText = '';
                if (this.type == 'exam') {
                    lessonInList.dataset.size = (parseInt(lessonInList.dataset.size) + parseInt(this.draggedLesson.classroom_exam_size)).toString();
                    badgeText = lessonInList.dataset.size;
                } else {
                    lessonInList.dataset.lessonHours = (parseInt(lessonInList.dataset.lessonHours) + 1).toString();
                    badgeText = lessonInList.dataset.lessonHours;
                }
                lessonInList.querySelector(".lesson-classroom").innerText = badgeText;

                // If we were dragging a sticky element, remove it (it will be recreated by updateStickyList)
                // Actually we dragged the "ghost". logic implies we remove the dragged element from where it came from?
                // But the logic here is: we took it from TABLE and put it in LIST.
                // The `draggedLesson.HTMLElement` is the one from the Table?
                // No, in dropTableToList, we are dragging FROM table TO list.
                // So `draggedLesson.HTMLElement` is the table element? 
                // Wait, `dropTableToTable` line 1353 does `cell.appendChild`.
                // Here we do `this.draggedLesson.HTMLElement.remove()` (line 1277 in original).
                // Yes, removing the element from the Table.
                this.draggedLesson.HTMLElement.remove()
            } else {
                //eğer listede yoksa o ders listeye eklenir
                // Create new element for the ORIGINAL list
                let newElement = this.draggedLesson.HTMLElement.cloneNode(true);
                // Reset attributes
                newElement.id = draggedElementIdInList;
                newElement.classList.remove('lesson-card'); // If it had it?
                // Original logic followed below:

                let draggedElementFrameDiv = document.createElement("div");
                draggedElementFrameDiv.classList.add("frame", "col-md-4", "p-0", "ps-1");
                this.list.appendChild(draggedElementFrameDiv)

                let badgeText = '';
                if (this.type == 'exam') {
                    newElement.dataset.size = this.draggedLesson.classroom_exam_size
                    badgeText = newElement.dataset.size;
                } else {
                    newElement.dataset.lessonHours = 1;
                    badgeText = newElement.dataset.lessonHours;
                }
                newElement.querySelector(".lesson-classroom").innerText = badgeText

                delete newElement.dataset.time
                delete newElement.dataset.dayIndex
                delete newElement.dataset.classroomId
                delete newElement.dataset.classroomExamSize
                delete newElement.dataset.classroomSize
                newElement.dataset.scheduleItemId = ''; // Clear sched item id if present

                //klonlanan yeni elemente de drag start olay dinleyicisi ekleniyor.
                newElement.addEventListener('dragstart', this.dragStartHandler.bind(this));
                draggedElementFrameDiv.appendChild(newElement);

                // Remove the one from table
                this.draggedLesson.HTMLElement.remove();
            }

            this.updateStickyList(); // Refresh sticky list
        }

        this.resetDraggedLesson();
    }
    //todo
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