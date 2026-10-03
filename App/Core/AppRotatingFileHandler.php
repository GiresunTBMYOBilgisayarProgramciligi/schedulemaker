<?php

namespace App\Core;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use DateTimeImmutable;

/**
 * Genişletilmiş Rotasyonlu Dosya İşleyicisi.
 * Monolog'un varsayılan günlük (Y-m-d), aylık (Y-m) ve yıllık (Y) rotasyonlarına ek olarak
 * ISO-8601 standartlarında haftalık (Y-Www, örn: 2026-W40) rotasyon desteği sağlar.
 */
class AppRotatingFileHandler extends RotatingFileHandler
{
    public const FILE_PER_WEEK = 'Y-\WW';

    public function __construct(
        string $filename,
        int $maxFiles = 0,
        int|string|Level $level = Level::Debug,
        bool $bubble = true,
        ?int $filePermission = null,
        bool $useLocking = false,
        string $dateFormat = 'Y-m-d',
        string $filenameFormat = '{filename}-{date}',
        ?\DateTimeZone $timezone = null
    ) {
        parent::__construct(
            $filename,
            $maxFiles,
            $level,
            $bubble,
            $filePermission,
            $useLocking,
            $dateFormat,
            $filenameFormat,
            $timezone
        );
    }

    /**
     * @param string $dateFormat
     * @return void
     */
    protected function setDateFormat(string $dateFormat): void
    {
        if ($dateFormat === self::FILE_PER_WEEK || preg_match('{^[Yy](([/_.-]?m)([/_.-]?d)?)?$}', $dateFormat)) {
            $this->dateFormat = $dateFormat;
            return;
        }

        parent::setDateFormat($dateFormat);
    }

    /**
     * @return DateTimeImmutable
     */
    protected function getNextRotation(): DateTimeImmutable
    {
        if ($this->dateFormat === self::FILE_PER_WEEK) {
            return (new DateTimeImmutable('next monday', $this->timezone))->setTime(0, 0, 0);
        }

        return parent::getNextRotation();
    }

    /**
     * @return string
     */
    protected function getGlobPattern(): string
    {
        if ($this->dateFormat === self::FILE_PER_WEEK) {
            $fileInfo = pathinfo($this->filename);
            $glob = str_replace(
                ['{filename}', '{date}'],
                [$fileInfo['filename'], '[0-9][0-9][0-9][0-9]-W[0-9][0-9]'],
                ($fileInfo['dirname'] ?? '') . '/' . $this->filenameFormat
            );
            if (isset($fileInfo['extension'])) {
                $glob .= '.' . $fileInfo['extension'];
            }
            return $glob;
        }

        return parent::getGlobPattern();
    }
}
