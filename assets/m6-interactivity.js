import { store, getContext, getElement } from '@wordpress/interactivity';

// Cosmetic, memory-only progressive enhancement. The server remains authoritative
// for published fields, identity, validation, consent evidence and capacity.
const { state } = store('uop/m6', {
	state: {
		get eventHidden() {
			const context = getContext();
			const filter = String(context.filter || '').trim().toLocaleLowerCase();
			return Boolean(filter && !String(context.title || '').includes(filter));
		},
		get eventsPresent() {
			const context = getContext();
			// The full public list remains visible to clients without JavaScript.
			return !context.filter || Array.from(getElement().ref?.parentElement?.querySelectorAll('li') || [])
				.some(node => !node.hidden);
		},
		get disclosureClosed() {
			return !getContext().open;
		},
		get fieldHidden() {
			const context = getContext();
			return !evaluate(context.condition, context.values || {});
		}
	},
	actions: {
		filterEvents() {
			const context = getContext();
			context.filter = getElement().ref?.value || '';
		},
		toggleDisclosure() {
			const context = getContext();
			context.open = !context.open;
		},
		changeField() {
			const context = getContext();
			const input = getElement().ref;
			const key = input?.dataset?.uopField;
			if (!key || !Object.prototype.hasOwnProperty.call(context.values, key)) return;
			let value = input.value;
			if (input.type === 'checkbox') value = input.checked;
			if (input.multiple) value = Array.from(input.selectedOptions, option => option.value);
			if (input.type === 'number') value = value === '' ? null : Number(value);
			context.values[key] = value === '' ? null : value;
		}
	}
});

function evaluate(ast, values, depth = 0) {
	if (!ast) return true;
	if (depth > 8 || typeof ast !== 'object') return false;
	if (Array.isArray(ast.all)) return ast.all.every(child => evaluate(child, values, depth + 1));
	if (Array.isArray(ast.any)) return ast.any.some(child => evaluate(child, values, depth + 1));
	if (ast.not) return !evaluate(ast.not, values, depth + 1);
	if (ast.source !== 'registration' || !Object.prototype.hasOwnProperty.call(values, ast.field)) return false;
	const actual = values[ast.field];
	const target = ast.value;
	switch (ast.operator) {
		case 'exists': return actual !== null && actual !== '' && (!Array.isArray(actual) || actual.length > 0);
		case 'empty': return actual === null || actual === '' || (Array.isArray(actual) && !actual.length);
		case 'eq': return actual !== null && actual === target;
		case 'neq': return actual !== null && actual !== target;
		case 'in': return actual !== null && Array.isArray(target) && target.some(v => v === actual);
		case 'not_in': return actual !== null && Array.isArray(target) && !target.some(v => v === actual);
		case 'lt': case 'lte': case 'gt': case 'gte': {
			if (actual === null || target === null || !isFinite(Number(actual)) || !isFinite(Number(target))) return false;
			if (typeof actual === 'boolean' || typeof target === 'boolean') return false;
			if (ast.operator === 'lt') return Number(actual) < Number(target);
			if (ast.operator === 'lte') return Number(actual) <= Number(target);
			if (ast.operator === 'gt') return Number(actual) > Number(target);
			return Number(actual) >= Number(target);
		}
		case 'date_before': case 'date_after': {
			const date = s => typeof s === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(s) && !Number.isNaN(Date.parse(s)) && new Date(s).toISOString().slice(0, 10) === s;
			if (!date(actual) || !date(target)) return false;
			return ast.operator === 'date_before' ? actual < target : actual > target;
		}
		default: return false;
	}
}

// State is local to its corresponding uop/m6 block context. No server writes.
void state;
