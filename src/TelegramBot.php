<?php

namespace FrutoMir;

use FrutoMir\Config\Configuration;
use FrutoMir\Telegram\TelegramApi;
use FrutoMir\Logger\Logger;
use FrutoMir\AI\OrderDetector;
use FrutoMir\Message\MessageHandler;

/**
 * Main Telegram Bot class
 * Orchestrates all bot functionality following SOLID principles
 */
class TelegramBot
{
    private $config;
    private $telegramApi;
    private $logger;
    private $orderDetector;
    private $messageHandler;

    public function __construct()
    {
        $this->config = new Configuration();
        $this->telegramApi = new TelegramApi($this->config);
        $this->logger = new Logger();
        $this->logger->info("BOT init php=" . PHP_VERSION . " tz=" . date_default_timezone_get());
        $this->orderDetector = new OrderDetector($this->config);
        $this->messageHandler = new MessageHandler(
            $this->config,
            $this->telegramApi,
            $this->logger,
            $this->orderDetector
        );
    }

    /**
     * Process incoming webhook data
     */
    public function processWebhook()
    {
        $this->logger->debug(
            "WEBHOOK begin method=" . ($_SERVER['REQUEST_METHOD'] ?? 'CLI') .
            " ip=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') .
            " len=" . (int)($_SERVER['CONTENT_LENGTH'] ?? 0)
        );
        $input = $this->getWebhookInput();
        
        if (empty($input)) {
            $this->logger->warn("WEBHOOK empty payload");
            return;
        }

        if (!$this->isValidMessage($input)) {
            $this->logger->warn("WEBHOOK invalid payload");
            return;
        }

        $this->logger->debug("WEBHOOK ok message_id=" . ($input['message']['message_id'] ?? '—'));
        $this->messageHandler->handleMessage($input['message']);
        $this->logger->debug("WEBHOOK end");
    }

    /**
     * Get webhook input data
     */
    private function getWebhookInput()
    {
        $rawInput = file_get_contents('php://input');
        $this->logger->debug("WEBHOOK raw_len=" . strlen((string)$rawInput));
        return json_decode($rawInput, true) ?? [];
    }

    /**
     * Validate if input contains a valid message
     */
    private function isValidMessage($input)
    {
        return isset($input['message']) 
            && is_array($input['message'])
            && isset($input['message']['from'])
            && isset($input['message']['chat']);
    }
}
