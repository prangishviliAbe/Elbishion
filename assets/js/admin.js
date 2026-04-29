(function () {
	'use strict';

	var confirmMessage = '\u10d3\u10d0\u10e0\u10ec\u10db\u10e3\u10dc\u10d4\u10d1\u10e3\u10da\u10d8 \u10ee\u10d0\u10e0\u10d7, \u10e0\u10dd\u10db \u10d2\u10d0\u10d2\u10e0\u10eb\u10d4\u10da\u10d4\u10d1\u10d0 \u10d2\u10e1\u10e3\u10e0\u10d7?';

	document.addEventListener('click', function (event) {
		var link = event.target.closest('.elbishion-confirm-action');

		if (!link) {
			return;
		}

		if (!window.confirm(confirmMessage)) {
			event.preventDefault();
		}
	});
}());
