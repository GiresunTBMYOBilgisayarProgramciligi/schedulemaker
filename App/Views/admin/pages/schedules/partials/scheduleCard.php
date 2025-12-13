<!--  owner_type, owner id gibi bilgileri buraya doldurmak yerine sadece schedule id kullanmak nasıl olur? -->
<!--begin::Row Program Satırı-->
<div class="row mb-3">
    <div class="col-12">
        <div class="card card-outline card-primary"
        data-owner-type="<?= $filters['owner_type'] ?>"
        data-owner-id="<?= $filters['owner_id'] ?>"
        data-type="<?= $filters['type'] ?>"
        data-academic-year="<?= $filters['academic_year'] ?>"
        data-semester="<?= $filters['semester'] ?>"
        <?= $dataSemesterNo ?>
        data-owner-name="<?= $ownerName ?>"
        >
            <div class="card-header">
                <h3 class="card-title"><?= $cardTitle ?></h3>
                <div class="card-tools"><!-- todo butondan değil card dan bilgiler alınacak-->
                    <div class="btn-group" role="group" aria-label="Dışa aktarma">
                        <button id="singlePageExport" type="button" class="btn btn-outline-primary btn-sm" >
                            <span>Excel\'e aktar</span>
                        </button>
                        <button id="singlePageCalendar" type="button" class="btn btn-outline-secondary btn-sm" >
                            <span>Takvime kaydet</span>
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?= $availableLessonsHTML ?>
                <!--begin::Row Schedule Table-->
                <div class="row">
                    <div class="schedule-table col-md-12" <?= $dataSemesterNo ?>>
                        <?= $scheduleTableHTML ?>
                    </div><!--end::schedule-table-->
                </div><!--end::Row-->
            </div><!--end::card-body-->
        </div><!--end::Card-->
    </div>
</div><!--end::Row-->