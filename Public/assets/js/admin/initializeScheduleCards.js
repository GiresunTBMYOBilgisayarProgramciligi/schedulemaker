/**
 * Tüm tekli sayfalar (hoca, derslik, program vb.) ve edit sayfalarında ortak kullanılacak
 * ScheduleCard başlatma fonksiyonlarını barındırır.
 * Öncesinde ScheduleCard.js (ve ilgili alt sınıfları) yüklenmeli.
 */

window.scheduleCards = [];

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
        if (typeof ExamScheduleCard !== 'undefined' && ['midterm-exam', 'final-exam', 'makeup-exam', 'exam'].includes(type)) {
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
});