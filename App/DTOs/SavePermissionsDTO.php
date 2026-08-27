<?php

namespace App\DTOs;

/**
 * Yetki kaydetme işlemi için verileri taşıyan kesin tipli DTO.
 */
readonly class SavePermissionsDTO
{
    /**
     * @param int $userId
     * @param string $scope 'units', 'departments', 'programs'
     * @param int $targetId
     * @param array $permissions İzin dizisi
     */
    public function __construct(
        public int $userId,
        public string $scope,
        public int $targetId,
        public array $permissions = []
    ) {
    }

    public static function fromArray(array $data): self
    {
        $permissions = $data['permissions'] ?? [];
        if (is_string($permissions)) {
            $permissions = json_decode($permissions, true) ?: [];
        }

        return new self(
            userId: (int)($data['user_id'] ?? 0),
            scope: (string)($data['scope'] ?? ''),
            targetId: (int)($data['target_id'] ?? 0),
            permissions: is_array($permissions) ? $permissions : []
        );
    }

    public function toArray(): array
    {
        return [
            'user_id'     => $this->userId,
            'scope'       => $this->scope,
            'target_id'   => $this->targetId,
            'permissions' => $this->permissions,
        ];
    }
}
