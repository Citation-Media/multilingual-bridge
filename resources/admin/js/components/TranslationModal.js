/**
 * Translation Modal Component
 */

import { createElement, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Modal, TextareaControl, Notice } from '@wordpress/components';
import { useTranslation } from '../hooks/useTranslation';

export const TranslationModal = ({ isOpen, onClose, modalData }) => {
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

	// Use ref to track if we've already loaded for this modalData
	const loadedDataRef = useRef(null);

	// Load original value when modal opens with new data
	useEffect(() => {
		if (isOpen && modalData) {
			// Only load if this is new modalData or we haven't loaded yet
			const currentDataKey = `${modalData.postId}-${modalData.fieldKey}`;
			if (loadedDataRef.current !== currentDataKey) {
				loadedDataRef.current = currentDataKey;
				loadOriginal();
			}
		} else if (!isOpen) {
			// Reset when modal closes
			loadedDataRef.current = null;
			reset();
		}
	}, [isOpen, modalData?.postId, modalData?.fieldKey, loadOriginal, reset]);

	const saveTranslation = () => {
		// Dispatch event to save the translation
		const event = new CustomEvent('multilingual-bridge:save-translation', {
			detail: {
				fieldKey: modalData.fieldKey,
				value: translatedValue,
			},
		});
		document.dispatchEvent(event);
		onClose();
	};

	if (!isOpen || !modalData) {
		return null;
	}

	const modalTitle = `${__('Translate', 'multilingual-bridge')} ${modalData.fieldLabel || modalData.fieldKey}`;
	const sourceLangLabel = `${__('Original', 'multilingual-bridge')} (${modalData.sourceLang})`;
	const targetLangLabel = `${__('Translation', 'multilingual-bridge')} (${modalData.targetLang})`;

	return createElement(
		Modal,
		{
			title: modalTitle,
			onRequestClose: onClose,
			className: 'multilingual-bridge-translation-modal',
			shouldCloseOnClickOutside: false,
		},
		createElement(
			'div',
			{ className: 'multilingual-bridge-modal-content' },
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
			createElement(
				'div',
				{ className: 'multilingual-bridge-modal-fields' },
				createElement(
					'div',
					{ className: 'multilingual-bridge-modal-field' },
					createElement(TextareaControl, {
						label: sourceLangLabel,
						value: originalValue,
						onChange: setOriginalValue,
						disabled: isLoading,
						rows: 6,
						className: 'multilingual-bridge-original-field',
					})
				),
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
			createElement(
				'div',
				{ className: 'multilingual-bridge-modal-actions' },
				createElement(
					Button,
					{
						variant: 'secondary',
						onClick: translate,
						disabled: isLoading || !originalValue.trim(),
						isBusy: isLoading,
						className: 'multilingual-bridge-translate-button',
					},
					__('Translate', 'multilingual-bridge')
				),
				createElement(
					Button,
					{
						variant: 'primary',
						onClick: saveTranslation,
						disabled: !translatedValue.trim(),
						className: 'multilingual-bridge-save-button',
					},
					__('Use Translation', 'multilingual-bridge')
				)
			)
		)
	);
};
