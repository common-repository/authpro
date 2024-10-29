<?php
/**
 * @package AuthPro
 */
/*
Plugin Name: AuthPro
Plugin URI: https://www.authpro.com/integrations/wordpress.shtml
Description: Adds AuthPro.com remotely hosted service support to your WordPress website. You'll need to <a href="https://www.authpro.com/signup.shtml">signup for AuthPro account</a> if you do not have one yet.
Version: 1.3.0
Author: AuthPro
Author URI: https://www.authpro.com/?WP
License: GPLv2 or later
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/


add_action( 'admin_menu', 'authpro_plugin_menu' );

add_action( 'add_meta_boxes', 'authpro_meta_box' );
add_action( 'save_post', 'authpro_set_post_protection' );
add_action( 'save_page', 'authpro_set_page_protection' );

add_action( 'wp_enqueue_scripts', 'authpro_enqueue_script' );
add_filter( 'preprocess_comment', 'authpro_check_comment');
add_filter( 'script_loader_tag', 'authpro_add_data_acc', 10, 3 );

add_filter( 'plugin_action_links', 'authpro_plugin_action_links', 10, 2 );

// add authpro configuration link
function authpro_plugin_action_links( $links, $file ) {
	if ( $file == plugin_basename(__FILE__) ) {
		array_unshift( $links, '<a href="' . admin_url( 'options-general.php?page=authpro' ) . '">'.__( 'Settings' ).'</a>');
	}
	return $links;
}

// enqueue Authpro protection script code if needed
function authpro_enqueue_script() {

	if (is_admin()) { return; }

	// disable enqueue protection code in elementor editor
	if ( in_array( 'elementor/elementor.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) { return; }
		if ( \Elementor\Plugin::$instance->preview->is_preview_mode() ) { return; }
	}

	// enqueue authpro protection scripts
	if ( authpro_isprotected( get_queried_object() ) == '1' ) {
		$authpro_username = get_option('authpro_username');
		$authpro_script = 'https://www.authpro.com/auth/' . $authpro_username . '/?action=pp&rand=' . rand();
		wp_enqueue_script( 'ap-pp', $authpro_script, false );
		wp_enqueue_script( 'ap-wpp', plugins_url( '/authpro.js', __FILE__ ), false );
	}
}

// add Authpro username to authpro.js
function authpro_add_data_acc( $tag, $handle, $source ) {
	if ( 'ap-wpp' === $handle ) {
		$authpro_username = get_option('authpro_username');
		$tag = '<script src="' . $source . '" id="ap-wpp" data-acc="' . $authpro_username .'"></script>' . "\n";
	}
	return $tag;
}

// disable post comments if not authorized by AuthPro
function authpro_check_comment( $commentdata ) {
	$authpro_auth = 'https://www.authpro.com/auth/'.get_option('authpro_username').'/';
	$post_id = $commentdata['comment_post_ID'];
	if ( authpro_isprotected( get_post($post_id) ) == '1' ) {
		$allow_post = 0;
		if ( isset($_POST['ap_sid']) ) {
			$resp = wp_remote_get( $authpro_auth.'?mode=API&action=session&id='.$_POST['ap_sid'] );
			if ( is_wp_error( $resp ) ) { wp_die( $resp->get_error_message() ); }
		        if ( $resp['body'] == 'OK' ) { $allow_post = 1; }
		}
		if ( $allow_post == 0 ) { wp_die('Not authorized<script>setTimeout(()=>{ document.location.replace("'.$authpro_auth.'?action=ppfail"); }, 5000);</script>'); }
	}
	return $commentdata;
}

function authpro_isprotected( $page_obj ) {

	$authpro_usage = get_option('authpro_usage');
	$authpro_protect = '';
	$authpro_ispp = '';

	if ($authpro_usage == 'D') { return ''; }
	if ($authpro_usage == 'A') { return 1; }

	if ( isset( $page_obj ) && property_exists( $page_obj, 'post_type' ) ) {

		$authpro_ispp = $page_obj->post_type;
	}

	if ( ( $authpro_ispp != 'post' ) && ( $authpro_ispp != 'page' ) ) { return ''; }

	if ( $authpro_usage == 'P' ) {

		$authpro_protect = get_post_meta( $page_obj->ID, '_authpro_protect', true );

	} else {
		$ta = array();
		$pa = array();
		$ptax = '';
		if ( $authpro_usage == 'C' ) {
			$ptax = 'category';
			$pa = explode(',', get_option('authpro_protect_categories') );
		}
		if ( $authpro_usage == 'T' ) {
			$ptax = 'post_tag';
			$pa = explode(',', get_option('authpro_protect_tags') );
		}
		if ( $ptax ) {
			$tal = get_the_terms( $page_obj->ID, $ptax ); //print_r($tal); echo '<hr>';
			if ( is_array( $tal ) ) {
				foreach ( $tal as $ti ) { 
					if ( property_exists( $ti, 'term_id' ) ) {
						$ta[] = $ti->term_id; 
						if ( ( property_exists( $ti, 'parent' ) ) && ( $ti->parent>0 ) ) {
							$tap = get_ancestors( $ti->term_id, $ptax );
							$ta = array_merge( $ta, $tap );
						}
					}
				}
			}
		}
		$ca = array_intersect( $ta, $pa );
		if ( count($ca)>0 ) { $authpro_protect='1'; }
	}
	return $authpro_protect;
}

// register metabox
function authpro_meta_box() {
	add_meta_box( 'authpro_meta_box_id', 'AuthPro page protection', 'authpro_meta_box_content', 'page', 'normal', 'default' );
	add_meta_box( 'authpro_meta_box_id', 'AuthPro post protection', 'authpro_meta_box_content', 'post', 'normal', 'default' );
}

// display the metabox
function authpro_meta_box_content( $post ) {
	// nonce field for security check, you can have the same
	// nonce field for all your meta boxes of same plugin
	wp_nonce_field( plugin_basename( __FILE__ ), 'authpro_nonce' );
	$value = get_post_meta( $post->ID, '_authpro_protect', true );
	if ( $value==1 ) { $check='checked'; } else { $check=''; }
	echo '<input type="checkbox" name="authpro_protect" value="1" ' . $check . '/> Protect with AuthPro <br />';
}

function authpro_set_post_protection( $post_id ) {

    // check if this isn't an auto save
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
        return;

    // security check
    if ( !isset($_POST['authpro_nonce']) ) return;
    if ( !wp_verify_nonce( $_POST['authpro_nonce'], plugin_basename( __FILE__ ) ) )
        return;

    // Check the user's permissions.
    if ( isset( $_POST['post_type'] ) && 'page' == $_POST['post_type'] ) {

		if ( ! current_user_can( 'edit_page', $post_id ) ) {
			return;
		}

	} else {

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
    }

    // now store data in custom fields based on checkbox selected
    if ( isset( $_POST['authpro_protect'] ) )
        update_post_meta( $post_id, '_authpro_protect', 1 );
    else
        update_post_meta( $post_id, '_authpro_protect', 0 );
}



function authpro_plugin_menu() {
	add_options_page( 'AuthPro Options', 'AuthPro', 'manage_options', 'authpro', 'authpro_plugin_options' );
}


/*** OPTIONS ***/
function authpro_plugin_options() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}

	// Make sure post was from this page
	if ( count($_POST) > 0 ) {
		check_admin_referer('authpro-options');
	}
		
	if ( isset($_POST['update_options']) ) {

	        $authpro_usage = $_POST['authpro_usage'];
	        $authpro_username = $_POST['authpro_username'];

        	update_option( 'authpro_usage', $authpro_usage );
        	update_option( 'authpro_username', $authpro_username );

        	if ( $authpro_usage == 'C' ) {
			$authpro_protect_categories = join( ",", $_POST['post_category'] );
			update_option( 'authpro_protect_categories', $authpro_protect_categories );
        	}

        	if ( $authpro_usage == 'T' ) {
			$authpro_protect_tags = join( ",", $_POST['tax_input']['post_tag'] );
			update_option( 'authpro_protect_tags', $authpro_protect_tags );
        	}

		echo '<div class="updated">AuthPro settings updated.</div>';
	}

	$authpro_usage = get_option( 'authpro_usage' );
	$authpro_username = get_option( 'authpro_username' );
	$authpro_usage_set = array_fill_keys( array( 'D', 'P', 'C', 'T', 'A' ), '' );
	$authpro_usage_set[$authpro_usage] = 'selected';
	$authpro_protect_categories = get_option( 'authpro_protect_categories' );
	$authpro_protect_tags = get_option( 'authpro_protect_tags' );
	?>
	<div class="wrap">
	  <style scoped>ul {padding: 5px 0px 0px 20px}</style>
	  <h2><?php echo __('AuthPro Settings','authpro'); ?></h2>
  	  <form action="options-general.php?page=authpro" id="authpro_cfg_form" method="post">
	  <?php wp_nonce_field('authpro-options'); ?>
		<table class="form-table">
		  <tr>
			<th scope="row" valign="top">AuthPro account username:</th>
			<td>
				<input name="authpro_username" value="<?php echo $authpro_username ?>" type="text" size="20" />
			</td>
		  </tr>
		  <tr>
			<th scope="row" valign="top">AuthPro protection:</th>
			<td>
				<select name="authpro_usage" onchange="authpro_usage_opts()"> <option value='D' <?php echo $authpro_usage_set['D']; ?>>Disabled</option><option value='P' <?php echo $authpro_usage_set['P']; ?>>Selected pages only</option><option value='C' <?php echo $authpro_usage_set['C']; ?>>Selected categories</option><option value='T' <?php echo $authpro_usage_set['T']; ?>>Selected post tags</option><option value='A' <?php echo $authpro_usage_set['A']; ?>>Entire website/blog</option></select>
			</td>
		  </tr>
		  <tr>
			<th scope="row" valign="top"></th>
			<td>
				<div id="authpro_opts_D" style="display:none; color: var(--e-notice-context-color)">AuthPro protection is disabled</div>
				<div id="authpro_opts_P" style="display:none">You can enable AuthPro protection on page or post edit page</div>
				<div id="authpro_opts_C" style="display:none"><ul><?php wp_category_checklist( 0, 0, explode(',', $authpro_protect_categories ), false, null, false ); ?></ul></div>
				<div id="authpro_opts_T" style="display:none"><ul><?php wp_terms_checklist( 0, [ 'taxonomy' => 'post_tag', 'checked_ontop' => false, 'selected_cats' => explode(',', $authpro_protect_tags ) ] ); ?></div>
				<div id="authpro_opts_A" style="display:none">All website pages are protected by AuthPro</div>
			</td>
		  </tr>
		</table>
		<p class="submit">
			<input name="update_options" type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
		</p>
	  </form>
	</div>
	<script>
	  var f = document.getElementById('authpro_cfg_form');
	  authpro_usage_opts();
	  function authpro_usage_opts() {
	    var apu = f.authpro_usage.value;
	    const uos = ['D','P','C','T','A'];
	    uos.forEach((uo) => { if (uo==apu) { document.getElementById('authpro_opts_'+uo).style.display='block'; } else { document.getElementById('authpro_opts_'+uo).style.display='none'; } });
	  }
	</script>
	<?php
}
?>