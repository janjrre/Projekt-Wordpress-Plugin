/* M6-10: Presentation-only adapter to the existing protected M4/M6 REST commands.
 * No identity decisions, published-version checks or consent evidence live here.
 */
(() => {
	'use strict';
	function uuid() {
		if (!globalThis.crypto || typeof crypto.randomUUID !== 'function') throw new Error('Secure browser unavailable');
		return crypto.randomUUID();
	}
	function evaluate(ast, values, depth = 0) {
		if (!ast) return true;
		if (depth > 8 || typeof ast !== 'object') return false;
		if (Array.isArray(ast.all)) return ast.all.every(child => evaluate(child, values, depth + 1));
		if (Array.isArray(ast.any)) return ast.any.some(child => evaluate(child, values, depth + 1));
		if (ast.not) return !evaluate(ast.not, values, depth + 1);
		if (ast.source !== 'registration' || !Object.prototype.hasOwnProperty.call(values, ast.field)) return false;
		const a = values[ast.field], b = ast.value;
		switch (ast.operator) {
			case 'exists': return a !== null && a !== '' && (!Array.isArray(a) || a.length > 0);
			case 'empty': return a === null || a === '' || (Array.isArray(a) && !a.length);
			case 'eq': return a !== null && a === b;
			case 'neq': return a !== null && a !== b;
			case 'in': return a !== null && Array.isArray(b) && b.includes(a);
			case 'not_in': return a !== null && Array.isArray(b) && !b.includes(a);
			case 'lt': case 'lte': case 'gt': case 'gte':
				if (a === null || b === null || !Number.isFinite(Number(a)) || !Number.isFinite(Number(b)) || typeof a === 'boolean' || typeof b === 'boolean') return false;
				return ({lt: Number(a) < Number(b), lte: Number(a) <= Number(b), gt: Number(a) > Number(b), gte: Number(a) >= Number(b)})[ast.operator];
			case 'date_before': case 'date_after': {
				const date = s => typeof s === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(s) && !Number.isNaN(Date.parse(s)) && new Date(s).toISOString().slice(0, 10) === s;
				return date(a) && date(b) && (ast.operator === 'date_before' ? a < b : a > b);
			}
			default: return false;
		}
	}
	function value(group) {
		const controls = Array.from(group.querySelectorAll('[data-uop-field]')).filter(el => !el.disabled);
		if (!controls.length) return null;
		const first = controls[0];
		if (first.type === 'radio') return controls.find(el => el.checked)?.value ?? null;
		if (first.type === 'checkbox') return first.checked;
		if (first.multiple) return Array.from(first.selectedOptions, opt => opt.value);
		if (first.type === 'number') return first.value === '' ? null : Number(first.value);
		return first.value === '' ? null : first.value;
	}
	function refresh(form) {
		const groups = Array.from(form.querySelectorAll('[data-uop-field-group]'));
		// Dependencies have already been checked for cycles by the published FormSchema.
		for (let pass = 0; pass <= groups.length; pass++) {
			const values = Object.fromEntries(groups.map(g => [g.dataset.uopFieldGroup, g.hidden ? null : value(g)]));
			let changed = false;
			for (const group of groups) {
				let condition = null;
				if (group.dataset.uopCondition) {
					try { condition = JSON.parse(group.dataset.uopCondition); } catch { condition = {source: 'invalid'}; }
				}
				const hidden = !evaluate(condition, values);
				if (hidden !== group.hidden) changed = true;
				group.hidden = hidden;
				group.querySelectorAll('[data-uop-field]').forEach(el => {
					el.disabled = hidden;
					el.required = !hidden && el.dataset.uopRequired === '1';
				});
			}
			if (!changed) break;
		}
	}
	function fields(form) {
		const result = {};
		for (const group of form.querySelectorAll('[data-uop-field-group]')) {
			if (group.hidden) continue;
			const current = value(group);
			if (current !== null) result[group.dataset.uopFieldGroup] = current;
		}
		return result;
	}
	async function request(path, options = {}, nonce = '') {
		const headers = { 'Accept': 'application/json', ...(options.body ? {'Content-Type': 'application/json'} : {}) };
		if (nonce) headers['X-WP-Nonce'] = nonce;
		const response = await fetch(path, {...options, headers, credentials: 'same-origin', cache: 'no-store', referrerPolicy: 'no-referrer'});
		if (!response.ok) throw new Error('http_' + response.status);
		return response.json();
	}
	async function init(form) {
		const button = form.querySelector('[data-uop-submit]');
		const result = form.querySelector('[data-uop-result]');
		const guest = form.dataset.uopGuest === '1';
		const rest = form.dataset.uopRest;
		const nonce = guest ? '' : (globalThis.window.uopM6Registration?.nonce || '');
		const subject = form.querySelector('[data-uop-subject]');
		let command = uuid();
		let finished = false;
		if (!guest) {
			if (!nonce || !subject) return;
			try {
				const data = await request(rest + 'me/portal', {}, nonce);
				const items = (data.items || []).filter(item => item.can_view_entries);
				subject.replaceChildren(new globalThis.Option('Choose a person', ''));
				for (const item of items) subject.add(new globalThis.Option(item.display_name, item.public_id));
				subject.disabled = items.length === 0;
				if (!items.length) result.textContent = 'No authorized person is available for registration.';
			} catch {
				result.textContent = 'The authorized persons could not be loaded. Please sign in again.';
				return;
			}
		}
		refresh(form);
		button.disabled = Boolean(subject?.disabled);
		form.addEventListener('input', () => { if (!finished) { command = uuid(); refresh(form); } });
		form.addEventListener('change', () => { if (!finished) { command = uuid(); refresh(form); } });
		form.addEventListener('submit', async event => {
			event.preventDefault();
			if (finished || button.disabled) return;
			refresh(form);
			if (!form.reportValidity()) return;
			button.disabled = true;
			result.textContent = 'Submitting registration…';
			const body = {event_id: form.dataset.uopEvent, command_id: command, fields: fields(form)};
			const occurrence = form.querySelector('[data-uop-occurrence]')?.value;
			if (occurrence) body.occurrence_id = occurrence;
			if (!guest) {
				if (!subject?.value) { result.textContent = 'Choose an authorized person.'; button.disabled = false; return; }
				body.person_id = subject.value;
			}
			try {
				const data = await request(rest + (guest ? 'registrations/guest' : 'registrations'), {method: 'POST', body: JSON.stringify(body)}, nonce);
				if (guest && data.status !== 'received') throw new Error('invalid_receipt');
				if (!guest && data.status !== 'submitted') throw new Error('invalid_receipt');
				finished = true;
				result.textContent = guest
					? 'If the registration was accepted, an email confirmation link will be sent. Please check your mailbox.'
					: 'Your registration was received. You can check its status in your participant portal.';
				form.querySelectorAll('input, textarea, select').forEach(el => { el.disabled = true; });
			} catch {
				result.textContent = 'Registration could not be completed. Check your entries or try again later.';
				button.disabled = false;
			}
		});
	}
	function start() {
		globalThis.document.querySelectorAll('[data-uop-registration-form]').forEach(form => {
			init(form).catch(() => {
				form.querySelector('[data-uop-result]').textContent = 'Secure registration is unavailable in this browser.';
			});
		});
	}
	if (globalThis.document.readyState === 'loading') globalThis.document.addEventListener('DOMContentLoaded', start, {once:true});
	else start();
})();
