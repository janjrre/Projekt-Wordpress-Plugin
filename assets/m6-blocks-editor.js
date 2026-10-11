/* global wp */
(function () {
	'use strict';
	const h = wp.element.createElement;
	const { registerBlockType } = wp.blocks;
	const { useBlockProps, InspectorControls } = wp.blockEditor;
	const { PanelBody, TextControl, RangeControl, Notice } = wp.components;
	const t = wp.i18n.__;
	const names = [
		['event-list', 'Event List', 'List public events'],
		['event-details', 'Event Details', 'Display one public event'],
		['registration-form', 'Registration Form', 'Show published registration field requirements'],
		['portal', 'Participant Portal', 'Participant gateway'],
		['my-registrations', 'My Registrations', 'View authorized registrations']
	];
	names.forEach(([slug, title, description]) => {
		registerBlockType('uop/' + slug, {
			title: t(title, 'uop-core'),
			edit: function Editor({ attributes, setAttributes }) {
				const wrapper = useBlockProps({ className: 'uop-m6-blocks-editor' });
				const eventSelector = (slug === 'event-details' || slug === 'registration-form');
				const sidebar = eventSelector ? h(InspectorControls, null,
					h(PanelBody, { title: t('Event settings', 'uop-core'), initialOpen: true },
						h(TextControl, {
							label: t('Public event UUID', 'uop-core'),
							help: t('Find the event ID in UOP event administration. Private events never appear on the site.', 'uop-core'),
							value: attributes.eventId || '',
							onChange: value => setAttributes({ eventId: value })
						})
					)
				) : slug === 'event-list' ? h(InspectorControls, null,
					h(PanelBody, { title: t('List settings', 'uop-core'), initialOpen: true },
						h(RangeControl, {
							label: t('Maximum events', 'uop-core'), min: 1, max: 12,
							value: attributes.limit || 6, onChange: value => setAttributes({ limit: value })
						})
					)
				) : null;
				return h('div', wrapper, sidebar,
					h('strong', null, t(title, 'uop-core')),
					h('p', null, t(description, 'uop-core')),
					eventSelector && !attributes.eventId ? h(Notice, { status: 'warning', isDismissible: false },
						t('Select a public event UUID in the block settings.', 'uop-core')
					) : null,
					eventSelector && attributes.eventId ? h('code', null, attributes.eventId) : null,
					slug === 'registration-form' ? h('p', null, t('Public fields are server-rendered. Submission remains disabled until the secure portal is connected.', 'uop-core')) : null,
					slug === 'portal' || slug === 'my-registrations' ? h('p', null, t('Visitor-specific content renders on the server and is not included in the editor preview.', 'uop-core')) : null
				);
			},
			save: function () { return null; }
		});
	});
}());