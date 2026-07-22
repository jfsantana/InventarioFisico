<?php

class BaseModel
{
    protected PDO $db;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
    }
}
