<?php

namespace FrutoMir\AI;

use FrutoMir\Config\Configuration;

/**
 * Order detection using multiple AI services
 * Implements Strategy pattern for different AI providers
 */
class OrderDetector
{
    private $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * Detect order using Mistral AI
     */
    public function detectWithMistral($messageText)
    {
        $url = "https://api.mistral.ai/v1/chat/completions";

        $prompt = <<<EOT
Define if the message is a products order and reply in JSON format:
{
  "is_order": true/false,
  "items": ["1. Product name 1", "2. Product name 2", ...]
}

Message: "{$messageText}"
EOT;

        $payload = [
            "model" => "mistral-small-latest",
            "messages" => [
                ["role" => "user", "content" => $prompt]
            ]
        ];

        return $this->makeCurlRequest($url, $payload, [
            "Authorization: Bearer " . $this->config->getMistralApiKey(),
            "Content-Type: application/json"
        ]);
    }

    /**
     * Detect order using Yandex AI
     */
    public function detectWithYandex($messageText)
    {
        $url = "https://llm.api.cloud.yandex.net/foundationModels/v1/completion";

        $payload = [
            "modelUri" => "gpt://" . $this->config->getYandexFolderId() . "/yandexgpt",
            "completionOptions" => [
                "stream" => false,
                "temperature" => 0.6,
                "maxTokens" => "2000",
                "reasoningOptions" => [
                    "mode" => "DISABLED"
                ]
            ],
            "messages" => [
                [
                    "role" => "system",
                    "text" => 'Define if the message is a products order and reply in JSON format: { "is_order": true/false, "items": ["1. Product name 1", "2. Product name 2", ...] }'
                ],
                [
                    "role" => "user",
                    "text" => $messageText
                ]
            ]
        ];

        return $this->makeCurlRequest($url, $payload, [
            "Authorization: Bearer " . $this->config->getYandexIamToken(),
            "Content-Type: application/json"
        ]);
    }

    /**
     * Detect order using Ollama
     */
    public function detectWithOllama($messageText)
    {
        $url = $this->config->getOllamaUrl();

        $systemPrompt = "You are an assistant that analyzes chat messages.
If the message is a grocery order, extract items as a numbered list of ordered products.
Return result as valid JSON.
If the message is not an order, respond exactly with: null";

        $data = [
            "model" => "gemma3:4b",
            "messages" => [
                ["role" => "system", "content" => $systemPrompt],
                ["role" => "user", "content" => $messageText]
            ],
            "stream" => false
        ];

        $options = [
            "http" => [
                "header"  => "Content-type: application/json\r\n",
                "method"  => "POST",
                "content" => json_encode($data),
                "timeout" => 60
            ]
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);

        if ($result === false) {
            return null;
        }

        $response = json_decode($result, true);

        if (!isset($response["message"]["content"])) {
            return null;
        }

        $content = trim($response["message"]["content"]);

        if ($content === "null") {
            return null;
        }

        $parsed = json_decode($content, true);
        return is_array($parsed) ? $parsed : null;
    }

    /**
     * Make a cURL request to AI service
     */
    private function makeCurlRequest($url, $payload, $headers)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);

        $raw = curl_exec($ch);

        if ($raw === false) {
            return ["error" => curl_error($ch)];
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return ["error" => "API error HTTP {$httpCode}", "raw" => $raw];
        }

        $data = json_decode($raw, true);
        return $this->parseAiResponse($data, $raw);
    }

    /**
     * Parse AI response and extract JSON
     */
    private function parseAiResponse($data, $raw)
    {
        // Try to get content from different response structures
        $generated = $data["choices"][0]["message"]["content"] 
                  ?? $data["result"]["alternatives"][0]["message"]["text"] 
                  ?? null;

        if (!$generated) {
            return ["error" => "Unexpected AI response", "raw" => $data];
        }

        // Try parsing as direct JSON
        $json = json_decode($generated, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($json["is_order"])) {
            return $json;
        }

        // Fallback: extract JSON from text
        if (preg_match('/\{.*\}/us', $generated, $matches)) {
            $json = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && isset($json["is_order"])) {
                return $json;
            }
        }

        return ["error" => "No valid JSON", "generated" => $generated];
    }
}
