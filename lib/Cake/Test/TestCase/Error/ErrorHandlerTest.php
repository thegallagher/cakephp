<?php
/**
 * ErrorHandlerTest file
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
 * @package       Cake.Test.Case.Error
 * @since         CakePHP(tm) v 1.2.0.5432
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
namespace Cake\Test\TestCase\Error;
use Cake\TestSuite\TestCase,
	Cake\Error\ErrorHandler,
	Cake\Controller\Controller,
	Cake\Routing\Router,
	Cake\Core\App,
	Cake\Core\Configure,
	Cake\Core\Plugin,
	Cake\Network\Request,
	Cake\Error;

/**
 * ErrorHandlerTest class
 *
 * @package       Cake.Test.Case.Error
 */
class ErrorHandlerTest extends TestCase {

	public $_restoreError = false;

/**
 * setup create a request object to get out of router later.
 *
 * @return void
 */
	public function setUp() {
		parent::setUp();
		App::build(array(
			'View' => array(
				CAKE . 'Test' . DS . 'TestApp' . DS . 'View'. DS
			)
		), true);
		Router::reload();

		$request = new Request(null, false);
		$request->base = '';
		Router::setRequestInfo($request);
		$this->_debug = Configure::read('debug');
		$this->_error = Configure::read('Error');
		Configure::write('debug', 2);
	}

/**
 * tearDown
 *
 * @return void
 */
	public function tearDown() {
		Configure::write('debug', $this->_debug);
		Configure::write('Error', $this->_error);
		App::build();
		if ($this->_restoreError) {
			restore_error_handler();
		}
		parent::tearDown();
	}

/**
 * test error handling when debug is on, an error should be printed from Debugger.
 *
 * @return void
 */
	public function testHandleErrorDebugOn() {
		set_error_handler('Cake\Error\ErrorHandler::handleError');
		$this->_restoreError = true;

		ob_start();
		$wrong .= '';
		$result = ob_get_clean();

		$this->assertRegExp('/<pre class="cake-error">/', $result);
		$this->assertRegExp('/<b>Notice<\/b>/', $result);
		$this->assertRegExp('/variable:\s+wrong/', $result);
	}

/**
 * provides errors for mapping tests.
 *
 * @return void
 */
	public static function errorProvider() {
		return array(
			array(E_USER_NOTICE, 'Notice'),
			array(E_USER_WARNING, 'Warning'),
			array(E_USER_ERROR, 'Fatal Error'),
		);
	}

/**
 * test error mappings
 *
 * @dataProvider errorProvider
 * @return void
 */
	public function testErrorMapping($error, $expected) {
		set_error_handler('Cake\Error\ErrorHandler::handleError');
		$this->_restoreError = true;

		ob_start();
		trigger_error('Test error', $error);

		$result = ob_get_clean();
		$this->assertRegExp('/<b>' . $expected . '<\/b>/', $result);
	}

/**
 * test error prepended by @
 *
 * @return void
 */
	public function testErrorSuppressed() {
		set_error_handler('Cake\Error\ErrorHandler::handleError');
		$this->_restoreError = true;

		ob_start();
		@include 'invalid.file';
		$result = ob_get_clean();
		$this->assertTrue(empty($result));
	}

/**
 * Test that errors go into CakeLog when debug = 0.
 *
 * @return void
 */
	public function testHandleErrorDebugOff() {
		Configure::write('debug', 0);
		Configure::write('Error.trace', false);
		if (file_exists(LOGS . 'debug.log')) {
			@unlink(LOGS . 'debug.log');
		}

		set_error_handler('Cake\Error\ErrorHandler::handleError');
		$this->_restoreError = true;

		$out .= '';

		$result = file(LOGS . 'debug.log');
		$this->assertEquals(count($result), 1);
		$this->assertRegExp(
			'/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} (Notice|Debug): Notice \(8\): Undefined variable:\s+out in \[.+ line \d+\]$/',
			$result[0]
		);
		@unlink(LOGS . 'debug.log');
	}

/**
 * Test that errors going into CakeLog include traces.
 *
 * @return void
 */
	public function testHandleErrorLoggingTrace() {
		Configure::write('debug', 0);
		Configure::write('Error.trace', true);
		if (file_exists(LOGS . 'debug.log')) {
			@unlink(LOGS . 'debug.log');
		}

		set_error_handler('Cake\Error\ErrorHandler::handleError');
		$this->_restoreError = true;

		$out .= '';

		$result = file(LOGS . 'debug.log');
		$this->assertRegExp(
			'/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} (Notice|Debug): Notice \(8\): Undefined variable:\s+out in \[.+ line \d+\]$/',
			$result[0]
		);
		$this->assertRegExp('/^Trace:/', $result[1]);
		$this->assertRegExp('/^' . preg_quote(__NAMESPACE__, '/') . '\\\ErrorHandlerTest\:\:testHandleErrorLoggingTrace\(\)/', $result[2]);
		@unlink(LOGS . 'debug.log');
	}

/**
 * test handleException generating a page.
 *
 * @return void
 */
	public function testHandleException() {
		$this->skipIf(file_exists(APP . 'app_error.php'), 'App error exists cannot run.');

		$error = new Error\NotFoundException('Kaboom!');
		ob_start();
		ErrorHandler::handleException($error);
		$result = ob_get_clean();
		$this->assertRegExp('/Kaboom!/', $result, 'message missing.');
	}

/**
 * test handleException generating a page.
 *
 * @return void
 */
	public function testHandleExceptionLog() {
		$this->skipIf(file_exists(APP . 'app_error.php'), 'App error exists cannot run.');

		if (file_exists(LOGS . 'error.log')) {
			unlink(LOGS . 'error.log');
		}
		Configure::write('Exception.log', true);
		$error = new Error\NotFoundException('Kaboom!');

		ob_start();
		ErrorHandler::handleException($error);
		$result = ob_get_clean();
		$this->assertRegExp('/Kaboom!/', $result, 'message missing.');

		$log = file(LOGS . 'error.log');
		$this->assertRegExp('/\[Cake\\\Error\\\NotFoundException\] Kaboom!/', $log[0], 'message missing.');
		$this->assertRegExp('/\#0.*ErrorHandlerTest->testHandleExceptionLog/', $log[1], 'Stack trace missing.');
	}

/**
 * tests it is possible to load a plugin exception renderer
 *
 * @return void
 */
	public function testLoadPluginHanlder() {
		App::build(array(
			'plugins' => array(
				CAKE . 'Test' . DS . 'TestApp' . DS . 'Plugin' . DS
			)
		), true);
		Plugin::load('TestPlugin');
		Configure::write('Exception.renderer', 'TestPlugin.TestPluginExceptionRenderer');
		$error = new Error\NotFoundException('Kaboom!');
		ob_start();
		ErrorHandler::handleException($error);
		$result = ob_get_clean();
		$this->assertEquals($result, 'Rendered by test plugin');
		Plugin::unload();
	}

}
