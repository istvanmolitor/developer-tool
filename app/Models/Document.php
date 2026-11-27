<?php

namespace App\Models;

use App\Enums\SearchType;
use App\Services\Search\IndexedInSearch;
use App\Services\Search\Searchable;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Document extends Model implements Searchable
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    use IndexedInSearch;

    protected $fillable = [
        'project_id',
        'path',
        'title',
        'content',
    ];

    /**
     * The project whose docs folder this file was imported from.
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

    public static function searchType(): SearchType
    {
        return SearchType::Document;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchDocument(): array
    {
        return [
            'title' => $this->title ?? $this->path,
            'body' => $this->content,
            'extra' => $this->path,
            'path' => $this->path,
            'project_id' => $this->project_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
