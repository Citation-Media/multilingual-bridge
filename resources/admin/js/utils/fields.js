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
		const originalValue = await loadOriginalValue(postId, fieldKey);

		if (originalValue.trim()) {
			updateACFField(fieldKey, originalValue);
		}
	} catch (error) {}
}

/**
 * Update field value based on field type
 *
 * @param {string} fieldName - Field name
 * @param {string} value     - Value to set
 * @param {string} fieldType - Field type (text, textarea, wysiwyg, lexical-editor, meta)
 */
export function updateFieldValue(fieldName, value, fieldType) {
	if (fieldType === 'wysiwyg') {
		const iframeId = `acf[${fieldName}]_ifr`;
		const iframe = document.getElementById(iframeId);

		if (iframe && iframe.contentWindow) {
			const doc = iframe.contentWindow.document;
			if (doc.body) {
				doc.body.innerHTML = value;
			}
		}
	} else if (fieldType === 'lexical-editor') {
		updateACFField(`acf[${fieldName}]`, value);
	} else if (fieldType === 'meta') {
		// Native WordPress meta field
		const metaInput = document.querySelector(`[name="${fieldName}"]`);
		if (metaInput) {
			metaInput.value = value;
			metaInput.dispatchEvent(new Event('input', { bubbles: true }));
			metaInput.dispatchEvent(new Event('change', { bubbles: true }));
		}
	} else {
		// ACF text/textarea fields
		updateACFField(`acf[${fieldName}]`, value);
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

	const button = document.createElement('span');
	button.className = 'multilingual-bridge-translate-btn';

	const translationIcon = document.createElement('span');
	translationIcon.className = 'dashicons dashicons-translation';
	translationIcon.title = __('Translate field', 'multilingual-bridge');
	translationIcon.style.cursor = 'pointer';
	button.appendChild(translationIcon);

	const pasteIcon = document.createElement('span');
	pasteIcon.className = 'dashicons dashicons-editor-paste-text';
	pasteIcon.title = __(
		'Copy original text to translation field',
		'multilingual-bridge'
	);
	pasteIcon.style.cursor = 'pointer';
	pasteIcon.style.marginLeft = '5px';
	button.appendChild(pasteIcon);

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

	pasteIcon.addEventListener('click', (e) => {
		e.preventDefault();
		e.stopPropagation();
		onCopy(fieldKey, postId);
	});

	return button;
}
