@props(['active' => 'profile', 'enabled' => true])

@if($enabled)
<section class="buyer-account-shell" data-buyer-account-shell>
    <x-buyer.account-sidebar :active="$active" />
    <div class="buyer-account-content">
        {{ $slot }}
    </div>
</section>
<script>
(() => {
    const navigation = document.querySelector('[data-buyer-account-navigation]');
    if (!navigation || navigation.dataset.accountReady) return;
    navigation.dataset.accountReady = '1';
    const compact = window.matchMedia('(max-width: 1023px)');
    const sync = () => { navigation.open = !compact.matches; };
    sync();
    compact.addEventListener('change', sync);
})();
</script>
@else
    {{ $slot }}
@endif
