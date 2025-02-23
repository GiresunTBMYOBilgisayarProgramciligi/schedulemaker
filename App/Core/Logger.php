<?php

namespace App\Core;

use Exception;

/**
 * Monolog kütüphanesini kullanarak yapılacak olan loglama işlemlerinin düzenlendiği sınıf
 */
class Logger
{
    /**
     *
     */
    public function __construct()
    {

    }

    public static function getCallerInfo()
    {
        // debug_backtrace() ile çağrı yığınını alıyoruz
        $trace = debug_backtrace();

        // [0] mevcut method (getCallerInfo)
        // [1] static method'un kendisi
        // [2] static method'u çağıran yer
        $caller = isset($trace[2]) ? $trace[2] : $trace[1];

        return [
            'file' => $caller['file'] ?? 'unknown',
            'line' => $caller['line'] ?? 'unknown',
            'class' => $caller['class'] ?? 'unknown',
            'method' => $caller['function'] ?? 'unknown',
            'args' => $caller['args'] ?? []
        ];
    }

    public static function setExceptionLog(Exception $exception): void
    {
        $info = self::getCallerInfo();

        $_SESSION["errors"][] = [
            "caller" => [
                "file" => $info['file'],
                "line" => $info['line'],
                "class" => $info['class'],
                "method" => $info['method']
            ],
            "exception" => [
                "details" => [
                    "message" => $exception->getMessage(),
                    "code" => $exception->getCode(),
                    "line" => $exception->getLine(),
                    "file" => $exception->getFile(),
                    "trace" => $exception->getTraceAsString()
                ]
            ]
        ];
        //todo diğer loglama işlemleri yapılacak
    }

    public static function setErrorLog($errorMessage): void
    {
        $info = self::getCallerInfo();

        $_SESSION["errors"][] = sprintf(
            "Message: %s\nCalled from: %s on line %d\nMethod: %s::%s()\n",
            $errorMessage,
            $info['file'],
            $info['line'],
            $info['class'],
            $info['method']
        );
        //todo diğer loglama işlemleri yapılacak
    }

    public static function setAndShowErrorLog($errorMessage): void
    {
        self::setErrorLog($errorMessage);
        /**
         * @see App/Views/admin/theme.php
         */
        $_SESSION["error"][] = $errorMessage;
        //todo diğer loglama işlemleri yapılacak
    }
}