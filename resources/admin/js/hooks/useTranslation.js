/**
 * Custom React Hook for Translation Modal State Management
 *
 * Manages all state and operations for the translation modal including:
 * - Loading original text from default language post
 * - Calling translation API (DeepL)
 * - Managing loading/error states
 * - Resetting state when modal closes
 *
 * This hook encapsulates complex async logic and provides a clean interface
 * for the TranslationModal component.
 *
 * @package
 */

import { useState, useCallback, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { loadOriginalValue, translateText } from '../utils/api';

/**
 * Translation modal state management hook
 *
 * @param {Object|null} modalData            - Data about the field being translated
 * @param {string}      modalData.fieldKey   - ACF field key
 * @param {string}      modalData.fieldLabel - Field label for display
 * @param {number}      modalData.postId     - Original language post ID
 * @param {string}      modalData.sourceLang - Source language code
 * @param {string}      modalData.targetLang - Target language code
 * @param {string}      modalData.fieldType  - ACF field type
 * @return {Object} Translation state and operations containing originalValue (string), setOriginalValue (Function), translatedValue (string), setTranslatedValue (Function), isLoading (boolean), errorMessage (string), loadOriginal (Function), translate (Function), and reset (Function)
 */
export function useTranslation(modalData) {
	// State for original language text (loaded from default language post)
	const [originalValue, setOriginalValue] = useState('');

	// State for translated text (from DeepL or manual entry)
	const [translatedValue, setTranslatedValue] = useState('');

	// Loading state (true during API calls)
	const [isLoading, setIsLoading] = useState(false);

	// Error message to display (empty string = no error)
	const [errorMessage, setErrorMessage] = useState('');

	// Memoize modalData to prevent unnecessary re-renders when parent re-renders
	// Only create new reference if modalData actually changes
	const memoizedModalData = useMemo(() => modalData, [modalData]);

	/**
	 * Load original value from default language post
	 *
	 * Called automatically when modal opens (see TranslationModal useEffect).
	 * Fetches the meta value from the original post to show translator the source text.
	 */
	const loadOriginal = useCallback(async () => {
		if (!memoizedModalData) {
			return;
		}

		try {
			setIsLoading(true);
			setErrorMessage('');

			const value = await loadOriginalValue(
				memoizedModalData.postId,
				memoizedModalData.fieldKey
			);
			setOriginalValue(value);
		} catch (error) {
			setErrorMessage(
				error.message ||
					__('Error loading original value', 'multilingual-bridge')
			);
		} finally {
			setIsLoading(false);
		}
	}, [memoizedModalData]);

	/**
	 * Translate original text via DeepL API
	 *
	 * Called when user clicks "Translate" button in modal.
	 * Sends original text to translation endpoint and updates translated value.
	 */
	const translate = useCallback(async () => {
		// Don't translate if no text or no modal data
		if (!originalValue.trim() || !memoizedModalData) {
			return;
		}

		try {
			setIsLoading(true);
			setErrorMessage('');

			const translation = await translateText(
				originalValue,
				memoizedModalData.sourceLang,
				memoizedModalData.targetLang
			);
			setTranslatedValue(translation);
		} catch (error) {
			setErrorMessage(
				error.message || __('Translation failed', 'multilingual-bridge')
			);
		} finally {
			setIsLoading(false);
		}
	}, [originalValue, memoizedModalData]);

	/**
	 * Reset all state to initial values
	 *
	 * Called when modal closes to ensure clean state for next open.
	 */
	const reset = useCallback(() => {
		setOriginalValue('');
		setTranslatedValue('');
		setErrorMessage('');
		setIsLoading(false);
	}, []);

	// Return all state and operations for use in TranslationModal
	return {
		originalValue,
		setOriginalValue,
		translatedValue,
		setTranslatedValue,
		isLoading,
		errorMessage,
		loadOriginal,
		translate,
		reset,
	};
}
