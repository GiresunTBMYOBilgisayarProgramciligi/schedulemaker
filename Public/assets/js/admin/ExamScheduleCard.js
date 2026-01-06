/**
 * Sınav programı için ScheduleCard sınıfından türetilen alt sınıf.
 */
class ExamScheduleCard extends ScheduleCard {
    constructor(scheduleCardElement) {
        super(scheduleCardElement);
    }

    async initialize(scheduleCardElement) {
        await super.initialize(scheduleCardElement);
        this.initWeekNavigation();
    }

    /**
     * Sınav atama modalını açar (Çoklu derslik ve gözetmen seçimi)
     */
    async openAssignmentModal(title = "Sınav Atama ve Derslik Seçimi") {
        return new Promise((resolve, reject) => {
            let scheduleModal = new Modal();
            let initialHours = 2; // Varsayılan 2 slot
            let lessonSize = parseInt(this.draggedLesson.size) || 0;

            let modalContentHTML = `
            <form id="exam-assignment-form">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input class="form-control" id="selected_hours" type="number" 
                                   value="${initialHours}" min=1>
                            <label for="selected_hours">Sınav Süresi (Slot Sayısı)</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-info py-2 px-3 mb-0">
                           <small><strong>Ders Mevcudu:</strong> <span id="lesson-size">${lessonSize}</span></small><br>
                           <small><strong>Toplam Kapasite:</strong> <span id="total-capacity">0</span></small>
                        </div>
                    </div>
                </div>
                <div id="classroom-observer-rows" class="mb-2">
                    <!-- Satırlar buraya gelecek -->
                </div>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="add-row-btn">
                        <i class="bi bi-plus-circle me-1"></i>Yeni Derslik/Gözetmen Ekle
                    </button>
                </div>
            </form>`;

            scheduleModal.prepareModal(title, modalContentHTML, true, false, "lg");
            scheduleModal.showModal();

            let rowsContainer = scheduleModal.body.querySelector("#classroom-observer-rows");
            let addRowBtn = scheduleModal.body.querySelector("#add-row-btn");
            let hoursInput = scheduleModal.body.querySelector("#selected_hours");
            let totalCapacitySpan = scheduleModal.body.querySelector("#total-capacity");

            const updateCapacity = () => {
                let total = 0;
                rowsContainer.querySelectorAll(".classroom-select").forEach(select => {
                    let option = select.selectedOptions[0];
                    if (option && option.dataset.examSize) {
                        total += parseInt(option.dataset.examSize);
                    }
                });
                totalCapacitySpan.innerText = total;
                if (total < lessonSize) {
                    totalCapacitySpan.classList.add("text-danger");
                } else {
                    totalCapacitySpan.classList.remove("text-danger");
                }
            };

            const updateOptionsVisibility = () => {
                const selectedClassrooms = Array.from(rowsContainer.querySelectorAll(".classroom-select")).map(s => s.value).filter(v => v);
                const selectedObservers = Array.from(rowsContainer.querySelectorAll(".observer-select")).map(s => s.value).filter(v => v);

                rowsContainer.querySelectorAll(".classroom-select").forEach(select => {
                    const currentValue = select.value;
                    Array.from(select.options).forEach(option => {
                        if (option.value && option.value !== currentValue && selectedClassrooms.includes(option.value)) {
                            option.style.display = 'none';
                        } else {
                            option.style.display = '';
                        }
                    });
                });

                rowsContainer.querySelectorAll(".observer-select").forEach(select => {
                    const currentValue = select.value;
                    Array.from(select.options).forEach(option => {
                        if (option.value && option.value !== currentValue && selectedObservers.includes(option.value)) {
                            option.style.display = 'none';
                        } else {
                            option.style.display = '';
                        }
                    });
                });
            };

            const addRow = async (isFirst = false) => {
                let rowCount = rowsContainer.querySelectorAll(".row").length;
                let rowId = `row-${Date.now()}-${rowCount}`;
                let rowHTML = `
                <div class="row g-2 mb-2 align-items-end" id="${rowId}">
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Derslik</label>
                        <select class="form-select classroom-select" required></select>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label small mb-1">Gözetmen</label>
                        <select class="form-select observer-select" required></select>
                    </div>
                    <div class="col-md-1 text-end">
                        ${!isFirst ? `<button type="button" class="btn btn-outline-danger btn-sm remove-row-btn"><i class="bi bi-trash"></i></button>` : ''}
                    </div>
                </div>`;

                let wrapper = document.createElement("div");
                wrapper.innerHTML = rowHTML;
                let rowElement = wrapper.firstElementChild;
                rowsContainer.appendChild(rowElement);

                let classroomSelect = rowElement.querySelector(".classroom-select");
                let observerSelect = rowElement.querySelector(".observer-select");

                await Promise.all([
                    this.fetchAvailableClassrooms(classroomSelect, hoursInput.value),
                    this.fetchAvailableObservers(observerSelect, hoursInput.value)
                ]);

                if (isFirst && this.draggedLesson.lecturer_id) {
                    observerSelect.value = this.draggedLesson.lecturer_id;
                }

                classroomSelect.addEventListener("change", () => {
                    updateCapacity();
                    updateOptionsVisibility();
                });
                observerSelect.addEventListener("change", updateOptionsVisibility);

                if (!isFirst) {
                    rowElement.querySelector(".remove-row-btn").addEventListener("click", () => {
                        rowElement.remove();
                        updateCapacity();
                        updateOptionsVisibility();
                    });
                }
                updateCapacity();
                updateOptionsVisibility();
            };

            addRowBtn.addEventListener("click", () => addRow());
            addRow(true);

            scheduleModal.confirmButton.addEventListener("click", (event) => {
                event.preventDefault();
                let selectedData = {
                    hours: hoursInput.value,
                    assignments: []
                };

                let rows = rowsContainer.querySelectorAll(".row");
                let isValid = true;
                rows.forEach(row => {
                    let classroomSelect = row.querySelector(".classroom-select");
                    let observerSelect = row.querySelector(".observer-select");
                    if (!classroomSelect.value || !observerSelect.value) {
                        isValid = false;
                    } else {
                        selectedData.assignments.push({
                            classroom_id: classroomSelect.value,
                            classroom_name: classroomSelect.selectedOptions[0].innerText.replace(/\s*\(.*\)$/, ""),
                            classroom_exam_size: classroomSelect.selectedOptions[0].dataset.examSize,
                            observer_id: observerSelect.value,
                            observer_name: observerSelect.selectedOptions[0].innerText
                        });
                    }
                });

                if (!isValid) {
                    new Toast().prepareToast("Hata", "Lütfen tüm alanları doldurun.", "warning");
                    return;
                }

                if (parseInt(totalCapacitySpan.innerText) < lessonSize) {
                    if (!confirm("Seçilen dersliklerin kapasitesi ders mevcudundan az. Devam etmek istiyor musunuz?")) {
                        return;
                    }
                }

                scheduleModal.closeModal();
                resolve(selectedData);
            });
        });
    }

    /**
     * Sınavlar için çakışma kontrolü
     */
    checkCrash(selectedHours, classroom = null) {
        return new Promise((resolve, reject) => {
            let checkedHours = 0;
            const newLessonCode = this.draggedLesson.lesson_code;
            const newClassroomId = classroom ? classroom.id : this.draggedLesson.classroom_id;
            const newLecturerId = this.draggedLesson.observer_id || this.draggedLesson.lecturer_id;

            if (!this.draggedLesson.end_element) {
                console.error("checkCrash: end_element is missing");
                reject("Hedef hücre bulunamadı.");
                return;
            }

            const targetDayIndex = parseInt(this.draggedLesson.end_element.dataset.dayIndex);
            const dropTable = this.draggedLesson.end_element.closest("table") || this.table;
            const startRowIndex = this.draggedLesson.end_element.closest("tr").rowIndex;

            for (let i = 0; checkedHours < parseInt(selectedHours); i++) {
                let row = dropTable.rows[startRowIndex + i];
                if (!row) {
                    console.error("checkCrash: Row not found at index", startRowIndex + i, "for day", targetDayIndex);
                    reject("Eklenen sınav saatleri programın dışına taşıyor.");
                    return;
                }

                // Sadece ilgili güne ait hücreyi bul
                const extractDay = (val) => {
                    if (val === undefined || val === null) return -1;
                    const match = val.toString().match(/\d+/);
                    return match ? parseInt(match[0]) : -1;
                };

                let cell = Array.from(row.cells).find(c => extractDay(c.dataset.dayIndex) === targetDayIndex);

                if (!cell) {
                    console.error("checkCrash: Cell not found for targetDayIndex", targetDayIndex, "in row", startRowIndex + i, ". Available dayIndexes:", Array.from(row.cells).map(c => c.dataset.dayIndex));
                    reject("Hücre bulunamadı (Tablo yapısı tutarsız).");
                    return;
                }

                if (!cell.classList.contains("drop-zone") || cell.querySelector('.slot-unavailable')) {
                    if (cell.style.display === 'none') {
                        console.error("checkCrash: Intersection with another rowspan exam at row", startRowIndex + i, "day", targetDayIndex, "Cell:", cell);
                        reject("Bu saatte başka bir sınav planlanmış.");
                    } else {
                        console.error("checkCrash: Slot not suitable (not drop-zone or unavailable) at row", startRowIndex + i, "day", targetDayIndex, "Cell:", cell);
                        reject("Seçilen zaman aralığında uygun olmayan saatler (Müsait değil/Kısıtlı) var.");
                    }
                    return;
                }

                let lessons = cell.querySelectorAll('.lesson-card');
                if (lessons.length !== 0) {
                    for (let existingLesson of lessons) {
                        const existCode = existingLesson.getAttribute("data-lesson-code");
                        const existClassroomId = existingLesson.getAttribute("data-classroom-id");
                        const existLecturerId = existingLesson.getAttribute("data-lecturer-id");

                        let existMatch = existCode.match(/^(.+)\.(\d+)$/);
                        let currentMatch = newLessonCode.match(/^(.+)\.(\d+)$/);
                        let existBase = existMatch ? existMatch[1] : existCode;
                        let currentBase = currentMatch ? currentMatch[1] : newLessonCode;

                        if (existBase !== currentBase) {
                            console.error("checkCrash: Base lesson mismatch", existBase, currentBase, "at row", startRowIndex + i, "day", targetDayIndex, "Existing lesson:", existingLesson);
                            reject("Sınav programında aynı saate farklı dersler konulamaz.");
                            return;
                        }

                        if (existClassroomId == newClassroomId) {
                            console.error("checkCrash: Classroom conflict", existClassroomId, "at row", startRowIndex + i, "day", targetDayIndex, "Existing lesson:", existingLesson);
                            reject("Aynı derslikte aynı saatte birden fazla sınav olamaz.");
                            return;
                        }

                        if (existLecturerId == newLecturerId) {
                            console.error("checkCrash: Lecturer conflict", existLecturerId, "at row", startRowIndex + i, "day", targetDayIndex, "Existing lesson:", existingLesson);
                            reject("Aynı gözetmen aynı saatte birden fazla sınavda görev alamaz.");
                            return;
                        }
                    }
                }
                checkedHours++;
            }
            resolve(true);
        });
    }

    /**
     * Sınav programı için gün indeksini dataset üzerinden alır (rowspan desteği için)
     */
    getLessonItemData(element) {
        let itemData = super.getLessonItemData(element);
        if (itemData) {
            const cell = element.closest('td');
            if (cell && cell.dataset.dayIndex !== undefined) {
                const match = cell.dataset.dayIndex.toString().match(/\d+/);
                itemData.day_index = match ? parseInt(match[0]) : itemData.day_index;
            }

            // Sınav programı için veri süzme (Program/Ders programında hoca ve derslik null olmalı)
            if (this.owner_type === 'program' || this.owner_type === 'lesson') {
                if (itemData.data && itemData.data[0]) {
                    itemData.data[0].lecturer_id = null;
                    itemData.data[0].classroom_id = null;
                }
            }
        }
        return itemData;
    }

    async saveScheduleItems(scheduleItems) {
        let data = new FormData();
        data.append('items', JSON.stringify(scheduleItems));

        return fetch("/ajax/saveExamScheduleItem", {
            method: "POST",
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: data
        })
            .then(response => response.json())
            .then((data) => {
                if (data.status === "error") {
                    new Toast().prepareToast("Hata", data.msg, "danger")
                    return false;
                } else {
                    return data.createdIds || true;
                }
            })
            .catch((error) => {
                console.error("Exam saveScheduleItems error:", error);
                new Toast().prepareToast("Hata", "Sistem hatası!", "danger");
                return false;
            });
    }

    /**
     * Sınavları silerken rowspan'i temizler ve gizli hücreleri geri getirir
     */
    clearTableItemsByIds(deletedIds) {
        if (!deletedIds || deletedIds.length === 0) return;
        const idSet = new Set(deletedIds.map(id => id.toString()));

        idSet.forEach(sid => {
            const selector = `td[data-schedule-item-id="${sid}"], td[data-hidden-by="${sid}"]`;
            this.card.querySelectorAll(selector).forEach(cell => {
                cell.rowSpan = 1;
                cell.style.display = '';
                cell.innerHTML = '<div class="empty-slot"></div>';

                cell.removeAttribute('data-schedule-item-id');
                cell.removeAttribute('data-end-time');
                cell.removeAttribute('data-hidden-by');

                delete cell.dataset.scheduleItemId;
                delete cell.dataset.endTime;
                delete cell.dataset.hiddenBy;
            });
        });
    }

    /**
     * Sınav programı için hücre bulma ve senkronizasyon (data-day-index kullanarak)
     */
    syncTableItems(createdItems, externalTemplates = new Map()) {
        const itemIds = createdItems.filter(item => item.schedule_id == this.id).map(item => item.id);
        this.clearTableItemsByIds(itemIds);

        const extractDay = (val) => {
            if (val === undefined || val === null) return -1;
            const match = val.toString().match(/\d+/);
            return match ? parseInt(match[0]) : -1;
        };

        createdItems.forEach(item => {
            if (item.schedule_id != this.id) return;
            const itemStartTime = item.start_time.substring(0, 5);
            const targetDay = parseInt(item.day_index);

            // Tüm tabloları tara (doğru hafta tablosunu bulmak için)
            let firstCell = null;
            let targetTable = null;
            const tables = this.card.querySelectorAll('.schedule-table');

            for (let t = 0; t < tables.length; t++) {
                const table = tables[t];
                // Eğer item'ın week_index'i tablonun dataset'indeki ile eşleşmiyorsa atla
                if (parseInt(item.week_index || 0) !== parseInt(table.dataset.weekIndex || 0)) continue;

                for (let i = 0; i < table.rows.length; i++) {
                    const row = table.rows[i];
                    for (let j = 0; j < row.cells.length; j++) {
                        const cell = row.cells[j];
                        if (extractDay(cell.dataset.dayIndex) === targetDay && cell.dataset.startTime == itemStartTime) {
                            firstCell = cell;
                            targetTable = table;
                            break;
                        }
                    }
                    if (firstCell) break;
                }
                if (firstCell) break;
            }

            if (!firstCell) return;

            // Slot sayısını hesapla
            const slotCount = this.getDurationInHours(item.start_time, item.end_time);

            // İlk hücreyi güncelle
            firstCell.rowSpan = slotCount;
            firstCell.dataset.scheduleItemId = item.id;
            firstCell.dataset.endTime = item.end_time.substring(0, 5); // Bitiş saatini set et
            firstCell.innerHTML = '';

            const itemsData = (typeof item.data === 'string') ? JSON.parse(item.data) : item.data;
            if (itemsData && Array.isArray(itemsData)) {
                itemsData.forEach(d => {
                    const lessonId = d.lesson_id?.toString();
                    let templateCard = externalTemplates.get(lessonId) || document.querySelector(`.lesson-card[data-lesson-id="${lessonId}"]`);
                    if (!templateCard && this.draggedLesson && this.draggedLesson.lesson_id == lessonId) {
                        templateCard = this.draggedLesson.HTMLElement;
                    }
                    if (templateCard) {
                        const newCard = templateCard.cloneNode(true);
                        newCard.dataset.scheduleItemId = item.id;
                        firstCell.appendChild(newCard);
                    }
                });
            }

            // Diğer hücreleri gizle (İlgili tablonun satırlarını kullan)
            let hiddenCount = 0;
            let currentRowIndex = firstCell.parentElement.rowIndex + 1;
            while (hiddenCount < slotCount - 1) {
                const nextRow = targetTable.rows[currentRowIndex];
                if (!nextRow) break;
                for (let j = 0; j < nextRow.cells.length; j++) {
                    const cell = nextRow.cells[j];
                    if (extractDay(cell.dataset.dayIndex) == targetDay) {
                        cell.style.display = 'none';
                        cell.dataset.hiddenBy = item.id;
                        hiddenCount++;
                        break;
                    }
                }
                currentRowIndex++;
            }
        });
    }

    /**
     * Sınavları tabloya taşır ve mevcudu günceller
     */
    moveLessonListToTable(scheduleItems, classroom, createdIds = []) {
        const itemIds = scheduleItems.map(i => i.id).filter(id => id);
        this.clearTableItemsByIds(itemIds);

        const extractDay = (val) => {
            if (val === undefined || val === null) return -1;
            const match = val.toString().match(/\d+/);
            return match ? parseInt(match[0]) : -1;
        };

        scheduleItems.forEach((item, index) => {
            const itemStartTime = item.start_time;
            const itemEndTime = item.end_time;
            const targetDayIndex = parseInt(item.day_index, 10);
            const slotCount = this.getDurationInHours(itemStartTime, itemEndTime);

            let currentDataId = item.id;
            if (createdIds && createdIds[index]) {
                const groupedIds = createdIds[index];
                const targetIds = groupedIds[this.owner_type] || groupedIds['program'];
                if (targetIds && targetIds.length > 0) currentDataId = targetIds[0];
            }

            const sourceElement = item.originalElement || this.draggedLesson.HTMLElement;

            // İlgili haftanın tablosunu bul
            let firstCell = null;
            let targetTable = null;
            const tables = this.card.querySelectorAll('.schedule-table');
            const itemWeekIndex = parseInt(item.week_index || 0);

            for (let t = 0; t < tables.length; t++) {
                const table = tables[t];
                if (parseInt(table.dataset.weekIndex || 0) !== itemWeekIndex) continue;

                for (let i = 0; i < table.rows.length; i++) {
                    const row = table.rows[i];
                    for (let j = 0; j < row.cells.length; j++) {
                        const cell = row.cells[j];
                        if (extractDay(cell.dataset.dayIndex) === targetDayIndex && cell.dataset.startTime == itemStartTime) {
                            firstCell = cell;
                            targetTable = table;
                            break;
                        }
                    }
                    if (firstCell) break;
                }
                if (firstCell) break;
            }

            if (firstCell && targetTable) {
                // İlk hücreyi ayarla
                firstCell.rowSpan = slotCount;
                firstCell.dataset.scheduleItemId = currentDataId;
                firstCell.dataset.endTime = itemEndTime.substring(0, 5); // Bitiş saatini set et
                firstCell.innerHTML = '';

                let lessonCard = sourceElement.cloneNode(true);
                lessonCard.dataset.scheduleItemId = currentDataId;
                lessonCard.className = lessonCard.className.replace('col-md-4', '').replace('p-0', '').replace('ps-1', '').replace('frame', '').trim();

                if (!lessonCard.classList.contains('lesson-card')) lessonCard.classList.add('lesson-card');
                lessonCard.classList.add('h-100', 'm-0', 'w-100');

                // Gözetmen listesini ekle
                if (item.detail && item.detail.assignments) {
                    const lessonMeta = lessonCard.querySelector('.lesson-meta');
                    if (lessonMeta) {
                        lessonMeta.innerHTML = '';
                        let observerList = document.createElement("div");
                        observerList.className = "lesson-observers-list w-100";
                        item.detail.assignments.forEach(assignment => {
                            let observerDiv = document.createElement("div");
                            observerDiv.className = "lesson-observer-item small d-flex justify-content-between w-100";
                            observerDiv.innerHTML = `
                                <span class="lesson-lecturer text-truncate" title="Gözetmen">${assignment.observer_name}</span>
                                <span class="lesson-classroom fw-bold ms-2" title="Derslik">${assignment.classroom_name}</span>
                            `;
                            observerList.appendChild(observerDiv);
                        });
                        lessonMeta.appendChild(observerList);
                    }
                    lessonCard.dataset.lecturerId = item.detail.assignments[0].observer_id;
                    lessonCard.dataset.observerId = item.detail.assignments[0].observer_id;
                }

                firstCell.appendChild(lessonCard);
                lessonCard.addEventListener('dragstart', this.dragStartHandler.bind(this));

                // Diğer hücreleri gizle
                let hiddenCount = 0;
                let currentRowIndex = firstCell.parentElement.rowIndex + 1;
                while (hiddenCount < slotCount - 1) {
                    const nextRow = targetTable.rows[currentRowIndex];
                    if (!nextRow) break;
                    for (let j = 0; j < nextRow.cells.length; j++) {
                        const cell = nextRow.cells[j];
                        if (extractDay(cell.dataset.dayIndex) === targetDayIndex) {
                            cell.style.display = 'none';
                            cell.dataset.hiddenBy = currentDataId;
                            hiddenCount++;
                            break;
                        }
                    }
                    currentRowIndex++;
                }
            }
        });

        // Mevcudun güncellenmesi 
        let targetElement = this.draggedLesson.HTMLElement;
        if (targetElement && targetElement.closest('.sticky-header-wrapper')) {
            targetElement = this.list.querySelector(`[data-lesson-id="${this.draggedLesson.lesson_id}"]`);
        }

        if (targetElement) {
            const currentRemaining = parseInt(this.draggedLesson.size || 0);
            const decrement = parseInt(classroom.exam_size || 0);
            const newRemaining = Math.max(0, currentRemaining - decrement);

            if (newRemaining > 0) {
                const classroomSpan = targetElement.querySelector(".lesson-classroom");
                if (classroomSpan) classroomSpan.innerText = newRemaining.toString() + " Kişi";
                targetElement.dataset.size = newRemaining.toString();
            } else {
                targetElement.closest("div.frame")?.remove();
                targetElement.remove();
            }
            this.updateStickyList();
        }
    }

    /**
     * Çoklu tablo (haftalık yapı) desteği için generateScheduleItems metodunu override eder.
     */
    generateScheduleItems(input, classroom) {
        let scheduleItems = [];
        let itemsToProcess = Array.isArray(input) ? input : [{
            hours: parseInt(input.hours || input),
            data: {
                "lesson_id": this.draggedLesson.lesson_id,
                "lecturer_id": this.draggedLesson.lecturer_id,
                "classroom_id": classroom?.id || null
            },
            status: (this.draggedLesson.group_no > 0 ? "group" : "single"),
            detail: input.assignments ? { assignments: input.assignments } : null
        }];

        const extractDay = (val) => {
            if (val === undefined || val === null) return -1;
            const match = val.toString().match(/\d+/);
            return match ? parseInt(match[0]) : -1;
        };

        const targetDayIndex = parseInt(this.draggedLesson.end_element.dataset.dayIndex);
        const targetTable = this.draggedLesson.end_element.closest('table');
        const startRowIndex = this.draggedLesson.end_element.closest('tr').rowIndex;

        itemsToProcess.forEach(itemInfo => {
            let currentItem = null;
            let addedHours = 0;
            let currentSlotOffset = 0;
            let hoursNeeded = itemInfo.hours;

            while (addedHours < hoursNeeded) {
                let rowIndex = startRowIndex + currentSlotOffset;
                if (rowIndex >= targetTable.rows.length) break;

                let row = targetTable.rows[rowIndex];
                let cell = Array.from(row.cells).find(c => extractDay(c.dataset.dayIndex) === targetDayIndex);

                if (cell && (cell.classList.contains("drop-zone") || cell === this.draggedLesson.end_element) && !cell.querySelector('.slot-unavailable')) {
                    if (!currentItem) {
                        currentItem = {
                            'id': null,
                            'schedule_id': this.id,
                            'day_index': targetDayIndex,
                            'week_index': parseInt(targetTable.dataset.weekIndex || 0),
                            'start_time': cell.dataset.startTime,
                            'end_time': null,
                            'status': itemInfo.status,
                            'data': itemInfo.data,
                            'detail': itemInfo.detail || null
                        };
                    }

                    let slotDuration = this.lessonHourToMinute(1);
                    if (currentItem.end_time) {
                        currentItem.end_time = this.addMinutes(currentItem.end_time, slotDuration + this.breakDuration);
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
                scheduleItems.push(currentItem);
            }
        });
        return scheduleItems;
    }

    initWeekNavigation() {
        this.weekCount = parseInt(this.card.dataset.weekCount) || this.card.querySelectorAll('.schedule-table').length;
        const prevBtn = this.card.querySelector('.prev-week');
        const nextBtn = this.card.querySelector('.next-week');
        const label = this.card.querySelector('.current-week-label');

        if (!prevBtn || !nextBtn) return;

        prevBtn.addEventListener('click', () => {
            if (this.currentWeekIndex > 0) {
                this.switchWeek(this.currentWeekIndex - 1);
            }
        });

        nextBtn.addEventListener('click', () => {
            if (this.currentWeekIndex < this.weekCount - 1) {
                this.switchWeek(this.currentWeekIndex + 1);
            }
        });
    }

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
                    const [classroomRes, programRes] = await Promise.all([
                        fetch("/ajax/checkClassroomSchedule", { method: "POST", headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: data }),
                        fetch("/ajax/checkProgramSchedule", { method: "POST", headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: data })
                    ]);
                    classroomData = await classroomRes.json();
                    programData = await programRes.json();
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

            const extractDay = (val) => {
                if (val === undefined || val === null) return -1;
                const match = val.toString().match(/\d+/);
                return match ? parseInt(match[0]) : -1;
            };

            const applyCells = (map, classes = []) => {
                if (!map) return;
                Object.keys(map).forEach(rowKey => {
                    const r = parseInt(rowKey, 10);
                    if (!isNaN(r) && this.table.rows[r]) {
                        Object.keys(map[rowKey]).forEach(colKey => {
                            const targetDay = parseInt(colKey, 10) - 1;
                            const cell = Array.from(this.table.rows[r].cells).find(c => extractDay(c.dataset.dayIndex) === targetDay);
                            if (cell) {
                                const emptySlot = cell.querySelector('.empty-slot');
                                if (emptySlot) emptySlot.classList.add(...classes);
                            }
                        });
                    }
                });
            };

            if (classroomData && classroomData.status !== "error") applyCells(classroomData.unavailableCells, ["slot-unavailable", "unavailable-for-classroom"]);
            if (lecturerData && lecturerData.status !== "error") {
                applyCells(lecturerData.unavailableCells, ["slot-unavailable", "unavailable-for-lecturer"]);
                applyCells(lecturerData.preferredCells, ["slot-preferred"]);
            }
            if (programData && programData.status !== "error") applyCells(programData.unavailableCells, ["slot-unavailable", "unavailable-for-program"]);

            return true;
        } catch (error) {
            toast.closeToast();
            console.error("Exam highlightUnavailableCells error:", error);
            new Toast().prepareToast("Hata", "Veriler alınırken hata oluştu", "danger");
            return false;
        }
    }

    switchWeek(weekIndex) {
        const tables = this.card.querySelectorAll('table.schedule-table');
        const prevBtn = this.card.querySelector('.prev-week');
        const nextBtn = this.card.querySelector('.next-week');
        const label = this.card.querySelector('.current-week-label');

        tables.forEach(t => {
            t.classList.add('d-none');
            t.classList.remove('active');
        });

        const targetTable = this.card.querySelector(`table.schedule-table[data-week-index="${weekIndex}"]`);
        if (targetTable) {
            targetTable.classList.remove('d-none');
            targetTable.classList.add('active');
            this.table = targetTable;
            this.currentWeekIndex = weekIndex;

            this.initStickyHeaders();
        }

        if (label) label.textContent = `${weekIndex + 1}. Hafta`;
        if (prevBtn) prevBtn.disabled = (weekIndex === 0);
        if (nextBtn) nextBtn.disabled = (weekIndex === this.weekCount - 1);

        window.dispatchEvent(new Event('scroll'));
    }
}
