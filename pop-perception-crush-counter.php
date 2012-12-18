<?php
/*
Plugin Name: Pop Perception Crush Counter
Description: Track registered users' crushes on celebrity pages.
Version: 1.0 
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
    
    function __construct(){
        $this->plugin_dir = trailingslashit(plugin_dir_path(__FILE__));
	$this->plugin_url = trailingslashit(plugins_url('',__FILE__));
        /*Hook to register shortcodes */
	add_shortcode('crush_buttons', array(&$this, 'processShortCodes'));
        add_action('init', array(&$this, 'init'));
    }
    
    function init(){
        /** add in the stylesheet used by the plugin */
	wp_enqueue_style('crush-button-styles', $this->plugin_url.'/css/plugin.css', true);
    }
    
    function processShortCodes(){
        /** create buttons */
        $buttons .= "<button type='button' class='crush-button'>Crush</button>";
        $buttons = "<button type='button' class='crush-button guy'>Guy Crush</button>";
        $buttons .= "<button type='button' class='crush-button girl'>Girl Crush</button>";
        return $buttons;
    }
    
    
}
?>