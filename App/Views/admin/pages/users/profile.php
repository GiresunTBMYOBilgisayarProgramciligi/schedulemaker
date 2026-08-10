<?php
/**
 * @var \App\Models\User $user
 * @var \App\Controllers\UserController $userController
 * @var array $departments
 * @var array $units
 * @var string $page_title
 */

use App\Core\Gate;
use function App\Helpers\getSettingValue;

?>
<!--begin::App Main-->
<main class="app-main">
    <!--begin::App Content Header-->
    <div class="app-content-header">
        <!--begin::Container-->
        <div class="container-fluid">
            <!--begin::Row-->
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0"><?= $page_title ?></h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="/admin">Ana Sayfa</a></li>
                        <li class="breadcrumb-item">Kullanıcı İşlemleri</li>
                        <li class="breadcrumb-item active">Profil</li>
                    </ol>
                </div>
            </div>
            <!--end::Row-->
        </div>
        <!--end::Container-->
    </div>
    <!--end::App Content Header-->
    <!--begin::App Content-->
    <div class="app-content">
        <!--begin::Container-->
        <div class="container-fluid">
            <!--begin::Row-->
            <div class="row mb-3">
                <div class="col-md-3">
                    <!-- Profile Image -->
                    <div class="card card-primary card-outline">
                        <div class="card-body box-profile">
                            <div class="text-center">
                                <img class="img-fluid rounded-circle border border-3 p-1 mx-auto d-block" style="width: 100px;"
                                     src="<?= $user->getGravatarURL(150) ?>" alt="User profile picture">
                            </div>

                            <h3 class="profile-username text-center"><?= $user->getFullName() ?></h3>

                            <p class="text-muted text-center"><?= $user->title ?></p>

                            <ul class="list-group list-group-flush mb-3">
                                <li class="list-group-item d-flex justify-content-between align-items-start">
                                    <div class="ms-3 me-auto">
                                        <b># id</b>
                                    </div>
                                    <span class="badge bg-primary "><?= $user->id ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-start">
                                    <div class="ms-3 me-auto">
                                        <b>Haftalık Ders Sayısı</b>
                                    </div>
                                    <span class="badge text-bg-primary "><?= count($user->lessons) ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-start">
                                    <div class="ms-3 me-auto">
                                        <b>Toplam Öğrenci Sayısı</b>
                                    </div>
                                    <span class="badge text-bg-primary "><?= array_reduce($user->lessons, fn($sum, $l) => $sum + ($l->size ?? 0), 0) ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-start">
                                    <div class="ms-3 me-auto">
                                        <b>Haftalık Ders Saati</b>
                                    </div>
                                    <span class="badge text-bg-primary "><?= array_reduce($user->lessons, fn($sum, $l) => $sum + (empty($l->parentLesson) ? ($l->hours ?? 0) : 0), 0) ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-start">
                                    <div class="ms-3 me-auto">
                                        <b>Son Giriş Tarihi</b>
                                    </div>
                                    <span class="badge text-bg-secondary "><?= $user->getLastLogin() ?></span>
                                </li>
                            </ul>

                        </div>
                        <!-- /.card-body -->
                        <div class="card-footer text-end">
                            <?php if (Gate::check("delete",$user)): ?>
                                <form action="/ajax/deleteuser/<?= $user->id ?>"
                                      class="ajaxFormDelete"
                                      id="deleteUser-<?= $user->id ?>"
                                      method="post">
                                    <input type="hidden" name="id"
                                           value="<?= $user->id ?>">
                                    <input form="deleteUser-<?= $user->id ?>" type="submit" class="btn btn-danger"
                                           value="Kullanıcıyı Sil">
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- /.card -->
                </div>
                <!-- /.col -->
                <div class="col-md-9">
                    <!-- About Me Box  -->
                    <div class="card card-outline card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Bilgilerim</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse">
                                    <i data-lte-icon="expand" class="bi bi-plus-lg"></i>
                                    <i data-lte-icon="collapse" class="bi bi-dash-lg"></i>
                                </button>
                            </div>
                        </div>
                        <!-- /.card-header -->
                        <div class="card-body">
                            <form action="/ajax/updateUser" method="post" class="ajaxForm updateForm"
                                  title="Bilgileri Güncelle">
                                <div class="row">
                                    <div class="col-md-6">
                                        <input type="hidden" name="id" value="<?= $user->id ?>">
                                        <div class="mb-3">
                                            <label class="form-label" for="name">Adı</label>
                                            <input type="text" class="form-control" id="name" name="name"
                                                   placeholder="Adı"
                                                   value="<?= htmlspecialchars($user->name ?? '') ?>"
                                                   required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label" for="last_name">Soyadı</label>
                                            <input type="text" class="form-control" id="last_name" name="last_name"
                                                   placeholder="Soyadı"
                                                   value="<?= htmlspecialchars($user->last_name ?? '') ?>"
                                                   required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label" for="mail">e-Posta</label>
                                            <input type="email" class="form-control" id="mail" name="mail"
                                                   placeholder="e-Posta"
                                                   value="<?= htmlspecialchars($user->mail ?? '') ?>"
                                                   required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label" for="password">Parola</label>
                                            <input type="password" class="form-control" id="password" name="password"
                                                   placeholder="Parola">
                                            <div class="form-text text-muted">Boş bırakıldığı taktirde işleme alınmayacaktır.</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label" for="role">Rol</label>
                                            <select class="form-select" id="role" name="role" <?= !$canEditSpecialFields ? 'disabled' : '' ?>>
                                                <?php foreach (App\Enums\UserRole::getAssignableRoles() as $roleEnum): ?>
                                                    <option value="<?= $roleEnum->value ?>"
                                                        <?= $roleEnum->value == $user->role ? "selected" : "" ?>><?= $roleEnum->getLabel() ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label" for="title">Ünvan</label>
                                            <select class="form-select" id="title" name="title" <?= !$canEditSpecialFields ? 'disabled' : '' ?>>
                                                <option value=""></option>
                                                <?php foreach (App\Enums\UserTitle::cases() as $titleEnum): ?>
                                                    <option value="<?= $titleEnum->value ?>"
                                                        <?= $titleEnum->value == $user->title ? "selected" : "" ?>><?= $titleEnum->value ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label" for="unit_id">Üst Birim</label>
                                            <select class="form-select tom-select" id="unit_id" name="unit_id" <?= !$canEditSpecialFields ? 'disabled' : '' ?>>
                                                <option value="">Birim Seçiniz (Opsiyonel)</option>
                                                <?php foreach ($units as $unit): ?>
                                                    <option value="<?= $unit->id ?>" <?= $unit->id == $user->unit_id ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($unit->name) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label" for="department_id">Bölüm</label>
                                            <select class="form-select tom-select" id="department_id" name="department_id" <?= !$canEditSpecialFields ? 'disabled' : '' ?> data-selected="<?= $user->department_id ?? '' ?>">
                                                <option value="0">İlk olarak Birim Seçiniz</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label" for="program_id">Program</label>
                                            <select class="form-select" id="program_id" name="program_id" <?= !$canEditSpecialFields ? 'disabled' : '' ?> data-selected="<?= $user->program_id ?? '' ?>">
                                                <option value="0">İlk olarak Bölüm Seçiniz</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                        </div>
                        <!-- /.card-body -->
                        <div class="card-footer text-end">
                            <?php if (Gate::check("update", $user)): ?>
                            <button type="submit" class="btn btn-primary">Güncelle</button>
                            <?php endif; ?>
                        </div>

                        </form>
                    </div>
                    <!-- /.card -->
                </div>
                <!-- /.col -->
            </div>
            <!--end::Row-->
            <!--begin::Row-->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="card card-primary card-outline card-tabs">
                        <div class="card-header p-0 border-bottom-0">
                            <ul class="nav nav-tabs" id="profileTabs" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" id="lessons-tab" data-bs-toggle="pill" href="#lessons" role="tab" aria-controls="lessons" aria-selected="true">Derslerim</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="schedule-tab" data-bs-toggle="pill" href="#schedule" role="tab" aria-controls="schedule" aria-selected="false">Ders Programım</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="exams-tab" data-bs-toggle="pill" href="#exams" role="tab" aria-controls="exams" aria-selected="false">Sınav Programım</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="notes-tab" data-bs-toggle="pill" href="#notes" role="tab" aria-controls="notes" aria-selected="false">
                                        <i class="bi bi-journal-text me-1"></i> Program Notları & İstekleri
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <div class="card-body">
                            <div class="tab-content" id="profileTabsContent">
                                <div class="tab-pane fade show active" id="lessons" role="tabpanel" aria-labelledby="lessons-tab">
                                    <?php if (!empty($groupedAssignments)): ?>
                                        <div class="accordion" id="lessonsAccordion">
                                            <?php 
                                            $activePeriodKey = getSettingValue('academic_year') . ' ' . getSettingValue('semester');
                                            $idx = 0;
                                            foreach ($groupedAssignments as $periodKey => $lessons):
                                                $idx++;
                                                $collapseId = "collapsePeriod_" . $idx;
                                                $isCurrent = ($periodKey === $activePeriodKey) || ($idx === 1 && !isset($groupedAssignments[$activePeriodKey]));
                                            ?>
                                                <div class="accordion-item mb-2 border rounded">
                                                    <h2 class="accordion-header" id="heading_<?= $idx ?>">
                                                        <button class="accordion-button <?= $isCurrent ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>" aria-expanded="<?= $isCurrent ? 'true' : 'false' ?>" aria-controls="<?= $collapseId ?>">
                                                            <strong><?= htmlspecialchars($periodKey) ?> Dönemi Dersleri</strong>
                                                            <span class="badge text-bg-primary ms-2"><?= count($lessons) ?> Ders</span>
                                                        </button>
                                                    </h2>
                                                    <div id="<?= $collapseId ?>" class="accordion-collapse collapse <?= $isCurrent ? 'show' : '' ?>" aria-labelledby="heading_<?= $idx ?>" data-bs-parent="#lessonsAccordion">
                                                        <div class="accordion-body">
                                                            <div class="row">
                                                                <?php foreach ($lessons as $lesson): ?>
                                                                    <div class="col-md-4 col-sm-6 p-1">
                                                                        <a href="/admin/lesson/<?= $lesson->id ?>" class="text-decoration-none text-reset">
                                                                            <?php
                                                                            $popoverAttr = '';
                                                                            if (!empty($lesson->parentLesson)) {
                                                                                $popoverTitle = 'Birleştirilmiş Ders';
                                                                                $popoverContent = 'Bu ders ' . $lesson->parentLesson->getFullName(addCode: true, addProgram: true) . ' dersine bağlıdır.';
                                                                                $popoverAttr = 'data-bs-toggle="popover" title="' . htmlspecialchars($popoverTitle) . '" data-bs-content="' . htmlspecialchars($popoverContent) . '" data-bs-trigger="hover"';
                                                                            }
                                                                            ?>
                                                                            <div class="lesson-card w-100 <?= $lesson->getScheduleCSSClass() ?? '' ?>" style="cursor: pointer;" <?= $popoverAttr ?>>
                                                                                <span class="lesson-name" title="<?= htmlspecialchars($lesson->name) ?>">
                                                                                    <?= htmlspecialchars($lesson->code) ?> - <?= htmlspecialchars($lesson->name) ?>
                                                                                </span>
                                                                                <div class="lesson-meta">
                                                                                    <span class="lesson-lecturer">
                                                                                        <?= htmlspecialchars($lesson->program->name ?? "-") ?>
                                                                                    </span>
                                                                                    <span class="lesson-classroom">
                                                                                        <?= $lesson->hours ?> Saat / <?= $lesson->size ?> Kişi
                                                                                    </span>
                                                                                </div>
                                                                            </div>
                                                                        </a>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="col-12 text-center text-muted py-3">Hoca üzerine kayıtlı ders görevlendirmesi bulunamadı.</div>
                                    <?php endif; ?>
                                </div>

                                <div class="tab-pane fade" id="schedule" role="tabpanel" aria-labelledby="schedule-tab">
                                    <?= $scheduleHTML ?>
                                </div>
                                <div class="tab-pane fade" id="exams" role="tabpanel" aria-labelledby="exams-tab">
                                    <!-- Nested Tabs for Exams -->
                                    <ul class="nav nav-tabs mb-3" id="examTabs" role="tablist">
                                        <li class="nav-item">
                                            <a class="nav-link active" id="midterm-tab" data-bs-toggle="tab" href="#midterm" role="tab">Ara Sınav</a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" id="final-tab" data-bs-toggle="tab" href="#final" role="tab">Final</a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" id="makeup-tab" data-bs-toggle="tab" href="#makeup" role="tab">Bütünleme</a>
                                        </li>
                                    </ul>
                                    <div class="tab-content" id="examTabsContent">
                                        <div class="tab-pane fade show active" id="midterm" role="tabpanel">
                                            <?= $midtermScheduleHTML ?>
                                        </div>
                                        <div class="tab-pane fade" id="final" role="tabpanel">
                                            <?= $finalScheduleHTML ?>
                                        </div>
                                        <div class="tab-pane fade" id="makeup" role="tabpanel">
                                            <?= $makeupScheduleHTML ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="notes" role="tabpanel" aria-labelledby="notes-tab">
                                    <div class="row">
                                        <div class="col-md-5">
                                            <div class="card card-outline card-primary mb-3">
                                                <div class="card-header">
                                                    <h3 class="card-title fs-6 mb-0"><i class="bi bi-pencil-square me-1"></i> Not / İstek Ekle veya Güncelle</h3>
                                                </div>
                                                <div class="card-body">
                                                    <form id="form-save-schedule-note">
                                                        <input type="hidden" name="user_id" value="<?= (int)$user->id ?>">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Akademik Yıl:</label>
                                                            <select class="form-select" name="academic_year">
                                                                <?php for ($year = 2023; $year <= date('Y'); $year++): ?>
                                                                    <option value="<?= $year . ' - ' . ($year + 1) ?>" <?= getSettingValue("academic_year") == $year . ' - ' . ($year + 1) ? 'selected' : '' ?>>
                                                                        <?= $year . ' - ' . ($year + 1) ?>
                                                                    </option>
                                                                <?php endfor; ?>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Dönem:</label>
                                                            <select class="form-select" name="semester">
                                                                <option value="Güz" <?= getSettingValue("semester") == 'Güz' ? 'selected' : '' ?>>Güz</option>
                                                                <option value="Bahar" <?= getSettingValue("semester") == 'Bahar' ? 'selected' : '' ?>>Bahar</option>
                                                                <option value="Yaz" <?= getSettingValue("semester") == 'Yaz' ? 'selected' : '' ?>>Yaz</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Program Türü:</label>
                                                            <select class="form-select" name="schedule_type">
                                                                <option value="lesson">Ders Programı</option>
                                                                <option value="midterm-exam">Ara Sınav Programı</option>
                                                                <option value="final-exam">Final Sınav Programı</option>
                                                                <option value="makeup-exam">Bütünleme Sınav Programı</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Program Notu / Özel İstek:</label>
                                                            <textarea class="form-control" name="note" rows="4" placeholder="Ders/sınav programı düzenlenirken dikkat edilmesini istediğiniz kısıtları ve notları giriniz..." required></textarea>
                                                        </div>
                                                        <div id="note-update-warning" class="alert alert-warning p-2 small mb-3 d-none">
                                                            <i class="bi bi-exclamation-triangle-fill me-1"></i> Bu dönem ve program türü için kayıtlı bir notunuz bulunmaktadır. Güncellediğinizde önceki yetkili yanıtı ve durumu sıfırlanacaktır.
                                                        </div>
                                                        <button type="submit" id="btn-save-note" class="btn btn-primary w-100">
                                                            <i class="bi bi-check-circle-fill me-1"></i> Notu Kaydet
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-7">
                                            <div class="card card-outline card-info mb-3">
                                                <div class="card-header">
                                                    <h3 class="card-title fs-6 mb-0"><i class="bi bi-clock-history me-1"></i> İletilen Program Notları ve Durumları</h3>
                                                </div>
                                                <div class="card-body p-0">
                                                    <div id="my-schedule-notes-list" class="p-3">
                                                        <div class="text-center py-4">
                                                            <div class="spinner-border text-primary" role="status"></div>
                                                            <p class="mt-2 text-muted">Notlar yükleniyor...</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- /.card -->
                    </div>
                </div>
            </div>
        </div>
        <!--end::Container-->
    </div>
    <!--end::App Content-->
</main>
<!--end::App Main-->
<script>
    //todo bu kodlar ayrı bir dosyaya taşınacak ve assetmanager ile profil sayfalarına yüklenecek
window.currentUserNotes = [];

window.checkFormExistingNote = function() {
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

    const match = (window.currentUserNotes || []).find(n => 
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
};

window.loadMyScheduleNotes = async function() {
    const listContainer = document.getElementById('my-schedule-notes-list');
    if (!listContainer) return;

    const profileUserId = <?= (int)$user->id ?>;
    const canManageNotes = <?= \App\Core\Gate::check('canManageNotes', \App\Models\ScheduleNote::class) ? 'true' : 'false' ?>;

    const formData = new FormData();
    formData.append('user_id', profileUserId);

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
            window.currentUserNotes = notes;
            window.checkFormExistingNote();

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
};

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('#lessons [data-bs-toggle="popover"]').forEach(el => new bootstrap.Popover(el, { trigger: 'hover' }));

    const notesTab = document.getElementById('notes-tab');
    if (notesTab) {
        notesTab.addEventListener('shown.bs.tab', function() {
            window.loadMyScheduleNotes();
        });
        if (notesTab.classList.contains('active')) {
            window.loadMyScheduleNotes();
        }
    }

    const form = document.getElementById('form-save-schedule-note');
    if (form) {
        form.querySelectorAll('select').forEach(select => {
            select.addEventListener('change', function() {
                window.checkFormExistingNote();
            });
        });
    }
});
</script>