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
        $selectedId = (int)$this->getRequest()->getParam('selected', 0);

        $locale = (string)$this->scopeConfig->getValue(
            'general/locale/code',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $tree = $this->googleCategoryStorage->getTreeByLocale($locale ?: 'en_US');

        $html = $this->renderHtml($tree, $selectedId);

        $result = $this->rawFactory->create();
        $result->setHeader('Content-Type', 'text/html; charset=UTF-8', true);

        return $result->setContents($html);
    }

    /**
     * Recursively build collapsible <details>/<summary> tree HTML
     *
     * @param array $nodes
     * @param int $selectedId
     * @return string
     */
    private function buildTreeHtml(array $nodes, $selectedId)
    {
        if (empty($nodes)) {
            return '';
        }

        $html = '<ul class="gc-tree-list">';

        foreach ($nodes as $node) {
            $id = (int)$node['value'];
            $name = $this->escaper->escapeHtml((string)$node['label']);
            $nameJs = json_encode((string)$node['label']);
            $hasChildren = !empty($node['optgroup']);
            $isSelected = ($id === $selectedId);
            $selectedClass = $isSelected ? ' gc-selected' : '';

            if ($hasChildren) {
                $html .= '<li class="gc-has-children">';
                $html .= '<details' . ($isSelected ? ' open' : '') . '>';
                $html .= '<summary class="gc-node' . $selectedClass . '" data-id="' . $id . '" data-name=' . $nameJs . '>';
                $html .= '<span class="gc-node-label">' . $name . '</span>';
                $html .= '<span class="gc-node-id">' . $id . '</span>';
                $html .= '<button type="button" class="gc-pick-btn" data-id="' . $id . '" data-label=' . $nameJs . '>Select</button>';
                $html .= '</summary>';
                $html .= $this->buildTreeHtml($node['optgroup'], $selectedId);
                $html .= '</details>';
                $html .= '</li>';
            } else {
                $html .= '<li class="gc-leaf">';
                $html .= '<div class="gc-node' . $selectedClass . '" data-id="' . $id . '" data-name=' . $nameJs . '>';
                $html .= '<span class="gc-node-label">' . $name . '</span>';
                $html .= '<span class="gc-node-id">' . $id . '</span>';
                $html .= '<button type="button" class="gc-pick-btn" data-id="' . $id . '" data-label=' . $nameJs . '>Select</button>';
                $html .= '</div>';
                $html .= '</li>';
            }
        }

        $html .= '</ul>';

        return $html;
    }

    /**
     * @param array $tree
     * @param int $selectedId
     * @return string
     */
    private function renderHtml(array $tree, $selectedId)
    {
        $treeHtml = $this->buildTreeHtml($tree, $selectedId);
        $selectedIdJs = (int)$selectedId;

        return <<<HTML
<style>
#gc-picker-wrap{display:flex;flex-direction:column;height:100%}
#gc-search-wrap{padding:8px 0 8px 0;flex-shrink:0}
#gc-search{width:100%;padding:7px 10px;border:1px solid #bbb;border-radius:3px;font-size:13px;box-sizing:border-box}
#gc-info{font-size:12px;color:#666;margin-top:4px;min-height:16px}
#gc-tree{overflow-y:auto;flex:1;padding-top:4px}
.gc-tree-list{list-style:none;padding-left:16px}
#gc-tree>.gc-tree-list{padding-left:0}
.gc-node{display:flex;align-items:center;padding:4px 6px;border-radius:3px;gap:6px;cursor:default}
.gc-node:hover{background:#f0f7ff}
.gc-selected{background:#dbeeff!important;font-weight:600}
summary.gc-node{cursor:pointer;list-style:none}
summary.gc-node::-webkit-details-marker{display:none}
summary.gc-node::before{content:'▶';font-size:9px;color:#999;min-width:10px;transition:transform .15s}
details[open]>summary.gc-node::before{transform:rotate(90deg)}
.gc-node-label{flex:1;word-break:break-word}
.gc-node-id{color:#999;font-size:11px;white-space:nowrap}
.gc-pick-btn{padding:2px 8px;font-size:11px;background:#1979c3;color:#fff;border:none;border-radius:2px;cursor:pointer;white-space:nowrap;flex-shrink:0}
.gc-pick-btn:hover{background:#105fa0}
.gc-hidden{display:none!important}
.gc-highlight{background:rgba(255,220,0,0.4);border-radius:2px}
</style>
<div id="gc-picker-wrap">
  <div id="gc-search-wrap">
    <input type="text" id="gc-search" placeholder="Search categories..." autocomplete="off">
    <div id="gc-info"></div>
  </div>
  <div id="gc-tree">{$treeHtml}</div>
</div>
HTML;
    }
}

