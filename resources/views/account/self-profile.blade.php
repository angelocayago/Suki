@extends($area === 'admin' ? 'admin.layout' : 'layouts.'.$area)
@section('title', 'My profile')
@section('content')
<div class="mx-auto max-w-xl space-y-5">
  <div class="flex items-center gap-3"><x-profile-avatar :user="$user" size="h-14 w-14"/><div><h1 class="text-2xl font-bold">My profile</h1><p class="text-sm text-gray-500">{{ ucfirst($area) }} account</p></div></div>
  @if(session('success'))<div class="rounded bg-green-50 p-3 text-green-800">{{ session('success') }}</div>@endif
  <form method="POST" action="{{ route($area.'.profile.update') }}" class="space-y-4 rounded border bg-white p-5">@csrf @method('PATCH')
    <div><label>First name</label><input class="w-full rounded border p-2" name="first_name" value="{{ old('first_name', $user->first_name) }}" required></div>
    <div><label>Last name</label><input class="w-full rounded border p-2" name="last_name" value="{{ old('last_name', $user->last_name) }}" required></div>
    <div><label>Middle initial</label><input class="w-full rounded border p-2" name="middle_initial" value="{{ old('middle_initial', $user->middle_initial) }}"></div>
    <div><label>Phone</label><input class="w-full rounded border p-2" name="phone" value="{{ old('phone', $user->phone) }}"></div>
    <p class="text-xs text-gray-500">Role, approval, verification, provider assignment, and password are managed separately.</p>
    <button class="rounded bg-[#173F35] px-4 py-2 text-white">Save profile</button>
  </form>
</div>
@endsection
