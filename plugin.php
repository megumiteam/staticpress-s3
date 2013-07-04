<?php
/*
Plugin Name: StaticPress S3
Author: wokamoto 
Plugin URI: 
Description: StaticPress -> S3.
Version: 0.1.0
Author URI: http://www.digitalcube.jp/
Text Domain: static-press-S3
Domain Path: /languages

License:
 Released under the GPL license
  http://www.gnu.org/copyleft/gpl.html

  Copyright 2013 (email : wokamoto1973@gmail.com)

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
add_action('plugins_loaded', function(){
	global $staticpress;
	if (!isset($staticpress))
		return;

	if (!class_exists('S3_helper'))
		require(dirname(__FILE__).'/includes/class-S3_helper.php');
	if (!class_exists('staticpress_s3_admin'))
		require(dirname(__FILE__).'/includes/class-staticpress_s3_admin.php');
	if (!class_exists('staticpress_s3'))
		require(dirname(__FILE__).'/includes/class-staticpress_s3.php');

	//load_plugin_textdomain(staticpress_s3_admin::TEXT_DOMAIN, false, dirname(plugin_basename(__FILE__)) . '/languages/');

	new staticpress_s3(staticpress_s3_admin::get_option());
	if (is_admin())
		new staticpress_s3_admin();
});