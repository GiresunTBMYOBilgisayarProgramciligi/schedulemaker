<?php
use App\Models\Schedule;
/**
 * @var Schedule $schedule
 * @var string $cardTitle
 * @var string $availableLessonsHTML
 * @var string $scheduleTableHTML
 */
?>
<!--begin::Row Program Satırı-->
<div class="row mb-3">
    <div class="col-12">
        <div class="card schedule-card card-outline card-primary" id="scheduleCard-<?= $schedule->id ?>"
            data-schedule-id="<?= $schedule->id ?>" data-duration="<?= $duration ?? 50 ?>"
            data-break="<?= $break ?? 10 ?>"
            data-only-table="<?= isset($only_table) && $only_table ? 'true' : 'false' ?>"
            data-preference-mode="<?= isset($preference_mode) && $preference_mode ? 'true' : 'false' ?>"
            data-week-count="<?= $weekCount ?? 1 ?>"
            data-type="<?= $schedule->type ?>"
            data-schedule-screen-name="<?= $schedule->getScheduleScreenName() ?>">
            <div
                class="card-header <?= (isset($weekCount) && $weekCount > 1) ? 'd-flex justify-content-between align-items-center' : '' ?>">
                <h3 class="card-title"><?= $cardTitle ?></h3>

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

                <div class="card-tools">
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
            <div class="card-body">
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
        </div><!--end::Card-->
    </div>
</div><!--end::Row-->