<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicationDocumentController extends Controller
{
    public function show(Application $application, string $document): StreamedResponse
    {
        $sellerDocument = $application->sellerDocuments()
            ->where('document_type', $document)
            ->first();

        if ($sellerDocument) {
            $storage = Storage::disk($sellerDocument->disk);
            abort_unless($storage->exists($sellerDocument->path), 404);

            return $this->safeResponse(
                $storage,
                $sellerDocument->path,
                $sellerDocument->original_name,
                $sellerDocument->mime_type
            );
        }

        // Compatibility for pre-Phase-1B application documents.
        $legacyDocuments = $application->documents ?? [];
        abort_unless(isset($legacyDocuments[$document]), 404);
        $storage = Storage::disk(config('filesystems.default'));
        abort_unless($storage->exists($legacyDocuments[$document]), 404);

        return $this->safeResponse(
            $storage,
            $legacyDocuments[$document],
            basename($legacyDocuments[$document]),
            $storage->mimeType($legacyDocuments[$document]) ?: 'application/octet-stream'
        );
    }

    private function safeResponse(
        FilesystemAdapter $storage,
        string $path,
        string $name,
        string $mimeType
    ): StreamedResponse {
        return $storage->response($path, $name, [
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
