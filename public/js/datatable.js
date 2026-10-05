/*
 * Solkeb data tables: search, sort, page size, paging and CSV export for any <table class="datatable">.
 * No libraries needed.
 *
 *   <table class="datatable" data-page-length="25" data-order="2,desc" data-export="guests">
 *   <td data-order="2026-09-30">30 Sep 2026</td>   sort by this value instead of the text
 *   <th class="no-sort">                           column is not sortable (empty headers are automatic)
 */
(function () {
    'use strict';

    var SIZES = [10, 25, 50, 100, -1];

    function text(el) { return (el.textContent || '').replace(/\s+/g, ' ').trim(); }

    function key(td) {
        var v = td.hasAttribute('data-order') ? td.getAttribute('data-order') : text(td);
        var n = v.replace(/[,\s%]|ETB/g, '').replace('−', '-');
        if (n !== '' && n !== '-' && !isNaN(n)) return { n: parseFloat(n) };
        return { s: v.toLowerCase() };
    }

    function compare(a, b) {
        if ('n' in a && 'n' in b) return a.n - b.n;
        if ('n' in a) return -1;
        if ('n' in b) return 1;
        return a.s.localeCompare(b.s, undefined, { numeric: true });
    }

    function el(tag, cls, html) {
        var e = document.createElement(tag);
        if (cls) e.className = cls;
        if (html != null) e.innerHTML = html;
        return e;
    }

    function DataTable(table) {
        var tbody = table.tBodies[0];
        var heads = table.tHead ? Array.prototype.slice.call(table.tHead.rows[0].cells) : [];
        var rows = Array.prototype.slice.call(tbody.rows);
        var emptyText = 'No records found.';

        // A single "nothing here" row from the server is not data
        if (rows.length === 1 && rows[0].cells.length === 1 && rows[0].cells[0].colSpan > 1) {
            emptyText = text(rows[0]) || emptyText;
            rows = [];
        }

        var actionCol = heads.map(function (th) { return th.classList.contains('no-sort') || text(th) === ''; });
        var data = rows.map(function (tr, i) {
            var cells = Array.prototype.slice.call(tr.cells);
            return {
                tr: tr, i: i,
                keys: cells.map(key),
                search: cells.filter(function (c, j) { return !actionCol[j]; }).map(text).join(' ').toLowerCase()
            };
        });

        var saved = null;
        try { saved = JSON.parse(localStorage.getItem('dt-size') || 'null'); } catch (e) { /* private mode */ }
        var state = {
            q: '', page: 0,
            size: parseInt(table.getAttribute('data-page-length') || saved || 25, 10),
            col: null, dir: 1
        };
        var order = (table.getAttribute('data-order') || '').split(',');
        if (order[0] !== '') { state.col = parseInt(order[0], 10); state.dir = order[1] === 'desc' ? -1 : 1; }

        // ---- Controls ------------------------------------------------------------------------------
        var holder = table.closest('.table-wrap') || table; // keep the horizontal-scroll wrapper around the table
        var wrap = el('div', 'dt');
        holder.parentNode.insertBefore(wrap, holder);
        var top = el('div', 'dt-top');
        var sizeSel = el('select', 'dt-size');
        SIZES.forEach(function (s) {
            var o = el('option', null, s === -1 ? 'All' : String(s));
            o.value = s; if (s === state.size) o.selected = true; sizeSel.appendChild(o);
        });
        if (SIZES.indexOf(state.size) === -1) { var o = el('option', null, String(state.size)); o.value = state.size; o.selected = true; sizeSel.prepend(o); }
        var left = el('label', 'dt-length'); left.append('Show ', sizeSel, ' entries');

        var right = el('div', 'dt-right');
        var search = el('input'); search.type = 'search'; search.placeholder = 'Search…'; search.setAttribute('aria-label', 'Search this table');
        var searchLabel = el('label', 'dt-search'); searchLabel.append('Search: ', search);
        right.appendChild(searchLabel);
        if (table.hasAttribute('data-export')) {
            var csv = el('button', 'btn btn-sm btn-light', 'CSV'); csv.type = 'button'; csv.title = 'Download the rows shown by the search as a CSV file';
            right.appendChild(csv);
            csv.addEventListener('click', exportCsv);
        }
        top.append(left, right);

        var bottom = el('div', 'dt-bottom');
        var info = el('div', 'dt-info'); info.setAttribute('aria-live', 'polite');
        var pager = el('div', 'dt-pager');
        bottom.append(info, pager);

        wrap.appendChild(top);
        wrap.appendChild(holder);
        wrap.appendChild(bottom);

        // ---- Sortable headers ----------------------------------------------------------------------
        heads.forEach(function (th, j) {
            if (actionCol[j]) return;
            th.classList.add('dt-sortable');
            th.tabIndex = 0;
            th.setAttribute('role', 'button');
            var go = function () {
                if (state.col === j) { state.dir = -state.dir; } else { state.col = j; state.dir = 1; }
                state.page = 0; render();
            };
            th.addEventListener('click', go);
            th.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); } });
        });

        search.addEventListener('input', function () { state.q = search.value.trim().toLowerCase(); state.page = 0; render(); });
        sizeSel.addEventListener('change', function () {
            state.size = parseInt(sizeSel.value, 10); state.page = 0;
            try { localStorage.setItem('dt-size', JSON.stringify(state.size)); } catch (e) { /* ignore */ }
            render();
        });

        function filtered() {
            var terms = state.q ? state.q.split(/\s+/) : [];
            var list = data.filter(function (d) { return terms.every(function (t) { return d.search.indexOf(t) !== -1; }); });
            if (state.col !== null) {
                list.sort(function (a, b) { return compare(a.keys[state.col], b.keys[state.col]) * state.dir || a.i - b.i; });
            }
            return list;
        }

        function render() {
            var list = filtered();
            var size = state.size === -1 ? list.length || 1 : state.size;
            var pages = Math.max(1, Math.ceil(list.length / size));
            state.page = Math.min(state.page, pages - 1);
            var start = state.page * size;
            var shown = list.slice(start, start + size);

            tbody.textContent = '';
            if (!shown.length) {
                var tr = el('tr'), td = el('td', 'empty');
                td.colSpan = heads.length || 1;
                td.textContent = data.length ? 'No matching records.' : emptyText;
                tr.appendChild(td); tbody.appendChild(tr);
            } else {
                shown.forEach(function (d) { tbody.appendChild(d.tr); });
            }

            heads.forEach(function (th, j) {
                th.classList.remove('dt-asc', 'dt-desc');
                if (actionCol[j]) return;
                if (state.col === j) {
                    th.classList.add(state.dir === 1 ? 'dt-asc' : 'dt-desc');
                    th.setAttribute('aria-sort', state.dir === 1 ? 'ascending' : 'descending');
                } else {
                    th.removeAttribute('aria-sort');
                }
            });

            info.textContent = list.length
                ? 'Showing ' + (start + 1) + ' to ' + (start + shown.length) + ' of ' + list.length + ' entries'
                  + (list.length !== data.length ? ' (filtered from ' + data.length + ')' : '')
                : 'Showing 0 entries' + (data.length ? ' (filtered from ' + data.length + ')' : '');

            pager.textContent = '';
            if (pages > 1) {
                var add = function (label, page, disabled, current) {
                    var b = el('button', 'dt-page' + (current ? ' current' : ''), label);
                    b.type = 'button'; b.disabled = disabled;
                    if (current) b.setAttribute('aria-current', 'page');
                    b.addEventListener('click', function () { state.page = page; render(); });
                    pager.appendChild(b);
                };
                add('Previous', state.page - 1, state.page === 0);
                var nums = [];
                for (var p = 0; p < pages; p++) {
                    if (p === 0 || p === pages - 1 || Math.abs(p - state.page) <= 1) nums.push(p);
                }
                nums.forEach(function (p, k) {
                    if (k && p - nums[k - 1] > 1) pager.appendChild(el('span', 'dt-gap', '…'));
                    add(String(p + 1), p, false, p === state.page);
                });
                add('Next', state.page + 1, state.page === pages - 1);
            }
        }

        function exportCsv() {
            var cols = heads.map(function (th, j) { return j; }).filter(function (j) { return !actionCol[j]; });
            var esc = function (v) { return /[",\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; };
            var lines = [cols.map(function (j) { return esc(text(heads[j])); }).join(',')];
            filtered().forEach(function (d) {
                lines.push(cols.map(function (j) { return esc(text(d.tr.cells[j] || el('td'))); }).join(','));
            });
            var blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
            var a = el('a'); a.href = URL.createObjectURL(blob);
            a.download = (table.getAttribute('data-export') || 'table') + '-' + new Date().toISOString().slice(0, 10) + '.csv';
            document.body.appendChild(a); a.click();
            setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 0);
        }

        render();
    }

    function init() { document.querySelectorAll('table.datatable').forEach(DataTable); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
