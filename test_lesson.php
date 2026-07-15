<?php
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
require_once __DIR__ . '/App/Helpers/Helper.php';
$lesson = (new \App\Models\Lesson())->get()->where(['id' => 522])->with(['childLessons' => ['with'=>['program'], 'semester'=>'Bahar', 'academic_year'=>'2025-2026']])->first();
print_r(count($lesson->childLessons));
echo "\n";
print_r($lesson->childLessons[0]->program->name ?? "no program");
echo "\n";
