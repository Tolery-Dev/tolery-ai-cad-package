<?php
namespace Tolery\AiCad\Services;

use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

readonly class AICADClient
{
    public function __construct(
        private ?string $baseUrl = null,
        private ?string $apiKey  = null,
    ) {}

    /** POST /cad/chat_to_cad — one-shot (fallback non-stream) */
    public function chatToCad(array $messages, ?string $projectId = null, int $timeoutSec = 60): array
    {
        $res = $this->http($timeoutSec)->post(
            $this->endpoint('/cad/chat_to_cad'),
            [
                'messages'   => $messages,           // [['role'=>'user','content'=>'...'], ...]
                'project_id' => $projectId,
                // 'stream'   => false  // suivant le swagger si supporté
            ]
        )->throw()->json();

        return [
            'assistant_message' => (string) (Arr::get($res, 'assistant_message') ?? Arr::get($res, 'message', '')),
            'json_edges_url'    => Arr::get($res, 'json_edges_url') ?? Arr::get($res, 'json_edges'),
            'ui'                => Arr::get($res, 'ui', []),
            'meta'              => Arr::get($res, 'meta', []),
        ];
    }

    /**
     * POST /cad/chat_to_cad — streaming (SSE/JSONL/ndjson)
     * Yields:
     *  - ['type'=>'delta','data'=>string]
     *  - ['type'=>'json_edges','url'=>string]
     *  - ['type'=>'result','assistant_message'=>string,'json_edges_url'=>?string]
     * @throws ConnectionException
     */
    public function chatToCadStream(array $messages, ?string $projectId = null, int $timeoutSec = 60): Generator
    {
        $resp = $this->http($timeoutSec)
            ->withOptions(['stream' => true])
            ->post($this->endpoint('/cad/chat_to_cad'), [
                'messages'   => $messages,
                'project_id' => $projectId,
                'stream'     => true,
            ]);

        $psr  = $resp->toPsrResponse();
        $body = $psr->getBody();
        $buffer = '';

        while (!$body->eof()) {
            $chunk = $body->read(8192);
            if ($chunk === '') { usleep(10_000); continue; }
            $buffer .= $chunk;

            // Mode SSE: lignes "data: {...}\n\n"
            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $packet = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                foreach (explode("\n", $packet) as $line) {
                    $line = ltrim($line);
                    if ($line === '' || Str::startsWith($line, ':')) continue;
                    if (!Str::startsWith($line, 'data:')) continue;

                    $json = trim(substr($line, 5));
                    if ($json === '' || $json === '[DONE]') continue;

                    $payload = json_decode($json, true);
                    if (!is_array($payload)) continue;

                    if (isset($payload['delta'])) {
                        yield ['type' => 'delta', 'data' => (string) $payload['delta']];
                    }
                    if (isset($payload['json_edges_url'])) {
                        yield ['type' => 'json_edges', 'url' => (string) $payload['json_edges_url']];
                    }
                    if (isset($payload['assistant_message']) || isset($payload['done'])) {
                        yield [
                            'type'              => 'result',
                            'assistant_message' => (string) ($payload['assistant_message'] ?? ''),
                            'json_edges_url'    => $payload['json_edges_url'] ?? null,
                        ];
                    }
                }
            }

            // Mode NDJSON / JSONL: lignes JSON terminées par \n
            while (($nl = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $nl));
                $buffer = substr($buffer, $nl + 1);
                if ($line === '' || $line === '[DONE]') continue;

                $payload = json_decode($line, true);
                if (!is_array($payload)) continue;

                if (isset($payload['delta'])) {
                    yield ['type' => 'delta', 'data' => (string) $payload['delta']];
                }
                if (isset($payload['json_edges_url'])) {
                    yield ['type' => 'json_edges', 'url' => (string) $payload['json_edges_url']];
                }
                if (isset($payload['assistant_message']) || isset($payload['done'])) {
                    yield [
                        'type'              => 'result',
                        'assistant_message' => (string) ($payload['assistant_message'] ?? ''),
                        'json_edges_url'    => $payload['json_edges_url'] ?? null,
                    ];
                }
            }
        }

        // Fallback: si un JSON complet est resté en buffer
        $rest = trim($buffer);
        if ($rest !== '') {
            $payload = json_decode($rest, true);
            if (is_array($payload)) {
                yield [
                    'type'              => 'result',
                    'assistant_message' => (string) ($payload['assistant_message'] ?? $payload['message'] ?? ''),
                    'json_edges_url'    => $payload['json_edges_url'] ?? $payload['json_edges'] ?? null,
                ];
            }
        }
    }

    /* ---------- HTTP helpers ---------- */
    protected function http(int $timeoutSec): PendingRequest
    {
        $req = Http::timeout($timeoutSec)->acceptJson();
        $key = $this->apiKey ?? config('ai-cad.api.key') ?? env('AICAD_API_KEY');
        if ($key) $req = $req->withToken($key);
        return $req;
    }

    protected function endpoint(string $path): string
    {
        return rtrim($this->baseUrl(), '/') . '/' . ltrim($path, '/');
    }

    protected function baseUrl(): string
    {
        return rtrim($this->baseUrl ?? config('ai-cad.api.base_url') ?? env('AICAD_BASE_URL', ''), '/');
    }
}
