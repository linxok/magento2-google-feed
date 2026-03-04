<?php
namespace MyCompany\GoogleFeed\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;

class GoogleCategoryFetcher
{
    const TAXONOMY_URL_PATTERN = 'https://www.google.com/basepages/producttype/taxonomy-with-ids.%s.txt';

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
     * @param Curl $curl
     * @param LoggerInterface $logger
     * @param GoogleCategoryStorage $googleCategoryStorage
     */
    public function __construct(
        Curl $curl,
        LoggerInterface $logger,
        GoogleCategoryStorage $googleCategoryStorage
    ) {
        $this->curl = $curl;
        $this->logger = $logger;
        $this->googleCategoryStorage = $googleCategoryStorage;
    }

    /**
     * @param string $localeCode
     * @return array
     * @throws LocalizedException
     */
    public function fetch($localeCode)
    {
        $normalizedLocale = $this->googleCategoryStorage->normalizeLocaleCode($localeCode);

        foreach ($this->getLocaleCandidates($normalizedLocale) as $candidateLocale) {
            $url = sprintf(self::TAXONOMY_URL_PATTERN, $candidateLocale);

            try {
                $this->curl->setTimeout(30);
                $this->curl->get($url);

                if ((int)$this->curl->getStatus() !== 200) {
                    continue;
                }

                $rows = $this->parse((string)$this->curl->getBody());
                if (empty($rows)) {
                    continue;
                }

                return [
                    'locale_code' => $candidateLocale,
                    'source_url' => $url,
                    'rows' => $rows,
                ];
            } catch (\Exception $e) {
                $this->logger->warning('Unable to fetch Google taxonomy from ' . $url . ': ' . $e->getMessage());
            }
        }

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
}
