/**
 * Post Translation Widget Entry Point
 *
 * Bootstraps the React-based post translation widget that allows
 * translating post meta to multiple target languages at once.
 *
 * Architecture:
 * 1. React App: Manages translation state, language selection, and API calls
 * 2. Custom Hook: usePostTranslation handles translation logic
 * 3. WordPress Components: Uses @wordpress/components for UI consistency
 * 4. ACF Field Highlighter: Highlights fields with pending translation updates
 *
 * @package
 */

import { createElement, createRoot } from '@wordpress/element';
import { PostTranslationWidget } from './components/PostTranslationWidget';
import { highlightPendingACFFields } from './utils/acf-field-highlighter';

/**
 * Bootstrap Application
 *
 * Runs when DOM is ready. Mounts the React app into the widget container.
 */
document.addEventListener('DOMContentLoaded', function () {
	// Find widget container in DOM
	const widgetContainer = document.getElementById(
		'multilingual-bridge-post-widget'
	);

	// Only initialize if widget exists on page
	if (widgetContainer) {
		// Get widget data from PHP (passed via data attributes)
		const widgetData = widgetContainer.dataset;

		// Parse data attributes
		const postId = parseInt(widgetData.postId, 10);
		const sourceLanguage = widgetData.sourceLanguage;
		const targetLanguages = JSON.parse(widgetData.targetLanguages || '{}');
		const translations = JSON.parse(widgetData.translations || '{}');
		const translationsPending = JSON.parse(widgetData.translationsPending || '{}');

		// Get edit post URL from localized script
		const editPostUrl =
			window.multilingualBridgePost?.editPostUrl ||
			'/wp-admin/post.php?post=POST_ID&action=edit';

		// Render React widget (appears on source posts only)
		const root = createRoot(widgetContainer);
		root.render(
			createElement(PostTranslationWidget, {
				postId,
				sourceLanguage,
				targetLanguages,
				translations,
				translationsPending,
				editPostUrl,
			})
		);
	}

	// Highlight ACF fields with pending updates (on translated posts only)
	const pendingMeta = window.multilingualBridgePost?.pendingMeta || [];
	if (pendingMeta.length > 0) {
		highlightPendingACFFields(pendingMeta);
	}
});
