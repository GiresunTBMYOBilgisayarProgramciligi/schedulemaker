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
    
    if (typeof window.updateBulkPublishButtonState === 'function') {
        window.updateBulkPublishButtonState();
    }
};

window.updateBulkPublishButtonState = async function () {
    const btnBulkPublish = document.getElementById('btn-bulk-publish');
    if (!btnBulkPublish) return;
    
    const semesterEl = document.getElementById('semester');
    const academicYearEl = document.getElementById('academic_year');
    
    if (!semesterEl || !academicYearEl) return;
    
    const formData = new FormData();
    formData.append('semester', semesterEl.value);
    formData.append('academic_year', academicYearEl.value);
    
    try {
        const response = await fetch('/ajax/getBulkPublishStatus', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        const data = await response.json();
        
        if (data.status === 'success') {
            if (data.total_count === 0) {
                btnBulkPublish.style.display = 'none';
                return;
            }
            
            btnBulkPublish.style.display = 'inline-block';
            
            if (data.all_published) {
                btnBulkPublish.innerHTML = '<i class="bi bi-globe-x me-1"></i> Tümünü Yayından Kaldır';
                btnBulkPublish.classList.remove('btn-success');
                btnBulkPublish.classList.add('btn-secondary');
                btnBulkPublish.dataset.action = 'unpublish';
            } else {
                btnBulkPublish.innerHTML = '<i class="bi bi-globe me-1"></i> Tümünü Yayınla';
                btnBulkPublish.classList.remove('btn-secondary');
                btnBulkPublish.classList.add('btn-success');
                btnBulkPublish.dataset.action = 'publish';
            }
        }
    } catch (error) {
        console.error("Bulk publish status error", error);
    }
};

document.addEventListener("DOMContentLoaded", function () {
    const semesterEl = document.getElementById('semester');
    const academicYearEl = document.getElementById('academic_year');
    if (semesterEl) {
        semesterEl.addEventListener('change', window.updateBulkPublishButtonState);
    }
    if (academicYearEl) {
        academicYearEl.addEventListener('change', window.updateBulkPublishButtonState);
    }
    
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

    // Toplu Yayınla ve Değişiklikleri Bildir butonları
    const btnBulkPublish = document.getElementById('btn-bulk-publish');
    if (btnBulkPublish) {
        btnBulkPublish.addEventListener('click', async function () {
            const semesterEl = document.getElementById('semester');
            const academicYearEl = document.getElementById('academic_year');
            const semester = semesterEl ? semesterEl.value : '';
            const academicYear = academicYearEl ? academicYearEl.value : '';
            const action = btnBulkPublish.dataset.action || 'publish';
            const isPublishing = action === 'publish';
            
            let confirmMsg = isPublishing ? "Tüm programlar yayınlanacak. Emin misiniz?" : "Tüm programlar yayından kaldırılacak. Emin misiniz?";
            if (semester && academicYear) {
                confirmMsg = isPublishing 
                    ? `${academicYear} - ${semester} dönemi için tüm programlar yayınlanacak. Emin misiniz?` 
                    : `${academicYear} - ${semester} dönemi için tüm programlar yayından kaldırılacak. Emin misiniz?`;
            }
            
            let publishModal = new Modal();
            publishModal.prepareModal(isPublishing ? "Yayınlama Onayı" : "Yayından Kaldırma Onayı", confirmMsg, true, true);
            publishModal.confirmButton.textContent = isPublishing ? "Yayınla" : "Yayından Kaldır";
            publishModal.showModal();
            
            publishModal.confirmButton.addEventListener("click", async (event) => {
                publishModal.hideModal();
                try {
                    const formData = new FormData();
                    if (semester) formData.append('semester', semester);
                    if (academicYear) formData.append('academic_year', academicYear);
                    formData.append('action', action);
                    
                    const response = await fetch('/ajax/bulkPublishSchedules', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    });
                    const data = await response.json();
                    if (data.status === 'success') {
                        new Toast().prepareToast("Başarılı", data.msg, "success");
                        // Sayfadaki switch butonları da güncelleyelim
                        document.querySelectorAll('.publish-schedule-toggle').forEach(el => {
                            el.checked = isPublishing;
                            el.nextElementSibling.innerText = isPublishing ? 'Yayında' : 'Yayınla';
                        });
                        window.updateBulkPublishButtonState();
                    } else {
                        new Toast().prepareToast("Hata", data.msg || 'Hata oluştu', "danger");
                    }
                } catch (error) {
                    new Toast().prepareToast("Hata", 'Bir hata oluştu.', "danger");
                }
            });
        });
    }

    const btnNotifyChanges = document.getElementById('btn-notify-changes');
    if (btnNotifyChanges) {
        btnNotifyChanges.addEventListener('click', async function () {
            let notifyModal = new Modal();
            notifyModal.prepareModal("Bildirim Onayı", "Değişiklik olan programlar için hocalara bildirim gönderilecek. Emin misiniz?", true, true);
            notifyModal.confirmButton.textContent = "Gönder";
            notifyModal.showModal();
            
            notifyModal.confirmButton.addEventListener("click", async (event) => {
                notifyModal.hideModal();
                try {
                    const response = await fetch('/ajax/notifyScheduleChanges', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const data = await response.json();
                    if (data.status === 'success' || data.status === 'info') {
                        new Toast().prepareToast("Bilgi", data.msg, data.status === 'success' ? "success" : "info");
                    } else {
                        new Toast().prepareToast("Hata", data.msg || 'Hata oluştu', "danger");
                    }
                } catch (error) {
                    new Toast().prepareToast("Hata", 'Bir hata oluştu.', "danger");
                }
            });
        });
    }

});