<?php
namespace MyCompany\GoogleFeed\Controller\Adminhtml\Category;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Escaper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use MyCompany\GoogleFeed\Model\GoogleCategoryStorage;

class Picker extends Action implements HttpGetActionInterface
{
    const ADMIN_RESOURCE = 'MyCompany_GoogleFeed::config';

    /**
     * @var RawFactory
     */
    private $rawFactory;

    /**
     * @var GoogleCategoryStorage
     */
    private $googleCategoryStorage;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var Escaper
     */
    private $escaper;

    public function __construct(
        Context $context,
        RawFactory $rawFactory,
        GoogleCategoryStorage $googleCategoryStorage,
        ScopeConfigInterface $scopeConfig,
        Escaper $escaper
    ) {
        parent::__construct($context);
        $this->rawFactory = $rawFactory;
        $this->googleCategoryStorage = $googleCategoryStorage;
        $this->scopeConfig = $scopeConfig;
        $this->escaper = $escaper;
    }

    public function execute()
    {
        $storeId = (int)$this->getRequest()->getParam('store', 0);
        $field = (string)$this->getRequest()->getParam('field', 'category[mycompany_google_product_category]');
        $selectedId = (int)$this->getRequest()->getParam('selected', 0);

        $locale = (string)$this->scopeConfig->getValue(
            'general/locale/code',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $options = $this->googleCategoryStorage->getOptionsByLocale($locale ?: 'en_US');

        $html = $this->renderHtml($options, $field, $selectedId);

        $result = $this->rawFactory->create();
        $result->setHeader('Content-Type', 'text/html; charset=UTF-8', true);

        return $result->setContents($html);
    }

    /**
     * @param array $options
     * @param string $fieldName
     * @param int $selectedId
     * @return string
     */
    private function renderHtml(array $options, $fieldName, $selectedId)
    {
        $fieldJs = json_encode($fieldName);
        $rows = [];

        foreach ($options as $option) {
            $value = $this->escaper->escapeHtmlAttr((string)$option['value']);
            $label = $this->escaper->escapeHtml((string)$option['label']);
            $labelJs = json_encode((string)$option['label']);
            $isSelected = (int)$option['value'] === $selectedId;
            $rowStyle = $isSelected ? ' style="background:#eef6ff;"' : '';
            $selectedBadge = $isSelected
                ? '<span style="display:inline-block;margin-left:8px;padding:2px 6px;background:#007bdb;color:#fff;border-radius:10px;font-size:11px;">Current</span>'
                : '';
            $rows[] = '<tr>'
                . $rowStyle
                . '<td style="padding:6px 8px;white-space:nowrap;">' . $value . '</td>'
                . '<td style="padding:6px 8px;">' . $label . $selectedBadge . '</td>'
                . '<td style="padding:6px 8px;">'
                . '<button type="button" class="action-default" onclick="pickCategory(' . $value . ', ' . $labelJs . ')">Select</button>'
                . '</td>'
                . '</tr>';
        }

        return '<!doctype html>'
            . '<html><head><meta charset="utf-8"><title>Google Category Picker</title></head>'
            . '<body style="font-family:Arial,sans-serif;padding:12px;">'
            . '<h2 style="margin:0 0 10px;">Google Product Category Picker</h2>'
            . '<p style="margin:0 0 12px;color:#666;">Select a category to apply it to the current form field.</p>'
            . '<p id="selectedValue" style="margin:0 0 12px;color:#444;"></p>'
            . '<input type="text" id="search" placeholder="Search..." onkeyup="filterRows()" style="width:100%;padding:8px;margin-bottom:10px;">'
            . '<div style="max-height:540px;overflow:auto;border:1px solid #ddd;">'
            . '<table id="categoryTable" style="width:100%;border-collapse:collapse;">'
            . '<thead><tr style="background:#f7f7f7;"><th style="text-align:left;padding:8px;">ID</th><th style="text-align:left;padding:8px;">Category</th><th style="text-align:left;padding:8px;">Action</th></tr></thead>'
            . '<tbody>' . implode('', $rows) . '</tbody></table></div>'
            . '<script>'
            . 'var targetField=' . $fieldJs . ';'
            . 'var currentSelected=' . (int)$selectedId . ';'
            . 'if(currentSelected>0){document.getElementById("selectedValue").textContent="Current selected ID: "+currentSelected;}'
            . 'function pickCategory(id,label){'
            . 'if(window.opener&&!window.opener.closed){'
            . 'var doc=window.opener.document;'
            . 'var el=doc.querySelector("[name=\""+targetField+"\"]");'
            . 'if(el){el.value=String(id);el.dispatchEvent(new Event("change",{bubbles:true}));window.close();return;}'
            . '}'
            . 'alert("Unable to set value in opener window.");'
            . '}'
            . 'function filterRows(){'
            . 'var q=document.getElementById("search").value.toLowerCase();'
            . 'var rows=document.querySelectorAll("#categoryTable tbody tr");'
            . 'rows.forEach(function(r){r.style.display=r.textContent.toLowerCase().indexOf(q)!==-1?"":"none";});'
            . '}'
            . '</script>'
            . '</body></html>';
    }
}
