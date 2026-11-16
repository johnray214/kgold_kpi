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
            startTopProgress();
            const btn = e.submitter || form.querySelector('button[type="submit"], button:not([type="button"])');
            if (!btn || btn.dataset.spinning) return;
            btn.dataset.spinning = '1';
            btn.classList.add('pointer-events-none', 'opacity-85');
            requestAnimationFrame(() => { btn.disabled = true; });
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
            tr.classList.toggle('hidden', !visible);
            if (visible) shown++;
        });
        document.getElementById('bc-empty-filter')?.classList.toggle('hidden', shown > 0);
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
        const target = document.getElementById(input.dataset.tableSearch);
        if (!target) return;
        input.addEventListener('input', () => {
            const term = input.value.trim().toLowerCase();
            target.querySelectorAll('[data-search-item]').forEach((item) => {
                if (!term) {
                    item.classList.remove('hidden');
                    return;
                }
                let hay = item.dataset.search || '';
                item.querySelectorAll('input[type="text"]').forEach((ti) => {
                    if (ti.value) hay += ' ' + ti.value.toLowerCase();
                });
                item.classList.toggle('hidden', !hay.includes(term));
            });
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
        });
    });
});
