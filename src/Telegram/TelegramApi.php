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
        return file_get_contents($url);
    }

    /**
     * Get chat member information
     */
    public function getChatMember($chatId, $userId)
    {
        $url = "{$this->baseUrl}/getChatMember?chat_id={$chatId}&user_id={$userId}";
        $response = file_get_contents($url);
        return json_decode($response, true) ?? [];
    }

    /**
     * Get forum topic information by ID
     */
    public function getForumTopicById($chatId, $threadId)
    {
        $url = "{$this->baseUrl}/getForumTopicByID?chat_id={$chatId}&message_thread_id={$threadId}";
        $response = file_get_contents($url);
        return json_decode($response, true) ?? [];
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
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            file_put_contents('curl_errors.txt', curl_error($ch), FILE_APPEND);
        }

        curl_close($ch);
        return $response ?: null;
    }
}
