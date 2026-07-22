<?php

class CatalogoModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function getRoles(): array {
        return $this->db->query("SELECT id, nombre FROM roles ORDER BY id")->fetchAll();
    }

    public function getEstados(): array {
        return $this->db->query("SELECT id, nombre FROM estados ORDER BY id")->fetchAll();
    }

    public function getPrioridades(): array {
        return $this->db->query("SELECT id, nombre FROM prioridades ORDER BY id")->fetchAll();
    }
}
