(function () {
	'use strict';
	var registry = window.wc && window.wc.wcBlocksRegistry;
	var settings = window.wc && window.wc.wcSettings;
	var element = window.wp && window.wp.element;
	var entities = window.wp && window.wp.htmlEntities;
	var sanitizer = window.wc && window.wc.sanitize;
	var gateways = window.BCK_BLOCK_GATEWAYS || [];
	if (!registry || !settings || !element || !gateways.length) return;

	gateways.forEach(function (gateway) {
		var data = settings.getPaymentMethodData(gateway, {});
		var title = entities && entities.decodeEntities ? entities.decodeEntities(data.title || gateway) : (data.title || gateway);
		var description = sanitizer && sanitizer.sanitizeHTML ? sanitizer.sanitizeHTML(data.description || '') : '';
		var content = element.createElement('div', { dangerouslySetInnerHTML: { __html: description } });
		var Label = function (props) {
			var icon = data.icon ? element.createElement('img', { src: data.icon, alt: '', className: 'wc-block-components-payment-method-icon' }) : undefined;
			return element.createElement(props.components.PaymentMethodLabel, { text: title, icon: icon });
		};
		registry.registerPaymentMethod({
			name: gateway,
			label: element.createElement(Label),
			content: content,
			edit: content,
			canMakePayment: function () { return true; },
			ariaLabel: title,
			supports: { features: data.supports || ['products'] }
		});
	});
}());
