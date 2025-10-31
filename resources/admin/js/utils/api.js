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
export async function translateText(
	text,
	targetLang,
	sourceLang,
	provider = null
) {
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
 * @return {Promise<{providers: Array<{id: string, name: string, available: boolean}>, default: string}>} Object containing array of provider configurations and default provider ID
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
 * Get current ACF field value from the DOM
 *
 * Reads the current value of an ACF field from the page.
 * This is used to populate the translation field with existing content
 * so users can edit existing translations instead of starting from scratch.
 *
 * Handles different field types:
 * - text/textarea: Reads input.value
 * - wysiwyg: Reads from TinyMCE iframe content
 *
 * @param {string} fieldKey  - ACF field key/name (with or without acf[] wrapper)
 * @param {string} fieldType - Optional ACF field type (text, textarea, wysiwyg, etc.)
 * @return {string} Current field value (empty string if not found)
 */
export function getCurrentFieldValue(fieldKey, fieldType = 'text') {
	// Normalize field name to ACF format (acf[field_name])
	const acfFieldName = fieldKey.startsWith('acf[')
		? fieldKey
		: `acf[${fieldKey}]`;

	// Handle WYSIWYG fields (TinyMCE editor)
	if (fieldType === 'wysiwyg') {
		// TinyMCE creates an iframe with ID format: acf[field_name]_ifr
		const iframeId = `${acfFieldName}_ifr`;
		const iframe = document.getElementById(iframeId);

		if (iframe && iframe.contentWindow) {
			const doc = iframe.contentWindow.document;
			if (doc.body) {
				return doc.body.innerHTML || '';
			}
		}

		// Fall through to textarea if iframe not found (visual editor disabled)
	}

	// Handle text, textarea, and other standard input fields
	const escapedFieldKey = escapeCSSSelector(acfFieldName);
	const input = document.querySelector(`[name="${escapedFieldKey}"]`);

	if (input) {
		return input.value || '';
	}

	return '';
}

/**
 * Update ACF field value in the DOM and trigger change events
 *
 * Finds the ACF input field by name, updates its value, and triggers both
 * native and ACF-specific change events to ensure proper state synchronization.
 *
 * ACF fields use the naming format: acf[field_name]
 * This function accepts either format:
 * - Plain name: "my_field" → searches for acf[my_field]
 * - Full ACF name: "acf[my_field]" → searches as-is
 *
 * Field type handling:
 * - text/textarea: Direct value update
 * - wysiwyg: Updates TinyMCE iframe content
 * - Other types: Falls back to direct value update
 *
 * Why trigger multiple events:
 * - Native 'change' event: For browser/framework listeners
 * - ACF.trigger(): For ACF's internal state management
 *
 * @param {string} fieldKey  - ACF field key/name (with or without acf[] wrapper)
 * @param {string} value     - New value to set
 * @param {string} fieldType - Optional ACF field type (text, textarea, wysiwyg, etc.)
 * @return {boolean} True if field was updated, false if field not found
 */
export function updateACFField(fieldKey, value, fieldType = 'text') {
	// Normalize field name to ACF format (acf[field_name])
	// If already in ACF format, use as-is; otherwise wrap it
	const acfFieldName = fieldKey.startsWith('acf[')
		? fieldKey
		: `acf[${fieldKey}]`;

	// Handle WYSIWYG fields (TinyMCE editor)
	if (fieldType === 'wysiwyg') {
		// TinyMCE creates an iframe with ID format: acf[field_name]_ifr
		const iframeId = `${acfFieldName}_ifr`;
		const iframe = document.getElementById(iframeId);

		if (iframe && iframe.contentWindow) {
			const doc = iframe.contentWindow.document;
			if (doc.body) {
				doc.body.innerHTML = value;

				// Trigger change on the textarea that TinyMCE is bound to
				const textarea = document.querySelector(
					`[name="${escapeCSSSelector(acfFieldName)}"]`
				);
				if (textarea) {
					textarea.value = value;
					textarea.dispatchEvent(
						new Event('change', { bubbles: true })
					);

					// eslint-disable-next-line no-undef
					if (typeof acf !== 'undefined' && acf.trigger) {
						// eslint-disable-next-line no-undef
						acf.trigger('change', textarea);
					}
				}

				return true;
			}
		}

		// Fall through to regular input handling if iframe not found
	}

	// Handle text, textarea, and other standard input fields
	const escapedFieldKey = escapeCSSSelector(acfFieldName);
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
