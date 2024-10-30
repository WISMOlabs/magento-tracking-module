<?php

namespace Wismolabs\Tracking\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\MessageQueue\ConsumerInterface;
use Magento\Framework\MessageQueue\PublisherInterface;
use \Magento\Framework\ObjectManagerInterface;
use Wismolabs\Tracking\Helper\Data as TrackingHelper;

class SalesShipmentTrackObserver implements ObserverInterface
{
    /**
     * Send Track Numbers queue topic name.
     */
    private const TOPIC_MEDIA_WISMOLABS_SEND_TRACKS = 'wismolabs_track_info.send';

    /**
     * @var PublisherInterface
     */
    protected $publisher;

    /**
     * @var TrackingHelper
     */
    protected $helper;

    /**
     * Constructor
     * @param PublisherInterface $publisher
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        PublisherInterface $publisher,
        ObjectManagerInterface $objectManager
    ) {
        $this->publisher = $publisher;
        $this->helper = $objectManager->get(TrackingHelper::class);
    }

    /**
     * Method to publish tracking data to queue
     *
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $event = $observer->getEvent();
        /** @var \Magento\Sales\Model\Order\Shipment\Track $track */
        $track = $event->getData('data_object');
        $storeId = $track->getStoreId();

        $postData = $this->helper->assembleData($track);
        if (!$postData) {
            return;
        }
        if ($this->helper->useQueue($storeId)) {
            $this->publisher->publish(
                self::TOPIC_MEDIA_WISMOLABS_SEND_TRACKS,
                json_encode([
                    "data" => $postData,
                    "storeId" => $storeId,
                    "trackId" => $track->getId(),
                     "orderId" => $track->getOrderId()
                ])
            );
        } else {
            $this->helper->sendTrack($postData, $storeId, $track->getId(), $track->getOrderId());
        }
    }
}
