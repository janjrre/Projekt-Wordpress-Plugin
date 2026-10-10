/* global wp, document, window */
(function () {
	'use strict';
	const api = wp.apiFetch;
	const t = wp.i18n.__;
	const cfg = window.uopM6Portal || {};
	api.use(api.createNonceMiddleware(cfg.nonce || ''));
	const base = '/uop/v1';
	const request = (path, options) => api({ path: base + path, ...options });
	const uuid = () => window.crypto && window.crypto.randomUUID ? window.crypto.randomUUID() : '';
	const el = (tag, className, text) => {
		const n = document.createElement(tag);
		if (className) n.className = className;
		if (typeof text !== 'undefined') n.textContent = String(text);
		return n;
	};
	const button = (text, fn, kind) => {
		const n = el('button', 'uop-portal__button' + (kind ? ' uop-portal__button--' + kind : ''), text);
		n.type = 'button';
		n.addEventListener('click', fn);
		return n;
	};
	const errorMessage = error => {
		if (!error) return t('The request could not be completed.', 'uop-core');
		if (error.code === 'uop_unauthenticated') return t('Your login has expired. Sign in again.', 'uop-core');
		if (error.code === 'uop_not_found' || error.code === 'uop_forbidden') return t('Your access may have changed. Refresh the people list.', 'uop-core');
		if (error.code === 'uop_conflict') return t('The record has changed. Reload and try again.', 'uop-core');
		if (error.code === 'uop_invalid_schema' || error.code === 'uop_validation') return t('Review the submitted information and try again.', 'uop-core');
		return t('The server could not complete the request. Please try again.', 'uop-core');
	};

	class Portal {
		constructor(root) {
			this.root = root;
			this.people = [];
			this.selected = '';
			this.rows = [];
			this.next = null;
			this.after = '';
			this.history = [];
			this.busy = false;
			this.seq = 0;
			this.kind = root.getAttribute('data-uop-portal-root');
			this.banner = el('div', 'uop-portal__banner');
			this.banner.setAttribute('aria-live', 'polite');
			this.banner.setAttribute('role', 'status');
			this.layout = el('div', 'uop-portal');
			this.layout.hidden = true;
			this.heading = el('h3', '', t('Choose person', 'uop-core'));
			this.selector = el('select', 'uop-portal__select');
			this.selector.id = 'uop-person-' + String(Portal.nextId++);
			const label = el('label', 'uop-portal__label', t('Person to manage', 'uop-core'));
			label.htmlFor = this.selector.id;
			this.selector.addEventListener('change', () => this.change(this.selector.value));
			this.refresh = button(t('Refresh access', 'uop-core'), () => this.loadSubjects(true));
			this.controls = el('div', 'uop-portal__controls');
			this.controls.append(label, this.selector, this.refresh);
			this.profile = el('section', 'uop-portal__profile');
			this.entries = el('section', 'uop-portal__entries');
			this.layout.append(this.heading, this.controls, this.profile, this.entries);
			this.root.prepend(this.banner, this.layout);
			this.loadSubjects(false);
		}

		setBusy(yes) {
			this.busy = yes;
			this.selector.disabled = yes;
			this.refresh.disabled = yes;
			this.root.querySelectorAll('.uop-portal__button').forEach(n => { n.disabled = yes; });
		}
		message(text, danger) {
			this.banner.textContent = text;
			this.banner.setAttribute('role', danger ? 'alert' : 'status');
			this.banner.classList.toggle('uop-portal__banner--error', Boolean(danger));
		}
		async loadSubjects(keepSelection) {
			const seq = ++this.seq;
			this.setBusy(true);
			this.message(t('Checking your current permissions…', 'uop-core'));
			try {
				const data = await request('/me/portal');
				if (seq !== this.seq) return;
				this.people = Array.isArray(data.items) ? data.items : [];
				const wanted = keepSelection ? this.selected : '';
				this.selected = this.people.some(p => p.public_id === wanted) ? wanted : (this.people[0]?.public_id || '');
				this.selector.replaceChildren();
				this.people.forEach(p => {
					const option = el('option', '', p.display_name);
					option.value = p.public_id;
					this.selector.append(option);
				});
				this.selector.value = this.selected;
				this.history = [];
				this.after = '';
				this.rows = [];
				this.next = null;
				this.layout.hidden = false;
				const fallback = this.root.querySelector('[data-uop-portal-fallback]');
				if (fallback) fallback.hidden = true;
				if (!this.selected) {
					this.profile.replaceChildren();
					this.entries.replaceChildren(el('p', '', t('No linked or delegated persons are available for this account.', 'uop-core')));
					this.message('');
				} else {
					this.drawProfile();
					await this.loadEntries();
				}
			} catch (e) {
				if (seq !== this.seq) return;
				this.people = [];
				this.selected = '';
				this.rows = [];
				this.profile.replaceChildren();
				this.entries.replaceChildren();
				this.message(errorMessage(e), true);
			} finally {
				if (seq === this.seq) this.setBusy(false);
			}
		}
		async change(id) {
			if (!this.people.some(p => p.public_id === id)) return;
			++this.seq;
			this.selected = id;
			this.history = [];
			this.after = '';
			this.next = null;
			this.rows = [];
			this.drawProfile();
			await this.loadEntries();
		}
		drawProfile() {
			this.profile.replaceChildren();
			const person = this.people.find(p => p.public_id === this.selected);
			if (!person) return;
			const title = el('h4', '', person.display_name);
			this.profile.append(title);
			if (this.kind === 'portal' && person.can_edit_name) {
				const form = el('form', 'uop-portal__rename');
				const label = el('label', '', t('Display name', 'uop-core'));
				const input = el('input');
				input.type = 'text';
				input.value = person.display_name;
				input.required = true;
				input.maxLength = 191;
				input.id = 'uop-name-' + String(Portal.nextId++);
				label.htmlFor = input.id;
				form.append(label, input);
				const save = el('button', 'uop-portal__button', t('Save name', 'uop-core'));
				save.type = 'submit';
				form.append(save);
				form.addEventListener('submit', e => {
					e.preventDefault();
					this.rename(person, input.value.trim());
				});
				this.profile.append(form);
			}
			if (!person.can_view_entries) {
				this.profile.append(el('p', '', t('This profile is shared for viewing only. Registration access was not granted.', 'uop-core')));
			}
		}
		async loadEntries() {
			const seq = ++this.seq;
			const person = this.people.find(p => p.public_id === this.selected);
			this.entries.replaceChildren(el('p', '', t('Loading registrations…', 'uop-core')));
			if (!person || !person.can_view_entries) {
				this.entries.replaceChildren();
				this.message('');
				this.setBusy(false);
				return;
			}
			this.setBusy(true);
			this.message(t('Checking registration access…', 'uop-core'));
			try {
				let path = '/me/portal/registrations?person_id=' + encodeURIComponent(this.selected);
				if (this.after) path += '&after=' + encodeURIComponent(this.after);
				const data = await request(path);
				if (seq !== this.seq) return;
				this.rows = Array.isArray(data.items) ? data.items : [];
				this.next = data.next || null;
				this.drawEntries();
				this.message('');
			} catch (e) {
				if (seq !== this.seq) return;
				this.rows = [];
				this.next = null;
				this.entries.replaceChildren(el('p', '', t('Registration access is unavailable.', 'uop-core')));
				this.message(errorMessage(e), true);
			} finally {
				if (seq === this.seq) this.setBusy(false);
			}
		}
		drawEntries() {
			this.entries.replaceChildren(el('h4', '', t('Registrations', 'uop-core')));
			if (!this.rows.length) {
				this.entries.append(el('p', '', t('No registrations for this person.', 'uop-core')));
			} else {
				const list = el('ul', 'uop-portal__list');
				this.rows.forEach(reg => {
					const li = el('li', 'uop-portal__entry');
					const text = el('span', '', t('Status', 'uop-core') + ': ' + reg.status + ' · ' + reg.created_at);
					li.append(text);
					if (reg.can_cancel) {
						li.append(button(t('Cancel registration', 'uop-core'), () => this.cancel(reg), 'destructive'));
					}
					list.append(li);
				});
				this.entries.append(list);
			}
			const pagination = el('nav', 'uop-portal__pagination');
			pagination.setAttribute('aria-label', t('Registration pages', 'uop-core'));
			const previous = button(t('Previous', 'uop-core'), async () => {
				if (!this.history.length) return;
				this.after = this.history.pop();
				await this.loadEntries();
			});
			previous.disabled = !this.history.length;
			const next = button(t('Next', 'uop-core'), async () => {
				if (!this.next) return;
				this.history.push(this.after);
				this.after = this.next;
				await this.loadEntries();
			});
			next.disabled = !this.next;
			pagination.append(previous, el('span', '', t('Page', 'uop-core') + ' ' + (this.history.length + 1)), next);
			this.entries.append(pagination);
		}
		async rename(person, name) {
			if (!name || name.length > 191 || !person.can_edit_name || this.busy) {
				this.message(t('Enter a valid name.', 'uop-core'), true);
				return;
			}
			this.setBusy(true);
			try {
				const fresh = await request('/me/portal');
				if (!fresh.items?.some(p => p.public_id === person.public_id && p.can_edit_name)) {
					this.message(t('Your permission has changed.', 'uop-core'), true);
					await this.loadSubjects(false);
					return;
				}
				await request('/people/' + encodeURIComponent(person.public_id), {
					method: 'PATCH',
					data: { display_name: name, version: person.version }
				});
				await this.loadSubjects(true);
				this.message(t('Name updated.', 'uop-core'));
			} catch (e) {
				this.message(errorMessage(e), true);
			} finally {
				this.setBusy(false);
			}
		}
		async cancel(reg) {
			if (!reg.can_cancel || this.busy) return;
			if (!window.confirm(t('Cancel this registration? A reserved place may be released.', 'uop-core'))) return;
			const command = uuid();
			if (!command) {
				this.message(t('Secure command IDs are unavailable in this browser.', 'uop-core'), true);
				return;
			}
			this.setBusy(true);
			try {
				// Recheck delegation in this session; the existing domain command also
				// reevaluates permissions under the transaction before state changes.
				const subjects = await request('/me/portal');
				if (!subjects.items?.some(p => p.public_id === this.selected && p.can_view_entries)) {
					await this.loadSubjects(false);
					this.message(t('Your access was revoked. No changes were made.', 'uop-core'), true);
					return;
				}
				await request('/registrations/' + encodeURIComponent(reg.public_id) + '/cancel', {
					method: 'POST', data: { command_id: command }
				});
				await this.loadSubjects(true);
				this.message(t('Registration cancelled.', 'uop-core'));
			} catch (e) {
				this.message(errorMessage(e), true);
				if (e.code === 'uop_not_found' || e.code === 'uop_forbidden') await this.loadSubjects(false);
			} finally {
				this.setBusy(false);
			}
		}
	}
	Portal.nextId = 1;
	document.querySelectorAll('[data-uop-portal-root]').forEach(root => new Portal(root));
}());
