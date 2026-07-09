/**
 * Ders programı için ScheduleCard sınıfından türetilen alt sınıf.
 * Ders programına özgü tüm iş mantığını barındırır:
 * - Sağ tık menüsü (showContextMenu)
 * - Ders verisi çıkartma (getLessonItemData)
 * - Program öğesi oluşturma (generateScheduleItems)
 * - Çakışma renklendirme (highlightUnavailableCells)
 * - API çağrıları (save, move, delete)
 * - Atama modalı (openAssignmentModal)
 * - Client-side çakışma kontrolü (checkCrash)
 */
class LessonScheduleCard extends ScheduleCard {
    constructor(scheduleCardElement) {
        super(scheduleCardElement);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Sağ Tık Menüsü
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Ders programına özgü sağ tık menüsü.
     * Hoca, derslik, program, ders ve çocuk ders programlarını gösterir.
     */
    showContextMenu(x, y, lessonCard) {
        const oldMenu = document.getElementById('lesson-context-menu');
        if (oldMenu) oldMenu.remove();

        const menu = document.createElement('div');
        menu.id = 'lesson-context-menu';
        menu.className = 'context-menu';
        menu.style.position = 'absolute';
        menu.style.left = `${x}px`;
        menu.style.top = `${y}px`;
        menu.style.zIndex = '2000';

        let menuItems = []
        const lecturerId = lessonCard.dataset.lecturerId;
        const lecturerName = lessonCard.dataset.lecturerName || 'Hoca';
        const classroomId = lessonCard.dataset.classroomId;
        const classroomName = lessonCard.dataset.classroomName || 'Derslik';
        const programId = lessonCard.dataset.programId;
        const programName = lessonCard.dataset.programName || 'Program';
        const lessonId = lessonCard.dataset.lessonId;
        const lessonName = lessonCard.dataset.lessonName || 'Ders';

        if (classroomId) {
            menuItems.push({
                text: `${classroomName} programını göster`,
                icon: 'bi-door-open',
                onClick: () => this.showScheduleInModal('classroom', classroomId, `${classroomName} Programı`)
            });
        }
        if (lecturerId) {
            menuItems.push({
                text: `${lecturerName} programını göster`,
                icon: 'bi-person-badge',
                onClick: () => this.showScheduleInModal('user', lecturerId, `${lecturerName} Programı`)
            });
        }
        if (programId) {
            menuItems.push({
                text: `${programName} programını göster`,
                icon: 'bi-book',
                onClick: () => this.showScheduleInModal('program', programId, `${programName} Programı`)
            });
        }
        if (lessonId) {
            menuItems.push({
                text: `${lessonName} programını göster`,
                icon: 'bi-journal-text',
                onClick: () => this.showScheduleInModal('lesson', lessonId, `${lessonName} Programı`)
            });
        }

        // Çocuk derslerin programlarını ekle (data-child-lessons-ID-program-id formatını tara)
        lessonCard.getAttributeNames().forEach(attrName => {
            const match = attrName.match(/^data-child-lessons-(\d+)-program-id$/);
            if (match) {
                const childId = match[1];
                const programId = lessonCard.getAttribute(attrName);
                const programName = lessonCard.getAttribute(`data-child-lessons-${childId}-program-name`) || 'Program';

                menuItems.push({
                    text: `${programName} programını göster`,
                    icon: 'bi-book-half',
                    onClick: () => this.showScheduleInModal('program', programId, `${programName} Programı`)
                });
            }
        });
        menuItems.forEach(item => {
            const menuItem = document.createElement('div');
            menuItem.className = 'context-menu-item';
            menuItem.innerHTML = `<i class="bi ${item.icon} me-2"></i>${item.text}`;
            menuItem.onclick = item.onClick;
            menu.appendChild(menuItem);
        });

        document.body.appendChild(menu);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Veri Çıkartma
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Ders programındaki bir lesson-card elementinden veri çıkartır.
     * Ders formatında: lesson_id, lecturer_id, classroom_id bilgilerini döner.
     */
    getLessonItemData(element) {
        if (!element) return null;
        const ds = element.dataset;
        const cell = element.closest('td');

        if (!cell) {
            console.warn("Element is not inside a table cell:", element);
            return null;
        }

        return {
            id: ds.scheduleItemId,
            schedule_id: this.id,
            day_index: parseInt(cell.dataset.dayIndex),
            week_index: parseInt(this.table?.dataset?.weekIndex || 0),
            start_time: cell.dataset.startTime,
            end_time: cell.dataset.endTime,
            status: ds.status || (parseInt(ds.groupNo) > 0 ? "group" : "single"),
            data: [
                {
                    lesson_id: ds.lessonId,
                    lecturer_id: ds.lecturerId,
                    classroom_id: ds.classroomId
                }
            ],
            detail: null
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Schedule Item Oluşturma
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Ders programına özgü schedule item nesneleri oluşturur.
     * Sürüklenen dersin bilgileriyle tablo hücrelerini tarayarak yeni item'lar üretir.
     */
    generateScheduleItems(input, classroom) {
        let scheduleItems = [];
        let itemsToProcess = Array.isArray(input) ? input : [{
            hours: parseInt(input.hours || input),
            data: [{
                "lesson_id": this.draggedLesson.lesson_id,
                "lecturer_id": this.draggedLesson.lecturer_id,
                "classroom_id": classroom?.id || null
            }],
            status: (this.draggedLesson.group_no > 0 ? "group" : "single"),
            detail: input.assignments ? { assignments: input.assignments } : null
        }];

        let currentSlotOffset = 0;
        let breakTime = this.breakDuration;

        itemsToProcess.forEach(itemInfo => {
            let currentItem = null;
            let addedHours = 0;
            let hoursNeeded = itemInfo.hours;

            while (addedHours < hoursNeeded) {
                let rowIndex = this.draggedLesson.end_element.closest("tr").rowIndex + currentSlotOffset;
                if (rowIndex >= this.table.rows.length) break;

                let row = this.table.rows[rowIndex];
                let cell = Array.from(row.cells).find(c => c.dataset.dayIndex == this.draggedLesson.end_element.dataset.dayIndex);

                if (cell && cell.classList.contains("drop-zone") && !cell.querySelector('.slot-unavailable')) {
                    if (!currentItem) {
                        currentItem = {
                            'id': null,
                            'schedule_id': this.id,
                            'day_index': parseInt(this.draggedLesson.end_element.dataset.dayIndex),
                            'week_index': parseInt(this.table?.dataset?.weekIndex || 0),
                            'start_time': cell.dataset.startTime,
                            'end_time': null,
                            'status': itemInfo.status,
                            'data': itemInfo.data,
                            'detail': itemInfo.detail || null
                        };
                    }

                    let slotDuration = this.lessonHourToMinute(1);
                    if (currentItem.end_time) {
                        currentItem.end_time = this.addMinutes(currentItem.end_time, slotDuration + breakTime);
                    } else {
                        currentItem.end_time = this.addMinutes(currentItem.start_time, slotDuration);
                    }
                    addedHours++;
                    currentSlotOffset++;
                } else {
                    if (currentItem) {
                        scheduleItems.push(currentItem);
                        currentItem = null;
                    }
                    currentSlotOffset++;
                }
            }
            if (currentItem) {
                currentItem.originalElement = itemInfo.originalElement;
                scheduleItems.push(currentItem);
            }
        });
        return scheduleItems;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Çakışma Renklendirme
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Ders programına özgü müsaitlik renklendirmesi.
     * Owner tipine göre (user/program/classroom) farklı API çağrıları yapar.
     */
    async highlightUnavailableCells() {
        this.clearCells();

        let data = new FormData();
        data.append("lesson_id", this.draggedLesson.lesson_id);
        data.append("semester", this.semester);
        data.append("academic_year", this.academic_year);
        data.append("type", this.type);
        data.append("week_index", this.currentWeekIndex);

        let toast = new Toast();
        toast.prepareToast("Yükleniyor", "Program durumu kontrol ediliyor...");

        try {
            let classroomData = null, programData = null, lecturerData = null;

            switch (this.owner_type) {
                case 'user': {
                    const [classroomRes, programRes, lecturerRes] = await Promise.all([
                        fetch("/ajax/checkClassroomSchedule", { method: "POST", headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: data }),
                        fetch("/ajax/checkProgramSchedule", { method: "POST", headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: data }),
                        fetch("/ajax/checkLecturerSchedule", { method: "POST", headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: data })
                    ]);
                    classroomData = await classroomRes.json();
                    programData = await programRes.json();
                    lecturerData = await lecturerRes.json();
                    break;
                }
                case 'program': {
                    const [classroomRes, lecturerRes] = await Promise.all([
                        fetch("/ajax/checkClassroomSchedule", { method: "POST", headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: data }),
                        fetch("/ajax/checkLecturerSchedule", { method: "POST", headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: data })
                    ]);
                    classroomData = await classroomRes.json();
                    lecturerData = await lecturerRes.json();
                    break;
                }
                case 'classroom': {
                    const [programRes, lecturerRes] = await Promise.all([
                        fetch("/ajax/checkProgramSchedule", { method: "POST", headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: data }),
                        fetch("/ajax/checkLecturerSchedule", { method: "POST", headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: data })
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
                            const cell = this.table.rows[r].cells[c];
                            if (!isNaN(c) && cell) {
                                const emptySlot = cell.querySelector('.empty-slot');
                                if (emptySlot) emptySlot.classList.add(...classes);
                            }
                        });
                    }
                });
            };
            /**Program çakışması ile hoca tercihleri çakışırsa hoca tercih hücrelerinden siler */
            if (programData && programData.status !== "error" && programData.unavailableCells &&
                lecturerData && lecturerData.status !== "error" && lecturerData.preferredCells) {
                Object.keys(programData.unavailableCells).forEach(row => {
                    if (lecturerData.preferredCells[row]) {
                        Object.keys(programData.unavailableCells[row]).forEach(col => {
                            delete lecturerData.preferredCells[row][col];
                        });
                    }
                });
            }

            if (classroomData && classroomData.status !== "error") applyCells(classroomData.unavailableCells, ["slot-unavailable", "unavailable-for-classroom"]);
            if (lecturerData && lecturerData.status !== "error") {
                applyCells(lecturerData.unavailableCells, ["slot-unavailable", "unavailable-for-lecturer"]);
                applyCells(lecturerData.preferredCells, ["slot-preferred"]);
            }
            if (programData && programData.status !== "error") applyCells(programData.unavailableCells, ["slot-unavailable", "unavailable-for-program"]);

            return true;
        } catch (error) {
            toast.closeToast();
            console.error("highlightUnavailableCells hatası:", error);
            new Toast().prepareToast("Hata", "Veriler alınırken hata oluştu", "danger");
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // API Çağrıları
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Ders programı öğelerini kaydetme API çağrısı.
     * Endpoint: /ajax/saveScheduleItem
     */
    async saveScheduleItems(scheduleItems) {
        let data = new FormData();
        data.append('items', JSON.stringify(scheduleItems));

        return fetch("/ajax/saveScheduleItem", {
            method: "POST",
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: data
        })
            .then(response => response.json())
            .then((data) => {
                if (data.status === "error") {
                    console.error("saveScheduleItems API hatası:", data.msg);
                    new Toast().prepareToast("Hata", data.msg, "danger")
                    return false;
                } else {
                    return data.createdIds || true;
                }
            })
            .catch((error) => {
                console.error("saveScheduleItems sistem hatası:");
                new Toast().prepareToast("Hata", "Sistem hatası!", "danger");
                return false;
            });
    }

    /**
     * Ders programı öğelerini taşıma API çağrısı (silme + kaydetme tek transaction).
     * Endpoint: /ajax/moveScheduleItems
     */
    async moveScheduleItems(scheduleItems, deletedItems) {
        let data = new FormData();
        data.append('items', JSON.stringify(scheduleItems));
        data.append('deleted_items', JSON.stringify(deletedItems));

        return fetch("/ajax/moveScheduleItems", {
            method: "POST",
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: data
        })
            .then(response => response.json())
            .then((data) => {
                if (data.status === "error") {
                    console.error("moveScheduleItems API hatası:", data.msg);
                    new Toast().prepareToast("Hata", data.msg, "danger")
                    return false;
                } else {
                    return data.createdIds || true;
                }
            })
            .catch((error) => {
                console.error("moveScheduleItems sistem hatası:");
                new Toast().prepareToast("Hata", "Sistem hatası!", "danger");
                return false;
            });
    }

    /**
     * Ders programı öğelerini silme API çağrısı.
     * Endpoint: /ajax/deleteScheduleItems
     */
    async deleteScheduleItems(param = null) {
        let scheduleItems = [];
        if (Array.isArray(param)) {
            scheduleItems = param;
        } else if (param === null && this.selectedLessonElements.size > 0) {
            this.selectedLessonElements.forEach(el => {
                const itemData = this.getLessonItemData(el);
                if (itemData) scheduleItems.push(itemData);
            });
        } else {
            const itemData = this.getLessonItemData(this.draggedLesson.HTMLElement);
            if (itemData) {
                if (param && (typeof param === 'string' || typeof param === 'number')) itemData.classroom_id = param;
                scheduleItems.push(itemData);
            }
        }

        if (scheduleItems.length === 0) return false;

        let data = new FormData();
        data.append("items", JSON.stringify(scheduleItems));

        return fetch("/ajax/deleteScheduleItems", {
            method: "POST",
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: data,
        })
            .then(response => response.json())
            .then((data) => {
                if (data.status === "error") {
                    console.error("deleteScheduleItems API hatası:", data.msg);
                    new Toast().prepareToast("Hata", data.msg, "danger")
                    return false;
                } else {
                    return true;
                }
            })
            .catch((error) => {
                console.error("deleteScheduleItems sistem hatası:");
                new Toast().prepareToast("Hata", "Sistem hatası!", "danger");
                return false;
            });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Atama ve Çakışma
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Ders atama modalını açar
     */
    async openAssignmentModal(title = "Sınıf ve Saat Seçimi") {
        return new Promise((resolve, reject) => {
            let scheduleModal = new Modal();
            let maxHours = this.draggedLesson.lesson_hours;
            let initialHours = this.draggedLesson.lesson_hours;
            let sizeString = this.draggedLesson.size>0 ? this.draggedLesson.size+" Öğrenci için " : "";

            let modalContentHTML = `
            <form>
                <div class="form-floating mb-3">
                    <input class="form-control" id="selected_hours" type="number" 
                           value="${initialHours}" 
                           min=1 max=${maxHours}>
                    <label for="selected_hours">Süre (Saat)</label>
                </div>
                <div class="mb-3">
                    <label class="form-label">${sizeString}Derslik Seçin</label>
                    <select id="classroom" class="form-select" required></select>
                </div>
            </form>`;

            scheduleModal.prepareModal(title, modalContentHTML, true, false);
            scheduleModal.showModal();

            let selectedHoursInput = scheduleModal.body.querySelector("#selected_hours");
            let classroomSelect = scheduleModal.body.querySelector("#classroom");

            const updateLists = () => {
                this.fetchAvailableClassrooms(classroomSelect, selectedHoursInput.value);
            };

            selectedHoursInput.addEventListener("change", updateLists);
            updateLists(); // Initial fetch

            let formEl = scheduleModal.body.querySelector("form");

            scheduleModal.confirmButton.addEventListener("click", (event) => {
                event.preventDefault();
                formEl.dispatchEvent(new SubmitEvent("submit", { cancelable: true }));
            });

            scheduleModal.modal.addEventListener("hidden.bs.modal", () => {
                resolve(null);
            });

            formEl.addEventListener("submit", (event) => {
                event.preventDefault();

                if (!classroomSelect.value) {
                    console.error("Ders atama hatası: Derslik seçilmedi.");
                    new Toast().prepareToast("Dikkat", "Bir derslik seçmelisiniz.", "danger");
                    return;
                }

                const classroomName = classroomSelect.selectedOptions[0].innerText.replace(/\s*\(.*\)$/, "");
                const examSize = parseInt(classroomSelect.selectedOptions[0].dataset.examSize || '0');
                const size = parseInt(classroomSelect.selectedOptions[0].dataset.size || '0');

                const selectedClassroom = {
                    id: classroomSelect.value,
                    name: classroomName,
                    exam_size: examSize,
                    size: size
                };

                const result = {
                    classroom: selectedClassroom,
                    hours: selectedHoursInput.value
                };

                scheduleModal.closeModal();
                resolve(result);
            });
        });
    }

    /**
     * Dersler için çakışma kontrolü
     */
    checkCrash(selectedHours, classroom = null) {
        return new Promise((resolve, reject) => {
            let checkedHours = 0;
            const newLessonCode = this.draggedLesson.lesson_code;
            const newGroupNo = this.draggedLesson.group_no;

            for (let i = 0; checkedHours < selectedHours; i++) {
                let row = this.table.rows[this.draggedLesson.end_element.closest("tr").rowIndex + i];
                if (!row) {
                    console.error("checkCrash hatası: Ders saatleri programın dışına taşıyor. Row index:", this.draggedLesson.end_element.closest("tr").rowIndex + i);
                    reject("Eklenen ders saatleri programın dışına taşıyor.");
                    return;
                }

                let cell = Array.from(row.cells).find(c => c.dataset.dayIndex == this.draggedLesson.end_element.dataset.dayIndex);
                if (!cell || !cell.classList.contains("drop-zone") || cell.querySelector('.slot-unavailable')) {
                    if (cell && cell.querySelector('.slot-unavailable')) {
                        new Toast().prepareToast("Dikkat", "Uygun olmayan ders saatleri atlandı.", "info");
                    }
                    continue;
                }

                let lessons = cell.querySelectorAll('.lesson-card');
                if (lessons.length !== 0) {
                    let isGroup = Boolean(cell.querySelector('.lesson-group-container'));

                    if (!isGroup) {
                        console.error("checkCrash hatası: Hedef hücre grup dersi değil. Hücre içeriği:", cell.innerHTML);
                        reject("Bu alana ders ekleyemezsiniz.");
                        return;
                    } else {
                        let hasCrash = false;
                        lessons.forEach((lesson) => {
                            if (this.draggedLesson.group_no < 1) {
                                hasCrash = true;
                                console.error("checkCrash hatası: Eklenen ders gruplu değil.");
                                reject("Eklenen ders gruplu değil, bu alana eklenemez");
                            }
                            if (lesson.dataset.lecturerId == this.draggedLesson.lecturer_id) {
                                hasCrash = true;
                                console.error("checkCrash hatası: Hoca çakışması. Hoca ID:", this.draggedLesson.lecturer_id);
                                reject("Hoca aynı anda iki farklı derse giremez.");
                            }
                            if (lesson.dataset.lessonCode === newLessonCode) {
                                hasCrash = true;
                                console.error("checkCrash hatası: Ders kodu çakışması. Ders kodu:", newLessonCode);
                                reject("Lütfen farklı bir ders seçin.");
                            }
                            if (lesson.dataset.groupNo === newGroupNo) {
                                hasCrash = true;
                                console.error("checkCrash hatası: Grup numarası çakışması. Grup no:", newGroupNo);
                                reject("Grup numaraları aynı olamaz.");
                            }
                        });
                        if (hasCrash) return;
                    }
                }
                checkedHours++;
            }
            resolve(true);
        });
    }


}
