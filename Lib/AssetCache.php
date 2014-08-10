<?php
App::uses('AssetCompiler', 'AssetCompress.Lib');
App::uses('AssetScanner', 'AssetCompress.Lib');

/**
 * Writes compiled assets to the filesystem
 * with optional timestamps.
 *
 */
class AssetCache {

	protected $_Config = null;

	public function __construct(AssetConfig $config) {
		$this->_Config = $config;
	}

/**
 * Writes content into a file
 *
 * @param string $filename The filename to write.
 * @param string $contents The contents to write.
 * @throws RuntimeException
 */
	public function write($filename, $content) {
		$ext = $this->_Config->getExt($filename);
		$path = $this->_Config->cachePath($ext);

		if (!is_writable($path)) {
			throw new RuntimeException('Cannot write cache file. Unable to write to ' . $path);
		}
		$filename = $this->buildFileName($filename);
		return file_put_contents($path . $filename, $content) !== false;
	}

/**
 * Check to see if a cached build file is 'fresh'.
 * Fresh cached files have timestamps newer than all of the component
 * files.
 *
 * @param string $target The target file being built.
 * @return boolean
 */
	public function isFresh($target) {
		$ext = $this->_Config->getExt($target);
		$files = $this->_Config->files($target);

		$theme = $this->_Config->theme();

		$target = $this->buildFileName($target);

		$buildFile = $this->_Config->cachePath($ext) . $target;

		if (!file_exists($buildFile)) {
			return false;
		}
		$configTime = $this->_Config->modifiedTime();
		$buildTime = filemtime($buildFile);

		if ($configTime >= $buildTime) {
			return false;
		}

		$Scanner = new AssetScanner($this->_Config->paths($ext, $target), $theme);

		foreach ($files as $file) {
			$path = $Scanner->find($file);
			if ($Scanner->isRemote($path)) {
				$time = $this->getRemoteFileLastModified($path);
			} else {
				$time = filemtime($path);
			}
			if ($time === false || $time >= $buildTime) {
				return false;
			}
		}
		return true;
	}

/**
 * Gets the modification time of a remote $url.
 * Based on: http://www.php.net/manual/en/function.filemtime.php#81194
 * @param type $url
 * @return The last modified time of the $url file, in Unix timestamp, or false it can't be read.
 */
	public function getRemoteFileLastModified($url) {
		// default
		$unixtime = 0;

		// @codingStandardsIgnoreStart
		$fp = @fopen($url, 'rb');
		// @codingStandardsIgnoreEnd
		if (!$fp) {
			return false;
		}

		$metadata = stream_get_meta_data($fp);
		foreach ($metadata['wrapper_data'] as $response) {
			// case: redirection
			if (substr(strtolower($response), 0, 10) === 'location: ') {
				$newUri = substr($response, 10);
				fclose($fp);
				return $this->getRemoteFileLastModified($newUri);
			}
			// case: last-modified
			// @codingStandardsIgnoreStart
			elseif (substr(strtolower($response), 0, 15) === 'last-modified: ') {
			// @codingStandardsIgnoreEnd
				$unixtime = strtotime(substr($response, 15));
				break;
			}
		}

		fclose($fp);
		return $unixtime;
	}

/**
 * Set the hash for a build file.
 *
 * @param string $build The name of the build to set a timestamp for.
 * @param integer $time The timestamp.
 * @return void
 */
	public function setHash($build, $hash) {
		$ext = $this->_Config->getExt($build);
		if (!$this->_Config->get($ext . '.fileHash')) {
			return false;
		}
		$data = $this->_readHash();
		$build = $this->buildFileName($build, false);
		$data[$build] = $hash;
		if ($this->_Config->general('cacheConfig')) {
			Cache::write(AssetConfig::CACHE_BUILD_TIME_KEY, $data, AssetConfig::CACHE_CONFIG);
		}
		$data = serialize($data);
		file_put_contents(TMP . AssetConfig::BUILD_TIME_FILE, $data);
		chmod(TMP . AssetConfig::BUILD_TIME_FILE, 0644);
	}

/**
 * Get the last build hash for a given build.
 *
 * Will either read the cached version, or the on disk version. If
 * no hash is found for a file, a new time will be generated and saved.
 *
 * If fileHash are disabled, false will be returned.
 *
 * @param string $build The build to get a timestamp for.
 * @return mixed The last build time, or false.
 */
	public function getHash($build) {
		$ext = $this->_Config->getExt($build);
		if (!$this->_Config->get($ext . '.fileHash')) {
			return false;
		}
		$data = $this->_readHash();
		$name = $this->buildFileName($build, false);
		if (!empty($data[$name])) {
			return $data[$name];
		}

        $assetCompiler = new AssetCompiler($this->_Config);
        $content = $assetCompiler->generate($build);
        $hash = self::getHashFromContents($content);
		$this->setHash($build, $hash);
		return $hash;
	}

/**
 * Read timestamps from either the fast cache, or the serialized file.
 *
 * @return array An array of timestamps for build files.
 */
	protected function _readHash() {
		$data = array();
		$cachedConfig = $this->_Config->general('cacheConfig');
		if ($cachedConfig) {
			$data = Cache::read(AssetConfig::CACHE_BUILD_TIME_KEY, AssetConfig::CACHE_CONFIG);
		}
		if (empty($data) && file_exists(TMP . AssetConfig::BUILD_TIME_FILE)) {
			$data = file_get_contents(TMP . AssetConfig::BUILD_TIME_FILE);
			if ($data) {
				$data = unserialize($data);
			}
		}
		return $data;
	}

/**
 * Get the final filename for a build. Resolves
 * theme prefixes and hashes.
 *
 * @param string $target The build target name.
 * @return string The build filename to cache on disk.
 */
	public function buildFileName($target, $contents=true) {
		$file = $target;
		if ($this->_Config->isThemed($target)) {
			$file = $this->_Config->theme() . '-' . $target;
		}

		if ($contents) {
            $hash = $this->getHash($file);
			$file = $this->_hashFile($file, $hash);
		}
		return $file;
	}

    public static function getHashFromContents($contents) {
        $md5 = md5($contents);

        //to byte array
        $byteArray = '';
        for ($i = 0; $i < strlen($md5); $i+=2) {
            $byteArray .= pack('H*', substr($md5, $i, 2));
        }

        //base64 encode, then make url friendly
        $base64 = base64_encode($byteArray);
        $a = array('+', '/');
        $b = array('-', '_');
        $result =  str_replace($a, $b, $base64);

        return substr($result, 0, 22)   ;
    }

/**
 * Modify a file name and append the file hash
 *
 * @param string $file The filename.
 * @param integer $hash The hash.
 * @return string The build filename to cache on disk.
 */
	protected function _hashFile($file, $hash) {
		if (!$hash) {
			return $file;
		}
		$pos = strrpos($file, '.');
		$name = substr($file, 0, $pos);
		$ext = substr($file, $pos);
		return $name . '.v' . $hash . $ext;
	}
}
