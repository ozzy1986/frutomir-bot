<?php

namespace FrutoMir\Telegram;

use FrutoMir\Config\Configuration;

/**
 * Telegram API wrapper class
 * Handles all Telegram API interactions
 */
class TelegramApi
{
    private $config;
    private $baseUrl;
    private $defaultTimeoutSeconds = 20;
    private $defaultConnectTimeoutSeconds = 5;

    public function __construct($config)
    {
        $this->config = $config;
        $this->baseUrl = $config->getTelegramApiUrl();
    }

    /**
     * Send a message via Telegram API
     */
    public function sendMessage($params)
    {
        return $this->makeRequest('sendMessage', $params);
    }

    /**
     * Send a photo via Telegram API
     */
    public function sendPhoto($params)
    {
        return $this->makeRequest('sendPhoto', $params);
    }

    /**
     * Send a document via Telegram API
     */
    public function sendDocument($params)
    {
        return $this->makeRequest('sendDocument', $params);
    }

    /**
     * Send a sticker via Telegram API
     */
    public function sendSticker($params)
    {
        return $this->makeRequest('sendSticker', $params);
    }

    /**
     * Delete a message
     */
    public function deleteMessage($chatId, $messageId)
    {
        $url = "{$this->baseUrl}/deleteMessage?chat_id={$chatId}&message_id={$messageId}";
        return $this->getWithTimeout($url);
    }

    /**
     * Get chat member information
     */
    public function getChatMember($chatId, $userId)
    {
        $url = "{$this->baseUrl}/getChatMember?chat_id={$chatId}&user_id={$userId}";
        $response = $this->getWithTimeout($url);
        return $response ? (json_decode($response, true) ?? []) : [];
    }

    /**
     * Get forum topic information by ID
     */
    public function getForumTopicById($chatId, $threadId)
    {
        // Note: Telegram Bot API does not provide a method to fetch topic by ID.
        // We keep this method for backward compatibility but make it safe and non-blocking.
        $url = "{$this->baseUrl}/getForumTopicByID?chat_id={$chatId}&message_thread_id={$threadId}";
        $response = $this->getWithTimeout($url);
        return $response ? (json_decode($response, true) ?? []) : [];
    }

    /**
     * Make a cURL request to Telegram API
     */
    private function makeRequest($method, $params)
    {
        $url = "{$this->baseUrl}/{$method}";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->defaultConnectTimeoutSeconds);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->defaultTimeoutSeconds);
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            file_put_contents('curl_errors.txt', curl_error($ch), FILE_APPEND);
        }

        curl_close($ch);
        return $response ?: null;
    }

    /**
     * Safe GET request with timeouts and error handling
     */
    private function getWithTimeout($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->defaultConnectTimeoutSeconds);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->defaultTimeoutSeconds);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $response = curl_exec($ch);
        if ($response === false) {
            curl_close($ch);
            return null;
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200) {
            return null;
        }
        return $response;
    }
}
