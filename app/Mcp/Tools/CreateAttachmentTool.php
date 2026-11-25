<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\FormatsAttachments;
use App\Services\AttachmentService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\File;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('create-attachment')]
#[Title('Csatolmány létrehozása fájlból')]
#[Description('Egy lemezen lévő fájlt csatolmányként elment a projekthez: fizikailag a privát lemezre másolja, és rögzíti az adatbázisba. Visszaadja a csatolmány azonosítóját és a tárolt abszolút útvonalát, amelyre hivatkozni lehet. A taskhoz kötéshez utána használd az attach-to-task eszközt.')]
class CreateAttachmentTool extends Tool
{
    use FormatsAttachments;

    public function handle(Request $request, AttachmentService $attachments): Response
    {
        $validated = $request->validate([
            'project' => ['required', 'string'],
            'file_path' => ['required', 'string'],
            'filename' => ['nullable', 'string', 'max:255'],
        ]);

        $project = $this->resolveProject($validated['project']);

        if ($project === null) {
            return Response::error("Nincs „{$validated['project']}” nevű projekt. Használd a list-projects eszközt a nevekhez.");
        }

        $path = $validated['file_path'];

        if (! File::isFile($path) || ! File::isReadable($path)) {
            return Response::error("A megadott fájl nem olvasható: {$path}");
        }

        $attachment = $attachments->store(
            $project,
            (string) File::get($path),
            $validated['filename'] ?? basename($path),
            File::mimeType($path) ?: null,
        );

        return Response::json([
            'message' => 'Csatolmány létrehozva.',
            'attachment' => $this->attachmentToArray($attachment),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()
                ->description('A projekt neve (vagy azonosítója), amelyhez a csatolmány tartozik.')
                ->required(),
            'file_path' => $schema->string()
                ->description('Az elmentendő fájl abszolút elérési útja a lemezen.')
                ->required(),
            'filename' => $schema->string()
                ->description('Opcionális megjelenített fájlnév. Alapértelmezetten a forrásfájl neve.'),
        ];
    }
}
