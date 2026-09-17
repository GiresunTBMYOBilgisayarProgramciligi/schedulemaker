/**
 * Ders Atama Sayfası JavaScript Yönetimi (TomSelect Entegrasyonlu)
 */
document.addEventListener('DOMContentLoaded', function () {
    const academicYearSelect = document.getElementById('academic_year');
    const semesterSelect = document.getElementById('semester');
    const programSelect = document.getElementById('program_id');
    const btnFetchLessons = document.getElementById('btnFetchLessons');
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
    btnFetchLessons.addEventListener('click', fetchLessons);

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
