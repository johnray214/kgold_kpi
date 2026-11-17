import Chart from 'chart.js/auto';

window.Chart = Chart;

document.addEventListener('DOMContentLoaded', () => {
    const SPINNER_SVG = '<svg class="size-3.5 animate-spin shrink-0" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>';

    const startTopProgress = () => {
        const bar = document.getElementById('top-nav-progress');
        if (!bar) return;
        bar.classList.remove('opacity-0');
        bar.style.width = '72%';
    };

    // 1) Mobile sidebar toggle
    const sidebar = document.getElementById('app-sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    const toggleSidebar = (open) => {
        if (!sidebar || !backdrop) return;
        sidebar.classList.toggle('-translate-x-full', !open);
        backdrop.classList.toggle('hidden', !open);
    };
    document.getElementById('open-sidebar')?.addEventListener('click', () => toggleSidebar(true));
    document.getElementById('close-sidebar')?.addEventListener('click', () => toggleSidebar(false));
    backdrop?.addEventListener('click', () => toggleSidebar(false));

    // 1b) Sidebar "All Pages" smooth CSS grid-row accordion + rotating arrow
    const sbPagesToggle = document.getElementById('sidebar-pages-toggle');
    const sbPagesWrap = document.getElementById('sidebar-pages-wrap');
    const sbPagesChevron = document.getElementById('sidebar-pages-chevron');
    sbPagesToggle?.addEventListener('click', () => {
        const opening = sbPagesWrap?.classList.contains('grid-rows-[0fr]');
        sbPagesWrap?.classList.toggle('grid-rows-[0fr]', !opening);
        sbPagesWrap?.classList.toggle('opacity-0', !opening);
        sbPagesWrap?.classList.toggle('grid-rows-[1fr]', !!opening);
        sbPagesWrap?.classList.toggle('opacity-100', !!opening);
        sbPagesToggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
        sbPagesChevron?.classList.toggle('rotate-180', !!opening);
    });

    // 1c) Custom Top-Bar Page Picker Dropdown (smooth scale/fade + rotating arrow)
    const pickerWrap = document.getElementById('top-page-picker');
    const pickerBtn = document.getElementById('top-page-btn');
    const pickerMenu = document.getElementById('top-page-menu');
    const pickerChevron = document.getElementById('top-page-chevron');
    if (pickerWrap && pickerBtn && pickerMenu) {
        const setPickerOpen = (open) => {
            pickerMenu.classList.toggle('opacity-0', !open);
            pickerMenu.classList.toggle('scale-95', !open);
            pickerMenu.classList.toggle('-translate-y-1', !open);
            pickerMenu.classList.toggle('pointer-events-none', !open);
            pickerMenu.classList.toggle('opacity-100', open);
            pickerMenu.classList.toggle('scale-100', open);
            pickerMenu.classList.toggle('translate-y-0', open);
            pickerMenu.classList.toggle('pointer-events-auto', open);
            pickerChevron?.classList.toggle('rotate-180', open);
            pickerBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        };
        pickerBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isClosed = pickerMenu.classList.contains('pointer-events-none');
            setPickerOpen(isClosed);
        });
        document.addEventListener('click', (e) => {
            if (!pickerWrap.contains(e.target)) setPickerOpen(false);
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') setPickerOpen(false);
        });
    }

    // 1f) Instant loading feedback on all internal links & sidebar items
    document.querySelectorAll('a[href]').forEach((a) => {
        a.addEventListener('click', (e) => {
            const href = a.getAttribute('href') || '';
            if (
                e.defaultPrevented ||
                e.metaKey || e.ctrlKey || e.shiftKey || e.altKey ||
                a.target === '_blank' ||
                href.startsWith('#') ||
                href.startsWith('javascript:') ||
                href.includes('/export/')
            ) {
                return;
            }
            startTopProgress();
            if (a.classList.contains('nav-item') || a.classList.contains('nav-item-active') || a.hasAttribute('data-nav-link')) {
                a.classList.add('bg-primary-soft', 'text-primary');
                if (!a.querySelector('[data-link-spin]')) {
                    const wrap = document.createElement('span');
                    wrap.dataset.linkSpin = '1';
                    wrap.innerHTML = SPINNER_SVG;
                    a.appendChild(wrap);
                }
            }
        });
    });

    // 1g) Universal button spinner on all form submissions (Login, Accounts, Profile, Filters, Sync)
    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', (e) => {
            if (e.defaultPrevented) return;
            const isDownload = form.hasAttribute('data-download-form');
            if (!isDownload) startTopProgress();
            const btn = e.submitter || form.querySelector('button[type="submit"], button:not([type="button"])');
            if (!btn || btn.dataset.spinning) return;
            const origHtml = btn.innerHTML;
            btn.dataset.spinning = '1';
            btn.classList.add('pointer-events-none', 'opacity-85');
            requestAnimationFrame(() => { btn.disabled = true; });
            if (isDownload) {
                setTimeout(() => {
                    delete btn.dataset.spinning;
                    btn.disabled = false;
                    btn.classList.remove('pointer-events-none', 'opacity-85');
                    btn.innerHTML = origHtml;
                }, 2200);
            }
            const existingRefresh = btn.querySelector('[data-sync-label]');
            if (existingRefresh) {
                btn.querySelector('svg')?.classList.add('animate-spin');
                existingRefresh.textContent = 'Syncing…';
                return;
            }
            if (btn.dataset.loadingText) {
                btn.innerHTML = `${SPINNER_SVG}<span>${btn.dataset.loadingText}</span>`;
                return;
            }
            const icon = btn.querySelector('svg');
            if (icon) {
                icon.outerHTML = SPINNER_SVG;
            } else {
                btn.insertAdjacentHTML('afterbegin', SPINNER_SVG);
            }
        });
    });

    window.addEventListener('pageshow', (e) => {
        if (!e.persisted) return;
        const bar = document.getElementById('top-nav-progress');
        if (bar) {
            bar.style.width = '0';
            bar.classList.add('opacity-0');
        }
        document.querySelectorAll('[data-link-spin]').forEach((el) => el.remove());
        document.querySelectorAll('[data-spinning]').forEach((btn) => {
            delete btn.dataset.spinning;
            btn.disabled = false;
            btn.classList.remove('pointer-events-none', 'opacity-85');
        });
    });

    // 1d) Password show/hide Eye toggle across Login, Accounts, and Profile
    document.querySelectorAll('[data-pw-toggle]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.dataset.pwToggle);
            if (!input) return;
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.querySelector('[data-eye-open]')?.classList.toggle('hidden', show);
            btn.querySelector('[data-eye-closed]')?.classList.toggle('hidden', !show);
        });
    });

    // 1e) Accounts page: Assign Pages & Edit Account modals + Select All / Clear
    const openModal = (id, open) => {
        const m = document.getElementById(id);
        if (!m) return;
        m.classList.toggle('hidden', !open);
        m.classList.toggle('grid', open);
    };
    document.querySelectorAll('[data-close-modal]').forEach((b) => {
        b.addEventListener('click', () => openModal(b.dataset.closeModal, false));
    });
    document.querySelectorAll('[data-check-all]').forEach((b) => {
        b.addEventListener('click', () => {
            document.querySelectorAll(`${b.dataset.checkAll} input[type="checkbox"]`).forEach((cb) => {
                const item = cb.closest('[data-search-item]');
                if (!item || !item.classList.contains('hidden')) {
                    cb.checked = true;
                }
            });
        });
    });
    document.querySelectorAll('[data-uncheck-all]').forEach((b) => {
        b.addEventListener('click', () => {
            document.querySelectorAll(`${b.dataset.uncheckAll} input[type="checkbox"]`).forEach((cb) => (cb.checked = false));
        });
    });
    document.querySelectorAll('[data-check-ready]').forEach((b) => {
        b.addEventListener('click', () => {
            document.querySelectorAll(`${b.dataset.checkReady} input[type="checkbox"]`).forEach((cb) => {
                cb.checked = cb.hasAttribute('data-ready-page');
            });
        });
    });
    const createRoleSel = document.getElementById('create-role-select');
    const createPagesBox = document.getElementById('create-pages-box');
    createRoleSel?.addEventListener('change', () => {
        createPagesBox?.classList.toggle('hidden', createRoleSel.value === 'admin');
    });

    document.querySelectorAll('[data-open-assign]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const d = JSON.parse(btn.dataset.openAssign);
            const nameEl = document.getElementById('assign-user-name');
            const form = document.getElementById('assign-form');
            const searchIn = document.getElementById('assign-modal-search');
            if (nameEl) nameEl.textContent = d.name;
            if (form) form.action = d.pages_url;
            if (searchIn) {
                searchIn.value = '';
                document.querySelectorAll('#assign-pages-list [data-search-item]').forEach((el) => el.classList.remove('hidden'));
            }
            const set = new Set((d.page_ids || []).map(Number));
            document.querySelectorAll('[data-assign-cb]').forEach((cb) => {
                cb.checked = set.has(Number(cb.dataset.assignCb));
            });
            openModal('assign-modal', true);
            searchIn?.focus();
        });
    });

    document.querySelectorAll('[data-open-edit]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const d = JSON.parse(btn.dataset.openEdit);
            const form = document.getElementById('edit-account-form');
            if (form) form.action = d.update_url;
            const n = document.getElementById('edit-name');
            const e = document.getElementById('edit-email');
            const r = document.getElementById('edit-role');
            const a = document.getElementById('edit-active');
            const pw = document.getElementById('edit-password');
            const roleWrap = document.getElementById('edit-role-wrap');
            if (n) n.value = d.name;
            if (e) e.value = d.email;
            if (r) r.value = d.role;
            if (a) a.value = d.is_active ? '1' : '0';
            if (pw) pw.value = '';
            roleWrap?.classList.toggle('hidden', !!d.is_self);
            openModal('edit-account-modal', true);
        });
    });

    // 2) Auto-dismiss status toast after 6s
    const toast = document.getElementById('status-toast');
    if (toast) {
        setTimeout(() => {
            toast.classList.add('opacity-0', 'translate-y-1');
            setTimeout(() => toast.remove(), 250);
        }, 6000);
    }

    // 3) Sync button loading lock
    document.querySelectorAll('form[data-sync-form]').forEach((form) => {
        form.addEventListener('submit', () => {
            const btn = form.querySelector('button[type="submit"]');
            if (!btn) return;
            btn.disabled = true;
            btn.querySelector('svg')?.classList.add('animate-spin');
            const label = btn.querySelector('[data-sync-label]');
            if (label) label.textContent = 'Syncing…';
        });
    });

    // 4) Sypher-style Revenue & Attribution Chart (supports Line vs Stacked Bar toggle + interactive legend pills)
    const el = document.getElementById('trend-chart');
    if (el) {
        const data = JSON.parse(el.dataset.trend);
        const defaultMode = el.dataset.chartMode || 'line';
        const colors = {
            botcake_ai: '#4B55F5',
            broadcast: '#0EA5E9',
            sequence: '#8B5CF6',
            agent: '#F59E0B',
            organic: '#94A3B8',
            pending: '#CBD5E1',
        };
        const labels = {
            botcake_ai: 'Botcake AI',
            broadcast: 'Broadcast',
            sequence: 'Sequence',
            agent: 'Agent / Telesales',
            organic: 'Organic',
            pending: 'POS Only',
        };
        const font = { family: "'Plus Jakarta Sans', 'Inter', sans-serif", size: 11 };

        const buildDatasets = (mode) => {
            const list = [];
            // Primary Emerald Revenue curve (like Sypher's green Revenue curve)
            list.push({
                type: 'line',
                key: 'revenue',
                label: 'POS Revenue (₱)',
                data: data.sales,
                borderColor: '#10B981',
                backgroundColor: 'rgba(16, 185, 129, 0.10)',
                fill: mode === 'line',
                borderWidth: 2.2,
                pointRadius: 2.5,
                pointHoverRadius: 5,
                pointBackgroundColor: '#FFFFFF',
                pointBorderColor: '#10B981',
                pointBorderWidth: 2,
                tension: 0.35,
                yAxisID: 'ySales',
                order: 1,
            });

            Object.keys(data.series).forEach((k) => {
                if (mode === 'line') {
                    list.push({
                        type: 'line',
                        key: k,
                        label: labels[k] || k,
                        data: data.series[k],
                        borderColor: colors[k] || '#94A3B8',
                        backgroundColor: 'transparent',
                        borderWidth: 1.9,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        tension: 0.35,
                        yAxisID: 'y',
                        order: 2,
                    });
                } else {
                    list.push({
                        type: 'bar',
                        key: k,
                        label: labels[k] || k,
                        data: data.series[k],
                        backgroundColor: colors[k] || '#94A3B8',
                        borderRadius: 3,
                        maxBarThickness: 22,
                        stack: 'orders',
                        yAxisID: 'y',
                        order: 2,
                    });
                }
            });
            return list;
        };

        let currentMode = defaultMode;
        const chart = new Chart(el, {
            data: { labels: data.labels, datasets: buildDatasets(currentMode) },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 240 },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#FFFFFF',
                        titleColor: '#64748B',
                        bodyColor: '#111827',
                        borderColor: '#EAECF4',
                        borderWidth: 1,
                        padding: 12,
                        cornerRadius: 12,
                        boxPadding: 5,
                        usePointStyle: true,
                        titleFont: { ...font, size: 10, weight: '600' },
                        bodyFont: { ...font, weight: '600' },
                        callbacks: {
                            label: (ctx) => {
                                if (ctx.dataset.yAxisID === 'ySales') {
                                    return ` ${ctx.dataset.label}: ₱${Math.round(ctx.parsed.y).toLocaleString()}`;
                                }
                                return ctx.parsed.y > 0 ? ` ${ctx.dataset.label}: ${ctx.parsed.y} orders` : null;
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        stacked: currentMode === 'bar',
                        grid: { color: '#F6F7FB' },
                        border: { display: false },
                        ticks: { font, color: '#64748B', maxRotation: 0, autoSkipPadding: 12 },
                    },
                    y: {
                        stacked: currentMode === 'bar',
                        beginAtZero: true,
                        position: 'right',
                        border: { display: false },
                        ticks: { precision: 0, font, color: '#94A3B8' },
                        grid: { drawOnChartArea: false },
                    },
                    ySales: {
                        beginAtZero: true,
                        position: 'left',
                        border: { display: false },
                        grid: { color: '#F1F3F9' },
                        ticks: {
                            font,
                            color: '#64748B',
                            callback: (v) => (v >= 1000 ? '₱' + (v / 1000).toFixed(v % 1000 === 0 ? 0 : 1) + 'K' : '₱' + v),
                        },
                    },
                },
            },
        });

        const hiddenKeys = new Set();

        // Mode toggle buttons (Line vs Bar, like Sypher top-right chart icons)
        document.querySelectorAll('[data-chart-toggle]').forEach((btn) => {
            btn.addEventListener('click', () => {
                currentMode = btn.dataset.chartToggle;
                const nextDatasets = buildDatasets(currentMode);
                nextDatasets.forEach((ds) => {
                    if (hiddenKeys.has(ds.key)) ds.hidden = true;
                });
                chart.data.datasets = nextDatasets;
                chart.options.scales.x.stacked = currentMode === 'bar';
                chart.options.scales.y.stacked = currentMode === 'bar';
                chart.update();
                document.querySelectorAll('[data-chart-toggle]').forEach((b) => {
                    const on = b === btn;
                    b.classList.toggle('bg-primary-soft', on);
                    b.classList.toggle('text-primary', on);
                    b.classList.toggle('text-muted', !on);
                });
            });
        });

        // Interactive Sypher-style legend checkboxes (calls chart.update() + updates checkbox visual state)
        document.querySelectorAll('[data-series-toggle]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const key = btn.dataset.seriesToggle;
                const dsIndex = chart.data.datasets.findIndex((d) => d.key === key);
                if (dsIndex === -1) return;

                const currentlyVisible = chart.isDatasetVisible(dsIndex);
                const nextVisible = !currentlyVisible;
                if (nextVisible) {
                    hiddenKeys.delete(key);
                } else {
                    hiddenKeys.add(key);
                }

                chart.setDatasetVisibility(dsIndex, nextVisible);
                // Hide right-hand ySales axis ticks when POS Revenue is unchecked
                if (key === 'revenue' && chart.options.scales.ySales) {
                    chart.options.scales.ySales.display = nextVisible;
                }
                chart.update();

                btn.setAttribute('aria-pressed', nextVisible ? 'true' : 'false');
                btn.classList.toggle('opacity-45', !nextVisible);
                btn.classList.toggle('line-through', !nextVisible);
                const box = btn.querySelector('[data-check-box]');
                const checkIcon = btn.querySelector('svg');
                if (checkIcon) checkIcon.classList.toggle('opacity-0', !nextVisible);
                if (box) box.classList.toggle('!bg-slate-200', !nextVisible);
            });
        });
    }

    // 5) Sypher right-pane tabs + search + pagination (Pages / All Orders / AI / Broadcast)
    const rightPanel = document.getElementById('sypher-right-panel');
    if (rightPanel) {
        const searchInput = document.getElementById('right-panel-search');
        const perPageSel = document.getElementById('rp-per-page');
        const prevBtn = document.getElementById('rp-prev');
        const nextBtn = document.getElementById('rp-next');
        const pageInd = document.getElementById('rp-page-indicator');
        const rangeLbl = document.getElementById('rp-range-label');
        let activeTab = 'pages';
        let curPage = 1;

        const applyRightFilter = (resetPage = false) => {
            if (resetPage) curPage = 1;
            const term = (searchInput?.value || '').trim().toLowerCase();
            const perPage = Number(perPageSel?.value || 8);

            rightPanel.querySelectorAll('[data-rp-pane]').forEach((pane) => {
                const isTab = pane.dataset.rpPane === activeTab;
                pane.classList.toggle('hidden', !isTab);
                if (!isTab) return;

                const matched = [];
                pane.querySelectorAll('[data-rp-row]').forEach((row) => {
                    const matchSrc = !pane.dataset.srcFilter || row.dataset.src === pane.dataset.srcFilter;
                    const matchTerm = !term || (row.dataset.search || '').includes(term);
                    if (matchSrc && matchTerm) {
                        matched.push(row);
                    } else {
                        row.classList.add('hidden');
                    }
                });

                const total = matched.length;
                const totalPages = Math.max(1, Math.ceil(total / perPage));
                if (curPage > totalPages) curPage = totalPages;
                const startIdx = (curPage - 1) * perPage;
                const endIdx = startIdx + perPage;

                matched.forEach((row, idx) => {
                    row.classList.toggle('hidden', idx < startIdx || idx >= endIdx);
                });

                if (pageInd) pageInd.textContent = `${curPage} / ${totalPages}`;
                if (prevBtn) prevBtn.disabled = curPage <= 1;
                if (nextBtn) nextBtn.disabled = curPage >= totalPages;
                if (rangeLbl) {
                    rangeLbl.textContent = total === 0
                        ? '0 results'
                        : `· ${startIdx + 1}–${Math.min(endIdx, total)} of ${total}`;
                }
            });
        };

        rightPanel.querySelectorAll('[data-rp-tab]').forEach((tab) => {
            tab.addEventListener('click', () => {
                const target = tab.dataset.rpTab;
                const src = tab.dataset.rpSrc || '';
                activeTab = target;
                const ordersPane = rightPanel.querySelector('[data-rp-pane="orders"]');
                if (ordersPane) ordersPane.dataset.srcFilter = src;

                rightPanel.querySelectorAll('[data-rp-tab]').forEach((t) => {
                    const on = t === tab;
                    t.classList.toggle('bg-slate-100', on);
                    t.classList.toggle('text-ink', on);
                    t.classList.toggle('font-semibold', on);
                    t.classList.toggle('text-muted', !on);
                });
                applyRightFilter(true);
            });
        });

        searchInput?.addEventListener('input', () => applyRightFilter(true));
        perPageSel?.addEventListener('change', () => applyRightFilter(true));
        prevBtn?.addEventListener('click', () => {
            if (curPage > 1) {
                curPage--;
                applyRightFilter(false);
            }
        });
        nextBtn?.addEventListener('click', () => {
            curPage++;
            applyRightFilter(false);
        });
        applyRightFilter(true);
    }

    // 5b) Custom Delete Confirmation Modal (shared by Accounts & Active Pages)
    const delModal = document.getElementById('confirm-delete-modal');
    document.querySelectorAll('[data-confirm-delete]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const form = document.getElementById('confirm-delete-form');
            const titleEl = document.getElementById('cdm-title');
            const nameEl = document.getElementById('cdm-name');
            const descEl = document.getElementById('cdm-desc');
            if (form) form.action = btn.dataset.deleteUrl || '#';
            if (titleEl) titleEl.textContent = btn.dataset.deleteTitle || 'Confirm Delete';
            if (nameEl) nameEl.textContent = btn.dataset.deleteName || '';
            if (descEl) descEl.textContent = btn.dataset.deleteDesc || 'Are you sure you want to delete this item?';
            openModal('confirm-delete-modal', true);
        });
    });
    delModal?.addEventListener('click', (e) => {
        if (e.target === delModal) openModal('confirm-delete-modal', false);
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && delModal && !delModal.classList.contains('hidden')) {
            openModal('confirm-delete-modal', false);
        }
    });

    // Universal Table Paginator registry (used by Broadcasts, Sequences, Botcake AI, and POS Orders)
    const tablePagers = new Map();
    const refreshTablePager = (targetId, resetPage = false) => {
        const fn = tablePagers.get(targetId);
        if (fn) fn(resetPage);
    };

    // 6) Broadcasts table search + segment filter pills
    const filterBroadcasts = () => {
        const searchEl = document.getElementById('bc-search');
        const table = document.getElementById('bc-table');
        if (!table) return;
        const term = (searchEl?.value || '').trim().toLowerCase();
        const activeSeg = table.dataset.activeSegment || 'all';
        let shown = 0;
        table.querySelectorAll('tbody tr[data-bc-row]').forEach((tr) => {
            const matchSeg = activeSeg === 'all' || tr.dataset.segment === activeSeg;
            const matchText = !term || (tr.dataset.search || '').includes(term);
            const visible = matchSeg && matchText;
            tr.dataset.filteredOut = visible ? '0' : '1';
            tr.classList.toggle('hidden', !visible);
            if (visible) shown++;
        });
        document.getElementById('bc-empty-filter')?.classList.toggle('hidden', shown > 0);
        refreshTablePager('bc-table', true);
    };

    document.getElementById('bc-search')?.addEventListener('input', filterBroadcasts);
    document.querySelectorAll('[data-seg-btn]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const table = document.getElementById('bc-table');
            if (!table) return;
            table.dataset.activeSegment = btn.dataset.segBtn;
            document.querySelectorAll('[data-seg-btn]').forEach((b) => {
                const active = b === btn;
                b.classList.toggle('bg-primary', active);
                b.classList.toggle('text-white', active);
                b.classList.toggle('border-primary', active);
                b.classList.toggle('bg-white', !active);
                b.classList.toggle('text-muted', !active);
            });
            filterBroadcasts();
        });
    });

    // 7) Generic instant search for Sequences, Orders, Active Pages, Assign Pages, and Page Picker
    document.querySelectorAll('[data-table-search]').forEach((input) => {
        const targetId = input.dataset.tableSearch;
        const target = document.getElementById(targetId);
        if (!target) return;
        input.addEventListener('input', () => {
            const term = input.value.trim().toLowerCase();
            target.querySelectorAll('[data-search-item]').forEach((item) => {
                if (!term) {
                    item.dataset.filteredOut = '0';
                    item.classList.remove('hidden');
                    return;
                }
                let hay = item.dataset.search || '';
                item.querySelectorAll('input[type="text"]').forEach((ti) => {
                    if (ti.value) hay += ' ' + ti.value.toLowerCase();
                });
                const ok = hay.includes(term);
                item.dataset.filteredOut = ok ? '0' : '1';
                item.classList.toggle('hidden', !ok);
            });
            refreshTablePager(targetId, true);
        });
    });

    // 8) Botcake-Style Broadcast Detail Slide-Over Drawer
    const bcDrawer = document.getElementById('bc-drawer');
    const bcBackdrop = document.getElementById('bc-drawer-backdrop');
    const setBcDrawer = (open) => {
        if (!bcDrawer || !bcBackdrop) return;
        bcDrawer.classList.toggle('translate-x-full', !open);
        bcBackdrop.classList.toggle('hidden', !open);
    };
    document.getElementById('bc-drawer-close')?.addEventListener('click', () => setBcDrawer(false));
    bcBackdrop?.addEventListener('click', () => setBcDrawer(false));

    const fmtNum = (n) => Number(n || 0).toLocaleString();
    document.querySelectorAll('tr[data-bc-detail]').forEach((tr) => {
        tr.addEventListener('click', () => {
            const d = JSON.parse(tr.dataset.bcDetail);
            const setTxt = (id, v) => {
                const node = document.getElementById(id);
                if (node) node.textContent = v;
            };
            const av = document.getElementById('bcd-avatar');
            if (av) av.src = d.avatar || '';
            setTxt('bcd-name', d.name);
            setTxt('bcd-meta', `${d.page_name} · ${d.sent_at} · ${d.method}`);
            setTxt('bcd-targeted', fmtNum(d.targeted));
            setTxt('bcd-delivered', fmtNum(d.delivered));
            setTxt('bcd-rate', d.success_pct);
            setTxt('bcd-seen', fmtNum(d.seen));
            setTxt('bcd-clicked', fmtNum(d.clicked));
            setTxt('bcd-phone', fmtNum(d.phone));
            setTxt('bcd-leadrate', `${d.lead_rate} lead rate`);
            setTxt('bcd-orders', fmtNum(d.total_orders ?? d.orders));
            setTxt('bcd-cvr', `${fmtNum(d.orders)} direct · ${fmtNum(d.assisted)} AI-closed`);
            setTxt('bcd-sales', d.sales_fmt);
            setTxt('bcd-assisted', d.assisted > 0 ? `${d.assisted_sales_fmt} closed by AI` : `${d.cvr} CVR`);
            setTxt('bcd-failed-badge', `${fmtNum(d.failed)} not delivered`);

            const ordLink = document.getElementById('bcd-orders-link');
            const salesLink = document.getElementById('bcd-sales-link');
            const targetOrdersUrl = d.orders > 0 ? d.orders_url : (d.assisted > 0 ? d.assisted_url : d.orders_url);
            if (ordLink) ordLink.href = targetOrdersUrl;
            if (salesLink) salesLink.href = targetOrdersUrl;

            const list = document.getElementById('bcd-errors-list');
            if (list) {
                list.innerHTML = '';
                if (!d.failed) {
                    list.innerHTML = '<div class="p-4 text-good font-medium bg-emerald-50/40">0 failed — 100% of scheduled sends went through.</div>';
                } else if (Array.isArray(d.errors) && d.errors.length > 0) {
                    const isUtilityRetry = (e) =>
                        Number(e.code) === 2022 && (d.raw_method === 'utility_message' || Number(e.count || 0) > Number(d.failed || 0));
                    const realErrors = d.errors.filter((e) => !isUtilityRetry(e));
                    const windowRetries = d.errors.filter(isUtilityRetry);
                    const displayErrors = realErrors.length > 0 ? realErrors : d.errors;

                    const maxErr = Math.max(1, ...displayErrors.map((e) => Number(e.count || 0)));
                    displayErrors.forEach((e) => {
                        const row = document.createElement('div');
                        row.className = 'p-3.5 space-y-1.5';
                        const code = Number(e.code);
                        const isBlocked = code === 551;
                        const label = code === 551
                            ? 'Blocked / Person unavailable (#551)'
                            : code === 100
                                ? 'Deactivated / No matching user (#100)'
                                : code === 613
                                    ? 'Meta API rate limit (#613)'
                                    : String(e.description || `Error #${e.code}`);
                        const pct = Math.min(100, Math.round((Number(e.count || 0) / maxErr) * 100));
                        row.innerHTML = `
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-medium text-ink">${label.replace(/</g, '&lt;')}</span>
                                <span class="num font-bold ${isBlocked ? 'text-amber-700' : 'text-bad'}">${fmtNum(e.count)}</span>
                            </div>
                            <div class="h-1.5 rounded-full bg-slate-100 overflow-hidden">
                                <div class="h-full rounded-full ${isBlocked ? 'bg-amber-400' : 'bg-bad'}" style="width:${pct}%"></div>
                            </div>
                        `;
                        list.appendChild(row);
                    });

                    if (realErrors.length > 0 && windowRetries.length > 0) {
                        const retryTotal = windowRetries.reduce((sum, r) => sum + Number(r.count || 0), 0);
                        const info = document.createElement('div');
                        info.className = 'p-3.5 bg-primary-soft/55 text-[11px] text-ink space-y-1';
                        info.innerHTML = `
                            <div class="flex items-center justify-between font-semibold text-primary">
                                <span>Outside 24h Window (#2022) — Auto-Retried via Utility</span>
                                <span class="num">${fmtNum(retryTotal)}</span>
                            </div>
                            <p class="text-muted leading-relaxed">Meta rejected free standard messaging outside the 24h window, so Botcake automatically retried and delivered these via Utility Template (already counted in Delivered above).</p>
                        `;
                        list.appendChild(info);
                    }
                } else {
                    list.innerHTML = `<div class="p-4 flex items-center justify-between"><span class="text-muted">${d.failed_reason}</span><span class="num font-bold text-bad">${fmtNum(d.failed)}</span></div>`;
                }
            }
            setBcDrawer(true);
        });
    });

    // 9) Client-side sortable columns
    document.querySelectorAll('table[data-sortable] thead th[data-sort]').forEach((th) => {
        th.addEventListener('click', () => {
            const table = th.closest('table');
            const tbody = table.querySelector('tbody');
            if (!tbody) return;
            const idx = Array.from(th.parentNode.children).indexOf(th);
            const type = th.dataset.sort;
            const dir = th.dataset.dir === 'desc' ? 'asc' : 'desc';
            table.querySelectorAll('thead th[data-sort]').forEach((h) => delete h.dataset.dir);
            th.dataset.dir = dir;

            const rows = Array.from(tbody.querySelectorAll('tr[data-sort-row]'));
            rows.sort((a, b) => {
                const av = a.children[idx]?.dataset.val ?? a.children[idx]?.textContent.trim() ?? '';
                const bv = b.children[idx]?.dataset.val ?? b.children[idx]?.textContent.trim() ?? '';
                if (type === 'num') {
                    const na = parseFloat(String(av).replace(/[^0-9.-]/g, '')) || 0;
                    const nb = parseFloat(String(bv).replace(/[^0-9.-]/g, '')) || 0;
                    return dir === 'asc' ? na - nb : nb - na;
                }
                return dir === 'asc' ? String(av).localeCompare(String(bv)) : String(bv).localeCompare(String(av));
            });
            rows.forEach((r) => tbody.appendChild(r));
            if (table.id) refreshTablePager(table.id, true);
        });
    });

    // 9b) Initialize Universal Table Pagers ([data-pager-for])
    document.querySelectorAll('[data-pager-for]').forEach((pagerEl) => {
        const targetId = pagerEl.dataset.pagerFor;
        const table = document.getElementById(targetId);
        if (!table) return;

        const sizeSel = pagerEl.querySelector('[data-pager-size]');
        const prevBtn = pagerEl.querySelector('[data-pager-prev]');
        const nextBtn = pagerEl.querySelector('[data-pager-next]');
        const indEl = pagerEl.querySelector('[data-pager-ind]');
        const rangeEl = pagerEl.querySelector('[data-pager-range]');
        let curPage = 1;

        const getRows = () => {
            const bcRows = Array.from(table.querySelectorAll('tbody tr[data-bc-row]'));
            if (bcRows.length > 0) return bcRows;
            return Array.from(table.querySelectorAll('[data-search-item]'));
        };

        const render = (resetPage = false) => {
            if (resetPage) curPage = 1;
            const allRows = getRows();
            if (allRows.length === 0) {
                pagerEl.classList.add('hidden');
                return;
            }
            pagerEl.classList.remove('hidden');

            const perPage = Number(sizeSel?.value || pagerEl.dataset.defaultPerPage || 10);
            const matched = [];
            allRows.forEach((r) => {
                if (r.dataset.filteredOut === '1') {
                    r.classList.add('hidden');
                } else {
                    matched.push(r);
                }
            });

            const total = matched.length;
            const totalPages = Math.max(1, Math.ceil(total / perPage));
            if (curPage > totalPages) curPage = totalPages;
            const start = (curPage - 1) * perPage;
            const end = start + perPage;

            matched.forEach((r, idx) => {
                r.classList.toggle('hidden', idx < start || idx >= end);
            });

            if (indEl) indEl.textContent = `${curPage} / ${totalPages}`;
            if (prevBtn) prevBtn.disabled = curPage <= 1;
            if (nextBtn) nextBtn.disabled = curPage >= totalPages;
            if (rangeEl) {
                rangeEl.textContent = total === 0
                    ? '· 0 matching rows'
                    : `· ${start + 1}–${Math.min(end, total)} of ${total}`;
            }
        };

        tablePagers.set(targetId, render);
        sizeSel?.addEventListener('change', () => render(true));
        prevBtn?.addEventListener('click', () => {
            if (curPage > 1) {
                curPage--;
                render(false);
            }
        });
        nextBtn?.addEventListener('click', () => {
            curPage++;
            render(false);
        });
        render(true);
    });

    // 10) Member EOD Generator: Optional fields + Custom Macro/Broadcast Rows + Draft Persistence + Direct PDF Download
    const eodPaper = document.getElementById('eod-paper');
    if (eodPaper) {
        const memDraftKey = `kpi_mem_eod_v2_${eodPaper.dataset.reportDate || 'today'}_${eodPaper.dataset.memberId || '0'}`;
        let restoringMemDraft = false;

        const val = (id) => (document.getElementById(id)?.value ?? '').trim();
        const setTxt = (id, t) => {
            const el = document.getElementById(id);
            if (el) el.textContent = t;
        };
        const setVal = (id, v) => {
            const el = document.getElementById(id);
            if (el) el.value = v;
        };

        const rowsList = document.getElementById('custom-eod-rows-list');
        const rowsEmpty = document.getElementById('custom-eod-rows-empty');
        const rowTpl = document.getElementById('custom-eod-row-tpl');
        const customWrap = document.getElementById('out-custom-sales-wrap');
        const customTbody = document.getElementById('out-custom-sales-tbody');

        const memFieldIds = [
            'in-prep-by', 'in-role-title', 'in-coverage', 'in-bc-target',
            'in-macro-today', 'in-macro-target', 'in-inq-today', 'in-inq-yest',
            'in-inq-target', 'in-pending', 'in-support', 'in-ceo',
            'in-prio-1', 'in-prio-2', 'in-prio-3',
        ];

        const saveMemDraft = () => {
            if (restoringMemDraft) return;
            try {
                const fields = {};
                memFieldIds.forEach((id) => {
                    const el = document.getElementById(id);
                    if (el) fields[id] = el.value;
                });
                const rawRows = [];
                rowsList?.querySelectorAll('[data-custom-row]').forEach((node) => {
                    rawRows.push({
                        page: node.querySelector('[data-cr-page]')?.value || '',
                        type: node.querySelector('[data-cr-type]')?.value || 'Macro Sale',
                        orders: node.querySelector('[data-cr-orders]')?.value || '',
                        sales: node.querySelector('[data-cr-sales]')?.value || '',
                        note: node.querySelector('[data-cr-note]')?.value || '',
                    });
                });
                localStorage.setItem(memDraftKey, JSON.stringify({
                    fields,
                    incPages: document.getElementById('in-show-pages-tbl')?.checked ?? true,
                    rows: rawRows,
                }));
            } catch {}
        };

        const syncEodPreview = () => {
            const prepBy = val('in-prep-by');
            const roleTitle = val('in-role-title');
            const coverage = val('in-coverage');
            const bcTarget = val('in-bc-target');
            const macroToday = val('in-macro-today');
            const macroTarget = val('in-macro-target');
            const inqToday = val('in-inq-today');
            const inqYest = val('in-inq-yest');
            const inqTarget = val('in-inq-target');
            const pending = val('in-pending');
            const support = val('in-support');
            const ceo = val('in-ceo') || 'Wala.';
            const p1 = val('in-prio-1');
            const p2 = val('in-prio-2');
            const p3 = val('in-prio-3');
            const incPages = document.getElementById('in-show-pages-tbl')?.checked ?? true;

            setTxt('out-prep-by', prepBy);
            setTxt('out-sig-name', prepBy);
            setTxt('out-sig-role', roleTitle);
            setTxt('out-coverage', coverage);
            setTxt('out-bc-target-1', bcTarget !== '' ? bcTarget : '—');
            setTxt('out-bc-target-2', bcTarget !== '' ? bcTarget : '—');

            // Optional Macro row & bullet
            document.getElementById('row-macro')?.classList.toggle('hidden', macroToday === '');
            setTxt('out-macro-today', macroToday || '0');
            setTxt('out-macro-target', macroTarget !== '' ? macroTarget : '—');
            const liMacro = document.getElementById('li-macro-bullet');
            if (liMacro) {
                liMacro.classList.toggle('hidden', macroToday === '');
                liMacro.textContent = macroToday !== '' ? `Corrected ${macroToday} identified bot/macro response issue(s).` : '';
            }

            // Optional Unanswered Inquiries row & bullets
            document.getElementById('row-inq')?.classList.toggle('hidden', inqToday === '');
            setTxt('out-inq-today', inqToday || '0');
            setTxt('out-inq-yest', inqYest !== '' ? inqYest : '—');
            setTxt('out-inq-target', inqTarget !== '' ? inqTarget : '—');
            document.getElementById('li-inq-impact')?.classList.toggle('hidden', inqToday === '');
            setTxt('out-inq-impact', inqToday);

            // Optional Pending Customer Concerns
            document.getElementById('li-pending-impact')?.classList.toggle('hidden', pending === '');
            setTxt('out-pending-impact', pending);

            const liInqBullet = document.getElementById('li-inq-bullet');
            if (liInqBullet) {
                const parts = [];
                if (inqToday !== '') parts.push(`Assisted ${inqToday} customer inquiries.`);
                if (pending !== '') parts.push(`Identified ${pending} pending customer concern(s) for follow-up.`);
                liInqBullet.classList.toggle('hidden', parts.length === 0);
                liInqBullet.textContent = parts.join(' ');
            }

            setTxt('out-support', support);
            document.getElementById('out-support')?.classList.toggle('hidden', support === '');
            setTxt('out-ceo', ceo);
            setTxt('out-prio-1', p1);
            setTxt('out-prio-2', p2);
            setTxt('out-prio-3', p3);
            document.getElementById('out-pages-table-wrap')?.classList.toggle('hidden', !incPages);

            // Sync hidden PDF form inputs
            setVal('pdf-prep-by', prepBy);
            setVal('pdf-role-title', roleTitle);
            setVal('pdf-coverage', coverage);
            setVal('pdf-bc-target', bcTarget);
            setVal('pdf-macro-today', macroToday);
            setVal('pdf-macro-target', macroTarget);
            setVal('pdf-inq-today', inqToday);
            setVal('pdf-inq-yest', inqYest);
            setVal('pdf-inq-target', inqTarget);
            setVal('pdf-pending', pending);
            setVal('pdf-support', support);
            setVal('pdf-ceo', ceo);
            setVal('pdf-prio-1', p1);
            setVal('pdf-prio-2', p2);
            setVal('pdf-prio-3', p3);
            setVal('pdf-include-pages', incPages ? '1' : '0');
            saveMemDraft();
        };

        const renderCustomRows = () => {
            const items = [];
            let domRowCount = 0;
            rowsList?.querySelectorAll('[data-custom-row]').forEach((node) => {
                domRowCount++;
                const page = (node.querySelector('[data-cr-page]')?.value || '').trim();
                const type = (node.querySelector('[data-cr-type]')?.value || 'Macro Sale').trim();
                const orders = Number(node.querySelector('[data-cr-orders]')?.value || 0);
                const sales = Number(node.querySelector('[data-cr-sales]')?.value || 0);
                const note = (node.querySelector('[data-cr-note]')?.value || '').trim();
                if (page !== '' || orders > 0 || sales > 0 || note !== '') {
                    items.push({ page: page || 'Assigned Page', type, orders, sales, note });
                }
            });

            rowsEmpty?.classList.toggle('hidden', domRowCount > 0);
            customWrap?.classList.toggle('hidden', items.length === 0);
            setVal('pdf-custom-rows', JSON.stringify(items));

            if (customTbody) {
                customTbody.innerHTML = '';
                let sumOrd = 0;
                let sumSales = 0;
                items.forEach((it) => {
                    sumOrd += it.orders;
                    sumSales += it.sales;
                    const tr = document.createElement('tr');
                    const esc = (s) => String(s).replace(/</g, '&lt;');
                    tr.innerHTML = `
                        <td class="py-1.5 px-3 font-medium text-slate-800">${esc(it.page)}</td>
                        <td class="py-1.5 px-3 text-slate-600">${esc(it.type)}</td>
                        <td class="py-1.5 px-3 text-right font-semibold text-slate-900">${it.orders.toLocaleString()}</td>
                        <td class="py-1.5 px-3 text-right font-semibold text-emerald-700">₱${it.sales.toLocaleString()}</td>
                        <td class="py-1.5 px-3 text-slate-500">${esc(it.note)}</td>
                    `;
                    customTbody.appendChild(tr);
                });
                if (items.length > 0) {
                    const tot = document.createElement('tr');
                    tot.className = 'bg-slate-50 font-bold';
                    tot.innerHTML = `
                        <td colspan="2" class="py-1.5 px-3 text-slate-900">TOTAL ADDITIONAL LOGGED</td>
                        <td class="py-1.5 px-3 text-right text-slate-900">${sumOrd.toLocaleString()}</td>
                        <td class="py-1.5 px-3 text-right text-emerald-700">₱${sumSales.toLocaleString()}</td>
                        <td></td>
                    `;
                    customTbody.appendChild(tot);
                }
            }
            saveMemDraft();
        };

        const addMemCustomRow = (initData = null) => {
            if (!rowTpl || !rowsList) return;
            const frag = rowTpl.content.cloneNode(true);
            const rowEl = frag.querySelector('[data-custom-row]');
            if (initData) {
                const pIn = rowEl.querySelector('[data-cr-page]');
                const tIn = rowEl.querySelector('[data-cr-type]');
                const oIn = rowEl.querySelector('[data-cr-orders]');
                const sIn = rowEl.querySelector('[data-cr-sales]');
                const nIn = rowEl.querySelector('[data-cr-note]');
                if (pIn) pIn.value = initData.page ?? '';
                if (tIn) tIn.value = initData.type ?? 'Macro Sale';
                if (oIn) oIn.value = initData.orders ?? '';
                if (sIn) sIn.value = initData.sales ?? '';
                if (nIn) nIn.value = initData.note ?? '';
            }
            rowEl.querySelectorAll('input, select').forEach((inp) => {
                inp.addEventListener('input', renderCustomRows);
                inp.addEventListener('change', renderCustomRows);
                inp.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') e.preventDefault();
                });
            });
            rowEl.querySelector('[data-cr-remove]')?.addEventListener('click', () => {
                rowEl.remove();
                renderCustomRows();
            });
            rowsList.appendChild(frag);
            renderCustomRows();
        };

        memFieldIds.forEach((id) => {
            const el = document.getElementById(id);
            el?.addEventListener('input', syncEodPreview);
            el?.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') e.preventDefault();
            });
        });
        document.getElementById('in-show-pages-tbl')?.addEventListener('change', syncEodPreview);

        document.querySelectorAll('[data-fill-val]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const target = document.getElementById(btn.dataset.fillTarget);
                if (target) {
                    target.value = btn.dataset.fillVal;
                    syncEodPreview();
                }
            });
        });

        document.getElementById('add-custom-eod-row')?.addEventListener('click', () => addMemCustomRow());

        document.getElementById('eod-reset-draft')?.addEventListener('click', () => {
            try { localStorage.removeItem(memDraftKey); } catch {}
            window.location.reload();
        });

        // Restore saved draft for this date + member (or open 1 ready row by default)
        try {
            const savedRaw = localStorage.getItem(memDraftKey);
            if (savedRaw) {
                restoringMemDraft = true;
                const parsed = JSON.parse(savedRaw);
                if (parsed?.fields) {
                    Object.entries(parsed.fields).forEach(([id, v]) => {
                        const el = document.getElementById(id);
                        if (el && v !== undefined) el.value = v;
                    });
                }
                if (typeof parsed?.incPages === 'boolean') {
                    const chk = document.getElementById('in-show-pages-tbl');
                    if (chk) chk.checked = parsed.incPages;
                }
                if (Array.isArray(parsed?.rows) && parsed.rows.length > 0) {
                    parsed.rows.forEach((r) => addMemCustomRow(r));
                } else {
                    addMemCustomRow();
                }
                restoringMemDraft = false;
            } else {
                addMemCustomRow();
            }
        } catch {
            restoringMemDraft = false;
            addMemCustomRow();
        }

        // Capture live contenteditable bullet edits right before downloading PDF
        document.getElementById('eod-pdf-form')?.addEventListener('submit', () => {
            syncEodPreview();
            const bullets = {};
            eodPaper.querySelectorAll('ul[data-eod-sec]').forEach((ul) => {
                const key = ul.dataset.eodSec;
                bullets[key] = Array.from(ul.querySelectorAll('li:not(.hidden)'))
                    .map((li) => li.textContent.trim())
                    .filter(Boolean);
            });
            setVal('pdf-bullets', JSON.stringify(bullets));
        });

        // Submit Member EOD to Team Leader Consolidated EOD
        document.getElementById('eod-submit-form')?.addEventListener('submit', () => {
            syncEodPreview();
            const bullets = [];
            eodPaper.querySelectorAll('ul[data-eod-sec] li:not(.hidden)').forEach((li) => {
                const t = li.textContent.trim();
                if (t) bullets.push(t);
            });
            const payload = {
                summary: bullets.slice(0, 4).join(' '),
                custom_rows: JSON.parse(document.getElementById('pdf-custom-rows')?.value || '[]'),
            };
            setVal('submit-eod-payload', JSON.stringify(payload));
        });

        document.getElementById('eod-copy-btn')?.addEventListener('click', async () => {
            const clone = eodPaper.cloneNode(true);
            clone.querySelectorAll('.no-print, .hidden').forEach((n) => n.remove());
            const plain = clone.innerText.replace(/\n{3,}/g, '\n\n').trim();
            try {
                await navigator.clipboard.writeText(plain);
                const lbl = document.getElementById('eod-copy-label');
                if (lbl) {
                    const prev = lbl.textContent;
                    lbl.textContent = 'Copied!';
                    setTimeout(() => { lbl.textContent = prev; }, 2000);
                }
            } catch {}
        });

        syncEodPreview();
        renderCustomRows();
    }

    // 11) My Profile: Search + 6-per-page Pagination
    const profPanel = document.getElementById('profile-pages-panel');
    if (profPanel) {
        const searchIn = document.getElementById('prof-pages-search');
        const prevBtn = document.getElementById('prof-pages-prev');
        const nextBtn = document.getElementById('prof-pages-next');
        const indEl = document.getElementById('prof-pages-ind');
        const rangeEl = document.getElementById('prof-pages-range');
        const perPage = 6;
        let curPage = 1;

        const renderProfPages = (reset = false) => {
            if (reset) curPage = 1;
            const term = (searchIn?.value || '').trim().toLowerCase();
            const matched = [];
            profPanel.querySelectorAll('[data-prof-page-row]').forEach((row) => {
                if (!term || (row.dataset.search || '').includes(term)) {
                    matched.push(row);
                } else {
                    row.classList.add('hidden');
                }
            });
            const total = matched.length;
            const totalPages = Math.max(1, Math.ceil(total / perPage));
            if (curPage > totalPages) curPage = totalPages;
            const start = (curPage - 1) * perPage;
            const end = start + perPage;
            matched.forEach((r, i) => r.classList.toggle('hidden', i < start || i >= end));

            if (indEl) indEl.textContent = `${curPage} / ${totalPages}`;
            if (prevBtn) prevBtn.disabled = curPage <= 1;
            if (nextBtn) nextBtn.disabled = curPage >= totalPages;
            if (rangeEl) rangeEl.textContent = total === 0 ? '0 matching pages' : `${start + 1}–${Math.min(end, total)} of ${total}`;
        };

        searchIn?.addEventListener('input', () => renderProfPages(true));
        prevBtn?.addEventListener('click', () => { if (curPage > 1) { curPage--; renderProfPages(false); } });
        nextBtn?.addEventListener('click', () => { curPage++; renderProfPages(false); });
        renderProfPages(true);
    }

    // 12) Admin / Team Leader Consolidated EOD (with localStorage Draft Persistence across Generate reloads)
    const admPaper = document.getElementById('adm-eod-paper');
    if (admPaper) {
        const admDraftKey = `kpi_adm_eod_v2_${admPaper.dataset.reportDate || 'today'}`;
        let restoringAdmDraft = false;

        const salesToday = Number(admPaper.dataset.salesToday || 0);
        const ordersToday = Number(admPaper.dataset.ordersToday || 0);
        const salesYest = Number(admPaper.dataset.salesYest || 0);
        const aiShareStr = admPaper.dataset.aiShare || '0.0';
        const aovStr = admPaper.dataset.aov || '0';
        const baseMissed = JSON.parse(admPaper.dataset.baseMissed || '[]');
        const pageOwnersMap = JSON.parse(admPaper.dataset.pageOwners || '{}');
        let issuesState = JSON.parse(admPaper.dataset.baseIssues || '[]');

        const admFieldIds = [
            'adm-prep-by', 'adm-position', 'adm-dept', 'adm-consolidated',
            'adm-spend', 'adm-inq', 'adm-yest-spend', 'adm-creatives',
            'adm-decision', 'adm-decision-by',
            'adm-prio-1', 'adm-prio-1-own', 'adm-prio-2', 'adm-prio-2-own', 'adm-prio-3', 'adm-prio-3-own',
        ];

        const gv = (id) => (document.getElementById(id)?.value ?? '').trim();
        const st = (id, v) => { const n = document.getElementById(id); if (n) n.textContent = v; };
        const sv = (id, v) => { const n = document.getElementById(id); if (n) n.value = v; };
        const esc = (s) => String(s ?? '').replace(/</g, '&lt;');

        const saveAdmDraft = () => {
            if (restoringAdmDraft) return;
            try {
                const fields = {};
                admFieldIds.forEach((id) => {
                    const el = document.getElementById(id);
                    if (el) fields[id] = el.value;
                });

                const missedRows = [];
                document.querySelectorAll('#adm-extra-missed-list [data-adm-missed-row]').forEach((node) => {
                    missedRows.push({
                        page: node.querySelector('[data-am-page]')?.value || '',
                        owner: node.querySelector('[data-am-owner]')?.value || '',
                        orders: node.querySelector('[data-am-orders]')?.value || '',
                        sales: node.querySelector('[data-am-sales]')?.value || '',
                    });
                });

                const prodInputs = {};
                admPaper.querySelectorAll('tr[data-prod-row]').forEach((tr) => {
                    const pName = tr.dataset.prodName;
                    const inqV = tr.querySelector('[data-prod-inq]')?.value || '';
                    const spV = tr.querySelector('[data-prod-spend]')?.value || '';
                    if (inqV !== '' || spV !== '') {
                        prodInputs[pName] = { inq: inqV, spend: spV };
                    }
                });

                localStorage.setItem(admDraftKey, JSON.stringify({
                    fields,
                    missedRows,
                    prodInputs,
                    issues: issuesState,
                }));
            } catch {}
        };

        const renderMissedTable = () => {
            const extra = [];
            document.querySelectorAll('#adm-extra-missed-list [data-adm-missed-row]').forEach((node) => {
                const page = (node.querySelector('[data-am-page]')?.value || '').trim();
                const owner = (node.querySelector('[data-am-owner]')?.value || '').trim();
                const orders = Number(node.querySelector('[data-am-orders]')?.value || 0);
                const sales = Number(node.querySelector('[data-am-sales]')?.value || 0);
                if (page || orders > 0 || sales > 0) {
                    const resolvedOwner = owner || pageOwnersMap[page] || '—';
                    extra.push({ page: page || 'Unnamed Page', owner: resolvedOwner, orders, sales });
                }
            });
            sv('apdf-extra-missed', JSON.stringify(extra));

            const combined = [...baseMissed, ...extra];
            const wrap = document.getElementById('aout-missed-wrap');
            const tbody = document.getElementById('aout-missed-tbody');
            wrap?.classList.toggle('hidden', combined.length === 0);

            let ordSum = 0;
            let salSum = 0;
            if (tbody) {
                tbody.innerHTML = '';
                combined.forEach((r, idx) => {
                    ordSum += Number(r.orders || 0);
                    salSum += Number(r.sales || 0);
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="py-1.5 px-3 text-slate-500">${idx + 1}</td>
                        <td class="py-1.5 px-3 font-semibold text-slate-800">${esc(r.page)}</td>
                        <td class="py-1.5 px-3 text-slate-600">${esc(r.owner)}</td>
                        <td class="py-1.5 px-3 text-right font-bold text-slate-900">${Number(r.orders || 0).toLocaleString()}</td>
                        <td class="py-1.5 px-3 text-right font-semibold text-emerald-700">₱${Number(r.sales || 0).toLocaleString()}</td>
                    `;
                    tbody.appendChild(tr);
                });
                if (combined.length > 0) {
                    const tot = document.createElement('tr');
                    tot.className = 'bg-slate-50 font-bold';
                    tot.innerHTML = `
                        <td></td>
                        <td colspan="2" class="py-1.5 px-3 text-slate-900">TOTAL — ${combined.length} page(s)</td>
                        <td class="py-1.5 px-3 text-right text-slate-900">${ordSum.toLocaleString()}</td>
                        <td class="py-1.5 px-3 text-right text-emerald-700">₱${salSum.toLocaleString()}</td>
                    `;
                    tbody.appendChild(tot);
                }
            }
            st('aout-kpi4-val', `${ordSum.toLocaleString()} / ₱${salSum.toLocaleString()}`);
            st('aout-missed-kpi', `${ordSum.toLocaleString()} / ₱${salSum.toLocaleString()}`);
            st('aout-missed-pages-kpi', String(combined.length));
            saveAdmDraft();
        };

        const addAdmMissedRow = (initData = null) => {
            const tpl = document.getElementById('adm-missed-tpl');
            const list = document.getElementById('adm-extra-missed-list');
            if (!tpl || !list) return;
            const frag = tpl.content.cloneNode(true);
            const row = frag.querySelector('[data-adm-missed-row]');
            const pageInput = row.querySelector('[data-am-page]');
            const ownerInput = row.querySelector('[data-am-owner]');
            const ordInput = row.querySelector('[data-am-orders]');
            const salInput = row.querySelector('[data-am-sales]');

            if (initData) {
                if (pageInput) pageInput.value = initData.page ?? '';
                if (ownerInput) ownerInput.value = initData.owner ?? '';
                if (ordInput) ordInput.value = initData.orders ?? '';
                if (salInput) salInput.value = initData.sales ?? '';
            }

            const autoFillOwnerAndRender = () => {
                const pVal = (pageInput?.value || '').trim();
                if (pVal && ownerInput && ownerInput.value.trim() === '' && pageOwnersMap[pVal]) {
                    ownerInput.value = pageOwnersMap[pVal];
                }
                renderMissedTable();
            };

            row.querySelectorAll('input').forEach((inp) => {
                inp.addEventListener('input', autoFillOwnerAndRender);
                inp.addEventListener('change', autoFillOwnerAndRender);
                inp.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') e.preventDefault();
                });
            });

            row.querySelector('[data-am-del]')?.addEventListener('click', () => {
                row.remove();
                renderMissedTable();
            });

            list.appendChild(frag);
            renderMissedTable();
        };

        const renderIssuesTable = () => {
            const tbody = document.getElementById('aout-issues-tbody');
            if (!tbody) return;
            tbody.innerHTML = '';
            if (issuesState.length === 0) {
                issuesState.push({ issue: 'No critical system blockers reported in current window.', owner: 'Team', status: 'Clear' });
            }
            issuesState.forEach((it, idx) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="py-1.5 px-3"><input type="text" data-iss-f="issue" value="${esc(it.issue).replace(/"/g, '&quot;')}" class="w-full bg-transparent focus:bg-primary-soft/25 focus:outline-none rounded px-1 -mx-1"></td>
                    <td class="py-1.5 px-3 font-semibold"><input type="text" data-iss-f="owner" value="${esc(it.owner).replace(/"/g, '&quot;')}" class="w-full bg-transparent focus:bg-primary-soft/25 focus:outline-none rounded px-1 -mx-1"></td>
                    <td class="py-1.5 px-3"><input type="text" data-iss-f="status" value="${esc(it.status).replace(/"/g, '&quot;')}" class="w-full bg-transparent focus:bg-primary-soft/25 focus:outline-none rounded px-1 -mx-1"></td>
                    <td class="no-print py-1.5 px-2 text-center"><button type="button" data-iss-del class="text-slate-400 hover:text-bad cursor-pointer">×</button></td>
                `;
                tr.querySelectorAll('input[data-iss-f]').forEach((inp) => {
                    inp.addEventListener('input', () => {
                        issuesState[idx][inp.dataset.issF] = inp.value;
                        sv('apdf-issues', JSON.stringify(issuesState));
                        saveAdmDraft();
                    });
                    inp.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter') e.preventDefault();
                    });
                });
                tr.querySelector('[data-iss-del]')?.addEventListener('click', () => {
                    issuesState.splice(idx, 1);
                    renderIssuesTable();
                });
                tbody.appendChild(tr);
            });
            sv('apdf-issues', JSON.stringify(issuesState));
            saveAdmDraft();
        };

        const syncAdminEod = () => {
            const prep = gv('adm-prep-by');
            const pos = gv('adm-position');
            const dept = gv('adm-dept');
            const cons = gv('adm-consolidated');
            const creatives = gv('adm-creatives');
            const dec = gv('adm-decision') || 'None / Wala.';
            const decBy = gv('adm-decision-by') || '—';
            const p1 = gv('adm-prio-1');
            const p1o = gv('adm-prio-1-own');
            const p2 = gv('adm-prio-2');
            const p2o = gv('adm-prio-2-own');
            const p3 = gv('adm-prio-3');
            const p3o = gv('adm-prio-3-own');

            st('aout-prep-by', prep);
            st('aout-leader-heading', prep);
            st('aout-sig-name', prep);
            st('aout-position', pos);
            st('aout-sig-pos', pos);
            st('aout-dept', dept);
            st('aout-sig-dept', dept);
            st('aout-consolidated', cons);
            st('aout-decision', dec);
            st('aout-decision-by', decBy);
            st('aout-prio-1', p1); st('aout-prio-1-own', p1o);
            st('aout-prio-2', p2); st('aout-prio-2-own', p2o);
            st('aout-prio-3', p3); st('aout-prio-3-own', p3o);

            document.getElementById('arow-creatives')?.classList.toggle('hidden', creatives === '');
            st('aout-creatives-val', creatives);

            // Per-product optional Inquiries & Spend -> sum up if top-level is blank
            const prodAds = {};
            let sumProdInq = 0;
            let sumProdSpend = 0;
            admPaper.querySelectorAll('tr[data-prod-row]').forEach((tr) => {
                const pName = tr.dataset.prodName;
                const pOrd = Number(tr.dataset.prodOrders || 0);
                const pSal = Number(tr.dataset.prodSales || 0);
                const pInq = Number(tr.querySelector('[data-prod-inq]')?.value || 0);
                const pSpend = Number(tr.querySelector('[data-prod-spend]')?.value || 0);
                sumProdInq += pInq;
                sumProdSpend += pSpend;
                if (pInq > 0 || pSpend > 0) {
                    prodAds[pName] = { inq: pInq, spend: pSpend };
                }
                const parts = [];
                if (pInq > 0) parts.push(`${((pOrd / pInq) * 100).toFixed(1)}%`);
                if (pSpend > 0) parts.push(`${(pSal / pSpend).toFixed(2)}x`);
                const cell = tr.querySelector('[data-prod-calc]');
                if (cell) cell.textContent = parts.length ? parts.join(' · ') : '—';
            });
            sv('apdf-product-ads', JSON.stringify(prodAds));

            const topSpendIn = Number(gv('adm-spend') || 0);
            const topInqIn = Number(gv('adm-inq') || 0);
            const effSpend = topSpendIn > 0 ? topSpendIn : sumProdSpend;
            const effInq = topInqIn > 0 ? topInqIn : sumProdInq;
            const yestSpend = Number(gv('adm-yest-spend') || 0);

            if (effInq > 0) {
                const conv = ((ordersToday / effInq) * 100).toFixed(1) + '%';
                document.getElementById('arow-conv')?.classList.remove('hidden');
                st('aout-conv-today', conv);
                st('aout-kpi1-val', conv);
                st('aout-kpi1-lbl', 'Conv. Rate (Target 20%)');
            } else {
                document.getElementById('arow-conv')?.classList.add('hidden');
                st('aout-kpi1-val', `${aiShareStr}%`);
                st('aout-kpi1-lbl', 'Botcake AI Share');
            }

            if (effSpend > 0) {
                const roas = (salesToday / effSpend).toFixed(2) + 'x';
                document.getElementById('arow-roas')?.classList.remove('hidden');
                st('aout-roas-today', `${roas} (₱${Math.round(effSpend).toLocaleString()})`);
                st('aout-roas-yest', yestSpend > 0 ? `${(salesYest / yestSpend).toFixed(2)}x (₱${Math.round(yestSpend).toLocaleString()})` : '—');
                st('aout-kpi3-val', roas);
                st('aout-kpi3-lbl', `Real POS ROAS`);
            } else {
                document.getElementById('arow-roas')?.classList.add('hidden');
                st('aout-kpi3-val', `₱${aovStr}`);
                st('aout-kpi3-lbl', 'Verified AOV');
            }

            const grandParts = [];
            if (effInq > 0) grandParts.push(`${((ordersToday / effInq) * 100).toFixed(1)}%`);
            if (effSpend > 0) grandParts.push(`${(salesToday / effSpend).toFixed(2)}x`);
            st('aout-prod-grand-calc', grandParts.length ? grandParts.join(' · ') : '—');

            sv('apdf-prep-by', prep);
            sv('apdf-position', pos);
            sv('apdf-department', dept);
            sv('apdf-consolidated', cons);
            sv('apdf-spend', String(effSpend));
            sv('apdf-inq', String(effInq));
            sv('apdf-yest-spend', String(yestSpend));
            sv('apdf-creatives', creatives);
            sv('apdf-decision', dec);
            sv('apdf-decision-by', decBy);
            sv('apdf-prio-1', p1); sv('apdf-prio-1-own', p1o);
            sv('apdf-prio-2', p2); sv('apdf-prio-2-own', p2o);
            sv('apdf-prio-3', p3); sv('apdf-prio-3-own', p3o);
            saveAdmDraft();
        };

        admFieldIds.forEach((id) => {
            const el = document.getElementById(id);
            el?.addEventListener('input', syncAdminEod);
            el?.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') e.preventDefault();
            });
        });

        admPaper.querySelectorAll('[data-prod-inq], [data-prod-spend]').forEach((inp) => {
            inp.addEventListener('input', syncAdminEod);
            inp.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') e.preventDefault();
            });
        });

        document.getElementById('adm-add-missed-btn')?.addEventListener('click', () => addAdmMissedRow());

        document.getElementById('adm-add-issue-btn')?.addEventListener('click', () => {
            issuesState.push({ issue: '', owner: 'Team', status: 'Open' });
            renderIssuesTable();
        });

        document.getElementById('adm-eod-reset-draft')?.addEventListener('click', () => {
            try { localStorage.removeItem(admDraftKey); } catch {}
            window.location.reload();
        });

        // Restore saved Admin EOD draft (or open 1 ready-to-type row by default so typing works immediately)
        try {
            const savedRaw = localStorage.getItem(admDraftKey);
            if (savedRaw) {
                restoringAdmDraft = true;
                const parsed = JSON.parse(savedRaw);
                if (parsed?.fields) {
                    Object.entries(parsed.fields).forEach(([id, v]) => {
                        const el = document.getElementById(id);
                        if (el && v !== undefined) el.value = v;
                    });
                }
                if (parsed?.prodInputs) {
                    admPaper.querySelectorAll('tr[data-prod-row]').forEach((tr) => {
                        const p = parsed.prodInputs[tr.dataset.prodName];
                        if (p) {
                            const iEl = tr.querySelector('[data-prod-inq]');
                            const sEl = tr.querySelector('[data-prod-spend]');
                            if (iEl && p.inq !== undefined) iEl.value = p.inq;
                            if (sEl && p.spend !== undefined) sEl.value = p.spend;
                        }
                    });
                }
                if (Array.isArray(parsed?.issues) && parsed.issues.length > 0) {
                    issuesState = parsed.issues;
                }
                if (Array.isArray(parsed?.missedRows) && parsed.missedRows.length > 0) {
                    parsed.missedRows.forEach((r) => addAdmMissedRow(r));
                } else {
                    addAdmMissedRow();
                }
                restoringAdmDraft = false;
            } else {
                addAdmMissedRow();
            }
        } catch {
            restoringAdmDraft = false;
            addAdmMissedRow();
        }

        document.getElementById('adm-eod-pdf-form')?.addEventListener('submit', () => {
            syncAdminEod();
            renderMissedTable();
            sv('apdf-leader-sum', document.getElementById('aout-leader-sum-p')?.textContent.trim() || '');
            const mNotes = {};
            admPaper.querySelectorAll('[data-member-note]').forEach((el) => {
                mNotes[el.dataset.memberNote] = el.textContent.trim();
            });
            sv('apdf-member-notes', JSON.stringify(mNotes));
            sv('apdf-issues', JSON.stringify(issuesState));
        });

        document.getElementById('adm-eod-copy-btn')?.addEventListener('click', async () => {
            const clone = admPaper.cloneNode(true);
            clone.querySelectorAll('.no-print, .hidden').forEach((n) => n.remove());
            const plain = clone.innerText.replace(/\n{3,}/g, '\n\n').trim();
            try {
                await navigator.clipboard.writeText(plain);
                const lbl = document.getElementById('adm-eod-copy-label');
                if (lbl) {
                    const prev = lbl.textContent;
                    lbl.textContent = 'Copied!';
                    setTimeout(() => { lbl.textContent = prev; }, 2000);
                }
            } catch {}
        });

        renderMissedTable();
        renderIssuesTable();
        syncAdminEod();
    }
});
