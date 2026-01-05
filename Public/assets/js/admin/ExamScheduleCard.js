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

            for (let i = 0; checkedHours < selectedHours; i++) {
                let row = this.table.rows[this.draggedLesson.end_element.closest("tr").rowIndex + i];
                if (!row) {
                    reject("Eklenen sınav saatleri programın dışına taşıyor.");
                    return;
                }

                let cell = row.cells[this.draggedLesson.end_element.cellIndex];
                if (!cell || !cell.classList.contains("drop-zone") || cell.querySelector('.slot-unavailable')) {
                    continue;
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
                            reject("Sınav programında aynı saate farklı dersler konulamaz.");
                            return;
                        }

                        if (existClassroomId == newClassroomId) {
                            reject("Aynı derslikte aynı saatte birden fazla sınav olamaz.");
                            return;
                        }

                        if (existLecturerId == newLecturerId) {
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
     * Sınavları tabloya taşır ve mevcudu günceller
     */
    moveLessonListToTable(scheduleItems, classroom, createdIds = []) {
        super.moveLessonListToTable(scheduleItems, classroom, createdIds);

        // Sınavlara özgü kart içeriği (gözetmen listesi) ve kalan mevcut güncelleme mantığı
        scheduleItems.forEach(item => {
            if (item.detail && item.detail.assignments) {
                // Her hücredeki kartı bul ve gözetmen listesini güncelle
                const dayIndex = parseInt(item.day_index, 10);
                const colIndex = dayIndex + 1;
                const itemStartTime = item.start_time;
                const itemEndTime = item.end_time;

                for (let i = 0; i < this.table.rows.length; i++) {
                    const row = this.table.rows[i];
                    const cell = row.cells[colIndex];
                    if (!cell) continue;

                    let cellStartTime = cell.dataset.startTime || (row.cells[0]?.innerText.trim().substring(0, 5));
                    if (cellStartTime && cellStartTime >= itemStartTime && cellStartTime < itemEndTime) {
                        const lessonCard = cell.querySelector('.lesson-card');
                        if (lessonCard) {
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
                            // İlk gözetmeni lecturer_id olarak atayalım (backward compatibility için)
                            lessonCard.dataset.lecturerId = item.detail.assignments[0].observer_id;
                            lessonCard.dataset.observerId = item.detail.assignments[0].observer_id;
                        }
                    }
                }
            }
        });

        let targetElement = this.draggedLesson.HTMLElement;
        if (targetElement.closest('.sticky-header-wrapper')) {
            targetElement = this.list.querySelector(`[data-lesson-id="${this.draggedLesson.lesson_id}"]`);
        }

        if (targetElement) {
            const currentRemaining = parseInt(this.draggedLesson.size || 0);
            const decrement = parseInt(classroom.exam_size || 0);
            const newRemaining = Math.max(0, currentRemaining - decrement);

            if (newRemaining > 0) {
                targetElement.querySelector(".lesson-classroom").innerText = newRemaining.toString();
                targetElement.dataset.size = newRemaining.toString();
            } else {
                targetElement.closest("div.frame")?.remove();
                targetElement.remove();
            }
            this.updateStickyList();
        }
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
