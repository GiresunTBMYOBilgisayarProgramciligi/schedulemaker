<?php

namespace App\Models;

use App\Core\Model;
use App\Enums\UnitType;
use App\Enums\UserRole;
use Exception;

class Unit extends Model
{
    public ?int $id = null;
    public ?string $name = null;
    public ?string $type = null;
    public ?int $manager_id = null;
    public ?bool $active = null;

    public ?User $manager = null;
    public array $submanagers = [];
    public array $departments = [];
    public array $buildings = [];
    protected array $excludeFromDb = ['manager', 'submanagers', 'departments', 'buildings'];
    protected string $table_name = 'units';

    public function getLabel(): string
    {
        return 'birim';
    }

    public function getLogDetail(): string
    {
        return $this->name ?? 'ID: ' . $this->id;
    }

    public function getTypeName(): string
    {
        return UnitType::tryFrom((string)$this->type)?->getLabel() ?? '';
    }

    public function getManagerTitle(): string
    {
        return UnitType::tryFrom((string)$this->type)?->getManagerTitle() ?? 'Müdür';
    }

    public function getSubManagerTitle(bool $plural = false): string
    {
        return UnitType::tryFrom((string)$this->type)?->getSubManagerTitle($plural) ?? ($plural ? 'Müdür Yardımcıları' : 'Müdür Yardımcısı');
    }

    /**
     * @param array $results
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function getDepartmentsRelation(array $results, array $options = []): array
    {
        $unitIds = array_column($results, 'id');
        if (empty($unitIds))
            return $results;

        $query = (new Department())->get()->where(['unit_id' => ['in' => $unitIds]]);

        if (isset($options['with'])) {
            $query->with($options['with']);
        }

        $departments = $query->all();
        $departmentsGrouped = [];
        foreach ($departments as $dept) {
            $departmentsGrouped[$dept->unit_id][] = $dept;
        }

        foreach ($results as &$row) {
            $row['departments'] = $departmentsGrouped[$row['id']] ?? [];
        }
        return $results;
    }

    /**
     * @param array $results
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function getBuildingsRelation(array $results, array $options = []): array
    {
        $unitIds = array_column($results, 'id');
        if (empty($unitIds)) {
            return $results;
        }

        $query = (new Building())->get()->where(['unit_id' => ['in' => $unitIds]]);

        if (isset($options['with'])) {
            $query->with($options['with']);
        }

        $buildings = $query->all();
        $buildingsGrouped = [];
        foreach ($buildings as $b) {
            $buildingsGrouped[$b->unit_id][] = $b;
        }

        foreach ($results as &$row) {
            $row['buildings'] = $buildingsGrouped[$row['id']] ?? [];
        }
        return $results;
    }

    /**
     * @param array $results
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function getManagerRelation(array $results, array $options = []): array
    {
        $userIds = array_unique(array_column($results, 'manager_id'));
        $userIds = array_filter($userIds);
        if (empty($userIds)) {
            return $results;
        }

        $query = (new User())->get()->where(['id' => ['in' => $userIds]]);

        if (isset($options['with'])) {
            $query->with($options['with']);
        }

        $users = $query->all();
        $usersKeyed = [];
        foreach ($users as $user) {
            $usersKeyed[$user->id] = $user;
        }

        foreach ($results as &$row) {
            if (isset($row['manager_id']) && isset($usersKeyed[$row['manager_id']])) {
                $row['manager'] = $usersKeyed[$row['manager_id']];
            } else {
                $row['manager'] = null;
            }
        }
        return $results;
    }

    /**
     * @param array $results
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function getSubmanagersRelation(array $results, array $options = []): array
    {
        $unitIds = array_column($results, 'id');
        if (empty($unitIds)) {
            return $results;
        }

        $query = (new User())->get()->where([
            'unit_id' => ['in' => $unitIds],
            'role'    => UserRole::SubManager->value,
        ]);

        if (isset($options['with'])) {
            $query->with($options['with']);
        }

        $submanagers = $query->all();
        $submanagersGrouped = [];
        foreach ($submanagers as $user) {
            $submanagersGrouped[$user->unit_id][] = $user;
        }

        foreach ($results as &$row) {
            $row['submanagers'] = $submanagersGrouped[$row['id']] ?? [];
        }
        return $results;
    }
}
