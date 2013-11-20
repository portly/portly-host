<?php
/*
Plugin Name: Portly Router
Plugin URI: http://github.com/portly/portly-router/
Description: Zero-config plugin to use with <a href="https://getportly.com">Portly</a>.  Alters all WordPress-generated URLs according to the server's current hostname and handles reverse-proxy HTTPS connections. Essentially allows using a public domain without configuring VirtualHosts or altering the Site URL.
Author: Portly
Version: 1.1.0
Author URI: https://getportly.com
 */

class PortlyRouter {

  const MU_FILE = "/portly-router-mu.php";

  public function __construct() {

    $this->is_forwarding = array_key_exists('HTTP_X_FORWARDED_HOST', $_SERVER);
    $this->set_site_path_regex();
    $this->detect_ssl();
    $this->enable_filters();

    if ( defined('PORTLY_MU_INSTALLED') ) {
      $this->define_cookie_paths();
      $this->alter_request_uri();
    }

    add_action('load-options-general.php', array(&$this, 'general_options_page_init'));
    register_activation_hook(__FILE__, array(&$this, 'activate'));
    register_deactivation_hook(__FILE__, array(&$this, 'deactivate'));

  }


  /* Public: This runs on the activation of the plugin.  It sets up the Must-Use plugin
   * so that we can override constants defined before regular plugins.
   *
   * Returns nothing.
   */
  public function activate() {

	  if ( ! is_dir(WPMU_PLUGIN_DIR)) mkdir(WPMU_PLUGIN_DIR);

 	  $copy_from = __DIR__ . self::MU_FILE;
	  $copy_to = WPMU_PLUGIN_DIR . self::MU_FILE;
	  if ( ! file_exists($copy_to) ) {
		  copy($copy_from, $copy_to);
      $file = file($copy_from);
      unset($file[1]);
      array_pop($file);
      file_put_contents($copy_to, $file);
	  }

  }

  /* Public: Removes the file created on activation in the Must-Use plugins folder.
   *
   * Returns nothing.
   */
  public function deactivate() {
    if( file_exists(WPMU_PLUGIN_DIR . self::MU_FILE) ) {
      unlink(WPMU_PLUGIN_DIR . self::MU_FILE);
    }
  }

  protected function set_site_path_regex() {
    if($this->is_forwarding && array_key_exists('HTTP_X_FORWARDED_PATH',$_SERVER)) {
      $this->site_path_regex = "/^".str_replace('/','\/',$_SERVER['HTTP_X_FORWARDED_PATH']).'/';
    } else {
      $this->site_path_regex = '//';
    }
  }

  protected function detect_ssl() {
    if (!empty($_SERVER['HTTP_USESSL']) && $_SERVER['HTTP_USESSL'] != 'off') {
      $_SERVER['HTTPS'] = 'on';
    }
  }

  protected function enable_filters() {
    add_filter('content_url',    array(&$this, 'content_url'), 100);
    add_filter('option_home',    array(&$this, 'option_home'), 100);
    add_filter('option_siteurl', array(&$this, 'option_siteurl'), 100);
    add_filter('plugins_url',    array(&$this, 'plugins_url'), 100);
    add_filter('theme_root_uri', array(&$this, 'theme_root_uri'), 100);
    add_filter('upload_dir',     array(&$this, 'upload_dir'), 100);
    add_filter('wp_redirect',    array(&$this, 'filter_url'), 100);
  }

  protected function disable_filters() {
    remove_filter('content_url',    array(&$this, 'content_url'), 100);
    remove_filter('option_home',    array(&$this, 'option_home'), 100);
    remove_filter('option_siteurl', array(&$this, 'option_siteurl'), 100);
    remove_filter('plugins_url',    array(&$this, 'plugins_url'), 100);
    remove_filter('theme_root_uri', array(&$this, 'theme_root_uri'), 100);
    remove_filter('upload_dir',     array(&$this, 'upload_dir'), 100);
  }

  /* Internal: Alters the REQUEST_URI server variable so that add_query_params() and potentially
   * other methods don't inject a bad path.
   *
   * Returns nothing.
   */
  protected function alter_request_uri() {
    if ($this->is_forwarding) {
      $_SERVER["REQUEST_URI"] = preg_replace($this->site_path_regex,'',$_SERVER['REQUEST_URI']);
    }
  }

  /* Internal: Defines the cookie paths so we can have authentication at the right place.
   *
   * Returns nothing.
   */
  protected function define_cookie_paths() {
    if (!defined('COOKIEPATH')) {
      define( 'COOKIEPATH', preg_replace( '|https?://[^/]+|i', '', get_option( 'home' ) . '/' ) );
      define( 'SITECOOKIEPATH', preg_replace( '|https?://[^/]+|i', '', get_option( 'siteurl' ) . '/' ) );
    };
  }

  /*
   * Public: Filters the original host out of the URL and replaces it
   * with the current host.
   *
   * Returns a String.
   */
  public function filter_url($url) {
    $parse_url = parse_url($url);

    $host_and_path = $_SERVER['HTTP_HOST'];
    if ($this->is_forwarding) {
       $host_and_path .= isset($parse_url['path']) ? preg_replace($this->site_path_regex, '', $parse_url['path']) : '';
    } else {
       $host_and_path .= $parse_url['path'];
    }

    $new_url =
         ((isset($parse_url['scheme'])) ? $parse_url['scheme'] . '://' : '')
        .((isset($parse_url['user'])) ? $parse_url['user'] . ((isset($parse_url['pass'])) ? ':' . $parse_url['pass'] : '') .'@' : '')
        .$host_and_path
        .((isset($parse_url['query'])) ? '?' . $parse_url['query'] : '')
        .((isset($parse_url['fragment'])) ? '#' . $parse_url['fragment'] : '')
        ;
    return $new_url;
  }

  /*
   * Public: Disable host filters on options-general.php in order to avoid
   * obscuring the 'home' and 'siteurl' settings, potentially resulting
   * in involuntary changing the sites default host name
   */
  public function general_options_page_init() {
    /*
     * Register hook to temporarily disable the host filters on
     * /wp-admin/options-general.php, as close to the input fields as possible
     */
    add_action('all_admin_notices', array(&$this, 'general_options_page_begin'), 20);
  }

  public function general_options_page_begin() {

    /* Enable host filters again */
    $this->disable_filters();

    /*
     * Register functions to enable the filters again,
     * as quickly as possible after the input fields has been output
     */

    // Perhaps unsafe in contrast to future page changes, but appears to be fully functional for now
    add_filter('date_formats', array(&$this, 'general_options_page_end'), 1);

    // Perhaps safer, and probably OK to run it once more to be sure
    add_action('in_admin_footer', array(&$this, 'general_options_page_end'), 1);
  }

  public function general_options_page_end($input = null) {
    $this->enable_filters();

    // Remove filters again
    remove_filter('date_formats', array(&$this, 'general_options_page_end'), 1);
    remove_action('in_admin_footer', array(&$this, 'general_options_page_end'), 1);

    return $input;
  }

  public function theme_root_uri($theme_root_uri) {
    return $this->filter_url($theme_root_uri);
  }

  public function option_home($url) {
    return maybe_unserialize($this->filter_url($url));
  }

  public function option_siteurl($url) {
    return $this->option_home($url);
  }

  public function plugins_url($url) {
    return $this->filter_url($url);
  }

  public function content_url($url) {
    return $this->filter_url($url);
  }

  public function upload_dir($values) {
    $values['url'] = $this->filter_url($values['url']);
    $values['baseurl'] = $this->filter_url($values['baseurl']);

    return $values;
  }

}

$portly_router = new PortlyRouter();

