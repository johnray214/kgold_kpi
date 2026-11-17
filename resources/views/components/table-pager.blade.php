@props(['target', 'perPage' => 10])

<div data-pager-for="{{ $target }}" data-default-per-page="{{ $perPage }}"
     class="px-6 py-3 border-t border-line bg-slate-50/40 flex flex-wrap items-center justify-between gap-2 text-xs text-muted">
    <div class="flex items-center gap-2">
        <span>Show</span>
        <select data-pager-size aria-label="Rows per page"
                class="rounded-lg border border-line bg-white px-2 py-1 text-xs font-semibold text-ink cursor-pointer">
            @foreach (array_unique([(int) $perPage, 25, 50]) as $opt)
                <option value="{{ $opt }}" @selected($opt === (int) $perPage)>{{ $opt }}</option>
            @endforeach
            <option value="9999">All</option>
        </select>
        <span data-pager-range class="num text-[11px] text-muted"></span>
    </div>

    <div class="flex items-center gap-1.5">
        <button type="button" data-pager-prev
                class="px-2.5 py-1 rounded-lg border border-line bg-white text-ink font-medium hover:bg-slate-50 disabled:opacity-40 disabled:pointer-events-none cursor-pointer">
            ‹ Prev
        </button>
        <span data-pager-ind class="num px-2 font-semibold text-ink">1 / 1</span>
        <button type="button" data-pager-next
                class="px-2.5 py-1 rounded-lg border border-line bg-white text-ink font-medium hover:bg-slate-50 disabled:opacity-40 disabled:pointer-events-none cursor-pointer">
            Next ›
        </button>
    </div>
</div>
