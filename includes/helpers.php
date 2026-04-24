<?php
function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function config($key = null, $default = null)
{
    $config = $GLOBALS['app_config'] ?? [];

    if ($key === null || $key === '') {
        return $config;
    }

    $segments = explode('.', (string)$key);
    $value = $config;

    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }

        $value = $value[$segment];
    }

    return $value;
}

function app_base_url()
{
    $baseUrl = trim((string)config('app.base_url', '/client1'));

    if ($baseUrl === '' || $baseUrl === '/') {
        return '';
    }

    return '/' . trim($baseUrl, '/');
}

function app_url($path = '')
{
    $path = ltrim((string)$path, '/');
    $baseUrl = app_base_url();

    if ($path === '') {
        return $baseUrl === '' ? '/' : $baseUrl;
    }

    if ($baseUrl === '') {
        return '/' . $path;
    }

    return $baseUrl . '/' . $path;
}

function asset_url($path = '')
{
    $path = ltrim((string)$path, '/');

    if ($path === '') {
        return app_url('assets');
    }

    return app_url('assets/' . $path);
}

function format_currency($amount)
{
    return 'PHP ' . number_format((float)$amount, 2);
}

function flash_set($type, $message)
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function flash_get()
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function status_badge_class($status)
{
    $map = [
        'APPROVED' => 'bg-emerald-100 text-emerald-700',
        'REJECTED' => 'bg-rose-100 text-rose-700',
        'PENDING' => 'bg-amber-100 text-amber-700',
        'PAID' => 'bg-emerald-100 text-emerald-700',
        'UNPAID' => 'bg-rose-100 text-rose-700',
        'ORDER_CONFIRMED' => 'bg-amber-100 text-amber-700',
        'ORDER_COMPLETE' => 'bg-emerald-100 text-emerald-700',
        'LOW' => 'bg-amber-100 text-amber-700',
        'OK' => 'bg-emerald-100 text-emerald-700',
        'SENT_TO_RECEIVING' => 'bg-sky-100 text-sky-700',
        'RETURNED' => 'bg-rose-100 text-rose-700',
        'STORED' => 'bg-emerald-100 text-emerald-700',
    ];

    return $map[$status] ?? 'bg-slate-100 text-slate-700';
}

function display_status_label($status)
{
    $map = [
        'SENT_TO_RECEIVING' => 'FORWARDED_TO_INVENTORY',
        'RETURNED' => 'FOLLOW_UP',
        'STORED' => 'COMPLETED',
    ];

    return $map[$status] ?? (string)$status;
}
