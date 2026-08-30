<?php

namespace App\Routers;

use App\Core\Router;
use App\Middlewares\AuthMiddleware;
use App\Models\User;

class LegalRouter extends Router
{
    private ?User $currentUser = null;

    public function __construct()
    {
        parent::__construct();
        $this->currentUser = AuthMiddleware::user();
        $this->view_data['currentUser'] = $this->currentUser;
    }

    /**
     * KVKK Aydınlatma Metni Sayfası
     */
    public function IndexAction(): void
    {
        $this->KvkkAction();
    }

    /**
     * KVKK Aydınlatma Metni
     */
    public function KvkkAction(): void
    {
        $this->view_data['page_title'] = "KVKK Aydınlatma Metni";
        $this->callView("home/legal/kvkk");
    }

    /**
     * Gizlilik ve Çerez Politikası
     */
    public function PrivacyAction(): void
    {
        $this->view_data['page_title'] = "Gizlilik ve Çerez Politikası";
        $this->callView("home/legal/privacy");
    }

    /**
     * Türkçe alias
     */
    public function GizlilikAction(): void
    {
        $this->PrivacyAction();
    }
}
