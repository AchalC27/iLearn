<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function load_data(): array
{
    $file = __DIR__ . '/../storage/data.json';
    if (!is_file($file)) {
        return ['users' => [], 'posts' => [], 'comments' => []];
    }

    $data = json_decode((string)file_get_contents($file), true);
    return is_array($data) ? $data : ['users' => [], 'posts' => [], 'comments' => []];
}
