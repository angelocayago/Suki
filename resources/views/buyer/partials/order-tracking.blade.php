<section aria-label="Order tracking" class="min-w-0 space-y-5">
    <header class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-6">
        <h2 class="text-lg font-bold text-gray-900">Order Tracking</h2>
        <p class="mt-1 break-words text-sm text-gray-700">Order #{{ $tracking['number'] }}</p>
        <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-gray-600">Overall order status</p>
        <p class="mt-1 inline-flex rounded-full bg-[#DDF3EC] px-3 py-1 text-sm font-semibold text-[#155244]">{{ $tracking['status'] }}</p>
        @if($tracking['placed'])
            <p class="mt-3 text-sm text-gray-600">{{ $tracking['placed']['label'] }}:
                <time datetime="{{ $tracking['placed']['time']['iso'] }}">{{ $tracking['placed']['time']['label'] }}</time>
            </p>
        @endif
        <p class="mt-3 text-sm text-gray-600">Each store's package can progress separately. Only recorded activity is shown.</p>
    </header>

    @forelse($tracking['packages'] as $package)
        <article class="min-w-0 rounded-2xl border border-gray-200 bg-white p-4 sm:p-6" aria-label="Package from {{ $package['store'] }}">
            <h3 class="break-words font-bold text-gray-900">{{ $package['store'] }}</h3>
            @if($package['tracking_code'])<p class="mt-1 break-all text-xs text-gray-600">Tracking code: {{ $package['tracking_code'] }}</p>@endif
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <div><dt class="text-gray-600">Seller status</dt><dd class="font-semibold text-gray-900">{{ $package['seller_status'] }}</dd></div>
                <div><dt class="text-gray-600">Delivery status</dt><dd class="font-semibold text-gray-900">{{ $package['delivery_status'] }}</dd></div>
            </dl>
            <p class="mt-4 rounded-xl border p-3 text-sm {{ $package['exception'] ? 'border-amber-300 bg-amber-50 text-amber-950' : 'border-[#D5EAE2] bg-[#EEF8F3] text-[#155244]' }}">
                <strong>{{ $package['exception'] ? 'Delivery update: ' : '' }}</strong>{{ $package['message'] }}
            </p>
            @if($package['inconsistent'])<p class="mt-2 text-sm text-amber-950">Tracking records are not yet in sync. Seller and delivery statuses are shown separately.</p>@endif
            @if($package['cancel_reason'])<p class="mt-2 text-sm text-gray-700">Cancellation reason: {{ $package['cancel_reason'] }}</p>@endif
            @if($package['updated'])
                <p class="mt-3 text-xs text-gray-600">Last recorded update: <time datetime="{{ $package['updated']['iso'] }}">{{ $package['updated']['label'] }}</time></p>
            @endif
            @if($package['scheduled'])
                <p class="mt-2 text-sm text-gray-700">Recorded rescheduled time: <time datetime="{{ $package['scheduled']['iso'] }}">{{ $package['scheduled']['label'] }}</time></p>
            @endif

            <ol class="mt-6" aria-label="Recorded package timeline">
                @forelse($package['main'] as $entry)
                    <li class="flex min-w-0 gap-3 sm:gap-4">
                        <div class="relative flex w-4 shrink-0 justify-center" aria-hidden="true">
                            @if(!$loop->last)<span class="absolute bottom-0 left-1/2 top-2 w-px -translate-x-1/2 bg-[#D5EAE2]"></span>@endif
                            <span class="relative mt-1.5 h-3 w-3 shrink-0 rounded-full {{ $entry['exception'] ? 'bg-amber-700' : 'bg-[#1F6F5B]' }}"></span>
                        </div>
                        <div class="min-w-0 flex-1 break-words text-sm {{ $loop->last ? 'pb-2' : 'pb-6' }}">
                            <p class="font-semibold leading-6 {{ $entry['exception'] ? 'text-amber-950' : 'text-gray-900' }}">{{ $entry['label'] }}</p>
                            @if($entry['reason'])<p class="mt-1 leading-6 text-gray-700">Reason: {{ $entry['reason'] }}</p>@endif
                            <div class="mt-1 flex flex-wrap gap-x-2 gap-y-1 leading-6 text-gray-600">
                                <span class="font-medium">{{ $entry['id'] ? 'Recorded event' : 'Milestone time' }}</span>
                                <time class="min-w-0" datetime="{{ $entry['time']['iso'] }}">{{ $entry['time']['label'] }}</time>
                            </div>
                        </div>
                    </li>
                @empty
                    <li class="text-sm text-gray-600">No milestone times have been recorded for this package yet.</li>
                @endforelse
                <li aria-current="step" class="mt-4 rounded-xl border border-[#1F6F5B] bg-[#EEF8F3] p-4 text-sm leading-6 text-[#155244]">
                    <p class="font-semibold">Current state: {{ $package['current'] }}</p>
                    <p class="mt-1">Current recorded status; a start time is not inferred.</p>
                </li>
            </ol>
            @if($package['activity'])
                <details data-tracking-activity="{{ $package['tracking_code'] ?? $loop->index }}" class="mt-6 rounded-xl border border-gray-200 p-4">
                    <summary class="min-h-11 cursor-pointer rounded text-sm font-semibold leading-6 text-[#155244] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1F6F5B]">More activity</summary>
                    <ol class="mt-4 space-y-5 border-l-2 border-gray-200 pl-4" aria-label="Recorded operational activity">
                        @foreach($package['activity'] as $entry)
                            <li class="min-w-0 break-words text-sm leading-6 text-gray-700">
                                <p class="font-medium">{{ $entry['label'] }}</p>
                                <time class="mt-1 block text-sm text-gray-600" datetime="{{ $entry['time']['iso'] }}">{{ $entry['time']['label'] }}</time>
                            </li>
                        @endforeach
                    </ol>
                </details>
            @endif
        </article>
    @empty
        <article class="rounded-2xl border border-gray-200 bg-white p-5">
            <h3 class="font-semibold text-gray-900">Historical order tracking</h3>
            <p class="mt-2 text-sm text-gray-600">Package tracking details aren't available for this order. Only recorded order activity is shown.</p>
            <ol class="mt-5 space-y-5 border-l-2 border-[#D5EAE2] pl-4">
                @foreach($tracking['legacy'] as $entry)
                    <li class="min-w-0 break-words text-sm leading-6 text-gray-700"><p class="font-semibold">{{ $entry['label'] }}</p><time class="mt-1 block text-gray-600" datetime="{{ $entry['time']['iso'] }}">{{ $entry['time']['label'] }}</time></li>
                @endforeach
            </ol>
        </article>
    @endforelse
</section>
