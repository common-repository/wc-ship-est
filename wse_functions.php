<?php
/*
Plugin Name: Ship Estimate for WooCommerce
Plugin URI: https://richardlerma.com/plugins/
Description: Add a Delivery Estimate or Shipping Method Description to WooCommerce with a simple, fast and lightweight plugin.
Author: RLDD
Author URI: https://richardlerma.com/contact/
Requires Plugins: woocommerce
Version: 2.0.17
Text Domain: wc-ship-est
Copyright: (c) 2019-2024 - rldd.net - All Rights Reserved
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html
WC requires at least: 7.0
WC tested up to: 9.1
*/

global $wp_version,$wse_version,$wse_pro_version,$wse_version_type; $wse_version='2.0.17';
$wse_version_type='GPL';
$wse_pro_version=get_option('wse_pro_version');
if(function_exists('wse_pro_activate')) $wse_version_type='PRO';
if(!defined('ABSPATH')) exit;

function wse_error() {file_put_contents(dirname(__file__).'/install_log.txt', ob_get_contents());}
if(defined('WP_DEBUG') && true===WP_DEBUG) add_action('activated_plugin','wse_error');

function wse_activate($upgrade) {
  global $wpdb,$wse_version;
  require_once(ABSPATH.basename(get_admin_url()).'/includes/upgrade.php');
  update_option('wse_db_version',$wse_version,'no');
  if(function_exists('wse_pro_ping'))wse_pro_ping();
  wse_update_methods();
}
register_activation_hook(__FILE__,'wse_activate');

function wse_add_action_links($links) {
  $settings_url=get_admin_url(null,'admin.php?page=wc-ship-est');
  $support_url='https://richardlerma.com/contact/';
  $links[]='<a href="'.$support_url.'">Support</a>';
  array_push($links,'<a href="'.$settings_url.'">Settings</a>');
  return $links;
}
add_filter('plugin_action_links_'.plugin_basename(__FILE__),'wse_add_action_links');

function wse_uninstall() {
  $uninstall=get_option('wse_uninstall');
  if($uninstall=='delete') {wse_r("DELETE FROM wp_options WHERE option_name LIKE 'wse_%' OR option_name LIKE 'wse:%';");}
}
register_uninstall_hook(__FILE__,'wse_uninstall');

function wse_is_path($pages) {
  if(stripos($pages,'order-received')!==false) if(function_exists('is_wc_endpoint_url')) if(is_wc_endpoint_url('order-received')) return true;
  if(stripos($pages,'/cart')!==false) if(function_exists('is_cart')) if(is_cart()) return true;
  if(stripos($pages,'/checkout')!==false) if(function_exists('is_checkout')) if(is_checkout()) return true;

  $page_array=explode(',',$pages);
  $current_page=strtolower($_SERVER['REQUEST_URI']);
  foreach($page_array as $page) {
    if(strpos($current_page,strtolower($page))!==false) return true;
  }
  return false;
}

function wse_admin_notice() {
  if(!wse_is_path('ajax,cron,page=wc-ship-est')){
    require_once(ABSPATH."wp-includes/pluggable.php");
    if(current_user_can('manage_options')) {
      $settings_url=get_admin_url(null,'admin.php?page=wc-ship-est'); ?>
      <div class="notice notice-success is-dismissible" style='margin:0;'>
        <p><?php _e("The <em>WC Ship Estimate</em> plugin is active, but is not yet configured. Visit the <a href='$settings_url'>configuration page</a> to complete setup.",'Ship Estimate');?>
      </div><?php
    }
  }
}

function wse_checkConfig() {
  if(empty(get_option('wse_methods'))) add_action('admin_notices','wse_admin_notice');
}
add_action('admin_init','wse_checkConfig');

function wse_r($q,$t=NULL) {
  global $wp_version;
  if(function_exists('r')) return r($q,$t);
  include_once(ABSPATH.'wp-includes/pluggable.php');
  if(version_compare('6.1',$wp_version)>0) require_once(ABSPATH.'wp-includes/wp-db.php');
  else require_once(ABSPATH.'wp-includes/class-wpdb.php');
  
  global $wpdb;
  if(!$wpdb) $wpdb=new wpdb(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST);
  $prf=$wpdb->prefix;
  $s=str_replace(' wp_',' '.$prf,$q);
  $s=str_replace($prf.str_replace('wp_','',$prf),$prf,$s);
  $r=$wpdb->get_results($s,OBJECT);
  if($r) return $r;
}

add_action('before_woocommerce_init',function() {
	if(class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class )) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables',__FILE__,true);
	}
});

function wse_update_methods($force=0) {
  global $wse_version;
  if($force<1 && get_option('wse_method_update')==$wse_version) return;
  $methods=get_option('wse_methods');
  if(!empty($methods)) {
    $new_methods=array();
    foreach($methods as $m) {
      $method=get_option("wse:$m");
      $ud=0;$mi=1;
      if(!stripos($m,':')) {
        $ud=1;
        delete_option("wse:$m");
        if(is_numeric(substr($m,-$mi))) {
          while(is_numeric(substr($m,-$mi))) $mi++;
          $mi--;
          $m=substr($m,0,-$mi).':'.substr($m,-$mi);
        }
        update_option("wse:$m",$method);
      }
      array_push($new_methods,$m);
    }
    if($ud>0) update_option('wse_methods',$new_methods);
  }
  update_option('wse_method_update',$wse_version);
}

function wse_adminMenu() {
  add_submenu_page('woocommerce','Ship Estimate','Ship Estimate','manage_options','wc-ship-est','wse_admin');

  function wse_admin() {
    global $wp_version,$wse_version,$wse_pro_version,$wse_version_type;
    $get_version=wse_r("SELECT @@version as version;");
    if($get_version) foreach($get_version as $row):$mysql_version=$row->version;endforeach;
    if(isset($_POST['wse_methods']) && check_admin_referer('config_wse','wse_config')) {
      wse_update_methods(1);
      $wse_methods=array_map('sanitize_text_field',$_POST['wse_methods']); update_option('wse_methods',$wse_methods);
      $wse_desc=array_map('sanitize_text_field',$_POST['wse_desc']); update_option('wse_desc',$wse_desc);
      $wse_append_desc=array_map('sanitize_text_field',$_POST['wse_append_desc']); update_option('wse_append_desc',$wse_append_desc);
      
      $wse_mn_days=array_map('sanitize_text_field',$_POST['wse_mn_days']); update_option('wse_mn_days',$wse_mn_days);
      $wse_mx_days=array_map('sanitize_text_field',$_POST['wse_mx_days']); update_option('wse_mx_days',$wse_mx_days);
      $wse_bz_days=array_map('sanitize_text_field',$_POST['wse_bz_days']); update_option('wse_bz_days',$wse_bz_days);
      
      $wse_mon=array_map('sanitize_text_field',$_POST['wse_mon']); update_option('wse_mon',$wse_mon);
      $wse_tue=array_map('sanitize_text_field',$_POST['wse_tue']); update_option('wse_tue',$wse_tue);
      $wse_wed=array_map('sanitize_text_field',$_POST['wse_wed']); update_option('wse_wed',$wse_wed);
      $wse_thu=array_map('sanitize_text_field',$_POST['wse_thu']); update_option('wse_thu',$wse_thu);
      $wse_fri=array_map('sanitize_text_field',$_POST['wse_fri']); update_option('wse_fri',$wse_fri);
      $wse_sat=array_map('sanitize_text_field',$_POST['wse_sat']); update_option('wse_sat',$wse_sat);
      $wse_sun=array_map('sanitize_text_field',$_POST['wse_sun']); update_option('wse_sun',$wse_sun);
      $wse_hol=array_map('sanitize_text_field',$_POST['wse_hol']); update_option('wse_hol',$wse_hol);
      
      $del_fri=array_map('sanitize_text_field',$_POST['wse_del_fri']); update_option('wse_del_fri',$del_fri);
      $del_sat=array_map('sanitize_text_field',$_POST['wse_del_sat']); update_option('wse_del_sat',$del_sat);
      $del_sun=array_map('sanitize_text_field',$_POST['wse_del_sun']); update_option('wse_del_sun',$del_sun);
      $del_hol=array_map('sanitize_text_field',$_POST['wse_del_hol']); update_option('wse_del_hol',$del_hol);

      $wse_ctf=array_map('sanitize_text_field',$_POST['wse_ctf']); update_option('wse_ctf',$wse_ctf);
      $wse_date=array_map('sanitize_text_field',$_POST['wse_date']); update_option('wse_date',$wse_date);

      $prd_dsp=array_map('sanitize_text_field',$_POST['wse_prd_dsp']); update_option('wse_prd_dsp',$prd_dsp);
      $prd_desc=array_map('sanitize_text_field',$_POST['wse_prd_desc']); update_option('wse_prd_desc',$prd_desc);

      $prds_dsp=array_map('sanitize_text_field',$_POST['wse_prds_dsp']); update_option('wse_prds_dsp',$prds_dsp);
      $prdx_dsp=array_map('sanitize_text_field',$_POST['wse_prdx_dsp']); update_option('wse_prdx_dsp',$prdx_dsp);

      $wse_prds=array_map('sanitize_text_field',$_POST['wse_prds']); update_option('wse_prds',$wse_prds);
      $prd_mn_days=array_map('sanitize_text_field',$_POST['wse_prd_mn_days']); update_option('wse_prd_mn_days',$prd_mn_days);
      $prd_mx_days=array_map('sanitize_text_field',$_POST['wse_prd_mx_days']); update_option('wse_prd_mx_days',$prd_mx_days);
      $prd_bk_days=array_map('sanitize_text_field',$_POST['wse_prd_bk_days']); update_option('wse_prd_bk_days',$prd_bk_days);
      $dt_format=sanitize_text_field($_POST['dt_format']); update_option('wse_dt_format',$dt_format);
      $def_bk_days=intval($_POST['wse_def_bk_days']); update_option('wse_def_bk_days',$def_bk_days);
      $def_bk_desc=sanitize_text_field($_POST['wse_def_bk_desc']); update_option('wse_def_bk_desc',$def_bk_desc);

      $cart_ct=intval($_POST['wse_cart_ct']); update_option('wse_cart_ct',$cart_ct);
      $blo_ct=intval($_POST['wse_blo_ct']); update_option('wse_blo_ct',$blo_ct);
      $bli_ct=intval($_POST['wse_bli_ct']); update_option('wse_bli_ct',$bli_ct);

      $vac_begin=sanitize_text_field($_POST['wse_vac_begin']); update_option('wse_vac_begin',$vac_begin);
      $vac_end=sanitize_text_field($_POST['wse_vac_end']);     update_option('wse_vac_end',$vac_end);
      $prds_vac=sanitize_text_field($_POST['wse_prds_vac']); update_option('wse_prds_vac',$prds_vac);
      $prdx_vac=sanitize_text_field($_POST['wse_prdx_vac']); update_option('wse_prdx_vac',$prdx_vac);
      $prd_dsp_title=intval($_POST['wse_prd_dsp_title']); update_option('wse_prd_dsp_title',$prd_dsp_title);

      $rvw_prompt=sanitize_text_field($_POST['wse_rvw_prompt']); update_option('wse_reviews',$rvw_prompt);
      $merchant_id=sanitize_text_field($_POST['wse_merchant_id']); if(empty($merchant_id)) $merchant_id=sanitize_text_field($_POST['wse_merchant_id2']); update_option('wse_merchant_id',$merchant_id);
      if(isset($_POST['wse_rvw_loc'])) $wse_rvw_loc=sanitize_text_field($_POST['wse_rvw_loc']); else $wse_rvw_loc=''; update_option('wse_reviews_opt',$wse_rvw_loc);

      $wse_rvw_badge=sanitize_text_field($_POST['wse_rvw_badge']); update_option('wse_rvw_badge',$wse_rvw_badge);
      if(isset($_POST['wse_rvw_badge_loc'])) $wse_rvw_badge_loc=sanitize_text_field($_POST['wse_rvw_badge_loc']); else $wse_rvw_badge_loc=''; update_option('wse_rvw_badge_loc',$wse_rvw_badge_loc);

      $wse_holidays=sanitize_text_field($_POST['wse_holidays']); update_option('wse_holidays',$wse_holidays);

      $wse_in_email=intval($_POST['wse_in_email']); update_option('wse_in_email',$wse_in_email);
      $wse_in_email_desc=sanitize_text_field($_POST['wse_in_email_desc']); update_option('wse_in_email_desc',$wse_in_email_desc);

      $wse_uninstall=sanitize_text_field($_POST['wse_uninstall']); update_option('wse_uninstall',$wse_uninstall);
    } else {
      $wse_methods=get_option('wse_methods');
      $wse_desc=get_option('wse_desc');
      $wse_append_desc=get_option('wse_append_desc');
      $wse_mn_days=get_option('wse_mn_days');
      $wse_mx_days=get_option('wse_mx_days');
      $wse_bz_days=get_option('wse_bz_days');
      
      $wse_mon=get_option('wse_mon');
      $wse_tue=get_option('wse_tue');
      $wse_wed=get_option('wse_wed');
      $wse_thu=get_option('wse_thu');
      $wse_fri=get_option('wse_fri');
      $wse_sat=get_option('wse_sat');
      $wse_sun=get_option('wse_sun');
      $wse_hol=get_option('wse_hol');
      
      $del_fri=get_option('wse_del_fri');
      $del_sat=get_option('wse_del_sat');
      $del_sun=get_option('wse_del_sun');
      $del_hol=get_option('wse_del_hol');
      
      $wse_ctf=get_option('wse_ctf');
      $wse_date=get_option('wse_date');
      
      $prd_dsp=get_option('wse_prd_dsp');
      $prd_desc=get_option('wse_prd_desc');
      
      $prds_dsp=get_option('wse_prds_dsp');
      $prdx_dsp=get_option('wse_prdx_dsp');
      
      $wse_prds=get_option('wse_prds');
      $prd_mn_days=get_option('wse_prd_mn_days');
      $prd_mx_days=get_option('wse_prd_mx_days');
      $prd_bk_days=get_option('wse_prd_bk_days');
      $dt_format=get_option('wse_dt_format');
      $def_bk_days=get_option('wse_def_bk_days');
      $def_bk_desc=get_option('wse_def_bk_desc');
      
      $cart_ct=get_option('wse_cart_ct');
      $blo_ct=get_option('wse_blo_ct');
      $bli_ct=get_option('wse_bli_ct');
      
      $vac_begin=get_option('wse_vac_begin');
      $vac_end=get_option('wse_vac_end');
      $prds_vac=get_option('wse_prds_vac');
      $prdx_vac=get_option('wse_prdx_vac');
      $prd_dsp_title=get_option('prd_dsp_title');
      
      $rvw_prompt=get_option('wse_reviews');
      $merchant_id=get_option('wse_merchant_id');
      $wse_rvw_loc=get_option('wse_reviews_opt');
      
      $wse_rvw_badge=get_option('wse_rvw_badge');
      $wse_rvw_badge_loc=get_option('wse_rvw_badge_loc');
      
      $wse_holidays=get_option('wse_holidays');

      $wse_in_email=get_option('wse_in_email');
      $wse_in_email_desc=get_option('wse_in_email_desc');
      
      $wse_uninstall=get_option('wse_uninstall');
    }
    
    if(empty($wse_mon)) { // if ver<=1.3.6
      $del_sat=$wse_sat;
      $del_sun=$wse_sun;
      $del_hol=$wse_hol;
    }

    if(empty($wse_methods)) $wse_holidays='0101,1225,0704';
    if(empty($wse_in_email)) $wse_in_email=1;
    
    function wse_get_methods($methods,$sel_method) {
      $list='';
      if(!empty($methods)) {
        foreach($methods as $m) {
          $zone=ucwords($m->zone);
          $method_id=$m->method_id;
          $method="$method_id:{$m->instance_id}";
          $method_title=unserialize($m->meta)['title'];
          update_option("wse:$method",$method_title);
          if($sel_method==$method) $sel='selected'; else $sel='';
          if($method_title=='Default') $method_title=ucwords(str_replace('_',' ',$method_id));
          $list.="<option $sel value='$method'>$zone - $method_title";
        }
      }
      return $list;
    }
    
    function wse_get_prds($prd_array,$sel_prd,$filter_prd='') {
      $list='';
      if(!empty($prd_array)) {
        foreach($prd_array as $p) {
          if(!empty($filter_prd) && $p->type!=$filter_prd) continue;
          $prd_id=$p->product_id;
          $product_title=$p->product;
          if($sel_prd==$prd_id) $sel='selected'; else $sel='';
          $list.="<option $sel value='$prd_id'>$product_title";
        }
      }
      return $list;
    }

    function wse_num_list($sel) {
      if(empty($sel)) $sel=0;
      $options=$apm='';
      $hr=$dsp=9;
      $loop=1;
      while($loop<=24){
        if($hr>23) $hr=0;
        if($hr==1) $dsp=1;
        if($hr<12) $apm='AM'; else $apm='PM';
        if($dsp>=13) $dsp=$dsp-12;
        if($hr==$sel) $df='selected'; else $df='';
        $options.="<option $df value='$hr'>$dsp $apm</option>";
        $loop++; $dsp++; $hr++;
      }
      return $options;
    }
    
    $install_alert=$wc_methods='';
    if(!in_array('woocommerce/woocommerce.php',apply_filters('active_plugins',get_option('active_plugins')))) {
      $wse_method_list='';
      if(!function_exists('wse_itm_est')) $install_alert.="
      <div class='wse_alert'>
        WooCommerce is required for the Ship Estimate plugin to work. <a href='https://wordpress.org/plugins/woocommerce/' target='_blank'>Download for free</a> or <a href='plugins.php' target='_blank'>Activate</a>.
      </div><br>";
    } else {
      $wc_methods=wse_r("
        SELECT IFNULL(zone_name,'Other Zones')zone,method_id,instance_id
        ,IFNULL((SELECT option_value FROM wp_options o WHERE o.option_name LIKE 'woocommerce_f%_settings' 
        AND CONVERT(o.option_name,CHAR(99))=CONCAT('woocommerce_',CONVERT(m.method_id,CHAR(99)),'_',CONVERT(m.instance_id,CHAR(10)),'_settings')),'a:1:{s:5:\"title\";s:7:\"Default\";}')meta
        FROM wp_woocommerce_shipping_zone_methods m 
        LEFT JOIN wp_woocommerce_shipping_zones z ON m.zone_id=z.zone_id
        ORDER BY z.zone_order, m.method_order;
      ");
    }
    $wc_prds=wse_r("
      SELECT p.ID product_id
      ,p.post_type type
      ,CONCAT(p.post_title,CASE WHEN p.post_parent>0 AND p.post_title NOT LIKE CONCAT('%',RIGHT(p.post_excerpt,LOCATE(':',REVERSE(p.post_excerpt))-1)) THEN IFNULL(CONCAT(' - ',p.post_excerpt),'') ELSE '' END)product
      FROM wp_posts p
      LEFT JOIN wp_posts pr ON pr.ID=p.post_parent
      WHERE (p.post_type='product' OR p.post_type='product_variation')
      AND IFNULL(pr.post_status,'publish')!='trash'
      AND p.post_status!='trash'
      ORDER BY product;
    ");

    if(empty($install_alert) && !function_exists('wse_itm_est') && !isset($_REQUEST['tab']) && $wse_version!==get_option('wse_dismiss_upgrade')) {
      if(isset($_GET['wse_dismiss_upgrade'])) update_option('wse_dismiss_upgrade',$wse_version,'no');
      else $install_alert.="
      <div style='margin:3em'>&nbsp;</div>
      <div style='position:absolute;margin:-4em 0 0 4em;background:#935584;font-weight:500;font-size:1.3em;color:#fff;padding:1em;border-radius:5px'>
        New Features Available <a href='?page=wc-ship-est&wse_dismiss_upgrade' style='font-size:.8em;color:#ffffff7a;margin-left:1em'>dismiss</a>
        <span class='dashicons dashicons-arrow-down' style='position:absolute;bottom:-0.55em;zoom:2;right:5%;color:#935584;'></span>
      </div>";
    } ?>

      <div class='wrap'>
        <div>
          <img style='width:4em' src='<?php echo plugins_url('/assets/icon-256x256.png',__FILE__);?>'>
          <h2 style='display:inline-block;vertical-align:top;letter-spacing:.2em;font-variant-caps:all-petite-caps;color:#0071b2;'>Ship Estimate for WooCommerce</h2>
        </div>
        <?php echo $install_alert;?>

        <script>
          function wse_getE(e) {return document.getElementById(e);}

          function add_prd_choice(e,i,s,option_txt='') {
            user_dsp=wse_getE('user'+s+i);
            prd_dsp=wse_getE('wse'+s+i);
            if(option_txt=='') option_txt=e.options[e.selectedIndex].text;
            if(wse_getE('p'+i+':'+e.value)) {if(confirm('Product already selected. Remove it?'))rmv_prd_choice(wse_getE('p'+i+':'+e.value),i,s);}
            else {
              if(user_dsp.innerHTML=='All Products') user_dsp.innerHTML='';
              user_dsp.innerHTML+="<div id='p"+i+':'+e.value+"' onclick=\"rmv_prd_choice(this,'"+i+"','"+s+"')\";>"+option_txt+'</div>';
              if(!prd_dsp.value.includes(','+e.value+',')) prd_dsp.value+=e.value+',';
            }
            e.value=0;
          }
          function rmv_prd_choice(e,i,s) {
            e.remove();
            user_dsp=wse_getE('user'+s+i); if(user_dsp.innerHTML=='') user_dsp.innerHTML='All Products';
            prd_dsp=wse_getE('wse'+s+i);
            prd=e.id.replace('p'+i+':','');
            prd_dsp.value=prd_dsp.value.replace(','+prd+',',',');
            wse_csv(prd_dsp);
          }
          function pop_prd_choice(i,s) {
            prd_ids=wse_getE('wse'+s+i).value.split(',').filter(Boolean);
            user_dsp=wse_getE('user'+s+i);
            prd_opt=wse_getE('sel'+s+i).options;
            prd_ids.forEach(function(prd_id){add_prd_choice({value:prd_id,options:prd_opt},i,s,wse_opt_txt(prd_opt,prd_id))});
            if(user_dsp.innerHTML=='') user_dsp.innerHTML='All Products';
            wse_csv(wse_getE('wse'+s+i));
          }
          function wse_opt_txt(options,value) {
            for(var j=0; j<options.length;j++) {if(options[j].value===value) return options[j].text;}
          }
          function wse_csv(e) {
            if(e.value.slice(0,1)!=',')e.value=','+e.value;
          }
          function wse_tab(t) {
            wse_getE('wse_tab').value=t;
            var tre=document.querySelectorAll('#wse_admin tr');
            tre.forEach(tr=>{
              var sel=tr.classList.contains(t);
              if(tr.classList.contains('form_save')) sel=true;
              tr.style.display=sel?'table-row':'none';
            });
            var navItems=document.getElementsByClassName('nav-tab');
            for(var i=0; i<navItems.length; i++) {
              var navItem=navItems[i];
              var isT=navItem.onclick.toString().includes("'"+t+"'");
              if(isT) navItem.classList.add('nav_sel'); else navItem.classList.remove('nav_sel');
            }
          }
        </script>

        <div id='wse_tabs' style='display:flex'>
          <a href='#!' class='nav-tab' onclick="wse_tab('methods')">Methods</a>
          <a href='#!' class='nav-tab' onclick="wse_tab('products')">Product Rules</a>
          <a href='#!' class='nav-tab' onclick="wse_tab('options')">Options</a>
          <a href='#!' class='nav-tab' onclick="wse_tab('pro')">PRO</a>
        </div>
        <form name='wse_admin' method='post' action='?page=wc-ship-est' onchange="unsaved_changes=true;" onsubmit="unsaved_changes=false;">
          <table id='wse_admin' style='background:#fff;border:1px solid #ddd;padding:1em;width:-webkit-fill-available;max-width:90em'>
            <tr class='methods'>
              <td nowrap>New Method</span></td>
              <td><a href='<?php echo admin_url('admin.php?page=wc-settings&tab=shipping');?>' class='page-title-action button'>Create a New Shipping Method</a> <div style='display:inline;margin:1em'> or use a method below.</div></td>
            </tr>

            <tr class='methods'>
              <td nowrap style='vertical-align:text-top'>Configured Methods</td>
              <td class='items'><?php
                $i=0;
                $m_ct=1;
                $now=current_datetime()->format('U');
                if(is_array($wse_methods)) $m_ct=count($wse_methods);
                if($m_ct>0) while($i<$m_ct) {
                  if(isset($wse_methods[$i])) $wse_method=$wse_methods[$i];
                  else {
                    $wse_method='';
                    $wse_sun[$i]=$wse_hol[$i]=$del_sun[$i]=$del_hol[$i]=1;
                  } ?>
                  <div id='wse_method_<?php echo $i;?>'>
                    <a href='#!' onclick="wse_add_item('add',this.parentElement,'wse_method');"><span class='dashicons dashicons-insert'></span></a>
                    <a href='#!' onclick="wse_add_item('remove',this.parentElement,'wse_method');" <?php if($i<1) echo "style='display:none'";?>><span class='dashicons dashicons-remove'></span></a>

                    <select required name='wse_methods[]'>
                      <option value='' selected disabled>Method
                      <option value='ALL' <?php if($wse_method=='ALL') echo 'selected';?>>Default (Fallback for ALL Methods)
                      <?php echo wse_get_methods($wc_methods,$wse_method); ?>
                    </select><br>

                    <input type='text' name='wse_desc[]' placeholder='Description' value="<?php if(isset($wse_desc[$i])) echo $wse_desc[$i]; ?>"> Displayed above estimate in Cart, e.g. <i>Economy</i> or <i>Estimated by</i> or <i>Expect your order in</i><br>
                    <input type='number' name='wse_mn_days[]' class='short regular-text' placeholder='Min Days' step='1' value="<?php if(isset($wse_mn_days[$i])) echo $wse_mn_days[$i]; ?>"> 
                    <input type='number' name='wse_mx_days[]' class='short regular-text' placeholder='Max Days' step='1' value="<?php if(isset($wse_mx_days[$i])) echo $wse_mx_days[$i]; ?>"> Min/Max Day Range<br>

                    <div class='est_date'>
                      <input type='hidden' name='wse_date[]' value=<?php if(!empty($wse_date[$i])) echo 1; else echo 0;?>>
                      <input type='checkbox' <?php if(!empty($wse_date[$i])) echo 'checked';?> onclick="wse_toggle_est(this.nextElementSibling.nextElementSibling,this.checked);wse_toggle_biz(this.parentElement.nextElementSibling,this.checked);wse_checkbox(this.previousElementSibling,this.checked);"> Display Exact Date <i>Required for Google Reviews</i>

                      <div <?php if(empty($wse_date[$i])) echo "style='display:none'";?>>
                        <div style='background:#f5f5f5'>
                          Shipping Cutoff Time 
                          <select name='wse_ctf[]' placeholder='Cutoff Time' style='margin:0'>
                            <?php if(isset($wse_ctf[$i])) $cutoff=$wse_ctf[$i]; else $cutoff=0; echo wse_num_list($cutoff);?>
                          </select> <i>Current System Time: <?php echo date("g:i a",$now);?></i></span>
                          <hr>
                          <b>Exclude Shipping</b> on<br>
                          <input type='hidden' name='wse_mon[]' value=<?php if(!empty($wse_mon[$i])) echo 1; else echo 0;?>>
                          <input type='checkbox' <?php if(!empty($wse_mon[$i])) echo 'checked';?> onclick="wse_checkbox(this.previousElementSibling,this.checked);"> Mon &nbsp;

                          <input type='hidden' name='wse_tue[]' value=<?php if(!empty($wse_tue[$i])) echo 1; else echo 0;?>>
                          <input type='checkbox' <?php if(!empty($wse_tue[$i])) echo 'checked';?> onclick="wse_checkbox(this.previousElementSibling,this.checked);"> Tue &nbsp;

                          <input type='hidden' name='wse_wed[]' value=<?php if(!empty($wse_wed[$i])) echo 1; else echo 0;?>>
                          <input type='checkbox' <?php if(!empty($wse_wed[$i])) echo 'checked';?> onclick="wse_checkbox(this.previousElementSibling,this.checked);"> Wed &nbsp;

                          <input type='hidden' name='wse_thu[]' value=<?php if(!empty($wse_thu[$i])) echo 1; else echo 0;?>>
                          <input type='checkbox' <?php if(!empty($wse_thu[$i])) echo 'checked';?> onclick="wse_checkbox(this.previousElementSibling,this.checked);"> Thu &nbsp;

                          <input type='hidden' name='wse_fri[]' value=<?php if(!empty($wse_fri[$i])) echo 1; else echo 0;?>>
                          <input type='checkbox' <?php if(!empty($wse_fri[$i])) echo 'checked';?> onclick="wse_checkbox(this.previousElementSibling,this.checked);"> Fri &nbsp;
                          
                          <input type='hidden' name='wse_sat[]' value=<?php if(!empty($wse_sat[$i])) echo 1; else echo 0;?>>
                          <input type='checkbox' <?php if(!empty($wse_sat[$i])) echo 'checked';?> onclick="wse_checkbox(this.previousElementSibling,this.checked);"> Sat &nbsp;

                          <input type='hidden' name='wse_sun[]' value=<?php if(!empty($wse_sun[$i])) echo 1; else echo 0;?>>
                          <input type='checkbox' <?php if(!empty($wse_sun[$i])) echo 'checked';?> onclick="wse_checkbox(this.previousElementSibling,this.checked);"> Sun &nbsp;

                          <input type='hidden' name='wse_hol[]' value=<?php if(!empty($wse_hol[$i])) echo 1; else echo 0;?>>
                          <input type='checkbox' <?php if(!empty($wse_hol[$i])) echo 'checked';?> onclick="wse_checkbox(this.previousElementSibling,this.checked);"> Holidays
                        </div>

                        <div>
                          <b>Exclude Delivery</b> on<br>
                          <input type='hidden' name='wse_del_fri[]' value=<?php if(!empty($del_fri[$i])) echo 1; else echo 0;?>>
                          <input type='checkbox' <?php if(!empty($del_fri[$i])) echo 'checked';?> onclick="wse_checkbox(this.previousElementSibling,this.checked);"> Fri &nbsp;
                          
                          <input type='hidden' name='wse_del_sat[]' value=<?php if(!empty($del_sat[$i])) echo 1; else echo 0;?>>
                          <input type='checkbox' <?php if(!empty($del_sat[$i])) echo 'checked';?> onclick="wse_checkbox(this.previousElementSibling,this.checked);"> Sat &nbsp;

                          <input type='hidden' name='wse_del_sun[]' value=<?php if(!empty($del_sun[$i])) echo 1; else echo 0;?>>
                          <input type='checkbox' <?php if(!empty($del_sun[$i])) echo 'checked';?> onclick="wse_checkbox(this.previousElementSibling,this.checked);"> Sun &nbsp;

                          <input type='hidden' name='wse_del_hol[]' value=<?php if(!empty($del_hol[$i])) echo 1; else echo 0;?>>
                          <input type='checkbox' <?php if(!empty($del_hol[$i])) echo 'checked';?> onclick="wse_checkbox(this.previousElementSibling,this.checked);"> Holidays
                        </div>

                      </div>
                    </div>

                    <div style='display:<?php if(!empty($wse_date[$i])) echo 'none'; else echo 'block';?>'>
                      <input type='hidden' name='wse_bz_days[]' value=0>
                      Text after estimate<br>
                      <input type='text' name='wse_append_desc[]' placeholder='Days' value="<?php if(!empty($wse_bz_days[$i]) && empty($wse_append_desc[$i])) echo 'Business Days'; elseif(isset($wse_append_desc[$i])) echo $wse_append_desc[$i]; else echo 'Days'; ?>"> e.g. <i>Days</i> or <i>Business Days</i>
                    </div>

                    <span class='prd_dsp' style='display:block'>
                      <input type='hidden' name='wse_prd_dsp[]' value=<?php if(!empty($prd_dsp[$i])) echo 1; else echo 0;?>>
                      <input type='checkbox' <?php if(function_exists('wse_itm_est')) {if(!empty($prd_dsp[$i])) echo 'checked';} else echo "style='opacity:.5;pointer-events:none'"; ?> onclick="wse_toggle_est(this.nextElementSibling,this.checked);wse_toggle_biz(this.parentElement.nextElementSibling,this.checked);wse_checkbox(this.previousElementSibling,this.checked);"> Display Estimate on Product Page (PRO)

                      <div style='background:#EEE;<?php if(empty($prd_dsp[$i])|| !function_exists('wse_itm_est')) echo "display:none;";?>'>
                        <input type='text' name='wse_prd_desc[]' placeholder='Description' value="<?php if(isset($prd_desc[$i])) echo $prd_desc[$i]; ?>"> Displayed before estimate on product page, e.g. <i>Get it by</i> or <i>Order now for delivery by</i> or <i>Free delivery in</i><br>

                        <div class='user_prds' id='user_prds_dsp<?php echo $i;?>'></div>
                        <select name='wse_prdx_dsp[]'>
                          <option value='1' <?php if(isset($prdx_dsp[$i]) && $prdx_dsp[$i]>0) echo 'selected';?>>Include Products</option>
                          <option value='-1'<?php if(isset($prdx_dsp[$i]) && $prdx_dsp[$i]<0) echo 'selected';?>>Exclude Products</option>
                        </select>
                        <select id='sel_prds_dsp<?php echo $i;?>' required onchange="if(this.value<0){wse_getE('wse_prds_dsp<?php echo $i;?>').style.display='block';this.value=0;return;}add_prd_choice(this,<?php echo $i;?>,'_prds_dsp');">
                          <option value='0' selected>Choose a Product
                          <option value='-1'>-- Add Product IDs Manually --
                          <?php echo wse_get_prds($wc_prds,'','product'); ?>
                        </select>
                        <br>
                        <input type='text' name='wse_prds_dsp[]' id='wse_prds_dsp<?php echo $i;?>' style='display:none;width:100%' value="<?php if(isset($prds_dsp[$i])) echo $prds_dsp[$i]; ?>" placeholder='Comma Separated Product Ids' onchange="wse_csv(this);">
                        <script>pop_prd_choice(<?php echo $i;?>,'_prds_dsp')</script>
                      </div>
                    </span>

                  </div><?php
                  $i++;
                } ?>
              </td>
            </tr>

            <tr class='products'>
              <td nowrap></td>
              <td><b>Note</b>: Products that use variations will use the main product ship estimate until a variation is added to the cart.</td>
            </tr>
            <tr class='products'>
              <td nowrap style='vertical-align:text-top'>Product Rules</td>
              <td class='items'><?php
                $i=0;
                $p_ct=1;
                if(is_array($wse_prds)) $p_ct=count($wse_prds);
                if($p_ct>0) while($i<$p_ct) {
                  if(isset($wse_prds[$i])) $wse_prd=$wse_prds[$i]; else $wse_prd=''; ?>
                  <div id='wse_prd_<?php echo $i;?>'>
                    <a href='#!' onclick="wse_add_item('add',this.parentElement,'wse_prd');"><span class='dashicons dashicons-insert'></span></a>
                    <a href='#!' onclick="wse_add_item('remove',this.parentElement,'wse_prd');" <?php if($i<1) echo "style='display:none'";?>><span class='dashicons dashicons-remove'></span></a>
                    
                    <select required name='wse_prds[]'>
                      <option value='0' selected <?php if(empty($wse_prd)) echo 'selected';?>>Choose a Product
                      <?php echo wse_get_prds($wc_prds,$wse_prd); ?>
                    </select><br>

                    <input type='number' name='wse_prd_mn_days[]' class='short regular-text' placeholder='Min Days' step='1' value="<?php if(isset($prd_mn_days[$i])) echo $prd_mn_days[$i]; ?>"> 
                    <input type='number' name='wse_prd_mx_days[]' class='short regular-text' placeholder='Max Days' step='1' value="<?php if(isset($prd_mx_days[$i])) echo $prd_mx_days[$i]; ?>"> 
                    <input type='number' name='wse_prd_bk_days[]' class='short regular-text' placeholder='Backorder' step='1' value="<?php if(isset($prd_bk_days[$i])) echo $prd_bk_days[$i]; ?>"> Min/Max/Backorder (Days added to the existing estimate)
                  </div><?php
                  $i++;
                } ?>
              </td>
            </tr>

            <tr class='options'>
              <td nowrap style='vertical-align:text-top'>Date Format<br></td>
              <td>
                <div style='margin-bottom:1em'>
                  <input type='text' name='dt_format' title='Default: D, M j' placeholder='D, M j' value="<?php if(isset($dt_format)) echo $dt_format; ?>"><br>
                  Leave blank for default as <b>Mon, Jan 1st</b><br>
                  <a href='https://www.w3schools.com/php/func_date_date_format.asp' target='_blank'>Other formats</a>
                </div>
              </td>
            </tr>

            <tr class='options'>
              <td nowrap style='vertical-align:text-top'>Backorder Defaults</td>
              <td class='items'>
                <span class="dashicons dashicons-info-outline"></span> Backorder defaults apply when any product in the cart is out of stock, backorders are permitted, and no product-specific rules exist.
                <div style='background:#f5f5f5;margin-top:1em'>
                  <input type='number' name='wse_def_bk_days' class='short regular-text' placeholder='Backorder' step='1' value="<?php if(isset($def_bk_days)) echo $def_bk_days; ?>"> Backorder Days (added to Method)
                </div>
                <input type='text' name='wse_def_bk_desc' placeholder='Description' value="<?php if(isset($def_bk_desc)) echo $def_bk_desc; ?>"> Shipping description, e.g. <i>2-3 weeks</i> or <i>On backorder</i><br>
                <b>Optional</b> This description displays in lieu of the calculated estimate on ALL shipping methods when Backorder Days (above) is not set.<br><br>
              </td>
            </tr>
            
            <tr class='options'>
              <td nowrap style='vertical-align:text-top'>Google Review Prompt</td>
              <td>
                <input type='hidden' name='wse_rvw_prompt' value=<?php if(!empty($rvw_prompt)) echo 1; else echo 0;?>>
                <input type='checkbox' <?php if(!empty($rvw_prompt)) echo 'checked';?> onclick="wse_toggle_est(this.nextElementSibling,this.checked);wse_checkbox(this.previousElementSibling,this.checked);"> Add Google Customer Reviews Prompt (on checkout confirmation)

                <div <?php if(empty($rvw_prompt)) echo "style='display:none'";?>><br>
                  <span class="dashicons dashicons-info-outline"></span> You must first <a href='https://merchants.google.com/mc/customerreviews/configuration' target='_blank'>enable Google Customer Reviews</a>.<br>
                  <input type='text' name='wse_merchant_id' placeholder='Google Merchant ID' value="<?php if(isset($merchant_id)) echo $merchant_id; ?>"><br>
                  <select name='wse_rvw_loc'>
                    <option value='' selected disabled>Prompt Location
                    <option value='CENTER_DIALOG' <?php if($wse_rvw_loc=='CENTER_DIALOG') echo 'selected';?>>Center Dialog
                    <option value='BOTTOM_TRAY' <?php if($wse_rvw_loc=='BOTTOM_TRAY') echo 'selected';?>>Bottom Tray
                    <option value='BOTTOM_RIGHT_DIALOG' <?php if($wse_rvw_loc=='BOTTOM_RIGHT_DIALOG') echo 'selected';?>>Bottom Right Dialog
                    <option value='BOTTOM_LEFT_DIALOG' <?php if($wse_rvw_loc=='BOTTOM_LEFT_DIALOG') echo 'selected';?>>Bottom Left Dialog
                    <option value='TOP_RIGHT_DIALOG' <?php if($wse_rvw_loc=='TOP_RIGHT_DIALOG') echo 'selected';?>>Top Right Dialog
                    <option value='TOP_LEFT_DIALOG' <?php if($wse_rvw_loc=='TOP_LEFT_DIALOG') echo 'selected';?>>Top Left Dialog
                  </select>
                </div>
              </td>
            </tr>

            <tr class='options'>
              <td nowrap style='vertical-align:text-top'>Google Reviews Badge<br></td>
              <td>
                <input type='hidden' name='wse_rvw_badge' value=<?php if(!empty($wse_rvw_badge)) echo 1; else echo 0;?>>
                <input type='checkbox' <?php if(!empty($wse_rvw_badge)) echo 'checked';?> onclick="wse_toggle_est(this.nextElementSibling.nextElementSibling,this.checked);wse_checkbox(this.previousElementSibling,this.checked);"> Display Google Customer Reviews Badge (in footer)
                <div><br><span class="dashicons dashicons-info-outline"></span> You must have at least <a href='https://support.google.com/merchants/answer/7105655?hl=en' target='_blank'>100 Google Customer Reviews in a particular country during the past year</a>.</div>

                <div <?php if(empty($wse_rvw_badge)) echo "style='display:none'";?>><br>
                  <img src="//lh3.googleusercontent.com/WCmXNMduGDBq9v2DVMEdRDfcjOQh7FBEXgXx9BoawNtm7KUgfyotOJwE6KcojQtkiIkP=w159" width="159" height="49" style='float:right'>
                  <input type='text' name='wse_merchant_id2' placeholder='Google Merchant ID' value="<?php if(isset($merchant_id)) echo $merchant_id; ?>"><br>
                  <select name='wse_rvw_badge_loc'>
                    <option value='' selected disabled>Badge Location
                    <option value='BOTTOM_RIGHT' <?php if($wse_rvw_badge_loc=='BOTTOM_RIGHT') echo 'selected';?>>Bottom Right
                    <option value='BOTTOM_LEFT' <?php if($wse_rvw_badge_loc=='BOTTOM_LEFT') echo 'selected';?>>Bottom Left
                  </select>
                </div>
              </td>
            </tr>

            <tr class='options'>
              <td nowrap style='vertical-align:text-top'>Holidays</td>
              <td>
                <span class="dashicons dashicons-info-outline"></span> Holidays are used when 'Display Estimated Delivery Date' <b>and</b> 'Exclude Holidays' are checked.<br><br>
                <textarea name='wse_holidays' style='width:100%' placeholder='0101,1225,0704,053121,090621,112521'><?php echo $wse_holidays; ?></textarea><br>
                Separate dates by comma or new line. Format like MMDD or MMDDYY.
              </td>
            </tr>

            <tr class='options'>
              <td nowrap style='vertical-align:text-top'>Email</td>
              <td>
                <input type='hidden' name='wse_in_email' value=<?php if($wse_in_email>=0) echo 1; else echo -1;?>>
                <input type='checkbox' <?php if($wse_in_email>=0) echo 'checked';?> onclick="wse_toggle_est(this.nextElementSibling,this.checked);wse_checkbox(this.previousElementSibling,this.checked,-1);"> Display Estimate in 'Processing order' Email
                <div <?php if($wse_in_email<0) echo "style='display:none'";?>><br>
                  <span class="dashicons dashicons-info-outline"></span> Customize a shipping description below to appear after the first paragraph, before the product list.<br>
                  <input type='text' name='wse_in_email_desc' placeholder='Delivery estimate: {ship_est}' style='width:50%' value="<?php if(isset($wse_in_email_desc)) echo $wse_in_email_desc; ?>"><br>
                  Use {ship_est} to display the date.
                </div>
              </td>
            </tr>

            <tr class='options'>
              <td nowrap style='vertical-align:text-top'>Advanced Options</td>
              <td>
                <br>
                <span class="dashicons dashicons-info-outline"></span> <b>Email Variable</b>
                  <div style='margin:0 1.5em'>
                    Use {ship_est} in the "Additional content" section of a WC email template.<br>
                    Recommended templates to edit: <a target='_blank' href='?page=wc-settings&tab=email&section=wc_email_customer_processing_order'>Processing order</a>, and <a target='_blank' href='?page=wc-settings&tab=email&section=wc_email_customer_invoice'>Customer invoice</a>.
                  </div>
                  <code style='display:block;margin:1em;padding:1em'>Your delivery estimate is {ship_est}.</code>

                <br><br>
                <span class="dashicons dashicons-info-outline"></span> <b>In Cart Estimate</b>
                  <div style='margin:0 1.5em'>
                    Text can be customized in some themes at: <a href='/wp-admin/customize.php'>Appearance > Customize</a> > Additional CSS:<br>
                    The following CSS will bold each shipping estimate.
                  </div>
                  <code style='display:block;margin:1em;padding:1em'>.woocommerce-shipping-methods label:after,.wc-block-components-totals-shipping .wc-block-components-radio-control__option:after,.wc-block-components-totals-item__description .wc-block-components-totals-shipping__via:after{color:#b085bb;font-weight:bold;}</code>

                <br><br>
                <span class="dashicons dashicons-info-outline"></span> <b>Shortcode</b>
                  <div style='margin:0 1.5em'>
                    Themes that do not use a traditional WC checkout, or heavily modified themes can use this shortcode on checkout:
                  </div>
                  <code style='display:block;margin:1em;padding:1em'>[display_ship_est]</code>
              </td>
            </tr>

            <tr class='options'>
              <td>Uninstall</td>
              <td>
                <select name='wse_uninstall'>
                  <option value='' selected disabled>Uninstall Preference
                  <option value='' <?php if($wse_uninstall=='') echo 'selected';?>>Keep all settings
                  <option value='delete' <?php if($wse_uninstall=='delete') echo 'selected';?>>Delete all settings
                </select>
              </td>
            </tr>

            <tr class='pro admin' style='background:aliceblue;font-size:1.1em'>
              <td nowrap style='color:#2271b1;vertical-align:text-top'><b>Ship Estimate PRO</b>
                <?php if(function_exists('wse_pro_activate')) { ?>
                  <div style='margin:1em 0'>
                    <span class="dashicons dashicons-image-rotate" style='color:#2271b1'></span>
                    <a style='font-weight:normal;font-size:.9em' href='<?php echo get_admin_url(null,'admin.php?page=wc-ship-est&pro_update=1&tab=pro');?>'>Check for Updates</a>
                  </div><?php 
                } ?>
              </td>
              <td>
                
                <div style='float:right;margin:-1em -1em -1em 1em;padding:2em;background:#dddbdb4f'>
                  <a href='#!' style='width:100%;text-align:center;margin:.5em 0;' class='button wse_btn' onclick="wse_getE('wse_diag').style.display='block';">Diagnostics</a>
                  <a href='https://richardlerma.com/contact/?imsg=' target='_blank' style='width:100%;text-align:center;margin: 0.5em 0;' class='button wse_btn' onclick="this.href+=append_diag('wse_diag');">Contact Support</a>
                </div>
                <style>#wse_admin .wse_btn{background:#2271b1;color:#fff;background-blend-mode:lighten;background-image:linear-gradient(#00000038 0 0);}#wse_admin .wse_btn:hover{background-blend-mode:darken;}</style>
                <?php if(function_exists('wse_itm_est')) { ?>
                  <div style='font-weight:bold;color:green'>Active
                    <br><a class='button wse_btn' style='background-color:green;border:seagreen;margin:1em 0;font-weight:normal;font-size:.9em' href='https://www.paypal.com/myaccount/autopay/' target='_blank'>Manage Subscription</a>
                  </div><?php 
                } ?>
                
                <div id='wse_diag' style='display:none;background:#fff;padding:1em;font-size:.9em'>
                  <b>Configuration</b><br>
                  Host <?php echo $_SERVER['HTTP_HOST'].'@'.$_SERVER['SERVER_ADDR']; ?><br>
                  Path <?php echo substr(plugin_dir_path( __FILE__ ),-34);?><br>
                  WP <?php echo $wp_version; if(is_multisite()) echo 'multi'; ?><br>
                  PHP <?php echo phpversion();?><br>
                  MYSQL <?php echo $mysql_version; if(!empty($config_mode)) echo $config_mode; ?><br>
                  Theme <?php $pt=wp_get_theme(get_template()); echo $pt->Name.' '.$pt->Version; $ct=wp_get_theme(); if($pt->Name!==$ct->Name) echo ', '.$ct->Name.' '.$ct->Version;?><br>
                  Ship Est <?php echo "$wse_version $wse_version_type $wse_pro_version"; ?><br>
                  <hr>
                  <b>Settings</b><br>
                  Methods: <?php if(is_array($wse_methods)) echo count($wse_methods); else echo 0;?><br>
                  CartCt: <?php echo $cart_ct;?><br>
                  Backlog OrderCt: <?php echo $blo_ct;?><br>
                  Backlog ItemCt: <?php echo $bli_ct;?><br>
                  Vac: <?php echo $vac_begin;?> - <?php echo $vac_end;?><br>
                  Backlog ItemCt: <?php echo $bli_ct;?><br>
                  Review Prompt: <?php echo $rvw_prompt;?><br>
                  Merchant: <?php echo $merchant_id;?><br>
                  Review Badge: <?php echo $wse_rvw_badge;?><br>
                  Holidays: <?php echo $wse_holidays;?><br>
                  Email: <?php echo $wse_in_email;?><br>
                  Uninstall: <?php echo $wse_uninstall;?><br>
                </div>

                <?php if(!function_exists('wse_itm_est')) { ?>
                <div style='background:#fff;padding:1em'>
                  <div style='color:#2271b1;font-size:1.1em;opacity:.7;margin-bottom:2em'>Features</div>
                  <ul style='list-style:disc;margin:2em'>
                    <li><b>Dynamic Estimates for any Order</b><br>Dynamically adjust delivery estimates based on the quantity of products in the shopping cart.
                    <li><b>Single Product Page Display</b><br>Configure Estimates on product pages displayed above the add to cart button, along with the flexibility to include or exclude products.
                    <li><b>Optimize for Sales Backlogs</b><br>Account for varying processing times by factoring in sales backlogs (orders in processing status).
                    <li><b>Vacation Mode with Precision</b><br>Plan ahead with vacation mode that supports a date range for your absence, along with the flexibility to include or exclude products.
                    <li><b>Dedicated Support</b><br>Dedicated support by email within an average 4 hour response time.
                  </ul>
                  <a style='margin:1em 0 0;padding:.5em 10%;' class='button wse_btn' href="https://richardlerma.com/wse-terms" target='_blank'>Learn More</a>
                  <style>tr.pro:not(.admin) input,tr.pro:not(.admin) select{opacity:.5;pointer-events:none}.pro.admin li{margin-bottom:1em}</style>
                </div>
                <?php } ?>
              </td>
            </tr>

            <tr class='pro'>
              <td nowrap style='vertical-align:text-top'>Cart Quantity Rules</td>
              <td class='items'>
                <span class="dashicons dashicons-info-outline"></span> Increase the delivery estimate based on the quantity of products in cart. Days are added to the existing estimate.
                <div style='background:#f5f5f5;margin-top:1em'>
                  <input type='number' name='wse_cart_ct' class='short regular-text' placeholder='Items' step='1' min=0 max=99 value="<?php if(isset($cart_ct)) echo $cart_ct; ?>" onchange="if(this.value<1) v='x'; else v=this.value; cart_ct1.innerHTML=cart_ct2.innerHTML=v;"> A day will be added for every <b id='cart_ct1'><?php if($cart_ct>0) echo $cart_ct; else echo 'x'; ?></b> items in the cart (after the first <b id='cart_ct2'><?php if($cart_ct>0) echo $cart_ct; else echo 'x'; ?></b>).<br>
                </div>
                <b>Example</b> If this value is set at 2, then 1 day will be added when 3 items are in the cart, and 5 days will be added when 10 items are in the cart.<br><br>
              </td>
            </tr>

            <tr class='pro'>
              <td nowrap style='vertical-align:text-top'>Sales Backlog</td>
              <td class='items'>
                <span class="dashicons dashicons-info-outline"></span> Increase the delivery estimate based on the sales backlog (orders in <b>processing</b> status). Days are added to the existing estimate.
                <div style='background:#f5f5f5;margin-top:1em'>
                  <input type='number' name='wse_bli_ct' class='short regular-text' placeholder='Items' step='1' min=0 max=99 value="<?php if(isset($bli_ct)) echo $bli_ct; ?>" onchange="if(this.value<1) v='x'; else v=this.value; bli_ct1.innerHTML=bli_ct2.innerHTML=v;"> A day will be added for every <b id='bli_ct1'><?php if($bli_ct>0) echo $bli_ct; else echo 'x'; ?></b> items in processing (after the first <b id='bli_ct2'><?php if($bli_ct>0) echo $bli_ct; else echo 'x'; ?></b>).<br>
                  <input type='number' name='wse_blo_ct' class='short regular-text' placeholder='Orders' step='1' min=0 max=99 value="<?php if(isset($blo_ct)) echo $blo_ct; ?>" onchange="if(this.value<1) v='x'; else v=this.value; blo_ct1.innerHTML=blo_ct2.innerHTML=v;"> A day will be added for every <b id='blo_ct1'><?php if($blo_ct>0) echo $blo_ct; else echo 'x'; ?></b> orders in processing (after the first <b id='blo_ct2'><?php if($blo_ct>0) echo $blo_ct; else echo 'x'; ?></b>).
                </div>
                <b>Example</b> If this value is set at 6, then 1 day will be added when 7 orders are processing, and 2 days will be added when 12 orders are processing.<br><br>
              </td>
            </tr>

            <tr class='pro'>
              <td nowrap style='vertical-align:text-top'>Product Estimate</td>
              <td>
                <span class="dashicons dashicons-info-outline"></span> Product Page Estimates are displayed above the add to cart button and configured on the Methods tab.
                  <div style="width:11em;float:right;padding:1em;background:#e4e4e4;color:#2871b1;">* Products that use variations will use the main product ship estimate until a variation is added to the cart.</div><br><br>
                  <div style='margin:0 1.5em'>
                    <input type='hidden' name='wse_prd_dsp_title' value=<?php if($prd_dsp_title>=0) echo 1; else echo -1;?>>
                    <input type='checkbox' <?php if($prd_dsp_title>=0) echo 'checked';?> onclick="wse_toggle_est(this.nextElementSibling.firstElementChild,this.checked);wse_checkbox(this.previousElementSibling,this.checked,-1);"> Display Estimate Title on Product Page*
                    <div class="wse_est" style="width:fit-content;font-size:12.5;font-family:sans-serif;margin-bottom:2em;padding:1em 2em 1em 1.5em;line-height:2em;backdrop-filter:brightness(97%);">
                      <span id='wse_est_title' style="display:<?php if($prd_dsp_title>=0) echo 'block'; else echo 'none';?>;font-weight:600;opacity:.6">
                        <img src='<?php echo plugin_dir_url(__FILE__);?>assets/icon-256x256.png' style="width:2em;display:inline-block;vertical-align: middle;filter:grayscale(100%);">
                        Delivery Estimate
                      </span>
                      <div title="Priority">Get it by Mon, Feb  5 - Wed, Feb  7</div>
                      <div title="Economy">Free delivery by Tue, Feb  6 - Fri, Feb  9</div>
                      <style>#wse_admin .wse_est div{margin-bottom:0!important;line-height:2em}</style>
                    </div>
                    Text can be customized in some themes at: <a href='/wp-admin/customize.php'>Appearance > Customize</a> > Additional CSS:<br>
                    The following CSS will bold each shipping estimate.
                  </div>
                  <code style='display:block;margin:1em;padding:1em'>.wse_est div{color:#b085bb;font-weight:bold;}</code>
              </td>
            </tr>

            <tr class='pro'>
              <td nowrap style='vertical-align:text-top'>Vacation</td>
              <td class='items'>
                <span class="dashicons dashicons-info-outline"></span> Vacation mode enables a complete lapse in shipping between the set dates.<br>
                <input type='date' name='wse_vac_begin' value="<?php if(isset($vac_begin)) echo $vac_begin; ?>"> <input type='date' name='wse_vac_end' value="<?php if(isset($vac_end)) echo $vac_end; ?>"><br><br>

                <div class='user_prds' id='user_prds_vac' style='background:#fff'></div>
                <select name='wse_prdx_vac'>
                  <option value='1' <?php if($prdx_vac>0) echo 'selected';?>>Include Products</option>
                  <option value='-1'<?php if($prdx_vac<0) echo 'selected';?>>Exclude Products</option>
                </select>
                <select id='sel_prds_vac' required onchange="if(this.value<0){wse_getE('wse_prds_vac').style.display='block';this.value=0;return;}add_prd_choice(this,'','_prds_vac');">
                  <option value='0' selected>Choose a Product
                  <option value='-1'>-- Add Product IDs Manually --
                  <?php echo wse_get_prds($wc_prds,''); ?>
                </select>
                <br>
                <input type='text' name='wse_prds_vac' id='wse_prds_vac' style='display:none;width:100%' value="<?php if(isset($prds_vac)) echo $prds_vac; ?>" placeholder='Comma Separated Product Ids' onchange="wse_csv(this);">
                <script>pop_prd_choice('','_prds_vac')</script>
              </td>
            </tr>

            <tr class='form_save' style='background:#fff'>
              <td colspan='2'>
                <a href='update-core.php' target='_blank' class='page-title-action button' style='margin-top:3em'>Check for Updates</a>
                <?php echo wp_nonce_field('config_wse','wse_config');?>
                <input type='hidden' id='wse_tab' name='tab'>
                <input type='submit' class='page-title-action' style='padding:1em 8em;float:right' value='Save' onclick='unsaved_changes=false;'>
              </td>
            </tr>

          </table>
        </form>
      </div>
      <style>
        .nav-tab{background:#e7e7eb}
        .nav-tab.nav_sel{background:#fff}
        #wse_admin td{padding:.5em 1em}
        #wse_admin i{color:#2271b1;font-size:.8em;font-family:sans-serif}
        .wse_alert{margin-top:1em;background:#fff;border:1px solid #ddd;padding:1em;border-left:5px solid #d82626}
        .dashicons{vertical-align:text-top;color:#207cb0;cursor:pointer}
        .dashicons-image-rotate,.dashicons-remove{color:#d82626}
        .dashicons-warning{color:orange}
        #wse_admin a{display:inline-block;cursor:pointer;text-decoration:none;outline:none;box-shadow:none}
        #wse_admin a:hover .dashicons{transform:scale(.9)}
        #wse_admin td{padding:1em}
        #wse_admin input,#wse_admin select{margin:.5em 0}
        #wse_admin select{vertical-align:inherit}
        #wse_admin input.short{width:100px}
        #wse_admin tr:nth-child(even){background:#f5f5f5}
        #wse_admin td.items div:not(.est_date){padding:1em;border:1px solid #ccc;border-radius:5px}
        #wse_admin td.items div:nth-child(even):not(.est_date){background:#fff}
        #wse_admin td.items div a{float:right;margin:.3em;zoom:1.5}
        #wse_admin td div{-webkit-transition:all .5s;transition:all .5s}
        #wse_admin td div:not(:last-child){margin-bottom:1em}
        #wse_admin .est_date{display:inline-block;margin-top:1em;max-width:85%}
        #wse_admin .wse_new{opacity:1}
        #wse_admin .wse_new select{background:#f3f5f6}
        #wse_admin div.wse_del{background:#d7008045!important;opacity:0}
        #wse_admin td.items div.user_prds div{display:inline-block;margin:.5em;padding:.5em;color:#2271b1;cursor:pointer;box-shadow:inset 0px 0px 2px 0px #ddd;background:#fafafa}
        #wse_admin td.items div.user_prds div:hover{border:1px solid darkred;color:darkslategray}
        #wse_admin td.items div.user_prds div:before{content:'X';margin-right:.5em;color:black}
        #wse_admin td.items div.user_prds div:hover:before{color:darkred}
        #wse_admin td div[id^=wse_method]{margin-bottom:2em}
        #wse_admin div select[name="wse_methods[]"]:first-of-type,#wse_admin div select[name="wse_prds[]"]:first-of-type{color:#2271b1;font-weight:600;font-size:1.3em}
        #wse_admin div select[name="wse_prds[]"]:first-of-type{font-size:1.1em}
        #wse_admin input[type='submit']:hover{background:#fff;color:#0071b2}
        #wse_admin input[type='submit']{color:#fff;background:#0071b2}
      </style>

      <script type='text/javascript'>
        wse_tab('<?php if(isset($_REQUEST['tab'])) echo sanitize_text_field($_REQUEST['tab']); else echo 'methods';?>');
        var m_inc=1;
        var unsaved_changes=false;
        var usc_interval=setInterval(function() {
          if(document.readyState==='complete') {
            clearInterval(usc_interval);
            window.onbeforeunload=function(){return unsaved_changes ? 'If you leave this page you will lose unsaved changes.' : null;}
        }},100);

        function wse_toggle_est(id,checked) {
          if(!id) return;
          if(checked>0) id.style.display='block'; else id.style.display='none';
        }

        function wse_toggle_biz(id,checked) {
          if(!id) return;
          if(checked>0) id.style.display='none'; else id.style.display='block';
        }

        function wse_checkbox(id,checked,off=0) {
          if(!id) return;
          if(checked>0) id.value=1; else id.value=off;
        }

        function wse_add_item(a,p,t) {
          unsaved_changes=1;
          var m=wse_getE(t+'_0');
          if(a=='remove') {if(m.id!==p.id && confirm('Are you sure you want to delete this item?')) {p.classList.add('wse_del');setTimeout(function(){p.remove();},500);} return false;}
          var i=document.createElement('div');
          m_inc++;
          i.id=t+'_'+m_inc;
          i.innerHTML=m.innerHTML;
          i.style.opacity=0;
          m.parentElement.append(i);
          i.firstElementChild.nextElementSibling.style.display='block';
          i.lastElementChild.style.display='block';
          if(t=='wse_method') {
            var inc=0;
            var elm=i.firstElementChild.nextElementSibling.nextElementSibling;
            while(inc<=6) {
              elm.value='';
              elm=elm.nextElementSibling;
              inc++;
            }
          }
          window.scrollTo(0,document.body.scrollHeight);
          setTimeout(function(){i.style.opacity='';i.classList.add('wse_new');},250);
        }

        function append_diag(diag) {
          var d=wse_getE(diag).innerHTML;
          d=d.replace(/  /g,'');
          d=d.replace(/(\r\n|\r|\n)/g,'%0A');
          d=d.replace(/<\/?[^>]+(>|$)/g,'');
          return 'Type your inquiry here%0A%0A%0ADiagnostics follow:%0A-------------%0A'+d;
        }
      </script><?php
  }
}
add_action('admin_menu','wse_adminMenu');

function wse_getlocale(){return get_locale();}//'fr_FR'

function wse_ship_span($ship_days,$exc_days,$exc_del_days,$exc_hol,$exc_del_hol,$hols,$ctf,$now) { // Push on excluded days
  $days=$shipped=0;
  while($days<=$ship_days) {
    $incr=0;
    $date=strtotime("+$days days");

    $dy=0;
    while($dy<=7 && $incr<1) {
      if($days>0 && date('N',$date)==$dy+1 && $exc_del_days[$dy]>0) $incr=1; // exc del day of wk
      elseif($shipped<1 && date('N',$date)==$dy+1 && $exc_days[$dy]>0) $incr=1; // exc ship day of wk
      $dy++;
    }

    if($incr<1) {
      if($days>0 && $exc_del_hol>0 && stripos($hols,date('md',$date))) $incr=1; // exc del holiday
      elseif($exc_hol>0 && $shipped<1 && stripos($hols,date('md',$date))) $incr=1; // exc ship holiday
    }

    if($incr<1 && $days<1 && $ctf>0 && date('G',$now)>=$ctf) $incr=1; // push if after cutoff time
    if($incr<1) $shipped=1; else $ship_days++;
    $days++;
  }
  return $ship_days;
}

function wse_sess() {
  if(!isset($_SERVER['HTTP_COOKIE'])) return;
  return sanitize_text_field(explode(';',$_SERVER['HTTP_COOKIE'])[0]);
}

function wse_prd_days($type,$prd_id=0) {
  if(is_admin()) return;
  global $woocommerce,$bko_prd;
  $ship_days=array();
  $prds=get_option('wse_prds');
  $p_ct=$apply_ct=$bko_prd=$cart_exists=0;
  if(is_array($prds)) $p_ct=count($prds);
  if($type=='min') $prd_days=get_option('wse_prd_mn_days');
  if($type=='max') $prd_days=get_option('wse_prd_mx_days');
  $def_bk_days=get_option('wse_def_bk_days');
  $def_bk_desc=get_option('wse_def_bk_desc');
  $prd_bk_days=get_option('wse_prd_bk_days');
  if($prd_id>0) {
    $i=0;
    if($p_ct>0) {
      while($i<$p_ct) {
        if($prds[$i]>0) {
          if($prd_id==$prds[$i]) {
            if(empty($prd_days[$i])) $prd_days[$i]=0; 
            if(wse_backordered($prd_id,$prd_id,1)>0) if($prd_bk_days[$i]>0) {$prd_days[$i]+=$prd_bk_days[$i]; $apply_ct++;} // Backorders
            if($prd_days[$i]>0) array_push($ship_days,$prd_days[$i]); // Record result to array
          }
        }
        $i++;
      }
    }
    if($apply_ct<1 && wse_backordered($prd_id,$prd_id,1)>0) {
      if(!empty($def_bk_days)) array_push($ship_days,$def_bk_days);
      elseif(!empty($def_bk_desc)) $bko_prd++;
    }
    $cart_exists++;
  }

  if(is_object($woocommerce->cart)) foreach($woocommerce->cart->get_cart() as $cart_item_keys=>$cart_item) {
    $i=$apply_ct=0;
    if($p_ct>0) {
      while($i<$p_ct) {
        if($prds[$i]>0) {
          if($cart_item['product_id']==$prds[$i] || $cart_item['variation_id']==$prds[$i]) {
            if(empty($prd_days[$i])) $prd_days[$i]=0; 
            if(wse_backordered($cart_item['product_id'],$cart_item['variation_id'],$cart_item['quantity'])>0) if($prd_bk_days[$i]>0) {$prd_days[$i]+=$prd_bk_days[$i]; $apply_ct++;} // Backorders
            if($prd_days[$i]>0) array_push($ship_days,$prd_days[$i]); // Record result to array
          }
        }
        $i++;
      }
    }
    if($apply_ct<1 && wse_backordered($cart_item['product_id'],$cart_item['variation_id'],$cart_item['quantity'])>0) {
      if(!empty($def_bk_days)) array_push($ship_days,$def_bk_days);
      elseif(!empty($def_bk_desc)) $bko_prd++;
    }
    set_transient("wse_cart_prd_$type".wse_sess(),$ship_days,3600); // For retrieval after checkout
    $cart_exists++;
  }
  if($cart_exists<1) $ship_days=get_transient("wse_cart_prd_$type".wse_sess());
  if(empty($ship_days)) return 0;
  rsort($ship_days); // Sort array descending
  return $ship_days[0]; // Return highest day total
}

function wse_backordered($product_id,$variation_id=0,$cart_qty=1) {
  $bop=wse_r("SELECT ID
    ,(SELECT meta_value FROM wp_postmeta WHERE meta_key='_manage_stock' AND post_id=p.ID LIMIT 1)p_manage_stock
    ,(SELECT meta_value FROM wp_postmeta WHERE meta_key='_stock_status' AND post_id=p.ID LIMIT 1)p_stock_status
    ,(SELECT meta_value FROM wp_postmeta WHERE meta_key='_backorders' AND post_id=p.ID AND meta_value IN ('yes','notify') LIMIT 1)p_backorders
    ,(SELECT meta_value FROM wp_postmeta WHERE meta_key='_stock' AND post_id=p.ID LIMIT 1)p_stock
    ,(SELECT meta_value FROM wp_postmeta WHERE meta_key='_manage_stock' AND post_id='$variation_id' LIMIT 1)v_manage_stock
    ,(SELECT meta_value FROM wp_postmeta WHERE meta_key='_stock_status' AND post_id='$variation_id' LIMIT 1)v_stock_status
    ,(SELECT meta_value FROM wp_postmeta WHERE meta_key='_backorders' AND post_id='$variation_id' AND meta_value IN ('yes','notify') LIMIT 1)v_backorders
    ,(SELECT meta_value FROM wp_postmeta WHERE meta_key='_stock' AND post_id='$variation_id' LIMIT 1)v_stock
    FROM wp_posts p
    WHERE p.ID=$product_id;");

  foreach($bop as $p) {
    $p_manage_stock=$p->p_manage_stock;
    $p_stock_status=$p->p_stock_status;
    $p_backorders=$p->p_backorders;
    $p_stock=$p->p_stock;
    
    $v_manage_stock=$p->v_manage_stock;
    $v_stock_status=$p->v_stock_status;
    $v_backorders=$p->v_backorders;
    $v_stock=$p->v_stock;
  }

  if($p_manage_stock=='yes') {
    if($p_stock_status=='onbackorder') return 1;
    if(!empty($p_backorders) && $cart_qty>$p_stock) return 1;
  }

  if($v_stock_status=='onbackorder') return 1;
  if($v_manage_stock=='yes') {
    if(!empty($v_backorders) && $cart_qty>$v_stock) return 1;
  }

  return 0;
}

function wse_ship_est($order_id=0,$op=0) {
  if(!class_exists('woocommerce')) return;
  if(!function_exists('wc_get_order_id_by_order_key')) return;
  if($order_id<1 && isset($_GET['key'])) $order_id=wc_get_order_id_by_order_key($_GET['key']);

  wse_update_methods();
  $methods=get_option('wse_methods');
  $methods_desc=get_option('wse_desc');
  $append_desc=get_option('wse_append_desc');
  $wse_mn_days=get_option('wse_mn_days');
  $wse_mx_days=get_option('wse_mx_days');
  $wse_bz_days=get_option('wse_bz_days');

  $prd_dsp=get_option('wse_prd_dsp');
  $prd_desc=get_option('wse_prd_desc');
  $prds_dsp=get_option('wse_prds_dsp');
  $prdx_dsp=get_option('wse_prdx_dsp');

  $wse_mon=get_option('wse_mon');
  $wse_tue=get_option('wse_tue');
  $wse_wed=get_option('wse_wed');
  $wse_thu=get_option('wse_thu');
  $wse_fri=get_option('wse_fri');
  $wse_sat=get_option('wse_sat');
  $wse_sun=get_option('wse_sun');
  $wse_hol=get_option('wse_hol');

  $del_fri=get_option('wse_del_fri');
  $del_sat=get_option('wse_del_sat');
  $del_sun=get_option('wse_del_sun');
  $del_hol=get_option('wse_del_hol');

  if(empty($wse_mon)) { // if ver<=1.3.6
    $del_sat=$wse_sat;
    $del_sun=$wse_sun;
    $del_hol=$wse_hol;
  }

  $def_bk_desc=get_option('wse_def_bk_desc');
  global $bko_prd,$product;
  $dt_format=get_option('wse_dt_format'); if(empty($dt_format)) $dt_format="D, M j";
  $hols=get_option('wse_holidays');
  $wse_ctf=get_option('wse_ctf');
  $now=current_datetime()->format('U');
  $wse_date=get_option('wse_date');
  $m_ct=1;
  $i=$bko_prd=$method_chk=$pid=0;
  $avail_methods_ct=9;
  $style=$blk_style=$blk_sel_style=$text=$method=$method_sel=$sel_method='';
  $im=$id=$ifd=array();

  if($order_id>0) {
    $method_sel=get_post_meta($order_id,'_wc_method_id',true);
    if(empty($method_sel)) {
      $order=wc_get_order($order_id);
      if($order && method_exists($order,'get_shipping_methods')) $method=@array_shift($order->get_shipping_methods());
      if(empty($method)) return;
      $method_id=$method['method_id'];
      $instance_id=$method['instance_id'];
      $method_sel="$method_id:$instance_id";
      add_post_meta($order_id,'_wc_method_id',$method_sel,true);
    }
  }
  if(is_array($methods)) $m_ct=count($methods);
  if($op>1) if(method_exists($product,'get_id')) $pid=$product->get_id();
  $prd_days_min=wse_prd_days('min',$pid);
  $prd_days_max=wse_prd_days('max',$pid);
  if(!empty($method_sel) && !in_array($method_sel,$methods)) $method_sel='ALL';
  if(!empty($methods) && $m_ct>0) while($i<$m_ct) {
    $mn_date=$mx_date=$est_date=$format_est=$method_title=$default_est='';
    $desc=$methods_desc[$i];
    if($pid>0) {
      if(isset($prds_dsp[$i]) && !empty(preg_replace("/[^0-9]/",'',$prds_dsp[$i]))) {
        if($prdx_dsp[$i]>0) {if(strpos($prds_dsp[$i],",$pid,")===false) {$i++;continue;}}
        elseif($prdx_dsp[$i]<0) {if(strpos($prds_dsp[$i],",$pid,")!==false) {$i++;continue;}}
      }
      if(!empty($prd_desc[$i])) $desc=$prd_desc[$i];
    }
    $method=$methods[$i];
    $method_title=get_option("wse:$method");
    $mn_days=$wse_mn_days[$i];
    $mx_days=$wse_mx_days[$i];
    $dsp_date=$wse_date[$i];

    if(function_exists('wse_add_days')) {
      if(!empty($mn_days)) $mn_days=$mn_days+wse_add_days($method_title,$mn_days,'min',$pid);
      if(!empty($mx_days)) $mx_days=$mx_days+wse_add_days($method_title,$mx_days,'max',$pid);
    }
    elseif(function_exists('wse_adjust_days')) { // Check for user defined wse_adjust_days
      if(!empty($mn_days)) $mn_days=$mn_days+wse_adjust_days($method_title,$mn_days,'min');
      if(!empty($mx_days)) $mx_days=$mx_days+wse_adjust_days($method_title,$mx_days,'max');
    }

    if(!empty($mn_days)) $mn_days=$mn_days+$prd_days_min;
    if(!empty($mx_days)) $mx_days=$mx_days+$prd_days_max;

    // Estimate by Specific Date
    if(empty($bko_prd) && !empty($dsp_date) && (!empty($mn_days) || !empty($mx_days))) {
      $locale=wse_getlocale();
      if(empty($wse_mon)) {$exc_ship_days=array(0,0,0,0,0,$wse_sat[$i],$wse_sun[$i]); $del_fri[$i]='';}
      else $exc_ship_days=array($wse_mon[$i],$wse_tue[$i],$wse_wed[$i],$wse_thu[$i],$wse_fri[$i],$wse_sat[$i],$wse_sun[$i]);
      $exc_hol=$wse_hol[$i];

      $exc_del_days=array(0,0,0,0,$del_fri[$i],$del_sat[$i],$del_sun[$i]);
      $exc_del_hol=$del_hol[$i];
      
      if(!empty($wse_ctf)) $ctf=$wse_ctf[$i]; else $ctf=0;

      if(!empty($mn_days)) {
        $mn_days=wse_ship_span($mn_days,$exc_ship_days,$exc_del_days,$exc_hol,$exc_del_hol,$hols,$ctf,$now);
        $mn_date=strtotime("+$mn_days days",$now);
        $est_date=date('Y-m-d',$mn_date);
        //setlocale(LC_TIME,$locale); $mn_format_date=strftime('%a, %b %e',$mn_date);
        $mn_format_date=date_i18n($dt_format, $mn_date);
      }
      if(!empty($mx_days)) {
        $mx_days=wse_ship_span($mx_days,$exc_ship_days,$exc_del_days,$exc_hol,$exc_del_hol,$hols,$ctf,$now);
        $mx_date=strtotime("+$mx_days days",$now);
        $est_date=date('Y-m-d',$mx_date);
        //setlocale(LC_TIME,$locale); $mx_format_date=strftime('%a, %b %e',$mx_date);
        $mx_format_date=date_i18n($dt_format, $mx_date);
      }

      if(empty($order_id) && !empty($method_sel)) { // Individual call for specific method before checkout
        if(empty($method_title)) $method_title=get_option("wse:$method");
        if($method_sel==$method_title) return $est_date;
      }

      if(!empty($mn_date) && !empty($mx_date)) {if(!empty($desc)) $format_est="$desc "; $format_est.="$mn_format_date - $mx_format_date";}
      elseif(!empty($mn_date)) if(!empty($desc)) $format_est="$desc $mn_format_date"; else $format_est="On or after $mn_format_date";
      elseif(!empty($mx_date)) if(!empty($desc)) $format_est="$desc $mx_format_date"; else $format_est="By $mx_format_date";

      if($order_id>0 && !empty($est_date) && !empty($method_sel) && $method_sel==$method) { // Individual call after checkout
        wse_save_est($order_id,$est_date,$format_est);
        return $est_date;
      }
    }

    // Estimate by number of Days
    elseif($bko_prd>0 || !empty($mn_days) || !empty($mx_days)) {
      if($bko_prd>0) $format_est=$def_bk_desc;
      else {
        if(!empty($wse_bz_days[$i]) && empty($append_desc[$i])) echo $append_desc[$i]='Business Days'; elseif(!isset($append_desc[$i])) $append_desc[$i]='Days';
        if(!empty($mn_days) && !empty($mx_days)) $format_est="$desc $mn_days - $mx_days $append_desc[$i]";
        elseif(!empty($mn_days)) $format_est="$desc $mn_days+ $append_desc[$i]";
        elseif(!empty($mx_days)) $format_est="$desc $mx_days $append_desc[$i]";
      }

      if($order_id>0 && !empty($method_sel) && $method_sel==$method) { // Individual call after checkout
        wse_save_est($order_id,$default_est,$format_est);
        if(!empty($format_est)) return $format_est;
        if(!empty($default_est)) return $default_est;
      }
    }

    // Est Description
    if(!empty($desc) && !empty($format_est)) $format_est_css=str_replace("$desc ","$desc\a ",$format_est);
    else {
      if(!empty($format_est)) $format_est_css=$format_est;
      elseif(!empty($desc)) $format_est_css=$desc;
    }

    // Build CSS
    if($method=='ALL') { // Store in default vars
      if(!empty($est_date)) $default_est=$est_date;
      if(!empty($format_est)) $format_default_est=$format_est; elseif(!empty($desc)) $format_default_est=$desc;
      $blk_style=",.wc-block-components-totals-shipping__via:after";
      $style.=".woocommerce-shipping-methods label:after,.wc-block-components-radio-control__option .wc-block-components-radio-control__label:after$blk_style{content:'$format_est_css'}";

    } else {
      if(isset(WC()->session) && is_object(WC()->session) && $method_chk<1) {
        $sel_methods=WC()->session->get('chosen_shipping_methods');
        if(isset($sel_methods[0])) $sel_method=$sel_methods[0];
        $method_chk=1;
      }
      $sel_method=str_replace(':','',$sel_method);
      $method_css=str_replace(':','',$method); // Cart blocks
      if($method_css==$sel_method) $blk_sel_style=",.wc-block-components-totals-item__description.wc-block-components-totals-shipping__via:after"; else $blk_sel_style='';
      $style.="#shipping_method_0_$method_css+label:after,.wc-block-components-radio-control__option[for=\"radio-control-0-$method\"] .wc-block-components-radio-control__label:after$blk_sel_style{content:'$format_est_css'}";
    }
    if($op>1 && isset($prd_dsp[$i]) && $prd_dsp[$i]>0) {array_push($im,$method);array_push($id,$est_date);array_push($ifd,$format_est);}
    $i++;
  }
  if($op>1) if(function_exists('wse_itm_est')) return wse_itm_est($im,$id,$ifd);

  // Apply default vars
  if($order_id>0) {
    wse_save_est($order_id,$default_est,$format_default_est);
    if(!empty($default_est)) return $default_est;
    if(!empty($format_default_est)) return $format_default_est;
  }


  // Output CSS
  if(empty($order_id) && empty($method_sel) && !empty($style)) {
    $style.="
      .woocommerce-shipping-methods label:after,.wc-block-components-totals-shipping .wc-block-components-radio-control__option:after,.wc-block-components-totals-item__description .wc-block-components-totals-shipping__via:after{white-space:nowrap;display:block;border-bottom:1px solid #DDD;text-align:right;white-space:pre;line-height:1em;padding:.3em 0 1em 0;margin-bottom:1em}
      .wc-block-components-radio-control__label-group .wc-block-components-radio-control__label:after,.wc-block-components-totals-item__description .wc-block-components-totals-shipping__via:after{display:block}
      .est_changed:after{display:none!important}";
    $script="
      <script>
        document.addEventListener('DOMContentLoaded',function() {
        setTimeout(function() {
          var sel_est=document.getElementsByClassName('wc-block-components-totals-shipping__via')[0];
          if(sel_est) {
            var observer=new MutationObserver(function(){sel_est.classList.add('est_changed');});
            observer.observe(sel_est,{characterData:true,subtree:true});
          }
          }, 1000);
        });
      </script>";
    if($op>0) return "<style>$style</style>$script"; else echo "<style>$style</style>$script";
  }
}
if(wse_is_path('/cart') || wse_is_path('/checkout') || wse_is_path('order-received')) add_action('wp_footer','wse_ship_est');


function wse_save_est($order_id,$est_date,$est_days) {
  if(!empty($est_days)) add_post_meta($order_id,'delivery_est_days',$est_days,true);
  if(!empty($est_date)) add_post_meta($order_id,'delivery_est',$est_date,true);
  wse_order_received($order_id);
}

function dsp_ship_est() {return wse_ship_est(0,1);}
add_shortcode('display_ship_est','dsp_ship_est');

function dsp_ship_est_itm() {return wse_ship_est(0,2);}
add_action('woocommerce_before_add_to_cart_form','dsp_ship_est_itm');

function wse_order_received($order_id){
  if(!$order_id>0) return;
  $order=wc_get_order($order_id);
  if(!$order) return;
  
  $google_opt='';
  $del_date=get_post_meta($order_id,'delivery_est',true);
  $method=$order->get_shipping_method();
  $rvw_prompt=get_option('wse_reviews');
  $merchant_id=get_option('wse_merchant_id');

  if(!empty($rvw_prompt) && !empty($merchant_id) && !empty($del_date)) {
    $wse_rvw_loc=get_option('wse_reviews_opt');
    if(empty($wse_rvw_loc)) $wse_rvw_loc='CENTER_DIALOG';
    $skus=$ocontents=$result=$products='';
    $oemail=strtolower($order->get_billing_email());
    $octry=$order->get_shipping_country();
    $items=$order->get_items();
    foreach($items as $i) {
      if(strlen($ocontents)>0) $ocontents.=',';
      $sku=get_post_meta($i['variation_id'],'_sku',true);
      if(empty($sku)) $sku=get_post_meta($i['product_id'],'_sku',true);
      if(!empty($skus)) $skus.=',';
      if(!empty($sku)) $skus.="{'gtin':'$sku'}";
    }
    if(!empty($skus)) $products=",'products': [$skus]";

    $google_opt="
    <script>
      window.renderOptIn=function() { 
        window.gapi.load('surveyoptin', function() {
          window.gapi.surveyoptin.render(
            {
              'merchant_id': $merchant_id,
              'order_id': '$order_id',
              'email': '$oemail',
              'delivery_country': '$octry',
              'estimated_delivery_date': '$del_date',
              'opt_in_style': '$wse_rvw_loc'
              $products
            });
        });
      }
    </script>
    <script src='https://apis.google.com/js/platform.js?onload=renderOptIn' async defer></script>";
  }

  $del_day=get_post_meta($order_id,'delivery_est_days',true);
  if(empty($del_day)) return;
  $del_dsp="$del_day via $method";
  $del_dsp="
  <script>
    function wse_show_est() {
      var p=document.getElementsByClassName('woocommerce-thankyou-order-details')[0];
      var i=document.createElement('li');
      i.class='woocommerce-order-overview__date date';
      i.innerHTML=\"Delivery Estimate<strong>$del_dsp</strong>\";
      p.appendChild(i);
    }
    wse_show_est();
  </script>";
  echo $google_opt.$del_dsp;
}


function wse_delivery_est($order_id=0) {
  if($order_id<1 && !wse_is_path('wp-admin/post.php?post=') && !wse_is_path('wp-admin/admin.php?page=wc-orders&')) return;
  $post_type=$dsp_est='';
  $post_id=wse_admin_get_order();
  if($order_id<1 && $post_id<1) return;

  ob_start();
  wse_ship_est($order_id);
  ob_end_clean();
  
  if($post_id>0) $delivery_est=get_post_meta($post_id,'delivery_est_days',true); else $delivery_est=get_post_meta($order_id,'delivery_est_days',true);
  if(empty($delivery_est)) {
    if($post_id>0) $delivery_est=get_post_meta($post_id,'delivery_est',true); else $delivery_est=get_post_meta($order_id,'delivery_est',true);
    if(!empty($delivery_est)) {
      setlocale(LC_TIME,wse_getlocale());//wse_getlocale() 'fr_FR'
      $delivery_est=strtotime($delivery_est);
      $delivery_est=strftime('%a, %b %e, %Y',$delivery_est);
    }
  }
  if(empty($delivery_est)) return;
  if($order_id>0) return "<br>$delivery_est";
  if(!current_user_can('manage_options')) $dsp_est='pointer-events:none';
  ?>
  <form name='update_del_est' method='post'>
    <input id='est_days' name='est_days'>
    <?php wp_nonce_field('del_est_action','del_est_nonce'); ?>
  </form>
  <style>.del_est{background:#fff;padding:1em;border:1px solid #ddd;border-radius:3px}</style>
  <script type='text/javascript'>
    setTimeout(function(){load_delivery_est();},1000);
    
    function load_delivery_est() {
      if(!document.getElementById('actions')) return;
      var par;
      if(document.getElementById('tracking-items')) par=document.getElementById('tracking-items'); else par=document.getElementsByClassName('order_actions')[0];
      var del_est=document.createElement('div');
      del_est.className=del_est.id='del_est';
      par.parentElement.insertBefore(del_est,par.parentElement.lastChild);
      del_est.innerHTML+="<b>Delivery Estimate:</b><br><input id='est_dsp' value='<?php echo $delivery_est;?>' title='Click to edit' style='width:100%;border-radius:3px;border:1px solid #eee;margin:.5em 0 0 0;padding:.5em;font-size:13px;<?php echo $dsp_est;?>' onkeydown=\"if(event.key==='Enter'){event.preventDefault();submit_del_est(this)}\" onchange='submit_del_est(this)'>";
    }
    function submit_del_est(i){
      i.style.pointerEvents='none';
      i.style.opacity=.3;
      document.getElementById('est_days').value=i.value;
      update_del_est.submit();
    }
  </script><?php 
}
add_action('admin_footer','wse_delivery_est');

function wse_admin_get_order() {
  $post_type='';
  $post_id=0;
  if(isset($_GET['id'])) $post_id=intval($_GET['id']);
  if(isset($_GET['post'])) $post_id=intval($_GET['post']);
  if($post_id>0) $post_type=get_post_type($post_id);
  if($post_type=='shop_order') return $post_id; else return 0;
}

function wse_admin_edit_est(){
  if(isset($_POST['est_days']) && current_user_can('manage_options') && check_admin_referer('del_est_action','del_est_nonce')) {
    $order_id=wse_admin_get_order();
    if($order_id>0) {
      $est_days=sanitize_text_field($_POST['est_days']);
      update_post_meta($order_id,'delivery_est_days',$est_days);
    }
  }
}
add_action('admin_head','wse_admin_edit_est');

function wse_add_estimate_to_email($order,$sent_to_admin,$plain_text,$email) {
  if(stripos('new_order,customer_invoice,customer_processing_order',$email->id)!==false) {
    $wse_in_email=get_option('wse_in_email');
    if($wse_in_email<0) return;
    if(empty($order)) return;

    $order_id=$order->get_id();
    $del_est=wse_delivery_est($order_id);
    if(empty($del_est)) return;

    $wse_in_email_desc=get_option('wse_in_email_desc');
    if(empty($wse_in_email_desc)) $wse_in_email_desc="Delivery Estimate: $del_est";
    echo str_ireplace('{ship_est}',$del_est,"$wse_in_email_desc<br><br>");
  }
}
add_action('woocommerce_email_before_order_table','wse_add_estimate_to_email',20,4);


function wse_add_email_ship_var($string,$email) {
  if(empty($email) || !is_object($email->object)) return $string;
  $order_id=$email->object->get_id();
  $del_est=wse_delivery_est($order_id);
  return str_ireplace('{ship_est}',$del_est,$string);
}
add_filter('woocommerce_email_format_string','wse_add_email_ship_var',10,4);


function wse_rvw_badge() {
  $wse_rvw_badge=get_option('wse_rvw_badge');
  $merchant_id=get_option('wse_merchant_id');
  if(empty($wse_rvw_badge) || empty($merchant_id)) return;

  $wse_rvw_badge_loc=get_option('wse_rvw_badge_loc');
  echo "<style>@media screen and (max-width:767px) {#___ratingbadge_0{margin-bottom:70em!important}}</style>
  <script src='https://apis.google.com/js/platform.js?onload=renderBadge' async defer></script>
  <script>
    window.renderBadge=function() {
      var ratingBadgeContainer=document.createElement('div');
        document.body.appendChild(ratingBadgeContainer);
        window.gapi.load('ratingbadge', function() {
          window.gapi.ratingbadge.render(ratingBadgeContainer,{'merchant_id':$merchant_id,'position':'$wse_rvw_badge_loc'});           
       });
    }
  </script>";
}
add_action('wp_footer','wse_rvw_badge');