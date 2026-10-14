<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SellerViolation;
use App\Models\SellerViolationEvidence;
use App\Services\Admin\SellerComplianceService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SellerComplianceEvidenceController extends Controller
{
    public function store(
        Request $request,
        SellerViolation $violation,
        SellerComplianceService $compliance
    ): RedirectResponse {
        $extensions = implode(',', config('compliance.evidence_extensions'));
        $mimeTypes = implode(',', config('compliance.evidence_mime_types'));

        $validated = $request->validate([
            'evidence' => [
                'required',
                'file',
                'max:'.config('compliance.evidence_max_kilobytes'),
                'mimes:'.$extensions,
                'mimetypes:'.$mimeTypes,
                'extensions:'.$extensions,
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $compliance->addEvidence(
            $violation,
            $request->user(),
            $validated['evidence'],
            $validated['description'] ?? null
        );

        return back()->with('success', 'Compliance evidence uploaded.');
    }

    public function show(
        SellerViolation $violation,
        SellerViolationEvidence $evidence
    ): StreamedResponse {
        $storage = $this->authorizedStorage($violation, $evidence);

        return $storage->response($evidence->path, $evidence->original_name, [
            'Content-Type' => $evidence->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    public function download(
        SellerViolation $violation,
        SellerViolationEvidence $evidence
    ): StreamedResponse {
        $storage = $this->authorizedStorage($violation, $evidence);

        return $storage->download($evidence->path, $evidence->original_name, [
            'Content-Type' => $evidence->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    private function authorizedStorage(
        SellerViolation $violation,
        SellerViolationEvidence $evidence
    ): FilesystemAdapter
    {
        abort_unless((int) $evidence->seller_violation_id === (int) $violation->id, 404);

        $storage = Storage::disk($evidence->disk);
        abort_unless($storage->exists($evidence->path), 404);

        return $storage;
    }
}
