<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

final class HealthController
{
    public function index(Request $request): string
    {
        $db = 'skipped';
        try {
            $pdo = Database::pdo();
            $pdo->query('SELECT 1');
            $db = 'ok';
        } catch (\Throwable $e) {
            $db = 'down';
        }

        return Response::json([
            'status' => 'ok',
            'service' => 'itportal',
            'php' => PHP_VERSION,
            'time' => date('c'),
            'db' => $db,
        ]);
    }
}
