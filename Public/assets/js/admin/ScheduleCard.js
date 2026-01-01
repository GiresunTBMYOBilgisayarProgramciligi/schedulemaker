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
         * Toplu işlem için seçilen derslerin listesi
         * @type {Set<HTMLElement>}
         */
        this.selectedLessonElements = new Set();
        this.selectedScheduleItemIds = new Set();
        this.isProcessing = false; // Concurrent işlem koruması
        this.currentWeekIndex = 0;
        this.weekCount = 1;

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
        this.initBulkSelection(); // Toplu seçim olaylarını başlat
        this.initContextMenu(); // Sağ tık menüsünü başlat
        this.initWeekNavigation(); // Hafta navigasyonunu başlat
    }

    /**
     * Bir ders kartı elementinden silme/kaydetme için gerekli verileri hazırlar
     * @param {HTMLElement} element 
     * @returns {Object}
     */
    getLessonItemData(element) {
        if (!element) return null;
        const ds = element.dataset;
        const cell = element.closest('td');

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

    /**
     * Toplu seçim (Checkbox ve Tıklama) olaylarını dinler
     */
    initBulkSelection() {
        // Checkbox değişimlerini dinle
        this.card.addEventListener('change', (event) => {
            if (event.target.classList.contains('lesson-bulk-checkbox')) {
                const checkbox = event.target;
                const lessonCard = checkbox.closest('.lesson-card');
                this.updateSelectionState(lessonCard, checkbox.checked);
            }
        });

        // Kart tıklamalarını dinle (Tek ve Çift Tıklama)
        this.card.addEventListener('click', (event) => {
            const lessonCard = event.target.closest('.lesson-card');
            if (!lessonCard) return;

            // Linklere veya checkbox'ın kendisine tıklandıysa işlemi tarayıcıya bırak
            if (event.target.tagName === 'A' || event.target.classList.contains('lesson-bulk-checkbox')) {
                return;
            }

            const checkbox = lessonCard.querySelector('.lesson-bulk-checkbox');
            if (checkbox) {
                checkbox.checked = !checkbox.checked;
                // Change event'ini manuel tetikle ki yukarıdaki dinleyici çalışsın
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        this.card.addEventListener('dblclick', (event) => {
            const lessonCard = event.target.closest('.lesson-card');
            if (!lessonCard) return;

            const lessonId = lessonCard.dataset.lessonId;
            if (!lessonId) return;

            // Aynı lesson-id'ye sahip TÜM kartları seç
            const sameLessons = this.card.querySelectorAll(`.lesson-card[data-lesson-id="${lessonId}"]`);
            sameLessons.forEach(card => {
                const cb = card.querySelector('.lesson-bulk-checkbox');
                if (cb && !cb.checked) {
                    cb.checked = true;
                    cb.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });

            // Metin seçimini engelle (çift tıklandığında metin seçilmesi rahatsız edici olabilir)
            window.getSelection().removeAllRanges();
        });
    }

    /**
     * Bir kartın seçim durumunu günceller
     */
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

    /**
     * Tüm seçimleri temizler
     */
    clearSelection() {
        this.selectedLessonElements.forEach(el => {
            el.classList.remove('selected-lesson');
            const cb = el.querySelector('.lesson-bulk-checkbox');
            if (cb) cb.checked = false;
        });
        this.selectedLessonElements.clear();
        this.selectedScheduleItemIds.clear();
    }

    /**
     * Hafta navigasyonu (Önceki/Sonraki Hafta) olaylarını başlatır
     */
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

    /**
     * Belirtilen haftaya geçiş yapar
     * @param {number} weekIndex 
     */
    switchWeek(weekIndex) {
        const tables = this.card.querySelectorAll('table.schedule-table');
        const prevBtn = this.card.querySelector('.prev-week');
        const nextBtn = this.card.querySelector('.next-week');
        const label = this.card.querySelector('.current-week-label');

        // Tabloyu değiştir
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

            // Header yapışkanlığını ve diğer tablo tabanlı özellikleri güncelle
            this.initStickyHeaders();

            // Drag-drop olaylarını yeni tablo için yeniden bağlamak gerekebilir 
            // ama querySelectorAll('.drop-zone') hepsini kapsıyor mu? 
            // initialize'da bind edilenler tüm drop-zone'lar içindi.
            // Eğer tablolar DOM'da zaten varsa ve sadece gizleniyorsa olaylar korunur.
        }

        // Buton durumlarını ve etiketi güncelle
        if (label) label.textContent = `${weekIndex + 1}. Hafta`;
        if (prevBtn) prevBtn.disabled = (weekIndex === 0);
        if (nextBtn) nextBtn.disabled = (weekIndex === this.weekCount - 1);

        // Sticky headers'ı yeni görünür tablo için tetikle
        window.dispatchEvent(new Event('scroll'));
    }

    /**
     * Ders kartları için sağ tık menüsünü başlatır
     */
    initContextMenu() {
        this.card.addEventListener('contextmenu', (event) => {
            const lessonCard = event.target.closest('.lesson-card');
            if (!lessonCard || lessonCard.classList.contains('dummy')) return;

            event.preventDefault();
            this.showContextMenu(event.pageX, event.pageY, lessonCard);
        });

        // Menüyü kapatmak için boş bir yere tıklandığında
        document.addEventListener('click', () => {
            const menu = document.getElementById('lesson-context-menu');
            if (menu) menu.remove();
        });
    }

    /**
     * Özel sağ tık menüsünü gösterir
     */
    showContextMenu(x, y, lessonCard) {
        // Varsa eski menüyü kaldır
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

    /**
     * Belirtilen programı modal içerisinde gösterir
     */
    async showScheduleInModal(ownerType, ownerId, title) {
        if (!ownerId) {
            new Toast().prepareToast("Hata", "ID bilgisi eksik", "danger");
            return;
        }

        const modal = new Modal();
        modal.initializeModal("xl");
        modal.prepareModal(title, '<div class="text-center"><div class="spinner-border" role="status"></div></div>', false, true);
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

        // Re-bind drop events for the list itself if it is a drop zone
        if (listClone.classList.contains('drop-zone')) {
            listClone.addEventListener("drop", this.dropHandler.bind(this, listClone));
            listClone.addEventListener("dragover", this.dragOverHandler.bind(this));
        }

        // Re-bind drop events for any child drop zones
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
                            const cell = this.table.rows[r].cells[c];
                            if (!isNaN(c) && cell) {
                                const emptySlot = cell.querySelector('.empty-slot');
                                if (emptySlot) {
                                    emptySlot.classList.add(...classes);
                                }
                            }
                        });
                    }
                });
            };

            // Derslik
            if (classroomData && classroomData.status !== "error") {
                applyCells(classroomData.unavailableCells, ["slot-unavailable", "unavailable-for-classroom"]);
            }
            // Hoca
            if (lecturerData && lecturerData.status !== "error") {
                applyCells(lecturerData.unavailableCells, ["slot-unavailable", "unavailable-for-lecturer"]);
                applyCells(lecturerData.preferredCells, ["slot-preferred"]);
            }
            // Program
            if (programData && programData.status !== "error") {
                applyCells(programData.unavailableCells, ["slot-unavailable", "unavailable-for-program"]);
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
                const emptySlot = this.table.rows[i].cells[j].querySelector('.empty-slot');
                if (emptySlot) {
                    emptySlot.classList.remove(
                        "slot-unavailable",
                        "slot-preferred",
                        "unavailable-for-lecturer",
                        "unavailable-for-classroom",
                        "unavailable-for-program"
                    );
                }
            }
        }
    }

    /**
     * Generic method to fetch options for a select element
     */
    async fetchOptions(url, targetSelect, data, defaultText = "Seçiniz") {
        targetSelect.innerHTML = `<option value="">${defaultText}</option>`;
        let spinner = new Spinner();
        spinner.showSpinner(targetSelect.querySelector("option"));

        try {
            const response = await fetch(url, {
                method: "POST",
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: data
            });
            const resData = await response.json();
            spinner.removeSpinner();
            targetSelect.innerHTML = `<option value="">${defaultText}</option>`;

            if (resData.status === "error") {
                new Toast().prepareToast("Hata", resData.msg || "Liste alınırken hata oluştu", "danger");
                console.error(resData.msg);
                return;
            }

            // Standardize response to array
            const items = resData.classrooms || resData.observers || [];
            items.forEach(item => {
                let option = document.createElement("option");
                option.value = item.id;

                if (item.class_size !== undefined) {
                    // Classroom
                    const size = this.examTypes.includes(this.type) ? (item.exam_size || 0) : item.class_size;
                    option.innerText = `${item.name} (${size})`;
                    option.dataset.size = item.class_size;
                    option.dataset.examSize = item.exam_size;
                } else {
                    // Observer
                    option.innerText = `${item.title} ${item.name} ${item.last_name}`;
                }
                targetSelect.appendChild(option);
            });

        } catch (error) {
            new Toast().prepareToast("Hata", "Liste alınırken hata oluştu", "danger");
            console.error(error);
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
     * Unified modal for selecting details (Hours, Classroom, Observer)
     */
    openAssignmentModal(options = {}) {
        const { includeObserver = false, title = "Seçim Yapın" } = options;

        return new Promise((resolve, reject) => {
            let scheduleModal = new Modal();
            let maxHours = includeObserver ? this.draggedLesson.size : this.draggedLesson.lesson_hours;
            let initialHours = includeObserver ? 1 : this.draggedLesson.lesson_hours;

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
                ${includeObserver ? `
                <div class="mb-3">
                    <label class="form-label">Gözetmen Seçin</label>
                    <select id="observer" class="form-select" required></select>
                </div>` : ''}
            </form>`;

            scheduleModal.prepareModal(title, modalContentHTML, true, false);
            scheduleModal.showModal();

            let selectedHoursInput = scheduleModal.body.querySelector("#selected_hours");
            let classroomSelect = scheduleModal.body.querySelector("#classroom");
            let observerSelect = includeObserver ? scheduleModal.body.querySelector("#observer") : null;

            const updateLists = () => {
                this.fetchAvailableClassrooms(classroomSelect, selectedHoursInput.value);
                if (includeObserver) {
                    this.fetchAvailableObservers(observerSelect, selectedHoursInput.value);
                }
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
                    new Toast().prepareToast("Dikkat", "Bir derslik seçmelisiniz.", "danger");
                    return;
                }
                if (includeObserver && !observerSelect.value) {
                    new Toast().prepareToast("Dikkat", "Bir gözetmen seçmelisiniz.", "danger");
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

                if (includeObserver) {
                    result.observer = {
                        id: observerSelect.value,
                        full_name: observerSelect.selectedOptions[0].innerText
                    };
                }

                scheduleModal.closeModal();
                resolve(result);
            });
        });
    }

    selectClassroomAndHours() {
        return this.openAssignmentModal({ includeObserver: false, title: "Sınıf ve Saat Seçimi" });
    }

    selectClassroomAndObserver() {
        return this.openAssignmentModal({ includeObserver: true, title: "Derslik ve Gözetmen Seçimi" });
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
        let [hours, mins] = timeStr.split(":").map(Number);
        let date = new Date();
        date.setHours(hours, mins + minutes, 0);
        return date.toTimeString().substring(0, 5);
    }

    /**
     * HH:mm formatını dakikaya çevirir
     */
    timeToMinutes(timeStr) {
        if (!timeStr) return 0;
        const [h, m] = timeStr.split(':').map(Number);
        return (h * 60) + m;
    }

    /**
     * Dakikayı HH:mm formatına çevirir
     */
    minutesToTime(minutes) {
        const h = Math.floor(minutes / 60);
        const m = minutes % 60;
        return `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}`;
    }

    /**
     * İki zaman arasındaki farkı (slot sayısı olarak) döner
     */
    getDurationInHours(startTime, endTime) {
        const start = this.timeToMinutes(startTime);
        const end = this.timeToMinutes(endTime);
        const diff = end - start;
        const slotWithBreak = this.lessonHourToMinute(1) + this.breakDuration;

        // Eğer diff tam slot süresine bölünmüyorsa (son ders teneffüssüz bitmişse) yukarı yuvarla
        return Math.ceil(diff / (this.lessonHourToMinute(1) + this.breakDuration));
    }

    /**
     * Verilen ders listesi veya süresi kadar ScheduleItem nesnesi üretir.
     * Ardışık yerleştirme mantığını (bulk move) destekler.
     * @param {number|Array} input Saat sayısı veya ders veri listesi
     * @param {Object} classroom 
     */
    generateScheduleItems(input, classroom) {
        let scheduleItems = [];
        let itemsToProcess = [];

        if (Array.isArray(input)) {
            itemsToProcess = input;
        } else {
            // Tek bir giriş varsa eski mantıkla diziye çevir
            itemsToProcess = [{
                hours: input,
                data: {
                    "lesson_id": this.draggedLesson.lesson_id,
                    "lecturer_id": this.draggedLesson.lecturer_id,
                    "classroom_id": classroom.id
                },
                status: this.draggedLesson.group_no > 0 ? "group" : "single"
            }];
        }

        let currentSlotOffset = 0;
        let breakTime = this.breakDuration;

        itemsToProcess.forEach(itemInfo => {
            let currentItem = null;
            let addedHours = 0;
            let hoursNeeded = itemInfo.hours;
            let i = 0;

            while (addedHours < hoursNeeded) {
                let rowIndex = this.draggedLesson.end_element.closest("tr").rowIndex + currentSlotOffset;

                if (rowIndex >= this.table.rows.length) break;

                let row = this.table.rows[rowIndex];
                let cell = row.cells[this.draggedLesson.end_element.cellIndex];

                let isValid = cell && cell.classList.contains("drop-zone") && !cell.querySelector('.slot-unavailable');

                if (isValid) {
                    if (!currentItem) {
                        currentItem = {
                            'id': null, // Her zaman yeni kayıt (Insert)
                            'schedule_id': this.id,
                            'day_index': parseInt(this.draggedLesson.end_element.dataset.dayIndex),
                            'week_index': parseInt(this.table?.dataset?.weekIndex || 0),
                            'start_time': cell.dataset.startTime,
                            'end_time': null,
                            'status': itemInfo.status,
                            'data': itemInfo.data,
                            'detail': null
                        };
                    }

                    let slotDuration = this.lessonHourToMinute(1);

                    if (currentItem.end_time) {
                        currentItem.end_time = this.addMinutes(currentItem.end_time, slotDuration + breakTime);
                    } else {
                        currentItem.end_time = this.addMinutes(currentItem.start_time, slotDuration);
                    }

                    addedHours++;
                    currentSlotOffset++; // Global offset her geçerli slotta artar
                } else {
                    // Geçersiz slot (öğle arası vb.) - offset artar ama addedHours artmaz
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

        console.log('Generated Bulk Schedule Items:', scheduleItems);
        return scheduleItems;
    }

    moveLessonListToTable(scheduleItems, classroom, createdIds = []) {
        console.log('moveLessonListToTable', scheduleItems, classroom, createdIds);

        let addedHours = 0;
        let idIndex = 0;

        scheduleItems.forEach(item => {
            let itemStartTime = item.start_time;
            let itemEndTime = item.end_time;
            // day_index should typically be an integer, ensure it
            let targetDayIndex = parseInt(item.day_index, 10);
            let colIndex = targetDayIndex + 1; // 0 index is time column

            // Backend'den gelen gruplanmış ID'ler varsa ekran tipine (owner_type) uygun olanı seç
            let currentDataId = item.id;
            if (createdIds && createdIds[idIndex]) {
                const groupedIds = createdIds[idIndex];
                // Ekran tipine göre ID seç, yoksa fallback olarak program ID'sini kullan
                const targetIds = groupedIds[this.owner_type] || groupedIds['program'];
                if (targetIds && targetIds.length > 0) {
                    currentDataId = targetIds[0];
                }
            }
            idIndex++;

            // Elementi bul (Eğer item içinde originalElement varsa onu kullan, yoksa genel draggedLesson'ı kullan)
            const sourceElement = item.originalElement || this.draggedLesson.HTMLElement;

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
                    cell.dataset.scheduleItemId = currentDataId;

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
                    let lessonCard = sourceElement.cloneNode(true);

                    // Seçim durumunu temizle (Klonlandığı için eski seçim hali gelebilir)
                    lessonCard.classList.remove('selected-lesson');
                    const bulkCb = lessonCard.querySelector('.lesson-bulk-checkbox');
                    if (bulkCb) bulkCb.checked = false;

                    // Gereksiz classları temizle (frame, col-md-4 vb.)
                    lessonCard.className = lessonCard.className
                        .replace('col-md-4', '')
                        .replace('p-0', '')
                        .replace('ps-1', '')
                        .replace('frame', '')
                        .trim();

                    if (!lessonCard.classList.contains('lesson-card')) lessonCard.classList.add('lesson-card');

                    // Bulk checkbox ekle (zaten varsa ekleme)
                    if (!lessonCard.querySelector('.lesson-bulk-checkbox')) {
                        const bulkCheckbox = document.createElement('input');
                        bulkCheckbox.type = 'checkbox';
                        bulkCheckbox.className = 'lesson-bulk-checkbox';
                        bulkCheckbox.title = 'Toplu işlem için seç';
                        lessonCard.prepend(bulkCheckbox);
                    }

                    // Attribute'leri ayarla
                    lessonCard.setAttribute('draggable', 'true');
                    lessonCard.dataset.scheduleItemId = currentDataId;
                    lessonCard.dataset.groupNo = lessonCard.dataset.groupNo || 0;
                    lessonCard.dataset.size = lessonCard.dataset.size || 0;
                    lessonCard.dataset.lessonId = lessonCard.dataset.lessonId;
                    lessonCard.dataset.lessonCode = lessonCard.dataset.lessonCode;
                    lessonCard.dataset.classroomId = classroom.id;
                    lessonCard.dataset.classroomSize = classroom.size;
                    lessonCard.dataset.classroomExamSize = classroom.exam_size;

                    // Lecturer handling
                    let lecturerId;
                    if (this.examTypes.includes(this.type) && lessonCard.dataset.observerId) {
                        lecturerId = lessonCard.dataset.observerId;
                    } else {
                        lecturerId = lessonCard.dataset.lecturerId;
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
                    return data.createdIds || true;
                }
            })
            .catch((error) => {
                new Toast().prepareToast("Hata", "Program kaydedilirken hata oluştu. Detaylar için geliştirici konsoluna bakın", "danger");
                console.error(error);
                return false;
            });
    }
    //todo
    /**
     * Programdan dersleri silmek için kullanılır.
     * @param {Array|string|number|null} param Silinecek derslerin listesi (Array) veya tek bir derslik ID'si. Null ise seçilenler veya sürüklenen ders kullanılır.
     */
    async deleteScheduleItems(param = null) {
        let scheduleItems = [];

        if (Array.isArray(param)) {
            // Doğrudan item listesi verilmiş (Toplu İşlem)
            scheduleItems = param;
        } else if (param === null && this.selectedLessonElements.size > 0) {
            // Hiçbir parametre yok ve seçim var (Toplu İşlem - dropTableToList gibi yerlerden çağrılırsa)
            this.selectedLessonElements.forEach(el => {
                const itemData = this.getLessonItemData(el);
                if (itemData) scheduleItems.push(itemData);
            });
        } else {
            // Tekli işlem (draggedLesson kullanılır)
            const itemData = this.getLessonItemData(this.draggedLesson.HTMLElement);
            if (itemData) {
                // Eğer param verilmişse (eski sistemde classroom_id), onu ezebiliriz.
                if (param && (typeof param === 'string' || typeof param === 'number')) {
                    itemData.classroom_id = param;
                }
                scheduleItems.push(itemData);
            }
        }

        if (scheduleItems.length === 0) {
            console.warn("Silinecek ders öğesi bulunamadı.");
            return false;
        }

        console.log("Silinmesi istenen öğeler:", scheduleItems);

        let data = new FormData();
        data.append("items", JSON.stringify(scheduleItems));


        return fetch("/ajax/deleteScheduleItems", {
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
                    console.info("Silme işlemi yanıtı (v4.6):", data);
                    // 1. ÖNCE Yeni oluşanları (split) senkronize et (ID'leri güncelleyerek silinmekten kurtar)
                    if (data.createdItems && data.createdItems.length > 0) {
                        this.syncTableItems(data.createdItems);
                    }
                    // 2. SONRA Gerçekten silinenleri temizle
                    if (data.deletedIds && data.deletedIds.length > 0) {
                        this.clearTableItemsByIds(data.deletedIds);
                    }
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
        const lessonElement = event.target.closest(".lesson-card");
        if (!lessonElement) return;

        // Her durumda sürüklenen eleman bilgisini ayarla (highlight için gerekli)
        this.setDraggedLesson(lessonElement, event);

        // Eğer sürüklenen ders seçili değilse, mevcut seçimi temizle ve sadece bunu seçili yap (opsiyonel ama standart UX)
        // Ancak kullanıcı çoklu sürüklemek istiyorsa, seçili olanlardan birini tutuyor olmalı.
        if (this.selectedLessonElements.size > 0 && this.selectedLessonElements.has(lessonElement)) {
            // Çoklu sürükleme durumu
            const items = Array.from(this.selectedScheduleItemIds);
            event.dataTransfer.setData("text/plain", JSON.stringify({
                type: 'bulk',
                ids: items
            }));
        } else {
            // Tekli sürükleme
            this.clearSelection(); // Mevcut seçimleri temizle
            event.dataTransfer.setData("text/plain", JSON.stringify({
                type: 'single',
                id: this.draggedLesson.schedule_item_id
            }));
        }

        event.dataTransfer.effectAllowed = "move";
        event.dataTransfer.dropEffect = "move";
        // The original logic for `removeLessonDropZone` and `highlightUnavailableCells` was here.
        // It seems the new logic for `highlightUnavailableCells` is now inside the `else` block for single drag.
        // The `removeLessonDropZone` logic should still apply if `draggedLesson.start_element` is "table".
        // Let's re-add the `removeLessonDropZone` logic if it's a table element being dragged.
        // The `console.log` and `if (this.draggedLesson.start_element === "table")` block should be preserved.
        // The `highlightUnavailableCells` call is already in the `else` block for single drag.
        // The `.then` part of `highlightUnavailableCells` should also be preserved.

        // Re-integrating the original logic that was implicitly removed by the snippet:
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

    async dropHandler(element, event) {
        if (this.isProcessing) {
            console.warn("Already processing a drop event, ignoring...");
            return;
        }
        this.isProcessing = true;

        try {
            event.preventDefault();
            this.isDragging = false;
            this.clearCells();
            // ... (rest of logic)
            /*
            * silmek için buraya sürükleyin yazısını göstermek için eklenen tooltip kaldırılıyor
            * */
            if (this.removeLessonDropZone) {
                this.removeLessonDropZone.style.border = ""
                const tooltip = bootstrap.Tooltip.getInstance(this.removeLessonDropZone);
                if (tooltip) tooltip.hide()
            }

            this.dropZone = element;
            console.log("Drop triggered on:", element);

            let dragData;
            try {
                dragData = JSON.parse(event.dataTransfer.getData("text/plain"));
            } catch (e) {
                console.error("Invalid drag data", e);
                return;
            }

            const isToList = this.dropZone.classList.contains("available-schedule-items");

            if (dragData.type === 'bulk') {
                const ids = dragData.ids;
                if (isToList) {
                    // Toplu silme (Tablodan Listeye)
                    let deleteResult = await this.deleteScheduleItems(); // Tüm seçili dersleri bir kerede silmeye gönder
                    if (deleteResult) {
                        // Seçili elementlerin kopyasını alalım çünkü loop içinde DOM'dan silinecekler
                        const elementsToProcess = Array.from(this.selectedLessonElements).filter(el => ids.includes(el.dataset.scheduleItemId));

                        for (const el of elementsToProcess) {
                            this.draggedLesson.HTMLElement = el;
                            this.draggedLesson.schedule_item_id = el.dataset.scheduleItemId;
                            this.getDatasetValue(this.draggedLesson, el);
                            // dropTableToList içinde this.draggedLesson kullanıldığı için her seferinde set ediyoruz
                            await this.dropTableToList(true); // true = skip delete call (already done)
                        }
                        this.clearSelection();
                    }
                } else {
                    // Toplu taşıma (Tablodan Tabloya) - YENİ: Toplu yönetiliyor
                    console.log("Bulk move detected with IDs:", ids);
                    this.draggedLesson.end_element = this.dropZone;
                    this.draggedLesson.end_element.dataset.dayIndex = this.dropZone.cellIndex - 1;
                    await this.dropTableToTable(true); // true = bulk
                    this.clearSelection();
                }
            } else {
                // Tekli sürükleme
                this.draggedLesson.end_element = this.dropZone;
                if (this.draggedLesson.start_element === "list") {
                    if (!isToList) {
                        this.draggedLesson.end_element.dataset.dayIndex = this.dropZone.cellIndex - 1;
                        await this.dropListToTable();
                    }
                } else { // draggedLesson.start_element === "table"
                    console.log("dropHandler: Table source. isToList:", isToList, "DropZone:", this.dropZone);
                    if (isToList) {
                        await this.dropTableToList();
                    } else {
                        this.draggedLesson.end_element.dataset.dayIndex = this.dropZone.cellIndex - 1;
                        await this.dropTableToTable();
                    }
                }
            }

            this.clearSelection();
            document.dispatchEvent(lessonDrop);
        } catch (e) {
            console.error("Drop handler failed:", e);
        } finally {
            this.isProcessing = false;
        }
    }

    /**
     * Backend'den gelen silinen ID'leri tablodan ve DOM'dan temizler.
     * @param {Array} deletedIds 
     */
    clearTableItemsByIds(deletedIds) {
        if (!deletedIds || deletedIds.length === 0) return;
        console.log("Tablodan silinecek ID'ler:", deletedIds);

        const idSet = new Set(deletedIds.map(id => id.toString()));

        // Tüm hücreleri gez ve eşleşen ID'leri temizle
        for (let i = 0; i < this.table.rows.length; i++) {
            const row = this.table.rows[i];
            for (let j = 1; j < row.cells.length; j++) {
                const cell = row.cells[j];
                const cellId = cell.dataset.scheduleItemId ? cell.dataset.scheduleItemId.toString() : null;
                if (cellId && idSet.has(cellId)) {
                    // Hücre içindeki kartları bul (Henüz ID'yi silmeden önce)
                    const orphanedCards = cell.querySelectorAll(`.lesson-card[data-schedule-item-id="${cellId}"]`);
                    orphanedCards.forEach(c => c.remove());

                    // ID'yi hücreden sil
                    delete cell.dataset.scheduleItemId;

                    // KRİTİK: Hücrede hiç ders kartı veya konteyner kalmadıysa boş slot div'ini geri koy
                    const hasLesson = cell.querySelector('.lesson-card');
                    const hasContainer = cell.querySelector('.lesson-group-container');

                    if (!hasLesson && !hasContainer) {
                        cell.innerHTML = '<div class="empty-slot"></div>';
                    } else if (hasContainer && !hasLesson) {
                        // Boş konteyner kaldıysa onu da temizle ve slot koy
                        cell.innerHTML = '<div class="empty-slot"></div>';
                    }
                }
            }
        }

        // Tablo dışında kalmış (orphaned) kartlar varsa onları da DOM'dan temizle
        idSet.forEach(id => {
            const cards = document.querySelectorAll(`.lesson-card[data-schedule-item-id="${id}"]`);
            cards.forEach(card => card.remove());
        });
    }

    /**
     * Backend'den gelen yeni item'ları (split sonrası oluşanlar) tabloya yansıtır.
     * @param {Array} createdItems 
     */
    syncTableItems(createdItems) {
        console.log("Syncing Table Items with new IDs (v4.5):", createdItems);
        createdItems.forEach(item => {
            // Sadece bu programa ait öğeleri senkronize et (Diğer kardeş programların ID'lerini yoksay)
            if (item.schedule_id != this.id) return;

            const dayIndex = parseInt(item.day_index, 10);
            const itemStartTime = item.start_time.substring(0, 5);
            const itemEndTime = item.end_time.substring(0, 5);
            const colIndex = dayIndex + 1;

            // Önemli: Ders sadece başladığı hücrede değil, sürdüğü tüm hücrelerde ID'sini taşımalıdır.
            for (let i = 0; i < this.table.rows.length; i++) {
                const row = this.table.rows[i];
                const cell = row.cells[colIndex];
                if (!cell) continue;

                let cellStartTime = cell.dataset.startTime;
                if (!cellStartTime && row.cells[0]) {
                    cellStartTime = row.cells[0].innerText.trim().substring(0, 5);
                }

                // Eğer hücrenin saati dersin aralığında ise ID'yi güncelle
                if (cellStartTime && cellStartTime >= itemStartTime && cellStartTime < itemEndTime) {
                    cell.dataset.scheduleItemId = item.id;

                    // Hücre içindeki ders kartlarını ve boş slotu bul
                    let lessonCards = cell.querySelectorAll('.lesson-card');
                    let emptySlot = cell.querySelector('.empty-slot');

                    // Kurtarılmış Preferred/Unavailable alanı ise (üzerinde ders yoksa)
                    if (item.status === 'preferred' || item.status === 'unavailable') {
                        // Eğer hücrede ders kartı veya konteyner varsa temizle (kurtarma işlemi)
                        if (lessonCards.length > 0 || cell.querySelector('.lesson-group-container')) {
                            cell.innerHTML = '<div class="empty-slot"></div>';
                        } else if (!emptySlot) {
                            cell.innerHTML = '<div class="empty-slot"></div>';
                        }
                        return; // Preferred slotlar ana Program görünümünde kart olarak çizilmez
                    }

                    // Ders ekleniyorsa/senkronize ediliyorsa boş slotu kaldır
                    if (emptySlot) {
                        emptySlot.remove();
                    }

                    // KRİTİK: Eğer hücre olması gereken ID'ye sahip ama içinde GÖRSEL KART YOKSA (bölünme sonrası), kartı oluştur.
                    if (lessonCards.length === 0) {
                        const data = (typeof item.data === 'string') ? JSON.parse(item.data) : item.data;
                        const lessonId = data && data[0] ? data[0].lesson_id : null;

                        if (lessonId) {
                            // Dokümandaki herhangi bir aynı ders kartını bul (şablon olarak kullanmak için)
                            const templateCard = document.querySelector(`.lesson-card[data-lesson-id="${lessonId}"]`);
                            if (templateCard) {
                                console.log(`Re-creating missing card for lesson ${lessonId} in cell ${cellStartTime}`);
                                let newCard = templateCard.cloneNode(true);
                                newCard.dataset.scheduleItemId = item.id;

                                // Group container kontrolü
                                let container = cell;
                                if (item.status === 'group') {
                                    container = cell.querySelector('.lesson-group-container');
                                    if (!container) {
                                        container = document.createElement('div');
                                        container.classList.add('lesson-group-container');
                                        cell.appendChild(container);
                                    }
                                }
                                container.appendChild(newCard);
                                lessonCards = [newCard]; // loop devam etmesi için
                            }
                        }
                    }

                    // Mevcut veya yeni oluşturulan kartların ID'sini güncelle
                    lessonCards.forEach(card => {
                        card.dataset.scheduleItemId = item.id;
                    });
                }
            }
        });

        // Canlı güncelleme sonrası popoverları (not ikonları) etkinleştir
        const popoverTriggerList = [].slice.call(this.table.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl, { trigger: 'hover' });
        });
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
                        this.moveLessonListToTable(scheduleItems, classroom, saveResult);
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
                let scheduleItems = this.generateScheduleItems(hours, classroom);
                let saveScheduleResult = await this.saveScheduleItems(scheduleItems);
                if (saveScheduleResult) {
                    saveScheduleToast.closeToast()
                    this.moveLessonListToTable(scheduleItems, classroom, saveScheduleResult);
                }
            } catch (errorMessage) {
                new Toast().prepareToast("Hata", errorMessage, "danger");
                console.error(errorMessage);
            }
        }

        this.resetDraggedLesson();
    }
    async dropTableToList(skipDelete = false) {
        console.warn("dropTableToList CALLED! SkipDelete:", skipDelete);

        let deleteScheduleResult = skipDelete ? true : await this.deleteScheduleItems();

        if (deleteScheduleResult) {
            let draggedElementIdInList = "available-lesson-" + this.draggedLesson.lesson_id;
            // Always look in the ORIGINAL list
            let lessonInList = this.list.querySelector("#" + draggedElementIdInList);

            //listede taşınan dersin varlığını kontrol et
            if (lessonInList) {
                let badgeText = '';
                if (this.type == 'exam') {
                    lessonInList.dataset.size = (parseInt(lessonInList.dataset.size) + parseInt(this.draggedLesson.classroom_exam_size)).toString();
                    badgeText = lessonInList.dataset.size + " Kişi";
                } else {
                    lessonInList.dataset.lessonHours = ((parseInt(lessonInList.dataset.lessonHours) || 0) + 1).toString();
                    badgeText = lessonInList.dataset.lessonHours + " Saat";
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
                console.warn("Adding NEW element to Available List:", draggedElementIdInList);
                let newElement = this.draggedLesson.HTMLElement.cloneNode(true);
                // Reset attributes
                newElement.id = draggedElementIdInList;
                // Original logic followed below:

                let draggedElementFrameDiv = document.createElement("div");
                draggedElementFrameDiv.classList.add("frame", "col-md-4", "p-0", "ps-1");
                this.list.appendChild(draggedElementFrameDiv)

                let badgeText = '';
                if (this.type == 'exam') {
                    newElement.dataset.size = this.draggedLesson.classroom_exam_size
                    badgeText = newElement.dataset.size + " Kişi";
                } else {
                    newElement.dataset.lessonHours = 1;
                    badgeText = newElement.dataset.lessonHours + " Saat";
                }
                newElement.querySelector(".lesson-classroom").innerText = badgeText
                newElement.querySelector(".lesson-bulk-checkbox").remove()

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
    async dropTableToTable(isBulk = false) {
        console.log("dropTableToTable called. isBulk:", isBulk);
        let itemsToMove = [];
        let classroom = null;
        let totalHours = 0;
        let itemsToDelete = []; // Eskiden oldIds idi, artık tüm nesne listesi
        let detailedItems = [];

        if (isBulk && this.selectedLessonElements.size > 0) {
            const sortedElements = Array.from(this.selectedLessonElements).sort((a, b) => {
                const rowA = a.closest('tr').rowIndex;
                const rowB = b.closest('tr').rowIndex;
                return rowA - rowB;
            });

            sortedElements.forEach(el => {
                const data = this.getLessonItemData(el);
                if (data) {
                    const hours = this.getDurationInHours(data.start_time, data.end_time) || 1;
                    itemsToMove.push({ element: el, data: data });
                    itemsToDelete.push(data); // Nesnenin tamamını ekle
                    totalHours += hours;

                    detailedItems.push({
                        hours: hours,
                        data: data.data[0], // Sadece iç veri (lesson_id vb)
                        status: data.status,
                        originalElement: el // Klonlama için referans
                    });
                }
            });

            if (itemsToMove.length > 0) {
                const el = itemsToMove[0].element;
                const classroomSpan = el.querySelector('.lesson-classroom');
                classroom = {
                    id: el.dataset.classroomId,
                    name: classroomSpan ? classroomSpan.innerText : "",
                    size: el.dataset.classroomSize,
                    exam_size: el.dataset.classroomExamSize
                };
            }
        } else {
            const element = this.draggedLesson.HTMLElement;
            const data = this.getLessonItemData(element);
            if (data) {
                const hours = this.getDurationInHours(data.start_time, data.end_time) || 1;
                itemsToMove.push({ element: element, data: data });
                itemsToDelete.push(data);
                totalHours = hours;

                detailedItems.push({
                    hours: hours,
                    data: data.data[0],
                    status: data.status,
                    originalElement: element
                });

                const classroomSpan = element.querySelector('.lesson-classroom');
                classroom = {
                    id: element.dataset.classroomId,
                    name: classroomSpan ? classroomSpan.innerText : "",
                    size: element.dataset.classroomSize,
                    exam_size: element.dataset.classroomExamSize
                };
            }
        }

        if (itemsToMove.length === 0) return;

        try {
            await this.checkCrash(totalHours, classroom);
        } catch (errorMessage) {
            new Toast().prepareToast("Hata", errorMessage, "danger");
            return;
        }

        const newItems = this.generateScheduleItems(detailedItems, classroom);

        // 3. Backend Çakışma Kontrolü
        if (await this.checkCrashBackEnd(newItems)) {
            // 4. ESKİLERİ SİL (Toplu)
            console.log("Attempting to delete items:", itemsToDelete);
            if (await this.deleteScheduleItems(itemsToDelete)) {
                // 5. YENİLERİ KAYDET (Toplu)
                let saveResult = await this.saveScheduleItems(newItems);
                if (saveResult) {
                    // 6. DOM GÜNCELLEME
                    // Eskileri temizle
                    itemsToMove.forEach(item => item.element.remove());

                    // Yeni yerine yerleştir
                    this.moveLessonListToTable(newItems, classroom, saveResult);
                    console.info("Dersler başarıyla taşındı.");
                } else {
                    console.error("Yeni dersler kaydedilemedi!");
                    new Toast().prepareToast("Hata", "Dersler taşınırken kayıt aşamasında sorun oluştu.", "danger");
                }
            } else {
                console.error("Eski dersler silinemedi");
            }
        }

        this.resetDraggedLesson();
    }
}