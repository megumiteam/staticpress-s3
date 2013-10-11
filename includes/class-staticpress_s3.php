<?php
class staticpress_s3 {
	static $debug_mode  = false;
    static $instance;

	private $s3;                // S3 Object
	private $options = array(); // this plugin options

	function __construct($options){
        self::$instance = $this;

		$this->options = $options;
		add_action('StaticPress::file_put', array($this, 'file_put'), 10, 2);
	}

	static public function plugin_basename() {
		return plugin_basename(dirname(dirname(__FILE__)).'/plugin.php');
	}

	public function file_put($file_dest, $url){
		$s3_bucket = isset($this->options['bucket']) ? $this->options['bucket'] : false;
		$s3_key    = preg_replace('#^(https?://[^/]+/|/)#i', '', urldecode($url));
		$result    = $this->s3_upload($file_dest, $s3_bucket, $s3_key);
	}

	// Initializing S3 object
	private function s3($S3_bucket = null){
		if (isset($this->s3)) {
			if (isset($S3_bucket) && $this->s3->current_bucket() !== $S3_bucket)
				$this->s3->set_current_bucket($S3_bucket);
			return $this->s3;
		}
		if ($this->options) {
			$s3 = new S3_helper(
				isset($this->options['access_key']) ? $this->options['access_key'] : null,
				isset($this->options['secret_key']) ? $this->options['secret_key'] : null,
				isset($this->options['region'])     ? $this->options['region']     : null
				);
			if ($s3 && isset($S3_bucket))
				$s3->set_current_bucket($S3_bucket);
			$this->s3 = $s3;
			return $s3;
		}
		return false;
	}

	// Upload file to S3
	private function s3_upload($filename, $S3_bucket, $S3_key){
		if (!file_exists($filename))
			return false;

		$upload_result = false;
		if ($s3 = $this->s3($S3_bucket)) {
			$upload_result = $s3->upload($filename, $S3_key);
			if (self::$debug_mode && function_exists('dbgx_trace_var')) {
				dbgx_trace_var($upload_result);
			}
		}
		return $upload_result;
	}

	// Download file to S3
	private function s3_download($filename, $S3_bucket, $S3_key){
		$download_result = false;
		if ($s3 = $this->s3($S3_bucket)) {
			if (!$s3->object_exists($S3_key))
				return false;
			$download_result = $s3->download($S3_key, $filename);
			if (self::$debug_mode && function_exists('dbgx_trace_var')) {
				dbgx_trace_var($download_result);
			}
		}
		return $download_result;
	}

	// Delete S3 object
	private function s3_delete($S3_bucket, $S3_key){
		$delete_result = false;
		if ($s3 = $this->s3($S3_bucket)) {
			$delete_result =
				$s3->object_exists($S3_key)
				? $s3->delete($S3_key)
				: true;
			if (self::$debug_mode && function_exists('dbgx_trace_var')) {
				dbgx_trace_var($delete_result);
			}
		}
		return $delete_result;
	}
}
