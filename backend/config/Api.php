<?php
declare(strict_types=1);

final class Api
{
    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    public static function body(): array
    {
        $decoded = json_decode(file_get_contents('php://input') ?: '', true);
        if (!is_array($decoded)) self::json(['error' => 'Request body must be valid JSON.'], 400);
        return $decoded;
    }

    public static function requireMethod(string $method): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
            header('Allow: ' . $method);
            self::json(['error' => 'Method not allowed.'], 405);
        }
    }

    public static function csrf(): void
    {
        $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($provided) || !hash_equals($_SESSION['csrf'] ?? '', $provided)) {
            self::json(['error' => 'CSRF token is missing or invalid. Refresh and try again.'], 403);
        }
    }
}
