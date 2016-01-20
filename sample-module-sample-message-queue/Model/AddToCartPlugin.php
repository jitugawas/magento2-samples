<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\SampleMessageQueue\Model;

use Magento\Checkout\Model\Cart;

/**
 * Test plugin to demonstrate sync and async queue messages
 */
class AddToCartPlugin
{
    /**
     * @var \Magento\Framework\MessageQueue\PublisherPool
     */
    protected $publisherPool;

    /**
     * @var \Psr\Log\LoggerInterface $logger
     */
    private $logger;

    /**
     * @var \Magento\GiftCardAccount\Model\GiftcardaccountFactory
     */
    protected $giftCardAccountFactory;

    /**
     * Initialize dependencies.
     *
     * @param \Magento\Framework\MessageQueue\PublisherPool $publisherPool
     * @param \Magento\GiftCardAccount\Model\GiftcardaccountFactory $giftCardAccountFactory
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Magento\Framework\MessageQueue\PublisherPool $publisherPool,
        \Magento\GiftCardAccount\Model\GiftcardaccountFactory $giftCardAccountFactory,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->publisherPool = $publisherPool;
        $this->logger = $logger;
        $this->giftCardAccountFactory = $giftCardAccountFactory;
    }

    /**
     * Add gift card account to notify customer via email
     *
     * @param Cart $subject
     * @param \Closure $proceed
     * @return Cart
     */
    public function aroundSave(
        Cart $subject,
        \Closure $proceed
    ) {
        $before = $subject->getItemsQty();
        $result = $proceed();
        $after = $subject->getItemsQty();

        if ($subject->getQuote()->getCustomerId() && $before == 0 && $after > $before) {
            $this->logger->debug('Plugin Start: Before items QTY: ' . $before . '; After Items QTY: ' . $after);
            try {
                $customer = $subject->getQuote()->getCustomer();
                $giftCardAccountCode = $this->publisherPool
                    ->publish('add.to.cart.product.added', $subject->getQuote()->getId());

                /** @var \Magento\GiftCardAccount\Model\Giftcardaccount $giftCard */
                $giftCard = $this->giftCardAccountFactory->create();
                $giftCard->loadByCode($giftCardAccountCode);
                if (!$giftCard->getId()) {
                    throw new \Exception('Invalid gift card code');
                }
                $payload = [
                    'amount' => $giftCard->getGiftCardsAmount(),
                    'customer_email' => $customer->getEmail(),
                    'customer_name' => $customer->getFirstname() . ' ' . $customer->getLastname(),
                    'cart_id' => $subject->getQuote()->getId(),
                ];

                $this->publisherPool
                    ->publish('add.to.cart.giftcard.added', json_encode($payload));

            } catch (\Exception $e) {
                $this->logger->debug('Plugin Error: ' . $e->getMessage());
            }
            $this->logger->debug('Plugin End');
        } else {
            //Just for debugging
            $this->logger->debug('Plugin: do nothing. ' . $before .' != 0 :');
        }
        return $result;
    }
}
