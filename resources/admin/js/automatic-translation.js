/**
 * Automatic Translation Widget Entry Point
 *
 * Bootstraps the React-based automatic translation widget that allows
 * translating post meta to multiple target languages at once.
 *
 * Architecture:
 * 1. React App: Manages translation state, language selection, and API calls
 * 2. Custom Hook: useAutomaticTranslation handles translation logic
 * 3. WordPress Components: Uses @wordpress/components for UI consistency
 *
 * @package
 */

import { createElement, createRoot } from '@wordpress/element';
import { AutomaticTranslationWidget } from './components/AutomaticTranslationWidget';

/**
 * Bootstrap Application
 *
 * Runs when DOM is ready. Mounts the React app into the widget container.
 */
document.addEventListener('DOMContentLoaded', function () {
	// Find widget container in DOM
	const widgetContainer = document.getElementById(
		'multilingual-bridge-automatic-widget'
	);

	// Only initialize if widget exists on page
	if (!widgetContainer) {
		return;
	}

	// Get widget data from PHP (passed via data attributes)
	const widgetData = widgetContainer.dataset;

	// Parse data attributes
	const postId = parseInt(widgetData.postId, 10);
	const sourceLanguage = widgetData.sourceLanguage;
	const targetLanguages = JSON.parse(widgetData.targetLanguages || '{}');
	const translations = JSON.parse(widgetData.translations || '{}');

	// Get edit post URL from localized script
	const editPostUrl =
		window.multilingualBridgeAuto?.editPostUrl ||
		'/wp-admin/post.php?post=POST_ID&action=edit';

	// Render React widget
	const root = createRoot(widgetContainer);
	root.render(
		createElement(AutomaticTranslationWidget, {
			postId,
			sourceLanguage,
			targetLanguages,
			translations,
			editPostUrl,
		})
	);
});
