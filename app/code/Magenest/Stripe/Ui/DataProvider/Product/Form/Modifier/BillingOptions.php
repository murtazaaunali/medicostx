<?php
/**
 * Created by Magenest.
 * Author: Pham Quang Hau
 * Date: 06/07/2016
 * Time: 23:12
 */

namespace Magenest\Stripe\Ui\DataProvider\Product\Form\Modifier;

use Magento\Catalog\Model\Locator\LocatorInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\ProductOptions\ConfigInterface;
use Magento\Catalog\Model\Config\Source\Product\Options\Price as ProductOptionsPrice;
use Magento\Framework\UrlInterface;
use Magento\Framework\Stdlib\ArrayManager;
use Magento\Ui\Component\Container;
use Magento\Ui\Component\DynamicRows;
use Magento\Ui\Component\Form\Fieldset;
use Magento\Ui\Component\Form\Field;
use Magento\Ui\Component\Form\Element\Input;
use Magento\Ui\Component\Form\Element\Select;
use Magento\Ui\Component\Form\Element\DataType\Text;
use Magento\Ui\Component\Form\Element\DataType\Number;
use Magenest\Stripe\Model\AttributeFactory;
use Psr\Log\LoggerInterface;

class BillingOptions extends \Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier
{
    /**#@+
     * Group values
     */
    const GROUP_BILLING_OPTIONS_NAME = 'stripe_billing_options';
    const GROUP_BILLING_OPTIONS_SCOPE = 'data.product';
    // name of tab that precedes this billing options tab
    const GROUP_BILLING_OPTIONS_PREVIOUS_NAME = 'stripe-subscription';
    const GROUP_BILLING_OPTIONS_DEFAULT_SORT_ORDER = 200;
    /**#@-*/

    /**#@+
     * Button values
     */
    const BUTTON_ADD = 'button_add';
    /**#@-*/

    /**#@+
     * Container values
     */
    const CONTAINER_HEADER_NAME = 'stripe_container_header';
    const CONTAINER_OPTION = 'stripe_container_option';
    const CONTAINER_COMMON_NAME = 'stripe_container_common';
    const CONTAINER_TYPE_TRIAL_NAME = 'stripe_container_type_trial';
    const CONTAINER_TYPE_INIT_NAME = 'stripe_container_type_init';
    /**#@-*/

    /**#@+
     * Grid values
     */
    const GRID_OPTIONS_NAME = 'stripe_billing_options';
    const GRID_TYPE_SELECT_NAME = 'stripe_billing_values';
    /**#@-*/

    /**#@+
     * Field values
     */
    const FIELD_TITLE_NAME = 'billing_title';
    const FIELD_ENABLE = 'affect_product_custom_options';
    const FIELD_OPTION_ID = 'option_id';
    const FIELD_PLAN_ID = 'plan_id';
    const FIELD_UNIT_NAME = 'unit_id';
    const FIELD_FREQUENCY_NAME = 'frequency';
    const FIELD_SORT_ORDER_NAME = 'sort_order';
    const FIELD_IS_DELETE = 'is_delete';
    /**#@-*/

    /**#@+
     * Trial Field values
     */
    const FIELD_IS_TRIAL_ENABLED = 'is_trial_enabled';
    const FIELD_TRIAL_DAY_NAME = 'trial_day';
    /**#@-*/

    /**#@+
     * Import options values
     */
    const CUSTOM_OPTIONS_LISTING = 'product_custom_options_listing';
    /**#@-*/

    protected $meta = [];
    protected $logger;
    protected $locator;
    protected $storeManager;
    protected $productOptionsConfig;
    protected $productOptionsPrice;
    protected $urlBuilder;
    protected $arrayManager;
    protected $attrFactory;

    public function __construct(
        LocatorInterface $locator,
        StoreManagerInterface $storeManager,
        ConfigInterface $productOptionsConfig,
        ProductOptionsPrice $productOptionsPrice,
        UrlInterface $urlBuilder,
        ArrayManager $arrayManager,
        LoggerInterface $loggerInterface,
        AttributeFactory $attributeFactory
    ) {
        $this->locator = $locator;
        $this->storeManager = $storeManager;
        $this->productOptionsConfig = $productOptionsConfig;
        $this->productOptionsPrice = $productOptionsPrice;
        $this->urlBuilder = $urlBuilder;
        $this->arrayManager = $arrayManager;
        $this->logger = $loggerInterface;
        $this->attrFactory = $attributeFactory;
    }

    public function modifyData(array $data)
    {
        $productId = $this->locator->getProduct()->getId();

        $attrModel = $this->attrFactory->create();
        $attr = $attrModel->getCollection()->addFieldToFilter('entity_id', $productId)->getFirstItem();

        if (!$attr->getId()) {
            return $data;
        }

        $billingOptions = unserialize($attr->getValue());

        $data[strval($productId)]['product']['stripe_billing_options'] = $billingOptions;

        return $data;
    }

    public function modifyMeta(array $meta)
    {
        $this->meta = $meta;

        $this->createStripeSubscriptionPanel();

        return $this->meta;
    }

    protected function createStripeSubscriptionPanel()
    {
        $this->meta = array_replace_recursive(
            $this->meta,
            [
                static::GROUP_BILLING_OPTIONS_NAME => [
                    'arguments' => [
                        'data' => [
                            'config' => [
                                'label' => __('Stripe Subscription Billing Options'),
                                'componentType' => Fieldset::NAME,
                                'dataScope' => static::GROUP_BILLING_OPTIONS_SCOPE,
                                'collapsible' => true,
                                'sortOrder' => $this->getNextGroupSortOrder(
                                    $this->meta,
                                    static::GROUP_BILLING_OPTIONS_PREVIOUS_NAME,
                                    static::GROUP_BILLING_OPTIONS_DEFAULT_SORT_ORDER
                                ),
                            ],
                        ],
                    ],
                    'children' => [
                        static::CONTAINER_HEADER_NAME => $this->getHeaderContainerConfig(10),
                        static::FIELD_ENABLE => $this->getEnableFieldConfig(20),
                        static::GRID_OPTIONS_NAME => $this->getOptionsGridConfig(30)
                    ]
                ]
            ]
        );

        return $this;
    }

    protected function getHeaderContainerConfig($sortOrder)
    {
        return [
            'arguments' => [
                'data' => [
                    'config' => [
                        'label' => null,
                        'formElement' => Container::NAME,
                        'componentType' => Container::NAME,
                        'template' => 'ui/form/components/complex',
                        'sortOrder' => $sortOrder,
                        'content' => __('Configure multiple subscription plans for your Stripe Payment Gateway.'),
                    ],
                ],
            ],
            'children' => [
                static::BUTTON_ADD => [
                    'arguments' => [
                        'data' => [
                            'config' => [
                                'title' => __('Add Option'),
                                'formElement' => Container::NAME,
                                'componentType' => Container::NAME,
                                'component' => 'Magento_Ui/js/form/components/button',
                                'sortOrder' => 20,
                                'actions' => [
                                    [
                                        'targetName' => "product_form.product_form.stripe_billing_options.stripe_billing_options",
                                        'actionName' => 'processingAddChild',
                                    ]
                                ]
                            ]
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function getEnableFieldConfig($sortOrder)
    {
        return [
            'arguments' => [
                'data' => [
                    'config' => [
                        'formElement' => Field::NAME,
                        'componentType' => Input::NAME,
                        'dataScope' => static::FIELD_ENABLE,
                        'dataType' => Number::NAME,
                        'visible' => false,
                        'sortOrder' => $sortOrder,
                    ],
                ],
            ],
        ];
    }

    protected function getOptionsGridConfig($sortOrder)
    {
        return [
            'arguments' => [
                'data' => [
                    'config' => [
                        'addButtonLabel' => __('Add Option'),
                        'componentType' => DynamicRows::NAME,
                        'component' => 'Magento_Catalog/js/components/dynamic-rows-import-custom-options',
                        'template' => 'ui/dynamic-rows/templates/collapsible',
                        'additionalClasses' => 'admin__field-wide',
                        'deleteProperty' => static::FIELD_IS_DELETE,
                        'deleteValue' => '1',
                        'addButton' => false,
                        'renderDefaultRecord' => false,
                        'columnsHeader' => false,
                        'collapsibleHeader' => true,
                        'sortOrder' => $sortOrder,
                        'dataProvider' => static::CUSTOM_OPTIONS_LISTING,
                        'links' => ['insertData' => '${ $.provider }:${ $.dataProvider }'],
                    ],
                ],
            ],
            'children' => [
                'record' => [
                    'arguments' => [
                        'data' => [
                            'config' => [
                                'headerLabel' => __('New Plan'),
                                'componentType' => Container::NAME,
                                'component' => 'Magento_Ui/js/dynamic-rows/record',
                                'positionProvider' => static::CONTAINER_OPTION . '.' . static::FIELD_SORT_ORDER_NAME,
                                'isTemplate' => true,
                                'is_collection' => true,
                            ],
                        ],
                    ],
                    'children' => [
                        static::CONTAINER_OPTION => [
                            'arguments' => [
                                'data' => [
                                    'config' => [
                                        'componentType' => Fieldset::NAME,
                                        'label' => null,
                                        'sortOrder' => 10,
                                        'opened' => true,
                                    ],
                                ],
                            ],
                            'children' => [
                                static::FIELD_SORT_ORDER_NAME => $this->getPositionFieldConfig(10),
                                static::CONTAINER_COMMON_NAME => $this->getMainContainerConfig(20),
                                static::CONTAINER_TYPE_TRIAL_NAME => $this->getTrialTypeContainerConfig(30),
                            ]
                        ],
                    ]
                ]
            ]
        ];
    }

    protected function getPositionFieldConfig($sortOrder)
    {
        return [
            'arguments' => [
                'data' => [
                    'config' => [
                        'componentType' => Field::NAME,
                        'formElement' => Input::NAME,
                        'dataScope' => static::FIELD_SORT_ORDER_NAME,
                        'dataType' => Number::NAME,
                        'visible' => false,
                        'sortOrder' => $sortOrder,
                    ],
                ],
            ],
        ];
    }

    protected function getMainContainerConfig($sortOrder)
    {
        $commonContainer = [
            'arguments' => [
                'data' => [
                    'config' => [
                        'componentType' => Container::NAME,
                        'formElement' => Container::NAME,
                        'component' => 'Magento_Ui/js/form/components/group',
                        'breakLine' => false,
                        'showLabel' => false,
                        'additionalClasses' => 'admin__field-group-columns admin__control-group-equal',
                        'sortOrder' => $sortOrder,
                    ],
                ],
            ],
            'children' => [
                static::FIELD_OPTION_ID => $this->getOptionIdFieldConfig(10),
                static::FIELD_PLAN_ID => $this->getInputFieldConfig(
                    20,
                    __('Plan ID'),
                    static::FIELD_PLAN_ID,
                    Text::NAME,
                    true
                ),
                static::FIELD_UNIT_NAME => $this->getUnitFieldConfig(
                    30,
                    __('Period Unit'),
                    true
                ),
                static::FIELD_FREQUENCY_NAME => $this->getInputFieldConfig(
                    40,
                    __('Billing Frequency'),
                    static::FIELD_FREQUENCY_NAME,
                    Number::NAME,
                    true
                ),
                static::FIELD_IS_TRIAL_ENABLED => $this->getIsTrialEnabledFieldConfig(50),
            ]
        ];

        if ($this->locator->getProduct()->getStoreId()) {
            $useDefaultConfig = [
                'service' => [
                    'template' => 'Magento_Catalog/form/element/helper/custom-option-service',
                ]
            ];
            $titlePath = $this->arrayManager->findPath(static::FIELD_TITLE_NAME, $commonContainer, null)
                . static::META_CONFIG_PATH;
            $commonContainer = $this->arrayManager->merge($titlePath, $commonContainer, $useDefaultConfig);
        }

        return $commonContainer;
    }

    protected function getOptionIdFieldConfig($sortOrder)
    {
        return [
            'arguments' => [
                'data' => [
                    'config' => [
                        'formElement' => Input::NAME,
                        'componentType' => Field::NAME,
                        'dataScope' => static::FIELD_OPTION_ID,
                        'sortOrder' => $sortOrder,
                        'visible' => false,
                    ],
                ],
            ],
        ];
    }

    protected function getInputFieldConfig($sortOrder, $label, $scope, $type, $isRequired)
    {
        $validateNumber = true;
        if ($type == Text::NAME) {
            $validateNumber = false;
        }

        $options = [
            'arguments' => [
                'data' => [
                    'config' => [
                        'label' => $label,
                        'component' => 'Magento_Catalog/component/static-type-input',
                        'valueUpdate' => 'input'
                    ],
                ],
            ],
        ];

        return array_replace_recursive(
            [
                'arguments' => [
                    'data' => [
                        'config' => [
                            'label' => $label,
                            'componentType' => Field::NAME,
                            'formElement' => Input::NAME,
                            'dataScope' => $scope,
                            'dataType' => $type,
                            'sortOrder' => $sortOrder,
                            'validation' => [
                                'required-entry' => $isRequired,
                                'validate-zero-or-greater' => $validateNumber
                            ],
                        ],
                    ],
                ],
            ],
            $options
        );
    }

    protected function getUnitFieldConfig($sortOrder, $label, $isRequired)
    {
        $options = [
            'arguments' => [
                'data' => [
                    'config' => [
                        'label' => $label,
                        'valueUpdate' => 'input'
                    ],
                ],
            ],
        ];

        return array_replace_recursive(
            [
                'arguments' => [
                    'data' => [
                        'config' => [
                            'label' => $label,
                            'componentType' => Field::NAME,
                            'dataType' => Text::NAME,
                            'formElement' => Select::NAME,
                            'dataScope' => static::FIELD_UNIT_NAME,
                            'options' => $this->getUnitOptions(),
                            'sortOrder' => $sortOrder,
                            'visible' => true,
                            'validation' => [
                                'required-entry' => $isRequired
                            ],
                        ],
                    ],
                ],
            ],
            $options
        );
    }

    protected function getIsTrialEnabledFieldConfig($sortOrder)
    {
        return [
            'arguments' => [
                'data' => [
                    'config' => [
                        'label' => __('Trial Enabled'),
                        'componentType' => Field::NAME,
                        'formElement' => Select::NAME,
                        'component' => 'Magento_Catalog/js/custom-options-type',
                        'elementTmpl' => 'ui/grid/filters/elements/ui-select',
                        'selectType' => 'optgroup',
                        'dataScope' => static::FIELD_IS_TRIAL_ENABLED,
                        'dataType' => Text::NAME,
                        'sortOrder' => $sortOrder,
                        'options' => $this->getTrialEnabledConfig(),
                        'disableLabel' => true,
                        'multiple' => false,
                        'selectedPlaceholders' => [
                            'defaultPlaceholder' => __('-- Please select --'),
                        ],
                        'validation' => [
                            'required-entry' => true
                        ],
                        'groupsConfig' => [
                            'options' => [
                                'values' => ['yes'],
                                'indexes' => [
                                    static::CONTAINER_TYPE_TRIAL_NAME,
                                    static::FIELD_TRIAL_DAY_NAME
                                ]
                            ]
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function getTrialTypeContainerConfig($sortOrder)
    {
        return [
            'arguments' => [
                'data' => [
                    'config' => [
                        'componentType' => Container::NAME,
                        'formElement' => Container::NAME,
                        'component' => 'Magento_Ui/js/form/components/group',
                        'breakLine' => false,
                        'showLabel' => false,
                        'additionalClasses' => 'admin__field-group-columns admin__control-group-equal',
                        'sortOrder' => $sortOrder,
                    ],
                ],
            ],
            'children' => [
                static::FIELD_TRIAL_DAY_NAME => $this->getTrialAmountFieldConfig(10),
            ]
        ];
    }

    protected function getTrialAmountFieldConfig($sortOrder)
    {
        return [
            'arguments' => [
                'data' => [
                    'config' => [
                        'label' => __('Trial Period (Day)'),
                        'componentType' => Field::NAME,
                        'formElement' => Input::NAME,
                        'dataScope' => static::FIELD_TRIAL_DAY_NAME,
                        'dataType' => Number::NAME,
                        'sortOrder' => $sortOrder,
                        'validation' => [
                            'validate-zero-or-greater' => true
                        ],
                    ],
                ],
            ],
        ];
    }


    protected function getTrialEnabledConfig()
    {
        return [
            [
                'value' => 0,
                'label' => 'Options',
                'optgroup' => [
                    [
                        'label' => 'Yes',
                        'value' => 'yes'
                    ],
                    [
                        'label' => 'No',
                        'value' => 'no'
                    ]
                ]
            ]
        ];
    }

    protected function getUnitOptions()
    {
        return [
            [
                'label' => 'Day',
                'value' => 'day'
            ],
            [
                'label' => 'Week',
                'value' => 'week'
            ],
            [
                'label' => 'Month',
                'value' => 'month'
            ],
            [
                'label' => 'Year',
                'value' => 'year'
            ]
        ];
    }
}
