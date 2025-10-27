/**
 * Bulk Translation Entry Point
 *
 * Initializes the bulk translation feature for ACF fields.
 * Renders the BulkTranslationModal React component and manages button interactions.
 *
 * @package
 */

import {
	createElement,
	createRoot,
	useState,
	useEffect,
} from '@wordpress/element';
import { BulkTranslationModal } from './components/BulkTranslationModal';

const BulkTranslationApp = () => {
	const [isModalOpen, setIsModalOpen] = useState(false);
	const [modalData, setModalData] = useState(null);

	useEffect(() => {
		const handleOpenModal = (event) => {
			setModalData(event.detail);
			setIsModalOpen(true);
		};

		document.addEventListener(
			'multilingual-bridge:open-bulk-translation-modal',
			handleOpenModal
		);

		return () => {
			document.removeEventListener(
				'multilingual-bridge:open-bulk-translation-modal',
				handleOpenModal
			);
		};
	}, []);

	const closeModal = () => {
		setIsModalOpen(false);
		setModalData(null);
	};

	return createElement(BulkTranslationModal, {
		isOpen: isModalOpen,
		onClose: closeModal,
		modalData,
	});
};

document.addEventListener('DOMContentLoaded', function () {
	const button = document.getElementById(
		'multilingual-bridge-bulk-translate'
	);

	if (!button) {
		return;
	}

	const modalContainer = document.getElementById(
		'multilingual-bridge-bulk-translate-modal'
	);

	if (!modalContainer) {
		return;
	}

	const root = createRoot(modalContainer);
	root.render(createElement(BulkTranslationApp));

	button.addEventListener('click', (e) => {
		e.preventDefault();

		const postId = parseInt(button.getAttribute('data-post-id'));
		const sourceLang = button.getAttribute('data-source-lang');
		const targetLang = button.getAttribute('data-target-lang');

		const event = new CustomEvent(
			'multilingual-bridge:open-bulk-translation-modal',
			{
				detail: {
					postId,
					sourceLang,
					targetLang,
				},
			}
		);
		document.dispatchEvent(event);
	});
});
