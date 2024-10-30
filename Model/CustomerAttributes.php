<?php

namespace Wismolabs\Tracking\Model;

class CustomerAttributes implements \Magento\Framework\Option\ArrayInterface
{
    /**
     *
     * @var \Magento\Customer\Model\CustomerFactory
     */
    protected $customerFactory;

    /**
     * Options array
     *
     * @var array
     */
    protected $options = [];

    /**
     * Attribute to skip before rendering list of customer attributes in admin config
     *
     * @var array
     */
    protected $hiddenAttrs = [
        "disable_auto_group_change",
        "password_hash",
        "tax_vat",
        "failures_num",
        "first_failure",
        "rp_token",
        "rp_token_created_at",
        "lock_expires",
        "created_at",
        "confirmation",
        "entity_id",
        "increment_id",
        "default_billing",
        "default_shipping",
        "updated_at",
        "taxvat",
        "website_id",
        "created_in",
        "firstname",
        "email"
    ];

    /**
     * @param \Magento\Customer\Model\CustomerFactory $customerFactory
     */
    public function __construct(\Magento\Customer\Model\CustomerFactory $customerFactory)
    {
        $this->customerFactory = $customerFactory;
    }

    /**
     * Return options array
     *
     * @param boolean $isMultiselect
     * @param string|array $foregroundCountries
     * @return array
     */
    public function toOptionArray($isMultiselect = false, $foregroundCountries = '')
    {
        if (!$this->options) {
            $attrs = $this->customerFactory->create()->getAttributes();
            foreach ($attrs as $attr) {
                if (in_array($attr->getAttributeCode(), $this->hiddenAttrs)) {
                    continue;
                }
                $this->options[] = [
                    "label" => $attr->getName(),
                    "value" => $attr->getAttributeCode()
                ];
            }
        }

        $options = $this->options;
        if (!$isMultiselect) {
            array_unshift($options, ['value' => '', 'label' => __('--Please Select--')]);
        }
        return $options;
    }
}
