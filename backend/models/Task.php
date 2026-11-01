<?php
declare(strict_types=1);

final class Task
{
    public function __construct(private PDO $db) {}

    public function listing(array $user, ?string $status, int $page, int $perPage): array
    {
        $where = [];
        $params = [];
        if ($user['role'] !== 'admin') { $where[] = 't.owner_id = :owner'; $params['owner'] = $user['id']; }
        if ($status !== null) { $where[] = 't.status = :status'; $params['status'] = $status; }
        $clause = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $count = $this->db->prepare('SELECT COUNT(*) FROM tasks t' . $clause);
        $count->execute($params);
        $total = (int)$count->fetchColumn();
        $query = $this->db->prepare('SELECT t.id,t.title,t.description,t.status,t.priority,t.due_date,t.owner_id,t.created_at,t.updated_at,u.name AS owner_name FROM tasks t JOIN users u ON u.id=t.owner_id' . $clause . ' ORDER BY (t.due_date IS NULL), t.due_date ASC, t.updated_at DESC LIMIT :limit OFFSET :offset');
        foreach ($params as $key => $value) $query->bindValue(':' . $key, $value);
        $query->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $query->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $query->execute();
        return ['data' => $query->fetchAll(), 'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'pages' => (int)ceil($total / $perPage)]];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT t.id,t.title,t.description,t.status,t.priority,t.due_date,t.owner_id,t.created_at,t.updated_at,u.name AS owner_name FROM tasks t JOIN users u ON u.id=t.owner_id WHERE t.id=:id');
        $stmt->execute(['id' => $id]); return $stmt->fetch() ?: null;
    }
    public function save(array $data, int $ownerId, ?int $id = null): int
    {
        if ($id === null) {
            $stmt = $this->db->prepare('INSERT INTO tasks (title,description,status,priority,due_date,owner_id) VALUES (:title,:description,:status,:priority,:due_date,:owner_id)');
            $stmt->execute($data + ['owner_id' => $ownerId]); return (int)$this->db->lastInsertId();
        }
        $stmt = $this->db->prepare('UPDATE tasks SET title=:title,description=:description,status=:status,priority=:priority,due_date=:due_date WHERE id=:id');
        $stmt->execute($data + ['id' => $id]); return $id;
    }
    public function delete(int $id): void { $stmt=$this->db->prepare('DELETE FROM tasks WHERE id=:id'); $stmt->execute(['id'=>$id]); }
    public function summary(array $user): array
    {
        $sql = 'SELECT status, COUNT(*) AS count FROM tasks';
        if ($user['role'] !== 'admin') $sql .= ' WHERE owner_id = :owner';
        $sql .= ' GROUP BY status'; $stmt=$this->db->prepare($sql);
        if ($user['role'] !== 'admin') $stmt->execute(['owner'=>$user['id']]); else $stmt->execute();
        $counts=['todo'=>0,'in-progress'=>0,'done'=>0]; foreach ($stmt->fetchAll() as $row) $counts[$row['status']] = (int)$row['count'];
        return $counts;
    }
}
