<?php
/*
Plugin Name: DC Vote
Plugin URI: http://duane.co.za/plugins/dc-vote
Description: Add voting functionality to posts, pages or custom post types. Enhanced with AJAX for real-time updates. Allow Facebook likes and Tweets to count as a vote! Administrators have complete control over voting features as well as the ability to easily create a custom template for displaying the vote button and text.
Version: 1.0
Author: Duane Cilliers
Author URI: http://duane.co.za/
Author Email: duanecilliers@gmail.com
License:

	Copyright 2013 TODO (email@domain.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

*/

/*
 * Breakdown
 * ---------------------
 * ->
 */

class DCVote {

	var $db_version;

	/*--------------------------------------------*
	 * Constructor
	 *--------------------------------------------*/

	/**
	 * Initializes the plugin by setting localization, filters, and administration functions.
	 */
	function __construct() {

		$this->db_version = '1.0';

		// Load plugin text domain
		add_action( 'init', array( $this, 'plugin_textdomain' ) );

		// Register admin styles and scripts
		add_action( 'admin_print_styles', array( $this, 'register_admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'register_admin_scripts' ) );

		// Register site styles and scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'register_plugin_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_plugin_scripts' ) );

		// Register hooks that are fired when the plugin is activated, deactivated, and uninstalled, respectively.
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'upgrade_db' ) );

		add_shortcode( 'dcvote', array( $this, 'vote_shortcode' ) );

		add_shortcode( 'dcv-top-voted', array( $this, 'top_voted_shortcode' ) );

		add_action( 'admin_menu', array( $this, 'admin_vote_list' ) );

		add_action( 'wp_head', array( $this, 'voting_header' ) );

		// Non-logged in user
		add_action( 'wp_ajax_nopriv_dcv-submit', array( $this, 'vote_ajax_submit' ) );
		// Logged in user
		add_action( 'wp_ajax_dcv-submit', array( $this, 'vote_ajax_submit' ) );

		// Non-logged in user
		add_action( 'wp_ajax_nopriv_dcv-top-widget', array( $this, 'top_ajax_submit' ) );
		// Logged in user
		add_action( 'wp_ajax_dcv-top-widget', array( $this, 'top_ajax_submit' ) );


	} // end constructor

	/**
	 * Fired when the plugin is activated.
	 *
	 * @param boolean $network_wide True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog
	 */
	public function activate( $network_wide ) {

		$this->install_db();

	} // end activate

	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @param boolean $network_wide True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog
	 */
	public function deactivate( $network_wide ) {
		// TODO: Define deactivation functionality here
	} // end deactivate

	/**
	 * Loads the plugin text domain for translation
	 */
	public function plugin_textdomain() {

		$domain = 'dc-vote';
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );
		load_textdomain( $domain, WP_LANG_DIR.'/'.$domain.'/'.$domain.'-'.$locale.'.mo' );
		load_plugin_textdomain( $domain, FALSE, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );

	} // end plugin_textdomain

	/**
	 * Registers and enqueues admin-specific styles.
	 */
	public function register_admin_styles() {

		wp_enqueue_style( 'dc-vote-admin-styles', plugins_url( 'dc-vote/css/admin.css' ) );

	} // end register_admin_styles

	/**
	 * Registers and enqueues admin-specific JavaScript.
	 */
	public function register_admin_scripts() {

		$dcv_nonce = wp_create_nonce('dcv_submit_nonce');
		// wp_enqueue_script('dcv_userregister', WP_PLUGIN_URL.'/wp-voting/scripts/dcv-userregister.js', false, false, false);
		wp_enqueue_script( 'dc-vote-admin-script', plugins_url( 'dc-vote/js/admin.js' ), array( 'jquery' ) );

	} // end register_admin_scripts

	/**
	 * Registers and enqueues plugin-specific styles.
	 */
	public function register_plugin_styles() {

		wp_enqueue_style( 'dc-vote-plugin-styles', plugins_url( 'dc-vote/css/display.css' ) );

	} // end register_plugin_styles

	/**
	 * Registers and enqueues plugin-specific scripts.
	 */
	public function register_plugin_scripts() {

		$dcv_nonce = wp_create_nonce('dcv_submit_nonce');
		wp_enqueue_script( 'dc-vote-plugin-script', plugins_url( 'dc-vote/js/display.js' ), array( 'jquery' ) );
		wp_enqueue_script('dc-vote-voterajax', plugins_url( 'dc-vote/js/voterajax.js' ), array('jquery') );
		wp_localize_script('dc-vote-voterajax', 'dcvAjax', array('ajaxurl' => admin_url('admin-ajax.php'), 'dcv_nonce' => $dcv_nonce,));

	} // end register_plugin_scripts

	/*--------------------------------------------*
	 * Core Functions
	 *---------------------------------------------*/

	/*
	 * Create two tables (dc_vote and dc_vote_meta)
	 * dc_vote tbl to store voted posts
	 * dc_vote_meta tbl to store voted posts' additional data
	 * @since 1.0
	 */
	function install_db() {
		global $wpdb;
		$charset_collate = '';

		if ( $wpdb->supports_collation() ) {
			if ( !empty( $wpdb->charset ) ) {
				$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
			}
			if ( !empty( $wpdb->collate ) ) {
				$charset_collate .= " COLLATE $wpdb->collate";
			}
		}

		$query = "CREATE TABLE ".$wpdb->prefix."dc_vote (
					ID bigint(20) unsigned NOT NULL auto_increment,
					post_id bigint(20) unsigned NOT NULL,
					author_id bigint(20) unsigned NOT NULL,
					vote_count bigint(20) NULL,
					PRIMARY KEY  (ID)
				);
					CREATE TABLE ".$wpdb->prefix."dc_vote_meta (
					post_id bigint(20) unsigned NOT NULL,
					voter_id bigint(20) unsigned NOT NULL,
					vote_type varchar(40) NOT NULL,
					vote_value bigint(20) unsigned NOT NULL,
					vote_date datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
					voter_ip varchar(40) NOT NULL,
					KEY post_id (post_id)
				) $charset_collate;";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $query );

		//  Create options
		add_option( "dc-vote-db-version", $this->db_version );

		//  upgrade for WP version below 3.1
		$installed_ver = get_option( 'dc-vote-db-version' );
		if ( $installed_ver != $this->db_version ) {
			$query = "CREATE TABLE ".$wpdb->prefix."dc_vote (
							ID bigint(20) unsigned NOT NULL auto_increment,
							post_id bigint(20) unsigned NOT NULL,
							author_id bigint(20) unsigned NOT NULL,
							vote_count bigint(20) NULL,
							PRIMARY KEY  (ID)
						);
							CREATE TABLE ".$wpdb->prefix."dc_vote_meta (
							post_id bigint(20) unsigned NOT NULL,
							voter_id bigint(20) unsigned NOT NULL,
							vote_type varchar(40) NOT NULL,
							vote_value bigint(20) unsigned NOT NULL,
							vote_date datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							voter_ip varchar(40) NOT NULL,
							KEY post_id (post_id)
						) $charset_collate;";
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $query );

			update_option( 'dc-vote-db-version', $this->db_version );
		}
	}

	/*
	 * Upgrade
	 * Since WP 3.1 the register_activation_hook is not called when a plugin is updated
	 * So need this function to call wp_vote_dbinstall()
	 * @since 1.6
	 */
	function upgrade_db() {
		if ( get_site_option( 'dc-vote-db-version' != $this->db_version ) ) {
			$this->install_db();
		}
	}

	/*
	 * Shortcode
	 * @since 1.4
	 * @usage [dcvote]
	 */
	function vote_shortcode( $atts ) {
		global $post;
		return $this->get_display_vote( $post->ID );
	}

	/*
	 * shortcode
	 * @since 1.0
	 * @usage [dcv-top-voted show="10" nopostmsg="Nothing is voted yet"]
	 */
	function top_voted_shortcode( $atts ) {
		extract( shortcode_atts( array (
					'show' => '5',
					'nopostmsg' => 'Nothing to show'
				), $atts ) );

		return $this->top_voted_calc( $show, $nopostmsg );
	}

	/*
	 * Register necessary admin menus here
	 * Added dcv-allow-author-vote in v1.0
	 * Added dcv-voted-custom-txt in v1.0
	 * Added dcv-vote-btn-custom-txt in v1.0
	 * Added dcv-custom-css in v1.0
	 * Added dcv-allow-public-vote in v1.0
	 * @since 1.0
	 */
	function admin_vote_list() {
		add_menu_page( 'Vote', 'Vote', 'administrator', 'dcv-admin-voting-logs', array( $this, 'admin_vote_logs' ), 'div' );
		add_submenu_page( 'dcv-admin-voting-logs', 'Vote Options', 'Vote Options', 'administrator', 'dcv-admin-voting-options', array( $this, 'admin_vote_options' ) );
		register_setting( 'dcv_admin_vote_form_options', 'dc-vote-onoff', '' );
		register_setting( 'dcv_admin_vote_form_options', 'dcv-allow-author-vote', '' );
		register_setting( 'dcv_admin_vote_form_options', 'dcv-voted-custom-txt', '' );
		register_setting( 'dcv_admin_vote_form_options', 'dcv-vote-btn-custom-txt' );
		register_setting( 'dcv_admin_vote_form_options', 'dcv-custom-css' );
		register_setting( 'dcv_admin_vote_form_options', 'dcv-voting-alert-msg', '' );
		register_setting( 'dcv_admin_vote_form_options', 'dcv-allow-public-vote', '' );
	}

	/*
	 * Admin voting logs menu
	 * @since 1.0
	 */
	function admin_vote_logs() {
		?>
		<div class="wrap">
			<h2><?php _e( 'Vote Logs' ); ?></h2>
			<?php
		if ( current_user_can( 'manage_options' ) ) {
			$this->list_admin_vote_logs();
		}
		?>
		</div>
		<?php
	}

	/*
	 * Admin voting options
	 * Added dcv-allow-author-vote in v1.0
	 * Fixed initial selected state for options in v1.0
	 * Added dcv-voted-custom-txt in v1.0
	 * Added dcv-vote-btn-custom-txt in v1.0
	 * Added dcv-custom-css in v1.0
	 * Added dcv-allow-public-vote in v1.0
	 * @since 1.0
	 */
	function admin_vote_options() {
		?>
		<div class="wrap">
			<h2><?php _e( 'Vote Options' ); ?></h2>
			<div class="postbox-container" style="width: 65%">

				<div class="metabox-holder">
					<?php if ( isset( $_GET['settings-updated'] ) ) { ?>
						<div id="setting-error-settings_updated" class="updated settings-error">
						<p><strong>Settings saved.</strong></p></div>
					<?php } ?>
					<div class="meta-box-sortables ui-sortable">
						<form method="post" action="options.php" id="dcv-admin-voting-options">
							<?php
								$onoff = get_option( 'dc-vote-onoff' );
								$allow_author_vote = get_option( 'dcv-allow-author-vote' );
								$allow_public_vote = get_option( 'dcv-allow-public-vote' );
							?>
							<div id="wpvsettings" class="postbox">
								<div title="Click to toggle" class="handlediv"><br></div>
								<h3 class="hndle"><span>Vote Settings</span></h3>
								<div class="inside">
									<table class="form-table">
										<tr>
											<!-- Options section -->
											<td width="65%">
												<table>
													<tr valign="top">
														<th scope="row">Vote feature On/Off</th>
														<td>
															<input type="radio" name="dc-vote-onoff" value="On" <?php if ( $onoff == 'On' ) echo 'checked="checked"'; ?> /> On
															<input type="radio" name="dc-vote-onoff" value="Off" <?php if ( $onoff == 'Off' || empty( $onoff ) ) echo 'checked="checked"'; ?> /> Off
														</td>
													</tr>
													<tr valign="top">
														<th scope="row">Allow post author to vote his own posts</th>
														<td>
															<input type="radio" name="dcv-allow-author-vote" value="Yes" <?php if ( $allow_author_vote == 'Yes' ) echo 'checked="checked"'; ?> /> Yes
															<input type="radio" name="dcv-allow-author-vote" value="No" <?php if ( $allow_author_vote == 'No' || empty( $allow_author_vote ) ) echo 'checked="checked"'; ?> /> No
														</td>
													</tr>
													<tr valign="top">
														<th scope="row">Allow public(unregistered or non logged in) users to vote</th>
														<td>
															<input type="radio" name="dcv-allow-public-vote" value="Yes" <?php if ( $allow_public_vote == 'Yes' ) echo 'checked="checked"'; ?> /> Yes
															<input type="radio" name="dcv-allow-public-vote" value="No" <?php if ( $allow_public_vote == 'No' || empty( $allow_public_vote ) ) echo 'checked="checked"'; ?> /> No
														</td>
													</tr>
													<tr vlaign="top">
														<th scope="row">Vote count custom text <br /><strong><i>(default: "voted")</i></strong></th>
														<td>
															<input type="text" name="dcv-voted-custom-txt" value="<?php echo get_option( 'dcv-voted-custom-txt' ); ?>" />
														</td>
													</tr>
													<tr vlaign="top">
														<th scope="row">Vote button custom text <br /><strong><i>(default: "vote")</i></strong></th>
														<td>
															<input type="text" name="dcv-vote-btn-custom-txt" value="<?php echo get_option( 'dcv-vote-btn-custom-txt' ); ?>" />
														</td>
													</tr>
													<tr valign="top">
														<th scope="row">Custom CSS <br /><strong><i>Especially to override vote and voted buttons images</i></strong></th>
														<td>
															<textarea cols="60" rows="15" name="dcv-custom-css"><?php echo get_option( 'dcv-custom-css' ); ?></textarea><br />
														</td>
													</tr>
													<tr valign="top">
														<th scope="row">
															Alert message for non logged in users
															<br /><strong><i>If "Allow public users to vote feature" is set to "Yes",
																	this alert message will not be shown</i></strong>
														</th>
														<td>
															<textarea cols="60" rows="7" name="dc-vote-alert-msg"><?php echo get_option( 'dc-vote-alert-msg' ); ?></textarea><br />
														</td>
													</tr>
												</table>
											</td>
										</tr>
									</table>
								</div>
							</div>
							<br /><br />
							<?php settings_fields( 'dcv_admin_vote_form_options' ); ?>
							<input type="submit" class="button-primary" value="<?php _e( 'Save Changes' ) ?>" />
						</form>
					</div>
				</div>
			</div>
			<div class="postbox-container side" style="width:20%;">
				<div class="metabox-holder">
					<div class="meta-box-sortables ui-sortable">
						<div id="help" class="postbox">
							<div class="handlediv" title="Click to toggle"><br /></div>
							<h3 class="hndle"><span>Help</span></h3>
							<div class="inside">
								<table>
									<tr>
										<td><br />
											<strong>Custom CSS Guide</strong>
											<p>
												To change the vote button, please follow below steps <br />
											</p>
											<ol>
												<li>
														Upload your custom vote button to <span class="dcv_icode">plugins/dc-vote/images/</span> folder via FTP client
												</li>
												<li>
													Use <span class="dcv_icode">.dcv_vote_icon</span> css class to include your uploaded image.
													Write your custom css rule in the Custom CSS text box.<br />
													Below is the default vote button css rule. <br /><br />
													<span class="dcv_icode">
														.dcv_vote_icon { <br />
																background: url('../images/vote-btn.png') no-repeat; <br />
																width: 21px; <br />
																height: 20px; <br />
																display: inline-block; <br />
														}
													</span>
												</li>
											</ol><br />

											<p>
													To change the voted button, please follow below steps <br />
											</p>
											<ol>
												<li>
													Upload your custom voted button to <span class="dcv_icode">plugins/dc-vote/images/</span> folder via FTP client
												</li>
												<li>
													Use <span class="dcv_icode">.dcv_voted_icon</span> css class to include your uploaded image.
													Write your custom css rule in the Custom CSS text box.<br />
													Below is the default voted button css rule. <br /><br />
													<span class="dcv_icode">
														.dcv_voted_icon { <br />
																background: url('../images/voted-btn.png') no-repeat; <br />
																width: 21px; <br />
																height: 20px; <br />
																display: inline-block; <br />
														}
													</span>
												</li>
											</ol><br />

											<p>
												To style total vote count widget, please follow below steps <br />
											</p>
											<ol>
												<li>
													Use this class <span class="dcv_icode">.wpvtcount</span> to style your
													total vote count widget. Write your custom css rule in the Custom CSS text box.
													<br /><br /> e.g. <br />
													<span class="dcv_icode">
														.wpvtcount { <br />
																color: red; <br />
																font-size: 24px; <br />
														}
													</span>
												</li>
											</ol>

											<p class="dcv_icode" style="color:red;">
												Note: Please use absolute url for your custom images
												in your custom css. Please see the screenshot for example.
											</p>
										</td>
									</tr>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/*
	 * setup the post in dc_vote tbl
	 * @since 1.0
	 */
	function set_post( $post_ID, $author_ID ) {
		global $wpdb;

		// prevents SQL injection
		$p_ID = $wpdb->escape( $post_ID );
		$a_ID = $wpdb->escape( $author_ID );

		// Check if entry exists
		$id_raw = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM ".$wpdb->prefix."dc_vote WHERE post_id = %d AND author_id = %d", $p_ID, $a_ID ) );
		if ( $id_raw != '' ) {
			// entry exists, do nothing
		} else {
			// entry does not exist
			$wpdb->query( $wpdb->prepare( "INSERT INTO ".$wpdb->prefix."dc_vote (post_id, author_id, vote_count) VALUES (%d, %d, '')", $p_ID, $a_ID ) );
		}
	}

	/*
	 * Get vote count from dc_vote tbl
	 * @return string vote count
	 * @since 1.0
	 */
	function get_vote( $post_ID, $author_ID ) {
		global $wpdb;

		// prevents SQL injection
		$p_ID = $wpdb->escape( $post_ID );
		$a_ID = $wpdb->escape( $author_ID );

		// Create entries if not existant
		$this->set_post( $p_ID, $a_ID );

		$votes = $wpdb->get_var( $wpdb->prepare( "SELECT vote_count FROM  ".$wpdb->prefix."dc_vote WHERE post_id = %d AND author_id = %d", $p_ID, $a_ID ) );

		return $votes;
	}

	/*
	 * Check an user is already voted the post or not
	 * @return boolean
	 * @since 1.0
	 */
	function user_voted( $post_ID, $user_ID, $author_ID, $user_IP ) {
		global $wpdb;

		//  prevents SQL injection
		$p_ID = $wpdb->escape( $post_ID );
		$u_ID = $wpdb->escape( $user_ID );
		$a_ID = $wpdb->escape( $author_ID );
		$u_IP = $wpdb->escape( $user_IP );

		//  Create entry if not existant
		$this->set_post( $p_ID, $a_ID );

		if ( $u_ID == 0 )
			$voted = $wpdb->get_var( $wpdb->prepare( "SELECT voter_ip FROM ".$wpdb->prefix."dc_vote_meta WHERE post_id = %d AND voter_ip = %s AND voter_id = %s", $p_ID, $u_IP, $u_ID ) );
		else
			$voted = $wpdb->get_var( $wpdb->prepare( "SELECT voter_id FROM ".$wpdb->prefix."dc_vote_meta WHERE post_id = %d AND voter_id = %d", $p_ID, $u_ID ) );

		//  Record not found, so not voted yet
		if ( empty ( $voted ) || $voted == NULL )
			$voted = FALSE;
		else
			$voted = TRUE; // already voted

		return $voted;
	}

	/*
	 * Perform voting action here
	 * Update the vote count in dc_vote tbl
	 * Insert the voting metadata to dc_vote_meta tbl
	 * @return boolean
	 * @since 1.0
	 */
	function vote( $post_ID, $user_ID, $vote_type, $vote_value, $author_ID, $user_IP ) {
		global $wpdb, $current_user;
		$result = FALSE;

		// Prevents SQL injection
		$p_ID = $wpdb->escape( $post_ID );
		$u_ID = $wpdb->escape( $user_ID );
		$v_type = $wpdb->escape( $vote_type );
		$v_value = $wpdb->escape( $vote_value );
		$a_ID = $wpdb->escape( $author_ID );
		$u_IP = $wpdb->escape( $user_IP );
		//$dt = date('Y-m-d H:i:s');

		// Prevents fake userID
		if ( is_user_logged_in() ) {
			get_currentuserinfo();
			if ( $current_user->ID != $u_ID )
				return $result;
		}

		$this->set_post( $p_ID, $a_ID );

		$curr_count = $wpdb->get_var( $wpdb->prepare( "SELECT vote_count FROM  ".$wpdb->prefix."dc_vote WHERE post_id = %d AND author_id = %d", $p_ID, $a_ID ) );

		if ( !$this->user_voted( $p_ID, $u_ID, $a_ID, $u_IP ) ) {
			$new_count = $curr_count + 1;
			$wpdb->query( $wpdb->prepare( "UPDATE ".$wpdb->prefix."dc_vote SET vote_count = %d WHERE post_id = %d AND author_id = %d", $new_count, $p_ID, $a_ID ) );
			$wpdb->query( $wpdb->prepare( "INSERT INTO ".$wpdb->prefix."dc_vote_meta (post_id, voter_id, vote_type, vote_value, vote_date, voter_ip) VALUES (%d, %d, %s, %d, NOW(), %s)", array( $p_ID, $u_ID, $v_type, $v_value, $u_IP ) ) );

			$result = TRUE;
		}
		else {
			$result = FALSE;
		}
		return $result;
	}

	/*
	 * Display voting logs to admin user
	 * @echo voting table with pagination
	 * @since 1.0
	 * @todo reset all feature
	 */
	function list_admin_vote_logs() {

		require_once ABSPATH . 'wp-content/plugins/dc-vote/dcv-pagination.class.php';

		global $wpdb;
		$ob_par = '';

		// Prevents fake admin
		if ( !current_user_can( 'manage_options' ) )
			wp_die( 'You do not have permission to do that!' );

		if ( isset( $_GET['reset'] ) ) {
			if ( $_GET['reset'] != 'all' ) {
				$reset_id = (int)$_GET['reset'];
				$wpdb->query( $wpdb->prepare( "UPDATE ".$wpdb->prefix."dc_vote SET vote_count = 0 WHERE post_id = %d", $reset_id ) );
				$wpdb->query( $wpdb->prepare( "DELETE FROM ".$wpdb->prefix."dc_vote_meta WHERE post_id = %d", $reset_id ) );
			}
			else {
				$wpdb->query( $wpdb->prepare( "DELETE FROM ".$wpdb->prefix."dc_vote" ) );
				$wpdb->query( $wpdb->prepare( "DELETE FROM ".$wpdb->prefix."dc_vote_meta" ) );
			}
		}

		if ( isset( $_GET['orderby'] ) ) {
			if ( $_GET['orderby'] == 'vote_count' ) {
				$orderby = 'vote_count';
				$ob_par = '&orderby=vote_count';
			}
			elseif ( $_GET['orderby'] == 'vote_date' ) {
				$orderby = 'vote_date';
				$ob_par = '&orderby=vote_date';
			}
		}
		else {
			$orderby = 'vote_date';
		}

		$items = $wpdb->query( $wpdb->prepare( "SELECT * FROM ".$wpdb->prefix."dc_vote_meta" ) );

		if ( $items > 0 ) {
			$p = new dcv_pagination;
			$p->items( $items );
			$p->limit( 20 ); // Limit entries per page
			$p->target( "admin.php?page=dcv-admin-voting-logs".$ob_par );
			// $p->currentPage( $_GET[$p->paging] ); // Gets and validates the current page
			$p->calculate(); // Calculates what to show
			$p->parameterName( 'paging' );
			$p->adjacents( 1 ); //No. of page away from the current page

			if ( !isset( $_GET['paging'] ) ) {
				$p->page = 1;
				$pg_link = '';
			} else {
				$p->page = $_GET['paging'];
				$pg_link = '&paging='.$p->page;
			}

			//Query for limit paging
			$limit = "LIMIT " . ( $p->page - 1 ) * $p->limit  . ", " . $p->limit;

		}
		else {
			echo "No Record Found";
			return;
		}
		?>
			<a style="display:inline-block;margin:5px 0;" class="button reset-all-votes" href="?page=dcv-admin-voting-logs&reset=all">Reset All</a>
			<div class="tablenav">
				<div class='tablenav-pages'>
						<?php echo $p->show();  // Echo out the list of paging. ?>
				</div>
			</div>
			<table class="widefat">
			<thead>
				<tr>
					<th>Title</th>
					<th>Author</th>
					<th>Voter</th>
					<th><a href="?page=dcv-admin-voting-logs&orderby=vote_date<?php echo $pg_link; ?>" title="Order by vote date">Vote date</a></th>
					<th><a href="?page=dcv-admin-voting-logs&orderby=vote_count<?php echo $pg_link; ?>" title="Order by vote count">Current vote count</a></th>
					<th>Reset vote</th>
				</tr>
			</thead>
			<tbody>
			<?php
			$result = $wpdb->get_results( $wpdb->prepare( "SELECT ".$wpdb->prefix."dc_vote.post_id, author_id, voter_id, vote_count, vote_date FROM ".$wpdb->prefix."dc_vote INNER JOIN ".$wpdb->prefix."dc_vote_meta ON ".$wpdb->prefix."dc_vote.post_id = ".$wpdb->prefix."dc_vote_meta.post_id WHERE vote_count <> 0 ORDER BY $orderby DESC $limit" ) );

			if ( $result > 0 && !empty( $result ) ) {
				foreach ( $result as $row ) {
					$post_data = get_post( $row->post_id );

					if ( $row->voter_id > 0 ) {
						$voter_info = get_userdata( $row->voter_id );
						$voter_name = $voter_info->display_name;
					}
					else {
						$voter_name = "Guest";
					}

					$post_authorID = $post_data->post_author;
					$post_author_info = get_userdata( $post_authorID );
					$vote_date = date( 'd/m/Y H:i a', strtotime( $row->vote_date ) ); //new DateTime($row->vote_date);
					echo '<tr>';
					echo '<td>';
					echo '<a href="'.get_permalink( $row->post_id ).'" target="_blank">'.$post_data->post_title.'</a>';
					echo '</td>';

					echo '<td>';
					echo $post_author_info->display_name;
					echo '</td>';

					echo '<td>';
					echo $voter_name;
					echo '</td>';

					echo '<td>';
					echo $vote_date; //$vote_date->format('d/m/Y H:i a');
					echo '</td>';

					echo '<td>';
					echo $row->vote_count;
					echo '</td>';

					echo '<td>';
					echo '<a class="button reset-entry-votes" href="?page=dcv-admin-voting-logs&reset='.$row->post_id.'" >Reset</a>';
					echo '</td>';
					echo '</tr>';
				}
			}
			else {
				echo "<tr><td colspan=\"5\">No Record Found</td></tr>";
			}
			?>
			</tbody>
			<tfoot>
				<tr>
					<th>Title</th>
					<th>Author</th>
					<th>Voter</th>
					<th><a href="?page=dcv-admin-voting-logs&orderby=vote_date<?php echo $pg_link; ?>" title="Order by vote date">Vote date</a></th>
					<th><a href="?page=dcv-admin-voting-logs&orderby=vote_count<?php echo $pg_link; ?>" title="Order by vote count">Current vote count</a></th>
					<th>Reset vote</th>
				</tr>
			</tfoot>
			</table>
			<div class="tablenav">
				<div class='tablenav-pages'>
					<?php echo $p->show();  // Echo out the list of paging. ?>
				</div>
			</div>
	<?php
	}

	/*
	 * Display alert message if an user is vote a post without login.
	 * @return string alert message body
	 * @since 1.0
	 * @todo add custom login and registration URLs
	 */
	function voting_alert_msg() {
		$content = get_option( 'dcv-voting-alert-msg' );
		if ( empty ( $content ) || $content == null ) {
			$content = '<h3>Please log in to vote</h3>'.
				'<p>You need to log in to vote. If you already had an account, you may '.
				'<a href="'. get_option( 'siteurl' ).'/wp-login.php" title="Log in">log in</a> here</p>'.
				'<p>Alternatively, if you do not have an account yet you can '.
				'<a href="'. get_option( 'siteurl' ).'/wp-login.php?action=register" title="Register account">create one here</a>.</p>';
		}
		return $content;
	}

	/*
	 * Get IP address of a user
	 * @since 1.0
	 */
	function get_the_ip() {
		if ( empty( $_SERVER["HTTP_X_FORWARDED_FOR"] ) ) {
			$ip = $_SERVER["REMOTE_ADDR"];
		} else {
			$ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
		}
		if ( strpos( $ip, ',' ) !== false ) {
			$ip = explode( ',', $ip );
			$ip = $ip[0];
		}
		return esc_attr( $ip );
	}

	/*
	 * Vote count calculator for total vote count
	 * @since 1.0
	 */
	function total_vote_calc() {
		global $wpdb;
		$result = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(vote_count) AS vote_count_sum FROM ".$wpdb->prefix."dc_vote" ) );
		return $result;
	}

	/*
	 * Top voted func for top voted widget
	 * @param int $showcount number of post to show. default 5
	 * @since 1.7
	 * changed in 1.8
	 * set $showcount default value
	 */
	function top_voted_calc( $showcount = 5, $nopost_msg = null ) {
		global $wpdb;
		$result = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM ".$wpdb->prefix."dc_vote INNER JOIN ".$wpdb->prefix."posts ON ".$wpdb->prefix."dc_vote.post_id = ".$wpdb->prefix."posts.ID WHERE vote_count > 0 ORDER BY ".$wpdb->prefix."dc_vote.vote_count DESC, ".$wpdb->prefix."posts.post_date DESC LIMIT %d", $showcount ) );
		if ( !empty( $result ) ) {
			$output = '<ul>';
			foreach ( $result as $r ) {
				$post_data = get_post( $r->post_id );
				$post_url = get_permalink( $r->post_id );
				$vote_count = $r->vote_count;
				$output .= '<li><a title="'.$post_data->post_title.' - Total voted ('.$vote_count.')" href="'.$post_url.'">'.$post_data->post_title.' ('.$vote_count.')</a></li>';
			}
			$output .= '</ul>';
		}
		else {
			$output = $nopost_msg;
		}
		return $output;
	}

	/*
	 * Load custom css for frontend
	 * @since 1.0
	 */
	function voting_header() {
		if ( get_option( 'dcv-custom-css' ) )
			echo "\n<!-- WP Voting custom CSS - begin -->\n<style type='text/css'>\n" . get_option( 'dcv-custom-css' ) . "\n</style>\n<!-- WP Voting custom CSS - end -->\n\n";
	}

	/*
	 * Voting ajax
	 * Check security via nonce
	 * @since 1.0
	 */
	function vote_ajax_submit() {
		$nonce = $_POST['dcv_nonce'];

		if ( !wp_verify_nonce( $nonce, 'dcv_submit_nonce' ) )
			wp_die( 'Don\'t Cheat!' );

		$postID	= $_POST['postID'];
		$userID		= $_POST['userID'];
		$voteType	= $_POST['voteType'];
		$voteValue = $_POST['voteValue'];
		$authorID 	= $_POST['authorID'];
		$userIP    	= $this->get_the_ip();

		if ( !empty( $postID ) && ( $userID >= 0 ) && !empty( $authorID ) && !empty( $userIP ) ) {
			if ( $this->vote( $postID, $userID, $voteType, $voteValue, $authorID, $userIP ) ) {
				$response = $this->get_vote( $postID, $authorID );
			}
			else {
				$response = "Error: Voting! Please try again later.";
			}
		}
		echo $response;
		exit;
	}

	/*
	 * Ajax updating widget
	 * @since 1.0
	 */
	function top_ajax_submit() {
		$nonce = $_POST['dcv_nonce'];

		if ( !wp_verify_nonce( $nonce, 'dcv_submit_nonce' ) )
			wp_die( 'Don\'t Cheat!' );

		$showcount = get_option( 'dcv-top-voted-scount' );

		if ( $showcount !== FALSE ) {
			echo $this->top_voted_calc( $showcount );
		}
		exit;
	}

	/*
	 * Implement voting function to show it on the frontend
	 * Integrate admin voting feature on/off here
	 * Check allow post author to vote his own posts here
	 * Integrate custom vote and voted text
	 * Intefrate custom vote and voted button
	 * @since 1.0
	 */
	function get_display_vote( $postID ) {
		global $user_ID, $user_login;
		$output = '';
		$user_IP = $this->get_the_ip();
		$author_ID = get_the_author_meta( 'ID' );

		//## Get current vote count
		$curr_votes = $this->get_vote( $postID, $author_ID );

		//## Allow or disallow post author to vote his own posts
		$allow_author_vote = get_option( 'dcv-allow-author-vote' );
		if ( empty ( $allow_author_vote ) || $allow_author_vote == null || $allow_author_vote == 'No' ) {
			$allow_author_vote = false;
		}
		else {
			$allow_author_vote = true;
		}

		//## Allow or disallow public vote check
		$allow_public_vote = get_option( 'dcv-allow-public-vote' );
		if ( empty( $allow_public_vote ) || $allow_public_vote == null || $allow_public_vote == 'No' ) {
			$allow_public_vote = false;
		}
		else {
			$allow_public_vote = true;
		}

		//## Get custom vote count text
		$voted_custom_txt = get_option( 'dcv-voted-custom-txt' );
		if ( empty( $voted_custom_txt ) )
			$voted_custom_txt = 'voted';

		//## Get custom vote button text
		$vote_btn_custom_txt = get_option( 'dcv-vote-btn-custom-txt' );
		if ( empty( $vote_btn_custom_txt ) )
			$vote_btn_custom_txt = 'vote';

		//## Voting feature in On
		if ( get_option ( 'dc-vote-onoff' ) == 'On' ) {

			//## Registered user
			if ( is_user_logged_in() || $allow_public_vote ) {

				//## Unlogged in
				if ( !is_user_logged_in() && $allow_public_vote )
					$user_ID = 0;

				//## Cannot vote their own post (Voting is disallowed) and show vote count and voted btn
				if ( $user_ID == $author_ID && !$allow_author_vote ) {

					$output .= '<div class="dcv_postvote">'.
						'<span class="dcv_votewidget" id="dcvvotewidget'.get_the_ID().'">'.
						'<span class="dcv_votecount" id="dcvvotecount'.get_the_ID().'">'.
						'<span class="dcv_vcount">'.$curr_votes.' </span>'.
						$voted_custom_txt.
						'</span>'.
						'<span class="dcv_votebtncon">'.
						'<span class="dcv_votebtn" id="wpvvoteid'.get_the_ID().'">'.
						'<span class="dcv_voted_icon"></span>'.
						'<span class="dcv_votebtn_txt dcv_votedbtn_txt">'.$vote_btn_custom_txt.'</span>'.
						'</span>'.
						'</span>'.
						'</span>'.
						'</div>';
				}
				//## Voting is allowed
				else {

					//## New vote, so allowed and show vote count and vote btn
					if ( !$this->user_voted( $postID, $user_ID, $author_ID, $user_IP ) ) {

						$output .= '<div class="dcv_postvote">'.
							'<span class="dcv_votewidget" id="dcvvotewidget'.get_the_ID().'">'.
							'<span class="dcv_votecount" id="dcvvotecount'.get_the_ID().'">'.
							'<img title="Loading" alt="Loading" src="'.get_bloginfo( 'url' ).'/wp-content/plugins/wp-voting/images/ajax-loader.gif" class="loadingimage" style="visibility: hidden; display: none;"/>'.
							'<span class="dcv_vcount">'.$curr_votes.' </span>'.
							$voted_custom_txt.
							'</span>'.

							'<span class="dcv_votebtncon">'.
							'<span class="dcv_votebtn" id="wpvvoteid'.get_the_ID().'">'.
							'<a title="vote" class="dc_vote" href="javascript:void(0)" >'.
							'<span class="dcv_vote_icon"></span>'.
							'<span class="dcv_votebtn_txt">'.$vote_btn_custom_txt.'</span>'.
							'<input type="hidden" class="postID" value="'.$postID.'" />'.
							'<input type="hidden" class="userID" value="'.$user_ID.'" />'.
							'<input type="hidden" class="authorID" value="'.$author_ID.'" />'.
							'</a>'.
							'<span class="dcv_voted_icon" style="display: none;"></span>'.
							'<span class="dcv_votebtn_txt dcv_votedbtn_txt" style="display: none;">'.$vote_btn_custom_txt.'</span>'.
							'</span>'.
							'</span>'.
							'</span>'.
							'</div>';
					}
					//## Already voted, so disallowed and show vote count and voted btn
					else {

						$output .= '<div class="dcv_postvote">'.
							'<span class="dcv_votewidget" id="dcvvotewidget'.get_the_ID().'">'.
							'<span class="dcv_votecount" id="dcvvotecount'.get_the_ID().'">'.
							'<span class="dcv_vcount">'.$curr_votes.' </span>'.
							$voted_custom_txt.
							'</span>'.
							'<span class="dcv_votebtncon">'.
							'<span class="dcv_votebtn" id="wpvvoteid'.get_the_ID().'">'.
							'<span class="dcv_voted_icon"></span>'.
							'<span class="dcv_votebtn_txt dcv_votedbtn_txt">'.$vote_btn_custom_txt.'</span>'.
							'</span>'.
							'</span>'.
							'</span>'.
							'</div>';
					}
				}
			}
			//## Public vote is not allowed
			else {

				$output .= '<div class="dcv_postvote">'.
					'<span class="dcv_votewidget" id="dcvvotewidget'.get_the_ID().'">'.
					'<span class="dcv_votecount" id="dcvvotecount'.get_the_ID().'">'.
					'<span class="dcv_vcount">'.$curr_votes.' </span>'.$voted_custom_txt.
					'</span>'.
					'<span class="dcv_votebtncon">'.
					'<span class="dcv_votebtn" id="wpvvoteid'.get_the_ID().'">'.
					'<a title="vote" href="javascript:dcv_regopen();">'.
					'<span class="dcv_vote_icon"></span>'.
					'<span class="dcv_votebtn_txt">'.$vote_btn_custom_txt.'</span>'.
					'</a>'.
					'</span>'.
					'</span>'.
					'</span>'.
					'</div>';
			}
		}
		//## Voting feature is off, so show only vote count
		else {

			$output .= '<div class="dcv_postvote">'.
				'<span class="dcv_votewidget" id="dcvvotewidget'.get_the_ID().'">'.
				'<span class="dcv_votecount" id="dcvvotecount'.get_the_ID().'">'.
				'<span class="dcv_vcount">'.$curr_votes.' </span>'.
				$voted_custom_txt.
				'</span>'.
				'</span>'.
				'</div>';
		}

		return $output;
	}

	/*
	 * Implement voting function to show it on the frontend
	 * @since 1.0
	 * echo out get_display_vote
	 */
	function display_vote( $postID ) {
		echo $this->get_display_vote( $postID );
	}

} // end class

$plugin_name = new DCVote();
