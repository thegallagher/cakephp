<?php
/**
 * LogTest file
 *
 * PHP 5
 *
 * CakePHP(tm) Tests <http://book.cakephp.org/view/1196/Testing>
 * Copyright 2005-2011, Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice
 *
 * @copyright     Copyright 2005-2011, Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://book.cakephp.org/view/1196/Testing CakePHP(tm) Tests
 * @package       Cake.Test.Case.Log
 * @since         CakePHP(tm) v 1.2.0.5432
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
namespace Cake\Test\TestSuite\Log;
use Cake\TestSuite\TestCase,
	Cake\Log\Log,
	Cake\Log\Engine\FileLog,
	Cake\Core\App,
	Cake\Core\Plugin;

/**
 * LogTest class
 *
 * @package       Cake.Test.Case.Log
 */
class LogTest extends TestCase {

/**
 * Start test callback, clears all streams enabled.
 *
 * @return void
 */
	public function setUp() {
		parent::setUp();
		$streams = Log::configured();
		foreach ($streams as $stream) {
			Log::drop($stream);
		}
	}

/**
 * test importing loggers from app/libs and plugins.
 *
 * @return void
 */
	public function testImportingLoggers() {
		App::build(array(
			'libs' => array(CAKE . 'Test' . DS . 'TestApp' . DS . 'Lib' . DS),
			'plugins' => array(CAKE . 'Test' . DS . 'TestApp' . DS . 'Plugin' . DS)
		), true);
		Plugin::load('TestPlugin');

		$result = Log::config('libtest', array(
			'engine' => 'TestAppLog'
		));
		$this->assertTrue($result);
		$this->assertEquals(Log::configured(), array('libtest'));

		$result = Log::config('plugintest', array(
			'engine' => 'TestPlugin.TestPluginLog'
		));
		$this->assertTrue($result);
		$this->assertEquals(Log::configured(), array('libtest', 'plugintest'));

		App::build();
		Plugin::unload();
	}

/**
 * test all the errors from failed logger imports
 *
 * @expectedException Cake\Error\LogException
 * @return void
 */
	public function testImportingLoggerFailure() {
		Log::config('fail', array());
	}

/**
 * test that loggers have to implement the correct interface.
 *
 * @expectedException Cake\Error\LogException
 * @return void
 */
	public function testNotImplementingInterface() {
		Log::config('fail', array('engine' => 'stdClass'));
	}

/**
 * Test that Log autoconfigures itself to use a FileLogger with the LOGS dir.
 * When no streams are there.
 *
 * @return void
 */
	public function testAutoConfig() {
		if (file_exists(LOGS . 'error.log')) {
			unlink(LOGS . 'error.log');
		}
		Log::write(LOG_WARNING, 'Test warning');
		$this->assertTrue(file_exists(LOGS . 'error.log'));

		$result = Log::configured();
		$this->assertEquals($result, array('default'));
		unlink(LOGS . 'error.log');
	}

/**
 * test configuring log streams
 *
 * @return void
 */
	public function testConfig() {
		Log::config('file', array(
			'engine' => 'FileLog',
			'path' => LOGS
		));
		$result = Log::configured();
		$this->assertEquals($result, array('file'));

		if (file_exists(LOGS . 'error.log')) {
			@unlink(LOGS . 'error.log');
		}
		Log::write(LOG_WARNING, 'Test warning');
		$this->assertTrue(file_exists(LOGS . 'error.log'));

		$result = file_get_contents(LOGS . 'error.log');
		$this->assertRegExp('/^2[0-9]{3}-[0-9]+-[0-9]+ [0-9]+:[0-9]+:[0-9]+ Warning: Test warning/', $result);
		unlink(LOGS . 'error.log');
	}

/**
 * explict tests for drop()
 *
 * @return void
 **/
	public function testDrop() {
		Log::config('file', array(
			'engine' => 'FileLog',
			'path' => LOGS
		));
		$result = Log::configured();
		$this->assertEquals($result, array('file'));

		Log::drop('file');
		$result = Log::configured();
		$this->assertEquals($result, array());
	}

/**
 * testLogFileWriting method
 *
 * @return void
 */
	public function testLogFileWriting() {
		if (file_exists(LOGS . 'error.log')) {
			unlink(LOGS . 'error.log');
		}
		$result = Log::write(LOG_WARNING, 'Test warning');
		$this->assertTrue($result);
		$this->assertTrue(file_exists(LOGS . 'error.log'));
		unlink(LOGS . 'error.log');

		Log::write(LOG_WARNING, 'Test warning 1');
		Log::write(LOG_WARNING, 'Test warning 2');
		$result = file_get_contents(LOGS . 'error.log');
		$this->assertRegExp('/^2[0-9]{3}-[0-9]+-[0-9]+ [0-9]+:[0-9]+:[0-9]+ Warning: Test warning 1/', $result);
		$this->assertRegExp('/2[0-9]{3}-[0-9]+-[0-9]+ [0-9]+:[0-9]+:[0-9]+ Warning: Test warning 2$/', $result);
		unlink(LOGS . 'error.log');
	}

}
