<?php
declare(strict_types=1);

namespace Moro\Core;

/**
 * HTTP response helpers
 */
final class Response
{
    public static function redirect(string $path): never
    {
        header('Location: ' . Paths::baseUrl() . $path);
        exit;
    }

    public static function redirectToUrl(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }

    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }
}
