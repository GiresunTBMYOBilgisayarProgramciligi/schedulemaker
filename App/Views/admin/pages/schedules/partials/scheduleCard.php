<?php
use App\Models\Schedule;
use App\Core\Gate;
use App\Enums\PermissionType;
use function App\Helpers\getSettingValue;
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
$cardClasses = $no_card ? "schedule-card" : "card schedule-card card-outline card-primary";
$headerClasses = $no_card ? "d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2" : "card-header d-flex justify-content-between align-items-center flex-wrap gap-2";
$bodyClasses = $no_card ? "" : "card-body";
?>
<!--begin::Row Program Satırı-->
<div class="row mb-3">
    <div class="col-12">
        <div class="<?= $cardClasses ?>" id="scheduleCard-<?= $schedule->id ?>" data-schedule-id="<?= $schedule->id ?>"
            data-duration="<?= $duration ?? 50 ?>" data-break="<?= $break ?? 10 ?>"
            data-only-table="<?= isset($only_table) && $only_table ? 'true' : 'false' ?>"
            data-preference-mode="<?= isset($preference_mode) && $preference_mode ? 'true' : 'false' ?>"
            data-week-count="<?= $weekCount ?? 1 ?>" data-type="<?= $schedule->type ?>"
            data-schedule-screen-name="<?= $schedule->getScheduleScreenName() ?>">
            <div class="<?= $headerClasses ?>">
                <?php if (!$no_card): ?>
                    <h3 class="card-title"><?= $cardTitle ?></h3>
                <?php endif; ?>

                <?php if (isset($weekCount) && $weekCount > 1): ?>
                    <div class="week-navigation mx-auto">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-outline-primary btn-sm prev-week" disabled>
                                <i class="bi bi-chevron-left"></i> Önceki Hafta
                            </button>
                            <span class="btn btn-sm btn-outline-primary disabled current-week-label">1. Hafta</span>
                            <button type="button" class="btn btn-outline-primary btn-sm next-week">
                                Sonraki Hafta <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="d-flex ms-auto <?php echo $no_card ? 'justify-content-end w-100' : 'card-tools'; ?>">
                    <div class="btn-group" role="group" aria-label="Dışa aktarma">
                        <button id="singlePageExport" type="button" class="btn btn-outline-primary btn-sm"
                            data-owner-type="<?= $schedule->owner_type ?>" data-owner-id="<?= $schedule->owner_id ?>">
                            <span>Excel'e aktar</span>
                        </button>
                        <button id="singlePageCalendar" type="button" class="btn btn-outline-secondary btn-sm"
                            data-owner-type="<?= $schedule->owner_type ?>" data-owner-id="<?= $schedule->owner_id ?>">
                            <span>Takvime kaydet</span>
                        </button>
                    </div>
                </div>
            </div>
            <div class="<?= $bodyClasses ?>">
                <?php if (!isset($only_table) || !$only_table): ?>
                    <?= $availableLessonsHTML ?>
                <?php endif; ?>
                <!--begin::Row Schedule Table-->
                <div class="row">
                    <div class="schedule-table-wrapper col-md-12">
                        <?= $scheduleTableHTML ?>
                    </div><!--end::schedule-table-wrapper-->
                </div><!--end::Row-->
            </div><!--end::card-body-->
            <?php if (!$no_card): ?>
            <div class="card-footer d-flex align-items-center bg-light">
                <div class="text-muted small">
                    <i class="bi bi-clock-history me-1"></i> Son Güncelleme: <?= !empty($schedule->updated_at) ? (new \DateTime($schedule->updated_at))->format('d.m.Y H:i') : 'Bilinmiyor' ?>
                </div>
                <?php if (Gate::check(PermissionType::PUBLISH_SCHEDULE->value, $schedule)): ?>
                <div class="form-check form-switch m-0 ms-auto" title="Kullanıcıların görmesi için programı yayınlayın">
                    <input class="form-check-input publish-schedule-toggle" type="checkbox" role="switch" id="publish-switch-<?= $schedule->id ?>" data-schedule-id="<?= $schedule->id ?>" <?= !empty($schedule->is_published) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="publish-switch-<?= $schedule->id ?>"><?= !empty($schedule->is_published) ? 'Yayında' : 'Yayınla' ?></label>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div><!--end::Card-->
    </div>
</div><!--end::Row-->