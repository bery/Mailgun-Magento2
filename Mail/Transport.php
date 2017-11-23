<?php

namespace Bogardo\Mailgun\Mail;

use Bogardo\Mailgun\Helper\Config as Config;
use InvalidArgumentException;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Mail\MessageInterface;
use Magento\Framework\Mail\Transport as MagentoTransport;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Phrase;
use Mailgun\Mailgun;
use Mailgun\Messages\MessageBuilder;
use Zend_Mail;


class Transport extends MagentoTransport implements TransportInterface
{

    /**
     * @var \Bogardo\Mailgun\Helper\Config
     */
    protected $config;

    /**
     * @var \Magento\Framework\Mail\MessageInterface|Zend_Mail
     */
    protected $message;

    /**
     * Transport constructor.
     *
     * @param \Magento\Framework\Mail\MessageInterface $message
     * @param null                                     $parameters
     *
     * @throws InvalidArgumentException
     */
    public function __construct(MessageInterface $message, $parameters = null)
    {
        parent::__construct($message, $parameters);

        $this->config = ObjectManager::getInstance()->create(Config::class);
        $this->message = $message;
    }

    /**
     * Send a mail using this transport
     *
     * @return void
     */
    public function sendMessage()
    {
        // If Mailgun Service is disabled, use the default mail transport
        if (!$this->config->enabled()) {
            parent::sendMessage();

            return;
        }

        $message = $this->createMailgunMessage($this->message);

        $mailgun = Mailgun::create($this->config->getApiKey());

        $mailgun->messages->send($this->config->domain(), $message->getTo(), $message->toString());
    }

    /**
     * @return \Http\Client\HttpClient
     */
    protected function getHttpClient()
    {
        return new \Http\Adapter\Guzzle6\Client();
    }

    /**
     * @param array $message
     *
     * @return \Mailgun\Messages\MessageBuilder
     * @throws \Mailgun\Messages\Exceptions\TooManyParameters
     */
    protected function createMailgunMessage(array $message)
    {
        $message = \Swift_Message::newInstance($message['subject']);
        $message->setFrom($message['from']);
        // We need all "tos". Incluce the BCC here.
        $message->setTo(array_merge($message['to'],$message['bcc']));
        $message->setCc($message['cc']);

        $message->setBody($message['text'], 'text');
        $message->setBody($message['html'], 'text/html');

        // Send the message
        return $message;
    }

}
