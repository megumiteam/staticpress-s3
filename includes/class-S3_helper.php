<?php
require_once(dirname(__FILE__).'/aws.phar');

use Aws\Common\Aws;
use Aws\Common\Enum\Region;
use Aws\S3\Enum\CannedAcl;
use Aws\S3\Exception\S3Exception;
use Guzzle\Http\EntityBody;

class S3_helper {
	private $s3;
	private $options = array(
		'Bucket' => '',
		'StorageClass' => 'STANDARD',
		'ACL' => CannedAcl::PUBLIC_READ,
		);

	function __construct($access_key = null, $secret_key = null, $region = null) {
		if ($access_key && $secret_key) {
			$this->init_s3($access_key, $secret_key, $region);
		}
	}

	// get S3 object
	public function init_s3($access_key, $secret_key, $region = null){
		if ( !isset($region) )
			$region = Region::AP_NORTHEAST_1;
		$s3 = Aws::factory(array(
			'key' => $access_key,
			'secret' => $secret_key,
			'region' => $this->get_region($region),
			))->get('s3');
		$this->s3 = $s3;
		return $s3;
	}

	public function get_regions() {
		$regions = array();
		foreach(Region::values() as $key => $val){
			$region = str_replace('-','_',strtoupper($val));
			if ( !in_array($region, $regions))
				$regions[] = $region;
		}
		return $regions;
	}

	public function get_region($region) {
		$region = str_replace('-','_',strtoupper($region));
		$regions = Region::values();
		return
			isset($regions[$region])
			? $regions[$region]
			: false;
	}

	public function set_option($option_array){
		if (!is_array($option_array))
			return false;
		$this->options = array_merge($this->options, $option_array);
	}

	// S3 Upload
	public function upload($filename, $upload_path = null, $bucket = null) {
		if (!file_exists($filename) || !$this->s3)
			return false;

		try {
			if (!$upload_path)
				$upload_path = $filename;
			$args = array_merge($this->options, array(
				'Key'         => $upload_path,
				'Body'        => $this->file_body($filename),
				'ContentType' => $this->mime_type($filename),
				));
			if (isset($bucket))
				$args['Bucket'] = $bucket;
			if (!isset($args['Bucket']))
				return false;
			$response = $this->s3->putObject($args);
			return $response;
		} catch (S3Exception $e) {
			return false;
		}
	}

	// S3 Download
	public function download($key, $download_path = null, $bucket = null) {
		if (!$this->s3)
			return false;

		try {
			if (!$download_path)
				$download_path = dirname(__FILE__).'/'.basename($key);
			$args = array_merge($this->options, array(
				'Key'         => $key,
				));
			if (isset($bucket))
				$args['Bucket'] = $bucket;
			if (!isset($args['Bucket']))
				return false;
			$response = $this->s3->getObject($args);
			$response['Body']->rewind();
			file_put_contents($download_path, $response['Body']->read($response['ContentLength']));
			return $response;
		} catch (S3Exception $e) {
			return false;
		}
	}

	// S3 Delete
	public function delete($upload_path, $bucket = null) {
		if (!$this->s3)
			return false;

		try {
			$args = array_merge($this->options, array(
				'Key'         => $upload_path,
				));
			if (isset($bucket))
				$args['Bucket'] = $bucket;
			if (!isset($args['Bucket']))
				return false;
			$response = $this->s3->deleteObject($args);
			return $response;
		} catch (S3Exception $e) {
			return false;
		}
	}

	// list buckets
	public function list_buckets() {
		if (!isset($this->s3))
			return false;
		try {
			$list_buckets = $this->s3->listBuckets();
			return isset($list_buckets["Buckets"]) ? $list_buckets["Buckets"] : false;
		} catch (S3Exception $e) {
			return false;
		}
	}

	// return current bucket
	public function current_bucket(){
		return isset($this->options['Bucket']) ? $this->options['Bucket'] : false;
	}

	// set current bucket
	public function set_current_bucket($bucket){
		if ($this->bucket_exists($bucket)) {
			$this->options['Bucket'] = $bucket;
			return $bucket;
		} else {
			return false;
		}
	}

	// does Bucket exists
	public function bucket_exists($bucket = null, $accept403 = true) {
		if (!isset($this->s3))
			return false;
		if (!isset($bucket))
			$bucket = isset($this->options['Bucket']) ? $this->options['Bucket'] : false;
		return $bucket ? $this->s3->doesBucketExist($bucket, $accept403) : false;
	}

	// does Object exists
	public function object_exists($key) {
		if (!isset($this->s3))
			return false;
		if (!isset($this->options['Bucket']))
			return false;
		return $this->s3->doesObjectExist($this->options['Bucket'], $key);
	}

	// get file_body
	private function file_body($filename) {
		$filebody =
			file_exists($filename)
			? EntityBody::factory(fopen($filename, 'r'))
			: null;
		return $filebody;
 	}

	// get file_type
	private function mime_type($filename){
		static $info;
		if (!isset($info))
			$info = new FInfo(FILEINFO_MIME_TYPE);

		$mime_type =
			file_exists($filename)
			? $info->file($filename)
			: false;

		if ( $mime_type == 'text/plain') {
			if (preg_match('/\.css$/i', $filename))
				$mime_type = 'text/css';
			else if (preg_match('/\.js$/i', $filename))
				$mime_type = 'application/x-javascript';
			else if (preg_match('/\.html?$/i', $filename))
				$mime_type = 'text/html';
			else if (preg_match('/\.xml$/i', $filename))
				$mime_type = 'application/xml';
		}

		return $mime_type;
 	}
}
