/**
 * All of the code for your admin JavaScript source
 * should reside in this file.
 *
 * @package
 */

// Language Debug page functionality
document.addEventListener('DOMContentLoaded', function () {
	// Only run on Language Debug page
	const debugForm = document.querySelector(
		'form[action="admin-post.php"] input[value="language_debug"]'
	);
	if (!debugForm) {
		return;
	}

	const debugAction = document.getElementById('debug_action');
	const targetLanguageField = document.getElementById('target_language');
	const targetLanguageLabel = targetLanguageField
		? targetLanguageField.closest('p').previousElementSibling
		: null;

	// Function to toggle target language field visibility
	function toggleTargetLanguage() {
		if (!debugAction || !targetLanguageField || !targetLanguageLabel) {
			return;
		}

		const showTargetLanguage = debugAction.value === 'fix_language';

		targetLanguageField.style.display = showTargetLanguage
			? 'block'
			: 'none';
		targetLanguageLabel.style.display = showTargetLanguage
			? 'block'
			: 'none';

		// Update required attribute
		targetLanguageField.required = showTargetLanguage;
	}

	// Initial toggle
	toggleTargetLanguage();

	// Toggle on change
	if (debugAction) {
		debugAction.addEventListener('change', toggleTargetLanguage);
	}

	// Add confirmation for destructive actions
	const form = debugForm.closest('form');
	if (form) {
		form.addEventListener('submit', function (e) {
			const action = debugAction ? debugAction.value : '';

			if (action === 'delete') {
				// eslint-disable-next-line no-alert
				const confirmDelete = confirm(
					'Are you sure you want to delete all posts in unconfigured languages? This action cannot be undone.'
				);
				if (!confirmDelete) {
					e.preventDefault();
				}
			} else if (action === 'fix_language') {
				// eslint-disable-next-line no-alert
				const confirmFix = confirm(
					'Are you sure you want to change the language assignment for all posts in unconfigured languages?'
				);
				if (!confirmFix) {
					e.preventDefault();
				}
			}
		});
	}

	// Multi-select helper text
	const multiSelect = document.getElementById('debug_post_type');
	if (multiSelect) {
		multiSelect.addEventListener('change', function () {
			const selectedCount = this.selectedOptions.length;
			const helper = this.nextElementSibling;

			if (selectedCount > 1 && helper && helper.tagName === 'SMALL') {
				helper.textContent = `${selectedCount} post types selected. Hold Ctrl/Cmd to select more.`;
			}
		});
	}
});
