<?php

class Proveedor extends BaseModel
{
    private const LIMITES = [
        'CardCode' => 15,
        'CardName' => 100,
        'MailAddres' => 254,
        'MailZipCod' => 20,
        'CntctPrsn' => 90,
        'LicTradNum' => 32,
        'Country' => 100,
        'MailCity' => 100,
        'MailCounty' => 100,
        'MailCountr' => 100,
        'E_Mail' => 100,
    ];

    public function listar(array $filters = []): array
    {
        $sql = 'SELECT CardCode, CardName, GroupCode, MailAddres, MailZipCod, CntctPrsn,
                       Notes, Balance, LicTradNum, Country, MailCity, MailCounty, MailCountr, E_Mail
                FROM proveedores';
        $params = [];
        $query = trim((string) ($filters['q'] ?? ''));

        if ($query !== '') {
            $sql .= ' WHERE CardCode LIKE :codigo OR CardName LIKE :nombre OR LicTradNum LIKE :rif OR E_Mail LIKE :email';
            $like = '%' . $query . '%';
            $params = [
                'codigo' => $like,
                'nombre' => $like,
                'rif' => $like,
                'email' => $like,
            ];
        }

        $sql .= ' ORDER BY CardName ASC, CardCode ASC';
        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function obtener(string $cardCode): ?array
    {
        $statement = $this->db->prepare(
            'SELECT CardCode, CardName, GroupCode, MailAddres, MailZipCod, CntctPrsn,
                    Notes, Balance, LicTradNum, Country, MailCity, MailCounty, MailCountr, E_Mail
             FROM proveedores
             WHERE CardCode = :CardCode
             LIMIT 1'
        );
        $statement->execute(['CardCode' => trim($cardCode)]);
        $proveedor = $statement->fetch();

        return $proveedor ?: null;
    }

    public function crear(array $data): array
    {
        $data = $this->normalizar($data);
        if ($this->obtener($data['CardCode'])) {
            throw new InvalidArgumentException('Ya existe un proveedor o fabricante con ese codigo.');
        }

        $statement = $this->db->prepare(
            'INSERT INTO proveedores
                (CardCode, CardName, GroupCode, MailAddres, MailZipCod, CntctPrsn,
                 Notes, Balance, LicTradNum, Country, MailCity, MailCounty, MailCountr, E_Mail)
             VALUES
                (:CardCode, :CardName, :GroupCode, :MailAddres, :MailZipCod, :CntctPrsn,
                 :Notes, :Balance, :LicTradNum, :Country, :MailCity, :MailCounty, :MailCountr, :E_Mail)'
        );
        $statement->execute($data);

        return $data;
    }

    public function actualizar(string $cardCode, array $data): bool
    {
        $cardCode = trim($cardCode);
        if (!$this->obtener($cardCode)) {
            throw new InvalidArgumentException('El proveedor o fabricante no existe.');
        }

        $data['CardCode'] = $cardCode;
        $data = $this->normalizar($data);
        $statement = $this->db->prepare(
            'UPDATE proveedores
             SET CardName = :CardName,
                 GroupCode = :GroupCode,
                 MailAddres = :MailAddres,
                 MailZipCod = :MailZipCod,
                 CntctPrsn = :CntctPrsn,
                 Notes = :Notes,
                 Balance = :Balance,
                 LicTradNum = :LicTradNum,
                 Country = :Country,
                 MailCity = :MailCity,
                 MailCounty = :MailCounty,
                 MailCountr = :MailCountr,
                 E_Mail = :E_Mail
             WHERE CardCode = :CardCode'
        );

        return $statement->execute($data);
    }

    public function eliminar(string $cardCode): bool
    {
        $cardCode = trim($cardCode);
        if ($this->estaEnUso($cardCode)) {
            throw new DomainException('No se puede eliminar porque ya se usa en entradas de inventario.');
        }

        $statement = $this->db->prepare('DELETE FROM proveedores WHERE CardCode = :CardCode');
        $statement->execute(['CardCode' => $cardCode]);

        return $statement->rowCount() === 1;
    }

    public function estaEnUso(string $cardCode): bool
    {
        $statement = $this->db->prepare(
            'SELECT COUNT(*)
             FROM inventarioentrante
             WHERE CardCode = :codigoProveedor OR FabricanteCode = :codigoFabricante'
        );
        $statement->execute([
            'codigoProveedor' => trim($cardCode),
            'codigoFabricante' => trim($cardCode),
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function normalizar(array $data): array
    {
        $normalized = [];
        foreach (self::LIMITES as $field => $limit) {
            $value = trim((string) ($data[$field] ?? ''));
            if (mb_strlen($value) > $limit) {
                throw new InvalidArgumentException($field . ' no puede superar ' . $limit . ' caracteres.');
            }
            $normalized[$field] = $value === '' ? null : $value;
        }

        if ($normalized['CardCode'] === null || $normalized['CardName'] === null) {
            throw new InvalidArgumentException('El codigo y el nombre son obligatorios.');
        }

        if ($normalized['E_Mail'] !== null && filter_var($normalized['E_Mail'], FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('El correo electronico no es valido.');
        }

        $groupCode = trim((string) ($data['GroupCode'] ?? ''));
        if ($groupCode !== '' && filter_var($groupCode, FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException('El grupo debe ser un numero entero.');
        }

        $balance = trim((string) ($data['Balance'] ?? ''));
        if ($balance !== '' && !is_numeric($balance)) {
            throw new InvalidArgumentException('El balance debe ser numerico.');
        }

        $normalized['GroupCode'] = $groupCode === '' ? null : (int) $groupCode;
        $normalized['Balance'] = $balance === '' ? null : (float) $balance;
        $normalized['Notes'] = trim((string) ($data['Notes'] ?? '')) ?: null;

        return $normalized;
    }
}