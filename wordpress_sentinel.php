<?php
/**
 * @package WORDPRESSSENTINEL
 */
/*
Plugin Name: Wordpress Sentinel
Plugin URI: http://blogrescue.com/2011/12/new-plugin-wp-sentinel/
Description: Watches over your install and alerts you when changes are made. <a href="options-general.php?page=wordpress_sentinel">Settings</a> | <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=GFVCGSUFKX2CU">Donate</a>
Version: 1.31
Author: Blogrescue.com
Author URI: http://blogrescue.com 
License: New BSD License (http://www.opensource.org/licenses/bsd-license.php)
*/
require("wordpress_sentinel_db.php");

//ini_set('display_errors',1); 
//error_reporting(E_ERROR);
 
define('WPS_BASE', 0);
define('WPS_PLUGINS', 1);
define('WPS_THEMES', 2);
  
define('WPS_STATE_NEW', 0);
define('WPS_STATE_OK', 1);
define('WPS_STATE_CHANGED', 2);
define('WPS_STATE_MISSING', 3);
define('WPS_STATE_ADDED', 4);
define('WPS_STATE_ALLOWED', 98);
define('WPS_STATE_CHECK', 99);

define('WPS_MODE_RECURSIVE', 0);
define('WPS_MODE_DIRECTORY', 1);
define('WPS_MODE_FILE', 2);

class wordpress_sentinel {
  var $admin_page = 'wordpress_sentinel';
  var $uri = "";
  var $args = array();

  var $wp_theme_dir; 
  var $wp_plugin_dir;
  var $wp_wordpress_dir;
  var $use_checksums;
  
  var $base;
  var $plugins;
  var $themes;
  
  var $states = array(
    '0'=>'<span style="color:orange;font-weight:bold;">New</span>',
    '1'=>'<span style="color:green;font-weight:bold;">OK</span>',
    '2'=>'<span style="color:red;font-weight:bold;">Changed</span>',
    '3'=>'<span style="color:red;font-weight:bold;">Missing</span>',
    '4'=>'<span style="color:red;font-weight:bold;">Added</span>',
    '98'=>'<span style="color:blue;font-weight:bold;">Not Watched</span>',
    '99'=>'<span style="color:blue;font-weight:bold;">Checking...</span>'
  );
  
  function wordpress_sentinel() {
    $this->parse_uri();
    
    $this->wp_theme_dir = get_theme_root();
    $this->wp_plugin_dir = WP_PLUGIN_DIR;
    $this->wp_wordpress_dir = dirname(WP_CONTENT_DIR);  
    
    $this->use_checksums = get_site_option('wordpress_sentinel_use_checksums');
  }
  
  function site_check() {
    
    // Check for issues and display admin message
    if(!preg_match("/page=wordpress_sentinel/", $_SERVER['REQUEST_URI'])) {
      $this->build_section_list();
      
      global $wpdb;
      $changed = $wpdb->get_var("SELECT COUNT(*) FROM ".$wpdb->prefix."wordpresssentinel_section WHERE status = ".WPS_STATE_CHANGED." AND watch = 1");
      $new = $wpdb->get_var("SELECT COUNT(*) FROM ".$wpdb->prefix."wordpresssentinel_section WHERE status = ".WPS_STATE_NEW." AND watch = 1");
      if($changed || $new)
        add_action('admin_notices', 'wordpress_sentinel_admin_warning');
    }
    
    // Periodic (mock cron) checking for changes
    $action_interval = 10; // minutes
    $check_interval = 4; // hours
    
    $site_check = intval(get_site_option('wordpress_sentinel_site_check'));
    if($site_check + ($action_interval * 60) < time()) {
      $this->build_section_list();
      $all_sections = array_merge($this->base, $this->themes, $this->plugins);
    
      foreach($all_sections as $section) {
        if($section->state == WPS_STATE_NEW || $section->watch == 0) continue;
        
        $delta_time = time() - strtotime($section->last_checked);
        $threshold = 60 * 60 * $check_interval;
        if($delta_time > $threshold) {
          $this->check_section($section->id, false);
          break;
        }
      }

      update_option('wordpress_sentinel_site_check', time());
    }
  }
  
  function admin_panel() {
    $this->build_section_list();

    $admin_home = true;
    $action = $this->arg('action');
    $section_id = $this->arg('section', '0');
    $section_id_intval = intval($section_id);
    $checksum_message = '';

    if($action == 'checksum') {
      check_admin_referer('sentinel-checksum');
      $this->use_checksums = !$this->use_checksums;
      update_option('wordpress_sentinel_use_checksums', $this->use_checksums);
      
      $checksum_message = "<div id='message' class='updated' style='margin-top:8px;'>".
        "<p>Checksum Mode Changed - Checksums are now <strong>".
        ($this->use_checksums ? 'Enabled' : 'Disabled')."</strong>.".
        ($this->use_checksums ? ' (All Snapshots will need to be Refreshed!)' : '').
        "</p></div>";
    }  

    $this->interface_buttons();
    if($checksum_message != '') print $checksum_message;

    if($action == 'snapshot') {
      if($section_id == 'all') {
        check_admin_referer('sentinel-snapshot-all');
        $this->build_all_new_snapshots();
      } else {
        check_admin_referer('sentinel-snapshot-'.$section_id_intval);
        $this->build_snapshot($section_id_intval);
      }
      
    } else if($action == 'details' && $section_id > 0) {
      check_admin_referer('sentinel-details-'.$section_id_intval);
      $admin_home = $this->view_details($section_id_intval);

    } else if($action == 'section-watch' && $section_id > 0) {
      check_admin_referer('sentinel-watch-'.$section_id_intval);
      $this->set_section_watch_status($section_id_intval, 1);
      
    } else if($action == 'section-nowatch' && $section_id > 0) {
      check_admin_referer('sentinel-nowatch-'.$section_id_intval);
      $this->set_section_watch_status($section_id_intval, 0);
      
    } else if($action == 'check') {
      if($section_id == 'all') {
        check_admin_referer('sentinel-check-all');
        $this->check_all_sections();
      } else {
        check_admin_referer('sentinel-check-'.$section_id_intval);
        $this->check_section($section_id_intval);
      }

    } else if($action == 'help') {
      $this->show_help();
      $admin_home = false;
    }
    
    if ($admin_home) {
      $this->build_section_list();
      $this->display_sections();
    }
  }
  
  function interface_buttons() {
    print '<div id="icon-options-general" class="icon32"><br /></div>';
    print '<h2>Wordpress Sentinel</h2>';
    print "<div style='text-align:center;'>";
    
    $snapshot_url = $this->url('snapshot', array('section'=>'all'));
    $snapshot_link = ( function_exists('wp_nonce_url') ) ? wp_nonce_url($snapshot_url, 'sentinel-snapshot-all') : $snapshot_url;
    print "<a href='$snapshot_link' class='button'>".
      "Snapshot Everything New</a>";
      
    print "&nbsp;&nbsp;&nbsp;&nbsp;";
    
    $check_url = $this->url('check', array('section'=>'all'));
    $check_link = ( function_exists('wp_nonce_url') ) ? wp_nonce_url($check_url, 'sentinel-check-all') : $check_url;
    print "<a href='$check_link' class='button'>Check Everything</a>";

    print "&nbsp;&nbsp;&nbsp;&nbsp;";
    
    $sum_url = $this->url('checksum');
    $sum_link = ( function_exists('wp_nonce_url') ) ? wp_nonce_url($sum_url, 'sentinel-checksum') : $sum_url;
    print "<a href='$sum_link' class='button'>".($this->use_checksums ? 'Disable' : 'Enable')." Checksums</a>";
    
    print "&nbsp;&nbsp;&nbsp;&nbsp;";
    
    print "<a href='".$this->url('help')."' class='button'>How Does This Work?</a>";
    print "</div>";
  }
  
  function display_sections() {
    $this->display_section("Wordpress", $this->base);
    $this->display_section("Themes", $this->themes);
    $this->display_section("Plugins", $this->plugins);
  }
  
  function display_section($title, $sections) {
    print "<h3>$title</h3>";
    print '<table class="wp-list-posts widefat fixed posts">';
    
    $columns = array('Name', 'Location', 'Files/Snapshot Date', 'Status');
    $header = ''; foreach(array_values($columns) as $column) $header .= "<th>$column</th>";
    print "<thead>$header</thead>";

    foreach($sections as $section) {
      print "<tr>";
      print "<td>".$this->display_section_name($section)."</td>";
      print "<td>".$section->location."</td>";
      print "<td><strong>".$section->files."</strong><br /><span style='color:#555;'>".$section->snapshot_made."</span></td>";
      print "<td>".$this->states[$section->watch == 0 ? 98 : $section->state]."</td>";
      print "</tr>";
    }
    
    print "</table>"; 
  }

  function display_section_name($section) {
    $watch_icon = $section->watch ? "watch.png" : "nowatch.png";
    $watch_action = $section->watch ? "nowatch" : "watch";
    $watch_link = $this->url("section-$watch_action",array('section'=>$section->id));
    $watch_action_link = ( function_exists('wp_nonce_url') ) ? wp_nonce_url($watch_link, "sentinel-$watch_action-".intval($section->id)) : $watch_link;

    $details_url = $this->url('details', array('section'=>$section->id));
    $details_link = ( function_exists('wp_nonce_url') ) ? wp_nonce_url($details_url, 'sentinel-details-'.$section->id) : $details_url;
    
    $snapshot_url = $this->url('snapshot', array('section'=>$section->id));
    $snapshot_link = ( function_exists('wp_nonce_url') ) ? wp_nonce_url($snapshot_url, 'sentinel-snapshot-'.$section->id) : $snapshot_url;
    
    $check_url = $this->url('check', array('section'=>$section->id));
    $check_link = ( function_exists('wp_nonce_url') ) ? wp_nonce_url($check_url, 'sentinel-check-'.$section->id) : $check_url;
  
    $output = "<a href='$watch_action_link'><img src='".plugins_url("images/$watch_icon" , __FILE__ )."' width='16' /></a>&nbsp;";
    $output .= "<strong>".$section->name."</strong><br />( ";
    if($section->state != WPS_STATE_NEW)
      $output .= "<a href='$details_link'>Details</a> | ";
    $snap = ($section->state == WPS_STATE_NEW ? "Create Snapshot" : "Refresh Snapshot");
    $output .= "<a href='$snapshot_link'>$snap</a> | ";
    $output .= "<a href='$check_link'>Perform Check</a>";
    $output .= " )";

    return $output;
  }
  
  function show_help() {
  ?>
  <div style='font-size:1.2em;margin-right:100px;'>
  <a href='<?php echo $this->url(); ?>'>&laquo; Back</a>
  <h3>How does this thing work?</h3>
  <p>As Wordpress grows in popularity, it also becomes a bigger target for the hacking community.  It is hard to think of anything more frustrating than finding that your site is redirecting or displaying content which is not your own.</p>
  <p>If you are hacked, there are four questions that you have to address:</p>
  <ol><li>How did they get in?</li><li>What did they change?</li><li>How do I undo the damage that was done?</li><li>How do I prevent them from getting in again?</li></ol>
  <p>The purpose of this plugin is to alert you when you have been hacked and to address questions 2 & 3.  Wordpress Sentinel acts as a watchdog that knows how your install is supposed to look and then alert you when something gets changed.</p>
  <h3>How do I use it?</h3>
  <p>First, install the plugin and go to the Wordpress Sentinel option under Settings.  It should list content under Wordpress, Themes and Plugins.</p>
  <p>Second, click the <strong>Snapshot Everything New</strong> button, and every file in your Wordpress install, as well as installed Themes and Plugins will be catalogued.</p>
  <p>Periodically, the plugin will check a portion of the items for which snapshots have been taken. If any changes are detected, an administrative message will be displayed in Wordpress Admin. If this happens, go back to the Wordpress Sentinel option under Settings. The offending item will be marked as <span style='font-weight:bold;color:red;'>Changed</span>. If you click details, you can see what files have been changed and you can determine if this was a valid change or an intrusion and take the appropriate action..</p>
  <h3>What if I'm the one making changes?</h3>
  <p>Obviously, the plugin cannot differentiate between a good change and a bad change, so if you make changes to a Theme or install a new Plugin, or even Upgrade Wordpress to a newer version, it is simply going to notice the change and let you know. When this happens (and it will happen), just go to the Wordpress Sentinel option, find the item that you changed or added, and Refresh the Snapshot. (The <strong>Snapshot Everything New</strong> button is a handy way to create initial snapshots after installing new themes and plugins.  It does not touch items which have previously been catalogued.)</p>
  <h3>What are Checksums and Why do I need Them?</h3>
  <p>Checksums are a way of looking at the contents of a file and building a hash.  If the file changes in any way, even if the size remains the same, the checksum will be different.  Enabling checksums adds extra security however, however this comes at a cost.  The added overhead can slow down a site if there are an inordinate number of files or if there are extremely large files that have to be processed.  The basic file checks compare the modification date and the file size.  This should provide adequate protection in most situations.</p>
  <h3>It is complaining because my sitemap updated - How do I fix this?</h3>
  To stop watching your sitemap files, do the following:
  <ol>
  <li>Go to the Wordpress Sentinel interface</li>
  <li>Under <em>Wordpress Root</em>, click the <strong>Detail</strong> link</li>
  <li>Find <em>sitemap.xml</em> in the list and click on the Eye Icon to the left of the filename</li>
  <li>Find <em>sitemap.xml.gz</em> (if it exists) in the list and click on the Eye Icon to the left of the filename</li>
  <li>Click the <strong>Back</strong> link to get back to the Sentinel main screen</li>
  <li>Under Wordpress Root, click the <strong>Perform Check</strong> link</li>
  </ol>
  The same process can be used to disable watching any specific file.
  <h3>I have a plugin that creates temp files in the plugin directory and gives false positives.  How do I fix this?</h3>
  To stop watching a specific plugin or theme, do the following:
  <ol>
  <li>Go to the Wordpress Sentinel interface</li>
  <li>Find the plugin or theme that you would like to not have watched</li>
  <li>Click on the Eye Icon to the left of the plugin or theme</li>
  <li>The Eye Icon will now show a red X indicating that the plugin or theme is not being watched</li>
  </ol>
  <h3>What do I do if I really have been hacked?</h3>
  <p>The first thing to do is to look at the Wordpress Sentinel page and figure out what items have been changed.  Take a screenshot and then look at the details of those items to see what files have been affected.  If Wordpress is changed, you need to replace every file that is changed, although usually removing the existing install and replacing it with a clean install is the best course.</p>
  <p>If a plugin has been corrupted, it needs to be completely removed and reinstalled.  Just updating over the existing install is not advised, as any malicious files that have been added would remain.</p>
  <p>If a theme has been corrupted, then things may get complicated.  If it is a stock theme that can be removed and reinstalled, then do that.  If it is a custom theme, then every modified file needs to be carefully examined and cleaned up.  You may need someone with advanced skills in site development to help separate the template content from the injected code.</p>
  <h3>How do I stop the hacker from getting back in?</h3>
  <p>That is really beyond the scope of this plugin.  The best course of action is to keep Wordpress as well as all plugins and themes up to date.  If you know the time the hack occurred (and this plugin can help you determine that) then it is also a good idea to have an Analyst look through your server logs and try to isolate the entry point.</p>
  <br /><br />
  Hope this plugin helps, and if you do need advanced help recovering from a hack or any other Wordpress assistance, feel free to contact me at <strong>ed.blogrescue@gmail.com</strong>.
  </div>
  <?php
  }
  
  function build_section_list() {
    $this->base = array();
    array_push($this->base, 
      new wordpresssentinel_section('Wordpress Root','wp_root',$this->wp_wordpress_dir, WPS_BASE, WPS_MODE_DIRECTORY));
    array_push($this->base, 
      new wordpresssentinel_section('Wordpress Includes','wp_includes',
        $this->wp_wordpress_dir.DIRECTORY_SEPARATOR.'wp-includes', WPS_BASE));
    array_push($this->base, 
      new wordpresssentinel_section('Wordpress Admin','wp_admin',
        $this->wp_wordpress_dir.DIRECTORY_SEPARATOR.'wp-admin', WPS_BASE));
      
    $this->themes = array();
    $themes = get_themes();
    foreach($themes as $k=>$v) {
      $section = new wordpresssentinel_section($v["Name"], $v["Template"], $v["Template Dir"], WPS_THEME);
      array_push($this->themes, $section);
    }
    
    $this->plugins = array();
    $plugins = get_plugins();
    foreach($plugins as $k=>$v) {
      $dir = $this->wp_plugin_dir . (dirname($k)== '.' ? '' : DIRECTORY_SEPARATOR.dirname($k));
      $section = new wordpresssentinel_section($v["Name"], basename($k), $dir, WPS_PLUGIN, 
        (dirname($k)== '.' ? WPS_MODE_FILE : WPS_MODE_RECURSIVE));
      array_push($this->plugins, $section);
    }
    
    $this->remove_unused_sections();
  }
  
  function remove_unused_sections() {
    $valid_section_ids = array();
    foreach($this->base as $section)
        array_push($valid_section_ids, $section->id);
    foreach($this->themes as $section)
        array_push($valid_section_ids, $section->id);
    foreach($this->plugins as $section)
        array_push($valid_section_ids, $section->id);

    global $wpdb;
    $sql = "SELECT section_id FROM ".$wpdb->prefix."wordpresssentinel_section";
    $results = $wpdb->get_results($sql);
    foreach($results as $result) {
      if(!in_array($result->section_id, $valid_section_ids)) {
        $this->remove_section($result->section_id);
      }
    }
  }
  
  function view_details($section_id) {
    global $wpdb; 
    $sql = "SELECT * FROM ".$wpdb->prefix."wordpresssentinel_section WHERE section_id = %d";
    $section_row = $wpdb->get_row($wpdb->prepare($sql, (int)$section_id));    
    
    if($section_row == null) {
      print "<div id='message' class='error' style='margin-top:8px;'>";
      print "<p>Cannot display details for section '$section_id' - Section Not Found</p></div>";
      return;
    }

    if(isset($this->args['watch'])) {
      $this->set_file_watch_status($this->arg('watch'), 1);
    }
    if(isset($this->args['nowatch'])) {
      $this->set_file_watch_status($this->arg('nowatch'), 0);
    }
    
    print "<a href='".$this->url()."'>&laquo; Back</a>";
    print "<h3>Snapshot Details for: ".$section_row->name."</h3>";
    print '<table class="wp-list-posts widefat posts">';
    
    $columns = array('File', 'Size', 'Modified', 'Status');
    $header = ''; foreach(array_values($columns) as $column) $header .= "<th>$column</th>";
    print "<thead>$header</thead>";

    $sql = "SELECT * FROM ".$wpdb->prefix."wordpresssentinel_file WHERE section_id = %d ORDER BY location";
    $section_files = $wpdb->get_results($wpdb->prepare($sql, (int)$section_id));  
    
    foreach($section_files as $section_file) {
      $icon = $section_file->watch ? "watch.png" : "nowatch.png";
      $action = $section_file->watch ? "nowatch" : "watch";
      $link = $this->url('details',array('section'=>$section_id,$action=>$section_file->file_id));
      $action_link = ( function_exists('wp_nonce_url') ) ? wp_nonce_url($link, 'sentinel-details-'.intval($section_id)) : $link;
      
      $alt_size = '';
      $alt_date = '';
      $alt_state = '';
      if($section_file->status == WPS_STATE_CHANGED) {
        $alt_size = "<br /><span style='color:red;'>".$section_file->changed_size."</span>";
        $alt_date = "<br /><span style='color:red;'>".$section_file->changed_date."</span>";
        $alt_state = "<br /><span style='color:red;'>&laquo; To</span>";
      }

      $watch = "<a href='$action_link'><img src='".plugins_url("images/$icon" , __FILE__ )."' width='16'></a>";
      print "<tr>";
      print "<td>$watch&nbsp;&nbsp;".$section_file->location."</td>";
      print "<td>".$section_file->size.$alt_size."</td>";
      print "<td>".$section_file->update_date.$alt_date."</td>";
      print "<td>".$this->states[$section_file->status].$alt_state."</td>";
      print "</tr>";
    }
    
    print "<tfoot>$header</tfoot>";
    print "</table>";
  }
  
  function set_file_watch_status($file_id, $watch_status) {
    global $wpdb;
    $wpdb->update($wpdb->prefix.'wordpresssentinel_file', 
      array('watch'=>$watch_status), array('file_id'=>intval($file_id)));
  }

  function set_section_watch_status($section_id, $watch_status) {
    global $wpdb;
    $wpdb->update($wpdb->prefix.'wordpresssentinel_section', 
      array('watch'=>$watch_status), array('section_id'=>intval($section_id)));
  }
  
  function build_all_new_snapshots() {
    $update_list = array();
    $all_sections = array_merge($this->base, $this->themes, $this->plugins);
    
    foreach($all_sections as $section) {
      if($section->state == WPS_STATE_NEW) {
        $this->build_snapshot($section->id, false);
        $update_list[] = $section->name;
      }
    }
    print "<div id='message' class='updated' style='margin-top:8px;'><p>Snapshots Updated for: <strong>".
      (count($update_list) ? join(", ", $update_list) : "No New Items Found") ."</p></strong></div>";
  }
  
  function build_snapshot($section_id, $display_message = true) {
    global $wpdb; 
    $sql = "SELECT * FROM ".$wpdb->prefix."wordpresssentinel_section WHERE section_id = %d";
    $section_row = $wpdb->get_row($wpdb->prepare($sql, (int)$section_id));    
    
    if($section_row == null) {
      print "<div id='message' class='error' style='margin-top:8px;'><p>Cannot generate snapshot for section '$section_id' - Section Not Found</p></div>";
      return;
    }
    
    $ignored_files = $this->get_ignored_files($section_id);
    $this->reset_section($section_id);
    
    $file_list = $this->get_files($section_row);
    foreach($file_list as $file_key=>$file_object) {
      $this->add_section_file($section_id, $file_key, $file_object, !in_array($file_object->file, $ignored_files));
    }
    
    $updates = array( 
      'status'=>WPS_STATE_OK, 
      'files'=>count($file_list),
      'snapshot_made'=>date('Y-m-d H:i:s'), 
      'last_checked'=>date('Y-m-d H:i:s')
    );
    $this->update_section_record($section_id, $updates);
    
    if($display_message)
      print "<div id='message' class='updated' style='margin-top:8px;'><p>Snapshot Updated for <strong>".$section_row->name.
        "</p></strong></div>";
  }
  
  function get_ignored_files($section_id) {
    global $wpdb;
    $ignored_files = array();
    
    $sql = "SELECT location FROM ".$wpdb->prefix."wordpresssentinel_file WHERE section_id = %d AND watch = 0";
    $results = $wpdb->get_results($wpdb->prepare($sql, (int)$section_id));    
    foreach($results as $result) $ignored_files[] = $result->location;
    return $ignored_files;
  }
  
  function add_section_file($section_id, $file_key, $file_object, $watch=1) {
    global $wpdb;
    $mysql_date = date("Y-m-d H:i:s", $file_object->date);
    $fields = array('section_id'=>$section_id, 'file_ref'=>$file_key, 'location'=>$file_object->file,
        'size'=>$file_object->size, 'update_date' => $mysql_date, 'checksum'=>$file_object->checksum, 
        'status'=>WPS_STATE_OK, 'watch'=>$watch);
    $formats = array('%d', '%s', '%s', '%d', '%s', '%s', '%d', '%d');
    $result = $wpdb->insert($wpdb->prefix.'wordpresssentinel_file', $fields, $formats);
  }
  
  function set_section_status($section_id, $status) {
    global $wpdb;
    $wpdb->update($wpdb->prefix.'wordpresssentinel_section', 
      array('status'=>$status), array('section_id'=>$section_id));
  }

  function remove_section($section_id) {
    global $wpdb;
    $wpdb->query("DELETE FROM ".$wpdb->prefix."wordpresssentinel_file WHERE section_id = $section_id");
    $wpdb->query("DELETE FROM ".$wpdb->prefix."wordpresssentinel_section WHERE section_id = $section_id");
  }
  
  function reset_section($section_id) {
    global $wpdb;
    $wpdb->update($wpdb->prefix.'wordpresssentinel_section', 
      array('status'=>WPS_STATE_NEW,'files'=>0,'snapshot_made'=>0,'last_checked'=>0), 
      array('section_id'=>$section_id));
      
    $wpdb->query("DELETE FROM ".$wpdb->prefix."wordpresssentinel_file WHERE section_id = $section_id");
  }

  function check_all_sections() {
    $all_sections = array_merge($this->base, $this->themes, $this->plugins);
    $all_errors = 0;
    foreach($all_sections as $section) {
      $all_errors += $this->check_section($section->id, false);
    }
    
    if($all_errors) {
      print "<div id='message' class='error' style='margin-top:8px;'><p>Check Performed for <strong>Everything</strong> <em>(Possible Issues Found)</em></p></div>";
    } else {
      print "<div id='message' class='updated' style='margin-top:8px;'><p>Check Performed for <strong>Everything</strong></p></div>";
    }
  }
  
  function check_section($section_id, $display_message = true) {
    global $wpdb; 
    $sql = "SELECT * FROM ".$wpdb->prefix."wordpresssentinel_section WHERE section_id = %d";
    $section_row = $wpdb->get_row($wpdb->prepare($sql, (int)$section_id));    
    
    if($section_row == null) {
      print "<div id='message' class='error' style='margin-top:8px;'><p>Cannot perform check for section '$section_id' - Section Not Found</p></div>";
      return;
    }
    
    $error_count = 0;
    $this->set_section_status_before_check($section_id);
    $section_actual_files = $this->get_files($section_row);

    $sql = "SELECT * FROM ".$wpdb->prefix."wordpresssentinel_file WHERE section_id = %d";
    $section_snapshot_files = $wpdb->get_results($wpdb->prepare($sql, (int)$section_id));    
    
    foreach($section_snapshot_files as $snapshot_file) {
      $file_id = $snapshot_file->file_id;
      $updates = array();
      if(!isset($section_actual_files[sha1($snapshot_file->location)])) {
        $updates['status'] = WPS_STATE_MISSING;
        $error_count++;
      } else {
        $actual_file = $section_actual_files[sha1($snapshot_file->location)];
        $difference = ($snapshot_file->size != $actual_file->size) ||
          ($snapshot_file->update_date != date("Y-m-d H:i:s", $actual_file->date));
        if($this->use_checksums == true) $difference = $difference || ($snapshot_file->checksum != $actual_file->checksum);
        
        if($difference) {
          if($snapshot_file->watch == 0) {
            $updates['status'] = WPS_STATE_ALLOWED;
          } else {
            $updates['status'] = WPS_STATE_CHANGED;
            $updates['changed_size'] = $actual_file->size;
            $updates['changed_date'] = date("Y-m-d H:i:s", $actual_file->date);
            $updates['changed_checksum'] = $actual_file->checksum;
            $error_count++;
          }
        } else {
          $updates['status'] = WPS_STATE_OK;
        }
        unset($section_actual_files[sha1($snapshot_file->location)]);
      }
      
      $this->update_file_record($file_id, $updates);      
    }
    
    foreach($section_actual_files as $extra_file) {
      $data = array('location'=>$extra_file->file, 'changed_size'=>$extra_file->size,
        'changed_date'=>date('Y-m-d H:i:s', $extra_file->date), 'changed_checksum'=>$extra_file->checksum, 
        'status'=>WPS_STATE_ADDED,'section_id'=>$section_id);
      $format = array('%s', '%d', '%s', '%s', '%d');
      $wpdb->insert($wpdb->prefix.'wordpresssentinel_file', $data, $format);
      $error_count++;
    }
    
    $updates = array('status'=>($error_count ? WPS_STATE_CHANGED : WPS_STATE_OK), 'last_checked'=>date('Y-m-d H:i:s'));
    $this->update_section_record($section_id, $updates);
    
    if($display_message) {
      if($error_count) {
        print "<div id='message' class='error' style='margin-top:8px;'><p>Check Performed for <strong>".$section_row->name.
          "</strong> <em>(Possible Issues Found)<em></p></div>";
      } else {
        print "<div id='message' class='updated' style='margin-top:8px;'><p>Check Performed for <strong>".$section_row->name."</strong></p></div>";
      }
    }
    
    return $error_count;
  }
  
  function update_section_record($section_id, $updates) {
    global $wpdb;
    $wpdb->update($wpdb->prefix.'wordpresssentinel_section', $updates, array('section_id'=>$section_id));
  }

  function update_file_record($file_id, $updates) {
    global $wpdb;
    $wpdb->update($wpdb->prefix.'wordpresssentinel_file', $updates, array('file_id'=>$file_id));
  }
  
  function set_section_status_before_check($section_id) {
    global $wpdb;
    $wpdb->update($wpdb->prefix.'wordpresssentinel_section', array('status'=>WPS_STATE_CHECK), array('section_id'=>$section_id));
    
    $wpdb->query("DELETE FROM ".$wpdb->prefix."wordpresssentinel_file WHERE status = ".WPS_STATE_ADDED);
    
    $data = array('status'=>WPS_STATE_CHECK, 'changed_size'=>0, 'changed_date'=>'', 'changed_checksum'=>'');
    $where = array('section_id'=>$section_id);
    $wpdb->update($wpdb->prefix.'wordpresssentinel_file', $data, $where);
  }
  
  function get_files($section_row) {
    $files = array();
    
    if($section_row->mode == WPS_MODE_FILE) {
      $file_location  = $section_row->location . DIRECTORY_SEPARATOR . $section_row->slug;
      $files[sha1($file_location)] = new wordpresssentinel_file($file_location, $this->use_checksums);
    } else {
      $this->process_directory($files, $section_row->mode, $section_row->location);
    }
    
    return $files;
  }

  function process_directory(&$files, $mode, $path) {
    $handle = opendir($path);
    $skip = array('.', '..', 'cache', 'error_log');
    
    while($entry = readdir($handle)) {
      if(in_array($entry, $skip)) continue;
      
      $resource = $path.DIRECTORY_SEPARATOR.$entry;

      if(is_dir($resource)) {
        if($mode == WPS_MODE_RECURSIVE) $this->process_directory($files, $mode, $resource);
      } else {
        $files[sha1($resource)] = new wordpresssentinel_file($resource, $this->use_checksums);
      }
    }
    closedir($handle);
  }
  
  function snap_file($section_id, $resource) {
  }
  
  function url($action='', $url_args=array()) {
    $url_result = $this->uri;
    $url_result .= "?page=".$this->admin_page;
    if(!empty($action)) {
      $url_result .= "&action=$action";
      $skip = array('page', 'action');
    
      foreach($url_args as $key=>$val) {
        if(!in_array($key, $skip)) $url_result .= "&".esc_html($key)."=".esc_html($val);
      }
    }
      
    return $url_result;
  }
  
  function arg($key, $default='') {
    $value = esc_html($this->value($this->args, $key, $default));
    return ($value);
  }

  function value($data, $key, $default='') {
    return is_array($data) ? (isset($data[$key]) ? $data[$key] : $default) : $default;
  }

  function parse_uri() {
    $this->uri = preg_replace('/\?.*$/', '', esc_html($_SERVER['REQUEST_URI']));
    parse_str($_SERVER['QUERY_STRING'], $this->args);
  }
  
}
class wordpresssentinel_section {
  var $name;
  var $slug;
  var $location;
  var $type;
  var $state = WPS_STATE_NEW;
  var $mode = WPS_MODE_RECURSIVE;
  var $id = 0;
  var $files = 0;
  var $watch = 1;
  var $snapshot_made = "no snapshot";
  var $last_checked = "never checked";
  
  function wordpresssentinel_section($name, $slug, $location, $type, $mode=WPS_MODE_RECURSIVE) {
    global $wpdb;
    
    $this->name = $name;
    $this->slug = $slug;
    $this->location = $location;
    $this->type = $type;
    $this->mode = $mode;
    $this->set_section_id();
  }
  
  function set_section_id() {
    global $wpdb;
    $sql = "SELECT section_id, status, files, snapshot_made, last_checked, watch FROM ".
      $wpdb->prefix."wordpresssentinel_section WHERE type = %s AND name = %s AND location = %s";
    $result = $wpdb->get_row($wpdb->prepare($sql, $this->type, $this->name, $this->location));
    
    if($result == null) {
      $fields = array('type'=>$this->type, 'name'=>$this->name, 'slug'=>$this->slug,
        'location'=>$this->location, 'mode' => $this->mode, 'status'=>WPS_STATE_NEW, 
        'snapshot_made'=>'0000-00-00 00:00:00', 'last_checked'=>'0000-00-00 00:00:00');
      $formats = array('%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s');
      $result = $wpdb->insert($wpdb->prefix.'wordpresssentinel_section', $fields, $formats);
      $this->id = $wpdb->insert_id;
    } else {
      $this->id = $result->section_id;
      $this->state = $result->status;
      $this->files = $result->files;
      $this->snapshot_made = $result->snapshot_made == '0000-00-00 00:00:00' ? 'no snapshot' : $result->snapshot_made;
      $this->last_checked = $result->last_checked == '0000-00-00 00:00:00' ? 'never checked' : $result->last_checked;
      $this->watch = $result->watch;
    }
  }
}

class wordpresssentinel_file {
  var $file;
  var $size;
  var $checksum;
  var $date;
  
  function wordpresssentinel_file($location, $use_checksums) {
    $this->file = $location;
    $this->size = filesize($location);
    $this->date = filemtime($location);

    if($use_checksums == true && $this->size < 50000) {
      $this->checksum = sha1_file($location);
    } else {
      $this->checksum = 'not used';
    }
  }
}

function wordpress_sentinel_update_db_check() {
  global $wordpress_sentinel_db_version;
    
  if (get_site_option('wordpress_sentinel_db_version') != $wordpress_sentinel_db_version)
    wordpress_sentinel_install();
}

function wordpress_sentinel_check() {
  global $wordpress_sentinel;
  $wordpress_sentinel->site_check();
}

function wordpress_sentinel_admin() {
  global $wordpress_sentinel;
  $wordpress_sentinel->admin_panel();
}

function wordpress_sentinel_add_menu() {
  add_submenu_page('options-general.php', 'Wordpress Sentinel', 'Wordpress Sentinel', 'manage_options', 'wordpress_sentinel', 'wordpress_sentinel_admin');
}

function wordpress_sentinel_admin_warning() {
  print "<div id='wp-sentinel-warning' class='error fade'><p><strong>Wordpress Sentinel:</strong> ";
  print "Install files have changed. (<a href='options-general.php?page=wordpress_sentinel'>Details</a>)</p></div>";
}

function plugin_setting_links($links, $file) {
  if (method_exists($this, 'addPluginSettingLinks')) {
    $links = $this->addPluginSettingLinks($links, $file);
  } else {
    $this_plugin = plugin_basename(__FILE__);
    if ($file == $this_plugin) {
      $settings_link = '<a href="options-general.php?page=wordpress_sentinel">Settings</a>';
        array_unshift($links, $settings_link); // before other links
      }
    }
  return $links;
}

global $wordpress_sentinel;
$wordpress_sentinel = new wordpress_sentinel();

if(is_admin()) {
  add_action('admin_menu', 'wordpress_sentinel_add_menu');
  add_action('admin_init', 'wordpress_sentinel_check');
  add_filter('plugin_action_links', 'plugin_setting_links', 10, 2 );
}
add_action('plugins_loaded', 'wordpress_sentinel_update_db_check');