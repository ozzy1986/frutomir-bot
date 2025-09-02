<?php

namespace FrutoMir\Config;

/**
 * Configuration class for managing bot settings and API keys
 * Follows PSR-4 autoloading standard
 */
class Configuration
{
    private $telegramToken;
    private $mistralApiKey;
    private $yandexIamToken;
    private $yandexFolderId;
    private $targetGroupChatId;
    private $notificationChatId;
    private $ollamaUrl;
    private $topicNames;

    public function __construct()
    {
        $this->telegramToken = '8487179037:AAHK9c6xF--3HJRrVnNyYpB30h8kMI_bcgo';
        $this->mistralApiKey = 'lSCBzobQL9c6XLmwIR1mWy6OLQTA2TY1';
        $this->yandexIamToken = 't1.9euelZqRlomNicaJnomRyZ3JjIuQyu3rnpWaipuXxsqTzI-PzJ3Ons_Nzpbl8_dUOz86-e9RDDwe_d3z9xRqPDr571EMPB79zef1656VmpDIy8nLlJmZx5WLlZSNkY2J7_zF656VmpDIy8nLlJmZx5WLlZSNkY2J.px4ci35693R7NbhJoz6j0aVsbaRfrdMgu-Q0cN5X88ex7KwKJdmoRCOWd_egyQUAl4mZ2j4TQuwbOtpOFS_2Bw';
        $this->yandexFolderId = 'b1gsk50sqi33190v0vem';
        $this->targetGroupChatId = -1002038562353;
        $this->notificationChatId = 503810348;
        $this->ollamaUrl = 'http://192.166.101.61:114/api/chat';
        
        $this->topicNames = [
            2   => 'Домашний виноград',
            4   => 'Макаронные изделия, крупы, мука',
            6   => 'Конфеты',
            8   => 'Соль, сахар, уксус, пакеты, салфетки',
            10  => 'Кондитерские изделия',
            12  => 'Консервы, соленья, масла растительные',
            14  => 'Молочные продукты, яйца и мороженое',
            16  => 'Морепродукты и полуфабрикаты',
            18  => 'Вода, напитки, пиво',
            20  => 'Специи, соусы',
            23  => 'Чай, кофе, какао',
            25  => 'Овощи и фрукты',
            240 => 'Мёд, орешки, сухофрукты, чипсы, семечки',
        ];
    }

    public function getTelegramToken()
    {
        return $this->telegramToken;
    }

    public function getMistralApiKey()
    {
        return $this->mistralApiKey;
    }

    public function getYandexIamToken()
    {
        return $this->yandexIamToken;
    }

    public function getYandexFolderId()
    {
        return $this->yandexFolderId;
    }

    public function getTargetGroupChatId()
    {
        return $this->targetGroupChatId;
    }

    public function getNotificationChatId()
    {
        return $this->notificationChatId;
    }

    public function getOllamaUrl()
    {
        return $this->ollamaUrl;
    }

    public function getTopicNames()
    {
        return $this->topicNames;
    }

    public function getTopicName($threadId)
    {
        return $this->topicNames[$threadId] ?? null;
    }

    public function getTelegramApiUrl()
    {
        return "https://api.telegram.org/bot{$this->telegramToken}";
    }
}
