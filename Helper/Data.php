<?php
namespace Wismolabs\Tracking\Helper;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Helper\Context;
use \Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment\Track;
use \Magento\Sales\Model\Order\Shipment;
use \Magento\Catalog\Model\ProductFactory;
use \Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\Shipment\Track\Collection as TrackCollection;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    private const PREFIX = 'shipping/shipment_email_settings/';
    private const XML_PATH_ACCOUNT = 'account_id';
    private const XML_PATH_RETAILER_ID = 'retailer_id';
    private const XML_PATH_ORDER_BUTTON_CSS = 'order_button_css';

    private const XML_PATH_LINK_IN_SHIPPING_EMAIL = 'include_link_into_shipping_email';
    private const XML_PATH_LINK_IN_ORDER_CONFIRMATION_EMAIL = 'include_link_into_order_confirmation_email';
    private const XML_PATH_LINK_IN_ACCOUNT = 'include_link_into_order_history_pages';

    private const XML_PATH_AUTH_TOKEN = 'auth_token';
    private const XML_PATH_SEND_TRACK_URL = 'track_send_api_url';
    private const XML_PATH_RPS = 'rps';
    private const XML_PATH_ATTRS_TO_SEND = 'customer_attributes';
    private const XML_PATH_ATTRS_USE_QUEUE = 'use_mysql_queue';
    private const XML_PATH_ATTRS_TURN_ON_LOGS = 'use_logs';

    private const LOG_PREFIX = "Wismolabs_Tracking_Helper: ";

    /**
     * @var ProductFactory
     */
    protected $productFactory;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     *
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @param Context $context
     * @param ProductFactory $productFactory
     * @param CustomerRepositoryInterface $customerRepository
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        Context $context,
        ProductFactory $productFactory,
        CustomerRepositoryInterface $customerRepository,
        OrderRepositoryInterface $orderRepository
    ) {
        parent::__construct($context);
        $this->productFactory = $productFactory;
        $this->customerRepository = $customerRepository;
        $this->orderRepository = $orderRepository;
    }

    /**
     * Wrapper method around config value getter
     *
     * @param string $config_path
     * @param null|int|string $scopeCode
     */
    public function getConfig($config_path, $scopeCode = null)
    {
        return $this->scopeConfig->getValue(
            self::PREFIX . $config_path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $scopeCode
        );
    }

    /**
     * Get Shipping Method
     *
     * @param Order $order
     */
    private function getMethod(Order $order)
    {
        $shippingMethod = $order->getShippingMethod();
        return is_object($shippingMethod) ? $shippingMethod['method'] : $shippingMethod;
    }

    /**
     * Get Carrier Code
     *
     * @param Order $order
     * @param Track $shipmentTrack
     */
    private function getCarrierCode(Order $order, Track $shipmentTrack)
    {
        $shippingMethod = $order->getShippingMethod();
        return is_object($shippingMethod) ? $shippingMethod['carrier_code'] : $shipmentTrack->getCarrierCode();
    }

    /**
     * Get Skus From Shipment
     *
     * @param Shipment $shipment
     */
    private function getProductSkus(Shipment $shipment)
    {
        $skus = array_map(
            function ($shipmentItem) {
                return $shipmentItem->getSku();
            },
            !empty($shipment->getItems()) ? $shipment->getItems() : []
        );
        return implode(",", array_unique($skus));
    }

    /**
     * Get Product Skus from order
     *
     * @param Order $order
     */
    private function getProductSkusByOrder(\Magento\Sales\Model\Order $order)
    {
        $skus = array_map(
            function ($item) {
                return $item->getSku();
            },
            !empty($order->getItems()) ? $order->getItems() : []
        );
        return implode(",", array_unique($skus));
    }

    /**
     * Get Category Ids from shipment
     *
     * @param Shipment $shipment
     */
    private function getCategoryIds(Shipment $shipment)
    {
        $categoryIds = array_map(
            function ($shipmentItem) {
                $productId = $shipmentItem->getProductId();
                $product = $this->productFactory->create()->load($productId);
                return $product->getCategoryIds();
            },
            !empty($shipment->getItems()) ? $shipment->getItems() : []
        );
        $oneDimension = call_user_func_array('array_merge', $categoryIds);
        return implode(",", array_unique($oneDimension));
    }

    /**
     * Get Category Ids from order
     *
     * @param Order $order
     */
    private function getCategoryIdsByOrder(\Magento\Sales\Model\Order $order)
    {
        $categoryIds = array_map(
            function ($item) {
                $productId = $item->getProductId();
                $product = $this->productFactory->create()->load($productId);
                return $product->getCategoryIds();
            },
            !empty($order->getItems()) ? $order->getItems() : []
        );
        $oneDimension = call_user_func_array('array_merge', $categoryIds);
        return implode(",", array_unique($oneDimension));
    }

    /**
     * Get List of Customers Attributes to be used
     *
     * @param null|int|string $storeId
     */
    protected function getCustomerAttributesToExtract($storeId)
    {
        return $this->getConfig(self::XML_PATH_ATTRS_TO_SEND, $storeId);
    }

    /**
     * Is Link need to be added to shipment email
     *
     * @param null|int|string $storeId
     */
    public function isLinkEnabledInShippingEmail($storeId)
    {
        return $this->getConfig(self::XML_PATH_LINK_IN_SHIPPING_EMAIL, $storeId);
    }

    /**
     * Is Link need to be added to order confirmation email
     *
     * @param null|int|string $storeId
     */
    public function isLinkEnabledInOrderConfirmationEmail($storeId)
    {
        return $this->getConfig(self::XML_PATH_LINK_IN_ORDER_CONFIRMATION_EMAIL, $storeId);
    }

    /**
     * Is Link need to be added to account orders page
     *
     * @param null|int|string $storeId
     */
    public function isLinkEnabledInUserAccountOrdersPage($storeId)
    {
        return $this->getConfig(self::XML_PATH_LINK_IN_ACCOUNT, $storeId);
    }

    /**
     * Getter name by attribute code
     *
     * @param string $a
     */
    protected function getterName($a)
    {
        return "get" . str_replace(' ', '', ucwords(str_replace('_', ' ', $a)));
    }

    /**
     * Get Customer Attribute Values from order
     *
     * @param Order $order
     */
    protected function getCustomerAttributeValues($order)
    {
        $customerId = $order->getCustomerId();
        if (!$customerId) {
            return [];
        }
        $customer = $this->customerRepository->getById($customerId);
        $attrCodesToSend = $this->getCustomerAttributesToExtract($customer->getStoreId());
        if (!$attrCodesToSend) {
            return [];
        }
        $attrCodesToSendArray = explode(",", $attrCodesToSend);

        $result = [];
        /** @var \Magento\Customer\Model\Data\Customer $customer */
        foreach ($attrCodesToSendArray as $attrCode) {
            $methodName = $this->getterName($attrCode);
            if (method_exists($customer, $methodName)) {
                $attrVal = call_user_func([$customer, $methodName]);
            } else {
                $attrVal = $customer->getCustomAttribute($attrCode);
            }

            if (is_object($attrVal)) {
                $attrVal = (array) $attrVal;
            }

            if (!$attrVal) {
                continue;
            }

            if (is_array($attrVal)) {
                $attrVal = json_encode($attrVal, 0, 10);
            }

            $result[$attrCode] = $attrVal;
        }
        return $result;
    }

    /**
     * Generate Url
     *
     * @param Order $order
     * @param mixed $tracks
     */
    public function getCombinedUrl(Order $order, $tracks = null)
    {
        $trackCollection = $tracks ? $tracks : $order->getTracksCollection();
        if ($trackCollection) {
            $orderNumber = $order->getRealOrderId();
            $orderDate = $order->getCreatedAt() ? substr($order->getCreatedAt(), 0, 10) : "";
            $customerName = $order->getCustomerFirstname();
            $originZIP = $this->getConfig(Shipment::XML_PATH_STORE_ZIP, $order->getStoreId());
            $shippingAddress = $order->getShippingAddress();
            $slug = $this->getConfig(self::XML_PATH_ACCOUNT, $order->getStoreId());
            $host = strpos($slug, ".") ? $slug : $slug . ".wismolabs.com";
            $retailerID = $this->getConfig(self::XML_PATH_RETAILER_ID, $order->getStoreId());

            $trackingNumbers = [];
            $carriers = [];
            $shippingDates = [];
            $categoryIdsSet = [];
            $productIdsSet = [];

            $estimatedDeliveryDate = "";

            $serviceMethod = $this->getMethod($order);

            $destinationZIP = $shippingAddress ? $shippingAddress->getPostcode() : "";
            $destinationCountry = $shippingAddress->getCountryId();

            if (count($trackCollection)) {
                /** @var \Magento\Sales\Model\Order\Shipment\Track $shipmentTrack */
                foreach ($trackCollection as $shipmentTrack) {

                    $shipment = $shipmentTrack->getShipment();
                    $trackingNumber = $shipmentTrack->getNumber();
                    $trackingNumbers[] = $trackingNumber;

                    $carrier = $this->getCarrierCode($order, $shipmentTrack);
                    $carriers[] = $carrier;

                    $shippingDate = $shipment->getCreatedAt() ? substr($shipment->getCreatedAt(), 0, 10) : "";
                    $shippingDates[] = $shippingDate;

                    $categoryIds = $this->getCategoryIds($shipment);
                    $categoryIdsSet[] = $categoryIds;
                    $productIds = $this->getProductSkus($shipment);
                    $productIdsSet[] = $productIds;
                }
            } else {
                $categoryIds = $this->getCategoryIdsByOrder($order);
                $categoryIdsSet[] = $categoryIds;
                $productIds = $this->getProductSkusByOrder($order);
                $productIdsSet[] = $productIds;
            }

            $trackingNumbersStr = implode("|", array_unique($trackingNumbers));
            $carriersStr = implode("|", array_unique($carriers));
            $shippingDatesStr = implode("|", array_unique($shippingDates));
            $categoryIdsStr = implode("|", array_unique($categoryIdsSet));
            $productIdsStr = implode("|", array_unique($productIdsSet));
            $optional = $this->getCustomerAttributeValues($order);
            $optionalParams = $optional ? "&" . http_build_query($optional) : "";
            $url = "https://{$host}/{$retailerID}/tracking" .
                "?TRK={$trackingNumbersStr}&CAR={$carriersStr}" .
                "&SERV={$serviceMethod}&ON={$orderNumber}&OD={$orderDate}&SD={$shippingDatesStr}" .
                "&ED={$estimatedDeliveryDate}&name={$customerName}" .
                "&oZIP={$originZIP}&dZIP={$destinationZIP}&dCountry={$destinationCountry}" .
                "&CID={$categoryIdsStr}&PID={$productIdsStr}{$optionalParams}";
            return $url;
        }
        return "";
    }

    /**
     * Get Html For Track Button
     *
     * @param string $trackButtonUrl
     */
    public function getTrackButtonHtml($trackButtonUrl)
    {
        $html = $this->getConfig(self::XML_PATH_ORDER_BUTTON_CSS);
        return str_replace('{{WISMOLINK}}', $trackButtonUrl, $html);
    }

    /**
     * Get Url For One Track
     *
     * @param Order $order
     * @param Track $shipmentTrack
     */
    public function getUrl(Order $order, Track $shipmentTrack)
    {
        return $this->getCombinedUrl($order, [$shipmentTrack]);
    }

    /**
     * Get Tracks Data
     *
     * @param Order $order
     * @param mixed $tracks
     */
    public function getTracksData(Order $order, $tracks = null)
    {
        $trackCollection = $tracks ? $tracks : $order->getTracksCollection();
        if ($trackCollection) {
            $orderNumber = $order->getRealOrderId();
            $orderDate = $order->getCreatedAt() ? substr($order->getCreatedAt(), 0, 10) : "";
            $originZIP = $this->getConfig(Shipment::XML_PATH_STORE_ZIP, $order->getStoreId());
            $shippingAddress = $order->getShippingAddress();

            $trackingNumbers = [];
            $carriers = [];
            $serviceMethods = [];
            $shippingDates = [];
            $destinationZIPs = [];
            $categoryIdsSet = [];
            $productIdsSet = [];
            $destinationCountry = "";

            /** @var \Magento\Sales\Model\Order\Shipment\Track $shipmentTrack */
            foreach ($trackCollection as $shipmentTrack) {

                $shipment = $shipmentTrack->getShipment();
                $trackingNumber = $shipmentTrack->getNumber();
                $trackingNumbers[] = $trackingNumber;

                $carrier = $this->getCarrierCode($order, $shipmentTrack);
                $carriers[] = $carrier;

                $serviceMethod = $this->getMethod($order);
                $serviceMethods[] = $serviceMethod;

                $shippingDate = $shipment->getCreatedAt() ? substr($shipment->getCreatedAt(), 0, 10) : "";
                $shippingDates[] = $shippingDate;

                $estimatedDeliveryDate = "";

                $destinationZIP = $shippingAddress ? $shippingAddress->getPostcode() : "";
                $destinationZIPs[] = $destinationZIP;

                $destinationCountry = $shippingAddress->getCountryId();
                $categoryIds = $this->getCategoryIds($shipment);
                $categoryIdsSet[] = $categoryIds;
                $productIds = $this->getProductSkus($shipment);
                $productIdsSet[] = $productIds;
                $tags = "";
            }

            $trackingNumbersStr = implode("|", array_unique($trackingNumbers));
            $carriersStr = implode("|", array_unique($carriers));
            $serviceMethodStr = implode("|", array_unique($serviceMethods));
            $shippingDatesStr = implode("|", array_unique($shippingDates));
            $destinationZIPsStr = implode("|", array_unique($destinationZIPs));
            $categoryIdsStr = implode("|", array_unique($categoryIdsSet));
            $productIdsStr = implode("|", array_unique($productIdsSet));
            if ($categoryIdsStr) {
                $categoryMap = [ "cid" => $categoryIdsStr];
            } else {
                $categoryMap = [];
            }
            $customerAttributes = $this->getCustomerAttributeValues($order);
            $optional = $customerAttributes ? ["optional" => $customerAttributes] : [];
            return array_merge([
                "name" => $order->getCustomerFirstname() ? $order->getCustomerFirstname() : "-",
                "email" => $order->getCustomerEmail(),
                "trk" => $trackingNumbersStr,
                "on" => $orderNumber,
                "car" => $carriersStr,
                "serv" => $serviceMethodStr,
                "od" => $orderDate,
                "sd" => $shippingDatesStr,
                "ozip" => $originZIP,
                "dzip" => $destinationZIPsStr,
                "dcountry" => $destinationCountry,
                "pid" => $productIdsStr,
                "tag" => "mg"
            ], $optional, $categoryMap);
        }
        return [];
    }

    /**
     * Get Single Track Data
     *
     * @param Order $order
     * @param Track $shipmentTrack
     */
    public function getTrackData(Order $order, Track $shipmentTrack)
    {
        return $this->getTracksData($order, [$shipmentTrack]);
    }

    /**
     * Assemble json
     *
     * @param Track $track
     */
    public function assembleData($track)
    {
        try {
            $order = $this->orderRepository->get($track->getOrderId());
            $data = $this->getTrackData($order, $track);
            return json_encode($data);
        } catch (\Exception $e) {
            $this->log($e->getMessage() . " " . $e->getTraceAsString(), $track->getStoreId());
            return "";
        }
    }

    /**
     * Send Request
     *
     * @param string $postData
     * @param null|int|string $storeId
     * @param string $trackId
     * @param string $orderId
     */
    public function sendTrack($postData, $storeId, $trackId, $orderId)
    {
        $opts = ['http' =>
            [
                'method'  => 'POST',
                'header'  => [
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'x-wismo-auth-token: ' . $this->getAuthToken($storeId)
                ],
                'content' => $postData,
                'ignore_errors' => true
            ]
        ];

        try {
            $context  = stream_context_create($opts);
            $url = $this->getUrlToSendTrack($storeId);
            //$w = stream_get_wrappers();// you might want to check if $w has "https" value in it if this code has issue
    
            $response = file_get_contents($url, false, $context);
            $responseCode = $http_response_header[0];
            if (str_contains($responseCode, "HTTP/1.1 20")) {
                $this->log(
                    self::LOG_PREFIX .
                    'track saved, trackId: ' . $trackId .
                    ', orderId: ' . $orderId .
                    ', response: ' . $response,
                    $storeId
                );
            } else {
                $this->log(
                    self::LOG_PREFIX . 'track hasn\'t been saved, trackId: ' . $trackId . ", orderId: " . $orderId .
                    ", responseCode: " . $responseCode . ", response: " . $response,
                    $storeId
                );
            }
            $this->waitIfQueue($storeId);
        } catch (\Exception $e) {
            $this->log(
                self::LOG_PREFIX . 'potential issue with server settings, trackId: ' .
                $trackId . ", orderId: " .
                $orderId . "err:" . $e->getMessage(),
                $storeId
            );
        }
    }

    /**
     * Url for request
     *
     * @param null|int|string $storeId
     */
    public function getUrlToSendTrack($storeId)
    {
        return $this->getConfig(self::XML_PATH_SEND_TRACK_URL, $storeId);
    }

    /**
     * Rps
     *
     * @param null|int|string $storeId
     */
    public function getRPS($storeId)
    {
        return $this->getConfig(self::XML_PATH_RPS, $storeId);
    }

    /**
     * Use Queue
     *
     * @param null|int|string $storeId
     */
    public function useQueue($storeId)
    {
        return $this->getConfig(self::XML_PATH_ATTRS_USE_QUEUE, $storeId);
    }

    /**
     * Check if logging is enabled
     *
     * @param null|int|string $storeId
     */
    public function areLogsEnabled($storeId)
    {
        return $this->getConfig(self::XML_PATH_ATTRS_TURN_ON_LOGS, $storeId);
    }

    /**
     * Get Token
     *
     * @param null|int|string $storeId
     */
    public function getAuthToken($storeId)
    {
        return $this->getConfig(self::XML_PATH_AUTH_TOKEN, $storeId);
    }

    /**
     * Sleep to control rps
     *
     * @param null|int|string $storeId
     */
    private function waitIfQueue($storeId)
    {
        if ($this->useQueue($storeId) && $this->getRPS($storeId)) {
            usleep(intval(1000000/$this->getRPS($storeId)));
        }
    }

    /**
     * Log message
     *
     * @param string $message
     * @param null|int|string $storeId
     */
    private function log($message, $storeId)
    {
        if ($this->areLogsEnabled($storeId)) {
            $this->_logger->info($message);
        }
    }
}
