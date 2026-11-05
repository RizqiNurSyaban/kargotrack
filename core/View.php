<?php
namespace Core;

/**
 * View Renderer Engine
 * Mengelola rendering view modular dengan layout terstruktur dan proteksi XSS
 */
class View
{
    private static string $baseDir;

    public static function init(): void
    {
        self::$baseDir = dirname(__DIR__) . '/views/';
    }

    /**
     * Render view with layout
     */
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/main'): void
    {
        if (!isset(self::$baseDir)) {
            self::init();
        }

        // Extract variables to local scope
        extract($data);

        $viewFile = self::$baseDir . $view . '.php';
        if (!file_exists($viewFile)) {
            die("View file tidak ditemukan: " . htmlspecialchars($viewFile, ENT_QUOTES, 'UTF-8'));
        }

        // Render view content into buffer
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        // If layout requested, render inside layout
        if ($layout !== null) {
            $layoutFile = self::$baseDir . $layout . '.php';
            if (file_exists($layoutFile)) {
                require $layoutFile;
                return;
            }
        }

        echo $content;
    }

    /**
     * Render partial view (tanpa layout)
     */
    public static function partial(string $partial, array $data = []): void
    {
        if (!isset(self::$baseDir)) {
            self::init();
        }
        extract($data);
        $file = self::$baseDir . $partial . '.php';
        if (file_exists($file)) {
            require $file;
        }
    }
}

/**
 * Base URL helper
 */
if (!function_exists('base_url')) {
    function base_url(string $path = ''): string {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $dir = str_replace('\\', '/', dirname($scriptName));
        
        // Jika request tidak mengandung /public, gunakan base root project agar URL rapi
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        if (str_ends_with($dir, '/public') && !str_contains($requestUri, '/public')) {
            $dir = substr($dir, 0, -7);
        }
        
        $base = rtrim($protocol . $host . $dir, '/');
        $path = ltrim($path, '/');
        
        return empty($path) ? $base : $base . '/' . $path;
    }
}

/**
 * Format Rupiah Helper
 */
if (!function_exists('format_rupiah')) {
    function format_rupiah($nominal): string {
        return 'Rp ' . number_format((float)$nominal, 0, ',', '.');
    }
}

/**
 * Format Tanggal Indonesia
 */
if (!function_exists('format_tanggal_indo')) {
    function format_tanggal_indo(?string $datetime, bool $withTime = true): string {
        if (!$datetime) return '-';
        $timestamp = strtotime($datetime);
        if (!$timestamp) return $datetime;

        $bulanIndo = [
            1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
            'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'
        ];

        $tgl = date('d', $timestamp);
        $bln = (int)date('m', $timestamp);
        $thn = date('Y', $timestamp);
        $jam = date('H:i', $timestamp);

        $bulanStr = $bulanIndo[$bln] ?? date('M', $timestamp);
        return $withTime ? "{$tgl} {$bulanStr} {$thn}, {$jam}" : "{$tgl} {$bulanStr} {$thn}";
    }
}
