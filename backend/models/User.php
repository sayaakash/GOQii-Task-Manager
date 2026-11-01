<?php
declare(strict_types=1);

final class User
{
    public function __construct(private PDO $db) {}

    public function byEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT u.id, u.name, u.email, u.password_hash, r.name AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        return $stmt->fetch() ?: null;
    }

    public function create(string $name, string $email, string $password): int
    {
        $stmt = $this->db->prepare("INSERT INTO users (name, email, password_hash, role_id) SELECT :name, :email, :hash, id FROM roles WHERE name = 'user'");
        $stmt->execute(['name' => $name, 'email' => $email, 'hash' => password_hash($password, PASSWORD_DEFAULT)]);
        return (int)$this->db->lastInsertId();
    }

    public function all(): array
    {
        return $this->db->query('SELECT u.id, u.name, u.email, r.name AS role, u.created_at, COUNT(t.id) AS task_count FROM users u JOIN roles r ON r.id=u.role_id LEFT JOIN tasks t ON t.owner_id=u.id GROUP BY u.id, u.name, u.email, r.name, u.created_at ORDER BY u.created_at DESC')->fetchAll();
    }
}
