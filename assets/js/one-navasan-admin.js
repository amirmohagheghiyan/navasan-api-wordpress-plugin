(function () {
	'use strict';

	var cfg = window.oneNavasanAdmin;
	var result = document.getElementById('one-navasan-result');
	if (!cfg || !result) {
		return;
	}

	function post(action, data) {
		var body = new URLSearchParams();
		body.append('action', 'one_navasan_' + action);
		body.append('nonce', cfg.nonce);
		Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });
		return fetch(cfg.ajax, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (r) { return r.json(); });
	}

	function show(text, cls) {
		result.innerHTML = '';
		var p = document.createElement('p');
		p.className = cls || '';
		p.textContent = text;
		result.appendChild(p);
		return p;
	}

	function disableAll(state) {
		document.querySelectorAll('[data-one-action]').forEach(function (b) { b.disabled = state; });
	}

	function simple(action, onDone) {
		show(cfg.i18n.working);
		disableAll(true);
		post(action).then(function (r) {
			disableAll(false);
			onDone(r);
		}).catch(function () {
			disableAll(false);
			show(cfg.i18n.error, 'one-bad');
		});
	}

	function summarize(totals) {
		var skipped = 0;
		Object.keys(totals.skipped).forEach(function (k) { skipped += totals.skipped[k]; });
		return cfg.i18n.updated + ' ' + totals.updated + ' | ' + cfg.i18n.unchanged + ' ' + totals.unchanged + ' | ' + cfg.i18n.skipped + ' ' + skipped;
	}

	function runChunks(mode) {
		var cursor = 0, shown = 0;
		var totals = { updated: 0, unchanged: 0, skipped: {}, products: 0 };
		var examples = [];
		result.innerHTML = '';
		var status = document.createElement('p');
		result.appendChild(status);
		var table = null, tbody = null;
		if (mode === 'preview') {
			table = document.createElement('table');
			table.className = 'widefat striped one-table';
			table.innerHTML = '<thead><tr><th></th><th></th><th></th><th></th></tr></thead><tbody></tbody>';
			var ths = table.querySelectorAll('th');
			ths[0].textContent = cfg.i18n.colName;
			ths[1].textContent = cfg.i18n.colOld;
			ths[2].textContent = cfg.i18n.colNew;
			ths[3].textContent = cfg.i18n.colDetail;
			tbody = table.querySelector('tbody');
			result.appendChild(table);
		}
		disableAll(true);

		function step() {
			status.textContent = cfg.i18n.progress + ' ' + totals.products + ' — ' + summarize(totals);
			post('chunk', { mode: mode, cursor: cursor, shown: shown }).then(function (r) {
				if (!r.success) {
					disableAll(false);
					status.className = 'one-bad';
					status.textContent = (r.data && r.data.message) || cfg.i18n.error;
					return;
				}
				var d = r.data;
				if (tbody && d.rows_html) {
					tbody.insertAdjacentHTML('beforeend', d.rows_html); // Server-escaped markup.
					shown += d.rows_count;
				}
				totals.updated += d.counts.updated;
				totals.unchanged += d.counts.unchanged;
				totals.products += d.counts.products;
				Object.keys(d.counts.skipped).forEach(function (k) {
					totals.skipped[k] = (totals.skipped[k] || 0) + d.counts.skipped[k];
				});
				d.counts.examples.forEach(function (e) { if (examples.length < 15) { examples.push(e); } });
				cursor = d.cursor;
				if (!d.done) {
					step();
					return;
				}
				finish();
			}).catch(function () {
				disableAll(false);
				status.className = 'one-bad';
				status.textContent = cfg.i18n.error;
			});
		}

		function finish() {
			status.className = 'one-ok';
			status.textContent = (mode === 'preview' ? cfg.i18n.previewOk : cfg.i18n.applyOk) + ' — ' + summarize(totals);
			if (examples.length) {
				var h = document.createElement('p');
				h.textContent = cfg.i18n.sample;
				result.appendChild(h);
				var ul = document.createElement('ul');
				examples.forEach(function (e) {
					var li = document.createElement('li');
					li.textContent = '#' + e.id + ' ' + e.name + ' — ' + e.reason;
					ul.appendChild(li);
				});
				result.appendChild(ul);
			}
			disableAll(false);
			var apply = document.querySelector('[data-one-action="apply"]');
			if (mode === 'preview' && apply) { apply.disabled = false; }
			var recalc = document.querySelector('[data-one-action="recalc"]');
			if (mode === 'apply' && recalc) { recalc.disabled = false; }
		}

		step();
	}

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-one-action]');
		if (!btn || btn.disabled) { return; }
		var action = btn.getAttribute('data-one-action');
		if (action === 'fetch') {
			simple('fetch', function (r) {
				show((r.data && r.data.message) || cfg.i18n.error, r.success && r.data.ok ? 'one-ok' : 'one-bad');
				setTimeout(function () { window.location.reload(); }, 1200);
			});
		} else if (action === 'test') {
			simple('test', function (r) {
				if (r.success) { result.innerHTML = r.data.html; } else { show(cfg.i18n.error, 'one-bad'); }
			});
		} else if (action === 'approve') {
			simple('approve', function () { window.location.reload(); });
		} else if (action === 'preview') {
			runChunks('preview');
		} else if (action === 'apply' || action === 'recalc') {
			if (window.confirm(cfg.i18n.confirm)) { runChunks('apply'); }
		}
	});
})();
