/**
 * Tekli sayfalardaki Program düzenlemelerini yönetir.
 */
/**
 * Uygun dersler listesinden ders sürüklenmeye başladığında aynı sezondaki tablo içerisinde uygun olmayan hücrelerin listesi
 */
let unavailableCells;

let preferredCells;
let spinner = new Spinner();
document.addEventListener("DOMContentLoaded", function () {
    // draggable="true" olan tüm elementleri seç
    const dragableElements = document.querySelectorAll('[draggable="true"]');
    //drop-zone sınıfına sahip tüm elementler
    const dropZones = document.querySelectorAll('.drop-zone');
    // Her bir draggable öğeye event listener ekle
    dragableElements.forEach(element => {
        element.addEventListener('dragstart', dragStartHandler);
    });
    //tüm drop-zone alanları için olay dinleyicisi ekleniyor
    dropZones.forEach(element => {
        element.addEventListener("drop", dropHandler.bind(this, element));
        element.addEventListener("dragover", dragOverHandler.bind(this, element)) // bu olmadan çalışmıyor
    });
    //sayfadaki tüm tooltiplerin aktif edilmesi için
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })

    /**
     * bırakılan alanda başka ders olup olmadığını ve grup işlemlerini kontrol eder
     * Bırakılan alandaki ders ile bırakılan derslerin gruplarının olup olmadığını varsa farklı olup olmadığını kontrol eder
     * @param dropZone
     * @param draggedElement
     * @param selectedClassroom
     */
    async function checkLessonCrash(dropZone, draggedElement, selectedClassroom){}

    /**
     * Belirtilen tabloda dersin hocasının dolu günleri vurgulanır
     * @param lessonId
     * @param table
     * @returns {Promise<boolean>}
     */
    function highlightUnavailableCells(lessonId, table){}

    /**
     * Ders sürükleme işlemi başlatıldığında tablo üzerinde hocanın uygun olmayan saatleri kırmızı ile vurgulanıyor.
     * Bu fonksiyon o vurguları siler
     * @param table
     */
    function clearCells(table) {
        for (let i = 0; i < table.rows.length; i++) {
            for (let j = 0; j < table.rows[i].cells.length; j++) {
                /*
                Öğle arası bg-danger ile vurgulandığı için bu işlem o saatlari etkilemiyor
                 */
                table.rows[i].cells[j].classList.remove("text-bg-danger")
                table.rows[i].cells[j].classList.remove("text-bg-success")
            }
        }
        /**
         * Veri Tabanından alınan Uygun olmayan hücreler bilgisi temizleniyor
         * @type {null}
         */
        unavailableCells = null;
    }

    async function saveSchedule(scheduleData){
        let data = new FormData();
        // scheduleData içindeki tüm verileri otomatik olarak FormData'ya ekle
        Object.entries(scheduleData).forEach(([key, value]) => {
            data.append(key, value);
        });

    }

    async function deleteSchedule(scheduleData){}

    /**
     * Tablodan alınarak listeye geri bırakılan dersler için yapılan işlemler
     * @param table
     * @param draggedElement
     * @param dropZone
     * @returns {Promise<void>}
     */
    async function dropTableToList(table, draggedElement, dropZone){}

    async function dropTableToTable(table, draggedElement, dropZone){}

    async function dropListToTable(listElement, draggedElement, dropZone){}

    /**
     *
     * @param element bırakma işleminin yapıldığı element
     * @param event bırakma olayı
     */
    function dropHandler(element, event) {
        event.preventDefault();
        let dropZones = document.querySelectorAll(".available-schedule-items.drop-zone")
        dropZones.forEach((dropZone) => {
            dropZone.style.border = ""
            const tooltip = bootstrap.Tooltip.getInstance(dropZone);
            if (tooltip)
                tooltip.hide()
        })
        /**
         * Bırakma eyleminin yapıldığı ana element (eventListenner'ı olan)
         */
        const droppedZone = element;
        /**
         * Bırakma eyleminin yapıldığı elementin çocuklarından birisi. üzerine bırakılan element
         */
        const droppedTargetElement = event.target
        /**
         * alanlar snake case olmalı
         * @type {{}}
         */
        let transferredData = {};
        /**
         * Sürükleme olayı ile gönderilen veriler transferredData objesine aktarılıyor.
         */
        for (let data_index in event.dataTransfer.types) {
            transferredData[event.dataTransfer.types[data_index]] = event.dataTransfer.getData(event.dataTransfer.types[data_index])
        }
        /**
         * Sürüklenen ve bırakılacak olan element
         * @type {Element}
         */
        const draggedElement = document.getElementById(event.dataTransfer.getData("id"))
        switch (transferredData.start_element) {
            case "list":
                if (droppedZone.classList.contains("available-schedule-items")) {
                    // Listeden Listeye
                    let table = document.querySelector('table[data-semester-no="' + droppedZone.dataset.semesterNo + '"]');
                    clearCells(table);
                    return;
                } else {
                    // Listeden Tabloya bırakma işlemleri
                    let list = draggedElement.closest(".available-schedule-items");
                    dropListToTable(list, draggedElement, droppedZone)
                }
                break;
            case "table":
                if (droppedZone.classList.contains("available-schedule-items")) {
                    //Tablodan Listeye
                    let table = draggedElement.closest("table");
                    dropTableToList(table, draggedElement, droppedZone)
                } else {
                    //Tablodan Tabloya
                    let table = draggedElement.closest("table");
                    dropTableToTable(table, draggedElement, droppedZone)
                }
                break;
        }
    }

    /**
     * @param event sürükleme olayı
     */
    function dragStartHandler(event) {
        event.dataTransfer.effectAllowed = "move";
        event.dataTransfer.dropEffect = "move";
        // id bilgisi aktarılyor
        event.dataTransfer.setData("id", event.target.id)
        //tüm data bilgileri aktarılıyor
        for (let data in event.target.dataset) {
            //format kısmına genelde text/plain, text/html gibi terimler yasılıyor. Ama anladığım kadarıyla buraya ne yazdıysak get Data kısmına da aynısını yazmamız yeterli
            event.dataTransfer.setData(data, event.target.dataset[data])
        }
        if (event.target.closest("table")) {
            let dropZones = document.querySelectorAll(".available-schedule-items.drop-zone")
            dropZones.forEach((dropZone) => {
                dropZone.style.border = "2px dashed"
                // Bootstrap tooltip nesnesini oluştur
                const tooltip = new bootstrap.Tooltip(dropZone);
                tooltip.show();
            })
            event.dataTransfer.setData("start_element", "table")
            let table = event.target.closest("table")
            let lessonID = event.target.dataset['lessonId'];
            clearCells(table);
            let result = highlightUnavailableCells(lessonID, table);
        } else if (event.target.closest(".available-schedule-items")) {
            event.dataTransfer.setData("start_element", "list")
            let table = document.querySelector('table[data-semester-no="' + event.dataTransfer.getData("semesterNo") + '"]')
            let lessonID = event.target.dataset['lessonId'];
            clearCells(table);
            let result = highlightUnavailableCells(lessonID, table);
        }
    }

    /**
     * Sürükleme işlemi sürdüğü sürece çalışır
     * @param element
     * @param event
     */
    function dragOverHandler(element, event) {
        event.preventDefault();
        event.dataTransfer.effectAllowed = "move";
    }
})