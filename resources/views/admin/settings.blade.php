@extends('layouts.app')

@section('title', 'Admin Settings')

@section('content')
    <div class="min-h-screen flex items-center justify-center px-4 py-10">
        <div class="w-full max-w-[520px] rounded-xl border border-slate-800 bg-slate-900/60 p-6 shadow">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-base font-semibold text-slate-100">Admin Settings</h1>
                <a href="{{ route('chart') }}" class="text-xs text-slate-300 hover:text-white">Back to chart</a>
            </div>

            <div class="mt-3 text-xs text-slate-400">
                Signed in as: <span class="font-semibold text-slate-200">{{ auth()->user()->email }}</span>
            </div>

            @if (session('status'))
                <div class="mt-3 rounded-lg border border-emerald-900/50 bg-emerald-950/30 px-3 py-2 text-xs text-emerald-200">
                    {{ session('status') }}
                </div>
            @endif

            <div class="mt-4 border-t border-slate-800 pt-4">
                <form class="space-y-4" method="POST" action="{{ route('admin.password.update') }}">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="current_password" class="block text-xs text-slate-400">Current password</label>
                        <input
                            id="current_password"
                            name="current_password"
                            type="password"
                            autocomplete="current-password"
                            required
                            class="mt-2 block w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-slate-100 placeholder:text-slate-500 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                        />
                        @error('current_password')
                            <div class="mt-2 text-xs text-red-300">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="block text-xs text-slate-400">New password</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="new-password"
                            required
                            class="mt-2 block w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-slate-100 placeholder:text-slate-500 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                        />
                        @error('password')
                            <div class="mt-2 text-xs text-red-300">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-xs text-slate-400">Confirm new password</label>
                        <input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            required
                            class="mt-2 block w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-slate-100 placeholder:text-slate-500 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                        />
                    </div>

                    <button
                        type="submit"
                        class="inline-flex w-full items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                    >
                        Update password
                    </button>
                </form>

                <form class="mt-4" method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex w-full items-center justify-center rounded-lg border border-slate-700 bg-slate-800 px-4 py-2 text-sm font-semibold text-slate-100 hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-500/30"
                    >
                        Logout
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection
