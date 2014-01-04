//javascript document

jQuery(document).ready(function() {
	//reset the line-height of publications paragraphs
	//to 1.5 * the font size, if font is large.
	fs_str = jQuery('.impactpubs_publication').css('font-size');
	fs_int = parseInt(fs_str);
	if ( fs_int * 1.5 > 23 ) {
		lh_int = fs_int * 1.5;
		lh_str = lh_int + 'px'
		jQuery('.impactpubs_publication').css('line-height', lh_str);
	}
	
})