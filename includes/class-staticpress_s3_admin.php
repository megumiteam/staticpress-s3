<?php
class staticpress_s3_admin {
	const OPTION_KEY   = 'staticpress_s3';
	const OPTION_PAGE  = 'staticpress_s3';
	const TEXT_DOMAIN  = 'staticpress_s3';

	const NONCE_ACTION = 's3_update_options';
	const NONCE_NAME   = '_wpnonce_s3_update_options';

	private $options = array();
	private $plugin_basename;
	private $admin_hook, $admin_action;
	private $regions = array(
		'US_EAST_1',
		'US_WEST_1',
		'US_WEST_2',
		'EU_WEST_1',
		'AP_SOUTHEAST_1',
		'AP_SOUTHEAST_2',
		'AP_NORTHEAST_1',
		'SA_EAST_1',
		'US_GOV_WEST_1'
		);

	function __construct(){
		$this->options = $this->get_option();
		$this->plugin_basename = staticpress_s3::plugin_basename();
		add_action('StaticPress::options_save', array(&$this, 'options_save'));
		add_action('StaticPress::options_page', array(&$this, 'options_page'));
	}

	static public function option_keys(){
		return array(
			'access_key' => __('AWS Access Key',  self::TEXT_DOMAIN),
			'secret_key' => __('AWS Secret Key',  self::TEXT_DOMAIN),
			'region'     => __('AWS Region',  self::TEXT_DOMAIN),
			'bucket'     => __('S3 Bucket',  self::TEXT_DOMAIN),
			);
	}

	static public function get_option(){
		$options = get_option(self::OPTION_KEY);
		foreach (array_keys(self::option_keys()) as $key) {
			if (!isset($options[$key]) || is_wp_error($options[$key]))
				$options[$key] = '';
		}
		return $options;
	}

	//**************************************************************************************
	// Add Admin Menu
	//**************************************************************************************
	public function options_save(){
		$option_keys   = $this->option_keys();
		$this->options = $this->get_option();

		$iv = new InputValidator('POST');
		$iv->set_rules(self::NONCE_NAME, 'required');

		// Update options
		if (!is_wp_error($iv->input(self::NONCE_NAME)) && check_admin_referer(self::NONCE_ACTION, self::NONCE_NAME)) {
			// Get posted options
			$fields = array_keys($option_keys);
			foreach ($fields as $field) {
				switch ($field) {
				case 'access_key':
				case 'secret_key':
					$iv->set_rules($field, array('trim','esc_html','required'));
					break;
				default:
					$iv->set_rules($field, array('trim','esc_html'));
					break;
				}
			}
			$options = $iv->input($fields);
			$err_message = '';
			foreach ($option_keys as $key => $field) {
				if (is_wp_error($options[$key])) {
					$error_data = $options[$key];
					$err = '';
					foreach ($error_data->errors as $errors) {
						foreach ($errors as $error) {
							$err .= (!empty($err) ? '<br />' : '') . __('Error! : ', self::TEXT_DOMAIN);
							$err .= sprintf(
								__(str_replace($key, '%s', $error), self::TEXT_DOMAIN),
								$field
								);
						}
					}
					$err_message .= (!empty($err_message) ? '<br />' : '') . $err;
				}
				if (!isset($options[$key]) || is_wp_error($options[$key]))
					$options[$key] = '';
			}
			if (staticpress_s3::DEBUG_MODE && function_exists('dbgx_trace_var')) {
				dbgx_trace_var($options);
			}

			// Update options
			if ($this->options !== $options) {
				update_option(self::OPTION_KEY, $options);
				printf(
					'<div id="message" class="updated fade"><p><strong>%s</strong></p></div>'."\n",
					empty($err_message) ? __('Done!', self::TEXT_DOMAIN) : $err_message
					);
				$this->options = $options;
			}
			unset($options);
		}
	}

	public function options_page(){
		$option_keys   = $this->option_keys();
		$this->options = $this->get_option();
		$title = __('StaticPress S3 Option', self::TEXT_DOMAIN);

		// Get S3 Object
		$s3 = new S3_helper(
			isset($this->options['access_key']) ? $this->options['access_key'] : null,
			isset($this->options['secret_key']) ? $this->options['secret_key'] : null,
			isset($this->options['region']) ? $this->options['region'] : null
			);
		$regions = $this->regions;
		$buckets = false;
		if ($s3) {
			$regions = $s3->get_regions();
			$buckets = $s3->list_buckets();
		}
		if (!$buckets) {
			unset($option_keys['bucket']);
			unset($option_keys['s3_url']);
		}

?>
		<div class="wrap">
		<h2><?php echo esc_html( $title ); ?></h2>
		<form method="post" action="<?php echo $this->admin_action;?>">
		<?php echo wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME, true, false) . "\n"; ?>
		<table class="wp-list-table fixed"><tbody>
		<?php foreach ($option_keys as $field => $label) { $this->input_field($field, $label, array('regions' => $regions, 'buckets' => $buckets)); } ?>
		</tbody></table>
		<?php submit_button(); ?>
		</form>
		</div>
<?php
	}

	private function input_field($field, $label, $args = array()){
		extract($args);

		$label = sprintf('<th><label for="%1$s">%2$s</label></th>'."\n", $field, $label);

		$input_field = sprintf('<td><input type="text" name="%1$s" value="%2$s" id="%1$s" size=100 /></td>'."\n", $field, esc_attr($this->options[$field]));
		switch ($field) {
		case 'region':
			if ($regions && count($regions) > 0) {
				$input_field  = sprintf('<td><select name="%1$s">', $field);
				$input_field .= '<option value=""></option>';
				foreach ($regions as $region) {
					$input_field .= sprintf(
						'<option value="%1$s"%2$s>%3$s</option>',
						esc_attr($region),
						$region == $this->options[$field] ? ' selected' : '',
						__($region, self::TEXT_DOMAIN));
				}
				$input_field .= '</select></td>';
			}
			break;
		case 'bucket':
			if ($buckets && count($buckets) > 0) {
				$input_field  = sprintf('<td><select name="%1$s">', $field);
				$input_field .= '<option value=""></option>';
				foreach ($buckets as $bucket) {
					$input_field .= sprintf(
						'<option value="%1$s"%2$s>%1$s</option>',
						esc_attr($bucket['Name']),
						$bucket['Name'] == $this->options[$field] ? ' selected' : '');
				}
				$input_field .= '</select></td>';
			}
			break;
		}

		echo "<tr>\n{$label}{$input_field}</tr>\n";
	}
}