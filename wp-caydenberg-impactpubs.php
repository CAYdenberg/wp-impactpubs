<?php
/*
Plugin Name: ImpactPubs
Description: Display a list of publications with badges from ImpactStory.
Version: 2.2
Author: Casey A. Ydenberg
Author URI: www.brittle-star.com
*/

/*
 Copyright 2013 by Casey A. Ydenberg (email: ydenberg@gmail.com)
 
 This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/*~~~~~~~~~~~~~~~~~~~~~~
WORDPRESS HOOKS
~~~~~~~~~~~~~~~~~~~~~~*/
register_activation_hook(__FILE__, 'impactpubs_install');
register_deactivation_hook(__FILE__, 'impactpubs_uninstall');

add_action( 'wp_enqueue_scripts', 'impactpubs_scripts' );
add_action( 'admin_enqueue_scripts', 'impactpubs_scripts' );

add_action('admin_menu', 'impactpubs_create_menu');

add_action('impactpubs_weekly_update', 'impactpubs_update_lists');

add_shortcode('publications', 'impactpubs_display_pubs');

//installation procedures: 
//schedule weekly event to update publication lists
function impactpubs_install() {
	wp_schedule_event( current_time( 'timestamp' ), 'weekly', 'impactpubs_weekly_update' );
}

//uninstallation procedures:
//remove scheduled tasks
function impactpubs_uninstall() {
	wp_clear_scheduled_hook( 'impactpubs_weekly_update' );
}

//add javascript and stylesheets to both the admin page and front-end.
//hooked by 'wp_enqueue_scripts' and 'admin_enqueue_scripts'
function impactpubs_scripts() {
	wp_enqueue_style( 'ip_style', plugins_url( 'ip_style.css', __FILE__ ) );
	wp_enqueue_script( 'ip_script', plugins_url( 'ip_script.js' , __FILE__) );
}

//create the admin menu
//hooked by admin_menu event
function impactpubs_create_menu() {
	add_menu_page('My ImpactStory Publication Retrieval Information', 'My Pubs', 
	'edit_posts', __FILE__, 'impactpubs_settings_form');
}


//create and handle the settings form
//hooked by impactpubs_create_menu
function impactpubs_settings_form() {
	$user_ob = wp_get_current_user();
	$user = $user_ob->ID;
	$valid = '';
	//process a form submission if it has occured
	if ( isset($_POST['submit'] ) ) {
		check_admin_referer( 'impactpubs_nonce' );
		$pubsource = $_POST['impactpubs_pubsource'];
		$identifier = $_POST['impactpubs_identifier'];
		$is_key = $_POST['impactpubs_impactstory_key'];
		//keep track of validation errors
		$valid .= impactpubs_validate_pubsource($pubsource);
		$valid .= impactpubs_validate_identifier($identifier, $pubsource);
		$valid .= impactpubs_validate_is_key($is_key);
		if ( $valid == '' ) {
			if ( !add_user_meta($user, '_impactpubs_pubsource', $pubsource, TRUE ) ) {
				update_user_meta($user, '_impactpubs_pubsource', $pubsource);
			}
			if ( !add_user_meta($user, '_impactpubs_identifier', $identifier, TRUE ) ) {
				update_user_meta($user, '_impactpubs_identifier', $identifier );
			}
			if ( !add_user_meta($user, '_impactpubs_is_key', $is_key, TRUE ) ) {
				update_user_meta($user, '_impactpubs_is_key', $is_key);
			}
		}
	} else {
		//if no NEW data has been submitted, use values from the database as defaults in the form
		$pubsource = get_user_meta( $user, '_impactpubs_pubsource', TRUE );
		if ( $pubsource == '') $pubsource = 'pubmed';
		$identifier = get_user_meta( $user, '_impactpubs_identifier', TRUE );
		$is_key = get_user_meta( $user, '_impactpubs_is_key', TRUE );
		$identifier = stripslashes($identifier);
	}
	?>
	<div class = "wrap">
		<h2>ImpactPubs Settings</h2>
		
		<?php if ( $valid != '' ){
			echo "<h2>Oops, looks like there was a problem:<br />$valid</h2>";
		}
		?>
		
		<form method = "POST" id = "impactpubsForm">
			<?php wp_nonce_field( 'impactpubs_nonce' ); ?>
			<table>
				<tr>
					<td><label for = "impactpubs_pub_source">Publication source</label></td>
					<td><input type = "radio" name = "impactpubs_pubsource" value = "pubmed" 
					<?php if ( $pubsource == 'pubmed' ) echo 'checked'; ?> />PubMed<br />				
					<input type = "radio" name = "impactpubs_pubsource" value = "orcid" 
					<?php if ( $pubsource == 'orcid' ) echo 'checked'; ?> />ORCiD
				</tr>
				
				<tr>
					<td><label for = "impactpubs_identifier">Identifier</label></td>
					<td><input type = "text" name = "impactpubs_identifier" 
					value = "<?php echo esc_attr__( $identifier ); ?>"></td>
				</tr>
				
				<tr>
				<td><label for = "impactpubs_impactstory_key">ImpactStory API key</label></td>
				<td><input type = "text" name = "impactpubs_impactstory_key" 
				value = "<?php echo esc_attr__( $is_key ); ?>"></td>
				</tr>
				
				<tr>
					<td></td><td>
					<input type = "submit" name = "submit" value = "Save Settings" class = "button-primary" /></td>
				</tr>
			</table>
		</form>	
	</div>
	
	<div class = "wrap">
		<h2>When you type <i>[publications name=<?php echo $user_ob->user_login; ?>]</i>,
		the following will be shown:</h2>
			
		<?php
			$impactpubs = new impactpubs_publist($user, $is_key );
			if ( $pubsource == 'pubmed' ) {
				$impactpubs->import_from_pubmed( $identifier );	
			} else if ( $pubsource == 'orcid' ) {
				$impactpubs->import_from_orcid( $identifier );
			}
			echo $impactpubs->make_html();
			$impactpubs->write_to_db();
		?>
	</div>
	
	<?php
}

/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
hooked by impactpubs_weekly_update

Update the publications lists for all users
Pull out their pubsource, identifier, and IS key
from the metadata, create an impactpubs object, 
run the import, and write to db 
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/
function impactpubs_update_lists(){
	$users = get_users( array() );
	foreach ( $users as $user ){
		$id = $user->ID;
		$pubsource = get_user_meta( $id, '_impactpubs_pubsource', TRUE);
		//if no entry in meta database (user has not set up their profile), 
		//then skip to the next one
		if ( $pubsource == '' ) continue;
		$identifier = get_user_meta( $id, '_impactpubs_pubsource', TRUE);
		$is_key = get_user_meta( $id, '_impactpubs_is_key', TRUE);
		$impactpubs = new impactpubs_publist( $id, $is_key );
		if ( $pubsource == 'pubmed' ) $impactspubs->import_from_pubmed( $identifier );
		if ( $pubsource == 'orcid' ) $impactspubs->import_from_orcid( $identifier );
		//only write to the database if data was retrieved (in case of problems in search)
		if ( count( $impactpubs->papers ) > 0 ) $impactpubs->write_to_db();
	}
}



/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
hooked by shortcode [publications]

Shortcode attributes:
name=string (refers to user/login name)
If no name is provided the admin/first user created (ID = 1) will be selected
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/
function impactpubs_display_pubs($shortcode_atts) {
	global $wpdb;
	//Set defaults and extract shortcode attributes
	$name = '';
	if ( isset( $shortcode_atts['name'] ) ) $name = $shortcode_atts['name'];
	//find the user ID number
	//by default find info for user with ID = 1
	if ( $name == '' ) $user_id = 1;
	else {
		$user = $wpdb->get_row("SELECT * FROM $wpdb->users WHERE user_login = '$name'", ARRAY_A);
		//cover possibility of an unrecognized user_login
		if ( !$user ) return 'Publication information not available';
		else $user_id = $user['ID'];
	}	
	$result = $wpdb->get_row("
		SELECT * FROM $wpdb->usermeta 
		WHERE user_id = $user_id 
		AND meta_key = '_impactpubs_html' ", ARRAY_A);
	if ($result) return $result['meta_value'];
	else return 'Publication information not available';
}



/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
object impactpubs_publist(string $user, string $is_key)
Properties: 
user - the current user's name, 
papers - an array of papers belonging to that user, 
is_key - the impactstory key for that user (optional)

Methods:
import_from_pubmed(string $pubmed_query)
import_from_orcid(string $orcid_id)
make_html($key)

Declared by:
impactpub_settings_form
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/

class impactpubs_publist {
	public $usr, $papers = array(), $is_key;
	function __construct($usr, $is_key = ''){
		$this->usr = $usr;
		if ( $is_key != '' ) $this->is_key = $is_key;
	}
	/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	Retrieve paper properties from a publication list.
	import_from_pubmed(string $pubmed_query). 
	Because PubMed does not have unique identifiers for authors, this is usually
	a search string that will pull up pubs from the author in question. 
	eg: 
	* ydenberg
	* ydenberg CA[author] 
	* ydenberg CA[author] AND (princeton[affiliation] OR brandeis[affiliation])
	Assigns paper properties to the child objects of class paper.
	Called by: impactpub_settings_form()
	Calls:
	impactpub_author_format()
	~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/
	function import_from_pubmed($pubmed_query) {
		//format the author string with "%20" in place of a space to make a remote call
		$pubmed_query= preg_replace("/[\s\+]/", "\%20", $pubmed_query);
		//build the url for the initial search
		$search = "http://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=pubmed&term=".$pubmed_query
		."&retmax=1000&retmode=xml";
		//build the url for subsequent call to esummary to retrieve the records
		$retrieve = "http://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?db=pubmed&id=";
		//make a call to pubmeds esearch utility, to retrieve pmids associated with an authors name or
		//other search
		if ( !$result = file_get_contents($search) ) die('There was a problem getting data from PubMed');
		//open a new DOM and dump the results from esearch
		$dom = new DOMDocument();
		$dom->loadXML($result);
		//pmid numbers are stored in a tag called "Id". Get all of these, then loop through them, adding each one
		//to the url that will be sent to esummary
		$ids = $dom->getElementsByTagName('Id');
		//check that publications have been found
		if ($ids) { //if no ids are found, the function will output "No publications".
			foreach ($ids as $id){
				//build the URL to retrieve individual records
				$retrieve = $retrieve.$id->nodeValue.",";
				//the ending ",", if present, doesn't seem to have any adverse effects
			}
			//make a second call to pubmed's esummary utility
			$result = file_get_contents($retrieve);
			//load the results into a DOM, then retrieve the DocSum tags, which represent each paper that was found
			$dom->loadXML($result);
			$papers = $dom->getElementsByTagName('DocSum');
			$paper_num = 0;
			foreach ($papers as $paper){
				$this->papers[$paper_num] = new impactpubs_paper();
				//id_types will be assigned as pmid in each case 
				$this->papers[$paper_num]->id_type = 'pmid';
				//get the id number associated with the record
				$this->papers[$paper_num]->id = $paper->getElementsByTagName('Id')->item(0)->nodeValue; 
				//initialize values of the data we want to get from the XML
				//authors and year need further manipulation and are not immediately declared in the Object
				$authors = array();
				$year = 0;
		/*the relevent fields (except for the pmid) are stored in tags of the style <Item Name="Journal">Nature</Item>.
		Since PHP does not have a method for getting XML data by attribute name, we have to get all the nodes of type
		"Item", then pick out the ones with relevent name. We do this by calling "getAttribute("Name")" and then comparing
		it to the Names for the data we're interested in.*/
				$items = $paper->getElementsByTagName("Item");
				foreach ($items as $item){
					$datatype = $item->getAttribute("Name");
					switch ($datatype){
						case "Author": $authors[] = $item->nodeValue;
							break;
						case "PubDate": $year = $item->nodeValue;
							break;
						case "Title": $this->papers[$paper_num]->title = $item->nodeValue;
							break;
						case "Source": $this->papers[$paper_num]->journal = $item->nodeValue;
							break;
						case "Volume": $this->papers[$paper_num]->volume = $item->nodeValue;
							break;
						case "Issue": $this->papers[$paper_num]->issue = $item->nodeValue;
							break;
						case "Pages": $this->papers[$paper_num]->pages = $item->nodeValue;
							break;
					} //end switch
				} //end inner foreach
				//the date includes year and month. Strip them out. 
				$this->papers[$paper_num]->year = substr($year, 0, 4);
				//format the authors list
				$this->papers[$paper_num]->authors = impactpubs_author_format($authors);
				$this->papers[$paper_num]->url = 'http://www.ncbi.nlm.nih.gov/pubmed/'.$this->papers[$paper_num]->id;
				$paper_num++;
			}
		}				
	}
	/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	Retrieve paper properties from a publication list.
	import_from_orcid(string $orcid_id). This is a 16 digit number linking to an ORCID user's profile.
	eg 0000-0003-1419-2405
	Assigns paper properties to the child objects of class paper.
	Called by: impactpub_settings_form()
	Calls:
	impactpub_author_format()
	impactpub_parse_bibtex()
	~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/
	function import_from_orcid($orcid_id){
		$retrieve = 'http://pub.orcid.org/'.$orcid_id.'/orcid-works';
		if ( !$result = file_get_contents($retrieve) ) die('There was a problem getting data from ORCiD');
		$dom = new DOMDocument();
		$dom->loadXML($result);
		//works are objects on the XML DOM representing each item in an ORCID user's profile
		$works = $dom->getElementsByTagName('orcid-work');
		$paper_num = 0;
		//loop through the works
		foreach ($works as $work) {
			//create a new paper to store properties in
			//using $paper_num as a key
			$this->papers[$paper_num] = new impactpubs_paper();
			//Most of the needed info is stored in the XML node 'citation'
			$citation = $work->getElementsByTagName('citation')->item(0)->nodeValue;
			$citation_type = $work->getElementsByTagName('work-citation-type')->item(0)->nodeValue;
			//$id_type will store the external identifier type: pmid, doi, or url. Other types may be possible
			//Some works do not have these so we inialize to false
			$id_type = FALSE;
			if ( isset($work->getElementsByTagName('work-external-identifier-type')->item(0)->nodeValue) ){
				$id_type = $work->getElementsByTagName('work-external-identifier-type')->item(0)->nodeValue;
				if ($id_type == 'pmid') {
					$this->papers[$paper_num]->id_type = 'pmid';
					$this->papers[$paper_num]->id = $work->getElementsByTagName('work-external-identifier-id')->item(0)->nodevalue;	
					$this->papers[$paper_num]->url = 'http://www.ncbi.nlm.nih.gov/pubmed/'.$pmid;
				} elseif ($id_type == 'doi') {
					$this->papers[$paper_num]->id_type = 'doi';
					$this->papers[$paper_num]->id = $work->getElementsByTagName('work-external-identifier-id')->item(0)->nodeValue;
					$this->papers[$paper_num]->url = 'http://dx.doi.org/'.
					$work->getElementsByTagName('work-external-identifier-id')->item(0)->nodeValue;
				} elseif ($url = $work->getElementsByTagName('url')->item(0)->nodevalue) {
					$this->papers[$paper_num]->id_type = 'url';
					$this->papers[$paper_num]->id = $url;
					$this->papers[$paper_num]->url = $url;
				}
			}
			if ($citation_type == 'bibtex'){
				//initialize to avoid indexing errors
				$bibtex_arr = array( 
					'author' => '',
					'year' => '',
					'title' => '',
				);
				$bibtex_arr = array_merge( $bibtex_arr, impactpubs_parse_bibtex($citation) );
				//some bibtex authors fields are stored with whitespace or semicolons trailing
				//take those off
				$authors = trim( $bibtex_arr['author'] );
				$authors = trim( $authors, ';,' );
				//'and' is often used between every author ... create an array and then
				//format back to a single string to get rid of this
				$authors_arr = explode( 'and', $authors );
				$this->papers[$paper_num]->authors = impactpubs_author_format($authors_arr, FALSE);
				$this->papers[$paper_num]->year = $bibtex_arr['year'];
				$this->papers[$paper_num]->title = $bibtex_arr['title'];
				//Storing either 'journal' or 'publisher' info in the 'journal' field
				if ( isset( $bibtex_arr['journal'] ) ) $this->papers[$paper_num]->journal = $bibtex_arr['journal'];
				elseif ( isset ( $bibtex_arr['publisher'] ) ) $this->papers[$paper_num]->journal = $bibtex_arr['publisher'];
				//vol, number and pages are all "optional". Have created fallbacks to deal with the lack of any/all
				if ( isset( $bibtex_arr['volume'] ) ) $this->papers[$paper_num]->volume = $bibtex_arr['volume'];
				if ( isset( $bibtex_arr['number'] ) ) $this->papers[$paper_num]->issue = $bibtex_arr['number'];
				if ( isset( $bibtex_arr['pages'] ) ) $this->papers[$paper_num]->pages = $bibtex_arr['pages'];
				//last ditch attempt to find doi and url in the bibtex info if they haven't been found above.
				if ( $id_type == FALSE && isset( $bibtex_arr['doi'] ) ) {
					$this->papers[$paper_num]->id_type = 'doi';
					$this->papers[$paper_num]->id = $bibtex_arr['doi'];
					$this->papers[$paper_num]->url = 'http://dx.doi.org/'.$bibtex_arr['doi'];
				} elseif ( $id_type == FALSE && isset( $bibtex_arr['url'] ) ) {
					$this->papers[$paper_num]->id_type = 'url';
					$this->papers[$paper_num]->id = $bibtex_arr['url'];
					$this->papers[$paper_num]->url = $bibtex_arr['url'];
				}
			}
			//other possible citation type fallbacks here
			$paper_num++;
		} 
	}
	/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	string $html make_html()
	creates the HTML for a publication list.
	Called by impactpub_settings_form()
	Calls impactpubs_paper->make_html()
	~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/
	function make_html(){
		if ( !count( $this->papers ) ) return 'No publications';
		$html = '';
		if ($this->is_key) $html .= '<script type="text/javascript" src="http://impactstory.org/embed/v1/impactstory.js"></script>';
		foreach ($this->papers as $paper){
			$html .= $paper->make_html($this->is_key);
		}
		return $html;
	}
	/*~~~~~~~~~~~~~~~~~~~~~~~~~~
	write_to_db()
	Writes the html (only) of the retrieved search as metadata
	Called by impactpubs_settings_for()
	~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/
	function write_to_db(){
		$user = $this->usr;
		$value = $this->make_html();
		if ( !add_user_meta($user, '_impactpubs_html', $value, TRUE ) ) {
			update_user_meta($user, '_impactpubs_html', $value);
		}
	}
}

/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
object impactpubs_paper(string $user, string $is_key)
Properties: self-explanatory

Methods:
make_html(string $key)
Key is the impactstory key, which is passed from the parent 
impactpubs_publist->make_html method because it is associated
with a user, not a paper

Declared by:
impactpub_publist->import_from_pubmed()
impactpub_publist->import_from_orcid()
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/
class impactpubs_paper {
	public $id_type, $id, $authors, $year, $title, $volume, $issue, $pages, $url;
	/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	string html make_html(string $key) where $key is an impactstory key
	Creates an HTML formatted string based on the properties of a paper.
	Each paper is present as a <p>, with class "publication" and a unique id for CSS styling.
	Could use a list, but formatting looks better this way without doing any CSS 
	(easier for end-users, IMO).
	Each element of the publication (authors, year, title, etc.) is present as a seperate span with
	a distinct class.
	Called by:
	impactpubs_publist->make_html()
	~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/
	function make_html($key = 0){
		$html = '<p class = "publication" id = "'.$this->id.'"><span class = "authors">'.$this->authors.'</span>, ';
		$html .= '<span class = "date">'.$this->year.'</span>. <span class = "title"><a href = "'.$this->url.'">';
		$html .= $this->title.'</a></span> <span class = "journal">'.$this->journal.'</span>';
		//if both a volume and an issue are present, format as : 152(4):3572-1380
		if ($this->volume && $this->issue && $this->pages) {
			$html .= ' <span class = "vol">'.$this->volume.'('.$this->issue.'):'.$this->pages.'</span>';
		} //if no issue is present, format as 152:3572-1380
		else if ($this->volume && $this->pages) {
			$html .= ' <span class = "vol">'.$this->volume.':'.$this->pages.'</span>';
		} else if ($this->volume) {
			$html .= ' <span class = "vol">'.$this->volume.'</span>.';
		} else { //if no volume or issue, assume online publication
			$html .= ".";
		}
		if ($key) {
			$html .= '<span class = "impactstory-embed" data-show-logo = "false" data-id = "'.$this->id.'"';
			$html .= 'data-id-type = "'.$this->id_type.'" data-api-key="'.$key.'">';
		}
		$html .= "</p>";
		return $html;
	}
}


/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
string $authors impactpubs_author_format(array $authors[, boolean $lastnamefirst])

Called by: 
impactpubs_publist->import_from_pubmed()
impactpubs_publist->import_from_orcid()

Takes an array of author names and returns a nicely formatted string.
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/
function impactpubs_author_format($authors){
	$output = "";
	foreach ($authors as $author){
		$author = trim($author); 
		$output = $output.", ".$author;
	}
	$output = trim($output, ';,');
	return $output;
}

/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
array $bibtex_elements parse_bibtex(string $bibtex_string)

Called by:
impactpubs_publist->import_from_orcid()
impactpubs_parse_bibtex()

Calls:
impactpubs_parse_bibtex()

An example of a bibtex formatted string is:
@misc{dataciteafc902fa-dd50-4c54-9b93-5e773a62f76f, doi = {10.6084/M9.FIGSHARE.681737}, url = {http://dx.doi.org/10.6084/M9.FIGSHARE.681737}, author = {Martin Fenner, Jennifer Lin; }, publisher = {Figshare}, title = {Article-Level Metrics Hannover Medical School}, year = {2013} }
This function looks for an opening '{', then performs a loop to find the paired '}'. It strips out specials chars, and 
then takes everything before the '{' as the key. Everything between the braces is the value.
When it encounters another opening '{', it strips the string down to the latest comma, and sends the new string 
to another iteration of the same function.
Key-value pairs are stored in the array $extracted, and this array is returned. When another instance of parse_bibtex is called,
the returned values are joined. 
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/

function impactpubs_parse_bibtex($bibtex_str){
	//initialize the array that will be returned
	$extracted = array();
	//find the first {
	$begin = strpos($bibtex_str, '{');
	//the key is the string preceding the {, striped of some special chars
	$key = substr($bibtex_str, 0, $begin);
	$key = preg_replace('/[\@\s\=]/', '', $key);
	//isolate the rest of the string (past the {)
	$therest = substr($bibtex_str, $begin + 1);
	//loop through $therest char by char to find the paired }
	$position = 0;
	$last_comma = 0;
	$level = -1;
	$str_length = strlen($therest);
	while ( $level < 0 && $position < $str_length ) {
		$character = substr( $therest, $position, 1);
		if ( $character == ',' ){
			//we keep track of the last comma to isolate other key-value pairs
			$last_comma = $position;
		}
		if ( $character == '{' ){
			$level--;
			//isolate the new string from the last occurance of a comma
			$new_string = substr($therest, $last_comma + 1);
			//call impactpubs_parse_bibtex again on the new string
			$extracted = array_merge( $extracted, impactpubs_parse_bibtex($new_string) );
		} else if ( $character == '}'){
			$level++;
		}
		$position++;
	}
	$value = substr($therest, 0, $position - 1);
	$extracted[$key] = $value;
	//isolate everything beyond the key-value pair
	//$therest = substr( $therest, $position );
	//this might not be necessary ...
	//$extracted = array_merge( $extracted, impactpubs_parse_bibtex($therest) );
	return $extracted;
}

/*~~~~~~~~~~~~~~~~~~~~~~~~~
string validation impactpubs_validate_pubsource(string $value)
Called by: impactpubs_settings_form()
~~~~~~~~~~~~~~~~~~~~~~~~~*/
function impactpubs_validate_pubsource($value){
	$valid = FALSE;
	if ( $value == 'pubmed' ) $valid = TRUE;
	if ( $value == 'orcid') $valid = TRUE;
	if ( $valid ) return '';
	else return 'Invalid publication source supplied';
}

/*~~~~~~~~~~~~~~~~~~~~~~~~~
string validation impactpubs_validate_identifier(string $value, string $pubsource)
Called by: impactpubs_settings_form()
~~~~~~~~~~~~~~~~~~~~~~~~~*/
function impactpubs_validate_identifier($value, $pubsource = 'orcid'){
	if ( $pubsource == 'orcid' ) {
		//allowed characters are numbers and the dash (-) symbol
		if ( preg_match('/[^0-9A-Za-z\-]/', $value) ) {
			return 'Invalid ORCiD key';
		} else {
			return '';
		}
	} else {
		//for pubmed, just excluding ;, quotes, escape char to prevetn injection
		if ( preg_match('/[\;\"\'\\\]/', $value) ) {
			return 'Invalid Pubmed search string';
		} else {
			return '';		
		}
	}
}

/*~~~~~~~~~~~~~~~~~~~~~~~~~
string validation impactpubs_validate_is_key(string $value)
Called by: impactpubs_settings_form()
Letters, numbers, and the - are allowed
~~~~~~~~~~~~~~~~~~~~~~~~~*/
function impactpubs_validate_is_key($value){
	//impactstory key contains only letters, numbers, and the dash (-) symbol
	if ( preg_match('/[^A-Za-z0-9\-]/', $value) ) {
		return 'Invalid ImpactStory API key';	
	} else {
		return '';
	}
}

?>
