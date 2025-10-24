/**
 * Translation Modal Component
 *
 * Displays a modal dialog for translating ACF fields with:
 * - Original text from default language post (editable)
 * - Translation field (can be auto-filled via DeepL or manually entered)
 * - Translate button (calls DeepL API)
 * - Use Translation button (saves to ACF field)
 *
 * Modal Flow:
 * 1. User clicks translate icon on ACF field
 * 2. Modal opens and loads original text from default language post
 * 3. User clicks "Translate" to auto-translate or manually enters translation
 * 4. User clicks "Use Translation" to insert text into ACF field
 * 5. Modal closes and field is populated
 *
 * @package
 */

import { createElement, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Modal, TextareaControl, Notice } from '@wordpress/components';
import { useTranslation } from '../hooks/useTranslation';

/**
 * Translation Modal React Component
 *
 * @param {Object}      props           - Component props
 * @param {boolean}     props.isOpen    - Whether modal is visible
 * @param {Function}    props.onClose   - Callback when modal closes
 * @param {Object|null} props.modalData - Field data (null when modal closed)
 * @return {JSX.Element|null} Modal element or null if not open
 */
export const TranslationModal = ({ isOpen, onClose, modalData }) => {
	// Get translation state and operations from custom hook
	const {
		originalValue,
		setOriginalValue,
		translatedValue,
		setTranslatedValue,
		isLoading,
		errorMessage,
		loadOriginal,
		translate,
		reset,
	} = useTranslation(modalData);

	// Track which field data we've loaded to prevent duplicate API calls
	// This prevents re-loading when modal re-renders (e.g., state changes)
	const loadedDataRef = useRef(null);

	/**
	 * Effect: Load original value when modal opens with new field
	 *
	 * Why we need this:
	 * - Automatically fetch original text when modal opens
	 * - Prevent duplicate loads when modal re-renders
	 * - Reset state when modal closes for clean next open
	 */
	useEffect(() => {
		if (isOpen && modalData) {
			// Create unique key for this field to prevent duplicate loads
			const currentDataKey = `${modalData.postId}-${modalData.fieldKey}`;

			// Only load if this is a different field than last time
			if (loadedDataRef.current !== currentDataKey) {
				loadedDataRef.current = currentDataKey;
				loadOriginal();
			}
		} else if (!isOpen) {
			// Reset when modal closes
			loadedDataRef.current = null;
			reset();
		}
	}, [isOpen, modalData, loadOriginal, reset]);

	/**
	 * Save translated value to ACF field
	 *
	 * Dispatches custom event that translation.js listens for.
	 * This decouples the React modal from DOM manipulation.
	 */
	const saveTranslation = () => {
		// Dispatch event with field key and translated value
		const event = new CustomEvent('multilingual-bridge:save-translation', {
			detail: {
				fieldKey: modalData.fieldKey,
				value: translatedValue,
			},
		});
		document.dispatchEvent(event);

		// Close modal after saving
		onClose();
	};

	// Don't render anything if modal is closed or no data
	if (!isOpen || !modalData) {
		return null;
	}

	// Build dynamic labels with language codes
	const modalTitle = `${__('Translate', 'multilingual-bridge')} ${modalData.fieldLabel || modalData.fieldKey}`;
	const sourceLangLabel = `${__('Original', 'multilingual-bridge')} (${modalData.sourceLang})`;
	const targetLangLabel = `${__('Translation', 'multilingual-bridge')} (${modalData.targetLang})`;

	return createElement(
		Modal,
		{
			title: modalTitle,
			onRequestClose: onClose,
			className: 'multilingual-bridge-translation-modal',
			shouldCloseOnClickOutside: false, // Prevent accidental closes
		},
		createElement(
			'div',
			{ className: 'multilingual-bridge-modal-content' },

			// Error notice (only shows when errorMessage is not empty)
			errorMessage &&
				createElement(
					Notice,
					{
						status: 'error',
						isDismissible: false,
						className: 'multilingual-bridge-modal-error',
					},
					errorMessage
				),

			// Two-column layout for original and translation
			createElement(
				'div',
				{ className: 'multilingual-bridge-modal-fields' },

				// Left column: Original text (editable - allows fixing typos)
				createElement(
					'div',
					{ className: 'multilingual-bridge-modal-field' },
					createElement(TextareaControl, {
						label: sourceLangLabel,
						value: originalValue,
						onChange: setOriginalValue,
						disabled: isLoading, // Prevent editing during API calls
						rows: 6,
						className: 'multilingual-bridge-original-field',
					})
				),

				// Right column: Translation (editable - allows manual translation)
				createElement(
					'div',
					{ className: 'multilingual-bridge-modal-field' },
					createElement(TextareaControl, {
						label: targetLangLabel,
						value: translatedValue,
						onChange: setTranslatedValue,
						disabled: isLoading,
						rows: 6,
						className: 'multilingual-bridge-translation-field',
					})
				)
			),

			// Action buttons at bottom
			createElement(
				'div',
				{ className: 'multilingual-bridge-modal-actions' },

				// Translate button (calls DeepL API)
				createElement(
					Button,
					{
						variant: 'secondary',
						onClick: translate,
						disabled: isLoading || !originalValue.trim(), // Need text to translate
						isBusy: isLoading, // Shows spinner during API call
						className: 'multilingual-bridge-translate-button',
					},
					__('Translate', 'multilingual-bridge')
				),

				// Save button (inserts translation into ACF field)
				createElement(
					Button,
					{
						variant: 'primary',
						onClick: saveTranslation,
						disabled: !translatedValue.trim(), // Need translation to save
						className: 'multilingual-bridge-save-button',
					},
					__('Use Translation', 'multilingual-bridge')
				)
			)
		)
	);
};
