<?php
/**
 * Sınav Analizleri
 *
 * @var string       $page_title
 * @var Unit[]       $units
 * @var array        $filters         ['academic_year', 'semester', 'exam_type', 'unit_id', 'department_id']
 * @var ExamType[]   $examTypes
 * @var array        $examDutyStats   UserRepository::getExamDutyStats() çıktısı
 * @var array        $examDutySummary ['total', 'assigned_count', 'unassigned_count', 'average', 'max']
 * @var array        $classOverlaps   ExamAnalysisService::classOverlaps() çıktısı
 * @var array        $dailyLimit      ExamAnalysisService::dailyLimitViolations() çıktısı
 */

use App\Core\View;
use App\Enums\ExamType;
use App\Helpers\TimeHelper;
use function App\Helpers\e;

$dutyWithTasks = array_filter($examDutyStats, fn($r) => $r['total'] > 0);
$selectedExamType = ExamType::from($filters['exam_type']);

// Bir sınavı tek satırda gösterir: "BSP-141 Muhasebe I (55 öğrenci) 09:00-10:00"
$lessonLine = static fn(array $d) => e(
    $d['class'] . '. sınıf · ' . $d['code'] . ' ' . $d['name']
    . ($d['group_no'] ? " ({$d['group_no']}. grup)" : '')
    . ' · ' . substr($d['start'], 0, 5) . '-' . substr($d['end'], 0, 5)
    . ($d['size'] ? " · {$d['size']} öğrenci" : '')
);
?>
<!--begin::App Main-->
<main class="app-main">
    <!--begin::App Content Header-->
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0"><?= e($page_title) ?></h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="/admin">Ana Sayfa</a></li>
                        <li class="breadcrumb-item">Takvim İşlemleri</li>
                        <li class="breadcrumb-item active">Sınav Analizleri</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <!--end::App Content Header-->
    <!--begin::App Content-->
    <div class="app-content">
        <div class="container-fluid">

            <!-- Filtre -->
            <div class="card card-outline card-primary mb-4">
                <div class="card-body">
                    <form method="get" action="/admin/examanalysis" class="row g-2 g-md-3 align-items-start" id="examAnalysisFilterForm">
                        <div class="col-6 col-md-2">
                            <select class="form-select" name="academic_year" aria-label="Akademik Yıl">
                                <?php for ($year = 2023; $year <= (int)date('Y'); $year++): ?>
                                    <?php $yearValue = $year . ' - ' . ($year + 1); ?>
                                    <option value="<?= e($yearValue) ?>" <?= $filters['academic_year'] === $yearValue ? 'selected' : '' ?>><?= e($yearValue) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <select class="form-select" name="semester" aria-label="Dönem">
                                <?php foreach (['Güz', 'Bahar', 'Yaz'] as $semesterOption): ?>
                                    <option value="<?= e($semesterOption) ?>" <?= $filters['semester'] === $semesterOption ? 'selected' : '' ?>><?= e($semesterOption) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-2">
                            <select class="form-select" name="exam_type" aria-label="Sınav Tipi" title="Çakışma ve günlük sınır analizleri bu sınav tipi için yapılır">
                                <?php foreach ($examTypes as $examType): ?>
                                    <option value="<?= e($examType->value) ?>" <?= $selectedExamType === $examType ? 'selected' : '' ?>><?= e($examType->label()) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <?= View::renderComponent('schedules/_programSelector', [
                                'units'                => $units ?? [],
                                'selectedUnitId'       => $filters['unit_id'] ?? '',
                                'selectedDepartmentId' => $filters['department_id'] ?? '',
                                'showProgram'          => false,
                                'showSemesterNo'       => false,
                                'showButton'           => false,
                            ]) ?>
                        </div>
                        <div class="col-12 col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-fill">
                                <i class="bi bi-funnel me-1"></i>Filtrele
                            </button>
                            <a href="/admin/examanalysis" class="btn btn-outline-secondary" title="Filtreyi temizle">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sekmeler -->
            <ul class="nav nav-tabs mb-3" id="examAnalysisTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-duty" data-bs-toggle="tab" data-bs-target="#pane-duty" type="button" role="tab" aria-controls="pane-duty" aria-selected="true">
                        <i class="bi bi-clipboard-data me-1"></i> Gözetmenlik Dağılımı
                        <span class="badge text-bg-info ms-1"><?= (int)$examDutySummary['total'] ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-overlap" data-bs-toggle="tab" data-bs-target="#pane-overlap" type="button" role="tab" aria-controls="pane-overlap" aria-selected="false">
                        <i class="bi bi-layers me-1"></i> Sınıf Çakışmaları
                        <span class="badge <?= $classOverlaps['conflict_count'] ? 'text-bg-danger' : 'text-bg-success' ?> ms-1"><?= (int)$classOverlaps['conflict_count'] ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-daily" data-bs-toggle="tab" data-bs-target="#pane-daily" type="button" role="tab" aria-controls="pane-daily" aria-selected="false">
                        <i class="bi bi-calendar-x me-1"></i> Günlük Sınav Sınırı
                        <span class="badge <?= $dailyLimit['violation_count'] ? 'text-bg-danger' : 'text-bg-success' ?> ms-1"><?= (int)$dailyLimit['violation_count'] ?></span>
                    </button>
                </li>
            </ul>

            <div class="tab-content">

                <!-- ===================== Gözetmenlik Dağılımı ===================== -->
                <div class="tab-pane fade show active" id="pane-duty" role="tabpanel" aria-labelledby="tab-duty" tabindex="0">
                    <div class="card card-outline card-info mb-4 exam-duty" id="examDutyCard">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="bi bi-clipboard-data me-1"></i> Sınav Gözetmenlik Görevleri
                                <span class="text-muted small ms-1"><?= e($filters['academic_year'] . ' ' . $filters['semester']) ?></span>
                            </h3>
                        </div>
                        <div class="card-body">
                            <!-- Özet -->
                            <div class="row g-2 mb-3 text-center">
                                <div class="col-6 col-md-3">
                                    <div class="border rounded py-2">
                                        <div class="fs-4 fw-semibold"><?= $examDutySummary['total'] ?></div>
                                        <div class="text-muted small">Toplam Görev</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="border rounded py-2">
                                        <div class="fs-4 fw-semibold"><?= $examDutySummary['assigned_count'] ?></div>
                                        <div class="text-muted small">Görevli Hoca</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="border rounded py-2">
                                        <div class="fs-4 fw-semibold"><?= $examDutySummary['average'] ?></div>
                                        <div class="text-muted small">Hoca Başına Ortalama</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="border rounded py-2">
                                        <div class="fs-4 fw-semibold"><?= $examDutySummary['unassigned_count'] ?></div>
                                        <div class="text-muted small">Görevi Olmayan</div>
                                    </div>
                                </div>
                            </div>

                            <?php if (empty($dutyWithTasks)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-clipboard-x fs-2"></i>
                                    <p class="mt-2 mb-0">Bu dönem için atanmış gözetmenlik görevi bulunamadı.</p>
                                </div>
                            <?php else: ?>
                            <div class="row g-3">
                                <!-- Grafik -->
                                <div class="col-12 col-xl-7">
                                    <div class="d-flex flex-wrap gap-3 mb-2 small text-body-secondary">
                                        <?php foreach (ExamType::cases() as $examType): ?>
                                            <span><span class="duty-swatch seg-<?= $examType->value ?>"></span><?= e($examType->label()) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="duty-chart">
                                        <?php foreach ($dutyWithTasks as $row): ?>
                                        <?php
                                            $typeSummary = implode(', ', array_map(
                                                fn(ExamType $type) => $type->label() . ': ' . $row['by_type'][$type->value],
                                                ExamType::cases()
                                            ));
                                            $tooltip = $row['full_name'] . ' — ' . $typeSummary
                                                . ' (Toplam ' . $row['total'] . ', ' . TimeHelper::formatDuration($row['total_minutes']) . ')';
                                        ?>
                                        <div class="duty-row" title="<?= e($tooltip) ?>">
                                            <div class="duty-name"><?= e($row['full_name']) ?></div>
                                            <div class="duty-track" style="width: <?= round($row['total'] / $examDutySummary['max'] * 100, 2) ?>%;">
                                                <?php foreach ($row['by_type'] as $type => $count): ?>
                                                    <?php if ($count > 0): ?>
                                                    <div class="duty-seg seg-<?= e($type) ?>" style="flex: <?= $count ?> 0 0;"></div>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="duty-value"><?= $row['total'] ?></div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Liste -->
                                <div class="col-12 col-xl-5">
                                    <div class="duty-table">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead class="table-light sticky-top">
                                                <tr>
                                                    <th>Öğretim Elemanı</th>
                                                    <?php foreach (ExamType::cases() as $examType): ?>
                                                        <th class="text-center"><?= e($examType->label()) ?></th>
                                                    <?php endforeach; ?>
                                                    <th class="text-center">Toplam</th>
                                                    <th class="text-end">Süre</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($examDutyStats as $row): ?>
                                                <tr class="<?= $row['total'] === 0 ? 'text-muted' : '' ?>">
                                                    <td>
                                                        <a href="/admin/profile/<?= $row['id'] ?>" class="fw-semibold"><?= e($row['full_name']) ?></a>
                                                        <?php if (!empty($row['department_name'])): ?>
                                                            <div class="text-muted duty-department"><?= e($row['department_name']) ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <?php foreach ($row['by_type'] as $count): ?>
                                                        <td class="text-center"><?= $count ?></td>
                                                    <?php endforeach; ?>
                                                    <td class="text-center fw-semibold"><?= $row['total'] ?></td>
                                                    <td class="text-end small text-nowrap"><?= TimeHelper::formatDuration($row['total_minutes']) ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ===================== Sınıf Çakışmaları ===================== -->
                <div class="tab-pane fade" id="pane-overlap" role="tabpanel" aria-labelledby="tab-overlap" tabindex="0">
                    <div class="card card-outline card-danger mb-4">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="bi bi-layers me-1"></i> Sınıf Düzeyi Çakışmaları
                                <span class="text-muted small ms-1"><?= e($selectedExamType->label() . ' · ' . $filters['academic_year'] . ' ' . $filters['semester']) ?></span>
                            </h3>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small">
                                Aynı programda farklı sınıf düzeylerinin (örn. 1. ve 2. sınıf) sınavlarının aynı saatte yapılması.
                                Sınav birleştirmesiyle bağlı dersler aynı sınav sayıldığı için çakışma sayılmaz.
                            </p>
                            <div class="row g-2 mb-3 text-center">
                                <div class="col-6">
                                    <div class="border rounded py-2">
                                        <div class="fs-4 fw-semibold"><?= (int)$classOverlaps['conflict_count'] ?></div>
                                        <div class="text-muted small">Çakışma</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded py-2">
                                        <div class="fs-4 fw-semibold"><?= count($classOverlaps['programs']) ?></div>
                                        <div class="text-muted small">Etkilenen Program</div>
                                    </div>
                                </div>
                            </div>

                            <?php if (empty($classOverlaps['programs'])): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-check-circle fs-2 text-success"></i>
                                    <p class="mt-2 mb-0">
                                        <?= $classOverlaps['item_count'] ? 'Sınıf düzeyi çakışması bulunamadı.' : 'Bu filtre için sınav programı kaydı bulunamadı.' ?>
                                    </p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($classOverlaps['programs'] as $program): ?>
                                <div class="mb-3">
                                    <h6 class="mb-2">
                                        <?= e($program['program']) ?>
                                        <span class="text-muted small"><?= e($program['unit']) ?></span>
                                        <span class="badge text-bg-danger ms-1"><?= count($program['conflicts']) ?></span>
                                    </h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width: 7rem;">Gün</th>
                                                    <th>Birinci Sınav</th>
                                                    <th>İkinci Sınav</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($program['conflicts'] as $conflict): ?>
                                                <tr>
                                                    <td><?= e($conflict['day']) ?></td>
                                                    <td><?= $lessonLine($conflict['first']) ?></td>
                                                    <td><?= $lessonLine($conflict['second']) ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ===================== Günlük Sınav Sınırı ===================== -->
                <div class="tab-pane fade" id="pane-daily" role="tabpanel" aria-labelledby="tab-daily" tabindex="0">
                    <div class="card card-outline card-warning mb-4">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="bi bi-calendar-x me-1"></i> Günlük Sınav Sınırını Aşan Sınıflar
                                <span class="text-muted small ms-1"><?= e($selectedExamType->label() . ' · ' . $filters['academic_year'] . ' ' . $filters['semester']) ?></span>
                            </h3>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small">
                                Yönetmeliğe göre bir sınıfın bir günde en fazla <?= (int)$dailyLimit['max'] ?> sınavı olabilir.
                                Aynı saat dilimindeki sınavlar (gruplar, sınav birleştirmeleri) tek sınav sayılır.
                            </p>
                            <div class="row g-2 mb-3 text-center">
                                <div class="col-12 col-md-4 offset-md-4">
                                    <div class="border rounded py-2">
                                        <div class="fs-4 fw-semibold"><?= count($dailyLimit['programs']) ?></div>
                                        <div class="text-muted small">Etkilenen Program</div>
                                    </div>
                                </div>
                            </div>

                            <?php if (empty($dailyLimit['programs'])): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-check-circle fs-2 text-success"></i>
                                    <p class="mt-2 mb-0">
                                        <?= $dailyLimit['item_count'] ? 'Günlük sınırı aşan sınıf bulunamadı.' : 'Bu filtre için sınav programı kaydı bulunamadı.' ?>
                                    </p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($dailyLimit['programs'] as $program): ?>
                                <div class="mb-3">
                                    <h6 class="mb-2">
                                        <?= e($program['program']) ?>
                                        <span class="text-muted small"><?= e($program['unit']) ?></span>
                                        <span class="badge text-bg-warning ms-1"><?= count($program['violations']) ?></span>
                                    </h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width: 6rem;">Sınıf</th>
                                                    <th style="width: 7rem;">Gün</th>
                                                    <th style="width: 6rem;" class="text-center">Sınav</th>
                                                    <th>Sınavlar</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($program['violations'] as $violation): ?>
                                                <tr>
                                                    <td><?= (int)$violation['class'] ?>. sınıf</td>
                                                    <td><?= e($violation['day']) ?></td>
                                                    <td class="text-center"><span class="badge text-bg-warning"><?= (int)$violation['exam_count'] ?></span></td>
                                                    <td>
                                                        <?php foreach ($violation['exams'] as $exam): ?>
                                                            <div>
                                                                <span class="text-muted"><?= e(substr($exam['start'], 0, 5) . '-' . substr($exam['end'], 0, 5)) ?></span>
                                                                <?= e(implode(' + ', $exam['lessons'])) ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <!--end::App Content-->
</main>
<!--end::App Main-->

<script>
// Filtre gönderildiğinde sayfa yenilendiği için son açık sekmeyi hatırla (bootstrap betiği sayfa sonunda yüklenir)
document.addEventListener('DOMContentLoaded', function () {
    var storageKey = 'examAnalysisActiveTab';
    var tabs = document.getElementById('examAnalysisTabs');
    if (!tabs || !window.bootstrap) return;

    try {
        var saved = sessionStorage.getItem(storageKey);
        var savedTab = saved ? document.getElementById(saved) : null;
        if (savedTab) bootstrap.Tab.getOrCreateInstance(savedTab).show();
    } catch (e) { /* sessionStorage kullanılamıyorsa ilk sekme açık kalır */ }

    tabs.addEventListener('shown.bs.tab', function (event) {
        try { sessionStorage.setItem(storageKey, event.target.id); } catch (e) { /* yoksay */ }
    });
});
</script>
