@extends('layouts.app', ['title' => 'My Profile · Botcake KPI'])

@section('content')
    <div>
        <h1 class="text-lg font-bold tracking-tight text-ink">My Profile</h1>
        <p class="text-xs text-muted">Update your name, email, or optionally change your password</p>
    </div>

    <div class="grid lg:grid-cols-12 gap-5">
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

        {{-- Assigned Pages Summary --}}
        <section class="card lg:col-span-5 overflow-hidden flex flex-col">
            <div class="card-head">
                <div>
                    <h2 class="card-title"><x-icon name="layers" class="size-4 text-primary"/> My Pages Access</h2>
                    <p class="card-sub">Role: <b>{{ ucfirst($me->role) }}</b></p>
                </div>
            </div>
            <div class="p-6 flex-1 space-y-2.5">
                @if ($me->isAdmin())
                    <p class="text-xs text-muted mb-3">As an <b>Admin</b>, your account automatically has access to all active pages ({{ $allPages->count() }}):</p>
                    @foreach ($allPages as $p)
                        <div class="flex items-center gap-3 p-2.5 rounded-xl bg-slate-50 border border-line">
                            <x-page-avatar :id="$p->fb_page_id" :name="$p->name" size="size-7 !rounded-lg"/>
                            <div class="min-w-0">
                                <div class="text-xs font-semibold text-ink truncate">{{ $p->name }}</div>
                                <div class="font-mono text-[10px] text-muted">{{ $p->fb_page_id }}</div>
                            </div>
                        </div>
                    @endforeach
                @elseif ($me->pages->isEmpty())
                    <div class="p-6 text-center text-xs text-muted">No pages have been assigned to your account yet. Ask an Admin to assign your dedicated pages in <b>Accounts</b>.</div>
                @else
                    @foreach ($me->pages as $p)
                        <div class="flex items-center gap-3 p-2.5 rounded-xl bg-slate-50 border border-line">
                            <x-page-avatar :id="$p->fb_page_id" :name="$p->name" size="size-7 !rounded-lg"/>
                            <div class="min-w-0">
                                <div class="text-xs font-semibold text-ink truncate">{{ $p->name }}</div>
                                <div class="font-mono text-[10px] text-muted">{{ $p->fb_page_id }}</div>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </section>
    </div>
@endsection
