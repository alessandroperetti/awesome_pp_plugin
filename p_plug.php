<?php
	/**
	 * Plugin Name: Peretti Plugin
	 * Plugin URI: https://www.perettialessandro.it/
	 * Description: Awesome E-commerce with special features
	 * Version: 1.0
	 * Author: Alessandro Peretti
	 * Author URI: https://www.perettialessandro.it/
	 **/

	/**
	 * Activate the plugin.
	 */

	define("MANAGER", 'manager');


	if(!class_exists("PP_plugin")) {
		class PP_plugin {

			public $pp_plugin_path;
			public $pp_lib_path;
			private static $pp_instance = null;
			

			/**
         	* Constructor
         	*/
        	private function __construct() {
				$this->pp_plugin_path = plugin_dir_path(__FILE__);
				$this->pp_lib_path = $this->pp_plugin_path . 'lib/';
				$this->pp_class_path = $this->pp_plugin_path . 'classes/';
				// include lib content
				foreach (glob($this->pp_lib_path . "*.php") as $filename){
					include $filename;
				}
				// include classes content
				foreach (glob($this->pp_class_path . "*.php") as $filename){
					include $filename;
				}
				//Call setup plugin
            	$this->pp_setup_actions();
			}

			public static function pp_get_instance(){
				if(self::$pp_instance == null){
					$pp_c = __CLASS__;
         		    self::$pp_instance = new $pp_c;
				}
				return self::$pp_instance;
			}
			
			public function pp_setup_actions(){

				Utility::peretti_debug('Inside setup actions');
				//Main plugin hooks
				/* on plugin activation */
				register_activation_hook( __FILE__,  array( 'PP_plugin', 'pp_activate'));
				/* on plugin deactivation */
				register_deactivation_hook( __FILE__, array( 'PP_plugin', 'pp_deactivate'));
			}

			public static function pp_activate(){
				Utility::peretti_debug('Inside activator');
				//create role manager
				add_role(MANAGER, "manager", array("edit-post" => true, "read" => true));
				//Give the manager the "share" capability
				get_role(MANAGER)->add_cap("share");
				self::pp_add_role_in_each_site();
				self::pp_config_creation();
			}

			public static function pp_deactivate(){
				// TODO opposite of the activation
				Utility::peretti_debug('Inside deactivator');
				get_role( MANAGER )->remove_cap('share');
				remove_role(MANAGER);
				self::pp_del_role_in_each_site();
				self::pp_config_deletion();
			}

			public static function pp_config_creation(){
				global $wpdb;
				$table_conf = $wpdb->prefix . "pp_conf";
				$charset_collate = $wpdb->get_charset_collate();

				$sql = "CREATE TABLE IF NOT EXISTS $table_conf (
		 				id mediumint(9) NOT NULL AUTO_INCREMENT,
						enable_for mediumint(9) NOT NULL,
						label varchar(100) NOT NULL,
						priority mediumint(9),
						data_creation TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		  				PRIMARY KEY  (id)
						) $charset_collate;";

				require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
				dbDelta($sql);

				$sql = "INSERT INTO $table_conf ( enable_for, label )
				VALUES (43800, '1 mese'),(21600, '15 giorni'),(10080, '7 giorni'),(1440, '24 ore'),(720, '12 ore');";
				dbDelta($sql);
			}

			public static function pp_config_deletion(){
				global $wpdb;
				$table_conf = $wpdb->prefix . "pp_conf";
				$charset_collate = $wpdb->get_charset_collate();

				$sql = "DROP TABLE IF EXISTS $table_conf";
				$wpdb->query($sql);	
			}

			public static function pp_add_role_in_each_site() {
				Utility::peretti_debug("Add role manager in each subsite");
				global $wpdb;
				
				$table_blogs = $wpdb->prefix . "blogs";
				$sites = $wpdb->get_results("SELECT * FROM $table_blogs WHERE blog_id > 1");

				$pp_global_options_wp_roles = get_option($wpdb->prefix . 'user_roles');

				if (count($sites) > 0) {
					foreach ($sites as $site) {
						update_blog_option($site->blog_id, $wpdb->prefix . $site->blog_id . "_user_roles", $pp_global_options_wp_roles);
					}
				}
			}

			public static function pp_del_role_in_each_site() {
				Utility::peretti_debug("Delete role manager in each subsite");
				global $wpdb;
				$table_blogs = $wpdb->prefix . "blogs";
				$sites = $wpdb->get_results("SELECT * FROM $table_blogs WHERE blog_id > 1");

				$pp_global_options_wp_roles = get_option($wpdb->prefix . 'user_roles');

				if (count($sites) > 0) {
					foreach ($sites as $site) {
						update_blog_option($site->blog_id, $wpdb->prefix . $site->blog_id . "_user_roles", $pp_global_options_wp_roles);
					}
				}
			}


			public function pp_init() {
				//Call setup action after the plugins are loaded
				add_action('plugins_loaded', array($this, 'pp_setup'));
			}

			public function pp_setup(){
				//All the actions
				add_action( 'wp_insert_site', array($this, 'pp_new_blog_activation'), 10, 6);
				add_action( 'admin_menu', array($this, 'pp_menu_product_list' ));
				add_action('wp_enqueue_scripts', array($this, 'load_my_java'));
				add_action('woocommerce_before_single_product', array($this, 'ajax_expired_passw'));
				//AJAX to open modal to config the time life of the password
				add_action('wp_ajax_actionconf', array($this,'actionconf')); // Logged-in users
				add_action('wp_ajax_nopriv_actionconf', array($this, 'actionconf')); // Guest users
				//AJAX for generating unique password
				add_action('wp_ajax_generatepassword', array($this,'generatepassword')); // Logged-in users
				add_action('wp_ajax_nopriv_generatepassword', array($this, 'generatepassword')); // Guest users
				//AJAX for creating new unique password
				add_action('wp_ajax_generate_and_save', array($this,'generate_and_save')); // Logged-in users
				add_action('wp_ajax_nopriv_generate_and_save', array($this, 'generate_and_save')); // Guest users
				//Edit the title in case of Manager Users 
				remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_title', 10, 2);
				add_action('woocommerce_single_product_summary', array($this,'pp_product_as_manager'), 6, 2);

			}

			public function pp_new_blog_activation($blog_id, $user_id, $domain, $path, $site_id, $meta){
				Utility::peretti_debug("New blog action: ");
				Utility::peretti_debug($blog_id);
				error_log("new_id: \n");
				error_log($blog_id);
			}

			public function actionconf(){
				global $wpdb;

				$table_conf = $wpdb->base_prefix . "pp_conf";
				Utility::peretti_debug($table_conf);
				$confs = $wpdb->get_results("SELECT * FROM $table_conf ORDER BY priority ASC");
				Utility::peretti_debug($confs);
				echo json_encode($confs);
				wp_die(); 
			}

			public function generate_and_save(){
				global $wpdb;

				include $this->pp_lib_path;
				$pp_password = new PP_password();
				$str_password = $pp_password->generate_password();
				echo $str_password;
				wp_die(); 
			}

			public function pp_menu_product_list(){
				add_options_page('My Plugin Options', 'PP Commerce', 'manage_options', '2343', array($this,'pp_product_list'));
			}

			public function pp_product_list() {
				if (!current_user_can('manage_options')) {
					wp_die(__('You do not have sufficient permissions to access this page.'));
				}
				global $wpdb;
			} 

			public function pp_product_as_manager(){
				$user = wp_get_current_user();
				$roles = (array) $user->roles;
				if (in_array("manager", $roles) && current_user_can('share')) {
				?>
						<button type="submit" id="shareBtn" class="button margin-2">Condividi </button>
						<!-- The Modal -->
						<div id="myModal" class="modal">

							<!-- Modal content -->
							<div class="modal-content">
							<span class="site-description"></span>
								<div class="modal-header">
									<span class="modal-title"> Aggiungi Password</span>
									<span class="close">&times;</span>
								</div>	
								<div class="modal-body">
									<select class="form-control" id="select-expiration">
									</select>
									<div id="password-generated" class="pp-hide"> <strong> Password generata: </strong> <p id="password-string"> </p> </div>
									</div>	
								<div class="modal-footer">
									<button type="submit" id="password-generator" class="pp-button opa" disabled> Genera password </button>
								</div>
							</div>
						</div>
					<?php
				}
			}

			public function load_my_java() {
				wp_enqueue_script( 'my_java', plugins_url( '/js/pp_js.js', __FILE__ ));
			}

			public function ajax_expired_passw(){
				?>
				<script type="text/javascript" >
				
				ajax_url = "<?php echo admin_url('admin-ajax.php'); ?>";

				jQuery(document).ready(function($) {

					document.getElementById("password-generator").addEventListener("click", function(){
						//call AJAX function to generate and save password corresponding to currently blog id.
						var my_data = {
           					 action: 'generate_and_save'  // This is required so WordPress knows which func to use
       					};
						jQuery.get(ajax_url, my_data, function(response) {
							console.log(response);
							//Modal body with new password generate plus the copy button
							$("#password-string").html(response);
							$("#password-generated").removeClass("pp-hide");/*  */
							$("#password-generated").addClass("pp-show");/*  */
							

						});

					});	

					document.getElementById("shareBtn").addEventListener("click", function(){
						var modal = document.getElementById("myModal");

						// Get the button that opens the modal
						var btn = document.getElementById("shareBtn");

						// Get the <span> element that closes the modal
						var span = document.getElementsByClassName("close")[0];

						// When the user clicks on the button, open the modal
						
						modal.style.display = "block";
						
						// When the user clicks on <span> (x), close the modal
						span.onclick = function() {
						modal.style.display = "none";
						}

						// When the user clicks anywhere outside of the modal, close it
						window.onclick = function(event) {
							if (event.target == modal) {
								modal.style.display = "none";
							}
						}

						$("#select-expiration").change(function() {
  							if($("#select-expiration").val() !== ''){
								$("#password-generator").prop('disabled', false);
								$("#password-generator").removeClass("opa");

							}
						});

						var my_data = {
           					 action: 'actionconf'  // This is required so WordPress knows which func to use
       					};
						   
						// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
						jQuery.get(ajax_url, my_data, function(response) {
							$("#select-expiration").html('');
							//console.log(response);
							response = JSON.parse(response);
							$("#select-expiration").append('<label class="site-description" for="list_confs">Scegli la durata della password</label>');
							$("#select-expiration").append('<option value=""> Scegli un opzione </option>');
							response.forEach(element => {
								$("#select-expiration").append(`<option value="${element.id}">${element.label}</option>`);
							});
						});
					});	
				});
				</script>
				<?php
			}
		}
	
	}

	$pp_instance_ready = PP_plugin::pp_get_instance();
	$pp_instance_ready->pp_init();
	 
?>