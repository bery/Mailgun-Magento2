<?php

namespace Bogardo\Mailgun\Mail;

use Bogardo\Mailgun\Helper\Config as Config;
use InvalidArgumentException;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Mail\MessageInterface;
use Magento\Framework\Mail\Transport as MagentoTransport;
use Magento\Framework\Mail\TransportInterface;
use Mailgun\Mailgun;
use Mailgun\HttpClientConfigurator;
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
        if($this->config->testMode()){
            $configurator = new HttpClientConfigurator();
            $configurator->setEndpoint('http://bin.mailgun.net/8e51aaa3');
            $configurator->setDebug(true);
            $mailgun = Mailgun::configure($configurator);
        }else{
            $mailgun = Mailgun::create($this->config->getApiKey());
        }
        $mailgun->messages()->send($this->config->domain(),
            [
                'from'    => $this->message->getFrom(),
                'to'      => $this->message->getRecipients(),
                'subject' => $this->message->getSubject(),
                'text'    => $this->message->getBodyText(true),
                'html'    => $this->message->getBodyHtml()
            ]);
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
    protected function createMailgunMessage($messageObject)
    {
        $message = new \Swift_Message();
        $message->setSubject($messageObject->getSubject());
        $message->setFrom($messageObject->getFrom());
        // We need all "tos". Incluce the BCC here.
        $message->setTo($messageObject->getRecipients());

        $message->setBody($messageObject->getBodyHtml(true), 'text/html');
        $message->addPart($messageObject->getBodyText(true), 'text/plain');

        // Send the message
        return $message;
    }

}
