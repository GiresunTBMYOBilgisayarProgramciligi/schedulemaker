/**
 * Tekli program sayfalarında (Hoca, Ders, Derslik) Preferred ve Unavailable slotlarını yönetir.
 * ScheduleCard.js'in basitleştirilmiş bir versiyonudur.
 */
class SingleScheduleHandler {
    constructor() {
        this.draggedLesson = null;
        this.selectedLessonElements = new Set();
        this.selectedScheduleItemIds = new Set();
        this.initialize();
    }

    initialize() {
        this.initDraggableItems();
        this.initDropZones();
        this.initDeleteZones();
        this.initModals();
        this.initBulkSelection();
        this.initPopovers();
        console.log("SingleScheduleHandler initialized");
    }

    initDraggableItems() {
        document.addEventListener('dragstart', (e) => {
            if (e.target.classList.contains('lesson-card') || e.target.classList.contains('slot-preferred') || e.target.classList.contains('slot-unavailable')) {
                this.draggedLesson = e.target;
                e.dataTransfer.setData('text/plain', e.target.id);
                e.target.classList.add('dragging');
            }
        });

        document.addEventListener('dragend', (e) => {
            if (e.target.classList.contains('lesson-card') || e.target.classList.contains('slot-preferred') || e.target.classList.contains('slot-unavailable')) {
                e.target.classList.remove('dragging');
                this.draggedLesson = null;
            }
        });
    }

    initDropZones() {
        document.addEventListener('dragover', (e) => {
            if (e.target.closest('.drop-zone')) {
                e.preventDefault();
                e.target.closest('.drop-zone').classList.add('drag-over');
            }
        });

        document.addEventListener('dragleave', (e) => {
            if (e.target.closest('.drop-zone')) {
                e.target.closest('.drop-zone').classList.remove('drag-over');
            }
        });

        document.addEventListener('drop', async (e) => {
            const dropZone = e.target.closest('.drop-zone');
            if (dropZone && this.draggedLesson) {
                e.preventDefault();
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

    initPopovers(container = document) {
        const popoverTriggerList = [].slice.call(container.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl, { trigger: 'hover' });
        });
    }

    initDeleteZones() {
        // available-schedule-items zaten drop-zone olarak işaretlendi
    }

    initModals() {
        // Bootstrap modal elementini oluştur (eğer yoksa)
        if (!document.getElementById('singleScheduleModal')) {
            const modalHtml = `
            <div class="modal fade" id="singleScheduleModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Program Öğesi Ekle</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="singleScheduleForm">
                                <div class="mb-3">
                                    <label class="form-label">Süre (Saat)</label>
                                    <input type="number" class="form-control" name="hours" id="modalHours" min="1" max="8">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Açıklama</label>
                                    <textarea class="form-control" name="description" id="modalDescription" rows="3" placeholder="Açıklama giriniz..."></textarea>
                                    <div class="invalid-feedback">Unavailable durumu için açıklama girmek zorunludur.</div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                            <button type="button" class="btn btn-primary" id="saveSingleItem">Kaydet</button>
                        </div>
                    </div>
                </div>
            </div>`;
            document.body.insertAdjacentHTML('beforeend', modalHtml);

            // Form submit davranışını engelle ve Enter tuşu ile kaydetmeyi sağla
            // Global Modal Keydown Handler
            // Form submit, Close button enter, vb. hepsini yakalar
            const modalEl = document.getElementById('singleScheduleModal');
            if (modalEl) {
                modalEl.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        // Textarea içinde Enter'a basılırsa yeni satıra geçsin, engelleme
                        if (e.target.tagName === 'TEXTAREA') return;

                        e.preventDefault();
                        e.stopPropagation();

                        // Eğer focus kapatma butonundaysa hiçbir şey yapma (kapanmasın)
                        if (e.target.classList.contains('btn-close')) {
                            // İstenirse burada focus inputa atılabilir
                            document.getElementById('modalHours').focus();
                            return;
                        }

                        // Diğer durumlarda (input vb.) kaydet butonunu tetikle
                        document.getElementById('saveSingleItem').click();
                    }
                });
            }
        }

        if (!document.getElementById('deleteConfirmModal')) {
            const deleteModalHtml = `
            <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Silme Onayı</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p id="deleteModalMessage">Seçili öğeleri silmek istediğinize emin misiniz?</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                            <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Sil</button>
                        </div>
                    </div>
                </div>
            </div>`;
            document.body.insertAdjacentHTML('beforeend', deleteModalHtml);
        }

        this.bsModal = new bootstrap.Modal(document.getElementById('singleScheduleModal'));
        this.deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));

        // Modal açıldığında inputa focuslan (timeout ile garantiye al)
        document.getElementById('singleScheduleModal').addEventListener('shown.bs.modal', function () {
            setTimeout(() => {
                const input = document.getElementById('modalHours');
                if (input) input.focus();
            }, 100);
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

        // Yeni ekleme (Modal aç)
        document.getElementById('modalDescription').value = '';
        document.getElementById('modalDescription').classList.remove('is-invalid');
        document.getElementById('modalHours').value = '1';

        this.bsModal.show();

        return new Promise((resolve) => {
            const saveBtn = document.getElementById('saveSingleItem');
            const handler = async () => {
                const hours = parseInt(document.getElementById('modalHours').value);
                const description = document.getElementById('modalDescription').value;

                if (status === 'unavailable' && !description.trim()) {
                    document.getElementById('modalDescription').classList.add('is-invalid');
                    return;
                }

                saveBtn.removeEventListener('click', handler);
                this.bsModal.hide();

                const scheduleData = this.prepareScheduleData(dropZone, hours, description, lessonData);
                await this.saveItem(scheduleData);
                resolve();
            };
            saveBtn.addEventListener('click', handler);

            document.getElementById('singleScheduleModal').addEventListener('hidden.bs.modal', () => {
                saveBtn.removeEventListener('click', handler);
            }, { once: true });
        });
    }

    initBulkSelection() {
        const table = document.querySelector('table.schedule-table');
        if (!table) return;

        // Checkbox değişimlerini dinle
        table.addEventListener('change', (event) => {
            if (event.target.classList.contains('lesson-bulk-checkbox')) {
                const checkbox = event.target;
                const slot = checkbox.closest('.empty-slot') || checkbox.closest('.lesson-card');
                this.updateSelectionState(slot, checkbox.checked);
            }
        });

        // Kart tıklamalarını dinle (Tek ve Çift Tıklama)
        table.addEventListener('click', (event) => {
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

        table.addEventListener('dblclick', (event) => {
            const slot = event.target.closest('.empty-slot') || event.target.closest('.lesson-card');
            if (!slot) return;

            const status = slot.dataset.status;
            if (!status || !['preferred', 'unavailable'].includes(status)) return;

            const cell = slot.closest('td');
            if (!cell) return;

            const cellIndex = cell.cellIndex; // Günü temsil eder

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
            // NOT: Önce sync, sonra clear yapılması split olan parçaların ID'si güncellendiği için 
            // clear tarafından yanlışlıkla silinmesini engeller.
            if (deleteResult.createdItems) this.syncTableItems(deleteResult.createdItems);
            this.clearTableItemsByIds(deleteResult.deletedIds || itemsToDelete.map(it => it.id));

            // 4. Silme başarılıysa yeni öğeleri (taşınan kısmı) kaydet
            const saveResult = await this.saveItem(newItems, false);
            if (saveResult && saveResult.status === 'success') {
                this.clearSelection();
                new Toast().prepareToast("Başarılı", "Öğeler başarıyla taşındı.", "success");
            } else {
                // Kayıt başarısızsa bilgi ver (Silindi ama kaydedilemedi durumu)
                console.error("Save failed after delete:", saveResult);
                new Toast().prepareToast("Hata", "Öğeler silindi ancak yeni konuma kaydedilemedi. Sayfayı yenileyerek durumu kontrol edin.", "warning");
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
        const scheduleCard = dropZone.closest('.schedule-card');
        if (!scheduleCard) return [];

        const table = scheduleCard.querySelector('table.schedule-table');
        if (!table) return [];

        const scheduleId = scheduleCard.dataset.scheduleId;
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
                        data: isDummy ? null : {
                            lesson_id: lessonData.lessonId,
                            lecturer_id: lessonData.lecturerId,
                            classroom_id: lessonData.classroomId || 0
                        },
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

    async saveItem(items) {
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
                // createdItems önceliklidir çünkü tam nesne verisi (day_index, timing vb.) içerir
                if (result.createdItems && Array.isArray(result.createdItems)) {
                    this.syncTableItems(result.createdItems);
                } else if (result.createdIds) {
                    // Eğer sadece ID dönmüşse (eskiden olduğu gibi), sync kısıtlı çalışır
                    this.syncTableItems(result.createdIds);
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
        document.getElementById('deleteModalMessage').textContent = message;

        this.deleteModal.show();

        return new Promise((resolve) => {
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            const handler = async () => {
                confirmBtn.removeEventListener('click', handler);
                this.deleteModal.hide();

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
                        // Önce parçaları senkronize et, sonra eskileri sil
                        if (result.createdItems) this.syncTableItems(result.createdItems);
                        this.clearTableItemsByIds(result.deletedIds || itemsToDelete.map(it => it.id));

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
            confirmBtn.addEventListener('click', handler);

            document.getElementById('deleteConfirmModal').addEventListener('hidden.bs.modal', () => {
                confirmBtn.removeEventListener('click', handler);
            }, { once: true });
        });
    }

    syncTableItems(items) {
        if (!items || !Array.isArray(items)) return;
        const scheduleCard = document.querySelector('.schedule-card');
        const table = scheduleCard ? scheduleCard.querySelector('table.schedule-table') : document.querySelector('table.schedule-table');
        if (!table) return;

        const currentScheduleId = scheduleCard ? scheduleCard.dataset.scheduleId : null;

        items.forEach(item => {
            // Eğer item sadece bir ID veya geçersiz bir nesne ise atla
            if (!item || typeof item !== 'object' || !item.id) return;
            if (currentScheduleId && item.schedule_id != currentScheduleId) return;

            const dayIndex = parseInt(item.day_index);
            const itemStart = item.start_time.substring(0, 5);
            const itemEnd = item.end_time.substring(0, 5);

            for (let i = 1; i < table.rows.length; i++) {
                const cell = table.rows[i].cells[dayIndex + 1];
                if (!cell) continue;

                const cellStart = cell.dataset.startTime;
                if (cellStart >= itemStart && cellStart < itemEnd) {
                    cell.dataset.scheduleItemId = item.id;
                    cell.innerHTML = this.createDummySlotHTML(item);
                }
            }
        });

        this.initPopovers(table);
    }

    clearTableItemsByIds(deletedIds) {
        if (!deletedIds || deletedIds.length === 0) return;
        const idSet = new Set(deletedIds.map(id => id.toString()));
        const scheduleCard = document.querySelector('.schedule-card');
        const table = scheduleCard ? scheduleCard.querySelector('table.schedule-table') : document.querySelector('table.schedule-table');
        if (!table || !table.rows) return;

        for (let i = 1; i < table.rows.length; i++) {
            const row = table.rows[i];
            for (let j = 1; j < row.cells.length; j++) {
                const cell = row.cells[j];
                const cellId = cell.dataset.scheduleItemId;
                if (cellId && idSet.has(cellId.toString())) {
                    delete cell.dataset.scheduleItemId;
                    cell.innerHTML = '<div class="empty-slot"></div>';
                }
            }
        }
    }

    createDummySlotHTML(item) {
        const scheduleCard = document.querySelector('.schedule-card');
        const onlyTable = scheduleCard ? (scheduleCard.dataset.onlyTable === 'true' || scheduleCard.dataset.onlyTable === '1') : false;
        const statusClass = item.status === 'preferred' ? 'slot-preferred' : 'slot-unavailable';

        let detail = item.detail;
        if (typeof detail === 'string') {
            try { detail = JSON.parse(detail); } catch (e) { detail = {}; }
        }

        let html = `<div class="empty-slot dummy ${statusClass}" 
                        draggable="${onlyTable ? 'true' : 'false'}" 
                        data-schedule-item-id="${item.id}" 
                        data-status="${item.status}"
                        data-detail='${JSON.stringify(item.detail || {})}'>`;

        if (onlyTable) {
            html += `<input type="checkbox" class="lesson-bulk-checkbox" title="Toplu işlem için seç">`;
        }

        if (detail && detail.description) {
            html += `<div class="note-icon" data-bs-toggle="popover" data-bs-placement="left"
                          data-bs-trigger="hover" data-bs-content="${detail.description}"
                          data-bs-original-title="Açıklama">
                        <i class="bi bi-chat-square-text-fill"></i>
                    </div>`;
        }

        html += `</div>`;
        return html;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.singleScheduleHandler = new SingleScheduleHandler();
});
