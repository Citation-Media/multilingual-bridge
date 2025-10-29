/**
 * API Utilities for Multilingual Bridge Translation
 *
 * Handles all API interactions for the translation modal including:
 * - Loading original field values from the default language post
 * - Sending translation requests to DeepL API
 * - Updating ACF field values in the DOM
 *
 * @package
 */

import apiFetch from '@wordpress/api-fetch';

/**
 * Clean ACF field key by removing wrapper syntax
 *
 * ACF fields can be accessed via meta with simple keys (e.g., "field_name")
 * or via form submission with wrapper syntax (e.g., "acf[field_name]").
 * This function extracts the clean key for API requests.
 *
 * @param {string} fieldKey - The field key (may include acf[] wrapper)
 * @return {string} Clean field key without wrapper syntax
 *
 * @example
 * cleanFieldKey('acf[my_field]') // Returns: 'my_field'
 * cleanFieldKey('my_field')      // Returns: 'my_field'
 */
export function cleanFieldKey(fieldKey) {
	const acfMatch = fieldKey.match(/^acf\[([^\]]+)\]$/);
	return acfMatch ? acfMatch[1] : fieldKey;
}

/**
 * Load original field value from the default language post
 *
 * Fetches the meta value from the original (default language) post version.
 * This allows translators to see the source text they need to translate.
 *
 * @param {number} postId   - ID of the original language post
 * @param {string} fieldKey - ACF field key to load
 * @return {Promise<string>} The original field value (empty string if not found)
 *
 * @throws {Error} If API request fails or post/field not found
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
 * Translate text using configured translation provider
 *
 * Sends text to the plugin's translation endpoint which uses the configured
 * translation provider (e.g., DeepL, Google Translate) to translate from
 * source language to target language.
 *
 * @param {string}      text       - Text to translate
 * @param {string}      targetLang - Target language code (e.g., 'fr', 'es')
 * @param {string}      sourceLang - Source language code (e.g., 'en', 'de')
 * @param {string|null} provider   - Optional provider ID (uses default if not specified)
 * @return {Promise<string>} Translated text (empty string if translation fails)
 *
 * @throws {Error} If API request fails or translation service unavailable
 */
export async function translateText(text, targetLang, sourceLang, provider = null) {
	const data = {
		text,
		target_lang: targetLang,
		source_lang: sourceLang,
	};

	// Include provider if specified.
	if (provider) {
		data.provider = provider;
	}

	const response = await apiFetch({
		path: '/multilingual-bridge/v1/translate',
		method: 'POST',
		data,
	});

	return response.translation || '';
}

/**
 * Get available translation providers
 *
 * Fetches list of configured translation providers and the default provider.
 * This allows the UI to show provider selection if multiple providers are available.
 *
 * @return {Promise<{providers: Array<{id: string, name: string, available: boolean}>, default: string}>}
 *
 * @throws {Error} If API request fails
 */
export async function getProviders() {
	const response = await apiFetch({
		path: '/multilingual-bridge/v1/providers',
		method: 'GET',
	});

	return response;
}

/**
 * Escape special characters in CSS selectors
 *
 * CSS selectors require special characters to be escaped with backslashes.
 * This prevents errors when selecting elements by field names that contain
 * special characters like brackets, dots, or colons.
 *
 * @param {string} selector - CSS selector string to escape
 * @return {string} Escaped selector safe for use in querySelector
 *
 * @private
 */
function escapeCSSSelector(selector) {
	return selector.replace(/[!"#$%&'()*+,.\/:;<=>?@[\\\]^`{|}~]/g, '\\$&');
}

/**
 * Update ACF field value in the DOM and trigger change events
 *
 * Finds the ACF input field by name, updates its value, and triggers both
 * native and ACF-specific change events to ensure proper state synchronization.
 *
 * Why trigger multiple events:
 * - Native 'change' event: For browser/framework listeners
 * - ACF.trigger(): For ACF's internal state management
 *
 * @param {string} fieldKey - ACF field key/name
 * @param {string} value    - New value to set
 * @return {boolean} True if field was updated, false if field not found
 */
export function updateACFField(fieldKey, value) {
	const escapedFieldKey = escapeCSSSelector(fieldKey);
	const input = document.querySelector(`[name="${escapedFieldKey}"]`);

	if (input) {
		// Update the input value
		input.value = value;

		// Trigger native change event (bubbles up for React/Vue listeners)
		input.dispatchEvent(new Event('change', { bubbles: true }));

		// Trigger ACF's internal change handler if available
		// This ensures ACF's validation and conditional logic runs
		// eslint-disable-next-line no-undef
		if (typeof acf !== 'undefined' && acf.trigger) {
			// eslint-disable-next-line no-undef
			acf.trigger('change', input);
		}

		return true;
	}

	return false;
}
