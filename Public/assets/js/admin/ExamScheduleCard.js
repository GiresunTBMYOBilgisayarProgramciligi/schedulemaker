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

    async bindCardEvents() {
        await super.bindCardEvents();
        this.initWeekNavigation();
        if (this.weekCount > 1) {
            this.switchWeek(this.currentWeekIndex);
        }
    }

    /**
     * Sınav programı için özel sağ tık menüsü.
     * Atanmış tüm gözetmen ve derslikleri listeler.
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

        let menuItems = [];
        const programId = lessonCard.dataset.programId;

        // Atamaları kontrol et
        let assignments = [];
        if (lessonCard.dataset.detail) {
            try {
                const detail = JSON.parse(lessonCard.dataset.detail);
                if (detail && detail.assignments) {
                    assignments = detail.assignments;
                }
            } catch (e) {
                console.error("JSON parse error for lesson card detail", e);
            }
        }

        if (assignments.length > 0) {
            assignments.forEach(asgn => {
                if (Array.isArray(asgn.observers) && asgn.observers.length > 0) {
                    asgn.observers.forEach(obs => {
                        if (obs.id) {
                            menuItems.push({
                                text: `${obs.name || 'Gözetmen'} programını göster`,
                                icon: 'bi-person-badge',
                                onClick: () => this.showScheduleInModal('user', obs.id, `${obs.name || 'Gözetmen'} Programı`)
                            });
                        }
                    });
                } else if (asgn.observer_id) {
                    menuItems.push({
                        text: `${asgn.observer_name} programını göster`,
                        icon: 'bi-person-badge',
                        onClick: () => this.showScheduleInModal('user', asgn.observer_id, `${asgn.observer_name} Programı`)
                    });
                }
                if (asgn.classroom_id) {
                    menuItems.push({
                        text: `${asgn.classroom_name} programını göster`,
                        icon: 'bi-door-open',
                        onClick: () => this.showScheduleInModal('classroom', asgn.classroom_id, `${asgn.classroom_name} Programı`)
                    });
                }
            });
        } else {
            // Detay yoksa ScheduleCard'daki gibi dataset'ten dene (belki liste tarafındadır veya tekil atamadır)
            const lecturerId = lessonCard.dataset.lecturerId;
            const lecturerName = lessonCard.dataset.lecturerName || 'Hoca';
            const classroomId = lessonCard.dataset.classroomId;
            const classroomName = lessonCard.dataset.classroomName || 'Derslik';
            if (lecturerId) {
                menuItems.push({
                    text: `${lecturerName} programını göster`,
                    icon: 'bi-person-badge',
                    onClick: () => this.showScheduleInModal('user', lecturerId, `${lecturerName} Programı`)
                });
            }
            if (classroomId) {
                menuItems.push({
                    text: `${classroomName} programını göster`,
                    icon: 'bi-door-open',
                    onClick: () => this.showScheduleInModal('classroom', classroomId, `${classroomName} Programı`)
                });
            }
        }

        if (programId) {
            const programName = lessonCard.dataset.programName || 'Program';
            menuItems.push({
                text: `${programName} programını göster`,
                icon: 'bi-book',
                onClick: () => this.showScheduleInModal('program', programId, `${programName} Programı`)
            });
        }

        // Dersi düzenle butonu
        const lessonId = lessonCard.dataset.lessonId;
        if (lessonId) {
            const lessonName = lessonCard.dataset.lessonName || 'Ders';
            menuItems.push({
                text: `${lessonName} sayfasını aç`,
                icon: 'bi-pencil-square',
                onClick: () => window.open(`/admin/lesson/${lessonId}`, '_blank')
            });
        }

        // Sınavı düzenle butonu
        const scheduleItemId = lessonCard.dataset.scheduleItemId;
        const isLocked = lessonCard.dataset.isLocked === 'true';
        if (scheduleItemId) {
            menuItems.push({
                text: 'Sınavı Düzenle',
                icon: 'bi-pencil-fill',
                onClick: () => {
                    if (isLocked) {
                        new Toast().prepareToast("Uyarı", "Kilitli bir sınav ögesi düzenlenemez. Önce kilidi açın.", "warning");
                    } else {
                        this.editExamScheduleItem(lessonCard);
                    }
                }
            });
        }

        menuItems.forEach(item => {
            const menuItem = document.createElement('div');
            menuItem.className = 'context-menu-item';
            menuItem.innerHTML = `<i class="bi ${item.icon} me-2"></i>${item.text}`;
            menuItem.onclick = (e) => {
                e.stopPropagation();
                item.onClick();
                menu.remove();
            };
            menu.appendChild(menuItem);
        });

        document.body.appendChild(menu);
    }

    /**
     * Program öğesinin kilidini açar veya kilitler (çoklu seçimi destekler).
     */
    async toggleLockScheduleItem(scheduleItemId, targetLockState = null) {
        let ids = [];
        // Eğer tıklanan eleman seçili elemanlar listesinde değilse sadece ona işlem yap
        if (scheduleItemId && !this.selectedScheduleItemIds.has(scheduleItemId)) {
            ids.push(scheduleItemId);
        } else if (this.selectedScheduleItemIds.size > 0) {
            // Eğer çoklu seçim varsa hepsine uygula
            ids = Array.from(this.selectedScheduleItemIds);
        } else if (scheduleItemId) {
            ids.push(scheduleItemId);
        }

        if (ids.length === 0) return;

        let data = new FormData();
        data.append("ids", JSON.stringify(ids));
        if (targetLockState !== null) {
            data.append("target_state", targetLockState ? '1' : '0');
        }

        try {
            const response = await fetch("/ajax/toggleLockScheduleItem", {
                method: "POST",
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: data,
            });
            const result = await response.json();

            if (result.status === "error") {
                new Toast().prepareToast("Hata", result.msg, "danger");
            } else {
                new Toast().prepareToast("Başarılı", result.msg, "success");
                
                // Seçimleri temizle
                this.selectedScheduleItemIds.clear();
                this.selectedLessonElements.forEach(el => el.classList.remove('selected-lesson'));
                this.selectedLessonElements.clear();
                const checkboxes = this.table.querySelectorAll('.lesson-bulk-checkbox');
                checkboxes.forEach(cb => cb.checked = false);

                await this.refreshScheduleCard();
            }
        } catch (error) {
            console.error("toggleLockScheduleItem sistem hatası:", error);
            new Toast().prepareToast("Hata", "İşlem sırasında hata oluştu.", "danger");
        }
    }

    /**
     * Sınav programı ögesini düzenleme işlemi (Modal açıp var olan verileri yükler,
     * kullanıcı onayladığında eski ögeyi silip yeni verilerle oluşturur).
     */
    async editExamScheduleItem(lessonCard) {
        if (!lessonCard) return;

        const scheduleItemId = lessonCard.dataset.scheduleItemId;
        const lessonId = lessonCard.dataset.lessonId;
        const lessonName = lessonCard.dataset.lessonName || 'Ders';
        const lessonCode = lessonCard.dataset.lessonCode || '';
        const groupNo = lessonCard.dataset.groupNo || 0;
        const size = lessonCard.dataset.size || 0;
        const cell = lessonCard.closest('td');

        if (!scheduleItemId || !cell) return;

        let detail = {};
        if (lessonCard.dataset.detail) {
            try {
                detail = JSON.parse(lessonCard.dataset.detail);
            } catch (e) {
                console.error("JSON parse error for lesson card detail", e);
            }
        }

        const itemData = this.getLessonItemData(lessonCard);
        if (!itemData) return;

        const currentHours = this.getDurationInHours(itemData.start_time, itemData.end_time) || 1;

        this.resetDraggedLesson();
        this.draggedLesson.lesson_id = lessonId;
        this.draggedLesson.lesson_name = lessonName;
        this.draggedLesson.lesson_code = lessonCode;
        this.draggedLesson.group_no = groupNo;
        this.draggedLesson.size = size;
        this.draggedLesson.lecturer_id = lessonCard.dataset.lecturerId;
        this.draggedLesson.end_element = cell;
        this.draggedLesson.schedule_item_id = scheduleItemId;
        this.draggedLesson.HTMLElement = lessonCard;

        const initialData = {
            hours: currentHours,
            assignments: detail.assignments || [],
            ignore_remaining: !!detail.ignore_remaining
        };

        const modalTitle = `${lessonName} - Sınav Düzenle`;
        const result = await this.openAssignmentModal(modalTitle, initialData);

        if (!result) {
            this.resetDraggedLesson();
            return;
        }

        let classroom = result.classroom || result.assignments?.[0];
        if (result.assignments && result.assignments.length > 0) {
            classroom = {
                id: result.assignments[0].classroom_id,
                name: result.assignments[0].classroom_name,
                exam_size: result.assignments[0].classroom_exam_size,
                size: result.assignments[0].classroom_size
            };
        }

        let hours = result.hours;

        try {
            // 1. Çakışma Kontrolü (Client-side)
            await this.checkCrash(hours, classroom);

            // 2. Yeni öğeleri oluştur
            const newItems = this.generateScheduleItems(result, classroom);

            // 3. Silinecek eski öğeyi DTO verisi olarak hazırla
            const deletedItems = [itemData];

            // 4. Taşıma/güncelleme (Silme + Ekleme tek transaction backend)
            let moveResult = await this.moveScheduleItems(newItems, deletedItems);
            if (moveResult) {
                new Toast().prepareToast("Başarılı", "Sınav bilgileri güncellendi.", "success");
                await this.refreshScheduleCard();
            }
        } catch (errorMessage) {
            new Toast().prepareToast("Hata", errorMessage, "danger");
        } finally {
            this.resetDraggedLesson();
        }
    }

    /**
     * Sınav atama modalını açar (Çoklu derslik ve gözetmen seçimi).
     * initialData verilirse mevcut verileri doldurur (düzenleme modu).
     */
    async openAssignmentModal(title = "Sınav Atama ve Derslik Seçimi", initialData = null) {
        return new Promise((resolve, reject) => {
            let scheduleModal = new Modal();
            let initialHours = initialData ? (parseInt(initialData.hours) || 2) : 2;
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
                           <small><strong>Toplam Kapasite:</strong> <span id="total-capacity">0</span></small><br>
                           <small class="text-danger d-none" id="remaining-info"><strong>Kalan Mevcut:</strong> <span id="remaining-count">0</span></small>
                        </div>
                    </div>
                </div>
                <div id="classroom-observer-rows" class="mb-2">
                    <!-- Satırlar buraya gelecek -->
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="add-row-btn">
                        <i class="bi bi-plus-circle me-1"></i>Yeni Derslik/Gözetmen Ekle
                    </button>
                    <div class="form-check d-none" id="ignore-remaining-wrapper">
                        <input class="form-check-input" type="checkbox" id="ignore-remaining-check">
                        <label class="form-check-label small" for="ignore-remaining-check">
                            <i class="bi bi-exclamation-triangle me-1 text-danger"></i>Kalan mevcudu yok say
                        </label>
                    </div>
                </div>
            </form>`;
            
            const startTime = this.draggedLesson.end_element.dataset.startTime;
            const dayIndex = this.draggedLesson.end_element.dataset.dayIndex;
            const days = ['Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi', 'Pazar'];
            const emptySlot = this.draggedLesson.end_element.querySelector('.empty-slot');
            const dateText = emptySlot && emptySlot.dataset.date ? ` (${emptySlot.dataset.date})` : "";
            
            if (!initialData) {
                title = `${this.draggedLesson.lesson_name} - ${days[dayIndex]}${dateText} - ${startTime}`;
            }
            scheduleModal.prepareModal(title, modalContentHTML, true, false, "lg");
            scheduleModal.showModal();

            let rowsContainer = scheduleModal.body.querySelector("#classroom-observer-rows");
            let addRowBtn = scheduleModal.body.querySelector("#add-row-btn");
            let hoursInput = scheduleModal.body.querySelector("#selected_hours");
            let totalCapacitySpan = scheduleModal.body.querySelector("#total-capacity");
            let remainingInfo = scheduleModal.body.querySelector("#remaining-info");
            let remainingCount = scheduleModal.body.querySelector("#remaining-count");
            let ignoreRemainingWrapper = scheduleModal.body.querySelector("#ignore-remaining-wrapper");
            let ignoreRemainingCheck = scheduleModal.body.querySelector("#ignore-remaining-check");

            if (initialData && initialData.ignore_remaining) {
                ignoreRemainingCheck.checked = true;
            }

            const updateCapacity = () => {
                let total = 0;
                rowsContainer.querySelectorAll(".classroom-select").forEach(select => {
                    let option = select.selectedOptions[0];
                    if (option && option.dataset.examSize) {
                        total += parseInt(option.dataset.examSize);
                    }
                });
                totalCapacitySpan.innerText = total;
                let remaining = lessonSize - total;
                if (total < lessonSize) {
                    totalCapacitySpan.classList.add("text-danger");
                    remainingInfo.classList.remove("d-none");
                    remainingCount.innerText = remaining;
                    ignoreRemainingWrapper.classList.remove("d-none");
                } else {
                    totalCapacitySpan.classList.remove("text-danger");
                    remainingInfo.classList.add("d-none");
                    ignoreRemainingWrapper.classList.add("d-none");
                    ignoreRemainingCheck.checked = false;
                }
            };

            // TomSelect instance'larını takip etmek için
            const tomSelectInstances = new Map();

            const updateOptionsVisibility = () => {
                const classroomSelects = Array.from(rowsContainer.querySelectorAll(".classroom-select"));
                const observerSelects = Array.from(rowsContainer.querySelectorAll(".observer-select"));
                
                const selectedClassrooms = classroomSelects.map(s => s.value).filter(Boolean);
                
                // Tüm satırlarda seçilmiş gözetmen ID'lerini topla
                const allSelectedObservers = [];
                observerSelects.forEach(select => {
                    const ts = tomSelectInstances.get(select);
                    let vals = [];
                    if (ts) {
                        const rawVal = ts.getValue();
                        vals = Array.isArray(rawVal) ? rawVal : (rawVal ? [rawVal] : []);
                    } else if (select && select.selectedOptions) {
                        vals = Array.from(select.selectedOptions).map(o => o.value);
                    }
                    vals.forEach(v => {
                        if (v !== null && v !== undefined && String(v).trim() !== "") {
                            allSelectedObservers.push(String(v));
                        }
                    });
                });

                // Derslik filtrelemesi
                classroomSelects.forEach(select => {
                    const currentValue = select.value;
                    if (select && select.options) {
                        Array.from(select.options).forEach(option => {
                            if (option.value && option.value !== currentValue && selectedClassrooms.includes(option.value)) {
                                option.classList.add('d-none');
                            } else if (option.value) {
                                option.classList.remove('d-none');
                            }
                        });
                    }
                });

                // Gözetmen filtrelemesi (başka satırda seçilen gözetmenleri gizle)
                observerSelects.forEach(select => {
                    const ts = tomSelectInstances.get(select);
                    if (!ts || !ts.options) return;
                    const rawVal = ts.getValue();
                    const currentVals = Array.isArray(rawVal) ? rawVal.map(String) : (rawVal ? [String(rawVal)] : []);

                    Object.keys(ts.options).forEach(optValue => {
                        if (optValue && !currentVals.includes(optValue) && allSelectedObservers.includes(optValue)) {
                            ts.getOption(optValue)?.classList.add('d-none');
                        } else if (optValue) {
                            ts.getOption(optValue)?.classList.remove('d-none');
                        }
                    });
                });
            };

            const initTomSelect = (selectElement, placeholder) => {
                const ts = new TomSelect(selectElement, {
                    placeholder: placeholder,
                    allowEmptyOption: false,
                    dropdownParent: 'body',
                    maxItems: null,
                });
                tomSelectInstances.set(selectElement, ts);
                return ts;
            };

            const addRow = async (isFirst = false, initialAssignment = null) => {
                let rowCount = rowsContainer.querySelectorAll(".row").length;
                let rowId = `row-${Date.now()}-${rowCount}`;
                let rowHTML = `
                <div class="row g-2 mb-2 align-items-end" id="${rowId}">
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Derslik</label>
                        <select class="form-select classroom-select" required></select>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label small mb-1">Gözetmen(ler)</label>
                        <select class="form-select observer-select" multiple required></select>
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

                const observerTS = initTomSelect(observerSelect, 'Gözetmen(ler) seçmek için yazmaya başlayın...');

                if (initialAssignment) {
                    if (initialAssignment.classroom_id) {
                        let opt = Array.from(classroomSelect.options).find(o => o.value == initialAssignment.classroom_id);
                        if (!opt) {
                            opt = document.createElement('option');
                            opt.value = initialAssignment.classroom_id;
                            opt.innerText = initialAssignment.classroom_name || 'Mevcut Derslik';
                            opt.dataset.examSize = initialAssignment.classroom_exam_size || 0;
                            classroomSelect.appendChild(opt);
                        }
                        classroomSelect.value = initialAssignment.classroom_id;
                    }
                    
                    if (Array.isArray(initialAssignment.observers) && initialAssignment.observers.length > 0) {
                        initialAssignment.observers.forEach(obs => {
                            if (obs.id) {
                                if (!observerTS.options[obs.id]) {
                                    observerTS.addOption({
                                        value: obs.id,
                                        text: obs.name || 'Mevcut Gözetmen'
                                    });
                                }
                                observerTS.addItem(obs.id);
                            }
                        });
                    } else if (initialAssignment.observer_id) {
                        if (!observerTS.options[initialAssignment.observer_id]) {
                            observerTS.addOption({
                                value: initialAssignment.observer_id,
                                text: initialAssignment.observer_name || 'Mevcut Gözetmen'
                            });
                        }
                        observerTS.addItem(initialAssignment.observer_id);
                    }
                } else if (isFirst && this.draggedLesson.lecturer_id) {
                    observerTS.addItem(this.draggedLesson.lecturer_id);
                }

                classroomSelect.addEventListener("change", () => {
                    updateCapacity();
                    updateOptionsVisibility();
                });
                observerTS.on("change", () => {
                    updateOptionsVisibility();
                });

                if (!isFirst) {
                    rowElement.querySelector(".remove-row-btn").addEventListener("click", () => {
                        const oTS = tomSelectInstances.get(observerSelect);
                        if (oTS) { oTS.destroy(); tomSelectInstances.delete(observerSelect); }
                        rowElement.remove();
                        updateCapacity();
                        updateOptionsVisibility();
                    });
                }
                updateCapacity();
                updateOptionsVisibility();
            };

            addRowBtn.addEventListener("click", () => addRow());
            if (initialData && Array.isArray(initialData.assignments) && initialData.assignments.length > 0) {
                initialData.assignments.forEach((asgn, index) => {
                    addRow(index === 0, asgn);
                });
            } else {
                addRow(true);
            }

            scheduleModal.modal.addEventListener("hidden.bs.modal", () => {
                resolve(null);
            });

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
                    let observerTS = tomSelectInstances.get(observerSelect);
                    
                    let rawObserverVal = observerTS ? observerTS.getValue() : Array.from(observerSelect.selectedOptions).map(o => o.value);
                    let selectedObserverIds = Array.isArray(rawObserverVal) ? rawObserverVal : (rawObserverVal ? [rawObserverVal] : []);
                    selectedObserverIds = selectedObserverIds.filter(v => v);

                    if (!classroomSelect.value || selectedObserverIds.length === 0) {
                        isValid = false;
                    } else {
                        let observersList = selectedObserverIds.map(id => {
                            let text = observerTS ? (observerTS.options[id]?.text || '') : '';
                            return {
                                id: parseInt(id),
                                name: text
                            };
                        });

                        selectedData.assignments.push({
                            classroom_id: parseInt(classroomSelect.value),
                            classroom_name: classroomSelect.selectedOptions[0].innerText.replace(/\s*\(.*\)$/, ""),
                            classroom_exam_size: parseInt(classroomSelect.selectedOptions[0].dataset.examSize || 0),
                            observers: observersList,
                            observer_id: observersList[0]?.id || null,
                            observer_name: observersList[0]?.name || ''
                        });
                    }
                });

                if (!isValid) {
                    console.error("Exam assignment error: Fields missing");
                    new Toast().prepareToast("Hata", "Lütfen tüm derslikleri ve en az birer gözetmeni seçin.", "warning");
                    return;
                }

                if (parseInt(totalCapacitySpan.innerText) < lessonSize) {
                    if (ignoreRemainingCheck.checked) {
                        // Kalan mevcudu yok say: detail'e ignore_remaining ekle
                        selectedData.ignore_remaining = true;
                    } else if (!confirm("Seçilen dersliklerin kapasitesi ders mevcudundan az. Devam etmek istiyor musunuz?")) {
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
                console.error("checkCrash error: Target cell missing"); reject("Hedef hücre bulunamadı.");
                return;
            }

            const targetDayIndex = parseInt(this.draggedLesson.end_element.dataset.dayIndex);
            const dropTable = this.draggedLesson.end_element.closest("table") || this.table;
            const startRowIndex = this.draggedLesson.end_element.closest("tr").rowIndex;

            const movingElements = new Set();
            if (this.draggedLesson?.HTMLElement) {
                movingElements.add(this.draggedLesson.HTMLElement);
            }
            if (this.selectedLessonElements && this.selectedLessonElements.size > 0) {
                this.selectedLessonElements.forEach(el => movingElements.add(el));
            }
            const movingScheduleItemId = this.draggedLesson?.schedule_item_id || this.draggedLesson?.HTMLElement?.dataset?.scheduleItemId;

            for (let i = 0; checkedHours < parseInt(selectedHours); i++) {
                const currentRowIndex = startRowIndex + i;
                let row = dropTable.rows[currentRowIndex];
                if (!row) {
                    console.error("checkCrash: Row not found at index", currentRowIndex, "for day", targetDayIndex);
                    console.error("checkCrash error: Out of bounds"); reject("Eklenen sınav saatleri programın dışına taşıyor.");
                    return;
                }

                // Sadece ilgili güne ait hücreyi bul
                let cell = Array.from(row.cells).find(c => c.dataset.dayIndex == targetDayIndex);

                // Eğer ilgili satırda hücre bulunamadıysa (üst satırlardaki rowspan nedeniyle atlanmış olabilir)
                if (!cell) {
                    for (let r = currentRowIndex - 1; r >= 0; r--) {
                        let prevRow = dropTable.rows[r];
                        if (!prevRow) continue;
                        let prevCell = Array.from(prevRow.cells).find(c => c.dataset.dayIndex == targetDayIndex);
                        if (prevCell && prevCell.rowSpan) {
                            let span = parseInt(prevCell.rowSpan);
                            if (r + span > currentRowIndex) {
                                cell = prevCell;
                                break;
                            }
                        }
                    }
                }

                if (!cell) {
                    console.error("checkCrash: Cell not found for targetDayIndex", targetDayIndex, "in row", currentRowIndex, ". Available dayIndexes:", Array.from(row.cells).map(c => c.dataset.dayIndex));
                    console.error("checkCrash error: Table structure inconsistent"); reject("Hücre bulunamadı (Tablo yapısı tutarsız).");
                    return;
                }

                if (!cell.classList.contains("drop-zone") || cell.querySelector('.slot-unavailable')) {
                    if (cell.style.display === 'none') {
                        console.error("checkCrash: Intersection with another rowspan exam at row", startRowIndex + i, "day", targetDayIndex, "Cell:", cell);
                        console.error("checkCrash error: Rowspan intersection"); reject("Bu saatte başka bir sınav planlanmış.");
                    } else {
                        console.error("checkCrash: Slot not suitable (not drop-zone or unavailable) at row", startRowIndex + i, "day", targetDayIndex, "Cell:", cell);
                        console.error("checkCrash error: Slot not suitable"); reject("Seçilen zaman aralığında uygun olmayan saatler (Müsait değil/Kısıtlı) var.");
                    }
                    return;
                }

                let lessons = cell.querySelectorAll('.lesson-card');
                if (lessons.length !== 0) {
                    for (let existingLesson of lessons) {
                        // Taşınan dersin kendisini çakışma kontrolünden muaf tut
                        if (movingElements.has(existingLesson)) {
                            continue;
                        }
                        const existItemId = existingLesson.dataset.scheduleItemId;
                        if (movingScheduleItemId && existItemId && String(existItemId) === String(movingScheduleItemId)) {
                            continue;
                        }
                        if (this.selectedScheduleItemIds && existItemId && this.selectedScheduleItemIds.has(existItemId)) {
                            continue;
                        }

                        const existCode = existingLesson.getAttribute("data-lesson-code");
                        const existClassroomId = existingLesson.getAttribute("data-classroom-id");
                        const existLecturerId = existingLesson.getAttribute("data-lecturer-id");

                        if (existCode) {
                            let existMatch = existCode.match(/^(.+)\.(\d+)$/);
                            let currentMatch = newLessonCode ? newLessonCode.match(/^(.+)\.(\d+)$/) : null;
                            let existBase = existMatch ? existMatch[1] : existCode;
                            let currentBase = currentMatch ? currentMatch[1] : newLessonCode;

                            if (existBase !== currentBase) {
                                console.warn("checkCrash: Base lesson mismatch (may be exam combined)", existBase, currentBase, "at row", startRowIndex + i, "day", targetDayIndex, "Existing lesson:", existingLesson);
                            }
                        }

                        if (existClassroomId && newClassroomId && existClassroomId == newClassroomId) {
                            console.error("checkCrash: Classroom conflict", existClassroomId, "at row", startRowIndex + i, "day", targetDayIndex, "Existing lesson:", existingLesson);
                            console.error("checkCrash error: Classroom conflict"); reject("Aynı derslikte aynı saatte birden fazla sınav olamaz.");
                            return;
                        }

                        if (existLecturerId && newLecturerId && existLecturerId == newLecturerId) {
                            console.error("checkCrash: Lecturer conflict", existLecturerId, "at row", startRowIndex + i, "day", targetDayIndex, "Existing lesson:", existingLesson);
                            console.error("checkCrash error: Lecturer conflict"); reject("Aynı gözetmen aynı saatte birden fazla sınavda görev alamaz.");
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
     * Sınav programındaki bir lesson-card elementinden veri çıkartır.
     * Sınav kurallarına uygun olarak hazırlanır (detail JSON ayrıştırma, gün indeksi, 
     * program/ders görünümünde lecturer_id ve classroom_id null yapılması vb.).
     */
    getLessonItemData(element) {
        if (!element) return null;
        const ds = element.dataset;
        const cell = element.closest('td');

        if (!cell) {
            console.warn("Element is not inside a table cell:", element);
            return null;
        }

        let detail = null;
        if (ds.detail) {
            try {
                detail = JSON.parse(ds.detail);
            } catch (e) {
                console.error("Exam detail parse error:", e);
            }
        }

        let lecturerId = ds.lecturerId ? parseInt(ds.lecturerId) : null;
        let classroomId = ds.classroomId ? parseInt(ds.classroomId) : null;

        // Sınav programı için veri süzme (Program/Ders görünümünde hoca ve derslik null olmalı)
        if (this.owner_type === 'program' || this.owner_type === 'lesson') {
            lecturerId = null;
            classroomId = null;
        }

        const dayIndex = cell.dataset.dayIndex !== undefined ? parseInt(cell.dataset.dayIndex) : null;
        const table = cell.closest('table') || this.table;

        return {
            id: ds.scheduleItemId ? parseInt(ds.scheduleItemId) : null,
            schedule_id: parseInt(this.id),
            day_index: dayIndex,
            week_index: parseInt(table?.dataset?.weekIndex || 0),
            start_time: cell.dataset.startTime,
            end_time: cell.dataset.endTime,
            status: ds.status || (parseInt(ds.groupNo) > 0 ? "group" : "single"),
            data: [
                {
                    lesson_id: ds.lessonId ? parseInt(ds.lessonId) : null,
                    lecturer_id: lecturerId,
                    classroom_id: classroomId
                }
            ],
            detail: detail
        };
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
                    console.error("Exam save API error:", data.msg); new Toast().prepareToast("Hata", data.msg, "danger")
                    return false;
                } else {
                    return data.createdIds || true;
                }
            })
            .catch((error) => {
                console.error("Exam saveScheduleItems error:", error);
                console.error("Exam save system error:", error); new Toast().prepareToast("Hata", "Sistem hatası!", "danger");
                return false;
            });
    }

    async moveScheduleItems(scheduleItems, deletedItems) {
        let data = new FormData();
        data.append('items', JSON.stringify(scheduleItems));
        data.append('deleted_items', JSON.stringify(deletedItems));

        return fetch("/ajax/moveExamScheduleItems", {
            method: "POST",
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: data
        })
            .then(response => response.json())
            .then((data) => {
                if (data.status === "error") {
                    console.error("moveExamScheduleItems API hatası:", data.msg);
                    new Toast().prepareToast("Hata", data.msg, "danger")
                    return false;
                } else {
                    return data.createdIds || true;
                }
            })
            .catch((error) => {
                console.error("moveExamScheduleItems sistem hatası:", error);
                new Toast().prepareToast("Hata", "Sistem hatası!", "danger");
                return false;
            });
    }

    /**
     * Sınav programı öğelerini silme API çağrısı.
     * Ders programı ile aynı endpoint kullanılır: /ajax/deleteScheduleItems
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

    /**
     * Çoklu tablo (haftalık yapı) desteği için generateScheduleItems metodunu override eder.
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
            detail: input.assignments ? { assignments: input.assignments, ...(input.ignore_remaining ? { ignore_remaining: true } : {}) } : null
        }];

        const targetDayIndex = parseInt(this.draggedLesson.end_element.dataset.dayIndex);
        const targetTable = this.draggedLesson.end_element.closest('table');

        itemsToProcess.forEach(itemInfo => {
            let hoursNeeded = itemInfo.hours || 1;
            let startCell = this.draggedLesson.end_element;
            let startTime = startCell.dataset.startTime;
            let slotDuration = this.lessonHourToMinute(1) + this.breakDuration;
            let totalMinutes = hoursNeeded * slotDuration;
            let endTime = this.addMinutes(startTime, totalMinutes);

            scheduleItems.push({
                'id': null,
                'schedule_id': parseInt(this.id),
                'day_index': targetDayIndex,
                'week_index': parseInt(targetTable?.dataset?.weekIndex || 0),
                'start_time': startTime,
                'end_time': endTime,
                'status': itemInfo.status,
                'data': Array.isArray(itemInfo.data) ? itemInfo.data : [itemInfo.data],
                'detail': itemInfo.detail || null
            });
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
            let programData = null

            switch (this.owner_type) {
                case 'user': {
                    const [programRes] = await Promise.all([
                        fetch("/ajax/checkProgramSchedule", { method: "POST", headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: data })
                    ]);
                    programData = await programRes.json();
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
                            const targetDay = parseInt(colKey, 10) - 1;
                            const cell = Array.from(this.table.rows[r].cells).find(c => parseInt(c.dataset.dayIndex) === targetDay);
                            if (cell) {
                                const emptySlot = cell.querySelector('.empty-slot');
                                if (emptySlot) emptySlot.classList.add(...classes);
                            }
                        });
                    }
                });
            };

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

    /**
     * Sınav programında tablodan tabloya taşıma işlemini gerçekleştirir.
     */
    async dropTableToTable(isBulk = false) {
        let itemsToMove = [], classroom = null, totalHours = 0, itemsToDelete = [], detailedItems = [];

        const elements = (isBulk && this.selectedLessonElements.size > 0) ?
            Array.from(this.selectedLessonElements).sort((a, b) => a.closest('tr').rowIndex - b.closest('tr').rowIndex) :
            [this.draggedLesson.HTMLElement];

        elements.forEach(el => {
            const data = this.getLessonItemData(el);
            if (data) {
                // Sınav süresini (slot sayısını) hesapla
                const hours = this.getDurationInHours(data.start_time, data.end_time) || 1;

                // Atama detaylarını (gözetmen/derslik) dataset'ten al
                let detail = null;
                try {
                    detail = el.dataset.detail ? JSON.parse(el.dataset.detail) : null;
                } catch (e) {
                    console.error("Exam detail parse error:", e);
                }

                itemsToMove.push({ element: el, data: data });
                itemsToDelete.push(data);
                totalHours += hours;

                detailedItems.push({
                    hours,
                    data: data.data,
                    status: data.status,
                    originalElement: el,
                    detail: detail // Taşıma sırasında atamaları koru
                });

                if (!classroom) {
                    classroom = {
                        id: el.dataset.classroomId,
                        name: el.querySelector('.lesson-classroom')?.innerText || "",
                        size: el.dataset.classroomSize,
                        exam_size: el.dataset.classroomExamSize
                    };
                }
            }
        });

        if (itemsToMove.length === 0) return;

        try {
            // 1. Çakışma Kontrolü (Client-side)
            await this.checkCrash(totalHours, classroom);

            // 2. Yeni öğeleri oluştur
            const newItems = this.generateScheduleItems(detailedItems, classroom);

            // 3. Yeni öğeleri kaydet (Taşıma işlemi için özel uç noktayı kullan)
            let moveResult = await this.moveScheduleItems(newItems, itemsToDelete);
            if (moveResult) {
                // 4. UI Güncelleme
                await this.refreshScheduleCard();
            }
        } catch (errorMessage) {
            new Toast().prepareToast("Hata", errorMessage, "danger");
        }
        this.resetDraggedLesson();
    }
}
