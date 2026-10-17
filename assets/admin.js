/* Lignes répétables + choix de PDF dans la médiathèque. */
(function ($) {
	'use strict';

	$(document).on('click', '.if-add', function () {
		var id = $(this).data('target');
		var html = $('#' + id).html().replace(/__i__/g, 'n' + Date.now());
		$('.if-repeat[data-template="' + id + '"]').append(html);
	});

	$(document).on('click', '.if-remove', function () {
		$(this).closest('.if-row').remove();
	});

	$(document).on('click', '.if-pick-file', function (e) {
		e.preventDefault();
		if (typeof wp === 'undefined' || !wp.media) {
			return;
		}
		var row = $(this).closest('.if-row');
		var frame = wp.media({
			title: 'Choisir un PDF',
			library: { type: 'application/pdf' },
			button: { text: 'Utiliser ce PDF' },
			multiple: false
		});
		frame.on('select', function () {
			var file = frame.state().get('selection').first().toJSON();
			row.find('.if-attachment-id').val(file.id);
			row.find('.if-file-name').text(file.filename);
			var title = row.find('input[name$="[title]"]');
			if (!title.val()) {
				title.val(file.title);
			}
		});
		frame.open();
	});

	$(document).on('click', '.if-pick-video', function (e) {
		e.preventDefault();
		if (typeof wp === 'undefined' || !wp.media) {
			return;
		}
		var row = $(this).closest('.if-row');
		var frame = wp.media({
			title: 'Choisir ou téléverser une vidéo',
			library: { type: 'video' },
			button: { text: 'Utiliser cette vidéo' },
			multiple: false
		});
		frame.on('select', function () {
			var file = frame.state().get('selection').first().toJSON();
			row.find('.if-video-url').val(file.url);
			var title = row.find('input[name$="[title]"]');
			if (!title.val()) {
				title.val(file.title);
			}
		});
		frame.open();
	});
})(jQuery);
