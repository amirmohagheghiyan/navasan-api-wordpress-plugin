/**
 * Fills elements having data-one-gold="value|change_val|change_pct|high|low|updated"
 * with the current rate from the plugin REST route. Inputs get their value set.
 * Optional attributes: data-one-symbol, data-one-unit (toman|rial),
 * data-one-format (separator|plain), data-one-digits (fa|en).
 */
(function () {
	'use strict';

	var nodes = document.querySelectorAll('[data-one-gold]');
	if (!nodes.length || !window.oneNavasan || !window.oneNavasan.rest) {
		return;
	}

	var faDigits = '۰۱۲۳۴۵۶۷۸۹';

	function toFa(text) {
		return String(text).replace(/\d/g, function (d) { return faDigits.charAt(d); })
			.replace(/,/g, '٬').replace(/\./g, '٫');
	}

	function format(number, fmt, digits, field) {
		if (number === null || number === undefined || isNaN(number)) {
			return '';
		}
		var decimals = (field === 'change_pct' || Math.round(number) !== number) ? 2 : 0;
		var text = Number(number).toFixed(decimals);
		if (decimals) {
			text = text.replace(/0+$/, '').replace(/\.$/, '');
		}
		if (fmt !== 'plain') {
			var parts = text.split('.');
			parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
			text = parts.join('.');
		}
		return digits === 'fa' ? toFa(text) : text;
	}

	function fill(el, data) {
		var field = el.getAttribute('data-one-gold') || 'value';
		var value = data[field];
		var isInput = /^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName);
		if (value === undefined || value === null) {
			return;
		}
		if (field === 'updated') {
			var date = new Date(value * 1000);
			var locale = el.getAttribute('data-one-digits') === 'en' ? 'en-GB' : 'fa-IR';
			el.textContent = date.toLocaleString(locale);
			return;
		}
		if (isInput) {
			el.value = value;
			el.dispatchEvent(new Event('input', { bubbles: true }));
			el.dispatchEvent(new Event('change', { bubbles: true }));
			return;
		}
		el.textContent = format(value, el.getAttribute('data-one-format'), el.getAttribute('data-one-digits') || 'fa', field);
	}

	// Group elements by symbol + unit so each combination is requested once.
	var groups = {};
	Array.prototype.forEach.call(nodes, function (el) {
		var symbol = el.getAttribute('data-one-symbol') || '';
		var unit = el.getAttribute('data-one-unit') === 'rial' ? 'rial' : 'toman';
		var key = symbol + '|' + unit;
		(groups[key] = groups[key] || { symbol: symbol, unit: unit, els: [] }).els.push(el);
	});

	Object.keys(groups).forEach(function (key) {
		var g = groups[key];
		var url = window.oneNavasan.rest + (window.oneNavasan.rest.indexOf('?') > -1 ? '&' : '?') +
			'unit=' + encodeURIComponent(g.unit) + (g.symbol ? '&symbol=' + encodeURIComponent(g.symbol) : '');
		fetch(url, { credentials: 'omit' })
			.then(function (r) { return r.ok ? r.json() : null; })
			.then(function (data) {
				if (!data) { return; }
				g.els.forEach(function (el) { fill(el, data); });
			})
			.catch(function () { /* keep server-rendered content */ });
	});
})();
