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

    // Toplu Yayınla ve Değişiklikleri Bildir butonları
    const btnBulkPublish = document.getElementById('btn-bulk-publish');
    if (btnBulkPublish) {
        btnBulkPublish.addEventListener('click', async function () {
            if (confirm("Tüm programlar yayınlanacak. Emin misiniz?")) {
                try {
                    const response = await fetch('/ajax/bulkPublishSchedules', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const data = await response.json();
                    if (data.status === 'success') {
                        new Toast().prepareToast("Başarılı", data.msg, "success");
                        // Sayfadaki switch butonları da güncelleyelim
                        document.querySelectorAll('.publish-schedule-toggle').forEach(el => {
                            el.checked = true;
                            el.nextElementSibling.innerText = 'Yayında';
                        });
                    } else {
                        new Toast().prepareToast("Hata", data.msg || 'Hata oluştu', "danger");
                    }
                } catch (error) {
                    new Toast().prepareToast("Hata", 'Bir hata oluştu.', "danger");
                }
            }
        });
    }

    const btnNotifyChanges = document.getElementById('btn-notify-changes');
    if (btnNotifyChanges) {
        btnNotifyChanges.addEventListener('click', async function () {
            if (confirm("Değişiklik olan programlar için hocalara bildirim gönderilecek. Emin misiniz?")) {
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
            }
        });
    }

});