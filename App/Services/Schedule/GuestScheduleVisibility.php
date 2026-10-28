<?php

namespace App\Services\Schedule;

use App\Core\Gate;
use App\Enums\ExamType;
use App\Enums\OwnerType;
use App\Enums\PermissionType;
use App\Enums\ScheduleItemStatus;
use App\Middlewares\AuthMiddleware;
use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use App\Repositories\ScheduleRepository;
use Exception;

/**
 * Giriş yapmamış (misafir) kullanıcıların programları nasıl göreceğini belirleyen kurallar.
 *
 * Misafire özel tüm kurallar bu sınıfta toplanır; giriş yapmış kullanıcılar için metotlar
 * programı değiştirmeden döndürür. Programın kendisinin görülüp görülemeyeceği (yayın durumu)
 * SchedulePolicy::view ile belirlenir, bu sınıf yalnızca görülebilen programın içeriğini düzenler.
 *
 * Kurallar:
 *  1. Tercih / uygun değil kayıtları gösterilmez.                                  (forView)
 *  2. Sınavlarda gözetmen isimleri yer almaz: salon atamalarındaki gözetmenler ve derslik
 *     kaydındaki gözetmen. Hoca programındaki gözetmenlik görevleri görünür kalır; hoca
 *     programı yayınlanmadıkça (SchedulePolicy::view) misafir zaten göremez.          (forView, forExport)
 *  3. Sınav kartlarında gözetmen yerine dersin hocası gösterilir.                    (forView)
 *  4. Sınav çıktılarında gözetmen ve program adı yazılmaz; istekteki seçenekler
 *     bu alanlar için yok sayılır.                                                   (exportOptionOverrides)
 *  5. Hoca sınav programında bir dersin sınavı, dersin program/yarıyıl programı
 *     yayınlandıysa görünür (ders programları ayrıca yayınlanmaz).                   (filterVisibleLessonSchedules)
 *
 * Bu kuralların arayüzdeki karşılığı: _lessonCard.php (hidesObservers) ve
 * exportSchedule.js (getExportOptionFields).
 */
class GuestScheduleVisibility
{
    public static function isGuest(): bool
    {
        return AuthMiddleware::user() === null;
    }

    /**
     * Kural 2: Misafire gözetmen bilgisi gösterilmez.
     */
    public static function hidesObservers(): bool
    {
        return self::isGuest();
    }

    /**
     * Ekranda gösterilecek programı misafir kurallarına göre hazırlar (kural 1, 2, 3; kaydedilmez).
     *
     * @throws Exception
     */
    public function forView(Schedule $schedule): Schedule
    {
        if (!self::isGuest()) {
            return $schedule;
        }

        $schedule->items = array_values(array_filter(
            $schedule->items ?? [],
            fn(ScheduleItem $item) => !in_array($item->status, [ScheduleItemStatus::PREFERRED->value, ScheduleItemStatus::UNAVAILABLE->value], true)
        ));

        if (ExamType::isExamType((string)$schedule->type)) {
            $this->hideObservers($schedule);
            // Kural 3: gözetmen yerine dersin hocası
            (new ExamScheduleService())->attachLessonLecturerNames($schedule->items, (string)$schedule->semester, (string)$schedule->academic_year);
        }
        return $schedule;
    }

    /**
     * Dışa aktarılacak programı misafir kurallarına göre hazırlar (kural 2; kaydedilmez).
     *
     * Dersin hocası burada eklenmez: dışa aktarıcılar detail['lesson_lecturer'] alanını
     * gözetmenlik kaydı biçimlendirmesi için kullanır ve hocayı kendileri çözümler.
     */
    public function forExport(Schedule $schedule): Schedule
    {
        if (self::isGuest() && ExamType::isExamType((string)$schedule->type)) {
            $this->hideObservers($schedule);
        }
        return $schedule;
    }

    /**
     * Kural 4: Misafir sınav çıktısında dışa aktarma seçeneklerini ezen değerler.
     *
     * @return array<string,bool>
     */
    public static function exportOptionOverrides(?string $scheduleType): array
    {
        if (!self::isGuest() || !ExamType::isExamType((string)$scheduleType)) {
            return [];
        }
        return ['show_observer' => false, 'show_program' => false];
    }

    /**
     * Kural 5: Hoca sınav programında gösterilebilecek ders sahipli sınav programlarını süzer.
     *
     * Görüntüleme yetkisi olan programlar doğrudan kabul edilir. Ders programları yayınlanmadığı
     * için (bkz. SchedulePublishService) yetkisi olmayanlarda dersin program/yarıyıl programının
     * yayın durumuna bakılır.
     *
     * @param Schedule[] $lessonSchedules owner_type=lesson sınav programları
     * @return Schedule[]
     * @throws Exception
     */
    public function filterVisibleLessonSchedules(array $lessonSchedules): array
    {
        $visible = [];
        $restricted = [];
        foreach ($lessonSchedules as $lessonSchedule) {
            if (Gate::check(PermissionType::VIEW->value, $lessonSchedule)) {
                $visible[] = $lessonSchedule;
            } else {
                $restricted[] = $lessonSchedule;
            }
        }
        if (empty($restricted)) {
            return $visible;
        }

        $lessonIds = array_values(array_unique(array_map(fn(Schedule $s) => (int)$s->owner_id, $restricted)));
        $lessons = [];
        foreach ((new Lesson())->get()->where(['id' => ['in' => $lessonIds]])->all() as $lesson) {
            $lessons[(int)$lesson->id] = $lesson;
        }

        $scheduleRepository = new ScheduleRepository();
        $publishedPrograms = [];
        foreach ($restricted as $lessonSchedule) {
            $lesson = $lessons[(int)$lessonSchedule->owner_id] ?? null;
            if (!$lesson || empty($lesson->program_id)) {
                continue;
            }
            $programKey = $lesson->program_id . '|' . (int)$lesson->semester_no;
            $publishedPrograms[$programKey] ??= (bool)$scheduleRepository->findByOwnerAndPeriod(
                OwnerType::PROGRAM->value,
                (int)$lesson->program_id,
                (string)$lessonSchedule->academic_year,
                (string)$lessonSchedule->semester,
                (string)$lessonSchedule->type,
                (int)$lesson->semester_no
            )?->is_published;
            if ($publishedPrograms[$programKey]) {
                $visible[] = $lessonSchedule;
            }
        }
        return $visible;
    }

    /**
     * Kural 2: salon atamalarındaki gözetmen isimlerini ve derslik kaydındaki gözetmeni çıkarır.
     * Hocanın kendi gözetmenlik görevleri (exam_assignment) korunur.
     */
    private function hideObservers(Schedule $schedule): void
    {
        $isLecturerSchedule = $schedule->owner_type === OwnerType::USER->value;
        foreach ($schedule->items ?? [] as $item) {
            $detail = $item->detail ?? [];
            unset($detail['observers']);
            if (isset($detail['assignments']) && is_array($detail['assignments'])) {
                foreach ($detail['assignments'] as $index => $assignment) {
                    unset($assignment['observers'], $assignment['observer_id'], $assignment['observer_name']);
                    $detail['assignments'][$index] = $assignment;
                }
            }
            $item->detail = $detail;

            // Derslik sınav kaydında lecturer_id gözetmeni tutar; hoca programında ise programın sahibidir
            if (!$isLecturerSchedule) {
                $data = $item->data ?? [];
                foreach ($data as $index => $dayData) {
                    $data[$index]['lecturer_id'] = null;
                }
                $item->data = $data;
            }
        }
    }
}
