//javascript document

jQuery(document).ready(function($) {
	$('.impactpubs_years-ctl').change(function(e) {
		var year = parseInt($(this).val(), 10);
		$('.impactpubs_publication').removeClass('impactpubs_notshown').each(function() {
			var pubyear = parseInt($(this).data('year'));
			if (pubyear !== year) {
				$(this).addClass('impactpubs_notshown');
			}
		});
	});

})
