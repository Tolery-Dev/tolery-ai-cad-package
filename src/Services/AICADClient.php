<?php

namespace Tolery\AiCad\Services;

use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

readonly class AICADClient
{
    /**
     * POST /api/generate-cad-stream — SSE progress + final payload
     * Events (exemples reçus):
     *  - {"step":"analysis","status":"Analyzing user requirements...","overall_percentage":10,...}
     *  - ...
     *  - {"final_response":{"chat_response":"...","obj_export":"...","step_export":"...","tessellated_export":null,"attribute_and_transientid_map":null,"manufacturing_errors":[]}}
     *
     * Yields:
     *  - ['type'=>'progress', 'step'=>string, 'status'=>string, 'message'=>string, 'overall_percentage'=>int]
     *  - ['type'=>'final', 'final_response'=>array{chat_response?:string,obj_export?:string,step_export?:string,tessellated_export?:?string,attribute_and_transientid_map?:?string,manufacturing_errors?:array}]
     *
     * @throws ConnectionException
     */
    public function generateCadStream(string $message, ?string $projectId = null, int $timeoutSec = 180): Generator
    {
        $url = $this->endpoint('/api/generate-cad-stream');

        $resp = $this->http($timeoutSec)
            ->withHeaders(['Accept' => 'text/event-stream'])
            ->withOptions(['stream' => true])
            ->post($url, [
                'message' => $message,
                'project_id' => $projectId,
                'stream' => true,
            ]);

        $psr = $resp->toPsrResponse();
        $body = $psr->getBody();
        $buffer = '';

        while (! $body->eof()) {
            $chunk = $body->read(8192);
            if ($chunk === '') {
                usleep(10_000);

                continue;
            }
            $buffer .= $chunk;

            // SSE: paquets séparés par \n\n
            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $packet = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                foreach (explode("\n", $packet) as $line) {
                    $line = ltrim($line);
                    if ($line === '' || Str::startsWith($line, ':')) {
                        continue;
                    }
                    if (! Str::startsWith($line, 'data:')) {
                        continue;
                    }

                    $json = trim(substr($line, 5));
                    if ($json === '' || $json === '[DONE]') {
                        continue;
                    }

                    $payload = json_decode($json, true);
                    if (! is_array($payload)) {
                        continue;
                    }

                    if (isset($payload['final_response'])) {
                        yield [
                            'type' => 'final',
                            'final_response' => (array) $payload['final_response'],
                        ];

                        continue;
                    }

                    // Progress event
                    $step = (string) ($payload['step'] ?? '');
                    $status = (string) ($payload['status'] ?? '');
                    $msg = (string) ($payload['message'] ?? '');
                    $pct = (int) ($payload['overall_percentage'] ?? 0);

                    if ($step !== '' || $status !== '' || $msg !== '') {
                        yield [
                            'type' => 'progress',
                            'step' => $step,
                            'status' => $status,
                            'message' => $msg,
                            'overall_percentage' => $pct,
                        ];
                    }
                }
            }
        }
    }

    /* ---------- HTTP helpers ---------- */
    protected function http(int $timeoutSec): PendingRequest
    {
        $req = Http::timeout($timeoutSec)->acceptJson();
        $key = config('ai-cad.api.key', '');
        if ($key) {
            $req = $req->withToken($key);
        }

        return $req;
    }

    protected function endpoint(string $path): string
    {
        return rtrim($this->baseUrl(), '/').'/'.ltrim($path, '/');
    }

    protected function baseUrl(): string
    {
        return rtrim(config('ai-cad.api.base_url', ''), '/');
    }
}
