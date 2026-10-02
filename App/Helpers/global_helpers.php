<?php

/**
 * Global Helper Functions (Root Namespace \)
 *
 * Bu dosya, View katmanında ve genel uygulama kodlarında `use function`
 * zorunluluğu olmadan kullanılabilen çekirdek yardımcı fonksiyonları tanımlar.
 */

if (!function_exists('e')) {
    /**
     * HTML karakterlerini güvenli şekilde kaçırır (XSS koruması).
     *
     * @param mixed $value
     * @param bool $doubleEncode
     * @return string
     */
    function e(mixed $value, bool $doubleEncode = true): string
    {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', $doubleEncode);
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Aktif CSRF belirtecini (token) döner.
     *
     * @return string
     */
    function csrf_token(): string
    {
        return \App\Core\Csrf::getToken();
    }
}

if (!function_exists('csrf_field')) {
    /**
     * HTML formları için gizli CSRF belirteç girdi alanını (<input>) üretir.
     *
     * @return string
     */
    function csrf_field(): string
    {
        return \App\Core\Csrf::field();
    }
}

if (!function_exists('getSettingValue')) {
    /**
     * Sistem ayar değerini döner.
     */
    function getSettingValue($key = null, $group = "general", $default = null)
    {
        return \App\Helpers\getSettingValue($key, $group, $default);
    }
}

if (!function_exists('formatLessonName')) {
    /**
     * Ders adını kurallara uygun şekilde biçimlendirir.
     */
    function formatLessonName(?string $name): string
    {
        return \App\Helpers\formatLessonName($name);
    }
}

if (!function_exists('hasRole')) {
    /**
     * Kullanıcının belirtilen role sahip olup olmadığını kontrol eder.
     */
    function hasRole(string|\App\Enums\UserRole $role, ?\App\Models\User $user = null, bool $reverse = false): bool
    {
        return \App\Helpers\hasRole($role, $user, $reverse);
    }
}

if (!function_exists('getSemesterNumbers')) {
    /**
     * Dönem numaralarını liste olarak döner.
     */
    function getSemesterNumbers(?string $semester = null, ?int $maxSemester = null, bool $includeInternshipSemesters = false): array
    {
        return \App\Helpers\getSemesterNumbers($semester, $maxSemester, $includeInternshipSemesters);
    }
}

if (!function_exists('getClassFromSemesterNo')) {
    /**
     * Yarıyıl numarasından sınıf bilgisini hesaplar.
     */
    function getClassFromSemesterNo($semesterNo): string
    {
        return \App\Helpers\getClassFromSemesterNo($semesterNo);
    }
}

if (!function_exists('getSuggestedSemesterNo')) {
    /**
     * Önerilen yarıyıl numarasını hesaplar.
     */
    function getSuggestedSemesterNo(int $semesterNo, string $semester, int $totalSemesters = 4): int
    {
        return \App\Helpers\getSuggestedSemesterNo($semesterNo, $semester, $totalSemesters);
    }
}

if (!function_exists('getMaxSemesterNo')) {
    /**
     * İlgili program/bölüm/birim için maksimum yarıyıl numarasını hesaplar.
     */
    function getMaxSemesterNo(?int $programId = null, ?int $departmentId = null, ?int $unitId = null): int
    {
        return \App\Helpers\getMaxSemesterNo($programId, $departmentId, $unitId);
    }
}

if (!function_exists('getSemesterSelectOptions')) {
    /**
     * Yarıyıl seçim seçeneklerini dizi olarak döner.
     */
    function getSemesterSelectOptions(?string $semester = null, ?int $maxSemester = null, bool $includeInternshipSemesters = false): array
    {
        return \App\Helpers\getSemesterSelectOptions($semester, $maxSemester, $includeInternshipSemesters);
    }
}

if (!function_exists('getAppVersion')) {
    /**
     * Uygulama sürüm numarasını döner.
     */
    function getAppVersion(): string
    {
        return \App\Helpers\getAppVersion();
    }
}

if (!function_exists('getCurrentYearAndSemester')) {
    /**
     * Aktif akademik yıl ve dönemi döner.
     */
    function getCurrentYearAndSemester(): bool|string
    {
        return \App\Helpers\getCurrentYearAndSemester();
    }
}

if (!function_exists('renderBuildingSelectOptions')) {
    function renderBuildingSelectOptions(
        array $buildings,
        ?int $selectedBuildingId = null,
        ?bool $hasMultipleUnits = null,
        string $placeholder = 'Bina Seçiniz (Opsiyonel)'
    ): string {
        return \App\Helpers\renderBuildingSelectOptions($buildings, $selectedBuildingId, $hasMultipleUnits, $placeholder);
    }
}

if (!function_exists('renderProgramSelectOptions')) {
    function renderProgramSelectOptions(
        array $programs,
        ?int $selectedProgramId = null,
        string $placeholder = 'Program Seçiniz...'
    ): string {
        return \App\Helpers\renderProgramSelectOptions($programs, $selectedProgramId, $placeholder);
    }
}

if (!function_exists('renderLecturerSelectOptions')) {
    function renderLecturerSelectOptions(
        array $lecturers,
        ?int $selectedLecturerId = null,
        ?bool $hasMultipleUnits = null,
        string $placeholder = 'Öğretim Görevlisi Seçiniz (Opsiyonel)'
    ): string {
        return \App\Helpers\renderLecturerSelectOptions($lecturers, $selectedLecturerId, $hasMultipleUnits, $placeholder);
    }
}

if (!function_exists('renderDepartmentSelectOptions')) {
    function renderDepartmentSelectOptions(
        array $departments,
        ?int $selectedDepartmentId = null,
        string $placeholder = 'Bölüm Seçiniz...'
    ): string {
        return \App\Helpers\renderDepartmentSelectOptions($departments, $selectedDepartmentId, $placeholder);
    }
}

