let dataTable = new DataTable('.dataTable', {
    layout: {
        topStart: {
            pageLength: {
                menu: [10, 25, 50, 100]
            },
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: '<i class="bi bi-file-earmark-excel me-1"></i> Excel',
                    className: 'btn btn-success btn-sm',
                    exportOptions: {
                        columns: ':not(.no-export)'
                    }
                },
                {
                    extend: 'pdfHtml5',
                    text: '<i class="bi bi-file-earmark-pdf me-1"></i> PDF',
                    className: 'btn btn-danger btn-sm',
                    exportOptions: {
                        columns: ':not(.no-export)'
                    }
                }
            ]
        },
        topEnd: 'search'
    },
    pageLength: 25,
    lengthMenu: [10, 25, 50, 100],
    language: {
        url: '/assets/js/datatable_tr.json'
    },
    initComplete: function () {
        let api = this.api();
        let filterIcons = []; // kolon başına ikonları sakla

        api.columns().every(function () {
            let column = this;
            let header = column.header();
            let headerText = header.textContent.trim();

            // "İşlemler" sütununu dışa aktarmadan hariç tutmak için işaretle
            if (headerText === "İşlemler") {
                $(column.header()).addClass('no-export');
                $(column.nodes()).addClass('no-export');
            }

            if (header.classList.contains("filterable")) {
                header.style.whiteSpace = 'nowrap'; // satır kaymalarını önlemek için
                // Filtre ikonu sarmalayıcı
                let filterWrapper = document.createElement('div');
                filterWrapper.classList.add('dropdown', 'd-inline-block', 'me-2'); // sağa boşluk

                let filterIcon = document.createElement("i");
                filterIcon.classList.add("bi", "bi-funnel", "dropdown-toggle");
                filterIcon.style.cursor = "pointer";
                filterIcon.setAttribute("data-bs-toggle", "dropdown");
                filterIcon.setAttribute("aria-expanded", "false");

                // Bu ikonu kaydet
                filterIcons[column.index()] = filterIcon;

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
                        // 💡 DÜZELTME: HTML etiketlerini temizle
                        // Veriyi temizlemek için geçici bir DOM öğesi oluşturuyoruz.
                        let tempDiv = document.createElement('div');
                        tempDiv.innerHTML = d;
                        let cleanData = tempDiv.textContent || tempDiv.innerText || d;

                        let li = document.createElement('li');
                        let a = document.createElement('a');
                        a.classList.add('dropdown-item');
                        a.href = "#";

                        // Menüde görünen değer temizlenmiş değer olmalı
                        a.textContent = cleanData;

                        a.addEventListener('click', function (e) {
                            e.preventDefault();

                            // 💡 DÜZELTME: Filtreleme işlemi de temizlenmiş değerle yapılmalı
                            // DataTable'ın filtreleme fonksiyonu olan search() içerisindeki değeri de
                            // temizlenmiş veri (cleanData) ile eşleştirmeliyiz.
                            column.search('^' + cleanData + '$', true, false).draw();
                        });
                        li.appendChild(a);
                        dropdownMenu.appendChild(li);
                    }
                });

                filterWrapper.appendChild(dropdownMenu);
                header.appendChild(filterWrapper);

                // Tıklamaların sıralama tetiklemesini engelle
                filterIcon.addEventListener('click', function (e) {
                    e.stopPropagation();
                });
                dropdownMenu.querySelectorAll('a').forEach(a => {
                    a.addEventListener('click', function (e) {
                        e.stopPropagation();
                        let dropdown = bootstrap.Dropdown.getInstance(filterIcon);
                        if (dropdown) dropdown.hide();
                    });
                });
            }
        });

        // Tabloda filtre yapıldığında ikon durumunu güncelle
        api.on('draw', function () {
            api.columns().every(function () {
                let column = this;
                let filterIcon = filterIcons[column.index()];
                if (!filterIcon) return;

                if (column.search()) {
                    filterIcon.classList.remove('bi-funnel');
                    filterIcon.classList.add('bi-funnel-fill'); // dolu ikon
                } else {
                    filterIcon.classList.remove('bi-funnel-fill', 'text-primary');
                    filterIcon.classList.add('bi-funnel');
                }
            });
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