@extends('layouts.app', ['title' => 'My Profile · KGOLD AUTOMATION TEAM KPI'])

@php
    $profilePages = $me->isAdmin() ? $allPages : $me->pages;
@endphp

@section('content')
    <div>
        <h1 class="text-lg font-bold tracking-tight text-ink">My Profile</h1>
        <p class="text-xs text-muted">Update your name, email, or optionally change your password</p>
    </div>

    <div class="grid lg:grid-cols-12 gap-5 items-start">
        {{-- Profile & Optional Password Form --}}
        <section class="card lg:col-span-7 overflow-hidden">
            <div class="card-head">
                <div>
                    <h2 class="card-title"><x-icon name="user" class="size-4 text-primary"/> Account Details</h2>
                    <p class="card-sub">Leave the password fields empty if you do not want to change your password</p>
                </div>
            </div>

            <form method="POST" action="{{ route('profile.update') }}" class="p-6 space-y-5">
                @csrf
                <div class="grid sm:grid-cols-2 gap-4">
                    <label class="flex flex-col gap-1">
                        <span class="text-[11px] font-semibold text-muted uppercase">Full Name *</span>
                        <input type="text" name="name" value="{{ old('name', $me->name) }}" required class="field">
                    </label>

                    <label class="flex flex-col gap-1">
                        <span class="text-[11px] font-semibold text-muted uppercase">Email Address *</span>
                        <input type="email" name="email" value="{{ old('email', $me->email) }}" required class="field">
                    </label>
                </div>

                <div class="pt-4 border-t border-line space-y-4">
                    <div>
                        <h3 class="text-sm font-semibold text-ink">Change Password <span class="text-xs font-normal text-muted">(Optional)</span></h3>
                        <p class="text-[11px] text-muted">Only fill these in when you want to set a new password</p>
                    </div>

                    <div class="grid sm:grid-cols-3 gap-3.5">
                        <label class="flex flex-col gap-1">
                            <span class="text-[11px] font-semibold text-muted uppercase">Current Password</span>
                            <x-password-field name="current_password" id="prof-cur-pw" placeholder="Current password" autocomplete="current-password"/>
                        </label>

                        <label class="flex flex-col gap-1">
                            <span class="text-[11px] font-semibold text-muted uppercase">New Password</span>
                            <x-password-field name="new_password" id="prof-new-pw" placeholder="Min. 6 characters" autocomplete="new-password"/>
                        </label>

                        <label class="flex flex-col gap-1">
                            <span class="text-[11px] font-semibold text-muted uppercase">Confirm New Password</span>
                            <x-password-field name="new_password_confirmation" id="prof-conf-pw" placeholder="Repeat new password" autocomplete="new-password"/>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end pt-2 border-t border-line">
                    <button type="submit" class="btn-primary">
                        <x-icon name="check" class="size-3.5"/> Save Profile
                    </button>
                </div>
            </form>
        </section>

        {{-- Assigned Pages Summary (Paginated + Searchable so it matches Left Card height!) --}}
        <section id="profile-pages-panel" class="card lg:col-span-5 overflow-hidden flex flex-col">
            <div class="card-head">
                <div>
                    <h2 class="card-title">
                        <x-icon name="layers" class="size-4 text-primary"/> My Pages Access
                        <span class="pill bg-primary-soft text-primary num">{{ $profilePages->count() }}</span>
                    </h2>
                    <p class="card-sub">
                        Role: <b>{{ ucfirst($me->role) }}</b>
                        @if ($me->isAdmin()) · Full access to all active pages @endif
                    </p>
                </div>

                @if ($profilePages->count() > 5)
                    <div class="relative">
                        <x-icon name="search" class="size-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none"/>
                        <input id="prof-pages-search" type="search" placeholder="Search page…" aria-label="Search my pages" class="search-field w-40">
                    </div>
                @endif
            </div>

            <div class="p-4 flex-1 space-y-2">
                @if ($profilePages->isEmpty())
                    <div class="p-6 text-center text-xs text-muted">No pages have been assigned to your account yet. Ask an Admin to assign your dedicated pages in <b>Accounts</b>.</div>
                @else
                    <div id="prof-pages-list" class="space-y-2">
                        @foreach ($profilePages as $p)
                            <a href="{{ route('dashboard', ['page' => $p->fb_page_id]) }}"
                               data-prof-page-row
                               data-search="{{ strtolower($p->name.' '.$p->fb_page_id.' '.($p->team ?? '').' '.($p->product ?? '')) }}"
                               class="flex items-center justify-between gap-3 p-2.5 rounded-xl bg-slate-50/80 border border-line hover:border-primary/40 hover:bg-primary-soft/25 transition-colors cursor-pointer group">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <x-page-avatar :id="$p->fb_page_id" :name="$p->name" size="size-7 !rounded-lg"/>
                                    <div class="min-w-0">
                                        <div class="text-xs font-semibold text-ink group-hover:text-primary truncate">{{ $p->name }}</div>
                                        <div class="font-mono text-[10px] text-muted">{{ $p->fb_page_id }}</div>
                                    </div>
                                </div>
                                @if (!empty($p->team))
                                    <span class="pill bg-white border border-line text-muted shrink-0">{{ $p->team }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($profilePages->count() > 6)
                <div class="px-5 py-2.5 border-t border-line bg-slate-50/50 flex items-center justify-between text-xs text-muted">
                    <span id="prof-pages-range" class="num text-[11px]">1–6 of {{ $profilePages->count() }}</span>
                    <div class="flex items-center gap-1.5">
                        <button type="button" id="prof-pages-prev" class="px-2.5 py-1 rounded-lg border border-line bg-white text-ink font-medium hover:bg-slate-50 disabled:opacity-40 disabled:pointer-events-none cursor-pointer">‹ Prev</button>
                        <span id="prof-pages-ind" class="num px-1.5 font-semibold text-ink">1 / {{ ceil($profilePages->count() / 6) }}</span>
                        <button type="button" id="prof-pages-next" class="px-2.5 py-1 rounded-lg border border-line bg-white text-ink font-medium hover:bg-slate-50 disabled:opacity-40 disabled:pointer-events-none cursor-pointer">Next ›</button>
                    </div>
                </div>
            @endif
        </section>
    </div>
@endsection
