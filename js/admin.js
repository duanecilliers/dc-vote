(function ($) {
	"use strict";
	$(function () {
		$('.reset-entry-votes').click( function(e) {
			return confirm( 'Are you sure you want to reset votes for this entry?' );
		});
		$('.reset-all-votes').click( function(e) {
			return confirm( 'Are you sure you wish to delete all vote records?' );
		});
	});
}(jQuery));
