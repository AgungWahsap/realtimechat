<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Livewire\Attributes\Validate;

new class extends Component {
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required')]
    public string $password = '';

    public bool $remember = false;

    public function login()
    {
        $this->validate();

        if (Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            session()->regenerate();
            return redirect()->intended(route('chat'));
        }

        $this->addError('email', 'Email atau kata sandi salah.');
    }
}; ?>

<div class="flex min-h-screen items-center justify-center bg-slate-50 font-sans antialiased">
    <div class="w-full max-w-[400px] rounded-2xl bg-white p-8 shadow-xl shadow-slate-200/50">
        {{-- Logo & Title --}}
        <div class="mb-8 text-center">
            <div class="mx-auto mb-3 flex size-12 items-center justify-center rounded-xl bg-blue-600 text-white shadow-md">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-6" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M4.913 2.658c2.075-.27 4.19-.408 6.337-.408 2.147 0 4.262.139 6.337.408 1.922.25 3.291 1.861 3.405 3.727a4.403 4.403 0 00-1.032-.211 50.89 50.89 0 00-8.42 0c-2.358.196-4.04 2.19-4.04 4.434v4.286a4.47 4.47 0 002.433 3.984L7.28 21.53A.75.75 0 016 21v-4.03a48.527 48.527 0 01-1.087-.128C2.905 16.58 1.5 14.833 1.5 12.862V6.638c0-1.97 1.405-3.718 3.413-3.979z" />
                    <path d="M15.75 7.5c-1.376 0-2.739.057-4.086.169C10.124 7.797 9 9.103 9 10.609v4.285c0 1.507 1.128 2.814 2.67 2.94 1.243.102 2.5.157 3.768.165l2.782 2.781a.75.75 0 001.28-.53v-2.39l.33-.026c1.542-.125 2.67-1.433 2.67-2.94v-4.286c0-1.505-1.125-2.811-2.664-2.94A49.392 49.392 0 0015.75 7.5z" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">CHAT</h1>
            <p class="mt-1 text-sm text-slate-500">Masuk ke akun perpesanan real-time Anda</p>
        </div>

        {{-- Form --}}
        <form wire:submit="login" class="space-y-4">
            <div>
                <label for="email" class="mb-1.5 block text-xs font-semibold text-slate-700">Email atau Username</label>
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <input wire:model="email" id="email" type="email" required autofocus
                        class="block w-full rounded-lg border-0 bg-slate-100 py-2.5 pl-10 pr-4 text-sm text-slate-900 ring-1 ring-inset ring-transparent placeholder:text-slate-500 focus:bg-white focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6"
                        placeholder="nama@email.com atau username">
                </div>
                @error('email') <span class="mt-1 block text-xs text-red-500">{{ $message }}</span> @enderror
            </div>

            <div>
                <div class="mb-1.5 flex items-center justify-between">
                    <label for="password" class="block text-xs font-semibold text-slate-700">Kata Sandi</label>
                    <a href="#" class="text-[10px] font-semibold text-orange-600 hover:text-orange-500">Lupa kata sandi?</a>
                </div>
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <input wire:model="password" id="password" type="password" required
                        class="block w-full rounded-lg border-0 bg-slate-100 py-2.5 pl-10 pr-10 text-sm text-slate-900 ring-1 ring-inset ring-transparent placeholder:text-slate-500 focus:bg-white focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:text-sm sm:leading-6"
                        placeholder="••••••••">
                    <button type="button" class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="flex items-center justify-between pt-2">
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="$toggle('remember')" class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2 {{ $remember ? 'bg-blue-600' : 'bg-slate-200' }}" role="switch" aria-checked="{{ $remember ? 'true' : 'false' }}">
                        <span aria-hidden="true" class="pointer-events-none inline-block size-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $remember ? 'translate-x-4' : 'translate-x-0' }}"></span>
                    </button>
                    <span class="text-xs text-slate-600" id="remember-label">Ingat saya</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="size-1.5 rounded-full bg-green-500"></span>
                    <span class="text-[10px] text-slate-500">30 hari aktif</span>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-lg bg-orange-600 px-3 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-orange-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orange-600 transition">
                    <span wire:loading.remove>Masuk ke Obrolan</span>
                    <span wire:loading>Memproses...</span>
                    <svg wire:loading.remove xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </button>
            </div>
        </form>

        <div class="mt-8 relative">
            <div class="absolute inset-0 flex items-center" aria-hidden="true">
                <div class="w-full border-t border-slate-200"></div>
            </div>
            <div class="relative flex justify-center">
                <span class="bg-white px-3 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Atau masuk dengan</span>
            </div>
        </div>

        <div class="mt-8 text-center text-xs text-slate-500">
            Belum punya akun? <a href="#" class="font-semibold text-orange-600 hover:text-orange-500">Daftar sekarang</a>
        </div>
    </div>
</div>
