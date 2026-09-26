/**
 * Program Gösterme işlemleri
 * Öncesinde myHTMLElemens.js yüklenmeli
 */
// Yeni bir custom event oluştur
const scheduleLoaded = new Event("scheduleLoaded");
document.addEventListener("DOMContentLoaded", function () {
    const departmentSelect = document.getElementById("department_id")
    const programSelect = document.getElementById("program_id")
    const departmentAndProgramScheduleButton = document.getElementById('departmentAndProgramScheduleButton')
    const lecturerScheduleButton = document.getElementById('lecturerScheduleButton')
    const lecturerSelect = document.getElementById("lecturer_id");
    const classroomScheduleButton = document.getElementById('classroomScheduleButton')
    const classroomSelect = document.getElementById("classroom_id");
    const toast = new Toast();
    if (departmentAndProgramScheduleButton) {
        departmentAndProgramScheduleButton.addEventListener("click", async function () {
            let data = new FormData();
            let scheduleType = document.getElementById('schedule_type')?.value || "lesson";
            data.append("type", scheduleType);
            data.append("semester", document.getElementById("semester").value);
            data.append("academic_year", document.getElementById("academic_year").value);
            data.append("only_table", departmentAndProgramScheduleButton.dataset.onlyTable)
            if (!window.location.pathname.startsWith('/admin')) {
                data.append("is_published", "true");
            }
            if (programSelect.value > 0) {
                data.append("owner_type", "program");
                data.append("owner_id", programSelect.value);

                const semesterNoSelect = document.getElementById("semester_no");
                if (semesterNoSelect && semesterNoSelect.value && semesterNoSelect.value !== "0" && semesterNoSelect.value !== "") {
                    data.append("semester_no", semesterNoSelect.value);
                }

                toast.prepareToast("Yükleniyor", "Ders Programı Yükleniyor...", "info", false)
                await getSchedulesHTML(data);
            } else {
                new Toast().prepareToast("Hata", "Bir Program seçmelisiniz.", "danger");
            }
        });
    }
    if (lecturerScheduleButton) {
        lecturerScheduleButton.addEventListener("click", async function () {
            let data = new FormData();
            let scheduleType = document.getElementById('schedule_type')?.value || "lesson";
            data.append("type", scheduleType);
            data.append("semester", document.getElementById("semester").value);
            data.append("academic_year", document.getElementById("academic_year").value);
            data.append("only_table", lecturerScheduleButton.dataset.onlyTable)
            if (!window.location.pathname.startsWith('/admin')) {
                data.append("is_published", "true");
            }
            if (lecturerSelect.value > 0) {
                data.append("owner_type", "user");
                data.append("owner_id", lecturerSelect.value);
                toast.prepareToast("Yükleniyor", "Ders Programı Yükleniyor...", "info", false)
                await getSchedulesHTML(data);
            } else {
                new Toast().prepareToast("Hata", "Bir hoca seçmelisiniz.", "danger");
            }

        });
    }
    if (classroomScheduleButton) {
        classroomScheduleButton.addEventListener("click", async function () {
            let data = new FormData();
            let scheduleType = document.getElementById('schedule_type')?.value || "lesson";
            data.append("type", scheduleType);
            data.append("semester", document.getElementById("semester").value);
            data.append("academic_year", document.getElementById("academic_year").value);
            data.append("only_table", classroomScheduleButton.dataset.onlyTable)
            if (!window.location.pathname.startsWith('/admin')) {
                data.append("is_published", "true");
            }
            if (classroomSelect.value > 0) {
                data.append("owner_type", "classroom");
                data.append("owner_id", classroomSelect.value);
                toast.prepareToast("Yükleniyor", "Ders Programı Yükleniyor...", "info", false)
                await getSchedulesHTML(data);
            } else {
                new Toast().prepareToast("Hata", "Bir derslik seçmelisiniz.", "danger");
            }

        });
    }

    function initWeekNavigation(container) {
        const cards = container.querySelectorAll('.schedule-card');
        cards.forEach(card => {
            const weekCount = parseInt(card.dataset.weekCount) || card.querySelectorAll('table.schedule-table').length;
            if (weekCount <= 1) return;

            const prevBtn = card.querySelector('.prev-week');
            const nextBtn = card.querySelector('.next-week');
            const label = card.querySelector('.current-week-label');
            let currentWeekIndex = 0;

            if (!prevBtn || !nextBtn) return;

            const switchWeek = (weekIndex) => {
                const tables = card.querySelectorAll('table.schedule-table');
                tables.forEach(t => {
                    t.classList.add('d-none');
                    t.classList.remove('active');
                });

                const targetTable = card.querySelector(`table.schedule-table[data-week-index="${weekIndex}"]`);
                if (targetTable) {
                    targetTable.classList.remove('d-none');
                    targetTable.classList.add('active');
                    currentWeekIndex = weekIndex;
                }

                if (label) label.textContent = `${weekIndex + 1}. Hafta`;
                if (prevBtn) prevBtn.disabled = (weekIndex === 0);
                if (nextBtn) nextBtn.disabled = (weekIndex === weekCount - 1);
                card.dispatchEvent(new CustomEvent('scheduleWeekChanged', { detail: { weekIndex } }));
            };

            prevBtn.addEventListener('click', () => {
                if (currentWeekIndex > 0) {
                    switchWeek(currentWeekIndex - 1);
                }
            });

            nextBtn.addEventListener('click', () => {
                if (currentWeekIndex < weekCount - 1) {
                    switchWeek(currentWeekIndex + 1);
                }
            });

            // İlk haftayı aktif et
            switchWeek(0);
        });
    }

    function initMobileDayNavigation(container) {
        const cards = container.querySelectorAll('.schedule-card');
        cards.forEach(card => {
            const tableWrapper = card.querySelector('.schedule-table-wrapper');
            if (!tableWrapper) return;

            // Varsa eski navigasyonu kaldır
            const oldNav = card.querySelector('.mobile-day-navigation');
            if (oldNav) {
                oldNav.remove();
            }

            const tables = card.querySelectorAll('table.schedule-table');
            if (tables.length === 0) return;

            const firstTable = tables[0];
            const dayHeaders = Array.from(firstTable.querySelectorAll('thead th[data-day-index]'));
            if (dayHeaders.length <= 1) return;

            const days = dayHeaders.map(th => {
                const dayIndex = parseInt(th.dataset.dayIndex, 10);
                const dayName = th.dataset.dayName || th.textContent.replace(/<[^>]*>/g, '').trim();
                const shortName = dayName.length > 3 ? dayName.substring(0, 3) : dayName;
                return { index: dayIndex, name: dayName, shortName: shortName };
            });

            tableWrapper.classList.add('mobile-day-view');

            // Bugünün gün indeksini hesapla (Pazartesi=0, Salı=1, ..., Pazar=6)
            const now = new Date();
            const jsDay = now.getDay();
            const currentWeekdayIndex = (jsDay === 0) ? 6 : (jsDay - 1);
            let activeDayIndex = days.some(d => d.index === currentWeekdayIndex) ? currentWeekdayIndex : days[0].index;

            // Mobil navigasyon DOM bileşeni
            const nav = document.createElement('div');
            nav.className = 'mobile-day-navigation d-md-none mb-3';
            nav.innerHTML = `
                <div class="mobile-day-nav-card shadow-xs">
                    <div class="d-flex align-items-center justify-content-between">
                        <button type="button" class="btn btn-sm btn-outline-primary mobile-day-nav-btn prev-day-btn" title="Önceki Gün" aria-label="Önceki Gün">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <div class="text-center px-2 flex-grow-1 user-select-none">
                            <div class="fw-bold text-primary fs-6 current-day-name"></div>
                            <div class="text-muted small current-day-date d-none" style="font-size: 0.75rem;"></div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary mobile-day-nav-btn next-day-btn" title="Sonraki Gün" aria-label="Sonraki Gün">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                    <div class="mobile-day-pills d-flex justify-content-between gap-1 mt-2 w-100 flex-wrap">
                        ${days.map(d => `
                            <button type="button" class="btn btn-xs rounded-pill mobile-day-pill flex-fill btn-light border text-secondary" data-day-index="${d.index}">
                                ${d.shortName}
                            </button>
                        `).join('')}
                    </div>
                </div>
            `;

            tableWrapper.parentNode.insertBefore(nav, tableWrapper);

            // Mobilde tek gün görünümünde lunch-break-cell'in colspan değerini 1 yap
            // Böylece tarayıcı tablo motoru gereksiz boş sütun alanı bırakmaz ve gün tüm genişliği kaplar!
            const mql = window.matchMedia('(max-width: 767.98px)');
            const updateColspans = (isMobile) => {
                card.querySelectorAll('.lunch-break-cell').forEach(cell => {
                    if (!cell.dataset.desktopColspan) {
                        cell.dataset.desktopColspan = cell.getAttribute('colspan') || '5';
                    }
                    cell.colSpan = isMobile ? 1 : parseInt(cell.dataset.desktopColspan, 10);
                });
            };
            updateColspans(mql.matches);
            mql.addEventListener('change', (e) => updateColspans(e.matches));

            const dayNameEl = nav.querySelector('.current-day-name');
            const dayDateEl = nav.querySelector('.current-day-date');
            const prevBtn = nav.querySelector('.prev-day-btn');
            const nextBtn = nav.querySelector('.next-day-btn');
            const pills = nav.querySelectorAll('.mobile-day-pill');

            const updateView = (dayIndex) => {
                activeDayIndex = dayIndex;

                tables.forEach(t => {
                    t.setAttribute('data-active-day', dayIndex);
                });

                const activeTable = card.querySelector('table.schedule-table.active') || tables[0];
                const th = activeTable.querySelector(`thead th[data-day-index="${dayIndex}"]`);

                let dayName = "";
                let dayDate = "";
                if (th) {
                    dayName = th.dataset.dayName || "";
                    dayDate = th.dataset.dayDate || "";
                    if (!dayName) {
                        const small = th.querySelector('small');
                        if (small) {
                            dayDate = small.textContent.trim();
                            dayName = th.childNodes[0]?.textContent?.trim() || "";
                        } else {
                            dayName = th.textContent.trim();
                        }
                    }
                } else {
                    const found = days.find(d => d.index === dayIndex);
                    dayName = found ? found.name : "";
                }

                dayNameEl.textContent = dayName;
                if (dayDate) {
                    dayDateEl.textContent = dayDate;
                    dayDateEl.classList.remove('d-none');
                } else {
                    dayDateEl.classList.add('d-none');
                }

                const currentPos = days.findIndex(d => d.index === dayIndex);
                prevBtn.disabled = (currentPos <= 0);
                nextBtn.disabled = (currentPos >= days.length - 1);

                pills.forEach(pill => {
                    const pIndex = parseInt(pill.dataset.dayIndex, 10);
                    if (pIndex === dayIndex) {
                        pill.className = 'btn btn-xs rounded-pill mobile-day-pill btn-primary shadow-xs active';
                    } else {
                        pill.className = 'btn btn-xs rounded-pill mobile-day-pill btn-light border text-secondary';
                    }
                });
            };

            prevBtn.addEventListener('click', () => {
                const currentPos = days.findIndex(d => d.index === activeDayIndex);
                if (currentPos > 0) {
                    updateView(days[currentPos - 1].index);
                }
            });

            nextBtn.addEventListener('click', () => {
                const currentPos = days.findIndex(d => d.index === activeDayIndex);
                if (currentPos < days.length - 1) {
                    updateView(days[currentPos + 1].index);
                }
            });

            pills.forEach(pill => {
                pill.addEventListener('click', () => {
                    const targetIndex = parseInt(pill.dataset.dayIndex, 10);
                    updateView(targetIndex);
                });
            });

            // Sınav haftası değiştiğinde tarih etiketini güncelle
            card.addEventListener('scheduleWeekChanged', () => {
                updateView(activeDayIndex);
            });

            // Dokunmatik kaydırma (touch swipe) desteği
            let touchStartX = 0;
            let touchStartY = 0;
            tableWrapper.addEventListener('touchstart', (e) => {
                if (e.touches.length === 1) {
                    touchStartX = e.touches[0].clientX;
                    touchStartY = e.touches[0].clientY;
                }
            }, { passive: true });

            tableWrapper.addEventListener('touchend', (e) => {
                if (!touchStartX || !touchStartY || e.changedTouches.length === 0) return;
                const touchEndX = e.changedTouches[0].clientX;
                const touchEndY = e.changedTouches[0].clientY;
                const diffX = touchEndX - touchStartX;
                const diffY = touchEndY - touchStartY;
                touchStartX = 0;
                touchStartY = 0;

                if (Math.abs(diffX) > 40 && Math.abs(diffX) > Math.abs(diffY) * 1.5) {
                    const currentPos = days.findIndex(d => d.index === activeDayIndex);
                    if (diffX < 0 && currentPos < days.length - 1) {
                        updateView(days[currentPos + 1].index);
                    } else if (diffX > 0 && currentPos > 0) {
                        updateView(days[currentPos - 1].index);
                    }
                }
            }, { passive: true });

            // İlk açılış render'ı
            updateView(activeDayIndex);
        });
    }

    function getSchedulesHTML(scheduleData = new FormData()) {
        const container = document.getElementById('schedule_container');
        container.innerHTML = "";
        return fetch("/ajax/getScheduleHTML", {
            method: "POST",
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: scheduleData,
        })
            .then(response => response.json())
            .then((data) => {
                if (data['status'] !== 'error') {
                    container.innerHTML = data['HTML'];
                    
                    // Hafta navigasyonunu aktif et (Sınav programları için)
                    initWeekNavigation(container);

                    // Mobil tek gün görünümünü aktif et
                    initMobileDayNavigation(container);
                    
                    //Cardiçerisindeki tüm tooltiplerin aktif edilmesi için
                    var tooltipTriggerList = [].slice.call(container.querySelectorAll('[data-bs-toggle="tooltip"]'))
                    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                        return new bootstrap.Tooltip(tooltipTriggerEl)
                    });

                    /**
                     * Bağlı derslerde gösterilecek popoverları aktif etmek için eklendi.
                     * @type {*[]}
                     */
                    var popoverTriggerList = [].slice.call(container.querySelectorAll('[data-bs-toggle="popover"]'))
                    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
                        return new bootstrap.Popover(popoverTriggerEl, { trigger: 'hover' })
                    })
                    toast.closeToast()
                    document.dispatchEvent(scheduleLoaded);
                } else {
                    new Toast().prepareToast("Hata", data['msg'], "danger");
                    toast.closeToast()
                    console.error(data['msg']);
                }
            })
            .catch((error) => {
                new Toast().prepareToast("Hata", "Ders programı oluşturulurken hata oluştu. Detaylar için geliştirici konsoluna bakın", "danger");
                console.error(error);
            });
    }

    // Halihazırda sayfada bulunan program kartları varsa ilklendir
    const existingContainer = document.getElementById('schedule_container');
    if (existingContainer && existingContainer.querySelector('.schedule-card')) {
        initWeekNavigation(existingContainer);
        initMobileDayNavigation(existingContainer);
    }
});
