<?php

class ClienteCotizacion extends BaseModel
{
    public function listar(array $filters = []): array
    {
        $query = trim((string) ($filters['q'] ?? ''));
        $status = (string) ($filters['status'] ?? 'active');
        $sql = 'SELECT CardCode AS idCliente, LicTradNum AS rif, CardName AS nombre,
                       COALESCE(MailAddres, Address) AS direccion, E_Mail AS email,
                       Phone1 AS telefono, CardType AS tipo, activo
                FROM tbl_clientes_cotizacion';
        $params = [];

        if ($status === 'inactive') {
            $sql .= ' WHERE activo = 0';
        } elseif ($status !== 'all') {
            $sql .= ' WHERE activo = 1';
        }

        if ($query !== '') {
            $sql .= str_contains($sql, ' WHERE ') ? ' AND' : ' WHERE';
            $sql .= ' (CardCode LIKE :codigo OR LicTradNum LIKE :rif OR CardName LIKE :nombre OR E_Mail LIKE :email)';
            $like = '%' . $query . '%';
            $params = ['codigo' => $like, 'rif' => $like, 'nombre' => $like, 'email' => $like];
        }
        $sql .= ' ORDER BY CardName ASC, CardCode ASC';
        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function listarPaginado(array $filters = [], int $page = 1, int $pageSize = 25): array
    {
        $pageSize = max(1, min(100, $pageSize));
        $page = max(1, $page);
        $query = trim((string) ($filters['q'] ?? ''));
        $status = (string) ($filters['status'] ?? 'all');
        $where = [];
        $params = [];

        if ($status === 'inactive') {
            $where[] = 'activo = 0';
        } elseif ($status !== 'all') {
            $where[] = 'activo = 1';
        }
        if ($query !== '') {
            $where[] = '(CardCode LIKE :codigo OR LicTradNum LIKE :rif OR CardName LIKE :nombre OR E_Mail LIKE :email)';
            $like = '%' . $query . '%';
            $params += ['codigo' => $like, 'rif' => $like, 'nombre' => $like, 'email' => $like];
        }

        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $countStatement = $this->db->prepare('SELECT COUNT(*) FROM tbl_clientes_cotizacion' . $whereSql);
        $countStatement->execute($params);
        $total = (int) $countStatement->fetchColumn();
        $totalPages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $pageSize;

        $sql = 'SELECT CardCode AS idCliente, LicTradNum AS rif, CardName AS nombre,
                       COALESCE(MailAddres, Address) AS direccion, E_Mail AS email,
                       Phone1 AS telefono, CardType AS tipo, activo,
                       (SELECT COUNT(*) FROM tbl_cotizacion_cabecera cotizacion
                        WHERE cotizacion.idCliente = cliente.CardCode) AS cantidadCotizaciones
                FROM tbl_clientes_cotizacion cliente' . $whereSql .
                ' ORDER BY CardName ASC, CardCode ASC LIMIT :limite OFFSET :offset';
        $statement = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $statement->bindValue(':limite', $pageSize, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return [
            'clientes' => $statement->fetchAll(),
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'totalPages' => $totalPages,
        ];
    }

    public function obtenerTodosLosClientes(): array
    {
        return $this->listar();
    }

    public function obtenerClientePorId(string $idCliente): ?array
    {
        $statement = $this->db->prepare(
            'SELECT CardCode AS idCliente, LicTradNum AS rif, CardName AS nombre,
                    COALESCE(MailAddres, Address) AS direccion, E_Mail AS email,
                    Phone1 AS telefono, CardType AS tipo, activo
             FROM tbl_clientes_cotizacion
             WHERE CardCode = :idCliente AND activo = 1 LIMIT 1'
        );
        $statement->execute(['idCliente' => trim($idCliente)]);
        $cliente = $statement->fetch();

        return $cliente ?: null;
    }

    public function crear(array $data): string
    {
        $data = $this->normalizar($data);
        $data['CardCode'] = $this->siguienteCodigo();
        $statement = $this->db->prepare(
            'INSERT INTO tbl_clientes_cotizacion
                (CardCode, CardName, LicTradNum, MailAddres, E_Mail, Phone1, activo)
             VALUES (:CardCode, :CardName, :LicTradNum, :MailAddres, :E_Mail, :Phone1, 1)'
        );
        $statement->execute($data);

        return $data['CardCode'];
    }

    public function actualizar(string $idCliente, array $data): bool
    {
        $exists = $this->db->prepare('SELECT 1 FROM tbl_clientes_cotizacion WHERE CardCode = :idCliente LIMIT 1');
        $exists->execute(['idCliente' => trim($idCliente)]);
        if (!$exists->fetchColumn()) {
            throw new InvalidArgumentException('El cliente de Cotizaciones no existe.');
        }
        $data = $this->normalizar($data);
        $data['CardCode'] = trim($idCliente);
        $data['activo'] = !empty($data['activo']) ? 1 : 0;
        $statement = $this->db->prepare(
            'UPDATE tbl_clientes_cotizacion
             SET CardName = :CardName, LicTradNum = :LicTradNum,
                 MailAddres = :MailAddres, E_Mail = :E_Mail, Phone1 = :Phone1,
                 activo = :activo
             WHERE CardCode = :CardCode'
        );

        return $statement->execute($data);
    }

    public function actualizarEmailSiVacio(string $idCliente, string $email): bool
    {
        $email = trim($email);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }
        $statement = $this->db->prepare(
            'UPDATE tbl_clientes_cotizacion SET E_Mail = :email
             WHERE CardCode = :idCliente AND activo = 1
               AND (E_Mail IS NULL OR TRIM(E_Mail) = "")'
        );
        $statement->execute(['email' => $email, 'idCliente' => trim($idCliente)]);

        return $statement->rowCount() === 1;
    }

    public function desactivar(string $idCliente): bool
    {
        $statement = $this->db->prepare(
            'UPDATE tbl_clientes_cotizacion SET activo = 0
             WHERE CardCode = :idCliente AND activo = 1'
        );
        $statement->execute(['idCliente' => trim($idCliente)]);

        return $statement->rowCount() === 1;
    }

    public function activar(string $idCliente): bool
    {
        $statement = $this->db->prepare(
            'UPDATE tbl_clientes_cotizacion SET activo = 1
             WHERE CardCode = :idCliente AND activo = 0'
        );
        $statement->execute(['idCliente' => trim($idCliente)]);

        return $statement->rowCount() === 1;
    }

    private function siguienteCodigo(): string
    {
        $statement = $this->db->query("SELECT CardCode FROM tbl_clientes_cotizacion WHERE CardCode LIKE 'COT%' ORDER BY CardCode DESC LIMIT 1");
        $ultimo = (string) ($statement->fetchColumn() ?: '');
        $secuencia = preg_match('/^COT(\d+)$/', $ultimo, $matches) ? (int) $matches[1] + 1 : 1;

        return 'COT' . str_pad((string) $secuencia, 5, '0', STR_PAD_LEFT);
    }

    private function normalizar(array $data): array
    {
        $nombre = trim((string) ($data['nombre'] ?? $data['CardName'] ?? ''));
        $rif = trim((string) ($data['rif'] ?? $data['LicTradNum'] ?? ''));
        $direccion = trim((string) ($data['direccion'] ?? $data['MailAddres'] ?? ''));
        $email = trim((string) ($data['email'] ?? $data['E_Mail'] ?? ''));
        $telefono = trim((string) ($data['telefono'] ?? $data['Phone1'] ?? ''));

        if ($nombre === '' || mb_strlen($nombre) > 100 || $rif === '' || mb_strlen($rif) > 32) {
            throw new InvalidArgumentException('El nombre y el RIF son obligatorios y tienen un formato invalido.');
        }
        if (mb_strlen($direccion) > 100 || mb_strlen($email) > 100 || mb_strlen($telefono) > 20) {
            throw new InvalidArgumentException('Uno de los datos del cliente supera el limite permitido.');
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('El correo electronico no es valido.');
        }

        return [
            'CardName' => $nombre,
            'LicTradNum' => $rif,
            'MailAddres' => $direccion === '' ? null : $direccion,
            'E_Mail' => $email === '' ? null : $email,
            'Phone1' => $telefono === '' ? null : $telefono,
            'activo' => !empty($data['activo']) ? 1 : 0,
        ];
    }
}