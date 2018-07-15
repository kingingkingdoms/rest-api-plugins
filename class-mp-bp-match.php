<?php
class Mp_BP_Match {
	function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'mp_load_scripts' ), 10 );
		add_action( 'bp_include', array( $this, 'mp_bp_match_init' ), 100 );
	}
	
	/* Allow matching only if BuddyPress is active */
	function mp_bp_match_init() {
		require_once( 'src/class.settings-api.php' );
		require_once( 'mp-settings.php' );
		new WeDevs_Settings_API_Match();
		
		add_action( 'wp_footer', array( $this, 'hmk_script' ), 100 );
		add_action( 'bp_profile_header_meta', array( $this, 'hmk_show_matching_percentage' ), 10 );
		add_action( 'bp_directory_members_item', array( $this, 'hmk_matching_percentage_button' ), 200 );
		add_shortcode('mp_match_percentage', array( $this, 'mp_match_percentage_function' ) );
		add_action( 'wp_ajax_hmk_get_percentage', array( $this, 'hmk_get_percentage_function' ), 100 );
		add_action( 'wp_ajax_nopriv_hmk_get_percentage', array( $this, 'hmk_get_percentage_function' ), 100 );
	}
	
	/* Register the shortcode to allow the the percentage to be displayed on user's profiles */
	function mp_match_percentage_function() {
		$this->hmk_show_matching_percentage();
	}
	
	/* Load the css files */
	function mp_load_scripts() {
		wp_enqueue_style( 'hmk-style', plugins_url( 'src/css/style.css', __FILE__) );
	}
	
	/* Show the percent match on the button when users click it */
	function hmk_get_percentage_function() {
		$user_displayed = $_POST['hmk_uid'];
		$user_logged_in = get_current_user_id();
		$hmk_match_percentage = $this->hmk_get_matching_percentage_number( $user_displayed,$user_logged_in );
		if( $hmk_match_percentage >=0 ) {
			echo '<span class="hmk-member-match-percent">' . round( $hmk_match_percentage, 2 ) . '% Match to You</span>';
		}
		die;
	}
	
	/* Hide the button for logged-in users with the member type "Brand, Famous Person, Organization, or Government" and for displayed users with the member type "Brand, Famous Person, Organization, or Government. Show for everyone else. */
	function hmk_matching_percentage_button() {
		$user_displayed_id =  bp_get_member_user_id();
		$user_logged_in = get_current_user_id();
		if( $user_displayed_id == $user_logged_in )
			return;
		if( ! bp_has_member_type( $user_displayed_id, 'brand' ) && ( ! bp_has_member_type( $user_displayed_id, 'organization' ) && ( ! bp_has_member_type( $user_displayed_id, 'famous-person' ) && ( ! bp_has_member_type( $user_displayed_id, 'government' ) && ( ! bp_has_member_type( $user_logged_in, 'brand' ) && ( ! bp_has_member_type( $user_logged_in, 'organization' ) && ( ! bp_has_member_type( $user_logged_in, 'famous-person' ) && ( ! bp_has_member_type( $user_logged_in, 'government' ) ) ) ) ) ) ) ) ) {
			echo "<div class='hmk-trigger-match'><div id='user-$user_displayed_id' class='hmk-get-percent generic-button'> Calculate Match</div></div>";
		}
		return '';
	}
	
	/* Calculates the match percentage based on the number of xprofile fields matched and their percentage value */
	function hmk_get_matching_percentage_number( $user_displayed = '' , $user_logged_in = '' ) {
		global $wpdb;
		if( empty( $user_logged_in ) ) {
			$user_logged_in = get_current_user_id();
		}
		if( empty( $user_displayed ) ) {
			$user_displayed = bp_displayed_user_id();
		}
		
		$percentage_class = new WeDevs_Settings_API;
		$xprofile_table = $wpdb->prefix . 'bp_xprofile_fields';
		$sql = "SELECT id, name, type FROM $xprofile_table WHERE type ! = 'option'";
		$result = $wpdb->get_results( $sql ) or die( mysql_error() );
		$percentage = 0;
		
		foreach( $result as $results ) {
			$fd_id = $results->id;
			$fd_type = $results->type;
			$key = 'hmk_field_percentage_' . $fd_id;
			$field_percentage_value = $percentage_class->get_option( "$key", 'hmk_percentages' );
			if( $fd_type == 'checkbox' || $fd_type == 'multiselectbox' ) {
				$field1 = xprofile_get_field_data( $fd_id, $user_logged_in );
				$field2 = xprofile_get_field_data( $fd_id, $user_displayed );
				if( $field1 && $field2 ) {
					$intersect = array_intersect( ( array ) $field1, ( array ) $field2 );
					if( count( $intersect ) >= 1 ) {
						$percentage += $field_percentage_value;
					}
				}
			} elseif( xprofile_get_field_data( $fd_id, $user_logged_in ) ! = '' && xprofile_get_field_data( $fd_id, $user_logged_in ) == xprofile_get_field_data( $fd_id, $user_displayed ) ) {
				$field_percentage_value = $percentage_class->get_option( "$key", 'hmk_percentages' );
				$percentage += $field_percentage_value;
			}
		}
		
		/* If percent match equals 0, then set to 5 */
		if( $percentage == 0 )
			$percentage = 5;
		return $percentage;
	}
	
	/* Get the matching percentage and draw a circle. */
	function hmk_show_matching_percentage() {
		if( ! is_user_logged_in() )
			return;
		if( bp_is_my_profile() )
			return false;
		echo '<div class="c100 p' . $this->hmk_get_matching_percentage_number() . 'small hmk-percentage blue">
		<span class="hmk-match-inside">Match</span>
		<span>' . $this->hmk_get_matching_percentage_number() . '%</span>
		<div class="slice">
		<div class="bar"></div>
		<div class="fill"></div>
		</div>
		</div>';
	}
	
	/* Return the percent match on the button when users click on it */
	function hmk_script() {
	?>
		<script>
			jQuery( document ) . ready( function( $ ) {
				jQuery( '.hmk-get-percent' ) . on( 'click', function( event ) {
					var uid = event . target . id;
					uid = uid . split( '-' );
					uid = uid[1];
					jQuery( '#user-'+uid ) . html( 'Please wait...' );
					jQuery . post( ajaxurl, {
						action: 'hmk_get_percentage',
						'hmk_uid': uid,
					},
								  function( response ) {
						jQuery( '#user-'+uid ) . html( response );
					}
						);
				}
					);
			}
				);
		</script>
	<?php
	}
}
