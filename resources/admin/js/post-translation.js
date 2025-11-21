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
 *
 * @package
 */

import { createElement, createRoot } from '@wordpress/element';
import { Tooltip } from '@wordpress/components';
import { PostTranslationWidget } from './components/PostTranslationWidget';

/**
 * Add pending update indicators to ACF fields
 *
 * Wraps the entire field label in a yellow badge with warning icon.
 * Uses WordPress Tooltip component for proper positioning and accessibility.
 */
function addPendingUpdateIndicators() {
	// Get localized strings
	const strings = window.multilingualBridgePost?.strings || {};
	const tooltipText =
		strings.pendingUpdateTooltip ||
		'This field has translation updates from the source language';

	// Find all ACF fields with pending updates
	const pendingFields = document.querySelectorAll(
		'.acf-field.mlb-pending-update'
	);

	pendingFields.forEach((field) => {
		// Check if indicator already exists
		if (field.querySelector('.mlb-pending-badge-container')) {
			return;
		}

		// Find the label element
		const label = field.querySelector('.acf-label label');
		if (!label) {
			return;
		}

		// Get the original label text
		const labelText = label.textContent.trim();

		// Clear the label content
		label.textContent = '';

		// Create container for React component
		const container = document.createElement('span');
		container.className = 'mlb-pending-badge-container';

		// Append container to label
		label.appendChild(container);

		// Create React root and render Tooltip component wrapping entire badge
		const root = createRoot(container);
		root.render(
			createElement(
				Tooltip,
				{ text: tooltipText },
				createElement(
					'span',
					{
						className: 'mlb-pending-badge',
						'aria-label': tooltipText,
					},
					createElement('span', {
						className: 'dashicons dashicons-warning',
						'aria-hidden': 'true',
					}),
					labelText
				)
			)
		);
	});
}

/**
 * Bootstrap Application
 *
 * Runs when DOM is ready. Mounts the React app into the widget container.
 */
document.addEventListener('DOMContentLoaded', function () {
	// Get edit post URL from localized script
	const editPostUrl =
		window.multilingualBridgePost?.editPostUrl ||
		'/wp-admin/post.php?post=POST_ID&action=edit';

	// Find full widget container (source posts)
	const widgetContainer = document.getElementById(
		'multilingual-bridge-post-widget'
	);

	if (widgetContainer) {
		// Get widget data from PHP (passed via data attributes)
		const widgetData = widgetContainer.dataset;

		// Parse data attributes
		const postId = parseInt(widgetData.postId, 10);
		const sourceLanguage = widgetData.sourceLanguage;
		const targetLanguages = JSON.parse(widgetData.targetLanguages || '{}');
		const translations = JSON.parse(widgetData.translations || '{}');
		const translationsPending = JSON.parse(
			widgetData.translationsPending || '{}'
		);

		// Render React widget
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

	// Find navigation widget container (translated posts)
	const navContainer = document.getElementById(
		'multilingual-bridge-post-widget-nav'
	);

	if (navContainer) {
		// Get widget data from PHP
		const navData = navContainer.dataset;

		// Parse data attributes
		const postId = parseInt(navData.postId, 10);
		const currentLanguage = navData.currentLanguage;
		const availableLanguages = JSON.parse(
			navData.availableLanguages || '{}'
		);
		const translations = JSON.parse(navData.translations || '{}');

		// Render navigation-only widget
		const root = createRoot(navContainer);
		root.render(
			createElement(PostTranslationWidget, {
				postId,
				sourceLanguage: currentLanguage,
				targetLanguages: {},
				translations,
				translationsPending: {},
				editPostUrl,
				isNavigation: true,
				availableLanguages,
			})
		);
	}

	// Add pending update indicators to ACF fields
	addPendingUpdateIndicators();

	// Re-add indicators when ACF fields are loaded/updated (e.g., flexible content, repeaters)
	if (window.acf) {
		window.acf.addAction('ready', addPendingUpdateIndicators);
		window.acf.addAction('append', addPendingUpdateIndicators);
		window.acf.addAction('load', addPendingUpdateIndicators);
	}
});
