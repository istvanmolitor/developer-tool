<?php

namespace App\Models;

use Database\Factories\DocumentationFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Documentation extends Model
{
    /** @use HasFactory<DocumentationFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'path',
        'title',
        'content',
    ];

    /**
     * The project whose docs/documentations folder this file was imported from.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The Markdown content rendered to safe HTML. Raw HTML in the source is
     * stripped and unsafe links are disallowed, so the result is safe to inject
     * with `v-html`. Not appended by default — call `append('content_html')`
     * where the rendered body is actually needed.
     *
     * @return Attribute<string, never>
     */
    protected function contentHtml(): Attribute
    {
        return Attribute::get(fn (): string => Str::markdown((string) $this->content, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]));
    }
}
