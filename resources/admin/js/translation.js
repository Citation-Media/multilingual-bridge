/**
 * Multilingual Bridge Translation Entry Point
 *
 * Main JavaScript file that bootstraps the translation feature for ACF fields.
 *
 * Architecture:
 * 1. React App: Manages the translation modal (state, API calls, UI)
 * 2. DOM Manipulation: Injects translation buttons into ACF field labels
 * 3. Event Bus: Custom events coordinate between React and vanilla JS
 *
 * Event Flow:
 * - User clicks translate icon → triggers 'open-translation-modal' event
 * - React modal opens, loads original text, allows translation
 * - User clicks "Use Translation" → triggers 'save-translation' event
 * - Event handler updates ACF field value in DOM
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
import { copyOriginalToField, createTranslationButton } from './utils/fields';
import { updateACFField } from './utils/api';

/**
 * Main React App Component
 *
 * Manages modal visibility and data via React state.
 * Listens for custom events from translation buttons.
 *
 * @return {JSX.Element} TranslationModal component
 */
const TranslationApp = () => {
	// Modal visibility state
	const [isModalOpen, setIsModalOpen] = useState(false);

	// Modal data (field information passed when opening modal)
	const [modalData, setModalData] = useState(null);

	/**
	 * Effect: Listen for modal open events from translation buttons
	 *
	 * When user clicks translation icon on ACF field, button dispatches
	 * a custom event with field data. This listener opens the modal.
	 */
	useEffect(() => {
		const handleOpenModal = (event) => {
			setModalData(event.detail);
			setIsModalOpen(true);
		};

		// Listen for custom event
		document.addEventListener(
			'multilingual-bridge:open-translation-modal',
			handleOpenModal
		);

		// Cleanup on unmount
		return () => {
			document.removeEventListener(
				'multilingual-bridge:open-translation-modal',
				handleOpenModal
			);
		};
	}, []);

	/**
	 * Close modal and clear data
	 */
	const closeModal = () => {
		setIsModalOpen(false);
		setModalData(null);
	};

	// Render modal component
	return createElement(TranslationModal, {
		isOpen: isModalOpen,
		onClose: closeModal,
		modalData,
	});
};

/**
 * Initialize translation buttons for ACF fields
 *
 * Scans DOM for fields marked as translatable (by PHP ACF_Translation class)
 * and injects translation action buttons into their labels.
 *
 * Called on:
 * - Page load (DOMContentLoaded)
 * - ACF 'ready' action (when ACF initializes)
 * - ACF 'append' action (when new fields added dynamically, e.g., repeater rows)
 */
function initializeACFTranslationButtons() {
	// Find all translatable fields (marked by PHP with data attributes)
	const translatableFields = document.querySelectorAll(
		'.multilingual-translatable-field'
	);

	translatableFields.forEach(function (fieldWrapper) {
		// Skip if button already exists (prevent duplicates)
		if (fieldWrapper.querySelector('.multilingual-bridge-translate-btn')) {
			return;
		}

		// Find the label element to append buttons to
		const labelElement = fieldWrapper.querySelector('.acf-label label');
		if (!labelElement) {
			return;
		}

		// Extract field data from data attributes (set by PHP)
		const fieldData = {
			fieldKey: fieldWrapper.getAttribute('data-field-key'),
			fieldLabel: fieldWrapper.getAttribute('data-field-label'),
			postId: fieldWrapper.getAttribute('data-post-id'),
			sourceLang: fieldWrapper.getAttribute('data-source-lang'),
			targetLang: fieldWrapper.getAttribute('data-target-lang'),
			fieldType: fieldWrapper.getAttribute('data-field-type'),
		};

		// Create button group with translate and copy icons
		const button = createTranslationButton(
			fieldData,

			// Callback: Open translation modal
			(data) => {
				const event = new CustomEvent(
					'multilingual-bridge:open-translation-modal',
					{ detail: data }
				);
				document.dispatchEvent(event);
			},

			// Callback: Copy original text directly to field
			copyOriginalToField
		);

		// Inject button into field label
		labelElement.appendChild(button);
	});
}

/**
 * Bootstrap Application
 *
 * Runs when DOM is ready. Sets up:
 * 1. React app for modal
 * 2. Translation buttons on ACF fields
 * 3. Event listener for saving translations
 */
document.addEventListener('DOMContentLoaded', function () {
	// Only run on pages with ACF
	// eslint-disable-next-line no-undef
	if (typeof acf === 'undefined') {
		return;
	}

	// Create container for React modal
	const modalContainer = document.createElement('div');
	modalContainer.id = 'multilingual-bridge-react-modal';
	document.body.appendChild(modalContainer);

	// Render React app into container
	const root = createRoot(modalContainer);
	root.render(createElement(TranslationApp));

	// Initialize translation buttons on page load
	initializeACFTranslationButtons();

	// Re-initialize when ACF loads new fields dynamically
	// eslint-disable-next-line no-undef
	if (typeof acf !== 'undefined' && acf.addAction) {
		// When ACF initializes all fields
		// eslint-disable-next-line no-undef
		acf.addAction('ready', initializeACFTranslationButtons);

		// When ACF appends new fields (repeater rows, flexible content, etc.)
		// eslint-disable-next-line no-undef
		acf.addAction('append', initializeACFTranslationButtons);
	}

	/**
	 * Event Listener: Save translation to ACF field
	 *
	 * Listens for save event from modal. Updates the ACF field
	 * value in the DOM and triggers change events.
	 */
	document.addEventListener(
		'multilingual-bridge:save-translation',
		function (event) {
			const { fieldKey, value } = event.detail;
			updateACFField(fieldKey, value);
		}
	);
});
