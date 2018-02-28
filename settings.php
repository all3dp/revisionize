<?php
/*  
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

namespace Revisionize;

require_once 'addon.php';

load_addons();

add_action('admin_init', __NAMESPACE__.'\\settings_admin_init');
add_action('admin_menu', __NAMESPACE__.'\\settings_menu');

function settings_admin_init() {
  setup_settings();
}

function settings_menu() {
  add_submenu_page (
    'options-general.php',
    'Revisionize Settings',
    'Revisionize',
    'manage_options',
    'revisionize',
    __NAMESPACE__.'\\settings_page'
  );
}

function settings_page() {
  if (!current_user_can('manage_options')) {
    echo 'Not Allowed.';
    return;
  }
  ?>
  <div class="wrap">
    <style type="text/css">
    .rvz-settings-form {
      margin-top: 15px;
    }
    .rvz-settings-form .form-table {
      margin-top: 0;
    }
    .rvz-settings-form .form-table th, .rvz-settings-form .form-table td {
      padding-top: 12px;
      padding-bottom: 12px;
    }
    .rvz-settings-form .form-table p {
      margin-top: 0;
    }
    .rvz-addons {  }
    .rvz-addons * { box-sizing: border-box; }
    .rvz-addon {
      float: left;
      width: 25%;
      height: 300px;
      padding: 15px 40px 15px 0;
    }
    .rvz-addon h3 {
      background-color: orange;
      border-radius: 3px;
      color: white;
      padding: 0 10px;
      line-height: 30px;
      text-transform: uppercase;
      width: 100%;
    }
    .rvz-addon p, .rvz-addon ul {
      padding: 0 10px;
    }
    .rvz-addon ul {
      list-style: disc;
      padding-left: 25px;
    }
    .rvz-addon .rvz-button {
      display: block;
      width: 200px;
      margin: 0 auto;
      background-color: blue;
      color: white;
      text-align: center;
      line-height: 30px;
      text-decoration: none;
      border-radius: 3px;
    }
    </style>
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <form action="options.php" enctype="multipart/form-data" method="post" class="rvz-settings-form">
    <?php
      settings_fields('revisionize');
  
      do_fields_section('revisionize_section_basic');

      // settings from Addons
      do_action('revisionize_settings_fields');

      do_fields_section('revisionize_section_addons');

      submit_button('Save Settings');

    ?>
    </form>

    <?php addons_html(); ?>
  </div>
  <?php 
}

function do_fields_section($key) {  
  echo '<table class="form-table">';
  do_settings_fields('revisionize', $key);
  echo '</table>';
}

function setup_settings() {
  register_setting('revisionize', 'revisionize_settings', array(
    "sanitize_callback" => __NAMESPACE__.'\\on_settings_saved'
  ));

  setup_basic_settings();
  setup_addon_settings();

}

function setup_basic_settings() {
  add_settings_section('revisionize_section_basic', '', '__return_null', 'revisionize');  

  checkbox_setting('Keep Backup', 'keep_backup', "After publishing the revision, the previously live post will be kept around and marked as a backup revision of the new version.", true, 'revisionize_section_basic', 'revisionize_keep_original_on_publish', __NAMESPACE__.'\\filter_keep_backup');


  checkbox_setting('Preserve Date', 'preserve_date', "The date of the original post will be maintained even if the revisionized post date changes. In particular, a scheduled revision won't modify the post date once it's published.", true, 'revisionize_section_basic', 'revisionize_preserve_post_date', __NAMESPACE__.'\\filter_preserve_date');

}

function setup_addon_settings() {
  add_settings_section('revisionize_section_addons', '', '__return_null', 'revisionize');

  // These fields are displayed
  add_settings_field('revisionize_addon_file', __('Upload Addon', REVISIONIZE_I18N_DOMAIN), __NAMESPACE__.'\\settings_addon_file_html', 'revisionize', 'revisionize_section_addons', array('label_for' => 'revisionize_addon_file'));  
}

function settings_addon_file_html($args) {
  $id = esc_attr($args['label_for']);
  ?>
  <div>
    <input id="<?php echo $id?>" type="file" name="revisionize_addon_file" style="width:320px" accept=".rvz"/> 
    <p>To install or update an addon, choose a <em>.rvz</em> file and click <em>Save Settings</em></p>
  </div>  
  <?php  
}

function addons_html() {
  ?>
  <h1>Revisionize Addons</h1>
  <p>Improve the free Revisionize plugin with these official addons.</p>
  <div class="rvz-addons">
    <?php foreach (settings_get_available_addons() as $addon) addon_html($addon); ?>
  </div>
  <?php
}

function addon_html($addon) {
  ?>
  <div class="rvz-addon<?php if ($addon['installed']) echo " rvz-installed" ?>">
    <h3><?php echo $addon['name'];?></h3>
    <p><?php echo nl2br($addon['description']); ?></p>
    <p>
      <?php if ($addon['installed']): ?>
      Installed: <?php echo $addon['installed']?>
      <?php else: ?>
      <a class="rvz-button" href="<?php echo $addon['url']?>" target="_blank">$<?php echo $addon['price']?> - Add to Cart</a>
      <?php endif; ?>
    </p>
  </div>
  <?php
}

// access settings
function get_setting($key, $default='') {
  $settings = get_option('revisionize_settings');  
  return !empty($settings[$key]) ? $settings[$key] : $default;
}

function is_on_settings_page() {
  global $pagenow;
  return $pagenow == 'options-general.php' && $_GET['page'] == 'revisionize';
}

function on_settings_saved($settings) {
  if (!empty($_FILES['revisionize_addon_file']['tmp_name'])) {
    install_addon($_FILES['revisionize_addon_file']['tmp_name']);
  }
  return $settings;
}

function install_addon($filename) {
  // make sure the directory exists
  $target_path = REVISIONIZE_ROOT.'/addons';
  wp_mkdir_p($target_path);

  $data = file_get_contents($filename);
  $data = json_decode(base64_decode($data), true);

  // TODO: check to see if addon already installed and if this version is newer. Maybe send warning if not (downgrading)
  file_put_contents($target_path.'/'.$data['name'].'.php', base64_decode($data['code']));
}

function get_installed_addons() {
  return apply_filters('revisionize_installed_addons', array());
}

function settings_get_available_addons() {
  $addons = array(
    array(
      "id" => "enhanced_settings",
      "name" => "Enhanced Settings",
      "description" => "Enhance the settings panel with features that help keep your revisions more organized. Includes settings for:<ul><li>Trashing old revisions</li><li>Displaying a revision ID</li><li>Excluding post types</li></ul>",
      "url" => "https://revisionize.pro/",
      "price" => "10"
    ), array(
      "id" => "contributors_can",
      "name" => "Contributors Can",
      "description" => "Users who can create posts, such as those with the built-in Contributor role, can also revisionize existing posts. They still cannot publish the revision - only someone with publish capabilities, like an Editor or Administrator can.",
      "url" => "https://revisionize.pro/",
      "price" => "10"
    )
  ); 

  $installed = get_installed_addons();
  foreach ($addons as &$addon) {
    $addon["installed"] = array_key_exists($addon["id"], $installed) ? $installed[$addon["id"]] : false;
  } 

  return $addons;
}

function load_addons() {
  $addons = settings_get_available_addons();
  foreach ($addons as $addon) {
    $file = REVISIONIZE_ROOT.'/addons/'.$addon['id'].'.php';
    if (file_exists($file)) {
      require_once $file;
      \RevisionizeAddon::create($addon['id']);
    }
  }

  do_action('revisionize_addons_loaded');
}

function filter_keep_backup($b) {
  return is_checkbox_checked('keep_backup', $b);
}

function filter_preserve_date($b) {
  return is_checkbox_checked('preserve_date', $b);
}

function checkbox_setting($name, $key, $description, $default, $section, $filter=null, $handler=null) {
  add_settings_field('revisionize_setting_'.$key, $name, __NAMESPACE__.'\\field_checkbox', 'revisionize', $section, array(
    'label_for' => 'revisionize_setting_'.$key,
    'key' => $key,
    'description' => $description,
    'default' => $default
  ));

  if ($filter && $handler) {
    add_filter($filter, $handler);
  }
}

function field_checkbox($args) {
  $id = esc_attr($args['label_for']);
  $key = esc_attr($args['key']);
  $checked = is_checkbox_checked($key, $args['default']);
  ?>
  <div>
    <input type="hidden" name="revisionize_settings[_<?php echo $key?>_set]" value="1"/>
    <label>
      <input id="<?php echo $id?>" type="checkbox" name="revisionize_settings[<?php echo $key?>]" <?php if ($checked) echo "checked"?>/> 
      <?php echo $args['description']?>
    </label>
  </div>  
  <?php  
}

function is_checkbox_checked($key, $default) {
  return is_checkbox_set($key) ? is_checkbox_on($key) : $default;
}

function is_checkbox_on($key) {
  return get_setting($key) == "on";    
}

function is_checkbox_set($key) {
  return get_setting('_'.$key.'_set') == "1";    
}



// function pro_api_status_checked_notice() {
//   $notice = 'fetch_api_key_status called';
//   echo '<div class="notice notice-warning is-dismissible"><p>'.$notice.'</p></div>';    
// }

// function pro_update_available_notice() {
//   $notice = __('A new version of Revisionize PRO is available. Go to <a href="'.admin_url('options-general.php?page=revisionize').'">settings</a> to update.', REVISIONIZE_I18N_DOMAIN);
//   echo '<div class="notice notice-warning is-dismissible"><p>'.$notice.'</p></div>';  
// }
// function pro_do_update_notice() {
//   $notice = __('A new version of Revisionize PRO is available. <a href="'.admin_url('options-general.php?page=revisionize&pro_do_update=1').'">Update Now</a>.', REVISIONIZE_I18N_DOMAIN);
//   echo '<div class="notice notice-warning is-dismissible"><p>'.$notice.'</p></div>';  
// }

