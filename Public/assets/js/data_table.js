let dataTable = new DataTable('.dataTable', {
    // config options...
    language: {
        url: '/assets/js/datatable_tr.json'
    },
    initComplete: function () {
        document.querySelector('.dataTable thead').style.whiteSpace = 'nowrap';// satır kaymalarını önlemek için
        let api = this.api();

        api.columns().every(function () {
            let column = this;
            let header = column.header();

            if (header.classList.contains("filterable")) {
                // Filtre ikonu sarmalayıcı
                let filterWrapper = document.createElement('div');
                filterWrapper.classList.add('dropdown', 'd-inline-block', 'me-2'); // sağa boşluk

                let filterIcon = document.createElement("i");
                filterIcon.classList.add("bi", "bi-funnel", "dropdown-toggle");
                filterIcon.style.cursor = "pointer";
                filterIcon.setAttribute("data-bs-toggle", "dropdown");
                filterIcon.setAttribute("aria-expanded", "false");
                filterWrapper.appendChild(filterIcon);

                // Dropdown menüsü
                let dropdownMenu = document.createElement('ul');
                dropdownMenu.classList.add('dropdown-menu', 'p-2');
                dropdownMenu.style.maxHeight = "200px";
                dropdownMenu.style.overflowY = "auto";

                // "Tümü" seçeneği
                let allOption = document.createElement('li');
                let allLink = document.createElement('a');
                allLink.classList.add('dropdown-item');
                allLink.href = "#";
                allLink.textContent = "Tümü";
                allLink.addEventListener('click', function (e) {
                    e.preventDefault();
                    column.search('').draw();
                });
                allOption.appendChild(allLink);
                dropdownMenu.appendChild(allOption);

                // Eşsiz sütun verilerini ekle
                column.data().unique().sort().each(function (d) {
                    if (d) {
                        let li = document.createElement('li');
                        let a = document.createElement('a');
                        a.classList.add('dropdown-item');
                        a.href = "#";
                        a.textContent = d;
                        a.addEventListener('click', function (e) {
                            e.preventDefault();
                            column.search('^' + d + '$', true, false).draw();
                        });
                        li.appendChild(a);
                        dropdownMenu.appendChild(li);
                    }
                });

                filterWrapper.appendChild(dropdownMenu);
                header.appendChild(filterWrapper);
                filterIcon.addEventListener('click', function (e) {
                    e.stopPropagation(); // Bu tıklamanın th elementine yayılmasını engeller
                });
                dropdownMenu.querySelectorAll('a').forEach(a => {
                    a.addEventListener('click', function (e) {
                        e.stopPropagation();
                    });
                });


            }
        });
    }

});
/**
 * Popover
 * Bağlı derslerin hangi derse bağlı olduğunu göstermek için bu kodları ekledim
 */
document.addEventListener('DOMContentLoaded', function () {
    const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
    const popoverList = [...popoverTriggerList].map(el => new bootstrap.Popover(el));
});