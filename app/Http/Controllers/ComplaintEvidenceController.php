<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\ComplaintEvidence;
use App\Services\Admin\ComplaintService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComplaintEvidenceController extends Controller
{
    public function store(
        Request $request,
        Complaint $complaint,
        ComplaintService $complaints
    ): RedirectResponse {
        $extensions = implode(',', config('complaints.evidence_extensions'));
        $mimeTypes = implode(',', config('complaints.evidence_mime_types'));

        $validated = $request->validate([
            'evidence' => [
                'required',
                'file',
                'max:'.config('complaints.evidence_max_kilobytes'),
                'mimes:'.$extensions,
                'mimetypes:'.$mimeTypes,
                'extensions:'.$extensions,
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $complaints->addEvidence(
            $complaint,
            $request->user(),
            $validated['evidence'],
            $validated['description'] ?? null
        );

        return back()->with('success', 'Complaint evidence uploaded.');
    }

    public function show(
        Request $request,
        Complaint $complaint,
        ComplaintEvidence $evidence,
        ComplaintService $complaints
    ): StreamedResponse {
        $storage = $this->authorizedStorage($request, $complaint, $evidence, $complaints);

        return $storage->response($evidence->path, $evidence->original_name, $this->headers($evidence));
    }

    public function download(
        Request $request,
        Complaint $complaint,
        ComplaintEvidence $evidence,
        ComplaintService $complaints
    ): StreamedResponse {
        $storage = $this->authorizedStorage($request, $complaint, $evidence, $complaints);

        return $storage->download($evidence->path, $evidence->original_name, $this->headers($evidence));
    }

    private function authorizedStorage(
        Request $request,
        Complaint $complaint,
        ComplaintEvidence $evidence,
        ComplaintService $complaints
    ): FilesystemAdapter {
        $complaints->ensureCanAccessEvidence($complaint, $evidence, $request->user());

        $storage = Storage::disk($evidence->disk);
        abort_unless($storage->exists($evidence->path), 404);

        return $storage;
    }

    private function headers(ComplaintEvidence $evidence): array
    {
        return [
            'Content-Type' => $evidence->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ];
    }
}
