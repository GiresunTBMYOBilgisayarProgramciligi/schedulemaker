<?php

namespace App\Mailers;

use App\Core\Mailer;
use App\Core\View;
use App\Models\Department;
use App\Models\Lesson;
use App\Models\LessonAssignment;
use App\Models\Program;
use App\Models\Schedule;
use App\Models\Unit;
use App\Models\User;
use Exception;

class ScheduleMailer extends Mailer
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param User $lecturer
     * @param array $changes
     * @return bool
     */
    public function sendScheduleChangesNotification(User $lecturer, array $changes): bool
    {
        try {
            if (empty($lecturer->mail)) {
                return false;
            }

            $this->resetMailerState();
            $this->mailer->addAddress($lecturer->mail, $lecturer->getFullName());
            $this->mailer->Subject = 'Ders/Sınav Programınızda Değişiklik Yapıldı';

            $body = View::renderEmail('schedule_changes', [
                'lecturer' => $lecturer,
                'changes'  => $changes,
                'appUrl'   => $this->getAppUrl()
            ]);

            $this->mailer->Body = $body;
            $this->mailer->AltBody = strip_tags(str_replace(['<br>', '</li>', '</p>'], "\n", $body));

            return $this->send();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param User $lecturer
     * @param Schedule $schedule
     * @param string $excelContent
     * @param string $excelFileName
     * @param string $icsContent
     * @param string $icsFileName
     * @param array $scopeInfo
     * @return bool
     */
    public function sendSchedulePublishedNotification(
        User $lecturer,
        Schedule $schedule,
        string $excelContent,
        string $excelFileName,
        string $icsContent,
        string $icsFileName,
        array $scopeInfo = []
    ): bool {
        try {
            if (empty($lecturer->mail)) {
                return false;
            }

            $this->resetMailerState();
            $this->mailer->addAddress($lecturer->mail, $lecturer->getFullName());
            $academicYear = htmlspecialchars($schedule->academic_year ?? '');
            $semester     = htmlspecialchars($schedule->semester ?? '');
            $typeLabel    = $schedule->getScheduleTypeName();

            if (empty($scopeInfo)) {
                $scopeInfo = $this->resolveUserScopeInfo($lecturer, $schedule);
            }

            $unitPrefix = !empty($scopeInfo['unitName']) ? "{$scopeInfo['unitName']} " : "";
            $this->mailer->Subject = "{$academicYear} {$semester} Dönemi {$unitPrefix}{$typeLabel} Programınız Yayınlandı";

            // Excel Eki
            if (!empty($excelContent)) {
                $this->mailer->addStringAttachment(
                    $excelContent,
                    $excelFileName,
                    'base64',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                );
            }

            // ICS Eki
            if (!empty($icsContent)) {
                $this->mailer->addStringAttachment(
                    $icsContent,
                    $icsFileName,
                    'base64',
                    'text/calendar; charset=utf-8'
                );
            }

            $body = View::renderEmail('schedule_published', [
                'lecturer'       => $lecturer,
                'schedule'       => $schedule,
                'unitName'       => $scopeInfo['unitName'] ?? null,
                'departmentName' => $scopeInfo['departmentName'] ?? null,
                'programName'    => $scopeInfo['programName'] ?? null,
                'appUrl'         => $this->getAppUrl()
            ]);

            $this->mailer->Body = $body;
            $this->mailer->AltBody = strip_tags(str_replace(['<br>', '</li>', '</p>', '</tr>'], "\n", $body));

            return $this->send();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param User $lecturer
     * @param string $unitName
     * @param string $scheduleType
     * @param string $semester
     * @param string $academicYear
     * @param string|null $departmentName
     * @param string|null $programName
     * @param array $lessonNames
     * @return bool
     */
    public function sendCrossUnitNotification(
        User $lecturer,
        string $unitName,
        string $scheduleType,
        string $semester,
        string $academicYear,
        ?string $departmentName = null,
        ?string $programName = null,
        array $lessonNames = []
    ): bool {
        try {
            if (empty($lecturer->mail)) {
                return false;
            }

            $this->resetMailerState();
            $this->mailer->addAddress($lecturer->mail, $lecturer->getFullName());
            $this->mailer->Subject = "{$academicYear} {$semester} Dönemi {$unitName} {$scheduleType} Yayınlandı";

            // Hocanın asıl kadro/bağlılık adını bul (users tablosu öncelikli)
            $ownAffNames = [];
            if ($lecturer->program_id) {
                $p = (new Program())->find($lecturer->program_id);
                if ($p) $ownAffNames[] = $p->name . " Programı";
            } elseif ($lecturer->department_id) {
                $d = (new Department())->find($lecturer->department_id);
                if ($d) $ownAffNames[] = $d->name . " Bölümü";
            } elseif ($lecturer->unit_id) {
                $u = (new Unit())->find($lecturer->unit_id);
                if ($u) $ownAffNames[] = $u->name;
            }
            $ownAffiliationName = !empty($ownAffNames) ? implode(', ', array_unique($ownAffNames)) : null;

            $body = View::renderEmail('schedule_cross_unit_published', [
                'lecturer'           => $lecturer,
                'unitName'           => $unitName,
                'scheduleType'       => $scheduleType,
                'semester'           => $semester,
                'academicYear'       => $academicYear,
                'departmentName'     => $departmentName,
                'programName'        => $programName,
                'lessonNames'        => $lessonNames,
                'ownAffiliationName' => $ownAffiliationName,
                'appUrl'             => $this->getAppUrl()
            ]);

            $this->mailer->Body = $body;
            $this->mailer->AltBody = strip_tags(str_replace(['<br>', '</li>', '</p>'], "\n", $body));

            return $this->send();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Hoca ve program verisinden Birim, Bölüm ve Program isimlerini çözümler
     */
    public function resolveUserScopeInfo(User $lecturer, Schedule $schedule): array
    {
        $units = [];
        $departments = [];
        $programs = [];

        // 1. Bu dönem hocanın DERS VERDİĞİ programları tespit et (Öncelikli)
        $assignments = (new LessonAssignment())->get()->where([
            'lecturer_id'   => $lecturer->id,
            'semester'      => $schedule->semester,
            'academic_year' => $schedule->academic_year
        ])->all();

        if (!empty($assignments)) {
            $lessonIds = array_unique(array_filter(array_map(fn($a) => $a->lesson_id, $assignments)));
            if (!empty($lessonIds)) {
                $lessons = (new Lesson())->get()->where(['id' => ['in' => $lessonIds]])->all();
                foreach ($lessons as $lesson) {
                    if ($lesson->program_id && !isset($programs[$lesson->program_id])) {
                        $prog = (new Program())->find($lesson->program_id);
                        if ($prog) {
                            $programs[$prog->id] = $prog->name;
                            if ($prog->department_id && !isset($departments[$prog->department_id])) {
                                $dept = (new Department())->find($prog->department_id);
                                if ($dept) {
                                    $departments[$dept->id] = $dept->name;
                                    if ($dept->unit_id && !isset($units[$dept->unit_id])) {
                                        $u = (new Unit())->find($dept->unit_id);
                                        if ($u) $units[$u->id] = $u->name;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // 2. Eğer bu dönem hocaya atanmış aktif ders yoksa asıl kadro bilgilerini kullan
        if (empty($programs) && $lecturer->program_id) {
            $p = (new Program())->find($lecturer->program_id);
            if ($p) $programs[$p->id] = $p->name;
        }
        if (empty($departments) && $lecturer->department_id) {
            $d = (new Department())->find($lecturer->department_id);
            if ($d) $departments[$d->id] = $d->name;
        }
        if (empty($units) && $lecturer->unit_id) {
            $u = (new Unit())->find($lecturer->unit_id);
            if ($u) $units[$u->id] = $u->name;
        }

        return [
            'unitName'       => !empty($units) ? implode(', ', array_unique($units)) : null,
            'departmentName' => !empty($departments) ? implode(', ', array_unique($departments)) : null,
            'programName'    => !empty($programs) ? implode(', ', array_unique($programs)) : null,
        ];
    }

    private function getAppUrl(): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . "://" . $domain;
    }
}
