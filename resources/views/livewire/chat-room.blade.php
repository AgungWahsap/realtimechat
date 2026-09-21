<?php

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
    /** The authenticated user's ID, exposed so the Echo attribute can reference it. */
    public int $userId;

    /** List of contacts available to chat with. */
    public Collection $contacts;

    /** The currently selected contact, or null when none is chosen. */
    public ?User $activeContact = null;

    /** Messages in the active conversation. */
    public Collection $messages;

    /** The text being composed by the user. */
    public string $newMessage = '';

    // -------------------------------------------------------------------------
    // Lifecycle
    // -------------------------------------------------------------------------

    /**
     * Boot the component: load the contact list and initialise message list.
     */
    public function mount(): void
    {
        /** @var User $authUser */
        $authUser = auth()->user();

        $this->userId = $authUser->id;

        $this->contacts = $authUser->role === 'it_support'
            ? User::where('role', 'staff')->orderBy('name')->get()
            : User::where('role', 'it_support')->orderBy('name')->get();

        $this->messages = collect();

        if ($this->contacts->isNotEmpty()) {
            $this->loadConversation($this->contacts->first()->id);
        }
    }

    // -------------------------------------------------------------------------
    // Actions
    // -------------------------------------------------------------------------

    /**
     * Switch the active conversation to the given user and mark incoming
     * messages as read.
     */
    public function loadConversation(int $userId): void
    {
        $this->activeContact = User::findOrFail($userId);

        // Mark all unread messages sent by this contact to the auth user as read.
        Message::query()
            ->where('sender_id', $userId)
            ->where('receiver_id', $this->userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        // Load the full conversation ordered chronologically.
        $this->messages = Message::query()
            ->where(function ($query) use ($userId) {
                $query->where('sender_id', $this->userId)
                    ->where('receiver_id', $userId);
            })
            ->orWhere(function ($query) use ($userId) {
                $query->where('sender_id', $userId)
                    ->where('receiver_id', $this->userId);
            })
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Persist the composed message, reset the input, and broadcast via Reverb.
     */
    public function sendMessage(): void
    {
        $this->validate(['newMessage' => ['required', 'string', 'max:5000']]);

        if (! $this->activeContact) {
            return;
        }

        $message = Message::create([
            'sender_id' => $this->userId,
            'receiver_id' => $this->activeContact->id,
            'message_text' => $this->newMessage,
        ]);

        // Append to local collection immediately so the sender sees it at once.
        $this->messages->push($message);

        $this->reset('newMessage');

        MessageSent::dispatch($message);
    }

    // -------------------------------------------------------------------------
    // Reverb / Echo listener
    // -------------------------------------------------------------------------

    /**
     * Receive incoming messages broadcast on the authenticated user's private
     * channel and append them to the current conversation when they originate
     * from the active contact.
     *
     * Channel  : private-chat.{userId}   (matches PrivateChannel('chat.{receiver_id}'))
     * Event    : message.sent            (matches broadcastAs() = 'message.sent')
     *
     * @param  array{id: int, sender_id: int, receiver_id: int, message_text: string, created_at: string}  $event
     */
    #[On('echo-private:chat.{userId},message.sent')]
    public function handleMessageReceived(array $event): void
    {
        // Only append when the conversation with the sender is currently open.
        if ($this->activeContact?->id !== $event['sender_id']) {
            return;
        }

        $this->messages->push(new Message([
            'id' => $event['id'],
            'sender_id' => $event['sender_id'],
            'receiver_id' => $event['receiver_id'],
            'message_text' => $event['message_text'],
            'is_read' => true,
            'created_at' => $event['created_at'],
            'updated_at' => $event['created_at'],
        ]));
    }
}; ?>

<div>
    //
</div>
