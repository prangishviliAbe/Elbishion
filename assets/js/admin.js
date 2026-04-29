(function () {
	'use strict';

	document.addEventListener('click', function (event) {
		var link = event.target.closest('.elbishion-confirm-action');

		if (!link) {
			return;
		}

		if (!window.confirm('დარწმუნებული ხართ, რომ გაგრძელება გსურთ?')) {
			event.preventDefault();
		}
	});
}());
