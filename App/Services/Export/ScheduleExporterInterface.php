<?php

namespace App\Services\Export;

use App\DTOs\ScheduleExportFilterDTO;
use App\DTOs\ScheduleExportOptionsDTO;

/**
 * Tüm program dışa aktarma (Excel, ICS) sınıfları bu arayüzü uygular.
 */
interface ScheduleExporterInterface
{
    /**
     * @param ScheduleExportFilterDTO|array $filters Doğrulanmış filtre DTO veya dizisi
     * @param ScheduleExportOptionsDTO|array $showOptions Gösterim seçenekleri
     */
    public function export(ScheduleExportFilterDTO|array $filters, ScheduleExportOptionsDTO|array $showOptions = []): void;

    /**
     * Dışa aktarılacak dosyanın adını üretir.
     *
     * @param ScheduleExportFilterDTO|array $filters
     * @return string
     */
    public function getFileName(ScheduleExportFilterDTO|array $filters): string;

    /**
     * Dışa aktarılan dosyanın ham içeriğini (binary Excel veya ICS metni) string olarak döner.
     *
     * @param ScheduleExportFilterDTO|array $filters
     * @param ScheduleExportOptionsDTO|array $showOptions
     * @return string
     */
    public function getRawContent(ScheduleExportFilterDTO|array $filters, ScheduleExportOptionsDTO|array $showOptions = []): string;
}
