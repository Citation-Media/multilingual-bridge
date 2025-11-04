/**
 * Post Translation Widget Component
 *
 * React component for the post translation sidebar widget that allows
 * translating post meta to multiple languages at once.
 *
 * @package
 */

import {
	createElement,
	Fragment,
	useState,
	useEffect,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, CheckboxControl, Notice } from '@wordpress/components';
import { usePostTranslation } from '../hooks/usePostTranslation';

/**
 * Language Checkbox Item Component
 *
 * Renders a single language checkbox with translation status indicator.
 *
 * @param {Object}   props                  - Component props
 * @param {string}   props.langName         - Language display name
 * @param {boolean}  props.hasTranslation   - Whether translation exists
 * @param {number}   props.translationId    - Existing translation post ID
 * @param {boolean}  props.checked          - Checkbox checked state
 * @param {Function} props.onChange         - Checkbox change handler
 * @param {string}   props.editPostUrl      - URL template for editing posts
 * @param {boolean}  props.isNewTranslation - Whether this is a newly created translation
 * @param {boolean}  props.hasPending       - Whether translation has pending updates
 * @return {JSX.Element} Language checkbox item
 */
const LanguageCheckboxItem = ({
	langName,
	hasTranslation,
	translationId,
	checked,
	onChange,
	editPostUrl,
	isNewTranslation,
	hasPending,
}) => {
	const editUrl = editPostUrl.replace('POST_ID', translationId);

	// Determine status classes and icon
	let statusClass = 'mlb-translation-status';
	let iconClass = 'dashicons';
	let titleText = '';

	if (hasPending) {
		statusClass += ' mlb-translation-pending';
		iconClass += ' dashicons-warning';
		titleText = __('Translation needs update', 'multilingual-bridge');
	} else if (hasTranslation) {
		statusClass += ` mlb-has-translation${isNewTranslation ? ' mlb-new-translation' : ''}`;
		iconClass += ' dashicons-yes-alt';
		titleText = __('Translation exists', 'multilingual-bridge');
	} else {
		statusClass += ' mlb-no-translation';
		iconClass += ' dashicons-marker';
		titleText = __('No translation', 'multilingual-bridge');
	}

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
				className: statusClass,
				title: titleText,
			},
			createElement('span', {
				className: iconClass,
			})
		)
	);
};

/**
 * Translation Errors Component
 *
 * Displays only errors from the post translation operation.
 *
 * @param {Object}   props           - Component props
 * @param {Object}   props.result    - Translation API result
 * @param {string[]} props.languages - Selected language codes
 * @param {Object}   props.langNames - Map of language codes to names
 * @return {JSX.Element|null} Errors display or null if no errors
 */
const TranslationErrors = ({ result, languages, langNames }) => {
	// Only show errors, not successful results
	const errors = languages
		.map((langCode) => {
			const langResult = result.languages[langCode];
			if (!langResult || langResult.success) {
				return null;
			}

			const langName = langNames[langCode] || langCode;
			const statusContent = langResult.errors.join(', ');

			return createElement(
				'div',
				{
					className: 'mlb-result-language mlb-lang-error',
					key: langCode,
				},
				createElement('span', {
					className: 'dashicons dashicons-dismiss',
				}),
				createElement('strong', null, `${langName}: `),
				createElement('span', null, statusContent)
			);
		})
		.filter(Boolean);

	// If no errors, don't render anything
	if (errors.length === 0) {
		return null;
	}

	// Show overall error message and individual errors
	return createElement(
		'div',
		{ className: 'mlb-results-list' },
		createElement(
			'div',
			{ className: 'mlb-result-overall mlb-result-error' },
			createElement('span', { className: 'dashicons dashicons-warning' }),
			createElement(
				'strong',
				null,
				__(
					'Translation completed with some errors.',
					'multilingual-bridge'
				)
			)
		),
		...errors
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
 * Main Post Translation Widget Component
 *
 * @param {Object} props                     - Component props
 * @param {number} props.postId              - Source post ID
 * @param {Object} props.targetLanguages     - Available target languages
 * @param {Object} props.translations        - Existing translations
 * @param {Object} props.translationsPending - Pending updates for each translation
 * @param {string} props.editPostUrl         - URL template for editing posts
 * @return {JSX.Element} Widget component
 */
export const PostTranslationWidget = ({
	postId,
	targetLanguages,
	translations,
	translationsPending,
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
		updatedTranslations,
		pendingUpdates,
	} = usePostTranslation(
		postId,
		targetLanguages,
		translations,
		translationsPending
	);

	// Local validation error state
	const [validationError, setValidationError] = useState(null);

	// Track newly translated languages to highlight them
	const [newlyTranslated, setNewlyTranslated] = useState({});

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

		// Clear any previous validation errors and highlights
		setValidationError(null);
		setNewlyTranslated({});

		// Execute translation (async handled in hook)
		translate();
	};

	// Watch for translation results to update newly translated state
	useEffect(() => {
		if (result && !isTranslating) {
			const newTranslations = {};
			selectedLanguages.forEach((langCode) => {
				const langResult = result.languages?.[langCode];
				if (langResult && langResult.success) {
					newTranslations[langCode] = true;
				}
			});

			// Update newly translated state
			if (Object.keys(newTranslations).length > 0) {
				setNewlyTranslated(newTranslations);

				// Clear the highlight after 3 seconds
				const timer = setTimeout(() => {
					setNewlyTranslated({});
				}, 3000);

				return () => clearTimeout(timer);
			}
		}
	}, [result, isTranslating, selectedLanguages]);

	return createElement(
		'div',
		{ id: 'multilingual-bridge-post-widget' },

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

						// Success notification (shown after successful translation)
						result &&
							result.success &&
							!isTranslating &&
							Object.keys(newlyTranslated).length > 0 &&
							createElement(
								Notice,
								{
									status: 'success',
									isDismissible: false,
									className: 'mlb-widget-success',
								},
								__(
									'Translation completed successfully!',
									'multilingual-bridge'
								)
							),

						// Language checkboxes
						createElement(
							'div',
							{ className: 'mlb-language-list' },
							...Object.entries(targetLanguages).map(
								([langCode, language]) => {
									const hasTranslation =
										updatedTranslations[langCode] !==
										undefined;
									const translationId = hasTranslation
										? updatedTranslations[langCode]
										: 0;
									const isNewTranslation =
										newlyTranslated[langCode] === true;
									const hasPending =
										pendingUpdates[langCode]?.hasPending ||
										false;

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
										isNewTranslation,
										hasPending,
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

						// Translation errors only (successful translations shown in language list)
						result &&
							!result.success &&
							createElement(
								'div',
								{
									className: 'mlb-widget-results',
									style: { display: 'block' },
								},
								createElement(TranslationErrors, {
									result,
									languages: selectedLanguages,
									langNames,
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
					'This will translate all translatable ACF fields to the selected languages.',
					'multilingual-bridge'
				)
			)
		)
	);
};
