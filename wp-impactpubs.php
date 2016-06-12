<?php
/*
Plugin Name: ImpactPubs
Plugin URI: www.mylabwebsite.com
=======
Description: Display a list of publications with badges from ImpactStory.
Version: 3.3.1
Author: Casey A. Ydenberg
Author URI: www.caseyy.org
*/

/*
 Copyright 2016 by Casey A. Ydenberg (email: ydenberg@gmail.com)

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

require_once( plugin_dir_path(__FILE__).'ip_import_engine.php' );

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
  wp_enqueue_script( 'ip_script', plugins_url( 'ip_script.js', __FILE__ ), array('jquery'), null, true );
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
    $display = $_POST['impactpubs_display-mode'];
		//for impactstory searches, remove all whitespace from the identifier
		if ( $pubsource == 'impactstory' ) {
			$identifier = preg_replace( '/\s+/', '', $identifier );
		}
		//keep track of validation errors
		$valid .= impactpubs_validate_pubsource($pubsource);
		$valid .= impactpubs_validate_identifier($identifier, $pubsource);
		if ( $valid === '' ) {
      update_user_meta($user, '_impactpubs_pubsource', $pubsource);
      update_user_meta($user, '_impactpubs_identifier', $identifier );
      update_user_meta($user, '_impactpubs_display-mode', $display);
		}
	} else {
		//if no NEW data has been submitted, use values from the database as defaults in the form
		$pubsource = get_user_meta( $user, '_impactpubs_pubsource', TRUE ) ?: 'pubmed';
		$identifier = stripslashes(get_user_meta( $user, '_impactpubs_identifier', TRUE ));
		$display = get_user_meta( $user, '_impactpubs_display-mode', TRUE) ?: 'single-page';
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
					<td>Publication source</td>
					<td>
            <input type="radio" name="impactpubs_pubsource" value="pubmed" id="impactpubs_pubsource_pubmed"
					    <?php if ( $pubsource === 'pubmed' ) echo 'checked'; ?> />
            <label for="impactpubs_pubsource_pubmed">PubMed</label>
            <br />
            <input type="radio" name="impactpubs_pubsource" value="orcid" id="impactpubs_pubsource_orcid"
					    <?php if ( $pubsource === 'orcid' ) echo 'checked'; ?> />
            <label for="impactpubs_pubsource_orcid">ORCiD
            <br />
            <input type="radio" name="impactpubs_pubsource" value="impactstory" id="impactpubs_pubsource_impactstory"
					    <?php if ( $pubsource === 'impactstory' ) echo 'checked'; ?> />
            <label for="impactpubs_pubsource_impactstory">ImpactStory</label>
          </td>
				</tr>

				<tr>
					<td><label for="impactpubs_identifier">Identifier</label></td>
					<td>
            <input type="text" name="impactpubs_identifier" id="impactpubs_identifier"
					       value = "<?php echo esc_attr__( $identifier ); ?>">
          </td>
					<td><i>For ORCiD, this is a 16-digit number (e.g. 0000-0003-1419-2405).<br>
						For PubMed, enter a unique query string (e.g. Ydenberg CA AND (Brandeis[affiliation] OR Princeton[affiliation]).<br />
						For ImpactStory, this is generally your name as it appears in the URL: www.impactstory.com/user/<b>YourName</b>/
						</i></td>
				</tr>

        <tr>
          <td>Display Mode</td>
          <td>
            <input type="radio" name="impactpubs_display-mode" value="single-page" id="impactpubs_display-mode_single-page"
              <?php if ( $display !== 'by-year' ) echo 'checked'; ?> />
            <label for="impactpubs_display-mode_single-page">Single Page</label>
            <br />
            <input type="radio" name="impactpubs_display-mode" value="by-year" id="impactpubs_display-mode_by-year"
              <?php if ( $display === 'by-year' ) echo 'checked'; ?> />
            <label for="impactpubs_display-mode_by-year">By Year</label>
          </td>
        </tr>

				<tr>
					<td></td><td>
					<input type = "submit" name = "submit" value = "Save Settings" class = "button-primary" /></td>
				</tr>
			</table>
		</form>
	</div>

	<div class = "wrap" id = "impactpubs_wrapper">
		<?php
			$impactpubs = new impactpubs_publist( $user, $display );
			try {
				$impactpubs->import( $pubsource, $identifier );
			} catch (Exception $e) {
				echo '<h2>Warning: There was a problem getting data from the source. The remote server may be down, or there might be an error somewhere. Please try again in a few minutes.';
				exit();
			}

			if ( $html = $impactpubs->make_html() ) {
				echo '<h2>When you type <i>[publications name='.$user_ob->user_login.']</i>, the following will be shown:</h2>';
				echo $html;
				if ( !add_user_meta($user, '_impactpubs_html', $html, TRUE ) ) {
					update_user_meta($user, '_impactpubs_html', $html);
				}
			} else {
				echo '<h2>Search came up empty. Sure you have the right identifier?</h2>';
			}

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
		$impactpubs = new impactpubs_publist( $id );
		try {
			$impactpubs->import( $pubsource, $identifier );
		} catch (Exception $e) {
			//this is a quiet death, since it will occur during a cron
			die();
		}
		//only write to the database if data was retrieved (in case of problems in search)
		if ( $html = $impactpubs->make_html() ) {
			if ( !add_user_meta($user, '_impactpubs_html', $html, TRUE ) ) {
				update_user_meta($user, '_impactpubs_html', $html);
			}
		}
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

?>
