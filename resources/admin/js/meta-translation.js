/**
 * Meta Field Translation Entry Point
 *
 * Handles translation for both ACF fields and native WordPress meta fields.
 *
 * @package
 */

import {
	createElement,
	createRoot,
	useState,
	useEffect,
} from '@wordpress/element';
import { TranslationModal } from './components/TranslationModal';
import { copyOriginalToField, createTranslationButton, updateFieldValue } from './utils/fields';

/**
 * Main React App Component
 *
 * @return {JSX.Element} TranslationModal component
 */
const MetaTranslationApp = () => {
	const [isModalOpen, setIsModalOpen] = useState(false);
	const [modalData, setModalData] = useState(null);

	useEffect(() => {
		const handleOpenModal = (event) => {
			setModalData(event.detail);
			setIsModalOpen(true);
		};

		document.addEventListener(
			'multilingual-bridge:open-translation-modal',
			handleOpenModal
		);

		return () => {
			document.removeEventListener(
				'multilingual-bridge:open-translation-modal',
				handleOpenModal
			);
		};
	}, []);

	const closeModal = () => {
		setIsModalOpen(false);
		setModalData(null);
	};

	return createElement(TranslationModal, {
		isOpen: isModalOpen,
		onClose: closeModal,
		modalData,
	});
};

/**
 * Initialize translation buttons for both ACF and native meta fields
 */
function initializeMetaTranslationButtons() {
	// Initialize ACF fields
	// eslint-disable-next-line no-undef
	if (typeof acf !== 'undefined') {
		initializeACFFields();
	}

	// Initialize native meta fields
	initializeNativeMetaFields();
}

/**
 * Initialize ACF field translation buttons
 */
function initializeACFFields() {
	const translatableFields = document.querySelectorAll(
		'.multilingual-translatable-field'
	);

	translatableFields.forEach(function (fieldWrapper) {
		if (fieldWrapper.querySelector('.multilingual-bridge-translate-btn')) {
			return;
		}

		const labelElement = fieldWrapper.querySelector('.acf-label label');
		if (!labelElement) {
			return;
		}

		const fieldData = {
			fieldKey: fieldWrapper.getAttribute('data-field-key'),
			fieldLabel: fieldWrapper.getAttribute('data-field-label'),
			postId: fieldWrapper.getAttribute('data-post-id'),
			sourceLang: fieldWrapper.getAttribute('data-source-lang'),
			targetLang: fieldWrapper.getAttribute('data-target-lang'),
			fieldType: fieldWrapper.getAttribute('data-field-type'),
		};

		const button = createTranslationButton(
			fieldData,
			(data) => {
				const event = new CustomEvent(
					'multilingual-bridge:open-translation-modal',
					{ detail: data }
				);
				document.dispatchEvent(event);
			},
			copyOriginalToField
		);

		labelElement.appendChild(button);
	});
}

/**
 * Initialize native meta field translation buttons
 */
function initializeNativeMetaFields() {
	const metaFields = document.querySelectorAll(
		'.multilingual-translatable-meta-field'
	);

	metaFields.forEach(function (fieldWrapper) {
		if (fieldWrapper.querySelector('.multilingual-bridge-translate-btn')) {
			return;
		}

		const actionsElement = fieldWrapper.querySelector(
			'.multilingual-meta-field-actions'
		);
		if (!actionsElement) {
			return;
		}

		const fieldData = {
			fieldKey: fieldWrapper.getAttribute('data-field-key'),
			fieldLabel: fieldWrapper.getAttribute('data-field-label'),
			postId: fieldWrapper.getAttribute('data-post-id'),
			sourceLang: fieldWrapper.getAttribute('data-source-lang'),
			targetLang: fieldWrapper.getAttribute('data-target-lang'),
			fieldType: fieldWrapper.getAttribute('data-field-type'),
		};

		const button = createTranslationButton(
			fieldData,
			(data) => {
				const event = new CustomEvent(
					'multilingual-bridge:open-translation-modal',
					{ detail: data }
				);
				document.dispatchEvent(event);
			},
			copyOriginalToField
		);

		actionsElement.appendChild(button);
	});
}

/**
 * Bootstrap Application
 */
document.addEventListener('DOMContentLoaded', function () {
	// Create modal container
	let modalContainer = document.getElementById(
		'multilingual-bridge-meta-translation-modal'
	);

	if (!modalContainer) {
		modalContainer = document.createElement('div');
		modalContainer.id = 'multilingual-bridge-meta-translation-modal';
		document.body.appendChild(modalContainer);
	}

	// Render React app
	const root = createRoot(modalContainer);
	root.render(createElement(MetaTranslationApp));

	// Initialize translation buttons
	initializeMetaTranslationButtons();

	// Re-initialize for ACF when it loads new fields
	// eslint-disable-next-line no-undef
	if (typeof acf !== 'undefined' && acf.addAction) {
		// eslint-disable-next-line no-undef
		acf.addAction('ready', initializeMetaTranslationButtons);

		// eslint-disable-next-line no-undef
		acf.addAction('append', initializeMetaTranslationButtons);
	}

	// Event listener: Save translation to field
	document.addEventListener(
		'multilingual-bridge:save-translation',
		function (event) {
			const { fieldKey, value, fieldType } = event.detail;
			updateFieldValue(fieldKey, value, fieldType || 'meta');
		}
	);
});
