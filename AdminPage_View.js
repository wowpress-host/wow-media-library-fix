jQuery(function($) {
	var notices_count = 0;
	var ajax_timeout = null;



	var checkbox_value = function(selector) {
		return $(selector).is(':checked') ? 'true' : 'false';
	};



	var show_if = function(selector, show) {
		$(selector).css('display', (show ? '' : 'none'));
	};



	var state_set = function(mode) {
		if (mode)
			wow_mlf_state = mode;

		var mode = wow_mlf_state;

		show_if('#wow_mlf_start_outer',	mode == 'start');
		show_if('#wow_mlf_restart_outer', mode == 'done');
		show_if('#wow_mlf_continue_outer', mode == 'paused' || mode == 'failed');
		show_if('#wow_mlf_config', mode == 'start');
		show_if('#wow_mlf_process',
			mode == 'working' || mode == 'paused' || mode == 'done' ||
			mode == 'failed');
		show_if('#wow_mlf_working_now', mode == 'working');
		show_if('#wow_mlf_working_outer', mode == 'working');
		show_if('#wow_mlf_done', mode == 'done');
		show_if('#wow_mlf_failed', mode == 'failed');
	}



	var ajax_timeout_clear = function() {
		if (ajax_timeout != null) {
			clearTimeout(ajax_timeout);
			ajax_timeout = null;
		}
	};



	var step = function(extras) {
		extras.action = 'wow_media_library_fix_process';
		extras._wpnonce = wow_media_library_fix_nonce;

		var react_to_result = true;
		var react_to_failure = function() {
			if (!react_to_result)
				return;

			react_to_result = false;

			if (!extras.failed_attempt)
				extras.failed_attempt = 0;

			extras.failed_attempt++;

			if (extras.failed_attempt > 5)
				step_failed('Repeating timeouts during server request');
			else
				step({failed_attempt: extras.failed_attempt});
		};


		ajax_timeout_clear();
		ajax_timeout = setTimeout(react_to_failure, 15000);

		jQuery.post({
			url: ajaxurl,
			data: extras,
			dataType: 'json',
			error: react_to_failure,
			success: function(data) {
				if (!react_to_result)
					return;
				if (!data || !data.status)
					return react_to_failure();

				ajax_timeout_clear();

				$('#wow_mlf_total').html(data.posts_all);
				$('#wow_mlf_processed').html(data.posts_processed);

				$('#wow_mlf_unreferenced_files_processed_outer').css('display',
					data.unreferenced_files_processed > 0 ? '' : 'none');
				$('#wow_mlf_unreferenced_files_processed').html(
					data.unreferenced_files_processed);

				$('#wow_mlf_errors').html(data.errors_count);
				$('#wow_mlf_error_database').css('display',
					data.error_database ? '' : 'none');
				$('#wow_mlf_now').html(data.last_processed_description);

				notices_add(data.new_notices);

				if (data.status == 'working_posts' ||
					data.status == 'working_index_files') {
					if (wow_mlf_state == 'working') {
						step({});
					}
				} else if (data.status == 'done') {
					step_done();
				} else {
					step_failed({responseText: 'unknown status ' . data.status});
				}
			}
		});
	};



	var notices_add = function(notices) {
		if (!notices || !notices.length)
			return;
		if (notices_count > 10000)
			return;

		for (var n = 0; n < notices.length; n++) {
			var i = notices[n];
			notices_count++;
			var notice = $('<div>');
			if (i.post_id) {
				notice.append($('<a>')
					.prop('href', 'post.php?action=edit&post=' + Number(i.post_id))
					.text(i.post_id));
				notice.append($('<span>').text(': '));
			}
			notice.append($('<span>').text(i.message));

			$('#wow_mlf_notices').prepend(notice);
		}
		if (notices_count > 10000) {
			$('#wow_mlf_notices').prepend(
				$( '<div>Too many log entries. ' +
					'Stop logging to avoid browser crash. ' +
					'Consider log to file instead.</div>' ));
		}
	};



	var step_done = function() {
		state_set('done');
	};



	var step_failed = function(error) {
		$('#wow_mlf_notices').prepend(
			$('<div>').text('Request failed: ' +
				(error.statusText ? error.statusText : '') +
				' ' +
				(error.responseText ? error.responseText.substr(0, 500) : '' )));

		state_set('failed');
	};



	$('.wow_mlf_show_config').click(function(e) {
		e.preventDefault();
		state_set('start');
	});



	$('.wow_mlf_start').click(function(e) {
		e.preventDefault();
		state_set('working');
		$('html, body').animate({ scrollTop: 0 }, "slow");
		$('#wow_mlf_total').html('starting...');
		$('#wow_mlf_processed').html('0');
		$('#wow_mlf_errors').html('0');
		$('#wow_mlf_error_database').css('display', 'none');
		$('#wow_mlf_now').html('');
		$('#wow_mlf_notices').html('');
		notices_count = 0;

		var log_to = $('input[name="wow_mlf_config_log_to"]:checked').val();
		if (log_to == 'file')
			$('#wow_mlf_notices').prepend($('<div>Logging to file selected</div>'));

		step({
			'wmlf_action': 'start',
			'guid':
				$('input[name="wow_mlf_config_guid"]:checked').val(),
			'posts_delete_with_missing_images':
				checkbox_value('#wow_mlf_config_posts_delete_with_missing_images'),
			'posts_delete_duplicate_url':
				$('input[name="wow_mlf_config_posts_delete_duplicate_url"]:checked').val(),
			'files_thumbnails':
				$('input[name="wow_mlf_config_files_thumbnails"]:checked').val(),
			'files_weak_references':
				$('input[name="wow_mlf_config_files_weak_references"]:checked').val(),
			'files_unreferenced':
				$('input[name="wow_mlf_config_files_unreferenced"]:checked').val(),
			'regenerate_metadata':
				checkbox_value('#wow_mlf_config_regenerate_metadata'),
			'log_to': log_to,
			'log_verbose': checkbox_value('#wow_mlf_config_log_verbose')
		});
	});

	$('#wow_mlf_continue').click(function(e) {
		e.preventDefault();
		state_set('working');

		step({'wmlf_action': 'continue'});
	});

	$('#wow_mlf_stop').click(function(e) {
		e.preventDefault();
		state_set('paused');
	});
});
