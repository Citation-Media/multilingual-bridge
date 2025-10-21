/**
 * Multilingual Bridge Translation Entry Point
 *
 * Bootstraps the React translation app and handles ACF field button initialization
 */

import {
	createElement,
	createRoot,
	useState,
	useEffect,
} from '@wordpress/element';
import { TranslationModal } from './components/TranslationModal';
import { copyOriginalToField, createTranslationButton } from './utils/fields';
import { updateACFField } from './utils/api';

// Main App Component
const TranslationApp = () => {
	const [isModalOpen, setIsModalOpen] = useState(false);
	const [modalData, setModalData] = useState(null);

	useEffect(() => {
		// Event listener for opening modal
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

// Initialize the React app and event handlers
document.addEventListener('DOMContentLoaded', function () {
	// Only run on ACF edit screens
	// eslint-disable-next-line no-undef
	if (typeof acf === 'undefined') {
		return;
	}

	// Create container for React app
	const modalContainer = document.createElement('div');
	modalContainer.id = 'multilingual-bridge-react-modal';
	document.body.appendChild(modalContainer);

	// Render React app
	const root = createRoot(modalContainer);
	root.render(createElement(TranslationApp));

	// Function to initialize translation buttons for ACF fields
	function initializeACFTranslationButtons() {
		const translatableFields = document.querySelectorAll(
			'.multilingual-translatable-field'
		);

		translatableFields.forEach(function (fieldWrapper) {
			// Skip if button already exists
			if (
				fieldWrapper.querySelector('.multilingual-bridge-translate-btn')
			) {
				return;
			}

			// Find the field label to append the button
			const labelElement = fieldWrapper.querySelector('.acf-label label');
			if (!labelElement) {
				return;
			}

			// Extract field data
			const fieldData = {
				fieldKey: fieldWrapper.getAttribute('data-field-key'),
				fieldLabel: fieldWrapper.getAttribute('data-field-label'),
				postId: fieldWrapper.getAttribute('data-post-id'),
				sourceLang: fieldWrapper.getAttribute('data-source-lang'),
				targetLang: fieldWrapper.getAttribute('data-target-lang'),
				fieldType: fieldWrapper.getAttribute('data-field-type'),
			};

			// Create translation button using utility
			const button = createTranslationButton(
				fieldData,
				// onTranslate callback
				(data) => {
					const event = new CustomEvent(
						'multilingual-bridge:open-translation-modal',
						{ detail: data }
					);
					document.dispatchEvent(event);
				},
				// onCopy callback
				copyOriginalToField
			);

			// Append button to label
			labelElement.appendChild(button);
		});
	}

	// Initialize buttons on page load
	initializeACFTranslationButtons();

	// Re-initialize when ACF fields are loaded (for dynamic fields)
	// eslint-disable-next-line no-undef
	if (typeof acf !== 'undefined' && acf.addAction) {
		// eslint-disable-next-line no-undef
		acf.addAction('ready', initializeACFTranslationButtons);
		// eslint-disable-next-line no-undef
		acf.addAction('append', initializeACFTranslationButtons);
	}

	// Handle translation saving
	document.addEventListener(
		'multilingual-bridge:save-translation',
		function (event) {
			const { fieldKey, value } = event.detail;
			updateACFField(fieldKey, value);
		}
	);
});
