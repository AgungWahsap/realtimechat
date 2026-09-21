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


{{-- ============================================================
     CHAT ROOM — two-column layout (contacts | messages)
     Colours matched to the reference design:
       sidebar bg  : #111b27  /  header bg : #0e1f2e
       chat bg     : #efeae2
       sent bubble : #1e3a5a  (dark navy, white text)
       recv bubble : #ffffff
       accent/send : #f97316  (orange)
     ============================================================ --}}
<div class="flex h-screen overflow-hidden bg-[#111b27] font-sans antialiased">

    {{-- ─────────────────────────────────────────────────────────
         LEFT — CONTACT SIDEBAR
         ───────────────────────────────────────────────────────── --}}
    <aside class="flex w-[360px] shrink-0 flex-col border-r border-[#1f2f3d]">

        {{-- Sidebar header --}}
        <header class="flex items-center justify-between bg-[#0e1f2e] px-4 py-3">
            {{-- Auth user avatar + name --}}
            <div class="flex items-center gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-[#1e3a5a] text-sm font-bold text-white">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </div>
                <div>
                    <p class="text-sm font-semibold text-white">{{ auth()->user()->name }}</p>
                    <span class="rounded-full bg-[#1e3a5a] px-2 py-0.5 text-[10px] font-medium uppercase tracking-wider text-sky-300">
                        {{ auth()->user()->role === 'it_support' ? 'IT Support' : 'Staff' }}
                    </span>
                </div>
            </div>
            {{-- Icon actions --}}
            <div class="flex items-center gap-3 text-slate-400">
                <button class="rounded-full p-1.5 transition hover:bg-[#1a2d3e] hover:text-white" title="Baru">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                </button>
                <button class="rounded-full p-1.5 transition hover:bg-[#1a2d3e] hover:text-white" title="Menu">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/>
                    </svg>
                </button>
            </div>
        </header>

        {{-- Search bar --}}
        <div class="bg-[#111b27] px-3 py-2">
            <div class="flex items-center gap-2 rounded-lg bg-[#1a2d3e] px-3 py-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                </svg>
                <input
                    type="text"
                    placeholder="Cari atau mulai percakapan baru"
                    class="w-full bg-transparent text-sm text-slate-300 placeholder-slate-500 outline-none"
                />
            </div>
        </div>

        {{-- Contact list --}}
        <div class="flex-1 overflow-y-auto bg-[#111b27]">
            @forelse($contacts as $contact)
                <button
                    wire:click="loadConversation({{ $contact->id }})"
                    id="contact-{{ $contact->id }}"
                    class="group flex w-full items-center gap-3 border-b border-[#1f2f3d] px-4 py-3 text-left transition-colors
                           hover:bg-[#1a2d3e]
                           {{ $activeContact?->id === $contact->id
                               ? 'border-l-[3px] border-l-[#00a884] bg-[#1a2d3e] pl-[calc(1rem-3px)]'
                               : 'border-l-[3px] border-l-transparent' }}"
                >
                    {{-- Avatar --}}
                    <div class="relative shrink-0">
                        <div class="flex size-12 items-center justify-center rounded-full bg-gradient-to-br from-sky-600 to-blue-800 text-base font-bold text-white">
                            {{ strtoupper(substr($contact->name, 0, 1)) }}
                        </div>
                        {{-- Online indicator --}}
                        <span class="absolute bottom-0 right-0 size-3 rounded-full border-2 border-[#111b27] bg-emerald-400"></span>
                    </div>

                    {{-- Name + preview --}}
                    <div class="min-w-0 flex-1">
                        <div class="flex items-baseline justify-between">
                            <p class="truncate text-sm font-semibold text-white">{{ $contact->name }}</p>
                        </div>
                        <p class="truncate text-xs text-slate-400">
                            {{ $contact->role === 'it_support' ? 'IT Support' : 'Staff' }}
                        </p>
                    </div>
                </button>
            @empty
                <div class="flex flex-col items-center justify-center py-16 text-slate-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mb-3 size-10 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
                    </svg>
                    <p class="text-sm">Tidak ada kontak tersedia</p>
                </div>
            @endforelse
        </div>
    </aside>

    {{-- ─────────────────────────────────────────────────────────
         RIGHT — CHAT PANEL
         ───────────────────────────────────────────────────────── --}}
    <main class="flex flex-1 flex-col overflow-hidden">
        @if($activeContact)

            {{-- Chat header --}}
            <header class="flex items-center justify-between bg-[#0e1f2e] px-5 py-3 shadow-md">
                <div class="flex items-center gap-3">
                    {{-- Contact avatar --}}
                    <div class="relative">
                        <div class="flex size-10 items-center justify-center rounded-full bg-gradient-to-br from-sky-600 to-blue-800 text-sm font-bold text-white">
                            {{ strtoupper(substr($activeContact->name, 0, 1)) }}
                        </div>
                        <span class="absolute bottom-0 right-0 size-2.5 rounded-full border-2 border-[#0e1f2e] bg-emerald-400"></span>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-semibold text-white">{{ $activeContact->name }}</p>
                            <span class="rounded-sm bg-sky-700 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-sky-200">
                                {{ $activeContact->role === 'it_support' ? 'IT Support' : 'Staff' }}
                            </span>
                        </div>
                        <p class="text-xs text-emerald-400">Online</p>
                    </div>
                </div>

                {{-- Header actions --}}
                <div class="flex items-center gap-3 text-slate-400">
                    <button class="rounded-full p-1.5 transition hover:bg-[#1a2d3e] hover:text-white" title="Cari">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                        </svg>
                    </button>
                    <button class="rounded-full p-1.5 transition hover:bg-[#1a2d3e] hover:text-white" title="Menu">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="currentColor" viewBox="0 0 24 24">
                            <circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/>
                        </svg>
                    </button>
                </div>
            </header>

            {{-- Messages area — Alpine handles auto-scroll --}}
            <div
                x-data="{
                    scrollToBottom() {
                        this.$nextTick(() => {
                            this.$el.scrollTop = this.$el.scrollHeight;
                        });
                    }
                }"
                x-init="scrollToBottom()"
                x-effect="$watch('$wire.messages', () => scrollToBottom())"
                id="chat-messages"
                class="flex-1 overflow-y-auto bg-[#efeae2] px-6 py-5 space-y-1"
            >
                {{-- Date separator --}}
                <div class="my-4 flex items-center justify-center">
                    <span class="rounded-md bg-white/70 px-3 py-1 text-[11px] font-medium text-slate-500 shadow-sm backdrop-blur-sm">
                        HARI INI
                    </span>
                </div>

                @forelse($messages as $message)
                    @php $isMine = $message->sender_id === auth()->id(); @endphp

                    <div class="flex {{ $isMine ? 'justify-end' : 'justify-start' }} px-2">
                        <div class="
                            group relative max-w-[65%] rounded-2xl px-4 py-2.5 shadow-sm
                            {{ $isMine
                                ? 'rounded-tr-sm bg-[#1e3a5a] text-white'
                                : 'rounded-tl-sm bg-white text-slate-800' }}
                        ">
                            {{-- Bubble tail --}}
                            @if($isMine)
                                <span class="absolute -right-1.5 top-0 size-3 bg-[#1e3a5a]"
                                      style="clip-path: polygon(0 0, 0% 100%, 100% 0)"></span>
                            @else
                                <span class="absolute -left-1.5 top-0 size-3 bg-white"
                                      style="clip-path: polygon(100% 0, 0 0, 100% 100%)"></span>
                            @endif

                            <p class="text-sm leading-relaxed">{{ $message->message_text }}</p>

                            {{-- Timestamp + read receipt --}}
                            <div class="mt-1 flex items-center justify-end gap-1">
                                <span class="text-[10px] {{ $isMine ? 'text-sky-300/70' : 'text-slate-400' }}">
                                    {{ \Carbon\Carbon::parse($message->created_at)->format('H:i') }}
                                </span>
                                @if($isMine)
                                    {{-- Read receipts: double-check when read --}}
                                    @if($message->is_read)
                                        <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5 text-sky-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M1.5 12.5l5 5L18.5 6M6 12.5l5 5"/>
                                        </svg>
                                    @else
                                        <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5 text-sky-300/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.5l5 5 10-11"/>
                                        </svg>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="flex flex-1 flex-col items-center justify-center py-20 text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mb-3 size-12 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.4" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                        <p class="text-sm">Belum ada pesan. Mulai percakapan!</p>
                    </div>
                @endforelse
            </div>

            {{-- Message input bar --}}
            <div class="flex items-center gap-3 border-t border-[#1f2f3d] bg-[#f0f2f5] px-4 py-3">
                {{-- Emoji --}}
                <button class="shrink-0 text-slate-500 transition hover:text-slate-700" title="Emoji">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </button>

                {{-- Attachment --}}
                <button class="shrink-0 text-slate-500 transition hover:text-slate-700" title="Lampiran">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                    </svg>
                </button>

                {{-- Text input --}}
                <input
                    wire:model.live="newMessage"
                    wire:keydown.enter="sendMessage"
                    type="text"
                    id="message-input"
                    placeholder="Ketik pesan di sini..."
                    class="flex-1 rounded-full bg-white px-5 py-2.5 text-sm text-slate-800 shadow-sm outline-none ring-1 ring-transparent placeholder-slate-400
                           focus:ring-[#00a884] transition"
                    autocomplete="off"
                />

                {{-- Send button (orange circle) --}}
                <button
                    wire:click="sendMessage"
                    id="send-message-btn"
                    class="flex size-11 shrink-0 items-center justify-center rounded-full bg-[#f97316] text-white shadow-lg
                           transition hover:bg-[#ea6c10] active:scale-95 disabled:opacity-50"
                    wire:loading.attr="disabled"
                    title="Kirim"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-5 translate-x-0.5" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M3.478 2.405a.75.75 0 00-.926.94l2.432 7.905H13.5a.75.75 0 010 1.5H4.984l-2.432 7.905a.75.75 0 00.926.94 60.519 60.519 0 0018.445-8.986.75.75 0 000-1.218A60.517 60.517 0 003.478 2.405z"/>
                    </svg>
                </button>
            </div>

            {{-- Validation error --}}
            @error('newMessage')
                <p class="bg-red-50 px-5 py-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror

        @else
            {{-- Empty state — no contact selected --}}
            <div class="flex flex-1 flex-col items-center justify-center gap-4 bg-[#efeae2] text-slate-500">
                <div class="flex size-24 items-center justify-center rounded-full bg-[#1e3a5a]/10">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-12 text-[#1e3a5a]/40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.4" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                </div>
                <div class="text-center">
                    <p class="text-base font-semibold text-slate-600">Helpdesk Kampus</p>
                    <p class="mt-1 text-sm text-slate-400">Pilih kontak di sebelah kiri untuk memulai percakapan.</p>
                </div>
            </div>
        @endif
    </main>
</div>

