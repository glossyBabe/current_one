$(function() {
	$("a.glw_action_clean").on("click", function(e) {
		e.preventDefault();
		var url = document.location.href,
			workUrl = "/third_party/glware/ajhandler.php";
		$.post(workUrl, {glw_action: "clean_all_data"}, function() {
			document.location.href = url;
		});
	});
});

		
