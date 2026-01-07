/**
 * Ders programı için ScheduleCard sınıfından türetilen alt sınıf.
 */
class LessonScheduleCard extends ScheduleCard {
    constructor(scheduleCardElement) {
        super(scheduleCardElement);
    }

    /**
     * Ders atama modalını açar
     */
    async openAssignmentModal(title = "Sınıf ve Saat Seçimi") {
        return new Promise((resolve, reject) => {
            let scheduleModal = new Modal();
            let maxHours = this.draggedLesson.lesson_hours;
            let initialHours = this.draggedLesson.lesson_hours;

            let modalContentHTML = `
            <form>
                <div class="form-floating mb-3">
                    <input class="form-control" id="selected_hours" type="number" 
                           value="${initialHours}" 
                           min=1 max=${maxHours}>
                    <label for="selected_hours">Süre (Saat)</label>
                </div>
                <div class="mb-3">
                    <label class="form-label">Derslik Seçin</label>
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

                let cell = row.cells[this.draggedLesson.end_element.cellIndex];
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

    /**
     * Dersleri tabloya taşır ve süreleri günceller
     */
    moveLessonListToTable(scheduleItems, classroom, createdIds = []) {
        super.moveLessonListToTable(scheduleItems, classroom, createdIds);

        // Derslere özgü kalan saat güncelleme mantığı
        let targetElement = this.draggedLesson.HTMLElement;
        if (targetElement && targetElement.closest('.sticky-header-wrapper')) {
            targetElement = this.list.querySelector(`[data-lesson-id="${this.draggedLesson.lesson_id}"]`);
        }

        if (targetElement) {
            let addedHours = scheduleItems.reduce((acc, item) => acc + this.getDurationInHours(item.start_time, item.end_time), 0);
            if (this.draggedLesson.lesson_hours > addedHours) {
                let newHours = this.draggedLesson.lesson_hours - addedHours;
                targetElement.querySelector(".lesson-classroom").innerHTML = newHours.toString() + " Saat";
                targetElement.dataset.lessonHours = newHours;
            } else {
                targetElement.closest("div.frame")?.remove();
                targetElement.remove();
            }
            this.updateStickyList();
        }
    }
}
