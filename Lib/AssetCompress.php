<?php
/**
 * Class AssetCompress
 *
 * Manually compress assets using gzip.
 * Especially useful for CDNs which do not support automatic gzip (such as s3)
 */
class AssetCompress {

	/**
	 * compression_level
	 * Must be a value between 1-9
	 * 9 being most compressed and most CPU intensive.
	 */
	protected $_settings = array(
		'compression_level' => 9
	);

	public function output($contents) {
		return gzcompress($contents, $this->_settings['compression_level']);
	}
}