<?php

/**
 * FrutoMir Telegram Bot - Object-Oriented Entry Point
 * 
 * This is the new OOP version of the bot following SOLID, DRY, KISS principles
 * and PSR standards with English comments.
 * 
 * @author FrutoMir Team
 * @version 2.0
 */

require_once __DIR__ . '/vendor/autoload.php';

use FrutoMir\TelegramBot;
use FrutoMir\Logger\Logger;

// Initialize logger early to capture startup issues
$logger = new Logger();

// Basic PHP and environment diagnostics (brief)
$logger->info(
    'ENTRY php=' . PHP_VERSION .
    ' sapi=' . PHP_SAPI .
    ' ip=' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') .
    ' ua=' . (isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 80) : '—')
);

// Global error/exception handlers to ensure errors are logged
set_error_handler(function ($severity, $message, $file, $line) use ($logger) {
    $logger->error("PHP error sev={$severity} msg={$message} at {$file}:{$line}");
});

set_exception_handler(function ($e) use ($logger) {
    $logger->error('Uncaught exception ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
});

register_shutdown_function(function () use ($logger) {
    $err = error_get_last();
    if ($err) {
        $logger->error('Shutdown error type=' . $err['type'] . ' msg=' . $err['message'] . ' at ' . $err['file'] . ':' . $err['line']);
    }
});

try {
    $bot = new TelegramBot();
    $bot->processWebhook();
} catch (Throwable $e) {
    $logger->error("Bot fatal: " . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(200);
}
