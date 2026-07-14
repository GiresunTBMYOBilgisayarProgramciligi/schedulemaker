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
        this.examTypes = window.EXAM_TYPES || ['midterm-exam', 'final-exam', 'makeup-exam'];
        this.draggedLesson = {
            'start_element': null,
            'end_element': null,
            'schedule_item_id': null,
            'lesson_id': null,
            'lesson_code': null,
            'lesson_name': null,
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
        this.academic_year = this.card.dataset.academicYear || null;
        this.type = this.card.dataset.type || null;
        this.preference_mode = this.card.dataset.preferenceMode === 'true' || this.card.dataset.preferenceMode === '1';

        if (this.card.dataset.semesterNo) {
            try {
                this.semester_no = JSON.parse(this.card.dataset.semesterNo);
            } catch (e) {
                this.semester_no = this.card.dataset.semesterNo;
            }
        }

        console.log(`[ScheduleCard Initialized] ID: ${this.id}, Owner: ${this.owner_type} #${this.owner_id}, SemesterNo:`, this.semester_no, "AcademicYear:", this.academic_year);

        await this.bindCardEvents();

        this.initContextMenu();
        
        if (this.preference_mode) return; // preference mode'da bulk selection ve context menu init etme SingleScheduleHandler'da yapılacak.

        // Event delegation methods should be initialized only once
        this.initBulkSelection();
        
        // Store initial classes for empty slots
        this.card.querySelectorAll('.empty-slot').forEach(slot => {
            if (!slot.hasAttribute('data-initial-classes')) {
                slot.setAttribute('data-initial-classes', Array.from(slot.classList).join(' '));
            }
        });
        
    }

    async bindCardEvents() {
        let schedule = await this.getSchedule();
        this.list = this.card.querySelector(".available-schedule-items");
        this.table = this.card.querySelector("table.active");

        if (schedule) {
            Object.keys(schedule).forEach((key) => {
                this[key] = schedule[key];
            });
        }

        // Re-initialize Bootstrap Popovers (Her iki modda da çalışmalı)
        const popoverTriggerList = [].slice.call(this.card.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl, { trigger: 'hover' });
        });

        /**
         * Preference Mode (Hoca Tercihleri) durumunda olay yönetimini SingleScheduleHandler'a devret.
         */
        if (this.preference_mode) {
            if (!window.singleScheduleHandlerList[this.id]) {
                window.singleScheduleHandlerList[this.id] = new SingleScheduleHandler();
            }
            window.singleScheduleHandlerList[this.id].bindToCard(this);
            this.initStickyHeaders(); // Sticky header'lar her durumda çalışmalı
            return;
        }

        const dragableElements = this.card.querySelectorAll('[draggable="true"]');
        const dropZones = this.card.querySelectorAll('.drop-zone');

        dragableElements.forEach(element => {
            element.removeEventListener('dragstart', this.dragStartHandler); // prevent duplicate binding
            element.addEventListener('dragstart', this.dragStartHandler.bind(this));
            element.removeEventListener('dragend', this.dragEndHandler);
            element.addEventListener('dragend', this.dragEndHandler.bind(this));
        });

        dropZones.forEach(element => {
            // Remove old listeners to prevent duplicates if called multiple times (though bind creates new function ref, so removeEventListener won't work easily without storing ref. 
            // But since we refresh HTML, old elements are gone, so no duplicate listener issue on elements.)
            element.addEventListener("drop", this.dropHandler.bind(this, element));
            element.addEventListener("dragover", this.dragOverHandler.bind(this))
        });

        this.removeLessonDropZone = this.card.querySelector(".available-schedule-items.drop-zone")

        this.initStickyHeaders();

        // initBulkSelection and initContextMenu are now in initialize() to ensure single binding via delegation.
    }

    // Call this ONLY once in constructor or initialize
    initGlobalEvents() {
        this.initBulkSelection();
        this.initContextMenu();
    }

    async refreshScheduleCard() {
        let data = new FormData();
        data.append("owner_type", this.owner_type);
        data.append("owner_id", this.owner_id);
        data.append("semester", this.semester);
        data.append("academic_year", this.academic_year);
        data.append("type", this.type);

        if (this.semester_no) {
            if (Array.isArray(this.semester_no)) {
                this.semester_no.forEach(no => data.append("semester_no[]", no));
            } else {
                data.append("semester_no", this.semester_no);
            }
        }

        data.append("preference_mode", this.preference_mode ? "true" : "false");

        // Debug logging
        console.group("refreshScheduleCard Call");
        console.log("Card ID:", this.id);
        console.log("Owner:", this.owner_type, this.owner_id);
        console.log("Semester:", this.semester);
        console.log("Semester No:", this.semester_no);
        console.log("Academic Year:", this.academic_year);
        console.log("Type:", this.type);
        console.log("Sent Data:", Object.fromEntries(data.entries()));
        console.groupEnd();

        // Add loading state
        const loadingDiv = document.createElement('div');
        loadingDiv.className = 'overlay d-flex justify-content-center align-items-center';
        loadingDiv.style.position = 'absolute';
        loadingDiv.style.top = '0';
        loadingDiv.style.left = '0';
        loadingDiv.style.width = '100%';
        loadingDiv.style.height = '100%';
        loadingDiv.style.backgroundColor = 'rgba(255,255,255,0.7)';
        loadingDiv.style.zIndex = '50';
        loadingDiv.innerHTML = '<div class="spinner-border text-primary" role="status"></div>';

        if (this.card.style.position === '' || this.card.style.position === 'static') {
            this.card.style.position = 'relative';
        }
        this.card.appendChild(loadingDiv);

        try {
            const response = await fetch("/ajax/getScheduleHTML", {
                method: "POST",
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: data
            });
            const result = await response.json();

            if (result.status === "success" && result.HTML) {
                // Update Card Content
                // We need to parse HTML and extract content to preserve this.card element
                const parser = new DOMParser();
                const doc = parser.parseFromString(result.HTML, 'text/html');
                const newContent = doc.querySelector('.schedule-card')?.innerHTML;

                if (newContent) {
                    this.card.innerHTML = newContent;
                } else {
                    // Fallback if result.HTML is just content without wrapper
                    this.card.innerHTML = result.HTML;
                }

                // Re-bind events and properties
                await this.bindCardEvents();

            } else {
                new Toast().prepareToast("Hata", result.msg || "Kart yenilenirken hata oluştu", "danger");
            }
        } catch (error) {
            console.error("Schedule refresh error:", error);
            new Toast().prepareToast("Hata", "Kart yenilenirken sistem hatası oluştu", "danger");
        } finally {
            loadingDiv.remove();
        }
    }

    /**
     * Alt sınıflar tarafından geçersiz kılınmalıdır.
     * Tablo hücresindeki bir lesson-card elementinden veri çıkartır.
     */
    getLessonItemData(element) {
        console.warn("getLessonItemData should be implemented by subclass.");
        return null;
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

    /**
     * Alt sınıflar tarafından geçersiz kılınmalıdır.
     * Sağ tık menüsünü oluşturur.
     */
    showContextMenu(x, y, lessonCard) {
        console.warn("showContextMenu should be implemented by subclass.");
    }

    async showScheduleInModal(ownerType, ownerId, title) {
        if (!ownerId) {
            new Toast().prepareToast("Hata", "ID bilgisi eksik", "danger");
            return;
        }

        const modal = new Modal();
        modal.prepareModal(title, '<div class="text-center"><div class="spinner-border" role="status"></div></div>', false, true, "xl");

        // Sayfaya git butonu ekle
        let url = "";
        switch (ownerType) {
            case 'user': url = `/admin/profile/${ownerId}`; break;
            case 'classroom': url = `/admin/classroom/${ownerId}`; break;
            case 'program': url = `/admin/program/${ownerId}`; break;
            case 'lesson': url = `/admin/lesson/${ownerId}`; break;
        }

        if (url) {
            const goBtn = document.createElement('button');
            goBtn.className = 'btn btn-info';
            goBtn.innerHTML = '<i class="bi bi-box-arrow-up-right me-1"></i> Sayfaya Git';
            goBtn.onclick = () => window.open(url, '_blank');
            modal.footer.prepend(goBtn);
        }

        modal.showModal();

        const data = new FormData();
        data.append('owner_type', ownerType);
        data.append('owner_id', ownerId);
        data.append('semester', this.semester);
        data.append('academic_year', this.academic_year);
        data.append('type', this.type);
        data.append('only_table', 'true');
        data.append('no_card', 'true');

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
            element.addEventListener('dragend', this.dragEndHandler.bind(this));
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

        // Klonlanan listedeki popover'ları yeniden initialize et
        const popoverTriggers = listClone.querySelectorAll('[data-bs-toggle="popover"]');
        popoverTriggers.forEach(el => new bootstrap.Popover(el, { trigger: 'hover' }));
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
                    console.error("getSchedule API hatası:", data.msg);
                    new Toast().prepareToast("Hata", data.msg, "danger")
                    return false;
                } else {
                    return data.schedule;
                }
            })
            .catch((error) => {
                console.error("getSchedule sistem hatası:", error);
                new Toast().prepareToast("Hata", "Program bilgisi alınırken hata oluştu.", "danger");
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

    /**
     * Alt sınıflar tarafından geçersiz kılınmalıdır.
     * Sürükleme sırasında müsait olmayan hücreleri renklendirir.
     */
    async highlightUnavailableCells() {
        this.clearCells();
        return true;
    }

    clearCells() {
        const classesToRemove = [
            "slot-unavailable", "slot-preferred", "unavailable-for-lecturer",
            "unavailable-for-classroom", "unavailable-for-program"
        ];
        
        for (let i = 0; i < this.table.rows.length; i++) {
            for (let j = 0; j < this.table.rows[i].cells.length; j++) {
                const emptySlot = this.table.rows[i].cells[j].querySelector('.empty-slot');
                if (emptySlot) {
                    const initialClasses = emptySlot.getAttribute('data-initial-classes') || '';
                    classesToRemove.forEach(cls => {
                        // Regex ile tam kelime eşleşmesi kontrolü (örn. "slot-unavailable" var mı)
                        const regex = new RegExp(`\\b${cls}\\b`);
                        if (!regex.test(initialClasses)) {
                            emptySlot.classList.remove(cls);
                        }
                    });
                    if (this.owner_type !== "user") emptySlot.innerHTML = '';// kalan not bilgisini silmek için
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
                console.error("fetchOptions API hatası (" + url + "):", resData.msg);
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
            console.error("fetchOptions sistem hatası (" + url + "):", error);
            new Toast().prepareToast("Hata", "Liste alınırken hata oluştu", "danger");
        }
    }

    async fetchAvailableClassrooms(classroomSelect, hours) {
        const segments = this.generateScheduleItems(hours, null);
        let data = new FormData();
        data.append("schedule_id", this.id);
        data.append("items", JSON.stringify(segments));
        data.append("lesson_id", this.draggedLesson.lesson_id);
        data.append("day_index", this.draggedLesson.end_element.dataset.dayIndex);
        data.append("week_index", this.currentWeekIndex);
        await this.fetchOptions("/ajax/getAvailableClassroomForSchedule", classroomSelect, data, "Bir Sınıf Seçin");
    }

    async fetchAvailableObservers(observerSelect, hours) {
        const segments = this.generateScheduleItems(hours, null);
        let data = new FormData();
        data.append("items", JSON.stringify(segments));
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
            scheduleModal.modal.addEventListener("hidden.bs.modal", () => {
                resolve(null);
            });

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

    /**
     * Alt sınıflar tarafından geçersiz kılınmalıdır.
     * Sürüklenen ders/sınav bilgilerine göre schedule item nesneleri oluşturur.
     */
    generateScheduleItems(input, classroom) {
        console.warn("generateScheduleItems should be implemented by subclass.");
        return [];
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
                    console.error("checkCrashBackEnd API hatası:", data.msg);
                    new Toast().prepareToast("Hata", data.msg, "danger")
                    return false;
                } else {
                    return true;
                }
            })
            .catch((error) => {
                console.error("checkCrashBackEnd sistem hatası:");
                new Toast().prepareToast("Hata", "Sistem hatası!", "danger");
                return false;
            });
    }

    /**
     * Alt sınıflar tarafından geçersiz kılınmalıdır.
     * Schedule item'larını ilgili endpoint'e kaydeder.
     */
    async saveScheduleItems(scheduleItems) {
        console.warn("saveScheduleItems should be implemented by subclass.");
        return false;
    }

    /**
     * Alt sınıflar tarafından geçersiz kılınmalıdır.
     * Schedule item'larını taşır (silme + kaydetme tek transaction).
     */
    async moveScheduleItems(scheduleItems, deletedItems) {
        console.warn("moveScheduleItems should be implemented by subclass.");
        return false;
    }

    /**
     * Alt sınıflar tarafından geçersiz kılınmalıdır.
     * Schedule item'larını siler.
     */
    async deleteScheduleItems(param = null) {
        console.warn("deleteScheduleItems should be implemented by subclass.");
        return false;
    }

    dragStartHandler(event) {
        console.debug("ScheduleCard.dragStartHandler", event);
        this.isDragging = true;
        this.card.classList.add('is-dragging');
        const lessonElement = event.target.closest(".lesson-card");
        if (!lessonElement) return;

        this.setDraggedLesson(lessonElement, event);
        this.setEmptySlotPlaceholders();

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
            console.debug("Raw Data:", rawData);
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
                        await this.refreshScheduleCard(); // Refresh after bulk operation
                    }
                } else {
                    this.draggedLesson.end_element = this.dropZone;

                    await this.dropTableToTable(true);
                    this.clearSelection();
                    // refresh is handled inside dropTableToTable
                }
            } else {
                this.draggedLesson.end_element = this.dropZone;
                if (this.draggedLesson.start_element === "list") {
                    if (!isToList) {

                        await this.dropListToTable();
                    }
                } else {
                    if (isToList) await this.dropTableToList();
                    else {

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

    dragOverHandler(event) {
        event.preventDefault();
        event.dataTransfer.effectAllowed = "move";
    }

    dragEndHandler(event) {
        this.isDragging = false;
        this.card.classList.remove('is-dragging');
        this.clearCells();
        if (this.removeLessonDropZone) {
            this.removeLessonDropZone.style.border = "";
            const tooltip = bootstrap.Tooltip.getInstance(this.removeLessonDropZone);
            if (tooltip) tooltip.hide();
        }
    }

    setEmptySlotPlaceholders() {
        const dropZones = this.table.querySelectorAll('td.drop-zone');
        const headers = this.table.querySelectorAll('thead th');
        
        dropZones.forEach(td => {
            const dayIndex = parseInt(td.dataset.dayIndex);
            let dayName = "";
            let dateText = "";
            const headerCell = headers[dayIndex + 1];
            if (headerCell) {
                dayName = Array.from(headerCell.childNodes)
                    .find(node => node.nodeType === Node.TEXT_NODE)
                    ?.textContent.trim() || "";
                
                const smallTag = headerCell.querySelector('small');
                if (smallTag) {
                    dateText = smallTag.textContent.trim();
                }
            }
            
            const startTime = td.dataset.startTime || "";
            const endTime = td.dataset.endTime || "";
            
            let placeholderText = dayName;
            if (dateText) placeholderText += `\n${dateText}`;
            placeholderText += `\n${startTime} - ${endTime}`;
            
            const emptySlot = td.querySelector('.empty-slot');
            if (emptySlot) {
                emptySlot.setAttribute('data-placeholder', placeholderText);
                if (dateText) {
                    emptySlot.setAttribute('data-date', dateText);
                }
            }
        });
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
                    if (saveResult) {
                        await this.refreshScheduleCard();
                    }
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
                if (saveScheduleResult) {
                    await this.refreshScheduleCard();
                }
            } catch (errorMessage) {
                new Toast().prepareToast("Hata", errorMessage, "danger");
            }
        }
        this.resetDraggedLesson();
    }

    async dropTableToList(skipDelete = false) {
        if (skipDelete || await this.deleteScheduleItems()) {
            if (!skipDelete) await this.refreshScheduleCard();
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
                detailedItems.push({ hours, data: data.data, status: data.status, originalElement: el });
                if (!classroom) classroom = { id: el.dataset.classroomId, name: el.querySelector('.lesson-classroom')?.innerText || "", size: el.dataset.classroomSize, exam_size: el.dataset.classroomExamSize };
            }
        });

        if (itemsToMove.length === 0) return;

        try {
            await this.checkCrash(totalHours, classroom);
            const newItems = this.generateScheduleItems(detailedItems, classroom);
            
            // Sadece taşıma işlemlerinde doğrudan yeni moveScheduleItems fonksiyonunu kullanıyoruz.
            // Bu backend'de tek bir transaction ile önce eski item'ları silecek, sonra yenilerini kaydedecek.
            let moveResult = await this.moveScheduleItems(newItems, itemsToDelete);
            if (moveResult) {
                await this.refreshScheduleCard();
            }
        } catch (errorMessage) {
            new Toast().prepareToast("Hata", errorMessage, "danger");
        }
        this.resetDraggedLesson();
    }


}
