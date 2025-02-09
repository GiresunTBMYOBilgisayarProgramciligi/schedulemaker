<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Setting;
use Exception;

class SettingsController extends Controller
{
    protected string $table_name = "settings";
    protected string $modelName = "App\Models\Setting";

    /**
     * @param $key
     * @param $group
     * @return Setting|string
     * @throws Exception
     */
    public function getSetting($key = null, $group = "general")
    {
        try {
            if (is_null($key)) {
                throw new Exception("Ayar için anahtar girilmelidir");
            }
            $whereClause = "";
            $parameters = [];
            $this->prepareWhereClause(["key" => $key, "group" => $group], $whereClause, $parameters);
            $stmt = $this->database->prepare("SELECT * FROM $this->table_name $whereClause");
            $stmt->execute($parameters);
            $settingsData = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($settingsData) {
                $setting = new Setting();
                $setting->fill($settingsData);
                return $setting;
            } else throw new Exception("Ayar Bulunamadı");
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param Setting $setting
     * @return int
     * @throws Exception
     */
    public function saveNew(Setting $setting): int
    {
        try {
            $newSettingData = $setting->getArray(['table_name', "database", "id"]);

            $sql = $this->createInsertSQL($newSettingData);
            $stmt = $this->database->prepare($sql);
            $stmt->execute($newSettingData);
            return $this->database->lastInsertId();
        } catch (Exception $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                $existingSetting = $this->getSetting($setting->key, $setting->group);
                $setting->id = $existingSetting->id;
                return $this->updateSetting($setting);
            } else {
                throw new Exception($e->getMessage(), $e->getCode(), $e);
            }
        }
    }

    /**
     * @param Setting $setting
     * @return int
     * @throws Exception
     */
    public function updateSetting(Setting $setting): int
    {
        try {
            $settingData = $setting->getArray(['table_name', "database", "id"]);
            // Sorgu ve parametreler için ayarlamalar
            $columns = [];
            $parameters = [];

            foreach ($settingData as $key => $value) {
                $columns[] = "`$key` = :$key";
                $parameters[$key] = $value; // NULL dahil tüm değerler parametre olarak ekleniyor
            }

            // WHERE koşulu için ID ekleniyor
            $parameters["id"] = $setting->id;

            // Dinamik SQL sorgusu oluştur
            $query = sprintf(
                "UPDATE %s SET %s WHERE id = :id",
                $this->table_name,
                implode(", ", $columns)
            );
            // Sorguyu hazırla ve çalıştır
            $stmt = $this->database->prepare($query);
            $stmt->execute($parameters);
            return $setting->id;
        } catch (Exception $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                throw new Exception("Bu ayar başka bir ayarla çakışıyor. Farkı bir anahtar ve grup belirleyin", $e->getCode(), $e);
            } else {
                throw new Exception($e->getMessage(), $e->getCode(), $e);
            }
        }
    }

    /**
     * Tüm ayarları [group][key]= value şeklinde dizi oarak döndürür
     * @return array
     * @throws Exception
     */
    public function getSettings():array{
        try {
            $settingModels = $this->getListByFilters();
            $settings = [];
            foreach ($settingModels as $setting) {
                $settings[$setting->group][$setting->key] = match ($setting->type) {
                    'integer' => (int)$setting->value,
                    'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
                    'json' => json_decode($setting->value, true),
                    default => $setting->value
                };
            }
            return $settings;
        }catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }
    }
}