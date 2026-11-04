const loader = document.querySelector('[data-page-loader]');
if (loader) {
	const show = () => {
		loader.hidden = false;
		loader.setAttribute('aria-hidden', 'false');
	};
	const hide = () => {
		loader.hidden = true;
		loader.setAttribute('aria-hidden', 'true');
	};

	if (document.readyState === 'complete') {
		hide();
	} else {
		show();
		window.addEventListener('load', hide, { once: true });
	}

	window.addEventListener('pageshow', (event) => {
		if (event.persisted) hide();
	});

	document.addEventListener('click', (event) => {
		const target = event.target;
		if (!(target instanceof Element)) return;
		const link = target.closest('a[href]');
		if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
		if (link.target && link.target !== '_self') return;
		if (link.hasAttribute('download')) return;
		if (link.hasAttribute('data-page-loader-skip')) return;
		if (link.getAttribute('href')?.includes('/export')) return;

		const destination = new URL(link.href, window.location.href);
		if (destination.origin !== window.location.origin) return;
		if (destination.pathname === window.location.pathname && destination.search === window.location.search && destination.hash) return;

		window.setTimeout(() => {
			if (!event.defaultPrevented) show();
		}, 0);
	});

	document.addEventListener('submit', (event) => {
		const form = event.target;
		if (!(form instanceof HTMLFormElement) || (form.target && form.target !== '_self')) return;
		window.setTimeout(() => {
			if (!event.defaultPrevented) show();
		}, 0);
	});
}
