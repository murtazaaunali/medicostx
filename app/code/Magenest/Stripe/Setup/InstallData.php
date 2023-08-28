<?php
/**
 * Created by Magenest.
 * Author: Pham Quang Hau
 * Date: 19/05/2016
 * Time: 20:36
 */

namespace Magenest\Stripe\Setup;

use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class InstallData implements InstallDataInterface
{
    private $eavSetupFactory;

    public function __construct(
        EavSetupFactory $eavSetupFactory
    ) {
        $this->eavSetupFactory = $eavSetupFactory;
    }

    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);

        $setup->startSetup();

        $eavSetup->addAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            'stripe_enable',
            [
                'group' => 'Stripe Subscription',
                'type' => 'int',
                'backend' => '',
                'frontend' => '',
                'sort_order' => 10,
                'label' => 'Enable Subscription',
                'input' => 'boolean',
                'class' => '',
                'source' => 'Magento\Eav\Model\Entity\Attribute\Source\Boolean',
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'required' => false,
                'user_defined' => false,
                'default' => '',
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => false,
                'unique' => false,
                'apply_to' => 'simple,virtual,downloadable,configurable'
            ]
        );

        $setup->endSetup();
    }
}
