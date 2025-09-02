<?php

namespace FrutoMir\Logger;

/**
 * Simple logger class for bot operations
 * Follows Single Responsibility Principle
 */
class Logger
{
    private $logFile;

    public function __construct($logFile = 'log.txt')
    {
        $this->logFile = $logFile;
    }

    /**
     * Log a message with timestamp
     */
    public function log($message)
    {
        $timestamp = date('d.m.Y H:i:s');
        $logEntry = "[{$timestamp}] {$message}\n";
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Log incoming message data
     */
    public function logMessage($messageData)
    {
        $chatId = $messageData['chat']['id'] ?? 'unknown';
        $threadId = $messageData['message_thread_id'] ?? '—';
        $text = $messageData['text'] ?? '';
        $time = date('d.m.Y H:i:s');

        $logEntry = "Time: {$time}\nChat: {$chatId}\nTopic: {$threadId}\nText: {$text}\n---\n" 
                   . print_r($messageData, true) . "\n---\n\n\n";
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Log order detection process
     */
    public function logOrderDetection($service, $userName, $userId, $text)
    {
        $this->log("=== ORDER DETECTION ({$service}) ===");
        $this->log("User: {$userName} (ID: {$userId})");
        $this->log("Text: {$text}");
    }

    /**
     * Log API call results
     */
    public function logApiResult($service, $result)
    {
        $this->log("Starting {$service} call for order detection");
        $this->log("{$service} result: " . print_r($result, true));
    }

    /**
     * Log detected orders
     */
    public function logDetectedOrder($service)
    {
        $this->log("ORDER DETECTED BY {$service}!");
    }

    /**
     * End order detection logging
     */
    public function endOrderDetection()
    {
        $this->log("=== END ORDER DETECTION ===\n");
    }
}
