<?php

/**
 * Copyright © Alexandru-Manuel Carabus All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Hawksama\Notice\Observer;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Hawksama\Notice\Query\Notice\GetListQuery;

class AddAttributesToQuoteItem implements ObserverInterface
{
    public function __construct(
        private readonly GetListQuery $getListQuery,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductResource $productResource
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var QuoteItem $quoteItem */
        $quoteItem = $observer->getEvent()->getQuoteItem();
        $productId = $quoteItem->getProductId();
        $quoteProduct = $observer->getEvent()->getProduct();

        $product = $this->productRepository->getById($productId);

        $rules = $this->getActiveNoticeRules();

        foreach ($rules as $rule) {
            $attributeCode = $rule->getProductAttribute();
            $attributeValue = $this->getProductAttributeValue($product, $attributeCode);

            if ($attributeValue !== null) {
                $quoteProduct->setData($attributeCode, $attributeValue);
            }
        }
    }

    private function getActiveNoticeRules(): array
    {
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('enabled', true)->create();
        $searchResults = $this->getListQuery->execute($searchCriteria);

        return $searchResults->getItems();
    }

    /**
     * @throws LocalizedException
     */
    private function getProductAttributeValue(ProductInterface $product, string $attributeCode): null|bool|string
    {
        /** @var \Magento\Framework\DataObject $product */
        $value = $product->getData($attributeCode);

        if (empty($value)) {
            return null;
        }

        $attribute = $this->productResource->getAttribute($attributeCode);

        if ($attribute && $attribute->usesSource()) {
            return $attribute->getSource()->getOptionText($value);
        }

        return (string) $value;
    }
}
