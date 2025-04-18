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
use Tolery\AiCad\Enum\MaterialFamily;
use Tolery\AiCad\Models\Chat;
use ZipArchive;

class GetAICADResponse implements ShouldQueue
{
    use Dispatchable;

    public function __construct(public Chat $chat, public string $message, public ?string $pdfUrl)
    {
        File::ensureDirectoryExists(Storage::path($chat->getStorageFolder()));
    }

    /**
     * @throws ConnectionException|PathAlreadyExists
     */
    public function handle(): void
    {

        $objName = 'file-'.uuid_create();

        $args = [
            'image_path' => $this->pdfUrl ?? '',
            'message' => $this->message,
            'material_choice' => $this->chat->material_family->value ?? MaterialFamily::STEEL->value,
            'part_file_name' => $objName,
            'export_format' => 'obj',
        ];

        if ($this->chat->session_id) {
            $args['session_id'] = $this->chat->session_id;
        }

        $response = Http::timeout(60)
            ->post(config('ai-cad.api-url').'/chat_to_cad', $args);

        Log::info('ai-cad response : '.$response->body());

        if ($response->successful()) {
            $chatResponse = json_decode($response->body());
            if (! $this->chat->session_id) {
                $this->chat->session_id = $chatResponse->session_id;
                $this->chat->save();
            }

            $objPath = null;
            $jsonEdgePath = null;

            if ($chatResponse->obj_export) {

                $objPath = $this->unzipObjFile($chatResponse->obj_export, $objName);

            } else {
                Log::info('Pas encore de fichier à télécharger');
            }

            if ($chatResponse->tessellated_export) {

                $jsonEdgePath = $this->chat->getStorageFolder().'/'.$objName.'.json';
                Storage::put($jsonEdgePath, json_encode($chatResponse->tessellated_export));

            } else {
                Log::info('Pas encore de fichier à télécharger');
            }

            $this->chat->messages()->create([
                'message' => $chatResponse->chat_response,
                'ai_cad_path' => $objPath,
                'ai_json_edge_path' => $jsonEdgePath,
                'edge_object_map_id' => $chatResponse->attribute_and_transientid_map,
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

        $tmpDir = (new TemporaryDirectory)
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

        $objPath = $this->chat->getStorageFolder().'/'.$objName.'.obj';

        Storage::put(
            $objPath,
            File::get($unzipPath.'/Part Studio 1.obj')
        );

        return $objPath;
    }
}
