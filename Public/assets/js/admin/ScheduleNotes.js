/**
 * ScheduleNotes.js
 * Hoca İstekleri ve Notları Yönetimi (Vanilla JS Handler)
 */
class ScheduleNotesHandler {
    constructor() {
        this.initEventListeners();
    }

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

        // Akademisyen tarafı: Not Kaydetme Formu
        document.addEventListener('submit', function (e) {
            if (e.target && e.target.matches('#form-save-schedule-note')) {
                e.preventDefault();
                self.saveMyNote(e.target);
            }
        });
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

                notes.forEach(n => {
                    if (n.status === 'completed') {
                        completedCount++;
                    } else if (n.status === 'rejected') {
                        rejectedCount++;
                    } else if (n.status === 'read') {
                        readCount++;
                    } else {
                        pendingCount++;
                    }
                });

                let icons = [];
                if (pendingCount > 0) {
                    icons.push(`<span class="ms-1" title="Görülmemiş Notlar"><i class="bi bi-eye-slash-fill"></i> ${pendingCount}</span>`);
                }
                if (readCount > 0) {
                    icons.push(`<span class="ms-1" title="Görülen Notlar"><i class="bi bi-eye-fill"></i> ${readCount}</span>`);
                }
                if (completedCount > 0) {
                    icons.push(`<span class="ms-1 text-success" title="Gereği Yapılan Notlar"><i class="bi bi-check-circle-fill"></i> ${completedCount}</span>`);
                }
                if (rejectedCount > 0) {
                    icons.push(`<span class="ms-1 text-danger" title="Reddedilen Notlar"><i class="bi bi-x-circle-fill"></i> ${rejectedCount}</span>`);
                }

                countEl.innerHTML = icons.length > 0 ? ` (${icons.join(' ')})` : ' (0)';
            }
        } catch (err) {
            // Sessizce geç
        }
    }

    /**
     * Program düzenleme ekranında seçili programa veya hocaya ait akademisyen notlarını yükler.
     */
    async loadProgramNotes() {
        const { programId, lecturerId, academicYear, semester, scheduleType } = this.getContextParams();

        if (programId <= 0 && lecturerId <= 0) {
            this.notify('Uyarı', 'Lütfen önce bir program veya hoca seçiniz.', 'warning');
            return;
        }

        const modalBody = document.getElementById('schedule-notes-modal-body');
        if (modalBody) {
            modalBody.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Yükleniyor...</span>
                    </div>
                    <p class="mt-2 text-muted">Akademisyen notları getiriliyor...</p>
                </div>
            `;
        }

        const modalEl = document.getElementById('modal-schedule-notes');
        if (modalEl && typeof bootstrap !== 'undefined') {
            const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
            bsModal.show();
        }

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
                
                // Modal açıldığında da rozeti güncelle
                this.updateNotesCountBadge();

                if (!modalBody) return;

                if (notes.length === 0) {
                    modalBody.innerHTML = `
                        <div class="alert alert-info text-center my-3" role="alert">
                            <i class="bi bi-info-circle fs-3 d-block mb-2"></i>
                            Seçilen kritere ait henüz iletilmiş akademisyen notu bulunmamaktadır.
                        </div>
                    `;
                    return;
                }

                let html = '';
                notes.forEach(note => {
                    html += `
                        <div class="card card-outline card-secondary mb-3 schedule-note-item" data-note-id="${note.id}">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center p-2 px-3">
                                <div>
                                    <strong class="fs-6">${note.lecturer_name || ''}</strong>
                                    <small class="text-muted d-block">${note.academic_year} - ${note.semester} (${note.schedule_type})</small>
                                </div>
                                <div class="d-flex align-items-center ms-auto gap-3">
                                    <span class="badge ${note.badge_class} fs-6">${note.status_label}</span>
                                    <form action="/ajax/deleteScheduleNote" method="POST" class="ajaxFormDelete d-inline" data-confirm-message="Bu program notunu silmek istediğinize emin misiniz?">
                                        <input type="hidden" name="note_id" value="${note.id}">
                                        <button type="submit" class="btn btn-sm btn-link text-danger p-0" title="Notu Sil">
                                            <i class="bi bi-trash fs-5"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <div class="card-body">
                                <blockquote class="blockquote bg-white p-3 rounded border-start border-4 border-primary fs-6 mb-3">
                                    ${note.note}
                                </blockquote>
                                ${note.read_at ? `
                                    <p class="text-muted small mb-2">
                                        <i class="bi bi-eye-fill text-info"></i> Görüldü: <strong>${note.read_by_name || 'Düzenleyici'}</strong> (${note.read_at})
                                    </p>
                                ` : ''}
                                
                                <hr class="my-2">
                                <div class="row g-2 align-items-center mt-2">
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold mb-1">Durum Güncelle:</label>
                                        <select class="form-select form-select-sm note-status-select">
                                            <option value="completed" ${note.status === 'completed' ? 'selected' : ''}>Gereği Yapıldı</option>
                                            <option value="rejected" ${note.status === 'rejected' ? 'selected' : ''}>Reddedildi</option>
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

                modalBody.innerHTML = html;
            } else {
                if (modalBody) modalBody.innerHTML = `<div class="alert alert-danger">${res.msg || 'Bir hata oluştu.'}</div>`;
            }
        } catch (err) {
            if (modalBody) modalBody.innerHTML = '<div class="alert alert-danger">Sunucu ile iletişim kurulurken bir hata oluştu.</div>';
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
     * Akademisyenin notunu kaydeder.
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
                if (typeof window.loadMyScheduleNotes === 'function') {
                    window.loadMyScheduleNotes();
                }
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
}

document.addEventListener('DOMContentLoaded', function () {
    window.scheduleNotesHandler = new ScheduleNotesHandler();
    window.scheduleNotesHandler.updateNotesCountBadge();
});
