/**
 * Field Operation Utilities for ACF Translation
 *
 * Handles DOM manipulation and button creation for translatable ACF fields.
 * Provides quick actions for copying original text and opening translation modal.
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
import { loadOriginalValue, updateACFField } from './api';

/**
 * Copy original language value directly to ACF field
 *
 * Provides a quick way for translators to copy the original text
 * into the translation field without using the modal. Useful when:
 * - The text should remain unchanged (e.g., proper nouns, brand names)
 * - Starting a translation from the original as a base
 *
 * @param {string} fieldKey - ACF field key/name
 * @param {number} postId   - ID of the original language post
 * @return {Promise<void>}
 */
export async function copyOriginalToField(fieldKey, postId) {
	try {
		// Load the original value from default language post
		const originalValue = await loadOriginalValue(postId, fieldKey);

		// Only update if we got a non-empty value
		if (originalValue.trim()) {
			updateACFField(fieldKey, originalValue);
		}
	} catch (error) {
		// Silently fail - user will simply see no change if field not found
		// This prevents error messages for edge cases like deleted fields
	}
}

/**
 * Create translation action buttons for ACF fields
 *
 * Creates a button group with two actions:
 * 1. Translation icon (dashicons-translation): Opens the translation modal
 * 2. Paste icon (dashicons-editor-paste-text): Copies original text directly
 *
 * These buttons are injected into ACF field labels for fields marked as translatable
 * by the ACF_Translation PHP class via data attributes.
 *
 * @param {Object}   fieldData            - Field configuration data
 * @param {string}   fieldData.fieldKey   - ACF field key
 * @param {string}   fieldData.fieldLabel - Human-readable field label
 * @param {string}   fieldData.postId     - Original language post ID
 * @param {string}   fieldData.sourceLang - Source language code (e.g., 'en')
 * @param {string}   fieldData.targetLang - Target language code (e.g., 'fr')
 * @param {string}   fieldData.fieldType  - ACF field type (text, textarea, wysiwyg, etc.)
 * @param {Function} onTranslate          - Callback when translation button is clicked
 * @param {Function} onCopy               - Callback when copy button is clicked
 * @return {HTMLElement} Button element ready to append to field label
 */
export function createTranslationButton(fieldData, onTranslate, onCopy) {
	const { fieldKey, fieldLabel, postId, sourceLang, targetLang, fieldType } =
		fieldData;

	// Create button container (span allows inline display next to label)
	const button = document.createElement('span');
	button.className = 'multilingual-bridge-translate-btn';

	// Create translation modal icon
	const translationIcon = document.createElement('span');
	translationIcon.className = 'dashicons dashicons-translation';
	translationIcon.title = __('Translate field', 'multilingual-bridge');
	translationIcon.style.cursor = 'pointer';
	button.appendChild(translationIcon);

	// Create direct copy icon
	const pasteIcon = document.createElement('span');
	pasteIcon.className = 'dashicons dashicons-editor-paste-text';
	pasteIcon.title = __(
		'Copy original text to translation field',
		'multilingual-bridge'
	);
	pasteIcon.style.cursor = 'pointer';
	pasteIcon.style.marginLeft = '5px';
	button.appendChild(pasteIcon);

	// Handle translation modal open
	translationIcon.addEventListener('click', (e) => {
		e.preventDefault();
		onTranslate({
			fieldKey,
			fieldLabel,
			postId: parseInt(postId),
			sourceLang,
			targetLang,
			fieldType,
		});
	});

	// Handle direct copy action
	pasteIcon.addEventListener('click', (e) => {
		e.preventDefault();
		e.stopPropagation(); // Prevent triggering parent click handlers
		onCopy(fieldKey, postId);
	});

	return button;
}
