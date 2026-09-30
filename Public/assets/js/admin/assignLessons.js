/**
 * Ders Atama Sayfası JavaScript Yönetimi (TomSelect Entegrasyonlu)
 */
document.addEventListener('DOMContentLoaded', function () {
    const academicYearSelect = document.getElementById('academic_year');
    const semesterSelect = document.getElementById('semester');
    const programSelect = document.getElementById('program_id');
    const btnExportExcel = document.getElementById('btnExportExcel');
    const btnExportAllExcel = document.getElementById('btnExportAllExcel');
    const btnAddLesson = document.getElementById('btnAddLesson');
    const btnToggleAllLecturers = document.getElementById('btnToggleAllLecturers');
    const btnSaveAllList = document.querySelectorAll('.btn-save-all');
    const tableBody = document.getElementById('assignmentTableBody');

    const lecturerTemplate = document.getElementById('lecturerOptionsTemplate');
    const lessonTypeTemplate = document.getElementById('lessonTypeOptionsTemplate');
    const semesterNoTemplate = document.getElementById('semesterNoOptionsTemplate');
    const classroomTypeTemplate = document.getElementById('classroomTypeOptionsTemplate');
    const buildingTemplate = document.getElementById('buildingOptionsTemplate');

    // UBS İçe Aktarma Elemanları
    const btnOpenUbsModal = document.getElementById('btnOpenUbsModal');
    const ubsImportModalEl = document.getElementById('ubsImportModal');
    const ubsUploadForm = document.getElementById('ubsUploadForm');
    const ubsFileInput = document.getElementById('ubsFileInput');
    const ubsModalTargetInfo = document.getElementById('ubsModalTargetInfo');
    const ubsImportBanner = document.getElementById('ubsImportBanner');
    const ubsProgramName = document.getElementById('ubsProgramName');
    const ubsSummaryText = document.getElementById('ubsSummaryText');
    const btnCancelUbsMode = document.getElementById('btnCancelUbsMode');
    const btnSaveAllUbs = document.getElementById('btnSaveAllUbs');

    let isUbsMode = false;
    let ubsModalInstance = null;

    if (!tableBody || !programSelect) return;

    let showAllLecturers = false;
    let cachedLessons = [];

    // Sayfa açıldığında program seçili ise doğrudan getir ve linki güncelle
    updateAddLessonButton();
    if (programSelect.value) {
        fetchLessons();
    }

    // Program veya dönem değiştiğinde otomatik getir
    programSelect.addEventListener('change', function () {
        updateAddLessonButton();
        fetchLessons();
    });
    semesterSelect.addEventListener('change', fetchLessons);
    academicYearSelect.addEventListener('change', fetchLessons);

    // "Ders Ekle" linkini seçili programa göre dinamik güncelle
    function updateAddLessonButton() {
        if (!btnAddLesson) return;
        const programId = programSelect.value;
        if (programId && programId !== '0') {
            btnAddLesson.href = `/admin/addlesson/${encodeURIComponent(programId)}`;
            btnAddLesson.classList.remove('disabled');
        } else {
            btnAddLesson.href = '/admin/addlesson';
        }
    }

    // "Tüm Hocaları Göster / Sadece Bölüm Hocalarını Göster" Toggle
    if (btnToggleAllLecturers) {
        btnToggleAllLecturers.addEventListener('click', function () {
            showAllLecturers = !showAllLecturers;
            this.dataset.showAll = showAllLecturers ? 'true' : 'false';

            if (showAllLecturers) {
                this.className = 'btn btn-sm btn-outline-info';
                this.innerHTML = '<i class="bi bi-people me-1"></i> Sadece Bölüm Hocalarını Göster';
            } else {
                this.className = 'btn btn-sm btn-outline-secondary';
                this.innerHTML = '<i class="bi bi-globe me-1"></i> Tüm Hocaları Göster';
            }

            // Tablodaki mevcut hoca select kutularını TomSelect ile yeniden oluştur (seçimleri koruyarak)
            refreshAllLecturerSelects();
        });
    }

    // Excel İndirme (Seçili Program)
    btnExportExcel.addEventListener('click', function () {
        const programId = programSelect.value;
        const academicYear = academicYearSelect.value;
        const semester = semesterSelect.value;

        if (!programId) {
            showToast('Uyarı', 'Lütfen bir program seçiniz.', 'warning');
            return;
        }

        showExportOptionsModal((options) => {
            const params = new URLSearchParams({
                academic_year: academicYear,
                semester: semester,
                program_id: programId,
                show_classroom_type: options.show_classroom_type ? '1' : '0',
                show_building: options.show_building ? '1' : '0'
            });

            window.location.href = `/admin/exportlessonassignments?${params.toString()}`;
        });
    });

    // Excel İndirme (Tüm Yetkili Programlar)
    if (btnExportAllExcel) {
        btnExportAllExcel.addEventListener('click', function () {
            const academicYear = academicYearSelect.value;
            const semester = semesterSelect.value;

            showExportOptionsModal((options) => {
                const params = new URLSearchParams({
                    academic_year: academicYear,
                    semester: semester,
                    show_classroom_type: options.show_classroom_type ? '1' : '0',
                    show_building: options.show_building ? '1' : '0'
                });

                window.location.href = `/admin/exportalllessonassignments?${params.toString()}`;
            });
        });
    }

    /**
     * Diğer export işlemlerinde olduğu gibi Excel dışa aktarma seçeneklerini soran modal
     */
    function showExportOptionsModal(onConfirm) {
        if (typeof Modal === 'undefined') {
            onConfirm({ show_classroom_type: true, show_building: true });
            return;
        }

        const modal = new Modal();
        const content = `
            <div class="p-2">
                <p class="mb-3 border-bottom pb-2">Excel tablosunda görünmesini istediğiniz ek sütunları seçin:</p>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="export_show_classroom_type" checked>
                    <label class="form-check-label" for="export_show_classroom_type">Derslik türü</label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="export_show_building" checked>
                    <label class="form-check-label" for="export_show_building">Bina</label>
                </div>
            </div>
        `;

        modal.prepareModal("Ders Atamaları Excel Dışa Aktarma Seçenekleri", content, true, true, "md");
        modal.confirmButton.textContent = "Dışa Aktar";
        modal.showModal();

        modal.confirmButton.addEventListener("click", () => {
            const classroomTypeCheck = document.getElementById("export_show_classroom_type");
            const buildingCheck = document.getElementById("export_show_building");

            const options = {
                show_classroom_type: classroomTypeCheck ? classroomTypeCheck.checked : true,
                show_building: buildingCheck ? buildingCheck.checked : true
            };

            modal.closeModal();
            onConfirm(options);
        });
    }

    // Dersleri Getir
    async function fetchLessons() {
        if (isUbsMode) {
            isUbsMode = false;
            if (ubsImportBanner) ubsImportBanner.classList.add('d-none');
        }

        const programId = programSelect.value;
        const academicYear = academicYearSelect.value;
        const semester = semesterSelect.value;

        if (!programId) {
            destroyAllTomSelects();
            tableBody.innerHTML = `
                <tr>
                    <td colspan="11" class="text-center py-4 text-muted">
                        <i class="bi bi-exclamation-circle fs-3 d-block mb-2"></i>
                        Lütfen bir program seçiniz.
                    </td>
                </tr>`;
            toggleSaveAllButtons(false);
            return;
        }

        destroyAllTomSelects();
        tableBody.innerHTML = `
            <tr>
                <td colspan="11" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Yükleniyor...</span>
                    </div>
                    <div class="mt-2 text-muted">Dersler yükleniyor...</div>
                </td>
            </tr>`;

        try {
            const response = await fetch('/ajax/getProgramLessonsForAssignment', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    program_id: programId,
                    academic_year: academicYear,
                    semester: semester
                })
            });

            const result = await response.json();

            if (!response.ok || result.status !== 'success') {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="11" class="text-center py-4 text-danger">
                            <i class="bi bi-x-circle fs-3 d-block mb-2"></i>
                            ${result.msg || 'Dersler yüklenirken bir hata oluştu.'}
                        </td>
                    </tr>`;
                toggleSaveAllButtons(false);
                return;
            }

            cachedLessons = result.lessons || [];
            renderTable(cachedLessons, result.valid_semesters || []);
        } catch (error) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="11" class="text-center py-4 text-danger">
                        <i class="bi bi-wifi-off fs-3 d-block mb-2"></i>
                        Sunucu ile iletişim kurulamadı.
                    </td>
                </tr>`;
            toggleSaveAllButtons(false);
        }
    }

    function toggleSaveAllButtons(show) {
        btnSaveAllList.forEach(btn => {
            if (show) {
                btn.classList.remove('d-none');
            } else {
                btn.classList.add('d-none');
            }
        });
    }

    // Seçili programın departmentId'sini döner
    function getCurrentDepartmentId() {
        const selectedOption = programSelect.selectedOptions[0];
        return selectedOption ? parseInt(selectedOption.dataset.departmentId, 10) || 0 : 0;
    }

    // Hoca seçenekleri HTML'ini üretir
    function buildLecturerSelectOptions(currentLecturerId) {
        const currentDeptId = getCurrentDepartmentId();
        const allLecturerOptions = Array.from(lecturerTemplate.content.querySelectorAll('option'));

        let html = '<option value="">-- Atanmamış --</option>';

        const deptLecturers = [];
        const otherLecturers = [];

        allLecturerOptions.forEach(opt => {
            if (!opt.value) return; // boş seçeneği atla
            const deptId = parseInt(opt.dataset.departmentId, 10) || 0;
            const item = {
                value: opt.value,
                name: opt.textContent.trim(),
                deptId: deptId,
                deptName: opt.dataset.departmentName || 'Diğer'
            };

            if (currentDeptId > 0 && deptId === currentDeptId) {
                deptLecturers.push(item);
            } else {
                otherLecturers.push(item);
            }
        });

        const curIdStr = currentLecturerId ? String(currentLecturerId) : '';

        // Eğer showAllLecturers aktifse optgroup'lar ile tümünü göster
        if (showAllLecturers) {
            if (deptLecturers.length > 0) {
                html += '<optgroup label="Bölüm Öğretim Elemanları">';
                deptLecturers.forEach(l => {
                    const sel = (curIdStr === l.value) ? ' selected' : '';
                    html += `<option value="${l.value}"${sel}>${escapeHtml(l.name)}</option>`;
                });
                html += '</optgroup>';
            }

            if (otherLecturers.length > 0) {
                html += '<optgroup label="Diğer Bölüm ve Birim Hocaları">';
                otherLecturers.forEach(l => {
                    const sel = (curIdStr === l.value) ? ' selected' : '';
                    html += `<option value="${l.value}"${sel}>${escapeHtml(l.name)} (${escapeHtml(l.deptName)})</option>`;
                });
                html += '</optgroup>';
            }
        } else {
            // Sadece Bölüm Hocaları listelenir
            deptLecturers.forEach(l => {
                const sel = (curIdStr === l.value) ? ' selected' : '';
                html += `<option value="${l.value}"${sel}>${escapeHtml(l.name)}</option>`;
            });

            // Eğer atanmış hoca başka bölümden ise seçeneği koru
            if (curIdStr) {
                const assignedInOther = otherLecturers.find(l => l.value === curIdStr);
                if (assignedInOther) {
                    html += `<optgroup label="Atanmış Öğretim Elemanı">
                        <option value="${assignedInOther.value}" selected>${escapeHtml(assignedInOther.name)} (${escapeHtml(assignedInOther.deptName)})</option>
                    </optgroup>`;
                }
            }
        }

        return html;
    }

    // Bir select elementinde TomSelect başlatır
    function initTomSelect(selectElement) {
        if (typeof TomSelect === 'undefined' || !selectElement) return;

        if (selectElement.tomselect) {
            selectElement.tomselect.destroy();
        }

        try {
            new TomSelect(selectElement, {
                placeholder: '-- Atanmamış --',
                allowEmptyOption: true,
                maxOptions: 500,
                dropdownParent: 'body', // Tabloda taşma/kesilme (clipping) olmasını engeller
                controlInput: '<input>',
                render: {
                    no_results: function () {
                        return '<div class="no-results p-2 text-muted small">Hoca bulunamadı</div>';
                    }
                }
            });
        } catch (e) {
            console.warn('TomSelect init warning:', e);
        }
    }

    // Tablodaki tüm TomSelect örneklerini temizler
    function destroyAllTomSelects() {
        const selects = tableBody.querySelectorAll('.lesson-lecturer');
        selects.forEach(sel => {
            if (sel.tomselect) {
                try {
                    sel.tomselect.destroy();
                } catch (e) {}
            }
        });
    }

    // Tablodaki tüm hoca select kutularını yeniden doldurur ve TomSelect'i yeniler
    function refreshAllLecturerSelects() {
        const rows = tableBody.querySelectorAll('tr[data-lesson-id]');
        rows.forEach(tr => {
            const selectLec = tr.querySelector('.lesson-lecturer');
            if (selectLec) {
                const currentVal = selectLec.tomselect ? selectLec.tomselect.getValue() : selectLec.value;
                if (selectLec.tomselect) {
                    try {
                        selectLec.tomselect.destroy();
                    } catch (e) {}
                }
                selectLec.innerHTML = buildLecturerSelectOptions(currentVal);
                selectLec.value = currentVal;
                initTomSelect(selectLec);
            }
        });
    }

    // Yarıyıl seçenekleri HTML'ini üretir
    function buildSemesterSelectOptions(currentSemesterNo, validSemesters) {
        if (validSemesters && validSemesters.length > 0) {
            const numbers = [...validSemesters];
            const curNum = currentSemesterNo ? parseInt(currentSemesterNo, 10) : null;
            if (curNum && !numbers.includes(curNum)) {
                numbers.push(curNum);
                numbers.sort((a, b) => a - b);
            }
            return numbers.map(n => `<option value="${n}">${n}. Yarıyıl</option>`).join('');
        }
        return semesterNoTemplate ? semesterNoTemplate.innerHTML : '';
    }

    // Tabloyu Oluştur
    function renderTable(lessons, validSemesters = []) {
        destroyAllTomSelects();

        if (!lessons || lessons.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="11" class="text-center py-4 text-muted">
                        <i class="bi bi-folder-x fs-3 d-block mb-2"></i>
                        Bu dönem ve programa ait kayıtlı ders bulunamadı.
                    </td>
                </tr>`;
            toggleSaveAllButtons(false);
            return;
        }

        toggleSaveAllButtons(true);
        tableBody.innerHTML = '';

        lessons.forEach(lesson => {
            const tr = document.createElement('tr');
            tr.dataset.lessonId = lesson.id;

            const codeStr = lesson.code || '';
            const groupStr = (lesson.group_no && lesson.group_no > 0) ? lesson.group_no : '0';
            const semNoStr = lesson.semester_no ? `${lesson.semester_no}. Yarıyıl` : '—';

            tr.innerHTML = `
                <td class="fw-bold">${escapeHtml(codeStr)}</td>
                <td class="text-center">${escapeHtml(groupStr)}</td>
                <td>
                    <a href="/admin/lesson/${lesson.id}" target="_blank" class="text-dark fw-semibold text-decoration-none">
                        ${escapeHtml(lesson.name)}
                        <i class="bi bi-box-arrow-up-right text-muted small ms-1"></i>
                    </a>
                </td>
                <td>
                    <select class="form-select form-select-sm text-center lesson-semester-no">
                        ${buildSemesterSelectOptions(lesson.semester_no, validSemesters)}
                    </select>
                </td>
                <td>
                    <select class="form-select form-select-sm lesson-type">
                        ${lessonTypeTemplate.innerHTML}
                    </select>
                </td>
                <td>
                    <input type="number" min="0" class="form-control form-control-sm text-center lesson-size" value="${lesson.size}">
                </td>
                <td>
                    <input type="number" min="1" class="form-control form-control-sm text-center lesson-hours" value="${lesson.hours}">
                </td>
                <td style="min-width: 250px;">
                    <select class="form-select form-select-sm lesson-lecturer">
                        ${buildLecturerSelectOptions(lesson.lecturer_id)}
                    </select>
                </td>
                <td>
                    <select class="form-select form-select-sm lesson-classroom-type">
                        ${classroomTypeTemplate.innerHTML}
                    </select>
                </td>
                <td>
                    <select class="form-select form-select-sm lesson-building">
                        ${buildingTemplate.innerHTML}
                    </select>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-primary btn-save-row" title="Bu dersi kaydet">
                        <i class="bi bi-save"></i>
                    </button>
                    <span class="status-icon ms-1"></span>
                </td>
            `;

            // Seçili yarıyıl
            const selectSemesterNo = tr.querySelector('.lesson-semester-no');
            if (selectSemesterNo && lesson.semester_no) {
                selectSemesterNo.value = String(lesson.semester_no);
            }

            // Seçili tür
            const selectType = tr.querySelector('.lesson-type');
            if (selectType && lesson.type) {
                selectType.value = String(lesson.type);
            }

            // Seçili derslik türü
            const selectClassroomType = tr.querySelector('.lesson-classroom-type');
            if (selectClassroomType && lesson.classroom_type) {
                selectClassroomType.value = String(lesson.classroom_type);
            }

            // Seçili bina
            const selectBuilding = tr.querySelector('.lesson-building');
            if (selectBuilding && lesson.building_id) {
                selectBuilding.value = String(lesson.building_id);
            }

            // Hoca için TomSelect başlat
            const selectLec = tr.querySelector('.lesson-lecturer');
            initTomSelect(selectLec);

            // Satır içi kaydet butonu
            const btnSave = tr.querySelector('.btn-save-row');
            btnSave.addEventListener('click', () => saveRow(tr));

            tableBody.appendChild(tr);
        });
    }

    // Tekil Satır Kaydet
    async function saveRow(tr) {
        const lessonId = tr.dataset.lessonId;
        const semesterNoSelect = tr.querySelector('.lesson-semester-no');
        const typeSelect = tr.querySelector('.lesson-type');
        const sizeInput = tr.querySelector('.lesson-size');
        const hoursInput = tr.querySelector('.lesson-hours');
        const lecturerSelect = tr.querySelector('.lesson-lecturer');
        const classroomTypeSelect = tr.querySelector('.lesson-classroom-type');
        const buildingSelect = tr.querySelector('.lesson-building');
        const btnSave = tr.querySelector('.btn-save-row');
        const statusIcon = tr.querySelector('.status-icon');

        const semesterNo = semesterNoSelect ? parseInt(semesterNoSelect.value, 10) : null;
        const size = parseInt(sizeInput.value, 10);
        const hours = parseInt(hoursInput.value, 10);
        const type = parseInt(typeSelect.value, 10) || 1;
        const lecturerId = lecturerSelect.tomselect ? lecturerSelect.tomselect.getValue() : lecturerSelect.value;
        const classroomType = classroomTypeSelect.value;
        const buildingId = buildingSelect.value;
        const academicYear = academicYearSelect.value;
        const semester = semesterSelect.value;

        if (isNaN(size) || size < 0) {
            showToast('Uyarı', 'Mevcut geçerli bir sayı olmalıdır.', 'warning');
            sizeInput.focus();
            return;
        }

        if (isNaN(hours) || hours < 1) {
            showToast('Uyarı', 'Ders saati en az 1 olmalıdır.', 'warning');
            hoursInput.focus();
            return;
        }

        // Butonu devre dışı bırak ve spinner göster
        btnSave.disabled = true;
        btnSave.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';
        statusIcon.innerHTML = '';

        try {
            const response = await fetch('/ajax/updateLessonAssignment', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    id: lessonId,
                    type: type,
                    semester_no: semesterNo,
                    size: size,
                    hours: hours,
                    lecturer_id: lecturerId,
                    classroom_type: classroomType,
                    building_id: buildingId,
                    academic_year: academicYear,
                    semester: semester
                })
            });

            const result = await response.json();

            btnSave.disabled = false;
            btnSave.innerHTML = '<i class="bi bi-save"></i>';

            if (response.ok && result.status === 'success') {
                statusIcon.innerHTML = '<i class="bi bi-check-circle-fill text-success fs-5"></i>';
                showToast('Başarılı', result.msg || 'Ders güncellendi.', 'success');
                setTimeout(() => {
                    statusIcon.innerHTML = '';
                }, 3000);
            } else {
                statusIcon.innerHTML = '<i class="bi bi-x-circle-fill text-danger fs-5"></i>';
                showToast('Hata', result.msg || 'Ders güncellenemedi.', 'danger');
            }
        } catch (error) {
            btnSave.disabled = false;
            btnSave.innerHTML = '<i class="bi bi-save"></i>';
            statusIcon.innerHTML = '<i class="bi bi-x-circle-fill text-danger fs-5"></i>';
            showToast('Hata', 'Sunucu bağlantı hatası.', 'danger');
        }
    }

    // Tümünü Kaydet (Header ve Footer butonları)
    btnSaveAllList.forEach(btn => {
        btn.addEventListener('click', handleSaveAll);
    });

    async function handleSaveAll() {
        if (isUbsMode) {
            handleSaveAllUbs();
            return;
        }

        const rows = tableBody.querySelectorAll('tr[data-lesson-id]');
        if (rows.length === 0) return;

        const assignments = [];
        for (const tr of rows) {
            const lessonId = tr.dataset.lessonId;
            const semesterNoSelect = tr.querySelector('.lesson-semester-no');
            const semesterNo = semesterNoSelect ? parseInt(semesterNoSelect.value, 10) : null;
            const type = parseInt(tr.querySelector('.lesson-type').value, 10) || 1;
            const size = parseInt(tr.querySelector('.lesson-size').value, 10) || 0;
            const hours = parseInt(tr.querySelector('.lesson-hours').value, 10) || 1;
            const lecturerSelect = tr.querySelector('.lesson-lecturer');
            const lecturerId = lecturerSelect.tomselect ? lecturerSelect.tomselect.getValue() : lecturerSelect.value;
            const classroomType = tr.querySelector('.lesson-classroom-type').value;
            const buildingId = tr.querySelector('.lesson-building').value;

            assignments.push({
                id: lessonId,
                type: type,
                semester_no: semesterNo,
                size: size,
                hours: hours,
                lecturer_id: lecturerId,
                classroom_type: classroomType,
                building_id: buildingId
            });
        }

        btnSaveAllList.forEach(btn => {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Kaydediliyor...';
        });

        try {
            const response = await fetch('/ajax/bulkUpdateLessonAssignments', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    assignments: assignments,
                    academic_year: academicYearSelect.value,
                    semester: semesterSelect.value
                })
            });

            const result = await response.json();

            btnSaveAllList.forEach(btn => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-all me-1"></i> Tüm Değişiklikleri Kaydet';
            });

            if (response.ok && result.status === 'success') {
                showToast('Başarılı', result.msg || 'Tüm ders atamaları kaydedildi.', 'success');
                rows.forEach(tr => {
                    const icon = tr.querySelector('.status-icon');
                    if (icon) icon.innerHTML = '<i class="bi bi-check-circle-fill text-success fs-5"></i>';
                });
                setTimeout(() => {
                    rows.forEach(tr => {
                        const icon = tr.querySelector('.status-icon');
                        if (icon) icon.innerHTML = '';
                    });
                }, 3000);
            } else {
                showToast('Hata', result.msg || 'Toplu kaydetme sırasında hata oluştu.', 'danger');
            }
        } catch (error) {
            btnSaveAllList.forEach(btn => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-all me-1"></i> Tüm Değişiklikleri Kaydet';
            });
            showToast('Hata', 'Sunucu bağlantı hatası.', 'danger');
        }
    }

    // ==========================================
    // UBS İçe Aktarma Yönetimi
    // ==========================================

    if (btnOpenUbsModal) {
        btnOpenUbsModal.addEventListener('click', function () {
            const programId = programSelect.value;
            if (!programId || programId === '0') {
                showToast('Uyarı', 'Lütfen önce yukarıdaki filtreden bir Program seçiniz.', 'warning');
                return;
            }
            const programText = programSelect.selectedOptions[0]?.textContent.trim() || '';
            const yearText = academicYearSelect.value;
            const semText = semesterSelect.value;
            if (ubsModalTargetInfo) {
                ubsModalTargetInfo.textContent = `Program: ${programText} | Dönem: ${yearText} (${semText})`;
            }
            if (ubsFileInput) ubsFileInput.value = '';
            if (typeof bootstrap !== 'undefined' && ubsImportModalEl) {
                ubsModalInstance = bootstrap.Modal.getOrCreateInstance(ubsImportModalEl);
                ubsModalInstance.show();
            }
        });
    }

    if (ubsUploadForm) {
        ubsUploadForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const programId = programSelect.value;
            const academicYear = academicYearSelect.value;
            const semester = semesterSelect.value;
            const file = ubsFileInput.files[0];
            if (!file) {
                showToast('Uyarı', 'Lütfen bir Excel dosyası seçiniz.', 'warning');
                return;
            }

            const submitBtn = document.getElementById('btnSubmitUbsFile');
            const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Çözümleniyor...';
            }

            const formData = new FormData();
            formData.append('importFile', file);
            formData.append('program_id', programId);
            formData.append('academic_year', academicYear);
            formData.append('semester', semester);

            try {
                const response = await fetch('/ajax/parseUbsLessons', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
                const result = await response.json();

                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnHtml;
                }

                if (!response.ok || result.status !== 'success') {
                    showToast('Hata', result.msg || 'Dosya çözümlenirken bir hata oluştu.', 'danger');
                    return;
                }

                if (ubsModalInstance) {
                    ubsModalInstance.hide();
                }

                const data = result.data;
                isUbsMode = true;
                if (ubsImportBanner) {
                    ubsImportBanner.classList.remove('d-none');
                    if (ubsProgramName) ubsProgramName.textContent = data.program.name;
                    if (ubsSummaryText) {
                        ubsSummaryText.innerHTML = `Toplam <strong>${data.items.length}</strong> ders listelendi. <span class="badge bg-success">${data.existing_count} Mevcut</span> (güncellenecek), <span class="badge bg-warning text-dark">${data.new_count} Yeni Ders</span> (eklenecek), <span class="badge bg-secondary">${data.missing_in_ubs_count || 0} UBS'de Yok</span> (sistemde kayıtlı). <span class="text-muted">(Hocasız ${data.skipped_no_lecturer} satır otomatik atlandı)</span>`;
                    }
                }

                renderUbsTable(data.items);
                showToast('Bilgi', 'Bölümdeki tüm dersler ve UBS eşleştirmeleri listelendi.', 'info');
            } catch (err) {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnHtml;
                }
                showToast('Hata', 'Sunucu ile iletişim hatası: ' + err.message, 'danger');
            }
        });
    }

    if (btnCancelUbsMode) {
        btnCancelUbsMode.addEventListener('click', function () {
            isUbsMode = false;
            if (ubsImportBanner) ubsImportBanner.classList.add('d-none');
            fetchLessons();
        });
    }

    if (btnSaveAllUbs) {
        btnSaveAllUbs.addEventListener('click', handleSaveAllUbs);
    }

    /**
     * UBS'den gelen ders listesini tabloya çizer
     */
    function renderUbsTable(items) {
        destroyAllTomSelects();
        tableBody.innerHTML = '';

        if (!items || items.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="11" class="text-center py-4 text-muted">
                        <i class="bi bi-info-circle fs-3 d-block mb-2"></i>
                        UBS dosyasında geçerli ve hocası atanmış ders bulunamadı.
                    </td>
                </tr>`;
            toggleSaveAllButtons(false);
            return;
        }

        items.forEach((item, index) => {
            const tr = document.createElement('tr');
            tr.dataset.lessonId = item.lesson_id || '';
            tr.dataset.code = item.code || '';
            tr.dataset.groupNo = item.group_no ?? 0;
            tr.dataset.name = item.name || '';
            tr.dataset.status = item.status;

            // Görsel Vurgu ve Rozet
            let statusBadge = '';
            if (item.status === 'new') {
                tr.className = 'table-warning border-warning';
                statusBadge = `<span class="badge bg-warning text-dark"><i class="bi bi-plus-circle me-1"></i>Yeni</span>`;
            } else if (item.status === 'missing_in_ubs') {
                tr.className = 'table-light text-muted';
                statusBadge = `<span class="badge bg-secondary"><i class="bi bi-dash-circle me-1"></i>UBS'de Yok</span>`;
            } else {
                tr.className = 'table-light';
                statusBadge = `<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Mevcut</span>`;
            }

            const oldSizeInfo = (item.status === 'existing' && item.db_size !== null && item.db_size !== item.size)
                ? `<small class="text-muted d-block text-center" style="font-size: 0.75rem;">Eski: ${item.db_size}</small>`
                : '';

            const codeHtml = item.lesson_id
                ? `<a href="/admin/lesson/${item.lesson_id}" target="_blank" class="text-dark fw-bold text-decoration-none" title="Ders Detayına Git">
                       ${escapeHtml(item.code)} <i class="bi bi-box-arrow-up-right text-muted small" style="font-size: 0.75rem;"></i>
                   </a>`
                : `<strong class="text-dark">${escapeHtml(item.code)}</strong>`;

            tr.innerHTML = `
                <td class="text-nowrap">
                    ${codeHtml}
                </td>
                <td class="text-center">
                    <span class="badge bg-secondary">${item.group_no}</span>
                </td>
                <td>
                    <div class="d-flex align-items-center gap-1">
                        ${statusBadge}
                        <span class="fw-semibold">${escapeHtml(item.name)}</span>
                    </div>
                </td>
                <td>
                    <select class="form-select form-select-sm lesson-semester-no">
                        ${buildSemesterSelectOptions(item.semester_no)}
                    </select>
                </td>
                <td>
                    <select class="form-select form-select-sm lesson-type">
                        ${lessonTypeTemplate ? lessonTypeTemplate.innerHTML : ''}
                    </select>
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm text-center lesson-size" 
                           value="${item.size ?? 0}" min="0" title="Öğrenci Mevcudu">
                    ${oldSizeInfo}
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm text-center lesson-hours" 
                           value="${item.hours ?? 1}" min="1" title="Haftalık Toplam Ders Saati (T+U+L)">
                </td>
                <td>
                    <select class="form-select form-select-sm lesson-lecturer" id="ubs_lecturer_${index}">
                        ${buildLecturerSelectOptions(item.lecturer_id)}
                    </select>
                </td>
                <td>
                    <select class="form-select form-select-sm lesson-classroom-type">
                        ${classroomTypeTemplate ? classroomTypeTemplate.innerHTML : ''}
                    </select>
                </td>
                <td>
                    <select class="form-select form-select-sm lesson-building">
                        ${buildingTemplate ? buildingTemplate.innerHTML : ''}
                    </select>
                </td>
                <td class="text-center text-nowrap">
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-danger btn-remove-ubs-row" title="Bu satırı tablodan çıkar">
                            <i class="bi bi-trash"></i>
                        </button>
                        <button type="button" class="btn btn-outline-success btn-save-ubs-row" title="Bu dersi kaydet / güncelle">
                            <i class="bi bi-check-lg"></i>
                        </button>
                    </div>
                </td>
            `;

            // Değerleri seçili hale getir
            const selectSemesterNo = tr.querySelector('.lesson-semester-no');
            if (selectSemesterNo && item.semester_no) {
                selectSemesterNo.value = String(item.semester_no);
            }
            const selectType = tr.querySelector('.lesson-type');
            if (selectType && item.type) {
                selectType.value = String(item.type);
            }
            const selectClassroomType = tr.querySelector('.lesson-classroom-type');
            if (selectClassroomType && item.classroom_type) {
                selectClassroomType.value = String(item.classroom_type);
            }
            const selectBuilding = tr.querySelector('.lesson-building');
            if (selectBuilding && item.building_id) {
                selectBuilding.value = String(item.building_id);
            }

            tableBody.appendChild(tr);

            // Hoca Select'ini TomSelect ile başlat
            const lecturerSelect = tr.querySelector(`#ubs_lecturer_${index}`);
            if (lecturerSelect) {
                initTomSelect(lecturerSelect);
            }

            // Satırı tablodan silme butonu
            const btnRemove = tr.querySelector('.btn-remove-ubs-row');
            if (btnRemove) {
                btnRemove.addEventListener('click', function () {
                    tr.remove();
                    updateUbsRowCounts();
                    showToast('Bilgi', `'${item.name}' dersi tablodan çıkarıldı.`, 'info');
                });
            }

            // Satırı tekil kaydetme butonu
            const btnSaveRow = tr.querySelector('.btn-save-ubs-row');
            if (btnSaveRow) {
                btnSaveRow.addEventListener('click', function () {
                    saveSingleUbsRow(tr, btnSaveRow);
                });
            }
        });

        toggleSaveAllButtons(true);
    }

    /**
     * Kalan satır sayılarını kontrol edip banner'ı günceller
     */
    function updateUbsRowCounts() {
        const rows = tableBody.querySelectorAll('tr[data-status]');
        if (rows.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="11" class="text-center py-4 text-muted">
                        <i class="bi bi-info-circle fs-3 d-block mb-2"></i>
                        Tabloda kaydedilecek ders kalmadı.
                    </td>
                </tr>`;
            toggleSaveAllButtons(false);
            return;
        }

        let existing = 0;
        let newCount = 0;
        let missingCount = 0;
        rows.forEach(r => {
            if (r.dataset.status === 'new') newCount++;
            else if (r.dataset.status === 'missing_in_ubs') missingCount++;
            else existing++;
        });

        if (ubsSummaryText) {
            ubsSummaryText.innerHTML = `Kalan <strong>${rows.length}</strong> ders. <span class="badge bg-success">${existing} Mevcut</span>, <span class="badge bg-warning text-dark">${newCount} Yeni Ders</span>, <span class="badge bg-secondary">${missingCount} UBS'de Yok</span>.`;
        }
    }

    /**
     * Tek bir UBS satırını sunucuya kaydeder
     */
    async function saveSingleUbsRow(tr, btnSave) {
        const lessonId = tr.dataset.lessonId || null;
        const code = tr.dataset.code;
        const groupNo = parseInt(tr.dataset.groupNo, 10) || 0;
        const name = tr.dataset.name;
        const programId = parseInt(programSelect.value, 10);
        const academicYear = academicYearSelect.value;
        const semester = semesterSelect.value;

        const size = parseInt(tr.querySelector('.lesson-size')?.value, 10) || 0;
        const hours = parseInt(tr.querySelector('.lesson-hours')?.value, 10) || 1;
        const semesterNo = parseInt(tr.querySelector('.lesson-semester-no')?.value, 10) || 1;
        const type = parseInt(tr.querySelector('.lesson-type')?.value, 10) || 1;
        const classroomType = parseInt(tr.querySelector('.lesson-classroom-type')?.value, 10) || 1;
        const buildingId = tr.querySelector('.lesson-building')?.value || null;

        const lecturerSelect = tr.querySelector('.lesson-lecturer');
        const lecturerId = lecturerSelect?.tomselect ? lecturerSelect.tomselect.getValue() : lecturerSelect?.value;

        const originalBtnHtml = btnSave.innerHTML;
        btnSave.disabled = true;
        btnSave.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        try {
            const response = await fetch('/ajax/saveUbsLesson', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    lesson_id: lessonId,
                    program_id: programId,
                    academic_year: academicYear,
                    semester: semester,
                    code: code,
                    group_no: groupNo,
                    name: name,
                    size: size,
                    hours: hours,
                    semester_no: semesterNo,
                    type: type,
                    classroom_type: classroomType,
                    building_id: buildingId,
                    lecturer_id: lecturerId
                })
            });

            const result = await response.json();
            btnSave.disabled = false;
            btnSave.innerHTML = originalBtnHtml;

            if (response.ok && result.status === 'success') {
                showToast('Başarılı', result.msg, 'success');
                if (result.lesson_id) {
                    tr.dataset.lessonId = result.lesson_id;
                    const codeCell = tr.querySelector('td:first-child');
                    if (codeCell) {
                        codeCell.innerHTML = `<a href="/admin/lesson/${result.lesson_id}" target="_blank" class="text-dark fw-bold text-decoration-none" title="Ders Detayına Git">
                            ${escapeHtml(code)} <i class="bi bi-box-arrow-up-right text-muted small" style="font-size: 0.75rem;"></i>
                        </a>`;
                    }
                }
                tr.dataset.status = 'saved';
                tr.className = 'table-success bg-opacity-25';
                const actionCell = tr.querySelector('td:last-child');
                if (actionCell) {
                    actionCell.innerHTML = `<span class="badge bg-success py-2 px-3"><i class="bi bi-check-circle me-1"></i>Kaydedildi</span>`;
                }
            } else {
                showToast('Hata', result.msg || 'Ders kaydedilemedi.', 'danger');
            }
        } catch (err) {
            btnSave.disabled = false;
            btnSave.innerHTML = originalBtnHtml;
            showToast('Hata', 'Sunucu bağlantı hatası: ' + err.message, 'danger');
        }
    }

    /**
     * Tablodaki tüm aktif UBS satırlarını topluca kaydeder
     */
    async function handleSaveAllUbs() {
        const rows = tableBody.querySelectorAll('tr[data-status]');
        const pendingRows = Array.from(rows).filter(r => r.dataset.status !== 'saved');

        if (pendingRows.length === 0) {
            showToast('Bilgi', 'Kaydedilecek yeni değişiklik bulunmamaktadır.', 'info');
            return;
        }

        const items = [];
        for (const tr of pendingRows) {
            const lecturerSelect = tr.querySelector('.lesson-lecturer');
            const lecturerId = lecturerSelect?.tomselect ? lecturerSelect.tomselect.getValue() : lecturerSelect?.value;

            items.push({
                lesson_id: tr.dataset.lessonId || null,
                code: tr.dataset.code,
                group_no: parseInt(tr.dataset.groupNo, 10) || 0,
                name: tr.dataset.name,
                size: parseInt(tr.querySelector('.lesson-size')?.value, 10) || 0,
                hours: parseInt(tr.querySelector('.lesson-hours')?.value, 10) || 1,
                semester_no: parseInt(tr.querySelector('.lesson-semester-no')?.value, 10) || 1,
                type: parseInt(tr.querySelector('.lesson-type')?.value, 10) || 1,
                classroom_type: parseInt(tr.querySelector('.lesson-classroom-type')?.value, 10) || 1,
                building_id: tr.querySelector('.lesson-building')?.value || null,
                lecturer_id: lecturerId || null
            });
        }

        btnSaveAllList.forEach(btn => {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Toplu Kaydediliyor...';
        });
        if (btnSaveAllUbs) {
            btnSaveAllUbs.disabled = true;
            btnSaveAllUbs.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Kaydediliyor...';
        }

        try {
            const response = await fetch('/ajax/bulkSaveUbsLessons', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    items: items,
                    program_id: parseInt(programSelect.value, 10),
                    academic_year: academicYearSelect.value,
                    semester: semesterSelect.value
                })
            });

            const result = await response.json();

            btnSaveAllList.forEach(btn => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-all me-1"></i> Tüm Değişiklikleri Kaydet';
            });
            if (btnSaveAllUbs) {
                btnSaveAllUbs.disabled = false;
                btnSaveAllUbs.innerHTML = '<i class="bi bi-check-all me-1"></i> Tüm UBS Derslerini Kaydet';
            }

            if (response.ok && result.status === 'success') {
                showToast('Başarılı', result.msg, 'success');
                // UBS modunu kapat ve sayfayı güncel verilerle yenile
                isUbsMode = false;
                if (ubsImportBanner) ubsImportBanner.classList.add('d-none');
                fetchLessons();
            } else {
                showToast('Hata', result.msg || 'Toplu kaydetme sırasında hata oluştu.', 'danger');
            }
        } catch (err) {
            btnSaveAllList.forEach(btn => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-all me-1"></i> Tüm Değişiklikleri Kaydet';
            });
            if (btnSaveAllUbs) {
                btnSaveAllUbs.disabled = false;
                btnSaveAllUbs.innerHTML = '<i class="bi bi-check-all me-1"></i> Tüm UBS Derslerini Kaydet';
            }
            showToast('Hata', 'Sunucu bağlantı hatası: ' + err.message, 'danger');
        }
    }

    function showToast(title, message, type = 'info') {
        if (typeof Toast !== 'undefined') {
            new Toast().prepareToast(title, message, type);
        } else {
            alert(`${title}: ${message}`);
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
});
