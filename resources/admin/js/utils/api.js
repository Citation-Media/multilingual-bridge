/**
 * API utilities for multilingual bridge translation
 */

import apiFetch from '@wordpress/api-fetch';

/**
 * Extract field key from ACF field name (remove acf[] wrapper if present)
 * @param fieldKey
 */
export function cleanFieldKey(fieldKey) {
	const acfMatch = fieldKey.match(/^acf\[([^\]]+)\]$/);
	return acfMatch ? acfMatch[1] : fieldKey;
}

/**
 * Load original value from meta field
 * @param postId
 * @param fieldKey
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
 * @param text
 * @param sourceLang
 * @param targetLang
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
 * Update ACF field value and trigger change events
 * @param fieldKey
 * @param value
 */
export function updateACFField(fieldKey, value) {
	const input = document.querySelector(`[name="${fieldKey}"]`);

	if (input) {
		input.value = value;
		// Trigger change event for ACF
		input.dispatchEvent(new Event('change', { bubbles: true }));
		// Also trigger ACF's change event if available
		if (typeof acf !== 'undefined' && acf.trigger) {
			acf.trigger('change', input);
		}
		return true;
	}

	return false;
}
