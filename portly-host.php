<?php
/*
Plugin Name: Portly Host
Plugin URI: http://github.com/portly/portly-host/
Description: Alters all WordPress-generated URLs according to the servers current hostname and handles reverse-proxy HTTPS connections. Based initially on Any-hostname plugin.
Author: Kelly Martin
Version: 1.0.0
Author URI: https://getportly.com
*/

class PortlyHost {

  public function __construct() {
    $this->detect_ssl();
    $this->enable_filters();
    add_action('load-options-general.php', array(&$this, 'general_options_page_init'));
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
  }

  protected function disable_filters() {
    remove_filter('content_url',    array(&$this, 'content_url'), 100);
    remove_filter('option_home',    array(&$this, 'option_home'), 100);
    remove_filter('option_siteurl', array(&$this, 'option_siteurl'), 100);
    remove_filter('plugins_url',    array(&$this, 'plugins_url'), 100);
    remove_filter('theme_root_uri', array(&$this, 'theme_root_uri'), 100);
    remove_filter('upload_dir',     array(&$this, 'upload_dir'), 100);
  }

  /*
   * Internal: Filters the original host out of the URL and replaces it
   * with the current host.
   *
   * Returns a String.
   */
  protected function filter_url($url) {
    $parse_url = parse_url($url);
    $host = $_SERVER['HTTP_HOST'];
    return
         ((isset($parse_url['scheme'])) ? $parse_url['scheme'] . '://' : '')
        .((isset($parse_url['user'])) ? $parse_url['user'] . ((isset($parse_url['pass'])) ? ':' . $parse_url['pass'] : '') .'@' : '')
        .$host
        .((isset($parse_url['path'])) ? $parse_url['path'] : '')
        .((isset($parse_url['query'])) ? '?' . $parse_url['query'] : '')
        .((isset($parse_url['fragment'])) ? '#' . $parse_url['fragment'] : '')
    ;
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


$portly_host = new PortlyHost();

function portly_host_first() {
  // ensure path to this file is via main wp plugin path
  $wp_path_to_this_file = preg_replace('/(.*)plugins\/(.*)$/', WP_PLUGIN_DIR."/$2", __FILE__);
  $this_plugin = plugin_basename(trim($wp_path_to_this_file));
  $active_plugins = get_option('active_plugins');
  $this_plugin_key = array_search($this_plugin, $active_plugins);
  if ($this_plugin_key) { // if it's 0 it's the first plugin already, no need to continue
    array_splice($active_plugins, $this_plugin_key, 1);
    array_unshift($active_plugins, $this_plugin);
    update_option('active_plugins', $active_plugins);
  }
}

add_action("activated_plugin", "portly_host_first");
