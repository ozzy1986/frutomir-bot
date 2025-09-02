<?php

namespace FrutoMir\Message;

use FrutoMir\Config\Configuration;
use FrutoMir\Telegram\TelegramApi;
use FrutoMir\Logger\Logger;
use FrutoMir\AI\OrderDetector;

/**
 * Handles different types of messages and their processing
 * Implements Single Responsibility and Open/Closed principles
 */
class MessageHandler
{
    private $config;
    private $telegramApi;
    private $logger;
    private $orderDetector;

    public function __construct(
        $config,
        $telegramApi,
        $logger,
        $orderDetector
    ) {
        $this->config = $config;
        $this->telegramApi = $telegramApi;
        $this->logger = $logger;
        $this->orderDetector = $orderDetector;
    }

    /**
     * Process incoming message
     */
    public function handleMessage($messageData)
    {
        $this->logger->logMessage($messageData);

        $messageThreadId = $messageData['message_thread_id'] ?? null;
        $userId = $messageData['from']['id'];
        $groupChatId = $messageData['chat']['id'];

        // Handle admin reposts to target group
        if ($messageThreadId !== null) {
            $this->handleAdminRepost($messageData, $userId, $groupChatId);
            $this->handleUserMessageMoving($messageData, $userId, $groupChatId, $messageThreadId);
        }

        // Handle order detection for non-admin users and bot
        $this->handleOrderDetection($messageData, $userId, $groupChatId);
    }

    /**
     * Handle admin message reposts to target group
     */
    private function handleAdminRepost($messageData, $userId, $groupChatId)
    {
        $userStatus = $this->getUserStatus($userId, $groupChatId);

        if (!in_array($userStatus, ['creator', 'administrator'])) {
            return;
        }

        $text = htmlspecialchars($messageData['text'] ?? '');
        $params = [
            'chat_id' => $this->config->getTargetGroupChatId(),
            'text' => $text,
            'parse_mode' => 'HTML'
        ];

        $this->sendMessageByType($messageData, $params);
    }

    /**
     * Handle moving user messages from topics to main chat
     */
    private function handleUserMessageMoving($messageData, $userId, $groupChatId, $messageThreadId)
    {
        $userStatus = $this->getUserStatus($userId, $groupChatId);

        // Exit if admin posted - don't move admin messages
        if (in_array($userStatus, ['creator', 'administrator'])) {
            exit;
        }

        // if the message is in main topic (Заказы/Отзывы), then do nothing
        if ($messageThreadId === null) {
            exit;
        }

        $this->logger->log("Moving message from topic to main chat");
        // Delete original message
        $this->telegramApi->deleteMessage($groupChatId, $messageData['message_id']);

        // Repost to main chat
        $this->repostToMainChat($messageData, $userId, $groupChatId, $messageThreadId);

        // Send notification to user
        $this->sendUserNotification($messageData, $userId);

        // Process with AI order detection
        $this->processOrderDetectionForMovedMessage($messageData);
    }

    /**
     * Handle order detection for eligible users
     */
    private function handleOrderDetection($messageData, $userId, $groupChatId)
    {
        $userStatus = $this->getUserStatus($userId, $groupChatId);
        $isBot = $messageData['from']['is_bot'] ?? false;

        // Check orders for non-admins or bot messages
        if (in_array($userStatus, ['creator', 'administrator']) && !$isBot) {
            return;
        }

        $text = $messageData['text'] ?? '';
        if (empty($text)) {
            return;
        }

        $userName = $this->getUserName($messageData['from']);
        $this->logger->logOrderDetection('ALL_SERVICES', $userName, $userId, $text);

        $this->detectOrderWithAllServices($text, $userName);
        $this->logger->endOrderDetection();
    }

    /**
     * Get user status in chat
     */
    private function getUserStatus($userId, $chatId)
    {
        $data = $this->telegramApi->getChatMember($chatId, $userId);
        return $data['result']['status'] ?? 'member';
    }

    /**
     * Get user full name
     */
    private function getUserName($userInfo)
    {
        $firstName = $userInfo['first_name'] ?? '';
        $lastName = $userInfo['last_name'] ?? '';
        return trim("{$firstName} {$lastName}");
    }

    /**
     * Send message based on its type (text, photo, document, sticker)
     */
    private function sendMessageByType($messageData, $baseParams)
    {
        if (isset($messageData['photo'])) {
            $fileId = end($messageData['photo'])['file_id'];
            $params = $baseParams;
            $params['photo'] = $fileId;
            $params['caption'] = $baseParams['text'] . "\n\n" . ($messageData['caption'] ?? '');
            unset($params['text']);
            $this->telegramApi->sendPhoto($params);
        } elseif (isset($messageData['document'])) {
            $params = $baseParams;
            $params['document'] = $messageData['document']['file_id'];
            $params['caption'] = $baseParams['text'] . "\n\n" . ($messageData['caption'] ?? '');
            unset($params['text']);
            $this->telegramApi->sendDocument($params);
        } elseif (isset($messageData['sticker'])) {
            $params = $baseParams;
            $params['sticker'] = $messageData['sticker']['file_id'];
            unset($params['text']);
            $this->telegramApi->sendSticker($params);
        } elseif (isset($messageData['text'])) {
            $this->telegramApi->sendMessage($baseParams);
        }
    }

    /**
     * Repost message to main chat with topic information
     */
    private function repostToMainChat($messageData, $userId, $groupChatId, $messageThreadId)
    {
        $topicName = $this->getTopicName($messageThreadId, $groupChatId);
        $userName = $this->getUserName($messageData['from']);
        $username = $messageData['from']['username'] ?? '';
        
        $forwardText = "<b>Message from <a href=\"tg://user?id={$userId}\">{$userName}</a>";
        if ($username) {
            $forwardText .= " <a href=\"https://t.me/{$username}\">@{$username}</a>";
        }
        $forwardText .= " {$topicName}:</b>\n\n" . htmlspecialchars($messageData['text'] ?? '');

        $params = [
            'chat_id' => $groupChatId,
            'text' => $forwardText,
            'parse_mode' => 'HTML'
        ];

        $this->sendMessageByType($messageData, $params);
    }

    /**
     * Send notification to user about message moving
     */
    private function sendUserNotification($messageData, $userId)
    {
        $privateText = "Ваше сообщение было перемещено в тему заказов и отзывов, вы должны писать там.\n\nСообщение:\n\n" 
                      . htmlspecialchars($messageData['text'] ?? $messageData['caption'] ?? '');

        $params = [
            'chat_id' => $userId,
            'text' => $privateText
        ];

        $this->sendMessageByType($messageData, $params);
    }

    /**
     * Get topic name by thread ID
     */
    private function getTopicName($threadId, $chatId)
    {
        // Try to get from configuration first
        $topicName = $this->config->getTopicName($threadId);
        if ($topicName) {
            return "from topic <b>{$topicName}</b>";
        }

        // Try to get from API
        $data = $this->telegramApi->getForumTopicById($chatId, $threadId);
        $apiTopicName = $data['result']['name'] ?? null;
        
        return $apiTopicName ? "from topic <b>{$apiTopicName}</b>" : "from topic";
    }

    /**
     * Process order detection for moved messages (legacy functionality)
     */
    private function processOrderDetectionForMovedMessage($messageData)
    {
        $text = $messageData['text'] ?? '';
        if (empty($text)) {
            return;
        }

        $userName = $this->getUserName($messageData['from']);
        $this->detectOrderWithAllServices($text, $userName);
    }

    /**
     * Detect orders using all available AI services
     */
    private function detectOrderWithAllServices($text, $userName)
    {
        // Mistral detection
        $this->logger->logApiResult('mistral', []);
        $result = $this->orderDetector->detectWithMistral($text);
        $this->logger->logApiResult('mistral', $result);
        
        if ($this->isValidOrder($result)) {
            $this->logger->logDetectedOrder('MISTRAL');
            $this->sendOrderNotification('Mistral', $userName, $result['items']);
        }

        // Ollama detection
        $this->logger->logApiResult('ollama', []);
        $result = $this->orderDetector->detectWithOllama($text);
        $this->logger->logApiResult('ollama', $result ?? []);
        
        if ($this->isValidOrder($result)) {
            $this->logger->logDetectedOrder('OLLAMA');
            $this->sendOrderNotification('Ollama', $userName, $result['items']);
        }

        // Yandex detection
        $this->logger->logApiResult('yandex', []);
        $result = $this->orderDetector->detectWithYandex($text);
        $this->logger->logApiResult('yandex', $result);
        
        if ($this->isValidOrder($result)) {
            $this->logger->logDetectedOrder('YANDEX');
            $this->sendOrderNotification('Yandex', $userName, $result['items']);
        }
    }

    /**
     * Check if AI result indicates a valid order
     */
    private function isValidOrder($result)
    {
        return $result 
            && !empty($result['is_order']) 
            && !empty($result['items']) 
            && is_array($result['items']);
    }

    /**
     * Send order notification to admin
     */
    private function sendOrderNotification($service, $userName, $items)
    {
        $text = "🛒 ЗАКАЗ ОБНАРУЖЕН ({$service})!\n\nОт: {$userName}\n\n" . implode("\n", $items);
        
        $this->telegramApi->sendMessage([
            'chat_id' => $this->config->getNotificationChatId(),
            'text' => $text
        ]);
    }
}
