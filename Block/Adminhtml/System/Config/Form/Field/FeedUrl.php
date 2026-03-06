<?php
namespace MyCompany\GoogleFeed\Block\Adminhtml\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Backend\Block\Template\Context;
use Magento\Store\Model\StoreManagerInterface;

class FeedUrl extends Field
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param array $data
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->storeManager = $storeManager;
        parent::__construct($context, $data);
    }

    /**
     * Get element HTML
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $html = '<div style="padding: 15px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px;">';
        
        // Get all stores grouped by website
        $websites = $this->storeManager->getWebsites();
        
        foreach ($websites as $website) {
            $stores = $website->getStores();
            
            if (count($stores) > 0) {
                $html .= '<div style="margin-bottom: 20px;">';
                $html .= '<h4 style="margin: 0 0 10px 0; color: #333;">' . $this->escapeHtml($website->getName()) . '</h4>';
                
                foreach ($stores as $store) {
                    $storeCode = $store->getCode();
                    $storeName = $store->getName();
                    $baseUrl = $this->getNormalizedStoreBaseUrl($store);
                    $feedUrl = rtrim($baseUrl, '/') . '/googlefeed/feed/index?store=' . rawurlencode($storeCode);
                    
                    $uniqueId = 'googlefeed_url_' . $store->getId();
                    $messageId = 'copy_message_' . $store->getId();
                    
                    $html .= '<div style="margin-bottom: 15px; padding: 10px; background: #fff; border: 1px solid #e0e0e0; border-radius: 3px;">';
                    
                    // Store name and code
                    $html .= '<div style="margin-bottom: 8px; display: flex; align-items: center; gap: 10px;">';
                    $html .= '<strong style="color: #555;">' . $this->escapeHtml($storeName) . '</strong>';
                    $html .= '<span style="padding: 2px 8px; background: #e3f2fd; color: #1976d2; border-radius: 3px; font-size: 11px; font-family: monospace;">';
                    $html .= $this->escapeHtml($storeCode);
                    $html .= '</span>';
                    $html .= '</div>';
                    
                    // URL input and buttons
                    $html .= '<div style="display: flex; gap: 8px; align-items: center;">';
                    $html .= '<input type="text" readonly value="' . $this->escapeHtmlAttr($feedUrl) . '" ';
                    $html .= 'id="' . $uniqueId . '" ';
                    $html .= 'style="flex: 1; padding: 6px 10px; border: 1px solid #ccc; border-radius: 3px; background: #fafafa; font-family: monospace; font-size: 12px;" />';
                    $html .= '<button type="button" class="action-default scalable" onclick="copyUrl(\'' . $uniqueId . '\', \'' . $messageId . '\')" style="padding: 6px 12px;">';
                    $html .= '<span>' . __('Copy') . '</span>';
                    $html .= '</button>';
                    $html .= '<a href="' . $this->escapeUrl($feedUrl) . '" target="_blank" class="action-default scalable" style="padding: 6px 12px; text-decoration: none;">';
                    $html .= '<span>' . __('Open') . '</span>';
                    $html .= '</a>';
                    $html .= '</div>';
                    
                    // Copy success message
                    $html .= '<div id="' . $messageId . '" style="margin-top: 6px; color: #2e7d32; font-size: 12px; display: none;">';
                    $html .= '✓ ' . __('URL copied to clipboard!');
                    $html .= '</div>';
                    
                    $html .= '</div>';
                }
                
                $html .= '</div>';
            }
        }
        
        $html .= '<div style="margin-top: 15px; padding: 10px; background: #e3f2fd; border-left: 4px solid #1976d2; border-radius: 3px;">';
        $html .= '<strong style="color: #1565c0;">' . __('Multi-Language Setup:') . '</strong><br/>';
        $html .= '<span style="color: #555; font-size: 13px;">';
        $html .= __('Each store view has its own feed URL with the <code>?store=CODE</code> parameter. ');
        $html .= __('Use these URLs in Google Merchant Center to create separate feeds for each language/country.');
        $html .= '</span>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        $html .= '<script>
        function copyUrl(inputId, messageId) {
            var urlInput = document.getElementById(inputId);
            urlInput.select();
            urlInput.setSelectionRange(0, 99999);
            
            try {
                document.execCommand("copy");
                var message = document.getElementById(messageId);
                message.style.display = "block";
                setTimeout(function() {
                    message.style.display = "none";
                }, 3000);
            } catch (err) {
                alert("' . __('Failed to copy URL. Please copy manually.') . '");
            }
        }
        </script>';
        
        return $html;
    }

    /**
     * @param \Magento\Store\Api\Data\StoreInterface|\Magento\Store\Model\Store $store
     * @return string
     */
    protected function getNormalizedStoreBaseUrl($store)
    {
        $baseUrl = (string)$store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
        $storeCode = trim((string)$store->getCode(), '/');

        if ($storeCode === '') {
            return rtrim($baseUrl, '/') . '/';
        }

        $parts = parse_url($baseUrl);
        if ($parts === false || empty($parts['host'])) {
            return rtrim($baseUrl, '/') . '/';
        }

        $host = $parts['host'];
        $path = isset($parts['path']) ? trim((string)$parts['path'], '/') : '';

        if ($path === '') {
            $hostSuffix = substr($host, -strlen($storeCode));
            if ($hostSuffix === $storeCode && strlen($host) > strlen($storeCode)) {
                $normalizedHost = substr($host, 0, -strlen($storeCode));
                if ($normalizedHost !== '') {
                    $host = $normalizedHost;
                }
            }

            $path = $storeCode;
        }

        $normalizedUrl = '';
        if (!empty($parts['scheme'])) {
            $normalizedUrl .= $parts['scheme'] . '://';
        }

        if (!empty($parts['user'])) {
            $normalizedUrl .= $parts['user'];
            if (!empty($parts['pass'])) {
                $normalizedUrl .= ':' . $parts['pass'];
            }
            $normalizedUrl .= '@';
        }

        $normalizedUrl .= $host;

        if (!empty($parts['port'])) {
            $normalizedUrl .= ':' . $parts['port'];
        }

        $normalizedUrl .= '/' . trim($path, '/') . '/';

        return $normalizedUrl;
    }
}
