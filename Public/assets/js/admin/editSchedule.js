/**
 * Öncesinde ScheduleCard yüklenmeli
 */
document.addEventListener("DOMContentLoaded", function () {

    document.addEventListener('scheduleLoaded', function () {
        let scheduleCardElements = document.querySelectorAll("#schedule_container .card")

        if (scheduleCardElements.length > 0 && scheduleCardElements[0].dataset.scheduleScreenName) {
            document.title = scheduleCardElements[0].dataset.scheduleScreenName;
        }

        let scheduleCards = [];
        scheduleCardElements.forEach((scheduleCardElement) => {
            let scheduleCard = new ScheduleCard(scheduleCardElement)
            scheduleCards.push(scheduleCard);
        })
        document.addEventListener("lessonDrop", (event) => {
            /**
             * herhangi bir scheduleCard nesnesinde dropHandler çalıştığında tüm ScheduleCard nesnelerinin sürüklenen ders bilgileri sıfırlanıyor.
             * Farklı tablolara bırakma işlemi yapıldığında scheduleCard nesnesindeki drop dinleyicisi tetiklenmiyor. Bu yüzden hepsinde sıfırlama yapılıyor
             */
            scheduleCards.forEach((scheduleCard) => {
                if (scheduleCard.isDragging) {
                    scheduleCard.isDragging = false;
                    scheduleCard.resetDraggedLesson();
                    scheduleCard.clearCells();
                }
            })
        })
    })
});