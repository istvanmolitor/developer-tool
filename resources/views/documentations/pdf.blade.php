<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="utf-8">
    <title>{{ $project->name }} — dokumentáció</title>
    <style>
        @page {
            margin: 28mm 18mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            line-height: 1.6;
            color: #1f2937;
        }

        .cover {
            text-align: center;
            padding-top: 60mm;
        }

        .cover h1 {
            font-size: 28px;
            margin: 0 0 8px;
        }

        .cover .path {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 12px;
            color: #6b7280;
        }

        .cover .meta {
            margin-top: 16px;
            font-size: 11px;
            color: #9ca3af;
        }

        .doc {
            page-break-before: always;
        }

        .doc-header {
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 6px;
            margin-bottom: 14px;
        }

        .doc-header h2 {
            font-size: 20px;
            margin: 0;
        }

        .doc-header .path {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 10px;
            color: #9ca3af;
        }

        h1, h2, h3, h4, h5, h6 {
            color: #111827;
            line-height: 1.3;
        }

        .content h1 { font-size: 20px; }
        .content h2 { font-size: 17px; }
        .content h3 { font-size: 15px; }
        .content h4 { font-size: 13px; }

        .content p,
        .content ul,
        .content ol,
        .content blockquote,
        .content pre,
        .content table {
            margin: 0 0 10px;
        }

        .content code {
            font-family: DejaVu Sans Mono, monospace;
            background: #f3f4f6;
            padding: 1px 4px;
            border-radius: 3px;
            font-size: 10px;
        }

        .content pre {
            background: #f3f4f6;
            padding: 10px;
            border-radius: 4px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .content pre code {
            background: transparent;
            padding: 0;
        }

        .content blockquote {
            border-left: 3px solid #d1d5db;
            padding-left: 12px;
            color: #4b5563;
        }

        .content table {
            border-collapse: collapse;
            width: 100%;
        }

        .content th,
        .content td {
            border: 1px solid #e5e7eb;
            padding: 5px 8px;
            text-align: left;
        }

        .content img {
            max-width: 100%;
        }

        .content a {
            color: #2563eb;
            text-decoration: none;
        }

        .empty {
            color: #6b7280;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="cover">
        <h1>{{ $project->name }}</h1>
        <div class="path">{{ $project->path }}</div>
        <div class="meta">
            Dokumentáció · {{ $documentations->count() }} fájl<br>
            Generálva: {{ $generatedAt->format('Y-m-d H:i') }}
        </div>
    </div>

    @forelse ($documentations as $documentation)
        <div class="doc">
            <div class="doc-header">
                <h2>{{ $documentation->title ?? $documentation->path }}</h2>
                <div class="path">{{ $documentation->path }}</div>
            </div>
            <div class="content">
                {!! $documentation->content_html !!}
            </div>
        </div>
    @empty
        <div class="doc">
            <p class="empty">Ehhez a projekthez még nincs dokumentáció.</p>
        </div>
    @endforelse
</body>
</html>
