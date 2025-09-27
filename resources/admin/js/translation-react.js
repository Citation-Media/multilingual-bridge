/**
 * React-based ACF Translation functionality
 *
 * Handles the translation modal and button creation for ACF fields using WordPress React standards
 */

import { createElement, useState, useEffect, useCallback } from '@wordpress/element';
import { createRoot } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

// Translation Modal Component
const TranslationModal = ({ isOpen, onClose, modalData }) => {
	const [originalValue, setOriginalValue] = useState('');
	const [translatedValue, setTranslatedValue] = useState('');
	const [isLoading, setIsLoading] = useState(false);
	const [errorMessage, setErrorMessage] = useState('');

	const loadOriginalValue = useCallback(async () => {
		if (!modalData) return;
		
		try {
			setIsLoading(true);
			setErrorMessage('');

			// Extract field key from ACF field name (remove acf[] wrapper if present)
			let fieldKey = modalData.fieldKey;
			const acfMatch = modalData.fieldKey.match(/^acf\[([^\]]+)\]$/);
			if (acfMatch) {
				fieldKey = acfMatch[1];
			}

			const response = await apiFetch({
				path: `/multilingual-bridge/v1/meta/${modalData.postId}/${fieldKey}`,
				method: 'GET',
			});

			setOriginalValue(response.value || '');
		} catch (error) {
			// eslint-disable-next-line no-console
			console.error('Error loading original value:', error);
			setErrorMessage(error.message || __('Error loading original value', 'multilingual-bridge'));
		} finally {
			setIsLoading(false);
		}
	}, [modalData]);

	// Load original value when modal opens
	useEffect(() => {
		if (isOpen && modalData) {
			loadOriginalValue();
		}
	}, [isOpen, modalData, loadOriginalValue]);

	const translateText = async () => {
		if (!originalValue.trim()) {
			return;
		}

		try {
			setIsLoading(true);
			setErrorMessage('');

			const response = await apiFetch({
				path: '/multilingual-bridge/v1/translate',
				method: 'POST',
				data: {
					text: originalValue,
					target_lang: modalData.targetLang,
					source_lang: modalData.sourceLang,
				},
			});

			setTranslatedValue(response.translation || '');
		} catch (error) {
			setErrorMessage(
				error.message || __('Translation failed', 'multilingual-bridge')
			);
		} finally {
			setIsLoading(false);
		}
	};

	const saveTranslation = () => {
		// Dispatch event to save the translation
		const event = new CustomEvent('multilingual-bridge:save-translation', {
			detail: {
				fieldKey: modalData.fieldKey,
				value: translatedValue,
			},
		});
		document.dispatchEvent(event);
		onClose();
	};

	if (!isOpen || !modalData) {
		return null;
	}

	const modalTitle =
		__('Translate', 'multilingual-bridge') +
		' ' +
		(modalData.fieldLabel || modalData.fieldKey);
	const sourceLangLabel =
		__('Original', 'multilingual-bridge') +
		' (' +
		modalData.sourceLang +
		')';
	const targetLangLabel =
		__('Translation', 'multilingual-bridge') +
		' (' +
		modalData.targetLang +
		')';

	return createElement(
		'div',
		{
			className: 'multilingual-bridge-modal-backdrop',
			onClick: onClose,
		},
		createElement(
			'div',
			{
				className: 'multilingual-bridge-modal',
				onClick: (e) => e.stopPropagation(),
			},
			createElement(
				'div',
				{ className: 'multilingual-bridge-modal-header' },
				createElement('h2', null, modalTitle),
				createElement(
					'button',
					{
						onClick: onClose,
						'aria-label': __('Close modal', 'multilingual-bridge'),
					},
					'×'
				)
			),
			createElement(
				'div',
				{ className: 'multilingual-bridge-modal-body' },
				createElement(
					'div',
					{ className: 'multilingual-bridge-modal-columns' },
					createElement(
						'div',
						{ className: 'multilingual-bridge-modal-column' },
						createElement('h3', null, sourceLangLabel),
						createElement('textarea', {
							value: originalValue,
							onChange: (e) => setOriginalValue(e.target.value),
							disabled: isLoading,
						})
					),
					createElement(
						'div',
						{ className: 'multilingual-bridge-modal-column' },
						createElement('h3', null, targetLangLabel),
						createElement('textarea', {
							value: translatedValue,
							onChange: (e) => setTranslatedValue(e.target.value),
							disabled: isLoading,
						})
					)
				),
				errorMessage &&
					createElement(
						'div',
						{ className: 'multilingual-bridge-modal-error' },
						errorMessage
					),
				createElement(
					'div',
					{ className: 'multilingual-bridge-modal-actions' },
					createElement(
						'button',
						{
							className: 'button button-secondary',
							onClick: translateText,
							disabled: isLoading || !originalValue.trim(),
							style: {
								opacity:
									isLoading || !originalValue.trim()
										? 0.6
										: 1,
								cursor:
									isLoading || !originalValue.trim()
										? 'not-allowed'
										: 'pointer',
							},
						},
						__('Translate', 'multilingual-bridge')
					),
					createElement(
						'button',
						{
							className: 'button button-primary',
							onClick: saveTranslation,
							disabled: !translatedValue.trim(),
						},
						__('Use Translation', 'multilingual-bridge')
					)
				)
			)
		)
	);
};

// Main App Component
const TranslationApp = () => {
	const [isModalOpen, setIsModalOpen] = useState(false);
	const [modalData, setModalData] = useState(null);

	useEffect(() => {
		// Event listener for opening modal
		const handleOpenModal = (event) => {
			setModalData(event.detail);
			setIsModalOpen(true);
		};

		document.addEventListener(
			'multilingual-bridge:open-translation-modal',
			handleOpenModal
		);

		return () => {
			document.removeEventListener(
				'multilingual-bridge:open-translation-modal',
				handleOpenModal
			);
		};
	}, []);

	const closeModal = () => {
		setIsModalOpen(false);
		setModalData(null);
	};

	return createElement(TranslationModal, {
		isOpen: isModalOpen,
		onClose: closeModal,
		modalData,
	});
};

// Function to copy original value to ACF field
async function copyOriginalToField(fieldKey, postId) {
	try {
		// Extract field key from ACF field name (remove acf[] wrapper if present)
		let cleanFieldKey = fieldKey;
		const acfMatch = fieldKey.match(/^acf\[([^\]]+)\]$/);
		if (acfMatch) {
			cleanFieldKey = acfMatch[1];
		}

		const response = await apiFetch({
			path: `/multilingual-bridge/v1/meta/${postId}/${cleanFieldKey}`,
			method: 'GET',
		});

		const originalValue = response.value || '';

		if (originalValue.trim()) {
			// Find the ACF input field and set the value
			const input = document.querySelector(`[name="${fieldKey}"]`);

			if (input) {
				input.value = originalValue;
				// Trigger change event for ACF
				input.dispatchEvent(new Event('change', { bubbles: true }));
				// Also trigger ACF's change event if available
				if (typeof acf !== 'undefined' && acf.trigger) {
					acf.trigger('change', input);
				}
			}
		}
	} catch (err) {
		// eslint-disable-next-line no-console
		console.error('Multilingual Bridge: Error copying original value', err);
	}
}

// Initialize the React app
document.addEventListener('DOMContentLoaded', function () {
	// Only run on ACF edit screens
	if (typeof acf === 'undefined') {
		return;
	}

	// Create container for React app
	const modalContainer = document.createElement('div');
	modalContainer.id = 'multilingual-bridge-react-modal';
	document.body.appendChild(modalContainer);

	// Render React app
	const root = createRoot(modalContainer);
	root.render(createElement(TranslationApp));

	// Function to initialize translation buttons for ACF fields
	function initializeACFTranslationButtons() {
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

			// Create translate button container
			const button = document.createElement('span');
			button.className = 'multilingual-bridge-translate-btn';

			// Add translation icon
			const translationIcon = document.createElement('span');
			translationIcon.className = 'dashicons dashicons-translation';
			button.appendChild(translationIcon);

			// Add paste text icon
			const pasteIcon = document.createElement('span');
			pasteIcon.className = 'dashicons dashicons-editor-paste-text';
			pasteIcon.title = __(
				'Copy original text to translation field',
				'multilingual-bridge'
			);
			pasteIcon.style.cursor = 'pointer';
			pasteIcon.style.marginLeft = '5px';

			// Add click handler for paste icon
			pasteIcon.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				copyOriginalToField(fieldKey, postId);
			});

			button.appendChild(pasteIcon);

			// Add click handler for translation icon
			translationIcon.addEventListener('click', function (e) {
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
			const input = document.querySelector(
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
	);
});