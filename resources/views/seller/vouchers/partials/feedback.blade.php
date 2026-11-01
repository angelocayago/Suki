@if(session('success'))<p class="sv-success" role="status">{{ session('success') }}</p>@endif
@if($errors->any())<div class="sv-errors" role="alert"><strong>Please review the voucher.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
