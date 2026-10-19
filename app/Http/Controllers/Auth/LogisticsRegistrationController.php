<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LogisticsRegistrationRequest;
use App\Exceptions\VerificationDeliveryException;
use App\Models\Application;
use App\Models\LogisticsProvider;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountVerificationService;
use App\Services\GoogleRegistrationService;
use App\Services\PhilippineAddressService;
use App\Services\DomainNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class LogisticsRegistrationController extends Controller
{
    public function store(LogisticsRegistrationRequest $request, AccountVerificationService $verification, GoogleRegistrationService $google, PhilippineAddressService $addresses): RedirectResponse
    {
        $data = $request->validated();
        try {
            $data = array_merge($data, $addresses->resolve($data));
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }
        $google->assertMatches($request, $data['email'], 'logistics');
        $paths = [
            'government_id' => $request->file('government_id')->store('application-documents/logistics/government-ids', 'local'),
            'business_permit' => $request->file('business_permit')->store('application-documents/logistics/business-permits', 'local'),
        ];
        try {
            $user = DB::transaction(function () use ($data, $paths, $google, $request): User {
                $name = trim($data['first_name'].' '.($data['middle_initial'] ?? '').' '.$data['last_name']);
                $user = User::query()->create([
                    'role'=>'logistics','first_name'=>$data['first_name'],'last_name'=>$data['last_name'],'middle_initial'=>$data['middle_initial'] ?? null,
                    'sex'=>$data['sex'],'birthday'=>$data['birthday'],'name'=>$name,'email'=>$data['email'],'phone'=>$data['phone'],
                    'status'=>'pending','is_suspended'=>false,'verification_required_at'=>now(),'password'=>$data['password'],
                ]);
                $user->roles()->syncWithoutDetaching([Role::query()->firstOrCreate(['name'=>'logistics'])->id]);
                LogisticsProvider::query()->create([
                    'user_id'=>$user->id,'name'=>$data['business_name'],'slug'=>Str::slug($data['business_name']).'-'.$user->id,
                    'status'=>'pending','contact_phone'=>$data['phone'],'address'=>$data['address'],
                    'region'=>$data['region'],'province'=>$data['province'],'city'=>$data['municipality'],'barangay'=>$data['barangay'],
                    'government_id_path'=>$paths['government_id'],'business_permit_path'=>$paths['business_permit'],
                ]);
                $application = Application::query()->create([
                    'user_id'=>$user->id,'application_type'=>'logistics','status'=>'pending',
                    'application_data'=>['business_name'=>$data['business_name'],'address'=>$data['address'],'region'=>$data['region'],'province'=>$data['province'],'city'=>$data['municipality'],'barangay'=>$data['barangay']], 'documents'=>$paths,
                ]);
                $google->linkIfPending($request, $user, 'logistics');
                app(DomainNotificationService::class)->adminsAfterCommit('Logistics application pending', 'A logistics application is awaiting review.', ['application_id' => $application->id]);
                return $user;
            }, 3);
        } catch (Throwable $e) {
            foreach ($paths as $path) Storage::disk('local')->delete($path);
            throw $e;
        }
        $request->session()->put('verification_user_id', $user->id);
        if ($user->oauth_provider === 'google') {
            $google->clearPending($request);
            $request->session()->forget('verification_user_id');
            return redirect()->route('login')->with('success', 'Google verified your email. Your application remains pending administrator approval.');
        }
        try {
            $verification->send($user, 'email', false);
        } catch (VerificationDeliveryException) {
            return redirect()->route('verification.contact.show')
                ->with('error', 'Your application was saved, but we could not deliver the verification code. Please use Resend code.');
        }
        return redirect()->route('verification.contact.show')->with('success', 'Application saved. Verify your email before administrator review.');
    }
}
