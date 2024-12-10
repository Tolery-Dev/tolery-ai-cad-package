<?php

namespace Tolery\AiCad\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\TemporaryDirectory\Exceptions\PathAlreadyExists;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Tolery\AiCad\Models\Chat;
use ZipArchive;

class GetAICADResponse implements ShouldQueue
{
    use Dispatchable;

    public function __construct(public Chat $chat, public string $message) {}

    /**
     * @throws ConnectionException|PathAlreadyExists
     */
    public function handle(): void
    {

        $objName = 'file-'. uuid_create();

        $args = [
            'image_path' => '',
            'message' => $this->message,
            'part_file_name' => $objName,
        ];

        if ($this->chat->session_id) {
            $args['session_id'] = $this->chat->session_id;
        }

        $response = Http::timeout(60)
            ->post(config('ai-cad.api-url').'/image_chat_to_cad', $args);

        Log::info('ai-cad response : '.$response->body());

        if ($response->successful()) {
            $chatResponse = json_decode($response->body());
            if (! $this->chat->session_id) {
                $this->chat->session_id = $chatResponse->response->session_id;
                $this->chat->save();
            }

            $objPath = null;

            if ($chatResponse->response->obj_export) {

                $objPath = $this->unzipObjFile($chatResponse->response->obj_export->link, $objName);

            } else {
                Log::info('Pas encore de fichier à télécharger');
            }

            $this->chat->messages()->create([
                'message' => $chatResponse->response->chat_response,
                'ai_cad_path' => $objPath,
            ]);
        } else {
            $this->chat->messages()->create([
                'message' => $response->body(),
            ]);
            Log::error($response->body());

        }
    }


    /**
     * @throws PathAlreadyExists
     */
    private function unzipObjFile(string $objUrl, string $objName): string
    {

        $tmpDir = (new TemporaryDirectory())
            ->deleteWhenDestroyed()
            ->force()
            ->create();

        $tmpFile = "chat-{$this->chat->id}.zip";
        $tmpPath = $tmpDir->path($tmpFile);

        try {
            Http::withBasicAuth(config('ai-cad.onshape.access-key'), config('ai-cad.onshape.secret-key'))
                ->sink($tmpPath)
                ->get($objUrl);

        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }


        Log::info('ai-cad download file');

        $unzipPath = $tmpDir->path("chat-{$this->chat->id}");
        $zip = new ZipArchive;
        $zip->open($tmpPath);
        $zip->extractTo($unzipPath);
        $zip->close();

        $objPathDir = 'ai-cad/' . now()->format('Y-m');
        File::ensureDirectoryExists(Storage::path($objPathDir));

        $objPath = $objPathDir . '/' . $objName . '.obj';

        Storage::put(
            $objPath,
            File::get($unzipPath.'/Part Studio 1.obj')
        );

        return $objPath;
    }
}
