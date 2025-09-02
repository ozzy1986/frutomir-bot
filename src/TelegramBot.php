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
        $input = $this->getWebhookInput();
        
        if (!$this->isValidMessage($input)) {
            return;
        }

        $this->messageHandler->handleMessage($input['message']);
    }

    /**
     * Get webhook input data
     */
    private function getWebhookInput()
    {
        $rawInput = file_get_contents('php://input');
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
