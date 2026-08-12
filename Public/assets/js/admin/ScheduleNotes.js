/**
 * ScheduleNotes.js
 * Hoca İstekleri ve Notları Yönetimi (Vanilla JS Handler)
 * 
 * Bu sınıf, akademisyenlerin ilettiği ders/sınav programı notlarının ve özel isteklerinin:
 * 1. Profil sayfasında listelenmesini ve yönetilmesini,
 * 2. Yeni not ekleme veya mevcut notu güncelleme form kontrollerini,
 * 3. Takvim düzenleme sayfalarında düzenleyicilere rozet/sayaç ve modal şeklinde gösterilmesini sağlar.
 */
class ScheduleNotesHandler {
    constructor() {
        /**
         * Kullanıcının profil sayfasında ilettiği mevcut notların listesini tutar.
         * Form üzerindeki seçimler değiştikçe mevcut not eşleşmesini kontrol etmek için kullanılır.
         */
        this.currentUserNotes = [];
        this.initEventListeners();
    }

    /**
     * Kullanıcıya bildirim (Toast/Alert) gösterir.
     */
    notify(title, text, type = 'info') {
        if (typeof Toast !== 'undefined') {
            new Toast().prepareToast(title, text, type);
        } else {
            alert(title + ': ' + text);
        }
    }

    /**
     * Sayfadaki aktif sekmeye ve seçili elemanlara göre arama bağlamını tespit eder.
     */
    getContextParams() {
        const academicYear = document.getElementById('academic_year')?.value || '';
        const semester = document.getElementById('semester')?.value || '';
        const scheduleType = document.getElementById('schedule_type')?.value || 'lesson';

        let programId = 0;
        let lecturerId = 0;

        const lecturerTab = document.getElementById('lecturer-tab');
        const isLecturerTabActive = lecturerTab && lecturerTab.classList.contains('active');

        const lecturerIdEl = document.getElementById('lecturer_id');
        const programIdEl = document.getElementById('program_id') || document.getElementById('filter_program_id');

        const selectedLecturerId = lecturerIdEl ? parseInt(lecturerIdEl.value, 10) || 0 : 0;
        const selectedProgramId = programIdEl ? parseInt(programIdEl.value, 10) || 0 : 0;

        if (isLecturerTabActive && selectedLecturerId > 0) {
            lecturerId = selectedLecturerId;
        } else if (selectedProgramId > 0) {
            programId = selectedProgramId;
        } else if (selectedLecturerId > 0) {
            lecturerId = selectedLecturerId;
        }

        return {
            programId,
            lecturerId,
            academicYear,
            semester,
            scheduleType
        };
    }

    /**
     * Tüm olay dinleyicilerini (Event Listeners) başlatır.
     */
    initEventListeners() {
        const self = this;

        // Düzenleyici tarafı: Modal açma butonu
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('#btn-show-schedule-notes');
            if (btn) {
                e.preventDefault();
                self.loadProgramNotes();
            }
        });

        // Düzenleyici tarafı: Program/Hoca/Derslik düzenle butonlarına basıldığında not sayısını güncelle
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('#departmentAndProgramScheduleButton, #lecturerScheduleButton, #classroomScheduleButton');
            if (btn) {
                self.updateNotesCountBadge();
            }
        });

        // Tab değiştirildiğinde veya hoca/program seçimi değiştiğinde rozeti güncelle
        document.addEventListener('change', function (e) {
            if (e.target && e.target.matches('#program_id, #lecturer_id, #academic_year, #semester, #schedule_type')) {
                self.updateNotesCountBadge();
            }
        });

        // TomSelect bileşenlerinin değişimlerini dinle
        setTimeout(() => {
            ['#program_id', '#lecturer_id', '#academic_year', '#semester', '#schedule_type'].forEach(selector => {
                const el = document.querySelector(selector);
                if (el && el.tomselect) {
                    el.tomselect.on('change', function() {
                        self.updateNotesCountBadge();
                    });
                }
            });
        }, 500);

        // Düzenleyici tarafı: Not Silme Butonu (myHTMLElements.js Modal sınıfı ile onay alma)
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.btn-delete-schedule-note');
            if (btn) {
                e.preventDefault();
                const noteId = btn.dataset.noteId;
                const confirmMessage = (typeof gettext !== 'undefined' && gettext.deleteMessage) 
                    ? gettext.deleteMessage 
                    : "Bu akademisyen notunu silmek istediğinizden emin misiniz?";

                const title = (typeof gettext !== 'undefined' && gettext.confirmDelete) 
                    ? gettext.confirmDelete 
                    : "Silme Onayı";

                const deleteBtnText = (typeof gettext !== 'undefined' && gettext.delete) 
                    ? gettext.delete 
                    : "Sil";

                if (typeof Modal !== 'undefined') {
                    let confirmDeleteModal = new Modal();
                    confirmDeleteModal.prepareModal(title, confirmMessage, true, true, "sm");
                    if (confirmDeleteModal.confirmButton) {
                        confirmDeleteModal.confirmButton.textContent = deleteBtnText;
                        confirmDeleteModal.confirmButton.classList.remove('btn-success');
                        confirmDeleteModal.confirmButton.classList.add('btn-danger');
                    }
                    confirmDeleteModal.showModal();
                    confirmDeleteModal.confirmButton.addEventListener("click", () => {
                        confirmDeleteModal.closeModal();
                        self.deleteScheduleNote(noteId);
                    });
                } else if (confirm(confirmMessage)) {
                    self.deleteScheduleNote(noteId);
                }
            }
        });

        // Düzenleyici tarafı: Durum Güncelleme Butonu
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.btn-save-note-status');
            if (btn) {
                e.preventDefault();
                const card = btn.closest('.schedule-note-item');
                if (!card) return;

                const noteId = card.dataset.noteId;
                const statusSelect = card.querySelector('.note-status-select');
                const feedbackInput = card.querySelector('.note-feedback-input');

                const status = statusSelect ? statusSelect.value : '';
                const feedback = feedbackInput ? feedbackInput.value : '';

                self.updateNoteStatus(noteId, status, feedback, btn);
            }
        });

        // Akademisyen tarafı: Not Kaydetme Formu Gönderimi
        document.addEventListener('submit', function (e) {
            if (e.target && e.target.matches('#form-save-schedule-note')) {
                e.preventDefault();
                self.saveMyNote(e.target);
            }
        });

        // Akademisyen tarafı: Form üzerindeki select seçimleri değiştikçe mevcut not kontrolü
        document.addEventListener('change', function (e) {
            if (e.target && e.target.closest('#form-save-schedule-note')) {
                self.checkFormExistingNote();
            }
        });

        // Profil sayfasındaki 'İstekler / Notlar' sekmesi açıldığında notları yükle
        const notesTab = document.getElementById('notes-tab');
        if (notesTab) {
            notesTab.addEventListener('shown.bs.tab', function() {
                self.loadMyScheduleNotes();
            });
            if (notesTab.classList.contains('active')) {
                self.loadMyScheduleNotes();
            }
        }
    }

    /**
     * Seçili programa veya hocaya ait akademisyen not sayısını ve durum dökümünü günceller.
     */
    async updateNotesCountBadge() {
        const { programId, lecturerId, academicYear, semester, scheduleType } = this.getContextParams();
        const countEl = document.getElementById('schedule-notes-count');
        if (!countEl) return;

        if (programId <= 0 && lecturerId <= 0) {
            countEl.innerHTML = '';
            return;
        }

        const formData = new FormData();
        if (programId > 0) formData.append('program_id', programId);
        if (lecturerId > 0) formData.append('lecturer_id', lecturerId);
        formData.append('academic_year', academicYear);
        formData.append('semester', semester);
        formData.append('schedule_type', scheduleType);
        formData.append('mark_read', '0'); // Sayım yapılırken görüldü olarak işaretleme

        try {
            const response = await fetch('/ajax/getProgramScheduleNotes', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            const res = await response.json();

            if (res.status === 'success') {
                const notes = res.data || [];

                if (notes.length === 0) {
                    countEl.innerHTML = ' (0)';
                    return;
                }

                let pendingCount = 0;
                let readCount = 0;
                let completedCount = 0;
                let rejectedCount = 0;
                let infoSentCount = 0;

                notes.forEach(n => {
                    if (n.status === 'completed') {
                        completedCount++;
                    } else if (n.status === 'rejected') {
                        rejectedCount++;
                    } else if (n.status === 'info_sent') {
                        infoSentCount++;
                    } else if (n.status === 'read') {
                        readCount++;
                    } else {
                        pendingCount++;
                    }
                });

                let icons = [];
                if (pendingCount > 0) {
                    icons.push(`<span class="badge bg-danger" title="${pendingCount} Okunmamış / Bekleyen Not"><i class="bi bi-envelope-fill me-1"></i>${pendingCount}</span>`);
                }
                if (readCount > 0) {
                    icons.push(`<span class="badge bg-info text-dark" title="${readCount} İncelenen Not"><i class="bi bi-eye-fill me-1"></i>${readCount}</span>`);
                }
                if (completedCount > 0) {
                    icons.push(`<span class="badge bg-success" title="${completedCount} Gereği Yapılmış Not"><i class="bi bi-check-circle-fill me-1"></i>${completedCount}</span>`);
                }
                if (infoSentCount > 0) {
                    icons.push(`<span class="badge bg-primary" title="${infoSentCount} Bilgi Verilmiş Not"><i class="bi bi-info-circle-fill me-1"></i>${infoSentCount}</span>`);
                }
                if (rejectedCount > 0) {
                    icons.push(`<span class="badge bg-secondary" title="${rejectedCount} Reddedilmiş Not"><i class="bi bi-x-circle-fill me-1"></i>${rejectedCount}</span>`);
                }

                countEl.innerHTML = ` (${notes.length}) <span class="ms-1 d-inline-flex gap-1 align-items-center">${icons.join(' ')}</span>`;
            } else {
                countEl.innerHTML = '';
            }
        } catch (err) {
            countEl.innerHTML = '';
        }
    }

    /**
     * Düzenleyici için modal açıldığında akademisyen notlarını yükler ve ekranda gösterir.
     */
    async loadProgramNotes() {
        const modalEl = document.getElementById('modal-schedule-notes');
        const listContainer = document.getElementById('schedule-notes-list-container');
        if (!modalEl || !listContainer) return;

        const { programId, lecturerId, academicYear, semester, scheduleType } = this.getContextParams();

        if (programId <= 0 && lecturerId <= 0) {
            this.notify('Uyarı', 'Lütfen önce bir program veya akademisyen seçiniz.', 'warning');
            return;
        }

        listContainer.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-2 text-muted">Akademisyen notları yükleniyor...</p>
            </div>
        `;

        const modal = new bootstrap.Modal(modalEl);
        modal.show();

        const formData = new FormData();
        if (programId > 0) formData.append('program_id', programId);
        if (lecturerId > 0) formData.append('lecturer_id', lecturerId);
        formData.append('academic_year', academicYear);
        formData.append('semester', semester);
        formData.append('schedule_type', scheduleType);
        formData.append('mark_read', '1'); // Modal açıldığında notları görüldü olarak işaretle

        try {
            const response = await fetch('/ajax/getProgramScheduleNotes', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            const res = await response.json();

            if (res.status === 'success') {
                const notes = res.data || [];
                if (notes.length === 0) {
                    listContainer.innerHTML = `
                        <div class="alert alert-info text-center my-3">
                            <i class="bi bi-info-circle fs-4 d-block mb-1"></i>
                            Bu filtreye uygun herhangi bir akademisyen notu veya özel istek bulunmamaktadır.
                        </div>
                    `;
                } else {
                    let html = '';
                    notes.forEach(note => {
                        html += `
                            <div class="card card-outline card-primary mb-3 schedule-note-item" data-note-id="${note.id}">
                                <div class="card-header d-flex justify-content-between align-items-center p-2 px-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <strong><i class="bi bi-person-circle me-1"></i>${note.user_name}</strong>
                                        <small class="text-muted">(${note.department_name || 'Bölüm Belirtilmemiş'})</small>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge ${note.badge_class}">${note.status_label}</span>
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete-schedule-note py-0 px-1" data-note-id="${note.id}" title="Notu Sil">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body p-3">
                                    <p class="mb-2 text-dark fs-6" style="white-space: pre-wrap;">${note.note}</p>
                                    <div class="small text-muted mb-2">
                                        <i class="bi bi-calendar-event me-1"></i> Dönem: <strong>${note.academic_year} ${note.semester}</strong> | 
                                        <i class="bi bi-clock me-1"></i> İletilme: ${note.created_at}
                                        ${note.read_at ? ` | <i class="bi bi-eye-fill text-info me-1"></i> Görüldü: ${note.read_at}` : ''}
                                    </div>

                                    <hr class="my-2">
                                    <div class="row g-2 align-items-center mt-2">
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold mb-1">Durum Güncelle:</label>
                                            <select class="form-select form-select-sm note-status-select">
                                                <option value="completed" ${note.status === 'completed' ? 'selected' : ''}>Gereği Yapıldı</option>
                                                <option value="rejected" ${note.status === 'rejected' ? 'selected' : ''}>Reddedildi</option>
                                                <option value="info_sent" ${note.status === 'info_sent' ? 'selected' : ''}>Bilgi Verildi</option>
                                            </select>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label small fw-bold mb-1">Geri Bildirim / Açıklama:</label>
                                            <input type="text" class="form-control form-control-sm note-feedback-input" 
                                                   placeholder="Hocaya mail ile iletilecek açıklama..." 
                                                   value="${note.editor_feedback || ''}">
                                        </div>
                                        <div class="col-md-3 text-end pt-3">
                                            <button type="button" class="btn btn-sm btn-primary w-100 btn-save-note-status">
                                                <i class="bi bi-send-fill me-1"></i> Kaydet & Mail Gönder
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    listContainer.innerHTML = html;
                }

                // Rozeti güncelle
                this.updateNotesCountBadge();
            } else {
                listContainer.innerHTML = `<div class="alert alert-danger">${res.msg || 'Notlar yüklenirken bir hata oluştu.'}</div>`;
            }
        } catch (err) {
            listContainer.innerHTML = `<div class="alert alert-danger">Sunucu ile iletişim kurulurken bir hata oluştu.</div>`;
        }
    }

    /**
     * Bir akademisyen notunu siler.
     */
    async deleteScheduleNote(noteId) {
        const formData = new FormData();
        formData.append('note_id', noteId);

        try {
            const response = await fetch('/ajax/deleteScheduleNote', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            const res = await response.json();

            if (res.status === 'success') {
                this.notify('Başarılı', res.msg, 'success');
                const card = document.querySelector(`.schedule-note-item[data-note-id="${noteId}"]`);
                if (card) {
                    card.remove();
                }

                // Rozeti ve sayacı güncelle
                this.updateNotesCountBadge();

                // Eğer profil sayfasındaysak kişisel not listesini yenile
                this.loadMyScheduleNotes();
            } else {
                this.notify('Hata', res.msg || 'Not silinemedi.', 'danger');
            }
        } catch (err) {
            this.notify('Hata', 'Not silinirken sunucu ile iletişim kurulamadı.', 'danger');
        }
    }

    /**
     * Düzenleyici not durumunu ve açıklamasını kaydeder.
     */
    async updateNoteStatus(noteId, status, feedback, btn) {
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Kaydediliyor...';

        const formData = new FormData();
        formData.append('note_id', noteId);
        formData.append('status', status);
        formData.append('editor_feedback', feedback);

        try {
            const response = await fetch('/ajax/updateScheduleNoteStatus', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            const res = await response.json();
            btn.disabled = false;
            btn.innerHTML = originalHtml;

            if (res.status === 'success') {
                this.notify('Başarılı', res.msg, 'success');
                this.updateNotesCountBadge();
            } else {
                this.notify('Hata', res.msg || 'Güncelleme başarısız.', 'danger');
            }
        } catch (err) {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            this.notify('Hata', 'İşlem sırasında bir hata oluştu.', 'danger');
        }
    }

    /**
     * Akademisyenin yeni notunu kaydeder veya var olan notunu günceller.
     * Form gönderildikten sonra not listesini otomatik yeniler.
     */
    async saveMyNote(form) {
        const btn = form.querySelector('button[type="submit"]');
        const originalHtml = btn ? btn.innerHTML : '';

        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Kaydediliyor...';
        }

        const formData = new FormData(form);

        try {
            const response = await fetch('/ajax/saveScheduleNote', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            const res = await response.json();

            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }

            if (res.status === 'success') {
                this.notify('Başarılı', res.msg, 'success');
                this.loadMyScheduleNotes();
            } else {
                this.notify('Hata', res.msg || 'Not kaydedilemedi.', 'danger');
            }
        } catch (err) {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
            this.notify('Hata', 'Not kaydı esnasında hata oluştu.', 'danger');
        }
    }

    /**
     * Profil sayfasında akademisyenin seçtiği akademik yıl, dönem ve program türü kombinasyonuna
     * uygun önceden iletilmiş bir notu olup olmadığını kontrol eder.
     * Eğer daha önce yazılmış bir not varsa textarea'ya aktarır ve butonu 'Notu Güncelle' durumuna getirir.
     */
    checkFormExistingNote() {
        const form = document.getElementById('form-save-schedule-note');
        if (!form) return;

        const academicYearSelect = form.querySelector('[name="academic_year"]');
        const semesterSelect = form.querySelector('[name="semester"]');
        const scheduleTypeSelect = form.querySelector('[name="schedule_type"]');
        const noteTextarea = form.querySelector('[name="note"]');
        const warningBox = document.getElementById('note-update-warning');
        const submitBtn = document.getElementById('btn-save-note');

        if (!noteTextarea || !submitBtn) return;

        const academicYear = academicYearSelect ? academicYearSelect.value : '';
        const semester = semesterSelect ? semesterSelect.value : '';
        const scheduleType = scheduleTypeSelect ? scheduleTypeSelect.value : '';

        const match = (this.currentUserNotes || []).find(n => 
            n.academic_year === academicYear && 
            n.semester === semester && 
            n.schedule_type === scheduleType
        );

        if (match) {
            noteTextarea.value = match.note;
            if (warningBox) warningBox.classList.remove('d-none');
            submitBtn.innerHTML = '<i class="bi bi-pencil-square me-1"></i> Notu Güncelle';
            submitBtn.className = 'btn btn-warning w-100';
        } else {
            noteTextarea.value = '';
            if (warningBox) warningBox.classList.add('d-none');
            submitBtn.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Notu Kaydet';
            submitBtn.className = 'btn btn-primary w-100';
        }
    }

    /**
     * Profil sayfasındaki #my-schedule-notes-list kapsayıcısını hedef alarak akademisyenin 
     * geçmiş program notlarını AJAX ile getirir ve dinamik olarak HTML kartlarını oluşturur.
     */
    async loadMyScheduleNotes() {
        const listContainer = document.getElementById('my-schedule-notes-list');
        if (!listContainer) return;

        const profileUserId = parseInt(listContainer.dataset.userId || '0', 10);
        const canManageNotes = listContainer.dataset.canManage === 'true';

        const formData = new FormData();
        if (profileUserId > 0) {
            formData.append('user_id', profileUserId);
        }

        try {
            const response = await fetch('/ajax/getMyScheduleNotes', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            const res = await response.json();

            if (res.status === 'success') {
                const notes = res.data || [];
                this.currentUserNotes = notes;
                window.currentUserNotes = notes; // geriye dönük uyumluluk
                this.checkFormExistingNote();

                if (notes.length === 0) {
                    listContainer.innerHTML = `
                        <div class="alert alert-light text-center my-2 border">
                            <i class="bi bi-info-circle text-muted fs-4 d-block mb-1"></i>
                            Henüz iletilmiş bir ders/sınav programı notu bulunmamaktadır.
                        </div>
                    `;
                    return;
                }

                let html = '';
                notes.forEach(note => {
                    let typeTitle = 'Ders Programı';
                    if (note.schedule_type === 'midterm-exam') typeTitle = 'Ara Sınav';
                    else if (note.schedule_type === 'final-exam') typeTitle = 'Final Sınavı';
                    else if (note.schedule_type === 'makeup-exam') typeTitle = 'Bütünleme Sınavı';

                    html += `
                        <div class="card card-outline card-secondary mb-3 schedule-note-item" data-note-id="${note.id}">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center p-2 px-3">
                                <span class="fw-bold">${note.academic_year} ${note.semester} (${typeTitle})</span>
                                <div class="d-flex align-items-center ms-auto gap-3">
                                    <span class="badge ${note.badge_class}">${note.status_label}</span>
                                    <form action="/ajax/deleteScheduleNote" method="POST" class="ajaxFormDelete d-inline" data-confirm-message="Bu program notunu silmek istediğinize emin misiniz?">
                                        <input type="hidden" name="note_id" value="${note.id}">
                                        <button type="submit" class="btn btn-sm btn-link text-danger p-0" title="Notu Sil">
                                            <i class="bi bi-trash fs-5"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <div class="card-body p-3">
                                <p class="mb-2 text-dark fs-6">${note.note}</p>
                                ${note.read_at ? `
                                    <div class="small text-muted mb-1">
                                        <i class="bi bi-eye-fill text-info me-1"></i> Görüldü: <strong>${note.read_by_name || 'Düzenleyici'}</strong> (${note.read_at})
                                    </div>
                                ` : '<div class="small text-warning"><i class="bi bi-clock me-1"></i> Henüz düzenleyici tarafından görülmedi.</div>'}
                                ${note.editor_feedback ? `
                                    <div class="alert alert-success p-2 mt-2 mb-0 small border-start border-4 border-success">
                                        <strong><i class="bi bi-chat-left-text-fill me-1"></i> Düzenleyici Notu (${note.status_updated_by_name || ''}):</strong><br>
                                        ${note.editor_feedback}
                                    </div>
                                ` : ''}

                                ${canManageNotes ? `
                                    <hr class="my-2">
                                    <div class="row g-2 align-items-center mt-2">
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold mb-1">Durum Güncelle:</label>
                                            <select class="form-select form-select-sm note-status-select">
                                                <option value="completed" ${note.status === 'completed' ? 'selected' : ''}>Gereği Yapıldı</option>
                                                <option value="rejected" ${note.status === 'rejected' ? 'selected' : ''}>Reddedildi</option>
                                                <option value="info_sent" ${note.status === 'info_sent' ? 'selected' : ''}>Bilgi Verildi</option>
                                            </select>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label small fw-bold mb-1">Geri Bildirim / Açıklama:</label>
                                            <input type="text" class="form-control form-control-sm note-feedback-input" 
                                                   placeholder="Hocaya mail ile iletilecek açıklama..." 
                                                   value="${note.editor_feedback || ''}">
                                        </div>
                                        <div class="col-md-3 text-end pt-3">
                                            <button type="button" class="btn btn-sm btn-primary w-100 btn-save-note-status">
                                                <i class="bi bi-send-fill me-1"></i> Kaydet & Mail Gönder
                                            </button>
                                        </div>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    `;
                });
                listContainer.innerHTML = html;
            } else {
                listContainer.innerHTML = '<div class="alert alert-danger">Notlar yüklenirken bir hata oluştu.</div>';
            }
        } catch (err) {
            listContainer.innerHTML = '<div class="alert alert-danger">Sunucuyla iletişim kurulamadı.</div>';
        }
    }
}

// Sınıf örneğini oluştur ve global erişim köprülerini sağla
document.addEventListener('DOMContentLoaded', function () {
    window.scheduleNotesHandler = new ScheduleNotesHandler();
    window.scheduleNotesHandler.updateNotesCountBadge();

    // Geriye dönük uyumluluk için global fonksiyon köprüleri
    window.loadMyScheduleNotes = () => window.scheduleNotesHandler.loadMyScheduleNotes();
    window.checkFormExistingNote = () => window.scheduleNotesHandler.checkFormExistingNote();
});
