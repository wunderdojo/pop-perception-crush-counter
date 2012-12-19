<?php
/*
Plugin Name: Pop Perception Crush Counter
Description: Track registered users' crushes on celebrity pages.
Version: 1.1 
Author: James Currie
License: GPL2
*/
/*  Copyright 2012  Jamie Currie  (email : jamie@zelcreative.com)

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

$crushCounter = new crushCounter();
class crushCounter{
    /* class properties */
    private     $version = '1.1';
    
    function __construct(){
        $this->plugin_dir = trailingslashit(plugin_dir_path(__FILE__));
	$this->plugin_url = trailingslashit(plugins_url('',__FILE__));
        /** register an activation hook to run when the plugin is activated */        
	register_activation_hook(__FILE__, array( &$this, 'activation' ) ); 
        /*Hook to register shortcodes */
	add_shortcode('crush_buttons', array(&$this, 'processShortCodes'));
        /** Add a new meta section to the celebrity custom post type for gender */
        add_action('add_meta_boxes', array($this, 'addMetaBoxes'), 10, 2);
        /** Hook to save data from custom post custom meta boxes */
        add_action('save_post', array(&$this,'saveMetaData'),10,2); 
        /** Hooks for the ajax crush voting */
        add_action('wp_ajax_ajax-crush', array(&$this, 'ajaxCrush'));
		add_action('wp_ajax_nopriv_ajax-crush', array(&$this, 'ajaxCrush'));
        add_action('init', array(&$this, 'init'));
    }
    
    function activation(){
         /**check to see if we need to do any updates */
        $cur_version = get_option('popcrush_version');
        if( version_compare($this->version, $cur_version, '>')){
                /** create custom tables */
		require_once(ABSPATH . '/wp-admin/includes/upgrade.php');
		$table_name = $wpdb->prefix."crushes";
		$sql = "CREATE TABLE ".$table_name." (
		id bigint(20) unsigned NOT NULL auto_increment,
                post_id bigint(20) unsigned default NULL,
                user_id bigint(20) unsigned default NULL,
                crush_type tinyint(1) unsigned default NULL,
		created timestamp NOT NULL default CURRENT_TIMESTAMP,							
		UNIQUE KEY  (id)			);";
		dbDelta($sql);
                update_option( "popcrush_version", $this->version);
            }
    }
    
    function init(){
        /** add in the stylesheet used by the plugin */
	wp_enqueue_style('crush-button-styles', $this->plugin_url.'/css/plugin.css?', true);
    }
    
    function processShortCodes(){
        /** There are three buttons -- crush, guy crush, girl crush
         * Crush shows for all celebs. Guy & Girl show based on gender
         * Gender is stored as post_meta with key = 'gender'
         */
        global $post; 
        $buttons = '';
        /* load the javascript for ajax voting. Done here so it only loads on the pages where it is used */
         wp_register_script('ajax-crush', $this->plugin_url.'/js/ajax-crush.js', array('jquery'), 1, true);
         wp_localize_script( 'ajax-crush', 'myAjax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
         wp_print_scripts(array('ajax-crush'));
        
         /** get the gender so we know which buttons to show */
        $gender = get_post_meta($post->ID, 'gender', TRUE);
        $gender=($gender=='male')?'guy':'girl';
        /* get the current crush count for this celebrity */
        $count = $this->getCrushCount($post->ID);
        /* check if the user is logged in and if they have already crushed this celebrity */
        if(is_user_logged_in()){
            $class='in';
            $user_id = get_current_user_id();
            $c = $this->checkCrush($post->ID, $user_id);
            }
        else{
           $class = 'out';
           $disabled = 'disabled';
           $buttons .="<div class='warning'>Log in or register below to cast your crush!</div>";
        }
        $buttons .= sprintf("<table id='crush'><tr><td><button %1s type='button' class='crush-button %2s' value='%3d' rel='1'>Crush</button><div class='crush-counter'><span id='crush-count'><b>%4d</b> %5s</span></div></td>",$disabled, $class,$post->ID, $count[1]['count'], $count[1]['term']);
        $buttons .= sprintf("<td><button %1s type='button' class='crush-button %2s %3s' value='%4d' rel='2'>%5s Crush</button><div class='crush-counter'><span id='gender-crush-count'><b>%6d</b> %7s</span></div></td></tr></table>",$disabled, $gender, $class, $post->ID, ucfirst($gender), $count[2]['count'], $count[2]['term'] );
        return $buttons;
    }
    
    
    function checkCrush($post_id, $user_id){
        global $wpdb;
        $query = "SELECT crush_type FROM {$wpdb->prefix}crushes WHERE post_id = '$post_id' AND user_id = '$user_id'";
        $type = $wpdb->get_var($query);
        return (is_null($type))? '0':$type;
        }
        
    function recordCrush($post_id, $user_id, $crush_type){
        global $wpdb;
        $wpdb->insert($wpdb->prefix."crushes", array(
                'post_id'=>$post_id,
                'user_id'=>$user_id,
                'crush_type'=>$crush_type
                ));
        }
        
    function removeCrush($post_id, $user_id){
        global $wpdb;
        $query = "DELETE FROM {$wpdb->prefix}crushes WHERE post_id = '$post_id' AND user_id='$user_id'";
        $wpdb->query($query);
        }
        
    function updateCrush($post_id, $user_id, $crush_type){
        global $wpdb;
        $wpdb->update($wpdb->prefix."crushes", array(
                'crush_type'=>$crush_type
                ),
                array(
                    'post_id'=>$post_id,
                    'user_id'=>$user_id
                    )
                );
        
        }
        
    function getCrushCount($post_id){
        global $wpdb;
        $query = "SELECT crush_type, COUNT(*) as count FROM {$wpdb->prefix}crushes WHERE post_id = $post_id GROUP BY crush_type";
        $results = $wpdb->get_results($query);
        $count = array('1'=>array('count'=>0, 'term'=>'crushes'), '2'=>array('count'=>'0', 'term'=>'crushes'));
        if($results){
            foreach($results as $result){ 
                $count[$result->crush_type] = array('count'=>$result->count, 'term'=>($result->count<>1)?'crushes':'crush');
                }
           } 
        return $count;
        }
        
     /** There are a couple of areas we we need to provide data to the PopPerception plugin */
    public function totalCrushes($post_id){
        $count = $this->getCrushCount($post_id);
        $total = $count[1]['count'] + $count[2]['count'];
        return $total;
    }

    /** This function handles the front end ajax crush button actions */
    function ajaxCrush(){
        extract($_POST);
        /** start by double checking that they are logged in */
        $status = is_user_logged_in();
        if($status==false){ 
            echo json_encode(array('status'=>$status));
        }
        elseif($status==true){
            $user_id = get_current_user_id();
        /** they're logged in, now see if they have already registered a crush
         *  for this particular celebrity. If they have, remove it. If they haven't, add it.
         */
           $c = $this->checkCrush($post_id, $user_id);
           /** haven't voted previously, register a new vote **/
           if($c==0){ $this->recordCrush($post_id, $user_id, $crush_type);}
           /** have registered a crush and now want to remove it */
           elseif(($c==1 && $crush_type == '1') || ($c==2 && $crush_type =='2')){ $this->removeCrush($post_id, $user_id);}
           /** have registered a crush and want to switch the type */
           elseif(($c==2 && $crush_type =='1') || ($c==1 && $crush_type=='2')){$this->updateCrush($post_id, $user_id, $crush_type);}
           /** now send back the updated crush counts as an array (count=>general crushes, gcount=>girl or guy crushes) **/
           $data = $this->getCrushCount($post_id);
           $data['status'] = $status;
           $data['c']=$c;
           echo json_encode($data);
        }
        die();
        
        
    }
    
    /** functions for handling adding / displaying / processing meta fields on custom post types */
    function loadMetaFields($post_type, $post){
        $meta_boxes = array(
        '0'=>array(
        'id' => 'Celebrity Info',
        'title' => 'Celebrity Info',
        'page' => 'celebrity',
        'context' => 'normal',
        'priority' => 'high',
        'fields' => array(
            array(
                'name' => 'Gender',
                'id' => 'gender',
                'type' => 'radio',
                'options' => array(
                               array('value'=>'male', 'name'=>'Male'),
                               array('value'=>'female', 'name'=>'Female')
                            ),
                'std' => ''
            )
            )//end of fields
            )//end of 0 index
        );//end of metaboxes array
        return $meta_boxes;
    }//end of loadMetaFields
        
     function addMetaBoxes($post_type, $post){
	/* get the regular metas */
        $args = $this->loadMetaFields($post_type, $post);
        foreach ($args as $meta_box){
        add_meta_box($meta_box['id'], $meta_box['title'], array($this,'showMetaBoxes'), $meta_box['page'], $meta_box['context'], $meta_box['priority'], $meta_box); 
        }//end for each
    }//end addMetaBoxes
    
    function showMetaBoxes($post, $meta_box){
    // Use nonce for verification
    echo '<input type="hidden" name="wunder_meta_box_nonce" value="', wp_create_nonce(basename(__FILE__)), '" />';
    
    echo '<table class="form-table">';
    foreach ($meta_box['args']['fields'] as $field) {
        // get current post meta data
        $meta = get_post_meta($post->ID, $field['id'], true);
        $$field['id']=$meta;
        $class =($field['class'])?$field['class']:'std';
        echo '<tr>';
        if($meta_box['args']['class']=='checkbox'){
        echo '<th style="width:80%"><label for="', $field['id'], '">', $field['name'], '</label></th>';
        }
        else{
        echo '<th style="width:20%"><label for="', $field['id'], '">', $field['name'], '</label></th>';
        }
        echo '<td>';
        switch ($field['type']) {
            case 'text':
            echo '<input type="text" name="', $field['id'], '" id="', $field['id'], '" value="', $meta ? $meta : $field['std'], '" size="30" style="width:97%" />', '
', $field['desc'];
            break;
            case 'time':
            echo '<input type="text" name="', $field['id'], '" id="', $field['id'], '" value="', $meta ? $meta : $field['std'], '" size="20" style="" />', '
', $field['desc'];
            echo '
             <script type="text/javascript">
            //<![CDATA[
            jQuery(document).ready( function($) {
            $("#'.$field["id"].'").timeEntry({ampmPrefix: " ", ampmNames: ["am", "pm"],spinnerImage: "timeEntry2.png", spinnerSize: [20, 20, 0]});
             });
            //]]>
            </script>';
            break;
            case 'date':
            echo '<input type="text" name="', $field['id'], '" id="', $field['id'], '" value="', $meta ? $meta : $field['std'], '" size="20" style="" />', '
', $field['desc'];
            echo '
             <script type="text/javascript">
            //<![CDATA[
            jQuery(document).ready( function($) {
            $("#'.$field["id"].'").datepicker({ dateFormat: "yy-mm-dd"});
             });
            //]]>
            </script>';
            break;
            case 'textarea':
                echo '<textarea name="', $field['id'], '" id="', $field['id'], '" cols="60" rows="4" style="width:97%">', $meta ? $meta : $field['std'], '</textarea>', '
', $field['desc'];
                break;
             case 'select':
                echo '<select class="'.$class.'" name="'. $field['id']. '" id="'. $field['id']. '">';
                foreach ($field['options'] as $key =>$value) {
                $selected = ($meta == $key)?'selected':'';
                echo '<option value="'.$key.'" '.$selected.'>'.$value.'</option>';
                }
                echo '</select>', '<br>
', $field['desc'];
                break;
           
            case 'radio':
                foreach ($field['options'] as $option) {
                $value = $meta ?$meta : $field['std'];
                    echo '<input type="radio" name="', $field['id'], '" value="', $option['value'], '"', $value == $option['value'] ? ' checked="checked"' : '', ' />', $option['name'];
                }
echo'<br>', $field['desc'];
                break;
            case 'checkbox':
                echo '<input type="checkbox" name="', $field['id'], '" id="', $field['id'], '"', $meta ? ' checked="checked"' : '', ' />
';
                break;
            case 'map':
            if($latitude =='' || $longitude ==''){
            echo "No map is currently associated with this venue.";
            }
            else{
                echo '
                <script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=false"></script>
                <div id="gmap" class="admin"></div>
                <script type="text/javascript">gmapV3('.$latitude.', '.$longitude.');addMarker('.$latitude.', '.$longitude.');</script>';
             }  
                break;    
        }
        echo     '<td>',
            '</tr>';
    }
    echo '</table>';
  }//end of showMetaBoxes function
 
    /** save data from custom meta fields */
    function saveMetaData($post_id, $post){
    // verify nonce
    if (!wp_verify_nonce($_POST['wunder_meta_box_nonce'], basename(__FILE__))) {
        return $post_id;
    }

    // check autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return $post_id;
    }

    // check permissions
    if ('page' == $_POST['post_type']) {
        if (!current_user_can('edit_page', $post_id)) {
            return $post_id;
        }
    } elseif (!current_user_can('edit_post', $post_id)) {
        return $post_id;
    }
    //get the post type and the meta_box fields
      $post_type =$_POST['post_type'];
	  $meta_boxes = $this->loadMetaFields($post_type, $post);
      foreach ($meta_boxes as $meta_box){
      foreach ($meta_box['fields'] as $field) {
        $old = get_post_meta($post_id, $field['id'], true);
        $new = $_POST[$field['id']];
        if ($new && $new != $old) {
        update_post_meta($post_id, $field['id'], $new);
        } elseif ('' == $new && $old) {
        delete_post_meta($post_id, $field['id'], $old);
        }     
        }//end of inner foreach loop
        }//end of outer foreach loop
    }//end of saveMetaBoxes
    
    
}//end of the class
?>