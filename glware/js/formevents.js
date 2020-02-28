$(function() {
	console.log('why?');
	$(document).on('af_complete', function(e, response) {
		console.log('hewwo', response);
	});
});
