/**
 * Config Sync admin page JavaScript.
 *
 * Handles export, import, diff preview, and ZIP transfer operations
 * via the REST API and admin-ajax endpoints.
 *
 * @package ConfigSync
 * @since   1.0.0
 */

/* global jQuery, configSyncAdmin */
(function ($) {
	'use strict';

	var api = {
		/**
		 * Make a REST API request.
		 *
		 * @param {string} endpoint REST endpoint path (relative to rest URL).
		 * @param {string} method   HTTP method.
		 * @param {Object} data     Optional request body.
		 * @return {jQuery.Deferred}
		 */
		request: function (endpoint, method, data) {
			return $.ajax({
				url: configSyncAdmin.restUrl + endpoint,
				method: method,
				contentType: 'application/json',
				data: data ? JSON.stringify(data) : undefined,
				beforeSend: function (xhr) {
					xhr.setRequestHeader('X-WP-Nonce', configSyncAdmin.restNonce);
				}
			});
		}
	};

	/**
	 * Show a notice in the notices area.
	 *
	 * @param {string} message Notice text.
	 * @param {string} type    'success', 'error', or 'info'.
	 */
	function showNotice(message, type) {
		var cssClass = 'notice notice-' + type + ' is-dismissible';
		var $notice = $('<div class="' + cssClass + '"><p>' + escapeHtml(message) + '</p></div>');
		$('#syncforge-notices').html($notice);

		// WordPress notice dismissal.
		if (typeof wp !== 'undefined' && wp.notices) {
			wp.notices.removeDismissible();
		}
	}

	/**
	 * Escape HTML entities in a string.
	 *
	 * @param {string} str Raw string.
	 * @return {string} Escaped string.
	 */
	function escapeHtml(str) {
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str));
		return div.innerHTML;
	}

	/**
	 * Truncate a value for display.
	 *
	 * @param {*}      val    The value to truncate.
	 * @param {number} maxLen Maximum character length.
	 * @return {string}
	 */
	function truncateValue(val, maxLen) {
		if (val === null || typeof val === 'undefined') {
			return '(empty)';
		}
		var str = typeof val === 'object' ? JSON.stringify(val) : String(val);
		if (str.length > (maxLen || 120)) {
			return str.substring(0, maxLen || 120) + '...';
		}
		return str;
	}

	/**
	 * Render a diff result into the diff section.
	 *
	 * @param {Object} providers Provider-keyed diff data.
	 */
	function renderDiff(providers) {
		var $section = $('#syncforge-diff-section');
		var $content = $('#syncforge-diff-content');
		$content.empty();

		var hasChanges = false;

		$.each(providers, function (providerId, changes) {
			if (!changes || !changes.length) {
				return;
			}
			hasChanges = true;

			var $group = $('<div class="syncforge-provider-group"></div>');
			$group.append('<h3>' + escapeHtml(providerId) + '</h3>');

			var $table = $(
				'<table class="syncforge-diff-table">' +
				'<thead><tr>' +
				'<th>Type</th><th>Key</th><th>Current (DB)</th><th>YAML (File)</th>' +
				'</tr></thead><tbody></tbody></table>'
			);

			$.each(changes, function (_, change) {
				var rowClass = '';
				var typeLabel = change.type || 'unknown';

				if (change.type === 'added') {
					rowClass = 'diff-added';
				} else if (change.type === 'removed') {
					rowClass = 'diff-removed';
				} else if (change.type === 'changed') {
					rowClass = 'diff-changed';
				}

				$table.find('tbody').append(
					'<tr class="' + rowClass + '">' +
					'<td>' + escapeHtml(typeLabel) + '</td>' +
					'<td><code>' + escapeHtml(change.key || '') + '</code></td>' +
					'<td class="diff-value">' + escapeHtml(truncateValue(change.old)) + '</td>' +
					'<td class="diff-value">' + escapeHtml(truncateValue(change.new)) + '</td>' +
					'</tr>'
				);
			});

			$group.append($table);
			$content.append($group);
		});

		if (!hasChanges) {
			$content.html('<div class="syncforge-no-changes">No differences found. Database and YAML files are in sync.</div>');
			$('#syncforge-import').hide();
		} else {
			$('#syncforge-import').show();
		}

		$section.show();
	}

	/**
	 * Render operation results.
	 *
	 * @param {Object} data    Response data.
	 * @param {string} action  The action that was performed.
	 */
	function renderResults(data, action) {
		var $section = $('#syncforge-results');
		var $content = $('#syncforge-results-content');
		$content.empty();

		var $list = $('<ul></ul>');

		if (data.providers) {
			$.each(data.providers, function (pid, stats) {
				var detail = '';
				if (stats.files !== undefined) {
					detail = stats.files + ' file(s), ' + stats.items + ' item(s)';
				} else if (stats.updated !== undefined) {
					detail = stats.updated + ' updated, ' + stats.created + ' created, ' + stats.deleted + ' deleted, ' + stats.skipped + ' skipped';
				} else {
					detail = JSON.stringify(stats);
				}
				$list.append('<li><strong>' + escapeHtml(pid) + '</strong>: ' + escapeHtml(detail) + '</li>');
			});
		}

		if (data.errors && Object.keys(data.errors).length > 0) {
			$.each(data.errors, function (pid, msg) {
				$list.append('<li style="color:#d63638;"><strong>' + escapeHtml(pid) + '</strong>: ' + escapeHtml(msg) + '</li>');
			});
		}

		$content.append('<p><strong>' + escapeHtml(action) + ' complete.</strong></p>');
		$content.append($list);
		$section.show();
	}

	// Export button.
	$('#syncforge-export').on('click', function () {
		var $btn = $(this);
		$btn.prop('disabled', true).text(configSyncAdmin.i18n.exporting);

		api.request('export', 'POST').done(function (data) {
			showNotice('Export completed successfully.', 'success');
			renderResults(data, 'Export');
		}).fail(function (xhr) {
			var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Export failed.';
			showNotice(msg, 'error');
		}).always(function () {
			$btn.prop('disabled', false).text('Export to YAML');
		});
	});

	// Preview (dry-run import) button.
	$('#syncforge-preview').on('click', function () {
		var $btn = $(this);
		$btn.prop('disabled', true).text(configSyncAdmin.i18n.previewing);

		api.request('diff', 'GET').done(function (data) {
			renderDiff(data.providers || {});
		}).fail(function (xhr) {
			var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Preview failed.';
			showNotice(msg, 'error');
		}).always(function () {
			$btn.prop('disabled', false).text('Preview Changes');
		});
	});

	// Import button (shown after preview).
	$('#syncforge-import').on('click', function () {
		if (!window.confirm(configSyncAdmin.i18n.confirm)) {
			return;
		}

		var $btn = $(this);
		$btn.prop('disabled', true).text(configSyncAdmin.i18n.importing);

		api.request('import', 'POST').done(function (data) {
			showNotice('Import completed successfully.', 'success');
			renderResults(data, 'Import');
			$('#syncforge-diff-section').hide();
			$('#syncforge-import').hide();
		}).fail(function (xhr) {
			var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Import failed.';
			showNotice(msg, 'error');
		}).always(function () {
			$btn.prop('disabled', false).text('Apply Import');
		});
	});

	// ZIP export button.
	$('#syncforge-zip-export').on('click', function () {
		var $btn = $(this);
		$btn.prop('disabled', true).text('Generating...');

		// Use admin-ajax for file download (REST API cannot stream binary).
		var url = configSyncAdmin.ajaxUrl +
			'?action=config_sync_zip_export' +
			'&_wpnonce=' + encodeURIComponent(configSyncAdmin.ajaxNonce);

		// Create a temporary link to trigger download.
		var $link = $('<a></a>')
			.attr('href', url)
			.attr('download', 'syncforge-config.zip')
			.appendTo('body');

		$link[0].click();
		$link.remove();

		setTimeout(function () {
			$btn.prop('disabled', false).text('Download ZIP');
		}, 2000);
	});

	// ZIP file input enable/disable submit button.
	$('#syncforge-zip-file').on('change', function () {
		$('#syncforge-zip-import').prop('disabled', !this.files.length);
	});

	// ZIP import form submission.
	$('#syncforge-zip-import-form').on('submit', function (e) {
		e.preventDefault();

		var fileInput = document.getElementById('syncforge-zip-file');
		if (!fileInput.files.length) {
			showNotice('Please select a ZIP file first.', 'error');
			return;
		}

		var $btn = $('#syncforge-zip-import');
		$btn.prop('disabled', true).text(configSyncAdmin.i18n.uploading);

		var formData = new FormData();
		formData.append('action', 'config_sync_zip_import');
		formData.append('_wpnonce', configSyncAdmin.ajaxNonce);
		formData.append('config_zip', fileInput.files[0]);

		$.ajax({
			url: configSyncAdmin.ajaxUrl,
			method: 'POST',
			data: formData,
			processData: false,
			contentType: false
		}).done(function (response) {
			if (response.success) {
				showNotice('ZIP uploaded and extracted. Preview changes below, then apply import.', 'success');
				// Automatically load diff after upload.
				api.request('diff', 'GET').done(function (data) {
					renderDiff(data.providers || {});
				});
			} else {
				showNotice(response.data || 'ZIP upload failed.', 'error');
			}
		}).fail(function () {
			showNotice('ZIP upload failed. Check file size and format.', 'error');
		}).always(function () {
			$btn.prop('disabled', false).text('Upload & Preview');
			fileInput.value = '';
		});
	});

})(jQuery);
