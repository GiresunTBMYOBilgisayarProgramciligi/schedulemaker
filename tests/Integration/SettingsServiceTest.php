<?php

namespace Tests\Integration;

use Tests\BaseTestCase;
use App\Services\SettingsService;
use App\DTOs\SettingDTO;
use App\Models\Setting;

class SettingsServiceTest extends BaseTestCase
{
    private SettingsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SettingsService();
    }

    public function testSaveMultipleSettingsUpsert(): void
    {
        $dto1 = new SettingDTO(group: 'test_group', key: 'academic_year', value: '2026-2027', type: 'string');
        $dto2 = new SettingDTO(group: 'test_group', key: 'is_maintenance', value: '1', type: 'boolean');

        $result = $this->service->saveMultipleSettings([$dto1, $dto2]);
        $this->assertTrue($result);

        $setting1 = (new Setting())->get()->where(['group' => 'test_group', 'key' => 'academic_year'])->first();
        $this->assertNotNull($setting1);
        $this->assertEquals('2026-2027', $setting1->value);

        // Şimdi güncelleme (upsert) testi
        $dtoUpdated = new SettingDTO(group: 'test_group', key: 'academic_year', value: '2027-2028', type: 'string');
        $this->service->saveMultipleSettings([$dtoUpdated]);

        $settingUpdated = (new Setting())->get()->where(['group' => 'test_group', 'key' => 'academic_year'])->first();
        $this->assertEquals('2027-2028', $settingUpdated->value);
    }
}
