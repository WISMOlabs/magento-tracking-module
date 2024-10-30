<?php
namespace Wismolabs\Tracking\Model;

use Wismolabs\Tracking\Helper\Data as TrackingHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\ObjectManagerInterface;

class SendTrackHandler
{
    /**
     * @var TrackingHelper
     */
    protected $helper;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @param ObjectManagerInterface $objectManager
     * @param Context $context
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        Context $context
    ) {
        $this->helper = $objectManager->get(TrackingHelper::class);
        $this->logger = $context->getLogger();
    }

    /**
     * Handle consumed messages
     *
     * @param string $message
     * @return string
     */
    public function process($message)
    {
        $this->logger->info("Received message for sending track");
        $data = json_decode($message, true);
        $trackId = $data["trackId"];
        $this->logger->info("About to handle trackId: " . $trackId);
        $orderId = $data["orderId"];
        $storeId = $data["storeId"];
        $trackData = $data["data"];
        $this->helper->sendTrack($trackData, $storeId, $trackId, $orderId);
        return $trackId . ' processed by SendTrackerHandler';
    }
}
