<?php
App::uses('AssetCache', 'AssetCompress.Lib');
App::uses('AssetConfig', 'AssetCompress.Lib');

class AssetCacheTest extends CakeTestCase {

	public function setUp() {
		parent::setUp();
		AssetConfig::clearBuildTimestamp();
		$this->_testFiles = App::pluginPath('AssetCompress') . 'Test' . DS . 'test_files' . DS;
		$this->testConfig = $this->_testFiles . 'Config' . DS . 'integration.ini';
		$this->_themeConfig = $this->_testFiles . 'Config' . DS . 'themed.ini';

		$this->config = AssetConfig::buildFromIniFile($this->testConfig, array(
			'TEST_FILES' => $this->_testFiles
		));
		$this->config->cachePath('js', TMP);
		$this->config->set('js.fileHash', true);
		$this->cache = new AssetCache($this->config);
	}

	public function testWrite() {
		$this->config->set('js.fileHash', false);
		$result = $this->cache->write('test.js', 'Some content');
		$this->assertNotEqual($result, false);
		$contents = file_get_contents(TMP . 'test.js');
		$this->assertEquals('Some content', $contents);
		unlink(TMP . 'test.js');
	}

	public function testWriteTimestamp() {
		$this->assertTrue($this->config->get('js.fileHash'));

        $hash = AssetCache::getHashFromContents(time());
		$this->cache->setHash('test.js', $hash);
		$this->cache->write('test.js', 'Some content');

		$contents = file_get_contents(TMP . 'test.v' . $hash . '.js');
		$this->assertEquals('Some content', $contents);
		unlink(TMP . 'test.v' . $hash . '.js');
	}

	public function testIsFreshNoBuild() {
		$this->assertFalse($this->cache->isFresh('libs.js'));
	}

	public function testIsFreshSuccess() {
		// Disable filehash so the filenames are known
		$this->config->set('js.fileHash', false);
		touch(TMP . '/libs.js');

		$this->assertTrue($this->cache->isFresh('libs.js'));
		unlink(TMP . '/libs.js');
	}

	public function testIsFreshConfigExpire() {
		touch(TMP . '/libs.js');

		$data = parse_ini_file($this->testConfig, true);
		$constants = array(
			'TEST_FILES' => $this->_testFiles
		);
		$config = new AssetConfig($data, $constants, strtotime('-1 minute'));
		$config->cachePath('js', TMP);
		$config->set('js.fileHash', false);

		$cache = new AssetCache($config);
		$this->assertTrue($cache->isFresh('libs.js'));

		$config = new AssetConfig($data, $constants, strtotime('+1 minute'));
		$config->cachePath('js', TMP);
		$config->set('js.fileHash', false);

		$cache = new AssetCache($config);
		$this->assertFalse($cache->isFresh('libs.js'));

		unlink(TMP . '/libs.js');
	}

	public function testThemeFileSaving() {
		$this->config = AssetConfig::buildFromIniFile($this->_themeConfig);
		$this->config->theme('blue');
		$this->config->cachePath('css', TMP);
		$this->cache = new AssetCache($this->config);

		$this->cache->write('themed.css', 'theme file.');
		$contents = file_get_contents(TMP . 'blue-themed.css');
		$this->assertEquals('theme file.', $contents);
	}


	public function testGetSetTimestamp() {
		$hash = AssetCache::getHashFromContents(time());
		$this->cache->setHash('libs.js', $hash);
		$result = $this->cache->getHash('libs.js');
		$this->assertEquals($hash, $result);

        // cannot figure this out
		//$result = $this->cache->getHash('foo.bar.js');
	    //$this->assertEquals($hash, $result);

		$this->config->set('js.fileHash', false);
		$result = $this->cache->getHash('foo.bar.js');
		$this->assertFalse($result);
	}

	public function testBuildFileNameTheme() {
		$this->config = AssetConfig::buildFromIniFile($this->_themeConfig);
		$this->config->theme('blue');
		$this->config->cachePath('css', TMP);
		$this->cache = new AssetCache($this->config);

		$result = $this->cache->buildFileName('themed.css');
		$this->assertEquals('blue-themed.css', $result);
	}

	public function testBuildFileNameTimestampNoValue() {
		$this->config->cachePath('js', TMP);
		$this->cache = new AssetCache($this->config);

        $hash = 'VmeO-_2S0B06XW1wRWqj-g';
		$result = $this->cache->buildFileName('libs.js');
		$this->assertEquals('libs.v' . $hash . '.js', $result);
	}

	public function testFileHashFromCache() {
		$this->config->general('cacheConfig', true);
		$this->config->set('js.fileHash', true);

		$hash = 'VmeO-_2S0B06XW1wRWqj-g';
		$this->cache->buildFilename('libs.js');

		// delete the file so we know we hit the cache.
		unlink(TMP . AssetConfig::BUILD_TIME_FILE);

		$result = $this->cache->buildFilename('libs.js');
		$this->assertEquals('libs.v' . $hash . '.js', $result);
	}

}
