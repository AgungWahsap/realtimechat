<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Volt::route('/login', 'login')->name('login');

Volt::route('/', 'chat-room')->middleware('auth')->name('chat');
