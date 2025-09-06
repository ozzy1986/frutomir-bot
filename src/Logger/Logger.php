<?php

namespace FrutoMir\Logger;

/**
 * Lightweight logger with rotation and log levels.
 */
class Logger
{
    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO  = 'INFO';
    const LEVEL_WARN  = 'WARN';
    const LEVEL_ERROR = 'ERROR';

    private $logFile;
    private $minLevelOrder;
    private $levelToOrder;
    private $maxSizeBytes;
    private $maxFiles;

    public function __construct($logFile = 'log.txt', $minLevel = self::LEVEL_DEBUG, $maxSizeBytes = 1048576, $maxFiles = 5)
    {
        $this->logFile = $logFile;
        $this->levelToOrder = [
            self::LEVEL_DEBUG => 10,
            self::LEVEL_INFO  => 20,
            self::LEVEL_WARN  => 30,
            self::LEVEL_ERROR => 40,
        ];
        $this->minLevelOrder = $this->levelToOrder[$minLevel] ?? 20;
        $this->maxSizeBytes = $maxSizeBytes; // 1 MB default
        $this->maxFiles = $maxFiles;         // keep 5 files: log.txt, log.txt.1..4
    }

    /**
     * Backward compatible: info-level log
     */
    public function log($message)
    {
        $this->write(self::LEVEL_INFO, $message);
    }

    public function debug($message)
    {
        $this->write(self::LEVEL_DEBUG, $message);
    }

    public function info($message)
    {
        $this->write(self::LEVEL_INFO, $message);
    }

    public function warn($message)
    {
        $this->write(self::LEVEL_WARN, $message);
    }

    public function error($message)
    {
        $this->write(self::LEVEL_ERROR, $message);
    }

    /**
     * Brief log of incoming message data
     */
    public function logMessage($messageData)
    {
        $chatId = $messageData['chat']['id'] ?? 'unknown';
        $threadId = $messageData['message_thread_id'] ?? '—';
        $messageId = $messageData['message_id'] ?? '—';
        $userId = $messageData['from']['id'] ?? '—';
        $text = $messageData['text'] ?? ($messageData['caption'] ?? '');
        $text = $this->truncate($text, 200);
        $types = [];
        foreach (['text','photo','document','sticker','audio','video'] as $t) {
            if (isset($messageData[$t])) { $types[] = $t; }
        }
        $typesStr = $types ? implode(',', $types) : 'unknown';

        $this->info("MSG chat={$chatId} thread={$threadId} msg={$messageId} user={$userId} types={$typesStr} text=\"{$text}\"");
    }

    /**
     * Log order detection process (brief)
     */
    public function logOrderDetection($service, $userName, $userId, $text)
    {
        $this->info("ORDER_DETECT start service={$service} user={$userName}({$userId}) text=\"" . $this->truncate($text, 200) . "\"");
    }

    /**
     * Log API call results (brief)
     */
    public function logApiResult($service, $result)
    {
        if (is_array($result)) {
            if (isset($result['error'])) {
                $this->warn("AI {$service} error=" . $this->truncate(json_encode($result, JSON_UNESCAPED_UNICODE), 300));
                return;
            }
            $keys = implode(',', array_slice(array_keys($result), 0, 5));
            $this->debug("AI {$service} result_keys=[{$keys}]");
        } else {
            $this->debug("AI {$service} result_type=" . gettype($result));
        }
    }

    /**
     * Log detected orders
     */
    public function logDetectedOrder($service)
    {
        $this->info("ORDER_DETECT matched_by={$service}");
    }

    public function endOrderDetection()
    {
        $this->info("ORDER_DETECT end");
    }

    private function write($level, $message)
    {
        if (($this->levelToOrder[$level] ?? 100) < $this->minLevelOrder) {
            return;
        }
        $this->rotateIfNeeded();
        $timestamp = date('d.m.Y H:i:s');
        $pid = getmypid();
        $line = "[{$timestamp}] {$level} [pid={$pid}] {$message}\n";
        @file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }

    private function rotateIfNeeded()
    {
        clearstatcache(true, $this->logFile);
        if (!file_exists($this->logFile)) {
            return;
        }
        $size = filesize($this->logFile);
        if ($size === false || $size < $this->maxSizeBytes) {
            return;
        }

        for ($i = $this->maxFiles - 1; $i >= 1; $i--) {
            $src = $this->logFile . ($i === 1 ? '' : '.' . ($i - 1));
            $dst = $this->logFile . '.' . $i;
            if (file_exists($src)) {
                @rename($src, $dst);
            }
        }
        // Finally move current to .1 and create new file
        @rename($this->logFile, $this->logFile . '.1');
    }

    private function truncate($string, $maxLen)
    {
        $string = (string)$string;
        if (mb_strlen($string, 'UTF-8') <= $maxLen) {
            return $string;
        }
        return mb_substr($string, 0, $maxLen, 'UTF-8') . '…';
    }
}
