/* global window, document */
(() => {
	'use strict';
	const root = document.getElementById('uop-guest-verification');
	if (!root) return;
	const state = root.querySelector('[role="status"]');
	const button = root.querySelector('button');
	const fragment = new URLSearchParams(window.location.hash.slice(1));
	const registration = fragment.get('registration_id') || '';
	const token = fragment.get('token') || '';
	// Strip bearer evidence immediately: never send it in a subsequent navigation.
	window.history.replaceState(null, '', window.location.pathname + window.location.search);
	const valid = /^[0-9a-f-]{36}$/.test(registration) && /^[0-9a-f]{64}$/.test(token);
	if (!valid) {
		button.disabled = true;
		state.textContent = root.dataset.invalid;
		return;
	}
	button.addEventListener('click', async () => {
		button.disabled = true;
		state.textContent = root.dataset.processing;
		try {
			await window.fetch(root.dataset.endpoint, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' },
				credentials: 'omit',
				cache: 'no-store',
				referrerPolicy: 'no-referrer',
				body: JSON.stringify({ registration_id: registration, token }),
			});
			state.textContent = root.dataset.received;
		} catch {
			state.textContent = root.dataset.received;
		}
	});
})();
