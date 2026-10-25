(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var form = document.getElementById('servsys-customer-form');
		if (!form || form.getAttribute('data-existing-customer') !== '1') {
			return;
		}

		var dependentForms = document.querySelectorAll('.servsys-dependent-form');
		var notice = document.getElementById('servsys-save-first-notice');
		var dirty = false;

		function setDirty() {
			if (dirty) {
				return;
			}
			dirty = true;
			dependentForms.forEach(function (dependentForm) {
				dependentForm.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (button) {
					button.disabled = true;
				});
			});
			if (notice) {
				notice.hidden = false;
			}
		}

		form.querySelectorAll('input, textarea, select').forEach(function (field) {
			if (field.type !== 'hidden' && field.type !== 'submit') {
				field.addEventListener('input', setDirty);
				field.addEventListener('change', setDirty);
			}
		});
	});
}());
