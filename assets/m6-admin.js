/* global wp, window, document */
(function () {
	'use strict';
	const h = wp.element.createElement;
	const { useState, useEffect, useRef } = wp.element;
	const api = wp.apiFetch;
	const t = wp.i18n.__;
	const config = window.uopM6Admin || {};
	api.use(api.createNonceMiddleware(config.nonce || ''));
	const root = document.getElementById('uop-m6-admin-root');
	if (!root) return;
	const resource = root.dataset.resource === 'registrations' ? 'registrations' : 'people';
	const states = ['', 'submitted', 'review', 'accepted', 'waitlisted', 'offered', 'rejected', 'cancelled'];
	const uuid = () => window.crypto && window.crypto.randomUUID ? window.crypto.randomUUID() : null;
	const key = (path, options) => api({ path: '/uop/v1' + path, ...options });
	const description = (error) => {
		if (!error) return t('The request could not be completed.', 'uop-core');
		const code = error.code || '';
		if (code === 'uop_unauthenticated') return t('Your login has expired. Sign in again and retry.', 'uop-core');
		if (code === 'uop_forbidden') return t('You do not have permission for this action.', 'uop-core');
		if (code === 'uop_not_found') return t('The record is unavailable or you do not have access to it.', 'uop-core');
		if (code === 'uop_conflict') return t('The record changed since it was loaded. Refresh before trying again.', 'uop-core');
		if (code === 'uop_invalid_schema' || code === 'uop_validation') return t('Check the entered values and try again.', 'uop-core');
		return t('The server could not complete this request. Try again.', 'uop-core');
	};
	const name = record => record.display_name || record.person_name || record.public_id;
	function App() {
		const [items, setItems] = useState([]);
		const [after, setAfter] = useState('');
		const [history, setHistory] = useState([]);
		const [next, setNext] = useState('');
		const [status, setStatus] = useState('');
		const [listState, setListState] = useState('loading');
		const [detailState, setDetailState] = useState('idle');
		const [selected, setSelected] = useState('');
		const [detail, setDetail] = useState(null);
		const [editName, setEditName] = useState('');
		const [buckets, setBuckets] = useState([]);
		const [bucket, setBucket] = useState('');
		const [busy, setBusy] = useState(false);
		const [error, setError] = useState('');
		const [notice, setNotice] = useState('');
		const alertRef = useRef(null);
		const listSeq = useRef(0);
		const detailSeq = useRef(0);

		function listUrl(cursor, filter) {
			const params = [];
			if (cursor) params.push('after=' + encodeURIComponent(cursor));
			if (resource === 'registrations' && filter) params.push('status=' + encodeURIComponent(filter));
			return '/admin/' + resource + (params.length ? '?' + params.join('&') : '');
		}
		async function loadList(cursor, filter) {
			const seq = ++listSeq.current;
			setListState('loading');
			setError('');
			try {
				const data = await key(listUrl(cursor, filter));
				if (seq !== listSeq.current) return;
				setItems(data.items || []);
				setNext(data.next || '');
				setListState((data.items || []).length ? 'ready' : 'empty');
			} catch (e) {
				if (seq !== listSeq.current) return;
				setItems([]);
				setNext('');
				setListState(e.code === 'uop_forbidden' || e.code === 'uop_unauthenticated' ? 'denied' : e.code === 'uop_not_found' ? 'missing' : 'failure');
				setError(description(e));
			}
		}
		useEffect(() => { loadList(after, status); return () => { ++listSeq.current; }; }, [after, status]);
		useEffect(() => { if (error && alertRef.current) alertRef.current.focus(); }, [error]);
		async function loadDetail(id) {
			const seq = ++detailSeq.current;
			setSelected(id);
			setDetail(null);
			setBuckets([]);
			setBucket('');
			setDetailState('loading');
			setError('');
			setNotice('');
			try {
				const data = await key(resource === 'people' ? '/people/' + id : '/registrations/' + id);
				if (seq !== detailSeq.current) return;
				setDetail(data);
				setEditName(data.display_name || '');
				setDetailState('ready');
				if (resource === 'registrations' && data.event_id && (data.status === 'submitted' || data.status === 'review')) {
					try {
						const seats = await key('/events/' + data.event_id + '/capacity');
						if (seq !== detailSeq.current) return;
						const available = seats.buckets || [];
						setBuckets(available);
						setBucket(available.length ? available[0].public_id : '');
					} catch (_) { /* No capacity-management permission; regular detail still works. */ }
				}
			} catch (e) {
				if (seq !== detailSeq.current) return;
				setDetailState(e.code === 'uop_not_found' ? 'missing' : e.code === 'uop_forbidden' ? 'denied' : 'failure');
				setError(description(e));
			}
		}
		async function run(path, method, data, success) {
			if (busy) return;
			setBusy(true); setError(''); setNotice('');
			try {
				await key(path, { method, data });
				await Promise.all([loadDetail(selected), loadList(after, status)]);
				setNotice(success);
			} catch (e) {
				setError(description(e));
				if (e.code === 'uop_conflict') setDetailState('conflict');
				else if (e.code === 'uop_invalid_schema' || e.code === 'uop_validation') setDetailState('validation');
			} finally { setBusy(false); }
		}
		function nextPage() {
			if (!next) return;
			setHistory(previous => previous.concat([after]));
			setAfter(next);
			setSelected(''); setDetail(null); setDetailState('idle');
		}
		function previousPage() {
			if (!history.length) return;
			const previous = history[history.length - 1];
			setHistory(history.slice(0, -1));
			setAfter(previous);
			setSelected(''); setDetail(null); setDetailState('idle');
		}
		function changeFilter(e) {
			setStatus(e.target.value); setHistory([]); setAfter('');
			setSelected(''); setDetail(null); setDetailState('idle');
		}
		const selectedItem = items.find(item => item.public_id === selected);
		function savePerson(event) {
			event.preventDefault();
			if (!detail || !selectedItem || !selectedItem.can_edit || !editName.trim() || editName.length > 191) {
				setError(t('Enter a valid name before saving.', 'uop-core'));
				return;
			}
			run('/people/' + detail.public_id, 'PATCH', { display_name: editName.trim(), version: detail.version }, t('Name saved.', 'uop-core'));
		}
		function registrationAction(action, extra, prompt) {
			if (!detail) return;
			if (!window.confirm(prompt)) return;
			const command = uuid();
			if (!command) { setError(t('Secure command identifiers are unavailable in this browser.', 'uop-core')); return; }
			const path = action === 'allocation' ? '/registrations/' + detail.public_id + '/allocation' :
				action === 'cancel' ? '/registrations/' + detail.public_id + '/cancel' :
				'/registrations/' + detail.public_id + '/transitions';
			run(path, 'POST', { command_id: command, ...extra },
				t('Registration updated. The current state has been reloaded.', 'uop-core'));
		}
		function list() {
			const toolbar = resource === 'registrations' ? h('label', { key: 'status' },
				t('Status', 'uop-core'),
				h('select', { value: status, onChange: changeFilter, disabled: listState === 'loading' || busy },
					states.map(state => h('option', { key: state, value: state }, state || t('All statuses', 'uop-core')))
				)
			) : null;
			const children = items.map(item => h('li', { key: item.public_id },
				h('button', {
					type: 'button', onClick: () => loadDetail(item.public_id), 'aria-current': selected === item.public_id ? 'true' : undefined,
					disabled: busy
				},
					h('strong', null, name(item)),
					h('span', { className: 'uop-m6-note' }, ' · ' + (item.status || '')),
					h('div', { className: 'uop-m6-id' }, item.public_id)
				)
			));
			return h('section', { className: 'uop-m6-panel', 'aria-label': t('Record list', 'uop-core') },
				h('h2', null, resource === 'people' ? t('People', 'uop-core') : t('Registrations', 'uop-core')),
				h('div', { className: 'uop-m6-toolbar' }, toolbar,
					h('button', { className: 'button', type: 'button', onClick: () => loadList(after, status), disabled: busy || listState === 'loading' }, t('Refresh list', 'uop-core'))
				),
				listState === 'loading' ? h('p', { role: 'status' }, t('Loading records…', 'uop-core')) : null,
				listState === 'empty' ? h('p', { role: 'status' }, t('No records found in this view.', 'uop-core')) : null,
				listState === 'denied' ? h('p', { role: 'alert' }, t('You cannot access this list.', 'uop-core')) : null,
				listState === 'missing' ? h('p', { role: 'alert' }, t('This page is no longer available. Return to the first page.', 'uop-core')) : null,
				listState === 'failure' ? h('p', { role: 'alert' }, t('Failed to load records. You can retry.', 'uop-core')) : null,
				listState === 'ready' ? h('ul', { className: 'uop-m6-items' }, children) : null,
				h('nav', { className: 'uop-m6-page', 'aria-label': t('Pagination', 'uop-core') },
					h('button', { className: 'button', type: 'button', disabled: !history.length || busy || listState === 'loading', onClick: previousPage }, t('Previous', 'uop-core')),
					h('span', null, t('Page', 'uop-core') + ' ' + (history.length + 1)),
					h('button', { className: 'button', type: 'button', disabled: !next || busy || listState === 'loading', onClick: nextPage }, t('Next', 'uop-core'))
				)
			);
		}
		function personDetail() {
			return h('div', { className: 'uop-m6-detail' },
				h('dl', null,
					h('dt', null, t('Person ID', 'uop-core')), h('dd', { className: 'uop-m6-id' }, detail.public_id),
					h('dt', null, t('Status', 'uop-core')), h('dd', null, h('span', { className: 'uop-m6-status' }, detail.status)),
					h('dt', null, t('Version', 'uop-core')), h('dd', null, detail.version),
					h('dt', null, t('Email', 'uop-core')), h('dd', null, detail.primary_email || t('Not available to this account', 'uop-core'))
				),
				selectedItem && selectedItem.can_edit ? h('form', { onSubmit: savePerson },
					h('label', { className: 'uop-m6-field', htmlFor: 'uop-m6-display-name' }, t('Display name', 'uop-core')),
					h('input', { id: 'uop-m6-display-name', type: 'text', required: true, maxLength: 191, value: editName, disabled: busy, onChange: e => setEditName(e.target.value) }),
					h('p', { className: 'uop-m6-note' }, t('Saving uses the loaded version. A concurrent edit must be reloaded.', 'uop-core')),
					h('button', { type: 'submit', className: 'button button-primary', disabled: busy || !editName.trim() || editName === detail.display_name }, t('Save name', 'uop-core'))
				) : h('p', null, h('strong', null, detail.display_name), ' · ', t('Read-only', 'uop-core'))
			);
		}
		function registrationDetail() {
			const reviewable = detail.status === 'submitted';
			const rejectable = detail.status === 'submitted' || detail.status === 'review';
			const cancelable = ['submitted', 'review', 'accepted', 'waitlisted', 'offered'].includes(detail.status);
			const allocatable = rejectable && buckets.length > 0;
			return h('div', { className: 'uop-m6-detail' },
				h('dl', null,
					h('dt', null, t('Registration', 'uop-core')), h('dd', { className: 'uop-m6-id' }, detail.public_id),
					h('dt', null, t('Person', 'uop-core')), h('dd', null, selectedItem && selectedItem.person_name ? selectedItem.person_name : detail.person_id),
					h('dt', null, t('Event', 'uop-core')), h('dd', { className: 'uop-m6-id' }, detail.event_id || t('Not available', 'uop-core')),
					h('dt', null, t('Status', 'uop-core')), h('dd', null, h('span', { className: 'uop-m6-status' }, detail.status)),
					h('dt', null, t('Created', 'uop-core')), h('dd', null, detail.created_at),
					h('dt', null, t('Version', 'uop-core')), h('dd', null, detail.version)
				),
				h('div', { className: 'uop-m6-actions' },
					reviewable ? h('button', { type: 'button', className: 'button', disabled: busy, onClick: () => registrationAction('transition', { target: 'review' }, t('Move this registration to review?', 'uop-core')) }, t('Mark for review', 'uop-core')) : null,
					rejectable ? h('button', { type: 'button', className: 'button', disabled: busy, onClick: () => registrationAction('transition', { target: 'rejected' }, t('Reject this registration?', 'uop-core')) }, t('Reject', 'uop-core')) : null,
					cancelable ? h('button', { type: 'button', className: 'button', disabled: busy, onClick: () => registrationAction('cancel', {}, t('Cancel this registration? This may release a reserved place.', 'uop-core')) }, t('Cancel registration', 'uop-core')) : null
				),
				allocatable ? h('div', { className: 'uop-m6-actions' },
					h('label', { className: 'uop-m6-field', htmlFor: 'uop-m6-bucket' }, t('Place allocation bucket', 'uop-core'),
						h('select', { id: 'uop-m6-bucket', value: bucket, disabled: busy, onChange: e => setBucket(e.target.value) },
							buckets.map(b => h('option', { key: b.public_id, value: b.public_id }, b.label + ' (' + b.occupied + '/' + b.capacity + ')'))
						)
					),
					h('button', { type: 'button', className: 'button button-primary', disabled: busy || !bucket, onClick: () => registrationAction('allocation', { bucket_id: bucket }, t('Apply the capacity rules and decide allocation? This may place the person on the waitlist.', 'uop-core')) }, t('Decide allocation', 'uop-core'))
				) : null,
				h('p', { className: 'uop-m6-note' }, t('Allocation and state changes are checked by the server and audited. Only authorized actions succeed.', 'uop-core'))
			);
		}
		function detailView() {
			return h('section', { className: 'uop-m6-panel', 'aria-label': t('Record details', 'uop-core') },
				h('h2', null, t('Details', 'uop-core')),
				!selected ? h('p', { className: 'uop-m6-note' }, t('Select an item to view its details.', 'uop-core')) : null,
				detailState === 'loading' ? h('p', { role: 'status' }, t('Loading details…', 'uop-core')) : null,
				detailState === 'missing' ? h('p', { role: 'alert' }, t('Record not found or no longer accessible.', 'uop-core')) : null,
				detailState === 'denied' ? h('p', { role: 'alert' }, t('You are not authorized to view this record.', 'uop-core')) : null,
				detailState === 'failure' ? h('p', { role: 'alert' }, t('Unable to load record details.', 'uop-core')) : null,
				detailState === 'conflict' ? h('p', { role: 'alert' }, t('The record has changed. Reload it before updating.', 'uop-core')) : null,
				detailState === 'validation' ? h('p', { role: 'alert' }, t('Review the entered values and try again.', 'uop-core')) : null,
				detailState === 'ready' && detail ? resource === 'people' ? personDetail() : registrationDetail() : null,
				selected ? h('div', { className: 'uop-m6-actions' }, h('button', { type: 'button', className: 'button', onClick: () => loadDetail(selected), disabled: busy || detailState === 'loading' }, t('Reload details', 'uop-core'))) : null
			);
		}
		return h('div', null,
			error ? h('div', { className: 'uop-m6-alert', role: 'alert', tabIndex: -1, ref: alertRef }, error) : null,
			notice ? h('p', { className: 'uop-m6-notice', role: 'status' }, notice) : null,
			h('div', { className: 'uop-m6-shell' }, list(), detailView())
		);
	}
	wp.element.createRoot(root).render(h(App));
}());