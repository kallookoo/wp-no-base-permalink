jQuery(document).ready(function($){
	function check_input_tag(){
		if($('input#disabled-tag-base').is(':checked')){
			$('label[for^="old-tag-redirect"]').parent().parent().show();
		} else {
			$('label[for^="old-tag-redirect"]').parent().parent().hide();
		}
	};
	check_input_tag();
	$('input#disabled-tag-base').click(function(){
		check_input_tag();
	});
});
