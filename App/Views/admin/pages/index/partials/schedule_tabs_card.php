<?php
/**
 * Dashboard ders & sınav programları sekmeli kartı (bölüm başkanı ve akademisyen ortak).
 *
 * @var string      $tabPrefix          Element id'leri için önek ('dept', 'lecturer')
 * @var string      $cardColor          card-outline rengi ('info', 'primary')
 * @var string      $emptyClass         Boş durum kutusu sınıfları ('py-4', 'py-5')
 * @var string      $emptyIconClass     Boş durum ikon sınıfları
 * @var string      $scheduleHTML
 * @var array       $examScheduleHTMLs  Sınav tipi => HTML
 * @var string|null $lessonEditUrl      Verilirse sekmeye göre değişen "Düzenle" butonları gösterilir
 */

use App\Enums\ExamType;
use function App\Helpers\e;

$lessonEditUrl = $lessonEditUrl ?? null;

// Sekme tanımları (ders + her sınav tipi)
$tabs = [[
    'key'      => 'lesson',
    'label'    => 'Ders Programı',
    'icon'     => 'bi-calendar-week',
    'html'     => $scheduleHTML ?? '',
    'editUrl'  => $lessonEditUrl,
    'editClass' => 'btn-outline-info',
]];
foreach (ExamType::cases() as $examType) {
    $tabs[] = [
        'key'      => $examType->value,
        'label'    => $examType->label(),
        'icon'     => 'bi-file-earmark-check',
        'html'     => $examScheduleHTMLs[$examType->value] ?? '',
        'editUrl'  => '/admin/editexamschedule?type=' . $examType->value,
        'editClass' => 'btn-outline-danger',
    ];
}
$tabListId = "scheduleTab-{$tabPrefix}";
?>
<div class="card card-outline card-<?= e($cardColor) ?> h-100">
    <div class="card-header p-0 border-bottom-0">
        <div class="d-flex justify-content-between align-items-center px-3 pt-2 pb-0">
            <ul class="nav nav-tabs border-0 flex-grow-1" id="<?= e($tabListId) ?>" role="tablist">
                <?php foreach ($tabs as $i => $tab): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $i === 0 ? 'active' : '' ?>"
                            id="schedTab-<?= e($tabPrefix) ?>-<?= e($tab['key']) ?>"
                            data-bs-toggle="tab"
                            data-bs-target="#schedPane-<?= e($tabPrefix) ?>-<?= e($tab['key']) ?>"
                            type="button" role="tab"
                            aria-controls="schedPane-<?= e($tabPrefix) ?>-<?= e($tab['key']) ?>"
                            aria-selected="<?= $i === 0 ? 'true' : 'false' ?>">
                        <i class="bi <?= e($tab['icon']) ?> me-1"></i><?= e($tab['label']) ?>
                    </button>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($lessonEditUrl !== null): ?>
            <!-- Aktif sekmeye ait Düzenle butonu -->
            <div class="ms-2 pb-1 flex-shrink-0" data-edit-buttons-for="#<?= e($tabListId) ?>">
                <?php foreach ($tabs as $i => $tab): ?>
                <a href="<?= e($tab['editUrl']) ?>"
                   class="btn btn-sm <?= e($tab['editClass']) ?> <?= $i === 0 ? '' : 'd-none' ?>"
                   data-edit-pane="#schedPane-<?= e($tabPrefix) ?>-<?= e($tab['key']) ?>">
                    Düzenle
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <div class="tab-content">
            <?php foreach ($tabs as $i => $tab): ?>
            <div class="tab-pane fade <?= $i === 0 ? 'show active' : '' ?>"
                 id="schedPane-<?= e($tabPrefix) ?>-<?= e($tab['key']) ?>"
                 role="tabpanel"
                 aria-labelledby="schedTab-<?= e($tabPrefix) ?>-<?= e($tab['key']) ?>">
                <?php if (!empty($tab['html'])): ?>
                    <?= $tab['html'] ?>
                <?php else: ?>
                <div class="text-center text-muted <?= e($emptyClass) ?>">
                    <i class="bi bi-calendar-x <?= e($emptyIconClass) ?>"></i>
                    <p class="mt-2"><?= e($tab['label']) ?> henüz oluşturulmamış.</p>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php if ($lessonEditUrl !== null): ?>
<script>
// Sekme değişince yalnızca aktif sekmenin "Düzenle" butonunu göster
document.getElementById('<?= e($tabListId) ?>').addEventListener('shown.bs.tab', function (e) {
    var activePane = e.target.getAttribute('data-bs-target');
    document.querySelectorAll('[data-edit-buttons-for="#<?= e($tabListId) ?>"] [data-edit-pane]').forEach(function (btn) {
        btn.classList.toggle('d-none', btn.getAttribute('data-edit-pane') !== activePane);
    });
});
</script>
<?php endif; ?>
