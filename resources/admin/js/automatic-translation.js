/**
 * Automatic Translation Widget JavaScript
 *
 * Handles automatic translation of post meta to multiple languages
 */

(function ($) {
	'use strict';

	/**
	 * Automatic Translation Handler
	 */
	const AutomaticTranslation = {
		/**
		 * Initialize the handler
		 */
		init() {
			this.$widget = $('#multilingual-bridge-automatic-widget');
			this.$button = $('#mlb-generate-translation');
			this.$checkboxes = $('input[name="mlb_target_languages[]"]');
			this.$progress = $('.mlb-widget-progress');
			this.$progressBar = $('.mlb-progress-fill');
			this.$progressText = $('.mlb-progress-text');
			this.$results = $('.mlb-widget-results');

			this.bindEvents();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents() {
			this.$button.on('click', (e) => {
				e.preventDefault();
				this.handleTranslation();
			});
		},

		/**
		 * Handle translation button click
		 */
		async handleTranslation() {
			// Get selected languages
			const selectedLanguages = this.getSelectedLanguages();

		if (selectedLanguages.length === 0) {
			alert(multilingualBridgeAuto.strings.noLanguages);
			return;
		}

			// Disable button and show progress
			this.$button.prop('disabled', true);
			this.showProgress();
			this.$results.hide().empty();

			try {
				// Get post data
				const postId = parseInt(this.$button.data('post-id'), 10);
				const sourceLanguage = this.$button.data('source-language');

			// Update progress
			this.updateProgress(0, multilingualBridgeAuto.strings.translating);

			// Call API
			const result = await this.callAutomaticTranslateAPI(
					postId,
					selectedLanguages
				);

				// Show results
				this.showResults(result, selectedLanguages);
			} catch (error) {
				this.showError(error);
			} finally {
				this.$button.prop('disabled', false);
				this.hideProgress();
			}
		},

		/**
		 * Get selected target languages
		 *
		 * @return {Array} Array of language codes
		 */
		getSelectedLanguages() {
			const languages = [];

			this.$checkboxes.filter(':checked').each(function () {
				languages.push($(this).val());
			});

			return languages;
		},

	/**
	 * Call automatic translate REST API
	 *
	 * @param {number} postId Source post ID
	 * @param {Array} targetLanguages Array of target language codes
	 * @return {Promise} API response
	 */
	async callAutomaticTranslateAPI(postId, targetLanguages) {
		const response = await fetch(
			`${multilingualBridgeAuto.apiUrl}/automatic-translate`,
			{
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': multilingualBridgeAuto.nonce,
				},
					body: JSON.stringify({
						post_id: postId,
						target_languages: targetLanguages,
					}),
				}
			);

			if (!response.ok) {
				const error = await response.json();
				throw new Error(error.message || 'Translation failed');
			}

			return response.json();
		},

		/**
		 * Show progress bar
		 */
		showProgress() {
			this.$progress.slideDown(200);
		},

		/**
		 * Hide progress bar
		 */
		hideProgress() {
			this.$progress.slideUp(200);
		},

		/**
		 * Update progress bar
		 *
		 * @param {number} percent Progress percentage (0-100)
		 * @param {string} text Progress text
		 */
		updateProgress(percent, text) {
			this.$progressBar.css('width', `${percent}%`);
			this.$progressText.text(text);
		},

		/**
		 * Show translation results
		 *
		 * @param {Object} result API response
		 * @param {Array} selectedLanguages Selected language codes
		 */
		showResults(result, selectedLanguages) {
			const $resultsList = $('<div class="mlb-results-list"></div>');

		// Overall status
		const overallClass = result.success
			? 'mlb-result-success'
			: 'mlb-result-error';
		const overallMessage = result.success
			? multilingualBridgeAuto.strings.success
			: multilingualBridgeAuto.strings.partial;

			$resultsList.append(
				`<div class="mlb-result-overall ${overallClass}">
					<span class="dashicons ${
						result.success ? 'dashicons-yes-alt' : 'dashicons-warning'
					}"></span>
					<strong>${overallMessage}</strong>
				</div>`
			);

			// Individual language results
			selectedLanguages.forEach((langCode) => {
				const langResult = result.languages[langCode];
				const checkbox = this.$checkboxes.filter(
					`[value="${langCode}"]`
				);
				const langName = checkbox.data('language-name');

				if (langResult) {
					const statusClass = langResult.success
						? 'mlb-lang-success'
						: 'mlb-lang-error';
					const statusIcon = langResult.success
						? 'dashicons-yes-alt'
						: 'dashicons-dismiss';

					let statusText = '';
					if (langResult.success) {
						statusText = `${langResult.meta_translated} meta fields translated`;
						if (langResult.created_new) {
							statusText += ' (new post created)';
						}
					} else {
						statusText = langResult.errors.join(', ');
					}

					$resultsList.append(
						`<div class="mlb-result-language ${statusClass}">
							<span class="dashicons ${statusIcon}"></span>
							<strong>${langName}:</strong> ${statusText}
						</div>`
					);

					// Update checkbox status icon
					if (langResult.success && langResult.target_post_id > 0) {
						checkbox
							.closest('.mlb-language-item')
							.find('.mlb-translation-status')
							.removeClass('mlb-no-translation')
							.addClass('mlb-has-translation')
							.attr(
								'title',
								multilingualBridgeAuto.strings.success
							)
							.find('.dashicons')
							.removeClass('dashicons-marker')
							.addClass('dashicons-yes-alt');

						// Update data attributes
						checkbox
							.data('has-translation', '1')
							.data('translation-id', langResult.target_post_id)
							.attr('data-has-translation', '1')
							.attr(
								'data-translation-id',
								langResult.target_post_id
							);
					}
				}
			});

			this.$results.html($resultsList).slideDown(200);
		},

		/**
		 * Show error message
		 *
	 * @param {Error} error Error object
	 */
	showError(error) {
		const errorMessage = error.message || multilingualBridgeAuto.strings.error;

		this.$results
				.html(
					`<div class="mlb-result-overall mlb-result-error">
						<span class="dashicons dashicons-dismiss"></span>
						<strong>${errorMessage}</strong>
					</div>`
				)
				.slideDown(200);
		},
	};

	// Initialize when DOM is ready
	$(document).ready(function () {
		if ($('#multilingual-bridge-automatic-widget').length) {
			AutomaticTranslation.init();
		}
	});
})(jQuery);
