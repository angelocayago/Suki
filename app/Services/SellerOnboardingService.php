<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Category;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SellerOnboardingService
{
    /**
     * Create a complete pending seller application and its private documents.
     */
    public function register(array $data, ?callable $beforeCommit = null): Application
    {
        $storedFiles = [];

        try {
            return DB::transaction(function () use ($data, &$storedFiles, $beforeCommit): Application {
                $category = Category::query()
                    ->where('is_active', true)
                    ->findOrFail($data['category_id']);
                $fullName = trim(implode(' ', array_filter([
                    $data['first_name'],
                    $data['middle_initial'] ?? null,
                    $data['last_name'],
                ])));

                $user = User::query()->create([
                    'role' => 'seller',
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'middle_initial' => $data['middle_initial'] ?? null,
                    'sex' => $data['sex'],
                    'birthday' => $data['birthday'],
                    'name' => $fullName,
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'status' => 'pending',
                    'is_suspended' => false,
                    'verification_required_at' => now(),
                    'password' => $data['password'],
                ]);

                $role = Role::query()->firstOrCreate(['name' => 'seller']);
                $user->roles()->syncWithoutDetaching([$role->id]);

                $address = $user->addresses()->create([
                    'label' => 'Business',
                    'recipient' => $fullName,
                    'phone' => $data['phone'],
                    'line1' => $data['address'],
                    'region' => $data['region'],
                    'barangay' => $data['barangay'],
                    'city' => $data['municipality'],
                    'province' => $data['province'],
                    'postal_code' => $data['postal_code'] ?? null,
                    'is_default' => true,
                ]);

                $seller = Seller::query()->create([
                    'user_id' => $user->id,
                    'name' => $data['business_name'],
                    'slug' => Str::slug($data['business_name']).'-'.$user->id,
                    'business_category' => $category->name,
                    'category_id' => $category->id,
                    'seller_type' => $data['seller_type'],
                    'tin' => $data['tin'],
                    'status' => 'pending',
                    'commission_bps' => 1000,
                    'pickup_address_id' => $address->id,
                ]);

                // Keep the legacy pivot populated while category_id is the authority.
                $seller->allowedCategories()->syncWithoutDetaching([
                    $category->id => ['assigned_by' => null],
                ]);

                $application = Application::query()->create([
                    'user_id' => $user->id,
                    'application_type' => 'seller',
                    'status' => 'pending',
                    'application_data' => [
                        'seller_id' => $seller->id,
                        'business_name' => $seller->name,
                        'category_id' => $category->id,
                        'business_category' => $category->name,
                        'seller_type' => $seller->seller_type,
                        'tin' => $seller->tin,
                        'address_id' => $address->id,
                    ],
                ]);

                $documents = ['valid_id' => $data['valid_id']];
                if (($data['business_permit'] ?? null) instanceof UploadedFile) {
                    $documents['business_permit'] = $data['business_permit'];
                }

                $legacyPaths = [];
                foreach ($documents as $type => $file) {
                    $document = $this->storeDocument(
                        $seller,
                        $application,
                        $user,
                        $type,
                        $file,
                        $storedFiles
                    );
                    $legacyPaths[$type] = $document->path;
                }

                $seller->update([
                    'valid_id_path' => $legacyPaths['valid_id'],
                    'business_permit_path' => $legacyPaths['business_permit'] ?? null,
                ]);
                $application->update(['documents' => $legacyPaths]);

                app(DomainNotificationService::class)->adminsAfterCommit('Seller application pending', 'A seller application is awaiting review.', ['application_id' => $application->id]);

                if ($beforeCommit) {
                    $beforeCommit($user);
                }
                return $application->fresh(['user.sellers.category', 'sellerDocuments']);
            }, 3);
        } catch (Throwable $exception) {
            foreach ($storedFiles as [$disk, $path]) {
                Storage::disk($disk)->delete($path);
            }

            throw $exception;
        }
    }

    private function storeDocument(
        Seller $seller,
        Application $application,
        User $uploader,
        string $type,
        UploadedFile $file,
        array &$storedFiles
    ): SellerDocument {
        $disk = config('seller_documents.disk');
        $extension = strtolower($file->extension() ?: $file->getClientOriginalExtension());
        $path = $file->storeAs(
            'sellers/'.$seller->id,
            Str::uuid().'.'.$extension,
            $disk
        );

        if (! is_string($path)) {
            throw new \RuntimeException('The seller document could not be stored.');
        }

        // Track immediately so a later database exception cannot orphan the file.
        $storedFiles[] = [$disk, $path];

        return SellerDocument::query()->create([
            'seller_id' => $seller->id,
            'application_id' => $application->id,
            'uploaded_by' => $uploader->id,
            'document_type' => $type,
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'verification_status' => 'pending',
        ]);
    }
}
