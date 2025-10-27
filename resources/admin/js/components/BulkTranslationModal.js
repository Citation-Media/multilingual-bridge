/**
 * Bulk Translation Modal Component
 *
 * @package
 */

import {
	createElement,
	useState,
	useEffect,
	useCallback,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Modal,
	Notice,
	Spinner,
	TextareaControl,
} from '@wordpress/components';
import { fetchFields, translateText } from '../utils/api';
import { updateFieldValue } from '../utils/fields';

/**
 * Bulk Translation Modal React Component
 *
 * @param {Object}      props           - Component props
 * @param {boolean}     props.isOpen    - Whether modal is visible
 * @param {Function}    props.onClose   - Callback when modal closes
 * @param {Object|null} props.modalData - Post data (postId, sourceLang, targetLang)
 * @return {JSX.Element|null} Modal element or null if not open
 */
export const BulkTranslationModal = ({ isOpen, onClose, modalData }) => {
	const [fields, setFields] = useState([]);
	const [isLoadingFields, setIsLoadingFields] = useState(false);
	const [isTranslating, setIsTranslating] = useState(false);
	const [translationProgress, setTranslationProgress] = useState({});
	const [errorMessage, setErrorMessage] = useState('');

	const resetState = () => {
		setFields([]);
		setIsLoadingFields(false);
		setIsTranslating(false);
		setTranslationProgress({});
		setErrorMessage('');
	};

	const loadFields = useCallback(async () => {
		setIsLoadingFields(true);
		setErrorMessage('');

		try {
			const response = await fetchFields(modalData.postId);
			setFields(response.fields || []);
			if (!response.fields || response.fields.length === 0) {
				setErrorMessage(
					__('No translatable fields found.', 'multilingual-bridge')
				);
			}
		} catch (error) {
			setErrorMessage(
				error.message ||
					__('Failed to load fields.', 'multilingual-bridge')
			);
		} finally {
			setIsLoadingFields(false);
		}
	}, [modalData]);

	useEffect(() => {
		if (isOpen && modalData) {
			loadFields();
		} else if (!isOpen) {
			resetState();
		}
	}, [isOpen, modalData, loadFields]);

	const translateAllFields = async () => {
		setIsTranslating(true);
		setErrorMessage('');

		const progress = {};
		const updatedFields = [...fields];

		for (let i = 0; i < updatedFields.length; i++) {
			const field = updatedFields[i];

			if (!field.sourceValue || !field.sourceValue.trim()) {
				progress[field.key] = { status: 'skipped' };
				continue;
			}

			progress[field.key] = { status: 'translating' };
			setTranslationProgress({ ...progress });

			try {
				const translated = await translateText(
					field.sourceValue,
					modalData.targetLang,
					modalData.sourceLang
				);

				updatedFields[i] = { ...field, targetValue: translated };
				progress[field.key] = {
					status: 'completed',
					value: translated,
				};
			} catch (error) {
				progress[field.key] = {
					status: 'error',
					error:
						error.message ||
						__('Translation failed', 'multilingual-bridge'),
				};
			}

			setTranslationProgress({ ...progress });
		}

		setFields(updatedFields);
		setIsTranslating(false);
	};

	const applyTranslations = () => {
		fields.forEach((field) => {
			if (field.targetValue) {
				updateFieldValue(field.name, field.targetValue, field.type);
			}
		});

		onClose();
	};

	const updateFieldTargetValue = (fieldKey, newValue) => {
		setFields(
			fields.map((field) =>
				field.key === fieldKey
					? { ...field, targetValue: newValue }
					: field
			)
		);
	};

	if (!isOpen || !modalData) {
		return null;
	}

	return createElement(
		Modal,
		{
			title: __('Translate All Fields', 'multilingual-bridge'),
			onRequestClose: onClose,
			className: 'multilingual-bridge-bulk-translation-modal',
			shouldCloseOnClickOutside: false,
		},
		createElement(
			'div',
			{ className: 'multilingual-bridge-bulk-modal-content' },

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

			isLoadingFields &&
				createElement(
					'div',
					{ className: 'multilingual-bridge-loading' },
					createElement(Spinner),
					createElement(
						'p',
						{},
						__('Loading fields…', 'multilingual-bridge')
					)
				),

			!isLoadingFields &&
				fields.length > 0 &&
				createElement(
					'div',
					{ className: 'multilingual-bridge-fields-list' },
					createElement(
						'p',
						{ className: 'multilingual-bridge-fields-summary' },
						`${__('Found', 'multilingual-bridge')} ${fields.length} ${__('translatable field(s)', 'multilingual-bridge')}`
					),
					createElement(
						'div',
						{ className: 'multilingual-bridge-fields-grid' },
						fields.map((field) =>
							createElement(
								'div',
								{
									key: field.key,
									className: 'multilingual-bridge-field-item',
								},
								createElement(
									'div',
									{
										className:
											'multilingual-bridge-field-header',
									},
									createElement(
										'strong',
										{
											className:
												'multilingual-bridge-field-label',
										},
										field.label
									),
									createElement(
										'div',
										{
											className:
												'multilingual-bridge-field-status',
										},
										translationProgress[field.key]
											?.status === 'translating' &&
											createElement(Spinner),
										translationProgress[field.key]
											?.status === 'completed' &&
											createElement('span', {
												className:
													'dashicons dashicons-yes-alt',
											}),
										translationProgress[field.key]
											?.status === 'error' &&
											createElement('span', {
												className:
													'dashicons dashicons-warning',
											}),
										translationProgress[field.key]
											?.status === 'skipped' &&
											createElement(
												'span',
												{
													className:
														'multilingual-bridge-skipped',
												},
												__(
													'Skipped',
													'multilingual-bridge'
												)
											)
									)
								),
								createElement(
									'div',
									{
										className:
											'multilingual-bridge-field-content',
									},
									createElement(
										'div',
										{
											className:
												'multilingual-bridge-field-source',
										},
										createElement(TextareaControl, {
											label: `${__('Original', 'multilingual-bridge')} (${modalData.sourceLang})`,
											value: field.sourceValue || '',
											disabled: true,
											rows: 4,
											className:
												'multilingual-bridge-source-field',
										})
									),
									createElement(
										'div',
										{
											className:
												'multilingual-bridge-field-target',
										},
										createElement(TextareaControl, {
											label: `${__('Translation', 'multilingual-bridge')} (${modalData.targetLang})`,
											value: field.targetValue || '',
											onChange: (newValue) =>
												updateFieldTargetValue(
													field.key,
													newValue
												),
											disabled: isTranslating,
											rows: 4,
											className:
												'multilingual-bridge-target-field',
										})
									)
								),
								translationProgress[field.key]?.status ===
									'error' &&
									createElement(
										Notice,
										{
											status: 'error',
											isDismissible: false,
											className:
												'multilingual-bridge-field-error',
										},
										translationProgress[field.key].error
									)
							)
						)
					)
				),

			!isLoadingFields &&
				fields.length > 0 &&
				createElement(
					'div',
					{ className: 'multilingual-bridge-modal-actions' },
					createElement(
						Button,
						{
							variant: 'secondary',
							onClick: translateAllFields,
							disabled: isTranslating,
							isBusy: isTranslating,
							className:
								'multilingual-bridge-bulk-translate-button',
						},
						__('Translate All', 'multilingual-bridge')
					),
					createElement(
						Button,
						{
							variant: 'primary',
							onClick: applyTranslations,
							disabled:
								isTranslating ||
								!fields.some(
									(f) => f.targetValue && f.targetValue.trim()
								),
							className: 'multilingual-bridge-apply-button',
						},
						__('Apply Translations', 'multilingual-bridge')
					)
				)
		)
	);
};
