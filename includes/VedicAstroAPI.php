<?php
declare(strict_types=1);

final class VedicAstroAPI
{
    private string $baseUrl;
    private string $apiKey;
    private int $timeout;
    private int $connectTimeout;

    public function __construct(
        string $baseUrl,
        string $apiKey,
        int $timeout = 15,
        int $connectTimeout = 5
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->timeout = max(5, $timeout);
        $this->connectTimeout = max(2, $connectTimeout);
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Execute a documented VedicAstroAPI v3-json route.
     * The browser never supplies or sees the API key.
     *
     * @param array<string,mixed> $params
     * @return array{ok:bool,status:int,data:array<string,mixed>|null,error:string|null}
     */
    public function request(string $route, string $method = 'GET', array $params = []): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'VedicAstroAPI is not configured.'];
        }

        $route = '/' . ltrim($route, '/');
        $url = $this->baseUrl . $route;
        $params['api_key'] = $this->apiKey;
        $method = strtoupper($method);

        $ch = curl_init();
        if ($ch === false) {
            return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'Unable to initialize the API client.'];
        }

        if ($method === 'GET') {
            $url .= '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
            ],
        ]);

        if ($method !== 'GET') {
            try {
                $payload = json_encode($params, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                error_log('[ASTROPOP] API request encoding failed');
                curl_close($ch);
                return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'Could not prepare the astrology request.'];
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $body = curl_exec($ch);
        $curlError = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            error_log('[ASTROPOP] VedicAstroAPI cURL error: ' . $curlError);
            return ['ok' => false, 'status' => $status, 'data' => null, 'error' => 'Astrology service could not be reached.'];
        }

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            error_log('[ASTROPOP] VedicAstroAPI returned invalid JSON. HTTP ' . $status);
            return ['ok' => false, 'status' => $status, 'data' => null, 'error' => 'Astrology service returned an invalid response.'];
        }

        if (!is_array($data)) {
            return ['ok' => false, 'status' => $status, 'data' => null, 'error' => 'Astrology service returned an unexpected response.'];
        }

        if ($status < 200 || $status >= 300) {
            error_log('[ASTROPOP] VedicAstroAPI HTTP error: ' . $status);
            return ['ok' => false, 'status' => $status, 'data' => $data, 'error' => 'Astrology service returned an error.'];
        }

        return ['ok' => true, 'status' => $status, 'data' => $data, 'error' => null];
    }
}
