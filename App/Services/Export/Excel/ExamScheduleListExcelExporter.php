<?php

declare(strict_types=1);

namespace App\Services\Export\Excel;

use App\DTOs\ScheduleExportFilterDTO;
use App\Enums\ExamType;

/**
 * Sınav programlarını (Ara Sınav / Final / Bütünleme) satır bazlı liste formatında
 * Excel olarak dışa aktarır.
 *
 * Tablo çizimi, sütunlar ve veri formatı LessonScheduleListExcelExporter ile aynıdır;
 * sadece başlık etiketi ve dosya başlığı sınav türüne göre özelleşir.
 */
class ExamScheduleListExcelExporter extends LessonScheduleListExcelExporter
{
    protected string $defaultFileTitle = 'Sınav Programı';

    /**
     * Sınav türüne göre başlık etiketini belirler.
     */
    protected function resolvePeriodLabel(ScheduleExportFilterDTO $filters): string
    {
        $type = $filters->type ?? 'exam';

        return match ($type) {
            ExamType::MIDTERM->value => 'ARA SINAV PROGRAMI (LİSTE)',
            ExamType::FINAL->value   => 'FİNAL SINAV PROGRAMI (LİSTE)',
            ExamType::MAKEUP->value  => 'BÜTÜNLEME SINAV PROGRAMI (LİSTE)',
            default                  => 'SINAV PROGRAMI (LİSTE)',
        };
    }
}
