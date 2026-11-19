<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChatConversation extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'chat_conversations';

    protected $fillable = [
        'uuid',
        'type', // direct, group, channel
        'name',
        'description',
        'division_id',
        'created_by',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_conversation_user', 'conversation_id', 'user_id')
            ->withPivot(['last_read_at', 'is_muted'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class, 'conversation_id')->latestOfMany();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    /**
     * Get unread messages count for a specific user.
     */
    public function unreadCountFor(int $userId, ?string $lastReadAt = null): int
    {
        $query = $this->messages()->where('user_id', '!=', $userId);

        if ($lastReadAt) {
            $query->where('created_at', '>', $lastReadAt);
        }

        return $query->count();
    }

    /**
     * Get or create a direct 1-on-1 conversation between two users.
     */
    public static function findOrCreateDirect(int $userAId, int $userBId): self
    {
        if ($userAId === $userBId) {
            // Self-chat or fallback
            $conversation = self::where('type', 'direct')
                ->whereHas('users', fn($q) => $q->where('users.id', $userAId))
                ->where('created_by', $userAId)
                ->first();

            if (!$conversation) {
                $conversation = self::create([
                    'type' => 'direct',
                    'created_by' => $userAId,
                ]);
                $conversation->users()->attach($userAId);
            }
            return $conversation;
        }

        // Find existing conversation with both users and exactly 2 members
        $conversation = self::where('type', 'direct')
            ->whereHas('users', fn($q) => $q->where('users.id', $userAId))
            ->whereHas('users', fn($q) => $q->where('users.id', $userBId))
            ->first();

        if (!$conversation) {
            $conversation = self::create([
                'type' => 'direct',
                'created_by' => $userAId,
            ]);
            $conversation->users()->attach([$userAId, $userBId]);
        }

        return $conversation;
    }

    /**
     * Get or create default general public room.
     */
    public static function getOrCreateGeneralRoom(): self
    {
        $room = self::where('type', 'channel')->whereNull('division_id')->first();

        if (!$room) {
            $room = self::create([
                'type' => 'channel',
                'name' => 'Ruang Umum (Semua Pegawai)',
                'description' => 'Kanal diskusi dan informasi umum seluruh pegawai Sahabat SDAM.',
            ]);
        }

        return $room;
    }
}
