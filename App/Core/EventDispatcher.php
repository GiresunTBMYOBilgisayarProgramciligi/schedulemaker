<?php

namespace App\Core;

class EventDispatcher
{
    /**
     * @var array<string, array<callable|string>>
     */
    protected array $listeners = [];

    /**
     * @var EventDispatcher|null
     */
    protected static ?EventDispatcher $instance = null;

    /**
     * Singleton instance
     */
    public static function getInstance(): EventDispatcher
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Bir olaya (event) dinleyici (listener) kaydeder.
     * 
     * @param string $eventName Event sınıfının tam adı (örn: UserForgotPasswordEvent::class)
     * @param callable|string $listener Listener sınıf adı (örn: SendPasswordResetEmailListener::class) veya çağrılabilir fonksiyon
     */
    public function listen(string $eventName, $listener): void
    {
        if (!isset($this->listeners[$eventName])) {
            $this->listeners[$eventName] = [];
        }

        $this->listeners[$eventName][] = $listener;
    }

    /**
     * Kayıtlı bir olayı tetikler.
     * 
     * @param object $event Olay nesnesi
     */
    public function dispatch(object $event): void
    {
        $eventName = get_class($event);

        if (!isset($this->listeners[$eventName])) {
            return;
        }

        foreach ($this->listeners[$eventName] as $listener) {
            if (is_callable($listener)) {
                call_user_func($listener, $event);
            } elseif (is_string($listener) && class_exists($listener)) {
                $listenerInstance = new $listener();
                if (method_exists($listenerInstance, 'handle')) {
                    $listenerInstance->handle($event);
                }
            }
        }
    }
}
