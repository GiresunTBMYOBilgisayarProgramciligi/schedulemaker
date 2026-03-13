/**
 * Tekli program sayfalarında (Hoca, Ders, Derslik) Preferred ve Unavailable slotlarını yönetir.
 * ScheduleCard.js'in basitleştirilmiş bir versiyonudur.
 */
class SingleScheduleHandler {
    constructor() {
        this.draggedLesson = null;
        this.selectedLessonElements = new Set();
        this.selectedScheduleItemIds = new Set();
        this.ScheduleCard = null;
        this.modal = new Modal();
    }

    bindToCard(cardInstance) {
        if (this.ScheduleCard && this.ScheduleCard.id === cardInstance.id) {
            console.log(`[SingleScheduleHandler] Already bound to Card: ${cardInstance.id}. Updating reference.`);
            this.ScheduleCard = cardInstance;
            return;
        }

        this.ScheduleCard = cardInstance;
        console.log(`[SingleScheduleHandler] Binding to Card: ${cardInstance.id}, Type: ${cardInstance.type}`);

        this.initDraggableItems();
        this.initDropZones();
        this.initBulkSelection();
    }

    initDraggableItems() {
        const container = this.ScheduleCard.card;
        if (container.dataset.draggableInitialized) return;
        container.dataset.draggableInitialized = 'true';

        container.addEventListener('dragstart', (e) => {
            const dragTarget = e.target.closest('.slot-preferred') ||
                e.target.closest('.slot-unavailable');

            if (dragTarget) {
                console.debug("SingleScheduleHandler.dragStart", e);
                this.draggedLesson = dragTarget;
                e.dataTransfer.setData('text/plain', dragTarget.id);
                dragTarget.classList.add('dragging');
            }
        });

        container.addEventListener('dragend', (e) => {
            const dragTarget = e.target.closest('.slot-preferred') ||
                e.target.closest('.slot-unavailable');

            if (dragTarget) {
                console.debug("SingleScheduleHandler.dragEnd", e);
                dragTarget.classList.remove('dragging');
                this.draggedLesson = null;
            }
        });
    }

    initDropZones() {
        const container = this.ScheduleCard.card;
        if (container.dataset.dropZonesInitialized) return;
        container.dataset.dropZonesInitialized = 'true';

        container.addEventListener('dragover', (e) => {
            const dropZone = e.target.closest('.drop-zone');
            if (dropZone) {
                e.preventDefault();
                dropZone.classList.add('drag-over');
            }
        });

        container.addEventListener('dragleave', (e) => {
            const dropZone = e.target.closest('.drop-zone');
            if (dropZone) {
                dropZone.classList.remove('drag-over');
            }
        });

        container.addEventListener('drop', async (e) => {
            const dropZone = e.target.closest('.drop-zone');
            if (dropZone && this.draggedLesson) {
                e.preventDefault();
                e.stopPropagation(); // Card'ın kendi dropHandler'ına gitmesini engelle (eğer o da bir şekilde bağlıysa)
                dropZone.classList.remove('drag-over');

                // Eğer tabloya (schedule-table) bırakıldıysa
                if (dropZone.closest('.schedule-table')) {
                    await this.handleTableDrop(dropZone);
                }
                // Eğer silme alanına (available-schedule-items) bırakıldıysa
                else if (dropZone.closest('.available-schedule-items')) {
                    await this.handleDeleteDrop();
                }
            }
        });
    }

    async handleTableDrop(dropZone) {
        const lessonData = this.draggedLesson.dataset;
        const status = lessonData.status;
        const fromTable = !!lessonData.scheduleItemId;

        if (fromTable) {
            // Taşıma mantığı
            await this.handleTableMove(dropZone);
            return;
        }

        const cardInstance = this.ScheduleCard;

        const scheduleType = cardInstance?.type || 'lesson';
        let maxHours = ['midterm-exam', 'final-exam', 'makeup-exam'].includes(scheduleType) ? 18 : 8;

        // Modal oluştur
        this.modal.modal.id = `singleScheduleModal-${this.ScheduleCard.id}`;

        const formHtml = `
            <form id="singleScheduleForm">
                <div class="mb-3">
                    <label class="form-label" for="modalHours">Süre (Saat)</label>
                    <input type="number" class="form-control" name="hours" id="modalHours" min="1" max="${maxHours}" value="1">
                </div>
                <div class="mb-3">
                    <label class="form-label">Açıklama</label>
                    <textarea class="form-control" name="description" id="modalDescription" rows="3" placeholder="Açıklama giriniz..."></textarea>
                    <div class="invalid-feedback">"Müsait Değil" durumu için açıklama girmek zorunludur.</div>
                </div>
            </form>
        `;

        this.modal.prepareModal("Program Öğesi Ekle", formHtml, true, true, "md");
        this.modal.confirmButton.textContent = "Kaydet";

        // Enter tuşu desteği
        this.modal.modal.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                if (e.target.tagName === 'TEXTAREA') return;
                e.preventDefault();
                e.stopPropagation();
                this.modal.confirmButton.click();
            }
        });

        // Focus ayarı
        this.modal.modal.addEventListener('shown.bs.modal', () => {
            setTimeout(() => {
                const input = this.modal.modal.querySelector('#modalHours');
                if (input) input.focus();
            }, 100);
        });

        this.modal.showModal();

        return new Promise((resolve) => {
            const handler = async () => {
                const hoursInput = this.modal.modal.querySelector('#modalHours');
                const descInput = this.modal.modal.querySelector('#modalDescription');
                const hours = parseInt(hoursInput.value);
                const description = descInput.value;

                if (status === 'unavailable' && !description.trim()) {
                    descInput.classList.add('is-invalid');
                    return;
                }

                this.modal.confirmButton.removeEventListener('click', handler);
                this.modal.closeModal();

                const scheduleData = this.prepareScheduleData(dropZone, hours, description, lessonData);
                await this.saveItem(scheduleData, this.ScheduleCard.card);
                resolve();
            };
            this.modal.confirmButton.addEventListener('click', handler);

            this.modal.modal.addEventListener('hidden.bs.modal', () => {
                this.modal.confirmButton.removeEventListener('click', handler);
            }, { once: true });
        });
    }

    initBulkSelection() {
        const container = this.ScheduleCard.card;
        if (container.dataset.bulkSelectionInitialized) return;
        container.dataset.bulkSelectionInitialized = 'true';

        // Checkbox değişimlerini dinle
        container.addEventListener('change', (event) => {
            if (event.target.classList.contains('lesson-bulk-checkbox')) {
                const checkbox = event.target;
                const slot = checkbox.closest('.empty-slot') || checkbox.closest('.lesson-card');
                if (slot) this.updateSelectionState(slot, checkbox.checked);
            }
        });

        // Kart tıklamalarını dinle (Tek ve Çift Tıklama)
        container.addEventListener('click', (event) => {
            const slot = event.target.closest('.empty-slot') || event.target.closest('.lesson-card');
            if (!slot) return;

            // Linklere veya checkbox'ın kendisine tıklandıysa işlemi tarayıcıya bırak
            if (event.target.tagName === 'A' || event.target.classList.contains('lesson-bulk-checkbox')) {
                return;
            }

            const checkbox = slot.querySelector('.lesson-bulk-checkbox');
            if (checkbox) {
                checkbox.checked = !checkbox.checked;
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        container.addEventListener('dblclick', (event) => {
            const slot = event.target.closest('.empty-slot') || event.target.closest('.lesson-card');
            if (!slot) return;

            const status = slot.dataset.status;
            if (!status || !['preferred', 'unavailable'].includes(status)) return;

            const cell = slot.closest('td');
            if (!cell) return;

            const cellIndex = cell.cellIndex; // Günü temsil eder
            const table = container.querySelector('table.schedule-table');
            if (!table) return;

            // Aynı GÜN içindeki aynı STATUS'e sahip tüm slotları seç
            const sameDaySlots = table.querySelectorAll(`tr td:nth-child(${cellIndex + 1}) .empty-slot[data-status="${status}"]`);

            sameDaySlots.forEach(s => {
                const cb = s.querySelector('.lesson-bulk-checkbox');
                if (cb && !cb.checked) {
                    cb.checked = true;
                    cb.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });

            window.getSelection().removeAllRanges();
        });
    }

    updateSelectionState(element, isSelected) {
        const scheduleItemId = element.dataset.scheduleItemId;
        if (isSelected) {
            element.classList.add('selected-lesson');
            this.selectedLessonElements.add(element);
            if (scheduleItemId) this.selectedScheduleItemIds.add(scheduleItemId);
        } else {
            element.classList.remove('selected-lesson');
            this.selectedLessonElements.delete(element);
            if (scheduleItemId) this.selectedScheduleItemIds.delete(scheduleItemId);
        }
        console.log("Selected IDs:", Array.from(this.selectedScheduleItemIds));
    }

    async handleTableMove(dropZone) {
        if (!dropZone) return;

        console.log("handleTableMove started");
        let itemsToMove = [];
        let itemsToDelete = [];

        // 1. Taşınacak öğeleri belirle ve sırala
        if (this.selectedLessonElements.size > 0) {
            const sortedElements = Array.from(this.selectedLessonElements).sort((a, b) => {
                const rowA = a.closest('tr').rowIndex;
                const rowB = b.closest('tr').rowIndex;
                return rowA - rowB;
            });

            sortedElements.forEach(el => {
                const data = this.getSlotItemData(el);
                if (data && data.id) {
                    itemsToMove.push({ element: el, data: data });
                    itemsToDelete.push(data);
                }
            });
        } else {
            const data = this.getSlotItemData(this.draggedLesson);
            if (data && data.id) {
                itemsToMove.push({ element: this.draggedLesson, data: data });
                itemsToDelete.push(data);
            }
        }

        if (itemsToMove.length === 0) {
            console.warn("No items found to move");
            return;
        }

        const firstItem = itemsToMove[0];
        const firstStartTime = firstItem.data.start_time;
        const newItems = [];
        const scheduleCard = dropZone.closest('.schedule-card');
        if (!scheduleCard) {
            new Toast().prepareToast("Hata", "Program kartı bulunamadı.", "danger");
            return;
        }

        const scheduleId = scheduleCard.dataset.scheduleId;
        const targetDayIndex = dropZone.cellIndex - 1;
        const targetStartTime = dropZone.dataset.startTime;

        // 2. Yeni konumları ve verileri hazırla (Bağıl ofsetleri koru)
        itemsToMove.forEach(item => {
            const originalData = item.data;
            const duration = this.getDurationInMinutes(originalData.start_time, originalData.end_time);
            const offset = this.getDurationInMinutes(firstStartTime, originalData.start_time);
            const newItemStartTime = this.addMinutes(targetStartTime, offset);

            newItems.push({
                schedule_id: scheduleId,
                day_index: targetDayIndex,
                start_time: newItemStartTime,
                end_time: this.addMinutes(newItemStartTime, duration),
                status: originalData.status,
                data: null,
                detail: originalData.detail
            });
        });

        console.log("Planned new items:", newItems);

        try {
            // 3. Önce silme işlemini yap
            const deleteFormData = new FormData();
            deleteFormData.append('items', JSON.stringify(itemsToDelete));

            const deleteResponse = await fetch('/ajax/deleteScheduleItems', {
                method: 'POST',
                body: deleteFormData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            const deleteResult = await deleteResponse.json();
            if (deleteResult.status !== 'success') {
                new Toast().prepareToast("Hata", "Eski öğeler silinemedi: " + deleteResult.msg, "danger");
                return;
            }

            // UI'dan eskileri sil ve parçalananları (split) göster
            // YENİ YAPI: Eskisi gibi manuel DOM manipülasyonu yerine refreshScheduleCard kullanacağız. 
            // Fakat burada silme + kaydetme ardışık yapıldığı için refresh'i saveItem sonrasına bırakıyoruz.

            // 4. Silme başarılıysa yeni öğeleri (taşınan kısmı) kaydet
            const saveResult = await this.saveItem(newItems, dropZone.closest('.schedule-card'), true);
            if (saveResult && saveResult.status === 'success') {
                this.clearSelection();
                new Toast().prepareToast("Başarılı", "Öğeler başarıyla taşındı.", "success");
            } else {
                // Kayıt başarısızsa bilgi ver (Silindi ama kaydedilemedi durumu)
                console.error("Save failed after delete:", saveResult);
                new Toast().prepareToast("Hata", "Öğeler silindi ancak yeni konuma kaydedilemedi. Sayfayı yenileyerek durumu kontrol edin.", "warning");
                await this.refreshScheduleCard();
            }

        } catch (error) {
            console.error("Move Error:", error);
            new Toast().prepareToast("Hata", "Taşıma işlemi sırasında teknik bir hata oluştu: " + error.message, "danger");
        }
    }

    getDurationInMinutes(startTime, endTime) {
        const [sh, sm] = startTime.split(':').map(Number);
        const [eh, em] = endTime.split(':').map(Number);
        return (eh * 60 + em) - (sh * 60 + sm);
    }

    addMinutes(timeStr, minutes) {
        const [h, m] = timeStr.split(':').map(Number);
        const total = h * 60 + m + minutes;
        const nh = Math.floor(total / 60);
        const nm = total % 60;
        return `${nh.toString().padStart(2, '0')}:${nm.toString().padStart(2, '0')}`;
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

    prepareScheduleData(dropZone, hours, description, lessonData) {
        const scheduleCard = this.ScheduleCard.card;
        if (!scheduleCard) return [];

        const table = scheduleCard.querySelector('table.schedule-table');
        if (!table) return [];

        const scheduleId = this.ScheduleCard.id;
        const colIndex = dropZone.cellIndex;
        const startRowIndex = dropZone.closest('tr').rowIndex;
        const isDummy = ['preferred', 'unavailable'].includes(lessonData.status);

        let scheduleItems = [];
        let currentItem = null;
        let addedHours = 0;
        let currentRowOffset = 0;

        while (addedHours < hours) {
            const rowIndex = startRowIndex + currentRowOffset;
            if (rowIndex >= table.rows.length) break;

            const row = table.rows[rowIndex];
            const cell = row.cells[colIndex];

            // Hücre uygunluğu: Drop-zone olmalı ve içinde 'unavailable' (öğle arası dahil) olmamalı
            // NOT: createDummySlotHTML içindeki .slot-unavailable sınıfı burada belirleyici
            const isValid = cell && (cell.classList.contains('drop-zone') || cell.querySelector('.empty-slot')) && !cell.querySelector('.slot-unavailable');

            if (isValid) {
                if (!currentItem) {
                    currentItem = {
                        schedule_id: scheduleId,
                        day_index: colIndex - 1,
                        start_time: cell.dataset.startTime,
                        end_time: cell.dataset.endTime,
                        status: lessonData.status,
                        data: isDummy ? null : [{
                            lesson_id: lessonData.lessonId,
                            lecturer_id: lessonData.lecturerId,
                            classroom_id: lessonData.classroomId || 0
                        }],
                        detail: description && description.trim() !== "" ? { description: description } : null
                    };
                } else {
                    currentItem.end_time = cell.dataset.endTime;
                }
                addedHours++;
            } else {
                // Geçersiz hücre (Atla ve gerekirse mevcut öğeyi kaydet)
                if (currentItem) {
                    scheduleItems.push(currentItem);
                    currentItem = null;
                }
            }
            currentRowOffset++;
        }

        if (currentItem) {
            scheduleItems.push(currentItem);
        }

        return scheduleItems;
    }

    /**
     * Bir slot veya kart elementinden silme verilerini hazırlar
     */
    getSlotItemData(element) {
        if (!element) return null;
        const ds = element.dataset;
        const cell = element.closest('td');
        if (!cell) return null;

        let detail = ds.detail;
        if (typeof detail === 'string') {
            try {
                detail = JSON.parse(detail);
            } catch (e) {
                detail = null;
            }
        }

        return {
            id: ds.scheduleItemId,
            start_time: cell.dataset.startTime,
            end_time: cell.dataset.endTime,
            status: ds.status,
            data: null,
            detail: detail
        };
    }

    async saveItem(items, targetCardElement = null, autoRefresh = true) {
        try {
            const formData = new FormData();
            formData.append('items', JSON.stringify(items));

            const response = await fetch('/ajax/saveScheduleItem', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            const result = await response.json();

            if (result.status === 'success') {
                if (autoRefresh) {
                    if (targetCardElement) {
                        await this.refreshScheduleCard(targetCardElement);
                    } else {
                        // Eğer belirli bir kart gelmediyse tüm kartları yenile. 
                        // Genelde taşıma - silme işlemlerinde etkilenen tüm verileri garantilemek için.
                        for (const card of window.scheduleCards) {
                            await card.refreshScheduleCard();
                        }
                        this.initBulkSelection();
                    }
                }
                return result;
            } else {
                new Toast().prepareToast("Hata", result.msg, "danger");
            }
        } catch (error) {
            console.error("Save Error:", error);
            new Toast().prepareToast("Hata", "Sistem hatası oluştu.", "danger");
        }
    }

    async handleDeleteDrop() {
        const itemsToDelete = [];

        if (this.selectedLessonElements.size > 0) {
            this.selectedLessonElements.forEach(el => {
                const data = this.getSlotItemData(el);
                if (data && data.id) itemsToDelete.push(data);
            });
        } else {
            const data = this.getSlotItemData(this.draggedLesson);
            if (data && data.id) itemsToDelete.push(data);
        }

        if (itemsToDelete.length === 0) return;

        const message = itemsToDelete.length > 1
            ? `${itemsToDelete.length} adet öğeyi silmek istediğinize emin misiniz?`
            : "Bu öğeyi silmek istediğinize emin misiniz?";

        // Modal oluştur
        this.modal.modal.id = `deleteConfirmModal-${this.ScheduleCard.id}`;

        this.modal.prepareModal("Silme Onayı", `<p id="deleteModalMessage">${message}</p>`, true, true, "sm");
        this.modal.confirmButton.textContent = "Sil";
        this.modal.confirmButton.classList.replace('btn-success', 'btn-danger');

        this.modal.showModal();

        return new Promise((resolve) => {
            const handler = async () => {
                this.modal.confirmButton.removeEventListener('click', handler);
                this.modal.closeModal();

                try {
                    const formData = new FormData();
                    formData.append('items', JSON.stringify(itemsToDelete));

                    const response = await fetch('/ajax/deleteScheduleItems', {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });

                    const result = await response.json();
                    if (result.status === 'success') {
                        // Tüm kartları yeniliyoruz çünkü silinen öğe herhangi bir karta veya birden fazla karta ait olabilir.
                        for (const card of window.scheduleCards) {
                            await card.refreshScheduleCard();
                        }

                        this.clearSelection();
                        new Toast().prepareToast("Başarılı", "Silme işlemi tamamlandı.", "success");
                    } else {
                        new Toast().prepareToast("Hata", result.msg, "danger");
                    }
                } catch (error) {
                    console.error("Delete Error:", error);
                    new Toast().prepareToast("Hata", "Silme işlemi sırasında hata oluştu.", "danger");
                }
                resolve();
            };
            this.modal.confirmButton.addEventListener('click', handler);

            this.modal.modal.addEventListener('hidden.bs.modal', () => {
                this.modal.confirmButton.removeEventListener('click', handler);
            }, { once: true });
        });
    }

    async refreshScheduleCard(targetCardElement) {
        if (!targetCardElement) {
            for (const card of window.scheduleCards) {
                await card.refreshScheduleCard();
            }
            return;
        }

        const scheduleId = targetCardElement.dataset.scheduleId;
        const cardInstance = window.scheduleCards.find(c => c.id == scheduleId);

        if (cardInstance) {
            await cardInstance.refreshScheduleCard();
        } else {
            console.error("ScheduleCard instance is not available for refresh.");
        }
    }
}