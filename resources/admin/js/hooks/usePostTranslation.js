/**
 * usePostTranslation Hook
 *
 * Custom React hook for managing post translation state and operations.
 * Handles language selection, API calls, progress tracking, and results display.
 *
 * @package
 */

import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

/**
 * Custom hook for post translation functionality
 *
 * @param {number}  postId              - Source post ID
 * @param {Object}  targetLanguages     - Available target languages object
 * @param {Object}  translations        - Existing translations object
 * @param {Object}  translationsPending - Pending updates for translations
 * @param {boolean} isNavigation        - Whether this is navigation-only mode (skips translation state)
 * @return {Object} Translation state and methods
 */
export const usePostTranslation = (
	postId,
	targetLanguages,
	translations,
	translationsPending = {},
	isNavigation = false
) => {
	// In navigation mode, skip all translation state to avoid unnecessary overhead
	// Only initialize minimal state needed for component consistency
	const emptyArray = [];
	const emptyObject = {};

	// Selected language codes for translation
	const [selectedLanguages, setSelectedLanguages] = useState(emptyArray);

	// Translation in progress
	const [isTranslating, setIsTranslating] = useState(false);

	// Progress tracking
	const [progressPercent, setProgressPercent] = useState(0);
	const [progressText, setProgressText] = useState('');

	// Translation results
	const [result, setResult] = useState(null);

	// Error message
	const [errorMessage, setErrorMessage] = useState('');

	// Track updated translations to update UI
	const [updatedTranslations, setUpdatedTranslations] =
		useState(translations);

	// Track pending updates
	const [pendingUpdates, setPendingUpdates] = useState(translationsPending);

	// Early return for navigation mode - skip all function definitions
	if (isNavigation) {
		return {
			selectedLanguages: emptyArray,
			toggleLanguage: () => {},
			isTranslating: false,
			progressPercent: 0,
			progressText: '',
			result: null,
			errorMessage: '',
			translate: () => {},
			reset: () => {},
			updatedTranslations: emptyObject,
			pendingUpdates: emptyObject,
		};
	}

	/**
	 * Toggle language selection
	 *
	 * @param {string} langCode - Language code to toggle
	 */
	const toggleLanguage = (langCode) => {
		setSelectedLanguages((prev) => {
			if (prev.includes(langCode)) {
				return prev.filter((code) => code !== langCode);
			}
			return [...prev, langCode];
		});
	};

	/**
	 * Update progress bar
	 *
	 * @param {number} percent - Progress percentage (0-100)
	 * @param {string} text    - Progress text
	 */
	const updateProgress = (percent, text) => {
		setProgressPercent(percent);
		setProgressText(text);
	};

	/**
	 * Call post translate REST API
	 *
	 * @return {Promise<Object>} API response
	 */
	const callTranslateAPI = async () => {
		try {
			const response = await apiFetch({
				path: '/multilingual-bridge/v1/post-translate',
				method: 'POST',
				data: {
					post_id: postId,
					target_languages: selectedLanguages,
				},
			});

			return response;
		} catch (error) {
			throw new Error(
				error.message ||
					__(
						'Translation failed. Please try again.',
						'multilingual-bridge'
					)
			);
		}
	};

	/**
	 * Execute translation
	 */
	const translate = async () => {
		// Reset previous results
		setResult(null);
		setErrorMessage('');

		// Start translation
		setIsTranslating(true);
		updateProgress(0, __('Translating…', 'multilingual-bridge'));

		try {
			// Call API
			const response = await callTranslateAPI();

			// Update progress to 100%
			updateProgress(100, __('Complete!', 'multilingual-bridge'));

			// Store results with normalized structure
			// API returns: { success, source_post_id, translated_languages, translated_post_ids, message }
			// Convert to UI-friendly format with per-language results
			const languagesResult = {};
			selectedLanguages.forEach((langCode) => {
				const wasTranslated =
					response.translated_languages?.includes(langCode);
				languagesResult[langCode] = {
					success: wasTranslated,
					target_post_id:
						response.translated_post_ids?.[langCode] || 0,
					errors: wasTranslated ? [] : ['Translation failed'],
				};
			});

			setResult({
				success: response.success,
				languages: languagesResult,
			});

			// Update translations state for UI updates
			const newTranslations = { ...updatedTranslations };
			Object.entries(response.translated_post_ids || {}).forEach(
				([langCode, translatedPostId]) => {
					if (translatedPostId > 0) {
						newTranslations[langCode] = translatedPostId;
					}
				}
			);
			setUpdatedTranslations(newTranslations);

			// Clear pending updates for successfully translated languages
			const newPendingUpdates = { ...pendingUpdates };
			response.translated_languages?.forEach((langCode) => {
				// Always set hasPending to false for successfully translated languages
				newPendingUpdates[langCode] = { hasPending: false };
			});
			setPendingUpdates(newPendingUpdates);

			// Clear error
			setErrorMessage('');
		} catch (error) {
			// Show error
			setErrorMessage(error.message);
			setResult(null);
		} finally {
			// Stop translation
			setIsTranslating(false);
		}
	};

	/**
	 * Reset state
	 */
	const reset = () => {
		setSelectedLanguages([]);
		setIsTranslating(false);
		setProgressPercent(0);
		setProgressText('');
		setResult(null);
		setErrorMessage('');
	};

	return {
		selectedLanguages,
		toggleLanguage,
		isTranslating,
		progressPercent,
		progressText,
		result,
		errorMessage,
		translate,
		reset,
		updatedTranslations,
		pendingUpdates,
	};
};
