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
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class LogisticsRegistrationController extends Controller
{
    public function store(LogisticsRegistrationRequest $request, AccountVerificationService $verification): RedirectResponse
    {
        $data = $request->validated();
        $paths = [
            'government_id' => $request->file('government_id')->store('application-documents/logistics/government-ids', 'local'),
            'business_permit' => $request->file('business_permit')->store('application-documents/logistics/business-permits', 'local'),
        ];
        try {
            $user = DB::transaction(function () use ($data, $paths): User {
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
                    'government_id_path'=>$paths['government_id'],'business_permit_path'=>$paths['business_permit'],
                ]);
                Application::query()->create([
                    'user_id'=>$user->id,'application_type'=>'logistics','status'=>'pending',
                    'application_data'=>['business_name'=>$data['business_name'],'address'=>$data['address']], 'documents'=>$paths,
                ]);
                return $user;
            }, 3);
        } catch (Throwable $e) {
            foreach ($paths as $path) Storage::disk('local')->delete($path);
            throw $e;
        }
        $request->session()->put('verification_user_id', $user->id);
        try {
            $verification->send($user, 'email', false);
        } catch (VerificationDeliveryException) {
            return redirect()->route('verification.contact.show')
                ->with('error', 'Your application was saved, but we could not send the email code. Please use Resend code.');
        }
        return redirect()->route('verification.contact.show')->with('success', 'Application saved. Verify your contacts before administrator review.');
    }
}
