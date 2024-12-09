<?php

namespace Tolery\AiCad\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tolery\AiCad\Models\Chat;
use ZipArchive;

class GetAICADResponse implements ShouldQueue
{
    use Dispatchable;

    public function __construct(public Chat $chat, public string $message){}

    /**
     * @throws ConnectionException
     */
    public function handle(): void
    {

        $objName  = 'file-' . uuid_create();

        $args = [
            'image_path' => '',
            'message' => $this->message,
            'part_file_name' => $objName,
        ];

        if( $this->chat->session_id) {
            $args['session_id'] = $this->chat->session_id;
        }

        $response = Http::timeout(60)
            ->post(config('ai-cad.api-url').'/image_chat_to_cad', $args);

        Log::info('ai-cad response : ' . $response->body());

        if ($response->successful()) {
            $chatResponse = json_decode($response->body());
            if(!$this->chat->session_id){
                $this->chat->session_id = $chatResponse->response->session_id;
                $this->chat->save();
            }


            $objPath = null;

            if($chatResponse->response->obj_export){
                try {
                    File::ensureDirectoryExists(Storage::path('ai-cad/responses'));
                    File::ensureDirectoryExists(Storage::path('ai-cad/files'));

                    $responsePathZip = Storage::path('ai-cad/responses/chat-'.$this->chat->id .'.zip' );
                    $objPath = 'ai-cad/files/'. $objName .'.obj';

                    Http::withBasicAuth(config('ai-cad.onshape.access-key'), config('ai-cad.onshape.secret-key'))
                        ->sink($responsePathZip)
                        ->get($chatResponse->response->obj_export->link);

                    Log::info('ai-cad download file');


                    $zip = new ZipArchive;
                    $zip->open($responsePathZip);
                    $zip->extractTo(Storage::path('ai-cad/responses/chat-'.$this->chat->id));
                    $zip->close();

                    $hasMove = Storage::move(
                        'ai-cad/responses/chat-'.$this->chat->id . '/Part Studio 1.obj',
                        $objPath
                    );

                    Log::info('ai-cad mouved file : ' . $hasMove ? 'true' : 'false');

                } catch (\Exception $e ){
                    Log::error($e->getMessage());
                }
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
            Log::error( $response->body());

        }
    }
}
