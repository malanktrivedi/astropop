<?php
declare(strict_types=1);

require_once __DIR__ . '/AiProviderInterface.php';

final class OpenAIChatProvider implements AiProviderInterface
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;
    private int $timeout;
    private int $connectTimeout;

    public function __construct(
        ?string $apiKey = null,
        ?string $model = null,
        ?string $baseUrl = null,
        ?int $timeout = null,
        ?int $connectTimeout = null
    ) {
        $this->apiKey = trim((string) ($apiKey ?? env_value('OPENAI_API_KEY', '')));
        $this->model = trim((string) ($model ?? env_value('OPENAI_MODEL', 'gpt-5.6-luna')));
        $this->baseUrl = rtrim((string) ($baseUrl ?? env_value('OPENAI_API_BASE_URL', 'https://api.openai.com/v1')), '/');
        $this->timeout = max(10, (int) ($timeout ?? env_value('OPENAI_API_TIMEOUT', '30')));
        $this->connectTimeout = max(2, (int) ($connectTimeout ?? env_value('OPENAI_API_CONNECT_TIMEOUT', '10')));
    }

    /** @param array<string,mixed> $context @param array<int,array<string,mixed>> $history */
    public function chat(array $context, array $history, string $message): array
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('OpenAI API is not configured.');
        }

        $instructions = $this->buildInstructions($context);
        $input = [];
        foreach ($history as $item) {
            $role = ($item['sender_type'] ?? '') === 'user' ? 'user' : 'assistant';
            $body = trim((string) ($item['body'] ?? ''));
            if ($body === '') continue;
            $input[] = ['role' => $role, 'content' => $body];
        }
        $input[] = ['role' => 'user', 'content' => trim($message)];

        $payload = [
            'model' => $this->model,
            'instructions' => $instructions,
            'input' => $input,
        ];

        $response = $this->request('/responses', $payload);
        $reply = $this->extractText($response);
        if ($reply === '') {
            throw new RuntimeException('OpenAI returned an empty response.');
        }

        $usage = is_array($response['usage'] ?? null) ? $response['usage'] : [];
        return [
            'reply' => $reply,
            'model' => (string) ($response['model'] ?? $this->model),
            'input_tokens' => isset($usage['input_tokens']) ? (int) $usage['input_tokens'] : null,
            'output_tokens' => isset($usage['output_tokens']) ? (int) $usage['output_tokens'] : null,
            'raw' => $response,
        ];
    }

    /** @param array<string,mixed> $context @return string */
    private function buildInstructions(array $context): string
    {
        $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) $json = '{}';

        return <<<PROMPT
You are ASTROPOP, a Vedic astrology interpretation assistant.

Interpret the supplied Kundli data using Vedic astrology principles. Do not invent planetary positions, houses, dashas, yogas, degrees, dates, or other chart facts. Do not recalculate astronomy yourself when supplied chart data is available. If required data is missing, say so clearly.

Keep calculation facts separate from interpretation. Give practical, understandable answers and avoid presenting astrology as scientific certainty. For health, legal, financial, or other high-impact matters, provide appropriate caution and do not make definitive professional claims.

The following JSON is the user's saved ASTROPOP astrology context. Treat it as authoritative source data for this conversation:

{$json}
PROMPT;
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function request(string $path, array $payload): array
    {
        $ch = curl_init();
        if ($ch === false) throw new RuntimeException('Unable to initialize OpenAI client.');

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Accept: application/json',
                'Content-Type: application/json',
            ],
        ]);

        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            error_log('[ASTROPOP] OpenAI cURL error: ' . $curlError);
            throw new RuntimeException('AI service could not be reached.');
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            error_log('[ASTROPOP] OpenAI returned invalid JSON. HTTP ' . $status);
            throw new RuntimeException('AI service returned an invalid response.');
        }

        if (!is_array($data)) throw new RuntimeException('AI service returned an unexpected response.');
        if ($status < 200 || $status >= 300) {
            $message = is_array($data['error'] ?? null) ? (string) ($data['error']['message'] ?? '') : '';
            error_log('[ASTROPOP] OpenAI HTTP error: ' . $status . ($message !== '' ? ' - ' . $message : ''));
            throw new RuntimeException('AI service returned an error.');
        }
        return $data;
    }

    /** @param array<string,mixed> $response */
    private function extractText(array $response): string
    {
        if (isset($response['output_text']) && is_string($response['output_text'])) {
            return trim($response['output_text']);
        }

        $parts = [];
        foreach (($response['output'] ?? []) as $item) {
            if (!is_array($item)) continue;
            foreach (($item['content'] ?? []) as $content) {
                if (!is_array($content)) continue;
                if (($content['type'] ?? '') === 'output_text' && isset($content['text'])) {
                    $parts[] = (string) $content['text'];
                }
            }
        }
        return trim(implode("\n", $parts));
    }
}
