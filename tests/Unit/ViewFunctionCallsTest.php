<?php

namespace Tests\Unit;

use Tests\BaseTestCase;

class ViewFunctionCallsTest extends BaseTestCase
{
    /**
     * Tüm View dosyalarında çağrılan global fonksiyonların tanımlı olduğunu doğrular.
     */
    public function testAllFunctionsCalledInViewsExist(): void
    {
        $viewsPath = realpath(__DIR__ . '/../../App/Views');
        $this->assertNotFalse($viewsPath, 'Views dizini bulunamadı');

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($viewsPath));
        $definedUserFunctions = get_defined_functions()['user'];
        $definedInternalFunctions = get_defined_functions()['internal'];
        $allFunctions = array_flip(array_merge($definedUserFunctions, $definedInternalFunctions));

        $missing = [];
        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $content = file_get_contents($file->getRealPath());
            $tokens = token_get_all($content);
            $count = count($tokens);
            for ($i = 0; $i < $count; $i++) {
                if ($tokens[$i][0] === T_STRING) {
                    $fn = $tokens[$i][1];
                    $j = $i + 1;
                    while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                        $j++;
                    }
                    if ($j < $count && $tokens[$j] === '(') {
                        $k = $i - 1;
                        while ($k >= 0 && is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) {
                            $k--;
                        }
                        if ($k >= 0 && is_array($tokens[$k]) && in_array($tokens[$k][0], [
                            T_OBJECT_OPERATOR,
                            T_NULLSAFE_OBJECT_OPERATOR,
                            T_DOUBLE_COLON,
                            T_FUNCTION,
                            T_NEW,
                            T_CONST,
                            T_CLASS,
                            T_INTERFACE,
                            T_TRAIT,
                            T_EXTENDS,
                            T_IMPLEMENTS
                        ])) {
                            continue;
                        }
                        if (in_array(strtolower($fn), [
                            'isset', 'empty', 'unset', 'list', 'array', 'echo', 'print',
                            'include', 'include_once', 'require', 'require_once', 'eval', 'exit', 'die'
                        ])) {
                            continue;
                        }
                        $fnLower = strtolower($fn);
                        if (!isset($allFunctions[$fnLower])) {
                            $missing[$fn][] = str_replace($viewsPath . '/', '', $file->getRealPath());
                        }
                    }
                }
            }
        }

        $errorMessages = [];
        foreach ($missing as $fn => $filesList) {
            $filesList = array_unique($filesList);
            $errorMessages[] = "Tanımsız fonksiyon '{$fn}()': " . implode(', ', array_slice($filesList, 0, 3));
        }

        $this->assertEmpty($missing, "View dosyalarında tanımsız fonksiyon çağrıları tespit edildi:\n" . implode("\n", $errorMessages));
    }
}
