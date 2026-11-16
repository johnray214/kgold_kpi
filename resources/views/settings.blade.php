@extends('layouts.app', ['title' => 'Pages & Tokens · KGOLD AUTOMATION TEAM KPI'])

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-bold tracking-tight text-ink">Pages, Team Owners & API Tokens</h1>
            <p class="text-xs text-muted">
                Assign pages to teammates/teams, or let anyone add a new page directly by pasting its page tokens—no admin account required.
            </p>
        </div>
    </div>

    {{-- Connected Pancake POS Shops (Multi-Shop Support: KGOLD MAIN + KGOLD - HERBAL + future shops) --}}
    <section class="card overflow-hidden">
        <div class="card-head">
            <div>
                <h2 class="card-title"><x-icon name="cart" class="size-4 text-primary"/> Connected Pancake POS Shops ({{ ($posShops ?? collect())->count() }})</h2>
                <p class="card-sub">Every active page is automatically matched to its POS shop · paste another POS Webhook/API Key anytime you add a new shop</p>
            </div>
        </div>
        <div class="p-6 space-y-4">
            @if (($posShops ?? collect())->isNotEmpty())
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach ($posShops as $ps)
                        <div class="flex items-center justify-between gap-3 p-3.5 rounded-xl border border-line bg-slate-50/50">
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <span class="size-2 rounded-full bg-good shrink-0"></span>
                                    <span class="font-semibold text-xs text-ink truncate">{{ $ps->name }}</span>
                                </div>
                                <div class="text-[11px] text-muted num mt-0.5">Shop ID {{ $ps->shop_id }} · {{ $ps->pages_count }} pages</div>
                            </div>
                            <button type="button"
                                    data-confirm-delete
                                    data-delete-title="Remove POS Shop"
                                    data-delete-name="{{ $ps->name }} ({{ $ps->shop_id }})"
                                    data-delete-desc="Removes this POS shop API key from future syncs."
                                    data-delete-url="{{ route('settings.pos_shops.delete', $ps) }}"
                                    class="inline-flex items-center gap-1 rounded-xl border border-red-200 bg-red-50/70 px-2.5 min-h-[28px] text-xs font-semibold text-bad hover:bg-red-100 cursor-pointer shrink-0">
                                <x-icon name="trash" class="size-3.5"/> Delete
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('settings.pos_shops.add') }}" class="flex flex-wrap sm:flex-nowrap items-end gap-3 pt-2 border-t border-line">
                @csrf
                <label class="flex flex-col gap-1 flex-1 min-w-60">
                    <span class="text-[11px] font-semibold text-muted uppercase">Add POS Shop API Key <span class="font-normal text-slate-400">(pos.pages.fm → Settings → Webhook - API → API Key)</span></span>
                    <x-password-field name="api_key" id="new-pos-api-key" required placeholder="e.g. 0dc072fe0c814d9292e448176b648754" autocomplete="off" class="font-mono !text-xs"/>
                </label>
                <button type="submit" data-loading-text="Connecting…" class="btn-primary shrink-0">
                    <x-icon name="plus" class="size-3.5"/> Connect POS Shop & Sync
                </button>
            </form>
        </div>
    </section>

    {{-- Self-serve Add New Page card (no admin token needed!) --}}
    <section class="card overflow-hidden">
        <div class="card-head">
            <div>
                <h2 class="card-title"><x-icon name="sparkles" class="size-4 text-primary"/> Add a New Page (Self-Serve · No Admin Account Needed)</h2>
                <p class="card-sub">Just paste the new page’s Botcake & Pancake tokens—we auto-detect the Page ID from the token and pull the Page Name from POS</p>
            </div>
        </div>
        <form method="POST" action="{{ route('settings.pages.add') }}" class="p-6 grid sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
            @csrf
            <label class="flex flex-col gap-1 lg:col-span-2">
                <span class="text-[11px] font-semibold text-muted uppercase">Botcake Page Token *</span>
                <input type="password" name="botcake_token" placeholder="Paste from Botcake → Settings → Public API" autocomplete="off" class="field font-mono !text-xs">
            </label>
            <label class="flex flex-col gap-1 lg:col-span-2">
                <span class="text-[11px] font-semibold text-muted uppercase">Pancake Page Token *</span>
                <input type="password" name="pancake_token" placeholder="Paste from pages.fm → Settings → Tools" autocomplete="off" class="field font-mono !text-xs">
            </label>
            <label class="flex flex-col gap-1">
                <span class="text-[11px] font-semibold text-muted uppercase">Team / Owner</span>
                <input type="text" name="team" placeholder="e.g. Team A / John" class="field !text-xs">
            </label>
            <label class="flex flex-col gap-1">
                <span class="text-[11px] font-semibold text-muted uppercase">Product</span>
                <input type="text" name="product" value="Brusko Capsule" class="field !text-xs">
            </label>
            <label class="flex flex-col gap-1 lg:col-span-2">
                <span class="text-[11px] font-semibold text-muted uppercase">Page Name <span class="font-normal text-slate-400">(optional — auto-filled from POS)</span></span>
                <input type="text" name="name" placeholder="Leave blank to auto-detect from POS" class="field !text-xs">
            </label>
            <label class="flex flex-col gap-1 lg:col-span-2">
                <span class="text-[11px] font-semibold text-muted uppercase">Facebook Page ID <span class="font-normal text-slate-400">(optional — auto-read from token)</span></span>
                <input type="text" name="fb_page_id" placeholder="Leave blank to auto-detect" class="field font-mono !text-xs">
            </label>
            <div class="lg:col-span-2 flex justify-end">
                <button type="submit" class="btn-primary w-full sm:w-auto">
                    + Add Page & Start Sync
                </button>
            </div>
        </form>
    </section>

    {{-- Tracked Pages + Team Assignment + Per-Page Token Paste --}}
    <section class="card overflow-hidden">
        <div class="card-head">
            <div>
                <h2 class="card-title"><x-icon name="layers" class="size-4 text-primary"/> Active Pages ({{ ($allPages ?? $pages)->count() }})</h2>
                <p class="card-sub">Assign a Team / Owner to filter pages per teammate in the top bar · leave token inputs blank to keep saved tokens</p>
            </div>
            <div class="relative">
                <x-icon name="search" class="size-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none"/>
                <input id="active-pages-search" type="search" data-table-search="active-pages-tbody" placeholder="Search page, ID, team, or product…" aria-label="Search active pages" class="search-field w-64">
            </div>
        </div>
        <form method="POST" action="{{ route('settings.tokens') }}">
            @csrf
            <div class="overflow-x-auto max-h-[64vh]">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Page</th>
                            <th class="!text-left">Team / Owner</th>
                            <th class="!text-left">Product</th>
                            <th class="!text-left">Botcake Token</th>
                            <th class="!text-left">Pancake Token</th>
                            <th class="w-24">Action</th>
                        </tr>
                    </thead>
                    <tbody id="active-pages-tbody">
                        @foreach (($allPages ?? $pages) as $p)
                            <tr data-search-item data-search="{{ strtolower($p->name.' '.$p->fb_page_id.' '.$p->team.' '.$p->product) }}">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <x-page-avatar :id="$p->fb_page_id" :name="$p->name" size="size-8 !rounded-lg"/>
                                        <div>
                                            <div class="font-semibold text-ink">{{ $p->name }}</div>
                                            <div class="font-mono text-[11px] text-muted">{{ $p->fb_page_id }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="!text-left min-w-36">
                                    <input type="text" name="page_team[{{ $p->fb_page_id }}]" value="{{ $p->team }}"
                                           placeholder="e.g. Team A" class="field !min-h-[32px] !text-xs w-36">
                                </td>
                                <td class="!text-left min-w-36">
                                    <input type="text" name="page_product[{{ $p->fb_page_id }}]" value="{{ $p->product }}"
                                           placeholder="Product" class="field !min-h-[32px] !text-xs w-36">
                                </td>
                                <td class="!text-left min-w-56">
                                    @php $hasBc = isset($p->has_botcake) ? (bool) $p->has_botcake : !empty($p->botcake_token); @endphp
                                    <div class="flex items-center gap-2">
                                        <span @class(['pill shrink-0', 'bg-emerald-50 text-good' => $hasBc, 'bg-amber-50 text-amber-800' => ! $hasBc])>
                                            {{ $hasBc ? 'Saved' : 'Missing' }}
                                        </span>
                                        <input type="password" name="botcake_token[{{ $p->fb_page_id }}]" autocomplete="off"
                                               placeholder="{{ $hasBc ? '•••••• (replace)' : 'Paste Botcake token…' }}"
                                               class="field !min-h-[32px] !text-xs font-mono w-full">
                                    </div>
                                </td>
                                <td class="!text-left min-w-56">
                                    @php $hasPc = isset($p->has_pancake) ? (bool) $p->has_pancake : !empty($p->pancake_token); @endphp
                                    <div class="flex items-center gap-2">
                                        <span @class(['pill shrink-0', 'bg-emerald-50 text-good' => $hasPc, 'bg-amber-50 text-amber-800' => ! $hasPc])>
                                            {{ $hasPc ? 'Saved' : 'Missing' }}
                                        </span>
                                        <input type="password" name="pancake_token[{{ $p->fb_page_id }}]" autocomplete="off"
                                               placeholder="{{ $hasPc ? '•••••• (replace)' : 'Paste Pancake token…' }}"
                                               class="field !min-h-[32px] !text-xs font-mono w-full">
                                    </div>
                                </td>
                                <td>
                                    <button type="button"
                                            data-confirm-delete
                                            data-delete-title="Remove Active Page"
                                            data-delete-name="{{ $p->name }}"
                                            data-delete-desc="This removes the page from your KPI dashboard and unassigns it from team members. You can re-import it anytime."
                                            data-delete-url="{{ route('settings.pages.delete', $p->id) }}"
                                            class="inline-flex items-center gap-1 rounded-xl border border-red-200 bg-red-50/70 px-2.5 min-h-[30px] text-xs font-semibold text-bad hover:bg-red-100 hover:border-red-300 transition-colors cursor-pointer">
                                        <x-icon name="trash" class="size-3.5"/> Delete
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-3.5 bg-slate-50/70 border-t border-line flex justify-end">
                <button type="submit" class="btn-primary">
                    <x-icon name="check" class="size-3.5"/> Save Changes & Sync
                </button>
            </div>
        </form>
    </section>

    {{-- Scan & Import Pages via Personal Account Tokens --}}
    <section id="scanned-pages" class="card overflow-hidden">
        <div class="card-head">
            <div>
                <h2 class="card-title"><x-icon name="key" class="size-4 text-primary"/> Scan & Import Pages from Personal Account Tokens</h2>
                <p class="card-sub">Paste any teammate’s Personal Botcake & Pancake tokens to see all their pages and choose which ones to add or refresh</p>
            </div>
        </div>

        <form method="POST" action="{{ route('settings.scan') }}" class="p-6 grid md:grid-cols-12 gap-4 items-end border-b border-line">
            @csrf
            <label class="flex flex-col gap-1 md:col-span-5">
                <span class="text-[11px] font-semibold text-muted uppercase">Botcake Personal Token</span>
                <x-password-field name="botcake_user_token" id="scan-bc-tok" placeholder="botcake.io → Account Information → Access Token" autocomplete="off" class="font-mono !text-xs"/>
            </label>
            <label class="flex flex-col gap-1 md:col-span-5">
                <span class="text-[11px] font-semibold text-muted uppercase">Pancake Personal Token</span>
                <x-password-field name="pancake_user_token" id="scan-pc-tok" placeholder="pages.fm → Personal Settings → API Access Token" autocomplete="off" class="font-mono !text-xs"/>
            </label>
            <div class="md:col-span-2 flex justify-end">
                <button type="submit" data-loading-text="Scanning…" class="btn-primary w-full">
                    <x-icon name="search" class="size-3.5"/> Scan Pages
                </button>
            </div>
        </form>

        @if (! empty($scanResult['pages']))
            @php
                $scannedList = $scanResult['pages'];
                $readyCount = collect($scannedList)->filter(fn ($sp) => ! $sp['already_active'] && $sp['has_botcake'] && ($sp['has_pancake_token'] || $sp['can_generate_pancake']))->count();
            @endphp
            <form method="POST" action="{{ route('settings.import_scanned') }}" class="p-6 space-y-4 bg-slate-50/50">
                @csrf
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="pill bg-primary text-white font-semibold">
                            {{ $scanResult['owner_name'] ?: 'Scanned Account' }} · {{ count($scannedList) }} pages found
                        </span>
                        <span class="pill bg-white border border-line text-muted num">Botcake: {{ $scanResult['botcake_count'] }}</span>
                        <span class="pill bg-white border border-line text-muted num">Pancake: {{ $scanResult['pancake_count'] }}</span>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 text-xs">
                        <div class="relative">
                            <x-icon name="search" class="size-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none"/>
                            <input type="search" data-table-search="scanned-pages-grid" placeholder="Filter scanned pages…" class="search-field w-48">
                        </div>
                        <button type="button" data-check-ready="#scanned-pages-grid" class="text-primary hover:underline font-semibold cursor-pointer">
                            Select Ready ({{ $readyCount }})
                        </button>
                        <button type="button" data-check-all="#scanned-pages-grid" class="text-muted hover:text-ink font-medium cursor-pointer">Select all</button>
                        <button type="button" data-uncheck-all="#scanned-pages-grid" class="text-muted hover:text-ink cursor-pointer">Clear</button>
                    </div>
                </div>

                @if (! empty($scanResult['warnings']))
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-3.5 py-2 text-xs text-amber-900 flex items-center gap-2">
                        <x-icon name="alert" class="size-4 text-amber-600 shrink-0"/>
                        <span>{{ implode(' · ', $scanResult['warnings']) }}</span>
                    </div>
                @endif

                <div id="scanned-pages-grid" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2.5 max-h-[420px] overflow-y-auto pr-1">
                    @foreach ($scannedList as $sp)
                        @php
                            $isReady = ! $sp['already_active'] && $sp['has_botcake'] && ($sp['has_pancake_token'] || $sp['can_generate_pancake']);
                        @endphp
                        <label data-search-item
                               data-search="{{ strtolower($sp['name'].' '.$sp['fb_page_id']) }}"
                               class="flex items-start gap-2.5 p-3 rounded-xl border border-line bg-white hover:border-primary/50 cursor-pointer transition-colors select-none">
                            <input type="checkbox" name="selected_pages[]" value="{{ $sp['fb_page_id'] }}"
                                   @if ($isReady) data-ready-page="1" @endif
                                   class="mt-1 rounded border-slate-300 text-primary focus:ring-primary shrink-0">
                            <img src="{{ route('page.avatar', ['pageId' => $sp['fb_page_id']]) }}"
                                 alt="" width="32" height="32" loading="lazy"
                                 class="size-8 rounded-lg object-cover ring-1 ring-line bg-primary-soft shrink-0">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-1.5">
                                    <span class="text-xs font-semibold text-ink truncate" title="{{ $sp['name'] }}">{{ $sp['name'] }}</span>
                                    @if ($sp['already_active'])
                                        <span class="pill !px-1.5 !py-0 bg-slate-100 text-muted shrink-0">Active</span>
                                    @else
                                        <span class="pill !px-1.5 !py-0 bg-primary-soft text-primary shrink-0">New</span>
                                    @endif
                                </div>
                                <div class="font-mono text-[10px] text-muted">{{ $sp['fb_page_id'] }}</div>
                                <div class="flex flex-wrap items-center gap-1 mt-1">
                                    @if ($sp['has_botcake'])
                                        <span class="text-[10px] px-1.5 py-0.2 rounded bg-emerald-50 text-good font-medium">Botcake ✓</span>
                                    @else
                                        <span class="text-[10px] px-1.5 py-0.2 rounded bg-slate-100 text-slate-400">No Botcake</span>
                                    @endif

                                    @if ($sp['has_pancake_token'])
                                        <span class="text-[10px] px-1.5 py-0.2 rounded bg-emerald-50 text-good font-medium">Pancake ✓</span>
                                    @elseif ($sp['can_generate_pancake'])
                                        <span class="text-[10px] px-1.5 py-0.2 rounded bg-sky-50 text-sky-700 font-medium">Pancake (gen)</span>
                                    @else
                                        <span class="text-[10px] px-1.5 py-0.2 rounded bg-slate-100 text-slate-400">No Pancake</span>
                                    @endif

                                    @if ($sp['in_pos'])
                                        <span class="text-[10px] px-1.5 py-0.2 rounded bg-violet-50 text-violet-700 font-medium">POS ✓</span>
                                    @endif
                                </div>
                            </div>
                        </label>
                    @endforeach
                </div>

                <div class="pt-3 border-t border-line grid sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
                    <label class="flex flex-col gap-1">
                        <span class="text-[11px] font-semibold text-muted uppercase">Team / Owner</span>
                        <input type="text" name="team" value="{{ $scanResult['owner_name'] }}" placeholder="e.g. Shaina Delacruz" class="field !text-xs bg-white">
                    </label>
                    <label class="flex flex-col gap-1">
                        <span class="text-[11px] font-semibold text-muted uppercase">Product Label <span class="font-normal text-slate-400">(optional)</span></span>
                        <input type="text" name="product" placeholder="Leave blank to use Page Name" class="field !text-xs bg-white">
                    </label>
                    <label class="flex flex-col gap-1">
                        <span class="text-[11px] font-semibold text-muted uppercase">Also Assign to Member <span class="font-normal text-slate-400">(optional)</span></span>
                        <select name="assign_user_id" class="field !text-xs bg-white cursor-pointer">
                            <option value="0">— Admin only for now —</option>
                            @foreach ($memberAccounts ?? [] as $mAcc)
                                <option value="{{ $mAcc->id }}">{{ $mAcc->name }} ({{ $mAcc->email }})</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="flex flex-col gap-2">
                        <label class="inline-flex items-center gap-2 text-xs text-muted cursor-pointer">
                            <input type="checkbox" name="generate_pancake_tokens" value="1" checked class="rounded border-slate-300 text-primary">
                            <span>Generate missing Pancake token</span>
                        </label>
                        <button type="submit" data-loading-text="Importing…" class="btn-primary w-full">
                            <x-icon name="plus" class="size-3.5"/> Import Selected & Sync
                        </button>
                    </div>
                </div>
            </form>
        @endif
    </section>
@endsection
