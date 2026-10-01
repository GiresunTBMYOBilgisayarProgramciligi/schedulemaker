/**
 * Tüm tekli sayfalar (hoca, derslik, program vb.) ve edit sayfalarında ortak kullanılacak
 * ScheduleCard başlatma fonksiyonlarını barındırır.
 * Öncesinde ScheduleCard.js (ve ilgili alt sınıfları) yüklenmeli.
 */

window.scheduleCards = [];
window.EXAM_TYPES = ['midterm-exam', 'final-exam', 'makeup-exam'];

window.initializeScheduleCards = function () {
    let scheduleCardElements = document.querySelectorAll(".schedule-card");

    // Sayfa başlığını (title) ilk schedule-card'ın screen_name değerine göre ayarla
    let container = document.querySelector("#schedule_container");
    if (container) {
        let containerCards = container.querySelectorAll(".schedule-card");
        if (containerCards.length > 0 && containerCards[0].dataset.scheduleScreenName) {
            document.title = containerCards[0].dataset.scheduleScreenName;
        }
    } else if (scheduleCardElements.length > 0 && scheduleCardElements[0].dataset.scheduleScreenName) {
        document.title = scheduleCardElements[0].dataset.scheduleScreenName;
    }

    // Önceki kart referanslarını temizle
    window.scheduleCards = [];
    // Preference Mode (Hoca Tercihleri) için SingleScheduleHandler referanslarını temizle
    window.singleScheduleHandlerList=[];

    scheduleCardElements.forEach((scheduleCardElement) => {
        const type = scheduleCardElement.dataset.type;
        let scheduleCard;
        if (typeof ExamScheduleCard !== 'undefined' && [...window.EXAM_TYPES, 'exam'].includes(type)) {
            scheduleCard = new ExamScheduleCard(scheduleCardElement);
        } else if (typeof LessonScheduleCard !== 'undefined') {
            scheduleCard = new LessonScheduleCard(scheduleCardElement);
        } else {
            scheduleCard = new ScheduleCard(scheduleCardElement);
        }
        window.scheduleCards.push(scheduleCard);
    });
};

document.addEventListener("DOMContentLoaded", function () {
    
    // DOM yüklendiğinde mevcut kartları başlat
    window.initializeScheduleCards();

    // AJAX veya diğer yollarla kartlar yeniden yüklendiğinde tekrar başlat
    document.addEventListener('scheduleLoaded', function () {
        window.initializeScheduleCards();
    });

    document.addEventListener("lessonDrop", (event) => {
        /**
         * herhangi bir scheduleCard nesnesinde dropHandler çalıştığında tüm ScheduleCard nesnelerinin sürüklenen ders bilgileri sıfırlanıyor.
         * Farklı tablolara bırakma işlemi yapıldığında scheduleCard nesnesindeki drop dinleyicisi tetiklenmiyor. Bu yüzden hepsinde sıfırlama yapılıyor
         */
        window.scheduleCards.forEach((scheduleCard) => {
            if (scheduleCard.isDragging) {
                scheduleCard.isDragging = false;
                scheduleCard.resetDraggedLesson();
                scheduleCard.clearCells();
            }
        });
    });



    // Global Publish Schedule Switch Event Delegation
    document.addEventListener('change', async function (e) {
        const toggle = e.target.closest('.publish-schedule-toggle');
        if (!toggle) return;

        const scheduleId = toggle.dataset.scheduleId || toggle.closest('.schedule-card')?.dataset.scheduleId;
        if (!scheduleId) return;

        let formData = new FormData();
        formData.append('id', scheduleId);
        
        toggle.disabled = true;
        try {
            const response = await fetch('/ajax/togglePublishSchedule', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await response.json();
            toggle.disabled = false;
            if (data.status === 'success') {
                if (typeof Toast !== 'undefined') {
                    new Toast().prepareToast("Başarılı", data.msg, "success");
                }
                const label = toggle.nextElementSibling;
                if (label && label.classList.contains('form-check-label')) {
                    label.innerText = data.is_published ? 'Yayında' : 'Yayınla';
                }
                if (typeof window.updateBulkPublishButtonState === 'function') {
                    window.updateBulkPublishButtonState();
                }
            } else {
                if (typeof Toast !== 'undefined') {
                    new Toast().prepareToast("Hata", data.msg || 'Hata oluştu', "danger");
                }
                toggle.checked = !toggle.checked; // Revert change
            }
        } catch (error) {
            toggle.disabled = false;
            if (typeof Toast !== 'undefined') {
                new Toast().prepareToast("Hata", 'Bir hata oluştu.', "danger");
            }
            toggle.checked = !toggle.checked; // Revert change
        }
    });

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    const btnNotifyChanges = document.getElementById('btn-notify-changes');
    if (btnNotifyChanges) {
        btnNotifyChanges.addEventListener('click', async function () {
            const originalBtnHtml = btnNotifyChanges.innerHTML;
            btnNotifyChanges.disabled = true;
            btnNotifyChanges.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Yükleniyor...';

            let data;
            try {
                const response = await fetch('/ajax/getPendingScheduleChanges', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                data = await response.json();
            } catch (err) {
                btnNotifyChanges.disabled = false;
                btnNotifyChanges.innerHTML = originalBtnHtml;
                if (typeof Toast !== 'undefined') {
                    new Toast().prepareToast("Hata", 'Değişiklikler alınırken bağlantı hatası oluştu.', "danger");
                }
                return;
            }

            btnNotifyChanges.disabled = false;
            btnNotifyChanges.innerHTML = originalBtnHtml;

            if (!data || data.status !== 'success') {
                if (typeof Toast !== 'undefined') {
                    new Toast().prepareToast("Hata", data?.msg || 'Değişiklikler alınamadı.', "danger");
                }
                return;
            }

            if (!data.lecturers || data.lecturers.length === 0) {
                let emptyModal = new Modal();
                emptyModal.prepareModal(
                    "Değişiklik Bildirimi",
                    `<div class="text-center py-4">
                        <i class="bi bi-info-circle text-info" style="font-size: 2.5rem;"></i>
                        <h5 class="mt-3">Bildirilecek Değişiklik Bulunamadı</h5>
                        <p class="text-muted mb-0">Yayınlanmış programlarda henüz bildirilmemiş herhangi bir değişiklik kaydı bulunmamaktadır.</p>
                    </div>`,
                    false,
                    true,
                    "md"
                );
                emptyModal.showModal();
                return;
            }

            const totalLecturers = data.total_lecturers;
            const totalChanges = data.total_changes;

            let lecturersHtml = data.lecturers.map(lecturer => {
                const changesList = lecturer.changes.map(ch => `
                    <li class="mb-1">
                        <i class="bi bi-arrow-right-short text-primary"></i>
                        <span>${escapeHtml(ch.detail)}</span>
                        <small class="text-muted ms-1">(${escapeHtml(ch.created_at || '')})</small>
                    </li>
                `).join('');

                const deptBadge = lecturer.department_name ? `<span class="badge bg-light text-secondary border me-1">${escapeHtml(lecturer.department_name)}</span>` : '';
                const progBadge = lecturer.program_name ? `<span class="badge bg-light text-secondary border me-1">${escapeHtml(lecturer.program_name)}</span>` : '';

                return `
                    <div class="list-group-item p-3 border-start-0 border-end-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="form-check d-flex align-items-start mb-0">
                                <input class="form-check-input notify-lecturer-checkbox me-2 mt-1" type="checkbox" value="${lecturer.id}" id="notify-lecturer-${lecturer.id}" checked>
                                <label class="form-check-label" for="notify-lecturer-${lecturer.id}" style="cursor: pointer;">
                                    <div class="fw-bold text-dark">${escapeHtml(lecturer.name)}</div>
                                    <div class="small text-muted mb-1">${escapeHtml(lecturer.email)}</div>
                                    <div>${deptBadge}${progBadge}</div>
                                </label>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-warning text-dark mb-1 d-inline-block">${lecturer.change_count} Değişiklik</span>
                                <div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 toggle-changes-btn" data-bs-toggle="collapse" data-bs-target="#collapse-changes-${lecturer.id}" aria-expanded="false" style="font-size: 11px;">
                                        <i class="bi bi-chevron-down"></i> Detaylar
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="collapse mt-2 pt-2 border-top" id="collapse-changes-${lecturer.id}">
                            <ul class="list-unstyled mb-0 ps-2 small text-secondary" style="border-left: 3px solid #ffc107;">
                                ${changesList}
                            </ul>
                        </div>
                    </div>
                `;
            }).join('');

            let contentHtml = `
                <div class="mb-3">
                    <div class="alert alert-info py-2 px-3 mb-3 d-flex align-items-center">
                        <i class="bi bi-info-circle-fill fs-5 me-2 flex-shrink-0"></i>
                        <div>
                            Toplam <strong>${totalLecturers}</strong> öğretim elemanını ilgilendiren <strong>${totalChanges}</strong> adet değişiklik bulunmaktadır. Bildirim göndermek istediğiniz hocaları seçiniz.
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2 px-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="check-all-notify-lecturers" checked>
                            <label class="form-check-label fw-bold small" for="check-all-notify-lecturers" style="cursor: pointer;">
                                Tümünü Seç / Kaldır
                            </label>
                        </div>
                        <span class="badge bg-primary" id="notify-selected-count">${totalLecturers} Hoca Seçildi</span>
                    </div>
                    <div class="list-group border rounded" style="max-height: 380px; overflow-y: auto;">
                        ${lecturersHtml}
                    </div>
                </div>
            `;

            let notifyModal = new Modal();
            notifyModal.prepareModal("Değişiklik Bildirimi Gönder", contentHtml, true, true, "lg");
            notifyModal.confirmButton.textContent = `Bildirimleri Gönder (${totalLecturers})`;
            notifyModal.confirmButton.className = "btn btn-primary";
            notifyModal.showModal();

            const checkAll = notifyModal.modal.querySelector('#check-all-notify-lecturers');
            const checkboxes = notifyModal.modal.querySelectorAll('.notify-lecturer-checkbox');
            const selectedCountBadge = notifyModal.modal.querySelector('#notify-selected-count');

            function updateSelectionState() {
                const checkedCheckboxes = Array.from(checkboxes).filter(cb => cb.checked);
                const count = checkedCheckboxes.length;

                selectedCountBadge.textContent = `${count} Hoca Seçildi`;
                notifyModal.confirmButton.textContent = `Bildirimleri Gönder (${count})`;

                if (count === 0) {
                    notifyModal.confirmButton.disabled = true;
                    checkAll.checked = false;
                    checkAll.indeterminate = false;
                } else if (count === checkboxes.length) {
                    notifyModal.confirmButton.disabled = false;
                    checkAll.checked = true;
                    checkAll.indeterminate = false;
                } else {
                    notifyModal.confirmButton.disabled = false;
                    checkAll.checked = false;
                    checkAll.indeterminate = true;
                }
            }

            if (checkAll) {
                checkAll.addEventListener('change', function () {
                    checkboxes.forEach(cb => cb.checked = checkAll.checked);
                    updateSelectionState();
                });
            }

            checkboxes.forEach(cb => {
                cb.addEventListener('change', updateSelectionState);
            });

            notifyModal.confirmButton.addEventListener("click", async () => {
                const selectedIds = Array.from(checkboxes).filter(cb => cb.checked).map(cb => parseInt(cb.value));
                if (selectedIds.length === 0) return;

                notifyModal.confirmButton.disabled = true;
                notifyModal.confirmButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Gönderiliyor...';

                try {
                    const response = await fetch('/ajax/notifyScheduleChanges', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ lecturer_ids: selectedIds })
                    });
                    const res = await response.json();
                    notifyModal.closeModal();

                    if (res.status === 'success' || res.status === 'info') {
                        new Toast().prepareToast("Bilgi", res.msg, res.status === 'success' ? "success" : "info");
                    } else {
                        new Toast().prepareToast("Hata", res.msg || 'Hata oluştu', "danger");
                    }
                } catch (error) {
                    notifyModal.closeModal();
                    new Toast().prepareToast("Hata", 'Bildirim gönderilirken bir hata oluştu.', "danger");
                }
            });
        });
    }

});