<?php

namespace App\Services\Export;

/**
 * Tüm program dışa aktarma (Excel, ICS) sınıfları bu arayüzü uygular.
 */
interface ScheduleExporterInterface
{
    /**
     * @param array $filters    Doğrulanmış filtre dizisi (type, owner_type, owner_id, semester, academic_year vb.)
     * @param array $showOptions Gösterim seçenekleri (show_code, show_lecturer, show_program, show_observer)
     */
    public function export(array $filters, array $showOptions): void;
}
