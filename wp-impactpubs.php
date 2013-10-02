<?php
/*
Plugin Name: ImpactPubs
Description: Display a list of publications with badges from ImpactStory.
Version: 2.5
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

add_action('impactpubs_daily_update', 'impactpubs_update_lists');

add_shortcode('publications', 'impactpubs_display_pubs');

//installation procedures: 
//schedule daily event to update publication lists
function impactpubs_install() {
	wp_schedule_event( current_time( 'timestamp' ), 'daily', 'impactpubs_daily_update' );
}

//uninstallation procedures:
//remove scheduled tasks
function impactpubs_uninstall() {
	wp_clear_scheduled_hook( 'impactpubs_daily_update' );
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
	add_menu_page('My ImpactPubs Publication Retrieval Information', 'My Publications', 
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
					<td><i>For ORCiD, this is a 16-digit number (e.g. 0000-0003-1419-2405).<br>
				For PubMed, enter a unique query string (e.g. Ydenberg CA AND (Brandeis[affiliation] OR Princeton[affiliation]))</i></td>
				</tr>
				
				<tr>
				<td><label for = "impactpubs_impactstory_key">ImpactStory API key</label><br>
				<i>(Optional)</i></td>
				<td><input type = "text" name = "impactpubs_impactstory_key" 
				value = "<?php echo esc_attr__( $is_key ); ?>"></td>
				<td><i>Email <a href = "mailto:team@impactstory.org">team@impactstory.org</a> to request your <strong>free</strong> API key</i></td>
				</tr>
				
				<tr>
					<td></td><td>
					<input type = "submit" name = "submit" value = "Save Settings" class = "button-primary" /></td>
				</tr>
			</table>
		</form>	
	</div>
	
	<div class = "wrap" id = "impactpubs_wrapper">		
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
hooked by impactpubs_daily_update

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
		$identifier = get_user_meta( $id, '_impactpubs_identifier', TRUE);
		$is_key = get_user_meta( $id, '_impactpubs_is_key', TRUE);
		$impactpubs = new impactpubs_publist( $id, $is_key );
		if ( $pubsource == 'pubmed' ) $impactpubs->import_from_pubmed( $identifier );
		if ( $pubsource == 'orcid' ) $impactpubs->import_from_orcid( $identifier );
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
		$pubmed_query = preg_replace("/[\s\+]/", "%20", $pubmed_query);
		//build the url for the initial search
		$search = "http://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=pubmed&term=".$pubmed_query
		."&retmax=1000&retmode=xml";
		//build the url for subsequent call to esummary to retrieve the records
		$retrieve = "http://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?db=pubmed&id=";
		//make a call to pubmeds esearch utility, to retrieve pmids associated with an authors name or
		//other search
		$result = wp_remote_retrieve_body( wp_remote_get($search) );
		if ( !$result ) die('There was a problem getting data from PubMed');
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
			$result = wp_remote_retrieve_body( wp_remote_get($retrieve) );
			if ( !$result ) die('There was a problem getting data from PubMed');
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
	impactpubs_author_format()
	impactpubs_parse_bibtex()
	~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/
	function import_from_orcid($orcid_id){
		$search = 'http://feed.labs.orcid-eu.org/'.$orcid_id.'.json';
		$result = wp_remote_retrieve_body( wp_remote_get($search) );
		if ( !$result ) die('There was a problem getting data from ORCiD');
		$works = json_decode($result);
		$paper_num = 0;
		foreach ($works as $work){
			$listing = new impactpubs_paper();
			//get the publication year (essential)
			if ( isset($work->issued->{'date-parts'}[0][0]) ) {
				$listing->year = $work->issued->{'date-parts'}[0][0];
			} else {
				continue;
			}
			//get the title (essential)
			if ( isset($work->title) ) {
				$listing->title = $work->title;	
			} else {
				continue;
			}
			//get the journal/publisher/book series (essential)
			if ( isset( $work->{'container-title'} ) ) {
				$listing->journal = $work->{'container-title'};
			} else if ( isset($work->publisher) ) {
				$listing->journal = $work->publisher;
			} else {
				continue;
			}
			//get the authors list (essential)
			if ( isset($work->author) ) {
				$authors_arr = array();
				foreach ($work->author as $author_ob) {
					$authors_arr[] = $author_ob->family.', '.$author_ob->given;
				}
				$listing->authors = impactpubs_author_format($authors_arr);
			} else {
				continue;
			}
			//get volume, issue, and pages (not essential)
			if ( isset($work->volume) ) $listing->volume = $work->volume;
			if ( isset($work->issue) )  $listing->issue =  $work->issue;
			if ( isset($work->page) )  $listing->pages =  $work->page;
			//get the unique identifier
			if ( isset($work->URL) && isset($work->DOI) ) {
				$listing->id_type = 'doi';
				$listing->id = $work->DOI;
				$listing->url = $work->URL;
			} elseif ( isset($work->DOI) ) {
				$listing->id_type = 'doi';
				$listing->id = $work->DOI;
				$listing->url = 'http://dx.doi.org/'.$work->DOI;
			} elseif ( isset($work->URL) ) {
				$listing->id_type = 'url';
				$listing->id = $work->URL;
				$listing->url = $work->URL;
			} else {
				$listing->url = '';
			}
			$this->papers[$paper_num] = new impactpubs_paper();
			$this->papers[$paper_num] = $listing;
			unset($listing);
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
		if ($this->is_key) {
			$html .= '<script type="text/javascript" src="http://impactstory.org/embed/v1/impactstory.js"></script>';
		}
		foreach ($this->papers as $paper){
			$html .= $paper->make_html($this->is_key);
		}
		if ($this->is_key) {
			$html .= '<p class = "impactpubs_footnote"><i>Badges provided by ImpactStory. <a href = "http://www.impactstory.org">Learn more about altmetrics</a></i></p>';
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
	public $id_type, $id, $authors, $year, $title, $volume, $issue, $pages, $url, $full_citation;
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
		$html = '<p class = "impactpubs_publication" id = "'.$this->id.'">';
		if ( isset($this->full_citation) ){
			echo $this->full_citation;
		} else {
			$html .= '<span class = "authors">'.$this->authors.'</span>, ';
			$html .= '<span class = "date">'.$this->year.'</span>. <span class = "title"><a href = "'.$this->url.'">';
			$html .= $this->title.'</a></span> <span class = "journal">'.$this->journal.'</span>';
			//if both a volume and an issue are present, format as : 152(4):3572-1380
			if ($this->volume && $this->issue && $this->pages) {
				$html .= ' <span class = "vol">'.$this->volume.'('.$this->issue.'):'.$this->pages.'</span>';
			} //if no issue is present, format as 152:3572-1380
			elseif ($this->volume && $this->pages) {
				$html .= ' <span class = "vol">'.$this->volume.':'.$this->pages.'</span>';
			} elseif ($this->volume) {
				$html .= ' <span class = "vol">'.$this->volume.'</span>.';
			} else { //if no volume or issue, assume online publication
				$html .= ".";
			}
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
		$output = $output."; ".$author;
	}
	$output = trim($output, ';,');
	return $output;
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
		//for pubmed, just excluding ;, quotes, escape char to prevent injection
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
