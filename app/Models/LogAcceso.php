<?php

class LogAcceso extends BaseModel
{
    public function listar(array $filters = [], int $perPage = 50): array
    {
        [$where, $params] = $this->buildFilters($filters);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $offset = ($page - 1) * $perPage;

        $sql = 'SELECT id_log, fecha, username, modulo, accion, ip, resultado, detalle FROM log_accesos';
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY fecha DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function usuariosDisponibles(): array
    {
        return $this->db->query('SELECT DISTINCT username FROM log_accesos WHERE username IS NOT NULL AND username <> "" ORDER BY username ASC')->fetchAll();
    }

    public function exportarCsv(array $filters): void
    {
        [$where, $params] = $this->buildFilters($filters);
        $sql = 'SELECT fecha, username, modulo, accion, ip, resultado, detalle FROM log_accesos';
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY fecha DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="log_accesos.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Fecha/hora', 'Usuario', 'Modulo', 'Accion', 'IP', 'Resultado', 'Detalle']);
        foreach ($stmt->fetchAll() as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
    }

    private function buildFilters(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['usuario'])) {
            $where[] = 'username = :usuario';
            $params['usuario'] = $filters['usuario'];
        }

        if (!empty($filters['modulo'])) {
            $where[] = 'modulo = :modulo';
            $params['modulo'] = $filters['modulo'];
        }

        if (!empty($filters['resultado'])) {
            $where[] = 'resultado = :resultado';
            $params['resultado'] = $filters['resultado'];
        }

        if (!empty($filters['desde'])) {
            $where[] = 'fecha >= :desde';
            $params['desde'] = $filters['desde'] . ' 00:00:00';
        }

        if (!empty($filters['hasta'])) {
            $where[] = 'fecha <= :hasta';
            $params['hasta'] = $filters['hasta'] . ' 23:59:59';
        }

        return [$where, $params];
    }
}