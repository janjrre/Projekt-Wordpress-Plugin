/* global document, window */
(() => {
	'use strict';
	const root = document.getElementById('uop-waitlist-offer');
	if (!root) return;
	const state = root.querySelector('[role="status"]');
	const button = root.querySelector('button');
	const fragment = new URLSearchParams(window.location.hash.slice(1));
	const offer = fragment.get('offer_id') || '';
	const token = fragment.get('token') || '';
	window.history.replaceState(null, '', window.location.pathname + window.location.search);
	if (!/^[0-9a-f-]{36}$/.test(offer) || !/^[0-9a-f]{64}$/.test(token)) {
		button.disabled = true;
		state.textContent = root.dataset.invalid;
		return;
	}
	button.addEventListener('click', async () => {
		button.disabled = true;
		state.textContent = root.dataset.processing;
		try {
			const command = window.crypto.randomUUID();
			await window.fetch(root.dataset.endpoint, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				credentials: 'omit',
				cache: 'no-store',
				referrerPolicy: 'no-referrer',
				body: JSON.stringify({ offer_id: offer, token, command_id: command }),
			});
		} catch {
			// A generic receipt prevents an offer-state or account enumeration oracle.
		}
		state.textContent = root.dataset.received;
	});
})();
