/**
 * Toplu İşlem (Bulk Actions) Modülü
 *
 * DataTable tabanlı list sayfalarında çoklu seçim ve toplu silme/düzenleme
 * işlemlerini yöneten reusable JavaScript modülüdür.
 *
 * Kullanım:
 *   BulkActions.init({
 *       entity: 'program',
 *       deleteUrl: '/ajax/bulkDeletePrograms',
 *       updateUrl: '/ajax/bulkUpdatePrograms',
 *       deleteConfirmMessage: 'Seçili programlar silinecek...',
 *       editableFields: [
 *           { name: 'active', label: 'Durum', type: 'switch' },
 *           { name: 'department_id', label: 'Bölüm', type: 'select', options: [...] }
 *       ]
 *   });
 */
const BulkActions = (() => {
    let config = {};
    let selectedIds = new Set();
    let toolbar = null;
    let selectAllCheckbox = null;

    /**
     * Modülü başlatır
     * @param {Object} options Konfigürasyon
     */
    function init(options) {
        config = Object.assign({
            entity: '',
            deleteUrl: '',
            updateUrl: '',
            deleteConfirmMessage: 'Seçili kayıtları silmek istediğinize emin misiniz? Bu işlem geri alınamaz.',
            editableFields: [],
        }, options);

        selectedIds.clear();
        createToolbar();
        bindEvents();
    }

    /**
     * Toplu işlem toolbar'ını card-header'ın altına ekler
     */
    function createToolbar() {
        toolbar = document.getElementById('bulkActionsToolbar');
        if (!toolbar) return;

        updateToolbarVisibility();
    }

    /**
     * Checkbox olaylarını bağlar
     */
    function bindEvents() {
        // "Tümünü Seç" checkbox
        selectAllCheckbox = document.getElementById('bulkSelectAll');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', handleSelectAll);
        }

        // Satır checkbox'ları (event delegation)
        const tableBody = document.querySelector('.dataTable tbody');
        if (tableBody) {
            tableBody.addEventListener('change', function (e) {
                if (e.target && e.target.classList.contains('bulk-select-row')) {
                    handleRowSelect(e.target);
                }
            });
        }

        // DataTable draw olayında (sayfa değişikliği, filtreleme) selectAll durumunu güncelle
        if (typeof dataTable !== 'undefined') {
            dataTable.on('draw', function () {
                syncCheckboxesAfterDraw();
            });
        }

        // Toplu Sil butonu
        const deleteBtn = document.getElementById('bulkDeleteBtn');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', handleBulkDelete);
        }

        // Toplu Düzenle butonu
        const editBtn = document.getElementById('bulkEditBtn');
        if (editBtn) {
            editBtn.addEventListener('click', handleBulkEdit);
        }
    }

    /**
     * "Tümünü Seç" checkbox değişikliğini yönetir
     * @param {Event} e
     */
    function handleSelectAll(e) {
        const isChecked = e.target.checked;
        // Sadece görünür (mevcut sayfa) satırların checkbox'larını değiştir
        const visibleRows = document.querySelectorAll('.dataTable tbody tr:not(.dataTables_empty) .bulk-select-row');
        visibleRows.forEach(checkbox => {
            checkbox.checked = isChecked;
            const id = parseInt(checkbox.dataset.id);
            if (isChecked) {
                selectedIds.add(id);
            } else {
                selectedIds.delete(id);
            }
        });
        updateToolbarVisibility();
    }

    /**
     * Tek satır checkbox değişikliğini yönetir
     * @param {HTMLInputElement} checkbox
     */
    function handleRowSelect(checkbox) {
        const id = parseInt(checkbox.dataset.id);
        if (checkbox.checked) {
            selectedIds.add(id);
        } else {
            selectedIds.delete(id);
        }

        // selectAll checkbox durumunu güncelle
        updateSelectAllState();
        updateToolbarVisibility();
    }

    /**
     * DataTable draw olayında checkbox durumlarını senkronize eder
     */
    function syncCheckboxesAfterDraw() {
        const visibleCheckboxes = document.querySelectorAll('.dataTable tbody .bulk-select-row');
        visibleCheckboxes.forEach(checkbox => {
            const id = parseInt(checkbox.dataset.id);
            checkbox.checked = selectedIds.has(id);
        });
        updateSelectAllState();
        updateToolbarVisibility();
    }

    /**
     * "Tümünü Seç" checkbox'ının checked/indeterminate durumunu günceller
     */
    function updateSelectAllState() {
        if (!selectAllCheckbox) return;

        const visibleCheckboxes = document.querySelectorAll('.dataTable tbody .bulk-select-row');
        const checkedCount = Array.from(visibleCheckboxes).filter(cb => cb.checked).length;

        if (checkedCount === 0) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        } else if (checkedCount === visibleCheckboxes.length) {
            selectAllCheckbox.checked = true;
            selectAllCheckbox.indeterminate = false;
        } else {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = true;
        }
    }

    /**
     * Toolbar görünürlüğünü ve seçili sayısını günceller
     */
    function updateToolbarVisibility() {
        if (!toolbar) return;

        const count = selectedIds.size;
        if (count > 0) {
            toolbar.classList.remove('d-none');
            const countLabel = toolbar.querySelector('#bulkSelectedCount');
            if (countLabel) {
                countLabel.textContent = count;
            }
        } else {
            toolbar.classList.add('d-none');
        }
    }

    /**
     * Toplu silme işlemini yönetir
     */
    function handleBulkDelete() {
        if (selectedIds.size === 0) return;

        const confirmModal = new Modal();
        confirmModal.prepareModal(
            'Toplu Silme Onayı',
            `<p>${config.deleteConfirmMessage}</p><p class="mb-0"><strong>${selectedIds.size}</strong> adet kayıt silinecek.</p>`,
            true,
            true
        );
        confirmModal.confirmButton.textContent = gettext.delete;
        confirmModal.confirmButton.classList.remove('btn-success');
        confirmModal.confirmButton.classList.add('btn-danger');
        confirmModal.showModal();

        confirmModal.confirmButton.addEventListener('click', () => {
            confirmModal.closeModal();
            executeBulkAction(config.deleteUrl, { ids: Array.from(selectedIds) });
        });
    }

    /**
     * Toplu düzenleme modalını açar
     */
    function handleBulkEdit() {
        if (selectedIds.size === 0 || config.editableFields.length === 0) return;

        const editModal = new Modal();
        const formHTML = buildEditForm();
        editModal.prepareModal(
            'Toplu Düzenleme',
            formHTML,
            true,
            true,
            'lg'
        );
        editModal.confirmButton.textContent = 'Güncelle';
        editModal.showModal();

        // Event delegation: Modal gövdesindeki alan aktifleştirme (toggle) işlemlerini dinle
        editModal.body.addEventListener('change', function (e) {
            if (e.target && e.target.classList.contains('bulk-field-toggle')) {
                const fieldName = e.target.dataset.field;
                const inputs = editModal.body.querySelectorAll(`[name="${fieldName}"].bulk-field-input`);
                inputs.forEach(input => {
                    input.disabled = !e.target.checked;
                    if (!e.target.checked) {
                        if (input.type === 'checkbox') {
                            input.checked = false;
                        } else if (input.type !== 'radio') {
                            input.value = '';
                        }
                    }
                });
            }
        });

        editModal.confirmButton.addEventListener('click', () => {
            const fields = collectEditFormFields(editModal.body);
            if (Object.keys(fields).length === 0) {
                new Toast().prepareToast('Uyarı', 'En az bir alan seçmelisiniz.', 'warning');
                return;
            }
            editModal.closeModal();
            executeBulkAction(config.updateUrl, {
                ids: Array.from(selectedIds),
                fields: fields,
            });
        });

        // department-program cascade alanları için event bağla
        initDepartmentProgramCascade(editModal.body);
    }

    /**
     * Düzenleme formu HTML'ini oluşturur
     * @returns {string}
     */
    function buildEditForm() {
        let html = `<p class="text-muted mb-3"><strong>${selectedIds.size}</strong> adet kayıt güncellenecek. Sadece işaretlenen alanlar güncellenecektir.</p>`;
        html += '<div class="row">';

        config.editableFields.forEach(field => {
            if (field.type === 'department-program') {
                // Bölüm + Program cascade alanı (tam satır genişliğinde)
                html += '<div class="col-12 mb-3">';
                html += `<div class="form-check mb-2">`;
                html += `<input class="form-check-input bulk-field-toggle" type="checkbox" id="toggle_program_id" data-field="program_id">`;
                html += `<label class="form-check-label fw-bold" for="toggle_program_id">${field.label}</label>`;
                html += `</div>`;
                html += '<div class="ms-4 row g-2" id="bulk_dept_program_wrapper">';
                html += '<div class="col-12 col-md-6">';
                html += `<select class="form-select form-select-sm bulk-dept-select" id="bulk_department_id" disabled>`;
                html += `<option value="0">Bölüm Seçiniz</option>`;
                if (field.deptOptions && Array.isArray(field.deptOptions)) {
                    field.deptOptions.forEach(opt => {
                        html += `<option value="${opt.value}">${opt.label}</option>`;
                    });
                }
                html += `</select>`;
                html += '</div>';
                html += '<div class="col-12 col-md-6">';
                html += `<select class="form-select form-select-sm bulk-field-input" name="program_id" id="bulk_program_id" disabled>`;
                html += `<option value="0">Önce Bölüm Seçiniz</option>`;
                html += `</select>`;
                html += '</div>';
                html += '</div>'; // row
                html += '</div>'; // col-12
                return; // forEach devam
            }

            html += '<div class="col-md-6 mb-3">';
            html += `<div class="form-check mb-1">`;
            html += `<input class="form-check-input bulk-field-toggle" type="checkbox" id="toggle_${field.name}" data-field="${field.name}">`;
            html += `<label class="form-check-label fw-bold" for="toggle_${field.name}">${field.label}</label>`;
            html += `</div>`;

            if (field.type === 'switch') {
                html += `<div class="ms-4 mt-1">`;
                html += `<div class="form-check form-check-inline">`;
                html += `<input class="form-check-input bulk-field-input" type="radio" name="${field.name}" id="bulk_${field.name}_1" value="1" disabled>`;
                html += `<label class="form-check-label" for="bulk_${field.name}_1">Aktif</label>`;
                html += `</div>`;
                html += `<div class="form-check form-check-inline">`;
                html += `<input class="form-check-input bulk-field-input" type="radio" name="${field.name}" id="bulk_${field.name}_0" value="0" disabled checked>`;
                html += `<label class="form-check-label" for="bulk_${field.name}_0">Pasif</label>`;
                html += `</div>`;
                html += `</div>`;
            } else if (field.type === 'select') {
                html += `<select class="form-select form-select-sm ms-4 bulk-field-input" name="${field.name}" id="bulk_${field.name}" disabled>`;
                html += `<option value="">Seçiniz...</option>`;
                if (field.options && Array.isArray(field.options)) {
                    field.options.forEach(opt => {
                        html += `<option value="${opt.value}">${opt.label}</option>`;
                    });
                }
                html += `</select>`;
            } else if (field.type === 'text') {
                html += `<input type="text" class="form-control form-control-sm ms-4 bulk-field-input" name="${field.name}" id="bulk_${field.name}" disabled>`;
            }

            html += '</div>';
        });

        html += '</div>';
        return html;
    }

    /**
     * Düzenleme formundaki aktif alanların değerlerini toplar
     * @param {HTMLElement} modalBody
     * @returns {Object}
     */
    function collectEditFormFields(modalBody) {
        const fields = {};
        const toggles = modalBody.querySelectorAll('.bulk-field-toggle:checked');

        toggles.forEach(toggle => {
            const fieldName = toggle.dataset.field;
            const radioChecked = modalBody.querySelector(`[name="${fieldName}"].bulk-field-input[type="radio"]:checked`);
            if (radioChecked) {
                fields[fieldName] = radioChecked.value;
            } else {
                const input = modalBody.querySelector(`[name="${fieldName}"].bulk-field-input:not([type="radio"])`);
                if (input) {
                    if (input.type === 'checkbox') {
                        fields[fieldName] = input.checked;
                    } else {
                        fields[fieldName] = input.value;
                    }
                }
            }
        });

        return fields;
    }

    /**
     * Toplu düzenleme modalındaki Bölüm → Program cascade select bağlantısını kurar.
     * formEvents.js'teki departmentSelect → programSelect AJAX akışının aynısıdır.
     * @param {HTMLElement} container Modal gövdesi
     */
    function initDepartmentProgramCascade(container) {
        const deptToggle   = container.querySelector('#toggle_program_id');
        const deptSelect   = container.querySelector('#bulk_department_id');
        const programSelect = container.querySelector('#bulk_program_id');

        if (!deptToggle || !deptSelect || !programSelect) return;

        // Toggle aktif/pasif olduğunda bölüm ve program select'lerini de aktif/pasif yap
        deptToggle.addEventListener('change', function () {
            deptSelect.disabled   = !this.checked;
            programSelect.disabled = !this.checked;
            if (!this.checked) {
                deptSelect.value = '0';
                programSelect.innerHTML = '<option value="0">Önce Bölüm Seçiniz</option>';
            }
        });

        // Bölüm değiştiğinde program listesini AJAX ile yükle
        deptSelect.addEventListener('change', function () {
            const departmentId = this.value;

            programSelect.innerHTML = '<option value="0">Yükleniyor...</option>';
            programSelect.disabled = true;

            if (!departmentId || departmentId === '0') {
                programSelect.innerHTML = '<option value="0">Önce Bölüm Seçiniz</option>';
                return;
            }

            fetch(`/ajax/getProgramsList/${departmentId}`, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(response => response.json())
                .then(data => {
                    const progList = data['programs'] || [];
                    programSelect.innerHTML = '<option value="0">Program Seçiniz</option>';
                    progList.forEach(prog => {
                        const opt = document.createElement('option');
                        opt.value = prog.id;
                        opt.textContent = prog.name;
                        programSelect.appendChild(opt);
                    });
                    if (progList.length === 1) {
                        programSelect.value = progList[0].id;
                    }
                    programSelect.disabled = false;
                })
                .catch(error => {
                    new Toast().prepareToast('Hata', 'Programları alırken hata oluştu.', 'danger');
                    console.error(error);
                    programSelect.innerHTML = '<option value="0">Hata oluştu</option>';
                    programSelect.disabled = false;
                });
        });
    }

    /**
     * AJAX isteği gönderir ve sonucu işler
     * @param {string} url
     * @param {Object} data
     */
    function executeBulkAction(url, data) {
        const actionModal = new Modal();
        actionModal.prepareModal('İşlem Devam Ediyor', '', false, false);
        spinner.showSpinner(actionModal.body);
        actionModal.showModal();

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(data),
        })
            .then(response => response.json())
            .then(result => {
                spinner.removeSpinner();

                const statusClass = result.status === 'error' ? 'danger' : result.status;

                // Sonuç detaylarını oluştur
                let detailHtml = `<p class="mb-2">${result.msg}</p>`;
                if (result.details && result.details.failed && Object.keys(result.details.failed).length > 0) {
                    detailHtml += '<div class="mt-2 text-start"><strong>İşlenemeyen kayıtlar:</strong><ul class="small mb-0 mt-1">';
                    for (const [id, reason] of Object.entries(result.details.failed)) {
                        detailHtml += `<li>ID ${id}: ${reason}</li>`;
                    }
                    detailHtml += '</ul></div>';
                }

                actionModal.prepareModal('İşlem Sonucu', detailHtml, false, true, 'md', statusClass);

                const isDelete = url.toLowerCase().includes('delete');
                let shouldReload = false;

                // Başarılı satırları tablodan kaldır (Sadece silme işleminde)
                if (result.details && result.details.success) {
                    if (isDelete) {
                        result.details.success.forEach(id => {
                            selectedIds.delete(id);
                            const row = document.querySelector(`.bulk-select-row[data-id="${id}"]`);
                            if (row && typeof dataTable !== 'undefined') {
                                dataTable.row(row.closest('tr')).remove();
                            }
                        });

                        if (typeof dataTable !== 'undefined' && result.details.success.length > 0) {
                            dataTable.draw();
                        }
                    } else if (result.details.success.length > 0) {
                        // Güncelleme işleminde data table silinmez, verinin güncel halini almak için sayfa yenilenmeli.
                        shouldReload = true;
                    }
                }

                // Modal kapandığında sayfayı yenile (güncelleme durumunda)
                actionModal.cancelButton.addEventListener('click', () => {
                    if (shouldReload) {
                        window.location.reload();
                    }
                });

                updateToolbarVisibility();
                updateSelectAllState();
            })
            .catch(error => {
                spinner.removeSpinner();
                actionModal.prepareModal('Hata', 'İşlem sırasında bir hata oluştu: ' + error, false, true, 'md', 'danger');
                console.error('Bulk action error:', error);
            });
    }

    /**
     * Dışa açılan arayüz
     */
    return {
        init: init,
    };
})();
