<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\FormatsAttachments;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('delete-attachment')]
#[Title('Csatolmány törlése')]
#[Description('Végleg törli a csatolmányt: a fizikai fájlt és az adatbázis-sort is, a feladat- és levél-kötésekkel együtt. Figyelem: a docs/attachments mappából szinkronizált (source=sync) fájlt a következő sync újra létrehozza, ha a forrásfájl megmarad.')]
class DeleteAttachmentTool extends Tool
{
    use FormatsAttachments;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'attachment_id' => ['required', 'integer'],
        ]);

        $attachment = $this->resolveAttachment($validated['attachment_id']);

        if ($attachment === null) {
            return Response::error("Nincs {$validated['attachment_id']} azonosítójú csatolmány.");
        }

        $filename = $attachment->filename;
        $attachment->delete();

        return Response::json([
            'message' => "A(z) „{$filename}” csatolmány törölve.",
            'attachment_id' => $validated['attachment_id'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'attachment_id' => $schema->integer()
                ->description('A törlendő csatolmány azonosítója.')
                ->required(),
        ];
    }
}
