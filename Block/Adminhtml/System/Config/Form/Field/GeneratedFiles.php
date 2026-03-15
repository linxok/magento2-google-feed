<?php
namespace MyCompany\GoogleFeed\Block\Adminhtml\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Store\Model\StoreManagerInterface;

class GeneratedFiles extends Field
{
    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param Filesystem $filesystem
     * @param StoreManagerInterface $storeManager
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        Filesystem $filesystem,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->filesystem = $filesystem;
        $this->storeManager = $storeManager;
        parent::__construct($context, $data);
    }

    /**
     * Remove scope label
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Get generated files HTML
     *
     * @return string
     */
    protected function _getGeneratedFilesHtml()
    {
        $files = $this->getGeneratedFiles();
        
        if (empty($files)) {
            return '<div class="message message-notice">' . 
                   __('No feed files generated yet. Use cron or manual generation to create files.') . 
                   '</div>';
        }

        $html = '<div class="generated-files-list" style="margin-top: 10px;">';
        $html .= '<p><strong>' . __('Generated Feed Files:') . '</strong></p>';
        $html .= '<ul style="list-style: none; padding: 0;">';

        foreach ($files as $file) {
            $html .= '<li style="padding: 8px; border-bottom: 1px solid #e3e3e3;">';
            $html .= '<strong>' . $this->escapeHtml($file['name']) . '</strong> ';
            $html .= '<span style="color: #666;">(' . $this->formatBytes($file['size']) . ', ';
            $html .= $this->formatTimestamp($file['modified']) . ')</span> ';
            $html .= '<a href="' . $this->escapeUrl($file['url']) . '" target="_blank" style="margin-left: 10px;">';
            $html .= __('Download') . '</a>';
            $html .= '</li>';
        }

        $html .= '</ul>';
        $html .= '<p style="margin-top: 10px; color: #666;"><em>' . 
                 __('Files are located in pub/media/googlefeed/') . 
                 '</em></p>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Get list of generated feed files
     *
     * @return array
     */
    protected function getGeneratedFiles()
    {
        $files = [];
        
        try {
            $mediaDirectory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
            $feedPath = 'googlefeed';
            
            if (!$mediaDirectory->isDirectory($feedPath)) {
                return $files;
            }

            $fileList = $mediaDirectory->read($feedPath);
            
            foreach ($fileList as $filePath) {
                if ($mediaDirectory->isFile($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) === 'xml') {
                    $stat = $mediaDirectory->stat($filePath);
                    $fileName = basename($filePath);
                    
                    $files[] = [
                        'name' => $fileName,
                        'path' => $filePath,
                        'size' => $stat['size'],
                        'modified' => $stat['mtime'],
                        'url' => $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . $filePath
                    ];
                }
            }
            
            // Sort by modification time, newest first
            usort($files, function($a, $b) {
                return $b['modified'] - $a['modified'];
            });
            
        } catch (\Exception $e) {
            // Silently fail if directory doesn't exist
        }

        return $files;
    }

    /**
     * Format bytes to human readable format
     *
     * @param int $bytes
     * @return string
     */
    protected function formatBytes($bytes)
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' B';
        }
    }

    /**
     * Format timestamp to readable date
     *
     * @param int $timestamp
     * @return string
     */
    protected function formatTimestamp($timestamp)
    {
        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Render element HTML
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $html = $this->_getGeneratedFilesHtml();
        // Wrap in a container to prevent XML parsing issues
        return '<div style="padding: 10px; background: #f8f8f8; border: 1px solid #ddd;">' . $html . '</div>';
    }
}
