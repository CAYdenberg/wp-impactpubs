<?php

/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
object impactpubs_publist(string $user)
Properties: 
user - the current user's name, 
papers - an array of papers belonging to that user, 

Methods:
import_from_pubmed(string $pubmed_query)
import_from_orcid(string $orcid_id)
make_html()

Declared by:
impactpub_settings_form
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/

class impactpubs_publist {
	public $usr, $source, $papers = array();
	function __construct($usr){
		$this->usr = $usr;
	}
	/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	Switch-type method which calls the appropriate import 
	method based on the passed @pubsource.
	$pubsource -  string, either pubmed, impactstory or orcid
	$identifier - unique identifier for that pubsouce
	No return value.
	Throws an exception if the pubsource is not recognized.
	~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/
	function import( $pubsource, $identifier ) {
		$this->source = $pubsource;
		if ( $pubsource == 'pubmed' ) {
			$this->import_from_pubmed( $identifier );
		} else if ( $pubsource == 'orcid' ) {
			$this->import_from_orcid( $identifier );
		} else if ( $pubsource == 'impactstory' ) {
			$this->import_from_impactstory( $identifier );
		} else {
			throw new Exception('NoIdentifier');
		}
	}
	/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	Retrieve paper properties from a publication list.
	import_from_pubmed(string $pubmed_query). 
	No return value.
	Throws an exception if a connection to remote utility cannot be made.
	Because PubMed does not have unique identifiers for authors, this is usually
	a search string that will pull up pubs from the author in question. 
	eg: 
	* ydenberg
	* ydenberg CA[author] 
	* ydenberg CA[author] AND (princeton[affiliation] OR brandeis[affiliation])
	No return value
	Assigns paper properties to the child objects of class paper.
	Called by: impactpubs_publist::import()
	Calls: impactpub_author_format()
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
		$result = impactpubs_remote_call( $search );
		if ( !$result ) {
			throw new Exception('NoConnect');
		}
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
			$result = impactpubs_remote_call($retrieve);
			if ( !$result ) {
				throw new Exception('NoConnect');
			}
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
	import_from_orcid(string $orcid_id). 
	This is a 16 digit number linking to an ORCID user's profile.
	eg 0000-0003-1419-2405
	No return value.
	Throws an exception if a connection to ORCiD cannot be made.
	Assigns paper properties to the child objects of class paper.
	Called by: impactpub_settings_form()
	Calls:
	impactpubs_author_format()
	~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/
	function import_from_orcid($orcid_id){
		$search = 'http://feed.labs.orcid-eu.org/'.$orcid_id.'.json';
		$result = impactpubs_remote_call($search);
		if ( !$result ) {
			throw new Exception('NoConnect');
		}
		$works = json_decode($result);
		$paper_num = 0;
		foreach ($works as $work){
			$listing = new impactpubs_paper();
			//get the publication year (not essential)
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
			//get the journal/publisher/book series (not essential)
			if ( isset( $work->{'container-title'} ) ) {
				$listing->journal = $work->{'container-title'};
			} else if ( isset($work->publisher) ) {
				$listing->journal = $work->publisher;
			}
			//get the authors list (essential)
			if ( isset($work->author) ) {
				$authors_arr = array();
				foreach ($work->author as $author_ob) {
					if ( isset($author_ob->given) ) {
						$authors_arr[] = $author_ob->family.', '.$author_ob->given;
					} else {
						$authors_arr[] = $author_ob->family.', ';
					}
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
				$listing->id_type = '';
				$listing->id = '';
				$listing->url = '';
			}
			$this->papers[$paper_num] = new impactpubs_paper();
			$this->papers[$paper_num] = $listing;
			unset($listing);
			$paper_num++;
		} 
	}
	/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	no return import_from_impactstory( string $identifier )
	Throws an exception if a connection cannot be made.
	The identifier is URLified -  ie for Casey Ydenberg it should be CaseyYdenberg.
	The relevent form automatically strips out whitespace.
	~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/
	function import_from_impactstory( $identifier ) {
		$search = 'http://www.impactstory.org/user/'.$identifier.'/products';
		$result = impactpubs_remote_call($search);
		if ( !$result ) {
			throw new Exception('NoConnect');
		}
		$works = json_decode($result);
		$paper_num = 0;
		foreach ( $works as $work ) {
			$listing = new impactpubs_paper();
			//get the year
			if ( isset($work->biblio->year) ) {
				$listing->year = $work->biblio->year;
			}
			//get the title
			if ( isset($work->biblio->title) ) {
				$listing->title = $work->biblio->title;
			}
			//get the authors
			if ( isset($work->biblio->authors) ) {
				$listing->authors = $work->biblio->authors;
			}
			//get the url
			if ( isset($work->aliases->url[0]) ) {
				$listing->url = $work->aliases->url[0];
			}
			//get the journal
			if ( isset($work->biblio->journal) ) {
				$listing->journal = $work->biblio->journal;
			}
			//get the badges
			if ( isset($work->markup->metrics) ) {
				$listing->badges = $work->markup->metrics;
			}
			//get the ID
			if ( isset($work->aliases->doi[0]) ) {
				$listing->id_type = 'doi';
				$listing->id = $work->aliases->doi[0];
			} else if ( isset($work->aliases->pmid[0]) ) {
				$listing->id_type = 'pmid';
				$listing->id = $work->aliases->pmid[0];
			}
			$this->papers[$paper_num] = new impactpubs_paper();
			$this->papers[$paper_num] = $listing;
			unset($listing);
			$paper_num++;
		}
	}
	/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	no return ::sort_papers()
	Sorts papers in this class based on the year of publication.
	Calls: impactpubs_compare_papers()
	~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/
	function sort_papers() {
		usort($this->papers, 'impactpubs_compare_papers');
	}
	/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	string $html make_html()
	creates the HTML for a publication list.
	Called by impactpub_settings_form()
	Calls impactpubs_paper->make_html()
	~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/
	function make_html(){
		if ( !count( $this->papers ) ) return FALSE;
		$html = '';
		$this->sort_papers();
		foreach ($this->papers as $paper){
			$html .= $paper->make_html();
		}	
		//make sure the source has been set
		if ( !isset($this->source) ) {
			return $html;
		}
		$html .= '<p class = "impactpubs-footnote">Publication list retrieved from ';
		if ( $this->source == 'orcid' ) $html .= 
		'<a href = "http://orcid.org/">ORCiD</a>';
		else if ( $this->source == 'impactstory') $html .=
		'<a href = "http://impactstory.org/">ImpactStory</a>';
		else $html .= '<a href = "http://www.ncbi.nlm.nih.gov/books/NBK25500/">NCBI</a>';
		$html .= ' using <a href = "https://wordpress.org/plugins/impactpubs/">ImpactPubs</a></p>.';
		return $html;
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
	public $id_type, $id, $authors, $year, $title, $volume, $issue, $pages, $url, $full_citation, $badges;
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
	function make_html(){
		$html = '<p class = "impactpubs_publication" id = "'.$this->id.'">';
		if ( isset($this->full_citation) ){
			echo $this->full_citation;
		} else {
			
			//the authors
			if ( isset($this->authors) && $this->authors != '' ) {
				$html .= '<span class = "ip-authors">'.$this->authors.'</span>, ';
			}
			
			//the date (required)
			if ( isset($this->year) ) {
				$html .= '<span class = "ip-date">'.$this->year.'</span>. '; 	
			}
			//the title (required)
			$html .= '<span class = "ip-title">';
			if ($this->url != '') {
				$html .= '<a href = "'.$this->url.'">'.$this->title.'</a>';
			} else {
				$html .= $this->title.'</span>';
			}
			$html .= '</span> &nbsp';
			
			//the journal
			if ( isset($this->journal) ) {
				$html .= '<span class = "ip-journal">'.$this->journal.'</span>';
				//if both a volume and an issue are present, format as : 152(4):3572-1380
				if ($this->volume && $this->issue && $this->pages) {
					$html .= ' <span class = "ip-vol">'.$this->volume.'('.$this->issue.'):'.$this->pages.'</span>';
				} //if no issue is present, format as 152:3572-1380
				elseif ($this->volume && $this->pages) {
					$html .= ' <span class = "ip-vol">'.$this->volume.':'.$this->pages.'</span>';
				} elseif ($this->volume) {
					$html .= ' <span class = "ip-vol">'.$this->volume.'</span>.';
				} else { //if no volume or issue, assume online publication
					$html .= ".";
				}	
			}
			
			//the badges
			if ( isset ($this->badges) ) {
				$html .= $this->badges;
			}			
		}
		$html .= "</p>";
		return $html;
	}
}


/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
 * string page impactpubs_remote_call(string url)
 * Checks if the WP HTTP functions are installed.
 * If they are, uses WP to retrieve the page and returns the page body.
 * If not, uses file_get_contents and passes back the whole page.
 */
function impactpubs_remote_call($url) {
	if ( function_exists('wp_remote_retrieve_body') && function_exists('wp_remote_get') ) {
		return wp_remote_retrieve_body( wp_remote_get($url) );
	} else {
		try {
			return file_get_contents($url);
		} catch (Exception $e) {
			//final fallback included because some servers do not allow file_get_contents
			throw new Exception($e);
		}
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
string $validation impactpubs_validate_pubsource(string $value)
Called by: impactpubs_settings_form()
~~~~~~~~~~~~~~~~~~~~~~~~~*/
function impactpubs_validate_pubsource($value){
	$valid = FALSE;
	if ( $value == 'pubmed' ) $valid = TRUE;
	if ( $value == 'orcid') $valid = TRUE;
	if ( $value == 'impactstory' ) $valid = TRUE;
	if ( $valid ) return '';
	else return 'Invalid publication source supplied';
}

/*~~~~~~~~~~~~~~~~~~~~~~~~~
string $validation impactpubs_validate_identifier(string $value, string $pubsource)
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
	} else if ( $pubsource == 'pubmed' ){
		//for pubmed, just excluding ;, quotes, escape char to prevent injection
		if ( preg_match('/\<script/', $value) ) {
			return 'Invalid PubMed search';
		} else {
			return '';		
		}
	} else if ( $pubsource == 'impactstory' ) {
		if ( preg_match('/[^A-Za-z0-9]/', $value ) ) {
			return 'Invalid ImpactStory search';
		} else {
			return '';
		}
	}
}

/*~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
integer impactpubs_compare_papers(mixed $a, mixed $b)
usort callback function called by impactpubs_publist::sort_papers()
Designed to sort papers from newest to oldest (ie by year, in descending order)
Therefore $a and $b are intended to be integers; other values may result
in alphanumeric sorting.
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~*/
function impactpubs_compare_papers($a, $b) {
	if ( $a->year == $b->year ) {
		return 0;
	} else {
		return ( $a->year < $b->year ) ? 1 : -1;
	}
}

?>