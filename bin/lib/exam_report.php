<?php

/**
 * Sınav programı rapor betiklerinin (bin/exam_*.php) ortak iskeleti.
 *
 * Seçenekleri okur, dönem/yıl/sınav tipini ExamAnalysisFilterValidator ile doğrulayıp varsayılanları atar,
 * raporu çalıştırır ve text ya da json olarak yazdırır. Çıkış kodu: sorun bulunursa 1, bulunmazsa 0, hata olursa 2.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Bu betik yalnızca CLI (komut satırı) üzerinden çalıştırılabilir.\n");
}

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\DTOs\ExamAnalysisFilterDTO;
use App\Exceptions\ValidationException;
use App\Validators\Schedule\ExamAnalysisFilterValidator;
use Dotenv\Dotenv;

if (file_exists(dirname(__DIR__, 2) . '/App/.env')) {
    Dotenv::createImmutable(dirname(__DIR__, 2) . '/App')->safeLoad();
}

/**
 * Raporu çalıştırır ve betiği sonlandırır.
 *
 * @param string $scriptFile Yardım metni olarak yazdırılacak başlık yorumunun bulunduğu betik (__FILE__)
 * @param string[] $extraOptions Betiğe özgü getopt seçenekleri (örn. 'max::')
 * @param callable(ExamAnalysisFilterDTO, ?int[], array): array $analyze Filtre, program kimlikleri ve seçeneklerle sonucu üretir
 * @param callable(array, ExamAnalysisFilterDTO): void $renderText Sonucu metin olarak yazdırır
 * @param string $problemCountKey Sonuç dizisindeki sorun sayısı anahtarı (çıkış kodunu belirler)
 */
function runExamReport(string $scriptFile, array $extraOptions, callable $analyze, callable $renderText, string $problemCountKey): never
{
    $options = getopt('', array_merge(['type::', 'semester::', 'year::', 'program::', 'format::', 'help'], $extraOptions));
    if (isset($options['help'])) {
        preg_match('#/\*\*(.*?)\*/#s', file_get_contents($scriptFile), $header);
        echo trim(preg_replace('/^\s*\* ?/m', '', $header[1])) . "\n";
        exit(0);
    }

    try {
        $format = $options['format'] ?? 'text';
        if (!in_array($format, ['text', 'json'], true)) {
            throw new InvalidArgumentException("Geçersiz format: {$format} (text veya json olmalı)");
        }

        $filter = (new ExamAnalysisFilterValidator())->getDTO([
            'exam_type' => $options['type'] ?? null,
            'semester' => $options['semester'] ?? null,
            'academic_year' => $options['year'] ?? null,
        ]);
        $programIds = isset($options['program']) ? [(int) $options['program']] : null;

        $result = $analyze($filter, $programIds, $options);

        if ($format === 'json') {
            echo json_encode(
                ['type' => $filter->exam_type, 'semester' => $filter->semester, 'academic_year' => $filter->academic_year] + $result,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ) . "\n";
        } else {
            $renderText($result, $filter);
        }

        exit($result[$problemCountKey] > 0 ? 1 : 0);
    } catch (ValidationException $e) {
        fwrite(STDERR, 'HATA: ' . implode('; ', $e->getValidationErrors()) . "\n");
        exit(2);
    } catch (\Throwable $e) {
        fwrite(STDERR, 'HATA: ' . $e->getMessage() . "\n");
        exit(2);
    }
}
