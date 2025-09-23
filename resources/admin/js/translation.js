/**
 * ACF Translation functionality
 *
 * Handles the translation modal and button creation for ACF fields
 */

/* eslint-disable no-console */

// ACF Translation functionality
document.addEventListener('DOMContentLoaded', function () {
	// Only run on ACF edit screens
	if (typeof acf === 'undefined') {
		return;
	}

	// Function to initialize translation buttons for ACF fields
	function initializeACFTranslationButtons() {
		// Find all ACF fields with translation class
		const translatableFields = document.querySelectorAll(
			'.multilingual-translatable-field'
		);

		translatableFields.forEach(function (fieldWrapper) {
			// Skip if button already exists
			if (
				fieldWrapper.querySelector('.multilingual-bridge-translate-btn')
			) {
				return;
			}

			// Find the field label to append the button
			const labelElement = fieldWrapper.querySelector('.acf-label label');
			if (!labelElement) {
				return;
			}

			const fieldKey = fieldWrapper.getAttribute('data-field-key');
			const fieldLabel = fieldWrapper.getAttribute('data-field-label');
			const postId = fieldWrapper.getAttribute('data-post-id');
			const sourceLang = fieldWrapper.getAttribute('data-source-lang');
			const targetLang = fieldWrapper.getAttribute('data-target-lang');
			const fieldType = fieldWrapper.getAttribute('data-field-type');

			// Create translate button
			const button = document.createElement('button');
			button.type = 'button';
			button.className =
				'multilingual-bridge-translate-btn button button-secondary';
			button.style.marginLeft = '10px';
			button.style.fontSize = '11px';
			button.style.padding = '2px 8px';
			button.textContent = 'Translate';

			// Add click handler
			button.addEventListener('click', function (e) {
				e.preventDefault();

				// Dispatch custom event to open modal
				const event = new CustomEvent(
					'multilingual-bridge:open-translation-modal',
					{
						detail: {
							fieldKey,
							fieldLabel,
							postId: parseInt(postId),
							sourceLang,
							targetLang,
							fieldType,
						},
					}
				);
				document.dispatchEvent(event);
			});

			// Append button to label
			labelElement.appendChild(button);
		});
	}

	// Initialize buttons on page load
	initializeACFTranslationButtons();

	// Re-initialize when ACF fields are loaded (for dynamic fields)
	if (typeof acf !== 'undefined' && acf.addAction) {
		acf.addAction('ready', initializeACFTranslationButtons);
		acf.addAction('append', initializeACFTranslationButtons);
	}

	// Handle translation saving
	document.addEventListener(
		'multilingual-bridge:save-translation',
		function (event) {
			const detail = event.detail;
			const fieldWrapper = document
				.querySelector('[name="' + detail.fieldKey + '"]')
				.closest('.acf-field');

			if (fieldWrapper) {
				const input = fieldWrapper.querySelector(
					'[name="' + detail.fieldKey + '"]'
				);
				if (input) {
					input.value = detail.value;
					// Trigger change event for ACF
					input.dispatchEvent(new Event('change', { bubbles: true }));
					// Also trigger ACF's change event if available
					if (typeof acf !== 'undefined' && acf.trigger) {
						acf.trigger('change', input);
					}
				}
			}
		}
	);
});

// Alpine.js modal functionality
function multilingualBridgeModal() {
	return {
		isOpen: false,
		modalTitle: '',
		sourceLangLabel: '',
		targetLangLabel: '',
		originalValue: '',
		translatedValue: '',
		isLoading: false,
		errorMessage: '',
		fieldKey: '',
		sourceLang: '',
		targetLang: '',

		openModal(data) {
			this.fieldKey = data.fieldKey;
			this.sourceLang = data.sourceLang;
			this.targetLang = data.targetLang;
			this.modalTitle = `Translate ${data.fieldLabel || data.fieldKey}`;
			this.sourceLangLabel = `Original (${data.sourceLang})`;
			this.targetLangLabel = `Translation (${data.targetLang})`;
			this.originalValue = '';
			this.translatedValue = '';
			this.isLoading = false;
			this.errorMessage = '';

			// Load original value
			this.loadOriginalValue(data);
			this.isOpen = true;
		},

		closeModal() {
			this.isOpen = false;
		},

		async loadOriginalValue(data) {
			try {
				this.isLoading = true;
				this.errorMessage = '';

				// Get the current value from the ACF input field
				const fieldWrapper = document.querySelector(
					`[data-field-key="${data.fieldKey}"]`
				);
				if (fieldWrapper) {
					const input = fieldWrapper.querySelector(
						'input, textarea, select'
					);
					if (input) {
						this.originalValue = input.value || '';
					} else {
						this.originalValue = '';
					}
				} else {
					this.originalValue = '';
				}
			} catch (err) {
				console.error(
					'Multilingual Bridge Modal: Error loading original value',
					err
				);
				this.errorMessage = err.message;
			} finally {
				this.isLoading = false;
			}
		},

		async translateText() {
			if (!this.originalValue.trim()) {
				return;
			}

			try {
				this.isLoading = true;
				this.errorMessage = '';

				const translateUrl = `${window.multilingual_bridge?.rest_url || '/wp-json/'}multilingual-bridge/v1/translate`;

				const response = await fetch(translateUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': window.multilingual_bridge?.nonce || '',
					},
					body: JSON.stringify({
						text: this.originalValue,
						target_lang: this.targetLang,
						source_lang: this.sourceLang,
					}),
				});

				if (!response.ok) {
					throw new Error('Translation failed');
				}

				const result = await response.json();
				this.translatedValue = result.translation || '';
			} catch (err) {
				this.errorMessage = err.message;
			} finally {
				this.isLoading = false;
			}
		},

		saveTranslation() {
			// Dispatch event to save the translation
			const event = new CustomEvent(
				'multilingual-bridge:save-translation',
				{
					detail: {
						fieldKey: this.fieldKey,
						value: this.translatedValue,
					},
				}
			);
			document.dispatchEvent(event);
			this.closeModal();
		},
	};
}

// Make the function globally available for Alpine.js
window.multilingualBridgeModal = multilingualBridgeModal;

// Event listener for opening modal
document.addEventListener(
	'multilingual-bridge:open-translation-modal',
	function (event) {
		const modalElement = document.querySelector(
			'[x-data*="multilingualBridgeModal"]'
		);
		if (modalElement && modalElement._x_dataStack) {
			const modal = modalElement._x_dataStack[0];
			if (modal && typeof modal.openModal === 'function') {
				modal.openModal(event.detail);
			}
		}
	}
);
