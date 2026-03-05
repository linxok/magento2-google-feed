<?php
namespace MyCompany\GoogleFeed\Console\Command;

use Magento\Framework\App\State;
use Magento\Store\Model\StoreManagerInterface;
use MyCompany\GoogleFeed\Model\GoogleCategoryFetcher;
use MyCompany\GoogleFeed\Model\GoogleCategoryStorage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

class ImportGoogleTaxonomy extends Command
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var GoogleCategoryFetcher
     */
    private $categoryFetcher;

    /**
     * @var GoogleCategoryStorage
     */
    private $categoryStorage;

    /**
     * @var State
     */
    private $state;

    /**
     * @param StoreManagerInterface $storeManager
     * @param GoogleCategoryFetcher $categoryFetcher
     * @param GoogleCategoryStorage $categoryStorage
     * @param State $state
     * @param string|null $name
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        GoogleCategoryFetcher $categoryFetcher,
        GoogleCategoryStorage $categoryStorage,
        State $state,
        string $name = null
    ) {
        $this->storeManager = $storeManager;
        $this->categoryFetcher = $categoryFetcher;
        $this->categoryStorage = $categoryStorage;
        $this->state = $state;
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName('mycompany:googlefeed:import-taxonomy')
            ->setDescription('Import Google Product Category Taxonomy for all store views');
        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
        } catch (\Exception $e) {
        }

        $output->writeln('<info>Starting Google Product Category Taxonomy import...</info>');
        $output->writeln('');

        $stores = $this->storeManager->getStores(true);
        $localesProcessed = [];
        $totalStores = count($stores);
        $currentStore = 0;

        $output->writeln(sprintf('<comment>Found %d store view(s) to analyze</comment>', $totalStores));
        $output->writeln('');

        foreach ($stores as $store) {
            $currentStore++;
            $storeId = $store->getId();
            $storeName = $store->getName();
            $storeCode = $store->getCode();
            $localeCode = $store->getConfig('general/locale/code');

            $output->writeln(sprintf(
                '[%d/%d] <info>Store:</info> %s (ID: %s, Code: %s)',
                $currentStore,
                $totalStores,
                $storeName,
                $storeId,
                $storeCode
            ));
            $output->writeln(sprintf('        <info>Locale:</info> %s', $localeCode));

            $normalizedLocale = $this->categoryStorage->normalizeLocaleCode($localeCode);

            if (in_array($normalizedLocale, $localesProcessed)) {
                $output->writeln(sprintf(
                    '        <comment>Skipped - locale "%s" already processed</comment>',
                    $normalizedLocale
                ));
                $output->writeln('');
                continue;
            }

            $output->writeln(sprintf('        <info>Normalized locale:</info> %s', $normalizedLocale));
            $output->writeln('');

            try {
                $startTime = microtime(true);
                $result = $this->categoryFetcher->fetch($normalizedLocale);

                if (empty($result['rows'])) {
                    $output->writeln('        <error>Failed - no data received</error>');
                    $output->writeln('');
                    continue;
                }

                $downloadTime = round(microtime(true) - $startTime, 2);
                $categoryCount = count($result['rows']);
                
                $output->writeln(sprintf('        <info>Download URL:</info> %s', $result['source_url']));
                $output->writeln(sprintf('        <info>Download time:</info> %s seconds', $downloadTime));
                $output->writeln(sprintf('        <info>Categories found:</info> %d', $categoryCount));
                $output->writeln(sprintf('        <info>Locale used:</info> %s', $result['locale_code']));
                $output->writeln('');
                
                $output->write('        <info>Saving to database...</info> ');
                
                $saveStartTime = microtime(true);
                $this->categoryStorage->saveTaxonomy($result['locale_code'], $result['rows']);
                $saveTime = round(microtime(true) - $saveStartTime, 2);

                $output->writeln(sprintf('<info>Done (%s seconds)</info>', $saveTime));

                $localesProcessed[] = $result['locale_code'];
            } catch (\Exception $e) {
                $output->writeln(sprintf('        <error>Error: %s</error>', $e->getMessage()));
            }

            $output->writeln('');
        }

        $output->writeln('');
        $output->writeln('<info>Import completed!</info>');
        $output->writeln(sprintf(
            '<comment>Processed %d unique locale(s): %s</comment>',
            count($localesProcessed),
            implode(', ', $localesProcessed)
        ));

        return \Symfony\Component\Console\Command\Command::SUCCESS;
    }
}
