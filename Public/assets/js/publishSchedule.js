document.addEventListener('DOMContentLoaded', function () {
    // TomSelect initialization (similar to exportSchedule.js)
    const selectConfig = {
        create: false,
        sortField: {
            field: "text",
            direction: "asc"
        },
        plugins: {
            clear_button: {
                title: 'Temizle',
            }
        }
    };

    // Helper function for AJAX POST requests
    async function fetchPost(url, data) {
        const formData = new FormData();
        for (const key in data) {
            if (data[key] !== null && data[key] !== undefined) {
                formData.append(key, data[key]);
            }
        }
        const response = await fetch(url, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return await response.json();
    }

    // Selectors change events (formEvents.js handles cascading options population and dispatches change events)
    const selectorIds = [
        'unit_id', 'department_id', 'program_id',
        'lecturer_unit_id', 'lecturer_id',
        'classroom_unit_id', 'classroom_building_id', 'classroom_id',
        'schedule_type', 'academic_year', 'semester'
    ];
    selectorIds.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', function () {
                const resultsContainer = document.getElementById('publish_results_container');
                if (resultsContainer) resultsContainer.style.display = 'none';
                checkPublishStatus();
            });
        }
    });

    // Tab change event (Bootstrap 5 vanilla JS events)
    const tabElements = document.querySelectorAll('button[data-bs-toggle="tab"]');
    tabElements.forEach(tabEl => {
        tabEl.addEventListener('shown.bs.tab', function () {
            const resultsContainer = document.getElementById('publish_results_container');
            if (resultsContainer) resultsContainer.style.display = 'none';
            checkPublishStatus();
        });
    });

    /**
     * Helper to get element value handling both native select and TomSelect
     */
    function getVal(id) {
        const el = document.getElementById(id);
        if (!el) return null;
        const val = el.tomselect ? el.tomselect.getValue() : el.value;
        return (val && val !== '0' && val !== '') ? val : null;
    }

    /**
     * Determines current scope based on active tab and selections
     */
    function getCurrentScope() {
        const activeTabEl = document.querySelector('#publishTabs .nav-link.active');
        if (!activeTabEl) return { scope: null, scopeId: null, tab: null };
        const activeTab = activeTabEl.getAttribute('data-tab-value'); // program, user, classroom
        
        let scope = null;
        let scopeId = null;

        if (activeTab === 'program') {
            scopeId = getVal('program_id') || getVal('department_id') || getVal('unit_id');
            scope = getVal('program_id') ? 'program' : (getVal('department_id') ? 'department' : (getVal('unit_id') ? 'unit' : null));
        } else if (activeTab === 'user') {
            scopeId = getVal('lecturer_id') || getVal('lecturer_unit_id');
            scope = getVal('lecturer_id') ? 'user_single' : (getVal('lecturer_unit_id') ? 'unit' : null);
        } else if (activeTab === 'classroom') {
            scopeId = getVal('classroom_id') || getVal('classroom_building_id') || getVal('classroom_unit_id');
            scope = (getVal('classroom_id') || getVal('classroom_building_id')) ? 'classroom_single' : (getVal('classroom_unit_id') ? 'unit' : null);
        }

        return {
            scope: scope,
            scopeId: scopeId,
            tab: activeTab
        };
    }

    let checkStatusTimeout = null;
    function checkPublishStatus() {
        if (checkStatusTimeout) clearTimeout(checkStatusTimeout);
        checkStatusTimeout = setTimeout(executeCheckPublishStatus, 150);
    }

    /**
     * Checks publish status from backend and updates UI
     */
    async function executeCheckPublishStatus() {
        const {scope, scopeId, tab} = getCurrentScope();
        
        const activePane = document.querySelector('.tab-pane.active');
        if (!activePane) return;
        
        const btnGroup = activePane.querySelector('.publish-btn-group');
        const statsLabel = activePane.querySelector('.publish-stats');
        const btnPublish = activePane.querySelector('.btn-publish');
        const btnUnpublish = activePane.querySelector('.btn-unpublish');

        if (!scope || !scopeId || scope === 'user_single' || scope === 'classroom_single') {
            if (btnPublish) btnPublish.disabled = true;
            if (btnUnpublish) btnUnpublish.disabled = true;
            if (statsLabel) statsLabel.classList.add('d-none');
            
            if (scope === 'user_single' || scope === 'classroom_single') {
                if (statsLabel) {
                    statsLabel.classList.remove('d-none', 'text-bg-info', 'text-bg-success');
                    statsLabel.classList.add('text-bg-warning');
                    statsLabel.textContent = "Tekil yayınlama için düzenleme sayfasını kullanın.";
                }
            }
            return;
        }

        const semesterEl = document.getElementById('semester');
        const academicYearEl = document.getElementById('academic_year');
        const scheduleTypeEl = document.getElementById('schedule_type');

        const data = {
            scope: scope,
            scope_id: scopeId,
            semester: semesterEl ? semesterEl.value : null,
            academic_year: academicYearEl ? academicYearEl.value : null,
            type: scheduleTypeEl ? scheduleTypeEl.value : null,
            owner_type_tab: tab === 'program' ? null : tab
        };

        if (btnPublish) btnPublish.disabled = true;
        if (btnUnpublish) btnUnpublish.disabled = true;
        if (statsLabel) {
            statsLabel.classList.remove('d-none', 'text-bg-success', 'text-bg-warning');
            statsLabel.classList.add('text-bg-info');
            statsLabel.textContent = 'Kontrol ediliyor...';
        }

        try {
            const response = await fetchPost('/ajax/getPublishStatusByScope', data);
            if (response.status === 'success') {
                const stats = response;
                
                if (stats.details && stats.details.length > 0) {
                    console.log('--- Kapsama Dahil Olan Programlar (' + stats.total_count + ') ---');
                    stats.details.forEach(detail => {
                        console.log(`[${detail.is_published ? 'YAYINDA' : 'YAYINDA DEĞİL'}] (Schedule ID: ${detail.schedule_id || detail.id}, Type: ${detail.type || 'lesson'}, Owner Type: ${detail.owner_type}, Owner ID: ${detail.owner_id}) ${detail.name}`);
                    });
                }
                
                if (stats.total_count === 0) {
                    if (statsLabel) {
                        statsLabel.classList.remove('text-bg-info', 'text-bg-success');
                        statsLabel.classList.add('text-bg-warning');
                        statsLabel.textContent = 'Yayınlanacak program bulunamadı.';
                    }
                    if (btnPublish) btnPublish.disabled = true;
                    if (btnUnpublish) btnUnpublish.disabled = true;
                } else {
                    if (statsLabel) {
                        statsLabel.classList.remove('text-bg-warning', 'text-bg-info');
                        statsLabel.classList.add('text-bg-success');
                        statsLabel.textContent = `${stats.total_count} programdan ${stats.published_count}'i yayında.`;
                    }
                    
                    if (stats.all_published) {
                        if (btnPublish) btnPublish.disabled = true;
                        if (btnUnpublish) btnUnpublish.disabled = false;
                    } else {
                        if (btnPublish) btnPublish.disabled = false;
                        if (stats.published_count > 0) {
                            if (btnUnpublish) btnUnpublish.disabled = false;
                        } else {
                            if (btnUnpublish) btnUnpublish.disabled = true;
                        }
                    }
                }
            } else {
                if (statsLabel) {
                    statsLabel.classList.remove('text-bg-info');
                    statsLabel.classList.add('text-bg-danger');
                    statsLabel.textContent = 'Hata: ' + response.msg;
                }
            }
        } catch (error) {
            if (statsLabel) {
                statsLabel.classList.remove('text-bg-info');
                statsLabel.classList.add('text-bg-danger');
                statsLabel.textContent = 'Bağlantı hatası.';
            }
        }
    }

    /**
     * Handle Publish/Unpublish buttons
     */
    document.querySelectorAll('.btn-publish, .btn-unpublish').forEach(btn => {
        btn.addEventListener('click', function(e) {
            const currentBtn = e.currentTarget;
            const action = currentBtn.getAttribute('data-action');
            const actionText = action === 'publish' ? 'Yayınlamak' : 'Yayından Kaldırmak';
            
            const {scope, scopeId, tab} = getCurrentScope();
            if (!scope || !scopeId) return;

            const semesterEl = document.getElementById('semester');
            const academicYearEl = document.getElementById('academic_year');
            const scheduleTypeEl = document.getElementById('schedule_type');

            const data = {
                scope: scope,
                scope_id: scopeId,
                semester: semesterEl ? semesterEl.value : null,
                academic_year: academicYearEl ? academicYearEl.value : null,
                type: scheduleTypeEl ? scheduleTypeEl.value : null,
                owner_type_tab: tab === 'program' ? null : tab,
                action: action
            };

            const modal = new Modal();
            modal.prepareModal(
                'Emin misiniz?',
                `Seçili kapsamdaki programları <b>${actionText}</b> istediğinize emin misiniz? Bu işlem biraz zaman alabilir ve hocalara iletilecek e-postalar sıraya alınır.`,
                true,
                true,
                'md'
            );
            modal.confirmButton.textContent = 'Evet, Devam Et';
            modal.confirmButton.onclick = async function() {
                const originalHtml = currentBtn.innerHTML;
                currentBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> İşleniyor...';
                currentBtn.disabled = true;
                modal.closeModal();

                try {
                    const response = await fetchPost('/ajax/bulkPublishByScope', data);
                    currentBtn.innerHTML = originalHtml;
                    currentBtn.disabled = false;

                    if (response.status === 'success') {
                        const resultsContainer = document.getElementById('publish_results_container');
                        const resultText = resultsContainer ? resultsContainer.querySelector('.result-text') : null;
                        if (resultsContainer && resultText) {
                            resultsContainer.style.display = 'block';
                            resultText.innerHTML = response.msg;
                        }

                        const successModal = new Modal();
                        successModal.prepareModal(
                            'İşlem Başarılı',
                            `<div class="text-success mb-2"><i class="bi bi-check-circle-fill me-2 fs-5"></i> ${response.msg}</div>
                             <div class="mt-3 text-muted small">Gönderilen veya yakalanan e-postaları <a href="/mail_log.html" target="_blank" class="fw-bold text-decoration-underline">Test Mail Logları</a> sayfasından inceleyebilirsiniz.</div>`,
                            false,
                            true,
                            'md'
                        );
                        successModal.showModal();

                        checkPublishStatus();
                    } else {
                        const errModal = new Modal();
                        errModal.prepareModal('Hata', response.msg, false, true, 'sm', 'danger');
                        errModal.showModal();
                    }
                } catch (error) {
                    currentBtn.innerHTML = originalHtml;
                    currentBtn.disabled = false;
                    const errModal = new Modal();
                    errModal.prepareModal('Hata', 'Sunucu ile iletişim kurulamadı.', false, true, 'sm', 'danger');
                    errModal.showModal();
                }
            };
            modal.showModal();
        });
    });
});
