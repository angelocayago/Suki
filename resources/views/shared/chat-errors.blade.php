@if(session('success'))<p role="status" class="mb-4 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</p>@endif
@if($errors->any())<ul role="alert" class="mb-4 text-sm text-red-700">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
