/* Ordre d'affichage : glisser-déposer + flèches ▲▼ (dans le même groupe). */
(function ($) {
	'use strict';
	$(function () {
		var $form = $('#if-order-form');
		if (!$form.length) {
			return;
		}

		$form.find('.if-order-list').sortable({
			handle: '.if-order-handle',
			items: '> li',
			placeholder: 'if-order-placeholder',
			axis: 'y'
		});

		$form.on('click', '.if-up', function () {
			var $li = $(this).closest('li');
			$li.prev('li').before($li);
		});
		$form.on('click', '.if-down', function () {
			var $li = $(this).closest('li');
			$li.next('li').after($li);
		});

		$form.on('submit', function () {
			var ids = [];
			$form.find('li[data-id]').each(function () {
				ids.push($(this).data('id'));
			});
			$('#if-order-data').val(ids.join(','));
		});
	});
})(jQuery);
