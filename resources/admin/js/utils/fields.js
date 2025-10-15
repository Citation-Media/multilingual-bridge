/**
 * Field operation utilities
 */

import { __ } from '@wordpress/i18n';
import { loadOriginalValue, updateACFField } from './api';

/**
 * Copy original value to ACF field
 * @param fieldKey
 * @param postId
 */
export async function copyOriginalToField(fieldKey, postId) {
	try {
		const originalValue = await loadOriginalValue(postId, fieldKey);

		if (originalValue.trim()) {
			const success = updateACFField(fieldKey, originalValue);
			if (!success) {
				console.warn(
					'Multilingual Bridge: Could not find field to update',
					fieldKey
				);
			}
		}
	} catch (error) {
		console.error(
			'Multilingual Bridge: Error copying original value',
			error
		);
	}
}

/**
 * Create translation button icons
 * @param fieldData
 * @param onTranslate
 * @param onCopy
 */
export function createTranslationButton(fieldData, onTranslate, onCopy) {
	const { fieldKey, fieldLabel, postId, sourceLang, targetLang, fieldType } =
		fieldData;

	// Create translate button container
	const button = document.createElement('span');
	button.className = 'multilingual-bridge-translate-btn';

	// Add translation icon
	const translationIcon = document.createElement('span');
	translationIcon.className = 'dashicons dashicons-translation';
	translationIcon.title = __('Translate field', 'multilingual-bridge');
	translationIcon.style.cursor = 'pointer';
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
	button.appendChild(pasteIcon);

	// Add click handlers
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
