<?php
namespace Sd\StoreLocator\Controller\Adminhtml\Grid;

class Index extends \Magento\Backend\App\Action
{
    const ACL_RESOURCE = 'Sd_StoreLocator::storelocator_grid';
    const MENU_ITEM = 'Sd_StoreLocator::storelocator_grid';
    const TITLE = 'StoreLocator Grid';

    protected function _isAllowed()
    {
        $result = parent::_isAllowed();
        $result = $result && $this->_authorization->isAllowed(self::ACL_RESOURCE);
        return $result;
    }

    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu(self::MENU_ITEM);
        $resultPage->getConfig()->getTitle()->prepend(__(self::TITLE));
        return $resultPage;
    }
}
