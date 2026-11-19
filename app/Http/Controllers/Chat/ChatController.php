<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Division;
use App\Models\User;
use App\Notifications\GenericWebPushNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    /**
     * Display chat dashboard.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->ensureChannelsForUser($user);

        $conversations = $this->getUserConversationsData($user);
        $activeId = $request->query('conversation');

        // If activeId specified, verify access
        $activeConversation = null;
        $initialMessages = [];

        if ($activeId) {
            $conv = ChatConversation::with(['users', 'division'])->find($activeId);
            if ($conv && $this->userCanAccess($user, $conv)) {
                $activeConversation = $this->formatConversation($conv, $user);
                $initialMessages = $this->getConversationMessages($conv, 50);
                $this->markConversationAsRead($user, $conv);
            }
        }

        // If no active conversation chosen, select the first one if available
        if (!$activeConversation && count($conversations) > 0) {
            $firstConvModel = ChatConversation::with(['users', 'division'])->find($conversations[0]['id']);
            if ($firstConvModel) {
                $activeConversation = $conversations[0];
                $initialMessages = $this->getConversationMessages($firstConvModel, 50);
                $this->markConversationAsRead($user, $firstConvModel);
            }
        }

        // Contacts list for starting new direct chat (tanpa membocorkan NIP)
        $contacts = User::whereNull('deleted_at')
            ->select(['id', 'name', 'email', 'avatar', 'gender', 'divisi'])
            ->orderBy('name')
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'avatar' => $c->avatar ? (str_starts_with($c->avatar, 'http') ? $c->avatar : asset('storage/' . $c->avatar)) : null,
                'gender' => $c->gender,
                'divisi' => $c->divisi,
            ]);

        $divisions = Division::allCached()->map(fn($d) => [
            'id' => $d->id,
            'name' => $d->name,
        ]);

        return Inertia::render('Chat/Index', [
            'conversations' => $conversations,
            'activeConversation' => $activeConversation,
            'initialMessages' => $initialMessages,
            'contacts' => $contacts,
            'divisions' => $divisions,
        ]);
    }

    /**
     * Get updated list of conversations for polling.
     */
    public function conversations(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureChannelsForUser($user);

        return response()->json([
            'conversations' => $this->getUserConversationsData($user),
        ]);
    }

    /**
     * Fetch messages for a conversation (for polling).
     */
    public function messages(Request $request, ChatConversation $conversation): JsonResponse
    {
        $user = $request->user();
        if (!$this->userCanAccess($user, $conversation)) {
            return response()->json(['error' => 'Akses ditolak'], 403);
        }

        $afterId = $request->query('after_id');
        $query = $conversation->messages()
            ->with(['sender:id,name,avatar,gender,divisi'])
            ->orderBy('id', 'asc');

        if ($afterId) {
            $query->where('id', '>', (int) $afterId)->limit(100);
        } else {
            $query->limit(50);
        }

        $messages = $query->get()->map(fn($m) => $this->formatMessage($m));

        if ($messages->isNotEmpty()) {
            $this->markConversationAsRead($user, $conversation);
        }

        return response()->json([
            'messages' => $messages,
        ]);
    }

    /**
     * Send a new message.
     */
    public function sendMessage(Request $request, ChatConversation $conversation): JsonResponse
    {
        $user = $request->user();
        if (!$this->userCanAccess($user, $conversation)) {
            return response()->json(['error' => 'Akses ditolak'], 403);
        }

        $validated = $request->validate([
            'message' => 'nullable|string|max:5000',
            'attachment' => 'nullable|file|mimes:jpeg,jpg,png,webp,gif,pdf,doc,docx,xls,xlsx,zip,txt|max:5120',
        ]);

        if (empty($validated['message']) && !$request->hasFile('attachment')) {
            return response()->json(['error' => 'Pesan atau lampiran harus diisi.'], 422);
        }

        $attachmentPath = null;
        $attachmentName = null;
        $attachmentType = null;
        $attachmentSize = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentName = $file->getClientOriginalName();
            $attachmentSize = $file->getSize();
            $mime = $file->getMimeType() ?: '';

            if (str_starts_with($mime, 'image/')) {
                $attachmentType = 'image';
            } elseif (in_array($mime, ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])) {
                $attachmentType = 'document';
            } elseif (in_array($mime, ['application/zip', 'application/x-zip-compressed'])) {
                $attachmentType = 'archive';
            } else {
                $attachmentType = 'file';
            }

            $attachmentPath = $file->store('chat_attachments', 'private');
        }

        $message = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'message' => $validated['message'] ?? null,
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
            'attachment_type' => $attachmentType,
            'attachment_size' => $attachmentSize,
        ]);

        $conversation->update(['last_message_at' => now()]);
        $this->markConversationAsRead($user, $conversation);

        // Notify other participants via WebPush (silent fail on error)
        $this->notifyParticipants($conversation, $message, $user);

        return response()->json([
            'message' => $this->formatMessage($message->load('sender:id,name,avatar,gender,divisi')),
        ], 201);
    }

    /**
     * Start or open 1-on-1 direct conversation with a user.
     */
    public function startDirect(Request $request, User $targetUser)
    {
        $authUser = $request->user();
        $conversation = ChatConversation::findOrCreateDirect($authUser->id, $targetUser->id);

        if ($request->wantsJson()) {
            return response()->json([
                'conversation' => $this->formatConversation($conversation->load(['users']), $authUser),
            ]);
        }

        return redirect()->route('chat.index', ['conversation' => $conversation->id]);
    }

    /**
     * Create custom group chat.
     */
    public function createGroup(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'member_ids' => 'required|array|min:1|max:50',
            'member_ids.*' => 'exists:users,id',
        ]);

        $conversation = ChatConversation::create([
            'type' => 'group',
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'created_by' => $user->id,
            'last_message_at' => now(),
        ]);

        $members = array_unique(array_merge([$user->id], $validated['member_ids']));
        $conversation->users()->attach($members);

        if ($request->wantsJson()) {
            return response()->json([
                'conversation' => $this->formatConversation($conversation->load(['users']), $user),
            ]);
        }

        return redirect()->route('chat.index', ['conversation' => $conversation->id]);
    }

    /**
     * Mark conversation as read.
     */
    public function markAsRead(Request $request, ChatConversation $conversation): JsonResponse
    {
        $user = $request->user();
        if (!$this->userCanAccess($user, $conversation)) {
            return response()->json(['error' => 'Akses ditolak'], 403);
        }

        $this->markConversationAsRead($user, $conversation);

        return response()->json(['success' => true]);
    }

    /**
     * Download or stream private chat attachment with authorization check.
     */
    public function attachment(Request $request, ChatMessage $message)
    {
        $user = $request->user();
        if (!$this->userCanAccess($user, $message->conversation)) {
            abort(403, 'Akses lampiran ditolak.');
        }

        if (!$message->attachment_path) {
            abort(404, 'Lampiran tidak ditemukan.');
        }

        $path = $message->attachment_path;
        if (str_contains($path, '..')) {
            abort(400, 'Invalid path');
        }

        $disk = Storage::disk('private')->exists($path)
            ? 'private'
            : (Storage::disk('public')->exists($path) ? 'public' : null);

        if (!$disk) {
            abort(404, 'File lampiran tidak ditemukan.');
        }

        return Storage::disk($disk)->response($path, $message->attachment_name);
    }

    /**
     * Lightweight unread total for badges across the app.
     */
    public function unreadTotal(Request $request): JsonResponse
    {
        $user = $request->user();
        $total = 0;

        $conversations = $this->getUserConversationsData($user);
        foreach ($conversations as $c) {
            $total += $c['unread_count'] ?? 0;
        }

        return response()->json(['unread_total' => $total]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function userCanAccess(User $user, ChatConversation $conversation): bool
    {
        if ($conversation->type === 'channel') {
            // Ruang umum publik terbuka untuk semua pengguna aktif
            if ($conversation->division_id === null && $conversation->name === 'Ruang Umum') {
                return true;
            }

            // Superadmin & Admin dapat mengakses kanal untuk keperluan moderasi/bantuan
            if ($user->hasAnyRole(['superadmin', 'admin']) || (method_exists($user, 'isActualSuperadmin') && $user->isActualSuperadmin())) {
                return true;
            }

            // Kanal divisi dibatasi hanya untuk anggota divisi yang bersangkutan
            if ($conversation->division_id !== null) {
                $divName = $conversation->division?->name;
                if ($divName && trim((string) $user->divisi) === trim((string) $divName)) {
                    return true;
                }
            }

            return $conversation->users()->where('users.id', $user->id)->exists();
        }

        return $conversation->users()->where('users.id', $user->id)->exists();
    }

    private function ensureChannelsForUser(User $user): void
    {
        // 1. General public room
        $general = ChatConversation::getOrCreateGeneralRoom();
        if (!$general->users()->where('users.id', $user->id)->exists()) {
            $general->users()->attach($user->id);
        }

        // 2. Division channel if user belongs to a division
        if (!empty($user->divisi)) {
            $divisionName = trim($user->divisi);
            $divisionRoom = ChatConversation::where('type', 'channel')
                ->where('name', 'Divisi ' . $divisionName)
                ->first();

            if (!$divisionRoom) {
                $divModel = Division::where('name', $divisionName)->first();
                $divisionRoom = ChatConversation::create([
                    'type' => 'channel',
                    'name' => 'Divisi ' . $divisionName,
                    'description' => 'Kanal koordinasi khusus Divisi ' . $divisionName,
                    'division_id' => $divModel?->id,
                ]);
            }

            if (!$divisionRoom->users()->where('users.id', $user->id)->exists()) {
                $divisionRoom->users()->attach($user->id);
            }
        }
    }

    private function getUserConversationsData(User $user): array
    {
        $conversations = ChatConversation::with(['users', 'lastMessage.sender', 'division'])
            ->whereHas('users', fn($q) => $q->where('users.id', $user->id))
            ->orderByRaw('COALESCE(last_message_at, updated_at) DESC')
            ->get();

        return $conversations->map(fn($c) => $this->formatConversation($c, $user))->toArray();
    }

    private function formatConversation(ChatConversation $conv, User $currentUser): array
    {
        $displayName = $conv->name;
        $displayAvatar = null;
        $isDirect = $conv->type === 'direct';
        $otherUser = null;

        if ($isDirect) {
            $otherUser = $conv->users->firstWhere('id', '!=', $currentUser->id) ?? $currentUser;
            $displayName = $otherUser->name ?? 'Pengguna';
            $displayAvatar = $otherUser->avatar ? (str_starts_with($otherUser->avatar, 'http') ? $otherUser->avatar : asset('storage/' . $otherUser->avatar)) : null;
        }

        $userPivot = $conv->users->firstWhere('id', $currentUser->id)?->pivot;
        $lastReadAt = $userPivot?->last_read_at;

        $unreadCount = $conv->unreadCountFor($currentUser->id, $lastReadAt);

        $lastMsg = $conv->lastMessage;
        $lastSnippet = '';
        if ($lastMsg) {
            if (!empty($lastMsg->message)) {
                $lastSnippet = $lastMsg->message;
            } elseif ($lastMsg->attachment_type === 'image') {
                $lastSnippet = '📷 [Gambar]';
            } else {
                $lastSnippet = '📎 [Lampiran]';
            }
        }

        return [
            'id' => $conv->id,
            'uuid' => $conv->uuid,
            'type' => $conv->type,
            'name' => $conv->name,
            'description' => $conv->description,
            'display_name' => $displayName,
            'display_avatar' => $displayAvatar,
            'other_user' => $otherUser ? [
                'id' => $otherUser->id,
                'name' => $otherUser->name,
                'divisi' => $otherUser->divisi,
                'gender' => $otherUser->gender,
            ] : null,
            'unread_count' => $unreadCount,
            'last_message_snippet' => $lastSnippet,
            'last_message_time' => $lastMsg ? $lastMsg->created_at->diffForHumans() : null,
            'last_message_at' => $conv->last_message_at?->toIso8601String(),
            'members_count' => $conv->users->count(),
        ];
    }

    private function getConversationMessages(ChatConversation $conversation, int $limit = 50): array
    {
        return $conversation->messages()
            ->with(['sender:id,name,avatar,gender,divisi'])
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn($m) => $this->formatMessage($m))
            ->toArray();
    }

    private function formatMessage(ChatMessage $m): array
    {
        $sender = $m->sender;
        return [
            'id' => $m->id,
            'conversation_id' => $m->conversation_id,
            'user_id' => $m->user_id,
            'message' => $m->message,
            'attachment_url' => $m->attachment_url,
            'attachment_name' => $m->attachment_name,
            'attachment_type' => $m->attachment_type,
            'attachment_size' => $m->attachment_size,
            'sender' => $sender ? [
                'id' => $sender->id,
                'name' => $sender->name,
                'avatar' => $sender->avatar ? (str_starts_with($sender->avatar, 'http') ? $sender->avatar : asset('storage/' . $sender->avatar)) : null,
                'gender' => $sender->gender,
                'divisi' => $sender->divisi,
            ] : null,
            'created_at' => $m->created_at->format('H:i'),
            'date' => $m->created_at->translatedFormat('d M Y'),
            'timestamp' => $m->created_at->toIso8601String(),
        ];
    }

    private function markConversationAsRead(User $user, ChatConversation $conversation): void
    {
        // Hanya update pivot jika user memang sudah terdaftar sebagai anggota percakapan
        if ($conversation->users()->where('users.id', $user->id)->exists()) {
            $conversation->users()->updateExistingPivot($user->id, [
                'last_read_at' => now(),
            ]);
        }
    }

    private function notifyParticipants(ChatConversation $conversation, ChatMessage $message, User $sender): void
    {
        // Kanal umum publik tidak memicu WebPush massal ke seluruh pengguna
        if ($conversation->type === 'channel' && $conversation->division_id === null) {
            return;
        }

        try {
            $participants = $conversation->users()
                ->where('users.id', '!=', $sender->id)
                ->wherePivot('is_muted', false)
                ->get();

            if ($participants->isEmpty()) {
                return;
            }

            $title = $sender->name;
            if ($conversation->type !== 'direct') {
                $title .= " ({$conversation->name})";
            }

            $body = !empty($message->message)
                ? (strlen($message->message) > 80 ? substr($message->message, 0, 77) . '...' : $message->message)
                : 'Mengirim lampiran berkas/gambar';

            $chatUrl = route('chat.index', ['conversation' => $conversation->id]);

            // Kirim WebPush secara async setelah response dikirimkan ke client agar tidak memblokir request
            dispatch(function () use ($participants, $title, $body, $chatUrl) {
                foreach ($participants as $participant) {
                    try {
                        $participant->notify(new GenericWebPushNotification(
                            title: $title,
                            body: $body,
                            url: $chatUrl,
                        ));
                    } catch (\Throwable $inner) {
                        // ignore individual push error
                    }
                }
            })->afterResponse();
        } catch (\Throwable $e) {
            Log::warning('Gagal mengirim WebPush Chat: ' . $e->getMessage());
        }
    }
}
