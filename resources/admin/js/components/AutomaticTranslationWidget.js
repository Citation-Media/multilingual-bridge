/**
 * Automatic Translation Widget Component
 *
 * React component for the automatic translation sidebar widget that allows
 * translating post meta to multiple languages at once.
 *
 * @package
 */

import { createElement, Fragment, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, CheckboxControl, Notice } from '@wordpress/components';
import { useAutomaticTranslation } from '../hooks/useAutomaticTranslation';

/**
 * Language Checkbox Item Component
 *
 * Renders a single language checkbox with translation status indicator.
 *
 * @param {Object}   props                - Component props
 * @param {string}   props.langName       - Language display name
 * @param {boolean}  props.hasTranslation - Whether translation exists
 * @param {number}   props.translationId  - Existing translation post ID
 * @param {boolean}  props.checked        - Checkbox checked state
 * @param {Function} props.onChange       - Checkbox change handler
 * @param {string}   props.editPostUrl    - URL template for editing posts
 * @return {JSX.Element} Language checkbox item
 */
const LanguageCheckboxItem = ({
	langName,
	hasTranslation,
	translationId,
	checked,
	onChange,
	editPostUrl,
}) => {
	const editUrl = editPostUrl.replace('POST_ID', translationId);

	return createElement(
		'label',
		{ className: 'mlb-language-item' },
		createElement(CheckboxControl, {
			checked,
			onChange,
			label: '',
		}),
		createElement('span', { className: 'mlb-language-flag' }, langName),
		hasTranslation &&
			translationId > 0 &&
			createElement(
				'a',
				{
					href: editUrl,
					className: 'mlb-translation-edit-link',
					title: __('Edit translation', 'multilingual-bridge'),
					target: '_blank',
					rel: 'noopener noreferrer',
				},
				createElement('span', {
					className: 'dashicons dashicons-edit',
				})
			),
		createElement(
			'span',
			{
				className: hasTranslation
					? 'mlb-translation-status mlb-has-translation'
					: 'mlb-translation-status mlb-no-translation',
				title: hasTranslation
					? __('Translation exists', 'multilingual-bridge')
					: __('No translation', 'multilingual-bridge'),
			},
			createElement('span', {
				className: hasTranslation
					? 'dashicons dashicons-yes-alt'
					: 'dashicons dashicons-marker',
			})
		)
	);
};

/**
 * Translation Results Component
 *
 * Displays the results of the automatic translation operation.
 *
 * @param {Object}   props             - Component props
 * @param {Object}   props.result      - Translation API result
 * @param {string[]} props.languages   - Selected language codes
 * @param {Object}   props.langNames   - Map of language codes to names
 * @param {string}   props.editPostUrl - URL template for editing posts
 * @return {JSX.Element} Results display
 */
const TranslationResults = ({ result, languages, langNames, editPostUrl }) => {
	const overallClass = result.success
		? 'mlb-result-success'
		: 'mlb-result-error';
	const overallMessage = result.success
		? __('Translation completed successfully!', 'multilingual-bridge')
		: __('Translation completed with some errors.', 'multilingual-bridge');
	const overallIcon = result.success
		? 'dashicons-yes-alt'
		: 'dashicons-warning';

	return createElement(
		'div',
		{ className: 'mlb-results-list' },

		// Overall status
		createElement(
			'div',
			{ className: `mlb-result-overall ${overallClass}` },
			createElement('span', { className: `dashicons ${overallIcon}` }),
			createElement('strong', null, overallMessage)
		),

		// Individual language results
		...languages.map((langCode) => {
			const langResult = result.languages[langCode];
			if (!langResult) {
				return null;
			}

			const statusClass = langResult.success
				? 'mlb-lang-success'
				: 'mlb-lang-error';
			const statusIcon = langResult.success
				? 'dashicons-yes-alt'
				: 'dashicons-dismiss';
			const langName = langNames[langCode] || langCode;

			let statusContent = '';
			if (langResult.success && langResult.target_post_id > 0) {
				const editUrl = editPostUrl.replace(
					'POST_ID',
					langResult.target_post_id
				);
				const linkText = langResult.created_new
					? __('New translation created.', 'multilingual-bridge')
					: __('Translation updated.', 'multilingual-bridge');

				return createElement(
					'div',
					{
						className: `mlb-result-language ${statusClass}`,
						key: langCode,
					},
					createElement('span', {
						className: `dashicons ${statusIcon}`,
					}),
					createElement('strong', null, `${langName}: `),
					createElement('span', null, linkText + ' '),
					createElement(
						'a',
						{
							href: editUrl,
							target: '_blank',
							rel: 'noopener noreferrer',
							className: 'mlb-edit-link',
						},
						__('Edit Post', 'multilingual-bridge')
					)
				);
			} else if (langResult.success) {
				statusContent = __(
					'Translation completed.',
					'multilingual-bridge'
				);
			} else {
				statusContent = langResult.errors.join(', ');
			}

			return createElement(
				'div',
				{
					className: `mlb-result-language ${statusClass}`,
					key: langCode,
				},
				createElement('span', { className: `dashicons ${statusIcon}` }),
				createElement('strong', null, `${langName}: `),
				createElement('span', null, statusContent)
			);
		})
	);
};

/**
 * Progress Bar Component
 *
 * Displays translation progress indicator.
 *
 * @param {Object} props         - Component props
 * @param {number} props.percent - Progress percentage (0-100)
 * @param {string} props.text    - Progress text
 * @return {JSX.Element} Progress bar
 */
const ProgressBar = ({ percent, text }) => {
	return createElement(
		'div',
		{ className: 'mlb-widget-progress' },
		createElement(
			'div',
			{ className: 'mlb-progress-bar' },
			createElement('div', {
				className: 'mlb-progress-fill',
				style: { width: `${percent}%` },
			})
		),
		createElement('p', { className: 'mlb-progress-text' }, text)
	);
};

/**
 * Main Automatic Translation Widget Component
 *
 * @param {Object} props                 - Component props
 * @param {number} props.postId          - Source post ID
 * @param {Object} props.targetLanguages - Available target languages
 * @param {Object} props.translations    - Existing translations
 * @param {string} props.editPostUrl     - URL template for editing posts
 * @return {JSX.Element} Widget component
 */
export const AutomaticTranslationWidget = ({
	postId,
	targetLanguages,
	translations,
	editPostUrl,
}) => {
	const {
		selectedLanguages,
		toggleLanguage,
		isTranslating,
		progressPercent,
		progressText,
		result,
		errorMessage,
		translate,
	} = useAutomaticTranslation(postId, targetLanguages, translations);

	// Local validation error state
	const [validationError, setValidationError] = useState(null);

	// Get language names for display
	const langNames = Object.fromEntries(
		Object.entries(targetLanguages).map(([code, data]) => [code, data.name])
	);

	/**
	 * Handle translate button click
	 */
	const handleTranslate = () => {
		if (selectedLanguages.length === 0) {
			setValidationError(
				__(
					'Please select at least one target language.',
					'multilingual-bridge'
				)
			);
			return;
		}

		// Clear any previous validation errors
		setValidationError(null);
		translate();
	};

	return createElement(
		'div',
		{ id: 'multilingual-bridge-automatic-widget' },

		// Language list
		createElement(
			'div',
			{ className: 'mlb-widget-languages' },
			createElement(
				'p',
				null,
				createElement(
					'strong',
					null,
					__('Select target languages:', 'multilingual-bridge')
				)
			),

			Object.keys(targetLanguages).length === 0
				? createElement(
						'p',
						{ className: 'mlb-no-languages' },
						__(
							'No other languages available.',
							'multilingual-bridge'
						)
					)
				: createElement(
						Fragment,
						null,

						// Language checkboxes
						createElement(
							'div',
							{ className: 'mlb-language-list' },
							...Object.entries(targetLanguages).map(
								([langCode, language]) => {
									const hasTranslation =
										translations[langCode] !== undefined;
									const translationId = hasTranslation
										? translations[langCode]
										: 0;

									return createElement(LanguageCheckboxItem, {
										key: langCode,
										langCode,
										langName: language.name,
										hasTranslation,
										translationId,
										checked:
											selectedLanguages.includes(
												langCode
											),
										onChange: () =>
											toggleLanguage(langCode),
										editPostUrl,
									});
								}
							)
						),

						// Generate translations button
						createElement(
							'div',
							{ className: 'mlb-widget-actions' },
							createElement(
								Button,
								{
									variant: 'primary',
									onClick: handleTranslate,
									disabled: isTranslating,
									isBusy: isTranslating,
									className:
										'button button-primary button-large',
									id: 'mlb-generate-translation',
								},
								createElement('span', {
									className:
										'dashicons dashicons-translation',
								}),
								__(
									'Generate Translations',
									'multilingual-bridge'
								)
							)
						),

						// Progress bar (only shown during translation)
						isTranslating &&
							createElement(ProgressBar, {
								percent: progressPercent,
								text: progressText,
							}),

						// Validation error (shown when no language selected)
						validationError &&
							!isTranslating &&
							createElement(
								Notice,
								{
									status: 'warning',
									isDismissible: true,
									onRemove: () => setValidationError(null),
									className: 'mlb-widget-validation-error',
								},
								validationError
							),

						// Error message from API
						errorMessage &&
							createElement(
								Notice,
								{
									status: 'error',
									isDismissible: false,
									className: 'mlb-widget-error',
								},
								errorMessage
							),

						// Results (only shown after translation completes)
						result &&
							createElement(
								'div',
								{
									className: 'mlb-widget-results',
									style: { display: 'block' },
								},
								createElement(TranslationResults, {
									result,
									languages: selectedLanguages,
									langNames,
									editPostUrl,
								})
							)
					)
		),

		// Footer description
		createElement(
			'div',
			{ className: 'mlb-widget-footer' },
			createElement(
				'p',
				{ className: 'description' },
				__(
					'This will translate all translatable post meta (ACF fields, custom fields, etc.) to the selected languages.',
					'multilingual-bridge'
				)
			)
		)
	);
};
