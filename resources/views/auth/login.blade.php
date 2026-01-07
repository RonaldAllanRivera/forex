@extends('layouts.app')

@section('title', 'Login')

@section('content')
    <div class="min-h-screen flex items-center justify-center px-4 py-10">
        <div class="w-full max-w-[420px] rounded-xl border border-slate-800 bg-slate-900/60 p-6 shadow">
            <h1 class="text-base font-semibold text-slate-100">Sign in</h1>

            <form class="mt-4 space-y-4" method="POST" action="{{ route('login.store') }}">
                @csrf

                <div>
                    <label for="email" class="block text-xs text-slate-400">Email</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        autocomplete="username"
                        required
                        class="mt-2 block w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-slate-100 placeholder:text-slate-500 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                    />
                    @error('email')
                        <div class="mt-2 text-xs text-red-300">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-xs text-slate-400">Password</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        required
                        class="mt-2 block w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-slate-100 placeholder:text-slate-500 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                    />
                    @error('password')
                        <div class="mt-2 text-xs text-red-300">{{ $message }}</div>
                    @enderror
                </div>

                <div class="flex items-center justify-between gap-4">
                    <label class="inline-flex items-center gap-2 text-xs text-slate-400">
                        <input
                            type="checkbox"
                            name="remember"
                            value="1"
                            class="h-4 w-4 rounded border-slate-700 bg-slate-950 text-blue-600 focus:ring-blue-500/30"
                        />
                        Remember me
                    </label>

                    <span class="text-xs text-slate-400">Use an existing admin account.</span>
                </div>

                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                >
                    Login
                </button>
            </form>
        </div>
    </div>
@endsection
