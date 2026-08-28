<?php

namespace Tests\Unit;

use Tests\BaseTestCase;

class ViewSyntaxTest extends BaseTestCase
{
    /**
     * Tüm View dosyalarının geçerli PHP sözdizimine sahip olduğunu doğrular.
     */
    public function testAllViewFilesHaveValidSyntax(): void
    {
        $viewsPath = realpath(__DIR__ . '/../../App/Views');
        $this->assertNotFalse($viewsPath, 'Views dizini bulunamadı');

        $directory = new \RecursiveDirectoryIterator($viewsPath);
        $iterator = new \RecursiveIteratorIterator($directory);
        $phpFiles = new \RegexIterator($iterator, '/^.+\.php$/i', \RecursiveRegexIterator::GET_MATCH);

        $errors = [];

        foreach ($phpFiles as $file) {
            $filePath = $file[0];
            $output = [];
            $returnVar = 0;
            exec("php -l " . escapeshellarg($filePath) . " 2>&1", $output, $returnVar);

            if ($returnVar !== 0) {
                $errors[] = implode("\n", $output);
            }
        }

        $this->assertEmpty($errors, "Aşağıdaki View dosyalarında sözdizimi (syntax) hatası bulundu:\n" . implode("\n", $errors));
    }
}
