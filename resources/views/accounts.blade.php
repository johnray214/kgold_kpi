@extends('layouts.app', ['title' => 'Accounts · Botcake KPI'])

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-bold tracking-tight text-ink">Team Accounts & Assigned Pages</h1>
            <p class="text-xs text-muted">Create logins for teammates and choose which Facebook pages each account can view</p>
        </div>
    </div>

    {{-- Create New Account Card --}}
    <section class="card overflow-hidden">
        <div class="card-head">
            <div>
                <h2 class="card-title"><x-icon name="plus" class="size-4 text-primary"/> Create New Account</h2>
                <p class="card-sub">Admins see all pages · Members only see the pages you assign to them below</p>
            </div>
        </div>

        <form method="POST" action="{{ route('accounts.store') }}" class="p-6 space-y-5">
            @csrf
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <label class="flex flex-col gap-1">
                    <span class="text-[11px] font-semibold text-muted uppercase">Full Name *</span>
                    <input type="text" name="name" value="{{ old('name') }}" required placeholder="e.g. Leanne / Ja Kgb" class="field">
                </label>

                <label class="flex flex-col gap-1">
                    <span class="text-[11px] font-semibold text-muted uppercase">Email *</span>
                    <input type="email" name="email" value="{{ old('email') }}" required placeholder="teammate@kgold.com" class="field">
                </label>

                <label class="flex flex-col gap-1">
                    <span class="text-[11px] font-semibold text-muted uppercase">Password *</span>
                    <x-password-field name="password" required placeholder="Min. 6 characters" autocomplete="new-password"/>
                </label>

                <label class="flex flex-col gap-1">
                    <span class="text-[11px] font-semibold text-muted uppercase">Role *</span>
                    <select name="role" id="create-role-select" class="field cursor-pointer">
                        <option value="member" @selected(old('role') === 'member')>Member (Assigned Pages Only)</option>
                        <option value="admin" @selected(old('role') === 'admin')>Admin (All Pages + Manage Accounts)</option>
                    </select>
                </label>
            </div>

            {{-- Visual Page Assignment Checklist with Real Facebook Avatars + Instant Search --}}
            <div id="create-pages-box" class="space-y-2.5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold text-muted uppercase tracking-wider">Assign Dedicated Pages ({{ $allPages->count() }})</span>
                    <div class="flex flex-wrap items-center gap-3 text-xs">
                        <div class="relative">
                            <x-icon name="search" class="size-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none"/>
                            <input type="search" data-table-search="create-pages-grid" placeholder="Search page or ID…" aria-label="Search pages" class="search-field w-48">
                        </div>
                        <button type="button" data-check-all="#create-pages-grid" class="text-primary hover:underline font-medium cursor-pointer">Select visible</button>
                        <button type="button" data-uncheck-all="#create-pages-grid" class="text-muted hover:text-ink cursor-pointer">Clear</button>
                    </div>
                </div>

                <div id="create-pages-grid" class="grid sm:grid-cols-2 lg:grid-cols-4 gap-2.5 max-h-64 overflow-y-auto pr-1">
                    @foreach ($allPages as $p)
                        <label data-search-item data-search="{{ strtolower($p->name.' '.$p->fb_page_id.' '.$p->team.' '.$p->product) }}"
                               class="flex items-center gap-2.5 p-2.5 rounded-xl border border-line hover:border-primary/40 hover:bg-primary-soft/25 cursor-pointer transition-colors select-none">
                            <input type="checkbox" name="page_ids[]" value="{{ $p->id }}"
                                   class="rounded border-slate-300 text-primary focus:ring-primary shrink-0">
                            <x-page-avatar :id="$p->fb_page_id" :name="$p->name" size="size-7 !rounded-lg !text-[9px]"/>
                            <span class="text-xs font-medium text-ink truncate" title="{{ $p->name }}">{{ $p->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end pt-2 border-t border-line">
                <button type="submit" class="btn-primary">
                    <x-icon name="plus" class="size-3.5"/> Create Account
                </button>
            </div>
        </form>
    </section>

    {{-- Accounts Table --}}
    <section class="card overflow-hidden">
        <div class="card-head">
            <div>
                <h2 class="card-title"><x-icon name="users" class="size-4 text-primary"/> Active Accounts ({{ $accounts->count() }})</h2>
                <p class="card-sub">Click "Assign Pages" on any member to pick their pages, or "Edit" to update name/email/password</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th class="!text-left">Email</th>
                        <th class="!text-left">Role</th>
                        <th class="!text-left">Assigned Pages</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($accounts as $acc)
                        @php
                            $initials = collect(preg_split('/\s+/', trim($acc->name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
                            $assignedIds = $acc->pages->pluck('id')->all();
                            $editPayload = [
                                'id' => $acc->id,
                                'name' => $acc->name,
                                'email' => $acc->email,
                                'role' => $acc->role,
                                'is_active' => $acc->is_active,
                                'is_self' => $acc->id === auth()->id(),
                                'page_ids' => $assignedIds,
                                'update_url' => route('accounts.update', $acc),
                                'pages_url' => route('accounts.pages', $acc),
                            ];
                        @endphp
                        <tr @class(['opacity-55' => ! $acc->is_active])>
                            <td>
                                <div class="flex items-center gap-3">
                                    <span class="size-8 rounded-xl bg-primary-soft text-primary font-bold text-xs grid place-items-center shrink-0">
                                        {{ $initials }}
                                    </span>
                                    <div>
                                        <div class="font-semibold text-ink flex items-center gap-1.5">
                                            <span>{{ $acc->name }}</span>
                                            @if ($acc->id === auth()->id())
                                                <span class="pill !px-1.5 !py-0 bg-slate-100 text-muted">You</span>
                                            @endif
                                        </div>
                                        @if (! $acc->is_active)
                                            <span class="text-[10px] text-bad font-semibold">Deactivated</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="!text-left font-mono text-xs text-muted">{{ $acc->email }}</td>
                            <td class="!text-left">
                                @if ($acc->isAdmin())
                                    <span class="pill bg-primary-soft text-primary">Admin · All Pages</span>
                                @else
                                    <span class="pill bg-slate-100 text-ink">Member</span>
                                @endif
                            </td>
                            <td class="!text-left">
                                @if ($acc->isAdmin())
                                    <span class="text-xs text-muted">Full access to all {{ $allPages->count() }} pages</span>
                                @elseif ($acc->pages->isEmpty())
                                    <span class="pill bg-amber-50 text-amber-800">No pages assigned yet</span>
                                @else
                                    <div class="flex flex-wrap items-center gap-1.5 max-w-md">
                                        @foreach ($acc->pages->take(4) as $ap)
                                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-lg bg-slate-100 text-xs font-medium text-ink">
                                                <x-page-avatar :id="$ap->fb_page_id" :name="$ap->name" size="size-4 !rounded !text-[7px]"/>
                                                <span class="max-w-28 truncate">{{ $ap->name }}</span>
                                            </span>
                                        @endforeach
                                        @if ($acc->pages->count() > 4)
                                            <span class="pill bg-primary-soft text-primary">+{{ $acc->pages->count() - 4 }} more</span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td>
                                <div class="inline-flex items-center justify-end gap-1.5">
                                    @if (! $acc->isAdmin())
                                        <button type="button" data-open-assign='@json($editPayload)' class="btn-ghost !min-h-[30px] !px-2.5 !text-xs">
                                            <x-icon name="layers" class="size-3.5 text-primary"/> Assign Pages
                                        </button>
                                    @endif
                                    <button type="button" data-open-edit='@json($editPayload)' class="btn-ghost !min-h-[30px] !px-2.5 !text-xs">
                                        <x-icon name="edit" class="size-3.5"/> Edit
                                    </button>
                                    @if ($acc->id !== auth()->id())
                                        <button type="button"
                                                data-confirm-delete
                                                data-delete-title="Delete Team Account"
                                                data-delete-name="{{ $acc->name }} ({{ $acc->email }})"
                                                data-delete-desc="This permanently removes this teammate's login and page assignments."
                                                data-delete-url="{{ route('accounts.destroy', $acc) }}"
                                                class="inline-flex items-center gap-1 rounded-xl border border-red-200 bg-red-50/70 px-2.5 min-h-[30px] text-xs font-semibold text-bad hover:bg-red-100 hover:border-red-300 transition-colors cursor-pointer">
                                            <x-icon name="trash" class="size-3.5"/> Delete
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    {{-- Modal 1: Assign Pages to Account --}}
    <div id="assign-modal" class="fixed inset-0 z-50 bg-slate-900/35 backdrop-blur-[1px] hidden place-items-center p-4">
        <div class="card w-full max-w-xl overflow-hidden shadow-2xl">
            <div class="card-head">
                <div>
                    <h2 class="card-title">Assign Pages · <span id="assign-user-name" class="text-primary"></span></h2>
                    <p class="card-sub">Check the Facebook pages this account is allowed to view</p>
                </div>
                <button type="button" data-close-modal="assign-modal" aria-label="Close" class="p-1.5 rounded-lg text-muted hover:bg-slate-100 cursor-pointer">
                    <x-icon name="x" class="size-4"/>
                </button>
            </div>
            <form id="assign-form" method="POST" action="#">
                @csrf
                <div class="px-6 pt-4 pb-2.5 border-b border-line bg-slate-50/40 flex flex-wrap items-center justify-between gap-2.5 text-xs">
                    <div class="relative flex-1 min-w-48">
                        <x-icon name="search" class="size-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none"/>
                        <input id="assign-modal-search" type="search" data-table-search="assign-pages-list" placeholder="Search {{ $allPages->count() }} pages by name, ID, or team…" aria-label="Search assignable pages" class="search-field w-full bg-white">
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        <button type="button" data-check-all="#assign-pages-list" class="text-primary hover:underline font-semibold cursor-pointer">Select visible</button>
                        <button type="button" data-uncheck-all="#assign-pages-list" class="text-muted hover:text-ink cursor-pointer">Clear</button>
                    </div>
                </div>
                <div class="p-6 max-h-[55vh] overflow-y-auto">
                    <div id="assign-pages-list" class="grid sm:grid-cols-2 gap-2.5">
                        @foreach ($allPages as $p)
                            <label data-search-item data-search="{{ strtolower($p->name.' '.$p->fb_page_id.' '.$p->team.' '.$p->product) }}"
                                   class="flex items-center gap-2.5 p-2.5 rounded-xl border border-line hover:border-primary/40 hover:bg-primary-soft/25 cursor-pointer transition-colors select-none">
                                <input type="checkbox" name="page_ids[]" value="{{ $p->id }}" data-assign-cb="{{ $p->id }}"
                                       class="rounded border-slate-300 text-primary focus:ring-primary shrink-0">
                                <x-page-avatar :id="$p->fb_page_id" :name="$p->name" size="size-7 !rounded-lg !text-[9px]"/>
                                <div class="min-w-0 flex-1">
                                    <div class="text-xs font-medium text-ink truncate" title="{{ $p->name }}">{{ $p->name }}</div>
                                    <div class="font-mono text-[10px] text-muted truncate">{{ $p->fb_page_id }}</div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="px-6 py-3.5 bg-slate-50/70 border-t border-line flex justify-end gap-2">
                    <button type="button" data-close-modal="assign-modal" class="btn-ghost">Cancel</button>
                    <button type="submit" class="btn-primary"><x-icon name="check" class="size-3.5"/> Save Assigned Pages</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal 2: Edit Account Details & Optional Password --}}
    <div id="edit-account-modal" class="fixed inset-0 z-50 bg-slate-900/35 backdrop-blur-[1px] hidden place-items-center p-4">
        <div class="card w-full max-w-md overflow-hidden shadow-2xl">
            <div class="card-head">
                <div>
                    <h2 class="card-title">Edit Account</h2>
                    <p class="card-sub">Leave New Password blank if you don't want to change it</p>
                </div>
                <button type="button" data-close-modal="edit-account-modal" aria-label="Close" class="p-1.5 rounded-lg text-muted hover:bg-slate-100 cursor-pointer">
                    <x-icon name="x" class="size-4"/>
                </button>
            </div>
            <form id="edit-account-form" method="POST" action="#" class="p-6 space-y-4">
                @csrf
                @method('PUT')
                <label class="flex flex-col gap-1">
                    <span class="text-[11px] font-semibold text-muted uppercase">Full Name *</span>
                    <input id="edit-name" type="text" name="name" required class="field">
                </label>
                <label class="flex flex-col gap-1">
                    <span class="text-[11px] font-semibold text-muted uppercase">Email *</span>
                    <input id="edit-email" type="email" name="email" required class="field">
                </label>
                <label class="flex flex-col gap-1">
                    <span class="text-[11px] font-semibold text-muted uppercase">New Password <span class="font-normal text-slate-400">(optional)</span></span>
                    <x-password-field name="password" id="edit-password" placeholder="Leave blank to keep current password" autocomplete="new-password"/>
                </label>
                <div id="edit-role-wrap" class="grid grid-cols-2 gap-3">
                    <label class="flex flex-col gap-1">
                        <span class="text-[11px] font-semibold text-muted uppercase">Role</span>
                        <select id="edit-role" name="role" class="field cursor-pointer">
                            <option value="member">Member</option>
                            <option value="admin">Admin</option>
                        </select>
                    </label>
                    <label class="flex flex-col gap-1">
                        <span class="text-[11px] font-semibold text-muted uppercase">Status</span>
                        <select id="edit-active" name="is_active" class="field cursor-pointer">
                            <option value="1">Active</option>
                            <option value="0">Deactivated</option>
                        </select>
                    </label>
                </div>
                <div class="pt-3 border-t border-line flex justify-end gap-2">
                    <button type="button" data-close-modal="edit-account-modal" class="btn-ghost">Cancel</button>
                    <button type="submit" class="btn-primary"><x-icon name="check" class="size-3.5"/> Save Account</button>
                </div>
            </form>
        </div>
    </div>
@endsection
