<?php
declare(strict_types=1);

session_start();

const DATA_FILE = __DIR__ . '/../storage/data.json';
const DEFAULTS_FILE = __DIR__ . '/../data/defaults.php';

function e(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function load_data(): array {
    if (!file_exists(DATA_FILE)) {
        $defaults = require DEFAULTS_FILE;
        save_data($defaults);
        return $defaults;
    }

    $json = file_get_contents(DATA_FILE);
    $data = json_decode($json ?: '', true);

    if (!is_array($data) || !isset($data['users'], $data['posts'], $data['comments'])) {
        $data = require DEFAULTS_FILE;
        save_data($data);
    }

    return $data;
}

function save_data(array $data): void {
    file_put_contents(
        DATA_FILE,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

function redirect_to(string $page = 'dashboard', array $params = []): never {
    $query = http_build_query(array_merge(['page' => $page], $params));
    header('Location: index.php?' . $query);
    exit;
}

function flash(string $message, string $type = 'success'): void {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function consume_flash(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function new_id(string $prefix): string {
    return $prefix . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
}

function find_by_id(array $items, string $id): ?array {
    foreach ($items as $item) {
        if (($item['id'] ?? '') === $id) return $item;
    }
    return null;
}

function find_index(array $items, string $id): int {
    foreach ($items as $i => $item) {
        if (($item['id'] ?? '') === $id) return $i;
    }
    return -1;
}

function csv_download(string $filename, array $headers, array $rows): never {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $row) fputcsv($out, $row);
    fclose($out);
    exit;
}
