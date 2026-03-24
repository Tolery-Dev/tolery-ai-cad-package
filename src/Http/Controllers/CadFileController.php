<?php

namespace Tolery\AiCad\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Tolery\AiCad\Models\ChatMessage;

class CadFileController extends Controller
{
    /**
     * Sert le fichier JSON d'arêtes d'un message CAO depuis le Storage.
     * Évite les problèmes CORS et d'accessibilité directe des URLs Storage ou API externe.
     */
    public function serveJson(int $messageId): Response
    {
        $message = ChatMessage::findOrFail($messageId);

        if (! $message->ai_json_edge_path) {
            abort(404);
        }

        // Si c'est une URL externe, proxifie avec le token API
        if (filter_var($message->ai_json_edge_path, FILTER_VALIDATE_URL)) {
            $apiKey = config('ai-cad.api.key');
            $upstream = Http::when($apiKey, fn ($req) => $req->withToken($apiKey))
                ->timeout(30)
                ->get($message->ai_json_edge_path);

            abort_unless($upstream->successful(), 502);

            return response($upstream->body(), 200, ['Content-Type' => 'application/json']);
        }

        // Fichier local dans Storage
        abort_unless(Storage::exists($message->ai_json_edge_path), 404);

        return response(Storage::get($message->ai_json_edge_path), 200, [
            'Content-Type' => 'application/json',
        ]);
    }
}
