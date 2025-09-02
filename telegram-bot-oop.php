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

try {
    $bot = new TelegramBot();
    $bot->processWebhook();
} catch (Throwable $e) {
    // Log error for debugging
    error_log("Bot Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    
    // Return 200 to prevent Telegram from retrying
    http_response_code(200);
}
