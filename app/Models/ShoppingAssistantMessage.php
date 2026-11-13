<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ShoppingAssistantMessage extends Model {
    protected $fillable=['shopping_assistant_conversation_id','role','source','body'];
    public function conversation(): BelongsTo { return $this->belongsTo(ShoppingAssistantConversation::class, 'shopping_assistant_conversation_id'); }
}
