<?php

namespace App\Listeners;

use App\Core\Log;
use App\Enums\UserRole;
use App\Events\ChairpersonChangedEvent;
use App\Repositories\DepartmentRepository;
use App\Repositories\UserRepository;
use App\Services\UserService;

/**
 * Bölüm başkanı değiştiğinde kullanıcı rollerini senkronize eden dinleyici.
 *
 * - Yeni başkan varsa: rolünü DepartmentHead yapar.
 * - Eski başkan varsa ve başka bölümde başkan değilse: rolünü Lecturer'a düşürür.
 */
class SyncChairpersonRoleListener
{
    /**
     * @param ChairpersonChangedEvent $event
     */
    public function handle(ChairpersonChangedEvent $event): void
    {
        $logger = Log::logger();
        $userRepository = new UserRepository();
        $departmentRepository = new DepartmentRepository();
        $userService = new UserService();

        // Eski başkanın rolünü düşür (başka bölümde başkan değilse)
        if ($event->oldChairpersonId !== null) {
            /** @var \App\Models\User|null $oldChairperson */
            $oldChairperson = $userRepository->find($event->oldChairpersonId);

            if ($oldChairperson && !$departmentRepository->isChairpersonOfAnyDepartment($event->oldChairpersonId)) {
                $oldChairperson->role = UserRole::Lecturer->value;
                $userService->updateUser($oldChairperson);

                $logger->info('Eski bölüm başkanının rolü Akademisyen olarak güncellendi', [
                    'user_id' => $event->oldChairpersonId,
                ]);
            }
        }

        // Yeni başkanın rolünü yükselt
        if ($event->newChairpersonId !== null) {
            /** @var \App\Models\User|null $newChairperson */
            $newChairperson = $userRepository->find($event->newChairpersonId);

            if ($newChairperson && $newChairperson->role !== UserRole::DepartmentHead->value) {
                $newChairperson->role = UserRole::DepartmentHead->value;
                $userService->updateUser($newChairperson);

                $logger->info('Yeni bölüm başkanının rolü Bölüm Başkanı olarak güncellendi', [
                    'user_id' => $event->newChairpersonId,
                ]);
            }
        }
    }
}
