/**
 * Custom hook for handling translation state and operations
 */

import { useState, useCallback, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { loadOriginalValue, translateText } from '../utils/api';

export function useTranslation(modalData) {
	const [originalValue, setOriginalValue] = useState('');
	const [translatedValue, setTranslatedValue] = useState('');
	const [isLoading, setIsLoading] = useState(false);
	const [errorMessage, setErrorMessage] = useState('');

	// Memoize modalData to prevent unnecessary re-renders
	const memoizedModalData = useMemo(
		() => modalData,
		[
			modalData?.postId,
			modalData?.fieldKey,
			modalData?.sourceLang,
			modalData?.targetLang,
		]
	);

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
			console.error('Error loading original value:', error);
			setErrorMessage(
				error.message ||
					__('Error loading original value', 'multilingual-bridge')
			);
		} finally {
			setIsLoading(false);
		}
	}, [memoizedModalData]);

	const translate = useCallback(async () => {
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

	const reset = useCallback(() => {
		setOriginalValue('');
		setTranslatedValue('');
		setErrorMessage('');
		setIsLoading(false);
	}, []);

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
