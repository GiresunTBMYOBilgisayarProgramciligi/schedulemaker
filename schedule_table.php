<?php
$schedule=[
	0=> [
		'type' => 'lesson',
		'owner_type' => 'user',
		'owner_id' => 1,
		'academic_year' => '2025 - 2026',
		'semester' => 'Güz',
		'semester_no' => 1,
	],
	1=> [
		'type' => 'lesson',
		'owner_type' => 'lesson',
		'owner_id' => 1,
		'academic_year' => '2025 - 2026',
		'semester' => 'Güz',
		'semester_no' => 1,
	],
	2=> [
		'type' => 'lesson',
		'owner_type' => 'program',
		'owner_id' => 1,
		'academic_year' => '2025 - 2026',
		'semester' => 'Güz',
		'semester_no' => 1,
	],
	3=> [
		'type' => 'lesson',
		'owner_type' => 'classroom',
		'owner_id' => 1,
		'academic_year' => '2025 - 2026',
		'semester' => 'Güz',
		'semester_no' => 1,
	],
	4=> [
		'type' => 'midterm-exam',
		'owner_type' => 'user',
		'owner_id' => 1,
		'academic_year' => '2025 - 2026',
		'semester' => 'Güz',
		'semester_no' => 1,
	],
	5=> [
		'type' => 'final-exam',
		'owner_type' => 'user',
		'owner_id' => 1,
		'academic_year' => '2025 - 2026',
		'semester' => 'Güz',
		'semester_no' => 1,
	],
	6=> [
		'type' => 'makeup-exam',
		'academic_year' => '2025 - 2026',
		'semester' => 'Güz',
		'semester_no' => 1,
	],
];
$schedule_items =
	[
		'schedule_id' => 0,
		'day_index' => 0,
		'week_index' => 0,
		'start_time' => '08.00',
		'end_time' => '08.50',
		'status' => 'group', // enabe,disable,group,single
		'data' => [
			['lesson_id' => 1, 'lecturer_id' => 1, 'classroom_id' => 1],
			['lesson_id' => 2, 'lecturer_id' => 2, 'classroom_id' => 2]
		],
		'detail' => null,// hoca tercihleri yada diğer durumlar için açıklama mesajları vs. 
	];