<?php
namespace App\Services;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
class ShoppingAssistantAiAdapter {
    public function rewrite(string $grounded, array $context): ?string {
        $provider=config('services.shopping_assistant.provider');
        if (!in_array($provider,['openai','gemini'],true)) return null;
        try {
            // No user identity, addresses, contacts, proof, or raw conversation is sent.
            $response=Http::timeout((int)config('services.shopping_assistant.timeout',3))->acceptJson()
                ->withToken(config('services.shopping_assistant.key'))
                ->post((string)config('services.shopping_assistant.endpoint'), ['model'=>config('services.shopping_assistant.model'),'grounded_answer'=>$grounded,'catalog_context'=>$context,'instruction'=>'Rewrite briefly. Do not add facts, products, prices, availability, dates, or policy claims.']);
            $text=$response->successful() ? trim((string)data_get($response->json(),'text')) : '';
            // A provider cannot be authoritative.  Accept only a normalized
            // equivalent of the already-grounded answer, so it cannot add a
            // price, product, availability, policy, date, or status claim.
            return $text !== '' && mb_strlen($text)<=900 && $this->normalise($text)===$this->normalise($grounded)
                ? $grounded : null;
        } catch (\Throwable) { Log::warning('Shopping assistant optional provider unavailable.'); return null; }
    }
    private function normalise(string $value): string { return preg_replace('/\s+/u',' ',mb_strtolower(trim($value))) ?? ''; }
}
