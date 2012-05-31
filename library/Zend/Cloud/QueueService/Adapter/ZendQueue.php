<?php
/**
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Cloud_QueueService
 * @subpackage Adapter
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

namespace Zend\Cloud\QueueService\Adapter;

use Traversable;
use Zend\Stdlib\ArrayUtils;
use Zend\Cloud\QueueService\Exception,
    Zend\Queue\Queue;

/**
 * WindowsAzure adapter for simple queue service.
 *
 * @category   Zend
 * @package    Zend_Cloud_QueueService
 * @subpackage Adapter
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class ZendQueue extends AbstractAdapter
{
    /**
     * Options array keys for the Zend\Queue adapter.
     */
    const ADAPTER = 'adapter';

    /**
     * Storage client
     *
     * @var \Zend\Queue\Queue
     */
    protected $_queue = null;

    /**
     * @var array All queues
     */
    protected $_queues = array();

    /**
     * Constructor
     *
     * @param  array|Traversable $options
     */
    public function __construct ($options = array())
    {
        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        }

        if (!is_array($options)) {
            throw new Exception\InvalidArgumentException('Invalid options provided');
        }

        if (isset($options[self::MESSAGE_CLASS])) {
            $this->setMessageClass($options[self::MESSAGE_CLASS]);
        }

        if (isset($options[self::MESSAGESET_CLASS])) {
            $this->setMessageSetClass($options[self::MESSAGESET_CLASS]);
        }

        // Build Zend\Service\WindowsAzure\Storage\Blob instance
        if (!isset($options[self::ADAPTER])) {
            throw new Exception\InvalidArgumentException('No \Zend\Queue adapter provided');
        } else {
            $adapter = $options[self::ADAPTER];
            unset($options[self::ADAPTER]);
        }
        try {
            $this->_queue = new Queue($adapter, $options);
        } catch (\Zend\Queue\Exception\ExceptionInterface $e) {
            throw new Exception\RunTimeException('Error on create: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Create a queue. Returns the ID of the created queue (typically the URL).
     * It may take some time to create the queue. Check your vendor's
     * documentation for details.
     *
     * @param  string $name
     * @param  array  $options
     * @return string Queue ID (typically URL)
     */
    public function createQueue($name, $options = null)
    {
        try {
            $this->_queues[$name] = $this->_queue->createQueue($name, isset($options[Queue::TIMEOUT])?$options[Queue::TIMEOUT]:null);
            return $name;
        } catch (\Zend\Queue\Exception\ExceptionInterface $e) {
            throw new Exception\RuntimeException('Error on queue creation: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Delete a queue. All messages in the queue will also be deleted.
     *
     * @param  string $queueId
     * @param  array  $options
     * @return boolean true if successful, false otherwise
     */
    public function deleteQueue($queueId, $options = null)
    {
        if (!isset($this->_queues[$queueId])) {
            return false;
        }
        try {
            if ($this->_queues[$queueId]->deleteQueue()) {
                unset($this->_queues[$queueId]);
                return true;
            }
        } catch (\Zend\Queue\Exception\ExceptionInterface $e) {
            throw new Exception\RuntimeException('Error on queue deletion: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * List all queues.
     *
     * @param  array $options
     * @return array Queue IDs
     */
    public function listQueues($options = null)
    {
        try {
            return $this->_queue->getQueues();
        } catch (\Zend\Queue\Exception\ExceptionInterface $e) {
            throw new Exception\RuntimeException('Error on listing queues: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Get a key/value array of metadata for the given queue.
     *
     * @param  string $queueId
     * @param  array  $options
     * @return array
     */
    public function fetchQueueMetadata($queueId, $options = null)
    {
        if (!isset($this->_queues[$queueId])) {
            return false;
        }
        try {
            return $this->_queues[$queueId]->getOptions();
        } catch (\Zend\Queue\Exception\ExceptionInterface $e) {
            throw new Exception\RuntimeException('Error on fetching queue metadata: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Store a key/value array of metadata for the specified queue.
     * WARNING: This operation overwrites any metadata that is located at
     * $destinationPath. Some adapters may not support this method.
     *
     * @param  string $queueId
     * @param  array  $metadata
     * @param  array  $options
     * @return void
     */
    public function storeQueueMetadata($queueId, $metadata, $options = null)
    {
        if (!isset($this->_queues[$queueId])) {
            throw new Exception\InvalidArgumentException("No such queue: $queueId");
        }
        try {
            return $this->_queues[$queueId]->setOptions($metadata);
        } catch (\Zend\Queue\Exception\ExceptionInterface $e) {
            throw new Exception\RuntimeException('Error on setting queue metadata: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Send a message to the specified queue.
     *
     * @param  string $queueId
     * @param  string $message
     * @param  array  $options
     * @return string Message ID
     */
    public function sendMessage($queueId, $message, $options = null)
    {
        if (!isset($this->_queues[$queueId])) {
            throw new Exception\InvalidArgumentException("No such queue: $queueId");
        }
        try {
            return $this->_queues[$queueId]->send($message);
        } catch (\Zend\Queue\Exception\ExceptionInterface $e) {
            throw new Exception\RunTimeException('Error on sending message: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Receive at most $max messages from the specified queue and return the
     * message IDs for messages received.
     *
     * @param  string $queueId
     * @param  int    $max
     * @param  array  $options
     * @return array
     */
    public function receiveMessages($queueId, $max = 1, $options = null)
    {
        if (!isset($this->_queues[$queueId])) {
            throw new Exception\InvalidArgumentException("No such queue: $queueId");
        }
        try {
            $res = $this->_queues[$queueId]->receive($max, isset($options[Queue::TIMEOUT])?$options[Queue::TIMEOUT]:null);
            if ($res instanceof \Iterator) {
                return $this->_makeMessages($res);
            } else {
                return $this->_makeMessages(array($res));
            }
        } catch (\Zend\Queue\Exception\ExceptionInterface $e) {
            throw new Exception\RuntimeException('Error on receiving messages: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Create Zend\Cloud\QueueService\Message array for
     * Azure messages.
     *
     * @param array $messages
     * @return \Zend\Cloud\QueueService\Message[]
     */
    protected function _makeMessages($messages)
    {
        $messageClass = $this->getMessageClass();
        $setClass     = $this->getMessageSetClass();
        $result = array();
        foreach ($messages as $message) {
            $result[] = new $messageClass($message->body, $message);
        }
        return new $setClass($result);
    }

    /**
     * Delete the specified message from the specified queue.
     *
     * @param  string $queueId
     * @param  \Zend\Cloud\QueueService\Message $message Message ID or message
     * @param  array  $options
     * @return void
     */
    public function deleteMessage($queueId, $message, $options = null)
    {
        if (!isset($this->_queues[$queueId])) {
            throw new Exception\InvalidArgumentException("No such queue: $queueId");
        }
        try {
            if ($message instanceof \Zend\Cloud\QueueService\Message) {
                $message = $message->getMessage();
            } else {
                throw new Exception\InvalidArgumentException('Cannot delete the message: \Zend\Queue\Message object required');
            }

            return $this->_queues[$queueId]->deleteMessage($message);
        } catch (\Zend\Queue\Exception\ExceptionInterface $e) {
            throw new Exception\RuntimeException('Error on deleting a message: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Peek at the messages from the specified queue without removing them.
     *
     * @param  string $queueId
     * @param  int $num How many messages
     * @param  array  $options
     * @return \Zend\Cloud\QueueService\Message[]
     */
    public function peekMessages($queueId, $num = 1, $options = null)
    {
        throw new Exception\OperationNotAvailableException('ZendQueue doesn\'t currently support message peeking');
    }

    /**
     * Get Azure implementation
     * @return \Zend\Queue\Queue
     */
    public function getClient()
    {
        return $this->_queue;
    }
}
