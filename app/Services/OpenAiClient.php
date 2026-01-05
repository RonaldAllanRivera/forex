<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiClient
{
    /**
     * @param  array<int, array{role:string, content:string}>  $messages
     * @return array{content:string, parsed: array<string, mixed>, raw: array<string, mixed>, model:string}
     */
    public function chatJson(array $messages, ?string $model = null): array
    {
        $baseUrl = (string) config('services.openai.base_url');
        $apiKey = (string) config('services.openai.key');
        $timeoutSeconds = (int) config('services.openai.timeout_seconds');
        $defaultModel = (string) config('services.openai.model');

        if ($baseUrl === '') {
            throw new RuntimeException('Missing OpenAI base URL. Set OPENAI_BASE_URL.');
        }

        if ($timeoutSeconds <= 0) {
            throw new RuntimeException('Invalid OpenAI timeout. Set OPENAI_TIMEOUT_SECONDS.');
        }

        if ($defaultModel === '') {
            throw new RuntimeException('Missing OpenAI model. Set OPENAI_MODEL.');
        }

        $baseUrl = rtrim($baseUrl, '/');
        $modelUsed = $model ?: $defaultModel;

        if ($apiKey === '') {
            throw new RuntimeException('Missing OpenAI API key. Set OPENAI_API_KEY.');
        }

        try {
            $response = Http::baseUrl($baseUrl)
                ->withToken($apiKey)
                ->retry(3, 300, throw: false)
                ->timeout($timeoutSeconds)
                ->acceptJson()
                ->post('/chat/completions', [
                    'model' => $modelUsed,
                    'temperature' => 0.2,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => $messages,
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('OpenAI connection error: '.$e->getMessage(), previous: $e);
        }

        if (! $response->successful()) {
            try {
                $response->throw();
            } catch (RequestException $e) {
                throw new RuntimeException('OpenAI request failed: '.$e->getMessage(), previous: $e);
            }
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException('Unexpected OpenAI response format.');
        }

        $content = $data['choices'][0]['message']['content'] ?? null;
        if (! is_string($content) || $content === '') {
            throw new RuntimeException('OpenAI returned empty content.');
        }

        try {
            $parsed = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException('OpenAI returned invalid JSON content: '.$e->getMessage(), previous: $e);
        }

        if (! is_array($parsed)) {
            throw new RuntimeException('OpenAI returned non-object JSON.');
        }

        return [
            'content' => $content,
            'parsed' => $parsed,
            'raw' => $data,
            'model' => $modelUsed,
        ];
    }
}
