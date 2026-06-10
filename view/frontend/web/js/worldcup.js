/**
 * Antoine World Cup Hub — polling JS.
 *
 * Progressively enhances the hub.phtml server render:
 *  - Reads the initial snapshot from data-wc-initial.
 *  - Renders tabs (live, fixtures, standings, bracket) client-side.
 *  - Polls data-wc-data-url every data-wc-poll ms when any match is live.
 *  - Exposes DOM contract required by E2E tests:
 *      .wc-hub, .wc-match, .wc-match--live, .wc-standings,
 *      [data-wc-data-url], [data-wc-poll], [data-wc-initial],
 *      [data-wc-tab], [data-wc-panel], [data-wc-updated],
 *      [data-wc-refresh].
 */
(function () {
    'use strict';

    var root = document.querySelector('.wc-hub');
    if (!root || !root.dataset.wcDataUrl) { return; }

    var DATA_URL = root.dataset.wcDataUrl;
    var POLL_MS  = parseInt(root.dataset.wcPoll, 10) || 45000;
    var state    = JSON.parse(root.dataset.wcInitial || '{}');
    var active   = 'live';
    var timer    = null;

    /* ------------------------------------------------------------------ */
    /* DOM helpers                                                          */
    /* ------------------------------------------------------------------ */

    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls)      { n.className = cls; }
        if (text != null) { n.textContent = text; }
        return n;
    }

    function teamLabel(side, fallbackLabel) {
        return (side && side.name) ? side.name : (fallbackLabel || 'TBD');
    }

    function flagImg(team) {
        if (!team || !team.flag) { return null; }
        var img = el('img', 'wc-flag');
        img.src = team.flag;          // trusted CDN URL from bundled teams.json; set as attribute (no XSS)
        img.alt = '';
        img.loading = 'lazy';
        return img;
    }

    function matchRow(m) {
        var row = el('div', 'wc-match wc-match--' + m.status);
        row.appendChild(el('span', 'wc-match__home',  teamLabel(m.home,  m.home_label)));
        row.appendChild(el('span', 'wc-match__score', m.home_score + ' – ' + m.away_score));
        row.appendChild(el('span', 'wc-match__away',  teamLabel(m.away,  m.away_label)));
        var metaText = m.status === 'live'
            ? ('LIVE ' + (m.time_elapsed || ''))
            : (m.kickoff_beirut || '');
        row.appendChild(el('span', 'wc-match__meta', metaText));
        return row;
    }

    /* ------------------------------------------------------------------ */
    /* Tab renderers                                                        */
    /* ------------------------------------------------------------------ */

    function renderLive(panel) {
        var live = (state.matches && state.matches.live)     || [];
        var fin  = (state.matches && state.matches.finished) || [];
        if (!live.length && !fin.length) {
            panel.appendChild(el('p', 'wc-empty', 'No matches yet.'));
            return;
        }
        if (live.length) {
            panel.appendChild(el('h2', null, 'Live now'));
            live.forEach(function (m) { panel.appendChild(matchRow(m)); });
        }
        if (fin.length) {
            panel.appendChild(el('h2', null, 'Recent results'));
            fin.forEach(function (m) { panel.appendChild(matchRow(m)); });
        }
    }

    function renderFixtures(panel) {
        var up = (state.matches && state.matches.upcoming) || [];
        if (!up.length) {
            panel.appendChild(el('p', 'wc-empty', 'No upcoming fixtures.'));
            return;
        }
        up.forEach(function (m) { panel.appendChild(matchRow(m)); });
    }

    function renderStandings(panel) {
        var s = state.standings || {};
        Object.keys(s).forEach(function (g) {
            panel.appendChild(el('h2', null, 'Group ' + g));
            var table = el('table', 'wc-standings');
            var head  = el('tr');
            ['#', 'Team', 'P', 'W', 'D', 'L', 'GD', 'Pts'].forEach(function (h) {
                head.appendChild(el('th', null, h));
            });
            table.appendChild(head);
            s[g].forEach(function (r) {
                var tr = el('tr');
                tr.appendChild(el('td', null, String(r.rank)));
                var teamTd = el('td', 'wc-standings__team');
                var fi = flagImg(r.team);
                if (fi) { teamTd.appendChild(fi); }
                teamTd.appendChild(el('span', 'wc-team-name', (r.team && r.team.name) || ''));
                tr.appendChild(teamTd);
                [r.played, r.win, r.draw, r.loss, r.gd, r.pts]
                    .forEach(function (v) { tr.appendChild(el('td', null, String(v))); });
                table.appendChild(tr);
            });
            panel.appendChild(table);
        });
    }

    function renderBracket(panel) {
        var b = state.bracket || {};
        ['r32', 'r16', 'qf', 'sf', 'third', 'final'].forEach(function (round) {
            var ms = b[round] || [];
            if (!ms.length) { return; }
            panel.appendChild(el('h2', null, round.toUpperCase()));
            ms.forEach(function (m) { panel.appendChild(matchRow(m)); });
        });
    }

    /* ------------------------------------------------------------------ */
    /* Render loop                                                          */
    /* ------------------------------------------------------------------ */

    function render() {
        document.querySelectorAll('[data-wc-panel]').forEach(function (p) {
            p.hidden = (p.dataset.wcPanel !== active);
            if (p.hidden) { return; }
            p.innerHTML = '';
            if      (active === 'live')      { renderLive(p);      }
            else if (active === 'fixtures')  { renderFixtures(p);  }
            else if (active === 'standings') { renderStandings(p); }
            else if (active === 'bracket')   { renderBracket(p);   }
        });
        var stamp = root.querySelector('[data-wc-updated]');
        if (stamp) {
            stamp.textContent = state.generated_at
                ? ('Updated ' + state.generated_at.substring(11, 16))
                : '';
        }
    }

    /* ------------------------------------------------------------------ */
    /* Polling                                                              */
    /* ------------------------------------------------------------------ */

    function schedule() {
        if (timer) { clearTimeout(timer); timer = null; }
        if (state.any_live) { timer = setTimeout(refresh, POLL_MS); }
    }

    function refresh() {
        fetch(DATA_URL, { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.matches) { state = data; render(); }
            })
            .catch(function () { /* keep last render on network error */ })
            .then(schedule);
    }

    /* ------------------------------------------------------------------ */
    /* Event wiring                                                         */
    /* ------------------------------------------------------------------ */

    root.querySelectorAll('[data-wc-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            active = btn.dataset.wcTab;
            root.querySelectorAll('[data-wc-tab]').forEach(function (b) {
                b.setAttribute('aria-selected', String(b === btn));
            });
            render();
        });
    });

    var refreshBtn = root.querySelector('[data-wc-refresh]');
    if (refreshBtn) { refreshBtn.addEventListener('click', refresh); }

    /* ------------------------------------------------------------------ */
    /* Boot                                                                 */
    /* ------------------------------------------------------------------ */

    // Paint instantly from the inlined snapshot (fast first render, SEO), then
    // ALWAYS pull fresh data from the JSON endpoint. The page HTML is FPC-cached,
    // so its inlined snapshot can be stale; this unconditional fetch decouples the
    // live experience from the page cache and (re)schedules polling from the
    // freshly-fetched any_live flag.
    render();
    refresh();
}());
