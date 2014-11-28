<?php
/**
 * Class AssetCompress
 *
 * Manually compress assets using gzip.
 * Especially useful for CDNs which do not support automatic gzip (such as s3)
 */
class AssetCompress {

	/*
	 * compressionLevel
	 * Must be a value between 1-9
	 * 9 being most compressed and most CPU intensive.
	 */
	public static function build($contents, $compressionLevel=9) {
		return gzcompress($contents, $compressionLevel);
	}
}