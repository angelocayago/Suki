@php
$layout = 'layouts.app';
$messageRoutePrefix = 'buyer';

if (auth()->check()) {
    if (auth()->user()->isRider()) {
        $layout = 'rider.layout';
        $messageRoutePrefix = 'rider';
    } elseif (auth()->user()->isSeller()) {
        $layout = 'seller.layout';
        $messageRoutePrefix = 'seller';
    } elseif (auth()->user()->isLogisticOwner()) {
        $messageRoutePrefix = 'logistic';
    } elseif (auth()->user()->isAdmin()) {
        $messageRoutePrefix = 'admin';
    } elseif (auth()->user()->isCustomer()) {
        $messageRoutePrefix = 'buyer';
    }
}
@endphp
@extends($layout)

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Conversation with {{ $user->name }}</h1>
  <a href="{{ route($messageRoutePrefix . '.messages.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="table-container" style="max-width: 820px;">
  <div id="messageThread" class="border rounded p-3 mb-3" style="height: 450px; overflow-y: auto;">
    @forelse($messages as $msg)
      <div class="d-flex mb-2 {{ $msg->sender_id === auth()->id() ? 'justify-content-end' : 'justify-content-start' }}">
        <div class="p-2 rounded {{ $msg->sender_id === auth()->id() ? 'bg-primary text-white' : 'bg-light' }}" style="max-width: 70%;">
          <div class="small fw-bold">{{ $msg->sender->name ?? 'N/A' }}</div>
          <div>{{ $msg->message }}</div>
          <div class="small {{ $msg->sender_id === auth()->id() ? 'text-white-50' : 'text-muted' }}">{{ \Illuminate\Support\Carbon::parse($msg->created_at)->format('M d, H:i') }}</div>
        </div>
      </div>
    @empty
      <p class="text-muted text-center">No messages yet. Start the conversation below.</p>
    @endforelse
  </div>

  <form id="messageForm" method="POST" action="{{ route($messageRoutePrefix . '.messages.reply', $user) }}">
    @csrf
    <div class="mb-2">
      <textarea id="messageInput" name="message" class="form-control" rows="3" placeholder="Type your message..." required></textarea>
    </div>
    <button id="messageSendButton" type="submit" class="btn btn-bili-hub"><i class="bi bi-send"></i> Send Message</button>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const thread = document.getElementById('messageThread');
    const form = document.getElementById('messageForm');
    const input = document.getElementById('messageInput');
    const sendButton = document.getElementById('messageSendButton');

    const pollUrl = @json(route($messageRoutePrefix . '.messages.poll', $user));
    const sendUrl = @json(route($messageRoutePrefix . '.messages.reply', $user));
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || form.querySelector('input[name="_token"]')?.value
        || '';

    let lastRenderedSignature = '';
    let polling = false;

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }

    function renderMessages(messages, forceScroll = false) {
        const signature = messages.map((message) => message.id).join(',');

        if (signature === lastRenderedSignature && !forceScroll) {
            return;
        }

        const wasNearBottom =
            thread.scrollHeight - thread.scrollTop - thread.clientHeight < 90;

        if (!messages.length) {
            thread.innerHTML = `
                <p class="text-muted text-center mb-0">
                    No messages yet. Start the conversation below.
                </p>
            `;
        } else {
            thread.innerHTML = messages.map((message) => {
                const mine = Boolean(message.is_mine);
                const justify = mine ? 'justify-content-end' : 'justify-content-start';
                const bubble = mine ? 'bg-primary text-white' : 'bg-light';
                const timeClass = mine ? 'text-white-50' : 'text-muted';

                return `
                    <div class="d-flex mb-2 ${justify}">
                        <div class="p-2 rounded ${bubble}" style="max-width:70%;">
                            <div class="small fw-bold">${escapeHtml(message.sender_name)}</div>
                            <div style="white-space:pre-wrap;word-break:break-word;">${escapeHtml(message.message)}</div>
                            <div class="small ${timeClass}">${escapeHtml(message.created_at)}</div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        lastRenderedSignature = signature;

        if (forceScroll || wasNearBottom) {
            thread.scrollTop = thread.scrollHeight;
        }
    }

    async function pollMessages(forceScroll = false) {
        if (polling || document.hidden) {
            return;
        }

        polling = true;

        try {
            const response = await fetch(pollUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                cache: 'no-store',
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            renderMessages(data.messages || [], forceScroll);
        } catch (error) {
            // Temporary connection errors are ignored; next poll retries.
        } finally {
            polling = false;
        }
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const message = input.value.trim();
        if (!message) {
            return;
        }

        sendButton.disabled = true;

        try {
            const response = await fetch(sendUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ message }),
            });

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                alert(data.message || 'Unable to send message.');
                return;
            }

            input.value = '';
            await pollMessages(true);
            input.focus();
        } catch (error) {
            alert('Unable to send message. Please check your connection.');
        } finally {
            sendButton.disabled = false;
        }
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();

            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                sendButton.click();
            }
        }
    });

    thread.scrollTop = thread.scrollHeight;
    pollMessages(true);

    // Live update without manual browser refresh.
    setInterval(() => pollMessages(false), 2000);

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            pollMessages(true);
        }
    });
});
</script>

@endsection
