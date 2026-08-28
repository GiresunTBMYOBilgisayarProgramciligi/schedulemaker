<?php
use App\Models\Schedule;
use App\Core\Gate;
use App\Enums\PermissionType;
/**
 * @var Schedule $schedule
 * @var string $cardTitle
 * @var string $availableLessonsHTML
 * @var string $scheduleTableHTML
 */
?>
<!--begin::Row Program Satırı-->
<?php
$no_card = isset($no_card) && $no_card;
$cardClasses = $no_card ? "schedule-card" : "card schedule-card shadow-sm border-0 rounded-4 mb-4";
$headerClasses = $no_card ? "d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2" : "card-header bg-body border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2";
$bodyClasses = $no_card ? "" : "card-body p-2 p-md-3";
?>
<!--begin::Row Program Satırı-->
<div class="row mb-3">
    <div class="col-12">
        <div class="<?= $cardClasses ?>" id="scheduleCard-<?= $schedule->id ?>" data-schedule-id="<?= $schedule->id ?>"
            data-duration="<?= $duration ?? 50 ?>" data-break="<?= $break ?? 10 ?>"
            data-only-table="<?= isset($only_table) && $only_table ? 'true' : 'false' ?>"
            data-preference-mode="<?= isset($preference_mode) && $preference_mode ? 'true' : 'false' ?>"
            data-week-count="<?= $weekCount ?? 1 ?>" data-type="<?= $schedule->type ?>"
            data-semester-no="<?= $schedule->semester_no ?? '' ?>"
            data-schedule-screen-name="<?= $schedule->getScheduleScreenName() ?>">
            <div class="<?= $headerClasses ?>">
                <?php if (!$no_card): ?>
                    <h3 class="card-title fs-5 fw-bold text-body mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-calendar3 text-primary"></i>
                        <span><?= $cardTitle ?></span>
                    </h3>
                <?php endif; ?>

                <?php if (isset($weekCount) && $weekCount > 1): ?>
                    <div class="week-navigation my-1 my-sm-0 mx-auto mx-sm-0">
                        <div class="btn-group btn-group-sm shadow-xs" role="group" aria-label="Hafta Navigasyonu">
                            <button type="button" class="btn btn-outline-primary prev-week" disabled title="Önceki Hafta">
                                <i class="bi bi-chevron-left"></i> <span class="d-none d-sm-inline">Önceki</span>
                            </button>
                            <span class="btn btn-outline-primary disabled current-week-label fw-bold px-3">1. Hafta</span>
                            <button type="button" class="btn btn-outline-primary next-week" title="Sonraki Hafta">
                                <span class="d-none d-sm-inline">Sonraki</span> <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="d-flex ms-auto <?php echo $no_card ? 'justify-content-end w-100' : 'card-tools'; ?>">
                    <div class="btn-group btn-group-sm shadow-xs" role="group" aria-label="Dışa aktarma">
                        <button id="singlePageExport" type="button" class="btn btn-outline-success d-inline-flex align-items-center gap-1"
                            data-owner-type="<?= $schedule->owner_type ?>" data-owner-id="<?= $schedule->owner_id ?>" data-semester-no="<?= $schedule->semester_no ?? '' ?>" title="Excel Olarak İndir">
                            <i class="bi bi-file-earmark-excel"></i>
                            <span class="d-none d-sm-inline">Excel'e aktar</span>
                        </button>
                        <button id="singlePageCalendar" type="button" class="btn btn-outline-primary d-inline-flex align-items-center gap-1"
                            data-owner-type="<?= $schedule->owner_type ?>" data-owner-id="<?= $schedule->owner_id ?>" data-semester-no="<?= $schedule->semester_no ?? '' ?>" title="Telefon Takvimine (iCal) Kaydet">
                            <i class="bi bi-calendar-plus"></i>
                            <span class="d-none d-sm-inline">Takvime kaydet</span>
                        </button>
                    </div>
                </div>
            </div>
            <div class="<?= $bodyClasses ?>">
                <?php if (!isset($only_table) || !$only_table): ?>
                    <?= $availableLessonsHTML ?>
                <?php endif; ?>
                <!--begin::Row Schedule Table-->
                <div class="row g-0">
                    <div class="schedule-table-wrapper col-12 table-responsive">
                        <?= $scheduleTableHTML ?>
                    </div><!--end::schedule-table-wrapper-->
                </div><!--end::Row-->
            </div><!--end::card-body-->
            <?php if (!$no_card): ?>
            <div class="card-footer bg-body-tertiary border-top py-2 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="text-muted small d-inline-flex align-items-center gap-1">
                    <i class="bi bi-clock-history"></i>
                    <span>Son Güncelleme: <?= !empty($schedule->updated_at) ? (new \DateTime($schedule->updated_at))->format('d.m.Y H:i') : 'Bilinmiyor' ?></span>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-3 ms-auto">
                    <?php if (!empty($schedule->published_at)): ?>
                    <div class="text-muted small d-inline-flex align-items-center gap-1">
                        <i class="bi bi-calendar-check text-success"></i>
                        <span>Yayınlanma: <?= (new \DateTime($schedule->published_at))->format('d.m.Y H:i') ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (Gate::check(PermissionType::PUBLISH_SCHEDULE->value, $schedule)): ?>
                    <div class="form-check form-switch m-0 d-inline-flex align-items-center gap-1" title="Kullanıcıların görmesi için programı yayınlayın">
                        <input class="form-check-input publish-schedule-toggle m-0" type="checkbox" role="switch" id="publish-switch-<?= $schedule->id ?>" data-schedule-id="<?= $schedule->id ?>" <?= !empty($schedule->is_published) ? 'checked' : '' ?>>
                        <label class="form-check-label small fw-semibold ms-1" for="publish-switch-<?= $schedule->id ?>"><?= !empty($schedule->is_published) ? 'Yayında' : 'Yayınla' ?></label>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div><!--end::Card-->
    </div>
</div><!--end::Row-->