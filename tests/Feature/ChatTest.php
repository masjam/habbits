<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    protected User $userA;
    protected User $userB;
    protected User $userC;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->userA = User::factory()->create([
            'name' => 'Ahmad Pegawai',
            'email' => 'ahmad@example.com',
            'gender' => 'L',
            'divisi' => 'Kurikulum',
            'password' => Hash::make('password'),
        ]);
        $this->userA->assignRole('user');

        $this->userB = User::factory()->create([
            'name' => 'Budi Pegawai',
            'email' => 'budi@example.com',
            'gender' => 'L',
            'divisi' => 'Tata Usaha',
            'password' => Hash::make('password'),
        ]);
        $this->userB->assignRole('user');

        $this->userC = User::factory()->create([
            'name' => 'Citra Pegawai',
            'email' => 'citra@example.com',
            'gender' => 'P',
            'divisi' => 'Kurikulum',
            'password' => Hash::make('password'),
        ]);
        $this->userC->assignRole('user');
    }

    public function test_user_can_access_chat_index_and_auto_joins_channels(): void
    {
        $response = $this->actingAs($this->userA)->get(route('chat.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Chat/Index')
            ->has('conversations')
            ->has('contacts')
            ->has('divisions')
        );

        // General channel exists
        $generalRoom = ChatConversation::where('type', 'channel')->whereNull('division_id')->first();
        $this->assertNotNull($generalRoom);
        $this->assertTrue($generalRoom->users->contains($this->userA->id));

        // Division channel for userA ("Divisi Kurikulum") exists
        $divRoom = ChatConversation::where('type', 'channel')->where('name', 'Divisi Kurikulum')->first();
        $this->assertNotNull($divRoom);
        $this->assertTrue($divRoom->users->contains($this->userA->id));
    }

    public function test_user_can_start_direct_chat_with_another_user(): void
    {
        $response = $this->actingAs($this->userA)->post(route('chat.direct', $this->userB->id));

        $response->assertRedirect();

        $conv = ChatConversation::where('type', 'direct')->first();
        $this->assertNotNull($conv);
        $this->assertTrue($conv->users->contains($this->userA->id));
        $this->assertTrue($conv->users->contains($this->userB->id));

        // Subsequent call reuses the existing conversation
        $this->actingAs($this->userA)->post(route('chat.direct', $this->userB->id));
        $this->assertEquals(1, ChatConversation::where('type', 'direct')->count());
    }

    public function test_user_can_send_text_message(): void
    {
        $conv = ChatConversation::findOrCreateDirect($this->userA->id, $this->userB->id);

        $response = $this->actingAs($this->userA)->postJson(route('chat.send', $conv->id), [
            'message' => 'Halo Budi, bagaimana kabar?',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('message.message', 'Halo Budi, bagaimana kabar?');
        $response->assertJsonPath('message.user_id', $this->userA->id);

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conv->id,
            'user_id' => $this->userA->id,
            'message' => 'Halo Budi, bagaimana kabar?',
        ]);
    }

    public function test_user_can_send_file_attachment(): void
    {
        Storage::fake('private');

        $conv = ChatConversation::findOrCreateDirect($this->userA->id, $this->userB->id);
        $file = UploadedFile::fake()->image('laporan.png', 200, 200);

        $response = $this->actingAs($this->userA)->post(route('chat.send', $conv->id), [
            'message' => 'Berikut bukti gambarnya.',
            'attachment' => $file,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('message.attachment_name', 'laporan.png');
        $response->assertJsonPath('message.attachment_type', 'image');

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conv->id,
            'attachment_name' => 'laporan.png',
            'attachment_type' => 'image',
        ]);
    }

    public function test_empty_message_without_attachment_is_rejected(): void
    {
        $conv = ChatConversation::findOrCreateDirect($this->userA->id, $this->userB->id);

        $response = $this->actingAs($this->userA)->postJson(route('chat.send', $conv->id), [
            'message' => '',
        ]);

        $response->assertStatus(422);
    }

    public function test_polling_messages_with_after_id(): void
    {
        $conv = ChatConversation::findOrCreateDirect($this->userA->id, $this->userB->id);

        $msg1 = ChatMessage::create([
            'conversation_id' => $conv->id,
            'user_id' => $this->userA->id,
            'message' => 'Pesan 1',
        ]);

        $msg2 = ChatMessage::create([
            'conversation_id' => $conv->id,
            'user_id' => $this->userB->id,
            'message' => 'Pesan 2',
        ]);

        // Poll messages after msg1
        $response = $this->actingAs($this->userA)->getJson(route('chat.messages', [
            'conversation' => $conv->id,
            'after_id' => $msg1->id,
        ]));

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'messages');
        $response->assertJsonPath('messages.0.id', $msg2->id);
        $response->assertJsonPath('messages.0.message', 'Pesan 2');
    }

    public function test_user_cannot_read_private_chat_between_others(): void
    {
        $conv = ChatConversation::findOrCreateDirect($this->userA->id, $this->userB->id);

        // UserC attempts to view or send to conversation between A and B
        $response = $this->actingAs($this->userC)->getJson(route('chat.messages', $conv->id));
        $response->assertStatus(403);

        $sendResponse = $this->actingAs($this->userC)->postJson(route('chat.send', $conv->id), [
            'message' => 'Mengintip',
        ]);
        $sendResponse->assertStatus(403);
    }

    public function test_user_can_create_group_chat(): void
    {
        $response = $this->actingAs($this->userA)->postJson(route('chat.group.create'), [
            'name' => 'Panitia Ramadhan 1448H',
            'description' => 'Koordinasi kegiatan syiar ramadhan',
            'member_ids' => [$this->userB->id, $this->userC->id],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('conversation.name', 'Panitia Ramadhan 1448H');

        $group = ChatConversation::where('name', 'Panitia Ramadhan 1448H')->first();
        $this->assertNotNull($group);
        $this->assertEquals('group', $group->type);
        $this->assertCount(3, $group->users); // userA, userB, userC
    }

    public function test_unread_count_endpoint(): void
    {
        $conv = ChatConversation::findOrCreateDirect($this->userA->id, $this->userB->id);

        // userB sends message to conv
        ChatMessage::create([
            'conversation_id' => $conv->id,
            'user_id' => $this->userB->id,
            'message' => 'Halo Ahmad!',
        ]);

        $response = $this->actingAs($this->userA)->getJson(route('chat.unread-total'));
        $response->assertStatus(200);
        $response->assertJsonPath('unread_total', 1);

        // Mark as read
        $this->actingAs($this->userA)->postJson(route('chat.read', $conv->id));

        $responseAfter = $this->actingAs($this->userA)->getJson(route('chat.unread-total'));
        $responseAfter->assertStatus(200);
        $responseAfter->assertJsonPath('unread_total', 0);
    }

    public function test_unauthorized_user_cannot_mark_others_chat_as_read_and_cannot_auto_join(): void
    {
        $conv = ChatConversation::findOrCreateDirect($this->userA->id, $this->userB->id);

        // UserC attempts to mark conversation between A & B as read
        $response = $this->actingAs($this->userC)->postJson(route('chat.read', $conv->id));
        $response->assertStatus(403);

        // UserC must NOT be added to participants
        $this->assertFalse($conv->fresh()->users->contains($this->userC->id));
    }

    public function test_user_cannot_access_channel_of_different_division(): void
    {
        $tuDivision = \App\Models\Division::create(['name' => 'Tata Usaha']);
        $tuChannel = ChatConversation::create([
            'type' => 'channel',
            'name' => 'Divisi Tata Usaha',
            'division_id' => $tuDivision->id,
        ]);
        $tuChannel->users()->attach($this->userB->id);

        // UserA (Kurikulum) cannot read or poll messages in TU channel
        $responseA = $this->actingAs($this->userA)->getJson(route('chat.messages', $tuChannel->id));
        $responseA->assertStatus(403);

        // UserB (Tata Usaha) can read messages
        $responseB = $this->actingAs($this->userB)->getJson(route('chat.messages', $tuChannel->id));
        $responseB->assertStatus(200);
    }

    public function test_private_chat_attachment_requires_conversation_membership(): void
    {
        Storage::fake('private');

        $conv = ChatConversation::findOrCreateDirect($this->userA->id, $this->userB->id);
        $file = UploadedFile::fake()->image('rahasia.png', 100, 100);

        $sendResponse = $this->actingAs($this->userA)->post(route('chat.send', $conv->id), [
            'message' => 'Dokumen privat',
            'attachment' => $file,
        ]);
        $sendResponse->assertStatus(201);

        $msgId = $sendResponse->json('message.id');
        $msg = ChatMessage::findOrFail($msgId);

        // UserC (outside conversation) must be forbidden
        $responseC = $this->actingAs($this->userC)->get(route('chat.attachment', $msg->id));
        $responseC->assertStatus(403);

        // UserB (participant) can view/download
        $responseB = $this->actingAs($this->userB)->get(route('chat.attachment', $msg->id));
        $responseB->assertStatus(200);
    }

    public function test_contact_list_does_not_expose_nip(): void
    {
        $this->userB->update(['nip' => '198501012010011001']);

        $response = $this->actingAs($this->userA)->get(route('chat.index'));
        $response->assertStatus(200);

        $response->assertInertia(function (Assert $page) {
            $page->has('contacts', function (Assert $contacts) {
                $contacts->each(fn (Assert $contact) => $contact->missing('nip')->etc());
            });
        });
    }

    public function test_group_chat_rejects_exceeding_member_limit(): void
    {
        // Try to create group with 51 members
        $memberIds = range(1, 51);

        $response = $this->actingAs($this->userA)->postJson(route('chat.group.create'), [
            'name' => 'Grup Raksasa',
            'member_ids' => $memberIds,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('member_ids');
    }
}
