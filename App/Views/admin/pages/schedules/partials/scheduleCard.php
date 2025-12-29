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
            data-preference-mode="<?= isset($preference_mode) && $preference_mode ? 'true' : 'false' ?>">
            <div class="card-header">
                <h3 class="card-title"><?= $cardTitle ?></h3>
                <div class="card-tools"><!-- todo butondan değil card dan bilgiler alınacak-->
                    <div class="btn-group" role="group" aria-label="Dışa aktarma">
                        <button id="singlePageExport" type="button" class="btn btn-outline-primary btn-sm">
                            <span>Excel\'e aktar</span>
                        </button>
                        <button id="singlePageCalendar" type="button" class="btn btn-outline-secondary btn-sm">
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
                    <div class="schedule-table col-md-12">
                        <?= $scheduleTableHTML ?>
                    </div><!--end::schedule-table-->
                </div><!--end::Row-->
            </div><!--end::card-body-->
        </div><!--end::Card-->
    </div>
</div><!--end::Row-->