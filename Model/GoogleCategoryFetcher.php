<?php
namespace MyCompany\GoogleFeed\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class GoogleCategoryFetcher
{
    const TAXONOMY_URL_PATTERN = 'https://www.google.com/basepages/producttype/taxonomy-with-ids.%s.txt';
    const XML_PATH_TAXONOMY_URL = 'googlefeed/taxonomy/taxonomy_url';
    const XML_PATH_CUSTOM_URLS = 'googlefeed/taxonomy/custom_taxonomy_urls';
    const XML_PATH_FALLBACK_LOCALE = 'googlefeed/taxonomy/fallback_locale';

    /**
     * @var Curl
     */
    private $curl;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var GoogleCategoryStorage
     */
    private $googleCategoryStorage;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var array
     */
    private $customUrls = null;

    /**
     * @param Curl $curl
     * @param LoggerInterface $logger
     * @param GoogleCategoryStorage $googleCategoryStorage
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Curl $curl,
        LoggerInterface $logger,
        GoogleCategoryStorage $googleCategoryStorage,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->curl = $curl;
        $this->logger = $logger;
        $this->googleCategoryStorage = $googleCategoryStorage;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param string $localeCode
     * @return array
     * @throws LocalizedException
     */
    public function fetch($localeCode)
    {
        $normalizedLocale = $this->googleCategoryStorage->normalizeLocaleCode($localeCode);
        $candidates = $this->getLocaleCandidates($normalizedLocale);
        
        $this->logger->info(sprintf(
            'GoogleCategoryFetcher: Starting fetch for locale "%s". Will try %d candidate(s): %s',
            $normalizedLocale,
            count($candidates),
            implode(', ', $candidates)
        ));

        foreach ($candidates as $index => $candidateLocale) {
            $url = $this->getTaxonomyUrl($candidateLocale);
            $attemptNum = $index + 1;
            
            $this->logger->info(sprintf(
                'GoogleCategoryFetcher: Attempt %d/%d - Trying URL: %s',
                $attemptNum,
                count($candidates),
                $url
            ));

            try {
                $this->curl->setTimeout(30);
                $this->curl->get($url);
                
                $httpStatus = (int)$this->curl->getStatus();
                $bodySize = strlen((string)$this->curl->getBody());

                if ($httpStatus !== 200) {
                    $this->logger->warning(sprintf(
                        'GoogleCategoryFetcher: HTTP %d received from %s - skipping',
                        $httpStatus,
                        $url
                    ));
                    continue;
                }

                $this->logger->info(sprintf(
                    'GoogleCategoryFetcher: HTTP 200 OK - Downloaded %s bytes from %s',
                    number_format($bodySize),
                    $url
                ));

                $rows = $this->parse((string)$this->curl->getBody());
                
                if (empty($rows)) {
                    $this->logger->warning(sprintf(
                        'GoogleCategoryFetcher: Parsing failed - no valid categories found in response from %s',
                        $url
                    ));
                    continue;
                }

                $this->logger->info(sprintf(
                    'GoogleCategoryFetcher: Successfully parsed %d categories from %s (locale: %s)',
                    count($rows),
                    $url,
                    $candidateLocale
                ));

                return [
                    'locale_code' => $candidateLocale,
                    'source_url' => $url,
                    'rows' => $rows,
                ];
            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    'GoogleCategoryFetcher: Exception while fetching from %s - %s',
                    $url,
                    $e->getMessage()
                ));
            }
        }

        $this->logger->error(sprintf(
            'GoogleCategoryFetcher: All attempts failed for locale "%s". Tried URLs: %s',
            $normalizedLocale,
            implode(', ', array_map(function($c) { return sprintf(self::TAXONOMY_URL_PATTERN, $c); }, $candidates))
        ));

        throw new LocalizedException(__('Unable to download Google Product Category taxonomy.'));
    }

    /**
     * @param string $content
     * @return array
     */
    private function parse($content)
    {
        $rows = [];
        $lines = preg_split('/\r\n|\r|\n/', $content);

        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            if (!preg_match('/^(\d+)\s*-\s*(.+)$/', $line, $matches)) {
                continue;
            }

            $rows[] = [
                'google_category_id' => (int)$matches[1],
                'category_path' => trim($matches[2]),
            ];
        }

        return $rows;
    }

    /**
     * @param string $localeCode
     * @return array
     */
    private function getLocaleCandidates($localeCode)
    {
        $language = strtolower(substr($localeCode, 0, 2));
        $candidates = [
            $localeCode,
            $language,
            'en-US',
        ];

        return array_values(array_unique($candidates));
    }

    /**
     * Get taxonomy URL for specific locale from configuration
     *
     * @param string $localeCode
     * @return string
     */
    private function getTaxonomyUrl($localeCode)
    {
        // Check custom URLs first
        $customUrls = $this->loadCustomUrls();
        if (isset($customUrls[$localeCode])) {
            $this->logger->info(sprintf(
                'GoogleCategoryFetcher: Using custom URL for locale "%s"',
                $localeCode
            ));
            return $customUrls[$localeCode];
        }

        // Get URL pattern from config or use default
        $urlPattern = $this->scopeConfig->getValue(
            self::XML_PATH_TAXONOMY_URL,
            ScopeInterface::SCOPE_STORE
        );

        if (empty($urlPattern)) {
            $urlPattern = self::TAXONOMY_URL_PATTERN;
        }

        return sprintf($urlPattern, $localeCode);
    }

    /**
     * Load and parse custom taxonomy URLs from configuration
     *
     * @return array
     */
    private function loadCustomUrls()
    {
        if ($this->customUrls !== null) {
            return $this->customUrls;
        }

        $this->customUrls = [];
        $customUrlsConfig = $this->scopeConfig->getValue(
            self::XML_PATH_CUSTOM_URLS,
            ScopeInterface::SCOPE_STORE
        );

        if (empty($customUrlsConfig)) {
            return $this->customUrls;
        }

        // Parse custom URLs (format: locale_code=URL, one per line)
        $lines = preg_split('/\r\n|\r|\n/', $customUrlsConfig);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '=') === false) {
                continue;
            }

            list($locale, $url) = explode('=', $line, 2);
            $locale = trim($locale);
            $url = trim($url);

            if (!empty($locale) && !empty($url)) {
                $this->customUrls[$locale] = $url;
                $this->logger->info(sprintf(
                    'GoogleCategoryFetcher: Loaded custom URL for locale "%s": %s',
                    $locale,
                    $url
                ));
            }
        }

        return $this->customUrls;
    }
}
