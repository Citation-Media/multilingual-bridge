/**
 * API utilities for multilingual bridge translation
 */

import apiFetch from '@wordpress/api-fetch';

/**
 * Extract field key from ACF field name (remove acf[] wrapper if present)
 * @param {string} fieldKey
 */
export function cleanFieldKey(fieldKey) {
	const acfMatch = fieldKey.match(/^acf\[([^\]]+)\]$/);
	return acfMatch ? acfMatch[1] : fieldKey;
}

/**
 * Load original value from meta field
 * @param {number} postId
 * @param {string} fieldKey
 */
export async function loadOriginalValue(postId, fieldKey) {
	const cleanKey = cleanFieldKey(fieldKey);

	const response = await apiFetch({
		path: `/multilingual-bridge/v1/meta/${postId}/${encodeURIComponent(cleanKey)}`,
		method: 'GET',
	});

	return response.value || '';
}

/**
 * Translate text using API
 * @param {string} text
 * @param {string} sourceLang
 * @param {string} targetLang
 */
export async function translateText(text, sourceLang, targetLang) {
	const response = await apiFetch({
		path: '/multilingual-bridge/v1/translate',
		method: 'POST',
		data: {
			text,
			target_lang: targetLang,
			source_lang: sourceLang,
		},
	});

	return response.translation || '';
}

/**
 * Escape CSS selector special characters
 * @param {string} selector
 */
function escapeCSSSelector(selector) {
	return selector.replace(/[!"#$%&'()*+,.\/:;<=>?@[\\\]^`{|}~]/g, '\\$&');
}

/**
 * Update ACF field value and trigger change events
 * @param {string} fieldKey
 * @param {string} value
 */
export function updateACFField(fieldKey, value) {
	const escapedFieldKey = escapeCSSSelector(fieldKey);
	const input = document.querySelector(`[name="${escapedFieldKey}"]`);

	if (input) {
		input.value = value;
		// Trigger change event for ACF
		input.dispatchEvent(new Event('change', { bubbles: true }));
		// Also trigger ACF's change event if available
		// eslint-disable-next-line no-undef
		if (typeof acf !== 'undefined' && acf.trigger) {
			// eslint-disable-next-line no-undef
			acf.trigger('change', input);
		}
		return true;
	}

	return false;
}
