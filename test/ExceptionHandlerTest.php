<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Console;

use DomainException;
use Exception;
use RuntimeException;
use PHPUnit\Framework\TestCase;
use Zend\Console\Adapter\AdapterInterface;
use ZF\Console\ExceptionHandler;

/**
 * @group 9
 */
class ExceptionHandlerTest extends TestCase
{
    public function setUp()
    {
        $this->console = $this->getMockBuilder(AdapterInterface::class)->getMock();
        $this->handler = new ExceptionHandler($this->console);
    }

    public function testMessageTemplateIsPopulatedByDefault()
    {
        $this->assertAttributeContains(':className', 'messageTemplate', $this->handler);
        $this->assertAttributeContains(':message', 'messageTemplate', $this->handler);
    }

    public function testCanSetCustomMessageTemplate()
    {
        $this->handler->setMessageTemplate('testing');
        $this->assertAttributeEquals('testing', 'messageTemplate', $this->handler);
    }

    public function testCreateMessageFillsExpectedVariablesForExceptionWithoutPrevious()
    {
        $this->handler->setMessageTemplate(
            "ClassName: :className\nMessage: :message\nCode: :code\nFile: :file\n"
            . "Line: :line\nStack: :stack\nPrevious: :previous"
        );
        $exception = new Exception('testing', 127);
        $message = $this->handler->createMessage($exception);
        $this->assertContains('ClassName: ' . get_class($exception), $message);
        $this->assertContains('Message: ' . $exception->getMessage(), $message);
        $this->assertContains('Code: ' . $exception->getCode(), $message);
        $this->assertContains('File: ' . $exception->getFile(), $message);
        $this->assertContains('Line: ' . $exception->getLine(), $message);
        $this->assertContains('Stack: ' . $exception->getTraceAsString(), $message);
        $this->assertNotContains('Previous: :previous', $message);
    }

    public function testCreateMessageFillsExpectedVariablesForExceptionWithPrevious()
    {
        $this->handler->setMessageTemplate(
            "ClassName: :className\nMessage: :message\nCode: :code\nPrevious: :previous"
        );

        $first = new DomainException('initial exception', 1);
        $second = new RuntimeException('second exception', 2, $first);
        $third = new Exception('thrown exception', 3, $second);
        $message = $this->handler->createMessage($third);

        foreach ([$first, $second, $third] as $exception) {
            $this->assertContains('ClassName: ' . get_class($exception), $message);
            $this->assertContains('Message: ' . $exception->getMessage(), $message);
            $this->assertContains('Code: ' . $exception->getCode(), $message);
        }
        $this->assertNotContains('Previous: :previous', $message);
    }

    public function testErrorHandlingTriggersListeners()
    {
        $exception = new RuntimeException('Exception raised', 1);

        $handlerCount = 0;

        $listener = function ($error) use ($exception, &$handlerCount) {
            $this->assertSame($exception, $error, 'Listener did not receive same exception as was raised');
            ++$handlerCount;
        };

        $listener2 = clone $listener;

        $this->handler->attachListener($listener);
        $this->handler->attachListener($listener2);

        $exitCode = $this->handler->__invoke($exception);

        $this->assertSame(2, $handlerCount);
    }

    public function testTheSameListenerIsAttachedOnlyOnce()
    {
        $listener = function () {
        };
        $this->handler->attachListener($listener);
        $this->handler->attachListener($listener);
        $this->assertAttributeCount(1, 'listeners', $this->handler);
    }
}
