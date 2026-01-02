let lessonDrop = new Event("lessonDrop");
/**
 * Ders Programı düzenleme sayfasında Programı temsil eden temel sınıf.
 */
class ScheduleCard {

    constructor(scheduleCardElement = null) {
        this.card = null;
        this.id = null;
        this.table = null;
        this.list = null;
        this.academic_year = null;
        this.semester = null;
        this.semester_no = null;
        this.owner_type = null;
        this.owner_id = null;
        this.type = null;
        this.examTypes = ['midterm-exam', 'final-exam', 'makeup-exam']
        this.draggedLesson = {
            'start_element': null,
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
        this.isDragging = false;
        this.dropZone = null;
        this.removeLessonDropZone = null;
        this.selectedLessonElements = new Set();
        this.selectedScheduleItemIds = new Set();
        this.isProcessing = false;
        this.currentWeekIndex = 0;
        this.weekCount = 1;
        this.owner_name = null;

        if (scheduleCardElement) {
            this.initialize(scheduleCardElement)
        } else {
            new Toast().prepareToast("Hata", "Ders programı nesnesi tanımlanamadı", "danger");
        }
    }

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

        const dragableElements = this.card.querySelectorAll('[draggable="true"]');
        const dropZones = this.card.querySelectorAll('.drop-zone');

        dragableElements.forEach(element => {
            element.addEventListener('dragstart', this.dragStartHandler.bind(this));
        });

        dropZones.forEach(element => {
            element.addEventListener("drop", this.dropHandler.bind(this, element));
            element.addEventListener("dragover", this.dragOverHandler.bind(this))
        });

        this.removeLessonDropZone = this.card.querySelector(".available-schedule-items.drop-zone")

        this.initStickyHeaders();
        this.initBulkSelection();
        this.initContextMenu();
        this.initWeekNavigation();
    }

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
            day_index: parseInt(cell.cellIndex - 1),
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

    initBulkSelection() {
        this.card.addEventListener('change', (event) => {
            if (event.target.classList.contains('lesson-bulk-checkbox')) {
                const checkbox = event.target;
                const lessonCard = checkbox.closest('.lesson-card');
                this.updateSelectionState(lessonCard, checkbox.checked);
            }
        });

        this.card.addEventListener('click', (event) => {
            const lessonCard = event.target.closest('.lesson-card');
            if (!lessonCard) return;

            if (event.target.tagName === 'A' || event.target.classList.contains('lesson-bulk-checkbox')) {
                return;
            }

            const checkbox = lessonCard.querySelector('.lesson-bulk-checkbox');
            if (checkbox) {
                checkbox.checked = !checkbox.checked;
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        this.card.addEventListener('dblclick', (event) => {
            const lessonCard = event.target.closest('.lesson-card');
            if (!lessonCard) return;

            const lessonId = lessonCard.dataset.lessonId;
            if (!lessonId) return;

            const sameLessons = this.card.querySelectorAll(`.lesson-card[data-lesson-id="${lessonId}"]`);
            sameLessons.forEach(card => {
                const cb = card.querySelector('.lesson-bulk-checkbox');
                if (cb && !cb.checked) {
                    cb.checked = true;
                    cb.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });

            window.getSelection().removeAllRanges();
        });
    }

    updateSelectionState(lessonCard, isSelected) {
        const scheduleItemId = lessonCard.dataset.scheduleItemId;
        if (isSelected) {
            lessonCard.classList.add('selected-lesson');
            this.selectedLessonElements.add(lessonCard);
            this.selectedScheduleItemIds.add(scheduleItemId);
        } else {
            lessonCard.classList.remove('selected-lesson');
            this.selectedLessonElements.delete(lessonCard);
            this.selectedScheduleItemIds.delete(scheduleItemId);
        }
    }

    clearSelection() {
        this.selectedLessonElements.forEach(el => {
            el.classList.remove('selected-lesson');
            const cb = el.querySelector('.lesson-bulk-checkbox');
            if (cb) cb.checked = false;
        });
        this.selectedLessonElements.clear();
        this.selectedScheduleItemIds.clear();
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

    initContextMenu() {
        this.card.addEventListener('contextmenu', (event) => {
            const lessonCard = event.target.closest('.lesson-card');
            if (!lessonCard || lessonCard.classList.contains('dummy')) return;

            event.preventDefault();
            this.showContextMenu(event.pageX, event.pageY, lessonCard);
        });

        document.addEventListener('click', () => {
            const menu = document.getElementById('lesson-context-menu');
            if (menu) menu.remove();
        });
    }

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
        const classroomId = lessonCard.dataset.classroomId;
        const programId = lessonCard.dataset.programId;

        if (classroomId) {
            menuItems.push({
                text: 'Derslik programını göster',
                icon: 'bi-door-open',
                onClick: () => this.showScheduleInModal('classroom', classroomId, 'Derslik Programı')
            });
        }
        if (lecturerId) {
            menuItems.push({
                text: 'Hoca programını göster',
                icon: 'bi-person-badge',
                onClick: () => this.showScheduleInModal('user', lecturerId, 'Hoca Programı')
            });
        }
        if (programId) {
            menuItems.push({
                text: 'Program programını göster',
                icon: 'bi-book',
                onClick: () => this.showScheduleInModal('program', programId, 'Program Programı')
            });
        }
        menuItems.forEach(item => {
            const menuItem = document.createElement('div');
            menuItem.className = 'context-menu-item';
            menuItem.innerHTML = `<i class="bi ${item.icon} me-2"></i>${item.text}`;
            menuItem.onclick = item.onClick;
            menu.appendChild(menuItem);
        });

        document.body.appendChild(menu);
    }

    async showScheduleInModal(ownerType, ownerId, title) {
        if (!ownerId) {
            new Toast().prepareToast("Hata", "ID bilgisi eksik", "danger");
            return;
        }

        const modal = new Modal();
        modal.initializeModal("xl");
        modal.prepareModal(title, '<div class="text-center"><div class="spinner-border" role="status"></div></div>', false, true, "xl");
        modal.showModal();

        const data = new FormData();
        data.append('owner_type', ownerType);
        data.append('owner_id', ownerId);
        data.append('semester', this.semester);
        data.append('academic_year', this.academic_year);
        data.append('type', this.type);
        data.append('only_table', 'true');

        try {
            const response = await fetch('/ajax/getScheduleHTML', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: data
            });
            const result = await response.json();

            if (result.status === 'success') {
                modal.body.innerHTML = result.HTML;
            } else {
                modal.body.innerHTML = `<div class="alert alert-danger">${result.msg || "Program yüklenemedi"}</div>`;
            }
        } catch (error) {
            console.error(error);
            modal.body.innerHTML = '<div class="alert alert-danger">Sistem hatası oluştu.</div>';
        }
    }

    initStickyHeaders() {
        const availableList = this.card.querySelector('.available-schedule-items');
        const table = this.card.querySelector('.schedule-table');
        const thead = table.querySelector('thead');

        if (!availableList || !table || !thead) return;

        this.stickyWrapper = document.createElement('div');
        this.stickyWrapper.className = 'sticky-header-wrapper';
        this.stickyWrapper.style.position = 'fixed';

        const navbar = document.querySelector('.app-header') || document.querySelector('.main-header') || document.querySelector('nav.navbar');
        const isNavbarFixed = navbar && (getComputedStyle(navbar).position === 'fixed' || document.body.classList.contains('layout-navbar-fixed'));
        const topOffset = isNavbarFixed ? navbar.offsetHeight : 0;

        this.stickyWrapper.style.top = topOffset + 'px';
        this.stickyWrapper.style.zIndex = '1039';
        this.stickyWrapper.style.display = 'none';
        this.stickyWrapper.style.width = this.card.offsetWidth + 'px';
        this.stickyWrapper.style.backgroundColor = '#fff';
        this.stickyWrapper.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';

        this.updateStickyList();

        const tableClone = document.createElement('table');
        tableClone.className = table.className;
        tableClone.style.marginBottom = '0';

        const theadClone = thead.cloneNode(true);
        tableClone.appendChild(theadClone);

        const tableContainer = document.createElement('div');
        tableContainer.className = 'schedule-table-container mb-0';
        tableContainer.style.overflow = 'hidden';
        tableContainer.appendChild(tableClone);

        this.stickyWrapper.appendChild(tableContainer);
        this.card.appendChild(this.stickyWrapper);

        const syncWidths = () => {
            const originalThs = thead.querySelectorAll('th');
            const cloneThs = theadClone.querySelectorAll('th');

            originalThs.forEach((th, index) => {
                if (cloneThs[index]) {
                    cloneThs[index].style.width = th.offsetWidth + 'px';
                    cloneThs[index].style.minWidth = th.offsetWidth + 'px';
                    cloneThs[index].style.boxSizing = 'border-box';
                }
            });

            this.stickyWrapper.style.width = this.card.offsetWidth + 'px';
            tableContainer.scrollLeft = this.table.parentElement.scrollLeft;
        };

        window.addEventListener('scroll', () => {
            const cardRect = this.card.getBoundingClientRect();
            const navbar = document.querySelector('.app-header') || document.querySelector('.main-header') || document.querySelector('nav.navbar');
            const isNavbarFixed = navbar && (getComputedStyle(navbar).position === 'fixed' || document.body.classList.contains('layout-navbar-fixed'));
            const offset = isNavbarFixed ? navbar.offsetHeight : 0;

            if (cardRect.top < offset && cardRect.bottom > offset + availableList.offsetHeight + thead.offsetHeight) {
                if (this.stickyWrapper.style.display !== 'block') {
                    this.updateStickyList();
                }

                this.stickyWrapper.style.display = 'block';
                this.stickyWrapper.style.left = cardRect.left + 'px';
                this.stickyWrapper.style.top = offset + 'px';

                availableList.style.visibility = 'hidden';
                thead.style.visibility = 'hidden';

                syncWidths();
            } else {
                this.stickyWrapper.style.display = 'none';
                availableList.style.visibility = 'visible';
                thead.style.visibility = 'visible';
            }
        });

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
        const availableList = this.list;
        if (!availableList) return;

        const oldList = this.stickyWrapper.querySelector('.sticky-list-clone');
        if (oldList) oldList.remove();

        const listClone = availableList.cloneNode(true);
        listClone.id = '';
        listClone.classList.add('sticky-list-clone');
        listClone.style.visibility = 'visible';

        listClone.querySelectorAll('[id]').forEach(el => el.removeAttribute('id'));

        const dragableElements = listClone.querySelectorAll('[draggable="true"]');
        dragableElements.forEach(element => {
            element.addEventListener('dragstart', this.dragStartHandler.bind(this));
        });

        if (listClone.classList.contains('drop-zone')) {
            listClone.addEventListener("drop", this.dropHandler.bind(this, listClone));
            listClone.addEventListener("dragover", this.dragOverHandler.bind(this));
        }

        const childDropZones = listClone.querySelectorAll('.drop-zone');
        childDropZones.forEach(element => {
            element.addEventListener("drop", this.dropHandler.bind(this, element));
            element.addEventListener("dragover", this.dragOverHandler.bind(this));
        });

        this.stickyWrapper.prepend(listClone);
    }

    async getSchedule() {
        if (!this.id) return null;

        let data = new FormData();
        data.append("id", this.id);

        return fetch("/ajax/getSchedule", {
            method: "POST",
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: data,
        })
            .then(response => response.json())
            .then((data) => {
                if (data && data.status === "error") {
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
        Object.keys(this.draggedLesson).forEach(key => {
            this.draggedLesson[key] = null;
        });
    }

    getDatasetValue(setObject, getObject) {
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

            if (classroomData && classroomData.status !== "error") applyCells(classroomData.unavailableCells, ["slot-unavailable", "unavailable-for-classroom"]);
            if (lecturerData && lecturerData.status !== "error") {
                applyCells(lecturerData.unavailableCells, ["slot-unavailable", "unavailable-for-lecturer"]);
                applyCells(lecturerData.preferredCells, ["slot-preferred"]);
            }
            if (programData && programData.status !== "error") applyCells(programData.unavailableCells, ["slot-unavailable", "unavailable-for-program"]);

            return true;
        } catch (error) {
            toast.closeToast();
            new Toast().prepareToast("Hata", "Veriler alınırken hata oluştu", "danger");
            return false;
        }
    }

    clearCells() {
        for (let i = 0; i < this.table.rows.length; i++) {
            for (let j = 0; j < this.table.rows[i].cells.length; j++) {
                const emptySlot = this.table.rows[i].cells[j].querySelector('.empty-slot');
                if (emptySlot) {
                    emptySlot.classList.remove(
                        "slot-unavailable", "slot-preferred", "unavailable-for-lecturer",
                        "unavailable-for-classroom", "unavailable-for-program"
                    );
                }
            }
        }
    }

    async fetchOptions(url, targetSelect, data, defaultText = "Seçiniz") {
        targetSelect.innerHTML = `<option value="">${defaultText}</option>`;
        let spinner = new Spinner();
        spinner.showSpinner(targetSelect.querySelector("option"));

        try {
            const response = await fetch(url, { method: "POST", headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: data });
            const resData = await response.json();
            spinner.removeSpinner();
            targetSelect.innerHTML = `<option value="">${defaultText}</option>`;

            if (resData.status === "error") {
                new Toast().prepareToast("Hata", resData.msg || "Liste alınırken hata oluştu", "danger");
                return;
            }

            const items = resData.classrooms || resData.observers || [];
            items.forEach(item => {
                let option = document.createElement("option");
                option.value = item.id;
                if (item.class_size !== undefined) {
                    const size = this.examTypes.includes(this.type) ? (item.exam_size || 0) : item.class_size;
                    option.innerText = `${item.name} (${size})`;
                    option.dataset.size = item.class_size;
                    option.dataset.examSize = item.exam_size;
                } else {
                    option.innerText = `${item.title} ${item.name} ${item.last_name}`;
                }
                targetSelect.appendChild(option);
            });

        } catch (error) {
            new Toast().prepareToast("Hata", "Liste alınırken hata oluştu", "danger");
        }
    }

    async fetchAvailableClassrooms(classroomSelect, hours) {
        let data = new FormData();
        data.append("schedule_id", this.id);
        data.append("hours", hours);
        data.append("startTime", this.draggedLesson.end_element.dataset.startTime);
        data.append("day_index", this.draggedLesson.end_element.dataset.dayIndex);
        data.append("lesson_id", this.draggedLesson.lesson_id);
        data.append("week_index", this.currentWeekIndex);
        await this.fetchOptions("/ajax/getAvailableClassroomForSchedule", classroomSelect, data, "Bir Sınıf Seçin");
    }

    async fetchAvailableObservers(observerSelect, hours) {
        let data = new FormData();
        data.append("hours", hours);
        data.append("startTime", this.draggedLesson.end_element.dataset.startTime);
        data.append("day_index", this.draggedLesson.end_element.dataset.dayIndex);
        data.append("week_index", this.currentWeekIndex);
        data.append("type", this.type);
        data.append("semester", this.semester);
        data.append("academic_year", this.academic_year);
        await this.fetchOptions("/ajax/getAvailableObserversForSchedule", observerSelect, data, "Bir Gözetmen Seçin");
    }

    /**
     * Alt sınıflar tarafından geçersiz kılınmalıdır
     */
    async openAssignmentModal(title = "Seçim Yapın") {
        console.warn("openAssignmentModal should be implemented by subclass.");
        return null;
    }

    selectHours() {
        return new Promise((resolve) => {
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
     * Alt sınıflar tarafından geçersiz kılınmalıdır
     */
    checkCrash(selectedHours, classroom = null) {
        return Promise.resolve(true);
    }

    lessonHourToMinute(hours) {
        return hours * this.duration;
    }

    addMinutes(timeStr, minutes) {
        let [hours, mins] = timeStr.split(":").map(Number);
        let date = new Date();
        date.setHours(hours, mins + minutes, 0);
        return date.toTimeString().substring(0, 5);
    }

    timeToMinutes(timeStr) {
        if (!timeStr) return 0;
        const [h, m] = timeStr.split(':').map(Number);
        return (h * 60) + m;
    }

    minutesToTime(minutes) {
        const h = Math.floor(minutes / 60);
        const m = minutes % 60;
        return `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}`;
    }

    getDurationInHours(startTime, endTime) {
        const start = this.timeToMinutes(startTime);
        const end = this.timeToMinutes(endTime);
        const diff = end - start;
        return Math.ceil(diff / (this.lessonHourToMinute(1) + this.breakDuration));
    }

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
                let cell = row.cells[this.draggedLesson.end_element.cellIndex];

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

    moveLessonListToTable(scheduleItems, classroom, createdIds = []) {
        let idIndex = 0;
        scheduleItems.forEach(item => {
            let itemStartTime = item.start_time;
            let itemEndTime = item.end_time;
            let targetDayIndex = parseInt(item.day_index, 10);
            let colIndex = targetDayIndex + 1;

            let currentDataId = item.id;
            if (createdIds && createdIds[idIndex]) {
                const groupedIds = createdIds[idIndex];
                const targetIds = groupedIds[this.owner_type] || groupedIds['program'];
                if (targetIds && targetIds.length > 0) currentDataId = targetIds[0];
            }
            idIndex++;

            const sourceElement = item.originalElement || this.draggedLesson.HTMLElement;

            for (let i = 0; i < this.table.rows.length; i++) {
                let row = this.table.rows[i];
                let cell = row.cells[colIndex];
                if (!cell) continue;

                let cellStartTime = cell.dataset.startTime || (row.cells[0]?.innerText.trim().substring(0, 5));

                if (cellStartTime && cellStartTime >= itemStartTime && cellStartTime < itemEndTime) {
                    let emptySlot = cell.querySelector('.empty-slot');
                    if (emptySlot) emptySlot.remove();

                    cell.dataset.scheduleItemId = currentDataId;

                    let container = (item.status === 'group') ? (cell.querySelector('.lesson-group-container') || document.createElement('div')) : cell;
                    if (item.status === 'group' && !cell.querySelector('.lesson-group-container')) {
                        container.classList.add('lesson-group-container');
                        cell.appendChild(container);
                    }

                    let lessonCard = sourceElement.cloneNode(true);
                    lessonCard.classList.remove('selected-lesson');
                    const bulkCb = lessonCard.querySelector('.lesson-bulk-checkbox');
                    if (bulkCb) bulkCb.checked = false;

                    lessonCard.className = lessonCard.className.replace('col-md-4', '').replace('p-0', '').replace('ps-1', '').replace('frame', '').trim();
                    if (!lessonCard.classList.contains('lesson-card')) lessonCard.classList.add('lesson-card');

                    if (!lessonCard.querySelector('.lesson-bulk-checkbox')) {
                        const bulkCheckbox = document.createElement('input');
                        bulkCheckbox.type = 'checkbox';
                        bulkCheckbox.className = 'lesson-bulk-checkbox';
                        bulkCheckbox.title = 'Toplu işlem için seç';
                        lessonCard.prepend(bulkCheckbox);
                    }

                    lessonCard.setAttribute('draggable', 'true');
                    lessonCard.dataset.scheduleItemId = currentDataId;
                    lessonCard.dataset.classroomId = classroom.id;
                    lessonCard.dataset.classroomSize = classroom.size;
                    lessonCard.dataset.classroomExamSize = classroom.exam_size;

                    let classroomSpan = lessonCard.querySelector('.lesson-classroom');
                    if (classroomSpan) classroomSpan.innerHTML = `${classroom.name}`;

                    let lessonNameSpan = lessonCard.querySelector('.lesson-name');
                    if (lessonNameSpan) new bootstrap.Tooltip(lessonNameSpan);

                    lessonCard.addEventListener('dragstart', this.dragStartHandler.bind(this));
                    lessonCard.id = lessonCard.id.replace("available", "scheduleTable") + '-' + this.table.querySelectorAll('[id^=\"' + lessonCard.id + '\"]').length;

                    container.appendChild(lessonCard);
                }
            }
        });
    }

    async checkCrashBackEnd(scheduleItems) {
        let data = new FormData();
        data.append("items", JSON.stringify(scheduleItems));

        return fetch("/ajax/checkScheduleCrash", {
            method: "POST",
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: data,
        })
            .then(response => response.json())
            .then((data) => {
                if (data && data.status === "error") {
                    new Toast().prepareToast("Hata", data.msg, "danger")
                    return false;
                } else {
                    return true;
                }
            })
            .catch((error) => {
                new Toast().prepareToast("Hata", "Sistem hatası!", "danger");
                return false;
            });
    }

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
                    new Toast().prepareToast("Hata", data.msg, "danger")
                    return false;
                } else {
                    return data.createdIds || true;
                }
            })
            .catch((error) => {
                new Toast().prepareToast("Hata", "Sistem hatası!", "danger");
                return false;
            });
    }

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
                    new Toast().prepareToast("Hata", data.msg, "danger")
                    return false;
                } else {
                    const templates = new Map();
                    if (data.deletedIds) {
                        data.deletedIds.forEach(id => {
                            const cards = this.table.querySelectorAll(`.lesson-card[data-schedule-item-id="${id}"]`);
                            cards.forEach(card => {
                                const lId = card.dataset.lessonId;
                                if (lId && !templates.has(lId)) {
                                    templates.set(lId, card.cloneNode(true));
                                }
                            });
                        });
                    }

                    if (data.deletedIds) this.clearTableItemsByIds(data.deletedIds);
                    if (data.createdItems) this.syncTableItems(data.createdItems, templates);
                    return true;
                }
            })
            .catch((error) => {
                new Toast().prepareToast("Hata", "Sistem hatası!", "danger");
                return false;
            });
    }

    dragStartHandler(event) {
        this.isDragging = true;
        const lessonElement = event.target.closest(".lesson-card");
        if (!lessonElement) return;

        this.setDraggedLesson(lessonElement, event);

        if (this.selectedLessonElements.size > 0 && this.selectedLessonElements.has(lessonElement)) {
            event.dataTransfer.setData("text/plain", JSON.stringify({ type: 'bulk', ids: Array.from(this.selectedScheduleItemIds) }));
        } else {
            this.clearSelection();
            event.dataTransfer.setData("text/plain", JSON.stringify({ type: 'single', id: this.draggedLesson.schedule_item_id }));
        }

        event.dataTransfer.effectAllowed = "move";
        event.dataTransfer.dropEffect = "move";

        if (this.draggedLesson.start_element === "table") {
            this.removeLessonDropZone.style.border = "2px dashed"
            new bootstrap.Tooltip(this.removeLessonDropZone).show();
        }
        this.highlightUnavailableCells().then(() => {
            if (!this.isDragging) this.clearCells();
        });
    }

    async dropHandler(element, event) {
        if (this.isProcessing) return;
        this.isProcessing = true;
        try {
            event.preventDefault();
            this.isDragging = false;
            this.clearCells();
            if (this.removeLessonDropZone) {
                this.removeLessonDropZone.style.border = ""
                const tooltip = bootstrap.Tooltip.getInstance(this.removeLessonDropZone);
                if (tooltip) tooltip.hide()
            }

            this.dropZone = element;
            const rawData = event.dataTransfer.getData("text/plain");
            if (!rawData) {
                this.resetDraggedLesson();
                return;
            }
            let dragData = JSON.parse(rawData);
            const isToList = this.dropZone.classList.contains("available-schedule-items");

            if (dragData.type === 'bulk') {
                if (isToList) {
                    if (await this.deleteScheduleItems()) {
                        const elementsToProcess = Array.from(this.selectedLessonElements).filter(el => dragData.ids.includes(el.dataset.scheduleItemId));
                        for (const el of elementsToProcess) {
                            this.draggedLesson.HTMLElement = el;
                            this.draggedLesson.schedule_item_id = el.dataset.scheduleItemId;
                            this.getDatasetValue(this.draggedLesson, el);
                            await this.dropTableToList(true);
                        }
                        this.clearSelection();
                    }
                } else {
                    this.draggedLesson.end_element = this.dropZone;
                    this.draggedLesson.end_element.dataset.dayIndex = this.dropZone.cellIndex - 1;
                    await this.dropTableToTable(true);
                    this.clearSelection();
                }
            } else {
                this.draggedLesson.end_element = this.dropZone;
                if (this.draggedLesson.start_element === "list") {
                    if (!isToList) {
                        this.draggedLesson.end_element.dataset.dayIndex = this.dropZone.cellIndex - 1;
                        await this.dropListToTable();
                    }
                } else {
                    if (isToList) await this.dropTableToList();
                    else {
                        this.draggedLesson.end_element.dataset.dayIndex = this.dropZone.cellIndex - 1;
                        await this.dropTableToTable();
                    }
                }
            }
            this.clearSelection();
            document.dispatchEvent(lessonDrop);
        } catch (e) {
            console.error(e);
        } finally {
            this.isProcessing = false;
        }
    }

    clearTableItemsByIds(deletedIds) {
        if (!deletedIds || deletedIds.length === 0) return;
        const idSet = new Set(deletedIds.map(id => id.toString()));

        // Tablodaki tüm ilgili kartları bul ve sil
        document.querySelectorAll('.lesson-card').forEach(card => {
            if (card.dataset.scheduleItemId && idSet.has(card.dataset.scheduleItemId.toString())) {
                const cell = card.closest('td');
                card.remove();

                // Eğer hücre boş kaldıysa boş slot ekle ve ID'yi temizle
                if (cell && !cell.querySelector('.lesson-card')) {
                    cell.innerHTML = '<div class="empty-slot"></div>';
                    delete cell.dataset.scheduleItemId;
                }
            }
        });
    }

    syncTableItems(createdItems, externalTemplates = new Map()) {
        createdItems.forEach(item => {
            if (item.schedule_id != this.id) return;
            const itemStartTime = item.start_time.substring(0, 5);
            const itemEndTime = item.end_time.substring(0, 5);
            const targetDay = parseInt(item.day_index);

            // Rowspan ve tablo yapısından bağımsız hücre bulma
            for (let i = 0; i < this.table.rows.length; i++) {
                const row = this.table.rows[i];
                const timeCell = row.cells[0];
                const rowTime = timeCell?.innerText.trim().substring(0, 5);

                if (rowTime && rowTime >= itemStartTime && rowTime < itemEndTime) {
                    // Bu satırdaki doğru güne ait hücreyi bul (rowspan hesaba katarak)
                    let currentDay = -1;
                    let cell = null;
                    for (let j = 1; j < row.cells.length; j++) {
                        // Not: Basit tabloda j=day+1'dir ama rowspan varsa kayar. 
                        // Biz şimdilik basitleştirilmiş hücre içi veri kontrolü yapıyoruz.
                        // Eğer hücre bulunamazsa veya yanlışsa dataset üzerinden doğrula.
                        const tempCell = row.cells[j];
                        const cellDay = tempCell.cellIndex - 1; // Genelde bu doğrudur
                        if (cellDay === targetDay) {
                            cell = tempCell;
                            break;
                        }
                    }

                    if (!cell) continue;

                    cell.dataset.scheduleItemId = item.id;
                    const itemsData = (typeof item.data === 'string') ? JSON.parse(item.data) : item.data;
                    const existingCards = cell.querySelectorAll('.lesson-card');
                    const existingLessonIds = new Set(Array.from(existingCards).map(c => c.dataset.lessonId?.toString()));

                    if (item.status === 'preferred' || item.status === 'unavailable') {
                        cell.innerHTML = '<div class="empty-slot"></div>';
                        return;
                    }
                    if (cell.querySelector('.empty-slot')) cell.querySelector('.empty-slot').remove();

                    if (itemsData && Array.isArray(itemsData)) {
                        itemsData.forEach(d => {
                            const lessonId = d.lesson_id?.toString();
                            if (!lessonId) return;

                            if (existingLessonIds.has(lessonId)) {
                                // Kart var, sadece ID'sini güncelle
                                const card = cell.querySelector(`.lesson-card[data-lesson-id="${lessonId}"]`);
                                if (card) card.dataset.scheduleItemId = item.id;
                            } else {
                                // Kart yok, yeni oluştur
                                let templateCard = externalTemplates.get(lessonId) || document.querySelector(`.lesson-card[data-lesson-id="${lessonId}"]`);
                                if (!templateCard && this.draggedLesson && this.draggedLesson.lesson_id == lessonId) {
                                    templateCard = this.draggedLesson.HTMLElement;
                                }

                                if (templateCard) {
                                    let newCard = templateCard.cloneNode(true);
                                    newCard.dataset.scheduleItemId = item.id;
                                    newCard.dataset.classroomId = d.classroom_id || "";
                                    newCard.className = newCard.className.replace('col-md-4', '').replace('p-0', '').replace('ps-1', '').replace('frame', '').trim();
                                    if (!newCard.classList.contains('lesson-card')) newCard.classList.add('lesson-card');

                                    let container = (item.status === 'group') ? (cell.querySelector('.lesson-group-container') || cell.appendChild(Object.assign(document.createElement('div'), { className: 'lesson-group-container' }))) : cell;
                                    newCard.addEventListener('dragstart', this.dragStartHandler.bind(this));
                                    container.appendChild(newCard);
                                }
                            }
                        });
                    }
                }
            }
        });
        [].slice.call(this.table.querySelectorAll('[data-bs-toggle="popover"]')).map(el => new bootstrap.Popover(el, { trigger: 'hover' }));
    }

    dragOverHandler(event) {
        event.preventDefault();
        event.dataTransfer.effectAllowed = "move";
    }

    async dropListToTable() {
        if (this.owner_type !== 'classroom') {
            const result = await this.openAssignmentModal();
            if (!result) return;

            let classroom = result.classroom || result.assignments?.[0];
            if (result.assignments) {
                classroom.id = result.assignments[0].classroom_id;
                classroom.name = result.assignments[0].classroom_name;
                classroom.exam_size = result.assignments[0].classroom_exam_size;
                classroom.size = result.assignments[0].classroom_size;
            }
            let hours = result.hours;
            try {
                await this.checkCrash(hours, classroom);
                let scheduleItems = this.generateScheduleItems(result, classroom);
                if (await this.checkCrashBackEnd(scheduleItems)) {
                    let saveResult = await this.saveScheduleItems(scheduleItems);
                    if (saveResult) this.moveLessonListToTable(scheduleItems, classroom, saveResult);
                }
            } catch (errorMessage) {
                new Toast().prepareToast("Hata", errorMessage, "danger");
            }
        } else {
            try {
                let { hours } = await this.selectHours();
                let classroom = { 'id': this.owner_id, 'name': this.owner_name }
                await this.checkCrash(hours, classroom);
                let scheduleItems = this.generateScheduleItems(hours, classroom);
                let saveScheduleResult = await this.saveScheduleItems(scheduleItems);
                if (saveScheduleResult) this.moveLessonListToTable(scheduleItems, classroom, saveScheduleResult);
            } catch (errorMessage) {
                new Toast().prepareToast("Hata", errorMessage, "danger");
            }
        }
        this.resetDraggedLesson();
    }

    async dropTableToList(skipDelete = false) {
        if (skipDelete || await this.deleteScheduleItems()) {
            if (!this.draggedLesson || !this.draggedLesson.HTMLElement) {
                console.warn("No dragged element found for dropTableToList");
                this.resetDraggedLesson();
                return;
            }

            let draggedElementIdInList = "available-lesson-" + this.draggedLesson.lesson_id;
            let lessonInList = this.list.querySelector("#" + draggedElementIdInList);

            if (lessonInList) {
                if (this.examTypes.includes(this.type)) {
                    lessonInList.dataset.size = (parseInt(lessonInList.dataset.size || 0) + parseInt(this.draggedLesson.classroom_exam_size || 0)).toString();
                    lessonInList.querySelector(".lesson-classroom").innerText = lessonInList.dataset.size + " Kişi";
                } else {
                    lessonInList.dataset.lessonHours = ((parseInt(lessonInList.dataset.lessonHours) || 0) + 1).toString();
                    lessonInList.querySelector(".lesson-classroom").innerText = lessonInList.dataset.lessonHours + " Saat";
                }
                this.draggedLesson.HTMLElement.remove()
            } else {
                let newElement = this.draggedLesson.HTMLElement.cloneNode(true);
                if (!newElement) return;

                newElement.id = draggedElementIdInList;
                let frame = document.createElement("div");
                frame.classList.add("frame", "col-md-4", "p-0", "ps-1");
                this.list.appendChild(frame)

                if (this.examTypes.includes(this.type)) {
                    newElement.dataset.size = this.draggedLesson.classroom_exam_size;
                    newElement.querySelector(".lesson-classroom").innerText = (newElement.dataset.size || "0") + " Kişi";
                } else {
                    newElement.dataset.lessonHours = 1;
                    newElement.querySelector(".lesson-classroom").innerText = "1 Saat";
                }
                if (newElement.querySelector(".lesson-bulk-checkbox")) newElement.querySelector(".lesson-bulk-checkbox").remove()

                if (newElement.dataset) {
                    ['time', 'dayIndex', 'classroomId', 'classroomExamSize', 'classroomSize', 'scheduleItemId'].forEach(k => {
                        if (newElement.dataset[k] !== undefined) delete newElement.dataset[k];
                    });
                }
                newElement.addEventListener('dragstart', this.dragStartHandler.bind(this));
                frame.appendChild(newElement);
                this.draggedLesson.HTMLElement.remove();
            }
            this.updateStickyList();
        }
        this.resetDraggedLesson();
    }

    async dropTableToTable(isBulk = false) {
        let itemsToMove = [], classroom = null, totalHours = 0, itemsToDelete = [], detailedItems = [];

        const elements = (isBulk && this.selectedLessonElements.size > 0) ? Array.from(this.selectedLessonElements).sort((a, b) => a.closest('tr').rowIndex - b.closest('tr').rowIndex) : [this.draggedLesson.HTMLElement];

        elements.forEach(el => {
            const data = this.getLessonItemData(el);
            if (data) {
                const hours = this.getDurationInHours(data.start_time, data.end_time) || 1;
                itemsToMove.push({ element: el, data: data });
                itemsToDelete.push(data);
                totalHours += hours;
                detailedItems.push({ hours, data: data.data[0], status: data.status, originalElement: el });
                if (!classroom) classroom = { id: el.dataset.classroomId, name: el.querySelector('.lesson-classroom')?.innerText || "", size: el.dataset.classroomSize, exam_size: el.dataset.classroomExamSize };
            }
        });

        if (itemsToMove.length === 0) return;

        try {
            await this.checkCrash(totalHours, classroom);
            const newItems = this.generateScheduleItems(detailedItems, classroom);
            if (await this.checkCrashBackEnd(newItems)) {
                if (await this.deleteScheduleItems(itemsToDelete)) {
                    let saveResult = await this.saveScheduleItems(newItems);
                    if (saveResult) {
                        itemsToMove.forEach(item => item.element.remove());
                        this.moveLessonListToTable(newItems, classroom, saveResult);
                    }
                }
            }
        } catch (errorMessage) {
            new Toast().prepareToast("Hata", errorMessage, "danger");
        }
        this.resetDraggedLesson();
    }
}