/**
 * ACF Field Highlighter
 *
 * Highlights ACF fields that have pending translation updates.
 * Adds a yellow border and warning icon to fields that need re-translation.
 *
 * @package
 */

/**
 * Highlight ACF fields with pending updates
 *
 * @param {string[]} pendingMetaKeys - Array of meta keys with pending updates
 */
export const highlightPendingACFFields = (pendingMetaKeys) => {
	if (!pendingMetaKeys || pendingMetaKeys.length === 0) {
		return;
	}

	// Wait for ACF to render fields (ACF loads dynamically)
	const highlightFields = () => {
		pendingMetaKeys.forEach((metaKey) => {
			// Find ACF field by name attribute
			// ACF fields have format: acf[field_xxxxx] or just field_xxxxx
			const fieldSelectors = [
				`[data-name="${metaKey}"]`, // ACF field wrapper
				`[data-key="${metaKey}"]`, // ACF field wrapper (alternative)
				`[name="acf[${metaKey}]"]`, // ACF input field
				`[name="${metaKey}"]`, // Regular meta field
			];

			fieldSelectors.forEach((selector) => {
				const elements = document.querySelectorAll(selector);

				elements.forEach((element) => {
					// Find the closest ACF field wrapper
					let fieldWrapper = element.closest('.acf-field');

					// If no ACF wrapper, use the element itself
					if (!fieldWrapper) {
						fieldWrapper = element.closest('.postbox') || element;
					}

					// Add pending sync class
					if (fieldWrapper && !fieldWrapper.classList.contains('mlb-field-pending-sync')) {
						fieldWrapper.classList.add('mlb-field-pending-sync');
					}
				});
			});
		});
	};

	// Run immediately
	highlightFields();

	// Run again after a delay (for dynamically loaded fields)
	setTimeout(highlightFields, 500);
	setTimeout(highlightFields, 1000);

	// Watch for DOM changes (ACF repeater fields, flexible content, etc.)
	if (window.MutationObserver) {
		const observer = new MutationObserver(() => {
			highlightFields();
		});

		observer.observe(document.body, {
			childList: true,
			subtree: true,
		});

		// Stop observing after 5 seconds to avoid performance issues
		setTimeout(() => {
			observer.disconnect();
		}, 5000);
	}
};

/**
 * Clear pending highlight from a specific field
 *
 * @param {string} metaKey - Meta key to clear highlight from
 */
export const clearPendingHighlight = (metaKey) => {
	const fieldSelectors = [
		`[data-name="${metaKey}"]`,
		`[data-key="${metaKey}"]`,
		`[name="acf[${metaKey}]"]`,
		`[name="${metaKey}"]`,
	];

	fieldSelectors.forEach((selector) => {
		const elements = document.querySelectorAll(selector);

		elements.forEach((element) => {
			const fieldWrapper = element.closest('.acf-field');

			if (fieldWrapper) {
				fieldWrapper.classList.remove('mlb-field-pending-sync');
			}
		});
	});
};

/**
 * Clear all pending highlights
 */
export const clearAllPendingHighlights = () => {
	const highlightedFields = document.querySelectorAll('.mlb-field-pending-sync');

	highlightedFields.forEach((field) => {
		field.classList.remove('mlb-field-pending-sync');
	});
};
