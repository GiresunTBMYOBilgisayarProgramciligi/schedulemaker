<?php

namespace App\Controllers;

use App\Core\Controller;

class DepartmentController extends Controller
{
    private string $table_name = "departments";

    public function getDepartments()
    {
        try {
            $q = $this->database->prepare("Select * From $this->table_name");
            $q->execute();
            return $q->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            echo $e->getMessage();
            return [];
        }
    }
}