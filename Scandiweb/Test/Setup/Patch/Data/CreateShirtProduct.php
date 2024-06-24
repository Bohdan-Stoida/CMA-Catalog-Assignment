<?php

namespace Scandiweb\Test\Setup\Patch\Data;

use Exception;
use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Eav\Setup\EavSetup;
use Magento\Framework\App\State;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\StateException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Validation\ValidationException;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\Store\Model\StoreManagerInterface;

class CreateShirtProduct implements DataPatchInterface
{
    /**
     * @var SourceItemInterface[]
     */
    protected array $sourceItems = [];

    /**
     * CreateShirtProduct constructor.
     *
     * @param ProductInterfaceFactory $productFactory
     * @param ProductRepositoryInterface $productRepository
     * @param State $appState
     * @param EavSetup $eavSetup
     * @param StoreManagerInterface $storeManager
     * @param SourceItemInterfaceFactory $sourceItemFactory
     * @param SourceItemsSaveInterface $sourceItemsSave
     * @param CategoryLinkManagementInterface $categoryLinkManagement
     * @param CollectionFactory $categoryCollectionFactory
     */
    public function __construct(
        protected ProductInterfaceFactory $productFactory,
        protected ProductRepositoryInterface $productRepository,
        protected State $appState,
        protected EavSetup $eavSetup,
        protected StoreManagerInterface $storeManager,
        protected SourceItemInterfaceFactory $sourceItemFactory,
        protected SourceItemsSaveInterface $sourceItemsSave,
        protected CategoryLinkManagementInterface $categoryLinkManagement,
        protected CollectionFactory $categoryCollectionFactory
    ) {
    }

    /**
     * @return string[]
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * @return void
     * @throws Exception
     */
    public function apply(): void
    {
        $this->appState->emulateAreaCode('adminhtml', [$this, 'execute']);
    }

    /**
     * @return void
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws StateException
     * @throws ValidationException
     */
    public function execute(): void
    {
        $product = $this->createProduct();
        $this->createSourceItem($product);
        $this->assignProductToCategories($product, ['Men']);
    }

    /**
     * @return Product
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws StateException
     */
    private function createProduct(): Product
    {
        $product = $this->productFactory->create();

        if ($product->getIdBySku('shirt-1')) {
            return $product;
        }

        $product->setTypeId(Type::TYPE_SIMPLE)
            ->setAttributeSetId($this->eavSetup->getAttributeSetId(Product::ENTITY, 'Default'))
            ->setName('Shirt')
            ->setSku('shirt-1')
            ->setPrice(100)
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED)
            ->setCustomAttribute('description', 'Cool product! Buy it now!')
            ->setUrlKey('shirt')
            ->setWebsiteIds([$this->storeManager->getStore()->getWebsiteId()])
            ->setStockData(['use_config_manage_stock' => 1, 'is_qty_decimal' => 0]);

        return $this->productRepository->save($product);
    }

    /**
     * @param Product $product
     * @return void
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws ValidationException
     */
    private function createSourceItem(Product $product): void
    {
        $sourceItem = $this->sourceItemFactory->create();
        $sourceItem->setSourceCode('default');
        $sourceItem->setSku($product->getSku());
        $sourceItem->setQuantity(100);
        $sourceItem->setStatus(SourceItemInterface::STATUS_IN_STOCK);

        $this->sourceItems[] = $sourceItem;
        $this->sourceItemsSave->execute($this->sourceItems);
    }

    /**
     * @param Product $product
     * @param array $categoryTitles
     * @return void
     * @throws LocalizedException
     */
    private function assignProductToCategories(Product $product, array $categoryTitles): void
    {
        $categoryIds = $this->categoryCollectionFactory->create()
            ->addAttributeToFilter('name', ['in' => $categoryTitles])
            ->getColumnValues('entity_id');

        $this->categoryLinkManagement->assignProductToCategories($product->getSku(), $categoryIds);
    }
}
